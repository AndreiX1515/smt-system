<?php
  // Include your database connection
  include '../../conn.php'; // Adjust the path as necessary

  header('Content-Type: application/json');

  $input = json_decode(file_get_contents('php://input'), true);
  $transaction_id = $input['transaction_id'];

  // Prepare the SQL statement
  $stmt = $conn->prepare("SELECT * FROM booking WHERE transactNo = ?");
  $stmt->bind_param("s", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();

  // Check if a booking was found
  if ($result->num_rows > 0) 
  {
    $booking = $result->fetch_assoc();
    echo json_encode(['success' => true, 'booking' => $booking]);
  } 
  else 
  {
    echo json_encode(['success' => false, 'message' => 'No booking found.']);
  }

  $stmt->close();
  $conn->close();
?>
