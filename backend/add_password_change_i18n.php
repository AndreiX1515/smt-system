<?php
require_once 'conn.php';

//    i18n  
$passwordChangeTexts = [
    // 
    ['ko', 'currentPassword', ' ', 'password'],
    ['ko', 'newPassword', ' ', 'password'],
    ['ko', 'confirmNewPassword', '  ', 'password'],
    ['ko', 'passwordChanged', '  .', 'password'],
    ['ko', 'passwordChangeFailed', '  .', 'password'],
    ['ko', 'incorrectCurrentPassword', '   .', 'password'],
    ['ko', 'passwordChangeError', '    .', 'password'],
    
    // 
    ['en', 'currentPassword', 'Current Password', 'password'],
    ['en', 'newPassword', 'New Password', 'password'],
    ['en', 'confirmNewPassword', 'Confirm New Password', 'password'],
    ['en', 'passwordChanged', 'Password has been successfully changed.', 'password'],
    ['en', 'passwordChangeFailed', 'Failed to change password.', 'password'],
    ['en', 'incorrectCurrentPassword', 'Current password is incorrect.', 'password'],
    ['en', 'passwordChangeError', 'Error occurred while changing password.', 'password'],
    
    // 
    ['tl', 'currentPassword', 'Kasalukuyang Password', 'password'],
    ['tl', 'newPassword', 'Bagong Password', 'password'],
    ['tl', 'confirmNewPassword', 'Kumpirmahin ang Bagong Password', 'password'],
    ['tl', 'passwordChanged', 'Matagumpay na napalitan ang password.', 'password'],
    ['tl', 'passwordChangeFailed', 'Hindi ma-palitan ang password.', 'password'],
    ['tl', 'incorrectCurrentPassword', 'Mali ang kasalukuyang password.', 'password'],
    ['tl', 'passwordChangeError', 'May error na naganap habang nagpa-palit ng password.', 'password']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($passwordChangeTexts as $text) {
        $stmt->bind_param("ssss", $text[0], $text[1], $text[2], $text[3]);
        $stmt->execute();
        echo "Added: {$text[0]} - {$text[1]} = {$text[2]}\n";
    }
    
    echo "\n   i18n   !\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
