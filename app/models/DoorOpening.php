<?php

require_once __DIR__ . '/../../config/Database.php';

class DoorOpening {
    private $db;
    private $table = 'door_openings';

    public function __construct($db) {
        $this->db = $db;
    }

    public function create(int $deviceId, ?int $readingId = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO door_openings (device_id, reading_id) VALUES (?, ?)'
        );
        $stmt->execute([$deviceId, $readingId]);
        return (int) $this->db->lastInsertId();
    }

    public function getLast24Hours(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT opened_at FROM door_openings
             WHERE device_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY opened_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLast7Days(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT opened_at FROM door_openings
             WHERE device_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY opened_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLast30Days(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT opened_at FROM door_openings
             WHERE device_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY opened_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByRange(int $deviceId, string $from, string $to): array {
        $stmt = $this->db->prepare(
            'SELECT opened_at FROM door_openings
             WHERE device_id = ? AND opened_at BETWEEN ? AND ?
             ORDER BY opened_at ASC'
        );
        $stmt->execute([$deviceId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
