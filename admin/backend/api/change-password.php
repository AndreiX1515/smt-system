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
    echo json_encode(['success' => false, 'message' => 'Only POST is allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// : admin/agent/guide/cs  
$accountId = null;
if (isset($_SESSION['admin_accountId'])) $accountId = intval($_SESSION['admin_accountId']);
elseif (isset($_SESSION['agent_accountId'])) $accountId = intval($_SESSION['agent_accountId']);
elseif (isset($_SESSION['guide_accountId'])) $accountId = intval($_SESSION['guide_accountId']);
elseif (isset($_SESSION['cs_accountId'])) $accountId = intval($_SESSION['cs_accountId']);

if (empty($accountId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$currentPassword = (string)($input['currentPassword'] ?? '');
$newPassword = (string)($input['newPassword'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current/new password is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $updated = false;

    // accounts   : password/passwordHash
    $accCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM accounts");
    while ($colRes && ($c = $colRes->fetch_assoc())) {
        $accCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }
    $passwordCol = $accCols['password'] ?? ($accCols['passwordhash'] ?? 'password');
    $passwordHashCol = $accCols['passwordhash'] ?? null;

    // mysqlnd    get_result()  bind_result/fetch 
    $stmt = $conn->prepare("SELECT `{$passwordCol}` FROM accounts WHERE accountId = ? LIMIT 1");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param('i', $accountId);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception("Execute failed: " . $err);
    }
    $stmt->bind_result($storedRaw);
    $hasRow = $stmt->fetch();
    $stmt->close();
    if (!$hasRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stored = (string)($storedRaw ?? '');
    $ok = false;
    if ($stored !== '') {
        if (preg_match('/^\$2y\$/', $stored) || preg_match('/^\$2a\$/', $stored) || preg_match('/^\$argon2id\$/', $stored)) {
            $ok = password_verify($currentPassword, $stored);
        }
        if (!$ok && hash_equals($stored, $currentPassword)) {
            $ok = true;
        }
    }

    if (!$ok) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    // password /  : password  passwordHash 
    // - admin/backend  mysqli_bind_params_by_ref    ,   bind_param 
    $set = [];
    if ($passwordCol) $set[] = "`{$passwordCol}` = ?";
    if ($passwordHashCol && $passwordHashCol !== $passwordCol) $set[] = "`{$passwordHashCol}` = ?";

    if (empty($set)) {
        throw new Exception("No password column found in accounts");
    }

    $upd = $conn->prepare("UPDATE accounts SET " . implode(', ', $set) . " WHERE accountId = ?");
    if (!$upd) throw new Exception("Prepare failed: " . $conn->error);

    if (count($set) === 2) {
        $upd->bind_param('ssi', $hashed, $hashed, $accountId);
    } else {
        $upd->bind_param('si', $hashed, $accountId);
    }
    if (!$upd->execute()) {
        $err = $upd->error ?: $conn->error;
        $upd->close();
        throw new Exception("Update failed: " . $err);
    }
    $upd->close();
    $updated = true;

    //      ( )
    // -  enum('yes','no') / enum('Y','N') / tinyint     
    // -     ""     .
    if (isset($accCols['defaultpasswordstat'])) {
        try {
            $colName = $accCols['defaultpasswordstat'];
            $colInfo = null;
            $rInfo = $conn->query("SHOW COLUMNS FROM accounts LIKE '" . $conn->real_escape_string($colName) . "'");
            if ($rInfo && $rInfo->num_rows > 0) $colInfo = $rInfo->fetch_assoc();
            $type = strtolower((string)($colInfo['Type'] ?? ''));

            $value = 'N';
            if (strpos($type, 'tinyint') !== false || preg_match('/\bint\b/', $type)) {
                $value = '0';
            } elseif (strpos($type, 'enum') !== false) {
                // enum('yes','no') 
                if (strpos($type, "'yes'") !== false && strpos($type, "'no'") !== false) {
                    $value = 'no';
                }
                // enum('y','n')  enum('Y','N') 
                elseif ((strpos($type, "'y'") !== false && strpos($type, "'n'") !== false) || (strpos($type, "'y'") !== false && strpos($type, "'n'") !== false)) {
                    $value = 'N';
                } elseif (strpos($type, "'n'") !== false) {
                    $value = 'N';
                }
            }

            if ($value === '0') {
                $st = $conn->prepare("UPDATE accounts SET `{$colName}` = 0 WHERE accountId = ?");
                if ($st) {
                    $st->bind_param('i', $accountId);
                    $st->execute();
                    $st->close();
                }
            } else {
                $st = $conn->prepare("UPDATE accounts SET `{$colName}` = ? WHERE accountId = ?");
                if ($st) {
                    $st->bind_param('si', $value, $accountId);
                    $st->execute();
                    $st->close();
                }
            }
        } catch (Throwable $e2) {
            // ignore (     )
        }
    }

    echo json_encode(['success' => true, 'message' => 'Password changed successfully'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    //        (UX  )
    // - : defaultPasswordStat /          
    if (isset($updated) && $updated) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully',
            'warning' => 'Some additional settings may not have been updated',
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while changing password', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}


