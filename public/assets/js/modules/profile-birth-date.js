import {
    datesAreEqual,
    formatDateKey,
    parseDateKey,
    startOfDay
} from '../utils/date-utils.js';
import {
    CALENDAR_SYSTEM,
    createCalendarMonthDate,
    formatCalendarAccessibleDate,
    formatCalendarDate,
    formatCalendarNumber,
    getActiveCalendarSystem,
    getCalendarGrid,
    getCalendarMonthNames,
    getCalendarMonthStart,
    getCalendarWeekdayNames,
    getCalendarYearMonth
} from '../utils/calendar-core.js';

const MINIMUM_JALALI_YEAR = 1300;

export function initProfileBirthDate(signal) {
    const picker = document.getElementById('profileBirthDatePicker');
    const valueInput = document.getElementById('profileBirthDate');
    const toggleButton = document.getElementById('profileBirthDateButton');
    const display = document.getElementById('profileBirthDateDisplay');
    const popover = document.getElementById('profileBirthDatePopover');
    const monthSelect = document.getElementById('profileBirthMonth');
    const yearSelect = document.getElementById('profileBirthYear');
    const weekdays = document.getElementById('profileBirthDateWeekdays');
    const days = document.getElementById('profileBirthDateDays');
    const status = document.getElementById('profileBirthDateStatus');

    if (!picker || !valueInput || !toggleButton || !display || !popover
        || !monthSelect || !yearSelect || !weekdays || !days) {
        return;
    }

    const calendarSystem = getActiveCalendarSystem();
    const today = startOfDay(new Date());
    const minimumDate = startOfDay(createCalendarMonthDate(
        MINIMUM_JALALI_YEAR,
        1,
        CALENDAR_SYSTEM.JALALI
    ));
    let selectedDate = parseDateKey(valueInput.value);
    let viewDate = getCalendarMonthStart(selectedDate || today, calendarSystem);

    function isAllowedDate(date) {
        return date >= minimumDate && date <= today;
    }

    function updateDisplay() {
        display.textContent = selectedDate
            ? formatCalendarDate(selectedDate, calendarSystem)
            : calendarSystem === CALENDAR_SYSTEM.JALALI
                ? 'تاریخ تولد را انتخاب کنید'
                : 'Select your date of birth';
    }

    function closePicker(shouldRestoreFocus = false) {
        if (popover.open) {
            popover.close();
        }

        toggleButton.setAttribute('aria-expanded', 'false');

        if (shouldRestoreFocus) {
            toggleButton.focus();
        }
    }

    function updateMonthAvailability() {
        const current = getCalendarYearMonth(today, calendarSystem);
        const selectedYear = Number(yearSelect.value);

        Array.from(monthSelect.options).forEach((option) => {
            option.disabled = selectedYear === current.year
                && Number(option.value) > current.month;
        });

        if (monthSelect.selectedOptions[0]?.disabled) {
            monthSelect.value = String(current.month);
        }
    }

    function populateSelectors() {
        const current = getCalendarYearMonth(today, calendarSystem);
        const minimum = getCalendarYearMonth(minimumDate, calendarSystem);
        const view = getCalendarYearMonth(viewDate, calendarSystem);

        monthSelect.textContent = '';
        getCalendarMonthNames(calendarSystem).forEach((monthName, index) => {
            const option = document.createElement('option');

            option.value = String(index + 1);
            option.textContent = monthName;
            option.selected = index + 1 === view.month;
            monthSelect.appendChild(option);
        });

        yearSelect.textContent = '';
        for (let year = current.year; year >= minimum.year; year -= 1) {
            const option = document.createElement('option');

            option.value = String(year);
            option.textContent = formatCalendarNumber(year, calendarSystem);
            option.selected = year === view.year;
            yearSelect.appendChild(option);
        }

        updateMonthAvailability();
    }

    function renderWeekdays() {
        weekdays.textContent = '';
        weekdays.dir = calendarSystem === CALENDAR_SYSTEM.JALALI ? 'rtl' : 'ltr';

        getCalendarWeekdayNames(calendarSystem).forEach((weekdayName) => {
            const weekday = document.createElement('span');

            weekday.textContent = weekdayName;
            weekdays.appendChild(weekday);
        });
    }

    function selectDate(date) {
        if (!isAllowedDate(date)) {
            return;
        }

        selectedDate = startOfDay(date);
        valueInput.value = formatDateKey(selectedDate);
        valueInput.dispatchEvent(new Event('input', { bubbles: true }));
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        updateDisplay();

        if (status) {
            status.textContent = calendarSystem === CALENDAR_SYSTEM.JALALI
                ? `${formatCalendarAccessibleDate(selectedDate, calendarSystem)} انتخاب شد.`
                : `Selected ${formatCalendarAccessibleDate(selectedDate, calendarSystem)}.`;
        }

        closePicker(true);
    }

    function renderDays() {
        days.textContent = '';
        days.dir = calendarSystem === CALENDAR_SYSTEM.JALALI ? 'rtl' : 'ltr';

        getCalendarGrid(viewDate, calendarSystem).forEach((calendarDay) => {
            const button = document.createElement('button');
            const isSelected = datesAreEqual(calendarDay.date, selectedDate);

            button.type = 'button';
            button.className = 'profileBirthDateDay';
            button.textContent = calendarDay.dayLabel;
            button.dataset.date = calendarDay.dateKey;
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', calendarDay.accessibleLabel);
            button.setAttribute('aria-selected', String(isSelected));
            button.disabled = !isAllowedDate(calendarDay.date);

            if (calendarDay.isOutsideMonth) {
                button.classList.add('outsideMonth');
            }

            if (datesAreEqual(calendarDay.date, today)) {
                button.classList.add('today');
            }

            if (isSelected) {
                button.classList.add('selected');
            }

            button.addEventListener('click', () => selectDate(calendarDay.date), { signal });
            days.appendChild(button);
        });
    }

    function changeViewMonth() {
        updateMonthAvailability();

        const nextViewDate = createCalendarMonthDate(
            Number(yearSelect.value),
            Number(monthSelect.value),
            calendarSystem
        );

        if (!nextViewDate) {
            return;
        }

        viewDate = nextViewDate;
        renderDays();
    }

    monthSelect.setAttribute('aria-label', calendarSystem === CALENDAR_SYSTEM.JALALI ? 'ماه' : 'Month');
    yearSelect.setAttribute('aria-label', calendarSystem === CALENDAR_SYSTEM.JALALI ? 'سال' : 'Year');
    popover.setAttribute(
        'aria-label',
        calendarSystem === CALENDAR_SYSTEM.JALALI ? 'انتخاب تاریخ تولد' : 'Choose date of birth'
    );
    populateSelectors();
    renderWeekdays();
    renderDays();
    updateDisplay();

    toggleButton.addEventListener('click', () => {
        const shouldOpen = !popover.open;

        if (shouldOpen) {
            popover.show();
        } else {
            popover.close();
        }

        toggleButton.setAttribute('aria-expanded', String(shouldOpen));

        if (shouldOpen) {
            monthSelect.focus();
        }
    }, { signal });

    monthSelect.addEventListener('change', changeViewMonth, { signal });
    yearSelect.addEventListener('change', changeViewMonth, { signal });

    document.addEventListener('click', (event) => {
        if (popover.open && !picker.contains(event.target)) {
            closePicker();
        }
    }, { signal });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && popover.open) {
            closePicker(true);
        }
    }, { signal });
}
