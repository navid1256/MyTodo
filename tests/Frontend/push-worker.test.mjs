import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

/** Creates an isolated worker with optional window clients and test-only diagnostic storage. */
function createWorker({ windows = [], failDiagnosticStorage = false, matchWindows = async () => windows } = {}) {
    const handlers = {};
    const notifications = [];
    const opened = [];
    const diagnostics = [];
    const entries = [];
    const logs = [];
    const self = {
        location: { origin: 'https://mytodo.php' },
        addEventListener: (event, handler) => { handlers[event] = handler; },
        registration: { showNotification: async (title, options) => { notifications.push({ title, ...options }); } },
        clients: { matchAll: matchWindows, openWindow: async (url) => { opened.push(url); } },
        caches: { open: async () => {
            if (failDiagnosticStorage) throw new Error('Storage unavailable');
            return { put: async (url, response) => {
                const reports = new URL(url).pathname === '/__mytodo_push_click_debug_entry__' ? entries : diagnostics;
                reports.push(await response.json());
            } };
        } },
        skipWaiting: async () => {}
    };
    vm.runInNewContext(source, { self, URL, Response, console: { info: (...args) => logs.push(args) } });
    return { handlers, notifications, opened, diagnostics, entries, logs };
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
        navigate: async (url) => { calls.push(['navigate', url]); return client; },
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
    assert.equal(ordinary.entries.length, 0);
    assert.equal(ordinary.logs.length, 0);
});

test('test click records its entry before window lookup finishes without delaying navigation', async () => {
    let releaseWindows;
    const windowLookup = new Promise((resolve) => { releaseWindows = resolve; });
    const worker = createWorker({ matchWindows: () => windowLookup });
    const completion = clickTestNotification(worker);
    await new Promise((resolve) => setImmediate(resolve));
    try {
        assert.deepEqual(worker.entries, [{
            event: 'push_test_click', entryPoint: 'notificationclick', traceId: '0123456789abcdef',
            stages: ['click-received'], errorName: null
        }]);
        assert.equal(worker.diagnostics.length, 0);
        assert.deepEqual(JSON.parse(worker.logs[0][1]), worker.entries[0]);
    } finally {
        releaseWindows([]);
        await completion;
    }
    assert.equal(worker.diagnostics.at(-1)?.stages.at(-1), 'window-opened');
    assert.deepEqual(worker.entries[0].stages, ['click-received']);
});

test('test close handler is registered during evaluation and records only safe entry metadata', async () => {
    const worker = createWorker();
    assert.equal(typeof worker.handlers.notificationclose, 'function');
    let pending;
    worker.handlers.notificationclose({
        notification: {
            tag: 'mytodo-push-test-0123456789abcdef',
            data: { url: 'https://mytodo.php/notifications?private=value' },
            body: 'Sensitive message'
        },
        waitUntil: (promise) => { pending = promise; }
    });
    await pending;
    assert.deepEqual(worker.entries, [{
        event: 'push_test_close', entryPoint: 'notificationclose', traceId: '0123456789abcdef',
        stages: ['close-received'], errorName: null
    }]);
    assert.deepEqual(JSON.parse(worker.logs[0][1]), worker.entries[0]);
    assert.deepEqual(worker.opened, []);
    assert.equal(worker.diagnostics.length, 0);
    assert.ok(!JSON.stringify(worker.entries).includes('Sensitive'));
    assert.ok(!JSON.stringify(worker.logs).includes('private'));
});

test('ordinary close events do not log or extend work and test close tolerates unavailable storage', async () => {
    const ordinary = createWorker();
    ordinary.handlers.notificationclose({
        notification: { tag: 'mytodo-reminder-7' },
        waitUntil: () => assert.fail('Ordinary close events must not start diagnostic work')
    });
    assert.equal(ordinary.entries.length, 0);
    assert.equal(ordinary.logs.length, 0);

    const unavailable = createWorker({ failDiagnosticStorage: true });
    let pending;
    unavailable.handlers.notificationclose({
        notification: { tag: 'mytodo-push-test-0123456789abcdef' },
        waitUntil: (promise) => { pending = promise; }
    });
    await pending;
    assert.equal(unavailable.logs.length, 1);
});

test('click focuses the client returned by navigation instead of the stale original client', async () => {
    const calls = [];
    const navigatedClient = { focus: async () => { calls.push('focused-current-client'); } };
    const originalClient = {
        url: 'https://mytodo.php/account-settings',
        navigate: async (url) => { calls.push(url); return navigatedClient; },
        focus: async () => { throw new DOMException('The client was not found.', 'NotFoundError'); }
    };
    const worker = createWorker({ windows: [originalClient] });
    await clickTestNotification(worker);
    assert.deepEqual(calls, ['https://mytodo.php/notifications', 'focused-current-client']);
    assert.deepEqual(worker.opened, []);
    assert.equal(worker.diagnostics.at(-1).errorName, null);
    assert.equal(worker.diagnostics.at(-1).stages.at(-1), 'focus-complete');
});

test('navigation returning null never focuses the stale client or opens a duplicate tab', async () => {
    const worker = createWorker({ windows: [{
        url: 'https://mytodo.php/account-settings',
        navigate: async () => null,
        focus: async () => assert.fail('A navigation with no returned client must not focus the old client')
    }] });
    await clickTestNotification(worker);
    assert.deepEqual(worker.opened, []);
    assert.equal(worker.diagnostics.at(-1).errorName, null);
    assert.equal(worker.diagnostics.at(-1).stages.at(-1), 'focus-skipped-no-client');
});

test('a missing navigated client preserves NotFoundError without logging its message', async () => {
    const failure = new DOMException('Sensitive client details', 'NotFoundError');
    const navigatedClient = { focus: async () => { throw failure; } };
    const worker = createWorker({ windows: [{
        url: 'https://mytodo.php/account-settings',
        navigate: async () => navigatedClient,
        focus: async () => assert.fail('Only the navigated client may be focused')
    }] });
    await assert.rejects(clickTestNotification(worker), (error) => error === failure);
    assert.equal(worker.diagnostics.at(-1).errorName, 'NotFoundError');
    assert.equal(worker.diagnostics.at(-1).stages.at(-1), 'focusing');
    assert.ok(!JSON.stringify(worker.diagnostics).includes('Sensitive'));
    assert.deepEqual(worker.opened, []);
});
