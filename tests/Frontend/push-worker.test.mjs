import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

/** Creates an isolated worker with optional window clients and test-only diagnostic storage. */
function createWorker({ windows = [], failDiagnosticStorage = false } = {}) {
    const handlers = {};
    const notifications = [];
    const opened = [];
    const diagnostics = [];
    const self = {
        location: { origin: 'https://mytodo.php' },
        addEventListener: (event, handler) => { handlers[event] = handler; },
        registration: { showNotification: async (title, options) => { notifications.push({ title, ...options }); } },
        clients: { matchAll: async () => windows, openWindow: async (url) => { opened.push(url); } },
        caches: { open: async () => {
            if (failDiagnosticStorage) throw new Error('Storage unavailable');
            return { put: async (url, response) => { diagnostics.push(await response.json()); } };
        } },
        skipWaiting: async () => {}
    };
    vm.runInNewContext(source, { self, URL, Response });
    return { handlers, notifications, opened, diagnostics };
}

/** Dispatches a test notification click and waits for all of its lifetime work. */
async function clickTestNotification(worker, tag = 'mytodo-push-test-0123456789abcdef') {
    let pending;
    worker.handlers.notificationclick({
        notification: { tag, close: () => {}, data: { url: 'https://mytodo.php/notifications' } },
        waitUntil: (promise) => { pending = promise; }
    });
    await pending;
}

test('push shows title/body and never opens an external payload URL', async () => {
    const worker = createWorker();
    let pending;
    worker.handlers.push({ data: { json: () => ({ title: 'Task فارسی', body: 'Due soon', url: 'https://attacker.test/', tag: 'reminder-7' }) }, waitUntil: (promise) => { pending = promise; } });
    await pending;
    assert.equal(worker.notifications[0].title, 'Task فارسی');
    assert.equal(worker.notifications[0].data.url, 'https://mytodo.php/notifications');
    assert.equal(worker.notifications[0].tag, 'reminder-7');
});

test('malformed push still displays a user-visible fallback', async () => {
    const worker = createWorker();
    let pending;
    worker.handlers.push({ data: { json: () => { throw new Error('Invalid JSON'); } }, waitUntil: (promise) => { pending = promise; } });
    await pending;
    assert.equal(worker.notifications[0].title, 'MyTodo');
});

test('click closes notification and opens only an allowed application page', async () => {
    const worker = createWorker();
    let closed = false;
    let pending;
    worker.handlers.notificationclick({ notification: { close: () => { closed = true; }, data: { url: 'javascript:alert(1)' } }, waitUntil: (promise) => { pending = promise; } });
    await pending;
    assert.ok(closed);
    assert.deepEqual(worker.opened, ['https://mytodo.php/notifications']);
});

test('test click records a correlated open-window report without payload data', async () => {
    const worker = createWorker();
    await clickTestNotification(worker);
    assert.deepEqual(worker.opened, ['https://mytodo.php/notifications']);
    assert.deepEqual(worker.diagnostics, [{
        event: 'push_test_click', entryPoint: 'notificationclick', traceId: '0123456789abcdef',
        stages: ['click-received', 'matching-windows', 'opening-window', 'window-opened'],
        errorName: null
    }]);
});

test('test click records navigation and focus of an existing application window', async () => {
    const calls = [];
    const client = {
        url: 'https://mytodo.php/account-settings',
        navigate: async (url) => { calls.push(['navigate', url]); },
        focus: async () => { calls.push(['focus']); }
    };
    const worker = createWorker({ windows: [client] });
    await clickTestNotification(worker);
    assert.deepEqual(calls, [['navigate', 'https://mytodo.php/notifications'], ['focus']]);
    assert.deepEqual(worker.diagnostics[0]?.stages, [
        'click-received', 'matching-windows', 'window-found', 'navigating',
        'navigation-complete', 'focusing', 'focus-complete'
    ]);
});

test('test click records only an allowlisted error name and preserves navigation rejection', async () => {
    const failure = new TypeError('Secret endpoint and key must not be recorded');
    const worker = createWorker({ windows: [{
        url: 'https://mytodo.php/account-settings',
        navigate: async () => { throw failure; }
    }] });
    await assert.rejects(clickTestNotification(worker), (error) => error === failure);
    assert.equal(worker.diagnostics[0]?.errorName, 'TypeError');
    assert.deepEqual(worker.diagnostics[0]?.stages, [
        'click-received', 'matching-windows', 'window-found', 'navigating'
    ]);
    assert.ok(!JSON.stringify(worker.diagnostics).includes('Secret'));
});

test('diagnostic storage failure does not break clicking and ordinary reminders write no diagnostics', async () => {
    const unavailable = createWorker({ failDiagnosticStorage: true });
    await clickTestNotification(unavailable);
    assert.deepEqual(unavailable.opened, ['https://mytodo.php/notifications']);
    const ordinary = createWorker();
    await clickTestNotification(ordinary, 'mytodo-reminder-7');
    assert.equal(ordinary.diagnostics.length, 0);
});
