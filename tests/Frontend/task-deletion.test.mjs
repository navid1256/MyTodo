import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { deleteTask } = await import('../../public/assets/js/services/task-service.js');
delete globalThis.document;

/** Returns a fetch-compatible JSON response without making any network requests. */
function response(data, status = 200) {
    return { ok: status < 400, status, text: async () => JSON.stringify(data) };
}

/** Builds only the DOM contracts involved in deletion, with an abortable delegated listener. */
function setupPage(view = 'home') {
    const events = [];
    const alerts = [];
    const confirmations = [];
    const document = new EventTarget();
    const count = { textContent: '2' };
    const counterButton = { setAttribute(name, value) { this[name] = value; } };
    const list = {
        dataset: view === 'home' ? { emptyMessage: 'home.empty_today' } : {},
        children: [],
        querySelector(selector) { return selector === '.taskItem' && row.isConnected ? row : null; },
        appendChild(item) { this.children.push(item); },
    };
    const row = {
        isConnected: true, parentElement: list,
        remove() { this.isConnected = false; },
    };
    const button = {
        dataset: { taskId: '42', title: 'Sample task' }, disabled: false,
        closest(selector) { return selector === '.taskItem' ? row : this; },
        setAttribute(name, value) { this[name] = value; },
        removeAttribute(name) { delete this[name]; },
    };
    document.body = { dataset: { activeView: view } };
    document.getElementById = () => null;
    document.querySelector = (selector) => ({
        'meta[name="csrf-token"]': { content: 'csrf-test' },
        '.completedCount': count, '.completedButton': counterButton,
    })[selector] || null;
    document.querySelectorAll = () => [];
    document.contains = (element) => element.isConnected;
    document.createElement = () => ({ dataset: {}, setAttribute(name, value) { this[name] = value; } });
    document.addEventListener('task:removed-from-manage', (event) => events.push(event.detail));
    globalThis.document = document;
    globalThis.window = {
        confirm(message) { confirmations.push(message); return true; },
        alert(message) { alerts.push(message); },
    };
    const controller = new AbortController();
    /** Sends a delegated click, including clicks on the button's nested icon. */
    function click() {
        const event = new Event('click', { cancelable: true });
        Object.defineProperty(event, 'target', { value: { closest: () => button } });
        document.dispatchEvent(event);
    }
    return { document, button, row, list, count, events, alerts, confirmations, controller, click };
}

/** Lets the handler's asynchronous response and cleanup finish deterministically. */
async function settle() {
    await new Promise((resolve) => setImmediate(resolve));
}

test('delete service posts task id and CSRF to the existing JSON endpoint', async () => {
    const requests = [];
    globalThis.fetch = async (url, options) => { requests.push({ url, options }); return response({ success: true }); };
    await deleteTask(42, 'csrf-test');
    assert.equal(requests[0].url, '/api/tasks/delete');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.headers.Accept, 'application/json');
    assert.equal(requests[0].options.body.get('task_id'), '42');
    assert.equal(requests[0].options.body.get('csrf_token'), 'csrf-test');
});

test('duplicate clicks send once and the row stays until server success', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const page = setupPage('manage-tasks');
    let finish;
    let requests = 0;
    globalThis.fetch = () => { requests++; return new Promise((resolve) => { finish = resolve; }); };
    initTaskDeletion(page.controller.signal);
    page.click();
    page.click();
    assert.equal(requests, 1);
    assert.equal(page.button.disabled, true);
    assert.equal(page.row.isConnected, true);
    finish(response({ success: true, deleted_task_id: 42, completed_today_count: 1 }));
    await settle();
    assert.equal(page.row.isConnected, false);
    assert.equal(page.count.textContent, '1');
    assert.deepEqual(page.events, [{ taskId: 42 }]);
    assert.equal(page.alerts.length, 0);
    page.controller.abort();
});

test('confirmation cancellation never sends a request or removes the row', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const page = setupPage();
    globalThis.window.confirm = () => false;
    globalThis.fetch = () => { assert.fail('Cancelled deletion must not send a request.'); };
    initTaskDeletion(page.controller.signal);
    page.click();
    await settle();
    assert.equal(page.row.isConnected, true);
    assert.equal(page.button.disabled, false);
    page.controller.abort();
});

test('HTTP and network failures keep the task and restore the control for retry', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    for (const status of [403, 404, 500, 'network']) {
        const page = setupPage();
        globalThis.fetch = async () => {
            if (status === 'network') throw new Error('Offline');
            return response({ success: false, message: 'Delete denied' }, status);
        };
        initTaskDeletion(page.controller.signal);
        page.click();
        await settle();
        assert.equal(page.row.isConnected, true);
        assert.equal(page.button.disabled, false);
        assert.equal(page.alerts.length, 1);
        assert.equal(page.count.textContent, '2');
        page.controller.abort();
    }
});

test('deleting the last Home task renders its translated empty-list state', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const page = setupPage();
    globalThis.fetch = async () => response({ success: true, deleted_task_id: 42, completed_today_count: 2 });
    initTaskDeletion(page.controller.signal);
    page.click();
    await settle();
    assert.equal(page.list.children.length, 1);
    assert.equal(page.list.children[0].dataset.i18n, 'home.empty_today');
    page.controller.abort();
});

test('an aborted view cannot send new deletions or apply a stale result to the next view', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const page = setupPage();
    let finish;
    globalThis.fetch = () => new Promise((resolve) => { finish = resolve; });
    initTaskDeletion(page.controller.signal);
    page.click();
    page.controller.abort();
    finish(response({ success: true, deleted_task_id: 42, completed_today_count: 1 }));
    await settle();
    assert.equal(page.row.isConnected, true);
    assert.equal(page.count.textContent, '2');
    assert.equal(page.events.length, 0);
    assert.equal(page.alerts.length, 0);
});

test('missing CSRF and an already-aborted initializer never send deletions', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const page = setupPage();
    const originalQuery = page.document.querySelector;
    page.document.querySelector = (selector) => selector.includes('csrf-token') ? null : originalQuery(selector);
    globalThis.fetch = () => assert.fail('Missing CSRF must not issue a deletion.');
    initTaskDeletion(page.controller.signal);
    page.click();
    await settle();
    assert.equal(page.alerts.length, 1);
    assert.equal(page.row.isConnected, true);
    page.controller.abort();

    const nextPage = setupPage();
    nextPage.controller.abort();
    initTaskDeletion(nextPage.controller.signal);
    nextPage.click();
    assert.equal(nextPage.confirmations.length, 0);
});

test('Persian confirmation and empty-state messages use the shared translation catalog', async () => {
    const { initTaskDeletion } = await import('../../public/assets/js/modules/task-deletion.js');
    const { applyTranslations } = await import('../../public/assets/js/utils/i18n.js');
    const page = setupPage();
    applyTranslations({
        'task.delete_confirmation': 'آیا از حذف این کار مطمئن هستید؟\n{title}',
        'home.empty_today': 'هیچ کاری برای امروز وجود ندارد.',
    });
    globalThis.fetch = async () => response({ success: true, deleted_task_id: 42, completed_today_count: 2 });
    initTaskDeletion(page.controller.signal);
    page.click();
    await settle();
    assert.equal(page.confirmations[0], 'آیا از حذف این کار مطمئن هستید؟\nSample task');
    assert.equal(page.list.children[0].textContent, 'هیچ کاری برای امروز وجود ندارد.');
    page.controller.abort();
});
