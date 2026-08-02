import { previewTaskReminders } from '../services/task-service.js';

const PRESET_VALUES = {
    '30-minutes': { value: 30, unit: 'minutes' },
    '1-hour': { value: 1, unit: 'hours' },
    '12-hours': { value: 12, unit: 'hours' },
    '24-hours': { value: 24, unit: 'hours' }
};

const DEFAULT_REMINDERS = [
    { preset: '30-minutes', customValue: 30, customUnit: 'minutes' },
    { preset: '1-hour', customValue: 1, customUnit: 'hours' },
    { preset: '12-hours', customValue: 12, customUnit: 'hours' },
    { preset: '24-hours', customValue: 24, customUnit: 'hours' },
    { preset: 'custom', customValue: 2, customUnit: 'days' }
];

export function initReminderPicker() {
    var reminderModal = document.getElementById('reminderModal');
    var setTaskReminderButton = document.getElementById('setTaskReminderButton');
    var closeReminderModalButton = document.getElementById('closeReminderModal');
    var cancelReminderButton = document.getElementById('cancelReminderButton');
    var applyReminderButton = document.getElementById('applyReminderButton');
    var reminderCount = document.getElementById('reminderCount');
    var reminderCountLabel = document.getElementById('reminderCountLabel');
    var reminderList = document.getElementById('reminderList');
    var reminderModalMessage = document.getElementById('reminderModalMessage');
    var taskDueAt = document.getElementById('taskDueAt');
    var taskHasTime = document.getElementById('taskHasTime');
    var taskReminders = document.getElementById('taskReminders');
    var taskReminderSummary = document.getElementById('taskReminderSummary');
    var taskModalMessage = document.getElementById('taskModalMessage');
    var setTaskDateButton = document.getElementById('setTaskDateButton');
    var newTaskForm = document.getElementById('newTaskForm');
    var committedReminders = [];
    var draftReminders = [];
    var latestPreviewIsValid = false;
    var previewTimer = null;
    var previewController = null;
    var previewRequestId = 0;
    var lastReminderModalTrigger = null;

    function setReminderModalMessage(message) {
        if (reminderModalMessage) {
            reminderModalMessage.textContent = message;
        }
    }

    function setTaskModalMessage(message) {
        if (taskModalMessage) {
            taskModalMessage.textContent = message;
        }
    }

    function cloneReminder(reminder) {
        return {
            preset: reminder.preset,
            customValue: reminder.customValue,
            customUnit: reminder.customUnit
        };
    }

    function createDefaultReminder(index) {
        return cloneReminder(DEFAULT_REMINDERS[index] || DEFAULT_REMINDERS[0]);
    }

    function hasDueDateAndTime() {
        return Boolean(
            taskDueAt
            && taskDueAt.value
            && taskHasTime
            && taskHasTime.value === '1'
        );
    }

    function getCsrfToken() {
        var csrfInput = newTaskForm
            ? newTaskForm.querySelector('input[name="csrf_token"]')
            : null;

        return csrfInput ? csrfInput.value : '';
    }

    function getReminderPayload(reminder) {
        if (reminder.preset !== 'custom') {
            return {
                value: PRESET_VALUES[reminder.preset].value,
                unit: PRESET_VALUES[reminder.preset].unit
            };
        }

        return {
            value: Number(reminder.customValue),
            unit: reminder.customUnit
        };
    }

    function getDraftPayload() {
        return draftReminders.map(getReminderPayload);
    }

    function cancelPendingPreview() {
        if (previewTimer !== null) {
            window.clearTimeout(previewTimer);
            previewTimer = null;
        }

        if (previewController) {
            previewController.abort();
            previewController = null;
        }
    }

    function setPreviewState(message, state) {
        if (!reminderList) {
            return;
        }

        reminderList.querySelectorAll('.reminderPreview').forEach(function (preview) {
            preview.textContent = message;
            preview.classList.toggle('is-loading', state === 'loading');
            preview.classList.toggle('is-error', state === 'error');
            preview.classList.toggle('is-ready', state === 'ready');
        });
    }

    function renderPreviewItems(previewItems) {
        if (!reminderList) {
            return;
        }

        reminderList.querySelectorAll('.reminderPreview').forEach(function (preview, index) {
            var previewItem = previewItems[index];

            preview.classList.remove('is-loading', 'is-error');
            preview.classList.add('is-ready');
            preview.textContent = previewItem
                ? 'Your notification will be sent on ' + previewItem.formatted + '.'
                : '';
        });
    }

    async function refreshPreviews() {
        latestPreviewIsValid = false;

        if (!hasDueDateAndTime()) {
            setPreviewState('Set a due date and time to calculate this notification.', 'error');
            setReminderModalMessage('Please set a due date and time before adding reminders.');
            return false;
        }

        var payload = getDraftPayload();
        var invalidReminderIndex = payload.findIndex(function (reminder) {
            return !Number.isInteger(reminder.value) || reminder.value < 1;
        });

        if (invalidReminderIndex !== -1) {
            setPreviewState('Enter a positive whole number to calculate this notification.', 'error');
            setReminderModalMessage(
                'Reminder ' + (invalidReminderIndex + 1) + ' must have a positive whole number.'
            );
            return false;
        }

        cancelPendingPreview();
        var requestId = previewRequestId + 1;
        previewRequestId = requestId;
        previewController = new AbortController();
        setReminderModalMessage('');
        setPreviewState('Calculating notification time...', 'loading');

        try {
            var previewItems = await previewTaskReminders({
                csrfToken: getCsrfToken(),
                dueAt: taskDueAt.value,
                hasTime: taskHasTime.value,
                reminders: payload
            }, previewController.signal);

            if (requestId !== previewRequestId) {
                return false;
            }

            renderPreviewItems(previewItems);
            latestPreviewIsValid = true;
            previewController = null;
            return true;
        } catch (error) {
            if (error.name === 'AbortError') {
                return false;
            }

            if (requestId === previewRequestId) {
                setPreviewState('The notification time could not be calculated.', 'error');
                setReminderModalMessage(
                    error.message || 'The reminder time could not be calculated.'
                );
            }

            previewController = null;
            return false;
        }
    }

    function schedulePreview() {
        latestPreviewIsValid = false;

        if (previewTimer !== null) {
            window.clearTimeout(previewTimer);
        }

        if (previewController) {
            previewController.abort();
            previewController = null;
        }

        setPreviewState('Calculating notification time...', 'loading');
        previewTimer = window.setTimeout(function () {
            previewTimer = null;
            refreshPreviews();
        }, 180);
    }

    function updateCountLabel() {
        if (reminderCountLabel && reminderCount) {
            reminderCountLabel.textContent = reminderCount.value === '1'
                ? 'Reminder'
                : 'Reminders';
        }
    }

    function updateCustomMaximum(customInput, customUnit) {
        if (!customInput) {
            return;
        }

        var maximums = {
            minutes: 525600,
            hours: 8760,
            days: 365
        };

        customInput.max = String(maximums[customUnit] || 365);
    }

    function renderReminderList() {
        if (!reminderList) {
            return;
        }

        reminderList.textContent = '';

        draftReminders.forEach(function (reminder, index) {
            var reminderNumber = index + 1;
            var reminderItem = document.createElement('section');
            var presetId = 'reminderPreset' + reminderNumber;
            var customValueId = 'reminderCustomValue' + reminderNumber;
            var customUnitId = 'reminderCustomUnit' + reminderNumber;

            reminderItem.className = 'reminderItem';
            reminderItem.innerHTML =
                '<h3>Reminder ' + reminderNumber + '</h3>'
                + '<label class="reminderPresetField" for="' + presetId + '">'
                + '<span>Remind me at</span>'
                + '<select id="' + presetId + '" class="reminderPreset">'
                + '<option value="30-minutes">30 minutes before due time</option>'
                + '<option value="1-hour">1 hour before due time</option>'
                + '<option value="12-hours">12 hours before due time</option>'
                + '<option value="24-hours">24 hours before due time</option>'
                + '<option value="custom">Customize time</option>'
                + '</select>'
                + '</label>'
                + '<div class="customReminderFields">'
                + '<label for="' + customValueId + '">'
                + '<span>Time</span>'
                + '<input id="' + customValueId + '" class="reminderCustomValue" type="number" min="1" step="1" inputmode="numeric">'
                + '</label>'
                + '<label for="' + customUnitId + '">'
                + '<span>Unit</span>'
                + '<select id="' + customUnitId + '" class="reminderCustomUnit">'
                + '<option value="hours">Hours</option>'
                + '<option value="minutes">Minutes</option>'
                + '<option value="days">Days</option>'
                + '</select>'
                + '</label>'
                + '<span class="beforeDueText">before due time</span>'
                + '</div>'
                + '<p class="reminderPreview" aria-live="polite"></p>';

            var presetSelect = reminderItem.querySelector('.reminderPreset');
            var customFields = reminderItem.querySelector('.customReminderFields');
            var customValue = reminderItem.querySelector('.reminderCustomValue');
            var customUnit = reminderItem.querySelector('.reminderCustomUnit');

            presetSelect.value = reminder.preset;
            customValue.value = String(reminder.customValue);
            customUnit.value = reminder.customUnit;
            customFields.hidden = reminder.preset !== 'custom';
            updateCustomMaximum(customValue, reminder.customUnit);

            presetSelect.addEventListener('change', function () {
                reminder.preset = presetSelect.value;
                customFields.hidden = reminder.preset !== 'custom';
                schedulePreview();
            });

            customValue.addEventListener('input', function () {
                reminder.customValue = customValue.value;
                schedulePreview();
            });

            customUnit.addEventListener('change', function () {
                reminder.customUnit = customUnit.value;
                updateCustomMaximum(customValue, reminder.customUnit);
                schedulePreview();
            });

            reminderList.appendChild(reminderItem);
        });
    }

    function updateCommittedReminderSummary() {
        var payload = committedReminders.map(getReminderPayload);

        if (taskReminders) {
            taskReminders.value = JSON.stringify(payload);
        }

        if (taskReminderSummary) {
            if (!payload.length) {
                taskReminderSummary.textContent = 'No reminders set';
            } else {
                taskReminderSummary.textContent = payload.length === 1
                    ? '1 reminder set'
                    : payload.length + ' reminders set';
            }
        }

        if (setTaskReminderButton) {
            setTaskReminderButton.classList.toggle('has-reminders', payload.length > 0);
        }
    }

    function closeReminderModal(shouldRestoreFocus) {
        if (!reminderModal) {
            return;
        }

        cancelPendingPreview();
        reminderModal.hidden = true;
        latestPreviewIsValid = false;
        setReminderModalMessage('');

        if (setTaskReminderButton) {
            setTaskReminderButton.setAttribute('aria-expanded', 'false');
        }

        if (shouldRestoreFocus !== false
            && lastReminderModalTrigger
            && typeof lastReminderModalTrigger.focus === 'function') {
            lastReminderModalTrigger.focus();
        }
    }

    function openReminderModal(trigger) {
        if (!reminderModal || !reminderCount || !reminderList) {
            return;
        }

        if (!hasDueDateAndTime()) {
            setTaskModalMessage('Please set a due date and time before adding reminders.');

            if (setTaskDateButton) {
                setTaskDateButton.focus();
            }

            return;
        }

        lastReminderModalTrigger = trigger || document.activeElement;
        draftReminders = committedReminders.length
            ? committedReminders.map(cloneReminder)
            : [createDefaultReminder(0)];
        reminderCount.value = String(draftReminders.length);
        updateCountLabel();
        renderReminderList();
        setTaskModalMessage('');
        setReminderModalMessage('');
        reminderModal.hidden = false;

        if (setTaskReminderButton) {
            setTaskReminderButton.setAttribute('aria-expanded', 'true');
        }

        schedulePreview();

        window.requestAnimationFrame(function () {
            if (closeReminderModalButton) {
                closeReminderModalButton.focus();
            }
        });
    }

    async function applyReminderSelection() {
        if (!applyReminderButton) {
            return;
        }

        applyReminderButton.disabled = true;
        applyReminderButton.textContent = latestPreviewIsValid ? 'Applying...' : 'Calculating...';
        var previewsAreValid = await refreshPreviews();

        if (!previewsAreValid) {
            applyReminderButton.disabled = false;
            applyReminderButton.textContent = 'Apply';
            return;
        }

        committedReminders = draftReminders.map(cloneReminder);
        updateCommittedReminderSummary();
        applyReminderButton.disabled = false;
        applyReminderButton.textContent = 'Apply';
        closeReminderModal();
    }

    if (setTaskReminderButton) {
        setTaskReminderButton.addEventListener('click', function () {
            openReminderModal(setTaskReminderButton);
        });
    }

    if (closeReminderModalButton) {
        closeReminderModalButton.addEventListener('click', function () {
            closeReminderModal();
        });
    }

    if (cancelReminderButton) {
        cancelReminderButton.addEventListener('click', function () {
            closeReminderModal();
        });
    }

    if (applyReminderButton) {
        applyReminderButton.addEventListener('click', applyReminderSelection);
    }

    if (reminderModal) {
        reminderModal.addEventListener('click', function (event) {
            if (event.target === reminderModal) {
                closeReminderModal();
            }
        });
    }

    if (reminderCount) {
        reminderCount.addEventListener('change', function () {
            var requestedCount = Number(reminderCount.value);

            while (draftReminders.length < requestedCount) {
                draftReminders.push(createDefaultReminder(draftReminders.length));
            }

            draftReminders = draftReminders.slice(0, requestedCount);
            updateCountLabel();
            renderReminderList();
            schedulePreview();
        });
    }

    document.addEventListener('task:due-date-changed', function () {
        if (!hasDueDateAndTime()) {
            committedReminders = [];
            updateCommittedReminderSummary();

            if (reminderModal && !reminderModal.hidden) {
                closeReminderModal(false);
            }
        } else if (reminderModal && !reminderModal.hidden) {
            schedulePreview();
        }
    });

    updateCommittedReminderSummary();

    return {
        isOpen: function () {
            return Boolean(reminderModal && !reminderModal.hidden);
        },
        close: function (shouldRestoreFocus) {
            closeReminderModal(shouldRestoreFocus);
        }
    };
}
