<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

// 세션 시작
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], 405);
}

// JSON 데이터 받기
$input = json_decode(file_get_contents('php://input'), true);

error_log("Login input: " . json_encode($input));

if (!$input) {
    error_log("Invalid JSON input");
    send_json_response(['success' => false, 'message' => '잘못된 JSON 형식입니다.'], 400);
}

$email = sanitize_input($input['email'] ?? '');
$password = $input['password'] ?? '';

error_log("Parsed email: '$email', password length: " . strlen($password));

// 필수 필드 확인
if (empty($email) || empty($password)) {
    error_log("Empty email or password");
    send_json_response(['success' => false, 'message' => '이메일과 비밀번호를 입력해주세요.'], 400);
}

// 로그인 입력은 이메일/아이디(username) 모두 허용 (B2B/Guide 등)
// - 기존 UI에서 email 필드를 그대로 사용하므로 형식 검증은 완화

try {
    error_log("Starting user lookup for email: $email");

    // SMT 수정: accounts 스키마 편차 대응 (password/passwordHash, accountStatus/status, emailAddress/email)
    $accountColumns = [];
    $colRes = $conn->query("SHOW COLUMNS FROM accounts");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $accountColumns[] = strtolower($c['Field']);
        }
    }
    $emailCol = in_array('emailaddress', $accountColumns, true) ? 'emailAddress' : (in_array('email', $accountColumns, true) ? 'email' : 'emailAddress');
    $passwordCol = in_array('password', $accountColumns, true) ? 'password' : (in_array('passwordhash', $accountColumns, true) ? 'passwordHash' : 'password');
    $statusCol = in_array('accountstatus', $accountColumns, true) ? 'accountStatus' : (in_array('status', $accountColumns, true) ? 'status' : 'accountStatus');
    
    // login_attempts 테이블 생성 (존재하지 않는 경우)
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            attempt_count INT DEFAULT 0,
            last_attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            locked_until DATETIME NULL,
            INDEX idx_email (email),
            INDEX idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($createTableSql);
    
    // 로그인 시도 기록 조회/생성
    $stmt = $conn->prepare("
        SELECT attempt_count, locked_until 
        FROM login_attempts 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $attemptResult = $stmt->get_result();
    $attemptData = $attemptResult->fetch_assoc();
    
    // 제한 시간 확인 (15분 제한)
    if ($attemptData && $attemptData['locked_until']) {
        $lockedUntil = new DateTime($attemptData['locked_until']);
        $now = new DateTime();
        
        if ($lockedUntil > $now) {
            // 아직 제한 시간이 지나지 않음
            $remainingMinutes = $now->diff($lockedUntil)->i;
            error_log("Login attempt blocked for email: $email, locked until: " . $attemptData['locked_until']);
            send_json_response([
                'success' => false,
                'message' => 'Please try again in 15 minutes.',
                'errorCode' => 'LOGIN_LIMITED'
            ], 403);
            exit;
        } else {
            // 제한 시간이 지났으므로 기록 초기화
            $stmt = $conn->prepare("UPDATE login_attempts SET attempt_count = 0, locked_until = NULL WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $attemptData['attempt_count'] = 0;
        }
    }
    
    // 사용자 조회 (client 테이블과 조인)
    // - emailAddress 또는 username으로 로그인 가능
    // - 에이전트가 등록한 고객은 고객번호(clientId: CLI000123 등)를 "아이디"로 인식하는 경우가 있어 clientId 로그인도 허용
    // SMT 수정: clientType/clientRole 컬럼은 환경에 따라 없을 수 있어 동적 SELECT로 안전하게 제공
    $clientColumns = [];
    $clientColRes = $conn->query("SHOW COLUMNS FROM client");
    if ($clientColRes) {
        while ($c = $clientColRes->fetch_assoc()) {
            $clientColumns[] = strtolower($c['Field']);
        }
    }
    $clientTypeExpr = in_array('clienttype', $clientColumns, true) ? 'c.clientType' : "''";
    $clientRoleExpr = in_array('clientrole', $clientColumns, true) ? 'c.clientRole' : "''";

    // accountStatus/status 컬럼이 NULL/''로 들어간 레거시 데이터는 'active'로 간주해 로그인 가능하게 처리
    // (명시적으로 inactive/blocked 처리된 계정은 그대로 차단)
    $statusActiveExpr = "LOWER(COALESCE(NULLIF(TRIM(a.`{$statusCol}`), ''), 'active')) = 'active'";

    $stmt = $conn->prepare("
        SELECT 
            a.accountId,
            a.username,
            a.`{$emailCol}` AS emailAddress,
            a.`{$passwordCol}` AS password,
            a.`{$statusCol}` AS accountStatus,
            a.accountType,
            c.fName as firstName,
            c.lName as lastName,
            c.contactNo as phoneNumber,
            {$clientTypeExpr} as clientType,
            {$clientRoleExpr} as clientRole
        FROM accounts a 
        LEFT JOIN client c ON a.accountId = c.accountId 
        WHERE (a.`{$emailCol}` = ? OR a.username = ? OR c.clientId = ?)
          AND {$statusActiveExpr}
    ");
    $stmt->bind_param("sss", $email, $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("User lookup result: " . $result->num_rows . " rows found");
    
    if ($result->num_rows === 0) {
        error_log("No user found for email: $email");
        send_json_response([
            'success' => false, 
            'message' => 'No matching account was found. Please check your email/ID and password.',
            'errorCode' => 'USER_NOT_FOUND'
        ], 200); // dev_tasks #127: do not return HTTP 401 for invalid credentials
        exit;
    }
    
    $user = $result->fetch_assoc();
    error_log("User found: " . json_encode($user));
    
    // 비밀번호 확인 (hash 우선, legacy 평문 fallback)
    $stored = (string)($user['password'] ?? '');
    $passwordMatch = false;
    if ($stored !== '') {
        if (preg_match('/^\$2y\$/', $stored) || preg_match('/^\$2a\$/', $stored) || preg_match('/^\$argon2id\$/', $stored)) {
            $passwordMatch = password_verify($password, $stored);
        }
        if (!$passwordMatch && hash_equals($stored, (string)$password)) {
            $passwordMatch = true;
        }
    }
    error_log("Password verification result: " . ($passwordMatch ? 'true' : 'false'));
    
    if (!$passwordMatch) {
        error_log("Password verification failed for user: " . $user['emailAddress']);
        
        // 실패 횟수 증가
        $attemptCount = ($attemptData ? $attemptData['attempt_count'] : 0) + 1;
        
        if ($attemptData) {
            // 기존 기록 업데이트
            $stmt = $conn->prepare("
                UPDATE login_attempts 
                SET attempt_count = ?, last_attempt_time = NOW()
                WHERE email = ?
            ");
            $stmt->bind_param("is", $attemptCount, $email);
            $stmt->execute();
        } else {
            // 새 기록 생성
            $stmt = $conn->prepare("
                INSERT INTO login_attempts (email, attempt_count, last_attempt_time) 
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param("si", $email, $attemptCount);
            $stmt->execute();
        }
        
        // 5회 실패 시 15분 제한
        if ($attemptCount >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $conn->prepare("
                UPDATE login_attempts 
                SET locked_until = ?
                WHERE email = ?
            ");
            $stmt->bind_param("ss", $lockedUntil, $email);
            $stmt->execute();
            
            error_log("Login locked for email: $email until: $lockedUntil");
            send_json_response([
                'success' => false, 
                'message' => 'Please try again in 15 minutes. Too many failed login attempts.',
                'errorCode' => 'LOGIN_LIMITED'
            ], 403);
            exit;
        }
        
        send_json_response([
            'success' => false, 
            'message' => 'No matching account was found. Please check your email/ID and password.',
            'errorCode' => 'INVALID_CREDENTIALS'
        ], 200); // dev_tasks #127: do not return HTTP 401 for invalid credentials
        exit;
    }
    
    // 로그인 성공 시 실패 횟수 초기화
    if ($attemptData) {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }
    
    // 세션 생성
    $session_id = bin2hex(random_bytes(32));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // 기존 세션 삭제
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE accountid = ?");
    $stmt->bind_param("i", $user['accountId']);
    $stmt->execute();
    
    // 기존 만료된 세션 정리 (세션 유효시간 초과 비활성)
    $cleanupStmt = $conn->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), " . SESSION_LIFETIME_INTERVAL . ")");
    $cleanupStmt->execute();
    
    // 새 세션 저장
    $stmt = $conn->prepare("INSERT INTO user_sessions (session_id, accountid, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $session_id, $user['accountId'], $ip_address, $user_agent);
    $stmt->execute();
    
    // 세션 정보 저장
    $_SESSION['user_id'] = $user['accountId'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['emailAddress'];
    $_SESSION['account_type'] = $user['accountType'];
    $_SESSION['session_id'] = $session_id;

    // Remember Token 처리 (자동 로그인)
    if (!empty($input['rememberMe'])) {
        $rememberToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rememberToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // 기존 토큰 삭제
        $deleteStmt = $conn->prepare("DELETE FROM remember_tokens WHERE accountId = ?");
        $deleteStmt->bind_param("i", $user['accountId']);
        $deleteStmt->execute();

        // 새 토큰 저장
        $tokenStmt = $conn->prepare("INSERT INTO remember_tokens (accountId, token_hash, expires_at) VALUES (?, ?, ?)");
        $tokenStmt->bind_param("iss", $user['accountId'], $tokenHash, $expiresAt);
        $tokenStmt->execute();

        // 쿠키에 평문 토큰 저장 (30일, HttpOnly, Secure)
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', $rememberToken, [
            'expires' => strtotime('+30 days'),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    // lastLoginAt 갱신
    $stmt = $conn->prepare("UPDATE accounts SET lastLoginAt = NOW() WHERE accountId = ?");
    $stmt->bind_param("i", $user['accountId']);
    $stmt->execute();

    // 로그 기록
    log_activity($user['accountId'], "user_login", "User login: {$user['emailAddress']} (ID: {$user['accountId']})");

    // 응답 데이터
    $response = [
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'accountId' => $user['accountId'],
            'username' => $user['username'],
            'email' => $user['emailAddress'],
            'accountType' => $user['accountType'],
            'firstName' => $user['firstName'] ?? '',
            'lastName' => $user['lastName'] ?? '',
            'phoneNumber' => $user['phoneNumber'] ?? '',
            'clientType' => $user['clientType'] ?? '',
            'clientRole' => $user['clientRole'] ?? '',
            'accountRole' => $user['accountType']
        ],
        'session_id' => $session_id
    ];
    
    send_json_response($response);
    
} catch (Exception $e) {
    log_activity(0, "login_error", "Login error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '서버 오류가 발생했습니다.'], 500);
}
?>
