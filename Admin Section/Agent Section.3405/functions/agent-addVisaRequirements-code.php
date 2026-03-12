<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../conn.php"; // Database connection

if (isset($_POST['attachVisaRequirements'])) 
{
  $transactNo = $_POST['transaction_number'] ?? $_SESSION['transaction_number'] ?? null;
  $accId = $_POST['accId'];
  $guestIds = $_POST['guestIds'];

  $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
  $maxFileSize = 5 * 1024 * 1024;
  date_default_timezone_set('Asia/Taipei');
  $currentTime = date('Y-m-d H:i:s');

  if (!$transactNo || !$accId) 
  {
    echo "Transaction number or Agent ID is missing.";
    exit;
  }

  // Define document types
  $documentTypes = ['passport', 'permit', 'validId', 'certificate', 'guaranteedLetter'];
  
  function sanitizeFileName($fileName) 
  {
    return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $fileName);
  }

  $conn->begin_transaction(); // Start transaction
  $allFilesUploaded = true;

  foreach ($guestIds as $guestId) 
  {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/SMART-TRAVEL-MANAGEMENT-SYSTEM/Files Uploads/Visa Requirements Uploads" . DIRECTORY_SEPARATOR . $transactNo . DIRECTORY_SEPARATOR . $guestId;
    
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) 
    {
      echo "Failed to create upload directory for guest $guestId.<br>";
      $allFilesUploaded = false;
      break;
    }

    $guestidDate = $guestId . ' _ ' . date('Y-m-d - H-i-s');
    $atLeastOneFileUploaded = false;

    foreach ($documentTypes as $docType) 
    {
      if (!isset($_FILES[$docType]['tmp_name'][$guestId])) 
      {
        continue; // Skip if no files were uploaded for this document type
      }

      foreach ($_FILES[$docType]['tmp_name'][$guestId] as $key => $fileTmpPath) 
      {
        if (empty($fileTmpPath)) 
        {
          continue; // Skip empty file inputs
        }

        $fileName = sanitizeFileName($_FILES[$docType]['name'][$guestId][$key]);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . ucfirst($docType) . '_ ' . $guestidDate . '.' . $fileExtension;
        $fileTypeDetected = mime_content_type($fileTmpPath);
        $fileSize = filesize($fileTmpPath);

        if ($_FILES[$docType]['error'][$guestId][$key] !== UPLOAD_ERR_OK) 
        {
          $_SESSION['status'] = "Error uploading file $fileName.";
          $allFilesUploaded = false;
          break 2;
        }

        if (!in_array($fileTypeDetected, $allowedTypes)) 
        {
          $_SESSION['status'] = "Invalid file type for $docType. Only JPG, PNG, and PDF allowed.";
          $allFilesUploaded = false;
          break 2;
        }

        if ($fileSize > $maxFileSize) 
        {
          $_SESSION['status'] = "File $fileName exceeds 5MB limit.";
          $allFilesUploaded = false;
          break 2;
        }

        if (!move_uploaded_file($fileTmpPath, $filePath)) 
        {
          $_SESSION['status'] = "Error moving file $fileName.";
          $allFilesUploaded = false;
          break 2;
        }

        $atLeastOneFileUploaded = true;

        // Insert into the database
        $query = "INSERT INTO visarequirements (guestId, transactNo, accId, fileType, filePath, dateSubmitted)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
          
        if ($stmt) 
        {
          $stmt->bind_param("isssss", $guestId, $transactNo, $accId, $docType, $filePath, $currentTime);
          
          if (!$stmt->execute()) 
          {
            $_SESSION['status'] = "Error executing query: " . $stmt->error;
            $allFilesUploaded = false;
            break 2;
          }
          $stmt->close();
        } 
        else 
        {
          $_SESSION['status'] = "Error preparing statement: " . $conn->error;
          $allFilesUploaded = false;
          break 2;
        }
      }
    }

    if (!$atLeastOneFileUploaded) 
    {
      $_SESSION['status'] = "At least one file must be uploaded for guest $guestId.";
      $allFilesUploaded = false;
      break;
    }
  }

  if ($allFilesUploaded) 
  {
    $conn->commit();
    $_SESSION['status'] = "Visa requirements uploaded successfully.";
  } 
  else 
  {
    $conn->rollback();
    $_SESSION['status'] = "Upload failed. Transaction rolled back.";
  }

  header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
  exit();
}
?>
