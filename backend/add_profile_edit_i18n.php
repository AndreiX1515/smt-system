<?php
require_once 'conn.php';

//    i18n  
$profileEditTexts = [
    // 
    ['ko', 'editProfile', ' ', 'profile'],
    ['ko', 'updateProfile', ' ', 'profile'],
    ['ko', 'profileUpdated', '  .', 'profile'],
    ['ko', 'profileUpdateFailed', '  .', 'profile'],
    
    // 
    ['en', 'editProfile', 'Edit Member Information', 'profile'],
    ['en', 'updateProfile', 'Update Profile', 'profile'],
    ['en', 'profileUpdated', 'Profile has been successfully updated.', 'profile'],
    ['en', 'profileUpdateFailed', 'Failed to update profile.', 'profile'],
    
    // 
    ['tl', 'editProfile', 'I-edit ang Profile', 'profile'],
    ['tl', 'updateProfile', 'I-update ang Profile', 'profile'],
    ['tl', 'profileUpdated', 'Matagumpay na na-update ang profile.', 'profile'],
    ['tl', 'profileUpdateFailed', 'Hindi ma-update ang profile.', 'profile']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($profileEditTexts as $text) {
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
