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
 * Access rule for CD Live Exam Control.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_examfocuslive\local\state_manager;

/**
 * CD Live Exam Control access rule.
 *
 * @package quizaccess_examfocuslive
 */
class quizaccess_examfocuslive extends access_rule_base {
    /**
     * Create the rule when it is enabled for this quiz.
     *
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param bool $canignoretimelimits
     * @return self|null
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (!state_manager::monitoring_is_enabled($quiz)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /** Add monitoring settings to the quiz form. */
    public static function add_settings_form_fields($quizform, $mform) {
        $mform->addElement('header', 'examfocusliveheader', get_string('examfocusliveheader', 'quizaccess_examfocuslive'));
        $mform->setExpanded('examfocusliveheader', false);

        $mform->addElement('selectyesno', 'examfocuslive_enabled', get_string('enabled', 'quizaccess_examfocuslive'));
        $mform->addHelpButton('examfocuslive_enabled', 'enabled', 'quizaccess_examfocuslive');
        $mform->setDefault('examfocuslive_enabled', 0);

        $displaymodes = [
            'photo' => get_string('displayphoto', 'quizaccess_examfocuslive'),
            'name' => get_string('displayname', 'quizaccess_examfocuslive'),
            'both' => get_string('displayboth', 'quizaccess_examfocuslive'),
        ];
        $mform->addElement(
            'select',
            'examfocuslive_displaymode',
            get_string('displaymode', 'quizaccess_examfocuslive'),
            $displaymodes
        );
        $mform->addHelpButton('examfocuslive_displaymode', 'displaymode', 'quizaccess_examfocuslive');
        $mform->setDefault('examfocuslive_displaymode', 'name');
        $mform->hideIf('examfocuslive_displaymode', 'examfocuslive_enabled', 'eq', 0);

        $mform->addElement('selectyesno', 'examfocuslive_autopause', get_string('autopause', 'quizaccess_examfocuslive'));
        $mform->addHelpButton('examfocuslive_autopause', 'autopause', 'quizaccess_examfocuslive');
        $mform->setDefault('examfocuslive_autopause', 0);
        $mform->hideIf('examfocuslive_autopause', 'examfocuslive_enabled', 'eq', 0);

        $mform->addElement('selectyesno', 'examfocuslive_showprogress', get_string('showprogress', 'quizaccess_examfocuslive'));
        $mform->setDefault('examfocuslive_showprogress', 1);
        $mform->hideIf('examfocuslive_showprogress', 'examfocuslive_enabled', 'eq', 0);

        $intervals = [];
        foreach ([2, 3, 5, 10] as $seconds) {
            $intervals[$seconds] = get_string('seconds', 'quizaccess_examfocuslive', $seconds);
        }
        $mform->addElement('select', 'examfocuslive_heartbeat', get_string('heartbeat', 'quizaccess_examfocuslive'), $intervals);
        $mform->addHelpButton('examfocuslive_heartbeat', 'heartbeat', 'quizaccess_examfocuslive');
        $mform->setDefault('examfocuslive_heartbeat', 5);
        $mform->addElement('select', 'examfocuslive_gracems', get_string('gracems', 'quizaccess_examfocuslive'), [
            500 => '0.5 s', 1000 => '1 s', 1500 => '1.5 s', 2000 => '2 s',
        ]);
        $mform->setDefault('examfocuslive_gracems', 1000);
        $mform->hideIf('examfocuslive_gracems', 'examfocuslive_enabled', 'eq', 0);
        $mform->hideIf('examfocuslive_heartbeat', 'examfocuslive_enabled', 'eq', 0);
    }

    /** Validate the submitted monitoring settings. */
    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
        if (
            !empty($data['examfocuslive_enabled']) &&
                (!empty($data['cdexamcontrolenabled']) || !empty($data['cdexamsaveenabled']))
        ) {
            $errors['examfocuslive_enabled'] = get_string('conflictingmonitor', 'quizaccess_examfocuslive');
        }
        return $errors;
    }

    /** Save monitoring settings for the quiz. */
    public static function save_settings($quiz) {
        global $DB;

        $now = time();
        $values = (object) [
            'quizid' => $quiz->id,
            'enabled' => empty($quiz->examfocuslive_enabled) ? 0 : 1,
            'displaymode' => $quiz->examfocuslive_displaymode ?? 'name',
            'autopause' => empty($quiz->examfocuslive_autopause) ? 0 : 1,
            'gracems' => in_array((int) ($quiz->examfocuslive_gracems ?? 0), [500, 1000, 1500, 2000], true)
                ? (int) $quiz->examfocuslive_gracems : 1000,
            'showprogress' => isset($quiz->examfocuslive_showprogress) ? (int) $quiz->examfocuslive_showprogress : 1,
            'heartbeat' => min(10, max(2, (int) ($quiz->examfocuslive_heartbeat ?? 5))),
            'timemodified' => $now,
        ];
        $existing = $DB->get_record('quizaccess_examfocuslive_cfg', ['quizid' => $quiz->id]);
        if ($existing) {
            $values->id = $existing->id;
            $DB->update_record('quizaccess_examfocuslive_cfg', $values);
        } else {
            $values->timecreated = $now;
            $DB->insert_record('quizaccess_examfocuslive_cfg', $values);
        }
    }

    /** Return the join used to load monitoring settings. */
    public static function get_settings_sql($quizid) {
        return [
            'efl.enabled AS examfocuslive_enabled, efl.displaymode AS examfocuslive_displaymode, ' .
                'efl.autopause AS examfocuslive_autopause, efl.showprogress AS examfocuslive_showprogress, ' .
                'efl.heartbeat AS examfocuslive_heartbeat, efl.gracems AS examfocuslive_gracems',
            'LEFT JOIN {quizaccess_examfocuslive_cfg} efl ON efl.quizid = quiz.id',
            [],
        ];
    }

    /** Delete monitoring settings when the quiz is deleted. */
    public static function delete_settings($quiz) {
        global $DB;
        $quizid = is_object($quiz) ? $quiz->id : $quiz;
        $DB->delete_records('quizaccess_examfocuslive_evt', ['quizid' => $quizid]);
        $DB->delete_records('quizaccess_examfocuslive_att', ['quizid' => $quizid]);
        $DB->delete_records('quizaccess_examfocuslive_cfg', ['quizid' => $quizid]);
    }

    /**
     * ExamFocus owns the time-limit calculation so paused time is not consumed.
     *
     * @return array
     */
    public function get_superceded_rules() {
        return ['timelimit'];
    }

    /** Return the deadline adjusted for confirmed pauses. */
    public function end_time($attempt) {
        return state_manager::get_attempt_end_time($attempt, $this->quizobj->get_quiz(), $this->quizobj->get_context());
    }

    /** Return the remaining time displayed to the student. */
    public function time_left_display($attempt, $timenow) {
        $endtime = $this->end_time($attempt);
        if (!empty($attempt->preview) && $endtime !== false && $timenow > $endtime) {
            return false;
        }
        return $endtime === false ? false : $endtime - $timenow;
    }

    /**
     * Pause access to the normal attempt pages while a teacher decision is pending.
     *
     * @return string|false
     */
    public function prevent_access() {
        global $DB, $USER;
        if (
            has_capability('quizaccess/examfocuslive:exempt', $this->quizobj->get_context()) ||
                ($this->quiz->timeclose && $this->timenow >= $this->quiz->timeclose)
        ) {
            return false;
        }
        $sql = "SELECT efl.id, efl.status, efl.pausedbyteacher
                  FROM {quizaccess_examfocuslive_att} efl
                  JOIN {quiz_attempts} qa ON qa.id = efl.attemptid
                 WHERE efl.quizid = :quizid
                   AND efl.userid = :userid
                   AND qa.preview = 0
                   AND qa.state = 'inprogress'
              ORDER BY qa.id DESC";
        $live = $DB->get_record_sql($sql, [
            'quizid' => $this->quizobj->get_quizid(),
            'userid' => $USER->id,
        ], IGNORE_MULTIPLE);
        if (($live->status ?? '') === state_manager::STATUS_PAUSED) {
            $message = $live->pausedbyteacher ? 'teacherpauseddetail' : 'focuslostdetail';
            return get_string($message, 'quizaccess_examfocuslive');
        }
        if (($live->status ?? '') === state_manager::STATUS_TERMINATED) {
            return get_string('attemptterminated', 'quizaccess_examfocuslive');
        }
        return false;
    }

    /** Load student monitoring on eligible quiz attempt pages. */
    public function setup_attempt_page($page) {
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (
            !$attemptid || !in_array($page->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'], true) ||
                $this->quizobj->is_preview_user() ||
                has_capability('quizaccess/examfocuslive:exempt', $this->quizobj->get_context())
        ) {
            return;
        }
        $config = state_manager::get_quiz_config($this->quizobj->get_quizid());
        if (!$config) {
            return;
        }
        $page->requires->js_call_amd('quizaccess_examfocuslive/student_monitor', 'init', [[
            'attemptid' => $attemptid,
            'interval' => (int) $config->heartbeat,
            'gracems' => (int) $config->gracems,
            'processurl' => (new moodle_url('/mod/quiz/processattempt.php'))->out(false),
            'connectionlost' => get_string('connectionlost', 'quizaccess_examfocuslive'),
            'quizurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $this->quizobj->get_cmid()]))->out(false),
            'summaryurl' => (new moodle_url('/mod/quiz/summary.php', ['attempt' => $attemptid]))->out(false),
            'strings' => [
                'pluginname' => get_string('pluginname', 'quizaccess_examfocuslive'),
                'waitingdecision' => get_string('waitingdecision', 'quizaccess_examfocuslive'),
                'focuslost' => get_string('focuslost', 'quizaccess_examfocuslive'),
                'focuslostdetail' => get_string('focuslostdetail', 'quizaccess_examfocuslive'),
                'teacherpaused' => get_string('teacherpaused', 'quizaccess_examfocuslive'),
                'teacherpauseddetail' => get_string('teacherpauseddetail', 'quizaccess_examfocuslive'),
                'resumed' => get_string('attemptresumed', 'quizaccess_examfocuslive'),
                'completed' => get_string('completed', 'quizaccess_examfocuslive'),
                'terminated' => get_string('attemptterminated', 'quizaccess_examfocuslive'),
                'monitoring' => get_string('monitoringactive', 'quizaccess_examfocuslive'),
            ],
        ]]);
    }

    /** Describe the active quiz access rule. */
    public function description() {
        $messages = [get_string('monitoringdescription', 'quizaccess_examfocuslive')];
        $context = $this->quizobj->get_context();
        if (has_capability('quizaccess/examfocuslive:monitor', $context)) {
            $url = new moodle_url('/mod/quiz/accessrule/examfocuslive/dashboard.php', [
                'cmid' => $this->quizobj->get_cmid(),
            ]);
            $messages[] = html_writer::link($url, get_string('opendashboard', 'quizaccess_examfocuslive'), [
                'class' => 'btn btn-primary',
            ]);
        }
        return $messages;
    }
}
