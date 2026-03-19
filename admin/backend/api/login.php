<?php
// conn.php를 먼저 로드 (conn.php가 세션과 헤더를 처리함)
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

/**
 * 관리자 로그인 이력 저장 함수
 */
function saveLoginHistory($conn, $accountId, $email, $accountType, $status, $failureReason = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $conn->prepare("
            INSERT INTO admin_login_history
            (account_id, email, account_type, login_status, failure_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param("issssss", $accountId, $email, $accountType, $status, $failureReason, $ip, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to save login history: " . $e->getMessage());
    }
}



// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 입력 데이터 받기
// 정책: 로그인 ID는 accounts.emailAddress 기준
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

// 입력 검증
if (empty($username) || empty($password)) {
    http_response_code(400);
    // 요구사항: 누락된 값에 따라 메시지를 분리
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your ID'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Please enter your Password'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

try {
    // accounts 스키마 편차 대응: emailAddress/email, password/passwordHash, accountStatus/status
    $accCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM accounts");
    while ($colRes && ($c = $colRes->fetch_assoc())) {
        $accCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }
    $emailCol = $accCols['emailaddress'] ?? ($accCols['email'] ?? 'emailAddress');
    $passwordCol = $accCols['password'] ?? ($accCols['passwordhash'] ?? 'password');
    $statusCol = $accCols['accountstatus'] ?? ($accCols['status'] ?? 'accountStatus');

    // 정책: emailAddress로 로그인
    $sql = "SELECT a.accountId, a.username, a.`{$emailCol}` AS emailAddress, a.`{$passwordCol}` AS password, a.accountType, a.`{$statusCol}` AS accountStatus, a.defaultPasswordStat
            FROM accounts a
            WHERE a.`{$emailCol}` = ?
              AND a.accountType IN ('admin_ph','admin_kr','agent','guide','employee')
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("데이터베이스 쿼리 준비 실패: " . $conn->error);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$account) {
        // 로그인 실패 이력 저장 (계정 없음)
        saveLoginHistory($conn, null, $username, null, 'failed', 'Account not found');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 계정 상태 확인
    if (($account['accountStatus'] ?? '') !== 'active') {
        // 로그인 실패 이력 저장 (계정 비활성)
        saveLoginHistory($conn, $account['accountId'], $username, $account['accountType'], 'failed', 'Account inactive');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is inactive. Please contact the administrator.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 비밀번호 확인 (hash 우선, legacy 평문 fallback)
    $stored = (string)($account['password'] ?? '');
    $ok = false;
    if ($stored !== '') {
        // password_hash로 저장된 경우
        if (preg_match('/^\$2y\$/', $stored) || preg_match('/^\$2a\$/', $stored) || preg_match('/^\$argon2id\$/', $stored)) {
            $ok = password_verify($password, $stored);
        }
        // legacy 평문
        if (!$ok && hash_equals($stored, (string)$password)) {
            $ok = true;
        }
    }

    if (!$ok) {
        // 로그인 실패 이력 저장 (비밀번호 오류)
        saveLoginHistory($conn, $account['accountId'], $username, $account['accountType'], 'failed', 'Invalid password');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 세션 관리
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;
    
    // user_sessions 테이블 존재 확인
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;
    
    if ($tableExists) {
        // 테이블 컬럼 확인
        $columnsCheck = $conn->query("SHOW COLUMNS FROM user_sessions");
        $accountIdColumn = 'accountid';
        while ($col = $columnsCheck->fetch_assoc()) {
            if (strtolower($col['Field']) === 'accountid') {
                $accountIdColumn = $col['Field'];
                break;
            }
        }
        
        // 기존 세션 확인 및 삭제
        $session_check_sql = "SELECT session_id FROM user_sessions WHERE $accountIdColumn = ?";
        $session_check_stmt = $conn->prepare($session_check_sql);
        if ($session_check_stmt) {
            $session_check_stmt->bind_param("i", $account['accountId']);
            $session_check_stmt->execute();
            $session_result = $session_check_stmt->get_result();
            
            if ($session_result && $session_result->num_rows > 0) {
                $existing_session = $session_result->fetch_assoc();
                $existing_session_id = $existing_session['session_id'];
                
                $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
                if ($delete_stmt) {
                    $delete_stmt->bind_param("s", $existing_session_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }
            }
            $session_check_stmt->close();
        }
    }
    
    // 세션 ID 재생성
    session_regenerate_id(true);
    $new_session_id = session_id();
    
    // 세션 데이터 저장 (accountType 별로 분기)
    // - DB enum 상 CS는 employee로 저장됨 → app에서는 cs로 취급
    $rawType = $account['accountType'] ?? 'admin_ph';
    $type = ($rawType === 'employee') ? 'cs' : $rawType;
    $emailOrUser = $account['emailAddress'] ?: ($account['username'] ?? '');

    // Helper function to check admin types
    $isAdminType = in_array($type, ['admin_ph', 'admin_kr', 'admin'], true);

    if ($isAdminType) {
        $_SESSION['admin_accountId'] = $account['accountId'];
        $_SESSION['admin_userType'] = $type; // admin_ph or admin_kr
        $_SESSION['admin_emailAddress'] = $emailOrUser;
        $_SESSION['admin_timeout'] = time();
        $_SESSION['admin_defaultPasswordStat'] = $account['defaultPasswordStat'] ?? 'N';
    } elseif ($type === 'agent') {
        $_SESSION['agent_accountId'] = $account['accountId'];
        $_SESSION['agent_userType'] = 'agent';
        $_SESSION['agent_emailAddress'] = $emailOrUser;
        $_SESSION['agent_timeout'] = time();
        // DEBUG: 로그인 시 세션 확인 (문제 해결 후 제거)
        error_log("[admin-login] agent login success, agent_accountId=" . $account['accountId'] . ", session_id=" . session_id());
    } elseif ($type === 'guide') {
        $_SESSION['guide_accountId'] = $account['accountId'];
        $_SESSION['guide_userType'] = 'guide';
        $_SESSION['guide_emailAddress'] = $emailOrUser;
        $_SESSION['guide_timeout'] = time();
    } elseif ($type === 'cs') {
        $_SESSION['cs_accountId'] = $account['accountId'];
        $_SESSION['cs_userType'] = 'cs';
        $_SESSION['cs_emailAddress'] = $emailOrUser;
        $_SESSION['cs_timeout'] = time();
    } else {
        // 안전장치 (알 수 없는 타입은 admin_ph로 처리)
        $_SESSION['admin_accountId'] = $account['accountId'];
        $_SESSION['admin_userType'] = 'admin_ph';
        $_SESSION['admin_emailAddress'] = $emailOrUser;
        $_SESSION['admin_timeout'] = time();
    }
    
    // user_sessions 테이블에 세션 저장 (테이블이 존재하는 경우만)
    if ($tableExists) {
        $insert_stmt = $conn->prepare(
            "INSERT INTO user_sessions (session_id, $accountIdColumn, login_time, last_activity, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        if ($insert_stmt) {
            $insert_stmt->bind_param("sissss", $new_session_id, $account['accountId'], $login_time, $last_activity, $ip_address, $user_agent);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
    }
    
    // lastLoginAt 갱신
    $stmt = $conn->prepare("UPDATE accounts SET lastLoginAt = NOW() WHERE accountId = ?");
    $stmt->bind_param("i", $account['accountId']);
    $stmt->execute();
    $stmt->close();

    // 로그인 성공 이력 저장
    saveLoginHistory($conn, $account['accountId'], $emailOrUser, $rawType, 'success', null);

    // 로그인 성공 응답 (accountType 별 리다이렉트)
    $redirectUrl = './super/overview.html';
    if ($type === 'agent') {
        // agent 프로필 완성 여부 체크
        $profileCheck = $conn->prepare("SELECT agencyName, fName, lName, contactNo FROM agent WHERE accountId = ?");
        $profileCheck->bind_param("i", $account['accountId']);
        $profileCheck->execute();
        $agentProfile = $profileCheck->get_result()->fetch_assoc();
        $profileCheck->close();

        // 필수 필드가 비어있으면 프로필 완성 페이지로 리다이렉트
        if (!$agentProfile || empty($agentProfile['agencyName']) || empty($agentProfile['fName']) || empty($agentProfile['lName']) || empty($agentProfile['contactNo'])) {
            $redirectUrl = './complete-profile.html';
        } else {
            $redirectUrl = './agent/overview.html';
        }
    }
    if ($type === 'guide') $redirectUrl = './guide/full-list.html';
    if ($type === 'cs') $redirectUrl = './cs/inquiry-list.html';

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login success',
        'redirectUrl' => $redirectUrl,
        'userType' => $type
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '로그인 처리 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    error_log("Login fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '로그인 처리 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
