const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const moduleCode = fs.readFileSync(path.join(__dirname, '../amd/build/student_monitor.min.js'), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));

async function monitor() {
    const signals = [];
    const handlers = new Map();
    const timers = new Map();
    const form = {id: 'responseform', action: 'https://moodle.test/mod/quiz/processattempt.php', inert: false};
    const previouslyInert = {inert: true};
    const redirects = [];
    const overlayText = new Map();
    let tick = 0;
    let heartbeat;
    const clock = {
        endtime: 0, Y: {}, stops: 0, updates: 0,
        stop() {this.stops++;},
        update() {this.updates++;},
        updateEndTime(seconds) {this.endtime = Date.now() + seconds * 1000; this.update();},
    };
    const document = {
        hidden: false,
        focused: true,
        hasFocus() {return this.focused;},
        querySelector: () => null,
        querySelectorAll: () => [],
        addEventListener: (event, callback) => handlers.set(event, callback),
        getElementById: () => form,
        body: {children: [form, previouslyInert], appendChild(element) {this.children.push(element);}},
        documentElement: {classList: {add() {}, remove() {}}},
        createElement: () => ({
            dataset: {}, lastElementChild: {}, setAttribute() {},
            querySelector: (selector) => {
                if (!overlayText.has(selector)) {
                    overlayText.set(selector, {focus() {}});
                }
                return overlayText.get(selector);
            },
        }),
    };
    const browser = {
        M: {mod_quiz: {timer: clock}},
        crypto: require('node:crypto').webcrypto,
        location: {assign: (url) => redirects.push(url)},
        addEventListener: document.addEventListener,
        setTimeout: (callback, delay) => {timers.set(++tick, {callback, delay}); return tick;},
        clearTimeout: (id) => timers.delete(id),
        setInterval: (callback) => {heartbeat = callback;},
    };
    const active = {status: 'active'};
    let nextHeartbeat = active;
    let receive = () => Promise.resolve(active);
    let ready;
    vm.runInNewContext(moduleCode, {document, window: browser, define: (name, dependencies, factory) => {
        assert.equal(name, 'quizaccess_examfocuslive/student_monitor');
        assert.equal(dependencies.join(','), 'core/ajax,core/templates');
        ready = factory({call: ([request]) => {
            if (request.methodname.endsWith('_focus_lost')) {
                signals.push(request.args);
                return [receive()];
            }
            return [Promise.resolve(nextHeartbeat)];
        }}, {render: async(name) => {
            assert.equal(name, 'quizaccess_examfocuslive/student_lock');
            return '';
        }}).init({
            attemptid: 5, interval: 5, gracems: 1000, processurl: form.action,
            summaryurl: 'https://moodle.test/mod/quiz/summary.php?attempt=5',
            quizurl: 'https://moodle.test/mod/quiz/view.php?id=9', strings: {
                teacherpaused: 'Paused by the teacher',
                teacherpauseddetail: 'Wait for permission to continue',
                focuslost: 'Focus loss',
            },
        });
    }});
    await ready;
    return {
        document, form, signals, clock, previouslyInert, redirects, overlayText,
        emit: (event, detail) => handlers.get(event)(detail),
        drain: () => {
            for (const [id, timer] of timers) {
                timers.delete(id);
                timer.callback();
            }
        },
        setReceiver: (callback) => {receive = callback;},
        heartbeat: (state) => {nextHeartbeat = state; return heartbeat();},
    };
}

test('quiz submission and pagehide in either order produce no incident', async() => {
    for (const events of [['visibilitychange', 'pagehide'], ['pagehide', 'visibilitychange']]) {
        const m = await monitor();
        m.emit('submit', {target: m.form});
        m.document.hidden = true;
        m.document.focused = false;
        for (const event of events) {
            m.emit(event);
        }
        m.drain();
        assert.equal(m.signals.length, 0);
    }
});

test('brief loss is ignored and sustained loss produces one incident', async() => {
    const m = await monitor();
    m.document.focused = false;
    m.emit('blur');
    m.document.focused = true;
    m.emit('focus');
    m.drain();
    assert.equal(m.signals.length, 0);
    m.document.focused = false;
    m.document.hidden = true;
    m.emit('blur');
    m.emit('visibilitychange');
    m.drain();
    await flush();
    m.emit('blur');
    m.drain();
    assert.deepEqual(m.signals.map((signal) => signal.eventtype), ['visibilitychange']);
});

test('failed network request reuses the same token and leaves answers available', async() => {
    const m = await monitor();
    m.setReceiver(() => Promise.reject(new Error('offline')));
    m.document.focused = false;
    m.emit('blur');
    m.drain();
    await flush();
    assert.equal(m.signals.length, 2);
    assert.equal(m.signals[0].eventtoken, m.signals[1].eventtoken);
    assert.equal(m.form.inert, false);
});

test('confirmed pause stops Moodle timer and resume preserves the answer form', async() => {
    const m = await monitor();
    await flush();
    await m.heartbeat({status: 'paused', paused: true});
    assert.equal(m.clock.stops, 1);
    assert.equal(m.form.inert, true);
    m.clock.updateEndTime(6);
    assert.equal(m.clock.updates, 0);
    await m.heartbeat({status: 'active', deadline: 145, servernow: 100});
    assert.equal(m.clock.updates, 1);
    assert.equal(m.form.inert, false);
    assert.equal(m.previouslyInert.inert, true);
    assert.ok(m.clock.endtime - Date.now() > 44000);
});

test('teacher pause has its own notice and does not require a focus signal', async() => {
    const m = await monitor();
    await flush();
    await m.heartbeat({status: 'paused', paused: true, manualpause: true});
    assert.equal(m.document.focused, true);
    assert.equal(m.form.inert, true);
    assert.equal(m.overlayText.get('[data-lock-title]').textContent, 'Paused by the teacher');
    assert.equal(m.overlayText.get('[data-lock-message]').textContent, 'Wait for permission to continue');
    assert.equal(m.signals.length, 0);
});

test('a resumed deadline is applied once even if the browser never saw the pause', async() => {
    const m = await monitor();
    await flush();
    await m.heartbeat({status: 'active', deadline: 175, servernow: 130});
    await m.heartbeat({status: 'active', deadline: 175, servernow: 131});
    assert.equal(m.clock.updates, 1);
    assert.equal(m.clock.stops, 1);
    assert.ok(m.clock.endtime - Date.now() > 44000);
});

test('the submission grace period opens Moodle’s summary from a paused form', async() => {
    const m = await monitor();
    await flush();
    await m.heartbeat({status: 'paused', paused: true});
    await m.heartbeat({status: 'overdue'});
    assert.deepEqual(m.redirects, ['https://moodle.test/mod/quiz/summary.php?attempt=5']);
});

test('teacher termination stops the timer before redirecting', async() => {
    const m = await monitor();
    await flush();
    await m.heartbeat({status: 'terminated', terminated: true});
    assert.equal(m.clock.stops, 1);
    assert.equal(m.form.inert, true);
    m.drain();
    assert.deepEqual(m.redirects, ['https://moodle.test/mod/quiz/view.php?id=9']);
});

test('heartbeat sent before a confirmed pause cannot unlock the form', async() => {
    const m = await monitor();
    await flush();
    let deliverOldHeartbeat;
    const oldHeartbeat = m.heartbeat(new Promise((resolve) => {deliverOldHeartbeat = resolve;}));
    m.setReceiver(() => Promise.resolve({status: 'paused', paused: true}));
    m.document.hidden = true;
    m.document.focused = false;
    m.emit('visibilitychange');
    m.drain();
    await flush();
    assert.equal(m.form.inert, true);
    deliverOldHeartbeat({status: 'active'});
    await oldHeartbeat;
    assert.equal(m.form.inert, true);
    await m.heartbeat({status: 'active', deadline: 145, servernow: 100});
    assert.equal(m.form.inert, false);
});
