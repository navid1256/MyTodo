export function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeLabel = document.getElementById('themeLabel');

    function applyTheme(isDarkMode, shouldSave) {
        document.documentElement.classList.toggle('dark-mode', isDarkMode);

        if (themeToggle) {
            themeToggle.setAttribute('aria-pressed', String(isDarkMode));
        }

        if (themeLabel) {
            themeLabel.textContent = isDarkMode ? 'Light Mode' : 'Dark Mode';
        }

        if (themeIcon) {
            themeIcon.classList.toggle('fa-moon', !isDarkMode);
            themeIcon.classList.toggle('fa-sun', isDarkMode);
        }

        if (shouldSave) {
            try {
                localStorage.setItem('mytodo-theme', isDarkMode ? 'dark' : 'light');
            } catch (error) {
                // Theme switching still works when browser storage is unavailable.
            }
        }
    }

    applyTheme(document.documentElement.classList.contains('dark-mode'), false);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const isDarkMode = !document.documentElement.classList.contains('dark-mode');
            applyTheme(isDarkMode, true);
        });
    }
}
