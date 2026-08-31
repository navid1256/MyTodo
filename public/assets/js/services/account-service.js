import { sendJsonFormRequest } from './api-client.js';

export async function changePassword(formData) {
    formData.set('action', 'changePassword');

    return sendJsonFormRequest(formData, {
        errorMessage: 'The password could not be changed.'
    });
}

export async function saveAccountSettings(formData, signal) {
    return sendJsonFormRequest('/api/settings', formData, {
        signal,
        errorMessage: 'The account settings could not be saved.'
    });
}
