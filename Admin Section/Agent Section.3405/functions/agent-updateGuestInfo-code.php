<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['updateGuestInfo'])) 
  {
    $transactNo = $_POST['transactNo'];
    $guestId = $_POST['guestId'];
    $fName = $_POST['fName'];
    $lName = $_POST['lName'];
    $mName = $_POST['mName'];
    $suffixe = $_POST['suffix'];
    $birthdate = $_POST['birthdate'];
    $age = $_POST['age'];
    $sexe = $_POST['sex'];
    $nationality = $_POST['nationality'];
    $passportNo = $_POST['passportNo'];
    $passportIssuedDate = $_POST['passportIssued'];
    $passportExp = $_POST['passportExp'];
    $countryCode1st = $_POST['countryCode'];
    $contactNo1st = $_POST['contactNo'];
    $countryCode2nd = $_POST['2ndcountryCode'];
    $contactNo2nd = $_POST['2ndcontactNo'] ?: NULL; // Set NULL if empty
    $email = $_POST['email'];
    $addressLine1st = $_POST['addressLine'];
    $addressLine2nd = $_POST['2ndaddressLine'] ?: NULL; // Set NULL if empty
    $city = $_POST['city'];
    $state = $_POST['state'];
    $zipCode = $_POST['zipCode'];
    $country = $_POST['country'];

    // if ($contactNo2nd === "")
    // {
    //   $contactNo2nd = null;
    // }

    // if ($addressLine2nd === "")
    // {
    //   $addressLine2nd = null;
    // }

    // Start a transaction
    $conn->begin_transaction();

    // Prepare the SQL query with placeholders for the update
    $stmt = $conn->prepare("UPDATE `guest` SET 
        `transactNo` = ?, `fName` = ?, `lName` = ?, `mName` = ?, `suffix` = ?, 
        `birthdate` = ?, `age` = ?, `sex` = ?, `nationality` = ?, `passportNo` = ?, 
        `passportIssuedDate` = ?, `passportExp` = ?, `countryCode` = ?, `contactNo` = ?, `countryCode2` = ?, 
        `contactNo2` = ?, `emailAdd` = ?, `addressLine1` = ?, `addressLine2` = ?, 
        `city` = ?, `state` = ?, `zipCode` = ?, `country` = ? 
        WHERE `guestId` = ?");

    // Bind parameters using 'ssssssissssssssssssss', adjusting for the correct data types
    $stmt->bind_param("ssssssisssssssssssssssss", 
        $transactNo, $fName, $lName, $mName, $suffixe, 
        $birthdate, $age, $sexe, $nationality, $passportNo, $passportIssuedDate,
        $passportExp, $countryCode1st, $contactNo1st, $countryCode2nd, 
        $contactNo2nd, $email, $addressLine1st, $addressLine2nd, $city, 
        $state, $zipCode, $country, $guestId);

    // Execute the statement
    if (!$stmt->execute()) 
    {
      // Rollback the transaction if any error occurs
      $_SESSION['status'] = "Guest update failed: " . $stmt->error;
      $conn->rollback();  // Rollback transaction
      header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
      exit(0);
    }

    // If no errors, commit the transaction
    $conn->commit();

    // Success message and redirect
    $_SESSION['status'] = "Guest information updated successfully";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit(0);
  }
?>
