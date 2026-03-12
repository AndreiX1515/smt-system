<?php
require "../conn.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$bookingData = $input['bookingData'] ?? [];

if (empty($bookingData)) {
    echo json_encode(['success' => false, 'message' => 'Booking data is required'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Get package information
    $packageId = $bookingData['packageId'] ?? 0;
    $packageInfo = null;
    
    if ($packageId && $conn) {
        $stmt = $conn->prepare("SELECT * FROM packages WHERE packageId = ?");
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $packageInfo = $result->fetch_assoc();
            // package_schedules 기준으로 durationDays 보정
            $schStmt = $conn->prepare("SELECT MAX(day_number) AS maxDay FROM package_schedules WHERE package_id = ?");
            if ($schStmt) {
                $schStmt->bind_param('i', $packageId);
                $schStmt->execute();
                $schRow = $schStmt->get_result()->fetch_assoc();
                $schStmt->close();
                $maxDay = (int)($schRow['maxDay'] ?? 0);
                if ($maxDay > 0) {
                    $packageInfo['durationDays'] = $maxDay;
                    $packageInfo['duration_days'] = $maxDay;
                }
            }
        }
    }
    
    // Calculate pricing
    $basePrice = floatval($bookingData['basePrice'] ?? $packageInfo['packagePrice'] ?? 0);
    $adults = intval($bookingData['adults'] ?? 0);
    $children = intval($bookingData['children'] ?? 0);
    $infants = intval($bookingData['infants'] ?? 0);
    
    // Calculate guest pricing
    $guestPricing = [];
    if ($adults > 0) {
        $adultPrice = $basePrice * $adults;
        $guestPricing[] = [
            'type' => '',
            'count' => $adults,
            'unitPrice' => $basePrice,
            'totalPrice' => $adultPrice
        ];
    }
    
    if ($children > 0) {
        $childUnitPrice = max($basePrice - 5000, 0);
        $childPrice = $childUnitPrice * $children;
        $guestPricing[] = [
            'type' => '',
            'count' => $children,
            'unitPrice' => $childUnitPrice,
            'totalPrice' => $childPrice
        ];
    }

    if ($infants > 0) {
        $infantUnitPrice = 9000;
        $infantPrice = $infantUnitPrice * $infants;
        $guestPricing[] = [
            'type' => '',
            'count' => $infants,
            'unitPrice' => $infantUnitPrice,
            'totalPrice' => $infantPrice
        ];
    }
    
    // Calculate room pricing
    $roomPricing = [];
    $totalRoomPrice = 0;
    if (!empty($bookingData['selectedRooms'])) {
        foreach ($bookingData['selectedRooms'] as $room) {
            $roomPrice = floatval($room['price']) * intval($room['count']);
            $totalRoomPrice += $roomPrice;
            $roomPricing[] = [
                'name' => $room['name'],
                'count' => $room['count'],
                'unitPrice' => $room['price'],
                'totalPrice' => $roomPrice
            ];
        }
    }
    
    // Calculate options pricing
    $optionsPricing = [];
    $totalOptionsPrice = 0;
    if (!empty($bookingData['selectedOptions'])) {
        foreach ($bookingData['selectedOptions'] as $option) {
            $optionPrice = floatval($option['price']);
            $totalOptionsPrice += $optionPrice;
            $optionsPricing[] = [
                'name' => $option['name'],
                'price' => $optionPrice
            ];
        }
    }
    
    // Calculate totals
    $subtotal = array_sum(array_column($guestPricing, 'totalPrice')) + $totalRoomPrice + $totalOptionsPrice;
    $paymentFee = $subtotal * 0.03; // 3% payment fee
    $vat = $subtotal * 0.12; // 12% VAT
    $total = $subtotal + $paymentFee + $vat;
    
    $summary = [
        'packageInfo' => $packageInfo ? [
            'id' => $packageInfo['packageId'],
            'name' => $packageInfo['packageName'],
            'destination' => $packageInfo['destination'],
            'duration' => $packageInfo['durationDays'],
            'image' => $packageInfo['packageImageUrl'] ?? '../images/@img_card1.jpg'
        ] : null,
        'guestPricing' => $guestPricing,
        'roomPricing' => $roomPricing,
        'optionsPricing' => $optionsPricing,
        'travelerInfo' => $bookingData['travelers'] ?? [],
        'contactInfo' => $bookingData['contactInfo'] ?? [],
        'specialRequests' => [
            'seatRequest' => $bookingData['seatRequest'] ?? '',
            'otherRequest' => $bookingData['otherRequest'] ?? ''
        ]
    ];
    
    $pricing = [
        'base_price' => array_sum(array_column($guestPricing, 'totalPrice')),
        'room_price' => $totalRoomPrice,
        'options_price' => $totalOptionsPrice,
        'subtotal' => $subtotal,
        'payment_fee' => $paymentFee,
        'vat' => $vat,
        'total_price' => $total
    ];
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'pricing' => $pricing
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating booking summary: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

if ($conn) {
    $conn->close();
}
?>