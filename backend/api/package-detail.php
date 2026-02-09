<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../conn.php';

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    return $res && $res->num_rows > 0;
}

try {
    $packageId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
    
    if ($packageId <= 0) {
        throw new Exception('   ID.');
    }

    //    
    $packageQuery = "SELECT * FROM packages WHERE packageId = ?";
    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->bind_param("i", $packageId);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    
    if ($packageResult->num_rows === 0) {
        throw new Exception('   .');
    }
    
    $package = $packageResult->fetch_assoc();

    // () 
    // -     ,     
    // -  packageId  KOR00000  (   placeholder )
    $codeCandidates = ['productCode', 'packageCode', 'product_code', 'package_code', 'code', 'sku', 'SKU'];
    $resolvedCode = '';
    foreach ($codeCandidates as $k) {
        if (isset($package[$k]) && trim((string)$package[$k]) !== '') {
            $resolvedCode = trim((string)$package[$k]);
            break;
        }
    }
    if ($resolvedCode === '') {
        $resolvedCode = 'KOR' . str_pad((string)$packageId, 5, '0', STR_PAD_LEFT);
    }
    $package['productCode'] = $resolvedCode;

    // 
    $flights = [];
    if (table_exists($conn, 'package_flights')) {
        $q = "SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination
              FROM package_flights
              WHERE package_id = ?
              ORDER BY flight_type ASC";
        $st = $conn->prepare($q);
        $st->bind_param('i', $packageId);
        $st->execute();
        $rs = $st->get_result();
        while ($row = $rs->fetch_assoc()) {
            $flights[] = $row;
        }
        $st->close();
    }
    
    //  
    $schedules = [];
    if (table_exists($conn, 'package_schedules')) {
        // schema: package_id, schedule_id, day_number
        $schedulesQuery = "SELECT * FROM package_schedules WHERE package_id = ? ORDER BY day_number ASC, schedule_id ASC";
        $schedulesStmt = $conn->prepare($schedulesQuery);
        $schedulesStmt->bind_param("i", $packageId);
        $schedulesStmt->execute();
        $schedulesResult = $schedulesStmt->get_result();

        while ($schedule = $schedulesResult->fetch_assoc()) {
            //  (package_attractions)
            $schedule['attractions'] = [];
            if (table_exists($conn, 'package_attractions')) {
                $sid = (int)($schedule['schedule_id'] ?? 0);
                if ($sid > 0) {
                    $aq = "SELECT attraction_id, attraction_name, attraction_address, attraction_description, attraction_image, visit_order, start_time, end_time
                           FROM package_attractions
                           WHERE schedule_id = ?
                           ORDER BY visit_order ASC, attraction_id ASC";
                    $ast = $conn->prepare($aq);
                    $ast->bind_param('i', $sid);
                    $ast->execute();
                    $ars = $ast->get_result();
                    while ($a = $ars->fetch_assoc()) {
                        $schedule['attractions'][] = $a;
                    }
                    $ast->close();
                }
            }
            $schedules[] = $schedule;
        }
        $schedulesStmt->close();
    }

    //  (package_pricing_options)
    $pricingOptions = [];
    if (table_exists($conn, 'package_pricing_options')) {
        $pq = "SELECT pricing_id, option_name, price
               FROM package_pricing_options
               WHERE package_id = ?
               ORDER BY pricing_id ASC";
        $pst = $conn->prepare($pq);
        $pst->bind_param('i', $packageId);
        $pst->execute();
        $prs = $pst->get_result();
        while ($row = $prs->fetch_assoc()) $pricingOptions[] = $row;
        $pst->close();
    }

    //   (package_available_dates)
    $availability = [];
    if (table_exists($conn, 'package_available_dates')) {
        $aq = "SELECT pad.id AS availabilityId, pad.available_date AS availableDate, pad.price, pad.b2b_price AS b2bPrice,
                      pad.childPrice, pad.b2b_child_price AS b2bChildPrice, pad.infant_price AS infantPrice, pad.b2b_infant_price AS b2bInfantPrice,
                      pad.infant_seat_price AS infantSeatPrice, pad.b2b_infant_seat_price AS b2bInfantSeatPrice,
                      pad.capacity AS availableSeats,
                      COALESCE(bk.booked, 0) AS bookedSeats,
                      pad.status, pad.flight_id AS flightId
               FROM package_available_dates pad
               LEFT JOIN (
                   SELECT packageId, departureDate,
                          SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                   FROM bookings
                   WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','rejected'))
                     AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                   GROUP BY packageId, departureDate
               ) bk ON bk.packageId = pad.package_id AND bk.departureDate = pad.available_date
               WHERE pad.package_id = ?
               ORDER BY pad.available_date ASC";
        $ast = $conn->prepare($aq);
        $ast->bind_param('i', $packageId);
        $ast->execute();
        $ars = $ast->get_result();
        while ($row = $ars->fetch_assoc()) $availability[] = $row;
        $ast->close();
    }

    // //(package_usage_guide)
    $guides = [];
    if (table_exists($conn, 'package_usage_guide')) {
        $gq = "SELECT guide_id, guide_type, guide_file, guide_description
               FROM package_usage_guide
               WHERE package_id = ?
               ORDER BY guide_id ASC";
        $gst = $conn->prepare($gq);
        $gst->bind_param('i', $packageId);
        $gst->execute();
        $grs = $gst->get_result();
        while ($row = $grs->fetch_assoc()) $guides[] = $row;
        $gst->close();
    }

    // NOTE: Upload Flyer/Detail/Itinerary   .

    // 공통 숙박 정보 (package_accommodations) - 다중 숙소
    $accommodations = [];
    if (table_exists($conn, 'package_accommodations')) {
        $acq = "SELECT id, sort_order, accommodation_name, accommodation_address, accommodation_description, accommodation_image
               FROM package_accommodations
               WHERE package_id = ?
               ORDER BY sort_order ASC, id ASC";
        $acst = $conn->prepare($acq);
        $acst->bind_param('i', $packageId);
        $acst->execute();
        $acrs = $acst->get_result();
        while ($row = $acrs->fetch_assoc()) $accommodations[] = $row;
        $acst->close();
    }

    $response = [
        'success' => true,
        'data' => [
            'package' => $package,
            'flights' => $flights,
            'schedules' => $schedules,
            'pricingOptions' => $pricingOptions,
            'availability' => $availability,
            'guides' => $guides,
            'accommodations' => $accommodations
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