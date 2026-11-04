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
-- BLUEPRINTS
-- =============================================
CREATE TABLE `blueprints` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(150) NOT NULL,
  `description`    TEXT,
  `image_filename` VARCHAR(255) NOT NULL,
  `image_width`    INT UNSIGNED DEFAULT 0,
  `image_height`   INT UNSIGNED DEFAULT 0,
  `uploaded_by`    INT NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
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
-- =============================================
CREATE TABLE `nodes` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `blueprint_id` INT NOT NULL,
  `device_id`    VARCHAR(100) NOT NULL,
  `type`         VARCHAR(100) NOT NULL DEFAULT 'Generic',
  `ip_address`   VARCHAR(45)  NOT NULL DEFAULT '',
  `pos_x`        DECIMAL(8,4) NOT NULL DEFAULT 0,
  `pos_y`        DECIMAL(8,4) NOT NULL DEFAULT 0,
  `status`       VARCHAR(50)  NOT NULL DEFAULT 'Planned',
  `notes`        TEXT,
  `created_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
-- SEED HARDWARE NODES ON BLUEPRINT 1
-- =============================================
INSERT INTO `nodes` (`blueprint_id`, `device_id`, `type`, `ip_address`, `pos_x`, `pos_y`, `status`, `notes`, `created_by`) VALUES
  (1, 'CAM-01',   'Hikvision Dome',   '192.168.1.101', 18.5000, 18.0000, 'Live',             'Main Lobby ceiling mount facing entrance', 1),
  (1, 'CAM-02',   'Hikvision Bullet', '192.168.1.102', 95.0000, 50.0000, 'Hardware Mounted',  'East emergency exit doorway',              1),
  (1, 'CAM-03',   'Dahua PTZ',        '192.168.1.103', 50.0000, 22.0000, 'Cable Pulled',      'Conference Suite Alpha high angle',        2),
  (1, 'NVR-01',   'NVR Rack',         '192.168.1.10',  14.0000, 70.0000, 'Live',             'NOC Server Rack A Unit 4',                 1),
  (1, 'AP-01',    'Access Point',     '192.168.1.201', 50.0000, 50.0000, 'Live',             'Central corridor ceiling PoE AP',          2),
  (1, 'DOOR-01',  'Access Door',      '192.168.1.50',  15.0000, 56.0000, 'Troubleshoot',     'Server room card reader offline',          1);
