#!/usr/bin/php
<?php
/**
 * Auto Finalize waiting_cancelled Bookings Cron Script
 *
 * Finalizes bookings in waiting_cancelled status to cancelled after 24 hours.
 * Seats are released at this point (bookingStatus becomes 'cancelled',
 * so existing NOT IN ('cancelled','rejected') queries exclude them).
 *
 * Crontab entry (run daily at 3:00 AM):
 * 0 3 * * * /usr/bin/php /var/www/html/backend/cron/auto_finalize_waiting_cancelled.php >> /var/log/auto_finalize_waiting_cancelled.log 2>&1
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

    // Find bookings in waiting_cancelled for more than 24 hours
    $sql = "SELECT bookingId, bookingStatus, paymentStatus, updatedAt
            FROM bookings
            WHERE bookingStatus = 'waiting_cancelled'
              AND updatedAt <= NOW() - INTERVAL 1 DAY
            ORDER BY updatedAt ASC";

    $result = $conn->query($sql);

    if (!$result) {
        echo $logPrefix . "ERROR: Query failed: " . $conn->error . "\n";
        exit(1);
    }

    $processed = 0;
    $finalized = 0;
    $failed = 0;

    while ($row = $result->fetch_assoc()) {
        $processed++;
        $bookingId = $row['bookingId'];

        echo $logPrefix . "Processing bookingId={$bookingId} (waiting_cancelled since {$row['updatedAt']})\n";

        $conn->begin_transaction();
        try {
            // Lock row and verify still waiting_cancelled
            $checkStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$checkResult || $checkResult['bookingStatus'] !== 'waiting_cancelled') {
                $conn->rollback();
                echo $logPrefix . "  SKIP: bookingId={$bookingId} is no longer waiting_cancelled (current: " . ($checkResult['bookingStatus'] ?? 'null') . ")\n";
                continue;
            }

            // Finalize to cancelled
            $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', updatedAt = NOW() WHERE bookingId = ?");
            $updStmt->bind_param('s', $bookingId);
            $updStmt->execute();
            $updStmt->close();

            // Log status change in history
            $changedAt = date('Y-m-d H:i:s');
            $histStmt = $conn->prepare("
                INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
                VALUES (?, 'waiting_cancelled', 'cancelled', 'System (Auto-Finalize)', 'system', ?)
            ");
            $histStmt->bind_param('ss', $bookingId, $changedAt);
            $histStmt->execute();
            $histStmt->close();

            $conn->commit();
            $finalized++;
            echo $logPrefix . "  SUCCESS: Finalized bookingId={$bookingId} to cancelled\n";

            // Send final cancellation email
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
    echo $logPrefix . "Stats: Processed={$processed}, Finalized={$finalized}, Failed={$failed}\n";

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
