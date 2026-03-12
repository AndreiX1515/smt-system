<?php
// Example script: agent-changePassword.php

session_start(); // Start the session to access session variables

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp']; // Get the OTP entered by the user

    // Fetch the correct OTP from the session
    $correctOtp = $_SESSION['otp']; // The OTP that was stored in the session when sent

    // Check if the OTP matches
    if ($otp === $correctOtp) {
        // If OTP matches, respond with success
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP verified successfully. Password has been changed.',
        ]);

    } else {
        // If OTP does not match, respond with an error
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid OTP. Please try again.'
        ]);
    }
}
?>
