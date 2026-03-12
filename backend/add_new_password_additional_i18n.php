<?php
require_once 'conn.php';

//     i18n  
$newPasswordAdditionalTexts = [
    // 
    ['ko', 'newPasswordTitle', ' ', 'password'],
    
    // 
    ['en', 'newPasswordTitle', 'New Password', 'password'],
    
    // 
    ['tl', 'newPasswordTitle', 'Bagong Password', 'password']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($newPasswordAdditionalTexts as $text) {
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
