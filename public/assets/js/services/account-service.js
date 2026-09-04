import { sendJsonFormRequest } from './api-client.js';
import { translate } from '../utils/i18n.js';

export async function changePassword(formData, errorMessage) {
    formData.set('action', 'changePassword');

    return sendJsonFormRequest(formData, {
        errorMessage: errorMessage || translate('password.change_failed')
    });
}

export async function saveAccountSettings(formData, signal, errorMessage) {
    return sendJsonFormRequest('/api/settings', formData, {
        signal,
        errorMessage: errorMessage || translate('settings.save_failed')
    });
}
