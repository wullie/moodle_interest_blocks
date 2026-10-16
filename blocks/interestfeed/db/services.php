<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Web service functions for block_interestfeed.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_interestfeed_dismiss' => [
        'classname'   => 'block_interestfeed\external\dismiss',
        'description' => 'Dismiss one, several or all items from the current user\'s feed, and return the refreshed feed.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
