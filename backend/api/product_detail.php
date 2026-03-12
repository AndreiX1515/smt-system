<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS  
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../conn.php';

try {
    // CLI   QUERY_STRING 
    if (php_sapi_name() === 'cli' && isset($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $_GET);
    }
    
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
    
    //     - packages  
    $productQuery = "SELECT * FROM packages WHERE packageId = ? AND isActive = 1";
    $productStmt = $conn->prepare($productQuery);
    $productStmt->bind_param("i", $productId);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    
    if ($productResult->num_rows === 0) {
        throw new Exception("   .");
    }
    
    $product = $productResult->fetch_assoc();
    
    //   
    $imagesQuery = "SELECT * FROM product_images WHERE productId = ? ORDER BY sortOrder ASC";
    $imagesStmt = $conn->prepare($imagesQuery);
    $imagesStmt->bind_param("i", $productId);
    $imagesStmt->execute();
    $imagesResult = $imagesStmt->get_result();
    $images = [];
    while ($row = $imagesResult->fetch_assoc()) {
        $images[] = $row;
    }
    
    //  
    $schedulesQuery = "SELECT * FROM product_schedules WHERE productId = ? ORDER BY dayNumber ASC";
    $schedulesStmt = $conn->prepare($schedulesQuery);
    $schedulesStmt->bind_param("i", $productId);
    $schedulesStmt->execute();
    $schedulesResult = $schedulesStmt->get_result();
    $schedules = [];
    
    while ($schedule = $schedulesResult->fetch_assoc()) {
        $scheduleId = $schedule['scheduleId'];
        
        //    
        $activitiesQuery = "SELECT * FROM product_activities WHERE scheduleId = ? ORDER BY sortOrder ASC";
        $activitiesStmt = $conn->prepare($activitiesQuery);
        $activitiesStmt->bind_param("i", $scheduleId);
        $activitiesStmt->execute();
        $activitiesResult = $activitiesStmt->get_result();
        $activities = [];
        while ($activity = $activitiesResult->fetch_assoc()) {
            $activities[] = $activity;
        }
        
        //   
        $accommodationsQuery = "SELECT * FROM product_accommodations WHERE scheduleId = ?";
        $accommodationsStmt = $conn->prepare($accommodationsQuery);
        $accommodationsStmt->bind_param("i", $scheduleId);
        $accommodationsStmt->execute();
        $accommodationsResult = $accommodationsStmt->get_result();
        $accommodations = [];
        while ($accommodation = $accommodationsResult->fetch_assoc()) {
            $accommodations[] = $accommodation;
        }
        
        //   
        $transportationsQuery = "SELECT * FROM product_transportations WHERE scheduleId = ?";
        $transportationsStmt = $conn->prepare($transportationsQuery);
        $transportationsStmt->bind_param("i", $scheduleId);
        $transportationsStmt->execute();
        $transportationsResult = $transportationsStmt->get_result();
        $transportations = [];
        while ($transportation = $transportationsResult->fetch_assoc()) {
            $transportations[] = $transportation;
        }
        
        //   
        $mealsQuery = "SELECT * FROM product_meals WHERE scheduleId = ? ORDER BY 
            CASE mealType 
                WHEN 'breakfast' THEN 1 
                WHEN 'lunch' THEN 2 
                WHEN 'dinner' THEN 3 
            END";
        $mealsStmt = $conn->prepare($mealsQuery);
        $mealsStmt->bind_param("i", $scheduleId);
        $mealsStmt->execute();
        $mealsResult = $mealsStmt->get_result();
        $meals = [];
        while ($meal = $mealsResult->fetch_assoc()) {
            $meals[] = $meal;
        }
        
        $schedule['activities'] = $activities;
        $schedule['accommodations'] = $accommodations;
        $schedule['transportations'] = $transportations;
        $schedule['meals'] = $meals;
        
        $schedules[] = $schedule;
    }
    
    // /  
    $inclusionsQuery = "SELECT * FROM product_inclusions WHERE productId = ? ORDER BY itemType ASC, sortOrder ASC";
    $inclusionsStmt = $conn->prepare($inclusionsQuery);
    $inclusionsStmt->bind_param("i", $productId);
    $inclusionsStmt->execute();
    $inclusionsResult = $inclusionsStmt->get_result();
    $inclusions = [];
    while ($inclusion = $inclusionsResult->fetch_assoc()) {
        $inclusions[] = $inclusion;
    }
    
    //  
    $guideQuery = "SELECT * FROM product_guide WHERE productId = ? ORDER BY sortOrder ASC";
    $guideStmt = $conn->prepare($guideQuery);
    $guideStmt->bind_param("i", $productId);
    $guideStmt->execute();
    $guideResult = $guideStmt->get_result();
    $guides = [];
    while ($guide = $guideResult->fetch_assoc()) {
        $guides[] = $guide;
    }
    
    // /  
    $cancellationQuery = "SELECT * FROM product_cancellation WHERE productId = ? ORDER BY daysBefore DESC";
    $cancellationStmt = $conn->prepare($cancellationQuery);
    $cancellationStmt->bind_param("i", $productId);
    $cancellationStmt->execute();
    $cancellationResult = $cancellationStmt->get_result();
    $cancellations = [];
    while ($cancellation = $cancellationResult->fetch_assoc()) {
        $cancellations[] = $cancellation;
    }
    
    //    
    $visaQuery = "SELECT * FROM product_visa WHERE productId = ? ORDER BY sortOrder ASC";
    $visaStmt = $conn->prepare($visaQuery);
    $visaStmt->bind_param("i", $productId);
    $visaStmt->execute();
    $visaResult = $visaStmt->get_result();
    $visas = [];
    while ($visa = $visaResult->fetch_assoc()) {
        $visas[] = $visa;
    }
    
    //   
    $response = [
        'success' => true,
        'data' => [
            'product' => $product,
            'images' => $images,
            'schedules' => $schedules,
            'inclusions' => $inclusions,
            'guides' => $guides,
            'cancellations' => $cancellations,
            'visas' => $visas
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
