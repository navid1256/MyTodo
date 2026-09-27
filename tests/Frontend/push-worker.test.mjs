import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

function createWorker() {
    const handlers = {};
    const notifications = [];
    const opened = [];
    const self = {
        location: { origin: 'https://mytodo.php' },
        addEventListener: (event, handler) => { handlers[event] = handler; },
        registration: { showNotification: async (title, options) => { notifications.push({ title, ...options }); } },
        clients: { matchAll: async () => [], openWindow: async (url) => { opened.push(url); } },
        skipWaiting: async () => {}
    };
    vm.runInNewContext(source, { self, URL });
    return { handlers, notifications, opened };
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
