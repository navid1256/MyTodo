import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { initNotificationArchive } = await import('../../public/assets/js/modules/notification-archive.js');
const { applyTranslations } = await import('../../public/assets/js/utils/i18n.js');

/** Builds only the archive DOM contracts, without a browser or a configured database. */
function setup() {
    const rows = [{ dataset: { sentNotification: '1' } }];
    const title = { focused: false, focus() { this.focused = true; } };
    const message = { hidden: true, classList: { toggle() {} } };
    const list = {
        rows, setAttribute() {},
        querySelector() { return this.rows.find(row => row.dataset.sentNotification); },
        append(...items) { this.rows.push(...items); },
        replaceChildren(...items) { this.rows = items; },
    };
    const clear = new EventTarget();
    clear.querySelector = () => (clear.label ||= {});
    const more = new EventTarget();
    const fields = {};
    const clonedRow = { dataset: {}, querySelector(selector) { return fields[selector] ||= {}; }, focus() { this.focused = true; } };
    const template = { content: { firstElementChild: { cloneNode: () => clonedRow } } };
    const elements = {
        '[data-sent-notifications]': list, '[data-clear-notifications]': clear,
        '[data-load-notifications]': more, '[data-archive-message]': message,
        '[data-sent-notification-template]': template, h1: title,
    };
    const page = { dataset: { nextCursor: '2026-10-29 08:00:00|5' }, querySelector: s => elements[s] };
    globalThis.document = {
        getElementById: () => null,
        querySelector: s => ({ '[data-notification-archive]': page, 'meta[name="csrf-token"]': { content: 'archive-csrf' } })[s] || null,
        querySelectorAll: () => [],
        createElement: () => ({ dataset: {} }),
    };
    globalThis.window = { confirm: () => true };
    const controller = new AbortController();
    const requests = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return json({ success: true, unread_count: 0 });
    };
    return { controller, requests, page, list, clear, more, message, title, clonedRow, fields };
}

/** Returns a fetch-compatible JSON response for both shared API clients. */
function json(data, status = 200) {
    return { ok: status < 400, status, json: async () => data, text: async () => JSON.stringify(data) };
}

/** Waits for asynchronous event handlers without timing-dependent sleeps. */
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
}

test('opening the archive posts read state with CSRF before enabling actions', async () => {
    const page = setup();
    initNotificationArchive(page.controller.signal);
    assert.equal(page.clear.disabled, true);
    await settle();
    assert.equal(page.requests[0].url, '/api/notifications/read');
    assert.equal(page.requests[0].options.body.get('csrf_token'), 'archive-csrf');
    assert.equal(page.clear.disabled, false);
    page.controller.abort();
});

test('clear waits for server success, suppresses double clicks, then renders the empty state', async () => {
    const page = setup();
    initNotificationArchive(page.controller.signal);
    await settle();
    let finish;
    globalThis.fetch = (url, options) => {
        page.requests.push({ url, options });
        return new Promise(resolve => { finish = resolve; });
    };
    page.clear.dispatchEvent(new Event('click'));
    page.clear.dispatchEvent(new Event('click'));
    assert.equal(page.list.rows[0].dataset.sentNotification, '1');
    assert.equal(page.requests.length, 2);
    assert.equal(page.requests[1].url, '/api/notifications/clear');
    finish(json({ success: true, cleared_count: 23, unread_count: 0 }));
    await settle();
    assert.equal(page.list.rows[0].dataset.i18n, 'notifications.sent_empty');
    assert.equal(page.page.dataset.nextCursor, '');
    assert.equal(page.more.hidden, true);
    assert.equal(page.clear.disabled, true);
    assert.equal(page.title.focused, true);
    page.controller.abort();
});

test('cancelled confirmation and failed clear preserve the archive and permit retry', async () => {
    const page = setup();
    initNotificationArchive(page.controller.signal);
    await settle();
    window.confirm = () => false;
    page.clear.dispatchEvent(new Event('click'));
    assert.equal(page.requests.length, 1);
    window.confirm = () => true;
    globalThis.fetch = async () => json({ success: false }, 500);
    page.clear.dispatchEvent(new Event('click'));
    await settle();
    assert.equal(page.list.rows[0].dataset.sentNotification, '1');
    assert.equal(page.clear.disabled, false);
    assert.equal(page.message.hidden, false);
    page.controller.abort();
});

test('load more uses the cursor once, assigns untrusted titles as text, and focuses the first new row', async () => {
    const page = setup();
    initNotificationArchive(page.controller.signal);
    await settle();
    globalThis.fetch = async (url, options) => {
        page.requests.push({ url, options });
        return json({ success: true, items: [{ id: 2, title: '<img onerror=alert(1)>', sent_at: 'date', remind_at: 'date', due_at: 'date' }], next_cursor: null });
    };
    page.more.dispatchEvent(new Event('click'));
    page.more.dispatchEvent(new Event('click'));
    await settle();
    assert.equal(page.requests.length, 2);
    assert.match(page.requests[1].url, /sent\?cursor=2026-10-29%2008%3A00%3A00%7C5$/);
    assert.equal(page.fields['[data-notification-title]'].textContent, '<img onerror=alert(1)>');
    assert.equal(page.list.rows.length, 2);
    assert.equal(page.more.hidden, true);
    assert.equal(page.clonedRow.focused, true);
    page.controller.abort();
});

test('aborted navigation neither sends a read request nor applies a late clear result', async () => {
    const page = setup();
    page.controller.abort();
    initNotificationArchive(page.controller.signal);
    assert.equal(page.requests.length, 0);
    const live = setup();
    initNotificationArchive(live.controller.signal);
    await settle();
    let finish;
    globalThis.fetch = () => new Promise(resolve => { finish = resolve; });
    live.clear.dispatchEvent(new Event('click'));
    live.controller.abort();
    finish(json({ success: true, unread_count: 0 }));
    await settle();
    assert.equal(live.list.rows[0].dataset.sentNotification, '1');
});

test('Persian clear confirmation and completion use the shared translation catalog', async () => {
    const page = setup();
    applyTranslations({ 'notifications.clear_confirm': 'اعلان‌ها پاک شوند؟', 'notifications.cleared': 'اعلان‌ها پاک شدند.' });
    let confirmation;
    window.confirm = text => { confirmation = text; return true; };
    initNotificationArchive(page.controller.signal);
    await settle();
    page.clear.dispatchEvent(new Event('click'));
    await settle();
    assert.equal(confirmation, 'اعلان‌ها پاک شوند؟');
    assert.equal(page.message.textContent, 'اعلان‌ها پاک شدند.');
    page.controller.abort();
});
