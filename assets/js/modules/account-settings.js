const storagePrefix = 'mytodo-account-setting-';

function getStorageKey(settingName) {
    return `${storagePrefix}${settingName}`;
}

function restoreSelectedValue(select) {
    const settingName = select.dataset.accountSetting;

    if (!settingName) {
        return;
    }

    try {
        const savedValue = window.localStorage.getItem(getStorageKey(settingName));

        if (savedValue !== null && Array.from(select.options).some((option) => option.value === savedValue)) {
            select.value = savedValue;
        }
    } catch (error) {
        // The select remains usable when browser storage is unavailable.
    }
}

function saveSelectedValue(event) {
    const select = event.currentTarget;
    const settingName = select.dataset.accountSetting;

    if (!settingName) {
        return;
    }

    try {
        window.localStorage.setItem(getStorageKey(settingName), select.value);
    } catch (error) {
        // The selection still works for the current page when storage is unavailable.
    }
}

export function initAccountSettings(signal) {
    const settingSelects = document.querySelectorAll('[data-account-setting]');

    settingSelects.forEach((select) => {
        restoreSelectedValue(select);
        select.addEventListener('change', saveSelectedValue, { signal });
    });
}
