<?php
require __DIR__ . '/../../../backend/conn.php';
require_once __DIR__ . '/../../../backend/lib/mailer.php';

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
// - 프론트에서 key가 email 로 넘어오지만 실제 의미는 "아이디(username)" 입니다.
// 정책(단순화): accounts.username 단일 기준으로 계정을 찾습니다.
$loginId = trim($_POST['email'] ?? '');
$managerName = trim($_POST['managerName'] ?? '');
$managerEmail = trim($_POST['managerEmail'] ?? '');

// 입력 검증
if (empty($loginId) || empty($managerName) || empty($managerEmail)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your name and email correctly.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 이메일 형식 검증(담당자 이메일만)
if (!filter_var($managerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '담당자 이메일 형식이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 단일 모달(에이전트/가이드 구분 없이): 이메일로 계정 조회 후 이름 검증
    $table_exists = function ($name) use ($conn) {
        $safe = $conn->real_escape_string($name);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    };

    $hasAgentTable = $table_exists('agent');
    $hasGuideTable = $table_exists('guide');
    $hasGuidesTable = $table_exists('guides');

    // 단일 모달(에이전트/가이드/관리자 공용):
    // - loginId(username) 로 accounts 조회(username만)
    // - accountType으로 업무 구분(agent/guide/admin/employee)
    $acc = null;

    $accStmt = $conn->prepare("SELECT accountId, username, emailAddress, accountType FROM accounts WHERE username = ? AND accountType IN ('agent','guide','admin_ph','admin_kr','employee') LIMIT 1");
    if (!$accStmt) throw new Exception("쿼리 준비 실패: " . $conn->error);
    $accStmt->bind_param('s', $loginId);
    $accStmt->execute();
    $accRes = $accStmt->get_result();
    $acc = $accRes ? $accRes->fetch_assoc() : null;
    $accStmt->close();

    if (!$acc) {
        http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 이메일(담당자 이메일) 확인: 계정의 이메일(emailAddress)과 일치해야 함
    if (strcasecmp(trim($managerEmail), trim((string)($acc['emailAddress'] ?? ''))) !== 0) {
        http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accountId = intval($acc['accountId']);
    $accountType = (string)($acc['accountType'] ?? '');

    // 이름 조회/검증
    $fullName = '';
    if (in_array($accountType, ['admin_ph', 'admin_kr', 'employee'], true)) {
        // admin_ph/admin_kr/employee: 환경에 따라 employee 테이블이 있을 수 있으므로 가능한 경우 이름을 조인/조회하여 검증 강화
        // - 우선순위: employee(accountId) -> employee(email) -> accounts.username fallback
        $employeeFullName = '';
        $hasEmployeeTable = $table_exists('employee');
        if ($hasEmployeeTable) {
            $empCols = [];
            $empColRes = $conn->query("SHOW COLUMNS FROM employee");
            while ($empColRes && ($c = $empColRes->fetch_assoc())) {
                $empCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
            }
            $empAccountIdCol = $empCols['accountid'] ?? null;
            $empEmailCol = $empCols['emailaddress'] ?? ($empCols['email'] ?? null);
            $fCol = $empCols['fname'] ?? null;
            $mCol = $empCols['mname'] ?? null;
            $lCol = $empCols['lname'] ?? null;

            $select = [];
            if ($fCol) $select[] = "`{$fCol}` AS f";
            if ($mCol) $select[] = "`{$mCol}` AS m";
            if ($lCol) $select[] = "`{$lCol}` AS l";

            if (!empty($select)) {
                // 1) accountId로 조회
                if ($empAccountIdCol) {
                    $stE = $conn->prepare("SELECT " . implode(', ', $select) . " FROM employee WHERE `{$empAccountIdCol}` = ? LIMIT 1");
                    if ($stE) {
                        $stE->bind_param('i', $accountId);
                        $stE->execute();
                        $rE = $stE->get_result();
                        $rowE = $rE ? $rE->fetch_assoc() : null;
                        $stE->close();
                        if ($rowE) {
                            $employeeFullName = trim(($rowE['f'] ?? '') . ' ' . ($rowE['m'] ?? '') . ' ' . ($rowE['l'] ?? ''));
                        }
                    }
                }
                // 2) email로 조회 (employee 테이블에 email 컬럼이 있을 때)
                if ($employeeFullName === '' && $empEmailCol && $managerEmail !== '') {
                    $stE2 = $conn->prepare("SELECT " . implode(', ', $select) . " FROM employee WHERE `{$empEmailCol}` = ? LIMIT 1");
                    if ($stE2) {
                        $stE2->bind_param('s', $managerEmail);
                        $stE2->execute();
                        $rE2 = $stE2->get_result();
                        $rowE2 = $rE2 ? $rE2->fetch_assoc() : null;
                        $stE2->close();
                        if ($rowE2) {
                            $employeeFullName = trim(($rowE2['f'] ?? '') . ' ' . ($rowE2['m'] ?? '') . ' ' . ($rowE2['l'] ?? ''));
                        }
                    }
                }
            }
        }

        // fallback: accounts.username
        $fullName = ($employeeFullName !== '') ? $employeeFullName : (string)($acc['username'] ?? '');
    } elseif ($accountType === 'agent' && $hasAgentTable) {
        $st = $conn->prepare("SELECT fName, mName, lName FROM agent WHERE accountId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $accountId);
            $st->execute();
            $r = $st->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $st->close();
            $fullName = trim(($row['fName'] ?? '') . ' ' . ($row['mName'] ?? '') . ' ' . ($row['lName'] ?? ''));
        }
    } elseif ($accountType === 'guide') {
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
    }

    $fullName = preg_replace('/\s+/', ' ', trim($fullName));
    if ($fullName === '' || strcasecmp(trim($managerName), $fullName) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member information cannot be found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 임시 비밀번호 생성 (8자리 랜덤)
    $tempPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

    // 메일 발송 실패 시 비밀번호가 바뀌지 않도록 트랜잭션 처리
    $conn->begin_transaction();
    $old = null;
    $stOld = $conn->prepare("SELECT password, defaultPasswordStat FROM accounts WHERE accountId = ? LIMIT 1");
    if (!$stOld) throw new Exception("이전 비밀번호 조회 준비 실패: " . $conn->error);
    $stOld->bind_param('i', $accountId);
    $stOld->execute();
    $rOld = $stOld->get_result();
    $old = $rOld ? $rOld->fetch_assoc() : null;
    $stOld->close();
    if (!$old) throw new Exception("이전 비밀번호 조회 실패");

    // 비밀번호 업데이트
    $updateSql = "UPDATE accounts SET password = ?, defaultPasswordStat = 'yes' WHERE accountId = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) throw new Exception("비밀번호 업데이트 준비 실패: " . $conn->error);
    $updateStmt->bind_param('si', $hashedPassword, $accountId);
    $updateStmt->execute();
    $updateStmt->close();

    // 이메일 전송 (SMTP 설정이 있으면 SMTP 우선, 없으면 mail() fallback)
    $to = (string)($acc['emailAddress'] ?? '');
    $subject = 'SMART TRAVEL ADMIN - Temporary Password';
    $text = "Your temporary password is:\n\n{$tempPassword}\n\nPlease log in and change your password immediately.";
    $html = "<div style=\"font-family:Arial, sans-serif; line-height:1.5;\">
        <h2 style=\"margin:0 0 12px;\">Temporary Password</h2>
        <p>Your temporary password is:</p>
        <p style=\"font-size:18px;\"><strong>{$tempPassword}</strong></p>
        <p>Please log in and change your password immediately.</p>
    </div>";

    $sendRes = mailer_send($to, $subject, $html, $text);
    $sent = (bool)($sendRes['ok'] ?? false);
    $via = (string)($sendRes['via'] ?? 'none');
    $postmarkConfigured = (trim((string)getenv('POSTMARK_API_TOKEN')) !== '');
    $debugReturn = (string)getenv('MAIL_DEBUG_RETURN_PASSWORD') === '1';

    if (!$sent) {
        // 롤백: 기존 비밀번호로 원복
        $restoreStmt = $conn->prepare("UPDATE accounts SET password = ?, defaultPasswordStat = ? WHERE accountId = ?");
        if ($restoreStmt) {
            $restoreStmt->bind_param('ssi', $old['password'], $old['defaultPasswordStat'], $accountId);
            $restoreStmt->execute();
            $restoreStmt->close();
        }
        $conn->commit();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $postmarkConfigured
                ? '메일 발송에 실패했습니다. Postmark 설정을 확인해주세요.'
                : '메일 발송에 실패했습니다. Postmark API 토큰 설정이 필요합니다.',
            'data' => [
                'mailSent' => false,
                'mailVia' => $via,
                'debugTempPassword' => $debugReturn ? $tempPassword : null
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '임시 비밀번호가 발급되었습니다. 이메일을 확인해주세요.',
        'email' => $acc['emailAddress'],
        'data' => [
            'mailSent' => true,
            'mailVia' => $via,
            'debugTempPassword' => $debugReturn ? $tempPassword : null
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Reset agent password error: " . $e->getMessage());
    // 트랜잭션이 열려있다면 롤백
    try {
        if ($conn && $conn instanceof mysqli) {
            // mysqli는 in_transaction 속성이 없을 수 있어 안전하게 처리
            @$conn->rollback();
        }
    } catch (Throwable $_) { }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '비밀번호 재설정 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>

