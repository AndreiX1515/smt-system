<?php
/**
 * Reservation Status API
 * Returns pending and pending_update reservation counts and lists
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Database connection
require_once __DIR__ . '/../../conn.php';

try {
    // Get pending reservations
    $pendingQuery = "
        SELECT
            bookingId,
            packageName,
            departureDate,
            totalAmount,
            createdAt
        FROM bookings
        WHERE bookingStatus = 'pending'
        ORDER BY createdAt DESC
        LIMIT 100
    ";

    $pendingResult = $conn->query($pendingQuery);
    $pendingList = [];
    $pendingCount = 0;

    if ($pendingResult) {
        while ($row = $pendingResult->fetch_assoc()) {
            $pendingList[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'],
                'departureDate' => $row['departureDate'],
                'totalAmount' => floatval($row['totalAmount']),
                'createdAt' => $row['createdAt']
            ];
        }
        $pendingCount = count($pendingList);
    }

    // Get pending_update reservations
    $pendingUpdateQuery = "
        SELECT
            bookingId,
            packageName,
            departureDate,
            totalAmount,
            updatedAt
        FROM bookings
        WHERE bookingStatus = 'pending_update'
        ORDER BY updatedAt DESC
        LIMIT 100
    ";

    $pendingUpdateResult = $conn->query($pendingUpdateQuery);
    $pendingUpdateList = [];
    $pendingUpdateCount = 0;

    if ($pendingUpdateResult) {
        while ($row = $pendingUpdateResult->fetch_assoc()) {
            $pendingUpdateList[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'],
                'departureDate' => $row['departureDate'],
                'totalAmount' => floatval($row['totalAmount']),
                'updatedAt' => $row['updatedAt']
            ];
        }
        $pendingUpdateCount = count($pendingUpdateList);
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'pending' => [
                'count' => $pendingCount,
                'list' => $pendingList
            ],
            'pendingUpdate' => [
                'count' => $pendingUpdateCount,
                'list' => $pendingUpdateList
            ]
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
