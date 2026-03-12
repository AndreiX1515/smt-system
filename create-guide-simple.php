<?php
header('Content-Type: application/json; charset=utf-8');

// 비밀번호 시도
$passwords = ['', 'cloud1234', 'root', '1234'];
$servername = "localhost";
$username = "root";
$dbname = "smarttravel";

foreach ($passwords as $password) {
    $conn = @new mysqli($servername, $username, $password, $dbname);

    if (!$conn->connect_error) {
        $conn->set_charset("utf8");

        // 먼저 기존 가이드 확인
        $checkQuery = "SELECT a.accountId, a.emailAddress, g.guideName
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
                'dbPassword' => $password === '' ? '(empty)' : $password,
                'guide' => $guide,
                'testUrl' => 'http://localhost/smt-escape/admin/guide/full-list.html?test_guide=1',
                'loginUrl' => 'http://localhost/smt-escape/admin/index.html'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        // 가이드 없으면 생성
        $email = 'test.guide@smarttravel.com';
        $hashedPassword = password_hash('guide1234', PASSWORD_DEFAULT);
        $guideName = 'Test Guide';
        $username_val = 'testguide';

        // accounts 테이블 구조 확인
        $tableCheck = $conn->query("SHOW COLUMNS FROM accounts");
        $columns = [];
        while ($row = $tableCheck->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // 계정 생성
        if (in_array('username', $columns)) {
            $sql1 = "INSERT INTO accounts (emailAddress, password, accountType, username, createdAt)
                     VALUES ('$email', '$hashedPassword', 'guide', '$username_val', NOW())";
        } else {
            $sql1 = "INSERT INTO accounts (emailAddress, password, accountType, createdAt)
                     VALUES ('$email', '$hashedPassword', 'guide', NOW())";
        }

        if ($conn->query($sql1)) {
            $accountId = $conn->insert_id;

            // guides 테이블에 삽입
            $sql2 = "INSERT INTO guides (accountId, guideName, createdAt)
                     VALUES ($accountId, '$guideName', NOW())";

            if ($conn->query($sql2)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Guide account created successfully',
                    'dbPassword' => $password === '' ? '(empty)' : $password,
                    'guide' => [
                        'accountId' => $accountId,
                        'emailAddress' => $email,
                        'password' => 'guide1234',
                        'guideName' => $guideName
                    ],
                    'testUrl' => 'http://localhost/smt-escape/admin/guide/full-list.html?test_guide=1',
                    'loginUrl' => 'http://localhost/smt-escape/admin/index.html'
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create guide entry',
                    'error' => $conn->error
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create account',
                'error' => $conn->error
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $conn->close();
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Could not connect to database'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
