<?php
// Connect to your database
require "../../conn.php"; // Include the DB connection
session_start();

// Get the selected filter values from the POST request
$companyId = $_POST['companyId'];
$month = isset($_POST['month']) ? intval(date('m', strtotime($_POST['month']))) : date('m');
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Initialize totals and count variables
$totalPhpSum = 0;
$totalUsdSum = 0;
$totalPaymentAmount = 0;
$formattedTotalUsdSum = 0;  // Default to 0 if not set
$formattedTotalPhpSum = 0;  // Default to 0 if not set
$balance = 0;
$count = 1;

// Initialize data availability flag and table variables
$dataAvailable = false;
$table1 = '';
$table2 = '';
$tableData1 = [];
$tableData2 = [];

// Query: Fetch booking data
$sql = "SELECT f.startDate AS startDate, fh.hotelName AS hotelName, fr.rooms AS roomType, fr.price as roomPrice, 
          f.rooms AS NoofRooms, f.pax AS pax, f.phpPrice AS bookingPhpPrice, f.usdPrice AS bookingUsdPrice
        FROM `fit` f
        JOIN fithotel fh ON f.hotelId = fh.hotelId
        JOIN fitrooms fr ON f.roomId = fr.roomId
        JOIN branch b ON f.agentCode = b.branchAgentCode
        WHERE b.branchId = $companyId AND MONTH(f.startDate) = $month AND YEAR(f.startDate) = $year AND f.status = 'Completed'";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) 
{
  $dataAvailable = true;
  while ($row = $res->fetch_assoc()) 
  {
    // Booking Details
    $formattedBookingUsdPrice = number_format($row['roomPrice'], 2);
    $formattedPricePHP = number_format($row['bookingPhpPrice'], 2);
    $formattedPriceUSD = number_format($row['bookingUsdPrice'], 2);
    $totalPhpSum += $row['bookingPhpPrice'];
    $totalUsdSum += $row['bookingUsdPrice'];
    $formattedTotalPhpSum = number_format($totalPhpSum, 2);
    $formattedTotalUsdSum = number_format($totalUsdSum, 2);

    $table1 .= "<tr>
                  <td>$count</td>
                  <td>{$row['hotelName']} ({$row['roomType']}) No. of Rooms: {$row['NoofRooms']}</td>
                  <td>$ $formattedBookingUsdPrice</td>
                  <td>-</td>
                  <td>{$row['pax']}</td>
                  <td>$ " . $formattedPriceUSD . "</td>
                  <td>₱ " . $formattedPricePHP . "</td>
                </tr>";

    // Add the row data to the tableData1 array
    $tableData1[] = [
      'no' => $count,  // Sequential number
      'contents' => "{$row['hotelName']} ({$row['roomType']}) R: {$row['NoofRooms']}",  // Hotel and room details
      'price' => $formattedBookingUsdPrice,  // Formatted booking price in USD
      'pax' => $row['pax'],  // Number of passengers
      'total_usd' => $formattedPriceUSD,  // Total price in USD
      'total_php' => $formattedPricePHP,  // Total price in PHP
    ];

    $_SESSION['tableData1'] = $tableData1;
    $_SESSION['totalPhpSum'] = $formattedTotalPhpSum;
    $count++;
  }
} 
else 
{
  $table1 = "<tr><td colspan='7'>No Record found</td></tr>";
}

// Query: Fetch payment data
$sql2 = "SELECT fp.paymentType AS paymentType,  DATE_FORMAT(fp.paymentDate, '%M %d, %Y') AS paymentDate, fp.amount AS paymentAmount 
         FROM `fit` f
         JOIN fitpayment fp ON f.transactionNo = fp.transactNo
         JOIN branch b ON f.agentCode = b.branchAgentCode
         WHERE b.branchId = $companyId AND MONTH(f.startDate) = $month AND YEAR(f.startDate) = $year AND fp.paymentStatus = 'Approved'";

$res2 = $conn->query($sql2);

if ($res2 && $res2->num_rows > 0) 
{
  $dataAvailable = true;
  while ($row = $res2->fetch_assoc()) 
  {
    // Payment Details
    $formattedPaymentAmount = number_format($row['paymentAmount'], 2);
    $totalPaymentAmount += $row['paymentAmount'];

    $table2 .= "<tr>
                  <td>$count</td>
                  <td>{$row['paymentType']} - {$row['paymentDate']}</td>
                  <td>-</td>
                  <td></td>
                  <td>-</td>
                  <td>-</td>
                  <td>₱ $formattedPaymentAmount</td>
                </tr>";
    // Add the row data to the tableData1 array
    $tableData2[] = [
      'no' => $count,  // Sequential number
      'contents' =>  $row['paymentType'] . ' - ' . $row['paymentDate'],  // Hotel and room details
      'price' => '',  // Formatted booking price in USD
      'pax' => '',  // Number of passengers
      'total_usd' => '',  // Total price in USD
      'total_php' => $formattedPaymentAmount,  // Total price in PHP
    ];

    $_SESSION['tableData2'] = $tableData2;
    $_SESSION['totalPaymentAmount'] = $formattedPaymentAmount;
    $count++;
  }
} 
else 
{
  $table2 = "<tr><td colspan='7'>No Payment Records found</td></tr>";
}

$sql4 = "SELECT branchName FROM branch WHERE branchId = $companyId";
$res4 = $conn->query($sql4);

if ($res4 && $res4->num_rows > 0) 
{
  $row = $res4->fetch_assoc();
  $_SESSION['branchName'] = $row['branchName']; // Store the branch name in the session
} 
else 
{
  $_SESSION['branchName'] = 'Unknown Branch'; // Default value if branch is not found
}

// Generate subtotals
$formattedTotalPaymentAmount = number_format($totalPaymentAmount, 2);
$balance = $totalPhpSum - $totalPaymentAmount;
$formattedBalance = number_format($balance, 2);

// $_SESSION['table1'] = $table1;
$_SESSION['balance'] = $formattedBalance;

// Generate combined response
$response = "
  <table class='product-table'>
    <thead>
      <tr>
        <th>No.</th>
        <th>Contents</th>
        <th>Price (USD)</th>
        <th>Price (PHP)</th>
        <th>PAX</th>
        <th>Total (USD)</th>
        <th>Total (PHP)</th>
      </tr>
    </thead>
    <tbody>
      $table1
    </tbody>
  </table>
  <div class='subtotal-container'>
    <div class='balance'>
      <span>SUBTOTAL (Bookings): </span>
    </div>
    <div class='subtotal-item-usd'>
      <span>USD:</span>
      <span class='subtotal-usd'>$ $formattedTotalUsdSum</span>
    </div>
    <div class='subtotal-item-php'>
      <span>PHP:</span>
      <span class='subtotal-php'>₱ $formattedTotalPhpSum</span>
    </div>
  </div>
  <table class='product-table'>
    <tbody>
      $table2
    </tbody>
  </table>
  <div class='subtotal-container'>
    <div class='balance'>
      <span>SUBTOTAL: </span>
    </div>
    <div class='subtotal-item-usd'>
      <span>USD:</span>
      <span class='subtotal-usd'></span>
    </div>
    <div class='subtotal-item-php'>
      <span>PHP:</span>
      <span class='subtotal-php'>₱ $formattedTotalPaymentAmount</span>
    </div>
  </div>
  <div class='balance-container'>
    <div class='balance'>
      <span>BALANCE:</span>
    </div>
    <div class='balanceUSD'>
      <span>USD:</span>
      <span class='subtotal-usd'></span>
    </div>
    <div class='balancePHP'>
      <span>PHP:</span>
      <span class='subtotal-php'>₱ $formattedBalance</span>
    </div>
  </div>";

// Combine response and data availability flag
$responseData = [
    'dataAvailable' => $dataAvailable,
    'htmlContent' => $response
];

// Send the data as a JSON response
echo json_encode($responseData);
?>
