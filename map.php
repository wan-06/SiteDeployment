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
            <a class="back-btn" href="dashboard.php">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Dashboard
            </a>
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
                <div style="display:flex; align-items:center; gap:10px;">
                    <button type="button" id="btnToggleFov" class="btn-fov-toggle active" title="Toggle camera field-of-view directional cones">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><circle cx="12" cy="13" r="4"/></svg>
                        <span id="fovToggleText">FoV: ON</span>
                    </button>
                    <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;" id="pinCount">0 pins</span>
                </div>
            </div>
            <div class="blueprint-area" id="blueprintArea">
                <?php if ($imageExists): ?>
                    <div class="blueprint-stage" id="blueprintStage">
                        <img src="uploads/blueprints/<?= htmlspecialchars($blueprint['image_filename']) ?>" alt="Blueprint" id="blueprintImg">
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
                <div class="form-group"><label>Structured Cable Tag</label><input type="text" id="inputCable" placeholder="e.g. C-L2-014" disabled></div>
                <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:8px;">
                    <div class="form-group"><label>Tested By</label><input type="text" id="inputTestedBy" placeholder="Technician name" disabled></div>
                    <div class="form-group"><label>Commission Date</label><input type="date" id="inputCommissionDate" disabled></div>
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

        const PIN_SVG = `<svg viewBox="0 0 32 40" fill="none"><path d="M16 0C7.2 0 0 7.2 0 16c0 12 16 24 16 24s16-12 16-24C32 7.2 24.8 0 16 0z" fill="#ef4444"/><circle cx="16" cy="15" r="7" fill="white" opacity="0.9"/><circle cx="16" cy="15" r="3" fill="#ef4444"/></svg>`;

        document.addEventListener('DOMContentLoaded', () => {
            fetchNodes();
            if (IMAGE_EXISTS) setupBlueprintClick();
            setupFilters();
            setupFovEventListeners();
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

            // If placing a new pin with FoV enabled
            if (isNewPin && currentPosX !== null && currentPosY !== null && document.getElementById('inputFovEnabled').checked) {
                const cx = (currentPosX / 100) * vw;
                const cy = (currentPosY / 100) * vh;
                const dir   = parseInt(document.getElementById('inputFovDirection').value) || 0;
                const angle = parseInt(document.getElementById('inputFovAngle').value) || 90;
                const dist  = parseInt(document.getElementById('inputFovDistance').value) || 15;
                const r = (dist / 100) * vw;
                const colors = { fill: 'rgba(185, 255, 102, 0.35)', stroke: '#B9FF66' };
                drawSingleFovCone(svg, cx, cy, r, dir, angle, colors, true, vw, vh);
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
        }

        // ── FILTER & SEARCH MATCH CHECKER ──
        function nodeMatchesSearchAndFilter(n) {
            // 1. Status Filter Pill (activeFilter)
            if (activeFilter !== 'all' && n.status !== activeFilter) {
                return false;
            }

            // 2. Search query (matches status, notes, device_id, type, ip, mac, serial, switch, port, cable, tested_by)
            if (currentSearchQuery) {
                const q = currentSearchQuery.toLowerCase();
                const matches =
                    (n.status || '').toLowerCase().includes(q) ||
                    (n.notes || '').toLowerCase().includes(q) ||
                    (n.device_id || '').toLowerCase().includes(q) ||
                    (n.type || '').toLowerCase().includes(q) ||
                    (n.ip_address || '').toLowerCase().includes(q) ||
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
            if (!IMAGE_EXISTS) return;
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            let visibleCount = 0;

            nodes.forEach(node => {
                if (!nodeMatchesSearchAndFilter(node)) return;
                visibleCount++;

                const pin = document.createElement('div');
                pin.className = 'pin';
                pin.innerHTML = PIN_SVG + `<span class="pin-label">${esc(node.device_id)}</span>`;
                pin.style.left = node.pos_x + '%';
                pin.style.top  = node.pos_y + '%';
                pin.title = `${node.device_id} [${node.status}] - ${node.type}${node.notes ? '\nNotes: ' + node.notes : ''}${node.ip_address ? '\nIP: ' + node.ip_address : ''}${node.cable_label ? '\nCable: ' + node.cable_label : ''}`;

                pin.dataset.deviceId       = node.device_id;
                pin.dataset.type           = node.type;
                pin.dataset.ip             = node.ip_address || '';
                pin.dataset.status         = node.status;
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

            renderFovCones();
        }

        // ── CLICK MAP ──
        function setupBlueprintClick() {
            const stage = document.getElementById('blueprintStage');
            if (!stage) return;

            stage.addEventListener('click', (e) => {
                if (isDraggingFovHandle) return;

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
                document.getElementById('inputType').value = 'Hikvision Dome';
                document.getElementById('inputIp').value = '';
                document.getElementById('inputStatus').value = 'Planned';
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
                renderFovCones();
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
            disableFormFields();
            renderFovCones();
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
            if (e.key === 'Escape') closePhotoLightbox();
        });

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

