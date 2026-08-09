import {
    startOfDay,
    addDays,
    datesAreEqual,
    formatDateKey,
    parseDateKey
} from '../utils/date-utils.js';

export function initDateTimePicker() {
    var setTaskDateButton = document.getElementById('setTaskDateButton');
    var taskDueAt = document.getElementById('taskDueAt');
    var taskHasTime = document.getElementById('taskHasTime');
    var taskDateSummary = document.getElementById('taskDateSummary');
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
    var lastDateTimeModalTrigger = null;
    var calendarViewDate = null;
    var draftSelectedDate = null;
    var draftHasTime = true;
    var committedDateMode = 'unset';

    function setDateTimeModalMessage(message) {
        if (dateTimeModalMessage) {
            dateTimeModalMessage.textContent = message;
        }
    }

    function announceDueDateChange() {
        document.dispatchEvent(new CustomEvent('task:due-date-changed', {
            detail: {
                dueAt: taskDueAt ? taskDueAt.value : '',
                hasTime: taskHasTime ? taskHasTime.value : '0'
            }
        }));
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

        quickDateRadios.forEach(function (radio) {
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

        [taskTimeHour, taskTimeMinute, taskTimePeriod].forEach(function (control) {
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

            dayButton.addEventListener('click', function (event) {
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

        window.requestAnimationFrame(function () {
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
            announceDueDateChange();
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
            var invalidTimeControl = [taskTimeHour, taskTimeMinute].find(function (control) {
                return control && !control.checkValidity();
            });

            if (invalidTimeControl) {
                setDateTimeModalMessage('Enter an hour from 1 to 12 and minutes from 0 to 59.');
                invalidTimeControl.focus();
                return;
            }

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
        announceDueDateChange();
        closeDateTimeModal();
    }

    if (setTaskDateButton) {
        setTaskDateButton.addEventListener('click', function () {
            openDateTimeModal(setTaskDateButton);
        });
    }

    if (closeDateTimeModalButton) {
        closeDateTimeModalButton.addEventListener('click', function () {
            closeDateTimeModal();
        });
    }

    if (cancelDateTimeButton) {
        cancelDateTimeButton.addEventListener('click', function () {
            closeDateTimeModal();
        });
    }

    if (applyDateTimeButton) {
        applyDateTimeButton.addEventListener('click', applyDateTimeSelection);
    }

    if (dateTimeModal) {
        dateTimeModal.addEventListener('click', function (event) {
            if (event.target === dateTimeModal) {
                closeDateTimeModal();
            }
        });
    }

    if (previousCalendarMonthButton) {
        previousCalendarMonthButton.addEventListener('click', function () {
            calendarViewDate = new Date(
                calendarViewDate.getFullYear(),
                calendarViewDate.getMonth() - 1,
                1
            );
            renderCalendar();
        });
    }

    if (nextCalendarMonthButton) {
        nextCalendarMonthButton.addEventListener('click', function () {
            calendarViewDate = new Date(
                calendarViewDate.getFullYear(),
                calendarViewDate.getMonth() + 1,
                1
            );
            renderCalendar();
        });
    }

    quickDateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
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

    [setTimeYes, setTimeNo].forEach(function (radio) {
        if (!radio) {
            return;
        }

        radio.addEventListener('change', function () {
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
        setTimeSection.addEventListener('pointerdown', function (event) {
            if (!draftSelectedDate) {
                event.preventDefault();
                setDateTimeModalMessage('Please set a date first.');
            }
        }, true);
    }

    return {
        isOpen: function () {
            return Boolean(dateTimeModal && !dateTimeModal.hidden);
        },
        close: function (shouldRestoreFocus) {
            closeDateTimeModal(shouldRestoreFocus);
        }
    };
}
