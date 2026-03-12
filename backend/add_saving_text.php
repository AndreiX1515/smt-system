<?php
require_once 'conn.php';

// saving  
$savingTexts = [
    // 
    ['ko', 'saving', ' ...', 'profile'],
    
    // 
    ['en', 'saving', 'Saving...', 'profile'],
    
    // 
    ['tl', 'saving', 'Nagse-save...', 'profile']
];

try {
    $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    foreach ($savingTexts as $text) {
        $stmt->bind_param("ssss", $text[0], $text[1], $text[2], $text[3]);
        $stmt->execute();
        echo "Added: {$text[0]} - {$text[1]} = {$text[2]}\n";
    }
    
    echo "\nsaving   !\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
