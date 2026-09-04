import { translate } from '../../utils/i18n.js';

const reminderList = document.getElementById('reminderList');
const reminderCountLabel = document.getElementById('reminderCountLabel');
const reminderCount = document.getElementById('reminderCount');

export function renderPreviewItems(previewItems) {
    if (!reminderList) {
        return;
    }

    reminderList.querySelectorAll('.reminderPreview').forEach(function (preview, index) {
        const previewItem = previewItems[index];

        preview.classList.remove('is-loading', 'is-error');
        preview.classList.add('is-ready');
        preview.textContent = previewItem
            ? translate('reminder.preview.ready', { time: previewItem.formatted }, 'Your notification will be sent on {time}.')
            : '';
    });
}

export function updateCountLabel() {
    if (reminderCountLabel && reminderCount) {
        reminderCountLabel.textContent = reminderCount.value === '1'
            ? translate('reminder.modal.label')
            : translate('reminder.modal.count');
    }
}

export function updateCustomMaximum(customInput, customUnit) {
    if (!customInput) {
        return;
    }

    const maximums = {
        minutes: 525600,
        hours: 8760,
        days: 365
    };

    customInput.max = String(maximums[customUnit] || 365);
}

export function setPreviewState(message, state) {
    if (!reminderList) {
        return;
    }

    reminderList.querySelectorAll('.reminderPreview').forEach(function (preview) {
        preview.textContent = message;
        preview.classList.toggle('is-loading', state === 'loading');
        preview.classList.toggle('is-error', state === 'error');
        preview.classList.toggle('is-ready', state === 'ready');
    });
}
