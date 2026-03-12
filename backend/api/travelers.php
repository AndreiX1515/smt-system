<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

// send_json_response conn.php   

// POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'POST  .'], 405);
}

function visa_applications_table_exists(mysqli $conn): bool {
    $t = $conn->query("SHOW TABLES LIKE 'visa_applications'");
    return $t && $t->num_rows > 0;
}

function visa_applications_has_column(mysqli $conn, string $col): bool {
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM visa_applications LIKE '$c'");
    return $r && $r->num_rows > 0;
}

function generateVisaApplicationNo(mysqli $conn): string {
    // applicationNo: varchar(20) NOT NULL
    // : VA + YYYYMMDD + 4  ( 14)
    $prefix = 'VA' . date('Ymd');
    for ($i = 0; $i < 20; $i++) {
        $rand = str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $no = $prefix . $rand;

        $chk = $conn->prepare("SELECT 1 FROM visa_applications WHERE applicationNo = ? LIMIT 1");
        if (!$chk) return $no; // prepare    
        $chk->bind_param('s', $no);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$exists) return $no;
    }
    return $prefix . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 *     (visa_required=1) 
 *    " "   .
 *
 * -   (visa_applications.applicationId/applicationNo...) 
 * - booking  1 (    )
 * -  document_required( ) 
 */
function ensureVisaApplicationForBooking(mysqli $conn, string $bookingId, string $applicantName, ?int $bookingTravelerId = null): void {
    try {
        if ($bookingId === '' || $bookingId === 'temp') return;
        if (!visa_applications_table_exists($conn)) return;

        //  (applicationNo )   
        if (!visa_applications_has_column($conn, 'applicationNo')) return;

        // bookings(accountId/packageName/departureDate/contact...)  (  )
        $bst = $conn->prepare("SELECT accountId, packageId, packageName, departureDate, totalAmount, contactEmail, contactPhone FROM bookings WHERE bookingId = ? LIMIT 1");
        if (!$bst) return;
        $bst->bind_param('s', $bookingId);
        $bst->execute();
        $b = $bst->get_result()->fetch_assoc();
        $bst->close();
        if (!$b) return;

        $accountId = (int)($b['accountId'] ?? 0);
        if ($accountId <= 0) return;

        // bookingTravelerId  " " / 
        // (      )
        $hasTravelerCol = visa_applications_has_column($conn, 'bookingTravelerId');
        $tid = ($bookingTravelerId !== null) ? (int)$bookingTravelerId : null;
        if ($hasTravelerCol && $tid !== null && $tid > 0) {
            $chk = $conn->prepare("SELECT applicationId FROM visa_applications WHERE accountId = ? AND transactNo = ? AND bookingTravelerId = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('isi', $accountId, $bookingId, $tid);
                $chk->execute();
                $exists = $chk->get_result()->num_rows > 0;
                $chk->close();
                if ($exists) return;
            }
        } else {
            // : travelerId  booking  1 ()
            $chk = $conn->prepare("SELECT applicationId FROM visa_applications WHERE accountId = ? AND transactNo = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('is', $accountId, $bookingId);
                $chk->execute();
                $exists = $chk->get_result()->num_rows > 0;
                $chk->close();
                if ($exists) return;
            }
        }

        $applicationNo = generateVisaApplicationNo($conn);
        $packageName = (string)($b['packageName'] ?? '');
        $departureDate = (string)($b['departureDate'] ?? '');
        if ($departureDate === '') $departureDate = date('Y-m-d');
        $applicantName = trim($applicantName);
        if ($applicantName === '') $applicantName = 'Applicant';

        // destinationCountry    packageName   →  
        $destinationCountry = $packageName !== '' ? $packageName : 'Package';

        if ($hasTravelerCol && $tid !== null && $tid > 0) {
            $sql = "INSERT INTO visa_applications (
                        applicationNo, accountId, transactNo, bookingTravelerId, applicantName,
                        visaType, destinationCountry, applicationDate, departureDate,
                        status, processingFee
                    ) VALUES (?, ?, ?, ?, ?, 'tourist', ?, CURDATE(), ?, 'document_required', 0.00)";

            $ins = $conn->prepare($sql);
            if (!$ins) return;
            $ins->bind_param('sisisss', $applicationNo, $accountId, $bookingId, $tid, $applicantName, $destinationCountry, $departureDate);
            $ins->execute();
            $ins->close();
        } else {
            $sql = "INSERT INTO visa_applications (
                        applicationNo, accountId, transactNo, applicantName,
                        visaType, destinationCountry, applicationDate, departureDate,
                        status, processingFee
                    ) VALUES (?, ?, ?, ?, 'tourist', ?, CURDATE(), ?, 'document_required', 0.00)";
            $ins = $conn->prepare($sql);
            if (!$ins) return;
            $ins->bind_param('sissss', $applicationNo, $accountId, $bookingId, $applicantName, $destinationCountry, $departureDate);
            $ins->execute();
            $ins->close();
        }
    } catch (Throwable $e) {
        //       
        error_log("ensureVisaApplicationForBooking error: " . $e->getMessage());
    }
}

// FormData  JSON  
$input = [];
if (!empty($_POST)) {
    $input = $_POST;
} else {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        // FormData  
        parse_str($rawInput, $input);
    }
}

if (!$input || empty($input)) {
    send_json_response(['success' => false, 'message' => '  .'], 400);
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'getByBooking':
            getTravelersByBooking($conn, $input);
            break;
        case 'getByTypeAndIndex':
            getTravelerByTypeAndIndex($conn, $input);
            break;
        case 'create':
            createTraveler($conn, $input);
            break;
        case 'update':
            updateTraveler($conn, $input);
            break;
        case 'delete':
            deleteTraveler($conn, $input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} catch (Exception $e) {
    error_log("Travelers API error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '  : ' . $e->getMessage()], 500);
}

function getTravelersByBooking($conn, $input) {
    $bookingId = $input['bookingId'] ?? '';
    
    if (empty($bookingId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    //      
    if ($bookingId === 'temp') {
        send_json_response([
            'success' => true,
            'travelers' => []
        ]);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            bt.*,
            b.bookingId
        FROM booking_travelers bt
        JOIN bookings b ON bt.transactNo = b.bookingId
        WHERE b.bookingId = ?
        ORDER BY bt.bookingTravelerId
    ");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $travelers = [];
    $adultSeq = 1;
    $childSeq = 1;
    $infantSeq = 1;
    
    while ($row = $result->fetch_assoc()) {
        $sequence = 1;
        $type = strtolower($row['travelerType'] ?? 'adult');
        if ($type === 'adult') {
            $sequence = $adultSeq++;
        } elseif ($type === 'child') {
            $sequence = $childSeq++;
        } else {
            $sequence = $infantSeq++;
        }
        
        $travelers[] = [
            'id' => $row['bookingTravelerId'],
            'type' => $row['travelerType'],
            'sequence' => $sequence,
            'title' => $row['title'],
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'birthDate' => $row['birthDate'],
            'gender' => $row['gender'],
            'nationality' => $row['nationality'],
            'passportNumber' => $row['passportNumber'],
            'passportIssueDate' => $row['passportIssueDate'],
            'passportExpiry' => $row['passportExpiry'],
            'passportImage' => $row['passportImage'],
            'visaStatus' => $row['visaStatus'],
            'specialRequests' => $row['specialRequests'],
            'isMainTraveler' => $row['isMainTraveler']
        ];
    }
    
    send_json_response([
        'success' => true,
        'travelers' => $travelers
    ]);
}

function getTravelerByTypeAndIndex($conn, $input) {
    $bookingId = $input['bookingId'] ?? 'temp';
    $type = $input['type'] ?? 'adult';
    $index = intval($input['index'] ?? 1);
    
    if ($bookingId === 'temp') {
        send_json_response([
            'success' => true,
            'traveler' => null
        ]);
    }
    
    //   (adult -> Adult, child -> Child, infant -> Infant)
    $typeMap = ['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'];
    $dbType = $typeMap[strtolower($type)] ?? 'Adult';
    
    $stmt = $conn->prepare("
        SELECT bt.*
        FROM booking_travelers bt
        JOIN bookings b ON bt.transactNo = b.bookingId
        WHERE b.bookingId = ? AND bt.travelerType = ?
        ORDER BY bt.bookingTravelerId
        LIMIT 1 OFFSET ?
    ");
    
    if (!$stmt) {
        send_json_response([
            'success' => false,
            'message' => '  : ' . $conn->error
        ], 500);
    }
    
    $offset = $index - 1;
    $stmt->bind_param("ssi", $bookingId, $dbType, $offset);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        send_json_response([
            'success' => false,
            'message' => '  : ' . $error
        ], 500);
    }
    
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        send_json_response([
            'success' => true,
            'traveler' => [
                'id' => $row['bookingTravelerId'],
                'type' => $row['travelerType'],
                'title' => $row['title'],
                'firstName' => $row['firstName'],
                'lastName' => $row['lastName'],
                'birthDate' => $row['birthDate'],
                'gender' => $row['gender'],
                'nationality' => $row['nationality'],
                'passportNumber' => $row['passportNumber'],
                'passportIssueDate' => $row['passportIssueDate'],
                'passportExpiry' => $row['passportExpiry'],
                'passportImage' => $row['passportImage'],
                'visaStatus' => $row['visaStatus'],
                'specialRequests' => $row['specialRequests'],
                'isMainTraveler' => $row['isMainTraveler']
            ]
        ]);
    } else {
        $stmt->close();
        send_json_response([
            'success' => true,
            'traveler' => null
        ]);
    }
}

function formatDateFromYYYYMMDD($dateString) {
    if (empty($dateString) || strlen($dateString) !== 8) {
        return null;
    }
    $year = substr($dateString, 0, 4);
    $month = substr($dateString, 4, 2);
    $day = substr($dateString, 6, 2);
    return "$year-$month-$day";
}

function createTraveler($conn, $input) {
    $bookingId = $input['bookingId'] ?? '';
    $type = $input['type'] ?? 'Adult';
    $sequence = intval($input['sequence'] ?? 1);
    $title = $input['title'] ?? 'MR';
    $firstName = $input['first_name'] ?? '';
    $lastName = $input['last_name'] ?? '';
    $birthDate = formatDateFromYYYYMMDD($input['birth_date'] ?? '');
    $age = intval($input['age'] ?? 0);
    $gender = strtolower($input['gender'] ?? 'male');
    if (!in_array($gender, ['male', 'female'])) {
        $gender = ($input['gender'] === 'M' || $input['gender'] === '') ? 'male' : 'female';
    }
    $nationality = $input['nationality'] ?? '';
    $passportNumber = $input['passport_number'] ?? '';
    $passportIssueDate = formatDateFromYYYYMMDD($input['passport_issue_date'] ?? '');
    $passportExpiry = formatDateFromYYYYMMDD($input['passport_expiry_date'] ?? '');
    $visaRequired = intval($input['visa_required'] ?? 0);
    $visaStatus = $visaRequired ? 'applied' : 'not_required';
    $specialRequests = $input['special_requests'] ?? '';
    $isMainTraveler = ($sequence === 1 && strtolower($type) === 'adult') ? 1 : 0;
    $profileSource = $input['profile_source'] ?? $input['profileSource'] ?? '';

    //
    $passportImage = null;
    if (!empty($input['passport_image'])) {
        $passportImage = $input['passport_image'];
    } elseif (!empty($_FILES['passport_image'])) {
        $file = $_FILES['passport_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            // Store as file path (avoid huge base64 in POST/DB; dev_tasks #117)
            $uploadDir = __DIR__ . '/../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($ext === '' || !preg_match('/^[a-z0-9]+$/', $ext)) $ext = 'jpg';
            $fileName = 'traveler_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $fileName;
            if (@move_uploaded_file($file['tmp_name'], $dest)) {
                $passportImage = 'uploads/passports/' . $fileName;
            } else {
                // fallback to base64 if move fails
                $passportImage = base64_encode(file_get_contents($file['tmp_name']));
            }
        }
    }
    
    if (empty($bookingId) || $bookingId === 'temp') {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    if (empty($firstName) || empty($lastName)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }

    //  (visa_required=1)  /    (    )
    if ($visaRequired === 1) {
        if (empty($birthDate)) send_json_response(['success' => false, 'message' => ' .'], 400);
        if (empty($nationality)) send_json_response(['success' => false, 'message' => ' .'], 400);
        if (empty($passportNumber)) send_json_response(['success' => false, 'message' => ' .'], 400);
        if (empty($passportIssueDate)) send_json_response(['success' => false, 'message' => '  .'], 400);
        if (empty($passportExpiry)) send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    //   (Adult, Child, Infant)
    $typeMap = ['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'];
    $dbType = $typeMap[strtolower($type)] ?? $type;
    
    //    ( bookingId, type, sequence)
    $checkStmt = $conn->prepare("
        SELECT bt.bookingTravelerId
        FROM booking_travelers bt
        JOIN bookings b ON bt.transactNo = b.bookingId
        WHERE b.bookingId = ? AND bt.travelerType = ?
        ORDER BY bt.bookingTravelerId
        LIMIT 1 OFFSET ?
    ");
    $offset = $sequence - 1;
    $checkStmt->bind_param("ssi", $bookingId, $dbType, $offset);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        //    
        $existingId = $checkResult->fetch_assoc()['bookingTravelerId'];
        $checkStmt->close();
        updateTravelerById($conn, $existingId, $input, $passportImage);
        return;
    }
    $checkStmt->close();
    
    //  
    $stmt = $conn->prepare("
        INSERT INTO booking_travelers (
            transactNo, travelerType, title, firstName, lastName,
            birthDate, gender, nationality, passportNumber,
            passportIssueDate, passportExpiry, passportImage,
            visaStatus, specialRequests, isMainTraveler, profile_source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('ssssssssssssssis',
        $bookingId,
        $dbType,
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
        $isMainTraveler,
        $profileSource
    );
    
    if ($stmt->execute()) {
        $travelerId = $conn->insert_id;
        if ($visaRequired === 1) {
            $applicantName = trim($firstName . ' ' . $lastName);
            ensureVisaApplicationForBooking($conn, (string)$bookingId, $applicantName, (int)$travelerId);
        }
        send_json_response([
            'success' => true,
            'message' => '  .',
            'travelerId' => $travelerId
        ]);
    } else {
        throw new Exception('  : ' . $stmt->error);
    }
    $stmt->close();
}

function updateTraveler($conn, $input) {
    $travelerId = $input['travelerId'] ?? '';
    
    if (empty($travelerId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    updateTravelerById($conn, $travelerId, $input, null);
}

function updateTravelerById($conn, $travelerId, $input, $passportImage = null) {
    $title = $input['title'] ?? null;
    $firstName = $input['first_name'] ?? null;
    $lastName = $input['last_name'] ?? null;
    $birthDate = !empty($input['birth_date']) ? formatDateFromYYYYMMDD($input['birth_date']) : null;
    $gender = strtolower($input['gender'] ?? null);
    if ($gender && !in_array($gender, ['male', 'female'])) {
        $gender = ($input['gender'] === 'M' || $input['gender'] === '') ? 'male' : 'female';
    }
    $nationality = $input['nationality'] ?? null;
    $passportNumber = $input['passport_number'] ?? null;
    $passportIssueDate = !empty($input['passport_issue_date']) ? formatDateFromYYYYMMDD($input['passport_issue_date']) : null;
    $passportExpiry = !empty($input['passport_expiry_date']) ? formatDateFromYYYYMMDD($input['passport_expiry_date']) : null;
    $visaRequired = isset($input['visa_required']) ? intval($input['visa_required']) : null;
    $visaStatus = null;
    if ($visaRequired !== null) {
        $visaStatus = $visaRequired ? 'applied' : 'not_required';
    }
    $specialRequests = $input['special_requests'] ?? null;
    $profileSource = $input['profile_source'] ?? $input['profileSource'] ?? null;

    //  (visa_required=1)     
    if ($visaRequired !== null && (int)$visaRequired === 1) {
        // UI      (enter-traveler-info.js)    
        if ($birthDate === null) send_json_response(['success' => false, 'message' => ' .'], 400);
        if (empty((string)$nationality)) send_json_response(['success' => false, 'message' => ' .'], 400);
        if (empty((string)$passportNumber)) send_json_response(['success' => false, 'message' => ' .'], 400);
        if ($passportIssueDate === null) send_json_response(['success' => false, 'message' => '  .'], 400);
        if ($passportExpiry === null) send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    //   
    if ($passportImage === null && !empty($input['passport_image'])) {
        $passportImage = $input['passport_image'];
    } elseif ($passportImage === null && !empty($_FILES['passport_image'])) {
        $file = $_FILES['passport_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($ext === '' || !preg_match('/^[a-z0-9]+$/', $ext)) $ext = 'jpg';
            $fileName = 'traveler_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $fileName;
            if (@move_uploaded_file($file['tmp_name'], $dest)) {
                $passportImage = 'uploads/passports/' . $fileName;
            } else {
                $passportImage = base64_encode(file_get_contents($file['tmp_name']));
            }
        }
    }
    
    //   SET
    $setParts = [];
    $params = [];
    $types = '';
    
    if ($title !== null) {
        $setParts[] = "title = ?";
        $params[] = $title;
        $types .= 's';
    }
    if ($firstName !== null) {
        $setParts[] = "firstName = ?";
        $params[] = $firstName;
        $types .= 's';
    }
    if ($lastName !== null) {
        $setParts[] = "lastName = ?";
        $params[] = $lastName;
        $types .= 's';
    }
    if ($birthDate !== null) {
        $setParts[] = "birthDate = ?";
        $params[] = $birthDate;
        $types .= 's';
    }
    if ($gender !== null) {
        $setParts[] = "gender = ?";
        $params[] = $gender;
        $types .= 's';
    }
    if ($nationality !== null) {
        $setParts[] = "nationality = ?";
        $params[] = $nationality;
        $types .= 's';
    }
    if ($passportNumber !== null) {
        $setParts[] = "passportNumber = ?";
        $params[] = $passportNumber;
        $types .= 's';
    }
    if ($passportIssueDate !== null) {
        $setParts[] = "passportIssueDate = ?";
        $params[] = $passportIssueDate;
        $types .= 's';
    }
    if ($passportExpiry !== null) {
        $setParts[] = "passportExpiry = ?";
        $params[] = $passportExpiry;
        $types .= 's';
    }
    if ($passportImage !== null) {
        $setParts[] = "passportImage = ?";
        $params[] = $passportImage;
        $types .= 's';
    }
    if ($visaStatus !== null) {
        $setParts[] = "visaStatus = ?";
        $params[] = $visaStatus;
        $types .= 's';
    }
    if ($specialRequests !== null) {
        $setParts[] = "specialRequests = ?";
        $params[] = $specialRequests;
        $types .= 's';
    }
    if ($profileSource !== null) {
        $setParts[] = "profile_source = ?";
        $params[] = $profileSource;
        $types .= 's';
    }

    if (empty($setParts)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    $params[] = $travelerId;
    $types .= 'i';
    
    $sql = "UPDATE booking_travelers SET " . implode(', ', $setParts) . " WHERE bookingTravelerId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($visaRequired !== null && (int)$visaRequired === 1) {
            // bookingId enter-traveler-info.js  ,    DB 
            $bookingId = (string)($input['bookingId'] ?? '');
            $fn = $firstName;
            $ln = $lastName;

            if ($bookingId === '' || $fn === null || $ln === null) {
                $qs = $conn->prepare("SELECT transactNo, firstName, lastName FROM booking_travelers WHERE bookingTravelerId = ? LIMIT 1");
                if ($qs) {
                    $tid = (int)$travelerId;
                    $qs->bind_param('i', $tid);
                    $qs->execute();
                    $row = $qs->get_result()->fetch_assoc();
                    $qs->close();
                    if ($bookingId === '' && !empty($row['transactNo'])) $bookingId = (string)$row['transactNo'];
                    if ($fn === null && isset($row['firstName'])) $fn = (string)$row['firstName'];
                    if ($ln === null && isset($row['lastName'])) $ln = (string)$row['lastName'];
                }
            }

            $applicantName = trim((string)($fn ?? '') . ' ' . (string)($ln ?? ''));
            ensureVisaApplicationForBooking($conn, $bookingId, $applicantName, (int)$travelerId);
        }
        send_json_response([
            'success' => true,
            'message' => '  .',
            'travelerId' => $travelerId
        ]);
    } else {
        throw new Exception('  : ' . $stmt->error);
    }
    $stmt->close();
}

function deleteTraveler($conn, $input) {
    $travelerId = $input['travelerId'] ?? '';
    
    if (empty($travelerId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    $stmt = $conn->prepare("DELETE FROM booking_travelers WHERE bookingTravelerId = ?");
    $stmt->bind_param('i', $travelerId);
    
    if ($stmt->execute()) {
        send_json_response([
            'success' => true,
            'message' => '  .'
        ]);
    } else {
        throw new Exception('  : ' . $stmt->error);
    }
    $stmt->close();
}
?>
