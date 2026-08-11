export function convertTo12Hour(hour24, minute) {
    return {
        hour12: hour24 % 12 || 12,
        minute: Number(minute),
        period: hour24 >= 12 ? 'PM' : 'AM'
    };
}

export function convertTo24Hour(hour12, minute, period) {
    var normalizedHour = Number(hour12) % 12;

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
