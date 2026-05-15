<?php
/**
 * Service Voucher Generator
 * Collects booking data and generates PDF via Dompdf
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/weather_config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a Service Voucher PDF for a booking
 *
 * @param mysqli $conn
 * @param string $bookingId
 * @return array ['success' => bool, 'message' => string, 'data' => [...]]
 */
function generateServiceVoucher($conn, $bookingId) {

    // 1. Booking basic info
    $stmt = $conn->prepare("
        SELECT b.bookingId, b.packageId, b.packageName, b.departureDate,
               b.adults, b.children, b.infants, b.bookingStatus,
               b.agentId, b.totalAmount,
               p.destination, p.packageDestination, p.durationDays, p.duration_days
        FROM bookings b
        LEFT JOIN packages p ON b.packageId = p.packageId
        WHERE b.bookingId = ?
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }

    $packageId = (int)$booking['packageId'];
    $destination = $booking['destination'] ?: ($booking['packageDestination'] ?: 'Korea');

    // 2. Total travel days from package_schedules
    $stmt = $conn->prepare("SELECT MAX(day_number) as maxDay FROM package_schedules WHERE package_id = ?");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $totalDays = (int)($row['maxDay'] ?? ($booking['durationDays'] ?: ($booking['duration_days'] ?: 6)));

    // 3. Accommodations
    $stmt = $conn->prepare("SELECT * FROM package_accommodations WHERE package_id = ? ORDER BY sort_order ASC");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $accommodations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Build sort_order → name map for schedule fallback
    $accByOrder = [];
    foreach ($accommodations as $idx => $acc) {
        $accByOrder[$idx] = $acc['accommodation_name'];
    }

    // 4. Flights
    $stmt = $conn->prepare("SELECT * FROM package_flights WHERE package_id = ? ORDER BY flight_type ASC");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $flights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $departFlight = null;
    $returnFlight = null;
    foreach ($flights as $f) {
        if ($f['flight_type'] === 'departure') $departFlight = $f;
        if ($f['flight_type'] === 'return') $returnFlight = $f;
    }

    // 5. Rooming assignments + travelers
    $stmt = $conn->prepare("
        SELECT ra.room_type, ra.room_number,
               bt.firstName, bt.lastName, bt.travelerType, bt.bookingTravelerId
        FROM rooming_assignments ra
        JOIN booking_travelers bt ON ra.booking_traveler_id = bt.bookingTravelerId
        WHERE bt.transactNo = ?
        ORDER BY ra.room_type ASC, ra.id ASC
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If no rooming assignments, fallback to booking_travelers only
    if (empty($rooms)) {
        $stmt = $conn->prepare("
            SELECT firstName, lastName, travelerType, bookingTravelerId,
                   'unassigned' as room_type, NULL as room_number
            FROM booking_travelers
            WHERE transactNo = ?
            ORDER BY bookingTravelerId ASC
        ");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 6. Schedules
    $stmt = $conn->prepare("
        SELECT day_number, description, airport_location, accommodation_name, accommodation_address,
               breakfast, lunch, dinner
        FROM package_schedules
        WHERE package_id = ?
        ORDER BY day_number ASC
    ");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Match accommodation from package_accommodations if schedule has none
    // Simple strategy: spread accommodations across nights (day 1 to day totalDays-1)
    foreach ($schedules as &$sch) {
        if (empty($sch['accommodation_name'])) {
            $dayNum = (int)$sch['day_number'];
            // Last day usually no hotel
            if ($dayNum < $totalDays && !empty($accByOrder)) {
                // Map: first half of days → first hotel, second half → second hotel, etc.
                $accCount = count($accByOrder);
                $nightsTotal = $totalDays - 1;
                if ($nightsTotal > 0 && $accCount > 0) {
                    $idx = min(floor(($dayNum - 1) * $accCount / $nightsTotal), $accCount - 1);
                    $sch['matched_accommodation'] = $accByOrder[$idx] ?? '';
                }
            }
        }
    }
    unset($sch);

    // 7. Weather API
    $weather = getWeatherForecast($destination, $booking['departureDate'], $totalDays);

    // 8. Assemble data for template
    $data = [
        'booking' => $booking,
        'flights' => $flights,
        'departFlight' => $departFlight,
        'returnFlight' => $returnFlight,
        'accommodations' => $accommodations,
        'rooms' => $rooms,
        'schedules' => $schedules,
        'weather' => $weather,
        'totalDays' => $totalDays
    ];

    // 9. Render HTML from template
    ob_start();
    include __DIR__ . '/voucher_template.php';
    $html = ob_get_clean();

    // 10. Generate PDF with Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();

    // 11. Delete existing voucher document (file + DB)
    $existing = $conn->prepare("SELECT documentId, filePath FROM booking_documents WHERE bookingId = ? AND documentType = 'voucher'");
    $existing->bind_param('s', $bookingId);
    $existing->execute();
    $existingResult = $existing->get_result();
    while ($old = $existingResult->fetch_assoc()) {
        $oldPath = __DIR__ . '/../../' . ltrim($old['filePath'], '/');
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
        $delStmt = $conn->prepare("DELETE FROM booking_documents WHERE documentId = ?");
        $delStmt->bind_param('i', $old['documentId']);
        $delStmt->execute();
        $delStmt->close();
    }
    $existing->close();

    // 12. Build filename: SV-{destination}-{startMonthDay}-{endMonthDay}-{airline_code}.pdf
    $depDate = new DateTime($booking['departureDate']);
    $endDate = clone $depDate;
    $endDate->modify('+' . ($totalDays - 1) . ' days');

    $destCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $destination), 0, 3));
    $startStr = strtoupper($depDate->format('M-d'));
    $endStr = strtoupper($endDate->format('M-d'));

    // Extract airline code from flight number (e.g., "5J188" → "5J")
    $airlineCode = 'XX';
    if ($departFlight && !empty($departFlight['flight_number'])) {
        preg_match('/^([A-Z0-9]{2})/', $departFlight['flight_number'], $m);
        if (!empty($m[1])) $airlineCode = $m[1];
    }

    $originalName = "SV-{$destCode}-{$startStr}-{$endStr}-{$airlineCode}.pdf";

    // Save PDF
    $uploadDir = __DIR__ . '/../../uploads/travel_documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = $bookingId . '_voucher_' . time() . '.pdf';
    $destPath = $uploadDir . $fileName;

    if (file_put_contents($destPath, $pdfContent) === false) {
        return ['success' => false, 'message' => 'Failed to save PDF file'];
    }

    // 13. Insert into booking_documents
    $filePath = 'smt-system/uploads/travel_documents/' . $fileName;
    $fileSize = strlen($pdfContent);
    $mimeType = 'application/pdf';
    $documentType = 'voucher';
    $uploadedBy = $_SESSION['admin_accountId'] ?? $_SESSION['accountId'] ?? null;

    $stmt = $conn->prepare("INSERT INTO booking_documents (bookingId, documentType, filePath, originalName, fileSize, mimeType, uploadedBy) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssisi', $bookingId, $documentType, $filePath, $originalName, $fileSize, $mimeType, $uploadedBy);
    $stmt->execute();
    $docId = $stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Voucher generated successfully',
        'data' => [
            'documentId' => $docId,
            'filePath' => $filePath,
            'originalName' => $originalName,
            'fileSize' => $fileSize
        ]
    ];
}
