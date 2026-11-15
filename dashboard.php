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
$PAGE->requires->js_call_amd('quizaccess_examfocuslive/dashboard', 'init', [[
    'cmid' => $cmid,
    'interval' => max(2, (int) $config->heartbeat),
    'strings' => [
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
    ],
]]);

echo $OUTPUT->header();
?>
<div id="efl-dashboard" class="efl-dashboard" data-region="examfocuslive-dashboard" data-layout-mode="compact">
    <header class="efl-overview-header">
        <div class="efl-heading">
            <p><?= get_string('dashboardtitle', 'quizaccess_examfocuslive') ?></p>
            <h1><?= format_string($quiz->name) ?></h1>
        </div>
        <div class="efl-heading-actions">
            <span class="efl-sync"><span class="efl-sync__dot" aria-hidden="true"></span>
                <span data-sync-label><?= get_string('syncstarting', 'quizaccess_examfocuslive') ?></span>
            </span>
            <div class="efl-header-tools">
                <a class="efl-header-control" data-back-to-quiz href="<?= $quizurl->out() ?>" target="_self">
                    <?= get_string('backtoquiz', 'quizaccess_examfocuslive') ?>
                </a>
                <button type="button" class="efl-header-control" data-fullscreen aria-pressed="false">
                    <?= get_string('overviewfullscreen', 'quizaccess_examfocuslive') ?>
                </button>
            </div>
        </div>
    </header>

    <section class="efl-metrics" aria-label="<?= s(get_string('dashboardsummary', 'quizaccess_examfocuslive')) ?>">
        <article class="efl-metric efl-metric--total">
            <span class="efl-metric__icon" aria-hidden="true">◎</span>
            <span class="efl-metric__value" data-stat="total">—</span>
            <span class="efl-metric__label"><?= get_string('all', 'quizaccess_examfocuslive') ?></span>
        </article>
        <article class="efl-metric efl-metric--online">
            <span class="efl-metric__icon" aria-hidden="true">↗</span>
            <span class="efl-metric__value" data-stat="online">—</span>
            <span class="efl-metric__label"><?= get_string('online', 'quizaccess_examfocuslive') ?></span>
        </article>
        <article class="efl-metric efl-metric--alert">
            <span class="efl-metric__icon" aria-hidden="true">!</span>
            <span class="efl-metric__value" data-stat="alerts">—</span>
            <span class="efl-metric__label"><?= get_string('pausedattempts', 'quizaccess_examfocuslive') ?></span>
        </article>
        <article class="efl-metric efl-metric--done">
            <span class="efl-metric__icon" aria-hidden="true">✓</span>
            <span class="efl-metric__value" data-stat="finished">—</span>
            <span class="efl-metric__label"><?= get_string('finished', 'quizaccess_examfocuslive') ?></span>
        </article>
    </section>

    <section class="efl-toolbar" aria-label="<?= s(get_string('dashboardfilters', 'quizaccess_examfocuslive')) ?>">
        <div class="efl-filters">
            <button class="efl-filter is-active" type="button" data-filter="all" aria-pressed="true">
                <?= get_string('all', 'quizaccess_examfocuslive') ?>
            </button>
            <button class="efl-filter" type="button" data-filter="paused" aria-pressed="false">
                <?= get_string('pausedattempts', 'quizaccess_examfocuslive') ?>
            </button>
            <button class="efl-filter" type="button" data-filter="active" aria-pressed="false">
                <?= get_string('online', 'quizaccess_examfocuslive') ?>
            </button>
            <button class="efl-filter" type="button" data-filter="finished" aria-pressed="false">
                <?= get_string('finished', 'quizaccess_examfocuslive') ?>
            </button>
        </div>
        <label class="efl-search">
            <span aria-hidden="true">⌕</span>
            <span class="sr-only"><?= get_string('searchstudent', 'quizaccess_examfocuslive') ?></span>
            <input type="search" data-search placeholder="<?= s(get_string('searchstudent', 'quizaccess_examfocuslive')) ?>"
                   autocomplete="off">
        </label>
        <label class="efl-overview-select">
            <span><?= get_string('overviewsort', 'quizaccess_examfocuslive') ?></span>
            <select data-sort>
                <option value="name"><?= get_string('sortname', 'quizaccess_examfocuslive') ?></option>
                <option value="status"><?= get_string('sortstatus', 'quizaccess_examfocuslive') ?></option>
                <option value="incidents"><?= get_string('sortincidents', 'quizaccess_examfocuslive') ?></option>
                <option value="progress"><?= get_string('sortprogress', 'quizaccess_examfocuslive') ?></option>
                <option value="seen"><?= get_string('sortseen', 'quizaccess_examfocuslive') ?></option>
            </select>
        </label>
        <label class="efl-overview-select">
            <span><?= get_string('overviewview', 'quizaccess_examfocuslive') ?></span>
            <select data-layout>
                <option value="compact"><?= get_string('overviewcompact', 'quizaccess_examfocuslive') ?></option>
                <option value="details"><?= get_string('overviewdetails', 'quizaccess_examfocuslive') ?></option>
            </select>
        </label>
    </section>

    <section class="efl-bulk" data-bulk hidden aria-label="<?= s(get_string('groupcontrol', 'quizaccess_examfocuslive')) ?>">
        <div class="efl-bulk-selection">
            <button type="button" class="efl-button efl-button--quiet" data-select-visible>
                <?= get_string('selectvisible', 'quizaccess_examfocuslive') ?>
            </button>
            <button type="button" class="efl-button efl-button--quiet" data-clear-selection>
                <?= get_string('clearselection', 'quizaccess_examfocuslive') ?>
            </button>
            <span data-selection-count></span>
        </div>
        <div class="efl-bulk-actions">
            <label class="efl-overview-select">
                <span><?= get_string('applyto', 'quizaccess_examfocuslive') ?></span>
                <select data-action-scope>
                    <option value="selected"><?= get_string('selectedattempts', 'quizaccess_examfocuslive') ?></option>
                    <option value="all"><?= get_string('allattempts', 'quizaccess_examfocuslive') ?></option>
                </select>
            </label>
            <button type="button" class="efl-button efl-button--pause" data-bulk-action="pause">
                <?= get_string('pause', 'quizaccess_examfocuslive') ?>
            </button>
            <button type="button" class="efl-button efl-button--resume" data-bulk-action="resume">
                <?= get_string('resume', 'quizaccess_examfocuslive') ?>
            </button>
            <button type="button" class="efl-button efl-button--terminate" data-bulk-action="terminate">
                <?= get_string('terminate', 'quizaccess_examfocuslive') ?>
            </button>
        </div>
    </section>

    <p class="efl-visible-count" data-visible-count></p>
    <section class="efl-student-grid" data-student-grid aria-busy="true">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="efl-card efl-card--skeleton" aria-hidden="true"><span></span><span></span><span></span></div>
        <?php endfor; ?>
    </section>

    <div class="efl-empty" data-empty hidden>
        <span aria-hidden="true">◌</span>
        <strong><?= get_string('dashboardempty', 'quizaccess_examfocuslive') ?></strong>

    </div>

    <div class="efl-dialog-backdrop" data-dialog-backdrop hidden>
        <section class="efl-dialog" role="alertdialog" aria-modal="true"
                 aria-labelledby="efl-dialog-title" aria-describedby="efl-dialog-message">
            <button type="button" class="efl-dialog__close" data-dialog-close aria-label="<?= s(get_string('close')) ?>">
                ×
            </button>
            <div class="efl-dialog__icon" data-dialog-icon>!</div>
            <h2 id="efl-dialog-title" data-dialog-title></h2>
            <p id="efl-dialog-message" data-dialog-message></p>
            <p data-dialog-error role="alert" hidden></p>
            <div class="efl-dialog__actions">
                <button type="button" class="efl-button efl-button--quiet" data-dialog-close data-dialog-cancel>
                    <?= get_string('cancel') ?>
                </button>
                <button type="button" class="efl-button efl-button--primary" data-dialog-confirm></button>
            </div>
        </section>
    </div>
    <div class="efl-toast" data-toast role="status" aria-live="polite" hidden></div>
</div>
<?php
echo $OUTPUT->footer();
