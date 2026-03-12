<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['addFlightDetails'])) 
  {
    // Retrieve POST data
    $transactNo = $_POST['transaction_number'];
    // $packageId = $_POST['packageId'];
    $flightName = $_POST['flightName'];
    $flightCode = $_POST['flightCode'];
    $flightDepartureDate = $_POST['flightDepartureDate'];
    $flightDepartureTime = $_POST['flightDepartureTime'];
    $returnFlightName = $_POST['returnFlightName'];
    $returnFlightCode = $_POST['returnFlightCode'];
    $returnDepartureDate = $_POST['returnDepartureDate'];
    $returnDepartureTime = $_POST['returnDepartureTime'];

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Begin transaction
    $conn->begin_transaction();

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE clientflight SET flightName = ?, flightCode = ?, flightDepartureDate = ?, flightDepartureTime = ?, 
                            returnFlightName = ?, returnFlightCode = ?, returnDepartureDate = ?, returnDepartureTime = ? 
                            WHERE transactNo = ?");

    // Bind parameters
    $stmt->bind_param("sssssssss", $flightName, $flightCode, $flightDepartureDate, $flightDepartureTime, 
                                      $returnFlightName, $returnFlightCode, $returnDepartureDate, $returnDepartureTime, $transactNo);

    // Execute the statement
    if ($stmt->execute()) {
        // Commit transaction if successful
        $conn->commit();
        
        // Set session message for success
        $_SESSION['status'] = "Flight details updated successfully.";
    } else {
        // Rollback transaction if there's an error
        $conn->rollback();

        // Set session message for error
        $_SESSION['status'] = "Error updating flight details: " . $conn->error;
    }

    // Close the statement
    $stmt->close();

    // Close the connection
    $conn->close();

    // Redirect with status message
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit(0);
}


?>