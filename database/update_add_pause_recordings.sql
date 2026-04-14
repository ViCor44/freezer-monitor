-- Add pause-recordings support to devices table
ALTER TABLE devices
    ADD COLUMN recordings_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
    ADD COLUMN pause_reason      VARCHAR(500)             DEFAULT NULL AFTER recordings_paused,
    ADD COLUMN paused_at         DATETIME                 DEFAULT NULL AFTER pause_reason;
