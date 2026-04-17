<?php

require_once __DIR__ . '/../../config/Database.php';

class User {
    private $db;
    private $table = 'users';

    public function __construct($db) {  // ← Recebe a conexão como parâmetro
        $this->db = $db;
    }

    public function login($email, $password) {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        return $user;
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll(): array {
        return $this->db->query('SELECT id, name, email, role, approved, created_at FROM users ORDER BY created_at DESC')
                        ->fetchAll();
    }

    public function create(string $name, string $email, string $password): int {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role, approved) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash, ROLE_USER, 0]);
        return (int) $this->db->lastInsertId();
    }

    public function register(string $name, string $email, string $password): bool {
        $name = trim($name);
        $email = trim(strtolower($email));

        if ($name === '' || $email === '' || $password === '') {
            return false;
        }

        if ($this->findByEmail($email) !== false) {
            return false;
        }

        return $this->create($name, $email, $password) > 0;
    }

    public function approve(int $id, string $role = ROLE_USER): bool {
        if (!in_array($role, [ROLE_ADMIN, ROLE_USER], true)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE users SET approved = 1, role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }

    public function revoke(int $id): bool {
        $stmt = $this->db->prepare('UPDATE users SET approved = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function updateRole(int $id, string $role): bool {
        if (!in_array($role, [ROLE_ADMIN, ROLE_USER], true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }

    public function countPending(): int {
        return (int) $this->db->query('SELECT COUNT(*) FROM users WHERE approved = 0')->fetchColumn();
    }
}
