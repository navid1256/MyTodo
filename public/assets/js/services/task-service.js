import {
    sendFormRequest,
    sendJsonFormRequest
} from './api-client.js';
import { translate } from '../utils/i18n.js';

export async function createTask(formData) {
    const response = await sendFormRequest(formData);

    if (!response.ok || response.text.trim() !== '1') {
        throw new Error(response.text || translate('task.save_failed', {}, 'The task could not be saved.'));
    }

    return response.text;
}

export async function toggleTaskCompletion(taskId, csrfToken) {
    const formData = new FormData();
    formData.set('action', 'toggleTaskCompletion');
    formData.set('csrf_token', csrfToken);
    formData.set('task_id', String(taskId));

    return sendJsonFormRequest(formData, {
        errorMessage: translate('task.toggle_failed', {}, 'The task status could not be updated.')
    });
}

export async function previewTaskReminders(data, signal) {
    const formData = new FormData();
    formData.set('action', 'previewReminders');
    formData.set('csrf_token', data.csrfToken);
    formData.set('due_at', data.dueAt);
    formData.set('has_time', data.hasTime);
    formData.set('reminders', JSON.stringify(data.reminders));

    const responseData = await sendJsonFormRequest(formData, {
        signal,
        errorMessage: translate('reminder.preview.request_failed', {}, 'The reminder time could not be calculated.')
    });

    return responseData.reminders;
}
