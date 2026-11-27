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
 * Display the teacher monitoring dashboard.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\access_manager;
use quizaccess_examfocuslive\local\state_manager;

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/examfocuslive:monitor', $context);

$quiz = access_manager::load_quiz_and_settings((int) $cm->instance);
$config = state_manager::get_quiz_config((int) $quiz->id);
if (!$config || !state_manager::monitoring_is_enabled($quiz)) {
    throw new moodle_exception('errornotenabled', 'quizaccess_examfocuslive');
}

$PAGE->set_url('/mod/quiz/accessrule/examfocuslive/dashboard.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('dashboardtitle', 'quizaccess_examfocuslive'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('dashboard', 'quizaccess_examfocuslive'));
$quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);
$strings = [
    'all' => get_string('all', 'quizaccess_examfocuslive'),
    'online' => get_string('online', 'quizaccess_examfocuslive'),
    'finished' => get_string('finished', 'quizaccess_examfocuslive'),
    'active' => get_string('active', 'quizaccess_examfocuslive'),
    'paused' => get_string('paused', 'quizaccess_examfocuslive'),
    'terminated' => get_string('terminated', 'quizaccess_examfocuslive'),
    'completed' => get_string('completed', 'quizaccess_examfocuslive'),
    'offline' => get_string('offline', 'quizaccess_examfocuslive'),
    'overdue' => get_string('overdue', 'quizaccess_examfocuslive'),
    'pause' => get_string('pause', 'quizaccess_examfocuslive'),
    'resume' => get_string('resume', 'quizaccess_examfocuslive'),
    'terminate' => get_string('terminate', 'quizaccess_examfocuslive'),
    'pausetitle' => get_string('pausetitle', 'quizaccess_examfocuslive'),
    'resumetitle' => get_string('resumetitle', 'quizaccess_examfocuslive'),
    'pauseconfirm' => get_string('pauseconfirm', 'quizaccess_examfocuslive'),
    'resumeconfirm' => get_string('resumeconfirm', 'quizaccess_examfocuslive'),
    'terminateconfirm' => get_string('terminateconfirm', 'quizaccess_examfocuslive'),
    'statusupdated' => get_string('statusupdated', 'quizaccess_examfocuslive'),
    'controlfailed' => get_string('controlfailed', 'quizaccess_examfocuslive'),
    'staleaction' => get_string('staleaction', 'quizaccess_examfocuslive'),
    'waiting' => get_string('waiting', 'quizaccess_examfocuslive'),
    'page' => get_string('overviewpage', 'quizaccess_examfocuslive'),
    'incidents' => get_string('overviewincidents', 'quizaccess_examfocuslive'),
    'unfocused' => get_string('overviewunfocused', 'quizaccess_examfocuslive'),
    'fullscreen' => get_string('overviewfullscreen', 'quizaccess_examfocuslive'),
    'exitfullscreen' => get_string('exitfullscreen', 'quizaccess_examfocuslive'),
    'location' => get_string('location', 'quizaccess_examfocuslive'),
    'activeelapsed' => get_string('activeelapsed', 'quizaccess_examfocuslive'),
    'lastseen' => get_string('lastseen', 'quizaccess_examfocuslive'),
    'lastseennow' => get_string('lastseennow', 'quizaccess_examfocuslive'),
    'lastseenseconds' => get_string('lastseenseconds', 'quizaccess_examfocuslive', '{seconds}'),
    'lastseenminutes' => get_string('lastseenminutes', 'quizaccess_examfocuslive', '{minutes}'),
    'pausedalert' => get_string('pausedalert', 'quizaccess_examfocuslive'),
    'teacherpaused' => get_string('teacherpaused', 'quizaccess_examfocuslive'),
    'selectstudent' => get_string('selectstudent', 'quizaccess_examfocuslive', '{name}'),
    'selectioncount' => get_string('selectioncount', 'quizaccess_examfocuslive', '{count}'),
    'bulkconfirm' => get_string('bulkconfirm', 'quizaccess_examfocuslive', '{count}'),
    'bulkresult' => get_string('bulkresult', 'quizaccess_examfocuslive', (object) [
        'updated' => '{updated}',
        'skipped' => '{skipped}',
    ]),
    'bulkfailed' => get_string('bulkfailed', 'quizaccess_examfocuslive'),
    'syncupdating' => get_string('syncupdating', 'quizaccess_examfocuslive'),
    'synclive' => get_string('synclive', 'quizaccess_examfocuslive'),
    'syncretrying' => get_string('syncretrying', 'quizaccess_examfocuslive'),
];
$templatedata = [
    'quizname' => format_string($quiz->name),
    'quizurl' => $quizurl->out(false),
    'stringsjson' => json_encode($strings, JSON_THROW_ON_ERROR),
    'skeletons' => array_fill(0, 6, true),
];
$PAGE->requires->js_call_amd('quizaccess_examfocuslive/dashboard', 'init', [[
    'cmid' => $cmid,
    'interval' => max(2, (int) $config->heartbeat),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('quizaccess_examfocuslive/dashboard', $templatedata);
echo $OUTPUT->footer();
