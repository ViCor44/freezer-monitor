<?php

declare(strict_types=1);

$root = dirname(__DIR__);

// Load .env PRIMEIRO
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

// Carregar constantes
require $root . '/config/constants.php';
require $root . '/config/Database.php';
require $root . '/app/middleware/Auth.php';
require $root . '/app/models/User.php';
require $root . '/app/models/Device.php';
require $root . '/app/models/TemperatureReading.php';
require $root . '/app/models/Alert.php';
require $root . '/app/models/Note.php';
require $root . '/app/models/RecordingPause.php';
require $root . '/app/controllers/AuthController.php';
require $root . '/app/controllers/DashboardController.php';
require $root . '/app/controllers/AdminController.php';
require $root . '/app/controllers/WebhookController.php';

// Session
if (session_status() === PHP_SESSION_NONE) {
    // Evita falhas intermitentes de permissao em C:\xampp\tmp apos periodos sem atividade.
    $sessionDir = $root . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_start();
}

// ✅ INICIALIZAR DATABASE AQUI
$database = new Database();
$db = $database->connect();

if (!$db) {
    die('Database connection failed');
}

// Routing
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
$basePath = rtrim($basePath, '/');
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Support direct /public access without changing route definitions.
if (strpos($uri, '/public/') === 0) {
    $uri = substr($uri, strlen('/public'));
}

$uri = '/' . ltrim($uri, '/');

// --- API: Última leitura do dispositivo para polling do histórico ---
if (preg_match('#^/api/device/last-reading#', $uri)) {
    require_once $root . '/app/controllers/api/DeviceApiController.php';
    (new DeviceApiController())->lastReading();
    exit;
}

$routes = [
    'GET'  => [
        '/'                    => ['AuthController',      'loginPage'],
        '/login'               => ['AuthController',      'loginPage'],
        '/register'            => ['AuthController',      'registerPage'],
        '/logout'              => ['AuthController',      'logout'],
        '/pending'             => ['AuthController',      'pending'],
        '/dashboard'           => ['DashboardController', 'index'],
        '/dashboard/device'    => ['DashboardController', 'deviceDetails'],
        '/dashboard/chart'     => ['DashboardController', 'chartData'],
        '/dashboard/get-notes' => ['DashboardController', 'getNotes'],
        '/dashboard/devices/live' => ['DashboardController', 'devicesLiveData'],
        '/admin/users'         => ['AdminController',     'users'],
        '/admin/devices'       => ['AdminController',     'devices'],
        '/admin/alerts'        => ['AdminController',     'alerts'],
    ],
    'POST' => [
        '/login'                       => ['AuthController',   'login'],
        '/register'                    => ['AuthController',   'register'],
        '/logout'                      => ['AuthController',   'logout'],
        '/dashboard/save-note'         => ['DashboardController', 'saveNote'],
        '/dashboard/devices/pause'      => ['DashboardController', 'pauseDevice'],
        '/dashboard/devices/resume'     => ['DashboardController', 'resumeDevice'],
        '/admin/users/approve'         => ['AdminController',  'approveUser'],
        '/admin/users/revoke'          => ['AdminController',  'revokeUser'],
        '/admin/users/delete'          => ['AdminController',  'deleteUser'],
        '/admin/devices/create'        => ['AdminController',  'createDevice'],
        '/admin/devices/update'        => ['AdminController',  'updateDevice'],
        '/admin/devices/delete'        => ['AdminController',  'deleteDevice'],
        '/admin/alerts/acknowledge'    => ['AdminController',  'acknowledgeAlert'],
        '/admin/alerts/resolve'        => ['AdminController',  'resolveAlert'],
        '/admin/alerts/resolve-all'    => ['AdminController',  'resolveAllAlerts'],
        '/webhook/chirpstack'          => ['WebhookController','chirpstack'],
    ],
];

$handler = $routes[$method][$uri] ?? null;

if ($handler) {
    [$controllerClass, $action] = $handler;
    
    // ✅ PASSAR $db AO CONTROLLER
    $controller = new $controllerClass($db);
    $controller->$action();
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>404 Nao encontrado</h1><p><a href="' . BASE_URL . '/login">Ir para inicio</a></p></body></html>';
}