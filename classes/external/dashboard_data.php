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
 * Return a quiz roster for a monitoring dashboard.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_examfocuslive\local\state_manager;

defined('MOODLE_INTERNAL') || die();

/** Dashboard polling endpoint. */
class dashboard_data extends external_api {
    /**
     * Describe the quiz course-module identifier.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Quiz course-module ID'),
        ]);
    }

    /**
     * Return live data for the students visible to this teacher.
     * @param int $cmid Quiz course-module identifier
     * @return array Dashboard data
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid'));
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm);
        self::validate_context(\context_module::instance($cm->id));
        return state_manager::dashboard($params['cmid']);
    }

    /**
     * Describe the live roster and permissions.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'servernow' => new external_value(PARAM_INT, 'Current server timestamp'),
            'displaymode' => new external_value(PARAM_ALPHA, 'photo, name or both'),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the current user can control attempts'),
            'students' => new external_multiple_structure(new external_single_structure([
                'attemptid' => new external_value(PARAM_INT, 'Attempt ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Display name'),
                'sortname' => new external_value(PARAM_TEXT, 'Surname and given name for sorting'),
                'pictureurl' => new external_value(PARAM_RAW, 'Profile picture URL or empty when hidden'),
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the teacher can manage this attempt'),
                'revision' => new external_value(PARAM_INT, 'Revision of the attempt controls'),
                'manualpause' => new external_value(PARAM_BOOL, 'Whether the teacher paused the attempt'),
                'status' => new external_value(PARAM_ALPHA, 'Display state'),
                'rawstatus' => new external_value(PARAM_ALPHA, 'Persistent state'),
                'focusstate' => new external_value(PARAM_ALPHA, 'Browser focus state'),
                'progress' => new external_value(PARAM_INT, 'Estimated percentage or -1'),
                'page' => new external_value(PARAM_INT, 'One-based page number'),
                'questionlabel' => new external_value(PARAM_TEXT, 'Current question'),
                'incidents' => new external_value(PARAM_INT, 'Focus-loss count'),
                'lastseen' => new external_value(PARAM_INT, 'Last heartbeat timestamp'),
                'secondsago' => new external_value(PARAM_INT, 'Seconds since last heartbeat'),
                'elapsed' => new external_value(PARAM_INT, 'Active attempt duration'),
            ])),
        ]);
    }
}
