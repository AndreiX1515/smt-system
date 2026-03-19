<?php
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    //    
    $sql = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
    $result = $conn->query($sql);
    
    $branches = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $branches[] = [
                'branchId' => $row['branchId'],
                'branchName' => $row['branchName']
            ];
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'branches' => $branches
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Get branches error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '     .',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>

