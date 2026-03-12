<?php
require "../conn.php";

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetInquiries();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON + multipart(FormData)  
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $rawBody = file_get_contents('php://input');

    $input = null;
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode($rawBody, true);
    } else {
        // multipart/form-data  x-www-form-urlencoded
        $input = $_POST;
    }

    if (!is_array($input)) {
        $input = [];
    }

    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_inquiry':
            handleCreateInquiry($input);
            break;
        case 'get_inquiry':
            handleGetInquiry($input);
            break;
        case 'update_inquiry':
            handleUpdateInquiry($input);
            break;
        case 'delete_inquiry':
            handleDeleteInquiry($input);
            break;
        case 'reply_inquiry':
            handleReplyInquiry($input);
            break;
        case 'get_inquiry_replies':
            handleGetInquiryReplies($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

function ensureInquiryAttachmentsTable() {
    global $conn;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
    if ($tableCheck && $tableCheck->num_rows > 0) return;

    $sql = "
        CREATE TABLE IF NOT EXISTS inquiry_attachments (
            attachmentId INT AUTO_INCREMENT PRIMARY KEY,
            inquiryId INT NOT NULL,
            fileName VARCHAR(255) NOT NULL,
            filePath VARCHAR(500) NOT NULL,
            fileSize INT DEFAULT NULL,
            fileType VARCHAR(50) DEFAULT NULL,
            uploadedBy INT NOT NULL,
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_inquiryId (inquiryId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($sql);
}

function inquiryAttachmentsColumns() {
    global $conn;
    $defaults = [
        'attachmentId' => 'attachmentId',
        'inquiryId' => 'inquiryId',
        'fileName' => 'fileName',
        'filePath' => 'filePath',
        'fileSize' => 'fileSize',
        'fileType' => 'fileType',
        'uploadedBy' => 'uploadedBy',
        'createdAt' => 'createdAt',
    ];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return $defaults;

    $colsRes = $conn->query("SHOW COLUMNS FROM inquiry_attachments");
    if (!$colsRes) return $defaults;

    $present = [];
    while ($r = $colsRes->fetch_assoc()) {
        $present[strtolower($r['Field'])] = $r['Field'];
    }

    //    (  )
    $defaults['attachmentId'] = $present['attachmentid'] ?? $defaults['attachmentId'];
    $defaults['inquiryId'] = $present['inquiryid'] ?? $defaults['inquiryId'];
    $defaults['fileName'] = $present['filename'] ?? ($present['originalname'] ?? $defaults['fileName']);
    $defaults['filePath'] = $present['filepath'] ?? $defaults['filePath'];
    $defaults['fileSize'] = $present['filesize'] ?? $defaults['fileSize'];
    $defaults['fileType'] = $present['filetype'] ?? ($present['mimetype'] ?? $defaults['fileType']);
    $defaults['uploadedBy'] = $present['uploadedby'] ?? ($present['accountid'] ?? $defaults['uploadedBy']);
    $defaults['createdAt'] = $present['createdat'] ?? $defaults['createdAt'];
    return $defaults;
}

function normalizeUploadedFiles($filesSpec) {
    // <input name="files[]">  
    if (!$filesSpec || !isset($filesSpec['name'])) return [];
    $out = [];

    if (is_array($filesSpec['name'])) {
        $n = count($filesSpec['name']);
        for ($i = 0; $i < $n; $i++) {
            $out[] = [
                'name' => $filesSpec['name'][$i] ?? '',
                'type' => $filesSpec['type'][$i] ?? '',
                'tmp_name' => $filesSpec['tmp_name'][$i] ?? '',
                'error' => $filesSpec['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesSpec['size'][$i] ?? 0,
            ];
        }
    } else {
        $out[] = [
            'name' => $filesSpec['name'] ?? '',
            'type' => $filesSpec['type'] ?? '',
            'tmp_name' => $filesSpec['tmp_name'] ?? '',
            'error' => $filesSpec['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $filesSpec['size'] ?? 0,
        ];
    }
    return $out;
}

function getInquiryAttachments($inquiryId) {
    global $conn;
    ensureInquiryAttachmentsTable();

    $c = inquiryAttachmentsColumns();
    $stmt = $conn->prepare(
        "SELECT {$c['attachmentId']} AS attachmentId,
                {$c['fileName']} AS fileName,
                {$c['filePath']} AS filePath,
                {$c['fileType']} AS fileType,
                {$c['fileSize']} AS fileSize,
                {$c['createdAt']} AS createdAt
         FROM inquiry_attachments
         WHERE {$c['inquiryId']} = ?
         ORDER BY {$c['attachmentId']} ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $inquiryId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $path = $r['filePath'] ?? '';
        $rows[] = [
            'attachmentId' => intval($r['attachmentId']),
            'name' => $r['fileName'],
            'url' => ($path ? ('/' . ltrim($path, '/')) : ''),
            'mimeType' => $r['fileType'],
            'size' => isset($r['fileSize']) ? intval($r['fileSize']) : null,
            'createdAt' => $r['createdAt'],
        ];
    }
    $stmt->close();
    return $rows;
}

//   
function handleGetInquiries() {
    global $conn;
    
    $accountId = $_GET['accountId'] ?? '';
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    if (empty($accountId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        // inquiries   
        $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
        
        if ($tableCheck->num_rows === 0) {
            //     
            sendDefaultInquiries($status);
            return;
        }

        // DB (inquiries) :
        // - subject/content/category/priority/status(open|in_progress|resolved|closed)
        //   inquiry_replies    ((status) )
        $query = "
            SELECT 
                i.inquiryId,
                i.inquiryNo,
                i.subject,
                i.content,
                i.category,
                i.priority,
                i.status AS dbStatus,
                CASE WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN 1 ELSE 0 END AS hasReply,
                i.createdAt,
                i.updatedAt,
                c.fName,
                c.lName,
                a.emailAddress,
                c.contactNo
            FROM inquiries i
            LEFT JOIN client c ON i.accountId = c.accountId
            LEFT JOIN accounts a ON i.accountId = a.accountId
            WHERE i.accountId = ?
        ";
        
        $params = [$accountId];
        $types = 'i';
        
        if (!empty($status)) {
            // status UI(pending/replied/closed)  DB(open/in_progress/resolved/closed)   
            // - pending: (=reply ) + closed 
            // - replied:  (=reply )
            // - closed:  closed
            $st = strtolower(trim((string)$status));
            if ($st === 'pending') {
                $query .= " AND NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) AND i.status <> 'closed'";
            } elseif ($st === 'replied') {
                $query .= " AND EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($st === 'closed') {
                $query .= " AND i.status = 'closed'";
            } elseif ($st === 'open' || $st === 'in_progress' || $st === 'resolved' || $st === 'closed') {
                $query .= " AND i.status = ?";
                $params[] = $st;
                $types .= 's';
            }
        }
        
        if (!empty($category)) {
            $query .= " AND i.category = ?";
            $params[] = $category;
            $types .= 's';
        }

        //  (/ ) - LIMIT/OFFSET    COUNT
        $countSql = "SELECT COUNT(*) AS c FROM inquiries i WHERE i.accountId = ?";
        $countParams = [$accountId];
        $countTypes = 'i';
        if (!empty($status)) {
            $st = strtolower(trim((string)$status));
            if ($st === 'pending') {
                $countSql .= " AND NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) AND i.status <> 'closed'";
            } elseif ($st === 'replied') {
                $countSql .= " AND EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
            } elseif ($st === 'closed') {
                $countSql .= " AND i.status = 'closed'";
            } elseif ($st === 'open' || $st === 'in_progress' || $st === 'resolved' || $st === 'closed') {
                $countSql .= " AND i.status = ?";
                $countParams[] = $st;
                $countTypes .= 's';
            }
        }
        if (!empty($category)) {
            $countSql .= " AND i.category = ?";
            $countParams[] = $category;
            $countTypes .= 's';
        }
        $totalCount = 0;
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $countStmt->bind_param($countTypes, ...$countParams);
            $countStmt->execute();
            $row = $countStmt->get_result()->fetch_assoc();
            $totalCount = intval($row['c'] ?? 0);
            $countStmt->close();
        }
        
        $query .= " ORDER BY i.createdAt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $inquiries = [];
        while ($row = $result->fetch_assoc()) {
            //   
            $row['title'] = $row['subject'] ?? '';
            // inquiry-person.js message/subject    
            $row['message'] = $row['content'] ?? '';
            //  attachments   ().  
            $row['attachments'] = [];
            //  ( ): replied reply   , closed  
            $hasReply = intval($row['hasReply'] ?? 0) === 1;
            $db = $row['dbStatus'] ?? 'open';
            if ($db === 'closed') $row['status'] = 'closed';
            else if ($hasReply) $row['status'] = 'replied';
            else $row['status'] = 'pending';
            $inquiries[] = $row;
        }
        
        //   
        $statusCounts = getInquiryStatusCounts($accountId);
        
        log_activity($accountId, "inquiries_retrieved", "Inquiries retrieved for user: {$accountId}");
        
        send_json_response([
            'success' => true,
            'data' => [
                'inquiries' => $inquiries,
                'statusCounts' => $statusCounts,
                'totalCount' => $totalCount
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "inquiries_get_error", "Get inquiries error: " . $e->getMessage());
        sendDefaultInquiries($status);
    }
}

//  
function handleCreateInquiry($input) {
    global $conn;
    
    //   payload 
    $accountId = $input['accountId'] ?? $input['user_id'] ?? $input['userId'] ?? '';
    $title = $input['title'] ?? $input['subject'] ?? '';
    $content = $input['content'] ?? $input['message'] ?? '';
    $category = $input['category'] ?? $input['inquiry_type'] ?? 'general';
    $priority = $input['priority'] ?? 'medium';
    
    //   
    if (empty($accountId) || empty($title) || empty($content)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    //     
    $movedFiles = [];

    try {
        ensureInquiryAttachmentsTable();
        $conn->begin_transaction();
        
        // category: keep UI-selected values, allow backward-compatible aliases
        $category = strtolower(trim((string)$category));
        $uiMap = [
            // UI (inquiry-person.php)
            'product' => 'product',
            'reservation' => 'reservation',
            'booking' => 'booking',
            'payment' => 'payment',
            'cancel' => 'cancellation',
            'cancellation' => 'cancellation',
            'other' => 'other',
        ];
        $category = $uiMap[$category] ?? $category;
        // Accept both legacy categories and UI categories
        $allowedCats = ['general','product','reservation','booking','payment','cancellation','other','visa','technical','complaint','suggestion'];
        if (!in_array($category, $allowedCats, true)) $category = 'general';

        // priority enum  (normal -> medium)
        $priority = strtolower(trim((string)$priority));
        $prioMap = ['normal' => 'medium', 'low' => 'low', 'medium' => 'medium', 'high' => 'high', 'urgent' => 'urgent'];
        $priority = $prioMap[$priority] ?? 'medium';

        // inquiries / (/) 
        $cols = inquiriesColumns();

        //    (PK AUTO_INCREMENT )
        $fields = [];
        $placeholders = [];
        $types = '';
        $params = [];

        // inquiryNo ( )
        $inquiryNo = null;
        if (!empty($cols['inquiryNo'])) {
            $inquiryNo = generateInquiryNo($cols['inquiryNo']);
            $fields[] = $cols['inquiryNo'];
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $inquiryNo;
        }

        // accountId ()
        $fields[] = $cols['accountId'] ?: 'accountId';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = (int)$accountId;

        // category ( )
        if (!empty($cols['category'])) {
            $fields[] = $cols['category'];
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $category;
        }

        // subject/title ()
        $fields[] = $cols['subject'] ?: 'subject';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $title;

        // content/message ()
        $fields[] = $cols['content'] ?: 'content';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $content;

        // priority ( )
        if (!empty($cols['priority'])) {
            $fields[] = $cols['priority'];
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $priority;
        }

        // status ( )
        if (!empty($cols['status'])) {
            $defaultStatus = 'open';
            // enum     
            $statusEnumValues = [];
            $safeStatusCol = $cols['status'];
            if (preg_match('/^[a-zA-Z0-9_]+$/', $safeStatusCol)) {
                $statusColumnResult = $conn->query("SHOW COLUMNS FROM inquiries WHERE Field = '{$safeStatusCol}'");
                if ($statusColumnResult && $statusColumnResult->num_rows > 0) {
                    $statusColumn = $statusColumnResult->fetch_assoc();
                    $type = $statusColumn['Type'] ?? '';
                    if (preg_match("/^enum\\s*\\((.+)\\)$/i", $type, $matches)) {
                        $enumValues = explode(',', $matches[1]);
                        foreach ($enumValues as $val) {
                            $statusEnumValues[] = trim($val, "'\"");
                        }
                    }
                }
            }
            if (!empty($statusEnumValues)) {
                if (in_array('open', $statusEnumValues, true)) $defaultStatus = 'open';
                elseif (in_array('pending', $statusEnumValues, true)) $defaultStatus = 'pending';
                elseif (in_array('in_progress', $statusEnumValues, true)) $defaultStatus = 'in_progress';
                else $defaultStatus = $statusEnumValues[0];
            }

            $fields[] = $cols['status'];
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $input['status'] ?? $defaultStatus;
        }

        // createdAt ( )
        if (!empty($cols['createdAt'])) {
            $fields[] = $cols['createdAt'];
            $placeholders[] = 'NOW()';
        }

        $sql = "INSERT INTO inquiries (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("  (prepare): " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("  : " . $stmt->error);
        }
        $newId = $conn->insert_id;
        
        //    (multipart/form-data)
        $savedAttachments = [];
        if (isset($_FILES['files'])) {
            $cols = inquiryAttachmentsColumns();
            $files = normalizeUploadedFiles($_FILES['files']);
            $maxFiles = 5;
            $maxBytes = 10 * 1024 * 1024; // 10MB
            $allowedExts = ['jpg','jpeg','png','gif','pdf'];

            $uploadDir = __DIR__ . '/../../uploads/inquiries/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $count = 0;
            foreach ($files as $f) {
                if ($count >= $maxFiles) break;
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) continue;
                $size = intval($f['size'] ?? 0);
                if ($size <= 0 || $size > $maxBytes) continue;

                $orig = (string)($f['name'] ?? '');
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) continue;

                $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
                // random_bytes      
                $rand = bin2hex(random_bytes(6));
                $fileName = 'inq_' . date('YmdHis') . '_' . $rand . '_' . $safeBase . '.' . $ext;
                $destPath = $uploadDir . $fileName;
                if (!move_uploaded_file($f['tmp_name'], $destPath)) {
                    error_log("inquiry.php: move_uploaded_file failed for {$orig} -> {$destPath}");
                    continue;
                }
                $movedFiles[] = $destPath;

                $relPath = 'uploads/inquiries/' . $fileName;
                $mime = (string)($f['type'] ?? '');

                //  : fileName/filePath/fileType/fileSize/uploadedBy
                $ins = $conn->prepare(
                    "INSERT INTO inquiry_attachments ({$cols['inquiryId']}, {$cols['fileName']}, {$cols['filePath']}, {$cols['fileType']}, {$cols['fileSize']}, {$cols['uploadedBy']})
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                if ($ins) {
                    $uploadedBy = intval($accountId);
                    $sizeInt = intval($size);
                    $ins->bind_param('isssii', $newId, $orig, $relPath, $mime, $sizeInt, $uploadedBy);
                    $ins->execute();
                    $aid = $conn->insert_id;
                    $ins->close();

                    $savedAttachments[] = [
                        'attachmentId' => intval($aid),
                        'name' => $orig,
                        'url' => '/' . $relPath,
                        'mimeType' => $mime,
                        'size' => $size,
                    ];
                }

                $count++;
            }
        }

        $conn->commit();
        
        log_activity($accountId, "inquiry_created", "Inquiry created: {$newId} ({$inquiryNo}) for user: {$accountId}");
        
        send_json_response([
            'success' => true,
            'message' => ' .',
            'data' => [
                'inquiryId' => $newId,
                'inquiryNo' => $inquiryNo,
                'attachments' => $savedAttachments
            ]
        ]);
        
    } catch (Exception $e) {
        //      
        error_log("inquiry.php: create_inquiry failed: " . $e->getMessage());
        error_log("inquiry.php: trace: " . $e->getTraceAsString());
        if ($conn) {
            @$conn->rollback();
        }
        //     
        foreach ($movedFiles as $p) {
            if (is_string($p) && $p !== '' && file_exists($p)) {
                @unlink($p);
            }
        }
        log_activity($accountId ?? 0, "inquiry_create_error", "Create inquiry error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '    .'], 500);
    }
}

//  
function handleGetInquiry($input) {
    global $conn;
    
    $inquiryId = $input['inquiryId'] ?? '';
    $accountId = $input['accountId'] ?? '';
    
    if (empty($inquiryId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $query = "
            SELECT 
                i.*,
                c.fName,
                c.lName,
                a.emailAddress,
                c.contactNo
            FROM inquiries i
            LEFT JOIN client c ON i.accountId = c.accountId
            LEFT JOIN accounts a ON i.accountId = a.accountId
            WHERE i.inquiryId = ?
        ";
        
        $params = [$inquiryId];
        $types = 'i';
        
        if (!empty($accountId)) {
            $query .= " AND i.accountId = ?";
            $params[] = $accountId;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }
        
        $inquiry = $result->fetch_assoc();
        
        //   
        $inquiry['title'] = $inquiry['subject'] ?? '';
        $inquiry['message'] = $inquiry['content'] ?? '';
        $inquiry['attachments'] = getInquiryAttachments(intval($inquiry['inquiryId']));
        $db = $inquiry['status'] ?? 'open';
        if ($db === 'resolved') $inquiry['status'] = 'replied';
        else if ($db === 'closed') $inquiry['status'] = 'closed';
        else $inquiry['status'] = 'pending';
        
        //  
        $replies = getInquiryReplies($inquiryId);
        $inquiry['replies'] = $replies;
        
        send_json_response([
            'success' => true,
            'data' => $inquiry
        ]);
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "inquiry_get_error", "Get inquiry error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleUpdateInquiry($input) {
    global $conn;
    
    $inquiryId = $input['inquiryId'] ?? '';
    $updateData = $input['updateData'] ?? [];

    // multipart/form-data updateData JSON   
    if (is_string($updateData)) {
        $decoded = json_decode($updateData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $updateData = $decoded;
        } else {
            $updateData = [];
        }
    }

    // updateData  top-level  
    if (!is_array($updateData)) $updateData = [];
    if (empty($updateData)) {
        foreach (['subject','content','category','priority','status','title','message','inquiry_type'] as $k) {
            if (isset($input[$k])) $updateData[$k] = $input[$k];
        }
    }

    //   ( )
    $keepAttachmentIds = $input['keepAttachmentIds'] ?? null;
    if (is_string($keepAttachmentIds)) {
        $decoded = json_decode($keepAttachmentIds, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $keepAttachmentIds = $decoded;
        }
    }
    if (!is_array($keepAttachmentIds)) {
        $keepAttachmentIds = null; // null   
    }
    
    //    
    $hasNewFiles = isset($_FILES) && (isset($_FILES['files']) || isset($_FILES['files']['name']));
    if (empty($inquiryId) || (empty($updateData) && !$hasNewFiles && $keepAttachmentIds === null)) {
        send_json_response(['success' => false, 'message' => ' ID   .'], 400);
    }
    
    try {
        //  (): user session 
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionAccountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
        $sessionAccountId = is_numeric($sessionAccountId) ? intval($sessionAccountId) : null;

        //  (  )
        // - title -> subject, message -> content, inquiry_type -> category
        $allowedFields = ['subject', 'content', 'category', 'priority', 'status', 'title', 'message', 'inquiry_type'];
        $updateFields = [];
        $params = [];
        $types = '';
        
        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $col = $field;
                $val = $value;
                if ($field === 'title') { $col = 'subject'; }
                if ($field === 'message') { $col = 'content'; }
                if ($field === 'inquiry_type') { $col = 'category'; }

                if ($col === 'status') {
                    $st = strtolower(trim((string)$val));
                    // UI -> DB 
                    if ($st === 'pending') $val = 'open';
                    else if ($st === 'replied') $val = 'resolved';
                    else if ($st === 'closed') $val = 'closed';
                    else if ($st === 'open' || $st === 'in_progress' || $st === 'resolved' || $st === 'closed') $val = $st;
                    else $val = 'open';
                }

                $updateFields[] = "{$col} = ?";
                $params[] = $val;
                $types .= 's';
            }
        }

        $conn->begin_transaction();

        // 1)   (  )
        if (!empty($updateFields)) {
            $updateFields[] = "updatedAt = NOW()";
            $params2 = $params;
            $types2 = $types;

            $params2[] = $inquiryId;
            $types2 .= 'i';

            $where = "WHERE inquiryId = ?";
            if (!empty($sessionAccountId)) {
                $where .= " AND accountId = ?";
                $params2[] = $sessionAccountId;
                $types2 .= 'i';
            }

            $query = "UPDATE inquiries SET " . implode(', ', $updateFields) . " {$where}";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types2, ...$params2);
            if (!$stmt->execute()) {
                throw new Exception('  : ' . ($stmt->error ?: $conn->error));
            }
            //     0    
            if (!empty($sessionAccountId) && $stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception('   .');
            }
            $stmt->close();
        }

        // 2)  / 
        ensureInquiryAttachmentsTable();
        $c = inquiryAttachmentsColumns();
        $iid = intval($inquiryId);

        // keepAttachmentIds ,    
        if (is_array($keepAttachmentIds)) {
            $keep = array_values(array_filter(array_map('intval', $keepAttachmentIds), fn($v) => $v > 0));

            //    
            $cur = [];
            $stmtA = $conn->prepare("SELECT {$c['attachmentId']} AS attachmentId, {$c['filePath']} AS filePath FROM inquiry_attachments WHERE {$c['inquiryId']} = ?");
            if ($stmtA) {
                $stmtA->bind_param('i', $iid);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                while ($r = $resA->fetch_assoc()) $cur[] = $r;
                $stmtA->close();
            }

            $toDelete = [];
            foreach ($cur as $r) {
                $aid = intval($r['attachmentId'] ?? 0);
                if ($aid > 0 && !in_array($aid, $keep, true)) {
                    $toDelete[] = $r;
                }
            }

            if (!empty($toDelete)) {
                // DB 
                $ph = implode(',', array_fill(0, count($toDelete), '?'));
                $delSql = "DELETE FROM inquiry_attachments WHERE {$c['inquiryId']} = ? AND {$c['attachmentId']} IN ($ph)";
                $delStmt = $conn->prepare($delSql);
                if ($delStmt) {
                    $typesD = 'i' . str_repeat('i', count($toDelete));
                    $vals = [$iid];
                    foreach ($toDelete as $r) $vals[] = intval($r['attachmentId']);
                    $delStmt->bind_param($typesD, ...$vals);
                    if (!$delStmt->execute()) {
                        throw new Exception('  : ' . ($delStmt->error ?: $conn->error));
                    }
                    $delStmt->close();
                }

                //  (best-effort)
                foreach ($toDelete as $r) {
                    $rel = $r['filePath'] ?? '';
                    if (!is_string($rel) || $rel === '') continue;
                    $abs = __DIR__ . '/../../' . ltrim($rel, '/');
                    if (is_file($abs)) @unlink($abs);
                }
            }
        }

        //    (files[])
        $filesSpec = $_FILES['files'] ?? null;
        // name files[]  PHP $_FILES['files']  ,
        // name files    .
        if (!$filesSpec && isset($_FILES['files'])) $filesSpec = $_FILES['files'];
        if ($filesSpec && isset($filesSpec['name'])) {
            $files = normalizeUploadedFiles($filesSpec);
            if (!empty($files)) {
                $uploadDirRel = 'uploads/inquiries';
                $uploadDirAbs = __DIR__ . '/../../' . $uploadDirRel;
                if (!is_dir($uploadDirAbs)) @mkdir($uploadDirAbs, 0777, true);

                $uploader = $sessionAccountId ?? intval($input['accountId'] ?? 0);
                if ($uploader <= 0) $uploader = 1;

                foreach ($files as $f) {
                    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $orig = basename((string)($f['name'] ?? 'file'));
                    if ($orig === '') $orig = 'file';
                    $ext = pathinfo($orig, PATHINFO_EXTENSION);
                    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
                    if ($safeBase === '') $safeBase = 'file';
                    $newName = $safeBase . '_' . time() . '_' . mt_rand(1000, 9999) . ($ext ? ('.' . $ext) : '');
                    $destAbs = $uploadDirAbs . '/' . $newName;
                    $destRel = $uploadDirRel . '/' . $newName;

                    if (!move_uploaded_file($f['tmp_name'], $destAbs)) {
                        throw new Exception('  : ' . $orig);
                    }

                    $ins = $conn->prepare("INSERT INTO inquiry_attachments ({$c['inquiryId']}, {$c['fileName']}, {$c['filePath']}, {$c['fileSize']}, {$c['fileType']}, {$c['uploadedBy']}) VALUES (?, ?, ?, ?, ?, ?)");
                    if (!$ins) throw new Exception('  : ' . $conn->error);
                    $size = intval($f['size'] ?? 0);
                    $type = (string)($f['type'] ?? '');
                    $ins->bind_param('issisi', $iid, $orig, $destRel, $size, $type, $uploader);
                    if (!$ins->execute()) {
                        $ins->close();
                        throw new Exception('  : ' . ($conn->error ?: ''));
                    }
                    $ins->close();
                }
            }
        }

        $conn->commit();

        log_activity($input['accountId'] ?? ($sessionAccountId ?? 0), "inquiry_updated", "Inquiry updated: {$inquiryId}");
        send_json_response([
            'success' => true,
            'message' => ' .'
        ]);
        
    } catch (Exception $e) {
        if ($conn) {
            @$conn->rollback();
        }
        log_activity($input['accountId'] ?? 0, "inquiry_update_error", "Update inquiry error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleDeleteInquiry($input) {
    global $conn;
    
    $inquiryId = $input['inquiryId'] ?? '';
    $accountId = $input['accountId'] ?? '';
    
    if (empty($inquiryId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM inquiries WHERE inquiryId = ? AND accountId = ?");
        $stmt->bind_param("ii", $inquiryId, $accountId);
        
        if ($stmt->execute()) {
            log_activity($accountId, "inquiry_deleted", "Inquiry deleted: {$inquiryId} by user: {$accountId}");
            send_json_response([
                'success' => true,
                'message' => ' .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "inquiry_delete_error", "Delete inquiry error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleReplyInquiry($input) {
    global $conn;
    
    $inquiryId = $input['inquiryId'] ?? '';
    $replyContent = $input['replyContent'] ?? '';
    $authorId = intval($input['authorId'] ?? $input['replyAuthorId'] ?? $input['accountId'] ?? 0);
    if ($authorId <= 0) $authorId = 1; // fallback: super/admin (    )
    
    if (empty($inquiryId) || empty($replyContent)) {
        send_json_response(['success' => false, 'message' => ' ID   .'], 400);
    }
    
    try {
        $conn->begin_transaction();
        
        //  
        $stmt = $conn->prepare("
            INSERT INTO inquiry_replies (
                inquiryId, authorId, content, isInternal, createdAt
            ) VALUES (?, ?, ?, 0, NOW())
        ");
        
        $stmt->bind_param("iis", $inquiryId, $authorId, $replyContent);
        
        if (!$stmt->execute()) {
            throw new Exception("  : " . $stmt->error);
        }
        
        //  (Reply Status) (Processing Status)  
        //    status   . (updatedAt )
        $u = $conn->prepare("UPDATE inquiries SET updatedAt = NOW() WHERE inquiryId = ?");
        if ($u) {
            $u->bind_param("i", $inquiryId);
            $u->execute();
            $u->close();
        }
        
        $conn->commit();
        
        log_activity($input['accountId'] ?? 0, "inquiry_replied", "Inquiry replied: {$inquiryId} by authorId: {$authorId}");
        
        send_json_response([
            'success' => true,
            'message' => ' .'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity($input['accountId'] ?? 0, "inquiry_reply_error", "Reply inquiry error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '    .'], 500);
    }
}

//   
function handleGetInquiryReplies($input) {
    global $conn;
    
    $inquiryId = $input['inquiryId'] ?? '';
    
    if (empty($inquiryId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $replies = getInquiryReplies($inquiryId);
        
        send_json_response([
            'success' => true,
            'data' => $replies
        ]);
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "inquiry_replies_error", "Get inquiry replies error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//    
function getInquiryReplies($inquiryId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            ir.replyId,
            ir.content AS replyContent,
            ir.createdAt,
            a.username AS replyAuthor
        FROM inquiry_replies 
        ir
        LEFT JOIN accounts a ON ir.authorId = a.accountId
        WHERE ir.inquiryId = ?
        ORDER BY ir.createdAt ASC
    ");
    
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $replies = [];
    while ($row = $result->fetch_assoc()) {
        $replies[] = $row;
    }
    
    return $replies;
}

//   
function getInquiryStatusCounts($accountId) {
    global $conn;
    
    try {
        // UI (pending/replied/closed) 
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status <> 'closed' AND NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = inquiries.inquiryId) THEN 1 ELSE 0 END) AS pendingCnt,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = inquiries.inquiryId) THEN 1 ELSE 0 END) AS repliedCnt,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closedCnt
            FROM inquiries
            WHERE accountId = ?
        ");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        return [
            'pending' => intval($row['pendingCnt'] ?? 0),
            'replied' => intval($row['repliedCnt'] ?? 0),
            'closed' => intval($row['closedCnt'] ?? 0),
        ];
    } catch (Exception $e) {
        return [];
    }
}

//   
function sendDefaultInquiries($status = '') {
    $inquiries = [
        [
            'inquiryId' => 0,
            'title' => 'Product Inquiry',
            'content' => 'I would like to ask about the package details.',
            'category' => 'general',
            'status' => 'pending',
            'priority' => 'medium',
            'createdAt' => '2025-01-20 10:00:00',
            'updatedAt' => '2025-01-20 10:00:00',
            'attachments' => [],
            'fName' => 'John',
            'lName' => 'Doe',
            'emailAddress' => 'john@example.com'
        ],
        [
            'inquiryId' => 0,
            'title' => 'Booking Change Inquiry',
            'content' => 'I would like to change my booking date.',
            'category' => 'booking',
            'status' => 'replied',
            'priority' => 'high',
            'createdAt' => '2025-01-19 15:30:00',
            'updatedAt' => '2025-01-19 16:00:00',
            'attachments' => [],
            'fName' => 'Jane',
            'lName' => 'Smith',
            'emailAddress' => 'jane@example.com'
        ],
        [
            'inquiryId' => 0,
            'title' => 'Visa Application Inquiry',
            'content' => 'I would like to ask about required documents for visa application.',
            'category' => 'visa',
            'status' => 'closed',
            'priority' => 'medium',
            'createdAt' => '2025-01-18 09:15:00',
            'updatedAt' => '2025-01-18 14:30:00',
            'attachments' => [],
            'fName' => 'Mike',
            'lName' => 'Johnson',
            'emailAddress' => 'mike@example.com'
        ]
    ];
    
    //  
    if (!empty($status)) {
        $inquiries = array_filter($inquiries, function($inquiry) use ($status) {
            return $inquiry['status'] === $status;
        });
    }
    
    //  
    $statusCounts = [
        'pending' => 1,
        'replied' => 1,
        'closed' => 1
    ];
    
    send_json_response([
        'success' => true,
        'data' => [
            'inquiries' => array_values($inquiries),
            'statusCounts' => $statusCounts,
            'totalCount' => count($inquiries)
        ]
    ]);
}

// inquiryNo  (INQYYYYMMDD + 4) - inquiries.inquiryNo UNIQUE
function inquiriesColumns() {
    global $conn;
    $defaults = [
        'inquiryNo' => 'inquiryNo',
        'accountId' => 'accountId',
        'category' => 'category',
        'subject' => 'subject',
        'content' => 'content',
        'priority' => 'priority',
        'status' => 'status',
        'createdAt' => 'createdAt',
    ];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return $defaults;

    $colsRes = $conn->query("SHOW COLUMNS FROM inquiries");
    if (!$colsRes) return $defaults;
    $present = [];
    while ($r = $colsRes->fetch_assoc()) {
        $present[strtolower($r['Field'])] = $r['Field'];
    }

    // snake_case /   
    $defaults['inquiryNo'] = $present['inquiryno'] ?? ($present['inquiry_no'] ?? $defaults['inquiryNo']);
    $defaults['accountId'] = $present['accountid'] ?? ($present['account_id'] ?? $defaults['accountId']);
    $defaults['category'] = $present['category'] ?? ($present['inquirytype'] ?? ($present['inquiry_type'] ?? $defaults['category']));
    $defaults['subject'] = $present['subject'] ?? ($present['title'] ?? $defaults['subject']);
    $defaults['content'] = $present['content'] ?? ($present['message'] ?? $defaults['content']);
    $defaults['priority'] = $present['priority'] ?? $defaults['priority'];
    $defaults['status'] = $present['status'] ?? ($present['state'] ?? $defaults['status']);
    $defaults['createdAt'] = $present['createdat'] ?? ($present['created_at'] ?? $defaults['createdAt']);
    return $defaults;
}

function generateInquiryNo($inquiryNoColumn = 'inquiryNo') {
    global $conn;
    $prefix = 'INQ' . date('Ymd');
    $seq = 1;

    //     
    $col = (string)$inquiryNoColumn;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
        $col = 'inquiryNo';
    }

    //   INQYYYYMMDD001, INQYYYYMMDD0001, INQYYYYMMDD09   
    //  /    suffix  MAX .
    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING($col, 12) AS UNSIGNED)) AS maxSeq
        FROM inquiries
        WHERE $col LIKE CONCAT(?, '%')
    ");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $maxSeq = isset($row['maxSeq']) ? (int)$row['maxSeq'] : 0;
    if ($maxSeq > 0) {
        $seq = $maxSeq + 1;
    }

    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}