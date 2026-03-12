<?php
require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    //    + ()  
    $sql = "SELECT 
                co.companyId,
                co.companyName,
                co.branchId,
                b.branchName
            FROM company co
            LEFT JOIN branch b ON co.branchId = b.branchId
            ORDER BY co.companyName ASC";

    $result = $conn->query($sql);

    $companies = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $companies[] = [
                'companyId' => $row['companyId'],
                'companyName' => $row['companyName'] ?? '',
                'branchId' => $row['branchId'] ?? null,
                'branchName' => $row['branchName'] ?? ''
            ];
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'companies' => $companies
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Get companies error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '     .',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>


