import {
    formatDateKey,
    normaliseDate,
    parseDateKey
} from '../../utils/date-utils.js';
import {
    CALENDAR_SYSTEM,
    changeCalendarMonth,
    formatCalendarDate,
    getActiveCalendarSystem,
    getCalendarGrid,
    getCalendarMonthLabel,
    getCalendarMonthStart,
    getCalendarNavigation,
    getCalendarWeekdayNames
} from '../../utils/calendar-core.js';

export function createRepeatCalendar(options) {
    const repeatCalendarMonth = document.getElementById('repeatCalendarMonth');
    const repeatCalendarDays = document.getElementById('repeatCalendarDays');
    const previousRepeatMonth = document.getElementById('previousRepeatMonth');
    const nextRepeatMonth = document.getElementById('nextRepeatMonth');
    const repeatEndDateSummary = document.getElementById('repeatEndDateSummary');
    const repeatCalendarWeekdays = document.querySelector('.repeatCalendarWeekdays');
    const calendarSystem = getActiveCalendarSystem();
    const calendarNavigation = getCalendarNavigation();
    let draftEndDate = '';
    let calendarDate = getCalendarMonthStart(normaliseDate(new Date()), calendarSystem);

    if (previousRepeatMonth) {
        previousRepeatMonth.setAttribute('aria-label', calendarNavigation.left.label);
    }

    if (nextRepeatMonth) {
        nextRepeatMonth.setAttribute('aria-label', calendarNavigation.right.label);
    }

    function getMinimumEndDate() {
        const minimumEndDate = options.getMinimumEndDate();
        return normaliseDate(minimumEndDate || new Date());
    }

    function render() {
        if (!repeatCalendarDays || !repeatCalendarMonth) {
            return;
        }

        const firstOfMonth = getCalendarMonthStart(calendarDate, calendarSystem);
        const calendarGrid = getCalendarGrid(calendarDate, calendarSystem);
        const minimumEndDate = getMinimumEndDate();
        const selectedEndDate = parseDateKey(draftEndDate);
        const minimumMonth = getCalendarMonthStart(minimumEndDate, calendarSystem);

        repeatCalendarMonth.textContent = getCalendarMonthLabel(calendarDate, calendarSystem);
        repeatCalendarDays.dir = calendarSystem === CALENDAR_SYSTEM.JALALI ? 'rtl' : 'ltr';

        if (repeatCalendarWeekdays) {
            repeatCalendarWeekdays.dir = repeatCalendarDays.dir;
            getCalendarWeekdayNames(calendarSystem).forEach((weekdayName, index) => {
                if (repeatCalendarWeekdays.children[index]) {
                    repeatCalendarWeekdays.children[index].textContent = weekdayName;
                }
            });
        }

        repeatCalendarDays.textContent = '';

        if (previousRepeatMonth) {
            previousRepeatMonth.disabled = calendarNavigation.left.offset < 0
                && firstOfMonth <= minimumMonth;
        }

        if (nextRepeatMonth) {
            nextRepeatMonth.disabled = calendarNavigation.right.offset < 0
                && firstOfMonth <= minimumMonth;
        }

        calendarGrid.forEach((calendarDay) => {
            const day = calendarDay.date;
            const dayKey = calendarDay.dateKey;
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'repeatCalendarDay';
            button.textContent = calendarDay.dayLabel;
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', calendarDay.accessibleLabel);
            button.disabled = day <= minimumEndDate;

            if (calendarDay.isOutsideMonth) {
                button.classList.add('outsideMonth');
            }

            if (selectedEndDate && dayKey === formatDateKey(selectedEndDate)) {
                button.classList.add('selected');
                button.setAttribute('aria-selected', 'true');
            }

            button.addEventListener('click', function () {
                draftEndDate = dayKey;
                calendarDate = getCalendarMonthStart(day, calendarSystem);
                options.setMessage('');
                render();
            });

            repeatCalendarDays.appendChild(button);
        });

        if (repeatEndDateSummary) {
            repeatEndDateSummary.textContent = selectedEndDate
                ? 'Ends on ' + formatCalendarDate(selectedEndDate, calendarSystem)
                : 'Select an end date';
        }
    }

    function setEndDate(dateKey, baseDate) {
        draftEndDate = dateKey || '';

        const calendarBase = parseDateKey(draftEndDate)
            || baseDate
            || normaliseDate(new Date());

        calendarDate = getCalendarMonthStart(calendarBase, calendarSystem);

        render();
    }

    function getEndDate() {
        return draftEndDate;
    }

    if (previousRepeatMonth) {
        previousRepeatMonth.addEventListener('click', function () {
            calendarDate = changeCalendarMonth(
                calendarDate,
                calendarNavigation.left.offset,
                calendarSystem
            );
            render();
        });
    }

    if (nextRepeatMonth) {
        nextRepeatMonth.addEventListener('click', function () {
            calendarDate = changeCalendarMonth(
                calendarDate,
                calendarNavigation.right.offset,
                calendarSystem
            );
            render();
        });
    }

    return {
        getEndDate,
        render,
        setEndDate
    };
}
