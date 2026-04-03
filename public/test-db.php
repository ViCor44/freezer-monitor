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

echo "<h2>Variáveis de Ambiente:</h2>";
echo "DB_HOST: " . getenv('DB_HOST') . "<br>";
echo "DB_NAME: " . getenv('DB_NAME') . "<br>";
echo "DB_USER: " . getenv('DB_USER') . "<br>";

echo "<h2>Teste de Conexão:</h2>";
try {
    $conn = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS')
    );
    echo "✅ <strong>Conexão à BD OK!</strong>";
    
    // Test users table
    $stmt = $conn->prepare("SELECT * FROM users LIMIT 1");
    $stmt->execute();
    echo "<br>✅ <strong>Tabela users existe!</strong>";
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
}
?>