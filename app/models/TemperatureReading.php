<?php

require_once __DIR__ . '/../../config/Database.php';

class TemperatureReading {
    private $db;
    private $table = 'temperature_readings';

    public function __construct($db) {  // ← Recebe $db
        $this->db = $db;
    }

    public function getLatest($device_id, $limit = 100) {
        $sql = "SELECT * FROM {$this->table} WHERE device_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$device_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $deviceId, float $temperature, ?float $humidity = null, ?float $battery = null, ?int $rssi = null, ?float $snr = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO temperature_readings (device_id, temperature, humidity, battery, rssi, snr) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$deviceId, $temperature, $humidity, $battery, $rssi, $snr]);
        return (int) $this->db->lastInsertId();
    }

    public function getLast24Hours(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT temperature, humidity, recorded_at FROM temperature_readings
             WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll();
    }

    public function getLast7Days(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT temperature, humidity, recorded_at
             FROM temperature_readings
             WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll();
    }

    public function getLast30Days(int $deviceId): array {
        $stmt = $this->db->prepare(
            'SELECT temperature, humidity, recorded_at
             FROM temperature_readings
             WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll();
    }

    public function getByRange(int $deviceId, string $from, string $to): array {
        $stmt = $this->db->prepare(
            'SELECT temperature, humidity, recorded_at
             FROM temperature_readings
             WHERE device_id = ?
               AND recorded_at >= ?
               AND recorded_at <= ?
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([$deviceId, $from, $to]);
        return $stmt->fetchAll();
    }

    public function getLatestPerDevice(): array {
        return $this->db->query(
            'SELECT r.device_id, r.temperature, r.humidity, r.battery, r.recorded_at, d.name AS device_name
             FROM temperature_readings r
             INNER JOIN devices d ON d.id = r.device_id
             WHERE r.id = (
                 SELECT id FROM temperature_readings r2
                 WHERE r2.device_id = r.device_id
                 ORDER BY r2.recorded_at DESC LIMIT 1
             )
             ORDER BY d.name'
        )->fetchAll();
    }

    public function countToday(): int {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM temperature_readings WHERE DATE(recorded_at) = CURDATE()'
        )->fetchColumn();
    }

    // Retorna a última leitura (mais recente) para um device
    public function getLastByDevice(int $deviceId) {
        $stmt = $this->db->prepare(
            'SELECT id, created_at FROM temperature_readings WHERE device_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
