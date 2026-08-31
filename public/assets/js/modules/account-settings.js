import { saveAccountSettings } from '../services/account-service.js';

const storagePrefix = 'mytodo-account-setting-';

function detectBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (error) {
        return '';
    }
}

function hasOption(select, value) {
    return Array.from(select.options).some((option) => option.value === value);
}

function setBusy(selects, isBusy) {
    selects.forEach((select) => {
        select.disabled = isBusy;
    });
}

function showStatus(statusElement, message, isError = false) {
    statusElement.textContent = message;
    statusElement.classList.toggle('isError', isError);
    statusElement.hidden = message === '';
}

function cacheSettings(settings) {
    try {
        window.localStorage.setItem(`${storagePrefix}language`, settings.language);
        window.localStorage.setItem(`${storagePrefix}calendar-system`, settings.calendar_system);
        window.localStorage.setItem(`${storagePrefix}timezone`, settings.timezone);
    } catch (error) {
        // The database remains the source of truth when browser storage is unavailable.
    }
}

function updateTimezoneContext(timezone) {
    const secureAttribute = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `mytodo_timezone=${encodeURIComponent(timezone)}; Path=/; Max-Age=31536000; SameSite=Lax${secureAttribute}`;
    document.body.dataset.renderTimezone = timezone;
    document.body.dataset.timezonePersisted = '1';
}

async function persistSettings(form, selects, statusElement, signal) {
    setBusy(selects, true);
    showStatus(statusElement, 'Saving...');

    try {
        const response = await saveAccountSettings(new FormData(form), signal);

        cacheSettings(response.settings);
        updateTimezoneContext(response.settings.timezone);
        form.dataset.settingsPersisted = '1';
        showStatus(statusElement, response.message || 'Account settings saved.');
    } catch (error) {
        if (error.name !== 'AbortError') {
            showStatus(statusElement, error.message || 'The account settings could not be saved.', true);
        }
    } finally {
        if (!signal.aborted) {
            setBusy(selects, false);
        }
    }
}

export function initAccountSettings(signal) {
    const form = document.getElementById('accountSettingsForm');
    const statusElement = document.getElementById('accountSettingsStatus');

    if (!form || !statusElement) {
        return;
    }

    const selects = Array.from(form.querySelectorAll('[data-account-setting]'));
    const timezoneSelect = form.querySelector('[data-account-setting="timezone"]');
    let activeRequestController = null;

    const saveCurrentSettings = async () => {
        if (activeRequestController) {
            activeRequestController.abort();
        }

        const requestController = new AbortController();
        activeRequestController = requestController;

        await persistSettings(form, selects, statusElement, requestController.signal);

        if (activeRequestController === requestController) {
            activeRequestController = null;
        }
    };

    signal.addEventListener('abort', () => {
        if (activeRequestController) {
            activeRequestController.abort();
        }
    }, { once: true });

    form.addEventListener('change', () => {
        saveCurrentSettings();
    }, { signal });

    if (form.dataset.settingsPersisted === '1' || !timezoneSelect) {
        return;
    }

    const browserTimezone = detectBrowserTimezone();
    if (browserTimezone !== '' && hasOption(timezoneSelect, browserTimezone)) {
        timezoneSelect.value = browserTimezone;
    }

    saveCurrentSettings();
}
