<?php
  session_start();
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['bookNow'])) 
  {
    $accountId = $_SESSION['accountId'];
    $agentId = $_SESSION['agentId'];
    $agentCode = $_SESSION['agentCode'];
    $packageName = $_POST['packageName'];
    $nights = $_POST['nights'];
    $hotels = $_POST['hotels'];
    $rooms = $_POST['rooms'];
    $room = $_POST['room'];
    $dayPicker = $_POST['dayPicker'];
    $returnDate = $_POST['returnDate'];
    $pax = $_POST['pax'];
    $totalCostUSD = $_POST['totalCostUSD'];
    $totalCostPHP = $_POST['totalCostPHP'];

    $fName = $_POST['fName'];  
    $mName = $_POST['mName'];  
    $lName = $_POST['lName'];  
    $suffix = $_POST['suffix'];
    $countryCode = $_POST['countryCode']; 
    $contactNo = $_POST['contactNo'];
    $email = $_POST['email'];

    // Get the last bookingId and increment it for the new transaction
    $result = $conn->query("SELECT MAX(bookingId) AS lastBookingId FROM fit");
    if (!$result) 
    {
      $_SESSION['status'] = "Error fetching last booking ID: " . $conn->error;
      header("Location: ../agent-addBooking.php");
      exit(0);
    }

    $row = $result->fetch_assoc();
    $newBookingId = ($row && $row['lastBookingId'] !== null) ? $row['lastBookingId'] + 1 : 1;
    $formattedCounter = str_pad($newBookingId, 6, '0', STR_PAD_LEFT);
    $transactNo = $agentCode . '-' . $formattedCounter;

    // Set the session variable for the current user in MySQL
    $conn->query("SET @current_user_id = $accountId");

    // Start a transaction
    $conn->begin_transaction();

    // Prepare the SQL statement for insertion into the booking table
    $sql1 = "INSERT INTO fit (transactionNo, accountId, agentId, agentCode, packageId, nights, hotelId, roomId, rooms, startDate, 
    returnDate, pax, phpPrice, usdPrice, fName, mName, lName, suffix, countryCode, contactNo, email, bookingDate, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";
    $stmt1 = $conn->prepare($sql1);

    if (!$stmt1) 
    {
      $_SESSION['status'] = "Booking SQL preparation failed: " . $conn->error;
      $conn->rollback();  // Rollback transaction
      header("Location: ../agent-addbooking.php");
      exit(0);
    }

    // Bind and execute the booking insertion
    $stmt1->bind_param('sissiiiiissiddsssssss', $transactNo, $accountId, $agentId, $agentCode, $packageName, $nights, $hotels, $room, $rooms, 
    $dayPicker, $returnDate, $pax, $totalCostPHP, $totalCostUSD, $fName, $mName, $lName, $suffix, $countryCode, $contactNo, $email);
    
    if (!$stmt1->execute()) 
    {
      $_SESSION['status'] = "Database error on booking insert: " . $stmt1->error;
      $conn->rollback();  // Rollback the transaction if there is an error
      header("Location: ../agent-FIT.php");
      exit(0);
    }

    // If no errors, commit the transaction
    $conn->commit();

    // Optionally redirect or provide a success message
    $_SESSION['status'] = "Booking successful!";
    header("Location: ../agent-bookingFITPayment.php?id=" . $transactNo);
    exit(0);
  }
?>
