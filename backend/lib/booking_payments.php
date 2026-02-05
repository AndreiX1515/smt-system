<?php
/**
 * Booking Payments Helper Library
 *
 * Provides functions for managing the normalized booking_payments table.
 * This replaces the ~40 payment-related columns in the bookings table.
 */

/**
 * Get a specific payment by booking ID and payment step
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @param string $paymentStep The payment step (down, second, balance, full, middle, middle_balance)
 * @return array|null Payment record or null if not found
 */
function getPaymentByStep($conn, $bookingId, $paymentStep) {
    $stmt = $conn->prepare("
        SELECT * FROM booking_payments
        WHERE bookingId = ? AND paymentStep = ?
    ");
    $stmt->bind_param("ss", $bookingId, $paymentStep);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();
    return $payment;
}

/**
 * Get all payments for a booking
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @return array Array of payment records
 */
function getPaymentsByBookingId($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT * FROM booking_payments
        WHERE bookingId = ?
        ORDER BY FIELD(paymentStep, 'down', 'second', 'balance', 'full', 'middle', 'middle_balance')
    ");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
    return $payments;
}

/**
 * Insert or update a payment record
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @param string $paymentStep The payment step
 * @param array $data Associative array of fields to update
 * @return bool Success status
 */
function upsertPayment($conn, $bookingId, $paymentStep, $data) {
    // Check if record exists
    $existing = getPaymentByStep($conn, $bookingId, $paymentStep);

    if ($existing) {
        // Update existing record
        $setClauses = [];
        $types = "";
        $values = [];

        $allowedFields = ['amount', 'dueDate', 'filePath', 'fileName', 'uploadedAt',
                          'confirmedAt', 'confirmedBy', 'rejectedAt', 'rejectionReason', 'status'];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setClauses[] = "$field = ?";
                if ($value === null) {
                    $types .= "s";
                    $values[] = null;
                } elseif (is_int($value)) {
                    $types .= "i";
                    $values[] = $value;
                } elseif (is_float($value)) {
                    $types .= "d";
                    $values[] = $value;
                } else {
                    $types .= "s";
                    $values[] = $value;
                }
            }
        }

        if (empty($setClauses)) {
            return true; // Nothing to update
        }

        $sql = "UPDATE booking_payments SET " . implode(", ", $setClauses) .
               " WHERE bookingId = ? AND paymentStep = ?";
        $types .= "ss";
        $values[] = $bookingId;
        $values[] = $paymentStep;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    } else {
        // Insert new record
        $fields = ['bookingId', 'paymentStep'];
        $placeholders = ['?', '?'];
        $types = "ss";
        $values = [$bookingId, $paymentStep];

        $allowedFields = ['amount', 'dueDate', 'filePath', 'fileName', 'uploadedAt',
                          'confirmedAt', 'confirmedBy', 'rejectedAt', 'rejectionReason', 'status'];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $fields[] = $field;
                $placeholders[] = '?';
                if ($value === null) {
                    $types .= "s";
                    $values[] = null;
                } elseif (is_int($value)) {
                    $types .= "i";
                    $values[] = $value;
                } elseif (is_float($value)) {
                    $types .= "d";
                    $values[] = $value;
                } else {
                    $types .= "s";
                    $values[] = $value;
                }
            }
        }

        $sql = "INSERT INTO booking_payments (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

/**
 * Update payment status with optional extra fields
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @param string $paymentStep The payment step
 * @param string $status The new status (pending, uploaded, checking, confirmed, rejected)
 * @param array $extras Additional fields to update (e.g., confirmedAt, confirmedBy, rejectionReason)
 * @return bool Success status
 */
function updatePaymentStatus($conn, $bookingId, $paymentStep, $status, $extras = []) {
    $data = array_merge(['status' => $status], $extras);
    return upsertPayment($conn, $bookingId, $paymentStep, $data);
}

/**
 * Create payment records for a booking based on payment type
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @param string $paymentType The payment type (staged, middle, full)
 * @param array $amounts Associative array of amounts by step
 * @param array $dueDates Associative array of due dates by step
 * @return bool Success status
 */
function createPaymentsForBooking($conn, $bookingId, $paymentType, $amounts = [], $dueDates = []) {
    $success = true;

    switch ($paymentType) {
        case 'staged':
            // Down payment, second payment (advance), and balance
            $steps = ['down', 'second', 'balance'];
            break;
        case 'middle':
            // Middle payment and middle_balance
            $steps = ['middle', 'middle_balance'];
            break;
        case 'full':
            // Full payment only
            $steps = ['full'];
            break;
        default:
            return false;
    }

    foreach ($steps as $step) {
        $data = [
            'amount' => isset($amounts[$step]) ? $amounts[$step] : 0,
            'dueDate' => isset($dueDates[$step]) ? $dueDates[$step] : null,
            'status' => 'pending'
        ];

        if (!upsertPayment($conn, $bookingId, $step, $data)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Delete all payments for a booking
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @return bool Success status
 */
function deletePaymentsForBooking($conn, $bookingId) {
    $stmt = $conn->prepare("DELETE FROM booking_payments WHERE bookingId = ?");
    $stmt->bind_param("s", $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Format payments array as legacy format for backward compatibility
 * This converts the new table format to match the old bookings column format
 *
 * @param array $payments Array of payment records from getPaymentsByBookingId
 * @return array Legacy format payment data
 */
function formatPaymentsAsLegacy($payments) {
    $legacy = [];

    // Mapping from payment step to legacy field prefix
    $prefixMap = [
        'down' => 'downPayment',
        'second' => 'advancePayment',  // second = advance payment
        'balance' => 'balance',
        'full' => 'fullPayment',
        'middle' => 'middlePayment',
        'middle_balance' => 'middleBalancePayment'
    ];

    foreach ($payments as $payment) {
        $step = $payment['paymentStep'];
        $prefix = isset($prefixMap[$step]) ? $prefixMap[$step] : $step;

        // Amount
        $legacy[$prefix . 'Amount'] = $payment['amount'];

        // Due date
        $legacy[$prefix . 'DueDate'] = $payment['dueDate'];

        // File info
        $legacy[$prefix . 'File'] = $payment['filePath'];
        $legacy[$prefix . 'FileName'] = $payment['fileName'];
        $legacy[$prefix . 'UploadedAt'] = $payment['uploadedAt'];

        // Confirmation info
        $legacy[$prefix . 'ConfirmedAt'] = $payment['confirmedAt'];
        $legacy[$prefix . 'ConfirmedBy'] = $payment['confirmedBy'];

        // Rejection info
        $legacy[$prefix . 'RejectedAt'] = $payment['rejectedAt'];
        $legacy[$prefix . 'RejectionReason'] = $payment['rejectionReason'];

        // Status (new field, add for each step)
        $legacy[$prefix . 'Status'] = $payment['status'];
    }

    return $legacy;
}

/**
 * Get payments with legacy format merged for API response
 * Returns both new format (payments array) and legacy format (flat fields)
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @return array Combined payment data with both formats
 */
function getPaymentsWithLegacyFormat($conn, $bookingId) {
    $payments = getPaymentsByBookingId($conn, $bookingId);
    $legacy = formatPaymentsAsLegacy($payments);

    return [
        'payments' => $payments,
        'legacy' => $legacy
    ];
}

/**
 * Determine the current payment step based on booking status and payment statuses
 *
 * @param string $bookingStatus The booking status
 * @param string $paymentType The payment type (staged, middle, full)
 * @return string|null The current payment step
 */
function getCurrentPaymentStep($bookingStatus, $paymentType) {
    // Map booking status to payment step
    $statusToStep = [
        'waiting_down_payment' => 'down',
        'checking_down_payment' => 'down',
        'waiting_advance_payment' => 'second',
        'checking_advance_payment' => 'second',
        'waiting_second_payment' => 'second',
        'checking_second_payment' => 'second',
        'waiting_balance' => 'balance',
        'checking_balance' => 'balance',
        'waiting_full_payment' => 'full',
        'checking_full_payment' => 'full'
    ];

    return isset($statusToStep[$bookingStatus]) ? $statusToStep[$bookingStatus] : null;
}

/**
 * Check if a payment step has an uploaded file awaiting confirmation
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId The booking ID
 * @param string $paymentStep The payment step
 * @return bool True if payment has uploaded file pending confirmation
 */
function isPaymentAwaitingConfirmation($conn, $bookingId, $paymentStep) {
    $payment = getPaymentByStep($conn, $bookingId, $paymentStep);
    if (!$payment) {
        return false;
    }
    return $payment['status'] === 'uploaded' || $payment['status'] === 'checking';
}

/**
 * Get overdue payments (past due date, not confirmed)
 *
 * @param mysqli $conn Database connection
 * @param string|null $agentId Optional agent ID filter
 * @param int $limit Maximum number of records to return
 * @return array Array of overdue payment records with booking info
 */
function getOverduePayments($conn, $agentId = null, $limit = 100) {
    $sql = "
        SELECT bp.*, b.bookingId, b.packageName, b.departureDate, b.totalAmount,
               b.bookingStatus, b.paymentType, b.agentId
        FROM booking_payments bp
        JOIN bookings b ON bp.bookingId = b.bookingId
        WHERE bp.dueDate < CURDATE()
        AND bp.status NOT IN ('confirmed')
        AND b.bookingStatus NOT IN ('cancelled', 'confirmed', 'refunded', 'completed', 'rejected')
    ";

    $types = "";
    $params = [];

    if ($agentId !== null) {
        $sql .= " AND b.agentId = ?";
        $types .= "i";
        $params[] = $agentId;
    }

    $sql .= " ORDER BY bp.dueDate ASC LIMIT ?";
    $types .= "i";
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $overduePayments = [];
    while ($row = $result->fetch_assoc()) {
        $overduePayments[] = $row;
    }
    $stmt->close();

    return $overduePayments;
}

/**
 * Get pending payment reminders (due within specified days)
 *
 * @param mysqli $conn Database connection
 * @param int $daysBeforeDue Number of days before due date to include
 * @return array Array of payment records needing reminders
 */
function getPendingPaymentReminders($conn, $daysBeforeDue = 3) {
    $stmt = $conn->prepare("
        SELECT bp.*, b.bookingId, b.packageName, b.departureDate, b.totalAmount,
               b.bookingStatus, b.paymentType, b.agentId, b.contactEmail,
               a.email as agentEmail, a.name as agentName
        FROM booking_payments bp
        JOIN bookings b ON bp.bookingId = b.bookingId
        LEFT JOIN accounts a ON b.agentId = a.id
        WHERE bp.dueDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND bp.status = 'pending'
        AND b.bookingStatus NOT IN ('cancelled', 'refunded', 'completed', 'rejected', 'confirmed')
        ORDER BY bp.dueDate ASC
    ");
    $stmt->bind_param("i", $daysBeforeDue);
    $stmt->execute();
    $result = $stmt->get_result();

    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    $stmt->close();

    return $reminders;
}

/**
 * Sync payment data from legacy bookings columns to new table
 * Used during migration
 *
 * @param mysqli $conn Database connection
 * @param array $booking The booking record with legacy payment columns
 * @return bool Success status
 */
function syncFromLegacyBooking($conn, $booking) {
    $paymentType = $booking['paymentType'] ?? 'staged';
    $bookingId = $booking['bookingId'];
    $success = true;

    // Determine payment status based on file and confirmation
    $determineStatus = function($file, $confirmedAt, $rejectedAt) {
        if ($confirmedAt) return 'confirmed';
        if ($rejectedAt) return 'rejected';
        if ($file) return 'uploaded';
        return 'pending';
    };

    switch ($paymentType) {
        case 'staged':
            // Down payment
            $downData = [
                'amount' => $booking['downPaymentAmount'] ?? 0,
                'dueDate' => $booking['downPaymentDueDate'] ?? null,
                'filePath' => $booking['downPaymentFile'] ?? null,
                'fileName' => $booking['downPaymentFileName'] ?? null,
                'uploadedAt' => $booking['downPaymentUploadedAt'] ?? null,
                'confirmedAt' => $booking['downPaymentConfirmedAt'] ?? null,
                'confirmedBy' => $booking['downPaymentConfirmedBy'] ?? null,
                'rejectedAt' => $booking['downPaymentRejectedAt'] ?? null,
                'rejectionReason' => $booking['downPaymentRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['downPaymentFile'] ?? null,
                    $booking['downPaymentConfirmedAt'] ?? null,
                    $booking['downPaymentRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'down', $downData);

            // Second/Advance payment
            $secondData = [
                'amount' => $booking['advancePaymentAmount'] ?? 0,
                'dueDate' => $booking['advancePaymentDueDate'] ?? null,
                'filePath' => $booking['advancePaymentFile'] ?? null,
                'fileName' => $booking['advancePaymentFileName'] ?? null,
                'uploadedAt' => $booking['advancePaymentUploadedAt'] ?? null,
                'confirmedAt' => $booking['advancePaymentConfirmedAt'] ?? null,
                'confirmedBy' => $booking['advancePaymentConfirmedBy'] ?? null,
                'rejectedAt' => $booking['advancePaymentRejectedAt'] ?? null,
                'rejectionReason' => $booking['advancePaymentRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['advancePaymentFile'] ?? null,
                    $booking['advancePaymentConfirmedAt'] ?? null,
                    $booking['advancePaymentRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'second', $secondData);

            // Balance
            $balanceData = [
                'amount' => $booking['balanceAmount'] ?? 0,
                'dueDate' => $booking['balanceDueDate'] ?? null,
                'filePath' => $booking['balanceFile'] ?? null,
                'fileName' => $booking['balanceFileName'] ?? null,
                'uploadedAt' => $booking['balanceUploadedAt'] ?? null,
                'confirmedAt' => $booking['balanceConfirmedAt'] ?? null,
                'confirmedBy' => null, // Legacy didn't have this for balance
                'rejectedAt' => $booking['balanceRejectedAt'] ?? null,
                'rejectionReason' => $booking['balanceRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['balanceFile'] ?? null,
                    $booking['balanceConfirmedAt'] ?? null,
                    $booking['balanceRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'balance', $balanceData);
            break;

        case 'full':
            // Full payment
            $fullData = [
                'amount' => $booking['fullPaymentAmount'] ?? $booking['totalAmount'] ?? 0,
                'dueDate' => $booking['fullPaymentDueDate'] ?? null,
                'filePath' => $booking['fullPaymentFile'] ?? null,
                'fileName' => $booking['fullPaymentFileName'] ?? null,
                'uploadedAt' => $booking['fullPaymentUploadedAt'] ?? null,
                'confirmedAt' => $booking['fullPaymentConfirmedAt'] ?? null,
                'confirmedBy' => null, // Legacy didn't have this
                'rejectedAt' => $booking['fullPaymentRejectedAt'] ?? null,
                'rejectionReason' => $booking['fullPaymentRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['fullPaymentFile'] ?? null,
                    $booking['fullPaymentConfirmedAt'] ?? null,
                    $booking['fullPaymentRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'full', $fullData);
            break;

        case 'middle':
            // Middle payment - use down payment fields as middle
            $middleData = [
                'amount' => $booking['downPaymentAmount'] ?? 0,
                'dueDate' => $booking['downPaymentDueDate'] ?? null,
                'filePath' => $booking['downPaymentFile'] ?? null,
                'fileName' => $booking['downPaymentFileName'] ?? null,
                'uploadedAt' => $booking['downPaymentUploadedAt'] ?? null,
                'confirmedAt' => $booking['downPaymentConfirmedAt'] ?? null,
                'confirmedBy' => $booking['downPaymentConfirmedBy'] ?? null,
                'rejectedAt' => $booking['downPaymentRejectedAt'] ?? null,
                'rejectionReason' => $booking['downPaymentRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['downPaymentFile'] ?? null,
                    $booking['downPaymentConfirmedAt'] ?? null,
                    $booking['downPaymentRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'middle', $middleData);

            // Middle balance - use balance fields
            $middleBalanceData = [
                'amount' => $booking['balanceAmount'] ?? 0,
                'dueDate' => $booking['balanceDueDate'] ?? null,
                'filePath' => $booking['balanceFile'] ?? null,
                'fileName' => $booking['balanceFileName'] ?? null,
                'uploadedAt' => $booking['balanceUploadedAt'] ?? null,
                'confirmedAt' => $booking['balanceConfirmedAt'] ?? null,
                'confirmedBy' => null,
                'rejectedAt' => $booking['balanceRejectedAt'] ?? null,
                'rejectionReason' => $booking['balanceRejectionReason'] ?? null,
                'status' => $determineStatus(
                    $booking['balanceFile'] ?? null,
                    $booking['balanceConfirmedAt'] ?? null,
                    $booking['balanceRejectedAt'] ?? null
                )
            ];
            $success = $success && upsertPayment($conn, $bookingId, 'middle_balance', $middleBalanceData);
            break;
    }

    return $success;
}
