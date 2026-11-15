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
 * Register the dashboard and attempt web service functions.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_examfocuslive_heartbeat' => [
        'classname' => 'quizaccess_examfocuslive\\external\\heartbeat',
        'description' => 'Updates presence and progress for a monitored attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:attempt',
    ],
    'quizaccess_examfocuslive_focus_lost' => [
        'classname' => 'quizaccess_examfocuslive\\external\\focus_lost',
        'description' => 'Records sustained browser focus loss and optionally pauses the attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:attempt',
    ],
    'quizaccess_examfocuslive_dashboard' => [
        'classname' => 'quizaccess_examfocuslive\\external\\dashboard_data',
        'description' => 'Returns the live monitoring roster for a quiz.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'quizaccess/examfocuslive:monitor',
    ],
    'quizaccess_examfocuslive_control' => [
        'classname' => 'quizaccess_examfocuslive\\external\\control_attempt',
        'description' => 'Pauses, resumes or submits a monitored attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'quizaccess/examfocuslive:manage',
    ],
    'quizaccess_examfocuslive_bulk_control' => [
        'classname' => 'quizaccess_examfocuslive\\external\\bulk_control',
        'description' => 'Pauses, resumes or submits checked attempts in a monitored quiz.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'quizaccess/examfocuslive:manage, quizaccess/examfocuslive:monitor',
    ],
];

