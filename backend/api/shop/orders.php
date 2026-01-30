<?php
// 쇼핑몰 주문 API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
            $order_id = isset($_GET['id']) ? intval($_GET['id']) : null;

            if ($order_id) {
                // 주문 상세
                $stmt = $conn->prepare("SELECT * FROM shop_orders WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $order_id, $user_id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();

                if (!$order) {
                    http_response_code(404);
                    echo json_encode(['error' => '주문을 찾을 수 없습니다.']);
                    exit();
                }

                // 주문 상품
                $itemStmt = $conn->prepare("SELECT oi.*, p.thumbnail
                    FROM shop_order_items oi
                    JOIN shop_products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?");
                $itemStmt->bind_param('i', $order_id);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();

                $items = [];
                while ($row = $itemResult->fetch_assoc()) {
                    $items[] = $row;
                }
                $order['items'] = $items;

                echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
            } else {
                // 주문 목록
                $stmt = $conn->prepare("SELECT * FROM shop_orders WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $orders = [];
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }

                echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'POST':
            // 주문 생성
            $receiver_name = $input['receiver_name'] ?? '';
            $receiver_phone = $input['receiver_phone'] ?? '';
            $receiver_address = $input['receiver_address'] ?? '';
            $receiver_zipcode = $input['receiver_zipcode'] ?? '';
            $memo = $input['memo'] ?? '';
            $shipping_fee = floatval($input['shipping_fee'] ?? 0);

            if (!$receiver_name || !$receiver_phone || !$receiver_address) {
                http_response_code(400);
                echo json_encode(['error' => '배송 정보를 입력해주세요.']);
                exit();
            }

            // 장바구니 조회
            $cartQuery = "SELECT c.*, p.name, p.price, p.sale_price, p.stock
                FROM shop_cart c
                JOIN shop_products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.is_active = 1";
            $cartStmt = $conn->prepare($cartQuery);
            $cartStmt->bind_param('i', $user_id);
            $cartStmt->execute();
            $cartResult = $cartStmt->get_result();

            $cartItems = [];
            $total = 0;
            while ($row = $cartResult->fetch_assoc()) {
                if ($row['stock'] < $row['quantity']) {
                    http_response_code(400);
                    echo json_encode(['error' => "'{$row['name']}' 상품의 재고가 부족합니다."]);
                    exit();
                }
                $price = $row['sale_price'] ?: $row['price'];
                $row['final_price'] = $price;
                $total += $price * $row['quantity'];
                $cartItems[] = $row;
            }

            if (empty($cartItems)) {
                http_response_code(400);
                echo json_encode(['error' => '장바구니가 비어있습니다.']);
                exit();
            }

            $conn->begin_transaction();

            try {
                // 주문번호 생성
                $order_no = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $total_amount = $total + $shipping_fee;

                // 주문 생성
                $orderStmt = $conn->prepare("INSERT INTO shop_orders
                    (order_no, user_id, total_amount, shipping_fee, receiver_name, receiver_phone, receiver_address, receiver_zipcode, memo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $orderStmt->bind_param('siddsssss', $order_no, $user_id, $total_amount, $shipping_fee,
                    $receiver_name, $receiver_phone, $receiver_address, $receiver_zipcode, $memo);
                $orderStmt->execute();
                $order_id = $conn->insert_id;

                // 주문 상품 추가 & 재고 차감
                $itemStmt = $conn->prepare("INSERT INTO shop_order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
                $stockStmt = $conn->prepare("UPDATE shop_products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ?");

                foreach ($cartItems as $item) {
                    $itemStmt->bind_param('iisdi', $order_id, $item['product_id'], $item['name'], $item['final_price'], $item['quantity']);
                    $itemStmt->execute();

                    $stockStmt->bind_param('iii', $item['quantity'], $item['quantity'], $item['product_id']);
                    $stockStmt->execute();
                }

                // 장바구니 비우기
                $clearStmt = $conn->prepare("DELETE FROM shop_cart WHERE user_id = ?");
                $clearStmt->bind_param('i', $user_id);
                $clearStmt->execute();

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => '주문이 완료되었습니다.',
                    'order_id' => $order_id,
                    'order_no' => $order_no
                ], JSON_UNESCAPED_UNICODE);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
