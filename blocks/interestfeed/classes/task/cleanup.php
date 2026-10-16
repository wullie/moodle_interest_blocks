<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestfeed\task;

use block_interestfeed\feed_manager;

/**
 * Prunes change and seen rows that are well outside the look-back window.
 * Anything older than the window can never appear on the feed, so it is safe to drop.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('taskcleanup', 'block_interestfeed');
    }

    public function execute(): void {
        global $DB;

        // Keep a margin so raising the look-back setting doesn't immediately lose history.
        $days   = max(30, 2 * feed_manager::get_settings()->lookbackdays);
        $cutoff = time() - $days * DAYSECS;

        $DB->delete_records_select(feed_manager::CHANGES, 'timechanged < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select(feed_manager::SEEN, 'timeseen < :cutoff', ['cutoff' => $cutoff]);
    }
}
