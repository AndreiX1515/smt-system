<?php
session_start();
require "../../../conn.php"; // Database connection

$accountId = $_SESSION['agent_accountId']; // Get account ID from session

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = $_POST['currentPassword'];
    $emailAddress = $_POST['emailAddress'] ?? ''; // Optional email address from POST data

    // Check if current password is provided
    if (empty($currentPassword)) {
        // Respond with an error message if the current password is not provided
        echo json_encode([
            'status' => 'error',
            'message' => 'Current password is required.',
            'currentPassword' => $currentPassword 
        ]); 

    } else {
        // Verify password against the database
        $stmt = $conn->prepare("SELECT password FROM accounts WHERE accountId = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $stmt->bind_result($storedPassword); 
        $stmt->fetch();
        $stmt->close();

        // Check if password matches
        if ($currentPassword !== $storedPassword) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Current password is incorrect.',
                'currentPassword' => $currentPassword
            ]);
        } elseif (empty($emailAddress)) {
            // Handle missing email address
            echo json_encode([
                'status' => 'error',
                'message' => 'No email address associated with this account.',
                'emailAddress' => '',
                'accountId' => $accountId
            ]);

        } else {
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Sending OTP to your email to verify change password...',
                'currentPassword' => $currentPassword,
                'accountId' => $accountId,
                'emailAddress' => $emailAddress
            ]);
        }
    }

    $conn->close(); // Close connection
}
?>
