<?php
require "../conn.php";

header('Content-Type: application/json; charset=utf-8');

// POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// JSON  
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_json_response(['success' => false, 'message' => 'Invalid JSON.'], 400);
}

$email = sanitize_input($input['email'] ?? '');
$excludeAccountId = isset($input['accountId']) ? (int)$input['accountId'] : (isset($input['userId']) ? (int)$input['userId'] : 0);

//   
if (empty($email)) {
    send_json_response(['success' => false, 'message' => 'Email is required.'], 400);
}

//   
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['success' => false, 'message' => 'The email format is not correct.'], 400);
}

try {
    // Email duplication check (optionally excluding a specific accountId for edit-profile)
    if ($excludeAccountId > 0) {
        $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ? AND accountId <> ? LIMIT 1");
        $stmt->bind_param("si", $email, $excludeAccountId);
    } else {
        $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ? LIMIT 1");
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        send_json_response([
            'success' => true, 
            'available' => false, 
            'message' => 'This email is already in use.'
        ]);
    } else {
        send_json_response([
            'success' => true, 
            'available' => true, 
            'message' => 'Email is available.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("check-email error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'An error occurred.'], 500);
}
?>