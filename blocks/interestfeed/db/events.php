<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Event observers for block_interestfeed.
 *
 * \core\event\course_module_viewed is abstract; every activity's own "viewed"
 * event extends it, and Moodle delivers events to observers of parent classes.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\block_interestfeed\observer::course_module_created',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\block_interestfeed\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => '\block_interestfeed\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\block_interestfeed\observer::course_module_viewed',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\block_interestfeed\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback'  => '\block_interestfeed\observer::user_deleted',
    ],
];
