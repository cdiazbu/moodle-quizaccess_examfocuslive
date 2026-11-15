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
 * Callbacks for the CD Live Exam Control access rule.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the live room to quiz activity navigation for authorised staff.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function quizaccess_examfocuslive_extend_navigation_module($navigation, $course, $module, $cm): void {
    global $DB;
    if ($cm->modname !== 'quiz') {
        return;
    }
    $context = context_module::instance($cm->id);
    if (!has_capability('quizaccess/examfocuslive:monitor', $context)) {
        return;
    }
    if (!$DB->record_exists('quizaccess_examfocuslive_cfg', ['quizid' => $cm->instance, 'enabled' => 1])) {
        return;
    }
    $navigation->add(
        get_string('dashboard', 'quizaccess_examfocuslive'),
        new moodle_url('/mod/quiz/accessrule/examfocuslive/dashboard.php', ['cmid' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'examfocuslive-dashboard',
        new pix_icon('i/monitor', '')
    );
}
