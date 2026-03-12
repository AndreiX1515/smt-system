<?php
require_once 'conn.php';

//    i18n  
$accountSettingTexts = [
    // 
    ['ko', 'accountSettings', ' ', 'account'],
    ['ko', 'editMemberInfo', '  ', 'account'],
    ['ko', 'changePassword', ' ', 'account'],
    
    // 
    ['en', 'accountSettings', 'Account Settings', 'account'],
    ['en', 'editMemberInfo', 'Edit Member Information', 'account'],
    ['en', 'changePassword', 'Change Password', 'account'],
    
    // 
    ['tl', 'accountSettings', 'Mga Setting ng Account', 'account'],
    ['tl', 'editMemberInfo', 'I-edit ang Impormasyon ng Miyembro', 'account'],
    ['tl', 'changePassword', 'Palitan ang Password', 'account']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($accountSettingTexts as $text) {
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
