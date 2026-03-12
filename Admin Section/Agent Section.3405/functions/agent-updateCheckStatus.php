<?php
session_start();
require "../../conn.php"; // Database connection

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = $_POST['updates']; // Get updates array from AJAX

    foreach ($updates as $flightId => $isActive) {
        $flightId = intval($flightId);
        $isActive = intval($isActive);

        $stmt = $conn->prepare("UPDATE flight SET is_active = ? WHERE flightId = ?");
        $stmt->bind_param("ii", $isActive, $flightId);
        $stmt->execute();
    }

    echo "Success";
}
?>
