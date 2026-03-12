<?php
require "../conn.php";

// POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'POST  .'], 405);
}

// JSON  
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_json_response(['success' => false, 'message' => ' JSON .'], 400);
}

$action = $input['action'] ?? '';
$email = sanitize_input($input['email'] ?? '');
$verificationCode = $input['verificationCode'] ?? '';
$newPassword = $input['newPassword'] ?? '';

// / action 
// - send -> send_code
// - verify -> verify_code
// - reset -> reset_password
if ($action === 'send') $action = 'send_code';
if ($action === 'verify') $action = 'verify_code';
if ($action === 'reset') $action = 'reset_password';

switch ($action) {
    case 'send_code':
        handleSendVerificationCode($email);
        break;
    case 'verify_code':
        handleVerifyCode($email, $verificationCode);
        break;
    case 'reset_password':
        handleResetPassword($email, $verificationCode, $newPassword);
        break;
    default:
        send_json_response(['success' => false, 'message' => ' .'], 400);
}

// 1:  
function handleSendVerificationCode($email) {
    global $conn;
    
    if (empty($email)) {
        send_json_response(['success' => false, 'message' => ' .'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    try {
        //    (  : emailAddress/email)
        $accCols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM accounts");
        while ($colRes && ($c = $colRes->fetch_assoc())) {
            $accCols[strtolower($c['Field'])] = $c['Field'];
        }
        $emailCol = $accCols['emailaddress'] ?? ($accCols['email'] ?? 'emailAddress');

        $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE `{$emailCol}` = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '      .'], 404);
        }
        
        // 6  
        $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
    //    (  Redis DB )
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
        $_SESSION['password_reset'] = [
            'email' => $email,
            'code' => $verificationCode,
            'expires' => time() + 600, // 10  
            'attempts' => 0
        ];
        
        //    
        // sendPasswordResetEmail($email, $verificationCode);
        
        //     
        error_log("Password reset code for {$email}: {$verificationCode}");
        
        // log_activity(accountId, action, details)
        log_activity(0, "password_reset_send_code", "Password reset code sent to: {$email}");
        
        send_json_response([
            'success' => true,
            'message' => ' . (: ' . $verificationCode . ')',
            'data' => [
                'email' => $email,
                'expiresIn' => 600 // 10
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity(0, "password_reset_send_code_error", "Password reset send code error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

// 2:  
function handleVerifyCode($email, $verificationCode) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($email) || empty($verificationCode)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    if (!isset($_SESSION['password_reset'])) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    $resetData = $_SESSION['password_reset'];
    
    //   
    if (time() > $resetData['expires']) {
        unset($_SESSION['password_reset']);
        send_json_response(['success' => false, 'message' => ' .  .'], 400);
    }
    
    //   
    if ($resetData['email'] !== $email) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    //    ( 5)
    if ($resetData['attempts'] >= 5) {
        unset($_SESSION['password_reset']);
        send_json_response(['success' => false, 'message' => '   .  .'], 400);
    }
    
    //  
    if ($resetData['code'] !== $verificationCode) {
        $_SESSION['password_reset']['attempts']++;
        $remainingAttempts = 5 - $_SESSION['password_reset']['attempts'];
        send_json_response(['success' => false, 'message' => "  . ( : {$remainingAttempts})"], 400);
    }
    
    //   -    
    $_SESSION['password_reset']['verified'] = true;
    
    log_activity(0, "password_reset_verify_code", "Password reset code verified for: {$email}");
    
    send_json_response([
        'success' => true,
        'message' => ' .',
        'data' => [
            'email' => $email,
            'verified' => true
        ]
    ]);
}

// 3:  
function handleResetPassword($email, $verificationCode, $newPassword) {
    global $conn;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($email) || empty($verificationCode) || empty($newPassword)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    if (!isset($_SESSION['password_reset']) || !$_SESSION['password_reset']['verified']) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    $resetData = $_SESSION['password_reset'];
    
    //   
    if (time() > $resetData['expires']) {
        unset($_SESSION['password_reset']);
        send_json_response(['success' => false, 'message' => ' .  .'], 400);
    }
    
    //   
    if ($resetData['email'] !== $email || $resetData['code'] !== $verificationCode) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    //   
    if (strlen($newPassword) < 6) {
        send_json_response(['success' => false, 'message' => '  6  .'], 400);
    }
    
    try {
        // accounts   : emailAddress/email, password/passwordHash
        $accCols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM accounts");
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) $accCols[strtolower($c['Field'])] = $c['Field'];
        }
        $emailCol = isset($accCols['emailaddress']) ? $accCols['emailaddress'] : (isset($accCols['email']) ? $accCols['email'] : 'emailAddress');
        $passwordCol = isset($accCols['password']) ? $accCols['password'] : (isset($accCols['passwordhash']) ? $accCols['passwordhash'] : 'password');
        $passwordHashCol = isset($accCols['passwordhash']) ? $accCols['passwordhash'] : null;

        //    
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updates = [];
        $values = [];
        $types = '';
        if ($passwordCol) {
            $updates[] = "`{$passwordCol}` = ?";
            $values[] = $hashedPassword;
            $types .= 's';
        }
        if ($passwordHashCol && $passwordHashCol !== $passwordCol) {
            $updates[] = "`{$passwordHashCol}` = ?";
            $values[] = $hashedPassword;
            $types .= 's';
        }
        $updates[] = "updatedAt = NOW()";

        $sql = "UPDATE accounts SET " . implode(', ', $updates) . " WHERE `{$emailCol}` = ?";
        $stmt = $conn->prepare($sql);
        $values[] = $email;
        $types .= 's';
        mysqli_bind_params_by_ref($stmt, $types, $values);
        
        if ($stmt->execute()) {
            //  
            unset($_SESSION['password_reset']);
            
            log_activity(0, "password_reset_completed", "Password reset completed for: {$email}");
            
            send_json_response([
                'success' => true,
                'message' => '  .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '    .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity(0, "password_reset_error", "Password reset error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}
?>
