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

function setBusy(form, selects, saveButton, idleButtonLabel, isBusy) {
    form.setAttribute('aria-busy', String(isBusy));

    selects.forEach((select) => {
        select.disabled = isBusy;
    });

    saveButton.disabled = isBusy;
    saveButton.textContent = isBusy ? 'Saving...' : idleButtonLabel;
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
        return true;
    } catch {
        return false;
    }
}

function updateTimezoneContext(timezone) {
    const secureAttribute = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `mytodo_timezone=${encodeURIComponent(timezone)}; Path=/; Max-Age=31536000; SameSite=Lax${secureAttribute}`;
    document.body.dataset.renderTimezone = timezone;
    document.body.dataset.timezonePersisted = '1';
}

function updateLanguageContext(effectiveLanguage) {
    const normalizedLanguage = effectiveLanguage === 'persian' ? 'persian' : 'english';

    document.documentElement.lang = normalizedLanguage === 'persian' ? 'fa' : 'en';
    document.body.dataset.effectiveLanguage = normalizedLanguage;
}

function updateCalendarContext(calendarSystem) {
    document.body.dataset.calendarSystem = calendarSystem === 'jalali' ? 'jalali' : 'gregorian';
}

async function persistSettings(form, selects, saveButton, idleButtonLabel, statusElement, signal) {
    setBusy(form, selects, saveButton, idleButtonLabel, true);
    showStatus(statusElement, 'Saving...');

    try {
        const response = await saveAccountSettings(new FormData(form), signal);

        const settingsWereCached = cacheSettings(response.settings);
        updateTimezoneContext(response.settings.timezone);
        updateLanguageContext(response.settings.effective_language);
        updateCalendarContext(response.settings.calendar_system);
        form.dataset.settingsPersisted = '1';
        const successMessage = response.message || 'Account settings saved.';

        showStatus(
            statusElement,
            settingsWereCached
                ? successMessage
                : `${successMessage} Browser cache is unavailable.`
        );
    } catch (error) {
        if (error.name !== 'AbortError') {
            showStatus(statusElement, error.message || 'The account settings could not be saved.', true);
        }
    } finally {
        if (!signal.aborted) {
            setBusy(form, selects, saveButton, idleButtonLabel, false);
        }
    }
}

export function initAccountSettings(signal) {
    const form = document.getElementById('accountSettingsForm');
    const statusElement = document.getElementById('accountSettingsStatus');
    const saveButton = document.getElementById('accountSettingsSaveButton');

    if (!form || !statusElement || !saveButton) {
        return;
    }

    const selects = Array.from(form.querySelectorAll('[data-account-setting]'));
    const timezoneSelect = form.querySelector('[data-account-setting="timezone"]');
    const idleButtonLabel = saveButton.textContent.trim() || 'Save';
    let activeRequestController = null;

    const saveCurrentSettings = async () => {
        if (activeRequestController) {
            activeRequestController.abort();
        }

        const requestController = new AbortController();
        activeRequestController = requestController;

        await persistSettings(
            form,
            selects,
            saveButton,
            idleButtonLabel,
            statusElement,
            requestController.signal
        );

        if (activeRequestController === requestController) {
            activeRequestController = null;
        }
    };

    signal.addEventListener('abort', () => {
        if (activeRequestController) {
            activeRequestController.abort();
        }
    }, { once: true });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        saveCurrentSettings();
    }, { signal });

    form.addEventListener('change', () => {
        showStatus(statusElement, '');
    }, { signal });

    if (form.dataset.settingsPersisted === '1' || !timezoneSelect) {
        return;
    }

    const browserTimezone = detectBrowserTimezone();
    if (browserTimezone !== '' && hasOption(timezoneSelect, browserTimezone)) {
        timezoneSelect.value = browserTimezone;
    }
}
