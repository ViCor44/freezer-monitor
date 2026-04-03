/**
 * app.js – General application JavaScript
 */

// Expose BASE_URL to chart-helper (set by PHP in layouts if needed)
window.BASE_URL = window.BASE_URL || '';

// ── Dark Mode Toggle ──────────────────────────────────────────
function initDarkMode() {
    const html = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const storageKey = 'freezer-monitor-theme';

    // Load saved preference or use system preference
    function loadTheme() {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            return saved === 'dark';
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    // Apply theme
    function applyTheme(isDark) {
        if (isDark) {
            html.classList.add('dark-mode');
            if (themeToggleBtn) {
                themeToggleBtn.innerHTML = '<i class="bi bi-sun"></i>';
                themeToggleBtn.title = 'Mudar para modo claro';
            }
        } else {
            html.classList.remove('dark-mode');
            if (themeToggleBtn) {
                themeToggleBtn.innerHTML = '<i class="bi bi-moon-stars"></i>';
                themeToggleBtn.title = 'Mudar para modo escuro';
            }
        }
        localStorage.setItem(storageKey, isDark ? 'dark' : 'light');
    }

    // Initialize theme
    const isDark = loadTheme();
    applyTheme(isDark);

    // Toggle on button click
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const currentIsDark = html.classList.contains('dark-mode');
            applyTheme(!currentIsDark);
        });
    }
}

// Auto-dismiss flash alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    initDarkMode();

    const alerts = document.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(function (el) {
        setTimeout(function () {
            const bsAlert = window.bootstrap && bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });
});
