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
use context_system;

/**
 * Regression tests for the Moodle output templates.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class output_templates_test extends advanced_testcase {
    /**
     * Localised data survives HTML attribute escaping without being sent to js_call_amd.
     */
    public function test_dashboard_template_preserves_translation_data(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $strings = ['pause' => 'Pause "attempt" & continue', 'resume' => 'Resume <attempt>'];
        $html = $PAGE->get_renderer('core')->render_from_template('quizaccess_examfocuslive/dashboard', [
            'quizname' => 'Practice quiz',
            'quizurl' => '/mod/quiz/view.php?id=1&forceview=1',
            'stringsjson' => json_encode($strings, JSON_THROW_ON_ERROR),
            'skeletons' => array_fill(0, 6, true),
        ]);
        $this->assertSame(1, preg_match('/data-strings="([^"]*)"/', $html, $matches));
        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $this->assertSame($strings, json_decode($decoded, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(6, substr_count($html, 'efl-card--skeleton'));
        $this->assertStringContainsString('data-region="examfocuslive-dashboard"', $html);
    }

    /**
     * User-supplied text is escaped by Mustache and controls keep their data attributes.
     */
    public function test_student_card_escapes_user_text(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $name = '<Example student>';
        $html = $PAGE->get_renderer('core')->render_from_template('quizaccess_examfocuslive/student_cards', [
            'cards' => [[
                'compact' => true,
                'userid' => 1,
                'state' => 'active',
                'showname' => true,
                'name' => $name,
                'status' => 'Active',
                'controls' => true,
                'attemptid' => 2,
                'selectlabel' => $name,
                'selected' => true,
                'buttons' => [['action' => 'pause', 'attemptid' => 2, 'icon' => 'Ⅱ', 'label' => 'Pause']],
            ]],
        ]);
        $this->assertStringNotContainsString($name, $html);
        $this->assertStringContainsString('&lt;Example student&gt;', $html);
        $this->assertStringContainsString('data-action="pause"', $html);
        $this->assertStringContainsString('data-attempt="2"', $html);
        $this->assertStringContainsString(' checked', $html);
    }

    /**
     * The student pause interface retains the accessible dialog structure.
     */
    public function test_student_lock_has_accessible_dialog(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $html = $PAGE->get_renderer('core')->render_from_template('quizaccess_examfocuslive/student_lock', []);
        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('aria-labelledby="efl-lock-title"', $html);
        $this->assertStringContainsString('aria-describedby="efl-lock-message"', $html);
        $this->assertStringContainsString('data-lock-waiting', $html);
    }
}
