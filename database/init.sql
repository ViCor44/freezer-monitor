-- Freezer Monitor Database Schema
-- Run: mysql -u root -p < database/init.sql

CREATE DATABASE IF NOT EXISTS freezer_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freezer_monitor;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    approved    TINYINT(1)          NOT NULL DEFAULT 0,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Devices table
CREATE TABLE IF NOT EXISTS devices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)   NOT NULL,
    dev_eui         VARCHAR(16)    NOT NULL UNIQUE,
    location        VARCHAR(200)   DEFAULT NULL,
    temp_max        DECIMAL(5,2)   NOT NULL DEFAULT 5.00,
    temp_min        DECIMAL(5,2)   NOT NULL DEFAULT -25.00,
    calibration_offset DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    door_open       TINYINT(1)     NOT NULL DEFAULT 0,
    door_updated_at DATETIME       DEFAULT NULL,
    monitor_door_openings TINYINT(1) NOT NULL DEFAULT 1,
    active          TINYINT(1)     NOT NULL DEFAULT 1,
    last_seen_at    DATETIME       DEFAULT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Temperature readings table
CREATE TABLE IF NOT EXISTS temperature_readings (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id   INT UNSIGNED    NOT NULL,
    temperature DECIMAL(5,2)    NOT NULL,
    humidity    DECIMAL(5,2)    DEFAULT NULL,
    battery     DECIMAL(5,2)    DEFAULT NULL,
    rssi        SMALLINT        DEFAULT NULL,
    snr         DECIMAL(5,2)    DEFAULT NULL,
    recorded_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_recorded (device_id, recorded_at),
    INDEX idx_recorded_at (recorded_at),
    CONSTRAINT fk_readings_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id       INT UNSIGNED NOT NULL,
    type            ENUM('high','low','offline') NOT NULL,
    status          ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
    temperature     DECIMAL(5,2) DEFAULT NULL,
    message         VARCHAR(255) NOT NULL,
    acknowledged_by INT UNSIGNED DEFAULT NULL,
    acknowledged_at DATETIME     DEFAULT NULL,
    resolved_at     DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alerts_device (device_id),
    INDEX idx_alerts_status (status),
    CONSTRAINT fk_alerts_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_alerts_user  FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

-- Door opening events table
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

-- Seed admin user (password: admin123)
INSERT IGNORE INTO users (name, email, password, role, approved)
VALUES ('Administrator', 'admin@freezer.local',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Seed demo user (password: user123)
INSERT IGNORE INTO users (name, email, password, role, approved)
VALUES ('Demo User', 'user@freezer.local',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1);
