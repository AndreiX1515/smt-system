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
$managerName = trim($_POST['managerName'] ?? ($_POST['name'] ?? ''));
$managerEmail = trim($_POST['managerEmail'] ?? ($_POST['email'] ?? ''));

// 입력 검증
if (empty($managerName) || empty($managerEmail)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your name and email correctly.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 스키마 편차 대응: accounts.emailAddress/email
    $accounts_cols = [];
    $accColRes = $conn->query("SHOW COLUMNS FROM accounts");
    while ($accColRes && ($c = $accColRes->fetch_assoc())) {
        $accounts_cols[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }
    $emailCol = $accounts_cols['emailaddress'] ?? ($accounts_cols['email'] ?? 'emailAddress');

    // 단일 모달(에이전트/가이드 구분 없이): 이메일로 계정 조회 후 이름 검증
    $table_exists = function ($name) use ($conn) {
        $safe = $conn->real_escape_string($name);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    };

    $hasAgentTable = $table_exists('agent');
    $hasGuideTable = $table_exists('guide');
    $hasGuidesTable = $table_exists('guides');

    // 계정 찾기 (agent/guide) - 이메일로 계정을 찾고, 결과로 "로그인 ID(username)"를 반환한다.
    // (정책: accounts.username = 로그인 ID)
    $accStmt = $conn->prepare("SELECT accountId, username, `{$emailCol}` AS emailAddress, accountType FROM accounts WHERE `{$emailCol}` = ? AND accountType IN ('agent','guide') LIMIT 1");
    if (!$accStmt) throw new Exception("쿼리 준비 실패: " . $conn->error);
    $accStmt->bind_param('s', $managerEmail);
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
    $accountType = (string)($acc['accountType'] ?? '');

    // 이름 조회
    $fullName = '';
    if ($accountType === 'agent' && $hasAgentTable) {
        // agent 컬럼 편차 대응
        $agentCols = [];
        $rCols = $conn->query("SHOW COLUMNS FROM agent");
        while ($rCols && ($c = $rCols->fetch_assoc())) $agentCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
        $aidCol = $agentCols['accountid'] ?? 'accountId';
        $fCol = $agentCols['fname'] ?? 'fName';
        $mCol = $agentCols['mname'] ?? 'mName';
        $lCol = $agentCols['lname'] ?? 'lName';

        $st = $conn->prepare("SELECT `{$fCol}` AS fName, `{$mCol}` AS mName, `{$lCol}` AS lName FROM agent WHERE `{$aidCol}` = ? LIMIT 1");
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
            // guides 컬럼 편차 대응
            $guidesCols = [];
            $rCols = $conn->query("SHOW COLUMNS FROM guides");
            while ($rCols && ($c = $rCols->fetch_assoc())) $guidesCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
            $aidCol = $guidesCols['accountid'] ?? 'accountId';
            $guideNameCol = $guidesCols['guidename'] ?? 'guideName';
            $fCol = $guidesCols['fname'] ?? 'fName';
            $mCol = $guidesCols['mname'] ?? 'mName';
            $lCol = $guidesCols['lname'] ?? 'lName';

            $st = $conn->prepare("SELECT `{$guideNameCol}` AS guideName, `{$fCol}` AS fName, `{$mCol}` AS mName, `{$lCol}` AS lName FROM guides WHERE `{$aidCol}` = ? LIMIT 1");
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
            // guide(단수) 컬럼 편차 대응
            $guideCols = [];
            $rCols = $conn->query("SHOW COLUMNS FROM guide");
            while ($rCols && ($c = $rCols->fetch_assoc())) $guideCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
            $aidCol = $guideCols['accountid'] ?? 'accountId';
            $fCol = $guideCols['fname'] ?? 'fName';
            $mCol = $guideCols['mname'] ?? 'mName';
            $lCol = $guideCols['lname'] ?? 'lName';

            $st = $conn->prepare("SELECT `{$fCol}` AS fName, `{$mCol}` AS mName, `{$lCol}` AS lName FROM guide WHERE `{$aidCol}` = ? LIMIT 1");
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
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Success',
        // UI 호환: 기존 key(email)를 유지하되, 실제 값은 username(로그인 ID)
        'email' => (string)($acc['username'] ?? '')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Find agent ID error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while finding your ID.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>

