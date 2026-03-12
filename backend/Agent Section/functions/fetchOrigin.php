<?php
  require "../../conn.php"; // Move up to the parent directory 

  if (isset($_POST['packageId'])) 
  {
    $packageId = $_POST['packageId'];

    // Fetch distinct origins based on the selected packageId
    $sql = mysqli_query($conn, "SELECT DISTINCT origin FROM flight WHERE packageId = '$packageId' ORDER BY origin ASC");

    $originOptions = '<option selected disabled>Select Origin</option>'; // Default option

    if (mysqli_num_rows($sql) > 0) 
    {
      while ($res = mysqli_fetch_array($sql)) 
      {
        $originOptions .= '<option value="' . $res['origin'] . '">' . $res['origin'] . '</option>';
      }
    } 
    else 
    {
      $originOptions = '<option selected disabled>No Origins Available</option>';
    }

    // Add the query to get the package price based on the packageId
    $sqlPrice = mysqli_query($conn, "SELECT packagePrice FROM package WHERE packageId = '$packageId'");
    $packagePrice = 0; // Default value

    if (mysqli_num_rows($sqlPrice) > 0) 
    {
      $resPrice = mysqli_fetch_array($sqlPrice);
      $packagePrice = $resPrice['packagePrice'];
    }

    // Return both the origin options and package price as a JSON response
    echo json_encode(array
    (
      "originOptions" => $originOptions,
      "packagePrice" => $packagePrice // Include package price in the response
    ));
  }
?>
