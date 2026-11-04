<?php
/**
 * GET /api/users_api.php
 * List users (admin: all users, user: just self)
 * Supports ?q= search parameter
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$search = trim($_GET['q'] ?? '');

try {
    if ($user['role'] === 'admin') {
        $sql = "SELECT id, username, email, full_name, role, created_at FROM users";
        $params = [];

        if ($search) {
            $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
            $like = "%{$search}%";
            $params = [$like, $like, $like];
        }
        $sql .= " ORDER BY created_at DESC";
    } else {
        $sql = "SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ?";
        $params = [$user['id']];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

