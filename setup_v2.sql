-- =============================================
-- SiteDeploy v2 — Full Schema with Auth & Blueprints
-- Run this in phpMyAdmin or MySQL CLI
-- =============================================

DROP DATABASE IF EXISTS `site_deployment`;

CREATE DATABASE `site_deployment`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `site_deployment`;

-- =============================================
-- USERS
-- =============================================
CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL UNIQUE,
  `email`         VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- PROJECTS / CLIENT FOLDERS
-- =============================================
CREATE TABLE `projects` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `client_name`  VARCHAR(150) NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `code`         VARCHAR(50)  NOT NULL UNIQUE,
  `description`  TEXT,
  `status`       VARCHAR(50)  NOT NULL DEFAULT 'Active',
  `created_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- BLUEPRINTS
-- =============================================
CREATE TABLE `blueprints` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `project_id`     INT DEFAULT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `description`    TEXT,
  `image_filename` VARCHAR(255) NOT NULL,
  `image_width`        INT UNSIGNED DEFAULT 0,
  `image_height`       INT UNSIGNED DEFAULT 0,
  `real_width_meters`  DECIMAL(8,2) NOT NULL DEFAULT 60.00,
  `real_height_meters` DECIMAL(8,2) NOT NULL DEFAULT 40.00,
  `uploaded_by`    INT NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BLUEPRINT ACCESS (many-to-many)
-- Owner has access implicitly; this grants access to other users.
-- =============================================
CREATE TABLE `blueprint_access` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `blueprint_id` INT NOT NULL,
  `user_id`      INT NOT NULL,
  `granted_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`blueprint_id`) REFERENCES `blueprints`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)      ON DELETE CASCADE,
  FOREIGN KEY (`granted_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL,
  UNIQUE KEY `unique_access` (`blueprint_id`, `user_id`)
) ENGINE=InnoDB;

-- =============================================
-- NODES (pins on a specific blueprint)
-- Includes ELV Testing & Commissioning (T&C) Equipment Schedule
-- =============================================
CREATE TABLE `nodes` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `blueprint_id`    INT NOT NULL,
  `device_id`       VARCHAR(100) NOT NULL,
  `type`            VARCHAR(100) NOT NULL DEFAULT 'Generic',
  `serial_number`   VARCHAR(100) NULL,
  `ip_address`      VARCHAR(45)  NOT NULL DEFAULT '',
  `mac_address`     VARCHAR(30)  NULL,
  `switch_id`       VARCHAR(50)  NULL,
  `port_no`         VARCHAR(20)  NULL,
  `cable_label`     VARCHAR(50)  NULL,
  `pos_x`           DECIMAL(8,4) NOT NULL DEFAULT 0,
  `pos_y`           DECIMAL(8,4) NOT NULL DEFAULT 0,
  `status`          VARCHAR(50)  NOT NULL DEFAULT 'Planned',
  `notes`           TEXT,
  `tested_by`       VARCHAR(100) NULL,
  `commission_date` DATE         NULL,
  `fov_enabled`     TINYINT(1)   NOT NULL DEFAULT 0,
  `fov_angle`       INT          NOT NULL DEFAULT 90,
  `fov_direction`   INT          NOT NULL DEFAULT 0,
  `fov_distance`    INT          NOT NULL DEFAULT 15,
  `ping_status`     VARCHAR(20)  NOT NULL DEFAULT 'unknown',
  `ping_latency_ms` INT          NULL,
  `last_ping_at`    DATETIME     NULL,
  `created_by`      INT DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`blueprint_id`) REFERENCES `blueprints`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)      ON DELETE SET NULL,
  UNIQUE KEY `unique_device_per_blueprint` (`blueprint_id`, `device_id`)
) ENGINE=InnoDB;

-- =============================================
-- SEED USERS
-- Password for both: admin123
-- =============================================
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`) VALUES
  (1, 'admin', 'admin@sitedeploy.local', '$2y$10$BNdS/ytM./G5xLpsPAKiaO4dVTfZXK./U7MWjwgMpaeik.pg.zdlS', 'System Admin', 'admin'),
  (2, 'tech1', 'tech1@sitedeploy.local', '$2y$10$BNdS/ytM./G5xLpsPAKiaO4dVTfZXK./U7MWjwgMpaeik.pg.zdlS', 'Technician Ali', 'user')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`);

-- =============================================
-- SEED BLUEPRINT (Pre-loaded floorplan)
-- =============================================
INSERT INTO `blueprints` (`id`, `name`, `description`, `image_filename`, `image_width`, `image_height`, `uploaded_by`) VALUES
  (1, 'HQ Level 2 — Operations & NOC', 'Main corporate headquarters level 2 with server room, conference suite, and perimeter access control.', 'sample_floorplan.svg', 1600, 1000, 1);

-- =============================================
-- SEED ACCESS (Share with Technician Ali)
-- =============================================
INSERT INTO `blueprint_access` (`blueprint_id`, `user_id`, `granted_by`) VALUES
  (1, 2, 1);

-- =============================================
-- SEED HARDWARE NODES ON BLUEPRINT 1 (With T&C Commissioning & Camera FoV Data)
-- =============================================
INSERT INTO `nodes` 
  (`blueprint_id`, `device_id`, `type`, `serial_number`, `ip_address`, `mac_address`, `switch_id`, `port_no`, `cable_label`, `pos_x`, `pos_y`, `status`, `notes`, `tested_by`, `commission_date`, `fov_enabled`, `fov_angle`, `fov_direction`, `fov_distance`, `created_by`) 
VALUES
  (1, 'CAM-01',   'Hikvision Dome',   'DS-2CD2143G2-IS-20230911AAWR', '192.168.1.101', 'BC:54:51:3A:89:1F', 'SW-MDF-01',  'P01 (PoE)',      'C-L2-001', 18.5000, 18.0000, 'Live',             'Main Lobby ceiling mount facing entrance', 'Wan Pidin',      '2026-10-15', 1, 90, 135, 18, 1),
  (1, 'CAM-02',   'Hikvision Bullet', 'DS-2CD2043G2-I-20230815BBRT',  '192.168.1.102', 'BC:54:51:4B:92:2A', 'SW-MDF-01',  'P02 (PoE)',      'C-L2-002', 95.0000, 50.0000, 'Hardware Mounted',  'East emergency exit doorway',              'Wan Pidin',      '2026-10-16', 1, 70, 270, 24, 1),
  (1, 'CAM-03',   'Dahua PTZ',        'DH-SD49225XA-HNR-20231102A',   '192.168.1.103', '38:AF:29:12:87:C0', 'SW-IDF-01',  'P08 (Hi-PoE)',   'C-L2-008', 50.0000, 22.0000, 'Cable Pulled',      'Conference Suite Alpha high angle',        'Technician Ali', '2026-10-18', 1, 120, 180, 22, 2),
  (1, 'NVR-01',   'NVR Rack',         'DS-7732NI-I4-20230510CCXE',    '192.168.1.10',  'BC:54:51:10:00:0A', 'SW-CORE-01', 'G01 (Trunk)',    'FIB-MDF-01',14.0000, 70.0000, 'Live',             'NOC Server Rack A Unit 4',                 'Wan Pidin',      '2026-10-12', 0, 90, 0,   15, 1),
  (1, 'AP-01',    'Access Point',     'RG-AP820-L-20230718DDWE',      '192.168.1.201', '58:69:6C:44:19:80', 'SW-MDF-01',  'P12 (PoE)',      'C-L2-012', 50.0000, 50.0000, 'Live',             'Central corridor ceiling PoE AP',          'Technician Ali', '2026-10-16', 0, 90, 0,   15, 2),
  (1, 'DOOR-01',  'Access Door',      'DS-K2604T-20230401MMKL',       '192.168.1.50',  'BC:54:51:88:31:0D', 'SW-MDF-01',  'P16',            'C-SEC-001', 15.0000, 56.0000, 'Troubleshoot',     'Server room card reader offline',          'Wan Pidin',      NULL,         0, 90, 0,   15, 1);

-- =============================================
-- NODE PHOTOS (Mobile Site Photo Attachments & As-Built Evidence)
-- =============================================
CREATE TABLE IF NOT EXISTS `node_photos` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `blueprint_id` INT NOT NULL,
  `device_id`    VARCHAR(100) NOT NULL,
  `filename`     VARCHAR(255) NOT NULL,
  `caption`      VARCHAR(255) NULL,
  `uploaded_by`  INT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_bp_device` (`blueprint_id`, `device_id`),
  FOREIGN KEY (`blueprint_id`) REFERENCES `blueprints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- SEED SAMPLE NODE PHOTOS FOR BLUEPRINT 1
-- =============================================
INSERT INTO `node_photos` (`blueprint_id`, `device_id`, `filename`, `caption`, `uploaded_by`) VALUES
  (1, 'CAM-01', 'sample_cam01.svg', 'Main lobby dome camera ceiling mount & C-L2-001 cable tag', 1),
  (1, 'CAM-02', 'sample_cam02.svg', 'East emergency exit bullet camera & weatherproof conduit', 1),
  (1, 'NVR-01', 'sample_nvr01.svg', 'NOC 32-Channel NVR recorder & fiber patch panel', 1);

-- =============================================
-- CABLES (Structured Cabling & Conduit Routing)
-- =============================================
CREATE TABLE IF NOT EXISTS `cables` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `blueprint_id`        INT NOT NULL,
  `from_node_id`        INT NOT NULL,
  `to_node_id`          INT NOT NULL,
  `cable_label`         VARCHAR(50) NOT NULL,
  `cable_type`          VARCHAR(50) NOT NULL DEFAULT 'Cat6 UTP',
  `conduit_type`        VARCHAR(50) NOT NULL DEFAULT '20mm PVC',
  `waypoints`           TEXT NULL,
  `slack_drop_meters`   DECIMAL(5,2) NOT NULL DEFAULT 3.00,
  `total_length_meters` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `status`              VARCHAR(50) NOT NULL DEFAULT 'Planned',
  `color`               VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
  `notes`               TEXT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cable_bp` (`blueprint_id`),
  FOREIGN KEY (`blueprint_id`) REFERENCES `blueprints`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_node_id`) REFERENCES `nodes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_node_id`)   REFERENCES `nodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `cables` 
  (`blueprint_id`, `from_node_id`, `to_node_id`, `cable_label`, `cable_type`, `conduit_type`, `waypoints`, `slack_drop_meters`, `total_length_meters`, `status`, `color`, `notes`) 
VALUES
  (1, 1, 4, 'C-L2-001', 'Cat6 UTP', '20mm PVC Conduit', '[{"x":18.5,"y":50},{"x":14,"y":50}]', 3.00, 34.50, 'Live', '#3b82f6', 'Main lobby ceiling conduit run'),
  (1, 2, 4, 'C-L2-002', 'Cat6 UTP', '25mm GI Conduit', '[{"x":50,"y":50},{"x":14,"y":50}]', 3.00, 59.80, 'Hardware Mounted', '#3b82f6', 'East perimeter corridor trunking'),
  (1, 3, 4, 'C-L2-008', 'Cat6A STP', 'Cable Tray / Trunking', '[{"x":50,"y":50},{"x":14,"y":50}]', 3.00, 41.20, 'Cable Pulled', '#8b5cf6', 'Conference suite ceiling tray'),
  (1, 5, 4, 'C-L2-012', 'Cat6 UTP', '20mm PVC Conduit', '[{"x":14,"y":50}]', 3.00, 29.60, 'Live', '#10b981', 'Corridor PoE Access Point run'),
  (1, 6, 4, 'C-SEC-001','2-Core Shielded', '20mm PVC Conduit', NULL, 2.00, 9.20, 'Live', '#f59e0b', 'Server room card reader security link');

