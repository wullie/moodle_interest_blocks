<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestfeed\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use block_interestfeed\feed_manager;

/**
 * Privacy provider for block_interestfeed. Both tables are keyed by course module,
 * so data is reported against module contexts.
 *
 * Seen rows are deleted on request. Change rows are kept, because they drive other
 * users' feeds, but the user ID is anonymised to 0.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(feed_manager::CHANGES, [
            'userid'      => 'privacy:metadata:block_interestfeed_changes:userid',
            'timechanged' => 'privacy:metadata:block_interestfeed_changes:timechanged',
        ], 'privacy:metadata:block_interestfeed_changes');

        $collection->add_database_table(feed_manager::SEEN, [
            'userid'   => 'privacy:metadata:block_interestfeed_seen:userid',
            'cmid'     => 'privacy:metadata:block_interestfeed_seen:cmid',
            'timeseen' => 'privacy:metadata:block_interestfeed_seen:timeseen',
        ], 'privacy:metadata:block_interestfeed_seen');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $params      = ['modlevel' => CONTEXT_MODULE, 'userid' => $userid];
        $contextlist = new contextlist();

        foreach ([feed_manager::SEEN, feed_manager::CHANGES] as $table) {
            $contextlist->add_from_sql(
                "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {{$table}} t ON t.cmid = ctx.instanceid AND ctx.contextlevel = :modlevel
                  WHERE t.userid = :userid", $params);
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', "SELECT userid FROM {" . feed_manager::SEEN . "} WHERE cmid = :cmid", $params);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {" . feed_manager::CHANGES . "} WHERE cmid = :cmid AND userid > 0", $params);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cmid   = $context->instanceid;
            $seen   = $DB->get_record(feed_manager::SEEN, ['userid' => $userid, 'cmid' => $cmid]);
            $change = $DB->get_record(feed_manager::CHANGES, ['userid' => $userid, 'cmid' => $cmid]);
            if (!$seen && !$change) {
                continue;
            }

            $data = (object) [];
            if ($seen) {
                $data->timeseen = transform::datetime($seen->timeseen);
            }
            if ($change) {
                $data->lastchangedbyyou = transform::datetime($change->timechanged);
            }
            writer::with_context($context)->export_data([get_string('privacy:path', 'block_interestfeed')], $data);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $DB->delete_records(feed_manager::SEEN, ['cmid' => $context->instanceid]);
        $DB->set_field(feed_manager::CHANGES, 'userid', 0, ['cmid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $DB->delete_records(feed_manager::SEEN, ['userid' => $userid, 'cmid' => $context->instanceid]);
            $DB->set_field(feed_manager::CHANGES, 'userid', 0, ['userid' => $userid, 'cmid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;

        $DB->delete_records_select(feed_manager::SEEN, "cmid = :cmid AND userid $insql", $params);
        $DB->set_field_select(feed_manager::CHANGES, 'userid', 0, "cmid = :cmid AND userid $insql", $params);
    }
}
