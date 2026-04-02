<?php

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────
$root = dirname(__DIR__);
require $root . '/config/constants.php';
require $root . '/config/Database.php';
require $root . '/app/middleware/Auth.php';
require $root . '/app/models/User.php';
require $root . '/app/models/Device.php';
require $root . '/app/models/TemperatureReading.php';
require $root . '/app/models/Alert.php';
require $root . '/app/controllers/AuthController.php';
require $root . '/app/controllers/DashboardController.php';
require $root . '/app/controllers/AdminController.php';
require $root . '/app/controllers/WebhookController.php';

// Load .env if present
$envFile = $root . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// ── Session ────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Routing ────────────────────────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Strip base path if app is in a subdirectory
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

$uri = '/' . ltrim($uri, '/');

$routes = [
    'GET'  => [
        '/'                    => ['AuthController',      'loginPage'],
        '/login'               => ['AuthController',      'loginPage'],
        '/register'            => ['AuthController',      'registerPage'],
        '/logout'              => ['AuthController',      'logout'],
        '/pending'             => ['AuthController',      'pending'],
        '/dashboard'           => ['DashboardController', 'index'],
        '/dashboard/chart'     => ['DashboardController', 'chartData'],
        '/admin/users'         => ['AdminController',     'users'],
        '/admin/devices'       => ['AdminController',     'devices'],
        '/admin/alerts'        => ['AdminController',     'alerts'],
    ],
    'POST' => [
        '/login'                       => ['AuthController',   'login'],
        '/register'                    => ['AuthController',   'register'],
        '/logout'                      => ['AuthController',   'logout'],
        '/admin/users/approve'         => ['AdminController',  'approveUser'],
        '/admin/users/revoke'          => ['AdminController',  'revokeUser'],
        '/admin/users/delete'          => ['AdminController',  'deleteUser'],
        '/admin/devices/create'        => ['AdminController',  'createDevice'],
        '/admin/devices/update'        => ['AdminController',  'updateDevice'],
        '/admin/devices/delete'        => ['AdminController',  'deleteDevice'],
        '/admin/alerts/acknowledge'    => ['AdminController',  'acknowledgeAlert'],
        '/admin/alerts/resolve'        => ['AdminController',  'resolveAlert'],
        '/webhook/chirpstack'          => ['WebhookController','chirpstack'],
    ],
];

$handler = $routes[$method][$uri] ?? null;

if ($handler) {
    [$controllerClass, $action] = $handler;
    $controller = new $controllerClass();
    $controller->$action();
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1><p><a href="' . BASE_URL . '/login">Go home</a></p></body></html>';
}
