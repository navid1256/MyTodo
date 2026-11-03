import { translate } from './i18n.js';

const dialogQueues = new WeakMap();

/** Opens the next live request without replacing a message the user is still reading. */
function openNext(dialog, state) {
    if (state.active) return;
    const job = state.queue.shift();
    if (!job) return;
    if (job.done || job.options.signal?.aborted) {
        job.finish(false);
        openNext(dialog, state);
        return;
    }
    state.active = job;
    try {
        const cancel = dialog.querySelector('[data-dialog-cancel]');
        const confirm = dialog.querySelector('[data-dialog-confirm]');
        dialog.querySelector('[data-dialog-title]').textContent = job.options.title
            || translate(job.options.alert ? 'dialog.message_title' : 'dialog.confirm_title');
        dialog.querySelector('[data-dialog-message]').textContent = job.message;
        cancel.textContent = translate('common.cancel');
        cancel.hidden = Boolean(job.options.alert);
        cancel.autofocus = !job.options.alert;
        confirm.autofocus = Boolean(job.options.alert);
        confirm.textContent = job.options.confirmLabel || translate(job.options.alert ? 'dialog.ok' : 'dialog.confirm');
        dialog.classList.toggle('is-destructive', Boolean(job.options.destructive));
        dialog.returnValue = '';
        dialog.addEventListener('close', job.onClose);
        dialog.showModal();
    } catch {
        // Missing or unsupported UI must never silently authorize a destructive action.
        job.finish(false);
    }
}

/** Enqueues one safe text-only dialog and settles it when the user responds or its view is abandoned. */
function requestDialog(message, options) {
    const dialog = document.getElementById('appMessageDialog');
    if (!dialog || typeof dialog.showModal !== 'function' || options.signal?.aborted) {
        return Promise.resolve(false);
    }
    let state = dialogQueues.get(dialog);
    if (!state) {
        state = { queue: [], active: null };
        dialogQueues.set(dialog, state);
    }
    return new Promise(resolve => {
        const job = { message: String(message), options, done: false };

        /** Cleans up this request once, without resetting another queued request's dialog. */
        job.finish = function finish(confirmed) {
            if (job.done) return;
            job.done = true;
            options.signal?.removeEventListener('abort', job.onAbort);
            if (state.active === job) {
                dialog.removeEventListener('close', job.onClose);
                dialog.returnValue = '';
                state.active = null;
            }
            resolve(confirmed);
            queueMicrotask(() => openNext(dialog, state));
        };

        /** Treats Escape, cancellation, and stale view results as rejection rather than confirmation. */
        job.onClose = function onClose() {
            job.finish(dialog.returnValue === 'confirm' && !options.signal?.aborted);
        };

        /** Dismisses active UI or removes an abandoned queued action before it can be shown. */
        job.onAbort = function onAbort() {
            if (state.active === job) {
                // A native close event may still be queued after open becomes false.
                if (dialog.open) dialog.close('');
            } else {
                job.finish(false);
            }
        };
        options.signal?.addEventListener('abort', job.onAbort, { once: true });
        state.queue.push(job);
        openNext(dialog, state);
    });
}

/** Resolves true only after explicit confirmation; cancellation, navigation, or unavailable UI resolve false. */
export function confirmAction(message, options = {}) {
    return requestDialog(message, options);
}

/** Displays a one-button application message without invoking the browser's blocking alert UI. */
export function showAlert(message, options = {}) {
    return requestDialog(message, { ...options, alert: true });
}
