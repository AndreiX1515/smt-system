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
 * [star]/30 * * * * /usr/bin/php /var/www/html/backend/cron/cleanup_draft_bookings.php >> /var/log/cleanup_draft_bookings.log 2>&1
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

// ========== 3시간 데드라인 초과 full payment 예약 자동 취소 ==========
echo $logPrefix . "Checking for expired 3-hour full payment bookings...\n";
try {
    // fullPaymentDueDate가 DATETIME이고, 현재시각을 초과했고, 결제증빙 미업로드인 예약
    $stmt3h = $conn->prepare("
        SELECT bookingId FROM bookings
        WHERE paymentType = 'full'
          AND fullPaymentDueDate IS NOT NULL
          AND fullPaymentDueDate <= NOW()
          AND bookingStatus NOT IN ('cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft')
          AND COALESCE(fullPaymentFile, '') = ''
    ");
    $stmt3h->execute();
    $result3h = $stmt3h->get_result();

    $expired3hBookings = [];
    while ($row3h = $result3h->fetch_assoc()) {
        $expired3hBookings[] = $row3h['bookingId'];
    }
    $stmt3h->close();

    if (empty($expired3hBookings)) {
        echo $logPrefix . "No expired 3-hour full payment bookings found.\n";
    } else {
        echo $logPrefix . "Found " . count($expired3hBookings) . " expired 3-hour full payment booking(s).\n";

        // Email notification service
        $email_service_file = __DIR__ . '/../services/email_notification_service.php';
        if (file_exists($email_service_file)) {
            require_once $email_service_file;
        }

        $cancelledCount = 0;
        foreach ($expired3hBookings as $bookingId3h) {
            $conn->begin_transaction();
            try {
                // Lock and verify
                $chk = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
                $chk->bind_param('s', $bookingId3h);
                $chk->execute();
                $chkRow = $chk->get_result()->fetch_assoc();
                $chk->close();

                if (!$chkRow || in_array($chkRow['bookingStatus'], ['cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft'])) {
                    $conn->rollback();
                    continue;
                }

                // 즉시 cancelled (waiting_cancelled 거치지 않음)
                $upd = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', paymentStatus = 'failed', updatedAt = NOW() WHERE bookingId = ?");
                $upd->bind_param('s', $bookingId3h);
                $upd->execute();
                $upd->close();

                // booking_status_history 기록
                $changedAt3h = date('Y-m-d H:i:s');
                $hist = $conn->prepare("
                    INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
                    VALUES (?, ?, 'cancelled', 'System (3hr-Deadline)', 'system', ?)
                ");
                $prevStatus = $chkRow['bookingStatus'];
                $hist->bind_param('sss', $bookingId3h, $prevStatus, $changedAt3h);
                $hist->execute();
                $hist->close();

                $conn->commit();
                $cancelledCount++;
                echo $logPrefix . "  Cancelled 3hr-expired booking: {$bookingId3h}\n";

                // Send cancellation email
                if (function_exists('send_rejection_notification_email')) {
                    $reason3h = 'Full payment was not completed within the 3-hour deadline. The booking has been automatically cancelled.';
                    send_rejection_notification_email($conn, $bookingId3h, 'auto_cancellation', $reason3h);
                }
            } catch (Exception $e3h) {
                $conn->rollback();
                echo $logPrefix . "  ERROR cancelling {$bookingId3h}: " . $e3h->getMessage() . "\n";
            }
        }
        echo $logPrefix . "Cancelled {$cancelledCount} expired 3-hour full payment booking(s).\n";
    }
} catch (Exception $e) {
    echo $logPrefix . "ERROR in 3-hour check: " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $startTime, 2);
echo $logPrefix . "Draft booking cleanup completed in {$elapsed}s\n\n";
