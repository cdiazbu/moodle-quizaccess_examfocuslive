const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function dashboard(document = {documentElement: {lang: 'es'}}) {
    const source = fs.readFileSync(path.join(__dirname, '../amd/src/dashboard.js'), 'utf8')
        .replace("import Ajax from 'core/ajax';", '')
        .replace('export const init = ', 'const init = ');
    const moduleCode = `${source}\n({compareStudents, matchesFilter, compactCard, studentCard, bulkTargets,
        configure(options) { config = options; },
        bindFullscreen(element) { root = element; bindFullscreen(); }, sortBy(mode) { sortMode = mode; },
        setRoster(roster) { students = roster; canmanage = true; },
        setFilter(value) { query = value; }, select(id) { selectedAttempts.add(id); }});`;
    const module = vm.runInNewContext(moduleCode, {
        document,
    });
    module.configure({strings: {
        waiting: 'Sin iniciar', page: 'Página', incidents: 'Incidencias',
        lastseennow: 'Ahora', lastseenseconds: 'Hace {seconds} s', lastseenminutes: 'Hace {minutes} min',
        selectstudent: 'Seleccionar a {name}', pause: 'Pausar', resume: 'Permitir continuar',
        terminate: 'Finalizar intento', teacherpaused: 'Intento pausado por el docente',
    }});
    return module;
}

test('all sort modes preserve an uncapped roster and put the expected student first', () => {
    const panel = dashboard();
    const roster = Array.from({length: 61}, (_, userid) => ({
        userid, attemptid: 0, sortname: 'Apellido ' + userid, fullname: 'Alumno ' + userid,
        status: 'waiting', rawstatus: 'waiting', progress: -1, page: 0, incidents: 0, lastseen: 0, secondsago: 0,
    }));
    Object.assign(roster[40], {status: 'paused', page: 2, progress: 50, incidents: 3, lastseen: 10, secondsago: 10});
    Object.assign(roster[42], {status: 'active', page: 3, progress: 75, incidents: 1, lastseen: 20});
    const expected = {name: 0, status: 40, incidents: 40, progress: 42, seen: 42};
    for (const [mode, first] of Object.entries(expected)) {
        panel.sortBy(mode);
        const visible = roster.filter(panel.matchesFilter).sort(panel.compareStudents);
        assert.equal(visible.length, roster.length);
        assert.equal(visible[0].userid, first, mode);
    }
    assert.match(panel.compactCard({...roster[60], fullname: '<Alumno>'}), /&lt;Alumno&gt;/);
    assert.match(panel.compactCard(roster[60]), /Sin iniciar/);
    assert.doesNotMatch(panel.compactCard(roster[60]), /Página/);
    assert.match(panel.compactCard(roster[40]), /50%/);
    assert.match(panel.compactCard(roster[40]), /!3/);
    assert.match(panel.compactCard(roster[40]), /Hace 10 s/);
});

test('individual and group controls target only eligible started attempts, including offline attempts', () => {
    const panel = dashboard();
    const roster = [
        {attemptid: 1, status: 'active', rawstatus: 'active'},
        {attemptid: 2, status: 'paused', rawstatus: 'paused', manualpause: true},
        {attemptid: 3, status: 'offline', rawstatus: 'active'},
        {attemptid: 4, status: 'completed', rawstatus: 'completed'},
        {attemptid: 5, status: 'active', rawstatus: 'active', canmanage: false},
        {attemptid: 0, status: 'waiting', rawstatus: 'waiting'},
    ].map((student, userid) => ({canmanage: true, fullname: `Alumno ${userid}`, userid, progress: -1, ...student}));
    panel.setRoster(roster);
    panel.select(1);
    panel.select(2);
    panel.select(5);
    panel.setFilter('alumno 0');
    const ids = (action, all) => Array.from(panel.bulkTargets(action, all), (student) => student.attemptid);
    assert.deepEqual(ids('pause', false), [1]);
    assert.deepEqual(ids('pause', true), [1, 3]);
    assert.deepEqual(ids('resume', false), [2]);
    assert.deepEqual(ids('terminate', false), [1, 2]);
    assert.deepEqual(ids('terminate', true), [1, 2, 3]);
    assert.match(panel.compactCard(roster[0]), /data-action="pause"/);
    assert.match(panel.studentCard(roster[0]), /data-action="terminate"/);
    assert.match(panel.compactCard(roster[1]), /checked/);
    assert.match(panel.compactCard(roster[1]), /Intento pausado por el docente/);
    assert.doesNotMatch(panel.compactCard(roster[1]), /data-action="pause"/);
    assert.doesNotMatch(panel.compactCard(roster[4]), /data-select-attempt/);
});

test('quiz link keeps native navigation during fullscreen exit and preserves modified clicks', () => {
    const fullscreenClasses = new Set();
    const button = {setAttribute() {}, focus() {}, addEventListener() {}};
    const link = {addEventListener(_name, handler) { this.click = handler; }};
    const root = {
        classList: {contains: (name) => fullscreenClasses.has(name), remove: (name) => fullscreenClasses.delete(name)},
        querySelector: (selector) => selector === '[data-fullscreen]' ? button : link,
        removeAttribute() {},
    };
    let exitCalls = 0;
    const document = {
        documentElement: {classList: {remove() {}}},
        fullscreenElement: root,
        addEventListener() {},
        exitFullscreen() {
            exitCalls++;
            return new Promise(() => {});
        },
    };
    const panel = dashboard(document);
    panel.bindFullscreen(root);
    const event = {button: 0, preventDefault() { assert.fail('Native link navigation was intercepted'); }};
    link.click({...event, ctrlKey: true});
    assert.equal(exitCalls, 0);
    link.click(event);
    assert.equal(exitCalls, 1);

    document.fullscreenElement = null;
    fullscreenClasses.add('efl-expanded');
    link.click(event);
    assert.equal(fullscreenClasses.has('efl-expanded'), false);
});
