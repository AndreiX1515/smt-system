<?php
//   i18n   

require_once 'conn.php';

//    i18n 
$noticeTexts = [
    //  (ko)
    [
        'languageCode' => 'ko',
        'texts' => [
            ['textKey' => 'notice', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'noNotices', 'textValue' => ' .', 'category' => 'notice'],
            ['textKey' => 'loadingNotices', 'textValue' => '  ...', 'category' => 'notice'],
            ['textKey' => 'loadNoticesFailed', 'textValue' => '  .', 'category' => 'notice'],
            ['textKey' => 'general', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'booking', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'payment', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'visa', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'system', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'author', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'viewCount', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'priorityHigh', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'priorityMedium', 'textValue' => '', 'category' => 'notice'],
            ['textKey' => 'priorityLow', 'textValue' => '', 'category' => 'notice'],
        ]
    ],
    //  (en)
    [
        'languageCode' => 'en',
        'texts' => [
            ['textKey' => 'notice', 'textValue' => 'Notice', 'category' => 'notice'],
            ['textKey' => 'noNotices', 'textValue' => 'No notices.', 'category' => 'notice'],
            ['textKey' => 'loadingNotices', 'textValue' => 'Loading notices...', 'category' => 'notice'],
            ['textKey' => 'loadNoticesFailed', 'textValue' => 'Failed to load notices.', 'category' => 'notice'],
            ['textKey' => 'general', 'textValue' => 'General', 'category' => 'notice'],
            ['textKey' => 'booking', 'textValue' => 'Booking', 'category' => 'notice'],
            ['textKey' => 'payment', 'textValue' => 'Payment', 'category' => 'notice'],
            ['textKey' => 'visa', 'textValue' => 'Visa', 'category' => 'notice'],
            ['textKey' => 'system', 'textValue' => 'System', 'category' => 'notice'],
            ['textKey' => 'author', 'textValue' => 'Author', 'category' => 'notice'],
            ['textKey' => 'viewCount', 'textValue' => 'Views', 'category' => 'notice'],
            ['textKey' => 'priorityHigh', 'textValue' => 'High', 'category' => 'notice'],
            ['textKey' => 'priorityMedium', 'textValue' => 'Medium', 'category' => 'notice'],
            ['textKey' => 'priorityLow', 'textValue' => 'Low', 'category' => 'notice'],
        ]
    ],
    //  (tl)
    [
        'languageCode' => 'tl',
        'texts' => [
            ['textKey' => 'notice', 'textValue' => 'Notice', 'category' => 'notice'],
            ['textKey' => 'noNotices', 'textValue' => 'Walang mga notice.', 'category' => 'notice'],
            ['textKey' => 'loadingNotices', 'textValue' => 'Naglo-load ng mga notice...', 'category' => 'notice'],
            ['textKey' => 'loadNoticesFailed', 'textValue' => 'Nabigo sa pag-load ng mga notice.', 'category' => 'notice'],
            ['textKey' => 'general', 'textValue' => 'Pangkalahatan', 'category' => 'notice'],
            ['textKey' => 'booking', 'textValue' => 'Reserbasyon', 'category' => 'notice'],
            ['textKey' => 'payment', 'textValue' => 'Bayad', 'category' => 'notice'],
            ['textKey' => 'visa', 'textValue' => 'Visa', 'category' => 'notice'],
            ['textKey' => 'system', 'textValue' => 'Sistema', 'category' => 'notice'],
            ['textKey' => 'author', 'textValue' => 'May-akda', 'category' => 'notice'],
            ['textKey' => 'viewCount', 'textValue' => 'Mga View', 'category' => 'notice'],
            ['textKey' => 'priorityHigh', 'textValue' => 'Mataas', 'category' => 'notice'],
            ['textKey' => 'priorityMedium', 'textValue' => 'Katamtaman', 'category' => 'notice'],
            ['textKey' => 'priorityLow', 'textValue' => 'Mababa', 'category' => 'notice'],
        ]
    ]
];

try {
    $conn->begin_transaction();
    
    foreach ($noticeTexts as $languageData) {
        $languageCode = $languageData['languageCode'];
        
        foreach ($languageData['texts'] as $textData) {
            $textKey = $textData['textKey'];
            $textValue = $textData['textValue'];
            $category = $textData['category'];
            
            //    
            $checkStmt = $conn->prepare("SELECT textId FROM i18n_texts WHERE languageCode = ? AND textKey = ?");
            $checkStmt->bind_param("ss", $languageCode, $textKey);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                //   
                $updateStmt = $conn->prepare("UPDATE i18n_texts SET textValue = ?, category = ?, updatedAt = CURRENT_TIMESTAMP WHERE languageCode = ? AND textKey = ?");
                $updateStmt->bind_param("ssss", $textValue, $category, $languageCode, $textKey);
                $updateStmt->execute();
                echo "Updated: {$languageCode} - {$textKey} = {$textValue}\n";
            } else {
                //   
                $insertStmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("ssss", $languageCode, $textKey, $textValue, $category);
                $insertStmt->execute();
                echo "Inserted: {$languageCode} - {$textKey} = {$textValue}\n";
            }
        }
    }
    
    $conn->commit();
    echo "\n  i18n   .\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
