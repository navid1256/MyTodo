import { clearCompletedTasks } from '../services/task-service.js';
import { translate } from '../utils/i18n.js';
import { updateCompletedCount } from '../utils/task-counters.js';
import { updateNotificationBadge } from './notification-badge.js';

/** Connects Activity's confirmation dialog to an owner-scoped, non-retrying clear request. */
export function initActivityClear(signal) {
    if (signal?.aborted) {
        return;
    }
    const page = document.querySelector('.activityPage');
    const button = page?.querySelector('[data-clear-completed-tasks]');
    const dialog = page?.querySelector('[data-clear-completed-dialog]');
    const message = page?.querySelector('[data-activity-clear-message]');
    if (!button || !dialog || !message) {
        return;
    }
    let busy = false;

    /** Announces a localized result without inserting server-provided HTML. */
    function showMessage(key, isError = false, replacements = {}) {
        message.textContent = translate(key, replacements);
        message.classList.toggle('is-error', isError);
        message.hidden = false;
    }

    /** Opens native confirmation with Cancel focused and no previous confirmation retained. */
    function openConfirmation() {
        if (busy || button.disabled || dialog.open) {
            return;
        }
        dialog.returnValue = '';
        dialog.showModal();
    }

    /** Clears every completed date group only after the server confirms deletion. */
    async function handleConfirmation() {
        const confirmed = dialog.returnValue === 'clear';
        dialog.returnValue = '';
        if (!confirmed || busy || signal?.aborted) {
            return;
        }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!csrfToken) {
            showMessage('settings.validation.session_expired', true);
            return;
        }

        busy = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.querySelector('span').textContent = translate('activity.clearing');
        message.hidden = true;
        try {
            // Navigation cannot undo a committed deletion; let the request finish without retrying it.
            const response = await clearCompletedTasks(csrfToken);
            if (signal?.aborted || !page.isConnected) {
                return;
            }
            page.querySelectorAll('[data-completed-date]').forEach(group => group.remove());
            page.querySelector('#activityFilterEmpty').hidden = false;
            updateCompletedCount(response.completed_today_count);
            updateNotificationBadge(response.unread_count);
            showMessage('activity.cleared', false, { count: response.deleted_count });
            page.querySelector('#activityFilter').focus();
        } catch {
            if (!signal?.aborted && page.isConnected) {
                showMessage('activity.clear_failed', true);
            }
        } finally {
            busy = false;
            if (!signal?.aborted && page.isConnected) {
                button.disabled = !page.querySelector('.activityTaskItem');
                button.querySelector('span').textContent = translate('activity.clear');
                button.removeAttribute('aria-busy');
            }
        }
    }

    const options = signal ? { signal } : undefined;
    button.addEventListener('click', openConfirmation, options);
    dialog.addEventListener('close', handleConfirmation, options);
}
