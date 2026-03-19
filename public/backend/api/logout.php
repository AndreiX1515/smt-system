<?php
/**
 * logout.php
 *
 * Destroys the current admin session — PHP session, browser cookie,
 * and any DB record in user_sessions.
 *
 * File location : public/backend/api/logout.php
 * Log output    : public/logs/auth.log
 *
 * SESSION_COOKIE_* constants must be identical to login.php and
 * check-session.php. They are defined here in case this file is
 * ever called before conn.php has loaded.
 */

/* ============================================================
   SHARED SESSION CONFIGURATION
   Keep in sync with login.php and check-session.php.
   ============================================================ */
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH',     '/');
    define('SESSION_COOKIE_LIFETIME', 0);
    define('SESSION_COOKIE_SECURE',   isset($_SERVER['HTTPS']));
    define('SESSION_COOKIE_HTTPONLY', true);
    define('SESSION_COOKIE_SAMESITE', 'Lax');
}
if (!defined('AUTH_LOG_FILE')) {
    define('AUTH_LOG_FILE', __DIR__ . '/../../logs/auth.log');
}


/* ── Session bootstrap ───────────────────────────────────────────────────────
   Must set cookie params BEFORE session_start() and BEFORE conn.php,
   because conn.php → config/session.php may call session_start() itself.
   Setting params here first guarantees our path='/' cookie config wins.
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
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}


/* ============================================================
   LOGGING HELPER
   ============================================================ */
if (!function_exists('authLog')) {
    function authLog(string $level, string $step, string $message, array $context = []): void {
        $logDir = dirname(AUTH_LOG_FILE);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $ts   = date('Y-m-d H:i:s');
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ctx  = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line = "[{$ts}] [{$level}] [{$step}] [{$ip}] {$message}{$ctx}" . PHP_EOL;
        file_put_contents(AUTH_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }
}


/* ============================================================
   CORE SESSION DESTRUCTION
   ============================================================ */

/**
 * Three-step session teardown:
 *  A. Clear $_SESSION array
 *  B. Expire the browser cookie using the correct configured path
 *  C. Destroy the server-side session file
 *
 * Uses session_get_cookie_params() so the cookie path always matches
 * what was set at session_start() time — prevents the cookie surviving
 * a mismatch between creation path and expiry path.
 */
function destroySession(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}


/* ============================================================
   MAIN LOGOUT FLOW
   ============================================================ */
try {
    authLog('INFO', 'LOGOUT', 'Logout request received');

    /* ── Step 1: Capture session data before clearing ──────────────────── */
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    $guideAccountId = $_SESSION['guide_accountId'] ?? null;
    $csAccountId    = $_SESSION['cs_accountId']    ?? null;
    $anyAccountId   = $adminAccountId ?: ($agentAccountId ?: ($guideAccountId ?: $csAccountId));
    $currentSessionId = session_id();

    authLog('INFO', 'LOGOUT', 'Session data captured', [
        'session_id' => $currentSessionId,
        'accountId'  => $anyAccountId,
    ]);


    
    /* ── Step 2: Remove DB session records ─────────────────────────────── */
    $tableCheck  = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;

    if ($tableExists) {
        authLog('INFO', 'DB', 'Removing user_sessions records');

        $accountIdColumn = 'accountid';
        $colResult = $conn->query("SHOW COLUMNS FROM user_sessions");
        if ($colResult) {
            while ($col = $colResult->fetch_assoc()) {
                if (strtolower((string)$col['Field']) === 'accountid') {
                    $accountIdColumn = (string)$col['Field'];
                    break;
                }
            }
        }

        if ($currentSessionId) {
            $st = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            if ($st) {
                $st->bind_param('s', $currentSessionId);
                $st->execute();
                authLog('INFO', 'DB', 'Deleted by session_id', [
                    'session_id' => $currentSessionId,
                    'rows'       => $st->affected_rows,
                ]);
                $st->close();
            }
        }

        if ($anyAccountId) {
            $st = $conn->prepare("DELETE FROM user_sessions WHERE `{$accountIdColumn}` = ?");
            if ($st) {
                $st->bind_param('i', $anyAccountId);
                $st->execute();
                authLog('INFO', 'DB', 'Deleted by accountId', [
                    'accountId' => $anyAccountId,
                    'rows'      => $st->affected_rows,
                ]);
                $st->close();
            }
        }
    } else {
        authLog('DEBUG', 'DB', 'user_sessions table not found — skipping DB cleanup');
    }

    /* ── Step 3: Destroy PHP session ───────────────────────────────────── */
    authLog('INFO', 'SESSION', 'Destroying session', ['session_id' => $currentSessionId]);
    destroySession();
    authLog('INFO', 'SESSION', 'Session destroyed successfully');

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logged out.'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    authLog('ERROR', 'LOGOUT', 'Exception during logout', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    try { destroySession(); } catch (Throwable $_) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Logout failed. Please try again.'], JSON_UNESCAPED_UNICODE);

} catch (Error $e) {
    authLog('ERROR', 'LOGOUT', 'Fatal error during logout', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    try { destroySession(); } catch (Throwable $_) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Logout failed. Please try again.'], JSON_UNESCAPED_UNICODE);
}
exit;
?>