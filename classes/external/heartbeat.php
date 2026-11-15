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
 * Process a student monitoring heartbeat.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_examfocuslive\local\state_manager;

/**
 * Update the monitoring state of a quiz attempt.
 */
class heartbeat extends external_api {
    /**
     * Describe the current position and focus reported by the browser.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number'),
            'progress' => new external_value(PARAM_INT, 'Estimated percentage'),
            'questionlabel' => new external_value(PARAM_TEXT, 'Current question label', VALUE_DEFAULT, ''),
            'focusstate' => new external_value(PARAM_ALPHA, 'focused or blurred', VALUE_DEFAULT, 'focused'),
        ]);
    }

    /**
     * Process one heartbeat from the attempt owner.
     * @param int $attemptid Attempt identifier
     * @param int $page Current zero-based page
     * @param int $progress Estimated progress percentage
     * @param string $questionlabel Navigation label for the current position
     * @param string $focusstate Last observed focus state
     * @return array Current monitoring state
     */
    public static function execute(
        int $attemptid,
        int $page,
        int $progress,
        string $questionlabel = '',
        string $focusstate = 'focused'
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'attemptid',
            'page',
            'progress',
            'questionlabel',
            'focusstate'
        ));
        [, , $cm, $context] = state_manager::resolve_attempt($params['attemptid'], $USER->id);
        require_login($cm->course, false, $cm);
        self::validate_context($context);
        $state = state_manager::heartbeat(
            $params['attemptid'],
            $USER->id,
            $params['page'],
            $params['progress'],
            $params['questionlabel'],
            $params['focusstate']
        );
        return (array) $state;
    }

    /**
     * Describe what the heartbeat returns.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::state_structure();
    }

    /**
     * Describe the monitoring state shared by the student and teacher endpoints.
     * @return external_single_structure
     */
    public static function state_structure(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Current monitoring state'),
            'deadline' => new external_value(PARAM_INT, 'Effective deadline after resume, or zero'),
            'paused' => new external_value(PARAM_BOOL, 'Whether the attempt is paused'),
            'manualpause' => new external_value(PARAM_BOOL, 'Whether the teacher paused the attempt'),
            'terminated' => new external_value(PARAM_BOOL, 'Whether the teacher ended the attempt'),
            'incidents' => new external_value(PARAM_INT, 'Focus-loss count'),
            'servernow' => new external_value(PARAM_INT, 'Current server timestamp'),
        ]);
    }
}
