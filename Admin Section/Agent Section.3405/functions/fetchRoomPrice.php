<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require "../../conn.php"; // Move up to the parent directory 

if (isset($_POST['roomId'])) 
{
    $roomId = $_POST['roomId'];

    // Query to fetch price and calculate available rooms
    $sql = mysqli_query($conn, "
        SELECT 
            r.roomId AS roomId, 
            r.price AS price, 
            r.availRooms - IFNULL((
                SELECT SUM(f.rooms) 
                FROM fit f 
                WHERE f.roomId = r.roomId 
                AND f.status = 'Confirmed'
            ), 0) AS availRooms 
        FROM fitrooms r 
        WHERE r.roomId = '$roomId' 
        ORDER BY r.price ASC
    ");

    if (mysqli_num_rows($sql) > 0) 
    {
        $room = mysqli_fetch_assoc($sql);
        $roomPrice = $room['price']; // Extract price value
        $avail = $room['availRooms']; // Extract computed available rooms
    } 
    else 
    {
        $roomPrice = 'Error';
        $avail = 0;
    }

    // Return both the room price and available rooms as a JSON response
    echo json_encode(array(
        "roomPrice" => $roomPrice,
        "avail" => $avail
    ));
}
?>
