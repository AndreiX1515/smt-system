<?php
//  DB     API

//    (JSON  )
error_reporting(0);
ini_set('display_errors', 0);

//   
ob_start();

// CORS 
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET  
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET  .']);
    exit();
}

try {
    //    
    $conn_path = "../conn.php";
    if (!file_exists($conn_path)) {
        throw new Exception("conn.php    : " . $conn_path);
    }
    
    //  
    require $conn_path;
    
    $packageId = $_GET['id'] ?? '';
    
    if (empty($packageId)) {
        echo json_encode([
            'success' => false,
            'message' => ' ID .',
            'received_id' => $packageId
        ]);
        exit();
    }
    
    //   
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("  : " . ($conn->connect_error ?? '  '));
    }
    
    //  packages    
    $check_query = "SELECT COUNT(*) as count FROM packages";
    $check_result = $conn->query($check_query);
    $count_row = $check_result->fetch_assoc();
    
    if ($count_row['count'] == 0) {
        //       
        $sample_insert = "
        INSERT INTO packages (packageId, packageName, packagePrice, packageCategory, packageDescription, duration_days, meeting_location, meeting_time) VALUES 
        (1, '     5 6 ', 450000, 'season', '    , ·· ', 6, ' 2', '09:00'),
        (2, '   3 4', 280000, 'season', '   ', 4, '', '10:30'),
        (3, '  2 3', 180000, 'season', '   ', 3, '', '14:00')
        ON DUPLICATE KEY UPDATE packageName = VALUES(packageName)
        ";
        
        $conn->query($sample_insert);
    }
    
    //    
    $query = "
        SELECT 
            p.packageId,
            p.packageName,
            p.packagePrice,
            p.packageCategory,
            p.packageDescription,
            p.duration_days,
            p.meeting_location,
            p.meeting_time,
            f.flightId,
            f.origin,
            f.flightName,
            f.flightCode,
            f.flightDepartureDate,
            f.flightDepartureTime,
            f.flightArrivalDate,
            f.flightArrivalTime,
            f.returnFlightName,
            f.returnFlightCode,
            f.returnDepartureDate,
            f.returnDepartureTime,
            f.returnArrivalDate,
            f.returnArrivalTime,
            f.flightPrice,
            f.availSeats
        FROM packages p
        LEFT JOIN flight f ON p.packageId = f.packageId
        WHERE p.packageId = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => '    .',
            'packageId' => $packageId
        ]);
        exit();
    }
    
    //   
    $package_data = null;
    $flights = [];
    
    while ($row = $result->fetch_assoc()) {
        if (!$package_data) {
            $package_data = [
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName'],
                'packagePrice' => floatval($row['packagePrice']),
                'packageCategory' => $row['packageCategory'] ?? 'season',
                'packageDescription' => $row['packageDescription'] ?? '  ',
                'duration_days' => intval($row['duration_days'] ?? 3),
                'meeting_location' => $row['meeting_location'] ?? '',
                'meeting_time' => $row['meeting_time'] ?? '09:00',
                'packageImage' => '../images/package_' . $row['packageId'] . '.jpg',
                'highlights' => [
                    '  ',
                    '  ',
                    '  ',
                    '  '
                ],
                'includes' => [
                    ' ',
                    '  ',
                    ' ',
                    ' ',
                    ' '
                ],
                'excludes' => [
                    ' ',
                    ' ',
                    '',
                    ''
                ]
            ];
        }
        
        //    
        if ($row['flightId']) {
            $flights[] = [
                'flightId' => $row['flightId'],
                'origin' => $row['origin'],
                'flightName' => $row['flightName'],
                'flightCode' => $row['flightCode'],
                'departureDate' => $row['flightDepartureDate'],
                'departureTime' => $row['flightDepartureTime'],
                'arrivalDate' => $row['flightArrivalDate'],
                'arrivalTime' => $row['flightArrivalTime'],
                'returnFlightName' => $row['returnFlightName'],
                'returnFlightCode' => $row['returnFlightCode'],
                'returnDate' => $row['returnDepartureDate'],
                'returnTime' => $row['returnDepartureTime'],
                'returnArrivalDate' => $row['returnArrivalDate'],
                'returnArrivalTime' => $row['returnArrivalTime'],
                'flightPrice' => floatval($row['flightPrice'] ?? 0),
                'availSeats' => intval($row['availSeats'] ?? 30)
            ];
        }
    }
    
    $package_data['flights'] = $flights;
    
    echo json_encode([
        'success' => true,
        'message' => '   .',
        'data' => $package_data,
        'source' => 'real_database'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    //   
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB : ' . $e->getMessage(),
        'error_details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

//   
ob_end_flush();
?>