<?php

require_once __DIR__ . '/../../config/Database.php';

class Note {
    private $db;
    private $table = 'temperature_notes';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all notes for a device within a date range
     */
    public function getByDeviceAndRange(int $deviceId, string $from, string $to): array {
        $sql = "SELECT id, device_id, reading_id, noted_at, note_text, created_by, created_at 
                FROM {$this->table} 
                WHERE device_id = ? AND noted_at BETWEEN ? AND ? 
                ORDER BY noted_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$deviceId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new note
     */
    public function create(int $deviceId, string $notedAt, string $noteText, int $createdBy, ?int $readingId = null): int {
        $sql = "INSERT INTO {$this->table} (device_id, reading_id, noted_at, note_text, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$deviceId, $readingId, $notedAt, $noteText, $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Get a single note by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Update a note
     */
    public function update(int $id, string $noteText): bool {
        $sql = "UPDATE {$this->table} SET note_text = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$noteText, $id]);
    }

    /**
     * Delete a note
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Get notes for last 24 hours
     */
    public function getLast24Hours(int $deviceId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE device_id = ? AND noted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY noted_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
