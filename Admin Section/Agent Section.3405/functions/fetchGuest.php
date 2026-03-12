<?php
require "../../conn.php"; // Database connection

if (isset($_POST['flightDate']) && isset($_POST['agentCode'])) 
{
  $flightDate = $_POST['flightDate'];
  $agentCode = $_POST['agentCode'];

  $assignedGuests = [];
  $unassignedGuests = [];

  // Fetch guests who are assigned to a room
  $sqlAssigned = "SELECT g.guestId, g.fName, g.mName, g.lName, g.suffix, DATE_FORMAT(g.birthdate, '%Y %b %d') AS birthdate, g.age, g.sex, 
                    g.nationality, g.passportNo, DATE_FORMAT(g.passportExp, '%d %b %Y') AS passportExp, g.transactNo, r.roomNumber, r.roomType,
                    cd.concernDetailsId
                  FROM `guest` g
                  LEFT JOIN `roominglist` r ON g.guestId = r.guestId
                  JOIN `booking` b ON g.transactNo = b.transactNo
                  JOIN `flight` f ON b.flightId = f.flightId
                  LEFT JOIN `guestluggage` l ON g.guestId = l.guestId
                  LEFT JOIN `concerndetails` cd ON l.luggageType = cd.concernDetailsId
                  WHERE b.status = 'Confirmed' 
                    AND f.flightDepartureDate = ? 
                    AND b.agentCode = ?
                    AND r.roomNumber IS NOT NULL";

  if ($stmtAssigned = $conn->prepare($sqlAssigned))
  {
    $stmtAssigned->bind_param("ss", $flightDate, $agentCode);
    $stmtAssigned->execute();
    $resultAssigned = $stmtAssigned->get_result();

    $guestMap = []; // temp map for grouping

    while ($row = $resultAssigned->fetch_assoc()) 
    {
      $guestId = $row['guestId'];
      
      // Initialize guest if not already in map
      if (!isset($guestMap[$guestId])) 
      {
        $guestMap[$guestId] = [
          "id" => $guestId,
          "transactNo" => $row['transactNo'],
          "name" => trim($row['fName'] . " " . ($row['suffix'] === 'N/A' ? '' : $row['suffix']) . " " . $row['lName']),
          "age" => $row['age'],
          "dob" => $row['birthdate'] ?: 'N/A',
          "sex" => $row['sex'],
          "nationality" => $row['nationality'],
          "passport" => $row['passportNo'],
          "passportExp" => $row['passportExp'] ?: 'N/A',
          "roomNumber" => $row['roomNumber'],
          "roomType" => $row['roomType'] ?: 'N/A',
          "luggageType" => [] // store as array first
        ];
      }

      // Add luggage if found
      if (!empty($row['concernDetailsId'])) 
      {
        $guestMap[$guestId]['luggageType'][] = $row['concernDetailsId'];
      }
    }

    // Now process map into array, combining luggageType as comma-separated string
    foreach ($guestMap as $guest) {
      $assignedGuests[] = $guest;
    }
  }


  // Fetch guests who are NOT assigned to a room
  $sqlUnassigned = "SELECT g.guestId, g.fName, g.mName, g.lName, g.suffix, DATE_FORMAT(g.birthdate, '%Y %b %d') AS birthdate, g.age, g.sex, 
                    g.nationality, g.passportNo, DATE_FORMAT(g.passportExp, '%d %b %Y') AS passportExp, g.transactNo
                  FROM `guest` g
                  JOIN `booking` b ON g.transactNo = b.transactNo
                  JOIN `flight` f ON b.flightId = f.flightId
                  LEFT JOIN `roominglist` r ON g.guestId = r.guestId
                  WHERE b.status = 'Confirmed' AND f.flightDepartureDate = ? AND b.agentCode = ? AND r.guestId IS NULL";

  if ($stmtUnassigned = $conn->prepare($sqlUnassigned))
  {
    $stmtUnassigned->bind_param("ss", $flightDate, $agentCode);
    $stmtUnassigned->execute();
    $resultUnassigned = $stmtUnassigned->get_result();

    while ($row = $resultUnassigned->fetch_assoc())
    {
      $unassignedGuests[] = [
        "id" => $row['guestId'],
        "transactNo" => $row['transactNo'],
        "name" => trim($row['fName'] . " " . ($row['suffix'] === 'N/A' ? '' : $row['suffix']) . " " . $row['lName']),
        "age" => $row['age'],
        "dob" => $row['birthdate'] ?: 'N/A',
        "sex" => $row['sex'],
        "nationality" => $row['nationality'],
        "passport" => $row['passportNo'],
        "passportExp" => $row['passportExp'] ?: 'N/A'
      ];
    }
  }

  // Return both assigned and unassigned guests with luggage info
  echo json_encode([
    "assignedGuests" => $assignedGuests,
    "unassignedGuests" => $unassignedGuests
  ]);
}
?>
