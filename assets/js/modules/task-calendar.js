import {
    addDays,
    datesAreEqual,
    formatDateKey,
    startOfDay
} from '../utils/date-utils.js';

export function initTaskCalendar() {
    var taskCalendar = document.getElementById('taskCalendar');
    var calendarMonthLabel = document.getElementById('taskCalendarMonth');
    var calendarDays = document.getElementById('taskCalendarDays');
    var previousMonthButton = document.getElementById('previousTaskCalendarMonth');
    var nextMonthButton = document.getElementById('nextTaskCalendarMonth');
    var showAllTasksButton = document.getElementById('showAllTasksButton');
    var selectedDateLabel = document.getElementById('selectedTaskDateLabel');
    var filteredTasksEmpty = document.getElementById('filteredTasksEmpty');
    var allTasksEmpty = document.getElementById('allTasksEmpty');
    var calendarStatus = document.getElementById('taskCalendarStatus');
    var taskItems = Array.from(document.querySelectorAll('#manageTaskItems .taskItem'));

    if (!taskCalendar || !calendarMonthLabel || !calendarDays) {
        return;
    }

    var today = startOfDay(new Date());
    var calendarViewDate = new Date(today.getFullYear(), today.getMonth(), 1);
    var selectedDate = null;
    var taskCountsByDate = taskItems.reduce(function (counts, taskItem) {
        var taskDate = taskItem.dataset.taskDate;

        if (taskDate) {
            counts[taskDate] = (counts[taskDate] || 0) + 1;
        }

        return counts;
    }, {});

    function formatAccessibleDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }).format(date);
    }

    function updateListHeader(date) {
        var isShowingAllTasks = !date;

        if (showAllTasksButton) {
            showAllTasksButton.classList.toggle('is-active', isShowingAllTasks);
            showAllTasksButton.setAttribute('aria-pressed', String(isShowingAllTasks));
        }

        if (selectedDateLabel) {
            selectedDateLabel.hidden = isShowingAllTasks;
            selectedDateLabel.textContent = date
                ? '· ' + new Intl.DateTimeFormat('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                }).format(date)
                : '';
        }
    }

    function showAllTasks() {
        selectedDate = null;

        taskItems.forEach(function (taskItem) {
            taskItem.hidden = false;
        });

        if (allTasksEmpty) {
            allTasksEmpty.hidden = taskItems.length > 0;
        }

        if (filteredTasksEmpty) {
            filteredTasksEmpty.hidden = true;
        }

        updateListHeader(null);
        renderCalendar();

        if (calendarStatus) {
            calendarStatus.textContent = taskItems.length === 1
                ? 'Showing all 1 task.'
                : 'Showing all ' + taskItems.length + ' tasks.';
        }
    }

    function filterTasksByDate(date) {
        selectedDate = startOfDay(date);
        var selectedDateKey = formatDateKey(selectedDate);
        var visibleTaskCount = 0;

        taskItems.forEach(function (taskItem) {
            var shouldShow = taskItem.dataset.taskDate === selectedDateKey;
            taskItem.hidden = !shouldShow;

            if (shouldShow) {
                visibleTaskCount += 1;
            }
        });

        if (allTasksEmpty) {
            allTasksEmpty.hidden = true;
        }

        if (filteredTasksEmpty) {
            filteredTasksEmpty.hidden = visibleTaskCount > 0;
        }

        updateListHeader(selectedDate);

        if (calendarStatus) {
            calendarStatus.textContent = visibleTaskCount === 1
                ? 'Showing 1 task for ' + formatAccessibleDate(selectedDate) + '.'
                : 'Showing ' + visibleTaskCount + ' tasks for ' + formatAccessibleDate(selectedDate) + '.';
        }
    }

    function selectCalendarDate(date) {
        filterTasksByDate(date);
        calendarViewDate = new Date(date.getFullYear(), date.getMonth(), 1);
        renderCalendar();
    }

    function renderCalendar() {
        var viewYear = calendarViewDate.getFullYear();
        var viewMonth = calendarViewDate.getMonth();
        var firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        var daysFromMonday = (firstDayOfMonth.getDay() + 6) % 7;
        var firstGridDate = addDays(firstDayOfMonth, -daysFromMonday);

        calendarMonthLabel.textContent = new Intl.DateTimeFormat('en-US', {
            month: 'long',
            year: 'numeric'
        }).format(firstDayOfMonth);
        calendarDays.textContent = '';

        for (var dayIndex = 0; dayIndex < 42; dayIndex += 1) {
            var calendarDate = addDays(firstGridDate, dayIndex);
            var dateKey = formatDateKey(calendarDate);
            var taskCount = taskCountsByDate[dateKey] || 0;
            var dayButton = document.createElement('button');
            var accessibleDate = formatAccessibleDate(calendarDate);

            dayButton.type = 'button';
            dayButton.className = 'taskCalendarDay';
            dayButton.textContent = String(calendarDate.getDate());
            dayButton.dataset.date = dateKey;
            dayButton.setAttribute('role', 'gridcell');
            dayButton.setAttribute('aria-selected', String(datesAreEqual(calendarDate, selectedDate)));
            dayButton.setAttribute(
                'aria-label',
                accessibleDate + (taskCount
                    ? ', ' + taskCount + (taskCount === 1 ? ' task' : ' tasks')
                    : ', no tasks')
            );

            if (calendarDate.getMonth() !== viewMonth) {
                dayButton.classList.add('outside-month');
            }

            if (datesAreEqual(calendarDate, today)) {
                dayButton.classList.add('today');
            }

            if (taskCount > 0) {
                dayButton.classList.add('has-tasks');
            }

            if (datesAreEqual(calendarDate, selectedDate)) {
                dayButton.classList.add('selected');
            }

            dayButton.addEventListener('click', function (event) {
                var parts = event.currentTarget.dataset.date.split('-').map(Number);
                selectCalendarDate(new Date(parts[0], parts[1] - 1, parts[2]));
            });
            calendarDays.appendChild(dayButton);
        }
    }

    if (previousMonthButton) {
        previousMonthButton.addEventListener('click', function () {
            calendarViewDate = new Date(
                calendarViewDate.getFullYear(),
                calendarViewDate.getMonth() - 1,
                1
            );
            renderCalendar();
        });
    }

    if (nextMonthButton) {
        nextMonthButton.addEventListener('click', function () {
            calendarViewDate = new Date(
                calendarViewDate.getFullYear(),
                calendarViewDate.getMonth() + 1,
                1
            );
            renderCalendar();
        });
    }

    if (showAllTasksButton) {
        showAllTasksButton.addEventListener('click', showAllTasks);
    }

    showAllTasks();
}
