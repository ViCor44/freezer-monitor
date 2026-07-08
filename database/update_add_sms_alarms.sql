-- ===========================================================================
--  Envio de SMS quando um dispositivo permanece fora do intervalo de
--  temperatura durante mais de X minutos consecutivos (default 60 min).
--
--  Executar em phpMyAdmin ou:
--      mysql -u root freezer_monitor < database/update_add_sms_alarms.sql
-- ===========================================================================

USE freezer_monitor;

-- Preferencias por utilizador ------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(32) NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `receive_sms_alarms` TINYINT(1) NOT NULL DEFAULT 0 AFTER `phone`;

-- Estado da condicao de temperatura fora do intervalo por dispositivo -------
-- Deteta transicoes normal <-> fora-do-intervalo e serve como debounce para
-- decidir quando ja passou o tempo minimo (SMS_ALARM_MIN_MINUTES) para enviar
-- o SMS de alarme, evitando SMS por picos curtos.
CREATE TABLE IF NOT EXISTS `device_temp_alarm_state` (
    `device_id`       INT UNSIGNED NOT NULL,
    `alarm_type`      ENUM('temp_high','temp_low') NOT NULL,
    `first_active_at` DATETIME NOT NULL,
    `last_active_at`  DATETIME NOT NULL,
    `last_temperature` DECIMAL(5,2) NULL,
    `sms_sent_at`     DATETIME NULL,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`device_id`),
    CONSTRAINT fk_temp_state_device FOREIGN KEY (`device_id`)
        REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de todos os SMS tentados (sucesso, falha, ignorado) --------------------
CREATE TABLE IF NOT EXISTS `sms_log` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ts`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `device_id`  INT UNSIGNED NULL,
    `alarm_type` VARCHAR(32)  NULL,
    `event`      VARCHAR(16)  NULL,      -- 'ALARME' ou 'OK'
    `to_number`  VARCHAR(32)  NOT NULL,
    `message`    VARCHAR(255) NOT NULL,
    `status`     VARCHAR(16)  NOT NULL,  -- 'sent', 'failed', 'skipped'
    `response`   TEXT         NULL,
    INDEX idx_sms_ts (`ts`),
    INDEX idx_sms_device (`device_id`, `alarm_type`, `ts`),
    CONSTRAINT fk_sms_log_device FOREIGN KEY (`device_id`)
        REFERENCES `devices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
