export function convertTo12Hour(hour24, minute) {
    return {
        hour12: hour24 % 12 || 12,
        minute: Number(minute),
        period: hour24 >= 12 ? 'PM' : 'AM'
    };
}

export function convertTo24Hour(hour12, minute, period) {
    let normalizedHour = Number(hour12) % 12;

    if (period === 'PM') {
        normalizedHour += 12;
    }

    return {
        hour24: normalizedHour,
        hour12: Number(hour12),
        minute: Number(minute),
        period: period
    };
}

export function normalizeTimeInput(value, minimum, maximum) {
    if (value === '') {
        return '';
    }

    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '';
    }

    return String(Math.min(maximum, Math.max(minimum, Math.trunc(numericValue))));
}
