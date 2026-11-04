<?php
/**
 * POST /api/upload_blueprint.php
 * Handles blueprint image upload + metadata save.
 * Accepts multipart/form-data with fields: name, description, image (file)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$name) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Blueprint name is required']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Blueprint image is required']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF, BMP, SVG']);
    exit;
}

// Max 20MB
if ($file['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image too large. Max 20 MB']);
    exit;
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
$filename = 'bp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/blueprints/';
$destPath  = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    exit;
}

// Get image dimensions for alignment verification
$imgSize = getimagesize($destPath);
$imgWidth  = $imgSize[0] ?? 0;
$imgHeight = $imgSize[1] ?? 0;

try {
    $stmt = $pdo->prepare("
        INSERT INTO blueprints (name, description, image_filename, image_width, image_height, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $description, $filename, $imgWidth, $imgHeight, $user['id']]);

    $blueprintId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => "Blueprint '{$name}' uploaded successfully",
        'id'      => $blueprintId,
        'image'   => 'uploads/blueprints/' . $filename,
    ]);
} catch (PDOException $e) {
    // Clean up file on DB error
    @unlink($destPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

