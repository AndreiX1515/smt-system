<?php
require "../../conn.php"; // Move up to the parent directory
session_start(); // Start the session to access $_SESSION variables

if (isset($_POST['flightDate'])) 
{
  $flightDate = $_POST['flightDate'];
  $agentType = $_SESSION['agentType'];

  if ($agentType === 'Retailer')
  {
    // Query to fetch the return flight based on the outbound flight ID
    $sql = mysqli_query($conn, "SELECT flightId, flightPrice FROM flight WHERE flightId = '$flightDate'");

    if (mysqli_num_rows($sql) > 0) 
    {
      $res = mysqli_fetch_array($sql);
      $flightPrice = $res['flightPrice']; // Fetching the flight price
      $flightId = $res['flightId']; // Fetching the flight ID

      // Return a JSON response with return flight schedule, price, and flight ID
      echo json_encode(array
      (
        "flightPrice" => $flightPrice,
        "flightId" => $flightId
      ));
    } 
    else 
    {
      // Return a JSON response if no return flight is available
      echo json_encode(array
      (
        "flightPrice" => null, // No price available
        "flightId" => null // No flight ID available
      ));
    }
  }

  else
  {
    // Query to fetch the return flight based on the outbound flight ID
    $sql = mysqli_query($conn, "SELECT flightId, wholesalePrice FROM flight WHERE flightId = '$flightDate'");

    if (mysqli_num_rows($sql) > 0) 
    {
      $res = mysqli_fetch_array($sql);
      $flightPrice = $res['wholesalePrice']; // Fetching the flight price
      $flightId = $res['flightId']; // Fetching the flight ID

      // Return a JSON response with return flight schedule, price, and flight ID
      echo json_encode(array
      (
        "flightPrice" => $flightPrice,
        "flightId" => $flightId
      ));
    } 
    else 
    {
      // Return a JSON response if no return flight is available
      echo json_encode(array
      (
        "flightPrice" => null, // No price available
        "flightId" => null // No flight ID available
      ));
    }
  }

  
}
?>