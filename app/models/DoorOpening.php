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
}
