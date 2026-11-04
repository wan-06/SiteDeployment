<?php
/**
 * GET /api/get_nodes.php?blueprint_id=X
 * Returns all nodes for a specific blueprint (with access check)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$blueprintId = intval($_GET['blueprint_id'] ?? 0);
if (!$blueprintId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'blueprint_id is required']);
    exit;
}

// Access check
if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied to this blueprint']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.username AS created_by_username
        FROM nodes n
        LEFT JOIN users u ON u.id = n.created_by
        WHERE n.blueprint_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$blueprintId]);
    $nodes = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($nodes),
        'data'    => $nodes
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
