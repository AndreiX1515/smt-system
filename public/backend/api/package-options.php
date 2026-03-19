<?php
/**
 *    API
 *     
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

//  backend/conn.php 
$conn_file = __DIR__ . '/../../../backend/conn.php';
if (!file_exists($conn_file)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $conn_file;

// GET  POST  
$packageId = $_GET['packageId'] ?? $_POST['packageId'] ?? $_GET['package_id'] ?? $_POST['package_id'] ?? null;

if (!$packageId) {
    echo json_encode([
        'success' => false,
        'message' => 'Package ID .'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    //    
    //       
    //  package_rooms, rooms,  package_room_options     
    
    // package_rooms  
    $tableCheck = $conn->query("SHOW TABLES LIKE 'package_rooms'");
    if ($tableCheck->num_rows > 0) {
        $sql = "SELECT roomId, roomType, roomPrice, capacity, description 
                FROM package_rooms 
                WHERE packageId = ? AND isAvailable = 1
                ORDER BY roomPrice ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $packageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $roomOptions = [];
            while ($row = $result->fetch_assoc()) {
                $roomOptions[] = [
                    'roomId' => $row['roomId'],
                    'roomType' => $row['roomType'] ?? '',
                    'roomPrice' => floatval($row['roomPrice'] ?? 0),
                    'capacity' => intval($row['capacity'] ?? 1),
                    'description' => $row['description'] ?? ''
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'roomOptions' => $roomOptions
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // rooms   (packageId )
    $tableCheck2 = $conn->query("SHOW TABLES LIKE 'rooms'");
    if ($tableCheck2->num_rows > 0) {
        // rooms  packageId   
        $columnCheck = $conn->query("SHOW COLUMNS FROM rooms LIKE 'packageId'");
        if ($columnCheck->num_rows > 0) {
            $sql = "SELECT roomId, roomType, price as roomPrice, capacity, description 
                    FROM rooms 
                    WHERE packageId = ? AND isAvailable = 1
                    ORDER BY price ASC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $packageId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $roomOptions = [];
                while ($row = $result->fetch_assoc()) {
                    $roomOptions[] = [
                        'roomId' => $row['roomId'],
                        'roomType' => $row['roomType'] ?? '',
                        'roomPrice' => floatval($row['roomPrice'] ?? 0),
                        'capacity' => intval($row['capacity'] ?? 1),
                        'description' => $row['description'] ?? ''
                    ];
                }
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'roomOptions' => $roomOptions
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
    
    //       
    echo json_encode([
        'success' => true,
        'data' => [
            'roomOptions' => []
        ],
        'message' => 'No room options table found'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Package room options API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '     : ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    error_log("Package room options API fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '      : ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

