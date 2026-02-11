<?php
require __DIR__ . "/../conn.php";
require __DIR__ . "/../i18n_helper.php";
require_once __DIR__ . "/../lib/push.php";

// PHP 8+ bind_param    
if (!function_exists('mysqli_bind_params_by_ref')) {
    function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array &$params): void {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

function notifications_table_exists(): bool {
    global $conn;
    $r = $conn->query("SHOW TABLES LIKE 'notifications'");
    return ($r && $r->num_rows > 0);
}

function notifications_has_column(array $cols, string $colLower): bool {
    return isset($cols[strtolower($colLower)]);
}

function notifications_insert(array $cols, int $accountId, string $title, string $message, string $type, string $category, string $priority, string $actionUrl, array $data = []): void {
    global $conn;

    if (!notifications_table_exists()) return;

    $hasType = notifications_has_column($cols, 'type');
    $hasNotificationType = notifications_has_column($cols, 'notificationtype');
    $hasCategory = notifications_has_column($cols, 'category');
    $hasData = notifications_has_column($cols, 'data');
    $hasRelatedId = notifications_has_column($cols, 'relatedid');

    $fields = ["accountId", "title", "message"];
    $placeholders = ["?", "?", "?"];
    $values = [$accountId, $title, $message];
    $typesStr = "iss";

    if ($hasType) {
        $fields[] = $cols['type'];
        $placeholders[] = "?";
        $values[] = $type;
        $typesStr .= "s";
    } elseif ($hasNotificationType) {
        $fields[] = $cols['notificationtype'];
        $placeholders[] = "?";
        $values[] = $type;
        $typesStr .= "s";
    }

    if ($hasCategory) {
        $fields[] = $cols['category'];
        $placeholders[] = "?";
        $values[] = $category;
        $typesStr .= "s";
    }

    $fields[] = "priority";
    $placeholders[] = "?";
    $values[] = $priority;
    $typesStr .= "s";

    $fields[] = "isRead";
    $placeholders[] = "0";

    $fields[] = "actionUrl";
    $placeholders[] = "?";
    $values[] = $actionUrl;
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
        // relatedId   id(/)
        $values[] = (string)($data['bookingId'] ?? ($data['applicationId'] ?? ($data['visaApplicationId'] ?? ($data['relatedId'] ?? ''))));
        $typesStr .= "s";
    }

    $fields[] = "createdAt";
    $placeholders[] = "NOW()";

    $sql = "INSERT INTO notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    mysqli_bind_params_by_ref($stmt, $typesStr, $values);
    @$stmt->execute();
    @$stmt->close();

    //   :    push_queue (   /FCM )
    try {
        $dataArr = is_array($data) ? $data : [];
        push_enqueue_for_account($conn, $accountId, $title, $message, $actionUrl, [
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'data' => $dataArr,
        ]);
    } catch (Throwable $_) {}
}

//  :   7 (1~7 )    
function ensure_departure_reminders(int $accountId): void {
    global $conn;
    if ($accountId <= 0) return;
    if (!notifications_table_exists()) return;

    $cols = notifications_table_columns();

    // bookings   
    $tb = $conn->query("SHOW TABLES LIKE 'bookings'");
    if (!$tb || $tb->num_rows === 0) return;

    //   D-7 ~ D-1, bookingStatus confirmed
    $st = $conn->prepare("
        SELECT bookingId, packageId, packageName, departureDate
        FROM bookings
        WHERE (accountId = ? OR customerAccountId = ?)
          AND bookingStatus = 'confirmed'
          AND departureDate IS NOT NULL
          AND DATEDIFF(DATE(departureDate), CURDATE()) BETWEEN 1 AND 7
        ORDER BY departureDate ASC
        LIMIT 50
    ");
    if (!$st) return;
    $st->bind_param('ii', $accountId, $accountId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    $st->close();
    if (empty($rows)) return;

    $hasData = isset($cols['data']);
    $hasType = isset($cols['type']) || isset($cols['notificationtype']);

    foreach ($rows as $b) {
        $bookingId = (string)($b['bookingId'] ?? '');
        if ($bookingId === '') continue;
        $dep = (string)($b['departureDate'] ?? '');
        if ($dep === '') continue;

        // daysRemaining 
        $daysRemaining = null;
        try {
            $d0 = new DateTime(date('Y-m-d'));
            $d1 = new DateTime(substr($dep, 0, 10));
            $daysRemaining = (int)$d0->diff($d1)->format('%r%a');
        } catch (Throwable $e) {
            $daysRemaining = null;
        }
        if (!is_int($daysRemaining) || $daysRemaining < 1 || $daysRemaining > 7) continue;

        //  :  bookingId     departure reminder  skip
        $exists = false;
        if ($hasData) {
            $like1 = '%"statusKey":"departure_reminder"%';
            $like2 = '%"bookingId":"' . $conn->real_escape_string($bookingId) . '"%';
            $like3 = '%"daysRemaining":' . (int)$daysRemaining . '%';
            $q = "
                SELECT 1
                FROM notifications
                WHERE accountId = ?
                  AND DATE(createdAt) = CURDATE()
                  AND data LIKE ?
                  AND data LIKE ?
                  AND data LIKE ?
                LIMIT 1
            ";
            $c = $conn->prepare($q);
            if ($c) {
                $c->bind_param('isss', $accountId, $like1, $like2, $like3);
                $c->execute();
                $r = $c->get_result();
                $exists = ($r && $r->num_rows > 0);
                $c->close();
            }
        } else {
            $q = "
                SELECT 1
                FROM notifications
                WHERE accountId = ?
                  AND DATE(createdAt) = CURDATE()
                  AND title = 'Departure Reminder'
                  AND actionUrl LIKE ?
                LIMIT 1
            ";
            $like = '%reservation-detail.php?id=' . $conn->real_escape_string($bookingId) . '%';
            $c = $conn->prepare($q);
            if ($c) {
                $c->bind_param('is', $accountId, $like);
                $c->execute();
                $r = $c->get_result();
                $exists = ($r && $r->num_rows > 0);
                $c->close();
            }
        }
        if ($exists) continue;

        $title = 'Departure Reminder';
        $message = "The product's departure date is {$daysRemaining} day away.";
        // notifications.notificationType enum reminder  booking  
        $type = 'booking';
        $category = 'reservation_schedule';
        $priority = 'high';
        $actionUrl = "reservation-detail.php?id=" . rawurlencode($bookingId);
        $data = [
            'statusKey' => 'departure_reminder',
            'bookingId' => $bookingId,
            'packageId' => isset($b['packageId']) ? (int)$b['packageId'] : null,
            'packageName' => (string)($b['packageName'] ?? ''),
            'departureDate' => substr($dep, 0, 10),
            'daysRemaining' => $daysRemaining,
        ];

        notifications_insert($cols, $accountId, $title, $message, $type, $category, $priority, $actionUrl, $data);
    }
}

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetNotifications();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            handleGetNotifications($input);
            break;
        case 'mark_as_read':
            handleMarkAsRead($input);
            break;
        case 'mark_all_as_read':
            handleMarkAllAsRead($input);
            break;
        case 'get_unread_count':
            handleGetUnreadCount($input);
            break;
        case 'create_notification':
            handleCreateNotification($input);
            break;
        case 'register_device_token':
            handleRegisterDeviceToken($input);
            break;
        case 'unregister_device_token':
            handleUnregisterDeviceToken($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

function notifications_table_columns() {
    global $conn;
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM notifications");
    while ($r && ($row = $r->fetch_assoc())) {
        $cols[strtolower($row['Field'])] = $row['Field'];
    }
    return $cols;
}

function normalize_notification_row($row, $cols) {
    $type = $row['type'] ?? null;
    if (!$type && isset($cols['notificationtype'])) {
        $k = $cols['notificationtype'];
        if (isset($row[$k])) $type = $row[$k];
    }
    $type = $type ?: 'general';

    $category = $row['category'] ?? '';
    if (!$category) {
        if (in_array($type, ['booking', 'payment'], true)) $category = 'reservation_schedule';
        elseif ($type === 'visa') $category = 'visa';
        else $category = 'general';
    }

    $dataRaw = $row['data'] ?? '';
    $data = [];
    if (is_string($dataRaw) && $dataRaw !== '') {
        $parsed = json_decode($dataRaw, true);
        if (is_array($parsed)) $data = $parsed;
    } elseif (is_array($dataRaw)) {
        $data = $dataRaw;
    }

    $relatedId = $row['relatedId'] ?? $row['relatedid'] ?? null;
    if (!$relatedId) {
        if (isset($data['relatedId'])) $relatedId = $data['relatedId'];
        elseif (isset($data['bookingId'])) $relatedId = $data['bookingId'];
        elseif (isset($data['booking_id'])) $relatedId = $data['booking_id'];
        elseif (isset($data['visaApplicationId'])) $relatedId = $data['visaApplicationId'];
        elseif (isset($data['applicationId'])) $relatedId = $data['applicationId'];
    }
    // notifications  relatedId/data     actionUrl id  
    if (!$relatedId) {
        $actionUrl = $row['actionUrl'] ?? '';
        if (is_string($actionUrl) && $actionUrl !== '') {
            // : reservation-detail.php?id=BK2025...
            if (preg_match('/[?&]id=([^&#]+)/', $actionUrl, $m)) {
                $relatedId = urldecode($m[1]);
            }
        }
    }

    return [
        'notificationId' => $row['notificationId'] ?? null,
        'title' => $row['title'] ?? '',
        'message' => $row['message'] ?? '',
        'type' => $type,
        'category' => $category,
        'priority' => $row['priority'] ?? 'medium',
        'isRead' => $row['isRead'] ?? 0,
        'createdAt' => $row['createdAt'] ?? null,
        'readAt' => $row['readAt'] ?? null,
        'data' => $data,
        'actionUrl' => $row['actionUrl'] ?? '',
        'relatedId' => $relatedId
    ];
}

//   
function handleGetNotifications($input = []) {
    global $conn;
    
    $userId = $_GET['userId'] ?? $input['userId'] ?? '';
    $category = $_GET['category'] ?? $input['category'] ?? '';
    $limit = $_GET['limit'] ?? $input['limit'] ?? 20;
    $offset = $_GET['offset'] ?? $input['offset'] ?? 0;
    $unreadOnly = $_GET['unreadOnly'] ?? $input['unreadOnly'] ?? false;
    $lang = $_GET['lang'] ?? $input['lang'] ?? getCurrentLanguage();
    
    if (empty($userId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        //  :      (  )
        ensure_departure_reminders((int)$userId);
        //    :       (   )
        ensure_booking_status_notifications((int)$userId);

        // notifications   
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
        
        if ($tableCheck->num_rows === 0) {
            //     
            sendDefaultNotifications($category, $lang);
            return;
        }
        
        $cols = notifications_table_columns();
        $hasType = isset($cols['type']);
        $hasCategory = isset($cols['category']);
        $hasData = isset($cols['data']);
        $hasRelatedId = isset($cols['relatedid']);
        $hasNotificationType = isset($cols['notificationtype']);

        $selectType = $hasType ? "n.{$cols['type']} AS type" : ($hasNotificationType ? "n.{$cols['notificationtype']} AS type" : "'general' AS type");
        $selectCategory = $hasCategory ? "n.{$cols['category']} AS category" : "'' AS category";
        $selectData = $hasData ? "n.{$cols['data']} AS data" : "'' AS data";
        $selectRelated = $hasRelatedId ? ", n.{$cols['relatedid']} AS relatedId" : "";

        $query = "
            SELECT 
                n.notificationId,
                n.title,
                n.message,
                {$selectType},
                {$selectCategory},
                n.priority,
                n.isRead,
                n.createdAt,
                n.readAt,
                {$selectData},
                n.actionUrl
                {$selectRelated}
            FROM notifications n
            WHERE n.accountId = ?
        ";
        
        $params = [$userId];
        $types = 'i';
        
        if (!empty($category) && $hasCategory) {
            $query .= " AND n.{$cols['category']} = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        if ($unreadOnly) {
            $query .= " AND n.isRead = 0";
        }
        
        $query .= " ORDER BY n.priority DESC, n.createdAt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = normalize_notification_row($row, $cols);
        }
        
        //     
        $unreadCount = getUnreadCount($userId, $category);
        
        log_activity($userId, "notifications_retrieved", "Notifications retrieved");
        
        send_json_response([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
                'totalCount' => count($notifications)
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity($userId, "notifications_error", $e->getMessage());
        sendDefaultNotifications($category, $lang);
    }
}

//       (   )
function ensure_booking_status_notifications(int $accountId): void {
    global $conn;
    if ($accountId <= 0) return;
    if (!notifications_table_exists()) return;

    // bookings   
    $tb = $conn->query("SHOW TABLES LIKE 'bookings'");
    if (!$tb || $tb->num_rows === 0) return;

    $ncols = notifications_table_columns();
    $hasData = isset($ncols['data']);

    //  200 (  )
    $sql = "
        SELECT bookingId, packageId, packageName, departureDate
             , bookingStatus
             , paymentStatus
             , COALESCE(updatedAt, createdAt) AS touchedAt
        FROM bookings
        WHERE (accountId = ? OR customerAccountId = ?)
        ORDER BY touchedAt DESC
        LIMIT 200
    ";
    $st = $conn->prepare($sql);
    if (!$st) return;
    $st->bind_param('ii', $accountId, $accountId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    $st->close();
    if (empty($rows)) return;

    foreach ($rows as $b) {
        $bookingId = (string)($b['bookingId'] ?? '');
        if ($bookingId === '') continue;

        $raw = trim((string)($b['bookingStatus'] ?? ''));
        if ($raw === '') $raw = trim((string)($b['paymentStatus'] ?? ''));
        if ($raw === '') continue;

        //  /  ( )
        $statusKey = strtolower($raw);

        //  :  bookingId + statusKey   skip
        $exists = false;
        if ($hasData) {
            $like1 = '%"bookingId":"' . $conn->real_escape_string($bookingId) . '"%';
            $like2 = '%"statusKey":"' . $conn->real_escape_string($statusKey) . '"%';
            $q = "SELECT 1 FROM notifications WHERE accountId = ? AND data LIKE ? AND data LIKE ? LIMIT 1";
            $c = $conn->prepare($q);
            if ($c) {
                $c->bind_param('iss', $accountId, $like1, $like2);
                $c->execute();
                $r = $c->get_result();
                $exists = ($r && $r->num_rows > 0);
                $c->close();
            }
        } else {
            $q = "SELECT 1 FROM notifications WHERE accountId = ? AND actionUrl LIKE ? AND title LIKE 'Reservation%' LIMIT 1";
            $like = '%reservation-detail.php?id=' . $conn->real_escape_string($bookingId) . '%';
            $c = $conn->prepare($q);
            if ($c) {
                $c->bind_param('is', $accountId, $like);
                $c->execute();
                $r = $c->get_result();
                $exists = ($r && $r->num_rows > 0);
                $c->close();
            }
        }
        if ($exists) continue;

        //   (clientType) ( B2B/B2C )
        $clientType = '';
        try {
            $ct = $conn->prepare("SELECT LOWER(COALESCE(clientType,'')) AS clientType FROM client WHERE accountId = ? LIMIT 1");
            if ($ct) {
                $ct->bind_param('i', $accountId);
                $ct->execute();
                $row = $ct->get_result()->fetch_assoc();
                $ct->close();
                $clientType = (string)($row['clientType'] ?? '');
            }
        } catch (Throwable $e) { $clientType = ''; }

        $title = 'Reservation status changed';
        $message = "The product's reservation status has been changed.";
        $type = 'booking';
        $category = 'reservation_schedule';
        $priority = 'medium';
        $actionUrl = "reservation-detail.php?id=" . rawurlencode($bookingId);
        $data = [
            'statusKey' => $statusKey,
            'bookingId' => $bookingId,
            'packageId' => isset($b['packageId']) ? (int)$b['packageId'] : null,
            'packageName' => (string)($b['packageName'] ?? ''),
            'departureDate' => substr((string)($b['departureDate'] ?? ''), 0, 10),
            'bookingStatus' => (string)($b['bookingStatus'] ?? ''),
            'paymentStatus' => (string)($b['paymentStatus'] ?? ''),
        ];
        if ($clientType !== '') $data['clientType'] = $clientType;

        notifications_insert($ncols, $accountId, $title, $message, $type, $category, $priority, $actionUrl, $data);
    }
}

//   
function handleMarkAsRead($input) {
    global $conn;
    
    $userId = $input['userId'] ?? '';
    $notificationId = $input['notificationId'] ?? '';
    
    if (empty($userId) || empty($notificationId)) {
        send_json_response(['success' => false, 'message' => ' ID  ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET isRead = 1, readAt = NOW() 
            WHERE notificationId = ? AND accountId = ?
        ");
        $stmt->bind_param("ii", $notificationId, $userId);
        
        if ($stmt->execute()) {
            log_activity($userId, "notification_marked_read", "Notification {$notificationId} marked as read");
            send_json_response([
                'success' => true,
                'message' => '  .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($userId, "notification_read_error", $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//    
function handleMarkAllAsRead($input) {
    global $conn;
    
    $userId = $input['userId'] ?? '';
    $category = $input['category'] ?? '';
    
    if (empty($userId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $cols = notifications_table_columns();
        $hasCategory = isset($cols['category']);

        $query = "UPDATE notifications SET isRead = 1, readAt = NOW() WHERE accountId = ?";
        $params = [$userId];
        $types = 'i';
        
        if (!empty($category) && $hasCategory) {
            $query .= " AND {$cols['category']} = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        $stmt = $conn->prepare($query);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        
        if ($stmt->execute()) {
            log_activity($userId, "all_notifications_read", "All notifications marked as read");
            send_json_response([
                'success' => true,
                'message' => '   .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($userId, "all_notifications_read_error", $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//     
function handleGetUnreadCount($input) {
    global $conn;
    
    $userId = $input['userId'] ?? '';
    $category = $input['category'] ?? '';
    
    if (empty($userId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $unreadCount = getUnreadCount($userId, $category);
        
        send_json_response([
            'success' => true,
            'data' => [
                'unreadCount' => $unreadCount
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity($userId, "unread_count_error", $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleCreateNotification($input) {
    global $conn;
    
    $userId = $input['userId'] ?? '';
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    $type = $input['type'] ?? ($input['notificationType'] ?? 'general');
    $category = $input['category'] ?? '';
    $priority = $input['priority'] ?? 'medium';
    $actionUrl = $input['actionUrl'] ?? '';
    $data = $input['data'] ?? [];
    $relatedId = $input['relatedId'] ?? null;
    
    if (empty($userId) || empty($title) || empty($message)) {
        send_json_response(['success' => false, 'message' => ' ID, ,  .'], 400);
    }
    
    try {
        $cols = notifications_table_columns();
        $hasType = isset($cols['type']);
        $hasCategory = isset($cols['category']);
        $hasData = isset($cols['data']);
        $hasRelatedId = isset($cols['relatedid']);
        $hasNotificationType = isset($cols['notificationtype']);

        // data/relatedId      actionUrl  
        if ((!is_string($actionUrl) || trim($actionUrl) === '') && $relatedId) {
            $rid = (string)$relatedId;
            $statusKey = '';
            if (is_array($data) && isset($data['statusKey']) && is_string($data['statusKey'])) {
                $statusKey = $data['statusKey'];
            }
            $qs = 'id=' . rawurlencode($rid);
            if ($statusKey !== '') $qs .= '&statusKey=' . rawurlencode($statusKey);

            if (in_array($type, ['booking', 'payment'], true)) {
                $actionUrl = "reservation-detail.php?$qs";
            } elseif ($type === 'visa') {
                // statusKey     
                $actionUrl = "visa-detail-completion.php?$qs";
            }
        }

        $fields = ["accountId", "title", "message"];
        $placeholders = ["?", "?", "?"];
        $values = [$userId, $title, $message];
        $typesStr = "iss";

        if ($hasType) {
            $fields[] = $cols['type'];
            $placeholders[] = "?";
            $values[] = $type;
            $typesStr .= "s";
        } elseif ($hasNotificationType) {
            $fields[] = $cols['notificationtype'];
            $placeholders[] = "?";
            $values[] = $type;
            $typesStr .= "s";
        }

        if ($hasCategory) {
            $fields[] = $cols['category'];
            $placeholders[] = "?";
            $values[] = $category;
            $typesStr .= "s";
        }

        $fields[] = "priority";
        $placeholders[] = "?";
        $values[] = $priority;
        $typesStr .= "s";

        $fields[] = "isRead";
        $placeholders[] = "0";

        $fields[] = "actionUrl";
        $placeholders[] = "?";
        $values[] = $actionUrl;
        $typesStr .= "s";

        if ($hasData) {
            $fields[] = $cols['data'];
            $placeholders[] = "?";
            $values[] = json_encode($data);
            $typesStr .= "s";
        }
        if ($hasRelatedId) {
            $fields[] = $cols['relatedid'];
            $placeholders[] = "?";
            $values[] = $relatedId;
            $typesStr .= "s";
        }

        $fields[] = "createdAt";
        $placeholders[] = "NOW()";

        $sql = "INSERT INTO notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }
        mysqli_bind_params_by_ref($stmt, $typesStr, $values);
        
        if ($stmt->execute()) {
            $notificationId = $conn->insert_id;
            
            log_activity($userId, "notification_created", "Notification {$notificationId} created");

            //   : push_queue 
            try {
                push_enqueue_for_account($conn, (int)$userId, (string)$title, (string)$message, (string)$actionUrl, [
                    'type' => (string)$type,
                    'category' => (string)$category,
                    'priority' => (string)$priority,
                    'relatedId' => $relatedId,
                    'data' => is_array($data) ? $data : [],
                ]);
            } catch (Throwable $_) {}
            
            send_json_response([
                'success' => true,
                'message' => ' .',
                'data' => [
                    'notificationId' => $notificationId
                ]
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($userId, "notification_create_error", $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  :   /
function handleRegisterDeviceToken($input) {
    global $conn;
    $userId = (int)($input['userId'] ?? 0);
    $deviceToken = (string)($input['deviceToken'] ?? '');
    $platform = (string)($input['platform'] ?? ($input['devicePlatform'] ?? 'unknown'));

    if ($userId <= 0 || trim($deviceToken) === '') {
        send_json_response(['success' => false, 'message' => 'userId and deviceToken are required.'], 400);
    }

    $res = push_register_device_token($conn, $userId, $deviceToken, $platform);
    if (!($res['ok'] ?? false)) {
        send_json_response(['success' => false, 'message' => 'Failed to register device token.'], 500);
    }
    send_json_response(['success' => true, 'message' => 'Device token registered.']);
}

function handleUnregisterDeviceToken($input) {
    global $conn;
    $userId = (int)($input['userId'] ?? 0);
    $deviceToken = (string)($input['deviceToken'] ?? '');

    if ($userId <= 0 || trim($deviceToken) === '') {
        send_json_response(['success' => false, 'message' => 'userId and deviceToken are required.'], 400);
    }

    $res = push_unregister_device_token($conn, $userId, $deviceToken);
    if (!($res['ok'] ?? false)) {
        send_json_response(['success' => false, 'message' => 'Failed to unregister device token.'], 500);
    }
    send_json_response(['success' => true, 'message' => 'Device token unregistered.']);
}

//       
function getUnreadCount($userId, $category = '') {
    global $conn;
    
    try {
        $cols = notifications_table_columns();
        $hasCategory = isset($cols['category']);
        $query = "SELECT COUNT(*) as count FROM notifications WHERE accountId = ? AND isRead = 0";
        $params = [$userId];
        $types = 'i';
        
        if (!empty($category) && $hasCategory) {
            $query .= " AND {$cols['category']} = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        $stmt = $conn->prepare($query);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        return 0;
    }
}

//   
function sendDefaultNotifications($category = '', $lang = null) {
    //   
    if ($lang === null) {
        $lang = $_GET['lang'] ?? $_POST['lang'] ?? getCurrentLanguage();
    }
    
    //   packageId 
    global $conn;
    $packageId = 136; // 
    $packageCheck = $conn->query("SELECT packageId FROM packages LIMIT 1");
    if ($packageCheck && $packageCheck->num_rows > 0) {
        $packageRow = $packageCheck->fetch_assoc();
        $packageId = $packageRow['packageId'];
    }
    
    $notifications = [
        [
            'notificationId' => 1,
            'title' => getI18nText('departureReminder', $lang) ?: 'Departure Reminder',
            'message' => getI18nText('departureReminderMessage', $lang) ?: '  1 .',
            'type' => 'reminder',
            'category' => 'reservation_schedule',
            'priority' => 'high',
            'isRead' => false,
            'createdAt' => '2025-01-20 10:00:00',
            'readAt' => null,
            'data' => json_encode(['packageId' => $packageId, 'productId' => $packageId]),
            'actionUrl' => "product-detail.php?id={$packageId}"
        ],
        [
            'notificationId' => 2,
            'title' => getI18nText('paymentConfirmation', $lang) ?: 'Payment Confirmation',
            'message' => getI18nText('paymentConfirmationMessage', $lang) ?: ' .',
            'type' => 'confirmation',
            'category' => 'reservation_schedule',
            'priority' => 'medium',
            'isRead' => false,
            'createdAt' => '2025-01-19 15:30:00',
            'readAt' => null,
            'data' => [],
            'actionUrl' => 'reservation-detail.php'
        ],
        [
            'notificationId' => 3,
            'title' => getI18nText('visaApplicationUpdate', $lang) ?: 'Visa Application Update',
            'message' => getI18nText('visaApplicationApproved', $lang) ?: '  .',
            'type' => 'update',
            'category' => 'visa',
            'priority' => 'high',
            'isRead' => false,
            'createdAt' => '2025-01-18 09:15:00',
            'readAt' => null,
            'data' => [],
            'actionUrl' => 'visa-detail-completion.php'
        ]
    ];
    
    //  
    if (!empty($category)) {
        $notifications = array_filter($notifications, function($notification) use ($category) {
            return $notification['category'] === $category;
        });
    }
    
    $unreadCount = count(array_filter($notifications, function($notification) {
        return !$notification['isRead'];
    }));
    
    send_json_response([
        'success' => true,
        'data' => [
            'notifications' => array_values($notifications),
            'unreadCount' => $unreadCount,
            'totalCount' => count($notifications)
        ]
    ]);
}
?>