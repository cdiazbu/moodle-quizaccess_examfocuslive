<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Implement the Moodle Privacy API for monitoring data.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\privacy;

use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Provide access to monitoring data through the Moodle Privacy API.
 */
class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** Declare the monitoring data stored by the plugin. */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_examfocuslive_att', [
            'userid' => 'privacy:metadata:attempt:userid',
            'attemptid' => 'privacy:metadata:attempt:attemptid',
            'status' => 'privacy:metadata:attempt:status',
            'focusstate' => 'privacy:metadata:attempt:focusstate',
            'page' => 'privacy:metadata:attempt:page',
            'questionlabel' => 'privacy:metadata:attempt:questionlabel',
            'progress' => 'privacy:metadata:attempt:progress',
            'lastheartbeat' => 'privacy:metadata:attempt:lastheartbeat',
            'lostfocuscount' => 'privacy:metadata:attempt:lostfocuscount',
            'controlrevision' => 'privacy:metadata:attempt:controlrevision',
            'lastviolation' => 'privacy:metadata:attempt:lastviolation',
            'pausedat' => 'privacy:metadata:attempt:pausedat',
            'pausedbyteacher' => 'privacy:metadata:attempt:pausedbyteacher',
            'pausedseconds' => 'privacy:metadata:attempt:pausedseconds',
            'remainingatpause' => 'privacy:metadata:attempt:remainingatpause',
            'decisionby' => 'privacy:metadata:event:actorid',
            'timecreated' => 'privacy:metadata:attempt:timecreated',
            'timemodified' => 'privacy:metadata:attempt:timemodified',
        ], 'privacy:metadata:attempt');
        $collection->add_database_table('quizaccess_examfocuslive_evt', [
            'userid' => 'privacy:metadata:attempt:userid',
            'attemptid' => 'privacy:metadata:attempt:attemptid',
            'eventtype' => 'privacy:metadata:event:eventtype',
            'eventtime' => 'privacy:metadata:event:eventtime',
            'metadata' => 'privacy:metadata:event:metadata',
            'actorid' => 'privacy:metadata:event:actorid',
            'eventtoken' => 'privacy:metadata:event:eventtoken',
        ], 'privacy:metadata:event');
        return $collection;
    }

    /** Find quiz contexts associated with a student or teacher. */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE EXISTS (SELECT 1 FROM {quizaccess_examfocuslive_att} a
                                WHERE a.quizid = cm.instance AND (a.userid = :owner OR a.decisionby = :decider))
                    OR EXISTS (SELECT 1 FROM {quizaccess_examfocuslive_evt} e
                                WHERE e.quizid = cm.instance AND (e.userid = :subject OR e.actorid = :actor))";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'quiz',
            'owner' => $userid,
            'decider' => $userid,
            'subject' => $userid,
            'actor' => $userid,
        ];
        return (new contextlist())->add_from_sql($sql, $params);
    }

    /** Export monitoring data from the approved quiz contexts. */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records('quizaccess_examfocuslive_att', [
                'quizid' => $cm->instance,
                'userid' => $userid,
            ]);
            $events = $DB->get_records('quizaccess_examfocuslive_evt', [
                'quizid' => $cm->instance,
                'userid' => $userid,
            ], 'eventtime ASC, id ASC');
            $decisions = $DB->get_records('quizaccess_examfocuslive_evt', [
                'quizid' => $cm->instance,
                'actorid' => $userid,
            ], 'eventtime ASC, id ASC', 'id, eventtype, eventtime');
            $data = (object) [
                'attempts' => array_values(array_map([self::class, 'export_attempt'], $attempts)),
                'events' => array_values(array_map([self::class, 'export_event'], $events)),
                'decisions' => array_values(array_map(static function (object $record): object {
                    return (object) [
                        'eventtype' => $record->eventtype,
                        'eventtime' => transform::datetime($record->eventtime),
                    ];
                }, $decisions)),
            ];
            writer::with_context($context)->export_data([
                get_string('pluginname', 'quizaccess_examfocuslive'),
            ], $data);
        }
    }

    /** Erase monitoring data for a quiz context. */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('quizaccess_examfocuslive_evt', ['quizid' => $cm->instance]);
        $DB->delete_records('quizaccess_examfocuslive_att', ['quizid' => $cm->instance]);
    }

    /** Erase the user’s monitoring data in approved contexts. */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_users_data((int) $cm->instance, [$userid]);
        }
    }

    /** Find students and decision authors in the quiz context. */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $cm = $context instanceof context_module ? get_coursemodule_from_id('quiz', $context->instanceid) : false;
        if (!$cm) {
            return;
        }
        foreach (['quizaccess_examfocuslive_att', 'quizaccess_examfocuslive_evt'] as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE quizid = :quizid", ['quizid' => $cm->instance]);
        }
        foreach (['quizaccess_examfocuslive_att' => 'decisionby', 'quizaccess_examfocuslive_evt' => 'actorid'] as $table => $field) {
            $userlist->add_from_sql(
                $field,
                "SELECT $field FROM {{$table}} WHERE quizid = :quizid AND $field > 0",
                ['quizid' => $cm->instance]
            );
        }
    }

    /** Erase monitoring data for the approved users. */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        $cm = $context instanceof context_module ? get_coursemodule_from_id('quiz', $context->instanceid) : false;
        if (!$cm || !$userlist->get_userids()) {
            return;
        }
        self::delete_users_data((int) $cm->instance, $userlist->get_userids());
    }

    /**
     * Remove student data and clear references to the selected decision-makers.
     *
     * @param int $quizid Quiz containing the approved context
     * @param int[] $userids Users approved for deletion in this context
     */
    private static function delete_users_data(int $quizid, array $userids): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'eflprivacy');
        $params['quizid'] = $quizid;
        $transaction = $DB->start_delegated_transaction();
        foreach (['quizaccess_examfocuslive_evt' => 'actorid', 'quizaccess_examfocuslive_att' => 'decisionby'] as $table => $field) {
            $DB->delete_records_select($table, "quizid = :quizid AND userid $insql", $params);
            $DB->set_field_select($table, $field, 0, "quizid = :quizid AND $field $insql", $params);
        }
        $transaction->allow_commit();
    }

    /**
     * Format a student's monitoring record for export.
     * @param object $record Monitoring record
     * @return object Exported attempt data
     */
    private static function export_attempt(object $record): object {
        unset($record->id, $record->quizid, $record->userid, $record->decisionby, $record->controlrevision);
        $record->lastheartbeat = transform::datetime($record->lastheartbeat);
        $record->lastviolation = $record->lastviolation ? transform::datetime($record->lastviolation) : null;
        $record->pausedat = $record->pausedat ? transform::datetime($record->pausedat) : null;
        $record->timecreated = transform::datetime($record->timecreated);
        $record->timemodified = transform::datetime($record->timemodified);
        return $record;
    }

    /**
     * Format an event in a student's monitoring history.
     * @param object $record Audit event
     * @return object Exported event data
     */
    private static function export_event(object $record): object {
        unset($record->id, $record->quizid, $record->userid);
        unset($record->eventtoken, $record->actorid);
        $record->eventtime = transform::datetime($record->eventtime);
        return $record;
    }
}
