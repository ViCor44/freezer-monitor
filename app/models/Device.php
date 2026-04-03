<?php

require_once __DIR__ . '/../../config/Database.php';

if (!defined('TEMP_MAX')) {
    define('TEMP_MAX', 0);
}
if (!defined('TEMP_MIN')) {
    define('TEMP_MIN', -25);
}

class Device {
    private $db;
    private $table = 'devices';

    public function __construct($db) {  // ← Recebe $db
        $this->db = $db;
    }

    public function getAll() {
        $sql = "SELECT d.*, 
                       (
                           SELECT r.temperature
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC
                           LIMIT 1
                       ) AS last_temp,
                       (
                           SELECT r.recorded_at
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC
                           LIMIT 1
                       ) AS last_reading
                FROM {$this->table} d
                ORDER BY d.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function findById(int $id): array|false {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByDevEui(string $devEui): array|false {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE dev_eui = ? LIMIT 1');
        $stmt->execute([$devEui]);
        return $stmt->fetch();
    }

    public function create(string $name, string $devEui, string $location = '', float $tempMax = TEMP_MAX, float $tempMin = TEMP_MIN): int {
        $stmt = $this->db->prepare(
            'INSERT INTO devices (name, dev_eui, location, temp_max, temp_min) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, strtolower($devEui), $location, $tempMax, $tempMin]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $location, float $tempMax, float $tempMin, int $active): bool {
        $stmt = $this->db->prepare(
            'UPDATE devices SET name = ?, location = ?, temp_max = ?, temp_min = ?, active = ? WHERE id = ?'
        );
        return $stmt->execute([$name, $location, $tempMax, $tempMin, $active, $id]);
    }

    public function updateLastSeen(int $id): bool {
        $stmt = $this->db->prepare('UPDATE devices SET last_seen_at = NOW() WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM devices WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function count(): int {
        return (int) $this->db->query('SELECT COUNT(*) FROM devices WHERE active = 1')->fetchColumn();
    }
}
