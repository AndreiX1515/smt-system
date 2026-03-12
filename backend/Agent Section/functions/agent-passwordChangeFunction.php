<?php
session_start();
include_once('../../conn.php'); // Include your database connection script

// Check if the user is logged in or session data is available
if (!isset($_SESSION['accountId'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

// Check if newPassword is set in POST data
if (isset($_POST['newPassword'])) {  // No underscore here
 $newPassword = $_POST['newPassword'];

    // // Hash the new password before saving it
    // $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Use accountid passed from AJAX to identify the user
    $accountId = $_POST['accountid'];

    // Update the password in the database
    $query = "UPDATE accounts SET password = ? WHERE accountId = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("si", $newPassword , $accountId);

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Password changed successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to change password.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database query failed.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'New password is required.']);
}

// Close the database connection
$conn->close();
?>
