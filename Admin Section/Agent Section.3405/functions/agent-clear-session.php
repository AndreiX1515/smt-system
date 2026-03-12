<?php
session_start();

// Check if specific session keys exist before unsetting
if (!empty($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        unset($_SESSION[$key]); // Unset all session variables
    }
}

// Ensure session data is written and closed properly
session_write_close();

// Return a JSON response
echo json_encode([
    "success" => true,
    "message" => "Session data cleared successfully."
]);
exit;
?>
