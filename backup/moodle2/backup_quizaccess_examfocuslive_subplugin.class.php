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
 * Back up the quiz monitoring settings.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Back up quiz monitoring settings.
 */
class backup_quizaccess_examfocuslive_subplugin extends backup_mod_quiz_access_subplugin {
    /**
     * Define the quiz settings structure.
     *
     * @return mixed Quiz subplugin structure
     */
    protected function define_quiz_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('quizaccess_examfocuslive', null, [
            'enabled',
            'displaymode',
            'autopause',
            'showprogress',
            'heartbeat',
            'gracems',
        ]);
        $subplugin->add_child($wrapper);
        $wrapper->add_child($settings);
        $settings->set_source_table('quizaccess_examfocuslive_cfg', ['quizid' => backup::VAR_ACTIVITYID]);
        return $subplugin;
    }
}
