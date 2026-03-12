<?php
/**
 *      
 * Partnership Information Page I18n Text Addition Script
 */

require_once 'conn.php';

//    
$partnershipTexts = [
    ['trustedGlobalPartners', 'ko', '  <br>  ', 'partnership'],
    ['trustedGlobalPartners', 'en', 'Trusted Global Partners', 'partnership'],
    ['trustedGlobalPartners', 'tl', 'Pinagkakatiwalaang<br>Global Partners', 'partnership'],
    
    ['partnershipDescription', 'ko', 'Smart Travel   .', 'partnership'],
    ['partnershipDescription', 'en', 'These are the partner companies currently collaborating with SmartTravel.', 'partnership'],
    ['partnershipDescription', 'tl', 'Mga kasosyo na nakikipagtulungan sa Smart Travel.', 'partnership'],
    
    ['partners', 'ko', '', 'partnership'],
    ['partners', 'en', 'Partners', 'partnership'],
    ['partners', 'tl', 'Mga Kasosyo', 'partnership']
];

try {
    echo "     ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($partnershipTexts as $text) {
        $stmt->bind_param('ssss', $text[0], $text[1], $text[2], $text[3]);
        if ($stmt->execute()) {
            $successCount++;
            echo "✓   : {$text[0]} ({$text[1]})\n";
        } else {
            echo "✗   : {$text[0]} ({$text[1]}) - " . $stmt->error . "\n";
        }
    }
    
    echo "\n✓      : {$successCount}\n";
    echo "🎉     !\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>

