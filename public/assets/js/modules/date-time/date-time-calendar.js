import {
    datesAreEqual,
    parseDateKey
} from '../../utils/date-utils.js';
import {
    CALENDAR_SYSTEM,
    changeCalendarMonth as changeMonth,
    getActiveCalendarSystem,
    getCalendarGrid,
    getCalendarMonthLabel,
    getCalendarWeekdayNames
} from '../../utils/calendar-core.js';

export function renderDateTimeCalendar(options) {
    const calendarDays = options.calendarDays;
    const calendarMonthLabel = options.calendarMonthLabel;
    const viewDate = options.viewDate;
    const selectedDate = options.selectedDate;
    const onSelect = options.onSelect;
    const calendarSystem = getActiveCalendarSystem();
    const calendarWeekdays = calendarDays.previousElementSibling;

    if (!calendarDays || !calendarMonthLabel || !viewDate) {
        return;
    }

    const calendarGrid = getCalendarGrid(viewDate, calendarSystem);
    let todayDate = new Date();
    todayDate = new Date(
        todayDate.getFullYear(),
        todayDate.getMonth(),
        todayDate.getDate()
    );

    calendarMonthLabel.textContent = getCalendarMonthLabel(viewDate, calendarSystem);
    calendarDays.dir = calendarSystem === CALENDAR_SYSTEM.JALALI ? 'rtl' : 'ltr';

    if (calendarWeekdays?.classList.contains('calendarWeekdays')) {
        calendarWeekdays.dir = calendarDays.dir;
        getCalendarWeekdayNames(calendarSystem).forEach((weekdayName, index) => {
            if (calendarWeekdays.children[index]) {
                calendarWeekdays.children[index].textContent = weekdayName;
            }
        });
    }

    calendarDays.textContent = '';

    calendarGrid.forEach((calendarDay) => {
        const calendarDate = calendarDay.date;
        const dayButton = document.createElement('button');
        const isSelected = datesAreEqual(calendarDate, selectedDate);

        dayButton.type = 'button';
        dayButton.className = 'calendarDay';
        dayButton.textContent = calendarDay.dayLabel;
        dayButton.dataset.date = calendarDay.dateKey;
        dayButton.setAttribute('role', 'gridcell');
        dayButton.setAttribute('aria-selected', String(isSelected));

        dayButton.setAttribute(
            'aria-label',
            calendarDay.accessibleLabel
        );

        if (calendarDay.isOutsideMonth) {
            dayButton.classList.add('outsideMonth');
        }

        if (datesAreEqual(calendarDate, todayDate)) {
            dayButton.classList.add('today');
        }

        if (isSelected) {
            dayButton.classList.add('selected');
        }

        dayButton.addEventListener('click', function (event) {
            const selected = parseDateKey(event.currentTarget.dataset.date);

            if (selected && typeof onSelect === 'function') {
                onSelect(selected);
            }
        });

        calendarDays.appendChild(dayButton);
    });
}

export function changeCalendarMonth(viewDate, offset) {
    return changeMonth(viewDate, offset, getActiveCalendarSystem());
}
