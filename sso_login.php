<?php
/**
 * SSO Login Handler — Freezer Monitor
 * Fazer upload para: /freezer-monitor/sso_login.php no servidor
 */

define('SSO_SYSTEM_KEY', 'freezer');
define('SSO_MAP_PATH', $_SERVER['DOCUMENT_ROOT'] . '/super_login/systems_map.php');

// ── 0. Carregar configuração do mapa central ──────────────────
if (!file_exists(SSO_MAP_PATH)) {
    http_response_code(500);
    error_log('[SSO] systems_map.php não encontrado em: ' . SSO_MAP_PATH);
    exit('Erro de configuração SSO.');
}

$map    = require SSO_MAP_PATH;
$cfg    = $map['_config']      ?? null;
$system = $map[SSO_SYSTEM_KEY] ?? null;

if (!$cfg || !$system) {
    http_response_code(500);
    error_log('[SSO] Chave "' . SSO_SYSTEM_KEY . '" ou "_config" não existe em systems_map.php.');
    exit('Erro de configuração SSO.');
}

$redirectError = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/super_login/login.php';

// ── Configurar sessão igual ao index.php ──────────────────────
$sessionSavePath = __DIR__ . '/storage/sessions';
if (!is_dir($sessionSavePath)) {
    mkdir($sessionSavePath, 0700, true);
}
if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
    session_save_path($sessionSavePath);
}
session_start();

// ── 1. Validação básica do token ──────────────────────────────
$token = trim($_GET['token'] ?? '');

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    header('Location: ' . $redirectError . '?error=sso_invalid_token');
    exit;
}

try {
    // ── 2. Ligar à BD do Super Login ─────────────────────────
    $pdoSL = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // ── 3. Validar token ──────────────────────────────────────
    $stmt = $pdoSL->prepare("
        SELECT t.admin_id, m.user_id
          FROM admin_tokens t
          JOIN admin_user_map m
            ON  m.admin_id   = t.admin_id
            AND m.system_key = t.system_key
         WHERE t.token      = ?
           AND t.system_key = ?
           AND t.used       = 0
           AND t.expires_at > NOW()
         LIMIT 1
    ");
    $stmt->execute([$token, SSO_SYSTEM_KEY]);
    $row = $stmt->fetch();

    if (!$row) {
        header('Location: ' . $redirectError . '?error=sso_token_expired');
        exit;
    }

    // ── 4. Marcar token como usado ────────────────────────────
    $pdoSL->prepare("UPDATE admin_tokens SET used = 1 WHERE token = ?")
           ->execute([$token]);

    $userId = (int) $row['user_id'];

    // ── 5. Buscar dados do utilizador na BD local ─────────────
    $pdoLocal = new PDO($system['dsn'], $system['db_user'], $system['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $idField = $system['id_field'] ?? 'id';
    $tbl     = $system['users_table'];

    $stmt = $pdoLocal->prepare(
        "SELECT * FROM {$tbl} WHERE {$idField} = ? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ' . $redirectError . '?error=sso_user_not_found');
        exit;
    }

    // ── 6. Iniciar sessão (variáveis que o Freezer Monitor espera) ────
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_approved'] = (int) ($user['approved'] ?? 0);

    header('Location: ' . $system['redirect_ok']);
    exit;

} catch (PDOException $e) {
    error_log('[SSO] Erro BD (' . SSO_SYSTEM_KEY . '): ' . $e->getMessage());
    header('Location: ' . $redirectError . '?error=sso_db_error');
    exit;
}
