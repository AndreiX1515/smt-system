<?php
// 쇼핑몰 리뷰 API
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

// 세션 시작 (POST/DELETE 요청 시 로그인 필수)
require_once __DIR__ . '/../../config/session.php';

$conn = new mysqli("localhost", "root", "cloud1234", "smarttravel");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

// POST/DELETE는 로그인 필수
if ($method !== 'GET') {
    $session_user_id = $_SESSION['user_id'] ?? $_SESSION['accountId'] ?? null;
    if (!$session_user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.', 'requireLogin' => true]);
        exit();
    }
}

try {
    switch ($method) {
        case 'GET':
            $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
            $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
            $order_item_id = isset($_GET['order_item_id']) ? intval($_GET['order_item_id']) : null;

            if ($order_item_id) {
                // 특정 주문 아이템의 리뷰 확인
                $stmt = $conn->prepare("SELECT * FROM shop_reviews WHERE order_item_id = ?");
                $stmt->bind_param('i', $order_item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $review = $result->fetch_assoc();

                echo json_encode([
                    'success' => true,
                    'exists' => $review ? true : false,
                    'review' => $review
                ], JSON_UNESCAPED_UNICODE);
            } elseif ($product_id) {
                // 상품별 리뷰 목록
                $stmt = $conn->prepare("
                    SELECT r.*, oi.product_name, a.username
                    FROM shop_reviews r
                    LEFT JOIN shop_order_items oi ON r.order_item_id = oi.id
                    LEFT JOIN accounts a ON r.user_id = a.accountId
                    WHERE r.product_id = ?
                    ORDER BY r.created_at DESC
                ");
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $reviews = [];
                $totalRating = 0;
                while ($row = $result->fetch_assoc()) {
                    $reviews[] = $row;
                    $totalRating += $row['rating'];
                }

                $avgRating = count($reviews) > 0 ? round($totalRating / count($reviews), 1) : 0;

                echo json_encode([
                    'success' => true,
                    'reviews' => $reviews,
                    'count' => count($reviews),
                    'avg_rating' => $avgRating
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'product_id 또는 order_item_id가 필요합니다.']);
            }
            break;

        case 'POST':
            // FormData 또는 JSON 둘 다 지원
            if (isset($_POST['order_id'])) {
                $order_id = intval($_POST['order_id']);
                $order_item_id = intval($_POST['order_item_id'] ?? 0);
                $product_id = intval($_POST['product_id'] ?? 0);
                $rating = intval($_POST['rating'] ?? 5);
                $content = trim($_POST['content'] ?? '');
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                $order_id = intval($input['order_id'] ?? 0);
                $order_item_id = intval($input['order_item_id'] ?? 0);
                $product_id = intval($input['product_id'] ?? 0);
                $rating = intval($input['rating'] ?? 5);
                $content = trim($input['content'] ?? '');
            }

            $user_id = $session_user_id;

            if (!$order_id || !$order_item_id || !$product_id) {
                http_response_code(400);
                echo json_encode(['error' => '필수 항목이 누락되었습니다.']);
                exit();
            }

            if ($rating < 1 || $rating > 5) {
                $rating = 5;
            }

            // 이미 리뷰가 있는지 확인
            $checkStmt = $conn->prepare("SELECT id FROM shop_reviews WHERE order_item_id = ?");
            $checkStmt->bind_param('i', $order_item_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                http_response_code(400);
                echo json_encode(['error' => '이미 리뷰를 작성했습니다.']);
                exit();
            }

            // 주문 상태 확인 (배송완료인지)
            $orderStmt = $conn->prepare("SELECT status FROM shop_orders WHERE id = ?");
            $orderStmt->bind_param('i', $order_id);
            $orderStmt->execute();
            $order = $orderStmt->get_result()->fetch_assoc();

            if (!$order || $order['status'] !== 'delivered') {
                http_response_code(400);
                echo json_encode(['error' => '배송 완료된 주문만 리뷰를 작성할 수 있습니다.']);
                exit();
            }

            // 사진 업로드 처리 (최대 3장)
            $photoPaths = [];
            if (!empty($_FILES['photos'])) {
                $uploadDir = __DIR__ . '/../../../uploads/reviews/';
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                $files = $_FILES['photos'];
                $fileCount = is_array($files['name']) ? count($files['name']) : 1;
                $fileCount = min($fileCount, 3); // 최대 3장

                for ($i = 0; $i < $fileCount; $i++) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                    $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                    $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                    if ($fileError !== UPLOAD_ERR_OK || empty($tmpName)) continue;
                    if (!in_array($fileType, $allowedTypes)) continue;
                    if ($fileSize > $maxSize) continue;

                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $newName = 'review_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                    if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                        $photoPaths[] = '/uploads/reviews/' . $newName;
                    }
                }
            }

            $photosJson = !empty($photoPaths) ? json_encode($photoPaths) : null;

            $stmt = $conn->prepare("INSERT INTO shop_reviews (order_id, order_item_id, product_id, user_id, rating, content, photos) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisiss', $order_id, $order_item_id, $product_id, $user_id, $rating, $content, $photosJson);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => '리뷰가 등록되었습니다.',
                'id' => $conn->insert_id
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

            if (!$id || !$user_id) {
                http_response_code(400);
                echo json_encode(['error' => '잘못된 요청입니다.']);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM shop_reviews WHERE id = ? AND user_id = ?");
            $stmt->bind_param('is', $id, $user_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => '삭제되었습니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '리뷰를 찾을 수 없습니다.']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
