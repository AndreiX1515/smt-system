<?php
// 쇼핑몰 관리자 주문 API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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
            $status = isset($_GET['status']) ? $_GET['status'] : '';

            // 통계
            $stats = [];
            $statQuery = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
                SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered
                FROM shop_orders";
            $statResult = $conn->query($statQuery);
            if ($row = $statResult->fetch_assoc()) {
                $stats = $row;
            }

            if ($id) {
                // 주문 상세
                $stmt = $conn->prepare("SELECT * FROM shop_orders WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();

                if (!$order) {
                    http_response_code(404);
                    echo json_encode(['error' => '주문을 찾을 수 없습니다.']);
                    exit();
                }

                // 주문 상품
                $itemStmt = $conn->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
                $itemStmt->bind_param('i', $id);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();

                $items = [];
                while ($item = $itemResult->fetch_assoc()) {
                    $items[] = $item;
                }
                $order['items'] = $items;

                echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
            } else {
                // 주문 목록
                $where = "1=1";
                if ($status) {
                    $where .= " AND status = '" . $conn->real_escape_string($status) . "'";
                }

                $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
                if ($keyword) {
                    $kw = $conn->real_escape_string($keyword);
                    $where .= " AND (order_no LIKE '%{$kw}%' OR receiver_name LIKE '%{$kw}%')";
                }

                $query = "SELECT * FROM shop_orders WHERE $where ORDER BY created_at DESC LIMIT 100";
                $result = $conn->query($query);

                $orders = [];
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'orders' => $orders,
                    'stats' => $stats
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $status = $input['status'] ?? '';

            if (!$id || !$status) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 요청입니다.']);
                exit();
            }

            $validStatuses = ['pending', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'];
            if (!in_array($status, $validStatuses)) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 상태값입니다.']);
                exit();
            }

            // 상태에 따른 시간 업데이트
            $timeField = '';
            if ($status === 'paid') $timeField = ', paid_at = NOW()';
            if ($status === 'shipped') $timeField = ', shipped_at = NOW()';
            if ($status === 'delivered') $timeField = ', delivered_at = NOW()';

            $stmt = $conn->prepare("UPDATE shop_orders SET status = ? $timeField WHERE id = ?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => '상태가 변경되었습니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => '변경할 주문이 없습니다.']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
