<?php
// Include the connection file
include("../../conn.php");

// Set the correct header for JSON response
header('Content-Type: application/json');

// API key for fetching currency conversion rates
$apiKey = '77dc42e0276c97b3f723a125';

// Function to fetch the latest conversion rates from the API
function getExchangeRates($apiKey) {
    $url = "https://v6.exchangerate-api.com/v6/$apiKey/latest/USD"; // USD as the base currency
    
    // Initialize cURL to handle the request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if(curl_errno($ch)) {
        curl_close($ch);
        return json_encode(["status" => "error", "message" => "API request failed: " . curl_error($ch)]);
    }

    curl_close($ch);

    // Decode the API response
    $data = json_decode($response, true);
    
    if (!$data) {
        return json_encode(["status" => "error", "message" => "Failed to decode JSON response from API"]);
    }

    if (isset($data['result']) && $data['result'] == 'success') {
        return $data;
    } else {
        return json_encode(["status" => "error", "message" => "API rejected the request or returned invalid data."]);
    }
}

// Fetch fresh rates from the API
$exchangeRates = getExchangeRates($apiKey);
if (is_array($exchangeRates)) {
    $usd_to_php = $exchangeRates['conversion_rates']['PHP'] ?? 58.50;
    $usd_to_krw = $exchangeRates['conversion_rates']['KRW'] ?? 1420;

    // Database connection using mysqli
    global $conn; // Using the connection from conn.php
    
    // Check if there's already an entry for today
    $date = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM currency_conversions WHERE date = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count > 0) {
        echo json_encode(["status" => "error", "message" => "An entry for today already exists. No new entry added."]);
    } else {
        // Insert the conversion rates for today
        $stmt = $conn->prepare("INSERT INTO currency_conversions (date, usdtophp, usdtokrw, time_fetched) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('sdd', $date, $usd_to_php, $usd_to_krw);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Currency conversion rates for today inserted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to insert conversion rates into the database."]);
        }
        
        $stmt->close();
    }
} else {
    // If there's an issue with the response from the API, return the error
    echo $exchangeRates;
}

// Close the connection
$conn->close();
?>
