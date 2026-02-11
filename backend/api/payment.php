<?php
//   
error_reporting(E_ALL);
ini_set('display_errors', 0); //    
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// JSON    
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS  
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

//  
require "../conn.php";
require_once __DIR__ . '/../lib/booking-utils.php';

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
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

//  ( /   )
function createNotificationSafe($accountId, $title, $message, $type, $category, $priority, $actionUrl, $data = []) {
    global $conn;
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$tableCheck || $tableCheck->num_rows === 0) return;

        //    
        $cols = [];
        $r = $conn->query("SHOW COLUMNS FROM notifications");
        while ($r && ($row = $r->fetch_assoc())) {
            $cols[strtolower($row['Field'])] = $row['Field'];
        }

        $hasType = isset($cols['type']);
        $hasNotificationType = isset($cols['notificationtype']);
        $hasCategory = isset($cols['category']);
        $hasData = isset($cols['data']);
        $hasRelatedId = isset($cols['relatedid']);

        $fields = ["accountId", "title", "message"];
        $placeholders = ["?", "?", "?"];
        $values = [(int)$accountId, (string)$title, (string)$message];
        $typesStr = "iss";

        if ($hasType) {
            $fields[] = $cols['type'];
            $placeholders[] = "?";
            $values[] = (string)$type;
            $typesStr .= "s";
        } elseif ($hasNotificationType) {
            $fields[] = $cols['notificationtype'];
            $placeholders[] = "?";
            $values[] = (string)$type;
            $typesStr .= "s";
        }

        if ($hasCategory) {
            $fields[] = $cols['category'];
            $placeholders[] = "?";
            $values[] = (string)$category;
            $typesStr .= "s";
        }

        $fields[] = "priority";
        $placeholders[] = "?";
        $values[] = (string)$priority;
        $typesStr .= "s";

        $fields[] = "isRead";
        $placeholders[] = "0";

        $fields[] = "actionUrl";
        $placeholders[] = "?";
        $values[] = (string)$actionUrl;
        $typesStr .= "s";

        if ($hasData) {
            $fields[] = $cols['data'];
            $placeholders[] = "?";
            $values[] = json_encode($data, JSON_UNESCAPED_UNICODE);
            $typesStr .= "s";
        }
        if ($hasRelatedId) {
            $fields[] = $cols['relatedid'];
            $placeholders[] = "?";
            $values[] = (string)($data['bookingId'] ?? ($data['relatedId'] ?? ''));
            $typesStr .= "s";
        }

        $fields[] = "createdAt";
        $placeholders[] = "NOW()";

        $sql = "INSERT INTO notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return;

        // PHP 8+ bind_param 
        $bind = [];
        $bind[] = $typesStr;
        foreach ($values as $i => $_) $bind[] = &$values[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
        @$stmt->execute();
        @$stmt->close();
    } catch (Throwable $e) {
        // ignore
    }
}

try {
    //   
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Method not allowed', 405);
    }

    //   
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        sendErrorResponse('Empty request body');
    }

    //   
    error_log("Payment API Request - Raw input: " . $rawInput);

    // JSON 
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        sendErrorResponse('Invalid JSON: ' . json_last_error_msg());
    }

    //  
    $action = $input['action'] ?? '';
    if (empty($action)) {
        sendErrorResponse('Action is required');
    }

    //   
    if (!$conn) {
        sendErrorResponse('Database connection failed', 500);
    }

    error_log("Payment API - Processing action: " . $action);

    //  
    switch ($action) {
        case 'process':
            handlePaymentProcessing($input);
            break;
        case 'verify':
            handlePaymentVerification($input);
            break;
        default:
            sendErrorResponse('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    error_log("Payment API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
}

function handlePaymentProcessing($input) {
    global $conn;
    
    try {
        error_log("Payment processing started");
        error_log("Input data: " . json_encode($input, JSON_PRETTY_PRINT));
        
        $bookingData = $input['bookingData'] ?? [];
        $paymentMethod = $input['paymentMethod'] ?? '';
        $userId = $input['userId'] ?? 0;
        
        error_log("Parsed data - bookingData keys: " . implode(', ', array_keys($bookingData)));
        error_log("Payment method: " . $paymentMethod);
        error_log("User ID: " . $userId);
        
        if (empty($bookingData) || empty($paymentMethod) || !$userId) {
            throw new Exception('Required data missing - bookingData: ' . (empty($bookingData) ? 'empty' : 'ok') . 
                              ', paymentMethod: ' . ($paymentMethod ?: 'empty') . 
                              ', userId: ' . ($userId ?: 'empty'));
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        //    (payments.paymentMethod enum )
        $allowedMethods = ['card', 'bank_transfer', 'paypal', 'gcash', 'paymaya'];
        $paymentMethodEnum = 'bank_transfer';
        if (is_string($paymentMethod)) {
            $pm = trim($paymentMethod);
            if (in_array($pm, $allowedMethods, true)) {
                $paymentMethodEnum = $pm;
            } elseif (strpos($pm, '') !== false) {
                $paymentMethodEnum = 'gcash';
            } elseif (strpos($pm, '') !== false || strpos($pm, '') !== false || stripos($pm, 'bank') !== false) {
                $paymentMethodEnum = 'bank_transfer';
            } elseif (strpos($pm, '') !== false || strpos($pm, '') !== false || stripos($pm, 'card') !== false) {
                $paymentMethodEnum = 'card';
            } elseif (stripos($pm, 'paypal') !== false) {
                $paymentMethodEnum = 'paypal';
            }
        }

        // SMT   -      (Temporary save -> Payment Suspended/Completed)
        //   :
        // - : bookings.pending (  )
        // - (): bookings.confirmed/pending + payments.pending (  ,  )
        // - (/): bookings.confirmed/paid + payments.completed
        //    bookingStatus 'confirmed'   
        $bookingStatus = 'confirmed';  //   =  
        $bookingPaymentStatus = ($paymentMethodEnum === 'bank_transfer') ? 'pending' : 'paid';
        $paymentRowStatus = ($paymentMethodEnum === 'bank_transfer') ? 'pending' : 'completed';
        // SMT  

        // Capacity 검증 — 결제 직전 서버사이드 재고 확인
        $paxAdults = (int)($bookingData['adults'] ?? 0);
        $paxChildren = (int)($bookingData['children'] ?? 0);
        $totalPax = $paxAdults + $paxChildren;
        $capPackageId = (int)($bookingData['packageId'] ?? 0);
        $capDate = normalize_date_ymd((string)($bookingData['departureDate'] ?? ''));
        $capExclude = trim((string)($bookingData['bookingId'] ?? ($bookingData['tempId'] ?? ($bookingData['tempBookingId'] ?? ''))));
        if ($totalPax > 0 && $capPackageId > 0 && $capDate !== '') {
            $cap = check_capacity($conn, $capPackageId, $capDate, $totalPax, $capExclude !== '' ? $capExclude : null);
            if (!$cap['ok']) {
                throw new Exception($cap['message']);
            }
        }

        // Create/update booking record
        // - (save-temp-booking.php)  pending    bookingId  UPDATE
        //   pending   (B2C)  2   .
        $bookingId = createBooking($bookingData, $userId, $bookingStatus, $bookingPaymentStatus);
        
        // Process payment
        $paymentId = processPayment($bookingId, $bookingData, $paymentMethodEnum, $paymentRowStatus, (int)$userId);
        
        // Create booking travelers (   )
        createBookingTravelers($bookingId, $bookingData['travelers'] ?? []);
        
        // Update package availability if needed
        updatePackageAvailability($bookingData['packageId'], $bookingData['selectedFlight'] ?? null);

        // /  , (++) pending     (  )
        try {
            $pkgId = (int)($bookingData['packageId'] ?? 0);
            $dep = normalize_date_ymd((string)($bookingData['departureDate'] ?? ''));
            if ($pkgId > 0 && $dep !== '') {
                cancel_other_pending_drafts((int)$userId, $pkgId, $dep, (string)$bookingId);
            }
        } catch (Throwable $e) {
            // ignore
        }
        
        // Send confirmation email
        sendBookingConfirmation($userId, $bookingId);
        
        // ( /  )
        try {
            $pkgId = (int)($bookingData['packageId'] ?? 0);
            $pkgName = (string)($bookingData['packageName'] ?? '');
            $dep = (string)($bookingData['departureDate'] ?? '');
            //   " "   ,
            //  statusKey/clientType/bookingId    .
            $title = ($paymentMethodEnum === 'bank_transfer') ? 'Waiting for Deposit' : 'Payment Completed';
            $msg = ($paymentMethodEnum === 'bank_transfer')
                ? "Your reservation has been created and is waiting for deposit."
                : "The product's reservation status has been changed.";
            $statusKey = ($paymentMethodEnum === 'bank_transfer') ? 'pending' : 'paid';
            createNotificationSafe(
                (int)$userId,
                $title,
                $msg,
                'payment',
                'reservation_schedule',
                'high',
                "reservation-detail.php?id=" . rawurlencode((string)$bookingId),
                [
                    'statusKey' => $statusKey,
                    'clientType' => 'b2c',
                    'bookingId' => (string)$bookingId,
                    'packageId' => $pkgId,
                    'packageName' => $pkgName,
                    'departureDate' => $dep,
                ]
            );
        } catch (Throwable $e) {
            // ignore
        }

        $conn->commit();
        
        // Get booking data for response
        $bookingData = getBookingData($bookingId);
        
        // Response message/status for UI:
        // - bank_transfer: Payment Suspended (waiting for deposit)
        // - others: Payment Completed
        $uiStatusKey = ($paymentMethodEnum === 'bank_transfer') ? 'payment_suspended' : 'payment_completed';
        $uiMessage = ($paymentMethodEnum === 'bank_transfer')
            ? 'Payment Suspended'
            : 'Payment Completed';

        sendSuccessResponse([
            'bookingId' => $bookingId,
            'paymentId' => $paymentId,
            'statusKey' => $uiStatusKey,
            'bookingStatus' => $bookingStatus,
            'paymentStatus' => $bookingPaymentStatus,
            'paymentRowStatus' => $paymentRowStatus,
            'paymentMethod' => $paymentMethodEnum,
            'message' => $uiMessage,
            'bookingData' => $bookingData
        ]);
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        
        error_log("Payment processing error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendErrorResponse('Payment processing failed: ' . $e->getMessage(), 500);
    }
}

function createBooking($bookingData, $userId, $bookingStatus = 'pending', $bookingPaymentStatus = 'pending') {
    global $conn;
    
    $packageId = $bookingData['packageId'] ?? 0;
    $adults = $bookingData['adults'] ?? 0;
    $children = $bookingData['children'] ?? 0;
    $infants = $bookingData['infants'] ?? 0;
    $totalAmount = $bookingData['finalPricing']['total_price'] ?? 0;
    $departureDate = normalize_date_ymd((string)($bookingData['departureDate'] ?? ''));
    if ($departureDate === '') {
        throw new Exception('departureDate is required');
    }
    $departureTime = $bookingData['departureTime'] ?? '';
    if ($departureTime === '') {
        // 패키지에서 출발 시간 조회 시도
        $timeStmt = $conn->prepare("SELECT COALESCE(meeting_time, meetingTime) as mt FROM packages WHERE packageId = ? LIMIT 1");
        if ($timeStmt) {
            $timeStmt->bind_param('i', $packageId);
            $timeStmt->execute();
            $timeRow = $timeStmt->get_result()->fetch_assoc();
            $departureTime = $timeRow['mt'] ?? '';
            $timeStmt->close();
        }
    }
    $packageName = $bookingData['packageName'] ?? '';
    $packagePrice = $bookingData['packagePrice'] ?? 0;
    // bookings.bookingStatus enum: pending/confirmed/cancelled/completed
    $bookingStatus = in_array($bookingStatus, ['pending','confirmed','cancelled','completed'], true) ? $bookingStatus : 'pending';
    // bookings.paymentStatus enum: pending/paid/failed/refunded
    $bookingPaymentStatus = in_array($bookingPaymentStatus, ['pending','paid','failed','refunded'], true) ? $bookingPaymentStatus : 'pending';
    
    // (pending) bookingId   row UPDATE  
    $requestedBookingId = '';
    if (is_array($bookingData)) {
        $requestedBookingId = trim((string)($bookingData['bookingId'] ?? ($bookingData['tempId'] ?? ($bookingData['tempBookingId'] ?? ''))));
    }
    $bookingId = '';
    if ($requestedBookingId !== '') {
        $chk = $conn->prepare("SELECT bookingId FROM bookings WHERE bookingId = ? AND accountId = ? LIMIT 1");
        if ($chk) {
            $uid = (int)$userId;
            $chk->bind_param('si', $requestedBookingId, $uid);
            $chk->execute();
            $res = $chk->get_result();
            if ($res && $res->num_rows > 0) {
                $bookingId = $requestedBookingId;
            }
            $chk->close();
        }
    }
    if ($bookingId === '') {
        //     bookingId 
        $bookingId = generateBookingId();
    }
    
    //   
    $customerInfo = $bookingData['customerInfo'] ?? [];
    $contactEmail = $customerInfo['email'] ?? '';
    $contactPhone = $customerInfo['phone'] ?? '';
    
    //   
    $specialRequests = '';
    if (!empty($bookingData['seatRequest'])) {
        $specialRequests .= '  : ' . $bookingData['seatRequest'] . "\n";
    }
    if (!empty($bookingData['otherRequest'])) {
        $specialRequests .= ' : ' . $bookingData['otherRequest'];
    }
    
    // selectedOptions JSON
    // IMPORTANT: Do NOT overwrite existing bookings.selectedOptions with an empty string.
    // If client didn't send selectedOptions, keep NULL so COALESCE(?, selectedOptions) preserves DB value.
    $selectedOptionsJson = null;
    if (array_key_exists('selectedOptions', (array)$bookingData) && !empty($bookingData['selectedOptions'])) {
        $selectedOptionsJson = json_encode($bookingData['selectedOptions'], JSON_UNESCAPED_UNICODE);
    }

    //  bookingId  : INSERT  UPDATE  
    $existsStmt = $conn->prepare("SELECT bookingId FROM bookings WHERE bookingId = ? LIMIT 1");
    $exists = false;
    if ($existsStmt) {
        $existsStmt->bind_param('s', $bookingId);
        $existsStmt->execute();
        $r = $existsStmt->get_result();
        $exists = ($r && $r->num_rows > 0);
        $existsStmt->close();
    }

    if ($exists) {
        $sql = "
            UPDATE bookings
            SET packageId = ?,
                packageName = COALESCE(?, packageName),
                packagePrice = COALESCE(?, packagePrice),
                departureDate = ?,
                departureTime = ?,
                adults = ?,
                children = ?,
                infants = ?,
                totalAmount = ?,
                bookingStatus = ?,
                paymentStatus = ?,
                contactEmail = COALESCE(?, contactEmail),
                contactPhone = COALESCE(?, contactPhone),
                specialRequests = COALESCE(?, specialRequests),
                selectedOptions = COALESCE(?, selectedOptions),
                updatedAt = NOW()
            WHERE bookingId = ? AND accountId = ?
        ";
        $stmt = $conn->prepare($sql);
        $uid = (int)$userId;
        $stmt->bind_param(
            'isdssiiidsssssssi',
            $packageId,
            $packageName,
            $packagePrice,
            $departureDate,
            $departureTime,
            $adults,
            $children,
            $infants,
            $totalAmount,
            $bookingStatus,
            $bookingPaymentStatus,
            $contactEmail,
            $contactPhone,
            $specialRequests,
            $selectedOptionsJson,
            $bookingId,
            $uid
        );
        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $sql = "
            INSERT INTO bookings (
                bookingId, accountId, packageId, packageName, packagePrice,
                departureDate, departureTime, adults, children, infants,
                totalAmount, bookingStatus, paymentStatus, contactEmail, contactPhone,
                specialRequests, selectedOptions, customerAccountId
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('siisdssiiidssssssi',
            $bookingId, $userId, $packageId, $packageName, $packagePrice,
            $departureDate, $departureTime, $adults, $children, $infants,
            $totalAmount, $bookingStatus, $bookingPaymentStatus, $contactEmail, $contactPhone,
            $specialRequests, $selectedOptionsJson, $userId
        );
        if (!$stmt->execute()) {
            throw new Exception('Failed to create booking: ' . $stmt->error);
        }
        $stmt->close();
    }
    
    //   
    if (!empty($bookingData['selectedRooms'])) {
        saveBookingRooms($bookingId, $bookingData['selectedRooms']);
    }
    
    //   
    if (!empty($bookingData['customerInfo'])) {
        updateCustomerInfo($userId, $bookingData['customerInfo']);
    }
    
    return $bookingId;
}

//  ID  
function generateBookingId() {
    $prefix = 'BK';
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}

//   
function saveBookingRooms($bookingId, $selectedRooms) {
    global $conn;
    
    //  bookingId /   INSERT 
    try {
        $del = $conn->prepare("DELETE FROM booking_rooms WHERE transactNo = ?");
        if ($del) {
            $del->bind_param('s', $bookingId);
            $del->execute();
            $del->close();
        }
    } catch (Throwable $e) { /* ignore */ }
    
    // booking_rooms.roomType enum('standard','single','double','triple','family','suite')
    $allowed = ['standard', 'single', 'double', 'triple', 'family', 'suite'];
    $map = [
        'standard_room' => 'standard',
        'single_room' => 'single',
        'double_room' => 'double',
        'triple_room' => 'triple',
        'family_room' => 'family',
        'suite_room' => 'suite',
        'standard' => 'standard',
        'single' => 'single',
        'double' => 'double',
        'triple' => 'triple',
        'family' => 'family',
        'suite' => 'suite',
    ];

    foreach ($selectedRooms as $roomType => $room) {
        $stmt = $conn->prepare("
            INSERT INTO booking_rooms (
                transactNo, roomType, roomCount, pricePerRoom, totalPrice
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $rawType = is_string($roomType) ? strtolower(trim($roomType)) : '';
        $normalizedType = $map[$rawType] ?? null;
        if ($normalizedType === null && $rawType) {
            // "standard-room" / "standard room"   
            $rawType2 = preg_replace('/[^a-z_]+/', '_', $rawType);
            $rawType2 = trim($rawType2, '_');
            $normalizedType = $map[$rawType2] ?? null;
        }
        if ($normalizedType === null || !in_array($normalizedType, $allowed, true)) {
            $normalizedType = 'standard';
        }
        
        $roomCount = $room['count'] ?? 1;
        $roomPrice = $room['price'] ?? 0;
        $totalPrice = $roomPrice * $roomCount;
        
        $stmt->bind_param('ssidd', 
            $bookingId, $normalizedType, $roomCount, 
            $roomPrice, $totalPrice
        );
        
        $stmt->execute();
    }
}

// 
function updateCustomerInfo($userId, $customerInfo) {
    global $conn;
    
    // client   accountId  
    $stmt = $conn->prepare("SELECT id FROM client WHERE accountId = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        //    
        $stmt = $conn->prepare("
            UPDATE client SET 
                fName = ?, contactNo = ?, emailAddress = ?
            WHERE accountId = ?
        ");
        
        $name = $customerInfo['name'] ?? '';
        $phone = $customerInfo['phone'] ?? '';
        $email = $customerInfo['email'] ?? '';
        
        $stmt->bind_param('sssi', $name, $phone, $email, $userId);
        
        $stmt->execute();
    } else {
        //    
        $clientId = 'CLI' . date('Ymd') . rand(1000, 9999);
        
        $stmt = $conn->prepare("
            INSERT INTO client (
                clientId, accountId, fName, lName, contactNo, emailAddress
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $name = $customerInfo['name'] ?? '';
        $phone = $customerInfo['phone'] ?? '';
        $email = $customerInfo['email'] ?? '';
        
        $stmt->bind_param('siisss',
            $clientId, $userId, 
            $name,
            $name, //    
            $phone,
            $email
        );
        
        $stmt->execute();
    }
}

function processPayment($bookingId, $bookingData, $paymentMethodEnum, $paymentRowStatus, $userId) {
    global $conn;
    
    $amount = $bookingData['finalPricing']['total_price'] ?? 0;
    $paymentDate = date('Y-m-d H:i:s');
    $paymentStatus = in_array($paymentRowStatus, ['pending','completed','failed','cancelled','refunded'], true) ? $paymentRowStatus : 'pending';
    $transactionId = null;
    if ($paymentStatus === 'completed') {
        $transactionId = 'TXN' . time() . rand(1000, 9999);
    }
    $userId = (int)$userId;
    
    $allowed = ['card', 'bank_transfer', 'paypal', 'gcash', 'paymaya'];
    $paymentMethodEnum = in_array($paymentMethodEnum, $allowed, true) ? $paymentMethodEnum : 'bank_transfer';
    
    //  ID 
    $paymentId = 'PAY' . date('Ymd') . rand(1000, 9999);
    
    $stmt = $conn->prepare("
        INSERT INTO payments (
            paymentId, bookingId, accountId, paymentMethod, 
            paymentAmount, paymentStatus, transactionId, paymentDate
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('ssisdsss', 
        $paymentId, $bookingId, $userId, $paymentMethodEnum,
        $amount, $paymentStatus, $transactionId, $paymentDate
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to process payment: ' . $stmt->error);
    }
    
    return $paymentId;
}

function createBookingTravelers($bookingId, $travelers) {
    global $conn;
    
    if (empty($travelers)) {
        return;
    }

    //  bookingId /   INSERT 
    try {
        $del = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
        if ($del) {
            $del->bind_param('s', $bookingId);
            $del->execute();
            $del->close();
        }
    } catch (Throwable $e) { /* ignore */ }
    
    $stmt = $conn->prepare("
        INSERT INTO booking_travelers (
            transactNo, travelerType, title, firstName, lastName, 
            birthDate, gender, nationality, passportNumber, 
            passportIssueDate, passportExpiry, passportImage, 
            visaStatus, specialRequests, isMainTraveler
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($travelers as $index => $traveler) {
        // SMT   - camelCase snake_case   ( )
        //  YYYY-MM-DD  
        $birthDate = null;
        $birthDateRaw = $traveler['birth_date'] ?? $traveler['birthDate'] ?? null;
        if (!empty($birthDateRaw)) {
            $birthDate = formatBirthDate($birthDateRaw);
        }

        //    
        $passportIssueDate = null;
        $passportExpiry = null;
        $passportIssueDateRaw = $traveler['passport_issue_date'] ?? $traveler['passportIssueDate'] ?? null;
        $passportExpiryRaw = $traveler['passport_expiry_date'] ?? $traveler['passportExpiry'] ?? $traveler['passportExpiryDate'] ?? null;
        if (!empty($passportIssueDateRaw)) {
            $passportIssueDate = formatBirthDate($passportIssueDateRaw);
        }
        if (!empty($passportExpiryRaw)) {
            $passportExpiry = formatBirthDate($passportExpiryRaw);
        }
        // SMT  

        //   
        $visaStatus = 'not_required';
        if (!empty($traveler['visa_required']) || !empty($traveler['visaRequired']) || !empty($traveler['visaStatus'])) {
            $visaStatus = 'applied';
        }
        
        //    (    )
        $isMainTraveler = ($index === 0 && ($traveler['type'] ?? 'adult') === 'adult') ? 1 : 0;
        
        // SMT   - camelCase snake_case  
        $type = $traveler['type'] ?? $traveler['travelerType'] ?? 'adult';
        $title = $traveler['title'] ?? 'MR';
        $firstName = $traveler['first_name'] ?? $traveler['firstName'] ?? '';
        $lastName = $traveler['last_name'] ?? $traveler['lastName'] ?? '';
        // gender   (ENUM )
        $rawGender = $traveler['gender'] ?? 'male';
        $gender = strtolower($rawGender);
        if (!in_array($gender, ['male', 'female'])) {
            $gender = 'male'; // 
        }
        $nationality = $traveler['nationality'] ?? 'Korean';
        $passportNumber = $traveler['passport_number'] ?? $traveler['passportNumber'] ?? '';
        $passportImage = $traveler['passport_image'] ?? $traveler['passportImage'] ?? null;
        $specialRequests = $traveler['special_requests'] ?? $traveler['specialRequests'] ?? '';
        // SMT  
        
        $stmt->bind_param('ssssssssssssssi',
            $bookingId,
            $type,
            $title,
            $firstName,
            $lastName,
            $birthDate,
            $gender,
            $nationality,
            $passportNumber,
            $passportIssueDate,
            $passportExpiry,
            $passportImage,
            $visaStatus,
            $specialRequests,
            $isMainTraveler
        );
        
        $stmt->execute();
    }
}

// YYYY-MM-DD   (YYYY.MM.DD / YYYY/MM/DD / ISO datetime   )
function normalize_date_ymd(string $raw): string {
    $s = trim($raw);
    if ($s === '') return '';

    // ISO datetime: YYYY-MM-DDTHH:MM...
    if (strpos($s, 'T') !== false && strlen($s) >= 10) {
        $s = substr($s, 0, 10);
    }
    $s = str_replace(['.', '/'], '-', $s);

    // YYYYMMDD
    if (preg_match('/^\d{8}$/', $s)) {
        return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
    }
    // YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    //  : DateTime 
    try {
        $dt = new DateTime($raw);
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return '';
    }
}

//      pending   
function cancel_other_pending_drafts(int $accountId, int $packageId, string $departureDate, string $keepBookingId): void {
    global $conn;
    if ($accountId <= 0 || $packageId <= 0 || $departureDate === '' || $keepBookingId === '') return;
    $stmt = $conn->prepare("
        UPDATE bookings
        SET bookingStatus = 'cancelled',
            paymentStatus = 'failed',
            updatedAt = NOW()
        WHERE accountId = ?
          AND packageId = ?
          AND departureDate = ?
          AND bookingStatus = 'pending'
          AND paymentStatus = 'pending'
          AND bookingId <> ?
    ");
    if (!$stmt) return;
    $stmt->bind_param('iiss', $accountId, $packageId, $departureDate, $keepBookingId);
    $stmt->execute();
    $stmt->close();
}

//     (YYYYMMDD -> YYYY-MM-DD)
function formatBirthDate($dateString) {
    if (empty($dateString)) {
        return null;
    }
    
    // YYYYMMDD  
    if (strlen($dateString) === 8 && is_numeric($dateString)) {
        $year = substr($dateString, 0, 4);
        $month = substr($dateString, 4, 2);
        $day = substr($dateString, 6, 2);
        return $year . '-' . $month . '-' . $day;
    }
    
    //  YYYY-MM-DD  
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
        return $dateString;
    }
    
    return null;
}

function updatePackageAvailability($packageId, $selectedFlight) {
    global $conn;
    
    if (!$packageId || !$selectedFlight) {
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE package_flights 
        SET availableSeats = availableSeats - 1 
        WHERE packageId = ? AND flightId = ? AND availableSeats > 0
    ");
    
    $stmt->bind_param('ii', $packageId, $selectedFlight['id']);
    $stmt->execute();
}

function sendBookingConfirmation($userId, $bookingId) {
    // In production, implement email sending
    // For now, just log the action
    error_log("Booking confirmation sent for user: $userId, booking: $bookingId");
}

function handlePaymentVerification($input) {
    sendSuccessResponse([
        'message' => 'Payment verification not implemented yet'
    ]);
}

// Get booking data for response
function getBookingData($bookingId) {
    global $conn;
    
    try {
        $query = "
            SELECT 
                b.*,
                COALESCE(p.packageName, b.packageName, CONCAT('Deleted product #', b.packageId)) AS packageName,
                p.packageDestination,
                p.packageImage,
                COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) as duration_days,
                c.fName,
                c.lName,
                c.contactNo,
                c.emailAddress
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN client c ON b.accountId = c.accountId
            WHERE b.bookingId = ?
        ";
        
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