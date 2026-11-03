import { clearMessageNotifications } from '../services/notification-service.js';
import { translate } from '../utils/i18n.js';

/** Wires confirmed Messages cleanup without touching pending reminders or the sent archive. */
export function initMessageNotificationsClear(signal) {
    if (signal?.aborted) return;
    const page = document.querySelector('.notificationsContent');
    const button = page?.querySelector('[data-clear-message-notifications]');
    const dialog = page?.querySelector('[data-messages-clear-dialog]');
    if (!button || !dialog) return;
    const list = page.querySelector('#notificationsList');
    const message = page.querySelector('#notificationsPageMessage');
    let busy = false;

    /** Keeps Clear available after a pending reminder is cancelled, but never during an active request. */
    function refreshButton() {
        const hasTerminalRows = [...page.querySelectorAll('[data-notification-id]')].some(
            row => ['sent', 'cancelled'].includes(row.dataset.notificationState)
        );
        button.disabled = busy || !hasTerminalRows;
        button.querySelector('span').textContent = translate(busy ? 'notifications.clearing' : 'notifications.clear');
    }

    /** Announces localized success or failure through the existing Messages live region. */
    function showMessage(key, isError = false, replacements = {}) {
        message.textContent = translate(key, replacements);
        message.hidden = false;
        message.classList.toggle('is-error', isError);
        message.classList.toggle('is-success', !isError);
    }

    /** Opens native confirmation and discards any stale confirmation result. */
    function openConfirmation() {
        if (busy || button.disabled || dialog.open) return;
        dialog.returnValue = '';
        dialog.showModal();
    }

    /** Removes only rows absent from the server's remaining list after a successful clear. */
    async function clearConfirmed() {
        const confirmed = dialog.returnValue === 'clear';
        dialog.returnValue = '';
        if (!confirmed || busy || signal?.aborted) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!csrfToken) {
            showMessage('settings.validation.session_expired', true);
            return;
        }
        busy = true;
        button.setAttribute('aria-busy', 'true');
        message.hidden = true;
        refreshButton();
        try {
            const data = await clearMessageNotifications(csrfToken);
            if (signal?.aborted || !page.isConnected) return;
            if (!Array.isArray(data.remaining_ids)) throw new Error('Invalid clear response.');
            const remainingIds = new Set(data.remaining_ids.map(String));
            page.querySelectorAll('[data-notification-id]').forEach(row => {
                if (!remainingIds.has(row.dataset.notificationId)) row.remove();
            });
            if (!page.querySelectorAll('[data-notification-id]').length) {
                const empty = document.createElement('li');
                empty.className = 'emptyTask notificationsEmpty';
                empty.dataset.i18n = 'notifications.empty';
                empty.textContent = translate('notifications.empty');
                list.append(empty);
            }
            showMessage('notifications.messages_cleared', false, { count: data.cleared_count });
            page.querySelector('#notificationsPageTitle').focus();
        } catch {
            if (!signal?.aborted && page.isConnected) showMessage('notifications.request_failed', true);
        } finally {
            busy = false;
            if (!signal?.aborted && page.isConnected) {
                button.removeAttribute('aria-busy');
                refreshButton();
            }
        }
    }

    const options = signal ? { signal } : undefined;
    button.addEventListener('click', openConfirmation, options);
    dialog.addEventListener('close', clearConfirmed, options);
    page.addEventListener('notification:status-changed', refreshButton, options);
    refreshButton();
}
