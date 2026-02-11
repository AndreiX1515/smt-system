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

// GET request handler - Get availability for packages
function handleGetRequest() {
    global $conn;
    
    try {
        $package_id = $_GET['package_id'] ?? '';
        $flight_id = $_GET['flight_id'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $month = $_GET['month'] ?? '';
        
        if (!empty($package_id)) {
            getPackageAvailability($package_id, $date_from, $date_to);
        } elseif (!empty($flight_id)) {
            getFlightAvailability($flight_id);
        } elseif (!empty($month)) {
            getMonthlyAvailability($month);
        } else {
            getAllAvailability($date_from, $date_to);
        }
        
    } catch (Exception $e) {
        log_activity("Availability API error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get availability for a specific package
function getPackageAvailability($package_id, $date_from = '', $date_to = '') {
    global $conn;
    
    try {
        $package_id = intval($package_id);
        
        // Get package info first
        $package_query = "SELECT packageName, packagePrice FROM packages WHERE packageId = ? AND isActive = 1";
        $stmt = $conn->prepare($package_query);
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $package_result = $stmt->get_result();
        
        if ($package_result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => 'Package not found'], 404);
            return;
        }
        
        $package = $package_result->fetch_assoc();
        
        // Build flight query with date filters
        $flight_query = "SELECT f.*, 
                               (f.availSeats - COALESCE(b.booked_seats, 0)) as available_seats,
                               COALESCE(b.booked_seats, 0) as booked_seats
                        FROM flight f 
                        LEFT JOIN (
                            SELECT
                                packageId,
                                departureDate,
                                SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) as booked_seats
                            FROM bookings
                            WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled'))
                              AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                            GROUP BY packageId, departureDate
                        ) b ON f.packageId = b.packageId AND DATE(f.flightDepartureDate) = b.departureDate
                        WHERE f.packageId = ? AND f.is_active = 1";
        
        $params = [$package_id];
        $types = "i";
        
        if (!empty($date_from)) {
            $flight_query .= " AND f.flightDepartureDate >= ?";
            $params[] = $date_from;
            $types .= "s";
        }
        
        if (!empty($date_to)) {
            $flight_query .= " AND f.flightDepartureDate <= ?";
            $params[] = $date_to;
            $types .= "s";
        }
        
        $flight_query .= " ORDER BY f.flightDepartureDate ASC";
        
        $stmt = $conn->prepare($flight_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $availability = [];
        while ($row = $result->fetch_assoc()) {
            $price_multiplier = calculatePriceMultiplier($row['flightDepartureDate']);
            $current_price = $package['packagePrice'] * $price_multiplier;
            
            $availability[] = [
                'flightId' => intval($row['flightId']),
                'departureDate' => $row['flightDepartureDate'],
                'departureTime' => $row['flightDepartureTime'],
                'returnDate' => $row['returnDepartureDate'],
                'returnTime' => $row['returnDepartureTime'],
                'flightName' => $row['flightName'],
                'flightCode' => $row['flightCode'],
                'returnFlightName' => $row['returnFlightName'],
                'returnFlightCode' => $row['returnFlightCode'],
                'totalSeats' => intval($row['availSeats']),
                'availableSeats' => intval($row['available_seats']),
                'bookedSeats' => intval($row['booked_seats']),
                'basePrice' => floatval($package['packagePrice']),
                'currentPrice' => round($current_price, 2),
                'priceMultiplier' => $price_multiplier,
                'status' => getAvailabilityStatus(intval($row['available_seats'])),
                'isAvailable' => intval($row['available_seats']) > 0
            ];
        }
        
        // If no flights found, generate sample data
        if (empty($availability)) {
            $availability = generateSampleAvailability($package_id, $package['packagePrice']);
        }
        
        $response = [
            'success' => true,
            'message' => 'Package availability retrieved successfully',
            'data' => [
                'packageId' => $package_id,
                'packageName' => $package['packageName'],
                'basePrice' => floatval($package['packagePrice']),
                'availability' => $availability,
                'totalFlights' => count($availability)
            ]
        ];
        
        send_json_response($response);
        
    } catch (Exception $e) {
        log_activity("Get package availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get availability for a specific flight
function getFlightAvailability($flight_id) {
    global $conn;
    
    try {
        $flight_id = intval($flight_id);
        
        $query = "SELECT f.*, p.packageName, p.packagePrice,
                         (f.availSeats - COALESCE(b.booked_seats, 0)) as available_seats,
                         COALESCE(b.booked_seats, 0) as booked_seats
                  FROM flight f
                  JOIN packages p ON f.packageId = p.packageId
                  LEFT JOIN (
                      SELECT
                          packageId,
                          departureDate,
                          SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) as booked_seats
                      FROM bookings
                      WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled'))
                        AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                      GROUP BY packageId, departureDate
                  ) b ON f.packageId = b.packageId AND DATE(f.flightDepartureDate) = b.departureDate
                  WHERE f.flightId = ? AND f.is_active = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $flight_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => 'Flight not found'], 404);
            return;
        }
        
        $row = $result->fetch_assoc();
        $price_multiplier = calculatePriceMultiplier($row['flightDepartureDate']);
        $current_price = $row['packagePrice'] * $price_multiplier;
        
        $availability = [
            'flightId' => intval($row['flightId']),
            'packageId' => intval($row['packageId']),
            'packageName' => $row['packageName'],
            'departureDate' => $row['flightDepartureDate'],
            'departureTime' => $row['flightDepartureTime'],
            'returnDate' => $row['returnDepartureDate'],
            'returnTime' => $row['returnDepartureTime'],
            'flightName' => $row['flightName'],
            'flightCode' => $row['flightCode'],
            'returnFlightName' => $row['returnFlightName'],
            'returnFlightCode' => $row['returnFlightCode'],
            'origin' => $row['origin'],
            'totalSeats' => intval($row['availSeats']),
            'availableSeats' => intval($row['available_seats']),
            'bookedSeats' => intval($row['booked_seats']),
            'basePrice' => floatval($row['packagePrice']),
            'currentPrice' => round($current_price, 2),
            'priceMultiplier' => $price_multiplier,
            'status' => getAvailabilityStatus(intval($row['available_seats'])),
            'isAvailable' => intval($row['available_seats']) > 0,
            'wholesalePrice' => floatval($row['wholesalePrice']),
            'landPrice' => floatval($row['landPrice'])
        ];
        
        send_json_response(['success' => true, 'data' => $availability]);
        
    } catch (Exception $e) {
        log_activity("Get flight availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get monthly availability overview
function getMonthlyAvailability($month) {
    global $conn;
    
    try {
        // Validate month format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            send_json_response(['success' => false, 'message' => 'Invalid month format. Use YYYY-MM'], 400);
            return;
        }
        
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date)); // Last day of month
        
        $query = "SELECT f.flightDepartureDate as date,
                         COUNT(f.flightId) as total_flights,
                         SUM(f.availSeats) as total_seats,
                         SUM(f.availSeats - COALESCE(b.booked_seats, 0)) as available_seats,
                         SUM(COALESCE(b.booked_seats, 0)) as booked_seats,
                         AVG(p.packagePrice) as avg_price
                  FROM flight f
                  JOIN packages p ON f.packageId = p.packageId
                  LEFT JOIN (
                      SELECT
                          packageId,
                          departureDate,
                          SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) as booked_seats
                      FROM bookings
                      WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled'))
                        AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                      GROUP BY packageId, departureDate
                  ) b ON f.packageId = b.packageId AND DATE(f.flightDepartureDate) = b.departureDate
                  WHERE f.flightDepartureDate BETWEEN ? AND ? AND f.is_active = 1 AND p.isActive = 1
                  GROUP BY f.flightDepartureDate
                  ORDER BY f.flightDepartureDate";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $monthly_data = [];
        while ($row = $result->fetch_assoc()) {
            $monthly_data[] = [
                'date' => $row['date'],
                'totalFlights' => intval($row['total_flights']),
                'totalSeats' => intval($row['total_seats']),
                'availableSeats' => intval($row['available_seats']),
                'bookedSeats' => intval($row['booked_seats']),
                'occupancyRate' => $row['total_seats'] > 0 ? round(($row['booked_seats'] / $row['total_seats']) * 100, 1) : 0,
                'averagePrice' => round(floatval($row['avg_price']), 2),
                'status' => getAvailabilityStatus(intval($row['available_seats']))
            ];
        }
        
        send_json_response([
            'success' => true,
            'message' => 'Monthly availability retrieved successfully',
            'data' => [
                'month' => $month,
                'period' => ['from' => $start_date, 'to' => $end_date],
                'availability' => $monthly_data,
                'summary' => calculateMonthlySummary($monthly_data)
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity("Get monthly availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// Get all availability (admin overview)
function getAllAvailability($date_from = '', $date_to = '') {
    global $conn;
    
    try {
        $query = "SELECT f.*, p.packageName, p.packageCategory,
                         (f.availSeats - COALESCE(b.booked_seats, 0)) as available_seats,
                         COALESCE(b.booked_seats, 0) as booked_seats
                  FROM flight f
                  JOIN packages p ON f.packageId = p.packageId
                  LEFT JOIN (
                      SELECT
                          packageId,
                          departureDate,
                          SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) as booked_seats
                      FROM bookings
                      WHERE (bookingStatus IS NULL OR bookingStatus IN ('pending','confirmed'))
                        AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                      GROUP BY packageId, departureDate
                  ) b ON f.packageId = b.packageId AND DATE(f.flightDepartureDate) = b.departureDate
                  WHERE f.is_active = 1 AND p.isActive = 1";
        
        $params = [];
        $types = "";
        
        if (!empty($date_from)) {
            $query .= " AND f.flightDepartureDate >= ?";
            $params[] = $date_from;
            $types .= "s";
        }
        
        if (!empty($date_to)) {
            $query .= " AND f.flightDepartureDate <= ?";
            $params[] = $date_to;
            $types .= "s";
        }
        
        $query .= " ORDER BY f.flightDepartureDate ASC";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        $availability = [];
        while ($row = $result->fetch_assoc()) {
            $availability[] = [
                'flightId' => intval($row['flightId']),
                'packageId' => intval($row['packageId']),
                'packageName' => $row['packageName'],
                'packageCategory' => $row['packageCategory'],
                'departureDate' => $row['flightDepartureDate'],
                'departureTime' => $row['flightDepartureTime'],
                'flightCode' => $row['flightCode'],
                'totalSeats' => intval($row['availSeats']),
                'availableSeats' => intval($row['available_seats']),
                'bookedSeats' => intval($row['booked_seats']),
                'occupancyRate' => $row['availSeats'] > 0 ? round(($row['booked_seats'] / $row['availSeats']) * 100, 1) : 0,
                'status' => getAvailabilityStatus(intval($row['available_seats']))
            ];
        }
        
        send_json_response([
            'success' => true,
            'message' => 'All availability retrieved successfully',
            'data' => [
                'availability' => $availability,
                'summary' => calculateOverallSummary($availability),
                'filters' => ['date_from' => $date_from, 'date_to' => $date_to]
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity("Get all availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// POST request handler - Update availability (admin only)
function handlePostRequest() {
    global $conn;
    
    if (!isAuthenticated() || !isAdmin()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['flight_id'])) {
            send_json_response(['success' => false, 'message' => 'Flight ID is required'], 400);
            return;
        }
        
        $flight_id = intval($input['flight_id']);
        $new_seats = intval($input['available_seats'] ?? 0);
        
        if ($new_seats < 0) {
            send_json_response(['success' => false, 'message' => 'Available seats cannot be negative'], 400);
            return;
        }
        
        $stmt = $conn->prepare("UPDATE flight SET availSeats = ? WHERE flightId = ?");
        $stmt->bind_param("ii", $new_seats, $flight_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity("Flight availability updated: Flight ID $flight_id, New seats: $new_seats");
            send_json_response(['success' => true, 'message' => 'Availability updated successfully']);
        } else {
            send_json_response(['success' => false, 'message' => 'Flight not found or no changes made'], 404);
        }
        
    } catch (Exception $e) {
        log_activity("Update availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// PUT request handler - Bulk update availability (admin only)
function handlePutRequest() {
    global $conn;
    
    if (!isAuthenticated() || !isAdmin()) {
        send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        return;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['updates']) || !is_array($input['updates'])) {
            send_json_response(['success' => false, 'message' => 'Updates array is required'], 400);
            return;
        }
        
        $conn->begin_transaction();
        $updated_count = 0;
        
        foreach ($input['updates'] as $update) {
            if (isset($update['flight_id']) && isset($update['available_seats'])) {
                $flight_id = intval($update['flight_id']);
                $new_seats = intval($update['available_seats']);
                
                if ($new_seats >= 0) {
                    $stmt = $conn->prepare("UPDATE flight SET availSeats = ? WHERE flightId = ?");
                    $stmt->bind_param("ii", $new_seats, $flight_id);
                    $stmt->execute();
                    
                    if ($stmt->affected_rows > 0) {
                        $updated_count++;
                    }
                }
            }
        }
        
        $conn->commit();
        
        log_activity("Bulk availability update: $updated_count flights updated");
        send_json_response([
            'success' => true, 
            'message' => "Successfully updated $updated_count flights",
            'updated_count' => $updated_count
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity("Bulk update availability error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error occurred'], 500);
    }
}

// DELETE request handler - Not applicable for availability
function handleDeleteRequest() {
    send_json_response(['success' => false, 'message' => 'Delete operation not supported for availability'], 405);
}

// Helper functions

// Calculate price multiplier based on date (seasonal pricing)
function calculatePriceMultiplier($date) {
    $month = intval(date('m', strtotime($date)));
    $day = intval(date('d', strtotime($date)));
    
    // High season (Spring: March-May, Fall: September-November)
    if (($month >= 3 && $month <= 5) || ($month >= 9 && $month <= 11)) {
        return 1.2; // 20% increase
    }
    
    // Peak season (Cherry blossom: April, Golden week: early May)
    if (($month == 4) || ($month == 5 && $day <= 7)) {
        return 1.4; // 40% increase
    }
    
    // Low season (Winter: December-February, Summer: June-August)
    if (($month >= 12 || $month <= 2) || ($month >= 6 && $month <= 8)) {
        return 0.9; // 10% decrease
    }
    
    return 1.0; // Standard price
}

// Get availability status based on available seats
function getAvailabilityStatus($available_seats) {
    if ($available_seats <= 0) {
        return 'sold_out';
    } elseif ($available_seats <= 5) {
        return 'limited';
    } elseif ($available_seats <= 10) {
        return 'filling_fast';
    } else {
        return 'available';
    }
}

// Generate sample availability data
function generateSampleAvailability($package_id, $base_price) {
    $availability = [];
    $start_date = date('Y-m-d', strtotime('+7 days'));
    
    for ($i = 0; $i < 30; $i += 7) { // Weekly departures for next 30 days
        $departure_date = date('Y-m-d', strtotime($start_date . " +$i days"));
        $return_date = date('Y-m-d', strtotime($departure_date . ' +5 days'));
        
        $available_seats = rand(5, 30);
        $price_multiplier = calculatePriceMultiplier($departure_date);
        $current_price = $base_price * $price_multiplier;
        
        $availability[] = [
            'flightId' => 1000 + $i,
            'departureDate' => $departure_date,
            'departureTime' => '09:00:00',
            'returnDate' => $return_date,
            'returnTime' => '18:00:00',
            'flightName' => 'KE' . (100 + $i),
            'flightCode' => 'KE' . (100 + $i),
            'returnFlightName' => 'KE' . (200 + $i),
            'returnFlightCode' => 'KE' . (200 + $i),
            'totalSeats' => 30,
            'availableSeats' => $available_seats,
            'bookedSeats' => 30 - $available_seats,
            'basePrice' => floatval($base_price),
            'currentPrice' => round($current_price, 2),
            'priceMultiplier' => $price_multiplier,
            'status' => getAvailabilityStatus($available_seats),
            'isAvailable' => $available_seats > 0
        ];
    }
    
    return $availability;
}

// Calculate monthly summary
function calculateMonthlySummary($monthly_data) {
    if (empty($monthly_data)) {
        return [
            'totalFlights' => 0,
            'totalSeats' => 0,
            'totalBooked' => 0,
            'averageOccupancyRate' => 0,
            'averagePrice' => 0
        ];
    }
    
    $total_flights = array_sum(array_column($monthly_data, 'totalFlights'));
    $total_seats = array_sum(array_column($monthly_data, 'totalSeats'));
    $total_booked = array_sum(array_column($monthly_data, 'bookedSeats'));
    $prices = array_column($monthly_data, 'averagePrice');
    
    return [
        'totalFlights' => $total_flights,
        'totalSeats' => $total_seats,
        'totalBooked' => $total_booked,
        'averageOccupancyRate' => $total_seats > 0 ? round(($total_booked / $total_seats) * 100, 1) : 0,
        'averagePrice' => count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0
    ];
}

// Calculate overall summary
function calculateOverallSummary($availability) {
    if (empty($availability)) {
        return [
            'totalFlights' => 0,
            'totalSeats' => 0,
            'totalBooked' => 0,
            'averageOccupancyRate' => 0
        ];
    }
    
    $total_flights = count($availability);
    $total_seats = array_sum(array_column($availability, 'totalSeats'));
    $total_booked = array_sum(array_column($availability, 'bookedSeats'));
    
    return [
        'totalFlights' => $total_flights,
        'totalSeats' => $total_seats,
        'totalBooked' => $total_booked,
        'averageOccupancyRate' => $total_seats > 0 ? round(($total_booked / $total_seats) * 100, 1) : 0
    ];
}

// Authentication helper functions
function isAuthenticated() {
    return isset($_SESSION['accountId']) && !empty($_SESSION['accountId']);
}

function isAdmin() {
    return isset($_SESSION['accountType']) && in_array($_SESSION['accountType'], ['admin_ph', 'admin_kr'], true);
}

?>