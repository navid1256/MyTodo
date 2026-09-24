import { sendJsonFormRequest } from './api-client.js';
import { translate } from '../utils/i18n.js';

const lifecycleEndpoints = Object.freeze({
    pause: '/api/repeat-rules/pause',
    resume: '/api/repeat-rules/resume',
    cancel: '/api/repeat-rules/cancel',
    updateTask: '/api/repeat-tasks/update',
    updateRule: '/api/repeat-rules/update'
});

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
