<?php
// 쇼핑몰 카테고리 API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli("localhost", "root", "cloud1234", "smarttravel");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $query = "SELECT c.*, COUNT(p.id) as product_count
                FROM shop_categories c
                LEFT JOIN shop_products p ON c.id = p.category_id AND p.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order";

            $result = $conn->query($query);

            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }

            echo json_encode([
                'success' => true,
                'categories' => $categories
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            $name = $input['name'] ?? '';
            $sort_order = intval($input['sort_order'] ?? 0);

            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => '카테고리명을 입력하세요.']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO shop_categories (name, sort_order) VALUES (?, ?)");
            $stmt->bind_param('si', $name, $sort_order);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => '카테고리가 추가되었습니다.',
                'id' => $conn->insert_id
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => '카테고리 ID가 필요합니다.']);
                exit();
            }

            $updates = [];
            $params = [];
            $types = "";

            if (isset($input['name'])) {
                $updates[] = "name = ?";
                $params[] = $input['name'];
                $types .= "s";
            }
            if (isset($input['sort_order'])) {
                $updates[] = "sort_order = ?";
                $params[] = intval($input['sort_order']);
                $types .= "i";
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => '수정할 내용이 없습니다.']);
                exit();
            }

            $params[] = $id;
            $types .= "i";

            $query = "UPDATE shop_categories SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => '카테고리가 수정되었습니다.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => '카테고리 ID가 필요합니다.']);
                exit();
            }

            // 카테고리의 상품들의 category_id를 NULL로
            $updateStmt = $conn->prepare("UPDATE shop_products SET category_id = NULL WHERE category_id = ?");
            $updateStmt->bind_param('i', $id);
            $updateStmt->execute();

            // 카테고리 삭제
            $stmt = $conn->prepare("DELETE FROM shop_categories WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => '삭제되었습니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '카테고리를 찾을 수 없습니다.']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
