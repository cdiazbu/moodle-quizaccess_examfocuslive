// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Monitor student attempts and display the pause interface.
 *
 * @module     quizaccess_examfocuslive/student_monitor
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

let config;
let overlay;
let locked = false;
let sending = false;
let focusTimer = null;
let pageInactive = false;
let lossReported = false;
let reporting = false;
let observationRevision = 0;
let focusBeforePause = null;
let navigationUntil = 0;
let statusPill;
let transitionTimer = null;
let quizTimer;
let blockedElements = [];
let appliedDeadline = 0;

const cancelFocusCheck = () => window.clearTimeout(focusTimer);

const getProgress = () => {
    const buttons = Array.from(document.querySelectorAll('.qnbutton'));
    const pageInput = document.querySelector('input[name="thispage"]');
    const page = pageInput ? Number.parseInt(pageInput.value, 10) || 0 : 0;
    if (!buttons.length) {
        return {page, progress: 0, questionlabel: ''};
    }
    const completed = buttons.filter((button) =>
        button.classList.contains('complete') ||
        button.classList.contains('answersaved') ||
        button.classList.contains('correct') ||
        button.classList.contains('incorrect')
    ).length;
    const current = document.querySelector('.qnbutton.thispage, .qnbutton[aria-current="page"]');
    return {
        page,
        progress: Math.min(100, Math.round((completed / buttons.length) * 100)),
        questionlabel: current ? current.textContent.trim().replace(/\s+/g, ' ').slice(0, 100) : '',
    };
};

const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

const ensurePill = () => {
    const pill = document.createElement('div');
    statusPill = pill;
    pill.setAttribute('role', 'status');
    pill.className = 'efl-monitor-pill';
    pill.innerHTML = '<span class="efl-monitor-pill__dot"></span><span></span>';
    pill.lastElementChild.textContent = config.strings.monitoring;
    document.body.appendChild(pill);
};

const ensureOverlay = () => {
    if (overlay) {
        return overlay;
    }
    overlay = document.createElement('div');
    overlay.className = 'efl-lock';
    overlay.hidden = true;
    overlay.innerHTML = `
        <section class="efl-lock__panel" role="alertdialog" aria-modal="true" aria-labelledby="efl-lock-title"
                aria-describedby="efl-lock-message" tabindex="-1">
            <div class="efl-lock__signal" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="efl-lock__icon" aria-hidden="true">!</div>
            <p class="efl-lock__eyebrow" data-lock-brand></p>
            <h1 id="efl-lock-title" data-lock-title></h1>
            <p id="efl-lock-message" data-lock-message></p>
            <div class="efl-lock__waiting"><i aria-hidden="true"></i><span data-lock-waiting></span></div>
        </section>`;
    overlay.querySelector('[data-lock-brand]').textContent = config.strings.pluginname;
    overlay.querySelector('[data-lock-waiting]').textContent = config.strings.waitingdecision;
    document.body.appendChild(overlay);
    return overlay;
};

const showLock = (title, message, mode = 'paused') => {
    const node = ensureOverlay();
    node.dataset.mode = mode;
    node.querySelector('[data-lock-title]').textContent = title;
    node.querySelector('[data-lock-message]').textContent = message;
    node.querySelector('.efl-lock__waiting').hidden = mode !== 'paused';
    node.hidden = false;
    document.documentElement.classList.add('efl-attempt-locked');
};

const getQuizTimer = () => {
    const timer = window.M?.mod_quiz?.timer;
    if (timer && timer !== quizTimer) {
        quizTimer = timer;
        // Moodle autosave can otherwise restart the timer while the page is paused.
        const updateEndTime = timer.updateEndTime;
        if (typeof updateEndTime === 'function') {
            timer.updateEndTime = (timeLeft) => {
                if (!locked) {
                    updateEndTime.call(timer, timeLeft);
                }
            };
        }
    }
    return timer;
};

const lockPage = (title, message, mode) => {
    if (!locked) {
        locked = true;
        appliedDeadline = 0;
        focusBeforePause = document.activeElement;
        blockedElements = Array.from(document.body.children).filter((element) => element !== overlay && !element.inert);
        blockedElements.forEach((element) => {
            element.inert = true;
        });
    }
    getQuizTimer()?.stop();
    showLock(title, message, mode);
    overlay.querySelector('.efl-lock__panel').focus({preventScroll: true});
};

const handleState = (state) => {
    if (transitionTimer !== null) {
        return;
    }
    if (state.terminated || (locked && state.status === 'completed')) {
        lockPage(state.terminated ? config.strings.terminated : config.strings.completed, '', 'terminated');
        transitionTimer = window.setTimeout(() => window.location.assign(config.quizurl), 1200);
        return;
    }
    if (locked && state.status === 'overdue') {
        // Moodle's grace period allows submitting saved responses from the summary.
        window.location.assign(config.summaryurl);
        return;
    }
    if (state.paused) {
        if (locked) {
            getQuizTimer()?.stop();
        } else {
            const title = state.manualpause ? config.strings.teacherpaused : config.strings.focuslost;
            const detail = state.manualpause ? config.strings.teacherpauseddetail : config.strings.focuslostdetail;
            lockPage(title, detail, 'paused');
        }
        return;
    }
    if (state.status !== 'active') {
        return;
    }
    if (locked) {
        locked = false;
        blockedElements.forEach((element) => {
            element.inert = false;
        });
        blockedElements = [];
        overlay.hidden = true;
        document.documentElement.classList.remove('efl-attempt-locked');
        statusPill.lastElementChild.textContent = config.strings.resumed;
        if (focusBeforePause?.isConnected) {
            focusBeforePause.focus({preventScroll: true});
        }
        focusBeforePause = null;
    }
    const timer = getQuizTimer();
    if (timer?.Y && state.deadline > 0 && state.deadline !== appliedDeadline) {
        appliedDeadline = state.deadline;
        timer.stop();
        timer.endtime = Date.now() + (state.deadline - state.servernow) * 1000;
        timer.update();
    }
};

const heartbeat = async() => {
    if (sending || pageInactive || reporting) {
        return;
    }
    sending = true;
    const revision = observationRevision;
    try {
        const progress = getProgress();
        const state = await call('quizaccess_examfocuslive_heartbeat', {
            attemptid: config.attemptid,
            page: progress.page,
            progress: progress.progress,
            questionlabel: progress.questionlabel,
            focusstate: document.hasFocus() && !document.hidden ? 'focused' : 'blurred',
        });
        if (!pageInactive && revision === observationRevision) {
            statusPill.lastElementChild.textContent = config.strings.monitoring;
            handleState(state);
        }
    } catch {
        statusPill.lastElementChild.textContent = config.connectionlost;
    } finally {
        sending = false;
    }
};

const reportFocusLoss = async() => {
    if (locked || pageInactive || lossReported || reporting) {
        return;
    }
    lossReported = true;
    reporting = true;
    observationRevision++;
    const eventtype = document.hidden ? 'visibilitychange' : 'blur';
    try {
        const eventtoken = Array.from(window.crypto.getRandomValues(new Uint8Array(16)),
            (byte) => byte.toString(16).padStart(2, '0')).join('');
        const send = () => call('quizaccess_examfocuslive_focus_lost', {
            attemptid: config.attemptid, eventtype, eventtoken,
        });
        let state;
        try {
            state = await send();
        } catch {
            if (pageInactive) {
                return;
            }
            state = await send();
        }
        if (!pageInactive) {
            handleState(state);
        }
    } catch {
        statusPill.lastElementChild.textContent = config.connectionlost;
    } finally {
        reporting = false;
    }
};

const checkFocus = () => {
    cancelFocusCheck();
    if (pageInactive) {
        return;
    }
    if (!document.hidden && document.hasFocus()) {
        lossReported = false;
        return;
    }
    const wait = Math.max(config.gracems, navigationUntil - Date.now());
    focusTimer = window.setTimeout(() => {
        if (document.hidden || !document.hasFocus()) {
            reportFocusLoss();
        }
    }, wait);
};

export const init = (options) => {
    config = options;
    ensurePill();
    ensureOverlay();
    getQuizTimer();

    // Only a same-tab Moodle response form may delay the focus check. A failed
    // or cancelled form submission cannot suspend observation indefinitely.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.id === 'responseform' && form.action === config.processurl &&
                (!form.target || form.target === '_self')) {
            navigationUntil = Date.now() + 2500;
            checkFocus();
        }
    });
    document.addEventListener('visibilitychange', checkFocus);
    window.addEventListener('blur', checkFocus);
    window.addEventListener('focus', checkFocus);
    window.addEventListener('pagehide', () => {
        pageInactive = true;
        observationRevision++;
        cancelFocusCheck();
    });
    window.addEventListener('pageshow', () => {
        pageInactive = false;
        navigationUntil = 0;
        checkFocus();
    });

    heartbeat();
    window.setInterval(heartbeat, Math.max(2, config.interval) * 1000);
};
