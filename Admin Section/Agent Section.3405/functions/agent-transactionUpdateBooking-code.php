<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  require "../../conn.php"; // Make sure the path to your database connection file is correct

  if (isset($_POST['updateBooking'])) 
  {
    // Collect the input data from the form
    $transaction_number = $_POST['transaction_number'];
    $fName = $_POST['fName'];
    $lName = $_POST['lName'];
    $mName = $_POST['mName'];
    $suffix = $_POST['suffix'];
    $countryCode = $_POST['countryCode'];
    $contactNo = $_POST['contactNo'];
    $email = $_POST['email'];

    // Start a transaction
    $conn->begin_transaction();

    // Perform the update query
    $sql = "UPDATE booking SET fName = ?, lName = ?, mName = ?, suffix = ?, countryCode = ?, contactNo = ?, email = ? 
            WHERE transactNo = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) 
    {
      // Bind the parameters
      $stmt->bind_param("ssssssss", $fName, $lName, $mName, $suffix, $countryCode, $contactNo, $email, $transaction_number);

      // Execute the statement
      if ($stmt->execute()) 
      {
        // If successful, commit the transaction
        $conn->commit();
        $_SESSION['status'] = "Booking updated successfully.";
        header("Location: ../agent-transactions.php");
        exit(0);
      } 
      else 
      {
        // If an error occurs, rollback the transaction
        $conn->rollback();
        $_SESSION['status'] = "Error updating booking: " . $stmt->error;
        header("Location: ../agent-transactions.php");
        exit(0);
      }

      // Close the statement
      $stmt->close();
    } 
    else 
    {
      // If the statement preparation fails, rollback the transaction and report the error
      $conn->rollback();
      $_SESSION['status'] = "Error preparing statement: " . $conn->error;
      header("Location: ../agent-transactions.php");
      exit(0);
    }

    // Close the connection
    $conn->close();
  }
?>
