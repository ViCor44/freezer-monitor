-- Freezer Monitor Database Update
-- Add temperature notes table
-- Run: mysql -u username -p database_name < database/update_add_notes.sql

USE freezer_monitor;

-- Temperature notes table
CREATE TABLE IF NOT EXISTS temperature_notes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id       INT UNSIGNED NOT NULL,
    reading_id      BIGINT UNSIGNED DEFAULT NULL,
    noted_at        DATETIME NOT NULL,
    note_text       TEXT NOT NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_noted (device_id, noted_at),
    INDEX idx_reading (reading_id),
    CONSTRAINT fk_notes_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_reading FOREIGN KEY (reading_id) REFERENCES temperature_readings(id) ON DELETE SET NULL,
    CONSTRAINT fk_notes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
