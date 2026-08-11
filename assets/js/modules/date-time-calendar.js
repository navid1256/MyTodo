import {
    addDays,
    datesAreEqual,
    formatDateKey,
    parseDateKey
} from '../utils/date-utils.js';

export function renderDateTimeCalendar(options) {
    var calendarDays = options.calendarDays;
    var calendarMonthLabel = options.calendarMonthLabel;
    var viewDate = options.viewDate;
    var selectedDate = options.selectedDate;
    var onSelect = options.onSelect;

    if (!calendarDays || !calendarMonthLabel || !viewDate) {
        return;
    }

    var viewYear = viewDate.getFullYear();
    var viewMonth = viewDate.getMonth();
    var firstDayOfMonth = new Date(viewYear, viewMonth, 1);
    var firstGridDate = addDays(
        firstDayOfMonth,
        -firstDayOfMonth.getDay()
    );
    var todayDate = new Date();
    todayDate = new Date(
        todayDate.getFullYear(),
        todayDate.getMonth(),
        todayDate.getDate()
    );

    calendarMonthLabel.textContent = new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric'
    }).format(firstDayOfMonth);

    calendarDays.textContent = '';

    for (var dayIndex = 0; dayIndex < 42; dayIndex += 1) {
        var calendarDate = addDays(firstGridDate, dayIndex);
        var dayButton = document.createElement('button');
        var isSelected = datesAreEqual(calendarDate, selectedDate);

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
            var selected = parseDateKey(event.currentTarget.dataset.date);

            if (selected && typeof onSelect === 'function') {
                onSelect(selected);
            }
        });

        calendarDays.appendChild(dayButton);
    }
}

export function changeCalendarMonth(viewDate, offset) {
    return new Date(
        viewDate.getFullYear(),
        viewDate.getMonth() + offset,
        1
    );
}