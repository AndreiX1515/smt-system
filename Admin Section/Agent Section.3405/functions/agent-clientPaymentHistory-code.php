<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require "../../conn.php"; // Move up to the parent directory

header('Content-Type: application/json'); // Set JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $paymentId = $_POST['paymentId'] ?? '';
    $paymentStatus = $_POST['paymentStatus'] ?? '';
    $paymentRemarks = $_POST['paymentRemarks'] ?? '';
    $accountId = $_POST['accId'] ?? '';

    // Validate required fields
    if (empty($paymentId) || empty($paymentStatus) || empty($accountId)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    // Set remarks to NULL if empty
    $paymentRemarks = empty($paymentRemarks) ? NULL : $paymentRemarks;

    // Set the session variable for the current user in MySQL
    $conn->query("SET @current_user_id = $accountId");

    // Start a transaction
    $conn->begin_transaction();

    // Prepare the SQL statement for updating the request status
    $sql1 = "UPDATE paymentc SET paymentStatus = ?, paymentRemarks = ?, performedBy = ? WHERE paymentId = ?";
    $stmt1 = $conn->prepare($sql1);

    if (!$stmt1) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "SQL preparation failed: " . $conn->error]);
        exit;
    }

    // Bind parameters and execute the update
    $stmt1->bind_param('ssii', $paymentStatus, $paymentRemarks, $accountId, $paymentId);
    
    if (!$stmt1->execute()) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt1->error]);
        $stmt1->close();
        exit;
    }

    // Close the first statement
    $stmt1->close();

    // Commit the transaction
    $conn->commit();

    // Determine a user-friendly label based on paymentStatus
    $statusLabel = ($paymentStatus === "Approved") ? "Payment Successfully Approved" : "Payment Rejected";

    // Return success response with additional fetch value
    echo json_encode([
        "status" => "success",
        "message" => "$paymentId - status successfully updated to: $paymentStatus",
        "paymentStatus" => $paymentStatus, // Raw status
        "statusLabel" => $statusLabel      // Friendly label
    ]);

    exit;
}

// If accessed without POST request
echo json_encode(["status" => "error", "message" => "Invalid request."]);
exit;
?>
