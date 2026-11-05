<?php
/**
 * POST /api/save_node.php
 * Creates or updates a node on a specific blueprint
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

// Access check
if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied to this blueprint']);
    exit;
}

$type            = trim($data['type'] ?? 'Generic');
$serial_number   = trim($data['serial_number'] ?? '');
$ip_address      = trim($data['ip_address'] ?? '');
$mac_address     = trim($data['mac_address'] ?? '');
$switch_id       = trim($data['switch_id'] ?? '');
$port_no         = trim($data['port_no'] ?? '');
$cable_label     = trim($data['cable_label'] ?? '');
$pos_x           = floatval($data['pos_x'] ?? 0);
$pos_y           = floatval($data['pos_y'] ?? 0);
$status          = trim($data['status'] ?? 'Planned');
$notes           = trim($data['notes'] ?? '');
$tested_by       = trim($data['tested_by'] ?? '');
$commission_date = !empty($data['commission_date']) ? trim($data['commission_date']) : null;
$fov_enabled     = !empty($data['fov_enabled']) ? 1 : 0;
$fov_angle       = isset($data['fov_angle']) ? max(10, min(360, intval($data['fov_angle']))) : 90;
$fov_direction   = isset($data['fov_direction']) ? ((intval($data['fov_direction']) % 360) + 360) % 360 : 0;
$fov_distance    = isset($data['fov_distance']) ? max(2, min(80, intval($data['fov_distance']))) : 15;

try {
    $check = $pdo->prepare("SELECT id FROM nodes WHERE blueprint_id = ? AND device_id = ?");
    $check->execute([$blueprintId, $deviceId]);

    if ($check->rowCount() > 0) {
        $update = $pdo->prepare("
            UPDATE nodes SET 
                type = ?, serial_number = ?, ip_address = ?, mac_address = ?, 
                switch_id = ?, port_no = ?, cable_label = ?, pos_x = ?, pos_y = ?, 
                status = ?, notes = ?, tested_by = ?, commission_date = ?,
                fov_enabled = ?, fov_angle = ?, fov_direction = ?, fov_distance = ?
            WHERE blueprint_id = ? AND device_id = ?
        ");
        $update->execute([
            $type, $serial_number, $ip_address, $mac_address,
            $switch_id, $port_no, $cable_label, $pos_x, $pos_y,
            $status, $notes, $tested_by, $commission_date,
            $fov_enabled, $fov_angle, $fov_direction, $fov_distance,
            $blueprintId, $deviceId
        ]);
        echo json_encode(['success' => true, 'action' => 'updated', 'message' => "Node '{$deviceId}' updated"]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO nodes (
                blueprint_id, device_id, type, serial_number, ip_address, mac_address, 
                switch_id, port_no, cable_label, pos_x, pos_y, status, notes, 
                tested_by, commission_date, fov_enabled, fov_angle, fov_direction, fov_distance, created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $blueprintId, $deviceId, $type, $serial_number, $ip_address, $mac_address,
            $switch_id, $port_no, $cable_label, $pos_x, $pos_y, $status, $notes,
            $tested_by, $commission_date, $fov_enabled, $fov_angle, $fov_direction, $fov_distance, $user['id']
        ]);
        echo json_encode(['success' => true, 'action' => 'created', 'message' => "Node '{$deviceId}' created", 'id' => $pdo->lastInsertId()]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
