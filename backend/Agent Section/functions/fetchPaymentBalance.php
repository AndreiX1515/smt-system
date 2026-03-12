<?php
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['paymentTitle'])) 
  {
    $paymentTitle = $_POST['paymentTitle'];
    $transactionNumber = $_POST['transactionNumber']; // Assuming it's alphanumeric like 'A001-000001'

    if ($paymentTitle == "Package Payment") 
    {
      // Prepare and execute the query to get the total price
      $sql1 = "SELECT transactNo, totalPrice FROM booking WHERE transactNo = ?";
      $stmt = $conn->prepare($sql1);
      $stmt->bind_param("s", $transactionNumber); // Bind the transaction number as a string
      $stmt->execute();
      $result = $stmt->get_result();

      // Initialize totalPrice variable
      $totalPrice = 0.00;

      // Fetch the total price
      if ($result->num_rows > 0) 
      {
        $row = $result->fetch_assoc();
        $totalPrice = $row['totalPrice'];

        // Prepare and execute the query to sum the approved payments for the same transactNo
        $sql2 = "SELECT SUM(amount) as totalPayment 
                  FROM payment 
                  WHERE transactNo = ? AND paymentTitle = 'Package Payment' AND paymentStatus = 'Approved'";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("s", $transactionNumber); // Bind the transaction number as a string
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        // Initialize totalPayment variable
        $totalPayment = 0.00;

        // Check if there are any approved payments for the same transactNo
        if ($result2->num_rows > 0) 
        {
          $paymentRow = $result2->fetch_assoc();
          $totalPayment = $paymentRow['totalPayment'];  // Sum of approved payments

          $totalPrice -= $totalPayment;  // Subtract the total payments from the total price
        }

        // Close the second statement
        $stmt2->close();
      }

      // Ensure the value is not negative
      $amountLeft = max($totalPrice, 0.00);  // Avoid negative balance

      // Determine the message
      $packageMessage = $amountLeft > 0 ? "Outstanding Balance" : "Fully Paid";

      // Debugging: Log the amountLeft
      error_log("Amount Left: " . $amountLeft);

      // Return the remaining balance and package message as JSON
      echo json_encode([
          'amountLeft' => $amountLeft,
          'packageMessage' => $packageMessage
      ]);
    }

    else
    {
      // Prepare and execute the query to sum the confirmed request costs for the same transactNo
      $sql3 = "SELECT SUM(requestCost) as totalRequestCost 
                FROM request 
                WHERE transactNo = ? AND requestStatus = 'Confirmed'";

      $stmt3 = $conn->prepare($sql3);
      $stmt3->bind_param("s", $transactionNumber); // Bind the transaction number as a string
      $stmt3->execute();
      $result3 = $stmt3->get_result();

      // Initialize totalRequestCost variable
      $totalRequestCost = 0.00;

      // Check if there are any confirmed requests for the same transactNo
      if ($result3->num_rows > 0) 
      {
        $paymentRow = $result3->fetch_assoc();
        $totalRequestCost = $paymentRow['totalRequestCost'];  // Sum of confirmed request costs

        // Prepare and execute the query to sum the approved payments for the same transactNo
        $sql4 = "SELECT SUM(amount) as totalPayment 
                  FROM payment 
                  WHERE transactNo = ? AND paymentTitle = 'Request Payment' AND paymentStatus = 'Approved'";

        $stmt4 = $conn->prepare($sql4);
        $stmt4->bind_param("s", $transactionNumber); // Bind the transaction number as a string
        $stmt4->execute();
        $result4 = $stmt4->get_result();

        // Initialize totalPayment variable
        $totalPayment = 0.00;

        // Check if there are any approved payments for the same transactNo
        if ($result4->num_rows > 0) 
        {
          $paymentRow = $result4->fetch_assoc();
          $totalPayment = $paymentRow['totalPayment'];  // Sum of approved payments

          $totalRequestCost -= $totalPayment;  // Subtract the total payments from the total price
        }

        // Close the second statement
        $stmt4->close();
      }

      // Check if there are no requests, and return a message instead of a price
      if ($totalRequestCost == 0) 
      {
        // No requests found, return a message
        echo json_encode([
            'amountLeft' => 0,
            'requestMessage' => "No confirmed requests found."
        ]);
      } 
      else
      {
        // Ensure the value is not negative
        $amountLeft = max($totalRequestCost, 0.00);  // Avoid negative balance

        // Determine the message
        $requestMessage = $amountLeft > 0 ? "Outstanding Balance" : "Fully Paid";

        // Debugging: Log the amountLeft
        error_log("Amount Left: " . $amountLeft);

        // Return the remaining balance and request message as JSON
        echo json_encode([
            'amountLeft' => $amountLeft,
            'requestMessage' => $requestMessage
        ]);
      }
    }

    // Close the database connection
    $conn->close();
  }
?>