<?php
// Simple packages API that works without complex database setup
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try database connection first, fallback to sample data if needed
$use_database = false;
$conn = null;

try {
    $servername = "localhost";
    $username = "root";
    $password = "cloud1234";
    $dbname = "smarttravel";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    $conn->set_charset("utf8mb4");
    $use_database = true;
    
} catch (Exception $e) {
    $use_database = false;
}

// Get request parameters
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

if ($use_database && $conn) {
    // Database mode
    try {
        if ($id) {
            // Get single package
            $query = "SELECT p.*, 
                     (SELECT imageUrl FROM package_images WHERE packageId = p.packageId AND isPrimary = 1 LIMIT 1) as primaryImage
                     FROM packages p WHERE p.packageId = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Get package images
                $imgQuery = "SELECT imageUrl, imageAlt FROM package_images WHERE packageId = ? ORDER BY sortOrder";
                $imgStmt = $conn->prepare($imgQuery);
                $imgStmt->bind_param('i', $id);
                $imgStmt->execute();
                $imgResult = $imgStmt->get_result();
                
                $images = [];
                while ($img = $imgResult->fetch_assoc()) {
                    $images[] = $img;
                }
                $row['images'] = $images;
                
                // Get package itinerary
                $itinQuery = "SELECT * FROM package_itinerary WHERE packageId = ? ORDER BY dayNumber";
                $itinStmt = $conn->prepare($itinQuery);
                $itinStmt->bind_param('i', $id);
                $itinStmt->execute();
                $itinResult = $itinStmt->get_result();
                
                $itinerary = [];
                while ($itin = $itinResult->fetch_assoc()) {
                    $itinerary[] = $itin;
                }
                $row['itinerary'] = $itinerary;
                
                echo json_encode($row, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Package not found'], JSON_UNESCAPED_UNICODE);
            }
            
        } else {
            // Get package list
            $whereClause = $category !== 'all' ? "WHERE packageCategory = ?" : "";
            $query = "SELECT p.packageId, p.packageName, p.packageDescription, p.packageCategory, 
                     p.packageImageUrl, p.destination, COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.durationDays, 1) as durationDays, p.packagePrice, p.rating, p.reviewCount,
                     (SELECT imageUrl FROM package_images WHERE packageId = p.packageId AND isPrimary = 1 LIMIT 1) as primaryImage
                     FROM packages p $whereClause 
                     ORDER BY p.rating DESC, p.packageId 
                     LIMIT ? OFFSET ?";
            
            $stmt = $conn->prepare($query);
            if ($category !== 'all') {
                $stmt->bind_param('sii', $category, $limit, $offset);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $packages = [];
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
            
            echo json_encode([
                'packages' => $packages,
                'total' => count($packages),
                'category' => $category
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        // Fallback to sample data if database query fails
        $use_database = false;
    }
}

if (!$use_database) {
    // Fallback sample data mode
    $samplePackages = [
        [
            'packageId' => 1,
            'packageName' => '     5 6',
            'packageDescription' => '        .',
            'packageCategory' => 'season',
            'packageImageUrl' => 'images/packages/seoul_cherry_blossom.jpg',
            'primaryImage' => 'images/packages/seoul_cherry_1.jpg',
            'destination' => '',
            'durationDays' => 6,
            'packagePrice' => 450000.00,
            'rating' => 4.8,
            'reviewCount' => 127,
            'highlights' => '  ,  ,  ',
            'included' => ', 5 (),  , , , ',
            'meetingPoint' => ' ',
            'meetingTime' => '08:00:00'
        ],
        [
            'packageId' => 2,
            'packageName' => '   3 4',
            'packageDescription' => '      .',
            'packageCategory' => 'season',
            'packageImageUrl' => 'images/packages/jeju_canola.jpg',
            'primaryImage' => 'images/packages/jeju_canola_1.jpg',
            'destination' => '',
            'durationDays' => 4,
            'packagePrice' => 280000.00,
            'rating' => 4.6,
            'reviewCount' => 89,
            'highlights' => ' ,  ,  ',
            'included' => ', 3 ,  , , ',
            'meetingPoint' => '  ',
            'meetingTime' => '07:30:00'
        ],
        [
            'packageId' => 3,
            'packageName' => '   2 3',
            'packageDescription' => '      .',
            'packageCategory' => 'season',
            'packageImageUrl' => 'images/packages/seoraksan_autumn.jpg',
            'primaryImage' => 'images/packages/seorak_autumn_1.jpg',
            'destination' => '',
            'durationDays' => 3,
            'packagePrice' => 180000.00,
            'rating' => 4.9,
            'reviewCount' => 156,
            'highlights' => ' ,  ,  ',
            'included' => ', 2 ,  , , ',
            'meetingPoint' => ' ',
            'meetingTime' => '06:00:00'
        ],
        [
            'packageId' => 4,
            'packageName' => '   3 4',
            'packageDescription' => '      .',
            'packageCategory' => 'region',
            'packageImageUrl' => 'images/packages/busan_beach.jpg',
            'primaryImage' => 'images/packages/busan_beach_1.jpg',
            'destination' => '',
            'durationDays' => 4,
            'packagePrice' => 220000.00,
            'rating' => 4.7,
            'reviewCount' => 203,
            'highlights' => ',  , ',
            'included' => 'KTX , 3 , , ',
            'meetingPoint' => ' KTX ',
            'meetingTime' => '08:20:00'
        ],
        [
            'packageId' => 5,
            'packageName' => '   2 3',
            'packageDescription' => '       .',
            'packageCategory' => 'region',
            'packageImageUrl' => 'images/packages/jeonju_hanok.jpg',
            'primaryImage' => 'images/packages/jeonju_hanok_1.jpg',
            'destination' => '',
            'durationDays' => 3,
            'packagePrice' => 165000.00,
            'rating' => 4.5,
            'reviewCount' => 98,
            'highlights' => ' ,  ,  ',
            'included' => ', 2 , , ',
            'meetingPoint' => ' ',
            'meetingTime' => '07:00:00'
        ],
        [
            'packageId' => 12,
            'packageName' => '  ',
            'packageDescription' => '      .',
            'packageCategory' => 'oneday',
            'packageImageUrl' => 'images/packages/nami_island.jpg',
            'primaryImage' => 'images/packages/nami_island.jpg',
            'destination' => '',
            'durationDays' => 1,
            'packagePrice' => 65000.00,
            'rating' => 4.3,
            'reviewCount' => 89,
            'highlights' => ' ,  ,  ',
            'included' => ', , , ',
            'meetingPoint' => ' 2 ',
            'meetingTime' => '08:00:00'
        ]
    ];

    if ($id) {
        // Find specific package
        $package = null;
        foreach ($samplePackages as $p) {
            if ($p['packageId'] == $id) {
                $package = $p;
                break;
            }
        }
        
        if ($package) {
            // Add sample itinerary for detail view
            $package['itinerary'] = [
                [
                    'dayNumber' => 1,
                    'title' => ' ',
                    'description' => '   ',
                    'activities' => ' ,  ,  ',
                    'startTime' => '12:00:00',
                    'endTime' => '18:00:00'
                ],
                [
                    'dayNumber' => 2,
                    'title' => ' ',
                    'description' => '  ',
                    'activities' => '  ,  ',
                    'startTime' => '09:00:00',
                    'endTime' => '17:00:00'
                ]
            ];
            
            // Add sample images
            $package['images'] = [
                ['imageUrl' => $package['primaryImage'], 'imageAlt' => 'Main image'],
                ['imageUrl' => 'images/packages/sample_2.jpg', 'imageAlt' => 'Second image'],
                ['imageUrl' => 'images/packages/sample_3.jpg', 'imageAlt' => 'Third image']
            ];
            
            echo json_encode($package, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Package not found'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Filter by category if specified
        $filteredPackages = $samplePackages;
        if ($category !== 'all') {
            $filteredPackages = array_filter($samplePackages, function($p) use ($category) {
                return $p['packageCategory'] === $category;
            });
        }
        
        // Apply limit and offset
        $filteredPackages = array_slice($filteredPackages, $offset, $limit);
        
        echo json_encode([
            'packages' => array_values($filteredPackages),
            'total' => count($filteredPackages),
            'category' => $category,
            'mode' => $use_database ? 'database' : 'sample'
        ], JSON_UNESCAPED_UNICODE);
    }
}

if ($conn) {
    $conn->close();
}
?>