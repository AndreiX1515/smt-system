<?php
require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $bookingId = $_GET['bookingId'] ?? '';
    $accountId = $_GET['accountId'] ?? 1;
    
    if (empty($bookingId)) {
        send_json_response([
            'success' => false,
            'message' => ' ID .'
        ], 400);
    }
    
    //   
    $stmt = $conn->prepare("
        SELECT 
            b.bookingId,
            b.departureDate,
            b.adults,
            b.children,
            b.infants,
            b.totalAmount,
            b.bookingStatus,
            p.productName,
            p.productNameEn,
            p.duration,
            pi.imageUrl as thumbnail
        FROM bookings b
        LEFT JOIN products p ON b.packageId = p.productId
        LEFT JOIN product_images pi ON p.productId = pi.productId AND pi.isMain = 1
        WHERE b.bookingId = ? AND b.accountId = ?
    ");
    
    $stmt->bind_param("si", $bookingId, $accountId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        send_json_response([
            'success' => false,
            'message' => '   .'
        ], 404);
    }
    
    //   
    $stmt2 = $conn->prepare("
        SELECT 
            day_number,
            date,
            activities,
            accommodations,
            meals,
            transportations,
            notes
        FROM travel_schedules
        WHERE bookingId = ?
        ORDER BY day_number ASC
    ");
    
    $stmt2->bind_param("s", $bookingId);
    $stmt2->execute();
    $schedules = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    
    //    (   )
    $stmt3 = $conn->prepare("
        SELECT 
            g.guideId,
            g.guideName,
            g.profileImage,
            g.phone,
            g.email,
            g.languages,
            g.specialties,
            g.rating,
            g.total_reviews,
            g.bio,
            g.certifications,
            g.location
        FROM guides g
        WHERE g.isActive = 1
        LIMIT 1
    ");
    
    $stmt3->execute();
    $guide = $stmt3->get_result()->fetch_assoc();
    
    // JSON  
    if ($guide) {
        $guide['languages'] = json_decode($guide['languages'], true);
        $guide['specialties'] = json_decode($guide['specialties'], true);
        $guide['certifications'] = json_decode($guide['certifications'], true);
    }
    
    //  JSON  
    foreach ($schedules as &$schedule) {
        $schedule['activities'] = json_decode($schedule['activities'], true);
        $schedule['accommodations'] = json_decode($schedule['accommodations'], true);
        $schedule['meals'] = json_decode($schedule['meals'], true);
        $schedule['transportations'] = json_decode($schedule['transportations'], true);
    }
    
    send_json_response([
        'success' => true,
        'data' => [
            'booking' => $booking,
            'schedules' => $schedules,
            'guide' => $guide
        ]
    ]);
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '  : ' . $e->getMessage()
    ], 500);
}
?>



