<?php
/**
 *     
 * Add My Page I18n Texts Script
 */

require_once 'conn.php';

//    
$mypageTexts = [
    //  
    ['loginPrompt', 'ko', '<br>  ', 'mypage'],
    ['loginPrompt', 'en', 'Log in and<br>plan your next trip', 'mypage'],
    ['loginPrompt', 'tl', 'Mag-login at<br>magplano ng susunod na trip', 'mypage'],
    ['welcomeMessage', 'ko', ', {userName}!', 'mypage'],
    ['welcomeMessage', 'en', 'Welcome, {userName}!', 'mypage'],
    ['welcomeMessage', 'tl', 'Maligayang pagdating, {userName}!', 'mypage'],
    ['loginSignup', 'ko', '/', 'mypage'],
    ['loginSignup', 'en', 'Login/Sign Up', 'mypage'],
    ['loginSignup', 'tl', 'Mag-login/Mag-signup', 'mypage'],
    ['accountSetting', 'ko', ' ', 'mypage'],
    ['accountSetting', 'en', 'Account Settings', 'mypage'],
    ['accountSetting', 'tl', 'Mga Setting ng Account', 'mypage'],
    ['packageTours', 'ko', ' ', 'mypage'],
    ['packageTours', 'en', 'Package Tours', 'mypage'],
    ['packageTours', 'tl', 'Package Tours', 'mypage'],
    ['bySeason', 'ko', '', 'mypage'],
    ['bySeason', 'en', 'By Season', 'mypage'],
    ['bySeason', 'tl', 'Ayon sa Season', 'mypage'],
    ['byRegion', 'ko', '', 'mypage'],
    ['byRegion', 'en', 'By Region', 'mypage'],
    ['byRegion', 'tl', 'Ayon sa Rehiyon', 'mypage'],
    ['byTheme', 'ko', '', 'mypage'],
    ['byTheme', 'en', 'By Theme', 'mypage'],
    ['byTheme', 'tl', 'Ayon sa Theme', 'mypage'],
    ['private', 'ko', '', 'mypage'],
    ['private', 'en', 'Private', 'mypage'],
    ['private', 'tl', 'Private', 'mypage'],
    ['dayTrip', 'ko', '', 'mypage'],
    ['dayTrip', 'en', 'Day Trip', 'mypage'],
    ['dayTrip', 'tl', 'Day Trip', 'mypage'],
    ['recentActivity', 'ko', ' ', 'mypage'],
    ['recentActivity', 'en', 'Recent Activity', 'mypage'],
    ['recentActivity', 'tl', 'Mga Kamakailang Aktibidad', 'mypage'],
    ['manageMyTrips', 'ko', '  ', 'mypage'],
    ['manageMyTrips', 'en', 'Manage My Trips', 'mypage'],
    ['manageMyTrips', 'tl', 'Pamahalaan ang Aking mga Trip', 'mypage'],
    ['reservationHistory', 'ko', ' ', 'mypage'],
    ['reservationHistory', 'en', 'Reservation History', 'mypage'],
    ['reservationHistory', 'tl', 'Kasaysayan ng Reserbasyon', 'mypage'],
    ['visaApplicationHistory', 'ko', '  ', 'mypage'],
    ['visaApplicationHistory', 'en', 'Visa Application History', 'mypage'],
    ['visaApplicationHistory', 'tl', 'Kasaysayan ng Visa Application', 'mypage'],
    ['settingsSupport', 'ko', '  ', 'mypage'],
    ['settingsSupport', 'en', 'Settings & Support', 'mypage'],
    ['settingsSupport', 'tl', 'Mga Setting at Suporta', 'mypage'],
    ['notice', 'ko', '', 'mypage'],
    ['notice', 'en', 'Notice', 'mypage'],
    ['notice', 'tl', 'Notice', 'mypage'],
    ['customerCenter', 'ko', '', 'mypage'],
    ['customerCenter', 'en', 'Customer Center', 'mypage'],
    ['customerCenter', 'tl', 'Customer Center', 'mypage']
];

try {
    echo "    ...\n";
    
    $stmt = $conn->prepare("INSERT INTO i18n_texts (textKey, languageCode, textValue, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE textValue = VALUES(textValue), updatedAt = CURRENT_TIMESTAMP");
    
    $successCount = 0;
    foreach ($mypageTexts as $text) {
        $stmt->bind_param('ssss', $text[0], $text[1], $text[2], $text[3]);
        if ($stmt->execute()) {
            $successCount++;
        } else {
            echo "✗    : " . $text[0] . " - " . $stmt->error . "\n";
        }
    }
    
    echo "✓     : " . $successCount . "\n";
    echo "🎉    !\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
