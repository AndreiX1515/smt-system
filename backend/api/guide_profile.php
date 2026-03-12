<?php
require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $guideId = $_GET['guideId'] ?? null;
    $accountId = $_GET['accountId'] ?? null;
    
    if ($guideId) {
        //   
        $stmt = $conn->prepare("
            SELECT 
                g.guideId,
                g.accountId,
                g.guideName,
                g.profileImage,
                g.phoneNumber AS phone,
                g.email,
                g.languages,
                g.specialties,
                g.experienceYears AS experience_years,
                g.rating,
                g.totalReviews AS total_reviews,
                g.introduction AS bio,
                g.certifications,
                (g.status <> 'inactive') AS isActive,
                NULL AS location,
                g.createdAt,
                g.updatedAt
            FROM guides g
            WHERE g.guideId = ? AND g.status <> 'inactive'
        ");
        
        $stmt->bind_param("i", $guideId);
        $stmt->execute();
        $guide = $stmt->get_result()->fetch_assoc();
        
        if (!$guide) {
            send_json_response([
                'success' => false,
                'message' => '   .'
            ], 404);
        }
        
    } elseif ($accountId) {
        //  ID  
        $stmt = $conn->prepare("
            SELECT 
                g.guideId,
                g.accountId,
                g.guideName,
                g.profileImage,
                g.phoneNumber AS phone,
                g.email,
                g.languages,
                g.specialties,
                g.experienceYears AS experience_years,
                g.rating,
                g.totalReviews AS total_reviews,
                g.introduction AS bio,
                g.certifications,
                (g.status <> 'inactive') AS isActive,
                NULL AS location,
                g.createdAt,
                g.updatedAt
            FROM guides g
            WHERE g.accountId = ? AND g.status <> 'inactive'
        ");
        
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $guide = $stmt->get_result()->fetch_assoc();
        
        if (!$guide) {
            send_json_response([
                'success' => false,
                'message' => '   .'
            ], 404);
        }
        
    } else {
        //     
        $stmt = $conn->prepare("
            SELECT 
                g.guideId,
                g.guideName,
                g.profileImage,
                g.phoneNumber AS phone,
                g.email,
                g.languages,
                g.specialties,
                g.experienceYears AS experience_years,
                g.rating,
                g.totalReviews AS total_reviews,
                g.introduction AS bio,
                NULL AS location,
                g.createdAt
            FROM guides g
            WHERE g.status <> 'inactive'
            ORDER BY g.rating DESC, g.totalReviews DESC
        ");
        
        $stmt->execute();
        $guides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // JSON  
        foreach ($guides as &$guide) {
            $guide['languages'] = json_decode($guide['languages'], true);
            $guide['specialties'] = json_decode($guide['specialties'], true);
        }
        
        send_json_response([
            'success' => true,
            'data' => [
                'guides' => $guides
            ]
        ]);
    }
    
    // JSON  
    $guide['languages'] = json_decode($guide['languages'] ?? '[]', true) ?: [];
    $guide['specialties'] = json_decode($guide['specialties'] ?? '[]', true) ?: [];
    $guide['certifications'] = json_decode($guide['certifications'] ?? '[]', true) ?: [];

    //     (       )
    $guide['recentReviews'] = [];
    try {
        $stmt2 = $conn->prepare("
            SELECT 
                r.rating,
                r.createdAt,
                a.username as reviewerName
            FROM reviews r
            LEFT JOIN accounts a ON r.accountId = a.accountId
            ORDER BY r.createdAt DESC
            LIMIT 5
        ");
        if ($stmt2) {
            $stmt2->execute();
            $guide['recentReviews'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        }
    } catch (Throwable $t) {
        // ignore
        $guide['recentReviews'] = [];
    }
    
    send_json_response([
        'success' => true,
        'data' => $guide
    ]);
    
} catch (Throwable $e) {
    send_json_response([
        'success' => false,
        'message' => '  : ' . $e->getMessage()
    ], 500);
}
?>



