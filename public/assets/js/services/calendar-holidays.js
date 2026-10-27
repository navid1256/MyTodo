import { parseDateKey } from '../utils/date-utils.js';

const cachedYears = new Map();
const pendingYears = new Map();

/** Shares date-only results and in-flight requests across every calendar on the page. */
export function getHolidayDates(year) {
    if (!Number.isInteger(year) || year < 1347 || year > 1500) {
        return Promise.resolve(new Set());
    }
    const cached = cachedYears.get(year);
    if (cached && cached.expiresAt > Date.now()) {
        return Promise.resolve(cached.dates);
    }
    if (pendingYears.has(year)) {
        return pendingYears.get(year);
    }

    const request = fetchHolidayDates(year, cached).finally(() => pendingYears.delete(year));
    pendingYears.set(year, request);
    return request;
}

/** Fetches our authenticated endpoint without blocking rendering or exposing the upstream API. */
async function fetchHolidayDates(year, previous) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
        const response = await fetch(`/api/calendar/holidays?year=${year}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (!response.ok) {
            throw new Error('Holiday endpoint unavailable.');
        }
        const data = await response.json();
        if (data.year !== year || !Array.isArray(data.dates) || data.dates.length > 366
            || !data.dates.every((date) => typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date) && parseDateKey(date))
            || !Number.isInteger(data.refresh_after) || data.refresh_after < 0) {
            throw new Error('Invalid holiday response.');
        }
        const dates = new Set(data.dates);
        cachedYears.set(year, { dates, expiresAt: Date.now() + data.refresh_after * 1000 });
        return dates;
    } catch {
        // Holidays are optional decoration; retain known dates and avoid repeated offline requests.
        const dates = previous?.dates ?? new Set();
        cachedYears.set(year, { dates, expiresAt: Date.now() + 60000 });
        return dates;
    } finally {
        clearTimeout(timeout);
    }
}
