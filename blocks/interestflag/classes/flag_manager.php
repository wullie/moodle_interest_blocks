<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestflag;

/**
 * Reads and writes a user's course/section flags.
 *
 * A flag with sectionid = 0 means "the whole course"; it implicitly covers every
 * section, so the UI shows sections as "Included" while the course is flagged.
 *
 * This class is also the public API consumed by block_interestfeed.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flag_manager {

    /** @var string Flags table. */
    public const TABLE = 'block_interestflag_flags';

    /**
     * Follow or unfollow a course (sectionid 0) or a single section.
     */
    public static function set_flag(int $userid, int $courseid, int $sectionid, bool $flagged): void {
        global $DB;

        $conditions = ['userid' => $userid, 'courseid' => $courseid, 'sectionid' => $sectionid];

        if (!$flagged) {
            $DB->delete_records(self::TABLE, $conditions);
            return;
        }

        if (!$DB->record_exists(self::TABLE, $conditions)) {
            $DB->insert_record(self::TABLE, (object) ($conditions + ['timecreated' => time()]));
        }
    }

    /**
     * Everything a user follows, grouped by course.
     *
     * @return array [courseid => ['course' => bool, 'sections' => [sectionid => true]]]
     */
    public static function get_user_flags(int $userid): array {
        global $DB;

        $result = [];
        $records = $DB->get_recordset(self::TABLE, ['userid' => $userid], '', 'id, courseid, sectionid');
        foreach ($records as $record) {
            $result[$record->courseid] ??= ['course' => false, 'sections' => []];
            if ((int) $record->sectionid === 0) {
                $result[$record->courseid]['course'] = true;
            } else {
                $result[$record->courseid]['sections'][(int) $record->sectionid] = true;
            }
        }
        $records->close();

        return $result;
    }

    /**
     * The section a module sits in, plus any parent sections when it sits inside a
     * delegated section (e.g. a Moodle 5 subsection). Following a top-level section
     * therefore also covers content added to subsections within it.
     *
     * @return int[] Section ids, innermost first.
     */
    public static function get_section_chain(\course_modinfo $modinfo, int $sectionid): array {
        $chain   = [$sectionid];
        $section = $modinfo->get_section_info_by_id($sectionid);

        // Bounded loop so a malformed structure can never spin forever.
        for ($depth = 0; $section && $depth < 10; $depth++) {
            if (!method_exists($section, 'is_delegated') || !$section->is_delegated()) {
                break;
            }
            $delegate = $section->get_component_instance();
            $parent   = null;
            if ($delegate && method_exists($delegate, 'get_parent_section')) {
                $parent = $delegate->get_parent_section();
            } else if ($delegate && method_exists($delegate, 'get_cm') && ($cm = $delegate->get_cm())) {
                $parent = $modinfo->get_section_info_by_id($cm->section);
            }
            if (!$parent) {
                break;
            }
            $chain[] = (int) $parent->id;
            $section = $parent;
        }

        return $chain;
    }

    /**
     * Template context for the course-page block (also returned by the web service).
     *
     * @param \stdClass $course
     * @param int $userid
     * @param int $currentsectionid Section being viewed, highlighted in the list (0 = none).
     */
    public static function get_course_state(\stdClass $course, int $userid, int $currentsectionid = 0): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php'); // For get_section_name() when called via web service.

        $flags   = $DB->get_records_menu(self::TABLE, ['userid' => $userid, 'courseid' => $course->id], '', 'sectionid, id');
        $modinfo = get_fast_modinfo($course, $userid);

        $courseflagged = isset($flags[0]);
        $sections      = [];

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            // Delegated sections (subsections) are covered by their parent section.
            if (method_exists($section, 'is_delegated') && $section->is_delegated()) {
                continue;
            }
            $sections[] = [
                'id'      => (int) $section->id,
                'name'    => get_section_name($course, $section),
                'flagged' => isset($flags[$section->id]),
                'covered' => $courseflagged,
                'current' => (int) $section->id === $currentsectionid,
            ];
        }

        return [
            'courseid'      => (int) $course->id,
            'courseflagged' => $courseflagged,
            'sections'      => $sections,
            'hassections'   => !empty($sections),
            'dashboardurl'  => (new \moodle_url('/my/'))->out(false),
        ];
    }
}
