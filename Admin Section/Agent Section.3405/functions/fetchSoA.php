<?php
// Connect to your database
require "../../conn.php"; // Include the DB connection
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['month']) && isset($_POST['year'])) 
{
  // Get the selected filter values from the POST request
  $companyId = $_POST['companyId'];
  $month = (int) $_POST['month'];
  $year = (int) $_POST['year'];

  $totalRequestCostSum = 0;
  $formattedTotalPriceSum = '0.00';
  $formattedTotalRequestCostSum = '0.00';
  $formattedTotalAmount = '0.00';

  // Query to get the branchAgentCode
  $sql1 = "SELECT c.companyName, b.branchAgentCode
          FROM company c 
          JOIN branch b ON c.branchId = b.branchId
          WHERE c.companyId = $companyId";
  $result = $conn->query($sql1);

  $businessUnit = "";
  if ($result && $result->num_rows > 0) 
  {
    $row = $result->fetch_assoc();
    $businessUnit = $row['branchAgentCode'];
    $_SESSION['companyName'] = $row['companyName']; // Store the branch name in the session
  }
  else
  {
    $businessUnit = null;
  }

  $transactNumbers = [];
  $totalPriceSum = 0;
  $count = 1;
  $table1 = '';
  $tableData1 = [];

  // 1st Table - Flight Data (Includes all flights with the same flightDepartureDate)
  $sql3 = "SELECT b.flightId as flightId, b.pax, b.transactNo, CONCAT(f.flightDepartureDate, ' - ', f.returnArrivalDate) AS flightDates, 
            CASE 
              WHEN cl.clientRole = 'Wholeseller' 
              THEN f.wholesalePrice 
              ELSE f.flightPrice 
            END AS flightPrice, b.totalPrice
          FROM booking b
          JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
          JOIN company c ON cl.companyId = c.companyId
          JOIN flight f ON b.flightId = f.flightId
          WHERE MONTH(f.flightDepartureDate) = $month
            AND YEAR(f.flightDepartureDate) = $year
            AND b.status = 'Confirmed'
            AND cl.companyId = $companyId
          ORDER BY f.flightId";

  $res3 = $conn->query($sql3);

  if ($res3 && $res3->num_rows > 0) 
  {
    while ($row = $res3->fetch_assoc()) 
    {
      $transactNumbers[] = $row['transactNo'];
      $totalPriceSum += $row['totalPrice'];

      // Format prices
      $formattedFlightPrice = number_format($row['flightPrice'], 2);
      $formattedTotalPrice = number_format($row['totalPrice'], 2);
      $formattedTotalPriceSum = number_format($totalPriceSum, 2);

      // Build table row
      $table1 .= "<tr>
                <td>$count</td>
                <td>{$row['flightDates']}</td>
                <td></td>
                <td>₱ $formattedFlightPrice</td>
                <td>{$row['pax']}</td>
                <td></td>
                <td>₱ $formattedTotalPrice</td>
              </tr>";

      // Store table data for session
      $tableData1[] = [
      'no' => $count,
      'contents' => $row['flightDates'],
      'price' => $formattedFlightPrice,
      'pax' => $row['pax'],
      'total_usd' => '',
      'total_php' => $formattedTotalPrice,
      ];

      $count++;
    }

    // Store session variables
    $_SESSION['tableData1'] = $tableData1;
    $_SESSION['totalPriceSum'] = number_format($totalPriceSum, 2);
  } 
  else 
  {
    $_SESSION['totalPriceSum'] = "0.00";
    $table1 = "<tr><td colspan='7'>No flight bookings found</td></tr>";
  }

  $transactNoString = "'" . implode("','", $transactNumbers) . "'";

  $totalCostSum = 0;
  $handlingFeeCount = 0;
  $handlingFeeTotal = 0;  // Default to 0 if no handling fees
  $totalFinal = 0;  // Default to 0
  $totalRequestCostSum = 0;
  $table2 = '';
  $tableData2 = [];

  // 2nd Table - Request Data
  $sql4 = "SELECT b.flightId, cd.details, cd.price, SUM(r.pax) AS pax, SUM(r.requestCost) AS requestCost,
            COUNT(CASE WHEN r.handlingFee != 0 THEN 1 ELSE NULL END) AS handlingFeeCount
          FROM `request` r
          JOIN concerndetails cd ON r.concernDetailsId = cd.concernDetailsId
          JOIN booking b ON r.transactNo = b.transactNo
          JOIN flight f ON b.flightId = f.flightId
          WHERE r.requestStatus = 'Confirmed' AND r.transactNo IN ($transactNoString)
          GROUP BY b.flightId, cd.details, cd.price, r.concernDetailsId";

  // Execute the query
  $res4 = $conn->query($sql4);

  if ($res4->num_rows > 0) 
  {
    while ($row = $res4->fetch_assoc()) 
    {
      $handlingFeeCount += $row['handlingFeeCount'];
      $totalCostSum += $row['requestCost'];
      $formattedRequestPrice = number_format($row['price'], 2);
      $formattedRequestCost = number_format($row['requestCost'], 2);
      $formattedRequestCostSum = number_format($totalCostSum, 2);

      $table2 .= "<tr>
                    <td>$count</td>
                    <td>{$row['details']}</td>
                    <td></td>
                    <td>₱ $formattedRequestPrice</td>
                    <td>{$row['pax']}</td>
                    <td></td>
                    <td>₱ $formattedRequestCost</td>
                  </tr>";

      $tableData2[] = [
      'no' => $count,  // Sequential number for requests
      'contents' => $row['details'],  // Request details
      'price' => $formattedRequestPrice,  // Formatted request price in PHP
      'pax' => $row['pax'],  // Number of passengers for the request
      'total_usd' => '',  // No USD conversion for requests
      'total_php' => $formattedRequestCost,  // Total request cost in PHP
      ];

      $count++;
    }

    // Add the Handling Fee row only if there are handling fees
    if ($handlingFeeCount > 0) 
    {
      $handlingFeeTotal = $handlingFeeCount * 100;
      $formattedHandlingFeeTotal = number_format($handlingFeeTotal, 2);

      $table2 .= "<tr>
                    <td>$count</td>
                    <td>Handling Fee</td>
                    <td></td>
                    <td>₱ 100.00</td>
                    <td>$handlingFeeCount</td>
                    <td></td>
                    <td>₱ $formattedHandlingFeeTotal</td>
                  </tr>";

      // Add handling fee to the tableData2 array
      $tableData2[] = [
      'no' => $count,
      'contents' => 'Handling Fee',
      'price' => '100.00',
      'pax' => $handlingFeeCount,
      'total_usd' => '',
      'total_php' => $formattedHandlingFeeTotal,
      ];

      $totalRequestCostSum = $totalCostSum + $handlingFeeTotal;
    } 
    else 
    {
      // If no handling fee, set totalRequestCostSum to totalCostSum
      $totalRequestCostSum = $totalCostSum;
    }

    $formattedTotalRequestCostSum = number_format($totalRequestCostSum, 2);

    $_SESSION['tableData2'] = $tableData2;
    $_SESSION['totalRequestCost'] = $formattedTotalRequestCostSum;
  } 
  else 
  {
    $table2 = "<tr><td colspan='7'>No request data found</td></tr>";
    $_SESSION['totalRequestCost'] = "0.00";  // Default value if no data
  }

  $table3 = "";
  $totalAmount = 0; // To calculate the total payment amount
  $tableData3 = []; // Array to store table3 data

  // 3rd Table - Payment Data
  $sql5 = "SELECT DISTINCT p.transactNo AS transactNo, p.paymentType AS paymentType, p.amount AS amount, 
              DATE(p.paymentDate) AS paymentDate
            FROM payment p
            JOIN booking b ON b.transactNo = p.transactNo
            JOIN flight f ON b.flightId = f.flightId
            WHERE p.paymentStatus = 'Approved' AND p.transactNo IN ($transactNoString)";

  $res5 = $conn->query($sql5);

  if ($res5->num_rows > 0) 
  {
    while ($row = $res5->fetch_assoc()) 
    {
      // Accumulate the total payment amount
      $totalAmount += $row['amount'];
      $formattedTotalAmount = number_format($totalAmount, 2);

      // Format the payment amount
      $formattedAmount = number_format($row['amount'], 2);

      // Format the payment date as "Month DD, YYYY"
      $formattedDate = DateTime::createFromFormat('Y-m-d', $row['paymentDate'])->format('F d, Y');

      // Build the table row
      $table3 .= "<tr>
                    <td>$count</td>
                    <td>{$row['paymentType']} - $formattedDate</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>₱ $formattedAmount</td>
                  </tr>";

      // Add row data to the tableData3 array
      $tableData3[] = [
          'no' => $count,
          'contents' => $row['paymentType'] . ' - ' . $formattedDate,
          'price' => '', 
          'pax' => '',
          'total_usd' => '',
          'total_php' => $formattedAmount 
      ];

      $count++; // Increment row counter
    }

    // Store table data and total amount in the session
    $_SESSION['tableData3'] = $tableData3;
    $_SESSION['totalAmount'] = $formattedTotalAmount; // Store formatted total amount
  } 
  else 
  {
    $table3 = "<tr><td colspan='7'>No Payment data found</td></tr>";
    $_SESSION['tableData3'] = [];
    $_SESSION['totalAmount'] = "0.00"; // Default value
  }

  $balance = ($totalPriceSum + $totalRequestCostSum) - $totalAmount;
  $formattedBalance = number_format($balance, 2);
  $_SESSION['balance'] = $formattedBalance;

  $dataAvailable = true;

  // Check if there is any data in the result sets (flight, request, payment)
  if ($res3->num_rows > 0 || $res4->num_rows > 0) 
  {
    $dataAvailable = true;
  }

  // Build HTML response
  $response = "
      <table id='soaTable' class='product-table'>
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
          <span>SUBTOTAL: </span>
        </div>
        <div class='subtotal-item-usd'>
          <span>USD:</span>
          <span class='subtotal-usd'></span>
        </div>
        <div class='subtotal-item-php'>
          <span>PHP:</span>
          <span class='subtotal-php'>₱ " . $formattedTotalPriceSum . "</span>
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
          <span class='subtotal-php'>₱ " . $formattedTotalRequestCostSum . "</span>
        </div>
      </div>
      <table class='product-table'>
        <tbody>
          $table3
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
          <span class='subtotal-php'>₱ " . $formattedTotalAmount . "</span>
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
          <span class='subtotal-php'>₱ " . $formattedBalance . "</span>
        </div>
      </div>
      ";

  // Send JSON response
  echo json_encode([
    'dataAvailable' => $dataAvailable,
      'htmlContent' => $response
  ]);
}

?>