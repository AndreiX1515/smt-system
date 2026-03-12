<?php
/**
 *     
 * Add Additional My Page I18n Texts Script
 */

require_once 'conn.php';

//    
$additionalMypageTexts = [
    ['noRecentActivity', 'ko', '  .', 'mypage'],
    ['noRecentActivity', 'en', 'No recent activity.', 'mypage'],
    ['noRecentActivity', 'tl', 'Walang kamakailang aktibidad.', 'mypage'],
    ['bookingNumber', 'ko', ' ', 'mypage'],
    ['bookingNumber', 'en', 'Booking Number', 'mypage'],
    ['bookingNumber', 'tl', 'Numero ng Reserbasyon', 'mypage'],
    ['packageName', 'ko', '', 'mypage'],
    ['packageName', 'en', 'Package Name', 'mypage'],
    ['packageName', 'tl', 'Pangalan ng Package', 'mypage'],
    ['numberOfGuests', 'ko', ' ', 'mypage'],
    ['numberOfGuests', 'en', 'Number of Guests', 'mypage'],
    ['numberOfGuests', 'tl', 'Bilang ng mga Bisita', 'mypage'],
    ['adults', 'ko', '', 'mypage'],
    ['adults', 'en', 'Adults', 'mypage'],
    ['adults', 'tl', 'Mga Matanda', 'mypage'],
    ['children', 'ko', '', 'mypage'],
    ['children', 'en', 'Children', 'mypage'],
    ['children', 'tl', 'Mga Bata', 'mypage'],
    ['infants', 'ko', '', 'mypage'],
    ['infants', 'en', 'Infants', 'mypage'],
    ['infants', 'tl', 'Mga Sanggol', 'mypage'],
    ['reservationDetails', 'ko', '  ', 'mypage'],
    ['reservationDetails', 'en', 'Reservation Details', 'mypage'],
    ['reservationDetails', 'tl', 'Mga Detalye ng Reserbasyon', 'mypage']
];

try {
    echo "     ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($additionalMypageTexts as $text) {
        $stmt->bind_param('ssss', $text[0], $text[1], $text[2], $text[3]);
        if ($stmt->execute()) {
            $successCount++;
        } else {
            echo "✗    : " . $text[0] . " - " . $stmt->error . "\n";
        }
    }
    
    echo "✓      : " . $successCount . "\n";
    echo "🎉     !\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
