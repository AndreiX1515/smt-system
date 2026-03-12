<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

try {
    $accountId = $_GET['accountId'] ?? 1; //  1
    
    //    
    $stmt = $conn->prepare("
        SELECT 
            a.accountId,
            a.username,
            a.emailAddress,
            a.accountType,
            a.preferredLanguage,
            p.firstName,
            p.lastName,
            p.phone,
            p.dateOfBirth,
            p.nationality,
            p.profileImage,
            p.emergencyContact,
            p.emergencyPhone
        FROM accounts a
        LEFT JOIN user_profiles p ON a.accountId = p.accountId
        WHERE a.accountId = ?
    ");
    
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response([
            'success' => false,
            'message' => '   .'
        ], 404);
    }
    
    $profile = $result->fetch_assoc();
    
    //    
    $stmt2 = $conn->prepare("
        SELECT 
            b.bookingId,
            b.departureDate,
            b.adults,
            b.children,
            b.infants,
            b.totalAmount,
            b.bookingStatus,
            b.paymentStatus,
            p.packageName as productName,
            p.packageName as productNameEn,
            p.packageImage as thumbnail
        FROM bookings b
        LEFT JOIN packages p ON b.packageId = p.packageId
        WHERE b.accountId = ? 
        ORDER BY b.departureDate DESC
        LIMIT 3
    ");
    
    $stmt2->bind_param("i", $accountId);
    $stmt2->execute();
    $recentBookings = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    
    //     
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) as unreadCount
        FROM notifications
        WHERE accountId = ? AND isRead = 0
    ");
    
    $stmt3->bind_param("i", $accountId);
    $stmt3->execute();
    $unreadCount = $stmt3->get_result()->fetch_assoc()['unreadCount'];
    
    send_json_response([
        'success' => true,
        'data' => [
            'profile' => $profile,
            'recentBookings' => $recentBookings,
            'unreadNotifications' => $unreadCount
        ]
    ]);
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '  : ' . $e->getMessage()
    ], 500);
}
?>



