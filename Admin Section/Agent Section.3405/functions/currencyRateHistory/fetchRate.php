<?php
session_start();
require "../../../conn.php"; // Make sure path is correct on Hostinger

// === CONFIGURATION === //
$apiKey = '77dc42e0276c97b3f723a125'; // ExchangeRate-API key
$base = 'USD';
$target = 'PHP';
$fallbackRate = 56.50;
$primaryProvider = 'ExchangeRate-API';
$fallbackProvider = 'Manual Fallback';
date_default_timezone_set('Asia/Taipei');

$currentDate = date('Y-m-d');
$currentTime = date('Y-m-d H:i:s');

// === LOGGING === //
$logFile = __DIR__ . "/currency_rate_log.txt"; // Ensure write permissions

function logMessage($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// === API FETCH FUNCTION === //
function fetchExchangeRate($apiKey, $base, $target) {
    $url = "https://v6.exchangerate-api.com/v6/$apiKey/pair/$base/$target";
    $options = [
        "http" => [
            "method"  => "GET",
            "timeout" => 5
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['result']) && $data['result'] === 'success') {
            return $data['conversion_rate'];
        }
    }

    return false; // API failed
}

// === DEBUG TO CONSOLE === //
echo "<script>console.log('Current Time (Taipei): $currentTime');</script>";

// === MAIN LOGIC === //
$rate = fetchExchangeRate($apiKey, $base, $target);
$providerUsed = $rate ? $primaryProvider : $fallbackProvider;

if (!$rate) {
    $rate = $fallbackRate;
    $message = "⚠️ API failed. Fallback rate ₱$rate used.";
} else {
    $message = "✅ Rate retrieved: USD to PHP = ₱$rate";
}

// === DB INSERT === //
$insert = $conn->prepare("
    INSERT INTO currencyrates 
    (base_currency, target_currency, exchange_rate, date_recorded, time_recorded, provider) 
    VALUES (?, ?, ?, ?, ?, ?)
");

if ($insert) {
    $insert->bind_param("ssdsss", $base, $target, $rate, $currentDate, $currentTime, $providerUsed);
    $insert->execute();
    $insert->close();

    echo $message;
    logMessage("$message [$currentDate $currentTime]");
} else {
    echo "❌ Database error.";
    logMessage("❌ Database error: " . $conn->error);
}

$conn->close();
?>
