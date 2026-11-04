<?php
/**
 * POST /api/register_process.php
 * Admin-only: Create a new user account
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$username  = trim($data['username']  ?? '');
$email     = trim($data['email']     ?? '');
$full_name = trim($data['full_name'] ?? '');
$password  = $data['password'] ?? '';
$role      = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

if (!$username || !$email || !$full_name || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check duplicates
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$username, $email]);
    if ($check->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $full_name, $role]);

    echo json_encode([
        'success' => true,
        'message' => "User '{$username}' created successfully",
        'id'      => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

