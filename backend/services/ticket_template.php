<?php
/**
 * Per-Booking Airline Ticket PDF Template Router
 * Routes to airline-specific template based on $data['airline']
 *
 * Expected: $data array with 'airline' key
 */

if (!isset($data)) {
    die('Template requires $data');
}

$airline = $data['airline'] ?? 'unknown';

if ($airline === 'airasia') {
    include __DIR__ . '/ticket_template_airasia.php';
} elseif ($airline === 'cebu_pacific') {
    include __DIR__ . '/ticket_template_cebu.php';
} else {
    // Generic template (original)
    include __DIR__ . '/ticket_template_generic.php';
}
