<?php
/**
 * Traveler File Delete API
 * 여행자의 여권사진 또는 비자 문서 파일을 서버에서 삭제합니다.
 */

// 에러 설정
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// JSON 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 세션 확인 (선택적 - 보안을 위해 활성화 권장)
session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['agent_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// JSON 입력 파싱
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['fileUrl'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'fileUrl is required']);
    exit;
}

$fileUrl = $input['fileUrl'];
$fileType = $input['fileType'] ?? 'unknown'; // 'passport' or 'visa'

// URL에서 파일 경로 추출
// 예: /uploads/passports/file.jpg, uploads/passports/file.jpg, https://domain.com/uploads/passports/file.jpg
$filePath = '';

// URL 정규화
$fileUrl = str_replace('\\', '/', $fileUrl);

// 절대 URL인 경우 경로 부분만 추출
if (preg_match('/^https?:\/\//', $fileUrl)) {
    $parsed = parse_url($fileUrl);
    $fileUrl = $parsed['path'] ?? '';
}

// 앞의 슬래시 제거
$fileUrl = ltrim($fileUrl, '/');

// 허용된 디렉토리 확인 (보안)
$allowedPaths = ['uploads/passports', 'uploads/visas', 'uploads/travelers', 'uploads'];
$isAllowed = false;

foreach ($allowedPaths as $allowedPath) {
    if (strpos($fileUrl, $allowedPath) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'File path not allowed']);
    exit;
}

// 상위 디렉토리 접근 방지
if (strpos($fileUrl, '..') !== false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid file path']);
    exit;
}

// 실제 파일 경로 생성
$baseDir = realpath(__DIR__ . '/../../../');
$fullPath = $baseDir . '/' . $fileUrl;

// 파일 존재 확인 및 삭제
if (file_exists($fullPath)) {
    if (unlink($fullPath)) {
        // 로그 기록
        error_log("[DELETE_FILE] Successfully deleted: {$fullPath} (type: {$fileType})");
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully',
            'deletedPath' => $fileUrl
        ]);
    } else {
        error_log("[DELETE_FILE] Failed to delete: {$fullPath}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
    }
} else {
    // 파일이 이미 없는 경우도 성공으로 처리
    error_log("[DELETE_FILE] File not found (already deleted?): {$fullPath}");
    echo json_encode([
        'success' => true,
        'message' => 'File not found (may already be deleted)',
        'requestedPath' => $fileUrl
    ]);
}
