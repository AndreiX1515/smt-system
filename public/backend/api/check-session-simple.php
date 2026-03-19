<?php
//   -  
header('Content-Type: application/json; charset=utf-8');

$result = ['success' => false, 'message' => 'Unknown error'];

try {
    // 1.  
    $connPath = __DIR__ . '/../../../backend/conn.php';
    $realPath = realpath($connPath);
    
    if (!$realPath && !file_exists($connPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'conn.php not found',
            'path' => $connPath,
            '__DIR__' => __DIR__
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 2. conn.php 
    require $realPath ?: $connPath;
    
    // 3.  
    if (!isset($conn)) {
        echo json_encode([
            'success' => false,
            'message' => '$conn not set'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($conn->connect_error) {
        echo json_encode([
            'success' => false,
            'message' => 'DB connection error',
            'error' => $conn->connect_error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 4.  
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_accountId'])) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'userType' => $_SESSION['admin_userType'] ?? 'admin'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ], JSON_UNESCAPED_UNICODE);
}
?>

