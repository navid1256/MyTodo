import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { initMessageNotificationsClear } = await import('../../public/assets/js/modules/message-notifications-clear.js');
const { applyTranslations } = await import('../../public/assets/js/utils/i18n.js');
const { initNotificationCenter } = await import('../../public/assets/js/modules/notification-center.js');

/** Creates a minimal Messages DOM with mixed reminder states and an opaque test CSRF value. */
function setup() {
    const button = new EventTarget();
    button.label = {};
    button.querySelector = () => button.label;
    button.setAttribute = button.removeAttribute = () => {};
    const dialog = new EventTarget();
    dialog.returnValue = '';
    dialog.open = false;
    dialog.showModal = () => { dialog.open = true; };
    const message = { hidden: true, classList: { toggle() {} } };
    const title = { focus() { this.focused = true; } };
    const page = new EventTarget();
    page.isConnected = true;
    let rows = ['pending', 'failed', 'sent', 'cancelled'].map((state, index) => ({
        dataset: { notificationId: String(index + 1), notificationState: state },
        remove() { rows = rows.filter(row => row !== this); },
    }));
    const list = { append(item) { this.empty = item; } };
    const controls = { '[data-clear-message-notifications]': button, '[data-messages-clear-dialog]': dialog,
        '#notificationsPageMessage': message, '#notificationsList': list, '#notificationsPageTitle': title };
    page.querySelector = selector => controls[selector] || null;
    page.querySelectorAll = () => rows;
    globalThis.document = {
        getElementById: () => null,
        querySelector: selector => selector === '.notificationsContent' ? page : { content: 'messages-test' },
        querySelectorAll: () => [],
        createElement: () => ({ dataset: {} }),
    };
    const requests = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return response({ success: true, cleared_count: 2, remaining_ids: [1, 2] });
    };
    const controller = new AbortController();
    return { button, dialog, message, title, page, list, requests, controller, rows: () => rows,
        close(value) { dialog.returnValue = value; dialog.open = false; dialog.dispatchEvent(new Event('close')); } };
}

/** Supplies the existing JSON form client's real response shape. */
function response(data, status = 200) {
    return { ok: status === 200, status, text: async () => JSON.stringify(data) };
}

/** Waits for pending promise continuations without depending on wall-clock timing. */
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
}

test('opening, cancelling, or dismissing confirmation never clears Messages', async () => {
    const view = setup();
    initMessageNotificationsClear(view.controller.signal);
    view.button.dispatchEvent(new Event('click'));
    assert.equal(view.dialog.open, true);
    view.close('cancel');
    view.close('');
    await settle();
    assert.equal(view.requests.length, 0);
    assert.equal(view.rows().length, 4);
    view.controller.abort();
});

test('clear waits for success, posts only CSRF, and keeps authoritative pending/failed rows', async () => {
    const view = setup();
    initMessageNotificationsClear(view.controller.signal);
    let finish;
    globalThis.fetch = (url, options) => {
        view.requests.push({ url, options });
        return new Promise(resolve => { finish = resolve; });
    };
    view.close('clear');
    view.close('clear');
    assert.equal(view.requests.length, 1);
    assert.equal(view.requests[0].url, '/api/notifications/clear-messages');
    assert.deepEqual([...view.requests[0].options.body.keys()], ['csrf_token']);
    assert.equal(view.rows().length, 4);
    assert.equal(view.button.disabled, true);
    finish(response({ success: true, cleared_count: 2, remaining_ids: [1, 2] }));
    await settle();
    assert.deepEqual(view.rows().map(row => row.dataset.notificationState), ['pending', 'failed']);
    assert.equal(view.button.disabled, true);
    assert.equal(view.title.focused, true);
    view.rows()[0].dataset.notificationState = 'cancelled';
    view.page.dispatchEvent(new Event('notification:status-changed'));
    assert.equal(view.button.disabled, false);
    view.controller.abort();
});

test('HTTP errors keep the list and allow a deliberate retry; missing CSRF never posts', async () => {
    const view = setup();
    initMessageNotificationsClear(view.controller.signal);
    globalThis.fetch = async () => response({ success: false }, 500);
    view.close('clear');
    await settle();
    assert.equal(view.rows().length, 4);
    assert.equal(view.button.disabled, false);
    assert.equal(view.message.hidden, false);
    assert.equal(view.dialog.returnValue, '');
    document.querySelector = selector => selector === '.notificationsContent' ? view.page : null;
    view.close('clear');
    await settle();
    assert.equal(view.requests.length, 0);
    view.controller.abort();
});

test('navigation discards a late result and an already-aborted initializer cannot register requests', async () => {
    const view = setup();
    initMessageNotificationsClear(view.controller.signal);
    let finish;
    globalThis.fetch = () => new Promise(resolve => { finish = resolve; });
    view.close('clear');
    view.controller.abort();
    finish(response({ success: true, remaining_ids: [], cleared_count: 4 }));
    await settle();
    assert.equal(view.rows().length, 4);
    const abandoned = setup();
    abandoned.controller.abort();
    initMessageNotificationsClear(abandoned.controller.signal);
    abandoned.close('clear');
    assert.equal(abandoned.requests.length, 0);
});

test('clearing the last terminal rows renders a localized empty state and keeps clearing disabled', async () => {
    const view = setup();
    applyTranslations({ 'notifications.empty': 'هیچ اعلانی پیدا نشد.', 'notifications.messages_cleared': '{count} اعلان پاک شد.' });
    initMessageNotificationsClear(view.controller.signal);
    globalThis.fetch = async () => response({ success: true, cleared_count: 2, remaining_ids: [] });
    view.close('clear');
    await settle();
    assert.equal(view.rows().length, 0);
    assert.equal(view.list.empty.textContent, 'هیچ اعلانی پیدا نشد.');
    assert.equal(view.message.textContent, '2 اعلان پاک شد.');
    assert.equal(view.button.disabled, true);
    view.controller.abort();
});

test('the existing success-only Cancel response enables Clear without reloading or requiring a notification payload', async () => {
    const view = setup();
    view.rows().filter(row => ['sent', 'cancelled'].includes(row.dataset.notificationState)).forEach(row => row.remove());
    const row = view.rows()[0];
    const cancel = { disabled: false, closest: () => row };
    const edit = {};
    const status = { classList: { remove() {}, add() {} } };
    row.querySelector = selector => ({ '[data-notification-status]': status, '[data-notification-edit]': edit, '[data-notification-cancel]': cancel })[selector];
    row.dispatchEvent = event => view.page.dispatchEvent(event);
    const form = new EventTarget();
    form.querySelector = () => ({ value: 'messages-test' });
    const fields = Object.fromEntries(['notificationEditModal', 'notificationEditId', 'notificationOffsetValue', 'notificationOffsetUnit', 'notificationEditTask', 'notificationEditDue', 'notificationEditMessage', 'closeNotificationEdit', 'dismissNotificationEdit', 'saveNotificationEdit'].map(id => [id, new EventTarget()]));
    fields.notificationEditForm = form;
    fields.notificationsPageMessage = view.message;
    document.getElementById = id => fields[id];
    globalThis.window = { confirm: () => true };
    globalThis.fetch = async () => response({ success: true });
    initNotificationCenter(view.controller.signal);
    initMessageNotificationsClear(view.controller.signal);
    assert.equal(view.button.disabled, true);
    const click = new Event('click');
    Object.defineProperty(click, 'target', { value: { closest: selector => selector === '[data-notification-cancel]' ? cancel : null } });
    view.page.dispatchEvent(click);
    await settle();
    assert.equal(row.dataset.notificationState, 'cancelled');
    assert.equal(view.button.disabled, false);
    assert.equal(edit.disabled, true);
    assert.equal(cancel.disabled, true);
    assert.equal(view.message.hidden, false);
    view.controller.abort();
});
