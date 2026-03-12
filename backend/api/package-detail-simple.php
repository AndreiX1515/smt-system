<?php
//    API

//    
ini_set('display_errors', 0);
error_reporting(0);

// JSON  
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// GET  
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '{"success": false, "message": "GET "}';
    exit;
}

try {
    $packageId = $_GET['id'] ?? '';
    
    if (empty($packageId)) {
        echo '{"success": false, "message": " ID "}';
        exit;
    }
    
    //   
    $conn = new mysqli("localhost", "root", "cloud1234", "smarttravel");
    
    if ($conn->connect_error) {
        echo '{"success": false, "message": "DB  "}';
        exit;
    }
    
    $conn->set_charset("utf8");
    
    //  
    $stmt = $conn->prepare("SELECT packageId, packageName, packagePrice, packageCategory, packageDescription FROM packages WHERE packageId = ?");
    
    if (!$stmt) {
        echo '{"success": false, "message": "  "}';
        exit;
    }
    
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '{"success": false, "message": " "}';
        exit;
    }
    
    $package = $result->fetch_assoc();
    
    //  
    $response = [
        'success' => true,
        'message' => ' ',
        'data' => [
            'packageId' => intval($package['packageId']),
            'packageName' => $package['packageName'] ?? '',
            'packagePrice' => floatval($package['packagePrice'] ?? 0),
            'packageCategory' => $package['packageCategory'] ?? 'season',
            'packageDescription' => $package['packageDescription'] ?? ' ',
            'duration_days' => 3,
            'meeting_location' => '',
            'meeting_time' => '09:00',
            'packageImage' => '../images/package_' . $packageId . '.jpg',
            'highlights' => ['  ', '  '],
            'includes' => [' ', '  ', ' '],
            'excludes' => [' ', ' '],
            'flights' => []
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo '{"success": false, "message": " "}';
}
?>