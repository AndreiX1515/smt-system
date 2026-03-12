<?php
require_once 'conn.php';

//     i18n  
$profileEditAdditionalTexts = [
    // 
    ['ko', 'profileLoadFailed', '    .', 'profile'],
    ['ko', 'profileLoadError', '    .', 'profile'],
    ['ko', 'profileUpdateError', '    .', 'profile'],
    ['ko', 'loginRequired', ' .', 'profile'],
    ['ko', 'enterAllRequiredFields', '   .', 'profile'],
    
    // 
    ['en', 'profileLoadFailed', 'Unable to load profile information.', 'profile'],
    ['en', 'profileLoadError', 'Error occurred while loading profile.', 'profile'],
    ['en', 'profileUpdateError', 'Error occurred while updating profile.', 'profile'],
    ['en', 'loginRequired', 'Login is required.', 'profile'],
    ['en', 'enterAllRequiredFields', 'Please enter all required information.', 'profile'],
    
    // 
    ['tl', 'profileLoadFailed', 'Hindi ma-load ang impormasyon ng profile.', 'profile'],
    ['tl', 'profileLoadError', 'May error na naganap habang naglo-load ng profile.', 'profile'],
    ['tl', 'profileUpdateError', 'May error na naganap habang nag-u-update ng profile.', 'profile'],
    ['tl', 'loginRequired', 'Kailangan mag-login.', 'profile'],
    ['tl', 'enterAllRequiredFields', 'Pakipasok ang lahat ng kinakailangang impormasyon.', 'profile']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($profileEditAdditionalTexts as $text) {
        $stmt->bind_param("ssss", $text[0], $text[1], $text[2], $text[3]);
        $stmt->execute();
        echo "Added: {$text[0]} - {$text[1]} = {$text[2]}\n";
    }
    
    echo "\n    i18n   !\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
