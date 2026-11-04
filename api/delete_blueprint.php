<?php
/**
 * POST /api/delete_blueprint.php
 * Deletes a blueprint and all its nodes + access rows (via CASCADE)
 * Admin or owner only
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$data = json_decode(file_get_contents('php://input'), true);
$blueprintId = intval($data['blueprint_id'] ?? 0);

if (!$blueprintId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'blueprint_id is required']);
    exit;
}

try {
    // Get blueprint info
    $bp = $pdo->prepare("SELECT * FROM blueprints WHERE id = ?");
    $bp->execute([$blueprintId]);
    $bpRow = $bp->fetch();

    if (!$bpRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Blueprint not found']);
        exit;
    }

    if ($user['role'] !== 'admin' && $bpRow['uploaded_by'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only admin or owner can delete']);
        exit;
    }

    // Delete the image file
    $imagePath = __DIR__ . '/../uploads/blueprints/' . $bpRow['image_filename'];
    if (file_exists($imagePath)) {
        @unlink($imagePath);
    }

    // Delete from DB (nodes + access cascade)
    $del = $pdo->prepare("DELETE FROM blueprints WHERE id = ?");
    $del->execute([$blueprintId]);

    echo json_encode(['success' => true, 'message' => 'Blueprint deleted']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

