<?php
//    
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$endpoint = $_GET['endpoint'] ?? '';

//  
error_log("Auth API called: Method=$method, Endpoint=$endpoint");

switch ($endpoint) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'profile':
        handleProfile();
        break;
    case 'change-password':
        handleChangePassword();
        break;
    case 'forgot-password':
        handleForgotPassword();
        break;
    case 'reset-password':
        handleResetPassword();
        break;
    case 'verify-email':
        handleVerifyEmail();
        break;
    case 'refresh':
        handleRefreshToken();
        break;
    default:
        send_json_response(['success' => false, 'message' => ' .'], 404);
}

function handleLogin() {
    error_log("handleLogin function called");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("Invalid method: " . ($_SERVER['REQUEST_METHOD'] ?? 'undefined'));
        send_json_response(['success' => false, 'message' => 'POST  .'], 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Login input: " . json_encode($input));

    if (!isset($input['email']) || !isset($input['password'])) {
        error_log("Missing email or password");
        send_json_response(['success' => false, 'message' => '  .'], 400);
        return;
    }

    global $conn;
    
    //   
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        send_json_response(['success' => false, 'message' => '   .'], 500);
        return;
    }
    
    //   (accounts + client )
    $query = "SELECT a.*, c.fName as firstName, c.lName as lastName, c.contactNo as phoneNumber 
              FROM accounts a 
              LEFT JOIN client c ON a.accountId = c.accountId 
              WHERE a.emailAddress = ? AND a.accountStatus = 'active'";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        send_json_response(['success' => false, 'message' => '    .'], 500);
        return;
    }
    
    $stmt->bind_param('s', $input['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        //    ( active     )
        
        //  
        if (password_verify($input['password'], $user['password'])) {
            //  
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            //  
            $_SESSION['accountId'] = $user['accountId'];
            $_SESSION['email'] = $user['emailAddress'];
            $_SESSION['accountRole'] = $user['accountType'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['lastName'] = $user['lastName'];
            $_SESSION['isLoggedIn'] = true;
            
            //    
            log_activity($user['accountId'], 'login', 'User logged in successfully');
            
            //     ( ) -  DB    
            if (isset($input['rememberMe']) && $input['rememberMe']) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30
                // TODO: rememberToken  accounts   
            }
            
            //  
            $response = [
                'success' => true,
                'message' => ' ',
                'user' => [
                    'accountId' => $user['accountId'],
                    'email' => $user['emailAddress'],
                    'firstName' => $user['firstName'],
                    'lastName' => $user['lastName'],
                    'accountRole' => $user['accountType'],
                    'phoneNumber' => $user['phoneNumber']
                ]
            ];
            
            send_json_response($response);
            
        } else {
            //  
            log_activity($user['accountId'], 'login_failed', 'Invalid password attempt');
            send_json_response(['success' => false, 'message' => '  .'], 401);
        }
    } else {
        //  
        send_json_response(['success' => false, 'message' => '   .'], 401);
    }
}

function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'message' => 'POST  .'], 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['email', 'password', 'firstName', 'lastName', 'phoneNumber'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            send_json_response(['success' => false, 'message' => "  : $field"], 400);
            return;
        }
    }

    //   
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
        return;
    }

    //    (8 , // )
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $input['password'])) {
        send_json_response(['success' => false, 'message' => ' 8  , ,   .'], 400);
        return;
    }

    global $conn;
    
    //   
    $checkStmt = $conn->prepare("SELECT accountId FROM accounts WHERE emailAddress = ?");
    $checkStmt->bind_param('s', $input['email']);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        send_json_response(['success' => false, 'message' => '  .'], 409);
        return;
    }

    //  
    $conn->begin_transaction();
    
    try {
        //  
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $accountStmt = $conn->prepare("INSERT INTO accounts (username, emailAddress, password, accountType) VALUES (?, ?, ?, 'guest')");
        $accountStmt->bind_param('sss', 
            $input['email'], // username  
            $input['email'], 
            $hashedPassword
        );
        $accountStmt->execute();
        
        $accountId = $conn->insert_id;
        
        //   
        $clientStmt = $conn->prepare("INSERT INTO client (accountId, fName, lName, contactNo) VALUES (?, ?, ?, ?)");
        $clientStmt->bind_param('isss',
            $accountId,
            $input['firstName'],
            $input['lastName'],
            $input['phoneNumber']
        );
        $clientStmt->execute();
        
        //    (notifications    )
        // $notificationStmt = $conn->prepare("INSERT INTO notifications (accountId, notificationType, title, message, priority) VALUES (?, 'general', ' !', 'Smart Travel   .     .', 'medium')");
        // $notificationStmt->bind_param('i', $accountId);
        // $notificationStmt->execute();
        
        $conn->commit();
        
        //    (     )
        // sendVerificationEmail($input['email'], $emailVerificationToken);
        
        log_activity($accountId, 'register', 'New user registered: ' . $input['email']);
        
        send_json_response([
            'success' => true, 
            'message' => ' .    .',
            'accountId' => $accountId
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'message' => '   .'], 500);
    }
}

function handleLogout() {
    session_start();
    
    if (isset($_SESSION['accountId'])) {
        log_activity($_SESSION['accountId'], 'logout', 'User logged out');
    }
    
    //     (rememberToken    )
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_destroy();
    send_json_response(['success' => true, 'message' => '.']);
}

function handleProfile() {
    session_start();
    
    if (!isset($_SESSION['accountId'])) {
        send_json_response(['success' => false, 'message' => ' .'], 401);
        return;
    }

    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //   
        $query = "SELECT a.*, c.* FROM accounts a LEFT JOIN client c ON a.accountId = c.accountId WHERE a.accountId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $_SESSION['accountId']);
        $stmt->execute();
        
        if ($user = $stmt->get_result()->fetch_assoc()) {
            unset($user['password']); //  
            send_json_response(['success' => true, 'data' => $user]);
        } else {
            send_json_response(['success' => false, 'message' => '    .'], 404);
        }
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        //  
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conn->begin_transaction();
        
        try {
            // accounts  
            if (isset($input['preferredLanguage'])) {
                $accountStmt = $conn->prepare("UPDATE accounts SET preferredLanguage = ?, updatedAt = NOW() WHERE accountId = ?");
                $accountStmt->bind_param('si', $input['preferredLanguage'], $_SESSION['accountId']);
                $accountStmt->execute();
            }
            
            // client  
            $updateFields = [];
            $params = [];
            $types = '';
            
            $allowedFields = [
                'firstName' => 's', 'lastName' => 's', 'phoneNumber' => 's', 
                'address' => 's', 'dateOfBirth' => 's', 'gender' => 's', 
                'nationality' => 's', 'emergencyContactName' => 's', 
                'emergencyContactPhone' => 's', 'dietaryRestrictions' => 's'
            ];
            
            foreach ($allowedFields as $field => $type) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                    $types .= $type;
                }
            }
            
            if (!empty($updateFields)) {
                $sql = "UPDATE client SET " . implode(', ', $updateFields) . ", updatedAt = NOW() WHERE accountId = ?";
                $params[] = $_SESSION['accountId'];
                $types .= 'i';
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }
            
            $conn->commit();
            
            log_activity($_SESSION['accountId'], 'profile_update', 'Profile updated');
            
            send_json_response(['success' => true, 'message' => ' .']);
            
        } catch (Exception $e) {
            $conn->rollback();
            send_json_response(['success' => false, 'message' => '    .'], 500);
        }
    }
}

function handleChangePassword() {
    session_start();
    
    if (!isset($_SESSION['accountId'])) {
        send_json_response(['success' => false, 'message' => ' .'], 401);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'message' => 'POST  .'], 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['currentPassword']) || !isset($input['newPassword'])) {
        send_json_response(['success' => false, 'message' => '    .'], 400);
        return;
    }

    //    
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $input['newPassword'])) {
        send_json_response(['success' => false, 'message' => '  8  , ,   .'], 400);
        return;
    }

    global $conn;
    
    //   
    $stmt = $conn->prepare("SELECT password FROM accounts WHERE accountId = ?");
    $stmt->bind_param('i', $_SESSION['accountId']);
    $stmt->execute();
    
    if ($user = $stmt->get_result()->fetch_assoc()) {
        if (password_verify($input['currentPassword'], $user['password'])) {
            //   
            $hashedPassword = password_hash($input['newPassword'], PASSWORD_DEFAULT);
            
            $updateStmt = $conn->prepare("UPDATE accounts SET password = ?, updatedAt = NOW() WHERE accountId = ?");
            $updateStmt->bind_param('si', $hashedPassword, $_SESSION['accountId']);
            
            if ($updateStmt->execute()) {
                log_activity($_SESSION['accountId'], 'password_change', 'Password changed successfully');
                send_json_response(['success' => true, 'message' => '  .']);
            } else {
                send_json_response(['success' => false, 'message' => '    .'], 500);
            }
        } else {
            send_json_response(['success' => false, 'message' => '   .'], 400);
        }
    } else {
        send_json_response(['success' => false, 'message' => '    .'], 404);
    }
}

function handleForgotPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'message' => 'POST  .'], 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || empty($input['email'])) {
        send_json_response(['success' => false, 'message' => ' .'], 400);
        return;
    }

    global $conn;
    
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE email = ? AND isActive = 1");
    $stmt->bind_param('s', $input['email']);
    $stmt->execute();
    
    if ($user = $stmt->get_result()->fetch_assoc()) {
        //   
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1  
        
        $updateStmt = $conn->prepare("UPDATE accounts SET passwordResetToken = ?, passwordResetExpires = ? WHERE accountId = ?");
        $updateStmt->bind_param('ssi', $resetToken, $resetExpires, $user['accountId']);
        $updateStmt->execute();
        
        //    
        // sendPasswordResetEmail($input['email'], $resetToken);
        
        log_activity($user['accountId'], 'password_reset_request', 'Password reset requested');
        
        send_json_response(['success' => true, 'message' => '    .']);
    } else {
        //    
        send_json_response(['success' => true, 'message' => '    .']);
    }
}

function handleResetPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'message' => 'POST  .'], 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['token']) || !isset($input['newPassword'])) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
        return;
    }

    global $conn;
    
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE passwordResetToken = ? AND passwordResetExpires > NOW() AND isActive = 1");
    $stmt->bind_param('s', $input['token']);
    $stmt->execute();
    
    if ($user = $stmt->get_result()->fetch_assoc()) {
        $hashedPassword = password_hash($input['newPassword'], PASSWORD_DEFAULT);
        
        $updateStmt = $conn->prepare("UPDATE accounts SET password = ?, passwordResetToken = NULL, passwordResetExpires = NULL, updatedAt = NOW() WHERE accountId = ?");
        $updateStmt->bind_param('si', $hashedPassword, $user['accountId']);
        
        if ($updateStmt->execute()) {
            log_activity($user['accountId'], 'password_reset', 'Password reset completed');
            send_json_response(['success' => true, 'message' => '  .']);
        } else {
            send_json_response(['success' => false, 'message' => '    .'], 500);
        }
    } else {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
}

function handleVerifyEmail() {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
        return;
    }

    global $conn;
    
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE emailVerificationToken = ? AND isActive = 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    
    if ($user = $stmt->get_result()->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE accounts SET emailVerified = 1, emailVerificationToken = NULL, updatedAt = NOW() WHERE accountId = ?");
        $updateStmt->bind_param('i', $user['accountId']);
        
        if ($updateStmt->execute()) {
            log_activity($user['accountId'], 'email_verified', 'Email verification completed');
            send_json_response(['success' => true, 'message' => '  .']);
        } else {
            send_json_response(['success' => false, 'message' => '    .'], 500);
        }
    } else {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
}

function handleRefreshToken() {
    session_start();
    
    if (isset($_SESSION['accountId'])) {
        send_json_response([
            'success' => true, 
            'isLoggedIn' => true,
            'user' => [
                'accountId' => $_SESSION['accountId'],
                'email' => $_SESSION['email'],
                'firstName' => $_SESSION['firstName'],
                'lastName' => $_SESSION['lastName'],
                'accountRole' => $_SESSION['accountRole']
            ]
        ]);
    } else {
        send_json_response(['success' => false, 'isLoggedIn' => false]);
    }
}

// log_activity  conn.php    
?>