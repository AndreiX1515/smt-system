<?php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);  
  require "../../conn.php"; // Move up to the parent directory 

  if (isset($_POST['hotelId'])) 
  {
    $hotelId = $_POST['hotelId'];

    // Fetch distinct origins based on the selected packageId
    $sql = mysqli_query($conn, "SELECT r.roomId as roomId, r.rooms as rooms, r.availRooms - IFNULL((
                    SELECT SUM(rooms) 
                    FROM fit f 
                    WHERE f.roomId = r.roomId 
                    AND f.status = 'Confirmed'), 0) AS availRooms 
                                FROM fitrooms r
                                WHERE r.hotelId = '$hotelId' ORDER BY r.price ASC");

    $roomOptions = '<option selected disabled>Select Room</option>'; // Default option

    if (mysqli_num_rows($sql) > 0) 
    {
      while ($res = mysqli_fetch_array($sql)) 
      {
        $roomOptions .= '<option value="' . $res['roomId'] . '">' . $res['rooms'] . ' Available Rooms Left: '. $res['availRooms']. '</option>';
      }
    } 
    else 
    {
      $roomOptions = '<option selected disabled>No Rooms Available</option>';
    }

    // Return both the origin options and package price as a JSON response
    echo json_encode(array
    (
      "roomOptions" => $roomOptions
    ));
  }
?>
