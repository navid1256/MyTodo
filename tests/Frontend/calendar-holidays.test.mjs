import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = {
    getElementById: () => null,
    body: { dataset: { calendarSystem: 'jalali' } },
    documentElement: { lang: 'en' },
};
const { getHolidayDates } = await import('../../public/assets/js/services/calendar-holidays.js');
const { markCalendarHolidays } = await import('../../public/assets/js/utils/calendar-holidays.js');
const { getCalendarGrid } = await import('../../public/assets/js/utils/calendar-core.js');

/** Provides a date-only response without calling any external service. */
function response(year, dates, refreshAfter = 3600) {
    return { ok: true, json: async () => ({ year, dates, refresh_after: refreshAfter, available: true, stale: false }) };
}

/** Models only the calendar button and weekday contracts used by the holiday decoration. */
function element(parentElement = null) {
    const classes = new Set();
    const attributes = new Map([['aria-label', 'Calendar date']]);
    return {
        parentElement,
        classList: { toggle(name, active) { if (active) classes.add(name); else classes.delete(name); }, contains: (name) => classes.has(name) },
        getAttribute: (name) => attributes.get(name),
        setAttribute: (name, value) => attributes.set(name, value),
    };
}

test('all calendars share one pending request and refresh only after the server TTL', async (context) => {
    let now = 10000;
    context.mock.method(Date, 'now', () => now);
    let requests = 0;
    globalThis.fetch = async (url, options) => {
        requests++;
        assert.equal(url, '/api/calendar/holidays?year=1405');
        assert.equal(options.credentials, 'same-origin');
        return response(1405, ['2026-03-21'], 1);
    };
    const results = await Promise.all([getHolidayDates(1405), getHolidayDates(1405), getHolidayDates(1405)]);
    assert.equal(requests, 1);
    assert.ok(results.every((dates) => dates.has('2026-03-21')));
    await getHolidayDates(1405);
    assert.equal(requests, 1);
    now += 1001;
    await getHolidayDates(1405);
    assert.equal(requests, 2);
});

test('an unavailable endpoint preserves Friday styling and never rejects calendar rendering', async () => {
    globalThis.fetch = async () => { throw new Error('Offline'); };
    const grid = getCalendarGrid(new Date(2028, 5, 1), 'jalali');
    const days = { children: [] };
    days.children = grid.map(() => element(days));
    const weekdays = { children: Array.from({ length: 7 }, () => element()) };
    const updating = markCalendarHolidays(days, weekdays, grid);
    grid.forEach((day, index) => assert.equal(days.children[index].classList.contains('is-calendar-holiday'), day.date.getDay() === 5));
    assert.ok(weekdays.children[6].classList.contains('is-calendar-friday'));
    await updating;
    assert.equal(days.children.length, 42);
});

test('Gregorian and Jalali grids mark the same real holiday without changing button labels or selection', async () => {
    globalThis.fetch = async (url) => {
        const year = Number(new URL(url, 'https://mytodo.php').searchParams.get('year'));
        return response(year, year === 1405 ? ['2026-04-02'] : []);
    };
    for (const calendarSystem of ['gregorian', 'jalali']) {
        document.body.dataset.calendarSystem = calendarSystem;
        const grid = getCalendarGrid(new Date(2026, 3, 2), calendarSystem, 1);
        const days = { children: [] };
        days.children = grid.map(() => element(days));
        const weekdays = { children: Array.from({ length: 7 }, () => element()) };
        const selectedIndex = grid.findIndex((day) => day.dateKey === '2026-04-02');
        days.children[selectedIndex].classList.toggle('selected', true);
        await markCalendarHolidays(days, weekdays, grid, 1);
        assert.ok(days.children[selectedIndex].classList.contains('is-calendar-holiday'));
        assert.ok(days.children[selectedIndex].classList.contains('selected'));
        assert.equal(days.children[selectedIndex].getAttribute('aria-label'),
            calendarSystem === 'jalali' ? 'Calendar date, تعطیل رسمی' : 'Calendar date, Public holiday');
        assert.ok(weekdays.children[calendarSystem === 'jalali' ? 6 : 4].classList.contains('is-calendar-friday'));
    }
});

test('unsupported years are ignored locally without contacting the endpoint', async () => {
    let requests = 0;
    globalThis.fetch = async () => { requests++; throw new Error('Unexpected request'); };
    assert.equal((await getHolidayDates(1300)).size, 0);
    assert.equal((await getHolidayDates('../1405')).size, 0);
    assert.equal(requests, 0);
});

test('a Gregorian month around Nowruz loads both Jalali years', async () => {
    document.body.dataset.calendarSystem = 'gregorian';
    const requestedYears = [];
    globalThis.fetch = async (url) => {
        const year = Number(new URL(url, 'https://mytodo.php').searchParams.get('year'));
        requestedYears.push(year);
        return response(year, year === 1409 ? ['2030-04-02'] : []);
    };
    const grid = getCalendarGrid(new Date(2030, 2, 1), 'gregorian');
    const days = { children: [] };
    days.children = grid.map(() => element(days));
    await markCalendarHolidays(days, null, grid);
    assert.deepEqual(requestedYears.sort(), [1408, 1409]);
    const holiday = grid.findIndex((day) => day.dateKey === '2030-04-02');
    assert.ok(days.children[holiday].classList.contains('is-calendar-holiday'));
});

test('a delayed response never decorates replacement calendar buttons', async () => {
    let finish;
    globalThis.fetch = () => new Promise((resolve) => { finish = resolve; });
    const grid = getCalendarGrid(new Date(2032, 5, 1), 'gregorian');
    const days = { children: [] };
    days.children = grid.map(() => element(days));
    const outdated = days.children;
    const pending = markCalendarHolidays(days, null, grid);
    outdated.forEach((button) => { button.parentElement = null; });
    days.children = grid.map(() => element(days));
    finish(response(1411, [grid[0].dateKey]));
    await pending;
    assert.ok(days.children.every((button) => !button.classList.contains('is-calendar-holiday')));
    assert.ok(outdated.every((button) => button.getAttribute('aria-label') === 'Calendar date'));
});
