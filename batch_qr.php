<?php
/**
 * batch_qr.php
 * Batch QR Code Sticker Sheet Generator
 * 
 * Generates an A4 or thermal label print-ready sticker sheet for all devices
 * on a blueprint. Technicians print this before going on-site to label equipment.
 */

require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/db.php';

requireLogin();
$user = getCurrentUser();

$blueprintId = intval($_GET['blueprint_id'] ?? 0);
if (!$blueprintId) {
    header('Location: dashboard.php');
    exit;
}

if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    die('<h1>403 — Access Denied</h1><p>You do not have access to this blueprint.</p>');
}

// Fetch blueprint & project details
$bpStmt = $pdo->prepare("
    SELECT b.*, p.name AS project_name, p.client_name, p.code AS project_code
    FROM blueprints b
    LEFT JOIN projects p ON p.id = b.project_id
    WHERE b.id = ?
");
$bpStmt->execute([$blueprintId]);
$blueprint = $bpStmt->fetch();

if (!$blueprint) {
    die('Blueprint not found');
}

// Fetch all nodes ordered by device_id
$nodeStmt = $pdo->prepare("
    SELECT * FROM nodes 
    WHERE blueprint_id = ? 
    ORDER BY device_id ASC
");
$nodeStmt->execute([$blueprintId]);
$nodes = $nodeStmt->fetchAll();

// Fetch all cables to map cable labels
$cableStmt = $pdo->prepare("
    SELECT from_node_id, to_node_id, cable_label, total_length_meters, cable_type 
    FROM cables 
    WHERE blueprint_id = ?
");
$cableStmt->execute([$blueprintId]);
$allCables = $cableStmt->fetchAll();

$cableMap = [];
foreach ($allCables as $c) {
    $cableMap[$c['from_node_id']] = $c;
    $cableMap[$c['to_node_id']] = $c;
}

// Counts for filters
$cameraCount = 0;
$rackCount = 0;
foreach ($nodes as $n) {
    $t = strtolower($n['type'] ?? '');
    if (strpos($t, 'camera') !== false || strpos($t, 'cctv') !== false || strpos($t, 'dome') !== false || strpos($t, 'bullet') !== false || strpos($t, 'ptz') !== false) {
        $cameraCount++;
    } elseif (strpos($t, 'rack') !== false || strpos($t, 'switch') !== false || strpos($t, 'nvr') !== false || strpos($t, 'server') !== false) {
        $rackCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch QR Stickers — <?= htmlspecialchars($blueprint['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=IBM+Plex+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="assets/js/qrcode.min.js"></script>
    <style>
        :root {
            --bg: #0c0d12;
            --bar-bg: #191A23;
            --border: rgba(255, 255, 255, 0.15);
            --lime: #B9FF66;
            --text: #f9fafb;
            --text-muted: #9CA3AF;
        }

        * { box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* ── SCREEN CONTROLS BAR (Hidden during Print) ── */
        .controls-bar {
            background: var(--bar-bg);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        .header-title-box h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-title-box p {
            margin: 3px 0 0 0;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-btn.active {
            background: var(--lime);
            color: #111827;
            border-color: var(--lime);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2563eb;
            color: #fff;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background 0.15s;
        }
        .btn-action.btn-lime {
            background: var(--lime);
            color: #111827;
        }
        .btn-action.btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: #fff;
        }

        /* ── STICKER SHEET CONTAINER ── */
        .sheet-wrapper {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 20px;
        }

        .stickers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        /* ── INDUSTRIAL STICKER LABEL ── */
        .sticker-card {
            background: #ffffff;
            color: #000000;
            border: 2px solid #111827;
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
            break-inside: avoid;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            min-height: 145px;
            position: relative;
        }

        .sticker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1.5px solid #111827;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .sticker-brand {
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .sticker-brand-tag {
            background: #000;
            color: #fff;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 0.6rem;
        }
        .sticker-project {
            font-size: 0.62rem;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            max-width: 130px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sticker-body {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        .sticker-qr-box {
            width: 84px;
            height: 84px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 2px;
            flex-shrink: 0;
        }
        .sticker-qr-box canvas,
        .sticker-qr-box img {
            width: 80px !important;
            height: 80px !important;
            display: block;
        }

        .sticker-meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
            flex: 1;
        }
        .sticker-device-id {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 1.25rem;
            font-weight: 800;
            color: #000000;
            line-height: 1;
            margin-bottom: 2px;
            letter-spacing: -0.5px;
        }
        .sticker-type {
            font-size: 0.68rem;
            font-weight: 700;
            color: #4b5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sticker-row {
            font-size: 0.65rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sticker-row strong {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.68rem;
        }

        .sticker-footer {
            border-top: 1px dashed #9ca3af;
            margin-top: 6px;
            padding-top: 4px;
            font-size: 0.55rem;
            font-weight: 700;
            color: #6b7280;
            text-align: center;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* ── PRINT MEDIA RULES ── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .controls-bar {
                display: none !important;
            }
            .sheet-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .stickers-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 6mm !important;
            }
            .sticker-card {
                border: 1.5px solid #000 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <!-- Controls Bar -->
    <header class="controls-bar">
        <div class="header-title-box">
            <h1>
                <span>🏷️ Batch QR Sticker Sheet</span>
                <span style="font-size:0.75rem; background:rgba(185,255,102,0.15); color:var(--lime); padding:2px 8px; border-radius:12px; font-weight:700;">
                    <?= count($nodes) ?> Devices
                </span>
            </h1>
            <p>
                <strong><?= htmlspecialchars($blueprint['project_name'] ?: 'Project') ?></strong> · 
                <?= htmlspecialchars($blueprint['name']) ?> (<?= htmlspecialchars($blueprint['project_code'] ?: 'ASSET') ?>)
            </p>
        </div>

        <!-- Filter buttons -->
        <div class="filter-group">
            <button type="button" class="filter-btn active" onclick="filterStickers('all')">All (<?= count($nodes) ?>)</button>
            <button type="button" class="filter-btn" onclick="filterStickers('camera')">Cameras (<?= $cameraCount ?>)</button>
            <button type="button" class="filter-btn" onclick="filterStickers('rack')">Racks / Switches (<?= $rackCount ?>)</button>
        </div>

        <!-- Actions -->
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="map.php?blueprint_id=<?= $blueprintId ?>" class="btn-action btn-outline">
                <span>🗺️ Back to Map</span>
            </a>
            <button type="button" class="btn-action btn-lime" onclick="window.print()">
                <span>🖨️ Print Stickers (Ctrl+P)</span>
            </button>
        </div>
    </header>

    <div class="sheet-wrapper">
        <div class="stickers-grid" id="stickersGrid">
            <?php foreach ($nodes as $n): 
                $t = strtolower($n['type'] ?? '');
                $category = 'other';
                if (strpos($t, 'camera') !== false || strpos($t, 'cctv') !== false || strpos($t, 'dome') !== false || strpos($t, 'bullet') !== false || strpos($t, 'ptz') !== false) {
                    $category = 'camera';
                } elseif (strpos($t, 'rack') !== false || strpos($t, 'switch') !== false || strpos($t, 'nvr') !== false || strpos($t, 'server') !== false) {
                    $category = 'rack';
                }

                $cLink = $cableMap[$n['id']] ?? null;
                $cableTag = $n['cable_label'] ?: ($cLink ? $cLink['cable_label'] : '—');
                $switchPort = ($n['switch_id'] ?: '—') . ($n['port_no'] ? ' : P' . $n['port_no'] : '');
            ?>
                <div class="sticker-card" data-category="<?= $category ?>">
                    <div class="sticker-header">
                        <div class="sticker-brand">
                            <span class="sticker-brand-tag">MEMIS</span>
                            <span>ASSET TAG</span>
                        </div>
                        <div class="sticker-project" title="<?= htmlspecialchars($blueprint['name']) ?>">
                            <?= htmlspecialchars($blueprint['project_code'] ?: $blueprint['name']) ?>
                        </div>
                    </div>

                    <div class="sticker-body">
                        <!-- QR Code Canvas container -->
                        <div class="sticker-qr-box" id="qr_box_<?= $n['id'] ?>" data-node-id="<?= $n['id'] ?>" data-device-id="<?= htmlspecialchars($n['device_id']) ?>"></div>

                        <div class="sticker-meta">
                            <div class="sticker-device-id"><?= htmlspecialchars($n['device_id']) ?></div>
                            <div class="sticker-type"><?= htmlspecialchars($n['type']) ?></div>
                            <div class="sticker-row">IP: <strong><?= htmlspecialchars($n['ip_address'] ?: 'DHCP') ?></strong></div>
                            <div class="sticker-row">Port: <strong><?= htmlspecialchars($switchPort) ?></strong></div>
                            <div class="sticker-row">Cable: <strong><?= htmlspecialchars($cableTag) ?></strong></div>
                        </div>
                    </div>

                    <div class="sticker-footer">
                        Scan with phone camera to inspect on-site
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        const BLUEPRINT_ID = <?= intval($blueprintId) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            generateAllQrCodes();
        });

        function generateAllQrCodes() {
            const baseUrl = `${window.location.origin}${window.location.pathname.replace(/batch_qr\.php.*$/, '')}quick_scan.php`;

            document.querySelectorAll('.sticker-qr-box').forEach(box => {
                const nodeId = box.dataset.nodeId;
                const devId = box.dataset.deviceId;
                const scanUrl = `${baseUrl}?id=${nodeId}&dev=${encodeURIComponent(devId)}&bp=${BLUEPRINT_ID}`;

                box.innerHTML = '';
                new QRCode(box, {
                    text: scanUrl,
                    width: 80,
                    height: 80,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            });
        }

        function filterStickers(category) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.toggle('active', b.getAttribute('onclick').includes(`'${category}'`));
            });

            document.querySelectorAll('.sticker-card').forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
