<?php
/**
 * Booking Deadline Calculator
 *
 * Central helper for calculating payment deadlines based on:
 * - Days until departure
 * - Payment type (staged/middle/full)
 * - Approval date (approvedAt)
 */

/**
 * Calculate all payment deadlines for a booking.
 *
 * @param string $paymentType 'staged', 'middle', or 'full'
 * @param int $daysUntilDeparture Days from today to departure
 * @param string $departureDate 'Y-m-d' format
 * @param string $approvalDate 'Y-m-d H:i:s' format (DateTime of approval)
 * @return array [
 *   'paymentType' => string (possibly forced),
 *   'downPaymentDueDate' => string|null,
 *   'secondPaymentDueDate' => string|null,
 *   'middlePaymentDueDate' => string|null (stored in secondPaymentDueDate column),
 *   'balanceDueDate' => string|null,
 *   'fullPaymentDueDate' => string|null,
 *   'isDatetime' => bool (true if deadlines are DATETIME, false if DATE),
 *   'immediateCancel' => bool (true if < 34 days, should cancel on expiry without waiting_cancelled)
 * ]
 */
function calculateBookingDeadlines($paymentType, $daysUntilDeparture, $departureDate, $approvalDate) {
    $approval = new DateTime($approvalDate);
    $departure = new DateTime($departureDate);

    $result = [
        'paymentType' => $paymentType,
        'downPaymentDueDate' => null,
        'secondPaymentDueDate' => null,
        'balanceDueDate' => null,
        'fullPaymentDueDate' => null,
        'isDatetime' => false,
        'immediateCancel' => false,
    ];

    // Force payment type based on days until departure
    if ($daysUntilDeparture < 34) {
        $result['paymentType'] = 'full';
    } elseif ($daysUntilDeparture >= 34 && $daysUntilDeparture <= 39) {
        $result['paymentType'] = 'full';
    } elseif ($daysUntilDeparture >= 40 && $daysUntilDeparture <= 44) {
        if ($paymentType === 'staged') {
            $result['paymentType'] = 'middle';
        }
    }
    // > 44 days: all types allowed

    $paymentType = $result['paymentType'];

    if ($daysUntilDeparture < 34) {
        // < 34 days: full payment, +3 hours, immediate cancel
        $result['isDatetime'] = true;
        $result['immediateCancel'] = true;
        $deadline = clone $approval;
        $deadline->modify('+3 hours');
        $result['fullPaymentDueDate'] = $deadline->format('Y-m-d H:i:s');

    } elseif ($daysUntilDeparture >= 34 && $daysUntilDeparture <= 39) {
        // 34~39 days: full only, +24 hours
        $result['isDatetime'] = true;
        $result['immediateCancel'] = false;
        $deadline = clone $approval;
        $deadline->modify('+24 hours');
        $result['fullPaymentDueDate'] = $deadline->format('Y-m-d H:i:s');

    } elseif ($daysUntilDeparture >= 40 && $daysUntilDeparture <= 44) {
        if ($paymentType === 'middle') {
            // middle payment: +24 hours
            $result['isDatetime'] = true;
            $middleDeadline = clone $approval;
            $middleDeadline->modify('+24 hours');
            $result['secondPaymentDueDate'] = $middleDeadline->format('Y-m-d H:i:s');

            // balance: +3 days from approval
            $balanceDeadline = clone $approval;
            $balanceDeadline->modify('+3 days');
            $result['balanceDueDate'] = $balanceDeadline->format('Y-m-d H:i:s');
        } elseif ($paymentType === 'full') {
            // full payment: +3 days
            $result['isDatetime'] = true;
            $deadline = clone $approval;
            $deadline->modify('+3 days');
            $result['fullPaymentDueDate'] = $deadline->format('Y-m-d H:i:s');
        }

    } else {
        // > 44 days
        $result['isDatetime'] = false;
        $departureMinus40 = clone $departure;
        $departureMinus40->modify('-40 days');

        if ($paymentType === 'staged') {
            // down payment: approval +3 days
            $downDeadline = clone $approval;
            $downDeadline->modify('+3 days');
            $result['downPaymentDueDate'] = $downDeadline->format('Y-m-d');

            // second payment: approval +30 days, MAX departure-40
            $secondDeadline = clone $approval;
            $secondDeadline->modify('+30 days');
            if ($secondDeadline > $departureMinus40) {
                $secondDeadline = clone $departureMinus40;
            }
            $result['secondPaymentDueDate'] = $secondDeadline->format('Y-m-d');

            // balance: departure - 40 days (MIN = second deadline)
            $result['balanceDueDate'] = $departureMinus40->format('Y-m-d');

        } elseif ($paymentType === 'middle') {
            // middle payment: approval +3 days
            $middleDeadline = clone $approval;
            $middleDeadline->modify('+3 days');
            $result['secondPaymentDueDate'] = $middleDeadline->format('Y-m-d');

            // balance: departure - 40 days
            $result['balanceDueDate'] = $departureMinus40->format('Y-m-d');

        } elseif ($paymentType === 'full') {
            // full payment: approval +3 days
            $fullDeadline = clone $approval;
            $fullDeadline->modify('+3 days');
            $result['fullPaymentDueDate'] = $fullDeadline->format('Y-m-d');
        }
    }

    return $result;
}

/**
 * Determine the valid payment types for a given daysUntilDeparture.
 *
 * @param int $daysUntilDeparture
 * @return array List of valid payment type strings
 */
function getValidPaymentTypes($daysUntilDeparture) {
    if ($daysUntilDeparture < 34) {
        return ['full'];
    } elseif ($daysUntilDeparture >= 34 && $daysUntilDeparture <= 39) {
        return ['full'];
    } elseif ($daysUntilDeparture >= 40 && $daysUntilDeparture <= 44) {
        return ['middle', 'full'];
    } else {
        return ['staged', 'middle', 'full'];
    }
}

/**
 * Get the initial booking status based on payment type.
 *
 * @param string $paymentType
 * @return string
 */
function getInitialPaymentStatus($paymentType) {
    switch ($paymentType) {
        case 'staged':
            return 'waiting_down_payment';
        case 'middle':
            return 'waiting_advance_payment';
        case 'full':
            return 'waiting_full_payment';
        default:
            return 'waiting_full_payment';
    }
}
