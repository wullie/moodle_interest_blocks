<?php
// This file is part of Moodle - http://moodle.org/
namespace block_interestflag\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use block_interestflag\flag_manager;

/**
 * Privacy provider for block_interestflag. Flags are stored against the course context.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(flag_manager::TABLE, [
            'userid'      => 'privacy:metadata:block_interestflag_flags:userid',
            'courseid'    => 'privacy:metadata:block_interestflag_flags:courseid',
            'sectionid'   => 'privacy:metadata:block_interestflag_flags:sectionid',
            'timecreated' => 'privacy:metadata:block_interestflag_flags:timecreated',
        ], 'privacy:metadata:block_interestflag_flags');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_interestflag_flags} f ON f.courseid = ctx.instanceid AND ctx.contextlevel = :courselevel
                 WHERE f.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {block_interestflag_flags} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $flags = $DB->get_records(flag_manager::TABLE, ['userid' => $userid, 'courseid' => $context->instanceid]);
            if (!$flags) {
                continue;
            }
            $data = array_map(fn($f) => (object) [
                'sectionid'   => $f->sectionid,
                'scope'       => $f->sectionid ? 'section' : 'course',
                'timecreated' => transform::datetime($f->timecreated),
            ], array_values($flags));

            writer::with_context($context)->export_data(
                [get_string('privacy:path', 'block_interestflag')],
                (object) ['flags' => $data]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records(flag_manager::TABLE, ['courseid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records(flag_manager::TABLE, ['userid' => $userid, 'courseid' => $context->instanceid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select(flag_manager::TABLE, "courseid = :courseid AND userid $insql", $params);
    }
}
