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
        // Get reviews for a specific package
        if (isset($_GET['package_id'])) {
            getPackageReviews($_GET['package_id']);
            return;
        }
        
        // Get reviews for a specific guide
        if (isset($_GET['guide_id'])) {
            getGuideReviews($_GET['guide_id']);
            return;
        }
        
        // Get user's reviews
        if (isset($_GET['user_id'])) {
            getUserReviews($_GET['user_id']);
            return;
        }
        
        // Get all reviews (admin)
        getAllReviews();
        
    } catch (Exception $e) {
        log_activity("Reviews API error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get reviews for a package
function getPackageReviews($package_id) {
    global $conn;
    
    try {
        $package_id = intval($package_id);
        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);
        
        ensureReviewsTable();
        
        $query = "SELECT r.*, a.username 
                  FROM reviews r 
                  JOIN accounts a ON r.userId = a.accountId 
                  WHERE r.packageId = ? AND r.isActive = 1 
                  ORDER BY r.createdAt DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $package_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = formatReviewData($row);
        }
        
        // If no reviews found, use sample data
        if (empty($reviews)) {
            $reviews = getSamplePackageReviews($package_id);
        }
        
        // Get review statistics
        $stats = getPackageReviewStats($package_id);
        
        send_json_response([
            'success' => true,
            'message' => 'Package reviews retrieved successfully',
            'data' => [
                'reviews' => $reviews,
                'statistics' => $stats,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($reviews)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity("Get package reviews error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get reviews for a guide
function getGuideReviews($guide_id) {
    global $conn;
    
    try {
        $guide_id = intval($guide_id);
        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);
        
        ensureReviewsTable();
        
        $query = "SELECT r.*, a.username 
                  FROM reviews r 
                  JOIN accounts a ON r.userId = a.accountId 
                  WHERE r.guideId = ? AND r.isActive = 1 
                  ORDER BY r.createdAt DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $guide_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = formatReviewData($row);
        }
        
        // If no reviews found, use sample data
        if (empty($reviews)) {
            $reviews = getSampleGuideReviews($guide_id);
        }
        
        // Get guide review statistics
        $stats = getGuideReviewStats($guide_id);
        
        send_json_response([
            'success' => true,
            'message' => 'Guide reviews retrieved successfully',
            'data' => [
                'reviews' => $reviews,
                'statistics' => $stats,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($reviews)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity("Get guide reviews error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get user's reviews
function getUserReviews($user_id) {
    global $conn;
    
    try {
        if (!isAuthenticated()) {
            send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        
        $user_id = intval($user_id);
        
        // Check permission
        if ($_SESSION['accountId'] != $user_id && !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
            send_json_response(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        ensureReviewsTable();
        
        $query = "SELECT r.*, p.packageName, g.guideName 
                  FROM reviews r 
                  LEFT JOIN packages p ON r.packageId = p.packageId 
                  LEFT JOIN guides g ON r.guideId = g.guideId 
                  WHERE r.userId = ? 
                  ORDER BY r.createdAt DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $review = formatReviewData($row);
            $review['packageName'] = $row['packageName'];
            $review['guideName'] = $row['guideName'];
            $reviews[] = $review;
        }
        
        send_json_response([
            'success' => true,
            'message' => 'User reviews retrieved successfully',
            'data' => $reviews,
            'count' => count($reviews)
        ]);
        
    } catch (Exception $e) {
        log_activity("Get user reviews error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get all reviews (admin)
function getAllReviews() {
    global $conn;
    
    try {
        if (!isAuthenticated() || !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
            send_json_response(['success' => false, 'message' => 'Admin access required'], 403);
            return;
        }
        
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        $status = $_GET['status'] ?? '';
        
        ensureReviewsTable();
        
        $query = "SELECT r.*, a.username, p.packageName, g.guideName 
                  FROM reviews r 
                  JOIN accounts a ON r.userId = a.accountId 
                  LEFT JOIN packages p ON r.packageId = p.packageId 
                  LEFT JOIN guides g ON r.guideId = g.guideId 
                  WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if (!empty($status)) {
            if ($status === 'active') {
                $query .= " AND r.isActive = 1";
            } elseif ($status === 'inactive') {
                $query .= " AND r.isActive = 0";
            }
        }
        
        $query .= " ORDER BY r.createdAt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $review = formatReviewData($row);
            $review['username'] = $row['username'];
            $review['packageName'] = $row['packageName'];
            $review['guideName'] = $row['guideName'];
            $reviews[] = $review;
        }
        
        send_json_response([
            'success' => true,
            'message' => 'All reviews retrieved successfully',
            'data' => $reviews,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($reviews)
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity("Get all reviews error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// POST request handler - Submit review
function handlePostRequest() {
    global $conn;
    
    if (!isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['rating', 'comment'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && empty(trim($input[$field])))) {
                send_json_response(['success' => false, 'message' => "$field is required"], 400);
                return;
            }
        }
        
        // Must have either packageId or guideId
        if (empty($input['packageId']) && empty($input['guideId'])) {
            send_json_response(['success' => false, 'message' => 'Either package ID or guide ID is required'], 400);
            return;
        }
        
        $rating = floatval($input['rating']);
        if ($rating < 1 || $rating > 5) {
            send_json_response(['success' => false, 'message' => 'Rating must be between 1 and 5'], 400);
            return;
        }
        
        ensureReviewsTable();
        
        // Check if user already reviewed this package/guide
        $check_query = "SELECT reviewId FROM reviews WHERE userId = ?";
        $check_params = [$_SESSION['accountId']];
        $check_types = "i";
        
        if (!empty($input['packageId'])) {
            $check_query .= " AND packageId = ?";
            $check_params[] = intval($input['packageId']);
            $check_types .= "i";
        }
        
        if (!empty($input['guideId'])) {
            $check_query .= " AND guideId = ?";
            $check_params[] = intval($input['guideId']);
            $check_types .= "i";
        }
        
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param($check_types, ...$check_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            send_json_response(['success' => false, 'message' => 'You have already reviewed this item'], 409);
            return;
        }
        
        // Insert review
        $stmt = $conn->prepare("
            INSERT INTO reviews (userId, packageId, guideId, rating, comment, createdAt)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $package_id = !empty($input['packageId']) ? intval($input['packageId']) : null;
        $guide_id = !empty($input['guideId']) ? intval($input['guideId']) : null;
        
        $stmt->bind_param("iiids", 
            $_SESSION['accountId'],
            $package_id,
            $guide_id,
            $rating,
            trim($input['comment'])
        );
        
        if ($stmt->execute()) {
            $review_id = $conn->insert_id;
            
            log_activity("Review submitted: Review ID $review_id by user " . $_SESSION['accountId']);
            
            send_json_response([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => [
                    'reviewId' => $review_id,
                    'rating' => $rating
                ]
            ]);
        } else {
            throw new Exception("Failed to submit review");
        }
        
    } catch (Exception $e) {
        log_activity("Submit review error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Failed to submit review'], 500);
    }
}

// PUT request handler - Update review
function handlePutRequest() {
    global $conn;
    
    if (!isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $review_id = intval($_GET['id'] ?? $input['id'] ?? 0);
        
        if (empty($review_id)) {
            send_json_response(['success' => false, 'message' => 'Review ID is required'], 400);
            return;
        }
        
        ensureReviewsTable();
        
        // Get current review
        $stmt = $conn->prepare("SELECT * FROM reviews WHERE reviewId = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => 'Review not found'], 404);
            return;
        }
        
        $review = $result->fetch_assoc();
        
        // Check permission
        if ($_SESSION['accountId'] != $review['userId'] && !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
            send_json_response(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        $update_fields = [];
        $params = [];
        $types = "";
        
        if (isset($input['rating'])) {
            $rating = floatval($input['rating']);
            if ($rating < 1 || $rating > 5) {
                send_json_response(['success' => false, 'message' => 'Rating must be between 1 and 5'], 400);
                return;
            }
            $update_fields[] = "rating = ?";
            $params[] = $rating;
            $types .= "d";
        }
        
        if (isset($input['comment'])) {
            $update_fields[] = "comment = ?";
            $params[] = trim($input['comment']);
            $types .= "s";
        }
        
        if (isset($input['isActive']) && in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
            $update_fields[] = "isActive = ?";
            $params[] = intval($input['isActive']);
            $types .= "i";
        }
        
        if (empty($update_fields)) {
            send_json_response(['success' => false, 'message' => 'No valid fields to update'], 400);
            return;
        }
        
        $types .= "i";
        $params[] = $review_id;
        
        $stmt = $conn->prepare("UPDATE reviews SET " . implode(', ', $update_fields) . " WHERE reviewId = ?");
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            log_activity("Review updated: ID $review_id");
            send_json_response(['success' => true, 'message' => 'Review updated successfully']);
        } else {
            throw new Exception("Failed to update review");
        }
        
    } catch (Exception $e) {
        log_activity("Update review error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// DELETE request handler - Delete review
function handleDeleteRequest() {
    global $conn;
    
    if (!isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $review_id = intval($_GET['id'] ?? 0);
        
        if (empty($review_id)) {
            send_json_response(['success' => false, 'message' => 'Review ID is required'], 400);
            return;
        }
        
        ensureReviewsTable();
        
        // Get current review
        $stmt = $conn->prepare("SELECT * FROM reviews WHERE reviewId = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => 'Review not found'], 404);
            return;
        }
        
        $review = $result->fetch_assoc();
        
        // Check permission
        if ($_SESSION['accountId'] != $review['userId'] && !in_array($_SESSION['accountType'] ?? '', ['admin_ph', 'admin_kr'], true)) {
            send_json_response(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        // Soft delete by setting isActive = 0
        $stmt = $conn->prepare("UPDATE reviews SET isActive = 0 WHERE reviewId = ?");
        $stmt->bind_param("i", $review_id);
        
        if ($stmt->execute()) {
            log_activity("Review deleted: ID $review_id");
            send_json_response(['success' => true, 'message' => 'Review deleted successfully']);
        } else {
            throw new Exception("Failed to delete review");
        }
        
    } catch (Exception $e) {
        log_activity("Delete review error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Helper functions

function ensureReviewsTable() {
    global $conn;
    
    $create_table_query = "
        CREATE TABLE IF NOT EXISTS reviews (
            reviewId INT AUTO_INCREMENT PRIMARY KEY,
            userId INT NOT NULL,
            packageId INT NULL,
            guideId INT NULL,
            rating DECIMAL(2,1) NOT NULL,
            comment TEXT NOT NULL,
            isActive TINYINT(1) DEFAULT 1,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (userId) REFERENCES accounts(accountId),
            FOREIGN KEY (packageId) REFERENCES packages(packageId),
            INDEX idx_package_reviews (packageId, isActive),
            INDEX idx_guide_reviews (guideId, isActive),
            INDEX idx_user_reviews (userId)
        )
    ";
    
    $conn->query($create_table_query);
}

function formatReviewData($row) {
    return [
        'reviewId' => intval($row['reviewId']),
        'userId' => intval($row['userId']),
        'packageId' => $row['packageId'] ? intval($row['packageId']) : null,
        'guideId' => $row['guideId'] ? intval($row['guideId']) : null,
        'username' => $row['username'] ?? 'Anonymous',
        'rating' => floatval($row['rating']),
        'comment' => $row['comment'],
        'isActive' => boolval($row['isActive']),
        'createdAt' => $row['createdAt'],
        'timeAgo' => getTimeAgo($row['createdAt'])
    ];
}

function getPackageReviewStats($package_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as totalReviews,
            AVG(rating) as averageRating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating1
        FROM reviews 
        WHERE packageId = ? AND isActive = 1
    ");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return [
        'totalReviews' => intval($row['totalReviews']),
        'averageRating' => round(floatval($row['averageRating']), 1),
        'ratingDistribution' => [
            5 => intval($row['rating5']),
            4 => intval($row['rating4']),
            3 => intval($row['rating3']),
            2 => intval($row['rating2']),
            1 => intval($row['rating1'])
        ]
    ];
}

function getGuideReviewStats($guide_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as totalReviews,
            AVG(rating) as averageRating
        FROM reviews 
        WHERE guideId = ? AND isActive = 1
    ");
    $stmt->bind_param("i", $guide_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return [
        'totalReviews' => intval($row['totalReviews']),
        'averageRating' => round(floatval($row['averageRating']), 1)
    ];
}

function getSamplePackageReviews($package_id) {
    return [
        [
            'reviewId' => 1,
            'userId' => 1,
            'packageId' => $package_id,
            'guideId' => null,
            'username' => 'TravelLover',
            'rating' => 4.5,
            'comment' => '  !    ,  .',
            'isActive' => true,
            'createdAt' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'timeAgo' => '3 days ago'
        ],
        [
            'reviewId' => 2,
            'userId' => 2,
            'packageId' => $package_id,
            'guideId' => null,
            'username' => 'SeoulExplorer',
            'rating' => 5.0,
            'comment' => 'Perfect cherry blossom tour! The itinerary was well-planned and our guide was very knowledgeable.',
            'isActive' => true,
            'createdAt' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'timeAgo' => '1 week ago'
        ]
    ];
}

function getSampleGuideReviews($guide_id) {
    return [
        [
            'reviewId' => 3,
            'userId' => 3,
            'packageId' => null,
            'guideId' => $guide_id,
            'username' => 'KultureFan',
            'rating' => 4.8,
            'comment' => '    .     !',
            'isActive' => true,
            'createdAt' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'timeAgo' => '2 days ago'
        ]
    ];
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

function isAuthenticated() {
    return isset($_SESSION['accountId']) && !empty($_SESSION['accountId']);
}

?>