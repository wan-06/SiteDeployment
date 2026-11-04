-- =============================================
-- Site Deployment Database Setup
-- Run this in phpMyAdmin or MySQL CLI
-- =============================================

CREATE DATABASE IF NOT EXISTS `site_deployment`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `site_deployment`;

CREATE TABLE IF NOT EXISTS `nodes` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `device_id`   VARCHAR(100) NOT NULL UNIQUE,
  `type`        VARCHAR(100) NOT NULL DEFAULT 'Generic',
  `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '',
  `pos_x`       DECIMAL(8,4) NOT NULL DEFAULT 0,
  `pos_y`       DECIMAL(8,4) NOT NULL DEFAULT 0,
  `status`      VARCHAR(50)  NOT NULL DEFAULT 'Planned',
  `notes`       TEXT         NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed some sample data so the map isn't empty on first load
INSERT INTO `nodes` (`device_id`, `type`, `ip_address`, `pos_x`, `pos_y`, `status`, `notes`) VALUES
  ('CAM-01', 'Hikvision Dome',  '192.168.1.101', 25.5000, 30.2000, 'Live',             'Main lobby ceiling mount'),
  ('CAM-02', 'Hikvision Bullet','192.168.1.102', 60.1000, 15.8000, 'Hardware Mounted',  'Parking entrance — awaiting PoE switch'),
  ('CAM-03', 'Dahua PTZ',       '192.168.1.103', 45.0000, 65.0000, 'Cable Pulled',      'Server room corner'),
  ('NVR-01', 'NVR Rack',        '192.168.1.10',  80.0000, 75.0000, 'Live',             'Main server rack A'),
  ('AP-01',  'Access Point',    '192.168.1.201', 50.0000, 50.0000, 'Planned',           'Hallway ceiling — pending approval');

