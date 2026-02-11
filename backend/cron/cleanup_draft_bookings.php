#!/usr/bin/php
<?php
/**
 * Cleanup Draft Bookings Cron Script
 *
 * Deletes draft bookings older than 2 hours.
 * Draft bookings are created when an agent clicks "Next: Payment" but
 * has not yet completed the reservation via "Complete Reservation".
 *
 * Crontab entry (run every 30 minutes):
 * */30 * * * * /usr/bin/php /var/www/html/backend/cron/cleanup_draft_bookings.php >> /var/log/cleanup_draft_bookings.log 2>&1
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log start time
$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Draft booking cleanup cron job started\n";

try {
    // Load database connection
    require_once __DIR__ . '/../conn.php';

    // Find draft bookings older than 2 hours
    $stmt = $conn->prepare("
        SELECT bookingId FROM bookings
        WHERE bookingStatus = 'draft'
          AND createdAt < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $draftBookings = [];
    while ($row = $result->fetch_assoc()) {
        $draftBookings[] = $row['bookingId'];
    }
    $stmt->close();

    if (empty($draftBookings)) {
        echo $logPrefix . "No expired draft bookings found.\n";
    } else {
        echo $logPrefix . "Found " . count($draftBookings) . " expired draft booking(s) to delete.\n";

        $deletedCount = 0;
        foreach ($draftBookings as $bookingId) {
            $conn->begin_transaction();
            try {
                // Delete booking_travelers first (foreign key)
                $delTravelers = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
                $delTravelers->bind_param('s', $bookingId);
                $delTravelers->execute();
                $travelersDeleted = $delTravelers->affected_rows;
                $delTravelers->close();

                // Delete booking
                $delBooking = $conn->prepare("DELETE FROM bookings WHERE bookingId = ? AND bookingStatus = 'draft'");
                $delBooking->bind_param('s', $bookingId);
                $delBooking->execute();
                $bookingDeleted = $delBooking->affected_rows;
                $delBooking->close();

                $conn->commit();

                if ($bookingDeleted > 0) {
                    $deletedCount++;
                    echo $logPrefix . "Deleted draft booking: {$bookingId} (travelers: {$travelersDeleted})\n";
                }
            } catch (Exception $e) {
                $conn->rollback();
                echo $logPrefix . "ERROR deleting booking {$bookingId}: " . $e->getMessage() . "\n";
            }
        }

        echo $logPrefix . "Deleted {$deletedCount} draft booking(s).\n";
    }

} catch (Exception $e) {
    echo $logPrefix . "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$elapsed = round(microtime(true) - $startTime, 2);
echo $logPrefix . "Draft booking cleanup completed in {$elapsed}s\n\n";
