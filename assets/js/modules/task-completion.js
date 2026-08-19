import { toggleTaskCompletion } from '../services/task-service.js';

export function initTaskCompletion(signal) {
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';
    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (browserTimezone) {
        document.cookie = 'mytodo_timezone=' + encodeURIComponent(browserTimezone)
            + '; path=/; max-age=31536000; SameSite=Lax';
    }

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
            isDone ? 'Mark task as incomplete' : 'Mark task as completed'
        );

        if (icon) {
            icon.classList.toggle('fa-square-check', isDone);
            icon.classList.toggle('fa-square', !isDone);
        }
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
                safeCount + ' tasks completed today'
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
                updateTaskItem(toggleButton, responseData.task);
                updateCompletedCount(responseData.completed_today_count);
                if (responseData.task.is_done) {
                    removeCompletedTaskFromManageTasks(taskItem, responseData.task.id);
                }
                delete toggleButton.dataset.processing;
                toggleButton.removeAttribute('aria-disabled');
            })
            .catch(function (error) {
                window.alert(error.message || 'The task status could not be updated.');
                delete toggleButton.dataset.processing;
                toggleButton.removeAttribute('aria-disabled');
            });
    }, signal ? { signal } : undefined);
}
