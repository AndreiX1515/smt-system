<?php
/**
 *      
 * Add Login Page I18n Texts Script
 */

require_once 'conn.php';

//    
$loginTexts = [
    //   
    ['logIn', 'ko', '', 'login'],
    ['logIn', 'en', 'Log In', 'login'],
    ['logIn', 'tl', 'Mag-login', 'login'],
    ['email', 'ko', '', 'login'],
    ['email', 'en', 'Email', 'login'],
    ['email', 'tl', 'Email', 'login'],
    ['password', 'ko', '', 'login'],
    ['password', 'en', 'Password', 'login'],
    ['password', 'tl', 'Password', 'login'],
    ['passwordPlaceholder', 'ko', '8~12, // ', 'login'],
    ['passwordPlaceholder', 'en', '8-12 characters, including letters/numbers/special characters', 'login'],
    ['passwordPlaceholder', 'tl', '8-12 karakter, kasama ang mga titik/numero/espesyal na karakter', 'login'],
    ['autoLogin', 'ko', ' ', 'login'],
    ['autoLogin', 'en', 'Auto Login', 'login'],
    ['autoLogin', 'tl', 'Auto Login', 'login'],
    ['findId', 'ko', ' ', 'login'],
    ['findId', 'en', 'Find ID', 'login'],
    ['findId', 'tl', 'Hanapin ang ID', 'login'],
    ['findPassword', 'ko', ' ', 'login'],
    ['findPassword', 'en', 'Find Password', 'login'],
    ['findPassword', 'tl', 'Hanapin ang Password', 'login'],
    ['changePassword', 'ko', ' ', 'login'],
    ['changePassword', 'en', 'Change Password', 'login'],
    ['changePassword', 'tl', 'Palitan ang Password', 'login'],
    ['signUp', 'ko', '', 'login'],
    ['signUp', 'en', 'Sign Up', 'login'],
    ['signUp', 'tl', 'Mag-signup', 'login']
];

try {
    echo "     ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($loginTexts as $text) {
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
