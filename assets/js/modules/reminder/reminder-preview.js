import { previewTaskReminders } from '../../services/task-service.js';

export function createReminderPreviewController(options) {
    let previewTimer = null;
    let previewController = null;
    let previewRequestId = 0;
    let latestPreviewIsValid = false;

    function cancelPendingPreview() {
        if (previewTimer !== null) {
            window.clearTimeout(previewTimer);
            previewTimer = null;
        }

        if (previewController) {
            previewController.abort();
            previewController = null;
        }

        previewRequestId += 1;
        latestPreviewIsValid = false;
    }

    async function refreshPreviews() {
        cancelPendingPreview();

        if (!options.hasDueDateAndTime()) {
            options.setPreviewState(
                'Set a due date and time to calculate this notification.',
                'error'
            );
            options.setModalMessage(
                'Please set a due date and time before adding reminders.'
            );
            return false;
        }

        const payload = options.getPayload();
        const invalidReminderIndex = payload.findIndex(function (reminder) {
            return !Number.isInteger(reminder.value) || reminder.value < 0;
        });

        if (invalidReminderIndex !== -1) {
            options.setPreviewState(
                'Choose a valid reminder time to calculate this notification.',
                'error'
            );
            options.setModalMessage(
                'Reminder ' + (invalidReminderIndex + 1) + ' has an invalid reminder time.'
            );
            return false;
        }

        const requestId = previewRequestId + 1;
        previewRequestId = requestId;
        const requestController = new AbortController();
        previewController = requestController;

        options.setModalMessage('');
        options.setPreviewState('Calculating notification time...', 'loading');

        try {
            const previewItems = await previewTaskReminders({
                csrfToken: options.getCsrfToken(),
                dueAt: options.getDueAt(),
                hasTime: options.getHasTime(),
                reminders: payload
            }, requestController.signal);

            if (requestId !== previewRequestId) {
                return false;
            }

            options.renderPreviewItems(previewItems);
            latestPreviewIsValid = true;
            return true;
        } catch (error) {
            if (error.name === 'AbortError') {
                return false;
            }

            if (requestId === previewRequestId) {
                options.setPreviewState(
                    'The notification time could not be calculated.',
                    'error'
                );
                options.setModalMessage(
                    error.message || 'The reminder time could not be calculated.'
                );
            }

            return false;
        } finally {
            if (previewController === requestController) {
                previewController = null;
            }
        }
    }

    function schedulePreview() {
        cancelPendingPreview();
        options.setPreviewState('Calculating notification time...', 'loading');

        previewTimer = window.setTimeout(function () {
            previewTimer = null;
            refreshPreviews();
        }, 180);
    }

    return {
        schedule: schedulePreview,
        refresh: refreshPreviews,
        cancel: cancelPendingPreview,
        isValid: function () {
            return latestPreviewIsValid;
        }
    };
}
