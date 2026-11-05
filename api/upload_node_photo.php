<?php
/**
 * POST /api/upload_node_photo.php
 * Handles mobile / on-site photographic evidence upload for a node.
 * Accepts multipart/form-data:
 *   - blueprint_id (int)
 *   - device_id    (string)
 *   - caption      (string, optional)
 *   - photo        (file)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$blueprintId = intval($_POST['blueprint_id'] ?? 0);
$deviceId    = trim($_POST['device_id'] ?? '');
$caption     = trim($_POST['caption'] ?? '');

if (!$blueprintId || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'blueprint_id and device_id are required']);
    exit;
}

// Access check
if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied to this blueprint']);
    exit;
}

// Check if node exists
$nodeStmt = $pdo->prepare("SELECT id, device_id FROM nodes WHERE blueprint_id = ? AND device_id = ? LIMIT 1");
$nodeStmt->execute([$blueprintId, $deviceId]);
$node = $nodeStmt->fetch();
if (!$node) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Node '{$deviceId}' not found on this blueprint"]);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['photo']['error'] ?? 'missing';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Site photo file is required or upload failed (code ' . $errCode . ')']);
    exit;
}

$file = $_FILES['photo'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF, SVG']);
    exit;
}

// Max 25MB (high-res mobile camera capture)
if ($file['size'] > 25 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Photo too large. Max 25 MB']);
    exit;
}

// Generate unique safe filename
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!$ext || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
    $map = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg'
    ];
    $ext = $map[$mimeType] ?? 'jpg';
}

$safeDev = preg_replace('/[^a-zA-Z0-9_-]/', '', $deviceId);
$filename = 'site_' . $blueprintId . '_' . $safeDev . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

$uploadDir = __DIR__ . '/../uploads/nodes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save site photo to disk']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO node_photos (blueprint_id, device_id, filename, caption, uploaded_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$blueprintId, $deviceId, $filename, $caption ?: null, $user['id']]);
    $photoId = $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare("SELECT * FROM node_photos WHERE id = ?");
    $fetchStmt->execute([$photoId]);
    $newPhoto = $fetchStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Site verification photo uploaded successfully',
        'photo'   => $newPhoto
    ]);
} catch (PDOException $e) {
    @unlink($destPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
