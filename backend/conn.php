<?php
// 데이터베이스 연결 설정
$servername = "localhost";
$username = "root";
$password = "password1";
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

// 시간대 설정: 필리핀 (UTC+8)
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

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

?>