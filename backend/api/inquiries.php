<?php
require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $accountId = $_GET['accountId'] ?? 1;
    $inquiryId = $_GET['inquiryId'] ?? null;
    $status = $_GET['status'] ?? 'all';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    if ($inquiryId) {
        //   
        $stmt = $conn->prepare("
            SELECT 
                i.inquiryId,
                i.subject,
                i.content,
                i.status AS dbStatus,
                i.priority,
                i.createdAt,
                i.updatedAt,
                a.username as authorName,
                a.emailAddress as authorEmail
            FROM inquiries i
            LEFT JOIN accounts a ON i.accountId = a.accountId
            WHERE i.inquiryId = ? AND i.accountId = ?
        ");
        
        $stmt->bind_param("ii", $inquiryId, $accountId);
        $stmt->execute();
        $inquiry = $stmt->get_result()->fetch_assoc();
        
        if (!$inquiry) {
            send_json_response([
                'success' => false,
                'message' => '   .'
            ], 404);
        }

        //   
        $db = $inquiry['dbStatus'] ?? 'open';
        if ($db === 'resolved') $inquiry['status'] = 'replied';
        else if ($db === 'closed') $inquiry['status'] = 'closed';
        else $inquiry['status'] = 'pending';
        $inquiry['title'] = $inquiry['subject'] ?? '';
        $inquiry['message'] = $inquiry['content'] ?? '';
        
        //  
        $stmt2 = $conn->prepare("
            SELECT 
                ir.replyId,
                ir.content as replyMessage,
                ir.createdAt as replyDate,
                a.username as replyAuthor
            FROM inquiry_replies ir
            LEFT JOIN accounts a ON ir.authorId = a.accountId
            WHERE ir.inquiryId = ?
            ORDER BY ir.createdAt ASC
        ");
        
        $stmt2->bind_param("i", $inquiryId);
        $stmt2->execute();
        $replies = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $inquiry['replies'] = $replies;
        
        send_json_response([
            'success' => true,
            'data' => $inquiry
        ]);
        
    } else {
        //   
        $whereClause = "WHERE i.accountId = ?";
        $params = [$accountId];
        $paramTypes = "i";
        
        if ($status !== 'all') {
            $st = strtolower(trim((string)$status));
            if ($st === 'pending') {
                $whereClause .= " AND i.status IN ('open','in_progress')";
            } elseif ($st === 'replied') {
                $whereClause .= " AND i.status = 'resolved'";
            } elseif ($st === 'open' || $st === 'in_progress' || $st === 'resolved' || $st === 'closed') {
                $whereClause .= " AND i.status = ?";
                $params[] = $st;
                $paramTypes .= "s";
            }
        }
        
        $stmt = $conn->prepare("
            SELECT 
                i.inquiryId,
                i.subject,
                i.content,
                i.status AS dbStatus,
                i.priority,
                i.createdAt,
                i.updatedAt
            FROM inquiries i
            $whereClause
            ORDER BY i.createdAt DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= "ii";
        
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $inquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($inquiries as &$inq) {
            $db = $inq['dbStatus'] ?? 'open';
            if ($db === 'resolved') $inq['status'] = 'replied';
            else if ($db === 'closed') $inq['status'] = 'closed';
            else $inq['status'] = 'pending';
            $inq['title'] = $inq['subject'] ?? '';
            $inq['message'] = $inq['content'] ?? '';
        }
        unset($inq);
        
        //  
        $statusCounts = [];
        $statuses = ['pending', 'replied', 'closed'];
        
        foreach ($statuses as $statusType) {
            if ($statusType === 'pending') {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM inquiries WHERE accountId = ? AND status IN ('open','in_progress')");
                $stmt2->bind_param("i", $accountId);
                $stmt2->execute();
                $statusCounts[$statusType] = $stmt2->get_result()->fetch_assoc()['count'];
            } elseif ($statusType === 'replied') {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM inquiries WHERE accountId = ? AND status = 'resolved'");
                $stmt2->bind_param("i", $accountId);
                $stmt2->execute();
                $statusCounts[$statusType] = $stmt2->get_result()->fetch_assoc()['count'];
            } else {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM inquiries WHERE accountId = ? AND status = 'closed'");
                $stmt2->bind_param("i", $accountId);
                $stmt2->execute();
                $statusCounts[$statusType] = $stmt2->get_result()->fetch_assoc()['count'];
            }
        }
        
        send_json_response([
            'success' => true,
            'data' => [
                'inquiries' => $inquiries,
                'statusCounts' => $statusCounts
            ]
        ]);
    }
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '  : ' . $e->getMessage()
    ], 500);
}
?>