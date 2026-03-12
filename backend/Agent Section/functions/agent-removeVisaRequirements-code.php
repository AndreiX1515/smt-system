<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  require "../../conn.php"; // Database connection

  if (isset($_POST['remove'])) 
  {
    $fileId = $_POST['fileId'];
    $transactNo = $_POST['transactNo'];

    // Start transaction
    $conn->begin_transaction();

    // Retrieve file path from the database
    $sql1 = "SELECT filePath FROM visarequirements WHERE requirementId = ?";
    $stmt1 = $conn->prepare($sql1);

    if ($stmt1) 
    {
      $stmt1->bind_param("i", $fileId);
      $stmt1->execute();
      $result = $stmt1->get_result();
      $stmt1->close();

      if ($result->num_rows > 0) 
      {
        $row = $result->fetch_assoc();
        $filePath = $row['filePath'];

        // Proceed with deletion if file path exists
        $sql2 = "DELETE FROM visarequirements WHERE requirementId = ?";
        $stmt2 = $conn->prepare($sql2);

        if ($stmt2) 
        {
          $stmt2->bind_param("i", $fileId);

          if ($stmt2->execute()) 
          {
            // Check if the file exists and delete it
            if (file_exists($filePath) && is_file($filePath)) 
            {
              unlink($filePath);
            }

            // Commit transaction
            $conn->commit();
            $_SESSION['status'] = "File successfully removed.";
          } 
          else 
          {
            $conn->rollback();
            $_SESSION['status'] = "Failed to remove file from database.";
          }

          $stmt2->close();
        } 
        else 
        {
          $conn->rollback();
          $_SESSION['status'] = "Error preparing delete statement.";
        }
      } 
      else 
      {
        $conn->rollback();
        $_SESSION['status'] = "File not found in database.";
      }
    } 
    else 
    {
      $conn->rollback();
      $_SESSION['status'] = "Error preparing select statement.";
    }

    // Redirect back to the previous page
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo)); // Redirect on error
    exit();
  }
?>