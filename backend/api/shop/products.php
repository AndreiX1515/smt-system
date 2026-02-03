<?php
// 쇼핑몰 상품 API
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
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $category = isset($_GET['category']) ? intval($_GET['category']) : null;
            $featured = isset($_GET['featured']) ? true : false;
            $admin = isset($_GET['admin']) ? true : false; // 관리자 모드
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

            if ($id) {
                // 상품 상세
                $activeCondition = $admin ? "" : "AND p.is_active = 1";
                $stmt = $conn->prepare("SELECT p.*, c.name as category_name, c.id as category_id
                    FROM shop_products p
                    LEFT JOIN shop_categories c ON p.category_id = c.id
                    WHERE p.id = ? $activeCondition");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    if (!$admin) {
                        $updateStmt = $conn->prepare("UPDATE shop_products SET view_count = view_count + 1 WHERE id = ?");
                        $updateStmt->bind_param('i', $row['id']);
                        $updateStmt->execute();
                    }
                    echo json_encode(['success' => true, 'product' => $row], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => '상품을 찾을 수 없습니다.']);
                }
            } else {
                // 상품 목록
                $where = $admin ? ["1=1"] : ["p.is_active = 1"];
                $params = [];
                $types = "";

                if ($category) {
                    $where[] = "c.id = ?";
                    $params[] = $category;
                    $types .= "i";
                }

                if ($featured) {
                    $where[] = "p.is_featured = 1";
                }

                if ($status !== null && $status !== '') {
                    $where[] = "p.is_active = ?";
                    $params[] = intval($status);
                    $types .= "i";
                }

                if ($keyword) {
                    $where[] = "p.name LIKE ?";
                    $params[] = "%$keyword%";
                    $types .= "s";
                }

                $whereClause = implode(" AND ", $where);

                $countQuery = "SELECT COUNT(*) as total FROM shop_products p LEFT JOIN shop_categories c ON p.category_id = c.id WHERE $whereClause";
                $countStmt = $conn->prepare($countQuery);
                if (!empty($params)) {
                    $countStmt->bind_param($types, ...$params);
                }
                $countStmt->execute();
                $total = $countStmt->get_result()->fetch_assoc()['total'];

                $query = "SELECT p.*, c.name as category_name
                    FROM shop_products p
                    LEFT JOIN shop_categories c ON p.category_id = c.id
                    WHERE $whereClause
                    ORDER BY p.is_featured DESC, p.sort_order ASC, p.created_at DESC
                    LIMIT ? OFFSET ?";

                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";

                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                $products = [];
                while ($row = $result->fetch_assoc()) {
                    $products[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'products' => $products,
                    'total' => intval($total),
                    'limit' => $limit,
                    'offset' => $offset
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            $category_id = intval($input['category_id'] ?? 0);
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $price = floatval($input['price'] ?? 0);
            $sale_price = isset($input['sale_price']) && $input['sale_price'] !== null ? floatval($input['sale_price']) : null;
            $stock = intval($input['stock'] ?? 0);
            $thumbnail = $input['thumbnail'] ?? '';
            $is_active = intval($input['is_active'] ?? 1);
            $is_featured = intval($input['is_featured'] ?? 0);

            if (!$name || !$price) {
                http_response_code(400);
                echo json_encode(['error' => '필수 항목을 입력하세요.']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO shop_products
                (category_id, name, description, price, sale_price, stock, thumbnail, is_active, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issddisii',
                $category_id, $name, $description,
                $price, $sale_price, $stock, $thumbnail, $is_active, $is_featured);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => '상품이 등록되었습니다.',
                'id' => $conn->insert_id
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => '상품 ID가 필요합니다.']);
                exit();
            }

            $updates = [];
            $params = [];
            $types = "";

            if (isset($input['category_id'])) {
                $updates[] = "category_id = ?";
                $params[] = intval($input['category_id']);
                $types .= "i";
            }
            if (isset($input['name'])) {
                $updates[] = "name = ?";
                $params[] = $input['name'];
                $types .= "s";
            }
            if (isset($input['description'])) {
                $updates[] = "description = ?";
                $params[] = $input['description'];
                $types .= "s";
            }
            if (isset($input['price'])) {
                $updates[] = "price = ?";
                $params[] = floatval($input['price']);
                $types .= "d";
            }
            if (array_key_exists('sale_price', $input)) {
                $updates[] = "sale_price = ?";
                $params[] = $input['sale_price'] !== null ? floatval($input['sale_price']) : null;
                $types .= "d";
            }
            if (isset($input['stock'])) {
                $updates[] = "stock = ?";
                $params[] = intval($input['stock']);
                $types .= "i";
            }
            if (isset($input['thumbnail'])) {
                $updates[] = "thumbnail = ?";
                $params[] = $input['thumbnail'];
                $types .= "s";
            }
            if (isset($input['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = intval($input['is_active']);
                $types .= "i";
            }
            if (isset($input['is_featured'])) {
                $updates[] = "is_featured = ?";
                $params[] = intval($input['is_featured']);
                $types .= "i";
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

            $query = "UPDATE shop_products SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => '상품이 수정되었습니다.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => '상품 ID가 필요합니다.']);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM shop_products WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => '삭제되었습니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '상품을 찾을 수 없습니다.']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
