<?php
require __DIR__ . '/../conn.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. 먼저 기존 가이드 계정 확인
    $checkQuery = "SELECT a.accountId, a.emailAddress, a.accountType, g.guideName
                   FROM accounts a
                   INNER JOIN guides g ON a.accountId = g.accountId
                   WHERE a.accountType = 'guide'
                   LIMIT 1";

    $result = $conn->query($checkQuery);

    if ($result && $result->num_rows > 0) {
        $guide = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => 'Guide account already exists',
            'guide' => $guide,
            'loginInfo' => [
                'email' => $guide['emailAddress'],
                'note' => 'Use test mode or check password in database'
            ],
            'testUrl' => 'http://localhost/smt-escape/admin/guide/full-list.html?test_guide=1'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // 2. 가이드 계정이 없으면 새로 생성
    $email = 'test.guide@smarttravel.com';
    $password = password_hash('guide1234', PASSWORD_DEFAULT);
    $guideName = 'Test Guide';

    // accounts 테이블에 삽입
    $insertAccount = $conn->prepare("INSERT INTO accounts (emailAddress, password, accountType, username, createdAt) VALUES (?, ?, 'guide', ?, NOW())");
    $insertAccount->bind_param('sss', $email, $password, $guideName);

    if (!$insertAccount->execute()) {
        throw new Exception('Failed to create account: ' . $conn->error);
    }

    $accountId = $conn->insert_id;
    $insertAccount->close();

    // guides 테이블에 삽입
    $insertGuide = $conn->prepare("INSERT INTO guides (accountId, guideName, createdAt) VALUES (?, ?, NOW())");
    $insertGuide->bind_param('is', $accountId, $guideName);

    if (!$insertGuide->execute()) {
        // accounts 롤백
        $conn->query("DELETE FROM accounts WHERE accountId = $accountId");
        throw new Exception('Failed to create guide: ' . $conn->error);
    }

    $insertGuide->close();

    echo json_encode([
        'success' => true,
        'message' => 'Test guide account created successfully',
        'guide' => [
            'accountId' => $accountId,
            'emailAddress' => $email,
            'guideName' => $guideName
        ],
        'loginInfo' => [
            'email' => $email,
            'password' => 'guide1234'
        ],
        'testUrl' => 'http://localhost/smt-escape/admin/guide/full-list.html?test_guide=1'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
