<?php
  require "../../conn.php"; // Adjust path if needed
  session_start(); // Start the session to access session variables

  if (isset($_POST['flightId']) && isset($_POST['accId'])) 
  {
    $flightId = $_POST['flightId'];
    $accId = $_POST['accId'];

    $query = "SELECT f.flightId, a.seats AS totalAgentSeats,(f.availSeats - COALESCE(( SELECT SUM(b.pax) FROM booking b WHERE b.flightId = f.flightId AND b.accountId = a.accountId AND (b.status = 'Confirmed' OR b.status='Reserved')
    
    AND b.bookingType = 'Package'), 0)) AS totalSeatsLeft, GREATEST(a.seats - COALESCE((SELECT SUM(b.pax) FROM booking b 
    WHERE b.flightId = f.flightId AND b.accountId = a.accountId AND  (b.status = 'Confirmed' OR b.status='Reserved') 
    AND b.bookingType = 'Package'), 0), 0) AS availableSeats
    FROM agent a
    JOIN flight f ON f.flightId = ?
    WHERE a.accountId = ?";

    // Query flight seat through agentflightseat Table
    // $query = "SELECT flight.flightId, flight.availSeats - IFNULL((SELECT SUM(pax) FROM booking WHERE booking.flightId = flight.flightId 
    //             AND booking.status = 'Confirmed' AND booking.bookingType = 'Package'), 0) AS totalSeatsLeft,
    //             agentflightseats.flightSeatId,  agentflightseats.agentId, agentflightseats.maxSeats, GREATEST(agentflightseats.maxSeats - 
    //             (SELECT IFNULL(SUM(pax), 0) FROM booking WHERE booking.flightId = agentflightseats.flightId 
    //             AND booking.agentId = agentflightseats.agentId AND booking.status = 'Confirmed' AND booking.bookingType = 'Package'), 0
    //             ) AS availableSeats
    //           FROM agentflightseats
    //           JOIN flight ON flight.flightId = agentflightseats.flightId
    //           WHERE agentflightseats.agentId = ? 
    //           AND agentflightseats.flightId = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $flightId, $accId); // Bind parameters to prevent SQL injection
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) 
    {
      $res = $result->fetch_assoc();
      $flightId = $res['flightId']; // Fetch the flight ID
      $totalSeatsLeft = $res['totalSeatsLeft']; // Dynamically calculated remaining seats
      $maxSeats = $res['availableSeats']; // Fetch the max available seats

      // Return a JSON response
      echo json_encode(array(
          "flightId" => $flightId,
          "totalSeatsLeft" => $totalSeatsLeft,
          "maxSeats" => $maxSeats
      ));
    } 
    else 
    {
      // No data found
      echo json_encode(array(
          "flightId" => null,
          "totalSeatsLeft" => null,
          "maxSeats" => null
      ));
    }
    $stmt->close(); // Close the statement
    $conn->close(); // Close the connection
  } 
  else 
  {
    // If required data is missing, return null values
    echo json_encode(array(
        "flightId" => null,
        "totalSeatsLeft" => $null,
        "maxSeats" => null
    ));
  }
?>
