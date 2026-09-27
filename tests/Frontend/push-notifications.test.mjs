import test from 'node:test';
import assert from 'node:assert/strict';

// Test only public VAPID conversion and feature/permission states, never browser secrets.
globalThis.document = { getElementById: () => null };
const { decodeApplicationServerKey, getPushBrowserState, initPushNotifications } = await import('../../public/assets/js/modules/push-notifications.js');

test('VAPID Base64URL is converted to bytes, including URL-safe characters', () => {
    assert.deepEqual([...decodeApplicationServerKey('AQI-_w')], [1, 2, 62, 255]);
});

test('missing support and insecure context cannot prompt for permission', () => {
    assert.equal(getPushBrowserState({ isSecureContext: false }), 'unsupported');
    assert.equal(getPushBrowserState({ isSecureContext: true, navigator: {} }), 'unsupported');
});

test('supported browsers distinguish default, denied and granted permissions', () => {
    for (const permission of ['default', 'denied', 'granted']) {
        const browser = { isSecureContext: true, navigator: { serviceWorker: {} }, PushManager: {}, Notification: { permission } };
        assert.equal(getPushBrowserState(browser), permission);
    }
});

function fakeControl() {
    return {
        hidden: false, disabled: true, dataset: {}, textContent: '', listeners: {},
        classList: { toggle: () => {} },
        addEventListener(event, callback, { signal }) { if (!signal.aborted) this.listeners[event] = callback; }
    };
}

function setupPushPage(permission = 'default', existingSubscription = null) {
    const enable = fakeControl();
    const disable = fakeControl();
    const status = fakeControl();
    const panel = { querySelector: (selector) => ({ '[data-push-enable]': enable, '[data-push-disable]': disable, '[data-push-status]': status })[selector], setAttribute: () => {} };
    globalThis.document = { getElementById: (id) => id === 'pushSettings' ? panel : null, querySelector: () => ({ value: 'test-csrf' }) };
    let prompts = 0;
    const calls = [];
    const notification = { permission, requestPermission: () => { prompts++; notification.permission = 'granted'; return Promise.resolve('granted'); } };
    const registration = { pushManager: { getSubscription: async () => existingSubscription, subscribe: async () => ({ endpoint: 'https://fcm.googleapis.com/test', toJSON: () => ({ endpoint: 'test' }), unsubscribe: async () => true }) } };
    const serviceWorker = { getRegistration: async () => existingSubscription ? registration : null, register: async () => registration, ready: Promise.resolve(registration) };
    Object.defineProperty(globalThis, 'navigator', { value: { serviceWorker }, configurable: true });
    globalThis.Notification = notification;
    globalThis.window = { isSecureContext: true, navigator: globalThis.navigator, Notification: notification, PushManager: {} };
    globalThis.fetch = async (url, options) => {
        calls.push({ url, csrf: options.body.get('csrf_token') });
        return { ok: true, text: async () => JSON.stringify({ success: true, configured: true, subscribed: false, publicKey: 'AQI' }) };
    };
    return { enable, disable, status, calls, get prompts() { return prompts; } };
}

test('page load does not request permission; explicit enable sends a CSRF-protected registration', async () => {
    const page = setupPushPage();
    initPushNotifications(new AbortController().signal);
    await new Promise(setImmediate);
    assert.equal(page.prompts, 0);
    assert.equal(page.enable.disabled, false);
    await page.enable.listeners.click();
    assert.equal(page.prompts, 1);
    assert.equal(page.calls.at(-1).url, '/api/push/subscribe');
    assert.equal(page.calls.at(-1).csrf, 'test-csrf');
    assert.equal(page.enable.hidden, true);
    assert.equal(page.disable.hidden, false);
});

test('denied permission disables Enable and does not prompt again', async () => {
    const page = setupPushPage('denied');
    initPushNotifications(new AbortController().signal);
    await new Promise(setImmediate);
    assert.equal(page.enable.disabled, true);
    assert.equal(page.status.dataset.i18n, 'push.denied');
    assert.equal(page.prompts, 0);
});

test('disable deletes only this subscription on server before revoking browser subscription', async () => {
    let serverRemoved = false;
    const page = setupPushPage('granted', { endpoint: 'https://fcm.googleapis.com/test', unsubscribe: async () => { assert.ok(serverRemoved); return true; } });
    initPushNotifications(new AbortController().signal);
    await new Promise(setImmediate);
    globalThis.fetch = async (url) => { assert.equal(url, '/api/push/unsubscribe'); serverRemoved = true; return { ok: true, text: async () => '{"success":true}' }; };
    await page.disable.listeners.click();
    assert.equal(page.disable.hidden, true);
    assert.equal(page.status.dataset.i18n, 'push.disabled');
});

test('aborted dashboard navigation does not attach new handlers or send requests', async () => {
    const page = setupPushPage();
    const controller = new AbortController();
    controller.abort();
    initPushNotifications(controller.signal);
    assert.equal(page.calls.length, 0);
    assert.deepEqual(page.enable.listeners, {});
});
