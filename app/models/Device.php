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
    private ?bool $hasMonitorDoorOpeningsColumn = null;

    public function __construct($db) {  // ← Recebe $db
        $this->db = $db;
    }

    public function getAll() {
        $sql = "SELECT d.*,
                       (
                           SELECT r.temperature
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC, r.id DESC
                           LIMIT 1
                       ) AS last_temp,
                       (
                           SELECT r.recorded_at
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC, r.id DESC
                           LIMIT 1
                       ) AS last_reading,
                       TIMESTAMPDIFF(
                           SECOND,
                           (
                               SELECT r.recorded_at
                               FROM temperature_readings r
                               WHERE r.device_id = d.id
                               ORDER BY r.recorded_at DESC, r.id DESC
                               LIMIT 1
                           ),
                           NOW()
                       ) AS seconds_since_reading,
                       TIMESTAMPDIFF(
                           SECOND,
                           COALESCE(
                               d.last_seen_at,
                               (
                                   SELECT r.recorded_at
                                   FROM temperature_readings r
                                   WHERE r.device_id = d.id
                                   ORDER BY r.recorded_at DESC, r.id DESC
                                   LIMIT 1
                               )
                           ),
                           NOW()
                       ) AS seconds_since_seen,
                       rp.reason      AS pause_reason,
                       rp.paused_at   AS paused_at
                FROM {$this->table} d
                LEFT JOIN recording_pauses rp
                       ON rp.device_id = d.id AND rp.resumed_at IS NULL
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

    public function findByIdWithTelemetry(int $id): array|false {
        $sql = "SELECT d.*,
                       (
                           SELECT r.temperature
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC, r.id DESC
                           LIMIT 1
                       ) AS last_temp,
                       (
                           SELECT r.recorded_at
                           FROM temperature_readings r
                           WHERE r.device_id = d.id
                           ORDER BY r.recorded_at DESC, r.id DESC
                           LIMIT 1
                       ) AS last_reading,
                       TIMESTAMPDIFF(
                           SECOND,
                           (
                               SELECT r.recorded_at
                               FROM temperature_readings r
                               WHERE r.device_id = d.id
                               ORDER BY r.recorded_at DESC, r.id DESC
                               LIMIT 1
                           ),
                           NOW()
                       ) AS seconds_since_reading,
                       TIMESTAMPDIFF(
                           SECOND,
                           COALESCE(
                               d.last_seen_at,
                               (
                                   SELECT r.recorded_at
                                   FROM temperature_readings r
                                   WHERE r.device_id = d.id
                                   ORDER BY r.recorded_at DESC, r.id DESC
                                   LIMIT 1
                               )
                           ),
                           NOW()
                       ) AS seconds_since_seen
                FROM {$this->table} d
                WHERE d.id = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByDevEui(string $devEui): array|false {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE dev_eui = ? LIMIT 1');
        $stmt->execute([$devEui]);
        return $stmt->fetch();
    }

    public function create(
        string $name,
        string $devEui,
        string $location = '',
        float $tempMax = TEMP_MAX,
        float $tempMin = TEMP_MIN,
        int $monitorDoorOpenings = 1
    ): int {
        if ($this->supportsDoorMonitoringOption()) {
            $stmt = $this->db->prepare(
                'INSERT INTO devices (name, dev_eui, location, temp_max, temp_min, monitor_door_openings) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, strtolower($devEui), $location, $tempMax, $tempMin, $monitorDoorOpenings]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO devices (name, dev_eui, location, temp_max, temp_min) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, strtolower($devEui), $location, $tempMax, $tempMin]);
        }

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $location,
        float $tempMax,
        float $tempMin,
        int $active,
        int $monitorDoorOpenings
    ): bool {
        if ($this->supportsDoorMonitoringOption()) {
            $stmt = $this->db->prepare(
                'UPDATE devices SET name = ?, location = ?, temp_max = ?, temp_min = ?, active = ?, monitor_door_openings = ? WHERE id = ?'
            );
            return $stmt->execute([$name, $location, $tempMax, $tempMin, $active, $monitorDoorOpenings, $id]);
        }

        $stmt = $this->db->prepare(
            'UPDATE devices SET name = ?, location = ?, temp_max = ?, temp_min = ?, active = ? WHERE id = ?'
        );
        return $stmt->execute([$name, $location, $tempMax, $tempMin, $active, $id]);
    }

    private function supportsDoorMonitoringOption(): bool {
        if ($this->hasMonitorDoorOpeningsColumn !== null) {
            return $this->hasMonitorDoorOpeningsColumn;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'monitor_door_openings'");
            $this->hasMonitorDoorOpeningsColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->hasMonitorDoorOpeningsColumn = false;
        }

        return $this->hasMonitorDoorOpeningsColumn;
    }

    public function updateLastSeen(int $id): bool {
        $stmt = $this->db->prepare('UPDATE devices SET last_seen_at = NOW() WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function updateDoorStatus(int $id, int $doorOpen): bool {
        $stmt = $this->db->prepare(
            'UPDATE devices SET door_open = ?, door_updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$doorOpen, $id]);
    }

    public function pauseRecordings(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE devices SET recordings_paused = 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function resumeRecordings(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE devices SET recordings_paused = 0 WHERE id = ?'
        );
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
