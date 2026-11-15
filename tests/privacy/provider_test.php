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

namespace quizaccess_examfocuslive\privacy;

use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Integration coverage for student records and teacher decision exports.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_examfocuslive\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Export only the requester's data, then erase teacher and student data independently.
     */
    public function test_export_and_erase_student_and_teacher_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $otherquiz = $generator->create_module('quiz', ['course' => $course->id]);
        $ctx = context_module::instance($quiz->cmid);
        $otherctx = context_module::instance($otherquiz->cmid);
        $component = 'quizaccess_examfocuslive';
        $eventids = [];
        $now = time();
        foreach ([$quiz->id, $otherquiz->id] as $quizid) {
            $context = $quizid === $quiz->id ? $ctx : $otherctx;
            $usage = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
            $usage->set_preferred_behaviour('deferredfeedback');
            \question_engine::save_questions_usage_by_activity($usage);
            $attemptid = $DB->insert_record('quiz_attempts', (object) [
                'quiz' => $quizid, 'userid' => $student->id, 'attempt' => 1, 'uniqueid' => $usage->get_id(), 'layout' => '0',
            ]);
            $DB->insert_record('quizaccess_efl_attempt', (object) [
                'attemptid' => $attemptid, 'quizid' => $quizid, 'userid' => $student->id,
                'decisionby' => $teacher->id, 'timecreated' => $now, 'timemodified' => $now,
            ]);
            $eventids[$quizid] = $DB->insert_record('quizaccess_efl_event', (object) [
                'attemptid' => $attemptid, 'quizid' => $quizid, 'userid' => $student->id,
                'eventtype' => 'resumed', 'eventtime' => $now, 'actorid' => $teacher->id, 'eventtoken' => 'internal',
            ]);
        }

        $this->assertEqualsCanonicalizing([$ctx->id, $otherctx->id], provider::get_contexts_for_userid($teacher->id)->get_contextids());
        $users = new userlist($ctx, $component);
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$student->id, $teacher->id], $users->get_userids());

        $path = [get_string('pluginname', $component)];
        $teacherrequest = new approved_contextlist($teacher, $component, [$ctx->id]);
        provider::export_user_data($teacherrequest);
        $teacherdata = writer::with_context($ctx)->get_data($path);
        $this->assertSame([], $teacherdata->attempts);
        $this->assertSame([], $teacherdata->events);
        $this->assertSame(['eventtype', 'eventtime'], array_keys((array) $teacherdata->decisions[0]));
        $this->assertSame('resumed', $teacherdata->decisions[0]->eventtype);

        writer::reset();
        provider::export_user_data(new approved_contextlist($student, $component, [$ctx->id]));
        $studentdata = writer::with_context($ctx)->get_data($path);
        $this->assertCount(1, $studentdata->attempts);
        $this->assertSame('resumed', $studentdata->events[0]->eventtype);
        $this->assertArrayNotHasKey('decisionby', (array) $studentdata->attempts[0]);
        $this->assertArrayNotHasKey('actorid', (array) $studentdata->events[0]);
        $this->assertArrayNotHasKey('eventtoken', (array) $studentdata->events[0]);

        provider::delete_data_for_user($teacherrequest);
        $this->assertSame(0, (int) $DB->get_field('quizaccess_efl_event', 'actorid', ['id' => $eventids[$quiz->id]]));
        $this->assertTrue($DB->record_exists('quizaccess_efl_attempt', ['quizid' => $quiz->id, 'decisionby' => 0]));
        $this->assertTrue($DB->record_exists('quizaccess_efl_attempt', ['quizid' => $otherquiz->id, 'decisionby' => $teacher->id]));

        provider::delete_data_for_users(new approved_userlist($ctx, $component, [$student->id]));
        $this->assertFalse($DB->record_exists('quizaccess_efl_attempt', ['quizid' => $quiz->id]));
        $this->assertFalse($DB->record_exists('quizaccess_efl_event', ['id' => $eventids[$quiz->id]]));
        $this->assertTrue($DB->record_exists('quizaccess_efl_event', ['id' => $eventids[$otherquiz->id]]));
    }
}
