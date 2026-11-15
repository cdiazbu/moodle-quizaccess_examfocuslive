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
 * Compatibility class for the former pause maintenance task.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\task;

/**
 * Maintain deadlines while an attempt is paused.
 */
class freeze_paused_deadlines extends \core\task\scheduled_task {
    /**
     * Return the task name shown in Moodle.
     *
     * @return string Localised task name
     */
    public function get_name(): string {
        return get_string('taskfreezedeadlines', 'quizaccess_examfocuslive');
    }

    /**
     * Run the scheduled monitoring maintenance.
     */
    public function execute(): void {
        // Moodle now recalculates the deadline using the access rule.
    }
}
