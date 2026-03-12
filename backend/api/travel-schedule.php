<?php
require "../conn.php";

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetSchedule();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_schedule':
            handleGetSchedule();
            break;
        case 'get_schedule_detail':
            handleGetScheduleDetail($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

//   
function handleGetSchedule() {
    global $conn;
    
    $userId = $_GET['userId'] ?? $_POST['userId'] ?? '';
    $bookingId = $_GET['bookingId'] ?? $_POST['bookingId'] ?? '';
    
    if (empty($userId) && empty($bookingId)) {
        send_json_response(['success' => false, 'message' => ' ID   ID .'], 400);
    }
    
    try {
        //     
        $query = "
            SELECT 
                b.bookingId,
                b.createdAt as bookingDate,
                b.departureDate,
                DATE_ADD(b.departureDate, INTERVAL COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) DAY) as returnDate,
                b.totalAmount as totalPrice,
                b.bookingStatus,
                (b.adults + b.children + b.infants) as participants,
                p.packageName,
                p.packageDestination as destination,
                COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) as duration,
                p.packageImage as mainImage,
                c.fName,
                c.lName,
                c.contactNo
            FROM bookings b
            JOIN packages p ON b.packageId = p.packageId
            JOIN client c ON b.accountId = c.accountId
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($userId)) {
            $query .= " AND c.accountId = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        if (!empty($bookingId)) {
            $query .= " AND b.bookingId = ?";
            $params[] = $bookingId;
            $types .= 's';
        }
        
        $query .= " ORDER BY b.departureDate DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $schedules[] = [
                'bookingId' => $row['bookingId'],
                'bookingDate' => $row['bookingDate'],
                'departureDate' => $row['departureDate'],
                'returnDate' => $row['returnDate'],
                'totalPrice' => $row['totalPrice'],
                'bookingStatus' => $row['bookingStatus'],
                'participants' => $row['participants'],
                'packageName' => $row['packageName'],
                'destination' => $row['destination'],
                'duration' => $row['duration'],
                'mainImage' => $row['mainImage'],
                'clientName' => trim($row['fName'] . ' ' . $row['lName']),
                'contactNo' => $row['contactNo']
            ];
        }
        
        log_activity("Travel schedule retrieved for user: {$userId}");
        
        send_json_response([
            'success' => true,
            'data' => $schedules
        ]);
        
    } catch (Exception $e) {
        log_activity("Get schedule error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//    
function handleGetScheduleDetail($input) {
    global $conn;
    
    $bookingId = $input['bookingId'] ?? '';
    
    if (empty($bookingId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        //    
        $stmt = $conn->prepare("
            SELECT 
                b.*,
                p.packageName,
                p.packageDestination as destination,
                COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) as duration,
                p.packageDescription as description,
                p.packageImage as mainImage,
                p.packageIncludes as itinerary,
                c.fName,
                c.lName,
                c.contactNo,
                a.emailAddress
            FROM bookings b
            JOIN packages p ON b.packageId = p.packageId
            JOIN client c ON b.accountId = c.accountId
            JOIN accounts a ON b.accountId = a.accountId
            WHERE b.bookingId = ?
        ");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }
        
        $booking = $result->fetch_assoc();
        
        //    (booking_travelers  )
        $stmt = $conn->prepare("
            SELECT 
                bt.travelerId,
                bt.firstName,
                bt.lastName,
                bt.dateOfBirth,
                bt.passportNumber,
                bt.passportExpiryDate as passportExpiry,
                bt.nationality,
                bt.specialRequests as emergencyContact,
                bt.specialRequests as dietaryRestrictions
            FROM booking_travelers bt
            WHERE bt.bookingId = ?
        ");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $travelers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        //     (travel_schedules  )
        $scheduleDetails = [];
        try {
            $stmt = $conn->prepare("
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
                ORDER BY day_number
            ");
            $stmt->bind_param("s", $bookingId);
            $stmt->execute();
            $scheduleResult = $stmt->get_result();
            
            while ($row = $scheduleResult->fetch_assoc()) {
                $scheduleDetails[] = [
                    'dayNumber' => $row['day_number'],
                    'date' => $row['date'],
                    'activities' => json_decode($row['activities'], true) ?: [],
                    'accommodations' => json_decode($row['accommodations'], true) ?: [],
                    'meals' => json_decode($row['meals'], true) ?: [],
                    'transportations' => json_decode($row['transportations'], true) ?: [],
                    'notes' => $row['notes']
                ];
            }
        } catch (Exception $e) {
            // travel_schedules     
            $scheduleDetails = generateDefaultSchedule($booking);
        }
        
        //   
        $response = [
            'bookingId' => $booking['bookingId'],
            'bookingDate' => $booking['bookingDate'],
            'departureDate' => $booking['departureDate'],
            'returnDate' => $booking['returnDate'],
            'totalPrice' => $booking['totalPrice'],
            'bookingStatus' => $booking['bookingStatus'],
            'participants' => $booking['participants'],
            'packageInfo' => [
                'packageName' => $booking['packageName'],
                'destination' => $booking['destination'],
                'duration' => $booking['duration'],
                'description' => $booking['description'],
                'mainImage' => $booking['mainImage'],
                'itinerary' => $booking['itinerary']
            ],
            'clientInfo' => [
                'name' => trim($booking['fName'] . ' ' . $booking['lName']),
                'contactNo' => $booking['contactNo'],
                'emailAddress' => $booking['emailAddress']
            ],
            'travelers' => $travelers,
            'scheduleDetails' => $scheduleDetails
        ];
        
        log_activity("Travel schedule detail retrieved for booking: {$bookingId}");
        
        send_json_response([
            'success' => true,
            'data' => $response
        ]);
        
    } catch (Exception $e) {
        log_activity("Get schedule detail error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//    (travel_schedules   )
function generateDefaultSchedule($booking) {
    $departureDate = new DateTime($booking['departureDate']);
    $returnDate = new DateTime($booking['returnDate']);
    $duration = $booking['duration'] ?: $departureDate->diff($returnDate)->days + 1;
    
    $schedule = [];
    for ($day = 1; $day <= $duration; $day++) {
        $currentDate = clone $departureDate;
        $currentDate->add(new DateInterval('P' . ($day - 1) . 'D'));
        
        $schedule[] = [
            'dayNumber' => $day,
            'date' => $currentDate->format('Y-m-d'),
            'activities' => [
                '' => ' ',
                '' => ' ',
                '' => '  '
            ],
            'accommodations' => [
                '' => ' ',
                '' => '15:00',
                '' => '11:00'
            ],
            'meals' => [
                '' => ' ',
                '' => ' ',
                '' => ' '
            ],
            'transportations' => [
                '' => ' ',
                '' => '09:00',
                '' => '18:00'
            ],
            'notes' => $day === 1 ? ' -  ' : ($day === $duration ? ' -  ' : '')
        ];
    }
    
    return $schedule;
}
?>
