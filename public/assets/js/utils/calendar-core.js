import {
    isValidJalaaliDate,
    jalaaliMonthLength,
    toGregorian,
    toJalaali
} from 'jalaali-js';

import {
    addDays,
    formatDateKey,
    startOfDay
} from './date-utils.js';
import { translate } from './i18n.js';

export const CALENDAR_SYSTEM = Object.freeze({
    GREGORIAN: 'gregorian',
    JALALI: 'jalali'
});

const JALALI_MONTH_NAMES = Object.freeze([
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند'
]);

const JALALI_WEEKDAY_NAMES = Object.freeze([
    'شنبه',
    'یکشنبه',
    'دوشنبه',
    'سه‌شنبه',
    'چهارشنبه',
    'پنجشنبه',
    'جمعه'
]);

const GREGORIAN_WEEKDAY_NAMES = Object.freeze([
    'Sun',
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat'
]);

const persianNumberFormatter = new Intl.NumberFormat('fa-IR', {
    useGrouping: false
});

const jalaliAccessibleDateFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
});

function normalizeCalendarSystem(calendarSystem) {
    return calendarSystem === CALENDAR_SYSTEM.JALALI
        ? CALENDAR_SYSTEM.JALALI
        : CALENDAR_SYSTEM.GREGORIAN;
}

function createLocalDate(year, month, day) {
    return new Date(year, month - 1, day, 12);
}

function toGregorianDate(jalaliYear, jalaliMonth, jalaliDay = 1) {
    const gregorian = toGregorian(jalaliYear, jalaliMonth, jalaliDay);

    return createLocalDate(gregorian.gy, gregorian.gm, gregorian.gd);
}

function shiftJalaliMonth(jalaliYear, jalaliMonth, offset) {
    const absoluteMonth = (jalaliYear * 12) + (jalaliMonth - 1) + offset;
    const shiftedYear = Math.floor(absoluteMonth / 12);
    const shiftedMonth = ((absoluteMonth % 12) + 12) % 12;

    return {
        year: shiftedYear,
        month: shiftedMonth + 1
    };
}

export function getActiveCalendarSystem() {
    return normalizeCalendarSystem(document.body?.dataset.calendarSystem);
}

export function getCalendarMonthStart(date, calendarSystem) {
    const normalizedSystem = normalizeCalendarSystem(calendarSystem);

    if (normalizedSystem === CALENDAR_SYSTEM.JALALI) {
        const jalali = toJalaali(date);

        return toGregorianDate(jalali.jy, jalali.jm);
    }

    return new Date(date.getFullYear(), date.getMonth(), 1, 12);
}

export function changeCalendarMonth(viewDate, offset, calendarSystem) {
    const normalizedSystem = normalizeCalendarSystem(calendarSystem);

    if (normalizedSystem === CALENDAR_SYSTEM.JALALI) {
        const currentJalaliMonth = toJalaali(viewDate);
        const shiftedMonth = shiftJalaliMonth(
            currentJalaliMonth.jy,
            currentJalaliMonth.jm,
            offset
        );

        return toGregorianDate(shiftedMonth.year, shiftedMonth.month);
    }

    return new Date(
        viewDate.getFullYear(),
        viewDate.getMonth() + offset,
        1,
        12
    );
}

export function getCalendarMonthLabel(viewDate, calendarSystem) {
    const normalizedSystem = normalizeCalendarSystem(calendarSystem);

    if (normalizedSystem === CALENDAR_SYSTEM.JALALI) {
        const jalali = toJalaali(viewDate);

        return `${JALALI_MONTH_NAMES[jalali.jm - 1]} ${persianNumberFormatter.format(jalali.jy)}`;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric'
    }).format(viewDate);
}

export function getCalendarMonthNames(calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        return [...JALALI_MONTH_NAMES];
    }

    return Array.from({ length: 12 }, (_, monthIndex) => (
        new Intl.DateTimeFormat('en-US', { month: 'long' }).format(
            new Date(2020, monthIndex, 1, 12)
        )
    ));
}

export function getCalendarYearMonth(date, calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        const jalali = toJalaali(date);

        return {
            year: jalali.jy,
            month: jalali.jm
        };
    }

    return {
        year: date.getFullYear(),
        month: date.getMonth() + 1
    };
}

export function createCalendarMonthDate(year, month, calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        if (!isValidJalaaliDate(year, month, 1)) {
            return null;
        }

        return toGregorianDate(year, month);
    }

    const gregorianDate = createLocalDate(year, month, 1);

    return gregorianDate.getFullYear() === year && gregorianDate.getMonth() === month - 1
        ? gregorianDate
        : null;
}

export function formatCalendarNumber(value, calendarSystem) {
    return normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI
        ? persianNumberFormatter.format(value)
        : String(value);
}

export function getCalendarWeekdayNames(calendarSystem, gregorianWeekStartsOn = 0) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        return [...JALALI_WEEKDAY_NAMES];
    }

    return GREGORIAN_WEEKDAY_NAMES.map((_, index) => (
        GREGORIAN_WEEKDAY_NAMES[(index + gregorianWeekStartsOn) % 7]
    ));
}

export function getCalendarNavigation() {
    return {
        left: {
            offset: -1,
            label: translate('common.previous_month', {}, 'Previous month')
        },
        right: {
            offset: 1,
            label: translate('common.next_month', {}, 'Next month')
        }
    };
}

export function getCalendarDayLabel(date, calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        return persianNumberFormatter.format(toJalaali(date).jd);
    }

    return String(date.getDate());
}

export function formatCalendarDate(date, calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        const jalali = toJalaali(date);

        return `${persianNumberFormatter.format(jalali.jd)} ${JALALI_MONTH_NAMES[jalali.jm - 1]} ${persianNumberFormatter.format(jalali.jy)}`;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    }).format(date);
}

export function formatCalendarAccessibleDate(date, calendarSystem) {
    if (normalizeCalendarSystem(calendarSystem) === CALENDAR_SYSTEM.JALALI) {
        return jalaliAccessibleDateFormatter.format(date);
    }

    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    }).format(date);
}

export function getCalendarGrid(viewDate, calendarSystem, gregorianWeekStartsOn = 0) {
    const normalizedSystem = normalizeCalendarSystem(calendarSystem);
    const monthStart = getCalendarMonthStart(viewDate, normalizedSystem);
    let offsetFromWeekStart;

    if (normalizedSystem === CALENDAR_SYSTEM.JALALI) {
        offsetFromWeekStart = (monthStart.getDay() + 1) % 7;
    } else {
        offsetFromWeekStart = (monthStart.getDay() - gregorianWeekStartsOn + 7) % 7;
    }

    const firstGridDate = addDays(monthStart, -offsetFromWeekStart);
    const activeJalaliMonth = normalizedSystem === CALENDAR_SYSTEM.JALALI
        ? toJalaali(monthStart)
        : null;

    return Array.from({ length: 42 }, (_, index) => {
        const date = addDays(firstGridDate, index);
        const jalaliDate = normalizedSystem === CALENDAR_SYSTEM.JALALI
            ? toJalaali(date)
            : null;
        const isOutsideMonth = normalizedSystem === CALENDAR_SYSTEM.JALALI
            ? jalaliDate.jy !== activeJalaliMonth.jy || jalaliDate.jm !== activeJalaliMonth.jm
            : date.getMonth() !== monthStart.getMonth();

        return {
            date,
            dateKey: formatDateKey(date),
            dayLabel: getCalendarDayLabel(date, normalizedSystem),
            accessibleLabel: formatCalendarAccessibleDate(date, normalizedSystem),
            isOutsideMonth
        };
    });
}

export function getJalaliMonthLength(jalaliYear, jalaliMonth) {
    if (!isValidJalaaliDate(jalaliYear, jalaliMonth, 1)) {
        return 0;
    }

    return jalaaliMonthLength(jalaliYear, jalaliMonth);
}

export function toCanonicalDate(date) {
    return formatDateKey(startOfDay(date));
}
