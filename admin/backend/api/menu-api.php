<?php
/**
 * Menu Permission API
 * 메뉴 권한 관리 API
 */

require_once __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Helper function to check admin types
function isAdminType($type) {
    return in_array($type, ['admin_ph', 'admin_kr'], true);
}

// Get current user's role from session
function getCurrentUserRole() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['admin_userType'] ?? null;
}

// Check if user is authenticated as admin
function requireAdminAuth() {
    $role = getCurrentUserRole();
    if (!$role || !isAdminType($role)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
        exit;
    }
    return $role;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'getMenus':
        // 현재 사용자 권한에 맞는 메뉴 목록 반환
        getMenusForCurrentUser();
        break;

    case 'getAllMenus':
        // 전체 메뉴 목록 (관리용) - admin_kr만 접근 가능
        getAllMenus();
        break;

    case 'getPermissions':
        // 역할별 권한 설정 조회 - admin_kr만 접근 가능
        getPermissions();
        break;

    case 'savePermissions':
        // 권한 설정 저장 - admin_kr만 접근 가능
        savePermissions();
        break;

    case 'getRoles':
        // 역할 목록 조회
        getRoles();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

/**
 * 현재 사용자 권한에 맞는 메뉴 목록 반환
 */
function getMenusForCurrentUser() {
    global $conn;

    $role = requireAdminAuth();

    try {
        $stmt = $conn->prepare("
            SELECT m.menu_id, m.menu_name, m.menu_name_en, m.parent_menu_id,
                   m.menu_order, m.menu_url, m.is_active
            FROM admin_menus m
            INNER JOIN admin_menu_permissions p ON m.menu_id = p.menu_id
            WHERE p.role = ? AND p.can_access = 1 AND m.is_active = 1
            ORDER BY m.menu_order, m.menu_id
        ");

        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();

        $menus = [];
        while ($row = $result->fetch_assoc()) {
            $menus[] = $row;
        }
        $stmt->close();

        // 메뉴 ID 배열만 반환 (프론트엔드 필터링용)
        $menuIds = array_column($menus, 'menu_id');

        echo json_encode([
            'success' => true,
            'role' => $role,
            'menuIds' => $menuIds,
            'menus' => $menus
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 전체 메뉴 목록 조회 (관리용)
 */
function getAllMenus() {
    global $conn;

    $role = requireAdminAuth();

    // admin_kr만 접근 가능
    if ($role !== 'admin_kr') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Only admin_kr can access this feature.']);
        exit;
    }

    try {
        $result = $conn->query("
            SELECT menu_id, menu_name, menu_name_en, parent_menu_id,
                   menu_order, menu_url, is_active
            FROM admin_menus
            ORDER BY menu_order, menu_id
        ");

        $menus = [];
        while ($row = $result->fetch_assoc()) {
            $menus[] = $row;
        }

        echo json_encode([
            'success' => true,
            'menus' => $menus
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 역할별 권한 설정 조회
 */
function getPermissions() {
    global $conn;

    $role = requireAdminAuth();

    // admin_kr만 접근 가능
    if ($role !== 'admin_kr') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Only admin_kr can access this feature.']);
        exit;
    }

    try {
        // 전체 메뉴 목록
        $menusResult = $conn->query("
            SELECT menu_id, menu_name, menu_name_en, parent_menu_id, menu_order
            FROM admin_menus
            WHERE is_active = 1
            ORDER BY menu_order, menu_id
        ");

        $menus = [];
        while ($row = $menusResult->fetch_assoc()) {
            $menus[] = $row;
        }

        // 권한 설정
        $permResult = $conn->query("
            SELECT role, menu_id, can_access
            FROM admin_menu_permissions
        ");

        $permissions = [];
        while ($row = $permResult->fetch_assoc()) {
            if (!isset($permissions[$row['role']])) {
                $permissions[$row['role']] = [];
            }
            $permissions[$row['role']][$row['menu_id']] = (int)$row['can_access'];
        }

        echo json_encode([
            'success' => true,
            'menus' => $menus,
            'permissions' => $permissions,
            'roles' => ['admin_ph', 'admin_kr']
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 권한 설정 저장
 */
function savePermissions() {
    global $conn;

    $role = requireAdminAuth();

    // admin_kr만 접근 가능
    if ($role !== 'admin_kr') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Only admin_kr can access this feature.']);
        exit;
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['permissions']) || !is_array($input['permissions'])) {
            throw new Exception('Invalid permissions data');
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO admin_menu_permissions (role, menu_id, can_access)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), updated_at = NOW()
        ");

        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        foreach ($input['permissions'] as $targetRole => $menuPermissions) {
            // admin_kr의 menu-permission 권한은 항상 유지 (자기 자신 잠금 방지)
            foreach ($menuPermissions as $menuId => $canAccess) {
                if ($targetRole === 'admin_kr' && $menuId === 'menu-permission') {
                    $canAccess = 1; // 항상 접근 가능
                }
                $canAccessInt = $canAccess ? 1 : 0;
                $stmt->bind_param('ssi', $targetRole, $menuId, $canAccessInt);
                $stmt->execute();
            }
        }

        $stmt->close();
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Permissions saved successfully'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 역할 목록 조회
 */
function getRoles() {
    requireAdminAuth();

    echo json_encode([
        'success' => true,
        'roles' => [
            ['id' => 'admin_ph', 'name' => 'Admin (Philippines)'],
            ['id' => 'admin_kr', 'name' => 'Admin (Korea)']
        ]
    ], JSON_UNESCAPED_UNICODE);
}
