import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { initActivityClear } = await import('../../public/assets/js/modules/activity-clear.js');
const { initActivityFilter } = await import('../../public/assets/js/modules/activity-filter.js');
const { applyTranslations } = await import('../../public/assets/js/utils/i18n.js');

/** Builds only the DOM contracts used by Activity clearing and its existing date filter. */
function setup() {
    const button = new EventTarget();
    button.disabled = false;
    button.label = {};
    button.querySelector = () => button.label;
    button.setAttribute = () => {};
    button.removeAttribute = () => {};
    button.focus = () => {};
    const dialog = new EventTarget();
    dialog.open = false;
    dialog.returnValue = '';
    dialog.showModal = () => { dialog.open = true; };
    const message = { hidden: true, classList: { toggle() {} } };
    const empty = { hidden: true };
    const filter = new EventTarget();
    filter.value = 'today';
    filter.focus = () => { filter.focused = true; };
    const page = { isConnected: true };
    let groups = [{ dataset: { completedDate: '2026-10-29' }, hidden: false }, { dataset: { completedDate: '2020-01-01' }, hidden: true }];
    groups.forEach(group => { group.remove = () => { groups = groups.filter(item => item !== group); }; });
    const counter = { textContent: '2' };
    const counterButton = { setAttribute(name, value) { this[name] = value; } };
    page.querySelectorAll = () => groups;
    page.querySelector = selector => ({
        '[data-clear-completed-tasks]': button, '[data-clear-completed-dialog]': dialog,
        '[data-activity-clear-message]': message, '#activityFilterEmpty': empty, '#activityFilter': filter,
        '.activityTaskItem': groups.length ? {} : null,
    })[selector] || null;
    globalThis.document = {
        body: { dataset: { renderDate: '2026-10-29' } },
        getElementById: id => ({ activityFilter: filter, activityFilterEmpty: empty })[id] || null,
        querySelector: selector => ({
            '.activityPage': page, 'meta[name="csrf-token"]': { content: 'clear-test' },
            '.completedCount': counter, '.completedButton': counterButton,
        })[selector] || null,
        querySelectorAll: selector => selector === '[data-completed-date]' ? groups : [],
    };
    globalThis.window = { location: { href: 'https://mytodo.php/activity?filter=today' }, history: { pushState() {} } };
    const requests = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return response({ success: true, deleted_count: 2, completed_today_count: 0, unread_count: 0 });
    };
    const controller = new AbortController();
    /** Completes the same native dialog close event fired by confirm, cancel, or Escape. */
    function close(value) {
        dialog.returnValue = value;
        dialog.open = false;
        dialog.dispatchEvent(new Event('close'));
    }
    return { page, button, dialog, message, empty, filter, counter, counterButton, requests, controller, close, groups: () => groups };
}

/** Returns a fetch-compatible JSON response without contacting the application server. */
function response(data, status = 200) {
    return { ok: status < 400, status, text: async () => JSON.stringify(data) };
}

/** Waits for asynchronous event handlers without timing-dependent sleeps. */
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
}

test('opening and cancelling the confirmation never sends a deletion request', async () => {
    const page = setup();
    initActivityClear(page.controller.signal);
    page.button.dispatchEvent(new Event('click'));
    assert.equal(page.dialog.open, true);
    assert.equal(page.requests.length, 0);
    page.close('cancel');
    page.close('');
    await settle();
    assert.equal(page.requests.length, 0);
    assert.equal(page.groups().length, 2);
    page.controller.abort();
});

test('clear posts only CSRF, waits for success, suppresses duplicate confirmation, and removes all date groups', async () => {
    const page = setup();
    initActivityClear(page.controller.signal);
    let finish;
    globalThis.fetch = (url, options) => {
        page.requests.push({ url, options });
        return new Promise(resolve => { finish = resolve; });
    };
    page.close('clear');
    page.close('clear');
    assert.equal(page.requests.length, 1);
    assert.equal(page.requests[0].url, '/api/tasks/clear-completed');
    assert.equal(page.requests[0].options.body.get('csrf_token'), 'clear-test');
    assert.equal(page.requests[0].options.body.has('filter'), false);
    assert.equal(page.groups().length, 2);
    assert.equal(page.button.disabled, true);
    finish(response({ success: true, deleted_count: 2, completed_today_count: 0, unread_count: 0 }));
    await settle();
    assert.equal(page.groups().length, 0);
    assert.equal(page.empty.hidden, false);
    assert.equal(page.counter.textContent, '0');
    assert.equal(page.button.disabled, true);
    assert.equal(page.filter.focused, true);
    page.controller.abort();
});

test('failed requests preserve history and re-enable clearing for a deliberate retry', async () => {
    const page = setup();
    initActivityClear(page.controller.signal);
    globalThis.fetch = async () => response({ success: false }, 500);
    page.close('clear');
    await settle();
    assert.equal(page.groups().length, 2);
    assert.equal(page.button.disabled, false);
    assert.equal(page.message.hidden, false);
    assert.equal(page.dialog.returnValue, '');
    page.controller.abort();
});

test('navigation abort discards late UI results and an already-aborted view cannot send requests', async () => {
    const page = setup();
    initActivityClear(page.controller.signal);
    let finish;
    globalThis.fetch = () => new Promise(resolve => { finish = resolve; });
    page.close('clear');
    page.controller.abort();
    finish(response({ success: true, deleted_count: 2, completed_today_count: 0, unread_count: 0 }));
    await settle();
    assert.equal(page.groups().length, 2);
    const oldPage = setup();
    oldPage.controller.abort();
    initActivityClear(oldPage.controller.signal);
    oldPage.close('clear');
    assert.equal(oldPage.requests.length, 0);
});

test('changing the filter after clear cannot resurrect removed date groups or hide the empty state', async () => {
    const page = setup();
    initActivityFilter(page.controller.signal);
    initActivityClear(page.controller.signal);
    page.close('clear');
    await settle();
    page.filter.value = 'all';
    page.filter.dispatchEvent(new Event('change'));
    assert.equal(page.empty.hidden, false);
    assert.equal(page.groups().length, 0);
    page.controller.abort();
});

test('Persian result text uses the shared translation catalog', async () => {
    const page = setup();
    applyTranslations({ 'activity.cleared': '{count} کار حذف شد.' });
    initActivityClear(page.controller.signal);
    page.close('clear');
    await settle();
    assert.equal(page.message.textContent, '2 کار حذف شد.');
    page.controller.abort();
});

test('missing CSRF preserves completed tasks and never sends a deletion request', async () => {
    const page = setup();
    const querySelector = document.querySelector;
    document.querySelector = selector => selector === 'meta[name="csrf-token"]' ? null : querySelector(selector);
    initActivityClear(page.controller.signal);
    page.close('clear');
    await settle();
    assert.equal(page.requests.length, 0);
    assert.equal(page.groups().length, 2);
    assert.equal(page.message.hidden, false);
    assert.equal(page.button.disabled, false);
    page.controller.abort();
});
