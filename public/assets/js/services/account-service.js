import { sendJsonFormRequest } from './api-client.js';

export async function changePassword(formData, errorMessage) {
    formData.set('action', 'changePassword');

    return sendJsonFormRequest(formData, {
        errorMessage: errorMessage || 'The password could not be changed.'
    });
}

export async function saveAccountSettings(formData, signal, errorMessage) {
    return sendJsonFormRequest('/api/settings', formData, {
        signal,
        errorMessage: errorMessage || 'The account settings could not be saved.'
    });
}
