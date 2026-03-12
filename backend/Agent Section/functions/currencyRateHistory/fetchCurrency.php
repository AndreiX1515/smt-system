<?php
require "../../../conn.php"; // DB connection

// Fetch all records for sorting and displaying
$query = "SELECT * FROM currencyRates ORDER BY base_currency, target_currency, time_recorded DESC";
$result = $conn->query($query);

$currencyMap = [];
$data = [];
$latestTimeRecorded = null; // For Last Updated

if ($result->num_rows > 0) {
    // Organize data by currency pair and date
    while ($row = $result->fetch_assoc()) {
        $pairKey = $row['base_currency'] . '_' . $row['target_currency'];
        $date = date('Y-m-d', strtotime($row['time_recorded']));
        $currencyMap[$pairKey][$date][] = $row;

        // Track the latest time_recorded
        if ($latestTimeRecorded === null || strtotime($row['time_recorded']) > strtotime($latestTimeRecorded)) {
            $latestTimeRecorded = $row['time_recorded'];
        }
    }

    // Process each currency pair
    foreach ($currencyMap as $pair => $dates) {
        krsort($dates); // Sort dates descending

        foreach ($dates as $currentDate => $entries) {
            foreach ($entries as $currentData) {
                $currentRate = $currentData['exchange_rate'];
                $currencyLabel = $currentData['base_currency'] . ' to ' . $currentData['target_currency'];
                $dateTime = $currentData['time_recorded'];

                $yesterday = date('Y-m-d', strtotime($currentDate . ' -1 day'));
                $percentageDiff = 'N/A';
                $changeClass = 'rate-neutral';
                $arrow = '';

                if (isset($dates[$yesterday])) {
                    $yesterdayRate = $dates[$yesterday][0]['exchange_rate'];
                    $diff = $currentRate - $yesterdayRate;
                    $percentChange = ($diff / $yesterdayRate) * 100;
                    $arrow = $percentChange > 0 ? '↑' : ($percentChange < 0 ? '↓' : '');
                    $changeClass = $percentChange > 0 ? 'rate-up' : ($percentChange < 0 ? 'rate-down' : 'rate-neutral');
                    $symbol = $percentChange >= 0 ? '+' : '';
                    $percentageDiff = $arrow . ' ' . $symbol . number_format($percentChange, 2) . '%';
                }

                $data[] = [
                    'currencyLabel' => $currencyLabel,
                    'currentRate' => $currentRate,
                    'percentageDiff' => $percentageDiff,
                    'changeClass' => $changeClass,
                    'dateTime' => $dateTime
                ];
            }
        }
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode([
    'data' => $data,
    'latestTimeRecorded' => $latestTimeRecorded
]);
?>
