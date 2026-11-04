<?php
/**
 * POST /api/manage_access.php
 * Admin or blueprint owner: grant/revoke user access to a blueprint
 * 
 * Actions:
 *   { action: "grant",  blueprint_id: 1, user_id: 2 }
 *   { action: "revoke", blueprint_id: 1, user_id: 2 }
 *   { action: "list",   blueprint_id: 1 }              ← list users with access
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$data = json_decode(file_get_contents('php://input'), true);
$action      = $data['action'] ?? '';
$blueprintId = intval($data['blueprint_id'] ?? 0);

if (!$blueprintId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'blueprint_id is required']);
    exit;
}

// Only admin or the blueprint owner can manage access
$bp = $pdo->prepare("SELECT uploaded_by FROM blueprints WHERE id = ?");
$bp->execute([$blueprintId]);
$bpRow = $bp->fetch();

if (!$bpRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Blueprint not found']);
    exit;
}

if ($user['role'] !== 'admin' && $bpRow['uploaded_by'] != $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only admin or blueprint owner can manage access']);
    exit;
}

try {
    switch ($action) {
        case 'grant':
            $targetUserId = intval($data['user_id'] ?? 0);
            if (!$targetUserId) {
                echo json_encode(['success' => false, 'error' => 'user_id is required']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO blueprint_access (blueprint_id, user_id, granted_by) VALUES (?, ?, ?)");
            $stmt->execute([$blueprintId, $targetUserId, $user['id']]);
            echo json_encode(['success' => true, 'message' => 'Access granted']);
            break;

        case 'revoke':
            $targetUserId = intval($data['user_id'] ?? 0);
            if (!$targetUserId) {
                echo json_encode(['success' => false, 'error' => 'user_id is required']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM blueprint_access WHERE blueprint_id = ? AND user_id = ?");
            $stmt->execute([$blueprintId, $targetUserId]);
            echo json_encode(['success' => true, 'message' => 'Access revoked']);
            break;

        case 'list':
            // Get all users who have access (owner + granted)
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.full_name, u.role, 'granted' AS access_type, ba.created_at
                FROM blueprint_access ba
                JOIN users u ON u.id = ba.user_id
                WHERE ba.blueprint_id = ?
                UNION
                SELECT u.id, u.username, u.full_name, u.role, 'owner' AS access_type, b.created_at
                FROM blueprints b
                JOIN users u ON u.id = b.uploaded_by
                WHERE b.id = ?
                ORDER BY access_type DESC
            ");
            $stmt->execute([$blueprintId, $blueprintId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: grant, revoke, list']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

