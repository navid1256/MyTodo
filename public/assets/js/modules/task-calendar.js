import {
    addDays,
    datesAreEqual,
    formatDateKey,
    startOfDay
} from '../utils/date-utils.js';

export function initTaskCalendar(signal) {
    const taskCalendar = document.getElementById('taskCalendar');
    const calendarMonthLabel = document.getElementById('taskCalendarMonth');
    const calendarDays = document.getElementById('taskCalendarDays');
    const previousMonthButton = document.getElementById('previousTaskCalendarMonth');
    const nextMonthButton = document.getElementById('nextTaskCalendarMonth');
    const showAllTasksButton = document.getElementById('showAllTasksButton');
    const selectedDateLabel = document.getElementById('selectedTaskDateLabel');
    const filteredTasksEmpty = document.getElementById('filteredTasksEmpty');
    const allTasksEmpty = document.getElementById('allTasksEmpty');
    const calendarStatus = document.getElementById('taskCalendarStatus');
    let taskItems = Array.from(document.querySelectorAll('#manageTaskItems .taskItem'));

    if (!taskCalendar || !calendarMonthLabel || !calendarDays) {
        return;
    }

    const today = startOfDay(new Date());
    let calendarViewDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDate = null;
    function getTaskCountsByDate() {
        return taskItems.reduce(function (counts, taskItem) {
            const taskDate = taskItem.dataset.taskDate;

            if (taskDate) {
                counts[taskDate] = (counts[taskDate] || 0) + 1;
            }

            return counts;
        }, {});
    }

    let taskCountsByDate = getTaskCountsByDate();

    function formatAccessibleDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }).format(date);
    }

    function updateListHeader(date) {
        const isShowingAllTasks = !date;

        if (showAllTasksButton) {
            showAllTasksButton.classList.toggle('is-active', isShowingAllTasks);
            showAllTasksButton.setAttribute('aria-pressed', String(isShowingAllTasks));
        }

        if (selectedDateLabel) {
            selectedDateLabel.hidden = isShowingAllTasks;
            selectedDateLabel.textContent = date
                ? new Intl.DateTimeFormat('en-US', {
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
        const selectedDateKey = formatDateKey(selectedDate);
        let visibleTaskCount = 0;

        taskItems.forEach(function (taskItem) {
            const shouldShow = taskItem.dataset.taskDate === selectedDateKey;
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
        const viewYear = calendarViewDate.getFullYear();
        const viewMonth = calendarViewDate.getMonth();
        const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        const daysFromMonday = (firstDayOfMonth.getDay() + 6) % 7;
        const firstGridDate = addDays(firstDayOfMonth, -daysFromMonday);

        calendarMonthLabel.textContent = new Intl.DateTimeFormat('en-US', {
            month: 'long',
            year: 'numeric'
        }).format(firstDayOfMonth);
        calendarDays.textContent = '';

        for (let dayIndex = 0; dayIndex < 42; dayIndex += 1) {
            const calendarDate = addDays(firstGridDate, dayIndex);
            const dateKey = formatDateKey(calendarDate);
            const taskCount = taskCountsByDate[dateKey] || 0;
            const dayButton = document.createElement('button');
            const accessibleDate = formatAccessibleDate(calendarDate);

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
                const parts = event.currentTarget.dataset.date.split('-').map(Number);
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

    document.addEventListener('task:removed-from-manage', function (event) {
        const taskId = Number(event.detail && event.detail.taskId);

        if (!Number.isInteger(taskId) || taskId < 1) {
            return;
        }

        taskItems = taskItems.filter(function (taskItem) {
            return Number(taskItem.dataset.taskId) !== taskId;
        });
        taskCountsByDate = getTaskCountsByDate();

        if (selectedDate) {
            filterTasksByDate(selectedDate);
            renderCalendar();
            return;
        }

        showAllTasks();
    }, signal ? { signal } : undefined);

    showAllTasks();
}
