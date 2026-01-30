<?php
require "../conn.php";

// Handle different HTTP methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest();
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleDeleteRequest();
} else {
    send_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

// GET request handler
function handleGetRequest() {
    global $conn;
    
    try {
        // Get single guide by ID
        if (isset($_GET['id'])) {
            getGuideById($_GET['id']);
            return;
        }
        
        // Get guide location for booking
        if (isset($_GET['booking_id'])) {
            getGuideLocationByBooking($_GET['booking_id']);
            return;
        }
        
        // Get all guides
        getAllGuides();
        
    } catch (Exception $e) {
        log_activity("Guides API error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get all guides
function getAllGuides() {
    global $conn;
    
    try {
        $location = $_GET['location'] ?? '';
        $available_only = $_GET['available_only'] ?? false;
        
        // Create guides table if not exists
        ensureGuidesTable();
        
        $query = "SELECT * FROM guides WHERE isActive = 1";
        $params = [];
        $types = "";
        
        if (!empty($location)) {
            $query .= " AND currentLocation LIKE ?";
            $params[] = "%$location%";
            $types .= "s";
        }
        
        if ($available_only) {
            $query .= " AND status = 'available'";
        }
        
        $query .= " ORDER BY guideName ASC";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        $guides = [];
        while ($row = $result->fetch_assoc()) {
            $guides[] = formatGuideData($row);
        }
        
        // If no guides found, use sample data
        if (empty($guides)) {
            $guides = getSampleGuides();
        }
        
        send_json_response([
            'success' => true,
            'message' => 'Guides retrieved successfully',
            'data' => $guides,
            'count' => count($guides)
        ]);
        
    } catch (Exception $e) {
        log_activity("Get all guides error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get guide by ID
function getGuideById($guide_id) {
    global $conn;
    
    try {
        $guide_id = intval($guide_id);
        
        ensureGuidesTable();
        
        $stmt = $conn->prepare("SELECT * FROM guides WHERE guideId = ? AND isActive = 1");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $sample_guide = getSampleGuideById($guide_id);
            if ($sample_guide) {
                send_json_response(['success' => true, 'data' => $sample_guide]);
                return;
            }
            send_json_response(['success' => false, 'message' => 'Guide not found'], 404);
            return;
        }
        
        $guide = $result->fetch_assoc();
        
        send_json_response([
            'success' => true,
            'data' => formatGuideData($guide)
        ]);
        
    } catch (Exception $e) {
        log_activity("Get guide by ID error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get guide location by booking
function getGuideLocationByBooking($booking_id) {
    global $conn;
    
    try {
        if (!isAuthenticated()) {
            send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        //    (bookings) : bookingId BK...   
        $booking_id = (string)$booking_id;

        // Get booking info
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE bookingId = ?");
        $stmt->bind_param("s", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => 'Booking not found'], 404);
            return;
        }
        
        $booking = $result->fetch_assoc();
        
        // Check if user can access this booking
        $sessionAccountId = $_SESSION['accountId'] ?? $_SESSION['user_id'] ?? null;
        $sessionUserType = $_SESSION['userType'] ?? $_SESSION['accountType'] ?? '';
        if ($sessionAccountId != $booking['accountId'] && !in_array($sessionUserType, ['admin_ph', 'admin_kr', 'super'], true)) {
            send_json_response(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        // Get assigned guide (simulate assignment)
        $assigned_guide = getAssignedGuide($booking);
        
        send_json_response([
            'success' => true,
            'data' => $assigned_guide
        ]);
        
    } catch (Exception $e) {
        log_activity("Get guide location error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// POST request handler - Update guide location
function handlePostRequest() {
    global $conn;
    
    if (!isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['guide_id']) || empty($input['latitude']) || empty($input['longitude'])) {
            send_json_response(['success' => false, 'message' => 'Guide ID, latitude, and longitude are required'], 400);
            return;
        }
        
        $guide_id = intval($input['guide_id']);
        $latitude = floatval($input['latitude']);
        $longitude = floatval($input['longitude']);
        $location_name = $input['location_name'] ?? '';
        
        ensureGuidesTable();
        
        // Update guide location
        $stmt = $conn->prepare("
            UPDATE guides 
            SET latitude = ?, longitude = ?, currentLocation = ?, lastUpdated = NOW() 
            WHERE guideId = ?
        ");
        $stmt->bind_param("ddsi", $latitude, $longitude, $location_name, $guide_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity("Guide location updated: Guide ID $guide_id");
            send_json_response(['success' => true, 'message' => 'Location updated successfully']);
        } else {
            send_json_response(['success' => false, 'message' => 'Guide not found or no changes made'], 404);
        }
        
    } catch (Exception $e) {
        log_activity("Update guide location error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// PUT request handler - Update guide profile
function handlePutRequest() {
    global $conn;
    
    if (!isAuthenticated() || !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
        send_json_response(['success' => false, 'message' => 'Admin access required'], 403);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $guide_id = intval($_GET['id'] ?? $input['id'] ?? 0);
        
        if (empty($guide_id)) {
            send_json_response(['success' => false, 'message' => 'Guide ID is required'], 400);
            return;
        }
        
        ensureGuidesTable();
        
        $update_fields = [];
        $params = [];
        $types = "";
        
        $allowed_fields = ['guideName', 'phone', 'email', 'languages', 'specialties', 'status', 'hourlyRate'];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $input[$field];
                $types .= ($field === 'hourlyRate') ? 'd' : 's';
            }
        }
        
        if (empty($update_fields)) {
            send_json_response(['success' => false, 'message' => 'No valid fields to update'], 400);
            return;
        }
        
        $types .= 'i';
        $params[] = $guide_id;
        
        $stmt = $conn->prepare("UPDATE guides SET " . implode(', ', $update_fields) . " WHERE guideId = ?");
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity("Guide updated: ID $guide_id");
            send_json_response(['success' => true, 'message' => 'Guide updated successfully']);
        } else {
            send_json_response(['success' => false, 'message' => 'Guide not found'], 404);
        }
        
    } catch (Exception $e) {
        log_activity("Update guide error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// DELETE request handler - Deactivate guide
function handleDeleteRequest() {
    global $conn;
    
    if (!isAuthenticated() || !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
        send_json_response(['success' => false, 'message' => 'Admin access required'], 403);
        return;
    }
    
    try {
        $guide_id = intval($_GET['id'] ?? 0);
        
        if (empty($guide_id)) {
            send_json_response(['success' => false, 'message' => 'Guide ID is required'], 400);
            return;
        }
        
        ensureGuidesTable();
        
        // Soft delete by setting isActive = 0
        $stmt = $conn->prepare("UPDATE guides SET isActive = 0 WHERE guideId = ?");
        $stmt->bind_param("i", $guide_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity("Guide deactivated: ID $guide_id");
            send_json_response(['success' => true, 'message' => 'Guide deactivated successfully']);
        } else {
            send_json_response(['success' => false, 'message' => 'Guide not found'], 404);
        }
        
    } catch (Exception $e) {
        log_activity("Deactivate guide error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Helper functions

function ensureGuidesTable() {
    global $conn;
    
    $create_table_query = "
        CREATE TABLE IF NOT EXISTS guides (
            guideId INT AUTO_INCREMENT PRIMARY KEY,
            guideName VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(100),
            languages TEXT,
            specialties TEXT,
            status ENUM('available', 'busy', 'offline') DEFAULT 'available',
            currentLocation VARCHAR(255),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            hourlyRate DECIMAL(8, 2) DEFAULT 50.00,
            isActive TINYINT(1) DEFAULT 1,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            lastUpdated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    
    $conn->query($create_table_query);
    
    // Insert sample guides if table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM guides");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        insertSampleGuides();
    }
}

function insertSampleGuides() {
    global $conn;
    
    $sample_guides = [
        [
            'guideName' => 'Kim Min-jun',
            'phone' => '+82-10-1234-5678',
            'email' => 'minjun.kim@guides.com',
            'languages' => 'Korean, English, Japanese',
            'specialties' => 'Cultural Heritage, Food Tours, Photography',
            'status' => 'available',
            'currentLocation' => 'Myeongdong, Seoul',
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'hourlyRate' => 45.00
        ],
        [
            'guideName' => 'Lee So-young',
            'phone' => '+82-10-2345-6789',
            'email' => 'soyoung.lee@guides.com',
            'languages' => 'Korean, English, Chinese',
            'specialties' => 'Shopping, K-Pop Culture, Modern Seoul',
            'status' => 'available',
            'currentLocation' => 'Gangnam, Seoul',
            'latitude' => 37.4979,
            'longitude' => 127.0276,
            'hourlyRate' => 50.00
        ],
        [
            'guideName' => 'Park Ji-hoon',
            'phone' => '+82-10-3456-7890',
            'email' => 'jihoon.park@guides.com',
            'languages' => 'Korean, English, German',
            'specialties' => 'History, Palace Tours, Traditional Culture',
            'status' => 'busy',
            'currentLocation' => 'Jongno, Seoul',
            'latitude' => 37.5735,
            'longitude' => 126.9788,
            'hourlyRate' => 55.00
        ]
    ];
    
    foreach ($sample_guides as $guide) {
        $stmt = $conn->prepare("
            INSERT INTO guides (guideName, phone, email, languages, specialties, status, 
                               currentLocation, latitude, longitude, hourlyRate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssssddd",
            $guide['guideName'],
            $guide['phone'],
            $guide['email'],
            $guide['languages'],
            $guide['specialties'],
            $guide['status'],
            $guide['currentLocation'],
            $guide['latitude'],
            $guide['longitude'],
            $guide['hourlyRate']
        );
        $stmt->execute();
    }
}

function formatGuideData($row) {
    return [
        'guideId' => intval($row['guideId']),
        'guideName' => $row['guideName'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'languages' => explode(', ', $row['languages'] ?? ''),
        'specialties' => explode(', ', $row['specialties'] ?? ''),
        'status' => $row['status'],
        'currentLocation' => $row['currentLocation'],
        'latitude' => floatval($row['latitude'] ?? 0),
        'longitude' => floatval($row['longitude'] ?? 0),
        'hourlyRate' => floatval($row['hourlyRate']),
        'isActive' => boolval($row['isActive']),
        'lastUpdated' => $row['lastUpdated'],
        'rating' => rand(40, 50) / 10, // Random rating for demo
        'completedTours' => rand(50, 200) // Random tour count for demo
    ];
}

function getSampleGuides() {
    return [
        [
            'guideId' => 1,
            'guideName' => 'Kim Min-jun',
            'phone' => '+82-10-1234-5678',
            'email' => 'minjun.kim@guides.com',
            'languages' => ['Korean', 'English', 'Japanese'],
            'specialties' => ['Cultural Heritage', 'Food Tours', 'Photography'],
            'status' => 'available',
            'currentLocation' => 'Myeongdong, Seoul',
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'hourlyRate' => 45.00,
            'isActive' => true,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'rating' => 4.8,
            'completedTours' => 127
        ],
        [
            'guideId' => 2,
            'guideName' => 'Lee So-young',
            'phone' => '+82-10-2345-6789',
            'email' => 'soyoung.lee@guides.com',
            'languages' => ['Korean', 'English', 'Chinese'],
            'specialties' => ['Shopping', 'K-Pop Culture', 'Modern Seoul'],
            'status' => 'available',
            'currentLocation' => 'Gangnam, Seoul',
            'latitude' => 37.4979,
            'longitude' => 127.0276,
            'hourlyRate' => 50.00,
            'isActive' => true,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'rating' => 4.6,
            'completedTours' => 89
        ],
        [
            'guideId' => 3,
            'guideName' => 'Park Ji-hoon',
            'phone' => '+82-10-3456-7890',
            'email' => 'jihoon.park@guides.com',
            'languages' => ['Korean', 'English', 'German'],
            'specialties' => ['History', 'Palace Tours', 'Traditional Culture'],
            'status' => 'busy',
            'currentLocation' => 'Jongno, Seoul',
            'latitude' => 37.5735,
            'longitude' => 126.9788,
            'hourlyRate' => 55.00,
            'isActive' => true,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'rating' => 4.9,
            'completedTours' => 156
        ]
    ];
}

function getSampleGuideById($guide_id) {
    $guides = getSampleGuides();
    foreach ($guides as $guide) {
        if ($guide['guideId'] == $guide_id) {
            return $guide;
        }
    }
    return null;
}

function getAssignedGuide($booking) {
    // Simulate guide assignment based on booking
    $guides = getSampleGuides();
    $assigned_guide = $guides[array_rand($guides)]; // Random assignment for demo
    
    // Add real-time location update simulation
    $assigned_guide['currentLocation'] = 'Meeting point: Incheon Airport Terminal 2';
    $assigned_guide['latitude'] = 37.4602;
    $assigned_guide['longitude'] = 126.4407;
    $assigned_guide['estimatedArrival'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $assigned_guide['contactInstructions'] = 'Look for guide with "Smart Travel" sign at Gate 7';
    
    return $assigned_guide;
}

function isAuthenticated() {
    return isset($_SESSION['accountId']) && !empty($_SESSION['accountId']);
}

?>