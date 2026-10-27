import { getHolidayDates } from '../services/calendar-holidays.js';
import { CALENDAR_SYSTEM, getActiveCalendarSystem, getCalendarYearMonth } from './calendar-core.js';
import { translate } from './i18n.js';

/** Colors Fridays immediately, then decorates the existing buttons with cached official holidays. */
export async function markCalendarHolidays(days, weekdays, grid, gregorianWeekStartsOn = 0) {
    const calendarSystem = getActiveCalendarSystem();
    const fridayIndex = calendarSystem === CALENDAR_SYSTEM.JALALI ? 6 : (5 - gregorianWeekStartsOn + 7) % 7;
    Array.from(weekdays?.children ?? []).forEach((weekday, index) => {
        weekday.classList.toggle('is-calendar-friday', index === fridayIndex);
    });
    const buttons = Array.from(days.children);
    const labels = buttons.map((button) => button.getAttribute('aria-label') || '');
    buttons.forEach((button, index) => {
        button.classList.toggle('is-calendar-holiday', grid[index].date.getDay() === 5);
    });

    // Even a Gregorian month may overlap two Jalali years around Nowruz.
    const years = [...new Set(grid.map((day) => getCalendarYearMonth(day.date, CALENDAR_SYSTEM.JALALI).year))];
    const holidaySets = await Promise.all(years.map(getHolidayDates));
    const holidayLabel = calendarSystem === CALENDAR_SYSTEM.JALALI
        ? 'تعطیل رسمی'
        : translate('calendar.public_holiday', {}, 'Public holiday');
    buttons.forEach((button, index) => {
        // A completed request must not decorate a different month after rapid navigation.
        if (button.parentElement !== days) {
            return;
        }
        const day = grid[index];
        const isPublicHoliday = holidaySets.some((dates) => dates.has(day.dateKey));
        button.classList.toggle('is-calendar-holiday', day.date.getDay() === 5 || isPublicHoliday);
        button.setAttribute('aria-label', isPublicHoliday ? `${labels[index]}, ${holidayLabel}` : labels[index]);
    });
}
