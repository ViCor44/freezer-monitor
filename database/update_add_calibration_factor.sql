USE freezer_monitor;

ALTER TABLE devices
    ADD COLUMN IF NOT EXISTS calibration_offset DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER temp_min;
