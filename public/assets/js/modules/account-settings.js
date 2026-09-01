import { saveAccountSettings } from '../services/account-service.js';
import { applyTranslations } from '../utils/i18n.js';

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

function setBusy(form, selects, saveButton, isBusy) {
    form.setAttribute('aria-busy', String(isBusy));

    selects.forEach((select) => {
        select.disabled = isBusy;
    });

    saveButton.disabled = isBusy;
    saveButton.textContent = isBusy
        ? (form.dataset.savingMessage || 'Saving...')
        : (form.dataset.saveLabel || 'Save');
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
    document.documentElement.dir = normalizedLanguage === 'persian' ? 'rtl' : 'ltr';
    document.body.dataset.effectiveLanguage = normalizedLanguage;
}

function updateCalendarContext(calendarSystem) {
    document.body.dataset.calendarSystem = calendarSystem === 'jalali' ? 'jalali' : 'gregorian';
}

function updateTranslatedMessages(form, translations) {
    if (!translations || typeof translations !== 'object') {
        return;
    }

    form.dataset.saveLabel = translations['common.save'] || form.dataset.saveLabel;
    form.dataset.savingMessage = translations['common.saving'] || form.dataset.savingMessage;
    form.dataset.cacheUnavailableMessage = translations['settings.cache_unavailable']
        || form.dataset.cacheUnavailableMessage;
    form.dataset.saveFailedMessage = translations['settings.save_failed'] || form.dataset.saveFailedMessage;
}

async function persistSettings(form, selects, saveButton, statusElement, signal) {
    const formData = new FormData(form);

    setBusy(form, selects, saveButton, true);
    showStatus(statusElement, form.dataset.savingMessage || 'Saving...');

    try {
        const response = await saveAccountSettings(
            formData,
            signal,
            form.dataset.saveFailedMessage || 'The account settings could not be saved.'
        );

        const settingsWereCached = cacheSettings(response.settings);
        updateTimezoneContext(response.settings.timezone);
        updateLanguageContext(response.settings.effective_language);
        updateCalendarContext(response.settings.calendar_system);
        applyTranslations(response.translations);
        updateTranslatedMessages(form, response.translations);
        form.dataset.settingsPersisted = '1';
        const successMessage = response.message || 'Account settings saved.';

        showStatus(
            statusElement,
            settingsWereCached
                ? successMessage
                : `${successMessage} ${form.dataset.cacheUnavailableMessage || 'Browser cache is unavailable.'}`
        );
    } catch (error) {
        if (error.name !== 'AbortError') {
            showStatus(statusElement, error.message || 'The account settings could not be saved.', true);
        }
    } finally {
        if (!signal.aborted) {
            setBusy(form, selects, saveButton, false);
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
