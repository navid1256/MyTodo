import { createTask } from '../services/task-service.js';
import { updateRecurringRule, updateRecurringTask } from '../services/repeat-service.js';
import { translate } from '../utils/i18n.js';

const initializedTaskForms = new WeakSet();

export function initTaskModal(dateTimePicker, reminderPicker, repeatPicker) {
    // متغیرهای Task Modal
    const taskModal = document.getElementById('taskModal');
    const openTaskModalButton = document.getElementById('openTaskModal');
    const closeTaskModalButton = document.getElementById('closeTaskModal');
    const newTaskForm = document.getElementById('newTaskForm');
    const taskModalText = document.getElementById('taskModalText');
    const taskModalMessage = document.getElementById('taskModalMessage');
    const saveTaskButton = document.getElementById('saveTaskButton');
    const taskFormMode = document.getElementById('taskFormMode');
    const taskEditId = document.getElementById('taskEditId');
    const taskEditScope = document.getElementById('taskEditScope');
    const taskEditScopeControl = document.getElementById('taskEditScopeControl');
    const taskEditScopeChoice = document.getElementById('taskEditScopeChoice');
    const taskEditRuleId = document.getElementById('taskEditRuleId');
    let lastTaskModalTrigger = null;

    if (!newTaskForm || initializedTaskForms.has(newTaskForm)) {
        return;
    }
    initializedTaskForms.add(newTaskForm);

    // توابع و Event Listenerها

    function setTaskModalMessage(message) {
        if (taskModalMessage) {
            taskModalMessage.textContent = message;
        }
    }

    function openTaskModal(trigger) {
        if (!taskModal || !taskModalText) {
            return;
        }

        lastTaskModalTrigger = trigger || document.activeElement;
        if (!taskModal.open) {
            taskModal.showModal();
        }
        document.body.classList.add('task-modal-open');
        setTaskModalMessage('');

        window.requestAnimationFrame(function () {
            taskModalText.focus();
        });
    }

    function resetTaskModalState() {
        newTaskForm?.reset();
        if (taskFormMode) taskFormMode.value = 'create';
        if (taskEditId) taskEditId.value = '';
        if (taskEditRuleId) taskEditRuleId.value = '';
        if (taskEditScope) taskEditScope.value = '';
        if (taskEditScopeControl) taskEditScopeControl.hidden = true;
        dateTimePicker?.reset?.();
        reminderPicker?.reset?.();
        repeatPicker?.reset?.();
        repeatPicker?.setEnabled?.(true);
        if (taskModalText) taskModalText.value = '';
    }

    function setEditScope(scope) {
        const value = scope === 'future' ? 'future' : 'single';
        if (taskEditScope) taskEditScope.value = value;
        const isSingle = value === 'single';
        repeatPicker?.setEnabled?.(!isSingle);
    }

    function openEditModal(trigger, payload) {
        if (!payload || !taskModalText) return;
        lastTaskModalTrigger = trigger || document.activeElement;
        if (taskFormMode) taskFormMode.value = 'edit';
        if (taskEditId) taskEditId.value = String(payload.task_id || '');
        if (taskEditScopeControl) taskEditScopeControl.hidden = false;
        if (taskEditScopeChoice) taskEditScopeChoice.value = 'single';
        setEditScope('single');
        taskModalText.value = payload.title || '';
        dateTimePicker?.load?.(payload.due_at || '', Boolean(payload.has_time));
        reminderPicker?.load?.(payload.reminders || []);
        repeatPicker?.load?.(payload.repeat_config || null);
        if (!taskModal.open) taskModal.showModal();
        document.body.classList.add('task-modal-open');
        setTaskModalMessage('');
        window.requestAnimationFrame(() => taskModalText.focus());
    }

    function openRuleEditModal(trigger, payload) {
        if (!payload || !taskModalText) return;
        lastTaskModalTrigger = trigger || document.activeElement;
        if (taskFormMode) taskFormMode.value = 'rule';
        if (taskEditRuleId) taskEditRuleId.value = String(payload.id || '');
        if (taskEditId) taskEditId.value = '';
        if (taskEditScopeControl) taskEditScopeControl.hidden = true;
        if (taskEditScope) taskEditScope.value = '';
        taskModalText.value = payload.title || '';
        dateTimePicker?.load?.(payload.start_at || '', Boolean(payload.has_time));
        reminderPicker?.load?.(payload.reminders || []);
        repeatPicker?.load?.(payload);
        if (!taskModal.open) taskModal.showModal();
        document.body.classList.add('task-modal-open');
        setTaskModalMessage('');
        window.requestAnimationFrame(() => taskModalText.focus());
    }

    function closeTaskModal() {
        if (!taskModal) {
            return;
        }

        if (dateTimePicker && dateTimePicker.isOpen()) {
            dateTimePicker.close(false);
        }

        if (reminderPicker && reminderPicker.isOpen()) {
            reminderPicker.close(false);
        }

        if (repeatPicker && repeatPicker.isOpen()) {
            repeatPicker.close(false);
        }

        if (taskModal.open) {
            taskModal.close();
        }
        document.body.classList.remove('task-modal-open');
        setTaskModalMessage('');
        resetTaskModalState();

        if (lastTaskModalTrigger && typeof lastTaskModalTrigger.focus === 'function') {
            lastTaskModalTrigger.focus();
        }
    }

    if (openTaskModalButton) {
        openTaskModalButton.addEventListener('click', function () {
            resetTaskModalState();
            openTaskModal(openTaskModalButton);
        });
    }

    if (taskEditScopeChoice) {
        taskEditScopeChoice.addEventListener('change', () => setEditScope(taskEditScopeChoice.value));
    }

    document.addEventListener('click', function (event) {
        const editButton = event.target?.closest?.('[data-task-edit]');
        if (!editButton) return;
        const script = editButton.parentElement?.querySelector?.('[data-task-edit-payload]');
        if (!script) return;
        try {
            openEditModal(editButton, JSON.parse(script.textContent || '{}'));
        } catch {
            setTaskModalMessage(translate('task.edit_failed', {}, 'The task could not be loaded.'));
        }
    });

    document.addEventListener('recurring-rule:edit', function (event) {
        openRuleEditModal(event.detail?.trigger, event.detail?.payload);
    });

    if (closeTaskModalButton) {
        closeTaskModalButton.addEventListener('click', closeTaskModal);
    }

    if (taskModal) {
        taskModal.addEventListener('click', function (event) {
            if (event.target === taskModal) {
                closeTaskModal();
            }
        });

        taskModal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeTaskModal();
        });
    }

    if (newTaskForm) {
        newTaskForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const taskTitle = taskModalText ? taskModalText.value.trim() : '';

            if (taskTitle.length < 3) {
                setTaskModalMessage(translate('task.validation.title_too_short', {}, 'Task text must be at least 3 characters long.'));
                taskModalText.focus();
                return;
            }

            const isEdit = taskFormMode?.value === 'edit' || taskFormMode?.value === 'rule';
            const isRuleEdit = taskFormMode?.value === 'rule';
            const editScope = taskEditScope?.value || taskEditScopeChoice?.value || 'single';
            const repeatValidationMessage = !isEdit && repeatPicker ? repeatPicker.validate() : '';

            if (repeatValidationMessage) {
                setTaskModalMessage(repeatValidationMessage);
                return;
            }

            const formData = new FormData(newTaskForm);
            formData.set('action', 'newTask');
            formData.set('task_title', taskTitle);
            if (isEdit) {
                formData.set('mode', 'edit');
                if (isRuleEdit) {
                    formData.set('repeat_rule_id', taskEditRuleId?.value || '');
                    formData.set('mode', 'rule');
                } else {
                    formData.set('scope', editScope);
                    if (editScope === 'single') formData.set('repeat_config', '');
                }
            }

            saveTaskButton.disabled = true;
            saveTaskButton.textContent = translate('common.saving');
            setTaskModalMessage('');

            const request = isRuleEdit
                ? updateRecurringRule(formData)
                : isEdit ? updateRecurringTask(formData) : createTask(formData);
            request.then(function (response) {
                    if (!isEdit) {
                        window.location.reload();
                        return;
                    }
                    if (isRuleEdit) {
                        document.dispatchEvent(new CustomEvent('recurring-rule:updated', {
                            detail: { previousRuleId: taskEditRuleId?.value, response }
                        }));
                        closeTaskModal();
                        return;
                    }
                    const task = response.task || response;
                    const row = document.querySelector('[data-task-id="' + String(task.id || taskEditId?.value) + '"]');
                    const title = row?.querySelector?.('.taskTitle');
                    if (title) title.textContent = task.title || taskTitle;
                    if (Array.isArray(response.deleted_task_ids)) {
                        response.deleted_task_ids.forEach((deletedId) => {
                            document.querySelector('[data-task-id="' + String(deletedId) + '"]')?.remove();
                        });
                    }
                    closeTaskModal();
                })
                .catch(function (error) {
                    setTaskModalMessage(error.message || translate('task.save_failed', {}, 'The task could not be saved.'));
                    saveTaskButton.disabled = false;
                    saveTaskButton.textContent = translate('common.save');
                });
        });
    }

}


