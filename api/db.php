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
            'error'   => 'Cannot connect to MySQL server. Please ensure MySQL is started in XAMPP Control Panel. (' . $e->getMessage() . ')'
        ]);
        exit;
    }
}
