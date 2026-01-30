<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

// B2B/B2C 구분 (product-detail.php와 동일한 로직)
$isB2B = false;
try {
    $sessionAccountId = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
    $sessionAccountId = $sessionAccountId !== null ? (int)$sessionAccountId : 0;
    $agentSessionId = $_SESSION['agent_accountId'] ?? null;
    $agentSessionId = $agentSessionId !== null ? (int)$agentSessionId : 0;
    $sessionAccountType = strtolower(trim((string)($_SESSION['account_type'] ?? '')));

    if ($agentSessionId > 0 || in_array($sessionAccountType, ['agent', 'admin_ph', 'admin_kr'], true)) {
        $isB2B = true;
    } elseif ($sessionAccountId > 0) {
        $stmtBiz = $conn->prepare("
            SELECT COALESCE(c.clientType, '') AS clientType
            FROM accounts a
            LEFT JOIN client c ON a.accountId = c.accountId
            WHERE a.accountId = ?
            LIMIT 1
        ");
        if ($stmtBiz) {
            $stmtBiz->bind_param('i', $sessionAccountId);
            $stmtBiz->execute();
            $rowBiz = $stmtBiz->get_result()->fetch_assoc();
            $stmtBiz->close();
            if ($rowBiz) {
                $clientType = strtolower(trim((string)($rowBiz['clientType'] ?? '')));
                $isB2B = ($clientType === 'wholeseller');
            }
        }
    }
} catch (Throwable $e) {
    // ignore (default: B2C)
}

$packageId = $_GET['id'] ?? '';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

if (empty($packageId)) {
    send_json_response(['success' => false, 'message' => ' ID .'], 400);
    exit;
}

try {
    //   
    error_log("Product availability API called with packageId: $packageId, year: $year, month: $month");

    //   
    $packageQuery = "SELECT * FROM packages WHERE packageId = ?";
    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->bind_param("i", $packageId);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();

    error_log("Database query executed, rows found: " . $packageResult->num_rows);

    if ($packageResult->num_rows === 0) {
        send_json_response(['success' => false, 'message' => '   .'], 404);
        exit;
    }

    $package = $packageResult->fetch_assoc();

    //     (    )
    $availability = generateAvailableDates($year, $month, $package, $conn, $isB2B);

    $response = [
        'success' => true,
        'message' => '    .',
        'data' => [
            'year' => (int)$year,
            'month' => (int)$month,
            'product' => [
                'packageId' => $package['packageId'],
                'packageName' => $package['packageName'],
                'packagePrice' => (float)$package['packagePrice'],
                'b2bPrice' => isset($package['b2b_price']) && $package['b2b_price'] !== null ? (float)$package['b2b_price'] : null,
                'childPrice' => isset($package['childPrice']) && $package['childPrice'] !== null ? (float)$package['childPrice'] : null,
                'b2bChildPrice' => isset($package['b2b_child_price']) && $package['b2b_child_price'] !== null ? (float)$package['b2b_child_price'] : null,
                'infantPrice' => isset($package['infantPrice']) && $package['infantPrice'] !== null ? (float)$package['infantPrice'] : null,
                'b2bInfantPrice' => isset($package['b2b_infant_price']) && $package['b2b_infant_price'] !== null ? (float)$package['b2b_infant_price'] : null,
                'packageCategory' => $package['packageCategory']
            ],
            'availability' => $availability
        ]
    ];

    send_json_response($response);

} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '      .',
        'error' => $e->getMessage()
    ], 500);
}

//     -  DB
function generateAvailableDates($year, $month, $package, $conn, $isB2B = false) {
    $availability = [];
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    $packageId = $package['packageId'];
    $basePrice = floatval($package['packagePrice']);
    // dev_tasks #115: packages.maxParticipants=0이면 날짜가 존재하더라도 예약 불가(닫힘) 처리
    $packageMaxParticipants = intval($package['maxParticipants'] ?? 0);
    $packageClosed = ($packageMaxParticipants <= 0);
    
    //  (robust: "YYYY-MM-DD ~ YYYY-MM-DD"   )
    $salesPeriodStart = null;
    $salesPeriodEnd = null;
    // start/end     (sales_period DATE  )
    $rawSales = '';
    if (!empty($package['sales_start_date']) && !empty($package['sales_end_date'])) {
        $rawSales = (string)$package['sales_start_date'] . ' ~ ' . (string)$package['sales_end_date'];
    } elseif (!empty($package['sales_period'])) {
        $rawSales = (string)$package['sales_period'];
    }
    if ($rawSales !== '') {
        $raw = (string)$rawSales;
        $m = [];
        if (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', $raw, $m) && !empty($m[1])) {
            $startStr = $m[1][0] ?? '';
            $endStr = $m[1][1] ?? ($m[1][0] ?? '');
            try {
                if ($startStr) $salesPeriodStart = new DateTime($startStr);
                if ($endStr) $salesPeriodEnd = new DateTime($endStr);
            } catch (Throwable $e) {
                $salesPeriodStart = null;
                $salesPeriodEnd = null;
            }
        } else {
            try {
                $salesPeriodStart = new DateTime($raw);
                $salesPeriodEnd = new DateTime($raw);
            } catch (Throwable $e) {
                $salesPeriodStart = null;
                $salesPeriodEnd = null;
            }
        }
    }
    
    //    
    $monthStart = new DateTime("$year-$month-01");
    $monthEnd = new DateTime("$year-$month-" . $monthStart->format('t'));
    $monthEnd->setTime(23, 59, 59);
    
    $monthStartStr = $monthStart->format('Y-m-d');
    $monthEndStr = $monthEnd->format('Y-m-d');

    // 1) package_available_dates 조회 (기존 '열림' 상태인 날짜만)
    $availabilityByDate = [];

    // Sale 할인 정보 조회 (활성화된 세일 중 해당 월에 적용되는 것)
    $saleDiscountByDateId = [];
    try {
        $tblSales = $conn->query("SHOW TABLES LIKE 'sales'");
        $tblSaleItems = $conn->query("SHOW TABLES LIKE 'sale_items'");
        $hasSaleTables = ($tblSales && $tblSales->num_rows > 0) && ($tblSaleItems && $tblSaleItems->num_rows > 0);

        if ($hasSaleTables) {
            $saleSt = $conn->prepare("
                SELECT si.package_available_date_id, s.discount_amount, s.sale_name
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                INNER JOIN package_available_dates pad ON pad.id = si.package_available_date_id
                WHERE s.is_active = 1
                  AND CURDATE() BETWEEN s.sale_start_date AND s.sale_end_date
                  AND pad.package_id = ?
                  AND pad.available_date >= ?
                  AND pad.available_date <= ?
            ");
            if ($saleSt) {
                $saleSt->bind_param('iss', $packageId, $monthStartStr, $monthEndStr);
                $saleSt->execute();
                $saleRs = $saleSt->get_result();
                while ($saleRow = $saleRs->fetch_assoc()) {
                    $dateId = (int)$saleRow['package_available_date_id'];
                    $saleDiscountByDateId[$dateId] = [
                        'discountAmount' => floatval($saleRow['discount_amount']),
                        'saleName' => $saleRow['sale_name']
                    ];
                }
                $saleSt->close();
            }
        }
    } catch (Throwable $e) {
        $saleDiscountByDateId = [];
    }

    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'package_available_dates'");
        $hasPkgAvail = ($tbl && $tbl->num_rows > 0);
        if ($hasPkgAvail) {
            $st = $conn->prepare("
                SELECT id, available_date, price, b2b_price, childPrice, b2b_child_price, infant_price, b2b_infant_price, singlePrice, capacity, flight_id, departure_time, status
                FROM package_available_dates
                WHERE package_id = ?
                  AND available_date >= ?
                  AND available_date <= ?
                  AND status = 'open'
                ORDER BY available_date ASC
            ");
            if ($st) {
                $st->bind_param('iss', $packageId, $monthStartStr, $monthEndStr);
                $st->execute();
                $rs = $st->get_result();
                while ($r = $rs->fetch_assoc()) {
                    $ds = substr((string)($r['available_date'] ?? ''), 0, 10);
                    if ($ds === '') continue;

                    $dateId = (int)$r['id'];
                    $saleDiscount = $saleDiscountByDateId[$dateId] ?? null;
                    $discountAmount = $saleDiscount ? $saleDiscount['discountAmount'] : 0;
                    $saleName = $saleDiscount ? $saleDiscount['saleName'] : null;

                    // 원본 가격
                    $originalPrice = floatval($r['price'] ?? 0);
                    $originalB2bPrice = isset($r['b2b_price']) && $r['b2b_price'] !== null ? floatval($r['b2b_price']) : null;

                    // 할인 적용 가격 계산
                    $finalPrice = $isB2B
                        ? floatval($r['b2b_price'] ?? $r['price'] ?? 0) - $discountAmount
                        : floatval($r['price'] ?? 0) - $discountAmount;
                    $finalPrice = max($finalPrice, 0); // 음수 방지

                    $finalB2bPrice = $originalB2bPrice !== null
                        ? max($originalB2bPrice - $discountAmount, 0)
                        : null;

                    $availabilityByDate[$ds] = [
                        'dateId' => $dateId,
                        'availableSeats' => intval($r['capacity'] ?? 0),
                        'price' => $finalPrice,
                        'originalPrice' => $originalPrice,
                        'b2bPrice' => $finalB2bPrice,
                        'originalB2bPrice' => $originalB2bPrice,
                        'childPrice' => isset($r['childPrice']) ? floatval($r['childPrice']) : null,
                        'b2bChildPrice' => isset($r['b2b_child_price']) && $r['b2b_child_price'] !== null ? floatval($r['b2b_child_price']) : null,
                        'infantPrice' => isset($r['infant_price']) ? floatval($r['infant_price']) : null,
                        'b2bInfantPrice' => isset($r['b2b_infant_price']) && $r['b2b_infant_price'] !== null ? floatval($r['b2b_infant_price']) : null,
                        'singlePrice' => isset($r['singlePrice']) ? floatval($r['singlePrice']) : null,
                        'flightId' => isset($r['flight_id']) ? (int)$r['flight_id'] : 0,
                        'departureTime' => substr((string)($r['departure_time'] ?? ''), 0, 5),
                        'discountAmount' => $discountAmount,
                        'saleName' => $saleName,
                        'isOnSale' => $discountAmount > 0,
                    ];
                }
                $st->close();
            }
        }
    } catch (Throwable $e) {
        $availabilityByDate = [];
    }

    // 2) bookedSeats( ) - month   
    $bookedByDate = [];
    try {
        $bst = $conn->prepare("
            SELECT departureDate, SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infants,0)) AS booked
            FROM bookings
            WHERE packageId = ?
              AND departureDate >= ?
              AND departureDate <= ?
              AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','rejected'))
              AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
            GROUP BY departureDate
        ");
        if ($bst) {
            $bst->bind_param('iss', $packageId, $monthStartStr, $monthEndStr);
            $bst->execute();
            $brs = $bst->get_result();
            while ($b = $brs->fetch_assoc()) {
                $ds = substr((string)($b['departureDate'] ?? ''), 0, 10);
                if ($ds === '') continue;
                $bookedByDate[$ds] = intval($b['booked'] ?? 0);
            }
            $bst->close();
        }
    } catch (Throwable $e) {
        $bookedByDate = [];
    }

    // 3) () flightId/  month  preload
    $flightIdByDate = [];
    $flightDepTimeByDate = [];
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'flight'");
        $hasFlightTbl = ($tbl && $tbl->num_rows > 0);
        if ($hasFlightTbl) {
            $fst = $conn->prepare("
                SELECT flightId, DATE(flightDepartureDate) AS d, flightDepartureTime
                FROM flight
                WHERE packageId = ?
                  AND is_active = 1
                  AND DATE(flightDepartureDate) >= ?
                  AND DATE(flightDepartureDate) <= ?
            ");
            if ($fst) {
                $fst->bind_param('iss', $packageId, $monthStartStr, $monthEndStr);
                $fst->execute();
                $frs = $fst->get_result();
                while ($f = $frs->fetch_assoc()) {
                    $ds = substr((string)($f['d'] ?? ''), 0, 10);
                    if ($ds === '') continue;
                    $flightIdByDate[$ds] = intval($f['flightId'] ?? 0);
                    $flightDepTimeByDate[$ds] = substr((string)($f['flightDepartureTime'] ?? ''), 0, 5);
                }
                $fst->close();
            }
        }
    } catch (Throwable $e) {
        $flightIdByDate = [];
        $flightDepTimeByDate = [];
    }

    // 4) () package_flights( )  fallback
    $pkgDepartureTime = '';
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'package_flights'");
        $has = ($tbl && $tbl->num_rows > 0);
        if ($has) {
            $pst = $conn->prepare("SELECT departure_time FROM package_flights WHERE package_id = ? AND flight_type = 'departure' LIMIT 1");
            if ($pst) {
                $pst->bind_param('i', $packageId);
                $pst->execute();
                $row = $pst->get_result()->fetch_assoc();
                $pst->close();
                $raw = trim((string)($row['departure_time'] ?? ''));
                if ($raw !== '') {
                    // TIME  DATETIME  
                    if (preg_match('/^\d{2}:\d{2}/', $raw)) $pkgDepartureTime = substr($raw, 0, 5);
                    else if (preg_match('/\b(\d{2}:\d{2})\b/', $raw, $m)) $pkgDepartureTime = $m[1];
                }
            }
        }
    } catch (Throwable $e) {
        $pkgDepartureTime = '';
    }
    
    //     
    $currentDate = clone $monthStart;
    $availabilityId = 1;
    
    while ($currentDate <= $monthEnd) {
        $dateStr = $currentDate->format('Y-m-d');
        $day = (int)$currentDate->format('d');
        
        // 판매 기간 체크 제거 - package_available_dates 기준으로 판매 가능 여부 판단
        // (sales_start_date/sales_end_date 필터 사용 안 함)
        
        //   :
        //   :   inactive  (/)
        $isPastDate = $currentDate < $today;
        
        $status = 'unavailable';
        $price = $basePrice;
        $remainingSeats = 0;
        $maxSeats = 0;
        // flightId/(  ): per-date flight   package_flights   
        $flightId = isset($flightIdByDate[$dateStr]) && (int)$flightIdByDate[$dateStr] > 0 ? (int)$flightIdByDate[$dateStr] : null;
        $flightFare = null; // date 
        $landFare = floatval($package['packagePrice'] ?? 0); //  (land)
        $departureTime = ''; // ( :   / :    )
        if (isset($flightDepTimeByDate[$dateStr]) && (string)$flightDepTimeByDate[$dateStr] !== '') {
            $departureTime = (string)$flightDepTimeByDate[$dateStr];
        } elseif ($pkgDepartureTime !== '') {
            $departureTime = $pkgDepartureTime;
        }
        $bookedSeats = intval($bookedByDate[$dateStr] ?? 0);

        // 날짜별 인원타입 가격 (package_available_dates에서 가져옴)
        $childPrice = null;
        $infantPrice = null;
        $singlePrice = null;
        // B2B 가격
        $b2bPrice = null;
        $b2bChildPrice = null;
        $b2bInfantPrice = null;

        // package_available_dates    /
        if (isset($availabilityByDate[$dateStr])) {
            if ($isPastDate) {
                $currentDate->modify('+1 day');
                continue;
            }
            // package 자체가 닫힘(maxParticipants=0)이면 per-date availableSeats가 있어도 예약 불가
            $maxSeats = $packageClosed ? 0 : intval($availabilityByDate[$dateStr]['availableSeats'] ?? 0);
            $flightFare = floatval($availabilityByDate[$dateStr]['price'] ?? 0);
            // 인원타입별 가격
            $childPrice = $availabilityByDate[$dateStr]['childPrice'];
            $infantPrice = $availabilityByDate[$dateStr]['infantPrice'];
            $singlePrice = $availabilityByDate[$dateStr]['singlePrice'];
            // B2B 가격
            $b2bPrice = $availabilityByDate[$dateStr]['b2bPrice'];
            $b2bChildPrice = $availabilityByDate[$dateStr]['b2bChildPrice'];
            $b2bInfantPrice = $availabilityByDate[$dateStr]['b2bInfantPrice'];
            // package_available_dates.flightId   ,  flight   
            $fid = intval($availabilityByDate[$dateStr]['flightId'] ?? 0);
            $flightId = $fid > 0 ? $fid : (intval($flightIdByDate[$dateStr] ?? 0) ?: null);
            $departureTime = (string)($availabilityByDate[$dateStr]['departureTime'] ?? '');
            if ($departureTime === '' && isset($flightDepTimeByDate[$dateStr])) $departureTime = (string)$flightDepTimeByDate[$dateStr];
            if ($departureTime === '' && $pkgDepartureTime !== '') $departureTime = $pkgDepartureTime;
            $remainingSeats = max($maxSeats - $bookedSeats, 0);
            if ($packageClosed || $maxSeats <= 0) {
                $status = 'closed';
            } else if ($remainingSeats > 0) {
                $status = 'available';
            } else {
                $status = 'closed';
            }
            // 가격: package_available_dates.price가 있으면 해당 가격 사용, 없으면 packages.packagePrice 사용
            $price = ($flightFare > 0) ? $flightFare : $landFare;
        } else {
            // package_available_dates에 없는 날짜는 스킵 (응답에 포함하지 않음)
            $currentDate->modify('+1 day');
            continue;
        }
        
        if ($status !== 'unavailable') {
            // Sale 할인 정보 가져오기
            $discountAmount = $availabilityByDate[$dateStr]['discountAmount'] ?? 0;
            $saleName = $availabilityByDate[$dateStr]['saleName'] ?? null;
            $isOnSale = $availabilityByDate[$dateStr]['isOnSale'] ?? false;
            $originalPrice = $availabilityByDate[$dateStr]['originalPrice'] ?? $price;
            $originalB2bPrice = $availabilityByDate[$dateStr]['originalB2bPrice'] ?? $b2bPrice;

            $availability[] = [
                'availabilityId' => $availabilityId++,
                'availableDate' => $dateStr,
                'status' => $status,
                // B2C 가격 (기본) - 할인 적용된 가격
                'price' => round($price, 0),
                'originalPrice' => round($originalPrice, 0),
                'childPrice' => $childPrice,
                'infantPrice' => $infantPrice,
                'singlePrice' => $singlePrice,
                // B2B 가격 (에이전트/관리자용) - 할인 적용된 가격
                'b2bPrice' => $b2bPrice !== null ? round($b2bPrice, 0) : null,
                'originalB2bPrice' => $originalB2bPrice !== null ? round($originalB2bPrice, 0) : null,
                'b2bChildPrice' => $b2bChildPrice,
                'b2bInfantPrice' => $b2bInfantPrice,
                'remainingSeats' => $remainingSeats,
                'maxSeats' => $maxSeats,
                'flightId' => $flightId,
                'flightPrice' => $flightFare,
                'landPrice' => $landFare,
                'bookedSeats' => $bookedSeats,
                'departureTime' => $departureTime, // HH:MM (선택)
                // Sale 할인 정보
                'discountAmount' => $discountAmount,
                'saleName' => $saleName,
                'isOnSale' => $isOnSale,
            ];
        }
        
        $currentDate->modify('+1 day');
    }

    return $availability;
}

?>

