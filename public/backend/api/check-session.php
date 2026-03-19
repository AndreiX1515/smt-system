<?php
/**
 * check-session.php
 *
 * Checks whether the current request carries a valid admin session.
 * Returns a JSON object with authenticated status, userType, and
 * display info used by the header.
 *
 * File location : public/backend/api/check-session.php
 * Log output    : public/logs/auth.log
 *
 * Session keys checked (in priority order):
 *   admin_accountId  → admin / admin_ph / admin_kr
 *   agent_accountId  → agent
 *   guide_accountId  → guide
 *   cs_accountId     → cs
 *
 * Response shape:
 *   { success, authenticated, userType, accountId, emailAddress, displayName, roleLabel }
 *   { success, authenticated: false }
 */

/* ============================================================
   SHARED SESSION CONFIGURATION
   Keep in sync with login.php and logout.php.
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
   MUST come before require conn.php.
   conn.php → config/session.php may call session_start() — by setting
   params first we guarantee our cookie configuration is used.
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
   HELPERS
   ============================================================ */

/** @param string $type @return bool */
function isAdminType(string $type): bool {
    return in_array($type, ['admin_ph', 'admin_kr', 'admin'], true);
}

/**
 * Builds display name and role label for the session user.
 * @param mysqli $conn
 * @param string $userType
 * @param int    $accountId
 * @return array{ displayName: string, roleLabel: string }
 */
function buildDisplayInfo(mysqli $conn, string $userType, int $accountId): array {
    $displayName = '';
    $roleLabel   = match(true) {
        $userType === 'agent'   => 'Agent',
        $userType === 'guide'   => 'Guide',
        isAdminType($userType)  => 'Administrator',
        default                 => 'Employee',
    };

    if (isAdminType($userType)) {
        return [
            'displayName' => ($userType === 'admin_kr') ? 'ADMIN (KR)' : 'ADMIN (PH)',
            'roleLabel'   => $roleLabel,
        ];
    }
    if ($userType === 'cs') {
        return ['displayName' => 'CS', 'roleLabel' => $roleLabel];
    }

    /* Agent — look up accounts.username via agent table */
    if ($userType === 'agent') {
        $check = $conn->query("SHOW TABLES LIKE 'agent'");
        if ($check && $check->num_rows > 0) {
            $st = $conn->prepare(
                "SELECT COALESCE(NULLIF(a.username,''), '') AS displayName
                 FROM agent ag
                 LEFT JOIN accounts a ON ag.accountId = a.accountId
                 WHERE ag.accountId = ? ORDER BY ag.id ASC LIMIT 1"
            );
            if ($st) {
                $st->bind_param('i', $accountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $displayName = (string)($row['displayName'] ?? '');
            }
        }
    }

    /* Guide — guideName preferred, fallback to accounts.username */
    if ($userType === 'guide') {
        $check = $conn->query("SHOW TABLES LIKE 'guides'");
        if ($check && $check->num_rows > 0) {
            $st = $conn->prepare(
                "SELECT COALESCE(NULLIF(g.guideName,''), NULLIF(a.username,''), '') AS displayName
                 FROM guides g
                 LEFT JOIN accounts a ON g.accountId = a.accountId
                 WHERE g.accountId = ? LIMIT 1"
            );
            if ($st) {
                $st->bind_param('i', $accountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $displayName = (string)($row['displayName'] ?? '');
            }
        }
    }

    /* Fallback — accounts.username or emailAddress */
    if ($displayName === '') {
        $st = $conn->prepare(
            "SELECT COALESCE(NULLIF(username,''), NULLIF(emailAddress,''), '') AS displayName
             FROM accounts WHERE accountId = ? LIMIT 1"
        );
        if ($st) {
            $st->bind_param('i', $accountId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $displayName = (string)($row['displayName'] ?? '');
        }
    }

    return ['displayName' => $displayName, 'roleLabel' => $roleLabel];
}

/* ============================================================
   SESSION CHECK
   Priority order: admin → agent → guide → cs
   First matching key wins.
   ============================================================ */
authLog('DEBUG', 'CHECK', 'Session check requested', ['session_id' => session_id()]);

$sessionMap = [
    'admin' => ['key' => 'admin_accountId', 'typeKey' => 'admin_userType',  'emailKey' => 'admin_emailAddress'],
    'agent' => ['key' => 'agent_accountId', 'typeKey' => 'agent_userType',  'emailKey' => 'agent_emailAddress'],
    'guide' => ['key' => 'guide_accountId', 'typeKey' => 'guide_userType',  'emailKey' => 'guide_emailAddress'],
    'cs'    => ['key' => 'cs_accountId',    'typeKey' => 'cs_userType',     'emailKey' => 'cs_emailAddress'],
];

foreach ($sessionMap as $role => $keys) {
    if (!isset($_SESSION[$keys['key']])) continue;

    $uid  = (int)    $_SESSION[$keys['key']];
    $ut   = (string)($_SESSION[$keys['typeKey']] ?? $role);
    $info = buildDisplayInfo($conn, $ut, $uid);

    authLog('INFO', 'CHECK', 'Authenticated session found', [
        'role'      => $role,
        'accountId' => $uid,
        'userType'  => $ut,
    ]);

    echo json_encode([
        'success'       => true,
        'authenticated' => true,
        'userType'      => $ut,
        'accountId'     => $uid,
        'emailAddress'  => $_SESSION[$keys['emailKey']] ?? '',
        'displayName'   => $info['displayName'],
        'roleLabel'     => $info['roleLabel'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* No matching session key */
authLog('DEBUG', 'CHECK', 'No authenticated session found');
echo json_encode(['success' => true, 'authenticated' => false], JSON_UNESCAPED_UNICODE);
exit;
?>