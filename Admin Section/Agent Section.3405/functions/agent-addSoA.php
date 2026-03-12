<?php
require "../../conn.php";
session_start();

// Validate and sanitize inputs
$monthInput = isset($_POST['month']) ? $_POST['month'] : null;
$year = isset($_POST['year']) ? $_POST['year'] : null;
$flightId = isset($_POST['flightId']) ? intval($_POST['flightId']) : null;
$currentDate = isset($_POST['currentDate']) ? $_POST['currentDate'] : null;

// echo $

// // Basic validation
// if (!$monthInput || !$year || !$flightId) {
//     echo json_encode(['error' => 'Missing required data.']);
//     exit;
// }

$month = date('m', strtotime($monthInput));
$dateGenerated = date('Y-m-d H:i:s');

$conn->begin_transaction();

// Fetch last SOA number
$sql5 = "SELECT MAX(id) AS lastSoAId FROM soa";
$result5 = $conn->query($sql5);

if (!$result5) {
    $conn->rollback();
    echo json_encode(['error' => "Error fetching last SOA number."]);
    exit;
}

$row = $result5->fetch_assoc();
$newSoANo = ($row && $row['lastSoAId'] !== null) ? $row['lastSoAId'] + 1 : 1;
$formattedCounter = str_pad($newSoANo, 5, '0', STR_PAD_LEFT);
$soaNo = 'SMT-' . $year . '-' . $formattedCounter;

// Insert SOA with flightId
$sql6 = "INSERT INTO soa (soaNo, branchId, month, flightId, dateGenerated, status)
         VALUES (?, ?, ?, ?, ?, ?)";
$stmt6 = $conn->prepare($sql6);

if (!$stmt6) {
    $conn->rollback();
    echo json_encode(['error' => "Error preparing SQL statement."]);
    exit;
}

$status = 'Partially Paid';
$stmt6->bind_param('siisss', $soaNo, $accountId, $month, $flightId, $dateGenerated, $status);

if ($stmt6->execute()) {
    $conn->commit();
    echo json_encode(['soanum' => $soaNo]);
} else {
    $conn->rollback();
    echo json_encode(['error' => "Error inserting SOA number."]);
}

$stmt6->close();
$conn->close();
?>
