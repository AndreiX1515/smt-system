<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../conn.php"; // Database connection

if (isset($_POST['upload'])) 
{
  $transactNo = $_POST['transactNo'] ?? null;
  $guestId = $_POST['guestId'] ?? null;
  $accountId = $_POST['accountId'] ?? null;
  $fileType = $_POST['fileType'] ?? null;
  $file = $_FILES['file'] ?? null;

  if (!$transactNo || !$guestId || !$fileType || !$file) 
  {
    $_SESSION['status'] = "Missing required data.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }

  // Allowed file types
  $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
  $maxFileSize = 5 * 1024 * 1024;
  $currentDateTime = date('Y-m-d - H-i-s');

  function sanitizeFileName($fileName) 
  {
    return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $fileName);
  }

  // Define upload directory
  $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/SMART-TRAVEL-MANAGEMENT-SYSTEM/Files Uploads/Visa Requirements Uploads" . DIRECTORY_SEPARATOR . $transactNo . DIRECTORY_SEPARATOR . $guestId;
  
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) 
  {
    $_SESSION['status'] = "Failed to create upload directory.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }

  // File processing
  $fileTmpPath = $file['tmp_name'];
  $fileName = sanitizeFileName($file['name']);
  $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  $newFilePath = $uploadDir . DIRECTORY_SEPARATOR . ucfirst($fileType) . '_ ' . $guestId . ' _ ' . $currentDateTime . '.' . $fileExtension;
  $fileTypeDetected = mime_content_type($fileTmpPath);
  $fileSize = filesize($fileTmpPath);

  // File validation
  if ($file['error'] !== UPLOAD_ERR_OK) 
  {
    $_SESSION['status'] = "Error uploading file.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }
  
  if (!in_array($fileTypeDetected, $allowedTypes)) 
  {
    $_SESSION['status'] = "Invalid file type. Only JPG, PNG, and PDF allowed.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }

  if ($fileSize > $maxFileSize) 
  {
    $_SESSION['status'] = "File exceeds 5MB limit.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }

  if (!move_uploaded_file($fileTmpPath, $newFilePath)) 
  {
    $_SESSION['status'] = "Error moving file.";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit();
  }

  // Start transaction
  $conn->begin_transaction();

  // Insert into the database
  $query = "INSERT INTO visarequirements (guestId, transactNo, accId, fileType, filePath, dateSubmitted)
  VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($query);

  if ($stmt) 
  {
    $stmt->bind_param("isisss", $guestId, $transactNo, $accountId, $fileType, $newFilePath, $currentDateTime);

    if (!$stmt->execute()) 
    {
      $_SESSION['status'] = "Error executing query: " . $stmt->error;
      $conn->rollback(); // Rollback transaction on failure
      header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo)); // Redirect on error
      exit();
    }
  } 
  else 
  {
    $_SESSION['status'] = "Error preparing statement: " . $conn->error;
    $conn->rollback(); // Rollback transaction on failure
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo)); // Redirect on error
    exit();
  }

  // Commit transaction if everything is successful
  $conn->commit();
  $stmt->close();

  // Redirect to success page
  $_SESSION['status'] = "Visa requirements uploaded successfully.";
  header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
  exit();

  // Check if a record exists
  // $checkQuery = "SELECT * FROM visarequirements WHERE guestId = ? AND transactNo = ?";
  // $stmt = $conn->prepare($checkQuery);
  // $stmt->bind_param("is", $guestId, $transactNo);
  // $stmt->execute();
  // $result = $stmt->get_result();
  // $stmt->close();

  // if ($result->num_rows > 0) 
  // {
  //   // Update the existing record
  //   $updateQuery = "UPDATE visarequirements SET $fileType = ?, dateSubmitted = ? WHERE guestId = ? AND transactNo = ?";
  //   $stmt = $conn->prepare($updateQuery);
  //   $stmt->bind_param("ssis", $newFilePath, $currentDateTime, $guestId, $transactNo);
  // } 
  // else 
  // {
  //   // Insert a new record if none exists
  //   $insertQuery = "INSERT INTO visarequirements (guestId, transactNo, accId, $fileType, dateSubmitted)
  //                   VALUES (?, ?, (SELECT accId FROM agent WHERE agentId = (SELECT accId FROM visarequirements WHERE transactNo = ? LIMIT 1)), ?, ?)";
  //   $stmt = $conn->prepare($insertQuery);
  //   $stmt->bind_param("issss", $guestId, $transactNo, $transactNo, $newFilePath, $currentDateTime);
  // }

  // if ($stmt->execute()) 
  // {
  //   $conn->commit();
  //   $_SESSION['status'] = ucfirst($fileType) . " updated successfully.";
  // } 
  // else 
  // {
  //   $conn->rollback();
  //   $_SESSION['status'] = "Database update failed: " . $stmt->error;
  // }

  
}
?>
