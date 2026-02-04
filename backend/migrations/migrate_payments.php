<?php
/**
 * Payment Data Migration Script
 *
 * Migrates payment data from legacy bookings columns to the new booking_payments table.
 * Safe to run multiple times - uses upsert logic.
 *
 * Usage: php migrate_payments.php [--dry-run] [--limit=N]
 */

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../lib/booking_payments.php';

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

echo "===========================================\n";
echo "Payment Data Migration Script\n";
echo "===========================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "LIVE") . "\n";
echo "Limit: " . ($limit ? $limit : "No limit") . "\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "-------------------------------------------\n\n";

// Count existing records in booking_payments
$countResult = $conn->query("SELECT COUNT(*) as count FROM booking_payments");
$existingCount = $countResult->fetch_assoc()['count'];
echo "Existing records in booking_payments: $existingCount\n";

// Get all bookings with payment data
$sql = "SELECT * FROM bookings WHERE bookingStatus NOT IN ('draft') ORDER BY createdAt ASC";
if ($limit) {
    $sql .= " LIMIT $limit";
}

$result = $conn->query($sql);
$totalBookings = $result->num_rows;
echo "Total bookings to process: $totalBookings\n\n";

$successCount = 0;
$errorCount = 0;
$skippedCount = 0;
$errors = [];

$conn->begin_transaction();

try {
    while ($booking = $result->fetch_assoc()) {
        $bookingId = $booking['bookingId'];
        $paymentType = $booking['paymentType'] ?? 'staged';

        echo "Processing booking $bookingId (type: $paymentType)... ";

        if ($dryRun) {
            // In dry run, just validate
            $steps = [];
            switch ($paymentType) {
                case 'staged':
                    $steps = ['down', 'second', 'balance'];
                    break;
                case 'middle':
                    $steps = ['middle', 'middle_balance'];
                    break;
                case 'full':
                    $steps = ['full'];
                    break;
            }
            echo "Would create " . count($steps) . " payment records\n";
            $successCount++;
        } else {
            // Actually migrate the data
            if (syncFromLegacyBooking($conn, $booking)) {
                echo "OK\n";
                $successCount++;
            } else {
                echo "ERROR\n";
                $errors[] = "Failed to migrate booking $bookingId";
                $errorCount++;
            }
        }
    }

    if (!$dryRun && $errorCount === 0) {
        $conn->commit();
        echo "\n-------------------------------------------\n";
        echo "Transaction COMMITTED\n";
    } else if (!$dryRun) {
        $conn->rollback();
        echo "\n-------------------------------------------\n";
        echo "Transaction ROLLED BACK due to errors\n";
    }

} catch (Exception $e) {
    $conn->rollback();
    echo "\n-------------------------------------------\n";
    echo "Transaction ROLLED BACK due to exception:\n";
    echo $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// Summary
echo "\n===========================================\n";
echo "Migration Summary\n";
echo "===========================================\n";
echo "Total processed: $totalBookings\n";
echo "Successful: $successCount\n";
echo "Errors: $errorCount\n";
echo "Skipped: $skippedCount\n";

if (!$dryRun) {
    $newCountResult = $conn->query("SELECT COUNT(*) as count FROM booking_payments");
    $newCount = $newCountResult->fetch_assoc()['count'];
    echo "New records in booking_payments: $newCount\n";
    echo "Records added: " . ($newCount - $existingCount) . "\n";
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";

// Verification queries
if (!$dryRun && $errorCount === 0) {
    echo "\n===========================================\n";
    echo "Verification\n";
    echo "===========================================\n";

    // Check payment distribution by step
    $verifyResult = $conn->query("
        SELECT paymentStep, COUNT(*) as count,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN status = 'uploaded' THEN 1 ELSE 0 END) as uploaded,
               SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM booking_payments
        GROUP BY paymentStep
        ORDER BY FIELD(paymentStep, 'down', 'second', 'balance', 'full', 'middle', 'middle_balance')
    ");

    echo "\nPayment records by step:\n";
    echo str_pad("Step", 15) . str_pad("Total", 8) . str_pad("Pending", 10) . str_pad("Uploaded", 10) . str_pad("Confirmed", 10) . str_pad("Rejected", 10) . "\n";
    echo str_repeat("-", 63) . "\n";

    while ($row = $verifyResult->fetch_assoc()) {
        echo str_pad($row['paymentStep'], 15);
        echo str_pad($row['count'], 8);
        echo str_pad($row['pending'], 10);
        echo str_pad($row['uploaded'], 10);
        echo str_pad($row['confirmed'], 10);
        echo str_pad($row['rejected'], 10);
        echo "\n";
    }

    // Check for bookings without payment records
    $missingResult = $conn->query("
        SELECT COUNT(*) as count
        FROM bookings b
        LEFT JOIN booking_payments bp ON b.bookingId = bp.bookingId
        WHERE bp.id IS NULL
        AND b.bookingStatus NOT IN ('draft')
    ");
    $missingCount = $missingResult->fetch_assoc()['count'];
    echo "\nBookings without payment records: $missingCount\n";
}

$conn->close();
