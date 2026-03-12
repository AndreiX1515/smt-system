<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['cancelTransact'])) 
  {
    // Get the transaction number from the form
    $transactNo = $_POST['updateTransactNo'];
    $reason = $_POST['reason'];
    $accountId = $_SESSION['agent_accountId'];

    // Set the session variable for the current user in MySQL
    $conn->query("SET @current_user_id = $accountId");

    // Begin transaction
    $conn->begin_transaction();

    // Prepare the SQL query to update the status
    $sql = "UPDATE booking SET status = 'Cancelled',  remarks = ? WHERE transactNo = ?";

    // Use a prepared statement to prevent SQL injection
    $stmt = $conn->prepare($sql);

    if ($stmt) 
    {
      // Bind the transaction number to the statement
      $stmt->bind_param('ss', $reason, $transactNo);

      // Execute the statement
      if ($stmt->execute()) 
      {
        // Commit the transaction
        $conn->commit();
        // Commit the transaction if all inserts succeed
        $_SESSION['status'] = "Transaction Status Updated Successfully";
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      } 
      else 
      {
        // Rollback the transaction on execution failure
        $conn->rollback();
        // Commit the transaction if all inserts succeed
        $_SESSION['status'] = "Failed to update the status for Transaction #$transactNo. Please try again.";
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }

      // Close the prepared statement
      $stmt->close();
    } 
    else 
    {
      // Rollback the transaction on preparation failure
      $conn->rollback();
      // Redirect back to the specific transaction's page
      $_SESSION['status'] = "Failed to update the status for Transaction #$transactNo. Please try again.";
      header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
      exit(0);
    }
  }

?>