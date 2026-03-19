<?php
//    
header('Content-Type: application/json; charset=utf-8');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$result = [
    'success' => false,
    'messages' => []
];

try {
    // 1.   
    $connPath = __DIR__ . '/../../../backend/conn.php';
    $result['messages'][] = "Checking path: $connPath";
    $result['messages'][] = "File exists: " . (file_exists($connPath) ? 'yes' : 'no');
    $result['messages'][] = "__DIR__: " . __DIR__;
    
    if (!file_exists($connPath)) {
        $result['error'] = 'conn.php not found';
        ob_end_clean();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 2. conn.php 
    require $connPath;
    $result['messages'][] = "conn.php loaded";
    
    // 3.  
    if (!isset($conn)) {
        $result['error'] = '$conn variable not set';
        ob_end_clean();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['messages'][] = "Connection object exists";
    
    if ($conn->connect_error) {
        $result['error'] = $conn->connect_error;
        ob_end_clean();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 4.   
    $testQuery = $conn->query("SELECT 1 as test");
    if ($testQuery) {
        $result['messages'][] = "Query test successful";
        $result['success'] = true;
    } else {
        $result['error'] = $conn->error;
    }
    
    // 5.  
    $result['session_status'] = session_status();
    $result['session_started'] = session_status() !== PHP_SESSION_NONE;
    
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
    $result['file'] = $e->getFile();
    $result['line'] = $e->getLine();
    $result['trace'] = $e->getTraceAsString();
}

ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

