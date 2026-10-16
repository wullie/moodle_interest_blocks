<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestflag\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_interestflag\flag_manager;

/**
 * Follow or unfollow a course or section, returning the refreshed block state so the
 * JS can re-render the block from the same Mustache template the server used.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_flag extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'         => new external_value(PARAM_INT,  'Course ID'),
            'sectionid'        => new external_value(PARAM_INT,  'Section ID (0 = whole course)', VALUE_DEFAULT, 0),
            'flagged'          => new external_value(PARAM_BOOL, 'True to follow, false to unfollow'),
            'currentsectionid' => new external_value(PARAM_INT,  'Section being viewed, for highlighting', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $courseid, int $sectionid, bool $flagged, int $currentsectionid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'sectionid', 'flagged', 'currentsectionid'));

        // validate_context() on a course context runs require_login() for that course.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/interestflag:flag', $context);

        $course = get_course($params['courseid']);

        // Unfollowing is always allowed; following needs a section in this course the user can see.
        if ($params['sectionid'] > 0 && $params['flagged']) {
            $section = get_fast_modinfo($course)->get_section_info_by_id($params['sectionid']);
            if (!$section || !$section->uservisible) {
                throw new \invalid_parameter_exception('Section not found or not available.');
            }
        }

        flag_manager::set_flag($USER->id, $course->id, $params['sectionid'], $params['flagged']);

        return flag_manager::get_course_state($course, $USER->id, $params['currentsectionid']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'      => new external_value(PARAM_INT,  'Course ID'),
            'courseflagged' => new external_value(PARAM_BOOL, 'Whole course is followed'),
            'sections'      => new external_multiple_structure(
                new external_single_structure([
                    'id'      => new external_value(PARAM_INT,  'Section ID'),
                    'name'    => new external_value(PARAM_RAW,  'Formatted section name'),
                    'flagged' => new external_value(PARAM_BOOL, 'Section is followed'),
                    'covered' => new external_value(PARAM_BOOL, 'Section is covered by the course flag'),
                    'current' => new external_value(PARAM_BOOL, 'Section is the one being viewed'),
                ])
            ),
            'hassections'   => new external_value(PARAM_BOOL, 'There is at least one section'),
            'dashboardurl'  => new external_value(PARAM_URL,  'Dashboard URL'),
        ]);
    }
}
