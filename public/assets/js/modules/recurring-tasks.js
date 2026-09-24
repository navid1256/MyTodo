import {
    cancelRepeatRule,
    pauseRepeatRule,
    resumeRepeatRule
} from '../services/repeat-service.js';
import { translate } from '../utils/i18n.js';

const registeredContainers = new WeakMap();
const pendingRows = new WeakMap();
const lifecycleActions = Object.freeze({
    active: ['edit', 'pause', 'cancel'],
    paused: ['edit', 'resume', 'cancel']
});

function getCsrfToken() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    return tokenMeta?.getAttribute('content') || tokenMeta?.content || '';
}

function setPageMessage(container, message) {
    const status = container.querySelector('#recurringTasksStatus');
    if (!status) {
        return;
    }

    status.textContent = message;
    status.hidden = !message;
}

function setRowBusy(row, busy) {
    const buttons = [...row.querySelectorAll('[data-repeat-action]')];
    if (busy) {
        const previousState = buttons.map((button) => ({
            button,
            disabled: Boolean(button.disabled),
            ariaDisabled: button.getAttribute('aria-disabled')
        }));
        buttons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
        row.setAttribute('aria-busy', 'true');
        return previousState;
    }

    buttons.forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-disabled');
    });
    row.removeAttribute('aria-busy');
}

function restoreRowButtons(row, previousState) {
    previousState.forEach(({ button, disabled, ariaDisabled }) => {
        if (!row.contains(button)) {
            return;
        }
        button.disabled = disabled;
        if (ariaDisabled === null) {
            button.removeAttribute('aria-disabled');
        } else {
            button.setAttribute('aria-disabled', ariaDisabled);
        }
    });
    row.removeAttribute('aria-busy');
}

function createActionButton(row, action) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.repeatAction = action;
    button.dataset.repeatRuleId = row.dataset.repeatRuleId;
    button.dataset.i18n = `recurring.action.${action}`;
    button.textContent = translate(`recurring.action.${action}`, {}, action[0].toUpperCase() + action.slice(1));
    return button;
}

function renderActions(row, status) {
    const actions = lifecycleActions[status];
    const actionsContainer = row.querySelector('.recurringActions');

    if (!actions) {
        actionsContainer?.remove();
        return;
    }

    const actionGroup = actionsContainer || document.createElement('div');
    actionGroup.classList.add('recurringActions');
    actionGroup.setAttribute('role', 'group');
    if (!actionsContainer) {
        row.appendChild(actionGroup);
    }

    const existing = new Map(
        [...actionGroup.querySelectorAll('[data-repeat-action]')]
            .map((button) => [button.dataset.repeatAction, button])
    );
    [...existing.entries()].forEach(([action, button]) => {
        if (!actions.includes(action)) {
            button.remove();
        }
    });

    actions.forEach((action) => {
        const button = existing.get(action) || createActionButton(row, action);
        button.disabled = false;
        button.dataset.repeatRuleId = row.dataset.repeatRuleId;
        button.textContent = translate(
            `recurring.action.${action}`,
            {},
            action[0].toUpperCase() + action.slice(1)
        );
        button.removeAttribute('aria-disabled');
        if (!button.parentElement) {
            actionGroup.appendChild(button);
        }
    });
}

function renderRuleState(row, status) {
    const safeStatus = ['active', 'paused', 'completed', 'cancelled'].includes(status)
        ? status
        : 'active';
    row.dataset.repeatStatus = safeStatus;
    const statusBadge = row.querySelector('.recurringStatus');
    if (statusBadge) {
        statusBadge.dataset.i18n = `recurring.status.${safeStatus}`;
        statusBadge.textContent = translate(
            `recurring.status.${safeStatus}`,
            {},
            safeStatus[0].toUpperCase() + safeStatus.slice(1)
        );
    }
    renderActions(row, safeStatus);
    row.removeAttribute('aria-busy');
}

function isStale(container, row, signal, requestToken) {
    return signal?.aborted || !container.contains(row) || pendingRows.get(row) !== requestToken;
}

function confirmLifecycleAction(action) {
    if (action === 'resume') {
        return true;
    }
    const message = translate(
        `recurring.confirm.${action}`,
        {},
        action === 'pause'
            ? 'Pause this recurring task?'
            : 'Cancel this recurring task permanently?'
    );
    return typeof window !== 'undefined' && typeof window.confirm === 'function'
        ? window.confirm(message)
        : true;
}

function sendLifecycleRequest(action, repeatRuleId, csrfToken, signal) {
    if (action === 'pause') {
        return pauseRepeatRule(repeatRuleId, csrfToken, signal);
    }
    if (action === 'resume') {
        return resumeRepeatRule(repeatRuleId, csrfToken, signal);
    }
    return cancelRepeatRule(repeatRuleId, csrfToken, signal);
}

function handleAction(container, actionButton, signal) {
    const action = actionButton.dataset.repeatAction;
    if (action === 'edit') {
        const row = actionButton.closest('.recurringRule');
        if (!row || actionButton.disabled) return;
        let payload = {};
        try { payload = JSON.parse(row.dataset.repeatRule || '{}'); } catch { payload = {}; }
        document.dispatchEvent(new CustomEvent('recurring-rule:edit', {
            detail: { trigger: actionButton, payload }
        }));
        return;
    }
    if (!['pause', 'resume', 'cancel'].includes(action) || actionButton.disabled) {
        return;
    }

    const row = actionButton.closest('.recurringRule');
    const repeatRuleId = Number(actionButton.dataset.repeatRuleId || row?.dataset.repeatRuleId);
    if (!row || !container.contains(row) || !Number.isInteger(repeatRuleId) || repeatRuleId < 1) {
        return;
    }
    if (!confirmLifecycleAction(action)) {
        return;
    }

    const requestToken = Symbol(action);
    pendingRows.set(row, requestToken);
    const previousState = setRowBusy(row, true);
    setPageMessage(container, translate('recurring.message.processing', {}, 'Updating recurring task…'));

    sendLifecycleRequest(action, repeatRuleId, getCsrfToken(), signal)
        .then((response) => {
            if (isStale(container, row, signal, requestToken)) {
                return;
            }
            const status = response.status || (action === 'pause' ? 'paused' : action === 'resume' ? 'active' : 'cancelled');
            renderRuleState(row, status);
            setPageMessage(
                container,
                translate(`recurring.message.${status}`, {}, `Recurring task ${status}.`)
            );
            pendingRows.delete(row);
        })
        .catch((error) => {
            if (isStale(container, row, signal, requestToken)) {
                return;
            }
            restoreRowButtons(row, previousState);
            setPageMessage(
                container,
                error?.message || translate(
                    'recurring.message.failed',
                    {},
                    'The recurring task could not be updated. Please try again.'
                )
            );
            pendingRows.delete(row);
        });
}

export function initRecurringTasks(signal) {
    if (signal?.aborted) {
        return;
    }

    const container = document.querySelector('.recurringTasksContent');
    if (!container) {
        return;
    }

    const previousSignal = registeredContainers.get(container);
    if (previousSignal && !previousSignal.aborted) {
        return;
    }

    const listener = (event) => {
        const actionButton = event.target?.closest?.('[data-repeat-action]');
        if (actionButton && container.contains(actionButton)) {
            handleAction(container, actionButton, signal);
        }
    };
    container.addEventListener('click', listener, signal ? { signal } : undefined);
    const updateListener = (event) => {
        const previousRuleId = String(event.detail?.previousRuleId || '');
        const response = event.detail?.response || {};
        const row = [...container.querySelectorAll('.recurringRule')]
            .find((candidate) => candidate.dataset.repeatRuleId === previousRuleId);
        if (!row) return;
        const title = response.task?.title || response.title;
        if (title) {
            const heading = row.querySelector('.recurringRuleMain h2');
            if (heading) heading.textContent = title;
        }
        if (response.status) renderRuleState(row, response.status);
        if (response.repeat_rule_id && response.repeat_rule_id !== Number(previousRuleId)) {
            row.dataset.repeatRuleId = String(response.repeat_rule_id);
            row.dataset.repeatStatus = 'active';
            renderRuleState(row, 'active');
        }
        setPageMessage(container, translate('recurring.message.updated', {}, 'Recurring task updated.'));
    };
    document.addEventListener('recurring-rule:updated', updateListener, signal ? { signal } : undefined);
    registeredContainers.set(container, signal || null);
}

export {
    getCsrfToken,
    renderRuleState,
    setPageMessage,
    setRowBusy
};
