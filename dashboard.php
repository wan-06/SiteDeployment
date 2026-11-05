<?php
require_once __DIR__ . '/api/auth.php';
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteDeploy — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:#F3F3F3;--surface:#FFFFFF;--dark:#191A23;--dark-card:#292A32;
            --lime:#B9FF66;--lime-hover:#a8f050;--text:#191A23;--text-muted:#6b6d7b;
            --text-on-dark:#FFFFFF;--border:#E0E0E0;--red:#FF6B6B;
            --radius:14px;--radius-sm:8px;--radius-pill:100px;
            --shadow:0 2px 12px rgba(0,0,0,0.06);--shadow-lg:0 8px 32px rgba(0,0,0,0.10);
            --font:'Space Grotesk',system-ui,sans-serif;
            --transition:all 0.25s cubic-bezier(0.4,0,0.2,1);
        }
        body { font-family:var(--font); background:var(--bg); color:var(--text); line-height:1.5; }

        /* ── NAVBAR ── */
        .navbar { display:flex; align-items:center; justify-content:space-between; padding:16px 40px; background:var(--surface); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; }
        .navbar-brand { display:flex; align-items:center; gap:10px; font-weight:700; font-size:1.3rem; color:var(--text); text-decoration:none; }
        .navbar-brand svg { width:28px; height:28px; }
        .navbar-right { display:flex; align-items:center; gap:12px; }
        .user-badge { display:flex; align-items:center; gap:8px; padding:6px 16px; background:var(--bg); border-radius:var(--radius-pill); font-size:0.85rem; font-weight:500; }
        .user-badge .role-tag { background:var(--dark); color:var(--text-on-dark); padding:2px 10px; border-radius:var(--radius-pill); font-size:0.72rem; font-weight:600; text-transform:uppercase; }
        .user-badge .role-tag.admin { background:var(--lime); color:var(--dark); }

        .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 24px; border-radius:var(--radius-pill); border:2px solid var(--dark); background:transparent; color:var(--text); font-family:var(--font); font-weight:600; font-size:0.9rem; cursor:pointer; transition:var(--transition); text-decoration:none; }
        .btn:hover { background:var(--dark); color:var(--text-on-dark); }
        .btn-lime { background:var(--lime); border-color:var(--lime); color:var(--dark); }
        .btn-lime:hover { background:var(--lime-hover); border-color:var(--lime-hover); }
        .btn-dark { background:var(--dark); border-color:var(--dark); color:var(--text-on-dark); }
        .btn-dark:hover { background:#2d2e3a; }
        .btn-danger { border-color:var(--red); color:var(--red); }
        .btn-danger:hover { background:var(--red); color:#fff; }
        .btn-sm { padding:6px 16px; font-size:0.82rem; }
        .btn-ghost { border:none; padding:8px 16px; }
        .btn-ghost:hover { background:var(--bg); color:var(--text); }

        /* ── HERO ── */
        .hero { padding:48px 40px 32px; }
        .hero h1 { font-size:2.4rem; font-weight:700; line-height:1.15; margin-bottom:8px; }
        .hero h1 .hl { background:var(--lime); padding:2px 12px; border-radius:var(--radius-sm); }
        .hero p { font-size:1rem; color:var(--text-muted); }

        /* ── TABS ── */
        .tabs { display:flex; gap:4px; padding:0 40px 24px; }
        .tab { padding:10px 24px; border-radius:var(--radius-pill); font-weight:600; font-size:0.9rem; cursor:pointer; transition:var(--transition); border:none; background:transparent; color:var(--text-muted); font-family:var(--font); }
        .tab:hover { background:var(--surface); color:var(--text); }
        .tab.active { background:var(--dark); color:var(--text-on-dark); }

        /* ── TOOLBAR ── */
        .toolbar { display:flex; align-items:center; justify-content:space-between; padding:0 40px 24px; flex-wrap:wrap; gap:12px; }
        .search-box { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-pill); padding:10px 20px; min-width:300px; }
        .search-box svg { width:16px; height:16px; opacity:0.4; flex-shrink:0; }
        .search-box input { border:none; background:none; outline:none; font-family:var(--font); font-size:0.9rem; color:var(--text); width:100%; }
        .toolbar-right { display:flex; gap:8px; }

        /* ── BLUEPRINT CARDS GRID ── */
        .cards-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:20px; padding:0 40px 40px; }

        .bp-card {
            background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
            overflow:hidden; transition:var(--transition); cursor:pointer; position:relative;
        }
        .bp-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }

        .bp-card-img { width:100%; height:180px; object-fit:cover; background:var(--dark); display:block; }
        .bp-card-body { padding:20px; }
        .bp-card-body h3 { font-size:1.1rem; font-weight:700; margin-bottom:4px; }
        .bp-card-body .bp-desc { font-size:0.85rem; color:var(--text-muted); margin-bottom:12px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .bp-card-body .bp-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .bp-meta-item { display:flex; align-items:center; gap:4px; font-size:0.78rem; color:var(--text-muted); }
        .bp-meta-item svg { width:14px; height:14px; }

        .bp-card-footer {
            padding: 12px 18px 14px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fafaf7;
        }
        .bp-footer-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .bp-footer-meta .owner {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bp-footer-meta .owner strong {
            color: var(--dark);
            font-weight: 600;
        }
        .bp-footer-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        .bp-btn-report {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--dark);
            font-weight: 600;
            font-size: 0.82rem;
            padding: 7px 12px;
            border-radius: var(--radius-pill);
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }
        .bp-btn-report:hover {
            background: var(--lime);
            border-color: var(--dark);
            color: var(--dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .bp-btn-share, .bp-btn-delete {
            padding: 7px 12px;
            font-size: 0.82rem;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            transition: var(--transition);
        }
        .bp-btn-delete {
            border: 1.5px solid rgba(255, 107, 107, 0.4);
            color: var(--red);
            background: transparent;
        }
        .bp-btn-delete:hover {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
        }

        .status-dots { display:flex; gap:4px; }
        .status-dot { width:8px; height:8px; border-radius:50%; }

        /* ── USERS TABLE (admin tab) ── */
        .table-container { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin:0 40px 40px; }
        table { width:100%; border-collapse:collapse; }
        thead th { padding:12px 20px; text-align:left; font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); background:var(--bg); border-bottom:1px solid var(--border); }
        tbody td { padding:14px 20px; font-size:0.88rem; border-bottom:1px solid var(--border); }
        tbody tr:hover { background:#f7f8fa; }
        tbody tr:last-child td { border-bottom:none; }
        .role-badge { display:inline-flex; padding:3px 12px; border-radius:var(--radius-pill); font-size:0.75rem; font-weight:600; text-transform:uppercase; }
        .role-badge.admin { background:var(--lime); color:var(--dark); }
        .role-badge.user { background:var(--bg); color:var(--text-muted); border:1px solid var(--border); }

        /* ── MODAL ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
        .modal-overlay.show { display:flex; }
        .modal { background:var(--surface); border-radius:var(--radius); width:100%; max-width:520px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-lg); }
        .modal-header { padding:24px 28px 0; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h3 { font-size:1.2rem; font-weight:700; }
        .modal-close { width:32px; height:32px; border:none; background:var(--bg); border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.2rem; transition:var(--transition); }
        .modal-close:hover { background:var(--dark); color:var(--text-on-dark); }
        .modal-body { padding:24px 28px; }
        .modal-footer { padding:16px 28px 24px; display:flex; gap:8px; justify-content:flex-end; }

        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-bottom:6px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:10px 14px; border:2px solid var(--border); border-radius:var(--radius-sm);
            font-family:var(--font); font-size:0.9rem; color:var(--text); outline:none; transition:var(--transition); background:var(--bg);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--lime); background:var(--surface); }
        .form-group textarea { resize:vertical; min-height:70px; }
        .form-group .file-upload { border:2px dashed var(--border); border-radius:var(--radius-sm); padding:30px; text-align:center; cursor:pointer; transition:var(--transition); background:var(--bg); }
        .form-group .file-upload:hover { border-color:var(--lime); background:#f8fff0; }
        .form-group .file-upload.has-file { border-color:var(--lime); background:#f0ffe0; }
        .form-group .file-upload input[type="file"] { display:none; }
        .form-group .file-upload p { font-size:0.88rem; color:var(--text-muted); }
        .form-group .file-upload .fname { font-weight:600; color:var(--text); margin-top:4px; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:80px 20px; }
        .empty-state svg { width:64px; height:64px; opacity:0.2; margin-bottom:16px; }
        .empty-state h3 { font-size:1.2rem; margin-bottom:8px; }
        .empty-state p { color:var(--text-muted); font-size:0.92rem; margin-bottom:20px; }

        /* ── ACCESS MODAL ── */
        .access-list { list-style:none; max-height:200px; overflow-y:auto; }
        .access-list li { display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border); }
        .access-list li:last-child { border:none; }
        .access-user-info { display:flex; align-items:center; gap:8px; }
        .access-avatar { width:28px; height:28px; border-radius:50%; background:var(--dark); color:var(--text-on-dark); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700; }

        /* ── TOAST ── */
        .toast-container { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
        .toast { background:var(--dark); color:var(--text-on-dark); padding:14px 24px; border-radius:var(--radius); font-size:0.88rem; font-weight:500; box-shadow:var(--shadow-lg); display:flex; align-items:center; gap:10px; animation:toastIn .3s ease,toastOut .3s ease 2.7s forwards; max-width:360px; }
        .toast.success { border-left:4px solid var(--lime); }
        .toast.error { border-left:4px solid var(--red); }
        @keyframes toastIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        @keyframes toastOut { from{opacity:1} to{opacity:0;transform:translateY(-8px)} }

        .tab-content { display:none; }
        .tab-content.active { display:block; }

        @media (max-width:768px) {
            .hero,.toolbar,.tabs,.cards-grid,.table-container { padding-left:20px; padding-right:20px; }
            .navbar { padding:12px 20px; }
            .hero h1 { font-size:1.8rem; }
            .cards-grid { grid-template-columns:1fr; }
            .search-box { min-width:auto; flex:1; }
        }
    </style>
</head>
<body>
    <!-- ═══ NAVBAR ═══ -->
    <nav class="navbar">
        <a class="navbar-brand" href="dashboard.php">
            <svg viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="6" fill="#191A23"/><path d="M7 14h14M14 7v14M9 9l10 10M19 9L9 19" stroke="#B9FF66" stroke-width="2" stroke-linecap="round"/></svg>
            SiteDeploy
        </a>
        <div class="navbar-right">
            <div class="user-badge">
                <span><?= htmlspecialchars($user['full_name']) ?></span>
                <span class="role-tag <?= $user['role'] ?>"><?= $user['role'] ?></span>
            </div>
            <a href="logout.php" class="btn btn-sm btn-ghost" title="Sign Out">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </nav>

    <!-- ═══ HERO ═══ -->
    <section class="hero">
        <h1>Welcome, <span class="hl"><?= htmlspecialchars($user['full_name']) ?></span></h1>
        <p><?= isAdmin() ? 'Manage all blueprints, users, and site deployments from here.' : 'View and manage your assigned site deployment blueprints.' ?></p>
    </section>

    <!-- ═══ TABS ═══ -->
    <div class="tabs">
        <button class="tab active" data-tab="blueprints">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Blueprints
        </button>
        <?php if (isAdmin()): ?>
        <button class="tab" data-tab="users">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px; margin-right:4px;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Users
        </button>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB: BLUEPRINTS ═══ -->
    <div class="tab-content active" id="tab-blueprints">
        <div class="toolbar">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchBlueprints" placeholder="Search by name, status (Live, Mounted, Cable, Planned), owner...">
            </div>
            <div class="toolbar-right">
                <button class="btn btn-lime" onclick="openUploadModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New Blueprint
                </button>
            </div>
        </div>
        <div class="cards-grid" id="blueprintsGrid">
            <div class="empty-state" style="grid-column:1/-1;">
                <p>Loading blueprints...</p>
            </div>
        </div>
    </div>

    <!-- ═══ TAB: USERS (admin only) ═══ -->
    <?php if (isAdmin()): ?>
    <div class="tab-content" id="tab-users">
        <div class="toolbar">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchUsers" placeholder="Search users by name, username, or email...">
            </div>
            <div class="toolbar-right">
                <button class="btn btn-dark" onclick="openCreateUserModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    Create User
                </button>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead><tr>
                    <th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Created</th>
                </tr></thead>
                <tbody id="usersTableBody"><tr><td colspan="5" style="text-align:center;padding:40px;">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ MODAL: Upload Blueprint ═══ -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Upload New Blueprint</h3>
                <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Blueprint Name *</label>
                    <input type="text" id="bpName" placeholder="e.g. Building A — Ground Floor">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="bpDesc" placeholder="Optional notes about this blueprint..."></textarea>
                </div>
                <div class="form-group">
                    <label>Floorplan Image *</label>
                    <div class="file-upload" id="fileUploadZone" onclick="document.getElementById('bpFile').click()">
                        <input type="file" id="bpFile" accept="image/*" onchange="handleFileSelect(this)">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        <p>Click to select an image</p>
                        <p style="font-size:0.75rem; opacity:0.5;">JPG, PNG, WEBP — Max 20 MB</p>
                        <div class="fname" id="fileName"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm" onclick="closeModal('uploadModal')">Cancel</button>
                <button class="btn btn-lime btn-sm" onclick="uploadBlueprint()">Upload Blueprint</button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Create User (admin) ═══ -->
    <div class="modal-overlay" id="createUserModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Create New User</h3>
                <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label>Username *</label><input type="text" id="newUsername" placeholder="e.g. tech_ali"></div>
                <div class="form-group"><label>Full Name *</label><input type="text" id="newFullName" placeholder="e.g. Ali Bin Ahmad"></div>
                <div class="form-group"><label>Email *</label><input type="email" id="newEmail" placeholder="e.g. ali@company.com"></div>
                <div class="form-group"><label>Password *</label><input type="password" id="newPassword" placeholder="Min 6 characters"></div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="newRole"><option value="user">User</option><option value="admin">Admin</option></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm" onclick="closeModal('createUserModal')">Cancel</button>
                <button class="btn btn-dark btn-sm" onclick="createUser()">Create User</button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Manage Access ═══ -->
    <div class="modal-overlay" id="accessModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Manage Access — <span id="accessBpName"></span></h3>
                <button class="modal-close" onclick="closeModal('accessModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">Grant or revoke user access to this blueprint.</p>
                <div class="form-group">
                    <label>Add User</label>
                    <div style="display:flex; gap:8px;">
                        <select id="accessUserSelect" style="flex:1;"><option value="">Select a user...</option></select>
                        <button class="btn btn-lime btn-sm" onclick="grantAccess()">Grant</button>
                    </div>
                </div>
                <label style="font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); display:block; margin-bottom:8px;">Current Access</label>
                <ul class="access-list" id="accessList"></ul>
            </div>
        </div>
    </div>

    <!-- ═══ TOAST ═══ -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ═══ JAVASCRIPT ═══ -->
    <script>
        const CURRENT_USER = <?= json_encode($user) ?>;
        const IS_ADMIN = CURRENT_USER.role === 'admin';
        let allBlueprints = [];
        let allUsers = [];
        let currentAccessBpId = null;

        // ── INIT ──
        document.addEventListener('DOMContentLoaded', () => {
            loadBlueprints();
            if (IS_ADMIN) loadUsers();
            setupTabs();
            setupSearch();
        });

        // ── TABS ──
        function setupTabs() {
            document.querySelectorAll('.tab').forEach(t => {
                t.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
                    t.classList.add('active');
                    document.getElementById('tab-' + t.dataset.tab).classList.add('active');
                });
            });
        }

        // ── SEARCH ──
        function setupSearch() {
            document.getElementById('searchBlueprints').addEventListener('input', () => renderBlueprints());
            const su = document.getElementById('searchUsers');
            if (su) su.addEventListener('input', () => renderUsers());
        }

        // ── LOAD BLUEPRINTS ──
        async function loadBlueprints() {
            try {
                const res = await fetch('api/blueprints_api.php');
                const json = await res.json();
                if (json.success) { allBlueprints = json.data; renderBlueprints(); }
            } catch(e) { showToast('Failed to load blueprints','error'); }
        }

        // ── RENDER BLUEPRINT CARDS ──
        function renderBlueprints() {
            const q = document.getElementById('searchBlueprints').value.toLowerCase().trim();
            let filtered = allBlueprints;
            if (q) {
                filtered = allBlueprints.filter(b => {
                    const nameMatch = (b.name || '').toLowerCase().includes(q);
                    const descMatch = (b.description || '').toLowerCase().includes(q);
                    const ownerMatch = (b.owner_username || '').toLowerCase().includes(q) || (b.owner_name || '').toLowerCase().includes(q);

                    // Match node status (e.g. searching "live", "mounted", "cable", "planned")
                    const ss = b.status_summary || {};
                    const statusMatch = Object.keys(ss).some(st =>
                        st.toLowerCase().includes(q) && parseInt(ss[st]) > 0
                    );

                    return nameMatch || descMatch || ownerMatch || statusMatch;
                });
            }

            const grid = document.getElementById('blueprintsGrid');

            if (filtered.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state" style="grid-column:1/-1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        <h3>${q ? 'No blueprints match your search' : 'No blueprints yet'}</h3>
                        <p>${q ? 'Try a different search term (e.g. status "Live", name, or owner)' : 'Upload your first floorplan to get started'}</p>
                        ${q ? '' : '<button class="btn btn-lime" onclick="openUploadModal()">Upload Blueprint</button>'}
                    </div>`;
                return;
            }

            grid.innerHTML = filtered.map(b => {
                const ss = b.status_summary || {};
                const nodeCount = parseInt(b.node_count) || 0;
                const liveCount = parseInt(ss['Live'])||0;
                const mountedCount = parseInt(ss['Hardware Mounted'])||0;
                const cableCount = parseInt(ss['Cable Pulled'])||0;
                const plannedCount = parseInt(ss['Planned'])||0;

                return `
                <div class="bp-card" onclick="openBlueprint(${b.id})">
                    <img class="bp-card-img" src="uploads/blueprints/${esc(b.image_filename)}" alt="${esc(b.name)}" onerror="this.style.background='var(--dark)'; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22/>';">
                    <div class="bp-card-body">
                        <h3>${esc(b.name)}</h3>
                        <div class="bp-desc">${esc(b.description || 'No description')}</div>
                        <div class="bp-meta">
                            <span class="bp-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                ${nodeCount} node${nodeCount!==1?'s':''}
                            </span>
                            <span class="bp-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="8.5" cy="7" r="4"/></svg>
                                ${(b.assigned_users||[]).length + 1} user${(b.assigned_users||[]).length>0?'s':''}
                            </span>
                            <div class="status-dots">
                                ${liveCount ? `<span class="status-dot" style="background:#22c55e" title="${liveCount} Live"></span>` : ''}
                                ${mountedCount ? `<span class="status-dot" style="background:#f59e0b" title="${mountedCount} Mounted"></span>` : ''}
                                ${cableCount ? `<span class="status-dot" style="background:#3b82f6" title="${cableCount} Cable"></span>` : ''}
                                ${plannedCount ? `<span class="status-dot" style="background:#ef4444" title="${plannedCount} Planned"></span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="bp-card-footer" onclick="event.stopPropagation()">
                        <div class="bp-footer-meta">
                            <span class="owner">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.6;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4-4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                by <strong>${esc(b.owner_name||b.owner_username)}</strong>
                            </span>
                        </div>
                        <div class="bp-footer-actions">
                            <a href="handover_report.php?id=${b.id}" target="_blank" class="bp-btn-report" title="View & Print T&C As-Built Handover Report">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <span>T&amp;C Report</span>
                            </a>
                            ${IS_ADMIN || b.uploaded_by == CURRENT_USER.id ? `
                                <button class="btn btn-sm btn-ghost bp-btn-share" onclick="openAccessModal(${b.id}, '${escAttr(b.name)}')" title="Manage Access">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                    <span>Share</span>
                                </button>
                                <button class="btn btn-sm bp-btn-delete" onclick="deleteBlueprint(${b.id}, '${escAttr(b.name)}')" title="Delete Blueprint">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    <span>Delete</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        // ── OPEN BLUEPRINT MAP ──
        function openBlueprint(id) { window.location.href = 'map.php?id=' + id; }

        // ── UPLOAD BLUEPRINT ──
        function openUploadModal() {
            document.getElementById('bpName').value = '';
            document.getElementById('bpDesc').value = '';
            document.getElementById('bpFile').value = '';
            document.getElementById('fileName').textContent = '';
            document.getElementById('fileUploadZone').classList.remove('has-file');
            openModal('uploadModal');
        }

        function handleFileSelect(input) {
            const zone = document.getElementById('fileUploadZone');
            const nameEl = document.getElementById('fileName');
            if (input.files[0]) {
                nameEl.textContent = input.files[0].name;
                zone.classList.add('has-file');
            } else {
                nameEl.textContent = '';
                zone.classList.remove('has-file');
            }
        }

        async function uploadBlueprint() {
            const name = document.getElementById('bpName').value.trim();
            const desc = document.getElementById('bpDesc').value.trim();
            const file = document.getElementById('bpFile').files[0];

            if (!name) { showToast('Blueprint name is required','error'); return; }
            if (!file) { showToast('Please select a floorplan image','error'); return; }

            const fd = new FormData();
            fd.append('name', name);
            fd.append('description', desc);
            fd.append('image', file);

            try {
                const res = await fetch('api/upload_blueprint.php', { method:'POST', body:fd });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    closeModal('uploadModal');
                    loadBlueprints();
                } else {
                    showToast(json.error||'Upload failed','error');
                }
            } catch(e) { showToast('Network error','error'); }
        }

        // ── DELETE BLUEPRINT ──
        async function deleteBlueprint(id, name) {
            if (!confirm('Delete blueprint "' + name + '"? All nodes and access will be removed.')) return;
            try {
                const res = await fetch('api/delete_blueprint.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({blueprint_id: id})
                });
                const json = await res.json();
                if (json.success) { showToast('Blueprint deleted','success'); loadBlueprints(); }
                else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── ACCESS MANAGEMENT ──
        async function openAccessModal(bpId, bpName) {
            currentAccessBpId = bpId;
            document.getElementById('accessBpName').textContent = bpName;
            openModal('accessModal');

            // Load users for the dropdown
            try {
                const [accessRes, usersRes] = await Promise.all([
                    fetch('api/manage_access.php', {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({action:'list', blueprint_id:bpId})
                    }),
                    fetch('api/users_api.php')
                ]);
                const accessJson = await accessRes.json();
                const usersJson  = await usersRes.json();

                // Render access list
                const list = document.getElementById('accessList');
                if (accessJson.success && accessJson.data.length) {
                    list.innerHTML = accessJson.data.map(u => `
                        <li>
                            <div class="access-user-info">
                                <div class="access-avatar">${(u.full_name||u.username).charAt(0).toUpperCase()}</div>
                                <div>
                                    <div style="font-size:0.88rem;font-weight:600;">${esc(u.full_name)}</div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">@${esc(u.username)} · ${u.access_type}</div>
                                </div>
                            </div>
                            ${u.access_type !== 'owner' ? `<button class="btn btn-sm btn-danger" onclick="revokeAccess(${u.id})">Revoke</button>` : '<span class="role-badge admin">Owner</span>'}
                        </li>
                    `).join('');
                } else {
                    list.innerHTML = '<li style="text-align:center;color:var(--text-muted);padding:20px;">Only the owner has access</li>';
                }

                // Populate user dropdown (exclude users who already have access)
                const existingIds = (accessJson.data||[]).map(u => u.id);
                const select = document.getElementById('accessUserSelect');
                select.innerHTML = '<option value="">Select a user...</option>';
                if (usersJson.success) {
                    usersJson.data.filter(u => !existingIds.includes(u.id)).forEach(u => {
                        select.innerHTML += `<option value="${u.id}">${esc(u.full_name)} (@${esc(u.username)})</option>`;
                    });
                }
            } catch(e) { showToast('Error loading access data','error'); }
        }

        async function grantAccess() {
            const userId = document.getElementById('accessUserSelect').value;
            if (!userId) { showToast('Select a user first','error'); return; }
            try {
                const res = await fetch('api/manage_access.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({action:'grant', blueprint_id:currentAccessBpId, user_id:parseInt(userId)})
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Access granted','success');
                    const bpName = document.getElementById('accessBpName').textContent;
                    openAccessModal(currentAccessBpId, bpName);
                    loadBlueprints();
                } else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        async function revokeAccess(userId) {
            if (!confirm('Revoke access for this user?')) return;
            try {
                const res = await fetch('api/manage_access.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({action:'revoke', blueprint_id:currentAccessBpId, user_id:userId})
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Access revoked','success');
                    const bpName = document.getElementById('accessBpName').textContent;
                    openAccessModal(currentAccessBpId, bpName);
                    loadBlueprints();
                } else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── USERS TAB (admin) ──
        async function loadUsers() {
            try {
                const res = await fetch('api/users_api.php');
                const json = await res.json();
                if (json.success) { allUsers = json.data; renderUsers(); }
            } catch(e) { showToast('Failed to load users','error'); }
        }

        function renderUsers() {
            const q = (document.getElementById('searchUsers')?.value||'').toLowerCase();
            let filtered = allUsers;
            if (q) {
                filtered = allUsers.filter(u =>
                    u.username.toLowerCase().includes(q) || u.full_name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
                );
            }
            const tbody = document.getElementById('usersTableBody');
            if (!filtered.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">No users found</td></tr>';
                return;
            }
            tbody.innerHTML = filtered.map(u => `
                <tr>
                    <td><strong>@${esc(u.username)}</strong></td>
                    <td>${esc(u.full_name)}</td>
                    <td style="font-size:0.82rem;color:var(--text-muted);">${esc(u.email)}</td>
                    <td><span class="role-badge ${u.role}">${u.role}</span></td>
                    <td style="font-size:0.82rem;color:var(--text-muted);">${formatDate(u.created_at)}</td>
                </tr>
            `).join('');
        }

        // ── CREATE USER ──
        function openCreateUserModal() { openModal('createUserModal'); }

        async function createUser() {
            const data = {
                username:  document.getElementById('newUsername').value.trim(),
                full_name: document.getElementById('newFullName').value.trim(),
                email:     document.getElementById('newEmail').value.trim(),
                password:  document.getElementById('newPassword').value,
                role:      document.getElementById('newRole').value,
            };
            if (!data.username||!data.full_name||!data.email||!data.password) {
                showToast('All fields are required','error'); return;
            }
            try {
                const res = await fetch('api/register_process.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify(data)
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message,'success');
                    closeModal('createUserModal');
                    loadUsers();
                    // Clear form
                    ['newUsername','newFullName','newEmail','newPassword'].forEach(id => document.getElementById(id).value='');
                } else showToast(json.error,'error');
            } catch(e) { showToast('Network error','error'); }
        }

        // ── MODAL HELPERS ──
        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', (e) => { if(e.target===m) m.classList.remove('show'); });
        });

        // ── TOAST ──
        function showToast(msg, type='info') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            const icons = {success:'✓', error:'✕', info:'ℹ'};
            t.innerHTML = `<span style="font-size:1.1rem;">${icons[type]||'ℹ'}</span> ${esc(msg)}`;
            c.appendChild(t);
            setTimeout(()=>t.remove(), 3200);
        }

        // ── HELPERS ──
        function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
        function escAttr(s) { return (s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }
        function formatDate(d) { if(!d) return '—'; return new Date(d).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); }
    </script>
</body>
</html>

