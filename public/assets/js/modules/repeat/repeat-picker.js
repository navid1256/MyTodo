import {
    normaliseDate,
    parseDateKey
} from '../../utils/date-utils.js';
import {
    cloneRule,
    createDefaultRule,
    formatRuleSummary,
    validateRule
} from './repeat-rule.js';
import { createRepeatCalendar } from './repeat-calendar.js';
import { createRepeatForm } from './repeat-form.js';

export function initRepeatPicker() {
    const repeatModal = document.getElementById('repeatModal');
    const setTaskRepeatButton = document.getElementById('setTaskRepeatButton');
    const closeRepeatModalButton = document.getElementById('closeRepeatModal');
    const cancelRepeatButton = document.getElementById('cancelRepeatButton');
    const applyRepeatButton = document.getElementById('applyRepeatButton');
    const taskRepeat = document.getElementById('taskRepeat');
    const taskRepeatSummary = document.getElementById('taskRepeatSummary');
    const taskDueAt = document.getElementById('taskDueAt');
    const repeatModalMessage = document.getElementById('repeatModalMessage');
    let appliedRule = null;
    let lastTrigger = null;

    function getTaskStartDate() {
        if (!taskDueAt || !taskDueAt.value) {
            return null;
        }

        return parseDateKey(taskDueAt.value.slice(0, 10));
    }

    function getBaseDate() {
        return getTaskStartDate() || normaliseDate(new Date());
    }

    function setMessage(message) {
        if (repeatModalMessage) {
            repeatModalMessage.textContent = message;
        }
    }

    const repeatCalendar = createRepeatCalendar({
        getMinimumEndDate: getBaseDate,
        setMessage
    });
    const repeatForm = createRepeatForm({
        calendar: repeatCalendar,
        getBaseDate,
        setMessage
    });

    function close(restoreFocus) {
        if (!repeatModal) {
            return;
        }

        if (repeatModal.open) {
            repeatModal.close();
        }

        setMessage('');

        if (setTaskRepeatButton) {
            setTaskRepeatButton.setAttribute('aria-expanded', 'false');
        }

        if (restoreFocus !== false && lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    }

    function open(trigger) {
        if (!repeatModal) {
            return;
        }

        lastTrigger = trigger || document.activeElement;
        repeatForm.populate(cloneRule(appliedRule) || createDefaultRule(getBaseDate()));
        setMessage('');
        if (!repeatModal.open) {
            repeatModal.showModal();
        }

        if (setTaskRepeatButton) {
            setTaskRepeatButton.setAttribute('aria-expanded', 'true');
        }

        window.requestAnimationFrame(function () {
            const selectedFrequency = document.querySelector('input[name="task_repeat_frequency"]:checked');

            if (selectedFrequency) {
                selectedFrequency.focus();
            }
        });
    }

    if (setTaskRepeatButton) {
        setTaskRepeatButton.addEventListener('click', function () {
            open(setTaskRepeatButton);
        });
    }

    if (closeRepeatModalButton) {
        closeRepeatModalButton.addEventListener('click', function () {
            close();
        });
    }

    if (cancelRepeatButton) {
        cancelRepeatButton.addEventListener('click', function () {
            close();
        });
    }

    if (repeatModal) {
        repeatModal.addEventListener('click', function (event) {
            if (event.target === repeatModal) {
                close();
            }
        });

        repeatModal.addEventListener('cancel', function (event) {
            event.preventDefault();
            close();
        });
    }

    if (applyRepeatButton) {
        applyRepeatButton.addEventListener('click', function () {
            const rule = repeatForm.collect();
            const validationMessage = validateRule(rule, getTaskStartDate());

            if (validationMessage) {
                setMessage(validationMessage);
                return;
            }

            appliedRule = cloneRule(rule);

            if (taskRepeat) {
                taskRepeat.value = JSON.stringify(appliedRule);
            }

            if (taskRepeatSummary) {
                taskRepeatSummary.textContent = formatRuleSummary(appliedRule);
            }

            if (setTaskRepeatButton) {
                setTaskRepeatButton.classList.add('has-repeat');
            }

            close();
        });
    }

    return {
        close,
        isOpen: function () {
            return Boolean(repeatModal?.open);
        },
        validate: function () {
            return appliedRule ? validateRule(appliedRule, getTaskStartDate()) : '';
        }
    };
}
