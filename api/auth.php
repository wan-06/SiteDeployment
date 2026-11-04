<?php
/**
 * Auth Middleware
 * Include at the top of any page that requires login.
 * Sets $currentUser array with id, username, email, full_name, role.
 */

session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo '<h1>403 — Access Denied</h1><p>Admin privileges required.</p>';
        exit;
    }
}

function getCurrentUser() {
    return [
        'id'        => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']   ?? null,
        'full_name' => $_SESSION['full_name']  ?? null,
        'email'     => $_SESSION['email']      ?? null,
        'role'      => $_SESSION['role']       ?? null,
    ];
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if a user has access to a specific blueprint.
 * Admins have access to everything.
 * Users have access if they uploaded it OR have a blueprint_access row.
 */
function hasAccessToBlueprint($pdo, $blueprintId, $userId, $role) {
    if ($role === 'admin') return true;

    $stmt = $pdo->prepare("
        SELECT 1 FROM blueprints WHERE id = ? AND uploaded_by = ?
        UNION
        SELECT 1 FROM blueprint_access WHERE blueprint_id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$blueprintId, $userId, $blueprintId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * API-level auth check (for AJAX calls).
 * Returns JSON error instead of redirect.
 */
function requireApiAuth() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}

