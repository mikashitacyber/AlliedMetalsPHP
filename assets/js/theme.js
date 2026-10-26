document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) return;

    function updateButton() {
        const isDark = document.documentElement.dataset.theme === 'dark';
        toggle.textContent = isDark ? '☀' : '☾';
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} appearance`);
        toggle.setAttribute('title', `Switch to ${isDark ? 'light' : 'dark'} appearance`);
    }

    updateButton();
    toggle.addEventListener('click', () => {
        const isDark = document.documentElement.dataset.theme !== 'dark';
        if (isDark) {
            document.documentElement.dataset.theme = 'dark';
        } else {
            delete document.documentElement.dataset.theme;
        }
        try {
            localStorage.setItem('allied-metals-theme', isDark ? 'dark' : 'light');
        } catch (error) {
            console.warn('Could not save the theme preference.', error);
        }
        updateButton();
    });
});
