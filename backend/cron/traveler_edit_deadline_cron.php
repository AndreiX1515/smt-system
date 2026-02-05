#!/usr/bin/php
<?php
/**
 * Traveler Edit Deadline Reminder Cron Script
 *
 * Sends email reminders to agents for bookings where departure is exactly 45 days away.
 * After this date, traveler information can no longer be freely modified.
 *
 * Crontab entry (run daily at 9:00 AM):
 * 0 9 * * * /usr/bin/php /var/www/html/backend/cron/traveler_edit_deadline_cron.php >> /var/log/traveler_edit_deadline_cron.log 2>&1
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log start time
$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Traveler edit deadline reminder cron job started\n";

try {
    // Load database connection
    require_once __DIR__ . '/../conn.php';

    // Load email notification service
    require_once __DIR__ . '/../services/email_notification_service.php';

    // Create service instance
    $service = new EmailNotificationService($conn);

    // Send traveler edit deadline reminders
    $stats = $service->sendTravelerEditDeadlineReminders();

    // Log results
    $duration = round(microtime(true) - $startTime, 2);
    echo $logPrefix . "Completed in {$duration}s\n";
    echo $logPrefix . "Stats: Processed={$stats['processed']}, Sent={$stats['sent']}, Failed={$stats['failed']}, Skipped={$stats['skipped']}\n";

    // Close database connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

} catch (Exception $e) {
    echo $logPrefix . "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo $logPrefix . "Traveler edit deadline reminder cron job finished\n";
exit(0);
