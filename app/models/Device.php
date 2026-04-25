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
    private ?bool $hasCalibrationOffsetColumn = null;
    private ?bool $hasZoneColumn = null;

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
                  ORDER BY COALESCE(NULLIF(TRIM(d.zone), ''), 'Sem zona'), d.name";
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
        string $zone = '',
        string $location = '',
        float $tempMax = TEMP_MAX,
        float $tempMin = TEMP_MIN,
        int $active = 1,
        int $monitorDoorOpenings = 1,
        float $calibrationOffset = 0.0
    ): int {
        $supportsDoorMonitoring = $this->supportsDoorMonitoringOption();
        $supportsCalibrationOffset = $this->supportsCalibrationOffset();
        $supportsZone = $this->supportsZone();

        $columns = ['name', 'dev_eui'];
        $placeholders = ['?', '?'];
        $values = [$name, strtolower($devEui)];

        if ($supportsZone) {
            $columns[] = 'zone';
            $placeholders[] = '?';
            $values[] = $zone;
        }

        $columns[] = 'location';
        $placeholders[] = '?';
        $values[] = $location;

        $columns[] = 'temp_max';
        $placeholders[] = '?';
        $values[] = $tempMax;

        $columns[] = 'temp_min';
        $placeholders[] = '?';
        $values[] = $tempMin;

        $columns[] = 'active';
        $placeholders[] = '?';
        $values[] = $active;

        if ($supportsDoorMonitoring) {
            $columns[] = 'monitor_door_openings';
            $placeholders[] = '?';
            $values[] = $monitorDoorOpenings;
        }

        if ($supportsCalibrationOffset) {
            $columns[] = 'calibration_offset';
            $placeholders[] = '?';
            $values[] = $calibrationOffset;
        }

        $sql = sprintf(
            'INSERT INTO devices (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $zone,
        string $location,
        float $tempMax,
        float $tempMin,
        int $active,
        int $monitorDoorOpenings,
        float $calibrationOffset = 0.0
    ): bool {
        $supportsDoorMonitoring = $this->supportsDoorMonitoringOption();
        $supportsCalibrationOffset = $this->supportsCalibrationOffset();
        $supportsZone = $this->supportsZone();

        $setParts = ['name = ?'];
        $values = [$name];

        if ($supportsZone) {
            $setParts[] = 'zone = ?';
            $values[] = $zone;
        }

        $setParts[] = 'location = ?';
        $values[] = $location;

        $setParts[] = 'temp_max = ?';
        $values[] = $tempMax;

        $setParts[] = 'temp_min = ?';
        $values[] = $tempMin;

        $setParts[] = 'active = ?';
        $values[] = $active;

        if ($supportsDoorMonitoring) {
            $setParts[] = 'monitor_door_openings = ?';
            $values[] = $monitorDoorOpenings;
        }

        if ($supportsCalibrationOffset) {
            $setParts[] = 'calibration_offset = ?';
            $values[] = $calibrationOffset;
        }

        $values[] = $id;

        $sql = sprintf('UPDATE devices SET %s WHERE id = ?', implode(', ', $setParts));
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($values);
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

    private function supportsCalibrationOffset(): bool {
        if ($this->hasCalibrationOffsetColumn !== null) {
            return $this->hasCalibrationOffsetColumn;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'calibration_offset'");
            $this->hasCalibrationOffsetColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->hasCalibrationOffsetColumn = false;
        }

        return $this->hasCalibrationOffsetColumn;
    }

    private function supportsZone(): bool {
        if ($this->hasZoneColumn !== null) {
            return $this->hasZoneColumn;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'zone'");
            $this->hasZoneColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->hasZoneColumn = false;
        }

        return $this->hasZoneColumn;
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
