<?php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST');
        $this->db_name = getenv('DB_NAME');
        $this->username = getenv('DB_USER');
        $this->password = getenv('DB_PASS');
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Alinha o fuso da sessao MySQL com o do PHP para que NOW()/CURDATE()
            // e as leituras de DATETIME em PHP (strtotime, DateTime, etc.) usem
            // sempre a mesma referencia horaria. Sem isto, um servidor com o
            // OS/MySQL em UTC (ou noutro TZ) faz com que os alarmes com base em
            // idade (>= 60 min) disparem prematuramente.
            try {
                $offset = (new DateTime('now'))->format('P'); // ex.: "+01:00"
                $stmt = $this->conn->prepare('SET time_zone = ?');
                $stmt->execute([$offset]);
            } catch (Throwable $e) {
                // Ignora: se a base de dados nao aceitar o SET, mantem TZ default.
            }
        } catch (PDOException $exception) {
            echo 'Connection error: ' . $exception->getMessage();
        }
    }

    public function connect() {
        return $this->conn;
    }

    public function getConnection() {  // ← ADICIONE ESTE MÉTODO
        return $this->conn;
    }
}
?>