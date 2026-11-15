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
 * Manage the state of monitored attempts.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\local;

use context_module;
use core\lock\lock_config;
use mod_quiz\access_manager;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use moodle_exception;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die();

/**
 * Domain service for monitored attempts.
 *
 * All state transitions are centralised here to keep AJAX endpoints small and
 * make pause/resume behaviour atomic and auditable.
 */
class state_manager {
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Return the configuration for one quiz.
     *
     * @param int $quizid
     * @return stdClass|null
     */
    public static function get_quiz_config(int $quizid): ?stdClass {
        global $DB;
        $record = $DB->get_record('quizaccess_efl_quiz', ['quizid' => $quizid, 'enabled' => 1]);
        return $record ?: null;
    }

    /**
     * Check if this quiz can be monitored without running two CD focus rules.
     *
     * @param stdClass $quiz Quiz settings including access rule settings
     * @return bool
     */
    public static function monitoring_is_enabled(stdClass $quiz): bool {
        $enabled = $quiz->examfocuslive_enabled ?? (self::get_quiz_config((int) $quiz->id) !== null);
        return $enabled && empty($quiz->cdexamcontrolenabled) && empty($quiz->cdexamsaveenabled);
    }

    /**
     * Resolve and validate an attempt owned by a student.
     *
     * @param int $attemptid
     * @param int|null $expecteduserid
     * @return array [attempt, quiz, cm, context, config]
     */
    public static function resolve_attempt(int $attemptid, ?int $expecteduserid = null): array {
        global $DB;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if ($expecteduserid !== null && (int) $attempt->userid !== $expecteduserid) {
            throw new moodle_exception('errorinvalidattempt', 'quizaccess_examfocuslive');
        }
        $quiz = access_manager::load_quiz_and_settings((int) $attempt->quiz);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $config = self::get_quiz_config($quiz->id);
        if (!$config || !self::monitoring_is_enabled($quiz)) {
            throw new moodle_exception('errornotenabled', 'quizaccess_examfocuslive');
        }
        return [$attempt, $quiz, $cm, $context, $config];
    }

    /**
     * Create the live state lazily.
     *
     * @param stdClass $attempt
     * @return stdClass
     */
    public static function ensure_live_attempt(stdClass $attempt): stdClass {
        global $DB;
        $record = $DB->get_record('quizaccess_efl_attempt', ['attemptid' => $attempt->id]);
        if ($record) {
            return $record;
        }
        $now = \core\di::get(\core\clock::class)->time();
        $record = (object) [
            'attemptid' => $attempt->id,
            'quizid' => $attempt->quiz,
            'userid' => $attempt->userid,
            'status' => self::is_quiz_attempt_finished($attempt->state) ? self::STATUS_COMPLETED : self::STATUS_ACTIVE,
            'focusstate' => 'focused',
            'page' => 0,
            'progress' => 0,
            'questionlabel' => null,
            'lastheartbeat' => 0,
            'lostfocuscount' => 0,
            'controlrevision' => 0,
            'lastviolation' => 0,
            'pausedat' => 0,
            'pausedbyteacher' => 0,
            'pausedseconds' => 0,
            'remainingatpause' => 0,
            'decisionby' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('quizaccess_efl_attempt', $record);
        return $record;
    }

    /**
     * Serialize changes made by this plugin to an attempt and its audit trail.
     * @param int $attemptid Attempt identifier
     * @param callable $operation Change to run inside the lock and transaction
     * @return stdClass Result of the operation
     */
    private static function with_attempt_lock(int $attemptid, callable $operation): stdClass {
        global $DB;
        $factory = lock_config::get_lock_factory('quizaccess_examfocuslive');
        $lock = $factory->get_lock('attempt:' . $attemptid, 5);
        if (!$lock) {
            throw new moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $operation();
                $transaction->allow_commit();
                return $result;
            } catch (\Throwable $error) {
                $transaction->rollback($error);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Determine whether the attempt owner is subject to monitoring.
     * @param stdClass $attempt Moodle attempt record
     * @param context_module $context Quiz module context
     * @return bool
     */
    private static function can_monitor(stdClass $attempt, context_module $context): bool {
        return empty($attempt->preview) &&
            !has_capability('quizaccess/examfocuslive:exempt', $context, $attempt->userid);
    }

    /**
     * Record a normal browser heartbeat.
     * @param int $attemptid Attempt identifier
     * @param int $userid Owner identifier
     * @param int $page Current zero-based page
     * @param int $progress Estimated progress percentage
     * @param string $questionlabel Current question label
     * @param string $focusstate Reported focus state
     * @return stdClass Client monitoring state
     */
    public static function heartbeat(
        int $attemptid,
        int $userid,
        int $page,
        int $progress,
        string $questionlabel,
        string $focusstate
    ): stdClass {
        global $DB;
        return self::with_attempt_lock($attemptid, static function () use (
            $DB, $attemptid, $userid, $page, $progress, $questionlabel, $focusstate
        ): stdClass {
            [$attempt, , , $context] = self::resolve_attempt($attemptid, $userid);
            if (!self::can_monitor($attempt, $context)) {
                return self::client_state((object) ['status' => self::STATUS_COMPLETED, 'lostfocuscount' => 0]);
            }
            require_capability('mod/quiz:attempt', $context, $userid);
            if ($attempt->state !== 'inprogress') {
                $previous = $DB->get_record('quizaccess_efl_attempt', ['attemptid' => $attemptid]);
                $status = match (true) {
                    ($previous->status ?? '') === self::STATUS_TERMINATED => self::STATUS_TERMINATED,
                    $attempt->state === 'overdue' => 'overdue',
                    default => self::STATUS_COMPLETED,
                };
                return self::client_state((object) [
                    'status' => $status,
                    'lostfocuscount' => $previous->lostfocuscount ?? 0,
                ]);
            }
            $live = self::ensure_live_attempt($attempt);
            $now = \core\di::get(\core\clock::class)->time();
            $updated = (object) [
                'id' => $live->id,
                'page' => max(0, $page),
                'progress' => min(100, max(0, $progress)),
                'questionlabel' => \core_text::substr(clean_param($questionlabel, PARAM_NOTAGS), 0, 100),
                'focusstate' => $focusstate === 'blurred' ? 'blurred' : 'focused',
                'lastheartbeat' => $now,
            ];
            $deadline = null;
            if ($live->status === self::STATUS_PAUSED) {
                $quiz = quiz_settings::create((int) $attempt->quiz, $userid)->get_quiz();
                if ($quiz->timeclose && $now >= $quiz->timeclose) {
                    // Reflect Moodle's expiry policy without waiting for the next cron run.
                    $attemptobj = quiz_attempt::create($attemptid);
                    $attemptobj->handle_if_time_expired($now, false);
                    $status = $attemptobj->get_state() === 'overdue' ? 'overdue' : self::STATUS_COMPLETED;
                    return self::client_state((object) ['status' => $status, 'lostfocuscount' => $live->lostfocuscount]);
                }
            } else if ($live->status === self::STATUS_ACTIVE && (int) $live->controlrevision > 0) {
                $quiz = quiz_settings::create((int) $attempt->quiz, $userid)->get_quiz();
                $ignorelimit = has_capability('mod/quiz:ignoretimelimits', $context, $userid);
                $deadline = self::deadline($attempt, $quiz, $live, $now, $ignorelimit);
            }
            $DB->update_record('quizaccess_efl_attempt', $updated);
            return self::client_state($live, $deadline);
        });
    }

    /**
     * Register a focus-loss incident and optionally pause.
     * @param int $attemptid Attempt identifier
     * @param int $userid Owner identifier
     * @param string $eventtype Browser focus event type
     * @param string $token Client-generated deduplication token
     * @return stdClass Client monitoring state
     */
    public static function focus_lost(int $attemptid, int $userid, string $eventtype, string $token = ''): stdClass {
        global $DB;
        return self::with_attempt_lock($attemptid, static function () use ($DB, $attemptid, $userid, $eventtype, $token): stdClass {
            [$attempt, , , $context, $config] = self::resolve_attempt($attemptid, $userid);
            if (!self::can_monitor($attempt, $context)) {
                return self::client_state((object) ['status' => self::STATUS_COMPLETED, 'lostfocuscount' => 0]);
            }
            require_capability('mod/quiz:attempt', $context, $userid);
            if ($attempt->state !== 'inprogress') {
                $previous = $DB->get_record('quizaccess_efl_attempt', ['attemptid' => $attemptid]);
                $status = match (true) {
                    ($previous->status ?? '') === self::STATUS_TERMINATED => self::STATUS_TERMINATED,
                    $attempt->state === 'overdue' => 'overdue',
                    default => self::STATUS_COMPLETED,
                };
                return self::client_state((object) [
                    'status' => $status,
                    'lostfocuscount' => $previous->lostfocuscount ?? 0,
                ]);
            }
            $live = self::ensure_live_attempt($attempt);
            $now = \core\di::get(\core\clock::class)->time();
            $eventkey = ['attemptid' => $attemptid, 'eventtoken' => $token];
            if ($live->status !== self::STATUS_ACTIVE ||
                    ($token !== '' && $DB->record_exists('quizaccess_efl_event', $eventkey)) ||
                    ($token === '' && $live->lastviolation >= $now - 1)) {
                return self::client_state($live);
            }

            $quiz = quiz_settings::create((int) $attempt->quiz, $userid)->get_quiz();
            $ignorelimit = has_capability('mod/quiz:ignoretimelimits', $context, $userid);
            $deadline = self::deadline($attempt, $quiz, $live, $now, $ignorelimit);
            if (!empty($config->autopause) && ($deadline === null || $deadline > $now)) {
                self::pause_attempt($live, $attempt, $quiz, $now, $ignorelimit, false);
            }
            $live->focusstate = 'blurred';
            $live->lastheartbeat = $now;
            $live->lastviolation = $now;
            $live->lostfocuscount++;
            $live->timemodified = $now;
            $DB->update_record('quizaccess_efl_attempt', $live);
            self::log_event($attempt, $eventtype, $userid, $token);
            if ($live->status === self::STATUS_PAUSED) {
                $deadline = self::deadline($attempt, $quiz, $live, $now, $ignorelimit);
                $DB->set_field('quiz_attempts', 'timecheckstate', $deadline, ['id' => $attempt->id]);
            }
            return self::client_state($live);
        });
    }

    /**
     * Apply a teacher decision to the version of the attempt shown in the panel.
     * @param int $attemptid Attempt identifier
     * @param string $action Pause, resume or terminate
     * @param int $actorid Acting teacher identifier
     * @param int $revision Attempt revision confirmed by the teacher
     * @param int|null $cmid Limit the operation to the quiz displayed on the dashboard
     * @return stdClass Client monitoring state
     */
    public static function control(int $attemptid, string $action, int $actorid, int $revision, ?int $cmid = null): stdClass {
        global $DB;
        if (!in_array($action, ['pause', 'resume', 'terminate'], true)) {
            throw new moodle_exception('errorinvalidaction', 'quizaccess_examfocuslive');
        }
        return self::with_attempt_lock($attemptid, static function () use (
            $DB, $attemptid, $action, $actorid, $revision, $cmid
        ): stdClass {
            [$attempt, , $cm, $context] = self::resolve_attempt($attemptid);
            if ($cmid !== null && (int) $cm->id !== $cmid) {
                throw new moodle_exception('errorinvalidattempt', 'quizaccess_examfocuslive');
            }
            require_capability('quizaccess/examfocuslive:manage', $context);
            require_capability('quizaccess/examfocuslive:monitor', $context);
            $allowed = self::allowed_students($cm, $context, true);
            if (($allowed !== null && !in_array((int) $attempt->userid, $allowed, true)) ||
                    !is_enrolled($context, $attempt->userid, 'mod/quiz:attempt', true) ||
                    !$DB->record_exists('user', ['id' => $attempt->userid, 'deleted' => 0, 'suspended' => 0]) ||
                    !get_fast_modinfo($cm->course, $attempt->userid)->get_cm($cm->id)->uservisible) {
                throw new moodle_exception('errorinvalidattempt', 'quizaccess_examfocuslive');
            }
            if ($attempt->state !== 'inprogress' || !self::can_monitor($attempt, $context)) {
                throw new moodle_exception('staleaction', 'quizaccess_examfocuslive');
            }
            $live = self::ensure_live_attempt($attempt);
            $paused = $live->status === self::STATUS_PAUSED;
            if ($revision !== (int) $live->controlrevision ||
                    (!$paused && $live->status !== self::STATUS_ACTIVE) ||
                    ($action === 'resume' && !$paused) || ($action === 'pause' && $paused)) {
                throw new moodle_exception('staleaction', 'quizaccess_examfocuslive');
            }
            $quiz = quiz_settings::create((int) $attempt->quiz, (int) $attempt->userid)->get_quiz();
            $now = \core\di::get(\core\clock::class)->time();
            $ignorelimit = has_capability('mod/quiz:ignoretimelimits', $context, $attempt->userid);
            $deadline = self::deadline($attempt, $quiz, $live, $now, $ignorelimit);
            if ($deadline !== null && $now >= $deadline) {
                throw new moodle_exception('staleaction', 'quizaccess_examfocuslive');
            }

            if ($action === 'pause') {
                self::pause_attempt($live, $attempt, $quiz, $now, $ignorelimit, true);
            } else {
                if ($paused) {
                    $live->pausedseconds += max(0, $now - $live->pausedat);
                }
                $live->pausedat = 0;
                $live->pausedbyteacher = 0;
                $live->remainingatpause = 0;
                $live->controlrevision++;
                $live->status = $action === 'resume' ? self::STATUS_ACTIVE : self::STATUS_TERMINATED;
            }
            $live->decisionby = $actorid;
            $live->timemodified = $now;
            $DB->update_record('quizaccess_efl_attempt', $live);
            if ($action === 'terminate') {
                self::finish_quiz_attempt($attemptid, $now);
                $deadline = null;
            } else {
                $deadline = self::deadline($attempt, $quiz, $live, $now, $ignorelimit);
                $DB->set_field('quiz_attempts', 'timecheckstate', $deadline, ['id' => $attempt->id]);
            }
            $event = match ($action) {
                'pause' => 'teacherpaused',
                'resume' => 'resumed',
                'terminate' => 'terminated',
            };
            self::log_event($attempt, $event, $actorid);
            return self::client_state($live, $deadline);
        });
    }

    /**
     * Freeze the student's individual time allowance for an active attempt.
     * @param stdClass $live Monitoring record
     * @param stdClass $attempt Moodle attempt record
     * @param stdClass $quiz Effective quiz settings
     * @param int $now Pause timestamp
     * @param bool $ignorelimit Whether the student can ignore the time limit
     * @param bool $manual Whether a teacher initiated the pause
     */
    private static function pause_attempt(
        stdClass $live,
        stdClass $attempt,
        stdClass $quiz,
        int $now,
        bool $ignorelimit,
        bool $manual
    ): void {
        $live->status = self::STATUS_PAUSED;
        $live->pausedat = $now;
        $live->pausedbyteacher = (int) $manual;
        $live->controlrevision++;
        $live->remainingatpause = $ignorelimit || empty($quiz->timelimit) ? 0 :
            (int) $attempt->timestart + (int) $quiz->timelimit + $live->pausedseconds - $now;
    }

    /**
     * Return the student IDs allowed by the activity's group mode.
     * @param stdClass $cm Quiz course module
     * @param context_module $context Quiz module context
     * @param bool $write Apply the management restrictions for visible groups
     * @return int[]|null Allowed students, or null for all groups
     */
    private static function allowed_students(stdClass $cm, context_module $context, bool $write = false): ?array {
        global $DB, $USER;
        $mode = groups_get_activity_groupmode($cm);
        if (has_capability('moodle/site:accessallgroups', $context) ||
                ($mode !== SEPARATEGROUPS && !($write && $mode === VISIBLEGROUPS))) {
            return null;
        }
        $groups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id', false, true);
        if (!$groups) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'eflgroup');
        return array_map('intval', $DB->get_fieldset_select('groups_members', 'DISTINCT userid', "groupid $insql", $params));
    }

    /**
     * Calculate the end time used by Moodle's access manager.
     *
     * @param stdClass $attempt
     * @param stdClass $quiz
     * @param context_module $context Quiz module context
     * @return int|false
     */
    public static function get_attempt_end_time(stdClass $attempt, stdClass $quiz, context_module $context) {
        global $DB;
        if (empty($quiz->timelimit) || has_capability('mod/quiz:ignoretimelimits', $context, $attempt->userid)) {
            return false;
        }
        if (!empty($attempt->preview)) {
            return $attempt->timestart + $quiz->timelimit;
        }
        $live = $DB->get_record('quizaccess_efl_attempt', ['attemptid' => $attempt->id]);
        if (!$live) {
            return $attempt->timestart + $quiz->timelimit;
        }
        if ($attempt->state === 'inprogress' && $live->status === self::STATUS_PAUSED && $live->remainingatpause > 0) {
            return \core\di::get(\core\clock::class)->time() + $live->remainingatpause;
        }
        return $attempt->timestart + $quiz->timelimit + $live->pausedseconds;
    }

    /**
     * Get dashboard data for the latest attempt of each participant.
     *
     * @param int $cmid Quiz course-module identifier
     * @return array
     */
    public static function dashboard(int $cmid): array {
        global $DB, $PAGE;
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cmid);
        require_capability('quizaccess/examfocuslive:monitor', $context);
        $config = self::get_quiz_config($cm->instance);
        if (!$config || !self::monitoring_is_enabled(access_manager::load_quiz_and_settings((int) $cm->instance))) {
            throw new moodle_exception('errornotenabled', 'quizaccess_examfocuslive');
        }

        $params = ['quizid' => $cm->instance];
        $allowed = self::allowed_students($cm, $context);
        $managestudents = self::allowed_students($cm, $context, true);
        $canmanage = has_capability('quizaccess/examfocuslive:manage', $context);
        $groupclause = '';
        if ($allowed === []) {
            $groupclause = 'AND 1 = 0';
        } else if ($allowed !== null) {
            [$insql, $groupparams] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'eflstudent');
            $params += $groupparams;
            $groupclause = "AND u.id $insql";
        }
        $userfields = \core_user\fields::for_name()->with_userpic()->get_sql('u', false, '', '', false)->selects;
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, 'mod/quiz:attempt', 0, true);
        $params += $enrolledparams;
        $sql = "SELECT u.id AS rosterid, $userfields, u.id AS userid, qa.id AS attemptid, qa.state AS quizstate,
                       qa.timestart, qa.timemodified AS quizmodified, qa.timefinish,
                       efl.status, efl.focusstate, efl.page, efl.progress, efl.questionlabel,
                       efl.lastheartbeat, efl.lostfocuscount, efl.controlrevision, efl.pausedat,
                       efl.pausedseconds, efl.pausedbyteacher
                  FROM {user} u
                  JOIN ($enrolledsql) enrolled ON enrolled.id = u.id
             LEFT JOIN {quiz_attempts} qa ON qa.id = (
                         SELECT MAX(qa2.id)
                           FROM {quiz_attempts} qa2
                          WHERE qa2.quiz = :quizid AND qa2.userid = u.id AND qa2.preview = 0
                       )
             LEFT JOIN {quizaccess_efl_attempt} efl ON efl.attemptid = qa.id
                 WHERE u.deleted = 0 AND u.suspended = 0 $groupclause
              ORDER BY u.lastname, u.firstname, u.id";
        $records = $DB->get_records_sql($sql, $params);
        $availability = new \core_availability\info_module(get_fast_modinfo($cm->course)->get_cm($cmid));
        $records = $availability->filter_user_list($records);
        $exempt = $canmanage ? get_users_by_capability($context, 'quizaccess/examfocuslive:exempt', 'u.id', 'u.id') : [];
        $manageable = $managestudents === null ? null : array_fill_keys($managestudents, true);
        $now = \core\di::get(\core\clock::class)->time();
        $students = [];
        foreach ($records as $record) {
            $pictureurl = '';
            if ($config->displaymode !== 'name') {
                $picture = new user_picture($record);
                $picture->size = 100;
                $pictureurl = $picture->get_url($PAGE)->out(false);
            }
            $started = !empty($record->attemptid);
            $status = $started ? ($record->status ?: self::STATUS_ACTIVE) : 'waiting';
            if ($started && self::is_quiz_attempt_finished($record->quizstate) && $status !== self::STATUS_TERMINATED) {
                $status = self::STATUS_COMPLETED;
            } else if ($record->quizstate === 'overdue') {
                $status = 'overdue';
            }
            $lastseen = (int) ($record->lastheartbeat ?: $record->quizmodified);
            if ($status === self::STATUS_ACTIVE && $lastseen < $now - ((int) $config->heartbeat * 4)) {
                $displaystatus = 'offline';
            } else {
                $displaystatus = $status;
            }
            $pausedcurrent = ($status === self::STATUS_PAUSED && !empty($record->pausedat))
                ? $now - $record->pausedat : 0;
            $end = in_array($status, [self::STATUS_COMPLETED, self::STATUS_TERMINATED], true)
                ? ((int) $record->timefinish ?: (int) $record->quizmodified) : $now;
            $students[] = [
                'attemptid' => (int) $record->attemptid,
                'userid' => (int) $record->userid,
                'fullname' => fullname($record),
                'sortname' => trim($record->lastname . ' ' . $record->firstname),
                'pictureurl' => $pictureurl,
                'canmanage' => $canmanage && $record->quizstate === 'inprogress' && !isset($exempt[$record->userid]) &&
                    ($manageable === null || isset($manageable[$record->userid])),
                'revision' => (int) $record->controlrevision,
                'manualpause' => $status === self::STATUS_PAUSED && !empty($record->pausedbyteacher),
                'status' => $displaystatus,
                'rawstatus' => $status,
                'focusstate' => $record->focusstate ?: 'focused',
                'progress' => !empty($config->showprogress) ? (int) ($record->progress ?? 0) : -1,
                'page' => $started ? (int) ($record->page ?? 0) + 1 : 0,
                'questionlabel' => $record->questionlabel ?? '',
                'incidents' => (int) ($record->lostfocuscount ?? 0),
                'lastseen' => $lastseen,
                'secondsago' => $lastseen ? max(0, $now - $lastseen) : 0,
                'elapsed' => $started
                    ? max(0, $end - (int) $record->timestart - (int) $record->pausedseconds - $pausedcurrent) : 0,
            ];
        }
        return [
            'servernow' => $now,
            'displaymode' => $config->displaymode,
            'canmanage' => $canmanage,
            'students' => $students,
        ];
    }

    /**
     * Format the monitoring state returned to JavaScript.
     * @param stdClass $live Monitoring record
     * @param int|null $deadline Effective deadline if applicable
     * @return stdClass
     */
    private static function client_state(stdClass $live, ?int $deadline = null): stdClass {
        return (object) [
            'status' => $live->status,
            'deadline' => $deadline ?? 0,
            'paused' => $live->status === self::STATUS_PAUSED,
            'manualpause' => $live->status === self::STATUS_PAUSED && !empty($live->pausedbyteacher),
            'terminated' => $live->status === self::STATUS_TERMINATED,
            'incidents' => (int) $live->lostfocuscount,
            'servernow' => \core\di::get(\core\clock::class)->time(),
        ];
    }

    /**
     * Compute the effective deadline, including the quiz closing time.
     * @param stdClass $attempt Quiz attempt record
     * @param stdClass $quiz Effective quiz settings
     * @param stdClass $live Monitoring record
     * @param int $now Current timestamp
     * @param bool $ignorelimit Whether the student can ignore the time limit
     * @return int|null Deadline, or null for an untimed quiz
     */
    private static function deadline(
        stdClass $attempt,
        stdClass $quiz,
        stdClass $live,
        int $now,
        bool $ignorelimit
    ): ?int {
        $deadline = null;
        if (!$ignorelimit && !empty($quiz->timelimit)) {
            $deadline = $live->status === self::STATUS_PAUSED
                ? $now + $live->remainingatpause
                : $attempt->timestart + $quiz->timelimit + $live->pausedseconds;
        }
        if (!empty($quiz->timeclose)) {
            $deadline = $deadline === null ? $quiz->timeclose : min($deadline, $quiz->timeclose);
        }
        return $deadline;
    }

    /**
     * Record a focus incident or a teacher decision in the audit history.
     * @param stdClass $attempt Quiz attempt record
     * @param string $eventtype Event type
     * @param int $actorid Acting user identifier
     * @param string $token Optional deduplication token
     */
    private static function log_event(stdClass $attempt, string $eventtype, int $actorid, string $token = ''): void {
        global $DB;
        $DB->insert_record('quizaccess_efl_event', (object) [
            'attemptid' => $attempt->id,
            'quizid' => $attempt->quiz,
            'userid' => $attempt->userid,
            'eventtype' => $eventtype,
            'eventtime' => \core\di::get(\core\clock::class)->time(),
            'eventtoken' => $token === '' ? null : $token,
            'actorid' => $actorid,
        ]);
    }

    /**
     * Check if the underlying quiz attempt can no longer be monitored.
     * @param string $state Moodle quiz attempt state
     * @return bool
     */
    private static function is_quiz_attempt_finished(string $state): bool {
        return in_array($state, ['finished', 'submitted', 'abandoned'], true);
    }

    /**
     * Submit and grade an attempt across Moodle 4.4–5.x.
     * @param int $attemptid Attempt identifier
     * @param int $now Submission timestamp
     */
    private static function finish_quiz_attempt(int $attemptid, int $now): void {
        $attemptobj = quiz_attempt::create($attemptid);
        if ($attemptobj->get_state() !== quiz_attempt::IN_PROGRESS) {
            throw new moodle_exception('staleaction', 'quizaccess_examfocuslive');
        }
        if (method_exists($attemptobj, 'process_submit')) {
            $attemptobj->process_submit($now, false, $now, false);
            $attemptobj->process_grade_submission($now);
        } else {
            $attemptobj->process_finish($now, false, $now, false);
        }
    }
}
