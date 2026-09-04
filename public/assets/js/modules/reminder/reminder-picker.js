import { createReminderPreviewController } from './reminder-preview.js';

import { translate } from '../../utils/i18n.js';

import {
    cloneReminder,
    createDefaultReminder,
    getReminderPayload
} from '../../utils/reminder-utils.js';

import {
    renderPreviewItems,
    updateCountLabel,
    updateCustomMaximum,
    setPreviewState
} from './reminder-list.js';

export function initReminderPicker(signal) {
    const reminderModal = document.getElementById('reminderModal');
    const setTaskReminderButton = document.getElementById('setTaskReminderButton');
    const closeReminderModalButton = document.getElementById('closeReminderModal');
    const cancelReminderButton = document.getElementById('cancelReminderButton');
    const applyReminderButton = document.getElementById('applyReminderButton');
    const reminderCount = document.getElementById('reminderCount');
    const reminderList = document.getElementById('reminderList');
    const reminderModalMessage = document.getElementById('reminderModalMessage');
    const taskDueAt = document.getElementById('taskDueAt');
    const taskHasTime = document.getElementById('taskHasTime');
    const taskReminders = document.getElementById('taskReminders');
    const taskReminderSummary = document.getElementById('taskReminderSummary');
    const taskModalMessage = document.getElementById('taskModalMessage');
    const setTaskDateButton = document.getElementById('setTaskDateButton');
    const newTaskForm = document.getElementById('newTaskForm');
    let committedReminders = [];
    let draftReminders = [];
    let lastReminderModalTrigger = null;

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

    function hasDueDateAndTime() {
        return Boolean(
            taskDueAt?.value
            && taskHasTime?.value === '1'
        );
    }

    function getCsrfToken() {
        const csrfInput = newTaskForm
            ? newTaskForm.querySelector('input[name="csrf_token"]')
            : null;

        return csrfInput ? csrfInput.value : '';
    }

    function getDraftPayload() {
        return draftReminders.map(getReminderPayload);
    }

    const reminderPreview = createReminderPreviewController({
        hasDueDateAndTime: hasDueDateAndTime,
        getPayload: getDraftPayload,
        getCsrfToken: getCsrfToken,

        getDueAt: function () {
            return taskDueAt ? taskDueAt.value : '';
        },

        getHasTime: function () {
            return taskHasTime ? taskHasTime.value : '0';
        },

        setPreviewState: setPreviewState,
        setModalMessage: setReminderModalMessage,
        renderPreviewItems: renderPreviewItems
    });

    function renderReminderList() {
        if (!reminderList) {
            return;
        }

        reminderList.textContent = '';

        draftReminders.forEach(function (reminder, index) {
            const reminderNumber = index + 1;
            const reminderItem = document.createElement('section');
            const presetId = 'reminderPreset' + reminderNumber;
            const customValueId = 'reminderCustomValue' + reminderNumber;
            const customUnitId = 'reminderCustomUnit' + reminderNumber;

            reminderItem.className = 'reminderItem';
            reminderItem.innerHTML =
                '<h3>' + translate('reminder.item_title', { number: reminderNumber }, 'Reminder {number}') + '</h3>'
                + '<label class="reminderPresetField" for="' + presetId + '">'
                + '<span>' + translate('notifications.editor.remind_me') + '</span>'
                + '<select id="' + presetId + '" class="reminderPreset">'
                + '<option value="on-due-time">' + translate('notifications.on_due_time') + '</option>'
                + '<option value="30-minutes">' + translate('reminder.preset.30_minutes', {}, '30 minutes before due time') + '</option>'
                + '<option value="1-hour">' + translate('reminder.preset.1_hour', {}, '1 hour before due time') + '</option>'
                + '<option value="12-hours">' + translate('reminder.preset.12_hours', {}, '12 hours before due time') + '</option>'
                + '<option value="24-hours">' + translate('reminder.preset.24_hours', {}, '24 hours before due time') + '</option>'
                + '<option value="custom">' + translate('reminder.preset.custom', {}, 'Customize time') + '</option>'
                + '</select>'
                + '</label>'
                + '<div class="customReminderFields">'
                + '<label for="' + customValueId + '">'
                + '<span>' + translate('date_time.hour') + '</span>'
                + '<input id="' + customValueId + '" class="reminderCustomValue" type="number" min="1" step="1" inputmode="numeric">'
                + '</label>'
                + '<label for="' + customUnitId + '">'
                + '<span>' + translate('notifications.editor.time_unit') + '</span>'
                + '<select id="' + customUnitId + '" class="reminderCustomUnit">'
                + '<option value="hours">' + translate('notifications.unit.hour.other') + '</option>'
                + '<option value="minutes">' + translate('notifications.unit.minute.other') + '</option>'
                + '<option value="days">' + translate('notifications.unit.day.other') + '</option>'
                + '</select>'
                + '</label>'
                + '<span class="beforeDueText">' + translate('notifications.editor.before_due_time') + '</span>'
                + '</div>'
                + '<p class="reminderPreview" aria-live="polite"></p>';

            const presetSelect = reminderItem.querySelector('.reminderPreset');
            const customFields = reminderItem.querySelector('.customReminderFields');
            const customValue = reminderItem.querySelector('.reminderCustomValue');
            const customUnit = reminderItem.querySelector('.reminderCustomUnit');

            presetSelect.value = reminder.preset;
            customValue.value = String(reminder.customValue);
            customUnit.value = reminder.customUnit;
            customFields.hidden = reminder.preset !== 'custom';
            updateCustomMaximum(customValue, reminder.customUnit);

            presetSelect.addEventListener('change', function () {
                reminder.preset = presetSelect.value;
                customFields.hidden = reminder.preset !== 'custom';
                reminderPreview.schedule();
            });

            customValue.addEventListener('input', function () {
                reminder.customValue = customValue.value;
                reminderPreview.schedule();
            });

            customUnit.addEventListener('change', function () {
                reminder.customUnit = customUnit.value;
                updateCustomMaximum(customValue, reminder.customUnit);
                reminderPreview.schedule();
            });

            reminderList.appendChild(reminderItem);
        });
    }

    function updateCommittedReminderSummary() {
        const payload = committedReminders.map(getReminderPayload);

        if (taskReminders) {
            taskReminders.value = JSON.stringify(payload);
        }

        if (taskReminderSummary) {
            if (!payload.length) {
                taskReminderSummary.textContent = translate('task.modal.no_reminders');
            } else if (payload.length === 1) {
                taskReminderSummary.textContent = translate('reminder.summary_one');
            } else {
                taskReminderSummary.textContent = translate('reminder.summary', { count: payload.length });
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

        reminderPreview.cancel();
        if (reminderModal.open) {
            reminderModal.close();
        }
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
            setTaskModalMessage(translate('reminder.validation.date_required', {}, 'Please set a due date and time before adding reminders.'));

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
        if (!reminderModal.open) {
            reminderModal.showModal();
        }

        if (setTaskReminderButton) {
            setTaskReminderButton.setAttribute('aria-expanded', 'true');
        }

        reminderPreview.schedule();

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
        applyReminderButton.textContent = reminderPreview.isValid()
            ? translate('reminder.applying', {}, 'Applying...')
            : translate('reminder.calculating', {}, 'Calculating...');
        const previewsAreValid = await reminderPreview.refresh();

        if (!previewsAreValid) {
            applyReminderButton.disabled = false;
            applyReminderButton.textContent = translate('common.apply');
            return;
        }

        committedReminders = draftReminders.map(cloneReminder);
        updateCommittedReminderSummary();
        applyReminderButton.disabled = false;
        applyReminderButton.textContent = translate('common.apply');
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

        reminderModal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeReminderModal();
        });
    }

    if (reminderCount) {
        reminderCount.addEventListener('change', function () {
            const requestedCount = Number(reminderCount.value);

            while (draftReminders.length < requestedCount) {
                draftReminders.push(createDefaultReminder(draftReminders.length));
            }

            draftReminders = draftReminders.slice(0, requestedCount);
            updateCountLabel();
            renderReminderList();
            reminderPreview.schedule();
        });
    }

    document.addEventListener('task:due-date-changed', function () {
        if (!hasDueDateAndTime()) {
            committedReminders = [];
            updateCommittedReminderSummary();

            if (reminderModal?.open) {
                closeReminderModal(false);
            }
        } else if (reminderModal?.open) {
            reminderPreview.schedule();
        }
    }, signal ? { signal } : undefined);

    updateCommittedReminderSummary();

    return {
        isOpen: function () {
            return Boolean(reminderModal?.open);
        },
        close: function (shouldRestoreFocus) {
            closeReminderModal(shouldRestoreFocus);
        }
    };
}
