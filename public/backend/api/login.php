<?php
// backend/api/login.php

session_start();
header('Content-Type: application/json');

error_log("[LOGIN] Starting login.php");

// Check required file
$configPath = '../../config/conn.php';
if (!file_exists($configPath)) {
    error_log("[LOGIN][ERROR] Required config file not found: $configPath");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration missing.']);
    exit;
} else {
    require_once $configPath;
    error_log("[LOGIN] Included config file: $configPath");
}

// Ensure $conn exists
if (!isset($conn)) {
    error_log("[LOGIN][ERROR] Database connection object (\$conn) not set after including conn.php");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not initialized.']);
    exit;
} else {
    error_log("[LOGIN] Database connection object (\$conn) exists");
}

try {
    /* ── Step 1: Input validation ────────────────────────── */
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    error_log("[LOGIN] Received username: '$username'");

    if (empty($username)) {
        error_log("[LOGIN][WARN] Missing username");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter your ID.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($password)) {
        error_log("[LOGIN][WARN] Missing password");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter your Password.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Step 2: Detect actual column names ────────────────── */
    $accCols  = [];
    $colRes   = $conn->query("SHOW COLUMNS FROM accounts");
    if (!$colRes) {
        error_log("[LOGIN][ERROR] Failed to fetch columns from accounts table: " . $conn->error);
        throw new Exception("Could not fetch table schema.");
    }
    while ($c = $colRes->fetch_assoc()) {
        $accCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }
    error_log("[LOGIN] Detected account columns: " . implode(',', array_values($accCols)));

    $emailCol    = $accCols['emailaddress'] ?? ($accCols['email'] ?? 'emailAddress');
    $passwordCol = $accCols['password']     ?? ($accCols['passwordhash'] ?? 'password');
    $statusCol   = $accCols['accountstatus'] ?? ($accCols['status'] ?? 'accountStatus');

    /* ── Step 3: Account lookup ──────────────────────────── */
    $sql = "SELECT
                a.accountId,
                a.username,
                a.`{$emailCol}`    AS emailAddress,
                a.`{$passwordCol}` AS password,
                a.accountType,
                a.`{$statusCol}`   AS accountStatus,
                a.defaultPasswordStat
            FROM accounts a
            WHERE a.`{$emailCol}` = ?
              AND a.accountType IN ('admin_ph','admin_kr','agent','guide','employee')
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("[LOGIN][ERROR] Failed to prepare account lookup query: " . $conn->error);
        throw new Exception('Failed to prepare account lookup query.');
    } else {
        error_log("[LOGIN] Prepared account lookup query successfully");
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account) {
        error_log("[LOGIN][WARN] Account not found for username: $username");
        http_response_code(401);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'ACCOUNT_NOT_FOUND',
            'field'     => 'username',
            'message'   => 'No account found with that ID. Please check and try again.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    error_log("[LOGIN] Account found: accountId={$account['accountId']}, type={$account['accountType']}");

    /* ── Step 4: Account status check ───────────────────── */
    $accountStatus = (string)($account['accountStatus'] ?? '');
    if ($accountStatus !== 'active') {
        error_log("[LOGIN][WARN] Account status not active: $accountStatus");
        http_response_code(403);
        $statusMessages = [
            'inactive'  => 'Your account has been deactivated. Please contact the administrator.',
            'suspended' => 'Your account has been suspended. Please contact the administrator.',
            'pending'   => 'Your account is pending approval. Please wait for confirmation.',
            'banned'    => 'Your account has been banned. Please contact the administrator.',
        ];
        $statusMsg = $statusMessages[$accountStatus] ?? 'Your account is not active. Please contact the administrator.';
        echo json_encode([
            'success'   => false,
            'errorCode' => 'ACCOUNT_' . strtoupper($accountStatus),
            'field'     => 'username',
            'message'   => $statusMsg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Step 5: Password verification ───────────────────── */
    $stored = (string) ($account['password'] ?? '');
    $ok = false;

    if ($stored !== '') {
        if (preg_match('/^\$2[ay]\$|^\$argon2id\$/', $stored)) {
            $ok = password_verify($password, $stored);
            error_log("[LOGIN] Password verification (hash) result: " . ($ok ? 'MATCH' : 'FAIL'));
        }
        if (!$ok && hash_equals($stored, $password)) {
            $ok = true;
            error_log("[LOGIN][WARN] Password matched plain-text fallback");
        }
    }

    if (!$ok) {
        error_log("[LOGIN][WARN] Incorrect password for accountId={$account['accountId']}");
        http_response_code(401);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'WRONG_PASSWORD',
            'field'     => 'password',
            'message'   => 'Incorrect password. Please try again.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Step 6: Session creation ────────────────────────── */
    session_regenerate_id(true);
    $newSessionId = session_id();
    error_log("[LOGIN] Session regenerated: $newSessionId");

    $rawType = (string) ($account['accountType'] ?? 'admin_ph');
    $type = ($rawType === 'employee') ? 'cs' : $rawType;
    $isAdmin = in_array($type, ['admin_ph', 'admin_kr', 'admin'], true);
    $emailOrUser = $account['emailAddress'] ?: ($account['username'] ?? '');

    if ($isAdmin) {
        $_SESSION['admin_accountId'] = (int) $account['accountId'];
        $_SESSION['admin_userType'] = $type;
        $_SESSION['admin_emailAddress'] = $emailOrUser;
        $_SESSION['admin_timeout'] = time();
    } elseif ($type === 'agent') {
        $_SESSION['agent_accountId'] = (int) $account['accountId'];
        $_SESSION['agent_userType'] = 'agent';
        $_SESSION['agent_emailAddress'] = $emailOrUser;
        $_SESSION['agent_timeout'] = time();
    } elseif ($type === 'guide') {
        $_SESSION['guide_accountId'] = (int) $account['accountId'];
        $_SESSION['guide_userType'] = 'guide';
        $_SESSION['guide_emailAddress'] = $emailOrUser;
        $_SESSION['guide_timeout'] = time();
    } elseif ($type === 'cs') {
        $_SESSION['cs_accountId'] = (int) $account['accountId'];
        $_SESSION['cs_userType'] = 'cs';
        $_SESSION['cs_emailAddress'] = $emailOrUser;
        $_SESSION['cs_timeout'] = time();
    } else {
        $_SESSION['admin_accountId'] = (int) $account['accountId'];
        $_SESSION['admin_userType'] = 'admin_ph';
        $_SESSION['admin_emailAddress'] = $emailOrUser;
        $_SESSION['admin_timeout'] = time();
    }
    error_log("[LOGIN] Session created for accountId={$account['accountId']} type=$type");

    /* ── Step 7: Optional theme save ───────────────────── */
    if (!empty($_POST['theme'])) {
        $preferredTheme = $_POST['theme'];
        $accountId = (int)$account['accountId'];
        $stmt = $conn->prepare("
            INSERT INTO user_settings (account_id, setting_key, setting_value)
            VALUES (?, 'theme', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        if ($stmt) {
            $stmt->bind_param('is', $accountId, $preferredTheme);
            $stmt->execute();
            $stmt->close();
            error_log("[LOGIN] User theme saved for accountId=$accountId, theme=$preferredTheme");
        } else {
            error_log("[LOGIN][WARN] Failed to save user theme: " . $conn->error);
        }
    }

    /* ── Step 8: Return success + redirect ─────────────── */
    $redirectMap = [
        'agent' => '../../public/agent/overview.php',
        'guide' => '../../public/guide/full-list.php',
        'cs'    => '../../public/cs/inquiry-list.php',
        'admin' => '../../pages/super/overview.php'
    ];

    echo json_encode([
        'success'     => true,
        'userType'    => $type,
        'redirectUrl' => $redirectMap[$type] ?? $redirectMap['admin']
    ], JSON_UNESCAPED_UNICODE);
    error_log("[LOGIN] Login success, returning JSON for accountId={$account['accountId']}");

} catch (Exception $e) {
    error_log("[LOGIN][EXCEPTION] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
    exit;
}