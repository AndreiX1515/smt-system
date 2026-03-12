<?php
require "../../conn.php"; // Assumes this gives you an object-oriented MySQLi $conn
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['luggageToDelete']) && is_array($data['luggageToDelete'])) {
    $conn->begin_transaction(); // Begin transaction
    $errorOccurred = false;

    foreach ($data['luggageToDelete'] as $luggage) {
        $guestId = $conn->real_escape_string($luggage['guestId']);
        $luggageId = $conn->real_escape_string($luggage['luggageId']);

        $query = "DELETE FROM guestluggage WHERE luggageType = '$luggageId' AND guestId = '$guestId'";
        if (!$conn->query($query)) {
            $errorOccurred = true;
            break;
        }
    }

    if ($errorOccurred) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete one or more luggage items.']);
    } else {
        $conn->commit();
        echo json_encode(['status' => 'success']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
