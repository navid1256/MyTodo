const ACTIVITY_FILTERS = new Set(['today', 'yesterday', 'week', 'month', 'all']);
const DAY_IN_MILLISECONDS = 24 * 60 * 60 * 1000;

function parseDateKey(dateKey) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dateKey));

    if (!match) {
        return null;
    }

    return Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

export function matchesActivityFilter(dateKey, filter, todayKey) {
    if (filter === 'all') {
        return true;
    }

    const dateValue = parseDateKey(dateKey);
    const todayValue = parseDateKey(todayKey);

    if (dateValue === null || todayValue === null) {
        return false;
    }

    if (filter === 'today') {
        return dateValue === todayValue;
    }

    if (filter === 'yesterday') {
        return dateValue === todayValue - DAY_IN_MILLISECONDS;
    }

    if (filter === 'week') {
        const todayWeekday = new Date(todayValue).getUTCDay();
        const daysSinceMonday = (todayWeekday + 6) % 7;
        const weekStart = todayValue - (daysSinceMonday * DAY_IN_MILLISECONDS);
        const weekEnd = weekStart + (6 * DAY_IN_MILLISECONDS);

        return dateValue >= weekStart && dateValue <= weekEnd;
    }

    if (filter === 'month') {
        const date = new Date(dateValue);
        const today = new Date(todayValue);

        return date.getUTCFullYear() === today.getUTCFullYear()
            && date.getUTCMonth() === today.getUTCMonth();
    }

    return false;
}

export function initActivityFilter(signal) {
    const filterSelect = document.getElementById('activityFilter');
    const dateGroups = document.querySelectorAll('[data-completed-date]');
    const emptyMessage = document.getElementById('activityFilterEmpty');
    const todayKey = document.body.dataset.renderDate || '';

    if (!filterSelect || !todayKey) {
        return;
    }

    function normalizeFilter(filter) {
        return ACTIVITY_FILTERS.has(filter) ? filter : 'all';
    }

    function applyFilter(filter) {
        const activeFilter = normalizeFilter(filter);
        let visibleGroupCount = 0;

        dateGroups.forEach(function (group) {
            const isVisible = matchesActivityFilter(
                group.dataset.completedDate,
                activeFilter,
                todayKey
            );

            group.hidden = !isVisible;
            visibleGroupCount += isVisible ? 1 : 0;
        });

        filterSelect.value = activeFilter;

        if (emptyMessage) {
            emptyMessage.hidden = visibleGroupCount > 0;
        }
    }

    const initialFilter = new URL(window.location.href).searchParams.get('filter');
    applyFilter(initialFilter || filterSelect.value || 'all');

    filterSelect.addEventListener('change', function () {
        const activeFilter = normalizeFilter(filterSelect.value);
        const targetUrl = new URL(window.location.href);

        if (activeFilter === 'all') {
            targetUrl.searchParams.delete('filter');
        } else {
            targetUrl.searchParams.set('filter', activeFilter);
        }

        window.history.pushState(
            { dashboardView: 'activity', activityFilter: activeFilter },
            '',
            targetUrl.href
        );
        applyFilter(activeFilter);
    }, signal ? { signal } : undefined);
}
