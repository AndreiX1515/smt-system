<?php
/**
 * Google Sheets → 시스템 실시간 Webhook
 * Apps Script onEdit에서 호출됨
 *
 * 입력: { secret, packageId, date, r, app }
 * 동작: capacity = r + app → package_available_dates UPDATE
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../conn.php';

$config = require __DIR__ . '/../config/google_sheets_config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Secret 검증
$expectedSecret = $config['webhook_secret'] ?? '';
$receivedSecret = $input['secret'] ?? '';
if ($expectedSecret === '' || !hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$packageId = (int)($input['packageId'] ?? 0);
$date = trim($input['date'] ?? '');
$r = (int)($input['r'] ?? 0);
$app = (int)($input['app'] ?? 0);

if ($packageId <= 0 || $date === '') {
    http_response_code(400);
    echo json_encode(['error' => 'packageId and date required']);
    exit;
}

// 날짜 정규화
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $ts = strtotime($date);
    if ($ts === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }
    $date = date('Y-m-d', $ts);
}

$newCapacity = $r + $app;

// package_available_dates 업데이트
$stmt = $conn->prepare(
    "UPDATE package_available_dates SET capacity = ? WHERE package_id = ? AND available_date = ?"
);
$stmt->bind_param('iis', $newCapacity, $packageId, $date);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

// 행이 없으면 INSERT (open 상태로)
if ($affected === 0) {
    $checkStmt = $conn->prepare(
        "SELECT id FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1"
    );
    $checkStmt->bind_param('is', $packageId, $date);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $exists = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$exists) {
        $insertStmt = $conn->prepare(
            "INSERT INTO package_available_dates (package_id, available_date, capacity, status) VALUES (?, ?, ?, 'open')"
        );
        $insertStmt->bind_param('isi', $packageId, $date, $newCapacity);
        $insertStmt->execute();
        $insertStmt->close();
    }
}

// 로그
require_once __DIR__ . '/../lib/google_sheets.php';
gs_log("WEBHOOK packageId={$packageId} date={$date} R={$r} APP={$app} capacity={$newCapacity}");

echo json_encode(['success' => true, 'capacity' => $newCapacity]);
