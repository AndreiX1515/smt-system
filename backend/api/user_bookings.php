<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../conn.php';

// selectedOptions(JSON)에서 guestOptions를 표준 형태로 추출
if (!function_exists('extract_guest_options_from_selected_options')) {
    function extract_guest_options_from_selected_options($selectedOptionsRaw): array {
        $so = null;
        if (is_string($selectedOptionsRaw)) {
            $t = trim($selectedOptionsRaw);
            if ($t !== '') {
                $decoded = json_decode($t, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $so = $decoded;
            }
        } else if (is_array($selectedOptionsRaw)) {
            $so = $selectedOptionsRaw;
        }

        if (!is_array($so)) return [];

        // guestOptions가 저장되는 위치가 케이스별로 다를 수 있어(마이그레이션/구현 이력),
        // 가능한 경로를 모두 탐색한다.
        $go = $so['guestOptions'] ?? null;
        if (!is_array($go)) {
            $nested = $so['selectedOptions'] ?? null;
            if (is_array($nested) && isset($nested['guestOptions']) && is_array($nested['guestOptions'])) {
                $go = $nested['guestOptions'];
            }
        }
        if (!is_array($go)) return [];

        $out = [];
        foreach ($go as $item) {
            if (!is_array($item)) continue;
            $name = trim((string)($item['name'] ?? ($item['optionName'] ?? ($item['title'] ?? ''))));
            $qty = $item['qty'] ?? ($item['quantity'] ?? 0);
            $qty = is_numeric($qty) ? (int)$qty : 0;
            // unitPrice는 리스트 표시에 필수는 아니지만 함께 내려줌(다른 화면 재사용 대비)
            $unitPrice = $item['unitPrice'] ?? ($item['price'] ?? ($item['optionPrice'] ?? null));
            $unitPrice = is_numeric($unitPrice) ? (float)$unitPrice : null;

            if ($name !== '' && $qty > 0) {
                $out[] = [
                    'name' => $name,
                    'qty' => $qty,
                    'unitPrice' => $unitPrice
                ];
            }
        }
        return $out;
    }
}

// PHP 8+ bind_param 스프레드 참조 우회 헬퍼
if (!function_exists('mysqli_bind_params_by_ref')) {
    function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array &$params): void {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

// CLI에서 실행될 때 QUERY_STRING 파싱
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

try {
    // GET 파라미터에서 accountId 가져오기 (호환: userId 지원)
    $accountId = $_GET['accountId'] ?? ($_GET['userId'] ?? '');
    $bookingId = $_GET['bookingId'] ?? '';
    
    // accountId가 없으면 세션에서 보완 (user 페이지는 보통 세션 기반)
    if (empty($accountId)) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $accountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? '');
    }

    if (empty($accountId)) {
        send_json_response([
            'success' => false,
            'message' => '로그인이 필요합니다.'
        ], 401);
    }

    // 강제 정수 변환 (안전)
    $accountId = (int)$accountId;
    
    $status = $_GET['status'] ?? 'all'; // all, confirmed, pending, cancelled, completed

    // accountId OR customerAccountId 로 조회 (B2B 예약도 고객에게 노출)
    $whereClause = "WHERE (b.accountId = ? OR b.customerAccountId = ?) AND b.bookingStatus != 'draft'";
    $params = [$accountId, $accountId];
    $paramTypes = "ii";

    if ($status !== 'all') {
        $whereClause .= " AND b.bookingStatus = ?";
        $params[] = $status;
        $paramTypes .= "s";
    }

    if (!empty($bookingId)) {
        $whereClause .= " AND b.bookingId = ?";
        $params[] = $bookingId;
        $paramTypes .= "s";
    }
    
    // 예약 내역 조회

    $stmt = $conn->prepare("
        SELECT 
            b.bookingId,
            b.packageId,
            b.departureDate,
            b.departureTime,
            b.adults,
            b.children,
            b.infants,
            b.totalAmount,
            b.bookingStatus,
            b.paymentStatus,
            b.selectedOptions,
            b.specialRequests,
            b.createdAt,
            COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) as productName,
            COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) as productNameEn,
            COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1) as duration,
            p.product_images as thumbnail,
            COALESCE(NULLIF(p.meeting_location,''), NULLIF(p.meetingPoint,''), '') as meetingLocation,
            COALESCE(p.meeting_time, p.meetingTime) as meetingTime
        FROM bookings b
        LEFT JOIN packages p ON b.packageId = p.packageId
        $whereClause
        ORDER BY b.departureDate DESC
    ");
    
    mysqli_bind_params_by_ref($stmt, $paramTypes, $params);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // guestOptions를 API에서 보장해서 내려준다 (프론트에서 "Adult/Child/Infant" 등 구 스키마 사용 금지)
    // 1) selectedOptions(JSON)에서 우선 추출
    foreach ($bookings as $i => $b) {
        $bookings[$i]['guestOptions'] = extract_guest_options_from_selected_options($b['selectedOptions'] ?? null);
    }

    // NOTE: Backfilling/mapping from other columns is intentionally NOT done.
    // guestOptions must come from DB selectedOptions JSON exactly as saved.
    
    // 상태별 카운트 (pending 포함)
    $statusCounts = [];
    $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    
    foreach ($statuses as $statusType) {
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) as count
            FROM bookings
            WHERE (accountId = ? OR customerAccountId = ?) AND bookingStatus = ?
        ");
        $stmt2->bind_param("iis", $accountId, $accountId, $statusType);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        $statusCounts[$statusType] = (int)($row['count'] ?? 0); // 정수로 명시적 변환
        $stmt2->close();
    }
    
    error_log("User Bookings API - Status counts for accountId $accountId: " . json_encode($statusCounts, JSON_UNESCAPED_UNICODE));
    
    send_json_response([
        'success' => true,
        'data' => [
            'bookings' => $bookings,
            'statusCounts' => $statusCounts
        ]
    ]);
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '서버 오류가 발생했습니다: ' . $e->getMessage()
    ], 500);
}
?>