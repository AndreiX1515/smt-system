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

// ========== DATETIME 데드라인 초과 full payment 예약 자동 처리 ==========
// < 34일: +3시간 deadline → 즉시 cancelled (waiting_cancelled 없음)
// 34~39일: +24시간 deadline → waiting_cancelled 전환 (24시간 유예 후 최종 취소)
echo $logPrefix . "Checking for expired DATETIME full payment bookings...\n";
try {
    // fullPaymentDueDate가 DATETIME이고, 현재시각을 초과했고, 결제증빙 미업로드인 예약
    $stmtDT = $conn->prepare("
        SELECT bookingId, fullPaymentDueDate, departureDate FROM bookings
        WHERE paymentType = 'full'
          AND fullPaymentDueDate IS NOT NULL
          AND fullPaymentDueDate <= NOW()
          AND LENGTH(fullPaymentDueDate) > 10
          AND bookingStatus NOT IN ('cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft')
          AND COALESCE(fullPaymentFile, '') = ''
    ");
    $stmtDT->execute();
    $resultDT = $stmtDT->get_result();

    $expiredDTBookings = [];
    while ($rowDT = $resultDT->fetch_assoc()) {
        $expiredDTBookings[] = $rowDT;
    }
    $stmtDT->close();

    if (empty($expiredDTBookings)) {
        echo $logPrefix . "No expired DATETIME full payment bookings found.\n";
    } else {
        echo $logPrefix . "Found " . count($expiredDTBookings) . " expired DATETIME full payment booking(s).\n";

        // Email notification service
        $email_service_file = __DIR__ . '/../services/email_notification_service.php';
        if (file_exists($email_service_file)) {
            require_once $email_service_file;
        }

        $cancelledCount = 0;
        $waitingCancelledCount = 0;
        foreach ($expiredDTBookings as $dtRow) {
            $bookingIdDT = $dtRow['bookingId'];

            // 출발일까지 남은 일수로 3시간 vs 24시간 구분
            $daysUntilDep = null;
            if (!empty($dtRow['departureDate'])) {
                $nowDT = new DateTime();
                $nowDT->setTime(0, 0, 0);
                $depDT = new DateTime($dtRow['departureDate']);
                $depDT->setTime(0, 0, 0);
                $daysUntilDep = (int)$nowDT->diff($depDT)->format('%r%a');
            }

            // < 34일: 즉시 cancelled (3시간 deadline)
            $isImmediateCancel = ($daysUntilDep !== null && $daysUntilDep < 34);

            $conn->begin_transaction();
            try {
                // Lock and verify
                $chk = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
                $chk->bind_param('s', $bookingIdDT);
                $chk->execute();
                $chkRow = $chk->get_result()->fetch_assoc();
                $chk->close();

                if (!$chkRow || in_array($chkRow['bookingStatus'], ['cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft'])) {
                    $conn->rollback();
                    continue;
                }

                $prevStatus = $chkRow['bookingStatus'];
                $changedAtDT = date('Y-m-d H:i:s');

                if ($isImmediateCancel) {
                    // 즉시 cancelled (waiting_cancelled 거치지 않음)
                    $upd = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', paymentStatus = 'failed', updatedAt = NOW() WHERE bookingId = ?");
                    $upd->bind_param('s', $bookingIdDT);
                    $upd->execute();
                    $upd->close();

                    $hist = $conn->prepare("
                        INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt, changeReason)
                        VALUES (?, ?, 'cancelled', 'System (3hr-Deadline)', 'system', ?, 'Full payment not completed within 3-hour deadline')
                    ");
                    $hist->bind_param('sss', $bookingIdDT, $prevStatus, $changedAtDT);
                    $hist->execute();
                    $hist->close();

                    $conn->commit();
                    $cancelledCount++;
                    echo $logPrefix . "  Cancelled 3hr-expired booking: {$bookingIdDT}\n";

                    if (function_exists('send_rejection_notification_email')) {
                        $reason3h = 'Full payment was not completed within the 3-hour deadline. The booking has been automatically cancelled.';
                        send_rejection_notification_email($conn, $bookingIdDT, 'auto_cancellation', $reason3h);
                    }
                } else {
                    // 34~39일: waiting_cancelled 전환 (24시간 유예)
                    $upd = $conn->prepare("UPDATE bookings SET bookingStatus = 'waiting_cancelled', paymentStatus = 'failed', updatedAt = NOW() WHERE bookingId = ?");
                    $upd->bind_param('s', $bookingIdDT);
                    $upd->execute();
                    $upd->close();

                    $hist = $conn->prepare("
                        INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt, changeReason)
                        VALUES (?, ?, 'waiting_cancelled', 'System (24hr-Deadline)', 'system', ?, 'Full payment not completed within 24-hour deadline')
                    ");
                    $hist->bind_param('sss', $bookingIdDT, $prevStatus, $changedAtDT);
                    $hist->execute();
                    $hist->close();

                    $conn->commit();
                    $waitingCancelledCount++;
                    echo $logPrefix . "  Moved to waiting_cancelled (24hr grace): {$bookingIdDT}\n";

                    if (function_exists('send_pending_cancellation_email')) {
                        $reason24h = 'Full payment was not completed within the 24-hour deadline. You have 24 hours to resolve this before permanent cancellation.';
                        send_pending_cancellation_email($conn, $bookingIdDT, $reason24h);
                    }
                }
            } catch (Exception $eDT) {
                $conn->rollback();
                echo $logPrefix . "  ERROR processing {$bookingIdDT}: " . $eDT->getMessage() . "\n";
            }
        }
        echo $logPrefix . "Processed: Cancelled={$cancelledCount}, WaitingCancelled={$waitingCancelledCount}\n";
    }
} catch (Exception $e) {
    echo $logPrefix . "ERROR in DATETIME deadline check: " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $startTime, 2);
echo $logPrefix . "Draft booking cleanup completed in {$elapsed}s\n\n";
