#!/usr/bin/php
<?php
/**
 * Visa Document Reminder Follow-up Cron Script
 *
 * Sends a follow-up reminder to agents who haven't submitted visa documents
 * 1 week after the initial visa reminder email was sent.
 *
 * Checks:
 * - Initial visa_reminder was sent 7+ days ago
 * - No visa_reminder_followup has been sent yet
 * - Visa documents not yet submitted (visaSend = 0 for all travelers in booking)
 * - Booking is still in active status (not cancelled/rejected)
 *
 * Crontab entry (run daily at 10:00 AM):
 * 0 10 * * * /usr/bin/php /var/www/html/backend/cron/visa_reminder_cron.php >> /var/log/visa_reminder_cron.log 2>&1
 */

date_default_timezone_set('Asia/Manila');

$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Visa reminder follow-up cron job started\n";

try {
    require_once __DIR__ . '/../conn.php';
    require_once __DIR__ . '/../services/email_notification_service.php';

    // Find bookings where:
    // 1) visa_reminder was sent 7+ days ago
    // 2) visa_reminder_followup has NOT been sent
    // 3) visa documents not yet submitted
    $sql = "
        SELECT DISTINCT enl.bookingId, enl.recipientEmail, enl.sentAt
        FROM email_notification_logs enl
        WHERE enl.notificationType = 'visa_reminder'
          AND enl.status = 'sent'
          AND enl.sentAt <= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM email_notification_logs enl2
              WHERE enl2.bookingId = enl.bookingId
                AND enl2.notificationType = 'visa_reminder_followup'
                AND enl2.status = 'sent'
          )
    ";

    $result = $conn->query($sql);
    if (!$result) {
        echo $logPrefix . "Query failed: " . $conn->error . "\n";
        exit(1);
    }

    $sent = 0;
    $skipped = 0;
    $failed = 0;

    while ($row = $result->fetch_assoc()) {
        $bookingId = $row['bookingId'];

        // Check booking is still active
        $bStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ?");
        $bStmt->bind_param('s', $bookingId);
        $bStmt->execute();
        $bResult = $bStmt->get_result();
        $booking = $bResult->fetch_assoc();
        $bStmt->close();

        if (!$booking || in_array($booking['bookingStatus'], ['cancelled', 'rejected', 'pending'])) {
            $skipped++;
            echo $logPrefix . "Skipped {$bookingId}: status={$booking['bookingStatus']}\n";
            continue;
        }

        // Check if any visa application for this booking still has visaSend = 0
        $vStmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN visaSend = 1 THEN 1 ELSE 0 END) as submitted
            FROM visa_applications
            WHERE transactNo = ?
        ");
        $vStmt->bind_param('s', $bookingId);
        $vStmt->execute();
        $vResult = $vStmt->get_result();
        $visaStats = $vResult->fetch_assoc();
        $vStmt->close();

        $total = intval($visaStats['total'] ?? 0);
        $submitted = intval($visaStats['submitted'] ?? 0);

        // Skip if no visa applications or all already submitted
        if ($total === 0 || $submitted >= $total) {
            $skipped++;
            echo $logPrefix . "Skipped {$bookingId}: all visa docs submitted ({$submitted}/{$total})\n";
            continue;
        }

        // Send follow-up reminder
        $service = new EmailNotificationService($conn);
        $emailResult = $service->sendVisaReminderFollowup($bookingId);

        if ($emailResult['success']) {
            $sent++;
            echo $logPrefix . "Sent follow-up for {$bookingId} ({$submitted}/{$total} submitted)\n";
        } else {
            $failed++;
            echo $logPrefix . "Failed for {$bookingId}: {$emailResult['message']}\n";
        }
    }

    $duration = round(microtime(true) - $startTime, 2);
    echo $logPrefix . "Completed in {$duration}s - Sent={$sent}, Skipped={$skipped}, Failed={$failed}\n";

    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

} catch (Exception $e) {
    echo $logPrefix . "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo $logPrefix . "Visa reminder follow-up cron job finished\n";
exit(0);
