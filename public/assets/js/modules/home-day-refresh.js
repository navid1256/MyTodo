function getLocalDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return year + '-' + month + '-' + day;
}

export function initHomeDayRefresh(signal) {
    if (document.body.dataset.activeView !== 'home') {
        return;
    }

    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const renderTimezone = document.body.dataset.renderTimezone || '';

    const timezoneIsPersisted = document.body.dataset.timezonePersisted === '1';

    if (!timezoneIsPersisted && browserTimezone && browserTimezone !== renderTimezone) {
        document.cookie = 'mytodo_timezone=' + encodeURIComponent(browserTimezone)
            + '; path=/; max-age=31536000; SameSite=Lax';
        window.location.reload();
        return;
    }

    const renderedDateKey = document.body.dataset.renderDate || getLocalDateKey(new Date());

    function refreshWhenDayChanges() {
        if (getLocalDateKey(new Date()) !== renderedDateKey) {
            window.location.reload();
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshWhenDayChanges();
        }
    }, signal ? { signal } : undefined);

    window.addEventListener('focus', refreshWhenDayChanges, signal ? { signal } : undefined);
}
