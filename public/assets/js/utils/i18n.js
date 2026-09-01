function interpolate(message, element) {
    return message.replace(/\{([a-zA-Z0-9_]+)\}/g, (token, name) => element.dataset[name] ?? token);
}

export function applyTranslations(translations, root = document) {
    if (!translations || typeof translations !== 'object') {
        return;
    }

    root.querySelectorAll('[data-i18n]').forEach((element) => {
        const key = element.dataset.i18n;
        const translation = translations[key];

        if (typeof translation === 'string') {
            element.textContent = interpolate(translation, element);
        }
    });

    root.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const key = element.dataset.i18nAriaLabel;
        const translation = translations[key];

        if (typeof translation === 'string') {
            element.setAttribute('aria-label', interpolate(translation, element));
        }
    });

    if (root === document && typeof translations['app.name'] === 'string') {
        document.title = translations['app.name'];
    }

    const themeToggle = document.getElementById('themeToggle');
    const themeLabel = document.getElementById('themeLabel');
    const darkModeLabel = translations['theme.dark_mode'];
    const lightModeLabel = translations['theme.light_mode'];

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
