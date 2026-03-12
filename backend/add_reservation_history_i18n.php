<?php
require_once 'conn.php';

//      
$i18nTexts = [
    'ko' => [
        'reservationHistory' => ' ',
        'upcoming' => '',
        'past' => '',
        'cancelled' => '',
        'haveQuestions' => ' ?',
        'customerSupport' => ''
    ],
    'en' => [
        'reservationHistory' => 'Reservation History',
        'upcoming' => 'Upcoming',
        'past' => 'Past',
        'cancelled' => 'Cancelled',
        'haveQuestions' => 'Have any questions?',
        'customerSupport' => 'Customer Support'
    ],
    'tl' => [
        'reservationHistory' => 'Kasaysayan ng Reserbasyon',
        'upcoming' => 'Paparating',
        'past' => 'Nakaraan',
        'cancelled' => 'Nakansela',
        'haveQuestions' => 'May mga tanong ba kayo?',
        'customerSupport' => 'Suporta ng Customer'
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
    
    echo "      .\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
}

$conn->close();
?>
