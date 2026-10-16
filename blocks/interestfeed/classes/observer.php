<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestfeed;

/**
 * Keeps the change log and seen state up to date from core events.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    public static function course_module_created(\core\event\course_module_created $event): void {
        feed_manager::record_change($event->courseid, $event->objectid, (int) $event->userid, true);
    }

    public static function course_module_updated(\core\event\course_module_updated $event): void {
        feed_manager::record_change($event->courseid, $event->objectid, (int) $event->userid, false);
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records(feed_manager::CHANGES, ['cmid' => $event->objectid]);
        $DB->delete_records(feed_manager::SEEN, ['cmid' => $event->objectid]);
    }

    /**
     * Opening a module takes it off the user's feed (until it next changes).
     * Only writes when the module is tracked and the user follows something in that course.
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        global $DB;

        $userid = (int) $event->userid;
        $cmid   = (int) $event->contextinstanceid;
        if (!$userid || !$cmid || isguestuser($userid)) {
            return;
        }
        if (!$DB->record_exists(feed_manager::CHANGES, ['cmid' => $cmid])) {
            return;
        }
        if (!$DB->record_exists(\block_interestflag\flag_manager::TABLE, ['userid' => $userid, 'courseid' => $event->courseid])) {
            return;
        }
        feed_manager::mark_seen($userid, [$cmid]);
    }

    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records_select(feed_manager::SEEN,
            'cmid IN (SELECT cmid FROM {' . feed_manager::CHANGES . '} WHERE courseid = :courseid)',
            ['courseid' => $event->objectid]);
        $DB->delete_records(feed_manager::CHANGES, ['courseid' => $event->objectid]);
    }

    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records(feed_manager::SEEN, ['userid' => $event->objectid]);
        $DB->set_field(feed_manager::CHANGES, 'userid', 0, ['userid' => $event->objectid]);
    }
}
