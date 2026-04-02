<?php

define('APP_NAME', 'Freezer Monitor');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');

// Temperature alert thresholds (°C)
define('TEMP_MAX', 5.0);
define('TEMP_MIN', -25.0);

// Session constants
define('SESSION_USER_ID', 'user_id');
define('SESSION_USER_ROLE', 'user_role');
define('SESSION_USER_NAME', 'user_name');
define('SESSION_USER_APPROVED', 'user_approved');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Alert types
define('ALERT_HIGH', 'high');
define('ALERT_LOW', 'low');
define('ALERT_OFFLINE', 'offline');

// Alert statuses
define('ALERT_STATUS_OPEN', 'open');
define('ALERT_STATUS_ACKNOWLEDGED', 'acknowledged');
define('ALERT_STATUS_RESOLVED', 'resolved');
