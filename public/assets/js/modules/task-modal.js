import { createTask } from '../services/task-service.js';

export function initTaskModal(dateTimePicker, reminderPicker, repeatPicker) {
    // متغیرهای Task Modal
    const taskModal = document.getElementById('taskModal');
    const openTaskModalButton = document.getElementById('openTaskModal');
    const closeTaskModalButton = document.getElementById('closeTaskModal');
    const newTaskForm = document.getElementById('newTaskForm');
    const taskModalText = document.getElementById('taskModalText');
    const taskModalMessage = document.getElementById('taskModalMessage');
    const saveTaskButton = document.getElementById('saveTaskButton');
    let lastTaskModalTrigger = null;

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

        if (lastTaskModalTrigger && typeof lastTaskModalTrigger.focus === 'function') {
            lastTaskModalTrigger.focus();
        }
    }

    if (openTaskModalButton) {
        openTaskModalButton.addEventListener('click', function () {
            openTaskModal(openTaskModalButton);
        });
    }

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
                setTaskModalMessage('Task text must be at least 3 characters long.');
                taskModalText.focus();
                return;
            }

            const repeatValidationMessage = repeatPicker ? repeatPicker.validate() : '';

            if (repeatValidationMessage) {
                setTaskModalMessage(repeatValidationMessage);
                return;
            }

            const formData = new FormData(newTaskForm);
            formData.set('action', 'newTask');
            formData.set('task_title', taskTitle);

            saveTaskButton.disabled = true;
            saveTaskButton.textContent = 'Saving...';
            setTaskModalMessage('');

            createTask(formData)
                .then(function () {
                    window.location.reload();
                })
                .catch(function (error) {
                    setTaskModalMessage(error.message || 'The task could not be saved.');
                    saveTaskButton.disabled = false;
                    saveTaskButton.textContent = 'Save';
                });
        });
    }

}


