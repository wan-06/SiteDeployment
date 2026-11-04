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
