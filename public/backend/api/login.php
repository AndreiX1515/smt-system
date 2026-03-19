<?php
/**
 * login.php
 *
 * Authenticates an admin-side user (admin_ph / admin_kr / agent / guide / cs)
 * and creates a server-side session.
 *
 * File location : public/backend/api/login.php
 * Log output    : public/logs/auth.log
 *
 * Steps:
 *  1. Session bootstrap  — must happen before conn.php so cookie params are set first
 *  2. Input validation   — username (email) + password presence check
 *  3. Account lookup     — emailAddress in accounts table, active types only
 *  4. Status check       — account must be 'active'
 *  5. Password verify    — bcrypt / argon2 / legacy plain-text fallback
 *  6. Session creation   — regenerate ID, write role-keyed $_SESSION vars
 *  7. DB session record  — insert into user_sessions if table exists
 *  8. lastLoginAt update — update accounts table
 *  9. Login history      — write to admin_login_history
 * 10. Success response   — return redirectUrl + userType
 */

/* ============================================================
   SHARED SESSION CONFIGURATION
   These params must be identical in login.php, logout.php,
   and check-session.php. Changing them here changes all three.
   ============================================================ */
define('SESSION_COOKIE_PATH',     '/');
define('SESSION_COOKIE_LIFETIME', 0);
define('SESSION_COOKIE_SECURE',   isset($_SERVER['HTTPS']));
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

/* ── Log file path (relative to this file's directory) ── */
define('AUTH_LOG_FILE', __DIR__ . '/../../logs/auth.log');

/* ── Step 1: Session bootstrap ───────────────────────────────────────────────
   MUST come before require conn.php.
   conn.php → config/session.php may call session_start() with its own params.
   By setting params here first, we guarantee our cookie configuration wins
   even if session.php calls session_start() during the require below.
   If a session is already active (e.g. during testing), we skip start.
─────────────────────────────────────────────────────────────────────────────*/
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_LIFETIME,
        'path'     => SESSION_COOKIE_PATH,
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => SESSION_COOKIE_HTTPONLY,
        'samesite' => SESSION_COOKIE_SAMESITE,
    ]);
    session_start();
}

require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');

/* Block CORS from unknown origins in production — adjust as needed */
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

/* ============================================================
   LOGGING HELPER
   Writes a structured log line to AUTH_LOG_FILE.
   Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [step] message {context}
   ============================================================ */

/**
 * Writes a structured log entry to the auth log file.
 *
 * @param string $level   LOG_DEBUG | LOG_INFO | LOG_WARN | LOG_ERROR
 * @param string $step    Short label for the current step (e.g. 'INPUT', 'AUTH')
 * @param string $message Human-readable description.
 * @param array  $context Optional key-value pairs appended as JSON.
 */
function authLog(string $level, string $step, string $message, array $context = []): void {
    $logDir = dirname(AUTH_LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $ts      = date('Y-m-d H:i:s');
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ctx     = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    $line    = "[{$ts}] [{$level}] [{$step}] [{$ip}] {$message}{$ctx}" . PHP_EOL;

    file_put_contents(AUTH_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/* ============================================================
   HELPERS
   ============================================================ */

/**
 * Saves a login attempt to admin_login_history.
 * Silently swallows errors — login history is non-critical.
 *
 * @param mysqli      $conn
 * @param int|null    $accountId
 * @param string      $email
 * @param string|null $accountType
 * @param string      $status        'success' | 'failed'
 * @param string|null $failureReason
 */
function saveLoginHistory(
    mysqli $conn,
    ?int   $accountId,
    string $email,
    ?string $accountType,
    string $status,
    ?string $failureReason = null
): void {
    try {
        $ip        = $_SERVER['REMOTE_ADDR']     ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $conn->prepare("
            INSERT INTO admin_login_history
                (account_id, email, account_type, login_status, failure_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param('issssss',
                $accountId, $email, $accountType,
                $status, $failureReason, $ip, $userAgent
            );
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        authLog('ERROR', 'HISTORY', 'Failed to save login history', ['error' => $e->getMessage()]);
    }
}

/**
 * Detects the actual column name in a schema that may differ
 * between environments (e.g. 'accountId' vs 'accountid').
 *
 * @param mysqli $conn
 * @param string $table      Table name to inspect.
 * @param string $targetLower Lowercase version of the column to find.
 * @param string $default    Value to return if the column is not found.
 * @return string
 */
function detectColumn(mysqli $conn, string $table, string $targetLower, string $default): string {
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if (!$result) return $default;
    while ($col = $result->fetch_assoc()) {
        if (strtolower((string)$col['Field']) === $targetLower) {
            return (string)$col['Field'];
        }
    }
    return $default;
}

/* ============================================================
   ONLY POST ALLOWED
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authLog('WARN', 'REQUEST', 'Rejected non-POST request', ['method' => $_SERVER['REQUEST_METHOD']]);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
   MAIN LOGIN FLOW
   ============================================================ */
try {

    /* ── Step 2: Input validation ────────────────────────────────────────── */
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    authLog('INFO', 'INPUT', 'Login attempt received', ['username' => $username]);

    if (empty($username)) {
        authLog('WARN', 'INPUT', 'Missing username');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter your ID.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($password)) {
        authLog('WARN', 'INPUT', 'Missing password', ['username' => $username]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter your Password.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Step 3: Account lookup ──────────────────────────────────────────── */
    authLog('INFO', 'LOOKUP', 'Querying accounts table', ['username' => $username]);

    /*
     * Detect actual column names to handle schema variations across environments.
     * Supported variants:
     *   emailAddress | email
     *   password     | passwordHash
     *   accountStatus| status
     */
    $accCols  = [];
    $colRes   = $conn->query("SHOW COLUMNS FROM accounts");
    while ($colRes && ($c = $colRes->fetch_assoc())) {
        $accCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }
    $emailCol    = $accCols['emailaddress'] ?? ($accCols['email']          ?? 'emailAddress');
    $passwordCol = $accCols['password']     ?? ($accCols['passwordhash']   ?? 'password');
    $statusCol   = $accCols['accountstatus']?? ($accCols['status']         ?? 'accountStatus');

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
        throw new Exception('Failed to prepare account lookup query: ' . $conn->error);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account) {
        authLog('WARN', 'LOOKUP', 'Account not found', ['username' => $username]);
        saveLoginHistory($conn, null, $username, null, 'failed', 'Account not found');
        http_response_code(401);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'ACCOUNT_NOT_FOUND',
            'field'     => 'username',
            'message'   => 'No account found with that ID. Please check and try again.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    authLog('INFO', 'LOOKUP', 'Account found', [
        'accountId'   => $account['accountId'],
        'accountType' => $account['accountType'],
    ]);

    /* ── Step 4: Account status check ───────────────────────────────────── */
    $accountStatus = (string)($account['accountStatus'] ?? '');
    if ($accountStatus !== 'active') {
        authLog('WARN', 'STATUS', 'Account is not active', [
            'accountId' => $account['accountId'],
            'status'    => $accountStatus,
        ]);
        saveLoginHistory($conn, (int)$account['accountId'], $username, $account['accountType'], 'failed', 'Account ' . $accountStatus);

        /* Specific message per status value */
        $statusMessages = [
            'inactive'  => 'Your account has been deactivated. Please contact the administrator.',
            'suspended' => 'Your account has been suspended. Please contact the administrator.',
            'pending'   => 'Your account is pending approval. Please wait for confirmation.',
            'banned'    => 'Your account has been banned. Please contact the administrator.',
        ];
        $statusMsg = $statusMessages[$accountStatus]
            ?? 'Your account is not active. Please contact the administrator.';

        http_response_code(403);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'ACCOUNT_' . strtoupper($accountStatus),
            'field'     => 'username',
            'message'   => $statusMsg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Step 5: Password verification ──────────────────────────────────── */
    authLog('INFO', 'AUTH', 'Verifying password', ['accountId' => $account['accountId']]);

    $stored = (string) ($account['password'] ?? '');
    $ok     = false;

    if ($stored !== '') {
        /* Modern hashed passwords — bcrypt or argon2id */
        if (preg_match('/^\$2[ay]\$|^\$argon2id\$/', $stored)) {
            $ok = password_verify($password, $stored);
            authLog('DEBUG', 'AUTH', 'Tried password_verify', ['matched' => $ok]);
        }
        /* Legacy plain-text fallback (timing-safe comparison) */
        if (!$ok && hash_equals($stored, $password)) {
            $ok = true;
            authLog('WARN', 'AUTH', 'Plain-text password match — consider upgrading to bcrypt', [
                'accountId' => $account['accountId'],
            ]);
        }
    }

    if (!$ok) {
        authLog('WARN', 'AUTH', 'Password mismatch', ['accountId' => $account['accountId']]);
        saveLoginHistory($conn, (int)$account['accountId'], $username, $account['accountType'], 'failed', 'Invalid password');
        http_response_code(401);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'WRONG_PASSWORD',
            'field'     => 'password',
            'message'   => 'Incorrect password. Please try again.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    authLog('INFO', 'AUTH', 'Password verified successfully', ['accountId' => $account['accountId']]);

    /* ── Step 6: Session creation ────────────────────────────────────────── */
    authLog('INFO', 'SESSION', 'Regenerating session ID');

    /*
     * Invalidate any old session record before regenerating.
     * session_regenerate_id(true) deletes the old session file on the server
     * and issues a new session cookie to the browser.
     */
    session_regenerate_id(true);
    $newSessionId = session_id();

    /*
     * Normalise accountType:
     *   DB stores CS users as 'employee' — the app treats them as 'cs'.
     */
    $rawType = (string) ($account['accountType'] ?? 'admin_ph');
    $type    = ($rawType === 'employee') ? 'cs' : $rawType;
    $isAdmin = in_array($type, ['admin_ph', 'admin_kr', 'admin'], true);
    $emailOrUser = $account['emailAddress'] ?: ($account['username'] ?? '');

    /* Write role-namespaced session keys */
    if ($isAdmin) {
        $_SESSION['admin_accountId']          = (int) $account['accountId'];
        $_SESSION['admin_userType']           = $type;
        $_SESSION['admin_emailAddress']       = $emailOrUser;
        $_SESSION['admin_timeout']            = time();
        $_SESSION['admin_defaultPasswordStat']= $account['defaultPasswordStat'] ?? 'N';
    } elseif ($type === 'agent') {
        $_SESSION['agent_accountId']    = (int) $account['accountId'];
        $_SESSION['agent_userType']     = 'agent';
        $_SESSION['agent_emailAddress'] = $emailOrUser;
        $_SESSION['agent_timeout']      = time();
    } elseif ($type === 'guide') {
        $_SESSION['guide_accountId']    = (int) $account['accountId'];
        $_SESSION['guide_userType']     = 'guide';
        $_SESSION['guide_emailAddress'] = $emailOrUser;
        $_SESSION['guide_timeout']      = time();
    } elseif ($type === 'cs') {
        $_SESSION['cs_accountId']    = (int) $account['accountId'];
        $_SESSION['cs_userType']     = 'cs';
        $_SESSION['cs_emailAddress'] = $emailOrUser;
        $_SESSION['cs_timeout']      = time();
    } else {
        /* Unknown type safety fallback */
        authLog('WARN', 'SESSION', 'Unknown accountType — defaulting to admin_ph', ['type' => $type]);
        $_SESSION['admin_accountId']    = (int) $account['accountId'];
        $_SESSION['admin_userType']     = 'admin_ph';
        $_SESSION['admin_emailAddress'] = $emailOrUser;
        $_SESSION['admin_timeout']      = time();
    }

    authLog('INFO', 'SESSION', 'Session created', [
        'accountId'    => $account['accountId'],
        'type'         => $type,
        'new_session'  => $newSessionId,
    ]);

    /* ── Step 7: DB session record ───────────────────────────────────────── */
    $tableCheck  = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;

    if ($tableExists) {
        $accountIdColumn = detectColumn($conn, 'user_sessions', 'accountid', 'accountid');
        $now             = date('Y-m-d H:i:s');
        $ip              = $_SERVER['REMOTE_ADDR']     ?? 'Unknown';
        $ua              = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        /* Remove any existing session records for this account first */
        $delStmt = $conn->prepare("DELETE FROM user_sessions WHERE `{$accountIdColumn}` = ?");
        if ($delStmt) {
            $delStmt->bind_param('i', $account['accountId']);
            $delStmt->execute();
            $delStmt->close();
        }

        /* Insert fresh session record */
        $insStmt = $conn->prepare(
            "INSERT INTO user_sessions
                (session_id, `{$accountIdColumn}`, login_time, last_activity, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if ($insStmt) {
            $insStmt->bind_param('sissss', $newSessionId, $account['accountId'], $now, $now, $ip, $ua);
            $insStmt->execute();
            $insStmt->close();
            authLog('INFO', 'SESSION', 'DB session record written', ['session_id' => $newSessionId]);
        } else {
            authLog('WARN', 'SESSION', 'Failed to prepare user_sessions insert', ['error' => $conn->error]);
        }
    } else {
        authLog('DEBUG', 'SESSION', 'user_sessions table not found — skipping DB record');
    }

    /* ── Step 8: Update lastLoginAt ──────────────────────────────────────── */
    $upd = $conn->prepare("UPDATE accounts SET lastLoginAt = NOW() WHERE accountId = ?");
    if ($upd) {
        $upd->bind_param('i', $account['accountId']);
        $upd->execute();
        $upd->close();
        authLog('INFO', 'ACCOUNT', 'lastLoginAt updated', ['accountId' => $account['accountId']]);
    }

    /* ── Step 9: Login history ───────────────────────────────────────────── */
    saveLoginHistory($conn, (int)$account['accountId'], $emailOrUser, $rawType, 'success', null);


    /* ── Step 10: Build redirect URL and respond ─────────────────────────── */
    $redirectUrl = './super/overview.php'; // default

    
    if ($type === 'agent') {
        /* Agent must complete their profile before accessing the dashboard */
        $profileStmt = $conn->prepare(
            "SELECT agencyName, fName, lName, contactNo FROM agent WHERE accountId = ? LIMIT 1"
        );
        if ($profileStmt) {
            $profileStmt->bind_param('i', $account['accountId']);
            $profileStmt->execute();
            $agentProfile = $profileStmt->get_result()->fetch_assoc();
            $profileStmt->close();

            $profileComplete = $agentProfile
                && !empty($agentProfile['agencyName'])
                && !empty($agentProfile['fName'])
                && !empty($agentProfile['lName'])
                && !empty($agentProfile['contactNo']);

            $redirectUrl = $profileComplete ? './agent/overview.html' : './complete-profile.html';
            authLog('INFO', 'REDIRECT', 'Agent profile check', [
                'complete' => $profileComplete,
                'redirect' => $redirectUrl,
            ]);
        }
    } elseif ($type === 'guide') {
        $redirectUrl = './guide/full-list.html';
    } elseif ($type === 'cs') {
        $redirectUrl = './cs/inquiry-list.html';
    }

    authLog('INFO', 'LOGIN', 'Login successful', [
        'accountId'   => $account['accountId'],
        'type'        => $type,
        'redirectUrl' => $redirectUrl,
    ]);

    http_response_code(200);
    echo json_encode([
        'success'     => true,
        'message'     => 'Login successful.',
        'redirectUrl' => $redirectUrl,
        'userType'    => $type,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    authLog('ERROR', 'LOGIN', 'Unhandled exception', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A login error occurred. Please try again.',
    ], JSON_UNESCAPED_UNICODE);

} catch (Error $e) {
    authLog('ERROR', 'LOGIN', 'Fatal error', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A login error occurred. Please try again.',
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>