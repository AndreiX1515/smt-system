<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require __DIR__ . "/../conn.php";

$method = $_SERVER['REQUEST_METHOD'];

function is_valid_ymd($s): bool {
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1;
}

function calc_booked_seats_by_date(mysqli $conn, int $packageId, string $startDate, string $endDate): array {
    // returns map ['YYYY-MM-DD' => bookedSeats]
    $map = [];
    if ($packageId <= 0 || !is_valid_ymd($startDate) || !is_valid_ymd($endDate)) return $map;

    $stmt = $conn->prepare("
        SELECT DATE(departureDate) AS d,
               SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
        FROM bookings
        WHERE packageId = ?
          AND DATE(departureDate) >= ?
          AND DATE(departureDate) <= ?
          AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
          AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
        GROUP BY DATE(departureDate)
    ");
    if (!$stmt) return $map;
    $stmt->bind_param('iss', $packageId, $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
        $ds = substr((string)($r['d'] ?? ''), 0, 10);
        if ($ds === '') continue;
        $map[$ds] = (int)($r['booked'] ?? 0);
    }
    $stmt->close();
    return $map;
}

function find_next_land_available_date(mysqli $conn, int $packageId, int $maxParticipants, ?string $salesStart, ?string $salesEnd): array {
    // returns ['date' => 'YYYY-MM-DD'|null, 'remaining' => int|null]
    if ($packageId <= 0 || $maxParticipants <= 0) return ['date' => null, 'remaining' => null];

    $today = date('Y-m-d');
    $start = (is_valid_ymd((string)$salesStart) ? (string)$salesStart : $today);
    $end = (is_valid_ymd((string)$salesEnd) ? (string)$salesEnd : date('Y-m-d', strtotime($today . ' +365 days')));

    // start must be >= today
    if ($start < $today) $start = $today;
    if ($end < $start) return ['date' => null, 'remaining' => null];

    $bookedMap = calc_booked_seats_by_date($conn, $packageId, $start, $end);

    $d = new DateTime($start);
    $endDt = new DateTime($end);
    $guard = 0;
    while ($d <= $endDt && $guard < 400) {
        $ds = $d->format('Y-m-d');
        $booked = (int)($bookedMap[$ds] ?? 0);
        $remaining = max($maxParticipants - $booked, 0);
        if ($remaining > 0) {
            return ['date' => $ds, 'remaining' => $remaining];
        }
        $d->modify('+1 day');
        $guard++;
    }
    return ['date' => null, 'remaining' => null];
}

// mysqli bind_param ""  , (...$params)   .
// (  //json_encode    PHP 8+    500 )
function mysqli_bind_params_by_ref($stmt, string $types, array &$params): bool {
    $bind = [];
    $bind[] = $types;
    foreach ($params as $i => $_) {
        $bind[] = &$params[$i];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

// Method routing
switch ($method) {
    case 'GET':
        handleGetPackages();
        break;
    case 'POST':
        handleCreatePackage();
        break;
    case 'PUT':
        handleUpdatePackage();
        break;
    case 'DELETE':
        handleDeletePackage();
        break;
    default:
        send_json_response(['success' => false, 'message' => '   .'], 405);
}

function handleGetPackages() {
    global $conn;

    try {
        //  
        $category = $_GET['category'] ?? '';
        $subCategory = $_GET['subCategory'] ?? $_GET['sub_category'] ?? '';
        $packageId = $_GET['id'] ?? '';
        $search = $_GET['search'] ?? '';
        //  (B2B/B2C) 
        // :       .
        // - B2B (clientType=Wholeseller): B2B 
        // - B2C (Retailer/): B2C(+NULL) 
        // - admin/super     salesTarget  
        $rawSalesTarget = $_GET['salesTarget'] ?? ($_GET['sales_target'] ?? '');
        $rawSalesTarget = strtoupper(trim((string)$rawSalesTarget));

        $sessionAccountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
        $sessionAccountId = $sessionAccountId !== null ? (int)$sessionAccountId : 0;
        //  (admin/agent)  agent_accountId  .
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        $agentAccountId = $agentAccountId !== null ? (int)$agentAccountId : 0;
        $sessionRole = $_SESSION['accountRole'] ?? $_SESSION['accountType'] ?? $_SESSION['account_type'] ?? ($_SESSION['userType'] ?? '');
        if (empty($sessionRole) && !empty($_SESSION['admin_accountId'])) $sessionRole = $_SESSION['admin_userType'] ?? 'admin_ph';
        $isAdmin = in_array($sessionRole, ['admin_ph', 'admin_kr', 'super', 'super_admin', 'agent_admin'], true);

        $isB2BUser = false;
        // B2B/B2C 판별: accounts.accountType 기반
        // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B
        // - accountType IN ('guest', 'guide', 'cs', '') → B2C
        if ($sessionAccountId > 0 && !$isAdmin) {
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
        } elseif ($agentAccountId > 0 && !$isAdmin) {
            $isB2BUser = true;
        }

        $salesTarget = $rawSalesTarget;
        if (!$isAdmin) {
            if ($isB2BUser) {
                // 세션 기반으로 B2B 사용자임이 확인됨
                $salesTarget = 'B2B';
            } elseif ($rawSalesTarget === 'B2B' && $sessionAccountId <= 0) {
                // 세션이 없지만 프론트엔드(localStorage)가 B2B를 요청한 경우 신뢰
                // (agent/admin 로그인 후 세션 쿠키가 AJAX에 포함되지 않는 경우 대비)
                $salesTarget = 'B2B';
            } else {
                $salesTarget = 'B2C';
            }
        }
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;
        // purchasableOnly " " ,   "    "   .
        // ()        .
        $purchasableOnly = ($_GET['purchasableOnly'] ?? $_GET['purchasable_only'] ?? $_GET['onlyPurchasable'] ?? '') ? true : false;
        
        // packages  
        //   
        $check_column = $conn->query("SHOW COLUMNS FROM packages LIKE 'packageCategory'");
        $has_category_column = $check_column->num_rows > 0;
        
        //   
        if ($packageId) {
            getSinglePackage($packageId, $salesTarget, $isAdmin);
            return;
        }

        // ()    "    " 
        if (!$isAdmin) {
            $purchasableOnly = true;
        }
        
        //     - packages
        // NOTE: purchasableOnly(  ) /
        // -  : package_available_dates + bookings() nextDate/remainingSeats/flightPrice
        // -  :   availCount=0   , '  sold out'  PHP .
        $query = "SELECT p.*,
                         paAny.availCount AS availCount,
                         paNext.nextDate AS nextAvailableDate,
                         MAX(pa.price) AS nextFlightPrice,
                         MAX(paRemain.remainingSeats) AS nextAvailableSeats
                  FROM packages p
                  LEFT JOIN (
                        SELECT package_id, COUNT(*) AS availCount
                        FROM package_available_dates
                        GROUP BY package_id
                  ) paAny ON paAny.package_id = p.packageId
                  LEFT JOIN (
                        SELECT pa.package_id, MIN(pa.available_date) AS nextDate
                        FROM package_available_dates pa
                        WHERE pa.available_date >= CURDATE()
                          AND pa.status IN ('available','confirmed','open')
                          AND COALESCE(pa.capacity, 0) > 0
                        GROUP BY pa.package_id
                  ) paNext ON paNext.package_id = p.packageId
                  LEFT JOIN package_available_dates pa
                         ON pa.package_id = p.packageId
                        AND pa.available_date = paNext.nextDate
                  LEFT JOIN (
                        SELECT pa.package_id, pa.available_date,
                               (pa.capacity - COALESCE(b.booked,0)) AS remainingSeats
                        FROM package_available_dates pa
                        LEFT JOIN (
                            SELECT packageId, DATE(departureDate) AS d,
                                   SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                            FROM bookings
                            WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                              AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                            GROUP BY packageId, DATE(departureDate)
                        ) b
                          ON b.packageId = pa.package_id
                         AND b.d = pa.available_date
                  ) paRemain
                    ON paRemain.package_id = pa.package_id
                   AND paRemain.available_date = paNext.nextDate
                  WHERE p.isActive = 1
                    AND (p.status IS NULL OR p.status = 'active')";
        
        $params = [];
        
        //  
        // -  daytrip    oneday   
        $categoryMap = [
            'daytrip' => 'oneday'
        ];
        $dbCategory = $categoryMap[$category] ?? $category;
        if (!empty($category) && $category !== 'all') {
            $query .= " AND p.packageCategory = ?";
            $params[] = $dbCategory;
        }

        //  
        if (!empty($subCategory) && $subCategory !== 'all') {
            $query .= " AND p.subCategory = ?";
            $params[] = $subCategory;
        }

        //  
        if (!empty($search)) {
            $query .= " AND (p.packageName LIKE ? OR p.packageDescription LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // sales_target 필터링 제거 - 이중 가격 시스템으로 변경
        // 모든 상품이 모든 사용자에게 노출됨 (가격만 다르게 표시)

        $having = "";
        //
        if ($purchasableOnly) {
            // 판매 가능 여부는 package_available_dates 기준으로 판단
            // - sales_start_date/sales_end_date 필터 제거 (package_available_dates에 가용 날짜가 있으면 판매 가능)
            // - 가용 날짜 있음 (availCount > 0): nextAvailableDate가 있어야 함 (capacity > 0인 날짜)
            // - 가용 날짜 없음 (availCount = 0): maxParticipants > 0이면 표시 (레거시 호환)
            $having = " HAVING ( (COALESCE(availCount,0) > 0 AND nextAvailableDate IS NOT NULL) OR (COALESCE(availCount,0) = 0 AND COALESCE(p.maxParticipants,0) > 0) )";
        }

        $query .= " GROUP BY p.packageId" . $having . " ORDER BY p.createdAt DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $types = str_repeat('s', count($params) - 2) . 'ii';
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $package = processPackageData($row);
            $packages[] = $package;
        }

        //  (= package_available_dates )  "  sold out"
        // - () ,
        // - 좌석수 0이어도 검색 가능하도록 조건 완화
        if ($purchasableOnly && !empty($packages)) {
            $filtered = [];
            foreach ($packages as $pkg) {
                $availCount = (int)($pkg['availCount'] ?? 0);
                if ($availCount > 0) {
                    //  : nextAvailableDate  ( 0 )
                    if (!empty($pkg['nextAvailableDate'])) {
                        $filtered[] = $pkg;
                    }
                    continue;
                }
                // 레거시: package_available_dates가 없는 상품은 maxParticipants 기준으로 next date 계산
                $maxP = (int)($pkg['maxParticipants'] ?? 0);
                if ($maxP <= 0) continue;

                // sales_start_date/sales_end_date 대신 null 전달 (오늘~365일 후 범위로 검색)
                $next = find_next_land_available_date($conn, (int)$pkg['packageId'], $maxP, null, null);
                if (!empty($next['date']) && (int)($next['remaining'] ?? 0) > 0) {
                    $pkg['nextAvailableDate'] = $next['date'];
                    $pkg['nextAvailableSeats'] = (int)$next['remaining'];
                    $filtered[] = $pkg;
                }
            }
            $packages = $filtered;
        }
        
        // IMPORTANT:
        // /    ()  
        // "    / "  .
        //        .
        
        //      - packages  
        $countQuery = "SELECT COUNT(*) as total FROM packages WHERE isActive = 1
                       AND (status IS NULL OR status = 'active')";
        $countParams = [];

        if (!empty($category) && $category !== 'all') {
            $countQuery .= " AND packageCategory = ?";
            $countParams[] = $dbCategory;
        }

        if (!empty($subCategory) && $subCategory !== 'all') {
            $countQuery .= " AND subCategory = ?";
            $countParams[] = $subCategory;
        }

        if (!empty($search)) {
            $countQuery .= " AND (packageName LIKE ? OR packageDescription LIKE ?)";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
        }

        // sales_target 필터링 제거 - 이중 가격 시스템으로 변경 (count query)
        
        $countStmt = $conn->prepare($countQuery);
        if (!empty($countParams)) {
            $types = str_repeat('s', count($countParams));
            mysqli_bind_params_by_ref($countStmt, $types, $countParams);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'] ?? count($packages);
        
        //  
        $response = [
            'success' => true,
            'message' => '   .',
            'data' => $packages,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ],
            'category' => $category
        ];
        
        send_json_response($response);
        
    } catch (Exception $e) {
        log_activity("system", "Packages API error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  : ' . $e->getMessage()], 500);
    }
}

function getSamplePackages($category) {
    $sample_packages = [
        // Season 
            [
                'packageId' => 1,
                'packageName' => '     5 6 ',
                'packagePrice' => 450000,
                'packageCategory' => 'season',
                'subCategory' => 'spring',
                'packageImage' => '@img_banner1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 2,
                'packageName' => '   3 4',
                'packagePrice' => 280000,
                'packageCategory' => 'season',
                'subCategory' => 'spring',
                'packageImage' => '@img_card1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 3,
                'packageName' => '  2 3',
                'packagePrice' => 180000,
                'packageCategory' => 'season',
                'subCategory' => 'autumn',
                'packageImage' => '@img_card2.jpg',
                'flights' => []
            ],
            [
                'packageId' => 4,
                'packageName' => '   4 5',
                'packagePrice' => 350000,
                'packageCategory' => 'season',
                'subCategory' => 'summer',
                'packageImage' => '@img_travel.jpg',
                'flights' => []
            ],
            // Region 
            [
                'packageId' => 5,
                'packageName' => '·   3 4',
                'packagePrice' => 220000,
                'packageCategory' => 'region',
                'subCategory' => 'gyeongju',
                'packageImage' => '@img_card2.jpg',
                'flights' => []
            ],
            [
                'packageId' => 6,
                'packageName' => '   2 3',
                'packagePrice' => 160000,
                'packageCategory' => 'region',
                'subCategory' => 'gyeongju',
                'packageImage' => '@img_banner1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 7,
                'packageName' => '   4 5',
                'packagePrice' => 320000,
                'packageCategory' => 'region',
                'subCategory' => 'gangwon',
                'packageImage' => '@img_card1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 8,
                'packageName' => '   5 6',
                'packagePrice' => 420000,
                'packageCategory' => 'region',
                'subCategory' => 'jeju',
                'packageImage' => '@img_travel.jpg',
                'flights' => []
            ],
            // Theme 
            [
                'packageId' => 9,
                'packageName' => '   3 4',
                'packagePrice' => 290000,
                'packageCategory' => 'theme',
                'subCategory' => 'culture',
                'packageImage' => '@img_card1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 10,
                'packageName' => 'K-POP  2 3',
                'packagePrice' => 250000,
                'packageCategory' => 'theme',
                'subCategory' => 'kpop',
                'packageImage' => '@img_card2.jpg',
                'flights' => []
            ],
            [
                'packageId' => 11,
                'packageName' => '  2 3',
                'packagePrice' => 180000,
                'packageCategory' => 'theme',
                'subCategory' => 'nature',
                'packageImage' => '@img_banner1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 12,
                'packageName' => '  4 5',
                'packagePrice' => 380000,
                'packageCategory' => 'theme',
                'subCategory' => 'food',
                'packageImage' => '@img_travel.jpg',
                'flights' => []
            ],
            // Private 
            [
                'packageId' => 13,
                'packageName' => '   ',
                'packagePrice' => 850000,
                'packageCategory' => 'private',
                'subCategory' => 'premium',
                'packageImage' => '@img_banner1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 14,
                'packageName' => 'VIP   ',
                'packagePrice' => 1200000,
                'packageCategory' => 'private',
                'subCategory' => 'vip',
                'packageImage' => '@img_card1.jpg',
                'flights' => []
            ],
            [
                'packageId' => 15,
                'packageName' => '   ',
                'packagePrice' => 650000,
                'packageCategory' => 'private',
                'subCategory' => 'custom',
                'packageImage' => '@img_card2.jpg',
                'flights' => []
            ],
            [
                'packageId' => 16,
                'packageName' => '   ',
                'packagePrice' => 980000,
                'packageCategory' => 'private',
                'subCategory' => 'luxury',
                'packageImage' => '@img_travel.jpg',
                'flights' => []
            ]
    ];
    
    //  
    if (!empty($category)) {
        return array_values(array_filter($sample_packages, function($pkg) use ($category) {
            return $pkg['packageCategory'] === $category;
        }));
    } else {
        return $sample_packages;
    }
}

function processPackageData($row) {
    $resolveImage = function ($row) {
        // : thumbnail_image > product_images > packageImageUrl/packageImage > detail_image > placeholder
        $img = '';
        try {
            $thumb = trim((string)($row['thumbnail_image'] ?? ''));
            if ($thumb !== '') $img = $thumb;
        } catch (Throwable $e) {}

        if ($img === '') {
            try {
                $raw = $row['product_images'] ?? '';
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        // array  {en:..., tl:...} 
                        if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
                            $lang = (isset($_GET['lang']) && in_array($_GET['lang'], ['en','tl'], true)) ? $_GET['lang'] : 'en';
                            $pick = $decoded[$lang] ?? ($decoded['en'] ?? null);
                            if (is_string($pick)) $img = $pick;
                        } else {
                            $first = $decoded[0] ?? '';
                            if (is_string($first)) $img = $first;
                        }
                    } elseif (is_string($decoded)) {
                        $img = $decoded;
                    } else {
                        $img = (string)$raw;
                    }
                }
            } catch (Throwable $e) {}
        }

        if ($img === '') {
            $img = trim((string)($row['packageImageUrl'] ?? ''));
        }
        if ($img === '') {
            $img = trim((string)($row['packageImage'] ?? ''));
        }
        if ($img === '') {
            $img = trim((string)($row['detail_image'] ?? ''));
        }
        // NOTE: (placeholder)   .
        //      ""  .
        if ($img === '') $img = '';

        // normalize:    /uploads/products/  
        $img = str_replace('\\', '/', (string)$img);
        if (str_starts_with($img, 'uploads/')) $img = '/' . $img;
        if (str_starts_with($img, 'products/')) $img = '/uploads/' . $img;
        if ($img !== '' && !str_starts_with($img, 'http://') && !str_starts_with($img, 'https://') && !str_starts_with($img, '/') && !str_contains($img, '/')) {
            //   
            $img = '/uploads/products/' . $img;
        }
        //   '/uploads/products/'   
        if ($img === '/uploads/products/' || $img === '/uploads/products') $img = '';
        //         (   /placeholder fallback)
        if ($img && !str_starts_with($img, 'http://') && !str_starts_with($img, 'https://')) {
            $fs = '/var/www/html' . (str_starts_with($img, '/') ? $img : ('/' . $img));
            if (!file_exists($fs)) {
                $img = '';
            }
        }
        return $img;
    };

    // JSON
    $mainImage = $resolveImage($row);

    // B2B 가격 설정
    $b2bPrice = isset($row['b2b_price']) && $row['b2b_price'] !== null ? floatval($row['b2b_price']) : null;
    $b2bChildPrice = isset($row['b2b_child_price']) && $row['b2b_child_price'] !== null ? floatval($row['b2b_child_price']) : null;
    $b2bInfantPrice = isset($row['b2b_infant_price']) && $row['b2b_infant_price'] !== null ? floatval($row['b2b_infant_price']) : null;

    // 가격 텍스트 오버라이드 (문자열 가격 표시용)
    $priceDisplayText = isset($row['price_display_text']) && trim((string)$row['price_display_text']) !== ''
        ? trim((string)$row['price_display_text']) : null;
    $b2bPriceDisplayText = isset($row['b2b_price_display_text']) && trim((string)$row['b2b_price_display_text']) !== ''
        ? trim((string)$row['b2b_price_display_text']) : null;

    $package = [
        'packageId' => $row['packageId'],
        'packageName' => $row['packageName'],
        // B2C 가격 (기본)
        'packagePrice' => floatval($row['packagePrice']),
        'priceDisplayText' => $priceDisplayText,
        'childPrice' => isset($row['childPrice']) && $row['childPrice'] !== null ? floatval($row['childPrice']) : null,
        'infantPrice' => isset($row['infantPrice']) && $row['infantPrice'] !== null ? floatval($row['infantPrice']) : null,
        // B2B 가격 (에이전트/관리자용)
        'b2bPrice' => $b2bPrice,
        'b2bPriceDisplayText' => $b2bPriceDisplayText,
        'b2bChildPrice' => $b2bChildPrice,
        'b2bInfantPrice' => $b2bInfantPrice,
        //   ()
        'singleRoomFee' => isset($row['single_room_fee']) && $row['single_room_fee'] !== null ? floatval($row['single_room_fee']) : null,
        'packageCategory' => $row['packageCategory'] ?? 'season',
        'subCategory' => $row['subCategory'] ?? null,
        'packageDescription' => $row['packageDescription'] ?? '',
        'duration_days' => $row['duration_days'] ?? 3,
        'meeting_location' => $row['meeting_location'] ?? '',
        'meeting_time' => $row['meeting_time'] ?? '09:00:00',
        'packageType' => $row['packageType'] ?? 'standard',
        'minParticipants' => $row['minParticipants'] ?? 1,
        'maxParticipants' => $row['maxParticipants'] ?? 50,
        'sales_start_date' => $row['sales_start_date'] ?? null,
        'sales_end_date' => $row['sales_end_date'] ?? null,
        'sales_target' => $row['sales_target'] ?? null,
        'difficulty' => $row['difficulty'] ?? 'easy',
        'formattedPrice' => '₱' . number_format($row['packagePrice'], 0),
        'includes' => json_decode($row['includes'] ?? '[]', true),
        'excludes' => json_decode($row['excludes'] ?? '[]', true),
        'highlights' => json_decode($row['highlights'] ?? '[]', true),
        // : /      .
        'imageUrl' => $mainImage,
        'images' => ($mainImage !== '' ? [$mainImage] : []),
        'roomOptions' => [],
        'packageOptions' => [],
        //   ( : package_pricing_options)
        'pricingOptions' => [],
        // 상품 문서 파일 (flyer/detail/itinerary)
        'flyer_file' => $row['flyer_file'] ?? null,
        'detail_file' => $row['detail_file'] ?? null,
        'itinerary_file' => $row['itinerary_file'] ?? null
    ];

    // (/)    
    $package['availCount'] = isset($row['availCount']) ? intval($row['availCount']) : null;
    $package['nextAvailableDate'] = $row['nextAvailableDate'] ?? null;
    $package['nextFlightPrice'] = isset($row['nextFlightPrice']) ? floatval($row['nextFlightPrice']) : null;
    $package['nextAvailableSeats'] = isset($row['nextAvailableSeats']) ? intval($row['nextAvailableSeats']) : null;
    
    // NOTE:  images  fallback  (placeholder   ).
    //        thumbnail_image/product_images   imageUrl/images  .
    
    //     (package_pricing_options)
    try {
        global $conn;
        $hasPricingTable = false;
        $tbl = $conn->query("SHOW TABLES LIKE 'package_pricing_options'");
        if ($tbl && $tbl->num_rows > 0) $hasPricingTable = true;
        if ($hasPricingTable) {
            $pid = intval($row['packageId'] ?? 0);
            if ($pid > 0) {
                $ps = $conn->prepare("SELECT option_name, price, b2b_price FROM package_pricing_options WHERE package_id = ? ORDER BY pricing_id ASC");
                if ($ps) {
                    $ps->bind_param('i', $pid);
                    $ps->execute();
                    $pr = $ps->get_result();
                    $opts = [];
                    while ($r = $pr->fetch_assoc()) {
                        $opts[] = [
                            'optionName' => $r['option_name'] ?? '',
                            'price' => isset($r['price']) ? floatval($r['price']) : 0,
                            'b2bPrice' => isset($r['b2b_price']) ? floatval($r['b2b_price']) : null,
                        ];
                    }
                    $ps->close();
                    $package['pricingOptions'] = $opts;

                    // pricingOptions  adult/child/infant   (   )
                    if (!empty($opts)) {
                        foreach ($opts as $o) {
                            $name = strtolower(trim((string)($o['optionName'] ?? '')));
                            $price = floatval($o['price'] ?? 0);
                            $b2bPrice = isset($o['b2bPrice']) ? floatval($o['b2bPrice']) : null;
                            if ($name === 'adult' || $name === 'adults' || str_contains($name, 'adult') || str_contains($name, '')) {
                                $package['packagePrice'] = $price;
                                if ($b2bPrice !== null) $package['b2bPrice'] = $b2bPrice;
                            } elseif ($name === 'child' || str_contains($name, 'child') || str_contains($name, '')) {
                                $package['childPrice'] = $price;
                                if ($b2bPrice !== null) $package['b2bChildPrice'] = $b2bPrice;
                            } elseif ($name === 'infant' || str_contains($name, 'infant') || str_contains($name, '')) {
                                $package['infantPrice'] = $price;
                                if ($b2bPrice !== null) $package['b2bInfantPrice'] = $b2bPrice;
                            }
                        }
                        $package['formattedPrice'] = '₱' . number_format($package['packagePrice'] ?? 0, 0);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // ignore pricing options load errors
    }

        // 가격 계산:
        // - package_available_dates.price는 "해당 날짜의 패키지 총 가격" (항공권 포함)
        // - nextFlightPrice가 있으면 그것을 사용, 없으면 packagePrice 사용
        // - 두 가격을 합산하지 않음 (2배 방지)
    try {
        $landPrice = floatval($package['packagePrice'] ?? 0);
        $datePrice = floatval($package['nextFlightPrice'] ?? 0);
        $nextDate = trim((string)($package['nextAvailableDate'] ?? ''));

        if ($nextDate !== '' && $datePrice > 0) {
            // 가용 날짜가 있고 해당 날짜의 가격이 있으면 그것을 사용
            $package['rawPrice'] = $datePrice;
            $package['formattedPrice'] = '₱' . number_format($datePrice, 0) . '~';
        } else {
            // 없으면 기본 패키지 가격 사용
            $package['rawPrice'] = $landPrice;
            $package['formattedPrice'] = '₱' . number_format($landPrice, 0) . '~';
        }
    } catch (Throwable $e) {
        // ignore
    }

    // NOTE: Upload Flyer/Detail/Itinerary   .

    return $package;
}

function getSinglePackage($packageId, $salesTarget = 'B2C', $isAdmin = false) {
    global $conn;

    // :     + sales_target  
    $query = "SELECT p.*
              FROM packages p
              WHERE p.packageId = ?";
    if (!$isAdmin) {
        // 일반 사용자 - 활성 상품만 (sales_target 필터 제거 - 이중 가격 시스템)
        $query = "SELECT p.*
                  FROM packages p
                  WHERE p.packageId = ?
                    AND p.isActive = 1
                    AND (p.status IS NULL OR p.status = 'active')";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => '   : ' . $conn->error], 500);
        return;
    }
    
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $package = processPackageData($row);
        send_json_response(['success' => true, 'data' => $package]);
    } else {
        send_json_response(['success' => false, 'message' => '   .'], 404);
    }
}

function handleCreatePackage() {
    //   (    )
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    //   
    // - : accountRole/accountType/account_type  
    // - : admin  userType/admin_accountId        
    $accountRole = $_SESSION['accountRole']
        ?? $_SESSION['accountType']
        ?? $_SESSION['account_type']
        ?? ($_SESSION['userType'] ?? '');

    // admin 세션 확인
    if (empty($accountRole) && !empty($_SESSION['admin_accountId'])) {
        $accountRole = $_SESSION['admin_userType'] ?? 'admin_ph';
    }

    // userType admin/super 정규화
    if (in_array($accountRole, ['super', 'super_admin'], true)) {
        $accountRole = 'super_admin';
    } elseif (in_array($accountRole, ['admin_ph', 'admin_kr'], true)) {
        // keep as is
    }

    if (!in_array($accountRole, ['super_admin', 'agent_admin', 'admin_ph', 'admin_kr'])) {
        send_json_response([
            'success' => false,
            'message' => 'Admin permission required. (super_admin, agent_admin, admin_ph, admin_kr)'
        ], 401);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['packageName', 'packagePrice', 'packageCategory', 'packageDescription'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            send_json_response(['success' => false, 'message' => "  : $field"], 400);
            return;
        }
    }
    
    global $conn;
    
    // NOTE:
    // packages     /   .
    //   'images'  ( product_images, packageImage  ) INSERT  500 .
    //     ,     INSERT .
    $sql = "INSERT INTO packages (
                packageName, packagePrice, packageCategory, packageDescription,
                duration_days, meeting_location, meeting_time, packageType,
                includes, excludes, highlights,
                minParticipants, maxParticipants, difficulty
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response([
            'success' => false,
            'message' => '    .',
            'error' => $conn->error ?: 'unknown'
        ], 500);
        return;
    }

    // bind_param    →    (   )  
    $params = [
        (string)$input['packageName'],
        (string)$input['packagePrice'],
        (string)$input['packageCategory'],
        (string)$input['packageDescription'],
        (string)($input['duration_days'] ?? 3),
        (string)($input['meeting_location'] ?? ''),
        (string)($input['meeting_time'] ?? '09:00:00'),
        (string)($input['packageType'] ?? 'standard'),
        json_encode($input['includes'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($input['excludes'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($input['highlights'] ?? [], JSON_UNESCAPED_UNICODE),
        (string)($input['minParticipants'] ?? 1),
        (string)($input['maxParticipants'] ?? 50),
        (string)($input['difficulty'] ?? 'easy'),
    ];
    $types = str_repeat('s', count($params));
    $ok = mysqli_bind_params_by_ref($stmt, $types, $params);
    if (!$ok) {
        error_log("Create Package - bind_param failed: " . ($stmt->error ?: 'unknown'));
        send_json_response([
            'success' => false,
            'message' => '   .',
            'error' => $stmt->error ?: 'unknown'
        ], 500);
        return;
    }
    
    if ($stmt->execute()) {
        $packageId = $conn->insert_id;
        log_activity("system", "Package created: {$input['packageName']} (ID: $packageId)");
        send_json_response(['success' => true, 'packageId' => $packageId, 'message' => '  .']);
    } else {
        error_log("Create Package - execute failed: " . ($stmt->error ?: ($conn->error ?: 'unknown')));
        send_json_response([
            'success' => false,
            'message' => '  .',
            'error' => $stmt->error ?: ($conn->error ?: 'unknown')
        ], 500);
    }
}

function handleUpdatePackage() {
    //   (    )
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    //   ( )
    $accountRole = $_SESSION['accountRole']
        ?? $_SESSION['accountType']
        ?? $_SESSION['account_type']
        ?? ($_SESSION['userType'] ?? '');

    if (empty($accountRole) && !empty($_SESSION['admin_accountId'])) {
        $accountRole = $_SESSION['admin_userType'] ?? 'admin_ph';
    }
    if (in_array($accountRole, ['super', 'super_admin'], true)) {
        $accountRole = 'super_admin';
    } elseif (in_array($accountRole, ['admin_ph', 'admin_kr'], true)) {
        // keep as is
    }

    if (!in_array($accountRole, ['super_admin', 'agent_admin', 'admin_ph', 'admin_kr'])) {
        send_json_response([
            'success' => false,
            'message' => 'Admin permission required. (super_admin, agent_admin, admin_ph, admin_kr)'
        ], 401);
        return;
    }

    $packageId = $_GET['id'] ?? '';
    if (!$packageId) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    global $conn;
    
    $updateFields = [];
    $params = [];
    $types = '';
    
    $allowedFields = [
        'packageName' => 's', 'packagePrice' => 'd', 'packageCategory' => 's', 
        'packageDescription' => 's', 'duration_days' => 'i', 'meeting_location' => 's',
        'meeting_time' => 's', 'packageType' => 's', 'minParticipants' => 'i',
        'maxParticipants' => 'i', 'difficulty' => 's', 'isActive' => 'i'
    ];

    foreach ($allowedFields as $field => $type) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
            $types .= $type;
        }
    }
    
    // JSON  
    $jsonFields = ['includes', 'excludes', 'highlights', 'images'];
    foreach ($jsonFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = json_encode($input[$field]);
            $types .= 's';
        }
    }

    if (empty($updateFields)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
        return;
    }

    $sql = "UPDATE packages SET " . implode(', ', $updateFields) . ", updatedAt = NOW() WHERE packageId = ?";
    $params[] = $packageId;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => '    .', 'error' => $conn->error ?: 'unknown'], 500);
        return;
    }
    if (!mysqli_bind_params_by_ref($stmt, $types, $params)) {
        send_json_response(['success' => false, 'message' => '   .', 'error' => $stmt->error ?: 'unknown'], 500);
        return;
    }
    
    if ($stmt->execute()) {
        log_activity("system", "Package updated: ID $packageId");
        send_json_response(['success' => true, 'message' => '  .']);
    } else {
        send_json_response(['success' => false, 'message' => '  .', 'error' => $stmt->error ?: ($conn->error ?: 'unknown')], 500);
    }
}

function handleDeletePackage() {
    try {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $accountRole = $_SESSION['accountRole']
            ?? $_SESSION['accountType']
            ?? $_SESSION['account_type']
            ?? ($_SESSION['userType'] ?? '');
        if (empty($accountRole) && !empty($_SESSION['admin_accountId'])) {
            $accountRole = $_SESSION['admin_userType'] ?? 'admin_ph';
        }
        if (in_array($accountRole, ['super', 'super_admin'], true)) {
            $accountRole = 'super_admin';
        } elseif (in_array($accountRole, ['admin_ph', 'admin_kr'], true)) {
            // keep as is
        }

        if (!in_array($accountRole, ['super_admin', 'agent_admin', 'admin_ph', 'admin_kr'])) {
            send_json_response([
                'success' => false,
                'message' => 'Admin permission required. (super_admin, agent_admin, admin_ph, admin_kr)'
            ], 401);
            return;
        }

        $packageId = $_GET['id'] ?? '';
        if (!$packageId) {
            send_json_response(['success' => false, 'message' => ' ID .'], 400);
            return;
        }

        global $conn;
        $packageId = intval($packageId);
        if ($packageId <= 0) {
            send_json_response(['success' => false, 'message' => '   ID.'], 400);
            return;
        }

        $pkgStmt = $conn->prepare("SELECT packageName FROM packages WHERE packageId = ? LIMIT 1");
        if (!$pkgStmt) {
            throw new Exception('    : ' . ($conn->error ?: '   '));
        }
        $pkgStmt->bind_param('i', $packageId);
        $pkgStmt->execute();
        $pkgResult = $pkgStmt->get_result();
        if (!$pkgResult || $pkgResult->num_rows === 0) {
            $pkgStmt->close();
            send_json_response(['success' => false, 'message' => '    .'], 404);
            return;
        }
        $packageName = $pkgResult->fetch_assoc()['packageName'] ?? '  ';
        $pkgStmt->close();

        $tablesByPackageId = [
            'flight' => ' ',
            'package_schedules' => ' ',
            'package_travel_costs' => '  ',
            'package_pricing_options' => '  ',
            'room_options' => '  ',
            'package_options' => '  ',
            'booking_rooms' => '  ',
            'booking_travelers' => ' ',
            'booking_services' => '  ',
            'bookings' => ' '
        ];

        $tablesByTransactNo = [
            'guest' => ' ',
            'roominglist' => '  ',
            'visa_applications' => '  ',
            'visarequirements' => ' ',
            'payment' => ' ',
            'paymentc' => ' ()',
            'fitpayment' => 'FIT  ',
            'bookingcomments' => ' ',
            'booking_services' => '  ',
            'booking_rooms' => '  ',
            'booking_travelers' => ' ',
            'guide_assignments' => '  ',
            'request' => ' ',
            'reviews' => ' ',
            'bookings' => ' '
        ];

        $deletedSummary = [];
        $conn->begin_transaction();

        //    (bookings)  transactNo(= bookingId) 
        $bookingNos = [];
        if (tableExists($conn, 'bookings') && columnExists($conn, 'bookings', 'packageId') && columnExists($conn, 'bookings', 'bookingId')) {
            $bookingStmt = $conn->prepare("SELECT COALESCE(NULLIF(transactNo,''), bookingId) as transactNo FROM bookings WHERE packageId = ?");
            if (!$bookingStmt) {
                throw new Exception('   : ' . ($conn->error ?: '   '));
            }
            $bookingStmt->bind_param('i', $packageId);
            $bookingStmt->execute();
            $bookingResult = $bookingStmt->get_result();
            while ($row = $bookingResult->fetch_assoc()) {
                if (!empty($row['transactNo'])) {
                    $bookingNos[] = $row['transactNo'];
                }
            }
            $bookingStmt->close();
        }

        if (!empty($bookingNos)) {
            $placeholders = implode(',', array_fill(0, count($bookingNos), '?'));
            $types = str_repeat('s', count($bookingNos));

            foreach ($tablesByTransactNo as $table => $label) {
                if (!tableExists($conn, $table) || !columnExists($conn, $table, 'transactNo')) {
                    continue;
                }

                $sql = "DELETE FROM `$table` WHERE transactNo IN ($placeholders)";
                $deleteStmt = $conn->prepare($sql);
                if (!$deleteStmt) {
                    throw new Exception("     : $table - " . ($conn->error ?: '   '));
                }

                $deleteStmt->bind_param($types, ...$bookingNos);
                if (!$deleteStmt->execute()) {
                    $err = $deleteStmt->error ?: '   ';
                    $deleteStmt->close();
                    throw new Exception("$label  : " . $err);
                }

                if ($deleteStmt->affected_rows > 0) {
                    $deletedSummary[] = "$label {$deleteStmt->affected_rows}";
                }
                $deleteStmt->close();
            }
        }

        foreach ($tablesByPackageId as $table => $label) {
            if (!tableExists($conn, $table) || !columnExists($conn, $table, 'packageId')) {
                continue;
            }

            $sql = "DELETE FROM `$table` WHERE packageId = ?";
            $deleteStmt = $conn->prepare($sql);
            if (!$deleteStmt) {
                throw new Exception("     : $table - " . ($conn->error ?: '   '));
            }
            $deleteStmt->bind_param('i', $packageId);
            if (!$deleteStmt->execute()) {
                $err = $deleteStmt->error ?: '   ';
                $deleteStmt->close();
                throw new Exception("$label  : " . $err);
            }

            if ($deleteStmt->affected_rows > 0) {
                $deletedSummary[] = "$label {$deleteStmt->affected_rows}";
            }
            $deleteStmt->close();
        }

        $deletePackageStmt = $conn->prepare("DELETE FROM packages WHERE packageId = ?");
        if (!$deletePackageStmt) {
            throw new Exception('    : ' . ($conn->error ?: '   '));
        }
        $deletePackageStmt->bind_param('i', $packageId);
        if (!$deletePackageStmt->execute()) {
            $err = $deletePackageStmt->error ?: '   ';
            $deletePackageStmt->close();
            throw new Exception('  : ' . $err);
        }
        $deletePackageStmt->close();

        $conn->commit();

        $accountId = $_SESSION['accountId'] ?? 'system';
        try {
            log_activity($accountId, "package_deleted", "Package permanently deleted: ID $packageId, Name: $packageName");
        } catch (Exception $e) {
            error_log("Log activity failed: " . $e->getMessage());
        }

        $summaryText = empty($deletedSummary) ? '  ' : implode(', ', $deletedSummary);
        send_json_response([
            'success' => true,
            'message' => '    .',
            'deletedPackageId' => $packageId,
            'deletedPackageName' => $packageName,
            'deletedRelations' => $summaryText
        ]);
        
    } catch (Exception $e) {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }
        error_log("Delete package exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        send_json_response([
            'success' => false, 
            'message' => '    : ' . $e->getMessage()
        ], 500);
    } catch (Error $e) {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }
        error_log("Delete package fatal error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        send_json_response([
            'success' => false, 
            'message' => '     : ' . $e->getMessage()
        ], 500);
    }
}

function tableExists($conn, $tableName) {
    if (!($conn instanceof mysqli)) {
        return false;
    }
    $tableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $tableName, $columnName) {
    if (!($conn instanceof mysqli)) {
        return false;
    }
    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $result && $result->num_rows > 0;
}

?>
