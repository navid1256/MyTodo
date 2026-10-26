import { formatDate, parseDateKey } from '../../utils/date-utils.js';
import { translate } from '../../utils/i18n.js';

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
        return translate('repeat.validation.date_required', {}, 'Please set a task date before adding repeat settings.');
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
        return translate('repeat.validation.interval', {}, 'Repeat Every must be a whole number between 1 and 999.');
    }

    return '';
}

function validateWeekDays(rule) {
    if (rule.unit === 'week' && rule.week_days.length === 0) {
        return translate('repeat.validation.week_days', {}, 'Choose at least one weekday for the repeat schedule.');
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
        return translate('repeat.validation.month_day', {}, 'Choose a valid day of the month.');
    }

    return '';
}

function validateEndDate(rule, startDate) {
    if (rule.ends.type !== 'date') {
        return '';
    }

    const endDate = parseDateKey(rule.ends.date);

    if (!endDate) {
        return translate('repeat.validation.end_date_required', {}, 'Please select an end date from the calendar.');
    }

    if (endDate <= startDate) {
        return translate('repeat.validation.end_date_after', {}, 'The repeat end date must be after the task date.');
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
        return translate('repeat.validation.count', {}, 'Repeat Counts must be a whole number between 1 and 9999.');
    }

    return '';
}

export function formatRuleSummary(rule) {
    let scheduleText;

    if (rule.frequency === 'daily') {
        scheduleText = translate('repeat.summary.daily', {}, 'Repeats daily');
    } else if (rule.frequency === 'weekly') {
        scheduleText = translate('repeat.summary.weekly', { days: rule.week_days.map(function (day) {
            return WEEKDAY_NAMES[day];
        }).join(', ') }, 'Repeats weekly on {days}');
    } else if (rule.frequency === 'monthly') {
        scheduleText = rule.month_day_mode === 'last_day'
            ? translate('repeat.summary.monthly_last_day', {}, 'Repeats monthly on the last day')
            : translate('repeat.summary.monthly_day', { day: rule.month_day }, 'Repeats monthly on day {day}');
    } else if (rule.unit === 'day') {
        scheduleText = rule.interval === 1
            ? translate('repeat.summary.every_day_one', {}, 'Repeats every day')
            : translate('repeat.summary.every_days', { interval: rule.interval }, 'Repeats every {interval} days');
    } else if (rule.unit === 'week') {
        scheduleText = rule.interval === 1
            ? translate('repeat.summary.every_week_one', { days: rule.week_days.map(function (day) { return WEEKDAY_NAMES[day]; }).join(', ') }, 'Repeats every week on {days}')
            : translate('repeat.summary.every_weeks', {
                interval: rule.interval,
                days: rule.week_days.map(function (day) { return WEEKDAY_NAMES[day]; }).join(', ')
            }, 'Repeats every {interval} weeks on {days}');
    } else {
        scheduleText = rule.month_day_mode === 'last_day'
            ? (rule.interval === 1
                ? translate('repeat.summary.every_month_one_last_day', {}, 'Repeats every month on the last day')
                : translate('repeat.summary.every_months_last_day', { interval: rule.interval }, 'Repeats every {interval} months on the last day'))
            : (rule.interval === 1
                ? translate('repeat.summary.every_month_one_day', { day: rule.month_day }, 'Repeats every month on day {day}')
                : translate('repeat.summary.every_months_day', { interval: rule.interval, day: rule.month_day }, 'Repeats every {interval} months on day {day}'));
    }

    if (rule.ends.type === 'date') {
        return scheduleText + ' · ' + translate('repeat.summary.until', { date: formatDate(parseDateKey(rule.ends.date)) }, 'Until {date}');
    }

    if (rule.ends.type === 'count') {
        return scheduleText + ' · ' + translate('repeat.summary.count', { count: rule.ends.count }, '{count} repeats');
    }

    return scheduleText + ' · ' + translate('repeat.end.endlessly');
}
