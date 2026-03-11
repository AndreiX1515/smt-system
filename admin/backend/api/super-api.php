<?php
/**
 * Super (Admin) API
 * 모든 Super Admin 관련 API 엔드포인트를 처리합니다.
 */

// 출력 버퍼링 시작 (에러 캡처를 위해)
ob_start();

// 에러 핸들러 등록
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // 치명적 에러만 처리
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    
    // 치명적 에러인 경우
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal Error: ' . $errstr . ' in ' . basename($errfile) . ':' . $errline
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return false; // 기본 에러 핸들러로 전달
});

// Shutdown 함수 등록 (fatal error 캡처)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
        // 모든 출력 버퍼 정리
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // 헤더가 이미 전송되지 않았는지 확인
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ], JSON_UNESCAPED_UNICODE);
        flush();
        exit;
    }
});

ini_set('display_errors', 0); // 출력 버퍼로 에러 캡처
error_reporting(E_ALL);
ini_set('log_errors', 1);

$conn_file = __DIR__ . '/../../../backend/conn.php';
if (!file_exists($conn_file)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection file not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once $conn_file;
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Failed to load database connection: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Failed to load database connection: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !$conn) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection not established'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Email notification service (for rejection notifications)
$email_service_file = __DIR__ . '/../../../backend/services/email_notification_service.php';
if (file_exists($email_service_file)) {
    require_once $email_service_file;
}

// Booking payments helper library
require_once __DIR__ . '/../../../backend/lib/booking_payments.php';

if (!function_exists('send_json_response')) {
    function send_json_response($data, $status_code = 200) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        $response = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo $response;
        exit;
    }
}

// mysqli::bind_param 은 참조 전달이 필요합니다.
// PHP 8+ 에서 bind_param($types, ...$params) 형태는 "참조가 아닌 값"이 전달되어 Fatal Error 로 500이 발생할 수 있습니다.
if (!function_exists('mysqli_bind_params_by_ref')) {
    function mysqli_bind_params_by_ref($stmt, string $types, array &$params): bool {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

// 테이블 존재 여부(환경별 스키마 대응용)
if (!function_exists('__table_exists')) {
    function __table_exists(mysqli $conn, string $table): bool {
        try {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if ($table === '') return false;
            $res = $conn->query("SHOW TABLES LIKE '$table'");
            return ($res && $res->num_rows > 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}

// 운영 DB마다 스키마 편차가 있어, 필수 컬럼 유무를 안전하게 체크/보정합니다.
if (!function_exists('__table_has_column')) {
    function __table_has_column(mysqli $conn, string $table, string $column): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('__ensure_bookings_guideid_column')) {
    function __ensure_bookings_guideid_column(mysqli $conn): void {
        try {
            if (!__table_has_column($conn, 'bookings', 'guideId')) {
                $conn->query("ALTER TABLE `bookings` ADD COLUMN `guideId` INT NULL");
            }
        } catch (Throwable $e) {
            // ALTER 실패 시, 이후 UPDATE 단계에서 명확한 에러로 처리됩니다.
        }
    }
}

// 최고관리자 판정(운영 정책)
// - admin 화면에서 accountType은 'admin' 단일이므로, 최고관리자는 대표 계정(accountId=6 / admin@smarttravel.com)으로 통일
if (!function_exists('__is_super_admin')) {
    function __is_super_admin(mysqli $conn): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
        } catch (Throwable $e) { /* ignore */ }

        // admin_ph, admin_kr 모두 최고관리자로 인정
        $userType = $_SESSION['admin_userType'] ?? '';
        if (in_array($userType, ['admin_ph', 'admin_kr'], true)) {
            return true;
        }

        // fallback: 세션에 admin_accountId가 있으면 최고관리자로 인정
        $aid = (int)($_SESSION['admin_accountId'] ?? 0);
        if ($aid > 0) {
            return true;
        }

        return false;
    }
}

// 예약 이력 추가 헬퍼 함수
if (!function_exists('__addBookingHistory')) {
    function __addBookingHistory(mysqli $conn, string $bookingId, string $description, array $extra = []): void {
        try {
            // booking_history 테이블 존재 확인
            $tableCheck = $conn->query("SHOW TABLES LIKE 'booking_history'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                $conn->query("CREATE TABLE IF NOT EXISTS booking_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bookingId VARCHAR(50) NOT NULL,
                    description TEXT,
                    changeType VARCHAR(50) NULL,
                    changedBy VARCHAR(100) NULL,
                    changedByType VARCHAR(20) NULL,
                    previousData LONGTEXT NULL,
                    newData LONGTEXT NULL,
                    reason TEXT NULL,
                    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_bookingId (bookingId)
                )");
            }

            $changeType = $extra['changeType'] ?? null;
            $changedBy = $extra['changedBy'] ?? null;
            $changedByType = $extra['changedByType'] ?? null;
            $previousData = $extra['previousData'] ?? null;
            $newData = $extra['newData'] ?? null;
            $reason = $extra['reason'] ?? null;

            $sql = "INSERT INTO booking_history (bookingId, description, changeType, changedBy, changedByType, previousData, newData, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssss", $bookingId, $description, $changeType, $changedBy, $changedByType, $previousData, $newData, $reason);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('Failed to add booking history: ' . $e->getMessage());
        }
    }
}

// 예약 ID 생성 헬퍼 함수
if (!function_exists('generateBookingId')) {
    function generateBookingId(mysqli $conn): string {
        $prefix = 'BK';
        $date = date('Ymd');

        // 오늘 날짜로 시작하는 예약 번호 개수 확인
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE bookingId LIKE ?";
        $likePattern = $prefix . $date . '%';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $likePattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['count'] + 1;
        $stmt->close();

        // 3자리 숫자로 포맷
        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);

        return $prefix . $date . $sequence;
    }
}

if (!function_exists('__ensure_booking_travelers_status_columns')) {
    function __ensure_booking_travelers_status_columns(mysqli $conn): void {
        try {
            if (!__table_has_column($conn, 'booking_travelers', 'reservationStatus')) {
                $conn->query("ALTER TABLE `booking_travelers` ADD COLUMN `reservationStatus` VARCHAR(20) NULL");
            }
        } catch (Throwable $e) { /* ignore */ }
        try {
            if (!__table_has_column($conn, 'booking_travelers', 'statusSyncDisabled')) {
                $conn->query("ALTER TABLE `booking_travelers` ADD COLUMN `statusSyncDisabled` TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Throwable $e) { /* ignore */ }
        // age 컬럼(환경별) - 없으면 프론트가 birthDate 기반으로 계산/표시 가능
        try {
            if (!__table_has_column($conn, 'booking_travelers', 'age')) {
                // 생성 실패해도 무방(필수 아님)
            }
        } catch (Throwable $e) { /* ignore */ }
    }
}

// B2B(에이전트 예약)에서도 비자 신청 내역(visa_applications)이 생성되어야 리스트/상세에서 추적 가능
if (!function_exists('__visa_applications_table_exists')) {
    function __visa_applications_table_exists(mysqli $conn): bool {
        $t = $conn->query("SHOW TABLES LIKE 'visa_applications'");
        return ($t && $t->num_rows > 0);
    }
}
if (!function_exists('__visa_applications_has_column')) {
    function __visa_applications_has_column(mysqli $conn, string $col): bool {
        $c = $conn->real_escape_string($col);
        $r = $conn->query("SHOW COLUMNS FROM visa_applications LIKE '$c'");
        return ($r && $r->num_rows > 0);
    }
}
// visa_applications 최신 정렬/동기화용 updatedAt 컬럼 보장
if (!function_exists('__ensure_visa_applications_updated_at')) {
    function __ensure_visa_applications_updated_at(mysqli $conn): void {
        try {
            if (!__visa_applications_table_exists($conn)) return;
            if (__visa_applications_has_column($conn, 'updatedAt')) return;
            // 모든 UPDATE 시점에 자동 갱신되도록 ON UPDATE CURRENT_TIMESTAMP 사용
            $conn->query("ALTER TABLE visa_applications ADD COLUMN updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Throwable $e) {
            // ignore
        }
    }
}
if (!function_exists('__generate_visa_application_no')) {
    function __generate_visa_application_no(mysqli $conn): string {
        $prefix = 'VA' . date('Ymd');
        for ($i = 0; $i < 20; $i++) {
            $rand = str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $no = $prefix . $rand;
            $chk = $conn->prepare("SELECT 1 FROM visa_applications WHERE applicationNo = ? LIMIT 1");
            if (!$chk) return $no;
            $chk->bind_param('s', $no);
            $chk->execute();
            $exists = $chk->get_result()->num_rows > 0;
            $chk->close();
            if (!$exists) return $no;
        }
        return $prefix . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('__ensure_visa_application_for_booking_traveler')) {
    function __ensure_visa_application_for_booking_traveler(mysqli $conn, string $bookingId, int $bookingTravelerId, string $applicantName): void {
        try {
            if ($bookingId === '' || $bookingId === 'temp') return;
            if ($bookingTravelerId <= 0) return;
            if (!__visa_applications_table_exists($conn)) return;
            // 운영 스키마(applicationNo) 있을 때만 생성
            if (!__visa_applications_has_column($conn, 'applicationNo')) return;

            $bst = $conn->prepare("SELECT accountId, packageName, departureDate FROM bookings WHERE bookingId = ? LIMIT 1");
            if (!$bst) return;
            $bst->bind_param('s', $bookingId);
            $bst->execute();
            $b = $bst->get_result()->fetch_assoc();
            $bst->close();
            if (!$b) return;

            $accountId = (int)($b['accountId'] ?? 0);
            if ($accountId <= 0) return;

            $hasTravelerCol = __visa_applications_has_column($conn, 'bookingTravelerId');
            if ($hasTravelerCol) {
                $chk = $conn->prepare("SELECT applicationId FROM visa_applications WHERE accountId = ? AND transactNo = ? AND bookingTravelerId = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param('isi', $accountId, $bookingId, $bookingTravelerId);
                    $chk->execute();
                    $exists = $chk->get_result()->num_rows > 0;
                    $chk->close();
                    if ($exists) return;
                }
            } else {
                // 레거시: travelerId 컬럼이 없으면 booking 단위 1건만
                $chk = $conn->prepare("SELECT applicationId FROM visa_applications WHERE accountId = ? AND transactNo = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param('is', $accountId, $bookingId);
                    $chk->execute();
                    $exists = $chk->get_result()->num_rows > 0;
                    $chk->close();
                    if ($exists) return;
                }
            }

            $applicationNo = __generate_visa_application_no($conn);
            $packageName = (string)($b['packageName'] ?? '');
            $departureDate = (string)($b['departureDate'] ?? '');
            if ($departureDate === '') $departureDate = date('Y-m-d');
            $applicantName = trim($applicantName);
            if ($applicantName === '') $applicantName = 'Applicant';
            $destinationCountry = $packageName !== '' ? $packageName : 'Package';

            if ($hasTravelerCol) {
                $sql = "INSERT INTO visa_applications (
                            applicationNo, accountId, transactNo, bookingTravelerId, applicantName,
                            visaType, destinationCountry, applicationDate, departureDate,
                            status, processingFee
                        ) VALUES (?, ?, ?, ?, ?, 'tourist', ?, CURDATE(), ?, 'document_required', 0.00)";
                $ins = $conn->prepare($sql);
                if (!$ins) return;
                $ins->bind_param('sisisss', $applicationNo, $accountId, $bookingId, $bookingTravelerId, $applicantName, $destinationCountry, $departureDate);
                $ins->execute();
                $ins->close();
            } else {
                $sql = "INSERT INTO visa_applications (
                            applicationNo, accountId, transactNo, applicantName,
                            visaType, destinationCountry, applicationDate, departureDate,
                            status, processingFee
                        ) VALUES (?, ?, ?, ?, 'tourist', ?, CURDATE(), ?, 'document_required', 0.00)";
                $ins = $conn->prepare($sql);
                if (!$ins) return;
                $ins->bind_param('sissss', $applicationNo, $accountId, $bookingId, $applicantName, $destinationCountry, $departureDate);
                $ins->execute();
                $ins->close();
            }
        } catch (Throwable $e) {
            // 비자 내역 생성 실패가 예약 저장 자체를 막지 않도록
        }
    }
}

if (!function_exists('send_error_response')) {
    function send_error_response($message, $status_code = 400) {
        // 모든 출력 버퍼 정리
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // 헤더가 이미 전송되지 않았는지 확인
        if (!headers_sent()) {
            http_response_code($status_code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo $response;
        flush();
        exit;
    }
}

if (!function_exists('send_success_response')) {
    function send_success_response($data = [], $message = 'Success') {
        // 모든 출력 버퍼 정리
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // 헤더가 이미 전송되지 않았는지 확인
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo $response;
        flush();
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$input = [];

// JSON 입력 처리
$jsonInput = json_decode(file_get_contents('php://input'), true);
if ($jsonInput !== null) {
    $input = array_merge($input, $jsonInput);
}

// GET 파라미터 병합
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

// POST 데이터 병합 (FormData 포함)
if ($method === 'POST' && !empty($_POST)) {
    $input = array_merge($input, $_POST);
}

$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

// 디버깅 로깅 (운영에서는 응답(JSON)과 분리되도록 기본 OFF)
$superApiDebug = false;
try {
    $superApiDebug = (!empty($input['debug']) && (string)$input['debug'] !== '0') || (getenv('SUPER_API_DEBUG') === '1');
} catch (Throwable $e) {
    $superApiDebug = false;
}
if ($superApiDebug) {
    error_log("Super API Request - Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . $action);
    error_log("Super API Input: " . json_encode($input, JSON_UNESCAPED_UNICODE));
    error_log("Super API POST: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
    error_log("Super API GET: " . json_encode($_GET, JSON_UNESCAPED_UNICODE));
}

// action이 비어있으면 에러
if (empty($action)) {
    if ($superApiDebug) error_log("Super API: Action is empty");
    send_error_response('Action parameter is required', 400);
}

try {
    // 세션 확인
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) {
        if ($superApiDebug) error_log("Super API: Admin not logged in");
        send_error_response('Admin login required', 401);
    }
    
    if ($superApiDebug) error_log("Super API: Processing action: " . $action);
    
    switch ($action) {
        // 상품 관리
        case 'getProducts':
            getProducts($conn, $input);
            break;
        case 'getProductDetail':
            getProductDetail($conn, $input);
            break;
        case 'createProduct':
            createProduct($conn, $input);
            break;
        case 'updateProduct':
            updateProduct($conn, $input);
            break;
        case 'downloadUsageGuide':
            downloadUsageGuide($conn, $input);
            break;
        case 'deleteUsageGuide':
            deleteUsageGuide($conn, $input);
            break;
        case 'deleteProduct':
            deleteProduct($conn, $input);
            break;

        // Sight 관리
        case 'getSights':
            getSights($conn, $input);
            break;
        case 'getSightDetail':
            getSightDetail($conn, $input);
            break;
        case 'createSight':
            createSight($conn, $input);
            break;
        case 'updateSight':
            updateSight($conn, $input);
            break;
        case 'deleteSight':
            deleteSight($conn, $input);
            break;

        // Agent 관리
        case 'getAgents':
            getAgents($conn, $input);
            break;
        case 'exportAgentsCsv':
            exportAgentsCsv($conn, $input);
            break;
        case 'getAgentDetail':
            getAgentDetail($conn, $input);
            break;
        case 'getAgentActivityLogs':
            getAgentActivityLogs($conn, $input);
            break;
        case 'createAgent':
            if ($superApiDebug) error_log("Super API: Calling createAgent function");
            createAgent($conn, $input);
            if ($superApiDebug) error_log("Super API: createAgent function returned");
            break;
        case 'updateAgent':
            updateAgent($conn, $input);
            break;
            
        // Guide 관리
        case 'getGuides':
            getGuides($conn, $input);
            break;
        case 'exportGuidesCsv':
            exportGuidesCsv($conn, $input);
            break;
        case 'getGuideDetail':
            getGuideDetail($conn, $input);
            break;
        case 'deleteGuideProfileImage':
            deleteGuideProfileImage($conn, $input);
            break;
        case 'getGuideAssignments':
            getGuideAssignments($conn, $input);
            break;
        case 'getGuideActivityLogs':
            getGuideActivityLogs($conn, $input);
            break;
        case 'createGuide':
            createGuide($conn, $input);
            break;
        case 'updateGuide':
            updateGuide($conn, $input);
            break;
            
        // 예약 관리 (B2B/B2C)
        case 'getB2BBookings':
            getB2BBookings($conn, $input);
            break;
        case 'exportB2BBookingsCsv':
            exportB2BBookingsCsv($conn, $input);
            break;
        case 'getB2CBookings':
            getB2CBookings($conn, $input);
            break;
        case 'exportB2CBookingsCsv':
            exportB2CBookingsCsv($conn, $input);
            break;
        case 'getBookingDetail':
            getBookingDetail($conn, $input);
            break;
        case 'getBookingsByDateAndPackage':
            getBookingsByDateAndPackage($conn, $input);
            break;
        case 'getContractGuides':
            getContractGuides($conn, $input);
            break;
        case 'updateB2CBooking':
            updateB2CBooking($conn, $input);
            break;
        case 'downloadTravelerPassport':
            downloadTravelerPassport($conn, $input);
            break;
        case 'uploadTravelerPassportImage':
            uploadTravelerPassportImage($conn, $input);
            break;
        case 'deleteTravelerPassportImage':
            deleteTravelerPassportImage($conn, $input);
            break;
        case 'uploadTravelerVisaDocument':
            uploadTravelerVisaDocument($conn, $input);
            break;
        case 'getB2BBookingDetail':
            getB2BBookingDetail($conn, $input);
            break;
        case 'updateB2BBooking':
            updateB2BBooking($conn, $input);
            break;
        case 'updateB2BBookingTravelersAndRooms':
            updateB2BBookingTravelersAndRooms($conn, $input);
            break;
        // SMT 수정 시작 - 선금 증빙 파일 업로드
        case 'uploadDepositProof':
            uploadDepositProof($conn, $input);
            break;
        // SMT 수정 완료
        // SMT 수정 시작 - 결제 확정/거절
        case 'confirmPayment':
            confirmPayment($conn, $input);
            break;
        case 'rejectPayment':
            rejectPayment($conn, $input);
            break;
        // SMT 수정 완료
        case 'cancelB2BBooking':
            cancelB2BBooking($conn, $input);
            break;
        case 'approveB2BBooking':
            approveB2BBooking($conn, $input);
            break;
        case 'rejectB2BBooking':
            rejectB2BBooking($conn, $input);
            break;
        case 'acknowledgeRejection':
            acknowledgeRejection($conn, $input);
            break;
        case 'setPaymentDeadline':
            setPaymentDeadline($conn, $input);
            break;
        case 'toggleEditAllowed':
            toggleEditAllowed($conn, $input);
            break;

        // 고객 관리 (B2B/B2C)
        case 'getB2BCustomers':
            getB2BCustomers($conn, $input);
            break;
        case 'getB2CCustomers':
            getB2CCustomers($conn, $input);
            break;
        case 'getB2BCustomerBookings':
            getB2BCustomerBookings($conn, $input);
            break;
        case 'getB2BCustomerInquiries':
            getB2BCustomerInquiries($conn, $input);
            break;
        case 'getCustomerActivityTimeline':
            getCustomerActivityTimeline($conn, $input);
            break;
        case 'exportB2BCustomersCsv':
            exportB2BCustomersCsv($conn, $input);
            break;
        case 'exportB2CCustomersCsv':
            exportB2CCustomersCsv($conn, $input);
            break;
        case 'getCustomerDetail':
            getCustomerDetail($conn, $input);
            break;
        case 'updateB2BCustomer':
            updateB2BCustomer($conn, $input);
            break;
        case 'updateB2CCustomer':
            updateB2CCustomer($conn, $input);
            break;
        case 'resetCustomerPassword':
            resetCustomerPassword($conn, $input);
            break;
        case 'resetAccountPassword':
            resetAccountPassword($conn, $input);
            break;
        case 'deleteCustomerPassportImage':
            deleteCustomerPassportImage($conn, $input);
            break;
        case 'getB2CCustomerBookings':
            getB2CCustomerBookings($conn, $input);
            break;
        case 'getB2CCustomerInquiries':
            getB2CCustomerInquiries($conn, $input);
            break;
            
        // 문의 관리
        case 'getUserInquiries':
            getUserInquiries($conn, $input);
            break;
        case 'getUserInquiryDetail':
            getUserInquiryDetail($conn, $input);
            break;
        case 'downloadInquiryAttachment':
            downloadInquiryAttachment($conn);
            break;
        case 'sendInquiryAnswer':
            sendInquiryAnswer($conn, $input);
            break;
        case 'updateInquiryStatus':
            updateInquiryStatus($conn, $input);
            break;
        case 'downloadUserInquiries':
            downloadUserInquiries($conn, $input);
            break;
        case 'downloadAgentInquiries':
            downloadAgentInquiries($conn, $input);
            break;

        // ========== 메시지 관리 ==========
        case 'getMessageThreads':
            getMessageThreads($conn, $input);
            break;
        case 'getMessageThreadDetail':
            getMessageThreadDetail($conn, $input);
            break;
        case 'createMessageThread':
            createMessageThread($conn, $input);
            break;
        case 'getMessageThreadByBookingId':
            getMessageThreadByBookingId($conn, $input);
            break;
        case 'sendMessage':
            handleSendMessage($conn, $input);
            break;
        case 'updateMessageThreadStatus':
            updateMessageThreadStatus($conn, $input);
            break;
        case 'getAgentListForMessage':
            getAgentListForMessage($conn, $input);
            break;
        case 'getMessageUnreadCount':
            getMessageUnreadCount($conn, $input);
            break;

        // 회원 관리
        case 'getMembers':
            getMembers($conn, $input);
            break;
        case 'exportMembersCsv':
            exportMembersCsv($conn, $input);
            break;
            
        // 공지사항 관리
        case 'getNotices':
            getNotices($conn, $input);
            break;
        case 'getNoticeDetail':
            getNoticeDetail($conn, $input);
            break;
        case 'createNotice':
            createNotice($conn, $input);
            break;
        case 'updateNotice':
            updateNotice($conn, $input);
            break;
        case 'deleteNotice':
            deleteNotice($conn, $input);
            break;
            
        // 매출 통계
        case 'getSalesByDate':
            getSalesByDate($conn, $input);
            break;
        case 'getSalesByProduct':
            getSalesByProduct($conn, $input);
            break;
        case 'getProductSalesOverview':
            getProductSalesOverview($conn, $input);
            break;
        case 'downloadSalesByDate':
            downloadSalesByDate($conn, $input);
            break;
        case 'downloadSalesByProduct':
            downloadSalesByProduct($conn, $input);
            break;
        case 'getSalesDashboard':
            getSalesDashboard($conn, $input);
            break;
        case 'downloadSalesDashboard':
            downloadSalesDashboard($conn, $input);
            break;

        // 비자 신청 관리
        case 'getVisaApplications':
            getVisaApplications($conn, $input);
            break;
        case 'getVisaApplicationsGrouped':
            getVisaApplicationsGrouped($conn, $input);
            break;
        case 'getVisaApplicationDetail':
            getVisaApplicationDetail($conn, $input);
            break;
        case 'updateVisaStatus':
            updateVisaStatus($conn, $input);
            break;
        case 'updateVisaFile':
            updateVisaFile($conn, $input);
            break;
        case 'deleteVisaFile':
            deleteVisaFile($conn, $input);
            break;
        case 'deleteVisaDocument':
            deleteVisaDocument($conn, $input);
            break;
        case 'downloadVisaApplications':
            downloadVisaApplications($conn, $input);
            break;
            
        // 팝업 관리
        case 'getPopups':
            getPopups($conn, $input);
            break;
        case 'getPopupDetail':
            getPopupDetail($conn, $input);
            break;
        case 'createPopup':
            createPopup($conn, $input);
            break;
        case 'updatePopup':
            updatePopup($conn, $input);
            break;
        case 'deletePopup':
            deletePopup($conn, $input);
            break;
        // 배너 관리
        case 'getBanners':
            getBanners($conn, $input);
            break;
        case 'updateBanner':
            updateBanner($conn, $input);
            break;
        // 파트너 관리
        case 'getPartners':
            getPartners($conn, $input);
            break;
        case 'updatePartner':
            updatePartner($conn, $input);
            break;
        case 'deletePartner':
            deletePartner($conn, $input);
            break;
        // 이용약관 관리
        case 'getTerms':
            getTerms($conn, $input);
            break;
        case 'updateTerms':
            updateTerms($conn, $input);
            break;
        // 회사 정보 관리
        case 'getCompanyInfo':
            getCompanyInfo($conn, $input);
            break;
        case 'updateCompanyInfo':
            updateCompanyInfo($conn, $input);
            break;
            
        // 템플릿 관리
        case 'getTemplates':
            getTemplates($conn, $input);
            break;
        case 'createTemplate':
            createTemplate($conn, $input);
            break;
        case 'getTemplateDetail':
            getTemplateDetail($conn, $input);
            break;
        case 'updateTemplate':
            updateTemplate($conn, $input);
            break;
        case 'deleteTemplate':
            deleteTemplate($conn, $input);
            break;
            
        // 카테고리 관리
        case 'getCategories':
            getCategories($conn, $input);
            break;
        case 'createCategory':
            createCategory($conn, $input);
            break;
        case 'createSubCategory':
            createSubCategory($conn, $input);
            break;
        case 'updateCategory':
            updateCategory($conn, $input);
            break;
        case 'deleteCategory':
            deleteCategory($conn, $input);
            break;
        case 'reorderSubCategories':
            reorderSubCategories($conn, $input);
            break;
        case 'reorderMainCategories':
            reorderMainCategories($conn, $input);
            break;
        case 'reorderOptionCategories':
            reorderOptionCategories($conn, $input);
            break;
        case 'reorderAirlineOptions':
            reorderAirlineOptions($conn, $input);
            break;

        // 에이전트 문의 관리
        case 'getAgentInquiries':
            getAgentInquiries($conn, $input);
            break;

        // 재고 관리
        case 'getInventoryCalendar':
            getInventoryCalendar($conn, $input);
            break;
        case 'updateInventory':
            updateInventory($conn, $input);
            break;
        case 'bulkUpdateInventory':
            bulkUpdateInventory($conn, $input);
            break;

        // Product Announcements
        case 'getProductAnnouncements':
            getProductAnnouncements($conn, $input);
            break;
        case 'getProductAnnouncementDetail':
            getProductAnnouncementDetail($conn, $input);
            break;
        case 'createProductAnnouncement':
            createProductAnnouncement($conn, $input);
            break;
        case 'updateProductAnnouncement':
            updateProductAnnouncement($conn, $input);
            break;
        case 'deleteProductAnnouncement':
            deleteProductAnnouncement($conn, $input);
            break;
        case 'publishProductAnnouncement':
            publishProductAnnouncement($conn, $input);
            break;
        case 'getAnnouncementTargetCount':
            getAnnouncementTargetCountApi($conn, $input);
            break;

        // 이메일 알림 로그
        case 'getEmailNotificationLogs':
            getEmailNotificationLogs($conn, $input);
            break;
        case 'getEmailNotificationStats':
            getEmailNotificationStats($conn);
            break;
        case 'exportEmailNotificationLogsCsv':
            exportEmailNotificationLogsCsv($conn, $input);
            break;

        // 로그인 이력 관리
        case 'getAdminLoginHistory':
            getAdminLoginHistory($conn, $input);
            break;
        case 'exportAdminLoginHistoryCsv':
            exportAdminLoginHistoryCsv($conn, $input);
            break;

        // 예약 상태 변경 히스토리
        case 'getBookingStatusHistory':
            getBookingStatusHistory($conn, $input);
            break;
        case 'exportBookingStatusHistoryCsv':
            exportBookingStatusHistoryCsv($conn, $input);
            break;

        // 옵션 관리 (대분류/중분류/소분류)
        case 'getMainOptionCategories':
            getMainOptionCategories($conn);
            break;
        case 'createMainOptionCategory':
            createMainOptionCategory($conn, $input);
            break;
        case 'updateMainOptionCategory':
            updateMainOptionCategory($conn, $input);
            break;
        case 'deleteMainOptionCategory':
            deleteMainOptionCategory($conn, $input);
            break;
        case 'copyMainOptionCategory':
            copyMainOptionCategory($conn, $input);
            break;
        case 'getAirlineList':
            getAirlineList($conn);
            break;
        case 'getAirlineOptions':
            getAirlineOptions($conn, $input);
            break;
        case 'createOptionCategory':
            createOptionCategory($conn, $input);
            break;
        case 'updateOptionCategory':
            updateOptionCategory($conn, $input);
            break;
        case 'deleteOptionCategory':
            deleteOptionCategory($conn, $input);
            break;
        case 'createAirlineOption':
            createAirlineOption($conn, $input);
            break;
        case 'updateAirlineOption':
            updateAirlineOption($conn, $input);
            break;
        case 'deleteAirlineOption':
            deleteAirlineOption($conn, $input);
            break;

        // 세일 관리
        case 'getSales':
            getSales($conn, $input);
            break;
        case 'getSaleDetail':
            getSaleDetail($conn, $input);
            break;
        case 'createSale':
            createSale($conn, $input);
            break;
        case 'updateSale':
            updateSale($conn, $input);
            break;
        case 'deleteSale':
            deleteSale($conn, $input);
            break;
        case 'toggleSaleActive':
            toggleSaleActive($conn, $input);
            break;
        case 'getPackagesForSale':
            getPackagesForSale($conn, $input);
            break;

        case 'getMonthlyInvoiceData':
            getMonthlyInvoiceData($conn, $input);
            break;

        // ========== Extra Options 관련 ==========
        case 'getExtraOptionsListAll':
            getExtraOptionsListAll($conn, $input);
            break;

        case 'getExtraOptionsDetail':
            getExtraOptionsDetailSuper($conn, $input);
            break;

        case 'saveExtraOptions':
            saveExtraOptionsSuper($conn, $input);
            break;

        case 'uploadOptionPaymentProof':
            uploadOptionPaymentProofSuper($conn, $input);
            break;

        case 'deleteOptionPaymentProof':
            deleteOptionPaymentProofSuper($conn, $input);
            break;

        case 'confirmOptionPayment':
            confirmOptionPayment($conn, $input);
            break;

        case 'rejectOptionPayment':
            rejectOptionPayment($conn, $input);
            break;

        // ========== Visa 관련 (Booking Detail) ==========
        case 'getVisaApplicationsByBookingId':
            getVisaApplicationsByBookingIdSuper($conn, $input);
            break;

        // ========== Rooming List 관련 ==========
        case 'getRoomingDates':
            getRoomingDates($conn, $input);
            break;
        case 'getRoomingList':
            getRoomingList($conn, $input);
            break;
        case 'saveRoomingAssignments':
            saveRoomingAssignments($conn, $input);
            break;
        case 'getRoomingAssignments':
            getRoomingAssignmentsSuper($conn, $input);
            break;
        case 'saveRoomingAssignmentsByBooking':
            saveRoomingAssignmentsByBookingSuper($conn, $input);
            break;

        default:
            send_error_response('Invalid action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log("Super API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    // 출력 버퍼 정리 후 에러 응답
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    send_error_response('An error occurred: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Super API fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    // 출력 버퍼 정리 후 에러 응답
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    send_error_response('A fatal error occurred: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log("Super API throwable error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    // 출력 버퍼 정리 후 에러 응답
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    send_error_response('An unexpected error occurred: ' . $e->getMessage(), 500);
}

// ========== 상품 관리 함수들 ==========

// ========== 카테고리 관리(대/중분류) ==========

function ensureCategoriesTables($conn) {
    // main categories
    $sql1 = "CREATE TABLE IF NOT EXISTS product_main_categories (
        mainCategoryId INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(100) NULL,
        name VARCHAR(100) NOT NULL,
        sortOrder INT NOT NULL DEFAULT 0,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql1)) {
        throw new Exception('Failed to create product_main_categories table: ' . $conn->error);
    }
    // Ensure code column exists (for older installs)
    try {
        $col = $conn->query("SHOW COLUMNS FROM product_main_categories LIKE 'code'");
        if (!$col || $col->num_rows === 0) {
            $conn->query("ALTER TABLE product_main_categories ADD COLUMN code VARCHAR(100) NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }

    // UNIQUE INDEX: uq_main_name (name) - only add if missing (MYSQLI_REPORT_STRICT 환경에서 중복 추가 시 예외 방지)
    $idx1 = null;
    try {
        $idx1 = $conn->query("SHOW INDEX FROM product_main_categories WHERE Key_name = 'uq_main_name'");
        if ($idx1 && $idx1->num_rows === 0) {
            $conn->query("ALTER TABLE product_main_categories ADD UNIQUE KEY uq_main_name (name)");
        }
    } catch (Throwable $e) {
        // ignore if already exists or cannot be added
    } finally {
        if ($idx1 instanceof mysqli_result) $idx1->free();
    }

    // UNIQUE INDEX: uq_main_code (code)
    $idx1c = null;
    try {
        $idx1c = $conn->query("SHOW INDEX FROM product_main_categories WHERE Key_name = 'uq_main_code'");
        if ($idx1c && $idx1c->num_rows === 0) {
            $conn->query("ALTER TABLE product_main_categories ADD UNIQUE KEY uq_main_code (code)");
        }
    } catch (Throwable $e) {
        // ignore
    } finally {
        if ($idx1c instanceof mysqli_result) $idx1c->free();
    }

    // sub categories
    $sql2 = "CREATE TABLE IF NOT EXISTS product_sub_categories (
        subCategoryId INT AUTO_INCREMENT PRIMARY KEY,
        mainCategoryId INT NOT NULL,
        code VARCHAR(100) NULL,
        name VARCHAR(100) NOT NULL,
        sortOrder INT NOT NULL DEFAULT 0,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_sub_main FOREIGN KEY (mainCategoryId) REFERENCES product_main_categories(mainCategoryId)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql2)) {
        throw new Exception('Failed to create product_sub_categories table: ' . $conn->error);
    }
    // Ensure code column exists (for older installs)
    try {
        $col2 = $conn->query("SHOW COLUMNS FROM product_sub_categories LIKE 'code'");
        if (!$col2 || $col2->num_rows === 0) {
            $conn->query("ALTER TABLE product_sub_categories ADD COLUMN code VARCHAR(100) NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }
    // UNIQUE INDEX: uq_sub_name (mainCategoryId, name)
    $idx2 = null;
    try {
        $idx2 = $conn->query("SHOW INDEX FROM product_sub_categories WHERE Key_name = 'uq_sub_name'");
        if ($idx2 && $idx2->num_rows === 0) {
            $conn->query("ALTER TABLE product_sub_categories ADD UNIQUE KEY uq_sub_name (mainCategoryId, name)");
        }
    } catch (Throwable $e) {
        // ignore
    } finally {
        if ($idx2 instanceof mysqli_result) $idx2->free();
    }

    // UNIQUE INDEX: uq_sub_code (mainCategoryId, code)
    $idx2c = null;
    try {
        $idx2c = $conn->query("SHOW INDEX FROM product_sub_categories WHERE Key_name = 'uq_sub_code'");
        if ($idx2c && $idx2c->num_rows === 0) {
            $conn->query("ALTER TABLE product_sub_categories ADD UNIQUE KEY uq_sub_code (mainCategoryId, code)");
        }
    } catch (Throwable $e) {
        // ignore
    } finally {
        if ($idx2c instanceof mysqli_result) $idx2c->free();
    }
}

function seedDefaultCategoriesIfEmpty($conn) {
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM product_main_categories");
    if (!$countRes) return;
    $cnt = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
    if ($cnt > 0) return;

    // 기본값(현재 퍼블에 있던 값 기반)
    $defaults = [
        ['code' => 'season', 'name' => '계절별', 'subs' => [
            ['code' => 'spring', 'name' => '봄'],
            ['code' => 'summer', 'name' => '여름'],
            ['code' => 'autumn', 'name' => '가을'],
            ['code' => 'winter', 'name' => '겨울'],
        ]],
        ['code' => 'region', 'name' => '지역별', 'subs' => [
            ['code' => 'seoul', 'name' => '서울'],
            ['code' => 'gangwon', 'name' => '강원'],
            ['code' => 'incheon', 'name' => '인천'],
            ['code' => 'gyeonggi', 'name' => '경기'],
        ]],
        ['code' => 'theme', 'name' => '테마별', 'subs' => [
            ['code' => 'family', 'name' => '가족'],
            ['code' => 'friend', 'name' => '친구'],
            ['code' => 'pet', 'name' => '반려동물'],
            ['code' => 'solo', 'name' => '혼자'],
        ]],
        ['code' => 'private', 'name' => '프라이빗', 'subs' => [
            ['code' => 'family', 'name' => '가족'],
        ]]
    ];

    $mainOrder = 1;
    foreach ($defaults as $def) {
        $mainName = $def['name'];
        $mainCode = $def['code'] ?? null;
        $stmt = $conn->prepare("INSERT INTO product_main_categories (code, name, sortOrder) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $mainCode, $mainName, $mainOrder);
        $stmt->execute();
        $mainId = $stmt->insert_id;
        $stmt->close();

        $subOrder = 1;
        foreach ($def['subs'] as $subDef) {
            $subName = is_array($subDef) ? ($subDef['name'] ?? '') : (string)$subDef;
            $subCode = is_array($subDef) ? ($subDef['code'] ?? null) : null;
            $stmt2 = $conn->prepare("INSERT INTO product_sub_categories (mainCategoryId, code, name, sortOrder) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param('issi', $mainId, $subCode, $subName, $subOrder);
            $stmt2->execute();
            $stmt2->close();
            $subOrder++;
        }
        $mainOrder++;
    }
}

function backfillCategoryCodes($conn) {
    // 메인 코드 백필(기존 seeded 데이터가 code=NULL인 경우)
    $mainMap = [
        '계절별' => 'season',
        '지역별' => 'region',
        '테마별' => 'theme',
        '프라이빗' => 'private',
    ];
    foreach ($mainMap as $name => $code) {
        $stmt = $conn->prepare("UPDATE product_main_categories SET code = ? WHERE (code IS NULL OR code = '') AND name = ?");
        $stmt->bind_param('ss', $code, $name);
        $stmt->execute();
        $stmt->close();
    }

    // 서브 코드 백필: main.code + sub.name 기준
    $subMaps = [
        'season' => ['봄' => 'spring', '여름' => 'summer', '가을' => 'autumn', '겨울' => 'winter'],
        'region' => ['서울' => 'seoul', '강원' => 'gangwon', '인천' => 'incheon', '경기' => 'gyeonggi'],
        'theme' => ['가족' => 'family', '친구' => 'friend', '반려동물' => 'pet', '혼자' => 'solo'],
        'private' => ['가족' => 'family'],
    ];
    foreach ($subMaps as $mainCode => $map) {
        foreach ($map as $name => $code) {
            $sql = "UPDATE product_sub_categories s
                    JOIN product_main_categories m ON m.mainCategoryId = s.mainCategoryId
                    SET s.code = ?
                    WHERE (s.code IS NULL OR s.code = '')
                      AND m.code = ?
                      AND s.name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $code, $mainCode, $name);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 여전히 code가 비어있는 항목은 slugify로 채움
    $res = $conn->query("SELECT mainCategoryId, name FROM product_main_categories WHERE code IS NULL OR code = ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['mainCategoryId'];
            $name = (string)$row['name'];
            $code = strtolower(trim($name));
            $code = preg_replace('/\s+/', '_', $code);
            $code = preg_replace('/[^a-z0-9_]/', '', $code);
            if ($code === '') $code = 'cat_' . $id;
            $stmt = $conn->prepare("UPDATE product_main_categories SET code = ? WHERE mainCategoryId = ?");
            $stmt->bind_param('si', $code, $id);
            $stmt->execute();
            $stmt->close();
        }
        $res->free();
    }

    $res2 = $conn->query("SELECT s.subCategoryId, s.name, m.code AS mainCode
                          FROM product_sub_categories s
                          JOIN product_main_categories m ON m.mainCategoryId = s.mainCategoryId
                          WHERE s.code IS NULL OR s.code = ''");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $id = (int)$row['subCategoryId'];
            $name = (string)$row['name'];
            $code = strtolower(trim($name));
            $code = preg_replace('/\s+/', '_', $code);
            $code = preg_replace('/[^a-z0-9_]/', '', $code);
            if ($code === '') $code = 'sub_' . $id;
            $stmt = $conn->prepare("UPDATE product_sub_categories SET code = ? WHERE subCategoryId = ?");
            $stmt->bind_param('si', $code, $id);
            $stmt->execute();
            $stmt->close();
        }
        $res2->free();
    }
}

function normalize_category_name($name) {
    $name = trim((string)$name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

function category_exists_main($conn, $name, $excludeId = null) {
    $name = normalize_category_name($name);
    if ($excludeId !== null) {
        $sql = "SELECT mainCategoryId FROM product_main_categories WHERE LOWER(name) = LOWER(?) AND mainCategoryId <> ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $name, $excludeId);
    } else {
        $sql = "SELECT mainCategoryId FROM product_main_categories WHERE LOWER(name) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $name);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['mainCategoryId'] : null;
}

function category_exists_sub($conn, $mainCategoryId, $name, $excludeId = null) {
    $name = normalize_category_name($name);
    $mainCategoryId = (int)$mainCategoryId;
    if ($excludeId !== null) {
        $sql = "SELECT subCategoryId FROM product_sub_categories WHERE mainCategoryId = ? AND LOWER(name) = LOWER(?) AND subCategoryId <> ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isi', $mainCategoryId, $name, $excludeId);
    } else {
        $sql = "SELECT subCategoryId FROM product_sub_categories WHERE mainCategoryId = ? AND LOWER(name) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $mainCategoryId, $name);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['subCategoryId'] : null;
}

function getCategories($conn, $input) {
    try {
        ensureCategoriesTables($conn);
        seedDefaultCategoriesIfEmpty($conn);
        backfillCategoryCodes($conn);

        $mainSql = "SELECT mainCategoryId, code, name, sortOrder FROM product_main_categories ORDER BY sortOrder ASC, mainCategoryId ASC";
        $mainRes = $conn->query($mainSql);
        $mains = [];
        while ($row = $mainRes->fetch_assoc()) {
            $row['mainCategoryId'] = (int)$row['mainCategoryId'];
            $row['sortOrder'] = (int)$row['sortOrder'];
            $row['code'] = $row['code'] ?? null;
            $row['subCategories'] = [];
            $mains[$row['mainCategoryId']] = $row;
        }

        $subSql = "SELECT subCategoryId, mainCategoryId, code, name, sortOrder
                   FROM product_sub_categories
                   ORDER BY mainCategoryId ASC, sortOrder ASC, subCategoryId ASC";
        $subRes = $conn->query($subSql);
        while ($row = $subRes->fetch_assoc()) {
            $mid = (int)$row['mainCategoryId'];
            if (!isset($mains[$mid])) continue;
            $mains[$mid]['subCategories'][] = [
                'subCategoryId' => (int)$row['subCategoryId'],
                'mainCategoryId' => $mid,
                'code' => $row['code'] ?? null,
                'name' => $row['name'],
                'sortOrder' => (int)$row['sortOrder']
            ];
        }

        send_success_response(['categories' => array_values($mains)]);
    } catch (Exception $e) {
        send_error_response('Failed to get categories: ' . $e->getMessage());
    }
}

function createCategory($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $mainName = normalize_category_name($input['mainCategoryName'] ?? $input['mainName'] ?? '');
        $subName = normalize_category_name($input['subCategoryName'] ?? $input['subName'] ?? '');
        if ($mainName === '' || $subName === '') {
            send_error_response('Main category name and subcategory name are required', 400);
        }

        if (category_exists_main($conn, $mainName) !== null) {
            send_error_response('A category name with the same name already exists.', 409);
        }

        // code 생성: 영문/숫자/언더스코어 중심, 한글 등으로 slug가 비면 cat_<timestamp>
        $mkCode = function($s, $prefix) {
            $s = strtolower(trim((string)$s));
            $s = preg_replace('/\s+/', '_', $s);
            $s = preg_replace('/[^a-z0-9_]/', '', $s);
            if ($s === '') $s = $prefix . '_' . time();
            return $s;
        };
        $mainCode = $mkCode($mainName, 'cat');
        // code 중복이면 suffix
        $suffix = 1;
        while (true) {
            $chk = $conn->prepare("SELECT mainCategoryId FROM product_main_categories WHERE code = ? LIMIT 1");
            $chk->bind_param('s', $mainCode);
            $chk->execute();
            $res = $chk->get_result();
            $exists = $res->fetch_assoc();
            $chk->close();
            if (!$exists) break;
            $suffix++;
            $mainCode = $mainCode . '_' . $suffix;
        }

        $orderRes = $conn->query("SELECT COALESCE(MAX(sortOrder), 0) AS mx FROM product_main_categories");
        $nextOrder = (int)($orderRes->fetch_assoc()['mx'] ?? 0) + 1;

        $stmt = $conn->prepare("INSERT INTO product_main_categories (code, name, sortOrder) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $mainCode, $mainName, $nextOrder);
        $stmt->execute();
        $mainId = (int)$stmt->insert_id;
        $stmt->close();

        $subCode = $mkCode($subName, 'sub');
        $stmt2 = $conn->prepare("INSERT INTO product_sub_categories (mainCategoryId, code, name, sortOrder) VALUES (?, ?, ?, 1)");
        $stmt2->bind_param('iss', $mainId, $subCode, $subName);
        $stmt2->execute();
        $stmt2->close();

        send_success_response(['mainCategoryId' => $mainId], 'Category created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create category: ' . $e->getMessage());
    }
}

function createSubCategory($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $mainCategoryId = (int)($input['mainCategoryId'] ?? 0);
        $subName = normalize_category_name($input['subCategoryName'] ?? $input['subName'] ?? '');
        if ($mainCategoryId <= 0 || $subName === '') {
            send_error_response('mainCategoryId and subCategoryName are required', 400);
        }

        if (category_exists_sub($conn, $mainCategoryId, $subName) !== null) {
            send_error_response('A category name with the same name already exists.', 409);
        }

        $subCode = strtolower(trim((string)$subName));
        $subCode = preg_replace('/\s+/', '_', $subCode);
        $subCode = preg_replace('/[^a-z0-9_]/', '', $subCode);
        if ($subCode === '') $subCode = 'sub_' . time();

        $stmt = $conn->prepare("SELECT COALESCE(MAX(sortOrder), 0) AS mx FROM product_sub_categories WHERE mainCategoryId = ?");
        $stmt->bind_param('i', $mainCategoryId);
        $stmt->execute();
        $res = $stmt->get_result();
        $nextOrder = (int)($res->fetch_assoc()['mx'] ?? 0) + 1;
        $stmt->close();

        $stmt2 = $conn->prepare("INSERT INTO product_sub_categories (mainCategoryId, code, name, sortOrder) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param('issi', $mainCategoryId, $subCode, $subName, $nextOrder);
        $stmt2->execute();
        $subId = (int)$stmt2->insert_id;
        $stmt2->close();

        send_success_response(['subCategoryId' => $subId], 'Subcategory created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create subcategory: ' . $e->getMessage());
    }
}

function updateCategory($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $level = (string)($input['level'] ?? $input['type'] ?? '');
        $name = normalize_category_name($input['name'] ?? '');

        if ($level === 'main') {
            $mainCategoryId = (int)($input['mainCategoryId'] ?? 0);
            if ($mainCategoryId <= 0 || $name === '') {
                send_error_response('mainCategoryId and name are required', 400);
            }
            if (category_exists_main($conn, $name, $mainCategoryId) !== null) {
                send_error_response('A category name with the same name already exists.', 409);
            }
            $stmt = $conn->prepare("UPDATE product_main_categories SET name = ? WHERE mainCategoryId = ?");
            $stmt->bind_param('si', $name, $mainCategoryId);
            $stmt->execute();
            $stmt->close();
            send_success_response([], 'Category updated successfully');
            return;
        }

        if ($level === 'sub') {
            $subCategoryId = (int)($input['subCategoryId'] ?? 0);
            $mainCategoryId = (int)($input['mainCategoryId'] ?? 0);
            if ($subCategoryId <= 0 || $mainCategoryId <= 0 || $name === '') {
                send_error_response('subCategoryId, mainCategoryId and name are required', 400);
            }
            if (category_exists_sub($conn, $mainCategoryId, $name, $subCategoryId) !== null) {
                send_error_response('A category name with the same name already exists.', 409);
            }
            $stmt = $conn->prepare("UPDATE product_sub_categories SET name = ? WHERE subCategoryId = ? AND mainCategoryId = ?");
            $stmt->bind_param('sii', $name, $subCategoryId, $mainCategoryId);
            $stmt->execute();
            $stmt->close();
            send_success_response([], 'Subcategory updated successfully');
            return;
        }

        send_error_response('Invalid level. Use main or sub.', 400);
    } catch (Exception $e) {
        send_error_response('Failed to update category: ' . $e->getMessage());
    }
}

function deleteCategory($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $level = (string)($input['level'] ?? $input['type'] ?? '');
        if ($level === 'main') {
            $mainCategoryId = (int)($input['mainCategoryId'] ?? 0);
            if ($mainCategoryId <= 0) send_error_response('mainCategoryId is required', 400);
            $stmt = $conn->prepare("DELETE FROM product_main_categories WHERE mainCategoryId = ?");
            $stmt->bind_param('i', $mainCategoryId);
            $stmt->execute();
            $stmt->close();
            send_success_response([], 'Category deleted successfully');
            return;
        }
        if ($level === 'sub') {
            $subCategoryId = (int)($input['subCategoryId'] ?? 0);
            if ($subCategoryId <= 0) send_error_response('subCategoryId is required', 400);
            $stmt = $conn->prepare("DELETE FROM product_sub_categories WHERE subCategoryId = ?");
            $stmt->bind_param('i', $subCategoryId);
            $stmt->execute();
            $stmt->close();
            send_success_response([], 'Subcategory deleted successfully');
            return;
        }
        send_error_response('Invalid level. Use main or sub.', 400);
    } catch (Exception $e) {
        send_error_response('Failed to delete category: ' . $e->getMessage());
    }
}

function reorderSubCategories($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $mainCategoryId = (int)($input['mainCategoryId'] ?? 0);
        $order = $input['order'] ?? $input['subCategoryIds'] ?? null;
        if ($mainCategoryId <= 0 || !is_array($order)) {
            send_error_response('mainCategoryId and order(array) are required', 400);
        }

        // Validate all belong to mainCategoryId
        $ids = array_values(array_filter(array_map('intval', $order), fn($v) => $v > 0));
        if (count($ids) === 0) {
            send_error_response('order is empty', 400);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids) + 1);
        $params = array_merge([$mainCategoryId], $ids);
        $sql = "SELECT COUNT(*) AS cnt FROM product_sub_categories WHERE mainCategoryId = ? AND subCategoryId IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt !== count($ids)) {
            send_error_response('Invalid subCategoryIds for this mainCategoryId', 400);
        }

        $conn->begin_transaction();
        $stmtUp = $conn->prepare("UPDATE product_sub_categories SET sortOrder = ? WHERE subCategoryId = ? AND mainCategoryId = ?");
        $i = 1;
        foreach ($ids as $subId) {
            $stmtUp->bind_param('iii', $i, $subId, $mainCategoryId);
            $stmtUp->execute();
            $i++;
        }
        $stmtUp->close();
        $conn->commit();

        send_success_response([], 'Subcategories reordered successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reorder subcategories: ' . $e->getMessage());
    }
}

function reorderMainCategories($conn, $input) {
    try {
        ensureCategoriesTables($conn);

        $order = $input['order'] ?? $input['mainCategoryIds'] ?? null;
        if (!is_array($order)) {
            send_error_response('order(array) is required', 400);
        }

        $ids = array_values(array_filter(array_map('intval', $order), fn($v) => $v > 0));
        if (count($ids) === 0) {
            send_error_response('order is empty', 400);
        }

        // Validate all IDs exist
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT COUNT(*) AS cnt FROM product_main_categories WHERE mainCategoryId IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt !== count($ids)) {
            send_error_response('Invalid mainCategoryIds', 400);
        }

        $conn->begin_transaction();
        $stmtUp = $conn->prepare("UPDATE product_main_categories SET sortOrder = ? WHERE mainCategoryId = ?");
        $i = 1;
        foreach ($ids as $mainId) {
            $stmtUp->bind_param('ii', $i, $mainId);
            $stmtUp->execute();
            $i++;
        }
        $stmtUp->close();
        $conn->commit();

        send_success_response([], 'Main categories reordered successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reorder main categories: ' . $e->getMessage());
    }
}

function reorderOptionCategories($conn, $input) {
    try {
        $mainCategory = $input['mainCategory'] ?? '';
        $order = $input['order'] ?? null;
        if (is_string($order)) $order = json_decode($order, true);

        if (empty($mainCategory) || !is_array($order)) {
            send_error_response('mainCategory and order(array) are required', 400);
        }

        $ids = array_values(array_filter(array_map('intval', $order), fn($v) => $v > 0));
        if (count($ids) === 0) {
            send_error_response('order is empty', 400);
        }

        // Validate all belong to mainCategory
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = 's' . str_repeat('i', count($ids));
        $params = array_merge([$mainCategory], $ids);
        $sql = "SELECT COUNT(*) AS cnt FROM airline_option_categories WHERE airline_name = ? AND category_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt !== count($ids)) {
            send_error_response('Invalid category IDs for this main category', 400);
        }

        $conn->begin_transaction();
        $stmtUp = $conn->prepare("UPDATE airline_option_categories SET sort_order = ? WHERE category_id = ?");
        $i = 1;
        foreach ($ids as $catId) {
            $stmtUp->bind_param('ii', $i, $catId);
            $stmtUp->execute();
            $i++;
        }
        $stmtUp->close();
        $conn->commit();

        send_success_response([], 'Option categories reordered successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reorder option categories: ' . $e->getMessage());
    }
}

function reorderAirlineOptions($conn, $input) {
    try {
        $categoryId = (int)($input['categoryId'] ?? 0);
        $order = $input['order'] ?? null;
        if (is_string($order)) $order = json_decode($order, true);

        if ($categoryId <= 0 || !is_array($order)) {
            send_error_response('categoryId and order(array) are required', 400);
        }

        $ids = array_values(array_filter(array_map('intval', $order), fn($v) => $v > 0));
        if (count($ids) === 0) {
            send_error_response('order is empty', 400);
        }

        // Validate all belong to categoryId
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = 'i' . str_repeat('i', count($ids));
        $params = array_merge([$categoryId], $ids);
        $sql = "SELECT COUNT(*) AS cnt FROM airline_options WHERE category_id = ? AND option_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();
        if ($cnt !== count($ids)) {
            send_error_response('Invalid option IDs for this category', 400);
        }

        $conn->begin_transaction();
        $stmtUp = $conn->prepare("UPDATE airline_options SET sort_order = ? WHERE option_id = ?");
        $i = 1;
        foreach ($ids as $optId) {
            $stmtUp->bind_param('ii', $i, $optId);
            $stmtUp->execute();
            $i++;
        }
        $stmtUp->close();
        $conn->commit();

        send_success_response([], 'Options reordered successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reorder options: ' . $e->getMessage());
    }
}

function getProducts($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // 검색 필터
        if (!empty($input['search'])) {
            $whereConditions[] = "p.packageName LIKE ?";
            $params[] = '%' . $input['search'] . '%';
            $types .= 's';
        }
        
        // 상태 필터 (Overall / On Sale / Sale Ended / Temporary Save)
        if (!empty($input['status'])) {
            $st = (string)$input['status'];
            if ($st === 'on_sale') {
                $whereConditions[] = "(p.status = 'active' OR p.packageStatus = 'active')";
            } elseif ($st === 'sale_ended') {
                $whereConditions[] = "(p.status = 'inactive' OR p.packageStatus = 'inactive')";
            } elseif ($st === 'temporary_save') {
                $whereConditions[] = "(p.status IN ('temporary','temp','draft','saved') OR p.packageStatus IN ('temporary','temp','draft','saved'))";
            } else {
                $whereConditions[] = "p.status = ?";
                $params[] = $st;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // 총 개수
        $countSql = "SELECT COUNT(*) as total FROM packages p $whereClause";
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            if (!$countStmt) {
                send_error_response('Failed to prepare count query: ' . ($conn->error ?: 'unknown'), 500);
            }
            if (!mysqli_bind_params_by_ref($countStmt, $types, $params)) {
                send_error_response('Failed to bind count params: ' . ($countStmt->error ?: 'unknown'), 500);
            }
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        // NOTE: 현재 DB 스키마(packages)에 맞춰 조회 컬럼을 사용합니다.
        // - 판매 대상: packages.sales_target
        // - 상태: packages.status (varchar) 우선, 없으면 packages.packageStatus 사용
        // - 판매 기간:
        //   - 우선순위: (sales_start_date/sales_end_date) → sales_period(range string or single date)
        //   - 스키마 편차가 있어 컬럼 유무를 감지하여 안전하게 처리합니다.
        // - 잔여 수량: 별도 집계 테이블이 없으므로 maxParticipants를 표시값으로 사용(추후 개선 가능)

        // 판매기간 컬럼 탐지(환경별)
        $hasSalesStartSnake = __table_has_column($conn, 'packages', 'sales_start_date');
        $hasSalesEndSnake = __table_has_column($conn, 'packages', 'sales_end_date');
        $hasSalesStartCamel = __table_has_column($conn, 'packages', 'salesStartDate');
        $hasSalesEndCamel = __table_has_column($conn, 'packages', 'salesEndDate');

        $salesStartExpr = 'NULL';
        $salesEndExpr = 'NULL';
        if ($hasSalesStartSnake) $salesStartExpr = 'p.sales_start_date';
        else if ($hasSalesStartCamel) $salesStartExpr = 'p.salesStartDate';
        if ($hasSalesEndSnake) $salesEndExpr = 'p.sales_end_date';
        else if ($hasSalesEndCamel) $salesEndExpr = 'p.salesEndDate';

        $dataSql = "SELECT 
            p.packageId,
            p.packageName,
            p.sales_target,
            p.maxParticipants,
            p.sales_period,
            $salesStartExpr AS sales_start_date,
            $salesEndExpr AS sales_end_date,
            p.status,
            p.packageStatus,
            p.createdAt,
            p.thumbnail_image,
            p.packageImageUrl,
            p.packageImage,
            p.product_images,
            p.detail_image
        FROM packages p
        $whereClause
        ORDER BY p.createdAt DESC
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) {
            send_error_response('Failed to prepare data query: ' . ($conn->error ?: 'unknown'), 500);
        }
        if (!mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams)) {
            send_error_response('Failed to bind data params: ' . ($dataStmt->error ?: 'unknown'), 500);
        }
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $products = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $status = $row['status'] ?? '';
            if ($status === '' || $status === null) {
                $status = $row['packageStatus'] ?? 'active';
            }

            // 썸네일 URL 결정(스키마별 호환)
            $thumb = trim((string)($row['thumbnail_image'] ?? ''));
            if ($thumb === '') $thumb = trim((string)($row['packageImageUrl'] ?? ''));
            if ($thumb === '') $thumb = trim((string)($row['packageImage'] ?? ''));
            if ($thumb === '') {
                // product_images가 JSON 배열/문자열인 경우 첫 번째 이미지 사용
                $pi = $row['product_images'] ?? null;
                if (is_string($pi) && $pi !== '') {
                    $tmp = json_decode($pi, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp) && !empty($tmp)) {
                        $first = $tmp[0];
                        if (is_string($first)) $thumb = trim($first);
                        elseif (is_array($first)) $thumb = trim((string)($first['url'] ?? $first['src'] ?? ''));
                    }
                }
            }
            // 경로 보정
            // - 파일명만 저장된 경우: uploads/products 에 저장된 것으로 간주
            // - uploads/... 형태: 앞에 / 보정
            if ($thumb !== '' && !str_starts_with($thumb, 'http') && !str_starts_with($thumb, '/')) {
                if (strpos($thumb, '/') === false) {
                    $thumb = '/uploads/products/' . $thumb;
                } else {
                    $thumb = '/' . ltrim($thumb, '/');
                }
            }

            // 파일이 실제로 없으면(깨진 참조) 썸네일은 비움 → UI는 기본 체크무늬(placeholder) 노출
            if ($thumb !== '' && str_starts_with($thumb, '/uploads/')) {
                $abs = __DIR__ . '/../../../' . ltrim($thumb, '/');
                if (!is_file($abs)) $thumb = '';
            }

            // 판매 기간 표시 문자열 생성
            // - DB에 start/end가 있으면 우선 사용
            // - 없으면 sales_period 그대로(이미 "start ~ end" 문자열일 수 있음)
            $salesStart = trim((string)($row['sales_start_date'] ?? ''));
            $salesEnd = trim((string)($row['sales_end_date'] ?? ''));
            $salesPeriod = '';
            if ($salesStart !== '' && $salesEnd !== '') {
                $salesPeriod = $salesStart . ' ~ ' . $salesEnd;
            } elseif ($salesStart !== '') {
                $salesPeriod = $salesStart;
            } else {
                $salesPeriod = trim((string)($row['sales_period'] ?? ''));
            }

            $products[] = [
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName'] ?? '',
                'targetMarket' => $row['sales_target'] ?? 'B2B',
                'remainingQuantity' => intval($row['maxParticipants'] ?? 0),
                'salesPeriod' => $salesPeriod,
                'status' => $status,
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--,
                'thumbnailUrl' => $thumb
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'products' => $products,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get products: ' . $e->getMessage());
    }
}

function getProductDetail($conn, $input) {
    try {
        $packageId = $input['packageId'] ?? $input['id'] ?? null;
        if (empty($packageId)) {
            send_error_response('Package ID is required');
        }

        $sql = "SELECT * FROM packages WHERE packageId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Product not found', 404);
        }

        $product = $result->fetch_assoc();
        $stmt->close();

        // 다중 숙소 정보 로드 (package_accommodations 테이블에서)
        $accommodations = [];
        try {
            $accomStmt = $conn->prepare("
                SELECT id, sort_order, accommodation_name, accommodation_address,
                       accommodation_description, accommodation_image
                FROM package_accommodations
                WHERE package_id = ?
                ORDER BY sort_order ASC
            ");
            if ($accomStmt) {
                $accomStmt->bind_param('i', $packageId);
                $accomStmt->execute();
                $accomResult = $accomStmt->get_result();
                while ($row = $accomResult->fetch_assoc()) {
                    $accommodations[] = [
                        'id' => $row['id'],
                        'sortOrder' => $row['sort_order'],
                        'name' => $row['accommodation_name'] ?? '',
                        'address' => $row['accommodation_address'] ?? '',
                        'description' => $row['accommodation_description'] ?? '',
                        'image' => $row['accommodation_image'] ?? ''
                    ];
                }
                $accomStmt->close();
            }
        } catch (Throwable $_) {
            // 테이블이 없을 수 있음
        }

        // 다중 숙소가 없으면 기존 공통 숙소 정보로 폴백 (하위 호환성)
        if (empty($accommodations)) {
            $oldName = $product['common_accommodation_name'] ?? '';
            $oldAddr = $product['common_accommodation_address'] ?? '';
            $oldDesc = $product['common_accommodation_description'] ?? '';
            $oldImg = $product['common_accommodation_image'] ?? '';
            if ($oldName || $oldAddr || $oldDesc || $oldImg) {
                $accommodations[] = [
                    'id' => 0,
                    'sortOrder' => 0,
                    'name' => $oldName,
                    'address' => $oldAddr,
                    'description' => $oldDesc,
                    'image' => $oldImg
                ];
            }
        }

        $product['accommodations'] = $accommodations;

        // 공통 교통 정보
        $product['commonTransportation'] = [
            'descriptionHtml' => $product['common_transportation_description'] ?? ''
        ];

        // 인원별 요금 옵션 (package_pricing_options)
        $pricingOptions = [];
        try {
            $poStmt = $conn->prepare("
                SELECT pricing_id, option_name, price, b2b_price
                FROM package_pricing_options
                WHERE package_id = ?
                ORDER BY pricing_id ASC
            ");
            if ($poStmt) {
                $poStmt->bind_param('i', $packageId);
                $poStmt->execute();
                $poResult = $poStmt->get_result();
                while ($row = $poResult->fetch_assoc()) {
                    $pricingOptions[] = [
                        'pricing_id' => $row['pricing_id'],
                        'option_name' => $row['option_name'] ?? '',
                        'price' => floatval($row['price'] ?? 0),
                        'b2b_price' => $row['b2b_price'] !== null ? floatval($row['b2b_price']) : null
                    ];
                }
                $poStmt->close();
            }
        } catch (Throwable $_) {
            // 테이블이 없을 수 있음
        }
        $product['pricingOptions'] = $pricingOptions;

        send_success_response(['product' => $product]);
    } catch (Exception $e) {
        send_error_response('Failed to get product detail: ' . $e->getMessage());
    }
}

function ensurePackageUsageGuideColumns($conn) {
    $need = [
        'usage_guide_file' => "ALTER TABLE packages ADD COLUMN usage_guide_file VARCHAR(255) NULL",
        'usage_guide_name' => "ALTER TABLE packages ADD COLUMN usage_guide_name VARCHAR(255) NULL",
        'usage_guide_size' => "ALTER TABLE packages ADD COLUMN usage_guide_size INT NULL",
    ];
    foreach ($need as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM packages LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$check || $check->num_rows === 0) {
            if (!$conn->query($sql)) {
                send_error_response("Failed to add column $col: " . $conn->error);
            }
        }
    }
}

function get_column_type($conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    if (!$res) return null;
    $row = $res->fetch_assoc();
    $res->free();
    return $row['Type'] ?? null;
}

function createProduct($conn, $input) {
    try {
        // 필수 필드 확인
        $requiredFields = ['packageName'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }
        
        ensurePackageUsageGuideColumns($conn);

        // 필드 매핑 (현재 어드민 폼 기준)
        $packageName = trim((string)$input['packageName']);
        $salesTarget = trim((string)($input['targetMarket'] ?? $input['sales_target'] ?? 'B2B'));
        $mainCategory = trim((string)($input['mainCategory'] ?? $input['packageCategory'] ?? ''));
        $subCategory = trim((string)($input['subCategory'] ?? ''));
        $description = (string)($input['description'] ?? $input['packageDescription'] ?? '');

        $salesStart = trim((string)($input['salesStartDate'] ?? ''));
        $salesEnd = trim((string)($input['salesEndDate'] ?? ''));
        // packages.sales_period 컬럼 타입에 맞게 저장
        // - date/datetime: 시작일만 저장(범위 문자열 저장 불가)
        // - varchar/text: "start ~ end" 범위 문자열 저장
        $salesPeriod = null;
        $spType = get_column_type($conn, 'packages', 'sales_period');
        $spTypeLower = strtolower((string)$spType);
        if ($salesStart !== '' && $salesEnd !== '') {
            if (str_starts_with($spTypeLower, 'date') || str_starts_with($spTypeLower, 'datetime') || str_starts_with($spTypeLower, 'timestamp')) {
                $salesPeriod = $salesStart;
            } else {
                $salesPeriod = $salesStart . ' ~ ' . $salesEnd;
            }
        } elseif ($salesStart !== '') {
            $salesPeriod = $salesStart;
        }

        $maxParticipants = isset($input['maxParticipants']) ? intval($input['maxParticipants']) : (isset($input['baseMaxParticipants']) ? intval($input['baseMaxParticipants']) : null);
        $basePrice = null;
        if (isset($input['base_price']) || isset($input['baseFlightFare'])) {
            $raw = (string)($input['base_price'] ?? $input['baseFlightFare'] ?? '');
            $raw = preg_replace('/[^0-9]/', '', $raw);
            $basePrice = ($raw !== '') ? intval($raw) : null;
        }
        // packages.packagePrice는 일부 스키마에서 NOT NULL일 수 있어 기본 값을 채워줍니다.
        // 어드민 폼에서는 baseFlightFare(기본 요금) 기반으로 저장하는 것으로 처리합니다.
        $packagePrice = ($basePrice !== null) ? $basePrice : 0;

        // B2C 가격 (packagePrice) - 폼에서 직접 전달받은 경우 우선
        if (isset($input['packagePrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['packagePrice']);
            $packagePrice = ($raw !== '') ? floatval($raw) : 0;
        }

        // B2B 가격
        $b2bPrice = null;
        if (isset($input['b2bPrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['b2bPrice']);
            $b2bPrice = ($raw !== '') ? floatval($raw) : null;
        }

        $sql = "INSERT INTO packages (packageName, packagePrice, b2b_price, sales_target, packageCategory, subCategory, packageDescription, sales_period, maxParticipants, base_price, isActive, packageStatus, createdAt)
                VALUES (?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?, ?, ?, ?, 1, 'active', NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare product insert: ' . $conn->error);
        $stmt->bind_param(
            'sddsssssii',
            $packageName,
            $packagePrice,
            $b2bPrice,
            $salesTarget,
            $mainCategory,
            $subCategory,
            $description,
            $salesPeriod,
            $maxParticipants,
            $basePrice
        );
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to create product: ' . $err);
        }
        $packageId = $conn->insert_id;
        $stmt->close();

        // 안내문 PDF 업로드 처리
        if (isset($_FILES['usageGuideFile']) && $_FILES['usageGuideFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['usageGuideFile']['tmp_name'];
            $origName = $_FILES['usageGuideFile']['name'] ?? ('usage-guide-' . $packageId . '.pdf');
            $size = intval($_FILES['usageGuideFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                send_error_response('Usage guide file must be a PDF');
            }
            $uploadDir = __DIR__ . '/../../../uploads/usage_guides/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'usage_guide_' . $packageId . '_' . time() . '_' . uniqid() . '.pdf';
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload usage guide file');
            }
            $relPath = 'uploads/usage_guides/' . $fileName;

            $up = $conn->prepare("UPDATE packages SET usage_guide_file = ?, usage_guide_name = ?, usage_guide_size = ? WHERE packageId = ?");
            if (!$up) send_error_response('Failed to prepare usage guide update: ' . $conn->error);
            $up->bind_param('ssii', $relPath, $origName, $size, $packageId);
            $up->execute();
            $up->close();
        }

        // SMT (#165): Flyer file upload handling for new product
        if (isset($_FILES['flyerFile']) && $_FILES['flyerFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['flyerFile']['tmp_name'];
            $origName = $_FILES['flyerFile']['name'] ?? ('flyer-' . $packageId . '.pdf');
            $size = intval($_FILES['flyerFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowedExt)) {
                send_error_response('Flyer file must be PDF, JPG, JPEG, or PNG');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'flyer_' . $packageId . '_' . time() . '_' . uniqid() . '.' . $ext;
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload flyer file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET flyer_file = ?, flyer_name = ?, flyer_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $packageId);
                $up->execute();
                $up->close();
            }
        }

        // SMT (#165): Itinerary file upload handling for new product
        if (isset($_FILES['itineraryFile']) && $_FILES['itineraryFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['itineraryFile']['tmp_name'];
            $origName = $_FILES['itineraryFile']['name'] ?? ('itinerary-' . $packageId . '.pdf');
            $size = intval($_FILES['itineraryFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                send_error_response('Itinerary file must be PDF');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'itinerary_' . $packageId . '_' . time() . '_' . uniqid() . '.pdf';
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload itinerary file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET itinerary_file = ?, itinerary_name = ?, itinerary_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $packageId);
                $up->execute();
                $up->close();
            }
        }

        // SMT (#165): Detail file upload handling for new product
        if (isset($_FILES['detailFile']) && $_FILES['detailFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['detailFile']['tmp_name'];
            $origName = $_FILES['detailFile']['name'] ?? ('detail-' . $packageId . '.pdf');
            $size = intval($_FILES['detailFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowedExt)) {
                send_error_response('Detail file must be PDF, JPG, JPEG, or PNG');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'detail_' . $packageId . '_' . time() . '_' . uniqid() . '.' . $ext;
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload detail file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET detail_file = ?, detail_name = ?, detail_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $packageId);
                $up->execute();
                $up->close();
            }
        }

        // 판매기간 내 모든 날짜에 대해 package_available_dates 자동 생성
        // (B2C/B2B 기본 가격으로 레코드 생성)
        if ($salesPeriod !== null && $salesPeriod !== '') {
            // 판매기간 파싱: "YYYY-MM-DD ~ YYYY-MM-DD" 형식
            $parts = preg_split('/\s*~\s*/', $salesPeriod);
            $startDate = isset($parts[0]) ? trim($parts[0]) : '';
            $endDate = isset($parts[1]) ? trim($parts[1]) : $startDate;

            if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                    $endDate = $startDate;
                }

                // 기본 가격 및 정원 (위에서 이미 정의된 변수 사용)
                $defaultB2cPrice = $packagePrice;
                $defaultB2bPrice = $b2bPrice;
                $defaultCapacity = ($maxParticipants !== null && $maxParticipants > 0) ? $maxParticipants : 20;

                // 날짜 범위 순회하며 레코드 생성
                $currentDate = $startDate;
                $endDateTs = strtotime($endDate);
                $maxDays = 365; // 안전 제한
                $insertedCount = 0;

                while (strtotime($currentDate) <= $endDateTs && $insertedCount < $maxDays) {
                    // 해당 날짜에 레코드가 있는지 확인
                    $chkStmt = $conn->prepare("SELECT id FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                    $chkStmt->bind_param('is', $packageId, $currentDate);
                    $chkStmt->execute();
                    $chkRes = $chkStmt->get_result();
                    $exists = $chkRes && $chkRes->num_rows > 0;
                    $chkStmt->close();

                    if (!$exists) {
                        $insStmt = $conn->prepare("INSERT INTO package_available_dates (package_id, available_date, capacity, price, b2b_price, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
                        $insStmt->bind_param('isidd', $packageId, $currentDate, $defaultCapacity, $defaultB2cPrice, $defaultB2bPrice);
                        $insStmt->execute();
                        $insStmt->close();
                    }

                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                    $insertedCount++;
                }
            }
        }

        send_success_response(['packageId' => $packageId], 'Product created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create product: ' . $e->getMessage());
    }
}

function updateProduct($conn, $input) {
    try {
        $packageId = $input['packageId'] ?? $input['id'] ?? null;
        if (empty($packageId)) {
            send_error_response('Package ID is required');
        }
        ensurePackageUsageGuideColumns($conn);

        $updates = [];
        $values = [];
        $types = '';

        if (isset($input['packageName'])) {
            $updates[] = "packageName = ?";
            $values[] = trim((string)$input['packageName']);
            $types .= 's';
        }
        if (isset($input['targetMarket'])) {
            $updates[] = "sales_target = ?";
            $values[] = trim((string)$input['targetMarket']);
            $types .= 's';
        }
        if (isset($input['mainCategory'])) {
            $updates[] = "packageCategory = NULLIF(?, '')";
            $values[] = trim((string)$input['mainCategory']);
            $types .= 's';
        }
        if (isset($input['subCategory'])) {
            $updates[] = "subCategory = NULLIF(?, '')";
            $values[] = trim((string)$input['subCategory']);
            $types .= 's';
        }
        if (isset($input['description'])) {
            $updates[] = "packageDescription = ?";
            $values[] = (string)$input['description'];
            $types .= 's';
        }

        $salesStart = trim((string)($input['salesStartDate'] ?? ''));
        $salesEnd = trim((string)($input['salesEndDate'] ?? ''));
        // salesPeriod ("YYYY-MM-DD ~ YYYY-MM-DD") 에서도 start/end 파싱
        if ($salesStart === '' && $salesEnd === '') {
            $salesPeriodInput = trim((string)($input['salesPeriod'] ?? $_POST['salesPeriod'] ?? ''));
            if ($salesPeriodInput !== '') {
                $spParts = preg_split('/\s*~\s*/', $salesPeriodInput);
                $salesStart = isset($spParts[0]) ? trim($spParts[0]) : '';
                $salesEnd = isset($spParts[1]) ? trim($spParts[1]) : '';
            }
        }
        if ($salesStart !== '' || $salesEnd !== '') {
            $spType = get_column_type($conn, 'packages', 'sales_period');
            $spTypeLower = strtolower((string)$spType);
            $salesPeriod = null;
            if ($salesStart !== '' && $salesEnd !== '') {
                if (str_starts_with($spTypeLower, 'date') || str_starts_with($spTypeLower, 'datetime') || str_starts_with($spTypeLower, 'timestamp')) {
                    $salesPeriod = $salesStart;
                } else {
                    $salesPeriod = $salesStart . ' ~ ' . $salesEnd;
                }
            } elseif ($salesStart !== '') {
                $salesPeriod = $salesStart;
            }
            $updates[] = "sales_period = ?";
            $values[] = $salesPeriod;
            $types .= 's';

            // sales_start_date, sales_end_date 컬럼도 함께 업데이트
            if ($salesStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $salesStart)) {
                $updates[] = "sales_start_date = ?";
                $values[] = $salesStart;
                $types .= 's';
            }
            if ($salesEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $salesEnd)) {
                $updates[] = "sales_end_date = ?";
                $values[] = $salesEnd;
                $types .= 's';
            }
        }

        if (isset($input['maxParticipants'])) {
            $updates[] = "maxParticipants = ?";
            $values[] = intval($input['maxParticipants']);
            $types .= 'i';
        } elseif (isset($input['baseMaxParticipants'])) {
            $updates[] = "maxParticipants = ?";
            $values[] = intval($input['baseMaxParticipants']);
            $types .= 'i';
        }

        if (isset($input['baseFlightFare'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['baseFlightFare']);
            $updates[] = "base_price = ?";
            $values[] = ($raw !== '') ? intval($raw) : null;
            $types .= 'i';
        }

        // B2C 가격 (packagePrice)
        if (isset($input['packagePrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['packagePrice']);
            $updates[] = "packagePrice = ?";
            $values[] = ($raw !== '') ? floatval($raw) : 0;
            $types .= 'd';
        }

        // B2B 가격 (b2bPrice - 기본 B2B 가격)
        // b2bPrice가 있으면 우선 사용, 없으면 b2bAdultPrice 사용
        $b2bPriceHandled = false;
        if (isset($input['b2bPrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['b2bPrice']);
            $updates[] = "b2b_price = ?";
            $values[] = ($raw !== '') ? floatval($raw) : null;
            $types .= 'd';
            $b2bPriceHandled = true;
        }

        // B2B 가격 처리 (이중 가격 시스템) - 인원별 요금에서 추출된 가격
        // b2bPrice가 이미 처리되었으면 건너뜀
        if (!$b2bPriceHandled && isset($input['b2bAdultPrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['b2bAdultPrice']);
            $updates[] = "b2b_price = ?";
            $values[] = ($raw !== '') ? floatval($raw) : null;
            $types .= 'd';
        }
        if (isset($input['b2bChildPrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['b2bChildPrice']);
            $updates[] = "b2b_child_price = ?";
            $values[] = ($raw !== '') ? floatval($raw) : null;
            $types .= 'd';
        }
        if (isset($input['b2bInfantPrice'])) {
            $raw = preg_replace('/[^0-9]/', '', (string)$input['b2bInfantPrice']);
            $updates[] = "b2b_infant_price = ?";
            $values[] = ($raw !== '') ? floatval($raw) : null;
            $types .= 'd';
        }

        // 가격 텍스트 (문자열로 표시할 가격)
        // DEBUG: Log the input values
        error_log("updateProduct - priceDisplayText in input: " . (array_key_exists('priceDisplayText', $input) ? 'YES' : 'NO') . " | value: " . ($input['priceDisplayText'] ?? 'NULL'));
        error_log("updateProduct - b2bPriceDisplayText in input: " . (array_key_exists('b2bPriceDisplayText', $input) ? 'YES' : 'NO') . " | value: " . ($input['b2bPriceDisplayText'] ?? 'NULL'));

        if (array_key_exists('priceDisplayText', $input)) {
            $updates[] = "price_display_text = ?";
            $val = trim((string)$input['priceDisplayText']);
            $values[] = ($val !== '') ? $val : null;
            $types .= 's';
            error_log("updateProduct - Adding price_display_text to updates: " . ($val !== '' ? $val : 'NULL'));
        }
        if (array_key_exists('b2bPriceDisplayText', $input)) {
            $updates[] = "b2b_price_display_text = ?";
            $val = trim((string)$input['b2bPriceDisplayText']);
            $values[] = ($val !== '') ? $val : null;
            $types .= 's';
            error_log("updateProduct - Adding b2b_price_display_text to updates: " . ($val !== '' ? $val : 'NULL'));
        }

        if (!empty($updates)) {
            $values[] = intval($packageId);
            $types .= 'i';
            $sql = "UPDATE packages SET " . implode(', ', $updates) . " WHERE packageId = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) send_error_response('Failed to prepare product update: ' . $conn->error);
            mysqli_bind_params_by_ref($stmt, $types, $values);
            if (!$stmt->execute()) {
                $err = $stmt->error ?: $conn->error;
                $stmt->close();
                send_error_response('Failed to update product: ' . $err);
            }
            $stmt->close();
        }

        // 안내문 PDF 업로드 처리(교체)
        if (isset($_FILES['usageGuideFile']) && $_FILES['usageGuideFile']['error'] === UPLOAD_ERR_OK) {
            $pid = intval($packageId);
            $cur = $conn->prepare("SELECT usage_guide_file FROM packages WHERE packageId = ? LIMIT 1");
            $cur->bind_param('i', $pid);
            $cur->execute();
            $curRes = $cur->get_result();
            $curRow = $curRes ? $curRes->fetch_assoc() : null;
            $cur->close();
            $oldRel = $curRow['usage_guide_file'] ?? null;

            $tmp = $_FILES['usageGuideFile']['tmp_name'];
            $origName = $_FILES['usageGuideFile']['name'] ?? ('usage-guide-' . $pid . '.pdf');
            $size = intval($_FILES['usageGuideFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                send_error_response('Usage guide file must be a PDF');
            }
            $uploadDir = __DIR__ . '/../../../uploads/usage_guides/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'usage_guide_' . $pid . '_' . time() . '_' . uniqid() . '.pdf';
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload usage guide file');
            }
            $relPath = 'uploads/usage_guides/' . $fileName;

            // old 삭제
            if (!empty($oldRel)) {
                $oldAbs = __DIR__ . '/../../../' . ltrim($oldRel, '/');
                if (is_file($oldAbs)) @unlink($oldAbs);
            }

            $up = $conn->prepare("UPDATE packages SET usage_guide_file = ?, usage_guide_name = ?, usage_guide_size = ? WHERE packageId = ?");
            if (!$up) send_error_response('Failed to prepare usage guide update: ' . $conn->error);
            $up->bind_param('ssii', $relPath, $origName, $size, $pid);
            $up->execute();
            $up->close();
        }

        // SMT (#165): Flyer file upload handling
        $pid = intval($packageId);
        if (isset($_FILES['flyerFile']) && $_FILES['flyerFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['flyerFile']['tmp_name'];
            $origName = $_FILES['flyerFile']['name'] ?? ('flyer-' . $pid . '.pdf');
            $size = intval($_FILES['flyerFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowedExt)) {
                send_error_response('Flyer file must be PDF, JPG, JPEG, or PNG');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'flyer_' . $pid . '_' . time() . '_' . uniqid() . '.' . $ext;
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload flyer file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET flyer_file = ?, flyer_name = ?, flyer_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $pid);
                $up->execute();
                $up->close();
            }
        } elseif (!empty($_POST['flyerFile_existing'])) {
            // Keep existing file (no change)
        }

        // SMT (#165): Itinerary file upload handling
        if (isset($_FILES['itineraryFile']) && $_FILES['itineraryFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['itineraryFile']['tmp_name'];
            $origName = $_FILES['itineraryFile']['name'] ?? ('itinerary-' . $pid . '.pdf');
            $size = intval($_FILES['itineraryFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                send_error_response('Itinerary file must be PDF');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'itinerary_' . $pid . '_' . time() . '_' . uniqid() . '.pdf';
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload itinerary file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET itinerary_file = ?, itinerary_name = ?, itinerary_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $pid);
                $up->execute();
                $up->close();
            }
        } elseif (!empty($_POST['itineraryFile_existing'])) {
            // Keep existing file (no change)
        }

        // SMT (#165): Detail file upload handling
        if (isset($_FILES['detailFile']) && $_FILES['detailFile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['detailFile']['tmp_name'];
            $origName = $_FILES['detailFile']['name'] ?? ('detail-' . $pid . '.pdf');
            $size = intval($_FILES['detailFile']['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowedExt)) {
                send_error_response('Detail file must be PDF, JPG, JPEG, or PNG');
            }
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'detail_' . $pid . '_' . time() . '_' . uniqid() . '.' . $ext;
            $absPath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmp, $absPath)) {
                send_error_response('Failed to upload detail file');
            }
            $relPath = 'uploads/products/' . $fileName;
            $up = $conn->prepare("UPDATE packages SET detail_file = ?, detail_name = ?, detail_size = ? WHERE packageId = ?");
            if ($up) {
                $up->bind_param('ssii', $relPath, $origName, $size, $pid);
                $up->execute();
                $up->close();
            }
        } elseif (!empty($_POST['detailFile_existing'])) {
            // Keep existing file (no change)
        }

        // 인원별 요금 옵션 처리 (package_pricing_options)
        $pid = intval($packageId);
        if (isset($_POST['optionName']) && is_array($_POST['optionName'])) {
            $optionNames = $_POST['optionName'];
            $optionPrices = $_POST['optionPrice'] ?? [];
            $optionB2bPrices = $_POST['optionB2bPrice'] ?? [];

            // 기존 옵션 삭제 후 새로 삽입 (간단한 replace 전략)
            $delStmt = $conn->prepare("DELETE FROM package_pricing_options WHERE package_id = ?");
            if ($delStmt) {
                $delStmt->bind_param('i', $pid);
                $delStmt->execute();
                $delStmt->close();
            }

            $insStmt = $conn->prepare("INSERT INTO package_pricing_options (package_id, option_name, price, b2b_price) VALUES (?, ?, ?, ?)");
            if ($insStmt) {
                for ($i = 0; $i < count($optionNames); $i++) {
                    $optName = trim((string)($optionNames[$i] ?? ''));
                    if ($optName === '') continue;
                    $optPrice = floatval($optionPrices[$i] ?? 0);
                    $optB2bPrice = isset($optionB2bPrices[$i]) && $optionB2bPrices[$i] !== '' ? floatval($optionB2bPrices[$i]) : null;
                    $insStmt->bind_param('isdd', $pid, $optName, $optPrice, $optB2bPrice);
                    $insStmt->execute();
                }
                $insStmt->close();
            }
        }

        // 삭제된 날짜 처리 (Daily Sales Adjustment에서 삭제된 날짜)
        if (isset($_POST['deletedDates']) && is_array($_POST['deletedDates'])) {
            foreach ($_POST['deletedDates'] as $delDate) {
                $delDate = trim((string)$delDate);
                if ($delDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $delDate)) continue;
                $delStmt = $conn->prepare("DELETE FROM package_available_dates WHERE package_id = ? AND available_date = ?");
                if ($delStmt) {
                    $delStmt->bind_param('is', $pid, $delDate);
                    $delStmt->execute();
                    $delStmt->close();
                }
            }
        }

        // 일자별 판매조정 (Daily Sales Adjustment) 처리
        if (isset($_POST['availabilityDate']) && is_array($_POST['availabilityDate'])) {
            $dates = $_POST['availabilityDate'];
            $seats = $_POST['availabilitySeats'] ?? [];
            $prices = $_POST['availabilityPrice'] ?? [];
            $b2bPrices = $_POST['availabilityB2bPrice'] ?? [];

            for ($i = 0; $i < count($dates); $i++) {
                $date = trim((string)($dates[$i] ?? ''));
                if ($date === '') continue;
                $seatVal = intval($seats[$i] ?? 0);
                $priceVal = floatval($prices[$i] ?? 0);
                $b2bPriceVal = isset($b2bPrices[$i]) && $b2bPrices[$i] !== '' && $b2bPrices[$i] !== '0' ? floatval($b2bPrices[$i]) : null;

                // UPSERT: INSERT or UPDATE
                $checkStmt = $conn->prepare("SELECT id FROM package_available_dates WHERE package_id = ? AND available_date = ?");
                if ($checkStmt) {
                    $checkStmt->bind_param('is', $pid, $date);
                    $checkStmt->execute();
                    $exists = $checkStmt->get_result()->fetch_assoc();
                    $checkStmt->close();

                    if ($exists) {
                        $upStmt = $conn->prepare("UPDATE package_available_dates SET capacity = ?, price = ?, b2b_price = ? WHERE package_id = ? AND available_date = ?");
                        if ($upStmt) {
                            $upStmt->bind_param('iddis', $seatVal, $priceVal, $b2bPriceVal, $pid, $date);
                            $upStmt->execute();
                            $upStmt->close();
                        }
                    } else {
                        $insStmt = $conn->prepare("INSERT INTO package_available_dates (package_id, available_date, capacity, price, b2b_price, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
                        if ($insStmt) {
                            $insStmt->bind_param('isidd', $pid, $date, $seatVal, $priceVal, $b2bPriceVal);
                            $insStmt->execute();
                            $insStmt->close();
                        }
                    }
                }
            }
        }

        // 판매기간 내 모든 날짜에 대해 package_available_dates 자동 생성
        // (B2C/B2B 기본 가격으로 레코드가 없는 날짜만 생성)
        $salesPeriodRaw = trim((string)($input['salesPeriod'] ?? $_POST['salesPeriod'] ?? ''));
        if ($salesPeriodRaw !== '') {
            // 판매기간 파싱: "YYYY-MM-DD ~ YYYY-MM-DD" 형식
            $parts = preg_split('/\s*~\s*/', $salesPeriodRaw);
            $startDate = isset($parts[0]) ? trim($parts[0]) : '';
            $endDate = isset($parts[1]) ? trim($parts[1]) : $startDate;

            if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                    $endDate = $startDate;
                }

                // 기본 가격 가져오기
                $defaultB2cPrice = 0;
                $defaultB2bPrice = null;
                $defaultCapacity = 0;
                $priceStmt = $conn->prepare("SELECT packagePrice, b2b_price, maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
                if ($priceStmt) {
                    $priceStmt->bind_param('i', $pid);
                    $priceStmt->execute();
                    $priceRow = $priceStmt->get_result()->fetch_assoc();
                    $priceStmt->close();
                    if ($priceRow) {
                        $defaultB2cPrice = floatval($priceRow['packagePrice'] ?? 0);
                        $defaultB2bPrice = $priceRow['b2b_price'] !== null ? floatval($priceRow['b2b_price']) : null;
                        $defaultCapacity = intval($priceRow['maxParticipants'] ?? 0);
                    }
                }

                // 날짜 범위 순회하며 레코드 생성 (없는 날짜만)
                $currentDate = $startDate;
                $endDateTs = strtotime($endDate);
                $insertedCount = 0;
                $maxDays = 365; // 최대 1년치만 생성 (안전장치)

                while (strtotime($currentDate) <= $endDateTs && $insertedCount < $maxDays) {
                    // 이미 존재하는지 확인
                    $checkStmt = $conn->prepare("SELECT id FROM package_available_dates WHERE package_id = ? AND available_date = ?");
                    if ($checkStmt) {
                        $checkStmt->bind_param('is', $pid, $currentDate);
                        $checkStmt->execute();
                        $exists = $checkStmt->get_result()->fetch_assoc();
                        $checkStmt->close();

                        if (!$exists) {
                            // 새 레코드 생성
                            $insStmt = $conn->prepare("INSERT INTO package_available_dates (package_id, available_date, capacity, price, b2b_price, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
                            if ($insStmt) {
                                $insStmt->bind_param('isidd', $pid, $currentDate, $defaultCapacity, $defaultB2cPrice, $defaultB2bPrice);
                                $insStmt->execute();
                                $insStmt->close();
                            }
                        }
                    }

                    // 다음 날짜로
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                    $insertedCount++;
                }
            }
        }

        send_success_response([], 'Product updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update product: ' . $e->getMessage());
    }
}

function downloadUsageGuide($conn, $input) {
    try {
        $packageId = $input['packageId'] ?? $input['id'] ?? null;
        if (empty($packageId)) send_error_response('Package ID is required');
        ensurePackageUsageGuideColumns($conn);

        $pid = intval($packageId);
        $stmt = $conn->prepare("SELECT usage_guide_file, usage_guide_name FROM packages WHERE packageId = ? LIMIT 1");
        if (!$stmt) send_error_response('Failed to prepare download query: ' . $conn->error);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $rel = $row['usage_guide_file'] ?? '';
        if ($rel === '') send_error_response('Usage guide not found', 404);

        $abs = __DIR__ . '/../../../' . ltrim($rel, '/');
        if (!is_file($abs)) send_error_response('File missing on server', 404);
        $name = $row['usage_guide_name'] ?? basename($abs);
        $name = $name !== '' ? $name : 'usage-guide.pdf';

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download usage guide: ' . $e->getMessage(), 500);
    }
}

function deleteUsageGuide($conn, $input) {
    try {
        $packageId = $input['packageId'] ?? $input['id'] ?? null;
        if (empty($packageId)) send_error_response('Package ID is required');
        ensurePackageUsageGuideColumns($conn);
        $pid = intval($packageId);

        $stmt = $conn->prepare("SELECT usage_guide_file FROM packages WHERE packageId = ? LIMIT 1");
        if (!$stmt) send_error_response('Failed to prepare delete query: ' . $conn->error);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $rel = $row['usage_guide_file'] ?? '';

        if ($rel !== '') {
            $abs = __DIR__ . '/../../../' . ltrim($rel, '/');
            if (is_file($abs)) @unlink($abs);
        }

        $up = $conn->prepare("UPDATE packages SET usage_guide_file = NULL, usage_guide_name = NULL, usage_guide_size = NULL WHERE packageId = ?");
        if (!$up) send_error_response('Failed to prepare delete update: ' . $conn->error);
        $up->bind_param('i', $pid);
        $up->execute();
        $up->close();

        send_success_response([], 'Usage guide deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete usage guide: ' . $e->getMessage(), 500);
    }
}

function deleteProduct($conn, $input) {
    try {
        $packageId = $input['packageId'] ?? $input['productId'] ?? $input['id'] ?? null;
        if (empty($packageId)) {
            send_error_response('Package ID is required');
        }

        $packageId = intval($packageId);

        // 관련 데이터 먼저 삭제 (외래키 제약 조건 고려)
        $relatedTables = [
            'package_images' => 'packageId',
            'package_itinerary' => 'packageId',
            'package_options' => 'packageId',
            'package_pricing_options' => 'packageId',
            'package_schedules' => 'packageId',
            'package_available_dates' => 'packageId',
            'package_attractions' => 'packageId',
            'package_flights' => 'packageId',
            'package_i18n' => 'packageId',
            'package_usage_guide' => 'packageId',
            'package_travel_costs' => 'packageId',
            'package_views' => 'packageId',
            'package_file' => 'packageId',
        ];

        foreach ($relatedTables as $table => $column) {
            try {
                $delSql = "DELETE FROM `$table` WHERE `$column` = ?";
                $delStmt = $conn->prepare($delSql);
                if ($delStmt) {
                    $delStmt->bind_param('i', $packageId);
                    $delStmt->execute();
                    $delStmt->close();
                }
            } catch (Exception $e) {
                // 테이블이 없거나 에러 발생 시 무시하고 계속 진행
                error_log("deleteProduct: Failed to delete from $table - " . $e->getMessage());
            }
        }

        // 메인 packages 테이블 삭제
        $sql = "DELETE FROM packages WHERE packageId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $packageId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            send_error_response('Product not found or already deleted');
            return;
        }

        $stmt->close();

        send_success_response([], 'Product deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete product: ' . $e->getMessage());
    }
}

// ========== Agent 관리 함수들 ==========

function getAgents($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // safety: accounts.accountType 컬럼 존재 시 agent 계정만 노출
        $hasAccountType = false;
        try {
            $col = $conn->query("SHOW COLUMNS FROM accounts LIKE 'accountType'");
            $hasAccountType = ($col && $col->num_rows > 0);
        } catch (Throwable $e) {
            $hasAccountType = false;
        }
        
        $whereConditions = [];
        $params = [];
        $types = '';

        // accountType = 'agent'인 계정만 조회
        if ($hasAccountType) $whereConditions[] = "ac.accountType = 'agent'";

        // personInChargeEmail이 없는 에이전트는 제외 (아직 로그인 안한 에이전트)
        $whereConditions[] = "(a.personInChargeEmail IS NOT NULL AND a.personInChargeEmail <> '')";

        // 요구사항: 지점명(Agent Name/Branch Name) 또는 이메일 기준 검색
        if (!empty($input['search'])) {
            $whereConditions[] = "(a.agencyName LIKE ? OR CONCAT(a.fName, ' ', a.lName) LIKE ? OR ac.emailAddress LIKE ?)";
            $searchTerm = '%' . $input['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // 계약 상태 필터 (UI): overall / under_contract / contract_ended
        $contractStatus = $input['contractStatus'] ?? ($input['status'] ?? '');
        if (!empty($contractStatus)) {
            if ($contractStatus === 'under_contract') {
                $whereConditions[] = "(a.contractEndDate IS NULL OR a.contractEndDate >= CURDATE())";
            } elseif ($contractStatus === 'contract_ended') {
                $whereConditions[] = "(a.contractEndDate IS NOT NULL AND a.contractEndDate < CURDATE())";
            } else {
                // fallback: legacy accountStatus filter
                $whereConditions[] = "ac.accountStatus = ?";
                $params[] = $contractStatus;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM agent a
                     LEFT JOIN accounts ac ON a.accountId = ac.accountId
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $dataSql = "SELECT
            a.agentId,
            a.accountId,
            NULL as companyId,
            '' as companyName,
            a.agencyName as branchName,
            CONCAT(a.fName, ' ', a.lName) as managerName,
            ac.username,
            ac.emailAddress,
            a.contactNo,
            a.agentType,
            a.agentRole,
            a.contractStartDate,
            a.contractEndDate,
            ac.accountStatus,
            ac.createdAt
        FROM agent a
        LEFT JOIN accounts ac ON a.accountId = ac.accountId
        $whereClause
        ORDER BY ac.createdAt DESC
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $agents = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $end = $row['contractEndDate'] ?? null;
            $activityStatus = 'under_contract';
            if (!empty($end) && strtotime(substr((string)$end, 0, 10)) < strtotime(date('Y-m-d'))) {
                $activityStatus = 'contract_ended';
            }
            $agents[] = [
                'agentId' => $row['agentId'],
                'accountId' => $row['accountId'],
                'companyId' => $row['companyId'],
                'companyName' => $row['companyName'] ?? '',
                'branchName' => $row['branchName'] ?? '',
                'managerName' => $row['managerName'] ?? '',
                'username' => $row['username'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'contactNo' => $row['contactNo'] ?? '',
                'agentType' => $row['agentType'] ?? '',
                'agentRole' => $row['agentRole'] ?? '',
                'contractStartDate' => $row['contractStartDate'] ?? null,
                'contractEndDate' => $row['contractEndDate'] ?? null,
                'activityStatus' => $activityStatus,
                'status' => $row['accountStatus'] ?? 'active',
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'agents' => $agents,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get agents: ' . $e->getMessage());
    }
}

function exportAgentsCsv($conn, $input) {
    try {
        $search = trim($input['search'] ?? '');
        $contractStatus = trim($input['contractStatus'] ?? ($input['status'] ?? ''));

        // safety: accounts.accountType 컬럼 존재 시 agent 계정만 노출
        $hasAccountType = false;
        try {
            $col = $conn->query("SHOW COLUMNS FROM accounts LIKE 'accountType'");
            $hasAccountType = ($col && $col->num_rows > 0);
        } catch (Throwable $e) {
            $hasAccountType = false;
        }

        $whereConditions = [];
        $params = [];
        $types = '';

        // 고객/기타 계정이 agent 테이블에 잘못 매핑되는 케이스 방지
        $whereConditions[] = "a.agentId LIKE 'AGT%'";
        if ($hasAccountType) $whereConditions[] = "ac.accountType = 'agent'";

        // personInChargeEmail이 없는 에이전트는 제외 (아직 로그인 안한 에이전트)
        $whereConditions[] = "(a.personInChargeEmail IS NOT NULL AND a.personInChargeEmail <> '')";

        // 요구사항: 지점명(Agent Name/Branch Name) 기준 검색
        if ($search !== '') {
            $whereConditions[] = "(a.agencyName LIKE ? OR CONCAT(a.fName, ' ', a.lName) LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $types .= 'ss';
        }

        if ($contractStatus === 'under_contract') {
            $whereConditions[] = "(a.contractEndDate IS NULL OR a.contractEndDate >= CURDATE())";
        } else if ($contractStatus === 'contract_ended') {
            $whereConditions[] = "(a.contractEndDate IS NOT NULL AND a.contractEndDate < CURDATE())";
        }

        $whereClause = !empty($whereConditions) ? ('WHERE ' . implode(' AND ', $whereConditions)) : '';

        $sql = "SELECT
                    a.agentId,
                    '' as companyName,
                    a.agencyName as branchName,
                    CONCAT(a.fName, ' ', a.lName) as managerName,
                    ac.emailAddress,
                    a.contactNo,
                    a.contractStartDate,
                    a.contractEndDate
                FROM agent a
                LEFT JOIN accounts ac ON a.accountId = ac.accountId
                
                
                $whereClause
                ORDER BY ac.createdAt DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agents_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Agent ID', 'Agent Name', 'Manager Name', 'Email', 'Contact', 'Contract Period', 'Activity Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            $start = $row['contractStartDate'] ? substr((string)$row['contractStartDate'], 0, 10) : '';
            $end = $row['contractEndDate'] ? substr((string)$row['contractEndDate'], 0, 10) : '';
            $period = ($start && $end) ? ($start . ' ~ ' . $end) : '';
            $activity = 'Under Contract';
            if ($end && strtotime($end) < strtotime(date('Y-m-d'))) $activity = 'Contract Ended';
            $agentName = ($row['branchName'] ?: ($row['companyName'] ?? ''));

            fputcsv($out, [
                $no++,
                $row['agentId'] ?? '',
                $agentName,
                $row['managerName'] ?? '',
                $row['emailAddress'] ?? '',
                $row['contactNo'] ?? '',
                $period,
                $activity
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export agents CSV: ' . $e->getMessage(), 500);
    }
}

function getAgentDetail($conn, $input) {
    try {
        $agentId = $input['agentId'] ?? $input['id'] ?? null;
        if (empty($agentId)) {
            send_error_response('Agent ID is required');
        }

        // agent.memo 컬럼이 없으면 추가 (퍼블/상세에서 메모 하드코딩 제거 목적)
        $memoCol = $conn->query("SHOW COLUMNS FROM agent LIKE 'memo'");
        if ($memoCol && $memoCol->num_rows === 0) {
            // TEXT nullable
            $conn->query("ALTER TABLE agent ADD COLUMN memo TEXT NULL");
        }
        
        // agentId는 'AGT001' 형식의 문자열
        $sql = "SELECT
            a.*,
            ac.username,
            ac.emailAddress,
            ac.accountStatus,
            ac.createdAt,
            a.agencyName as companyName,
            '' as businessUnit,
            '' as branchName
        FROM agent a
        LEFT JOIN accounts ac ON a.accountId = ac.accountId
        WHERE a.agentId = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $agentId);
        if (!$stmt->execute()) {
            $error = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to execute query: ' . $error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            send_error_response('Agent not found', 404);
        }
        
        $agent = $result->fetch_assoc();
        $stmt->close();

        // 계약 상태(activityStatus)는 contractEndDate 기준으로 파생 (목록(getAgents)과 동일 규칙)
        // - under_contract: contractEndDate가 없거나 오늘 이후/오늘
        // - contract_ended: contractEndDate가 오늘 이전
        $agent['activityStatus'] = 'under_contract';
        try {
            $end = $agent['contractEndDate'] ?? null;
            if (!empty($end)) {
                $endYmd = substr((string)$end, 0, 10);
                if ($endYmd !== '' && strtotime($endYmd) < strtotime(date('Y-m-d'))) {
                    $agent['activityStatus'] = 'contract_ended';
                }
            }
        } catch (Throwable $e) {
            $agent['activityStatus'] = 'under_contract';
        }
        
        // managerName 필드 추가 (fName과 lName 결합)
        if (isset($agent['fName']) && isset($agent['lName'])) {
            $agent['managerName'] = trim($agent['fName'] . ' ' . $agent['lName']);
        }

        // 소속 고객 수(동적): client.companyId 기반으로 B2B(Wholeseller) 고객 카운트
        $customerCount = 0;
        $agentCompanyId = intval($agent['companyId'] ?? 0);
        if ($agentCompanyId > 0) {
            $ccSql = "SELECT COUNT(DISTINCT c2.accountId) as c
                      FROM client c2
                      JOIN accounts ac2 ON ac2.accountId = c2.accountId
                      WHERE ac2.accountType = 'guest'
                        AND LOWER(COALESCE(c2.clientType,'')) IN ('wholeseller','wholesaler')
                        AND c2.companyId = ?";
            $cc = $conn->prepare($ccSql);
            if ($cc) {
                $cc->bind_param('i', $agentCompanyId);
                $cc->execute();
                $customerCount = intval($cc->get_result()->fetch_assoc()['c'] ?? 0);
                $cc->close();
            }
        }
        $agent['customerCount'] = $customerCount;

        // UI: Area 필드는 branchName이 우선, 없으면 company.businessUnit 사용
        if (empty($agent['branchName']) && !empty($agent['businessUnit'])) {
            $agent['branchName'] = $agent['businessUnit'];
        }
        
        send_success_response(['agent' => $agent]);
    } catch (Exception $e) {
        send_error_response('Failed to get agent detail: ' . $e->getMessage());
    }
}

function createAgent($conn, $input) {
    error_log("=== createAgent function called ===");
    error_log("createAgent - Input data: " . json_encode($input));
    
    try {
        $conn->begin_transaction();
        // agent.memo 컬럼 ensure (상세/수정과 동일하게 동작)
        $memoCol = $conn->query("SHOW COLUMNS FROM agent LIKE 'memo'");
        if ($memoCol && $memoCol->num_rows === 0) {
            $conn->query("ALTER TABLE agent ADD COLUMN memo TEXT NULL");
        }

        // 필수 필드 확인
        $requiredFields = ['emailAddress', 'password', 'fName'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                $missingFields[] = $field;
            }
        }
        if (!empty($missingFields)) {
            send_error_response("Required fields missing: " . implode(', ', $missingFields));
        }
        
        $fName = trim($input['fName'] ?? '');
        $lName = trim($input['lName'] ?? '');
        
        // company 테이블 제거됨 - companyId 사용 안함
        
        // username 생성
        $username = $input['username'] ?? '';
        if (empty($username)) {
            $emailAddress = $input['emailAddress'];
            if (strpos($emailAddress, '@') !== false) {
                $emailParts = explode('@', $emailAddress);
                $username = $emailParts[0];
            } else {
                $username = $emailAddress;
            }
        }
        
        // accounts 테이블에 계정 생성 (password는 hash 우선)
        $rawPw = (string)($input['password'] ?? '');
        $pwToStore = $rawPw;
        if ($rawPw !== '' && !preg_match('/^(\\$2y\\$|\\$2a\\$|\\$argon2id\\$)/', $rawPw)) {
            $pwToStore = password_hash($rawPw, PASSWORD_DEFAULT);
        }
        $accountSql = "INSERT INTO accounts (username, emailAddress, password, accountType, accountStatus) VALUES (?, ?, ?, 'agent', 'active')";
        $accountStmt = $conn->prepare($accountSql);
        if (!$accountStmt) {
            send_error_response('Failed to prepare account statement: ' . $conn->error);
        }
        
        $accountStmt->bind_param('sss', $username, $input['emailAddress'], $pwToStore);
        if (!$accountStmt->execute()) {
            $error = $accountStmt->error ?: $conn->error;
            $accountStmt->close();
            send_error_response('Failed to create account: ' . $error);
        }
        $accountId = $conn->insert_id;
        $accountStmt->close();
        
        if (!$accountId) {
            send_error_response('Failed to get account ID');
        }
        
        // agentId 생성 (AGT001, AGT002, ... 형식)
        $maxNumResult = $conn->query("SELECT MAX(CAST(SUBSTRING(agentId, 4) AS UNSIGNED)) as maxNum FROM agent WHERE agentId LIKE 'AGT%'");
        $maxNum = $maxNumResult->fetch_assoc()['maxNum'] ?? 0;
        $nextNum = $maxNum + 1;
        $newAgentId = 'AGT' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        
        $contactNo = $input['contactNo'] ?? '';
        $agentType = $input['agentType'] ?? 'Retailer';
        $agentRole = $input['agentRole'] ?? 'Sub-Agent';
        $contractStartDate = $input['contractStartDate'] ?? null;
        $contractEndDate = $input['contractEndDate'] ?? null;
        $memo = $input['memo'] ?? null;

        // empty string -> NULL
        if (is_string($contractStartDate) && trim($contractStartDate) === '') $contractStartDate = null;
        if (is_string($contractEndDate) && trim($contractEndDate) === '') $contractEndDate = null;
        if ($memo !== null) $memo = trim((string)$memo);
        
        // enum 정규화(스키마 불일치로 인한 Data truncated 방지)
        // agentType enum('Retailer','Wholeseller')
        $agentType = trim((string)$agentType);
        if (!in_array($agentType, ['Retailer', 'Wholeseller'], true)) {
            // UI에서 Online/Offline 등으로 올 수 있으므로 기본 Retailer로 fallback
            $agentType = 'Retailer';
        }
        // agentRole enum('Head Agent','Sub-Agent')
        $agentRole = trim((string)$agentRole);
        if (!in_array($agentRole, ['Head Agent', 'Sub-Agent'], true)) {
            $agentRole = 'Sub-Agent';
        }
        
        // agent 테이블 INSERT (계약/메모 포함)
        $agentSql = "INSERT INTO agent (
            agentId, accountId, fName, lName, contactNo, agentType, agentRole,
            contractStartDate, contractEndDate, memo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $agentStmt = $conn->prepare($agentSql);
        if (!$agentStmt) {
            send_error_response('Failed to prepare agent statement: ' . $conn->error);
        }

        // contractStart/End는 nullable date(string), memo는 nullable text
        $contractStart = ($contractStartDate === null || $contractStartDate === '') ? null : (string)$contractStartDate;
        $contractEnd = ($contractEndDate === null || $contractEndDate === '') ? null : (string)$contractEndDate;
        $memoText = ($memo === null || $memo === '') ? null : (string)$memo;

        $agentStmt->bind_param(
            'sissssssss',
            $newAgentId,
            $accountId,
            $fName,
            $lName,
            $contactNo,
            $agentType,
            $agentRole,
            $contractStart,
            $contractEnd,
            $memoText
        );
        if (!$agentStmt->execute()) {
            $error = $agentStmt->error ?: $conn->error;
            $agentStmt->close();
            send_error_response('Failed to create agent: ' . $error);
        }
        $agentStmt->close();
        
        error_log("createAgent - Success! Agent ID: $newAgentId, Account ID: $accountId");
        $conn->commit();
        send_success_response(['agentId' => $newAgentId, 'accountId' => $accountId], 'Agent created successfully');
    } catch (Exception $e) {
        if ($conn && $conn->errno === 0) {
            // best-effort rollback
            try { $conn->rollback(); } catch (Throwable $t) {}
        }
        error_log("createAgent Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("createAgent Stack trace: " . $e->getTraceAsString());
        send_error_response('Failed to create agent: ' . $e->getMessage());
    } catch (Error $e) {
        if ($conn) { try { $conn->rollback(); } catch (Throwable $t) {} }
        error_log("createAgent Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("createAgent Stack trace: " . $e->getTraceAsString());
        send_error_response('A fatal error occurred while creating agent: ' . $e->getMessage());
    } catch (Throwable $e) {
        if ($conn) { try { $conn->rollback(); } catch (Throwable $t) {} }
        error_log("createAgent Throwable: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("createAgent Stack trace: " . $e->getTraceAsString());
        send_error_response('An unexpected error occurred while creating agent: ' . $e->getMessage());
    }
    error_log("=== createAgent function ended ===");
}

function updateAgent($conn, $input) {
    try {
        $agentId = $input['agentId'] ?? $input['id'] ?? null;
        if (empty($agentId)) {
            send_error_response('Agent ID is required');
        }

        // agent.memo 컬럼 ensure
        $memoCol = $conn->query("SHOW COLUMNS FROM agent LIKE 'memo'");
        if ($memoCol && $memoCol->num_rows === 0) {
            $conn->query("ALTER TABLE agent ADD COLUMN memo TEXT NULL");
        }

        // company 테이블 제거됨 - companyId 관련 로직 삭제

        // agent 정보 업데이트
        $updates = [];
        $values = [];
        $types = '';

        // NOTE:
        // - contractStartDate/contractEndDate : date (nullable)
        // companyName -> agencyName 매핑 (프론트에서 companyName으로 전송)
        if (isset($input['companyName']) && !isset($input['agencyName'])) {
            $input['agencyName'] = $input['companyName'];
        }

        $updatableFields = [
            'fName',
            'lName',
            'contactNo',
            'agencyName',
            'agentType',
            'agentRole',
            'contractStartDate',
            'contractEndDate',
            'memo'
        ];

        foreach ($updatableFields as $field) {
            if (isset($input[$field])) {
                // empty string -> NULL (nullable fields)
                if (in_array($field, ['contractStartDate', 'contractEndDate'], true)) {
                    if (is_string($input[$field]) && trim($input[$field]) === '') {
                        $input[$field] = null;
                    }
                }
                $updates[] = "$field = ?";
                $values[] = $input[$field];
                $types .= 's';
            }
        }
        
        if (!empty($updates)) {
            $values[] = $agentId;
            $types .= 's';
            $agentSql = "UPDATE agent SET " . implode(', ', $updates) . " WHERE agentId = ?";
            $agentStmt = $conn->prepare($agentSql);
            mysqli_bind_params_by_ref($agentStmt, $types, $values);
            $agentStmt->execute();
            $agentStmt->close();
        }
        
        // accounts 정보 업데이트
        if (isset($input['emailAddress']) || isset($input['accountStatus']) || isset($input['username']) || isset($input['password'])) {
            $accountUpdates = [];
            $accountValues = [];
            $accountTypes = '';
            
            if (isset($input['username'])) {
                $u = trim((string)$input['username']);
                if ($u !== '') {
                    $accountUpdates[] = "username = ?";
                    $accountValues[] = $u;
                    $accountTypes .= 's';
                }
            }
            if (isset($input['emailAddress'])) {
                $accountUpdates[] = "emailAddress = ?";
                $accountValues[] = $input['emailAddress'];
                $accountTypes .= 's';
            }
            if (isset($input['accountStatus'])) {
                $accountUpdates[] = "accountStatus = ?";
                $accountValues[] = $input['accountStatus'];
                $accountTypes .= 's';
            }

            if (isset($input['password'])) {
                $rawPw = trim((string)$input['password']);
                if ($rawPw !== '' && !preg_match('/^(\\$2y\\$|\\$2a\\$|\\$argon2id\\$)/', $rawPw)) {
                    $hashed = password_hash($rawPw, PASSWORD_DEFAULT);
                    $accountUpdates[] = "password = ?";
                    $accountValues[] = $hashed;
                    $accountTypes .= 's';
                    // 비밀번호 변경 시 기본비밀번호 상태 해제
                    $accountUpdates[] = "defaultPasswordStat = 'no'";
                } elseif ($rawPw !== '') {
                    $accountUpdates[] = "password = ?";
                    $accountValues[] = $rawPw;
                    $accountTypes .= 's';
                    $accountUpdates[] = "defaultPasswordStat = 'no'";
                }
            }
            
            // accountId 가져오기
            $getAccountIdSql = "SELECT accountId FROM agent WHERE agentId = ?";
            $getAccountIdStmt = $conn->prepare($getAccountIdSql);
            // agentId는 'AGT001' 같은 문자열이므로 string으로 바인딩해야 함
            $getAccountIdStmt->bind_param('s', $agentId);
            $getAccountIdStmt->execute();
            $accountIdResult = $getAccountIdStmt->get_result();
            $row = $accountIdResult ? $accountIdResult->fetch_assoc() : null;
            $accountId = $row['accountId'] ?? null;
            $getAccountIdStmt->close();

            if (empty($accountId)) {
                send_error_response('Failed to resolve accountId for agentId: ' . $agentId);
            }
            
            $accountValues[] = $accountId;
            $accountTypes .= 'i';
            // company 테이블 제거됨 - region/businessUnit 업데이트 로직 삭제

            if (empty($accountUpdates)) {
                send_success_response([], 'Agent updated successfully');
            }

            $accountSql = "UPDATE accounts SET " . implode(', ', $accountUpdates) . ", updatedAt = NOW() WHERE accountId = ?";
            $accountStmt = $conn->prepare($accountSql);
            mysqli_bind_params_by_ref($accountStmt, $accountTypes, $accountValues);
            $accountStmt->execute();
            $accountStmt->close();
        }
        
        send_success_response([], 'Agent updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update agent: ' . $e->getMessage());
    }
}

// ========== Guide 관리 함수들 ==========

function getGuides($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($input['search'])) {
            // 요구사항: 가이드명 기준 검색
            $whereConditions[] = "(g.guideName LIKE ?)";
            $searchTerm = '%' . $input['search'] . '%';
            $params[] = $searchTerm;
            $types .= 's';
        }
        
        // 계약 상태 필터 (overall/under_contract/contract_ended)
        $contractStatus = $input['contractStatus'] ?? ($input['status'] ?? '');
        if (!empty($contractStatus)) {
            if ($contractStatus === 'under_contract') {
                // 일부 환경: 빈 문자열('')로 저장된 케이스를 NULL로 취급
                $whereConditions[] = "(NULLIF(g.contractEndDate,'') IS NULL OR NULLIF(g.contractEndDate,'') >= CURDATE())";
            } elseif ($contractStatus === 'contract_ended') {
                $whereConditions[] = "(NULLIF(g.contractEndDate,'') IS NOT NULL AND NULLIF(g.contractEndDate,'') < CURDATE())";
            } else {
                // fallback: 기존 status 컬럼(활성/비활성)
                $whereConditions[] = "g.status = ?";
                $params[] = $contractStatus;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM guides g 
                     LEFT JOIN accounts ac ON g.accountId = ac.accountId 
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $dataSql = "SELECT 
            g.guideId,
            g.accountId,
            g.guideCode,
            g.guideName,
            ac.emailAddress,
            g.phoneNumber,
            g.contractStartDate,
            g.contractEndDate,
            g.memo,
            g.status,
            ac.createdAt
        FROM guides g
        LEFT JOIN accounts ac ON g.accountId = ac.accountId
        $whereClause
        ORDER BY ac.createdAt DESC
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $guides = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $end = $row['contractEndDate'] ?? null;
            $activityStatus = 'under_contract';
            if (!empty($end) && strtotime(substr((string)$end, 0, 10)) < strtotime(date('Y-m-d'))) {
                $activityStatus = 'contract_ended';
            }
            $guides[] = [
                'guideId' => $row['guideId'],
                'accountId' => $row['accountId'],
                'guideCode' => $row['guideCode'] ?? '',
                'guideName' => $row['guideName'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'phoneNumber' => $row['phoneNumber'] ?? '',
                'contractStartDate' => $row['contractStartDate'] ?? null,
                'contractEndDate' => $row['contractEndDate'] ?? null,
                'activityStatus' => $activityStatus,
                'status' => $row['status'] ?? 'active',
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'guides' => $guides,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get guides: ' . $e->getMessage());
    }
}

function exportGuidesCsv($conn, $input) {
    try {
        $search = trim($input['search'] ?? '');
        $contractStatus = trim($input['contractStatus'] ?? ($input['status'] ?? ''));

        $whereConditions = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $whereConditions[] = "(g.guideName LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $types .= 's';
        }

        if ($contractStatus === 'under_contract') {
            $whereConditions[] = "(g.contractEndDate IS NULL OR g.contractEndDate >= CURDATE())";
        } else if ($contractStatus === 'contract_ended') {
            $whereConditions[] = "(g.contractEndDate IS NOT NULL AND g.contractEndDate < CURDATE())";
        }

        $whereClause = !empty($whereConditions) ? ('WHERE ' . implode(' AND ', $whereConditions)) : '';

        $sql = "SELECT 
                    g.guideCode,
                    g.guideName,
                    ac.emailAddress,
                    g.phoneNumber,
                    g.contractStartDate,
                    g.contractEndDate
                FROM guides g
                LEFT JOIN accounts ac ON g.accountId = ac.accountId
                $whereClause
                ORDER BY ac.createdAt DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="guides_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Guide Name', 'Email', 'Contact', 'Contract Period', 'Activity Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            $start = $row['contractStartDate'] ? substr((string)$row['contractStartDate'], 0, 10) : '';
            $end = $row['contractEndDate'] ? substr((string)$row['contractEndDate'], 0, 10) : '';
            $period = ($start && $end) ? ($start . ' ~ ' . $end) : '';
            $activity = 'Under Contract';
            if ($end && strtotime($end) < strtotime(date('Y-m-d'))) $activity = 'Contract Ended';

            fputcsv($out, [
                $no++,
                $row['guideName'] ?? '',
                $row['emailAddress'] ?? '',
                $row['phoneNumber'] ?? '',
                $period,
                $activity
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export guides CSV: ' . $e->getMessage(), 500);
    }
}

function getGuideDetail($conn, $input) {
    try {
        $guideId = $input['guideId'] ?? $input['id'] ?? null;
        if (empty($guideId)) {
            send_error_response('Guide ID is required');
        }

        $sql = "SELECT
            g.*,
            ac.username,
            ac.emailAddress,
            ac.accountStatus,
            ac.createdAt
        FROM guides g
        LEFT JOIN accounts ac ON g.accountId = ac.accountId";

        $guide = null;

        // guideCode (문자열) 또는 guideId (숫자)로 검색
        if (is_numeric($guideId)) {
            // 먼저 guideId로 검색
            $stmt = $conn->prepare($sql . " WHERE g.guideId = ?");
            $stmt->bind_param('i', $guideId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $guide = $result->fetch_assoc();
            }
            $stmt->close();

            // guideId로 못 찾으면 accountId로 검색
            if (!$guide) {
                $stmt = $conn->prepare($sql . " WHERE g.accountId = ?");
                $stmt->bind_param('i', $guideId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $guide = $result->fetch_assoc();
                }
                $stmt->close();
            }
        } else {
            // guideCode로 검색
            $stmt = $conn->prepare($sql . " WHERE g.guideCode = ?");
            $stmt->bind_param('s', $guideId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $guide = $result->fetch_assoc();
            }
            $stmt->close();
        }

        if (!$guide) {
            send_error_response('Guide not found', 404);
        }

        send_success_response(['guide' => $guide]);
    } catch (Exception $e) {
        send_error_response('Failed to get guide detail: ' . $e->getMessage());
    }
}

function ensureGuidesProfileImageMetaColumns($conn) {
    // guides.profileImageOriginalName / profileImageSize / profileImageMime
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM guides");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[strtolower($r['Field'])] = true;
    }
    $adds = [];
    if (!isset($cols['profileimageoriginalname'])) $adds[] = "ADD COLUMN profileImageOriginalName VARCHAR(255) NULL";
    if (!isset($cols['profileimagesize'])) $adds[] = "ADD COLUMN profileImageSize INT NULL";
    if (!isset($cols['profileimagemime'])) $adds[] = "ADD COLUMN profileImageMime VARCHAR(100) NULL";
    if (!$adds) return;
    $sql = "ALTER TABLE guides " . implode(', ', $adds);
    if (!$conn->query($sql)) {
        throw new Exception('Failed to ensure profile image meta columns: ' . $conn->error);
    }
}

function deleteGuideProfileImage($conn, $input) {
    try {
        $guideId = $input['guideId'] ?? $input['id'] ?? null;
        if (empty($guideId)) {
            send_error_response('Guide ID is required');
        }

        // 메타 컬럼 보장 (없으면 생성)
        ensureGuidesProfileImageMetaColumns($conn);

        $isNum = is_numeric($guideId);
        $idType = $isNum ? 'i' : 's';
        $idVal = $isNum ? intval($guideId) : (string)$guideId;

        // 기존 이미지 경로 조회
        $selSql = $isNum
            ? "SELECT profileImage FROM guides WHERE guideId = ?"
            : "SELECT profileImage FROM guides WHERE guideCode = ?";
        $sel = $conn->prepare($selSql);
        if (!$sel) send_error_response('Failed to prepare guide lookup: ' . $conn->error, 500);
        $sel->bind_param($idType, $idVal);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $sel->close();

        $path = trim((string)($row['profileImage'] ?? ''));

        // DB NULL 처리
        $where = $isNum ? "guideId = ?" : "guideCode = ?";
        $updSql = "UPDATE guides
                   SET profileImage = NULL,
                       profileImageOriginalName = NULL,
                       profileImageSize = NULL,
                       profileImageMime = NULL
                   WHERE $where";
        $upd = $conn->prepare($updSql);
        if (!$upd) send_error_response('Failed to prepare update: ' . $conn->error, 500);
        $upd->bind_param($idType, $idVal);
        if (!$upd->execute()) {
            $err = $upd->error ?: $conn->error;
            $upd->close();
            send_error_response('Failed to delete profile image: ' . $err, 500);
        }
        $upd->close();

        // 실제 파일 삭제 (uploads/guides/ 만 허용)
        if ($path !== '') {
            $normalized = ltrim($path, '/');
            if (strpos($normalized, 'uploads/guides/') === 0) {
                $abs = __DIR__ . '/../../../' . $normalized;
                if (file_exists($abs)) {
                    @unlink($abs);
                }
            }
        }

        send_success_response([], 'Guide profile image deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete guide profile image: ' . $e->getMessage(), 500);
    }
}

function createGuide($conn, $input) {
    try {
        $requiredFields = ['emailAddress', 'password', 'guideName'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }
        
        // username 생성
        $username = $input['username'] ?? '';
        if (empty($username)) {
            $emailAddress = $input['emailAddress'];
            if (strpos($emailAddress, '@') !== false) {
                $emailParts = explode('@', $emailAddress);
                $username = $emailParts[0];
            } else {
                $username = $emailAddress;
            }
        }
        
        // accounts 테이블에 계정 생성
        $accountSql = "INSERT INTO accounts (username, emailAddress, password, accountType, accountStatus) VALUES (?, ?, ?, 'guide', 'active')";
        $accountStmt = $conn->prepare($accountSql);
        $accountStmt->bind_param('sss', $username, $input['emailAddress'], $input['password']);
        $accountStmt->execute();
        $accountId = $conn->insert_id;
        $accountStmt->close();
        
        // guideCode 생성 (GD001, GD002, ... 형식)
        $maxNumResult = $conn->query("SELECT MAX(CAST(SUBSTRING(guideCode, 3) AS UNSIGNED)) as maxNum FROM guides WHERE guideCode LIKE 'GD%'");
        $maxNum = $maxNumResult->fetch_assoc()['maxNum'] ?? 0;
        $nextNum = $maxNum + 1;
        $newGuideCode = 'GD' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        
        // 증명사진 업로드 처리
        $profileImagePath = null;
        $profileOrig = null;
        $profileSize = null;
        $profileMime = null;
        if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
            ensureGuidesProfileImageMetaColumns($conn);
            $uploadDir = __DIR__ . '/../../../uploads/guides/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
            $fileName = 'guide_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadPath)) {
                $profileImagePath = 'uploads/guides/' . $fileName;
                $profileOrig = $_FILES['profileImage']['name'] ?? null;
                $profileSize = $_FILES['profileImage']['size'] ?? null;
                $profileMime = $_FILES['profileImage']['type'] ?? null;
            }
        }
        
        // guides 테이블에 생성
        $guideFields = ['accountId', 'guideCode', 'guideName', 'phoneNumber', 'email', 'status', 'introduction', 'memo', 'contractStartDate', 'contractEndDate'];
        $contractStartDate = isset($input['contractStartDate']) ? trim((string)$input['contractStartDate']) : '';
        $contractEndDate = isset($input['contractEndDate']) ? trim((string)$input['contractEndDate']) : '';
        $guideValues = [
            $accountId,
            $newGuideCode,
            $input['guideName'],
            $input['phoneNumber'] ?? '',
            $input['emailAddress'],
            $input['status'] ?? 'active',
            $input['introduction'] ?? '',
            $input['memo'] ?? '',
            ($contractStartDate !== '' ? $contractStartDate : null),
            ($contractEndDate !== '' ? $contractEndDate : null),
        ];
        $guideTypes = 'isssssssss';
        
        if ($profileImagePath) {
            $guideFields[] = 'profileImage';
            $guideValues[] = $profileImagePath;
            $guideTypes .= 's';

            // 파일 메타
            $guideFields[] = 'profileImageOriginalName';
            $guideValues[] = $profileOrig;
            $guideTypes .= 's';
            $guideFields[] = 'profileImageSize';
            $guideValues[] = $profileSize;
            $guideTypes .= 's';
            $guideFields[] = 'profileImageMime';
            $guideValues[] = $profileMime;
            $guideTypes .= 's';
        }
        
        $guidePlaceholders = str_repeat('?,', count($guideFields) - 1) . '?';
        $guideSql = "INSERT INTO guides (" . implode(', ', $guideFields) . ") VALUES ($guidePlaceholders)";
        $guideStmt = $conn->prepare($guideSql);
        $guideStmt->bind_param($guideTypes, ...$guideValues);
        $guideStmt->execute();
        $guideId = $conn->insert_id;
        $guideStmt->close();
        
        send_success_response(['guideId' => $guideId, 'guideCode' => $newGuideCode, 'accountId' => $accountId], 'Guide created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create guide: ' . $e->getMessage());
    }
}

function updateGuide($conn, $input) {
    try {
        $guideId = $input['guideId'] ?? $input['id'] ?? null;
        if (empty($guideId)) {
            send_error_response('Guide ID is required');
        }
        
        // guide 정보 업데이트
        $updates = [];
        $values = [];
        $types = '';
        
        // guides 테이블 실제 컬럼만 업데이트 (email/status 컬럼 없음)
        // guides 테이블 실제 컬럼만 업데이트
        $updatableFields = ['guideName', 'phoneNumber', 'introduction', 'contractStartDate', 'contractEndDate', 'memo'];
        foreach ($updatableFields as $field) {
            if (!array_key_exists($field, $input)) continue;
            $val = $input[$field];

            // 날짜 컬럼은 NULLIF로 처리 (빈 문자열 -> NULL) 해서 환경별 DATE/VARCHAR 모두 안전하게 저장
            if ($field === 'contractStartDate' || $field === 'contractEndDate') {
                $updates[] = "$field = NULLIF(?, '')";
                $values[] = trim((string)$val);
                $types .= 's';
                continue;
            }

            $updates[] = "$field = ?";
            $values[] = $val;
            $types .= 's';
        }
        
        // 증명사진 업로드 처리
        if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
            ensureGuidesProfileImageMetaColumns($conn);
            $uploadDir = __DIR__ . '/../../../uploads/guides/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 기존 이미지 삭제
            $oldImageSql = is_numeric($guideId) 
                ? "SELECT profileImage FROM guides WHERE guideId = ?" 
                : "SELECT profileImage FROM guides WHERE guideCode = ?";
            $oldImageStmt = $conn->prepare($oldImageSql);
            $oldImageStmt->bind_param(is_numeric($guideId) ? 'i' : 's', $guideId);
            $oldImageStmt->execute();
            $oldImageResult = $oldImageStmt->get_result();
            $oldImageRow = $oldImageResult ? $oldImageResult->fetch_assoc() : null;
            if ($oldImageRow && !empty($oldImageRow['profileImage'])) {
                $oldImagePath = __DIR__ . '/../../../' . $oldImageRow['profileImage'];
                if (file_exists($oldImagePath)) @unlink($oldImagePath);
            }
            $oldImageStmt->close();
            
            // 새 이미지 업로드
            $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
            $fileName = 'guide_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadPath)) {
                $profileImagePath = 'uploads/guides/' . $fileName;
                $updates[] = "profileImage = ?";
                $values[] = $profileImagePath;
                $types .= 's';

                $updates[] = "profileImageOriginalName = ?";
                $values[] = ($_FILES['profileImage']['name'] ?? null);
                $types .= 's';
                $updates[] = "profileImageSize = ?";
                $values[] = (string)($_FILES['profileImage']['size'] ?? null);
                $types .= 's';
                $updates[] = "profileImageMime = ?";
                $values[] = ($_FILES['profileImage']['type'] ?? null);
                $types .= 's';
            }
        }
        
        if (!empty($updates)) {
            $values[] = $guideId;
            
            // guideCode (문자열) 또는 guideId (숫자)로 검색
            if (is_numeric($guideId)) {
                $types .= 'i';
                $whereClause = "guideId = ?";
            } else {
                $types .= 's';
                $whereClause = "guideCode = ?";
            }
            
            $guideSql = "UPDATE guides SET " . implode(', ', $updates) . " WHERE $whereClause";
            $guideStmt = $conn->prepare($guideSql);
            mysqli_bind_params_by_ref($guideStmt, $types, $values);
            $guideStmt->execute();
            $guideStmt->close();
        }
        
        // accounts 정보 업데이트
        if (isset($input['emailAddress']) || isset($input['accountStatus']) || isset($input['username'])) {
            $accountUpdates = [];
            $accountValues = [];
            $accountTypes = '';
            
            if (isset($input['username'])) {
                $accountUpdates[] = "username = ?";
                $accountValues[] = $input['username'];
                $accountTypes .= 's';
            }
            if (isset($input['emailAddress'])) {
                $accountUpdates[] = "emailAddress = ?";
                $accountValues[] = $input['emailAddress'];
                $accountTypes .= 's';
            }
            if (isset($input['accountStatus'])) {
                $accountUpdates[] = "accountStatus = ?";
                $accountValues[] = $input['accountStatus'];
                $accountTypes .= 's';
            }
            
            // accountId 가져오기 (guideId 숫자 또는 guideCode 문자열 모두 지원)
            if (is_numeric($guideId)) {
                $getAccountIdSql = "SELECT accountId FROM guides WHERE guideId = ?";
                $getAccountIdType = 'i';
                $getAccountIdVal = intval($guideId);
            } else {
                $getAccountIdSql = "SELECT accountId FROM guides WHERE guideCode = ?";
                $getAccountIdType = 's';
                $getAccountIdVal = (string)$guideId;
            }
            $getAccountIdStmt = $conn->prepare($getAccountIdSql);
            $getAccountIdStmt->bind_param($getAccountIdType, $getAccountIdVal);
            $getAccountIdStmt->execute();
            $accountIdResult = $getAccountIdStmt->get_result();
            $accountRow = $accountIdResult ? $accountIdResult->fetch_assoc() : null;
            $accountId = $accountRow['accountId'] ?? null;
            $getAccountIdStmt->close();

            if (empty($accountId)) {
                send_error_response('Account not found for guide', 404);
            }
            
            $accountValues[] = $accountId;
            $accountTypes .= 'i';
            $accountSql = "UPDATE accounts SET " . implode(', ', $accountUpdates) . " WHERE accountId = ?";
            $accountStmt = $conn->prepare($accountSql);
            $accountStmt->bind_param($accountTypes, ...$accountValues);
            if (!$accountStmt->execute()) {
                $err = $accountStmt->error ?: $conn->error;
                $accountStmt->close();
                send_error_response('Failed to update guide account: ' . $err, 500);
            }
            $accountStmt->close();
        }
        
        send_success_response([], 'Guide updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update guide: ' . $e->getMessage());
    }
}

// ========== 예약 관리 함수들 (B2B/B2C) ==========

function getB2BBookings($conn, $input) {
    try {
        // 조회 시점 자동취소(상세 진입 없이도 목록/대시보드 숫자 맞추기)
        // 통일: downPaymentDueDate/downPaymentFile, balanceDueDate/balanceFile 사용
        try {
            $hasDownPaymentDueDate = __table_has_column($conn, 'bookings', 'downPaymentDueDate');
            $hasDownPaymentFile = __table_has_column($conn, 'bookings', 'downPaymentFile');
            $hasBalanceDueDate = __table_has_column($conn, 'bookings', 'balanceDueDate');
            $hasBalanceFile = __table_has_column($conn, 'bookings', 'balanceFile');

            if (($hasDownPaymentDueDate && $hasDownPaymentFile) || ($hasBalanceDueDate && $hasBalanceFile)) {
                $dateExpr = function ($col) {
                    return "(CASE
                        WHEN CAST($col AS CHAR) REGEXP '^[0-9]{8}$' THEN STR_TO_DATE(CAST($col AS CHAR), '%Y%m%d')
                        ELSE DATE($col)
                    END)";
                };

                // B2B 예약 = agent 계정 또는 price_tier='B2B'
                $join = "LEFT JOIN accounts a ON b.accountId = a.accountId";
                $b2bCond = "AND (a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)";

                $conds = [];
                if ($hasDownPaymentDueDate && $hasDownPaymentFile) {
                    $conds[] = "(" . $dateExpr('b.downPaymentDueDate') . " IS NOT NULL AND " . $dateExpr('b.downPaymentDueDate') . " < CURDATE() AND COALESCE(b.downPaymentFile,'') = '')";
                }
                if ($hasBalanceDueDate && $hasBalanceFile) {
                    $conds[] = "(" . $dateExpr('b.balanceDueDate') . " IS NOT NULL AND " . $dateExpr('b.balanceDueDate') . " < CURDATE() AND COALESCE(b.balanceFile,'') = '')";
                }

                if (!empty($conds)) {
                    $sql = "UPDATE bookings b
                            $join
                            SET b.bookingStatus='cancelled', b.paymentStatus='failed'
                            WHERE COALESCE(b.paymentStatus,'') = 'pending'
                              AND COALESCE(b.bookingStatus,'') NOT IN ('cancelled','confirmed','completed','pending_update','check_reject')
                              $b2bCond
                              AND (" . implode(' OR ', $conds) . ")";
                    $conn->query($sql);
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(1000, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // 선금/잔금 구분용(환경별 컬럼 유무 대응) - 통일: downPaymentFile 사용
        $hasDownPaymentFile = false;
        $dp = $conn->query("SHOW COLUMNS FROM bookings LIKE 'downPaymentFile'");
        if ($dp && $dp->num_rows > 0) $hasDownPaymentFile = true;

        // B2B 예약 목록 기준(변경됨):
        // - agent 계정이 예약한 건 OR price_tier='B2B'인 예약
        // - 상품의 sales_target과 무관하게 예약자/가격 티어 기준으로 분류
        $whereConditions = [];

        // B2B 예약 = agent 계정 또는 price_tier='B2B'
        $whereConditions[] = "(a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)";
        // 빈 bookingStatus 레코드 제외
        $whereConditions[] = "COALESCE(b.bookingStatus, '') != ''";
        $params = [];
        $types = '';

        // selectedOptions(customerInfo) 컬럼 유무(환경별 대응)
        $hasSelectedOptions = false;
        try {
            $soCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'selectedOptions'");
            if ($soCol && $soCol->num_rows > 0) $hasSelectedOptions = true;
        } catch (Throwable $e) { $hasSelectedOptions = false; }

        // contactName 컬럼 유무(환경별 대응) - 예약자명 보강에 사용
        $hasContactName = false;
        try {
            $cnCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactName'");
            if ($cnCol && $cnCol->num_rows > 0) $hasContactName = true;
        } catch (Throwable $e) { $hasContactName = false; }

        // booking_travelers(메인 여행자)로 예약자명 보강(선택옵션/연락처가 비어있는 레거시 케이스)
        $canUseBookingTravelers = false;
        try {
            $canUseBookingTravelers = __table_exists($conn, 'booking_travelers')
                && __table_has_column($conn, 'booking_travelers', 'transactNo')
                && __table_has_column($conn, 'booking_travelers', 'firstName')
                && __table_has_column($conn, 'booking_travelers', 'lastName');
        } catch (Throwable $e) { $canUseBookingTravelers = false; }
        $travNameStmt = null;
        if ($canUseBookingTravelers) {
            // isMainTraveler 컬럼이 없을 수도 있어 ORDER BY는 가드
            $hasIsMain = false;
            try { $hasIsMain = __table_has_column($conn, 'booking_travelers', 'isMainTraveler'); } catch (Throwable $e) { $hasIsMain = false; }
            $order = $hasIsMain ? "ORDER BY COALESCE(isMainTraveler,0) DESC, bookingTravelerId ASC" : "ORDER BY bookingTravelerId ASC";
            $travNameStmt = $conn->prepare("
                SELECT TRIM(firstName) as firstName, TRIM(lastName) as lastName
                FROM booking_travelers
                WHERE transactNo = ?
                {$order}
                LIMIT 1
            ");
        }

        // 예약자(accountId)와 실제 예약자(고객)가 다른 환경(에이전트가 고객 대신 예약 생성) 대응:
        // - bookings.customerAccountId(환경별 컬럼) 기준으로 고객(client)을 조인해서 reserverName을 우선 잡는다. (요구사항 id 83)
        $bookingColumns = [];
        try {
            $cr = $conn->query("SHOW COLUMNS FROM bookings");
            while ($cr && ($c = $cr->fetch_assoc())) $bookingColumns[] = strtolower((string)$c['Field']);
        } catch (Throwable $e) { $bookingColumns = []; }
        $customerAccountIdCol = null;
        if (in_array('customeraccountid', $bookingColumns, true)) $customerAccountIdCol = 'customerAccountId';
        else if (in_array('customer_account_id', $bookingColumns, true)) $customerAccountIdCol = 'customer_account_id';
        else if (in_array('customerid', $bookingColumns, true)) $customerAccountIdCol = 'customerId';
        else if (in_array('userid', $bookingColumns, true)) $customerAccountIdCol = 'userId';

        $customerJoinSql = '';
        $reserverExprSql = '';
        if (!empty($customerAccountIdCol)) {
            $customerJoinSql = "LEFT JOIN client cu ON b.`{$customerAccountIdCol}` = cu.accountId";
            $reserverExprSql = "TRIM(COALESCE(
                NULLIF(CONCAT(cu.fName, ' ', cu.lName), ' '),
                NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                NULLIF(a.username, ''),
                NULLIF(a.emailAddress, ''),
                ''
            )) as reserverName,";
        } else {
            $reserverExprSql = "TRIM(COALESCE(
                NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                NULLIF(a.username, ''),
                NULLIF(a.emailAddress, ''),
                ''
            )) as reserverName,";
        }

        // 검색 기준: all / product / agent / reserver
        if (!empty($input['search'])) {
            $searchType = $input['searchType'] ?? 'all';
            $searchTerm = '%' . $input['search'] . '%';
            // customerInfo.name 검색을 위해 selectedOptions 문자열에도 term을 포함 (JSON 함수 비활성/미지원 환경 대비)
            $customerInfoTerm = '%"customerInfo"%' . $input['search'] . '%';
            if ($searchType === 'product') {
                // Include bookingId in search so reservation number can be found.
                $whereConditions[] = "(
                    COALESCE(NULLIF(b.packageName, ''), p.packageName, '') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'sss';
            } else if ($searchType === 'agent') {
                $whereConditions[] = "(
                    b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
            } else if ($searchType === 'reserver') {
                $whereConditions[] = "(
                    TRIM(COALESCE(
                        NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                        NULLIF(a.username, ''),
                        NULLIF(a.emailAddress, ''),
                        ''
                    )) LIKE ?
                    " . ($hasSelectedOptions ? " OR COALESCE(b.selectedOptions,'') LIKE ?" : "") . "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                if ($hasSelectedOptions) { $params[] = $customerInfoTerm; $types .= 's'; }
                $params[] = $searchTerm; // contactEmail
                $params[] = $searchTerm; // contactPhone
                $params[] = $searchTerm; // bookingId
                $params[] = $searchTerm; // transactNo/bookingId
                $types .= 'sssss';
            } else {
                $whereConditions[] = "(
                    TRIM(COALESCE(
                        NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                        NULLIF(a.username, ''),
                        NULLIF(a.emailAddress, ''),
                        ''
                    )) LIKE ?
                    " . ($hasSelectedOptions ? " OR COALESCE(b.selectedOptions,'') LIKE ?" : "") . "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR COALESCE(NULLIF(b.packageName, ''), p.packageName, '') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                if ($hasSelectedOptions) { $params[] = $customerInfoTerm; $types .= 's'; }
                $params[] = $searchTerm; // contactEmail
                $params[] = $searchTerm; // contactPhone
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ssssss';
            }
        }

        // Travel start date 필터 (날짜 범위 지원)
        if (!empty($input['travelStartDate'])) {
            $dateRange = explode(',', (string)$input['travelStartDate']);
            if (count($dateRange) === 2) {
                // 날짜 범위 (시작일, 종료일)
                $whereConditions[] = "DATE(b.departureDate) >= ? AND DATE(b.departureDate) <= ?";
                $params[] = trim($dateRange[0]);
                $params[] = trim($dateRange[1]);
                $types .= 'ss';
            } else {
                // 단일 날짜
                $whereConditions[] = "DATE(b.departureDate) = ?";
                $params[] = (string)$input['travelStartDate'];
                $types .= 's';
            }
        }

        // SMT 수정 - 새로운 11단계 상태값 필터
        if (!empty($input['status'])) {
            $status = strtolower(trim((string)$input['status']));
            // 유효한 상태값 목록
            $validStatuses = [
                'waiting_down_payment', 'checking_down_payment',
                'waiting_second_payment', 'checking_second_payment',
                'waiting_balance', 'checking_balance',
                'rejected', 'confirmed', 'completed', 'cancelled', 'refunded',
                'pending', 'pending_update', 'check_reject'
            ];

            if (in_array($status, $validStatuses, true)) {
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) = ?";
                $params[] = $status;
                $types .= 's';
            } else if ($status === 'pending') {
                // 하위 호환성: pending은 모든 waiting/checking 상태를 포함
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) IN ('waiting_down_payment','checking_down_payment','waiting_second_payment','checking_second_payment','waiting_balance','checking_balance','pending')";
            } else if ($status === 'partial') {
                // 하위 호환성: partial은 second/balance waiting 상태
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) IN ('waiting_second_payment','checking_second_payment','waiting_balance','checking_balance')";
            }
        }
        // SMT 수정 완료
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $countSql = "SELECT COUNT(*) as total FROM bookings b
                     LEFT JOIN accounts a ON b.accountId = a.accountId
                     LEFT JOIN client c ON b.accountId = c.accountId
                     
                     -- ag: agentId로 조인 (에이전트가 고객을 위해 예약한 경우)
                     LEFT JOIN agent ag ON ag.id = b.agentId
                     
                     -- ag2: accountId로 조인 (에이전트가 자기 계정으로 직접 예약한 경우)
                     LEFT JOIN agent ag2 ON ag2.accountId = b.accountId AND b.agentId IS NULL
                     
                     LEFT JOIN guides g ON b.guideId = g.guideId
                     LEFT JOIN packages p ON b.packageId = p.packageId
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        // 최신순(=등록/생성 최신) 기준: bookings.createdAt DESC
        // createdAt 컬럼이 없는 환경은 departureDate/bookingId로 fallback
        $hasCreatedAt = false;
        $cRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'createdAt'");
        if ($cRes && $cRes->num_rows > 0) $hasCreatedAt = true;
        $orderBy = $hasCreatedAt ? "b.createdAt DESC" : "b.departureDate DESC";

        // Travel start date 정렬 우선 적용
        if (!empty($input['travelStartDateSort'])) {
            if ($input['travelStartDateSort'] === 'asc') {
                $orderBy = "b.departureDate ASC, b.createdAt DESC";
            } elseif ($input['travelStartDateSort'] === 'desc') {
                $orderBy = "b.departureDate DESC, b.createdAt DESC";
            }
        }

        // Date Booked (createdAt) 정렬
        if (!empty($input['dateBookedSort'])) {
            if ($input['dateBookedSort'] === 'asc') {
                $orderBy = "b.createdAt ASC";
            } elseif ($input['dateBookedSort'] === 'desc') {
                $orderBy = "b.createdAt DESC";
            }
        }

        // Last Modified (updatedAt) 정렬
        $hasUpdatedAt = false;
        $uRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'updatedAt'");
        if ($uRes && $uRes->num_rows > 0) $hasUpdatedAt = true;

        if (!empty($input['updatedAtSort']) && $hasUpdatedAt) {
            if ($input['updatedAtSort'] === 'asc') {
                $orderBy = "COALESCE(b.updatedAt, b.createdAt) ASC";
            } elseif ($input['updatedAtSort'] === 'desc') {
                $orderBy = "COALESCE(b.updatedAt, b.createdAt) DESC";
            }
        }

        $dataSql = "SELECT
            b.bookingId,
            b.transactNo as reservationNo,
            COALESCE(NULLIF(b.packageName, ''), p.packageName) as packageName,
            DATE(b.departureDate) as departureDate,
            DATE_ADD(
                DATE(b.departureDate),
                INTERVAL GREATEST(COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.durationDays, p.duration_days, 1) - 1, 0) DAY
            ) as returnDate,
            '' as branchName,
            '' as companyName,
            " . ($hasContactName ? "COALESCE(b.contactName,'') as contactName," : "'' as contactName,") . "
            COALESCE(b.contactEmail,'') as contactEmail,
            COALESCE(b.contactPhone,'') as contactPhone,
            " . ($hasSelectedOptions ? "COALESCE(b.selectedOptions,'') as selectedOptions," : "'' as selectedOptions,") . "
            {$reserverExprSql}
            (COALESCE(b.adults, 0) + COALESCE(b.children, 0) + COALESCE(b.infants, 0)) as numberOfPeople,
            g.guideName,
            b.bookingStatus,
            b.paymentStatus,
            b.totalAmount,
            TRIM(COALESCE(
                NULLIF(ag.agencyName, ''),
                NULLIF(ag2.agencyName, ''),
                NULLIF(CONCAT(ag.fName,' ',ag.lName), ' '),
                NULLIF(CONCAT(ag2.fName,' ',ag2.lName), ' '),
                ''
            )) as agentName,
            b.createdAt,
            b.updatedAt,
            COALESCE(a.accountType, '') as requestedByType
            " . ($hasDownPaymentFile ? ", COALESCE(b.downPaymentFile,'') as downPaymentFile" : ", '' as downPaymentFile") . "
        FROM bookings b
        LEFT JOIN accounts a ON b.accountId = a.accountId
        {$customerJoinSql}
        LEFT JOIN client c ON b.accountId = c.accountId
        
        LEFT JOIN agent ag ON ag.id = b.agentId
        
        LEFT JOIN agent ag2 ON ag2.accountId = b.accountId AND b.agentId IS NULL
        
        LEFT JOIN guides g ON b.guideId = g.guideId
        LEFT JOIN packages p ON b.packageId = p.packageId
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $bookings = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            // 예약자명: Customer Information(selectedOptions.customerInfo.name) 우선
            $reserverName = (string)($row['reserverName'] ?? '');
            $b2bStatusKey = '';
            try {
                $soRaw = (string)($row['selectedOptions'] ?? '');
                if ($soRaw !== '') {
                    $so = json_decode($soRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($so)) {
                        $ci = (isset($so['customerInfo']) && is_array($so['customerInfo'])) ? $so['customerInfo'] : null;
                        // customerInfo.name 뿐 아니라 firstName/lastName 등 다양한 키 대응
                        if (is_array($ci)) {
                            $ciName = trim((string)($ci['name'] ?? ''));
                            if ($ciName === '') {
                                $fn = trim((string)($ci['fName'] ?? $ci['firstName'] ?? $ci['customerFirstName'] ?? $ci['customer_first_name'] ?? ''));
                                $ln = trim((string)($ci['lName'] ?? $ci['lastName'] ?? $ci['customerLastName'] ?? $ci['customer_last_name'] ?? ''));
                                $ciName = trim($fn . ' ' . $ln);
                            }
                            if ($ciName !== '') $reserverName = $ciName;
                        }
                        $b2bStatusKey = trim((string)($so['b2bStatusKey'] ?? ''));
                    }
                }
            } catch (Throwable $e) { /* ignore */ }

            // contactName이 있으면 우선 (레거시/이관 데이터 보강)
            if (trim($reserverName) === '') {
                $reserverName = trim((string)($row['contactName'] ?? ''));
            }

            // booking_travelers(메인 여행자) fallback
            if (trim($reserverName) === '' && $travNameStmt) {
                try {
                    $tno = trim((string)($row['reservationNo'] ?? ''));
                    if ($tno !== '') {
                        $travNameStmt->bind_param('s', $tno);
                        $travNameStmt->execute();
                        $tr = $travNameStmt->get_result()->fetch_assoc();
                        if ($tr) {
                            $nm = trim((string)($tr['firstName'] ?? '') . ' ' . (string)($tr['lastName'] ?? ''));
                            if ($nm !== '') $reserverName = $nm;
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            // contactEmail/phone이 있으면, 마지막 fallback으로 사용
            if (trim($reserverName) === '') {
                $reserverName = trim((string)($row['contactEmail'] ?? '')) ?: trim((string)($row['contactPhone'] ?? ''));
            }

            // B2B 예약 상태키 - bookingStatus 직접 사용 (새로운 11단계 상태값)
            $statusKey = strtolower((string)($row['bookingStatus'] ?? 'waiting_down_payment'));

            // pending_update 예약인 경우 booking_change_requests에서 변경 요청 정보 조회
            $changeRequest = null;
            if ($statusKey === 'pending_update') {
                try {
                    $crStmt = $conn->prepare("SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' ORDER BY requestedAt DESC LIMIT 1");
                    if ($crStmt) {
                        $crStmt->bind_param('s', $row['bookingId']);
                        $crStmt->execute();
                        $crResult = $crStmt->get_result();
                        $cr = $crResult->fetch_assoc();
                        $crStmt->close();
                        if ($cr) {
                            $changeRequest = [
                                'id' => $cr['id'],
                                'changeType' => $cr['changeType'],
                                'originalStatus' => $cr['originalStatus'],
                                'originalPaymentStatus' => $cr['originalPaymentStatus'],
                                'targetStatus' => $cr['targetStatus'],
                                'targetPaymentStatus' => $cr['targetPaymentStatus'],
                                'previousData' => $cr['previousData'] ? json_decode($cr['previousData'], true) : null,
                                'newData' => $cr['newData'] ? json_decode($cr['newData'], true) : null,
                                'requestedBy' => $cr['requestedBy'],
                                'requestedByType' => $cr['requestedByType'] ?? 'employee',
                                'requestedAt' => $cr['requestedAt']
                            ];
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'reservationNo' => $row['reservationNo'] ?? $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                'departureDate' => $row['departureDate'] ?? '',
                'returnDate' => $row['returnDate'] ?? '',
                'branchName' => $row['branchName'] ?? '',
                'companyName' => $row['companyName'] ?? '',
                'agentName' => $row['agentName'] ?? '',
                'reserverName' => $reserverName,
                // 프론트가 customerName을 참조하므로 동일 값으로 제공
                'customerName' => $reserverName,
                // 프론트 상태 표시는 downPaymentFile 추론이 아니라 관리자 저장 상태를 우선
                'statusKey' => $statusKey,
                'numberOfPeople' => intval($row['numberOfPeople'] ?? 0),
                'guideName' => $row['guideName'] ?? 'Unassigned',
                'bookingStatus' => $row['bookingStatus'] ?? 'pending',
                'paymentStatus' => $row['paymentStatus'] ?? 'pending',
                'totalAmount' => floatval($row['totalAmount'] ?? 0),
                'downPaymentFile' => $row['downPaymentFile'] ?? '',
                'depositProofFile' => $row['downPaymentFile'] ?? '', // 레거시 호환
                'createdAt' => $row['createdAt'] ?? '',
                'requestedByType' => $row['requestedByType'] ?? '',
                'changeRequest' => $changeRequest,
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        try { if ($travNameStmt) $travNameStmt->close(); } catch (Throwable $e) { /* ignore */ }
        
        send_success_response([
            'bookings' => $bookings,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get B2B bookings: ' . $e->getMessage());
    }
}

function exportB2BBookingsCsv($conn, $input) {
    try {
        // CSV 다운로드 시점에도 자동취소를 반영(목록과 동일한 DB 상태 유지)
        // 통일: downPaymentDueDate/downPaymentFile, balanceDueDate/balanceFile 사용
        try {
            $hasDownPaymentDueDate = __table_has_column($conn, 'bookings', 'downPaymentDueDate');
            $hasDownPaymentFile = __table_has_column($conn, 'bookings', 'downPaymentFile');
            $hasBalanceDueDate = __table_has_column($conn, 'bookings', 'balanceDueDate');
            $hasBalanceFile = __table_has_column($conn, 'bookings', 'balanceFile');

            if (($hasDownPaymentDueDate && $hasDownPaymentFile) || ($hasBalanceDueDate && $hasBalanceFile)) {
                $dateExpr = function ($col) {
                    return "(CASE
                        WHEN CAST($col AS CHAR) REGEXP '^[0-9]{8}$' THEN STR_TO_DATE(CAST($col AS CHAR), '%Y%m%d')
                        ELSE DATE($col)
                    END)";
                };
                // B2B 예약 = agent 계정 또는 price_tier='B2B'
                $join = "LEFT JOIN accounts a ON b.accountId = a.accountId";
                $b2bCond = "AND (a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)";

                $conds = [];
                if ($hasDownPaymentDueDate && $hasDownPaymentFile) {
                    $conds[] = "(" . $dateExpr('b.downPaymentDueDate') . " IS NOT NULL AND " . $dateExpr('b.downPaymentDueDate') . " < CURDATE() AND COALESCE(b.downPaymentFile,'') = '')";
                }
                if ($hasBalanceDueDate && $hasBalanceFile) {
                    $conds[] = "(" . $dateExpr('b.balanceDueDate') . " IS NOT NULL AND " . $dateExpr('b.balanceDueDate') . " < CURDATE() AND COALESCE(b.balanceFile,'') = '')";
                }
                if (!empty($conds)) {
                    $sql = "UPDATE bookings b
                            $join
                            SET b.bookingStatus='cancelled', b.paymentStatus='failed'
                            WHERE COALESCE(b.paymentStatus,'') = 'pending'
                              AND COALESCE(b.bookingStatus,'') NOT IN ('cancelled','confirmed','completed','pending_update','check_reject')
                              $b2bCond
                              AND (" . implode(' OR ', $conds) . ")";
                    $conn->query($sql);
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // filters (same as list) - 통일: downPaymentFile 사용
        $hasDownPaymentFile = false;
        $dp = $conn->query("SHOW COLUMNS FROM bookings LIKE 'downPaymentFile'");
        if ($dp && $dp->num_rows > 0) $hasDownPaymentFile = true;

        // B2B CSV도 목록과 동일하게 예약자/가격 티어 기준으로 필터링
        $whereConditions = [];

        // B2B 예약 = agent 계정 또는 price_tier='B2B'
        $whereConditions[] = "(a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)";
        // 빈 bookingStatus 레코드 제외
        $whereConditions[] = "COALESCE(b.bookingStatus, '') != ''";
        $params = [];
        $types = '';

        // selectedOptions(customerInfo) 컬럼 유무(환경별 대응)
        $hasSelectedOptions = false;
        try {
            $soCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'selectedOptions'");
            if ($soCol && $soCol->num_rows > 0) $hasSelectedOptions = true;
        } catch (Throwable $e) { $hasSelectedOptions = false; }

        if (!empty($input['search'])) {
            $searchType = $input['searchType'] ?? 'all';
            $searchTerm = '%' . $input['search'] . '%';
            $customerInfoTerm = '%"customerInfo"%' . $input['search'] . '%';
            if ($searchType === 'product') {
                $whereConditions[] = "(
                    COALESCE(NULLIF(b.packageName, ''), p.packageName, '') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
                $types .= 'sss';
            } else if ($searchType === 'agent') {
                $whereConditions[] = "(
                    b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm; $params[] = $searchTerm;
                $types .= 'ss';
            } else if ($searchType === 'reserver') {
                $whereConditions[] = "(
                    TRIM(COALESCE(
                        NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                        NULLIF(a.username, ''),
                        NULLIF(a.emailAddress, ''),
                        ''
                    )) LIKE ?
                    " . ($hasSelectedOptions ? " OR COALESCE(b.selectedOptions,'') LIKE ?" : "") . "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                if ($hasSelectedOptions) { $params[] = $customerInfoTerm; $types .= 's'; }
                $params[] = $searchTerm; // contactEmail
                $params[] = $searchTerm; // contactPhone
                $params[] = $searchTerm; // bookingId
                $params[] = $searchTerm; // transactNo/bookingId
                $types .= 'sssss';
            } else {
                $whereConditions[] = "(
                    TRIM(COALESCE(
                        NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                        NULLIF(a.username, ''),
                        NULLIF(a.emailAddress, ''),
                        ''
                    )) LIKE ?
                    " . ($hasSelectedOptions ? " OR COALESCE(b.selectedOptions,'') LIKE ?" : "") . "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR COALESCE(NULLIF(b.packageName, ''), p.packageName, '') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $searchTerm;
                if ($hasSelectedOptions) { $params[] = $customerInfoTerm; $types .= 's'; }
                $params[] = $searchTerm; // contactEmail
                $params[] = $searchTerm; // contactPhone
                $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
                $types .= 'ssssss';
            }
        }

        // Travel start date 필터 (날짜 범위 지원)
        if (!empty($input['travelStartDate'])) {
            $dateRange = explode(',', (string)$input['travelStartDate']);
            if (count($dateRange) === 2) {
                $whereConditions[] = "DATE(b.departureDate) >= ? AND DATE(b.departureDate) <= ?";
                $params[] = trim($dateRange[0]);
                $params[] = trim($dateRange[1]);
                $types .= 'ss';
            } else {
                $whereConditions[] = "DATE(b.departureDate) = ?";
                $params[] = (string)$input['travelStartDate'];
                $types .= 's';
            }
        }

        if (!empty($input['status'])) {
            $status = (string)$input['status'];
            if ($status === 'pending' || $status === 'partial') {
                $whereConditions[] = "b.paymentStatus = 'pending'";
                if ($hasDownPaymentFile) {
                    if ($status === 'pending') $whereConditions[] = "COALESCE(b.downPaymentFile,'') = ''";
                    else $whereConditions[] = "COALESCE(b.downPaymentFile,'') <> ''";
                }
            } else {
                $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
                if (in_array($status, $paymentStatuses, true)) {
                    $whereConditions[] = "b.paymentStatus = ?";
                    $params[] = $status;
                } else {
                    $whereConditions[] = "b.bookingStatus = ?";
                    $params[] = $status;
                }
                $types .= 's';
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $hasCreatedAt = false;
        $cRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'createdAt'");
        if ($cRes && $cRes->num_rows > 0) $hasCreatedAt = true;
        $orderBy = $hasCreatedAt ? "b.createdAt DESC" : "b.departureDate DESC";

        $sql = "SELECT 
                    b.bookingId,
                    b.transactNo as reservationNo,
                    COALESCE(NULLIF(b.packageName, ''), p.packageName) as packageName,
                    DATE(b.departureDate) as departureDate,
                    DATE_ADD(
                        DATE(b.departureDate),
                        INTERVAL GREATEST(COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.durationDays, p.duration_days, 1) - 1, 0) DAY
                    ) as returnDate,
                    '' as branchName,
                    '' as companyName,
                    TRIM(COALESCE(
                        NULLIF(CONCAT(c.fName, ' ', c.lName), ' '),
                        NULLIF(CONCAT(ag.fName, ' ', ag.lName), ' '),
                        NULLIF(a.username, ''),
                        NULLIF(a.emailAddress, ''),
                        ''
                    )) as reserverName,
                    b.totalAmount,
                    b.paymentStatus,
                    b.bookingStatus
                FROM bookings b
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN client c ON b.accountId = c.accountId
                
                LEFT JOIN agent ag ON a.accountId = ag.accountId
                
                LEFT JOIN packages p ON b.packageId = p.packageId
                $whereClause
                ORDER BY $orderBy";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="b2b_bookings_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        // SMT 수정 시작 - UTF-8 BOM 추가 (한글 깨짐 방지)
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        // SMT 수정 완료
        fputcsv($out, ['No', 'Reservation No', 'Product Name', 'Departure Date', 'Return Date', 'Agent Name', 'Reservation Name', 'Total Amount', 'Payment Status', 'Reservation Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            $agentName = $row['branchName'] ?: ($row['companyName'] ?? '');
            fputcsv($out, [
                $no++,
                $row['reservationNo'] ?? ($row['bookingId'] ?? ''),
                $row['packageName'] ?? '',
                $row['departureDate'] ?? '',
                $row['returnDate'] ?? '',
                $agentName,
                $row['reserverName'] ?? '',
                $row['totalAmount'] ?? 0,
                $row['paymentStatus'] ?? '',
                $row['bookingStatus'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export B2B bookings CSV: ' . $e->getMessage(), 500);
    }
}

function getB2CBookings($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // NOTE: B2C 예약 분류 기준(운영 데이터 기준)
        // - accounts.accountType = 'guest' 가 B2C
        // - 일부 레거시/이관 데이터는 client.companyId가 NULL이 아닐 수 있으므로 companyId로 B2C/B2B를 가르면 누락됩니다.
        $hasAccounts = false;
        $hasAccountType = false;
        $tAcc = $conn->query("SHOW TABLES LIKE 'accounts'");
        if ($tAcc && $tAcc->num_rows > 0) {
            $hasAccounts = true;
            $cAcc = $conn->query("SHOW COLUMNS FROM accounts LIKE 'accountType'");
            if ($cAcc && $cAcc->num_rows > 0) $hasAccountType = true;
        }

        // B2C 예약 목록 기준(변경됨):
        // - agent 계정이 아니고 price_tier='B2B'가 아닌 예약
        // - 상품의 sales_target과 무관하게 예약자/가격 티어 기준으로 분류
        $whereConditions = [];

        // B2C 예약 = agent가 아닌 계정 AND price_tier가 B2B가 아닌 경우
        $whereConditions[] = "(COALESCE(a.accountType,'') != 'agent' AND COALESCE(b.price_tier,'B2C') != 'B2B' AND b.agentId IS NULL)";
        $params = [];
        $types = '';
        
        // selectedOptions(customerInfo) / contactName 컬럼 유무(환경별 대응)
        $hasSelectedOptions = false;
        $hasContactName = false;
        try {
            $soCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'selectedOptions'");
            if ($soCol && $soCol->num_rows > 0) $hasSelectedOptions = true;
        } catch (Throwable $e) { $hasSelectedOptions = false; }
        try {
            $cnCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactName'");
            if ($cnCol && $cnCol->num_rows > 0) $hasContactName = true;
        } catch (Throwable $e) { $hasContactName = false; }

        $search = isset($input['search']) ? trim((string)$input['search']) : '';
        $searchType = isset($input['searchType']) ? trim((string)$input['searchType']) : '';
        if ($search !== '') {
            $term = '%' . $search . '%';
            $customerInfoTerm = '%"customerInfo"%' . $search . '%';
            // 검색 기준: All / Product Name / Reservation Name
            if ($searchType === 'product_name') {
                // Include bookingId in search so reservation number can be found.
                $whereConditions[] = "(
                    b.packageName LIKE ?
                    OR p.packageName LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $types .= 'ssss';
            } elseif ($searchType === 'reservation_name') {
                // SMT 수정 시작 - Reservation Name 정확 일치 검색으로 변경
                // 정확 일치 검색: LIKE '%검색어%' 대신 = ? 사용
                $exactTerm = $search;
                $where = "(
                    TRIM(CONCAT(c.fName, ' ', c.lName)) = ?
                    OR TRIM(CONCAT(a.firstName, ' ', a.lastName)) = ?
                    OR TRIM(COALESCE(a.displayName,'')) = ?";
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';

                if ($hasContactName) { $where .= " OR TRIM(COALESCE(b.contactName,'')) = ?"; $params[] = $exactTerm; $types .= 's'; }
                // selectedOptions JSON 검색은 정확 일치가 어려우므로 제외하거나 별도 처리
                // bookingId와 transactNo는 예약 번호이므로 정확 일치로 검색
                $where .= "
                    OR TRIM(COALESCE(b.contactEmail,'')) = ?
                    OR TRIM(COALESCE(b.contactPhone,'')) = ?
                    OR b.bookingId = ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) = ?
                )";
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $whereConditions[] = $where;
                // SMT 수정 완료
            } else {
                $where = "(
                    CONCAT(c.fName, ' ', c.lName) LIKE ?
                    OR CONCAT(a.firstName, ' ', a.lastName) LIKE ?
                    OR a.displayName LIKE ?
                    OR b.packageName LIKE ?
                    OR p.packageName LIKE ?";
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';

                if ($hasContactName) { $where .= " OR COALESCE(b.contactName,'') LIKE ?"; $params[] = $term; $types .= 's'; }
                if ($hasSelectedOptions) { $where .= " OR COALESCE(b.selectedOptions,'') LIKE ?"; $params[] = $customerInfoTerm; $types .= 's'; }

                $where .= "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $whereConditions[] = $where;
            }
        }

        // Travel start date 필터 (날짜 범위 지원)
        if (!empty($input['travelStartDate'])) {
            $dateRange = explode(',', (string)$input['travelStartDate']);
            if (count($dateRange) === 2) {
                $whereConditions[] = "DATE(b.departureDate) >= ? AND DATE(b.departureDate) <= ?";
                $params[] = trim($dateRange[0]);
                $params[] = trim($dateRange[1]);
                $types .= 'ss';
            } else {
                $whereConditions[] = "DATE(b.departureDate) = ?";
                $params[] = (string)$input['travelStartDate'];
                $types .= 's';
            }
        }

        // SMT 수정 시작 - status 필터 로직 수정 (B2B와 동일하게)
        if (!empty($input['status'])) {
            // UI 요구:
            // - All Status / Payment Suspended / Payment Completed / Payment Canceled / Refund Completed / Trip Completed
            $status = strtolower(trim((string)$input['status']));

            // payment_suspended / stopped: 입금/결제 대기
            // - B2C 요구사항: 예약 직후(입금 전)도 여기로 분류되어야 함
            // - 따라서 bookingStatus=confirmed 이더라도 paymentStatus=pending 이면 suspended 로 봄
            if ($status === 'payment_suspended' || $status === 'stopped') {
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) = 'pending'";
                // suspended는 trip/payment-canceled/refund 와 섞이면 안됨
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) <> 'completed'";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) <> 'cancelled'";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) <> 'refunded'";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) <> 'failed'";
            }
            // payment_completed / completed: confirmed 또는 paid
            else if ($status === 'payment_completed' || $status === 'completed') {
                // payment_completed는 trip_completed(bookingStatus=completed)를 포함하면 안됨 (dev_tasks #124)
                // NOTE: B2C에서는 paymentStatus='paid' 인 경우만 결제완료로 본다.
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) = 'paid'";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) <> 'completed'";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) <> 'refunded'";
            }
            // payment_canceled / canceled: cancelled 또는 failed (refunded 제외)
            else if ($status === 'payment_canceled' || $status === 'canceled') {
                $whereConditions[] = "(LOWER(TRIM(COALESCE(b.bookingStatus,''))) = 'cancelled' OR LOWER(TRIM(COALESCE(b.paymentStatus,''))) = 'failed')";
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) <> 'refunded'";
            }
            // refund_completed / refunded: 환불 완료
            else if ($status === 'refund_completed' || $status === 'refunded') {
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.paymentStatus,''))) = 'refunded'";
            }
            // trip_completed: 여행 완료
            else if ($status === 'trip_completed') {
                $whereConditions[] = "LOWER(TRIM(COALESCE(b.bookingStatus,''))) = 'completed'";
            }
            // fallback: 그대로 비교
            else {
                $whereConditions[] = "(LOWER(TRIM(COALESCE(b.paymentStatus,''))) = ? OR LOWER(TRIM(COALESCE(b.bookingStatus,''))) = ?)";
                $params[] = $status;
                $params[] = $status;
                $types .= 'ss';
            }
        }
        // SMT 수정 완료
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // Latest-first ordering: prefer bookings.createdAt if present, otherwise fall back to departureDate.
        $hasCreatedAt = false;
        try {
            $cRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'createdAt'");
            if ($cRes && $cRes->num_rows > 0) $hasCreatedAt = true;
        } catch (Throwable $e) {
            $hasCreatedAt = false;
        }
        $orderBy = $hasCreatedAt ? "b.createdAt DESC" : "b.departureDate DESC";
        
        $countSql = "SELECT COUNT(*) as total FROM bookings b
                     LEFT JOIN client c ON b.accountId = c.accountId
                     LEFT JOIN accounts a ON b.accountId = a.accountId
                     LEFT JOIN guides g ON b.guideId = g.guideId
                     LEFT JOIN packages p ON b.packageId = p.packageId
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $dataSql = "SELECT 
            b.bookingId,
            b.packageId,
            COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) as packageName,
            b.departureDate,
            TRIM(CONCAT(
                COALESCE(NULLIF(c.fName,''), NULLIF(a.firstName,''), NULLIF(a.displayName,''), ''),
                ' ',
                COALESCE(NULLIF(c.lName,''), NULLIF(a.lastName,''), '')
            )) as reserverName,
            (COALESCE(b.adults, 0) + COALESCE(b.children, 0) + COALESCE(b.infants, 0)) as numberOfPeople,
            g.guideName,
            b.bookingStatus,
            b.paymentStatus
        FROM bookings b
        LEFT JOIN client c ON b.accountId = c.accountId
        LEFT JOIN accounts a ON b.accountId = a.accountId
        LEFT JOIN guides g ON b.guideId = g.guideId
        LEFT JOIN packages p ON b.packageId = p.packageId
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $bookings = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $paymentStatus = strtolower((string)($row['paymentStatus'] ?? ''));
            $bookingStatus = strtolower((string)($row['bookingStatus'] ?? ''));

            // 리스트 표기용 상태키/라벨(요구사항 기준)
            // - Trip Completed: bookingStatus=completed
            // - Refund Completed: paymentStatus=refunded
            // - Payment Canceled: bookingStatus=cancelled OR paymentStatus=failed
            // - Payment Completed: paymentStatus=paid
            // - Payment Suspended: 그 외(입금/결제 대기 포함)
            $statusKey = 'payment_suspended';
            if ($bookingStatus === 'completed') $statusKey = 'trip_completed';
            else if ($paymentStatus === 'refunded') $statusKey = 'refund_completed';
            else if ($paymentStatus === 'failed' || $bookingStatus === 'cancelled') $statusKey = 'payment_canceled';
            else if ($paymentStatus === 'paid') $statusKey = 'payment_completed';
            else $statusKey = 'payment_suspended';

            // 호환: 기존 프론트에서 booking.status.includes(...)를 사용하던 케이스 대응
            // (KOR 라벨을 함께 내려줌)
            $statusKo = '결제 중단';
            $statusEng = 'Payment Suspended';
            if ($statusKey === 'trip_completed') { $statusKo = '여행 완료'; $statusEng = 'Trip Completed'; }
            else if ($statusKey === 'refund_completed') { $statusKo = '환불 완료'; $statusEng = 'Refund Completed'; }
            else if ($statusKey === 'payment_canceled') { $statusKo = '결제 취소'; $statusEng = 'Payment Canceled'; }
            else if ($statusKey === 'payment_completed') { $statusKo = '결제 완료'; $statusEng = 'Payment Completed'; }

            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'packageId' => isset($row['packageId']) ? intval($row['packageId']) : null,
                'packageName' => $row['packageName'] ?? '',
                'travelStartDate' => $row['departureDate'] ?? '',
                'reserverName' => $row['reserverName'] ?? '',
                'numberOfPeople' => intval($row['numberOfPeople'] ?? 0),
                'guideName' => $row['guideName'] ?? '',
                'paymentStatus' => $row['paymentStatus'] ?? '',
                'bookingStatus' => $row['bookingStatus'] ?? '',
                'statusKey' => $statusKey,
                'status' => $statusKo,
                'statusEng' => $statusEng,
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'bookings' => $bookings,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get B2C bookings: ' . $e->getMessage());
    }
}

function exportB2CBookingsCsv($conn, $input) {
    try {
        $hasAccounts = false;
        $hasAccountType = false;
        $tAcc = $conn->query("SHOW TABLES LIKE 'accounts'");
        if ($tAcc && $tAcc->num_rows > 0) {
            $hasAccounts = true;
            $cAcc = $conn->query("SHOW COLUMNS FROM accounts LIKE 'accountType'");
            if ($cAcc && $cAcc->num_rows > 0) $hasAccountType = true;
        }

        // B2C CSV도 목록과 동일하게 예약자/가격 티어 기준으로 필터링
        $whereConditions = [];

        // B2C 예약 = agent가 아닌 계정 AND price_tier가 B2B가 아닌 경우
        $whereConditions[] = "(COALESCE(a.accountType,'') != 'agent' AND COALESCE(b.price_tier,'B2C') != 'B2B' AND b.agentId IS NULL)";
        $params = [];
        $types = '';

        // selectedOptions(customerInfo) / contactName 컬럼 유무(환경별 대응)
        $hasSelectedOptions = false;
        $hasContactName = false;
        try {
            $soCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'selectedOptions'");
            if ($soCol && $soCol->num_rows > 0) $hasSelectedOptions = true;
        } catch (Throwable $e) { $hasSelectedOptions = false; }
        try {
            $cnCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactName'");
            if ($cnCol && $cnCol->num_rows > 0) $hasContactName = true;
        } catch (Throwable $e) { $hasContactName = false; }

        $search = isset($input['search']) ? trim((string)$input['search']) : '';
        $searchType = isset($input['searchType']) ? trim((string)$input['searchType']) : '';
        if ($search !== '') {
            $term = '%' . $search . '%';
            $customerInfoTerm = '%"customerInfo"%' . $search . '%';
            if ($searchType === 'product_name') {
                $whereConditions[] = "(
                    b.packageName LIKE ?
                    OR p.packageName LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $types .= 'ssss';
            } elseif ($searchType === 'reservation_name') {
                // SMT 수정 시작 - Reservation Name 정확 일치 검색으로 변경 (CSV export)
                $exactTerm = $search;
                $where = "(
                    TRIM(CONCAT(c.fName, ' ', c.lName)) = ?
                    OR TRIM(CONCAT(a.firstName, ' ', a.lastName)) = ?
                    OR TRIM(COALESCE(a.displayName,'')) = ?";
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';

                if ($hasContactName) { $where .= " OR TRIM(COALESCE(b.contactName,'')) = ?"; $params[] = $exactTerm; $types .= 's'; }
                // selectedOptions JSON 검색은 정확 일치가 어려우므로 제외
                // bookingId와 transactNo는 예약 번호이므로 정확 일치로 검색
                $where .= "
                    OR TRIM(COALESCE(b.contactEmail,'')) = ?
                    OR TRIM(COALESCE(b.contactPhone,'')) = ?
                    OR b.bookingId = ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) = ?
                )";
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $params[] = $exactTerm; $types .= 's';
                $whereConditions[] = $where;
                // SMT 수정 완료
            } else {
                $where = "(
                    CONCAT(c.fName, ' ', c.lName) LIKE ?
                    OR CONCAT(a.firstName, ' ', a.lastName) LIKE ?
                    OR a.displayName LIKE ?
                    OR b.packageName LIKE ?
                    OR p.packageName LIKE ?";
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';

                if ($hasContactName) { $where .= " OR COALESCE(b.contactName,'') LIKE ?"; $params[] = $term; $types .= 's'; }
                if ($hasSelectedOptions) { $where .= " OR COALESCE(b.selectedOptions,'') LIKE ?"; $params[] = $customerInfoTerm; $types .= 's'; }

                $where .= "
                    OR COALESCE(b.contactEmail,'') LIKE ?
                    OR COALESCE(b.contactPhone,'') LIKE ?
                    OR b.bookingId LIKE ?
                    OR COALESCE(NULLIF(b.transactNo,''), b.bookingId) LIKE ?
                )";
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $params[] = $term; $types .= 's';
                $whereConditions[] = $where;
            }
        }

        // Travel start date 필터 (날짜 범위 지원)
        if (!empty($input['travelStartDate'])) {
            $dateRange = explode(',', (string)$input['travelStartDate']);
            if (count($dateRange) === 2) {
                $whereConditions[] = "DATE(b.departureDate) >= ? AND DATE(b.departureDate) <= ?";
                $params[] = trim($dateRange[0]);
                $params[] = trim($dateRange[1]);
                $types .= 'ss';
            } else {
                $whereConditions[] = "DATE(b.departureDate) = ?";
                $params[] = (string)$input['travelStartDate'];
                $types .= 's';
            }
        }

        if (!empty($input['status'])) {
            // UI 요구(신규) + 레거시 호환
            $status = trim((string)$input['status']);
            $map = [
                // 신규 키
                'payment_completed' => ['paid', 'confirmed'],
                'payment_canceled' => ['failed', 'cancelled'],
                'payment_suspended' => ['pending'],
                'refund_completed' => ['refunded'],
                'trip_completed' => ['completed'],
                // 레거시 키
                'completed' => ['paid', 'confirmed'],
                'canceled' => ['failed', 'cancelled'],
                'stopped' => ['pending'],
                'refunded' => ['refunded'],
            ];
            if (isset($map[$status])) {
                $vals = $map[$status];
                $in = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "(b.paymentStatus IN ($in) OR b.bookingStatus IN ($in))";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            } else {
                $whereConditions[] = "(b.paymentStatus = ? OR b.bookingStatus = ?)";
                $params[] = $status;
                $params[] = $status;
                $types .= 'ss';
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // Keep CSV ordering consistent with list: latest first (createdAt if exists).
        $hasCreatedAt = false;
        try {
            $cRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'createdAt'");
            if ($cRes && $cRes->num_rows > 0) $hasCreatedAt = true;
        } catch (Throwable $e) { $hasCreatedAt = false; }
        $orderBy = $hasCreatedAt ? "b.createdAt DESC" : "b.departureDate DESC";

        $sql = "SELECT
                    b.bookingId,
                    COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) as packageName,
                    b.departureDate,
                    TRIM(CONCAT(
                        COALESCE(NULLIF(c.fName,''), NULLIF(a.firstName,''), NULLIF(a.displayName,''), ''),
                        ' ',
                        COALESCE(NULLIF(c.lName,''), NULLIF(a.lastName,''), '')
                    )) as reserverName,
                    (COALESCE(b.adults, 0) + COALESCE(b.children, 0) + COALESCE(b.infants, 0)) as numberOfPeople,
                    COALESCE(g.guideName, 'Unassigned') as guideName,
                    b.paymentStatus,
                    b.bookingStatus
                FROM bookings b
                LEFT JOIN client c ON b.accountId = c.accountId
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN guides g ON b.guideId = g.guideId
                LEFT JOIN packages p ON b.packageId = p.packageId
                $whereClause
                ORDER BY $orderBy";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV export: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="b2c_bookings_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        // SMT 수정 시작 - UTF-8 BOM 추가 (한글 깨짐 방지)
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        // SMT 수정 완료
        fputcsv($out, ['No', 'Booking ID', 'Product Name', 'Departure Date', 'Reservation Name', 'PAX', 'Guide', 'Payment Status', 'Reservation Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $no++,
                $row['bookingId'] ?? '',
                $row['packageName'] ?? '',
                $row['departureDate'] ?? '',
                $row['reserverName'] ?? '',
                $row['numberOfPeople'] ?? 0,
                $row['guideName'] ?? '미배정',
                $row['paymentStatus'] ?? '',
                $row['bookingStatus'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export B2C bookings CSV: ' . $e->getMessage(), 500);
    }
}

function getBookingDetail($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        
        $sql = "SELECT
            b.*,
            c.fName,
            c.lName,
            c.emailAddress,
            c.contactNo,
            c.countryCode,
            g.guideName,
            -- Latest payment info (for B2C detail: paymentMethod/paymentDate)
            COALESCE(b.paymentMethod, pay.paymentMethod) as paymentMethod,
            COALESCE(pay.paymentDate, pay.createdAt, b.updatedAt, b.createdAt) as paidAt,
            COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) as fullPackageName,
            p.product_pricing,
            p.durationDays,
            p.duration_days,
            p.meeting_location,
            p.meeting_address,
            p.meeting_time,
            p.meetingPoint,
            p.meetingTime,
            -- Agent info: agentId로 조인하거나, accountId가 에이전트 계정인 경우 처리
            COALESCE(ag1.agencyName, ag2.agencyName, '') as agentName,
            COALESCE(ag1.agencyName, ag2.agencyName, '') as branchName,
            '' as companyName,
            COALESCE(CONCAT(ag1.fName, ' ', ag1.lName), CONCAT(ag2.fName, ' ', ag2.lName)) as agentManagerName,
            COALESCE(ag1.personInChargeEmail, ag2.personInChargeEmail) as agentManagerEmail,
            COALESCE(CONCAT(ag1.countryCode, ag1.contactNo), CONCAT(ag2.countryCode, ag2.contactNo)) as agentManagerContact
        FROM bookings b
        LEFT JOIN client c ON b.accountId = c.accountId
        LEFT JOIN guides g ON b.guideId = g.guideId
        LEFT JOIN (
            SELECT p1.*
            FROM payments p1
            INNER JOIN (
                SELECT bookingId, MAX(paymentId) AS maxPaymentId
                FROM payments
                GROUP BY bookingId
            ) pm ON pm.bookingId = p1.bookingId AND pm.maxPaymentId = p1.paymentId
        ) pay ON pay.bookingId = b.bookingId
        LEFT JOIN packages p ON b.packageId = p.packageId
        -- Agent via agentId (에이전트가 고객을 위해 예약한 경우)
        LEFT JOIN agent ag1 ON b.agentId = ag1.id
        -- Agent via accountId (에이전트가 자기 계정으로 직접 예약한 경우)
        LEFT JOIN agent ag2 ON b.accountId = ag2.accountId AND b.agentId IS NULL
        WHERE b.bookingId = ?";
        
        $stmt = $conn->prepare($sql);
        // bookings.bookingId는 varchar(20)
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Booking not found', 404);
        }
        
        $booking = $result->fetch_assoc();
        $stmt->close();

        // packageName 보정: bookings.packageName가 비어있고, packages 조인도 없을 수 있음(삭제된 상품)
        if (!isset($booking['packageName']) || trim((string)$booking['packageName']) === '') {
            $booking['packageName'] = $booking['fullPackageName'] ?? '';
        }

        // returnDate 계산
        // - 우선순위:
        //   1) package_schedules의 최대 day_number (실제 일정 기반, 가장 신뢰도 높음)
        //   2) packages.duration_days (snake)
        //   3) packages.durationDays (camel)
        // - 운영 데이터에서 durationDays=1, duration_days=3, schedules=2 처럼 충돌하는 케이스가 있어
        //   schedules가 있으면 이를 최우선으로 사용해야 Travel period가 실제 일정과 일치합니다.
        $existingReturn = trim((string)($booking['returnDate'] ?? ''));
        if ($existingReturn === '') {
            $returnDate = null;
            try {
                $dep = $booking['departureDate'] ?? null;
                $dur = 0;

                // 1) schedules 기반 duration 추정
                try {
                    $pid = intval($booking['packageId'] ?? 0);
                    if ($pid > 0) {
                        $maxDay = 0;
                        $mx = $conn->prepare("SELECT MAX(day_number) AS maxDay FROM package_schedules WHERE package_id = ?");
                        if ($mx) {
                            $mx->bind_param('i', $pid);
                            $mx->execute();
                            $mr = $mx->get_result()->fetch_assoc();
                            $mx->close();
                            $maxDay = intval($mr['maxDay'] ?? 0);
                        }
                        if ($maxDay > 0) $dur = $maxDay;
                    }
                } catch (Throwable $e) {
                    // ignore schedules errors and fall back to package columns
                }

                // 2) packages duration columns fallback
                if ($dur <= 0) {
                    $durDays = $booking['duration_days'] ?? null;
                    $durCamel = $booking['durationDays'] ?? null;
                    if (is_numeric($durDays) && intval($durDays) > 0) $dur = intval($durDays);
                    else if (is_numeric($durCamel) && intval($durCamel) > 0) $dur = intval($durCamel);
                    else $dur = 1;
                }

                if (!empty($dep) && $dur > 0) {
                    $dt = new DateTime($dep);
                    $dt->modify('+' . max($dur - 1, 0) . ' day');
                    $returnDate = $dt->format('Y-m-d');
                }
            } catch (Exception $e) {
                $returnDate = null;
            }
            $booking['returnDate'] = $returnDate;
        }

        // selectedOptions / selectedRooms 파싱
        $selectedOptionsRaw = $booking['selectedOptions'] ?? '';
        $selectedRoomsRaw = $booking['selectedRooms'] ?? '';
        $selectedOptionsObj = null;
        $selectedRoomsObj = null;
        if (is_string($selectedOptionsRaw) && $selectedOptionsRaw !== '') {
            $tmp = json_decode($selectedOptionsRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) $selectedOptionsObj = $tmp;
        }
        if (is_string($selectedRoomsRaw) && $selectedRoomsRaw !== '') {
            $tmp = json_decode($selectedRoomsRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) $selectedRoomsObj = $tmp;
        }

        // selectedRoomsObj가 비어있으면 selectedOptionsObj 안의 selectedRooms 사용 (fallback)
        if (empty($selectedRoomsObj) && is_array($selectedOptionsObj) && isset($selectedOptionsObj['selectedRooms'])) {
            $selectedRoomsObj = $selectedOptionsObj['selectedRooms'];
        }

        // 예약자(고객) 정보: selectedOptions.customerInfo 우선, 없으면 client/accounts
        $customerInfo = [
            'name' => trim(($booking['fName'] ?? '') . ' ' . ($booking['lName'] ?? '')),
            'email' => $booking['emailAddress'] ?? ($booking['contactEmail'] ?? ''),
            'countryCode' => $booking['countryCode'] ?? '+63',
            'phone' => $booking['contactNo'] ?? ($booking['contactPhone'] ?? '')
        ];
        if (is_array($selectedOptionsObj) && isset($selectedOptionsObj['customerInfo']) && is_array($selectedOptionsObj['customerInfo'])) {
            $ci = $selectedOptionsObj['customerInfo'];
            $customerInfo['name'] = $ci['name'] ?? $customerInfo['name'];
            $customerInfo['email'] = $ci['email'] ?? $customerInfo['email'];
            $customerInfo['countryCode'] = $ci['country_code'] ?? $ci['countryCode'] ?? $customerInfo['countryCode'];
            $customerInfo['phone'] = $ci['phone'] ?? $customerInfo['phone'];
        }

        // SMT 수정 시작 - 옵션 값 추출해서 booking 객체에 추가
        if (is_array($selectedOptionsObj)) {
            $booking['cabinBaggage'] = $selectedOptionsObj['cabinBaggage'] ?? '';
            $booking['breakfastRequest'] = $selectedOptionsObj['breakfastRequest'] ?? '';
            $booking['wifiRental'] = $selectedOptionsObj['wifiRental'] ?? '';
        }
        // SMT 수정 완료

        // 룸 옵션 요약: selectedRooms 기반
        $roomSummary = '';
        if (is_array($selectedRoomsObj)) {
            $parts = [];
            foreach ($selectedRoomsObj as $k => $room) {
                if (!is_array($room)) continue;
                $name = $room['roomType'] ?? $room['name'] ?? $k;
                $count = intval($room['count'] ?? 0);
                if ($count > 0) $parts[] = $name . 'x' . $count;
            }
            $roomSummary = implode(', ', $parts);
        }

        // 항공편 정보: package_flights 기반 (booking_flights 테이블은 없음)
        $flights = ['departure' => null, 'return' => null];
        if (!empty($booking['packageId'])) {
            $pid = intval($booking['packageId']);
            $fStmt = $conn->prepare("SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination FROM package_flights WHERE package_id = ? ORDER BY flight_type, flight_id");
            if ($fStmt) {
                $fStmt->bind_param('i', $pid);
                $fStmt->execute();
                $fRes = $fStmt->get_result();
                while ($row = $fRes->fetch_assoc()) {
                    if ($row['flight_type'] === 'departure' && $flights['departure'] === null) $flights['departure'] = $row;
                    if ($row['flight_type'] === 'return' && $flights['return'] === null) $flights['return'] = $row;
                }
                $fStmt->close();
            }
        }

        // 여행자 정보: booking_travelers.transactNo = transactNo(없으면 bookingId)
        $travelerKey = $booking['transactNo'] ?? '';
        if (empty($travelerKey)) $travelerKey = $bookingId;
        $travelerRows = [];
        $tStmt = $conn->prepare("
            SELECT
                bookingTravelerId,
                travelerType,
                title,
                firstName,
                lastName,
                birthDate,
                gender,
                nationality,
                passportNumber,
                passportIssueDate,
                passportExpiry,
                visaStatus,
                visaType,
                isMainTraveler,
                passportImage,
                reservationStatus,
                CASE WHEN passportImage IS NULL OR passportImage = '' THEN 0 ELSE 1 END as hasPassportImage
            FROM booking_travelers
            WHERE transactNo = ?
            ORDER BY bookingTravelerId ASC
        ");
        if ($tStmt) {
            $tStmt->bind_param('s', $travelerKey);
            $tStmt->execute();
            $tRes = $tStmt->get_result();
            while ($row = $tRes->fetch_assoc()) {
                $travelerRows[] = $row;
            }
            $tStmt->close();
        }
        
        // 인원 옵션(상품 요금 정보) 추출: packages.product_pricing(JSON)에서 optionName 리스트를 최대한 찾아 반환
        $peopleOptions = [];
        $pricingRaw = $booking['product_pricing'] ?? '';
        if (is_string($pricingRaw) && $pricingRaw !== '') {
            $decoded = json_decode($pricingRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $stack = [$decoded];
                while (!empty($stack)) {
                    $cur = array_pop($stack);
                    if (!is_array($cur)) continue;
                    foreach ($cur as $k => $v) {
                        if (is_array($v)) {
                            $stack[] = $v;
                        } else {
                            $lk = strtolower((string)$k);
                            if (in_array($lk, ['optionname', 'option_name', 'name'], true) && is_string($v) && trim($v) !== '') {
                                $peopleOptions[] = trim($v);
                            }
                        }
                    }
                }
            }
        }
        $peopleOptions = array_values(array_unique(array_filter($peopleOptions)));
        if (empty($peopleOptions)) {
            $peopleOptions = ['Adult', 'Child', 'Infant'];
        }

        send_success_response([
            'booking' => $booking,
            'customerInfo' => $customerInfo,
            'roomSummary' => $roomSummary,
            'selectedOptions' => $selectedOptionsObj,
            'selectedRooms' => $selectedRoomsObj,
            'flights' => $flights,
            'travelers' => $travelerRows,
            'peopleOptions' => $peopleOptions
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get booking detail: ' . $e->getMessage());
    }
}

// 특정 날짜와 패키지의 예약 목록 조회
function getBookingsByDateAndPackage($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $date = trim($input['date'] ?? '');

        if ($packageId <= 0) {
            send_error_response('Package ID is required');
            return;
        }
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            send_error_response('Valid date (YYYY-MM-DD) is required');
            return;
        }

        $sql = "SELECT
                    b.bookingId,
                    b.bookingStatus,
                    b.adults,
                    b.children,
                    b.infants,
                    b.totalAmount,
                    b.createdAt,
                    COALESCE(a.agencyName, CONCAT(a.fName, ' ', a.lName), CONCAT(c.fName, ' ', c.lName), '') as agencyName
                FROM bookings b
                LEFT JOIN client c ON b.accountId = c.accountId
                LEFT JOIN agent a ON b.accountId = a.accountId
                WHERE b.packageId = ?
                  AND b.departureDate = ?
                  AND b.bookingStatus NOT IN ('cancelled','draft')
                ORDER BY b.createdAt DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $packageId, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'bookingStatus' => $row['bookingStatus'],
                'reservationDate' => $row['createdAt'] ? date('Y-m-d', strtotime($row['createdAt'])) : '',
                'agencyName' => trim($row['agencyName'] ?? ''),
                'adults' => intval($row['adults'] ?? 0),
                'children' => intval($row['children'] ?? 0),
                'infants' => intval($row['infants'] ?? 0),
                'totalAmount' => floatval($row['totalAmount'] ?? 0)
            ];
        }
        $stmt->close();

        send_success_response($bookings);
    } catch (Exception $e) {
        send_error_response('Failed to get bookings: ' . $e->getMessage());
    }
}

// 가이드 목록 (회원관리 > 가이드 목록에서 "계약중"에 해당하는 개념을 active로 해석)
function getContractGuides($conn, $input) {
    try {
        $today = date('Y-m-d');
        // 스키마 차이 흡수: accounts(accountStatus vs status), guides(contractStartDate/contractEndDate/status) 컬럼 유무
        $guideCols = [];
        $gc = $conn->query("SHOW COLUMNS FROM guides");
        if ($gc) {
            while ($r = $gc->fetch_assoc()) $guideCols[strtolower($r['Field'])] = true;
        }
        $accCols = [];
        $ac = $conn->query("SHOW COLUMNS FROM accounts");
        if ($ac) {
            while ($r = $ac->fetch_assoc()) $accCols[strtolower($r['Field'])] = true;
        }

        $where = [];
        $params = [];
        $types = '';

        if (!empty($guideCols['status'])) {
            $where[] = "g.status = 'active'";
        }

        $accountStatusCol = null;
        if (!empty($accCols['accountstatus'])) $accountStatusCol = 'a.accountStatus';
        else if (!empty($accCols['status'])) $accountStatusCol = 'a.status';
        if ($accountStatusCol) {
            $where[] = "({$accountStatusCol} IS NULL OR {$accountStatusCol} = 'active')";
        }

        // contractStartDate/EndDate (date vs varchar 차이)
        if (!empty($guideCols['contractstartdate'])) {
            $t = strtolower((string)get_column_type($conn, 'guides', 'contractStartDate'));
            if (str_starts_with($t, 'date') || str_starts_with($t, 'datetime') || str_starts_with($t, 'timestamp')) {
                $where[] = "(g.contractStartDate IS NULL OR g.contractStartDate <= ?)";
                $params[] = $today; $types .= 's';
            } else {
                $where[] = "(g.contractStartDate IS NULL OR g.contractStartDate = '' OR g.contractStartDate <= ?)";
                $params[] = $today; $types .= 's';
            }
        }
        if (!empty($guideCols['contractenddate'])) {
            $t = strtolower((string)get_column_type($conn, 'guides', 'contractEndDate'));
            if (str_starts_with($t, 'date') || str_starts_with($t, 'datetime') || str_starts_with($t, 'timestamp')) {
                $where[] = "(g.contractEndDate IS NULL OR g.contractEndDate >= ?)";
                $params[] = $today; $types .= 's';
            } else {
                $where[] = "(g.contractEndDate IS NULL OR g.contractEndDate = '' OR g.contractEndDate >= ?)";
                $params[] = $today; $types .= 's';
            }
        }

        $whereClause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT g.guideId, g.guideName
                FROM guides g
                LEFT JOIN accounts a ON g.accountId = a.accountId
                {$whereClause}
                ORDER BY g.guideName ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare guides query: ' . $conn->error);
        if (!empty($params)) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) {
            send_error_response('Failed to get guides: ' . $conn->error);
        }
        $guides = [];
        while ($row = $res->fetch_assoc()) {
            $guides[] = [
                'guideId' => intval($row['guideId']),
                'guideName' => $row['guideName'] ?? ''
            ];
        }
        $stmt->close();
        send_success_response(['guides' => $guides]);
    } catch (Exception $e) {
        send_error_response('Failed to get guides: ' . $e->getMessage());
    }
}

function updateB2CBooking($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 환경별 스키마 편차 대응 (가이드 배정 저장용)
        __ensure_bookings_guideid_column($conn);

        // 업데이트 가능한 필드: guideId, bookingStatus(legacy), statusKey(new)
        $guideId = $input['guideId'] ?? null;
        $bookingStatus = $input['bookingStatus'] ?? null;
        $statusKey = $input['statusKey'] ?? null;

        $updates = [];
        $values = [];
        $types = '';

        if ($guideId === '' || $guideId === 'null') $guideId = null;
        if ($guideId !== null) {
            $guideId = intval($guideId);
            $updates[] = "guideId = ?";
            $values[] = $guideId;
            $types .= 'i';
        } else if (array_key_exists('guideId', $input)) {
            // 명시적으로 비우는 경우
            $updates[] = "guideId = NULL";
        }

        if ($bookingStatus !== null && $bookingStatus !== '') {
            $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
            if (!in_array($bookingStatus, $allowed, true)) {
                send_error_response('Invalid bookingStatus');
            }
            $updates[] = "bookingStatus = ?";
            $values[] = $bookingStatus;
            $types .= 's';
        }

        // New statusKey mapping to paymentStatus/bookingStatus
        if ($statusKey !== null && $statusKey !== '') {
            $map = [
                // Legacy 5-step statuses
                'payment_completed' => ['paymentStatus' => 'paid', 'bookingStatus' => 'confirmed'],
                'payment_canceled' => ['paymentStatus' => 'failed', 'bookingStatus' => 'cancelled'],
                'payment_suspended' => ['paymentStatus' => 'pending', 'bookingStatus' => 'pending'],
                'trip_completed' => ['paymentStatus' => 'paid', 'bookingStatus' => 'completed'],
                'refund_completed' => ['paymentStatus' => 'refunded', 'bookingStatus' => 'cancelled'],
                // New 11-step statuses (bookingStatus directly stored)
                'pending' => ['paymentStatus' => 'pending', 'bookingStatus' => 'pending'],
                'waiting_down_payment' => ['paymentStatus' => 'pending', 'bookingStatus' => 'waiting_down_payment'],
                'checking_down_payment' => ['paymentStatus' => 'pending', 'bookingStatus' => 'checking_down_payment'],
                'waiting_second_payment' => ['paymentStatus' => 'pending', 'bookingStatus' => 'waiting_second_payment'],
                'checking_second_payment' => ['paymentStatus' => 'pending', 'bookingStatus' => 'checking_second_payment'],
                'waiting_balance' => ['paymentStatus' => 'pending', 'bookingStatus' => 'waiting_balance'],
                'checking_balance' => ['paymentStatus' => 'pending', 'bookingStatus' => 'checking_balance'],
                'rejected' => ['paymentStatus' => 'pending', 'bookingStatus' => 'rejected'],
                'confirmed' => ['paymentStatus' => 'paid', 'bookingStatus' => 'confirmed'],
                'completed' => ['paymentStatus' => 'paid', 'bookingStatus' => 'completed'],
                'cancelled' => ['paymentStatus' => 'failed', 'bookingStatus' => 'cancelled'],
                'refunded' => ['paymentStatus' => 'refunded', 'bookingStatus' => 'refunded'],
            ];
            if (!isset($map[$statusKey])) {
                send_error_response('Invalid statusKey: ' . $statusKey);
            }
            $m = $map[$statusKey];
            $updates[] = "paymentStatus = ?";
            $values[] = $m['paymentStatus'];
            $types .= 's';
            $updates[] = "bookingStatus = ?";
            $values[] = $m['bookingStatus'];
            $types .= 's';
        }

        // SMT 수정 시작 - selectedOptions 통합 처리 (옵션 + customerInfo)
        $cabinBaggage = $input['cabinBaggage'] ?? null;
        $breakfastRequest = $input['breakfastRequest'] ?? null;
        $wifiRental = $input['wifiRental'] ?? null;
        $hasCustomerInfo = isset($input['customerInfo']) && is_array($input['customerInfo']);

        $hasOptions = ($cabinBaggage !== null || $breakfastRequest !== null || $wifiRental !== null);
        $needSelectedOptionsUpdate = ($hasOptions || $hasCustomerInfo);

        if ($needSelectedOptionsUpdate) {
            // 기존 selectedOptions 한 번만 가져오기
            $soStmt = $conn->prepare("SELECT selectedOptions FROM bookings WHERE bookingId = ?");
            $soStmt->bind_param('s', $bookingId);
            $soStmt->execute();
            $soRes = $soStmt->get_result();
            $existingRow = $soRes->fetch_assoc();
            $soStmt->close();

            $existingOptions = [];
            if ($existingRow && !empty($existingRow['selectedOptions'])) {
                $decoded = json_decode($existingRow['selectedOptions'], true);
                if (is_array($decoded)) {
                    $existingOptions = $decoded;
                }
            }

            // 옵션 값 업데이트
            if ($cabinBaggage !== null) {
                $existingOptions['cabinBaggage'] = $cabinBaggage;
                $existingOptions['extraBaggage'] = $cabinBaggage;
            }
            if ($breakfastRequest !== null) {
                $existingOptions['breakfastRequest'] = $breakfastRequest;
                $existingOptions['breakfast'] = $breakfastRequest;
            }
            if ($wifiRental !== null) {
                $existingOptions['wifiRental'] = $wifiRental;
                $existingOptions['wifi'] = $wifiRental;
            }

            // customerInfo 업데이트
            if ($hasCustomerInfo) {
                $ci = $input['customerInfo'];
                $ciName = trim((string)($ci['name'] ?? ''));
                $ciEmail = trim((string)($ci['email'] ?? ''));
                $ciPhone = trim((string)($ci['phone'] ?? ''));
                $ciCountryCode = trim((string)($ci['countryCode'] ?? '+63'));

                // bookings 테이블 컬럼 업데이트 (존재하는 경우)
                $hasContactEmail = false;
                $hasContactPhone = false;
                $hasContactName = false;
                try {
                    $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactEmail'");
                    if ($colCheck && $colCheck->num_rows > 0) $hasContactEmail = true;
                    $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactPhone'");
                    if ($colCheck && $colCheck->num_rows > 0) $hasContactPhone = true;
                    $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactName'");
                    if ($colCheck && $colCheck->num_rows > 0) $hasContactName = true;
                } catch (Throwable $e) { /* ignore */ }

                if ($hasContactEmail) {
                    $updates[] = "contactEmail = ?";
                    $values[] = $ciEmail !== '' ? $ciEmail : null;
                    $types .= 's';
                }
                if ($hasContactPhone) {
                    $fullPhone = $ciPhone;
                    if ($ciPhone !== '' && $ciCountryCode !== '' && strpos($ciPhone, $ciCountryCode) !== 0) {
                        $fullPhone = $ciCountryCode . ' ' . $ciPhone;
                    }
                    $updates[] = "contactPhone = ?";
                    $values[] = $fullPhone !== '' ? $fullPhone : null;
                    $types .= 's';
                }
                if ($hasContactName) {
                    $updates[] = "contactName = ?";
                    $values[] = $ciName !== '' ? $ciName : null;
                    $types .= 's';
                }

                // selectedOptions.customerInfo 업데이트
                $existingOptions['customerInfo'] = [
                    'name' => $ciName,
                    'email' => $ciEmail,
                    'phone' => $ciPhone,
                    'countryCode' => $ciCountryCode
                ];
            }

            // selectedOptions 한 번에 저장
            $updates[] = "selectedOptions = ?";
            $values[] = json_encode($existingOptions, JSON_UNESCAPED_UNICODE);
            $types .= 's';
        }
        // SMT 수정 완료

        if (empty($updates)) {
            send_error_response('No fields to update');
        }

        $sql = "UPDATE bookings SET " . implode(', ', $updates) . " WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare update: ' . $conn->error);
        }

        if (!empty($values)) {
            $values[] = $bookingId;
            $types .= 's';
            mysqli_bind_params_by_ref($stmt, $types, $values);
        } else {
            // guideId=NULL만 있는 케이스
            $stmt->bind_param('s', $bookingId);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to update booking: ' . $err);
        }
        $stmt->close();

        // SMT 수정 시작 - travelers 업데이트
        if (isset($input['travelers']) && is_array($input['travelers'])) {
            foreach ($input['travelers'] as $tr) {
                if (!is_array($tr)) continue;
                $tid = intval($tr['bookingTravelerId'] ?? 0);
                if ($tid <= 0) continue;

                $tUpdates = [];
                $tValues = [];
                $tTypes = '';

                // isMainTraveler
                if (isset($tr['isMainTraveler'])) {
                    $tUpdates[] = "isMainTraveler = ?";
                    $tValues[] = ($tr['isMainTraveler'] === '1' || $tr['isMainTraveler'] === 1) ? 1 : 0;
                    $tTypes .= 'i';
                }

                // reservationStatus
                if (isset($tr['reservationStatus'])) {
                    $tUpdates[] = "reservationStatus = ?";
                    $tValues[] = $tr['reservationStatus'];
                    $tTypes .= 's';
                }

                // visaStatus (enum: not_required, applied, approved, rejected)
                if (isset($tr['visaStatus'])) {
                    $allowedVisa = ['not_required', 'applied', 'approved', 'rejected'];
                    $visaVal = in_array($tr['visaStatus'], $allowedVisa) ? $tr['visaStatus'] : 'not_required';
                    $tUpdates[] = "visaStatus = ?";
                    $tValues[] = $visaVal;
                    $tTypes .= 's';
                }

                // title (enum: MR, MRS, MS, DR)
                if (isset($tr['title'])) {
                    $allowedTitle = ['MR', 'MRS', 'MS', 'DR'];
                    $titleVal = strtoupper($tr['title']);
                    $titleVal = in_array($titleVal, $allowedTitle) ? $titleVal : 'MR';
                    $tUpdates[] = "title = ?";
                    $tValues[] = $titleVal;
                    $tTypes .= 's';
                }

                // firstName
                if (isset($tr['firstName'])) {
                    $tUpdates[] = "firstName = ?";
                    $tValues[] = $tr['firstName'];
                    $tTypes .= 's';
                }

                // lastName
                if (isset($tr['lastName'])) {
                    $tUpdates[] = "lastName = ?";
                    $tValues[] = $tr['lastName'];
                    $tTypes .= 's';
                }

                // gender
                if (isset($tr['gender'])) {
                    $tUpdates[] = "gender = ?";
                    $tValues[] = $tr['gender'];
                    $tTypes .= 's';
                }

                // birthDate
                if (isset($tr['birthDate'])) {
                    $tUpdates[] = "birthDate = ?";
                    $tValues[] = $tr['birthDate'] ?: null;
                    $tTypes .= 's';
                }

                // nationality
                if (isset($tr['nationality'])) {
                    $tUpdates[] = "nationality = ?";
                    $tValues[] = $tr['nationality'];
                    $tTypes .= 's';
                }

                // passportNumber
                if (isset($tr['passportNumber'])) {
                    $tUpdates[] = "passportNumber = ?";
                    $tValues[] = $tr['passportNumber'];
                    $tTypes .= 's';
                }

                // passportIssueDate
                if (isset($tr['passportIssueDate'])) {
                    $tUpdates[] = "passportIssueDate = ?";
                    $tValues[] = $tr['passportIssueDate'] ?: null;
                    $tTypes .= 's';
                }

                // passportExpiry
                if (isset($tr['passportExpiry'])) {
                    $tUpdates[] = "passportExpiry = ?";
                    $tValues[] = $tr['passportExpiry'] ?: null;
                    $tTypes .= 's';
                }

                if (!empty($tUpdates)) {
                    $tSql = "UPDATE booking_travelers SET " . implode(', ', $tUpdates) . " WHERE bookingTravelerId = ?";
                    $tStmt = $conn->prepare($tSql);
                    if ($tStmt) {
                        $tValues[] = $tid;
                        $tTypes .= 'i';
                        mysqli_bind_params_by_ref($tStmt, $tTypes, $tValues);
                        $tStmt->execute();
                        $tStmt->close();
                    }
                }
            }
        }
        // SMT 수정 완료

        send_success_response([], 'Booking updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update booking: ' . $e->getMessage());
    }
}

function downloadTravelerPassport($conn, $input) {
    try {
        $bookingTravelerId = $input['bookingTravelerId'] ?? $input['id'] ?? null;
        if (empty($bookingTravelerId)) send_error_response('bookingTravelerId is required');
        $tid = intval($bookingTravelerId);

        $stmt = $conn->prepare("SELECT passportImage FROM booking_travelers WHERE bookingTravelerId = ? LIMIT 1");
        if (!$stmt) send_error_response('Failed to prepare query: ' . $conn->error, 500);
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $rel = (string)($row['passportImage'] ?? '');
        $rel = trim($rel);
        if ($rel === '') send_error_response('Passport image not found', 404);

        // normalize path:
        // - allow "/uploads/..." or "uploads/..."
        // - if URL is stored, use its path component
        if (preg_match('#^https?://#i', $rel)) {
            $p = parse_url($rel, PHP_URL_PATH);
            if (is_string($p) && $p !== '') $rel = $p;
        }
        $rel = str_replace('\\', '/', $rel);
        if (str_starts_with($rel, 'uploads/')) $rel = '/' . $rel;
        if (!str_starts_with($rel, '/uploads/')) send_error_response('Invalid file path', 400);

        $abs = __DIR__ . '/../../../' . ltrim($rel, '/');
        if (!is_file($abs)) send_error_response('File missing on server', 404);

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download passport: ' . $e->getMessage(), 500);
    }
}

function deleteTravelerPassportImage($conn, $input) {
    try {
        $bookingTravelerId = $input['bookingTravelerId'] ?? $input['id'] ?? null;
        if (empty($bookingTravelerId)) send_error_response('bookingTravelerId is required');
        $tid = intval($bookingTravelerId);

        $stmt = $conn->prepare("SELECT passportImage FROM booking_travelers WHERE bookingTravelerId = ? LIMIT 1");
        if (!$stmt) send_error_response('Failed to prepare query: ' . $conn->error, 500);
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $rel = $row['passportImage'] ?? '';
        if ($rel !== '' && str_starts_with($rel, '/uploads/')) {
            $abs = __DIR__ . '/../../../' . ltrim($rel, '/');
            if (is_file($abs)) @unlink($abs);
        }

        $up = $conn->prepare("UPDATE booking_travelers SET passportImage = NULL WHERE bookingTravelerId = ?");
        if (!$up) send_error_response('Failed to prepare update: ' . $conn->error, 500);
        $up->bind_param('i', $tid);
        $up->execute();
        $up->close();

        send_success_response([], 'Passport image deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete passport: ' . $e->getMessage(), 500);
    }
}

function uploadTravelerPassportImage($conn, $input) {
    try {
        // Super admin only
        $isSuperAdmin = __is_super_admin($conn);
        if (!$isSuperAdmin) send_error_response('Forbidden', 403);

        // multipart/form-data expected
        $bookingTravelerId = $_POST['bookingTravelerId'] ?? ($input['bookingTravelerId'] ?? $input['id'] ?? null);
        $tid = !empty($bookingTravelerId) ? intval($bookingTravelerId) : 0;

        if (!isset($_FILES['passportImage']) || ($_FILES['passportImage']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            send_error_response('No passport image uploaded', 400);
        }

        $file = $_FILES['passportImage'];

        // MIME validate (images only)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
        if ($finfo) finfo_close($finfo);
        if (!in_array($mimeType, $allowedTypes, true)) {
            send_error_response('Invalid file type. Only JPG, PNG, GIF are allowed.', 400);
        }

        // size limit 10MB
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            send_error_response('File size exceeds 10MB limit', 400);
        }

        // delete old file if any (only if bookingTravelerId exists)
        if ($tid > 0) {
            $oldRel = '';
            $stmt = $conn->prepare("SELECT passportImage FROM booking_travelers WHERE bookingTravelerId = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $tid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $oldRel = (string)($row['passportImage'] ?? '');
            }
            if ($oldRel !== '' && str_starts_with($oldRel, '/uploads/')) {
                $oldAbs = __DIR__ . '/../../../' . ltrim($oldRel, '/');
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
        }

        $uploadDir = __DIR__ . '/../../../uploads/passports/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                send_error_response('Failed to create upload directory', 500);
            }
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($ext === '') {
            $ext = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/gif') ? 'gif' : 'jpg');
        }
        // 파일명: bookingTravelerId가 있으면 사용, 없으면 'new_' + timestamp
        $prefix = ($tid > 0) ? 'traveler_passport_' . $tid : 'traveler_passport_new';
        $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            send_error_response('Failed to save uploaded file', 500);
        }

        $rel = '/uploads/passports/' . $fileName;

        // bookingTravelerId가 있으면 DB 업데이트
        if ($tid > 0) {
            $up = $conn->prepare("UPDATE booking_travelers SET passportImage = ? WHERE bookingTravelerId = ?");
            if ($up) {
                $up->bind_param('si', $rel, $tid);
                $up->execute();
                $up->close();
            }
        }

        send_success_response(['filePath' => $rel, 'path' => $rel], 'Passport image uploaded');
    } catch (Exception $e) {
        send_error_response('Failed to upload passport image: ' . $e->getMessage(), 500);
    }
}

// Visa Document 업로드 (Edit Traveler 모달에서 사용)
function uploadTravelerVisaDocument($conn, $input) {
    try {
        // Super admin only
        $isSuperAdmin = __is_super_admin($conn);
        if (!$isSuperAdmin) send_error_response('Forbidden', 403);

        // multipart/form-data expected
        $bookingTravelerId = $_POST['bookingTravelerId'] ?? ($input['bookingTravelerId'] ?? $input['id'] ?? null);
        $tid = !empty($bookingTravelerId) ? intval($bookingTravelerId) : 0;

        if (!isset($_FILES['visaDocument']) || ($_FILES['visaDocument']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            send_error_response('No visa document uploaded', 400);
        }

        $file = $_FILES['visaDocument'];

        // MIME validate (images + PDF)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
        if ($finfo) finfo_close($finfo);
        if (!in_array($mimeType, $allowedTypes, true)) {
            send_error_response('Invalid file type. Only JPG, PNG, GIF, PDF are allowed.', 400);
        }

        // size limit 10MB
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            send_error_response('File size exceeds 10MB limit', 400);
        }

        // delete old file if any (only if bookingTravelerId exists)
        if ($tid > 0) {
            $oldRel = '';
            $stmt = $conn->prepare("SELECT visaDocument FROM booking_travelers WHERE bookingTravelerId = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $tid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $oldRel = (string)($row['visaDocument'] ?? '');
            }
            if ($oldRel !== '' && str_starts_with($oldRel, '/uploads/')) {
                $oldAbs = __DIR__ . '/../../../' . ltrim($oldRel, '/');
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
        }

        $uploadDir = __DIR__ . '/../../../uploads/visa/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                send_error_response('Failed to create upload directory', 500);
            }
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($ext === '') {
            if ($mimeType === 'application/pdf') {
                $ext = 'pdf';
            } else {
                $ext = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/gif') ? 'gif' : 'jpg');
            }
        }
        // 파일명: bookingTravelerId가 있으면 사용, 없으면 'new_' + timestamp
        $prefix = ($tid > 0) ? 'traveler_visa_' . $tid : 'traveler_visa_new';
        $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            send_error_response('Failed to save uploaded file', 500);
        }

        $rel = '/uploads/visa/' . $fileName;

        // bookingTravelerId가 있으면 DB 업데이트
        if ($tid > 0) {
            $up = $conn->prepare("UPDATE booking_travelers SET visaDocument = ? WHERE bookingTravelerId = ?");
            if ($up) {
                $up->bind_param('si', $rel, $tid);
                $up->execute();
                $up->close();
            }
        }

        send_success_response(['filePath' => $rel, 'path' => $rel], 'Visa document uploaded');
    } catch (Exception $e) {
        send_error_response('Failed to upload visa document: ' . $e->getMessage(), 500);
    }
}

// ========== 고객 관리 함수들 (B2B/B2C) ==========

function getB2BCustomers($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // B2B 정책: client.clientType = 'Wholeseller' 기반
        $whereConditions = [
            "ac.accountType = 'guest'",
            "LOWER(COALESCE(c.clientType,'')) IN ('wholeseller','wholesaler')"
        ];
        $params = [];
        $types = '';

        if (!empty($input['search'])) {
            $searchType = $input['searchType'] ?? 'all'; // all/customer/branch
            $term = '%' . $input['search'] . '%';
            if ($searchType === 'customer') {
                $whereConditions[] = "CONCAT(c.fName, ' ', c.lName) LIKE ?";
                $params[] = $term;
                $types .= 's';
            } elseif ($searchType === 'branch') {
                $whereConditions[] = "(COALESCE('', '', '') LIKE ? OR COALESCE('' as co_ag_companyName, '' as co_cl_companyName, '') LIKE ?)";
                $params[] = $term;
                $params[] = $term;
                $types .= 'ss';
            } else {
                $whereConditions[] = "(
                    CONCAT(c.fName, ' ', c.lName) LIKE ?
                    OR COALESCE('', '', '') LIKE ?
                    OR COALESCE('' as co_ag_companyName, '' as co_cl_companyName, '') LIKE ?
                )";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $types .= 'sss';
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $countSql = "SELECT COUNT(*) as total FROM client c
                     LEFT JOIN accounts ac ON c.accountId = ac.accountId
                     $whereClause";

        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();

        $dataSql = "SELECT
            c.accountId,
            c.companyId,
            CONCAT(c.fName, ' ', c.lName) as customerName,
            ac.emailAddress,
            c.contactNo,
            '' as companyName,
            '' as branchName,
            '' as businessUnit,
            ac.accountStatus,
            COALESCE(ac.createdAt, c.updatedAt) as createdAt
        FROM client c
        LEFT JOIN accounts ac ON c.accountId = ac.accountId
        $whereClause
        ORDER BY COALESCE(ac.createdAt, c.updatedAt) DESC
        LIMIT ? OFFSET ?";

        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';

        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        $customers = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $bn = trim((string)($row['branchName'] ?? ''));
            $cn = trim((string)($row['companyName'] ?? ''));
            $bu = trim((string)($row['businessUnit'] ?? ''));
            $agentLabel = $bn;
            if ($agentLabel === '' && $cn !== '') $agentLabel = $cn;
            if ($agentLabel === '' && $bu !== '') $agentLabel = $bu;

            $customers[] = [
                'accountId' => $row['accountId'],
                'companyId' => $row['companyId'] ?? null,
                'customerName' => $row['customerName'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'contactNo' => $row['contactNo'] ?? '',
                'companyName' => $row['companyName'] ?? '',
                'branchName' => $agentLabel,
                'status' => $row['accountStatus'] ?? 'active',
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'customers' => $customers,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get B2B customers: ' . $e->getMessage());
    }
}

function getB2CCustomers($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // B2C 정책: clientType이 wholeseller가 아니면 B2C
        $whereConditions = [
            "ac.accountType = 'guest'",
            "(LOWER(COALESCE(c.clientType,'')) NOT IN ('wholeseller','wholesaler'))"
        ];
        $params = [];
        $types = '';

        if (!empty($input['search'])) {
            $whereConditions[] = "CONCAT(c.fName, ' ', c.lName) LIKE ?";
            $params[] = '%' . $input['search'] . '%';
            $types .= 's';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $countSql = "SELECT COUNT(*) as total FROM client c
                     LEFT JOIN accounts ac ON c.accountId = ac.accountId
                     $whereClause";

        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();

        $dataSql = "SELECT
            c.accountId,
            CONCAT(c.fName, ' ', c.lName) as customerName,
            ac.emailAddress,
            c.contactNo,
            ac.accountStatus,
            ac.createdAt
        FROM client c
        LEFT JOIN accounts ac ON c.accountId = ac.accountId
        $whereClause
        ORDER BY ac.createdAt DESC
        LIMIT ? OFFSET ?";

        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';

        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        $customers = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $customers[] = [
                'accountId' => $row['accountId'],
                'customerName' => $row['customerName'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'contactNo' => $row['contactNo'] ?? '',
                'status' => $row['accountStatus'] ?? 'active',
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'customers' => $customers,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get B2C customers: ' . $e->getMessage());
    }
}

function getCustomerDetail($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) {
            send_error_response('Account ID is required');
        }

        $sql = "SELECT
            c.*,
            ac.emailAddress,
            ac.accountStatus,
            ac.createdAt as accountCreatedAt,
            '' as companyName,
            '' as businessUnit,
            '' as branchName
        FROM client c
        LEFT JOIN accounts ac ON c.accountId = ac.accountId
        WHERE c.accountId = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Customer not found', 404);
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();

        // field normalize helpers (단건/일괄 등록/레거시 스키마 차이 대응)
        $pick = function(array $r, array $keys): string {
            foreach ($keys as $k) {
                if (!array_key_exists($k, $r)) continue;
                $v = $r[$k];
                if ($v === null) continue;
                $s = trim((string)$v);
                if ($s !== '') return $s;
            }
            return '';
        };
        $normalizePassportImagePath = function(string $path): string {
            $p = trim($path);
            if ($p === '') return '';
            // data url / absolute url passthrough
            if (preg_match('/^(data:|https?:\/\/)/i', $p)) return $p;
            $p = str_replace('\\', '/', $p);
            // smart-travel2 prefix cleanup
            $p = str_replace('/smart-travel2/', '/', $p);
            $p = str_replace('smart-travel2/', '', $p);
            // uploads/uploads cleanup
            $p = preg_replace('#/uploads/uploads/#', '/uploads/', $p);
            $p = preg_replace('#^uploads/uploads/#', 'uploads/', $p);
            // filename-only legacy: assume uploads/passports/
            if (strpos($p, '/') === false) $p = 'uploads/passports/' . $p;
            // ensure leading slash
            if ($p !== '' && $p[0] !== '/') $p = '/' . $p;
            return $p;
        };
        $computeAge = function(string $dob): ?int {
            $s = preg_replace('/[^0-9]/', '', $dob);
            if (strlen($s) !== 8) return null;
            $y = (int)substr($s, 0, 4);
            $m = (int)substr($s, 4, 2);
            $d = (int)substr($s, 6, 2);
            if ($y < 1900 || $m < 1 || $m > 12 || $d < 1 || $d > 31) return null;
            try {
                $birth = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $d));
                $today = new DateTime('today');
                $age = (int)$birth->diff($today)->y;
                return ($age >= 0 && $age <= 130) ? $age : null;
            } catch (Throwable $e) {
                return null;
            }
        };

        $dateOfBirth = $pick($row, ['dateOfBirth', 'birthDate', 'birth', 'dob', 'travelerBirth']);
        $passportNumber = $pick($row, ['passportNumber', 'passportNo', 'passport_no']);
        $passportIssueDate = $pick($row, ['passportIssueDate', 'passportIssuedDate', 'passportIssue', 'passport_issue', 'passportIssueDt']);
        $passportExpiry = $pick($row, ['passportExpiry', 'passportExpiryDate', 'passportExpire', 'passportExpiredDate', 'passport_expire', 'passportExp']);
        // Prefer stored age if exists, otherwise compute from DOB (dev_tasks #106 follow-up)
        $age = null;
        try {
            $rawAge = $pick($row, ['age']);
            if ($rawAge !== '' && preg_match('/^[0-9]{1,3}$/', $rawAge)) $age = (int)$rawAge;
        } catch (Throwable $e) { /* ignore */ }
        if ($age === null) $age = $computeAge($dateOfBirth);
        $passportImage = $normalizePassportImagePath($pick($row, ['profileImage', 'passportImage', 'passportPhoto', 'passport_photo']));

        // traveler name 분리: travelerFirstName/LastName 컬럼이 있으면 그것을 사용
        $travelerFirstName = $pick($row, ['travelerFirstName', 'traveler_first_name', 'travelerFName', 'traveler_firstname']);
        $travelerLastName = $pick($row, ['travelerLastName', 'traveler_last_name', 'travelerLName', 'traveler_lastname']);

        // B2B/B2C 판별: clientType 기반
        $ct = strtolower(trim((string)($row['clientType'] ?? '')));
        $isB2B = in_array($ct, ['wholeseller','wholesaler'], true);
        $companyName = trim((string)($row['companyName'] ?? ''));
        $businessUnit = trim((string)($row['businessUnit'] ?? ''));
        $branchName = trim((string)($row['branchName'] ?? ''));
        if (!$isB2B) {
            $companyName = '';
            $businessUnit = '';
            $branchName = '';
        }
        // B2B는 "Agent Name"이 비어있으면 안 됨(운영 요구사항):
        // - 우선순위: branchName → companyName → businessUnit
        if ($isB2B) {
            $agentLabel = $branchName;
            if ($agentLabel === '' && $companyName !== '') $agentLabel = $companyName;
            if ($agentLabel === '' && $businessUnit !== '') $agentLabel = $businessUnit;
            $branchName = $agentLabel;
        }
        
        $customer = [
            'accountId' => intval($row['accountId']),
            'customerNo' => $row['clientId'] ?? '',
            'customerName' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
            'fName' => $row['fName'] ?? '',
            'lName' => $row['lName'] ?? '',
            'travelerFirstName' => $travelerFirstName,
            'travelerLastName' => $travelerLastName,
            'emailAddress' => $row['emailAddress'] ?? ($row['email'] ?? ''),
            'countryCode' => $row['countryCode'] ?? '+63',
            'contactNo' => $row['contactNo'] ?? '',
            'companyId' => $row['companyId'] ?? null,
            'companyName' => $companyName,
            'branchName' => $branchName,
            'accountStatus' => $row['accountStatus'] ?? '',
            'registrationDate' => $row['accountCreatedAt'] ?? ($row['updatedAt'] ?? ''),
            'memo' => $row['memo'] ?? '',
            // traveler/passport fields
            'title' => $row['title'] ?? '',
            'gender' => $row['gender'] ?? '',
            'age' => ($age === null ? '' : $age),
            'dateOfBirth' => $dateOfBirth,
            'nationality' => $pick($row, ['nationality', 'countryOfOrigin']),
            'passportNumber' => $passportNumber,
            'passportIssueDate' => $passportIssueDate,
            'passportExpiry' => $passportExpiry,
            // passport image (reuse profileImage column)
            'passportImage' => $passportImage
        ];

        send_success_response(['customer' => $customer]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer detail: ' . $e->getMessage());
    }
}

function updateB2BCustomer($conn, $input) {
    try {
        // Support multipart/form-data and JSON
        $accountId = $_POST['accountId'] ?? $_POST['id'] ?? ($input['accountId'] ?? $input['id'] ?? null);
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $hasKey = function(string $k) use ($input): bool {
            if (array_key_exists($k, $_POST)) return true;
            return (is_array($input) && array_key_exists($k, $input));
        };

        $fName = trim($_POST['fName'] ?? ($input['fName'] ?? ''));
        $lName = trim($_POST['lName'] ?? ($input['lName'] ?? ''));
        $countryCode = trim($_POST['countryCode'] ?? ($input['countryCode'] ?? ''));
        $contactNo = trim($_POST['contactNo'] ?? ($input['contactNo'] ?? ''));
        $emailAddress = trim($_POST['emailAddress'] ?? ($input['emailAddress'] ?? ''));
        $memo = $_POST['memo'] ?? ($input['memo'] ?? null);
        if ($memo !== null) $memo = trim((string)$memo);

        // traveler/passport fields (optional)
        // NOTE: travelerFirstName/LastName are stored separately from client.fName/lName (dev_tasks #106)
        $travelerFirstName = trim($_POST['travelerFirstName'] ?? $_POST['travelerFName'] ?? ($input['travelerFirstName'] ?? $input['travelerFName'] ?? ''));
        $travelerLastName  = trim($_POST['travelerLastName']  ?? $_POST['travelerLName'] ?? ($input['travelerLastName']  ?? $input['travelerLName'] ?? ''));
        $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $gender = trim($_POST['gender'] ?? ($input['gender'] ?? ''));
        $ageRaw = trim($_POST['age'] ?? ($input['age'] ?? ''));
        $dateOfBirth = trim($_POST['dateOfBirth'] ?? ($input['dateOfBirth'] ?? ''));
        $nationality = trim($_POST['nationality'] ?? ($input['nationality'] ?? ''));
        $passportNumber = trim($_POST['passportNumber'] ?? ($input['passportNumber'] ?? ''));
        $passportIssueDate = trim($_POST['passportIssueDate'] ?? ($input['passportIssueDate'] ?? ''));
        $passportExpiry = trim($_POST['passportExpiry'] ?? ($input['passportExpiry'] ?? ''));

        if ($fName === '' || $contactNo === '' || $emailAddress === '') {
            send_error_response('Required fields are missing');
        }
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            send_error_response('Invalid email');
        }

        // passport image upload (store to client.profileImage)
        $passportImagePath = null;
        if (isset($_FILES['passportImage']) && isset($_FILES['passportImage']['tmp_name']) && is_uploaded_file($_FILES['passportImage']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $ext = pathinfo($_FILES['passportImage']['name'] ?? '', PATHINFO_EXTENSION);
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            if ($ext === '') $ext = 'jpg';
            $fileName = 'passport_' . $accountId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['passportImage']['tmp_name'], $dest)) {
                send_error_response('Failed to upload passport image', 500);
            }
            $passportImagePath = '/uploads/passports/' . $fileName;
        }

        // update accounts email
        $a = $conn->prepare("UPDATE accounts SET emailAddress = ?, updatedAt = NOW() WHERE accountId = ?");
        if (!$a) send_error_response('Failed to prepare account update: ' . $conn->error);
        $a->bind_param('si', $emailAddress, $accountId);
        if (!$a->execute()) {
            $err = $a->error ?: $conn->error;
            $a->close();
            send_error_response('Failed to update customer: ' . $err);
        }
        $a->close();

        // Ensure client.age column exists if we are asked to save age (dev_tasks #106 follow-up)
        $hasAgeCol = false;
        try { $hasAgeCol = __table_has_column($conn, 'client', 'age'); } catch (Throwable $e) { $hasAgeCol = false; }
        if (!$hasAgeCol && $hasKey('age')) {
            try { $conn->query("ALTER TABLE client ADD COLUMN age INT NULL"); } catch (Throwable $e) { /* ignore */ }
            try { $hasAgeCol = __table_has_column($conn, 'client', 'age'); } catch (Throwable $e) { $hasAgeCol = false; }
        }

        // build client update
        $fields = ["fName = ?", "lName = ?", "countryCode = ?", "contactNo = ?", "emailAddress = ?"];
        $vals = [$fName, $lName, ($countryCode !== '' ? $countryCode : '+63'), $contactNo, $emailAddress];
        $types = 'sssss';

        if ($memo !== null) { $fields[] = "memo = ?"; $vals[] = $memo; $types .= 's'; }

        // traveler name: allow clearing if key exists (dev_tasks #106)
        if ($hasKey('travelerFirstName') || $hasKey('travelerFName')) {
            $fields[] = "travelerFirstName = ?";
            $vals[] = ($travelerFirstName !== '' ? $travelerFirstName : null);
            $types .= 's';
        }
        if ($hasKey('travelerLastName') || $hasKey('travelerLName')) {
            $fields[] = "travelerLastName = ?";
            $vals[] = ($travelerLastName !== '' ? $travelerLastName : null);
            $types .= 's';
        }

        // Allow clearing fields: if key exists, write value (or NULL) even if empty
        if ($hasKey('title')) {
            if ($title === '') $fields[] = "title = NULL";
            else { $fields[] = "title = ?"; $vals[] = $title; $types .= 's'; }
        }
        if ($hasKey('gender')) {
            if ($gender === '') $fields[] = "gender = NULL";
            else { $fields[] = "gender = ?"; $vals[] = $gender; $types .= 's'; }
        }
        if ($hasAgeCol && $hasKey('age')) {
            $ageDigits = preg_replace('/[^0-9]/', '', $ageRaw);
            if ($ageDigits === '') {
                $fields[] = "age = NULL";
            } else {
                $ageNum = max(0, min(130, (int)$ageDigits));
                $fields[] = "age = ?";
                $vals[] = $ageNum;
                $types .= 'i';
            }
        }
        if ($hasKey('dateOfBirth')) {
            if ($dateOfBirth === '') $fields[] = "dateOfBirth = NULL";
            else { $fields[] = "dateOfBirth = ?"; $vals[] = $dateOfBirth; $types .= 's'; }
        }
        if ($hasKey('nationality')) {
            if ($nationality === '') $fields[] = "nationality = NULL";
            else { $fields[] = "nationality = ?"; $vals[] = $nationality; $types .= 's'; }
        }
        if ($hasKey('passportNumber')) {
            if ($passportNumber === '') $fields[] = "passportNumber = NULL";
            else { $fields[] = "passportNumber = ?"; $vals[] = $passportNumber; $types .= 's'; }
        }
        if ($hasKey('passportIssueDate')) {
            if ($passportIssueDate === '') $fields[] = "passportIssueDate = NULL";
            else { $fields[] = "passportIssueDate = ?"; $vals[] = $passportIssueDate; $types .= 's'; }
        }
        if ($hasKey('passportExpiry')) {
            if ($passportExpiry === '') $fields[] = "passportExpiry = NULL";
            else { $fields[] = "passportExpiry = ?"; $vals[] = $passportExpiry; $types .= 's'; }
        }
        if ($passportImagePath !== null) { $fields[] = "profileImage = ?"; $vals[] = $passportImagePath; $types .= 's'; }

        $vals[] = $accountId; $types .= 'i';
        $sql = "UPDATE client SET " . implode(', ', $fields) . ", updatedAt = NOW() WHERE accountId = ?";
        $c = $conn->prepare($sql);
        if (!$c) send_error_response('Failed to prepare client update: ' . $conn->error);
        mysqli_bind_params_by_ref($c, $types, $vals);
        if (!$c->execute()) {
            $err = $c->error ?: $conn->error;
            $c->close();
            send_error_response('Failed to update customer: ' . $err);
        }
        $c->close();

        // Return passport path so UI can reflect immediately without re-enter (dev_tasks #109)
        $data = [];
        if ($passportImagePath !== null) $data['passportImage'] = $passportImagePath;
        send_success_response($data, 'Customer updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update customer: ' . $e->getMessage());
    }
}

function generateTempPassword($minLen = 8, $maxLen = 12) {
    $len = random_int($minLen, $maxLen);
    $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    // ensure at least one of each
    $chars = [
        $letters[random_int(0, strlen($letters)-1)],
        $numbers[random_int(0, strlen($numbers)-1)],
        $symbols[random_int(0, strlen($symbols)-1)],
    ];
    $all = $letters . $numbers . $symbols;
    while (count($chars) < $len) {
        $chars[] = $all[random_int(0, strlen($all)-1)];
    }
    shuffle($chars);
    return implode('', $chars);
}

function resetCustomerPassword($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? $_POST['accountId'] ?? $_POST['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $tempPassword = generateTempPassword(8, 12);
        $hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

        // accounts.defaultPasswordStat enum('yes','no')
        $stmt = $conn->prepare("UPDATE accounts SET password = ?, defaultPasswordStat = 'yes', updatedAt = NOW() WHERE accountId = ?");
        if (!$stmt) send_error_response('Failed to prepare password reset: ' . $conn->error);
        $stmt->bind_param('si', $hashed, $accountId);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to reset password: ' . $err, 500);
        }
        $stmt->close();

        send_success_response(['tempPassword' => $tempPassword], 'Password reset successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reset password: ' . $e->getMessage(), 500);
    }
}

function resetAccountPassword($conn, $input) {
    // agent/guide 등 accounts 기반 계정 공통 비밀번호 초기화
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? $_POST['accountId'] ?? $_POST['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $tempPassword = generateTempPassword(8, 12);
        $hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

        // accounts.defaultPasswordStat enum('yes','no')
        $stmt = $conn->prepare("UPDATE accounts SET password = ?, defaultPasswordStat = 'yes', updatedAt = NOW() WHERE accountId = ?");
        if (!$stmt) send_error_response('Failed to prepare password reset: ' . $conn->error);
        $stmt->bind_param('si', $hashed, $accountId);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to reset password: ' . $err, 500);
        }
        $stmt->close();

        send_success_response(['tempPassword' => $tempPassword], 'Password reset successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reset password: ' . $e->getMessage(), 500);
    }
}

function deleteCustomerPassportImage($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $stmt = $conn->prepare("SELECT profileImage FROM client WHERE accountId = ? LIMIT 1");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $path = $row['profileImage'] ?? '';
        if ($path) {
            $abs = realpath(__DIR__ . '/../../../' . ltrim($path, '/'));
            if ($abs && strpos($abs, realpath(__DIR__ . '/../../../uploads/')) === 0 && file_exists($abs)) {
                @unlink($abs);
            }
        }

        $u = $conn->prepare("UPDATE client SET profileImage = NULL, updatedAt = NOW() WHERE accountId = ?");
        $u->bind_param('i', $accountId);
        $u->execute();
        $u->close();

        send_success_response([], 'Passport image deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete passport image: ' . $e->getMessage(), 500);
    }
}

function updateB2CCustomer($conn, $input) {
    try {
        // Support multipart/form-data and JSON
        $accountId = $_POST['accountId'] ?? $_POST['id'] ?? ($input['accountId'] ?? $input['id'] ?? null);
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $fName = trim($_POST['fName'] ?? ($input['fName'] ?? ''));
        $lName = trim($_POST['lName'] ?? ($input['lName'] ?? ''));
        $countryCode = trim($_POST['countryCode'] ?? ($input['countryCode'] ?? ''));
        $contactNo = trim($_POST['contactNo'] ?? ($input['contactNo'] ?? ''));
        $emailAddress = trim($_POST['emailAddress'] ?? ($input['emailAddress'] ?? ''));
        $memo = $_POST['memo'] ?? ($input['memo'] ?? null);
        if ($memo !== null) $memo = trim((string)$memo);

        // traveler/passport fields (optional)
        $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $gender = trim($_POST['gender'] ?? ($input['gender'] ?? ''));
        $dateOfBirth = trim($_POST['dateOfBirth'] ?? ($input['dateOfBirth'] ?? ''));
        $nationality = trim($_POST['nationality'] ?? ($input['nationality'] ?? ''));
        $passportNumber = trim($_POST['passportNumber'] ?? ($input['passportNumber'] ?? ''));
        $passportIssueDate = trim($_POST['passportIssueDate'] ?? ($input['passportIssueDate'] ?? ''));
        $passportExpiry = trim($_POST['passportExpiry'] ?? ($input['passportExpiry'] ?? ''));

        if ($fName === '' || $contactNo === '' || $emailAddress === '') {
            send_error_response('Required fields are missing');
        }
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            send_error_response('Invalid email');
        }

        // passport image upload (store to client.profileImage)
        $passportImagePath = null;
        if (isset($_FILES['passportImage']) && isset($_FILES['passportImage']['tmp_name']) && is_uploaded_file($_FILES['passportImage']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $ext = pathinfo($_FILES['passportImage']['name'] ?? '', PATHINFO_EXTENSION);
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            if ($ext === '') $ext = 'jpg';
            $fileName = 'passport_' . $accountId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['passportImage']['tmp_name'], $dest)) {
                send_error_response('Failed to upload passport image', 500);
            }
            $passportImagePath = '/uploads/passports/' . $fileName;
        }

        // accounts 이메일 업데이트 (중복 체크: 본인 제외)
        $dup = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ? AND accountId <> ?");
        if ($dup) {
            $dup->bind_param('si', $emailAddress, $accountId);
            $dup->execute();
            $dupRes = $dup->get_result();
            if ($dupRes && $dupRes->num_rows > 0) {
                $dup->close();
                send_error_response('이미 사용 중인 이메일입니다.');
            }
            $dup->close();
        }

        $accStmt = $conn->prepare("UPDATE accounts SET emailAddress = ?, updatedAt = NOW() WHERE accountId = ?");
        if (!$accStmt) send_error_response('Failed to prepare accounts update: ' . $conn->error);
        $accStmt->bind_param('si', $emailAddress, $accountId);
        if (!$accStmt->execute()) {
            $err = $accStmt->error ?: $conn->error;
            $accStmt->close();
            send_error_response('Failed to update account: ' . $err);
        }
        $accStmt->close();

        // build client update
        $fields = ["fName = ?", "lName = ?", "countryCode = ?", "contactNo = ?", "emailAddress = ?"];
        $vals = [$fName, $lName, ($countryCode !== '' ? $countryCode : '+63'), $contactNo, $emailAddress];
        $types = 'sssss';

        if ($memo !== null) { $fields[] = "memo = ?"; $vals[] = $memo; $types .= 's'; }
        if ($title !== '') { $fields[] = "title = ?"; $vals[] = $title; $types .= 's'; }
        if ($gender !== '') { $fields[] = "gender = ?"; $vals[] = $gender; $types .= 's'; }
        if ($dateOfBirth !== '') { $fields[] = "dateOfBirth = ?"; $vals[] = $dateOfBirth; $types .= 's'; }
        if ($nationality !== '') { $fields[] = "nationality = ?"; $vals[] = $nationality; $types .= 's'; }
        if ($passportNumber !== '') { $fields[] = "passportNumber = ?"; $vals[] = $passportNumber; $types .= 's'; }
        if ($passportIssueDate !== '') { $fields[] = "passportIssueDate = ?"; $vals[] = $passportIssueDate; $types .= 's'; }
        if ($passportExpiry !== '') { $fields[] = "passportExpiry = ?"; $vals[] = $passportExpiry; $types .= 's'; }
        if ($passportImagePath !== null) { $fields[] = "profileImage = ?"; $vals[] = $passportImagePath; $types .= 's'; }

        $vals[] = $accountId; $types .= 'i';
        $sql = "UPDATE client SET " . implode(', ', $fields) . ", updatedAt = NOW() WHERE accountId = ?";
        $cStmt = $conn->prepare($sql);
        if (!$cStmt) send_error_response('Failed to prepare client update: ' . $conn->error);
        mysqli_bind_params_by_ref($cStmt, $types, $vals);
        if (!$cStmt->execute()) {
            $err = $cStmt->error ?: $conn->error;
            $cStmt->close();
            send_error_response('Failed to update customer: ' . $err);
        }
        $cStmt->close();

        send_success_response([], 'Customer updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update customer: ' . $e->getMessage());
    }
}

function getB2CCustomerBookings($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(50, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE accountId = ?");
        $countStmt->bind_param('i', $accountId);
        $countStmt->execute();
        $totalCount = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // bookings에는 customerType 컬럼이 없으므로 client.clientType로 B2B/B2C를 유추해서 내려줌
        $sql = "SELECT 
                    b.bookingId,
                    COALESCE(NULLIF(b.packageName,''), p.packageName) as packageName,
                    b.createdAt,
                    b.departureDate,
                    b.bookingStatus,
                    b.paymentStatus,
                    (COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infants,0)) as guests,
                    b.totalAmount,
                    CASE WHEN LOWER(COALESCE(c.clientType,'')) IN ('wholeseller','wholesaler') THEN 'B2B' ELSE 'B2C' END as customerType
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN client c ON b.accountId = c.accountId
                WHERE b.accountId = ?
                ORDER BY b.createdAt DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $accountId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        $rowNum = $totalCount - $offset;
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'bookingId' => $r['bookingId'],
                'packageName' => $r['packageName'] ?? '',
                'reservationDate' => !empty($r['createdAt']) ? substr($r['createdAt'], 0, 10) : '',
                'travelStartDate' => $r['departureDate'] ?? '',
                'bookingStatus' => $r['bookingStatus'] ?? '',
                'paymentStatus' => $r['paymentStatus'] ?? '',
                'guests' => intval($r['guests'] ?? 0),
                'totalAmount' => floatval($r['totalAmount'] ?? 0),
                'customerType' => $r['customerType'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'bookings' => $rows,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $limit ? ceil($totalCount / $limit) : 0,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer bookings: ' . $e->getMessage());
    }
}

function getB2CCustomerInquiries($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(50, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries WHERE accountId = ?");
        $countStmt->bind_param('i', $accountId);
        $countStmt->execute();
        $totalCount = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $sql = "SELECT 
                    i.inquiryId,
                    i.category,
                    i.subject,
                    i.createdAt,
                    i.status,
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 'Answered'
                        ELSE 'Unanswered'
                    END as replyStatus,
                    CASE 
                        WHEN i.status IN ('open') THEN 'Received'
                        WHEN i.status IN ('in_progress') THEN 'Processing'
                        WHEN i.status IN ('resolved','closed') THEN 'Completed'
                        ELSE i.status
                    END as processingStatus
                FROM inquiries i
                WHERE i.accountId = ?
                ORDER BY i.createdAt DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $accountId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        $rowNum = $totalCount - $offset;
        while ($r = $res->fetch_assoc()) {
            // IMPORTANT: 문의 유형은 "등록한 값" 그대로 보여야 함 (요구사항: payment만 맞고 나머지가 product로 보이는 문제 방지)
            // - inquiries.category enum을 그대로 내려준다. (legacy 매핑으로 강제 변환하지 않음)
            $cat = strtolower(trim((string)($r['category'] ?? '')));
            if ($cat === '') $cat = 'general';
            $rows[] = [
                'inquiryId' => intval($r['inquiryId']),
                'inquiryType' => $cat,
                'inquiryTitle' => $r['subject'] ?? '',
                'createdAt' => $r['createdAt'] ?? '',
                'replyStatus' => $r['replyStatus'] ?? 'Unanswered',
                'processingStatus' => $r['processingStatus'] ?? 'Received',
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'inquiries' => $rows,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $limit ? ceil($totalCount / $limit) : 0,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer inquiries: ' . $e->getMessage());
    }
}

function getB2BCustomerBookings($conn, $input) {
    // 고객(accountId) 기준 bookings 조회
    // - 에이전트가 생성한 예약은 bookings.accountId=agent 이고 고객은 customerAccountId(환경별) 또는 selectedOptions.customerInfo.accountId에 있을 수 있음 (요구사항 id 82-1)
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(50, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // bookings 컬럼 체크
        $bookingColumns = [];
        $colRes = $conn->query("SHOW COLUMNS FROM bookings");
        while ($colRes && ($c = $colRes->fetch_assoc())) $bookingColumns[] = strtolower((string)$c['Field']);

        $customerAccountIdCol = null;
        if (in_array('customeraccountid', $bookingColumns, true)) $customerAccountIdCol = 'customerAccountId';
        else if (in_array('customer_account_id', $bookingColumns, true)) $customerAccountIdCol = 'customer_account_id';
        else if (in_array('customerid', $bookingColumns, true)) $customerAccountIdCol = 'customerId';
        else if (in_array('userid', $bookingColumns, true)) $customerAccountIdCol = 'userId';

        // 고객 타입은 고객(clientType)로 판별 (bookings.accountId는 agent일 수 있음)
        $custType = 'B2C';
        try {
            $st = $conn->prepare("SELECT LOWER(COALESCE(clientType,'')) as ct FROM client WHERE accountId = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $accountId);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                $ct = strtolower(trim((string)($r['ct'] ?? '')));
                if ($ct === 'wholeseller' || $ct === 'wholesaler') $custType = 'B2B';
            }
        } catch (Throwable $e) { /* ignore */ }

        // COUNT/SELECT where
        if (!empty($customerAccountIdCol)) {
            $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE `{$customerAccountIdCol}` = ?");
            $countStmt->bind_param('i', $accountId);
        } else {
            $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE JSON_EXTRACT(selectedOptions, '$.customerInfo.accountId') = ?");
            $countStmt->bind_param('i', $accountId);
        }
        $countStmt->execute();
        $totalCount = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $where = !empty($customerAccountIdCol)
            ? "b.`{$customerAccountIdCol}` = ?"
            : "JSON_EXTRACT(b.selectedOptions, '$.customerInfo.accountId') = ?";

        $sql = "SELECT 
                    b.bookingId,
                    COALESCE(NULLIF(b.packageName,''), p.packageName) as packageName,
                    b.createdAt,
                    b.departureDate,
                    b.bookingStatus,
                    b.paymentStatus,
                    (COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infants,0)) as guests,
                    b.totalAmount
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE {$where}
                ORDER BY b.createdAt DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $accountId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        $rowNum = $totalCount - $offset;
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'bookingId' => $r['bookingId'],
                'packageName' => $r['packageName'] ?? '',
                'reservationDate' => !empty($r['createdAt']) ? substr($r['createdAt'], 0, 10) : '',
                'travelStartDate' => $r['departureDate'] ?? '',
                'bookingStatus' => $r['bookingStatus'] ?? '',
                'paymentStatus' => $r['paymentStatus'] ?? '',
                'guests' => intval($r['guests'] ?? 0),
                'totalAmount' => floatval($r['totalAmount'] ?? 0),
                'customerType' => $custType,
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'bookings' => $rows,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $limit ? ceil($totalCount / $limit) : 0,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer bookings: ' . $e->getMessage());
    }
}

function getB2BCustomerInquiries($conn, $input) {
    // accountId 기준 inquiries 조회 (B2B/B2C 공통)
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(50, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // 컬럼명 편차 대응(category/inquiryType, subject/inquiryTitle)
        $colRes = $conn->query("SHOW COLUMNS FROM inquiries");
        $cols = [];
        while ($colRes && ($c = $colRes->fetch_assoc())) $cols[] = strtolower((string)$c['Field']);
        $categoryCol = in_array('category', $cols, true) ? 'category' : (in_array('inquirytype', $cols, true) ? 'inquiryType' : 'category');
        $titleCol = in_array('subject', $cols, true) ? 'subject' : (in_array('inquirytitle', $cols, true) ? 'inquiryTitle' : 'subject');

        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries WHERE accountId = ?");
        $countStmt->bind_param('i', $accountId);
        $countStmt->execute();
        $totalCount = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $sql = "SELECT 
                    i.inquiryId,
                    i.`{$categoryCol}` as category,
                    i.`{$titleCol}` as subject,
                    i.createdAt,
                    i.status,
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 'Answered'
                        ELSE 'Unanswered'
                    END as replyStatus,
                    CASE 
                        WHEN i.status IN ('open') THEN 'Received'
                        WHEN i.status IN ('in_progress') THEN 'Processing'
                        WHEN i.status IN ('resolved','closed') THEN 'Completed'
                        ELSE i.status
                    END as processingStatus
                FROM inquiries i
                WHERE i.accountId = ?
                ORDER BY i.createdAt DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $accountId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        $rowNum = $totalCount - $offset;
        while ($r = $res->fetch_assoc()) {
            // IMPORTANT: 문의 유형은 "등록한 값" 그대로 보여야 함 (요구사항)
            $cat = strtolower(trim((string)($r['category'] ?? '')));
            if ($cat === '') $cat = 'general';
            $rows[] = [
                'inquiryId' => intval($r['inquiryId']),
                'inquiryType' => $cat,
                'inquiryTitle' => $r['subject'] ?? '',
                'createdAt' => $r['createdAt'] ?? '',
                'replyStatus' => $r['replyStatus'] ?? 'Unanswered',
                'processingStatus' => $r['processingStatus'] ?? 'Received',
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'inquiries' => $rows,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $limit ? ceil($totalCount / $limit) : 0,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer inquiries: ' . $e->getMessage());
    }
}

function getCustomerActivityTimeline($conn, $input) {
    // activity_logs / user_activity_logs가 비어있을 수 있으므로 accounts/bookings/inquiries로 합성 타임라인 제공
    try {
        $accountId = $input['accountId'] ?? $input['id'] ?? null;
        if (empty($accountId)) send_error_response('Account ID is required');
        $accountId = intval($accountId);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(50, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        // 1) 계정 생성/수정/마지막 로그인
        $accountRow = null;
        $a = $conn->prepare("SELECT createdAt, updatedAt, lastLoginAt FROM accounts WHERE accountId = ? LIMIT 1");
        if ($a) {
            $a->bind_param('i', $accountId);
            $a->execute();
            $accountRow = $a->get_result()->fetch_assoc();
            $a->close();
        }

        // 2) 예약 생성 (최근 N개까지만 합성; 페이지는 전체 이벤트 배열로 처리)
        $bookings = [];
        $b = $conn->prepare("SELECT bookingId, createdAt FROM bookings WHERE accountId = ? ORDER BY createdAt DESC LIMIT 200");
        if ($b) {
            $b->bind_param('i', $accountId);
            $b->execute();
            $r = $b->get_result();
            while ($row = $r->fetch_assoc()) $bookings[] = $row;
            $b->close();
        }

        // 3) 문의 등록 (최근 N개까지만 합성)
        $inquiries = [];
        $i = $conn->prepare("SELECT inquiryId, createdAt FROM inquiries WHERE accountId = ? ORDER BY createdAt DESC LIMIT 200");
        if ($i) {
            $i->bind_param('i', $accountId);
            $i->execute();
            $r = $i->get_result();
            while ($row = $r->fetch_assoc()) $inquiries[] = $row;
            $i->close();
        }

        // 4) activity_logs가 있다면 추가(최근 N개) - 현재 DB는 0건이지만 추후 대비
        $activityLogs = [];
        $alCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($alCheck && $alCheck->num_rows > 0) {
            $al = $conn->prepare("SELECT action, description, createdAt FROM activity_logs WHERE accountId = ? ORDER BY createdAt DESC LIMIT 200");
            if ($al) {
                $al->bind_param('i', $accountId);
                $al->execute();
                $r = $al->get_result();
                while ($row = $r->fetch_assoc()) $activityLogs[] = $row;
                $al->close();
            }
        }

        // 합성
        $events = [];
        if ($accountRow && !empty($accountRow['createdAt'])) {
            $events[] = [
                'type' => 'account_created',
                'title' => '계정 생성',
                'detail' => '고객 계정이 생성되었습니다.',
                'createdAt' => $accountRow['createdAt']
            ];
        }
        if ($accountRow && !empty($accountRow['updatedAt'])) {
            $events[] = [
                'type' => 'account_updated',
                'title' => '정보 수정',
                'detail' => '고객 정보가 수정되었습니다.',
                'createdAt' => $accountRow['updatedAt']
            ];
        }
        if ($accountRow && !empty($accountRow['lastLoginAt'])) {
            $events[] = [
                'type' => 'login',
                'title' => '로그인',
                'detail' => '고객이 로그인했습니다.',
                'createdAt' => $accountRow['lastLoginAt']
            ];
        }
        foreach ($bookings as $row) {
            if (empty($row['createdAt'])) continue;
            $events[] = [
                'type' => 'booking_created',
                'title' => '예약 생성',
                'detail' => '새로운 예약이 생성되었습니다. (' . ($row['bookingId'] ?? '') . ')',
                'createdAt' => $row['createdAt'],
                'bookingId' => $row['bookingId'] ?? null
            ];
        }
        foreach ($inquiries as $row) {
            if (empty($row['createdAt'])) continue;
            $events[] = [
                'type' => 'inquiry_created',
                'title' => '문의 등록',
                'detail' => '문의가 등록되었습니다. (' . ($row['inquiryId'] ?? '') . ')',
                'createdAt' => $row['createdAt'],
                'inquiryId' => $row['inquiryId'] ?? null
            ];
        }
        foreach ($activityLogs as $row) {
            if (empty($row['createdAt'])) continue;
            $events[] = [
                'type' => 'activity_log',
                'title' => $row['action'] ?? '활동',
                'detail' => $row['description'] ?? '',
                'createdAt' => $row['createdAt']
            ];
        }

        // createdAt desc
        usort($events, function($a, $b) {
            return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
        });

        $totalCount = count($events);
        $slice = array_slice($events, $offset, $limit);

        // rowNum은 상단이 최신 = totalCount - offset
        $rowNum = $totalCount - $offset;
        $rows = [];
        foreach ($slice as $e) {
            $rows[] = array_merge($e, ['rowNum' => $rowNum--]);
        }

        send_success_response([
            'activities' => $rows,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $limit ? ceil($totalCount / $limit) : 0,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get activity timeline: ' . $e->getMessage());
    }
}

// ========== 문의 관리 함수들 ==========

function ensureInquiryRepliesTable($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
    if ($check && $check->num_rows > 0) return;

    $sql = "CREATE TABLE IF NOT EXISTS inquiry_replies (
        replyId INT AUTO_INCREMENT PRIMARY KEY,
        inquiryId INT NOT NULL,
        authorId INT NOT NULL,
        content LONGTEXT NOT NULL,
        isInternal TINYINT(1) NOT NULL DEFAULT 0,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_inquiryId (inquiryId),
        KEY idx_createdAt (createdAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        throw new Exception('Failed to create inquiry_replies table: ' . $conn->error);
    }
}

function getUserInquiries($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        ensureInquiryRepliesTable($conn);
        
        $whereConditions = [];
        $params = [];
        $types = '';

        // 회원(고객) 문의 목록은 고객(B2B/B2C) 문의만 포함
        // - 계정 타입/환경(agent 테이블 유무 등)에 의존하지 않고 "client 테이블에 연결된 문의"를 기준으로 필터링
        // - 이렇게 하면 B2B(회사 소속/wholeseller 등) + B2C(일반) 고객 모두 포함됩니다.
        $whereConditions[] = "EXISTS (SELECT 1 FROM client c WHERE c.accountId = i.accountId)";
        
        if (!empty($input['replyStatus'])) {
            if ($input['replyStatus'] === 'answered') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($input['replyStatus'] === 'unanswered') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            }
        }
        
        if (!empty($input['processingStatus'])) {
            // UI 값(pending/processing/completed)을 DB 값(open/in_progress/resolved/closed)으로 매핑
            $map = [
                'pending' => ['open'],
                'processing' => ['in_progress'],
                'completed' => ['resolved', 'closed']
            ];
            $key = $input['processingStatus'];
            if (isset($map[$key])) {
                $vals = $map[$key];
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            } else {
                $whereConditions[] = "i.status = ?";
                $params[] = $key;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM inquiries i $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest' ? 'i.createdAt ASC' : 'i.createdAt DESC';
        
        $dataSql = "SELECT 
            i.inquiryId,
            i.category,
            i.subject,
            i.createdAt,
            i.status,
            CASE 
                WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 'Answer completed'
                ELSE 'Unanswered'
            END as replyStatus,
            CASE 
                WHEN i.status = 'open' THEN 'Received'
                WHEN i.status = 'in_progress' THEN 'Processing'
                WHEN i.status IN ('resolved','closed') THEN 'Processing complete'
                ELSE i.status
            END as processingStatus
        FROM inquiries i
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $inquiries = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            // IMPORTANT: 문의 유형은 "등록한 값" 그대로 보여야 함
            $cat = strtolower(trim((string)($row['category'] ?? '')));
            if ($cat === '') $cat = 'general';
            $inquiries[] = [
                'inquiryId' => $row['inquiryId'],
                'inquiryType' => $cat,
                'inquiryTitle' => $row['subject'] ?? '',
                'createdAt' => $row['createdAt'] ?? '',
                'replyStatus' => $row['replyStatus'] ?? 'Unanswered',
                'processingStatus' => $row['processingStatus'] ?? 'Received',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'inquiries' => $inquiries,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get user inquiries: ' . $e->getMessage());
    }
}

function getUserInquiryDetail($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? $input['id'] ?? null;
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }

        ensureInquiryRepliesTable($conn);

        // NOTE:
        // - 문의 작성자의 유형(client/agent)에 따라 프로필 정보가 없을 수 있으므로 accounts/client/agent를 모두 조인합니다.
        // - 에이전트 문의 상세 요구사항: 담당자명/담당자 이메일/담당자 연락처를 제공해야 함
        $regionExpr = "COALESCE('' as co2_businessUnit,'')";

        $sql = "SELECT 
            i.*,
            COALESCE(c.clientId, '') as clientId,
            COALESCE(NULLIF(c.fName, ''), NULLIF(a.fName, ''), NULLIF(ac.username, ''), '') as fName,
            COALESCE(NULLIF(c.lName, ''), NULLIF(a.lName, ''), '') as lName,
            COALESCE(NULLIF(c.emailAddress, ''), NULLIF(ac.emailAddress, ''), '') as emailAddress,
            COALESCE(NULLIF(c.contactNo, ''), NULLIF(a.contactNo, ''), '') as contactNo,
            COALESCE(c.clientType, '') as clientType,
            -- 고객(B2B) 소속
            CASE WHEN LOWER(COALESCE(c.clientType,'')) IN ('wholeseller','wholesaler','wholesale') THEN COALESCE('' as co_companyName, '') ELSE '' END as companyName,
            CASE WHEN LOWER(COALESCE(c.clientType,'')) IN ('wholeseller','wholesaler','wholesale') THEN COALESCE('', '') ELSE '' END as branchName,
            -- 에이전트 소속/담당자 (agent 문의 상세)
            COALESCE('', '') as agentBranchName,
            COALESCE('' as co2_companyName, '') as agentCompanyName,
            COALESCE('' as co2_businessUnit, '') as agentBusinessUnit,
            {$regionExpr} as agentRegion,
            TRIM(CONCAT_WS(' ', NULLIF(a.fName,''), NULLIF(a.mName,''), NULLIF(a.lName,''))) as agentManagerName,
            COALESCE(NULLIF(ac.emailAddress,''), '') as agentManagerEmail,
            COALESCE(NULLIF(a.contactNo,''), '') as agentManagerContact
        FROM inquiries i
        LEFT JOIN accounts ac ON i.accountId = ac.accountId
        LEFT JOIN client c ON i.accountId = c.accountId
        
        LEFT JOIN agent a ON i.accountId = a.accountId
        
        WHERE i.inquiryId = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Inquiry not found', 404);
        }
        
        $inquiry = $result->fetch_assoc();

        // 에이전트 문의 상세 화면에서 쓰는 표준 키 제공 (프론트가 id/name에 종속되지 않도록)
        try {
            // Branch name: branch.branchName가 없을 수 있어(company.branchId NULL) companyName/businessUnit으로 fallback
            $agentBranchName = trim((string)($inquiry['agentBranchName'] ?? ''));
            $agentCompanyName = trim((string)($inquiry['agentCompanyName'] ?? ''));
            $agentBusinessUnit = trim((string)($inquiry['agentBusinessUnit'] ?? ''));
            $fallbackBranch = trim((string)($inquiry['branchName'] ?? ''));
            $fallbackCompany = trim((string)($inquiry['companyName'] ?? ''));

            $inquiry['branchName'] =
                ($agentBranchName !== '' ? $agentBranchName
                    : ($agentCompanyName !== '' ? $agentCompanyName
                        : ($agentBusinessUnit !== '' ? $agentBusinessUnit
                            : ($fallbackBranch !== '' ? $fallbackBranch : $fallbackCompany))));

            $inquiry['region'] = (string)($inquiry['agentRegion'] ?? ($agentBusinessUnit ?? ''));
            $inquiry['managerName'] = (string)($inquiry['agentManagerName'] ?? '');
            $inquiry['managerEmail'] = (string)($inquiry['agentManagerEmail'] ?? ($inquiry['emailAddress'] ?? ''));
            $inquiry['managerContact'] = (string)($inquiry['agentManagerContact'] ?? ($inquiry['contactNo'] ?? ''));
        } catch (Throwable $_) {}

        // 첨부파일(inquiry_attachments) 로드
        $attachments = [];
        if ($conn->query("SHOW TABLES LIKE 'inquiry_attachments'")->num_rows > 0) {
            $aStmt = $conn->prepare("SELECT * FROM inquiry_attachments WHERE inquiryId = ? ORDER BY attachmentId ASC");
            if ($aStmt) {
                $aStmt->bind_param('i', $inquiryId);
                $aStmt->execute();
                $aRes = $aStmt->get_result();
                while ($att = $aRes->fetch_assoc()) {
                    // 첨부파일 메타데이터 보강 (파일명/확장자/크기/이미지 여부)
                    try {
                        $filePath = (string)($att['filePath'] ?? $att['file_path'] ?? $att['path'] ?? '');
                        $fileName = (string)($att['fileName'] ?? $att['file_name'] ?? '');
                        $fileSize = $att['fileSize'] ?? $att['file_size'] ?? null;

                        $filePath = str_replace('\\', '/', trim($filePath));
                        if ($filePath !== '' && str_starts_with($filePath, 'uploads/')) $filePath = '/' . $filePath;
                        if ($filePath !== '' && !str_starts_with($filePath, '/uploads/')) {
                            // 보안상 uploads 밖은 노출하지 않음
                            $filePath = '';
                        }

                        $root = realpath(__DIR__ . '/../../..'); // /var/www/html
                        $uploadsRoot = realpath(__DIR__ . '/../../../uploads'); // /var/www/html/uploads
                        $abs = null;
                        if ($root && $uploadsRoot && $filePath !== '') {
                            $absTry = realpath($root . $filePath);
                            if ($absTry && str_starts_with($absTry, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($absTry)) {
                                $abs = $absTry;
                            }
                        }

                        if ($fileName === '') {
                            if ($abs) $fileName = basename($abs);
                            else if ($filePath !== '') $fileName = basename($filePath);
                        }
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

                        if (!is_numeric($fileSize) || (int)$fileSize <= 0) {
                            if ($abs) {
                                $sz = filesize($abs);
                                if ($sz !== false) $fileSize = (int)$sz;
                            }
                        } else {
                            $fileSize = (int)$fileSize;
                        }

                        $att['filePath'] = $filePath; // 정규화된 값으로 통일
                        $att['fileName'] = $fileName;
                        $att['fileExt'] = $ext;
                        $att['fileSize'] = is_numeric($fileSize) ? (int)$fileSize : null;
                        $att['isImage'] = $isImage ? 1 : 0;
                    } catch (Throwable $e) {
                        // ignore: keep raw values
                    }
                    $attachments[] = $att;
                }
                $aStmt->close();
            }
        }
        $inquiry['attachments'] = $attachments;
        
        // 답변 가져오기
        $replies = [];
        $replySql = "SELECT ir.*, ac.username as authorName
                     FROM inquiry_replies ir
                     LEFT JOIN accounts ac ON ir.authorId = ac.accountId
                     WHERE ir.inquiryId = ?
                     ORDER BY ir.createdAt DESC";
        $replyStmt = $conn->prepare($replySql);
        $replyStmt->bind_param('i', $inquiryId);
        $replyStmt->execute();
        $replyResult = $replyStmt->get_result();
        while ($reply = $replyResult->fetch_assoc()) {
            $replies[] = $reply;
        }
        $replyStmt->close();
        
        $stmt->close();
        
        send_success_response(['inquiry' => $inquiry, 'replies' => $replies]);
    } catch (Exception $e) {
        send_error_response('Failed to get inquiry detail: ' . $e->getMessage());
    }
}

function downloadInquiryAttachment($conn) {
    try {
        // super/admin only
        $in = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
        } else {
            $in = $_GET;
        }

        $inquiryId = $in['inquiryId'] ?? $in['id'] ?? null;
        $filePath = $in['filePath'] ?? '';
        if (empty($inquiryId) || empty($filePath)) send_error_response('Inquiry ID and filePath are required', 400);

        // 보안: uploads/ 아래만 허용 + ../ 차단
        $filePath = str_replace('\\', '/', trim((string)$filePath));
        if (str_contains($filePath, '..')) send_error_response('Invalid file path', 400);
        if ($filePath !== '' && str_starts_with($filePath, 'uploads/')) $filePath = '/' . $filePath;
        if (!str_starts_with($filePath, '/uploads/')) send_error_response('Invalid file path', 400);

        $root = realpath(__DIR__ . '/../../..'); // /var/www/html
        $uploadsRoot = realpath(__DIR__ . '/../../../uploads');
        if (!$root || !$uploadsRoot) send_error_response('Server path error', 500);
        $abs = realpath($root . $filePath);
        if (!$abs || !str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            send_error_response('File not found', 404);
        }

        // 파일명이 DB에 없으면 basename
        $name = basename($abs);
        $size = filesize($abs);
        $type = @mime_content_type($abs) ?: 'application/octet-stream';

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        header('Content-Transfer-Encoding: binary');
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        if (!$fallback) $fallback = 'attachment';
        $utf8Name = rawurlencode($name);
        header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''{$utf8Name}");
        if (is_numeric($size)) header('Content-Length: ' . $size);
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($abs);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download attachment: ' . $e->getMessage(), 500);
    }
}

function updateInquiryStatus($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? $input['id'] ?? null;
        $status = $input['status'] ?? null;
        if (empty($inquiryId) || empty($status)) {
            send_error_response('Inquiry ID and status are required');
        }

        // UI(pending/processing/completed) -> DB(open/in_progress/resolved)
        $statusKey = strtolower(trim((string)$status));
        $map = [
            'pending' => 'open',
            'processing' => 'in_progress',
            'completed' => 'resolved'
        ];
        $dbStatus = $map[$statusKey] ?? $statusKey;
        $allowed = ['open', 'in_progress', 'resolved', 'closed', 'pending', 'processing', 'completed'];
        if (!in_array($dbStatus, $allowed, true)) {
            send_error_response('Invalid status');
        }

        $resolvedAt = null;
        $setResolvedAt = false;
        if (in_array($dbStatus, ['resolved', 'closed'], true)) {
            $setResolvedAt = true;
        }

        if ($setResolvedAt) {
            $stmt = $conn->prepare("UPDATE inquiries SET status = ?, resolvedAt = NOW() WHERE inquiryId = ?");
            $stmt->bind_param('si', $dbStatus, $inquiryId);
        } else {
            $stmt = $conn->prepare("UPDATE inquiries SET status = ?, resolvedAt = NULL WHERE inquiryId = ?");
            $stmt->bind_param('si', $dbStatus, $inquiryId);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to update inquiry status: ' . $err);
        }
        $stmt->close();

        send_success_response([], 'Inquiry status updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update inquiry status: ' . $e->getMessage());
    }
}

function sendInquiryAnswer($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? $input['id'] ?? null;
        $content = $input['content'] ?? '';
        if (empty($inquiryId)) send_error_response('Inquiry ID is required');
        $content = trim((string)$content);
        if ($content === '' || $content === '<p><br></p>') {
            send_error_response('Reply content is required');
        }

        ensureInquiryRepliesTable($conn);

        $authorId = $_SESSION['admin_accountId'] ?? null;
        if (empty($authorId)) send_error_response('Admin login required', 401);
        $authorId = intval($authorId);

        // Insert reply
        $stmt = $conn->prepare("INSERT INTO inquiry_replies (inquiryId, authorId, content, isInternal) VALUES (?, ?, ?, 0)");
        if (!$stmt) send_error_response('Failed to prepare reply insert: ' . $conn->error);
        $stmt->bind_param('iis', $inquiryId, $authorId, $content);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to send reply: ' . $err);
        }
        $replyId = $conn->insert_id;
        $stmt->close();

        // 답변 여부(Reply Status)와 처리상태(Processing Status)는 분리:
        // 답변 등록만 하고 status 는 자동 변경하지 않습니다. (필요 시 updateInquiryStatus로 별도 변경)

        send_success_response(['replyId' => $replyId], 'Reply sent successfully');
    } catch (Exception $e) {
        send_error_response('Failed to send reply: ' . $e->getMessage());
    }
}

function downloadUserInquiries($conn, $input) {
    try {
        ensureInquiryRepliesTable($conn);

        $whereConditions = [];
        $params = [];
        $types = '';

        // 회원(고객) 문의 CSV는 고객(B2B/B2C) 문의만 포함 (client 테이블 기준)
        $whereConditions[] = "EXISTS (SELECT 1 FROM client c WHERE c.accountId = i.accountId)";

        if (!empty($input['replyStatus'])) {
            if ($input['replyStatus'] === 'answered') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($input['replyStatus'] === 'unanswered') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            }
        }

        if (!empty($input['processingStatus'])) {
            $map = [
                'pending' => ['open'],
                'processing' => ['in_progress'],
                'completed' => ['resolved', 'closed']
            ];
            $key = $input['processingStatus'];
            if (isset($map[$key])) {
                $vals = $map[$key];
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            } else {
                $whereConditions[] = "i.status = ?";
                $params[] = $key; $types .= 's';
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest' ? 'i.createdAt ASC' : 'i.createdAt DESC';

        $sql = "SELECT
                    i.inquiryId,
                    i.category,
                    i.subject,
                    i.createdAt,
                    i.status,
                    CASE
                        WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 'Response Complete'
                        ELSE 'Not Responded'
                    END as replyStatus,
                    CASE
                        WHEN i.status = 'open' THEN 'Received'
                        WHEN i.status = 'in_progress' THEN 'In Progress'
                        WHEN i.status IN ('resolved','closed') THEN 'Processing Complete'
                        ELSE i.status
                    END as processingStatus
                FROM inquiries i
                $whereClause
                ORDER BY $orderBy";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="user_inquiries_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Inquiry ID', 'Inquiry Type', 'Title', 'Created At', 'Response Status', 'Processing Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $no++,
                $row['inquiryId'] ?? '',
                $row['category'] ?? '',
                $row['subject'] ?? '',
                $row['createdAt'] ?? '',
                $row['replyStatus'] ?? '',
                $row['processingStatus'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download user inquiries: ' . $e->getMessage(), 500);
    }
}

function downloadAgentInquiries($conn, $input) {
    try {
        ensureInquiryRepliesTable($conn);

        $whereConditions = [];
        $params = [];
        $types = '';

        // 에이전트 문의 CSV는 "agent 계정" 문의만
        // 1) accounts.accountType='agent' 우선
        // 2) agent 테이블(accountId 존재)도 포함 (레거시/환경 차이 대응)
        $agentTableExists = false;
        $t = $conn->query("SHOW TABLES LIKE 'agent'");
        if ($t && $t->num_rows > 0) $agentTableExists = true;

        $cond = "EXISTS (SELECT 1 FROM accounts ac WHERE ac.accountId = i.accountId AND ac.accountType = 'agent')";
        if ($agentTableExists) {
            $cond .= " OR EXISTS (SELECT 1 FROM agent ag WHERE ag.accountId = i.accountId)";
        }
        $whereConditions[] = "($cond)";

        if (!empty($input['replyStatus'])) {
            if ($input['replyStatus'] === 'answered') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($input['replyStatus'] === 'unanswered') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            }
        }

        if (!empty($input['processingStatus'])) {
            $map = [
                'pending' => ['open'],
                'processing' => ['in_progress'],
                'completed' => ['resolved', 'closed']
            ];
            $key = $input['processingStatus'];
            if (isset($map[$key])) {
                $vals = $map[$key];
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            } else {
                $whereConditions[] = "i.status = ?";
                $params[] = $key; $types .= 's';
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest' ? 'i.createdAt ASC' : 'i.createdAt DESC';

        // agent 테이블이 없는 환경에서는 JOIN을 제거하고 지점명을 빈 값으로 반환
        $branchSelect = $agentTableExists
            ? "COALESCE(NULLIF('',''), NULLIF('' as co_companyName,''), '') AS branchName"
            : "'' AS branchName";
        $agentJoins = "";
        if ($agentTableExists) {
            $agentJoins = "
                    SELECT a.companyId
                    FROM agent a
                    WHERE a.accountId = i.accountId
                    ORDER BY (a.agentType = 'Wholeseller') DESC, a.id ASC
                    LIMIT 1
                )
                ";
        }

        $sql = "SELECT
                    i.inquiryId,
                    i.subject,
                    i.createdAt,
                    i.status,
                    $branchSelect,
                    CASE
                        WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 'Response Complete'
                        ELSE 'Not Responded'
                    END as replyStatus,
                    CASE
                        WHEN i.status = 'open' THEN 'Received'
                        WHEN i.status = 'in_progress' THEN 'In Progress'
                        WHEN i.status IN ('resolved','closed') THEN 'Processing Complete'
                        ELSE i.status
                    END as processingStatus
                FROM inquiries i
                $agentJoins
                $whereClause
                ORDER BY $orderBy";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agent_inquiries_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Inquiry ID', 'Branch', 'Title', 'Created At', 'Response Status', 'Processing Status'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $no++,
                $row['inquiryId'] ?? '',
                $row['branchName'] ?? '',
                $row['subject'] ?? '',
                $row['createdAt'] ?? '',
                $row['replyStatus'] ?? '',
                $row['processingStatus'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download agent inquiries: ' . $e->getMessage(), 500);
    }
}

// ========== 회원 관리 함수들 ==========

function getMembers($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        // 요구사항:
        // - 검색은 "이름" 기준으로만 동작
        // - Member Type은 B2B/B2C/Agent/Guide로 표기되도록,
        //   목록에는 guest(=customer), agent, guide만 포함 (admin/employee 제외)
        $whereConditions = ["ac.accountType IN ('guest','agent','guide')"];
        $params = [];
        $types = '';
        
        if (!empty($input['search'])) {
            // 이름 기준 검색 (client/agent: fName+lName, guide: guideName)
            $memberNameExpr = "TRIM(COALESCE(
                NULLIF(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,'')), ' '),
                NULLIF(CONCAT(COALESCE(a.fName,''), ' ', COALESCE(a.lName,'')), ' '),
                NULLIF(g.guideName,''),
                ''
            ))";
            $whereConditions[] = "($memberNameExpr LIKE ?)";
            $searchTerm = '%' . $input['search'] . '%';
            $params[] = $searchTerm;
            $types .= 's';
        }
        
        if (!empty($input['accountType'])) {
            $whereConditions[] = "ac.accountType = ?";
            $params[] = $input['accountType'];
            $types .= 's';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM accounts ac
                     LEFT JOIN client c ON ac.accountId = c.accountId
                     LEFT JOIN agent a ON ac.accountId = a.accountId
                     LEFT JOIN guides g ON ac.accountId = g.accountId
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $memberNameExpr = "TRIM(COALESCE(
            NULLIF(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,'')), ' '),
            NULLIF(CONCAT(COALESCE(a.fName,''), ' ', COALESCE(a.lName,'')), ' '),
            NULLIF(g.guideName,''),
            ''
        ))";

        $dataSql = "SELECT 
            ac.accountId,
            ac.emailAddress,
            ac.accountType,
            ac.accountStatus,
            c.clientType,
            COALESCE(
                NULLIF(c.contactNo,''),
                NULLIF(a.contactNo,''),
                NULLIF(g.phoneNumber,''),
                CONCAT(COALESCE(ac.phoneCountryCode,''), ' ', COALESCE(ac.phoneNumber,''))
            ) as contactNo,
            $memberNameExpr as memberName,
            ac.createdAt
        FROM accounts ac
        LEFT JOIN client c ON ac.accountId = c.accountId
        LEFT JOIN agent a ON ac.accountId = a.accountId
        LEFT JOIN guides g ON ac.accountId = g.accountId
        $whereClause
        ORDER BY ac.createdAt DESC
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $members = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $members[] = [
                'accountId' => $row['accountId'],
                'emailAddress' => $row['emailAddress'] ?? '',
                'accountType' => $row['accountType'] ?? '',
                'clientType' => $row['clientType'] ?? null,
                'accountStatus' => $row['accountStatus'] ?? 'active',
                'memberName' => trim($row['memberName'] ?? ''),
                'contactNo' => trim($row['contactNo'] ?? ''),
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'members' => $members,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get members: ' . $e->getMessage());
    }
}

// ========== CSV Export: Members / Customers ==========

function exportMembersCsv($conn, $input) {
    try {
        // session 체크는 super-api 상단에서 수행됨
        $search = trim($input['search'] ?? '');
        $accountType = trim($input['accountType'] ?? '');

        // 목록과 동일: guest/agent/guide만
        $where = ["ac.accountType IN ('guest','agent','guide')"];
        $params = [];
        $types = '';

        if ($search !== '') {
            $memberNameExpr = "TRIM(COALESCE(
                NULLIF(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,'')), ' '),
                NULLIF(CONCAT(COALESCE(a.fName,''), ' ', COALESCE(a.lName,'')), ' '),
                NULLIF(g.guideName,''),
                ''
            ))";
            $where[] = "($memberNameExpr LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $types .= 's';
        }
        if ($accountType !== '') {
            $where[] = "ac.accountType = ?";
            $params[] = $accountType;
            $types .= 's';
        }
        $whereClause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $memberNameExpr = "TRIM(COALESCE(
            NULLIF(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,'')), ' '),
            NULLIF(CONCAT(COALESCE(a.fName,''), ' ', COALESCE(a.lName,'')), ' '),
            NULLIF(g.guideName,''),
            ''
        ))";

        $sql = "SELECT 
                    ac.accountId,
                    ac.accountType,
                    c.clientType,
                    $memberNameExpr as memberName,
                    ac.emailAddress,
                    COALESCE(
                        NULLIF(c.contactNo,''),
                        NULLIF(a.contactNo,''),
                        NULLIF(g.phoneNumber,''),
                        CONCAT(COALESCE(ac.phoneCountryCode,''), ' ', COALESCE(ac.phoneNumber,''))
                    ) as contactNo,
                    ac.accountStatus,
                    ac.createdAt
                FROM accounts ac
                LEFT JOIN client c ON ac.accountId = c.accountId
                LEFT JOIN agent a ON ac.accountId = a.accountId
                LEFT JOIN guides g ON ac.accountId = g.accountId
                $whereClause
                ORDER BY ac.createdAt DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="members_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Account ID', 'Member Type', 'Client Type', 'Name', 'Email', 'Phone', 'Status', 'Created At'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $no++,
                $row['accountId'] ?? '',
                $row['accountType'] ?? '',
                $row['clientType'] ?? '',
                $row['memberName'] ?? '',
                $row['emailAddress'] ?? '',
                trim((string)($row['contactNo'] ?? '')),
                $row['accountStatus'] ?? '',
                $row['createdAt'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export members CSV: ' . $e->getMessage(), 500);
    }
}

function exportB2BCustomersCsv($conn, $input) {
    try {
        $search = trim($input['search'] ?? '');
        $searchType = trim($input['searchType'] ?? 'all'); // all/customer/branch

        // getB2BCustomers와 동일한 B2B 기준(clientType)
        $where = [
            "ac.accountType = 'guest'",
            "(LOWER(COALESCE(c.clientType,'')) IN ('wholeseller','wholesaler'))"
        ];
        $params = [];
        $types = '';
        if ($search !== '') {
            $term = '%' . $search . '%';
            if ($searchType === 'customer') {
                $where[] = "CONCAT(c.fName, ' ', c.lName) LIKE ?";
                $params[] = $term;
                $types .= 's';
            } elseif ($searchType === 'branch') {
                $where[] = "(COALESCE('', '', '') LIKE ? OR COALESCE('' as co_ag_companyName, '' as co_cl_companyName, '') LIKE ?)";
                $params[] = $term;
                $params[] = $term;
                $types .= 'ss';
            } else {
                $where[] = "(
                    CONCAT(c.fName, ' ', c.lName) LIKE ?
                    OR COALESCE('', '', '') LIKE ?
                    OR COALESCE('' as co_ag_companyName, '' as co_cl_companyName, '') LIKE ?
                )";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $types .= 'sss';
            }
        }
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT 
                    c.accountId,
                    CONCAT(c.fName, ' ', c.lName) as customerName,
                    ac.emailAddress,
                    c.contactNo,
                    COALESCE('' as co_ag_companyName, '' as co_cl_companyName, '') as companyName,
                    COALESCE('', '', '') as branchName,
                    COALESCE(co_ag.businessUnit, co_cl.businessUnit, '') as businessUnit,
                    ac.accountStatus,
                    COALESCE(ac.createdAt, c.updatedAt) as createdAt
                FROM client c
                LEFT JOIN accounts ac ON c.accountId = ac.accountId
                $whereClause
                ORDER BY COALESCE(ac.createdAt, c.updatedAt) DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="b2b_customers_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Account ID', 'Company/Branch', 'Contact Person', 'Email', 'Phone', 'Status', 'Created At'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            $bn = trim((string)($row['branchName'] ?? ''));
            $cn = trim((string)($row['companyName'] ?? ''));
            $bu = trim((string)($row['businessUnit'] ?? ''));
            $companyOrBranch = $bn ?: ($cn ?: $bu);
            fputcsv($out, [
                $no++,
                $row['accountId'] ?? '',
                $companyOrBranch,
                $row['customerName'] ?? '',
                $row['emailAddress'] ?? '',
                $row['contactNo'] ?? '',
                $row['accountStatus'] ?? '',
                $row['createdAt'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export B2B customers CSV: ' . $e->getMessage(), 500);
    }
}

function exportB2CCustomersCsv($conn, $input) {
    try {
        $search = trim($input['search'] ?? '');

        // getB2CCustomers와 동일한 필터를 적용 (wholeseller 제외)
        $where = [
            "ac.accountType = 'guest'",
            "(LOWER(COALESCE(c.clientType,'')) NOT IN ('wholeseller','wholesaler'))"
        ];
        $params = [];
        $types = '';
        if ($search !== '') {
            $where[] = "CONCAT(c.fName, ' ', c.lName) LIKE ?";
            $params[] = '%' . $search . '%';
            $types .= 's';
        }
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT 
                    c.accountId,
                    CONCAT(c.fName, ' ', c.lName) as customerName,
                    ac.emailAddress,
                    c.contactNo,
                    ac.accountStatus,
                    ac.createdAt
                FROM client c
                LEFT JOIN accounts ac ON c.accountId = ac.accountId
                $whereClause
                ORDER BY ac.createdAt DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare CSV query: ' . $conn->error, 500);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="b2c_customers_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Account ID', 'Customer Name', 'Email', 'Phone', 'Status', 'Created At'], ',', '"', '\\');

        $no = 1;
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $no++,
                $row['accountId'] ?? '',
                $row['customerName'] ?? '',
                $row['emailAddress'] ?? '',
                $row['contactNo'] ?? '',
                $row['accountStatus'] ?? '',
                $row['createdAt'] ?? ''
            ], ',', '"', '\\');
        }
        fclose($out);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export B2C customers CSV: ' . $e->getMessage(), 500);
    }
}

// ========== 공지사항 관리 함수들 ==========

function ensureNoticesExtraColumns($conn) {
    $need = [
        'startDate' => "ALTER TABLE notices ADD COLUMN startDate DATE NULL",
        'endDate' => "ALTER TABLE notices ADD COLUMN endDate DATE NULL",
        'signingInfo' => "ALTER TABLE notices ADD COLUMN signingInfo VARCHAR(255) NULL",
        // 언어별 이미지(JSON: {"en":"...","tl":"...","ko":"..."}) 저장을 위해 길이를 넉넉히 잡는다.
        'imageUrl' => "ALTER TABLE notices ADD COLUMN imageUrl VARCHAR(1024) NULL",
    ];
    foreach ($need as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM notices LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$check || $check->num_rows === 0) {
            // 일부 환경에서는 ALTER 권한이 없을 수 있으므로 실패해도 치명 에러로 만들지 않음
            @$conn->query($sql);
        }
    }

    // imageUrl 컬럼이 이미 존재하지만 길이가 255인 환경(기존)에서는 1024로 확장 시도
    try {
        $col = $conn->query("SHOW COLUMNS FROM notices LIKE 'imageUrl'");
        if ($col && $col->num_rows > 0) {
            $row = $col->fetch_assoc();
            $type = strtolower((string)($row['Type'] ?? ''));
            // varchar(255) -> varchar(1024)
            if (preg_match('/varchar\\((\\d+)\\)/', $type, $m)) {
                $len = intval($m[1] ?? 0);
                if ($len > 0 && $len < 1024) {
                    @$conn->query("ALTER TABLE notices MODIFY COLUMN imageUrl VARCHAR(1024) NULL");
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    // content 컬럼은 에디터(HTML) + 다국어 JSON 저장으로 쉽게 64KB(TEXT)를 초과할 수 있으므로 LONGTEXT로 확장
    try {
        $c = $conn->query("SHOW COLUMNS FROM notices LIKE 'content'");
        if ($c && $c->num_rows > 0) {
            $row = $c->fetch_assoc();
            $type = strtolower((string)($row['Type'] ?? ''));
            if ($type === 'text' || $type === 'mediumtext') {
                // NOT NULL 유지(스키마 호환), 타입만 확장
                @$conn->query("ALTER TABLE notices MODIFY COLUMN content LONGTEXT NOT NULL");
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function getNotices($conn, $input) {
    try {
        ensureNoticesExtraColumns($conn);
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($input['search'])) {
            $whereConditions[] = "n.title LIKE ?";
            $params[] = '%' . $input['search'] . '%';
            $types .= 's';
        }
        
        if (!empty($input['status'])) {
            $whereConditions[] = "n.status = ?";
            $params[] = $input['status'];
            $types .= 's';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM notices n $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        // notices 테이블은 createdAt이 없고 publishedAt/updatedAt만 존재
        $orderBy = $sortOrder === 'oldest'
            ? 'COALESCE(n.publishedAt, n.updatedAt) ASC'
            : 'COALESCE(n.publishedAt, n.updatedAt) DESC';

        // startDate/endDate 컬럼이 없을 수 있어, 존재 여부에 따라 SELECT 구성
        $cols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM notices");
        while ($colRes && ($c = $colRes->fetch_assoc())) $cols[] = strtolower($c['Field']);
        $hasStart = in_array('startdate', $cols, true) || in_array('start_date', $cols, true);
        $hasEnd = in_array('enddate', $cols, true) || in_array('end_date', $cols, true);
        $startField = in_array('startdate', $cols, true) ? 'n.startDate' : ($hasStart ? 'n.start_date' : "''");
        $endField = in_array('enddate', $cols, true) ? 'n.endDate' : ($hasEnd ? 'n.end_date' : "''");

        $dataSql = "SELECT 
            n.noticeId,
            n.title,
            n.status,
            $startField as startDate,
            $endField as endDate,
            n.publishedAt,
            n.updatedAt
        FROM notices n
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $notices = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $notices[] = [
                'noticeId' => $row['noticeId'],
                'title' => $row['title'] ?? '',
                'status' => $row['status'] ?? 'active',
                'startDate' => $row['startDate'] ?? '',
                'endDate' => $row['endDate'] ?? '',
                'createdAt' => $row['publishedAt'] ?? ($row['updatedAt'] ?? ''),
                'updatedAt' => $row['updatedAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'notices' => $notices,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get notices: ' . $e->getMessage());
    }
}

function getNoticeDetail($conn, $input) {
    try {
        ensureNoticesExtraColumns($conn);
        $noticeId = $input['noticeId'] ?? $input['id'] ?? null;
        if (empty($noticeId)) {
            send_error_response('Notice ID is required');
        }
        
        $sql = "SELECT * FROM notices WHERE noticeId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $noticeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Notice not found', 404);
        }
        
        $notice = $result->fetch_assoc();
        $stmt->close();
        
        send_success_response(['notice' => $notice]);
    } catch (Exception $e) {
        send_error_response('Failed to get notice detail: ' . $e->getMessage());
    }
}

function createNotice($conn, $input) {
    try {
        ensureNoticesExtraColumns($conn);
        $requiredFields = ['title', 'content'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }
        
        $columns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM notices");
        while ($col = $columnResult->fetch_assoc()) {
            $columns[] = strtolower($col['Field']);
        }
        
        $fields = ['title', 'content'];
        $values = [$input['title'], $input['content']];
        $types = 'ss';
        
        if (in_array('startdate', $columns) || in_array('start_date', $columns)) {
            $startField = in_array('startdate', $columns) ? 'startDate' : 'start_date';
            $fields[] = $startField;
            $values[] = $input['startDate'] ?? '';
            $types .= 's';
        }
        
        if (in_array('enddate', $columns) || in_array('end_date', $columns)) {
            $endField = in_array('enddate', $columns) ? 'endDate' : 'end_date';
            $fields[] = $endField;
            $values[] = $input['endDate'] ?? '';
            $types .= 's';
        }
        
        // signingInfo 컬럼이 없으면 summary에 저장 (가능한 경우)
        $signingInfo = isset($input['signingInfo']) ? trim((string)$input['signingInfo']) : '';
        if ($signingInfo !== '') {
            if (in_array('signinginfo', $columns) || in_array('signing_info', $columns)) {
                $signingField = in_array('signinginfo', $columns) ? 'signingInfo' : 'signing_info';
                $fields[] = $signingField;
                $values[] = $signingInfo;
                $types .= 's';
            } elseif (in_array('summary', $columns)) {
                $fields[] = 'summary';
                $values[] = $signingInfo;
                $types .= 's';
            }
        }

        // 이미지 업로드(선택)
        // - 신규: noticeImageEn / noticeImageTl (각 섹션별 이미지)
        // - 레거시: noticeImage (단일 이미지)
        $saveNoticeUpload = function(string $fileKey, string $prefix) {
            if (empty($_FILES[$fileKey]) || ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
            $tmp = $_FILES[$fileKey]['tmp_name'];
            $orig = $_FILES[$fileKey]['name'] ?? $prefix;
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') $ext = 'jpg';
            if (!in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
                send_error_response('Notice image must be an image file', 400);
            }
            $dir = __DIR__ . '/../../../uploads/notices/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $fn = $prefix . '_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
            $abs = $dir . $fn;
            if (!move_uploaded_file($tmp, $abs)) {
                send_error_response('Failed to upload notice image', 500);
            }
            return 'uploads/notices/' . $fn; // 상대 경로로 저장(기존과 동일)
        };

        $imgEn = $saveNoticeUpload('noticeImageEn', 'notice_en');
        $imgTl = $saveNoticeUpload('noticeImageTl', 'notice_tl');
        $imgLegacy = '';
        if ($imgEn === '' && $imgTl === '') {
            $imgLegacy = $saveNoticeUpload('noticeImage', 'notice');
        }

        $imageUrlValue = '';
        if ($imgEn !== '' || $imgTl !== '') {
            $imageUrlValue = json_encode([
                // ko는 기존 사용자 페이지 fallback을 위해 en을 우선값으로 둔다.
                'ko' => $imgEn !== '' ? $imgEn : ($imgTl !== '' ? $imgTl : ''),
                'en' => $imgEn,
                'tl' => $imgTl,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($imgLegacy !== '') {
            $imageUrlValue = $imgLegacy;
        }

        if ($imageUrlValue !== '') {
            if (in_array('imageurl', $columns, true)) {
                $fields[] = 'imageUrl';
                $values[] = $imageUrlValue;
                $types .= 's';
            } else {
                // imageUrl 컬럼이 없으면 content에 이미지 태그로 삽입(스키마 변경 없이 동작)
                // - content가 JSON이면 언어별로 삽입
                // - 아니면 문자열 앞에 삽입
                if (isset($values[1]) && is_string($values[1])) {
                    $c = $values[1];
                    $cTrim = trim($c);
                    if ($cTrim !== '' && $cTrim[0] === '{') {
                        $obj = json_decode($cTrim, true);
                        if (is_array($obj)) {
                            foreach (['ko', 'en', 'tl'] as $lang) {
                                $rel = '';
                                if ($lang === 'en') $rel = $imgEn;
                                else if ($lang === 'tl') $rel = $imgTl;
                                else $rel = ($imgEn !== '' ? $imgEn : ($imgTl !== '' ? $imgTl : $imgLegacy));
                                if ($rel === '') continue;
                                $obj[$lang] = '<p><img src="/' . $rel . '" alt=""></p>' . (string)($obj[$lang] ?? '');
                            }
                            $values[1] = json_encode($obj, JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $rel = ($imgLegacy !== '' ? $imgLegacy : ($imgEn !== '' ? $imgEn : $imgTl));
                        if ($rel !== '') $values[1] = '<p><img src="/' . $rel . '" alt=""></p>' . $values[1];
                    }
                }
            }
        }
        
        if (in_array('status', $columns)) {
            // notices.status enum: draft/published/archived
            $rawStatus = isset($input['status']) ? strtolower(trim((string)$input['status'])) : '';
            $status = 'published';
            if ($rawStatus === 'draft') $status = 'draft';
            elseif (in_array($rawStatus, ['published', 'active'], true)) $status = 'published';
            elseif (in_array($rawStatus, ['archived', 'inactive'], true)) $status = 'archived';
            $fields[] = 'status';
            $values[] = $status;
            $types .= 's';
        }

        // authorId (가능한 경우)
        if (in_array('authorid', $columns) || in_array('author_id', $columns)) {
            $authorId = $_SESSION['admin_accountId'] ?? null;
            if (!empty($authorId)) {
                $authorField = in_array('authorid', $columns) ? 'authorId' : 'author_id';
                $fields[] = $authorField;
                $values[] = intval($authorId);
                $types .= 'i';
            }
        }
        
        $sql = "INSERT INTO notices (" . implode(', ', $fields) . ") VALUES (" . str_repeat('?,', count($fields) - 1) . "?)";
        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $values);
        $stmt->execute();
        $noticeId = $conn->insert_id;
        $stmt->close();
        
        send_success_response(['noticeId' => $noticeId], 'Notice created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create notice: ' . $e->getMessage());
    }
}

function updateNotice($conn, $input) {
    try {
        ensureNoticesExtraColumns($conn);
        $noticeId = $input['noticeId'] ?? $input['id'] ?? null;
        if (empty($noticeId)) {
            send_error_response('Notice ID is required');
        }

        // 컬럼 존재 여부에 맞춰 업데이트 (현재 스키마에 없는 startDate/endDate/signingInfo 때문에 저장 실패하던 문제 해결)
        $columns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM notices");
        while ($columnResult && ($col = $columnResult->fetch_assoc())) {
            $columns[] = strtolower($col['Field']);
        }

        $updates = [];
        $values = [];
        $types = '';

        // 기존 imageUrl 읽기(언어별 merge/삭제, 파일 정리 목적)
        $existingImageUrl = null;
        if (in_array('imageurl', $columns, true)) {
            $st0 = $conn->prepare("SELECT imageUrl FROM notices WHERE noticeId = ? LIMIT 1");
            if ($st0) {
                $nid0 = intval($noticeId);
                $st0->bind_param('i', $nid0);
                $st0->execute();
                $existingImageUrl = ($st0->get_result()->fetch_assoc()['imageUrl'] ?? null);
                $st0->close();
            }
        }

        $safeUnlinkNoticeImage = function($url) {
            try {
                $u = trim((string)($url ?? ''));
                if ($u === '') return;
                $u = str_replace('\\', '/', $u);
                if ($u[0] !== '/') $u = '/' . $u;
                if (!str_starts_with($u, '/uploads/notices/')) return;
                if (str_contains($u, '..')) return;
                $abs = realpath(__DIR__ . '/../../../' . ltrim($u, '/'));
                $root = realpath(__DIR__ . '/../../../uploads/notices');
                if ($abs === false || $root === false) return;
                if (!str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) return;
                if (is_file($abs) && is_writable($abs)) {
                    @unlink($abs);
                }
            } catch (Throwable $e) { /* ignore */ }
        };

        $parseImageJson = function($raw): array {
            $s = trim((string)($raw ?? ''));
            if ($s === '') return ['ko' => '', 'en' => '', 'tl' => '', '__isJson' => false];
            if (str_starts_with($s, '{') && str_ends_with($s, '}')) {
                $j = json_decode($s, true);
                if (is_array($j)) {
                    return [
                        'ko' => trim((string)($j['ko'] ?? '')),
                        'en' => trim((string)($j['en'] ?? '')),
                        'tl' => trim((string)($j['tl'] ?? '')),
                        '__isJson' => true
                    ];
                }
            }
            // legacy single path
            $p = trim((string)$s);
            return ['ko' => $p, 'en' => $p, 'tl' => $p, '__isJson' => false];
        };

        $saveNoticeUpload = function(string $fileKey, string $prefix) {
            if (empty($_FILES[$fileKey]) || ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
            $tmp = $_FILES[$fileKey]['tmp_name'];
            $orig = $_FILES[$fileKey]['name'] ?? $prefix;
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') $ext = 'jpg';
            if (!in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
                send_error_response('Notice image must be an image file', 400);
            }
            $dir = __DIR__ . '/../../../uploads/notices/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $fn = $prefix . '_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
            $abs = $dir . $fn;
            if (!move_uploaded_file($tmp, $abs)) {
                send_error_response('Failed to upload notice image', 500);
            }
            return 'uploads/notices/' . $fn;
        };

        if (isset($input['title'])) {
            $updates[] = "title = ?";
            $values[] = (string)$input['title'];
            $types .= 's';
        }
        if (isset($input['content'])) {
            $updates[] = "content = ?";
            $values[] = (string)$input['content'];
            $types .= 's';
        }

        // start/end (있을 때만)
        if (isset($input['startDate']) && (in_array('startdate', $columns, true) || in_array('start_date', $columns, true))) {
            $field = in_array('startdate', $columns, true) ? 'startDate' : 'start_date';
            $updates[] = "$field = NULLIF(?, '')";
            $values[] = (string)$input['startDate'];
            $types .= 's';
        }
        if (isset($input['endDate']) && (in_array('enddate', $columns, true) || in_array('end_date', $columns, true))) {
            $field = in_array('enddate', $columns, true) ? 'endDate' : 'end_date';
            $updates[] = "$field = NULLIF(?, '')";
            $values[] = (string)$input['endDate'];
            $types .= 's';
        }

        // signingInfo: 컬럼 없으면 summary에 저장
        if (isset($input['signingInfo'])) {
            $val = trim((string)$input['signingInfo']);
            if (in_array('signinginfo', $columns, true) || in_array('signing_info', $columns, true)) {
                $field = in_array('signinginfo', $columns, true) ? 'signingInfo' : 'signing_info';
                $updates[] = "$field = NULLIF(?, '')";
                $values[] = $val;
                $types .= 's';
            } elseif (in_array('summary', $columns, true)) {
                $updates[] = "summary = NULLIF(?, '')";
                $values[] = $val;
                $types .= 's';
            }
        }

        if (isset($input['status']) && in_array('status', $columns, true)) {
            $rawStatus = strtolower(trim((string)$input['status']));
            $status = 'published';
            if ($rawStatus === 'draft') $status = 'draft';
            elseif (in_array($rawStatus, ['published', 'active'], true)) $status = 'published';
            elseif (in_array($rawStatus, ['archived', 'inactive'], true)) $status = 'archived';
            $updates[] = "status = ?";
            $values[] = $status;
            $types .= 's';
        }

        // 이미지(언어별): noticeImageEn / noticeImageTl 업로드 + deleteImageEn/deleteImageTl 플래그 처리
        if (in_array('imageurl', $columns, true)) {
            $imgState = $parseImageJson($existingImageUrl);
            $delEn = isset($input['deleteImageEn']) && (string)$input['deleteImageEn'] === '1';
            $delTl = isset($input['deleteImageTl']) && (string)$input['deleteImageTl'] === '1';

            if ($delEn && $imgState['en'] !== '') $safeUnlinkNoticeImage('/' . ltrim($imgState['en'], '/'));
            if ($delTl && $imgState['tl'] !== '') $safeUnlinkNoticeImage('/' . ltrim($imgState['tl'], '/'));
            if ($delEn) $imgState['en'] = '';
            if ($delTl) $imgState['tl'] = '';

            $newEn = $saveNoticeUpload('noticeImageEn', 'notice_en');
            $newTl = $saveNoticeUpload('noticeImageTl', 'notice_tl');
            if ($newEn !== '') {
                if ($imgState['en'] !== '') $safeUnlinkNoticeImage('/' . ltrim($imgState['en'], '/'));
                $imgState['en'] = $newEn;
            }
            if ($newTl !== '') {
                if ($imgState['tl'] !== '') $safeUnlinkNoticeImage('/' . ltrim($imgState['tl'], '/'));
                $imgState['tl'] = $newTl;
            }

            // ko fallback: en 우선, 그 다음 tl
            $imgState['ko'] = $imgState['en'] !== '' ? $imgState['en'] : ($imgState['tl'] !== '' ? $imgState['tl'] : '');

            $final = '';
            if ($imgState['en'] !== '' || $imgState['tl'] !== '') {
                $final = json_encode(['ko' => $imgState['ko'], 'en' => $imgState['en'], 'tl' => $imgState['tl']], JSON_UNESCAPED_UNICODE);
            } else {
                $final = null;
            }

            $updates[] = "imageUrl = ?";
            $values[] = $final;
            $types .= 's';
        }
        
        if (empty($updates)) {
            send_error_response('No fields to update');
        }
        
        $values[] = $noticeId;
        $types .= 'i';
        
        $sql = "UPDATE notices SET " . implode(', ', $updates) . " WHERE noticeId = ?";
        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $values);
        $stmt->execute();
        $stmt->close();
        
        send_success_response([], 'Notice updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update notice: ' . $e->getMessage());
    }
}

function deleteNotice($conn, $input) {
    try {
        $noticeId = $input['noticeId'] ?? $input['id'] ?? null;
        if (empty($noticeId)) {
            send_error_response('Notice ID is required');
        }
        $stmt = $conn->prepare("DELETE FROM notices WHERE noticeId = ?");
        if (!$stmt) send_error_response('Failed to prepare notice delete: ' . $conn->error, 500);
        $nid = intval($noticeId);
        $stmt->bind_param('i', $nid);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to delete notice: ' . $err, 500);
        }
        $stmt->close();
        send_success_response([], 'Notice deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete notice: ' . $e->getMessage(), 500);
    }
}

// ========== 매출 통계 함수들 ==========

function getSalesByDate($conn, $input) {
    try {
        $period = $input['period'] ?? 'daily';
        $startDate = $input['startDate'] ?? null;
        $endDate = $input['endDate'] ?? null;
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;
        
        // 날짜 범위 계산
        if (!$startDate || !$endDate) {
            $today = new DateTime();
            switch($period) {
                case 'daily':
                    $startDate = $today->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
                    break;
                case 'weekly':
                    $dayOfWeek = $today->format('w');
                    $diff = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $startDate = $today->modify('-' . $diff . ' days')->format('Y-m-d');
                    $endDate = (clone $today)->modify('+6 days')->format('Y-m-d');
                    break;
                case 'monthly':
                    $startDate = $today->format('Y-m-01');
                    $endDate = $today->format('Y-m-t');
                    break;
                case 'annual':
                    $startDate = $today->format('Y-01-01');
                    $endDate = $today->format('Y-12-31');
                    break;
                default:
                    $startDate = $today->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
            }
        }
        
        // 기간별 그룹화 및 시간대 계산
        $groupByClause = '';
        $timePeriodSelect = '';
        switch($period) {
            case 'daily':
                $groupByClause = "HOUR(b.createdAt)";
                $timePeriodSelect = "CONCAT(HOUR(b.createdAt), ' AM') as timePeriod, HOUR(b.createdAt) as legend";
                break;
            case 'weekly':
                $groupByClause = "DATE(b.createdAt)";
                $timePeriodSelect = "DATE_FORMAT(b.createdAt, '%Y-%m-%d') as timePeriod, DATE(b.createdAt) as legend";
                break;
            case 'monthly':
                $groupByClause = "DATE(b.createdAt)";
                $timePeriodSelect = "DATE_FORMAT(b.createdAt, '%Y-%m-%d') as timePeriod, DATE(b.createdAt) as legend";
                break;
            case 'annual':
                $groupByClause = "MONTH(b.createdAt)";
                $timePeriodSelect = "CONCAT(MONTH(b.createdAt), '월') as timePeriod, MONTH(b.createdAt) as legend";
                break;
            default:
                $groupByClause = "HOUR(b.createdAt)";
                $timePeriodSelect = "CONCAT(HOUR(b.createdAt), ' AM') as timePeriod, HOUR(b.createdAt) as legend";
        }
        
        $countSql = "SELECT COUNT(DISTINCT $groupByClause) as total
                     FROM bookings b
                     WHERE DATE(b.createdAt) BETWEEN ? AND ?
                     AND b.bookingStatus NOT IN ('cancelled','draft')";

        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param('ss', $startDate, $endDate);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalCount = $countResult->fetch_assoc()['total'];
        $countStmt->close();

        $sql = "SELECT
            $timePeriodSelect,
            COUNT(*) as bookingCount,
            SUM(b.totalAmount) as totalAmount
        FROM bookings b
        WHERE DATE(b.createdAt) BETWEEN ? AND ?
        AND b.bookingStatus NOT IN ('cancelled','draft')
        GROUP BY $groupByClause
        ORDER BY legend DESC
        LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssii', $startDate, $endDate, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = [
                'timePeriod' => $row['timePeriod'] ?? '',
                'legend' => $row['legend'] ?? '',
                'bookingCount' => intval($row['bookingCount'] ?? 0),
                'totalAmount' => floatval($row['totalAmount'] ?? 0)
            ];
        }
        $stmt->close();
        
        send_success_response([
            'sales' => $sales,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get sales by date: ' . $e->getMessage());
    }
}

function getSalesByProduct($conn, $input) {
    try {
        $period = $input['period'] ?? 'daily';
        $startDate = $input['startDate'] ?? null;
        $endDate = $input['endDate'] ?? null;
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;
        
        // 날짜 범위 계산
        $whereConditions = ["b.bookingStatus NOT IN ('cancelled','draft')"];
        $params = [];
        $types = '';

        if ($period !== 'all' && $startDate && $endDate) {
            $whereConditions[] = "DATE(b.createdAt) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'ss';
        } elseif ($period === 'all') {
            // 전체 기간이면 날짜 조건 없음
        } else {
            // 기본값: 오늘
            $today = date('Y-m-d');
            $whereConditions[] = "DATE(b.createdAt) = ?";
            $params[] = $today;
            $types .= 's';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // 집계된 데이터 기간(전체기간일 때도 표시용)
        $dateRangeLabel = '';
        try {
            $rangeSql = "SELECT MIN(DATE(b.createdAt)) as minDate, MAX(DATE(b.createdAt)) as maxDate FROM bookings b $whereClause";
            $rangeStmt = null;
            if (!empty($params)) {
                $rangeStmt = $conn->prepare($rangeSql);
                $rangeStmt->bind_param($types, ...$params);
                $rangeStmt->execute();
                $rangeRes = $rangeStmt->get_result();
            } else {
                $rangeRes = $conn->query($rangeSql);
            }
            $range = $rangeRes ? $rangeRes->fetch_assoc() : null;
            if ($rangeStmt) $rangeStmt->close();
            $minDate = $range['minDate'] ?? '';
            $maxDate = $range['maxDate'] ?? '';
            if (!empty($minDate) && !empty($maxDate)) {
                $dateRangeLabel = $minDate . ' ~ ' . $maxDate;
            }
        } catch (Exception $e) {
            $dateRangeLabel = '';
        }
        
        $countSql = "SELECT COUNT(DISTINCT b.packageName) as total 
                     FROM bookings b
                     $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        // packages 테이블에 viewCount 컬럼이 없으므로 views는 0으로 반환
        $sql = "SELECT 
            COALESCE(NULLIF(b.packageName, ''), p.packageName) as packageName,
            0 as views,
            COUNT(*) as reservations,
            SUM(CASE WHEN b.paymentStatus = 'paid' THEN b.totalAmount ELSE 0 END) as salesAmount
        FROM bookings b
        LEFT JOIN packages p ON b.packageId = p.packageId
        $whereClause
        GROUP BY COALESCE(NULLIF(b.packageName, ''), p.packageName)
        ORDER BY reservations DESC
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($dataTypes, ...$dataParams);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'packageName' => $row['packageName'] ?? '',
                'views' => intval($row['views'] ?? 0),
                'reservations' => intval($row['reservations'] ?? 0),
                'salesAmount' => floatval($row['salesAmount'] ?? 0)
            ];
        }
        $stmt->close();
        
        send_success_response([
            'products' => $products,
            'dateRangeLabel' => $dateRangeLabel,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get sales by product: ' . $e->getMessage());
    }
}

function getProductSalesOverview($conn, $input) {
    try {
        $landOnlyPattern = "(LAND ONLY)%";

        // Query 1: 패키지별 전체 재고
        $sqlPackages = "SELECT p.packageId, p.packageName,
                               SUM(pad.capacity) as totalCapacity,
                               p.packageName LIKE ? as isLandOnly
                        FROM packages p
                        INNER JOIN package_available_dates pad ON pad.package_id = p.packageId
                        WHERE pad.capacity > 0
                        GROUP BY p.packageId
                        ORDER BY p.packageName ASC";
        $stmt1 = $conn->prepare($sqlPackages);
        $stmt1->bind_param('s', $landOnlyPattern);
        $stmt1->execute();
        $pkgResult = $stmt1->get_result();
        $packages = [];
        while ($row = $pkgResult->fetch_assoc()) {
            $packages[$row['packageId']] = [
                'packageId' => intval($row['packageId']),
                'packageName' => $row['packageName'],
                'totalCapacity' => intval($row['totalCapacity']),
                'isLandOnly' => intval($row['isLandOnly']) === 1,
                'allSeats' => 0, 'allAmount' => 0, 'allCount' => 0,
                'confirmedSeats' => 0, 'confirmedAmount' => 0, 'confirmedCount' => 0,
                'dates' => []
            ];
        }
        $stmt1->close();

        // Query 2: 패키지별 예약 집계 (all / confirmed)
        $sqlBookings = "SELECT b.packageId,
            SUM(CASE WHEN b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) as allSeats,
            SUM(CASE WHEN b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) as allAmount,
            COUNT(CASE WHEN b.bookingStatus NOT IN ('cancelled','draft')
                       AND COALESCE(b.paymentStatus,'') != 'refunded'
                  THEN 1 ELSE NULL END) as allCount,
            SUM(CASE WHEN b.bookingStatus = 'confirmed'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) as confirmedSeats,
            SUM(CASE WHEN b.bookingStatus = 'confirmed'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) as confirmedAmount,
            COUNT(CASE WHEN b.bookingStatus = 'confirmed' THEN 1 ELSE NULL END) as confirmedCount
            FROM bookings b
            WHERE b.packageId IS NOT NULL
            GROUP BY b.packageId";
        $bookResult = $conn->query($sqlBookings);
        while ($row = $bookResult->fetch_assoc()) {
            $pid = $row['packageId'];
            if (isset($packages[$pid])) {
                $packages[$pid]['allSeats'] = intval($row['allSeats']);
                $packages[$pid]['allAmount'] = floatval($row['allAmount']);
                $packages[$pid]['allCount'] = intval($row['allCount']);
                $packages[$pid]['confirmedSeats'] = intval($row['confirmedSeats']);
                $packages[$pid]['confirmedAmount'] = floatval($row['confirmedAmount']);
                $packages[$pid]['confirmedCount'] = intval($row['confirmedCount']);
            }
        }

        // Query 3: 날짜별 상세 (재고 + 예약)
        $sqlDates = "SELECT pad.package_id as packageId, pad.available_date, pad.capacity, pad.status,
            COALESCE(SUM(CASE WHEN b.bookingStatus NOT IN ('cancelled','draft')
                              AND COALESCE(b.paymentStatus,'') != 'refunded'
                         THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) END), 0) as allSeats,
            COALESCE(SUM(CASE WHEN b.bookingStatus NOT IN ('cancelled','draft')
                              AND COALESCE(b.paymentStatus,'') != 'refunded'
                         THEN COALESCE(b.totalAmount,0) END), 0) as allAmount,
            COALESCE(SUM(CASE WHEN b.bookingStatus = 'confirmed'
                         THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) END), 0) as confirmedSeats,
            COALESCE(SUM(CASE WHEN b.bookingStatus = 'confirmed'
                         THEN COALESCE(b.totalAmount,0) END), 0) as confirmedAmount
            FROM package_available_dates pad
            LEFT JOIN bookings b ON b.packageId = pad.package_id AND b.departureDate = pad.available_date
            WHERE pad.capacity > 0
            GROUP BY pad.package_id, pad.available_date
            ORDER BY pad.available_date ASC";
        $dateResult = $conn->query($sqlDates);
        while ($row = $dateResult->fetch_assoc()) {
            $pid = $row['packageId'];
            if (isset($packages[$pid])) {
                $packages[$pid]['dates'][] = [
                    'date' => $row['available_date'],
                    'capacity' => intval($row['capacity']),
                    'status' => $row['status'],
                    'allSeats' => intval($row['allSeats']),
                    'allAmount' => floatval($row['allAmount']),
                    'confirmedSeats' => intval($row['confirmedSeats']),
                    'confirmedAmount' => floatval($row['confirmedAmount'])
                ];
            }
        }

        // Grand totals
        $grandTotals = [
            'totalCapacity' => 0, 'allSeats' => 0, 'allAmount' => 0,
            'confirmedSeats' => 0, 'confirmedAmount' => 0,
            'landOnlyCapacity' => 0, 'landOnlyAllSeats' => 0, 'landOnlyAllAmount' => 0
        ];
        foreach ($packages as $p) {
            if ($p['isLandOnly']) {
                $grandTotals['landOnlyCapacity'] += $p['totalCapacity'];
                $grandTotals['landOnlyAllSeats'] += $p['allSeats'];
                $grandTotals['landOnlyAllAmount'] += $p['allAmount'];
            } else {
                $grandTotals['totalCapacity'] += $p['totalCapacity'];
                $grandTotals['allSeats'] += $p['allSeats'];
                $grandTotals['allAmount'] += $p['allAmount'];
                $grandTotals['confirmedSeats'] += $p['confirmedSeats'];
                $grandTotals['confirmedAmount'] += $p['confirmedAmount'];
            }
        }

        send_success_response([
            'packages' => array_values($packages),
            'grandTotals' => $grandTotals
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get product sales overview: ' . $e->getMessage());
    }
}

function downloadSalesByDate($conn, $input) {
    try {
        $period = $input['period'] ?? 'daily';
        $startDate = $input['startDate'] ?? null;
        $endDate = $input['endDate'] ?? null;
        
        // 날짜 범위 계산 (getSalesByDate와 동일한 로직)
        if (!$startDate || !$endDate) {
            $today = new DateTime();
            switch($period) {
                case 'daily':
                    $startDate = $today->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
                    break;
                case 'weekly':
                    $dayOfWeek = $today->format('w');
                    $diff = $dayOfWeek == 0 ? 6 : $dayOfWeek - 1;
                    $startDate = $today->modify('-' . $diff . ' days')->format('Y-m-d');
                    $endDate = (clone $today)->modify('+6 days')->format('Y-m-d');
                    break;
                case 'monthly':
                    $startDate = $today->format('Y-m-01');
                    $endDate = $today->format('Y-m-t');
                    break;
                case 'annual':
                    $startDate = $today->format('Y-01-01');
                    $endDate = $today->format('Y-12-31');
                    break;
            }
        }
        
        $groupByClause = '';
        $timePeriodSelect = '';
        switch($period) {
            case 'daily':
                $groupByClause = "HOUR(b.createdAt)";
                $timePeriodSelect = "CONCAT(HOUR(b.createdAt), ' AM') as timePeriod";
                break;
            case 'weekly':
                $groupByClause = "DATE(b.createdAt)";
                $timePeriodSelect = "DATE_FORMAT(b.createdAt, '%Y-%m-%d') as timePeriod";
                break;
            case 'monthly':
                $groupByClause = "DATE(b.createdAt)";
                $timePeriodSelect = "DATE_FORMAT(b.createdAt, '%Y-%m-%d') as timePeriod";
                break;
            case 'annual':
                $groupByClause = "MONTH(b.createdAt)";
                $timePeriodSelect = "CONCAT(MONTH(b.createdAt), '월') as timePeriod";
                break;
            default:
                $groupByClause = "HOUR(b.createdAt)";
                $timePeriodSelect = "CONCAT(HOUR(b.createdAt), ' AM') as timePeriod";
        }
        
        $sql = "SELECT
            $timePeriodSelect,
            COUNT(*) as bookingCount,
            SUM(b.totalAmount) as totalAmount
        FROM bookings b
        WHERE DATE(b.createdAt) BETWEEN ? AND ?
        AND b.bookingStatus NOT IN ('cancelled','draft')
        GROUP BY $groupByClause
        ORDER BY $groupByClause DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales_by_date_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['No', 'Time-zone', 'Total sales amount (₱)'], ',', '"', '\\');
        
        $rowNum = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $rowNum++,
                $row['timePeriod'] ?? '',
                number_format($row['totalAmount'] ?? 0)
            ], ',', '"', '\\');
        }
        
        fclose($output);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download sales by date: ' . $e->getMessage());
    }
}

function downloadSalesByProduct($conn, $input) {
    try {
        $period = $input['period'] ?? 'daily';
        $startDate = $input['startDate'] ?? null;
        $endDate = $input['endDate'] ?? null;

        $whereConditions = ["b.bookingStatus NOT IN ('cancelled','draft')"];
        $params = [];
        $types = '';

        if ($period !== 'all' && $startDate && $endDate) {
            $whereConditions[] = "DATE(b.createdAt) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'ss';
        } elseif ($period === 'all') {
            // 전체 기간
        } else {
            $today = date('Y-m-d');
            $whereConditions[] = "DATE(b.createdAt) = ?";
            $params[] = $today;
            $types .= 's';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // packages 테이블에 viewCount 컬럼이 없으므로 views는 0으로 반환
        $sql = "SELECT 
            COALESCE(NULLIF(b.packageName, ''), p.packageName) as packageName,
            0 as views,
            COUNT(*) as reservations,
            SUM(CASE WHEN b.paymentStatus = 'paid' THEN b.totalAmount ELSE 0 END) as salesAmount
        FROM bookings b
        LEFT JOIN packages p ON b.packageId = p.packageId
        $whereClause
        GROUP BY COALESCE(NULLIF(b.packageName, ''), p.packageName)
        ORDER BY reservations DESC";
        
        $stmt = null;
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales_by_product_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['No', 'Product Name', 'Views', 'Number of reservations', 'Reservation rate', 'Sales Amount (₱)'], ',', '"', '\\');
        
        $rowNum = 1;
        while ($row = $result->fetch_assoc()) {
            $views = intval($row['views'] ?? 0);
            $reservations = intval($row['reservations'] ?? 0);
            $reservationRate = $views > 0 ? number_format(($reservations / $views) * 100, 2) . '%' : '0%';
            
            fputcsv($output, [
                $rowNum++,
                $row['packageName'] ?? '',
                number_format($views),
                number_format($reservations),
                $reservationRate,
                number_format($row['salesAmount'] ?? 0)
            ], ',', '"', '\\');
        }
        
        fclose($output);
        if ($stmt) $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download sales by product: ' . $e->getMessage());
    }
}

// ========== Sales Dashboard 함수들 ==========

function getSalesDashboard($conn, $input) {
    try {
        $page = max(1, intval($input['page'] ?? 1));
        $limit = max(1, min(100, intval($input['limit'] ?? 50)));

        $landOnlyCond = "p.packageName LIKE '(LAND ONLY)%'";

        // Query 1: 월별 재고 (normal / landOnly 분리)
        $sqlCapacity = "SELECT DATE_FORMAT(pad.available_date, '%Y-%m') AS yearMonth,
                               SUM(CASE WHEN NOT {$landOnlyCond} THEN pad.capacity ELSE 0 END) AS normalCapacity,
                               SUM(CASE WHEN {$landOnlyCond} THEN pad.capacity ELSE 0 END) AS landOnlyCapacity
                        FROM package_available_dates pad
                        INNER JOIN packages p ON pad.package_id = p.packageId
                        WHERE pad.capacity > 0
                        GROUP BY DATE_FORMAT(pad.available_date, '%Y-%m')
                        ORDER BY yearMonth ASC";
        $capResult = $conn->query($sqlCapacity);
        $capacityMap = [];
        while ($row = $capResult->fetch_assoc()) {
            $capacityMap[$row['yearMonth']] = [
                'normalCapacity' => intval($row['normalCapacity']),
                'landOnlyCapacity' => intval($row['landOnlyCapacity']),
            ];
        }

        // Query 2: 월별 예약 집계 (3탭: all/confirmed = non-land-only, landOnly)
        $sqlBookings = "SELECT DATE_FORMAT(b.departureDate, '%Y-%m') AS yearMonth,
            -- All (non-land-only, cancelled/rejected/refunded 제외)
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS allBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS allTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus NOT IN ('cancelled','draft')
                       AND COALESCE(b.paymentStatus,'') != 'refunded'
                  THEN 1 ELSE NULL END) AS allBookingCount,
            -- Confirmed (non-land-only)
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus = 'confirmed'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS confirmedBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus = 'confirmed'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS confirmedTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus = 'confirmed'
                  THEN 1 ELSE NULL END) AS confirmedBookingCount,
            -- Land Only (cancelled/rejected/refunded 제외)
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS landOnlyBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS landOnlyTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus NOT IN ('cancelled','draft')
                       AND COALESCE(b.paymentStatus,'') != 'refunded'
                  THEN 1 ELSE NULL END) AS landOnlyBookingCount
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            GROUP BY DATE_FORMAT(b.departureDate, '%Y-%m')
            ORDER BY yearMonth ASC";
        $bookResult = $conn->query($sqlBookings);
        $bookingMap = [];
        while ($row = $bookResult->fetch_assoc()) {
            $bookingMap[$row['yearMonth']] = $row;
        }

        // Merge: capacity가 있는 월만 포함
        $months = [];
        foreach ($capacityMap as $ym => $capRow) {
            $b = $bookingMap[$ym] ?? null;
            $months[] = [
                'yearMonth' => $ym,
                'normalCapacity' => $capRow['normalCapacity'],
                'landOnlyCapacity' => $capRow['landOnlyCapacity'],
                'allBookedSeats' => intval($b['allBookedSeats'] ?? 0),
                'allTotalAmount' => floatval($b['allTotalAmount'] ?? 0),
                'allBookingCount' => intval($b['allBookingCount'] ?? 0),
                'confirmedBookedSeats' => intval($b['confirmedBookedSeats'] ?? 0),
                'confirmedTotalAmount' => floatval($b['confirmedTotalAmount'] ?? 0),
                'confirmedBookingCount' => intval($b['confirmedBookingCount'] ?? 0),
                'landOnlyBookedSeats' => intval($b['landOnlyBookedSeats'] ?? 0),
                'landOnlyTotalAmount' => floatval($b['landOnlyTotalAmount'] ?? 0),
                'landOnlyBookingCount' => intval($b['landOnlyBookingCount'] ?? 0),
            ];
        }

        // Grand totals
        $keys = ['normalCapacity','landOnlyCapacity',
                 'allBookedSeats','allTotalAmount','allBookingCount',
                 'confirmedBookedSeats','confirmedTotalAmount','confirmedBookingCount',
                 'landOnlyBookedSeats','landOnlyTotalAmount','landOnlyBookingCount'];
        $grandTotals = array_fill_keys($keys, 0);
        foreach ($months as $m) {
            foreach ($keys as $k) { $grandTotals[$k] += $m[$k]; }
        }

        // Pagination
        $totalCount = count($months);
        $totalPages = max(1, ceil($totalCount / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;
        $pagedMonths = array_slice($months, $offset, $limit);

        send_success_response([
            'months' => $pagedMonths,
            'grandTotals' => $grandTotals,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to load sales dashboard: ' . $e->getMessage());
    }
}

function downloadSalesDashboard($conn, $input) {
    try {
        $landOnlyCond = "p.packageName LIKE '(LAND ONLY)%'";

        // Query 1: 월별 재고 (normal / landOnly 분리)
        $sqlCapacity = "SELECT DATE_FORMAT(pad.available_date, '%Y-%m') AS yearMonth,
                               SUM(CASE WHEN NOT {$landOnlyCond} THEN pad.capacity ELSE 0 END) AS normalCapacity,
                               SUM(CASE WHEN {$landOnlyCond} THEN pad.capacity ELSE 0 END) AS landOnlyCapacity
                        FROM package_available_dates pad
                        INNER JOIN packages p ON pad.package_id = p.packageId
                        WHERE pad.capacity > 0
                        GROUP BY DATE_FORMAT(pad.available_date, '%Y-%m')
                        ORDER BY yearMonth ASC";
        $capResult = $conn->query($sqlCapacity);
        $capacityMap = [];
        while ($row = $capResult->fetch_assoc()) {
            $capacityMap[$row['yearMonth']] = [
                'normalCapacity' => intval($row['normalCapacity']),
                'landOnlyCapacity' => intval($row['landOnlyCapacity']),
            ];
        }

        // Query 2: 월별 예약 집계 (3탭)
        $sqlBookings = "SELECT DATE_FORMAT(b.departureDate, '%Y-%m') AS yearMonth,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS allBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS allTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus NOT IN ('cancelled','draft')
                       AND COALESCE(b.paymentStatus,'') != 'refunded'
                  THEN 1 ELSE NULL END) AS allBookingCount,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus = 'confirmed'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS confirmedBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus = 'confirmed'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS confirmedTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) NOT LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus = 'confirmed'
                  THEN 1 ELSE NULL END) AS confirmedBookingCount,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0) ELSE 0 END) AS landOnlyBookedSeats,
            SUM(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                     AND b.bookingStatus NOT IN ('cancelled','draft')
                     AND COALESCE(b.paymentStatus,'') != 'refunded'
                THEN COALESCE(b.totalAmount,0) ELSE 0 END) AS landOnlyTotalAmount,
            COUNT(CASE WHEN COALESCE(p.packageName, b.packageName) LIKE '(LAND ONLY)%%'
                       AND b.bookingStatus NOT IN ('cancelled','draft')
                       AND COALESCE(b.paymentStatus,'') != 'refunded'
                  THEN 1 ELSE NULL END) AS landOnlyBookingCount
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            GROUP BY DATE_FORMAT(b.departureDate, '%Y-%m')
            ORDER BY yearMonth ASC";
        $bookResult = $conn->query($sqlBookings);
        $bookingMap = [];
        while ($row = $bookResult->fetch_assoc()) {
            $bookingMap[$row['yearMonth']] = $row;
        }

        // Merge
        $keys = ['normalCapacity','landOnlyCapacity',
                 'allBookedSeats','allTotalAmount','allBookingCount',
                 'confirmedBookedSeats','confirmedTotalAmount','confirmedBookingCount',
                 'landOnlyBookedSeats','landOnlyTotalAmount','landOnlyBookingCount'];
        $months = [];
        $grandTotals = array_fill_keys($keys, 0);
        foreach ($capacityMap as $ym => $capRow) {
            $b = $bookingMap[$ym] ?? null;
            $row = [
                'yearMonth' => $ym,
                'normalCapacity' => $capRow['normalCapacity'],
                'landOnlyCapacity' => $capRow['landOnlyCapacity'],
                'allBookedSeats' => intval($b['allBookedSeats'] ?? 0),
                'allTotalAmount' => floatval($b['allTotalAmount'] ?? 0),
                'allBookingCount' => intval($b['allBookingCount'] ?? 0),
                'confirmedBookedSeats' => intval($b['confirmedBookedSeats'] ?? 0),
                'confirmedTotalAmount' => floatval($b['confirmedTotalAmount'] ?? 0),
                'confirmedBookingCount' => intval($b['confirmedBookingCount'] ?? 0),
                'landOnlyBookedSeats' => intval($b['landOnlyBookedSeats'] ?? 0),
                'landOnlyTotalAmount' => floatval($b['landOnlyTotalAmount'] ?? 0),
                'landOnlyBookingCount' => intval($b['landOnlyBookingCount'] ?? 0),
            ];
            $months[] = $row;
            foreach ($keys as $k) { $grandTotals[$k] += $row[$k]; }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales_dashboard_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        fputcsv($output, [
            'No', 'Month',
            'Inventory', 'Booked Seats', 'Occupancy (%)', 'Total Amount', 'Bookings',
            'Confirmed Seats', 'Confirmed Occ (%)', 'Confirmed Amount', 'Confirmed Bookings',
            'LandOnly Inventory', 'LandOnly Seats', 'LandOnly Occ (%)', 'LandOnly Amount', 'LandOnly Bookings'
        ], ',', '"', '\\');

        $rowNum = 1;
        foreach ($months as $m) {
            $allOcc = $m['normalCapacity'] > 0 ? round($m['allBookedSeats'] / $m['normalCapacity'] * 100, 1) : 0;
            $confOcc = $m['normalCapacity'] > 0 ? round($m['confirmedBookedSeats'] / $m['normalCapacity'] * 100, 1) : 0;
            $loOcc = $m['landOnlyCapacity'] > 0 ? round($m['landOnlyBookedSeats'] / $m['landOnlyCapacity'] * 100, 1) : 0;
            fputcsv($output, [
                $rowNum++, $m['yearMonth'],
                number_format($m['normalCapacity']),
                number_format($m['allBookedSeats']), $allOcc.'%', number_format($m['allTotalAmount']), number_format($m['allBookingCount']),
                number_format($m['confirmedBookedSeats']), $confOcc.'%', number_format($m['confirmedTotalAmount']), number_format($m['confirmedBookingCount']),
                number_format($m['landOnlyCapacity']),
                number_format($m['landOnlyBookedSeats']), $loOcc.'%', number_format($m['landOnlyTotalAmount']), number_format($m['landOnlyBookingCount']),
            ], ',', '"', '\\');
        }

        // TOTAL row
        $g = $grandTotals;
        $allOccT = $g['normalCapacity'] > 0 ? round($g['allBookedSeats'] / $g['normalCapacity'] * 100, 1) : 0;
        $confOccT = $g['normalCapacity'] > 0 ? round($g['confirmedBookedSeats'] / $g['normalCapacity'] * 100, 1) : 0;
        $loOccT = $g['landOnlyCapacity'] > 0 ? round($g['landOnlyBookedSeats'] / $g['landOnlyCapacity'] * 100, 1) : 0;
        fputcsv($output, [
            '', 'TOTAL',
            number_format($g['normalCapacity']),
            number_format($g['allBookedSeats']), $allOccT.'%', number_format($g['allTotalAmount']), number_format($g['allBookingCount']),
            number_format($g['confirmedBookedSeats']), $confOccT.'%', number_format($g['confirmedTotalAmount']), number_format($g['confirmedBookingCount']),
            number_format($g['landOnlyCapacity']),
            number_format($g['landOnlyBookedSeats']), $loOccT.'%', number_format($g['landOnlyTotalAmount']), number_format($g['landOnlyBookingCount']),
        ], ',', '"', '\\');

        fclose($output);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download sales dashboard: ' . $e->getMessage());
    }
}

// ========== 비자 신청 관리 함수들 ==========

function mapVisaAdminUiToDbStatus(string $ui): string {
    $ui = strtolower(trim($ui));
    // 관리자 화면 UI: pending/reviewing/approved/rejected
    // DB enum: pending, document_required, under_review, approved, rejected, completed
    if ($ui === 'reviewing' || $ui === 'under_review') return 'under_review';
    if ($ui === 'approved') return 'approved';
    if ($ui === 'rejected') return 'rejected';
    // 기본: 서류 미비/요청 접수는 document_required로 저장(사용자 화면의 "서류 미비"와 일치)
    if ($ui === 'pending' || $ui === 'document_required') return 'document_required';
    // 예외적으로 DB 값이 직접 들어오면 그걸로
    return $ui;
}

function mapVisaDbToAdminUiStatus(string $db): string {
    $db = strtolower(trim($db));
    // 관리자 화면에서는 "요청 접수(pending)" / "심사중(reviewing)" / "발급 완료(approved)" / "반려(rejected)"
    if ($db === 'document_required' || $db === 'pending') return 'pending';
    if ($db === 'under_review') return 'reviewing';
    if ($db === 'approved' || $db === 'completed') return 'approved';
    if ($db === 'rejected') return 'rejected';
    return 'pending';
}

function extractVisaDocumentsFromNotes($notes): array {
    if ($notes === null) return [];
    $txt = trim((string)$notes);
    if ($txt === '') return [];
    $j = json_decode($txt, true);
    if (is_array($j) && isset($j['documents']) && is_array($j['documents'])) {
        return $j['documents'];
    }
    return [];
}

function extractVisaFileFromNotes($notes): string {
    if ($notes === null) return '';
    $txt = trim((string)$notes);
    if ($txt === '') return '';
    $j = json_decode($txt, true);
    if (!is_array($j)) return '';
    $v = $j['visaFile'] ?? ($j['visa_file'] ?? ($j['visaUrl'] ?? ($j['visaDocument'] ?? '')));
    $v = trim((string)$v);
    return $v;
}

// 파일 경로가 실제 uploads 하위에 존재하는지 확인
function __webroot_path(): string {
    static $root = null;
    if ($root !== null) return $root;
    // /var/www/html/admin/backend/api -> /var/www/html
    // NOTE: 이전 구현은 한 단계 더 올라가 /var/www 로 잡혀 uploads 실파일 확인이 항상 실패할 수 있었음
    $root = realpath(__DIR__ . '/../../..') ?: '';
    return $root;
}

function __uploads_abs_from_rel(string $rel): string {
    $rel = str_replace('\\', '/', trim($rel));
    if ($rel === '') return '';
    if (str_starts_with($rel, 'uploads/')) $rel = '/' . $rel;
    if (!str_starts_with($rel, '/uploads/')) return '';
    if (str_contains($rel, '..')) return '';
    $root = __webroot_path();
    if ($root === '') return '';
    $abs = realpath($root . '/' . ltrim($rel, '/'));
    if ($abs === false) return '';
    $uploadsRoot = realpath($root . '/uploads');
    if ($uploadsRoot === false) return '';
    if (!str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR)) return '';
    return $abs;
}

function __uploads_rel_normalize(string $p): string {
    $p = str_replace('\\', '/', trim($p));
    if ($p === '') return '';
    if (str_starts_with($p, 'uploads/')) $p = '/' . $p;
    // download.php는 /uploads/만 허용
    if (!str_starts_with($p, '/uploads/')) return '';
    return $p;
}

// notes에 기록된 경로가 실제와 불일치할 수 있어, uploads/visa 폴더에서 대체 파일을 찾아준다.
function __resolve_visa_doc_path(int $appId, string $normalizedPath, array $typeAliases = []): string {
    $normalizedPath = __uploads_rel_normalize($normalizedPath);
    if ($normalizedPath === '') return '';

    // 1) notes 경로가 실제로 존재하면 그대로 사용
    $abs = __uploads_abs_from_rel($normalizedPath);
    if ($abs !== '' && is_file($abs) && is_readable($abs)) return $normalizedPath;

    // 2) /uploads/visa/{id}/xxx 형태인데 실제는 /uploads/visa/xxx 로 저장된 환경 대응
    if (preg_match('#^/uploads/visa/(\\d+)/(.*)$#', $normalizedPath, $m)) {
        $basename = $m[2] ?? '';
        $alt = __uploads_rel_normalize('/uploads/visa/' . $basename);
        $abs2 = __uploads_abs_from_rel($alt);
        if ($abs2 !== '' && is_file($abs2) && is_readable($abs2)) return $alt;

        // 3) 파일명이 다르게(해시 포함) 저장된 케이스: uploads/visa/visa_{id}_{type}_* 패턴 검색
        $root = __webroot_path();
        $visaDir = ($root !== '') ? ($root . '/uploads/visa') : '';
        if ($visaDir !== '' && is_dir($visaDir)) {
            // alias가 없으면 basename prefix(underscore 전)로 추정
            if (empty($typeAliases)) {
                $guess = '';
                if (preg_match('/^([A-Za-z]+)_/', $basename, $mm)) $guess = strtolower($mm[1]);
                if ($guess !== '') $typeAliases = [$guess];
            }
            $cands = [];
            foreach ($typeAliases as $alias) {
                $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
                if ($alias === '') continue;
                foreach (glob($visaDir . "/visa_{$appId}_{$alias}_*") ?: [] as $fp) {
                    if (is_file($fp) && is_readable($fp)) $cands[] = $fp;
                }
            }
            if (!empty($cands)) {
                usort($cands, fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
                $pick = $cands[0];
                $relPick = '/uploads/visa/' . basename($pick);
                $abs3 = __uploads_abs_from_rel($relPick);
                if ($abs3 !== '' && is_file($abs3) && is_readable($abs3)) return $relPick;
            }
        }
    }

    // 못 찾으면 경로를 숨겨서(=다운로드 버튼 비활성화) 404를 방지
    return '';
}

function mergeVisaNotesSetKey($existingNotes, string $key, $value): string {
    $base = [];
    $txt = trim((string)($existingNotes ?? ''));
    if ($txt !== '') {
        $j = json_decode($txt, true);
        if (is_array($j)) $base = $j;
        else $base = ['notesText' => $txt];
    }
    $base[$key] = $value;
    return json_encode($base, JSON_UNESCAPED_UNICODE);
}

function getVisaApplications($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($input['status'])) {
            // UI status: pending/reviewing/approved/rejected
            $ui = strtolower((string)$input['status']);
            if ($ui === 'pending') {
                // 요청 접수(서류 미비 포함)
                $whereConditions[] = "(v.status IN ('pending','document_required'))";
            } elseif ($ui === 'reviewing') {
                $whereConditions[] = "v.status = 'under_review'";
            } elseif ($ui === 'approved') {
                $whereConditions[] = "(v.status IN ('approved','completed'))";
            } elseif ($ui === 'rejected') {
                $whereConditions[] = "v.status = 'rejected'";
            } else {
                // 혹시 DB status가 직접 들어오는 경우
                $whereConditions[] = "v.status = ?";
                $params[] = $ui;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM visa_applications v $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        // 요구사항: 사용자 제출/재제출/상태 변경 시 최신으로 올라와야 하므로 updatedAt 우선 정렬/표시
        $orderBy = $sortOrder === 'oldest'
            ? 'COALESCE(v.updatedAt, v.submittedAt, v.applicationDate) ASC'
            : 'COALESCE(v.updatedAt, v.submittedAt, v.applicationDate) DESC';
        
        $dataSql = "SELECT 
            v.*,
            c.fName,
            c.lName,
            c.emailAddress
        FROM visa_applications v
        LEFT JOIN client c ON v.accountId = c.accountId
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $applications = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            // 프론트(관리자 페이지)가 기대하는 상태값으로 정규화
            $raw = $row['status'] ?? 'pending';
            $uiStatus = mapVisaDbToAdminUiStatus((string)$raw);

            // list "신청일시"는 최신 액션 시각(updatedAt)을 우선 표시
            $createdAt = $row['updatedAt'] ?? ($row['submittedAt'] ?? '');
            if (!$createdAt && !empty($row['applicationDate'])) {
                $createdAt = $row['applicationDate'] . ' 00:00:00';
            }

            $applications[] = [
                'visaApplicationId' => $row['applicationId'] ?? '',
                'applicationId' => $row['applicationId'] ?? '',
                'applicationNo' => $row['applicationNo'] ?? '',
                'applicantName' => $row['applicantName'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'visaType' => $row['visaType'] ?? 'individual',
                'status' => $uiStatus,
                'createdAt' => $createdAt,
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'applications' => $applications,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get visa applications: ' . $e->getMessage());
    }
}

/**
 * Visa applications grouped by bookingId (same format as agent API)
 */
function getVisaApplicationsGrouped($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        $whereConditions = ["v.visaType IN ('group', 'individual')"];
        $params = [];
        $types = '';

        if (!empty($input['status'])) {
            $ui = strtolower((string)$input['status']);
            if ($ui === 'pending') {
                $whereConditions[] = "(v.status IN ('pending','document_required'))";
            } elseif ($ui === 'reviewing') {
                $whereConditions[] = "v.status = 'under_review'";
            } elseif ($ui === 'approved') {
                $whereConditions[] = "(v.status IN ('approved','completed'))";
            } elseif ($ui === 'rejected') {
                $whereConditions[] = "v.status = 'rejected'";
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // bookingId 기준 고유 건수 카운트
        $countSql = "SELECT COUNT(DISTINCT v.transactNo) as total
                     FROM visa_applications v
                     LEFT JOIN bookings b ON v.transactNo = b.bookingId
                     $whereClause";

        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            $countResult = $conn->query($countSql);
            $totalCount = $countResult->fetch_assoc()['total'];
        }

        $sortOrder = $input['sortOrder'] ?? 'latest';
        $bookingOrderBy = $sortOrder === 'oldest'
            ? 'MIN(COALESCE(v.updatedAt, v.applicationDate)) ASC'
            : 'MAX(COALESCE(v.updatedAt, v.applicationDate)) DESC';

        // 페이지네이션용 bookingId 목록 조회
        $bookingIdsSql = "SELECT v.transactNo as bookingId
                          FROM visa_applications v
                          LEFT JOIN bookings b ON v.transactNo = b.bookingId
                          $whereClause
                          GROUP BY v.transactNo
                          ORDER BY $bookingOrderBy
                          LIMIT ? OFFSET ?";

        $bookingIdsParams = array_merge($params, [$limit, $offset]);
        $bookingIdsTypes = $types . 'ii';

        $bookingIdsStmt = $conn->prepare($bookingIdsSql);
        if (!empty($bookingIdsTypes) && $bookingIdsTypes !== 'ii') {
            $bookingIdsStmt->bind_param($bookingIdsTypes, ...$bookingIdsParams);
        } else {
            $bookingIdsStmt->bind_param('ii', $limit, $offset);
        }
        $bookingIdsStmt->execute();
        $bookingIdsResult = $bookingIdsStmt->get_result();

        $bookingIds = [];
        while ($row = $bookingIdsResult->fetch_assoc()) {
            $bookingIds[] = $row['bookingId'];
        }
        $bookingIdsStmt->close();

        if (empty($bookingIds)) {
            send_success_response([
                'applications' => [],
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => (int)$totalCount,
                    'limit' => $limit
                ]
            ]);
            return;
        }

        // 해당 bookingId들의 모든 신청자 정보 조회
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $dataSql = "SELECT
            v.applicationId as visaApplicationId,
            v.applicationNo,
            v.applicantName,
            v.visaType,
            v.status,
            COALESCE(v.updatedAt, v.applicationDate) as createdAt,
            v.transactNo as bookingId,
            b.departureDate as travelStartDate,
            b.createdAt as dateBooked,
            a.agencyName
        FROM visa_applications v
        LEFT JOIN bookings b ON v.transactNo = b.bookingId
        LEFT JOIN agent a ON b.agentId = a.accountId
        WHERE v.transactNo IN ($placeholders) AND v.visaType IN ('group', 'individual')
        ORDER BY v.transactNo, v.applicationId ASC";

        $dataStmt = $conn->prepare($dataSql);
        $dataTypes = str_repeat('s', count($bookingIds));
        $dataStmt->bind_param($dataTypes, ...$bookingIds);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        // bookingId 기준으로 그룹핑
        $grouped = [];
        while ($row = $dataResult->fetch_assoc()) {
            $bookingId = $row['bookingId'];
            $uiStatus = mapVisaDbToAdminUiStatus((string)($row['status'] ?? 'pending'));
            $createdAt = $row['createdAt'] ?? '';
            if ($createdAt) {
                $createdAt = str_replace('T', ' ', $createdAt);
                if (strlen($createdAt) >= 16) $createdAt = substr($createdAt, 0, 16);
            }

            $traveler = [
                'visaApplicationId' => $row['visaApplicationId'] ?? '',
                'applicationNo' => $row['applicationNo'] ?? '',
                'applicantName' => $row['applicantName'] ?? '',
                'visaType' => $row['visaType'] ?? '',
                'status' => $uiStatus,
                'createdAt' => $createdAt
            ];

            if (!isset($grouped[$bookingId])) {
                $grouped[$bookingId] = [
                    'bookingId' => $bookingId,
                    'travelStartDate' => $row['travelStartDate'] ?? '',
                    'dateBooked' => $row['dateBooked'] ?? '',
                    'agencyName' => $row['agencyName'] ?? '',
                    'representativeName' => $traveler['applicantName'],
                    'representativeId' => $traveler['visaApplicationId'],
                    'representativeStatus' => $traveler['status'],
                    'representativeCreatedAt' => $traveler['createdAt'],
                    'representativeVisaType' => $traveler['visaType'],
                    'travelers' => [],
                    'travelerCount' => 0
                ];
            }
            $grouped[$bookingId]['travelers'][] = $traveler;
            $grouped[$bookingId]['travelerCount']++;
        }
        $dataStmt->close();

        // bookingIds 순서대로 정렬
        $applications = [];
        $rowNum = $totalCount - $offset;
        foreach ($bookingIds as $bid) {
            if (isset($grouped[$bid])) {
                $grouped[$bid]['rowNum'] = $rowNum--;
                $applications[] = $grouped[$bid];
            }
        }

        send_success_response([
            'applications' => $applications,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => (int)$totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get visa applications grouped: ' . $e->getMessage());
    }
}

function getVisaApplicationDetail($conn, $input) {
    try {
        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        if (empty($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        
        $sql = "SELECT 
            v.*,
            c.fName,
            c.lName,
            c.emailAddress,
            c.contactNo
        FROM visa_applications v
        LEFT JOIN client c ON v.accountId = c.accountId
        WHERE v.applicationId = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $visaApplicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Visa application not found', 404);
        }
        
        $application = $result->fetch_assoc();
        $stmt->close();

        // booking 정보 가져오기 (packageName은 booking에서 우선 가져옴)
        $bookingId = $application['transactNo'] ?? '';
        if (!empty($bookingId)) {
            $bkStmt = $conn->prepare("
                SELECT
                    bookingId, packageName, departureDate,
                    totalAmount, paymentStatus, bookingStatus,
                    createdAt as bookingCreatedAt
                FROM bookings
                WHERE bookingId = ?
                LIMIT 1
            ");
            if ($bkStmt) {
                $bkStmt->bind_param('s', $bookingId);
                $bkStmt->execute();
                $bookingRow = $bkStmt->get_result()->fetch_assoc();
                $bkStmt->close();

                if ($bookingRow) {
                    $application['bookingId'] = $bookingRow['bookingId'] ?? '';
                    // booking의 packageName을 우선 사용
                    if (!empty($bookingRow['packageName'])) {
                        $application['packageName'] = $bookingRow['packageName'];
                    }
                    $application['departureDate'] = $bookingRow['departureDate'] ?? '';
                    $application['totalAmount'] = $bookingRow['totalAmount'] ?? '';
                    $application['paymentStatus'] = $bookingRow['paymentStatus'] ?? '';
                    $application['bookingStatus'] = $bookingRow['bookingStatus'] ?? '';
                    $application['bookingCreatedAt'] = $bookingRow['bookingCreatedAt'] ?? '';
                }
            }
        }

        // booking에서 packageName을 못 가져온 경우에만 destinationCountry 사용 (fallback)
        if (empty($application['packageName']) && !empty($application['destinationCountry'])) {
            $application['packageName'] = $application['destinationCountry'];
        }

        // booking_travelers에서 신청자(메인 여행자) 정보 보강
        if (!empty($bookingId)) {
            // 우선순위:
            // 1) visa_applications.bookingTravelerId(있으면) → 해당 여행자
            // 2) applicantName과 booking_travelers 이름 매칭(유일하면) → 해당 여행자
            // 3) 메인 여행자(isMainTraveler=1)
            $travelerRow = null;

            $tid = 0;
            try {
                $tid = intval($application['bookingTravelerId'] ?? 0);
            } catch (_) { $tid = 0; }

            if ($tid > 0) {
                $t = $conn->prepare("
                    SELECT
                        title, firstName, lastName, birthDate, gender, nationality,
                        passportNumber, passportIssueDate, passportExpiry, passportImage, visaStatus
                    FROM booking_travelers
                    WHERE transactNo = ? AND bookingTravelerId = ?
                    LIMIT 1
                ");
                if ($t) {
                    $t->bind_param('si', $bookingId, $tid);
                    $t->execute();
                    $travelerRow = $t->get_result()->fetch_assoc();
                    $t->close();
                }
            }

            if (!$travelerRow) {
                $full = trim((string)($application['applicantName'] ?? ''));
                if ($full !== '') {
                    // firstName + space + lastName 매칭 (case-insensitive)
                    $t2 = $conn->prepare("
                        SELECT
                            title, firstName, lastName, birthDate, gender, nationality,
                            passportNumber, passportIssueDate, passportExpiry, passportImage, visaStatus
                        FROM booking_travelers
                        WHERE transactNo = ?
                          AND LOWER(CONCAT(TRIM(firstName), ' ', TRIM(lastName))) = LOWER(?)
                        ORDER BY isMainTraveler DESC, bookingTravelerId ASC
                        LIMIT 2
                    ");
                    if ($t2) {
                        $t2->bind_param('ss', $bookingId, $full);
                        $t2->execute();
                        $rs = $t2->get_result();
                        if ($rs && $rs->num_rows === 1) {
                            $travelerRow = $rs->fetch_assoc();
                        }
                        $t2->close();
                    }
                }
            }

            if (!$travelerRow) {
                $t3 = $conn->prepare("
                    SELECT
                        title, firstName, lastName, birthDate, gender, nationality,
                        passportNumber, passportIssueDate, passportExpiry, passportImage, visaStatus
                    FROM booking_travelers
                    WHERE transactNo = ?
                    ORDER BY isMainTraveler DESC, bookingTravelerId ASC
                    LIMIT 1
                ");
                if ($t3) {
                    $t3->bind_param('s', $bookingId);
                    $t3->execute();
                    $travelerRow = $t3->get_result()->fetch_assoc();
                    $t3->close();
                }
            }

            if ($travelerRow) {
                    // 관리자 상세 스크립트는 fName/lName 등을 찾음 → 동일 키로 제공
                    $tr = $travelerRow;
                    if (isset($tr['firstName'])) $application['fName'] = $tr['firstName'];
                    if (isset($tr['lastName'])) $application['lName'] = $tr['lastName'];
                    if (isset($tr['title'])) $application['honorific'] = $tr['title'];
                    if (isset($tr['gender'])) $application['gender'] = $tr['gender'];
                    if (isset($tr['nationality'])) $application['nationality'] = $tr['nationality'];
                    if (isset($tr['passportNumber'])) $application['passportNumber'] = $tr['passportNumber'];
                    if (isset($tr['passportIssueDate'])) $application['passportIssueDate'] = $tr['passportIssueDate'];
                    if (isset($tr['passportExpiry'])) $application['passportExpiryDate'] = $tr['passportExpiry'];

                    // 나이 계산(생년월일이 있을 때만)
                    if (!empty($tr['birthDate'])) {
                        try {
                            $bd = new DateTime($tr['birthDate']);
                            $today = new DateTime('today');
                            $application['age'] = $bd->diff($today)->y;
                            $application['birthDate'] = $tr['birthDate'];
                        } catch (Exception $e) {
                            // ignore
                        }
                    }

                    // 여권 사진 프리뷰(booking_travelers.passportImage가 base64일 수 있음)
                    if (!empty($tr['passportImage'])) {
                        $pi = (string)$tr['passportImage'];
                        $piTrim = trim($pi);
                        // 1) data URL
                        if (strpos($piTrim, 'data:') === 0) {
                            $application['passportPhoto'] = $piTrim;
                            $application['passportPhotoUrl'] = $piTrim;
                        }
                        // 2) base64 only
                        else if (preg_match('/^[A-Za-z0-9+\\/]+=*$/', $piTrim)) {
                            $application['passportPhoto'] = 'data:image/jpeg;base64,' . $piTrim;
                            $application['passportPhotoUrl'] = $application['passportPhoto'];
                        }
                        // 3) stored path (/uploads/...) or (uploads/...)
                        else {
                            // 보안: 미리보기는 uploads 경로만 허용(그 외는 프론트에서 무시 가능)
                            $p = str_replace('\\', '/', $piTrim);

                            // download.php?file=... 형태(구버전/혼합 데이터) 대응
                            try {
                                if (strpos($p, 'download.php') !== false && strpos($p, 'file=') !== false) {
                                    $u = @parse_url($p);
                                    if (is_array($u) && !empty($u['query'])) {
                                        parse_str($u['query'], $qs);
                                        if (!empty($qs['file'])) $p = (string)$qs['file'];
                                    }
                                }
                            } catch (Throwable $e) { /* ignore */ }

                            // 문자열 어딘가에 /uploads/가 포함된 경우(절대 URL 포함) 잘라내기
                            $pos = strpos($p, '/uploads/');
                            if ($pos !== false) {
                                $p = substr($p, $pos);
                            }

                            if (str_starts_with($p, 'uploads/')) $p = '/' . $p;
                            if (str_starts_with($p, '/uploads/')) {
                                $application['passportPhoto'] = $p;
                                $application['passportPhotoUrl'] = $p;
                            }
                        }
                    }
                }
        }

        // 사용자 업로드 서류는 notes(JSON)에 documents로 저장되는 경우가 있어, 관리자 상세 화면의 문서 키에 매핑해준다.
        $docs = extractVisaDocumentsFromNotes($application['notes'] ?? null);
        if (!empty($docs)) {
            // 관리자 상세(visa-app-detail.html)가 찾는 키들로 제공
            $norm = function($p) {
                $p = trim((string)$p);
                if ($p === '') return '';
                return ($p[0] === '/') ? $p : ('/' . $p);
            };
            $appIdForResolve = (int)($application['applicationId'] ?? ($visaApplicationId ?? 0));

            if (!empty($docs['photo'])) {
                $p = $norm($docs['photo']);
                $application['idPhoto'] = __resolve_visa_doc_path($appIdForResolve, $p, ['photo', 'id_photo']);
            }
            if (!empty($docs['passport'])) {
                $p = $norm($docs['passport']);
                $application['passportCopy'] = __resolve_visa_doc_path($appIdForResolve, $p, ['passport', 'passport_copy']);
            }
            if (!empty($docs['bankCertificate'])) {
                $p = $norm($docs['bankCertificate']);
                $application['bankCertificate'] = __resolve_visa_doc_path($appIdForResolve, $p, ['bankCertificate', 'bank_certificate']);
            }
            if (!empty($docs['bankStatement'])) {
                $p = $norm($docs['bankStatement']);
                $application['bankStatement'] = __resolve_visa_doc_path($appIdForResolve, $p, ['bankStatement', 'bank_statement']);
            }
            if (!empty($docs['itinerary'])) {
                $p = $norm($docs['itinerary']);
                $application['itinerary'] = __resolve_visa_doc_path($appIdForResolve, $p, ['itinerary']);
            }
        }

        // 비자 파일도 notes(JSON)에 저장될 수 있어 관리자 상세에 노출
        try {
            $vf = extractVisaFileFromNotes($application['notes'] ?? null);
            if (!empty($vf)) {
                $vf = trim((string)$vf);
                if ($vf !== '') {
                    $p = ($vf[0] === '/') ? $vf : ('/' . $vf);
                    // 존재하는 파일만 노출(없으면 버튼 비활성화를 위해 빈 값)
                    $application['visaFile'] = __resolve_visa_doc_path((int)($application['applicationId'] ?? 0), $p, []);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        
        send_success_response(['application' => $application]);
    } catch (Exception $e) {
        send_error_response('Failed to get visa application detail: ' . $e->getMessage());
    }
}

function updateVisaFile($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $visaFilePath = $input['visaFilePath'] ?? ($input['visaFile'] ?? ($input['filePath'] ?? null));

        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $appId = (int)$visaApplicationId;

        $vf = trim((string)($visaFilePath ?? ''));
        if ($vf === '') {
            send_error_response('visaFilePath is required');
        }

        // 기존 notes 읽고 merge해서 visaFile 저장
        $existingNotes = null;
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if ($st0) {
            $st0->bind_param('i', $appId);
            $st0->execute();
            $existingNotes = ($st0->get_result()->fetch_assoc()['notes'] ?? null);
            $st0->close();
        }

        $finalNotes = mergeVisaNotesSetKey($existingNotes, 'visaFile', $vf);

        // 비자 파일 업로드 시점에 상태는 "발급 완료"로 자동 전환
        $dbStatus = mapVisaAdminUiToDbStatus('approved');
        $sql = __visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare update');
        }
        $stmt->bind_param('ssi', $finalNotes, $dbStatus, $appId);
        $stmt->execute();
        $stmt->close();

        send_success_response([], 'Visa file updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update visa file: ' . $e->getMessage());
    }
}

function deleteVisaFile($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $appId = (int)$visaApplicationId;

        // 기존 notes 읽기
        $existingNotes = '';
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if (!$st0) send_error_response('Failed to prepare read');
        $st0->bind_param('i', $appId);
        $st0->execute();
        $existingNotes = (string)($st0->get_result()->fetch_assoc()['notes'] ?? '');
        $st0->close();

        $j = [];
        if (trim($existingNotes) !== '') {
            $tmp = json_decode($existingNotes, true);
            if (is_array($tmp)) $j = $tmp;
        }

        // 현재 파일 경로 추출 후 실제 파일 삭제(uploads 하위만)
        $visaFile = extractVisaFileFromNotes($existingNotes);
        $visaFileNorm = __uploads_rel_normalize($visaFile);
        if ($visaFileNorm !== '') {
            $abs = __uploads_abs_from_rel($visaFileNorm);
            if ($abs !== '' && is_file($abs) && is_writable($abs)) {
                @unlink($abs);
            }
        }

        // notes에서 visaFile 키 제거(호환 키 포함)
        if (isset($j['visaFile'])) unset($j['visaFile']);
        if (isset($j['visa_file'])) unset($j['visa_file']);
        if (isset($j['visaUrl'])) unset($j['visaUrl']);
        if (isset($j['visaDocument'])) unset($j['visaDocument']);

        $finalNotes = json_encode($j, JSON_UNESCAPED_UNICODE);
        $derivedUi = computeVisaDerivedUiStatusFromNotes($finalNotes);
        $derivedDb = mapVisaAdminUiToDbStatus($derivedUi);

        $sql = __visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $st = $conn->prepare($sql);
        if (!$st) send_error_response('Failed to prepare update');
        $st->bind_param('ssi', $finalNotes, $derivedDb, $appId);
        $st->execute();
        $st->close();

        send_success_response([
            'status' => $derivedUi
        ], 'Visa file deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete visa file: ' . $e->getMessage());
    }
}

function computeVisaDerivedUiStatusFromNotes(string $notesJson): string {
    // 규칙(요구사항):
    // - 서류 전부 미제출 (최초 상태) -> 요청 접수(pending)
    // - 서류 전부 제출 -> 심사중(reviewing)
    // - 서류 일부만 제출 또는 일부 삭제됨 -> 반려(rejected) - "Returned" 페이지에서 재업로드 유도
    // - 비자 파일 업로드 -> 발급 완료(approved)
    $notesJson = trim($notesJson);
    $docs = extractVisaDocumentsFromNotes($notesJson);
    $visaFile = extractVisaFileFromNotes($notesJson);
    if (trim((string)$visaFile) !== '') return 'approved';

    // Group visa required documents (새 방식)
    $requiredNew = ['passport', 'visaApplicationForm', 'bankCertificate', 'bankStatement'];
    // Legacy required documents (구 방식)
    $requiredOld = ['photo', 'passport', 'bankCertificate', 'bankStatement', 'itinerary'];

    // 새 방식 문서 키가 존재하는지 확인 (값이 빈 문자열이어도 키가 있으면 문서가 삭제된 것)
    $hasNewStyleKeys = false;
    foreach ($requiredNew as $k) {
        if (array_key_exists($k, $docs)) {
            $hasNewStyleKeys = true;
            break;
        }
    }

    // 새 방식 문서 카운트 (값이 실제로 있는 것)
    $presentNew = 0;
    foreach ($requiredNew as $k) {
        $p = isset($docs[$k]) ? trim((string)$docs[$k]) : '';
        if ($p !== '') $presentNew++;
    }

    // 구 방식 문서 카운트
    $presentOld = 0;
    foreach ($requiredOld as $k) {
        $p = isset($docs[$k]) ? trim((string)$docs[$k]) : '';
        if ($p !== '') $presentOld++;
    }

    // 새 방식 우선 체크 (새 문서 키가 존재하거나 하나라도 값이 있으면 새 방식으로 판단)
    $isNewStyleApp = $hasNewStyleKeys || $presentNew > 0;

    if ($isNewStyleApp) {
        if ($presentNew === count($requiredNew)) return 'reviewing';
        // 키는 존재하지만 값이 없거나 일부만 있는 경우 -> 문서가 삭제됨 또는 일부만 제출
        // rejected(반려) 상태로 변경하여 "Returned" 페이지에서 재업로드 유도
        return 'rejected';
    }

    // 구 방식 체크
    if ($presentOld === 0) return 'pending';
    if ($presentOld === count($requiredOld)) return 'reviewing';

    // 일부만 제출된 경우 - rejected(반려) 상태로 "Returned" 페이지에서 재업로드
    return 'rejected';
}

function deleteVisaDocument($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $docKey = strtolower(trim((string)($input['docKey'] ?? $input['documentKey'] ?? '')));
        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        if ($docKey === '') {
            send_error_response('docKey is required');
        }

        // UI 키 -> notes.documents 키 매핑
        $map = [
            'idphoto' => 'photo',
            'passportcopy' => 'passport',
            'bankcertificate' => 'bankCertificate',
            'bankstatement' => 'bankStatement',
            'itinerary' => 'itinerary',
            'visaapplicationform' => 'visaApplicationForm',
            'additionaldocuments' => 'additionalDocuments'
        ];
        $docKeyNorm = $map[$docKey] ?? $docKey;
        // 허용된 문서 키 목록 (Group/Individual 비자 모두 포함)
        $allowed = ['photo', 'passport', 'bankCertificate', 'bankStatement', 'itinerary', 'visaApplicationForm', 'additionalDocuments'];
        if (!in_array($docKeyNorm, $allowed, true)) {
            send_error_response('Invalid docKey: ' . $docKeyNorm);
        }

        $appId = (int)$visaApplicationId;

        // 기존 notes 읽기
        $existingNotes = '';
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if (!$st0) send_error_response('Failed to prepare read');
        $st0->bind_param('i', $appId);
        $st0->execute();
        $existingNotes = (string)($st0->get_result()->fetch_assoc()['notes'] ?? '');
        $st0->close();

        $j = [];
        if (trim($existingNotes) !== '') {
            $tmp = json_decode($existingNotes, true);
            if (is_array($tmp)) $j = $tmp;
        }
        if (!isset($j['documents']) || !is_array($j['documents'])) $j['documents'] = [];
        // 삭제 처리: 경로만 제거(실제 파일은 서버에서 보존)
        $j['documents'][$docKeyNorm] = '';

        $finalNotes = json_encode($j, JSON_UNESCAPED_UNICODE);
        $derivedUi = computeVisaDerivedUiStatusFromNotes($finalNotes);
        $derivedDb = mapVisaAdminUiToDbStatus($derivedUi);

        $sql = __visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $st = $conn->prepare($sql);
        if (!$st) send_error_response('Failed to prepare update');
        $st->bind_param('ssi', $finalNotes, $derivedDb, $appId);
        $st->execute();
        $st->close();

        send_success_response([
            'status' => $derivedUi
        ], 'Document deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete visa document: ' . $e->getMessage());
    }
}

function updateVisaStatus($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        if (empty($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        
        $uiStatus = $input['status'] ?? 'pending';
        $dbStatus = mapVisaAdminUiToDbStatus((string)$uiStatus);
        
        $sql = __visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET status = ? WHERE applicationId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $dbStatus, $visaApplicationId);
        $stmt->execute();
        $stmt->close();
        
        send_success_response([], 'Status updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update visa status: ' . $e->getMessage());
    }
}

function downloadVisaApplications($conn, $input) {
    try {
        __ensure_visa_applications_updated_at($conn);

        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($input['status'])) {
            $ui = strtolower((string)$input['status']);
            if ($ui === 'pending') {
                $whereConditions[] = "(v.status IN ('pending','document_required'))";
            } elseif ($ui === 'reviewing') {
                $whereConditions[] = "v.status = 'under_review'";
            } elseif ($ui === 'approved') {
                $whereConditions[] = "(v.status IN ('approved','completed'))";
            } elseif ($ui === 'rejected') {
                $whereConditions[] = "v.status = 'rejected'";
            } else {
                $whereConditions[] = "v.status = ?";
                $params[] = $ui;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest'
            ? 'COALESCE(v.updatedAt, v.submittedAt, v.applicationDate) ASC'
            : 'COALESCE(v.updatedAt, v.submittedAt, v.applicationDate) DESC';
        
        $sql = "SELECT 
            v.*,
            c.fName,
            c.lName,
            c.emailAddress
        FROM visa_applications v
        LEFT JOIN client c ON v.accountId = c.accountId
        $whereClause
        ORDER BY $orderBy";
        
        $stmt = null;
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="visa_applications_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        // 요구사항(id 93): 상태 라벨 영문 표기
        fputcsv($output, ['No', 'Applicant name', 'Application date/time', 'Status'], ',', '"', '\\');
        
        $rowNum = 1;
        $statusMap = [
            'pending' => 'Request Received',
            'reviewing' => 'Under Review',
            'approved' => 'Issuance Complete',
            'rejected' => 'Returned'
        ];
        
        while ($row = $result->fetch_assoc()) {
            $applicantName = $row['applicantName'] ?? trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
            $createdAt = $row['submittedAt'] ?? '';
            if (!$createdAt && !empty($row['applicationDate'])) $createdAt = $row['applicationDate'] . ' 00:00:00';

            $raw = $row['status'] ?? 'pending';
            $uiStatus = 'pending';
            if (in_array($raw, ['document_required', 'under_review'], true)) $uiStatus = 'reviewing';
            elseif (in_array($raw, ['approved', 'completed'], true)) $uiStatus = 'approved';
            elseif ($raw === 'rejected') $uiStatus = 'rejected';
            $status = $statusMap[$uiStatus] ?? $uiStatus;
            
            fputcsv($output, [
                $rowNum++,
                $applicantName,
                $createdAt,
                $status
            ], ',', '"', '\\');
        }
        
        fclose($output);
        if ($stmt) $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download visa applications: ' . $e->getMessage());
    }
}

// ========== 팝업 관리 함수들 ==========

function __compute_popup_status_by_period($startDate, $endDate, $today = null) {
    $s = is_string($startDate) ? trim($startDate) : '';
    $e = is_string($endDate) ? trim($endDate) : '';
    if ($s === '' || $e === '') return 'inactive';

    // DATE 컬럼이지만 입력이 datetime으로 들어올 수 있으니 앞 10자리만 사용
    $s = substr($s, 0, 10);
    $e = substr($e, 0, 10);

    if ($today === null) $today = date('Y-m-d');
    $t = substr(trim((string)$today), 0, 10);

    // 문자열 비교가 YYYY-MM-DD에서는 날짜 비교로 동작
    return ($t >= $s && $t <= $e) ? 'active' : 'inactive';
}

function getPopups($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // 상태는 노출 기간(start~end) 기준으로 "자동 결정" (DB status는 참고하지 않음)
        $statusExpr = "CASE WHEN (p.startDate IS NOT NULL AND p.startDate <> '' AND p.endDate IS NOT NULL AND p.endDate <> '' AND CURDATE() BETWEEN DATE(p.startDate) AND DATE(p.endDate)) THEN 'active' ELSE 'inactive' END";
        if (!empty($input['status'])) {
            $whereConditions[] = "($statusExpr) = ?";
            $params[] = ($input['status'] === 'active') ? 'active' : 'inactive';
            $types .= 's';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // popups 테이블이 없을 수 있으므로 동적으로 확인
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'popups'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_success_response([
                'popups' => [],
                'pagination' => [
                    'currentPage' => 1,
                    'totalPages' => 0,
                    'totalCount' => 0,
                    'limit' => $limit
                ]
            ]);
            return;
        }
        
        $countSql = "SELECT COUNT(*) as total FROM popups p $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];
        if ($countStmt) $countStmt->close();
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest' ? 'p.createdAt ASC' : 'p.createdAt DESC';
        
        $dataSql = "SELECT 
            p.*,
            ($statusExpr) as computedStatus
        FROM popups p
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $popups = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $computedStatus = $row['computedStatus'] ?? __compute_popup_status_by_period($row['startDate'] ?? '', $row['endDate'] ?? '');
            $popups[] = [
                'popupId' => $row['popupId'] ?? $row['id'] ?? '',
                'title' => $row['title'] ?? '',
                'imageUrl' => $row['imageUrl'] ?? $row['thumbnail'] ?? '',
                'startDate' => $row['startDate'] ?? '',
                'endDate' => $row['endDate'] ?? '',
                'status' => $computedStatus,
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'popups' => $popups,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get popups: ' . $e->getMessage());
    }
}

function getPopupDetail($conn, $input) {
    try {
        $popupId = $input['popupId'] ?? $input['id'] ?? null;
        if (empty($popupId)) {
            send_error_response('Popup ID is required');
        }
        
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'popups'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_error_response('Popups table does not exist', 404);
        }
        
        // 스키마 차이(popupId vs id) 대응
        $colsRes = $conn->query("SHOW COLUMNS FROM popups");
        $cols = [];
        if ($colsRes) {
            while ($r = $colsRes->fetch_assoc()) {
                $cols[strtolower($r['Field'])] = $r['Field'];
            }
        }
        $idCol = $cols['popupid'] ?? ($cols['id'] ?? null);
        if (!$idCol) {
            send_error_response('Popups table schema missing id column', 500);
        }

        $sql = "SELECT * FROM popups WHERE `$idCol` = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare popup detail: ' . $conn->error, 500);
        }
        $pid = intval($popupId);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Popup not found', 404);
        }
        
        $popup = $result->fetch_assoc();
        $stmt->close();

        // 상태는 노출 기간 기준으로 자동 계산
        $storedStatus = $popup['status'] ?? 'inactive';
        $computedStatus = __compute_popup_status_by_period($popup['startDate'] ?? '', $popup['endDate'] ?? '');
        $popup['storedStatus'] = $storedStatus;
        $popup['status'] = $computedStatus;
        
        send_success_response(['popup' => $popup]);
    } catch (Exception $e) {
        send_error_response('Failed to get popup detail: ' . $e->getMessage());
    }
}

function createPopup($conn, $input) {
    try {
        $requiredFields = ['title'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }

        ensurePopupsTable($conn);

        $title = trim((string)$input['title']);
        $startDate = isset($input['startDate']) ? trim((string)$input['startDate']) : null;
        $endDate = isset($input['endDate']) ? trim((string)$input['endDate']) : null;
        $link = isset($input['link']) ? trim((string)$input['link']) : null;
        $target = isset($input['target']) ? trim((string)$input['target']) : '_self';
        // 상태는 노출 기간 기준 자동 결정
        if (!empty($startDate) && !empty($endDate) && substr($startDate, 0, 10) > substr($endDate, 0, 10)) {
            send_error_response('Invalid popup period (startDate > endDate)');
        }
        $status = __compute_popup_status_by_period($startDate, $endDate);

        $imageUrl = isset($input['imageUrl']) ? trim((string)$input['imageUrl']) : null;
        if ($imageUrl === '') $imageUrl = null;

        // dataURL이면 실제 파일로 저장 후 URL만 저장
        if (!empty($imageUrl) && str_starts_with($imageUrl, 'data:image/')) {
            $saved = saveDataUrlImageToUploadsGeneric($imageUrl, 'popups', 'popup');
            if (empty($saved)) send_error_response('Failed to save popup image');
            $imageUrl = $saved; // /uploads/popups/...
        }

        $sql = "INSERT INTO popups (title, imageUrl, startDate, endDate, link, target, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare popup insert: ' . $conn->error);
        $stmt->bind_param('sssssss', $title, $imageUrl, $startDate, $endDate, $link, $target, $status);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to create popup: ' . $err);
        }
        $popupId = $conn->insert_id;
        $stmt->close();
        
        send_success_response(['popupId' => $popupId], 'Popup created successfully');
        return;
    } catch (Exception $e) {
        send_error_response('Failed to create popup: ' . $e->getMessage());
    }
}

function ensurePopupsTable($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'popups'");
    if ($checkTable && $checkTable->num_rows > 0) return;

    $sql = "CREATE TABLE IF NOT EXISTS popups (
        popupId INT NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        imageUrl VARCHAR(1024) NULL,
        startDate DATE NULL,
        endDate DATE NULL,
        link VARCHAR(2048) NULL,
        target VARCHAR(20) NULL DEFAULT '_self',
        status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (popupId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        send_error_response('Failed to create popups table: ' . $conn->error);
    }
}

function saveDataUrlImageToUploadsGeneric($dataUrl, $subdir, $prefix) {
    if (!preg_match('#^data:image/(png|jpeg|jpg|webp|gif);base64,(.+)$#', $dataUrl, $m)) {
        return null;
    }

    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    $bin = base64_decode($m[2], true);
    if ($bin === false) return null;

    $uploadDir = __DIR__ . '/../../../uploads/' . trim($subdir, '/');
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }
    }

    $rand = bin2hex(random_bytes(4));
    $filename = $prefix . '_' . date('YmdHis') . '_' . $rand . '.' . $ext;
    $path = $uploadDir . '/' . $filename;
    if (file_put_contents($path, $bin) === false) return null;

    return '/uploads/' . trim($subdir, '/') . '/' . $filename;
}

function updatePopup($conn, $input) {
    try {
        $popupId = $input['popupId'] ?? $input['id'] ?? null;
        if (empty($popupId)) {
            send_error_response('Popup ID is required');
        }
        
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'popups'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_error_response('Popups table does not exist', 404);
        }
        
        // helper: /uploads/popups 하위 파일만 안전 삭제
        $safeUnlinkPopupImage = function($url) {
            try {
                $u = trim((string)($url ?? ''));
                if ($u === '') return;
                $u = str_replace('\\', '/', $u);
                if (!str_starts_with($u, '/uploads/popups/')) return;
                if (str_contains($u, '..')) return;
                $abs = realpath(__DIR__ . '/../../../' . ltrim($u, '/'));
                $root = realpath(__DIR__ . '/../../../uploads/popups');
                if ($abs === false || $root === false) return;
                if (!str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) return;
                if (is_file($abs) && is_writable($abs)) {
                    @unlink($abs);
                }
            } catch (Throwable $e) {
                // ignore
            }
        };

        $updates = [];
        $values = [];
        $types = '';
        
        // status는 노출 기간으로 자동 결정되므로 클라이언트 입력은 무시
        // imageUrl은 ''/dataURL 처리 및 기존 파일 정리까지 수행
        $updatableFields = ['title', 'startDate', 'endDate', 'link', 'target'];
        foreach ($updatableFields as $field) {
            if (!isset($input[$field])) continue;
            $updates[] = "$field = ?";
            $values[] = $input[$field];
            $types .= 's';
        }
        
        if (empty($updates)) {
            send_error_response('No fields to update');
        }
        
        // 스키마 차이(popupId vs id) 대응
        $colsRes = $conn->query("SHOW COLUMNS FROM popups");
        $cols = [];
        if ($colsRes) {
            while ($r = $colsRes->fetch_assoc()) {
                $cols[strtolower($r['Field'])] = $r['Field'];
            }
        }
        $idCol = $cols['popupid'] ?? ($cols['id'] ?? null);
        if (!$idCol) {
            send_error_response('Popups table schema missing id column', 500);
        }

        // 기존 imageUrl 조회(삭제/교체 시 기존 파일 정리용)
        $pid = intval($popupId);
        $existingImageUrl = null;
        $imgStmt = $conn->prepare("SELECT imageUrl FROM popups WHERE `$idCol` = ? LIMIT 1");
        if ($imgStmt) {
            $imgStmt->bind_param('i', $pid);
            $imgStmt->execute();
            $existingImageUrl = ($imgStmt->get_result()->fetch_assoc()['imageUrl'] ?? null);
            $imgStmt->close();
        }

        if (array_key_exists('imageUrl', $input)) {
            $imageUrl = trim((string)($input['imageUrl'] ?? ''));
            if ($imageUrl === '') {
                // 삭제: DB NULL + 기존 파일 정리
                $safeUnlinkPopupImage($existingImageUrl);
                $updates[] = "imageUrl = ?";
                $values[] = null;
                $types .= 's';
            } else {
                // dataURL이면 파일로 저장 후 URL만 저장(기존 파일은 정리)
                if (str_starts_with($imageUrl, 'data:image/')) {
                    $saved = saveDataUrlImageToUploadsGeneric($imageUrl, 'popups', 'popup');
                    if (empty($saved)) send_error_response('Failed to save popup image');
                    $safeUnlinkPopupImage($existingImageUrl);
                    $imageUrl = $saved;
                }
                $updates[] = "imageUrl = ?";
                $values[] = $imageUrl;
                $types .= 's';
            }
        }

        // 기존 데이터를 읽어 기간 기준 자동 status 갱신도 함께 수행
        $getSql = "SELECT startDate, endDate FROM popups WHERE `$idCol` = ? LIMIT 1";
        $getStmt = $conn->prepare($getSql);
        if ($getStmt) {
            $getStmt->bind_param('i', $pid);
            $getStmt->execute();
            $getRes = $getStmt->get_result();
            $existing = $getRes ? $getRes->fetch_assoc() : null;
            $getStmt->close();

            $effectiveStart = isset($input['startDate']) ? $input['startDate'] : ($existing['startDate'] ?? null);
            $effectiveEnd = isset($input['endDate']) ? $input['endDate'] : ($existing['endDate'] ?? null);
            if (!empty($effectiveStart) && !empty($effectiveEnd) && substr((string)$effectiveStart, 0, 10) > substr((string)$effectiveEnd, 0, 10)) {
                send_error_response('Invalid popup period (startDate > endDate)');
            }
            $computedStatus = __compute_popup_status_by_period($effectiveStart, $effectiveEnd);
            $updates[] = "status = ?";
            $values[] = $computedStatus;
            $types .= 's';
        }

        $values[] = $pid;
        $types .= 'i';

        $sql = "UPDATE popups SET " . implode(', ', $updates) . " WHERE `$idCol` = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare popup update: ' . $conn->error, 500);
        }
        mysqli_bind_params_by_ref($stmt, $types, $values);
        $stmt->execute();
        $stmt->close();
        
        send_success_response([], 'Popup updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update popup: ' . $e->getMessage());
    }
}

function deletePopup($conn, $input) {
    try {
        $popupId = $input['popupId'] ?? $input['id'] ?? null;
        if (empty($popupId)) {
            send_error_response('Popup ID is required');
        }
        
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'popups'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_error_response('Popups table does not exist', 404);
        }
        
        // 스키마 차이(popupId vs id) 대응
        $colsRes = $conn->query("SHOW COLUMNS FROM popups");
        $cols = [];
        if ($colsRes) {
            while ($r = $colsRes->fetch_assoc()) {
                $cols[strtolower($r['Field'])] = $r['Field'];
            }
        }
        $idCol = $cols['popupid'] ?? ($cols['id'] ?? null);
        if (!$idCol) {
            send_error_response('Popups table schema missing id column', 500);
        }

        $sql = "DELETE FROM popups WHERE `$idCol` = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare popup delete: ' . $conn->error, 500);
        }
        $pid = intval($popupId);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $stmt->close();
        
        send_success_response([], 'Popup deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete popup: ' . $e->getMessage());
    }
}

function getBanners($conn, $input) {
    try {
        ensureBannersTable($conn);
        
        $sql = "SELECT * FROM banners ORDER BY bannerOrder ASC, bannerId ASC";
        $result = $conn->query($sql);
        
        $banners = [];
        while ($row = $result->fetch_assoc()) {
            $banners[] = $row;
        }
        
        send_success_response(['banners' => $banners]);
    } catch (Exception $e) {
        send_error_response('Failed to get banners: ' . $e->getMessage());
    }
}

function updateBanner($conn, $input) {
    try {
        $bannerId = $input['bannerId'] ?? $input['id'] ?? null;
        if (empty($bannerId)) {
            send_error_response('Banner ID is required');
        }

        ensureBannersTable($conn);

        $bannerId = intval($bannerId);
        $bannerOrder = isset($input['bannerOrder']) ? intval($input['bannerOrder']) : $bannerId;
        $url = isset($input['url']) ? trim((string)$input['url']) : null;
        if ($url === '') $url = null;

        // 기존 이미지 경로 조회(삭제/교체 시 파일 정리 목적)
        $existingImageUrl = null;
        try {
            $st0 = $conn->prepare("SELECT imageUrl FROM banners WHERE bannerId = ? LIMIT 1");
            if ($st0) {
                $st0->bind_param('i', $bannerId);
                $st0->execute();
                $existingImageUrl = ($st0->get_result()->fetch_assoc()['imageUrl'] ?? null);
                $st0->close();
            }
        } catch (Throwable $e) {
            $existingImageUrl = null;
        }

        $safeUnlinkBanner = function($imgUrl) {
            try {
                $u = trim((string)($imgUrl ?? ''));
                if ($u === '') return;
                $u = str_replace('\\', '/', $u);
                if (!str_starts_with($u, '/uploads/banners/')) return;
                if (str_contains($u, '..')) return;
                $abs = realpath(__DIR__ . '/../../../' . ltrim($u, '/'));
                $root = realpath(__DIR__ . '/../../../uploads/banners');
                if ($abs === false || $root === false) return;
                if (!str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) return;
                if (is_file($abs) && is_writable($abs)) {
                    @unlink($abs);
                }
            } catch (Throwable $e) {
                // ignore
            }
        };

        $imageUrl = isset($input['imageUrl']) ? trim((string)$input['imageUrl']) : null;
        if ($imageUrl === '') {
            // 삭제: DB NULL + 기존 파일도 정리(uploads/banners 하위만)
            $safeUnlinkBanner($existingImageUrl);
            $imageUrl = null;
        }

        // If imageUrl is a dataURL (from FileReader), save it as a real file and store the served URL path.
        if (!empty($imageUrl) && str_starts_with($imageUrl, 'data:image/')) {
            $saved = saveDataUrlImageToUploads($imageUrl, $bannerId);
            if (!empty($saved)) {
                // 교체: 기존 파일 정리
                $safeUnlinkBanner($existingImageUrl);
                $imageUrl = $saved; // e.g. /uploads/banners/banner1_...png
            } else {
                send_error_response('Failed to save banner image');
            }
        }

        $sql = "INSERT INTO banners (bannerId, bannerOrder, imageUrl, url)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    bannerOrder = VALUES(bannerOrder),
                    imageUrl = VALUES(imageUrl),
                    url = VALUES(url),
                    updatedAt = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare banner upsert: ' . $conn->error);
        }
        $stmt->bind_param('iiss', $bannerId, $bannerOrder, $imageUrl, $url);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to update banner: ' . $err);
        }
        $stmt->close();

        send_success_response([], 'Banner updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update banner: ' . $e->getMessage());
    }
}

function ensureBannersTable($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'banners'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // Ensure seed rows exist (1~10)
        for ($i = 1; $i <= 10; $i++) {
            $conn->query("INSERT IGNORE INTO banners (bannerId, bannerOrder, imageUrl, url) VALUES ($i, $i, NULL, NULL)");
        }
        return;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS banners (
        bannerId INT NOT NULL,
        bannerOrder INT NOT NULL DEFAULT 1,
        imageUrl VARCHAR(1024) NULL,
        url VARCHAR(2048) NULL,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (bannerId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        send_error_response('Failed to create banners table: ' . $conn->error);
    }

    for ($i = 1; $i <= 10; $i++) {
        $conn->query("INSERT IGNORE INTO banners (bannerId, bannerOrder, imageUrl, url) VALUES ($i, $i, NULL, NULL)");
    }
}

function saveDataUrlImageToUploads($dataUrl, $bannerId) {
    // data:image/{ext};base64,{...}
    if (!preg_match('#^data:image/(png|jpeg|jpg|webp|gif);base64,(.+)$#', $dataUrl, $m)) {
        return null;
    }

    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    $base64 = $m[2];
    $bin = base64_decode($base64, true);
    if ($bin === false) return null;

    $uploadDir = __DIR__ . '/../../../uploads/banners';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }
    }

    $rand = bin2hex(random_bytes(4));
    $filename = 'banner' . intval($bannerId) . '_' . date('YmdHis') . '_' . $rand . '.' . $ext;
    $path = $uploadDir . '/' . $filename;

    if (file_put_contents($path, $bin) === false) {
        return null;
    }

    return '/uploads/banners/' . $filename;
}

// ===== 파트너 관리 함수 =====

function ensurePartnersTable($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'partners'");
    if ($checkTable && $checkTable->num_rows > 0) {
        return;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS partners (
        partnerId INT AUTO_INCREMENT PRIMARY KEY,
        partnerOrder INT NOT NULL DEFAULT 1,
        partnerName VARCHAR(255) NOT NULL,
        partnerSubtitle VARCHAR(255) NULL,
        imageUrl VARCHAR(1024) NULL,
        isActive TINYINT(1) DEFAULT 1,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        send_error_response('Failed to create partners table: ' . $conn->error);
    }
}

function getPartners($conn, $input) {
    try {
        ensurePartnersTable($conn);

        $sql = "SELECT * FROM partners ORDER BY partnerOrder ASC, partnerId ASC";
        $result = $conn->query($sql);

        $partners = [];
        while ($row = $result->fetch_assoc()) {
            $partners[] = $row;
        }

        send_success_response(['partners' => $partners]);
    } catch (Exception $e) {
        send_error_response('Failed to get partners: ' . $e->getMessage());
    }
}

function updatePartner($conn, $input) {
    try {
        ensurePartnersTable($conn);

        $partnerId = $input['partnerId'] ?? null;
        $partnerName = isset($input['partnerName']) ? trim((string)$input['partnerName']) : '';
        $partnerSubtitle = isset($input['partnerSubtitle']) ? trim((string)$input['partnerSubtitle']) : null;
        $partnerOrder = isset($input['partnerOrder']) ? intval($input['partnerOrder']) : 1;
        $isActive = isset($input['isActive']) ? intval($input['isActive']) : 1;

        if (empty($partnerName)) {
            send_error_response('Partner name is required');
        }

        // 기존 이미지 경로 조회
        $existingImageUrl = null;
        if (!empty($partnerId)) {
            try {
                $st0 = $conn->prepare("SELECT imageUrl FROM partners WHERE partnerId = ? LIMIT 1");
                if ($st0) {
                    $st0->bind_param('i', $partnerId);
                    $st0->execute();
                    $existingImageUrl = ($st0->get_result()->fetch_assoc()['imageUrl'] ?? null);
                    $st0->close();
                }
            } catch (Throwable $e) {
                $existingImageUrl = null;
            }
        }

        $safeUnlinkPartner = function($imgUrl) {
            try {
                $u = trim((string)($imgUrl ?? ''));
                if ($u === '') return;
                $u = str_replace('\\', '/', $u);
                if (!str_starts_with($u, '/uploads/partners/')) return;
                if (str_contains($u, '..')) return;
                $abs = realpath(__DIR__ . '/../../../' . ltrim($u, '/'));
                $root = realpath(__DIR__ . '/../../../uploads/partners');
                if ($abs === false || $root === false) return;
                if (!str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) return;
                if (is_file($abs) && is_writable($abs)) {
                    @unlink($abs);
                }
            } catch (Throwable $e) {
                // ignore
            }
        };

        $imageUrl = isset($input['imageUrl']) ? trim((string)$input['imageUrl']) : null;
        if ($imageUrl === '') {
            $safeUnlinkPartner($existingImageUrl);
            $imageUrl = null;
        }

        // dataURL인 경우 파일로 저장
        if (!empty($imageUrl) && str_starts_with($imageUrl, 'data:image/')) {
            $saved = savePartnerImageToUploads($imageUrl, $partnerId ?? 0);
            if (!empty($saved)) {
                $safeUnlinkPartner($existingImageUrl);
                $imageUrl = $saved;
            } else {
                send_error_response('Failed to save partner image');
            }
        }

        if (!empty($partnerId)) {
            // UPDATE
            $sql = "UPDATE partners SET partnerName = ?, partnerSubtitle = ?, partnerOrder = ?, imageUrl = ?, isActive = ?, updatedAt = CURRENT_TIMESTAMP WHERE partnerId = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                send_error_response('Failed to prepare partner update: ' . $conn->error);
            }
            $stmt->bind_param('ssisii', $partnerName, $partnerSubtitle, $partnerOrder, $imageUrl, $isActive, $partnerId);
        } else {
            // INSERT
            $sql = "INSERT INTO partners (partnerName, partnerSubtitle, partnerOrder, imageUrl, isActive) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                send_error_response('Failed to prepare partner insert: ' . $conn->error);
            }
            $stmt->bind_param('ssisi', $partnerName, $partnerSubtitle, $partnerOrder, $imageUrl, $isActive);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            send_error_response('Failed to save partner: ' . $err);
        }

        $newId = !empty($partnerId) ? $partnerId : $conn->insert_id;
        $stmt->close();

        send_success_response(['partnerId' => $newId, 'message' => 'Partner saved successfully']);
    } catch (Exception $e) {
        send_error_response('Failed to save partner: ' . $e->getMessage());
    }
}

function deletePartner($conn, $input) {
    try {
        ensurePartnersTable($conn);

        $partnerId = $input['partnerId'] ?? null;
        if (empty($partnerId)) {
            send_error_response('Partner ID is required');
        }

        // 기존 이미지 삭제
        try {
            $st0 = $conn->prepare("SELECT imageUrl FROM partners WHERE partnerId = ? LIMIT 1");
            if ($st0) {
                $st0->bind_param('i', $partnerId);
                $st0->execute();
                $existingImageUrl = ($st0->get_result()->fetch_assoc()['imageUrl'] ?? null);
                $st0->close();

                if (!empty($existingImageUrl) && str_starts_with($existingImageUrl, '/uploads/partners/')) {
                    $abs = realpath(__DIR__ . '/../../../' . ltrim($existingImageUrl, '/'));
                    $root = realpath(__DIR__ . '/../../../uploads/partners');
                    if ($abs && $root && str_starts_with($abs, $root . DIRECTORY_SEPARATOR) && is_file($abs)) {
                        @unlink($abs);
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        $sql = "DELETE FROM partners WHERE partnerId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $partnerId);

        if (!$stmt->execute()) {
            send_error_response('Failed to delete partner: ' . $stmt->error);
        }

        $stmt->close();
        send_success_response(['message' => 'Partner deleted successfully']);
    } catch (Exception $e) {
        send_error_response('Failed to delete partner: ' . $e->getMessage());
    }
}

function savePartnerImageToUploads($dataUrl, $partnerId) {
    if (!preg_match('#^data:image/(png|jpeg|jpg|webp|gif);base64,(.+)$#', $dataUrl, $m)) {
        return null;
    }

    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    $base64 = $m[2];
    $bin = base64_decode($base64, true);
    if ($bin === false) return null;

    $uploadDir = realpath(__DIR__ . '/../../../uploads');
    if (!$uploadDir) {
        @mkdir(__DIR__ . '/../../../uploads', 0755, true);
        $uploadDir = realpath(__DIR__ . '/../../../uploads');
    }
    $uploadDir .= '/partners';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) return null;

    $rand = bin2hex(random_bytes(4));
    $filename = 'partner' . intval($partnerId) . '_' . date('YmdHis') . '_' . $rand . '.' . $ext;
    $path = $uploadDir . '/' . $filename;

    if (file_put_contents($path, $bin) === false) {
        return null;
    }

    return '/uploads/partners/' . $filename;
}

function getTerms($conn, $input) {
    try {
        $category = $input['category'] ?? 'terms';
        $language = $input['language'] ?? 'en';

        // allowlist (관리자 약관 6종)
        $allowedCategories = [
            'terms',
            'privacy_collection',
            'privacy_sharing',
            'marketing_consent',
            'cancellation_fee_special',
            'unique_identifier_collection'
        ];
        $allowedLang = ['ko', 'en', 'tl'];
        if (!in_array($category, $allowedCategories, true)) $category = 'terms';
        if (!in_array($language, $allowedLang, true)) $language = 'en';
        
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'terms'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_success_response(['content' => '']);
            return;
        }
        
        $sql = "SELECT content FROM terms WHERE category = ? AND language = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $category, $language);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $content = '';
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $content = $row['content'] ?? '';
        }
        $stmt->close();
        
        send_success_response(['content' => $content]);
    } catch (Exception $e) {
        send_error_response('Failed to get terms: ' . $e->getMessage());
    }
}

function ensureTermsTable($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'terms'");
    if ($checkTable && $checkTable->num_rows > 0) return;

    $sql = "CREATE TABLE IF NOT EXISTS terms (
        id INT NOT NULL AUTO_INCREMENT,
        category VARCHAR(50) NOT NULL,
        language VARCHAR(10) NOT NULL,
        content LONGTEXT NOT NULL,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_category_language (category, language)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        send_error_response('Failed to create terms table: ' . $conn->error);
    }
}

function updateTerms($conn, $input) {
    try {
        $category = $input['category'] ?? 'terms';
        $language = $input['language'] ?? 'en';
        $content = $input['content'] ?? '';

        // allowlist (관리자 약관 6종)
        $allowedCategories = [
            'terms',
            'privacy_collection',
            'privacy_sharing',
            'marketing_consent',
            'cancellation_fee_special',
            'unique_identifier_collection'
        ];
        $allowedLang = ['ko', 'en', 'tl'];
        if (!in_array($category, $allowedCategories, true)) $category = 'terms';
        if (!in_array($language, $allowedLang, true)) $language = 'en';
        
        ensureTermsTable($conn);
        
        $sql = "INSERT INTO terms (category, language, content) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE content = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $category, $language, $content, $content);
        $stmt->execute();
        $stmt->close();
        
        send_success_response([], 'Terms updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update terms: ' . $e->getMessage());
    }
}

function getCompanyInfo($conn, $input) {
    try {
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'company_info'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_success_response(['companyInfo' => []]);
            return;
        }
        
        $sql = "SELECT * FROM company_info ORDER BY infoId ASC LIMIT 1";
        $result = $conn->query($sql);

        $row = [];
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
        }

        // Map to frontend expected keys
        $companyInfo = [
            'address' => $row['address'] ?? '',
            'email' => $row['email'] ?? '',
            'phoneDomestic' => $row['phoneLocal'] ?? '',
            'phoneInternational' => $row['phoneInternational'] ?? ''
        ];

        send_success_response(['companyInfo' => $companyInfo]);
    } catch (Exception $e) {
        send_error_response('Failed to get company info: ' . $e->getMessage());
    }
}

function updateCompanyInfo($conn, $input) {
    try {
        $tableExists = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'company_info'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            send_error_response('Company info table does not exist', 404);
        }

        $address = isset($input['address']) ? trim((string)$input['address']) : '';
        $email = isset($input['email']) ? trim((string)$input['email']) : '';
        $phoneDomestic = isset($input['phoneDomestic']) ? trim((string)$input['phoneDomestic']) : '';
        $phoneInternational = isset($input['phoneInternational']) ? trim((string)$input['phoneInternational']) : '';

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            send_error_response('Invalid email');
        }

        $updatedBy = $_SESSION['admin_accountId'] ?? null;
        $updatedBy = !empty($updatedBy) ? intval($updatedBy) : null;

        // If table has no rows, create one with required companyName.
        $cntRes = $conn->query("SELECT COUNT(*) as c FROM company_info");
        $cnt = $cntRes ? intval(($cntRes->fetch_assoc()['c'] ?? 0)) : 0;
        if ($cnt === 0) {
            $ins = $conn->prepare("INSERT INTO company_info (companyName, address, email, phoneLocal, phoneInternational, updatedBy) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$ins) {
                send_error_response('Failed to prepare company info insert: ' . $conn->error);
            }
            $defaultName = 'SMART TRAVEL';
            $ins->bind_param('sssssi', $defaultName, $address, $email, $phoneDomestic, $phoneInternational, $updatedBy);
            if (!$ins->execute()) {
                $err = $ins->error ?: $conn->error;
                $ins->close();
                send_error_response('Failed to create company info row: ' . $err);
            }
            $ins->close();
        } else {
            // company_info schema uses phoneLocal (not phoneDomestic)
            $sql = "UPDATE company_info
                    SET address = ?, email = ?, phoneLocal = ?, phoneInternational = ?, updatedBy = ?
                    ORDER BY infoId ASC
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                send_error_response('Failed to prepare company info update: ' . $conn->error);
            }
            $stmt->bind_param('ssssi', $address, $email, $phoneDomestic, $phoneInternational, $updatedBy);
            if (!$stmt->execute()) {
                $err = $stmt->error ?: $conn->error;
                $stmt->close();
                send_error_response('Failed to update company info: ' . $err);
            }
            $stmt->close();
        }

        send_success_response([], 'Company info updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update company info: ' . $e->getMessage());
    }
}

// ========== 템플릿 관리 함수들 ==========

function getTemplates($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        // templates / product_templates 테이블 확인
        // IMPORTANT: 저장(create/update)은 product_templates 기준이므로 조회도 동일 우선순위로 통일한다.
        $tableExists = false;
        $tableName = 'product_templates';
        $checkTable = $conn->query("SHOW TABLES LIKE 'product_templates'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        } else {
            $checkTable = $conn->query("SHOW TABLES LIKE 'templates'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $tableExists = true;
                $tableName = 'templates';
            }
        }
        
        if (!$tableExists) {
            send_success_response([
                'templates' => [],
                'pagination' => [
                    'currentPage' => 1,
                    'totalPages' => 0,
                    'totalCount' => 0,
                    'limit' => $limit
                ]
            ]);
            return;
        }
        
        // 검색(템플릿명 기준)
        $where = [];
        $params = [];
        $types = '';

        $search = isset($input['search']) ? trim((string)$input['search']) : '';
        if ($search !== '') {
            // 테이블마다 컬럼명이 다를 수 있어 존재하는 컬럼만 대상으로 LIKE 조건 생성
            $cols = [];
            $colRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
            if ($colRes) {
                while ($c = $colRes->fetch_assoc()) {
                    $cols[] = $c['Field'] ?? '';
                }
                $colRes->free();
            }
            $cands = [];
            foreach (['templateName', 'name', 'title'] as $cand) {
                if (in_array($cand, $cols, true)) $cands[] = $cand;
            }
            if (empty($cands)) $cands[] = 'templateName'; // fallback (대부분 product_templates)

            $like = '%' . $search . '%';
            $parts = [];
            foreach ($cands as $col) {
                $parts[] = "t.`$col` LIKE ?";
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($parts)) $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $whereClause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countSql = "SELECT COUNT(*) as total FROM `$tableName` t $whereClause";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) send_error_response('Failed to prepare template count query: ' . ($conn->error ?: 'unknown'), 500);
        if ($types !== '') {
            if (!mysqli_bind_params_by_ref($countStmt, $types, $params)) {
                send_error_response('Failed to bind template count params: ' . ($countStmt->error ?: 'unknown'), 500);
            }
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalCount = $countRes ? ($countRes->fetch_assoc()['total'] ?? 0) : 0;
        $countStmt->close();
        
        $dataSql = "SELECT 
            t.*
        FROM `$tableName` t
        $whereClause
        ORDER BY t.createdAt DESC
        LIMIT ? OFFSET ?";
        
        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) send_error_response('Failed to prepare template list query: ' . ($conn->error ?: 'unknown'), 500);

        // 검색 파라미터 + limit/offset 바인딩
        $dataParams = $params;
        $dataTypes = $types;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataTypes .= 'ii';
        if (!mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams)) {
            send_error_response('Failed to bind template list params: ' . ($dataStmt->error ?: 'unknown'), 500);
        }
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $templates = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $templates[] = [
                'templateId' => $row['templateId'] ?? $row['id'] ?? '',
                'templateName' => $row['templateName'] ?? $row['name'] ?? $row['title'] ?? '',
                'category' => $row['category'] ?? $row['mainCategory'] ?? '',
                'subCategory' => $row['subCategory'] ?? '',
                'duration' => $row['duration'] ?? $row['schedulePeriod'] ?? '',
                'createdAt' => $row['createdAt'] ?? '',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'templates' => $templates,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get templates: ' . $e->getMessage());
    }
}

function getTemplateDetail($conn, $input) {
    try {
        $templateId = $input['templateId'] ?? $input['id'] ?? null;
        if (empty($templateId)) {
            send_error_response('Template ID is required');
        }

        // templates / product_templates 테이블 확인
        // IMPORTANT: 저장(create/update)은 product_templates 기준이므로 조회도 동일 우선순위로 통일한다.
        $tableExists = false;
        $tableName = 'product_templates';
        $idCol = 'templateId';
        $checkTable = $conn->query("SHOW TABLES LIKE 'product_templates'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableExists = true;
        } else {
            $checkTable = $conn->query("SHOW TABLES LIKE 'templates'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $tableExists = true;
                $tableName = 'templates';
                $idCol = 'id';
            }
        }

        if (!$tableExists) {
            send_error_response('Template table not found', 404);
        }

        $sql = "SELECT * FROM $tableName WHERE $idCol = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare template detail query: ' . $conn->error);

        if (is_numeric($templateId)) {
            $tid = intval($templateId);
            $stmt->bind_param('i', $tid);
        } else {
            // fallback: 문자열 id가 들어오는 경우
            $tid = (string)$templateId;
            $stmt->bind_param('s', $tid);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            send_error_response('Template not found', 404);
        }
        $row = $res->fetch_assoc();
        $stmt->close();

        $dataRaw = $row['data'] ?? null;
        $data = null;
        if (is_string($dataRaw) && $dataRaw !== '') {
            // 템플릿 data는 LONGTEXT(JSON)인데, 환경/저장방식에 따라 아래 케이스가 발생할 수 있음:
            // - UTF-8 깨짐으로 json_decode 실패
            // - 이중 인코딩("\"{...}\"") 형태로 저장
            // - 백슬래시가 과도하게 들어간 문자열(escape)로 저장
            $tryDecode = function ($raw) {
                if (!is_string($raw) || $raw === '') return null;
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) return $decoded;
                // PHP 7.2+: invalid UTF-8 substitute 플래그가 있으면 한 번 더 시도
                if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                    $decoded2 = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    if (json_last_error() === JSON_ERROR_NONE) return $decoded2;
                }
                return null;
            };

            $decoded = $tryDecode($dataRaw);
            // 이중 인코딩 대응: decode 결과가 문자열(JSON)인 경우 재파싱
            if (is_string($decoded)) {
                $decoded2 = $tryDecode($decoded);
                if ($decoded2 !== null) $decoded = $decoded2;
            }
            // escape 문자열 대응
            if ($decoded === null) {
                $decoded = $tryDecode(stripslashes($dataRaw));
            }
            if ($decoded !== null) $data = $decoded;
        }

        $template = [
            'templateId' => $row['templateId'] ?? $row['id'] ?? null,
            'templateName' => $row['templateName'] ?? $row['name'] ?? $row['title'] ?? '',
            'mainCategory' => $row['mainCategory'] ?? ($row['category'] ?? null),
            'subCategory' => $row['subCategory'] ?? null,
            'targetMarket' => $row['targetMarket'] ?? null,
            'schedulePeriod' => $row['schedulePeriod'] ?? ($row['duration'] ?? null),
            'createdAt' => $row['createdAt'] ?? null,
            'data' => $data,
            'dataRaw' => $dataRaw,
        ];

        send_success_response(['template' => $template]);
    } catch (Exception $e) {
        send_error_response('Failed to get template detail: ' . $e->getMessage());
    }
}

function ensureProductTemplatesTable($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'product_templates'");
    if ($check && $check->num_rows > 0) return;

    $sql = "CREATE TABLE IF NOT EXISTS product_templates (
        templateId INT NOT NULL AUTO_INCREMENT,
        templateName VARCHAR(255) NOT NULL,
        mainCategory VARCHAR(100) NULL,
        subCategory VARCHAR(100) NULL,
        targetMarket VARCHAR(20) NULL,
        schedulePeriod VARCHAR(50) NULL,
        data LONGTEXT NULL,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        createdBy INT NULL,
        PRIMARY KEY (templateId),
        KEY createdBy (createdBy)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        send_error_response('Failed to create product_templates table: ' . $conn->error);
    }
}

function createTemplate($conn, $input) {
    try {
        $templateName = trim((string)($input['templateName'] ?? ''));
        if ($templateName === '') {
            send_error_response("Field 'templateName' is required");
        }

        ensureProductTemplatesTable($conn);

        $mainCategory = isset($input['mainCategory']) ? trim((string)$input['mainCategory']) : null;
        $subCategory = isset($input['subCategory']) ? trim((string)$input['subCategory']) : null;
        $targetMarket = isset($input['targetMarket']) ? trim((string)$input['targetMarket']) : null;
        $schedulePeriod = isset($input['schedulePeriod']) ? trim((string)$input['schedulePeriod']) : null;

        $data = $input['data'] ?? null;
        $dataJson = null;
        if ($data !== null) {
            $dataJson = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $createdBy = $_SESSION['admin_accountId'] ?? null;
        $createdBy = !empty($createdBy) ? intval($createdBy) : null;

        $sql = "INSERT INTO product_templates (templateName, mainCategory, subCategory, targetMarket, schedulePeriod, data, createdBy)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare template insert: ' . $conn->error);
        $stmt->bind_param('ssssssi', $templateName, $mainCategory, $subCategory, $targetMarket, $schedulePeriod, $dataJson, $createdBy);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to create template: ' . $err);
        }
        $templateId = $conn->insert_id;
        $stmt->close();

        send_success_response(['templateId' => $templateId], 'Template created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create template: ' . $e->getMessage());
    }
}

function resolveTemplateTable(mysqli $conn): array {
    // returns [tableName, idCol]
    $check = $conn->query("SHOW TABLES LIKE 'product_templates'");
    if ($check && $check->num_rows > 0) {
        return ['product_templates', 'templateId'];
    }
    $check2 = $conn->query("SHOW TABLES LIKE 'templates'");
    if ($check2 && $check2->num_rows > 0) {
        return ['templates', 'id'];
    }
    // default to product_templates (ensure caller creates if needed)
    return ['product_templates', 'templateId'];
}

function updateTemplate($conn, $input) {
    try {
        $templateId = $input['templateId'] ?? $input['id'] ?? null;
        if (empty($templateId)) send_error_response('Template ID is required');

        // table 확인 (없으면 생성)
        ensureProductTemplatesTable($conn);
        [$tableName, $idCol] = resolveTemplateTable($conn);

        $updates = [];
        $values = [];
        $types = '';

        $fields = ['templateName', 'mainCategory', 'subCategory', 'targetMarket', 'schedulePeriod', 'data'];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $input)) continue;
            if ($f === 'data') {
                $v = $input['data'];
                $v = ($v === null) ? null : (is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
                $updates[] = "data = ?";
                $values[] = $v;
                $types .= 's';
                continue;
            }
            $updates[] = "$f = ?";
            $values[] = (string)$input[$f];
            $types .= 's';
        }

        if (empty($updates)) {
            send_error_response('No fields to update');
        }

        // updatedAt 컬럼이 있으면 갱신
        $hasUpdatedAt = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE 'updatedAt'");
        if ($hasUpdatedAt && $hasUpdatedAt->num_rows > 0) {
            $updates[] = "updatedAt = CURRENT_TIMESTAMP";
        }

        $where = "$idCol = ?";
        if (is_numeric($templateId)) {
            $values[] = intval($templateId);
            $types .= 'i';
        } else {
            $values[] = (string)$templateId;
            $types .= 's';
        }

        $sql = "UPDATE `$tableName` SET " . implode(', ', $updates) . " WHERE $where";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare template update: ' . $conn->error);
        mysqli_bind_params_by_ref($stmt, $types, $values);
        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to update template: ' . $err, 500);
        }
        $stmt->close();

        send_success_response([], 'Template updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update template: ' . $e->getMessage(), 500);
    }
}

function deleteTemplate($conn, $input) {
    try {
        $templateId = $input['templateId'] ?? $input['id'] ?? null;
        if (empty($templateId)) send_error_response('Template ID is required');

        ensureProductTemplatesTable($conn);
        [$tableName, $idCol] = resolveTemplateTable($conn);

        $sql = "DELETE FROM `$tableName` WHERE $idCol = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare template delete: ' . $conn->error, 500);

        if (is_numeric($templateId)) {
            $tid = intval($templateId);
            $stmt->bind_param('i', $tid);
        } else {
            $tid = (string)$templateId;
            $stmt->bind_param('s', $tid);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error ?: $conn->error;
            $stmt->close();
            send_error_response('Failed to delete template: ' . $err, 500);
        }
        $stmt->close();

        send_success_response([], 'Template deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete template: ' . $e->getMessage(), 500);
    }
}

// ========== 에이전트 문의 관리 함수들 ==========

function getAgentInquiries($conn, $input) {
    try {
        // replyStatus 계산/필터에 inquiry_replies 테이블이 필요
        ensureInquiryRepliesTable($conn);

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // 에이전트 문의는 "agent 계정" 문의만
        // 1) accounts.accountType='agent' 우선
        // 2) agent 테이블(accountId 존재)도 포함 (레거시/환경 차이 대응)
        $agentTableExists = false;
        $t = $conn->query("SHOW TABLES LIKE 'agent'");
        if ($t && $t->num_rows > 0) $agentTableExists = true;

        $cond = "EXISTS (SELECT 1 FROM accounts ac WHERE ac.accountId = i.accountId AND ac.accountType = 'agent')";
        if ($agentTableExists) {
            $cond .= " OR EXISTS (SELECT 1 FROM agent ag WHERE ag.accountId = i.accountId)";
        }
        $whereConditions[] = "($cond)";
        
        if (!empty($input['replyStatus'])) {
            if ($input['replyStatus'] === 'answered') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($input['replyStatus'] === 'unanswered') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            }
        }
        
        if (!empty($input['processingStatus'])) {
            // UI 값(pending/processing/completed)을 DB 값(open/in_progress/resolved/closed)으로 매핑
            $map = [
                'pending' => ['open'],
                'processing' => ['in_progress'],
                'completed' => ['resolved', 'closed']
            ];
            $key = $input['processingStatus'];
            if (isset($map[$key])) {
                $vals = $map[$key];
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            } else {
                $whereConditions[] = "i.status = ?";
                $params[] = $key;
                $types .= 's';
            }
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $countSql = "SELECT COUNT(*) as total FROM inquiries i $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            if (!$countStmt) {
                throw new Exception('Failed to prepare count query: ' . $conn->error);
            }
            $countStmt->bind_param($types, ...$params);
            if (!$countStmt->execute()) {
                $err = $countStmt->error ?: $conn->error;
                $countStmt->close();
                throw new Exception('Failed to execute count query: ' . $err);
            }
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        if (!$countResult) {
            throw new Exception('Failed to run count query: ' . $conn->error);
        }
        $countRow = $countResult->fetch_assoc();
        $totalCount = isset($countRow['total']) ? intval($countRow['total']) : 0;
        if ($countStmt) $countStmt->close();
        
        $sortOrder = $input['sortOrder'] ?? 'latest';
        $orderBy = $sortOrder === 'oldest' ? 'i.createdAt ASC' : 'i.createdAt DESC';
        
        // agent 테이블이 없는 환경(레거시/분리된 스키마 등)에서는 JOIN을 제거하고 지점명을 빈 값으로 반환
        // 지점명 표시는 branchName 우선, 없으면 companyName으로 fallback
        $branchSelect = $agentTableExists
            ? "COALESCE(NULLIF('',''), NULLIF('' as co_companyName,''), '') AS branchName"
            : "'' AS branchName";
        $agentJoins = "";
        if ($agentTableExists) {
            $agentJoins = "
        /*
         * accountId 하나에 agent 레코드가 여러 개(예: Retailer/Wholeseller) 존재할 수 있어
         * agent를 직접 JOIN하면 inquiry가 중복됩니다.
         * → accountId당 1개의 companyId를 결정(Wholeseller 우선, 그 외 id ASC)해서 조인합니다.
         */
            SELECT a.companyId
            FROM agent a
            WHERE a.accountId = i.accountId
            ORDER BY (a.agentType = 'Wholeseller') DESC, a.id ASC
            LIMIT 1
        )
        ";
        }

        $dataSql = "SELECT 
            i.inquiryId,
            i.subject,
            i.createdAt,
            i.status,
            $branchSelect,
            CASE 
                WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN '답변 완료'
                ELSE '미답변'
            END as replyStatus,
            CASE 
                WHEN i.status = 'open' THEN '접수됨'
                WHEN i.status = 'in_progress' THEN '처리중'
                WHEN i.status IN ('resolved','closed') THEN '처리 완료'
                ELSE i.status
            END as processingStatus
        FROM inquiries i
        $agentJoins
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) {
            throw new Exception('Failed to prepare data query: ' . $conn->error);
        }
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        if (!$dataStmt->execute()) {
            $err = $dataStmt->error ?: $conn->error;
            $dataStmt->close();
            throw new Exception('Failed to execute data query: ' . $err);
        }
        $dataResult = $dataStmt->get_result();
        
        $inquiries = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $inquiries[] = [
                'inquiryId' => $row['inquiryId'],
                'branchName' => $row['branchName'] ?? '',
                'inquiryTitle' => $row['subject'] ?? '',
                'createdAt' => $row['createdAt'] ?? '',
                'replyStatus' => $row['replyStatus'] ?? '미답변',
                'processingStatus' => $row['processingStatus'] ?? '접수됨',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        send_success_response([
            'inquiries' => $inquiries,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get agent inquiries: ' . $e->getMessage());
    }
}

function getB2BBookingDetail($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 고객 accountId 컬럼(환경별) 대응: agent가 고객 대신 예약 생성하는 경우 customer 정보는 별도 컬럼에 저장됨
        $bookingColumns = [];
        try {
            $cr = $conn->query("SHOW COLUMNS FROM bookings");
            while ($cr && ($c = $cr->fetch_assoc())) $bookingColumns[] = strtolower((string)$c['Field']);
        } catch (Throwable $e) { $bookingColumns = []; }
        $customerAccountIdCol = null;
        if (in_array('customeraccountid', $bookingColumns, true)) $customerAccountIdCol = 'customerAccountId';
        else if (in_array('customer_account_id', $bookingColumns, true)) $customerAccountIdCol = 'customer_account_id';
        else if (in_array('customerid', $bookingColumns, true)) $customerAccountIdCol = 'customerId';
        else if (in_array('userid', $bookingColumns, true)) $customerAccountIdCol = 'userId';

        $customerJoinSql = '';
        $customerNameExpr = "''";
        $customerEmailExpr = "''";
        $customerContactExpr = "''";
        if (!empty($customerAccountIdCol)) {
            $customerJoinSql = "LEFT JOIN client cu ON b.`{$customerAccountIdCol}` = cu.accountId";
            $customerNameExpr = "TRIM(COALESCE(NULLIF(CONCAT(cu.fName,' ',cu.lName),' '), NULLIF(cu.fName,''), NULLIF(cu.lName,''), ''))";
            $customerEmailExpr = "COALESCE(NULLIF(b.contactEmail,''), NULLIF(cu.emailAddress,''), '')";
            // client.contactNo는 국가코드 분리일 수 있어 b.contactPhone 우선
            $customerContactExpr = "COALESCE(NULLIF(b.contactPhone,''), NULLIF(cu.contactNo,''), '')";
        } else {
            // 환경에 customerAccountId가 없으면 contactEmail/Phone에 의존 (selectedOptions.customerInfo는 아래에서 보정)
            $customerNameExpr = "''";
            $customerEmailExpr = "COALESCE(NULLIF(b.contactEmail,''), '')";
            $customerContactExpr = "COALESCE(NULLIF(b.contactPhone,''), '')";
        }


        // balanceDueDate / balanceFile 컬럼 확인 (통일: balanceFile 사용)
        $hasBalanceDueDate = false;
        $c1 = $conn->query("SHOW COLUMNS FROM bookings LIKE 'balanceDueDate'");
        if ($c1 && $c1->num_rows > 0) $hasBalanceDueDate = true;
        else {
            $conn->query("ALTER TABLE bookings ADD COLUMN balanceDueDate DATE NULL");
            $c1 = $conn->query("SHOW COLUMNS FROM bookings LIKE 'balanceDueDate'");
            if ($c1 && $c1->num_rows > 0) $hasBalanceDueDate = true;
        }
        $hasBalanceFile = false;
        $c2 = $conn->query("SHOW COLUMNS FROM bookings LIKE 'balanceFile'");
        if ($c2 && $c2->num_rows > 0) $hasBalanceFile = true;

        // (1) 기본 예약/상품/고객/에이전트/가이드
        $sql = "SELECT 
            b.*,
            COALESCE(NULLIF(b.transactNo,''), b.bookingId) as reservationNo,
            DATE(b.departureDate) as departureDate,
            DATE_ADD(
                DATE(b.departureDate),
                INTERVAL GREATEST(COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1) - 1, 0) DAY
            ) as returnDate,
            {$customerNameExpr} as customerName,
            {$customerEmailExpr} as customerEmail,
            {$customerContactExpr} as customerContact,
            COALESCE(ba.username, '') as accountUsername,
            COALESCE(ba.emailAddress, '') as accountEmail,
            -- B2B: bookings.accountId가 agent 계정일 때만 agent/company/branch 정보를 내려줌
            '' as branchName,
            '' as companyName,
            -- Agent Name: branch/company가 없으면 agent 자체 정보로 fallback (ag2는 accountId로 조인된 에이전트)
            TRIM(COALESCE(
                NULLIF(ag.agencyName, ''),
                NULLIF(ag2.agencyName, ''),
                NULLIF(CONCAT(ag.fName,' ',ag.lName), ' '),
                NULLIF(CONCAT(ag2.fName,' ',ag2.lName), ' '),
                ''
            )) as agentName,
            -- Person in charge: agent.personInCharge 우선 (ag2는 accountId로 조인된 에이전트)
            TRIM(COALESCE(
                NULLIF(ag.personInCharge, ''),
                NULLIF(ag2.personInCharge, ''),
                NULLIF(CONCAT(ag.fName,' ',ag.lName), ' '),
                NULLIF(CONCAT(ag2.fName,' ',ag2.lName), ' '),
                ''
            )) as agentManagerName,
            COALESCE(NULLIF(ag.personInChargeEmail,''), NULLIF(ag2.personInChargeEmail,''), aa.emailAddress, aa2.emailAddress, '') as agentManagerEmail,
            COALESCE(ag.contactNo, ag2.contactNo, '') as agentManagerContact,
            g.guideId,
            g.guideName,
            COALESCE(NULLIF(b.packageName,''), p.packageName) as packageName,
            COALESCE(p.meeting_time, p.meetingTime, NULL) as meetingTime,
            COALESCE(p.meeting_location, p.meetingPoint, NULL) as meetingLocation,
            COALESCE(p.meeting_address, NULL) as meetingAddress
        FROM bookings b
        LEFT JOIN accounts ba ON b.accountId = ba.accountId
        {$customerJoinSql}
        -- ag: agentId로 조인 (에이전트가 고객을 위해 예약한 경우)
        LEFT JOIN agent ag ON ag.id = b.agentId
        
        LEFT JOIN accounts aa ON ag.accountId = aa.accountId
        -- ag2: accountId로 조인 (에이전트가 자기 계정으로 직접 예약한 경우)
        LEFT JOIN agent ag2 ON ag2.accountId = b.accountId AND b.agentId IS NULL
        
        LEFT JOIN accounts aa2 ON ag2.accountId = aa2.accountId
        LEFT JOIN guides g ON b.guideId = g.guideId
        LEFT JOIN packages p ON b.packageId = p.packageId
        WHERE b.bookingId = ?
        LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        // bookings.bookingId는 varchar(20)
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Booking not found', 404);
        }
        
        $booking = $result->fetch_assoc();
        $stmt->close();

        // meetingTime(meeting_time)는 time 타입일 수 있어서, 프론트에서 쓰기 쉽게 문자열로 정규화
        try {
            if (isset($booking['meetingTime']) && $booking['meetingTime'] !== null) {
                $booking['meetingTime'] = (string)$booking['meetingTime'];
            }
        } catch (Throwable $e) { }

        // selectedOptions 파싱 (응답에서 사용하기 위해 try 블록 밖에서 초기화)
        $selectedOptionsObj = null;
        $soRaw = $booking['selectedOptions'] ?? '';
        if (is_string($soRaw) && $soRaw !== '') {
            $tmp = json_decode($soRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $selectedOptionsObj = $tmp;
        }

        // 예약 고객 정보 보정:
        // - selectedOptions.customerInfo가 있으면 그것을 우선 사용
        // - 주의: 고객정보가 없다고 agent 계정(username/email)으로 fallback 하면 안 됨
        try {
            $so = $selectedOptionsObj;
            $ci = (is_array($so) && isset($so['customerInfo']) && is_array($so['customerInfo'])) ? $so['customerInfo'] : null;
            $ciName = '';
            if (is_array($ci)) {
                $ciName = trim((string)($ci['name'] ?? ''));
                if ($ciName === '') {
                    $fn = trim((string)($ci['fName'] ?? $ci['firstName'] ?? $ci['customerFirstName'] ?? ''));
                    $ln = trim((string)($ci['lName'] ?? $ci['lastName'] ?? $ci['customerLastName'] ?? ''));
                    $ciName = trim($fn . ' ' . $ln);
                }
            }
            $ciEmail = is_array($ci) ? trim((string)($ci['email'] ?? '')) : '';
            $ciPhone = is_array($ci) ? trim((string)($ci['phone'] ?? '')) : '';

            if (trim((string)($booking['customerName'] ?? '')) === '') {
                if ($ciName !== '') $booking['customerName'] = $ciName;
            }
            if (trim((string)($booking['customerEmail'] ?? '')) === '') {
                if ($ciEmail !== '') $booking['customerEmail'] = $ciEmail;
            }
            if (trim((string)($booking['customerContact'] ?? '')) === '' && $ciPhone !== '') {
                $booking['customerContact'] = $ciPhone;
            }
            // SMT 수정 시작 - 옵션 값 추출해서 booking 객체에 추가
            if (is_array($so)) {
                $booking['cabinBaggage'] = $so['cabinBaggage'] ?? '';
                $booking['breakfastRequest'] = $so['breakfastRequest'] ?? '';
                $booking['wifiRental'] = $so['wifiRental'] ?? '';
            }
            // SMT 수정 완료

            // B2B 상태키: 관리자가 선택한 값을 우선 사용(선금 증빙 업로드로 자동 변경하지 않음)
            $statusKey = '';
            $bs = strtolower((string)($booking['bookingStatus'] ?? ''));
            $ps = strtolower((string)($booking['paymentStatus'] ?? ''));
            if ($ps === 'refunded') $statusKey = 'refunded';
            else if ($bs === 'cancelled') $statusKey = 'cancelled';
            else if ($bs === 'completed') $statusKey = 'completed';
            else if ($bs === 'confirmed' || $ps === 'paid') $statusKey = 'confirmed';
            else {
                $k = is_array($so) ? strtolower(trim((string)($so['b2bStatusKey'] ?? ''))) : '';
                if (in_array($k, ['pending','partial','confirmed','completed','cancelled','refunded'], true)) $statusKey = $k;
                else $statusKey = 'pending';
            }
            $booking['statusKey'] = $statusKey;
        } catch (Throwable $e) { }

        // (2) 자동 예약 취소:
        // - 선금: 에이전트가 설정한 선금 입금 기한 경과 + 선금 증빙 미업로드 -> 자동 취소
        // - 잔금: 최고관리자가 설정한 잔금 입금 기한 경과 + 잔금 증빙 미업로드 -> 자동 취소
        $autoCancelled = false;
        try {
            $normalizeYmd = function ($raw) {
                $s = trim((string)$raw);
                if ($s === '') return '';
                // YYYYMMDD
                if (preg_match('/^\d{8}$/', $s)) {
                    return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
                }
                // YYYY-MM-DD... (DATE/DATETIME)
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
                    return substr($s, 0, 10);
                }
                // fallback
                $ts = strtotime($s);
                if ($ts === false) return '';
                return date('Y-m-d', $ts);
            };

            $today = date('Y-m-d');
            $bStatus = strtolower((string)($booking['bookingStatus'] ?? ''));
            $pStatus = strtolower((string)($booking['paymentStatus'] ?? ''));

            // 통일: downPaymentDueDate/downPaymentFile, balanceFile 사용
            $depositDue = $normalizeYmd($booking['downPaymentDueDate'] ?? '');
            $depositProof = trim((string)($booking['downPaymentFile'] ?? ''));

            $balanceDue = $normalizeYmd($booking['balanceDueDate'] ?? '');
            $balanceProof = trim((string)($booking['balanceFile'] ?? ''));

            $isCancelableState = ($bStatus !== 'cancelled' && $bStatus !== 'confirmed' && $bStatus !== 'completed');

            $shouldCancelDeposit = $isCancelableState
                && $pStatus === 'pending'
                && $depositDue !== ''
                && $depositDue < $today
                && $depositProof === '';

            // 잔금 자동 취소는 일반적으로 선금이 올라간(=partial 단계) 이후가 자연스럽지만,
            // 환경/운영 데이터에 따라 balanceDueDate만 먼저 설정되는 경우를 대비해
            // "잔금 기한이 설정되어 있고, 기한이 지났으며, 잔금 증빙이 없다"를 기준으로 취소합니다.
            // 단, 선금 기한 취소 조건에 먼저 걸리면 그게 우선 적용됩니다.
            $shouldCancelBalance = $isCancelableState
                && $pStatus === 'pending'
                && $balanceDue !== ''
                && $balanceDue < $today
                && $balanceProof === '';

            if ($shouldCancelDeposit || $shouldCancelBalance) {
                $previousStatus = $bStatus; // 변경 전 상태 저장
                $u = $conn->prepare("UPDATE bookings SET bookingStatus='cancelled', paymentStatus='failed' WHERE bookingId = ?");
                if ($u) {
                    $u->bind_param('s', $bookingId);
                    $u->execute();
                    $u->close();
                    $booking['bookingStatus'] = 'cancelled';
                    $booking['paymentStatus'] = 'failed';
                    $autoCancelled = true;

                    // Google Sheets APP 동기화
                    try {
                        require_once __DIR__ . '/../../../backend/lib/google_sheets.php';
                        gs_sync_booking_to_sheet($conn, (int)($booking['packageId'] ?? 0), date('Y-m-d', strtotime($booking['departureDate'] ?? '')));
                    } catch (Exception $gsEx) {
                        error_log("Sheets sync failed (auto-cancel): " . $gsEx->getMessage());
                    }

                    // 자동취소 히스토리 기록
                    __log_booking_status_change($conn, $bookingId, $previousStatus, 'cancelled', 'System (Auto-Cancel)', 'system');

                    // 에이전트에게 자동 취소 알림 이메일 발송
                    try {
                        if (function_exists('send_rejection_notification_email')) {
                            $cancelReason = $shouldCancelDeposit
                                ? 'Down payment due date has passed without payment proof submission. The booking has been automatically cancelled.'
                                : 'Balance payment due date has passed without payment proof submission. The booking has been automatically cancelled.';
                            send_rejection_notification_email($conn, $bookingId, 'auto_cancellation', $cancelReason);
                        }
                    } catch (Throwable $emailEx) {
                        error_log("Auto-cancel email failed for {$bookingId}: " . $emailEx->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // (3) 항공편 정보(package_flights) - 항상 object 형태로 내려서 프론트가 안정적으로 접근 가능하도록 함
        $flights = ['departure' => null, 'return' => null];
        try {
            $ft = $conn->query("SHOW TABLES LIKE 'package_flights'");
            if ($ft && $ft->num_rows > 0 && !empty($booking['packageId'])) {
                $fs = $conn->prepare("SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination
                                      FROM package_flights WHERE package_id = ? ORDER BY flight_type");
                if ($fs) {
                    $pid = intval($booking['packageId']);
                    $fs->bind_param('i', $pid);
                    $fs->execute();
                    $r = $fs->get_result();
                    while ($row = $r->fetch_assoc()) {
                        $ftype = (string)($row['flight_type'] ?? '');
                        if ($ftype === 'departure' && $flights['departure'] === null) $flights['departure'] = $row;
                        if ($ftype === 'return' && $flights['return'] === null) $flights['return'] = $row;
                    }
                    $fs->close();
                }
            }
        } catch (Throwable $e) { }
        $booking['flights'] = $flights;

        // (4) 여행자 정보(booking_travelers)
        $travelers = [];
        try {
            $tt = $conn->query("SHOW TABLES LIKE 'booking_travelers'");
            if ($tt && $tt->num_rows > 0) {
                // 환경별 컬럼 편차: reservationStatus/statusSyncDisabled/age가 있을 수 있음
                $cols = "bookingTravelerId, travelerType, title, firstName, lastName, birthDate, gender, nationality,
                         passportNumber, passportIssueDate, passportExpiry, passportImage, visaStatus, isMainTraveler";
                if (__table_has_column($conn, 'booking_travelers', 'reservationStatus')) $cols .= ", reservationStatus";
                if (__table_has_column($conn, 'booking_travelers', 'statusSyncDisabled')) $cols .= ", statusSyncDisabled";
                if (__table_has_column($conn, 'booking_travelers', 'age')) $cols .= ", age";
                if (__table_has_column($conn, 'booking_travelers', 'visaType')) $cols .= ", visaType";
                if (__table_has_column($conn, 'booking_travelers', 'visaDocument')) $cols .= ", visaDocument";
                if (__table_has_column($conn, 'booking_travelers', 'childRoom')) $cols .= ", childRoom";
                if (__table_has_column($conn, 'booking_travelers', 'infantSeat')) $cols .= ", infantSeat";
                if (__table_has_column($conn, 'booking_travelers', 'profile_source')) $cols .= ", profile_source";

                $ts = $conn->prepare("SELECT {$cols}
                                      FROM booking_travelers
                                      WHERE transactNo = ?
                                      ORDER BY isMainTraveler DESC, bookingTravelerId ASC");
                if ($ts) {
                    $tn = (string)($booking['transactNo'] ?? $bookingId);
                    $ts->bind_param('s', $tn);
                    $ts->execute();
                    $r = $ts->get_result();
                    while ($row = $r->fetch_assoc()) $travelers[] = $row;
                    $ts->close();
                }
            }
        } catch (Throwable $e) { }
        // normalize traveler fields for UI
        try {
            foreach ($travelers as &$t) {
                // title normalize: only MR/MS (UI 제한)
                $title = strtoupper(trim((string)($t['title'] ?? '')));
                if ($title === 'MISTER' || $title === 'MR' || $title === 'MSTR') $t['title'] = 'MR';
                else if ($title === 'MISS' || $title === 'MS' || $title === 'MRS') $t['title'] = 'MS';
                else $t['title'] = '';

                // passportImage normalize to "/uploads/..."
                $pi = trim((string)($t['passportImage'] ?? ''));
                if ($pi !== '' && str_starts_with($pi, 'uploads/')) $t['passportImage'] = '/' . $pi;

                // compute age if missing (schema may not have age column)
                if (!isset($t['age']) || $t['age'] === null || trim((string)$t['age']) === '') {
                    $bd = trim((string)($t['birthDate'] ?? ''));
                    if ($bd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd)) {
                        $dob = DateTime::createFromFormat('Y-m-d', $bd);
                        if ($dob) {
                            $now = new DateTime('today');
                            $t['age'] = $dob->diff($now)->y;
                        }
                    }
                }
            }
            unset($t);
        } catch (Throwable $e) { /* ignore */ }

        // (4.5) Fetch flight options for each traveler from booking_traveler_options
        try {
            $foptStmt = $conn->prepare("SELECT traveler_index, option_id, price FROM booking_traveler_options WHERE booking_id = ?");
            if ($foptStmt) {
                $foptStmt->bind_param('s', $bookingId);
                $foptStmt->execute();
                $foptResult = $foptStmt->get_result();
                $travelerFlightOpts = [];
                while ($frow = $foptResult->fetch_assoc()) {
                    $tidx = (int)$frow['traveler_index'];
                    if (!isset($travelerFlightOpts[$tidx])) {
                        $travelerFlightOpts[$tidx] = ['flightOptions' => [], 'flightOptionPrices' => []];
                    }
                    $optId = (int)$frow['option_id'];
                    $optPrice = (float)$frow['price'];
                    $travelerFlightOpts[$tidx]['flightOptions'][] = $optId;
                    $travelerFlightOpts[$tidx]['flightOptionPrices'][$optId] = $optPrice;
                }
                $foptStmt->close();
                // Assign to each traveler
                foreach ($travelers as $idx => &$t) {
                    if (isset($travelerFlightOpts[$idx])) {
                        $t['flightOptions'] = $travelerFlightOpts[$idx]['flightOptions'];
                        $t['flightOptionPrices'] = $travelerFlightOpts[$idx]['flightOptionPrices'];
                    } else {
                        $t['flightOptions'] = [];
                        $t['flightOptionPrices'] = [];
                    }
                }
                unset($t);
            }
        } catch (Throwable $e) { /* ignore */ }

        $booking['travelers'] = $travelers;

        // (5) 금액 파생값 - staged/middle/full은 DB에 정확한 balanceAmount가 있으므로 덮어쓰지 않음
        $paymentType = $booking['paymentType'] ?? '';
        if (!in_array($paymentType, ['staged', 'middle', 'full'], true)) {
            $total = floatval($booking['totalAmount'] ?? 0);
            $deposit = floatval($booking['depositAmount'] ?? 0);
            $booking['balanceAmount'] = max($total - $deposit, 0);
        }
        $booking['autoCancelled'] = $autoCancelled;

        // (6) roomSummary 계산: selectedOptions 내의 selectedRooms 기반
        $roomSummary = '';
        try {
            $soRaw = $booking['selectedOptions'] ?? '';
            $soObj = null;
            if (is_string($soRaw) && $soRaw !== '') {
                $tmp = json_decode($soRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $soObj = $tmp;
            }
            $selectedRoomsObj = null;
            // bookings.selectedRooms 컬럼 우선, 없으면 selectedOptions.selectedRooms 사용
            $srRaw = $booking['selectedRooms'] ?? '';
            if (is_string($srRaw) && $srRaw !== '') {
                $tmp = json_decode($srRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) $selectedRoomsObj = $tmp;
            }
            if (empty($selectedRoomsObj) && is_array($soObj) && isset($soObj['selectedRooms'])) {
                $selectedRoomsObj = $soObj['selectedRooms'];
            }
            if (is_array($selectedRoomsObj)) {
                $parts = [];
                foreach ($selectedRoomsObj as $k => $room) {
                    if (!is_array($room)) continue;
                    $name = $room['roomType'] ?? $room['name'] ?? $k;
                    $count = intval($room['count'] ?? 0);
                    if ($count > 0) $parts[] = $name . ' x' . $count;
                }
                $roomSummary = implode(', ', $parts);
            }
        } catch (Throwable $e) { /* ignore */ }

        // pending_update 상태인 경우 booking_change_requests에서 변경 요청 정보 조회
        $changeRequest = null;
        if (strtolower($booking['bookingStatus'] ?? '') === 'pending_update') {
            try {
                $crStmt = $conn->prepare("SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' ORDER BY requestedAt DESC LIMIT 1");
                if ($crStmt) {
                    $crStmt->bind_param('s', $bookingId);
                    $crStmt->execute();
                    $crResult = $crStmt->get_result();
                    $cr = $crResult->fetch_assoc();
                    $crStmt->close();
                    if ($cr) {
                        $changeRequest = [
                            'id' => $cr['id'],
                            'changeType' => $cr['changeType'],
                            'originalStatus' => $cr['originalStatus'],
                            'originalPaymentStatus' => $cr['originalPaymentStatus'],
                            'targetStatus' => $cr['targetStatus'],
                            'targetPaymentStatus' => $cr['targetPaymentStatus'],
                            'previousData' => $cr['previousData'] ? json_decode($cr['previousData'], true) : null,
                            'newData' => $cr['newData'] ? json_decode($cr['newData'], true) : null,
                            'requestedBy' => $cr['requestedBy'],
                            'requestedByType' => $cr['requestedByType'] ?? 'employee',
                            'requestedAt' => $cr['requestedAt']
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // check_reject 상태인 경우 booking_change_requests에서 거절 정보 조회
        $rejectedRequest = null;
        if (strtolower($booking['bookingStatus'] ?? '') === 'check_reject') {
            try {
                $crStmt = $conn->prepare("SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'rejected' ORDER BY processedAt DESC LIMIT 1");
                if ($crStmt) {
                    $crStmt->bind_param('s', $bookingId);
                    $crStmt->execute();
                    $crResult = $crStmt->get_result();
                    $cr = $crResult->fetch_assoc();
                    $crStmt->close();
                    if ($cr) {
                        $rejectedRequest = [
                            'id' => $cr['id'],
                            'changeType' => $cr['changeType'],
                            'originalStatus' => $cr['originalStatus'],
                            'originalPaymentStatus' => $cr['originalPaymentStatus'],
                            'targetStatus' => $cr['targetStatus'],
                            'targetPaymentStatus' => $cr['targetPaymentStatus'],
                            'rejectReason' => $cr['rejectReason'] ?? '',
                            'processedBy' => $cr['processedBy'] ?? '',
                            'processedAt' => $cr['processedAt'] ?? ''
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // 세일 정보 조회 (saleName)
        try {
            $saleStmt = $conn->prepare("
                SELECT s.sale_name, s.discount_amount
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN package_available_dates pad ON pad.id = si.package_available_date_id
                WHERE pad.package_id = ?
                  AND pad.available_date = DATE(?)
                  AND s.is_active = 1
                  AND CURDATE() BETWEEN s.sale_start_date AND s.sale_end_date
                LIMIT 1
            ");
            if ($saleStmt) {
                $pkgId = intval($booking['packageId'] ?? 0);
                $depDate = $booking['departureDate'] ?? '';
                $saleStmt->bind_param('is', $pkgId, $depDate);
                $saleStmt->execute();
                $saleResult = $saleStmt->get_result();
                if ($saleRow = $saleResult->fetch_assoc()) {
                    $booking['saleName'] = $saleRow['sale_name'];
                    $booking['saleDiscountPerPerson'] = floatval($saleRow['discount_amount']);
                }
                $saleStmt->close();
            }
        } catch (Throwable $e) { /* ignore */ }

        // 해당 출발일의 B2B 가격 조회 (package_available_dates)
        try {
            $padStmt = $conn->prepare("
                SELECT b2b_price, b2b_child_price, b2b_infant_price, price
                FROM package_available_dates
                WHERE package_id = ? AND available_date = DATE(?)
                LIMIT 1
            ");
            if ($padStmt) {
                $pkgIdPad = intval($booking['packageId'] ?? 0);
                $depDatePad = $booking['departureDate'] ?? '';
                $padStmt->bind_param('is', $pkgIdPad, $depDatePad);
                $padStmt->execute();
                $padResult = $padStmt->get_result();
                if ($padRow = $padResult->fetch_assoc()) {
                    $booking['dateb2bPrice'] = $padRow['b2b_price'] !== null ? floatval($padRow['b2b_price']) : null;
                    $booking['dateb2bChildPrice'] = $padRow['b2b_child_price'] !== null ? floatval($padRow['b2b_child_price']) : null;
                    $booking['dateb2bInfantPrice'] = $padRow['b2b_infant_price'] !== null ? floatval($padRow['b2b_infant_price']) : null;
                    $booking['dateb2cPrice'] = $padRow['price'] !== null ? floatval($padRow['price']) : null;
                }
                $padStmt->close();
            }
        } catch (Throwable $e) { /* ignore */ }

        // Get payment data from new booking_payments table
        $paymentData = getPaymentsWithLegacyFormat($conn, $bookingId);
        $payments = $paymentData['payments'];
        $legacyPayments = $paymentData['legacy'];

        // Merge legacy payment data into booking for backward compatibility
        foreach ($legacyPayments as $key => $value) {
            if (!isset($booking[$key]) || $booking[$key] === null || $booking[$key] === '') {
                $booking[$key] = $value;
            }
        }

        // Option payment status 조회
        try {
            $opStmt = $conn->prepare("SELECT option_payment_status FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
            if ($opStmt) {
                $opStmt->bind_param('s', $bookingId);
                $opStmt->execute();
                $opResult = $opStmt->get_result();
                $opRow = $opResult->fetch_assoc();
                $opStmt->close();
                $booking['optionPaymentStatus'] = $opRow ? ($opRow['option_payment_status'] ?? 'not_set') : 'not_set';
            }
        } catch (Throwable $e) {
            $booking['optionPaymentStatus'] = 'not_set';
        }

        send_success_response([
            'booking' => $booking,
            'roomSummary' => $roomSummary,
            'selectedOptions' => $selectedOptionsObj,
            'selectedRooms' => $selectedRoomsObj,
            'changeRequest' => $changeRequest,
            'rejectedRequest' => $rejectedRequest,
            'payments' => $payments  // New normalized payments array
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get B2B booking detail: ' . $e->getMessage());
    }
}

function updateB2BBooking($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        $isSuperAdmin = __is_super_admin($conn);

        // 현재 상태 조회(환불 완료 이후 상태 변경 잠금 검증)
        $current = null;
        try {
            $st0 = $conn->prepare("SELECT bookingStatus, paymentStatus, COALESCE(NULLIF(transactNo,''), bookingId) as transactKey, selectedOptions
                                   FROM bookings WHERE bookingId = ? LIMIT 1");
            if ($st0) {
                $st0->bind_param('s', $bookingId);
                $st0->execute();
                $current = $st0->get_result()->fetch_assoc();
                $st0->close();
            }
        } catch (Throwable $e) { $current = null; }
        if (!$current) {
            send_error_response('Booking not found', 404);
        }
        $currentPay = strtolower((string)($current['paymentStatus'] ?? ''));
        $currentBook = strtolower((string)($current['bookingStatus'] ?? ''));
        $travelerKey = (string)($current['transactKey'] ?? $bookingId);

        // 환불 완료 이후에는 상태값 변경 불가(전체/개별)
        $incomingStatusKey = $input['statusKey'] ?? $input['bookingStatus'] ?? null;
        $lockStatusChanges = ($currentPay === 'refunded');
        if ($lockStatusChanges) {
            if ($incomingStatusKey !== null) {
                $k = strtolower(trim((string)$incomingStatusKey));
                if ($k !== 'refunded') {
                    send_error_response('Refund Completed 상태에서는 예약 상태를 변경할 수 없습니다.', 400);
                }
            }
            // traveler 정보는 수정 가능하되, "예약 상태" 관련 필드는 저장하지 않는다(아래 로직에서 건너뜀)
        }

        // 환경별 스키마 편차 대응 (가이드 배정 저장용)
        __ensure_bookings_guideid_column($conn);

        // balanceDueDate 컬럼이 없는 환경 대응 (통일: balanceFile 사용, 레거시 balanceProofFile 미생성)
        try {
            if (!__table_has_column($conn, 'bookings', 'balanceDueDate')) {
                $conn->query("ALTER TABLE `bookings` ADD COLUMN `balanceDueDate` DATE NULL");
            }
        } catch (Throwable $e) {
            // ignore (schema may be managed externally)
        }

        $updates = [];
        $values = [];
        $types = '';
        $changedToPendingUpdate = false; // pending_update로 변경되었는지 추적

        // UI 상태 키를 DB bookingStatus/paymentStatus로 매핑
        // - pending: 선금 대기
        // - partial: 잔금 대기(= downPaymentFile 존재 전제, DB는 pending 유지)
        // - confirmed: 예약 확정 + 결제 완료
        // - completed: 여행 완료 + 결제 완료
        // - cancelled: 예약 취소
        // - refunded: 환불 완료(예약 취소 + paymentStatus refunded)
        $statusKey = $input['statusKey'] ?? $input['bookingStatus'] ?? null;
        $statusChangeReason = isset($input['statusChangeReason']) ? trim((string)$input['statusChangeReason']) : null;

        if ($statusKey !== null) {
            $k = strtolower(trim((string)$statusKey));

            // 11단계 상태 맵핑 (bookingStatus enum 직접 저장)
            $elevenStepStatuses = [
                'waiting_down_payment', 'checking_down_payment',
                'waiting_second_payment', 'checking_second_payment',
                'waiting_balance', 'checking_balance',
                'waiting_full_payment', 'checking_full_payment',
                'pending_update', 'check_reject', 'rejected'
            ];

            // 모든 상태 변경 시 사유 필수
            if ($currentBook !== $k) {
                if (empty($statusChangeReason)) {
                    send_error_response('Status change reason is required', 400);
                }
            }

            // 직접 저장 로직 (pending_update 플로우 제거)
            if (in_array($k, $elevenStepStatuses, true)) {
                // 11단계 상태는 bookingStatus에 직접 저장
                $updates[] = "bookingStatus = ?";
                $values[] = $k;
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'pending';
                $types .= 's';
            } elseif ($k === 'pending' || $k === 'partial') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'pending';
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'pending';
                $types .= 's';
            } elseif ($k === 'confirmed') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'confirmed';
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'paid';
                $types .= 's';
            } elseif ($k === 'completed') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'completed';
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'paid';
                $types .= 's';
            } elseif ($k === 'waiting_cancelled') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'waiting_cancelled';
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'failed';
                $types .= 's';
            } elseif ($k === 'cancelled') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'cancelled';
                $types .= 's';
            } elseif ($k === 'refunded') {
                $updates[] = "bookingStatus = ?";
                $values[] = 'refunded';
                $types .= 's';
                $updates[] = "paymentStatus = ?";
                $values[] = 'refunded';
                $types .= 's';
            }
        }

        // guideId
        if (array_key_exists('guideId', $input)) {
            $gid = $input['guideId'];
            if ($gid === '' || $gid === null) {
                $updates[] = "guideId = NULL";
            } else {
                $updates[] = "guideId = ?";
                $values[] = intval($gid);
                $types .= 'i';
            }
        }

        // balanceDueDate (잔금 기한)
        if (array_key_exists('balanceDueDate', $input)) {
            $updates[] = "balanceDueDate = ?";
            $values[] = $input['balanceDueDate'] ?: null;
            $types .= 's';
        }
        
        if (!empty($updates)) {
            $values[] = $bookingId;
            // bookings.bookingId는 varchar
            $types .= 's';
            $sql = "UPDATE bookings SET " . implode(', ', $updates) . " WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            mysqli_bind_params_by_ref($stmt, $types, $values);
            $stmt->execute();
            $stmt->close();
        }

        // 상태 변경 히스토리 저장
        if ($statusKey !== null && !$lockStatusChanges) {
            $newStatus = strtolower(trim((string)$statusKey));
            $oldStatus = $currentBook;
            if ($newStatus !== $oldStatus) {
                __log_booking_status_change($conn, $bookingId, $oldStatus, $newStatus, null, null, $statusChangeReason);
                // booking_history에도 확장된 이력 추가
                __addBookingHistory($conn, $bookingId, "Status changed: {$oldStatus} → {$newStatus}", [
                    'changeType' => 'status',
                    'changedBy' => $_SESSION['admin_username'] ?? 'admin',
                    'changedByType' => $_SESSION['admin_userType'] ?? 'admin_ph',
                    'previousData' => json_encode(['bookingStatus' => $oldStatus, 'paymentStatus' => $currentPay]),
                    'newData' => json_encode(['bookingStatus' => $newStatus]),
                    'reason' => $statusChangeReason
                ]);
            }
        }

        // SMT 수정 시작 - 옵션 값 저장 (cabinBaggage, breakfastRequest, wifiRental, selectedRooms)
        $hasOptions = array_key_exists('cabinBaggage', $input) ||
                      array_key_exists('breakfastRequest', $input) ||
                      array_key_exists('wifiRental', $input) ||
                      array_key_exists('selectedRooms', $input) ||
                      array_key_exists('statusKey', $input) ||
                      array_key_exists('bookingStatus', $input);
        if ($hasOptions) {
            try {
                $soRaw = (string)($current['selectedOptions'] ?? '');
                $soObj = null;
                if ($soRaw !== '') {
                    $tmp = json_decode($soRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $soObj = $tmp;
                }
                if (!is_array($soObj)) $soObj = [];

                if (array_key_exists('cabinBaggage', $input)) {
                    $soObj['cabinBaggage'] = $input['cabinBaggage'];
                }
                if (array_key_exists('breakfastRequest', $input)) {
                    $soObj['breakfastRequest'] = $input['breakfastRequest'];
                }
                if (array_key_exists('wifiRental', $input)) {
                    $soObj['wifiRental'] = $input['wifiRental'];
                }
                if (array_key_exists('selectedRooms', $input)) {
                    $soObj['selectedRooms'] = $input['selectedRooms'];
                }

                // B2B 예약 상태키 저장(관리자 선택값을 고정 표시하기 위함)
                if (!$lockStatusChanges && $incomingStatusKey !== null) {
                    $k = strtolower(trim((string)$incomingStatusKey));
                    if (in_array($k, ['pending','partial','confirmed','completed','cancelled','refunded'], true)) {
                        $soObj['b2bStatusKey'] = $k;
                    }
                }

                $newRaw = json_encode($soObj, JSON_UNESCAPED_UNICODE);
                if (__table_has_column($conn, 'bookings', 'selectedOptions')) {
                    $st = $conn->prepare("UPDATE bookings SET selectedOptions = ? WHERE bookingId = ?");
                    if ($st) {
                        $st->bind_param('ss', $newRaw, $bookingId);
                        $st->execute();
                        $st->close();
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }
        // SMT 수정 완료

        // 최고관리자: Customer/Traveler 정보 수정
        $hasCustomerInfo = isset($input['customerInfo']) && is_array($input['customerInfo']);
        $hasTravelers = isset($input['travelers']) && is_array($input['travelers']);

        if (($hasCustomerInfo || $hasTravelers) && !$isSuperAdmin) {
            send_error_response('최고관리자만 고객/여행자 정보를 수정할 수 있습니다.', 403);
        }

        // 상태 키(전체) - traveler sync 처리용
        $effectiveStatusKey = null;
        if ($incomingStatusKey !== null) {
            $effectiveStatusKey = strtolower(trim((string)$incomingStatusKey));
        } else {
            // 현재 booking/payment 상태로부터 UI 키 유도
            if ($currentPay === 'refunded') $effectiveStatusKey = 'refunded';
            else if ($currentBook === 'cancelled') $effectiveStatusKey = 'cancelled';
            else if ($currentBook === 'completed') $effectiveStatusKey = 'completed';
            else if ($currentBook === 'confirmed' || $currentPay === 'paid') $effectiveStatusKey = 'confirmed';
            else $effectiveStatusKey = 'pending';
        }

        // Customer Info 또는 Traveler 변경 시 pending_update 플로우 적용 (통합 처리)
        $hasCustomerOrTravelerChanges = $hasCustomerInfo || $hasTravelers;

        if ($hasCustomerOrTravelerChanges) {
            // transactNo 조회 (traveler 조회용)
            $travelerKeyForPending = $bookingId;
            try {
                $tkStmt = $conn->prepare("SELECT COALESCE(NULLIF(transactNo,''), bookingId) as tk FROM bookings WHERE bookingId = ? LIMIT 1");
                if ($tkStmt) {
                    $tkStmt->bind_param('s', $bookingId);
                    $tkStmt->execute();
                    $tkRes = $tkStmt->get_result();
                    if ($tkRes && $tkRow = $tkRes->fetch_assoc()) {
                        $travelerKeyForPending = $tkRow['tk'];
                    }
                    $tkStmt->close();
                }
            } catch (Throwable $e) { /* ignore */ }

            // 현재 Customer Info 조회
            $currentCustomerInfo = null;
            try {
                $soRaw = '';
                $stRead = $conn->prepare("SELECT contactName, contactEmail, contactPhone, selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
                if ($stRead) {
                    $stRead->bind_param('s', $bookingId);
                    $stRead->execute();
                    $readRes = $stRead->get_result();
                    if ($readRes && $readRow = $readRes->fetch_assoc()) {
                        $soRaw = (string)($readRow['selectedOptions'] ?? '');
                        $soObj = json_decode($soRaw, true);
                        $currentCustomerInfo = $soObj['customerInfo'] ?? [
                            'name' => $readRow['contactName'] ?? '',
                            'email' => $readRow['contactEmail'] ?? '',
                            'phone' => $readRow['contactPhone'] ?? ''
                        ];
                    }
                    $stRead->close();
                }
            } catch (Throwable $e) { /* ignore */ }

            // 현재 Travelers 데이터 조회
            $currentTravelers = [];
            try {
                __ensure_booking_travelers_status_columns($conn);
                $travStmt = $conn->prepare("SELECT * FROM booking_travelers WHERE transactNo = ?");
                if ($travStmt) {
                    $travStmt->bind_param('s', $travelerKeyForPending);
                    $travStmt->execute();
                    $travResult = $travStmt->get_result();
                    while ($travRow = $travResult->fetch_assoc()) {
                        $currentTravelers[] = $travRow;
                    }
                    $travStmt->close();
                }
            } catch (Throwable $e) { /* ignore */ }

            // 실제 변경이 있는지 비교 (변경 없으면 pending_update 건너뜀)
            $hasActualChanges = false;

            // 1) Customer Info 비교
            if ($hasCustomerInfo && $currentCustomerInfo) {
                $incomingCI = $input['customerInfo'];
                $normalizeStr = function($v) { return strtolower(trim((string)($v ?? ''))); };
                if ($normalizeStr($incomingCI['name'] ?? '') !== $normalizeStr($currentCustomerInfo['name'] ?? '')
                    || $normalizeStr($incomingCI['email'] ?? '') !== $normalizeStr($currentCustomerInfo['email'] ?? '')
                    || preg_replace('/[^0-9+]/', '', (string)($incomingCI['phone'] ?? '')) !== preg_replace('/[^0-9+]/', '', (string)($currentCustomerInfo['phone'] ?? ''))
                ) {
                    $hasActualChanges = true;
                }
            }

            // 2) Travelers 비교
            if (!$hasActualChanges && $hasTravelers) {
                $incomingTravelers = $input['travelers'];
                // 여행자 수 변경 체크
                if (count($incomingTravelers) !== count($currentTravelers)) {
                    $hasActualChanges = true;
                } else {
                    // 기존 traveler를 ID로 맵핑
                    $currentTravelerMap = [];
                    foreach ($currentTravelers as $ct) {
                        $ctId = intval($ct['bookingTravelerId'] ?? 0);
                        if ($ctId > 0) $currentTravelerMap[$ctId] = $ct;
                    }
                    $compareFields = ['title','firstName','lastName','gender','birthDate','nationality','passportNumber','passportIssueDate','passportExpiry','visaStatus'];
                    foreach ($incomingTravelers as $it) {
                        if (!is_array($it)) continue;
                        $itId = intval($it['bookingTravelerId'] ?? 0);
                        if ($itId <= 0) {
                            // 신규 traveler = 변경 있음
                            $hasActualChanges = true;
                            break;
                        }
                        if (!isset($currentTravelerMap[$itId])) {
                            $hasActualChanges = true;
                            break;
                        }
                        $ct = $currentTravelerMap[$itId];
                        foreach ($compareFields as $f) {
                            $inVal = trim((string)($it[$f] ?? ''));
                            // passportExpiry는 프론트에서 passportExpiry로 오지만 DB 컬럼명이 passportExpiry 또는 passportExpiryDate일 수 있음
                            $dbVal = trim((string)($ct[$f] ?? $ct[$f . 'Date'] ?? ''));
                            // 빈값/null 통일
                            if ($inVal === '' || $inVal === '0000-00-00') $inVal = '';
                            if ($dbVal === '' || $dbVal === '0000-00-00') $dbVal = '';
                            if (strtolower($inVal) !== strtolower($dbVal)) {
                                $hasActualChanges = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            // 실제 변경이 없으면 건너뜀
            if (!$hasActualChanges) {
                // 변경 없이 저장 - 기존 상태 유지하고 정상 응답
                // (guideId, balanceDueDate 등 다른 필드는 이미 위에서 처리됨)
                send_success_response([], 'Booking updated successfully (no customer/traveler changes detected)');
            }

            // Customer Info가 변경된 경우 직접 저장
            if ($hasCustomerInfo) {
                $ci = $input['customerInfo'];
                $ciUpdates = []; $ciVals = []; $ciTypes = '';
                if (isset($ci['name']))  { $ciUpdates[] = "contactName = ?";  $ciVals[] = $ci['name'];  $ciTypes .= 's'; }
                if (isset($ci['email'])) { $ciUpdates[] = "contactEmail = ?"; $ciVals[] = $ci['email']; $ciTypes .= 's'; }
                if (isset($ci['phone'])) { $ciUpdates[] = "contactPhone = ?"; $ciVals[] = $ci['phone']; $ciTypes .= 's'; }
                if (!empty($ciUpdates)) {
                    $ciVals[] = $bookingId; $ciTypes .= 's';
                    $ciSql = "UPDATE bookings SET " . implode(', ', $ciUpdates) . ", updatedAt = NOW() WHERE bookingId = ?";
                    $ciStmt = $conn->prepare($ciSql);
                    mysqli_bind_params_by_ref($ciStmt, $ciTypes, $ciVals);
                    $ciStmt->execute();
                    $ciStmt->close();
                }
            }

            // 이력 로깅 (확장된 함수)
            $previousDataJson = json_encode([
                'originalCustomerInfo' => $currentCustomerInfo,
                'originalTravelers' => $currentTravelers
            ], JSON_UNESCAPED_UNICODE);

            __addBookingHistory($conn, $bookingId, 'Customer/Traveler info updated directly by admin', [
                'changeType' => 'travelers',
                'changedBy' => $_SESSION['admin_username'] ?? 'admin',
                'changedByType' => $_SESSION['admin_userType'] ?? 'admin_ph',
                'previousData' => $previousDataJson,
                'newData' => json_encode([
                    'customerInfo' => $hasCustomerInfo ? $input['customerInfo'] : null,
                    'travelers' => $hasTravelers ? $input['travelers'] : null
                ], JSON_UNESCAPED_UNICODE)
            ]);
        }

        // Travelers 저장(인원옵션 travelerType은 read-only)
        if ($hasTravelers) {
            __ensure_booking_travelers_status_columns($conn);

            // 허용 상태
            $allowedStatus = ['pending','partial','confirmed','completed','cancelled','refunded','rejected','waiting_down_payment','checking_down_payment','waiting_second_payment','checking_second_payment','waiting_balance','checking_balance','waiting_full_payment','checking_full_payment'];

            // 아래 코드는 pending/pending_update/check_reject 상태일 때만 실행됨 (직접 저장)

            // 먼저 기존 여행자 ID 목록 수집 및 삭제 처리
            $sentTravelerIds = [];
            foreach ($input['travelers'] as $tr) {
                if (!is_array($tr)) continue;
                $tid = intval($tr['bookingTravelerId'] ?? 0);
                if ($tid > 0) $sentTravelerIds[] = $tid;
            }

            // 기존 여행자 중 프론트에서 보내지 않은 여행자 삭제 (UPDATE/INSERT 전에 실행)
            try {
                if (!empty($sentTravelerIds)) {
                    // 일부 기존 여행자가 있는 경우: 해당 ID 외의 여행자 삭제
                    $placeholders = implode(',', array_fill(0, count($sentTravelerIds), '?'));
                    $delSql = "DELETE FROM booking_travelers WHERE transactNo = ? AND bookingTravelerId NOT IN ($placeholders)";
                    $delStmt = $conn->prepare($delSql);
                    if ($delStmt) {
                        $delTypes = 's' . str_repeat('i', count($sentTravelerIds));
                        $delParams = array_merge([$travelerKey], $sentTravelerIds);
                        mysqli_bind_params_by_ref($delStmt, $delTypes, $delParams);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                } else if (count($input['travelers']) > 0) {
                    // 기존 여행자 없이 신규만 있는 경우: 기존 여행자 모두 삭제
                    $delStmt = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
                    if ($delStmt) {
                        $delStmt->bind_param('s', $travelerKey);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                }
            } catch (Throwable $e) { /* ignore */ }

            // 여행자 UPDATE/INSERT
            foreach ($input['travelers'] as $tr) {
                if (!is_array($tr)) continue;
                $tid = intval($tr['bookingTravelerId'] ?? 0);

                // 신규 여행자 추가 (bookingTravelerId가 없는 경우)
                if ($tid <= 0) {
                    try {
                        $travelerType = strtolower(trim((string)($tr['travelerType'] ?? $tr['type'] ?? 'adult')));
                        $title = trim((string)($tr['title'] ?? ''));
                        $firstName = trim((string)($tr['firstName'] ?? ''));
                        $lastName = trim((string)($tr['lastName'] ?? ''));
                        $birthDate = trim((string)($tr['birthDate'] ?? ''));
                        $gender = strtolower(trim((string)($tr['gender'] ?? '')));
                        $nationality = trim((string)($tr['nationality'] ?? ''));
                        $passportNumber = trim((string)($tr['passportNumber'] ?? ''));
                        $passportIssueDate = trim((string)($tr['passportIssueDate'] ?? ''));
                        $passportExpiry = trim((string)($tr['passportExpiry'] ?? ''));
                        $isMainTraveler = intval($tr['isMainTraveler'] ?? $tr['isMain'] ?? 0);

                        // ENUM 필드 처리: 빈 값이면 NULL로 설정
                        $titleVal = in_array($title, ['MR','MRS','MS','DR']) ? $title : null;
                        $genderVal = in_array($gender, ['male','female']) ? $gender : null;
                        $birthDateVal = ($birthDate !== '' && $birthDate !== '0000-00-00') ? $birthDate : null;
                        $passportIssueDateVal = ($passportIssueDate !== '' && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                        $passportExpiryVal = ($passportExpiry !== '' && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;

                        $insertSql = "INSERT INTO booking_travelers (transactNo, travelerType, title, firstName, lastName, birthDate, gender, nationality, passportNumber, passportIssueDate, passportExpiry, isMainTraveler, reservationStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stInsert = $conn->prepare($insertSql);
                        if ($stInsert) {
                            $stInsert->bind_param('sssssssssssis', $travelerKey, $travelerType, $titleVal, $firstName, $lastName, $birthDateVal, $genderVal, $nationality, $passportNumber, $passportIssueDateVal, $passportExpiryVal, $isMainTraveler, $effectiveStatusKey);
                            $stInsert->execute();
                            $stInsert->close();
                        }
                    } catch (Throwable $e) {
                        error_log("Insert traveler error: " . $e->getMessage());
                    }
                    continue;
                }

                // traveller가 이 예약(transactNo)에 속하는지 검증
                $ok = false;
                try {
                    $chk = $conn->prepare("SELECT bookingTravelerId FROM booking_travelers WHERE bookingTravelerId = ? AND transactNo = ? LIMIT 1");
                    if ($chk) {
                        $chk->bind_param('is', $tid, $travelerKey);
                        $chk->execute();
                        $rs = $chk->get_result();
                        $ok = ($rs && $rs->num_rows > 0);
                        $chk->close();
                    }
                } catch (Throwable $e) { $ok = false; }
                if (!$ok) continue;

                $fields = [];
                $vals = [];
                $types = '';

                // editable fields
                $map = [
                    'title' => 'title',
                    'firstName' => 'firstName',
                    'lastName' => 'lastName',
                    'birthDate' => 'birthDate',
                    'gender' => 'gender',
                    'nationality' => 'nationality',
                    'passportNumber' => 'passportNumber',
                    'passportIssueDate' => 'passportIssueDate',
                    'passportExpiry' => 'passportExpiry',
                    'visaStatus' => 'visaStatus'
                ];
                foreach ($map as $inKey => $col) {
                    if (array_key_exists($inKey, $tr) && __table_has_column($conn, 'booking_travelers', $col)) {
                        $fields[] = "{$col} = ?";
                        $vals[] = ($tr[$inKey] === '' ? null : $tr[$inKey]);
                        $types .= 's';
                    }
                }
                // age (optional)
                if (array_key_exists('age', $tr) && __table_has_column($conn, 'booking_travelers', 'age')) {
                    $fields[] = "age = ?";
                    $vals[] = ($tr['age'] === '' ? null : $tr['age']);
                    $types .= 's';
                }

                // reservationStatus + syncDisabled
                if (!$lockStatusChanges && __table_has_column($conn, 'booking_travelers', 'reservationStatus') && __table_has_column($conn, 'booking_travelers', 'statusSyncDisabled')) {
                    $rs = strtolower(trim((string)($tr['reservationStatus'] ?? '')));
                    if ($rs !== '' && !in_array($rs, $allowedStatus, true)) {
                        send_error_response('Invalid traveler reservationStatus', 400);
                    }
                    $syncDisabled = (int)($tr['statusSyncDisabled'] ?? 0);
                    // sync 규칙: 전체 상태와 다르면 syncDisabled=1, 같으면 0 (명시값보다 규칙 우선)
                    if ($rs === '' || $rs === $effectiveStatusKey) {
                        $syncDisabled = 0;
                        $rs = $effectiveStatusKey;
                    } else {
                        $syncDisabled = 1;
                    }
                    $fields[] = "reservationStatus = ?";
                    $vals[] = $rs;
                    $types .= 's';
                    $fields[] = "statusSyncDisabled = ?";
                    $vals[] = $syncDisabled;
                    $types .= 'i';
                }

                if (!empty($fields)) {
                    $vals[] = $tid;
                    $types .= 'i';
                    $st = $conn->prepare("UPDATE booking_travelers SET " . implode(', ', $fields) . " WHERE bookingTravelerId = ?");
                    if ($st) {
                        mysqli_bind_params_by_ref($st, $types, $vals);
                        $st->execute();
                        $st->close();
                    }
                }

                // ✅ B2B(에이전트 예약)에서도 비자 신청 내역 생성:
                // traveler.visaStatus 가 applied면 visa_applications에 (booking+traveler) 단위로 생성/중복 방지
                try {
                    $visaVal = strtolower(trim((string)($tr['visaStatus'] ?? '')));
                    if ($visaVal === 'applied') {
                        $fn = trim((string)($tr['firstName'] ?? ''));
                        $ln = trim((string)($tr['lastName'] ?? ''));
                        $applicantName = trim(($fn !== '' || $ln !== '') ? ($fn . ' ' . $ln) : '');
                        if ($applicantName === '') $applicantName = 'Applicant';
                        __ensure_visa_application_for_booking_traveler($conn, (string)$bookingId, (int)$tid, $applicantName);
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            // 전체 상태 변경 시: 동기화된 여행자( statusSyncDisabled=0 )의 상태를 전체에 맞춰 동기화
            if (!$lockStatusChanges && $incomingStatusKey !== null && __table_has_column($conn, 'booking_travelers', 'reservationStatus') && __table_has_column($conn, 'booking_travelers', 'statusSyncDisabled')) {
                $k = strtolower(trim((string)$incomingStatusKey));
                if (in_array($k, $allowedStatus, true)) {
                    $st = $conn->prepare("UPDATE booking_travelers
                                          SET reservationStatus = ?, statusSyncDisabled = 0
                                          WHERE transactNo = ? AND COALESCE(statusSyncDisabled,0) = 0");
                    if ($st) {
                        $st->bind_param('ss', $k, $travelerKey);
                        $st->execute();
                        $st->close();
                    }
                }
            }

            // numberOfPeople 업데이트: booking_travelers에서 현재 여행자 수 카운트 후 bookings 업데이트
            try {
                $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM booking_travelers WHERE transactNo = ?");
                if ($countStmt) {
                    $countStmt->bind_param('s', $travelerKey);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $travelerCount = 0;
                    if ($countResult && $row = $countResult->fetch_assoc()) {
                        $travelerCount = intval($row['cnt']);
                    }
                    $countStmt->close();

                    if ($travelerCount > 0 && __table_has_column($conn, 'bookings', 'numberOfPeople')) {
                        $updPeople = $conn->prepare("UPDATE bookings SET numberOfPeople = ? WHERE bookingId = ?");
                        if ($updPeople) {
                            $updPeople->bind_param('is', $travelerCount, $bookingId);
                            $updPeople->execute();
                            $updPeople->close();
                        }
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // pending_update로 변경된 경우 응답에 포함
        $responseData = [];
        if ($changedToPendingUpdate) {
            $responseData['newStatus'] = 'pending_update';
        }
        send_success_response($responseData, 'Booking updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update B2B booking: ' . $e->getMessage());
    }
}

// Edit Travelers 3단계 플로우: travelers + rooms 동시 저장
function updateB2BBookingTravelersAndRooms($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        $isSuperAdmin = __is_super_admin($conn);
        if (!$isSuperAdmin) {
            send_error_response('최고관리자만 여행자 정보를 수정할 수 있습니다.', 403);
        }

        $travelers = $input['travelers'] ?? [];
        $selectedRooms = $input['selectedRooms'] ?? [];

        if (!is_array($travelers) || empty($travelers)) {
            send_error_response('Travelers data is required');
        }

        // 현재 예약 정보 조회
        $stmt = $conn->prepare("SELECT bookingId, bookingStatus, paymentStatus, COALESCE(NULLIF(transactNo,''), bookingId) as transactKey, selectedOptions, packageId, departureDate, COALESCE(adults,0)+COALESCE(children,0)+COALESCE(infantsWithSeat,0) as currentPax FROM bookings WHERE bookingId = ? LIMIT 1");
        if (!$stmt) {
            send_error_response('Database error');
        }
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            send_error_response('Booking not found', 404);
        }

        $currentBook = strtolower((string)($booking['bookingStatus'] ?? ''));
        $currentPay = strtolower((string)($booking['paymentStatus'] ?? ''));
        $travelerKey = (string)($booking['transactKey'] ?? $bookingId);

        // 잔여 좌석 검증: 인원 추가 시 재고 초과 방지 (agent-api createReservation과 동일한 동적 계산)
        // 좌석 점유 인원만 카운트 (lap infant 제외)
        $newSeatCount = 0;
        foreach ($travelers as $t) {
            $tType = strtolower($t['type'] ?? '');
            if ($tType === 'infant' && empty($t['infantSeat'])) continue; // lap infant
            $newSeatCount++;
        }
        $newTravelerCount = $newSeatCount;
        $currentPax = (int)($booking['currentPax'] ?? 0);
        $seatDifference = $newTravelerCount - $currentPax;

        // 관리자도 pending_update 프로세스를 거침
        $skipPendingUpdate = false;

        if (!$skipPendingUpdate) {
            // 트랜잭션 시작: 좌석 체크 + change request + bookings 업데이트를 원자적으로 처리
            $conn->begin_transaction();
            try {
                $packageId = (int)($booking['packageId'] ?? 0);
                $departureDate = $booking['departureDate'] ?? '';

                // FOR UPDATE 잠금으로 동시성 보호 (인원 증가 시)
                if ($seatDifference > 0 && !empty($packageId) && !empty($departureDate)) {
                    // 1) capacity 조회
                    $maxSeats = 0;
                    $capStmt = $conn->prepare("SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                    if ($capStmt) {
                        $capStmt->bind_param('is', $packageId, $departureDate);
                        $capStmt->execute();
                        $capRow = $capStmt->get_result()->fetch_assoc();
                        if ($capRow) {
                            $maxSeats = (int)($capRow['capacity'] ?? 0);
                        }
                        $capStmt->close();
                    }
                    // capacity fallback: package_available_dates 행이 없을 때만 packages.maxParticipants
                    // (행이 있고 capacity=0이면 마감 → fallback하지 않음)
                    if (!$capRow) {
                        $pkgStmt = $conn->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
                        if ($pkgStmt) {
                            $pkgStmt->bind_param('i', $packageId);
                            $pkgStmt->execute();
                            $pkgRow = $pkgStmt->get_result()->fetch_assoc();
                            if ($pkgRow) {
                                $maxSeats = (int)($pkgRow['maxParticipants'] ?? 0);
                            }
                            $pkgStmt->close();
                        }
                    }
                    // 2) 실제 예약 수 동적 계산 (FOR UPDATE 잠금)
                    $bookedSeats = 0;
                    $bkStmt = $conn->prepare(
                        "SELECT SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                         FROM bookings
                         WHERE packageId = ? AND departureDate = ?
                           AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                           AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                         FOR UPDATE"
                    );
                    if ($bkStmt) {
                        $bkStmt->bind_param('is', $packageId, $departureDate);
                        $bkStmt->execute();
                        $bkRow = $bkStmt->get_result()->fetch_assoc();
                        if ($bkRow) {
                            $bookedSeats = (int)($bkRow['booked'] ?? 0);
                        }
                        $bkStmt->close();
                    }
                    // 3) 잔여 좌석 = maxSeats - bookedSeats (현재 예약 포함된 상태이므로 추가분만 체크)
                    $remainingSeats = $maxSeats - $bookedSeats;
                    if ($seatDifference > $remainingSeats) {
                        $conn->rollback();
                        send_error_response("Not enough seats available. Remaining: {$remainingSeats}, Requested additional: {$seatDifference}", 400);
                    }
                }

                // 현재 travelers 데이터 조회
                $currentTravelers = [];
                try {
                    $travStmt = $conn->prepare("SELECT * FROM booking_travelers WHERE transactNo = ?");
                    if ($travStmt) {
                        $travStmt->bind_param('s', $travelerKey);
                        $travStmt->execute();
                        $travResult = $travStmt->get_result();
                        while ($travRow = $travResult->fetch_assoc()) {
                            $currentTravelers[] = $travRow;
                        }
                        $travStmt->close();
                    }

                    // flightOptions도 함께 조회하여 각 traveler에 추가
                    $foptStmt = $conn->prepare("SELECT traveler_index, option_id, price FROM booking_traveler_options WHERE booking_id = ?");
                    if ($foptStmt) {
                        $foptStmt->bind_param('s', $bookingId);
                        $foptStmt->execute();
                        $foptResult = $foptStmt->get_result();
                        $travelerFlightOpts = [];
                        while ($frow = $foptResult->fetch_assoc()) {
                            $tidx = (int)$frow['traveler_index'];
                            if (!isset($travelerFlightOpts[$tidx])) {
                                $travelerFlightOpts[$tidx] = ['flightOptions' => [], 'flightOptionPrices' => []];
                            }
                            $optId = (int)$frow['option_id'];
                            $optPrice = (float)$frow['price'];
                            $travelerFlightOpts[$tidx]['flightOptions'][] = $optId;
                            $travelerFlightOpts[$tidx]['flightOptionPrices'][$optId] = $optPrice;
                        }
                        $foptStmt->close();

                        // currentTravelers에 flightOptions 추가
                        foreach ($currentTravelers as $idx => &$trav) {
                            if (isset($travelerFlightOpts[$idx])) {
                                $trav['flightOptions'] = $travelerFlightOpts[$idx]['flightOptions'];
                                $trav['flightOptionPrices'] = $travelerFlightOpts[$idx]['flightOptionPrices'];
                            } else {
                                $trav['flightOptions'] = [];
                                $trav['flightOptionPrices'] = [];
                            }
                        }
                        unset($trav);
                    }
                } catch (Throwable $e) { /* ignore */ }

                // 현재 selectedOptions에서 기존 룸 정보 조회
                $currentSelectedOptions = [];
                $soRaw = (string)($booking['selectedOptions'] ?? '');
                if ($soRaw !== '') {
                    $tmp = json_decode($soRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                        $currentSelectedOptions = $tmp;
                    }
                }
                $currentRooms = $currentSelectedOptions['selectedRooms'] ?? [];

                // 기존 bookings 컬럼값 조회 (선차감/복원용)
                $bkInfoStmt = $conn->prepare("SELECT adults, children, infants, totalAmount, visaFee, flightOptionFee, adultPrice, childPrice, infantPrice FROM bookings WHERE bookingId = ? LIMIT 1");
                $bkInfoStmt->bind_param('s', $bookingId);
                $bkInfoStmt->execute();
                $bkInfo = $bkInfoStmt->get_result()->fetch_assoc();
                $bkInfoStmt->close();

                // 새 인원 카운트 계산
                $newAdults = 0;
                $newChildren = 0;
                $newInfants = 0;
                foreach ($travelers as $t) {
                    $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                    if ($type === 'adult') $newAdults++;
                    elseif ($type === 'child') $newChildren++;
                    elseif ($type === 'infant') $newInfants++;
                }

                // totalAmount 계산 (approveB2BBooking과 동일한 로직)
                $adultPrice = floatval($bkInfo['adultPrice'] ?? 0);
                $childPriceVal = floatval($bkInfo['childPrice'] ?? 0) ?: max($adultPrice - 5000, 0);
                $infantPriceVal = floatval($bkInfo['infantPrice'] ?? 0) ?: 9000;

                $calculatedNewTotal = 0;
                $calculatedVisaFee = 0;
                $calculatedFlightOptionFee = 0;
                foreach ($travelers as $t) {
                    $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                    if ($type === 'adult') {
                        $calculatedNewTotal += $adultPrice;
                    } elseif ($type === 'child') {
                        $calculatedNewTotal += $childPriceVal;
                    } elseif ($type === 'infant') {
                        $calculatedNewTotal += $infantPriceVal;
                    }
                    $visaType = strtolower(trim($t['visaType'] ?? ''));
                    if ($visaType === 'group') { $calculatedVisaFee += 1500; $calculatedNewTotal += 1500; }
                    elseif ($visaType === 'individual') { $calculatedVisaFee += 1900; $calculatedNewTotal += 1900; }
                    if (!empty($t['flightOptionPrices']) && is_array($t['flightOptionPrices'])) {
                        foreach ($t['flightOptionPrices'] as $price) {
                            $fp = floatval($price);
                            $calculatedFlightOptionFee += $fp;
                            $calculatedNewTotal += $fp;
                        }
                    }
                }
                $pendingRooms = !empty($selectedRooms) ? $selectedRooms : $currentRooms;
                if (is_array($pendingRooms)) {
                    foreach ($pendingRooms as $r) {
                        $count = intval($r['count'] ?? $r['roomCount'] ?? 0);
                        $price = floatval($r['roomPrice'] ?? $r['room_price'] ?? 0);
                        if ($count > 0 && $price > 0) {
                            $calculatedNewTotal += $count * $price;
                        }
                    }
                }

                $isIncrease = ($seatDifference > 0);

                // booking_change_requests 테이블에 travelers + rooms 변경 요청 저장
                $previousDataJson = json_encode([
                    'originalTravelers' => $currentTravelers,
                    'originalRooms' => $currentRooms,
                    'originalAdults' => (int)$bkInfo['adults'],
                    'originalChildren' => (int)$bkInfo['children'],
                    'originalInfants' => (int)$bkInfo['infants'],
                    'originalTotalAmount' => (float)$bkInfo['totalAmount'],
                    'originalVisaFee' => (float)$bkInfo['visaFee'],
                    'originalFlightOptionFee' => (float)$bkInfo['flightOptionFee']
                ], JSON_UNESCAPED_UNICODE);
                $newDataJson = json_encode([
                    'pendingTravelers' => $travelers,
                    'pendingRooms' => $selectedRooms,
                    'newAdults' => $newAdults,
                    'newChildren' => $newChildren,
                    'newInfants' => $newInfants,
                    'newTotalAmount' => $calculatedNewTotal,
                    'newVisaFee' => $calculatedVisaFee,
                    'newFlightOptionFee' => $calculatedFlightOptionFee,
                    'preDeducted' => $isIncrease
                ], JSON_UNESCAPED_UNICODE);

                $trueOriginalStatus = __get_true_original_status($conn, $bookingId, $currentBook);
                $changeRequestSql = "INSERT INTO booking_change_requests (bookingId, changeType, originalStatus, originalPaymentStatus, previousData, newData, requestedBy, requestedByType, status) VALUES (?, 'travelers', ?, ?, ?, ?, ?, 'employee', 'pending')";
                $changeRequestStmt = $conn->prepare($changeRequestSql);
                if ($changeRequestStmt) {
                    $requestedBy = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'admin';
                    $changeRequestStmt->bind_param('ssssss', $bookingId, $trueOriginalStatus, $currentPay, $previousDataJson, $newDataJson, $requestedBy);
                    $changeRequestStmt->execute();
                    $changeRequestStmt->close();
                }

                // bookingStatus 업데이트: 증가 시 선차감, 감소 시 기존 유지
                if ($isIncrease) {
                    // 증가: adults/children/infants/totalAmount/visaFee/flightOptionFee 즉시 반영 (선차감)
                    $pendingStmt = $conn->prepare("UPDATE bookings SET adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, bookingStatus = 'pending_update', updatedAt = NOW() WHERE bookingId = ?");
                    if ($pendingStmt) {
                        $pendingStmt->bind_param('iiiddds', $newAdults, $newChildren, $newInfants, $calculatedNewTotal, $calculatedVisaFee, $calculatedFlightOptionFee, $bookingId);
                        $pendingStmt->execute();
                        $pendingStmt->close();
                    }
                } else {
                    // 감소 또는 동일: 원본 컬럼 유지, 상태만 변경
                    $pendingStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'pending_update', updatedAt = NOW() WHERE bookingId = ?");
                    if ($pendingStmt) {
                        $pendingStmt->bind_param('s', $bookingId);
                        $pendingStmt->execute();
                        $pendingStmt->close();
                    }
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            // 상태 변경 히스토리 기록
            __log_booking_status_change($conn, $bookingId, $currentBook, 'pending_update', null, null, 'Traveler/Room changes submitted for approval');

            // 성공 응답 반환 (실제 traveler/room 수정은 승인 시 실행)
            send_success_response([
                'pendingUpdate' => true,
                'newStatus' => 'pending_update',
                'bookingId' => $bookingId
            ], 'Changes saved for approval');
        } else {
            // pending/pending_update/check_reject 상태: 직접 저장 (기존 로직 사용)
            // travelers 저장
            __ensure_booking_travelers_status_columns($conn);

            // 기존 여행자 중 프론트에서 보내지 않은 여행자 삭제
            $sentTravelerIds = [];
            foreach ($travelers as $tr) {
                if (!is_array($tr)) continue;
                $tid = intval($tr['bookingTravelerId'] ?? 0);
                if ($tid > 0) $sentTravelerIds[] = $tid;
            }

            try {
                if (!empty($sentTravelerIds)) {
                    $placeholders = implode(',', array_fill(0, count($sentTravelerIds), '?'));
                    $delSql = "DELETE FROM booking_travelers WHERE transactNo = ? AND bookingTravelerId NOT IN ($placeholders)";
                    $delStmt = $conn->prepare($delSql);
                    if ($delStmt) {
                        $delTypes = 's' . str_repeat('i', count($sentTravelerIds));
                        $delParams = array_merge([$travelerKey], $sentTravelerIds);
                        mysqli_bind_params_by_ref($delStmt, $delTypes, $delParams);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                } else if (count($travelers) > 0) {
                    $delStmt = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
                    if ($delStmt) {
                        $delStmt->bind_param('s', $travelerKey);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                }
            } catch (Throwable $e) { /* ignore */ }

            // 여행자 UPDATE/INSERT (간소화된 버전)
            foreach ($travelers as $tr) {
                if (!is_array($tr)) continue;
                $tid = intval($tr['bookingTravelerId'] ?? 0);

                if ($tid <= 0) {
                    // 신규 여행자 추가
                    try {
                        $travelerType = strtolower(trim((string)($tr['travelerType'] ?? $tr['type'] ?? 'adult')));
                        $title = trim((string)($tr['title'] ?? ''));
                        $firstName = trim((string)($tr['firstName'] ?? ''));
                        $lastName = trim((string)($tr['lastName'] ?? ''));
                        $birthDate = trim((string)($tr['birthDate'] ?? ''));
                        $gender = strtolower(trim((string)($tr['gender'] ?? '')));
                        $nationality = trim((string)($tr['nationality'] ?? ''));
                        $passportNumber = trim((string)($tr['passportNumber'] ?? ''));
                        $passportIssueDate = trim((string)($tr['passportIssueDate'] ?? ''));
                        $passportExpiry = trim((string)($tr['passportExpiry'] ?? ''));
                        $isMainTraveler = intval($tr['isMainTraveler'] ?? $tr['isMain'] ?? 0);

                        $titleVal = in_array($title, ['MR','MRS','MS','DR']) ? $title : null;
                        $genderVal = in_array($gender, ['male','female']) ? $gender : null;
                        $birthDateVal = ($birthDate !== '' && $birthDate !== '0000-00-00') ? $birthDate : null;
                        $passportIssueDateVal = ($passportIssueDate !== '' && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                        $passportExpiryVal = ($passportExpiry !== '' && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;

                        $insertSql = "INSERT INTO booking_travelers (transactNo, travelerType, title, firstName, lastName, birthDate, gender, nationality, passportNumber, passportIssueDate, passportExpiry, isMainTraveler) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stInsert = $conn->prepare($insertSql);
                        if ($stInsert) {
                            $stInsert->bind_param('sssssssssssi', $travelerKey, $travelerType, $titleVal, $firstName, $lastName, $birthDateVal, $genderVal, $nationality, $passportNumber, $passportIssueDateVal, $passportExpiryVal, $isMainTraveler);
                            $stInsert->execute();
                            $stInsert->close();
                        }
                    } catch (Throwable $e) {
                        error_log("Insert traveler error: " . $e->getMessage());
                    }
                    continue;
                }

                // 기존 여행자 업데이트
                $ok = false;
                try {
                    $chk = $conn->prepare("SELECT bookingTravelerId FROM booking_travelers WHERE bookingTravelerId = ? AND transactNo = ? LIMIT 1");
                    if ($chk) {
                        $chk->bind_param('is', $tid, $travelerKey);
                        $chk->execute();
                        $rs = $chk->get_result();
                        $ok = ($rs && $rs->num_rows > 0);
                        $chk->close();
                    }
                } catch (Throwable $e) { $ok = false; }
                if (!$ok) continue;

                $fields = [];
                $vals = [];
                $types = '';

                // 기본 정보 필드들
                $fieldMap = [
                    'title' => 's', 'firstName' => 's', 'lastName' => 's', 'birthDate' => 's',
                    'gender' => 's', 'nationality' => 's', 'passportNumber' => 's',
                    'passportIssueDate' => 's', 'passportExpiry' => 's', 'visaType' => 's',
                    'visaRequired' => 'i', 'childRoom' => 'i', 'infantSeat' => 'i', 'isMainTraveler' => 'i'
                ];

                // 날짜 필드 목록 (빈 문자열을 NULL로 변환)
                $dateFields = ['birthDate', 'passportIssueDate', 'passportExpiry'];

                // boolean select 필드 ("yes"/"no" → 1/0 변환)
                $boolSelectFields = ['childRoom', 'infantSeat'];

                foreach ($fieldMap as $field => $type) {
                    if (array_key_exists($field, $tr)) {
                        $val = $tr[$field];
                        if (in_array($field, $boolSelectFields)) {
                            // "yes"/"no", "Yes"/"No", true/false, 1/0 모두 처리
                            $val = ($val === 'yes' || $val === 'Yes' || $val === '1' || $val === 1 || $val === true) ? 1 : 0;
                        } elseif ($type === 'i') {
                            $val = intval($val);
                        } elseif (in_array($field, $dateFields)) {
                            // 날짜 필드: 빈 문자열이나 0000-00-00은 NULL로 처리
                            $val = trim((string)$val);
                            $val = ($val !== '' && $val !== '0000-00-00') ? $val : null;
                        }
                        $fields[] = "$field = ?";
                        $vals[] = $val;
                        $types .= $type;
                    }
                }

                if (!empty($fields)) {
                    $vals[] = $tid;
                    $types .= 'i';
                    $upSql = "UPDATE booking_travelers SET " . implode(', ', $fields) . " WHERE bookingTravelerId = ?";
                    $upStmt = $conn->prepare($upSql);
                    if ($upStmt) {
                        mysqli_bind_params_by_ref($upStmt, $types, $vals);
                        $upStmt->execute();
                        $upStmt->close();
                    }
                }
            }

            // selectedRooms 저장 (selectedOptions 업데이트)
            try {
                $soRaw = '';
                $stRead = $conn->prepare("SELECT selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
                if ($stRead) {
                    $stRead->bind_param('s', $bookingId);
                    $stRead->execute();
                    $readRes = $stRead->get_result();
                    if ($readRes && $readRow = $readRes->fetch_assoc()) {
                        $soRaw = (string)($readRow['selectedOptions'] ?? '');
                    }
                    $stRead->close();
                }
                $soObj = [];
                if ($soRaw !== '') {
                    $tmp = json_decode($soRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $soObj = $tmp;
                }
                $soObj['selectedRooms'] = $selectedRooms;

                $newRaw = json_encode($soObj, JSON_UNESCAPED_UNICODE);
                $st = $conn->prepare("UPDATE bookings SET selectedOptions = ? WHERE bookingId = ?");
                if ($st) {
                    $st->bind_param('ss', $newRaw, $bookingId);
                    $st->execute();
                    $st->close();
                }
            } catch (Throwable $e) { /* ignore */ }

            // infantsWithSeat 재계산: booking_travelers에서 직접 카운트
            try {
                $iwsStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM booking_travelers WHERE transactNo = ? AND LOWER(travelerType) = 'infant' AND infantSeat = 1");
                if ($iwsStmt) {
                    $iwsStmt->bind_param('s', $bookingId);
                    $iwsStmt->execute();
                    $iwsRow = $iwsStmt->get_result()->fetch_assoc();
                    $iwsStmt->close();
                    $iwsCount = (int)($iwsRow['cnt'] ?? 0);
                    $updIws = $conn->prepare("UPDATE bookings SET infantsWithSeat = ? WHERE bookingId = ?");
                    if ($updIws) {
                        $updIws->bind_param('is', $iwsCount, $bookingId);
                        $updIws->execute();
                        $updIws->close();
                    }
                }
            } catch (Throwable $e) { /* ignore */ }

            send_success_response([
                'saved' => true,
                'bookingId' => $bookingId
            ], 'Changes saved successfully');
        }
    } catch (Exception $e) {
        send_error_response('Update failed: ' . $e->getMessage());
    }
}

// SMT 수정 시작 - 선금 증빙 파일 업로드 함수
function uploadDepositProof($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 파일 확인
        if (!isset($_FILES['depositProof']) || $_FILES['depositProof']['error'] !== UPLOAD_ERR_OK) {
            send_error_response('No file uploaded or upload error');
        }

        $file = $_FILES['depositProof'];

        // 파일 타입 검증 (이미지 및 PDF 허용)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            send_error_response('Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.');
        }

        // 파일 크기 제한 (10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            send_error_response('File size exceeds 10MB limit');
        }

        // 업로드 디렉토리 생성
        $uploadDir = __DIR__ . '/../../../uploads/deposits/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 파일명 생성
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $extension = $extension ? '.' . $extension : '';
        $fileName = 'deposit_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
        $uploadPath = $uploadDir . $fileName;

        // 파일 이동
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            send_error_response('Failed to save uploaded file');
        }

        $relativePath = 'uploads/deposits/' . $fileName;
        $originalFileName = $file['name']; // 원본 파일명 저장
        $now = date('Y-m-d H:i:s');

        // Update new booking_payments table
        upsertPayment($conn, $bookingId, 'down', [
            'filePath' => $relativePath,
            'fileName' => $originalFileName,
            'uploadedAt' => $now,
            'status' => 'uploaded'
        ]);

        // DB 업데이트 (원본 파일명도 저장) - 통일: downPaymentFile 사용 - Legacy columns
        $stmt = $conn->prepare("UPDATE bookings SET downPaymentFile = ?, downPaymentFileName = ?, downPaymentUploadedAt = ? WHERE bookingId = ?");
        if (!$stmt) {
            // 업로드된 파일 삭제
            @unlink($uploadPath);
            send_error_response('Database error');
        }
        $stmt->bind_param('ssss', $relativePath, $originalFileName, $now, $bookingId);
        if (!$stmt->execute()) {
            @unlink($uploadPath);
            send_error_response('Failed to update booking');
        }
        $stmt->close();

        send_success_response([
            'filePath' => $relativePath,
            'fileName' => $fileName,
            'originalFileName' => $originalFileName
        ], 'Deposit proof uploaded successfully');
    } catch (Exception $e) {
        send_error_response('Upload failed: ' . $e->getMessage());
    }
}
// SMT 수정 완료

// SMT 수정 시작 - 결제 확정/거절 함수
function confirmPayment($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        $paymentType = $input['paymentType'] ?? null;

        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        if (empty($paymentType) || !in_array($paymentType, ['down', 'second', 'balance', 'full'])) {
            send_error_response('Valid payment type is required (down, second, balance, full)');
        }

        // Get admin account ID for confirmedBy
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;

        // Check from new booking_payments table first
        $payment = getPaymentByStep($conn, $bookingId, $paymentType);

        // Fallback to legacy columns if no record in new table
        if (!$payment) {
            $checkSql = "SELECT downPaymentFile, downPaymentConfirmedAt, advancePaymentFile, advancePaymentConfirmedAt, balanceFile, balanceConfirmedAt, fullPaymentFile, fullPaymentConfirmedAt FROM bookings WHERE bookingId = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $booking = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$booking) {
                send_error_response('Booking not found');
            }
        } else {
            // Check file exists in new table
            if (empty($payment['filePath'])) {
                send_error_response('No ' . ucfirst($paymentType) . ' Payment proof file uploaded');
            }
            // Build compatible booking array from payment
            $booking = [
                'downPaymentFile' => $paymentType === 'down' ? $payment['filePath'] : null,
                'advancePaymentFile' => $paymentType === 'second' ? $payment['filePath'] : null,
                'balanceFile' => $paymentType === 'balance' ? $payment['filePath'] : null,
                'fullPaymentFile' => $paymentType === 'full' ? $payment['filePath'] : null
            ];
            // Get confirmation status from related payments
            $downPayment = getPaymentByStep($conn, $bookingId, 'down');
            $secondPayment = getPaymentByStep($conn, $bookingId, 'second');
            $booking['downPaymentConfirmedAt'] = $downPayment ? $downPayment['confirmedAt'] : null;
            $booking['advancePaymentConfirmedAt'] = $secondPayment ? $secondPayment['confirmedAt'] : null;
        }

        $now = date('Y-m-d H:i:s');

        // 예약의 결제 타입 조회 (middle 결제 구분용)
        $ptStmt = $conn->prepare("SELECT paymentType FROM bookings WHERE bookingId = ? LIMIT 1");
        $ptStmt->bind_param('s', $bookingId);
        $ptStmt->execute();
        $ptRow = $ptStmt->get_result()->fetch_assoc();
        $ptStmt->close();
        $bookingPaymentType = $ptRow['paymentType'] ?? '';

        // 결제 타입별 검증 및 업데이트 (상태 자동 전환 포함)
        switch ($paymentType) {
            case 'down':
                if (empty($booking['downPaymentFile'])) {
                    send_error_response('No Down Payment proof file uploaded');
                }
                // Down Payment 승인: middle 결제면 waiting_balance로, staged면 waiting_second_payment로 전환
                if ($bookingPaymentType === 'middle') {
                    $sql = "UPDATE bookings SET downPaymentConfirmedAt = ?, bookingStatus = 'waiting_balance' WHERE bookingId = ?";
                } else {
                    $sql = "UPDATE bookings SET downPaymentConfirmedAt = ?, bookingStatus = 'waiting_second_payment' WHERE bookingId = ?";
                }
                break;

            case 'second':
                // Down Payment 확정 여부 확인
                if (empty($booking['downPaymentConfirmedAt']) || $booking['downPaymentConfirmedAt'] === '0000-00-00 00:00:00') {
                    send_error_response('Down Payment must be confirmed first');
                }
                if (empty($booking['advancePaymentFile'])) {
                    send_error_response('No Second Payment proof file uploaded');
                }
                // Second Payment 승인 → waiting_balance로 자동 전환
                $sql = "UPDATE bookings SET advancePaymentConfirmedAt = ?, bookingStatus = 'waiting_balance' WHERE bookingId = ?";
                break;

            case 'balance':
                // middle 결제: down(middle) 확인 후 balance 가능 / staged 결제: second 확인 후 balance 가능
                if ($bookingPaymentType === 'middle') {
                    if (empty($booking['downPaymentConfirmedAt']) || $booking['downPaymentConfirmedAt'] === '0000-00-00 00:00:00') {
                        send_error_response('Middle Payment must be confirmed first');
                    }
                } else {
                    if (empty($booking['advancePaymentConfirmedAt']) || $booking['advancePaymentConfirmedAt'] === '0000-00-00 00:00:00') {
                        send_error_response('Second Payment must be confirmed first');
                    }
                }
                if (empty($booking['balanceFile'])) {
                    send_error_response('No Balance proof file uploaded');
                }
                // Balance 승인 → confirmed로 전환, paymentStatus = 'paid'
                $sql = "UPDATE bookings SET balanceConfirmedAt = ?, paymentStatus = 'paid', bookingStatus = 'confirmed' WHERE bookingId = ?";
                break;

            case 'full':
                if (empty($booking['fullPaymentFile'])) {
                    send_error_response('No Full Payment proof file uploaded');
                }
                // Full Payment 승인 → confirmed로 전환, paymentStatus = 'paid'
                $sql = "UPDATE bookings SET fullPaymentConfirmedAt = ?, paymentStatus = 'paid', bookingStatus = 'confirmed' WHERE bookingId = ?";
                break;
        }

        // Update new booking_payments table
        updatePaymentStatus($conn, $bookingId, $paymentType, 'confirmed', [
            'confirmedAt' => $now,
            'confirmedBy' => $adminAccountId
        ]);

        // Update legacy columns
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $now, $bookingId);

        if (!$stmt->execute()) {
            send_error_response('Failed to confirm payment');
        }
        $stmt->close();

        // Send next payment step notification email
        $nextStepMap = [
            'down' => ($bookingPaymentType === 'middle') ? null : 'second',
            'second' => 'balance',
            'balance' => null,
            'full' => null
        ];
        $nextStep = $nextStepMap[$paymentType] ?? null;
        if ($nextStep) {
            try {
                if (function_exists('send_payment_step_change_email')) {
                    send_payment_step_change_email($conn, $bookingId, $paymentType, $nextStep);
                }
            } catch (Throwable $emailEx) {
                error_log("Failed to send payment step change email for {$bookingId}: " . $emailEx->getMessage());
            }
        }

        send_success_response(['confirmedAt' => $now], 'Payment confirmed successfully');
    } catch (Exception $e) {
        send_error_response('Failed to confirm payment: ' . $e->getMessage());
    }
}

function rejectPayment($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        $paymentType = $input['paymentType'] ?? null;
        $reason = $input['reason'] ?? '';

        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        if (empty($paymentType) || !in_array($paymentType, ['down', 'second', 'balance', 'full'])) {
            send_error_response('Valid payment type is required (down, second, balance, full)');
        }

        $now = date('Y-m-d H:i:s');

        // Check from new booking_payments table first
        $payment = getPaymentByStep($conn, $bookingId, $paymentType);
        $filePath = null;

        if ($payment && !empty($payment['filePath'])) {
            $filePath = $payment['filePath'];
        } else {
            // Fallback to legacy columns
            $checkSql = "SELECT downPaymentFile, advancePaymentFile, balanceFile, fullPaymentFile FROM bookings WHERE bookingId = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $booking = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$booking) {
                send_error_response('Booking not found');
            }

            // Get file path from legacy columns
            switch ($paymentType) {
                case 'down':
                    $filePath = $booking['downPaymentFile'];
                    break;
                case 'second':
                    $filePath = $booking['advancePaymentFile'];
                    break;
                case 'balance':
                    $filePath = $booking['balanceFile'];
                    break;
                case 'full':
                    $filePath = $booking['fullPaymentFile'];
                    break;
            }
        }

        // 결제 타입별 DB 업데이트 (이전 waiting 상태로 복원 - 재고 유지)
        switch ($paymentType) {
            case 'down':
                $sql = "UPDATE bookings SET downPaymentFile = NULL, downPaymentFileName = NULL, downPaymentUploadedAt = NULL, downPaymentRejectedAt = NOW(), downPaymentRejectionReason = ?, bookingStatus = 'waiting_down_payment' WHERE bookingId = ?";
                break;

            case 'second':
                $sql = "UPDATE bookings SET advancePaymentFile = NULL, advancePaymentFileName = NULL, advancePaymentUploadedAt = NULL, advancePaymentRejectedAt = NOW(), advancePaymentRejectionReason = ?, bookingStatus = 'waiting_second_payment' WHERE bookingId = ?";
                break;

            case 'balance':
                $sql = "UPDATE bookings SET balanceFile = NULL, balanceFileName = NULL, balanceUploadedAt = NULL, balanceRejectedAt = NOW(), balanceRejectionReason = ?, bookingStatus = 'waiting_balance' WHERE bookingId = ?";
                break;

            case 'full':
                $sql = "UPDATE bookings SET fullPaymentFile = NULL, fullPaymentFileName = NULL, fullPaymentUploadedAt = NULL, fullPaymentRejectedAt = NOW(), fullPaymentRejectionReason = ?, bookingStatus = 'waiting_full_payment' WHERE bookingId = ?";
                break;
        }

        // 파일 삭제 (존재하는 경우)
        if (!empty($filePath)) {
            $fullPath = __DIR__ . '/../../../' . $filePath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        // Update new booking_payments table
        updatePaymentStatus($conn, $bookingId, $paymentType, 'rejected', [
            'rejectedAt' => $now,
            'rejectionReason' => $reason,
            'filePath' => null,
            'fileName' => null,
            'uploadedAt' => null
        ]);

        // Update legacy columns
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $reason, $bookingId);

        if (!$stmt->execute()) {
            send_error_response('Failed to reject payment');
        }
        $stmt->close();

        // Send rejection notification email
        if (function_exists('send_rejection_notification_email')) {
            $paymentTypeMap = [
                'down' => 'down_payment',
                'second' => 'advance_payment',
                'balance' => 'balance',
                'full' => 'full_payment',
            ];
            $mappedPaymentType = $paymentTypeMap[$paymentType] ?? $paymentType;
            send_rejection_notification_email($conn, $bookingId, 'payment', $reason, $mappedPaymentType);
        }

        send_success_response([], 'Payment rejected successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reject payment: ' . $e->getMessage());
    }
}
// SMT 수정 완료

function cancelB2BBooking($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        $sql = "UPDATE bookings SET bookingStatus = 'cancelled' WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();

        // Google Sheets APP 동기화
        try {
            require_once __DIR__ . '/../../../backend/lib/google_sheets.php';
            gs_sync_by_booking_id($conn, $bookingId);
        } catch (Exception $gsEx) {
            error_log("Sheets sync failed (cancelB2B): " . $gsEx->getMessage());
        }

        send_success_response([], 'Booking cancelled successfully');
    } catch (Exception $e) {
        send_error_response('Failed to cancel B2B booking: ' . $e->getMessage());
    }
}

function approveB2BBooking($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // pending/pending_update 상태인지 확인 및 paymentType 조회
        $checkSql = "SELECT bookingStatus, paymentType, departureDate, totalAmount FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $booking = $result->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found');
        }

        $adminUserType = $_SESSION['admin_userType'] ?? '';

        if ($booking['bookingStatus'] === 'pending') {
            // 신규 예약 승인: admin_kr, admin_ph 가능
            if (!in_array($adminUserType, ['admin_kr', 'admin_ph'], true)) {
                send_error_response('Only admin_kr or admin_ph can approve new bookings', 403);
            }

            // === Deadline 재계산 (승인일 기준) ===
            require_once __DIR__ . '/../../../backend/lib/deadline_calculator.php';
            require_once __DIR__ . '/../../../backend/lib/booking_payments.php';

            $approvalDate = date('Y-m-d H:i:s');
            $departureDate = $booking['departureDate'] ?? null;
            $currentPaymentType = $booking['paymentType'] ?? 'staged';
            $totalAmount = (float)($booking['totalAmount'] ?? 0);

            // 출발일까지 남은 일수 재계산
            $daysUntilDeparture = null;
            if (!empty($departureDate)) {
                $depDT = new DateTime($departureDate);
                $todayDT = new DateTime();
                $todayDT->setTime(0, 0, 0);
                $depDT->setTime(0, 0, 0);
                $daysUntilDeparture = (int)$todayDT->diff($depDT)->format('%r%a');
            }

            // Deadline 계산
            $deadlines = calculateBookingDeadlines($currentPaymentType, $daysUntilDeparture, $departureDate, $approvalDate);
            $finalPaymentType = $deadlines['paymentType'];

            // paymentType에 따라 다른 상태로 변경
            $newStatus = getInitialPaymentStatus($finalPaymentType);

            // bookings 테이블 UPDATE: deadlines + approvedAt + paymentType
            $updateFields = "bookingStatus = ?, paymentType = ?, approvedAt = ?, updatedAt = NOW()";
            $updateTypes = 'sss';
            $updateValues = [$newStatus, $finalPaymentType, $approvalDate];

            // Set deadline columns
            if ($deadlines['fullPaymentDueDate'] !== null) {
                $updateFields .= ", fullPaymentDueDate = ?";
                $updateTypes .= 's';
                $updateValues[] = $deadlines['fullPaymentDueDate'];
                // fullPaymentAmount
                $updateFields .= ", fullPaymentAmount = ?";
                $updateTypes .= 'd';
                $updateValues[] = $totalAmount;
            } else {
                $updateFields .= ", fullPaymentDueDate = NULL, fullPaymentAmount = NULL";
            }

            if ($finalPaymentType === 'middle') {
                // Middle: downPaymentDueDate stores middle deadline, advancePaymentDueDate unused
                $updateFields .= ", downPaymentDueDate = ?";
                $updateTypes .= 's';
                $updateValues[] = $deadlines['secondPaymentDueDate']; // middle deadline stored in downPaymentDueDate
                $updateFields .= ", advancePaymentDueDate = NULL";
            } else if ($deadlines['downPaymentDueDate'] !== null) {
                $updateFields .= ", downPaymentDueDate = ?";
                $updateTypes .= 's';
                $updateValues[] = $deadlines['downPaymentDueDate'];
            } else if ($finalPaymentType === 'full') {
                $updateFields .= ", downPaymentDueDate = NULL";
            }

            if ($finalPaymentType !== 'middle') {
                if ($deadlines['secondPaymentDueDate'] !== null) {
                    $updateFields .= ", advancePaymentDueDate = ?";
                    $updateTypes .= 's';
                    $updateValues[] = $deadlines['secondPaymentDueDate'];
                } else if ($finalPaymentType === 'full') {
                    $updateFields .= ", advancePaymentDueDate = NULL";
                }
            }

            if ($deadlines['balanceDueDate'] !== null) {
                $updateFields .= ", balanceDueDate = ?";
                $updateTypes .= 's';
                $updateValues[] = $deadlines['balanceDueDate'];
            } else if ($finalPaymentType === 'full') {
                $updateFields .= ", balanceDueDate = NULL";
            }

            $updateValues[] = $bookingId;
            $updateTypes .= 's';

            $sql = "UPDATE bookings SET {$updateFields} WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($updateTypes, ...$updateValues);
            $stmt->execute();
            $stmt->close();

            // booking_payments 테이블 dueDate 갱신
            $payments = getPaymentsByBookingId($conn, $bookingId);
            if (!empty($payments)) {
                $stepDeadlineMap = [];
                if ($finalPaymentType === 'staged') {
                    $stepDeadlineMap['down'] = $deadlines['downPaymentDueDate'];
                    $stepDeadlineMap['second'] = $deadlines['secondPaymentDueDate'];
                    $stepDeadlineMap['balance'] = $deadlines['balanceDueDate'];
                } elseif ($finalPaymentType === 'middle') {
                    $stepDeadlineMap['middle'] = $deadlines['secondPaymentDueDate'];
                    $stepDeadlineMap['middle_balance'] = $deadlines['balanceDueDate'];
                } elseif ($finalPaymentType === 'full') {
                    $stepDeadlineMap['full'] = $deadlines['fullPaymentDueDate'];
                }

                foreach ($stepDeadlineMap as $step => $dueDate) {
                    if ($dueDate !== null) {
                        upsertPayment($conn, $bookingId, $step, ['dueDate' => $dueDate]);
                    }
                }
            }

            // Google Sheets APP 동기화
            try {
                require_once __DIR__ . '/../../../backend/lib/google_sheets.php';
                gs_sync_by_booking_id($conn, $bookingId);
            } catch (Exception $gsEx) {
                error_log("Sheets sync failed (approveB2B): " . $gsEx->getMessage());
            }

            // 상태 변경 히스토리 저장
            $approvalReason = "New booking approved (paymentType: {$finalPaymentType}, daysUntilDeparture: {$daysUntilDeparture})";
            __log_booking_status_change($conn, $bookingId, 'pending', $newStatus, null, null, $approvalReason);

            // Send booking confirmation email to agent upon approval
            try {
                $emailResult = send_booking_confirmation_email($conn, $bookingId);
                if (!$emailResult['success']) {
                    error_log("Failed to send booking confirmation email for {$bookingId}: " . ($emailResult['message'] ?? 'Unknown error'));
                }
            } catch (Throwable $emailEx) {
                error_log("Exception sending booking confirmation email for {$bookingId}: " . $emailEx->getMessage());
            }

            send_success_response([], 'Booking approved successfully');
        } else if ($booking['bookingStatus'] === 'pending_update') {
            // booking_change_requests 테이블에서 변경 요청 조회
            $changeReqSql = "SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' ORDER BY requestedAt DESC LIMIT 1";
            $changeReqStmt = $conn->prepare($changeReqSql);
            $changeReqStmt->bind_param('s', $bookingId);
            $changeReqStmt->execute();
            $changeReqResult = $changeReqStmt->get_result();
            $changeRequest = $changeReqResult->fetch_assoc();
            $changeReqStmt->close();

            if (!$changeRequest) {
                send_error_response('No pending change request found for this booking');
            }

            $processedBy = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'admin';

            // status, deadline changeType은 admin_kr만 승인 가능
            if ($changeRequest['changeType'] === 'deadline') {
                if ($adminUserType !== 'admin_kr') {
                    send_error_response('Only admin_kr can approve this change type', 403);
                }
            }

            if ($changeRequest['changeType'] === 'status') {
                // 상태 변경 요청 승인: targetStatus로 변경
                $newStatus = $changeRequest['targetStatus'];
                $newPaymentStatus = $changeRequest['targetPaymentStatus'];

                if ($newPaymentStatus !== null) {
                    $sql = "UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sss', $newStatus, $newPaymentStatus, $bookingId);
                } else {
                    $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $newStatus, $bookingId);
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 승인 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'approved', processedBy = ?, processedAt = NOW() WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('si', $processedBy, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // 같은 타입의 오래된 pending 요청 자동 reject
                __reject_superseded_change_requests($conn, $bookingId, $changeRequest['id'], $changeRequest['changeType'], $processedBy);

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $newStatus, null, null, 'Status change request approved');

                send_success_response([], 'Status change approved successfully');

            } else if ($changeRequest['changeType'] === 'travelers' || $changeRequest['changeType'] === 'travelers_add_remove') {
                // travelers_add_remove인 경우: admin_kr만 승인 가능
                if ($changeRequest['changeType'] === 'travelers_add_remove') {
                    $adminUserType = $_SESSION['admin_userType'] ?? '';
                    if ($adminUserType !== 'admin_kr') {
                        send_error_response('Only admin_kr can approve traveler add/remove changes', 403);
                    }
                }
                // Traveler/Customer Info 변경 요청 승인: pendingTravelers + pendingCustomerInfo 적용
                $newData = json_decode($changeRequest['newData'], true);
                $previousData = json_decode($changeRequest['previousData'], true);
                $pendingTravelers = $newData['pendingTravelers'] ?? [];
                // Agent API는 selectedRooms로, Super Admin API는 pendingRooms로 저장함
                $pendingRooms = $newData['pendingRooms'] ?? $newData['selectedRooms'] ?? [];
                $pendingCustomerInfo = $newData['pendingCustomerInfo'] ?? null;
                $originalTravelers = $previousData['originalTravelers'] ?? [];
                $originalRooms = $previousData['originalRooms'] ?? [];
                $newStatus = __resolve_post_approval_status($conn, $bookingId, $changeRequest['id'], $changeRequest['originalStatus'] ?? 'confirmed');

                // Customer Info 적용 (있는 경우)
                if ($pendingCustomerInfo && is_array($pendingCustomerInfo)) {
                    $ciName = trim((string)($pendingCustomerInfo['name'] ?? ''));
                    $ciEmail = trim((string)($pendingCustomerInfo['email'] ?? ''));
                    $ciPhone = trim((string)($pendingCustomerInfo['phone'] ?? ''));

                    // bookings 테이블 업데이트 (contactName, contactEmail, contactPhone)
                    $ciUpdates = [];
                    $ciValues = [];
                    $ciTypes = '';

                    if (__table_has_column($conn, 'bookings', 'contactEmail')) {
                        $ciUpdates[] = "contactEmail = ?";
                        $ciValues[] = ($ciEmail !== '' ? $ciEmail : null);
                        $ciTypes .= 's';
                    }
                    if (__table_has_column($conn, 'bookings', 'contactPhone')) {
                        $ciUpdates[] = "contactPhone = ?";
                        $ciValues[] = ($ciPhone !== '' ? $ciPhone : null);
                        $ciTypes .= 's';
                    }
                    if (__table_has_column($conn, 'bookings', 'contactName')) {
                        $ciUpdates[] = "contactName = ?";
                        $ciValues[] = ($ciName !== '' ? $ciName : null);
                        $ciTypes .= 's';
                    }

                    if (!empty($ciUpdates)) {
                        $ciValues[] = $bookingId;
                        $ciTypes .= 's';
                        $ciSt = $conn->prepare("UPDATE bookings SET " . implode(', ', $ciUpdates) . " WHERE bookingId = ?");
                        if ($ciSt) {
                            mysqli_bind_params_by_ref($ciSt, $ciTypes, $ciValues);
                            $ciSt->execute();
                            $ciSt->close();
                        }
                    }

                    // selectedOptions.customerInfo 업데이트
                    try {
                        $soRaw = '';
                        $stRead = $conn->prepare("SELECT selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
                        if ($stRead) {
                            $stRead->bind_param('s', $bookingId);
                            $stRead->execute();
                            $readRes = $stRead->get_result();
                            if ($readRes && $readRow = $readRes->fetch_assoc()) {
                                $soRaw = (string)($readRow['selectedOptions'] ?? '');
                            }
                            $stRead->close();
                        }
                        $soObj = null;
                        if ($soRaw !== '') {
                            $tmp = json_decode($soRaw, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $soObj = $tmp;
                        }
                        if (!is_array($soObj)) $soObj = [];
                        $soObj['customerInfo'] = [
                            'name' => $ciName,
                            'email' => $ciEmail,
                            'phone' => $ciPhone
                        ];
                        $newRaw = json_encode($soObj, JSON_UNESCAPED_UNICODE);
                        if (__table_has_column($conn, 'bookings', 'selectedOptions')) {
                            $st = $conn->prepare("UPDATE bookings SET selectedOptions = ? WHERE bookingId = ?");
                            if ($st) {
                                $st->bind_param('ss', $newRaw, $bookingId);
                                $st->execute();
                                $st->close();
                            }
                        }
                    } catch (Throwable $e) { /* ignore */ }
                }

                // pendingTravelers가 비어있으면 Traveler 처리 스킵 (Customer Info만 변경된 경우)
                if (empty($pendingTravelers)) {
                    // bookingStatus를 원래 상태로 복원
                    $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $newStatus, $bookingId);
                    $stmt->execute();
                    $stmt->close();

                    // 변경 요청 승인 처리
                    $updateReqSql = "UPDATE booking_change_requests SET status = 'approved', processedBy = ?, processedAt = NOW() WHERE id = ?";
                    $updateReqStmt = $conn->prepare($updateReqSql);
                    $updateReqStmt->bind_param('si', $processedBy, $changeRequest['id']);
                    $updateReqStmt->execute();
                    $updateReqStmt->close();

                    // 같은 타입의 오래된 pending 요청 자동 reject
                    __reject_superseded_change_requests($conn, $bookingId, $changeRequest['id'], $changeRequest['changeType'], $processedBy);

                    // 상태 변경 히스토리 저장
                    __log_booking_status_change($conn, $bookingId, 'pending_update', $newStatus, null, null, 'Customer info change approved');

                    send_success_response([], 'Customer info change approved successfully');
                }

                // 금액 재계산을 위한 가격 정보 조회
                $priceStmt = $conn->prepare("SELECT totalAmount, adultPrice, childPrice, infantPrice FROM bookings WHERE bookingId = ? LIMIT 1");
                $priceStmt->bind_param('s', $bookingId);
                $priceStmt->execute();
                $priceResult = $priceStmt->get_result();
                $priceData = $priceResult->fetch_assoc();
                $priceStmt->close();

                $oldTotalAmount = floatval($priceData['totalAmount'] ?? 0);
                $adultPrice = floatval($priceData['adultPrice'] ?? 0);
                $childPriceVal = floatval($priceData['childPrice'] ?? 0) ?: max($adultPrice - 5000, 0);
                $infantPrice = floatval($priceData['infantPrice'] ?? 0) ?: 9000;

                // 새로운 총액 계산 함수
                $calculateTotal = function($travelers, $rooms) use ($adultPrice, $childPriceVal, $infantPrice) {
                    $total = 0;
                    // Traveler 요금
                    foreach ($travelers as $t) {
                        $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                        if ($type === 'adult') {
                            $total += $adultPrice;
                        } elseif ($type === 'child') {
                            $total += $childPriceVal;
                        } elseif ($type === 'infant') {
                            $total += $infantPrice;
                        }
                        // Visa 요금
                        $visaType = strtolower(trim($t['visaType'] ?? ''));
                        if ($visaType === 'group') $total += 1500;
                        elseif ($visaType === 'individual') $total += 1900;
                        // Flight Options
                        if (!empty($t['flightOptionPrices']) && is_array($t['flightOptionPrices'])) {
                            foreach ($t['flightOptionPrices'] as $price) {
                                $total += floatval($price);
                            }
                        }
                    }
                    // Room 요금
                    foreach ($rooms as $r) {
                        $count = intval($r['count'] ?? $r['roomCount'] ?? 0);
                        $price = floatval($r['roomPrice'] ?? $r['room_price'] ?? 0);
                        if ($count > 0 && $price > 0) {
                            $total += $count * $price;
                        }
                    }
                    return $total;
                };

                // Before/After 금액 계산
                $beforeTotal = $calculateTotal($originalTravelers, $originalRooms);
                $afterTotal = $calculateTotal($pendingTravelers, $pendingRooms);
                $priceAdjustment = $afterTotal - $beforeTotal;

                // Visa Fee와 Flight Option Fee 별도 계산 (pendingTravelers 기준)
                $newVisaFee = 0;
                $newFlightOptionFee = 0;
                foreach ($pendingTravelers as $t) {
                    $visaType = strtolower(trim($t['visaType'] ?? ''));
                    if ($visaType === 'group') $newVisaFee += 1500;
                    elseif ($visaType === 'individual') $newVisaFee += 1900;

                    if (!empty($t['flightOptionPrices']) && is_array($t['flightOptionPrices'])) {
                        foreach ($t['flightOptionPrices'] as $price) {
                            $newFlightOptionFee += floatval($price);
                        }
                    }
                }

                // transactNo 조회
                $travelerKey = $bookingId;
                try {
                    $tkStmt = $conn->prepare("SELECT COALESCE(NULLIF(transactNo,''), bookingId) as tk FROM bookings WHERE bookingId = ? LIMIT 1");
                    if ($tkStmt) {
                        $tkStmt->bind_param('s', $bookingId);
                        $tkStmt->execute();
                        $tkRes = $tkStmt->get_result();
                        if ($tkRes && $tkRow = $tkRes->fetch_assoc()) {
                            $travelerKey = $tkRow['tk'];
                        }
                        $tkStmt->close();
                    }
                } catch (Throwable $e) { /* ignore */ }

                // 기존 여행자 삭제 후 새 여행자 데이터 삽입
                try {
                    // 기존 여행자 삭제
                    $delStmt = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
                    if ($delStmt) {
                        $delStmt->bind_param('s', $travelerKey);
                        $delStmt->execute();
                        $delStmt->close();
                    }

                    // 기존 flight options 삭제
                    $delOptStmt = $conn->prepare("DELETE FROM booking_traveler_options WHERE booking_id = ?");
                    if ($delOptStmt) {
                        $delOptStmt->bind_param('s', $bookingId);
                        $delOptStmt->execute();
                        $delOptStmt->close();
                    }

                    // 새 여행자 삽입
                    $travelerIdx = 0;
                    foreach ($pendingTravelers as $tr) {
                        if (!is_array($tr)) continue;

                        $travelerType = strtolower(trim((string)($tr['travelerType'] ?? $tr['type'] ?? 'adult')));
                        $title = trim((string)($tr['title'] ?? ''));
                        $firstName = trim((string)($tr['firstName'] ?? ''));
                        $lastName = trim((string)($tr['lastName'] ?? ''));
                        $birthDate = trim((string)($tr['birthDate'] ?? ''));
                        $gender = strtolower(trim((string)($tr['gender'] ?? '')));
                        $nationality = trim((string)($tr['nationality'] ?? ''));
                        $passportNumber = trim((string)($tr['passportNumber'] ?? ''));
                        $passportIssueDate = trim((string)($tr['passportIssueDate'] ?? ''));
                        $passportExpiry = trim((string)($tr['passportExpiry'] ?? ''));
                        $isMainTraveler = intval($tr['isMainTraveler'] ?? $tr['isMain'] ?? $tr['isPrimary'] ?? 0);
                        $profileSource = trim((string)($tr['profile_source'] ?? $tr['profileSource'] ?? ''));
                        $visaType = strtolower(trim((string)($tr['visaType'] ?? '')));
                        $passportImage = trim((string)($tr['passportImage'] ?? $tr['passportPhoto'] ?? ''));
                        $childRoom = $tr['childRoom'];
                        $infantSeatRaw = $tr['infantSeat'] ?? 0;

                        // ENUM 필드 처리
                        $titleVal = in_array($title, ['MR','MRS','MS','DR']) ? $title : null;
                        $genderVal = in_array($gender, ['male','female']) ? $gender : null;
                        $birthDateVal = ($birthDate !== '' && $birthDate !== '0000-00-00') ? $birthDate : null;
                        $passportIssueDateVal = ($passportIssueDate !== '' && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                        $passportExpiryVal = ($passportExpiry !== '' && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;
                        $profileSourceVal = ($profileSource !== '') ? $profileSource : null;
                        $visaTypeVal = in_array($visaType, ['with_visa', 'group', 'individual', 'foreign']) ? $visaType : null;
                        // passportImage: base64면 파일로 저장, URL 경로면 그대로 유지
                        $passportImageVal = null;
                        if ($passportImage !== '' && str_starts_with($passportImage, 'data:image')) {
                            // Base64 이미지 디코딩 후 파일 저장
                            $ppUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/passports/';
                            if (!is_dir($ppUploadDir)) { @mkdir($ppUploadDir, 0755, true); }
                            $ppMatches = [];
                            if (preg_match('/^data:image\/(\w+);base64,/', $passportImage, $ppMatches)) {
                                $ppExt = $ppMatches[1];
                                $ppBase64 = substr($passportImage, strpos($passportImage, ',') + 1);
                                $ppData = base64_decode($ppBase64);
                                if ($ppData !== false) {
                                    $ppFilename = $bookingId . '_' . $travelerIdx . '_' . time() . '.' . $ppExt;
                                    if (file_put_contents($ppUploadDir . $ppFilename, $ppData)) {
                                        $passportImageVal = '/uploads/passports/' . $ppFilename;
                                    }
                                }
                            }
                        } elseif ($passportImage !== '' && (strpos($passportImage, '/uploads/') !== false || strpos($passportImage, 'uploads/') === 0)) {
                            $passportImageVal = $passportImage;
                        }
                        // childRoom: 'yes', '1', true 등을 1로 변환
                        $childRoomVal = ($childRoom === 'yes' || $childRoom === 'Yes' || $childRoom === '1' || $childRoom === 1 || $childRoom === true) ? 1 : 0;

                        // 동적으로 INSERT 쿼리 구성
                        $columns = ['transactNo', 'travelerType', 'title', 'firstName', 'lastName', 'birthDate', 'gender', 'nationality', 'passportNumber', 'passportIssueDate', 'passportExpiry', 'isMainTraveler', 'reservationStatus'];
                        $values = [$travelerKey, $travelerType, $titleVal, $firstName, $lastName, $birthDateVal, $genderVal, $nationality, $passportNumber, $passportIssueDateVal, $passportExpiryVal, $isMainTraveler, $newStatus];
                        $types = 'sssssssssssis';

                        if (__table_has_column($conn, 'booking_travelers', 'profile_source')) {
                            $columns[] = 'profile_source';
                            $values[] = $profileSourceVal;
                            $types .= 's';
                        }
                        if (__table_has_column($conn, 'booking_travelers', 'visaType')) {
                            $columns[] = 'visaType';
                            $values[] = $visaTypeVal;
                            $types .= 's';
                        }
                        if (__table_has_column($conn, 'booking_travelers', 'passportImage') && $passportImageVal) {
                            $columns[] = 'passportImage';
                            $values[] = $passportImageVal;
                            $types .= 's';
                        }
                        if (__table_has_column($conn, 'booking_travelers', 'childRoom')) {
                            $columns[] = 'childRoom';
                            $values[] = $childRoomVal;
                            $types .= 'i';
                        }
                        if (__table_has_column($conn, 'booking_travelers', 'infantSeat')) {
                            $infantSeatVal = ($infantSeatRaw === 'yes' || $infantSeatRaw === 'Yes' || $infantSeatRaw === '1' || $infantSeatRaw === 1 || $infantSeatRaw === true) ? 1 : 0;
                            $columns[] = 'infantSeat';
                            $values[] = $infantSeatVal;
                            $types .= 'i';
                        }

                        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                        $insertSql = "INSERT INTO booking_travelers (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                        $stInsert = $conn->prepare($insertSql);
                        if ($stInsert) {
                            mysqli_bind_params_by_ref($stInsert, $types, $values);
                            $stInsert->execute();
                            $stInsert->close();
                        }
                        $travelerIdx++;
                    }

                    // 새 flight options 삽입
                    $travelerIdx = 0;
                    foreach ($pendingTravelers as $tr) {
                        if (!is_array($tr)) { $travelerIdx++; continue; }
                        $flightOptions = $tr['flightOptions'] ?? [];
                        $flightOptionPrices = $tr['flightOptionPrices'] ?? [];
                        if (is_array($flightOptions) && count($flightOptions) > 0) {
                            foreach ($flightOptions as $optionId) {
                                $optionId = intval($optionId);
                                $optionPrice = floatval($flightOptionPrices[$optionId] ?? 0);
                                $insertOptSql = "INSERT INTO booking_traveler_options (booking_id, traveler_index, option_id, price) VALUES (?, ?, ?, ?)";
                                $stInsertOpt = $conn->prepare($insertOptSql);
                                if ($stInsertOpt) {
                                    $stInsertOpt->bind_param('siid', $bookingId, $travelerIdx, $optionId, $optionPrice);
                                    $stInsertOpt->execute();
                                    $stInsertOpt->close();
                                }
                            }
                        }
                        $travelerIdx++;
                    }

                    // numberOfPeople 업데이트
                    $travelerCount = count($pendingTravelers);
                    if ($travelerCount > 0 && __table_has_column($conn, 'bookings', 'numberOfPeople')) {
                        $updPeople = $conn->prepare("UPDATE bookings SET numberOfPeople = ? WHERE bookingId = ?");
                        if ($updPeople) {
                            $updPeople->bind_param('is', $travelerCount, $bookingId);
                            $updPeople->execute();
                            $updPeople->close();
                        }
                    }

                    // booked_seats 정적 컬럼 UPDATE 제거 - 모든 잔여석은 bookings 테이블에서 동적 계산
                } catch (Throwable $e) {
                    send_error_response('Failed to apply traveler changes: ' . $e->getMessage());
                }

                // pendingTravelers에서 타입별 인원 수 계산
                $newAdults = 0;
                $newChildren = 0;
                $newInfants = 0;
                foreach ($pendingTravelers as $t) {
                    $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                    if ($type === 'adult') {
                        $newAdults++;
                    } elseif ($type === 'child') {
                        $newChildren++;
                    } elseif ($type === 'infant') {
                        $newInfants++;
                    }
                }

                // infantsWithSeat 계산
                $newInfantsWithSeat = 0;
                foreach ($pendingTravelers as $t) {
                    $tType = strtolower(trim($t['travelerType'] ?? $t['type'] ?? ''));
                    if ($tType === 'infant' && filter_var($t['infantSeat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $newInfantsWithSeat++;
                    }
                }

                // preDeducted 플래그 확인 (하위호환: 키 없으면 false)
                $preDeducted = (bool)($newData['preDeducted'] ?? false);
                $adjustmentReason = 'Traveler change approved';

                if ($preDeducted) {
                    // 증가(선차감): adults/children/infants/totalAmount은 이미 요청 시점에 반영됨
                    // bookingStatus 복원 + priceAdjustment + infantsWithSeat만 기록
                    $sql = "UPDATE bookings SET bookingStatus = ?, infantsWithSeat = ?, priceAdjustment = COALESCE(priceAdjustment, 0) + ?, lastAdjustmentDate = NOW(), adjustmentReason = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sidss', $newStatus, $newInfantsWithSeat, $priceAdjustment, $adjustmentReason, $bookingId);
                } else {
                    // 감소 또는 기존 방식: 이제 컬럼값 적용
                    $sql = "UPDATE bookings SET bookingStatus = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, priceAdjustment = COALESCE(priceAdjustment, 0) + ?, lastAdjustmentDate = NOW(), adjustmentReason = ?, adults = ?, children = ?, infants = ?, infantsWithSeat = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sddddsiiiis', $newStatus, $afterTotal, $newVisaFee, $newFlightOptionFee, $priceAdjustment, $adjustmentReason, $newAdults, $newChildren, $newInfants, $newInfantsWithSeat, $bookingId);
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 승인 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'approved', processedBy = ?, processedAt = NOW() WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('si', $processedBy, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // 같은 타입의 오래된 pending 요청 자동 reject
                __reject_superseded_change_requests($conn, $bookingId, $changeRequest['id'], $changeRequest['changeType'], $processedBy);

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $newStatus, null, null, 'Traveler changes approved');

                // 승인 이메일 알림 발송
                if (function_exists('send_approval_notification_email')) {
                    send_approval_notification_email($conn, $bookingId, $changeRequest['changeType'], $priceAdjustment, $beforeTotal, $afterTotal);
                }

                // 금액 변동 정보를 응답에 포함
                $adjustmentInfo = [
                    'beforeTotal' => $beforeTotal,
                    'afterTotal' => $afterTotal,
                    'adjustment' => $priceAdjustment
                ];
                send_success_response(['priceAdjustment' => $adjustmentInfo], 'Traveler changes approved and applied successfully. Amount adjusted: ' . ($priceAdjustment >= 0 ? '+' : '') . number_format($priceAdjustment) . ' PHP');

            } else if ($changeRequest['changeType'] === 'deadline') {
                // Deadline 변경 요청 승인: admin_kr만 승인 가능
                $currentAdminType = $_SESSION['admin_userType'] ?? '';
                if ($currentAdminType !== 'admin_kr') {
                    send_error_response('Only admin_kr can approve deadline changes');
                }

                // newData에서 deadline 정보 추출
                $deadlineData = json_decode($changeRequest['newData'], true);
                if (!$deadlineData) {
                    send_error_response('Invalid deadline data in change request');
                }

                $deadlineType = $deadlineData['type'] ?? 'balance';
                $newDeadline = $deadlineData['deadline'] ?? null;
                $fieldName = $deadlineData['fieldName'] ?? 'balanceDueDate';

                if (empty($newDeadline)) {
                    send_error_response('No deadline value found in change request');
                }

                // 같은 bookingId + deadline 타입의 다른 pending 요청을 자동 reject (중복 방지)
                $autoRejectStmt = $conn->prepare(
                    "UPDATE booking_change_requests SET status = 'rejected', rejectReason = 'Auto-rejected: superseded by approved request', processedBy = ?, processedAt = NOW() WHERE bookingId = ? AND changeType = 'deadline' AND status = 'pending' AND id != ?"
                );
                $autoRejectStmt->bind_param('ssi', $processedBy, $bookingId, $changeRequest['id']);
                $autoRejectStmt->execute();
                $autoRejectStmt->close();

                // 원래 상태로 복원하면서 deadline 업데이트
                $newStatus = __resolve_post_approval_status($conn, $bookingId, $changeRequest['id'], $changeRequest['originalStatus'] ?? 'confirmed');

                $sql = "UPDATE bookings SET bookingStatus = ?, $fieldName = ?, updatedAt = NOW() WHERE bookingId = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sss', $newStatus, $newDeadline, $bookingId);
                $stmt->execute();
                $stmt->close();

                // 변경 요청 승인 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'approved', processedBy = ?, processedAt = NOW() WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('si', $processedBy, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // 같은 타입의 오래된 pending 요청 자동 reject
                __reject_superseded_change_requests($conn, $bookingId, $changeRequest['id'], $changeRequest['changeType'], $processedBy);

                // 이력 추가
                $historyLabels = [
                    'down' => 'Down Payment deadline',
                    'deposit' => 'Down Payment deadline',
                    'second' => 'Second Payment deadline',
                    'advance' => 'Second Payment deadline',
                    'balance' => 'Balance deadline',
                    'full' => 'Full Payment deadline'
                ];
                $historyMsg = ($historyLabels[$deadlineType] ?? 'Payment deadline') . ' change approved: ' . $newDeadline;
                __addBookingHistory($conn, $bookingId, $historyMsg);

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $newStatus, null, null, $historyMsg);

                send_success_response([], 'Deadline change approved successfully. New deadline: ' . $newDeadline);

            } else {
                // 기타 데이터 수정 요청 승인
                $newStatus = __resolve_post_approval_status($conn, $bookingId, $changeRequest['id'], $changeRequest['originalStatus'] ?? 'confirmed');

                $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $newStatus, $bookingId);
                $stmt->execute();
                $stmt->close();

                // 변경 요청 승인 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'approved', processedBy = ?, processedAt = NOW() WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('si', $processedBy, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // 같은 타입의 오래된 pending 요청 자동 reject
                __reject_superseded_change_requests($conn, $bookingId, $changeRequest['id'], $changeRequest['changeType'], $processedBy);

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $newStatus, null, null, 'Booking update approved');

                send_success_response([], 'Booking update approved successfully');
            }
        } else {
            send_error_response('Only pending or pending_update bookings can be approved. Current status: ' . $booking['bookingStatus']);
        }
    } catch (Exception $e) {
        send_error_response('Failed to approve B2B booking: ' . $e->getMessage());
    }
}

function rejectB2BBooking($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        $reason = $input['reason'] ?? '';

        // pending/pending_update 상태인지 확인
        $checkSql = "SELECT bookingStatus FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $booking = $result->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found');
        }

        // remarks 컬럼 존재 여부 확인
        $hasRemarks = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'remarks'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasRemarks = true;
        } else {
            // remarks 컬럼 생성 시도
            @$conn->query("ALTER TABLE bookings ADD COLUMN remarks TEXT NULL");
            $hasRemarks = true;
        }

        $adminUserType = $_SESSION['admin_userType'] ?? '';

        if ($booking['bookingStatus'] === 'pending') {
            // 신규 예약 거절: admin_kr, admin_ph 가능
            if (!in_array($adminUserType, ['admin_kr', 'admin_ph'], true)) {
                send_error_response('Only admin_kr or admin_ph can reject new bookings', 403);
            }
            // pending → cancelled (rejected 대신 cancelled 사용)
            if ($hasRemarks && !empty($reason)) {
                $sql = "UPDATE bookings SET bookingStatus = 'cancelled', remarks = CONCAT(COALESCE(remarks, ''), '\n[Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $reason, $bookingId);
            } else {
                $sql = "UPDATE bookings SET bookingStatus = 'cancelled', updatedAt = NOW() WHERE bookingId = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $bookingId);
            }
            $stmt->execute();
            $stmt->close();

            // Google Sheets APP 동기화
            try {
                require_once __DIR__ . '/../../../backend/lib/google_sheets.php';
                gs_sync_by_booking_id($conn, $bookingId);
            } catch (Exception $gsEx) {
                error_log("Sheets sync failed (rejectB2B): " . $gsEx->getMessage());
            }

            // 상태 변경 히스토리 저장
            __log_booking_status_change($conn, $bookingId, 'pending', 'cancelled', null, null, $reason ?: 'Booking rejected');

            // Send rejection notification email
            if (function_exists('send_rejection_notification_email')) {
                send_rejection_notification_email($conn, $bookingId, 'booking', $reason);
            }

            send_success_response([], 'Booking rejected successfully');
        } else if ($booking['bookingStatus'] === 'pending_update') {
            // booking_change_requests 테이블에서 변경 요청 조회
            $changeReqSql = "SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' ORDER BY requestedAt DESC LIMIT 1";
            $changeReqStmt = $conn->prepare($changeReqSql);
            $changeReqStmt->bind_param('s', $bookingId);
            $changeReqStmt->execute();
            $changeReqResult = $changeReqStmt->get_result();
            $changeRequest = $changeReqResult->fetch_assoc();
            $changeReqStmt->close();

            if (!$changeRequest) {
                send_error_response('No pending change request found for this booking');
            }

            // status, deadline changeType은 admin_kr만 거절 가능
            if ($changeRequest['changeType'] === 'deadline') {
                if ($adminUserType !== 'admin_kr') {
                    send_error_response('Only admin_kr can reject this change type', 403);
                }
            }

            $processedBy = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'admin';
            $originalStatus = __resolve_post_approval_status($conn, $bookingId, $changeRequest['id'], $changeRequest['originalStatus'] ?? 'confirmed');
            $originalPaymentStatus = $changeRequest['originalPaymentStatus'] ?? null;

            if ($changeRequest['changeType'] === 'status') {
                // 상태 변경 요청 거절: 원래 상태로 복원
                if ($hasRemarks && !empty($reason)) {
                    if ($originalPaymentStatus !== null) {
                        $sql = "UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Status Change Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ssss', $originalStatus, $originalPaymentStatus, $reason, $bookingId);
                    } else {
                        $sql = "UPDATE bookings SET bookingStatus = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Status Change Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('sss', $originalStatus, $reason, $bookingId);
                    }
                } else {
                    if ($originalPaymentStatus !== null) {
                        $sql = "UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('sss', $originalStatus, $originalPaymentStatus, $bookingId);
                    } else {
                        $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ss', $originalStatus, $bookingId);
                    }
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 거절 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'rejected', processedBy = ?, processedAt = NOW(), rejectReason = ? WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('ssi', $processedBy, $reason, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // Send rejection notification email
                if (function_exists('send_rejection_notification_email')) {
                    send_rejection_notification_email($conn, $bookingId, 'change_request', $reason);
                }

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $originalStatus, null, null, 'Status change rejected' . (!empty($reason) ? ': ' . $reason : ''));

                send_success_response([], 'Status change rejected successfully');

            } else if ($changeRequest['changeType'] === 'travelers' || $changeRequest['changeType'] === 'travelers_add_remove') {
                // Traveler 변경 요청 거절: pending_update → check_reject (에이전트가 거절 사유 확인 필요)

                // 선차감 복원: preDeducted=true였으면 원본 컬럼값으로 복원
                $rejectNewData = json_decode($changeRequest['newData'], true);
                $rejectPreviousData = json_decode($changeRequest['previousData'], true);
                $rejectPreDeducted = (bool)($rejectNewData['preDeducted'] ?? false);

                if ($rejectPreDeducted && $rejectPreviousData) {
                    // 증가가 선차감되었으므로 원본으로 복원 + check_reject 설정
                    $origAdults = (int)($rejectPreviousData['originalAdults'] ?? 0);
                    $origChildren = (int)($rejectPreviousData['originalChildren'] ?? 0);
                    $origInfants = (int)($rejectPreviousData['originalInfants'] ?? 0);
                    $origTotalAmount = (float)($rejectPreviousData['originalTotalAmount'] ?? 0);
                    $origVisaFee = (float)($rejectPreviousData['originalVisaFee'] ?? 0);
                    $origFlightOptionFee = (float)($rejectPreviousData['originalFlightOptionFee'] ?? 0);

                    if ($hasRemarks && !empty($reason)) {
                        $sql = "UPDATE bookings SET bookingStatus = 'check_reject', adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Traveler Change Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('iiidddss', $origAdults, $origChildren, $origInfants, $origTotalAmount, $origVisaFee, $origFlightOptionFee, $reason, $bookingId);
                    } else {
                        $sql = "UPDATE bookings SET bookingStatus = 'check_reject', adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('iiiddds', $origAdults, $origChildren, $origInfants, $origTotalAmount, $origVisaFee, $origFlightOptionFee, $bookingId);
                    }
                } else {
                    // 감소(preDeducted=false) 또는 기존 방식: 복원할 것 없음
                    if ($hasRemarks && !empty($reason)) {
                        $sql = "UPDATE bookings SET bookingStatus = 'check_reject', remarks = CONCAT(COALESCE(remarks, ''), '\n[Traveler Change Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ss', $reason, $bookingId);
                    } else {
                        $sql = "UPDATE bookings SET bookingStatus = 'check_reject', updatedAt = NOW() WHERE bookingId = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('s', $bookingId);
                    }
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 거절 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'rejected', processedBy = ?, processedAt = NOW(), rejectReason = ? WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('ssi', $processedBy, $reason, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // Send rejection notification email
                if (function_exists('send_rejection_notification_email')) {
                    send_rejection_notification_email($conn, $bookingId, 'change_request', $reason);
                }

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', 'check_reject', null, null, 'Traveler change rejected' . (!empty($reason) ? ': ' . $reason : ''));

                send_success_response([], 'Traveler change rejected successfully');

            } else if ($changeRequest['changeType'] === 'deadline') {
                // Deadline 변경 요청 거절: 원래 상태로 복원 (deadline은 변경하지 않음)
                if ($hasRemarks && !empty($reason)) {
                    $sql = "UPDATE bookings SET bookingStatus = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Deadline Change Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sss', $originalStatus, $reason, $bookingId);
                } else {
                    $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $originalStatus, $bookingId);
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 거절 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'rejected', processedBy = ?, processedAt = NOW(), rejectReason = ? WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('ssi', $processedBy, $reason, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // 이력 추가
                __addBookingHistory($conn, $bookingId, 'Deadline change rejected' . (!empty($reason) ? ': ' . $reason : ''));

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', $originalStatus, null, null, 'Deadline change rejected' . (!empty($reason) ? ': ' . $reason : ''));

                send_success_response([], 'Deadline change rejected successfully');

            } else {
                // 기타 데이터 수정 요청 거절: pending_update → check_reject (사용자가 확인 필요)
                if ($hasRemarks && !empty($reason)) {
                    $sql = "UPDATE bookings SET bookingStatus = 'check_reject', remarks = CONCAT(COALESCE(remarks, ''), '\n[Update Rejected] ', ?), updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $reason, $bookingId);
                } else {
                    $sql = "UPDATE bookings SET bookingStatus = 'check_reject', updatedAt = NOW() WHERE bookingId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('s', $bookingId);
                }
                $stmt->execute();
                $stmt->close();

                // 변경 요청 거절 처리
                $updateReqSql = "UPDATE booking_change_requests SET status = 'rejected', processedBy = ?, processedAt = NOW(), rejectReason = ? WHERE id = ?";
                $updateReqStmt = $conn->prepare($updateReqSql);
                $updateReqStmt->bind_param('ssi', $processedBy, $reason, $changeRequest['id']);
                $updateReqStmt->execute();
                $updateReqStmt->close();

                // Send rejection notification email
                if (function_exists('send_rejection_notification_email')) {
                    send_rejection_notification_email($conn, $bookingId, 'change_request', $reason);
                }

                // 상태 변경 히스토리 저장
                __log_booking_status_change($conn, $bookingId, 'pending_update', 'check_reject', null, null, 'Booking update rejected' . (!empty($reason) ? ': ' . $reason : ''));

                send_success_response([], 'Booking update rejected successfully');
            }
        } else {
            send_error_response('Only pending or pending_update bookings can be rejected. Current status: ' . $booking['bookingStatus']);
        }
    } catch (Exception $e) {
        send_error_response('Failed to reject B2B booking: ' . $e->getMessage());
    }
}

// 사용자가 거부 확인 시 booking_change_requests에서 원래 상태로 복원하는 함수
function acknowledgeRejection($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // check_reject 상태인지 확인
        $checkSql = "SELECT bookingStatus FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $booking = $result->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found');
        }

        if ($booking['bookingStatus'] !== 'check_reject') {
            send_error_response('Only check_reject bookings can be acknowledged. Current status: ' . $booking['bookingStatus']);
        }

        // booking_change_requests에서 거절된 변경 요청 조회
        $changeReqSql = "SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'rejected' ORDER BY processedAt DESC LIMIT 1";
        $changeReqStmt = $conn->prepare($changeReqSql);
        $changeReqStmt->bind_param('s', $bookingId);
        $changeReqStmt->execute();
        $changeReqResult = $changeReqStmt->get_result();
        $changeRequest = $changeReqResult->fetch_assoc();
        $changeReqStmt->close();

        if (!$changeRequest) {
            send_error_response('No rejected change request found for this booking');
        }

        // 원래 상태로 복원 (pending_update/check_reject 오염 방지)
        $originalStatus = __resolve_post_approval_status($conn, $bookingId, $changeRequest['id'], $changeRequest['originalStatus'] ?? 'confirmed');
        $originalPaymentStatus = $changeRequest['originalPaymentStatus'];

        if ($originalPaymentStatus !== null) {
            $sql = "UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $originalStatus, $originalPaymentStatus, $bookingId);
        } else {
            $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $originalStatus, $bookingId);
        }
        $stmt->execute();
        $stmt->close();

        // travelers 변경 요청이 거절된 경우 원본 traveler 데이터 복원
        if ($changeRequest['changeType'] === 'travelers' && !empty($changeRequest['previousData'])) {
            $previousData = json_decode($changeRequest['previousData'], true);
            $originalTravelers = $previousData['originalTravelers'] ?? [];

            if (!empty($originalTravelers)) {
                // 현재 travelers 삭제
                $deleteSql = "DELETE FROM booking_travelers WHERE transactNo = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param('s', $bookingId);
                $deleteStmt->execute();
                $deleteStmt->close();

                // 원본 travelers 복원
                foreach ($originalTravelers as $tr) {
                    $travelerType = $tr['travelerType'] ?? 'adult';
                    $title = $tr['title'] ?? null;
                    $firstName = $tr['firstName'] ?? '';
                    $lastName = $tr['lastName'] ?? '';
                    $birthDate = $tr['birthDate'] ?? null;
                    $gender = $tr['gender'] ?? null;
                    $nationality = $tr['nationality'] ?? '';
                    $passportNumber = $tr['passportNumber'] ?? '';
                    $passportIssueDate = $tr['passportIssueDate'] ?? null;
                    $passportExpiry = $tr['passportExpiry'] ?? null;
                    $passportImage = $tr['passportImage'] ?? null;
                    $visaDocument = $tr['visaDocument'] ?? null;
                    $visaStatus = $tr['visaStatus'] ?? 'not_required';
                    $visaType = $tr['visaType'] ?? null;
                    $specialRequests = $tr['specialRequests'] ?? null;
                    $isMainTraveler = (int)($tr['isMainTraveler'] ?? 0);
                    $reservationStatus = $tr['reservationStatus'] ?? null;
                    $childRoom = (int)($tr['childRoom'] ?? 0);
                    $infantSeat = (int)($tr['infantSeat'] ?? 0);

                    // null 또는 빈 날짜 값 처리
                    $birthDateVal = (!empty($birthDate) && $birthDate !== '0000-00-00') ? $birthDate : null;
                    $passportIssueDateVal = (!empty($passportIssueDate) && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                    $passportExpiryVal = (!empty($passportExpiry) && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;

                    $insertSql = "INSERT INTO booking_travelers (transactNo, travelerType, title, firstName, lastName, birthDate, gender, nationality, passportNumber, passportIssueDate, passportExpiry, passportImage, visaDocument, visaStatus, visaType, specialRequests, isMainTraveler, reservationStatus, childRoom, infantSeat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if ($insertStmt) {
                        $insertStmt->bind_param('ssssssssssssssssssii',
                            $bookingId, $travelerType, $title, $firstName, $lastName,
                            $birthDateVal, $gender, $nationality, $passportNumber,
                            $passportIssueDateVal, $passportExpiryVal, $passportImage,
                            $visaDocument, $visaStatus, $visaType, $specialRequests,
                            $isMainTraveler, $reservationStatus, $childRoom, $infantSeat
                        );
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
            }
        }

        send_success_response([], 'Rejection acknowledged and booking status restored successfully');
    } catch (Exception $e) {
        send_error_response('Failed to acknowledge rejection: ' . $e->getMessage());
    }
}

// 입금 기한 설정 (Super Admin)
// 변경 시 pending_update 상태로 전환되며 admin_kr 승인 필요
function setPaymentDeadline($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        $deadline = $input['deadline'] ?? null;
        $deadlineType = $input['deadlineType'] ?? 'balance'; // 'down', 'second', 'balance', 'full'

        if (empty($deadline)) {
            send_error_response('Deadline date is required');
        }

        // bookings 스키마: downPaymentDueDate / advancePaymentDueDate / balanceDueDate / fullPaymentDueDate
        $fieldName = 'balanceDueDate';
        switch ($deadlineType) {
            case 'down':
            case 'deposit':
                $fieldName = 'downPaymentDueDate';
                break;
            case 'second':
            case 'advance':
                $fieldName = 'advancePaymentDueDate';
                break;
            case 'balance':
                $fieldName = 'balanceDueDate';
                break;
            case 'full':
                $fieldName = 'fullPaymentDueDate';
                break;
        }

        $historyLabels = [
            'down' => 'Down Payment deadline',
            'deposit' => 'Down Payment deadline',
            'second' => 'Second Payment deadline',
            'advance' => 'Second Payment deadline',
            'balance' => 'Balance deadline',
            'full' => 'Full Payment deadline'
        ];

        // 예약 정보 조회 (departureDate 포함)
        $checkSql = "SELECT bookingId, bookingStatus, departureDate, $fieldName as currentDeadline FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $booking = $result->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found', 404);
        }

        // deadline을 DATE 형식으로 변환 (YYYY-MM-DD HH:MM -> YYYY-MM-DD)
        $deadlineDate = substr(trim($deadline), 0, 10);
        $currentDeadline = $booking['currentDeadline'] ?? null;
        $originalStatus = __get_true_original_status($conn, $bookingId, $booking['bookingStatus']);

        // 세션에서 요청자 정보 가져오기
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $requestedBy = $_SESSION['admin_userType'] ?? $_SESSION['admin_accountId'] ?? 'admin';
        $adminUserType = $_SESSION['admin_userType'] ?? 'admin_ph';

        // 출발일까지 남은 일수 계산
        $departureDate = $booking['departureDate'] ?? null;
        $daysUntilDeparture = null;
        if ($departureDate) {
            $depDate = new DateTime(substr($departureDate, 0, 10));
            $today = new DateTime(date('Y-m-d'));
            $daysUntilDeparture = (int)$today->diff($depDate)->format('%r%a');
        }

        // 모든 admin이 항상 직접 deadline 업데이트 (pending_update 플로우 제거)
        $updateSql = "UPDATE bookings SET $fieldName = ?, updatedAt = NOW() WHERE bookingId = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $deadlineDate, $bookingId);
        $updateStmt->execute();
        $updateStmt->close();

        // 예약 이력 추가
        $historyMsg = ($historyLabels[$deadlineType] ?? 'Payment deadline') . ' changed directly: ' . ($currentDeadline ?? 'none') . ' → ' . $deadlineDate;
        __addBookingHistory($conn, $bookingId, $historyMsg, [
            'changeType' => 'deadline',
            'changedBy' => $_SESSION['admin_username'] ?? 'admin',
            'changedByType' => $_SESSION['admin_userType'] ?? 'admin_ph',
            'previousData' => json_encode(['type' => $deadlineType, 'deadline' => $currentDeadline]),
            'newData' => json_encode(['type' => $deadlineType, 'deadline' => $deadlineDate])
        ]);

        // booking_payments 테이블도 동기화
        $stepMap = [
            'down' => 'down', 'deposit' => 'down',
            'second' => 'second', 'advance' => 'second',
            'balance' => 'balance',
            'full' => 'full'
        ];
        $paymentStep = $stepMap[$deadlineType] ?? $deadlineType;
        if (function_exists('upsertPayment')) {
            upsertPayment($conn, $bookingId, $paymentStep, ['dueDate' => $deadlineDate]);
        }

        // Send deadline changed email
        try {
            if (function_exists('send_deadline_extended_email')) {
                $adminName = $_SESSION['admin_username'] ?? 'Admin';
                send_deadline_extended_email($conn, $bookingId, $paymentStep, $deadlineDate, $adminName);
            }
        } catch (Throwable $emailEx) {
            error_log("Failed to send deadline changed email for {$bookingId}: " . $emailEx->getMessage());
        }

        send_success_response(['directUpdate' => true], 'Payment deadline updated successfully.');
    } catch (Exception $e) {
        send_error_response('Failed to set payment deadline: ' . $e->getMessage());
    }
}

/**
 * Toggle edit_allowed for a booking (allows agent to edit product/traveler info)
 */
function toggleEditAllowed($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // Get current edit_allowed status
        $stmt = $conn->prepare("SELECT edit_allowed FROM bookings WHERE bookingId = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            send_error_response('Booking not found');
        }

        // Toggle the value
        $currentValue = intval($row['edit_allowed'] ?? 0);
        $newValue = $currentValue === 1 ? 0 : 1;

        $stmt = $conn->prepare("UPDATE bookings SET edit_allowed = ? WHERE bookingId = ?");
        $stmt->bind_param('is', $newValue, $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response([
            'edit_allowed' => $newValue
        ], $newValue === 1 ? 'Edit allowed for agent' : 'Edit locked');
    } catch (Exception $e) {
        send_error_response('Failed to toggle edit allowed: ' . $e->getMessage());
    }
}

function getGuideAssignments($conn, $input) {
    try {
        $guideId = $input['guideId'] ?? $input['id'] ?? null;
        if (empty($guideId)) {
            send_error_response('Guide ID is required');
        }
        
        // guide_assignments와 bookings, packages 테이블 조인하여 가이드 배정 정보 조회 (신규 운영 예약: bookings)
        $sql = "SELECT 
                    ga.assignmentId,
                    ga.tourDate,
                    ga.status,
                    COALESCE(NULLIF(b.transactNo,''), b.bookingId, ga.transactNo) as transactNo,
                    COALESCE(NULLIF(b.bookingStatus,''), '') as bookingStatus,
                    COALESCE(b.totalAmount, 0) as totalPrice,
                    TRIM(CONCAT(
                        COALESCE(NULLIF(c.fName,''), NULLIF(a.firstName,''), NULLIF(a.displayName,''), ''),
                        ' ',
                        COALESCE(NULLIF(c.lName,''), NULLIF(a.lastName,''), '')
                    )) as customerName,
                    (COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infants,0)) as participants,
                    COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,'')) as productName
                FROM guide_assignments ga
                LEFT JOIN bookings b ON (ga.transactNo = b.bookingId OR (b.transactNo IS NOT NULL AND ga.transactNo = b.transactNo))
                LEFT JOIN client c ON b.accountId = c.accountId
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN packages p ON ga.packageId = p.packageId
                WHERE ga.guideId = ?
                ORDER BY ga.tourDate DESC, ga.assignedAt DESC
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $guideId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        send_success_response(['assignments' => $assignments], 'Guide assignments retrieved successfully');
    } catch (Exception $e) {
        send_error_response('Failed to get guide assignments: ' . $e->getMessage());
    }
}

function getGuideActivityLogs($conn, $input) {
    try {
        $guideId = $input['guideId'] ?? $input['id'] ?? null;
        if (empty($guideId)) {
            send_error_response('Guide ID is required');
        }
        
        // guides 테이블에서 accountId 찾기
        $guideSql = "SELECT accountId FROM guides WHERE guideId = ? OR guideCode = ?";
        $guideStmt = $conn->prepare($guideSql);
        $guideStmt->bind_param('ss', $guideId, $guideId);
        $guideStmt->execute();
        $guideResult = $guideStmt->get_result();
        $guide = $guideResult->fetch_assoc();
        $guideStmt->close();
        
        if (!$guide) {
            send_success_response(['logs' => []], 'No activity logs found');
            return;
        }
        
        $accountId = $guide['accountId'];
        
        // activity_logs 테이블에서 해당 가이드의 활동 로그 조회
        $sql = "SELECT 
                    al.logId,
                    al.action,
                    al.description,
                    al.createdAt,
                    ac.username as actor
                FROM activity_logs al
                LEFT JOIN accounts ac ON al.accountId = ac.accountId
                WHERE al.accountId = ?
                ORDER BY al.createdAt DESC
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        send_success_response(['logs' => $logs], 'Activity logs retrieved successfully');
    } catch (Exception $e) {
        send_error_response('Failed to get activity logs: ' . $e->getMessage());
    }
}

function getAgentActivityLogs($conn, $input) {
    try {
        $agentId = $input['agentId'] ?? $input['id'] ?? null;
        if (empty($agentId)) {
            send_error_response('Agent ID is required');
        }
        
        // agent 테이블에서 accountId 찾기
        $agentSql = "SELECT accountId FROM agent WHERE agentId = ? OR agentCode = ? OR accountId = ?";
        $agentStmt = $conn->prepare($agentSql);
        $agentStmt->bind_param('ssi', $agentId, $agentId, $agentId);
        $agentStmt->execute();
        $agentResult = $agentStmt->get_result();
        $agent = $agentResult->fetch_assoc();
        $agentStmt->close();
        
        if (!$agent) {
            send_success_response(['logs' => []], 'No activity logs found');
            return;
        }
        
        $accountId = $agent['accountId'];
        
        // activity_logs 테이블에서 해당 에이전트의 활동 로그 조회
        $sql = "SELECT 
                    al.logId,
                    al.action,
                    al.description,
                    al.createdAt,
                    ac.username as actor
                FROM activity_logs al
                LEFT JOIN accounts ac ON al.accountId = ac.accountId
                WHERE al.accountId = ?
                ORDER BY al.createdAt DESC
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        send_success_response(['logs' => $logs], 'Activity logs retrieved successfully');
    } catch (Exception $e) {
        send_error_response('Failed to get activity logs: ' . $e->getMessage());
    }
}

// ========== 재고 관리 함수들 ==========

/**
 * 월별 재고 캘린더 조회
 */
function getInventoryCalendar($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $year = intval($input['year'] ?? date('Y'));
        $month = intval($input['month'] ?? date('n'));

        if ($packageId <= 0) {
            send_error_response('Package ID is required');
            return;
        }

        // 상품 정보 조회
        $pkgStmt = $conn->prepare("SELECT packageId, packageName, maxParticipants, packagePrice, b2b_price, sales_start_date, sales_end_date FROM packages WHERE packageId = ?");
        $pkgStmt->bind_param('i', $packageId);
        $pkgStmt->execute();
        $pkgResult = $pkgStmt->get_result();
        $package = $pkgResult->fetch_assoc();
        $pkgStmt->close();

        if (!$package) {
            send_error_response('Product not found', 404);
            return;
        }

        // 월의 시작/종료일
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = date('t', strtotime($monthStart));
        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);

        // package_available_dates에서 데이터 조회 (B2B 가격 포함)
        $availabilityByDate = [];
        $avStmt = $conn->prepare("
            SELECT available_date AS availableDate, capacity AS availableSeats,
                   price, b2b_price AS b2bPrice,
                   childPrice, b2b_child_price AS b2bChildPrice,
                   infant_price AS infantPrice, b2b_infant_price AS b2bInfantPrice,
                   infant_seat_price AS infantSeatPrice, b2b_infant_seat_price AS b2bInfantSeatPrice,
                   singlePrice, flight_id AS flightId, departure_time AS departureTime, status
            FROM package_available_dates
            WHERE package_id = ? AND available_date >= ? AND available_date <= ?
            ORDER BY available_date ASC
        ");
        $avStmt->bind_param('iss', $packageId, $monthStart, $monthEnd);
        $avStmt->execute();
        $avResult = $avStmt->get_result();
        while ($row = $avResult->fetch_assoc()) {
            $dateStr = substr($row['availableDate'], 0, 10);
            $availabilityByDate[$dateStr] = $row;
        }
        $avStmt->close();

        // 예약 현황 집계
        $bookedByDate = [];
        $bkStmt = $conn->prepare("
            SELECT departureDate, SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
            FROM bookings
            WHERE packageId = ? AND departureDate >= ? AND departureDate <= ?
              AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
              AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
            GROUP BY departureDate
        ");
        $bkStmt->bind_param('iss', $packageId, $monthStart, $monthEnd);
        $bkStmt->execute();
        $bkResult = $bkStmt->get_result();
        while ($row = $bkResult->fetch_assoc()) {
            $dateStr = substr($row['departureDate'], 0, 10);
            $bookedByDate[$dateStr] = intval($row['booked']);
        }
        $bkStmt->close();

        // 캘린더 데이터 생성
        $calendar = [];
        $defaultSeats = intval($package['maxParticipants'] ?? 0);
        $defaultPrice = floatval($package['packagePrice'] ?? 0);
        $defaultB2bPrice = isset($package['b2b_price']) && $package['b2b_price'] !== null ? floatval($package['b2b_price']) : null;
        $today = date('Y-m-d');

        // 판매 기간 설정
        $salesStartDate = $package['sales_start_date'] ?? null;
        $salesEndDate = $package['sales_end_date'] ?? null;

        for ($day = 1; $day <= $lastDay; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

            // 판매 기간 외의 날짜는 건너뛰기
            if ($salesStartDate && $dateStr < $salesStartDate) continue;
            if ($salesEndDate && $dateStr > $salesEndDate) continue;

            $isPast = ($dateStr < $today);

            $availableSeats = $defaultSeats;
            $price = $defaultPrice;
            $childPrice = null;
            $infantPrice = null;
            $singlePrice = null;
            // B2B 가격 (기본값은 패키지의 b2b_price)
            $b2bPrice = $defaultB2bPrice;
            $b2bChildPrice = null;
            $b2bInfantPrice = null;
            $status = 'open';
            $flightId = null;
            $departureTime = '';

            if (isset($availabilityByDate[$dateStr])) {
                $av = $availabilityByDate[$dateStr];
                $availableSeats = intval($av['availableSeats'] ?? $defaultSeats);
                $price = floatval($av['price'] ?? $defaultPrice);
                $childPrice = isset($av['childPrice']) ? floatval($av['childPrice']) : null;
                $infantPrice = isset($av['infantPrice']) ? floatval($av['infantPrice']) : null;
                $singlePrice = isset($av['singlePrice']) ? floatval($av['singlePrice']) : null;
                // B2B 가격 (0이거나 null이면 기본값 유지)
                $b2bPrice = isset($av['b2bPrice']) && $av['b2bPrice'] !== null && floatval($av['b2bPrice']) > 0 ? floatval($av['b2bPrice']) : $defaultB2bPrice;
                $b2bChildPrice = isset($av['b2bChildPrice']) && $av['b2bChildPrice'] !== null ? floatval($av['b2bChildPrice']) : null;
                $b2bInfantPrice = isset($av['b2bInfantPrice']) && $av['b2bInfantPrice'] !== null ? floatval($av['b2bInfantPrice']) : null;
                $status = $av['status'] ?? 'open';
                $flightId = isset($av['flightId']) ? intval($av['flightId']) : null;
                $departureTime = substr($av['departureTime'] ?? '', 0, 5);
            }

            $bookedSeats = intval($bookedByDate[$dateStr] ?? 0);
            $remainingSeats = max(0, $availableSeats - $bookedSeats);

            // 수동으로 closed 설정된 경우 유지, 아니면 상태 계산
            $dbStatus = $status; // DB에서 가져온 원래 상태 보존
            if ($isPast) {
                $status = 'past';
            } elseif ($dbStatus === 'closed') {
                $status = 'closed'; // 수동 마감은 유지
            } elseif ($remainingSeats <= 0 && $availableSeats > 0) {
                $status = 'soldout';
            } elseif ($availableSeats <= 0) {
                $status = 'closed';
            }

            $calendar[] = [
                'date' => $dateStr,
                'day' => $day,
                'availableSeats' => $availableSeats,
                'bookedSeats' => $bookedSeats,
                'remainingSeats' => $remainingSeats,
                // B2C 가격
                'price' => $price,
                'childPrice' => $childPrice,
                'infantPrice' => $infantPrice,
                'singlePrice' => $singlePrice,
                // B2B 가격
                'b2bPrice' => $b2bPrice,
                'b2bChildPrice' => $b2bChildPrice,
                'b2bInfantPrice' => $b2bInfantPrice,
                'status' => $status,
                'flightId' => $flightId,
                'departureTime' => $departureTime,
                'isPast' => $isPast
            ];
        }

        send_success_response([
            'product' => [
                'packageId' => intval($package['packageId']),
                'packageName' => $package['packageName'],
                'maxParticipants' => $defaultSeats,
                'packagePrice' => $defaultPrice,
                'b2bPrice' => isset($package['b2b_price']) ? floatval($package['b2b_price']) : null,
                'salesStartDate' => $package['sales_start_date'] ?? null,
                'salesEndDate' => $package['sales_end_date'] ?? null
            ],
            'year' => $year,
            'month' => $month,
            'calendar' => $calendar
        ], 'Inventory calendar retrieved successfully');

    } catch (Exception $e) {
        send_error_response('Failed to get inventory calendar: ' . $e->getMessage());
    }
}

/**
 * 단일 날짜 재고 수정
 */
function updateInventory($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $availableDate = trim($input['availableDate'] ?? '');
        $availableSeats = isset($input['availableSeats']) ? intval($input['availableSeats']) : null;
        // B2C 가격
        $price = isset($input['price']) ? floatval($input['price']) : null;
        $childPrice = isset($input['childPrice']) ? floatval($input['childPrice']) : null;
        $infantPrice = isset($input['infantPrice']) ? floatval($input['infantPrice']) : null;
        $singlePrice = isset($input['singlePrice']) ? floatval($input['singlePrice']) : null;
        // B2B 가격
        $b2bPrice = isset($input['b2bPrice']) ? floatval($input['b2bPrice']) : null;
        $b2bChildPrice = isset($input['b2bChildPrice']) ? floatval($input['b2bChildPrice']) : null;
        $b2bInfantPrice = isset($input['b2bInfantPrice']) ? floatval($input['b2bInfantPrice']) : null;
        $b2bInfantSeatPrice = isset($input['b2bInfantSeatPrice']) ? floatval($input['b2bInfantSeatPrice']) : null;
        // 상태 (open/closed)
        $status = isset($input['status']) ? trim($input['status']) : null;
        if ($status !== null && !in_array($status, ['open', 'closed'])) {
            $status = null; // 유효하지 않은 값은 무시
        }

        if ($packageId <= 0) {
            send_error_response('Package ID is required');
            return;
        }
        if ($availableDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $availableDate)) {
            send_error_response('Valid date (YYYY-MM-DD) is required');
            return;
        }

        // 기존 레코드 확인
        $checkStmt = $conn->prepare("SELECT id FROM package_available_dates WHERE package_id = ? AND available_date = ?");
        $checkStmt->bind_param('is', $packageId, $availableDate);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            // UPDATE
            $updates = [];
            $types = '';
            $params = [];

            if ($availableSeats !== null) {
                $updates[] = 'capacity = ?';
                $types .= 'i';
                $params[] = $availableSeats;
            }
            if ($price !== null) {
                $updates[] = 'price = ?';
                $types .= 'd';
                $params[] = $price;
            }
            if ($childPrice !== null) {
                $updates[] = 'childPrice = ?';
                $types .= 'd';
                $params[] = $childPrice;
            }
            if ($infantPrice !== null) {
                $updates[] = 'infant_price = ?';
                $types .= 'd';
                $params[] = $infantPrice;
            }
            if ($singlePrice !== null) {
                $updates[] = 'singlePrice = ?';
                $types .= 'd';
                $params[] = $singlePrice;
            }
            // B2B 가격
            if ($b2bPrice !== null) {
                $updates[] = 'b2b_price = ?';
                $types .= 'd';
                $params[] = $b2bPrice;
            }
            if ($b2bChildPrice !== null) {
                $updates[] = 'b2b_child_price = ?';
                $types .= 'd';
                $params[] = $b2bChildPrice;
            }
            if ($b2bInfantPrice !== null) {
                $updates[] = 'b2b_infant_price = ?';
                $types .= 'd';
                $params[] = $b2bInfantPrice;
            }
            if ($b2bInfantSeatPrice !== null) {
                $updates[] = 'b2b_infant_seat_price = ?';
                $types .= 'd';
                $params[] = $b2bInfantSeatPrice;
            }
            // 상태 (open/closed)
            if ($status !== null) {
                $updates[] = 'status = ?';
                $types .= 's';
                $params[] = $status;
            }

            if (empty($updates)) {
                send_error_response('No fields to update');
                return;
            }

            $sql = "UPDATE package_available_dates SET " . implode(', ', $updates) . " WHERE package_id = ? AND available_date = ?";
            $types .= 'is';
            $params[] = $packageId;
            $params[] = $availableDate;

            $stmt = $conn->prepare($sql);
            mysqli_bind_params_by_ref($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();

            send_success_response(['updated' => true], 'Inventory updated successfully');
        } else {
            // INSERT (B2B 가격 포함)
            $seats = $availableSeats ?? 0;
            $prc = $price ?? 0;
            $cPrc = $childPrice;
            $iPrc = $infantPrice;
            $sPrc = $singlePrice;
            $b2bPrc = $b2bPrice;
            $b2bCPrc = $b2bChildPrice;
            $b2bIPrc = $b2bInfantPrice;
            $b2bISPrc = $b2bInfantSeatPrice;

            $insertStatus = $status ?? 'open';
            $insertStmt = $conn->prepare("
                INSERT INTO package_available_dates (package_id, available_date, capacity, price, b2b_price, childPrice, b2b_child_price, infant_price, b2b_infant_price, b2b_infant_seat_price, singlePrice, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->bind_param('isidddddddds', $packageId, $availableDate, $seats, $prc, $b2bPrc, $cPrc, $b2bCPrc, $iPrc, $b2bIPrc, $b2bISPrc, $sPrc, $insertStatus);
            $insertStmt->execute();
            $insertStmt->close();

            send_success_response(['inserted' => true], 'Inventory created successfully');
        }

    } catch (Exception $e) {
        send_error_response('Failed to update inventory: ' . $e->getMessage());
    }
}

/**
 * 일괄 재고 등록/수정
 */
function bulkUpdateInventory($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');
        $daysOfWeek = $input['daysOfWeek'] ?? [0,1,2,3,4,5,6]; // 0=일, 1=월, ..., 6=토
        $availableSeats = isset($input['availableSeats']) ? intval($input['availableSeats']) : 0;
        $status = trim($input['status'] ?? 'open');
        $price = isset($input['price']) ? floatval($input['price']) : 0;

        // status 유효성 검사
        if (!in_array($status, ['open', 'closed'])) {
            $status = 'open';
        }

        if ($packageId <= 0) {
            send_error_response('Package ID is required');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            send_error_response('Valid start and end dates (YYYY-MM-DD) are required');
            return;
        }
        if ($startDate > $endDate) {
            send_error_response('Start date must be before or equal to end date');
            return;
        }

        // daysOfWeek를 배열로 확보
        if (is_string($daysOfWeek)) {
            $daysOfWeek = json_decode($daysOfWeek, true) ?? [];
        }
        if (!is_array($daysOfWeek) || empty($daysOfWeek)) {
            $daysOfWeek = [0,1,2,3,4,5,6];
        }
        $daysOfWeek = array_map('intval', $daysOfWeek);

        $conn->begin_transaction();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');

        while ($current < $end) {
            $dow = intval($current->format('w')); // 0=일, 1=월, ...
            $dateStr = $current->format('Y-m-d');

            if (in_array($dow, $daysOfWeek)) {
                // INSERT ... ON DUPLICATE KEY UPDATE
                $sql = "INSERT INTO package_available_dates (package_id, available_date, capacity, price, status, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), price = VALUES(price), status = VALUES(status)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isids', $packageId, $dateStr, $availableSeats, $price, $status);
                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $inserted++;
                } elseif ($stmt->affected_rows === 2) {
                    $updated++;
                }
                $stmt->close();
            } else {
                $skipped++;
            }

            $current->modify('+1 day');
        }

        $conn->commit();

        send_success_response([
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $inserted + $updated
        ], 'Bulk inventory update completed');

    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to bulk update inventory: ' . $e->getMessage());
    }
}

/**
 * 상품 공지사항 목록 조회
 */
function getProductAnnouncements($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? min(100, max(1, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;
        $search = trim($input['search'] ?? '');
        $status = trim($input['status'] ?? '');

        // Build WHERE clause
        $whereClauses = ["pa.status != 'deleted'"];
        $params = [];
        $types = '';

        if ($search !== '') {
            $whereClauses[] = "(pa.title LIKE ? OR p.packageName LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }

        if ($status !== '') {
            $whereClauses[] = "pa.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Count total
        $countSql = "SELECT COUNT(*) as total
                     FROM product_announcements pa
                     LEFT JOIN packages p ON pa.packageId = p.packageId
                     WHERE $whereClause";

        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
        } else {
            $countResult = $conn->query($countSql)->fetch_assoc();
        }

        $totalCount = intval($countResult['total'] ?? 0);
        $totalPages = ceil($totalCount / $limit);

        // Get announcements
        $sql = "SELECT
                    pa.announcementId,
                    pa.packageId,
                    pa.title,
                    pa.targetType,
                    pa.targetStartDate,
                    pa.targetEndDate,
                    pa.status,
                    pa.publishedAt,
                    pa.createdAt,
                    p.packageName,
                    (@rownum := @rownum + 1) as rowNum
                FROM product_announcements pa
                LEFT JOIN packages p ON pa.packageId = p.packageId
                CROSS JOIN (SELECT @rownum := ?) r
                WHERE $whereClause
                ORDER BY pa.createdAt DESC
                LIMIT ? OFFSET ?";

        $allParams = array_merge([$offset], $params, [$limit, $offset]);
        $allTypes = 'i' . $types . 'ii';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $stmt->close();

        send_success_response([
            'announcements' => $announcements,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);

    } catch (Exception $e) {
        error_log("getProductAnnouncements error: " . $e->getMessage());
        send_error_response('Failed to load announcements: ' . $e->getMessage());
    }
}

/**
 * 상품 공지사항 상세 조회
 */
function getProductAnnouncementDetail($conn, $input) {
    try {
        $announcementId = intval($input['announcementId'] ?? $input['id'] ?? 0);

        if ($announcementId <= 0) {
            send_error_response('Announcement ID is required');
            return;
        }

        $sql = "SELECT
                    pa.*,
                    p.packageName,
                    p.thumbnailUrl as packageThumbnail
                FROM product_announcements pa
                LEFT JOIN packages p ON pa.packageId = p.packageId
                WHERE pa.announcementId = ? AND pa.status != 'deleted'";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $announcementId);
        $stmt->execute();
        $result = $stmt->get_result();
        $announcement = $result->fetch_assoc();
        $stmt->close();

        if (!$announcement) {
            send_error_response('Announcement not found', 404);
            return;
        }

        // Get target customers count
        $targetCount = getAnnouncementTargetCount($conn, $announcement);

        send_success_response([
            'announcement' => $announcement,
            'targetCount' => $targetCount
        ]);

    } catch (Exception $e) {
        error_log("getProductAnnouncementDetail error: " . $e->getMessage());
        send_error_response('Failed to load announcement detail: ' . $e->getMessage());
    }
}

/**
 * 공지 대상 고객 수 계산
 */
function getAnnouncementTargetCount($conn, $announcement) {
    try {
        $packageId = intval($announcement['packageId']);
        $targetType = $announcement['targetType'];
        $startDate = $announcement['targetStartDate'] ?? null;
        $endDate = $announcement['targetEndDate'] ?? null;

        $sql = "SELECT COUNT(*) as count
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE b.packageId = ? AND b.bookingStatus NOT IN ('cancelled','draft')";

        $params = [$packageId];
        $types = 'i';

        if ($targetType === 'specific_date' && $startDate) {
            // 출발일이 선택한 날짜와 일치하는 예약
            $sql .= " AND DATE(b.departureDate) = ?";
            $params[] = $startDate;
            $types .= 's';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return intval($result['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 상품 공지사항 생성
 */
function createProductAnnouncement($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $targetType = trim($input['targetType'] ?? 'all_purchasers');
        $targetStartDate = trim($input['targetStartDate'] ?? '');
        $targetEndDate = trim($input['targetEndDate'] ?? '');
        $adminId = intval($input['adminId'] ?? 0);

        if ($packageId <= 0) {
            send_error_response('Package ID is required');
            return;
        }
        if ($title === '') {
            send_error_response('Title is required');
            return;
        }
        if ($content === '') {
            send_error_response('Content is required');
            return;
        }

        // Validate targetType
        $validTargetTypes = ['all_purchasers', 'specific_date', 'specific_booking'];
        if (!in_array($targetType, $validTargetTypes)) {
            $targetType = 'all_purchasers';
        }

        $startDateValue = ($targetType === 'specific_date' && $targetStartDate !== '') ? $targetStartDate : null;
        $endDateValue = ($targetType === 'specific_date' && $targetEndDate !== '') ? $targetEndDate : null;

        $sql = "INSERT INTO product_announcements (packageId, adminId, title, content, targetType, targetStartDate, targetEndDate, status, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iisssss', $packageId, $adminId, $title, $content, $targetType, $startDateValue, $endDateValue);
        $stmt->execute();

        $announcementId = $conn->insert_id;
        $stmt->close();

        send_success_response([
            'announcementId' => $announcementId
        ], 'Announcement created successfully');

    } catch (Exception $e) {
        error_log("createProductAnnouncement error: " . $e->getMessage());
        send_error_response('Failed to create announcement: ' . $e->getMessage());
    }
}

/**
 * 상품 공지사항 수정
 */
function updateProductAnnouncement($conn, $input) {
    try {
        $announcementId = intval($input['announcementId'] ?? $input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $targetType = trim($input['targetType'] ?? '');
        $targetDate = trim($input['targetDate'] ?? '');

        if ($announcementId <= 0) {
            send_error_response('Announcement ID is required');
            return;
        }

        // Check if announcement exists and is not published
        $checkSql = "SELECT status FROM product_announcements WHERE announcementId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $announcementId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            send_error_response('Announcement not found', 404);
            return;
        }

        if ($existing['status'] === 'published') {
            send_error_response('Cannot edit published announcement');
            return;
        }

        $updates = [];
        $params = [];
        $types = '';

        if ($title !== '') {
            $updates[] = "title = ?";
            $params[] = $title;
            $types .= 's';
        }
        if ($content !== '') {
            $updates[] = "content = ?";
            $params[] = $content;
            $types .= 's';
        }
        if ($targetType !== '') {
            $updates[] = "targetType = ?";
            $params[] = $targetType;
            $types .= 's';

            if ($targetType === 'specific_date' && $targetDate !== '') {
                $updates[] = "targetDate = ?";
                $params[] = $targetDate;
                $types .= 's';
            } else if ($targetType !== 'specific_date') {
                $updates[] = "targetDate = NULL";
            }
        }

        if (empty($updates)) {
            send_error_response('No fields to update');
            return;
        }

        $params[] = $announcementId;
        $types .= 'i';

        $sql = "UPDATE product_announcements SET " . implode(', ', $updates) . " WHERE announcementId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        send_success_response(null, 'Announcement updated successfully');

    } catch (Exception $e) {
        error_log("updateProductAnnouncement error: " . $e->getMessage());
        send_error_response('Failed to update announcement: ' . $e->getMessage());
    }
}

/**
 * 상품 공지사항 삭제
 */
function deleteProductAnnouncement($conn, $input) {
    try {
        $announcementId = intval($input['announcementId'] ?? $input['id'] ?? 0);

        if ($announcementId <= 0) {
            send_error_response('Announcement ID is required');
            return;
        }

        $sql = "UPDATE product_announcements SET status = 'deleted', deletedAt = NOW() WHERE announcementId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $announcementId);
        $stmt->execute();
        $stmt->close();

        send_success_response(null, 'Announcement deleted successfully');

    } catch (Exception $e) {
        error_log("deleteProductAnnouncement error: " . $e->getMessage());
        send_error_response('Failed to delete announcement: ' . $e->getMessage());
    }
}

/**
 * 상품 공지사항 발행 (고객에게 전송)
 */
function publishProductAnnouncement($conn, $input) {
    try {
        $announcementId = intval($input['announcementId'] ?? $input['id'] ?? 0);

        if ($announcementId <= 0) {
            send_error_response('Announcement ID is required');
            return;
        }

        // Get announcement details
        $sql = "SELECT * FROM product_announcements WHERE announcementId = ? AND status = 'draft'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $announcementId);
        $stmt->execute();
        $announcement = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$announcement) {
            send_error_response('Announcement not found or already published', 404);
            return;
        }

        // Get target customers
        $packageId = intval($announcement['packageId']);
        $targetType = $announcement['targetType'];
        $startDate = $announcement['targetStartDate'] ?? null;
        $endDate = $announcement['targetEndDate'] ?? null;

        $customerSql = "SELECT DISTINCT b.bookingId, b.transactNo, b.accountId, b.departureDate
                        FROM bookings b
                        LEFT JOIN packages p ON b.packageId = p.packageId
                        WHERE b.packageId = ? AND b.bookingStatus NOT IN ('cancelled','draft')
                        AND b.accountId IS NOT NULL AND b.accountId > 0";

        $params = [$packageId];
        $types = 'i';

        if ($targetType === 'specific_date' && $startDate) {
            // 출발일이 선택한 날짜와 일치하는 예약
            $customerSql .= " AND DATE(b.departureDate) = ?";
            $params[] = $startDate;
            $types .= 's';
        }

        $customerStmt = $conn->prepare($customerSql);
        $customerStmt->bind_param($types, ...$params);
        $customerStmt->execute();
        $customers = $customerStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $customerStmt->close();

        // Update announcement status to published
        $updateSql = "UPDATE product_announcements SET status = 'published', publishedAt = NOW() WHERE announcementId = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $announcementId);
        $updateStmt->execute();
        $updateStmt->close();

        // Create notifications for each target customer
        $notificationCount = 0;
        $announcementTitle = $announcement['title'] ?? 'Product Announcement';
        $announcementContent = $announcement['content'] ?? '';

        // Get package name
        $packageName = '';
        $pkgStmt = $conn->prepare("SELECT packageName FROM packages WHERE packageId = ?");
        if ($pkgStmt) {
            $pkgStmt->bind_param('i', $packageId);
            $pkgStmt->execute();
            $pkgResult = $pkgStmt->get_result()->fetch_assoc();
            $packageName = $pkgResult['packageName'] ?? '';
            $pkgStmt->close();
        }

        // Check if notifications table has necessary columns
        $notifCols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM notifications");
        while ($colRes && ($row = $colRes->fetch_assoc())) {
            $notifCols[strtolower($row['Field'])] = $row['Field'];
        }
        $hasData = isset($notifCols['data']);
        $hasRelatedId = isset($notifCols['relatedid']);
        $hasCategory = isset($notifCols['category']);

        foreach ($customers as $customer) {
            $accountId = intval($customer['accountId'] ?? 0);
            if ($accountId <= 0) continue;

            $bookingId = $customer['bookingId'] ?? '';
            $departureDate = $customer['departureDate'] ?? '';

            // Build notification data
            $notifData = [
                'announcementId' => $announcementId,
                'packageId' => $packageId,
                'packageName' => $packageName,
                'bookingId' => $bookingId,
                'departureDate' => $departureDate,
                'statusKey' => 'product_announcement'
            ];

            $actionUrl = $bookingId ? "reservation-detail.php?id=" . rawurlencode($bookingId) : "";

            // Insert notification
            $fields = ["accountId", "notificationType", "title", "message", "priority", "isRead", "actionUrl", "createdAt"];
            $placeholders = ["?", "?", "?", "?", "?", "0", "?", "NOW()"];
            $values = [$accountId, 'booking', $announcementTitle, $announcementContent, 'high', $actionUrl];
            $bindTypes = "isssss";

            if ($hasCategory) {
                $fields[] = "category";
                $placeholders[] = "?";
                $values[] = 'reservation_schedule';
                $bindTypes .= "s";
            }

            if ($hasData) {
                $fields[] = "data";
                $placeholders[] = "?";
                $values[] = json_encode($notifData, JSON_UNESCAPED_UNICODE);
                $bindTypes .= "s";
            }

            if ($hasRelatedId) {
                $fields[] = "relatedId";
                $placeholders[] = "?";
                $values[] = $bookingId;
                $bindTypes .= "s";
            }

            $insertSql = "INSERT INTO notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param($bindTypes, ...$values);
                if ($insertStmt->execute()) {
                    $notificationCount++;
                }
                $insertStmt->close();
            }
        }

        send_success_response([
            'published' => true,
            'targetCount' => count($customers),
            'notificationsSent' => $notificationCount
        ], 'Announcement published successfully to ' . count($customers) . ' customers');

    } catch (Exception $e) {
        error_log("publishProductAnnouncement error: " . $e->getMessage());
        send_error_response('Failed to publish announcement: ' . $e->getMessage());
    }
}

/**
 * 공지 대상 고객 수 API
 */
function getAnnouncementTargetCountApi($conn, $input) {
    try {
        $packageId = intval($input['packageId'] ?? 0);
        $targetType = trim($input['targetType'] ?? 'all_purchasers');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        if ($packageId <= 0) {
            send_success_response(['count' => 0]);
            return;
        }

        $sql = "SELECT COUNT(*) as count
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE b.packageId = ? AND b.bookingStatus NOT IN ('cancelled','draft')";

        $params = [$packageId];
        $types = 'i';

        if ($targetType === 'specific_date' && $startDate !== '') {
            // 출발일이 선택한 날짜와 일치하는 예약
            $sql .= " AND DATE(b.departureDate) = ?";
            $params[] = $startDate;
            $types .= 's';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        send_success_response(['count' => intval($result['count'] ?? 0)]);

    } catch (Exception $e) {
        error_log("getAnnouncementTargetCountApi error: " . $e->getMessage());
        send_success_response(['count' => 0]);
    }
}

// ============================================
// 이메일 알림 로그 함수들
// ============================================

/**
 * 이메일 알림 로그 목록 조회
 */
function getEmailNotificationLogs($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 15;
        $offset = ($page - 1) * $limit;

        // 필터 파라미터
        $search = trim($input['search'] ?? '');
        $status = trim($input['status'] ?? '');
        $type = trim($input['type'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        // WHERE 조건 빌드
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = "(e.recipientEmail LIKE ? OR e.bookingId LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($status !== '' && in_array($status, ['sent', 'failed'])) {
            $where[] = "e.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if ($type !== '') {
            // 타입 필터는 prefix 매칭 (날짜 접미사 제거 대응)
            $where[] = "e.notificationType LIKE ?";
            $params[] = $type . '%';
            $types .= 's';
        }

        if ($startDate !== '') {
            $where[] = "DATE(e.sentAt) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $where[] = "DATE(e.sentAt) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // 총 개수 조회
        $countSql = "SELECT COUNT(*) as total FROM email_notification_logs e $whereClause";
        $countStmt = $conn->prepare($countSql);
        if ($types !== '' && count($params) > 0) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // 데이터 조회
        $sql = "
            SELECT
                e.id,
                e.bookingId,
                e.notificationType,
                e.recipientEmail,
                e.status,
                e.sentAt,
                e.errorMessage
            FROM email_notification_logs e
            $whereClause
            ORDER BY e.sentAt DESC
            LIMIT ?, ?
        ";

        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        $rowNum = $totalCount - $offset;

        while ($row = $result->fetch_assoc()) {
            $logs[] = [
                'rowNum' => $rowNum--,
                'id' => $row['id'],
                'bookingId' => $row['bookingId'],
                'notificationType' => $row['notificationType'],
                'recipientEmail' => $row['recipientEmail'],
                'status' => $row['status'],
                'sentAt' => $row['sentAt'],
                'errorMessage' => $row['errorMessage']
            ];
        }
        $stmt->close();

        send_success_response([
            'logs' => $logs,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => intval($totalCount),
                'limit' => $limit
            ]
        ]);

    } catch (Exception $e) {
        error_log("getEmailNotificationLogs error: " . $e->getMessage());
        send_error_response('Failed to load email notification logs: ' . $e->getMessage());
    }
}

/**
 * 이메일 알림 통계 조회
 */
function getEmailNotificationStats($conn) {
    try {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM email_notification_logs
        ";
        $result = $conn->query($sql);
        $stats = $result->fetch_assoc();

        send_success_response([
            'total' => intval($stats['total'] ?? 0),
            'sent' => intval($stats['sent'] ?? 0),
            'failed' => intval($stats['failed'] ?? 0)
        ]);

    } catch (Exception $e) {
        error_log("getEmailNotificationStats error: " . $e->getMessage());
        send_error_response('Failed to load email notification stats: ' . $e->getMessage());
    }
}

/**
 * 이메일 알림 로그 CSV 다운로드
 */
function exportEmailNotificationLogsCsv($conn, $input) {
    try {
        // 필터 파라미터
        $search = trim($input['search'] ?? '');
        $status = trim($input['status'] ?? '');
        $type = trim($input['type'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        // WHERE 조건 빌드
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = "(e.recipientEmail LIKE ? OR e.bookingId LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($status !== '' && in_array($status, ['sent', 'failed'])) {
            $where[] = "e.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if ($type !== '') {
            $where[] = "e.notificationType LIKE ?";
            $params[] = $type . '%';
            $types .= 's';
        }

        if ($startDate !== '') {
            $where[] = "DATE(e.sentAt) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $where[] = "DATE(e.sentAt) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                e.bookingId,
                e.notificationType,
                e.recipientEmail,
                e.status,
                e.sentAt,
                e.errorMessage
            FROM email_notification_logs e
            $whereClause
            ORDER BY e.sentAt DESC
            LIMIT 10000
        ";

        $stmt = $conn->prepare($sql);
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // CSV 출력
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = 'email_notification_logs_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');

        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // 헤더
        fputcsv($output, ['Booking ID', 'Notification Type', 'Recipient Email', 'Status', 'Sent At', 'Error Message']);

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['bookingId'],
                $row['notificationType'],
                $row['recipientEmail'],
                $row['status'] === 'sent' ? 'Sent' : 'Failed',
                $row['sentAt'],
                $row['errorMessage'] ?? ''
            ]);
        }

        fclose($output);
        $stmt->close();
        exit;

    } catch (Exception $e) {
        error_log("exportEmailNotificationLogsCsv error: " . $e->getMessage());
        http_response_code(500);
        echo "Error exporting CSV: " . $e->getMessage();
        exit;
    }
}

/**
 * 관리자 로그인 이력 조회
 */
function getAdminLoginHistory($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        // 필터 파라미터
        $search = trim($input['search'] ?? '');
        $loginStatus = trim($input['loginStatus'] ?? '');
        $accountType = trim($input['accountType'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        // WHERE 조건 빌드
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = "(h.email LIKE ? OR h.ip_address LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($loginStatus !== '' && in_array($loginStatus, ['success', 'failed'])) {
            $where[] = "h.login_status = ?";
            $params[] = $loginStatus;
            $types .= 's';
        }

        if ($accountType !== '' && in_array($accountType, ['admin_ph', 'admin_kr', 'agent', 'guide', 'employee'])) {
            $where[] = "h.account_type = ?";
            $params[] = $accountType;
            $types .= 's';
        }

        if ($startDate !== '') {
            $where[] = "DATE(h.created_at) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $where[] = "DATE(h.created_at) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // 총 개수 조회
        $countSql = "SELECT COUNT(*) as total FROM admin_login_history h $whereClause";
        $countStmt = $conn->prepare($countSql);
        if ($types !== '' && count($params) > 0) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // 데이터 조회
        $sql = "
            SELECT
                h.id,
                h.account_id,
                h.email,
                h.account_type,
                h.login_status,
                h.failure_reason,
                h.ip_address,
                h.user_agent,
                h.created_at
            FROM admin_login_history h
            $whereClause
            ORDER BY h.created_at DESC
            LIMIT ?, ?
        ";

        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $histories = [];
        $rowNum = $totalCount - $offset;
        $phTimezone = new DateTimeZone('Asia/Manila');
        $utcTimezone = new DateTimeZone('UTC');

        while ($row = $result->fetch_assoc()) {
            // UTC 시간을 필리핀 시간으로 변환
            $createdAtPh = $row['created_at'];
            if ($createdAtPh) {
                try {
                    $dt = new DateTime($row['created_at'], $utcTimezone);
                    $dt->setTimezone($phTimezone);
                    $createdAtPh = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // 변환 실패 시 원본 유지
                }
            }

            $histories[] = [
                'rowNum' => $rowNum--,
                'id' => $row['id'],
                'accountId' => $row['account_id'],
                'email' => $row['email'],
                'accountType' => $row['account_type'],
                'accountTypeLabel' => getAccountTypeLabel($row['account_type']),
                'loginStatus' => $row['login_status'],
                'loginStatusLabel' => $row['login_status'] === 'success' ? 'Success' : 'Failed',
                'failureReason' => $row['failure_reason'],
                'ipAddress' => $row['ip_address'],
                'userAgent' => $row['user_agent'],
                'createdAt' => $createdAtPh
            ];
        }
        $stmt->close();

        send_success_response([
            'histories' => $histories,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => intval($totalCount),
                'limit' => $limit
            ]
        ]);

    } catch (Exception $e) {
        error_log("getAdminLoginHistory error: " . $e->getMessage());
        send_error_response('Failed to load login history: ' . $e->getMessage());
    }
}

/**
 * 계정 유형 라벨 반환
 */
function getAccountTypeLabel($type) {
    switch ($type) {
        case 'admin': return 'Admin';
        case 'agent': return 'Agent';
        case 'guide': return 'Guide';
        case 'employee': return 'CS Staff';
        default: return $type ?? '-';
    }
}

/**
 * 관리자 로그인 이력 CSV 다운로드
 */
function exportAdminLoginHistoryCsv($conn, $input) {
    try {
        // 필터 파라미터
        $search = trim($input['search'] ?? '');
        $loginStatus = trim($input['loginStatus'] ?? '');
        $accountType = trim($input['accountType'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        // WHERE 조건 빌드
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = "(h.email LIKE ? OR h.ip_address LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($loginStatus !== '' && in_array($loginStatus, ['success', 'failed'])) {
            $where[] = "h.login_status = ?";
            $params[] = $loginStatus;
            $types .= 's';
        }

        if ($accountType !== '' && in_array($accountType, ['admin_ph', 'admin_kr', 'agent', 'guide', 'employee'])) {
            $where[] = "h.account_type = ?";
            $params[] = $accountType;
            $types .= 's';
        }

        if ($startDate !== '') {
            $where[] = "DATE(h.created_at) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $where[] = "DATE(h.created_at) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                h.email,
                h.account_type,
                h.login_status,
                h.failure_reason,
                h.ip_address,
                h.created_at
            FROM admin_login_history h
            $whereClause
            ORDER BY h.created_at DESC
            LIMIT 10000
        ";

        $stmt = $conn->prepare($sql);
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // CSV 출력
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = 'admin_login_history_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');

        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // 헤더
        fputcsv($output, ['Email', 'Account Type', 'Status', 'Failure Reason', 'IP Address', 'Login Time (PHT)']);

        $phTimezone = new DateTimeZone('Asia/Manila');
        $utcTimezone = new DateTimeZone('UTC');

        while ($row = $result->fetch_assoc()) {
            // UTC 시간을 필리핀 시간으로 변환
            $createdAtPh = $row['created_at'];
            if ($createdAtPh) {
                try {
                    $dt = new DateTime($row['created_at'], $utcTimezone);
                    $dt->setTimezone($phTimezone);
                    $createdAtPh = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // 변환 실패 시 원본 유지
                }
            }

            fputcsv($output, [
                $row['email'],
                getAccountTypeLabel($row['account_type']),
                $row['login_status'] === 'success' ? 'Success' : 'Failed',
                $row['failure_reason'] ?? '',
                $row['ip_address'],
                $createdAtPh
            ]);
        }

        fclose($output);
        $stmt->close();
        exit;

    } catch (Exception $e) {
        error_log("exportAdminLoginHistoryCsv error: " . $e->getMessage());
        http_response_code(500);
        echo "Error exporting CSV: " . $e->getMessage();
        exit;
    }
}

// ============================================
// Sight 관리 함수들
// ============================================

/**
 * 관광지 목록 조회
 */
function getSights($conn, $input) {
    try {
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $whereConditions = [];
        $params = [];
        $types = '';

        // 검색어 필터
        if (!empty($input['search'])) {
            $search = '%' . trim($input['search']) . '%';
            $whereConditions[] = "(sightName LIKE ? OR searchName LIKE ? OR sightAddress LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }

        // 상태 필터
        if (!empty($input['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $input['status'];
            $types .= 's';
        }

        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }

        // 총 개수 조회
        $countSql = "SELECT COUNT(*) as total FROM sights $whereClause";
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];

        // 데이터 조회
        $dataSql = "SELECT sightId, sightName, searchName, sightAddress, sightImage, status, createdAt
                    FROM sights $whereClause
                    ORDER BY createdAt DESC
                    LIMIT ? OFFSET ?";

        $dataParams = $params;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataTypes = $types . 'ii';

        $dataStmt = $conn->prepare($dataSql);
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        $sights = [];
        while ($row = $dataResult->fetch_assoc()) {
            $sights[] = [
                'sightId' => intval($row['sightId']),
                'sightName' => $row['sightName'],
                'searchName' => $row['searchName'],
                'sightAddress' => $row['sightAddress'],
                'sightImage' => $row['sightImage'],
                'status' => $row['status'],
                'createdAt' => $row['createdAt']
            ];
        }

        send_success_response([
            'sights' => $sights,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => intval($totalCount),
                'limit' => $limit
            ]
        ]);

    } catch (Exception $e) {
        error_log("getSights error: " . $e->getMessage());
        send_error_response('Failed to get sights: ' . $e->getMessage());
    }
}

/**
 * 관광지 상세 조회
 */
function getSightDetail($conn, $input) {
    try {
        $sightId = isset($input['sightId']) ? intval($input['sightId']) : 0;

        if ($sightId <= 0) {
            send_error_response('Sight ID is required');
        }

        $stmt = $conn->prepare("SELECT * FROM sights WHERE sightId = ?");
        $stmt->bind_param('i', $sightId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Sight not found', 404);
        }

        $sight = $result->fetch_assoc();
        $sight['sightId'] = intval($sight['sightId']);

        send_success_response(['sight' => $sight]);

    } catch (Exception $e) {
        error_log("getSightDetail error: " . $e->getMessage());
        send_error_response('Failed to get sight detail: ' . $e->getMessage());
    }
}

/**
 * 관광지 생성
 */
function createSight($conn, $input) {
    try {
        // 필수 필드 검증
        $sightName = trim($input['sightName'] ?? '');
        if (empty($sightName)) {
            send_error_response('Sight name is required');
        }

        $searchName = trim($input['searchName'] ?? '');
        $sightAddress = trim($input['sightAddress'] ?? '');
        $sightDescription = $input['sightDescription'] ?? '';
        $status = $input['status'] ?? 'active';

        // 이미지 업로드 처리
        $sightImage = null;
        if (isset($_FILES['sightImage']) && $_FILES['sightImage']['error'] === UPLOAD_ERR_OK) {
            $sightImage = uploadSightImage($_FILES['sightImage']);
        }

        $stmt = $conn->prepare("
            INSERT INTO sights (sightName, searchName, sightAddress, sightDescription, sightImage, status, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('ssssss', $sightName, $searchName, $sightAddress, $sightDescription, $sightImage, $status);

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert sight: ' . $stmt->error);
        }

        $sightId = $conn->insert_id;

        send_success_response([
            'sightId' => $sightId,
            'message' => 'Sight created successfully'
        ]);

    } catch (Exception $e) {
        error_log("createSight error: " . $e->getMessage());
        send_error_response('Failed to create sight: ' . $e->getMessage());
    }
}

/**
 * 관광지 수정
 */
function updateSight($conn, $input) {
    try {
        $sightId = isset($input['sightId']) ? intval($input['sightId']) : 0;

        if ($sightId <= 0) {
            send_error_response('Sight ID is required');
        }

        // 기존 데이터 확인
        $checkStmt = $conn->prepare("SELECT sightId, sightImage FROM sights WHERE sightId = ?");
        $checkStmt->bind_param('i', $sightId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if (!$existing) {
            send_error_response('Sight not found', 404);
        }

        // 동적 UPDATE 생성
        $updates = [];
        $values = [];
        $types = '';

        if (isset($input['sightName'])) {
            $updates[] = "sightName = ?";
            $values[] = trim($input['sightName']);
            $types .= 's';
        }

        if (isset($input['searchName'])) {
            $updates[] = "searchName = ?";
            $values[] = trim($input['searchName']);
            $types .= 's';
        }

        if (isset($input['sightAddress'])) {
            $updates[] = "sightAddress = ?";
            $values[] = trim($input['sightAddress']);
            $types .= 's';
        }

        if (isset($input['sightDescription'])) {
            $updates[] = "sightDescription = ?";
            $values[] = $input['sightDescription'];
            $types .= 's';
        }

        if (isset($input['status'])) {
            $updates[] = "status = ?";
            $values[] = $input['status'];
            $types .= 's';
        }

        // 이미지 업로드 처리
        if (isset($_FILES['sightImage']) && $_FILES['sightImage']['error'] === UPLOAD_ERR_OK) {
            $newImage = uploadSightImage($_FILES['sightImage']);
            if ($newImage) {
                $updates[] = "sightImage = ?";
                $values[] = $newImage;
                $types .= 's';

                // 기존 이미지 삭제
                if ($existing['sightImage']) {
                    $oldPath = __DIR__ . '/../../../uploads/sights/' . $existing['sightImage'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }
        }

        if (empty($updates)) {
            send_error_response('No fields to update');
        }

        $values[] = $sightId;
        $types .= 'i';

        $sql = "UPDATE sights SET " . implode(', ', $updates) . " WHERE sightId = ?";
        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $values);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update sight: ' . $stmt->error);
        }

        send_success_response(['message' => 'Sight updated successfully']);

    } catch (Exception $e) {
        error_log("updateSight error: " . $e->getMessage());
        send_error_response('Failed to update sight: ' . $e->getMessage());
    }
}

/**
 * 관광지 삭제
 */
function deleteSight($conn, $input) {
    try {
        $sightId = isset($input['sightId']) ? intval($input['sightId']) : 0;

        if ($sightId <= 0) {
            send_error_response('Sight ID is required');
        }

        // 기존 이미지 확인
        $checkStmt = $conn->prepare("SELECT sightImage FROM sights WHERE sightId = ?");
        $checkStmt->bind_param('i', $sightId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if (!$existing) {
            send_error_response('Sight not found', 404);
        }

        // 삭제
        $stmt = $conn->prepare("DELETE FROM sights WHERE sightId = ?");
        $stmt->bind_param('i', $sightId);

        if (!$stmt->execute()) {
            throw new Exception('Failed to delete sight: ' . $stmt->error);
        }

        // 이미지 파일 삭제
        if ($existing['sightImage']) {
            $imagePath = __DIR__ . '/../../../uploads/sights/' . $existing['sightImage'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        send_success_response(['message' => 'Sight deleted successfully']);

    } catch (Exception $e) {
        error_log("deleteSight error: " . $e->getMessage());
        send_error_response('Failed to delete sight: ' . $e->getMessage());
    }
}

/**
 * 관광지 이미지 업로드 헬퍼 함수
 */
function uploadSightImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WEBP');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Image size must be less than 5MB');
    }

    $uploadDir = __DIR__ . '/../../../uploads/sights/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'sight_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file');
    }

    return $filename;
}

// ============ 옵션 관리 함수들 (대분류/중분류/소분류) ============

/**
 * 대분류 목록 조회
 */
function getMainOptionCategories($conn) {
    $sql = "SELECT DISTINCT airline_name as main_category FROM airline_option_categories WHERE airline_name IS NOT NULL AND airline_name != '' ORDER BY airline_name";
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['main_category'];
    }
    send_success_response(['mainCategories' => $categories]);
}

/**
 * 대분류 생성
 */
function createMainOptionCategory($conn, $input) {
    $mainCategory = trim($input['mainCategory'] ?? '');

    if (empty($mainCategory)) {
        send_error_response('Main category name is required', 400);
        return;
    }

    // 중복 체크
    $checkSql = "SELECT DISTINCT airline_name FROM airline_option_categories WHERE airline_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $mainCategory);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        send_error_response('Main category already exists', 409);
        return;
    }
    $checkStmt->close();

    // 대분류만 생성 (빈 중분류로 placeholder 생성)
    $sql = "INSERT INTO airline_option_categories (airline_name, category_name, category_name_en, sort_order) VALUES (?, '', '', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $mainCategory);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response(['mainCategory' => $mainCategory], 'Main category created successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to create main category', 500);
    }
}

/**
 * 대분류 수정
 */
function updateMainOptionCategory($conn, $input) {
    $oldName = trim($input['oldName'] ?? '');
    $newName = trim($input['newName'] ?? '');

    if (empty($oldName) || empty($newName)) {
        send_error_response('Old and new names are required', 400);
        return;
    }

    $sql = "UPDATE airline_option_categories SET airline_name = ? WHERE airline_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $newName, $oldName);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Main category updated successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to update main category', 500);
    }
}

/**
 * 대분류 삭제 (하위 중분류/소분류 모두 삭제)
 */
function deleteMainOptionCategory($conn, $input) {
    $mainCategory = trim($input['mainCategory'] ?? '');

    if (empty($mainCategory)) {
        send_error_response('Main category name is required', 400);
        return;
    }

    // 해당 대분류의 모든 카테고리 ID 조회
    $catSql = "SELECT category_id FROM airline_option_categories WHERE airline_name = ?";
    $catStmt = $conn->prepare($catSql);
    $catStmt->bind_param('s', $mainCategory);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    $categoryIds = [];
    while ($row = $catResult->fetch_assoc()) {
        $categoryIds[] = $row['category_id'];
    }
    $catStmt->close();

    // 소분류 옵션 삭제
    if (!empty($categoryIds)) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $delOptSql = "DELETE FROM airline_options WHERE category_id IN ($placeholders)";
        $delOptStmt = $conn->prepare($delOptSql);
        $types = str_repeat('i', count($categoryIds));
        $delOptStmt->bind_param($types, ...$categoryIds);
        $delOptStmt->execute();
        $delOptStmt->close();
    }

    // 중분류 삭제
    $sql = "DELETE FROM airline_option_categories WHERE airline_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $mainCategory);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Main category deleted successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to delete main category', 500);
    }
}

/**
 * 대분류 복사 (중분류/소분류 포함)
 */
function copyMainOptionCategory($conn, $input) {
    $sourceCategory = trim($input['sourceCategory'] ?? '');
    $newName = trim($input['newName'] ?? '');

    if (empty($sourceCategory) || empty($newName)) {
        send_error_response('Source category and new name are required', 400);
        return;
    }

    if ($sourceCategory === $newName) {
        send_error_response('New name must be different from source', 400);
        return;
    }

    // 중복 체크
    $checkSql = "SELECT DISTINCT airline_name FROM airline_option_categories WHERE airline_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $newName);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        send_error_response('A category with this name already exists', 409);
        return;
    }
    $checkStmt->close();

    // 원본 대분류의 중분류 조회
    $catSql = "SELECT category_id, category_name, category_name_en, sort_order, is_active
               FROM airline_option_categories
               WHERE airline_name = ? AND category_name != ''
               ORDER BY sort_order, category_id";
    $catStmt = $conn->prepare($catSql);
    $catStmt->bind_param('s', $sourceCategory);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    $copiedCategories = 0;
    $copiedOptions = 0;

    while ($cat = $catResult->fetch_assoc()) {
        $oldCategoryId = $cat['category_id'];

        // 중분류 복사
        $insertCatSql = "INSERT INTO airline_option_categories (airline_name, category_name, category_name_en, sort_order, is_active) VALUES (?, ?, ?, ?, ?)";
        $insertCatStmt = $conn->prepare($insertCatSql);
        $insertCatStmt->bind_param('sssii', $newName, $cat['category_name'], $cat['category_name_en'], $cat['sort_order'], $cat['is_active']);
        $insertCatStmt->execute();
        $newCategoryId = $conn->insert_id;
        $insertCatStmt->close();
        $copiedCategories++;

        // 해당 중분류의 소분류(옵션) 조회 및 복사
        $optSql = "SELECT option_name, option_name_en, price, sort_order, is_active
                   FROM airline_options
                   WHERE category_id = ?
                   ORDER BY sort_order, option_id";
        $optStmt = $conn->prepare($optSql);
        $optStmt->bind_param('i', $oldCategoryId);
        $optStmt->execute();
        $optResult = $optStmt->get_result();

        while ($opt = $optResult->fetch_assoc()) {
            $insertOptSql = "INSERT INTO airline_options (category_id, option_name, option_name_en, price, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            $insertOptStmt = $conn->prepare($insertOptSql);
            $insertOptStmt->bind_param('issdii', $newCategoryId, $opt['option_name'], $opt['option_name_en'], $opt['price'], $opt['sort_order'], $opt['is_active']);
            $insertOptStmt->execute();
            $insertOptStmt->close();
            $copiedOptions++;
        }
        $optStmt->close();
    }
    $catStmt->close();

    send_success_response([
        'newName' => $newName,
        'copiedCategories' => $copiedCategories,
        'copiedOptions' => $copiedOptions
    ], "Copied $copiedCategories categories and $copiedOptions options to '$newName'");
}

/**
 * 대분류 목록 조회 (하위 호환용 - getMainOptionCategories와 동일)
 */
function getAirlineList($conn) {
    $sql = "SELECT DISTINCT airline_name as main_category FROM airline_option_categories WHERE airline_name IS NOT NULL AND airline_name != '' ORDER BY airline_name";
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['main_category'];
    }
    send_success_response(['airlines' => $categories, 'mainCategories' => $categories]);
}

/**
 * 특정 대분류의 중분류/소분류 조회
 */
function getAirlineOptions($conn, $input) {
    $mainCategory = $input['mainCategory'] ?? $input['airlineName'] ?? '';

    if (empty($mainCategory)) {
        send_error_response('Main category is required', 400);
        return;
    }

    // 중분류 조회
    $catSql = "SELECT category_id, category_name, category_name_en, sort_order, is_active
               FROM airline_option_categories
               WHERE airline_name = ? AND category_name != ''
               ORDER BY sort_order, category_id";
    $catStmt = $conn->prepare($catSql);
    $catStmt->bind_param('s', $mainCategory);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    $categories = [];
    while ($cat = $catResult->fetch_assoc()) {
        // 각 카테고리의 옵션 조회
        $optSql = "SELECT option_id, option_name, option_name_en, price, sort_order, is_active
                   FROM airline_options
                   WHERE category_id = ?
                   ORDER BY sort_order, option_id";
        $optStmt = $conn->prepare($optSql);
        $optStmt->bind_param('i', $cat['category_id']);
        $optStmt->execute();
        $optResult = $optStmt->get_result();

        $options = [];
        while ($opt = $optResult->fetch_assoc()) {
            $opt['price'] = floatval($opt['price']);
            $opt['is_active'] = (bool)$opt['is_active'];
            $options[] = $opt;
        }
        $optStmt->close();

        $cat['is_active'] = (bool)$cat['is_active'];
        $cat['options'] = $options;
        $categories[] = $cat;
    }
    $catStmt->close();

    send_success_response(['categories' => $categories]);
}

/**
 * 중분류 카테고리 생성 (특정 대분류 아래에)
 */
function createOptionCategory($conn, $input) {
    $mainCategory = $input['mainCategory'] ?? $input['airlineName'] ?? '';
    $categoryName = $input['categoryName'] ?? '';
    $categoryNameEn = $input['categoryNameEn'] ?? '';

    if (empty($mainCategory) || empty($categoryName)) {
        send_error_response('Main category and category name are required', 400);
        return;
    }

    // 중복 체크 (같은 대분류 내에서)
    $checkSql = "SELECT category_id FROM airline_option_categories WHERE airline_name = ? AND category_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ss', $mainCategory, $categoryName);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        send_error_response('Category already exists in this main category', 409);
        return;
    }
    $checkStmt->close();

    // 정렬 순서 (같은 대분류 내에서)
    $orderSql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM airline_option_categories WHERE airline_name = ?";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param('s', $mainCategory);
    $orderStmt->execute();
    $sortOrder = $orderStmt->get_result()->fetch_assoc()['next_order'];
    $orderStmt->close();

    $sql = "INSERT INTO airline_option_categories (airline_name, category_name, category_name_en, sort_order) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $mainCategory, $categoryName, $categoryNameEn, $sortOrder);

    if ($stmt->execute()) {
        $categoryId = $conn->insert_id;
        $stmt->close();
        send_success_response(['categoryId' => $categoryId], 'Category created successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to create category', 500);
    }
}

/**
 * 옵션 카테고리 수정
 */
function updateOptionCategory($conn, $input) {
    $categoryId = intval($input['categoryId'] ?? 0);
    $categoryName = $input['categoryName'] ?? '';
    $categoryNameEn = $input['categoryNameEn'] ?? '';

    if ($categoryId <= 0 || empty($categoryName)) {
        send_error_response('Category ID and name are required', 400);
        return;
    }

    $sql = "UPDATE airline_option_categories SET category_name = ?, category_name_en = ?, updated_at = NOW() WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $categoryName, $categoryNameEn, $categoryId);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Category updated successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to update category', 500);
    }
}

/**
 * 옵션 카테고리 삭제
 */
function deleteOptionCategory($conn, $input) {
    $categoryId = intval($input['categoryId'] ?? 0);

    if ($categoryId <= 0) {
        send_error_response('Category ID is required', 400);
        return;
    }

    $sql = "DELETE FROM airline_option_categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $categoryId);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Category deleted successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to delete category', 500);
    }
}

/**
 * 옵션 생성
 */
function createAirlineOption($conn, $input) {
    $categoryId = intval($input['categoryId'] ?? 0);
    $optionName = $input['optionName'] ?? '';
    $optionNameEn = $input['optionNameEn'] ?? '';
    $price = floatval($input['price'] ?? 0);

    if ($categoryId <= 0 || empty($optionName)) {
        send_error_response('Category ID and option name are required', 400);
        return;
    }

    // 정렬 순서
    $orderSql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM airline_options WHERE category_id = ?";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param('i', $categoryId);
    $orderStmt->execute();
    $sortOrder = $orderStmt->get_result()->fetch_assoc()['next_order'];
    $orderStmt->close();

    $sql = "INSERT INTO airline_options (category_id, option_name, option_name_en, price, sort_order) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issdi', $categoryId, $optionName, $optionNameEn, $price, $sortOrder);

    if ($stmt->execute()) {
        $optionId = $conn->insert_id;
        $stmt->close();
        send_success_response(['optionId' => $optionId], 'Option created successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to create option', 500);
    }
}

/**
 * 옵션 수정
 */
function updateAirlineOption($conn, $input) {
    $optionId = intval($input['optionId'] ?? 0);
    $optionName = $input['optionName'] ?? '';
    $optionNameEn = $input['optionNameEn'] ?? '';
    $price = floatval($input['price'] ?? 0);

    if ($optionId <= 0 || empty($optionName)) {
        send_error_response('Option ID and name are required', 400);
        return;
    }

    $sql = "UPDATE airline_options SET option_name = ?, option_name_en = ?, price = ?, updated_at = NOW() WHERE option_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssdi', $optionName, $optionNameEn, $price, $optionId);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Option updated successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to update option', 500);
    }
}

/**
 * 옵션 삭제
 */
function deleteAirlineOption($conn, $input) {
    $optionId = intval($input['optionId'] ?? 0);

    if ($optionId <= 0) {
        send_error_response('Option ID is required', 400);
        return;
    }

    $sql = "DELETE FROM airline_options WHERE option_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $optionId);

    if ($stmt->execute()) {
        $stmt->close();
        send_success_response([], 'Option deleted successfully');
    } else {
        $stmt->close();
        send_error_response('Failed to delete option', 500);
    }
}

// ========================================
// 예약 상태 변경 히스토리 관련 함수들
// ========================================

/**
 * pending_update 상태에서 변경 요청 생성 시, 올바른 originalStatus를 조회
 * pending_update/check_reject이 아닌 실제 의미 있는 상태를 반환
 */
function __get_true_original_status($conn, $bookingId, $currentStatus) {
    if (!in_array($currentStatus, ['pending_update', 'check_reject'])) {
        return $currentStatus;
    }

    // 가장 오래된 pending 요청 중 유효한 originalStatus 조회
    $stmt = $conn->prepare("SELECT originalStatus FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' AND originalStatus IS NOT NULL AND originalStatus NOT IN ('pending_update', 'check_reject') ORDER BY requestedAt ASC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['originalStatus'])) {
            return $row['originalStatus'];
        }
    }

    // pending 요청에 없으면, 최근 approved/rejected 요청에서 조회
    $stmt2 = $conn->prepare("SELECT originalStatus FROM booking_change_requests WHERE bookingId = ? AND originalStatus IS NOT NULL AND originalStatus NOT IN ('pending_update', 'check_reject') ORDER BY requestedAt DESC LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('s', $bookingId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $stmt2->close();
        if ($row2 && !empty($row2['originalStatus'])) {
            return $row2['originalStatus'];
        }
    }

    return 'confirmed'; // 최종 fallback
}

/**
 * 승인 후 복원할 상태를 결정
 * - originalStatus가 pending_update/check_reject이면 올바른 상태 조회
 * - 현재 승인하는 요청 외에 남은 pending 요청이 있으면 pending_update 유지
 */
/**
 * 변경 요청 승인 시 같은 bookingId + 같은 changeType의 오래된 pending 요청을 자동 reject
 * (최신 요청이 승인되면 이전 요청은 의미 없으므로 superseded 처리)
 */
function __reject_superseded_change_requests($conn, $bookingId, $approvedRequestId, $changeType, $processedBy = 'system') {
    try {
        $stmt = $conn->prepare(
            "UPDATE booking_change_requests SET status = 'rejected', rejectReason = 'Automatically superseded by newer approved request', processedBy = ?, processedAt = NOW() WHERE bookingId = ? AND changeType = ? AND status = 'pending' AND id != ?"
        );
        if ($stmt) {
            $stmt->bind_param('sssi', $processedBy, $bookingId, $changeType, $approvedRequestId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                error_log("Auto-rejected {$affected} superseded '{$changeType}' change request(s) for booking {$bookingId}");
            }
            return $affected;
        }
    } catch (Throwable $e) {
        error_log("__reject_superseded_change_requests error: " . $e->getMessage());
    }
    return 0;
}

function __resolve_post_approval_status($conn, $bookingId, $changeRequestId, $originalStatus) {
    $resolvedStatus = $originalStatus;
    if (in_array($resolvedStatus, ['pending_update', 'check_reject'])) {
        $resolvedStatus = __get_true_original_status($conn, $bookingId, $resolvedStatus);
    }

    // 현재 승인하는 요청 외에 남은 pending 요청 확인
    $remainingStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' AND id != ?");
    if ($remainingStmt) {
        $remainingStmt->bind_param('si', $bookingId, $changeRequestId);
        $remainingStmt->execute();
        $remainingResult = $remainingStmt->get_result();
        $remainingRow = $remainingResult->fetch_assoc();
        $remainingStmt->close();
        if (($remainingRow['cnt'] ?? 0) > 0) {
            return 'pending_update';
        }
    }

    return $resolvedStatus;
}

/**
 * 상태 변경 히스토리 테이블 확인 및 생성
 */
function __ensure_booking_status_history_table($conn) {
    static $checked = false;
    if ($checked) return true;
    $checked = true;

    try {
        $result = $conn->query("SHOW TABLES LIKE 'booking_status_history'");
        if ($result && $result->num_rows === 0) {
            $createResult = $conn->query("
                CREATE TABLE IF NOT EXISTS `booking_status_history` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `bookingId` VARCHAR(50) NOT NULL,
                    `previousStatus` VARCHAR(50) NULL,
                    `newStatus` VARCHAR(50) NOT NULL,
                    `changedBy` VARCHAR(100) NULL,
                    `changedByType` VARCHAR(20) NULL,
                    `changedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_bookingId` (`bookingId`),
                    INDEX `idx_changedAt` (`changedAt`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return $createResult !== false;
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * 상태 변경 로그 저장
 * @param $overrideChangedBy - 지정 시 세션 대신 이 값 사용 (자동취소 등)
 * @param $overrideChangedByType - 지정 시 세션 대신 이 값 사용
 * @param $changeReason - 상태 변경 사유 (선택사항)
 */
function __log_booking_status_change($conn, $bookingId, $previousStatus, $newStatus, $overrideChangedBy = null, $overrideChangedByType = null, $changeReason = null) {
    __ensure_booking_status_history_table($conn);

    try {
        // override가 지정된 경우 (자동취소 등)
        if ($overrideChangedBy !== null || $overrideChangedByType !== null) {
            $changedBy = $overrideChangedBy;
            $changedByType = $overrideChangedByType ?? 'system';
        } else {
            // 현재 로그인한 사용자 정보 가져오기
            $changedBy = null;
            $changedByType = 'system';

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (isset($_SESSION['admin_accountId'])) {
                $changedByType = 'admin';
                $adminAccountId = $_SESSION['admin_accountId'];
                $stmt = $conn->prepare("SELECT emailAddress FROM accounts WHERE accountId = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $adminAccountId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $changedBy = $row['emailAddress'];
                    }
                    $stmt->close();
                }
            } elseif (isset($_SESSION['agent_accountId'])) {
                $changedByType = 'agent';
                $agentAccountId = $_SESSION['agent_accountId'];
                $stmt = $conn->prepare("SELECT emailAddress FROM accounts WHERE accountId = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $agentAccountId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $changedBy = $row['emailAddress'];
                    }
                    $stmt->close();
                }
            }
        }

        // 필리핀 시간(UTC+8)으로 저장
        $phpTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $changedAt = $phpTime->format('Y-m-d H:i:s');

        // changeReason 컬럼 존재 여부 확인 후 INSERT
        $hasChangeReason = __table_has_column($conn, 'booking_status_history', 'changeReason');
        if ($hasChangeReason) {
            $stmt = $conn->prepare("
                INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changeReason, changedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param('sssssss', $bookingId, $previousStatus, $newStatus, $changedBy, $changedByType, $changeReason, $changedAt);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param('ssssss', $bookingId, $previousStatus, $newStatus, $changedBy, $changedByType, $changedAt);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        // ignore - 로깅 실패가 메인 기능에 영향을 주지 않도록
    }
}

/**
 * 예약 상태 변경 히스토리 조회
 */
function getBookingStatusHistory($conn, $input) {
    try {
        __ensure_booking_status_history_table($conn);

        $page = max(1, intval($input['page'] ?? 1));
        $limit = max(1, min(100, intval($input['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // 필터
        $search = trim($input['search'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');
        $statusFilter = trim($input['statusFilter'] ?? '');

        $whereConditions = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $whereConditions[] = "(h.bookingId LIKE ? OR h.changedBy LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }

        if ($startDate !== '') {
            $whereConditions[] = "DATE(h.changedAt) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $whereConditions[] = "DATE(h.changedAt) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        if ($statusFilter !== '') {
            $whereConditions[] = "h.newStatus = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }

        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // 총 개수 조회
        $countSql = "SELECT COUNT(*) as total FROM booking_status_history h $whereClause";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) {
            // 테이블이 없으면 빈 결과 반환
            send_success_response([
                'histories' => [],
                'pagination' => [
                    'currentPage' => 1,
                    'totalPages' => 0,
                    'totalCount' => 0,
                    'limit' => $limit
                ]
            ]);
            return;
        }
        if ($types !== '') {
            mysqli_bind_params_by_ref($countStmt, $types, $params);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
        $countStmt->close();

        // 데이터 조회
        $dataSql = "
            SELECT
                h.id,
                h.bookingId,
                h.previousStatus,
                h.newStatus,
                h.changedBy,
                h.changedByType,
                h.changeReason,
                h.changedAt,
                b.packageName as productName,
                b.departureDate as travelDate
            FROM booking_status_history h
            LEFT JOIN bookings b ON h.bookingId COLLATE utf8mb4_general_ci = b.bookingId COLLATE utf8mb4_general_ci
            $whereClause
            ORDER BY h.changedAt DESC
            LIMIT ? OFFSET ?
        ";

        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';

        $dataStmt = $conn->prepare($dataSql);
        if ($dataStmt) {
            mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
            $dataStmt->execute();
            $result = $dataStmt->get_result();

            $histories = [];
            $rowNum = $totalCount - $offset;
            while ($row = $result->fetch_assoc()) {
                $row['rowNum'] = $rowNum--;
                $row['previousStatusLabel'] = __get_status_label($row['previousStatus']);
                $row['newStatusLabel'] = __get_status_label($row['newStatus']);
                $histories[] = $row;
            }
            $dataStmt->close();

            $totalPages = ceil($totalCount / $limit);

            send_success_response([
                'histories' => $histories,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalCount' => $totalCount,
                    'limit' => $limit
                ]
            ]);
        } else {
            send_error_response('Failed to prepare query');
        }
    } catch (Exception $e) {
        send_error_response('Failed to get status history: ' . $e->getMessage());
    }
}

/**
 * 상태 라벨 반환
 */
function __get_status_label($status) {
    $labels = [
        'pending' => 'Pending',
        'pending_update' => 'Pending Update',
        'check_reject' => 'Check Reject',
        'waiting_down_payment' => 'Waiting for Down Payment',
        'checking_down_payment' => 'Checking Down Payment',
        'waiting_second_payment' => 'Waiting for Second Payment',
        'checking_second_payment' => 'Checking Second Payment',
        'waiting_balance' => 'Waiting for Balance',
        'checking_balance' => 'Checking Balance',
        'waiting_full_payment' => 'Waiting for Full Payment',
        'checking_full_payment' => 'Checking Full Payment',
        'rejected' => 'Payment Rejected',
        'confirmed' => 'Reservation Confirmed',
        'completed' => 'Trip Completed',
        'cancelled' => 'Reservation Cancelled',
        'refunded' => 'Refund Completed',
        'partial' => 'Waiting for Second Payment'
    ];
    return $labels[$status] ?? $status;
}

/**
 * 예약 상태 변경 히스토리 CSV 내보내기
 */
function exportBookingStatusHistoryCsv($conn, $input) {
    try {
        __ensure_booking_status_history_table($conn);

        // 필터
        $search = trim($input['search'] ?? '');
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');
        $statusFilter = trim($input['statusFilter'] ?? '');

        $whereConditions = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $whereConditions[] = "(h.bookingId LIKE ? OR h.changedBy LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }

        if ($startDate !== '') {
            $whereConditions[] = "DATE(h.changedAt) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $whereConditions[] = "DATE(h.changedAt) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        if ($statusFilter !== '') {
            $whereConditions[] = "h.newStatus = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }

        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT
                h.bookingId,
                b.packageName as productName,
                b.departureDate as travelDate,
                h.previousStatus,
                h.newStatus,
                h.changedBy,
                h.changedByType,
                h.changedAt
            FROM booking_status_history h
            LEFT JOIN bookings b ON h.bookingId COLLATE utf8mb4_general_ci = b.bookingId COLLATE utf8mb4_general_ci
            $whereClause
            ORDER BY h.changedAt DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($types !== '' && $stmt) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // CSV 헤더
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="booking_status_history_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

        fputcsv($output, [
            'Booking ID',
            'Product Name',
            'Travel Date',
            'Previous Status',
            'New Status',
            'Changed By',
            'Changed By Type',
            'Changed At'
        ]);

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['bookingId'],
                $row['productName'] ?? '',
                $row['travelDate'] ?? '',
                __get_status_label($row['previousStatus']),
                __get_status_label($row['newStatus']),
                $row['changedBy'] ?? '',
                $row['changedByType'] ?? '',
                $row['changedAt']
            ]);
        }

        fclose($output);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to export CSV: ' . $e->getMessage());
    }
}

// =============================================
// 세일 관리 함수들
// =============================================

/**
 * 세일 목록 조회
 */
function getSales($conn, $input) {
    try {
        $page = max(1, intval($input['page'] ?? 1));
        $limit = max(1, min(100, intval($input['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $status = trim($input['status'] ?? '');
        $search = trim($input['search'] ?? '');
        $sortOrder = ($input['sortOrder'] ?? 'latest') === 'oldest' ? 'ASC' : 'DESC';

        $whereConditions = [];
        $params = [];
        $types = '';

        if ($status === 'active') {
            $whereConditions[] = "s.is_active = 1";
        } elseif ($status === 'inactive') {
            $whereConditions[] = "s.is_active = 0";
        }

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $whereConditions[] = "s.sale_name LIKE ?";
            $params[] = $searchParam;
            $types .= 's';
        }

        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // 총 개수
        $countSql = "SELECT COUNT(*) as total FROM sales s $whereClause";
        if ($types !== '') {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
        } else {
            $countResult = $conn->query($countSql);
        }
        $totalCount = $countResult->fetch_assoc()['total'];

        // 세일 목록
        $sql = "
            SELECT
                s.id,
                s.sale_name,
                s.discount_amount,
                s.sale_start_date,
                s.sale_end_date,
                s.is_active,
                s.description,
                s.created_at,
                (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
            FROM sales s
            $whereClause
            ORDER BY s.created_at $sortOrder
            LIMIT ?, ?
        ";

        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();

        $sales = [];
        $rowNum = $totalCount - $offset;
        while ($row = $result->fetch_assoc()) {
            // 현재 진행 중인지 확인
            $today = date('Y-m-d');
            $isOngoing = ($row['is_active'] == 1) &&
                         ($today >= $row['sale_start_date']) &&
                         ($today <= $row['sale_end_date']);

            $sales[] = [
                'id' => $row['id'],
                'rowNum' => $rowNum--,
                'saleName' => $row['sale_name'],
                'discountAmount' => floatval($row['discount_amount']),
                'formattedDiscount' => '₱' . number_format($row['discount_amount'], 0),
                'saleStartDate' => $row['sale_start_date'],
                'saleEndDate' => $row['sale_end_date'],
                'isActive' => $row['is_active'] == 1,
                'isOngoing' => $isOngoing,
                'description' => $row['description'],
                'itemCount' => intval($row['item_count']),
                'createdAt' => $row['created_at']
            ];
        }
        $stmt->close();

        send_json_response([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => $totalCount,
                    'limit' => $limit
                ]
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get sales: ' . $e->getMessage());
    }
}

/**
 * 세일 상세 조회
 */
function getSaleDetail($conn, $input) {
    try {
        $saleId = intval($input['saleId'] ?? 0);
        if ($saleId <= 0) {
            send_error_response('Sale ID is required', 400);
        }

        // 세일 기본 정보
        $sql = "SELECT * FROM sales WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sale = $result->fetch_assoc();
        $stmt->close();

        if (!$sale) {
            send_error_response('Sale not found', 404);
        }

        // 세일 아이템 (패키지/날짜 정보) - 동적 계산: bookings 테이블 기반
        $itemSql = "
            SELECT
                si.id as saleItemId,
                si.package_available_date_id,
                pad.package_id,
                p.packageName,
                pad.available_date,
                pad.price,
                pad.capacity,
                COALESCE(bk.booked, 0) as booked_seats
            FROM sale_items si
            INNER JOIN package_available_dates pad ON pad.id = si.package_available_date_id
            INNER JOIN packages p ON p.packageId = pad.package_id
            LEFT JOIN (
                SELECT packageId, departureDate,
                       SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                FROM bookings
                WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                  AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                GROUP BY packageId, departureDate
            ) bk ON bk.packageId = pad.package_id AND bk.departureDate = pad.available_date
            WHERE si.sale_id = ?
            ORDER BY pad.available_date ASC
        ";
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param('i', $saleId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = [
                'saleItemId' => $item['saleItemId'],
                'packageAvailableDateId' => $item['package_available_date_id'],
                'packageId' => $item['package_id'],
                'packageName' => $item['packageName'],
                'availableDate' => $item['available_date'],
                'originalPrice' => floatval($item['price']),
                'salePrice' => floatval($item['price']) - floatval($sale['discount_amount']),
                'capacity' => intval($item['capacity']),
                'bookedSeats' => intval($item['booked_seats']),
                'remainingSeats' => intval($item['capacity']) - intval($item['booked_seats'])
            ];
        }
        $itemStmt->close();

        send_json_response([
            'success' => true,
            'data' => [
                'id' => $sale['id'],
                'saleName' => $sale['sale_name'],
                'discountAmount' => floatval($sale['discount_amount']),
                'saleStartDate' => $sale['sale_start_date'],
                'saleEndDate' => $sale['sale_end_date'],
                'isActive' => $sale['is_active'] == 1,
                'description' => $sale['description'],
                'createdAt' => $sale['created_at'],
                'updatedAt' => $sale['updated_at'],
                'items' => $items
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get sale detail: ' . $e->getMessage());
    }
}

/**
 * 세일 생성
 */
function createSale($conn, $input) {
    try {
        $saleName = trim($input['saleName'] ?? '');
        $discountAmount = floatval($input['discountAmount'] ?? 0);
        $saleStartDate = trim($input['saleStartDate'] ?? '');
        $saleEndDate = trim($input['saleEndDate'] ?? '');
        $description = trim($input['description'] ?? '');
        $isActive = isset($input['isActive']) ? ($input['isActive'] ? 1 : 0) : 1;
        $items = $input['items'] ?? []; // package_available_date_id 배열

        // 필수 검증
        if ($saleName === '') {
            send_error_response('Sale name is required', 400);
        }
        if ($discountAmount <= 0) {
            send_error_response('Discount amount must be greater than 0', 400);
        }
        if ($saleStartDate === '' || $saleEndDate === '') {
            send_error_response('Sale period is required', 400);
        }
        if ($saleStartDate > $saleEndDate) {
            send_error_response('Start date must be before end date', 400);
        }

        // 트랜잭션 시작
        $conn->begin_transaction();

        // 세일 생성
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        $sql = "INSERT INTO sales (sale_name, discount_amount, sale_start_date, sale_end_date, is_active, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sdssisd', $saleName, $discountAmount, $saleStartDate, $saleEndDate, $isActive, $description, $adminAccountId);
        $stmt->execute();
        $saleId = $conn->insert_id;
        $stmt->close();

        // 세일 아이템 추가
        if (!empty($items)) {
            $itemSql = "INSERT INTO sale_items (sale_id, package_available_date_id) VALUES (?, ?)";
            $itemStmt = $conn->prepare($itemSql);
            foreach ($items as $padId) {
                $padId = intval($padId);
                if ($padId > 0) {
                    $itemStmt->bind_param('ii', $saleId, $padId);
                    $itemStmt->execute();
                }
            }
            $itemStmt->close();
        }

        $conn->commit();

        send_json_response([
            'success' => true,
            'message' => 'Sale created successfully',
            'data' => ['saleId' => $saleId]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to create sale: ' . $e->getMessage());
    }
}

/**
 * 세일 수정
 */
function updateSale($conn, $input) {
    try {
        $saleId = intval($input['saleId'] ?? 0);
        if ($saleId <= 0) {
            send_error_response('Sale ID is required', 400);
        }

        $saleName = trim($input['saleName'] ?? '');
        $discountAmount = floatval($input['discountAmount'] ?? 0);
        $saleStartDate = trim($input['saleStartDate'] ?? '');
        $saleEndDate = trim($input['saleEndDate'] ?? '');
        $description = trim($input['description'] ?? '');
        $isActive = isset($input['isActive']) ? ($input['isActive'] ? 1 : 0) : 1;
        $items = $input['items'] ?? null; // null이면 아이템 변경 없음

        // 필수 검증
        if ($saleName === '') {
            send_error_response('Sale name is required', 400);
        }
        if ($discountAmount <= 0) {
            send_error_response('Discount amount must be greater than 0', 400);
        }
        if ($saleStartDate === '' || $saleEndDate === '') {
            send_error_response('Sale period is required', 400);
        }
        if ($saleStartDate > $saleEndDate) {
            send_error_response('Start date must be before end date', 400);
        }

        // 트랜잭션 시작
        $conn->begin_transaction();

        // 세일 수정
        $sql = "UPDATE sales SET sale_name = ?, discount_amount = ?, sale_start_date = ?, sale_end_date = ?, is_active = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sdssisi', $saleName, $discountAmount, $saleStartDate, $saleEndDate, $isActive, $description, $saleId);
        $stmt->execute();
        $stmt->close();

        // 아이템이 전달된 경우 갱신
        if ($items !== null) {
            // 기존 아이템 삭제
            $deleteSql = "DELETE FROM sale_items WHERE sale_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('i', $saleId);
            $deleteStmt->execute();
            $deleteStmt->close();

            // 새 아이템 추가
            if (!empty($items)) {
                $itemSql = "INSERT INTO sale_items (sale_id, package_available_date_id) VALUES (?, ?)";
                $itemStmt = $conn->prepare($itemSql);
                foreach ($items as $padId) {
                    $padId = intval($padId);
                    if ($padId > 0) {
                        $itemStmt->bind_param('ii', $saleId, $padId);
                        $itemStmt->execute();
                    }
                }
                $itemStmt->close();
            }
        }

        $conn->commit();

        send_json_response([
            'success' => true,
            'message' => 'Sale updated successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to update sale: ' . $e->getMessage());
    }
}

/**
 * 세일 삭제
 */
function deleteSale($conn, $input) {
    try {
        $saleId = intval($input['saleId'] ?? 0);
        if ($saleId <= 0) {
            send_error_response('Sale ID is required', 400);
        }

        // sale_items는 CASCADE로 자동 삭제됨
        $sql = "DELETE FROM sales WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows === 0) {
            send_error_response('Sale not found', 404);
        }

        send_json_response([
            'success' => true,
            'message' => 'Sale deleted successfully'
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to delete sale: ' . $e->getMessage());
    }
}

/**
 * 세일 활성화/비활성화 토글
 */
function toggleSaleActive($conn, $input) {
    try {
        $saleId = intval($input['saleId'] ?? 0);
        if ($saleId <= 0) {
            send_error_response('Sale ID is required', 400);
        }

        $sql = "UPDATE sales SET is_active = NOT is_active WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $stmt->close();

        // 변경된 상태 조회
        $checkSql = "SELECT is_active FROM sales WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $saleId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $checkStmt->close();

        send_json_response([
            'success' => true,
            'message' => 'Sale status toggled successfully',
            'data' => ['isActive' => $row['is_active'] == 1]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to toggle sale status: ' . $e->getMessage());
    }
}

/**
 * 세일에 추가할 패키지/날짜 목록 조회
 */
function getPackagesForSale($conn, $input) {
    try {
        $search = trim($input['search'] ?? '');
        $packageId = intval($input['packageId'] ?? 0);
        $startDate = trim($input['startDate'] ?? '');
        $endDate = trim($input['endDate'] ?? '');

        $whereConditions = [
            "pad.available_date >= CURDATE()",
            "pad.status IN ('available', 'confirmed', 'open')",
            "(pad.capacity - COALESCE((SELECT SUM(COALESCE(b.adults,0)+COALESCE(b.children,0)+COALESCE(b.infants,0)) FROM bookings b WHERE b.packageId=pad.package_id AND b.departureDate=pad.available_date AND (b.bookingStatus IS NULL OR b.bookingStatus NOT IN ('cancelled','draft')) AND (b.paymentStatus IS NULL OR b.paymentStatus <> 'refunded')), 0)) > 0",
            "p.isActive = 1"
        ];
        $params = [];
        $types = '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $whereConditions[] = "p.packageName LIKE ?";
            $params[] = $searchParam;
            $types .= 's';
        }

        if ($packageId > 0) {
            $whereConditions[] = "p.packageId = ?";
            $params[] = $packageId;
            $types .= 'i';
        }

        if ($startDate !== '') {
            $whereConditions[] = "pad.available_date >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if ($endDate !== '') {
            $whereConditions[] = "pad.available_date <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // 패키지 목록 (검색용)
        $packageSql = "
            SELECT DISTINCT p.packageId, p.packageName
            FROM packages p
            INNER JOIN package_available_dates pad ON pad.package_id = p.packageId
            $whereClause
            ORDER BY p.packageName ASC
            LIMIT 100
        ";

        if ($types !== '') {
            $packageStmt = $conn->prepare($packageSql);
            mysqli_bind_params_by_ref($packageStmt, $types, $params);
            $packageStmt->execute();
            $packageResult = $packageStmt->get_result();
        } else {
            $packageResult = $conn->query($packageSql);
        }

        $packages = [];
        while ($row = $packageResult->fetch_assoc()) {
            $packages[] = [
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName']
            ];
        }

        // 날짜 목록 (B2B 가격 기준) - 동적 계산: bookings 테이블 기반
        $dateSql = "
            SELECT
                pad.id as packageAvailableDateId,
                pad.package_id as packageId,
                p.packageName,
                pad.available_date,
                COALESCE(pad.b2b_price, pad.price) as price,
                pad.capacity,
                COALESCE(bk.booked, 0) as booked_seats,
                (pad.capacity - COALESCE(bk.booked, 0)) as remaining_seats
            FROM package_available_dates pad
            INNER JOIN packages p ON p.packageId = pad.package_id
            LEFT JOIN (
                SELECT packageId, departureDate,
                       SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                FROM bookings
                WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                  AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                GROUP BY packageId, departureDate
            ) bk ON bk.packageId = pad.package_id AND bk.departureDate = pad.available_date
            $whereClause
            ORDER BY p.packageName ASC, pad.available_date ASC
            LIMIT 500
        ";

        if ($types !== '') {
            $dateStmt = $conn->prepare($dateSql);
            mysqli_bind_params_by_ref($dateStmt, $types, $params);
            $dateStmt->execute();
            $dateResult = $dateStmt->get_result();
        } else {
            $dateResult = $conn->query($dateSql);
        }

        $dates = [];
        while ($row = $dateResult->fetch_assoc()) {
            $dates[] = [
                'packageAvailableDateId' => intval($row['packageAvailableDateId']),
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName'],
                'availableDate' => $row['available_date'],
                'price' => floatval($row['price']),
                'formattedPrice' => '₱' . number_format($row['price'], 0),
                'capacity' => intval($row['capacity']),
                'bookedSeats' => intval($row['booked_seats']),
                'remainingSeats' => intval($row['remaining_seats'])
            ];
        }

        send_json_response([
            'success' => true,
            'data' => [
                'packages' => $packages,
                'dates' => $dates
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get packages for sale: ' . $e->getMessage());
    }
}

/**
 * 월별 인보이스 데이터 조회 (B2B + B2C 통합)
 */
function getMonthlyInvoiceData($conn, $input) {
    try {
        // 날짜 범위 파라미터 (startDate/endDate 우선, 없으면 year/month 사용)
        if (!empty($input['startDate']) && !empty($input['endDate'])) {
            $startDate = $input['startDate'];
            $endDate = $input['endDate'];
            // year/month는 endDate 기준으로 설정
            $year = intval(date('Y', strtotime($endDate)));
            $month = intval(date('m', strtotime($endDate)));
        } else {
            $year = isset($input['year']) ? intval($input['year']) : intval(date('Y'));
            $month = isset($input['month']) ? intval($input['month']) : intval(date('m'));
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate)); // 해당 월의 마지막 날
        }
        $statusFilter = $input['statusFilter'] ?? '';

        // 동적 컬럼 확인
        $hasSelectedOptions = false;
        $hasContactName = false;
        $hasCustomerAccountId = false;
        $customerAccountIdCol = null;

        try {
            $col = $conn->query("SHOW COLUMNS FROM bookings LIKE 'selectedOptions'");
            $hasSelectedOptions = $col && $col->num_rows > 0;

            $col = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contactName'");
            $hasContactName = $col && $col->num_rows > 0;

            // customerAccountId 컬럼명 확인
            $bookingColumns = [];
            $cr = $conn->query("SHOW COLUMNS FROM bookings");
            while ($cr && ($c = $cr->fetch_assoc())) {
                $bookingColumns[] = strtolower((string)$c['Field']);
            }
            if (in_array('customeraccountid', $bookingColumns, true)) $customerAccountIdCol = 'customerAccountId';
            else if (in_array('customer_account_id', $bookingColumns, true)) $customerAccountIdCol = 'customer_account_id';
            $hasCustomerAccountId = !empty($customerAccountIdCol);
        } catch (Throwable $e) { /* ignore */ }

        // 기본 쿼리 구성 (B2B 인보이스용 확장 필드 포함)
        $selectFields = "
            b.bookingId,
            b.transactNo,
            b.packageId,
            b.packageName,
            COALESCE(b.packageName, p.packageName) as productName,
            b.departureDate,
            b.totalAmount,
            b.adults,
            b.children,
            b.infants,
            (COALESCE(b.adults, 0) + COALESCE(b.children, 0) + COALESCE(b.infants, 0)) as numberOfPeople,
            b.bookingStatus,
            b.paymentStatus,
            b.contactEmail,
            b.contactPhone,
            b.createdAt,
            b.price_tier,
            b.agentId,
            COALESCE(b.packagePrice, p.packagePrice, 0) as packagePrice,
            COALESCE(p.b2b_price, b.packagePrice, p.packagePrice, 0) as basePrice,
            COALESCE(b.visaFee, 0) as visaFee,
            COALESCE(b.flightOptionFee, 0) as flightOptionFee,
            b.saleId,
            b.saleName,
            COALESCE(b.saleDiscountAmount, 0) as saleDiscountAmount,
            COALESCE(p.b2b_price, 0) as b2b_price,
            COALESCE(p.b2b_child_price, 0) as b2b_child_price,
            COALESCE(p.b2b_infant_price, 0) as b2b_infant_price,
            COALESCE(b.downPaymentFile, '') as downPaymentFile,
            COALESCE(b.downPaymentAmount, 0) as downPaymentAmount,
            COALESCE(b.advancePaymentFile, '') as advancePaymentFile,
            COALESCE(b.advancePaymentAmount, 0) as advancePaymentAmount,
            COALESCE(b.balanceFile, '') as balanceFile,
            COALESCE(b.balanceAmount, 0) as balanceAmount,
            COALESCE(b.fullPaymentFile, '') as fullPaymentFile,
            COALESCE(b.fullPaymentAmount, 0) as fullPaymentAmount
        ";

        // contactName 필드 추가
        if ($hasContactName) {
            $selectFields .= ", b.contactName";
        }

        // selectedOptions 필드 추가
        if ($hasSelectedOptions) {
            $selectFields .= ", b.selectedOptions";
        }

        // 고객명 조회를 위한 JOIN 및 필드
        $customerJoin = "";
        $customerNameExpr = "";
        if ($hasCustomerAccountId) {
            $customerJoin = "LEFT JOIN client cu ON b.`{$customerAccountIdCol}` = cu.accountId";
            $customerNameExpr = "COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(cu.fName,''), ' ', COALESCE(cu.lName,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,''))), ''),
                a.username,
                ''
            ) as customerName";
        } else {
            $customerNameExpr = "COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,''))), ''),
                a.username,
                ''
            ) as customerName";
        }

        // 에이전트/회사명 (확장: 연락처, 담당자)
        $agentNameExpr = "COALESCE(
            NULLIF(ag.agencyName, ''),
            a.username,
            ''
        ) as agentName,
        COALESCE(ag.agencyName, '') as companyName,
        COALESCE(ag.contactNo, '') as agencyPhone,
        COALESCE(ag.personInCharge, '') as agencyContactPerson";

        // WHERE 조건 구성 (B2B 전용)
        $whereConditions = [];
        $params = [];
        $types = '';

        // 출발일 기준 필터링
        $whereConditions[] = "b.departureDate >= ?";
        $whereConditions[] = "b.departureDate <= ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';

        // 빈 상태 제외
        $whereConditions[] = "COALESCE(b.bookingStatus, '') != ''";

        // B2B 전용 (에이전트 예약만)
        $whereConditions[] = "(a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)";

        // packageId = 19 항상 제외
        $whereConditions[] = "(b.packageId IS NULL OR b.packageId != 19)";

        // cancelled 예약 항상 제외
        $whereConditions[] = "b.bookingStatus NOT IN ('cancelled','draft')";

        // 상태 필터
        if (!empty($statusFilter)) {
            $statuses = explode(',', $statusFilter);
            $statusPlaceholders = [];
            foreach ($statuses as $status) {
                $statusPlaceholders[] = '?';
                $params[] = trim($status);
                $types .= 's';
            }
            $whereConditions[] = "b.bookingStatus IN (" . implode(',', $statusPlaceholders) . ")";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // 최종 쿼리
        $sql = "
            SELECT
                {$selectFields},
                {$customerNameExpr},
                {$agentNameExpr}
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN accounts a ON b.accountId = a.accountId
            LEFT JOIN client c ON b.accountId = c.accountId
            LEFT JOIN agent ag ON b.agentId = ag.agentId
            {$customerJoin}
            {$whereClause}
            ORDER BY b.departureDate ASC, b.createdAt ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        if (!empty($types)) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            // selectedOptions에서 customerInfo 추출
            if ($hasSelectedOptions) {
                try {
                    $soRaw = $row['selectedOptions'] ?? '';
                    if (!empty($soRaw)) {
                        $so = json_decode($soRaw, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($so['customerInfo'])) {
                            $ci = $so['customerInfo'];
                            if (empty($row['customerName']) && !empty($ci['name'])) {
                                $row['customerName'] = $ci['name'];
                            }
                            if (empty($row['contactEmail']) && !empty($ci['email'])) {
                                $row['contactEmail'] = $ci['email'];
                            }
                            if (empty($row['contactPhone']) && !empty($ci['phone'])) {
                                $row['contactPhone'] = $ci['phone'];
                            }
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            // contactName 우선 적용
            if ($hasContactName && !empty($row['contactName']) && empty($row['customerName'])) {
                $row['customerName'] = $row['contactName'];
            }

            // 패키지명 정리
            $row['packageName'] = $row['productName'] ?: $row['packageName'];

            // 인원 수 계산
            $adults = intval($row['adults'] ?? 0);
            $children = intval($row['children'] ?? 0);
            $infants = intval($row['infants'] ?? 0);
            $numPeople = intval($row['numberOfPeople'] ?? 0);

            if ($numPeople === 0 && ($adults + $children + $infants) > 0) {
                $row['numberOfPeople'] = $adults + $children + $infants;
            } else if ($numPeople > 0 && $adults === 0) {
                $row['adults'] = $numPeople;
            }

            $bookings[] = $row;
        }

        $stmt->close();

        send_json_response([
            'success' => true,
            'data' => [
                'bookings' => $bookings,
                'year' => $year,
                'month' => $month,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'bookingType' => 'b2b',
                'totalCount' => count($bookings)
            ]
        ]);

    } catch (Exception $e) {
        error_log("getMonthlyInvoiceData error: " . $e->getMessage());
        send_error_response('Failed to get monthly invoice data: ' . $e->getMessage());
    }
}

// ========== 메시지 관리 함수 ==========

function generateThreadNo($conn) {
    $prefix = 'MSG' . date('Ymd');
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(threadNo, 12) AS UNSIGNED)) AS maxSeq FROM message_threads WHERE threadNo LIKE '{$prefix}%'");
    $row = $result ? $result->fetch_assoc() : null;
    $seq = ($row && $row['maxSeq']) ? intval($row['maxSeq']) + 1 : 1;
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function getMessageThreads($conn, $input) {
    try {
        $page = max(1, intval($input['page'] ?? 1));
        $limit = max(1, min(100, intval($input['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $status = $input['status'] ?? '';
        $search = trim($input['search'] ?? '');
        $sortOrder = ($input['sortOrder'] ?? 'latest') === 'oldest' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        $types = '';

        if ($status && in_array($status, ['open','in_progress','resolved','closed'])) {
            $where[] = 't.status = ?';
            $params[] = $status;
            $types .= 's';
        }
        if ($search !== '') {
            $where[] = '(t.subject LIKE ? OR ag.agencyName LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }
        $dateFrom = trim($input['dateFrom'] ?? '');
        $dateTo = trim($input['dateTo'] ?? '');
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 't.createdAt >= ?';
            $params[] = $dateFrom . ' 00:00:00';
            $types .= 's';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 't.createdAt <= ?';
            $params[] = $dateTo . ' 23:59:59';
            $types .= 's';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countSql = "SELECT COUNT(*) AS cnt FROM message_threads t LEFT JOIN agent ag ON t.agentAccountId = ag.accountId {$whereClause}";
        if ($params) {
            $stmt = $conn->prepare($countSql);
            $bindParams = [];
            foreach ($params as $k => &$v) $bindParams[] = &$params[$k];
            array_unshift($bindParams, $types);
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $totalCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
        } else {
            $totalCount = intval($conn->query($countSql)->fetch_assoc()['cnt'] ?? 0);
        }

        // List
        $sql = "SELECT t.threadId, t.threadNo, t.subject, t.status, t.category, t.lastMessageAt, t.createdAt,
                    ag.agencyName AS agentName,
                    (SELECT COUNT(*) FROM message_items mi WHERE mi.threadId = t.threadId AND mi.isRead = 0 AND mi.senderType = 'agent') AS unreadCount,
                    (SELECT mi.content FROM message_items mi WHERE mi.threadId = t.threadId ORDER BY mi.createdAt DESC LIMIT 1) AS lastMessage
                FROM message_threads t
                LEFT JOIN agent ag ON t.agentAccountId = ag.accountId
                {$whereClause}
                ORDER BY t.lastMessageAt {$sortOrder}, t.createdAt {$sortOrder}
                LIMIT ? OFFSET ?";

        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$limit, $offset]);

        $stmt = $conn->prepare($sql);
        $bindParams = [];
        foreach ($listParams as $k => &$v) $bindParams[] = &$listParams[$k];
        array_unshift($bindParams, $listTypes);
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $threads = [];
        $rowNum = $totalCount - $offset;
        while ($row = $result->fetch_assoc()) {
            $lastMsg = strip_tags($row['lastMessage'] ?? '');
            if (mb_strlen($lastMsg) > 50) $lastMsg = mb_substr($lastMsg, 0, 50) . '...';
            $threads[] = [
                'threadId' => intval($row['threadId']),
                'threadNo' => $row['threadNo'],
                'subject' => $row['subject'],
                'agentName' => $row['agentName'] ?? '',
                'status' => $row['status'],
                'category' => $row['category'],
                'unreadCount' => intval($row['unreadCount']),
                'lastMessage' => $lastMsg,
                'lastMessageAt' => $row['lastMessageAt'],
                'createdAt' => $row['createdAt'],
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'threads' => $threads,
            'pagination' => [
                'totalCount' => intval($totalCount),
                'currentPage' => $page,
                'totalPages' => max(1, ceil($totalCount / $limit)),
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get message threads: ' . $e->getMessage());
    }
}

function getMessageThreadDetail($conn, $input) {
    try {
        $threadId = intval($input['threadId'] ?? $input['id'] ?? 0);
        if (!$threadId) send_error_response('Thread ID is required');

        // Thread info
        $stmt = $conn->prepare("SELECT t.*, ag.agencyName AS agentName, COALESCE(NULLIF(TRIM(ag.personInCharge),''), TRIM(CONCAT(IFNULL(ag.fName,''),' ',IFNULL(ag.lName,'')))) AS managerName, ag.personInChargeEmail AS managerEmail, ag.contactNo AS managerContact
            FROM message_threads t
            LEFT JOIN agent ag ON t.agentAccountId = ag.accountId
            WHERE t.threadId = ?");
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $thread = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$thread) send_error_response('Thread not found', 404);

        // Mark agent messages as read
        $stmtRead = $conn->prepare("UPDATE message_items SET isRead = 1, readAt = NOW() WHERE threadId = ? AND senderType = 'agent' AND isRead = 0");
        $stmtRead->bind_param('i', $threadId);
        $stmtRead->execute();
        $stmtRead->close();

        // All messages
        $stmtMsg = $conn->prepare("SELECT mi.messageId, mi.senderId, mi.senderType, mi.content, mi.isRead, mi.readAt, mi.createdAt,
                a.firstName AS senderFirstName, a.lastName AS senderLastName
            FROM message_items mi
            LEFT JOIN accounts a ON mi.senderId = a.accountId
            WHERE mi.threadId = ?
            ORDER BY mi.createdAt ASC");
        $stmtMsg->bind_param('i', $threadId);
        $stmtMsg->execute();
        $msgResult = $stmtMsg->get_result();

        $messages = [];
        $messageIds = [];
        while ($row = $msgResult->fetch_assoc()) {
            $messageIds[] = intval($row['messageId']);
            $messages[] = [
                'messageId' => intval($row['messageId']),
                'senderId' => intval($row['senderId']),
                'senderType' => $row['senderType'],
                'senderName' => trim(($row['senderFirstName'] ?? '') . ' ' . ($row['senderLastName'] ?? '')) ?: ($row['senderType'] === 'admin' ? 'Admin' : 'Agent'),
                'content' => $row['content'],
                'isRead' => intval($row['isRead']),
                'readAt' => $row['readAt'],
                'createdAt' => $row['createdAt'],
                'attachments' => []
            ];
        }
        $stmtMsg->close();

        // Attachments for all messages
        if ($messageIds) {
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $stmtAtt = $conn->prepare("SELECT attachmentId, messageId, fileName, filePath, fileSize, fileType FROM message_attachments WHERE messageId IN ({$placeholders})");
            $attTypes = str_repeat('i', count($messageIds));
            $bindParams = [];
            foreach ($messageIds as $k => &$v) $bindParams[] = &$messageIds[$k];
            array_unshift($bindParams, $attTypes);
            call_user_func_array([$stmtAtt, 'bind_param'], $bindParams);
            $stmtAtt->execute();
            $attResult = $stmtAtt->get_result();
            $attMap = [];
            while ($att = $attResult->fetch_assoc()) {
                $mid = intval($att['messageId']);
                if (!isset($attMap[$mid])) $attMap[$mid] = [];
                $ext = strtolower(pathinfo($att['fileName'], PATHINFO_EXTENSION));
                $attMap[$mid][] = [
                    'attachmentId' => intval($att['attachmentId']),
                    'fileName' => $att['fileName'],
                    'filePath' => $att['filePath'],
                    'fileSize' => intval($att['fileSize']),
                    'fileType' => $att['fileType'],
                    'isImage' => in_array($ext, ['jpg','jpeg','png','gif','webp'])
                ];
            }
            $stmtAtt->close();

            foreach ($messages as &$msg) {
                $msg['attachments'] = $attMap[$msg['messageId']] ?? [];
            }
            unset($msg);
        }

        send_success_response([
            'thread' => [
                'threadId' => intval($thread['threadId']),
                'threadNo' => $thread['threadNo'],
                'subject' => $thread['subject'],
                'status' => $thread['status'],
                'category' => $thread['category'],
                'bookingId' => $thread['bookingId'] ?? null,
                'agentName' => $thread['agentName'] ?? '',
                'region' => '',
                'managerName' => $thread['managerName'] ?? '',
                'managerEmail' => $thread['managerEmail'] ?? '',
                'managerContact' => $thread['managerContact'] ?? '',
                'createdAt' => $thread['createdAt']
            ],
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get thread detail: ' . $e->getMessage());
    }
}

function getMessageThreadByBookingId($conn, $input) {
    try {
        $adminAccountId = intval($_SESSION['admin_accountId'] ?? 0);
        if (!$adminAccountId) send_error_response('Admin login required', 401);

        $bookingId = trim($input['bookingId'] ?? '');
        if ($bookingId === '') send_error_response('Booking ID is required');

        $stmt = $conn->prepare("SELECT threadId FROM message_threads WHERE bookingId = ? LIMIT 1");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Get agent info from booking
        $agentInfo = null;
        $stmtAgent = $conn->prepare("SELECT b.accountId, COALESCE(ag.agencyName, TRIM(CONCAT(IFNULL(ag.fName,''),' ',IFNULL(ag.lName,'')))) AS agentName, COALESCE(NULLIF(TRIM(ag.personInCharge),''), TRIM(CONCAT(IFNULL(ag.fName,''),' ',IFNULL(ag.lName,'')))) AS managerName FROM bookings b LEFT JOIN agent ag ON b.accountId = ag.accountId WHERE b.bookingId = ? LIMIT 1");
        $stmtAgent->bind_param('s', $bookingId);
        $stmtAgent->execute();
        $agentRow = $stmtAgent->get_result()->fetch_assoc();
        $stmtAgent->close();

        if ($agentRow) {
            $agentInfo = [
                'agentAccountId' => intval($agentRow['accountId']),
                'agentName' => $agentRow['agentName'] ?? '',
                'managerName' => $agentRow['managerName'] ?? ''
            ];
        }

        send_success_response([
            'exists' => !!$row,
            'threadId' => $row ? intval($row['threadId']) : null,
            'agentInfo' => $agentInfo
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to check thread: ' . $e->getMessage());
    }
}

function createMessageThread($conn, $input) {
    try {
        $adminAccountId = intval($_SESSION['admin_accountId'] ?? 0);
        if (!$adminAccountId) send_error_response('Admin login required', 401);

        $agentAccountId = intval($input['agentAccountId'] ?? 0);
        $subject = trim($input['subject'] ?? '');
        $content = trim($input['content'] ?? '');
        $category = $input['category'] ?? 'general';
        $bookingId = trim($input['bookingId'] ?? '') ?: null;

        if (!$agentAccountId) send_error_response('Agent selection is required');
        if ($subject === '') send_error_response('Subject is required');
        if ($content === '' || $content === '<p><br></p>') send_error_response('Message content is required');

        if (!in_array($category, ['general','product','booking','payment','cancellation','other'])) {
            $category = 'general';
        }

        $conn->begin_transaction();

        $threadNo = generateThreadNo($conn);

        $stmtThread = $conn->prepare("INSERT INTO message_threads (threadNo, subject, createdBy, agentAccountId, bookingId, category, status, lastMessageAt, createdAt) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())");
        $stmtThread->bind_param('ssisss', $threadNo, $subject, $adminAccountId, $agentAccountId, $bookingId, $category);
        if (!$stmtThread->execute()) {
            $conn->rollback();
            send_error_response('Failed to create thread: ' . $stmtThread->error);
        }
        $threadId = $conn->insert_id;
        $stmtThread->close();

        $stmtMsg = $conn->prepare("INSERT INTO message_items (threadId, senderId, senderType, content, createdAt) VALUES (?, ?, 'admin', ?, NOW())");
        $stmtMsg->bind_param('iis', $threadId, $adminAccountId, $content);
        if (!$stmtMsg->execute()) {
            $conn->rollback();
            send_error_response('Failed to create message: ' . $stmtMsg->error);
        }
        $messageId = $conn->insert_id;
        $stmtMsg->close();

        // Handle file attachments
        $attachments = handleMessageAttachments($conn, $threadId, $messageId, $adminAccountId);

        $conn->commit();

        send_success_response([
            'threadId' => $threadId,
            'threadNo' => $threadNo,
            'messageId' => $messageId,
            'attachments' => $attachments
        ], 'Message thread created successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to create thread: ' . $e->getMessage());
    }
}

function handleSendMessage($conn, $input) {
    try {
        $adminAccountId = intval($_SESSION['admin_accountId'] ?? 0);
        if (!$adminAccountId) send_error_response('Admin login required', 401);

        $threadId = intval($input['threadId'] ?? 0);
        $content = trim($input['content'] ?? '');

        if (!$threadId) send_error_response('Thread ID is required');

        $hasFiles = !empty($_FILES['files']) && is_array($_FILES['files']['name']) && $_FILES['files']['error'][0] === UPLOAD_ERR_OK;
        $hasContent = $content !== '' && $content !== '<p><br></p>';
        if (!$hasContent && !$hasFiles) send_error_response('Please enter a message or attach a file');

        // Verify thread exists
        $stmtCheck = $conn->prepare("SELECT threadId, status FROM message_threads WHERE threadId = ?");
        $stmtCheck->bind_param('i', $threadId);
        $stmtCheck->execute();
        $thread = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if (!$thread) send_error_response('Thread not found', 404);
        if ($thread['status'] === 'closed') send_error_response('Cannot send message to a closed thread');

        $conn->begin_transaction();

        $stmtMsg = $conn->prepare("INSERT INTO message_items (threadId, senderId, senderType, content, createdAt) VALUES (?, ?, 'admin', ?, NOW())");
        $stmtMsg->bind_param('iis', $threadId, $adminAccountId, $content);
        if (!$stmtMsg->execute()) {
            $conn->rollback();
            send_error_response('Failed to send message: ' . $stmtMsg->error);
        }
        $messageId = $conn->insert_id;
        $stmtMsg->close();

        $conn->query("UPDATE message_threads SET lastMessageAt = NOW() WHERE threadId = {$threadId}");

        $attachments = handleMessageAttachments($conn, $threadId, $messageId, $adminAccountId);

        $conn->commit();

        send_success_response([
            'messageId' => $messageId,
            'attachments' => $attachments
        ], 'Message sent successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to send message: ' . $e->getMessage());
    }
}

function handleMessageAttachments($conn, $threadId, $messageId, $uploadedBy) {
    $attachments = [];
    if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $uploadDir = realpath(__DIR__ . '/../../../uploads/messages');
        if (!$uploadDir) {
            $uploadDir = __DIR__ . '/../../../uploads/messages';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        }

        $allowedExts = ['jpg','jpeg','png','gif','pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $maxFiles = 5;

        $fileCount = min(count($_FILES['files']['name']), $maxFiles);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $originalName = $_FILES['files']['name'][$i];
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileSize = $_FILES['files']['size'][$i];
            $fileType = $_FILES['files']['type'][$i];

            if ($fileSize > $maxSize) continue;

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $storedName = 'msg_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '_' . $safeName;
            $destPath = $uploadDir . '/' . $storedName;

            if (move_uploaded_file($tmpPath, $destPath)) {
                $relPath = 'uploads/messages/' . $storedName;
                $stmtAtt = $conn->prepare("INSERT INTO message_attachments (messageId, threadId, fileName, filePath, fileSize, fileType, uploadedBy, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmtAtt->bind_param('iissisi', $messageId, $threadId, $originalName, $relPath, $fileSize, $fileType, $uploadedBy);
                $stmtAtt->execute();
                $attachments[] = [
                    'attachmentId' => $conn->insert_id,
                    'fileName' => $originalName,
                    'filePath' => $relPath,
                    'fileSize' => $fileSize,
                    'fileType' => $fileType
                ];
                $stmtAtt->close();
            }
        }
    }
    return $attachments;
}

function updateMessageThreadStatus($conn, $input) {
    try {
        $threadId = intval($input['threadId'] ?? 0);
        $status = $input['status'] ?? '';
        if (!$threadId) send_error_response('Thread ID is required');
        if (!in_array($status, ['open','in_progress','resolved','closed'])) {
            send_error_response('Invalid status');
        }

        $stmt = $conn->prepare("UPDATE message_threads SET status = ? WHERE threadId = ?");
        $stmt->bind_param('si', $status, $threadId);
        if (!$stmt->execute()) send_error_response('Failed to update status: ' . $stmt->error);
        $stmt->close();

        send_success_response([], 'Status updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update status: ' . $e->getMessage());
    }
}

function getAgentListForMessage($conn, $input) {
    try {
        $keyword = trim($input['keyword'] ?? '');

        $sql = "SELECT a.accountId, ag.agencyName, ag.personInCharge, ag.fName, ag.lName, ag.contactNo
            FROM accounts a
            INNER JOIN agent ag ON a.accountId = ag.accountId
            WHERE (a.accountStatus = 'active' OR a.accountStatus IS NULL)";

        $params = [];
        $types = '';

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $sql .= " AND (ag.agencyName LIKE ? OR ag.personInCharge LIKE ? OR ag.fName LIKE ? OR ag.lName LIKE ? OR CONCAT(ag.fName, ' ', ag.lName) LIKE ?)";
            $types .= 'sssss';
            $params = [$like, $like, $like, $like, $like];
        }

        $sql .= " ORDER BY ag.agencyName ASC LIMIT 50";

        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $agents = [];
        while ($row = $result->fetch_assoc()) {
            $name = trim($row['agencyName'] ?? '');
            $manager = trim($row['personInCharge'] ?? '');
            $fullName = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
            if ($name === '' && $fullName === '') continue;

            $agents[] = [
                'accountId' => intval($row['accountId']),
                'branchName' => $name,
                'managerName' => $manager ?: $fullName,
                'contactNo' => $row['contactNo'] ?? ''
            ];
        }
        $stmt->close();

        send_success_response(['agents' => $agents]);
    } catch (Exception $e) {
        send_error_response('Failed to get agent list: ' . $e->getMessage());
    }
}

function getMessageUnreadCount($conn, $input) {
    try {
        $result = $conn->query("SELECT COUNT(*) AS cnt FROM message_items WHERE isRead = 0 AND senderType = 'agent'");
        $count = $result ? intval($result->fetch_assoc()['cnt'] ?? 0) : 0;
        send_success_response(['unreadCount' => $count]);
    } catch (Exception $e) {
        send_error_response('Failed to get unread count: ' . $e->getMessage());
    }
}

// ========== Extra Options 함수들 ==========

/**
 * Extra Options 전체 리스트 조회 (Super Admin)
 */
function getExtraOptionsListAll($conn, $input) {
    try {
        $sql = "SELECT b.bookingId, b.departureDate, b.flightOptionFee, b.bookingStatus,
                       b.adults, b.children, b.infants,
                       p.packageName,
                       DATE_ADD(b.departureDate, INTERVAL (COALESCE(p.duration_days, p.durationDays, 1) - 1) DAY) as returnDate,
                       COALESCE(NULLIF(CONCAT(a.fName,' ',a.lName), ' '), a.agencyName, '') as agentName,
                       bop.option_payment_status, bop.option_payment_amount,
                       bop.option_payment_file, bop.option_payment_file_name,
                       bop.option_payment_uploaded_at,
                       bop.option_payment_confirmed_at, bop.option_payment_rejected_at,
                       bop.option_payment_rejection_reason
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN agent a ON b.agentId = a.id
                LEFT JOIN booking_option_payments bop ON b.bookingId = bop.booking_id
                WHERE b.bookingStatus NOT IN ('draft','cancelled')
                  AND (bop.option_payment_status IS NOT NULL AND bop.option_payment_status != 'not_set')
                ORDER BY
                    CASE bop.option_payment_status WHEN 'checking' THEN 0 WHEN 'pending_payment' THEN 1 WHEN 'rejected' THEN 2 WHEN 'confirmed' THEN 3 ELSE 4 END,
                    bop.option_payment_uploaded_at DESC,
                    b.createdAt DESC";
        $result = $conn->query($sql);

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $pax = intval($row['adults'] ?? 0) + intval($row['children'] ?? 0) + intval($row['infants'] ?? 0);
            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                'agentName' => $row['agentName'] ?? '',
                'departureDate' => $row['departureDate'],
                'returnDate' => $row['returnDate'] ?? '',
                'pax' => $pax,
                'bookingStatus' => $row['bookingStatus'],
                'flightOptionFee' => floatval($row['flightOptionFee'] ?? 0),
                'optionPaymentStatus' => $row['option_payment_status'] ?? 'not_set',
                'optionPaymentAmount' => floatval($row['option_payment_amount'] ?? 0),
                'optionPaymentFile' => $row['option_payment_file'],
                'optionPaymentFileName' => $row['option_payment_file_name'],
                'optionPaymentUploadedAt' => $row['option_payment_uploaded_at'],
                'optionPaymentConfirmedAt' => $row['option_payment_confirmed_at'],
                'optionPaymentRejectedAt' => $row['option_payment_rejected_at'],
                'optionPaymentRejectionReason' => $row['option_payment_rejection_reason']
            ];
        }

        send_success_response(['bookings' => $bookings]);
    } catch (Exception $e) {
        send_error_response('Failed to load extra options list: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 결제 승인 (Super Admin)
 */
function confirmOptionPayment($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) send_error_response('Booking ID is required');

        if (session_status() === PHP_SESSION_NONE) session_start();
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;

        // 현재 상태 확인
        $chk = $conn->prepare("SELECT option_payment_status, option_payment_file FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
        $chk->bind_param('s', $bookingId);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) send_error_response('Option payment record not found', 404);
        if ($row['option_payment_status'] !== 'checking') {
            send_error_response('Can only confirm payments in "checking" status');
        }
        if (empty($row['option_payment_file'])) {
            send_error_response('No payment proof file uploaded');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE booking_option_payments SET option_payment_status = 'confirmed', option_payment_confirmed_at = ?, option_payment_confirmed_by = ? WHERE booking_id = ?");
        $stmt->bind_param('sis', $now, $adminAccountId, $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response(['confirmedAt' => $now], 'Option payment confirmed successfully');
    } catch (Exception $e) {
        send_error_response('Failed to confirm option payment: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 결제 반려 (Super Admin)
 */
function rejectOptionPayment($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $reason = $input['reason'] ?? '';
        if (empty($bookingId)) send_error_response('Booking ID is required');

        // 현재 상태 및 파일 확인
        $chk = $conn->prepare("SELECT option_payment_status, option_payment_file FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
        $chk->bind_param('s', $bookingId);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) send_error_response('Option payment record not found', 404);
        if ($row['option_payment_status'] !== 'checking') {
            send_error_response('Can only reject payments in "checking" status');
        }

        // 파일 삭제
        if (!empty($row['option_payment_file'])) {
            $fullPath = __DIR__ . '/../../../' . $row['option_payment_file'];
            if (file_exists($fullPath)) @unlink($fullPath);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE booking_option_payments
                                SET option_payment_status = 'rejected',
                                    option_payment_file = NULL,
                                    option_payment_file_name = NULL,
                                    option_payment_uploaded_at = NULL,
                                    option_payment_rejected_at = ?,
                                    option_payment_rejection_reason = ?
                                WHERE booking_id = ?");
        $stmt->bind_param('sss', $now, $reason, $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response(['rejectedAt' => $now], 'Option payment rejected successfully');
    } catch (Exception $e) {
        send_error_response('Failed to reject option payment: ' . $e->getMessage(), 500);
    }
}

// ========== Rooming List 관련 함수 ==========

function getRoomingDates($conn, $input) {
    try {
        $month = $input['month'] ?? '';
        if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            send_error_response('Valid month parameter is required (YYYY-MM)', 400);
        }

        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $sql = "SELECT DATE(b.departureDate) AS departureDate,
                       b.packageId,
                       COALESCE(NULLIF(b.packageName,''), p.packageName) AS packageName,
                       SUM(b.adults + b.children + b.infants) AS totalPax,
                       COUNT(DISTINCT b.bookingId) AS bookingCount
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN accounts a ON b.accountId = a.accountId
                WHERE b.departureDate BETWEEN ? AND ?
                  AND (a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)
                  AND b.bookingStatus IN ('confirmed','completed','waiting_balance','checking_balance','waiting_full_payment','checking_full_payment','waiting_second_payment','checking_second_payment')
                GROUP BY DATE(b.departureDate), b.packageId
                ORDER BY b.departureDate";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = [
                'departureDate' => $row['departureDate'],
                'packageId' => (int)$row['packageId'],
                'packageName' => $row['packageName'] ?? '',
                'totalPax' => (int)$row['totalPax'],
                'bookingCount' => (int)$row['bookingCount']
            ];
        }
        $stmt->close();

        send_success_response($dates);
    } catch (Exception $e) {
        send_error_response('Failed to get rooming dates: ' . $e->getMessage(), 500);
    }
}

function getRoomingList($conn, $input) {
    try {
        $departureDate = $input['departureDate'] ?? '';
        $packageId = $input['packageId'] ?? '';

        if (empty($departureDate) || empty($packageId)) {
            send_error_response('departureDate and packageId are required', 400);
        }

        $packageId = (int)$packageId;

        // 메인 쿼리: 예약 + 여행객 + 방 배정 정보
        $sql = "SELECT
                    b.bookingId, b.departureDate, b.adults, b.children, b.infants,
                    b.roomOption, b.selectedRooms, b.selectedOptions, b.bookingStatus, b.specialRequests, b.seatRequest,
                    b.price_tier, b.contactEmail, b.contactPhone,
                    COALESCE(NULLIF(b.packageName,''), p.packageName) AS packageName,
                    p.duration_days, p.common_accommodation_name,
                    bt.bookingTravelerId, bt.travelerType, bt.title, bt.firstName, bt.lastName,
                    bt.birthDate, bt.gender, bt.nationality,
                    bt.passportNumber, bt.passportIssueDate, bt.passportExpiry,
                    bt.visaStatus, bt.specialRequests AS travelerRequests,
                    bt.isMainTraveler, bt.infantSeat, bt.childRoom,
                    COALESCE(ag.storeName, ag.agencyName) AS agentDisplayName,
                    ag.agencyName,
                    ag.id AS agentTableId,
                    g.guideName,
                    ra.room_number, ra.room_type, ra.luggage, ra.tipping, ra.remarks AS roomRemarks
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN booking_travelers bt ON bt.transactNo = b.bookingId
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN agent ag ON ag.id = b.agentId
                LEFT JOIN guides g ON b.guideId = g.guideId
                LEFT JOIN rooming_assignments ra
                    ON ra.booking_traveler_id = bt.bookingTravelerId
                    AND ra.departure_date = b.departureDate
                WHERE DATE(b.departureDate) = ?
                  AND b.packageId = ?
                  AND (a.accountType = 'agent' OR b.price_tier = 'B2B' OR b.agentId IS NOT NULL)
                  AND b.bookingStatus IN ('confirmed','completed','waiting_balance','checking_balance','waiting_full_payment','checking_full_payment','waiting_second_payment','checking_second_payment')
                ORDER BY ag.agencyName, b.bookingId, bt.bookingTravelerId";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $departureDate, $packageId);
        $stmt->execute();
        $result = $stmt->get_result();

        $agentsMap = [];
        $totalAdults = 0;
        $totalChildren = 0;
        $totalInfants = 0;
        $packageName = '';
        $accommodation = '';
        $durationDays = 0;
        $guideName = '';
        $processedBookings = [];

        while ($row = $result->fetch_assoc()) {
            if (empty($packageName)) $packageName = $row['packageName'] ?? '';
            if (empty($accommodation)) $accommodation = $row['common_accommodation_name'] ?? '';
            if (empty($durationDays)) $durationDays = (int)($row['duration_days'] ?? 0);
            if (empty($guideName) && !empty($row['guideName'])) $guideName = $row['guideName'];

            // 예약별 PAX 중복 방지
            $bid = $row['bookingId'];
            if (!isset($processedBookings[$bid])) {
                $processedBookings[$bid] = true;
                $totalAdults += (int)$row['adults'];
                $totalChildren += (int)$row['children'];
                $totalInfants += (int)$row['infants'];
            }

            if (empty($row['bookingTravelerId'])) continue;

            $agentName = $row['agentDisplayName'] ?? $row['agencyName'] ?? 'Unknown';

            // Luggage 기본값: rooming_assignments 우선, 없으면 예약 데이터에서 추출
            $luggage = $row['luggage'];
            if ($luggage === null || $luggage === '') {
                $luggage = '';
                $seatReq = trim((string)($row['seatRequest'] ?? ''));
                if ($seatReq !== '') $luggage = $seatReq;
            }

            // Remarks 기본값: rooming_assignments 우선, 없으면 예약/여행객 데이터에서 추출
            $remarks = $row['roomRemarks'];
            if ($remarks === null || $remarks === '') {
                $parts = [];
                $sr = trim((string)($row['specialRequests'] ?? ''));
                $tr = trim((string)($row['travelerRequests'] ?? ''));
                if ($sr !== '') $parts[] = $sr;
                if ($tr !== '' && $tr !== $sr) $parts[] = $tr;
                $remarks = implode('; ', $parts);
            }

            // roomOptions JSON 파싱 (selectedRooms 컬럼 → roomOption 컬럼 → selectedOptions JSON 내 selectedRooms)
            $selectedRooms = [];
            $rawRooms = $row['selectedRooms'] ?? '';
            if (empty($rawRooms) || $rawRooms === '[]') {
                $rawRooms = $row['roomOption'] ?? '';
            }
            if (!empty($rawRooms) && $rawRooms !== '[]') {
                $decoded = json_decode($rawRooms, true);
                if (is_array($decoded)) {
                    $selectedRooms = $decoded;
                } else {
                    // 잘린 JSON 복구: 완전한 객체만 추출
                    preg_match_all('/\{[^}]+\}/', $rawRooms, $matches);
                    foreach ($matches[0] as $objStr) {
                        $obj = json_decode($objStr, true);
                        if ($obj && isset($obj['roomId'])) {
                            $selectedRooms[] = $obj;
                        }
                    }
                }
            }
            // fallback: selectedOptions JSON 내 selectedRooms
            if (empty($selectedRooms)) {
                $rawSelOpts = $row['selectedOptions'] ?? '';
                if (!empty($rawSelOpts)) {
                    $selOptsDecoded = json_decode($rawSelOpts, true);
                    if (is_array($selOptsDecoded)) {
                        $rooms = $selOptsDecoded['selectedRooms'] ?? [];
                        if (is_array($rooms) && !empty($rooms)) {
                            $selectedRooms = $rooms;
                        }
                    }
                }
            }

            if (!isset($agentsMap[$agentName])) {
                $agentsMap[$agentName] = [
                    'agentName' => $agentName,
                    'travelers' => []
                ];
            }

            $agentsMap[$agentName]['travelers'][] = [
                'bookingTravelerId' => (int)$row['bookingTravelerId'],
                'bookingId' => $row['bookingId'],
                'title' => $row['title'] ?? '',
                'firstName' => $row['firstName'] ?? '',
                'lastName' => $row['lastName'] ?? '',
                'birthDate' => $row['birthDate'] ?? '',
                'gender' => $row['gender'] ?? '',
                'nationality' => $row['nationality'] ?? '',
                'passportNumber' => $row['passportNumber'] ?? '',
                'passportIssueDate' => $row['passportIssueDate'] ?? '',
                'passportExpiry' => $row['passportExpiry'] ?? '',
                'travelerType' => $row['travelerType'] ?? 'adult',
                'roomNumber' => $row['room_number'] !== null ? (int)$row['room_number'] : null,
                'roomType' => $row['room_type'] ?? null,
                'luggage' => $luggage,
                'tipping' => $row['tipping'] ?? '',
                'remarks' => $remarks,
                'infantSeat' => (int)($row['infantSeat'] ?? 0),
                'childRoom' => (int)($row['childRoom'] ?? 0),
                'contactEmail' => $row['contactEmail'] ?? '',
                'contactPhone' => $row['contactPhone'] ?? '',
                'roomOptions' => $selectedRooms
            ];
        }
        $stmt->close();

        // booking_traveler_options 조회 (Baggage, eSIM, Flight Meal 등)
        $bookingIds = array_keys($processedBookings);
        $travelerOptionsMap = []; // "bookingId|travelerIndex" => [{category, option}]
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $types = str_repeat('s', count($bookingIds));
            $optSql = "SELECT bto.booking_id, bto.traveler_index,
                              ao.option_name, aoc.category_name
                       FROM booking_traveler_options bto
                       JOIN airline_options ao ON bto.option_id = ao.option_id
                       JOIN airline_option_categories aoc ON ao.category_id = aoc.category_id
                       WHERE bto.booking_id IN ($placeholders)
                       ORDER BY bto.booking_id, bto.traveler_index, aoc.sort_order, ao.sort_order";
            $optStmt = $conn->prepare($optSql);
            $optStmt->bind_param($types, ...$bookingIds);
            $optStmt->execute();
            $optRes = $optStmt->get_result();
            while ($optRow = $optRes->fetch_assoc()) {
                $key = $optRow['booking_id'] . '|' . $optRow['traveler_index'];
                if (!isset($travelerOptionsMap[$key])) $travelerOptionsMap[$key] = [];
                $travelerOptionsMap[$key][] = [
                    'category' => $optRow['category_name'],
                    'option' => $optRow['option_name']
                ];
            }
            $optStmt->close();
        }

        // booking_travelers의 index 매핑 → flightOptions 문자열 생성
        // bookingId별 traveler를 순서대로 세어 traveler_index 매핑
        $travelerIndexByBooking = []; // bookingId => counter
        $flightOptionsById = []; // bookingTravelerId => flightOptions string
        foreach ($agentsMap as $agent) {
            foreach ($agent['travelers'] as $t) {
                $bid = $t['bookingId'];
                if (!isset($travelerIndexByBooking[$bid])) $travelerIndexByBooking[$bid] = 0;
                $idx = $travelerIndexByBooking[$bid]++;
                $key = $bid . '|' . $idx;
                $opts = $travelerOptionsMap[$key] ?? [];
                $optionTexts = [];
                foreach ($opts as $opt) {
                    $optionTexts[] = $opt['category'] . ': ' . $opt['option'];
                }
                $flightOptionsById[$t['bookingTravelerId']] = implode(', ', $optionTexts);
            }
        }
        // flightOptions를 traveler 배열에 추가
        foreach ($agentsMap as &$agent) {
            foreach ($agent['travelers'] as &$t) {
                $t['flightOptions'] = $flightOptionsById[$t['bookingTravelerId']] ?? '';
            }
            unset($t);
        }
        unset($agent);

        // 에이전트 목록 정리
        $agents = [];
        foreach ($agentsMap as $agent) {
            $agent['paxCount'] = count($agent['travelers']);
            $agents[] = $agent;
        }

        // 항공편 정보
        $flights = ['departure' => null, 'return' => null];
        $fStmt = $conn->prepare("SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination FROM package_flights WHERE package_id = ? ORDER BY flight_type, flight_id");
        if ($fStmt) {
            $fStmt->bind_param('i', $packageId);
            $fStmt->execute();
            $fRes = $fStmt->get_result();
            while ($fRow = $fRes->fetch_assoc()) {
                if ($fRow['flight_type'] === 'departure' && $flights['departure'] === null) {
                    $flights['departure'] = $fRow;
                }
                if ($fRow['flight_type'] === 'return' && $flights['return'] === null) {
                    $flights['return'] = $fRow;
                }
            }
            $fStmt->close();
        }

        // 귀국일 계산
        $returnDate = '';
        if ($durationDays > 0) {
            $returnDate = date('Y-m-d', strtotime($departureDate . ' + ' . ($durationDays - 1) . ' days'));
        }

        $totalPax = $totalAdults + $totalChildren + $totalInfants;

        $summary = [
            'departureDate' => $departureDate,
            'returnDate' => $returnDate,
            'packageName' => $packageName,
            'accommodation' => $accommodation,
            'totalPax' => $totalPax,
            'adults' => $totalAdults,
            'children' => $totalChildren,
            'infants' => $totalInfants,
            'guideName' => $guideName,
            'flights' => $flights
        ];

        send_json_response([
            'success' => true,
            'summary' => $summary,
            'agents' => $agents
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get rooming list: ' . $e->getMessage(), 500);
    }
}

function saveRoomingAssignments($conn, $input) {
    try {
        $departureDate = $input['departureDate'] ?? '';
        $packageId = $input['packageId'] ?? '';
        $assignments = $input['assignments'] ?? [];

        if (empty($departureDate) || empty($packageId)) {
            send_error_response('departureDate and packageId are required', 400);
        }
        if (!is_array($assignments) || empty($assignments)) {
            send_error_response('assignments array is required', 400);
        }

        $packageId = (int)$packageId;

        $sql = "INSERT INTO rooming_assignments (departure_date, package_id, booking_traveler_id, room_number, room_type, luggage, tipping, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    room_number = VALUES(room_number),
                    room_type = VALUES(room_type),
                    luggage = VALUES(luggage),
                    tipping = VALUES(tipping),
                    remarks = VALUES(remarks),
                    package_id = VALUES(package_id)";

        $stmt = $conn->prepare($sql);
        $savedCount = 0;

        foreach ($assignments as $a) {
            $travelerId = (int)($a['bookingTravelerId'] ?? 0);
            if ($travelerId <= 0) continue;

            $roomNumber = isset($a['roomNumber']) && $a['roomNumber'] !== '' && $a['roomNumber'] !== null ? (int)$a['roomNumber'] : null;
            $roomType = $a['roomType'] ?? null;
            if ($roomType !== null && $roomType !== '') {
                $roomType = substr($roomType, 0, 50);
            } else {
                $roomType = null;
            }
            $luggage = $a['luggage'] ?? null;
            $tipping = $a['tipping'] ?? null;
            $remarks = $a['remarks'] ?? null;

            $stmt->bind_param('siisssss', $departureDate, $packageId, $travelerId, $roomNumber, $roomType, $luggage, $tipping, $remarks);
            $stmt->execute();
            $savedCount++;
        }
        $stmt->close();

        send_success_response(['savedCount' => $savedCount], "Saved $savedCount assignments successfully");
    } catch (Exception $e) {
        send_error_response('Failed to save rooming assignments: ' . $e->getMessage(), 500);
    }
}

// ========== B2B Booking Detail - Extra Options / Visa / Room (Super Admin) ==========

function getExtraOptionsDetailSuper($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    try {
        // 예약 기본 정보 조회 (소유권 체크 없음 - super admin)
        $sql = "SELECT b.bookingId, b.departureDate, b.flightOptionFee,
                       b.bookingStatus, b.adults, b.children, b.infants,
                       b.packageId, p.packageName,
                       DATE_ADD(b.departureDate, INTERVAL (COALESCE(p.duration_days, p.durationDays, 1) - 1) DAY) as returnDate
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE b.bookingId = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) send_error_response('Reservation not found', 404);

        // Traveler 정보 조회
        $travelersStmt = $conn->prepare("SELECT * FROM booking_travelers WHERE transactNo = ? ORDER BY CASE travelerType WHEN 'adult' THEN 1 WHEN 'child' THEN 2 WHEN 'infant' THEN 3 ELSE 99 END, isMainTraveler DESC, bookingTravelerId ASC");
        $travelersStmt->bind_param('s', $bookingId);
        $travelersStmt->execute();
        $travelersResult = $travelersStmt->get_result();
        $travelers = [];
        while ($t = $travelersResult->fetch_assoc()) {
            $travelers[] = [
                'firstName' => $t['firstName'] ?? '',
                'lastName' => $t['lastName'] ?? '',
                'travelerType' => $t['travelerType'] ?? 'adult',
                'isMainTraveler' => intval($t['isMainTraveler'] ?? 0)
            ];
        }
        $travelersStmt->close();

        // 여행자별 기존 옵션 조회
        $optionsSql = "SELECT traveler_index, option_id, price FROM booking_traveler_options WHERE booking_id = ?";
        $optionsStmt = $conn->prepare($optionsSql);
        $optionsStmt->bind_param('s', $bookingId);
        $optionsStmt->execute();
        $optionsResult = $optionsStmt->get_result();
        $travelerOptions = [];
        while ($opt = $optionsResult->fetch_assoc()) {
            $idx = intval($opt['traveler_index']);
            if (!isset($travelerOptions[$idx])) $travelerOptions[$idx] = [];
            $travelerOptions[$idx][] = [
                'optionId' => intval($opt['option_id']),
                'price' => floatval($opt['price'])
            ];
        }
        $optionsStmt->close();

        // 항공사 이름 추출
        $airlineName = '';
        $flightStmt = $conn->prepare("SELECT airline_name FROM package_flights WHERE package_id = ? LIMIT 1");
        if ($flightStmt) {
            $flightStmt->bind_param('i', $booking['packageId']);
            $flightStmt->execute();
            $flightRow = $flightStmt->get_result()->fetch_assoc();
            if ($flightRow) $airlineName = $flightRow['airline_name'] ?? '';
            $flightStmt->close();
        }

        // 항공사 옵션 카테고리 조회
        $categories = [];
        if (!empty($airlineName)) {
            $catSql = "SELECT category_id, category_name, category_name_en FROM airline_option_categories WHERE airline_name = ? AND is_active = 1 ORDER BY sort_order, category_id";
            $catStmt = $conn->prepare($catSql);
            $catStmt->bind_param('s', $airlineName);
            $catStmt->execute();
            $catResult = $catStmt->get_result();
            while ($cat = $catResult->fetch_assoc()) {
                $optSql2 = "SELECT option_id, option_name, option_name_en, price FROM airline_options WHERE category_id = ? AND is_active = 1 ORDER BY sort_order, option_id";
                $optStmt2 = $conn->prepare($optSql2);
                $optStmt2->bind_param('i', $cat['category_id']);
                $optStmt2->execute();
                $optResult2 = $optStmt2->get_result();
                $opts = [];
                while ($o = $optResult2->fetch_assoc()) {
                    $o['price'] = floatval($o['price']);
                    $o['option_id'] = intval($o['option_id']);
                    $opts[] = $o;
                }
                $optStmt2->close();
                $cat['category_id'] = intval($cat['category_id']);
                $cat['options'] = $opts;
                $categories[] = $cat;
            }
            $catStmt->close();
        }

        // 옵션 결제 상태 조회
        $paymentInfo = null;
        $payStmt = $conn->prepare("SELECT * FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
        $payStmt->bind_param('s', $bookingId);
        $payStmt->execute();
        $payRow = $payStmt->get_result()->fetch_assoc();
        $payStmt->close();
        if ($payRow) {
            $paymentInfo = [
                'status' => $payRow['option_payment_status'],
                'amount' => floatval($payRow['option_payment_amount']),
                'file' => $payRow['option_payment_file'],
                'fileName' => $payRow['option_payment_file_name'],
                'uploadedAt' => $payRow['option_payment_uploaded_at'],
                'confirmedAt' => $payRow['option_payment_confirmed_at'],
                'rejectedAt' => $payRow['option_payment_rejected_at'],
                'rejectionReason' => $payRow['option_payment_rejection_reason']
            ];
        }

        // Extra options 변경 이력 조회
        $history = [];
        try {
            $hStmt = $conn->prepare("SELECT description, changedBy, changedByType, createdAt FROM booking_history WHERE bookingId = ? AND changeType = 'extra_options' ORDER BY createdAt DESC LIMIT 20");
            $hStmt->bind_param('s', $bookingId);
            $hStmt->execute();
            $hResult = $hStmt->get_result();
            while ($h = $hResult->fetch_assoc()) {
                $history[] = $h;
            }
            $hStmt->close();
        } catch (Throwable $e) {}

        send_success_response([
            'booking' => [
                'bookingId' => $booking['bookingId'],
                'packageName' => $booking['packageName'] ?? '',
                'departureDate' => $booking['departureDate'],
                'returnDate' => $booking['returnDate'] ?? '',
                'pax' => intval($booking['adults'] ?? 0) + intval($booking['children'] ?? 0) + intval($booking['infants'] ?? 0),
                'adults' => intval($booking['adults'] ?? 0),
                'children' => intval($booking['children'] ?? 0),
                'infants' => intval($booking['infants'] ?? 0),
                'flightOptionFee' => floatval($booking['flightOptionFee'] ?? 0)
            ],
            'travelers' => $travelers,
            'travelerOptions' => $travelerOptions,
            'airlineName' => $airlineName,
            'categories' => $categories,
            'paymentInfo' => $paymentInfo,
            'history' => $history
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to load extra options detail: ' . $e->getMessage(), 500);
    }
}

function saveExtraOptionsSuper($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    $travelerOptions = $input['travelerOptions'] ?? [];
    $totalOptionFee = floatval($input['totalOptionFee'] ?? 0);

    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    // 예약 존재 확인
    $chk = $conn->prepare("SELECT bookingId, DATE(departureDate) as depDate, COALESCE(flightOptionFee, 0) as prevFee FROM bookings WHERE bookingId = ? LIMIT 1");
    $chk->bind_param('s', $bookingId);
    $chk->execute();
    $chkRow = $chk->get_result()->fetch_assoc();
    if (!$chkRow) {
        $chk->close();
        send_error_response('Reservation not found', 404);
    }
    $chk->close();

    // 마감 체크: admin_kr은 출발 전까지, 나머지는 7일 전까지
    $depDate = $chkRow['depDate'] ?? '';
    if ($depDate !== '') {
        $userType = $_SESSION['admin_userType'] ?? '';
        $cutoffDays = ($userType === 'admin_kr') ? 0 : 7;
        $depTs = strtotime($depDate);
        $cutoffTs = $depTs - ($cutoffDays * 86400);
        if (time() >= $cutoffTs) {
            send_error_response('Extra options can only be changed up to ' . $cutoffDays . ' days before departure', 400);
        }
    }

    try {
        $conn->begin_transaction();

        $delStmt = $conn->prepare("DELETE FROM booking_traveler_options WHERE booking_id = ?");
        $delStmt->bind_param('s', $bookingId);
        $delStmt->execute();
        $delStmt->close();

        if (!empty($travelerOptions)) {
            $insStmt = $conn->prepare("INSERT INTO booking_traveler_options (booking_id, traveler_index, option_id, price) VALUES (?, ?, ?, ?)");
            foreach ($travelerOptions as $item) {
                $travelerIndex = intval($item['travelerIndex']);
                $optionId = intval($item['optionId']);
                $price = floatval($item['price'] ?? 0);
                $insStmt->bind_param('siid', $bookingId, $travelerIndex, $optionId, $price);
                $insStmt->execute();
            }
            $insStmt->close();
        }

        $feeStmt = $conn->prepare("UPDATE bookings SET flightOptionFee = ? WHERE bookingId = ?");
        $feeStmt->bind_param('ds', $totalOptionFee, $bookingId);
        $feeStmt->execute();
        $feeStmt->close();

        $newStatus = (!empty($travelerOptions) && $totalOptionFee > 0) ? 'pending_payment' : 'not_set';
        $upsertSql = "INSERT INTO booking_option_payments (booking_id, option_payment_status, option_payment_amount)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE
                           option_payment_status = VALUES(option_payment_status),
                           option_payment_amount = VALUES(option_payment_amount),
                           option_payment_file = NULL,
                           option_payment_file_name = NULL,
                           option_payment_uploaded_at = NULL,
                           option_payment_rejected_at = NULL,
                           option_payment_rejection_reason = NULL";
        $upsertStmt = $conn->prepare($upsertSql);
        $upsertStmt->bind_param('ssd', $bookingId, $newStatus, $totalOptionFee);
        $upsertStmt->execute();
        $upsertStmt->close();

        // booking_history에 이력 기록
        try {
            $prevFee = floatval($chkRow['prevFee'] ?? 0);
            $adminName = '';
            $anStmt = $conn->prepare("SELECT CONCAT(COALESCE(firstName,''),' ',COALESCE(lastName,'')) as name FROM accounts WHERE accountId = ? LIMIT 1");
            $anStmt->bind_param('i', $adminAccountId);
            $anStmt->execute();
            $anRow = $anStmt->get_result()->fetch_assoc();
            $anStmt->close();
            if ($anRow) $adminName = trim($anRow['name']);

            $desc = 'Extra options updated by Admin' . ($adminName ? " ({$adminName})" : '') . '. Option fee: ₱' . number_format($prevFee, 0) . ' → ₱' . number_format($totalOptionFee, 0);
            $hStmt = $conn->prepare("INSERT INTO booking_history (bookingId, description, changeType, changedBy, changedByType) VALUES (?, ?, 'extra_options', ?, 'admin')");
            $hStmt->bind_param('sss', $bookingId, $desc, $adminName);
            $hStmt->execute();
            $hStmt->close();
        } catch (Throwable $e) {
            error_log('Failed to log extra options history: ' . $e->getMessage());
        }

        $conn->commit();
        send_success_response(['status' => $newStatus, 'amount' => $totalOptionFee], 'Extra options saved successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to save extra options: ' . $e->getMessage(), 500);
    }
}

function uploadOptionPaymentProofSuper($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        send_error_response('File upload failed');
    }

    // 예약 존재 확인
    $chk = $conn->prepare("SELECT bookingId FROM bookings WHERE bookingId = ? LIMIT 1");
    $chk->bind_param('s', $bookingId);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        send_error_response('Reservation not found', 404);
    }
    $chk->close();

    try {
        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/../../../uploads/payment/options/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $originalFileName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            send_error_response('Invalid file type. Allowed: jpg, jpeg, png, gif, pdf');
        }

        $newFileName = 'option_' . $bookingId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            send_error_response('Failed to save uploaded file');
        }

        $filePath = 'uploads/payment/options/' . $newFileName;
        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE booking_option_payments
                SET option_payment_file = ?, option_payment_file_name = ?,
                    option_payment_uploaded_at = ?, option_payment_status = 'checking',
                    option_payment_rejected_at = NULL, option_payment_rejection_reason = NULL
                WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $filePath, $originalFileName, $now, $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response([
            'file' => $filePath,
            'fileName' => $originalFileName,
            'uploadedAt' => $now,
            'status' => 'checking'
        ], 'Payment proof uploaded successfully');
    } catch (Exception $e) {
        send_error_response('Failed to upload payment proof: ' . $e->getMessage(), 500);
    }
}

function deleteOptionPaymentProofSuper($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    // 현재 파일 경로 조회
    $chk = $conn->prepare("SELECT option_payment_file, option_payment_status FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
    $chk->bind_param('s', $bookingId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) send_error_response('Record not found', 404);

    if ($row['option_payment_status'] === 'confirmed') {
        send_error_response('Cannot delete proof file after confirmation', 403);
    }

    try {
        if (!empty($row['option_payment_file'])) {
            $fullPath = __DIR__ . '/../../../' . $row['option_payment_file'];
            if (file_exists($fullPath)) @unlink($fullPath);
        }

        $stmt = $conn->prepare("UPDATE booking_option_payments
                                SET option_payment_file = NULL, option_payment_file_name = NULL,
                                    option_payment_uploaded_at = NULL, option_payment_status = 'pending_payment'
                                WHERE booking_id = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response(['status' => 'pending_payment'], 'Payment proof deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete payment proof: ' . $e->getMessage(), 500);
    }
}

function getVisaApplicationsByBookingIdSuper($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (empty($adminAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? null;
    if (empty($bookingId)) send_error_response('Booking ID is required');

    // visa_applications 조회 + booking_travelers JOIN (소유권 체크 없음 - super admin)
    $sql = "SELECT
                v.applicationId,
                v.transactNo,
                v.bookingTravelerId,
                v.applicantName,
                v.visaType,
                v.status,
                v.notes,
                v.visaSend,
                v.createdAt,
                v.updatedAt,
                bt.travelerType,
                bt.firstName AS btFirstName,
                bt.lastName AS btLastName
            FROM visa_applications v
            LEFT JOIN booking_travelers bt ON v.bookingTravelerId = bt.bookingTravelerId
            WHERE v.transactNo = ?
            ORDER BY v.applicationId ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $uiStatus = mapVisaDbToAdminUiStatus($row['status'] ?? 'pending');
        $documents = extractVisaDocumentsFromNotes($row['notes']);
        $visaFile = extractVisaFileFromNotes($row['notes']);

        $applications[] = [
            'applicationId' => (int)$row['applicationId'],
            'transactNo'    => $row['transactNo'],
            'bookingTravelerId' => $row['bookingTravelerId'] ? (int)$row['bookingTravelerId'] : null,
            'applicantName' => $row['applicantName'] ?? '',
            'visaType'      => $row['visaType'] ?? '',
            'status'        => $uiStatus,
            'dbStatus'      => $row['status'] ?? 'pending',
            'visaSend'      => (int)($row['visaSend'] ?? 0),
            'documents'     => $documents,
            'visaFile'      => $visaFile,
            'travelerType'  => $row['travelerType'] ?? 'adult',
            'btFirstName'   => $row['btFirstName'] ?? '',
            'btLastName'    => $row['btLastName'] ?? '',
            'createdAt'     => $row['createdAt'] ?? '',
            'updatedAt'     => $row['updatedAt'] ?? ''
        ];
    }
    $stmt->close();

    send_success_response([
        'applications' => $applications,
        'totalCount'   => count($applications)
    ]);
}

function getRoomingAssignmentsSuper($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        if (empty($adminAccountId)) send_error_response('Login required', 401);

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) send_error_response('Booking ID is required', 400);

        // 예약 정보 조회 (소유권 체크 없음 - super admin)
        $checkSql = "SELECT bookingId, departureDate, packageId FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        $departureDate = $booking['departureDate'] ?? '';
        if (empty($departureDate)) {
            send_success_response(['assignments' => []]);
            return;
        }

        // booking_travelers에서 해당 예약의 traveler ID 목록 조회
        $travelerIds = [];
        $tSql = "SELECT bookingTravelerId FROM booking_travelers WHERE transactNo = ?";
        $tStmt = $conn->prepare($tSql);
        $tStmt->bind_param('s', $bookingId);
        $tStmt->execute();
        $tResult = $tStmt->get_result();
        while ($row = $tResult->fetch_assoc()) {
            $travelerIds[] = (int)$row['bookingTravelerId'];
        }
        $tStmt->close();

        if (empty($travelerIds)) {
            send_success_response(['assignments' => []]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($travelerIds), '?'));
        $types = str_repeat('i', count($travelerIds));
        $sql = "SELECT booking_traveler_id AS bookingTravelerId, room_type AS roomType, room_number AS roomNumber, remarks
                FROM rooming_assignments
                WHERE departure_date = ? AND booking_traveler_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $bindTypes = 's' . $types;
        $bindParams = array_merge([$departureDate], $travelerIds);
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $res = $stmt->get_result();

        $assignments = [];
        while ($row = $res->fetch_assoc()) {
            $assignments[] = [
                'bookingTravelerId' => (int)$row['bookingTravelerId'],
                'roomType' => $row['roomType'],
                'roomNumber' => $row['roomNumber'] !== null ? (int)$row['roomNumber'] : null,
                'remarks' => $row['remarks']
            ];
        }
        $stmt->close();

        send_success_response(['assignments' => $assignments]);
    } catch (Exception $e) {
        send_error_response('Failed to get rooming assignments: ' . $e->getMessage(), 500);
    }
}

function saveRoomingAssignmentsByBookingSuper($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        if (empty($adminAccountId)) send_error_response('Login required', 401);

        $bookingId = $input['bookingId'] ?? '';
        $assignments = $input['assignments'] ?? [];

        if (empty($bookingId)) send_error_response('Booking ID is required', 400);
        if (!is_array($assignments)) send_error_response('assignments array is required', 400);

        // 예약 정보 조회 (소유권 체크 없음 - super admin)
        $checkSql = "SELECT bookingId, departureDate, packageId FROM bookings WHERE bookingId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        $departureDate = $booking['departureDate'] ?? '';
        $packageId = (int)($booking['packageId'] ?? 0);

        if (empty($departureDate)) {
            send_error_response('Departure date not found for this booking', 400);
        }

        // 해당 예약의 traveler ID 목록 조회
        $travelerIds = [];
        $tSql = "SELECT bookingTravelerId FROM booking_travelers WHERE transactNo = ?";
        $tStmt = $conn->prepare($tSql);
        $tStmt->bind_param('s', $bookingId);
        $tStmt->execute();
        $tResult = $tStmt->get_result();
        while ($row = $tResult->fetch_assoc()) {
            $travelerIds[] = (int)$row['bookingTravelerId'];
        }
        $tStmt->close();

        // 기존 배정 삭제
        if (!empty($travelerIds)) {
            $placeholders = implode(',', array_fill(0, count($travelerIds), '?'));
            $types = str_repeat('i', count($travelerIds));
            $delSql = "DELETE FROM rooming_assignments WHERE departure_date = ? AND booking_traveler_id IN ($placeholders)";
            $delStmt = $conn->prepare($delSql);
            $bindTypes = 's' . $types;
            $bindParams = array_merge([$departureDate], $travelerIds);
            $delStmt->bind_param($bindTypes, ...$bindParams);
            $delStmt->execute();
            $delStmt->close();
        }

        // 새 배정 삽입
        $savedCount = 0;
        if (!empty($assignments)) {
            $sql = "INSERT INTO rooming_assignments (departure_date, package_id, booking_traveler_id, room_number, room_type, luggage, tipping, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        room_number = VALUES(room_number),
                        room_type = VALUES(room_type),
                        luggage = VALUES(luggage),
                        tipping = VALUES(tipping),
                        remarks = VALUES(remarks),
                        package_id = VALUES(package_id)";

            $stmt = $conn->prepare($sql);

            foreach ($assignments as $a) {
                $travelerId = (int)($a['bookingTravelerId'] ?? 0);
                if ($travelerId <= 0) continue;

                // 소유권 확인: 해당 traveler가 이 예약에 속하는지
                if (!in_array($travelerId, $travelerIds)) continue;

                $roomNumber = isset($a['roomNumber']) && $a['roomNumber'] !== '' && $a['roomNumber'] !== null ? (int)$a['roomNumber'] : null;
                $roomType = $a['roomType'] ?? null;
                if ($roomType !== null && $roomType !== '') {
                    $roomType = substr($roomType, 0, 50);
                } else {
                    $roomType = null;
                }
                $luggage = $a['luggage'] ?? null;
                $tipping = $a['tipping'] ?? null;
                $remarks = $a['remarks'] ?? null;

                $stmt->bind_param('siisssss', $departureDate, $packageId, $travelerId, $roomNumber, $roomType, $luggage, $tipping, $remarks);
                $stmt->execute();
                $savedCount++;
            }
            $stmt->close();
        }

        send_success_response(['savedCount' => $savedCount], "Saved $savedCount assignments successfully");
    } catch (Exception $e) {
        send_error_response('Failed to save rooming assignments: ' . $e->getMessage(), 500);
    }
}
