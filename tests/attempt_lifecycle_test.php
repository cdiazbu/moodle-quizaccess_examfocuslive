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

namespace quizaccess_examfocuslive;

use advanced_testcase;
use context_module;
use mod_quiz\quiz_attempt;
use moodle_exception;
use quizaccess_examfocuslive\external\bulk_control;
use quizaccess_examfocuslive\local\state_manager;
use quizaccess_examfocuslive\task\cleanup;

/**
 * Integration tests for the classroom monitoring and quiz attempt lifecycle.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_examfocuslive\local\state_manager
 * @covers     \quizaccess_examfocuslive\local\observer
 * @covers     \quizaccess_examfocuslive\task\cleanup
 * @covers     \quizaccess_examfocuslive\external\bulk_control
 */
final class attempt_lifecycle_test extends advanced_testcase {
    /**
     * Resuming a pause retains the student's effective limit and rejects an old teacher action.
     */
    public function test_resume_preserves_override_and_rejects_stale_action(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$student, $teacher, $attempt, $quiz, $quizgen] = $this->create_monitored_attempt();
        $quizgen->create_override(['quiz' => $quiz->id, 'userid' => $student->id, 'timelimit' => 180]);
        $clock = $this->mock_clock_with_frozen((int) $attempt->timestart + 30);

        $this->setUser($student);
        $this->assertTrue(state_manager::focus_lost($attempt->id, $student->id, 'blur', 'event-1')->paused);
        $this->assertSame(1, state_manager::focus_lost($attempt->id, $student->id, 'blur', 'event-1')->incidents);

        $clock->bump(45);
        $this->setUser($teacher);
        $resumed = state_manager::control($attempt->id, 'resume', $teacher->id, 1);
        $deadline = (int) $attempt->timestart + 180 + 45;
        $this->assertSame($deadline, $resumed->deadline);
        $this->assertSame($deadline, (int) $DB->get_field('quiz_attempts', 'timecheckstate', ['id' => $attempt->id]));

        $this->setUser($student);
        $heartbeat = state_manager::heartbeat($attempt->id, $student->id, 0, 25, '1', 'focused');
        $this->assertSame($deadline, $heartbeat->deadline);
        $this->assertSame(150, $heartbeat->deadline - $heartbeat->servernow);
        $this->assertTrue(state_manager::focus_lost($attempt->id, $student->id, 'blur', 'event-2')->paused);

        $this->setUser($teacher);
        $this->expectExceptionMessage(get_string('staleaction', 'quizaccess_examfocuslive'));
        state_manager::control($attempt->id, 'terminate', $teacher->id, 1);
    }

    /**
     * A teacher may submit a paused attempt and Moodle grades the response already saved.
     */
    public function test_termination_grades_saved_answers(): void {
        global $DB;
        $this->resetAfterTest();
        [$student, $teacher, $attempt, $quiz, $quizgen] = $this->create_monitored_attempt();
        $quizgen->submit_responses($attempt->id, [1 => get_string('true', 'qtype_truefalse')], false, false);
        $clock = $this->mock_clock_with_frozen((int) $attempt->timestart + 30);
        state_manager::focus_lost($attempt->id, $student->id, 'blur', 'submit-event');
        $clock->bump(20);

        $this->setUser($teacher);
        $this->assertTrue(state_manager::control($attempt->id, 'terminate', $teacher->id, 1)->terminated);
        $record = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(quiz_attempt::FINISHED, $record->state);
        $this->assertEquals(1.0, $record->sumgrades);
        $this->assertTrue($DB->record_exists('quiz_grades', ['quiz' => $quiz->id, 'userid' => $student->id]));
        $live = $DB->get_record('quizaccess_examfocuslive_att', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(20, (int) $live->pausedseconds);
    }

    /**
     * Teachers can pause a focused attempt before the first signal and resume its adjusted allowance.
     */
    public function test_manual_pause_without_focus_loss_or_automatic_pause(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$student, $teacher, $attempt] = $this->create_monitored_attempt(['examfocuslive_autopause' => 0]);
        $clock = $this->mock_clock_with_frozen((int) $attempt->timestart + 30);
        $this->setUser($teacher);
        $first = state_manager::control($attempt->id, 'pause', $teacher->id, 0);
        $this->assertTrue($first->manualpause);
        $this->assertSame(0, $first->incidents);
        $this->assertTrue($DB->record_exists('quizaccess_examfocuslive_evt', [
            'attemptid' => $attempt->id, 'eventtype' => 'teacherpaused', 'actorid' => $teacher->id,
        ]));

        $this->setUser($student);
        $this->assertTrue(state_manager::heartbeat($attempt->id, $student->id, 0, 0, '', 'focused')->manualpause);
        $attemptobj = quiz_attempt::create($attempt->id);
        $access = $attemptobj->get_access_manager($clock->time());
        $this->assertContains(get_string('teacherpauseddetail', 'quizaccess_examfocuslive'), $access->prevent_access());

        $clock->bump(35);
        $this->setUser($teacher);
        $resumed = state_manager::control($attempt->id, 'resume', $teacher->id, 1);
        $this->assertFalse($resumed->paused);
        $this->assertSame(90, $resumed->deadline - $resumed->servernow);

        $this->setUser($student);
        $active = state_manager::heartbeat($attempt->id, $student->id, 0, 0, '', 'focused');
        $this->assertSame($resumed->deadline, $active->deadline);
        $this->assertSame(0, $active->incidents);

        $this->setUser($teacher);
        $this->assertTrue(state_manager::control($attempt->id, 'pause', $teacher->id, 2)->manualpause);
        $this->expectExceptionMessage(get_string('staleaction', 'quizaccess_examfocuslive'));
        state_manager::control($attempt->id, 'resume', $teacher->id, 1);
    }

    /**
     * Batch targets are bound to the selected quiz and other attempts remain active.
     */
    public function test_group_actions_finish_active_and_paused_attempts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [, $teacher, $attempt, $quiz, $quizgen] = $this->create_monitored_attempt(['examfocuslive_autopause' => 0]);
        $generator = $this->getDataGenerator();
        $other = $generator->create_user();
        $generator->enrol_user($other->id, $quiz->course, 'student');
        $this->setUser($other);
        $second = $quizgen->create_attempt($quiz->id, $other->id);
        $quizgen->submit_responses($second->id, [1 => get_string('true', 'qtype_truefalse')], false, false);
        [, , $outside, $outsidequiz] = $this->create_monitored_attempt();
        $generator->enrol_user($teacher->id, $outsidequiz->course, 'editingteacher');

        $this->setUser($teacher);
        $result = bulk_control::execute($quiz->cmid, 'pause', [
            ['attemptid' => $attempt->id, 'revision' => 0],
            ['attemptid' => $outside->id, 'revision' => 0],
            ['attemptid' => $attempt->id, 'revision' => 0],
        ]);
        $this->assertSame(['updated', 'skipped'], array_column($result['results'], 'outcome'));
        $this->assertFalse($DB->record_exists('quizaccess_examfocuslive_att', ['attemptid' => $second->id]));
        $this->assertFalse($DB->record_exists('quizaccess_examfocuslive_att', ['attemptid' => $outside->id]));

        $result = bulk_control::execute($quiz->cmid, 'terminate', [
            ['attemptid' => $attempt->id, 'revision' => 1],
            ['attemptid' => $second->id, 'revision' => 0],
        ]);
        $this->assertSame(['updated', 'updated'], array_column($result['results'], 'outcome'));
        $this->assertSame(quiz_attempt::FINISHED, $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->id]));
        $this->assertSame(quiz_attempt::FINISHED, $DB->get_field('quiz_attempts', 'state', ['id' => $second->id]));
        $this->assertEquals(1.0, $DB->get_field('quiz_attempts', 'sumgrades', ['id' => $second->id]));
        $this->assertSame(0, (int) $DB->get_field('quizaccess_examfocuslive_att', 'pausedseconds', ['attemptid' => $second->id]));
        $this->assertSame(quiz_attempt::IN_PROGRESS, $DB->get_field('quiz_attempts', 'state', ['id' => $outside->id]));
    }

    /**
     * Moodle's quiz closing time and submission grace period apply to paused attempts.
     */
    public function test_closing_time_allows_moodle_grace_period(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $closing = time() + 40;
        [$student, $teacher, $attempt] = $this->create_monitored_attempt([
            'timeclose' => $closing,
            'overduehandling' => 'graceperiod',
            'graceperiod' => 60,
        ]);
        $clock = $this->mock_clock_with_frozen($closing - 10);
        $this->assertTrue(state_manager::focus_lost($attempt->id, $student->id, 'blur', 'close-event')->paused);
        $this->assertSame($closing, (int) $DB->get_field('quiz_attempts', 'timecheckstate', ['id' => $attempt->id]));

        $clock->bump(11);
        $this->setUser($teacher);
        try {
            state_manager::control($attempt->id, 'resume', $teacher->id, 1);
            $this->fail('Reopening a quiz past its closing time must fail.');
        } catch (moodle_exception $error) {
            $this->assertSame('staleaction', $error->errorcode);
        }

        $this->setUser($student);
        $this->assertSame('overdue', state_manager::heartbeat($attempt->id, $student->id, 0, 0, '', 'focused')->status);
        $attemptobj = quiz_attempt::create($attempt->id);
        $this->assertSame(quiz_attempt::OVERDUE, $attemptobj->get_state());
        $this->assertSame([], $attemptobj->get_access_manager($clock->time())->prevent_access());
    }

    /**
     * Abandoned attempts can have no timefinish and are still subject to retention.
     */
    public function test_cleanup_expires_abandoned_monitoring_without_finish_time(): void {
        global $DB;
        $this->resetAfterTest();
        [$student, , $attempt] = $this->create_monitored_attempt();
        state_manager::focus_lost($attempt->id, $student->id, 'blur', 'abandoned-event');
        $past = time() - 2 * DAYSECS;
        quiz_attempt::create($attempt->id)->process_abandon($past, false);
        $DB->set_field('quizaccess_examfocuslive_att', 'timemodified', $past, ['attemptid' => $attempt->id]);
        $DB->set_field('quizaccess_examfocuslive_evt', 'eventtime', $past, ['attemptid' => $attempt->id]);
        set_config('retentiondays', 1, 'quizaccess_examfocuslive');

        (new cleanup())->execute();

        $this->assertFalse($DB->record_exists('quizaccess_examfocuslive_att', ['attemptid' => $attempt->id]));
        $this->assertFalse($DB->record_exists('quizaccess_examfocuslive_evt', ['attemptid' => $attempt->id]));
        $this->assertTrue($DB->record_exists('quiz_attempts', ['id' => $attempt->id, 'state' => quiz_attempt::ABANDONED]));
    }

    /**
     * Only the teacher's own group can be managed, even with visible groups.
     */
    public function test_group_visibility_and_management_are_independent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$student, $teacher, $attempt, $quiz, $quizgen] = $this->create_monitored_attempt(['groupmode' => VISIBLEGROUPS]);
        $generator = $this->getDataGenerator();
        $other = $generator->create_user();
        $generator->enrol_user($other->id, $quiz->course, 'student');
        $groupa = $generator->create_group(['courseid' => $quiz->course]);
        $groupb = $generator->create_group(['courseid' => $quiz->course]);
        groups_add_member($groupa, $teacher);
        groups_add_member($groupa, $student);
        groups_add_member($groupb, $other);

        $context = context_module::instance($quiz->cmid);
        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, $context->id, true);
        $this->setUser($other);
        $otherattempt = $quizgen->create_attempt($quiz->id, $other->id);
        state_manager::focus_lost($otherattempt->id, $other->id, 'blur', 'other-group');

        $this->setUser($teacher);
        $students = array_column(state_manager::dashboard($quiz->cmid)['students'], null, 'userid');
        $this->assertTrue($students[$student->id]['canmanage']);
        $this->assertFalse($students[$other->id]['canmanage']);
        $this->assertSame((int) $attempt->id, $students[$student->id]['attemptid']);

        $this->expectExceptionMessage(get_string('errorinvalidattempt', 'quizaccess_examfocuslive'));
        state_manager::control($otherattempt->id, 'resume', $teacher->id, 1);
    }

    /**
     * A student may be excluded from monitoring without losing permission to attempt the quiz.
     */
    public function test_exempt_student_is_not_paused(): void {
        global $DB;
        $this->resetAfterTest();
        [$student, , $attempt, $quiz] = $this->create_monitored_attempt();
        $context = context_module::instance($quiz->cmid);
        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('quizaccess/examfocuslive:exempt', CAP_ALLOW, $role, $context->id, true);
        $this->setUser($student);

        $result = state_manager::focus_lost($attempt->id, $student->id, 'blur', 'exempt-event');
        $this->assertFalse($result->paused);
        $this->assertFalse($DB->record_exists('quizaccess_examfocuslive_att', ['attemptid' => $attempt->id]));
        $this->assertTrue(has_capability('mod/quiz:attempt', $context));
    }

    /**
     * Create and start a quiz with one true/false question and a monitored student.
     *
     * @param array $settings Additional settings for the test quiz
     * @return array Student, teacher, attempt, quiz and quiz generator
     */
    private function create_monitored_attempt(array $settings = []): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $quizgen = $generator->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_test_quiz([['True or false', 1, 'truefalse']], $settings + [
            'course' => $course->id,
            'examfocuslive_enabled' => 1,
            'examfocuslive_autopause' => 1,
            'timelimit' => 120,
            'preferredbehaviour' => 'deferredfeedback',
        ]);
        $this->setUser($student);
        $attempt = $quizgen->create_attempt($quiz->get_quizid(), $student->id);
        return [$student, $teacher, $attempt, $quiz->get_quiz(), $quizgen];
    }
}
