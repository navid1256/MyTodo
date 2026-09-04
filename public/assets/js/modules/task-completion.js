import { toggleTaskCompletion } from '../services/task-service.js';
import { translate } from '../utils/i18n.js';

function updateTaskItem(toggleButton, task) {
    const taskItem = toggleButton.closest('.taskItem');
    const icon = toggleButton.querySelector('i');
    const isDone = Boolean(task.is_done);

    if (taskItem) {
        taskItem.classList.toggle('checked', isDone);
    }

    toggleButton.setAttribute('aria-pressed', String(isDone));
    toggleButton.setAttribute(
        'aria-label',
        isDone ? translate('task.mark_incomplete') : translate('task.mark_completed')
    );

    if (icon) {
        icon.classList.toggle('fa-square-check', isDone);
        icon.classList.toggle('fa-square', !isDone);
    }
}
export function initTaskCompletion(signal) {
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';
    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (browserTimezone) {
        document.cookie = 'mytodo_timezone=' + encodeURIComponent(browserTimezone)
            + '; path=/; max-age=31536000; SameSite=Lax';
    }



    function updateCompletedCount(count) {
        const completedCount = document.querySelector('.completedCount');
        const completedButton = document.querySelector('.completedButton');
        const safeCount = Math.max(0, Number(count) || 0);

        if (completedCount) {
            completedCount.textContent = String(safeCount);
        }

        if (completedButton) {
            completedButton.setAttribute(
                'aria-label',
                translate('task.completed_today_label', { count: safeCount })
            );
        }
    }

    function removeCompletedTaskFromManageTasks(taskItem, taskId) {
        if (!taskItem || document.body.dataset.activeView !== 'manage-tasks') {
            return;
        }

        taskItem.remove();
        document.dispatchEvent(new CustomEvent('task:removed-from-manage', {
            detail: { taskId: Number(taskId) }
        }));
    }

    document.addEventListener('click', function (event) {
        const toggleButton = event.target.closest('[data-task-toggle]');

        if (!toggleButton || toggleButton.dataset.processing === 'true') {
            return;
        }

        const taskId = Number(toggleButton.dataset.taskId);
        const taskItem = toggleButton.closest('.taskItem');

        if (!Number.isInteger(taskId) || taskId < 1 || csrfToken === '') {
            return;
        }

        toggleButton.dataset.processing = 'true';
        toggleButton.setAttribute('aria-disabled', 'true');

        toggleTaskCompletion(taskId, csrfToken)
            .then(function (responseData) {
                const task = responseData.task || {
                    id: taskId,
                    is_done: Boolean(responseData.is_done)
                };

                updateTaskItem(toggleButton, task);
                updateCompletedCount(responseData.completed_today_count);
                if (task.is_done) {
                    removeCompletedTaskFromManageTasks(taskItem, task.id);
                }
                delete toggleButton.dataset.processing;
                toggleButton.removeAttribute('aria-disabled');
            })
            .catch(function (error) {
                window.alert(error.message || translate('task.toggle_failed', {}, 'The task status could not be updated.'));
                delete toggleButton.dataset.processing;
                toggleButton.removeAttribute('aria-disabled');
            });
    }, signal ? { signal } : undefined);
}
