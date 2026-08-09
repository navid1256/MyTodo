async function sendNotificationRequest(formData) {
    const response = await fetch('bootstrap/ajaxHandler.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    });
    const responseText = await response.text();
    let responseData;

    try {
        responseData = JSON.parse(responseText);
    } catch (error) {
        throw new Error(responseText || 'The notification request failed.');
    }

    if (!response.ok || !responseData.success) {
        throw new Error(responseData.message || 'The notification request failed.');
    }

    return responseData;
}

export function updateNotification(formData) {
    formData.set('action', 'updateNotification');
    return sendNotificationRequest(formData);
}

export function cancelNotification(notificationId, csrfToken) {
    const formData = new FormData();
    formData.set('action', 'cancelNotification');
    formData.set('csrf_token', csrfToken);
    formData.set('notification_id', String(notificationId));

    return sendNotificationRequest(formData);
}
