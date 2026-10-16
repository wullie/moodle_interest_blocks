<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Event observers for block_interestflag: keep the flags table tidy when the
 * things it points at are deleted.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\block_interestflag\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\course_section_deleted',
        'callback'  => '\block_interestflag\observer::course_section_deleted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback'  => '\block_interestflag\observer::user_deleted',
    ],
];
