<?php
require "../conn.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Database connection check
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get active payment methods
    $query = "SELECT methodId, methodCode, methodName_ko as name, methodName_en as nameEn, 
                     processingFee as fee, feeType, isActive, displayOrder
              FROM payment_methods 
              WHERE isActive = 1 
              ORDER BY displayOrder ASC, methodId ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Failed to fetch payment methods');
    }
    
    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = [
            'id' => intval($row['methodId']),
            'code' => $row['methodCode'],
            'name' => $row['name'],
            'nameEn' => $row['nameEn'],
            'fee' => floatval($row['fee']),
            'feeType' => $row['feeType'],
            'isActive' => boolval($row['isActive'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'methods' => $methods
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Fallback to default payment methods
    $defaultMethods = [
        [
            'id' => 1,
            'code' => 'gcash',
            'name' => '',
            'nameEn' => 'GCash',
            'fee' => 1.5,
            'feeType' => 'percentage',
            'isActive' => true
        ],
        [
            'id' => 2,
            'code' => 'credit_card',
            'name' => '/',
            'nameEn' => 'Credit/Debit Card',
            'fee' => 2.5,
            'feeType' => 'percentage',
            'isActive' => true
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'methods' => $defaultMethods,
        'mode' => 'fallback'
    ], JSON_UNESCAPED_UNICODE);
}

if ($conn) {
    $conn->close();
}
?>