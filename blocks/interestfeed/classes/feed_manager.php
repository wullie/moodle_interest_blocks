<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestfeed;

use block_interestflag\flag_manager;

/**
 * Builds a user's "new in what you follow" feed and records change / seen state.
 *
 * How an item gets onto the feed:
 *   1. A module is created or updated -> observer writes block_interestfeed_changes.
 *   2. The user follows its course, or the (parent) section it sits in -> block_interestflag.
 *   3. The change is within the look-back window, was made by someone else, and is newer
 *      than when the user last opened or dismissed that module (block_interestfeed_seen).
 *   4. The user can currently see the module on the course page (availability, visibility,
 *      enrolment all respected via modinfo / can_access_course()).
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feed_manager {

    /** @var string Change log table. */
    public const CHANGES = 'block_interestfeed_changes';

    /** @var string Seen / dismissed table. */
    public const SEEN = 'block_interestfeed_seen';

    /**
     * Admin settings with defaults applied.
     */
    public static function get_settings(): \stdClass {
        $config  = get_config('block_interestfeed');
        $ignored = $config->ignoredmodules ?? 'subsection,qbank';

        return (object) [
            'lookbackdays'   => max(1, (int) ($config->lookbackdays ?? 14)),
            'maxitems'       => max(1, (int) ($config->maxitems ?? 10)),
            'includeupdated' => (bool) ($config->includeupdated ?? true),
            'ignoredmodules' => array_filter(explode(',', $ignored)),
            'layout'         => ($config->layout ?? 'tiles') === 'list' ? 'list' : 'tiles',
        ];
    }

    /**
     * Record that a module was created or updated.
     */
    public static function record_change(int $courseid, int $cmid, int $userid, bool $created): void {
        global $DB;

        $now      = time();
        $existing = $DB->get_record(self::CHANGES, ['cmid' => $cmid], 'id');

        if ($existing) {
            $DB->update_record(self::CHANGES, (object) [
                'id'          => $existing->id,
                'courseid'    => $courseid,
                'userid'      => $userid,
                'timechanged' => $now,
            ]);
            return;
        }

        // First time we've seen this module (e.g. an old module being edited).
        $timeadded = $created ? $now : (int) $DB->get_field('course_modules', 'added', ['id' => $cmid]);

        $DB->insert_record(self::CHANGES, (object) [
            'courseid'    => $courseid,
            'cmid'        => $cmid,
            'userid'      => $userid,
            'timeadded'   => $timeadded ?: $now,
            'timechanged' => $now,
        ]);
    }

    /**
     * Mark modules as seen by a user (opened or dismissed), hiding them until they change again.
     *
     * @param int $userid
     * @param int[] $cmids
     */
    public static function mark_seen(int $userid, array $cmids): void {
        global $DB;

        $now = time();
        foreach (array_unique(array_map('intval', $cmids)) as $cmid) {
            $existing = $DB->get_record(self::SEEN, ['userid' => $userid, 'cmid' => $cmid], 'id');
            if ($existing) {
                $DB->set_field(self::SEEN, 'timeseen', $now, ['id' => $existing->id]);
            } else {
                $DB->insert_record(self::SEEN, (object) ['userid' => $userid, 'cmid' => $cmid, 'timeseen' => $now]);
            }
        }
    }

    /**
     * Items to show a user, newest first.
     *
     * @param int $userid
     * @param int $limit Maximum items (0 = no limit).
     * @return array ['items' => array[], 'hasmore' => bool, 'hasflags' => bool]
     */
    public static function get_feed(int $userid, int $limit): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $result = ['items' => [], 'hasmore' => false, 'hasflags' => false];

        $flags = flag_manager::get_user_flags($userid);
        if (!$flags) {
            return $result;
        }
        $result['hasflags'] = true;

        $settings  = self::get_settings();
        $cutoff    = time() - $settings->lookbackdays * DAYSECS;
        $timefield = $settings->includeupdated ? 'ch.timechanged' : 'ch.timeadded';

        [$insql, $params] = $DB->get_in_or_equal(array_keys($flags), SQL_PARAMS_NAMED, 'course');
        $params += ['userid' => $userid, 'actor' => $userid, 'cutoff' => $cutoff];

        $sql = "SELECT ch.id, ch.courseid, ch.cmid, ch.timeadded, ch.timechanged, s.timeseen
                  FROM {" . self::CHANGES . "} ch
             LEFT JOIN {" . self::SEEN . "} s ON s.cmid = ch.cmid AND s.userid = :userid
                 WHERE ch.courseid $insql
                   AND $timefield > :cutoff
                   AND ch.userid <> :actor
                   AND (s.id IS NULL OR s.timeseen < $timefield)
              ORDER BY $timefield DESC, ch.id DESC";

        $modinfos = [];  // Course id => course_modinfo, or null if the user can't access it.
        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $row) {
            $courseid = (int) $row->courseid;
            if (!array_key_exists($courseid, $modinfos)) {
                $modinfos[$courseid] = self::get_accessible_modinfo($courseid, $userid);
            }
            if (!$modinfos[$courseid]) {
                continue;
            }

            $item = self::build_item($row, $modinfos[$courseid], $flags[$courseid], $settings, $cutoff);
            if (!$item) {
                continue;
            }
            if ($limit && count($result['items']) >= $limit) {
                $result['hasmore'] = true;
                break;
            }
            $result['items'][] = $item;
        }
        $rs->close();

        return $result;
    }

    /**
     * Template context for the Dashboard block (also returned by the dismiss web service).
     */
    public static function export_for_template(int $userid): array {
        $settings = self::get_settings();
        $feed     = self::get_feed($userid, $settings->maxitems);

        return [
            'items'        => $feed['items'],
            'hasitems'     => !empty($feed['items']),
            'hasmore'      => $feed['hasmore'],
            'hasflags'     => $feed['hasflags'],
            'istiles'      => $settings->layout === 'tiles',
            'maxitems'     => $settings->maxitems,
            'lookbackdays' => $settings->lookbackdays,
        ];
    }

    /**
     * Modinfo for a course if the user can still access it (enrolled and active, or
     * otherwise permitted), else null. Unenrolled users keep their flags, but nothing shows.
     */
    private static function get_accessible_modinfo(int $courseid, int $userid): ?\course_modinfo {
        try {
            $modinfo = get_fast_modinfo($courseid, $userid);
        } catch (\moodle_exception $e) {
            return null;
        }
        return can_access_course($modinfo->get_course(), $userid, '', true) ? $modinfo : null;
    }

    /**
     * Turn a change row into a template item, or null if this user shouldn't see it.
     */
    private static function build_item(\stdClass $row, \course_modinfo $modinfo, array $flags,
            \stdClass $settings, int $cutoff): ?array {

        $cms = $modinfo->get_cms();
        $cm  = $cms[$row->cmid] ?? null;
        if (!$cm || $cm->deletioninprogress || !$cm->uservisible || !$cm->is_visible_on_course_page()) {
            return null;
        }
        if (in_array($cm->modname, $settings->ignoredmodules)) {
            return null;
        }

        // Course flag covers everything; otherwise the module's section (or a parent of it) must be flagged.
        if (!$flags['course']) {
            $chain = flag_manager::get_section_chain($modinfo, (int) $cm->section);
            if (!array_intersect($chain, array_keys($flags['sections']))) {
                return null;
            }
        }

        $course = $modinfo->get_course();
        // Labels and other no-view modules link to their place on the section page.
        $url    = $cm->url ?: new \moodle_url('/course/section.php', ['id' => $cm->section], 'module-' . $cm->id);
        $time   = (int) ($settings->includeupdated ? $row->timechanged : $row->timeadded);

        // "New" = the user has never opened it and it was created inside the window.
        $isnew = !$settings->includeupdated || ($row->timeseen === null && (int) $row->timeadded > $cutoff);

        return [
            'cmid'        => (int) $cm->id,
            'name'        => $cm->get_formatted_name(),
            'url'         => $url->out(false),
            'iconurl'     => $cm->get_icon_url()->out(false),
            'modname'     => (string) $cm->get_module_type_name(),
            // Purpose drives the theme's activity icon colour (as on the course page / Tiles format).
            'purpose'     => (string) plugin_supports('mod', $cm->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER),
            'isbranded'   => (bool) component_callback('mod_' . $cm->modname, 'is_branded', [], false),
            'coursename'  => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'courseurl'   => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'sectionname' => get_section_name($course, $cm->sectionnum),
            'time'        => userdate($time, get_string('strftimedatetimeshort', 'core_langconfig')),
            'isnew'       => $isnew,
        ];
    }
}
