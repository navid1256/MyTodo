function readInitialTranslations() {
    const catalog = document.getElementById('appTranslations');

    if (!catalog) {
        return {};
    }

    try {
        const translations = JSON.parse(catalog.textContent || '{}');

        return translations && typeof translations === 'object' ? translations : {};
    } catch (error) {
        return {};
    }
}

let currentTranslations = readInitialTranslations();

function interpolate(message, replacements = {}) {
    return message.replace(/\{([a-zA-Z0-9_]+)\}/g, (token, name) => replacements[name] ?? token);
}

function elementReplacements(element) {
    return element.dataset;
}

export function translate(key, replacements = {}, fallback = key) {
    const message = currentTranslations[key];

    return interpolate(typeof message === 'string' ? message : fallback, replacements);
}

export function applyTranslations(translations, root = document) {
    if (!translations || typeof translations !== 'object') {
        return;
    }

    currentTranslations = { ...currentTranslations, ...translations };

    root.querySelectorAll('[data-i18n]').forEach((element) => {
        const key = element.dataset.i18n;
        const translation = currentTranslations[key];

        if (typeof translation === 'string') {
            element.textContent = interpolate(translation, elementReplacements(element));
        }
    });

    root.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
        const key = element.dataset.i18nPlaceholder;
        const translation = currentTranslations[key];

        if (typeof translation === 'string') {
            element.setAttribute('placeholder', interpolate(translation, elementReplacements(element)));
        }
    });

    root.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const key = element.dataset.i18nAriaLabel;
        const translation = currentTranslations[key];

        if (typeof translation === 'string') {
            element.setAttribute('aria-label', interpolate(translation, elementReplacements(element)));
        }
    });

    root.querySelectorAll('[data-i18n-alt]').forEach((element) => {
        const key = element.dataset.i18nAlt;
        const translation = currentTranslations[key];

        if (typeof translation === 'string') {
            element.setAttribute('alt', interpolate(translation, elementReplacements(element)));
        }
    });

    if (root === document && typeof currentTranslations['app.name'] === 'string') {
        document.title = currentTranslations['app.name'];
    }

    const themeToggle = document.getElementById('themeToggle');
    const themeLabel = document.getElementById('themeLabel');
    const darkModeLabel = currentTranslations['theme.dark_mode'];
    const lightModeLabel = currentTranslations['theme.light_mode'];

    if (themeToggle && typeof darkModeLabel === 'string' && typeof lightModeLabel === 'string') {
        themeToggle.dataset.darkModeLabel = darkModeLabel;
        themeToggle.dataset.lightModeLabel = lightModeLabel;

        if (themeLabel) {
            themeLabel.textContent = document.documentElement.classList.contains('dark-mode')
                ? lightModeLabel
                : darkModeLabel;
        }
    }
}
