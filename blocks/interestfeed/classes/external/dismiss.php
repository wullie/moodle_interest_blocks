<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestfeed\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_interestfeed\feed_manager;

/**
 * Dismiss feed items for the current user and return the refreshed feed, so the JS
 * can re-render (pulling in the next item when the list was capped).
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dismiss extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module ID'),
                'Items to dismiss', VALUE_DEFAULT, []
            ),
            'all'   => new external_value(PARAM_BOOL, 'Dismiss everything currently in the feed', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(array $cmids, bool $all): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('cmids', 'all'));

        $context = \context_user::instance($USER->id);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \require_login_exception('Guests have no feed.');
        }

        if ($params['all']) {
            $feed  = feed_manager::get_feed($USER->id, 0);
            $cmids = array_column($feed['items'], 'cmid');
        } else if ($params['cmids']) {
            // Only record modules that are actually tracked.
            [$insql, $inparams] = $DB->get_in_or_equal($params['cmids'], SQL_PARAMS_NAMED);
            $cmids = $DB->get_fieldset_select(feed_manager::CHANGES, 'cmid', "cmid $insql", $inparams);
        } else {
            $cmids = [];
        }

        feed_manager::mark_seen($USER->id, $cmids);

        return feed_manager::export_for_template($USER->id);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'cmid'        => new external_value(PARAM_INT,  'Course module ID'),
                    'name'        => new external_value(PARAM_RAW,  'Formatted module name'),
                    'url'         => new external_value(PARAM_URL,  'Link to the module'),
                    'iconurl'     => new external_value(PARAM_URL,  'Module icon'),
                    'modname'     => new external_value(PARAM_TEXT, 'Module type name'),
                    'coursename'  => new external_value(PARAM_RAW,  'Formatted course name'),
                    'courseurl'   => new external_value(PARAM_URL,  'Link to the course'),
                    'sectionname' => new external_value(PARAM_RAW,  'Formatted section name'),
                    'time'        => new external_value(PARAM_TEXT, 'When it was added or changed'),
                    'isnew'       => new external_value(PARAM_BOOL, 'New (true) or updated (false)'),
                    'purpose'     => new external_value(PARAM_ALPHANUMEXT, 'Module purpose, for icon colour'),
                    'isbranded'   => new external_value(PARAM_BOOL, 'Module icon is branded (not recoloured)'),
                ])
            ),
            'istiles'      => new external_value(PARAM_BOOL, 'Show items as tiles rather than a list'),
            'hasitems'     => new external_value(PARAM_BOOL, 'There is at least one item'),
            'hasmore'      => new external_value(PARAM_BOOL, 'More items exist beyond maxitems'),
            'hasflags'     => new external_value(PARAM_BOOL, 'The user follows at least one course or section'),
            'maxitems'     => new external_value(PARAM_INT,  'Display cap'),
            'lookbackdays' => new external_value(PARAM_INT,  'Look-back window in days'),
        ]);
    }
}
