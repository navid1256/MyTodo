import { toggleTaskCompletion } from '../services/task-service.js';

export function initTaskCompletion() {
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';

    document.addEventListener('click', function (event) {
        const toggleButton = event.target.closest('[data-task-toggle]');

        if (!toggleButton || toggleButton.dataset.processing === 'true') {
            return;
        }

        const taskId = Number(toggleButton.dataset.taskId);
        if (!Number.isInteger(taskId) || taskId < 1 || csrfToken === '') {
            return;
        }

        toggleButton.dataset.processing = 'true';
        toggleButton.setAttribute('aria-disabled', 'true');

        toggleTaskCompletion(taskId, csrfToken)
            .then(function () {
                window.location.reload();
            })
            .catch(function (error) {
                window.alert(error.message || 'The task status could not be updated.');
                delete toggleButton.dataset.processing;
                toggleButton.removeAttribute('aria-disabled');
            });
    });
}
