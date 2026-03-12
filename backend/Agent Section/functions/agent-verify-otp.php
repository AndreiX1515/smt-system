<?php
session_start();
require '../../conn.php';

// Common function to output JSON response
function jsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if OTP is being verified
    if (isset($_POST['changepass-OTP'])) {
        $enteredOtp = $_POST['changepass-OTP'];

        // Validate OTP
        if (!isset($_SESSION['otp'])) {
            jsonResponse(false, 'OTP session not found. Please request a new OTP.');
        }

        if ($enteredOtp !== $_SESSION['otp']) {
            jsonResponse(false, 'Invalid OTP. Please try again.');
        }

        // OTP verified successfully
        jsonResponse(true, 'OTP verified successfully!');
    } else {
        jsonResponse(false, 'No OTP provided.');
    }
}
?>
