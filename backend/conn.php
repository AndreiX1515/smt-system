<?php
// 데이터베이스 연결 설정
$servername = "localhost";
$username = "root";
$password = "cloud1234";
$dbname = "smarttravel";
$port = 3306; // MySQL 기본 포트

// MySQLi 연결 생성 (포트 포함)
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 연결 확인
if ($conn->connect_error) {
    $error_msg = "Database connection failed: " . $conn->connect_error . " (Error No: " . $conn->connect_errno . ")";
    
    // API 응답을 위한 JSON 에러 (API 호출인 경우)
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '데이터베이스 연결 실패',
            'error' => $conn->connect_error,
            'error_code' => $conn->connect_errno,
            'server' => $servername,
            'database' => $dbname,
            'port' => $port
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    // 일반 페이지인 경우 - 상세 에러 정보 표시
    die("
    <div style='font-family: Arial; padding: 20px; background: #f5f5f5; border: 2px solid #d32f2f; border-radius: 5px; max-width: 600px; margin: 50px auto;'>
        <h2 style='color: #d32f2f; margin-top: 0;'>데이터베이스 연결 실패</h2>
        <p><strong>에러 메시지:</strong> {$conn->connect_error}</p>
        <p><strong>에러 코드:</strong> {$conn->connect_errno}</p>
        <hr style='border: 1px solid #ddd;'>
        <h3 style='color: #333;'>연결 정보:</h3>
        <ul style='line-height: 1.8;'>
            <li><strong>서버:</strong> {$servername}</li>
            <li><strong>포트:</strong> {$port}</li>
            <li><strong>데이터베이스:</strong> {$dbname}</li>
            <li><strong>사용자:</strong> {$username}</li>
        </ul>
        <hr style='border: 1px solid #ddd;'>
        <p style='color: #666; font-size: 12px;'>확인 사항: MySQL 서버 실행 여부, 데이터베이스 존재 여부, 사용자 권한, 포트 번호</p>
    </div>
    ");
}

// UTF-8 설정
$conn->set_charset("utf8");

// 연결 성공 로그 (디버깅용 - 필요시 주석 처리)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Database connection successful to $dbname");
}

// 에러 리포팅 설정
// error_reporting(E_ALL); // 서버 설정 사용
// 연결 에러는 위에서 처리하므로 display_errors는 유지
// ini_set('display_errors', 1); // 서버 설정 사용 // 연결 에러 확인을 위해 활성화
ini_set('log_errors', 0); // 로그 파일 사용 안 함

// 세션 설정 및 시작 (session.php에서 처리)
require_once __DIR__ . '/config/session.php';

// CORS 헤더 설정 (API 호출을 위해)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 유틸리티 함수들
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

if (!function_exists('generate_transaction_no')) {
    function generate_transaction_no() {
        return 'TXN' . date('Ymd') . rand(1000, 9999);
    }
}

if (!function_exists('send_json_response')) {
    function send_json_response($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 로그 함수 - auth.php와 호환되도록 수정
if (!function_exists('log_activity')) {
    // 기존 코드/레거시 API에서 인자 개수가 제각각이라(1~3개) 호환되도록 기본값을 둔다.
    function log_activity($accountId = 0, $action = '', $details = '') {
        // 로그 파일 권한 문제로 임시 비활성화
        // $log_file = 'logs/activity.log';
        // $timestamp = date('Y-m-d H:i:s');
        // $log_message = "[$timestamp] User $accountId - $action: $details" . PHP_EOL;
        // file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
}

// 로그 디렉토리 생성
// - include 되는 위치/권한에 따라 상대경로 logs 생성이 실패하며(PHP notice) Apache error.log를 오염시킬 수 있음
// - 프로젝트 루트(/var/www/html) 하위로 고정하고, 실패 시 조용히 무시
$__logDir = realpath(__DIR__ . '/..');
if ($__logDir !== false) {
    $__logDir = rtrim($__logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($__logDir)) {
        // 권한/환경에 따라 mkdir이 실패할 수 있으므로 에러를 숨김
        @mkdir($__logDir, 0755, true);
    }
}

/**
 * 패키지+날짜 조합의 잔여 좌석을 확인하고 요청 인원 수용 가능 여부를 반환한다.
 *
 * @param mysqli $db               DB 커넥션
 * @param int    $packageId        패키지 ID
 * @param string $departureDate    출발일 (YYYY-MM-DD)
 * @param int    $requestedPax     요청 인원 (adults+children+infantsWithSeat)
 * @param string|null $excludeBookingId  제외할 bookingId (UPDATE 시 자기 자신)
 * @return array {ok:bool, remainingSeats:int, maxParticipants:int, bookedSeats:int, message:string}
 */
if (!function_exists('check_capacity')) {
    function check_capacity(mysqli $db, int $packageId, string $departureDate, int $requestedPax, ?string $excludeBookingId = null): array {
        // 1) packages.maxParticipants 조회
        $st = $db->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
        if (!$st) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => 0, 'bookedSeats' => 0, 'message' => 'DB error'];
        }
        $st->bind_param('i', $packageId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();

        if (!$row) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => 0, 'bookedSeats' => 0, 'message' => 'Package not found'];
        }

        $packageMaxParticipants = (int)($row['maxParticipants'] ?? 0);
        $maxParticipants = $packageMaxParticipants;

        // 2) package_available_dates.capacity (per-date override)
        // dev_tasks #115: packages.maxParticipants=0 이면 per-date capacity 무시 (0 유지)
        if ($packageMaxParticipants > 0) {
            try {
                $tbl = $db->query("SHOW TABLES LIKE 'package_available_dates'");
                if ($tbl && $tbl->num_rows > 0) {
                    $st2 = $db->prepare("SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                    if ($st2) {
                        $st2->bind_param('is', $packageId, $departureDate);
                        $st2->execute();
                        $rs2 = $st2->get_result();
                        $r2 = $rs2 ? $rs2->fetch_assoc() : null;
                        $st2->close();
                        if ($r2 && isset($r2['capacity']) && $r2['capacity'] !== null) {
                            $maxParticipants = (int)$r2['capacity'];
                        }
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        // 3) 활성 예약 합산 (cancelled/rejected/refunded 제외)
        $excludeCond = '';
        $types = 'si';
        $params = [$departureDate, $packageId];
        if ($excludeBookingId !== null && $excludeBookingId !== '') {
            $excludeCond = "AND b.bookingId <> ?";
            $types = 'sis';
            $params[] = $excludeBookingId;
        }

        $sql = "SELECT COALESCE(SUM(COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infantsWithSeat,0)), 0) AS bookedSeats
                FROM bookings b
                WHERE b.packageId = ?
                  AND DATE(b.departureDate) = ?
                  AND b.bookingStatus NOT IN ('cancelled','rejected')
                  AND b.paymentStatus <> 'refunded'
                  {$excludeCond}";
        // bind order: packageId, departureDate, [excludeBookingId]
        $st3 = $db->prepare($sql);
        if (!$st3) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => $maxParticipants, 'bookedSeats' => 0, 'message' => 'DB error'];
        }
        if ($excludeBookingId !== null && $excludeBookingId !== '') {
            $st3->bind_param('iss', $packageId, $departureDate, $excludeBookingId);
        } else {
            $st3->bind_param('is', $packageId, $departureDate);
        }
        $st3->execute();
        $rs3 = $st3->get_result();
        $r3 = $rs3 ? $rs3->fetch_assoc() : null;
        $st3->close();

        $bookedSeats = (int)($r3['bookedSeats'] ?? 0);
        $remainingSeats = max($maxParticipants - $bookedSeats, 0);
        $ok = ($remainingSeats >= $requestedPax);

        return [
            'ok' => $ok,
            'remainingSeats' => $remainingSeats,
            'maxParticipants' => $maxParticipants,
            'bookedSeats' => $bookedSeats,
            'message' => $ok ? 'OK' : 'Not enough seats (remaining: ' . $remainingSeats . ', requested: ' . $requestedPax . ')',
        ];
    }
}

?>