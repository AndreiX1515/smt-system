<?php
session_start(); // Start the session

if (isset($_POST['transaction_number'])) {
    // Get the transaction number from POST request
    $transactionNumber = $_POST['transaction_number'];
    
    // Store the transaction number in a session variable
    $_SESSION['transaction_number'] = $transactionNumber;

    // Optionally return a success message
    echo json_encode(['status' => 'success', 'transaction_number' => $transactionNumber]);
} else {
    // Return an error message if transaction number is not set
    echo json_encode(['status' => 'error', 'message' => 'Transaction number not set.']);
}
?>
