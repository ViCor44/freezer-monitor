<?php

require_once __DIR__ . '/../../config/Database.php';

class RecordingPause {
    private $db;
    private $table = 'recording_pauses';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Open a new pause record for the device.
     */
    public function create(int $deviceId, string $reason, int $pausedBy): int {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (device_id, reason, paused_at, paused_by)
             VALUES (?, ?, NOW(), ?)"
        );
        $stmt->execute([$deviceId, $reason, $pausedBy]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Close the active (not yet resumed) pause for the device.
     */
    public function closeActive(int $deviceId, int $resumedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET resumed_at = NOW(), resumed_by = ?
             WHERE device_id = ? AND resumed_at IS NULL"
        );
        return $stmt->execute([$resumedBy, $deviceId]);
    }

    /**
     * Get the active (not yet resumed) pause for the device, or null.
     */
    public function getActive(int $deviceId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE device_id = ? AND resumed_at IS NULL
             ORDER BY paused_at DESC
             LIMIT 1"
        );
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all pauses for a device, ordered newest first.
     */
    public function getByDevice(int $deviceId): array {
        $stmt = $this->db->prepare(
            "SELECT rp.*,
                    u1.name AS paused_by_name,
                    u2.name AS resumed_by_name
             FROM {$this->table} rp
             LEFT JOIN users u1 ON u1.id = rp.paused_by
             LEFT JOIN users u2 ON u2.id = rp.resumed_by
             WHERE rp.device_id = ?
             ORDER BY rp.paused_at DESC"
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
