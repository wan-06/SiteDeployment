<?php
/**
 * quick_scan.php
 * Mobile-First Technician Quick-Scan & Asset Inspection Page
 * 
 * Opened when a technician scans the physical QR sticker on a camera / rack / conduit on-site.
 * 
 * Capabilities:
 * - Immediate physical device inspection (IP, MAC, Switch Port, Cable Tag, Specs)
 * - 1-Tap status update (Planned -> Cable Pulled -> Mounted -> Live -> Troubleshoot)
 * - On-site live ping test (verifies camera response on LAN)
 * - Photographic evidence capture directly via mobile camera
 * - Quick jump to interactive floorplan location
 */

require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/db.php';

$user = getCurrentUser();
$isLoggedIn = !empty($user['id']);

$nodeId   = intval($_GET['id'] ?? 0);
$deviceId = trim($_GET['dev'] ?? '');
$bpId     = intval($_GET['bp'] ?? 0);

// ── HANDLE QUICK AJAX ACTIONS (Status update / Notes update) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'You must be logged in to update device status or notes.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $postData = json_decode($raw, true) ?? $_POST;
    $action = $postData['action'] ?? '';
    $targetNodeId = intval($postData['node_id'] ?? 0);

    if (!$targetNodeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'node_id is required']);
        exit;
    }

    // Verify access to node
    $checkStmt = $pdo->prepare("SELECT id, blueprint_id, device_id, status, notes FROM nodes WHERE id = ?");
    $checkStmt->execute([$targetNodeId]);
    $targetNode = $checkStmt->fetch();

    if (!$targetNode) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Node not found']);
        exit;
    }

    if (!hasAccessToBlueprint($pdo, $targetNode['blueprint_id'], $user['id'], $user['role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to this project blueprint']);
        exit;
    }

    if ($action === 'update_status') {
        $newStatus = trim($postData['status'] ?? '');
        $allowed = ['Planned', 'Cable Pulled', 'Hardware Mounted', 'Live', 'Troubleshoot'];
        if (!in_array($newStatus, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }

        $testedBy = !empty($postData['tested_by']) ? trim($postData['tested_by']) : ($user['full_name'] ?: $user['username']);
        $commDate = ($newStatus === 'Live') ? date('Y-m-d') : null;

        $up = $pdo->prepare("UPDATE nodes SET status = ?, tested_by = COALESCE(NULLIF(?, ''), tested_by), commission_date = COALESCE(?, commission_date) WHERE id = ?");
        $up->execute([$newStatus, $testedBy, $commDate, $targetNodeId]);

        echo json_encode([
            'success'   => true,
            'message'   => "Status updated to {$newStatus}",
            'status'    => $newStatus,
            'tested_by' => $testedBy
        ]);
        exit;
    }

    if ($action === 'update_notes') {
        $newNotes = trim($postData['notes'] ?? '');
        $up = $pdo->prepare("UPDATE nodes SET notes = ? WHERE id = ?");
        $up->execute([$newNotes, $targetNodeId]);

        echo json_encode(['success' => true, 'message' => 'Notes saved successfully']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── FETCH NODE DATA ──
$node = null;
if ($nodeId > 0) {
    $stmt = $pdo->prepare("
        SELECT n.*,
               b.name AS blueprint_name, b.image_filename, b.project_id,
               p.name AS project_name, p.client_name, p.code AS project_code
        FROM nodes n
        JOIN blueprints b ON b.id = n.blueprint_id
        LEFT JOIN projects p ON p.id = b.project_id
        WHERE n.id = ?
    ");
    $stmt->execute([$nodeId]);
    $node = $stmt->fetch();
} elseif ($deviceId && $bpId) {
    $stmt = $pdo->prepare("
        SELECT n.*,
               b.name AS blueprint_name, b.image_filename, b.project_id,
               p.name AS project_name, p.client_name, p.code AS project_code
        FROM nodes n
        JOIN blueprints b ON b.id = n.blueprint_id
        LEFT JOIN projects p ON p.id = b.project_id
        WHERE n.device_id = ? AND n.blueprint_id = ?
    ");
    $stmt->execute([$deviceId, $bpId]);
    $node = $stmt->fetch();
}

// If still not found
if (!$node) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Asset Not Found — SiteDeploy</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=IBM+Plex+Mono:wght@500;700&display=swap" rel="stylesheet">
        <style>
            body { background: #0c0d12; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
            .card { background: #191A23; border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 32px 24px; max-width: 420px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
            .icon { font-size: 3rem; margin-bottom: 12px; }
            h2 { margin: 0 0 8px 0; font-size: 1.4rem; color: #ef4444; }
            p { font-size: 0.88rem; color: #9CA3AF; margin-bottom: 24px; line-height: 1.5; }
            .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #B9FF66; color: #111827; font-weight: 700; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">🔍</div>
            <h2>Device Asset Not Found</h2>
            <p>The scanned QR code does not correspond to an active hardware asset in this deployment database.</p>
            <a href="dashboard.php" class="btn">Go to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch photos for this node
$photoStmt = $pdo->prepare("SELECT id, filename, caption, created_at FROM node_photos WHERE node_id = ? ORDER BY id DESC");
$photoStmt->execute([$node['id']]);
$photos = $photoStmt->fetchAll();

// Fetch connected cable information (if any)
$cableStmt = $pdo->prepare("
    SELECT c.*, 
           fn.device_id AS from_dev, tn.device_id AS to_dev
    FROM cables c
    JOIN nodes fn ON fn.id = c.from_node_id
    JOIN nodes tn ON tn.id = c.to_node_id
    WHERE c.from_node_id = ? OR c.to_node_id = ?
    LIMIT 1
");
$cableStmt->execute([$node['id'], $node['id']]);
$cable = $cableStmt->fetch();

$targetDev = '';
if ($cable) {
    $targetDev = (intval($cable['from_node_id']) === intval($node['id'])) ? $cable['to_dev'] : $cable['from_dev'];
}

function statusBadgeClass($st) {
    switch ($st) {
        case 'Live':              return 'st-live';
        case 'Hardware Mounted':  return 'st-mounted';
        case 'Cable Pulled':      return 'st-pulled';
        case 'Planned':           return 'st-planned';
        case 'Troubleshoot':      return 'st-trouble';
        default:                  return 'st-planned';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($node['device_id']) ?> — Mobile Field Inspector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c0d12;
            --card-bg: #191A23;
            --card-sub: #22232e;
            --border: rgba(255, 255, 255, 0.12);
            --border-hover: rgba(185, 255, 102, 0.4);
            --lime: #B9FF66;
            --lime-hover: #a8ee55;
            --text-main: #f9fafb;
            --text-muted: #9CA3AF;
            --text-sub: #6B7280;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            background: var(--bg);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0 0 40px 0;
            min-height: 100vh;
        }

        /* Top Mobile App Bar */
        .top-app-bar {
            background: rgba(25, 26, 35, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .brand-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #fff;
        }
        .brand-logo-pill {
            background: #facc15;
            color: #000;
            font-weight: 900;
            font-size: 0.72rem;
            padding: 3px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        .brand-text {
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .user-status-pill {
            font-size: 0.72rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            background: rgba(255,255,255,0.08);
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .user-status-pill.logged-in {
            background: rgba(185, 255, 102, 0.15);
            color: var(--lime);
            border: 1px solid rgba(185, 255, 102, 0.3);
        }

        .main-container {
            max-width: 520px;
            margin: 0 auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Breadcrumb banner */
        .project-banner {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .project-banner strong { color: #fff; }

        /* Main Hero Card */
        .hero-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
        }
        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--lime);
        }
        .hero-top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .device-title {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 1.85rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .device-type-badge {
            display: inline-block;
            font-size: 0.75rem;
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            padding: 3px 8px;
            font-weight: 600;
            margin-top: 4px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .st-live { background: rgba(34, 197, 94, 0.18); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); }
        .st-mounted { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .st-pulled { background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); }
        .st-planned { background: rgba(239, 68, 68, 0.18); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .st-trouble { background: rgba(168, 85, 247, 0.18); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4); }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }

        /* 1-Tap Status Update Section */
        .section-box {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-button-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .btn-status-tap {
            background: var(--card-sub);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 12px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            text-align: left;
        }
        .btn-status-tap:active { transform: scale(0.97); }
        .btn-status-tap.active {
            border-color: var(--lime);
            background: rgba(185, 255, 102, 0.12);
            color: var(--lime);
            box-shadow: 0 0 12px rgba(185, 255, 102, 0.25);
        }
        .btn-status-tap:nth-child(5) { grid-column: span 2; }

        /* Hardware & Network Specs */
        .spec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .spec-item {
            background: var(--card-sub);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 8px;
            padding: 10px;
        }
        .spec-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--text-sub);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .spec-val {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.86rem;
            font-weight: 700;
            color: #fff;
            word-break: break-all;
        }

        /* Ping Test Row */
        .ping-test-box {
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
        }
        .ping-info {
            display: flex;
            flex-direction: column;
        }
        .btn-ping-action {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 700;
            font-size: 0.78rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-ping-action:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Photo Evidence Section */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 10px;
        }
        .photo-thumb {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
            position: relative;
            background: #000;
        }
        .photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-thumb-date {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            font-size: 0.6rem;
            color: #fff;
            padding: 2px 4px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-camera-upload {
            background: var(--lime);
            color: #111827;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 800;
            font-size: 0.9rem;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(185, 255, 102, 0.3);
            margin-top: 12px;
        }
        .btn-camera-upload:active { transform: scale(0.98); }

        /* Navigation Buttons */
        .nav-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .btn-nav {
            background: var(--card-sub);
            border: 1px solid var(--border);
            color: #fff;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }
        .btn-nav.primary {
            background: #2563eb;
            border-color: #3b82f6;
        }

        /* Toast notification */
        #toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #111827;
            color: #fff;
            border: 1px solid var(--lime);
            padding: 12px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999;
            pointer-events: none;
        }
        #toast.show { transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="top-app-bar">
        <a href="dashboard.php" class="brand-badge">
            <span class="brand-logo-pill">MEMIS</span>
            <span class="brand-text">SiteDeploy Field</span>
        </a>
        <?php if ($isLoggedIn): ?>
            <span class="user-status-pill logged-in">
                <span>●</span> <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)
            </span>
        <?php else: ?>
            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="user-status-pill">
                🔒 Log In
            </a>
        <?php endif; ?>
    </header>

    <main class="main-container">

        <!-- Project & Blueprint banner -->
        <div class="project-banner">
            <div>
                <strong><?= htmlspecialchars($node['project_name'] ?: 'Site Project') ?></strong>
                <span> · <?= htmlspecialchars($node['blueprint_name']) ?></span>
            </div>
            <span style="font-family:'IBM Plex Mono', monospace; font-size:0.7rem; color:var(--lime);">
                <?= htmlspecialchars($node['project_code'] ?: 'ASSET') ?>
            </span>
        </div>

        <?php if (!$isLoggedIn): ?>
            <div style="background:rgba(234, 179, 8, 0.12); border:1px solid rgba(234, 179, 8, 0.3); border-radius:10px; padding:10px 14px; font-size:0.78rem; color:#fde047; display:flex; align-items:center; justify-content:space-between;">
                <span>ℹ️ Read-Only Mode. Log in to update status or upload site photos.</span>
                <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" style="color:#fff; font-weight:800; text-decoration:underline;">Log In</a>
            </div>
        <?php endif; ?>

        <!-- Hero Card -->
        <div class="hero-card">
            <div class="hero-top-row">
                <div>
                    <div class="device-title"><?= htmlspecialchars($node['device_id']) ?></div>
                    <span class="device-type-badge"><?= htmlspecialchars($node['type']) ?></span>
                </div>
                <div id="liveStatusPill" class="status-pill <?= statusBadgeClass($node['status']) ?>">
                    <span class="pulse-dot"></span>
                    <span id="liveStatusText"><?= htmlspecialchars($node['status']) ?></span>
                </div>
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:10px; display:flex; gap:16px;">
                <span>Tested By: <strong id="valTestedBy" style="color:#fff;"><?= htmlspecialchars($node['tested_by'] ?: '—') ?></strong></span>
                <span>Date: <strong id="valCommissionDate" style="color:#fff;"><?= htmlspecialchars($node['commission_date'] ?: '—') ?></strong></span>
            </div>
        </div>

        <!-- 1-Tap Status Update (when logged in) -->
        <?php if ($isLoggedIn): ?>
            <div class="section-box">
                <div class="section-header">
                    <span class="section-title">⚡ 1-Tap Status Update</span>
                    <span style="font-size:0.7rem; color:var(--text-sub);">Tap to update</span>
                </div>
                <div class="status-button-grid">
                    <button type="button" class="btn-status-tap <?= $node['status'] === 'Planned' ? 'active' : '' ?>" onclick="updateStatus('Planned')">
                        <span>📋</span> Planned
                    </button>
                    <button type="button" class="btn-status-tap <?= $node['status'] === 'Cable Pulled' ? 'active' : '' ?>" onclick="updateStatus('Cable Pulled')">
                        <span>🪢</span> Cable Pulled
                    </button>
                    <button type="button" class="btn-status-tap <?= $node['status'] === 'Hardware Mounted' ? 'active' : '' ?>" onclick="updateStatus('Hardware Mounted')">
                        <span>🔩</span> Mounted
                    </button>
                    <button type="button" class="btn-status-tap <?= $node['status'] === 'Live' ? 'active' : '' ?>" onclick="updateStatus('Live')">
                        <span>🟢</span> Live / Tested
                    </button>
                    <button type="button" class="btn-status-tap <?= $node['status'] === 'Troubleshoot' ? 'active' : '' ?>" onclick="updateStatus('Troubleshoot')">
                        <span>⚠️</span> Troubleshoot Needed
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Network & Physical Hardware Specs -->
        <div class="section-box">
            <div class="section-header">
                <span class="section-title">🔌 Hardware &amp; Network Specs</span>
            </div>
            <div class="spec-grid">
                <div class="spec-item">
                    <div class="spec-label">IP Address</div>
                    <div class="spec-val"><?= htmlspecialchars($node['ip_address'] ?: 'Unassigned') ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">MAC Address</div>
                    <div class="spec-val"><?= htmlspecialchars($node['mac_address'] ?: '—') ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Switch &amp; Port</div>
                    <div class="spec-val"><?= htmlspecialchars(($node['switch_id'] ?: '—') . ($node['port_no'] ? ' : P' . $node['port_no'] : '')) ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Cable Run Label</div>
                    <div class="spec-val"><?= htmlspecialchars($node['cable_label'] ?: ($cable ? $cable['cable_label'] : '—')) ?></div>
                </div>
            </div>

            <?php if ($cable): ?>
                <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:8px; padding:8px 10px; margin-top:10px; font-size:0.75rem; color:var(--text-muted); display:flex; justify-content:space-between;">
                    <span>Terminated to: <strong style="color:#fff;"><?= htmlspecialchars($targetDev) ?></strong></span>
                    <span>Total Cable: <strong style="color:var(--lime);"><?= htmlspecialchars($cable['total_length_meters']) ?>m</strong> (<?= htmlspecialchars($cable['cable_type']) ?>)</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($node['ip_address'])): ?>
                <div class="ping-test-box">
                    <div class="ping-info">
                        <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Ping Test Target</span>
                        <span id="pingResultText" style="font-family:'IBM Plex Mono', monospace; font-size:0.8rem; color:#fff; font-weight:700;">
                            <?= htmlspecialchars($node['ip_address']) ?>
                        </span>
                    </div>
                    <button type="button" id="btnPingNow" class="btn-ping-action" onclick="runLivePing()">
                        <span>⚡ Test Ping</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notes / Remarks -->
        <div class="section-box">
            <div class="section-header">
                <span class="section-title">📝 Field Notes</span>
                <?php if ($isLoggedIn): ?>
                    <button type="button" onclick="saveNotes()" style="background:transparent; border:none; color:var(--lime); font-size:0.75rem; font-weight:700; cursor:pointer;">Save</button>
                <?php endif; ?>
            </div>
            <textarea id="fieldNotesInput" rows="2" <?= !$isLoggedIn ? 'readonly' : '' ?> placeholder="Enter field observations, mounting height, bracket type, or issue notes..." style="width:100%; background:var(--card-sub); border:1px solid var(--border); border-radius:8px; color:#fff; font-size:0.8rem; padding:8px 10px; font-family:inherit; resize:vertical;"><?= htmlspecialchars($node['notes'] ?? '') ?></textarea>
        </div>

        <!-- Site Photo Evidence -->
        <div class="section-box">
            <div class="section-header">
                <span class="section-title">📸 Photo Evidence</span>
                <span style="font-size:0.72rem; color:var(--text-muted);"><?= count($photos) ?> photos</span>
            </div>

            <?php if (empty($photos)): ?>
                <div id="noPhotosMsg" style="font-size:0.78rem; color:var(--text-sub); text-align:center; padding:14px 0;">
                    No on-site verification photos attached yet.
                </div>
            <?php endif; ?>

            <div class="photo-gallery" id="photoGallery">
                <?php foreach ($photos as $p): ?>
                    <div class="photo-thumb" onclick="window.open('uploads/node_photos/<?= htmlspecialchars($p['filename']) ?>', '_blank')">
                        <img src="uploads/node_photos/<?= htmlspecialchars($p['filename']) ?>" alt="<?= htmlspecialchars($p['caption'] ?: $node['device_id']) ?>">
                        <div class="photo-thumb-date"><?= date('d M H:i', strtotime($p['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isLoggedIn): ?>
                <!-- Camera Input directly triggering phone camera -->
                <input type="file" id="cameraPhotoInput" accept="image/*" capture="environment" style="display:none;" onchange="handlePhotoSelected(event)">
                <button type="button" class="btn-camera-upload" onclick="document.getElementById('cameraPhotoInput').click()">
                    <span>📸</span> Snap &amp; Upload Photo Proof
                </button>
            <?php endif; ?>
        </div>

        <!-- Navigation Actions -->
        <div class="nav-actions-grid">
            <a href="map.php?blueprint_id=<?= $node['blueprint_id'] ?>&select=<?= urlencode($node['device_id']) ?>" class="btn-nav primary">
                <span>🗺️</span> View on Blueprint
            </a>
            <a href="batch_qr.php?blueprint_id=<?= $node['blueprint_id'] ?>" class="btn-nav">
                <span>🖨️</span> All QR Stickers
            </a>
        </div>

    </main>

    <div id="toast">
        <span id="toastIcon">✓</span>
        <span id="toastMsg">Notification</span>
    </div>

    <script>
        const NODE_ID = <?= intval($node['id']) ?>;
        const BP_ID = <?= intval($node['blueprint_id']) ?>;
        const DEVICE_ID = <?= json_encode($node['device_id']) ?>;
        const TARGET_IP = <?= json_encode($node['ip_address']) ?>;

        function showToast(msg, icon = '✓') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            document.getElementById('toastIcon').textContent = icon;
            toast.classList.add('show');
            if (navigator.vibrate) navigator.vibrate(50);
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        async function updateStatus(newStatus) {
            try {
                const res = await fetch('quick_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', node_id: NODE_ID, status: newStatus })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, '✓');

                    // Update live badge styling
                    const pill = document.getElementById('liveStatusPill');
                    const text = document.getElementById('liveStatusText');
                    text.textContent = newStatus;
                    pill.className = 'status-pill ' + getStatusClass(newStatus);

                    if (json.tested_by) {
                        document.getElementById('valTestedBy').textContent = json.tested_by;
                    }
                    if (newStatus === 'Live') {
                        document.getElementById('valCommissionDate').textContent = new Date().toISOString().slice(0, 10);
                    }

                    // Update active button
                    document.querySelectorAll('.btn-status-tap').forEach(btn => {
                        btn.classList.toggle('active', btn.textContent.includes(newStatus) || (newStatus === 'Hardware Mounted' && btn.textContent.includes('Mounted')));
                    });
                } else {
                    showToast(json.error || 'Update failed', '⚠️');
                }
            } catch (e) {
                showToast('Network error updating status', '⚠️');
            }
        }

        function getStatusClass(st) {
            switch(st) {
                case 'Live': return 'st-live';
                case 'Hardware Mounted': return 'st-mounted';
                case 'Cable Pulled': return 'st-pulled';
                case 'Planned': return 'st-planned';
                case 'Troubleshoot': return 'st-trouble';
                default: return 'st-planned';
            }
        }

        async function saveNotes() {
            const notes = document.getElementById('fieldNotesInput').value.trim();
            try {
                const res = await fetch('quick_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_notes', node_id: NODE_ID, notes: notes })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Field notes saved!', '✓');
                } else {
                    showToast(json.error || 'Failed to save notes', '⚠️');
                }
            } catch (e) {
                showToast('Network error saving notes', '⚠️');
            }
        }

        async function runLivePing() {
            const btn = document.getElementById('btnPingNow');
            const resultText = document.getElementById('pingResultText');
            if (!TARGET_IP) return;

            btn.disabled = true;
            btn.innerHTML = '<span>⏳ Pinging...</span>';
            resultText.textContent = `Probing ${TARGET_IP}...`;

            try {
                const res = await fetch(`api/ping_api.php?action=ping_node&ip=${encodeURIComponent(TARGET_IP)}&blueprint_id=${BP_ID}&device_id=${encodeURIComponent(DEVICE_ID)}`);
                const json = await res.json();

                if (json.success) {
                    if (json.status === 'online') {
                        resultText.innerHTML = `<span style="color:#4ade80;">● ONLINE</span> (${json.latency_ms}ms · ${json.method})`;
                        showToast(`Device Online! ${json.latency_ms}ms`, '🟢');
                    } else {
                        resultText.innerHTML = `<span style="color:#f87171;">● OFFLINE</span> (${json.message || 'No response'})`;
                        showToast('Device is Offline', '🔴');
                    }
                } else {
                    resultText.innerHTML = `<span style="color:#f87171;">Error: ${json.error}</span>`;
                }
            } catch (e) {
                resultText.textContent = 'Ping request failed';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>⚡ Test Ping</span>';
            }
        }

        async function handlePhotoSelected(event) {
            const file = event.target.files[0];
            if (!file) return;

            const caption = prompt('Add optional note/caption for this photo:', 'Mounted and terminated');
            if (caption === null) return; // user cancelled

            const formData = new FormData();
            formData.append('blueprint_id', BP_ID);
            formData.append('device_id', DEVICE_ID);
            formData.append('caption', caption);
            formData.append('photo', file);

            showToast('Uploading site photo...', '⏳');

            try {
                const res = await fetch('api/upload_node_photo.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Photo uploaded successfully!', '✓');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(json.error || 'Upload failed', '⚠️');
                }
            } catch (e) {
                showToast('Network error uploading photo', '⚠️');
            }
        }
    </script>
</body>
</html>
