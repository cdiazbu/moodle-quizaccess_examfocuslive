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
 * Respond to changes in a Moodle attempt.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\local;

/**
 * Update monitoring in response to quiz attempt events.
 */
class observer {
    /**
     * Remove monitoring records after Moodle deletes the attempt.
     * @param \mod_quiz\event\attempt_deleted $event Deletion event
     */
    public static function attempt_deleted(\mod_quiz\event\attempt_deleted $event): void {
        global $DB;
        $DB->delete_records('quizaccess_examfocuslive_evt', ['attemptid' => $event->objectid]);
        $DB->delete_records('quizaccess_examfocuslive_att', ['attemptid' => $event->objectid]);
    }

    /**
     * Finalise monitoring when the attempt is submitted or abandoned.
     * @param \core\event\base $event Moodle attempt end event
     */
    public static function attempt_ended(\core\event\base $event): void {
        global $DB;
        $record = $DB->get_record('quizaccess_examfocuslive_att', ['attemptid' => $event->objectid]);
        if ($record && !in_array($record->status, [state_manager::STATUS_COMPLETED, state_manager::STATUS_TERMINATED], true)) {
            $now = (int) $event->timecreated;
            $DB->update_record('quizaccess_examfocuslive_att', (object) [
                'id' => $record->id,
                'status' => state_manager::STATUS_COMPLETED,
                'pausedseconds' => $record->pausedseconds + ($record->pausedat ? max(0, $now - $record->pausedat) : 0),
                'pausedat' => 0,
                'pausedbyteacher' => 0,
                'remainingatpause' => 0,
                'timemodified' => $now,
            ]);
        }
    }
}
