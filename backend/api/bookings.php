<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS  
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

//   
require_once '../config/database.php';

//   
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

//  
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$mysqli->set_charset("utf8mb4");

switch ($action) {
    case 'get_user_bookings':
        getUserBookings($mysqli, $data);
        break;
    
    case 'get_booking_detail':
        getBookingDetail($mysqli, $data);
        break;
    
    case 'create_booking':
        createBooking($mysqli, $data);
        break;
    
    case 'update_booking_status':
        updateBookingStatus($mysqli, $data);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

$mysqli->close();

//    
function getUserBookings($mysqli, $data) {
    $userId = $mysqli->real_escape_string($data['user_id'] ?? '');
    
    if (empty($userId)) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        return;
    }
    
    //    
    $query = "
        SELECT 
            b.booking_id AS bookingId,
            b.package_id AS packageId,
            p.package_name AS packageName,
            b.departure_date AS departureDate,
            b.return_date AS returnDate,
            b.booking_status AS bookingStatus,
            b.total_amount AS totalAmount,
            b.payment_status AS paymentStatus,
            b.created_at AS createdAt,
            p.duration_days AS durationDays,
            p.meeting_location AS meetingLocation,
            p.meeting_time AS meetingTime
        FROM bookings b
        JOIN packages p ON b.package_id = p.package_id
        WHERE b.user_id = ?
        ORDER BY b.departure_date ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $bookings = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $bookings
    ]);
    
    $stmt->close();
}

//    
function getBookingDetail($mysqli, $data) {
    $bookingId = $mysqli->real_escape_string($data['booking_id'] ?? '');
    
    if (empty($bookingId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking ID is required'
        ]);
        return;
    }
    
    //    
    $query = "
        SELECT 
            b.*,
            p.*,
            u.username,
            u.email,
            u.phone
        FROM bookings b
        JOIN packages p ON b.package_id = p.package_id
        JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    
    if ($booking) {
        echo json_encode([
            'success' => true,
            'data' => $booking
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found'
        ]);
    }
    
    $stmt->close();
}

//  
function createBooking($mysqli, $data) {
    $userId = $mysqli->real_escape_string($data['user_id'] ?? '');
    $packageId = $mysqli->real_escape_string($data['package_id'] ?? '');
    $departureDate = $mysqli->real_escape_string($data['departure_date'] ?? '');
    $returnDate = $mysqli->real_escape_string($data['return_date'] ?? '');
    $totalAmount = $mysqli->real_escape_string($data['total_amount'] ?? '');
    $numberOfPeople = $mysqli->real_escape_string($data['number_of_people'] ?? 1);
    
    if (empty($userId) || empty($packageId) || empty($departureDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'Required fields are missing'
        ]);
        return;
    }

    // Capacity 검증 (레거시 경로)
    if (function_exists('check_capacity')) {
        $cap = check_capacity($mysqli, (int)$packageId, $departureDate, (int)$numberOfPeople);
        if (!$cap['ok']) {
            echo json_encode(['success' => false, 'message' => $cap['message']]);
            return;
        }
    }

    //
    $query = "
        INSERT INTO bookings (
            user_id, package_id, departure_date, return_date,
            total_amount, number_of_people, booking_status,
            payment_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("iissdi", 
        $userId, $packageId, $departureDate, 
        $returnDate, $totalAmount, $numberOfPeople
    );
    
    if ($stmt->execute()) {
        $bookingId = $mysqli->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $bookingId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create booking'
        ]);
    }
    
    $stmt->close();
}

//   
function updateBookingStatus($mysqli, $data) {
    $bookingId = $mysqli->real_escape_string($data['booking_id'] ?? '');
    $status = $mysqli->real_escape_string($data['status'] ?? '');
    
    if (empty($bookingId) || empty($status)) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking ID and status are required'
        ]);
        return;
    }
    
    //   
    $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status value'
        ]);
        return;
    }
    
    //  
    $query = "UPDATE bookings SET booking_status = ? WHERE booking_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("si", $status, $bookingId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Booking status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update booking status'
        ]);
    }
    
    $stmt->close();
}
?>