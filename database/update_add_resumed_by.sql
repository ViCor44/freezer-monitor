-- Track who resumed recordings after a pause.
ALTER TABLE recording_pauses
    ADD COLUMN IF NOT EXISTS resumed_by INT UNSIGNED DEFAULT NULL AFTER resumed_at;
