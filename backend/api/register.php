<?php
// 에러 리포팅 설정 - API 응답을 위해 에러 표시 비활성화
error_reporting(E_ALL);
ini_set('display_errors', 0); // API JSON 응답을 깨뜨리지 않도록 비활성화
ini_set('log_errors', 1); // 에러 로깅은 활성화

// 데이터베이스 연결 직접 확인
$servername = "localhost";
$username = "root";
$password = "cloud1234";
$dbname = "smarttravel";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결 실패']);
    exit;
}

$conn->set_charset("utf8");

// JSON 응답 함수
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 입력 데이터 정리 함수
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 활동 로그 함수
function log_activity($message) {
    global $conn;
    // activity_logs 테이블이 없거나 구조가 다를 수 있으므로 간단한 로그만 남김
    error_log("Activity: " . $message);
}

// 디버깅을 위한 로그
error_log("Register API called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("Database connection successful");

// 간단한 테스트 응답
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    send_json_response(['success' => true, 'message' => 'Register API is working'], 200);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid method: " . $_SERVER['REQUEST_METHOD']);
    send_json_response(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], 405);
}

try {
    // JSON 데이터 받기
    $raw_input = file_get_contents('php://input');
    error_log("Raw input: " . $raw_input);

    $input = json_decode($raw_input, true);
    error_log("Parsed input: " . print_r($input, true));

    if (!$input) {
        error_log("JSON parsing failed");
        send_json_response(['success' => false, 'message' => '잘못된 JSON 형식입니다.'], 400);
    }

    // 필수 필드 확인 (phone은 선택)
    if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
        error_log("Missing required fields");
        send_json_response(['success' => false, 'message' => '모든 필수 항목을 입력해주세요.'], 400);
    }

    // 이메일 형식 확인
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email format: " . $input['email']);
        send_json_response(['success' => false, 'message' => '올바른 이메일 형식을 입력해주세요.'], 400);
    }

    // 비밀번호 길이 확인
    if (strlen($input['password']) < 6) {
        error_log("Password too short");
        send_json_response(['success' => false, 'message' => '비밀번호는 최소 6자 이상이어야 합니다.'], 400);
    }

    // 이메일 중복 확인
    $email = sanitize_input($input['email']);
    error_log("Checking email duplicate: " . $email);
    
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        send_json_response(['success' => false, 'message' => '데이터베이스 오류가 발생했습니다.'], 500);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        error_log("Email already exists: " . $email);
        send_json_response(['success' => false, 'message' => '이미 사용 중인 이메일입니다.'], 409);
    }
    
    // 사용자명 생성 (이메일에서 @ 앞부분 사용)
    $username = explode('@', $email)[0];
    
    // 사용자명 중복 확인 및 수정
    $original_username = $username;
    $counter = 1;
    while (true) {
        $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE username = ?");
        if (!$stmt) {
            error_log("Username check prepare failed: " . $conn->error);
            send_json_response(['success' => false, 'message' => '데이터베이스 오류가 발생했습니다.'], 500);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            break;
        }
        
        $username = $original_username . $counter;
        $counter++;
    }
    
    // 비밀번호 해시화
    $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // 트랜잭션 시작
    $conn->begin_transaction();
    
    try {
        // 제휴 코드 처리 (선택 필드)
        // - Partnership code는 super/agent-detail.html?id=AGTxxx 의 id 값(AGTxxx) 자체
        // - 입력 편차(공백/대소문자)를 보정한다.
        $affiliate_code_raw =
            (isset($input['affiliateCode']) ? (string)$input['affiliateCode'] : '') ?: 
            (isset($input['affiliate_code']) ? (string)$input['affiliate_code'] : '') ?: 
            (isset($input['partnershipCode']) ? (string)$input['partnershipCode'] : '') ?: 
            (isset($input['partnership_code']) ? (string)$input['partnership_code'] : '');
        $affiliate_code = trim($affiliate_code_raw);
        if ($affiliate_code === '') $affiliate_code = null;
        if ($affiliate_code !== null) {
            // 대문자 정규화 (MySQL collation이 case-insensitive여도 안전)
            $affiliate_code = strtoupper($affiliate_code);
        }

        // 제휴코드 검증 (agent 테이블에 존재하는지 확인)
        if ($affiliate_code !== null && $affiliate_code !== '') {
            $agentTable = $conn->query("SHOW TABLES LIKE 'agent'");
            if ($agentTable && $agentTable->num_rows > 0) {
                $hasAgentCode = false;
                try {
                    $hasAgentCodeRes = $conn->query("SHOW COLUMNS FROM agent LIKE 'agentCode'");
                    $hasAgentCode = ($hasAgentCodeRes && $hasAgentCodeRes->num_rows > 0);
                } catch (Throwable $e) { $hasAgentCode = false; }

                $chk = $conn->prepare($hasAgentCode
                    ? "SELECT agentId FROM agent WHERE agentId = ? OR agentCode = ? LIMIT 1"
                    : "SELECT agentId FROM agent WHERE agentId = ? LIMIT 1"
                );
                if ($chk) {
                    if ($hasAgentCode) $chk->bind_param("ss", $affiliate_code, $affiliate_code);
                    else $chk->bind_param("s", $affiliate_code);
                    $chk->execute();
                    $chkRes = $chk->get_result();
                    $row = $chkRes ? $chkRes->fetch_assoc() : null;
                    $chk->close();
                    if (!$row) {
                        send_json_response(['success' => false, 'message' => '유효하지 않은 제휴 코드입니다.'], 400);
                    }
                } else {
                    send_json_response(['success' => false, 'message' => '유효하지 않은 제휴 코드입니다.'], 400);
                }
            } else {
                send_json_response(['success' => false, 'message' => '유효하지 않은 제휴 코드입니다.'], 400);
            }
        }
        
        // accounts 테이블에 사용자 추가
        $stmt = $conn->prepare("INSERT INTO accounts (username, emailAddress, password, accountStatus, accountType, affiliateCode) VALUES (?, ?, ?, 'active', 'guest', ?)");
        if (!$stmt) {
            error_log("Accounts insert prepare failed: " . $conn->error);
            throw new Exception("데이터베이스 준비 오류");
        }
        
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $affiliate_code);
        $stmt->execute();
        
        $account_id = $conn->insert_id;
        error_log("Account created with ID: " . $account_id);
        
        // client 테이블에 클라이언트 정보 추가
        $name = sanitize_input($input['name']);
        $fname = explode(' ', $name)[0];
        $lname = count(explode(' ', $name)) > 1 ? implode(' ', array_slice(explode(' ', $name), 1)) : '';
        $phone = null;
        if (isset($input['phone']) && trim((string)$input['phone']) !== '') {
            $phone = sanitize_input((string)$input['phone']);
            // 간단 포맷 체크(선택): 숫자/공백/하이픈/+ 만 허용
            if (!preg_match('/^[0-9\-\+\s]+$/', $phone)) {
                send_json_response(['success' => false, 'message' => '연락처 형식이 올바르지 않습니다.'], 400);
            }
        }

        // 제휴코드가 있으면 Wholeseller(B2B), 없으면 Retailer(B2C)
        $clientType = ($affiliate_code !== null && $affiliate_code !== '') ? 'Wholeseller' : 'Retailer';

        // 먼저 임시 clientId로 INSERT 후 client.id를 가져와서 clientId 생성
        $temp_client_id = 'CLI_TEMP_' . time();
        $stmt = $conn->prepare("INSERT INTO client (clientId, accountId, fName, lName, contactNo, clientType, clientRole) VALUES (?, ?, ?, ?, ?, ?, 'Sub-Agent')");
        if (!$stmt) {
            error_log("Client insert prepare failed: " . $conn->error);
            throw new Exception("클라이언트 데이터베이스 준비 오류");
        }

        $stmt->bind_param("sissss", $temp_client_id, $account_id, $fname, $lname, $phone, $clientType);
        $stmt->execute();

        // client.id를 기반으로 clientId 생성
        $client_table_id = $conn->insert_id;
        $client_id = 'CLI' . str_pad($client_table_id, 6, '0', STR_PAD_LEFT);

        // clientId 업데이트
        $updateStmt = $conn->prepare("UPDATE client SET clientId = ? WHERE id = ?");
        $updateStmt->bind_param("si", $client_id, $client_table_id);
        $updateStmt->execute();
        $updateStmt->close();

        error_log("Client created with ID: " . $client_id);
        
        // 트랜잭션 커밋
        $conn->commit();
        
        // 로그 기록
        log_activity("User registration: {$email} (ID: {$account_id})");
        
        // 응답 데이터
        $response = [
            'success' => true,
            'message' => '회원가입이 완료되었습니다!',
            'user' => [
                'id' => $account_id,
                'username' => $username,
                'email' => $email,
                'name' => $name,
                'phone' => $phone
            ]
        ];
        
        error_log("Registration successful for: " . $email);
        send_json_response($response);
        
    } catch (Exception $e) {
        // 트랜잭션 롤백
        $conn->rollback();
        error_log("Transaction rollback: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    send_json_response(['success' => false, 'message' => '서버 오류가 발생했습니다: ' . $e->getMessage()], 500);
}
?>