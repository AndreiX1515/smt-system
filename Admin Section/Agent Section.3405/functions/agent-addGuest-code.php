<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['addGuestInformation'])) 
  {
    $transactNo = $_POST['transactNo'];
    $fNames = array_map('strtoupper', $_POST['fName']);
    $lNames = array_map('strtoupper', $_POST['lName']);
    $mNames = array_map('strtoupper', $_POST['mName']);
    $suffixes = $_POST['suffix'];
    $birthdates = $_POST['birthdate'];
    $ages = $_POST['age'];
    $sexes = $_POST['sex'];
    $nationalities = $_POST['nationality']; 
    $passportNos = array_map('strtoupper', $_POST['passportNo']);
    $passportExps = $_POST['passportExp'];
    $passportIssuedDates = $_POST['passportIssued'];
    $countryCode1st = $_POST['countryCode']; 
    $contactNo1st = $_POST['contactNo'];
    $countryCode2nd = $_POST['2ndcountryCode'];
    $contactNo2nd = $_POST['2ndcontactNo']; 
    $emails = $_POST['email']; 
    $addressLine1st = $_POST['addressLine']; 
    $addressLine2nd = $_POST['2ndaddressLine']; 
    $cities = $_POST['city']; 
    $states = $_POST['state']; 
    $zipCodes = $_POST['zipCode']; 
    $countries = $_POST['country']; 

    // Start a transaction
    $conn->begin_transaction();

    // Prepare the SQL query with placeholders
    $stmt = $conn->prepare("INSERT INTO `guest` 
      (`transactNo`, `fName`, `lName`, `mName`, `suffix`, `birthdate`, `age`, `sex`, `nationality`, 
      `countryCode`, `contactNo`, `countryCode2`, `contactNo2`, `emailAdd`, `addressLine1`, `addressLine2`, 
      `city`, `state`, `zipCode`, `country`, `passportNo`, `passportIssuedDate`, `passportExp`) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Loop through each entry in the arrays and bind parameters for each iteration
    foreach ($fNames as $index => $fName) 
    {
      $lName = $lNames[$index];
      $mName = $mNames[$index];
      $suffix = $suffixes[$index];
      $birthdate = $birthdates[$index];
      $age = $ages[$index];
      $sex = $sexes[$index];
      $nationality = $nationalities[$index];
      $passportNo = $passportNos[$index];
      $passportIssuedDate = $passportIssuedDates[$index];
      $passportExp = $passportExps[$index];
      $countryCode1 = $countryCode1st[$index];
      $contactNo1 = $contactNo1st[$index];
      $countryCode2 = empty($countryCode2nd[$index]) ? NULL : $countryCode2nd[$index];
      $contactNo2 = empty($contactNo2nd[$index]) ? NULL : $contactNo2nd[$index];
      $email = $emails[$index];
      $addressLine1 = $addressLine1st[$index];
      $addressLine2 = empty($addressLine2nd[$index]) ? NULL : $addressLine2nd[$index];
      $city = $cities[$index];
      $state = $states[$index];
      $zipCode = $zipCodes[$index];
      $country = $countries[$index];

      // Bind parameters using 'ssssssisssssssssssssss', adjusting for the correct data types
      $stmt->bind_param("ssssssissssssssssssssss", 
        $transactNo, $fName, $lName, $mName, $suffix, 
        $birthdate, $age, $sex, $nationality, 
        $countryCode1, $contactNo1, $countryCode2, $contactNo2, 
        $email, $addressLine1, $addressLine2, $city, 
        $state, $zipCode, $country, $passportNo, $passportIssuedDate, $passportExp);

      // Execute the statement
      if (!$stmt->execute()) 
      {
        // Rollback the transaction if any error occurs
        $_SESSION['status'] = "Guest insertion failed: " . $stmt->error;
        $conn->rollback();  // Rollback transaction
        header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
        exit(0);
      }
    }

    // If no errors, commit the transaction
    $conn->commit();

    // Commit the transaction if all inserts succeed
    $_SESSION['status'] = "Guest Information Uploaded Successfully";
    header("Location: ../agent-showGuest.php?id=" . htmlspecialchars($transactNo));
    exit(0);
  }
?>
