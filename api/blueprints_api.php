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
    $search = trim($_GET['q'] ?? '');

    try {
        if ($user['role'] === 'admin') {
            // Admin sees ALL blueprints
            $sql = "
                SELECT b.*, u.username AS owner_username, u.full_name AS owner_name,
                       (SELECT COUNT(*) FROM nodes n WHERE n.blueprint_id = b.id) AS node_count
                FROM blueprints b
                JOIN users u ON u.id = b.uploaded_by
            ";
            $params = [];

            if ($search) {
                $sql .= " WHERE b.name LIKE ? OR b.description LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?";
                $like = "%{$search}%";
                $params = [$like, $like, $like, $like];
            }

            $sql .= " ORDER BY b.updated_at DESC";
        } else {
            // User sees own + assigned blueprints
            $sql = "
                SELECT b.*, u.username AS owner_username, u.full_name AS owner_name,
                       (SELECT COUNT(*) FROM nodes n WHERE n.blueprint_id = b.id) AS node_count
                FROM blueprints b
                JOIN users u ON u.id = b.uploaded_by
                WHERE (b.uploaded_by = ? OR b.id IN (SELECT blueprint_id FROM blueprint_access WHERE user_id = ?))
            ";
            $params = [$user['id'], $user['id']];

            if ($search) {
                $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
                $like = "%{$search}%";
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

