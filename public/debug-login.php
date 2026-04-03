<?php
// Load .env
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/models/User.php';

// Connect
$database = new Database();
$db = $database->conn;

if (!$db) {
    die("❌ Erro: Não conseguiu conectar à BD");
}

// Test credentials
$email = 'admin@freezer.local';
$password = 'admin123';

echo "<h2>🔍 Debug de Login</h2>";

// 1. Check if user exists
echo "<h3>1. Verificar se utilizador existe:</h3>";
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ Utilizador encontrado!<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Nome: " . $user['name'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Status: " . $user['status'] . "<br>";
    echo "Password Hash: " . $user['password'] . "<br>";
} else {
    echo "❌ Utilizador NÃO encontrado!<br>";
}

// 2. Check password
echo "<h3>2. Verificar password:</h3>";
if ($user) {
    $hash = $user['password'];
    $verify = password_verify($password, $hash);
    
    echo "Password inserida: " . $password . "<br>";
    echo "Hash na BD: " . $hash . "<br>";
    if ($verify) {
        echo "✅ Password correta!<br>";
    } else {
        echo "❌ Password incorreta!<br>";
        
        // Try common hashes
        echo "<h3>Teste de hashes comuns:</h3>";
        $test_hashes = [
            '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gZvWQi',
            password_hash('admin123', PASSWORD_DEFAULT),
        ];
        
        foreach ($test_hashes as $test_hash) {
            if (password_verify($password, $test_hash)) {
                echo "✅ Hash válido encontrado: " . $test_hash . "<br>";
            }
        }
    }
}

// 3. Generate correct hash
echo "<h3>3. Gerar hash correto para 'admin123':</h3>";
$new_hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "Use este hash: <code>" . $new_hash . "</code><br>";

// 4. Update with new hash
echo "<h3>4. Atualizar BD com novo hash:</h3>";
echo "Execute este SQL:<br>";
echo "<code>UPDATE users SET password = '" . $new_hash . "' WHERE email = 'admin@freezer.local';</code>";
?>