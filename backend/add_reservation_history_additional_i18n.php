<?php
require_once 'conn.php';

//      
$i18nTexts = [
    'ko' => [
        'noReservationHistory' => '  .',
        'noUpcomingTrips' => '   ',
        'noCompletedTrips' => '  ',
        'noCancelledBookings' => '  ',
        'planNewTrip' => '  ',
        'browseProducts' => ' ',
        'loadingReservationHistory' => '   ...',
        'loadBookingHistoryFailed' => '   .'
    ],
    'en' => [
        'noReservationHistory' => 'No reservation history.',
        'noUpcomingTrips' => 'No upcoming trips yet',
        'noCompletedTrips' => 'No completed trips',
        'noCancelledBookings' => 'No cancelled bookings',
        'planNewTrip' => 'Plan a new trip',
        'browseProducts' => 'Browse Products',
        'loadingReservationHistory' => 'Loading reservation history...',
        'loadBookingHistoryFailed' => 'Failed to load reservation history.'
    ],
    'tl' => [
        'noReservationHistory' => 'Walang kasaysayan ng reserbasyon.',
        'noUpcomingTrips' => 'Wala pang paparating na mga trip',
        'noCompletedTrips' => 'Walang natapos na mga trip',
        'noCancelledBookings' => 'Walang nakanselang mga reserbasyon',
        'planNewTrip' => 'Magplano ng bagong trip',
        'browseProducts' => 'Tingnan ang mga Produkto',
        'loadingReservationHistory' => 'Naglo-load ng kasaysayan ng reserbasyon...',
        'loadBookingHistoryFailed' => 'Nabigo sa pag-load ng kasaysayan ng reserbasyon.'
    ]
];

try {
    foreach ($i18nTexts as $langCode => $texts) {
        foreach ($texts as $textKey => $textValue) {
            $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, 'reservation') ON DUPLICATE KEY UPDATE textValue = VALUES(textValue)");
            $stmt->bind_param("sss", $langCode, $textKey, $textValue);
            $stmt->execute();
        }
    }
    
    echo "       .\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
}

$conn->close();
?>
