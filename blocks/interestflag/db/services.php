<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Web service functions for block_interestflag.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_interestflag_set_flag' => [
        'classname'    => 'block_interestflag\external\set_flag',
        'description'  => 'Follow or unfollow a course or course section, and return the updated block state.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'block/interestflag:flag',
    ],
];
