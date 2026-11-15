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
 * Restore the quiz monitoring settings.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/** Restore provider for CD Live Exam Control quiz settings. */
class restore_quizaccess_examfocuslive_subplugin extends restore_mod_quiz_access_subplugin {
    protected function define_quiz_subplugin_structure() {
        return [new restore_path_element(
            $this->get_namefor(''),
            $this->get_pathfor('/quizaccess_examfocuslive')
        )];
    }

    /**
     * Restore one settings record.
     *
     * @param array $data
     */
    public function process_quizaccess_examfocuslive($data): void {
        global $DB;
        $record = (object) $data;
        $record->quizid = $this->get_new_parentid('quiz');
        $record->timecreated = time();
        $record->timemodified = time();
        if (!$DB->record_exists('quizaccess_examfocuslive_cfg', ['quizid' => $record->quizid])) {
            $DB->insert_record('quizaccess_examfocuslive_cfg', $record);
        }
    }
}
