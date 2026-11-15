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
 * Apply a teacher decision to an explicitly confirmed list of quiz attempts.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_examfocuslive\local\state_manager;

/**
 * Apply a teacher decision to multiple quiz attempts.
 */
class bulk_control extends external_api {
    /**
     * Define the target quiz, action and confirmed attempt versions.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Quiz course-module ID'),
            'action' => new external_value(PARAM_ALPHA, 'pause, resume or terminate'),
            'targets' => new external_multiple_structure(new external_single_structure([
                'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
                'revision' => new external_value(PARAM_INT, 'Revision confirmed by the teacher'),
            ])),
        ]);
    }

    /**
     * Apply one action to the confirmed attempts. Changes are committed independently.
     * @param int $cmid Quiz course-module identifier
     * @param string $action Pause, resume or terminate
     * @param array $targets Attempt IDs and confirmed revisions
     * @return array Outcome of every attempt
     */
    public static function execute(int $cmid, string $action, array $targets): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'action', 'targets'));
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('quizaccess/examfocuslive:monitor', $context);
        require_capability('quizaccess/examfocuslive:manage', $context);

        $action = $params['action'];
        if (!in_array($action, ['pause', 'resume', 'terminate'], true)) {
            throw new \invalid_parameter_exception('Invalid attempt action.');
        }

        $results = [];
        foreach ($params['targets'] as $target) {
            $attemptid = $target['attemptid'];
            if (isset($results[$attemptid])) {
                continue;
            }
            try {
                state_manager::control($attemptid, $action, $USER->id, $target['revision'], (int) $cm->id);
                $outcome = 'updated';
            } catch (\dml_missing_record_exception | \required_capability_exception $error) {
                $outcome = 'skipped';
            } catch (\moodle_exception $error) {
                if (!in_array($error->errorcode, ['errorinvalidattempt', 'staleaction', 'errornotenabled', 'locktimeout'], true)) {
                    throw $error;
                }
                $outcome = 'skipped';
            }
            $results[$attemptid] = ['attemptid' => $attemptid, 'outcome' => $outcome];
        }
        return ['results' => array_values($results)];
    }

    /**
     * Describe the result of each attempted change.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(new external_single_structure([
                'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
                'outcome' => new external_value(PARAM_ALPHA, 'updated or skipped'),
            ])),
        ]);
    }
}
