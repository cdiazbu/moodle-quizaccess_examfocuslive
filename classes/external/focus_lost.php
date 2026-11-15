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
 * Process a sustained browser focus loss observation.
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
 * Record sustained focus loss for a quiz attempt.
 */
class focus_lost extends external_api {
    /**
     * Describe a focus loss observation.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'eventtype' => new external_value(PARAM_ALPHANUMEXT, 'visibilitychange, blur or fullscreenexit'),
            'metadata' => new external_value(PARAM_TEXT, 'Legacy browser context, ignored', VALUE_DEFAULT, ''),
            'eventtoken' => new external_value(PARAM_ALPHANUMEXT, 'Event identifier', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Record a focus observation for the attempt owner.
     * @param int $attemptid Attempt identifier
     * @param string $eventtype Observed browser event
     * @param string $metadata Legacy value accepted for compatibility
     * @param string $eventtoken Identifier used to deduplicate retries
     * @return array Current monitoring state
     */
    public static function execute(int $attemptid, string $eventtype, string $metadata = '', string $eventtoken = ''): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('attemptid', 'eventtype', 'metadata', 'eventtoken')
        );
        [, , $cm, $context] = state_manager::resolve_attempt($params['attemptid'], $USER->id);
        require_login($cm->course, false, $cm);
        self::validate_context($context);
        if (
            !in_array($params['eventtype'], ['visibilitychange', 'blur', 'fullscreenexit'], true) ||
                strlen($params['eventtoken']) > 36
        ) {
            throw new \invalid_parameter_exception('Invalid focus event');
        }
        $state = state_manager::focus_lost(
            $params['attemptid'],
            $USER->id,
            $params['eventtype'],
            $params['eventtoken']
        );
        return (array) $state;
    }

    /**
     * Describe the resulting monitoring state.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return heartbeat::state_structure();
    }
}
