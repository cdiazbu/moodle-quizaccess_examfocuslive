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
 * Remove expired monitoring records.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_examfocuslive\task;

defined('MOODLE_INTERNAL') || die();

/** Expire monitoring data for completed attempts in bounded batches. */
class cleanup extends \core\task\scheduled_task {
    /**
     * Return the name shown in Moodle's scheduled tasks list.
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanup', 'quizaccess_examfocuslive');
    }

    /** Remove expired monitoring data in batches. */
    public function execute(): void {
        global $DB;
        $days = (int) get_config('quizaccess_examfocuslive', 'retentiondays');
        if ($days <= 0) {
            return;
        }

        $cutoff = time() - $days * DAYSECS;
        foreach (['quizaccess_examfocuslive_evt' => 'eventtime', 'quizaccess_examfocuslive_att' => 'timemodified'] as $table => $field) {
            do {
                $records = $DB->get_records_sql(
                    "SELECT data.id
                       FROM {{$table}} data
                       JOIN {quiz_attempts} qa ON qa.id = data.attemptid
                      WHERE qa.state IN ('finished', 'abandoned')
                        AND COALESCE(NULLIF(qa.timefinish, 0), qa.timemodified) < :finished
                        AND data.$field < :cutoff
                   ORDER BY data.id",
                    ['finished' => $cutoff, 'cutoff' => $cutoff],
                    0,
                    1000
                );
                $ids = array_keys($records);
                if ($ids) {
                    $DB->delete_records_list($table, 'id', $ids);
                }
            } while (count($ids) === 1000);
        }
    }
}
