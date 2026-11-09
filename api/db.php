<?php
/**
 * Database Connection (PDO)
 * Automatically initializes database & tables if missing
 */

$host = '127.0.0.1';
$db   = 'site_deployment';
$user = 'root';
$pass = '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);

    // Quick verification: does the users table exist?
    $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$check) {
        // Run setup_v2.sql to initialize all tables & seeds
        $schemaFile = __DIR__ . '/../setup_v2.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }
    } else {
        // Auto-migration: ensure T&C commissioning columns exist in nodes table
        $colCheck = $pdo->query("SHOW COLUMNS FROM nodes LIKE 'serial_number'")->fetch();
        if (!$colCheck) {
            $pdo->exec("
                ALTER TABLE nodes
                ADD COLUMN `serial_number`   VARCHAR(100) NULL AFTER `type`,
                ADD COLUMN `mac_address`     VARCHAR(30)  NULL AFTER `ip_address`,
                ADD COLUMN `switch_id`       VARCHAR(50)  NULL AFTER `mac_address`,
                ADD COLUMN `port_no`         VARCHAR(20)  NULL AFTER `switch_id`,
                ADD COLUMN `cable_label`     VARCHAR(50)  NULL AFTER `port_no`,
                ADD COLUMN `tested_by`       VARCHAR(100) NULL AFTER `notes`,
                ADD COLUMN `commission_date` DATE         NULL AFTER `tested_by`
            ");
        }

        // Auto-migration: ensure Camera Field-of-View (FoV) columns exist in nodes table
        $fovCheck = $pdo->query("SHOW COLUMNS FROM nodes LIKE 'fov_angle'")->fetch();
        if (!$fovCheck) {
            $pdo->exec("
                ALTER TABLE nodes
                ADD COLUMN `fov_enabled`   TINYINT(1)   NOT NULL DEFAULT 0 AFTER `commission_date`,
                ADD COLUMN `fov_angle`     INT          NOT NULL DEFAULT 90 AFTER `fov_enabled`,
                ADD COLUMN `fov_direction` INT          NOT NULL DEFAULT 0 AFTER `fov_angle`,
                ADD COLUMN `fov_distance`  INT          NOT NULL DEFAULT 15 AFTER `fov_direction`
            ");
        }

        // Auto-migration: ensure node_photos table exists for Mobile Site Photo Attachments
        $photoTableCheck = $pdo->query("SHOW TABLES LIKE 'node_photos'")->fetch();
        if (!$photoTableCheck) {
            $pdo->exec("
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
            ");

            // Seed sample photos for blueprint 1 nodes
            $sampleDir = __DIR__ . '/../uploads/nodes';
            if (file_exists("$sampleDir/sample_cam01.svg")) {
                $pdo->exec("
                    INSERT INTO `node_photos` (`blueprint_id`, `device_id`, `filename`, `caption`, `uploaded_by`) VALUES
                    (1, 'CAM-01', 'sample_cam01.svg', 'Main lobby dome camera ceiling mount & C-L2-001 cable tag', 1),
                    (1, 'CAM-02', 'sample_cam02.svg', 'East emergency exit bullet camera & weatherproof conduit', 1),
                    (1, 'NVR-01', 'sample_nvr01.svg', 'NOC 32-Channel NVR recorder & fiber patch panel', 1)
                ");
            }
        }

        // Auto-migration: ensure projects table exists for Project / Client Folder Organization
        $projTableCheck = $pdo->query("SHOW TABLES LIKE 'projects'")->fetch();
        if (!$projTableCheck) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `projects` (
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

                INSERT INTO `projects` (`id`, `client_name`, `name`, `code`, `description`, `status`, `created_by`) VALUES
                (1, 'Reckitt Benckiser (M) Sdn Bhd', 'Reckitt Benckiser CCTV Upgrade & Perimeter Defense', 'RB-JB-2026', 'Full factory and warehouse 1 & 2 IP CCTV upgrade, conduit cabling, and NVR rack commissioning.', 'Active', 1),
                (2, 'MEMIS Corporate Headquarters', 'MEMIS HQ Smart Facility & NOC Expansion', 'MEMIS-HQ-01', 'Operations center, server room, and biometric access control deployment.', 'Completed', 1)
                ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
            ");
        }

        // Auto-migration: ensure project_id column exists in blueprints table
        $bpProjColCheck = $pdo->query("SHOW COLUMNS FROM blueprints LIKE 'project_id'")->fetch();
        if (!$bpProjColCheck) {
            $pdo->exec("
                ALTER TABLE `blueprints`
                ADD COLUMN `project_id` INT NULL DEFAULT NULL AFTER `description`,
                ADD CONSTRAINT `fk_blueprints_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL;
            ");
            $pdo->exec("UPDATE `blueprints` SET `project_id` = 2 WHERE `id` = 1 AND `project_id` IS NULL");
            $pdo->exec("UPDATE `blueprints` SET `project_id` = 1 WHERE `name` LIKE '%Reckitt%' AND `project_id` IS NULL");
        }

        // Auto-migration: ensure real-world scale (meters) columns exist in blueprints table
        $scaleCheck = $pdo->query("SHOW COLUMNS FROM blueprints LIKE 'real_width_meters'")->fetch();
        if (!$scaleCheck) {
            $pdo->exec("
                ALTER TABLE `blueprints`
                ADD COLUMN `real_width_meters`  DECIMAL(8,2) NOT NULL DEFAULT 60.00 AFTER `image_height`,
                ADD COLUMN `real_height_meters` DECIMAL(8,2) NOT NULL DEFAULT 40.00 AFTER `real_width_meters`;
            ");
            // Set blueprint 1 to 60m x 40m, blueprint 2 (warehouse) to 120m x 70m
            $pdo->exec("UPDATE `blueprints` SET `real_width_meters` = 60.00, `real_height_meters` = 40.00 WHERE `id` = 1");
            $pdo->exec("UPDATE `blueprints` SET `real_width_meters` = 120.00, `real_height_meters` = 70.00 WHERE `id` = 2");
        }

        // Auto-migration: ensure cables table exists for Interactive Cable Routes & Length Calculator
        $cableTableCheck = $pdo->query("SHOW TABLES LIKE 'cables'")->fetch();
        if (!$cableTableCheck) {
            $pdo->exec("
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
            ");

            // Seed sample realistic cable routes for Blueprint 1 connecting to NVR-01 (ID 4)
            // Blueprint 1 dimensions: 60m x 40m. NVR-01 at (14%, 70%)
            $nodeCheck = $pdo->query("SELECT id, device_id FROM nodes WHERE blueprint_id = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
            // Flip to [device_id => id]
            $devMap = array_flip($nodeCheck);
            if (!empty($devMap['NVR-01']) && !empty($devMap['CAM-01'])) {
                $nvrId = $devMap['NVR-01'];
                
                // CAM-01 (18.5%, 18%) -> NVR-01 (14%, 70%) with corridor waypoint
                $c1Wp = json_encode([['x' => 18.5, 'y' => 50.0], ['x' => 14.0, 'y' => 50.0]]);
                $c1Len = 34.50; // calculated ~31.5m run + 3m drop

                // CAM-02 (95%, 50%) -> NVR-01 (14%, 70%) through central corridor
                $c2Wp = json_encode([['x' => 50.0, 'y' => 50.0], ['x' => 14.0, 'y' => 50.0]]);
                $c2Len = 59.80; // calculated ~56.8m run + 3m drop

                // CAM-03 (50%, 22%) -> NVR-01 (14%, 70%)
                $c3Wp = json_encode([['x' => 50.0, 'y' => 50.0], ['x' => 14.0, 'y' => 50.0]]);
                $c3Len = 41.20;

                // AP-01 (50%, 50%) -> NVR-01 (14%, 70%)
                $apWp = json_encode([['x' => 14.0, 'y' => 50.0]]);
                $apLen = 29.60;

                // DOOR-01 (15%, 56%) -> NVR-01 (14%, 70%)
                $doorLen = 9.20;

                $pdo->exec("
                    INSERT INTO `cables` 
                    (`blueprint_id`, `from_node_id`, `to_node_id`, `cable_label`, `cable_type`, `conduit_type`, `waypoints`, `slack_drop_meters`, `total_length_meters`, `status`, `color`, `notes`) 
                    VALUES
                    (1, {$devMap['CAM-01']}, {$nvrId}, 'C-L2-001', 'Cat6 UTP', '20mm PVC Conduit', '{$c1Wp}', 3.00, {$c1Len}, 'Live', '#3b82f6', 'Main lobby ceiling conduit run'),
                    (1, {$devMap['CAM-02']}, {$nvrId}, 'C-L2-002', 'Cat6 UTP', '25mm GI Conduit', '{$c2Wp}', 3.00, {$c2Len}, 'Hardware Mounted', '#3b82f6', 'East perimeter corridor trunking'),
                    (1, {$devMap['CAM-03']}, {$nvrId}, 'C-L2-008', 'Cat6A STP', 'Cable Tray / Trunking', '{$c3Wp}', 3.00, {$c3Len}, 'Cable Pulled', '#8b5cf6', 'Conference suite ceiling tray'),
                    (1, {$devMap['AP-01']},  {$nvrId}, 'C-L2-012', 'Cat6 UTP', '20mm PVC Conduit', '{$apWp}', 3.00, {$apLen}, 'Live', '#10b981', 'Corridor PoE Access Point run'),
                    (1, {$devMap['DOOR-01']},{$nvrId}, 'C-SEC-001','2-Core Shielded', '20mm PVC Conduit', NULL, 2.00, {$doorLen}, 'Live', '#f59e0b', 'Server room card reader security link')
                ");
            }
        }
    }
} catch (PDOException $e) {
    // If database doesn't exist, try connecting to MySQL server to create it automatically
    if ($e->getCode() == 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $rootPdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, $options);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);

            $schemaFile = __DIR__ . '/../setup_v2.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }
        } catch (Exception $initEx) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database initialization error: ' . $initEx->getMessage()
            ]);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Cannot connect to MySQL server. Please ensure MySQL is started in Laragon. (' . $e->getMessage() . ')'
        ]);
        exit;
    }
}
