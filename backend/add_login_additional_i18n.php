<?php
/**
 *      
 * Add Additional Login Page I18n Texts Script
 */

require_once 'conn.php';

//    
$additionalLoginTexts = [
    ['enterEmailPassword', 'ko', '   .', 'login'],
    ['enterEmailPassword', 'en', 'Please enter both email and password.', 'login'],
    ['enterEmailPassword', 'tl', 'Pakipasok ang parehong email at password.', 'login'],
    ['invalidEmailFormat', 'ko', '   .', 'login'],
    ['invalidEmailFormat', 'en', 'Please enter a valid email format.', 'login'],
    ['invalidEmailFormat', 'tl', 'Pakipasok ang wastong format ng email.', 'login'],
    ['loggingIn', 'ko', ' ...', 'login'],
    ['loggingIn', 'en', 'Logging in...', 'login'],
    ['loggingIn', 'tl', 'Naglo-login...', 'login'],
    ['loginSuccess', 'ko', ' !', 'login'],
    ['loginSuccess', 'en', 'Login successful!', 'login'],
    ['loginSuccess', 'tl', 'Matagumpay na nag-login!', 'login'],
    ['loginFailed', 'ko', ' .   .', 'login'],
    ['loginFailed', 'en', 'Login failed. Please check your email and password.', 'login'],
    ['loginFailed', 'tl', 'Hindi matagumpay ang pag-login. Pakisuri ang inyong email at password.', 'login'],
    ['networkError', 'ko', '  .  .', 'login'],
    ['networkError', 'en', 'Network error occurred. Please try again.', 'login'],
    ['networkError', 'tl', 'May naganap na network error. Pakisubukan ulit.', 'login'],
    ['noMemberInfo', 'ko', '    .', 'login'],
    ['noMemberInfo', 'en', 'No member information matches the entered details.', 'login'],
    ['noMemberInfo', 'tl', 'Walang impormasyon ng miyembro na tumugma sa mga detalye na inilagay.', 'login'],
    ['loginLimitMessage', 'ko', '15    .          .', 'login'],
    ['loginLimitMessage', 'en', 'Please try again after 15 minutes. Login attempts have failed multiple times, and login is restricted for a certain period.', 'login'],
    ['loginLimitMessage', 'tl', 'Mangyaring subukan muli pagkatapos ng 15 minuto. Ang mga pagtatangka sa pag-login ay nabigo nang maraming beses, at ang pag-login ay limitado sa loob ng ilang panahon.', 'login']
];

try {
    echo "      ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($additionalLoginTexts as $text) {
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
