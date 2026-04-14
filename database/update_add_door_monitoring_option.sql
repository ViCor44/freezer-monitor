USE freezer_monitor;

ALTER TABLE devices
    ADD COLUMN IF NOT EXISTS monitor_door_openings TINYINT(1) NOT NULL DEFAULT 1 AFTER door_updated_at;
