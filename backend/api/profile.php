<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

// POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// JSON  
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_json_response(['success' => false, 'message' => 'Invalid JSON.'], 400);
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get_profile':
            getUserProfile($conn, $input);
            break;
        case 'update_profile':
            updateUserProfile($conn, $input);
            break;
        case 'change_password':
            changePassword($conn, $input);
            break;
        case 'verify_password':
            verifyPassword($conn, $input);
            break;
        case 'delete_account':
            deleteAccount($conn, $input);
            break;
        default:
            send_json_response(['success' => false, 'message' => 'Invalid action.'], 400);
    }
} catch (Exception $e) {
    log_activity(0, "profile_api_error", "Profile API error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'An error occurred.'], 500);
}

function getUserProfile($conn, $input) {
    $accountId = $input['accountId'] ?? '';
    
    if (empty($accountId)) {
        send_json_response(['success' => false, 'message' => 'Account ID is required.'], 400);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            a.accountId, a.username, a.emailAddress, a.accountStatus, a.accountType, a.createdAt,
            c.clientId, c.fName, c.lName, c.contactNo, c.clientType, c.clientRole,
            ag.agentId AS agentCode
        FROM accounts a
        LEFT JOIN client c ON a.accountId = c.accountId
        LEFT JOIN agent ag ON a.accountId = ag.accountId
        WHERE a.accountId = ? AND a.accountStatus = 'active'
    ");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response(['success' => false, 'message' => 'Profile not found.'], 404);
    }
    
    $user = $result->fetch_assoc();
    
    $profile = [
        'accountId' => $user['accountId'],
        'username' => $user['username'],
        'email' => $user['emailAddress'],
        'firstName' => $user['fName'] ?? '',
        'lastName' => $user['lName'] ?? '',
        'phoneNumber' => $user['contactNo'] ?? '',
        'accountType' => $user['accountType'],
        'clientType' => $user['clientType'] ?? '',
        'clientRole' => $user['clientRole'] ?? ''
    ];
    
    send_json_response([
        'success' => true,
        'profile' => $profile
    ]);
}

function updateUserProfile($conn, $input) {
    //   ID 
    $user_id = $input['user_id'] ?? '';
    
    if (empty($user_id)) {
        send_json_response(['success' => false, 'message' => 'Account ID is required.'], 400);
    }
    
    $fname = trim($input['fname'] ?? '');
    $lname = trim($input['lname'] ?? '');
    $contact_no = trim($input['contact_no'] ?? '');
    $email = trim($input['email'] ?? '');
    
    if (empty($fname) || empty($contact_no) || empty($email)) {
        send_json_response(['success' => false, 'message' => 'Please enter all required fields.'], 400);
    }
    
    //   
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => 'The email format is not correct.'], 400);
    }
    
    //   
    if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $contact_no)) {
        send_json_response(['success' => false, 'message' => 'Contact format is not correct.'], 400);
    }
    
    //  
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE accountId = ? AND accountStatus = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response(['success' => false, 'message' => 'Account not found.'], 404);
    }
    
    //    ( )
    $checkEmail = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ? AND accountId != ? AND accountStatus = 'active'");
    $checkEmail->bind_param("si", $email, $user_id);
    $checkEmail->execute();
    $emailResult = $checkEmail->get_result();
    if ($emailResult->num_rows > 0) {
        send_json_response(['success' => false, 'message' => 'This email is already in use.'], 400);
    }
    
    // accounts   
    $stmt = $conn->prepare("UPDATE accounts SET emailAddress = ? WHERE accountId = ?");
    $stmt->bind_param("si", $email, $user_id);
    if (!$stmt->execute()) {
        send_json_response(['success' => false, 'message' => 'Failed to update account.'], 500);
    }
    
    //   
    $stmt = $conn->prepare("
        UPDATE client 
        SET fName = ?, lName = ?, contactNo = ? 
        WHERE accountId = ?
    ");
    
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => 'Failed to update profile.'], 500);
    }
    
    $stmt->bind_param("sssi", $fname, $lname, $contact_no, $user_id);
    if (!$stmt->execute()) {
        send_json_response(['success' => false, 'message' => 'Failed to update profile.'], 500);
    }

    /**
     * IMPORTANT:
     * UPDATE affected_rows "  "   0   .
     *   affected_rows===0    INSERT ,
     *  client   (  )  INSERT    .
     * →      .
     */
    $existsStmt = $conn->prepare("SELECT 1 FROM client WHERE accountId = ? LIMIT 1");
    if (!$existsStmt) {
        send_json_response(['success' => false, 'message' => 'Failed to update profile.'], 500);
    }
    $existsStmt->bind_param("i", $user_id);
    $existsStmt->execute();
    $existsRes = $existsStmt->get_result();
    $hasClientRow = ($existsRes && $existsRes->num_rows > 0);
    $existsStmt->close();

    if (!$hasClientRow) {
        //     
        $client_id = 'CLI' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
        $insertStmt = $conn->prepare("
            INSERT INTO client (clientId, accountId, companyId, fName, lName, contactNo, clientType, clientRole) 
            VALUES (?, ?, 1, ?, ?, ?, 'Retailer', 'Sub-Agent')
        ");
        
        if (!$insertStmt) {
            send_json_response(['success' => false, 'message' => 'Failed to update profile.'], 500);
        }
        
        $insertStmt->bind_param("sisss", $client_id, $user_id, $fname, $lname, $contact_no);
        if (!$insertStmt->execute()) {
            send_json_response(['success' => false, 'message' => 'Failed to update profile.'], 500);
        }
        $insertStmt->close();
    }
    
    send_json_response([
        'success' => true,
        'message' => 'Saved.'
    ]);
}

function changePassword($conn, $input) {
    //  ID  (input  )
    $user_id = $input['user_id'] ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    
    if (empty($user_id)) {
        send_json_response(['success' => false, 'message' => ' .'], 401);
    }
    
    // user_id  
    $user_id = (int)$user_id;
    
    if ($user_id <= 0) {
        send_json_response(['success' => false, 'message' => '   ID.'], 400);
    }
    
    $new_password = $input['new_password'] ?? '';
    
    if (empty($new_password)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    //   : //  8~12
    if (strlen($new_password) < 8 || strlen($new_password) > 12) {
        send_json_response(['success' => false, 'message' => ' 8~12 .'], 400);
    }
    
    if (!preg_match('/[a-zA-Z]/', $new_password)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $new_password)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    //  
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE accountId = ? AND accountStatus = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response(['success' => false, 'message' => '   .'], 404);
    }
    
    //     
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE accounts SET password = ? WHERE accountId = ?");
    
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => '   .'], 500);
    }
    
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if (!$stmt->execute()) {
        send_json_response(['success' => false, 'message' => '  : ' . $stmt->error], 500);
    }
    
    $stmt->close();
    
    log_activity($user_id, "password_changed", "Password changed for user: {$user_id}");
    
    send_json_response([
        'success' => true,
        'message' => ' .'
    ]);
}

function verifyPassword($conn, $input) {
    $user_id = $input['user_id'] ?? ($input['accountId'] ?? $input['account_id'] ?? '');
    $current_password = $input['current_password'] ?? '';
    
    if (empty($user_id) || empty($current_password)) {
        send_json_response(['success' => false, 'message' => 'Account ID and current password are required.'], 400);
    }
    
    //     
    // SMT: Treat NULL/empty status as active (legacy) and allow schema variations.
    $statusCol = null;
    try {
        $cols = $conn->query("SHOW COLUMNS FROM accounts");
        if ($cols) {
            while ($c = $cols->fetch_assoc()) {
                $f = strtolower((string)($c['Field'] ?? ''));
                if ($f === 'accountstatus') $statusCol = 'accountStatus';
                else if ($f === 'status' && !$statusCol) $statusCol = 'status';
            }
        }
    } catch (Throwable $_) { $statusCol = null; }
    $where = "accountId = ?";
    if ($statusCol) {
        $where .= " AND COALESCE(NULLIF($statusCol,''),'active') = 'active'";
    }
    $stmt = $conn->prepare("SELECT password FROM accounts WHERE $where");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        send_json_response(['success' => false, 'message' => 'User account not found or inactive.'], 404);
    }
    
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        send_json_response(['success' => false, 'message' => 'Current password is incorrect.'], 401);
    }
    
    send_json_response([
        'success' => true,
        'message' => 'Password verified successfully.'
    ]);
}

//  
function deleteAccount($conn, $input) {
    $user_id = $input['user_id'] ?? '';
    
    if (empty($user_id)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    // ()  
    // -  "       " 
    //    pending/confirmed    
    //     ( ) (refunded)     .
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM bookings
        WHERE accountId = ?
          AND bookingStatus IN ('pending', 'confirmed')
          AND (departureDate IS NULL OR DATE(departureDate) >= CURDATE())
          AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        send_json_response([
            'success' => false,
            'message' => '     .',
            'hasActiveBookings' => true
        ], 400);
    }
    
    try {
        //  
        $conn->begin_transaction();
        
        //   'inactive'  (soft delete)
        $stmt = $conn->prepare("UPDATE accounts SET accountStatus = 'inactive' WHERE accountId = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        //      ()
        // : client , bookings  
        
        $conn->commit();
        
        log_activity($user_id, "account_deleted", "Account deleted: {$user_id}");
        
        send_json_response([
            'success' => true,
            'message' => '  .'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity($user_id ?? 0, "delete_account_error", "Delete account error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '    .'], 500);
    }
}
?>