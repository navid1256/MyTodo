import { formatDate, parseDateKey } from '../../utils/date-utils.js';

const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function resolveMonthlyDay(year, monthIndex, monthDay, useLastDay) {
    const lastDayOfMonth = new Date(year, monthIndex + 1, 0).getDate();

    return useLastDay ? lastDayOfMonth : Math.min(monthDay, lastDayOfMonth);
}

export function cloneRule(rule) {
    return rule ? structuredClone(rule) : null;
}

export function createDefaultRule(baseDate) {
    return {
        frequency: 'daily',
        interval: 1,
        unit: 'day',
        repeat_on: null,
        week_days: [baseDate.getDay()],
        month_day: baseDate.getDate(),
        month_day_mode: 'clamp',
        ends: {
            type: 'endlessly',
            date: '',
            count: 10
        }
    };
}

export function validateRule(rule, startDate) {
    const validations = [
        () => validateStartDate(startDate),
        () => validateCustomInterval(rule),
        () => validateWeekDays(rule),
        () => validateMonthDay(rule),
        () => validateEndDate(rule, startDate),
        () => validateEndCount(rule)
    ];

    for (const validate of validations) {
        const error = validate();

        if (error) {
            return error;
        }
    }

    return '';
}

function validateStartDate(startDate) {
    if (!startDate) {
        return 'Please set a task date before adding repeat settings.';
    }

    return '';
}

function validateCustomInterval(rule) {
    if (
        rule.frequency === 'custom'
        && (!Number.isInteger(rule.interval)
            || rule.interval < 1
            || rule.interval > 999)
    ) {
        return 'Repeat Every must be a whole number between 1 and 999.';
    }

    return '';
}

function validateWeekDays(rule) {
    if (rule.unit === 'week' && rule.week_days.length === 0) {
        return 'Choose at least one weekday for the repeat schedule.';
    }

    return '';
}

function validateMonthDay(rule) {
    if (rule.unit !== 'month') {
        return '';
    }

    const isLastDay = rule.month_day_mode === 'last_day';

    if (
        !isLastDay
        && (!Number.isInteger(rule.month_day)
            || rule.month_day < 1
            || rule.month_day > 31)
    ) {
        return 'Choose a valid day of the month.';
    }

    return '';
}

function validateEndDate(rule, startDate) {
    if (rule.ends.type !== 'date') {
        return '';
    }

    const endDate = parseDateKey(rule.ends.date);

    if (!endDate) {
        return 'Please select an end date from the calendar.';
    }

    if (endDate <= startDate) {
        return 'The repeat end date must be after the task date.';
    }

    return '';
}

function validateEndCount(rule) {
    if (
        rule.ends.type === 'count'
        && (!Number.isInteger(rule.ends.count)
            || rule.ends.count < 1
            || rule.ends.count > 9999)
    ) {
        return 'Repeat Counts must be a whole number between 1 and 9999.';
    }

    return '';
}

export function formatRuleSummary(rule) {
    let scheduleText;

    if (rule.frequency === 'daily') {
        scheduleText = 'Repeats daily';
    } else if (rule.frequency === 'weekly') {
        scheduleText = 'Repeats weekly on ' + rule.week_days.map(function (day) {
            return WEEKDAY_NAMES[day];
        }).join(', ');
    } else if (rule.frequency === 'monthly') {
        scheduleText = rule.month_day_mode === 'last_day'
            ? 'Repeats monthly on the last day'
            : 'Repeats monthly on day ' + rule.month_day;
    } else if (rule.unit === 'day') {
        scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' day' : ' days');
    } else if (rule.unit === 'week') {
        scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' week' : ' weeks')
            + ' on ' + rule.week_days.map(function (day) { return WEEKDAY_NAMES[day]; }).join(', ');
    } else {
        scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' month' : ' months')
            + (rule.month_day_mode === 'last_day' ? ' on the last day' : ' on day ' + rule.month_day);
    }

    if (rule.ends.type === 'date') {
        return scheduleText + ' · Until ' + formatDate(parseDateKey(rule.ends.date));
    }

    if (rule.ends.type === 'count') {
        return scheduleText + ' · ' + rule.ends.count + (rule.ends.count === 1 ? ' repeat' : ' repeats');
    }

    return scheduleText + ' · Endlessly';
}
