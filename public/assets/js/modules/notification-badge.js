import { getNotificationArchive } from '../services/notification-service.js';
import { translate } from '../utils/i18n.js';

/** Updates the unread count and its accessible name from the same server value. */
export function updateNotificationBadge(count) {
    const button = document.querySelector('.notificationButton');
    if (!button) return;
    const safeCount = Math.max(0, Number(count) || 0);
    button.dataset.count = String(safeCount);
    button.removeAttribute('title');
    button.setAttribute('aria-label', translate('header.unread_notifications', { count: safeCount }));
    let badge = button.querySelector('.notificationBadge');
    if (safeCount === 0) {
        badge?.remove();
        return;
    }
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notificationBadge';
        button.appendChild(badge);
    }
    badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
}

/** Refreshes ordinary dashboard pages; the archive's read request updates its own badge. */
export async function initNotificationBadge(signal) {
    if (document.querySelector('[data-notification-archive]')) return;
    try {
        const data = await getNotificationArchive('unread', signal);
        if (!signal.aborted) updateNotificationBadge(data.unread_count);
    } catch (error) {
        if (signal.aborted) return;
        const button = document.querySelector('.notificationButton');
        if (button) button.title = translate('notifications.request_failed');
    }
}
