<?php
require "../../conn.php"; // Database connection

// Fetch the highest room number from the roomingList table
$query = "SELECT MAX(roomNumber) AS maxRoomNumber FROM roominglist";
$result = mysqli_query($conn, $query);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode(["maxRoomNumber" => $row['maxRoomNumber'] ?? 0]);
} else {
    echo json_encode(["maxRoomNumber" => 0]);
}
?>
