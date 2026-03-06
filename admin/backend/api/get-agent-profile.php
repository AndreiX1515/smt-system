<?php
/**
 * Agent 프로필 조회 API
 * - 로그인된 에이전트의 프로필 정보를 반환
 */
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET only']);
    exit;
}

$accountId = $_SESSION['agent_accountId'] ?? null;
if (!$accountId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$stmt = $conn->prepare("SELECT agencyName, fName, mName, lName, personInChargeEmail, contactNo FROM agent WHERE accountId = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$result = $stmt->get_result();
$agent = $result->fetch_assoc();
$stmt->close();

if (!$agent) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Agent profile not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $agent
], JSON_UNESCAPED_UNICODE);
exit;
?>
