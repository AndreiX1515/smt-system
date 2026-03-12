<?php
require "../../conn.php"; // Database connection
session_start(); // Start session

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['roomAssignments'])) 
{
  $roomAssignments = json_decode($_POST['roomAssignments'], true);
  $luggageAssignments = isset($_POST['luggageAssignments']) ? json_decode($_POST['luggageAssignments'], true) : [];

  if (!empty($roomAssignments)) 
  {
    $conn->begin_transaction(); // Start transaction

    // Prepare statements for checking, updating, and inserting room assignments
    $checkQuery = "SELECT roomType, roomNumber FROM roominglist WHERE transactNo = ? AND guestId = ?";
    $checkStmt = $conn->prepare($checkQuery);

    $updateQuery = "UPDATE roominglist SET roomType = ?, roomNumber = ? WHERE transactNo = ? AND guestId = ?";
    $updateStmt = $conn->prepare($updateQuery);

    $insertQuery = "INSERT INTO roominglist (transactNo, guestId, roomType, roomNumber) VALUES (?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);

    // Prepare luggage check and insert
    $checkLuggageQuery = "SELECT COUNT(*) FROM guestluggage WHERE guestId = ? AND luggageType = ?";
    $checkLuggageStmt = $conn->prepare($checkLuggageQuery);

    $insertLuggageQuery = "INSERT INTO guestluggage (guestId, luggageType) VALUES (?, ?)";
    $insertLuggageStmt = $conn->prepare($insertLuggageQuery);

    // ======= START: Handle deleted guests =======

    // Get all existing guestIds for each transactNo
    $existingGuests = [];
    $transactNos = array_unique(array_column($roomAssignments, 'transactNo'));

    foreach ($transactNos as $transactNo)
    {
      $query = "SELECT guestId FROM roominglist WHERE transactNo = ?";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("s", $transactNo);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $existingGuests[$transactNo][] = (int)$row['guestId'];
      }
      
      $stmt->close();
    }

    // Build a map of currently submitted guestIds
    $newAssignments = [];
    foreach ($roomAssignments as $room) {
      $newAssignments[$room['transactNo']][] = (int)$room['guestId'];
    }

    // 3. Identify and delete removed guests
    foreach ($existingGuests as $transactNo => $guestIds) 
    {
      $assignedGuestIds = isset($newAssignments[$transactNo]) ? $newAssignments[$transactNo] : [];
      
      foreach ($guestIds as $guestId) 
      {
        if (!in_array($guestId, $assignedGuestIds)) 
        {
          // Delete guest from roominglist
          $deleteQuery = "DELETE FROM roominglist WHERE transactNo = ? AND guestId = ?";
          $deleteStmt = $conn->prepare($deleteQuery);
          $deleteStmt->bind_param("si", $transactNo, $guestId);
          $deleteStmt->execute();
          $deleteStmt->close();

          // (Optional) Also delete guest luggage if needed
          $deleteLuggageQuery = "DELETE FROM guestluggage WHERE guestId = ?";
          $deleteLuggageStmt = $conn->prepare($deleteLuggageQuery);
          $deleteLuggageStmt->bind_param("i", $guestId);
          $deleteLuggageStmt->execute();
          $deleteLuggageStmt->close();
        }
      }
    }

    // ======= END: Handle deleted guests =======

    // Now process inserts/updates for the current room assignments
    foreach ($roomAssignments as $room)
    {
      $transactNo = $room['transactNo'];
      $guestId = (int) $room['guestId']; // Ensure integer type
      $roomType = $room['roomType']; 
      $roomNumber = (int) $room['roomNumber']; // Ensure integer type

      // Check if guestId already exists in roominglist
      $checkStmt->bind_param("si", $transactNo, $guestId);
      $checkStmt->execute();
      $result = $checkStmt->get_result();

      if ($result->num_rows > 0) 
      {
        // Fetch current room assignment
        $row = $result->fetch_assoc();

        // Check if the room type or number has changed
        if ($row['roomType'] !== $roomType || $row['roomNumber'] !== $roomNumber) 
        {
          $updateStmt->bind_param("sisi", $roomType, $roomNumber, $transactNo, $guestId);
          $updateStmt->execute();
        }
      } 
      else 
      {
        // Insert new record
        $insertStmt->bind_param("sisi", $transactNo, $guestId, $roomType, $roomNumber);
        $insertStmt->execute();
      }

      // Free result after use
      $result->free();
    }

    // Insert guest luggage details only if not already existing
    if (!empty($luggageAssignments)) 
    {
      foreach ($luggageAssignments as $luggage) 
      {
        $guestId = (int) $luggage['guestId'];
        $luggageType = $luggage['luggageId'];

        // Check if this luggage type already exists for this guest
        $checkLuggageStmt->bind_param("is", $guestId, $luggageType);
        $checkLuggageStmt->execute();
        $checkLuggageStmt->bind_result($count);
        $checkLuggageStmt->fetch();

        // Free result after use
        $checkLuggageStmt->free_result();

        if ($count == 0) 
        {
          $insertLuggageStmt->bind_param("is", $guestId, $luggageType);
          $insertLuggageStmt->execute();
        }
      }
    }

    $conn->commit(); // Commit transaction
    echo json_encode(["status" => "success", "message" => "Room assignments and luggage details saved successfully"]);

    // Close all statements
    $checkStmt->close();
    $updateStmt->close();
    $insertStmt->close();
    $checkLuggageStmt->close();
    $insertLuggageStmt->close();
  } 
  else 
  {
    echo json_encode(["status" => "error", "message" => "No room assignments provided"]);
  }
} 
else 
{
  echo json_encode(["status" => "error", "message" => "Invalid request"]);
}

$conn->close();
?>
