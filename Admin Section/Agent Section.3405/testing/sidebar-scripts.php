<?php
require "../../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$accountId = $_SESSION['agent_accountId'] ?? '';
$agentId = $_SESSION['agentId'] ?? '';
$agentCode = $_SESSION['agentCode'] ?? '';
$agentRole = $_SESSION['agentRole'] ?? '';
$agentType = $_SESSION['agentType'] ?? '';
$fName =  $_SESSION['agent_fName'] ?? '';
$lName = $_SESSION['agent_lName'] ?? '';
$mName = $_SESSION['agent_mName'] ?? '';
$branchId = $_SESSION['agent_branchId'] ?? '';
$email = $_SESSION['agent_email'] ?? '';
$password = $_SESSION['agent_password'] ?? '';
$emailAdress = $_SESSION['agent_emailAddress'] ?? '';

// Fetch Branch Name
$sql1 = "SELECT branchName FROM branch WHERE branchId = ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("i", $branchId);
$stmt1->execute();
$result1 = $stmt1->get_result();

if ($result1->num_rows > 0) {
  $row = $result1->fetch_assoc();
  $branchName = $row['branchName'];
} else {
  $branchName = "No Branch";
}

$stmt1->close();

// Fetch Agent Info (to get companyId)
$sql2 = "SELECT companyId FROM agent WHERE accountId = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $accountId);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows > 0) {
  $row2 = $result2->fetch_assoc();
  $companyId = $row2['companyId'];

  // Fetch Company Name if companyId is NOT NULL
  if (!is_null($companyId)) {
    $sql3 = "SELECT companyName FROM company WHERE companyId = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $companyId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();

    if ($result3->num_rows > 0) {
      $row3 = $result3->fetch_assoc();
      $companyName = $row3['companyName'];
    } else {
      $companyName = "Unknown Company"; // Fallback if no company record found
    }
    $stmt3->close();
  } else {
    $companyName = null; // No company assigned
  } 
} else {
  // Only set "No Branch" if branchName is still empty
  if (empty($branchName)) {
    $branchName = "No Branch";
  }
}
$stmt2->close();

// Format the full name in Last Name, First Name, Middle Name format
$fullName = htmlspecialchars(trim(
  $lName .                         // Always include last name
    ($fName ? ', ' . $fName : '') .  // Add first name with a comma if it's not empty
    ($mName ? ' ' . substr($mName, 0, 1) . '.' : '') // Add middle name initial if it's not empty
));

// Remove any trailing commas or extra spaces
$fullName = rtrim($fullName, ', '); // Clean up if only the last name is present
?>

<?php
date_default_timezone_set('Asia/Taipei');
$current_date = date('D, F d, Y');
?>