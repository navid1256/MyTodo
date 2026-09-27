import { sendJsonFormRequest } from './api-client.js';

export function sendPushRequest(action, csrfToken, fields = {}, signal) {
    const data = new FormData();
    data.set('csrf_token', csrfToken);
    Object.entries(fields).forEach(([key, value]) => data.set(key, value));
    return sendJsonFormRequest(`/api/push/${action}`, data, { signal });
}
