(function() {
  /*
Inspired by dribble.com/shots/1507858-Dashboard
*/
  var userMenuToggle = document.getElementById('userMenuToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  var profileMenu = document.querySelector('.profileMenu');
  var navigationItems = document.querySelectorAll('.navigation-list li');
  var themeToggle = document.getElementById('themeToggle');
  var themeIcon = document.getElementById('themeIcon');
  var themeLabel = document.getElementById('themeLabel');
  var taskModal = document.getElementById('taskModal');
  var openTaskModalButton = document.getElementById('openTaskModal');
  var quickAddTaskButton = document.getElementById('newTaskBtn');
  var quickTaskInput = document.getElementById('taskNameInput');
  var closeTaskModalButton = document.getElementById('closeTaskModal');
  var newTaskForm = document.getElementById('newTaskForm');
  var taskModalText = document.getElementById('taskModalText');
  var taskDueAt = document.getElementById('taskDueAt');
  var taskDateTimePanel = document.getElementById('taskDateTimePanel');
  var setTaskDateButton = document.getElementById('setTaskDateButton');
  var setTaskReminderButton = document.getElementById('setTaskReminderButton');
  var setTaskRepeatButton = document.getElementById('setTaskRepeatButton');
  var taskModalMessage = document.getElementById('taskModalMessage');
  var saveTaskButton = document.getElementById('saveTaskButton');
  var lastTaskModalTrigger = null;

  function applyTheme(isDarkMode, shouldSave) {
    document.documentElement.classList.toggle('dark-mode', isDarkMode);

    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', String(isDarkMode));
    }

    if (themeLabel) {
      themeLabel.textContent = isDarkMode ? 'Light Mode' : 'Dark Mode';
    }

    if (themeIcon) {
      themeIcon.classList.toggle('fa-moon', !isDarkMode);
      themeIcon.classList.toggle('fa-sun', isDarkMode);
    }

    if (shouldSave) {
      try {
        localStorage.setItem('mytodo-theme', isDarkMode ? 'dark' : 'light');
      } catch (error) {
        // Theme switching still works when browser storage is unavailable.
      }
    }
  }

  applyTheme(document.documentElement.classList.contains('dark-mode'), false);

  if (themeToggle) {
    themeToggle.addEventListener('click', function() {
      var isDarkMode = !document.documentElement.classList.contains('dark-mode');
      applyTheme(isDarkMode, true);
    });
  }

  function activateNavigationItem(activeItem) {
    navigationItems.forEach(function(item) {
      var control = item.querySelector('a, button');
      var isActive = item === activeItem;

      item.classList.toggle('active', isActive);

      if (control) {
        if (isActive) {
          control.setAttribute('aria-current', 'page');
        } else {
          control.removeAttribute('aria-current');
        }
      }
    });
  }

  if (navigationItems.length) {
    var savedNavigationId = null;
    var serverActiveItem = document.querySelector('.navigation-list li.active');

    try {
      savedNavigationId = sessionStorage.getItem('activeNavigationItem');
    } catch (error) {
      savedNavigationId = null;
    }

    if (serverActiveItem) {
      activateNavigationItem(serverActiveItem);
    } else if (savedNavigationId) {
      var savedNavigationItem = document.querySelector(
        '.navigation-list li[data-nav-id="' + savedNavigationId + '"]'
      );

      if (savedNavigationItem) {
        activateNavigationItem(savedNavigationItem);
      }
    }

    navigationItems.forEach(function(item) {
      var control = item.querySelector('a, button');

      if (!control) {
        return;
      }

      control.addEventListener('click', function() {
        activateNavigationItem(item);

        try {
          sessionStorage.setItem('activeNavigationItem', item.dataset.navId);
        } catch (error) {
          // The active color still works when browser storage is unavailable.
        }
      });
    });
  }

  function setTaskModalMessage(message) {
    if (taskModalMessage) {
      taskModalMessage.textContent = message;
    }
  }

  function setDateTimePanelState(isOpen) {
    if (!taskDateTimePanel || !setTaskDateButton) {
      return;
    }

    taskDateTimePanel.hidden = !isOpen;
    setTaskDateButton.setAttribute('aria-expanded', String(isOpen));

    if (isOpen && taskDueAt) {
      taskDueAt.focus();
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

    if (quickTaskInput && quickTaskInput.value.trim() !== '') {
      taskModalText.value = quickTaskInput.value.trim();
    }

    window.requestAnimationFrame(function() {
      taskModalText.focus();
    });
  }

  function closeTaskModal() {
    if (!taskModal) {
      return;
    }

    taskModal.hidden = true;
    document.body.classList.remove('task-modal-open');
    setTaskModalMessage('');

    if (lastTaskModalTrigger && typeof lastTaskModalTrigger.focus === 'function') {
      lastTaskModalTrigger.focus();
    }
  }

  if (taskDueAt) {
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    taskDueAt.min = now.toISOString().slice(0, 16);
  }

  if (openTaskModalButton) {
    openTaskModalButton.addEventListener('click', function() {
      openTaskModal(openTaskModalButton);
    });
  }

  if (quickAddTaskButton) {
    quickAddTaskButton.addEventListener('click', function() {
      openTaskModal(quickAddTaskButton);
    });
  }

  if (quickTaskInput) {
    quickTaskInput.addEventListener('keydown', function(event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        openTaskModal(quickTaskInput);
      }
    });
  }

  if (closeTaskModalButton) {
    closeTaskModalButton.addEventListener('click', closeTaskModal);
  }

  if (taskModal) {
    taskModal.addEventListener('click', function(event) {
      if (event.target === taskModal) {
        closeTaskModal();
      }
    });
  }

  if (setTaskDateButton) {
    setTaskDateButton.addEventListener('click', function() {
      var isOpen = setTaskDateButton.getAttribute('aria-expanded') === 'true';
      setDateTimePanelState(!isOpen);
    });
  }

  if (setTaskReminderButton) {
    setTaskReminderButton.addEventListener('click', function() {
      setToggleButtonState(setTaskReminderButton);
    });
  }

  if (setTaskRepeatButton) {
    setTaskRepeatButton.addEventListener('click', function() {
      setToggleButtonState(setTaskRepeatButton);
    });
  }

  if (newTaskForm) {
    newTaskForm.addEventListener('submit', function(event) {
      event.preventDefault();

      var taskTitle = taskModalText ? taskModalText.value.trim() : '';
      var dueAtValue = taskDueAt ? taskDueAt.value : '';

      if (taskTitle.length < 3) {
        setTaskModalMessage('Task text must be at least 3 characters long.');
        taskModalText.focus();
        return;
      }

      if (!dueAtValue) {
        setTaskModalMessage('Please set a date and time for this task.');
        setDateTimePanelState(true);
        return;
      }

      var formData = new FormData(newTaskForm);
      formData.set('action', 'newTask');
      formData.set('task_title', taskTitle);

      saveTaskButton.disabled = true;
      saveTaskButton.textContent = 'Saving...';
      setTaskModalMessage('');

      fetch('bootstrap/ajaxHandler.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
        .then(function(response) {
          return response.text().then(function(responseText) {
            if (!response.ok || responseText.trim() !== '1') {
              throw new Error(responseText || 'The task could not be saved.');
            }

            return responseText;
          });
        })
        .then(function() {
          window.location.reload();
        })
        .catch(function(error) {
          setTaskModalMessage(error.message || 'The task could not be saved.');
          saveTaskButton.disabled = false;
          saveTaskButton.textContent = 'Save';
        });
    });
  }

  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && taskModal && !taskModal.hidden) {
      closeTaskModal();
    }
  });

  if (!userMenuToggle || !profileDropdown || !profileMenu) {
    return;
  }

  function setProfileMenuState(isOpen) {
    userMenuToggle.setAttribute('aria-expanded', String(isOpen));
    profileDropdown.hidden = !isOpen;
  }

  userMenuToggle.addEventListener('click', function() {
    var isOpen = userMenuToggle.getAttribute('aria-expanded') === 'true';
    setProfileMenuState(!isOpen);
  });

  document.addEventListener('click', function(event) {
    if (!profileMenu.contains(event.target)) {
      setProfileMenuState(false);
    }
  });

  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && userMenuToggle.getAttribute('aria-expanded') === 'true') {
      setProfileMenuState(false);
      userMenuToggle.focus();
    }
  });

}).call(this);

//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiPGFub255bW91cz4iXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7RUFBQTs7OztBQUFBIiwic291cmNlc0NvbnRlbnQiOlsiIyMjXG5JbnNwaXJlZCBieSBkcmliYmxlLmNvbS9zaG90cy8xNTA3ODU4LURhc2hib2FyZFxuIyMjIl19
//# sourceURL=coffeescript
