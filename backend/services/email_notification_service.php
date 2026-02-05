<?php
/**
 * Email Notification Service
 *
 * Handles sending booking-related email notifications:
 * - Booking confirmation emails
 * - Payment due reminder emails
 *
 * Features:
 * - Duplicate prevention via email_notification_logs table
 * - Error logging
 */

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email_templates.php';
require_once __DIR__ . '/../lib/booking_payments.php';

class EmailNotificationService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Send booking confirmation email to agent
     *
     * @param string $bookingId
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendBookingConfirmation(string $bookingId): array {
        try {
            // Get booking details with agent info
            $booking = $this->getBookingWithAgentInfo($bookingId);
            if (!$booking) {
                return ['success' => false, 'message' => 'Booking not found'];
            }

            $agentEmail = $booking['agentEmail'] ?? '';
            if (empty($agentEmail)) {
                return ['success' => false, 'message' => 'Agent email not found'];
            }

            // Check for duplicate
            if ($this->isNotificationSent($bookingId, 'booking_confirmation', $agentEmail)) {
                return ['success' => true, 'message' => 'Notification already sent'];
            }

            // Prepare template data
            $templateData = [
                'bookingId' => $booking['bookingId'],
                'packageName' => $booking['packageName'] ?? '',
                'departureDate' => $booking['departureDate'] ?? '',
                'adults' => $booking['adults'] ?? 0,
                'children' => $booking['children'] ?? 0,
                'infants' => $booking['infants'] ?? 0,
                'totalAmount' => $booking['totalAmount'] ?? 0,
                'paymentType' => $booking['paymentType'] ?? 'staged',
                'downPaymentAmount' => $booking['downPaymentAmount'] ?? 0,
                'downPaymentDueDate' => $booking['downPaymentDueDate'] ?? '',
                'advancePaymentAmount' => $booking['advancePaymentAmount'] ?? 0,
                'advancePaymentDueDate' => $booking['advancePaymentDueDate'] ?? '',
                'balanceAmount' => $booking['balanceAmount'] ?? 0,
                'balanceDueDate' => $booking['balanceDueDate'] ?? '',
                'fullPaymentAmount' => $booking['fullPaymentAmount'] ?? 0,
                'fullPaymentDueDate' => $booking['fullPaymentDueDate'] ?? '',
                'agentName' => $booking['agentName'] ?? 'Agent',
                'agentEmail' => $agentEmail,
                'contactEmail' => $booking['contactEmail'] ?? '',
                'contactPhone' => $booking['contactPhone'] ?? '',
            ];

            // Generate email content
            $htmlBody = get_booking_confirmation_template($templateData);
            $subject = "[SMT Escape] Booking Confirmation - {$bookingId}";

            // Send email
            $result = mailer_send($agentEmail, $subject, $htmlBody);

            // Log the notification
            $this->logNotification(
                $bookingId,
                'booking_confirmation',
                $agentEmail,
                $result['ok'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );

            if ($result['ok']) {
                return ['success' => true, 'message' => 'Booking confirmation email sent'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email: ' . ($result['error'] ?? 'Unknown error')];
            }

        } catch (Exception $e) {
            error_log("EmailNotificationService::sendBookingConfirmation error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send payment due reminders for all pending payments
     * Called by cron job
     *
     * @return array ['processed' => int, 'sent' => int, 'failed' => int, 'skipped' => int]
     */
    public function sendPaymentDueReminders(): array {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            // Get all bookings with pending payments
            $reminders = $this->getPendingPaymentReminders();

            foreach ($reminders as $reminder) {
                $stats['processed']++;

                $bookingId = $reminder['bookingId'];
                $notificationType = $reminder['notificationType'];
                $agentEmail = $reminder['agentEmail'];

                // Check for duplicate
                if ($this->isNotificationSent($bookingId, $notificationType, $agentEmail)) {
                    $stats['skipped']++;
                    continue;
                }

                // Prepare template data
                $templateData = [
                    'bookingId' => $bookingId,
                    'packageName' => $reminder['packageName'] ?? '',
                    'departureDate' => $reminder['departureDate'] ?? '',
                    'paymentType' => $reminder['paymentType'],
                    'paymentAmount' => $reminder['paymentAmount'],
                    'dueDate' => $reminder['dueDate'],
                    'daysRemaining' => $reminder['daysRemaining'],
                    'agentName' => $reminder['agentName'] ?? 'Agent',
                    'totalAmount' => $reminder['totalAmount'] ?? 0,
                ];

                // Generate email content
                $htmlBody = get_payment_due_reminder_template($templateData);
                $daysText = $reminder['daysRemaining'] == 1 ? '1 Day' : $reminder['daysRemaining'] . ' Days';
                $paymentTypeLabel = $this->getPaymentTypeLabel($reminder['paymentType']);
                $subject = "[SMT Escape] {$paymentTypeLabel} Due in {$daysText} - {$bookingId}";

                // Send email
                $result = mailer_send($agentEmail, $subject, $htmlBody);

                // Log the notification
                $this->logNotification(
                    $bookingId,
                    $notificationType,
                    $agentEmail,
                    $result['ok'] ? 'sent' : 'failed',
                    $result['error'] ?? null
                );

                if ($result['ok']) {
                    $stats['sent']++;
                } else {
                    $stats['failed']++;
                    error_log("Failed to send payment reminder for {$bookingId}: " . ($result['error'] ?? 'Unknown error'));
                }
            }

        } catch (Exception $e) {
            error_log("EmailNotificationService::sendPaymentDueReminders error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get booking details with agent information
     */
    private function getBookingWithAgentInfo(string $bookingId): ?array {
        $sql = "
            SELECT
                b.*,
                ag.personInChargeEmail as agentEmail,
                COALESCE(ag.agencyName, ag.personInCharge, CONCAT(COALESCE(a.firstName, ''), ' ', COALESCE(a.lastName, ''))) as agentName
            FROM bookings b
            LEFT JOIN accounts a ON b.accountId = a.accountId
            LEFT JOIN agent ag ON a.accountId = ag.accountId
            WHERE b.bookingId = ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            return null;
        }

        // Get payment data from new booking_payments table
        $paymentData = getPaymentsWithLegacyFormat($this->conn, $bookingId);
        $legacyPayments = $paymentData['legacy'];

        // Merge legacy payment data into booking
        foreach ($legacyPayments as $key => $value) {
            if (!isset($booking[$key]) || $booking[$key] === null || $booking[$key] === '') {
                $booking[$key] = $value;
            }
        }

        // Add payments array for new format
        $booking['payments'] = $paymentData['payments'];

        return $booking;
    }

    /**
     * Get all pending payment reminders (3 days and 1 day before due date)
     */
    private function getPendingPaymentReminders(): array {
        $reminders = [];

        // First, get reminders from the new booking_payments table
        $newTableReminders = getPendingPaymentReminders($this->conn, 3);

        foreach ($newTableReminders as $row) {
            $daysRemaining = 0;
            if (!empty($row['dueDate'])) {
                $dueDateTime = new DateTime($row['dueDate']);
                $today = new DateTime('today');
                $diff = $today->diff($dueDateTime);
                $daysRemaining = $diff->invert ? -$diff->days : $diff->days;
            }

            // Map paymentStep to paymentType
            $stepToType = [
                'down' => 'down_payment',
                'second' => 'advance_payment',
                'balance' => 'balance',
                'full' => 'full_payment',
                'middle' => 'middle_payment',
                'middle_balance' => 'middle_balance'
            ];
            $paymentType = $stepToType[$row['paymentStep']] ?? $row['paymentStep'];

            $notificationType = $paymentType . '_' . ($daysRemaining == 1 ? '1day' : '3day');

            $reminders[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'],
                'departureDate' => $row['departureDate'],
                'totalAmount' => $row['totalAmount'],
                'paymentType' => $paymentType,
                'paymentAmount' => $row['amount'],
                'dueDate' => $row['dueDate'],
                'daysRemaining' => $daysRemaining,
                'agentEmail' => $row['agentEmail'] ?? '',
                'agentName' => trim($row['agentName'] ?? ''),
                'notificationType' => $notificationType,
            ];
        }

        // Also check legacy columns for bookings not yet in new table
        $today = date('Y-m-d');

        $paymentTypes = [
            'down_payment' => [
                'amountCol' => 'downPaymentAmount',
                'dueDateCol' => 'downPaymentDueDate',
                'confirmedCol' => 'downPaymentConfirmedAt',
                'statuses' => ['pending', 'waiting_down_payment', 'checking_down_payment'],
            ],
            'advance_payment' => [
                'amountCol' => 'advancePaymentAmount',
                'dueDateCol' => 'advancePaymentDueDate',
                'confirmedCol' => 'advancePaymentConfirmedAt',
                'statuses' => ['waiting_advance_payment', 'checking_advance_payment', 'waiting_second_payment', 'checking_second_payment'],
            ],
            'balance' => [
                'amountCol' => 'balanceAmount',
                'dueDateCol' => 'balanceDueDate',
                'confirmedCol' => 'balanceConfirmedAt',
                'statuses' => ['waiting_balance', 'checking_balance'],
            ],
        ];

        // Collect booking IDs already processed from new table
        $processedIds = array_column($reminders, 'bookingId');

        foreach ($paymentTypes as $paymentType => $config) {
            $amountCol = $config['amountCol'];
            $dueDateCol = $config['dueDateCol'];
            $confirmedCol = $config['confirmedCol'];
            $statusList = "'" . implode("','", $config['statuses']) . "'";

            $sql = "
                SELECT
                    b.bookingId,
                    b.packageName,
                    b.departureDate,
                    b.totalAmount,
                    b.{$amountCol} as paymentAmount,
                    b.{$dueDateCol} as dueDate,
                    DATEDIFF(b.{$dueDateCol}, ?) as daysRemaining,
                    ag.personInChargeEmail as agentEmail,
                    COALESCE(ag.agencyName, ag.personInCharge, CONCAT(COALESCE(a.firstName, ''), ' ', COALESCE(a.lastName, ''))) as agentName
                FROM bookings b
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN agent ag ON a.accountId = ag.accountId
                WHERE b.bookingStatus IN ({$statusList})
                  AND b.paymentType = 'staged'
                  AND b.{$dueDateCol} IS NOT NULL
                  AND b.{$confirmedCol} IS NULL
                  AND b.{$amountCol} > 0
                  AND DATEDIFF(b.{$dueDateCol}, ?) IN (3, 1)
                  AND ag.personInChargeEmail IS NOT NULL
                  AND ag.personInChargeEmail != ''
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ss', $today, $today);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                // Skip if already processed from new table
                if (in_array($row['bookingId'], $processedIds)) {
                    continue;
                }

                $daysRemaining = (int)$row['daysRemaining'];
                $notificationType = $paymentType . '_' . ($daysRemaining == 1 ? '1day' : '3day');

                $reminders[] = [
                    'bookingId' => $row['bookingId'],
                    'packageName' => $row['packageName'],
                    'departureDate' => $row['departureDate'],
                    'totalAmount' => $row['totalAmount'],
                    'paymentType' => $paymentType,
                    'paymentAmount' => $row['paymentAmount'],
                    'dueDate' => $row['dueDate'],
                    'daysRemaining' => $daysRemaining,
                    'agentEmail' => $row['agentEmail'],
                    'agentName' => trim($row['agentName']),
                    'notificationType' => $notificationType,
                ];
            }

            $stmt->close();
        }

        return $reminders;
    }

    /**
     * Check if notification was already sent
     */
    private function isNotificationSent(string $bookingId, string $notificationType, string $recipientEmail): bool {
        $sql = "
            SELECT id FROM email_notification_logs
            WHERE bookingId = ? AND notificationType = ? AND recipientEmail = ? AND status = 'sent'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sss', $bookingId, $notificationType, $recipientEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Log notification to database
     */
    private function logNotification(
        string $bookingId,
        string $notificationType,
        string $recipientEmail,
        string $status,
        ?string $errorMessage = null
    ): void {
        try {
            $sql = "
                INSERT INTO email_notification_logs
                (bookingId, notificationType, recipientEmail, status, errorMessage, sentAt)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                errorMessage = VALUES(errorMessage),
                sentAt = NOW()
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('sssss', $bookingId, $notificationType, $recipientEmail, $status, $errorMessage);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }

    /**
     * Get human-readable payment type label
     */
    private function getPaymentTypeLabel(string $paymentType): string {
        $labels = [
            'down_payment' => 'Down Payment',
            'advance_payment' => 'Advance Payment',
            'balance' => 'Balance Payment',
        ];
        return $labels[$paymentType] ?? 'Payment';
    }

    /**
     * Send booking rejection notification email
     *
     * @param string $bookingId
     * @param string $rejectionType 'booking', 'payment', 'change_request'
     * @param string $reason Rejection reason
     * @param string|null $paymentType For payment rejection: 'down_payment', 'advance_payment', 'balance', 'full_payment'
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendBookingRejectionNotification(
        string $bookingId,
        string $rejectionType = 'booking',
        string $reason = '',
        ?string $paymentType = null
    ): array {
        try {
            // Get booking details with agent info
            $booking = $this->getBookingWithAgentInfo($bookingId);
            if (!$booking) {
                return ['success' => false, 'message' => 'Booking not found'];
            }

            $agentEmail = $booking['agentEmail'] ?? '';
            if (empty($agentEmail)) {
                return ['success' => false, 'message' => 'Agent email not found'];
            }

            // Create notification type key for duplicate check
            $notificationKey = 'rejection_' . $rejectionType;
            if ($paymentType) {
                $notificationKey .= '_' . $paymentType;
            }
            $notificationKey .= '_' . date('Ymd'); // Add date to allow re-notifications on different days

            // Prepare template data
            $templateData = [
                'bookingId' => $booking['bookingId'],
                'packageName' => $booking['packageName'] ?? '',
                'rejectionType' => $rejectionType,
                'paymentType' => $paymentType,
                'reason' => $reason,
                'agentName' => $booking['agentName'] ?? 'Agent',
            ];

            // Generate email content
            $htmlBody = get_rejection_notification_template($templateData);

            // Build subject based on rejection type
            if ($rejectionType === 'payment') {
                $paymentLabels = [
                    'down_payment' => 'Down Payment',
                    'advance_payment' => 'Advance Payment',
                    'balance' => 'Balance Payment',
                    'full_payment' => 'Full Payment',
                ];
                $paymentLabel = $paymentLabels[$paymentType] ?? 'Payment';
                $subject = "[SMT Escape] {$paymentLabel} Rejected - {$bookingId}";
            } else if ($rejectionType === 'change_request') {
                $subject = "[SMT Escape] Change Request Rejected - {$bookingId}";
            } else {
                $subject = "[SMT Escape] Booking Rejected - {$bookingId}";
            }

            // Send email
            $result = mailer_send($agentEmail, $subject, $htmlBody);

            // Log the notification
            $this->logNotification(
                $bookingId,
                $notificationKey,
                $agentEmail,
                $result['ok'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );

            if ($result['ok']) {
                return ['success' => true, 'message' => 'Rejection notification email sent'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email: ' . ($result['error'] ?? 'Unknown error')];
            }

        } catch (Exception $e) {
            error_log("EmailNotificationService::sendBookingRejectionNotification error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send traveler edit deadline reminders
     * For bookings where departure is exactly 45 days away
     * Called by cron job
     *
     * @return array ['processed' => int, 'sent' => int, 'failed' => int, 'skipped' => int]
     */
    public function sendTravelerEditDeadlineReminders(): array {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            // 출발 45일 전인 active 예약 조회
            $sql = "
                SELECT
                    b.bookingId,
                    b.packageName,
                    b.departureDate,
                    ag.personInChargeEmail as agentEmail,
                    COALESCE(ag.agencyName, ag.personInCharge, CONCAT(COALESCE(a.firstName, ''), ' ', COALESCE(a.lastName, ''))) as agentName
                FROM bookings b
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN agent ag ON a.accountId = ag.accountId
                WHERE DATEDIFF(b.departureDate, CURDATE()) = 45
                  AND b.bookingStatus NOT IN ('cancelled', 'completed', 'rejected')
                  AND ag.personInChargeEmail IS NOT NULL
                  AND ag.personInChargeEmail != ''
            ";

            $result = $this->conn->query($sql);
            if (!$result) return $stats;

            while ($row = $result->fetch_assoc()) {
                $stats['processed']++;

                $bookingId = $row['bookingId'];
                $agentEmail = $row['agentEmail'];

                // 중복 확인
                if ($this->isNotificationSent($bookingId, 'traveler_edit_deadline', $agentEmail)) {
                    $stats['skipped']++;
                    continue;
                }

                // 수정 마감일 계산 (출발일 - 45일 = 오늘)
                $editDeadlineDate = date('Y-m-d');

                $templateData = [
                    'bookingId' => $bookingId,
                    'packageName' => $row['packageName'] ?? '',
                    'departureDate' => $row['departureDate'] ?? '',
                    'editDeadlineDate' => $editDeadlineDate,
                    'agentName' => trim($row['agentName'] ?? 'Agent'),
                ];

                $htmlBody = get_traveler_edit_deadline_template($templateData);
                $subject = "[SMT Escape] Traveler Edit Deadline Reminder - {$bookingId}";

                $sendResult = mailer_send($agentEmail, $subject, $htmlBody);

                $this->logNotification(
                    $bookingId,
                    'traveler_edit_deadline',
                    $agentEmail,
                    $sendResult['ok'] ? 'sent' : 'failed',
                    $sendResult['error'] ?? null
                );

                if ($sendResult['ok']) {
                    $stats['sent']++;
                } else {
                    $stats['failed']++;
                    error_log("Failed to send traveler edit deadline reminder for {$bookingId}: " . ($sendResult['error'] ?? 'Unknown error'));
                }
            }

        } catch (Exception $e) {
            error_log("EmailNotificationService::sendTravelerEditDeadlineReminders error: " . $e->getMessage());
        }

        return $stats;
    }
}

/**
 * Helper function to send booking confirmation (for easy integration)
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId Booking ID
 * @return array ['success' => bool, 'message' => string]
 */
function send_booking_confirmation_email($conn, string $bookingId): array {
    $service = new EmailNotificationService($conn);
    return $service->sendBookingConfirmation($bookingId);
}

/**
 * Helper function to send rejection notification email
 *
 * @param mysqli $conn Database connection
 * @param string $bookingId Booking ID
 * @param string $rejectionType 'booking', 'payment', 'change_request'
 * @param string $reason Rejection reason
 * @param string|null $paymentType For payment rejection: 'down_payment', 'advance_payment', 'balance', 'full_payment'
 * @return array ['success' => bool, 'message' => string]
 */
function send_rejection_notification_email(
    $conn,
    string $bookingId,
    string $rejectionType = 'booking',
    string $reason = '',
    ?string $paymentType = null
): array {
    $service = new EmailNotificationService($conn);
    return $service->sendBookingRejectionNotification($bookingId, $rejectionType, $reason, $paymentType);
}
