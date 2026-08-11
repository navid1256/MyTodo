import {
    formatDate,
    formatDateKey,
    normaliseDate,
    parseDateKey
} from '../../utils/date-utils.js';

const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

export function createRepeatCalendar(options) {
    const repeatCalendarMonth = document.getElementById('repeatCalendarMonth');
    const repeatCalendarDays = document.getElementById('repeatCalendarDays');
    const previousRepeatMonth = document.getElementById('previousRepeatMonth');
    const nextRepeatMonth = document.getElementById('nextRepeatMonth');
    const repeatEndDateSummary = document.getElementById('repeatEndDateSummary');
    let draftEndDate = '';
    let calendarDate = normaliseDate(new Date());

    function getMinimumEndDate() {
        const minimumEndDate = options.getMinimumEndDate();
        return normaliseDate(minimumEndDate || new Date());
    }

    function render() {
        if (!repeatCalendarDays || !repeatCalendarMonth) {
            return;
        }

        const year = calendarDate.getFullYear();
        const month = calendarDate.getMonth();
        const firstOfMonth = new Date(year, month, 1, 12);
        const firstVisibleDate = new Date(year, month, 1 - firstOfMonth.getDay(), 12);
        const minimumEndDate = getMinimumEndDate();
        const selectedEndDate = parseDateKey(draftEndDate);
        const minimumMonth = new Date(minimumEndDate.getFullYear(), minimumEndDate.getMonth(), 1, 12);

        repeatCalendarMonth.textContent = MONTH_NAMES[month] + ' ' + year;
        repeatCalendarDays.textContent = '';

        if (previousRepeatMonth) {
            previousRepeatMonth.disabled = firstOfMonth <= minimumMonth;
        }

        for (let index = 0; index < 42; index++) {
            const day = new Date(
                firstVisibleDate.getFullYear(),
                firstVisibleDate.getMonth(),
                firstVisibleDate.getDate() + index,
                12
            );
            const dayKey = formatDateKey(day);
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'repeatCalendarDay';
            button.textContent = String(day.getDate());
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', formatDate(day));
            button.disabled = day <= minimumEndDate;

            if (day.getMonth() !== month) {
                button.classList.add('outsideMonth');
            }

            if (selectedEndDate && dayKey === formatDateKey(selectedEndDate)) {
                button.classList.add('selected');
                button.setAttribute('aria-selected', 'true');
            }

            button.addEventListener('click', function () {
                draftEndDate = dayKey;
                calendarDate = new Date(day.getFullYear(), day.getMonth(), 1, 12);
                options.setMessage('');
                render();
            });

            repeatCalendarDays.appendChild(button);
        }

        if (repeatEndDateSummary) {
            repeatEndDateSummary.textContent = selectedEndDate
                ? 'Ends on ' + formatDate(selectedEndDate)
                : 'Select an end date';
        }
    }

    function setEndDate(dateKey, baseDate) {
        draftEndDate = dateKey || '';

        const calendarBase = parseDateKey(draftEndDate)
            || baseDate
            || normaliseDate(new Date());

        calendarDate = new Date(
            calendarBase.getFullYear(),
            calendarBase.getMonth(),
            1,
            12
        );

        render();
    }

    function getEndDate() {
        return draftEndDate;
    }

    if (previousRepeatMonth) {
        previousRepeatMonth.addEventListener('click', function () {
            calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() - 1, 1, 12);
            render();
        });
    }

    if (nextRepeatMonth) {
        nextRepeatMonth.addEventListener('click', function () {
            calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() + 1, 1, 12);
            render();
        });
    }

    return {
        getEndDate,
        render,
        setEndDate
    };
}
