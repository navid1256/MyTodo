import { deleteTask } from '../services/task-service.js';
import { translate } from '../utils/i18n.js';

/** Restores the translated Home empty state after removing the last task from a section. */
function updateEmptyList(list) {
    const messageKey = list.dataset.emptyMessage;
    if (!messageKey || list.querySelector('.taskItem')) {
        return;
    }

    const emptyItem = document.createElement('li');
    emptyItem.className = 'emptyTask';
    emptyItem.dataset.i18n = messageKey;
    emptyItem.textContent = translate(messageKey);
    list.appendChild(emptyItem);
}

/** Updates the shared Completed toolbar using the server's authoritative remaining count. */
function updateCompletedCount(count) {
    const safeCount = Math.max(0, Number(count) || 0);
    const countLabel = document.querySelector('.completedCount');
    const completedButton = document.querySelector('.completedButton');
    if (countLabel) {
        countLabel.textContent = String(safeCount);
    }
    completedButton?.setAttribute('aria-label', translate('task.completed_today_label', { count: safeCount }));
}

/** Connects delegated delete clicks to POST without retrying or removing rows optimistically. */
export function initTaskDeletion(signal) {
    if (signal?.aborted) {
        return;
    }

    /** Confirms a single deletion, guards duplicate clicks and ignores results from an obsolete view. */
    async function handleTaskDelete(event) {
        const button = event.target?.closest?.('[data-task-delete]');
        if (!button || button.disabled || button.dataset.processing === 'true') {
            return;
        }
        const taskItem = button.closest('.taskItem');
        const taskId = Number(button.dataset.taskId);
        if (!taskItem || !Number.isInteger(taskId) || taskId < 1) {
            return;
        }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!csrfToken) {
            window.alert(translate('settings.validation.session_expired'));
            return;
        }
        if (!window.confirm(translate('task.delete_confirmation', { title: button.dataset.title || '' }))) {
            return;
        }

        button.disabled = true;
        button.dataset.processing = 'true';
        button.setAttribute('aria-busy', 'true');
        try {
            // Do not abort a destructive request on navigation: it may already have reached the server.
            const response = await deleteTask(taskId, csrfToken);
            if (signal?.aborted || !taskItem.isConnected) {
                return;
            }
            const list = taskItem.parentElement;
            taskItem.remove();
            updateEmptyList(list);
            updateCompletedCount(response.completed_today_count);
            if (document.body.dataset.activeView === 'manage-tasks') {
                document.dispatchEvent(new CustomEvent('task:removed-from-manage', { detail: { taskId } }));
            }
            const nextControl = list.querySelector('[data-task-delete]') || document.getElementById('openTaskModal');
            nextControl?.focus();
        } catch (error) {
            if (!signal?.aborted && taskItem.isConnected) {
                window.alert(error.message || translate('task.delete_failed'));
            }
        } finally {
            button.disabled = false;
            delete button.dataset.processing;
            button.removeAttribute('aria-busy');
        }
    }

    document.addEventListener('click', handleTaskDelete, signal ? { signal } : undefined);
}
