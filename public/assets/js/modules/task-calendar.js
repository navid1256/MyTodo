import {
    datesAreEqual,
    formatDateKey,
    parseDateKey,
    startOfDay
} from '../utils/date-utils.js';
import {
    CALENDAR_SYSTEM,
    changeCalendarMonth,
    formatCalendarAccessibleDate,
    formatCalendarDate,
    getActiveCalendarSystem,
    getCalendarGrid,
    getCalendarMonthLabel,
    getCalendarMonthStart,
    getCalendarNavigation,
    getCalendarWeekdayNames
} from '../utils/calendar-core.js';
import { translate } from '../utils/i18n.js';

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

    const calendarWeekdays = taskCalendar.querySelector('.taskCalendarWeekdays');

    const today = startOfDay(new Date());
    const calendarSystem = getActiveCalendarSystem();
    const calendarNavigation = getCalendarNavigation();
    let calendarViewDate = getCalendarMonthStart(today, calendarSystem);
    let selectedDate = null;

    if (previousMonthButton) {
        previousMonthButton.setAttribute('aria-label', calendarNavigation.left.label);
    }

    if (nextMonthButton) {
        nextMonthButton.setAttribute('aria-label', calendarNavigation.right.label);
    }
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
        return formatCalendarAccessibleDate(date, calendarSystem);
    }

    function updateListHeader(date) {
        const isShowingAllTasks = !date;

        if (showAllTasksButton) {
            showAllTasksButton.classList.toggle('is-active', isShowingAllTasks);
            showAllTasksButton.setAttribute('aria-pressed', String(isShowingAllTasks));
        }

        if (selectedDateLabel) {
            selectedDateLabel.hidden = isShowingAllTasks;
            selectedDateLabel.textContent = date ? formatCalendarDate(date, calendarSystem) : '';
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
                ? translate('manage.showing_all_one', {}, 'Showing all 1 task.')
                : translate('manage.showing_all', { count: taskItems.length }, 'Showing all {count} tasks.');
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
                ? translate('manage.showing_date_one', { date: formatAccessibleDate(selectedDate) }, 'Showing 1 task for {date}.')
                : translate('manage.showing_date', { count: visibleTaskCount, date: formatAccessibleDate(selectedDate) }, 'Showing {count} tasks for {date}.');
        }
    }

    function selectCalendarDate(date) {
        filterTasksByDate(date);
        calendarViewDate = getCalendarMonthStart(date, calendarSystem);
        renderCalendar();
    }

    function renderCalendar() {
        const calendarGrid = getCalendarGrid(calendarViewDate, calendarSystem, 1);

        calendarMonthLabel.textContent = getCalendarMonthLabel(calendarViewDate, calendarSystem);
        calendarDays.dir = calendarSystem === CALENDAR_SYSTEM.JALALI ? 'rtl' : 'ltr';

        if (calendarWeekdays) {
            calendarWeekdays.dir = calendarDays.dir;
            getCalendarWeekdayNames(calendarSystem, 1).forEach((weekdayName, index) => {
                if (calendarWeekdays.children[index]) {
                    calendarWeekdays.children[index].textContent = weekdayName;
                }
            });
        }

        calendarDays.textContent = '';

        calendarGrid.forEach((calendarDay) => {
            const calendarDate = calendarDay.date;
            const dateKey = calendarDay.dateKey;
            const taskCount = taskCountsByDate[dateKey] || 0;
            const dayButton = document.createElement('button');
            const accessibleDate = formatAccessibleDate(calendarDate);

            let taskLabel;

            if (taskCount === 0) {
                taskLabel = 'no tasks';
            } else if (taskCount === 1) {
                taskLabel = '1 task';
            } else {
                taskLabel = `${taskCount} tasks`;
            }

            dayButton.type = 'button';
            dayButton.className = 'taskCalendarDay';
            dayButton.textContent = calendarDay.dayLabel;
            dayButton.dataset.date = dateKey;
            dayButton.setAttribute('role', 'gridcell');
            dayButton.setAttribute('aria-selected', String(datesAreEqual(calendarDate, selectedDate)));
            dayButton.setAttribute('aria-label', `${accessibleDate}, ${taskLabel}`);

            if (calendarDay.isOutsideMonth) {
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
                const selected = parseDateKey(event.currentTarget.dataset.date);

                if (selected) {
                    selectCalendarDate(selected);
                }
            });
            calendarDays.appendChild(dayButton);
        });
    }

    if (previousMonthButton) {
        previousMonthButton.addEventListener('click', function () {
            calendarViewDate = changeCalendarMonth(
                calendarViewDate,
                calendarNavigation.left.offset,
                calendarSystem
            );
            renderCalendar();
        });
    }

    if (nextMonthButton) {
        nextMonthButton.addEventListener('click', function () {
            calendarViewDate = changeCalendarMonth(
                calendarViewDate,
                calendarNavigation.right.offset,
                calendarSystem
            );
            renderCalendar();
        });
    }

    if (showAllTasksButton) {
        showAllTasksButton.addEventListener('click', showAllTasks);
    }

    document.addEventListener('task:removed-from-manage', function (event) {
        const taskId = Number(event.detail?.taskId);

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
