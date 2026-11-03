import assert from 'node:assert/strict';
import test from 'node:test';
import { installMessageDialog } from './helpers/message-dialog.mjs';

globalThis.document = { getElementById: () => null };
const { confirmAction, showAlert } = await import('../../public/assets/js/utils/message-dialog.js');
const { applyTranslations } = await import('../../public/assets/js/utils/i18n.js');

/** Creates a manually operated dialog and a shared localization catalog. */
function setup() {
    globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };
    const fixture = installMessageDialog(document);
    fixture.autoRespond = false;
    applyTranslations({ 'dialog.confirm_title': 'Confirm action', 'dialog.message_title': 'Message',
        'dialog.confirm': 'Confirm', 'dialog.ok': 'OK', 'common.cancel': 'Cancel' });
    return fixture;
}

/** Flushes dialog queue continuations without a wall-clock delay. */
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
}

test('confirmation renders untrusted text literally and only an explicit confirm resolves true', async () => {
    const view = setup();
    const pending = confirmAction('Delete?\n<img src=x onerror=alert(1)>', { destructive: true, confirmLabel: 'Delete' });
    assert.equal(view.dialog.open, true);
    assert.equal(view.message.textContent, 'Delete?\n<img src=x onerror=alert(1)>');
    assert.equal(view.cancel.hidden, false);
    assert.equal(view.cancel.autofocus, true);
    assert.equal(view.confirm.textContent, 'Delete');
    view.dialog.close('confirm');
    assert.equal(await pending, true);
    assert.equal(view.dialog.returnValue, '');
    const next = confirmAction('Again?');
    view.dialog.close('');
    assert.equal(await next, false);
});

test('alert shows one acknowledgment button instead of invoking the browser alert API', async () => {
    const view = setup();
    const pending = showAlert('Request failed.');
    assert.equal(view.cancel.hidden, true);
    assert.equal(view.confirm.autofocus, true);
    assert.equal(view.confirm.textContent, 'OK');
    assert.equal(view.title.textContent, 'Message');
    view.dialog.close('confirm');
    await pending;
});

test('simultaneous messages are displayed FIFO without overwriting the active confirmation', async () => {
    const view = setup();
    const first = confirmAction('First?');
    const second = confirmAction('Second?');
    assert.equal(view.message.textContent, 'First?');
    view.dialog.close('cancel');
    assert.equal(await first, false);
    await settle();
    assert.equal(view.message.textContent, 'Second?');
    view.dialog.close('confirm');
    assert.equal(await second, true);
});

test('navigation dismisses active and queued requests safely and no stale confirmation leaks to the next view', async () => {
    const view = setup();
    const controller = new AbortController();
    const first = confirmAction('Old first?', { signal: controller.signal });
    const second = confirmAction('Old queued?', { signal: controller.signal });
    controller.abort();
    assert.equal(await first, false);
    assert.equal(await second, false);
    await settle();
    assert.equal(view.dialog.open, false);
    const next = confirmAction('New view?');
    view.dialog.close('');
    assert.equal(await next, false);
});

test('missing markup, unsupported dialogs, and already-aborted requests never auto-approve actions', async () => {
    const view = setup();
    const controller = new AbortController();
    controller.abort();
    assert.equal(await confirmAction('Never open', { signal: controller.signal }), false);
    assert.equal(view.dialog.open, false);
    view.dialog.showModal = undefined;
    assert.equal(await confirmAction('Unsupported'), false);
    document.getElementById = () => null;
    assert.equal(await confirmAction('Missing'), false);
});

test('abort after native close waits for its pending close event before opening the next message', async () => {
    const view = setup();
    const controller = new AbortController();
    const first = confirmAction('Old view?', { signal: controller.signal });
    const second = showAlert('New view message');
    // Browsers clear open synchronously but dispatch the close event in a later task.
    view.dialog.open = false;
    view.dialog.returnValue = 'confirm';
    controller.abort();
    await settle();
    assert.equal(view.message.textContent, 'Old view?');
    view.dialog.dispatchEvent(new Event('close'));
    assert.equal(await first, false);
    await settle();
    assert.equal(view.dialog.open, true);
    assert.equal(view.message.textContent, 'New view message');
    view.dialog.close('confirm');
    await second;
});

test('each request uses the latest translated labels rather than retaining a previous language', async () => {
    const view = setup();
    applyTranslations({ 'dialog.confirm_title': 'تأیید عملیات', 'dialog.confirm': 'تأیید', 'common.cancel': 'انصراف' });
    const pending = confirmAction('کار حذف شود؟');
    assert.equal(view.title.textContent, 'تأیید عملیات');
    assert.equal(view.cancel.textContent, 'انصراف');
    assert.equal(view.confirm.textContent, 'تأیید');
    view.dialog.close('cancel');
    assert.equal(await pending, false);
});
