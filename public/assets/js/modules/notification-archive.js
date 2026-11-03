import { changeNotificationArchive, getNotificationArchive } from '../services/notification-service.js';
import { updateNotificationBadge } from './notification-badge.js';
import { translate } from '../utils/i18n.js';

/** Connects read state, bounded Load More, and confirmed Clear to the current archive view. */
export function initNotificationArchive(signal) {
    if (signal.aborted) return;
    const page = document.querySelector('[data-notification-archive]');
    if (!page) return;
    const list = page.querySelector('[data-sent-notifications]');
    const clearButton = page.querySelector('[data-clear-notifications]');
    const moreButton = page.querySelector('[data-load-notifications]');
    const message = page.querySelector('[data-archive-message]');
    const template = page.querySelector('[data-sent-notification-template]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let busy = false;

    /** Announces completed actions or errors without reserving space for an empty message. */
    function showMessage(key, isError = false, replacements = {}) {
        message.textContent = translate(key, replacements);
        message.hidden = false;
        message.classList.toggle('is-error', isError);
    }

    /** Serializes archive actions and exposes busy state to assistive technology. */
    function setBusy(value, action) {
        busy = value;
        list.setAttribute('aria-busy', String(value));
        clearButton.disabled = value || !list.querySelector('[data-sent-notification]');
        moreButton.disabled = value;
        clearButton.querySelector('span').textContent = translate(value && action === 'clear' ? 'notifications.clearing' : 'notifications.clear');
        moreButton.textContent = translate(value && action === 'load' ? 'notifications.loading' : 'notifications.load_more');
    }

    /** Clones trusted markup and inserts task titles and date strings as text, never HTML. */
    function createItem(item) {
        const row = template.content.firstElementChild.cloneNode(true);
        row.dataset.sentNotification = String(item.id);
        row.querySelector('[data-notification-title]').textContent = item.title;
        row.querySelector('[data-notification-date="sent_at"]').textContent = item.sent_at;
        row.querySelector('[data-notification-date="remind_at"]').textContent = item.remind_at;
        row.querySelector('[data-notification-date="task_due_at"]').textContent = item.due_at;
        return row;
    }

    /** Persists viewing before enabling actions, preventing stale read responses from overwriting Clear. */
    async function markRead() {
        setBusy(true, 'read');
        try {
            const data = await changeNotificationArchive('read', csrfToken, signal);
            if (!signal.aborted) updateNotificationBadge(data.unread_count);
        } catch (error) {
            if (!signal.aborted) showMessage('notifications.read_failed', true);
        } finally {
            if (!signal.aborted) setBusy(false, 'read');
        }
    }

    /** Appends the next cursor page once and keeps retry available when a request fails. */
    async function loadMore() {
        if (busy || !page.dataset.nextCursor) return;
        setBusy(true, 'load');
        try {
            const data = await getNotificationArchive('sent?cursor=' + encodeURIComponent(page.dataset.nextCursor), signal);
            if (signal.aborted) return;
            const rows = data.items.map(createItem);
            list.append(...rows);
            page.dataset.nextCursor = data.next_cursor || '';
            moreButton.hidden = !data.next_cursor;
            showMessage('notifications.loaded', false, { count: rows.length });
            rows[0]?.focus();
        } catch (error) {
            if (!signal.aborted) showMessage('notifications.request_failed', true);
        } finally {
            if (!signal.aborted) setBusy(false, 'load');
        }
    }

    /** Clears rows only after server success, preserving the list on cancellation or failure. */
    async function clearArchive() {
        if (busy || !window.confirm(translate('notifications.clear_confirm'))) return;
        setBusy(true, 'clear');
        try {
            const data = await changeNotificationArchive('clear', csrfToken, signal);
            if (signal.aborted) return;
            const empty = document.createElement('li');
            empty.className = 'emptyTask notificationsEmpty';
            empty.dataset.i18n = 'notifications.sent_empty';
            empty.textContent = translate('notifications.sent_empty');
            list.replaceChildren(empty);
            page.dataset.nextCursor = '';
            moreButton.hidden = true;
            updateNotificationBadge(data.unread_count);
            showMessage('notifications.cleared');
            page.querySelector('h1').focus();
        } catch (error) {
            if (!signal.aborted) showMessage('notifications.request_failed', true);
        } finally {
            if (!signal.aborted) setBusy(false, 'clear');
        }
    }

    moreButton.addEventListener('click', loadMore, { signal });
    clearButton.addEventListener('click', clearArchive, { signal });
    markRead();
}
