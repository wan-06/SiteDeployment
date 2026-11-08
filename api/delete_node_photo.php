<?php
/**
 * POST /api/delete_node_photo.php
 * Deletes a site verification photo by photo_id
 * Accepts application/json or form-data with { photo_id: X }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];
$photoId = intval($data['photo_id'] ?? $_POST['photo_id'] ?? 0);

if (!$photoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'photo_id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM node_photos WHERE id = ?");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo not found']);
        exit;
    }

    // Access check on blueprint
    if (!hasAccessToBlueprint($pdo, $photo['blueprint_id'], $user['id'], $user['role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to this blueprint']);
        exit;
    }

    // Only allow photo deletion if user is admin or the uploader (or has editor role)
    if ($user['role'] !== 'admin' && intval($photo['uploaded_by']) !== intval($user['id']) && $user['role'] !== 'technician') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this photo']);
        exit;
    }

    // Delete record from DB
    $delStmt = $pdo->prepare("DELETE FROM node_photos WHERE id = ?");
    $delStmt->execute([$photoId]);

    // Remove file from disk if exists (unless it's a seed sample or shared)
    $filePath = __DIR__ . '/../uploads/nodes/' . basename($photo['filename']);
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'Photo deleted successfully',
        'photo_id'  => $photoId,
        'device_id' => $photo['device_id']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

