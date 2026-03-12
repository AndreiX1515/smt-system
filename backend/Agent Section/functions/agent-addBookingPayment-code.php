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

    if (!isset($_FILES['proofs']) || count($_FILES['proofs']['name']) === 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Proof of payment files are required."]);
        exit;
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/SMART-TRAVEL-MANAGEMENT-SYSTEM/Files Uploads/Payment Uploads/$transactNo/";
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $maxFileSize = 4 * 1024 * 1024; // 4MB
    $uploadedFiles = [];

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create upload directory."]);
        exit;
    }

    foreach ($_FILES['proofs']['name'] as $key => $fileName) {
        $fileTmpPath = $_FILES['proofs']['tmp_name'][$key];
        $fileSize = $_FILES['proofs']['size'][$key];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions) || $fileSize > $maxFileSize || $_FILES['proofs']['error'][$key] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "File $fileName is invalid or exceeds 4MB limit."]);
            exit;
        }

        if (!is_uploaded_file($fileTmpPath)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Potential file upload attack detected on $fileName."]);
            exit;
        }

        $newFileName = $transactNo . '-' . date('m-d-Y_H-i') . '-' . uniqid() . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $uploadedFiles[] = $destPath;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to upload file: $fileName"]);
            exit;
        }
    }

    if (!empty($uploadedFiles)) {
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO payment (transactNo, accountId, paymentTitle, paymentType, amount, filePath, paymentDate, paymentStatus) 
                                VALUES (?, ?, 'Package Payment', 'Downpayment', ?, ?, ?, 'Submitted')");

        if (!$stmt) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
            exit;
        }

        foreach ($uploadedFiles as $filePath) {
            $stmt->bind_param('sidss', $transactNo, $accountId, $amount, $filePath, $paymentDate);
            if (!$stmt->execute()) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Database error on payment insert: " . $stmt->error]);
                exit;
            }
        }

        $stmt1 = $conn->prepare("UPDATE booking SET status = 'Pending' WHERE transactNo = ?");
        if (!$stmt1) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
            exit;
        }

        $stmt1->bind_param('s', $transactNo);
        if (!$stmt1->execute()) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error on booking update: " . $stmt1->error]);
            exit;
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Payment and proof files uploaded successfully!"]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No valid files uploaded."]);
    exit;
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid request."]);
?>
