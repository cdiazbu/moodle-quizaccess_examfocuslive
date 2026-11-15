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
 * English language strings.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'CD Live Exam Control';
$string['examfocusliveheader'] = 'CD Live Exam Control — live proctoring';
$string['enabled'] = 'Enable live proctoring';
$string['enabled_help'] = 'Show student progress and browser focus observations to authorised teachers.';
$string['displaymode'] = 'Student identification';
$string['displaymode_help'] = 'Choose whether the dashboard shows the profile picture, name, or both.';
$string['displayphoto'] = 'Picture only';
$string['displayname'] = 'Name only';
$string['displayboth'] = 'Picture and name';
$string['autopause'] = 'Pause on focus loss';
$string['autopause_help'] = 'After sustained browser focus loss, pause the quiz interface and timer until the teacher acts or the quiz closes.';
$string['showprogress'] = 'Show progress';
$string['heartbeat'] = 'Refresh frequency';
$string['heartbeat_help'] = 'Interval between dashboard updates. Shorter intervals are more immediate but generate more server requests.';
$string['seconds'] = '{$a} seconds';
$string['dashboard'] = 'Live monitoring';
$string['dashboardtitle'] = 'Monitoring room';
$string['dashboardintro'] = 'Live monitoring for {$a}';
$string['backtoquiz'] = 'Back to quiz';
$string['active'] = 'In progress';
$string['paused'] = 'Decision required';
$string['terminated'] = 'Ended by teacher';
$string['completed'] = 'Submitted';
$string['offline'] = 'Offline';
$string['all'] = 'All';
$string['alerts'] = 'Alerts';
$string['online'] = 'Online';
$string['finished'] = 'Finished';
$string['searchstudent'] = 'Search student';
$string['student'] = 'Student';
$string['progress'] = 'Progress';
$string['location'] = 'Current position';
$string['incidents'] = 'Incidents';
$string['lastseen'] = 'Last signal';
$string['resume'] = 'Allow to continue';
$string['terminate'] = 'Finish attempt';
$string['resumetitle'] = 'Resume attempt';
$string['resumeconfirm'] = 'The timer resumes from the paused point. The quiz closing time still applies.';
$string['terminateconfirm'] = 'Moodle will submit the saved responses and apply its usual grading. This submission is final.';
$string['focuslost'] = 'Focus loss detected';
$string['focuslostdetail'] = 'The attempt has been paused automatically. Wait for your teacher to allow you to continue.';
$string['attemptresumed'] = 'Your teacher has allowed you to continue. Restoring the quiz…';
$string['attemptterminated'] = 'Your teacher has ended this attempt.';
$string['monitoringactive'] = 'Monitoring active';
$string['monitoringdescription'] = 'This quiz uses CD Live Exam Control. Losing browser focus may pause the attempt and notify the teacher.';
$string['opendashboard'] = 'Open monitoring dashboard';
$string['pagequestion'] = 'Page {$a->page} · {$a->question}';
$string['pageonly'] = 'Page {$a}';
$string['never'] = 'Never';
$string['privacy:metadata:attempt'] = 'Live state of monitored attempts.';
$string['privacy:metadata:attempt:userid'] = 'Student identifier.';
$string['privacy:metadata:attempt:attemptid'] = 'Quiz attempt identifier.';
$string['privacy:metadata:attempt:status'] = 'Monitoring state of the attempt.';
$string['privacy:metadata:attempt:progress'] = 'Estimated progress percentage.';
$string['privacy:metadata:attempt:lastheartbeat'] = 'Time of the most recent browser signal.';
$string['privacy:metadata:attempt:lostfocuscount'] = 'Number of detected focus losses.';
$string['privacy:metadata:event'] = 'Audit log of incidents and teacher decisions.';
$string['privacy:metadata:event:eventtype'] = 'Incident or decision type.';
$string['privacy:metadata:event:eventtime'] = 'Time of the incident.';
$string['privacy:metadata:event:metadata'] = 'Minimal technical information associated with the incident.';
$string['examfocuslive:monitor'] = 'View the live monitoring dashboard';
$string['examfocuslive:manage'] = 'Resume or end monitored attempts';
$string['errornotenabled'] = 'CD Live Exam Control is not enabled for this quiz.';
$string['errorinvalidattempt'] = 'The selected attempt is invalid.';
$string['errorinvalidaction'] = 'The requested action is invalid.';
$string['statusupdated'] = 'Status updated';
$string['taskfreezedeadlines'] = 'Keep paused attempt deadlines frozen';

$string['controlfailed'] = 'The action could not be confirmed. Try again or close this dialog.';

$string['gracems'] = 'Sustained focus loss threshold';
$string['connectionlost'] = 'The supervision connection was interrupted. Your quiz controls remain available.';
$string['staleaction'] = 'The attempt has changed or closed. Review its current state before acting.';
$string['examfocuslive:exempt'] = 'Exempt from live exam supervision';

$string['conflictingmonitor'] = 'Enable only one CD focus monitor per quiz.';
$string['taskcleanup'] = 'Clean up expired live supervision data';
$string['retentiondays'] = 'Retention period for completed monitoring (days)';
$string['retentiondays_help'] = 'Cleanup applies only to finished or abandoned attempts.';
$string['retentiondisabled'] = 'Automatic deletion disabled';
$string['privacy:metadata:event:actorid'] = 'The user who triggered the event.';
$string['privacy:metadata:event:eventtoken'] = 'A random identifier to deduplicate a focus event.';

$string['waiting'] = 'Not started';
$string['overviewpage'] = 'Page';
$string['overviewincidents'] = 'Incidents';
$string['overviewunfocused'] = 'Out of focus';
$string['overviewsort'] = 'Sort';
$string['sortname'] = 'Surname';
$string['sortstatus'] = 'Status';
$string['sortincidents'] = 'Most incidents';
$string['sortprogress'] = 'Most progress';
$string['sortseen'] = 'Last signal';
$string['overviewview'] = 'View';
$string['overviewcompact'] = 'Class';
$string['overviewdetails'] = 'Details';
$string['overviewfullscreen'] = 'Fullscreen';
$string['exitfullscreen'] = 'Exit fullscreen';

$string['pause'] = 'Pause';
$string['pausetitle'] = 'Pause attempt';
$string['pauseconfirm'] = 'The individual timer pauses until the teacher allows the student to continue. The quiz closing time still applies.';
$string['teacherpaused'] = 'Attempt paused by the teacher';
$string['teacherpauseddetail'] = 'Your teacher has paused your attempt. You can continue when they allow it.';
$string['pausedattempts'] = 'Paused';
$string['groupcontrol'] = 'Group actions';
$string['applyto'] = 'Apply to';
$string['selectedattempts'] = 'Selection';
$string['allattempts'] = 'All students';
$string['selectstudent'] = 'Select {$a}';
$string['selectvisible'] = 'Select visible students';
$string['clearselection'] = 'Clear selection';
$string['selectioncount'] = 'Selected attempts: {$a}';
$string['bulkconfirm'] = 'Attempts affected: {$a}.';
$string['bulkresult'] = 'Attempts updated: {$a->updated}. Unchanged: {$a->skipped}.';
$string['bulkfailed'] = 'The whole operation could not be confirmed. Check the updated dashboard.';

$string['waitingdecision'] = 'Waiting for a decision from the teacher.';
$string['overdue'] = 'Submission grace period';
$string['dashboardsummary'] = 'Summary';
$string['dashboardfilters'] = 'Filters';
$string['dashboardempty'] = 'There are no students in this view';
$string['syncstarting'] = 'Connecting';
$string['syncupdating'] = 'Updating';
$string['synclive'] = 'Live';
$string['syncretrying'] = 'Reconnecting';
$string['lastseennow'] = 'Now';
$string['lastseenseconds'] = '{$a} s ago';
$string['lastseenminutes'] = '{$a} min ago';
$string['activeelapsed'] = 'Active time';
$string['pausedalert'] = 'Focus loss. Teacher decision required';
$string['privacy:metadata:attempt:focusstate'] = 'Most recently observed browser focus state.';
$string['privacy:metadata:attempt:page'] = 'Quiz page reported by the monitoring interface.';
$string['privacy:metadata:attempt:questionlabel'] = 'Navigation label for the current question or page.';
$string['privacy:metadata:attempt:lastviolation'] = 'Time of the most recent sustained focus loss.';
$string['privacy:metadata:attempt:pausedat'] = 'Start time of the current pause.';
$string['privacy:metadata:attempt:pausedbyteacher'] = 'Whether a teacher initiated the current pause.';
$string['privacy:metadata:attempt:controlrevision'] = 'Technical version identifier for the attempt state.';
$string['privacy:metadata:attempt:pausedseconds'] = 'Number of seconds spent paused.';
$string['privacy:metadata:attempt:remainingatpause'] = 'Time allowance remaining when the attempt paused.';
$string['privacy:metadata:attempt:timecreated'] = 'When monitoring started.';
$string['privacy:metadata:attempt:timemodified'] = 'When the monitoring state last changed.';
