<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Seed the change log from recently created modules, so the Dashboard is useful
 * straight after install instead of waiting for new content to be added.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_block_interestfeed_install(): bool {
    global $DB;

    $cutoff = time() - 30 * DAYSECS;

    $rs = $DB->get_recordset_select('course_modules', 'added > :cutoff AND deletioninprogress = 0',
        ['cutoff' => $cutoff], '', 'id, course, added');

    $batch = [];
    foreach ($rs as $cm) {
        $batch[] = (object) [
            'courseid'    => $cm->course,
            'cmid'        => $cm->id,
            'userid'      => 0,
            'timeadded'   => $cm->added,
            'timechanged' => $cm->added,
        ];
        if (count($batch) >= 500) {
            $DB->insert_records('block_interestfeed_changes', $batch);
            $batch = [];
        }
    }
    $rs->close();

    if ($batch) {
        $DB->insert_records('block_interestfeed_changes', $batch);
    }

    return true;
}
