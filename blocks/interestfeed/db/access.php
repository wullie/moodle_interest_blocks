<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Capabilities for block_interestfeed.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/interestfeed:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
        'clonedfrom'   => 'moodle/site:manageblocks',
    ],
    'block/interestfeed:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
        'clonedfrom'   => 'moodle/my:manageblocks',
    ],
];
