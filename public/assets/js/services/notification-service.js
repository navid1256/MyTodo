import { sendJsonFormRequest } from './api-client.js';
import { translate } from '../utils/i18n.js';

export function updateNotification(formData) {
    formData.set('action', 'updateNotification');
    return sendJsonFormRequest(formData, {
        errorMessage: translate('notifications.request_failed', {}, 'The notification request failed.')
    });
}

export function cancelNotification(notificationId, csrfToken) {
    const formData = new FormData();
    formData.set('action', 'cancelNotification');
    formData.set('csrf_token', csrfToken);
    formData.set('notification_id', String(notificationId));

    return sendJsonFormRequest(formData, {
        errorMessage: translate('notifications.request_failed', {}, 'The notification request failed.')
    });
}

/** Reads archive data with the current session without changing notification state. */
export async function getNotificationArchive(path, signal) {
    const response = await fetch('/api/notifications/' + path, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store', signal,
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(translate('notifications.request_failed'));
    }
    return data;
}

/** Sends a CSRF-protected archive action using the existing JSON response contract. */
export function changeNotificationArchive(action, csrfToken, signal) {
    const formData = new FormData();
    formData.set('csrf_token', csrfToken);
    return sendJsonFormRequest('/api/notifications/' + action, formData, {
        signal, errorMessage: translate('notifications.request_failed'),
    });
}
