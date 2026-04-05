<?php

// ── Application ────────────────────────────────────────────────────────────
define('ROOT', dirname(dirname(__FILE__)));

// Resolve BASE_URL automatically for XAMPP and virtual-host setups.
$configuredBaseUrl = getenv('BASE_URL');
if ($configuredBaseUrl) {
	define('BASE_URL', rtrim($configuredBaseUrl, '/'));
} else {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
	$scriptDir = rtrim($scriptDir, '/');
	$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

	// If Apache rewrites /project/* to /project/public/*, expose links without /public.
	if ($scriptDir !== '' && str_ends_with($scriptDir, '/public')) {
		$withoutPublic = substr($scriptDir, 0, -7);
		$directPublicPrefix = ($withoutPublic === '' ? '' : $withoutPublic) . '/public/';
		$isDirectPublicAccess = strpos($requestPath, $directPublicPrefix) === 0;
		$basePath = $isDirectPublicAccess ? $scriptDir : $withoutPublic;
	} else {
		$basePath = $scriptDir;
	}

	if ($basePath === '/') {
		$basePath = '';
	}

	define('BASE_URL', $scheme . '://' . $host . $basePath);
}
define('APP_NAME', 'Freezer Monitor');
define('APP_VERSION', '1.0.0');

// ── Database ───────────────────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?? 'localhost');
define('DB_NAME', getenv('DB_NAME') ?? 'freezer_monitor');
define('DB_USER', getenv('DB_USER') ?? 'root');
define('DB_PASS', getenv('DB_PASS') ?? '');

// ── User Roles ─────────────────────────────────────────────────────────────
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// ── Session Keys ───────────────────────────────────────────────────────────
define('SESSION_USER_ID', 'user_id');
define('SESSION_USER_ROLE', 'user_role');
define('SESSION_USER_NAME', 'user_name');
define('SESSION_USER_APPROVED', 'user_approved');

// ── User Status ────────────────────────────────────────────────────────────
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// ── Alert Types ────────────────────────────────────────────────────────────
define('ALERT_TEMP_HIGH', 'temp_high');
define('ALERT_TEMP_LOW', 'temp_low');
define('ALERT_OFFLINE', 'offline');

// Compatibility aliases used by older controllers/views.
define('ALERT_HIGH', ALERT_TEMP_HIGH);
define('ALERT_LOW', ALERT_TEMP_LOW);

// ── ChirpStack ─────────────────────────────────────────────────────────────
define('CHIRPSTACK_URL', getenv('CHIRPSTACK_URL') ?? 'http://191.188.126.13:8080');
define('CHIRPSTACK_USER', getenv('CHIRPSTACK_USER') ?? 'admin');
define('CHIRPSTACK_PASS', getenv('CHIRPSTACK_PASS') ?? 'admin');

// ── Temperature Limits ─────────────────────────────────────────────────────
define('DEFAULT_TEMP_MIN', 0);
define('DEFAULT_TEMP_MAX', 5);
define('TEMP_MIN', DEFAULT_TEMP_MIN);
define('TEMP_MAX', DEFAULT_TEMP_MAX);

// ── Device Status ───────────────────────────────────────────────────────────
define('DEVICE_ONLINE_WINDOW_MINUTES', (int) (getenv('DEVICE_ONLINE_WINDOW_MINUTES') ?: 5));

?>