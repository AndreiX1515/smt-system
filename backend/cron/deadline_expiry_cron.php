#!/usr/bin/php
<?php
/**
 * Deadline Expiry Cron - Auto-cancel bookings with expired payment deadlines
 *
 * Replaces the overview.php applyB2BAutoCancellation() logic.
 * Runs daily at midnight to check for expired deadlines and transition
 * bookings to waiting_cancelled status.
 *
 * < 34 days bookings with +3hr deadlines are handled by cleanup_draft_bookings.php
 *
 * Crontab entry:
 * 0 0 * * * /usr/bin/php /var/www/html/backend/cron/deadline_expiry_cron.php >> /var/log/deadline_expiry_cron.log 2>&1
 */

date_default_timezone_set('Asia/Manila');

$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Deadline expiry cron started\n";

try {
    require_once __DIR__ . '/../conn.php';

    $bpLib = __DIR__ . '/../lib/booking_payments.php';
    if (file_exists($bpLib)) {
        require_once $bpLib;
    }

    $emailService = __DIR__ . '/../services/email_notification_service.php';
    if (file_exists($emailService)) {
        require_once $emailService;
    }

    $processed = 0;
    $cancelled = 0;
    $failed = 0;

    // 1. Check booking_payments table for overdue payments
    $stmt = $conn->prepare("
        SELECT bp.bookingId, bp.paymentStep, bp.dueDate, bp.status as paymentStatus,
               b.bookingStatus, b.paymentType, b.departureDate
        FROM booking_payments bp
        JOIN bookings b ON bp.bookingId = b.bookingId
        WHERE bp.dueDate < CURDATE()
          AND bp.status != 'confirmed'
          AND b.bookingStatus NOT IN ('cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft', 'rejected')
        ORDER BY bp.dueDate ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $processedBookingIds = [];

    while ($row = $result->fetch_assoc()) {
        $bookingId = $row['bookingId'];
        if (in_array($bookingId, $processedBookingIds)) continue;

        $processed++;

        // Check if payment proof has been uploaded
        $hasProof = false;
        if (function_exists('getPaymentByStep')) {
            $payment = getPaymentByStep($conn, $bookingId, $row['paymentStep']);
            if ($payment && !empty($payment['filePath'])) {
                $hasProof = true;
            }
        }

        if ($hasProof) {
            echo $logPrefix . "  SKIP {$bookingId}: payment proof exists for step {$row['paymentStep']}\n";
            continue;
        }

        echo $logPrefix . "Processing {$bookingId} (step={$row['paymentStep']}, due={$row['dueDate']})\n";

        $conn->begin_transaction();
        try {
            $checkStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$checkResult || in_array($checkResult['bookingStatus'], ['cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft', 'rejected'])) {
                $conn->rollback();
                echo $logPrefix . "  SKIP {$bookingId}: status changed to {$checkResult['bookingStatus']}\n";
                $processedBookingIds[] = $bookingId;
                continue;
            }

            $prevStatus = $checkResult['bookingStatus'];

            $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'waiting_cancelled', paymentStatus = 'failed', updatedAt = NOW() WHERE bookingId = ?");
            $updStmt->bind_param('s', $bookingId);
            $updStmt->execute();
            $updStmt->close();

            $changedAt = date('Y-m-d H:i:s');
            $histStmt = $conn->prepare("
                INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt, changeReason)
                VALUES (?, ?, 'waiting_cancelled', 'System (Deadline-Expiry-Cron)', 'system', ?, ?)
            ");
            $reason = "Payment deadline expired for step: {$row['paymentStep']} (due: {$row['dueDate']})";
            $histStmt->bind_param('ssss', $bookingId, $prevStatus, $changedAt, $reason);
            $histStmt->execute();
            $histStmt->close();

            $conn->commit();
            $cancelled++;
            $processedBookingIds[] = $bookingId;
            echo $logPrefix . "  CANCELLED {$bookingId} -> waiting_cancelled\n";

            // Send pending cancellation email
            if (function_exists('send_pending_cancellation_email')) {
                $emailReason = "Payment deadline has passed for {$row['paymentStep']} payment (due: {$row['dueDate']}). You have 24 hours to resolve this before the booking is permanently cancelled.";
                send_pending_cancellation_email($conn, $bookingId, $emailReason);
            }

        } catch (Throwable $e) {
            $conn->rollback();
            $failed++;
            echo $logPrefix . "  ERROR {$bookingId}: {$e->getMessage()}\n";
        }
    }
    $stmt->close();

    // 2. Also check legacy columns for bookings not in booking_payments table
    $legacySql = "
        SELECT b.bookingId, b.bookingStatus, b.paymentType
        FROM bookings b
        WHERE b.bookingStatus NOT IN ('cancelled', 'confirmed', 'completed', 'waiting_cancelled', 'draft', 'rejected')
        AND b.bookingId NOT IN (SELECT DISTINCT bookingId FROM booking_payments)
        AND (
            (b.downPaymentDueDate IS NOT NULL AND DATE(b.downPaymentDueDate) < CURDATE() AND COALESCE(b.downPaymentFile, '') = '')
            OR (b.advancePaymentDueDate IS NOT NULL AND DATE(b.advancePaymentDueDate) < CURDATE() AND COALESCE(b.advancePaymentFile, '') = '')
            OR (b.balanceDueDate IS NOT NULL AND DATE(b.balanceDueDate) < CURDATE() AND COALESCE(b.balanceFile, '') = '')
            OR (b.fullPaymentDueDate IS NOT NULL AND b.fullPaymentDueDate < NOW() AND COALESCE(b.fullPaymentFile, '') = '')
        )
    ";
    $legacyResult = $conn->query($legacySql);

    if ($legacyResult) {
        while ($row = $legacyResult->fetch_assoc()) {
            $bookingId = $row['bookingId'];
            if (in_array($bookingId, $processedBookingIds)) continue;

            $processed++;

            $conn->begin_transaction();
            try {
                $prevStatus = $row['bookingStatus'];

                $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'waiting_cancelled', paymentStatus = 'failed', updatedAt = NOW() WHERE bookingId = ?");
                $updStmt->bind_param('s', $bookingId);
                $updStmt->execute();
                $updStmt->close();

                $changedAt = date('Y-m-d H:i:s');
                $histStmt = $conn->prepare("
                    INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt, changeReason)
                    VALUES (?, ?, 'waiting_cancelled', 'System (Deadline-Expiry-Cron/Legacy)', 'system', ?, 'Payment deadline expired (legacy columns)')
                ");
                $histStmt->bind_param('sss', $bookingId, $prevStatus, $changedAt);
                $histStmt->execute();
                $histStmt->close();

                $conn->commit();
                $cancelled++;
                $processedBookingIds[] = $bookingId;
                echo $logPrefix . "  CANCELLED (legacy) {$bookingId} -> waiting_cancelled\n";

                if (function_exists('send_pending_cancellation_email')) {
                    $emailReason = 'Payment deadline has passed. You have 24 hours to resolve this before the booking is permanently cancelled.';
                    send_pending_cancellation_email($conn, $bookingId, $emailReason);
                }

            } catch (Throwable $e) {
                $conn->rollback();
                $failed++;
                echo $logPrefix . "  ERROR (legacy) {$bookingId}: {$e->getMessage()}\n";
            }
        }
    }

    $duration = round(microtime(true) - $startTime, 2);
    echo $logPrefix . "Completed in {$duration}s - Processed={$processed}, Cancelled={$cancelled}, Failed={$failed}\n";

    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

} catch (Exception $e) {
    echo $logPrefix . "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo $logPrefix . "Deadline expiry cron finished\n";
exit(0);
