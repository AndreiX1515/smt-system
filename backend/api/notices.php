<?php
require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $category = $_GET['category'] ?? 'all';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    $noticeId = $_GET['noticeId'] ?? null;
    
    if ($noticeId) {
        //   
        $stmt = $conn->prepare("
            SELECT 
                n.noticeId,
                n.title,
                n.content,
                n.category,
                n.priority,
                n.viewCount,
                n.publishedAt,
                n.createdAt,
                a.username as authorName
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            WHERE n.noticeId = ? AND n.isActive = 1
        ");
        
        $stmt->bind_param("i", $noticeId);
        $stmt->execute();
        $notice = $stmt->get_result()->fetch_assoc();
        
        if (!$notice) {
            send_json_response([
                'success' => false,
                'message' => '   .'
            ], 404);
        }
        
        //  
        $stmt2 = $conn->prepare("
            UPDATE notices 
            SET viewCount = viewCount + 1 
            WHERE noticeId = ?
        ");
        $stmt2->bind_param("i", $noticeId);
        $stmt2->execute();
        
        send_json_response([
            'success' => true,
            'data' => $notice
        ]);
        
    } else {
        //   
        $whereClause = "WHERE n.isActive = 1";
        $params = [];
        $paramTypes = "";
        
        if ($category !== 'all') {
            $whereClause .= " AND n.category = ?";
            $params[] = $category;
            $paramTypes .= "s";
        }
        
        $stmt = $conn->prepare("
            SELECT 
                n.noticeId,
                n.title,
                n.content,
                n.category,
                n.priority,
                n.viewCount,
                n.publishedAt,
                n.createdAt,
                a.username as authorName
            FROM notices n
            LEFT JOIN accounts a ON n.authorId = a.accountId
            $whereClause
            ORDER BY n.publishedAt DESC, n.createdAt DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= "ii";
        
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        //  
        $categoryCounts = [];
        $categories = ['general', 'booking', 'payment', 'visa', 'system'];
        
        foreach ($categories as $cat) {
            $stmt2 = $conn->prepare("
                SELECT COUNT(*) as count
                FROM notices
                WHERE category = ? AND isActive = 1
            ");
            $stmt2->bind_param("s", $cat);
            $stmt2->execute();
            $categoryCounts[$cat] = $stmt2->get_result()->fetch_assoc()['count'];
        }
        
        send_json_response([
            'success' => true,
            'data' => [
                'notices' => $notices,
                'categoryCounts' => $categoryCounts
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



