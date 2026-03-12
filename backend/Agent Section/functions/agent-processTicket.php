<?php
header("Content-Type: application/json");
require "../../conn.php"; // Include database connection
session_start(); // Start session to access agent data

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve POST data
    $agentId = $_SESSION['agentId'] ?? "";
    $agentCode = $_SESSION['agentCode'] ?? "";
    $concernType = $_POST["concernType"] ?? "";
    $numUsers = isset($_POST["numUsers"]) && $concernType === "Request for Additional User" ? intval($_POST["numUsers"]) : NULL;
    $ticketDescription = $_POST["ticketDescription"] ?? "";
    $ticketPriority = $_POST["ticketPriority"] ?? "medium"; // Default priority

    // Validate required fields
    if (empty($agentId) || empty($agentCode) || empty($concernType) || empty($ticketDescription)) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        exit;
    }

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
        exit;
    }

    // Generate a unique ticketId
    do {
        $ticketId = date("Ymd") . "-" . rand(1000, 9999);
        $checkQuery = "SELECT COUNT(*) AS count FROM tickets WHERE ticketId = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $ticketId);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();
    } while ($count > 0); // Keep generating a new ticketId until it is unique

    // Prepare SQL statement
    $sql = "INSERT INTO tickets (ticketId, agentId, agentCode, concernType, numUsers, description, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    // Bind parameters
    $stmt->bind_param("ssssiss", $ticketId, $agentId, $agentCode, $concernType, $numUsers, $ticketDescription, $ticketPriority);

    // Execute query
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Ticket submitted successfully", "ticketId" => $ticketId]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}
?>
