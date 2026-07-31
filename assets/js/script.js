(function() {

  var userMenuToggle = document.getElementById('userMenuToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  var profileMenu = document.querySelector('.profileMenu');
  var navigationItems = document.querySelectorAll('.navigation-list li');
  var themeToggle = document.getElementById('themeToggle');
  var themeIcon = document.getElementById('themeIcon');
  var themeLabel = document.getElementById('themeLabel');
  var taskModal = document.getElementById('taskModal');
  var openTaskModalButton = document.getElementById('openTaskModal');
  var closeTaskModalButton = document.getElementById('closeTaskModal');
  var newTaskForm = document.getElementById('newTaskForm');
  var taskModalText = document.getElementById('taskModalText');
  var taskDueAt = document.getElementById('taskDueAt');
  var taskHasTime = document.getElementById('taskHasTime');
  var taskDateSummary = document.getElementById('taskDateSummary');
  var setTaskDateButton = document.getElementById('setTaskDateButton');
  var setTaskReminderButton = document.getElementById('setTaskReminderButton');
  var setTaskRepeatButton = document.getElementById('setTaskRepeatButton');
  var taskModalMessage = document.getElementById('taskModalMessage');
  var saveTaskButton = document.getElementById('saveTaskButton');
  var dateTimeModal = document.getElementById('dateTimeModal');
  var closeDateTimeModalButton = document.getElementById('closeDateTimeModal');
  var previousCalendarMonthButton = document.getElementById('previousCalendarMonth');
  var nextCalendarMonthButton = document.getElementById('nextCalendarMonth');
  var calendarMonthLabel = document.getElementById('dateTimeModalTitle');
  var calendarDays = document.getElementById('calendarDays');
  var quickDateRadios = document.querySelectorAll('input[name="quick_task_date"]');
  var setTimeSection = document.getElementById('setTimeSection');
  var setTimeYes = document.getElementById('setTimeYes');
  var setTimeNo = document.getElementById('setTimeNo');
  var taskTimeHour = document.getElementById('taskTimeHour');
  var taskTimeMinute = document.getElementById('taskTimeMinute');
  var taskTimePeriod = document.getElementById('taskTimePeriod');
  var dateTimeModalMessage = document.getElementById('dateTimeModalMessage');
  var cancelDateTimeButton = document.getElementById('cancelDateTimeButton');
  var applyDateTimeButton = document.getElementById('applyDateTimeButton');
  var lastTaskModalTrigger = null;
  var lastDateTimeModalTrigger = null;
  var calendarViewDate = null;
  var draftSelectedDate = null;
  var draftHasTime = true;
  var committedDateMode = 'unset';

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

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function addDays(date, numberOfDays) {
    var result = startOfDay(date);
    result.setDate(result.getDate() + numberOfDays);
    return result;
  }

  function datesAreEqual(firstDate, secondDate) {
    return Boolean(firstDate && secondDate)
      && firstDate.getFullYear() === secondDate.getFullYear()
      && firstDate.getMonth() === secondDate.getMonth()
      && firstDate.getDate() === secondDate.getDate();
  }

  function formatDateKey(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function parseDateKey(dateKey) {
    var parts = dateKey.split('-').map(Number);

    if (parts.length !== 3 || parts.some(function(part) { return !Number.isInteger(part); })) {
      return null;
    }

    var parsedDate = new Date(parts[0], parts[1] - 1, parts[2]);
    return formatDateKey(parsedDate) === dateKey ? parsedDate : null;
  }

  function setDateTimeModalMessage(message) {
    if (dateTimeModalMessage) {
      dateTimeModalMessage.textContent = message;
    }
  }

  function setPickerTime(hour24, minute) {
    if (!taskTimeHour || !taskTimeMinute || !taskTimePeriod) {
      return;
    }

    var period = hour24 >= 12 ? 'PM' : 'AM';
    var hour12 = hour24 % 12 || 12;
    taskTimeHour.value = String(hour12);
    taskTimeMinute.value = String(minute);
    taskTimePeriod.value = period;
  }

  function readPickerTime() {
    var hour12 = Number(taskTimeHour ? taskTimeHour.value : 12);
    var minute = Number(taskTimeMinute ? taskTimeMinute.value : 0);
    var period = taskTimePeriod ? taskTimePeriod.value : 'AM';
    var hour24 = hour12 % 12;

    if (period === 'PM') {
      hour24 += 12;
    }

    return {
      hour24: hour24,
      hour12: hour12,
      minute: minute,
      period: period
    };
  }

  function updateQuickDateSelection() {
    var today = startOfDay(new Date());
    var tomorrow = addDays(today, 1);
    var quickValue = null;

    if (!draftSelectedDate) {
      quickValue = 'no-date';
    } else if (datesAreEqual(draftSelectedDate, today)) {
      quickValue = 'today';
    } else if (datesAreEqual(draftSelectedDate, tomorrow)) {
      quickValue = 'tomorrow';
    }

    quickDateRadios.forEach(function(radio) {
      radio.checked = radio.value === quickValue;
    });
  }

  function updateTimeControls() {
    var hasDate = Boolean(draftSelectedDate);
    var timeIsEnabled = hasDate && draftHasTime;

    if (setTimeSection) {
      setTimeSection.classList.toggle('is-disabled', !hasDate);
      setTimeSection.setAttribute('aria-disabled', String(!hasDate));
    }

    if (setTimeYes) {
      setTimeYes.disabled = !hasDate;
      setTimeYes.checked = hasDate && draftHasTime;
    }

    if (setTimeNo) {
      setTimeNo.disabled = !hasDate;
      setTimeNo.checked = !hasDate || !draftHasTime;
    }

    [taskTimeHour, taskTimeMinute, taskTimePeriod].forEach(function(control) {
      if (control) {
        control.disabled = !timeIsEnabled;
      }
    });
  }

  function selectCalendarDate(date) {
    var dateWasEmpty = !draftSelectedDate;
    draftSelectedDate = startOfDay(date);
    calendarViewDate = new Date(date.getFullYear(), date.getMonth(), 1);

    if (dateWasEmpty) {
      draftHasTime = true;
    }

    setDateTimeModalMessage('');
    updateQuickDateSelection();
    updateTimeControls();
    renderCalendar();
  }

  function renderCalendar() {
    if (!calendarDays || !calendarMonthLabel) {
      return;
    }

    if (!calendarViewDate) {
      var today = new Date();
      calendarViewDate = new Date(today.getFullYear(), today.getMonth(), 1);
    }

    var viewYear = calendarViewDate.getFullYear();
    var viewMonth = calendarViewDate.getMonth();
    var firstDayOfMonth = new Date(viewYear, viewMonth, 1);
    var firstGridDate = addDays(firstDayOfMonth, -firstDayOfMonth.getDay());
    var todayDate = startOfDay(new Date());

    calendarMonthLabel.textContent = new Intl.DateTimeFormat('en-US', {
      month: 'long',
      year: 'numeric'
    }).format(firstDayOfMonth);
    calendarDays.textContent = '';

    for (var dayIndex = 0; dayIndex < 42; dayIndex += 1) {
      var calendarDate = addDays(firstGridDate, dayIndex);
      var dayButton = document.createElement('button');
      var isSelected = datesAreEqual(calendarDate, draftSelectedDate);

      dayButton.type = 'button';
      dayButton.className = 'calendarDay';
      dayButton.textContent = String(calendarDate.getDate());
      dayButton.dataset.date = formatDateKey(calendarDate);
      dayButton.setAttribute('role', 'gridcell');
      dayButton.setAttribute('aria-selected', String(isSelected));
      dayButton.setAttribute(
        'aria-label',
        new Intl.DateTimeFormat('en-US', {
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }).format(calendarDate)
      );

      if (calendarDate.getMonth() !== viewMonth) {
        dayButton.classList.add('outsideMonth');
      }

      if (datesAreEqual(calendarDate, todayDate)) {
        dayButton.classList.add('today');
      }

      if (isSelected) {
        dayButton.classList.add('selected');
      }

      dayButton.addEventListener('click', function(event) {
        var selectedDate = parseDateKey(event.currentTarget.dataset.date);

        if (selectedDate) {
          selectCalendarDate(selectedDate);
        }
      });
      calendarDays.appendChild(dayButton);
    }
  }

  function closeDateTimeModal(shouldRestoreFocus) {
    if (!dateTimeModal) {
      return;
    }

    dateTimeModal.hidden = true;
    setDateTimeModalMessage('');

    if (setTaskDateButton) {
      setTaskDateButton.setAttribute('aria-expanded', 'false');
    }

    if (shouldRestoreFocus !== false
      && lastDateTimeModalTrigger
      && typeof lastDateTimeModalTrigger.focus === 'function') {
      lastDateTimeModalTrigger.focus();
    }
  }

  function openDateTimeModal(trigger) {
    if (!dateTimeModal) {
      return;
    }

    var today = startOfDay(new Date());
    var storedDate = taskDueAt && taskDueAt.value
      ? parseDateKey(taskDueAt.value.slice(0, 10))
      : null;

    lastDateTimeModalTrigger = trigger || document.activeElement;

    if (committedDateMode === 'no-date') {
      draftSelectedDate = null;
      draftHasTime = false;
    } else if (storedDate) {
      draftSelectedDate = storedDate;
      draftHasTime = Boolean(taskHasTime && taskHasTime.value === '1');

      if (draftHasTime) {
        setPickerTime(
          Number(taskDueAt.value.slice(11, 13)),
          Number(taskDueAt.value.slice(14, 16))
        );
      } else {
        setPickerTime(new Date().getHours(), new Date().getMinutes());
      }
    } else {
      draftSelectedDate = today;
      draftHasTime = true;
      setPickerTime(new Date().getHours(), new Date().getMinutes());
    }

    calendarViewDate = new Date(
      (draftSelectedDate || today).getFullYear(),
      (draftSelectedDate || today).getMonth(),
      1
    );
    updateQuickDateSelection();
    updateTimeControls();
    renderCalendar();
    setDateTimeModalMessage('');
    dateTimeModal.hidden = false;

    if (setTaskDateButton) {
      setTaskDateButton.setAttribute('aria-expanded', 'true');
    }

    window.requestAnimationFrame(function() {
      if (closeDateTimeModalButton) {
        closeDateTimeModalButton.focus();
      }
    });
  }

  function applyDateTimeSelection() {
    if (!taskDueAt || !taskHasTime || !taskDateSummary) {
      return;
    }

    if (!draftSelectedDate) {
      taskDueAt.value = '';
      taskHasTime.value = '0';
      taskDateSummary.textContent = 'No date';
      committedDateMode = 'no-date';
      closeDateTimeModal();
      return;
    }

    var today = startOfDay(new Date());
    var tomorrow = addDays(today, 1);
    var dateLabel;

    if (datesAreEqual(draftSelectedDate, today)) {
      dateLabel = 'Today';
    } else if (datesAreEqual(draftSelectedDate, tomorrow)) {
      dateLabel = 'Tomorrow';
    } else {
      dateLabel = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      }).format(draftSelectedDate);
    }

    if (draftHasTime) {
      var selectedTime = readPickerTime();
      taskDueAt.value = formatDateKey(draftSelectedDate)
        + 'T'
        + String(selectedTime.hour24).padStart(2, '0')
        + ':'
        + String(selectedTime.minute).padStart(2, '0');
      taskHasTime.value = '1';
      taskDateSummary.textContent = dateLabel
        + ' · '
        + String(selectedTime.hour12).padStart(2, '0')
        + ':'
        + String(selectedTime.minute).padStart(2, '0')
        + ' '
        + selectedTime.period;
    } else {
      taskDueAt.value = formatDateKey(draftSelectedDate) + 'T00:00';
      taskHasTime.value = '0';
      taskDateSummary.textContent = dateLabel + ' · No time';
    }

    committedDateMode = 'date';
    closeDateTimeModal();
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

    window.requestAnimationFrame(function() {
      taskModalText.focus();
    });
  }

  function closeTaskModal() {
    if (!taskModal) {
      return;
    }

    if (dateTimeModal && !dateTimeModal.hidden) {
      closeDateTimeModal(false);
    }

    taskModal.hidden = true;
    document.body.classList.remove('task-modal-open');
    setTaskModalMessage('');

    if (lastTaskModalTrigger && typeof lastTaskModalTrigger.focus === 'function') {
      lastTaskModalTrigger.focus();
    }
  }

  if (openTaskModalButton) {
    openTaskModalButton.addEventListener('click', function() {
      openTaskModal(openTaskModalButton);
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
      openDateTimeModal(setTaskDateButton);
    });
  }

  if (closeDateTimeModalButton) {
    closeDateTimeModalButton.addEventListener('click', function() {
      closeDateTimeModal();
    });
  }

  if (cancelDateTimeButton) {
    cancelDateTimeButton.addEventListener('click', function() {
      closeDateTimeModal();
    });
  }

  if (applyDateTimeButton) {
    applyDateTimeButton.addEventListener('click', applyDateTimeSelection);
  }

  if (dateTimeModal) {
    dateTimeModal.addEventListener('click', function(event) {
      if (event.target === dateTimeModal) {
        closeDateTimeModal();
      }
    });
  }

  if (previousCalendarMonthButton) {
    previousCalendarMonthButton.addEventListener('click', function() {
      calendarViewDate = new Date(
        calendarViewDate.getFullYear(),
        calendarViewDate.getMonth() - 1,
        1
      );
      renderCalendar();
    });
  }

  if (nextCalendarMonthButton) {
    nextCalendarMonthButton.addEventListener('click', function() {
      calendarViewDate = new Date(
        calendarViewDate.getFullYear(),
        calendarViewDate.getMonth() + 1,
        1
      );
      renderCalendar();
    });
  }

  quickDateRadios.forEach(function(radio) {
    radio.addEventListener('change', function() {
      var today = startOfDay(new Date());

      if (radio.value === 'today') {
        selectCalendarDate(today);
      } else if (radio.value === 'tomorrow') {
        selectCalendarDate(addDays(today, 1));
      } else {
        draftSelectedDate = null;
        draftHasTime = false;
        setDateTimeModalMessage('');
        updateQuickDateSelection();
        updateTimeControls();
        renderCalendar();
      }
    });
  });

  [setTimeYes, setTimeNo].forEach(function(radio) {
    if (!radio) {
      return;
    }

    radio.addEventListener('change', function() {
      if (!draftSelectedDate) {
        draftHasTime = false;
        setDateTimeModalMessage('Please set a date first.');
      } else {
        draftHasTime = radio.value === 'yes';
        setDateTimeModalMessage('');
      }

      updateTimeControls();
    });
  });

  if (setTimeSection) {
    setTimeSection.addEventListener('pointerdown', function(event) {
      if (!draftSelectedDate) {
        event.preventDefault();
        setDateTimeModalMessage('Please set a date first.');
      }
    }, true);
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
    if (event.key !== 'Escape') {
      return;
    }

    if (dateTimeModal && !dateTimeModal.hidden) {
      closeDateTimeModal();
    } else if (taskModal && !taskModal.hidden) {
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
