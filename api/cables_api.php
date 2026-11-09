<?php
/**
 * /api/cables_api.php
 * Interactive Cable Routes & Length Calculator API
 * 
 * GET:
 *   ?blueprint_id=X - Returns all cable routes, blueprint scale, and BOM summaries
 * 
 * POST:
 *   action: 'save' - Create or update cable route
 *   action: 'delete' - Delete cable route by id
 *   action: 'update_scale' - Update blueprint real-world scale (width & height in meters)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$method = $_SERVER['REQUEST_METHOD'];

// Helper function to calculate Euclidean distance between two points in meters
function calcSegmentMeters($p1, $p2, $realWidthM, $realHeightM) {
    $dx = (($p2['x'] - $p1['x']) / 100.0) * $realWidthM;
    $dy = (($p2['y'] - $p1['y']) / 100.0) * $realHeightM;
    return sqrt($dx * $dx + $dy * $dy);
}

// Helper function to calculate total cable length from waypoints and slack
function computeTotalLength($pStart, $pEnd, $waypoints, $slackM, $realWidthM, $realHeightM) {
    $points = [];
    $points[] = $pStart;
    if (!empty($waypoints) && is_array($waypoints)) {
        foreach ($waypoints as $wp) {
            if (isset($wp['x']) && isset($wp['y'])) {
                $points[] = ['x' => floatval($wp['x']), 'y' => floatval($wp['y'])];
            }
        }
    }
    $points[] = $pEnd;

    $horizontalRun = 0.0;
    for ($i = 0; $i < count($points) - 1; $i++) {
        $horizontalRun += calcSegmentMeters($points[$i], $points[$i + 1], $realWidthM, $realHeightM);
    }

    return round($horizontalRun + floatval($slackM), 2);
}

if ($method === 'GET') {
    $blueprintId = intval($_GET['blueprint_id'] ?? 0);

    if ($blueprintId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'blueprint_id is required']);
        exit;
    }

    if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    try {
        // Fetch blueprint scale
        $bpStmt = $pdo->prepare("SELECT id, name, real_width_meters, real_height_meters, image_width, image_height FROM blueprints WHERE id = ?");
        $bpStmt->execute([$blueprintId]);
        $bp = $bpStmt->fetch();

        $realWidthM = floatval($bp['real_width_meters'] ?? 60.0);
        $realHeightM = floatval($bp['real_height_meters'] ?? 40.0);

        // Fetch all cables with source and target node details
        $stmt = $pdo->prepare("
            SELECT c.*,
                   fn.device_id AS from_device_id, fn.type AS from_type, fn.pos_x AS from_pos_x, fn.pos_y AS from_pos_y, fn.status AS from_status,
                   tn.device_id AS to_device_id, tn.type AS to_type, tn.pos_x AS to_pos_x, tn.pos_y AS to_pos_y, tn.status AS to_status
            FROM cables c
            JOIN nodes fn ON fn.id = c.from_node_id
            JOIN nodes tn ON tn.id = c.to_node_id
            WHERE c.blueprint_id = ?
            ORDER BY c.cable_label ASC, c.id ASC
        ");
        $stmt->execute([$blueprintId]);
        $cables = $stmt->fetchAll();

        // Decode waypoints and calculate BOM aggregates
        $totalMeters = 0.0;
        $typeMap = [];
        $statusMap = [];

        foreach ($cables as &$c) {
            $c['waypoints'] = !empty($c['waypoints']) ? json_decode($c['waypoints'], true) : [];
            $len = floatval($c['total_length_meters']);
            $totalMeters += $len;

            $type = $c['cable_type'] ?: 'Generic Cable';
            if (!isset($typeMap[$type])) {
                $typeMap[$type] = ['meters' => 0.0, 'count' => 0];
            }
            $typeMap[$type]['meters'] += $len;
            $typeMap[$type]['count'] += 1;

            $st = $c['status'] ?: 'Planned';
            $statusMap[$st] = ($statusMap[$st] ?? 0) + 1;
        }

        // Calculate 305m drum/box estimates for each cable type
        $drumEstimates = [];
        foreach ($typeMap as $type => $info) {
            $m = round($info['meters'], 2);
            $drumSize = 305.0; // Standard 305m / 1000ft Cat6 box
            $boxes = (int)ceil($m / $drumSize);
            $leftover = round(($boxes * $drumSize) - $m, 2);
            $drumEstimates[$type] = [
                'total_meters' => $m,
                'count'        => $info['count'],
                'box_standard' => $drumSize,
                'boxes_needed' => max(1, $boxes),
                'leftover_m'   => $leftover
            ];
        }

        echo json_encode([
            'success' => true,
            'scale' => [
                'real_width_meters'  => $realWidthM,
                'real_height_meters' => $realHeightM,
            ],
            'cables'  => $cables,
            'summary' => [
                'total_cables'       => count($cables),
                'total_meters'       => round($totalMeters, 2),
                'type_breakdown'     => $drumEstimates,
                'status_breakdown'   => $statusMap
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $action = $data['action'] ?? 'save';

    // ── ACTION: UPDATE SCALE ──
    if ($action === 'update_scale') {
        $blueprintId = intval($data['blueprint_id'] ?? 0);
        $widthM = floatval($data['real_width_meters'] ?? 0);
        $heightM = floatval($data['real_height_meters'] ?? 0);

        if ($blueprintId <= 0 || $widthM <= 0 || $heightM <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid blueprint_id and positive width/height meters are required']);
            exit;
        }

        if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        try {
            // Update blueprint scale
            $updateBp = $pdo->prepare("UPDATE blueprints SET real_width_meters = ?, real_height_meters = ? WHERE id = ?");
            $updateBp->execute([$widthM, $heightM, $blueprintId]);

            // Recalculate all cable lengths on this blueprint with the new scale!
            $cablesStmt = $pdo->prepare("
                SELECT c.id, c.waypoints, c.slack_drop_meters,
                       fn.pos_x AS from_x, fn.pos_y AS from_y,
                       tn.pos_x AS to_x, tn.pos_y AS to_y
                FROM cables c
                JOIN nodes fn ON fn.id = c.from_node_id
                JOIN nodes tn ON tn.id = c.to_node_id
                WHERE c.blueprint_id = ?
            ");
            $cablesStmt->execute([$blueprintId]);
            $existingCables = $cablesStmt->fetchAll();

            $updateCableLen = $pdo->prepare("UPDATE cables SET total_length_meters = ? WHERE id = ?");
            foreach ($existingCables as $ec) {
                $pStart = ['x' => floatval($ec['from_x']), 'y' => floatval($ec['from_y'])];
                $pEnd   = ['x' => floatval($ec['to_x']),   'y' => floatval($ec['to_y'])];
                $wp     = !empty($ec['waypoints']) ? json_decode($ec['waypoints'], true) : [];
                $newLen = computeTotalLength($pStart, $pEnd, $wp, $ec['slack_drop_meters'], $widthM, $heightM);
                $updateCableLen->execute([$newLen, $ec['id']]);
            }

            echo json_encode([
                'success' => true,
                'message' => "Scale updated to {$widthM}m × {$heightM}m. All cable routes recalculated."
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── ACTION: DELETE CABLE ──
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cable id is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT blueprint_id FROM cables WHERE id = ?");
            $stmt->execute([$id]);
            $cable = $stmt->fetch();
            if (!$cable) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Cable not found']);
                exit;
            }

            if (!hasAccessToBlueprint($pdo, $cable['blueprint_id'], $user['id'], $user['role'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $del = $pdo->prepare("DELETE FROM cables WHERE id = ?");
            $del->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Cable route deleted']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── ACTION: SAVE CABLE (Create or Update) ──
    $id                = intval($data['id'] ?? 0);
    $blueprintId       = intval($data['blueprint_id'] ?? 0);
    $fromNodeId        = intval($data['from_node_id'] ?? 0);
    $toNodeId          = intval($data['to_node_id'] ?? 0);
    $cableLabel        = trim($data['cable_label'] ?? '');
    $cableType         = trim($data['cable_type'] ?? 'Cat6 UTP');
    $conduitType       = trim($data['conduit_type'] ?? '20mm PVC Conduit');
    $slackDropMeters   = floatval($data['slack_drop_meters'] ?? 3.0);
    $status            = trim($data['status'] ?? 'Planned');
    $color             = trim($data['color'] ?? '#3b82f6');
    $notes             = trim($data['notes'] ?? '');
    $waypoints         = $data['waypoints'] ?? [];

    if (!$blueprintId || !$fromNodeId || !$toNodeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'blueprint_id, from_node_id, and to_node_id are required']);
        exit;
    }

    if ($fromNodeId === $toNodeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A cable cannot connect a node to itself']);
        exit;
    }

    if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    try {
        // Fetch blueprint scale and node positions for exact length computation
        $bpStmt = $pdo->prepare("SELECT real_width_meters, real_height_meters FROM blueprints WHERE id = ?");
        $bpStmt->execute([$blueprintId]);
        $bp = $bpStmt->fetch();
        $realWidthM = floatval($bp['real_width_meters'] ?? 60.0);
        $realHeightM = floatval($bp['real_height_meters'] ?? 40.0);

        $nodeStmt = $pdo->prepare("SELECT id, device_id, pos_x, pos_y FROM nodes WHERE id IN (?, ?)");
        $nodeStmt->execute([$fromNodeId, $toNodeId]);
        $nodes = $nodeStmt->fetchAll();

        if (count($nodes) < 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Source or destination node not found on this blueprint']);
            exit;
        }

        $nodeMap = [];
        foreach ($nodes as $n) { $nodeMap[$n['id']] = $n; }

        $pStart = ['x' => floatval($nodeMap[$fromNodeId]['pos_x']), 'y' => floatval($nodeMap[$fromNodeId]['pos_y'])];
        $pEnd   = ['x' => floatval($nodeMap[$toNodeId]['pos_x']),   'y' => floatval($nodeMap[$toNodeId]['pos_y'])];

        // Fallback cable label if empty
        if (!$cableLabel) {
            $cableLabel = 'C-' . $nodeMap[$fromNodeId]['device_id'];
        }

        // Clean waypoints array
        $cleanWp = [];
        if (is_array($waypoints)) {
            foreach ($waypoints as $wp) {
                if (isset($wp['x']) && isset($wp['y'])) {
                    $cleanWp[] = [
                        'x' => round(floatval($wp['x']), 4),
                        'y' => round(floatval($wp['y']), 4)
                    ];
                }
            }
        }
        $wpJson = !empty($cleanWp) ? json_encode($cleanWp) : null;

        // Compute total length
        $totalLength = computeTotalLength($pStart, $pEnd, $cleanWp, $slackDropMeters, $realWidthM, $realHeightM);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE cables SET
                    from_node_id = ?, to_node_id = ?, cable_label = ?, cable_type = ?,
                    conduit_type = ?, waypoints = ?, slack_drop_meters = ?, total_length_meters = ?,
                    status = ?, color = ?, notes = ?
                WHERE id = ? AND blueprint_id = ?
            ");
            $stmt->execute([
                $fromNodeId, $toNodeId, $cableLabel, $cableType,
                $conduitType, $wpJson, $slackDropMeters, $totalLength,
                $status, $color, $notes, $id, $blueprintId
            ]);
            $cableId = $id;
            $msg = "Cable '{$cableLabel}' updated ({$totalLength}m)";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cables
                (blueprint_id, from_node_id, to_node_id, cable_label, cable_type, conduit_type, waypoints, slack_drop_meters, total_length_meters, status, color, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $blueprintId, $fromNodeId, $toNodeId, $cableLabel, $cableType,
                $conduitType, $wpJson, $slackDropMeters, $totalLength,
                $status, $color, $notes
            ]);
            $cableId = $pdo->lastInsertId();
            $msg = "Cable '{$cableLabel}' routed ({$totalLength}m)";
        }

        echo json_encode([
            'success'              => true,
            'message'              => $msg,
            'id'                   => $cableId,
            'total_length_meters'  => $totalLength,
            'waypoints'            => $cleanWp
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

