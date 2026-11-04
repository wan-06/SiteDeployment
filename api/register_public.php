<?php
/**
 * POST /api/register_public.php
 * Public self-registration for new users/technicians
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$username  = strtolower(trim($data['username'] ?? ''));
$full_name = trim($data['full_name'] ?? '');
$email     = strtolower(trim($data['email'] ?? ''));
$password  = $data['password'] ?? '';

if (!$username || !$full_name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username must be 3-30 characters (letters, numbers, underscores only).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    // Check if username or email exists
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$username, $email]);
    if ($check->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'That username or email is already registered.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'user')");
    $stmt->execute([$username, $email, $hash, $full_name]);
    $userId = $pdo->lastInsertId();

    // Automatically grant access to demo blueprint #1 if it exists
    $bpCheck = $pdo->query("SELECT id FROM blueprints WHERE id = 1")->fetch();
    if ($bpCheck) {
        $grant = $pdo->prepare("INSERT IGNORE INTO blueprint_access (blueprint_id, user_id, granted_by) VALUES (1, ?, 1)");
        $grant->execute([$userId]);
    }

    // Auto log in new user
    $_SESSION['user_id']   = $userId;
    $_SESSION['username']  = $username;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email']     = $email;
    $_SESSION['role']      = 'user';

    echo json_encode([
        'success'  => true,
        'message'  => "Welcome, {$full_name}! Account created.",
        'redirect' => 'dashboard.php'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

