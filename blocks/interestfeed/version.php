<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Version details for block_interestfeed.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'block_interestfeed';
$plugin->version      = 2026101300;
$plugin->requires     = 2025041400; // Moodle 5.0+
$plugin->maturity     = MATURITY_BETA;
$plugin->release      = '1.1.0';
$plugin->dependencies = ['block_interestflag' => 2026100900];
