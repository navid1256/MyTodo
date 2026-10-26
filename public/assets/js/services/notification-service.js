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
