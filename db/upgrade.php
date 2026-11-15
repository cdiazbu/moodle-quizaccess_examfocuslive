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
 * Migrate the monitoring database tables.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps for CD Live Exam Control.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_quizaccess_examfocuslive_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026111402) {
        $manager = $DB->get_manager();
        $table = new xmldb_table('quizaccess_efl_quiz');
        $grace = new xmldb_field('gracems', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 1000, 'showprogress');
        if (!$manager->field_exists($table, $grace)) {
            $manager->add_field($table, $grace);
        }
        $manager->change_field_default(
            $table,
            new xmldb_field('displaymode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'name')
        );
        $manager->change_field_default($table, new xmldb_field('autopause', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0));
        $manager->change_field_default($table, new xmldb_field('heartbeat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 5));

        $events = new xmldb_table('quizaccess_efl_event');
        $token = new xmldb_field('eventtoken', XMLDB_TYPE_CHAR, '36', null, null, null, null, 'eventtime');
        if (!$manager->field_exists($events, $token)) {
            $manager->add_field($events, $token);
        }
        upgrade_plugin_savepoint(true, 2026111402, 'quizaccess', 'examfocuslive');
    }

    if ($oldversion < 2026111409) {
        $manager = $DB->get_manager();
        $table = new xmldb_table('quizaccess_efl_attempt');
        $revision = new xmldb_field('controlrevision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'lostfocuscount');
        $manual = new xmldb_field('pausedbyteacher', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'pausedat');
        if (!$manager->field_exists($table, $revision)) {
            $manager->add_field($table, $revision);
        }
        if (!$manager->field_exists($table, $manual)) {
            $manager->add_field($table, $manual);
        }
        // Keep the revision already known by panels for attempts upgraded in place.
        $DB->execute('UPDATE {quizaccess_efl_attempt} SET controlrevision = lostfocuscount');
        upgrade_plugin_savepoint(true, 2026111409, 'quizaccess', 'examfocuslive');
    }

    if ($oldversion < 2026111500) {
        $manager = $DB->get_manager();
        $tables = [
            'quizaccess_efl_quiz' => 'quizaccess_examfocuslive_cfg',
            'quizaccess_efl_attempt' => 'quizaccess_examfocuslive_att',
            'quizaccess_efl_event' => 'quizaccess_examfocuslive_evt',
        ];
        foreach ($tables as $oldname => $newname) {
            $table = new xmldb_table($oldname);
            if ($manager->table_exists($table)) {
                $manager->rename_table($table, $newname);
            }
        }
        upgrade_plugin_savepoint(true, 2026111500, 'quizaccess', 'examfocuslive');
    }

    return true;
}
