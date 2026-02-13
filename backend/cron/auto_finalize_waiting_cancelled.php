#!/usr/bin/php
<?php
/**
 * Auto Finalize waiting_cancelled Bookings Cron Script
 *
 * 차등 취소 로직:
 * - 즉시 취소 (유예 없음): down/middle payment 증빙 미업로드 또는 출발 34일 미만
 * - 24시간 유예: 일부 결제 증빙 존재 + 출발 ≥34일
 *
 * Crontab entry (run 4 times daily):
 * 0 0,6,12,18 * * * /usr/bin/php /var/www/html/backend/cron/auto_finalize_waiting_cancelled.php >> /var/log/auto_finalize_waiting_cancelled.log 2>&1
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log start time
$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Auto finalize waiting_cancelled cron job started\n";

try {
    // Load database connection
    require_once __DIR__ . '/../conn.php';

    // booking_payments helper
    $bpLib = __DIR__ . '/../lib/booking_payments.php';
    if (file_exists($bpLib)) {
        require_once $bpLib;
    }

    // Email notification service
    $email_service_file = __DIR__ . '/../services/email_notification_service.php';
    if (file_exists($email_service_file)) {
        require_once $email_service_file;
    }

    // Ensure booking_status_history table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS booking_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bookingId VARCHAR(50) NOT NULL,
            previousStatus VARCHAR(50),
            newStatus VARCHAR(50) NOT NULL,
            changedBy VARCHAR(100),
            changedByType VARCHAR(20),
            changedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bookingId (bookingId),
            INDEX idx_changedAt (changedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Find ALL bookings in waiting_cancelled status (not just 24hr+)
    $sql = "SELECT bookingId, bookingStatus, paymentStatus, paymentType, departureDate, updatedAt
            FROM bookings
            WHERE bookingStatus = 'waiting_cancelled'
            ORDER BY updatedAt ASC";

    $result = $conn->query($sql);

    if (!$result) {
        echo $logPrefix . "ERROR: Query failed: " . $conn->error . "\n";
        exit(1);
    }

    $processed = 0;
    $immediateCancel = 0;
    $graceCancel = 0;
    $graceWaiting = 0;
    $failed = 0;

    while ($row = $result->fetch_assoc()) {
        $processed++;
        $bookingId = $row['bookingId'];

        echo $logPrefix . "Processing bookingId={$bookingId} (paymentType={$row['paymentType']}, waiting_cancelled since {$row['updatedAt']})\n";

        // 출발일까지 남은 일수 계산
        $daysUntilDep = null;
        if (!empty($row['departureDate'])) {
            $now = new DateTime();
            $now->setTime(0, 0, 0);
            $depDate = new DateTime($row['departureDate']);
            $depDate->setTime(0, 0, 0);
            $daysUntilDep = (int)$now->diff($depDate)->format('%r%a');
        }

        // 결제 증빙 확인 (down 또는 middle 단계)
        $hasDownOrMiddleProof = false;
        if (function_exists('getPaymentsByBookingId')) {
            $payments = getPaymentsByBookingId($conn, $bookingId);
            foreach ($payments as $p) {
                $step = $p['paymentStep'] ?? '';
                if (in_array($step, ['down', 'middle']) && !empty($p['filePath'])) {
                    $hasDownOrMiddleProof = true;
                    break;
                }
            }
        }

        // 레거시 컬럼에서도 확인
        if (!$hasDownOrMiddleProof) {
            $legacyStmt = $conn->prepare("SELECT downPaymentFile FROM bookings WHERE bookingId = ?");
            $legacyStmt->bind_param('s', $bookingId);
            $legacyStmt->execute();
            $legacyRow = $legacyStmt->get_result()->fetch_assoc();
            $legacyStmt->close();
            if ($legacyRow && !empty($legacyRow['downPaymentFile'])) {
                $hasDownOrMiddleProof = true;
            }
        }

        // 즉시 취소 조건:
        // 1) down/middle payment 증빙 미업로드
        // 2) 출발 34일 미만
        $shouldImmediateCancel = !$hasDownOrMiddleProof || ($daysUntilDep !== null && $daysUntilDep < 34);

        if ($shouldImmediateCancel) {
            // 즉시 cancelled (유예 없음)
            $reason = !$hasDownOrMiddleProof
                ? 'No payment proof uploaded for down/middle payment.'
                : 'Departure is within 34 days.';
            echo $logPrefix . "  IMMEDIATE CANCEL: {$reason}\n";

            $conn->begin_transaction();
            try {
                $checkStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
                $checkStmt->bind_param('s', $bookingId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if (!$checkResult || $checkResult['bookingStatus'] !== 'waiting_cancelled') {
                    $conn->rollback();
                    echo $logPrefix . "  SKIP: No longer waiting_cancelled\n";
                    continue;
                }

                $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', updatedAt = NOW() WHERE bookingId = ?");
                $updStmt->bind_param('s', $bookingId);
                $updStmt->execute();
                $updStmt->close();

                $changedAt = date('Y-m-d H:i:s');
                $histStmt = $conn->prepare("
                    INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
                    VALUES (?, 'waiting_cancelled', 'cancelled', 'System (Auto-Finalize/Immediate)', 'system', ?)
                ");
                $histStmt->bind_param('ss', $bookingId, $changedAt);
                $histStmt->execute();
                $histStmt->close();

                $conn->commit();
                $immediateCancel++;
                echo $logPrefix . "  SUCCESS: Immediately cancelled bookingId={$bookingId}\n";

                if (function_exists('send_rejection_notification_email')) {
                    $emailReason = "Booking cancelled: {$reason} No grace period applied.";
                    $emailResult = send_rejection_notification_email($conn, $bookingId, 'auto_cancellation', $emailReason);
                    echo $logPrefix . "  EMAIL: " . ($emailResult['success'] ? 'Sent' : 'Failed - ' . ($emailResult['message'] ?? '')) . "\n";
                }
            } catch (Throwable $e) {
                $conn->rollback();
                $failed++;
                echo $logPrefix . "  ERROR: {$e->getMessage()}\n";
            }
            continue;
        }

        // 24시간 유예 확인
        $updatedAt = new DateTime($row['updatedAt']);
        $graceDeadline = clone $updatedAt;
        $graceDeadline->modify('+24 hours');
        $nowDt = new DateTime();

        if ($nowDt < $graceDeadline) {
            $graceWaiting++;
            echo $logPrefix . "  GRACE PERIOD: Still within 24hr grace (deadline: {$graceDeadline->format('Y-m-d H:i:s')})\n";
            continue;
        }

        // 24시간 경과 → 최종 취소
        $conn->begin_transaction();
        try {
            $checkStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$checkResult || $checkResult['bookingStatus'] !== 'waiting_cancelled') {
                $conn->rollback();
                echo $logPrefix . "  SKIP: No longer waiting_cancelled\n";
                continue;
            }

            $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', updatedAt = NOW() WHERE bookingId = ?");
            $updStmt->bind_param('s', $bookingId);
            $updStmt->execute();
            $updStmt->close();

            $changedAt = date('Y-m-d H:i:s');
            $histStmt = $conn->prepare("
                INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
                VALUES (?, 'waiting_cancelled', 'cancelled', 'System (Auto-Finalize)', 'system', ?)
            ");
            $histStmt->bind_param('ss', $bookingId, $changedAt);
            $histStmt->execute();
            $histStmt->close();

            $conn->commit();
            $graceCancel++;
            echo $logPrefix . "  SUCCESS: Finalized bookingId={$bookingId} to cancelled (24hr grace expired)\n";

            if (function_exists('send_rejection_notification_email')) {
                $reason = 'The 24-hour grace period has expired without payment proof submission. The booking has been permanently cancelled and reserved seats have been released.';
                $emailResult = send_rejection_notification_email($conn, $bookingId, 'auto_cancellation', $reason);
                echo $logPrefix . "  EMAIL: " . ($emailResult['success'] ? 'Sent' : 'Failed - ' . ($emailResult['message'] ?? '')) . "\n";
            }

        } catch (Throwable $e) {
            $conn->rollback();
            $failed++;
            echo $logPrefix . "  ERROR: Failed to finalize bookingId={$bookingId}: " . $e->getMessage() . "\n";
        }
    }

    // Log results
    $duration = round(microtime(true) - $startTime, 2);
    echo $logPrefix . "Completed in {$duration}s\n";
    echo $logPrefix . "Stats: Processed={$processed}, ImmediateCancel={$immediateCancel}, GraceCancel={$graceCancel}, GraceWaiting={$graceWaiting}, Failed={$failed}\n";

    // Close database connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

} catch (Exception $e) {
    echo $logPrefix . "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo $logPrefix . "Auto finalize waiting_cancelled cron job finished\n";
exit(0);
