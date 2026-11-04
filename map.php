<?php
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/db.php';
requireLogin();
$user = getCurrentUser();

// ── Blueprint ID is required ──
$blueprintId = intval($_GET['id'] ?? 0);
if (!$blueprintId) { header('Location: dashboard.php'); exit; }

// ── Access check ──
if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;"><div style="text-align:center;"><h1>403 — Access Denied</h1><p>You do not have access to this blueprint.</p><a href="dashboard.php">← Back to Dashboard</a></div></body></html>';
    exit;
}

// ── Load blueprint data ──
$stmt = $pdo->prepare("SELECT b.*, u.full_name AS owner_name FROM blueprints b JOIN users u ON u.id = b.uploaded_by WHERE b.id = ?");
$stmt->execute([$blueprintId]);
$blueprint = $stmt->fetch();

if (!$blueprint) { header('Location: dashboard.php'); exit; }

// Verify the image file actually exists
$imagePath = __DIR__ . '/uploads/blueprints/' . $blueprint['image_filename'];
$imageExists = file_exists($imagePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteDeploy — <?= htmlspecialchars($blueprint['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --bg:#F3F3F3;--surface:#FFFFFF;--dark:#191A23;--dark-card:#292A32;
            --lime:#B9FF66;--lime-hover:#a8f050;--text:#191A23;--text-muted:#6b6d7b;
            --text-on-dark:#FFFFFF;--border:#E0E0E0;--red:#FF6B6B;
            --radius:14px;--radius-sm:8px;--radius-pill:100px;
            --shadow:0 2px 12px rgba(0,0,0,0.06);--shadow-lg:0 8px 32px rgba(0,0,0,0.10);
            --font:'Space Grotesk',system-ui,sans-serif;
            --transition:all 0.25s cubic-bezier(0.4,0,0.2,1);
        }
        body { font-family:var(--font); background:var(--bg); color:var(--text); line-height:1.5; min-height:100vh; }

        /* ── NAVBAR ── */
        .navbar { display:flex; align-items:center; justify-content:space-between; padding:12px 40px; background:var(--surface); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; }
        .navbar-left { display:flex; align-items:center; gap:16px; }
        .navbar-brand { display:flex; align-items:center; gap:10px; font-weight:700; font-size:1.3rem; color:var(--text); text-decoration:none; }
        .navbar-brand svg { width:28px; height:28px; }
        .back-btn { display:flex; align-items:center; gap:6px; padding:6px 14px; border-radius:var(--radius-pill); font-size:0.82rem; font-weight:500; color:var(--text-muted); text-decoration:none; border:1px solid var(--border); transition:var(--transition); }
        .back-btn:hover { background:var(--bg); color:var(--text); }
        .bp-title { font-weight:700; font-size:1rem; }
        .navbar-right { display:flex; align-items:center; gap:12px; }
        .user-badge { display:flex; align-items:center; gap:8px; padding:6px 16px; background:var(--bg); border-radius:var(--radius-pill); font-size:0.82rem; font-weight:500; }
        .user-badge .role-tag { background:var(--dark); color:var(--text-on-dark); padding:2px 8px; border-radius:var(--radius-pill); font-size:0.7rem; font-weight:600; text-transform:uppercase; }
        .user-badge .role-tag.admin { background:var(--lime); color:var(--dark); }

        .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 24px; border-radius:var(--radius-pill); border:2px solid var(--dark); background:transparent; color:var(--text); font-family:var(--font); font-weight:600; font-size:0.9rem; cursor:pointer; transition:var(--transition); text-decoration:none; }
        .btn:hover { background:var(--dark); color:var(--text-on-dark); }
        .btn-lime { background:var(--lime); border-color:var(--lime); color:var(--dark); }
        .btn-lime:hover { background:var(--lime-hover); border-color:var(--lime-hover); }
        .btn-danger { border-color:var(--red); color:var(--red); }
        .btn-danger:hover { background:var(--red); color:#fff; }
        .btn-sm { padding:6px 16px; font-size:0.82rem; }

        /* ── STATS ── */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; padding:16px 40px; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; flex-direction:column; gap:2px; }
        .stat-label { font-size:0.75rem; color:var(--text-muted); font-weight:500; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:1.6rem; font-weight:700; }
        .stat-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }

        /* ── MAP LAYOUT ── */
        .app-layout { display:grid; grid-template-columns:1fr 380px; gap:20px; padding:16px 40px 40px; min-height:65vh; }

        /* ── MAP CONTAINER ── */
        .map-container { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; display:flex; flex-direction:column; }
        .map-toolbar { display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:8px; }
        .map-toolbar-left { display:flex; align-items:center; gap:10px; }
        .filter-pills { display:flex; gap:4px; flex-wrap:wrap; }
        .filter-pill { padding:4px 12px; border-radius:var(--radius-pill); border:1px solid var(--border); background:var(--surface); font-family:var(--font); font-size:0.75rem; font-weight:500; cursor:pointer; transition:var(--transition); color:var(--text-muted); }
        .filter-pill:hover { border-color:var(--dark); color:var(--text); }
        .filter-pill.active { background:var(--dark); color:var(--text-on-dark); border-color:var(--dark); }

        .blueprint-area {
            position:relative; flex:1; min-height:500px; background:#121319;
            cursor:crosshair; overflow:auto; display:flex; justify-content:center; align-items:flex-start;
            padding:12px;
        }
        .blueprint-stage {
            position:relative; display:inline-block; max-width:100%; border-radius:6px;
            overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.45);
        }
        .blueprint-stage img { width:100%; height:auto; display:block; pointer-events:none; user-select:none; }

        /* ── MISSING IMAGE WARNING ── */
        .missing-bp-warning {
            position:absolute; inset:0; display:flex; flex-direction:column; align-items:center;
            justify-content:center; gap:16px; background:rgba(0,0,0,0.85); color:#fff; z-index:50;
            text-align:center; padding:40px;
        }
        .missing-bp-warning h3 { font-size:1.3rem; color:var(--red); }
        .missing-bp-warning p { color:rgba(255,255,255,0.7); max-width:400px; }

        /* ── PINS ── */
        .pin { position:absolute; transform:translate(-50%,-100%); cursor:pointer; z-index:10; transition:transform .15s ease; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .pin:hover { transform:translate(-50%,-100%) scale(1.2); z-index:20; }
        .pin.selected { transform:translate(-50%,-100%) scale(1.25); z-index:30; }
        .pin svg { width:32px; height:40px; }
        .pin-label { position:absolute; bottom:-8px; left:50%; transform:translateX(-50%); background:var(--dark); color:var(--text-on-dark); font-size:0.62rem; font-weight:600; padding:2px 8px; border-radius:var(--radius-pill); white-space:nowrap; pointer-events:none; }
        .pin[data-status="Live"] svg path { fill:#22c55e; }
        .pin[data-status="Hardware Mounted"] svg path { fill:#f59e0b; }
        .pin[data-status="Cable Pulled"] svg path { fill:#3b82f6; }
        .pin[data-status="Planned"] svg path { fill:#ef4444; }
        .pin[data-status="Troubleshoot"] svg path { fill:#a855f7; }

        /* ── SIDE PANEL ── */
        .side-panel { background:var(--dark-card); border-radius:var(--radius); color:var(--text-on-dark); display:flex; flex-direction:column; overflow:hidden; }
        .panel-header { padding:20px 24px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .panel-header h3 { font-size:1.1rem; font-weight:700; margin-bottom:2px; }
        .panel-header p { font-size:0.78rem; color:rgba(255,255,255,0.5); }
        .panel-body { padding:20px 24px; flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:16px; }

        .form-group { display:flex; flex-direction:column; gap:5px; }
        .form-group label { font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:rgba(255,255,255,0.6); }
        .form-group input, .form-group select, .form-group textarea {
            background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:var(--radius-sm);
            padding:10px 14px; color:var(--text-on-dark); font-family:var(--font); font-size:0.88rem; transition:var(--transition); outline:none;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--lime); background:rgba(255,255,255,0.1); }
        .form-group select { cursor:pointer; }
        .form-group select option { background:var(--dark-card); color:#fff; }
        .form-group textarea { resize:vertical; min-height:60px; }

        .coord-display { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .coord-box { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-sm); padding:8px 12px; text-align:center; }
        .coord-box .cl { font-size:0.68rem; text-transform:uppercase; color:rgba(255,255,255,0.4); letter-spacing:0.5px; }
        .coord-box .cv { font-size:1rem; font-weight:700; color:var(--lime); font-variant-numeric:tabular-nums; }

        .panel-footer { padding:16px 24px; border-top:1px solid rgba(255,255,255,0.08); display:flex; flex-direction:column; gap:8px; }
        .panel-footer .btn { width:100%; justify-content:center; }

        /* ── PIN-FIRST NOTICE ── */
        .pin-first-notice {
            display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px;
            padding:24px 16px; background:rgba(185,255,102,0.06); border:1px dashed rgba(185,255,102,0.25);
            border-radius:var(--radius-sm); color:rgba(255,255,255,0.7); margin-bottom:4px;
        }
        .pin-first-notice strong { font-size:0.95rem; color:var(--lime); }
        .pin-first-notice span { font-size:0.78rem; color:rgba(255,255,255,0.45); }
        .pin-first-notice.hidden { display:none; }

        .node-form-fields.disabled { opacity:0.35; pointer-events:none; filter:grayscale(0.5); }
        .node-form-fields.enabled { opacity:1; pointer-events:auto; filter:none; }

        .btn-disabled-muted:disabled { opacity:0.4; cursor:not-allowed; background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.1); color:rgba(255,255,255,0.4); }

        /* ── TABLE ── */
        .table-section { padding:0 40px 60px; }
        .section-header { display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .section-title { background:var(--lime); padding:4px 16px; border-radius:var(--radius-sm); font-size:1.3rem; font-weight:700; }
        .table-container { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
        .table-toolbar { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:8px; }
        .search-box { display:flex; align-items:center; gap:8px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-pill); padding:8px 16px; min-width:240px; }
        .search-box svg { width:14px; height:14px; opacity:0.4; }
        .search-box input { border:none; background:none; outline:none; font-family:var(--font); font-size:0.85rem; color:var(--text); width:100%; }
        table { width:100%; border-collapse:collapse; }
        thead th { padding:10px 16px; text-align:left; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); background:var(--bg); border-bottom:1px solid var(--border); }
        tbody td { padding:12px 16px; font-size:0.85rem; border-bottom:1px solid var(--border); }
        tbody tr { transition:var(--transition); cursor:pointer; }
        tbody tr:hover { background:#f7f8fa; }
        tbody tr:last-child td { border-bottom:none; }

        .status-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:var(--radius-pill); font-size:0.75rem; font-weight:600; }
        .status-badge::before { content:''; width:6px; height:6px; border-radius:50%; }
        .status-badge.live { background:#dcfce7; color:#166534; } .status-badge.live::before { background:#22c55e; }
        .status-badge.hardware-mounted { background:#fef3c7; color:#92400e; } .status-badge.hardware-mounted::before { background:#f59e0b; }
        .status-badge.cable-pulled { background:#dbeafe; color:#1e40af; } .status-badge.cable-pulled::before { background:#3b82f6; }
        .status-badge.planned { background:#fee2e2; color:#991b1b; } .status-badge.planned::before { background:#ef4444; }
        .status-badge.troubleshoot { background:#f3e8ff; color:#6b21a8; } .status-badge.troubleshoot::before { background:#a855f7; }
        .ip-mono { font-family:'SF Mono','Cascadia Code','Consolas',monospace; font-size:0.78rem; background:var(--bg); padding:2px 8px; border-radius:var(--radius-sm); }

        /* ── TOAST ── */
        .toast-container { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
        .toast { background:var(--dark); color:var(--text-on-dark); padding:14px 24px; border-radius:var(--radius); font-size:0.85rem; font-weight:500; box-shadow:var(--shadow-lg); display:flex; align-items:center; gap:10px; animation:toastIn .3s ease,toastOut .3s ease 2.7s forwards; max-width:360px; }
        .toast.success { border-left:4px solid var(--lime); }
        .toast.error { border-left:4px solid var(--red); }
        @keyframes toastIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        @keyframes toastOut { from{opacity:1} to{opacity:0;transform:translateY(-8px)} }

        @media (max-width:900px) {
            .app-layout { grid-template-columns:1fr; }
            .navbar,.stats-row,.app-layout,.table-section { padding-left:20px; padding-right:20px; }
        }
        ::-webkit-scrollbar{width:5px} ::-webkit-scrollbar-thumb{background:#ccc;border-radius:3px}
        .side-panel ::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.15)}
    </style>
</head>
<body>

    <!-- ═══ NAVBAR ═══ -->
    <nav class="navbar">
        <div class="navbar-left">
            <a class="navbar-brand" href="dashboard.php">
                <svg viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="6" fill="#191A23"/><path d="M7 14h14M14 7v14M9 9l10 10M19 9L9 19" stroke="#B9FF66" stroke-width="2" stroke-linecap="round"/></svg>
                SiteDeploy
            </a>
            <a class="back-btn" href="dashboard.php">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Dashboard
            </a>
            <span class="bp-title"><?= htmlspecialchars($blueprint['name']) ?></span>
        </div>
        <div class="navbar-right">
            <?php if ($blueprint['uploaded_by'] == $user['id'] || $user['role'] === 'admin'): ?>
                <button class="btn btn-sm btn-danger" onclick="deleteCurrentBlueprint()" title="Delete this blueprint and all its nodes">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    Delete Blueprint
                </button>
            <?php endif; ?>
            <div class="user-badge">
                <?= htmlspecialchars($user['full_name']) ?>
                <span class="role-tag <?= $user['role'] ?>"><?= $user['role'] ?></span>
            </div>
        </div>
    </nav>

    <!-- ═══ STATS ═══ -->
    <div class="stats-row">
        <div class="stat-card"><span class="stat-label">Total Nodes</span><span class="stat-value" id="statTotal">0</span></div>
        <div class="stat-card"><span class="stat-label"><span class="stat-dot" style="background:#22c55e"></span>Live</span><span class="stat-value" id="statLive">0</span></div>
        <div class="stat-card"><span class="stat-label"><span class="stat-dot" style="background:#f59e0b"></span>Mounted</span><span class="stat-value" id="statMounted">0</span></div>
        <div class="stat-card"><span class="stat-label"><span class="stat-dot" style="background:#3b82f6"></span>Cable</span><span class="stat-value" id="statCable">0</span></div>
        <div class="stat-card"><span class="stat-label"><span class="stat-dot" style="background:#ef4444"></span>Planned</span><span class="stat-value" id="statPlanned">0</span></div>
    </div>

    <!-- ═══ MAP + SIDE PANEL ═══ -->
    <div class="app-layout">
        <div class="map-container">
            <div class="map-toolbar">
                <div class="map-toolbar-left">
                    <span style="font-weight:600;font-size:0.85rem;">Floorplan</span>
                    <div class="filter-pills">
                        <button class="filter-pill active" data-filter="all">All</button>
                        <button class="filter-pill" data-filter="Live">Live</button>
                        <button class="filter-pill" data-filter="Hardware Mounted">Mounted</button>
                        <button class="filter-pill" data-filter="Cable Pulled">Cable</button>
                        <button class="filter-pill" data-filter="Planned">Planned</button>
                    </div>
                </div>
                <span style="font-size:0.75rem;color:var(--text-muted);" id="pinCount">0 pins</span>
            </div>
            <div class="blueprint-area" id="blueprintArea">
                <?php if ($imageExists): ?>
                    <div class="blueprint-stage" id="blueprintStage">
                        <img src="uploads/blueprints/<?= htmlspecialchars($blueprint['image_filename']) ?>" alt="Blueprint" id="blueprintImg">
                    </div>
                <?php else: ?>
                    <div class="missing-bp-warning">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <h3>Blueprint Image Missing</h3>
                        <p>The floorplan image for this blueprint cannot be found on the server. Pin placement is disabled to prevent data misalignment.</p>
                        <a href="dashboard.php" class="btn" style="border-color:#fff;color:#fff;">← Back to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="side-panel">
            <div class="panel-header">
                <h3 id="panelTitle">Node Inspector</h3>
                <p id="panelSubtitle">Click the map to drop a pin</p>
            </div>
            <div class="panel-body">
                <!-- Pin-first notice: shown when no pin is placed yet -->
                <div class="pin-first-notice" id="pinFirstNotice">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--lime)" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <strong>Pin a location first</strong>
                    <span>Click on the floorplan to drop a pin before filling in the device details.</span>
                </div>
                <div class="coord-display">
                    <div class="coord-box"><div class="cl">X Position</div><div class="cv" id="coordX">—</div></div>
                    <div class="coord-box"><div class="cl">Y Position</div><div class="cv" id="coordY">—</div></div>
                </div>
                <div id="nodeFormFields" class="node-form-fields disabled">
                <div class="form-group"><label>Device ID</label><input type="text" id="inputDeviceId" placeholder="e.g. CAM-01" disabled></div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="inputType" disabled>
                        <option value="Hikvision Dome">Hikvision Dome</option><option value="Hikvision Bullet">Hikvision Bullet</option>
                        <option value="Dahua PTZ">Dahua PTZ</option><option value="Dahua Dome">Dahua Dome</option>
                        <option value="NVR Rack">NVR Rack</option><option value="Access Point">Access Point</option>
                        <option value="Access Door">Access Door</option><option value="Switch">Switch</option>
                        <option value="Sensor">Sensor</option><option value="Generic" selected>Generic</option>
                    </select>
                </div>
                <div class="form-group"><label>IP Address</label><input type="text" id="inputIp" placeholder="e.g. 192.168.1.100" disabled></div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="inputStatus" disabled>
                        <option value="Planned">🔴 Planned</option><option value="Cable Pulled">🔵 Cable Pulled</option>
                        <option value="Hardware Mounted">🟡 Hardware Mounted</option><option value="Live">🟢 Live</option>
                        <option value="Troubleshoot">🟣 Troubleshoot</option>
                    </select>
                </div>
                <div class="form-group"><label>Notes</label><textarea id="inputNotes" placeholder="Field notes..." disabled></textarea></div>
                </div><!-- end #nodeFormFields -->
            </div>
            <div class="panel-footer">
                <button class="btn btn-lime btn-disabled-muted" id="btnSave" onclick="saveNode()" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Pin a location first
                </button>
                <button class="btn btn-danger btn-sm" id="btnDelete" onclick="deleteNode()" style="display:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    Delete Node
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ NODE TABLE ═══ -->
    <div class="table-section">
        <div class="section-header">
            <span class="section-title">All Nodes</span>
        </div>
        <div class="table-container">
            <div class="table-toolbar">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="searchInput" placeholder="Search by device, type, IP...">
                </div>
                <span style="font-size:0.78rem;color:var(--text-muted);" id="tableCount">0 nodes</span>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>Device ID</th><th>Type</th><th>IP Address</th><th>Status</th><th>Position</th><th>Notes</th><th>By</th></tr></thead>
                    <tbody id="nodeTableBody"><tr><td colspan="7" style="text-align:center;padding:40px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ── CONFIG ──
        const BLUEPRINT_ID = <?= $blueprintId ?>;
        const IMAGE_EXISTS = <?= $imageExists ? 'true' : 'false' ?>;
        const BP_WIDTH     = <?= intval($blueprint['image_width']) ?>;
        const BP_HEIGHT    = <?= intval($blueprint['image_height']) ?>;

        let nodes = [];
        let selectedPin = null;
        let currentPosX = null, currentPosY = null;
        let activeFilter = 'all';
        let isNewPin = false;

        const PIN_SVG = `<svg viewBox="0 0 32 40" fill="none"><path d="M16 0C7.2 0 0 7.2 0 16c0 12 16 24 16 24s16-12 16-24C32 7.2 24.8 0 16 0z" fill="#ef4444"/><circle cx="16" cy="15" r="7" fill="white" opacity="0.9"/><circle cx="16" cy="15" r="3" fill="#ef4444"/></svg>`;

        document.addEventListener('DOMContentLoaded', () => {
            fetchNodes();
            if (IMAGE_EXISTS) setupBlueprintClick();
            setupFilters();
            document.getElementById('searchInput').addEventListener('input', renderTable);
        });

        // ── FETCH NODES ──
        async function fetchNodes() {
            try {
                const res = await fetch('api/get_nodes.php?blueprint_id=' + BLUEPRINT_ID);
                const json = await res.json();
                if (json.success) { nodes = json.data; renderPins(); renderTable(); updateStats(); }
            } catch(e) { showToast('Failed to load nodes: ' + e.message, 'error'); }
        }

        // ── RENDER PINS ──
        function renderPins() {
            document.querySelectorAll('.pin').forEach(p => p.remove());
            if (!IMAGE_EXISTS) return;
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            nodes.forEach(node => {
                if (activeFilter !== 'all' && node.status !== activeFilter) return;
                const pin = document.createElement('div');
                pin.className = 'pin';
                pin.innerHTML = PIN_SVG + `<span class="pin-label">${esc(node.device_id)}</span>`;
                pin.style.left = node.pos_x + '%';
                pin.style.top  = node.pos_y + '%';
                pin.dataset.deviceId = node.device_id;
                pin.dataset.type     = node.type;
                pin.dataset.ip       = node.ip_address;
                pin.dataset.status   = node.status;
                pin.dataset.notes    = node.notes || '';
                pin.dataset.posX     = node.pos_x;
                pin.dataset.posY     = node.pos_y;
                pin.setAttribute('data-status', node.status);
                pin.addEventListener('click', (e) => { e.stopPropagation(); selectPin(pin); });
                stage.appendChild(pin);
            });

            const c = document.querySelectorAll('.pin').length;
            document.getElementById('pinCount').textContent = c + ' pin' + (c !== 1 ? 's' : '');
        }

        // ── FORM LOCK / UNLOCK HELPERS ──
        function enableFormFields(allowDeviceIdEdit = true) {
            const notice = document.getElementById('pinFirstNotice');
            if (notice) notice.classList.add('hidden');

            const container = document.getElementById('nodeFormFields');
            if (container) {
                container.classList.remove('disabled');
                container.classList.add('enabled');
            }

            document.getElementById('inputDeviceId').disabled = !allowDeviceIdEdit;
            document.getElementById('inputType').disabled = false;
            document.getElementById('inputIp').disabled = false;
            document.getElementById('inputStatus').disabled = false;
            document.getElementById('inputNotes').disabled = false;

            const btnSave = document.getElementById('btnSave');
            btnSave.disabled = false;
            btnSave.classList.remove('btn-disabled-muted');
        }

        function disableFormFields() {
            const notice = document.getElementById('pinFirstNotice');
            if (notice) notice.classList.remove('hidden');

            const container = document.getElementById('nodeFormFields');
            if (container) {
                container.classList.add('disabled');
                container.classList.remove('enabled');
            }

            document.getElementById('inputDeviceId').disabled = true;
            document.getElementById('inputType').disabled = true;
            document.getElementById('inputIp').disabled = true;
            document.getElementById('inputStatus').disabled = true;
            document.getElementById('inputNotes').disabled = true;

            const btnSave = document.getElementById('btnSave');
            btnSave.disabled = true;
            btnSave.classList.add('btn-disabled-muted');
            btnSave.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Pin a location first`;
        }

        // ── SELECT PIN ──
        function selectPin(pin) {
            document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
            pin.classList.add('selected');
            selectedPin = pin;
            isNewPin = false;
            currentPosX = parseFloat(pin.dataset.posX);
            currentPosY = parseFloat(pin.dataset.posY);

            enableFormFields(false); // Existing pin: device ID is locked

            document.getElementById('coordX').textContent = currentPosX.toFixed(1) + '%';
            document.getElementById('coordY').textContent = currentPosY.toFixed(1) + '%';
            document.getElementById('inputDeviceId').value = pin.dataset.deviceId;
            document.getElementById('inputType').value     = pin.dataset.type;
            document.getElementById('inputIp').value       = pin.dataset.ip;
            document.getElementById('inputStatus').value   = pin.dataset.status;
            document.getElementById('inputNotes').value    = pin.dataset.notes;
            document.getElementById('panelTitle').textContent = pin.dataset.deviceId;
            document.getElementById('panelSubtitle').textContent = 'Editing existing node';
            document.getElementById('btnDelete').style.display = '';
            document.getElementById('btnSave').innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Update Node`;
        }

        // ── CLICK MAP ──
        function setupBlueprintClick() {
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            stage.addEventListener('click', (e) => {
                const rect = stage.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width * 100);
                const y = ((e.clientY - rect.top)  / rect.height * 100);
                currentPosX = parseFloat(x.toFixed(4));
                currentPosY = parseFloat(y.toFixed(4));

                document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
                document.querySelectorAll('.pin.temp-pin').forEach(p => p.remove());

                const pin = document.createElement('div');
                pin.className = 'pin selected temp-pin';
                pin.innerHTML = PIN_SVG + `<span class="pin-label">NEW</span>`;
                pin.style.left = x + '%';
                pin.style.top  = y + '%';
                pin.setAttribute('data-status', 'Planned');
                stage.appendChild(pin);

                selectedPin = pin;
                isNewPin = true;

                enableFormFields(true); // New pin: enable device ID input

                document.getElementById('coordX').textContent = x.toFixed(1) + '%';
                document.getElementById('coordY').textContent = y.toFixed(1) + '%';
                document.getElementById('inputDeviceId').value = '';
                document.getElementById('inputType').value = 'Generic';
                document.getElementById('inputIp').value = '';
                document.getElementById('inputStatus').value = 'Planned';
                document.getElementById('inputNotes').value = '';
                document.getElementById('panelTitle').textContent = 'New Node';
                document.getElementById('panelSubtitle').textContent = 'Fill in the details and save';
                document.getElementById('btnDelete').style.display = 'none';
                document.getElementById('btnSave').innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> Save New Node`;
                document.getElementById('inputDeviceId').focus();
            });
        }

        // ── SAVE ──
        async function saveNode() {
            if (currentPosX === null || currentPosY === null) {
                showToast('Please click on the blueprint to place a pin location first!', 'error');
                return;
            }

            const deviceId = document.getElementById('inputDeviceId').value.trim();
            if (!deviceId) {
                showToast('Device ID is required', 'error');
                document.getElementById('inputDeviceId').focus();
                return;
            }
            if (!IMAGE_EXISTS) {
                showToast('Cannot save — blueprint image is missing', 'error');
                return;
            }

            const payload = {
                blueprint_id: BLUEPRINT_ID,
                device_id:  deviceId,
                type:       document.getElementById('inputType').value,
                ip_address: document.getElementById('inputIp').value.trim(),
                pos_x:      currentPosX,
                pos_y:      currentPosY,
                status:     document.getElementById('inputStatus').value,
                notes:      document.getElementById('inputNotes').value.trim(),
            };

            try {
                const res = await fetch('api/save_node.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
                const json = await res.json();
                if (json.success) { showToast(json.message,'success'); await fetchNodes(); resetPanel(); }
                else showToast(json.error||'Save failed','error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── DELETE ──
        async function deleteNode() {
            const deviceId = document.getElementById('inputDeviceId').value.trim();
            if (!deviceId || !confirm('Delete "'+deviceId+'"?')) return;
            try {
                const res = await fetch('api/delete_node.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({blueprint_id:BLUEPRINT_ID, device_id:deviceId}) });
                const json = await res.json();
                if (json.success) { showToast(json.message,'success'); await fetchNodes(); resetPanel(); }
                else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── RESET PANEL ──
        function resetPanel() {
            selectedPin = null; isNewPin = false; currentPosX = currentPosY = null;
            document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
            document.querySelectorAll('.pin.temp-pin').forEach(p => p.remove());
            document.getElementById('coordX').textContent = '—';
            document.getElementById('coordY').textContent = '—';
            document.getElementById('inputDeviceId').value = '';
            document.getElementById('inputType').value = 'Generic';
            document.getElementById('inputIp').value = '';
            document.getElementById('inputStatus').value = 'Planned';
            document.getElementById('inputNotes').value = '';
            document.getElementById('panelTitle').textContent = 'Node Inspector';
            document.getElementById('panelSubtitle').textContent = 'Click the map to drop a pin';
            document.getElementById('btnDelete').style.display = 'none';

            disableFormFields();
        }

        // ── DELETE CURRENT BLUEPRINT (Owner or Admin) ──
        async function deleteCurrentBlueprint() {
            if (!confirm('Are you sure you want to delete this entire blueprint? All pins and shared access will be permanently removed.')) return;
            try {
                const res = await fetch('api/delete_blueprint.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ blueprint_id: BLUEPRINT_ID })
                });
                const json = await res.json();
                if (json.success) {
                    alert('Blueprint deleted successfully.');
                    window.location.href = 'dashboard.php';
                } else {
                    showToast(json.error || 'Failed to delete blueprint', 'error');
                }
            } catch (e) {
                showToast('Network error while deleting blueprint', 'error');
            }
        }

        // ── TABLE ──
        function renderTable() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            let f = nodes;
            if (q) f = nodes.filter(n => n.device_id.toLowerCase().includes(q) || n.type.toLowerCase().includes(q) || n.ip_address.toLowerCase().includes(q) || (n.notes||'').toLowerCase().includes(q));
            document.getElementById('tableCount').textContent = f.length + ' node' + (f.length!==1?'s':'');
            const tb = document.getElementById('nodeTableBody');
            if (!f.length) { tb.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">No nodes found</td></tr>'; return; }
            tb.innerHTML = f.map(n => `
                <tr onclick="scrollToPin('${escAttr(n.device_id)}')">
                    <td><strong>${esc(n.device_id)}</strong></td>
                    <td>${esc(n.type)}</td>
                    <td><span class="ip-mono">${esc(n.ip_address||'—')}</span></td>
                    <td><span class="status-badge ${statusClass(n.status)}">${esc(n.status)}</span></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);">${parseFloat(n.pos_x).toFixed(1)}%, ${parseFloat(n.pos_y).toFixed(1)}%</td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(n.notes||'—')}</td>
                    <td style="font-size:0.78rem;color:var(--text-muted);">${esc(n.created_by_username||'—')}</td>
                </tr>
            `).join('');
        }

        function scrollToPin(devId) {
            const pin = document.querySelector(`.pin[data-device-id="${devId}"]`);
            if (pin) { pin.scrollIntoView({behavior:'smooth',block:'center'}); selectPin(pin); }
        }

        // ── STATS ──
        function updateStats() {
            document.getElementById('statTotal').textContent   = nodes.length;
            document.getElementById('statLive').textContent     = nodes.filter(n=>n.status==='Live').length;
            document.getElementById('statMounted').textContent  = nodes.filter(n=>n.status==='Hardware Mounted').length;
            document.getElementById('statCable').textContent    = nodes.filter(n=>n.status==='Cable Pulled').length;
            document.getElementById('statPlanned').textContent  = nodes.filter(n=>n.status==='Planned').length;
        }

        // ── FILTERS ──
        function setupFilters() {
            document.querySelectorAll('.filter-pill').forEach(p => {
                p.addEventListener('click', () => {
                    document.querySelectorAll('.filter-pill').forEach(x => x.classList.remove('active'));
                    p.classList.add('active');
                    activeFilter = p.dataset.filter;
                    renderPins();
                });
            });
        }

        // ── TOAST ──
        function showToast(m,t='info') {
            const c=document.getElementById('toastContainer'), el=document.createElement('div');
            el.className='toast '+t; el.innerHTML=`<span style="font-size:1.1rem;">${{success:'✓',error:'✕',info:'ℹ'}[t]||'ℹ'}</span> ${esc(m)}`;
            c.appendChild(el); setTimeout(()=>el.remove(),3200);
        }

        // ── HELPERS ──
        function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
        function escAttr(s) { return (s||'').replace(/"/g,'&quot;').replace(/'/g,"\\'"); }
        function statusClass(s) { return (s||'').toLowerCase().replace(/\s+/g,'-'); }
    </script>
</body>
</html>

