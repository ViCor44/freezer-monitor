-- Replace pause columns on devices with a dedicated recording_pauses table.
-- Run after update_add_pause_recordings.sql (which added the columns).

CREATE TABLE IF NOT EXISTS recording_pauses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id   INT UNSIGNED NOT NULL,
    reason      VARCHAR(500) NOT NULL,
    paused_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resumed_at  DATETIME         DEFAULT NULL,
    paused_by   INT UNSIGNED     DEFAULT NULL,
    INDEX idx_device (device_id),
    INDEX idx_device_active (device_id, resumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remove redundant columns from devices (keep recordings_paused flag for fast webhook check)
ALTER TABLE devices
    DROP COLUMN IF EXISTS pause_reason,
    DROP COLUMN IF EXISTS paused_at;
