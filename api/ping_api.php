<?php
/**
 * /api/ping_api.php
 * Real-Time Live Ping Status & NOC Device Monitoring API
 * 
 * Supports:
 * - Ping single node (ICMP + TCP fallback, or simulated demo mode)
 * - Batch ping multiple nodes on a blueprint
 * - Get blueprint ping health summary
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$action = $data['action'] ?? ($_GET['action'] ?? 'ping_node');
$demoMode = !empty($data['demo_mode']) || !empty($_GET['demo_mode']);

/**
 * Validates IPv4, IPv6 or hostname
 */
function isValidTarget($target) {
    if (empty($target)) return false;
    $target = trim($target);
    if (filter_var($target, FILTER_VALIDATE_IP)) return true;
    if (preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $target)) return true;
    if ($target === 'localhost') return true;
    return false;
}

/**
 * Probes target IP/host via ICMP ping and TCP socket fallback
 */
function probeTarget($ip, $demoMode = false, $deviceId = '') {
    $ip = trim($ip);

    if (!isValidTarget($ip)) {
        return [
            'status'     => 'invalid',
            'latency_ms' => null,
            'method'     => 'None',
            'message'    => 'Invalid IP or hostname format'
        ];
    }

    // ── DEMO / PRESENTATION MODE ──
    if ($demoMode) {
        // High fidelity simulated response for presentations / offline demos
        // Use deterministic hash of device_id/ip so status stays consistent unless toggled
        $hash = crc32($ip . $deviceId);
        $isOffline = ($hash % 8 === 0); // ~12% chance offline for realism

        if ($isOffline) {
            usleep(150000); // 150ms delay
            return [
                'status'     => 'offline',
                'latency_ms' => null,
                'method'     => 'Simulated ICMP',
                'message'    => 'Request timed out (Simulated)'
            ];
        } else {
            $lat = 4 + ($hash % 28); // 4ms - 32ms
            usleep($lat * 2000);
            return [
                'status'     => 'online',
                'latency_ms' => $lat,
                'method'     => 'Simulated ICMP',
                'message'    => "Reply from {$ip}: bytes=32 time={$lat}ms TTL=64"
            ];
        }
    }

    // ── REAL MODE: STEP 1 - ICMP Ping (Windows ping.exe) ──
    $cmd = "ping -n 1 -w 600 " . escapeshellarg($ip);
    exec($cmd, $output, $returnVar);
    $outputStr = implode("\n", $output);

    $isOnline = false;
    $latency = null;

    if (stripos($outputStr, 'Reply from') !== false && (stripos($outputStr, 'TTL=') !== false || stripos($outputStr, 'bytes=') !== false)) {
        $isOnline = true;
        if (preg_match('/time[=<](\d+)ms/i', $outputStr, $m)) {
            $latency = max(1, intval($m[1]));
        } elseif (stripos($outputStr, 'time<1ms') !== false) {
            $latency = 1;
        }
        return [
            'status'     => 'online',
            'latency_ms' => $latency ?? 2,
            'method'     => 'ICMP',
            'message'    => "Reply from {$ip}: time={$latency}ms"
        ];
    }

    // ── REAL MODE: STEP 2 - Fallback TCP Socket Probe ──
    // Some CCTV cameras or firewalled switches drop ICMP echo requests but keep ports open
    $commonPorts = [80, 554, 8000, 443, 22];
    foreach ($commonPorts as $port) {
        $t0 = microtime(true);
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.35);
        if ($fp) {
            $roundTrip = max(1, round((microtime(true) - $t0) * 1000));
            fclose($fp);
            return [
                'status'     => 'online',
                'latency_ms' => $roundTrip,
                'method'     => "TCP:{$port}",
                'message'    => "TCP Port {$port} open ({$roundTrip}ms)"
            ];
        }
    }

    // Both ICMP and TCP failed -> Offline
    return [
        'status'     => 'offline',
        'latency_ms' => null,
        'method'     => 'ICMP/TCP Probe',
        'message'    => 'Destination unreachable or timed out'
    ];
}

// ══════════════════════════════════════════════════════════════
// ── ACTION: PING SINGLE NODE ──
// ══════════════════════════════════════════════════════════════
if ($action === 'ping_node') {
    $nodeId      = intval($data['node_id'] ?? ($_GET['node_id'] ?? 0));
    $blueprintId = intval($data['blueprint_id'] ?? ($_GET['blueprint_id'] ?? 0));
    $deviceId    = trim($data['device_id'] ?? ($_GET['device_id'] ?? ''));

    if (!$nodeId && (!$blueprintId || !$deviceId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'node_id or (blueprint_id + device_id) is required']);
        exit;
    }

    try {
        if ($nodeId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM nodes WHERE id = ?");
            $stmt->execute([$nodeId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM nodes WHERE blueprint_id = ? AND device_id = ?");
            $stmt->execute([$blueprintId, $deviceId]);
        }
        $node = $stmt->fetch();

        if (!$node) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Node not found']);
            exit;
        }

        if (!hasAccessToBlueprint($pdo, $node['blueprint_id'], $user['id'], $user['role'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $ip = trim($node['ip_address'] ?? '');
        $probe = probeTarget($ip, $demoMode, $node['device_id']);

        // Update database with latest probe results
        $upStmt = $pdo->prepare("
            UPDATE nodes 
            SET ping_status = ?, ping_latency_ms = ?, last_ping_at = NOW() 
            WHERE id = ?
        ");
        $upStmt->execute([
            $probe['status'],
            $probe['latency_ms'],
            $node['id']
        ]);

        echo json_encode([
            'success'        => true,
            'node_id'        => intval($node['id']),
            'device_id'      => $node['device_id'],
            'ip_address'     => $ip,
            'status'         => $probe['status'],
            'latency_ms'     => $probe['latency_ms'],
            'method'         => $probe['method'],
            'message'        => $probe['message'],
            'last_ping_at'   => date('Y-m-d H:i:s'),
            'demo_mode'      => $demoMode
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// ── ACTION: PING BATCH (ALL OR MULTIPLE NODES ON BLUEPRINT) ──
// ══════════════════════════════════════════════════════════════
if ($action === 'ping_batch') {
    $blueprintId = intval($data['blueprint_id'] ?? ($_GET['blueprint_id'] ?? 0));
    $nodeIds     = $data['node_ids'] ?? null;

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
        if (!empty($nodeIds) && is_array($nodeIds)) {
            $inClause = implode(',', array_map('intval', $nodeIds));
            $stmt = $pdo->query("SELECT * FROM nodes WHERE blueprint_id = {$blueprintId} AND id IN ({$inClause}) ORDER BY device_id ASC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM nodes WHERE blueprint_id = ? ORDER BY device_id ASC");
            $stmt->execute([$blueprintId]);
        }
        $nodes = $stmt->fetchAll();

        $results = [];
        $onlineCount = 0;
        $offlineCount = 0;
        $invalidCount = 0;
        $totalLatency = 0;
        $latencySampleCount = 0;

        $upStmt = $pdo->prepare("
            UPDATE nodes 
            SET ping_status = ?, ping_latency_ms = ?, last_ping_at = NOW() 
            WHERE id = ?
        ");

        foreach ($nodes as $n) {
            $ip = trim($n['ip_address'] ?? '');
            $probe = probeTarget($ip, $demoMode, $n['device_id']);

            $upStmt->execute([
                $probe['status'],
                $probe['latency_ms'],
                $n['id']
            ]);

            if ($probe['status'] === 'online') {
                $onlineCount++;
                if ($probe['latency_ms'] !== null) {
                    $totalLatency += $probe['latency_ms'];
                    $latencySampleCount++;
                }
            } elseif ($probe['status'] === 'offline') {
                $offlineCount++;
            } else {
                $invalidCount++;
            }

            $results[] = [
                'node_id'         => intval($n['id']),
                'device_id'       => $n['device_id'],
                'ip_address'      => $ip,
                'status'          => $probe['status'],
                'ping_status'     => $probe['status'],
                'latency_ms'      => $probe['latency_ms'],
                'ping_latency_ms' => $probe['latency_ms'],
                'method'          => $probe['method'],
                'last_ping_at'    => date('Y-m-d H:i:s')
            ];
        }

        $avgLatency = $latencySampleCount > 0 ? round($totalLatency / $latencySampleCount, 1) : 0;

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_scanned'   => count($nodes),
                'online'          => $onlineCount,
                'offline'         => $offlineCount,
                'invalid'         => $invalidCount,
                'avg_latency_ms'  => $avgLatency,
                'scan_time'       => date('Y-m-d H:i:s'),
                'demo_mode'       => $demoMode
            ],
            'results' => $results,
            'data'    => $results
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// ── ACTION: RESET / CLEAR PING STATUS ──
// ══════════════════════════════════════════════════════════════
if ($action === 'reset_ping') {
    $blueprintId = intval($data['blueprint_id'] ?? 0);
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
        $stmt = $pdo->prepare("UPDATE nodes SET ping_status = 'unknown', ping_latency_ms = NULL WHERE blueprint_id = ?");
        $stmt->execute([$blueprintId]);
        echo json_encode(['success' => true, 'message' => 'Ping statuses reset']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// ── ACTION: BLUEPRINT PING HEALTH SUMMARY ──
// ══════════════════════════════════════════════════════════════
if ($action === 'summary') {
    $blueprintId = intval($_GET['blueprint_id'] ?? ($data['blueprint_id'] ?? 0));
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
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_nodes,
                SUM(CASE WHEN ip_address != '' AND ip_address IS NOT NULL THEN 1 ELSE 0 END) AS total_with_ip,
                SUM(CASE WHEN ping_status = 'online' THEN 1 ELSE 0 END) AS online_count,
                SUM(CASE WHEN ping_status = 'offline' THEN 1 ELSE 0 END) AS offline_count,
                SUM(CASE WHEN ping_status = 'unknown' OR ping_status = '' OR ping_status IS NULL THEN 1 ELSE 0 END) AS unknown_count,
                AVG(CASE WHEN ping_status = 'online' AND ping_latency_ms IS NOT NULL THEN ping_latency_ms ELSE NULL END) AS avg_latency,
                MAX(last_ping_at) AS last_scan_at
            FROM nodes 
            WHERE blueprint_id = ?
        ");
        $stmt->execute([$blueprintId]);
        $row = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_nodes'    => intval($row['total_nodes']),
                'total_with_ip'  => intval($row['total_with_ip']),
                'online'         => intval($row['online_count']),
                'offline'        => intval($row['offline_count']),
                'unknown'        => intval($row['unknown_count']),
                'avg_latency_ms' => round(floatval($row['avg_latency']), 1),
                'last_scan_at'   => $row['last_scan_at']
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
