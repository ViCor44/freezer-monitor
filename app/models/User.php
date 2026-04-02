<?php

require_once __DIR__ . '/../../config/Database.php';

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
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

    public function approve(int $id): bool {
        $stmt = $this->db->prepare('UPDATE users SET approved = 1 WHERE id = ?');
        return $stmt->execute([$id]);
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
