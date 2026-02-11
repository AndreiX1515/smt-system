<?php
/**
 * Meeting Locations API
 *       API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../conn.php';

// JSON  
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET  
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

// POST  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $input = array_merge($input, $_POST);
}

// action  
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

//  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // meeting_locations  
    createMeetingLocationsTable($conn);
    
    switch ($action) {
        case 'getMeetingLocations':
            getMeetingLocations($conn, $input);
            break;
            
        case 'createMeetingLocation':
            createMeetingLocation($conn, $input);
            break;
            
        case 'deleteMeetingLocation':
            deleteMeetingLocation($conn, $input);
            break;
            
        default:
            send_json_response([
                'success' => false,
                'message' => 'Invalid action: ' . $action
            ], 400);
    }
} catch (Exception $e) {
    error_log("Meeting Locations API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_json_response([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

// meeting_locations  
function createMeetingLocationsTable($conn) {
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS meeting_locations (
            locationId INT AUTO_INCREMENT PRIMARY KEY,
            guideId INT NOT NULL,
            bookingId VARCHAR(50) NULL,
            meetingTime TIME NOT NULL,
            locationName VARCHAR(255) NOT NULL,
            address VARCHAR(500) NOT NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            content TEXT NULL,
            status ENUM('active','deleted') NOT NULL DEFAULT 'active',
            deletedAt DATETIME NULL,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guide (guideId),
            INDEX idx_booking (bookingId),
            INDEX idx_created (createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->query($createTableSql);

    //  (bookingId INT) -> bookingId VARCHAR(50) 
    try {
        $colRes = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'bookingId'");
        $col = $colRes ? $colRes->fetch_assoc() : null;
        if ($col && isset($col['Type']) && stripos((string)$col['Type'], 'int') !== false) {
            $conn->query("ALTER TABLE meeting_locations MODIFY COLUMN bookingId VARCHAR(50) NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }

    //   status/deletedAt   (soft delete )
    try {
        $c1 = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
        if (!$c1 || $c1->num_rows === 0) {
            $conn->query("ALTER TABLE meeting_locations ADD COLUMN status ENUM('active','deleted') NOT NULL DEFAULT 'active' AFTER content");
        }
        $c2 = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'deletedAt'");
        if (!$c2 || $c2->num_rows === 0) {
            $conn->query("ALTER TABLE meeting_locations ADD COLUMN deletedAt DATETIME NULL AFTER status");
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// PHP 8+ bind_param(...$params)   "by reference"   helper 
function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array $params): void {
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = $params[$k];
    }
    $bindParams = array_merge([$types], $refs);
    $tmp = [];
    foreach ($bindParams as $k => $v) {
        $tmp[$k] = &$bindParams[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

function table_exists(mysqli $conn, string $tableName): bool {
    $tn = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$tn}'");
    return ($res && $res->num_rows > 0);
}

//  " " bookingId 1 
function resolve_today_booking_id(mysqli $conn, int $guideId): ?string {
    if ($guideId <= 0) return null;

    // bookings guideId    
    $hasGuideIdCol = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM bookings LIKE 'guideId'");
        $hasGuideIdCol = ($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        $hasGuideIdCol = false;
    }

    if ($hasGuideIdCol) {
        $st = $conn->prepare("
            SELECT b.bookingId
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            WHERE b.guideId = ?
              AND DATE(b.departureDate) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              --       (duration_days / durationDays / packageDuration )
              --  duration_days(: 3 4 3)  durationDays  .
              AND DATE(DATE_ADD(
                    b.departureDate,
                    INTERVAL COALESCE(
                        (SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId),
                        NULLIF(p.duration_days, 0),
                        NULLIF(p.durationDays, 0),
                        0
                    ) DAY
              )) >= CURDATE()
              AND (b.bookingStatus IS NULL OR b.bookingStatus <> 'cancelled')
            ORDER BY b.departureDate ASC, b.departureTime ASC, b.createdAt DESC
            LIMIT 1
        ");
        if ($st) {
            $st->bind_param('i', $guideId);
            $st->execute();
            $r = $st->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $st->close();
            if (!empty($row['bookingId'])) return (string)$row['bookingId'];
        }
    }

    // booking_guides   fallback
    if (table_exists($conn, 'booking_guides')) {
        $st = $conn->prepare("
            SELECT b.bookingId
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            JOIN booking_guides bg ON b.bookingId = bg.bookingId
            WHERE bg.guideId = ?
              AND DATE(b.departureDate) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND DATE(DATE_ADD(
                    b.departureDate,
                    INTERVAL COALESCE(
                        (SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId),
                        NULLIF(p.duration_days, 0),
                        NULLIF(p.durationDays, 0),
                        0
                    ) DAY
              )) >= CURDATE()
              AND (b.bookingStatus IS NULL OR b.bookingStatus <> 'cancelled')
            ORDER BY b.departureDate ASC, b.departureTime ASC, b.createdAt DESC
            LIMIT 1
        ");
        if ($st) {
            $st->bind_param('i', $guideId);
            $st->execute();
            $r = $st->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $st->close();
            if (!empty($row['bookingId'])) return (string)$row['bookingId'];
        }
    }

    return null;
}

//    
function getMeetingLocations($conn, $input) {
    //  ID  (  )
    $guideId = $input['guideId'] ?? null;
    $bookingId = $input['bookingId'] ?? ($input['booking_id'] ?? null);
    $todayOnly = $input['todayOnly'] ?? ($input['today_only'] ?? 0);
    $todayOnly = is_numeric($todayOnly) ? intval($todayOnly) : 0;
    
    //    
    if (empty($guideId)) {
        // user   user_id  ( ).   accountId .
        $accountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
        if ($accountId) {
            // guides  guideId 
            $guideStmt = $conn->prepare("SELECT guideId FROM guides WHERE accountId = ? LIMIT 1");
            if ($guideStmt) {
                $guideStmt->bind_param('i', $accountId);
                $guideStmt->execute();
                $guideResult = $guideStmt->get_result();
                if ($guideResult && $guideResult->num_rows > 0) {
                    $guideRow = $guideResult->fetch_assoc();
                    $guideId = $guideRow['guideId'];
                }
                $guideStmt->close();
            }
        }
    }
    
    if (empty($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'Guide ID is required'
        ], 400);
    }

    // guide-mypage → meeting-location.html  bookingId   :
    // " "  bookingId      .
    $resolvedBookingId = null;
    if (empty($bookingId) && $todayOnly === 1) {
        $resolvedBookingId = resolve_today_booking_id($conn, intval($guideId));
        if (!empty($resolvedBookingId)) {
            $bookingId = $resolvedBookingId;
        }
    }
    
    // WHERE  
    $whereConditions = ['guideId = ?'];
    $params = [$guideId];
    $types = 'i';
    
    if (!empty($bookingId)) {
        $whereConditions[] = 'bookingId = ?';
        $params[] = (string)$bookingId;
        $types .= 's';
    }

    //  active (/ ). includeDeleted=1  
    $includeDeleted = $input['includeDeleted'] ?? ($input['include_deleted'] ?? 0);
    $includeDeleted = is_numeric($includeDeleted) ? intval($includeDeleted) : 0;
    if ($includeDeleted !== 1) {
        // status    :      
        try {
            $col = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
            if ($col && $col->num_rows > 0) {
                $whereConditions[] = "status = 'active'";
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    // todayOnly=1  " (bookingId) ".
    // bookingId     meeting location     
    // createdAt=  bookingId   .
    if ($todayOnly === 1 && empty($bookingId)) {
        $whereConditions[] = "DATE(createdAt) = CURDATE()";
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            locationId,
            guideId,
            bookingId,
            meetingTime,
            locationName,
            address,
            latitude,
            longitude,
            content,
            status,
            createdAt,
            updatedAt
        FROM meeting_locations
        $whereClause
        ORDER BY meetingTime DESC, createdAt DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    mysqli_bind_params_by_ref($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        // / 
        $createdAt = $row['createdAt'] ?? '';
        $registrationDateTime = '';
        if ($createdAt) {
            $dateTime = new DateTime($createdAt);
            $registrationDateTime = $dateTime->format('Y-m-d H:i');
        }
        
        $meetingTime = $row['meetingTime'] ?? '';
        $meetingTimeFormatted = '';
        if ($meetingTime) {
            $timeParts = explode(':', $meetingTime);
            if (count($timeParts) >= 2) {
                $meetingTimeFormatted = sprintf('%02d:%s', $timeParts[0], $timeParts[1]);
            }
        }
        
        $locations[] = [
            'locationId' => intval($row['locationId']),
            'guideId' => intval($row['guideId']),
            'bookingId' => $row['bookingId'] ? (string)$row['bookingId'] : null,
            'registrationDateTime' => $registrationDateTime,
            'meetingTime' => $meetingTimeFormatted,
            'locationName' => $row['locationName'] ?? '',
            'address' => $row['address'] ?? '',
            'latitude' => $row['latitude'] ? floatval($row['latitude']) : null,
            'longitude' => $row['longitude'] ? floatval($row['longitude']) : null,
            'content' => $row['content'] ?? '',
            'createdAt' => $createdAt,
            'status' => (string)($row['status'] ?? 'active')
        ];
    }
    
    $stmt->close();
    
    send_json_response([
        'success' => true,
        'data' => [
            'resolvedBookingId' => $resolvedBookingId ? (string)$resolvedBookingId : (!empty($bookingId) ? (string)$bookingId : null),
            'locations' => $locations
        ]
    ]);
}

//   
function createMeetingLocation($conn, $input) {
    //  ID 
    $guideId = $input['guideId'] ?? null;
    $bookingId = $input['bookingId'] ?? ($input['booking_id'] ?? null);
    $meetingTime = $input['meetingTime'] ?? '';
    $locationName = $input['locationName'] ?? '';
    $address = $input['address'] ?? '';
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $content = $input['content'] ?? '';
    
    //    
    if (empty($guideId)) {
        $accountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
        if ($accountId) {
            // guides    (accountId   )
            $columnsCheck = $conn->query("SHOW COLUMNS FROM guides LIKE 'accountId'");
            $hasAccountId = ($columnsCheck && $columnsCheck->num_rows > 0);
            
            if ($hasAccountId) {
                // guides  guideId 
                $guideStmt = $conn->prepare("SELECT guideId FROM guides WHERE accountId = ? LIMIT 1");
                if ($guideStmt) {
                    $guideStmt->bind_param('i', $accountId);
                    $guideStmt->execute();
                    $guideResult = $guideStmt->get_result();
                    if ($guideResult && $guideResult->num_rows > 0) {
                        $guideRow = $guideResult->fetch_assoc();
                        $guideId = $guideRow['guideId'];
                    }
                    $guideStmt->close();
                }
            } else {
                // accountId       ()
                $guideStmt = $conn->query("SELECT guideId FROM guides WHERE isActive = 1 LIMIT 1");
                if ($guideStmt && $guideStmt->num_rows > 0) {
                    $guideRow = $guideStmt->fetch_assoc();
                    $guideId = $guideRow['guideId'];
                }
            }
        }
    }
    
    //   
    if (empty($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'Guide ID is required'
        ], 400);
    }

    // bookingId  " " bookingId   (NULL  )
    if (empty($bookingId)) {
        $resolvedBookingId = resolve_today_booking_id($conn, intval($guideId));
        if (!empty($resolvedBookingId)) {
            $bookingId = $resolvedBookingId;
        }
    }
    if (empty($bookingId)) {
        send_json_response([
            'success' => false,
            'message' => 'No schedule found for today (bookingId).'
        ], 400);
    }
    
    if (empty($meetingTime)) {
        send_json_response([
            'success' => false,
            'message' => 'Meeting time is required'
        ], 400);
    }
    
    if (empty($locationName)) {
        send_json_response([
            'success' => false,
            'message' => 'Location name is required'
        ], 400);
    }
    
    if (empty($address)) {
        send_json_response([
            'success' => false,
            'message' => 'Address is required'
        ], 400);
    }
    
    if (empty($content)) {
        send_json_response([
            'success' => false,
            'message' => 'Content is required'
        ], 400);
    }
    
    // meetingTime  (HH:MM )
    $meetingTimeFormatted = '';
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $meetingTime, $matches)) {
        $meetingTimeFormatted = sprintf('%02d:%02d:00', intval($matches[1]), intval($matches[2]));
    } else {
        send_json_response([
            'success' => false,
            'message' => 'Invalid meeting time format. Use HH:MM'
        ], 400);
    }
    
    // INSERT  (status=active)
    $sql = "
        INSERT INTO meeting_locations (
            guideId,
            bookingId,
            meetingTime,
            locationName,
            address,
            latitude,
            longitude,
            content,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $bookingIdParam = $bookingId ? (string)$bookingId : null;
    $latitudeParam = $latitude ? floatval($latitude) : null;
    $longitudeParam = $longitude ? floatval($longitude) : null;
    
    $stmt->bind_param('issssdds',
        $guideId,
        $bookingIdParam,
        $meetingTimeFormatted,
        $locationName,
        $address,
        $latitudeParam,
        $longitudeParam,
        $content
    );
    
    if ($stmt->execute()) {
        $locationId = $conn->insert_id;
        $stmt->close();

        // guide_locations( )  (guide-location  )
        try {
            $conn->query("UPDATE guide_locations SET isActive = 0 WHERE guideId = " . intval($guideId));
            $ins = $conn->prepare("INSERT INTO guide_locations (guideId, latitude, longitude, locationName, address, isActive) VALUES (?, ?, ?, ?, ?, 1)");
            if ($ins) {
                $ins->bind_param('iddss', $guideId, $latitudeParam, $longitudeParam, $locationName, $address);
                $ins->execute();
                $ins->close();
            }
        } catch (Throwable $e) {
            // ignore
        }
        
        send_json_response([
            'success' => true,
            'message' => 'Location registered successfully',
            'data' => [
                'locationId' => $locationId
            ]
        ]);
    } else {
        $stmt->close();
        throw new Exception('Failed to insert location: ' . $conn->error);
    }
}

//   
function deleteMeetingLocation($conn, $input) {
    $locationId = $input['locationId'] ?? null;
    
    if (empty($locationId)) {
        send_json_response([
            'success' => false,
            'message' => 'Location ID is required'
        ], 400);
    }
    
    //  ID  (    )
    $guideId = $input['guideId'] ?? null;
    if (empty($guideId)) {
        $accountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
        if ($accountId) {
            // guides    (accountId   )
            $columnsCheck = $conn->query("SHOW COLUMNS FROM guides LIKE 'accountId'");
            $hasAccountId = ($columnsCheck && $columnsCheck->num_rows > 0);
            
            if ($hasAccountId) {
                // guides  guideId 
                $guideStmt = $conn->prepare("SELECT guideId FROM guides WHERE accountId = ? LIMIT 1");
                if ($guideStmt) {
                    $guideStmt->bind_param('i', $accountId);
                    $guideStmt->execute();
                    $guideResult = $guideStmt->get_result();
                    if ($guideResult && $guideResult->num_rows > 0) {
                        $guideRow = $guideResult->fetch_assoc();
                        $guideId = $guideRow['guideId'];
                    }
                    $guideStmt->close();
                }
            } else {
                // accountId       ()
                $guideStmt = $conn->query("SELECT guideId FROM guides WHERE isActive = 1 LIMIT 1");
                if ($guideStmt && $guideStmt->num_rows > 0) {
                    $guideRow = $guideStmt->fetch_assoc();
                    $guideId = $guideRow['guideId'];
                }
            }
        }
    }
    
    //    
    $checkStmt = $conn->prepare("SELECT guideId FROM meeting_locations WHERE locationId = ?");
    $checkStmt->bind_param('i', $locationId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        send_json_response([
            'success' => false,
            'message' => 'Location not found'
        ], 404);
    }
    
    $locationRow = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($guideId && intval($locationRow['guideId']) !== intval($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'You do not have permission to delete this location'
        ], 403);
    }
    
    //  : hard delete  soft delete(status='deleted')
    // - /  status='active' 
    // -     Status=Deleted 
    $hasStatus = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
        $hasStatus = ($col && $col->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    if ($hasStatus) {
        $upd = $conn->prepare("UPDATE meeting_locations SET status = 'deleted', deletedAt = NOW(), updatedAt = NOW() WHERE locationId = ?");
        $upd->bind_param('i', $locationId);
        if ($upd->execute()) {
            $upd->close();
            send_json_response(['success' => true, 'message' => 'Location deleted successfully']);
        }
        $upd->close();
        throw new Exception('Failed to delete location: ' . $conn->error);
    }

    //  fallback: status    hard delete
    $deleteStmt = $conn->prepare("DELETE FROM meeting_locations WHERE locationId = ?");
    $deleteStmt->bind_param('i', $locationId);
    if ($deleteStmt->execute()) {
        $deleteStmt->close();
        send_json_response(['success' => true, 'message' => 'Location deleted successfully']);
    }
    $deleteStmt->close();
    throw new Exception('Failed to delete location: ' . $conn->error);
}

// JSON  
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>

