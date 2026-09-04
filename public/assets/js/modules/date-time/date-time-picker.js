import {
    startOfDay,
    addDays,
    datesAreEqual,
    formatDateKey,
    parseDateKey
} from '../../utils/date-utils.js';
import {
    formatCalendarDate,
    getActiveCalendarSystem,
    getCalendarMonthStart,
    getCalendarNavigation
} from '../../utils/calendar-core.js';

import {
    convertTo12Hour,
    convertTo24Hour,
    normalizeTimeInput,
} from '../../utils/time-utils.js';

import {
    renderDateTimeCalendar,
    changeCalendarMonth
} from './date-time-calendar.js';

export function initDateTimePicker() {
    const setTaskDateButton = document.getElementById('setTaskDateButton');
    const taskDueAt = document.getElementById('taskDueAt');
    const taskHasTime = document.getElementById('taskHasTime');
    const taskDateSummary = document.getElementById('taskDateSummary');
    const dateTimeModal = document.getElementById('dateTimeModal');
    const closeDateTimeModalButton = document.getElementById('closeDateTimeModal');
    const previousCalendarMonthButton = document.getElementById('previousCalendarMonth');
    const nextCalendarMonthButton = document.getElementById('nextCalendarMonth');
    const calendarMonthLabel = document.getElementById('dateTimeModalTitle');
    const calendarDays = document.getElementById('calendarDays');
    const quickDateRadios = document.querySelectorAll('input[name="quick_task_date"]');
    const setTimeSection = document.getElementById('setTimeSection');
    const setTimeYes = document.getElementById('setTimeYes');
    const setTimeNo = document.getElementById('setTimeNo');
    const taskTimeHour = document.getElementById('taskTimeHour');
    const taskTimeMinute = document.getElementById('taskTimeMinute');
    const taskTimePeriod = document.getElementById('taskTimePeriod');
    const dateTimeModalMessage = document.getElementById('dateTimeModalMessage');
    const cancelDateTimeButton = document.getElementById('cancelDateTimeButton');
    const applyDateTimeButton = document.getElementById('applyDateTimeButton');
    let lastDateTimeModalTrigger = null;
    let calendarViewDate = null;
    let draftSelectedDate = null;
    let draftHasTime = true;
    let committedDateMode = 'unset';
    const calendarNavigation = getCalendarNavigation();

    if (previousCalendarMonthButton) {
        previousCalendarMonthButton.setAttribute('aria-label', calendarNavigation.left.label);
    }

    if (nextCalendarMonthButton) {
        nextCalendarMonthButton.setAttribute('aria-label', calendarNavigation.right.label);
    }

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

        const time = convertTo12Hour(hour24, minute);

        taskTimeHour.value = String(time.hour12);
        taskTimeMinute.value = String(time.minute);
        taskTimePeriod.value = time.period;
    }

    function readPickerTime() {
        return convertTo24Hour(
            taskTimeHour ? taskTimeHour.value : 12,
            taskTimeMinute ? taskTimeMinute.value : 0,
            taskTimePeriod ? taskTimePeriod.value : 'AM'
        );
    }

    function updateQuickDateSelection() {
        const today = startOfDay(new Date());
        const tomorrow = addDays(today, 1);
        let quickValue = null;

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
        const hasDate = Boolean(draftSelectedDate);
        const timeIsEnabled = hasDate && draftHasTime;

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
        const dateWasEmpty = !draftSelectedDate;
        draftSelectedDate = startOfDay(date);
        calendarViewDate = getCalendarMonthStart(date, getActiveCalendarSystem());

        if (dateWasEmpty) {
            draftHasTime = true;
        }

        setDateTimeModalMessage('');
        updateQuickDateSelection();
        updateTimeControls();
        renderCalendar();
    }

    function renderCalendar() {
        const today = new Date();

        if (!calendarViewDate) {
            calendarViewDate = getCalendarMonthStart(today, getActiveCalendarSystem());
        }

        renderDateTimeCalendar({
            calendarDays: calendarDays,
            calendarMonthLabel: calendarMonthLabel,
            viewDate: calendarViewDate,
            selectedDate: draftSelectedDate,
            onSelect: selectCalendarDate
        });
    }

    function closeDateTimeModal(shouldRestoreFocus) {
        if (!dateTimeModal) {
            return;
        }

        if (dateTimeModal.open) {
            dateTimeModal.close();
        }
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

        const today = startOfDay(new Date());
        const storedDate = taskDueAt?.value
            ? parseDateKey(taskDueAt.value.slice(0, 10))
            : null;

        lastDateTimeModalTrigger = trigger || document.activeElement;

        if (committedDateMode === 'no-date') {
            draftSelectedDate = null;
            draftHasTime = false;
        } else if (storedDate) {
            draftSelectedDate = storedDate;
            draftHasTime = Boolean(taskHasTime?.value === '1');

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

        calendarViewDate = getCalendarMonthStart(
            draftSelectedDate || today,
            getActiveCalendarSystem()
        );
        updateQuickDateSelection();
        updateTimeControls();
        renderCalendar();
        setDateTimeModalMessage('');
        if (!dateTimeModal.open) {
            dateTimeModal.showModal();
        }

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
            if (setTaskDateButton) {
                setTaskDateButton.classList.remove('has-date');
            }
            committedDateMode = 'no-date';
            announceDueDateChange();
            closeDateTimeModal();
            return;
        }

        const today = startOfDay(new Date());
        const tomorrow = addDays(today, 1);
        let dateLabel;

        if (datesAreEqual(draftSelectedDate, today)) {
            dateLabel = 'Today';
        } else if (datesAreEqual(draftSelectedDate, tomorrow)) {
            dateLabel = 'Tomorrow';
        } else {
            dateLabel = formatCalendarDate(draftSelectedDate, getActiveCalendarSystem());
        }

        if (draftHasTime) {
            const invalidTimeControl = [taskTimeHour, taskTimeMinute].find(function (control) {
                return control && !control.checkValidity();
            });

            if (invalidTimeControl) {
                setDateTimeModalMessage('Enter an hour from 1 to 12 and minutes from 0 to 59.');
                invalidTimeControl.focus();
                return;
            }

            const selectedTime = readPickerTime();
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

        if (setTaskDateButton) {
            setTaskDateButton.classList.add('has-date');
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

        dateTimeModal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeDateTimeModal();
        });
    }

    if (previousCalendarMonthButton) {
        previousCalendarMonthButton.addEventListener('click', function () {
            calendarViewDate = changeCalendarMonth(calendarViewDate, calendarNavigation.left.offset);
            renderCalendar();
        });
    }

    if (nextCalendarMonthButton) {
        nextCalendarMonthButton.addEventListener('click', function () {
            calendarViewDate = changeCalendarMonth(calendarViewDate, calendarNavigation.right.offset);
            renderCalendar();
        });
    }

    quickDateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            const today = startOfDay(new Date());

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

    [taskTimeHour, taskTimeMinute].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            input.value = normalizeTimeInput(
                input.value,
                Number(input.min),
                Number(input.max)
            );
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
            return Boolean(dateTimeModal?.open);
        },
        close: function (shouldRestoreFocus) {
            closeDateTimeModal(shouldRestoreFocus);
        }
    };
}
