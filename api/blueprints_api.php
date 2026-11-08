<?php
/**
 * /api/blueprints_api.php
 * GET  — List blueprints (filtered by role & search)
 * POST — (admin) create blueprint metadata (image handled by upload_blueprint.php)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

// ─── GET: List blueprints ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search    = trim($_GET['q'] ?? '');
    $projectId = $_GET['project_id'] ?? null;

    try {
        if ($user['role'] === 'admin') {
            // Admin sees ALL blueprints
            $sql = "
                SELECT b.*, u.username AS owner_username, u.full_name AS owner_name,
                       p.name AS project_name, p.client_name, p.code AS project_code, p.status AS project_status,
                       (SELECT COUNT(*) FROM nodes n WHERE n.blueprint_id = b.id) AS node_count
                FROM blueprints b
                JOIN users u ON u.id = b.uploaded_by
                LEFT JOIN projects p ON p.id = b.project_id
                WHERE 1=1
            ";
            $params = [];

            if ($projectId !== null && $projectId !== '') {
                if ($projectId === 'unassigned' || $projectId === '0') {
                    $sql .= " AND b.project_id IS NULL";
                } else {
                    $sql .= " AND b.project_id = ?";
                    $params[] = intval($projectId);
                }
            }

            if ($search) {
                $sql .= " AND (b.name LIKE ? OR b.description LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR p.name LIKE ? OR p.client_name LIKE ? OR p.code LIKE ?)";
                $like = "%{$search}%";
                $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
            }

            $sql .= " ORDER BY b.updated_at DESC";
        } else {
            // User sees own + assigned blueprints
            $sql = "
                SELECT b.*, u.username AS owner_username, u.full_name AS owner_name,
                       p.name AS project_name, p.client_name, p.code AS project_code, p.status AS project_status,
                       (SELECT COUNT(*) FROM nodes n WHERE n.blueprint_id = b.id) AS node_count
                FROM blueprints b
                JOIN users u ON u.id = b.uploaded_by
                LEFT JOIN projects p ON p.id = b.project_id
                WHERE (b.uploaded_by = ? OR b.id IN (SELECT blueprint_id FROM blueprint_access WHERE user_id = ?))
            ";
            $params = [$user['id'], $user['id']];

            if ($projectId !== null && $projectId !== '') {
                if ($projectId === 'unassigned' || $projectId === '0') {
                    $sql .= " AND b.project_id IS NULL";
                } else {
                    $sql .= " AND b.project_id = ?";
                    $params[] = intval($projectId);
                }
            }

            if ($search) {
                $sql .= " AND (b.name LIKE ? OR b.description LIKE ? OR p.name LIKE ? OR p.client_name LIKE ? OR p.code LIKE ?)";
                $like = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $sql .= " ORDER BY b.updated_at DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blueprints = $stmt->fetchAll();

        // For each blueprint, get node status summary
        foreach ($blueprints as &$bp) {
            $stmtS = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM nodes WHERE blueprint_id = ? GROUP BY status");
            $stmtS->execute([$bp['id']]);
            $bp['status_summary'] = $stmtS->fetchAll(PDO::FETCH_KEY_PAIR);

            // Get assigned users
            $stmtA = $pdo->prepare("
                SELECT u.id, u.username, u.full_name
                FROM blueprint_access ba
                JOIN users u ON u.id = ba.user_id
                WHERE ba.blueprint_id = ?
            ");
            $stmtA->execute([$bp['id']]);
            $bp['assigned_users'] = $stmtA->fetchAll();
        }

        echo json_encode(['success' => true, 'data' => $blueprints]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Method not allowed']);

