<?php
require "../conn.php";
require_once __DIR__ . '/../lib/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Only POST is allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    send_json_response(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'send_link':
            sendResetLink($conn, $input);
            break;
        case 'reset_with_token':
            resetWithToken($conn, $input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} catch (Throwable $e) {
    // Error(: undefined function)  JSON  
    log_activity(0, "password_reset_link_error", "Password reset link API error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'A server error occurred.'], 500);
}

function normalizeName($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', '', $s); //  
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    return $s;
}

function table_exists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $r && $r->num_rows > 0;
}

function table_columns(mysqli $conn, string $table): array {
    $cols = [];
    if (!table_exists($conn, $table)) return $cols;
    $r = $conn->query("SHOW COLUMNS FROM `{$table}`");
    while ($r && ($row = $r->fetch_assoc())) {
        $cols[strtolower($row['Field'])] = $row['Field'];
    }
    return $cols;
}

function build_full_name_from_parts($f, $m, $l): string {
    $name = trim((string)$f . ' ' . (string)$m . ' ' . (string)$l);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim((string)$name);
}

function account_name_candidates(mysqli $conn, int $accountId, string $accountType, string $usernameFromAccounts = ''): array {
    $cands = [];

    // 1) accounts     ( )
    $accCols = accounts_columns($conn);
    $parts = [];
    foreach (['firstname', 'lastname', 'displayname'] as $k) {
        if (isset($accCols[$k])) $parts[] = $accCols[$k];
    }
    if (!empty($parts)) {
        $selects = [];
        foreach ($parts as $col) $selects[] = "`{$col}`";
        $stmt = $conn->prepare("SELECT " . implode(', ', $selects) . " FROM accounts WHERE accountId = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $fn = isset($accCols['firstname']) ? ($row[$accCols['firstname']] ?? '') : '';
                $ln = isset($accCols['lastname']) ? ($row[$accCols['lastname']] ?? '') : '';
                $dn = isset($accCols['displayname']) ? ($row[$accCols['displayname']] ?? '') : '';
                $cands[] = build_full_name_from_parts($fn, '', $ln);
                $cands[] = trim((string)$fn);
                $cands[] = trim((string)$dn);
            }
        }
    }

    // 2) accountType     
    $t = strtolower(trim((string)$accountType));

    if ($t === 'agent' && table_exists($conn, 'agent')) {
        $cols = table_columns($conn, 'agent');
        $f = $cols['fname'] ?? null;
        $m = $cols['mname'] ?? null;
        $l = $cols['lname'] ?? null;
        $aidCol = $cols['accountid'] ?? null;
        if ($aidCol && ($f || $m || $l)) {
            $sel = [];
            if ($f) $sel[] = "`{$f}` AS f";
            if ($m) $sel[] = "`{$m}` AS m";
            if ($l) $sel[] = "`{$l}` AS l";
            $stmt = $conn->prepare("SELECT " . implode(', ', $sel) . " FROM agent WHERE `{$aidCol}` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $accountId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $cands[] = build_full_name_from_parts($row['f'] ?? '', $row['m'] ?? '', $row['l'] ?? '');
                    $cands[] = trim((string)($row['f'] ?? ''));
                }
            }
        }
    }

    if ($t === 'guide') {
        if (table_exists($conn, 'guides')) {
            $cols = table_columns($conn, 'guides');
            $aidCol = $cols['accountid'] ?? null;
            $guideNameCol = $cols['guidename'] ?? null;
            $f = $cols['fname'] ?? null;
            $m = $cols['mname'] ?? null;
            $l = $cols['lname'] ?? null;
            if ($aidCol) {
                $sel = [];
                if ($guideNameCol) $sel[] = "`{$guideNameCol}` AS gn";
                if ($f) $sel[] = "`{$f}` AS f";
                if ($m) $sel[] = "`{$m}` AS m";
                if ($l) $sel[] = "`{$l}` AS l";
                if (!empty($sel)) {
                    $stmt = $conn->prepare("SELECT " . implode(', ', $sel) . " FROM guides WHERE `{$aidCol}` = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i', $accountId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row) {
                            $gn = trim((string)($row['gn'] ?? ''));
                            if ($gn !== '') $cands[] = $gn;
                            $cands[] = build_full_name_from_parts($row['f'] ?? '', $row['m'] ?? '', $row['l'] ?? '');
                            $cands[] = trim((string)($row['f'] ?? ''));
                        }
                    }
                }
            }
        } elseif (table_exists($conn, 'guide')) {
            $cols = table_columns($conn, 'guide');
            $aidCol = $cols['accountid'] ?? null;
            $f = $cols['fname'] ?? null;
            $m = $cols['mname'] ?? null;
            $l = $cols['lname'] ?? null;
            if ($aidCol && ($f || $m || $l)) {
                $sel = [];
                if ($f) $sel[] = "`{$f}` AS f";
                if ($m) $sel[] = "`{$m}` AS m";
                if ($l) $sel[] = "`{$l}` AS l";
                $stmt = $conn->prepare("SELECT " . implode(', ', $sel) . " FROM guide WHERE `{$aidCol}` = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $accountId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        $cands[] = build_full_name_from_parts($row['f'] ?? '', $row['m'] ?? '', $row['l'] ?? '');
                        $cands[] = trim((string)($row['f'] ?? ''));
                    }
                }
            }
        }
    }

    // /: client 
    if (table_exists($conn, 'client')) {
        $cols = table_columns($conn, 'client');
        $aidCol = $cols['accountid'] ?? null;
        $f = $cols['fname'] ?? null;
        $l = $cols['lname'] ?? null;
        if ($aidCol && ($f || $l)) {
            $sel = [];
            if ($f) $sel[] = "`{$f}` AS f";
            if ($l) $sel[] = "`{$l}` AS l";
            $stmt = $conn->prepare("SELECT " . implode(', ', $sel) . " FROM client WHERE `{$aidCol}` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $accountId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $cands[] = build_full_name_from_parts($row['f'] ?? '', '', $row['l'] ?? '');
                    $cands[] = trim((string)($row['f'] ?? ''));
                }
            }
        }
    }

    // admin/employee:    username fallback
    if ($usernameFromAccounts !== '') $cands[] = trim((string)$usernameFromAccounts);

    // normalize + de-dup
    $out = [];
    $seen = [];
    foreach ($cands as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        $k = normalizeName($v);
        if ($k === '' || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $v;
    }
    return $out;
}

function isValidPasswordFormat($password) {
    $password = (string)$password;
    if (strlen($password) < 8 || strlen($password) > 12) return false;
    if (!preg_match('/[a-zA-Z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $password)) return false;
    return true;
}

function accounts_columns(mysqli $conn): array {
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM accounts");
    while ($r && ($row = $r->fetch_assoc())) {
        $cols[strtolower($row['Field'])] = $row['Field'];
    }
    return $cols;
}

function accounts_pick_col(array $cols, string $a, string $b, string $fallback): string {
    $la = strtolower($a);
    $lb = strtolower($b);
    if (isset($cols[$la])) return $cols[$la];
    if (isset($cols[$lb])) return $cols[$lb];
    return $fallback;
}

function ensure_password_reset_storage(mysqli $conn, array $accCols): array {
    // Prefer storing token/expires in accounts if columns exist; else use password_reset_tokens table.
    $hasToken = isset($accCols['passwordresettoken']);
    $hasExp = isset($accCols['passwordresetexpires']);
    if ($hasToken && $hasExp) {
        return ['mode' => 'accounts', 'tokenCol' => $accCols['passwordresettoken'], 'expCol' => $accCols['passwordresetexpires']];
    }

    // Fallback table
    $conn->query("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            accountId INT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expiresAt DATETIME NOT NULL,
            usedAt DATETIME DEFAULT NULL,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_token (token),
            KEY idx_account_expires (accountId, expiresAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    return ['mode' => 'table'];
}

function sendResetLink($conn, $input) {
    $name = sanitize_input($input['name'] ?? '');
    $email = sanitize_input($input['email'] ?? '');

    if (empty($name) || empty($email)) {
        send_json_response(['success' => false, 'message' => 'Please enter your name and email.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }

    // accounts   
    $accCols = accounts_columns($conn);
    $emailCol = accounts_pick_col($accCols, 'emailAddress', 'email', 'emailAddress');
    $statusCol = accounts_pick_col($accCols, 'accountStatus', 'status', 'accountStatus');

    //   (accounts) +  accountType      
    $stmt = $conn->prepare("
        SELECT 
            a.accountId,
            a.username,
            a.accountType,
            a.`{$emailCol}` AS emailAddress
        FROM accounts a
        WHERE a.`{$emailCol}` = ? AND LOWER(COALESCE(a.`{$statusCol}`,'')) = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        send_json_response(['success' => false, 'message' => 'There is no member information that matches the information you entered.'], 404);
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    $inputName = normalizeName($name);
    $aid = (int)($row['accountId'] ?? 0);
    $atype = (string)($row['accountType'] ?? '');
    $uname = (string)($row['username'] ?? '');
    $rawCandidates = account_name_candidates($conn, $aid, $atype, $uname);
    $candidates = [];
    foreach ($rawCandidates as $c) $candidates[] = normalizeName($c);

    $matched = false;
    foreach ($candidates as $cand) {
        if ($cand !== '' && $cand === $inputName) {
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        send_json_response(['success' => false, 'message' => 'There is no member information that matches the information you entered.'], 404);
    }

    $accountId = (int)$row['accountId'];
    $token = bin2hex(random_bytes(32)); // 64 chars

    // token   
    $store = ensure_password_reset_storage($conn, $accCols);
    if (($store['mode'] ?? '') === 'accounts') {
        $tokenCol = $store['tokenCol'];
        $expCol = $store['expCol'];
        $u = $conn->prepare("UPDATE accounts SET `{$tokenCol}` = ?, `{$expCol}` = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE accountId = ?");
        $u->bind_param("si", $token, $accountId);
        if (!$u->execute()) {
            $err = $u->error ?: $conn->error;
            $u->close();
            send_json_response(['success' => false, 'message' => 'Failed to generate reset link.'], 500);
        }
        $u->close();
    } else {
        // table mode
        $ins = $conn->prepare("INSERT INTO password_reset_tokens (accountId, token, expiresAt) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        if (!$ins) send_json_response(['success' => false, 'message' => 'Failed to generate reset link.'], 500);
        $ins->bind_param('is', $accountId, $token);
        if (!$ins->execute()) {
            $err = $ins->error ?: $conn->error;
            $ins->close();
            send_json_response(['success' => false, 'message' => 'Failed to generate reset link.'], 500);
        }
        $ins->close();
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'www.smt-escape.com';
    $resetUrl = "https://{$host}/user/change-password-reset.html?token=" . urlencode($token);

    $subject = 'Smart Travel - Password reset link';
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $html = "
        <div style=\"font-family: Arial, sans-serif; line-height: 1.5;\">
          <h2 style=\"margin:0 0 12px;\">Password reset</h2>
          <p>We received a request to reset the password for <strong>{$safeEmail}</strong>.</p>
          <p>This link will expire in <strong>30 minutes</strong>.</p>
          <p style=\"margin:18px 0;\">
            <a href=\"{$safeUrl}\" style=\"display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px;\">Reset password</a>
          </p>
          <p>If you did not request this, you can ignore this email.</p>
          <p style=\"color:#777;font-size:12px;\">Smart Travel</p>
        </div>
    ";
    $text = "Password reset link (expires in 30 minutes): {$resetUrl}";

    $sendRes = mailer_send($email, $subject, $html, $text);
    $mailSent = (bool)($sendRes['ok'] ?? false);
    $mailVia = (string)($sendRes['via'] ?? 'none');
    $mailErr = (string)($sendRes['error'] ?? '');

    // SMTP    ""   (:     +  )
    $postmarkConfigured = (trim((string)getenv('POSTMARK_API_TOKEN')) !== '');
    if (!$mailSent && $postmarkConfigured) {
        error_log("Password reset link mail FAILED via {$mailVia} for {$email}: {$mailErr}");
        send_json_response([
            'success' => false,
            'message' => 'Failed to send password reset link email. Please try again.',
        ], 500);
    }

    // SMTP /        (/)
    if (!$mailSent) error_log("Password reset link (not mailed) for {$email}: {$resetUrl}");
    log_activity($accountId, "password_reset_link_sent", "Password reset link generated for {$email}");

    $debugReturn = (string)getenv('MAIL_DEBUG_RETURN_LINK') === '1';
    send_json_response([
        'success' => true,
        'message' => 'Password reset link has been sent to your email.',
        'data' => [
            'expiresIn' => 1800,
            'mailSent' => $mailSent,
            'mailVia' => $mailVia,
            'resetUrl' => $debugReturn ? $resetUrl : null
        ]
    ]);
}

function resetWithToken($conn, $input) {
    $token = sanitize_input($input['token'] ?? '');
    $newPassword = $input['newPassword'] ?? '';

    if (empty($token) || empty($newPassword)) {
        send_json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
    }

    if (!isValidPasswordFormat($newPassword)) {
        send_json_response(['success' => false, 'message' => 'Password must be 8-12 characters and include letters, numbers, and a special character.'], 400);
    }

    $accCols = accounts_columns($conn);
    $emailCol = accounts_pick_col($accCols, 'emailAddress', 'email', 'emailAddress');
    $statusCol = accounts_pick_col($accCols, 'accountStatus', 'status', 'accountStatus');
    $passwordCol = accounts_pick_col($accCols, 'password', 'passwordHash', 'password');
    $passwordHashCol = isset($accCols['passwordhash']) ? $accCols['passwordhash'] : null;

    $store = ensure_password_reset_storage($conn, $accCols);

    $row = null;
    if (($store['mode'] ?? '') === 'accounts') {
        $tokenCol = $store['tokenCol'];
        $expCol = $store['expCol'];
        $stmt = $conn->prepare("
            SELECT accountId, `{$emailCol}` AS emailAddress
            FROM accounts
            WHERE `{$tokenCol}` = ?
              AND `{$expCol}` IS NOT NULL
              AND `{$expCol}` > NOW()
              AND LOWER(COALESCE(`{$statusCol}`,'')) = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            SELECT t.accountId, a.`{$emailCol}` AS emailAddress
            FROM password_reset_tokens t
            JOIN accounts a ON a.accountId = t.accountId
            WHERE t.token = ?
              AND t.usedAt IS NULL
              AND t.expiresAt > NOW()
              AND LOWER(COALESCE(a.`{$statusCol}`,'')) = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
        $stmt->close();
    }

    if (!$row) {
        send_json_response(['success' => false, 'message' => 'The reset link is invalid or expired.'], 400);
    }

    $accountId = (int)$row['accountId'];
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    // password /  : password  passwordHash 
    $updates = [];
    $values = [];
    $types = '';
    if ($passwordCol) {
        $updates[] = "`{$passwordCol}` = ?";
        $values[] = $hashed;
        $types .= 's';
    }
    // passwordHash   passwordCol passwordHash  ,  
    if ($passwordHashCol && $passwordHashCol !== $passwordCol) {
        $updates[] = "`{$passwordHashCol}` = ?";
        $values[] = $hashed;
        $types .= 's';
    }

    if (($store['mode'] ?? '') === 'accounts') {
        $updates[] = "`{$store['tokenCol']}` = NULL";
        $updates[] = "`{$store['expCol']}` = NULL";
    }
    $updates[] = "updatedAt = NOW()";

    $values[] = $accountId;
    $types .= 'i';

    $uSql = "UPDATE accounts SET " . implode(', ', $updates) . " WHERE accountId = ?";
    $u = $conn->prepare($uSql);
    if (!$u) send_json_response(['success' => false, 'message' => 'Failed to update password.'], 500);
    mysqli_bind_params_by_ref($u, $types, $values);
    if (!$u->execute()) {
        $err = $u->error ?: $conn->error;
        $u->close();
        send_json_response(['success' => false, 'message' => 'Failed to update password.'], 500);
    }
    $u->close();

    // table mode token used 
    if (($store['mode'] ?? '') === 'table') {
        $m = $conn->prepare("UPDATE password_reset_tokens SET usedAt = NOW() WHERE token = ? AND usedAt IS NULL");
        if ($m) {
            $m->bind_param('s', $token);
            @$m->execute();
            @$m->close();
        }
    }

    log_activity($accountId, "password_reset_link_completed", "Password reset completed via link for {$row['emailAddress']}");

    send_json_response(['success' => true, 'message' => 'Password updated.']);
}

?>

