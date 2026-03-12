<?php
require "../../conn.php"; // Include the DB connection
session_start();

// Get data from the POST request
$companyId = $_POST['companyId'];
$month = date('m', strtotime($_POST['month']));
$year = $_POST['year'];
$currentDate = $_POST['currentDate']; // Current date sent from the client

// Begin the transaction
$conn->begin_transaction();

// Fetch the last SOA number for the current year
$sql5 = "SELECT MAX(id) AS lastSoAId FROM soafit";
$result5 = $conn->query($sql5);

if (!$result5) 
{
	// Handle the error if fetching the last SOA number fails
	$conn->rollback();
	echo json_encode(['error' => "Error fetching last SOA number."]);
	exit(0);
}

// Get the next SOA number
$row = $result5->fetch_assoc();
$newSoANo = ($row && $row['lastSoAId'] !== null) ? $row['lastSoAId'] + 1 : 1;
$formattedCounter = str_pad($newSoANo, 5, '0', STR_PAD_LEFT);
$soaNo = 'SMT-' . $year . '-' . $formattedCounter;

// Set the current date and time for the `dateGenerated` field
$dateGenerated = date('Y-m-d H:i:s'); // Format: YYYY-MM-DD HH:MM:SS

// Prepare the SQL for insertion
$sql6 = "INSERT INTO soafit (soaNo, branchId, month, dateGenerated, status)
         VALUES (?, ?, ?, ?, ?)";
$stmt6 = $conn->prepare($sql6);

if (!$stmt6) 
{
	// Handle the error if statement preparation fails
	$conn->rollback();
	echo json_encode(['error' => "Error preparing SQL statement."]);
	exit(0);
}

// Bind parameters to the prepared statement
$status = 'Partially Paid';
$stmt6->bind_param('siiss', $soaNo, $companyId, $month, $dateGenerated, $status);

// Execute the statement
if ($stmt6->execute()) 
{
	// Commit the transaction
	$conn->commit();

	// Send the SOA number back to the client
	echo json_encode(['soanum' => $soaNo]);

} 
else 
{
	// Rollback the transaction if the execution fails
	$conn->rollback();
	echo json_encode(['error' => "Error inserting SOA number."]);
}

// Close the statement and the connection
$stmt6->close();
$conn->close();
?>
