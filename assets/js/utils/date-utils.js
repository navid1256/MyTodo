export function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function addDays(date, numberOfDays) {
    var result = startOfDay(date);
    result.setDate(result.getDate() + numberOfDays);
    return result;
}

export function datesAreEqual(firstDate, secondDate) {
    if (firstDate && secondDate
        && firstDate.getFullYear() === secondDate.getFullYear()
        && firstDate.getMonth() === secondDate.getMonth()
        && firstDate.getDate() === secondDate.getDate()
    ) {
        return true;
    }

    return false;
}

export function formatDateKey(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

export function parseDateKey(dateKey) {
    var parts = dateKey.split('-').map(Number);

    if (parts.length !== 3 || parts.some(function (part) { return !Number.isInteger(part); })) {
        return null;
    }

    var parsedDate = new Date(parts[0], parts[1] - 1, parts[2]);
    return formatDateKey(parsedDate) === dateKey ? parsedDate : null;
}

