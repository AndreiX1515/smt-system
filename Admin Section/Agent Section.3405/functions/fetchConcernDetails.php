<?php
  require "../../conn.php"; // Move up to the parent directory

  if (isset($_POST['concernId'])) 
  {
    $concernId = $_POST['concernId'];

    // Fetch distinct details and their prices based on the selected concernId
    $sql = mysqli_query($conn, "SELECT concernDetailsId, details, price FROM concerndetails WHERE concernId = '$concernId' ORDER BY details ASC");

    $detailsOptions = '<option selected disabled>Select Detail</option>'; // Default option

    $detailsData = [];  // Array to store details and price data

    if (mysqli_num_rows($sql) > 0) 
    {
      while ($res = mysqli_fetch_array($sql)) 
      {
        $detailsData[] = 
        [
          'id' => $res['concernDetailsId'],
          'title' => $res['details'],
          'price' => number_format($res['price'], 2)  // Format price to 2 decimal places
        ];

        // Create options for the select dropdown
        $detailsOptions .= '<option value="' . $res['concernDetailsId'] . '" data-price="' . $res['price'] . '">' . $res['details'] . '</option>';
      }
    } 
    else 
    {
      $detailsOptions = '<option selected disabled>No Details Available</option>';
    }

    // Return both the details options, price data, and formatted price as a JSON response
    echo json_encode(array(
      "detailsOptions" => $detailsOptions, // Options for the details dropdown
      "detailsData" => $detailsData // Additional details with price for further use
    ));
  }
?>
