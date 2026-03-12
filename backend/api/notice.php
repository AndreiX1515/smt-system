<?php
require "../conn.php";

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetNotices();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_notice':
            handleCreateNotice($input);
            break;
        case 'get_notice':
            handleGetNotice($input);
            break;
        case 'update_notice':
            handleUpdateNotice($input);
            break;
        case 'delete_notice':
            handleDeleteNotice($input);
            break;
        case 'increment_view_count':
            handleIncrementViewCount($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

//   
function handleGetNotices() {
    global $conn;
    
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    $search = $_GET['search'] ?? '';
    
    try {
        // notices   
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notices'");
        
        if ($tableCheck->num_rows === 0) {
            //     
            sendDefaultNotices($category);
            return;
        }

        //  : legacy(notices.isActive/isImportant/attachments/createdAt) vs current(notices.status/publishedAt/isPinned/isFeatured/imageUrl)
        $isLegacy = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM notices LIKE 'isActive'");
        if ($colCheck && $colCheck->num_rows > 0) $isLegacy = true;

        // category ( notices.category enum: general/promotion/maintenance/policy/announcement)
        $categoryMap = [
            'system' => 'maintenance',
            'product' => 'announcement',
            'payment' => 'policy',
            'general' => 'general'
        ];
        $dbCategory = $category;
        if (!$isLegacy && !empty($category)) {
            $dbCategory = $categoryMap[$category] ?? $category;
        }
        
        $query = $isLegacy ? "
            SELECT 
                n.noticeId,
                n.title,
                n.content,
                n.category,
                n.priority,
                n.isImportant,
                n.viewCount,
                n.createdAt,
                n.updatedAt,
                n.attachments,
                a.username as authorName
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            WHERE n.isActive = 1
        " : "
            SELECT
                n.noticeId,
                n.title,
                n.content,
                n.category,
                n.isPinned,
                n.isFeatured,
                n.viewCount,
                n.publishedAt AS createdAt,
                n.updatedAt,
                n.imageUrl,
                n.status,
                a.username as authorName
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            WHERE n.status = 'published'
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($dbCategory)) {
            $query .= " AND n.category = ?";
            $params[] = $dbCategory;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $query .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        // SMT :
        // -    2 " "    
        // - user (/user/notice.html)   ""  ,
        //   pinned/featured     .
        if ($isLegacy) {
            $query .= " ORDER BY n.createdAt DESC LIMIT ? OFFSET ?";
        } else {
            $query .= " ORDER BY n.publishedAt DESC LIMIT ? OFFSET ?";
        }
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notices = [];
        while ($row = $result->fetch_assoc()) {
            if ($isLegacy) {
                $row['attachments'] = json_decode($row['attachments'], true) ?: [];
            } else {
                //   
                $row['attachments'] = [];
                $row['isImportant'] = !empty($row['isPinned']) ? true : false;
                $row['priority'] = !empty($row['isPinned']) ? 'high' : 'normal';
            }
            $notices[] = $row;
        }
        
        //   
        $totalCount = getTotalNoticeCount($dbCategory, $search);
        
        log_activity("Notices retrieved: category={$category}, search={$search}");
        
        send_json_response([
            'success' => true,
            'data' => [
                'notices' => $notices,
                'totalCount' => $totalCount,
                'hasMore' => ($offset + $limit) < $totalCount
            ]
        ]);
        
    } catch (Throwable $e) {
        error_log("notice.php get_notices error: " . $e->getMessage());
        log_activity("Get notices error: " . $e->getMessage());
        sendDefaultNotices($category);
    }
}

//   ()
function handleCreateNotice($input) {
    global $conn;
    
    $authorId = $input['authorId'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $category = $input['category'] ?? 'general';
    $priority = $input['priority'] ?? 'normal';
    $isImportant = $input['isImportant'] ?? false;
    $attachments = $input['attachments'] ?? [];
    
    //   
    if (empty($authorId) || empty($title) || empty($content)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    try {
        $conn->begin_transaction();
        
        //  ID 
        $noticeId = generateNoticeId();
        
        //   
        $stmt = $conn->prepare("
            INSERT INTO notices (
                noticeId, authorId, title, content, category, 
                priority, isImportant, attachments, createdAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $attachmentsJson = json_encode($attachments);
        $stmt->bind_param("sissssss", $noticeId, $authorId, $title, $content, 
                          $category, $priority, $isImportant, $attachmentsJson);
        
        if (!$stmt->execute()) {
            throw new Exception("  : " . $stmt->error);
        }
        
        $conn->commit();
        
        log_activity("Notice created: {$noticeId} by user: {$authorId}");
        
        send_json_response([
            'success' => true,
            'message' => ' .',
            'data' => [
                'noticeId' => $noticeId
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity("Create notice error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '    .'], 500);
    }
}

//  
function handleGetNotice($input) {
    global $conn;
    
    $noticeId = $input['noticeId'] ?? '';
    
    if (empty($noticeId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        // notices     
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notices'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $notices = getDefaultNoticesArray();
            foreach ($notices as $n) {
                if ((string)($n['noticeId'] ?? '') === (string)$noticeId) {
                    send_json_response(['success' => true, 'data' => $n]);
                }
            }
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }

        $isLegacy = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM notices LIKE 'isActive'");
        if ($colCheck && $colCheck->num_rows > 0) $isLegacy = true;

        $stmt = $isLegacy ? $conn->prepare("
            SELECT 
                n.*,
                a.username as authorName,
                a.emailAddress as authorEmail
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            WHERE n.noticeId = ? AND n.isActive = 1
        ") : $conn->prepare("
            SELECT
                n.noticeId,
                n.title,
                n.content,
                n.category,
                n.isPinned,
                n.isFeatured,
                n.viewCount,
                n.publishedAt AS createdAt,
                n.updatedAt,
                n.imageUrl,
                n.status,
                a.username as authorName,
                a.emailAddress as authorEmail
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            WHERE n.noticeId = ? AND n.status = 'published'
        ");
        
        $stmt->bind_param("s", $noticeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }
        
        $notice = $result->fetch_assoc();
        if ($isLegacy) {
            $notice['attachments'] = json_decode($notice['attachments'], true) ?: [];
        } else {
            $notice['attachments'] = [];
            $notice['isImportant'] = !empty($notice['isPinned']) ? true : false;
            $notice['priority'] = !empty($notice['isPinned']) ? 'high' : 'normal';
        }
        
        send_json_response([
            'success' => true,
            'data' => $notice
        ]);
        
    } catch (Throwable $e) {
        error_log("notice.php get_notice error: " . $e->getMessage());
        log_activity("Get notice error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   ()
function handleUpdateNotice($input) {
    global $conn;
    
    $noticeId = $input['noticeId'] ?? '';
    $updateData = $input['updateData'] ?? [];
    
    if (empty($noticeId) || empty($updateData)) {
        send_json_response(['success' => false, 'message' => ' ID   .'], 400);
    }
    
    try {
        $allowedFields = ['title', 'content', 'category', 'priority', 'isImportant', 'attachments'];
        $updateFields = [];
        $params = [];
        $types = '';
        
        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'attachments') {
                    $value = json_encode($value);
                }
                $updateFields[] = "{$field} = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
        
        if (empty($updateFields)) {
            send_json_response(['success' => false, 'message' => '  .'], 400);
        }
        
        $updateFields[] = "updatedAt = NOW()";
        $params[] = $noticeId;
        $types .= 's';
        
        $query = "UPDATE notices SET " . implode(', ', $updateFields) . " WHERE noticeId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            log_activity("Notice updated: {$noticeId}");
            send_json_response([
                'success' => true,
                'message' => ' .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity("Update notice error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   ()
function handleDeleteNotice($input) {
    global $conn;
    
    $noticeId = $input['noticeId'] ?? '';
    $authorId = $input['authorId'] ?? '';
    
    if (empty($noticeId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        //   (isActive = 0)
        $stmt = $conn->prepare("UPDATE notices SET isActive = 0, updatedAt = NOW() WHERE noticeId = ?");
        $stmt->bind_param("s", $noticeId);
        
        if ($stmt->execute()) {
            log_activity("Notice deleted: {$noticeId} by user: {$authorId}");
            send_json_response([
                'success' => true,
                'message' => ' .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity("Delete notice error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleIncrementViewCount($input) {
    global $conn;
    
    $noticeId = $input['noticeId'] ?? '';
    
    if (empty($noticeId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("UPDATE notices SET viewCount = viewCount + 1 WHERE noticeId = ?");
        $stmt->bind_param("s", $noticeId);
        
        if ($stmt->execute()) {
            send_json_response([
                'success' => true,
                'message' => ' .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity("Increment view count error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//     
function getTotalNoticeCount($category = '', $search = '') {
    global $conn;
    
    try {
        $isLegacy = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM notices LIKE 'isActive'");
        if ($colCheck && $colCheck->num_rows > 0) $isLegacy = true;

        $query = $isLegacy
            ? "SELECT COUNT(*) as count FROM notices WHERE isActive = 1"
            : "SELECT COUNT(*) as count FROM notices WHERE status = 'published'";
        $params = [];
        $types = '';
        
        if (!empty($category)) {
            $query .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $query .= " AND (title LIKE ? OR content LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'];
    } catch (Throwable $e) {
        return 0;
    }
}

//   
function sendDefaultNotices($category = '') {
    $notices = getDefaultNoticesArray();
    
    //  
    if (!empty($category)) {
        $notices = array_filter($notices, function($notice) use ($category) {
            return $notice['category'] === $category;
        });
    }
    
    send_json_response([
        'success' => true,
        'data' => [
            'notices' => array_values($notices),
            'totalCount' => count($notices),
            'hasMore' => false
        ]
    ]);
}

function getDefaultNoticesArray() {
    return [
        [
            'noticeId' => 'NOT20250120001',
            'title' => 'Scheduled Maintenance Notice',
            'content' => ', Smart Travel .            .\n\n : 2025 7 10()  2 ~  6\n :     \n :          \n\n    ,       . .',
            'category' => 'system',
            'priority' => 'high',
            'isImportant' => true,
            'viewCount' => 1250,
            'createdAt' => '2025-01-20 10:00:00',
            'updatedAt' => '2025-01-20 10:00:00',
            'attachments' => [],
            'authorName' => ''
        ],
        [
            'noticeId' => 'NOT20250120002',
            'title' => 'New Package Release',
            'content' => '   .     .',
            'category' => 'product',
            'priority' => 'normal',
            'isImportant' => false,
            'viewCount' => 850,
            'createdAt' => '2025-01-19 15:30:00',
            'updatedAt' => '2025-01-19 15:30:00',
            'attachments' => [],
            'authorName' => ''
        ],
        [
            'noticeId' => 'NOT20250120003',
            'title' => 'Payment Method Update',
            'content' => '  .    .',
            'category' => 'payment',
            'priority' => 'normal',
            'isImportant' => false,
            'viewCount' => 650,
            'createdAt' => '2025-01-18 09:15:00',
            'updatedAt' => '2025-01-18 09:15:00',
            'attachments' => [],
            'authorName' => ''
        ]
    ];
}

//  ID 
function generateNoticeId() {
    $prefix = 'NOT';
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}
?>
