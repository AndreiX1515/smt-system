<?php
session_start();

// Unset the specific session variable
unset($_SESSION['flightId']);

// Return a success response
echo json_encode(["success" => true]);
exit;
?>
