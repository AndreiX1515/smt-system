<?php
/**
 *      
 * Add Home Page I18n Texts Script
 */

require_once 'conn.php';

//    
$additionalTexts = [
    //  
    ['representative', 'ko', '', 'footer'],
    ['representative', 'en', 'Representative', 'footer'],
    ['representative', 'tl', 'Representative', 'footer'],
    ['address', 'ko', '', 'footer'],
    ['address', 'en', 'Address', 'footer'],
    ['address', 'tl', 'Address', 'footer'],
    ['businessRegistrationNumber', 'ko', '  ', 'footer'],
    ['businessRegistrationNumber', 'en', 'Business Registration Number', 'footer'],
    ['businessRegistrationNumber', 'tl', 'Business Registration Number', 'footer'],
    ['telemarketingRegistrationNumber', 'ko', '  ', 'footer'],
    ['telemarketingRegistrationNumber', 'en', 'Telemarketing Registration Number', 'footer'],
    ['telemarketingRegistrationNumber', 'tl', 'Telemarketing Registration Number', 'footer'],
    ['email', 'ko', '', 'footer'],
    ['email', 'en', 'Email', 'footer'],
    ['email', 'tl', 'Email', 'footer'],
    ['fax', 'ko', 'FAX', 'footer'],
    ['fax', 'en', 'FAX', 'footer'],
    ['fax', 'tl', 'FAX', 'footer'],
    ['privacyOfficer', 'ko', '', 'footer'],
    ['privacyOfficer', 'en', 'Privacy Officer', 'footer'],
    ['privacyOfficer', 'tl', 'Privacy Officer', 'footer'],
    ['tourismBusinessRegistrationNumber', 'ko', '', 'footer'],
    ['tourismBusinessRegistrationNumber', 'en', 'Tourism Business Registration Number', 'footer'],
    ['tourismBusinessRegistrationNumber', 'tl', 'Tourism Business Registration Number', 'footer'],
    ['registrationOffice', 'ko', '', 'footer'],
    ['registrationOffice', 'en', 'Registration Office', 'footer'],
    ['registrationOffice', 'tl', 'Registration Office', 'footer'],
    ['copyright', 'ko', 'COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.', 'footer'],
    ['copyright', 'en', 'COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.', 'footer'],
    ['copyright', 'tl', 'COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.', 'footer'],
    
    //   
    ['serviceRequiresPermission', 'ko', '     .', 'permission'],
    ['serviceRequiresPermission', 'en', 'This service requires permission to proceed', 'permission'],
    ['serviceRequiresPermission', 'tl', 'Ang serbisyong ito ay nangangailangan ng pahintulot para magpatuloy', 'permission'],
    ['locationOptional', 'ko', ' ()', 'permission'],
    ['locationOptional', 'en', 'Location (Optional)', 'permission'],
    ['locationOptional', 'tl', 'Lokasyon (Opsyonal)', 'permission'],
    ['locationDesc', 'ko', '     ', 'permission'],
    ['locationDesc', 'en', 'Used for providing nearby information and services', 'permission'],
    ['locationDesc', 'tl', 'Ginagamit para sa pagbibigay ng impormasyon at serbisyo sa paligid', 'permission'],
    ['notificationOptional', 'ko', ' ()', 'permission'],
    ['notificationOptional', 'en', 'Notification (Optional)', 'permission'],
    ['notificationOptional', 'tl', 'Notification (Opsyonal)', 'permission'],
    ['notificationDesc', 'ko', '  ', 'permission'],
    ['notificationDesc', 'en', 'Push notification alerts', 'permission'],
    ['notificationDesc', 'tl', 'Mga push notification alerts', 'permission']
];

try {
    echo "     ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($additionalTexts as $text) {
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
