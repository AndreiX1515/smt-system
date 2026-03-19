<?php
/**
 * Agent 프로필 완성 API
 * - 최초 로그인한 agent가 프로필 정보를 입력하면 저장
 */
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 세션에서 agent 정보 확인
$accountId = $_SESSION['agent_accountId'] ?? null;
if (!$accountId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please login again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 입력 파싱
$input = json_decode(file_get_contents('php://input'), true);

$agencyName = trim($input['agencyName'] ?? '');
$fName = trim($input['fName'] ?? '');
$mName = trim($input['mName'] ?? '');
$lName = trim($input['lName'] ?? '');
$personInChargeEmail = trim($input['personInChargeEmail'] ?? '');
$contactNo = trim($input['contactNo'] ?? '');

// 필수 필드 검증
if (empty($agencyName) || empty($fName) || empty($lName) || empty($personInChargeEmail) || empty($contactNo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 이메일 형식 검증
if (!filter_var($personInChargeEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // agent 테이블에서 해당 accountId로 레코드 찾기
    $checkStmt = $conn->prepare("SELECT id FROM agent WHERE accountId = ?");
    $checkStmt->bind_param("i", $accountId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $agent = $result->fetch_assoc();
    $checkStmt->close();

    $mNameValue = $mName ?: null;

    if (!$agent) {
        // 새 agentId 생성 (AGT000XXX 형식)
        $maxRes = $conn->query("SELECT agentId FROM agent ORDER BY id DESC LIMIT 1");
        $maxRow = $maxRes ? $maxRes->fetch_assoc() : null;
        if ($maxRow && preg_match('/AGT(\d+)/', $maxRow['agentId'], $m)) {
            $newAgentId = 'AGT' . str_pad((int)$m[1] + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newAgentId = 'AGT000001';
        }

        // agent 레코드가 없으면 새로 INSERT
        $insertStmt = $conn->prepare("
            INSERT INTO agent (agentId, accountId, agencyName, fName, mName, lName, personInChargeEmail, contactNo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param("sissssss", $newAgentId, $accountId, $agencyName, $fName, $mNameValue, $lName, $personInChargeEmail, $contactNo);

        if ($insertStmt->execute()) {
            $insertStmt->close();
        } else {
            throw new Exception("Failed to insert agent profile: " . $conn->error);
        }
    } else {
        // agent 레코드가 있으면 UPDATE
        $updateStmt = $conn->prepare("
            UPDATE agent
            SET agencyName = ?,
                fName = ?,
                mName = ?,
                lName = ?,
                personInChargeEmail = ?,
                contactNo = ?
            WHERE accountId = ?
        ");
        $updateStmt->bind_param("ssssssi", $agencyName, $fName, $mNameValue, $lName, $personInChargeEmail, $contactNo, $accountId);

        if ($updateStmt->execute()) {
            $updateStmt->close();
        } else {
            throw new Exception("Failed to update agent profile: " . $conn->error);
        }
    }

    // accounts 테이블의 displayName 업데이트 (fName + lName)
    $displayName = trim($fName . ' ' . $lName);
    $accountStmt = $conn->prepare("UPDATE accounts SET displayName = ? WHERE accountId = ?");
    $accountStmt->bind_param("si", $displayName, $accountId);
    $accountStmt->execute();
    $accountStmt->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile saved successfully!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Complete profile error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving profile.'
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
