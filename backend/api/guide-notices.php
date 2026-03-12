<?php
/**
 * Guide Notices API
 *     API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../conn.php';

/**
 *        .
 * - user : $_SESSION['user_id']
 * -  : $_SESSION['accountId']
 */
function get_session_account_id(): ?int {
    $id = $_SESSION['user_id'] ?? $_SESSION['accountId'] ?? null;
    return is_numeric($id) ? intval($id) : null;
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

//  " " bookingId 1  (/  )
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
              AND DATE(b.departureDate) <= CURDATE()
              AND DATE(DATE_ADD(b.departureDate, INTERVAL COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 0) DAY)) >= CURDATE()
              AND (b.bookingStatus IS NULL OR b.bookingStatus NOT IN ('cancelled','draft'))
            ORDER BY b.departureTime DESC, b.createdAt DESC
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
              AND DATE(b.departureDate) <= CURDATE()
              AND DATE(DATE_ADD(b.departureDate, INTERVAL COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 0) DAY)) >= CURDATE()
              AND (b.bookingStatus IS NULL OR b.bookingStatus NOT IN ('cancelled','draft'))
            ORDER BY b.departureTime DESC, b.createdAt DESC
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

function resolve_guide_id(mysqli $conn, $guideIdFromInput, ?int $accountId): ?int {
    if (is_numeric($guideIdFromInput)) {
        return intval($guideIdFromInput);
    }
    if (empty($accountId)) {
        return null;
    }

    // guides  accountId   
    $columnsCheck = $conn->query("SHOW COLUMNS FROM guides LIKE 'accountId'");
    $hasAccountId = ($columnsCheck && $columnsCheck->num_rows > 0);

    if ($hasAccountId) {
        $stmt = $conn->prepare("SELECT guideId FROM guides WHERE accountId = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $stmt->close();
                return intval($row['guideId']);
            }
            $stmt->close();
        }
        return null;
    }

    // accountId    fallback
    $res = $conn->query("SELECT guideId FROM guides LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return intval($row['guideId']);
    }
    return null;
}

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
    // guide_announcements   (  )
    createGuideAnnouncementsTable($conn);
    
    switch ($action) {
        case 'getGuideNotices':
            getGuideNotices($conn, $input);
            break;
            
        case 'createGuideNotice':
            createGuideNotice($conn, $input);
            break;
            
        case 'updateGuideNotice':
            updateGuideNotice($conn, $input);
            break;
            
        case 'deleteGuideNotice':
            deleteGuideNotice($conn, $input);
            break;
            
        default:
            send_json_response([
                'success' => false,
                'message' => 'Invalid action: ' . $action
            ], 400);
    }
} catch (Exception $e) {
    error_log("Guide Notices API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_json_response([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

// guide_announcements   (  )
function createGuideAnnouncementsTable($conn) {
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS guide_announcements (
            announcementId INT AUTO_INCREMENT PRIMARY KEY,
            guideId INT NOT NULL,
            bookingId VARCHAR(20) NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
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

    //  DB bookingId INT  (bookingId BK... )  
    try {
        $cols = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'bookingId'");
        $col = $cols ? $cols->fetch_assoc() : null;
        $type = strtolower((string)($col['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'varchar') === false) {
            $conn->query("ALTER TABLE guide_announcements MODIFY bookingId VARCHAR(20) NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }

    // soft delete     
    try {
        $c1 = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        if (!$c1 || $c1->num_rows === 0) {
            $conn->query("ALTER TABLE guide_announcements ADD COLUMN status ENUM('active','deleted') NOT NULL DEFAULT 'active' AFTER content");
        }
        $c2 = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'deletedAt'");
        if (!$c2 || $c2->num_rows === 0) {
            $conn->query("ALTER TABLE guide_announcements ADD COLUMN deletedAt DATETIME NULL AFTER status");
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// () " "  (/)
function notify_booking_user_guide_notice(mysqli $conn, string $bookingId, int $announcementId, string $noticeTitle): void {
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$tbl || $tbl->num_rows === 0) return;

        // bookings  accountId  (B2B: customerAccountId )
        $recipient = null;
        $st = $conn->prepare("SELECT accountId, customerAccountId FROM bookings WHERE bookingId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('s', $bookingId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $cid = isset($row['customerAccountId']) ? (int)$row['customerAccountId'] : 0;
            $aid = isset($row['accountId']) ? (int)$row['accountId'] : 0;
            $recipient = ($cid > 0) ? $cid : ($aid > 0 ? $aid : null);
        }
        if (!$recipient) return;

        // notifications  
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

        $title = 'Guide Notice';
        $message = 'A guide notice has been posted.';
        $type = 'booking';
        $category = 'guide_notice';
        $priority = 'high';
        $actionUrl = 'guide-notice.html?booking_id=' . rawurlencode($bookingId);
        $data = [
            'statusKey' => 'guide_notice',
            'bookingId' => $bookingId,
            'announcementId' => $announcementId,
            'noticeTitle' => $noticeTitle,
        ];

        $fields = ["accountId", "title", "message", "priority", "isRead", "actionUrl", "createdAt"];
        $placeholders = ["?", "?", "?", "?", "0", "?", "NOW()"];
        $values = [$recipient, $title, $message, $priority, $actionUrl];
        $types = "issss";

        if ($hasType) {
            $fields[] = $cols['type'];
            $placeholders[] = "?";
            $values[] = $type;
            $types .= "s";
        } elseif ($hasNotificationType) {
            $fields[] = $cols['notificationtype'];
            $placeholders[] = "?";
            $values[] = $type;
            $types .= "s";
        }
        if ($hasCategory) {
            $fields[] = $cols['category'];
            $placeholders[] = "?";
            $values[] = $category;
            $types .= "s";
        }
        if ($hasData) {
            $fields[] = $cols['data'];
            $placeholders[] = "?";
            $values[] = json_encode($data, JSON_UNESCAPED_UNICODE);
            $types .= "s";
        }
        if ($hasRelatedId) {
            $fields[] = $cols['relatedid'];
            $placeholders[] = "?";
            $values[] = $bookingId;
            $types .= "s";
        }

        $sql = "INSERT INTO notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $ins = $conn->prepare($sql);
        if (!$ins) return;
        mysqli_bind_params_by_ref($ins, $types, $values);
        @$ins->execute();
        @$ins->close();
    } catch (Throwable $e) {
        // ignore
    }
}

//   
function getGuideNotices($conn, $input) {
    //  ID  (  )
    $guideId = $input['guideId'] ?? null;
    $bookingId = $input['bookingId'] ?? ($input['booking_id'] ?? null);
    $todayOnly = ($input['todayOnly'] ?? ($input['today_only'] ?? 0));
    $todayOnly = is_numeric($todayOnly) ? intval($todayOnly) : 0;

    $accountId = get_session_account_id();
    //   bookingId (   ), bookingId    
    if (empty($accountId) && empty($guideId) && empty($bookingId)) {
        send_json_response(['success' => false, 'message' => 'Login is required.'], 401);
    }

    // ( ) booking_id    :
    // - guide :  accountId -> guides.accountId 
    // -  : bookingId bookings.guideId  guide_assignments guideId 
    if (empty($guideId) && !empty($bookingId)) {
        $bookingKey = (string)$bookingId;
        $resolved = null;

        // bookings.guideId 
        $stmt = $conn->prepare("SELECT guideId, packageId FROM bookings WHERE bookingId = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $bookingKey);
            $stmt->execute();
            $r = $stmt->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $stmt->close();
            if ($row && !empty($row['guideId'])) {
                $resolved = intval($row['guideId']);
            } else if ($row && !empty($row['packageId'])) {
                // guide_assignments fallback ( assigned)
                $pid = intval($row['packageId']);
                $ga = $conn->prepare("SELECT guideId FROM guide_assignments WHERE packageId = ? AND status IN ('assigned','in_progress') ORDER BY assignedAt DESC LIMIT 1");
                if ($ga) {
                    $ga->bind_param('i', $pid);
                    $ga->execute();
                    $rr = $ga->get_result();
                    $gr = $rr ? $rr->fetch_assoc() : null;
                    $ga->close();
                    if ($gr && !empty($gr['guideId'])) $resolved = intval($gr['guideId']);
                }
            }
        }

        // booking_guides fallback (bookingId  )
        if (empty($resolved) && table_exists($conn, 'booking_guides')) {
            $bg = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? LIMIT 1");
            if ($bg) {
                $bg->bind_param('s', $bookingKey);
                $bg->execute();
                $rr = $bg->get_result();
                $br = $rr ? $rr->fetch_assoc() : null;
                $bg->close();
                if ($br && !empty($br['guideId'])) {
                    $resolved = intval($br['guideId']);
                }
            }
        }

        if (!empty($resolved)) {
            $guideId = $resolved;
        }
    }

    if (empty($guideId)) {
        $guideId = resolve_guide_id($conn, $guideId, $accountId);
    }
    if (empty($guideId)) {
        send_json_response(['success' => false, 'message' => 'Guide ID is required'], 400);
    }

    // guide-mypage → notice-info.html  bookingId   :
    // todayOnly=1 " " bookingId      .
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

    // /    (  status='deleted' )
    try {
        $col = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        if ($col && $col->num_rows > 0) {
            $whereConditions[] = "status = 'active'";
        }
    } catch (Throwable $e) {
        // ignore
    }

    //  :   ""  
    if ($todayOnly === 1) {
        $whereConditions[] = "DATE(createdAt) = CURDATE()";
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            announcementId,
            guideId,
            bookingId,
            title,
            content,
            status,
            createdAt,
            updatedAt
        FROM guide_announcements
        $whereClause
        ORDER BY createdAt DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    mysqli_bind_params_by_ref($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notices = [];
    while ($row = $result->fetch_assoc()) {
        // / 
        $createdAt = $row['createdAt'] ?? '';
        $registrationDateTime = '';
        if ($createdAt) {
            $dateTime = new DateTime($createdAt);
            $registrationDateTime = $dateTime->format('Y-m-d H:i');
        }
        
        $notices[] = [
            'announcementId' => intval($row['announcementId']),
            'guideId' => intval($row['guideId']),
            'bookingId' => !empty($row['bookingId']) ? (string)$row['bookingId'] : null,
            'registrationDateTime' => $registrationDateTime,
            'title' => $row['title'] ?? '',
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
            'notices' => $notices
        ]
    ]);
}

//  
function createGuideNotice($conn, $input) {
    //  ID 
    $guideId = $input['guideId'] ?? null;
    $bookingId = $input['bookingId'] ?? ($input['booking_id'] ?? null);
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    
    $accountId = get_session_account_id();
    if (empty($accountId) && empty($guideId)) {
        send_json_response(['success' => false, 'message' => 'Login is required.'], 401);
    }
    $guideId = resolve_guide_id($conn, $guideId, $accountId);
    
    //   
    if (empty($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'Guide ID is required'
        ], 400);
    }
    
    if (empty($title)) {
        send_json_response([
            'success' => false,
            'message' => 'Title is required'
        ], 400);
    }
    
    if (empty($content)) {
        send_json_response([
            'success' => false,
            'message' => 'Content is required'
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
    
    // INSERT 
    $sql = "
        INSERT INTO guide_announcements (
            guideId,
            bookingId,
            title,
            content,
            status
        ) VALUES (?, ?, ?, ?, 'active')
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $bookingIdParam = $bookingId ? (string)$bookingId : null;
    
    $stmt->bind_param('isss',
        $guideId,
        $bookingIdParam,
        $title,
        $content
    );
    
    if ($stmt->execute()) {
        $announcementId = $conn->insert_id;
        $stmt->close();

        //   (   /  )
        if (!empty($bookingIdParam)) {
            notify_booking_user_guide_notice($conn, (string)$bookingIdParam, (int)$announcementId, (string)$title);
        }
        
        send_json_response([
            'success' => true,
            'message' => 'Notice registered successfully',
            'data' => [
                'announcementId' => $announcementId
            ]
        ]);
    } else {
        $stmt->close();
        throw new Exception('Failed to insert notice: ' . $conn->error);
    }
}

//  
function updateGuideNotice($conn, $input) {
    $announcementId = $input['announcementId'] ?? null;
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    
    if (empty($announcementId)) {
        send_json_response([
            'success' => false,
            'message' => 'Announcement ID is required'
        ], 400);
    }
    
    if (empty($title)) {
        send_json_response([
            'success' => false,
            'message' => 'Title is required'
        ], 400);
    }
    
    if (empty($content)) {
        send_json_response([
            'success' => false,
            'message' => 'Content is required'
        ], 400);
    }
    
    //  ID  (    )
    $guideId = $input['guideId'] ?? null;
    $accountId = get_session_account_id();
    if (empty($accountId) && empty($guideId)) {
        send_json_response(['success' => false, 'message' => 'Login is required.'], 401);
    }
    $guideId = resolve_guide_id($conn, $guideId, $accountId);
    
    //    
    $checkStmt = $conn->prepare("SELECT guideId FROM guide_announcements WHERE announcementId = ?");
    $checkStmt->bind_param('i', $announcementId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        send_json_response([
            'success' => false,
            'message' => 'Notice not found'
        ], 404);
    }
    
    $noticeRow = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($guideId && intval($noticeRow['guideId']) !== intval($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'You do not have permission to update this notice'
        ], 403);
    }
    
    // UPDATE 
    $updateStmt = $conn->prepare("UPDATE guide_announcements SET title = ?, content = ?, updatedAt = NOW() WHERE announcementId = ?");
    $updateStmt->bind_param('ssi', $title, $content, $announcementId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        send_json_response([
            'success' => true,
            'message' => 'Notice updated successfully'
        ]);
    } else {
        $updateStmt->close();
        throw new Exception('Failed to update notice: ' . $conn->error);
    }
}

//  
function deleteGuideNotice($conn, $input) {
    $announcementId = $input['announcementId'] ?? null;
    
    if (empty($announcementId)) {
        send_json_response([
            'success' => false,
            'message' => 'Announcement ID is required'
        ], 400);
    }
    
    //  ID  (    )
    $guideId = $input['guideId'] ?? null;
    $accountId = get_session_account_id();
    if (empty($accountId) && empty($guideId)) {
        send_json_response(['success' => false, 'message' => 'Login is required.'], 401);
    }
    $guideId = resolve_guide_id($conn, $guideId, $accountId);
    
    //    
    $checkStmt = $conn->prepare("SELECT guideId FROM guide_announcements WHERE announcementId = ?");
    $checkStmt->bind_param('i', $announcementId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        send_json_response([
            'success' => false,
            'message' => 'Notice not found'
        ], 404);
    }
    
    $noticeRow = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($guideId && intval($noticeRow['guideId']) !== intval($guideId)) {
        send_json_response([
            'success' => false,
            'message' => 'You do not have permission to delete this notice'
        ], 403);
    }
    
    //  : hard delete  soft delete(status='deleted')
    $hasStatus = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        $hasStatus = ($col && $col->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    if ($hasStatus) {
        $upd = $conn->prepare("UPDATE guide_announcements SET status = 'deleted', deletedAt = NOW(), updatedAt = NOW() WHERE announcementId = ?");
        $upd->bind_param('i', $announcementId);
        if ($upd->execute()) {
            $upd->close();
            send_json_response(['success' => true, 'message' => 'Notice deleted successfully']);
        }
        $upd->close();
        throw new Exception('Failed to delete notice: ' . $conn->error);
    }

    //  fallback
    $deleteStmt = $conn->prepare("DELETE FROM guide_announcements WHERE announcementId = ?");
    $deleteStmt->bind_param('i', $announcementId);
    if ($deleteStmt->execute()) {
        $deleteStmt->close();
        send_json_response(['success' => true, 'message' => 'Notice deleted successfully']);
    }
    $deleteStmt->close();
    throw new Exception('Failed to delete notice: ' . $conn->error);
}

// JSON  
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
