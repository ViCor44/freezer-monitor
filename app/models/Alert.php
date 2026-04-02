<?php

require_once __DIR__ . '/../../config/Database.php';

class Alert {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function create(int $deviceId, string $type, string $message, ?float $temperature = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO alerts (device_id, type, message, temperature) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$deviceId, $type, $message, $temperature]);
        return (int) $this->db->lastInsertId();
    }

    public function getAll(string $status = ''): array {
        $sql = 'SELECT a.*, d.name AS device_name FROM alerts a
                INNER JOIN devices d ON d.id = a.device_id';
        $params = [];

        if ($status !== '') {
            $sql .= ' WHERE a.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            'SELECT a.*, d.name AS device_name FROM alerts a
             INNER JOIN devices d ON d.id = a.device_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function acknowledge(int $id, int $userId): bool {
        $stmt = $this->db->prepare(
            'UPDATE alerts SET status = ?, acknowledged_by = ?, acknowledged_at = NOW()
             WHERE id = ? AND status = ?'
        );
        return $stmt->execute([ALERT_STATUS_ACKNOWLEDGED, $userId, $id, ALERT_STATUS_OPEN]);
    }

    public function resolve(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE alerts SET status = ?, resolved_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([ALERT_STATUS_RESOLVED, $id]);
    }

    public function countOpen(): int {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM alerts WHERE status = 'open'"
        )->fetchColumn();
    }

    public function hasOpenAlert(int $deviceId, string $type): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM alerts WHERE device_id = ? AND type = ? AND status = 'open'"
        );
        $stmt->execute([$deviceId, $type]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
