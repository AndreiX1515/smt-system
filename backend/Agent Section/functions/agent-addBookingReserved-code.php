<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../conn.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
  $transactNo = $conn->real_escape_string($_POST['transactNo']);
  $accountId = (int) $_POST['agentAccountId'];
  $amount = (float) $_POST['downpayment'];

  date_default_timezone_set('Asia/Taipei');
  $paymentDate = date('Y-m-d H:i:s');

  $conn->query("SET @current_user_id = $accountId");

  $conn->begin_transaction();

  $stmt = $conn->prepare("INSERT INTO payment (transactNo, accountId, paymentTitle, paymentType, amount, filePath, paymentDate, paymentStatus) 
                          VALUES (?, ?, 'Package Payment', 'No Downpayment', ?, NULL, ?, 'Submitted')");

  if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
  }

  $stmt->bind_param('sids', $transactNo, $accountId, $amount, $paymentDate);

  if (!$stmt->execute()) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Payment insert error: " . $stmt->error]);
    exit;
  }

  $conn->commit();

  echo json_encode([
    "status" => "success",
    "message" => "Payment record saved for reserved booking.",
    "bookingStatus" => "Pay Later",
    "transactionNumber" => $transactNo
  ]);
  exit;
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid request."]);
exit;
