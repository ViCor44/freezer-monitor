/**
 * app.js – General application JavaScript
 */

// Expose BASE_URL to chart-helper (set by PHP in layouts if needed)
window.BASE_URL = window.BASE_URL || '';

// Auto-dismiss flash alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(function (el) {
        setTimeout(function () {
            const bsAlert = window.bootstrap && bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });
});
