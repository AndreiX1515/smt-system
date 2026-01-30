<?php
// 쇼핑몰 장바구니 API
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

// 사용자 ID (실제로는 세션/토큰에서 가져와야 함)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$input = json_decode(file_get_contents('php://input'), true);
if (!$user_id && isset($input['user_id'])) {
    $user_id = intval($input['user_id']);
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 장바구니 조회
            $query = "SELECT c.*, p.name, p.price, p.sale_price, p.thumbnail, p.stock
                FROM shop_cart c
                JOIN shop_products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.is_active = 1
                ORDER BY c.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            $total = 0;
            while ($row = $result->fetch_assoc()) {
                $price = $row['sale_price'] ?: $row['price'];
                $row['subtotal'] = $price * $row['quantity'];
                $total += $row['subtotal'];
                $items[] = $row;
            }

            echo json_encode([
                'success' => true,
                'items' => $items,
                'total' => $total,
                'count' => count($items)
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'POST':
            // 장바구니 추가
            $product_id = intval($input['product_id'] ?? 0);
            $quantity = intval($input['quantity'] ?? 1);

            if (!$product_id || $quantity < 1) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 요청입니다.']);
                exit();
            }

            // 재고 확인
            $stockStmt = $conn->prepare("SELECT stock FROM shop_products WHERE id = ? AND is_active = 1");
            $stockStmt->bind_param('i', $product_id);
            $stockStmt->execute();
            $stockResult = $stockStmt->get_result()->fetch_assoc();

            if (!$stockResult) {
                http_response_code(404);
                echo json_encode(['error' => '상품을 찾을 수 없습니다.']);
                exit();
            }

            if ($stockResult['stock'] < $quantity) {
                http_response_code(400);
                echo json_encode(['error' => '재고가 부족합니다.']);
                exit();
            }

            // 이미 장바구니에 있으면 수량 추가
            $stmt = $conn->prepare("INSERT INTO shop_cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $stmt->bind_param('iii', $user_id, $product_id, $quantity);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => '장바구니에 추가되었습니다.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'PUT':
            // 수량 변경
            $cart_id = intval($input['cart_id'] ?? 0);
            $quantity = intval($input['quantity'] ?? 0);

            if (!$cart_id || $quantity < 1) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 요청입니다.']);
                exit();
            }

            $stmt = $conn->prepare("UPDATE shop_cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('iii', $quantity, $cart_id, $user_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => '수량이 변경되었습니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '장바구니 항목을 찾을 수 없습니다.']);
            }
            break;

        case 'DELETE':
            // 장바구니 삭제
            $cart_id = intval($_GET['cart_id'] ?? ($input['cart_id'] ?? 0));

            if ($cart_id) {
                $stmt = $conn->prepare("DELETE FROM shop_cart WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $cart_id, $user_id);
            } else {
                // 전체 삭제
                $stmt = $conn->prepare("DELETE FROM shop_cart WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
            }
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => '삭제되었습니다.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
