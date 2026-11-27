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
 * Manage the live teacher monitoring dashboard.
 *
 * @module     quizaccess_examfocuslive/dashboard
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';

let config;
let root;
let students = [];
let displaymode = 'both';
let canmanage = false;
let currentFilter = 'all';
let sortMode = 'name';
let layout = 'compact';
const collator = new Intl.Collator(document.documentElement.lang || undefined, {sensitivity: 'base', numeric: true});
let query = '';
let polling = false;
let pendingAction = null;
let actionInProgress = false;
let refreshAfterPoll = false;
const selectedAttempts = new Set();
const actions = {
    pause: {state: 'active', title: 'pausetitle', message: 'pauseconfirm', icon: 'Ⅱ'},
    resume: {state: 'paused', title: 'resumetitle', message: 'resumeconfirm', icon: '▶'},
    terminate: {title: 'terminate', message: 'terminateconfirm', icon: '■'},
};

const canControl = (student) => student.attemptid > 0 && student.canmanage &&
    ['active', 'paused'].includes(student.rawstatus);

const canApply = (student, action) => canControl(student) &&
    (!actions[action].state || student.rawstatus === actions[action].state);

const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

const formatDuration = (seconds) => {
    const safe = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const secs = safe % 60;
    return hours > 0
        ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
        : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

const formatAgo = (seconds) => {
    if (seconds < 5) {
        return config.strings.lastseennow;
    }
    if (seconds < 60) {
        return config.strings.lastseenseconds.replace('{seconds}', seconds);
    }
    return config.strings.lastseenminutes.replace('{minutes}', Math.floor(seconds / 60));
};

const statusLabel = (status) => config.strings[status] || status;

const matchesFilter = (student) => {
    if (query && !student.fullname.toLocaleLowerCase().includes(query)) {
        return false;
    }
    if (currentFilter === 'paused') {
        return student.rawstatus === 'paused';
    }
    if (currentFilter === 'active') {
        return student.status === 'active';
    }
    if (currentFilter === 'finished') {
        return ['completed', 'terminated'].includes(student.rawstatus);
    }
    return true;
};

// Prepare plain values only; Mustache escapes names, labels and attributes.
const cardStatus = (student, compact, blurred) => {
    if (compact && student.manualpause) {
        return config.strings.teacherpaused;
    }
    if (compact && blurred) {
        return config.strings.unfocused;
    }
    return statusLabel(student.status);
};

const cardLocation = (student) => {
    const haspage = Boolean(student.page);
    const page = haspage ? `${config.strings.page} ${student.page}` : '';
    let location = '—';
    if (haspage) {
        location = student.questionlabel ? `${page} · ${student.questionlabel}` : page;
    }
    return {haspage, page, location, questionlabel: student.questionlabel || ''};
};

const cardControls = (student, paused) => {
    const controls = canmanage && canControl(student);
    const available = paused ? ['resume', 'terminate'] : ['pause', 'terminate'];
    return {
        controls,
        attemptid: student.attemptid,
        selectlabel: controls ? config.strings.selectstudent.replace('{name}', () => student.fullname) : '',
        selected: selectedAttempts.has(student.attemptid),
        buttons: controls ? available.map((action) => ({
            action,
            attemptid: student.attemptid,
            icon: actions[action].icon,
            label: config.strings[action],
        })) : [],
    };
};

const cardProgress = (student, haspage) => {
    const hasprogress = Number.isFinite(student.progress) && student.progress >= 0;
    const progress = hasprogress ? Math.min(100, student.progress) : 0;
    return {
        hasprogress,
        progress,
        progresslabel: haspage && hasprogress ? `${progress}%` : '',
    };
};

const cardContext = (student, compact) => {
    const showphoto = Boolean(displaymode !== 'name' && student.pictureurl);
    const showname = displaymode !== 'photo' || !showphoto;
    const blurred = student.status === 'active' && student.focusstate === 'blurred';
    const paused = student.rawstatus === 'paused';
    const location = cardLocation(student);
    let lastseen = compact ? '' : '—';
    if (student.lastseen) {
        lastseen = formatAgo(student.secondsago);
    }
    return {
        compact,
        userid: student.userid,
        state: student.status,
        paused,
        blurred,
        showphoto,
        showname,
        name: student.fullname,
        pictureurl: student.pictureurl || '',
        alertlabel: student.manualpause ? config.strings.teacherpaused : config.strings.pausedalert,
        status: cardStatus(student, compact, blurred),
        ...location,
        ...cardProgress(student, location.haspage),
        hasincidents: Boolean(student.incidents),
        incidents: student.incidents,
        incidentslabel: config.strings.incidents,
        elapsed: formatDuration(student.elapsed),
        lastseen,
        locationlabel: config.strings.location,
        elapsedlabel: config.strings.activeelapsed,
        lastseenlabel: config.strings.lastseen,
        ...cardControls(student, paused),
    };
};

const studentCard = (student) => cardContext(student, false);

const statusOrder = {paused: 0, overdue: 1, offline: 2, active: 3, waiting: 4, completed: 5, terminated: 6};
const compareStudents = (a, b) => {
    const byName = () => collator.compare(a.sortname, b.sortname) || a.userid - b.userid;
    const comparisons = {
        status: () => (statusOrder[a.status] ?? 6) - (statusOrder[b.status] ?? 6),
        incidents: () => b.incidents - a.incidents,
        progress: () => b.progress - a.progress,
        seen: () => b.lastseen - a.lastseen,
    };
    return (comparisons[sortMode]?.() ?? 0) || byName();
};

const compactCard = (student) => cardContext(student, true);

const bulkTargets = (action, all) => students.filter((student) => canApply(student, action) &&
    (all || selectedAttempts.has(student.attemptid)));

const updateBulkControls = (filtered) => {
    const bulk = root.querySelector('[data-bulk]');
    bulk.hidden = !canmanage;
    if (!canmanage) {
        return;
    }
    const selected = selectedAttempts.size;
    const all = bulk.querySelector('[data-action-scope]').value === 'all';
    bulk.querySelector('[data-selection-count]').textContent = config.strings.selectioncount.replace('{count}', selected);
    bulk.querySelector('[data-clear-selection]').disabled = selected === 0;
    bulk.querySelector('[data-select-visible]').disabled = !filtered.some((student) =>
        canControl(student) && !selectedAttempts.has(student.attemptid));
    bulk.querySelectorAll('[data-bulk-action]').forEach((button) => {
        button.disabled = !students.some((student) => canApply(student, button.dataset.bulkAction) &&
            (all || selectedAttempts.has(student.attemptid)));
    });
};

let renderRevision = 0;

const render = async() => {
    const revision = ++renderRevision;
    const filtered = students.filter(matchesFilter).sort(compareStudents);
    const cards = filtered.map(layout === 'compact' ? compactCard : studentCard);
    const html = await Templates.render('quizaccess_examfocuslive/student_cards', {cards});
    if (revision !== renderRevision) {
        return;
    }
    const template = document.createElement('template');
    template.innerHTML = html;
    const grid = root.querySelector('[data-student-grid]');
    if (grid.getAttribute('aria-busy') === 'true') {
        grid.replaceChildren();
    }
    // Preserve the control under the teacher's keyboard focus during live refresh.
    const active = document.activeElement;
    const focusedAttempt = grid.contains(active) ? active.dataset.attempt : null;
    const focusedAction = focusedAttempt ? active.dataset.action : null;
    const existing = new Map(Array.from(grid.children, (card) => [card.dataset.studentCard, card]));
    let position = grid.firstElementChild;
    for (const rendered of Array.from(template.content.children)) {
        const key = rendered.dataset.studentCard;
        const renderedHtml = rendered.outerHTML;
        const previous = existing.get(key);
        existing.delete(key);
        let card = previous;
        if (!card || card.renderedHtml !== renderedHtml) {
            card = rendered;
            card.renderedHtml = renderedHtml;
            if (previous) {
                if (position === previous) {
                    position = card;
                }
                previous.replaceWith(card);
            }
        }
        if (card !== position) {
            grid.insertBefore(card, position);
        }
        position = card.nextElementSibling;
    }
    existing.forEach((card) => card.remove());
    if (focusedAttempt && document.activeElement !== active) {
        const button = Array.from(grid.querySelectorAll('[data-attempt]')).find((item) =>
            item.dataset.attempt === focusedAttempt && item.dataset.action === focusedAction);
        (button || root.querySelector('[data-search]')).focus({preventScroll: true});
    }
    grid.setAttribute('aria-busy', 'false');
    root.querySelector('[data-empty]').hidden = filtered.length > 0;
    root.querySelector('[data-visible-count]').textContent = `${filtered.length} / ${students.length}`;

    root.querySelector('[data-stat="total"]').textContent = students.length;
    root.querySelector('[data-stat="online"]').textContent = students.filter((s) => s.status === 'active').length;
    root.querySelector('[data-stat="alerts"]').textContent = students.filter((s) => s.rawstatus === 'paused').length;
    root.querySelector('[data-stat="finished"]').textContent = students.filter((s) =>
        ['completed', 'terminated'].includes(s.rawstatus)).length;
    updateBulkControls(filtered);
};

// Event-driven refreshes must handle template loading failures as well as polling.
const renderFromInteraction = () => {
    render().catch(() => {
        root.querySelector('[data-sync-label]').textContent = config.strings.syncretrying;
        root.classList.add('is-disconnected');
    });
};

const poll = async(force = false) => {
    if (polling) {
        refreshAfterPoll ||= force;
        return;
    }
    polling = true;
    root.querySelector('[data-sync-label]').textContent = config.strings.syncupdating;
    try {
        const data = await call('quizaccess_examfocuslive_dashboard', {cmid: config.cmid});
        students = data.students;
        displaymode = data.displaymode;
        canmanage = data.canmanage;
        const eligible = new Set(students.filter(canControl).map((student) => student.attemptid));
        for (const attemptid of selectedAttempts) {
            if (!canmanage || !eligible.has(attemptid)) {
                selectedAttempts.delete(attemptid);
            }
        }
        await render();
        root.querySelector('[data-sync-label]').textContent = config.strings.synclive;
        root.classList.remove('is-disconnected');
    } catch {
        root.querySelector('[data-sync-label]').textContent = config.strings.syncretrying;
        root.classList.add('is-disconnected');
    } finally {
        polling = false;
        if (refreshAfterPoll) {
            refreshAfterPoll = false;
            await poll();
        }
    }
};

const openDialog = (action, attemptid = null, all = false) => {
    if (pendingAction || actionInProgress || !canmanage || !Object.hasOwn(actions, action)) {
        return;
    }
    const group = attemptid === null;
    const targets = group ? bulkTargets(action, all) : students.filter((student) =>
        student.attemptid === attemptid && canApply(student, action));
    if (!targets.length) {
        return;
    }
    pendingAction = {
        action,
        group,
        targets: targets.map((student) => ({attemptid: student.attemptid, revision: student.revision})),
    };
    const backdrop = root.querySelector('[data-dialog-backdrop]');
    const isTerminate = action === 'terminate';
    const targetLabel = group
        ? config.strings.bulkconfirm.replace('{count}', targets.length)
        : targets[0].fullname + '.';
    backdrop.querySelector('[data-dialog-title]').textContent = config.strings[actions[action].title];
    backdrop.querySelector('[data-dialog-message]').textContent = `${targetLabel} ${config.strings[actions[action].message]}`;
    backdrop.querySelector('[data-dialog-icon]').textContent = actions[action].icon;
    backdrop.querySelector('[data-dialog-confirm]').textContent = config.strings[action];
    backdrop.querySelector('[data-dialog-confirm]').className = `efl-button ${isTerminate
        ? 'efl-button--danger' : 'efl-button--primary'}`;
    backdrop.dataset.action = action;
    backdrop.querySelector('[data-dialog-error]').hidden = true;
    backdrop.hidden = false;
    backdrop.querySelector(isTerminate || group ? '[data-dialog-cancel]' : '[data-dialog-confirm]').focus();
};

const closeDialog = () => {
    if (actionInProgress || !pendingAction) {
        return;
    }
    const {group, action, targets} = pendingAction;
    root.querySelector('[data-dialog-backdrop]').hidden = true;
    pendingAction = null;
    const trigger = group ? root.querySelector(`[data-bulk-action="${action}"]`)
        : root.querySelector(`button[data-attempt="${targets[0].attemptid}"][data-action="${action}"]`);
    (trigger && !trigger.disabled ? trigger : root.querySelector('[data-search]')).focus({preventScroll: true});
};

const showToast = (message) => {
    const toast = root.querySelector('[data-toast]');
    toast.textContent = message;
    toast.hidden = false;
    window.setTimeout(() => {
        toast.hidden = true;
    }, 2600);
};

const confirmAction = async() => {
    if (!pendingAction || actionInProgress) {
        return;
    }

    const backdrop = root.querySelector('[data-dialog-backdrop]');
    const buttons = backdrop.querySelectorAll('button');
    const errorMessage = backdrop.querySelector('[data-dialog-error]');
    errorMessage.hidden = true;
    const {group, action, targets} = pendingAction;
    let message = config.strings.statusupdated;
    actionInProgress = true;
    buttons.forEach((button) => {
        button.disabled = true;
    });
    try {
        if (group) {
            let updated = 0;
            for (let offset = 0; offset < targets.length; offset += 25) {
                const response = await call('quizaccess_examfocuslive_bulk_control', {
                    cmid: config.cmid, action, targets: targets.slice(offset, offset + 25),
                });
                updated += response.results.filter((result) => result.outcome === 'updated').length;
            }
            message = config.strings.bulkresult.replace('{updated}', updated).replace('{skipped}', targets.length - updated);
        } else {
            await call('quizaccess_examfocuslive_control', {action, ...targets[0]});
        }
    } catch (error) {
        if (group) {
            message = config.strings.bulkfailed;
        } else if (error?.errorcode === 'staleaction') {
            message = config.strings.staleaction;
        } else {
            errorMessage.textContent = config.strings.controlfailed;
            errorMessage.hidden = false;
            return;
        }
    } finally {
        actionInProgress = false;
        buttons.forEach((button) => {
            button.disabled = false;
        });
        backdrop.querySelector('[data-dialog-confirm]').focus();
    }

    closeDialog();
    showToast(message);
    await poll(true);
};

const bind = () => {
    root.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-filter]');
        if (filter) {
            currentFilter = filter.dataset.filter;
            root.querySelectorAll('[data-filter]').forEach((item) => {
                const active = item === filter;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', String(active));
            });
            renderFromInteraction();
            return;
        }
        const action = event.target.closest('button[data-action][data-attempt]');
        if (action) {
            openDialog(action.dataset.action, Number.parseInt(action.dataset.attempt, 10));
            return;
        }
        const bulkAction = event.target.closest('[data-bulk-action]');
        if (bulkAction) {
            const all = root.querySelector('[data-action-scope]').value === 'all';
            openDialog(bulkAction.dataset.bulkAction, null, all);
            return;
        }
        if (event.target.closest('[data-select-visible]')) {
            students.filter((student) => matchesFilter(student) && canControl(student))
                .forEach((student) => selectedAttempts.add(student.attemptid));
            renderFromInteraction();
            return;
        }
        if (event.target.closest('[data-clear-selection]')) {
            selectedAttempts.clear();
            renderFromInteraction();
            return;
        }
        if (event.target.closest('[data-dialog-close]')) {
            closeDialog();
        }
        if (event.target.closest('[data-dialog-confirm]')) {
            confirmAction();
        }
    });
    root.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-select-attempt]');
        if (checkbox) {
            const attemptid = Number.parseInt(checkbox.dataset.attempt, 10);
            if (checkbox.checked) {
                selectedAttempts.add(attemptid);
            } else {
                selectedAttempts.delete(attemptid);
            }
            renderFromInteraction();
        } else if (event.target.matches('[data-action-scope]')) {
            updateBulkControls(students.filter(matchesFilter));
        }
    });
    root.querySelector('[data-search]').addEventListener('input', (event) => {
        query = event.target.value.trim().toLocaleLowerCase();
        renderFromInteraction();
    });
    document.addEventListener('keydown', (event) => {
        if (!pendingAction) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
        } else if (event.key === 'Tab') {
            const buttons = Array.from(root.querySelectorAll('[data-dialog-backdrop] button:not(:disabled)'));
            const first = buttons[0];
            const last = buttons[buttons.length - 1];
            if (!first || !buttons.includes(document.activeElement) ||
                    (event.shiftKey ? document.activeElement === first : document.activeElement === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first)?.focus();
            }
        }
    });
};

const bindFullscreen = () => {
    const button = root.querySelector('[data-fullscreen]');
    const isExpanded = () => document.fullscreenElement === root || root.classList.contains('efl-expanded');
    const updateButton = () => {
        const expanded = isExpanded();
        button.setAttribute('aria-pressed', String(expanded));
        button.textContent = config.strings[expanded ? 'exitfullscreen' : 'fullscreen'];
    };
    const leaveExpanded = () => {
        root.removeAttribute('popover');
        root.classList.remove('efl-expanded');
        document.documentElement.classList.remove('efl-dashboard-expanded');
        updateButton();
        button.focus({preventScroll: true});
    };
    const leave = async() => {
        if (document.fullscreenElement === root) {
            try {
                await document.exitFullscreen();
            } catch {
                updateButton();
            }
        } else if (root.classList.contains('efl-expanded')) {
            leaveExpanded();
        }
    };

    button.addEventListener('click', async() => {
        if (isExpanded()) {
            await leave();
            return;
        }
        try {
            if (document.fullscreenEnabled && root.requestFullscreen) {
                await root.requestFullscreen();
                updateButton();
                return;
            }
        } catch {
            // A framed Moodle instance can deny access to the browser fullscreen API.
        }
        root.classList.add('efl-expanded');
        document.documentElement.classList.add('efl-dashboard-expanded');
        if (root.showPopover) {
            try {
                root.setAttribute('popover', 'manual');
                root.showPopover();
            } catch {
                root.removeAttribute('popover');
            }
        }
        updateButton();
        button.focus({preventScroll: true});
    });
    const quizLink = root.querySelector('[data-back-to-quiz]');
    quizLink.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }
        // Keep native link navigation independent of the browser's fullscreen transition.
        void leave();
    });
    document.addEventListener('fullscreenchange', updateButton);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !event.defaultPrevented && root.classList.contains('efl-expanded')) {
            event.preventDefault();
            leaveExpanded();
        }
    });
};

export const init = (options) => {
    root = document.querySelector('[data-region="examfocuslive-dashboard"]');
    if (!root) {
        return;
    }
    config = {...options, strings: JSON.parse(root.dataset.strings)};
    bind();
    root.querySelector('[data-sort]').addEventListener('change', (event) => {
        sortMode = event.target.value;
        renderFromInteraction();
    });
    root.querySelector('[data-layout]').addEventListener('change', (event) => {
        layout = event.target.value;
        root.dataset.layoutMode = layout;
        renderFromInteraction();
    });
    bindFullscreen();
    poll();
    const schedule = () => {
        const hasActive = students.some((student) => ['active', 'paused'].includes(student.rawstatus));
        window.setTimeout(async() => {
            await poll();
            schedule();
        }, Math.max(hasActive && !document.hidden ? 3 : 10, config.interval) * 1000);
    };
    schedule();
};
