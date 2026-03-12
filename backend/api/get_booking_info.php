<?php
require_once 'conn.php';

//       API
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $bookingId = $_GET['bookingId'] ?? '';
    
    if (empty($bookingId)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }
    
    try {
        //    (     )
        $sql = "SELECT 
                    b.booking_id,
                    b.reservation_number,
                    b.total_amount,
                    b.departure_date,
                    b.departure_time,
                    b.return_date,
                    b.return_time,
                    b.duration,
                    b.adults,
                    b.children,
                    b.infants,
                    p.package_name,
                    p.package_price
                FROM bookings b
                LEFT JOIN packages p ON b.package_id = p.package_id
                WHERE b.booking_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            echo json_encode(['success' => true, 'booking' => $booking]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
