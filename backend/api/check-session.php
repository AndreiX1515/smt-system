<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

function table_exists($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

//    
if (isset($_SESSION['user_id']) || isset($_SESSION['accountId'])) {
    $userId = $_SESSION['user_id'] ?? $_SESSION['accountId'];

    // DB  /   (guide-mypage    )
    $profile = [
        'accountId' => (int)$userId,
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'accountType' => $_SESSION['account_type'] ?? ($_SESSION['accountRole'] ?? ''),
        'clientType' => '',
        'companyId' => null,
        'isB2B' => false
    ];
    try {
        $hasClient = table_exists($conn, 'client');
        $hasGuides = table_exists($conn, 'guides');
        $joins = '';
        $select = "a.username, a.emailAddress, a.accountType";
        if ($hasClient) $select .= ", c.fName, c.lName, COALESCE(c.clientType,'') AS clientType, c.companyId AS companyId";
        if ($hasGuides) $select .= ", g.guideName, g.guideCode";
        if ($hasClient) $joins .= " LEFT JOIN client c ON a.accountId = c.accountId";
        if ($hasGuides) $joins .= " LEFT JOIN guides g ON a.accountId = g.accountId";

        $stmtP = $conn->prepare("SELECT {$select} FROM accounts a {$joins} WHERE a.accountId = ? LIMIT 1");
        if ($stmtP) {
            $aid = (int)$userId;
            $stmtP->bind_param("i", $aid);
            $stmtP->execute();
            $row = $stmtP->get_result()->fetch_assoc();
            $stmtP->close();
            if ($row) {
                $profile['username'] = $row['username'] ?? $profile['username'];
                $profile['email'] = $row['emailAddress'] ?? $profile['email'];
                $profile['accountType'] = $row['accountType'] ?? $profile['accountType'];
                $profile['firstName'] = $row['fName'] ?? '';
                $profile['lastName'] = $row['lName'] ?? '';
                $profile['guideName'] = $row['guideName'] ?? '';
                $profile['guideCode'] = $row['guideCode'] ?? '';
                $profile['clientType'] = strtolower(trim((string)($row['clientType'] ?? '')));
                $profile['companyId'] = isset($row['companyId']) ? (int)$row['companyId'] : null;

                $display = trim((string)($profile['guideName'] ?? ''));
                if ($display === '') {
                    $display = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
                }
                if ($display === '') $display = (string)($profile['username'] ?? '');
                $profile['displayName'] = $display;

                // B2B/B2C 판별: accounts.accountType 기반
                // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B
                // - accountType IN ('guest', 'guide', 'cs', '') → B2C
                $profile['isB2B'] = in_array(strtolower($profile['accountType'] ?? ''), ['agent', 'admin_ph', 'admin_kr'], true);
            }
        }
    } catch (Throwable $e) {
        // ignore profile enrichment failures
    }
    
    // DB 세션 검증 (세션 유효시간 기준)
    $stmt = $conn->prepare("SELECT session_id, ip_address, user_agent FROM user_sessions WHERE accountid = ? AND last_activity > DATE_SUB(NOW(), " . SESSION_LIFETIME_INTERVAL . ")");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($sessionRow = $result->fetch_assoc()) {
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $storedUserAgent = $sessionRow['user_agent'] ?? '';

        // User-Agent 검증 (세션 탈취 방지)
        if ($storedUserAgent !== '' && $currentUserAgent !== $storedUserAgent) {
            // User-Agent 불일치 - 세션 탈취 의심
            error_log("Session hijack suspected: accountId={$userId}, storedUA={$storedUserAgent}, currentUA={$currentUserAgent}");

            // DB 세션 삭제
            $deleteStmt = $conn->prepare("DELETE FROM user_sessions WHERE accountid = ?");
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();

            // PHP 세션 정리
            session_unset();
            session_destroy();

            send_json_response([
                'success' => false,
                'isLoggedIn' => false,
                'message' => '보안상의 이유로 로그아웃되었습니다. 다시 로그인해주세요.',
                'errorCode' => 'SESSION_HIJACK_SUSPECTED'
            ]);
        }

        // IP 변경 감지 (로깅만, 차단 안 함)
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $storedIp = $sessionRow['ip_address'] ?? '';
        if ($storedIp !== '' && $currentIp !== $storedIp) {
            error_log("IP changed: accountId={$userId}, storedIP={$storedIp}, currentIP={$currentIp}");
            // IP 변경 시 DB 업데이트 (모바일/VPN 사용자 지원)
            $updateIpStmt = $conn->prepare("UPDATE user_sessions SET ip_address = ?, last_activity = NOW() WHERE accountid = ?");
            $updateIpStmt->bind_param("si", $currentIp, $userId);
            $updateIpStmt->execute();
        } else {
            // 세션 활동 시간 업데이트
            $updateStmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE accountid = ?");
            $updateStmt->bind_param("i", $userId);
            $updateStmt->execute();
        }

        send_json_response([
            'success' => true,
            'isLoggedIn' => true,
            'user' => [
                'id' => $userId,
                'username' => $profile['username'] ?? ($_SESSION['username'] ?? ''),
                'email' => $profile['email'] ?? ($_SESSION['email'] ?? ''),
                'account_type' => $profile['accountType'] ?? ($_SESSION['account_type'] ?? $_SESSION['accountRole'] ?? ''),
                'accountType' => $profile['accountType'] ?? ($_SESSION['account_type'] ?? $_SESSION['accountRole'] ?? ''),
                'isB2B' => (bool)($profile['isB2B'] ?? false),
                'firstName' => $profile['firstName'] ?? '',
                'lastName' => $profile['lastName'] ?? '',
                'displayName' => $profile['displayName'] ?? ($profile['username'] ?? ''),
                'guideName' => $profile['guideName'] ?? '',
                'guideCode' => $profile['guideCode'] ?? ''
            ]
        ]);
    } else {
        // DB 세션 없음 - PHP 세션 정리 후 로그인 실패 처리 (보안 강화)
        session_unset();
        session_destroy();

        send_json_response([
            'success' => false,
            'isLoggedIn' => false,
            'message' => '세션이 만료되었습니다. 다시 로그인해주세요.',
            'errorCode' => 'SESSION_EXPIRED'
        ]);
    }
} else {
    // PHP 세션 없음 - Remember Token으로 자동 로그인 시도
    if (isset($_COOKIE['remember_token'])) {
        $tokenHash = hash('sha256', $_COOKIE['remember_token']);

        $stmt = $conn->prepare("
            SELECT rt.accountId, a.username, a.emailAddress, a.accountType
            FROM remember_tokens rt
            JOIN accounts a ON rt.accountId = a.accountId
            WHERE rt.token_hash = ? AND rt.expires_at > NOW()
        ");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // 유효한 Remember Token - 새 세션 생성
            $session_id = bin2hex(random_bytes(32));
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // 기존 세션 삭제
            $deleteStmt = $conn->prepare("DELETE FROM user_sessions WHERE accountid = ?");
            $deleteStmt->bind_param("i", $row['accountId']);
            $deleteStmt->execute();

            // 새 세션 저장
            $insertStmt = $conn->prepare("INSERT INTO user_sessions (session_id, accountid, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("siss", $session_id, $row['accountId'], $ip_address, $user_agent);
            $insertStmt->execute();

            // PHP 세션 생성
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $row['accountId'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['emailAddress'];
            $_SESSION['account_type'] = $row['accountType'];
            $_SESSION['session_id'] = $session_id;

            // 프로필 정보 조회
            $profile = [
                'accountId' => (int)$row['accountId'],
                'username' => $row['username'],
                'email' => $row['emailAddress'],
                'accountType' => $row['accountType'],
                'firstName' => '',
                'lastName' => '',
                'displayName' => $row['username'],
                'isB2B' => in_array(strtolower($row['accountType'] ?? ''), ['agent', 'admin_ph', 'admin_kr'], true)
            ];

            // client 테이블에서 추가 정보 조회
            if (table_exists($conn, 'client')) {
                $clientStmt = $conn->prepare("SELECT fName, lName FROM client WHERE accountId = ?");
                $clientStmt->bind_param("i", $row['accountId']);
                $clientStmt->execute();
                $clientRow = $clientStmt->get_result()->fetch_assoc();
                if ($clientRow) {
                    $profile['firstName'] = $clientRow['fName'] ?? '';
                    $profile['lastName'] = $clientRow['lName'] ?? '';
                    $displayName = trim($profile['firstName'] . ' ' . $profile['lastName']);
                    if ($displayName !== '') {
                        $profile['displayName'] = $displayName;
                    }
                }
            }

            send_json_response([
                'success' => true,
                'isLoggedIn' => true,
                'autoLogin' => true,
                'user' => [
                    'id' => $profile['accountId'],
                    'username' => $profile['username'],
                    'email' => $profile['email'],
                    'account_type' => $profile['accountType'],
                    'accountType' => $profile['accountType'],
                    'isB2B' => $profile['isB2B'],
                    'firstName' => $profile['firstName'],
                    'lastName' => $profile['lastName'],
                    'displayName' => $profile['displayName']
                ]
            ]);
        } else {
            // 유효하지 않은 Remember Token - 쿠키 삭제
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            send_json_response([
                'success' => false,
                'isLoggedIn' => false,
                'message' => '로그인이 필요합니다.'
            ]);
        }
    } else {
        send_json_response([
            'success' => false,
            'isLoggedIn' => false,
            'message' => '로그인이 필요합니다.'
        ]);
    }
}
?>









