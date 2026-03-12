<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../conn.php"; // Move up to the parent directory

if (isset($_POST['payment'])) 
{
  $transactNo = $_POST['transactionNumber'];
  $accountId = $_POST['accountId'];
  $paymentType = $_POST['paymentType'];
  $amount = $_POST['amount'];

  // Set the timezone (replace 'Asia/Taipei' with your preferred timezone if needed)
  date_default_timezone_set('Asia/Taipei');
  $paymentDate = (new DateTime())->format('Y-m-d H:i:s'); // Current date and time

  // Set the session variable for the current user in MySQL
  $conn->query("SET @current_user_id = $accountId");

  if (isset($_FILES['proofs']) && count($_FILES['proofs']['name']) > 0) 
  {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/SMART-TRAVEL-MANAGEMENT-SYSTEM/Files Uploads/FIT Payment Uploads" . DIRECTORY_SEPARATOR . $transactNo . DIRECTORY_SEPARATOR;
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $maxFileSize = 4 * 1024 * 1024; // 4MB per file
    $uploadedFiles = []; // Array to store file paths

    if (!is_dir($uploadDir)) 
    {
      mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
    }

    foreach ($_FILES['proofs']['name'] as $key => $fileName) 
    {
      $fileTmpPath = $_FILES['proofs']['tmp_name'][$key];
      $fileSize = $_FILES['proofs']['size'][$key];
      $fileType = $_FILES['proofs']['type'][$key];
      $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

      if (in_array($fileExtension, $allowedExtensions) && $fileSize <= $maxFileSize && $_FILES['proofs']['error'][$key] === UPLOAD_ERR_OK) 
      {
        // Generate a unique name for the file to avoid collisions
        $newFileName = $transactNo . '-' . date('m-d-Y_H-i') . '-' . uniqid() . '.' . $fileExtension;

        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) 
        {
          // Store only the relative file path (directory + filename) in the array
          $uploadedFiles[] = $uploadDir . $newFileName;
        } 
        else 
        {
          $_SESSION['status'] = "Failed to upload file: $fileName";
          header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
          exit(0);
        }
      } 
      else 
      {
        $_SESSION['status'] = "File $fileName is invalid or exceeds size limit of 4MB.";
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }
    }

    if (!empty($uploadedFiles)) 
    {
      $conn->begin_transaction();

      // Insert payment information into the payment table, including file paths
      $sql = "INSERT INTO fitpayment (transactNo, accountId, paymentType, amount, filePath, paymentDate, paymentStatus) 
              VALUES (?, ?, ?, ?, ?, ?, 'Submitted')";
      $stmt = $conn->prepare($sql);

      if (!$stmt) 
      {
        $_SESSION['status'] = "Booking SQL preparation failed: " . $conn->error;
        $conn->rollback();
        header("Location: ../agent-showFITBooking.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }

      // Loop through uploaded files and insert each file path into the database
      foreach ($uploadedFiles as $filePath) 
      {
        // Bind parameters for each file upload
        $stmt->bind_param('sisdss', $transactNo, $accountId, $paymentType, $amount, $filePath, $paymentDate);

        if (!$stmt->execute()) 
        {
          $_SESSION['status'] = "Database error on payment insert: " . $stmt->error;
          $conn->rollback();
          header("Location: ../agent-showFITBooking.php?id=" . htmlspecialchars($transactNo));
          exit(0);
        }
      }

      $conn->commit();
      $_SESSION['status'] = "Payment and proof files uploaded successfully!";
      header("Location: ../agent-showFITBooking.php?id=" . htmlspecialchars($transactNo));
      exit(0);
    } 
    else 
    {
      $_SESSION['status'] = "No valid files uploaded.";
      header("Location: ../agent-showFITBooking.php?id=" . htmlspecialchars($transactNo));
      exit(0);
    }
  } 
  else 
  {
    $_SESSION['status'] = "Proof of payment files are required.";
    header("Location: ../agent-showFITBooking.php?id=" . htmlspecialchars($transactNo));
    exit(0);
  }
}

echo "MIME Type: " . $mimeType . "<br>";
?>
