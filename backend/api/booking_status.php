<?php
header('Content-Type: application/json; charset=utf-8');

require "../conn.php";

$packageId = isset($_GET['packageId']) ? intval($_GET['packageId']) : 0;
$departureDate = isset($_GET['departureDate']) ? $_GET['departureDate'] : null;

if ($packageId <= 0 || empty($departureDate)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid packageId or departureDate'
    ]);
    exit;
}

// YYYY-MM-DD  
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departureDate)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format'
    ]);
    exit;
}

/**
 * bookings.departureDate      
 * - DATE : AND b.departureDate = ?
 * - DATETIME : AND DATE(b.departureDate) = ?
 */
$sql = "SELECT 
            p.packageId,
            p.maxParticipants,
            p.minParticipants,
            COALESCE(SUM(COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infantsWithSeat,0)), 0) AS bookedSeats
        FROM packages p
        LEFT JOIN bookings b
            ON p.packageId = b.packageId
           AND (b.bookingStatus IS NULL OR b.bookingStatus NOT IN ('cancelled'))
           AND (b.paymentStatus IS NULL OR b.paymentStatus <> 'refunded')
           AND DATE(b.departureDate) = ?
        WHERE p.packageId = ?
          AND p.isActive = 1
        GROUP BY 
            p.packageId,
            p.maxParticipants,
            p.minParticipants";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $departureDate, $packageId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Package not found'
    ]);
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$bookedSeats     = (int)($row['bookedSeats'] ?? 0);
$packageMaxParticipants = (int)($row['maxParticipants'] ?? 0); // packages.maxParticipants (기본)
$maxParticipants = $packageMaxParticipants;
$minParticipants = (int)($row['minParticipants'] ?? 0);

//  (package_available_dates.capacity)
try {
    $tbl = $conn->query("SHOW TABLES LIKE 'package_available_dates'");
    $has = ($tbl && $tbl->num_rows > 0);
    // dev_tasks #115: packages.maxParticipants=0이면 per-date capacity가 있어도 예약 불가(0 유지)
    if ($has && $packageMaxParticipants > 0) {
        $st = $conn->prepare("SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
        if ($st) {
            $st->bind_param('is', $packageId, $departureDate);
            $st->execute();
            $rs = $st->get_result();
            $r = $rs ? $rs->fetch_assoc() : null;
            $st->close();
            if ($r && isset($r['capacity']) && $r['capacity'] !== null) {
                $maxParticipants = (int)$r['capacity'];
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

$remainingSeats  = max($maxParticipants - $bookedSeats, 0);
$isGuaranteedDeparture = ($minParticipants > 0 && $bookedSeats >= $minParticipants);

echo json_encode([
    'success'          => true,
    'bookedSeats'      => $bookedSeats,
    'maxParticipants'  => $maxParticipants,
    'remainingSeats'   => $remainingSeats,
    'minParticipants'  => $minParticipants,
    'isGuaranteedDeparture' => $isGuaranteedDeparture,
]);
