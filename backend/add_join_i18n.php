<?php
/**
 *      
 * Add Join Page I18n Texts Script
 */

require_once 'conn.php';

//    
$joinTexts = [
    //   
    ['name', 'ko', '', 'join'],
    ['name', 'en', 'Name', 'join'],
    ['name', 'tl', 'Pangalan', 'join'],
    ['phone', 'ko', '', 'join'],
    ['phone', 'en', 'Phone', 'join'],
    ['phone', 'tl', 'Telepono', 'join'],
    ['phonePlaceholder', 'ko', "'-'   ", 'join'],
    ['phonePlaceholder', 'en', "Enter numbers only without '-'", 'join'],
    ['phonePlaceholder', 'tl', "Ilagay ang mga numero lamang nang walang '-'", 'join'],
    ['passwordConfirm', 'ko', ' ', 'join'],
    ['passwordConfirm', 'en', 'Confirm Password', 'join'],
    ['passwordConfirm', 'tl', 'Kumpirmahin ang Password', 'join'],
    ['passwordConfirmPlaceholder', 'ko', ' ', 'join'],
    ['passwordConfirmPlaceholder', 'en', 'Confirm Password', 'join'],
    ['passwordConfirmPlaceholder', 'tl', 'Kumpirmahin ang Password', 'join'],
    ['duplicateCheck', 'ko', ' ', 'join'],
    ['duplicateCheck', 'en', 'Check Duplicate', 'join'],
    ['duplicateCheck', 'tl', 'Suriin ang Duplicate', 'join'],
    ['agreeAll', 'ko', ' ', 'join'],
    ['agreeAll', 'en', 'Agree All', 'join'],
    ['agreeAll', 'tl', 'Sumang-ayon sa Lahat', 'join'],
    ['privacyCollection', 'ko', '    ()', 'join'],
    ['privacyCollection', 'en', 'Personal Information Collection and Use (Required)', 'join'],
    ['privacyCollection', 'tl', 'Pagkolekta at Paggamit ng Personal na Impormasyon (Kailangan)', 'join'],
    ['privacyThirdParty', 'ko', ' 3  ()', 'join'],
    ['privacyThirdParty', 'en', 'Personal Information Third Party Provision (Required)', 'join'],
    ['privacyThirdParty', 'tl', 'Pagbibigay ng Personal na Impormasyon sa Ikatlong Partido (Kailangan)', 'join'],
    ['marketingConsent', 'ko', '   ()', 'join'],
    ['marketingConsent', 'en', 'Marketing Use Consent (Optional)', 'join'],
    ['marketingConsent', 'tl', 'Pagsang-ayon sa Paggamit ng Marketing (Opsiyonal)', 'join'],
    ['join', 'ko', '', 'join'],
    ['join', 'en', 'Sign Up', 'join'],
    ['join', 'tl', 'Mag-signup', 'join'],
    ['invalidEmailFormat', 'ko', '   .', 'join'],
    ['invalidEmailFormat', 'en', 'Email format is incorrect.', 'join'],
    ['invalidEmailFormat', 'tl', 'Mali ang format ng email.', 'join'],
    ['invalidPhoneFormat', 'ko', '   .', 'join'],
    ['invalidPhoneFormat', 'en', 'Phone format is incorrect.', 'join'],
    ['invalidPhoneFormat', 'tl', 'Mali ang format ng telepono.', 'join'],
    ['invalidPasswordFormat', 'ko', '   .', 'join'],
    ['invalidPasswordFormat', 'en', 'Password format is incorrect.', 'join'],
    ['invalidPasswordFormat', 'tl', 'Mali ang format ng password.', 'join'],
    ['passwordMismatch', 'ko', '  .', 'join'],
    ['passwordMismatch', 'en', 'Passwords do not match.', 'join'],
    ['passwordMismatch', 'tl', 'Hindi magkatugma ang mga password.', 'join']
];

try {
    echo "     ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($joinTexts as $text) {
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
