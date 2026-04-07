USE freezer_monitor;

ALTER TABLE devices
    ADD COLUMN door_open TINYINT(1) NOT NULL DEFAULT 0 AFTER temp_min,
    ADD COLUMN door_updated_at DATETIME NULL AFTER door_open;

CREATE TABLE IF NOT EXISTS door_openings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id       INT UNSIGNED NOT NULL,
    reading_id      BIGINT UNSIGNED DEFAULT NULL,
    opened_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_door_device_opened (device_id, opened_at),
    INDEX idx_door_reading (reading_id),
    CONSTRAINT fk_door_openings_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_door_openings_reading FOREIGN KEY (reading_id) REFERENCES temperature_readings(id) ON DELETE SET NULL
) ENGINE=InnoDB;
