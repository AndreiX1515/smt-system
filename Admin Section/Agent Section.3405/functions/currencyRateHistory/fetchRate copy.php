<?php
session_start();
require "../../../conn.php"; // DB connection

$apiKey = '77dc42e0276c97b3f723a125'; // ExchangeRate-API key
$base = 'USD';
$target = 'PHP';
$fallbackRate = 56.50;
$provider = 'ExchangeRate-API';

$logFile = __DIR__ . "/currency_rate_log.txt"; // Logs all activity
date_default_timezone_set('Asia/Taipei'); // Set the timezone to Taipei
$currentDate = date('Y-m-d');
$currentTime = date('Y-m-d H:i:s');  // Full current date and time in Taipei time
$currentHour = (int)date('H'); // Current hour extracted for checking
$cutoffTime = "08:00:00"; // Set cutoff time to 8:00 AM Taipei time

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Function to fetch exchange rate from API
function fetchRate($apiKey, $base, $target) {
    $url = "https://v6.exchangerate-api.com/v6/$apiKey/pair/$base/$target";
    $response = @file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}

// Echo current time to browser console
echo "<script>console.log('Current Time: $currentTime');</script>";

// Check if current time is 8:00 AM or past it in Taipei timezone
if ($currentTime >= "$currentDate $cutoffTime") {
    // Check if today's rate already exists in DB
    $check = $conn->prepare("SELECT * FROM currencyrates WHERE base_currency=? AND target_currency=? AND date_recorded=?");
    $check->bind_param("sss", $base, $target, $currentDate);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        // Fetch exchange rate from API
        $data = fetchRate($apiKey, $base, $target);

        if ($data && $data['result'] === 'success') {
            $rate = $data['conversion_rate'];

            // Insert rate into DB
            $insert = $conn->prepare("INSERT INTO currencyrates (base_currency, target_currency, exchange_rate, date_recorded, provider) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sssss", $base, $target, $rate, $currentDate, $provider);
            $insert->execute();
            $insert->close();

            $message = "✅ USD to PHP rate saved: ₱$rate ($currentDate)";
            echo $message;
            logMessage($message);
        } else {
            // Fallback rate in case API fails
            $rate = $fallbackRate;
            $fallbackProvider = 'Fallback Manual Rate';

            $insert = $conn->prepare("INSERT INTO currencyrates (base_currency, target_currency, exchange_rate, date_recorded, provider) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sssss", $base, $target, $rate, $currentDate, $fallbackProvider);
            $insert->execute();
            $insert->close();

            $message = "⚠️ API failed. Inserted fallback rate ₱$rate for $currentDate.";
            echo $message;
            logMessage($message);
        }
    } else {
        $message = "ℹ️ Rate already recorded for today ($currentDate).";
        echo $message;
        logMessage($message);
    }

    $check->close();
    $conn->close();
} else {
    // If it's before 8 AM, display a message and do not insert
    $message = "⏰ It's before 8:00 AM (Taipei time). Please wait until after 8:00 AM to record the daily rate.";
    echo $message;
    logMessage($message);
}
?>
