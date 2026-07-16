-- Add per-device SMS enable/disable flag
-- Run: mysql -u root -p freezer_monitor < database/update_add_sms_enabled.sql

USE freezer_monitor;

ALTER TABLE devices
    ADD COLUMN sms_enabled TINYINT(1) NOT NULL DEFAULT 1
    AFTER sms_alarm_minutes;
