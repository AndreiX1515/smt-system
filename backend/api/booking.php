<?php
//   
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// JSON    
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS  
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

//  
require "../conn.php";

//   
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

//   
function sendSuccessResponse($data) {
    //   booking  
    echo json_encode([
        'success' => true,
        'data' => $data,
        'booking' => $data, //   
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    //     
    $bookingId = '';
    $action = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $bookingId = $_GET['bookingId'] ?? '';
        $action = 'get_booking';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendErrorResponse('Invalid JSON: ' . json_last_error_msg());
        }
        
        $action = $input['action'] ?? '';
        $bookingId = $input['bookingId'] ?? '';
    } else {
        sendErrorResponse('Method not allowed', 405);
    }

    // bookingId  
    if (empty($bookingId)) {
        sendErrorResponse('   .', 404);
    }
    
    //   (POST  )
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'get_booking') {
        sendErrorResponse('Invalid action: ' . $action);
    }

    //   
    if (!$conn) {
        sendErrorResponse('Database connection failed', 500);
    }

    //   
    $bookingData = getBookingData($bookingId);
    
    if (!$bookingData) {
        sendErrorResponse('   .', 404);
    }

    sendSuccessResponse($bookingData);
        
    } catch (Exception $e) {
    error_log("Booking API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
}

// Get booking data for response
function getBookingData($bookingId) {
    global $conn;
    
    try {
        $query = "
            SELECT 
                b.*,
                COALESCE(NULLIF(p.packageName,''), NULLIF(b.packageName,''), CONCAT('Deleted product #', b.packageId)) AS packageName,
                p.packageDestination,
                p.packageImage,
                COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1) as duration_days,
                p.packagePrice,
                p.childPrice,
                p.infantPrice,
                c.fName,
                c.lName,
                c.contactNo,
                c.emailAddress
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN client c ON b.accountId = c.accountId
            WHERE b.bookingId = ?
        ";
        
        // seatRequest otherRequest      
        $checkSeatRequest = $conn->query("SHOW COLUMNS FROM bookings LIKE 'seatRequest'");
        $checkOtherRequest = $conn->query("SHOW COLUMNS FROM bookings LIKE 'otherRequest'");
        
        if ($checkSeatRequest->num_rows > 0 || $checkOtherRequest->num_rows > 0) {
            //   b.*    
            //     
            error_log("Booking API: seatRequest  : " . ($checkSeatRequest->num_rows > 0 ? 'YES' : 'NO'));
            error_log("Booking API: otherRequest  : " . ($checkOtherRequest->num_rows > 0 ? 'YES' : 'NO'));
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($booking = $result->fetch_assoc()) {
            return $booking;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Get booking data error: " . $e->getMessage());
        return null;
    }
}
?>