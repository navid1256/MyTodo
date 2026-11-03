import { translate } from './i18n.js';

/** Updates the Completed toolbar with the authoritative count returned by the server. */
export function updateCompletedCount(count) {
    const safeCount = Math.max(0, Number(count) || 0);
    const countLabel = document.querySelector('.completedCount');
    if (countLabel) {
        countLabel.textContent = String(safeCount);
    }
    document.querySelector('.completedButton')?.setAttribute(
        'aria-label', translate('task.completed_today_label', { count: safeCount })
    );
}
