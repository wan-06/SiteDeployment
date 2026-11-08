<?php
/**
 * MEMIS SDN. BHD.
 * Testing & Commissioning (T&C) and As-Built Handover Report
 */
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/db.php';
requireLogin();
$user = getCurrentUser();

$blueprintId = intval($_GET['id'] ?? 0);
if (!$blueprintId) { header('Location: dashboard.php'); exit; }

if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo '<h1>403 — Access Denied</h1><p>You do not have access to this blueprint report.</p>';
    exit;
}

// Fetch blueprint details
$bpStmt = $pdo->prepare("
    SELECT b.*, u.full_name AS owner_name, u.email AS owner_email,
           p.id AS project_id, p.name AS project_name, p.client_name, p.code AS project_code, p.status AS project_status
    FROM blueprints b 
    JOIN users u ON u.id = b.uploaded_by 
    LEFT JOIN projects p ON p.id = b.project_id
    WHERE b.id = ?
");
$bpStmt->execute([$blueprintId]);
$blueprint = $bpStmt->fetch();

if (!$blueprint) { header('Location: dashboard.php'); exit; }

// Fetch nodes
$nodesStmt = $pdo->prepare("
    SELECT n.*, u.full_name AS created_by_name 
    FROM nodes n 
    LEFT JOIN users u ON u.id = n.created_by 
    WHERE n.blueprint_id = ? 
    ORDER BY n.device_id ASC
");
$nodesStmt->execute([$blueprintId]);
$nodes = $nodesStmt->fetchAll();

// Fetch site verification photos for this blueprint
$photoStmt = $pdo->prepare("
    SELECT p.*, u.full_name AS uploader_name
    FROM node_photos p
    LEFT JOIN users u ON u.id = p.uploaded_by
    WHERE p.blueprint_id = ?
    ORDER BY p.device_id ASC, p.created_at ASC
");
$photoStmt->execute([$blueprintId]);
$photos = $photoStmt->fetchAll();

// Map node details for photos
$nodeMap = [];
foreach ($nodes as $n) {
    $nodeMap[$n['device_id']] = $n;
}

// Statistics
$totalNodes = count($nodes);
$liveNodes = count(array_filter($nodes, fn($n) => $n['status'] === 'Live'));
$mountedNodes = count(array_filter($nodes, fn($n) => $n['status'] === 'Hardware Mounted'));
$cableNodes = count(array_filter($nodes, fn($n) => $n['status'] === 'Cable Pulled'));
$plannedNodes = count(array_filter($nodes, fn($n) => $n['status'] === 'Planned'));
$troubleNodes = count(array_filter($nodes, fn($n) => $n['status'] === 'Troubleshoot'));

$reportDate = date('d F Y');
$reportRef = 'MEMIS-TC-' . date('Y') . '-' . str_pad($blueprintId, 4, '0', STR_PAD_LEFT);
$imagePath = 'uploads/blueprints/' . htmlspecialchars($blueprint['image_filename']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T&C As-Built Handover Report — <?= htmlspecialchars($blueprint['name']) ?> — MEMIS Sdn. Bhd.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --dark: #191A23;
            --lime: #B9FF66;
            --text: #191A23;
            --muted: #555869;
            --border: #D1D5DB;
            --bg-page: #F4F5F7;
            --surface: #FFFFFF;
        }

        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background: var(--bg-page);
            color: var(--text);
            line-height: 1.5;
            padding-bottom: 60px;
        }

        /* ── TOP ACTION BAR (Hidden when printing) ── */
        .top-action-bar {
            background: var(--dark);
            color: #fff;
            padding: 14px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .bar-left { display: flex; align-items: center; gap: 16px; }
        .back-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }
        .bar-title { font-weight: 700; font-size: 0.95rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 100px;
            border: none;
            font-family: inherit;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-lime { background: var(--lime); color: var(--dark); }
        .btn-lime:hover { background: #a8f050; transform: translateY(-1px); }

        /* ── REPORT DOCUMENT SHEET ── */
        .report-sheet {
            max-width: 1100px;
            margin: 32px auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 48px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        /* ── LETTERHEAD HEADER ── */
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 3px solid var(--dark);
            margin-bottom: 32px;
        }
        .company-brand h1 {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--dark);
        }
        .company-brand .reg-no {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }
        .company-brand .tagline {
            font-size: 0.82rem;
            background: var(--lime);
            color: var(--dark);
            font-weight: 700;
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 6px;
        }
        .company-contact {
            text-align: right;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.45;
        }

        /* ── REPORT TITLE CARD ── */
        .doc-title-card {
            background: #FAFAFA;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 32px;
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 20px;
        }
        .doc-title-card h2 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            margin-bottom: 8px;
        }
        .doc-meta-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 4px 12px;
            font-size: 0.84rem;
        }
        .doc-meta-label { font-weight: 600; color: var(--muted); }
        .doc-meta-value { font-weight: 500; }

        .stat-summary-boxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .stat-box {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .stat-box .s-val { font-size: 1.4rem; font-weight: 800; line-height: 1.1; }
        .stat-box .s-lbl { font-size: 0.68rem; text-transform: uppercase; color: var(--muted); font-weight: 600; }

        /* ── SECTION HEADING ── */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 16px;
        }
        .section-pill {
            background: var(--dark);
            color: #fff;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title { font-size: 1.1rem; font-weight: 700; }

        /* ── AS-BUILT DRAWING VISUAL ── */
        .floorplan-viewer {
            position: relative;
            background: #151720;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 32px;
        }
        .floorplan-viewer img {
            width: 100%;
            height: auto;
            display: block;
        }
        .floorplan-pin {
            position: absolute;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            border: 2px solid #fff;
        }
        .floorplan-pin.live { background: #22c55e; }
        .floorplan-pin.hardware-mounted { background: #f59e0b; }
        .floorplan-pin.cable-pulled { background: #3b82f6; }
        .floorplan-pin.planned { background: #ef4444; }
        .floorplan-pin.troubleshoot { background: #a855f7; }

        .pin-index-label {
            position: absolute;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.85);
            color: #fff;
            font-size: 0.6rem;
            padding: 1px 5px;
            border-radius: 4px;
            white-space: nowrap;
            font-family: 'IBM Plex Mono', monospace;
        }

        /* ── T&C SCHEDULE TABLE ── */
        .schedule-table-wrap {
            overflow-x: auto;
            margin-bottom: 36px;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        table.tc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        table.tc-table th {
            background: #F3F4F6;
            color: var(--dark);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-bottom: 2px solid var(--border);
            text-align: left;
            font-size: 0.72rem;
        }
        table.tc-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: middle;
        }
        table.tc-table tbody tr:nth-child(even) { background: #FAFAFA; }
        table.tc-table tbody tr:hover { background: #F3F4F6; }

        .tag-badge {
            font-family: 'IBM Plex Mono', monospace;
            font-weight: 700;
            background: #E5E7EB;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        .mono-text { font-family: 'IBM Plex Mono', monospace; font-size: 0.74rem; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pill.live { background: #DCFCE7; color: #166534; }
        .status-pill.hardware-mounted { background: #FEF3C7; color: #92400E; }
        .status-pill.cable-pulled { background: #DBEAFE; color: #1E40AF; }
        .status-pill.planned { background: #FEE2E2; color: #991B1B; }
        .status-pill.troubleshoot { background: #F3E8FF; color: #6B21A8; }

        /* ── SIGNATURE / SIGN-OFF BLOCK ── */
        .signoff-section {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 2px solid var(--border);
            page-break-inside: avoid;
        }
        .signoff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .sign-box {
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            background: #FAFAFA;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
        }
        .sign-box-role {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .sign-line {
            border-bottom: 1px dashed #9CA3AF;
            margin-top: 50px;
            margin-bottom: 8px;
        }
        .sign-meta-field {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 2px;
        }

        /* ── SECTION 3: SITE PHOTO EVIDENCE GALLERY ── */
        .photo-evidence-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 16px;
        }
        @media (max-width: 900px) {
            .photo-evidence-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .photo-evidence-grid { grid-template-columns: 1fr; }
        }
        .photo-evidence-card {
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #FAFAFA;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            break-inside: avoid;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .photo-evidence-thumb {
            position: relative;
            width: 100%;
            height: 190px;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .photo-evidence-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .photo-card-stamp {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(185, 255, 102, 0.95);
            color: #191A23;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .photo-evidence-body {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }
        .photo-evidence-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .photo-evidence-caption {
            font-size: 0.8rem;
            color: var(--text);
            font-weight: 500;
            line-height: 1.35;
            margin-top: 2px;
        }
        .photo-evidence-footer {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: auto;
            padding-top: 6px;
            border-top: 1px solid rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .empty-photos-box {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 36px;
            text-align: center;
            color: var(--muted);
            margin-top: 16px;
            background: #FAFAFA;
        }

        /* ── PRINT STYLES ── */
        @media print {
            body { background: #fff; padding: 0; }
            .top-action-bar { display: none !important; }
            .report-sheet {
                border: none;
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .page-break { page-break-before: always; }
            .floorplan-viewer { border: 1px solid #000; }
            table.tc-table th { background: #eee !important; -webkit-print-color-adjust: exact; }
            .status-pill { -webkit-print-color-adjust: exact; }
            .section-pill { -webkit-print-color-adjust: exact; }
            .photo-evidence-card { break-inside: avoid; page-break-inside: avoid; border: 1px solid #ccc; }
            .photo-card-stamp { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- ═══ TOP ACTIONS (Screen Only) ═══ -->
    <div class="top-action-bar">
        <div class="bar-left">
            <a href="map.php?id=<?= $blueprintId ?>" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Interactive Map
            </a>
            <span style="color:rgba(255,255,255,0.3)">|</span>
            <span class="bar-title">T&amp;C &amp; As-Built Handover Documentation</span>
        </div>
        <button class="btn btn-lime" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save as PDF
        </button>
    </div>

    <!-- ═══ REPORT DOCUMENT SHEET ═══ -->
    <div class="report-sheet">

        <!-- ── COMPANY LETTERHEAD ── -->
        <header class="company-header">
            <div class="company-brand">
                <h1>MEMIS SDN. BHD.</h1>
                <div class="reg-no">Company Reg. No: 201801016605 (1278621-H)</div>
                <div class="tagline">CIDB G5 REGISTERED &bull; SECURITY INFRASTRUCTURE &bull; ELV &amp; NETWORKING</div>
            </div>
            <div class="company-contact">
                <div><strong>HQ &amp; Engineering Center:</strong></div>
                <div>39A, 41 &amp; 41A, Jalan Danau 1, Taman Desa Jaya</div>
                <div>81100 Johor Bahru, Johor Darul Ta'zim, Malaysia</div>
                <div>Web: <strong>www.memis.com.my</strong></div>
            </div>
        </header>

        <!-- ── DOCUMENT TITLE & METADATA ── -->
        <div class="doc-title-card">
            <div>
                <h2>TESTING &amp; COMMISSIONING (T&amp;C) &amp; AS-BUILT HANDOVER REPORT</h2>
                <div class="doc-meta-grid">
                    <?php if (!empty($blueprint['client_name'])): ?>
                    <span class="doc-meta-label">Client / Owner:</span>
                    <span class="doc-meta-value"><strong><?= htmlspecialchars($blueprint['client_name']) ?></strong></span>

                    <span class="doc-meta-label">Project Code:</span>
                    <span class="doc-meta-value"><span class="mono-text" style="font-weight:700; background:#E5E7EB; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($blueprint['project_code']) ?></span> <?= htmlspecialchars($blueprint['project_name']) ?></span>
                    <?php endif; ?>

                    <span class="doc-meta-label">Site / Drawing:</span>
                    <span class="doc-meta-value"><strong><?= htmlspecialchars($blueprint['name']) ?></strong></span>

                    <span class="doc-meta-label">Report Ref:</span>
                    <span class="doc-meta-value mono-text"><?= $reportRef ?></span>

                    <span class="doc-meta-label">Inspection Date:</span>
                    <span class="doc-meta-value"><?= $reportDate ?></span>

                    <span class="doc-meta-label">Lead Engineer:</span>
                    <span class="doc-meta-value"><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
                </div>
            </div>
            <div class="stat-summary-boxes">
                <div class="stat-box">
                    <div class="s-val" style="color:#111;"><?= $totalNodes ?></div>
                    <div class="s-lbl">Total Devices</div>
                </div>
                <div class="stat-box">
                    <div class="s-val" style="color:#22c55e;"><?= $liveNodes ?></div>
                    <div class="s-lbl">Live &amp; Tested</div>
                </div>
                <div class="stat-box">
                    <div class="s-val" style="color:#8b5cf6;"><?= count($photos) ?></div>
                    <div class="s-lbl">Site Photos</div>
                </div>
                <div class="stat-box">
                    <div class="s-val" style="color:#f59e0b;"><?= $mountedNodes + $cableNodes + $plannedNodes ?></div>
                    <div class="s-lbl">Pending / WIP</div>
                </div>
            </div>
        </div>

        <!-- ── SECTION 1: AS-BUILT DRAWING ── -->
        <div class="section-heading">
            <span class="section-pill">Section 1</span>
            <span class="section-title">As-Built Floorplan &amp; Camera Coverage Overlay</span>
        </div>
        <div class="floorplan-viewer">
            <img src="<?= $imagePath ?>" alt="Floorplan Drawing">

            <!-- SVG Camera Field-of-View (FoV) Cones -->
            <?php
            $vw = intval($blueprint['image_width']) ?: 1600;
            $vh = intval($blueprint['image_height']) ?: 1000;
            ?>
            <svg class="report-fov-svg" viewBox="0 0 <?= $vw ?> <?= $vh ?>" style="position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:2;">
                <?php
                foreach ($nodes as $n):
                    if (empty($n['fov_enabled'])) continue;
                    $cx = (floatval($n['pos_x']) / 100) * $vw;
                    $cy = (floatval($n['pos_y']) / 100) * $vh;
                    $dist = !empty($n['fov_distance']) ? intval($n['fov_distance']) : 15;
                    $r = ($dist / 100) * $vw;
                    $dir = !empty($n['fov_direction']) ? intval($n['fov_direction']) : 0;
                    $angle = !empty($n['fov_angle']) ? intval($n['fov_angle']) : 90;

                    $status = $n['status'];
                    $fill = 'rgba(34, 197, 94, 0.22)';
                    $stroke = 'rgba(34, 197, 94, 0.85)';
                    if ($status === 'Hardware Mounted') { $fill = 'rgba(245, 158, 11, 0.22)'; $stroke = 'rgba(245, 158, 11, 0.85)'; }
                    elseif ($status === 'Cable Pulled') { $fill = 'rgba(59, 130, 246, 0.22)'; $stroke = 'rgba(59, 130, 246, 0.85)'; }
                    elseif ($status === 'Planned') { $fill = 'rgba(239, 68, 68, 0.22)'; $stroke = 'rgba(239, 68, 68, 0.85)'; }
                    elseif ($status === 'Troubleshoot') { $fill = 'rgba(168, 85, 247, 0.22)'; $stroke = 'rgba(168, 85, 247, 0.85)'; }

                    if ($angle >= 360): ?>
                        <circle cx="<?= round($cx, 1) ?>" cy="<?= round($cy, 1) ?>" r="<?= round($r, 1) ?>" fill="<?= $fill ?>" stroke="<?= $stroke ?>" stroke-width="1.5" />
                    <?php else: 
                        $rad1 = ($dir - ($angle / 2)) * (M_PI / 180);
                        $rad2 = ($dir + ($angle / 2)) * (M_PI / 180);
                        $radM = $dir * (M_PI / 180);

                        $x1 = $cx + $r * sin($rad1);
                        $y1 = $cy - $r * cos($rad1);
                        $x2 = $cx + $r * sin($rad2);
                        $y2 = $cy - $r * cos($rad2);
                        $xm = $cx + $r * sin($radM);
                        $ym = $cy - $r * cos($radM);

                        $largeArc = ($angle > 180) ? 1 : 0;
                        $d = sprintf("M %.1f %.1f L %.1f %.1f A %.1f %.1f 0 %d 1 %.1f %.1f Z", $cx, $cy, $x1, $y1, $r, $r, $largeArc, $x2, $y2);
                    ?>
                        <path d="<?= $d ?>" fill="<?= $fill ?>" stroke="<?= $stroke ?>" stroke-width="1.5" />
                        <line x1="<?= round($cx, 1) ?>" y1="<?= round($cy, 1) ?>" x2="<?= round($xm, 1) ?>" y2="<?= round($ym, 1) ?>" stroke="<?= $stroke ?>" stroke-width="1" stroke-dasharray="3 2" />
                    <?php endif;
                endforeach; ?>
            </svg>

            <?php 
            $pinIdx = 1;
            foreach ($nodes as $n): 
                $statusClass = strtolower(str_replace(' ', '-', $n['status']));
            ?>
                <div class="floorplan-pin <?= $statusClass ?>" style="left: <?= floatval($n['pos_x']) ?>%; top: <?= floatval($n['pos_y']) ?>%;" title="<?= htmlspecialchars($n['device_id']) ?>">
                    <?= $pinIdx ?>
                    <span class="pin-index-label"><?= htmlspecialchars($n['device_id']) ?></span>
                </div>
            <?php 
                $pinIdx++;
            endforeach; 
            ?>
        </div>

        <!-- ── SECTION 2: T&C EQUIPMENT SCHEDULE ── -->
        <div class="section-heading page-break">
            <span class="section-pill">Section 2</span>
            <span class="section-title">ELV Equipment Schedule &amp; Commissioning Log</span>
        </div>
        <div class="schedule-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Device Tag</th>
                        <th>Category / Model</th>
                        <th>Serial Number</th>
                        <th>IP Address / MAC</th>
                        <th>Switch / Port</th>
                        <th>Cable Tag</th>
                        <th>Status</th>
                        <th>Tested By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nodes)): ?>
                        <tr><td colspan="10" style="text-align:center; padding:30px; color:#6B7280;">No equipment recorded on this drawing yet.</td></tr>
                    <?php else: ?>
                        <?php 
                        $rowIdx = 1;
                        foreach ($nodes as $n): 
                            $statusClass = strtolower(str_replace(' ', '-', $n['status']));
                        ?>
                            <tr>
                                <td style="font-weight:700; color:#6B7280; text-align:center;"><?= $rowIdx ?></td>
                                <td>
                                    <span class="tag-badge"><?= htmlspecialchars($n['device_id']) ?></span>
                                    <?php if (!empty($n['notes'])): ?>
                                        <div style="font-size:0.68rem; color:#6B7280; margin-top:3px; max-width:140px; line-height:1.2;"><?= htmlspecialchars($n['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($n['type']) ?></strong></td>
                                <td class="mono-text"><?= htmlspecialchars($n['serial_number'] ?: '—') ?></td>
                                <td>
                                    <div class="mono-text" style="font-weight:600;"><?= htmlspecialchars($n['ip_address'] ?: '—') ?></div>
                                    <div class="mono-text" style="color:#6B7280; font-size:0.68rem;"><?= htmlspecialchars($n['mac_address'] ?: '—') ?></div>
                                </td>
                                <td>
                                    <?php if ($n['switch_id'] || $n['port_no']): ?>
                                        <div style="font-weight:600;"><?= htmlspecialchars($n['switch_id'] ?: '—') ?></div>
                                        <div class="mono-text" style="color:#6B7280; font-size:0.68rem;"><?= htmlspecialchars($n['port_no'] ?: '—') ?></div>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono-text"><?= htmlspecialchars($n['cable_label'] ?: '—') ?></td>
                                <td>
                                    <span class="status-pill <?= $statusClass ?>">
                                        <?= htmlspecialchars($n['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($n['tested_by'] ?: '—') ?></td>
                                <td class="mono-text" style="font-size:0.72rem;"><?= htmlspecialchars($n['commission_date'] ?: '—') ?></td>
                            </tr>
                        <?php 
                            $rowIdx++;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── SECTION 3: AS-BUILT SITE PHOTO EVIDENCE & INSTALLATION LOG ── -->
        <div class="section-heading page-break">
            <span class="section-pill" style="background:#8b5cf6;">Section 3</span>
            <span class="section-title">As-Built Site Photo Evidence &amp; Visual Verification Log</span>
        </div>

        <?php if (empty($photos)): ?>
            <div class="empty-photos-box">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px; opacity:0.6;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <div style="font-weight:700; font-size:1rem; color:var(--dark);">No Site Verification Photos Attached</div>
                <div style="font-size:0.85rem; margin-top:4px;">Technicians can attach on-site visual evidence by tapping "Take Photo" or "Upload File" in the blueprint map node inspector.</div>
            </div>
        <?php else: ?>
            <div class="photo-evidence-grid">
                <?php foreach ($photos as $photo): 
                    $pDev = $photo['device_id'];
                    $matchingNode = $nodeMap[$pDev] ?? null;
                    $nodeType = $matchingNode['type'] ?? 'ELV Hardware';
                    $nodeStatus = $matchingNode['status'] ?? 'Verified';
                    $statusClass = strtolower(str_replace(' ', '-', $nodeStatus));
                    $photoSrc = 'uploads/nodes/' . htmlspecialchars($photo['filename']);
                ?>
                    <div class="photo-evidence-card">
                        <div class="photo-evidence-thumb">
                            <img src="<?= $photoSrc ?>" alt="<?= htmlspecialchars($photo['caption'] ?: $pDev) ?>" loading="lazy">
                            <span class="photo-card-stamp">✓ Live Verified</span>
                        </div>
                        <div class="photo-evidence-body">
                            <div class="photo-evidence-head">
                                <div>
                                    <span class="tag-badge" style="font-size:0.82rem; font-weight:700;"><?= htmlspecialchars($pDev) ?></span>
                                    <span style="font-size:0.75rem; color:var(--muted); margin-left:4px;"><?= htmlspecialchars($nodeType) ?></span>
                                </div>
                                <span class="status-pill <?= $statusClass ?>" style="font-size:0.65rem; padding:2px 8px;">
                                    <?= htmlspecialchars($nodeStatus) ?>
                                </span>
                            </div>

                            <?php if (!empty($photo['caption'])): ?>
                                <div class="photo-evidence-caption">
                                    <?= htmlspecialchars($photo['caption']) ?>
                                </div>
                            <?php else: ?>
                                <div class="photo-evidence-caption" style="color:var(--muted); font-style:italic;">
                                    Physical installation verification &amp; conduit tagging
                                </div>
                            <?php endif; ?>

                            <div class="photo-evidence-footer">
                                <span>Tag: <strong class="mono-text"><?= htmlspecialchars($matchingNode['cable_label'] ?: $pDev) ?></strong></span>
                                <span><?= date('d M Y, H:i', strtotime($photo['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ── SECTION 4: FORMAL SIGN-OFF BLOCK ── -->
        <div class="signoff-section">
            <div style="margin-bottom:16px;">
                <span class="section-pill">Section 4</span>
                <span class="section-title" style="margin-left:8px;">Handover Verification &amp; Acceptance Sign-off</span>
            </div>
            <div class="signoff-grid">
                <div class="sign-box">
                    <div class="sign-box-role">Commissioned By (Contractor)</div>
                    <div>
                        <div class="sign-meta-field"><strong>MEMIS SDN. BHD.</strong></div>
                        <div class="sign-meta-field">Engineer: <?= htmlspecialchars($user['full_name']) ?></div>
                    </div>
                    <div>
                        <div class="sign-line"></div>
                        <div class="sign-meta-field">Signature &amp; Company Stamp</div>
                        <div class="sign-meta-field">Date: <?= $reportDate ?></div>
                    </div>
                </div>

                <div class="sign-box">
                    <div class="sign-box-role">Verified By (Main Con / Consultant)</div>
                    <div>
                        <div class="sign-meta-field">Company: ___________________________</div>
                        <div class="sign-meta-field">Inspector: __________________________</div>
                    </div>
                    <div>
                        <div class="sign-line"></div>
                        <div class="sign-meta-field">Signature &amp; Chop</div>
                        <div class="sign-meta-field">Date: ________________________</div>
                    </div>
                </div>

                <div class="sign-box">
                    <div class="sign-box-role">Accepted By (Client / Owner)</div>
                    <div>
                        <div class="sign-meta-field">Client: <strong><?= htmlspecialchars($blueprint['client_name'] ?: '_____________________________') ?></strong></div>
                        <div class="sign-meta-field">Representative: _____________________</div>
                    </div>
                    <div>
                        <div class="sign-line"></div>
                        <div class="sign-meta-field">Signature &amp; Chop</div>
                        <div class="sign-meta-field">Date: ________________________</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>

