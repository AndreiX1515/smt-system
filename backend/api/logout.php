<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Remember Token 쿠키 삭제
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

$accountId = null;
if (isset($_SESSION['user_id'])) $accountId = (int)$_SESSION['user_id'];
if (!$accountId && isset($_SESSION['accountId'])) $accountId = (int)$_SESSION['accountId'];

// DB 세션 및 Remember Token 삭제
try {
    if ($accountId) {
        // user_sessions 삭제
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE accountid = ?");
        if ($stmt) {
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $stmt->close();
        }

        // remember_tokens 삭제
        $tokenStmt = $conn->prepare("DELETE FROM remember_tokens WHERE accountId = ?");
        if ($tokenStmt) {
            $tokenStmt->bind_param("i", $accountId);
            $tokenStmt->execute();
            $tokenStmt->close();
        }
    }
} catch (Throwable $e) {
    // ignore
}

// PHP   
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

send_json_response([
    'success' => true,
    'message' => 'Logged out.'
]);

?>

