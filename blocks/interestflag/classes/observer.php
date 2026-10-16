<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestflag;

/**
 * Removes flags that point at deleted courses, sections or users.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(flag_manager::TABLE, ['courseid' => $event->objectid]);
    }

    public static function course_section_deleted(\core\event\course_section_deleted $event): void {
        global $DB;
        $DB->delete_records(flag_manager::TABLE, ['sectionid' => $event->objectid]);
    }

    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records(flag_manager::TABLE, ['userid' => $event->objectid]);
    }
}
