<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['request'])) 
  {
    $transactNo = $_POST['transaction_number'];
    $accountId = $_POST['accountId'];
    $concern = $_POST['concern'];
    $requestDetails = $_POST['requestDetails'];
    $pax = $_POST['pax'];
    $details = $_POST['details'];
    $customDescription = $_POST['customDescription'];
    $customAmount = $_POST['customAmount'];
    $headcountCustomAmount = $_POST['headcountCustomAmount'];
    // $infantDescription = $_POST['infantDescription'];
    // $infantDescription = $_POST['infantDescription'];
    $amount = $_POST['totalPrice'];

    // Set the session variable for the current user in MySQL
    $conn->query("SET @current_user_id = $accountId");

    // Start the transaction
    $conn->begin_transaction();

    if ($concern == "Others") 
    {
      // Prepare the SQL statement
      $stmt = $conn->prepare("INSERT INTO request (transactNo, accountId, customRequest, customAmount, pax, details, requestCost, 
            concernId, concernDetailsId, requestDate, requestStatus) VALUES(?, ?, ?, ?, ?, ?, ?, Null, Null, Now(), 'Submitted')");
      $stmt->bind_param("sisiisd", $transactNo, $accountId, $customDescription, $customAmount, $pax, $details, $amount);

      // Execute the statement
      if ($stmt->execute()) 
      {
        // Commit the transaction if everything is successful
        $conn->commit();
        $_SESSION['status'] = "Request submitted successfully!";
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      } 
      else 
      {
        // Rollback the transaction if there's an error
        $_SESSION['status'] = "Database error on request insert: " . $stmt->error;
        $conn->rollback();  // Rollback the transaction if there is an error
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }

  
    }

    // else if ($concern == '3')
    // {
    //   // Prepare the SQL statement
    //   $stmt = $conn->prepare("INSERT INTO request (transactNo, accountId, concernId, concernDetailsId, pax, details, requestCost, 
    //   customRequest, customAmount, requestDate, requestStatus) VALUES(?, ?, ?, Null, ?, ?, ?, Null, Null, Now(), 'Submitted')");
    //   $stmt->bind_param("siiiisd", $transactNo, $accountId, $concern, $requestDetails, $pax, $details, $amount);

    //   // Execute the statement
    //   if ($stmt->execute()) 
    //   {
    //     // Commit the transaction if everything is successful
    //     $conn->commit();
    //     $_SESSION['status'] = "Request submitted successfully!";
    //     header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    //     exit(0);
    //   } 
    //   else 
    //   {
    //     // Rollback the transaction if there's an error
    //     $_SESSION['status'] = "Database error on request insert: " . $stmt->error;
    //     $conn->rollback();  // Rollback the transaction if there is an error
    //     header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    //     exit(0);
    //   }

    //   // Close the statement
    //   $stmt->close();

    //   // Close the connection
    //   $conn->close();
    // }

    else 
    {
      // Prepare the SQL statement
      $stmt = $conn->prepare("INSERT INTO request (transactNo, accountId, concernId, concernDetailsId, pax, details, requestCost, 
      customRequest, customAmount, requestDate, requestStatus) VALUES(?, ?, ?, ?, ?, ?, ?, Null, Null, Now(), 'Submitted')");
      $stmt->bind_param("siiiisd", $transactNo, $accountId, $concern, $requestDetails, $pax, $details, $amount);

      // Execute the statement
      if ($stmt->execute()) 
      {
        // Commit the transaction if everything is successful
        $conn->commit();
        $_SESSION['status'] = "Request submitted successfully!";
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      } 
      else 
      {
        // Rollback the transaction if there's an error
        $_SESSION['status'] = "Database error on request insert: " . $stmt->error;
        $conn->rollback();  // Rollback the transaction if there is an error
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }


    }

  }
?>
