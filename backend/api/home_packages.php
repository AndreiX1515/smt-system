<?php
// Image fix applied - cache busted at 2025-01-15 12:00:00
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
    $category = isset($_GET['category']) ? $_GET['category'] : 'season';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;

    //   (   DB packageCategory )
    $categoryMap = [
        'season' => 'season',
        'region' => 'region',
        'theme' => 'theme',
        'private' => 'private',
        //  daytrip   /  oneday   
        'daytrip' => 'oneday',
        'oneday' => 'oneday'
    ];

    //  (code)      category  
    $dbCategory = $categoryMap[$category] ?? $category ?? 'season';

    // ===== B2B/B2C 판별 (홈페이지용) =====
    // B2B/B2C 판별: accounts.accountType 기반
    // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B (sales_target='B2B')
    // - accountType IN ('guest', 'guide', 'cs', '') → B2C (sales_target IS NULL/'B2C')
    $sessionAccountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
    $sessionAccountId = $sessionAccountId !== null ? (int)$sessionAccountId : 0;
    $isB2BUser = false;
    if ($sessionAccountId > 0) {
        try {
            $st = $conn->prepare("SELECT LOWER(COALESCE(accountType,'')) AS accountType FROM accounts WHERE accountId = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $sessionAccountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $isB2BUser = in_array(($row['accountType'] ?? ''), ['agent', 'admin_ph', 'admin_kr'], true);
            }
        } catch (Throwable $e) { $isB2BUser = false; }
    }

    //     - packages  (sales_target 필터 제거 - 이중 가격 시스템)
    $packageQuery = "
        SELECT
            packageId,
            packageName,
            packagePrice,
            price_display_text,
            b2b_price,
            b2b_price_display_text,
            childPrice,
            b2b_child_price,
            infantPrice,
            b2b_infant_price,
            packageCategory,
            packageDescription,
            durationDays,
            meeting_location,
            meeting_time,
            packageType,
            includes,
            excludes,
            highlights,
            rating,
            reviewCount,
            minParticipants,
            maxParticipants,
            packageStatus,
            difficulty,
            isActive,
            packageImageUrl,
            packageImage,
            thumbnail_image,
            product_images,
            detail_image,
            packageDuration,
            packageDestination,
            createdAt,
            updatedAt
        FROM packages
        WHERE packageCategory = ?
          AND (isActive IS NULL OR isActive = 1)
        ORDER BY createdAt DESC
        LIMIT ?
    ";

    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->bind_param('si', $dbCategory, $limit);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();

    $packages = [];
    while ($package = $packageResult->fetch_assoc()) {
        //     - flight  
        $flightQuery = "SELECT MIN(flightPrice) as baseFlightPrice, MIN(landPrice) as adultPrice 
                        FROM flight 
                        WHERE packageId = ? AND is_active = 1 
                        LIMIT 1";
        $flightStmt = $conn->prepare($flightQuery);
        $flightStmt->bind_param('i', $package['packageId']);
        $flightStmt->execute();
        $flightResult = $flightStmt->get_result();
        $flightData = $flightResult->fetch_assoc();
        $flightStmt->close();
        
        $hasFlight = !empty($flightData) && ($flightData['baseFlightPrice'] !== null || $flightData['adultPrice'] !== null);
        $baseFlightPrice = $flightData['baseFlightPrice'] ?? 0;
        $adultPrice = $flightData['adultPrice'] ?? 0;
        
        //  URL 
        // :
        // 1) packages.thumbnail_image (  ,   )
        // 2) packages.product_images (JSON/)
        // 3) packages.packageImageUrl / packages.packageImage / packages.detail_image
        $imageUrl = '';
        try {
            $thumb = trim((string)($package['thumbnail_image'] ?? ''));
            if ($thumb !== '') $imageUrl = $thumb;
        } catch (Throwable $e) { }

        if (!$imageUrl) {
            try {
                $raw = $package['product_images'] ?? '';
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        // array  {en:..., tl:...}  
                        if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
                            $lang = (isset($_GET['lang']) && in_array($_GET['lang'], ['en','tl'], true)) ? $_GET['lang'] : 'en';
                            $pick = $decoded[$lang] ?? ($decoded['en'] ?? null);
                            if (is_string($pick)) $imageUrl = $pick;
                        } else {
                            $first = $decoded[0] ?? '';
                            if (is_string($first)) $imageUrl = $first;
                        }
                    } elseif (is_string($decoded)) {
                        $imageUrl = $decoded;
                    } else {
                        // product_images    
                        $imageUrl = $raw;
                    }
                }
            } catch (Throwable $e) {
                $imageUrl = '';
            }
        }

        if (!$imageUrl) {
            $imageUrl = $package['packageImageUrl'] ?: $package['packageImage'];
        }
        if (!$imageUrl) {
            $imageUrl = $package['detail_image'] ?? '';
        }

        //  normalize
        $imageUrl = trim((string)$imageUrl);
        if ($imageUrl) {
            $imageUrl = str_replace('\\', '/', $imageUrl);
            if (str_starts_with($imageUrl, 'uploads/')) $imageUrl = '/' . $imageUrl;
            if (str_starts_with($imageUrl, 'products/')) $imageUrl = '/uploads/' . $imageUrl;
            //   (  /uploads/products  )
            if (!str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://') && !str_starts_with($imageUrl, '/') && !str_contains($imageUrl, '/')) {
                $imageUrl = '/uploads/products/' . $imageUrl;
            }
        }

        //      ( /)     fallback 
        if ($imageUrl && !str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://')) {
            $fs = '/var/www/html' . (str_starts_with($imageUrl, '/') ? $imageUrl : ('/' . $imageUrl));
            if (!file_exists($fs)) {
                $imageUrl = '';
            }
        }

        // NOTE: (placeholder)   .
        //   imageUrl    ""  .

        //    : ( )     
        $isConfirmed = false;
        try {
            $minP = intval($package['minParticipants'] ?? 0);
            if ($minP > 0) {
                $stmtConf = $conn->prepare("
                    SELECT MAX(t.cnt) AS maxBooked
                    FROM (
                        SELECT departureDate, SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS cnt
                        FROM bookings
                        WHERE packageId = ?
                          AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                          AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                          AND departureDate >= CURDATE()
                        GROUP BY departureDate
                    ) t
                ");
                if ($stmtConf) {
                    $stmtConf->bind_param('i', $package['packageId']);
                    $stmtConf->execute();
                    $r = $stmtConf->get_result();
                    $row = $r ? $r->fetch_assoc() : null;
                    $stmtConf->close();
                    $maxBooked = intval($row['maxBooked'] ?? 0);
                    $isConfirmed = ($maxBooked >= $minP);
                }
            }
        } catch (Throwable $e) {
            $isConfirmed = false;
        }

        //  
        //    :   +  1 
        if ($hasFlight && ($baseFlightPrice > 0 || $adultPrice > 0)) {
            //    1    
            if ($baseFlightPrice > 0 && $adultPrice > 0) {
                $formattedPrice = '₱' . number_format($baseFlightPrice, 0, '.', ',') . ' + ₱' . number_format($adultPrice, 0, '.', ',') . '~';
                $price = $baseFlightPrice + $adultPrice; //  
            } elseif ($baseFlightPrice > 0) {
                //    
                $formattedPrice = '₱' . number_format($baseFlightPrice, 0, '.', ',') . '~';
                $price = $baseFlightPrice;
            } else {
                //  1   
                $formattedPrice = '₱' . number_format($adultPrice, 0, '.', ',') . '~';
                $price = $adultPrice;
            }
        } else {
            //     
            $price = (float)$package['packagePrice'];
            $formattedPrice = '₱' . number_format($price, 0, '.', ',') . '~';
        }

        // B2B 가격 설정
        $b2bPrice = isset($package['b2b_price']) && $package['b2b_price'] !== null ? floatval($package['b2b_price']) : null;
        $b2bChildPrice = isset($package['b2b_child_price']) && $package['b2b_child_price'] !== null ? floatval($package['b2b_child_price']) : null;
        $b2bInfantPrice = isset($package['b2b_infant_price']) && $package['b2b_infant_price'] !== null ? floatval($package['b2b_infant_price']) : null;

        // 가격 텍스트 오버라이드 (문자열 가격 표시용)
        $priceDisplayText = isset($package['price_display_text']) && trim((string)$package['price_display_text']) !== ''
            ? trim((string)$package['price_display_text']) : null;
        $b2bPriceDisplayText = isset($package['b2b_price_display_text']) && trim((string)$package['b2b_price_display_text']) !== ''
            ? trim((string)$package['b2b_price_display_text']) : null;

        // B2B 사용자용 가격 포맷팅
        $b2bFormattedPrice = null;
        if ($b2bPrice !== null) {
            $b2bFormattedPrice = '₱' . number_format($b2bPrice, 0, '.', ',') . '~';
        }

        // B2C 가격 (packages 테이블의 기본 가격)
        $packagePrice = floatval($package['packagePrice'] ?? 0);
        $childPrice = floatval($package['childPrice'] ?? 0);
        $infantPrice = floatval($package['infantPrice'] ?? 0);

        $packages[] = [
            'id' => $package['packageId'],
            'packageId' => $package['packageId'],
            'name' => $package['packageName'],
            'packageName' => $package['packageName'],
            'price' => $formattedPrice,
            'rawPrice' => $price,
            // B2C 가격 (packages 테이블 기본값)
            'packagePrice' => $packagePrice,
            'priceDisplayText' => $priceDisplayText,
            'childPrice' => $childPrice,
            'infantPrice' => $infantPrice,
            // B2B 가격
            'b2bPrice' => $b2bPrice,
            'b2bPriceDisplayText' => $b2bPriceDisplayText,
            'b2bChildPrice' => $b2bChildPrice,
            'b2bInfantPrice' => $b2bInfantPrice,
            'b2bFormattedPrice' => $b2bFormattedPrice,
            'hasFlight' => $hasFlight,
            'baseFlightPrice' => $baseFlightPrice,
            'adultPrice' => $adultPrice,
            'category' => $package['packageCategory'],
            'description' => $package['packageDescription'],
            'destination' => $package['packageDestination'] ?? 'Korea',
            'duration' => $package['durationDays'] ?: $package['packageDuration'],
            'imageUrl' => $imageUrl,
            'imageAlt' => $package['packageName'] . '  ',
            'meetingLocation' => $package['meeting_location'],
            'meetingTime' => $package['meeting_time'],
            'includes' => $package['includes'],
            'excludes' => $package['excludes'],
            'highlights' => $package['highlights'],
            'rating' => $package['rating'] ?: 4.5,
            'reviewCount' => $package['reviewCount'] ?: 0,
            'minParticipants' => $package['minParticipants'] ?: 2,
            'maxParticipants' => $package['maxParticipants'] ?: 20,
            'difficulty' => $package['difficulty'] ?: 'easy',
            'isConfirmed' => $isConfirmed,
            'status' => $package['packageStatus'] ?: 'active',
            'isActive' => $package['isActive'] == 1,
            'createdAt' => $package['createdAt'],
            'updatedAt' => $package['updatedAt']
        ];
    }

    //    :     fallback   .

    //   
    $response = [
        'success' => true,
        'data' => [
            'category' => $category,
            'packages' => $packages,
            'total' => count($packages)
        ],
        'message' => count($packages) > 0 ? '  ' : '   .'
    ];

    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '    : ' . $e->getMessage(),
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * DB     0 , UI     .
 * - packageId  (>0)  product-detail.php   
 * - daytrip oneday  
 */
function getFallbackPackages($category, $limit = 4) {
    $limit = max(1, (int)$limit);
    $cat = $category;
    if ($cat === 'oneday') $cat = 'daytrip';

    $all = [
        'season' => [
            ['packageId' => 1, 'packageName' => '     5 6', 'rawPrice' => 340000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_card1.jpg'],
            ['packageId' => 3, 'packageName' => '   4 5', 'rawPrice' => 450000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_travel.jpg'],
        ],
        'region' => [
            ['packageId' => 6, 'packageName' => '·   3 4', 'rawPrice' => 220000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_card2.jpg'],
            ['packageId' => 9, 'packageName' => '   2 3', 'rawPrice' => 250000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_banner1.jpg'],
        ],
        'theme' => [
            ['packageId' => 10, 'packageName' => '   3 4', 'rawPrice' => 290000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_card1.jpg'],
            ['packageId' => 11, 'packageName' => 'K-POP  2 3', 'rawPrice' => 250000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_card2.jpg'],
        ],
        'private' => [
            ['packageId' => 20, 'packageName' => '  ', 'rawPrice' => 850000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_travel.jpg'],
        ],
        'daytrip' => [
            ['packageId' => 12, 'packageName' => '  ', 'rawPrice' => 120000, 'imageUrl' => 'https://www.smt-escape.com/images/@img_banner1.jpg'],
        ]
    ];

    $source = $all[$cat] ?? $all['season'];
    $out = [];
    foreach (array_slice($source, 0, $limit) as $p) {
        $price = (float)($p['rawPrice'] ?? 0);
        $out[] = [
            'id' => (int)$p['packageId'],
            'packageId' => (int)$p['packageId'],
            'name' => $p['packageName'],
            'packageName' => $p['packageName'],
            'price' => '₱' . number_format($price, 0, '.', ',') . '~',
            'rawPrice' => $price,
            'category' => ($cat === 'daytrip') ? 'oneday' : $cat,
            'description' => '',
            'destination' => 'Korea',
            'duration' => '',
            'imageUrl' => $p['imageUrl'],
            'imageAlt' => $p['packageName'] . '  ',
            'meetingLocation' => '',
            'meetingTime' => '',
            'includes' => '',
            'excludes' => '',
            'highlights' => '',
            'rating' => 4.5,
            'reviewCount' => 0,
            'minParticipants' => 1,
            'maxParticipants' => 20,
            'difficulty' => 'easy',
            'isConfirmed' => true,
            'status' => 'active',
            'isActive' => true,
            'createdAt' => null,
            'updatedAt' => null
        ];
    }
    return $out;
}
?>