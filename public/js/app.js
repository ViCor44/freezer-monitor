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

function initDashboardLiveCards() {
    const cardsContainer = document.getElementById('deviceCards');
    if (!cardsContainer) {
        return;
    }

    const POLL_MS = 10000;

    function updateBadgeClass(el, className) {
        if (!el) {
            return;
        }
        el.classList.remove('bg-success', 'bg-secondary', 'bg-danger', 'bg-warning', 'bg-info', 'bg-dark');
        el.classList.add('bg-' + className);
    }

    function renderDeviceCard(device) {
        const cardRoot = cardsContainer.querySelector('[data-device-id="' + device.id + '"]');
        if (!cardRoot) {
            return;
        }

        const onlineBadge = cardRoot.querySelector('.device-online-badge');
        const tempValue = cardRoot.querySelector('.device-temp-value');
        const rangeBadge = cardRoot.querySelector('.device-range-badge');
        const lastSeen = cardRoot.querySelector('.device-last-seen');

        if (onlineBadge) {
            updateBadgeClass(onlineBadge, device.is_online ? 'success' : 'secondary');
            onlineBadge.textContent = device.is_online ? 'Online' : 'Offline';
        }

        if (tempValue) {
            tempValue.textContent = device.temperature_text || '--';
        }

        if (rangeBadge) {
            updateBadgeClass(rangeBadge, device.range_badge_class || 'secondary');
            rangeBadge.textContent = device.range_badge_text || 'Sem dados recentes';
        }

        if (lastSeen) {
            lastSeen.textContent = 'Ultima comunicacao: ' + (device.last_seen_text || 'N/A');
        }
    }

    async function refreshCards() {
        try {
            const response = await fetch((window.BASE_URL || '') + '/dashboard/devices/live', {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (!payload || !Array.isArray(payload.devices)) {
                return;
            }

            payload.devices.forEach(renderDeviceCard);
        } catch (err) {
            // Ignore transient network errors during polling.
        }
    }

    refreshCards();
    window.setInterval(refreshCards, POLL_MS);
}

// Auto-dismiss flash alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    initDarkMode();
    initDashboardLiveCards();

    const alerts = document.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(function (el) {
        setTimeout(function () {
            const bsAlert = window.bootstrap && bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });
});
