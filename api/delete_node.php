<?php
/**
 * POST /api/delete_node.php
 * Deletes a node by device_id + blueprint_id
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$data = json_decode(file_get_contents('php://input'), true);

$blueprintId = intval($data['blueprint_id'] ?? 0);
$deviceId    = trim($data['device_id'] ?? '');

if (!$blueprintId || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'blueprint_id and device_id are required']);
    exit;
}

if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM nodes WHERE blueprint_id = ? AND device_id = ?");
    $stmt->execute([$blueprintId, $deviceId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => "Node '{$deviceId}' deleted"]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Node not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
