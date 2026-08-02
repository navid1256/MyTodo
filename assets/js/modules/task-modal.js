import { createTask } from '../services/task-service.js';

export function initTaskModal(dateTimePicker) {
    // متغیرهای Task Modal
    var taskModal = document.getElementById('taskModal');
    var openTaskModalButton = document.getElementById('openTaskModal');
    var closeTaskModalButton = document.getElementById('closeTaskModal');
    var newTaskForm = document.getElementById('newTaskForm');
    var taskModalText = document.getElementById('taskModalText');
    var setTaskReminderButton = document.getElementById('setTaskReminderButton');
    var setTaskRepeatButton = document.getElementById('setTaskRepeatButton');
    var taskModalMessage = document.getElementById('taskModalMessage');
    var saveTaskButton = document.getElementById('saveTaskButton');
    var lastTaskModalTrigger = null;
    
    // توابع و Event Listenerها

    function setTaskModalMessage(message) {
        if (taskModalMessage) {
            taskModalMessage.textContent = message;
        }
    }

    function setToggleButtonState(button) {
        if (!button) {
            return;
        }

        var isPressed = button.getAttribute('aria-pressed') === 'true';
        button.setAttribute('aria-pressed', String(!isPressed));
    }

    function openTaskModal(trigger) {
        if (!taskModal || !taskModalText) {
            return;
        }

        lastTaskModalTrigger = trigger || document.activeElement;
        taskModal.hidden = false;
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

        taskModal.hidden = true;
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
    }

    if (setTaskReminderButton) {
        setTaskReminderButton.addEventListener('click', function () {
            setToggleButtonState(setTaskReminderButton);
        });
    }

    if (setTaskRepeatButton) {
        setTaskRepeatButton.addEventListener('click', function () {
            setToggleButtonState(setTaskRepeatButton);
        });
    }

    if (newTaskForm) {
        newTaskForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var taskTitle = taskModalText ? taskModalText.value.trim() : '';

            if (taskTitle.length < 3) {
                setTaskModalMessage('Task text must be at least 3 characters long.');
                taskModalText.focus();
                return;
            }

            var formData = new FormData(newTaskForm);
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

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (dateTimePicker && dateTimePicker.isOpen()) {
            dateTimePicker.close();
        } else if (taskModal && !taskModal.hidden) {
            closeTaskModal();
        }
    });

}


