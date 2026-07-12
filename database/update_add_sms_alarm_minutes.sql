-- ===========================================================================
--  Tempo (em minutos) que um dispositivo pode estar continuamente fora do
--  intervalo de temperatura antes de enviar SMS de alarme. Configuravel por
--  dispositivo; se NULL usa a definicao global SMS_ALARM_MIN_MINUTES (60).
--
--  Executar em phpMyAdmin ou:
--      mysql -u root freezer_monitor < database/update_add_sms_alarm_minutes.sql
-- ===========================================================================

USE freezer_monitor;

ALTER TABLE devices
    ADD COLUMN IF NOT EXISTS sms_alarm_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER calibration_offset;
