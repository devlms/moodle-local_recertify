<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_recertify\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: archived certifications and the reminders sent.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Tables with a courseid and a userid. */
    private const TABLES = ['local_recertify_history', 'local_recertify_notice'];

    /**
     * Describe the personal data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_recertify_history', [
            'userid' => 'privacy:metadata:history:userid',
            'timecompleted' => 'privacy:metadata:history:timecompleted',
            'timeexpired' => 'privacy:metadata:history:timeexpired',
            'finalgrade' => 'privacy:metadata:history:finalgrade',
            'archive' => 'privacy:metadata:history:archive',
        ], 'privacy:metadata:history');
        $collection->add_database_table('local_recertify_notice', [
            'userid' => 'privacy:metadata:notice:userid',
            'daysbefore' => 'privacy:metadata:notice:daysbefore',
            'timecompleted' => 'privacy:metadata:notice:timecompleted',
            'timesent' => 'privacy:metadata:notice:timesent',
        ], 'privacy:metadata:notice');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * Courses where the user has history or reminders.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::TABLES as $table) {
            $list->add_from_sql("SELECT ctx.id
                                   FROM {context} ctx
                                   JOIN {{$table}} t ON t.courseid = ctx.instanceid AND ctx.contextlevel = :level
                                  WHERE t.userid = :userid", ['level' => CONTEXT_COURSE, 'userid' => $userid]);
        }
        return $list;
    }

    /**
     * Users with data in a course.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export certifications and reminders, one file per course.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $params = ['courseid' => $context->instanceid, 'userid' => $userid];
            $certifications = array_map(fn($h) => (object) [
                'timecompleted' => transform::datetime($h->timecompleted),
                'timeexpired' => transform::datetime($h->timeexpired),
                'finalgrade' => $h->finalgrade === null ? null : (float) $h->finalgrade,
                'archive' => json_decode($h->archive),
            ], array_values($DB->get_records('local_recertify_history', $params, 'timecompleted')));
            $reminders = array_map(fn($n) => (object) [
                'daysbefore' => (int) $n->daysbefore,
                'timesent' => transform::datetime($n->timesent),
            ], array_values($DB->get_records('local_recertify_notice', $params, 'timesent')));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_recertify')],
                (object) ['certifications' => $certifications, 'reminders' => $reminders]
            );
        }
    }

    /**
     * Delete everything in a course.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof context_course) {
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $context->instanceid]);
            }
        }
    }

    /**
     * Delete one user's data in the approved courses.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                foreach (self::TABLES as $table) {
                    $DB->delete_records($table, ['courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id]);
                }
            }
        }
    }

    /**
     * Delete several users' data in one course.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "courseid = :courseid AND userid $insql", $params);
        }
    }
}
