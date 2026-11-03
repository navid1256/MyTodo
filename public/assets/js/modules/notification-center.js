import { cancelNotification, updateNotification } from '../services/notification-service.js';
import { translate } from '../utils/i18n.js';
import { confirmAction } from '../utils/message-dialog.js';

const NOTIFICATION_STATUSES = ['pending', 'sent', 'failed', 'cancelled'];

function formatOffset(value, unit) {
    const numericValue = Number(value);

    if (numericValue === 0) {
        return translate('notifications.on_due_time');
    }

    const unitKey = 'notifications.unit.' + (unit === 'minute' ? 'minute' : unit === 'hour' ? 'hour' : 'day') + '.'
        + (numericValue === 1 ? 'one' : 'other');

    return translate(
        'notifications.before_due_time',
        { value: numericValue, unit: translate(unitKey, {}, unit) },
        '{value} {unit} before due time'
    );
}

/** Updates a reminder's visible state and notifies Messages cleanup when it becomes clearable. */
function setRowStatus(row, status) {
    row.dataset.notificationState = status;
    const statusElement = row.querySelector('[data-notification-status]');
    const editButton = row.querySelector('[data-notification-edit]');
    const cancelButton = row.querySelector('[data-notification-cancel]');
    const canManage = status === 'pending' || status === 'failed';

    if (statusElement) {
        NOTIFICATION_STATUSES.forEach(function (statusName) {
            statusElement.classList.remove('notificationStatus--' + statusName);
        });
        statusElement.classList.add('notificationStatus--' + status);
        statusElement.textContent = translate('notifications.status.' + status);
    }

    if (editButton) {
        editButton.disabled = !canManage;
    }

    if (cancelButton) {
        cancelButton.disabled = !canManage;
    }
    row.dispatchEvent(new CustomEvent('notification:status-changed', { bubbles: true }));
}

export function initNotificationCenter(signal) {
    const page = document.querySelector('.notificationsContent');

    if (!page) {
        return;
    }

    const modal = document.getElementById('notificationEditModal');
    const form = document.getElementById('notificationEditForm');
    const notificationId = document.getElementById('notificationEditId');
    const offsetValue = document.getElementById('notificationOffsetValue');
    const offsetUnit = document.getElementById('notificationOffsetUnit');
    const taskTitle = document.getElementById('notificationEditTask');
    const taskDue = document.getElementById('notificationEditDue');
    const modalMessage = document.getElementById('notificationEditMessage');
    const pageMessage = document.getElementById('notificationsPageMessage');
    const closeButton = document.getElementById('closeNotificationEdit');
    const dismissButton = document.getElementById('dismissNotificationEdit');
    const saveButton = document.getElementById('saveNotificationEdit');
    const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
    let activeRow = null;
    let previouslyFocusedElement = null;

    if (!modal || !form || !notificationId || !offsetValue || !offsetUnit || !csrfInput) {
        return;
    }

    function setPageMessage(message, type) {
        pageMessage.textContent = message;
        pageMessage.hidden = message.trim() === '';
        pageMessage.classList.toggle('is-success', type === 'success');
        pageMessage.classList.toggle('is-error', type === 'error');
    }

    setPageMessage('', '');

    function setModalMessage(message) {
        modalMessage.textContent = message;
    }



    function openEditor(button) {
        activeRow = button.closest('[data-notification-id]');

        if (!activeRow) {
            return;
        }

        previouslyFocusedElement = button;
        notificationId.value = activeRow.dataset.notificationId;
        offsetValue.value = button.dataset.offsetValue;
        offsetUnit.value = button.dataset.offsetUnit;
        taskTitle.textContent = button.dataset.taskTitle;
        taskDue.textContent = button.dataset.taskDue;
        setModalMessage('');
        if (!modal.open) {
            modal.showModal();
        }
        document.body.classList.add('notification-modal-open');
        window.requestAnimationFrame(function () {
            offsetValue.focus();
            offsetValue.select();
        });
    }

    function closeEditor() {
        if (modal.open) {
            modal.close();
        }
        document.body.classList.remove('notification-modal-open');
        setModalMessage('');

        if (previouslyFocusedElement) {
            previouslyFocusedElement.focus();
        }

        previouslyFocusedElement = null;
        activeRow = null;
    }

    page.addEventListener('click', async function (event) {
        const editButton = event.target.closest('[data-notification-edit]');

        if (editButton && !editButton.disabled) {
            openEditor(editButton);
            return;
        }

        const cancelButton = event.target.closest('[data-notification-cancel]');

        if (!cancelButton || cancelButton.disabled || cancelButton.dataset.processing === 'true') {
            return;
        }

        const row = cancelButton.closest('[data-notification-id]');

        if (!row) {
            return;
        }
        cancelButton.dataset.processing = 'true';
        try {
            const confirmed = await confirmAction(translate('notifications.cancel_confirm'), {
                signal, destructive: true, confirmLabel: translate('dialog.cancel_notification'),
            });
            if (!confirmed || signal?.aborted || !row.isConnected) return;
            cancelButton.disabled = true;
            setPageMessage('', '');
            await cancelNotification(row.dataset.notificationId, csrfInput.value);
            if (signal?.aborted || !row.isConnected) return;
            // The cancel endpoint returns success only, not a notification payload.
            setRowStatus(row, 'cancelled');
            setPageMessage(translate('notifications.cancelled'), 'success');
        } catch (error) {
            if (!signal?.aborted && row.isConnected) {
                cancelButton.disabled = false;
                setPageMessage(error.message || translate('notifications.cancel_failed', {}, 'The notification could not be cancelled.'), 'error');
            }
        } finally {
            delete cancelButton.dataset.processing;
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        setModalMessage('');
        saveButton.disabled = true;
        saveButton.textContent = translate('common.saving');

        try {
            const result = await updateNotification(new FormData(form));
            const updatedNotification = result.notification;

            if (activeRow) {
                const schedule = activeRow.querySelector('[data-notification-schedule]');
                const offset = activeRow.querySelector('[data-notification-offset]');
                const editButton = activeRow.querySelector('[data-notification-edit]');

                if (schedule) {
                    schedule.textContent = updatedNotification.formatted_remind_at;
                }

                if (offset) {
                    offset.textContent = formatOffset(
                        updatedNotification.offset_value,
                        updatedNotification.offset_unit
                    );
                }

                if (editButton) {
                    editButton.dataset.offsetValue = updatedNotification.offset_value;
                    editButton.dataset.offsetUnit = updatedNotification.offset_unit;
                }

                setRowStatus(activeRow, updatedNotification.status);
            }

            closeEditor();
            setPageMessage(result.message, 'success');
        } catch (error) {
            setModalMessage(error.message || translate('notifications.update_failed', {}, 'The notification could not be updated.'));
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = translate('notifications.editor.save_changes');
        }
    });

    closeButton.addEventListener('click', closeEditor);
    dismissButton.addEventListener('click', closeEditor);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeEditor();
        }
    });
    modal.addEventListener('cancel', function (event) {
        event.preventDefault();
        closeEditor();
    }, signal ? { signal } : undefined);
}
