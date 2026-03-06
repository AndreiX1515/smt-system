<?php
/**
 * Email HTML Templates for Booking Notifications
 *
 * Templates:
 * - Booking confirmation (예약 확인)
 * - Payment due reminders (결제 마감 알림)
 */

/**
 * Get common email styles
 */
function get_email_styles(): string {
    return '
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: linear-gradient(135deg, #1a4b8c 0%, #2563eb 100%); padding: 30px 20px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
        .header .logo { color: #ffffff; font-size: 28px; font-weight: bold; margin-bottom: 10px; }
        .content { padding: 30px 20px; }
        .greeting { font-size: 18px; margin-bottom: 20px; }
        .info-box { background-color: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #2563eb; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6b7280; font-weight: 500; width: 140px; flex-shrink: 0; }
        .info-value { color: #111827; font-weight: 600; }
        .highlight-box { background-color: #fef3c7; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        .urgent-box { background-color: #fee2e2; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #ef4444; }
        .amount { font-size: 24px; font-weight: bold; color: #1a4b8c; }
        .due-date { font-size: 18px; font-weight: bold; color: #dc2626; }
        .btn { display: inline-block; background: linear-gradient(135deg, #1a4b8c 0%, #2563eb 100%); color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        .footer a { color: #2563eb; text-decoration: none; }
        .divider { height: 1px; background-color: #e5e7eb; margin: 20px 0; }
        .success-box { background-color: #ecfdf5; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #10b981; }
        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { padding: 10px 0; vertical-align: top; }
        table.info-table td.label { color: #6b7280; width: 140px; }
        table.info-table td.value { color: #111827; font-weight: 600; }
    ';
}

/**
 * Get booking confirmation email template
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'departureDate' => string (YYYY-MM-DD),
 *   'adults' => int,
 *   'children' => int,
 *   'infants' => int,
 *   'totalAmount' => float,
 *   'paymentType' => string ('staged' or 'full'),
 *   'downPaymentAmount' => float,
 *   'downPaymentDueDate' => string,
 *   'advancePaymentAmount' => float,
 *   'advancePaymentDueDate' => string,
 *   'balanceAmount' => float,
 *   'balanceDueDate' => string,
 *   'agentName' => string,
 *   'agentEmail' => string,
 *   'contactEmail' => string,
 *   'contactPhone' => string,
 * ]
 */
function get_booking_confirmation_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $departureDate = $data['departureDate'] ?? '';
    $departureDateFormatted = $departureDate ? date('F j, Y', strtotime($departureDate)) : '';

    $adults = (int)($data['adults'] ?? 0);
    $children = (int)($data['children'] ?? 0);
    $infants = (int)($data['infants'] ?? 0);
    $totalTravelers = $adults + $children + $infants;

    $travelerText = [];
    if ($adults > 0) $travelerText[] = "{$adults} Adult(s)";
    if ($children > 0) $travelerText[] = "{$children} Child(ren)";
    if ($infants > 0) $travelerText[] = "{$infants} Infant(s)";
    $travelerSummary = implode(', ', $travelerText);

    $totalAmount = number_format((float)($data['totalAmount'] ?? 0), 2);
    $paymentType = $data['paymentType'] ?? 'staged';
    $agentName = htmlspecialchars($data['agentName'] ?? '');

    // Build payment schedule HTML
    $paymentScheduleHtml = '';
    if ($paymentType === 'staged') {
        $downPaymentAmount = number_format((float)($data['downPaymentAmount'] ?? 0), 2);
        $downPaymentDueDate = $data['downPaymentDueDate'] ?? '';
        $downPaymentDueDateFormatted = $downPaymentDueDate ? date('F j, Y', strtotime($downPaymentDueDate)) : 'N/A';

        $advancePaymentAmount = number_format((float)($data['advancePaymentAmount'] ?? 0), 2);
        $advancePaymentDueDate = $data['advancePaymentDueDate'] ?? '';
        $advancePaymentDueDateFormatted = $advancePaymentDueDate ? date('F j, Y', strtotime($advancePaymentDueDate)) : 'N/A';

        $balanceAmount = number_format((float)($data['balanceAmount'] ?? 0), 2);
        $balanceDueDate = $data['balanceDueDate'] ?? '';
        $balanceDueDateFormatted = $balanceDueDate ? date('F j, Y', strtotime($balanceDueDate)) : 'N/A';

        $paymentScheduleHtml = <<<HTML
        <div class="highlight-box">
            <h3 style="margin: 0 0 15px 0; color: #92400e;">Payment Schedule</h3>
            <table class="info-table">
                <tr>
                    <td class="label">Down Payment:</td>
                    <td class="value">₱{$downPaymentAmount}</td>
                    <td class="label" style="width: 80px;">Due:</td>
                    <td class="value">{$downPaymentDueDateFormatted}</td>
                </tr>
                <tr>
                    <td class="label">Advance Payment:</td>
                    <td class="value">₱{$advancePaymentAmount}</td>
                    <td class="label" style="width: 80px;">Due:</td>
                    <td class="value">{$advancePaymentDueDateFormatted}</td>
                </tr>
                <tr>
                    <td class="label">Balance:</td>
                    <td class="value">₱{$balanceAmount}</td>
                    <td class="label" style="width: 80px;">Due:</td>
                    <td class="value">{$balanceDueDateFormatted}</td>
                </tr>
            </table>
        </div>
HTML;
    } else {
        $fullPaymentAmount = number_format((float)($data['fullPaymentAmount'] ?? $data['totalAmount'] ?? 0), 2);
        $fullPaymentDueDate = $data['fullPaymentDueDate'] ?? '';
        $fullPaymentDueDateFormatted = $fullPaymentDueDate ? date('F j, Y', strtotime($fullPaymentDueDate)) : 'N/A';

        $paymentScheduleHtml = <<<HTML
        <div class="highlight-box">
            <h3 style="margin: 0 0 15px 0; color: #92400e;">Payment Information</h3>
            <table class="info-table">
                <tr>
                    <td class="label">Full Payment:</td>
                    <td class="value">₱{$fullPaymentAmount}</td>
                    <td class="label" style="width: 80px;">Due:</td>
                    <td class="value">{$fullPaymentDueDateFormatted}</td>
                </tr>
            </table>
        </div>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">SMT Escape</div>
            <h1>Booking Confirmation</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>Thank you for your reservation! Your booking has been successfully created and is now being processed.</p>

            <div class="info-box">
                <h3 style="margin: 0 0 15px 0; color: #1a4b8c;">Booking Details</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                    <tr>
                        <td class="label">Departure Date:</td>
                        <td class="value">{$departureDateFormatted}</td>
                    </tr>
                    <tr>
                        <td class="label">Travelers:</td>
                        <td class="value">{$travelerSummary}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Amount:</td>
                        <td class="value" style="font-size: 18px; color: #1a4b8c;">₱{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            {$paymentScheduleHtml}

            <p style="margin-top: 20px;">Please ensure timely payment to secure your reservation. You can upload payment proof through your agent portal.</p>

            <div class="divider"></div>

            <p style="font-size: 14px; color: #6b7280;">
                If you have any questions or need assistance, please don't hesitate to contact us.
            </p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get payment due reminder email template
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'departureDate' => string,
 *   'paymentType' => string ('down_payment', 'advance_payment', 'balance'),
 *   'paymentAmount' => float,
 *   'dueDate' => string,
 *   'daysRemaining' => int (7 or 1),
 *   'agentName' => string,
 *   'totalAmount' => float,
 * ]
 */
function get_payment_due_reminder_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $departureDate = $data['departureDate'] ?? '';
    $departureDateFormatted = $departureDate ? date('F j, Y', strtotime($departureDate)) : '';

    $paymentType = $data['paymentType'] ?? 'down_payment';
    $paymentTypeLabels = [
        'down_payment' => 'Down Payment',
        'advance_payment' => 'Advance Payment',
        'balance' => 'Balance Payment',
    ];
    $paymentTypeLabel = $paymentTypeLabels[$paymentType] ?? 'Payment';

    $paymentAmount = number_format((float)($data['paymentAmount'] ?? 0), 2);
    $dueDate = $data['dueDate'] ?? '';
    $dueDateFormatted = $dueDate ? date('F j, Y', strtotime($dueDate)) : '';

    $daysRemaining = (int)($data['daysRemaining'] ?? 7);
    $agentName = htmlspecialchars($data['agentName'] ?? '');
    $totalAmount = number_format((float)($data['totalAmount'] ?? 0), 2);

    // Determine urgency level
    $isUrgent = ($daysRemaining <= 1);
    $urgencyClass = $isUrgent ? 'urgent-box' : 'highlight-box';
    $urgencyHeaderColor = $isUrgent ? '#dc2626' : '#92400e';
    $urgencyText = $isUrgent
        ? 'URGENT: Payment Due Tomorrow!'
        : "Payment Reminder - {$daysRemaining} Days Remaining";

    $reminderMessage = $isUrgent
        ? "This is a final reminder that your <strong>{$paymentTypeLabel}</strong> is due <strong>tomorrow</strong>. Please ensure payment is made to avoid any issues with your reservation."
        : "This is a friendly reminder that your <strong>{$paymentTypeLabel}</strong> is due in <strong>{$daysRemaining} days</strong>. Please make sure to submit your payment on time.";

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(135deg, {$urgencyHeaderColor} 0%, #f97316 100%);">
            <div class="logo">SMT Escape</div>
            <h1>{$urgencyText}</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>{$reminderMessage}</p>

            <div class="{$urgencyClass}">
                <h3 style="margin: 0 0 15px 0; color: {$urgencyHeaderColor};">Payment Details</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Payment Type:</td>
                        <td class="value">{$paymentTypeLabel}</td>
                    </tr>
                    <tr>
                        <td class="label">Amount Due:</td>
                        <td class="value" style="font-size: 20px; color: #1a4b8c;">₱{$paymentAmount}</td>
                    </tr>
                    <tr>
                        <td class="label">Due Date:</td>
                        <td class="value due-date">{$dueDateFormatted}</td>
                    </tr>
                </table>
            </div>

            <div class="info-box">
                <h3 style="margin: 0 0 15px 0; color: #1a4b8c;">Booking Information</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                    <tr>
                        <td class="label">Departure Date:</td>
                        <td class="value">{$departureDateFormatted}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Amount:</td>
                        <td class="value">₱{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            <p style="margin-top: 20px;">Please upload your payment proof through your agent portal to complete this payment.</p>

            <div class="divider"></div>

            <p style="font-size: 14px; color: #6b7280;">
                If you have already made this payment, please disregard this email. For any questions, please contact our support team.
            </p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get rejection notification email template (unified for booking/payment rejections)
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'rejectionType' => string ('booking', 'payment', 'change_request', 'auto_cancellation'),
 *   'paymentType' => string (for payment rejection: 'down_payment', 'advance_payment', 'balance', 'full_payment'),
 *   'reason' => string,
 *   'agentName' => string,
 * ]
 */
function get_rejection_notification_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $reason = htmlspecialchars($data['reason'] ?? '');
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');
    $rejectionType = $data['rejectionType'] ?? 'booking';

    // Determine rejection title and message based on type
    $rejectionTitle = 'Booking Rejected';
    $rejectionMessage = 'Your booking has been rejected.';

    if ($rejectionType === 'payment') {
        $paymentTypeLabels = [
            'down_payment' => 'Down Payment',
            'advance_payment' => 'Advance Payment',
            'balance' => 'Balance Payment',
            'full_payment' => 'Full Payment',
        ];
        $paymentType = $data['paymentType'] ?? 'payment';
        $paymentLabel = $paymentTypeLabels[$paymentType] ?? 'Payment';
        $rejectionTitle = "{$paymentLabel} Rejected";
        $rejectionMessage = "Your {$paymentLabel} proof has been rejected. Please upload a new payment proof.";
    } else if ($rejectionType === 'change_request') {
        $rejectionTitle = 'Change Request Rejected';
        $rejectionMessage = 'Your change request has been rejected.';
    } else if ($rejectionType === 'auto_cancellation') {
        $rejectionTitle = 'Booking Automatically Cancelled';
        $rejectionMessage = 'Your booking has been automatically cancelled due to missed payment deadline.';
    }

    // Reason section
    $reasonHtml = '';
    if (!empty($reason)) {
        $reasonHtml = <<<HTML
            <div class="urgent-box">
                <h3 style="margin: 0 0 10px 0; color: #dc2626;">Rejection Reason</h3>
                <p style="margin: 0; color: #333;">{$reason}</p>
            </div>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$rejectionTitle} - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(135deg, #dc2626 0%, #f97316 100%);">
            <div class="logo">SMT Escape</div>
            <h1>{$rejectionTitle}</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>{$rejectionMessage}</p>

            <div class="info-box">
                <h3 style="margin: 0 0 15px 0; color: #1a4b8c;">Booking Information</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                </table>
            </div>

            {$reasonHtml}

            <p style="margin-top: 20px;">Please check your booking status in your agent portal for more details.</p>

            <div class="divider"></div>

            <p style="font-size: 14px; color: #6b7280;">
                If you have any questions, please contact our support team.
            </p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get pending cancellation (waiting_cancelled) notification email template
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'departureDate' => string,
 *   'totalAmount' => float,
 *   'reason' => string,
 *   'agentName' => string,
 * ]
 */
function get_pending_cancellation_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $departureDate = $data['departureDate'] ?? '';
    $departureDateFormatted = $departureDate ? date('F j, Y', strtotime($departureDate)) : 'N/A';
    $totalAmount = number_format((float)($data['totalAmount'] ?? 0), 2);
    $reason = htmlspecialchars($data['reason'] ?? '');
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');

    $reasonHtml = '';
    if (!empty($reason)) {
        $reasonHtml = <<<HTML
            <div class="urgent-box">
                <h3 style="margin: 0 0 10px 0; color: #dc2626;">Reason</h3>
                <p style="margin: 0; color: #333;">{$reason}</p>
            </div>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Scheduled for Cancellation - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="logo">SMT Escape</div>
            <h1>Booking Scheduled for Cancellation</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>We are writing to inform you that the following booking is <strong>scheduled for cancellation</strong> due to a missed payment deadline.</p>

            <div class="highlight-box" style="border-left-color: #f59e0b;">
                <h3 style="margin: 0 0 10px 0; color: #92400e;">&#9888; You have 24 hours to take action</h3>
                <p style="margin: 0; color: #333;">
                    This booking will be <strong>automatically cancelled after 24 hours</strong> if no action is taken.
                    During this grace period, the reserved seats are still being held.
                </p>
                <p style="margin: 10px 0 0 0; color: #333;">
                    If you wish to keep this booking, please upload the payment proof or contact the admin immediately.
                </p>
            </div>

            <div class="info-box">
                <h3 style="margin: 0 0 15px 0; color: #1a4b8c;">Booking Information</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                    <tr>
                        <td class="label">Departure Date:</td>
                        <td class="value">{$departureDateFormatted}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Amount:</td>
                        <td class="value">&#8369;{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            {$reasonHtml}

            <p style="margin-top: 20px;">Please log in to your agent portal to upload payment proof or contact the admin for further assistance.</p>

            <div class="divider"></div>

            <p style="font-size: 14px; color: #6b7280;">
                If the 24-hour grace period expires without action, the booking will be permanently cancelled and the reserved seats will be released.
            </p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get traveler edit deadline reminder email template
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'departureDate' => string (YYYY-MM-DD),
 *   'editDeadlineDate' => string (YYYY-MM-DD),
 *   'agentName' => string,
 * ]
 */
function get_traveler_edit_deadline_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $departureDate = $data['departureDate'] ?? '';
    $departureDateFormatted = $departureDate ? date('F j, Y', strtotime($departureDate)) : '';
    $editDeadlineDate = $data['editDeadlineDate'] ?? '';
    $editDeadlineDateFormatted = $editDeadlineDate ? date('F j, Y', strtotime($editDeadlineDate)) : '';
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traveler Edit Deadline Reminder - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">SMT ESCAPE</div>
            <h1>Traveler Edit Deadline Reminder</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>This is a reminder that the <strong>traveler information edit deadline</strong> for the following booking is approaching.</p>

            <div class="info-box">
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                    <tr>
                        <td class="label">Departure Date:</td>
                        <td class="value">{$departureDateFormatted}</td>
                    </tr>
                </table>
            </div>

            <div class="urgent-box">
                <h3 style="margin: 0 0 10px 0; color: #991b1b;">Important: Edit Deadline</h3>
                <p style="margin: 0;">After <strong>{$editDeadlineDateFormatted}</strong> (45 days before departure), traveler information can no longer be modified without special admin approval.</p>
                <p style="margin: 10px 0 0 0;">Please ensure all traveler details (names, passport info, etc.) are correct before this date.</p>
            </div>

            <p>If you need to make any changes, please log in to the system and update the traveler information as soon as possible.</p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get approval notification email template
 *
 * @param array $data [
 *   'bookingId' => string,
 *   'packageName' => string,
 *   'approvalType' => string ('travelers', 'travelers_add_remove'),
 *   'agentName' => string,
 *   'priceAdjustment' => float (optional),
 *   'beforeTotal' => float (optional),
 *   'afterTotal' => float (optional),
 * ]
 */
function get_approval_notification_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');
    $approvalType = $data['approvalType'] ?? 'travelers';

    // Determine approval title and message based on type
    $approvalTitle = 'Change Request Approved';
    $approvalMessage = 'Your change request has been approved and applied.';

    if ($approvalType === 'travelers' || $approvalType === 'travelers_add_remove') {
        $approvalTitle = 'Traveler Changes Approved';
        $approvalMessage = 'Your traveler change request has been approved and the changes have been applied to your booking.';
    }

    // Price adjustment section
    $adjustmentHtml = '';
    $priceAdjustment = floatval($data['priceAdjustment'] ?? 0);
    if ($priceAdjustment != 0) {
        $beforeTotal = number_format(floatval($data['beforeTotal'] ?? 0), 2);
        $afterTotal = number_format(floatval($data['afterTotal'] ?? 0), 2);
        $adjustmentFormatted = ($priceAdjustment >= 0 ? '+' : '') . number_format($priceAdjustment, 2);
        $adjustmentColor = $priceAdjustment > 0 ? '#dc2626' : '#059669';

        $adjustmentHtml = <<<ADJHTML
            <div class="highlight-box">
                <h3 style="margin: 0 0 15px 0; color: #92400e;">Price Adjustment</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Previous Total:</td>
                        <td class="value">&#8369;{$beforeTotal}</td>
                    </tr>
                    <tr>
                        <td class="label">New Total:</td>
                        <td class="value">&#8369;{$afterTotal}</td>
                    </tr>
                    <tr>
                        <td class="label">Adjustment:</td>
                        <td class="value" style="color: {$adjustmentColor};">&#8369;{$adjustmentFormatted}</td>
                    </tr>
                </table>
            </div>
ADJHTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$approvalTitle} - {$bookingId}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
            <div class="logo">SMT Escape</div>
            <h1>{$approvalTitle}</h1>
        </div>

        <div class="content">
            <p class="greeting">Dear {$agentName},</p>

            <p>{$approvalMessage}</p>

            <div class="success-box">
                <h3 style="margin: 0 0 15px 0; color: #065f46;">Booking Information</h3>
                <table class="info-table">
                    <tr>
                        <td class="label">Booking ID:</td>
                        <td class="value">{$bookingId}</td>
                    </tr>
                    <tr>
                        <td class="label">Package:</td>
                        <td class="value">{$packageName}</td>
                    </tr>
                </table>
            </div>

            {$adjustmentHtml}

            <p style="margin-top: 20px;">Please check your booking details in your agent portal.</p>

            <div class="divider"></div>

            <p style="font-size: 14px; color: #6b7280;">
                If you have any questions, please contact our support team.
            </p>
        </div>

        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>&copy; 2024 SMT Escape. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Deadline Extended Email Template
 */
function get_deadline_extended_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');
    $newDueDate = htmlspecialchars($data['newDueDate'] ?? '');
    $extendedBy = htmlspecialchars($data['extendedBy'] ?? 'System');

    $stepLabels = [
        'down' => 'Down Payment', 'second' => 'Second Payment',
        'balance' => 'Balance Payment', 'full' => 'Full Payment',
        'middle' => 'Middle Payment', 'middle_balance' => 'Middle Balance Payment'
    ];
    $stepLabel = $stepLabels[$data['paymentStep'] ?? ''] ?? ucfirst($data['paymentStep'] ?? '');

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5;">
    {$styles}
    <div class="email-container">
        <div class="header"><h1>Payment Deadline Extended</h1></div>
        <div class="content">
            <p>Dear {$agentName},</p>
            <p>The payment deadline for your booking has been extended.</p>
            <div class="highlight-box">
                <table class="info-table">
                    <tr><td class="label">Booking ID:</td><td class="value">{$bookingId}</td></tr>
                    <tr><td class="label">Package:</td><td class="value">{$packageName}</td></tr>
                    <tr><td class="label">Payment Step:</td><td class="value">{$stepLabel}</td></tr>
                    <tr><td class="label">New Due Date:</td><td class="value" style="color: #059669; font-weight: bold;">{$newDueDate}</td></tr>
                    <tr><td class="label">Extended By:</td><td class="value">{$extendedBy}</td></tr>
                </table>
            </div>
            <p>Please ensure payment is completed before the new deadline to avoid cancellation.</p>
        </div>
        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Payment Step Change Email Template
 */
function get_payment_step_change_template(array $data): string {
    $styles = get_email_styles();

    $bookingId = htmlspecialchars($data['bookingId'] ?? '');
    $packageName = htmlspecialchars($data['packageName'] ?? '');
    $agentName = htmlspecialchars($data['agentName'] ?? 'Agent');
    $nextDueDate = htmlspecialchars($data['nextDueDate'] ?? 'N/A');

    $stepLabels = [
        'down' => 'Down Payment', 'second' => 'Second Payment',
        'balance' => 'Balance Payment', 'full' => 'Full Payment',
        'middle' => 'Middle Payment', 'middle_balance' => 'Middle Balance Payment'
    ];
    $completedLabel = $stepLabels[$data['completedStep'] ?? ''] ?? ucfirst($data['completedStep'] ?? '');
    $nextLabel = $stepLabels[$data['nextStep'] ?? ''] ?? ucfirst($data['nextStep'] ?? '');

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5;">
    {$styles}
    <div class="email-container">
        <div class="header"><h1>Payment Step Confirmed</h1></div>
        <div class="content">
            <p>Dear {$agentName},</p>
            <p>Your <strong>{$completedLabel}</strong> has been confirmed. Please proceed with the next payment step.</p>
            <div class="highlight-box">
                <table class="info-table">
                    <tr><td class="label">Booking ID:</td><td class="value">{$bookingId}</td></tr>
                    <tr><td class="label">Package:</td><td class="value">{$packageName}</td></tr>
                    <tr><td class="label">Completed:</td><td class="value" style="color: #059669;">{$completedLabel} &#10003;</td></tr>
                    <tr><td class="label">Next Step:</td><td class="value" style="color: #dc2626; font-weight: bold;">{$nextLabel}</td></tr>
                    <tr><td class="label">Due Date:</td><td class="value" style="font-weight: bold;">{$nextDueDate}</td></tr>
                </table>
            </div>
            <p>Please ensure the next payment is completed before the deadline.</p>
        </div>
        <div class="footer">
            <p><strong>SMT Escape</strong></p>
            <p>This is an automated message.</p>
        </div>
    </div>
</body>
</html>
HTML;
}
