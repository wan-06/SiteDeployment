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
$stmt = $pdo->prepare("
    SELECT b.*, u.full_name AS owner_name,
           p.id AS project_id, p.name AS project_name, p.client_name, p.code AS project_code, p.status AS project_status
    FROM blueprints b 
    JOIN users u ON u.id = b.uploaded_by 
    LEFT JOIN projects p ON p.id = b.project_id
    WHERE b.id = ?
");
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
        .nav-project-crumb { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:var(--radius-pill); background:var(--bg); border:1px solid var(--border); text-decoration:none; color:var(--text); font-size:0.82rem; font-weight:600; transition:var(--transition); max-width:260px; }
        .nav-project-crumb:hover { background:var(--dark); color:#fff; border-color:var(--dark); }
        .nav-project-crumb .proj-code-pill { background:var(--lime); color:var(--dark); font-size:0.68rem; font-weight:700; padding:1px 6px; border-radius:4px; flex-shrink:0; }
        .nav-project-crumb .proj-client-text { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .breadcrumb-sep { color:var(--border); font-size:0.85rem; user-select:none; }
        .bp-title { font-weight:700; font-size:1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:320px; }
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
        .stat-card {
            background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
            padding:16px 20px; display:flex; flex-direction:column; gap:2px; cursor:pointer; transition:var(--transition);
            user-select:none;
        }
        .stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow); border-color:var(--dark); }
        .stat-card.active { border-color:var(--dark); background:#f4f4f0; box-shadow:0 0 0 2px var(--lime); }
        .stat-label { font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:1.6rem; font-weight:700; }
        .stat-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }

        /* ── MAP LAYOUT ── */
        .app-layout { display:grid; grid-template-columns:1fr 380px; gap:20px; padding:16px 40px 40px; min-height:65vh; }

        /* ── MAP CONTAINER ── */
        .map-container { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; display:flex; flex-direction:column; }
        .map-toolbar { display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:10px; }
        .map-toolbar-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .filter-pills { display:flex; gap:4px; flex-wrap:wrap; }
        .filter-pill { padding:5px 14px; border-radius:var(--radius-pill); border:1px solid var(--border); background:var(--surface); font-family:var(--font); font-size:0.75rem; font-weight:600; cursor:pointer; transition:var(--transition); color:var(--text-muted); }
        .filter-pill:hover { border-color:var(--dark); color:var(--text); }
        .filter-pill.active { background:var(--dark); color:var(--text-on-dark); border-color:var(--dark); }

        .blueprint-search-box {
            display:inline-flex; align-items:center; gap:6px; background:var(--bg);
            border:1px solid var(--border); border-radius:var(--radius-pill);
            padding:5px 14px; min-width:250px; transition:var(--transition);
        }
        .blueprint-search-box:focus-within {
            border-color:var(--dark); background:var(--surface);
            box-shadow:0 0 0 2px rgba(185,255,102,0.4);
        }
        .blueprint-search-box svg { width:14px; height:14px; opacity:0.5; flex-shrink:0; }
        .blueprint-search-box input { border:none; background:none; outline:none; font-family:var(--font); font-size:0.8rem; color:var(--text); width:100%; }
        .search-clear-btn { border:none; background:none; cursor:pointer; font-size:0.75rem; color:var(--text-muted); padding:0 2px; line-height:1; }
        .search-clear-btn:hover { color:var(--dark); }

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
        .panel-close-btn {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.7); width: 28px; height: 28px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: bold; transition: var(--transition); flex-shrink: 0;
        }
        .panel-close-btn:hover { background: #ef4444; color: #fff; border-color: #ef4444; }
        .btn-secondary {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.85); font-weight: 600;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.18); color: #fff; border-color: rgba(255,255,255,0.4); }

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

        /* ── T&C FIELD STYLES ── */
        .tc-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0 6px;
            padding-top: 12px;
            border-top: 1px dashed rgba(255,255,255,0.15);
        }
        .tc-pill {
            background: rgba(185,255,102,0.18);
            color: var(--lime);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tc-desc { font-size: 0.72rem; color: rgba(255,255,255,0.45); }
        .tag-mono { font-family:'SF Mono','Cascadia Code','Consolas',monospace; font-size:0.75rem; background:#E5E7EB; color:#111; padding:2px 6px; border-radius:4px; font-weight:700; }

        /* ── INTERACTIVE CABLE ROUTES & LENGTH CALCULATOR ── */
        .btn-cable-toggle, .btn-cable-schedule, .btn-scale-calibrate {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: var(--radius-pill);
            border: 1px solid var(--border); background: var(--surface);
            font-family: var(--font); font-size: 0.75rem; font-weight: 600;
            cursor: pointer; transition: var(--transition); color: var(--text-muted);
        }
        .btn-cable-toggle:hover, .btn-cable-schedule:hover, .btn-scale-calibrate:hover {
            border-color: var(--dark); color: var(--text);
        }
        .btn-cable-toggle.active {
            background: #2563eb; border-color: #1d4ed8; color: #fff;
            box-shadow: 0 1px 6px rgba(37, 99, 235, 0.35);
        }
        .btn-cable-schedule {
            background: rgba(185,255,102,0.15); border-color: rgba(25,26,35,0.2); color: var(--dark);
        }
        .btn-cable-schedule:hover {
            background: var(--lime); border-color: var(--dark); color: var(--dark);
        }
        .btn-scale-calibrate {
            background: var(--bg); font-family: 'IBM Plex Mono', monospace;
        }

        .cable-svg-layer {
            position: absolute; inset: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 4; overflow: visible;
        }
        .cable-group { pointer-events: all; cursor: pointer; }
        .cable-path-glow {
            stroke-linecap: round; stroke-linejoin: round;
            fill: none; opacity: 0.22; transition: stroke-width 0.2s, opacity 0.2s;
        }
        .cable-path-core {
            stroke-linecap: round; stroke-linejoin: round;
            fill: none; transition: stroke-width 0.2s, stroke 0.2s;
        }
        .cable-path-dash {
            stroke: #ffffff; stroke-width: 1.5; stroke-dasharray: 6 6;
            stroke-linecap: round; fill: none; opacity: 0.75;
            animation: cablePulse 1.2s linear infinite;
        }
        @keyframes cablePulse {
            from { stroke-dashoffset: 24; }
            to { stroke-dashoffset: 0; }
        }
        .cable-group:hover .cable-path-glow, .cable-group.active-selected .cable-path-glow {
            opacity: 0.65; stroke-width: 12px; filter: drop-shadow(0 0 6px currentColor);
        }
        .cable-group:hover .cable-path-core, .cable-group.active-selected .cable-path-core {
            stroke-width: 4px;
        }

        .cable-waypoint-handle {
            r: 5; fill: #ffffff; stroke: #191A23; stroke-width: 2.5;
            cursor: move; transition: transform 0.15s, r 0.15s; pointer-events: all;
        }
        .cable-waypoint-handle:hover, .cable-waypoint-handle:active {
            r: 8; fill: var(--lime); stroke: var(--dark);
        }

        .cable-tag-badge {
            font-family: 'IBM Plex Mono', monospace; font-size: 10px; font-weight: 700;
            fill: #ffffff; text-anchor: middle; dominant-baseline: central;
            pointer-events: none; user-select: none;
        }
        .cable-tag-rect {
            rx: 4; ry: 4; fill: #191A23; stroke: rgba(255,255,255,0.4); stroke-width: 1;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
        }

        /* Routing crosshair mode */
        .stage-routing-mode { cursor: crosshair !important; }
        .routing-banner {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            background: #191A23; color: #fff; padding: 6px 16px; border-radius: var(--radius-pill);
            border: 1.5px solid var(--lime); box-shadow: var(--shadow-lg);
            font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 10px;
            z-index: 100;
        }
        .btn-finish-routing {
            background: var(--lime); color: var(--dark); border: none; padding: 3px 10px;
            border-radius: var(--radius-pill); font-size: 0.75rem; font-weight: 700; cursor: pointer;
        }

        /* Inspector Cable Section */
        .cable-section-heading {
            display: flex; flex-direction: column; gap: 4px;
            padding-top: 14px; margin-top: 10px;
            border-top: 1px dashed rgba(255,255,255,0.14);
        }
        .cable-calc-box {
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: var(--radius-sm); padding: 8px 12px; margin: 8px 0;
        }
        .cable-calc-label { font-size: 0.72rem; color: #93c5fd; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .cable-calc-val { font-size: 1.15rem; font-weight: 800; font-family: 'IBM Plex Mono', monospace; color: #60a5fa; }
        .cable-action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 6px; }
        .btn-cable-act {
            padding: 6px 10px; font-size: 0.74rem; font-weight: 600; border-radius: var(--radius-sm);
            border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.85); cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-family: inherit;
        }
        .btn-cable-act:hover { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3); }
        .btn-cable-act.primary { background: #3b82f6; border-color: #2563eb; color: #fff; }
        .btn-cable-act.primary:hover { background: #2563eb; }
        .btn-cable-act.danger { color: #f87171; border-color: rgba(239, 68, 68, 0.4); }
        .btn-cable-act.danger:hover { background: #ef4444; color: #fff; }

        /* Cable Schedule Modal */
        .cable-modal-dialog { max-width: 920px; width: 95%; max-height: 90vh; }
        .bom-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .bom-card {
            background: #FAFAFA; border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px;
        }
        .bom-card .bom-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .bom-card .bom-val { font-size: 1.35rem; font-weight: 800; color: var(--dark); margin: 4px 0; font-family: 'IBM Plex Mono', monospace; }
        .bom-card .bom-sub { font-size: 0.72rem; color: #0284c7; font-weight: 600; }
        .cable-schedule-table-wrap { max-height: 380px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }

        /* ── REAL-TIME LIVE PING STATUS & NOC MONITOR ── */
        .btn-live-ping, .btn-noc-modal {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: var(--radius-pill);
            border: 1px solid var(--border); background: var(--surface);
            font-family: var(--font); font-size: 0.75rem; font-weight: 600;
            cursor: pointer; transition: var(--transition); color: var(--text-muted);
        }
        .btn-live-ping:hover, .btn-noc-modal:hover { border-color: var(--dark); color: var(--text); }
        .btn-live-ping.active {
            background: #10b981; border-color: #059669; color: #ffffff;
            box-shadow: 0 1px 6px rgba(16, 185, 129, 0.4);
        }
        .btn-noc-modal {
            background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); color: #047857;
        }
        .btn-noc-modal:hover {
            background: #10b981; border-color: #059669; color: #ffffff;
        }
        .ping-radar-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #9ca3af;
            transition: background 0.3s;
        }
        .btn-live-ping.active .ping-radar-dot {
            background: #ffffff;
            box-shadow: 0 0 8px #ffffff;
            animation: radarPulse 1.2s infinite;
        }
        @keyframes radarPulse {
            0% { transform: scale(0.9); opacity: 1; }
            50% { transform: scale(1.35); opacity: 0.5; }
            100% { transform: scale(0.9); opacity: 1; }
        }

        /* Pin Ping Rings & Halos */
        .pin-ping-pulse {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 38px; height: 38px; border-radius: 50%;
            pointer-events: none; z-index: -1;
        }
        .pin.ping-online .pin-ping-pulse {
            border: 2px solid #22c55e;
            animation: pingPulseRing 2s ease-out infinite;
        }
        .pin.ping-offline .pin-ping-pulse {
            border: 2.5px solid #ef4444;
            animation: pingPulseRing 1.1s ease-out infinite;
        }
        @keyframes pingPulseRing {
            0% { transform: translate(-50%, -50%) scale(0.6); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(1.7); opacity: 0; }
        }
        .pin-ping-tag {
            position: absolute; bottom: -18px; left: 50%;
            transform: translateX(-50%);
            font-family: 'IBM Plex Mono', monospace;
            font-size: 8.5px; font-weight: 700;
            padding: 1px 4px; border-radius: 3px;
            white-space: nowrap; pointer-events: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            line-height: 1.1;
        }
        .pin-ping-tag.online { background: #22c55e; color: #ffffff; }
        .pin-ping-tag.offline { background: #ef4444; color: #ffffff; animation: blinkSlow 1s infinite; }
        .pin-ping-tag.pinging { background: #f59e0b; color: #111827; }
        @keyframes blinkSlow { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* Inspector Ping Badge & Button */
        .ping-status-badge {
            font-family: 'IBM Plex Mono', monospace; font-size: 0.68rem; font-weight: 700;
            padding: 2px 7px; border-radius: var(--radius-pill);
            display: inline-flex; align-items: center; gap: 4px;
        }
        .ping-status-badge.online  { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); }
        .ping-status-badge.offline { background: rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5); }
        .ping-status-badge.pinging { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .ping-status-badge.invalid { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.3); }
        .ping-status-badge.unknown { background: rgba(255, 255, 255, 0.08); color: rgba(255,255,255,0.5); border: 1px solid rgba(255, 255, 255, 0.15); }
        
        .btn-ping-now {
            padding: 4px 10px; font-size: 0.74rem; font-weight: 700;
            background: rgba(37, 99, 235, 0.2); border: 1px solid rgba(37, 99, 235, 0.4);
            color: #93c5fd; border-radius: var(--radius-sm); cursor: pointer;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-ping-now:hover:not(:disabled) {
            background: #2563eb; color: #ffffff; border-color: #1d4ed8;
        }
        .btn-ping-now:disabled { opacity: 0.4; cursor: not-allowed; }

        /* NOC Monitor Modal */
        .noc-modal-dialog { max-width: 980px; width: 95%; max-height: 90vh; }
        .noc-summary-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px; margin-bottom: 16px;
        }
        .noc-stat-box {
            background: #FAFAFA; border: 1px solid var(--border); border-radius: 8px;
            padding: 12px 14px;
        }
        .noc-stat-title { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .noc-stat-val { font-size: 1.35rem; font-weight: 800; font-family: 'IBM Plex Mono', monospace; color: var(--dark); margin: 3px 0; }
        .noc-stat-sub { font-size: 0.7rem; color: var(--text-muted); }
        .noc-control-bar {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
            gap: 10px; margin-bottom: 12px; padding: 10px 14px;
            background: #F3F4F6; border-radius: 8px; border: 1px solid var(--border);
        }
        .noc-table-wrap { max-height: 380px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }

        /* ── CAMERA FOV DIRECTIONAL CONES ── */
        .btn-fov-toggle {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: var(--radius-pill);
            border: 1px solid var(--border); background: var(--surface);
            font-family: var(--font); font-size: 0.75rem; font-weight: 600;
            cursor: pointer; transition: var(--transition); color: var(--text-muted);
        }
        .btn-fov-toggle:hover { border-color: var(--dark); color: var(--text); }
        .btn-fov-toggle.active { background: var(--lime); border-color: var(--dark); color: var(--dark); box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

        .fov-svg-layer {
            position: absolute; inset: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 5; overflow: visible;
        }
        .fov-cone-path {
            transition: fill 0.2s ease, stroke 0.2s ease, opacity 0.2s ease;
            mix-blend-mode: normal;
        }
        .fov-cone-path.active-selected {
            filter: drop-shadow(0 0 8px rgba(185, 255, 102, 0.75));
        }
        .fov-sightline {
            stroke-dasharray: 4 3;
            pointer-events: none;
        }
        .fov-handle {
            pointer-events: all;
            cursor: grab;
            transition: transform 0.15s ease, r 0.15s ease;
        }
        .fov-handle:hover, .fov-handle:active {
            transform: scale(1.35);
            cursor: grabbing;
        }

        .fov-section-heading {
            display: flex; flex-direction: column; gap: 4px;
            padding-top: 14px; margin-top: 10px;
            border-top: 1px dashed rgba(255,255,255,0.14);
        }
        .fov-toggle-switch {
            position: relative; display: inline-block; width: 36px; height: 20px; cursor: pointer;
        }
        .fov-toggle-switch input { opacity: 0; width: 0; height: 0; }
        .fov-toggle-slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: rgba(255,255,255,0.2);
            transition: .25s; border-radius: 20px;
        }
        .fov-toggle-slider:before {
            position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px;
            background-color: white; transition: .25s; border-radius: 50%;
        }
        .fov-toggle-switch input:checked + .fov-toggle-slider { background-color: var(--lime); }
        .fov-toggle-switch input:checked + .fov-toggle-slider:before {
            transform: translateX(16px); background-color: var(--dark);
        }

        .fov-controls-group {
            display: flex; flex-direction: column; gap: 12px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-sm); padding: 12px; margin-top: 6px;
        }
        .fov-val-badge {
            font-size: 0.72rem; font-family: 'IBM Plex Mono', monospace; font-weight: 700;
            color: var(--lime); background: rgba(185,255,102,0.12);
            padding: 2px 7px; border-radius: 4px;
        }
        .fov-slider {
            -webkit-appearance: none; width: 100%; height: 6px; border-radius: 3px;
            background: rgba(255,255,255,0.18); outline: none; margin: 6px 0;
        }
        .fov-slider::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none; width: 16px; height: 16px; border-radius: 50%;
            background: var(--lime); cursor: pointer; border: 2px solid var(--dark);
        }
        .fov-preset-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-top: 4px; }
        .fov-preset-btn, .fov-angle-btn {
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.75); border-radius: 4px; padding: 4px 0;
            font-size: 0.7rem; font-weight: 600; cursor: pointer; transition: var(--transition);
        }
        .fov-preset-btn:hover, .fov-angle-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
        .fov-angle-btn.active { background: var(--lime); color: var(--dark); border-color: var(--lime); }

        /* ── SITE PHOTO ATTACHMENT & LIGHTBOX ── */
        .photo-section-heading {
            display: flex; flex-direction: column; gap: 4px;
            padding-top: 14px; margin-top: 10px;
            border-top: 1px dashed rgba(255,255,255,0.14);
        }
        .photo-count-badge {
            font-size: 0.72rem; font-family: 'IBM Plex Mono', monospace; font-weight: 700;
            color: #c4b5fd; background: rgba(139, 92, 246, 0.2);
            padding: 2px 7px; border-radius: 4px;
        }
        .node-photos-container {
            display: flex; flex-direction: column; gap: 10px;
        }
        .node-photo-gallery {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
            margin-top: 4px;
        }
        .gallery-empty {
            grid-column: 1 / -1;
            padding: 14px 10px; text-align: center;
            background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.12);
            border-radius: var(--radius-sm); font-size: 0.74rem; color: rgba(255,255,255,0.45);
        }
        .gallery-thumb-card {
            position: relative; aspect-ratio: 4/3; border-radius: 6px; overflow: hidden;
            background: #111; border: 1px solid rgba(255,255,255,0.15); cursor: pointer;
            transition: var(--transition);
        }
        .gallery-thumb-card:hover {
            border-color: var(--lime); transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        .gallery-thumb-card img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .thumb-del-btn {
            position: absolute; top: 3px; right: 3px; width: 20px; height: 20px;
            border-radius: 50%; background: rgba(0,0,0,0.7); color: #fff;
            border: 1px solid rgba(255,255,255,0.4); font-size: 11px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; opacity: 0; transition: opacity 0.15s ease;
        }
        .gallery-thumb-card:hover .thumb-del-btn { opacity: 1; }
        .thumb-del-btn:hover { background: #ef4444; border-color: #ef4444; }

        .photo-btn-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 4px;
        }
        .btn-photo-action {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 8px 12px; border-radius: var(--radius-sm);
            background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(139, 92, 246, 0.5);
            color: #ddd6fe; font-family: var(--font); font-size: 0.78rem; font-weight: 600;
            cursor: pointer; transition: var(--transition);
        }
        .btn-photo-action:hover {
            background: #8b5cf6; color: #fff; border-color: #8b5cf6;
        }
        .btn-photo-action.secondary {
            background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); color: rgba(255,255,255,0.85);
        }
        .btn-photo-action.secondary:hover {
            background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3);
        }

        .photo-preview-bar {
            display: flex; gap: 10px; align-items: center;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius-sm); padding: 8px; margin-top: 8px;
        }
        .preview-thumb-box {
            width: 52px; height: 52px; border-radius: 4px; overflow: hidden;
            background: #000; flex-shrink: 0;
        }
        .preview-thumb-box img { width: 100%; height: 100%; object-fit: cover; }
        .preview-meta { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .photo-caption-input {
            width: 100%; padding: 5px 8px; font-size: 0.76rem;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 4px; color: #fff; font-family: var(--font); outline: none;
        }
        .photo-caption-input:focus { border-color: var(--lime); }
        .preview-actions { display: flex; gap: 6px; }
        .btn-upload-confirm {
            padding: 4px 10px; border-radius: 4px; border: none;
            background: var(--lime); color: var(--dark); font-size: 0.72rem; font-weight: 700;
            cursor: pointer; transition: var(--transition);
        }
        .btn-upload-confirm:hover { background: #a8f050; }
        .btn-upload-cancel {
            padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2);
            background: transparent; color: rgba(255,255,255,0.6); font-size: 0.72rem;
            cursor: pointer;
        }
        .btn-upload-cancel:hover { color: #fff; border-color: rgba(255,255,255,0.4); }

        .photo-upload-progress {
            display: flex; align-items: center; gap: 8px; font-size: 0.75rem;
            color: var(--lime); padding: 6px 0;
        }
        .progress-spinner {
            width: 14px; height: 14px; border: 2px solid rgba(185,255,102,0.3);
            border-top-color: var(--lime); border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Lightbox Modal */
        .photo-lightbox-modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.88);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            z-index: 10000; align-items: center; justify-content: center; padding: 24px;
        }
        .photo-lightbox-modal.active { display: flex; }
        .lightbox-dialog {
            position: relative; max-width: 850px; width: 100%; max-height: 90vh;
            background: #191A23; border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius); overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 20px 50px rgba(0,0,0,0.7);
        }
        .lightbox-close-btn {
            position: absolute; top: 12px; right: 12px; z-index: 20; width: 32px; height: 32px;
            border-radius: 50%; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2);
            font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .lightbox-close-btn:hover { background: #ef4444; border-color: #ef4444; }
        .lightbox-image-wrap {
            flex: 1; min-height: 250px; max-height: calc(90vh - 110px); background: #0c0d12;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .lightbox-image-wrap img {
            max-width: 100%; max-height: calc(90vh - 110px); object-fit: contain; display: block;
        }
        .lightbox-footer {
            padding: 16px 20px; background: #191A23; border-top: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .lightbox-dev-badge {
            background: var(--lime); color: var(--dark); font-weight: 700; font-size: 0.78rem;
            padding: 3px 8px; border-radius: 4px; font-family: 'IBM Plex Mono', monospace;
        }
        .lightbox-time { font-size: 0.74rem; color: rgba(255,255,255,0.5); }
        .lightbox-caption { font-size: 0.88rem; color: #fff; font-weight: 500; margin-top: 4px; }
        .lightbox-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
            border-radius: var(--radius-pill); background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2); color: #fff; text-decoration: none;
            font-size: 0.78rem; font-weight: 600; transition: var(--transition);
        }
        .lightbox-btn:hover { background: rgba(255,255,255,0.2); border-color: #fff; }

        .table-photo-badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px;
            border-radius: 4px; font-size: 0.68rem; font-weight: 700;
            background: #ede9fe; color: #6d28d9; margin-left: 6px; cursor: pointer;
            transition: var(--transition);
        }
        .table-photo-badge:hover { background: #ddd6fe; color: #5b21b6; }

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
            <a class="back-btn" href="dashboard.php<?= !empty($blueprint['project_id']) ? '?project=' . $blueprint['project_id'] : '' ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Dashboard
            </a>
            <span class="breadcrumb-sep">/</span>
            <?php if (!empty($blueprint['client_name'])): ?>
                <a href="dashboard.php?project=<?= $blueprint['project_id'] ?>" class="nav-project-crumb" title="Project: <?= htmlspecialchars($blueprint['project_name']) ?>">
                    <span class="proj-code-pill"><?= htmlspecialchars($blueprint['project_code']) ?></span>
                    <span class="proj-client-text"><?= htmlspecialchars($blueprint['client_name']) ?></span>
                </a>
                <span class="breadcrumb-sep">/</span>
            <?php endif; ?>
            <span class="bp-title"><?= htmlspecialchars($blueprint['name']) ?></span>
        </div>
        <div class="navbar-right">
            <a href="handover_report.php?id=<?= $blueprintId ?>" target="_blank" class="btn btn-sm btn-lime" title="Generate MEMIS T&C As-Built Handover Report">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                As-Built Report
            </a>
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
        <div class="stat-card active" data-filter="all" title="Click to show all nodes"><span class="stat-label">Total Nodes</span><span class="stat-value" id="statTotal">0</span></div>
        <div class="stat-card" data-filter="Live" title="Click to filter Live nodes"><span class="stat-label"><span class="stat-dot" style="background:#22c55e"></span>Live</span><span class="stat-value" id="statLive">0</span></div>
        <div class="stat-card" data-filter="Hardware Mounted" title="Click to filter Mounted nodes"><span class="stat-label"><span class="stat-dot" style="background:#f59e0b"></span>Mounted</span><span class="stat-value" id="statMounted">0</span></div>
        <div class="stat-card" data-filter="Cable Pulled" title="Click to filter Cable Pulled nodes"><span class="stat-label"><span class="stat-dot" style="background:#3b82f6"></span>Cable</span><span class="stat-value" id="statCable">0</span></div>
        <div class="stat-card" data-filter="Planned" title="Click to filter Planned nodes"><span class="stat-label"><span class="stat-dot" style="background:#ef4444"></span>Planned</span><span class="stat-value" id="statPlanned">0</span></div>
    </div>

    <!-- ═══ MAP + SIDE PANEL ═══ -->
    <div class="app-layout">
        <div class="map-container">
            <div class="map-toolbar">
                <div class="map-toolbar-left">
                    <span style="font-weight:700;font-size:0.88rem;">Floorplan</span>
                    <div class="filter-pills">
                        <button class="filter-pill active" data-filter="all">All</button>
                        <button class="filter-pill" data-filter="Live">Live</button>
                        <button class="filter-pill" data-filter="Hardware Mounted">Mounted</button>
                        <button class="filter-pill" data-filter="Cable Pulled">Cable</button>
                        <button class="filter-pill" data-filter="Planned">Planned</button>
                    </div>
                    <div class="blueprint-search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="mapSearchInput" placeholder="Search status, notes, tag, IP..." autocomplete="off">
                        <button id="mapSearchClear" type="button" class="search-clear-btn" style="display:none;" title="Clear search">✕</button>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="btnToggleFov" class="btn-fov-toggle active" title="Toggle camera field-of-view directional cones">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><circle cx="12" cy="13" r="4"/></svg>
                        <span id="fovToggleText">FoV: ON</span>
                    </button>
                    <button type="button" id="btnToggleCables" class="btn-cable-toggle active" title="Toggle structured cable runs &amp; measured lengths">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M4 12h8m4 0h4M12 4v8m0 0v8"/><circle cx="12" cy="12" r="3"/><circle cx="4" cy="12" r="2"/><circle cx="20" cy="12" r="2"/></svg>
                        <span id="cableToggleText">Cables: ON</span>
                    </button>
                    <button type="button" id="btnToggleLivePing" class="btn-live-ping" onclick="toggleLivePingMonitoring()" title="Real-time Network Ping &amp; Heartbeat Monitoring">
                        <span class="ping-radar-dot"></span>
                        <span id="livePingBtnText">Live Ping: OFF</span>
                    </button>
                    <button type="button" id="btnOpenNocModal" class="btn-noc-modal" onclick="openNocModal()" title="View Network Operations Center (NOC) Device Monitor">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        <span>NOC Monitor</span>
                    </button>
                    <button type="button" id="btnOpenCableSchedule" class="btn-cable-schedule" onclick="openCableScheduleModal()" title="View Cable Pulling Schedule &amp; BOM Material Breakdown">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Cable Schedule</span>
                    </button>
                    <button type="button" id="btnOpenScaleModal" class="btn-scale-calibrate" onclick="openScaleModal()" title="Calibrate floorplan physical dimensions (Width &amp; Height in meters)">
                        <span>📐 <span id="toolbarScaleLabel"><?= floatval($blueprint['real_width_meters'] ?? 60.0) ?>m × <?= floatval($blueprint['real_height_meters'] ?? 40.0) ?>m</span></span>
                    </button>
                    <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;" id="pinCount">0 pins</span>
                </div>
            </div>
            <div class="blueprint-area" id="blueprintArea">
                <?php if ($imageExists): ?>
                    <div class="blueprint-stage" id="blueprintStage">
                        <img src="uploads/blueprints/<?= htmlspecialchars($blueprint['image_filename']) ?>" alt="Blueprint" id="blueprintImg">
                        <svg id="cableSvgLayer" class="cable-svg-layer"></svg>
                        <svg id="fovSvgLayer" class="fov-svg-layer"></svg>
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
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <h3 id="panelTitle">Node Inspector</h3>
                    <p id="panelSubtitle">Click the map to drop a pin</p>
                </div>
                <button type="button" id="btnPanelClose" class="panel-close-btn" onclick="resetPanel()" title="Cancel / Clear selection" style="display:none;">✕</button>
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
                <div class="form-group">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                        <label style="margin-bottom:0;">IP Address</label>
                        <span id="inspectorPingBadge" class="ping-status-badge unknown">Unknown</span>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <input type="text" id="inputIp" placeholder="e.g. 192.168.1.100" disabled style="flex:1;" oninput="onIpAddressInput(this.value)">
                        <button type="button" id="btnPingNodeNow" class="btn-ping-now" onclick="pingCurrentNode()" disabled title="Test device response time &amp; packet reply">
                            <span id="btnPingNodeText">⚡ Ping</span>
                        </button>
                    </div>
                    <div id="inspectorPingMeta" style="font-size:0.7rem; color:var(--text-muted); margin-top:4px; display:none;">
                        <span id="inspectorPingDetails">—</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="inputStatus" disabled>
                        <option value="Planned">🔴 Planned</option><option value="Cable Pulled">🔵 Cable Pulled</option>
                        <option value="Hardware Mounted">🟡 Hardware Mounted</option><option value="Live">🟢 Live</option>
                        <option value="Troubleshoot">🟣 Troubleshoot</option>
                    </select>
                </div>
                <div class="form-group"><label>Notes</label><textarea id="inputNotes" placeholder="Field notes..." disabled></textarea></div>

                <!-- Camera Field-of-View (FoV) Coverage Section -->
                <div class="fov-section-heading">
                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                        <span class="tc-pill" style="background:#0284c7; color:#fff;">📷 Camera FoV Cone</span>
                        <label class="fov-toggle-switch" title="Enable or disable camera FoV coverage cone">
                            <input type="checkbox" id="inputFovEnabled" disabled>
                            <span class="fov-toggle-slider"></span>
                        </label>
                    </div>
                    <span class="tc-desc">Directional CCTV Coverage &amp; Blind Spots</span>
                </div>

                <div id="fovControlsGroup" class="fov-controls-group" style="display:none;">
                    <!-- Aim Direction -->
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label>Aim Direction (Rotation)</label>
                            <span id="fovDirectionVal" class="fov-val-badge">0° (North)</span>
                        </div>
                        <input type="range" id="inputFovDirection" min="0" max="359" value="0" class="fov-slider" disabled>
                        <div class="fov-preset-row">
                            <button type="button" class="fov-preset-btn" data-dir="0">↑ N 0°</button>
                            <button type="button" class="fov-preset-btn" data-dir="90">→ E 90°</button>
                            <button type="button" class="fov-preset-btn" data-dir="180">↓ S 180°</button>
                            <button type="button" class="fov-preset-btn" data-dir="270">← W 270°</button>
                        </div>
                    </div>

                    <!-- Lens Spread (FoV) -->
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label>Lens Spread (FoV Angle)</label>
                            <span id="fovAngleVal" class="fov-val-badge">90° (Standard)</span>
                        </div>
                        <input type="range" id="inputFovAngle" min="30" max="180" step="5" value="90" class="fov-slider" disabled>
                        <div class="fov-preset-row">
                            <button type="button" class="fov-angle-btn" data-angle="60">60° Tele</button>
                            <button type="button" class="fov-angle-btn active" data-angle="90">90° Std</button>
                            <button type="button" class="fov-angle-btn" data-angle="120">120° Wide</button>
                            <button type="button" class="fov-angle-btn" data-angle="360">360° Omni</button>
                        </div>
                    </div>

                    <!-- Coverage Range / Distance -->
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label>Coverage Throw Distance</label>
                            <span id="fovDistanceVal" class="fov-val-badge">15% (~15m)</span>
                        </div>
                        <input type="range" id="inputFovDistance" min="5" max="40" value="15" class="fov-slider" disabled>
                    </div>
                </div>

                <!-- ELV Testing & Commissioning (T&C) Section -->
                <div class="tc-heading">
                    <span class="tc-pill">T&amp;C Commissioning</span>
                    <span class="tc-desc">MEMIS Handover Data</span>
                </div>
                <div class="form-group"><label>Serial Number (S/N)</label><input type="text" id="inputSerial" placeholder="e.g. DS-2CD2143G2-..." disabled></div>
                <div class="form-group"><label>MAC Address</label><input type="text" id="inputMac" placeholder="e.g. BC:54:51:3A:89:1F" disabled></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div class="form-group"><label>Switch ID</label><input type="text" id="inputSwitch" placeholder="e.g. SW-MDF-01" disabled></div>
                    <div class="form-group"><label>Port No</label><input type="text" id="inputPort" placeholder="e.g. P01 (PoE)" disabled></div>
                </div>
                <div class="form-group"><label>Structured Cable Tag</label><input type="text" id="inputCable" placeholder="e.g. C-L2-014" disabled oninput="syncCableTagInput(this.value)"></div>
                <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:8px;">
                    <div class="form-group"><label>Tested By</label><input type="text" id="inputTestedBy" placeholder="Technician name" disabled></div>
                    <div class="form-group"><label>Commission Date</label><input type="date" id="inputCommissionDate" disabled></div>
                </div>

                <!-- Structured Cabling & Conduit Routing Section -->
                <div class="cable-section-heading">
                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                        <span class="tc-pill" style="background:#2563eb; color:#fff;">🖧 Cable Route &amp; Length</span>
                        <span id="cableStatusBadge" class="tag-mono" style="font-size:0.68rem; background:rgba(255,255,255,0.08); color:#93c5fd;">No Cable</span>
                    </div>
                    <span class="tc-desc">Laluan Kabel &amp; Ukuran Panjang Sebenar (Meters)</span>
                </div>

                <div id="cableInspectorGroup" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <div class="form-group">
                        <label>Terminated At (Switch / Rack / Device)</label>
                        <select id="inputCableToNode" disabled onchange="onCableDestinationChanged()">
                            <option value="">-- No Direct Cable Link --</option>
                        </select>
                    </div>

                    <div id="cableDetailsSubgroup" style="display:none; flex-direction:column; gap:8px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div class="form-group">
                                <label>Cable Type</label>
                                <select id="inputCableType" disabled onchange="updateCurrentCableDetails()">
                                    <option value="Cat6 UTP">Cat6 UTP (Blue)</option>
                                    <option value="Cat6A STP">Cat6A STP (Purple)</option>
                                    <option value="Fiber 4-Core">Fiber 4-Core (Orange)</option>
                                    <option value="RG59 Coaxial">RG59 Coaxial (Black)</option>
                                    <option value="2-Core Shielded">2-Core Shielded (Yellow)</option>
                                    <option value="Power 3-Core">Power 3-Core (Red)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Conduit / Trunking</label>
                                <select id="inputConduitType" disabled onchange="updateCurrentCableDetails()">
                                    <option value="20mm PVC Conduit">20mm PVC Conduit</option>
                                    <option value="25mm GI Conduit">25mm GI Conduit</option>
                                    <option value="Cable Tray / Trunking">Cable Tray / Trunking</option>
                                    <option value="Underfloor Trench">Underfloor Trench</option>
                                    <option value="Exposed / Wall Clamped">Exposed / Wall Clamped</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div class="form-group">
                                <label>Slack Drop (Ceiling + Loop)</label>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <input type="number" id="inputSlackMeters" value="3.0" step="0.5" min="0" max="20" disabled onchange="updateCurrentCableDetails()" style="flex:1;">
                                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">m</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Cable Status</label>
                                <select id="inputCableStatus" disabled onchange="updateCurrentCableDetails()">
                                    <option value="Planned">Planned</option>
                                    <option value="Cable Pulled">Cable Pulled</option>
                                    <option value="Hardware Mounted">Terminated</option>
                                    <option value="Live">Live / Tested</option>
                                </select>
                            </div>
                        </div>

                        <div class="cable-calc-box">
                            <div>
                                <div class="cable-calc-label">Total Measured Length</div>
                                <div style="font-size:0.7rem; color:rgba(255,255,255,0.5);">Run + Drop Slack</div>
                            </div>
                            <div class="cable-calc-val"><span id="cableLiveLengthVal">0.0</span> m</div>
                        </div>

                        <div class="cable-action-btns">
                            <button type="button" id="btnRouteMode" class="btn-cable-act primary" onclick="toggleRouteBendsMode()" title="Click on the floorplan to add corridor bend corners">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
                                <span>⚡ Route Bends</span>
                            </button>
                            <button type="button" class="btn-cable-act" onclick="autoOrthogonalRoute()" title="Auto route along clean 90-degree industrial hallway angles">
                                <span>📐 90° Ortho</span>
                            </button>
                            <button type="button" class="btn-cable-act" onclick="straightenCableRoute()" title="Reset to straight line path">
                                <span>Straighten</span>
                            </button>
                            <button type="button" class="btn-cable-act danger" onclick="deleteActiveCable()" title="Remove this cable link">
                                <span>✕ Remove</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Site Photo Evidence Section -->
                <div class="photo-section-heading">
                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                        <span class="tc-pill" style="background:#8b5cf6; color:#fff;">📸 Site Photo Evidence</span>
                        <span id="photoBadgeCount" class="photo-count-badge">0 photos</span>
                    </div>
                    <span class="tc-desc">On-Site Visual Verification &amp; As-Built Handover Records</span>
                </div>

                <div id="nodePhotosContainer" class="node-photos-container">
                    <div id="nodePhotoGallery" class="node-photo-gallery"></div>

                    <!-- Upload / Camera Capture Controls -->
                    <div class="photo-upload-box" id="photoUploadBox">
                        <input type="file" id="nodePhotoFileInput" accept="image/*" style="display:none;" onchange="handlePhotoSelected(event)">
                        <div class="photo-btn-row">
                            <button type="button" class="btn-photo-action" id="btnTakeCameraPhoto" onclick="triggerCameraCapture()" title="Use mobile camera to snap physical node">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><circle cx="12" cy="13" r="4"/></svg>
                                <span>Take Photo</span>
                            </button>
                            <button type="button" class="btn-photo-action secondary" id="btnUploadFilePhoto" onclick="triggerFileSelect()" title="Browse photo from device">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span>Upload File</span>
                            </button>
                        </div>

                        <!-- Selected File Preview & Caption -->
                        <div id="photoPreviewBar" class="photo-preview-bar" style="display:none;">
                            <div class="preview-thumb-box">
                                <img id="photoTempPreview" src="" alt="Preview">
                            </div>
                            <div class="preview-meta">
                                <input type="text" id="photoCaptionInput" placeholder="Add caption (e.g. Ceiling mount tag)..." class="photo-caption-input">
                                <div class="preview-actions">
                                    <button type="button" id="btnUploadPhoto" class="btn-upload-confirm" onclick="uploadSelectedPhoto()">Upload Evidence</button>
                                    <button type="button" class="btn-upload-cancel" onclick="cancelPhotoSelection()">Cancel</button>
                                </div>
                            </div>
                        </div>

                        <div id="photoUploadProgress" class="photo-upload-progress" style="display:none;">
                            <div class="progress-spinner"></div>
                            <span>Uploading site evidence...</span>
                        </div>
                    </div>
                </div>
                </div><!-- end #nodeFormFields -->
            </div>
            <div class="panel-footer">
                <button class="btn btn-lime btn-disabled-muted" id="btnSave" onclick="saveNode()" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Pin a location first
                </button>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancelPin" onclick="resetPanel()" style="display:none; flex:1;">
                        Cancel
                    </button>
                    <button class="btn btn-danger btn-sm" id="btnDelete" onclick="deleteNode()" style="display:none; flex:1;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Delete Node
                    </button>
                </div>
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
                    <input type="text" id="searchInput" placeholder="Search by status (Live, Mounted...), notes, device, IP...">
                </div>
                <span style="font-size:0.78rem;color:var(--text-muted);" id="tableCount">0 nodes</span>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Device Tag</th>
                            <th>Category</th>
                            <th>IP / MAC Address</th>
                            <th>Switch &amp; Port</th>
                            <th>Cable Tag</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Site Notes</th>
                            <th>Tested By</th>
                            <th>Date</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody id="nodeTableBody"><tr><td colspan="11" style="text-align:center;padding:40px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ PHOTO LIGHTBOX MODAL ═══ -->
    <div id="photoLightboxModal" class="photo-lightbox-modal" onclick="closePhotoLightbox(event)">
        <div class="lightbox-dialog" onclick="event.stopPropagation()">
            <button class="lightbox-close-btn" onclick="closePhotoLightbox()" title="Close viewer">✕</button>
            <div class="lightbox-image-wrap">
                <img id="lightboxImg" src="" alt="Site photo">
            </div>
            <div class="lightbox-footer">
                <div class="lightbox-info">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span id="lightboxDevBadge" class="lightbox-dev-badge">CAM-01</span>
                        <span id="lightboxTime" class="lightbox-time">2026-11-05 17:30</span>
                    </div>
                    <div id="lightboxCaption" class="lightbox-caption">Ceiling mount &amp; conduit tag</div>
                </div>
                <div class="lightbox-actions">
                    <a id="lightboxDownloadBtn" href="" download class="lightbox-btn" title="Download Full Resolution">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Full Image
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: CABLE SCHEDULE & BOM ESTIMATOR ═══ -->
    <div id="cableScheduleModal" class="modal-overlay">
        <div class="modal cable-modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 style="font-size:1.2rem; font-weight:800; display:flex; align-items:center; gap:8px;">
                        <span>🖧 Structured Cabling Schedule &amp; BOM</span>
                        <span class="tag-mono" id="bomScaleTag" style="font-size:0.75rem; background:#E5E7EB; color:#111;">60m × 40m</span>
                    </h3>
                    <p style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">Automated length estimation &amp; box consumption schedule for sub-contractors and ELV procurement.</p>
                </div>
                <button class="modal-close" onclick="closeModal('cableScheduleModal')">&times;</button>
            </div>
            <div class="modal-body" style="padding-top:16px;">
                <!-- BOM Summary Cards -->
                <div class="bom-cards-grid" id="bomCardsGrid">
                    <div class="bom-card">
                        <div class="bom-title">Total Cable Measured</div>
                        <div class="bom-val" id="bomTotalMeters">0.0 m</div>
                        <div class="bom-sub" id="bomTotalRuns">0 cable runs</div>
                    </div>
                    <div class="bom-card">
                        <div class="bom-title">Cat6 UTP (305m Boxes)</div>
                        <div class="bom-val" id="bomCat6Boxes">0 Box</div>
                        <div class="bom-sub" id="bomCat6Sub">0m installed</div>
                    </div>
                    <div class="bom-card">
                        <div class="bom-title">Cat6A / Shielded Cable</div>
                        <div class="bom-val" id="bomOtherCables">0.0 m</div>
                        <div class="bom-sub" id="bomOtherSub">Specialty conduits</div>
                    </div>
                </div>

                <!-- Cable Table -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-size:0.85rem; font-weight:700;">Detailed Cable Pulling List</div>
                    <button type="button" class="btn btn-sm btn-lime" onclick="exportCableScheduleCsv()" style="font-size:0.78rem; padding:5px 14px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV (Excel)
                    </button>
                </div>

                <div class="cable-schedule-table-wrap">
                    <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                        <thead>
                            <tr style="background:#F9FAFB; border-bottom:1.5px solid var(--border); text-align:left;">
                                <th style="padding:10px 12px;">Tag</th>
                                <th style="padding:10px 12px;">From (Source)</th>
                                <th style="padding:10px 12px;">To (Termination)</th>
                                <th style="padding:10px 12px;">Cable Spec</th>
                                <th style="padding:10px 12px;">Conduit</th>
                                <th style="padding:10px 12px; text-align:right;">Run (m)</th>
                                <th style="padding:10px 12px; text-align:right;">Slack (m)</th>
                                <th style="padding:10px 12px; text-align:right;">Total (m)</th>
                                <th style="padding:10px 12px; text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="cableScheduleTableBody">
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">Loading cable schedule...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="padding-top:12px;">
                <button class="btn btn-sm" onclick="closeModal('cableScheduleModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: SCALE CALIBRATION ═══ -->
    <div id="scaleCalibrationModal" class="modal-overlay">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h3>Floorplan Scale Calibration</h3>
                <button class="modal-close" onclick="closeModal('scaleCalibrationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:0.84rem; color:var(--text-muted); margin-bottom:16px;">
                    Set the physical real-world dimensions of this floorplan. SiteDeploy automatically calculates the exact horizontal cable run length in meters.
                </p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div class="form-group">
                        <label>Physical Width (Meters) *</label>
                        <input type="number" id="inputScaleWidth" step="0.5" min="1" max="1000" placeholder="e.g. 60">
                    </div>
                    <div class="form-group">
                        <label>Physical Height (Meters) *</label>
                        <input type="number" id="inputScaleHeight" step="0.5" min="1" max="1000" placeholder="e.g. 40">
                    </div>
                </div>

                <label style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:6px;">Quick Dimension Presets</label>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:6px; margin-bottom:16px;">
                    <button type="button" class="btn btn-sm btn-ghost" onclick="applyScalePreset(30, 20)" style="font-size:0.75rem; border:1px solid var(--border); padding:6px 4px;">Small Office<br><strong>30m × 20m</strong></button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="applyScalePreset(60, 40)" style="font-size:0.75rem; border:1px solid var(--border); padding:6px 4px;">Commercial Floor<br><strong>60m × 40m</strong></button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="applyScalePreset(120, 70)" style="font-size:0.75rem; border:1px solid var(--border); padding:6px 4px;">Warehouse / Plant<br><strong>120m × 70m</strong></button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm" onclick="closeModal('scaleCalibrationModal')">Cancel</button>
                <button class="btn btn-lime btn-sm" onclick="saveScaleCalibration()">Save &amp; Recalculate Cables</button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: NOC COMMAND CENTER & LIVE PING MONITOR ═══ -->
    <div id="nocMonitorModal" class="modal-overlay">
        <div class="modal noc-modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 style="font-size:1.2rem; font-weight:800; display:flex; align-items:center; gap:8px;">
                        <span>📡 Network Operations Command Center (NOC)</span>
                        <span id="nocLiveBadge" class="ping-status-badge unknown" style="font-size:0.7rem;">Live Monitor Ready</span>
                    </h3>
                    <p style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">Real-time CCTV &amp; ELV device heartbeat, ICMP ping latency, and packet response monitor.</p>
                </div>
                <button class="modal-close" onclick="closeModal('nocMonitorModal')">&times;</button>
            </div>
            <div class="modal-body" style="padding-top:14px;">
                <!-- Summary Metrics -->
                <div class="noc-summary-grid">
                    <div class="noc-stat-box">
                        <div class="noc-stat-title">Network Devices</div>
                        <div class="noc-stat-val" id="nocTotalDevices">0</div>
                        <div class="noc-stat-sub" id="nocIpConfigured">0 IP configured</div>
                    </div>
                    <div class="noc-stat-box">
                        <div class="noc-stat-title">Online &amp; Responsive</div>
                        <div class="noc-stat-val" id="nocOnlineCount" style="color:#16a34a;">0</div>
                        <div class="noc-stat-sub" id="nocOnlinePercent">0% health rate</div>
                    </div>
                    <div class="noc-stat-box">
                        <div class="noc-stat-title">Offline / Packet Loss</div>
                        <div class="noc-stat-val" id="nocOfflineCount" style="color:#dc2626;">0</div>
                        <div class="noc-stat-sub" id="nocOfflineSub">No response</div>
                    </div>
                    <div class="noc-stat-box">
                        <div class="noc-stat-title">Average Round-Trip</div>
                        <div class="noc-stat-val" id="nocAvgLatency" style="color:#2563eb;">0.0 ms</div>
                        <div class="noc-stat-sub">ICMP response time</div>
                    </div>
                </div>

                <!-- Controls Bar -->
                <div class="noc-control-bar">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm btn-lime" id="btnNocPingAll" onclick="pingAllDevices()" style="font-size:0.78rem; padding:6px 14px; font-weight:700;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            <span id="btnNocPingAllText">Ping All Devices Now</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-ghost" id="btnNocFilterOffline" onclick="filterMapOfflineOnly()" style="font-size:0.75rem; border:1px solid var(--border); padding:6px 10px; background:#fff;">
                            <span>🔴 Show Offline on Floorplan</span>
                        </button>
                    </div>

                    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:0.78rem;">
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
                            <input type="checkbox" id="nocSoundAlert" checked style="accent-color:#10b981;">
                            <span>🔊 Sound Alert (Beep)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer;" title="Simulate realistic CCTV camera pings for client presentation when offline">
                            <input type="checkbox" id="nocDemoMode" onchange="toggleDemoPingMode(this.checked)" style="accent-color:#2563eb;">
                            <span>🧪 Presentation Demo</span>
                        </label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span>Auto Refresh:</span>
                            <select id="nocAutoInterval" onchange="changeNocInterval(this.value)" style="padding:4px 8px; border-radius:6px; border:1px solid var(--border); font-size:0.75rem; font-family:inherit;">
                                <option value="0">Manual</option>
                                <option value="15000">Every 15s</option>
                                <option value="30000" selected>Every 30s</option>
                                <option value="60000">Every 60s</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Device Table -->
                <div class="noc-table-wrap">
                    <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                        <thead>
                            <tr style="background:#F9FAFB; border-bottom:1.5px solid var(--border); text-align:left;">
                                <th style="padding:10px 12px;">Device ID</th>
                                <th style="padding:10px 12px;">Type</th>
                                <th style="padding:10px 12px;">IP Address</th>
                                <th style="padding:10px 12px;">Switch / Port</th>
                                <th style="padding:10px 12px; text-align:center;">Network Status</th>
                                <th style="padding:10px 12px; text-align:right;">Latency (ms)</th>
                                <th style="padding:10px 12px; text-align:right;">Last Tested</th>
                                <th style="padding:10px 12px; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="nocDeviceTableBody">
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">Loading NOC device inventory...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
                <span id="nocLastScanText" style="font-size:0.72rem; color:var(--text-muted);">Last scan: Never</span>
                <button class="btn btn-sm" onclick="closeModal('nocMonitorModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Floating Routing Mode Banner -->
    <div id="routingBanner" class="routing-banner" style="display:none;">
        <span style="font-size:1.1rem;">⚡</span>
        <span>Click on the floorplan to drop bend waypoints around walls &amp; corridors.</span>
        <button type="button" class="btn-finish-routing" onclick="toggleRouteBendsMode(false)">✓ Finish Routing</button>
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
        let currentSearchQuery = '';
        let isNewPin = false;
        let fovLayerVisible = true;
        let isDraggingFovHandle = false;
        let selectedPhotoFile = null;

        // Structured Cable & Scale variables
        let bpRealWidthM   = <?= floatval($blueprint['real_width_meters'] ?? 60.0) ?>;
        let bpRealHeightM  = <?= floatval($blueprint['real_height_meters'] ?? 40.0) ?>;
        let cables = [];
        let activeCable = null;
        let cablesVisible = true;
        let isRoutingMode = false;
        let isDraggingWaypoint = false;

        // Live Ping & NOC Monitoring variables
        let isLivePingActive = false;
        let livePingTimer = null;
        let livePingIntervalMs = 15000;
        let nocDemoMode = false;
        let isBatchPinging = false;

        const PIN_SVG = `<svg viewBox="0 0 32 40" fill="none"><path d="M16 0C7.2 0 0 7.2 0 16c0 12 16 24 16 24s16-12 16-24C32 7.2 24.8 0 16 0z" fill="#ef4444"/><circle cx="16" cy="15" r="7" fill="white" opacity="0.9"/><circle cx="16" cy="15" r="3" fill="#ef4444"/></svg>`;

        document.addEventListener('DOMContentLoaded', () => {
            fetchNodes();
            if (IMAGE_EXISTS) setupBlueprintClick();
            setupFilters();
            setupFovEventListeners();
            setupCableEventListeners();
            loadCables();
        });

        // ── CAMERA UTILITY HELPERS ──
        function isCameraType(t) {
            if (!t) return false;
            const s = t.toLowerCase();
            return s.includes('dome') || s.includes('bullet') || s.includes('ptz') || s.includes('camera') || s.includes('cctv');
        }

        function getFovColors(status, isSelected) {
            if (isSelected) {
                return { fill: 'rgba(185, 255, 102, 0.32)', stroke: '#B9FF66' };
            }
            switch(status) {
                case 'Live':
                    return { fill: 'rgba(34, 197, 94, 0.22)', stroke: 'rgba(34, 197, 94, 0.85)' };
                case 'Hardware Mounted':
                    return { fill: 'rgba(245, 158, 11, 0.22)', stroke: 'rgba(245, 158, 11, 0.85)' };
                case 'Cable Pulled':
                    return { fill: 'rgba(59, 130, 246, 0.22)', stroke: 'rgba(59, 130, 246, 0.85)' };
                case 'Planned':
                    return { fill: 'rgba(239, 68, 68, 0.22)', stroke: 'rgba(239, 68, 68, 0.85)' };
                case 'Troubleshoot':
                    return { fill: 'rgba(168, 85, 247, 0.22)', stroke: 'rgba(168, 85, 247, 0.85)' };
                default:
                    return { fill: 'rgba(185, 255, 102, 0.22)', stroke: 'rgba(185, 255, 102, 0.85)' };
            }
        }

        function updateFovLabels() {
            const dir = parseInt(document.getElementById('inputFovDirection').value) || 0;
            const angle = parseInt(document.getElementById('inputFovAngle').value) || 90;
            const dist = parseInt(document.getElementById('inputFovDistance').value) || 15;

            // Direction compass text
            let comp = 'North';
            if (dir > 22 && dir <= 67) comp = 'NE';
            else if (dir > 67 && dir <= 112) comp = 'East';
            else if (dir > 112 && dir <= 157) comp = 'SE';
            else if (dir > 157 && dir <= 202) comp = 'South';
            else if (dir > 202 && dir <= 247) comp = 'SW';
            else if (dir > 247 && dir <= 292) comp = 'West';
            else if (dir > 292 && dir <= 337) comp = 'NW';
            document.getElementById('fovDirectionVal').textContent = `${dir}° (${comp})`;

            // Lens angle text
            let lens = 'Standard';
            if (angle <= 65) lens = 'Narrow / Tele';
            else if (angle <= 95) lens = 'Standard';
            else if (angle <= 140) lens = 'Wide Angle';
            else if (angle >= 350) lens = '360° Fisheye';
            document.getElementById('fovAngleVal').textContent = `${angle}° (${lens})`;

            // Distance text
            document.getElementById('fovDistanceVal').textContent = `${dist}% (~${Math.round(dist * 1.0)}m)`;

            // Active preset highlights
            document.querySelectorAll('.fov-preset-btn').forEach(b => {
                b.classList.toggle('active', parseInt(b.dataset.dir) === dir);
            });
            document.querySelectorAll('.fov-angle-btn').forEach(b => {
                b.classList.toggle('active', parseInt(b.dataset.angle) === angle);
            });
        }

        // ── RENDER FOV DIRECTIONAL CONES ──
        function renderFovCones() {
            const svg = document.getElementById('fovSvgLayer');
            if (!svg) return;
            svg.innerHTML = '';
            if (!fovLayerVisible || !IMAGE_EXISTS) return;

            const img = document.getElementById('blueprintImg');
            const vw = BP_WIDTH || (img && img.naturalWidth) || 1600;
            const vh = BP_HEIGHT || (img && img.naturalHeight) || 1000;
            svg.setAttribute('viewBox', `0 0 ${vw} ${vh}`);

            nodes.forEach(node => {
                if (!nodeMatchesSearchAndFilter(node)) return;

                const isSelected = selectedPin && selectedPin.dataset.deviceId === node.device_id;
                let fovEnabled = parseInt(node.fov_enabled || 0) === 1;
                let dir   = parseInt(node.fov_direction || 0);
                let angle = parseInt(node.fov_angle || 90);
                let dist  = parseInt(node.fov_distance || 15);

                // Use live inspector values if selected
                if (isSelected) {
                    fovEnabled = document.getElementById('inputFovEnabled').checked;
                    dir   = parseInt(document.getElementById('inputFovDirection').value) || 0;
                    angle = parseInt(document.getElementById('inputFovAngle').value) || 90;
                    dist  = parseInt(document.getElementById('inputFovDistance').value) || 15;
                }

                if (!fovEnabled) return;

                const cx = (parseFloat(node.pos_x) / 100) * vw;
                const cy = (parseFloat(node.pos_y) / 100) * vh;
                const r  = (dist / 100) * vw;
                const colors = getFovColors(node.status, isSelected);

                drawSingleFovCone(svg, cx, cy, r, dir, angle, colors, isSelected, vw, vh);
            });

            // If placing a new pin with FoV enabled (only if temp pin is present on stage and matches active filter)
            const tempPinEl = document.querySelector('.pin.temp-pin');
            if (isNewPin && tempPinEl && currentPosX !== null && currentPosY !== null && document.getElementById('inputFovEnabled').checked) {
                const curStatus = document.getElementById('inputStatus').value || 'Planned';
                if (activeFilter === 'all' || activeFilter === curStatus) {
                    const cx = (currentPosX / 100) * vw;
                    const cy = (currentPosY / 100) * vh;
                    const dir   = parseInt(document.getElementById('inputFovDirection').value) || 0;
                    const angle = parseInt(document.getElementById('inputFovAngle').value) || 90;
                    const dist  = parseInt(document.getElementById('inputFovDistance').value) || 15;
                    const r = (dist / 100) * vw;
                    const colors = getFovColors(curStatus, true);
                    drawSingleFovCone(svg, cx, cy, r, dir, angle, colors, true, vw, vh);
                }
            }
        }

        function drawSingleFovCone(svg, cx, cy, r, dir, angle, colors, isSelected, vw, vh) {
            if (angle >= 360) {
                // 360 Omnidirectional
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('cx', cx.toFixed(1));
                circle.setAttribute('cy', cy.toFixed(1));
                circle.setAttribute('r', r.toFixed(1));
                circle.setAttribute('fill', colors.fill);
                circle.setAttribute('stroke', colors.stroke);
                circle.setAttribute('stroke-width', isSelected ? '2.5' : '1.5');
                circle.setAttribute('class', 'fov-cone-path' + (isSelected ? ' active-selected' : ''));
                svg.appendChild(circle);
                return;
            }

            const rad1 = (dir - (angle / 2)) * (Math.PI / 180);
            const rad2 = (dir + (angle / 2)) * (Math.PI / 180);
            const radM = dir * (Math.PI / 180);

            const x1 = cx + r * Math.sin(rad1);
            const y1 = cy - r * Math.cos(rad1);
            const x2 = cx + r * Math.sin(rad2);
            const y2 = cy - r * Math.cos(rad2);
            const xm = cx + r * Math.sin(radM);
            const ym = cy - r * Math.cos(radM);

            const largeArc = angle > 180 ? 1 : 0;
            const d = `M ${cx.toFixed(1)} ${cy.toFixed(1)} L ${x1.toFixed(1)} ${y1.toFixed(1)} A ${r.toFixed(1)} ${r.toFixed(1)} 0 ${largeArc} 1 ${x2.toFixed(1)} ${y2.toFixed(1)} Z`;

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('fill', colors.fill);
            path.setAttribute('stroke', colors.stroke);
            path.setAttribute('stroke-width', isSelected ? '2.5' : '1.5');
            path.setAttribute('class', 'fov-cone-path' + (isSelected ? ' active-selected' : ''));
            svg.appendChild(path);

            // Boresight dashed sightline
            const ray = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            ray.setAttribute('x1', cx.toFixed(1));
            ray.setAttribute('y1', cy.toFixed(1));
            ray.setAttribute('x2', xm.toFixed(1));
            ray.setAttribute('y2', ym.toFixed(1));
            ray.setAttribute('stroke', colors.stroke);
            ray.setAttribute('stroke-width', isSelected ? '1.8' : '1.2');
            ray.setAttribute('class', 'fov-sightline');
            svg.appendChild(ray);

            // Interactive rotation handle at cone tip
            if (isSelected) {
                const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                handle.setAttribute('cx', xm.toFixed(1));
                handle.setAttribute('cy', ym.toFixed(1));
                handle.setAttribute('r', '8');
                handle.setAttribute('fill', '#B9FF66');
                handle.setAttribute('stroke', '#191A23');
                handle.setAttribute('stroke-width', '2.5');
                handle.setAttribute('class', 'fov-handle');
                handle.setAttribute('title', 'Drag to aim camera angle');

                handle.addEventListener('mousedown', (e) => {
                    e.stopPropagation();
                    startAimDrag(cx, cy, vw, vh);
                });
                svg.appendChild(handle);
            }
        }

        // ── INTERACTIVE DRAG TO AIM ──
        function startAimDrag(cx, cy, vw, vh) {
            isDraggingFovHandle = true;
            const stage = document.getElementById('blueprintStage');

            function onMouseMove(e) {
                if (!isDraggingFovHandle) return;
                const rect = stage.getBoundingClientRect();
                const mx = ((e.clientX - rect.left) / rect.width) * vw;
                const my = ((e.clientY - rect.top) / rect.height) * vh;

                let deg = Math.atan2(mx - cx, -(my - cy)) * (180 / Math.PI);
                if (deg < 0) deg += 360;
                deg = Math.round(deg);

                document.getElementById('inputFovDirection').value = deg;
                updateFovLabels();
                renderFovCones();
            }

            function onMouseUp() {
                isDraggingFovHandle = false;
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            }

            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        }

        // ── FOV EVENT LISTENERS ──
        function setupFovEventListeners() {
            const toggleBtn = document.getElementById('btnToggleFov');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    fovLayerVisible = !fovLayerVisible;
                    toggleBtn.classList.toggle('active', fovLayerVisible);
                    document.getElementById('fovToggleText').textContent = fovLayerVisible ? 'FoV: ON' : 'FoV: OFF';
                    renderFovCones();
                });
            }

            const fovCheckbox = document.getElementById('inputFovEnabled');
            const fovControls = document.getElementById('fovControlsGroup');
            if (fovCheckbox) {
                fovCheckbox.addEventListener('change', () => {
                    fovControls.style.display = fovCheckbox.checked ? 'flex' : 'none';
                    updateFovLabels();
                    renderFovCones();
                });
            }

            const dirSlider = document.getElementById('inputFovDirection');
            if (dirSlider) {
                dirSlider.addEventListener('input', () => {
                    updateFovLabels();
                    renderFovCones();
                });
            }

            const angleSlider = document.getElementById('inputFovAngle');
            if (angleSlider) {
                angleSlider.addEventListener('input', () => {
                    updateFovLabels();
                    renderFovCones();
                });
            }

            const distSlider = document.getElementById('inputFovDistance');
            if (distSlider) {
                distSlider.addEventListener('input', () => {
                    updateFovLabels();
                    renderFovCones();
                });
            }

            // Presets
            document.querySelectorAll('.fov-preset-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    dirSlider.value = btn.dataset.dir;
                    updateFovLabels();
                    renderFovCones();
                });
            });

            document.querySelectorAll('.fov-angle-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    angleSlider.value = btn.dataset.angle;
                    updateFovLabels();
                    renderFovCones();
                });
            });

            // Auto-enable FoV when camera type is selected
            const typeSelect = document.getElementById('inputType');
            if (typeSelect) {
                typeSelect.addEventListener('change', () => {
                    if (isCameraType(typeSelect.value) && !fovCheckbox.checked) {
                        fovCheckbox.checked = true;
                        fovControls.style.display = 'flex';
                        updateFovLabels();
                        renderFovCones();
                    }
                });
            }

            // Dynamically update pin and FoV cone color when status changes
            const statusSelect = document.getElementById('inputStatus');
            if (statusSelect) {
                statusSelect.addEventListener('change', () => {
                    if (selectedPin) {
                        selectedPin.setAttribute('data-status', statusSelect.value);
                        selectedPin.dataset.status = statusSelect.value;
                    }
                    renderFovCones();
                });
            }
        }

        // ── FILTER & SEARCH MATCH CHECKER ──
        function nodeMatchesSearchAndFilter(n) {
            // 1. Status Filter Pill (activeFilter)
            if (activeFilter === 'ping-offline') {
                if ((n.ping_status || '').toLowerCase() !== 'offline') return false;
            } else if (activeFilter === 'ping-online') {
                if ((n.ping_status || '').toLowerCase() !== 'online') return false;
            } else if (activeFilter !== 'all' && n.status !== activeFilter) {
                return false;
            }

            // 2. Search query (matches status, notes, device_id, type, ip, mac, serial, switch, port, cable, tested_by, ping_status)
            if (currentSearchQuery) {
                const q = currentSearchQuery.toLowerCase();
                const matches =
                    (n.status || '').toLowerCase().includes(q) ||
                    (n.notes || '').toLowerCase().includes(q) ||
                    (n.device_id || '').toLowerCase().includes(q) ||
                    (n.type || '').toLowerCase().includes(q) ||
                    (n.ip_address || '').toLowerCase().includes(q) ||
                    (n.ping_status || '').toLowerCase().includes(q) ||
                    (n.mac_address || '').toLowerCase().includes(q) ||
                    (n.serial_number || '').toLowerCase().includes(q) ||
                    (n.switch_id || '').toLowerCase().includes(q) ||
                    (n.port_no || '').toLowerCase().includes(q) ||
                    (n.cable_label || '').toLowerCase().includes(q) ||
                    (n.tested_by || '').toLowerCase().includes(q);

                if (!matches) return false;
            }

            return true;
        }

        function applyFilters() {
            renderPins();
            renderTable();
            renderFovCones();
        }

        // ── FETCH NODES ──
        async function fetchNodes() {
            try {
                const res = await fetch('api/get_nodes.php?blueprint_id=' + BLUEPRINT_ID);
                const json = await res.json();
                if (json.success) { nodes = json.data; renderPins(); renderTable(); updateStats(); renderFovCones(); }
            } catch(e) { showToast('Failed to load nodes: ' + e.message, 'error'); }
        }

        // ── RENDER PINS ──
        function renderPins() {
            document.querySelectorAll('.pin').forEach(p => p.remove());
            if (isNewPin) {
                isNewPin = false;
                currentPosX = null;
                currentPosY = null;
                disableFormFields();
                const cancelBtn = document.getElementById('btnCancelPin');
                if (cancelBtn) cancelBtn.style.display = 'none';
                const closeBtn = document.getElementById('btnPanelClose');
                if (closeBtn) closeBtn.style.display = 'none';
            }
            if (!IMAGE_EXISTS) return;
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            let visibleCount = 0;

            nodes.forEach(node => {
                if (!nodeMatchesSearchAndFilter(node)) return;
                visibleCount++;

                const pingSt = (node.ping_status || 'unknown').toLowerCase();
                const hasIp = !!(node.ip_address && node.ip_address.trim());
                let pingClass = '';
                if (hasIp && pingSt === 'online') pingClass = ' ping-online';
                else if (hasIp && pingSt === 'offline') pingClass = ' ping-offline';
                else if (hasIp && pingSt === 'pinging') pingClass = ' ping-pinging';

                let pingTagHtml = '';
                if (hasIp) {
                    if (pingSt === 'online') {
                        const lat = node.ping_latency_ms ? `${node.ping_latency_ms}ms` : 'ON';
                        pingTagHtml = `<span class="pin-ping-tag online">${lat}</span>`;
                    } else if (pingSt === 'offline') {
                        pingTagHtml = `<span class="pin-ping-tag offline">DOWN</span>`;
                    } else if (pingSt === 'pinging') {
                        pingTagHtml = `<span class="pin-ping-tag pinging">...</span>`;
                    }
                }

                const pin = document.createElement('div');
                pin.className = 'pin' + pingClass;
                pin.innerHTML = PIN_SVG +
                    `<span class="pin-label">${esc(node.device_id)}</span>` +
                    (hasIp ? `<div class="pin-ping-pulse"></div>` : '') +
                    pingTagHtml;

                pin.style.left = node.pos_x + '%';
                pin.style.top  = node.pos_y + '%';
                pin.title = `${node.device_id} [${node.status}] - ${node.type}` +
                    (node.ip_address ? `\nIP: ${node.ip_address}` : '') +
                    (hasIp ? `\nPing: ${pingSt.toUpperCase()}${node.ping_latency_ms ? ' (' + node.ping_latency_ms + 'ms)' : ''}` : '') +
                    (node.cable_label ? `\nCable: ${node.cable_label}` : '') +
                    (node.notes ? `\nNotes: ${node.notes}` : '');

                pin.dataset.deviceId       = node.device_id;
                pin.dataset.type           = node.type;
                pin.dataset.ip             = node.ip_address || '';
                pin.dataset.status         = node.status;
                pin.dataset.pingStatus     = pingSt;
                pin.dataset.pingLatency    = node.ping_latency_ms || '';
                pin.dataset.lastPingAt     = node.last_ping_at || '';
                pin.dataset.notes          = node.notes || '';
                pin.dataset.serialNumber   = node.serial_number || '';
                pin.dataset.macAddress     = node.mac_address || '';
                pin.dataset.switchId       = node.switch_id || '';
                pin.dataset.portNo         = node.port_no || '';
                pin.dataset.cableLabel     = node.cable_label || '';
                pin.dataset.testedBy       = node.tested_by || '';
                pin.dataset.commissionDate = node.commission_date || '';
                pin.dataset.fovEnabled     = node.fov_enabled || 0;
                pin.dataset.fovAngle       = node.fov_angle || 90;
                pin.dataset.fovDirection   = node.fov_direction || 0;
                pin.dataset.fovDistance    = node.fov_distance || 15;
                pin.dataset.posX           = node.pos_x;
                pin.dataset.posY           = node.pos_y;
                pin.setAttribute('data-status', node.status);
                pin.addEventListener('click', (e) => { e.stopPropagation(); selectPin(pin); });
                stage.appendChild(pin);
            });

            const total = nodes.length;
            const pinCountEl = document.getElementById('pinCount');
            if (activeFilter !== 'all' || currentSearchQuery) {
                pinCountEl.textContent = `Showing ${visibleCount} of ${total} pin${total !== 1 ? 's' : ''}`;
            } else {
                pinCountEl.textContent = `${total} pin${total !== 1 ? 's' : ''}`;
            }

            renderFovCones();
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
            document.getElementById('inputSerial').disabled = false;
            document.getElementById('inputMac').disabled = false;
            document.getElementById('inputSwitch').disabled = false;
            document.getElementById('inputPort').disabled = false;
            document.getElementById('inputCable').disabled = false;
            document.getElementById('inputTestedBy').disabled = false;
            document.getElementById('inputCommissionDate').disabled = false;

            // FoV inputs
            document.getElementById('inputFovEnabled').disabled = false;
            document.getElementById('inputFovDirection').disabled = false;
            document.getElementById('inputFovAngle').disabled = false;
            document.getElementById('inputFovDistance').disabled = false;

            // Cable inputs
            const cableToNode = document.getElementById('inputCableToNode');
            if (cableToNode) cableToNode.disabled = false;
            const cableType = document.getElementById('inputCableType');
            if (cableType) cableType.disabled = false;
            const conduitType = document.getElementById('inputConduitType');
            if (conduitType) conduitType.disabled = false;
            const slackM = document.getElementById('inputSlackMeters');
            if (slackM) slackM.disabled = false;
            const cableSt = document.getElementById('inputCableStatus');
            if (cableSt) cableSt.disabled = false;

            const btnSave = document.getElementById('btnSave');
            btnSave.disabled = false;
            btnSave.classList.remove('btn-disabled-muted');

            const btnPing = document.getElementById('btnPingNodeNow');
            const ipVal = (document.getElementById('inputIp').value || '').trim();
            if (btnPing) btnPing.disabled = !ipVal;
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
            document.getElementById('inputSerial').disabled = true;
            document.getElementById('inputMac').disabled = true;
            document.getElementById('inputSwitch').disabled = true;
            document.getElementById('inputPort').disabled = true;
            document.getElementById('inputCable').disabled = true;
            document.getElementById('inputTestedBy').disabled = true;
            document.getElementById('inputCommissionDate').disabled = true;

            // FoV inputs
            document.getElementById('inputFovEnabled').disabled = true;
            document.getElementById('inputFovDirection').disabled = true;
            document.getElementById('inputFovAngle').disabled = true;
            document.getElementById('inputFovDistance').disabled = true;

            // Cable inputs
            const cableToNode = document.getElementById('inputCableToNode');
            if (cableToNode) cableToNode.disabled = true;
            const cableType = document.getElementById('inputCableType');
            if (cableType) cableType.disabled = true;
            const conduitType = document.getElementById('inputConduitType');
            if (conduitType) conduitType.disabled = true;
            const slackM = document.getElementById('inputSlackMeters');
            if (slackM) slackM.disabled = true;
            const cableSt = document.getElementById('inputCableStatus');
            if (cableSt) cableSt.disabled = true;

            const btnSave = document.getElementById('btnSave');
            btnSave.disabled = true;
            btnSave.classList.add('btn-disabled-muted');
            btnSave.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Pin a location first`;

            const btnPing = document.getElementById('btnPingNodeNow');
            if (btnPing) btnPing.disabled = true;
        }

        // ── SELECT PIN ──
        function selectPin(pin) {
            document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
            document.querySelectorAll('.pin.temp-pin').forEach(p => p.remove());
            pin.classList.add('selected');
            selectedPin = pin;
            isNewPin = false;
            currentPosX = parseFloat(pin.dataset.posX);
            currentPosY = parseFloat(pin.dataset.posY);

            enableFormFields(false); // Existing pin: device ID is locked

            const cancelBtn = document.getElementById('btnCancelPin');
            if (cancelBtn) {
                cancelBtn.textContent = 'Deselect';
                cancelBtn.style.display = '';
            }
            const closeBtn = document.getElementById('btnPanelClose');
            if (closeBtn) closeBtn.style.display = 'flex';

            document.getElementById('coordX').textContent = currentPosX.toFixed(1) + '%';
            document.getElementById('coordY').textContent = currentPosY.toFixed(1) + '%';
            document.getElementById('inputDeviceId').value = pin.dataset.deviceId;
            document.getElementById('inputType').value     = pin.dataset.type;
            document.getElementById('inputIp').value       = pin.dataset.ip;
            document.getElementById('inputStatus').value   = pin.dataset.status;
            document.getElementById('inputNotes').value    = pin.dataset.notes;
            document.getElementById('inputSerial').value   = pin.dataset.serialNumber || '';
            document.getElementById('inputMac').value      = pin.dataset.macAddress || '';
            document.getElementById('inputSwitch').value   = pin.dataset.switchId || '';
            document.getElementById('inputPort').value     = pin.dataset.portNo || '';
            document.getElementById('inputCable').value    = pin.dataset.cableLabel || '';
            document.getElementById('inputTestedBy').value = pin.dataset.testedBy || '';
            document.getElementById('inputCommissionDate').value = pin.dataset.commissionDate || '';

            // FoV parameters
            const fovOn = parseInt(pin.dataset.fovEnabled || 0) === 1;
            document.getElementById('inputFovEnabled').checked = fovOn;
            document.getElementById('inputFovDirection').value = pin.dataset.fovDirection || 0;
            document.getElementById('inputFovAngle').value     = pin.dataset.fovAngle || 90;
            document.getElementById('inputFovDistance').value  = pin.dataset.fovDistance || 15;
            document.getElementById('fovControlsGroup').style.display = fovOn ? 'flex' : 'none';
            updateFovLabels();

            document.getElementById('panelTitle').textContent = pin.dataset.deviceId;
            document.getElementById('panelSubtitle').textContent = 'Editing existing node';
            document.getElementById('btnDelete').style.display = '';
            document.getElementById('btnSave').innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Update Node`;

            // Site Photos
            const matchingNode = nodes.find(n => n.device_id === pin.dataset.deviceId);
            renderNodePhotos(matchingNode);

            // Ping Health Sync
            syncInspectorPing(matchingNode);

            // Cable route sync
            populateCableDestinationDropdown(matchingNode ? matchingNode.id : null);
            syncInspectorCable(matchingNode ? matchingNode.id : null);

            renderFovCones();
            renderCables();
        }

        // ── CLICK MAP ──
        function setupBlueprintClick() {
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            stage.addEventListener('click', (e) => {
                if (isDraggingFovHandle || isDraggingWaypoint) return;

                const rect = stage.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width * 100);
                const y = ((e.clientY - rect.top)  / rect.height * 100);

                if (isRoutingMode && activeCable) {
                    addWaypointToActiveCable(parseFloat(x.toFixed(4)), parseFloat(y.toFixed(4)));
                    return;
                }

                currentPosX = parseFloat(x.toFixed(4));
                currentPosY = parseFloat(y.toFixed(4));

                document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
                document.querySelectorAll('.pin.temp-pin').forEach(p => p.remove());

                // Default status follows active filter if set, otherwise Planned
                const initialStatus = (activeFilter !== 'all') ? activeFilter : 'Planned';

                const pin = document.createElement('div');
                pin.className = 'pin selected temp-pin';
                pin.innerHTML = PIN_SVG + `<span class="pin-label">NEW</span>`;
                pin.style.left = x + '%';
                pin.style.top  = y + '%';
                pin.setAttribute('data-status', initialStatus);
                pin.dataset.status = initialStatus;
                stage.appendChild(pin);

                selectedPin = pin;
                isNewPin = true;

                enableFormFields(true); // New pin: enable device ID input

                const cancelBtn = document.getElementById('btnCancelPin');
                if (cancelBtn) {
                    cancelBtn.textContent = 'Cancel New Pin';
                    cancelBtn.style.display = '';
                }
                const closeBtn = document.getElementById('btnPanelClose');
                if (closeBtn) closeBtn.style.display = 'flex';

                document.getElementById('coordX').textContent = x.toFixed(1) + '%';
                document.getElementById('coordY').textContent = y.toFixed(1) + '%';
                document.getElementById('inputDeviceId').value = '';
                document.getElementById('inputType').value = 'Hikvision Dome';
                document.getElementById('inputIp').value = '';
                document.getElementById('inputStatus').value = initialStatus;
                document.getElementById('inputNotes').value = '';
                document.getElementById('inputSerial').value = '';
                document.getElementById('inputMac').value = '';
                document.getElementById('inputSwitch').value = '';
                document.getElementById('inputPort').value = '';
                document.getElementById('inputCable').value = '';
                document.getElementById('inputTestedBy').value = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>;
                document.getElementById('inputCommissionDate').value = new Date().toISOString().split('T')[0];

                // Auto-enable FoV for default camera type
                document.getElementById('inputFovEnabled').checked = true;
                document.getElementById('inputFovDirection').value = 0;
                document.getElementById('inputFovAngle').value     = 90;
                document.getElementById('inputFovDistance').value  = 15;
                document.getElementById('fovControlsGroup').style.display = 'flex';
                updateFovLabels();

                document.getElementById('panelTitle').textContent = 'New Node';
                document.getElementById('panelSubtitle').textContent = 'Fill in the details and save';
                document.getElementById('btnDelete').style.display = 'none';
                document.getElementById('btnSave').innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> Save New Node`;
                document.getElementById('inputDeviceId').focus();

                renderNodePhotos(null);
                activeCable = null;
                syncInspectorPing(null);
                populateCableDestinationDropdown(null);
                syncInspectorCable(null);
                renderFovCones();
                renderCables();
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
                blueprint_id:    BLUEPRINT_ID,
                device_id:       deviceId,
                type:            document.getElementById('inputType').value,
                ip_address:      document.getElementById('inputIp').value.trim(),
                pos_x:           currentPosX,
                pos_y:           currentPosY,
                status:          document.getElementById('inputStatus').value,
                notes:           document.getElementById('inputNotes').value.trim(),
                serial_number:   document.getElementById('inputSerial').value.trim(),
                mac_address:     document.getElementById('inputMac').value.trim(),
                switch_id:       document.getElementById('inputSwitch').value.trim(),
                port_no:         document.getElementById('inputPort').value.trim(),
                cable_label:     document.getElementById('inputCable').value.trim(),
                tested_by:       document.getElementById('inputTestedBy').value.trim(),
                commission_date: document.getElementById('inputCommissionDate').value,
                fov_enabled:     document.getElementById('inputFovEnabled').checked ? 1 : 0,
                fov_angle:       parseInt(document.getElementById('inputFovAngle').value) || 90,
                fov_direction:   parseInt(document.getElementById('inputFovDirection').value) || 0,
                fov_distance:    parseInt(document.getElementById('inputFovDistance').value) || 15,
            };

            try {
                const res = await fetch('api/save_node.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
                const json = await res.json();
                if (json.success) { showToast(json.message,'success'); await fetchNodes(); await loadCables(); resetPanel(); }
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
                if (json.success) { showToast(json.message,'success'); await fetchNodes(); await loadCables(); resetPanel(); }
                else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── RESET PANEL ──
        function resetPanel() {
            selectedPin = null; isNewPin = false; currentPosX = currentPosY = null;
            document.querySelectorAll('.pin.selected').forEach(p => p.classList.remove('selected'));
            document.querySelectorAll('.pin.temp-pin').forEach(p => p.remove());

            const cancelBtn = document.getElementById('btnCancelPin');
            if (cancelBtn) cancelBtn.style.display = 'none';
            const closeBtn = document.getElementById('btnPanelClose');
            if (closeBtn) closeBtn.style.display = 'none';

            document.getElementById('coordX').textContent = '—';
            document.getElementById('coordY').textContent = '—';
            document.getElementById('inputDeviceId').value = '';
            document.getElementById('inputType').value = 'Hikvision Dome';
            document.getElementById('inputIp').value = '';
            document.getElementById('inputStatus').value = 'Planned';
            document.getElementById('inputNotes').value = '';
            document.getElementById('inputSerial').value = '';
            document.getElementById('inputMac').value = '';
            document.getElementById('inputSwitch').value = '';
            document.getElementById('inputPort').value = '';
            document.getElementById('inputCable').value = '';
            document.getElementById('inputTestedBy').value = '';
            document.getElementById('inputCommissionDate').value = '';

            // Reset FoV inputs
            document.getElementById('inputFovEnabled').checked = false;
            document.getElementById('inputFovDirection').value = 0;
            document.getElementById('inputFovAngle').value = 90;
            document.getElementById('inputFovDistance').value = 15;
            document.getElementById('fovControlsGroup').style.display = 'none';
            updateFovLabels();

            document.getElementById('panelTitle').textContent = 'Node Inspector';
            document.getElementById('panelSubtitle').textContent = 'Click the map to drop a pin';
            document.getElementById('btnDelete').style.display = 'none';

            renderNodePhotos(null);
            activeCable = null;
            if (isRoutingMode) toggleRouteBendsMode(false);
            syncInspectorPing(null);
            populateCableDestinationDropdown(null);
            syncInspectorCable(null);
            disableFormFields();
            renderFovCones();
            renderCables();
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
            const f = nodes.filter(nodeMatchesSearchAndFilter);
            const total = nodes.length;
            const tableCountEl = document.getElementById('tableCount');
            if (activeFilter !== 'all' || currentSearchQuery) {
                tableCountEl.textContent = `Showing ${f.length} of ${total} node${total !== 1 ? 's' : ''}`;
            } else {
                tableCountEl.textContent = `${total} node${total !== 1 ? 's' : ''}`;
            }

            const tb = document.getElementById('nodeTableBody');
            if (!f.length) {
                tb.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">
                    No commissioning nodes match your filter ${currentSearchQuery ? `("${esc(currentSearchQuery)}")` : ''}
                </td></tr>`;
                return;
            }

            tb.innerHTML = f.map(n => `
                <tr onclick="scrollToPin('${escAttr(n.device_id)}')">
                    <td>
                        <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                            <strong>${esc(n.device_id)}</strong>
                            ${(n.photo_count || 0) > 0 ? `
                                <span class="table-photo-badge" onclick="event.stopPropagation(); scrollToPin('${escAttr(n.device_id)}');" title="${n.photo_count} site verification photo${n.photo_count !== 1 ? 's' : ''} attached">
                                    📸 ${n.photo_count}
                                </span>
                            ` : ''}
                        </div>
                    </td>
                    <td>${esc(n.type)}</td>
                    <td>
                        <div class="ip-mono">${esc(n.ip_address||'—')}</div>
                        ${n.mac_address ? `<div class="tag-mono" style="font-size:0.7rem;color:var(--text-muted);">${esc(n.mac_address)}</div>` : ''}
                    </td>
                    <td>${n.switch_id ? `<span class="tag-mono">${esc(n.switch_id)}${n.port_no ? ' : P' + esc(n.port_no) : ''}</span>` : '—'}</td>
                    <td>${n.cable_label ? `<span class="tag-mono" style="background:#eef2ff;color:#4338ca;">${esc(n.cable_label)}</span>` : '—'}</td>
                    <td>${n.serial_number ? `<span class="tag-mono">${esc(n.serial_number)}</span>` : '—'}</td>
                    <td><span class="status-badge ${statusClass(n.status)}">${esc(n.status)}</span></td>
                    <td style="max-width:200px;font-size:0.8rem;color:var(--text);white-space:normal;">
                        ${n.notes ? `<div style="line-height:1.35;word-break:break-word;">${esc(n.notes)}</div>` : '<span style="color:var(--text-muted);">—</span>'}
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted);">${esc(n.tested_by||'—')}</td>
                    <td style="font-size:0.8rem;color:var(--text-muted);">${esc(n.commission_date||'—')}</td>
                    <td style="font-size:0.78rem;color:var(--text-muted);">${parseFloat(n.pos_x).toFixed(1)}%, ${parseFloat(n.pos_y).toFixed(1)}%</td>
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

        // ── FILTERS & SEARCH SETUP ──
        function setupFilters() {
            // 1. Filter Pills above Blueprint
            document.querySelectorAll('.filter-pill').forEach(p => {
                p.addEventListener('click', () => {
                    document.querySelectorAll('.filter-pill').forEach(x => x.classList.remove('active'));
                    p.classList.add('active');
                    activeFilter = p.dataset.filter;

                    // Sync stat card highlight
                    document.querySelectorAll('.stat-card').forEach(sc => {
                        sc.classList.toggle('active', sc.dataset.filter === activeFilter);
                    });

                    // Cancel unsaved pin or deselect mismatched node on filter switch
                    if (isNewPin) {
                        resetPanel();
                    } else if (selectedPin) {
                        const selDev = selectedPin.dataset.deviceId;
                        const selNode = nodes.find(n => n.device_id === selDev);
                        if (selNode && !nodeMatchesSearchAndFilter(selNode)) resetPanel();
                    }

                    applyFilters();
                });
            });

            // 2. Clickable Stat Cards (Quick Filter by Status)
            document.querySelectorAll('.stat-card').forEach(sc => {
                sc.addEventListener('click', () => {
                    const f = sc.dataset.filter || 'all';
                    activeFilter = f;

                    document.querySelectorAll('.filter-pill').forEach(p => {
                        p.classList.toggle('active', p.dataset.filter === f);
                    });
                    document.querySelectorAll('.stat-card').forEach(c => {
                        c.classList.toggle('active', c.dataset.filter === f);
                    });

                    // Cancel unsaved pin or deselect mismatched node on filter switch
                    if (isNewPin) {
                        resetPanel();
                    } else if (selectedPin) {
                        const selDev = selectedPin.dataset.deviceId;
                        const selNode = nodes.find(n => n.device_id === selDev);
                        if (selNode && !nodeMatchesSearchAndFilter(selNode)) resetPanel();
                    }

                    applyFilters();
                });
            });

            // 3. Search inputs synchronization (map toolbar + table toolbar)
            const mapSearch   = document.getElementById('mapSearchInput');
            const mapClear    = document.getElementById('mapSearchClear');
            const tableSearch = document.getElementById('searchInput');

            function handleSearchInput(val) {
                currentSearchQuery = (val || '').trim();
                if (mapSearch && mapSearch.value !== val) mapSearch.value = val;
                if (tableSearch && tableSearch.value !== val) tableSearch.value = val;
                if (mapClear) mapClear.style.display = currentSearchQuery ? 'inline-block' : 'none';

                if (isNewPin) {
                    resetPanel();
                } else if (selectedPin) {
                    const selDev = selectedPin.dataset.deviceId;
                    const selNode = nodes.find(n => n.device_id === selDev);
                    if (selNode && !nodeMatchesSearchAndFilter(selNode)) resetPanel();
                }

                applyFilters();
            }

            if (mapSearch) {
                mapSearch.addEventListener('input', (e) => handleSearchInput(e.target.value));
            }
            if (mapClear) {
                mapClear.addEventListener('click', () => handleSearchInput(''));
            }
            if (tableSearch) {
                tableSearch.addEventListener('input', (e) => handleSearchInput(e.target.value));
            }
        }

        // ── SITE PHOTO EVIDENCE FUNCTIONS ──
        function renderNodePhotos(node) {
            const galleryEl = document.getElementById('nodePhotoGallery');
            const badgeEl = document.getElementById('photoBadgeCount');
            const takeBtn = document.getElementById('btnTakeCameraPhoto');
            const uploadBtn = document.getElementById('btnUploadFilePhoto');
            if (!galleryEl) return;

            cancelPhotoSelection();

            if (!node || isNewPin) {
                galleryEl.innerHTML = `<div class="gallery-empty">Save node first before attaching on-site verification photos.</div>`;
                if (badgeEl) badgeEl.textContent = '0 photos';
                if (takeBtn) takeBtn.disabled = true;
                if (uploadBtn) uploadBtn.disabled = true;
                return;
            }

            if (takeBtn) takeBtn.disabled = false;
            if (uploadBtn) uploadBtn.disabled = false;

            const photos = node.photos || [];
            if (badgeEl) badgeEl.textContent = `${photos.length} photo${photos.length !== 1 ? 's' : ''}`;

            if (photos.length === 0) {
                galleryEl.innerHTML = `<div class="gallery-empty">No site photos attached yet.<br>Tap "Take Photo" or "Upload File" below.</div>`;
                return;
            }

            galleryEl.innerHTML = photos.map(p => `
                <div class="gallery-thumb-card" title="${escAttr(p.caption || node.device_id)}" onclick="openPhotoLightbox('${escAttr(p.filename)}', '${escAttr(node.device_id)}', '${escAttr(p.caption || '')}', '${escAttr(p.created_at)}')">
                    <img src="uploads/nodes/${escAttr(p.filename)}" alt="${escAttr(p.caption || '')}" loading="lazy">
                    <button type="button" class="thumb-del-btn" onclick="deleteNodePhoto(event, ${p.id}, '${escAttr(node.device_id)}')" title="Delete this photo">✕</button>
                </div>
            `).join('');
        }

        function triggerCameraCapture() {
            if (isNewPin || !selectedPin) {
                showToast('Please save node details first before attaching photos', 'info');
                return;
            }
            const input = document.getElementById('nodePhotoFileInput');
            input.setAttribute('capture', 'environment');
            input.value = '';
            input.click();
        }

        function triggerFileSelect() {
            if (isNewPin || !selectedPin) {
                showToast('Please save node details first before attaching photos', 'info');
                return;
            }
            const input = document.getElementById('nodePhotoFileInput');
            input.removeAttribute('capture');
            input.value = '';
            input.click();
        }

        function handlePhotoSelected(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            if (!file.type.match(/^image\//)) {
                showToast('Please select a valid image file (JPG, PNG, WEBP, SVG)', 'error');
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                showToast('Photo too large. Max 25 MB', 'error');
                return;
            }

            selectedPhotoFile = file;
            const reader = new FileReader();
            reader.onload = (ev) => {
                const previewImg = document.getElementById('photoTempPreview');
                if (previewImg) previewImg.src = ev.target.result;
                const captionInp = document.getElementById('photoCaptionInput');
                if (captionInp) captionInp.value = '';
                const bar = document.getElementById('photoPreviewBar');
                if (bar) bar.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }

        function cancelPhotoSelection() {
            selectedPhotoFile = null;
            const input = document.getElementById('nodePhotoFileInput');
            if (input) input.value = '';
            const bar = document.getElementById('photoPreviewBar');
            if (bar) bar.style.display = 'none';
        }

        async function uploadSelectedPhoto() {
            if (!selectedPhotoFile) {
                showToast('No photo selected', 'error');
                return;
            }
            if (!selectedPin || isNewPin) {
                showToast('Node must be saved before uploading photos', 'error');
                return;
            }

            const deviceId = selectedPin.dataset.deviceId;
            const caption = document.getElementById('photoCaptionInput').value.trim();

            const progressEl = document.getElementById('photoUploadProgress');
            const btnUpload = document.getElementById('btnUploadPhoto');
            if (progressEl) progressEl.style.display = 'flex';
            if (btnUpload) btnUpload.disabled = true;

            const fd = new FormData();
            fd.append('blueprint_id', BLUEPRINT_ID);
            fd.append('device_id', deviceId);
            fd.append('photo', selectedPhotoFile);
            if (caption) fd.append('caption', caption);

            try {
                const res = await fetch('api/upload_node_photo.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.success && json.photo) {
                    showToast('Site photo attached successfully!', 'success');

                    const node = nodes.find(n => n.device_id === deviceId);
                    if (node) {
                        if (!node.photos) node.photos = [];
                        node.photos.unshift(json.photo);
                        node.photo_count = node.photos.length;
                        renderNodePhotos(node);
                        renderTable();
                    }
                    cancelPhotoSelection();
                } else {
                    showToast(json.error || 'Failed to upload photo', 'error');
                }
            } catch (err) {
                showToast('Network error while uploading photo', 'error');
            } finally {
                if (progressEl) progressEl.style.display = 'none';
                if (btnUpload) btnUpload.disabled = false;
            }
        }

        async function deleteNodePhoto(e, photoId, deviceId) {
            e.stopPropagation();
            if (!confirm('Are you sure you want to remove this site verification photo?')) return;

            try {
                const res = await fetch('api/delete_node_photo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ photo_id: photoId })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Site photo removed', 'info');
                    const node = nodes.find(n => n.device_id === deviceId);
                    if (node && node.photos) {
                        node.photos = node.photos.filter(p => parseInt(p.id) !== parseInt(photoId));
                        node.photo_count = node.photos.length;
                        renderNodePhotos(node);
                        renderTable();
                    }
                } else {
                    showToast(json.error || 'Failed to delete photo', 'error');
                }
            } catch (err) {
                showToast('Network error while deleting photo', 'error');
            }
        }

        // ── LIGHTBOX VIEWER ──
        function openPhotoLightbox(filename, devId, caption, createdAt) {
            const modal = document.getElementById('photoLightboxModal');
            const img = document.getElementById('lightboxImg');
            const badge = document.getElementById('lightboxDevBadge');
            const captionEl = document.getElementById('lightboxCaption');
            const timeEl = document.getElementById('lightboxTime');
            const dlBtn = document.getElementById('lightboxDownloadBtn');

            const src = 'uploads/nodes/' + filename;
            img.src = src;
            badge.textContent = devId;
            captionEl.textContent = caption || 'Physical installation verification & conduit tagging';
            timeEl.textContent = createdAt ? new Date(createdAt).toLocaleString() : '';
            dlBtn.href = src;

            modal.classList.add('active');
        }

        function closePhotoLightbox(e) {
            if (e && e.target && e.target.closest('.lightbox-dialog') && !e.target.classList.contains('lightbox-close-btn')) {
                return;
            }
            const modal = document.getElementById('photoLightboxModal');
            if (modal) modal.classList.remove('active');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('photoLightboxModal');
                const cableSched = document.getElementById('cableScheduleModal');
                const scaleModal = document.getElementById('scaleCalibrationModal');
                const nocModal = document.getElementById('nocMonitorModal');
                if (modal && modal.classList.contains('active')) {
                    closePhotoLightbox();
                } else if (cableSched && cableSched.classList.contains('active')) {
                    closeModal('cableScheduleModal');
                } else if (scaleModal && scaleModal.classList.contains('active')) {
                    closeModal('scaleCalibrationModal');
                } else if (nocModal && nocModal.classList.contains('active')) {
                    closeModal('nocMonitorModal');
                } else if (isRoutingMode) {
                    toggleRouteBendsMode(false);
                } else if (selectedPin || isNewPin) {
                    resetPanel();
                }
            }
        });

        // ══════════════════════════════════════════════════════════════
        // ── INTERACTIVE CABLE ROUTING & LENGTH CALCULATOR FUNCTIONS ──
        // ══════════════════════════════════════════════════════════════

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }

        function setupCableEventListeners() {
            const toggleBtn = document.getElementById('btnToggleCables');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    cablesVisible = !cablesVisible;
                    toggleBtn.classList.toggle('active', cablesVisible);
                    document.getElementById('cableToggleText').textContent = cablesVisible ? `Cables: ON (${cables.length})` : 'Cables: OFF';
                    renderCables();
                });
            }
        }

        function updateCableToolbarBadge() {
            const toggleText = document.getElementById('cableToggleText');
            if (toggleText && cablesVisible) {
                toggleText.textContent = `Cables: ON (${cables.length})`;
            }
        }

        async function loadCables() {
            try {
                const res = await fetch('api/cables_api.php?blueprint_id=' + BLUEPRINT_ID);
                const json = await res.json();
                if (json.success) {
                    cables = json.cables || [];
                    if (json.scale) {
                        bpRealWidthM = parseFloat(json.scale.real_width_meters) || 60.0;
                        bpRealHeightM = parseFloat(json.scale.real_height_meters) || 40.0;
                        const scaleTxt = `${bpRealWidthM}m × ${bpRealHeightM}m`;
                        const scaleEl = document.getElementById('toolbarScaleLabel');
                        if (scaleEl) scaleEl.textContent = scaleTxt;
                        const bomTag = document.getElementById('bomScaleTag');
                        if (bomTag) bomTag.textContent = scaleTxt;
                    }
                    renderCables();
                    updateCableToolbarBadge();

                    if (selectedPin) {
                        const devId = selectedPin.dataset.deviceId;
                        const node = nodes.find(n => n.device_id === devId);
                        if (node) syncInspectorCable(node.id);
                    }
                }
            } catch (e) {
                console.error('Error loading cables:', e);
            }
        }

        function calcSegmentMeters(p1, p2) {
            const dx = ((p2.x - p1.x) / 100.0) * bpRealWidthM;
            const dy = ((p2.y - p1.y) / 100.0) * bpRealHeightM;
            return Math.sqrt(dx * dx + dy * dy);
        }

        function calcCableTotalLength(pStart, pEnd, waypoints, slackM) {
            const pts = [pStart];
            if (Array.isArray(waypoints)) {
                for (const wp of waypoints) {
                    if (wp && typeof wp.x === 'number' && typeof wp.y === 'number') {
                        pts.push({ x: parseFloat(wp.x), y: parseFloat(wp.y) });
                    }
                }
            }
            pts.push(pEnd);

            let horizontalRun = 0.0;
            for (let i = 0; i < pts.length - 1; i++) {
                horizontalRun += calcSegmentMeters(pts[i], pts[i + 1]);
            }
            return parseFloat((horizontalRun + parseFloat(slackM || 0)).toFixed(2));
        }

        function getCableColorByType(type) {
            switch (type) {
                case 'Cat6 UTP': return '#3b82f6';         // Standard Blue
                case 'Cat6A STP': return '#8b5cf6';        // Shielded Purple
                case 'Fiber 4-Core': return '#f97316';     // Orange Optical
                case 'RG59 Coaxial': return '#10b981';     // Coaxial Green
                case '2-Core Shielded': return '#eab308';  // Alarm / Sensor Yellow
                case 'Power 3-Core': return '#ef4444';     // Power Red
                default: return '#3b82f6';
            }
        }

        function getPathMidpoint(points) {
            if (!points || points.length === 0) return { x: 0, y: 0 };
            if (points.length === 1) return points[0];
            let total = 0;
            const segLens = [];
            for (let i = 0; i < points.length - 1; i++) {
                const dx = points[i+1].x - points[i].x;
                const dy = points[i+1].y - points[i].y;
                const len = Math.sqrt(dx*dx + dy*dy);
                segLens.push(len);
                total += len;
            }
            const half = total / 2;
            let accum = 0;
            for (let i = 0; i < segLens.length; i++) {
                if (accum + segLens[i] >= half || i === segLens.length - 1) {
                    const rem = half - accum;
                    const ratio = segLens[i] > 0 ? (rem / segLens[i]) : 0.5;
                    return {
                        x: points[i].x + (points[i+1].x - points[i].x) * ratio,
                        y: points[i].y + (points[i+1].y - points[i].y) * ratio
                    };
                }
                accum += segLens[i];
            }
            return points[0];
        }

        function renderCables() {
            const svg = document.getElementById('cableSvgLayer');
            if (!svg) return;
            svg.innerHTML = '';
            if (!cablesVisible || !IMAGE_EXISTS) return;

            const img = document.getElementById('blueprintImg');
            const vw = BP_WIDTH || (img && img.naturalWidth) || 1600;
            const vh = BP_HEIGHT || (img && img.naturalHeight) || 1000;
            svg.setAttribute('viewBox', `0 0 ${vw} ${vh}`);

            cables.forEach(c => {
                const fromNode = nodes.find(n => parseInt(n.id) === parseInt(c.from_node_id));
                const toNode   = nodes.find(n => parseInt(n.id) === parseInt(c.to_node_id));

                const fromX = fromNode ? parseFloat(fromNode.pos_x) : parseFloat(c.from_pos_x);
                const fromY = fromNode ? parseFloat(fromNode.pos_y) : parseFloat(c.from_pos_y);
                const toX   = toNode   ? parseFloat(toNode.pos_x)   : parseFloat(c.to_pos_x);
                const toY   = toNode   ? parseFloat(toNode.pos_y)   : parseFloat(c.to_pos_y);

                if (isNaN(fromX) || isNaN(fromY) || isNaN(toX) || isNaN(toY)) return;

                const pStart = { x: (fromX / 100) * vw, y: (fromY / 100) * vh };
                const pEnd   = { x: (toX / 100) * vw,   y: (toY / 100) * vh };

                const isCableActive = (activeCable && parseInt(activeCable.id) === parseInt(c.id)) ||
                                      (selectedPin && (
                                          (fromNode && selectedPin.dataset.deviceId === fromNode.device_id) ||
                                          (toNode && selectedPin.dataset.deviceId === toNode.device_id)
                                      ));

                const color = c.color || getCableColorByType(c.cable_type);
                const waypoints = Array.isArray(c.waypoints) ? c.waypoints : [];

                let d = `M ${pStart.x.toFixed(1)} ${pStart.y.toFixed(1)}`;
                const allPoints = [{ x: pStart.x, y: pStart.y }];
                waypoints.forEach(wp => {
                    const px = (parseFloat(wp.x) / 100) * vw;
                    const py = (parseFloat(wp.y) / 100) * vh;
                    d += ` L ${px.toFixed(1)} ${py.toFixed(1)}`;
                    allPoints.push({ x: px, y: py });
                });
                d += ` L ${pEnd.x.toFixed(1)} ${pEnd.y.toFixed(1)}`;
                allPoints.push({ x: pEnd.x, y: pEnd.y });

                const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                g.setAttribute('class', 'cable-group' + (isCableActive ? ' active-selected' : ''));
                g.setAttribute('data-cable-id', c.id);

                // 1. Glow path
                const glow = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                glow.setAttribute('d', d);
                glow.setAttribute('class', 'cable-path-glow');
                glow.setAttribute('stroke', color);
                glow.setAttribute('stroke-width', isCableActive ? '13' : '7');
                g.appendChild(glow);

                // 2. Core solid line
                const core = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                core.setAttribute('d', d);
                core.setAttribute('class', 'cable-path-core');
                core.setAttribute('stroke', color);
                core.setAttribute('stroke-width', isCableActive ? '4.5' : '2.8');
                g.appendChild(core);

                // 3. Pulse dash line
                if (isCableActive || c.status === 'Cable Pulled' || c.status === 'Live') {
                    const dash = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    dash.setAttribute('d', d);
                    dash.setAttribute('class', 'cable-path-dash');
                    g.appendChild(dash);
                }

                // 4. Central Tag Badge with rounded background
                const mid = getPathMidpoint(allPoints);
                const tagText = `${c.cable_label} · ${c.total_length_meters}m`;
                const tagWidth = Math.max(90, tagText.length * 7 + 16);
                const tagHeight = 22;

                const tagG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                tagG.setAttribute('transform', `translate(${mid.x.toFixed(1)}, ${mid.y.toFixed(1)})`);

                const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                rect.setAttribute('x', (-tagWidth / 2).toFixed(1));
                rect.setAttribute('y', (-tagHeight / 2).toFixed(1));
                rect.setAttribute('width', tagWidth);
                rect.setAttribute('height', tagHeight);
                rect.setAttribute('rx', '4');
                rect.setAttribute('ry', '4');
                rect.setAttribute('fill', isCableActive ? '#191A23' : '#292A32');
                rect.setAttribute('stroke', isCableActive ? '#B9FF66' : color);
                rect.setAttribute('stroke-width', isCableActive ? '2' : '1.2');
                tagG.appendChild(rect);

                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', '0');
                text.setAttribute('y', '0');
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('dominant-baseline', 'central');
                text.setAttribute('fill', isCableActive ? '#B9FF66' : '#ffffff');
                text.setAttribute('font-family', "'IBM Plex Mono', monospace");
                text.setAttribute('font-size', '10px');
                text.setAttribute('font-weight', '700');
                text.textContent = tagText;
                tagG.appendChild(text);

                g.appendChild(tagG);

                // 5. Waypoint handles
                if (isCableActive) {
                    waypoints.forEach((wp, idx) => {
                        const wx = (parseFloat(wp.x) / 100) * vw;
                        const wy = (parseFloat(wp.y) / 100) * vh;

                        const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                        handle.setAttribute('cx', wx.toFixed(1));
                        handle.setAttribute('cy', wy.toFixed(1));
                        handle.setAttribute('r', '7');
                        handle.setAttribute('class', 'cable-waypoint-handle');
                        handle.setAttribute('title', `Waypoint #${idx + 1}: Drag to move corner, double-click to remove`);

                        handle.addEventListener('mousedown', (e) => {
                            e.stopPropagation();
                            startWaypointDrag(c, idx, vw, vh);
                        });

                        handle.addEventListener('dblclick', (e) => {
                            e.stopPropagation();
                            removeWaypoint(c, idx);
                        });

                        g.appendChild(handle);
                    });
                }

                g.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectCable(c);
                });

                svg.appendChild(g);
            });
        }

        function startWaypointDrag(cable, idx, vw, vh) {
            isDraggingWaypoint = true;
            const stage = document.getElementById('blueprintStage');

            function onMouseMove(e) {
                if (!isDraggingWaypoint) return;
                const rect = stage.getBoundingClientRect();
                const xPercent = parseFloat(((e.clientX - rect.left) / rect.width * 100).toFixed(4));
                const yPercent = parseFloat(((e.clientY - rect.top) / rect.height * 100).toFixed(4));

                cable.waypoints[idx].x = Math.max(0, Math.min(100, xPercent));
                cable.waypoints[idx].y = Math.max(0, Math.min(100, yPercent));

                const fromNode = nodes.find(n => parseInt(n.id) === parseInt(cable.from_node_id));
                const toNode   = nodes.find(n => parseInt(n.id) === parseInt(cable.to_node_id));
                const pStart = { x: parseFloat(fromNode?.pos_x ?? cable.from_pos_x), y: parseFloat(fromNode?.pos_y ?? cable.from_pos_y) };
                const pEnd   = { x: parseFloat(toNode?.pos_x ?? cable.to_pos_x),     y: parseFloat(toNode?.pos_y ?? cable.to_pos_y) };

                cable.total_length_meters = calcCableTotalLength(pStart, pEnd, cable.waypoints, cable.slack_drop_meters);
                const lenVal = document.getElementById('cableLiveLengthVal');
                if (lenVal) lenVal.textContent = cable.total_length_meters.toFixed(1);

                renderCables();
            }

            async function onMouseUp() {
                isDraggingWaypoint = false;
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                await saveCableToServer(cable);
                showToast(`Waypoint updated (${cable.total_length_meters}m)`, 'info');
            }

            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        }

        async function removeWaypoint(cable, idx) {
            cable.waypoints.splice(idx, 1);
            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(cable.from_node_id));
            const toNode   = nodes.find(n => parseInt(n.id) === parseInt(cable.to_node_id));
            const pStart = { x: parseFloat(fromNode?.pos_x ?? cable.from_pos_x), y: parseFloat(fromNode?.pos_y ?? cable.from_pos_y) };
            const pEnd   = { x: parseFloat(toNode?.pos_x ?? cable.to_pos_x),     y: parseFloat(toNode?.pos_y ?? cable.to_pos_y) };

            cable.total_length_meters = calcCableTotalLength(pStart, pEnd, cable.waypoints, cable.slack_drop_meters);
            const lenVal = document.getElementById('cableLiveLengthVal');
            if (lenVal) lenVal.textContent = cable.total_length_meters.toFixed(1);

            renderCables();
            await saveCableToServer(cable);
            showToast('Waypoint removed', 'info');
        }

        async function addWaypointToActiveCable(x, y) {
            if (!activeCable) return;
            if (!Array.isArray(activeCable.waypoints)) activeCable.waypoints = [];
            activeCable.waypoints.push({ x, y });

            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(activeCable.from_node_id));
            const toNode   = nodes.find(n => parseInt(n.id) === parseInt(activeCable.to_node_id));
            const pStart = { x: parseFloat(fromNode?.pos_x ?? activeCable.from_pos_x), y: parseFloat(fromNode?.pos_y ?? activeCable.from_pos_y) };
            const pEnd   = { x: parseFloat(toNode?.pos_x ?? activeCable.to_pos_x),     y: parseFloat(toNode?.pos_y ?? activeCable.to_pos_y) };

            activeCable.total_length_meters = calcCableTotalLength(pStart, pEnd, activeCable.waypoints, activeCable.slack_drop_meters);
            const lenVal = document.getElementById('cableLiveLengthVal');
            if (lenVal) lenVal.textContent = activeCable.total_length_meters.toFixed(1);

            renderCables();
            await saveCableToServer(activeCable);
            showToast(`Corner added (${x.toFixed(1)}%, ${y.toFixed(1)}%) · ${activeCable.total_length_meters}m`, 'success');
        }

        async function autoOrthogonalRoute() {
            if (!activeCable) {
                showToast('Please select a cable run first', 'info');
                return;
            }
            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(activeCable.from_node_id));
            const toNode   = nodes.find(n => parseInt(n.id) === parseInt(activeCable.to_node_id));
            if (!fromNode || !toNode) return;

            const x1 = parseFloat(fromNode.pos_x);
            const y1 = parseFloat(fromNode.pos_y);
            const x2 = parseFloat(toNode.pos_x);
            const y2 = parseFloat(toNode.pos_y);

            const currentWp = activeCable.waypoints || [];
            let newCorner = { x: x2, y: y1 };
            if (currentWp.length === 1 && Math.abs(currentWp[0].x - x2) < 0.1 && Math.abs(currentWp[0].y - y1) < 0.1) {
                newCorner = { x: x1, y: y2 };
            }
            activeCable.waypoints = [newCorner];

            const pStart = { x: x1, y: y1 };
            const pEnd   = { x: x2, y: y2 };
            activeCable.total_length_meters = calcCableTotalLength(pStart, pEnd, activeCable.waypoints, activeCable.slack_drop_meters);
            const lenVal = document.getElementById('cableLiveLengthVal');
            if (lenVal) lenVal.textContent = activeCable.total_length_meters.toFixed(1);

            renderCables();
            await saveCableToServer(activeCable);
            showToast(`90° corridor route applied (${activeCable.total_length_meters}m)`, 'success');
        }

        async function straightenCableRoute() {
            if (!activeCable) return;
            activeCable.waypoints = [];
            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(activeCable.from_node_id));
            const toNode   = nodes.find(n => parseInt(n.id) === parseInt(activeCable.to_node_id));
            const pStart = { x: parseFloat(fromNode?.pos_x ?? activeCable.from_pos_x), y: parseFloat(fromNode?.pos_y ?? activeCable.from_pos_y) };
            const pEnd   = { x: parseFloat(toNode?.pos_x ?? activeCable.to_pos_x),     y: parseFloat(toNode?.pos_y ?? activeCable.to_pos_y) };

            activeCable.total_length_meters = calcCableTotalLength(pStart, pEnd, [], activeCable.slack_drop_meters);
            const lenVal = document.getElementById('cableLiveLengthVal');
            if (lenVal) lenVal.textContent = activeCable.total_length_meters.toFixed(1);

            renderCables();
            await saveCableToServer(activeCable);
            showToast(`Straight line path restored (${activeCable.total_length_meters}m)`, 'info');
        }

        async function saveCableToServer(cable) {
            const payload = {
                action: 'save',
                id: cable.id || 0,
                blueprint_id: BLUEPRINT_ID,
                from_node_id: cable.from_node_id,
                to_node_id: cable.to_node_id,
                cable_label: cable.cable_label,
                cable_type: cable.cable_type,
                conduit_type: cable.conduit_type,
                slack_drop_meters: cable.slack_drop_meters,
                status: cable.status,
                color: cable.color || getCableColorByType(cable.cable_type),
                notes: cable.notes || '',
                waypoints: cable.waypoints || []
            };

            try {
                const res = await fetch('api/cables_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    cable.id = json.id;
                    cable.total_length_meters = json.total_length_meters;
                    return true;
                } else {
                    showToast(json.error || 'Failed to save cable', 'error');
                    return false;
                }
            } catch (e) {
                showToast('Network error while saving cable route', 'error');
                return false;
            }
        }

        function selectCable(c) {
            activeCable = c;
            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(c.from_node_id));
            if (fromNode) {
                const pin = document.querySelector(`.pin[data-device-id="${fromNode.device_id}"]`);
                if (pin) selectPin(pin);
            }
            syncInspectorCable(c.from_node_id);
            renderCables();
        }

        function populateCableDestinationDropdown(currentNodeId) {
            const sel = document.getElementById('inputCableToNode');
            if (!sel) return;
            sel.innerHTML = '<option value="">-- No Direct Cable Link --</option>';

            if (!currentNodeId) return;

            const otherNodes = nodes.filter(n => parseInt(n.id) !== parseInt(currentNodeId));
            otherNodes.sort((a, b) => {
                const aIsHub = (a.type || '').toLowerCase().includes('rack') || (a.type || '').toLowerCase().includes('switch') || (a.type || '').toLowerCase().includes('nvr');
                const bIsHub = (b.type || '').toLowerCase().includes('rack') || (b.type || '').toLowerCase().includes('switch') || (b.type || '').toLowerCase().includes('nvr');
                if (aIsHub && !bIsHub) return -1;
                if (!aIsHub && bIsHub) return 1;
                return (a.device_id || '').localeCompare(b.device_id || '');
            });

            otherNodes.forEach(n => {
                const opt = document.createElement('option');
                opt.value = n.id;
                opt.textContent = `${n.device_id} [${n.type || 'Node'}] (${n.status})`;
                sel.appendChild(opt);
            });
        }

        function syncInspectorCable(nodeId) {
            const subgroup = document.getElementById('cableDetailsSubgroup');
            const badge = document.getElementById('cableStatusBadge');
            const sel = document.getElementById('inputCableToNode');
            if (!sel) return;

            if (!nodeId) {
                if (subgroup) subgroup.style.display = 'none';
                if (badge) {
                    badge.textContent = 'No Cable';
                    badge.style.background = 'rgba(255,255,255,0.08)';
                    badge.style.color = '#93c5fd';
                }
                sel.value = '';
                activeCable = null;
                return;
            }

            const cable = cables.find(c => parseInt(c.from_node_id) === parseInt(nodeId) || parseInt(c.to_node_id) === parseInt(nodeId));

            if (cable) {
                activeCable = cable;
                const targetId = parseInt(cable.from_node_id) === parseInt(nodeId) ? cable.to_node_id : cable.from_node_id;
                sel.value = targetId;
                if (subgroup) subgroup.style.display = 'flex';

                document.getElementById('inputCableType').value    = cable.cable_type || 'Cat6 UTP';
                document.getElementById('inputConduitType').value  = cable.conduit_type || '20mm PVC Conduit';
                document.getElementById('inputSlackMeters').value  = parseFloat(cable.slack_drop_meters) || 3.0;
                document.getElementById('inputCableStatus').value  = cable.status || 'Planned';
                document.getElementById('cableLiveLengthVal').textContent = parseFloat(cable.total_length_meters).toFixed(1);

                if (badge) {
                    badge.textContent = `${cable.cable_label} · ${cable.total_length_meters}m`;
                    badge.style.background = '#2563eb';
                    badge.style.color = '#ffffff';
                }
            } else {
                activeCable = null;
                sel.value = '';
                if (subgroup) subgroup.style.display = 'none';
                document.getElementById('cableLiveLengthVal').textContent = '0.0';
                if (badge) {
                    badge.textContent = 'No Cable';
                    badge.style.background = 'rgba(255,255,255,0.08)';
                    badge.style.color = '#93c5fd';
                }
            }
        }

        async function onCableDestinationChanged() {
            const sel = document.getElementById('inputCableToNode');
            const toNodeId = parseInt(sel.value);

            if (!selectedPin) return;
            const devId = selectedPin.dataset.deviceId;
            const fromNode = nodes.find(n => n.device_id === devId);
            if (!fromNode) return;

            if (!toNodeId) {
                if (activeCable) {
                    if (confirm(`Remove cable run '${activeCable.cable_label}'?`)) {
                        await deleteActiveCable();
                    } else {
                        syncInspectorCable(fromNode.id);
                    }
                }
                return;
            }

            const toNode = nodes.find(n => parseInt(n.id) === toNodeId);
            if (!toNode) return;

            let cable = cables.find(c => 
                (parseInt(c.from_node_id) === parseInt(fromNode.id) && parseInt(c.to_node_id) === toNodeId) ||
                (parseInt(c.to_node_id) === parseInt(fromNode.id) && parseInt(c.from_node_id) === toNodeId)
            );

            if (!cable) {
                const cableTag = document.getElementById('inputCable').value.trim() || ('C-' + fromNode.device_id);
                const pStart = { x: parseFloat(fromNode.pos_x), y: parseFloat(fromNode.pos_y) };
                const pEnd   = { x: parseFloat(toNode.pos_x),   y: parseFloat(toNode.pos_y) };
                const initSlack = 3.0;
                const totalLen = calcCableTotalLength(pStart, pEnd, [], initSlack);

                cable = {
                    id: 0,
                    blueprint_id: BLUEPRINT_ID,
                    from_node_id: fromNode.id,
                    to_node_id: toNode.id,
                    from_device_id: fromNode.device_id,
                    to_device_id: toNode.device_id,
                    from_pos_x: fromNode.pos_x,
                    from_pos_y: fromNode.pos_y,
                    to_pos_x: toNode.pos_x,
                    to_pos_y: toNode.pos_y,
                    cable_label: cableTag,
                    cable_type: 'Cat6 UTP',
                    conduit_type: '20mm PVC Conduit',
                    waypoints: [],
                    slack_drop_meters: initSlack,
                    total_length_meters: totalLen,
                    status: 'Planned',
                    color: '#3b82f6',
                    notes: ''
                };

                const saved = await saveCableToServer(cable);
                if (saved) {
                    cables.push(cable);
                    activeCable = cable;
                    document.getElementById('inputCable').value = cableTag;
                    syncInspectorCable(fromNode.id);
                    renderCables();
                    updateCableToolbarBadge();
                    showToast(`Cable '${cableTag}' routed to ${toNode.device_id} (${totalLen}m)`, 'success');
                }
            } else {
                activeCable = cable;
                syncInspectorCable(fromNode.id);
                renderCables();
            }
        }

        async function updateCurrentCableDetails() {
            if (!activeCable) return;

            activeCable.cable_type         = document.getElementById('inputCableType').value;
            activeCable.conduit_type       = document.getElementById('inputConduitType').value;
            activeCable.slack_drop_meters   = parseFloat(document.getElementById('inputSlackMeters').value) || 0;
            activeCable.status             = document.getElementById('inputCableStatus').value;
            activeCable.color              = getCableColorByType(activeCable.cable_type);

            const fromNode = nodes.find(n => parseInt(n.id) === parseInt(activeCable.from_node_id));
            const toNode   = nodes.find(n => parseInt(n.id) === parseInt(activeCable.to_node_id));
            const pStart = { x: parseFloat(fromNode?.pos_x ?? activeCable.from_pos_x), y: parseFloat(fromNode?.pos_y ?? activeCable.from_pos_y) };
            const pEnd   = { x: parseFloat(toNode?.pos_x ?? activeCable.to_pos_x),     y: parseFloat(toNode?.pos_y ?? activeCable.to_pos_y) };

            activeCable.total_length_meters = calcCableTotalLength(pStart, pEnd, activeCable.waypoints, activeCable.slack_drop_meters);
            const lenVal = document.getElementById('cableLiveLengthVal');
            if (lenVal) lenVal.textContent = activeCable.total_length_meters.toFixed(1);

            const badge = document.getElementById('cableStatusBadge');
            if (badge) badge.textContent = `${activeCable.cable_label} · ${activeCable.total_length_meters}m`;

            renderCables();
            await saveCableToServer(activeCable);
        }

        async function deleteActiveCable() {
            if (!activeCable) return;
            if (!confirm(`Delete cable route '${activeCable.cable_label}'?`)) return;

            try {
                const res = await fetch('api/cables_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: activeCable.id })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Cable route deleted', 'info');
                    cables = cables.filter(c => c.id !== activeCable.id);
                    activeCable = null;
                    if (selectedPin) {
                        const devId = selectedPin.dataset.deviceId;
                        const node = nodes.find(n => n.device_id === devId);
                        syncInspectorCable(node ? node.id : null);
                    }
                    renderCables();
                    updateCableToolbarBadge();
                } else {
                    showToast(json.error || 'Failed to delete cable', 'error');
                }
            } catch (e) {
                showToast('Network error while deleting cable', 'error');
            }
        }

        function toggleRouteBendsMode(forcedState) {
            if (!activeCable && (forcedState === true || (!isRoutingMode && forcedState === undefined))) {
                showToast('Please select or create a cable run first to route bends', 'info');
                return;
            }
            isRoutingMode = (forcedState !== undefined) ? forcedState : !isRoutingMode;
            const banner = document.getElementById('routingBanner');
            const btn = document.getElementById('btnRouteMode');

            if (banner) banner.style.display = isRoutingMode ? 'flex' : 'none';
            if (btn) {
                btn.classList.toggle('active', isRoutingMode);
                btn.innerHTML = isRoutingMode ? 
                    '<span>✓ Finish Bends</span>' : 
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg><span>⚡ Route Bends</span>';
            }
            renderCables();
        }

        function syncCableTagInput(val) {
            if (activeCable) {
                activeCable.cable_label = val.trim();
                const badge = document.getElementById('cableStatusBadge');
                if (badge) badge.textContent = `${activeCable.cable_label} · ${activeCable.total_length_meters}m`;
                renderCables();
                clearTimeout(activeCable._saveTimer);
                activeCable._saveTimer = setTimeout(() => {
                    saveCableToServer(activeCable);
                }, 600);
            }
        }

        // ── SCALE CALIBRATION ──
        function openScaleModal() {
            document.getElementById('inputScaleWidth').value = bpRealWidthM;
            document.getElementById('inputScaleHeight').value = bpRealHeightM;
            openModal('scaleCalibrationModal');
        }

        function applyScalePreset(w, h) {
            document.getElementById('inputScaleWidth').value = w;
            document.getElementById('inputScaleHeight').value = h;
        }

        async function saveScaleCalibration() {
            const w = parseFloat(document.getElementById('inputScaleWidth').value);
            const h = parseFloat(document.getElementById('inputScaleHeight').value);

            if (isNaN(w) || w <= 0 || isNaN(h) || h <= 0) {
                showToast('Please enter valid positive dimensions for width and height in meters', 'error');
                return;
            }

            try {
                const res = await fetch('api/cables_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_scale',
                        blueprint_id: BLUEPRINT_ID,
                        real_width_meters: w,
                        real_height_meters: h
                    })
                });
                const json = await res.json();
                if (json.success) {
                    bpRealWidthM = w;
                    bpRealHeightM = h;
                    const scaleTxt = `${w}m × ${h}m`;
                    const scaleEl = document.getElementById('toolbarScaleLabel');
                    if (scaleEl) scaleEl.textContent = scaleTxt;
                    const bomTag = document.getElementById('bomScaleTag');
                    if (bomTag) bomTag.textContent = scaleTxt;

                    closeModal('scaleCalibrationModal');
                    showToast(json.message || 'Scale updated and all cables recalculated!', 'success');
                    await loadCables();
                } else {
                    showToast(json.error || 'Failed to update scale', 'error');
                }
            } catch (e) {
                showToast('Network error while updating scale', 'error');
            }
        }

        // ── CABLE SCHEDULE MODAL & CSV EXPORT ──
        function openCableScheduleModal() {
            renderCableScheduleTable();
            openModal('cableScheduleModal');
        }

        function renderCableScheduleTable() {
            const tbody = document.getElementById('cableScheduleTableBody');
            if (!tbody) return;

            if (cables.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">No structured cable routes created yet. Connect nodes using the Inspector panel.</td></tr>`;
                document.getElementById('bomTotalMeters').textContent = '0.0 m';
                document.getElementById('bomTotalRuns').textContent = '0 cable runs';
                document.getElementById('bomCat6Boxes').textContent = '0 Box';
                document.getElementById('bomCat6Sub').textContent = '0m installed';
                document.getElementById('bomOtherCables').textContent = '0.0 m';
                return;
            }

            let totalMeters = 0.0;
            let cat6Meters = 0.0;
            let otherMeters = 0.0;

            tbody.innerHTML = cables.map(c => {
                const fromNode = nodes.find(n => parseInt(n.id) === parseInt(c.from_node_id));
                const toNode   = nodes.find(n => parseInt(n.id) === parseInt(c.to_node_id));
                const fromDev  = fromNode ? fromNode.device_id : (c.from_device_id || 'Node #' + c.from_node_id);
                const toDev    = toNode ? toNode.device_id : (c.to_device_id || 'Node #' + c.to_node_id);

                const totalLen = parseFloat(c.total_length_meters);
                const slack = parseFloat(c.slack_drop_meters);
                const runM = Math.max(0, totalLen - slack);

                totalMeters += totalLen;
                if ((c.cable_type || '').includes('Cat6 UTP')) {
                    cat6Meters += totalLen;
                } else {
                    otherMeters += totalLen;
                }

                return `
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 12px; font-weight:700;"><span class="tag-mono" style="background:#eef2ff; color:#3b82f6;">${esc(c.cable_label)}</span></td>
                        <td style="padding:10px 12px; font-weight:600;">${esc(fromDev)}</td>
                        <td style="padding:10px 12px; font-weight:600;">${esc(toDev)}</td>
                        <td style="padding:10px 12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${c.color||getCableColorByType(c.cable_type)}; margin-right:5px;"></span>${esc(c.cable_type)}</td>
                        <td style="padding:10px 12px; font-size:0.78rem; color:var(--text-muted);">${esc(c.conduit_type)}</td>
                        <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace;">${runM.toFixed(1)}</td>
                        <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace;">${slack.toFixed(1)}</td>
                        <td style="padding:10px 12px; text-align:right; font-weight:700; font-family:'IBM Plex Mono',monospace; color:#2563eb;">${totalLen.toFixed(1)}</td>
                        <td style="padding:10px 12px; text-align:center;"><span class="status-badge ${statusClass(c.status)}">${esc(c.status)}</span></td>
                    </tr>
                `;
            }).join('');

            const cat6Boxes = Math.ceil(cat6Meters / 305);
            const cat6Leftover = (cat6Boxes * 305) - cat6Meters;

            document.getElementById('bomTotalMeters').textContent = `${totalMeters.toFixed(1)} m`;
            document.getElementById('bomTotalRuns').textContent = `${cables.length} cable run${cables.length !== 1 ? 's' : ''}`;
            document.getElementById('bomCat6Boxes').textContent = `${cat6Boxes} Box${cat6Boxes !== 1 ? 'es' : ''}`;
            document.getElementById('bomCat6Sub').textContent = `${cat6Meters.toFixed(1)}m installed · ${Math.max(0, cat6Leftover).toFixed(1)}m remainder`;
            document.getElementById('bomOtherCables').textContent = `${otherMeters.toFixed(1)} m`;
            document.getElementById('bomOtherSub').textContent = `Shielded & Specialty conduits`;
        }

        function exportCableScheduleCsv() {
            if (cables.length === 0) {
                showToast('No cable routes to export', 'info');
                return;
            }

            const headers = ['Cable Tag', 'From (Source)', 'To (Termination)', 'Cable Spec', 'Conduit Type', 'Horizontal Run (m)', 'Slack Drop (m)', 'Total Length (m)', 'Status', 'Notes'];
            const rows = cables.map(c => {
                const fromNode = nodes.find(n => parseInt(n.id) === parseInt(c.from_node_id));
                const toNode   = nodes.find(n => parseInt(n.id) === parseInt(c.to_node_id));
                const fromDev  = fromNode ? fromNode.device_id : (c.from_device_id || 'Node #' + c.from_node_id);
                const toDev    = toNode ? toNode.device_id : (c.to_device_id || 'Node #' + c.to_node_id);

                const totalLen = parseFloat(c.total_length_meters);
                const slack = parseFloat(c.slack_drop_meters);
                const runM = Math.max(0, totalLen - slack);

                return [
                    c.cable_label,
                    fromDev,
                    toDev,
                    c.cable_type,
                    c.conduit_type,
                    runM.toFixed(2),
                    slack.toFixed(2),
                    totalLen.toFixed(2),
                    c.status,
                    c.notes || ''
                ].map(val => `"${String(val).replace(/"/g, '""')}"`);
            });

            const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `SiteDeploy_Cable_Schedule_BP${BLUEPRINT_ID}.csv`;
            link.click();
            showToast('Cable Schedule CSV exported successfully!', 'success');
        }

        // Click outside stage on dark area resets selection / cancels unsaved pin
        const bpArea = document.getElementById('blueprintArea');
        if (bpArea) {
            bpArea.addEventListener('click', (e) => {
                if (e.target === bpArea) resetPanel();
            });
        }

        // ══════════════════════════════════════════════════════════════
        // ── REAL-TIME LIVE PING STATUS & NOC MONITORING ──
        // ══════════════════════════════════════════════════════════════

        /**
         * Synthesizes a two-tone audible warning alert using native Web Audio API
         */
        function playOfflineAlertSound() {
            const soundEnabled = document.getElementById('nocSoundAlertToggle');
            if (soundEnabled && !soundEnabled.checked) return;
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const now = ctx.currentTime;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.setValueAtTime(440, now + 0.12);

                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.35);
            } catch (e) {
                console.warn('Audio alert blocked or unsupported:', e);
            }
        }

        /**
         * Updates the Node Inspector panel's ping badge, latency, and test button
         */
        function syncInspectorPing(node) {
            const badge = document.getElementById('inspectorPingBadge');
            const metaWrap = document.getElementById('inspectorPingMeta');
            const details = document.getElementById('inspectorPingDetails');
            const btnPing = document.getElementById('btnPingNodeNow');
            const ipInput = document.getElementById('inputIp');

            if (!badge) return;

            if (!node) {
                badge.className = 'ping-status-badge unknown';
                badge.textContent = 'Untested';
                if (metaWrap) metaWrap.style.display = 'none';
                if (details) details.textContent = '—';
                if (btnPing) {
                    const hasTypedIp = ipInput && ipInput.value && ipInput.value.trim().length > 0;
                    btnPing.disabled = !hasTypedIp;
                }
                return;
            }

            const ip = (node.ip_address || '').trim();
            const pingSt = (node.ping_status || 'unknown').toLowerCase();
            const latency = node.ping_latency_ms;
            const lastAt = node.last_ping_at;

            badge.className = 'ping-status-badge ' + pingSt;

            if (!ip) {
                badge.className = 'ping-status-badge unknown';
                badge.textContent = 'No IP Configured';
                if (btnPing) btnPing.disabled = true;
                if (metaWrap) metaWrap.style.display = 'none';
                return;
            }

            if (btnPing) btnPing.disabled = false;

            if (pingSt === 'online') {
                badge.textContent = `🟢 Online ${latency ? '(' + latency + 'ms)' : ''}`;
            } else if (pingSt === 'offline') {
                badge.textContent = '🔴 Offline (Unreachable)';
            } else if (pingSt === 'pinging') {
                badge.textContent = '🟡 Pinging...';
            } else if (pingSt === 'invalid') {
                badge.textContent = '⚠️ Invalid IP';
            } else {
                badge.textContent = '⚪ Untested';
            }

            if (metaWrap && details) {
                if (lastAt) {
                    metaWrap.style.display = 'block';
                    details.textContent = `Tested: ${lastAt}${latency ? ' · RTT: ' + latency + 'ms' : ''}`;
                } else {
                    metaWrap.style.display = 'none';
                    details.textContent = '—';
                }
            }
        }

        /**
         * Triggered when the user types in the IP Address field
         */
        function onIpAddressInput(val) {
            const btn = document.getElementById('btnPingNodeNow');
            if (btn) {
                btn.disabled = !(val && val.trim().length > 0);
            }
        }

        /**
         * Pings currently selected node from Inspector
         */
        async function pingCurrentNode() {
            if (!selectedPin) {
                showToast('Please select a device node to ping', 'info');
                return;
            }

            const devId = selectedPin.dataset.deviceId;
            const matchingNode = nodes.find(n => n.device_id === devId);
            if (!matchingNode) {
                showToast('Please save node first before running live network ping', 'info');
                return;
            }

            const currentIp = document.getElementById('inputIp').value.trim();
            if (!currentIp) {
                showToast('Please specify an IP address to ping', 'info');
                document.getElementById('inputIp').focus();
                return;
            }

            await pingSingleNode(matchingNode.id, false);
        }

        /**
         * Pings a single node via API and updates UI
         */
        async function pingSingleNode(nodeId, silent = false) {
            const node = nodes.find(n => parseInt(n.id) === parseInt(nodeId));
            if (!node) return;

            const btnPing = document.getElementById('btnPingNodeNow');
            const btnText = document.getElementById('btnPingNodeText');
            const originalText = btnText ? btnText.textContent : '⚡ Ping';

            // Set loading state
            if (btnPing) btnPing.disabled = true;
            if (btnText) btnText.innerHTML = `<span class="spin">⏳</span> Testing...`;

            // Update pin to pinging state
            node.ping_status = 'pinging';
            const pinEl = document.querySelector(`.pin[data-device-id="${node.device_id}"]`);
            if (pinEl) {
                pinEl.classList.remove('ping-online', 'ping-offline');
                pinEl.classList.add('ping-pinging');
                const tag = pinEl.querySelector('.pin-ping-tag');
                if (tag) {
                    tag.className = 'pin-ping-tag pinging';
                    tag.textContent = '...';
                }
            }

            if (selectedPin && selectedPin.dataset.deviceId === node.device_id) {
                syncInspectorPing(node);
            }

            try {
                const res = await fetch('api/ping_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'ping_node',
                        node_id: node.id,
                        demo_mode: nocDemoMode ? 1 : 0
                    })
                });

                const json = await res.json();
                if (json.success && json.data) {
                    const d = json.data;
                    const prevStatus = node.ping_status;
                    node.ping_status = d.ping_status;
                    node.ping_latency_ms = d.ping_latency_ms;
                    node.last_ping_at = d.last_ping_at;

                    // Re-render this pin
                    if (pinEl) {
                        pinEl.classList.remove('ping-online', 'ping-offline', 'ping-pinging');
                        if (d.ping_status === 'online') pinEl.classList.add('ping-online');
                        else if (d.ping_status === 'offline') pinEl.classList.add('ping-offline');

                        let tag = pinEl.querySelector('.pin-ping-tag');
                        if (!tag && node.ip_address) {
                            tag = document.createElement('span');
                            pinEl.appendChild(tag);
                        }
                        if (tag) {
                            tag.className = 'pin-ping-tag ' + d.ping_status;
                            tag.textContent = d.ping_status === 'online' ? (d.ping_latency_ms ? d.ping_latency_ms + 'ms' : 'ON') : (d.ping_status === 'offline' ? 'DOWN' : '...');
                        }
                    }

                    if (selectedPin && selectedPin.dataset.deviceId === node.device_id) {
                        syncInspectorPing(node);
                    }

                    if (d.ping_status === 'offline') {
                        playOfflineAlertSound();
                        if (!silent) showToast(`⚠️ Device ${node.device_id} is OFFLINE / unreachable!`, 'error');
                    } else if (d.ping_status === 'online') {
                        if (!silent) showToast(`✓ ${node.device_id} Online (${d.ping_latency_ms}ms)`, 'success');
                    } else {
                        if (!silent) showToast(`${node.device_id}: ${d.probe ? d.probe.message : 'Unknown'}`, 'info');
                    }

                    const nocModal = document.getElementById('nocMonitorModal');
                    if (nocModal && nocModal.classList.contains('active')) {
                        renderNocTable();
                    }
                } else {
                    if (!silent) showToast(json.error || 'Ping probe failed', 'error');
                }
            } catch (err) {
                if (!silent) showToast('Network probe failed: ' + err.message, 'error');
            } finally {
                if (btnPing) {
                    const currentIp = document.getElementById('inputIp').value.trim();
                    btnPing.disabled = !currentIp;
                }
                if (btnText) btnText.textContent = originalText;
            }
        }

        /**
         * Pings all devices on the current blueprint in a batch
         */
        async function pingAllDevices(isManual = false) {
            if (isBatchPinging) return;

            const ipNodes = nodes.filter(n => n.ip_address && n.ip_address.trim());
            if (ipNodes.length === 0) {
                if (isManual) showToast('No devices with IP addresses configured on this blueprint', 'info');
                return;
            }

            isBatchPinging = true;

            const btnPingAll = document.getElementById('nocPingAllBtn');
            const toolbarPingBtn = document.getElementById('btnToggleLivePing');
            if (btnPingAll) {
                btnPingAll.disabled = true;
                btnPingAll.innerHTML = `<span class="spin">⏳</span> Probing Network...`;
            }

            // Set all pins to pinging animation
            ipNodes.forEach(n => {
                const pinEl = document.querySelector(`.pin[data-device-id="${n.device_id}"]`);
                if (pinEl) {
                    const tag = pinEl.querySelector('.pin-ping-tag');
                    if (tag) {
                        tag.className = 'pin-ping-tag pinging';
                        tag.textContent = '...';
                    }
                }
            });

            try {
                const res = await fetch('api/ping_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'ping_batch',
                        blueprint_id: BLUEPRINT_ID,
                        demo_mode: nocDemoMode ? 1 : 0
                    })
                });

                const json = await res.json();
                const results = json.results || json.data;
                if (json.success && results) {
                    let newlyOffline = 0;

                    results.forEach(item => {
                        const n = nodes.find(x => parseInt(x.id) === parseInt(item.node_id));
                        if (n) {
                            const newStatus = (item.ping_status || item.status || 'unknown').toLowerCase();
                            const newLatency = item.ping_latency_ms !== undefined ? item.ping_latency_ms : item.latency_ms;
                            const wasOnline = (n.ping_status || '').toLowerCase() === 'online';

                            n.ping_status = newStatus;
                            n.ping_latency_ms = newLatency;
                            n.last_ping_at = item.last_ping_at;

                            if (wasOnline && newStatus === 'offline') {
                                newlyOffline++;
                            }
                        }
                    });

                    // Re-render pins
                    renderPins();

                    // Sync inspector if a pin is active
                    if (selectedPin) {
                        const curDev = selectedPin.dataset.deviceId;
                        const curNode = nodes.find(n => n.device_id === curDev);
                        syncInspectorPing(curNode);
                    }

                    // Update NOC modal table & metrics
                    renderNocTable();

                    if (newlyOffline > 0) {
                        playOfflineAlertSound();
                        showToast(`⚠️ Alert: ${newlyOffline} device(s) dropped offline!`, 'error');
                    } else if (isManual) {
                        const onlineCount = results.filter(r => r.ping_status === 'online').length;
                        showToast(`Network probe complete: ${onlineCount}/${results.length} devices online`, 'success');
                    }
                } else {
                    if (isManual) showToast(json.error || 'Batch ping failed', 'error');
                }
            } catch (err) {
                if (isManual) showToast('Network probe connection error: ' + err.message, 'error');
            } finally {
                isBatchPinging = false;
                if (btnPingAll) {
                    btnPingAll.disabled = false;
                    btnPingAll.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Ping All Devices Now`;
                }
            }
        }

        /**
         * Toggles periodic background heartbeat monitor
         */
        function toggleLivePingMonitoring() {
            isLivePingActive = !isLivePingActive;
            const btn = document.getElementById('btnToggleLivePing');
            const dot = document.getElementById('livePingDot');
            const text = document.getElementById('livePingText');

            if (isLivePingActive) {
                if (btn) btn.classList.add('active');
                if (dot) dot.style.background = '#22c55e';
                const sec = Math.round(livePingIntervalMs / 1000);
                if (text) text.textContent = `Ping: LIVE (${sec}s)`;

                // Ping immediately, then loop
                pingAllDevices(false);
                livePingTimer = setInterval(() => pingAllDevices(false), livePingIntervalMs);
                showToast(`Live Network Heartbeat active (${sec}s interval)`, 'success');
            } else {
                if (btn) btn.classList.remove('active');
                if (dot) dot.style.background = '#9CA3AF';
                if (text) text.textContent = 'Live Ping: OFF';

                if (livePingTimer) {
                    clearInterval(livePingTimer);
                    livePingTimer = null;
                }
                showToast('Live Network Heartbeat paused', 'info');
            }
        }

        /**
         * Changes interval for periodic heartbeat
         */
        function changeNocInterval(sec) {
            livePingIntervalMs = parseInt(sec) * 1000;
            if (isLivePingActive) {
                clearInterval(livePingTimer);
                livePingTimer = setInterval(() => pingAllDevices(false), livePingIntervalMs);
                const text = document.getElementById('livePingText');
                if (text) text.textContent = `Ping: LIVE (${sec}s)`;
                showToast(`Ping interval updated to ${sec}s`, 'info');
            }
        }

        /**
         * Presentation Demo Mode Toggle (realistic simulated 4-32ms pings for client meetings)
         */
        function toggleDemoPingMode(checked) {
            nocDemoMode = !!checked;
            if (nocDemoMode) {
                showToast('Presentation Demo Mode enabled: Simulating realistic device latency', 'info');
            } else {
                showToast('Real ICMP / TCP network probe mode active', 'info');
            }
        }

        /**
         * Opens the NOC Command Center modal and populates the device list
         */
        function openNocModal() {
            openModal('nocMonitorModal');
            renderNocTable();
        }

        /**
         * Populates device rows and summary cards in NOC Command Center modal
         */
        function renderNocTable() {
            const tbody = document.getElementById('nocDeviceTableBody');
            if (!tbody) return;

            const ipNodes = nodes.filter(n => n.ip_address && n.ip_address.trim());
            const totalIp = ipNodes.length;
            const onlineNodes = ipNodes.filter(n => (n.ping_status || '').toLowerCase() === 'online');
            const offlineNodes = ipNodes.filter(n => (n.ping_status || '').toLowerCase() === 'offline');

            let totalLat = 0, countLat = 0;
            onlineNodes.forEach(n => {
                if (n.ping_latency_ms) {
                    totalLat += parseInt(n.ping_latency_ms);
                    countLat++;
                }
            });
            const avgLat = countLat > 0 ? (totalLat / countLat).toFixed(1) : 0;

            const totEl = document.getElementById('nocTotalIpDevices');
            const onEl = document.getElementById('nocOnlineCount');
            const offEl = document.getElementById('nocOfflineCount');
            const latEl = document.getElementById('nocAvgLatency');

            if (totEl) totEl.textContent = totalIp;
            if (onEl) onEl.textContent = onlineNodes.length;
            if (offEl) offEl.textContent = offlineNodes.length;
            if (latEl) latEl.textContent = countLat > 0 ? `${avgLat} ms` : '—';

            if (totalIp === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:35px; color:var(--text-muted);">
                    No devices have an IP address configured. Add IP addresses to camera/node pins to begin monitoring.
                </td></tr>`;
                return;
            }

            tbody.innerHTML = ipNodes.map(n => {
                const st = (n.ping_status || 'unknown').toLowerCase();
                let statusBadge = '<span class="status-badge unknown" style="background:#f3f4f6; color:#4b5563;">⚪ Untested</span>';
                if (st === 'online') {
                    statusBadge = `<span class="status-badge live" style="background:#dcfce7; color:#15803d; font-weight:700;">🟢 Online</span>`;
                } else if (st === 'offline') {
                    statusBadge = `<span class="status-badge planned" style="background:#fee2e2; color:#b91c1c; font-weight:700;">🔴 Offline</span>`;
                } else if (st === 'pinging') {
                    statusBadge = `<span class="status-badge" style="background:#fef3c7; color:#b45309; font-weight:700;">🟡 Probing...</span>`;
                }

                const latencyStr = n.ping_latency_ms ? `${n.ping_latency_ms} ms` : '—';
                const lastPingStr = n.last_ping_at ? n.last_ping_at.split(' ')[1] || n.last_ping_at : 'Never';
                const switchPort = (n.switch_id || n.port_no) ? `${esc(n.switch_id || 'SW')}${n.port_no ? ' / P' + esc(n.port_no) : ''}` : '—';

                return `
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 12px; font-weight:700;">
                            <a href="javascript:void(0)" onclick="closeModal('nocMonitorModal'); scrollToPin('${escAttr(n.device_id)}');" style="color:var(--dark); text-decoration:underline;">
                                ${esc(n.device_id)}
                            </a>
                        </td>
                        <td style="padding:10px 12px; font-size:0.8rem;">${esc(n.type)}</td>
                        <td style="padding:10px 12px;"><span class="tag-mono" style="background:#f0fdf4; color:#166534;">${esc(n.ip_address)}</span></td>
                        <td style="padding:10px 12px; font-size:0.78rem; color:var(--text-muted);">${switchPort}</td>
                        <td style="padding:10px 12px; text-align:center;">${statusBadge}</td>
                        <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:600;">${latencyStr}</td>
                        <td style="padding:10px 12px; text-align:center; font-size:0.75rem; color:var(--text-muted);">${esc(lastPingStr)}</td>
                        <td style="padding:10px 12px; text-align:center;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="pingSingleNode(${n.id}, false)" style="padding:2px 8px; font-size:0.72rem;">
                                ⚡ Ping
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        /**
         * Filters floorplan view to only display offline devices
         */
        function filterMapOfflineOnly() {
            activeFilter = 'ping-offline';

            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.stat-card').forEach(sc => sc.classList.remove('active'));

            closeModal('nocMonitorModal');
            applyFilters();
            showToast('Floorplan filtered: Displaying OFFLINE devices only', 'info');
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

