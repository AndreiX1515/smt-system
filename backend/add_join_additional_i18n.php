<?php
/**
 *      
 * Add Additional Join Page I18n Texts Script
 */

require_once 'conn.php';

//    
$additionalJoinTexts = [
    ['enterEmail', 'ko', ' .', 'join'],
    ['enterEmail', 'en', 'Please enter your email.', 'join'],
    ['enterEmail', 'tl', 'Pakipasok ang inyong email.', 'join'],
    ['checking', 'ko', ' ...', 'join'],
    ['checking', 'en', 'Checking...', 'join'],
    ['checking', 'tl', 'Sinusuri...', 'join'],
    ['checkComplete', 'ko', '', 'join'],
    ['checkComplete', 'en', 'Check Complete', 'join'],
    ['checkComplete', 'tl', 'Tapos na ang Pagsuri', 'join'],
    ['emailAvailable', 'ko', '  .', 'join'],
    ['emailAvailable', 'en', 'This email is available.', 'join'],
    ['emailAvailable', 'tl', 'Magagamit ang email na ito.', 'join'],
    ['emailCheckFailed', 'ko', '  .', 'join'],
    ['emailCheckFailed', 'en', 'Email check failed.', 'join'],
    ['emailCheckFailed', 'tl', 'Hindi matagumpay ang pagsuri ng email.', 'join'],
    ['networkError', 'ko', '  .  .', 'join'],
    ['networkError', 'en', 'Network error occurred. Please try again.', 'join'],
    ['networkError', 'tl', 'May naganap na network error. Pakisubukan ulit.', 'join'],
    ['enterAllFields', 'ko', '   .', 'join'],
    ['enterAllFields', 'en', 'Please enter all required fields.', 'join'],
    ['enterAllFields', 'tl', 'Pakipasok ang lahat ng kinakailangang field.', 'join'],
    ['agreeTerms', 'ko', '  .', 'join'],
    ['agreeTerms', 'en', 'Please agree to the required terms.', 'join'],
    ['agreeTerms', 'tl', 'Pakipagkasundo sa mga kinakailangang tuntunin.', 'join'],
    ['joinSuccess', 'ko', ' !', 'join'],
    ['joinSuccess', 'en', 'Registration completed successfully!', 'join'],
    ['joinSuccess', 'tl', 'Matagumpay na nakumpleto ang pagrehistro!', 'join'],
    ['joinFailed', 'ko', ' .  .', 'join'],
    ['joinFailed', 'en', 'Registration failed. Please try again.', 'join'],
    ['joinFailed', 'tl', 'Hindi matagumpay ang pagrehistro. Pakisubukan ulit.', 'join'],
    ['joining', 'ko', ' ...', 'join'],
    ['joining', 'en', 'Registering...', 'join'],
    ['joining', 'tl', 'Nagre-rehistro...', 'join']
];

try {
    echo "      ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($additionalJoinTexts as $text) {
        $stmt->bind_param('ssss', $text[0], $text[1], $text[2], $text[3]);
        if ($stmt->execute()) {
            $successCount++;
        } else {
            echo "✗    : " . $text[0] . " - " . $stmt->error . "\n";
        }
    }
    
    echo "✓       : " . $successCount . "\n";
    echo "🎉      !\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
