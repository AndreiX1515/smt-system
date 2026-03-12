<?php
require "../conn.php";

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetGuide();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_guide_location':
            handleGetGuideLocation($input);
            break;
        case 'get_guide_profile':
            handleGetGuideProfile($input);
            break;
        case 'get_guide_notices':
            handleGetGuideNotices($input);
            break;
        case 'update_guide_location':
            handleUpdateGuideLocation($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

//   
function handleGetGuide() {
    global $conn;
    
    $guideId = $_GET['guideId'] ?? '';
    $bookingId = $_GET['bookingId'] ?? '';
    
    try {
        if (!empty($guideId)) {
            //   
            getSingleGuide($guideId);
        } elseif (!empty($bookingId)) {
            //    
            getGuideByBooking($bookingId);
        } else {
            //   
            getAllGuides();
        }
    } catch (Exception $e) {
        log_activity(0, "get_guide_error", "Get guide error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function getSingleGuide($guideId) {
    global $conn;
    
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
            g.certifications,
            (g.status <> 'inactive') AS isActive,
            NULL AS location,
            NULL AS currentLatitude,
            NULL AS currentLongitude,
            NULL AS lastLocationUpdate,
            a.emailAddress
        FROM guides g
        JOIN accounts a ON g.accountId = a.accountId
        WHERE g.guideId = ? AND g.status <> 'inactive'
    ");
    $stmt->bind_param("s", $guideId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response(['success' => false, 'message' => '   .'], 404);
    }
    
    $guide = $result->fetch_assoc();
    
    // JSON  
    $guide['languages'] = json_decode($guide['languages'], true) ?: [];
    $guide['specialties'] = json_decode($guide['specialties'], true) ?: [];
    $guide['certifications'] = json_decode($guide['certifications'], true) ?: [];
    
    send_json_response([
        'success' => true,
        'data' => $guide
    ]);
}

//    
function getGuideByBooking($bookingId) {
    global $conn;
    
    // booking_guides      
    $tableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        sendDefaultGuide();
        return;
    }

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
            g.certifications,
            (g.status <> 'inactive') AS isActive,
            NULL AS location,
            NULL AS currentLatitude,
            NULL AS currentLongitude,
            NULL AS lastLocationUpdate,
            bg.assignmentDate,
            bg.status as assignmentStatus
        FROM guides g
        JOIN booking_guides bg ON g.guideId = bg.guideId
        WHERE bg.bookingId = ? AND g.status <> 'inactive'
    ");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        //       
        sendDefaultGuide();
        return;
    }
    
    $guide = $result->fetch_assoc();
    
    // JSON  
    $guide['languages'] = json_decode($guide['languages'], true) ?: [];
    $guide['specialties'] = json_decode($guide['specialties'], true) ?: [];
    $guide['certifications'] = json_decode($guide['certifications'], true) ?: [];
    
    send_json_response([
        'success' => true,
        'data' => $guide
    ]);
}

//   
function getAllGuides() {
    global $conn;
    
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
            g.certifications,
            (g.status <> 'inactive') AS isActive,
            NULL AS location,
            NULL AS currentLatitude,
            NULL AS currentLongitude,
            NULL AS lastLocationUpdate
        FROM guides g
        WHERE g.status <> 'inactive'
        ORDER BY g.rating DESC, g.totalReviews DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $guides = [];
    while ($row = $result->fetch_assoc()) {
        $row['languages'] = json_decode($row['languages'], true) ?: [];
        $row['specialties'] = json_decode($row['specialties'], true) ?: [];
        $row['certifications'] = json_decode($row['certifications'], true) ?: [];
        $guides[] = $row;
    }
    
    send_json_response([
        'success' => true,
        'data' => $guides
    ]);
}

//   
function handleGetGuideLocation($input) {
    global $conn;
    
    $guideId = $input['guideId'] ?? '';
    $bookingId = $input['bookingId'] ?? '';
    
    if (empty($guideId) && empty($bookingId)) {
        send_json_response(['success' => false, 'message' => ' ID   ID .'], 400);
    }
    
    try {
        // bookingId  bookings.guideId   (  )
        if (!empty($bookingId) && empty($guideId)) {
            $b = $conn->prepare("SELECT guideId FROM bookings WHERE bookingId = ? LIMIT 1");
            if ($b) {
                $b->bind_param('s', $bookingId);
                $b->execute();
                $row = $b->get_result()->fetch_assoc();
                $b->close();
                if ($row && !empty($row['guideId'])) {
                    $guideId = (string)$row['guideId'];
                }
            }
        }

        // booking_guides   fallback 
        if (!empty($bookingId) && empty($guideId)) {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $bg = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? LIMIT 1");
                if ($bg) {
                    $bg->bind_param('s', $bookingId);
                    $bg->execute();
                    $row = $bg->get_result()->fetch_assoc();
                    $bg->close();
                    if ($row && !empty($row['guideId'])) {
                        $guideId = (string)$row['guideId'];
                    }
                }
            }
        }

        if (empty($guideId)) {
            sendDefaultGuideLocation();
            return;
        }

        // guide  + guide_locations( ) 
        $gid = (int)$guideId;
        $stmt = $conn->prepare("
            SELECT 
                g.guideId,
                g.guideName,
                gl.latitude AS currentLatitude,
                gl.longitude AS currentLongitude,
                gl.updatedAt AS lastLocationUpdate,
                gl.address AS location
            FROM guides g
            LEFT JOIN guide_locations gl 
                ON gl.guideId = g.guideId AND gl.isActive = 1
            WHERE g.guideId = ? AND g.status <> 'inactive'
            ORDER BY gl.updatedAt DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $gid);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendDefaultGuideLocation();
            return;
        }
        
        $guide = $result->fetch_assoc();
        
        send_json_response([
            'success' => true,
            'data' => $guide
        ]);
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "guide_location_error", "Get guide location error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function handleGetGuideProfile($input) {
    global $conn;
    
    $guideId = $input['guideId'] ?? '';
    
    if (empty($guideId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                g.*,
                a.emailAddress,
                COUNT(DISTINCT bg.bookingId) as totalBookings,
                COUNT(DISTINCT r.reviewId) as totalReviews,
                AVG(r.rating) as averageRating
            FROM guides g
            JOIN accounts a ON g.accountId = a.accountId
            LEFT JOIN booking_guides bg ON g.guideId = bg.guideId
            LEFT JOIN reviews r ON g.guideId = r.guideId
            WHERE g.guideId = ? AND g.isActive = 1
            GROUP BY g.guideId
        ");
        $stmt->bind_param("s", $guideId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }
        
        $guide = $result->fetch_assoc();
        
        // JSON  
        $guide['languages'] = json_decode($guide['languages'], true) ?: [];
        $guide['specialties'] = json_decode($guide['specialties'], true) ?: [];
        $guide['certifications'] = json_decode($guide['certifications'], true) ?: [];
        
        //   
        $guide['rating'] = round($guide['averageRating'], 1);
        $guide['total_reviews'] = $guide['totalReviews'];
        
        send_json_response([
            'success' => true,
            'data' => $guide
        ]);
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "guide_profile_error", "Get guide profile error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function handleGetGuideNotices($input) {
    global $conn;
    
    $guideId = $input['guideId'] ?? '';
    $bookingId = $input['bookingId'] ?? '';
    $limit = $input['limit'] ?? 10;
    $offset = $input['offset'] ?? 0;
    
    try {
        $query = "
            SELECT 
                gn.noticeId,
                gn.title,
                gn.content,
                gn.noticeType,
                gn.priority,
                gn.isUrgent,
                gn.createdAt,
                gn.updatedAt,
                g.guideName,
                g.guideId
            FROM guide_notices gn
            JOIN guides g ON gn.guideId = g.guideId
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($guideId)) {
            $query .= " AND gn.guideId = ?";
            $params[] = $guideId;
            $types .= 's';
        }
        
        if (!empty($bookingId)) {
            $query .= " AND gn.bookingId = ?";
            $params[] = $bookingId;
            $types .= 's';
        }
        
        $query .= " ORDER BY gn.isUrgent DESC, gn.priority DESC, gn.createdAt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notices = [];
        while ($row = $result->fetch_assoc()) {
            $notices[] = $row;
        }
        
        send_json_response([
            'success' => true,
            'data' => $notices
        ]);
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "guide_notices_error", "Get guide notices error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function handleUpdateGuideLocation($input) {
    global $conn;
    
    $guideId = $input['guideId'] ?? '';
    $latitude = $input['latitude'] ?? '';
    $longitude = $input['longitude'] ?? '';
    // (js/guide-location.js) address ,   location  
    $address = $input['address'] ?? ($input['location'] ?? '');
    $locationName = $input['locationName'] ?? ($input['placeName'] ?? '');
    
    if (empty($guideId) || empty($latitude) || empty($longitude)) {
        send_json_response(['success' => false, 'message' => ' ID, ,  .'], 400);
    }
    
    try {
        //  : guide_locations  
        $tableCheck = $conn->query("SHOW TABLES LIKE 'guide_locations'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $gid = (int)$guideId;
            $lat = (float)$latitude;
            $lng = (float)$longitude;
            $addr = (string)$address;
            $lname = (string)$locationName;

            $conn->begin_transaction();

            //  active 
            $deact = $conn->prepare("UPDATE guide_locations SET isActive = 0 WHERE guideId = ? AND isActive = 1");
            if ($deact) {
                $deact->bind_param('i', $gid);
                $deact->execute();
                $deact->close();
            }

            //   insert ( 1 active)
            $ins = $conn->prepare("INSERT INTO guide_locations (guideId, latitude, longitude, locationName, address, isActive) VALUES (?, ?, ?, ?, ?, 1)");
            if (!$ins) throw new Exception('   .');
            $ins->bind_param('iddss', $gid, $lat, $lng, $lname, $addr);
            if (!$ins->execute()) throw new Exception('  : ' . $ins->error);
            $ins->close();

            $conn->commit();

            log_activity($input['accountId'] ?? 0, "guide_location_updated", "Guide location updated (guide_locations): {$gid}");
            send_json_response(['success' => true, 'message' => ' .']);
        }

        // fallback:  (guide_locations )  (  )
        send_json_response(['success' => true, 'message' => 'Location update not supported in this environment.']);
        
    } catch (Exception $e) {
        if ($conn) {
            try { @$conn->rollback(); } catch (Exception $_) {}
        }
        log_activity($input['accountId'] ?? 0, "guide_location_update_error", "Update guide location error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function sendDefaultGuide() {
    $defaultGuide = [
        'guideId' => 'guide001',
        'guideName' => 'Hyunwoo Park',
        'profileImage' => '../images/@img_profile.svg',
        'phone' => '+82 1012345678',
        'email' => 'guide@smarttravel.com',
        'languages' => ['', ''],
        'specialties' => [' ', ' '],
        'experience_years' => 5,
        'rating' => 4.9,
        'total_reviews' => 127,
        'bio' => '     .',
        'certifications' => [' '],
        'isActive' => true,
        'location' => ', ',
        'currentLatitude' => 37.5665,
        'currentLongitude' => 126.9780,
        'lastLocationUpdate' => date('Y-m-d H:i:s')
    ];
    
    send_json_response([
        'success' => true,
        'data' => $defaultGuide
    ]);
}

//    
function sendDefaultGuideLocation() {
    $defaultLocation = [
        'guideId' => 'guide001',
        'guideName' => 'Hyunwoo Park',
        'currentLatitude' => 37.5665,
        'currentLongitude' => 126.9780,
        'lastLocationUpdate' => date('Y-m-d H:i:s'),
        'location' => ', '
    ];
    
    send_json_response([
        'success' => true,
        'data' => $defaultLocation
    ]);
}
?>
