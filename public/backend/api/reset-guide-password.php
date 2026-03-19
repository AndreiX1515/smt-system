<?php
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 입력 데이터 받기
$email = trim($_POST['email'] ?? '');
$guideName = trim($_POST['guideName'] ?? '');
$guideEmail = trim($_POST['guideEmail'] ?? '');

// 입력 검증
if (empty($email) || empty($guideName) || empty($guideEmail)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your name and email correctly.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 이메일 형식 검증
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($guideEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your name and email correctly.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $table_exists = function ($name) use ($conn) {
        $safe = $conn->real_escape_string($name);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    };

    $hasGuideTable = $table_exists('guide');
    $hasGuidesTable = $table_exists('guides');

    // 계정 확인
    $accStmt = $conn->prepare("SELECT accountId, emailAddress FROM accounts WHERE emailAddress = ? AND accountType = 'guide' LIMIT 1");
    if (!$accStmt) throw new Exception("쿼리 준비 실패: " . $conn->error);
    $accStmt->bind_param('s', $email);
    $accStmt->execute();
    $accRes = $accStmt->get_result();
    $acc = $accRes ? $accRes->fetch_assoc() : null;
    $accStmt->close();

    if (!$acc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accountId = intval($acc['accountId']);
    $fullName = '';
    if ($hasGuidesTable) {
        $st = $conn->prepare("SELECT guideName, fName, mName, lName FROM guides WHERE accountId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $accountId);
            $st->execute();
            $r = $st->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $st->close();
            $fullName = trim((string)($row['guideName'] ?? ''));
            if ($fullName === '') {
                $fullName = trim(($row['fName'] ?? '') . ' ' . ($row['mName'] ?? '') . ' ' . ($row['lName'] ?? ''));
            }
        }
    } elseif ($hasGuideTable) {
        $st = $conn->prepare("SELECT fName, mName, lName FROM guide WHERE accountId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $accountId);
            $st->execute();
            $r = $st->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $st->close();
            $fullName = trim(($row['fName'] ?? '') . ' ' . ($row['mName'] ?? '') . ' ' . ($row['lName'] ?? ''));
        }
    }

    $fullName = preg_replace('/\s+/', ' ', trim($fullName));
    
    if ($fullName === '' || strcasecmp(trim($guideName), trim($fullName)) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 이메일 확인
    if (strcasecmp(trim($guideEmail), trim((string)$acc['emailAddress'])) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 임시 비밀번호 생성 (8자리 랜덤)
    $tempPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    // 비밀번호 업데이트
    $updateSql = "UPDATE accounts SET password = ?, defaultPasswordStat = 'yes' WHERE accountId = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        throw new Exception("비밀번호 업데이트 준비 실패: " . $conn->error);
    }
    
    $updateStmt->bind_param('si', $hashedPassword, $accountId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // 이메일 전송 (기본 mail() 사용)
    $to = (string)($acc['emailAddress'] ?? '');
    $subject = 'SMART TRAVEL ADMIN - Temporary Password';
    $body = "Your temporary password is:\n\n{$tempPassword}\n\nPlease log in and change your password immediately.";
    $headers = "From: no-reply@smt-escape.com\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, $subject, $body, $headers);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '임시 비밀번호가 발급되었습니다.',
        'email' => $acc['emailAddress']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Reset guide password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '비밀번호 재설정 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>

