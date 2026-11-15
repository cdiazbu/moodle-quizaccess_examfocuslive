import Ajax from 'core/ajax';

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

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

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

const progressRing = (value) => {
    if (value < 0) {
        return '<span class="efl-progress-na">—</span>';
    }
    return `<div class="efl-progress" style="--progress:${value}" aria-label="${value}%">
        <span>${value}<small>%</small></span>
    </div>`;
};

const actionButtons = (student) => {
    if (!canmanage || !canControl(student)) {
        return '';
    }
    const available = student.rawstatus === 'paused' ? ['resume', 'terminate'] : ['pause', 'terminate'];
    const label = escapeHtml(config.strings.selectstudent.replace('{name}', () => student.fullname));
    const checked = selectedAttempts.has(student.attemptid) ? ' checked' : '';
    const buttons = available.map((action) => `<button type="button" class="efl-button efl-button--${action}"
                data-action="${action}" data-attempt="${student.attemptid}">
            <span aria-hidden="true">${actions[action].icon}</span>${escapeHtml(config.strings[action])}
        </button>`);
    return `<div class="efl-card__actions">
        <label class="efl-select-attempt" title="${label}">
            <input type="checkbox" data-select-attempt data-attempt="${student.attemptid}" aria-label="${label}"${checked}>
        </label>
        ${buttons.join('')}
    </div>`;
};

const studentCard = (student) => {
    const showPhoto = displaymode !== 'name' && student.pictureurl;
    const showName = displaymode !== 'photo' || !showPhoto;
    const alert = student.rawstatus === 'paused';
    const page = `${escapeHtml(config.strings.page)} ${student.page}`;
    const location = student.questionlabel ? `${page} · ${escapeHtml(student.questionlabel)}` : page;
    const question = student.page ? location : '—';
    const incidentClass = student.incidents ? 'efl-danger' : '';
    const elapsed = formatDuration(student.elapsed);
    const lastSeen = student.lastseen ? escapeHtml(formatAgo(student.secondsago)) : '—';
    return `<article class="efl-card efl-card--${student.status}${alert ? ' is-alert' : ''}" data-student-card="${student.userid}">
        ${alert ? `<div class="efl-card__alertbar"><span aria-hidden="true">!</span>
            ${escapeHtml(student.manualpause ? config.strings.teacherpaused : config.strings.pausedalert)}</div>` : ''}
        <div class="efl-card__top">
            <div class="efl-identity ${showPhoto ? '' : 'efl-identity--no-photo'}">
                ${showPhoto ? `<div class="efl-avatar"><img src="${escapeHtml(student.pictureurl)}" alt=""><i></i></div>` : ''}
                <div class="efl-identity__copy">
                    <h2 class="${showName ? '' : 'sr-only'}">${escapeHtml(student.fullname)}</h2>
                    <span class="efl-status"><i></i>${escapeHtml(statusLabel(student.status))}</span>
                </div>
            </div>
            ${progressRing(student.progress)}
        </div>
        <div class="efl-card__details">
            <div><span>${escapeHtml(config.strings.location)}</span><strong>${question}</strong></div>
            <div><span>${escapeHtml(config.strings.activeelapsed)}</span><strong class="efl-mono">${elapsed}</strong></div>
            <div><span>${escapeHtml(config.strings.incidents)}</span><strong class="${incidentClass}">${student.incidents}</strong></div>
            <div><span>${escapeHtml(config.strings.lastseen)}</span><strong>${lastSeen}</strong></div>
        </div>
        ${actionButtons(student)}
    </article>`;
};

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

const compactCard = (student) => {
    const name = escapeHtml(student.fullname);
    const showPhoto = displaymode !== 'name' && student.pictureurl;
    const showName = displaymode !== 'photo' || !showPhoto;
    const outOfFocus = student.status === 'active' && student.focusstate === 'blurred';
    const browserStatus = outOfFocus ? config.strings.unfocused : statusLabel(student.status);
    const status = student.manualpause ? config.strings.teacherpaused : browserStatus;
    const signal = student.lastseen ? formatAgo(student.secondsago) : '';
    const question = student.page ? escapeHtml(`${config.strings.page} ${student.page}`) : '';
    const progress = student.page && student.progress >= 0 ? `${student.progress}%` : '';
    const incidents = student.incidents
        ? `<span class="efl-tile-incidents" title="${escapeHtml(config.strings.incidents)}"
              aria-label="${escapeHtml(config.strings.incidents)}: ${student.incidents}">!${student.incidents}</span>` : '';
    return `<article class="efl-tile efl-tile--${student.status}${outOfFocus ? ' efl-tile--blurred' : ''}"
                     data-student-card="${student.userid}">
        <header class="efl-tile-heading">
            ${showPhoto ? `<img src="${escapeHtml(student.pictureurl)}" alt="" loading="lazy">` : ''}
            <h2 class="${showName ? '' : 'sr-only'}" title="${showName ? name : ''}">${name}</h2>
            ${incidents}
        </header>
        <div class="efl-tile-status"><span>${escapeHtml(status)}</span><span>${progress}</span></div>
        ${student.page ? `<div class="efl-tile-meta"><span title="${escapeHtml(student.questionlabel)}">${question}</span>
            <span>${escapeHtml(signal)}</span></div>` : ''}
        ${actionButtons(student)}
    </article>`;
};

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

const render = () => {
    const filtered = students.filter(matchesFilter).sort(compareStudents);
    const grid = root.querySelector('[data-student-grid]');
    // Preserve the control under the teacher's keyboard focus during live refresh.
    const active = document.activeElement;
    const focusedAttempt = grid.contains(active) ? active.dataset.attempt : null;
    const focusedAction = focusedAttempt ? active.dataset.action : null;
    const existing = new Map(Array.from(grid.children, (card) => [card.dataset.studentCard, card]));
    let position = grid.firstElementChild;
    for (const student of filtered) {
        const key = String(student.userid);
        const html = layout === "compact" ? compactCard(student) : studentCard(student);
        const previous = existing.get(key);
        existing.delete(key);
        let card = previous;
        if (!card || card.renderedHtml !== html) {
            const template = document.createElement('template');
            template.innerHTML = html;
            card = template.content.firstElementChild;
            card.renderedHtml = html;
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
        render();
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
            render();
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
            render();
            return;
        }
        if (event.target.closest('[data-clear-selection]')) {
            selectedAttempts.clear();
            render();
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
            render();
        } else if (event.target.matches('[data-action-scope]')) {
            updateBulkControls(students.filter(matchesFilter));
        }
    });
    root.querySelector('[data-search]').addEventListener('input', (event) => {
        query = event.target.value.trim().toLocaleLowerCase();
        render();
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
    config = options;
    root = document.querySelector('[data-region="examfocuslive-dashboard"]');
    if (!root) {
        return;
    }
    bind();
    root.querySelector('[data-sort]').addEventListener('change', (event) => {
        sortMode = event.target.value;
        render();
    });
    root.querySelector('[data-layout]').addEventListener('change', (event) => {
        layout = event.target.value;
        root.dataset.layoutMode = layout;
        render();
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
