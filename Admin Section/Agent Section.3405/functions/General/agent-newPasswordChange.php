<?php
require_once "../../../conn.php";
session_start();

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit();
}

// Validate input
$newPassword = $_POST['newPassword'] ?? '';
$accountIdverify = $_POST['accountId'] ?? '';

if (empty($newPassword)) {
    echo json_encode(["status" => "error", "message" => "Please enter a new password."]);
    exit();
}

// Check if accountIdverify is empty
if (empty($accountIdverify)) {
    echo json_encode(["status" => "error", "message" => "Account ID is required."]);
    exit();
}

// Update the password in the database (without hashing)
$updateQuery = "UPDATE accounts SET password = ? WHERE accountid = ?";
$updateStmt = $conn->prepare($updateQuery);

// Check if the statement was prepared successfully
if ($updateStmt === false) {
    echo json_encode(["status" => "error", "message" => "Failed to prepare SQL query: " . $conn->error]);
    exit();
}

$updateStmt->bind_param("si", $newPassword, $accountIdverify);

// Execute the query and check for errors
if ($updateStmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Password updated successfully."
    ]);
} else {
    // If there's an error with the execution, return the error message
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update password. Error: " . $updateStmt->error
    ]);
}

// Close the prepared statement
$updateStmt->close();
?>
