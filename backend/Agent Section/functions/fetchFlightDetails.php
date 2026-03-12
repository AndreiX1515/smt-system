<?php
require "../../conn.php"; // Move up to the parent directory
session_start(); // Start the session to access $_SESSION variables

if (isset($_POST['flightId'])) 
{
  $flightId = $_POST['flightId'];
  $agentType = $_POST['agentType'];

  if ($agentType === 'Retailer')
  {
    $sql1 = "SELECT f.flightPrice as flightPrice, f.origin as origin, f.packageId as packageId, p.packagePrice as packagePrice,
                p.packageName as packageName
              FROM flight f
              JOIN package p ON f.packageId = p.packageId
              WHERE flightId = $flightId";
    $result = $conn->query($sql1);

    if ($result->num_rows > 0) 
    {
      $row = $result->fetch_assoc();
      echo json_encode(
      [
        'packageName' => $row['packageName'],
        'packagePrice' => $row['packagePrice'],
        'flightPrice' => $row['flightPrice'],
        'origin' => $row['origin'],
        'packageId' => $row['packageId']
      ]);
    } 
    else 
    {
      echo json_encode(array
      (
        'packageName' => null,
        "packagePrice" => null, // No price available
        "flightPrice" => null, // No price available
        "origin" => null,
        "packageId" => null // No flight ID available
      ));
    }
  }
  else if ($agentType === 'Wholeseller')
  {
    $sql1 = "SELECT f.wholesalePrice as wholesalePrice, f.origin as origin, f.packageId as packageId, p.packagePrice as packagePrice,
                p.packageName as packageName
              FROM flight f
              JOIN package p ON f.packageId = p.packageId
              WHERE flightId = $flightId";
    $result = $conn->query($sql1);

    if ($result->num_rows > 0) 
    {
      $row = $result->fetch_assoc();
      echo json_encode(
      [
        'packageName' => $row['packageName'],
        'packagePrice' => $row['packagePrice'],
        'flightPrice' => $row['wholesalePrice'],
        'origin' => $row['origin'],
        'packageId' => $row['packageId']
      ]);
    } 
    else 
    {
      echo json_encode(array
      (
        'packageName' => null,
        "packagePrice" => null, // No price available
        "flightPrice" => null, // No price available
        "origin" => null,
        "packageId" => null // No flight ID available
      ));
    }
  }
}
?>