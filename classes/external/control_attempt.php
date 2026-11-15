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
 * Apply a teacher decision to an attempt.
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

defined('MOODLE_INTERNAL') || die();

/** Teacher control endpoint. */
class control_attempt extends external_api {
    /**
     * Describe the teacher action and the displayed attempt revision.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'action' => new external_value(PARAM_ALPHA, 'pause, resume or terminate'),
            'revision' => new external_value(PARAM_INT, 'Revision of the attempt shown to the teacher'),
        ]);
    }

    /**
     * Apply a teacher decision to an eligible attempt.
     * @param int $attemptid Attempt identifier
     * @param string $action Pause, resume or terminate
     * @param int $revision Attempt revision shown to the teacher
     * @return array Current monitoring state
     */
    public static function execute(int $attemptid, string $action, int $revision): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('attemptid', 'action', 'revision'));
        [, , $cm, $context] = state_manager::resolve_attempt($params['attemptid']);
        require_login($cm->course, false, $cm);
        self::validate_context($context);
        $state = state_manager::control($params['attemptid'], $params['action'], $USER->id, $params['revision']);
        return (array) $state;
    }

    /**
     * Describe the updated monitoring state.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return heartbeat::state_structure();
    }
}

