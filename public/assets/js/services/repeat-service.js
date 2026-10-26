import { sendJsonFormRequest } from './api-client.js';
import { translate } from '../utils/i18n.js';

const lifecycleEndpoints = Object.freeze({
    pause: '/api/repeat-rules/pause',
    resume: '/api/repeat-rules/resume',
    cancel: '/api/repeat-rules/cancel',
    updateTask: '/api/repeat-tasks/update',
    updateRule: '/api/repeat-rules/update'
});

/** Loads the authoritative server-rendered rows, including their current edit payloads. */
export async function fetchRecurringRows(signal) {
    const response = await fetch('/recurring-tasks?partial=1&filter=all', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal
    });
    if (!response.ok) throw new Error('Recurring rows could not be refreshed.');
    const payload = await response.json();
    if (payload?.activeView !== 'recurring-tasks' || typeof payload.html !== 'string') {
        throw new Error('Invalid recurring rows response.');
    }
    return payload.html;
}

async function updateRepeatRule(action, repeatRuleId, csrfToken, signal) {
    const formData = new FormData();
    formData.set('repeat_rule_id', String(repeatRuleId));
    formData.set('csrf_token', String(csrfToken || ''));

    return sendJsonFormRequest(lifecycleEndpoints[action], formData, {
        signal,
        errorMessage: translate(
            'recurring.message.failed',
            {},
            'The recurring task could not be updated. Please try again.'
        )
    });
}

export function pauseRepeatRule(repeatRuleId, csrfToken, signal) {
    return updateRepeatRule('pause', repeatRuleId, csrfToken, signal);
}

export function resumeRepeatRule(repeatRuleId, csrfToken, signal) {
    return updateRepeatRule('resume', repeatRuleId, csrfToken, signal);
}

export function cancelRepeatRule(repeatRuleId, csrfToken, signal) {
    return updateRepeatRule('cancel', repeatRuleId, csrfToken, signal);
}

export function updateRecurringTask(formData) {
    return sendJsonFormRequest(lifecycleEndpoints.updateTask, formData, {
        errorMessage: translate('task.edit_failed', {}, 'The recurring task could not be updated.')
    });
}

export function updateRecurringRule(formData) {
    return sendJsonFormRequest(lifecycleEndpoints.updateRule, formData, {
        errorMessage: translate('task.edit_failed', {}, 'The recurring rule could not be updated.')
    });
}
