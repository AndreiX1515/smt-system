<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../lib/booking-utils.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'POST  .'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json_response(['success' => false, 'message' => ' JSON .'], 400);
}

/**
 *   / API
 * - (JS)     booking row pending  upsert/update   
 *
 *  :
 * - action=get_pending: userId+packageId+departureDate  pending  1 
 * - action=save: bookingId  customerInfo/selectedOptions   
 * - action : bookingId  ,   (  pending )
 */

try {
    $action = $input['action'] ?? '';

    // SMT   - booking_id    ( )
    if ($action === 'get_by_id') {
        $bookingId = trim((string)($input['bookingId'] ?? ''));
        if ($bookingId === '') {
            send_json_response(['success' => false, 'message' => 'bookingId .'], 400);
        }

        $stmt = $conn->prepare("
            SELECT bookingId, accountId, packageId, packageName, packagePrice,
                   departureDate, departureTime, adults, children, infants,
                   totalAmount, bookingStatus, paymentStatus, selectedRooms, selectedOptions
            FROM bookings
            WHERE bookingId = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        send_json_response([
            'success' => true,
            'data' => $row ?: null
        ]);
    }
    // SMT  

    if ($action === 'get_pending') {
        $userId = (int)($input['userId'] ?? 0);
        $packageId = (int)($input['packageId'] ?? 0);
        $departureDate = trim((string)($input['departureDate'] ?? ''));

        if ($userId <= 0 || $packageId <= 0 || $departureDate === '') {
            send_json_response(['success' => false, 'message' => '  .'], 400);
        }

        $stmt = $conn->prepare("
            SELECT bookingId, accountId, packageId, departureDate, departureTime, adults, children, infants, totalAmount, bookingStatus, paymentStatus, selectedOptions
            FROM bookings
            WHERE accountId = ?
              AND packageId = ?
              AND departureDate = ?
              AND bookingStatus = 'pending'
            ORDER BY createdAt DESC
            LIMIT 1
        ");
        $stmt->bind_param('iis', $userId, $packageId, $departureDate);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        send_json_response([
            'success' => true,
            'booking' => $row ?: null
        ]);
    }

    if ($action === 'save') {
        $bookingId = trim((string)($input['bookingId'] ?? ''));
        if ($bookingId === '') {
            send_json_response(['success' => false, 'message' => 'bookingId .'], 400);
        }

        //  selectedOptions(JSON)  (customerInfo/ )
        $existingSelectedOptionsArr = [];
        $stmt = $conn->prepare("SELECT selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc()) && isset($row['selectedOptions']) && is_string($row['selectedOptions']) && trim($row['selectedOptions']) !== '') {
            try {
                $decoded = json_decode($row['selectedOptions'], true);
                if (is_array($decoded)) $existingSelectedOptionsArr = $decoded;
            } catch (Throwable $t) {
                // ignore
            }
        }
        $stmt->close();

        // 1)  (customerInfo)  
        if (isset($input['customerInfo']) && is_array($input['customerInfo'])) {
            $ci = $input['customerInfo'];
            $contactEmail = isset($ci['email']) ? sanitize_input((string)$ci['email']) : null;
            $phone = isset($ci['phone']) ? preg_replace('/[^0-9]/', '', (string)$ci['phone']) : null;
            $cc = isset($ci['country_code']) ? sanitize_input((string)$ci['country_code']) : null;
            $contactPhone = null;
            if ($phone) {
                $contactPhone = ($cc ? ($cc . ' ') : '') . $phone;
            }

            $stmt = $conn->prepare("UPDATE bookings SET contactEmail = COALESCE(?, contactEmail), contactPhone = COALESCE(?, contactPhone), updatedAt = NOW() WHERE bookingId = ?");
            $stmt->bind_param('sss', $contactEmail, $contactPhone, $bookingId);
            $stmt->execute();
            $stmt->close();

            // reservation-sum-pay.js   selectedOptions JSON customerInfo  
            $existingSelectedOptionsArr['customerInfo'] = [
                'name' => isset($ci['name']) ? (string)$ci['name'] : null,
                'email' => $contactEmail,
                'phone' => $phone,
                'country_code' => $cc,
                'load_from_account' => isset($ci['load_from_account']) ? (bool)$ci['load_from_account'] : null,
            ];
            // temp save marker (schema-safe): bookingStatus enum is limited, so keep progress in selectedOptions
            $existingSelectedOptionsArr['_tempSaved'] = true;
            $existingSelectedOptionsArr['_tempSavedAt'] = date('c');
            $existingSelectedOptionsArr['_reservationStage'] = 'enter_customer_info';
            $mergedJson = json_encode($existingSelectedOptionsArr, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("UPDATE bookings SET selectedOptions = ?, updatedAt = NOW() WHERE bookingId = ?");
            $stmt->bind_param('ss', $mergedJson, $bookingId);
            $stmt->execute();
            $stmt->close();
        }

        // 2) /(selectedOptions)  
        if (isset($input['selectedOptions']) && is_array($input['selectedOptions'])) {
            $so = $input['selectedOptions'];
            $selectedOptions = $so['selectedOptions'] ?? null;
            $seatRequest = isset($so['seatRequest']) ? (string)$so['seatRequest'] : null;
            $otherRequest = isset($so['otherRequest']) ? (string)$so['otherRequest'] : null;

            //  customerInfo     
            if (is_array($selectedOptions) || is_object($selectedOptions)) {
                $existingSelectedOptionsArr['selectedOptions'] = $selectedOptions;
            }
            $existingSelectedOptionsArr['_tempSaved'] = true;
            $existingSelectedOptionsArr['_tempSavedAt'] = date('c');
            // stage hint (best-effort)
            if (!empty($selectedOptions) && is_array($selectedOptions)) {
                $existingSelectedOptionsArr['_reservationStage'] = 'selected_options';
            }
            $selectedOptionsJson = json_encode($existingSelectedOptionsArr, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE bookings SET selectedOptions = COALESCE(?, selectedOptions), seatRequest = COALESCE(?, seatRequest), otherRequest = COALESCE(?, otherRequest), updatedAt = NOW() WHERE bookingId = ?");
            $stmt->bind_param('ssss', $selectedOptionsJson, $seatRequest, $otherRequest, $bookingId);
            $stmt->execute();
            $stmt->close();
        }

        send_json_response(['success' => true, 'bookingId' => $bookingId]);
    }

    //  upsert()
    $userId = isset($input['userId']) && $input['userId'] !== null && $input['userId'] !== '' ? (int)$input['userId'] : 0;
    $bookingId = trim((string)($input['bookingId'] ?? ''));
    $packageId = (int)($input['packageId'] ?? 0);
    $departureDate = trim((string)($input['departureDate'] ?? ''));
    $departureTimeRaw = trim((string)($input['departureTime'] ?? ''));
    $departureTime = ($departureTimeRaw === '') ? null : $departureTimeRaw;
    $packageName = isset($input['packageName']) ? sanitize_input((string)$input['packageName']) : null;
    $packagePrice = isset($input['packagePrice']) ? (float)$input['packagePrice'] : null;
    // legacy columns (adults/children/infants): keep for backward compatibility, but derive from guestOptions when provided
    $adults = isset($input['adults']) ? (int)$input['adults'] : 0;
    $children = isset($input['children']) ? (int)$input['children'] : 0;
    $infants = isset($input['infants']) ? (int)$input['infants'] : 0;
    $totalAmount = isset($input['totalAmount']) ? (float)$input['totalAmount'] : 0.0;
    $selectedRooms = $input['selectedRooms'] ?? null;
    $selectedRoomsJson = null;
    if (is_array($selectedRooms) || is_object($selectedRooms)) {
        $selectedRoomsJson = json_encode($selectedRooms, JSON_UNESCAPED_UNICODE);
    }

    // guestOptions: dynamic guest pricing options selected in step1
    // Support both payload shapes:
    // - { guestOptions: [...] }
    // - { selectedOptions: { guestOptions: [...] } }
    $guestOptions = $input['guestOptions'] ?? (($input['selectedOptions']['guestOptions'] ?? null));
    $guestOptionsArr = (is_array($guestOptions) || is_object($guestOptions)) ? $guestOptions : null;

    // priceTier: B2B or B2C (가격 티어)
    $priceTierRaw = trim((string)($input['priceTier'] ?? ''));
    $priceTier = in_array($priceTierRaw, ['B2B', 'B2C'], true) ? $priceTierRaw : 'B2C';
    $mergedSelectedOptionsJson = null;
    if ($guestOptionsArr !== null) {
        // derive legacy counts as "total guests" to prevent old screens from excluding "3rd option"
        $sumGuests = 0;
        try {
            foreach ((array)$guestOptionsArr as $go) {
                if (is_array($go)) {
                    $q = isset($go['qty']) ? (int)$go['qty'] : (isset($go['quantity']) ? (int)$go['quantity'] : 0);
                    if ($q > 0) $sumGuests += $q;
                }
            }
        } catch (Throwable $t) { /* ignore */ }
        $adults = max(0, $sumGuests);
        $children = 0;
        $infants = 0;
    }

    // (guest)  (localStorage) 
    if ($userId <= 0) {
        send_json_response(['success' => true, 'bookingId' => null]);
    }

    if ($packageId <= 0 || $departureDate === '') {
        send_json_response(['success' => false, 'message' => 'packageId/departureDate .'], 400);
    }

    // bookingId   pending 
    if ($bookingId === '') {
        $stmt = $conn->prepare("
            SELECT bookingId
            FROM bookings
            WHERE accountId = ?
              AND packageId = ?
              AND departureDate = ?
              AND bookingStatus = 'pending'
            ORDER BY createdAt DESC
            LIMIT 1
        ");
        $stmt->bind_param('iis', $userId, $packageId, $departureDate);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && !empty($row['bookingId'])) {
            $bookingId = (string)$row['bookingId'];
        }
    }

    if ($bookingId !== '') {
        // capacity 검증 (UPDATE 경로 — 자기 자신 제외)
        $totalPax = $adults + $children; // infants without seat 제외
        if ($totalPax > 0) {
            $cap = check_capacity($conn, $packageId, $departureDate, $totalPax, $bookingId);
            if (!$cap['ok']) {
                send_json_response(['success' => false, 'message' => $cap['message']], 400);
            }
        }

        // merge guestOptions into selectedOptions JSON (schema-safe)
        if ($guestOptionsArr !== null) {
            $existing = [];
            $st = $conn->prepare("SELECT selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
            if ($st) {
                $st->bind_param('s', $bookingId);
                $st->execute();
                $rs = $st->get_result();
                if ($rs && ($r = $rs->fetch_assoc()) && isset($r['selectedOptions']) && is_string($r['selectedOptions']) && trim($r['selectedOptions']) !== '') {
                    try {
                        $decoded = json_decode($r['selectedOptions'], true);
                        if (is_array($decoded)) $existing = $decoded;
                    } catch (Throwable $t) { /* ignore */ }
                }
                $st->close();
            }
            $existing['guestOptions'] = $guestOptionsArr;
            $existing['_tempSaved'] = true;
            $existing['_tempSavedAt'] = date('c');
            $existing['_reservationStage'] = 'select_guests';
            $mergedSelectedOptionsJson = json_encode($existing, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $conn->prepare("
            UPDATE bookings
            SET packageId = ?,
                departureDate = ?,
                departureTime = ?,
                packageName = COALESCE(?, packageName),
                packagePrice = COALESCE(?, packagePrice),
                adults = ?,
                children = ?,
                infants = ?,
                totalAmount = ?,
                selectedOptions = COALESCE(?, selectedOptions),
                selectedRooms = COALESCE(?, selectedRooms),
                price_tier = ?,
                updatedAt = NOW()
            WHERE bookingId = ?
        ");
        $stmt->bind_param(
            'isssdiiidssss',
            $packageId,
            $departureDate,
            $departureTime,
            $packageName,
            $packagePrice,
            $adults,
            $children,
            $infants,
            $totalAmount,
            $mergedSelectedOptionsJson,
            $selectedRoomsJson,
            $priceTier,
            $bookingId
        );
        $stmt->execute();
        $stmt->close();

        send_json_response(['success' => true, 'bookingId' => $bookingId]);
    }

    //
    // capacity 검증 (INSERT 경로)
    $totalPax = $adults + $children; // infants without seat 제외
    if ($totalPax > 0) {
        $cap = check_capacity($conn, $packageId, $departureDate, $totalPax);
        if (!$cap['ok']) {
            send_json_response(['success' => false, 'message' => $cap['message']], 400);
        }
    }

    // merge guestOptions into selectedOptions JSON on insert (schema-safe)
    if ($guestOptionsArr !== null) {
        $base = [
            'guestOptions' => $guestOptionsArr,
            '_tempSaved' => true,
            '_tempSavedAt' => date('c'),
            '_reservationStage' => 'select_guests',
        ];
        $mergedSelectedOptionsJson = json_encode($base, JSON_UNESCAPED_UNICODE);
    }

    $bookingId = generate_booking_id($conn);
    $stmt = $conn->prepare("
        INSERT INTO bookings (
            bookingId, accountId, packageId, packageName, packagePrice,
            departureDate, departureTime, adults, children, infants,
            totalAmount, bookingStatus, paymentStatus, selectedRooms, selectedOptions, price_tier, customerAccountId
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, 'pending', 'pending', ?, ?, ?, ?
        )
    ");
    $stmt->bind_param(
        'siisdssiidsssi',
        $bookingId, $userId, $packageId, $packageName, $packagePrice,
        $departureDate, $departureTime, $adults, $children, $infants,
        $totalAmount, $selectedRoomsJson, $mergedSelectedOptionsJson, $priceTier, $userId
    );
    $stmt->execute();
    $stmt->close();

    send_json_response(['success' => true, 'bookingId' => $bookingId]);

} catch (Exception $e) {
    send_json_response(['success' => false, 'message' => ' : ' . $e->getMessage()], 500);
}

function generate_booking_id(mysqli $conn): string {
    $prefix = 'BK' . date('Ymd');
    for ($i = 0; $i < 10; $i++) {
        $rand = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $id = $prefix . $rand; // : BK2025121701234
        $stmt = $conn->prepare("SELECT bookingId FROM bookings WHERE bookingId = ? LIMIT 1");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
        if (!$exists) return $id;
    }
    //  fallback
    return $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}


