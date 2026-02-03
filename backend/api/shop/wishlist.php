<?php
// 쇼핑몰 찜(위시리스트) API (로그인 필수)
header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://smpoc.site';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 세션 시작
require_once __DIR__ . '/../../config/session.php';

$conn = new mysqli("localhost", "root", "cloud1234", "smarttravel");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

// 세션에서 사용자 ID 확인 (로그인 필수)
$user_id = $_SESSION['user_id'] ?? $_SESSION['accountId'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.', 'requireLogin' => true]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 찜 목록 조회
            // product_id가 있으면 해당 상품의 찜 여부만 확인
            $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

            if ($product_id) {
                // 특정 상품 찜 여부 확인
                $stmt = $conn->prepare("SELECT id FROM shop_wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param('ii', $user_id, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();

                echo json_encode([
                    'success' => true,
                    'isWished' => $result->num_rows > 0
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // 전체 찜 목록 조회
                $query = "SELECT w.id, w.product_id, w.created_at,
                            p.name, p.price, p.sale_price, p.thumbnail, p.stock, p.is_featured,
                            c.name as category_name
                    FROM shop_wishlist w
                    JOIN shop_products p ON w.product_id = p.id
                    LEFT JOIN shop_categories c ON p.category_id = c.id
                    WHERE w.user_id = ? AND p.is_active = 1
                    ORDER BY w.created_at DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $items = [];
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'items' => $items,
                    'count' => count($items)
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'POST':
            // 찜 추가/토글
            $product_id = intval($input['product_id'] ?? 0);

            if (!$product_id) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 요청입니다.']);
                exit();
            }

            // 상품 존재 확인
            $checkStmt = $conn->prepare("SELECT id FROM shop_products WHERE id = ? AND is_active = 1");
            $checkStmt->bind_param('i', $product_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => '상품을 찾을 수 없습니다.']);
                exit();
            }

            // 이미 찜한 상품인지 확인
            $existStmt = $conn->prepare("SELECT id FROM shop_wishlist WHERE user_id = ? AND product_id = ?");
            $existStmt->bind_param('ii', $user_id, $product_id);
            $existStmt->execute();
            $existResult = $existStmt->get_result();

            if ($existResult->num_rows > 0) {
                // 이미 찜한 상품 -> 찜 해제
                $deleteStmt = $conn->prepare("DELETE FROM shop_wishlist WHERE user_id = ? AND product_id = ?");
                $deleteStmt->bind_param('ii', $user_id, $product_id);
                $deleteStmt->execute();

                echo json_encode([
                    'success' => true,
                    'action' => 'removed',
                    'isWished' => false,
                    'message' => '찜 목록에서 삭제되었습니다.'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // 찜 추가
                $insertStmt = $conn->prepare("INSERT INTO shop_wishlist (user_id, product_id) VALUES (?, ?)");
                $insertStmt->bind_param('ii', $user_id, $product_id);
                $insertStmt->execute();

                echo json_encode([
                    'success' => true,
                    'action' => 'added',
                    'isWished' => true,
                    'message' => '찜 목록에 추가되었습니다.'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'DELETE':
            // 찜 삭제
            $product_id = intval($_GET['product_id'] ?? ($input['product_id'] ?? 0));

            if ($product_id) {
                $stmt = $conn->prepare("DELETE FROM shop_wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param('ii', $user_id, $product_id);
            } else {
                // 전체 삭제
                $stmt = $conn->prepare("DELETE FROM shop_wishlist WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
            }
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => '삭제되었습니다.'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
