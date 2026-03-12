<?php
require "../../conn.php";

if (isset($_POST['transactNo'])) {
    $transactNo = $_POST['transactNo'];

    // Prepare the query to fetch pax
    $query = "SELECT pax FROM booking WHERE transactNo = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $transactNo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['pax' => $row['pax']]);
        } else {
            echo json_encode(['error' => 'No data found']);
        }
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Database error']);
    }
    $conn->close();
} else {
    echo json_encode(['error' => 'Invalid input']);
}
?>
