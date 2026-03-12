<?php
// conn.php   (conn.php   )
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    //  
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    // admin/agent/guide/cs  
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    $guideAccountId = $_SESSION['guide_accountId'] ?? null;
    $csAccountId = $_SESSION['cs_accountId'] ?? null;
    $anyAccountId = $adminAccountId ?: ($agentAccountId ?: ($guideAccountId ?: $csAccountId));
    
    // user_sessions   
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;
    
    if ($tableExists && $anyAccountId) {
        //   
        $columnsCheck = $conn->query("SHOW COLUMNS FROM user_sessions");
        $accountIdColumn = 'accountid';
        while ($col = $columnsCheck->fetch_assoc()) {
            if (strtolower($col['Field']) === 'accountid') {
                $accountIdColumn = $col['Field'];
                break;
            }
        }
        
        //  ID  
        $sessionId = session_id();
        if ($sessionId) {
            $deleteStmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("s", $sessionId);
                $deleteStmt->execute();
                $deleteStmt->close();
            }
        }
        
        // accountId   (   )
        $deleteByAccountStmt = $conn->prepare("DELETE FROM user_sessions WHERE $accountIdColumn = ?");
        if ($deleteByAccountStmt) {
            $deleteByAccountStmt->bind_param("i", $anyAccountId);
            $deleteByAccountStmt->execute();
            $deleteByAccountStmt->close();
        }
    }
    
    //   
    $_SESSION = array();
    
    //   
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    //  
    session_destroy();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '.'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    //     
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    @session_destroy();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '    .',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    error_log("Logout fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    //     
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    @session_destroy();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '    .',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>

