<?php
/**
 * /api/projects_api.php
 * Project / Client Folder Management API
 * 
 * GET:
 *   - Lists all projects with aggregated stats (blueprints count, devices count, live devices)
 *   - Query param ?id=X to fetch single project
 * 
 * POST:
 *   - Create new project: { client_name, name, code, description, status }
 *   - Update project: { action: 'update', id, client_name, name, code, description, status }
 *   - Delete project: { action: 'delete', id }
 *   - Move blueprint: { action: 'move_blueprint', blueprint_id, project_id }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireApiAuth();
$user = getCurrentUser();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $projectId = intval($_GET['id'] ?? 0);

    try {
        if ($projectId > 0) {
            $stmt = $pdo->prepare("
                SELECT p.*, u.full_name AS creator_name,
                       (SELECT COUNT(*) FROM blueprints b WHERE b.project_id = p.id) AS blueprint_count
                FROM projects p
                LEFT JOIN users u ON u.id = p.created_by
                WHERE p.id = ?
            ");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            if (!$project) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Project not found']);
                exit;
            }
            echo json_encode(['success' => true, 'project' => $project]);
            exit;
        }

        // List all projects
        $stmt = $pdo->query("
            SELECT p.*, u.full_name AS creator_name,
                   (SELECT COUNT(*) FROM blueprints b WHERE b.project_id = p.id) AS blueprint_count,
                   (SELECT COUNT(*) FROM nodes n JOIN blueprints b ON b.id = n.blueprint_id WHERE b.project_id = p.id) AS total_devices,
                   (SELECT COUNT(*) FROM nodes n JOIN blueprints b ON b.id = n.blueprint_id WHERE b.project_id = p.id AND n.status = 'Live') AS live_devices
            FROM projects p
            LEFT JOIN users u ON u.id = p.created_by
            ORDER BY p.status = 'Active' DESC, p.updated_at DESC
        ");
        $projects = $stmt->fetchAll();

        // Count unassigned blueprints
        $unassignedStmt = $pdo->query("
            SELECT COUNT(*) AS bp_count,
                   (SELECT COUNT(*) FROM nodes n JOIN blueprints b ON b.id = n.blueprint_id WHERE b.project_id IS NULL) AS total_devices
            FROM blueprints
            WHERE project_id IS NULL
        ");
        $unassigned = $unassignedStmt->fetch();

        echo json_encode([
            'success'          => true,
            'projects'         => $projects,
            'unassigned_count' => intval($unassigned['bp_count'] ?? 0),
            'unassigned_devices' => intval($unassigned['total_devices'] ?? 0)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $action = $data['action'] ?? 'create';

    // ── ACTION: MOVE BLUEPRINT TO FOLDER ──
    if ($action === 'move_blueprint') {
        $blueprintId = intval($data['blueprint_id'] ?? 0);
        $targetProjectId = !empty($data['project_id']) ? intval($data['project_id']) : null;

        if (!$blueprintId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'blueprint_id is required']);
            exit;
        }

        if (!hasAccessToBlueprint($pdo, $blueprintId, $user['id'], $user['role'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied to this blueprint']);
            exit;
        }

        try {
            $update = $pdo->prepare("UPDATE blueprints SET project_id = ? WHERE id = ?");
            $update->execute([$targetProjectId, $blueprintId]);
            echo json_encode([
                'success' => true,
                'message' => 'Blueprint moved successfully',
                'project_id' => $targetProjectId
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── ACTION: UPDATE PROJECT ──
    if ($action === 'update') {
        $id          = intval($data['id'] ?? 0);
        $clientName  = trim($data['client_name'] ?? '');
        $name        = trim($data['name'] ?? '');
        $code        = strtoupper(trim($data['code'] ?? ''));
        $description = trim($data['description'] ?? '');
        $status      = trim($data['status'] ?? 'Active');

        if (!$id || !$clientName || !$name || !$code) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id, client_name, name, and code are required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE projects
                SET client_name = ?, name = ?, code = ?, description = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$clientName, $name, $code, $description, $status, $id]);
            echo json_encode([
                'success' => true,
                'message' => "Project folder '{$code}' updated successfully"
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── ACTION: DELETE PROJECT ──
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project id is required']);
            exit;
        }

        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only administrators can delete project folders']);
            exit;
        }

        try {
            // Blueprints linked will automatically have project_id set to NULL due to ON DELETE SET NULL
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode([
                'success' => true,
                'message' => 'Project folder removed (contained blueprints are preserved as unassigned)'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── ACTION: CREATE NEW PROJECT FOLDER ──
    $clientName  = trim($data['client_name'] ?? '');
    $name        = trim($data['name'] ?? '');
    $code        = strtoupper(trim($data['code'] ?? ''));
    $description = trim($data['description'] ?? '');
    $status      = trim($data['status'] ?? 'Active');

    if (!$clientName || !$name || !$code) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Client Name, Project Name, and Project Code are required']);
        exit;
    }

    try {
        // Check duplicate code
        $check = $pdo->prepare("SELECT id FROM projects WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Project Code '{$code}' already exists. Please choose a unique code."]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO projects (client_name, name, code, description, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$clientName, $name, $code, $description, $status, $user['id']]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success'    => true,
            'message'    => "Project folder '{$name}' created successfully",
            'project_id' => $newId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

