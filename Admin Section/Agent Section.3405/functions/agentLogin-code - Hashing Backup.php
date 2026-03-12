<?php
require "../../conn.php";
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$response = []; // Initialize response array

if (isset($_POST['login'])) {
    $email = $_POST['username'];
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Verify account existence and fetch details
        $sqlAccount = "SELECT * FROM accounts WHERE email = ?";
        $stmtAccount = $conn->prepare($sqlAccount);
        $stmtAccount->bind_param('s', $email);
        $stmtAccount->execute();
        $resultAccount = $stmtAccount->get_result();

        if ($resultAccount->num_rows > 0) {
            $account = $resultAccount->fetch_assoc();

            // Verify password using password_verify
            if (password_verify($password, $account['password'])) {
                // Check account status
                if ($account['accountStatus'] === 'active') {
                    $accountType = $account['accountType'];
                    $accountId = $account['accountId'];

                    if ($accountType === 'agent') {
                        handleLogin($accountId, 'agent', "SELECT * FROM agent WHERE accountId = ?", ['branchId']);
                    } elseif ($accountType === 'employee') {
                        handleLogin($accountId, 'employee', "SELECT * FROM employee WHERE accountId = ?", ['position', 'countryCode', 'contactNo', 'branch']);
                    } elseif ($accountType === 'guest') {
                        handleLogin($accountId, 'guest', "SELECT * FROM client WHERE accountId = ?", ['position', 'countryCode', 'contactNo', 'branch']);
                    } else {
                        $response['success'] = false;
                        $response['message'] = "Invalid account type.";
                    }

                    // Add accountType to the response
                    $response['accountType'] = $accountType;
                } else {
                    $response['success'] = false;
                    $response['message'] = "Your account is inactive. Please contact the administrator.";
                }
            } else {
                $response['success'] = false;
                $response['message'] = "Incorrect Username/Password";
            }
        } else {
            $response['success'] = false;
            $response['message'] = "Incorrect Username/Password";
        }

        $stmtAccount->close();
    } else {
        $response['success'] = false;
        $response['message'] = "Please fill in both fields.";
    }
} else {
    $response['success'] = false;
    $response['message'] = "Invalid request.";
}

header('Content-Type: application/json');
echo json_encode($response);

// Function to handle login and session management
function handleLogin($accountId, $userType, $query, $additionalFields = []) {
    global $conn, $response;

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();

        if ($userType === 'agent') {
            manageAgentSession($accountId, $userDetails, $userType, $additionalFields);
        } elseif ($userType === 'employee') {
            manageEmployeeSession($accountId, $userDetails, $userType, $additionalFields);
        } elseif ($userType === 'guest') {
            manageGuestSession($accountId, $userDetails, $userType, $additionalFields);
        }

        $response['success'] = true;
        $response['message'] = ucfirst($userType) . " login successful.";
    } else {
        $response['success'] = false;
        $response['message'] = ucfirst($userType) . " details not found.";
    }
}


// Function to manage session for agents
function manageAgentSession($accountId, $userData, $userType, $additionalFields)
{
    global $conn;

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;

    // Check if there's an existing session for this account
    $session_check_stmt = $conn->prepare("SELECT session_id FROM user_sessions WHERE accountid = ?");
    $session_check_stmt->bind_param("i", $accountId);
    $session_check_stmt->execute();
    $session_check_result = $session_check_stmt->get_result();

    if ($session_check_result->num_rows > 0) {
        // Terminate the existing session if one is found
        $existing_session = $session_check_result->fetch_assoc();
        $existing_session_id = $existing_session['session_id'];

        // Delete the old session
        $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $delete_stmt->bind_param("s", $existing_session_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // Regenerate session ID to ensure session security
    session_regenerate_id(true);
    $new_session_id = session_id();

    $_SESSION['accountId'] = $accountId;
    $_SESSION['userType'] = $userType;
    $_SESSION['fName'] = $userData['fName'] ?? '';
    $_SESSION['mName'] = $userData['mName'] ?? '';
    $_SESSION['lName'] = $userData['lName'] ?? '';
    $_SESSION['agentId'] = $userData['agentId'] ?? '';  // Added agent_id to session
    $_SESSION['agentCode'] = $userData['agentCode'] ?? '';  // Added agent_Code to session
    $_SESSION['agentRole'] = $userData['agentRole'] ?? '';  // Added agent_agentRole to session
    $_SESSION['agentType'] = $userData['agentType'] ?? '';  // Added agent_agentType to session
    $_SESSION['branchId'] = $userData['branchId'] ?? '';  // Added branch_id to session
    $_SESSION['timeout'] = time();

    // Store additional fields in session if provided
    // foreach ($additionalFields as $field) {
    //     $_SESSION['agent_' . $field] = $userData[$field] ?? null;
    // }

    // Insert new session into the user_sessions table
    $insert_stmt = $conn->prepare(
        "INSERT INTO user_sessions (session_id, accountid, login_time, last_activity, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert_stmt->bind_param("sissss", $new_session_id, $accountId, $login_time, $last_activity, $ip_address, $user_agent);
    $insert_stmt->execute();
    $insert_stmt->close();
}

// Function to manage session for employees
function manageEmployeeSession($accountId, $userData, $userType, $additionalFields)
{
    global $conn;

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;

    // Check if there's an existing session for this account
    $session_check_stmt = $conn->prepare("SELECT session_id FROM user_sessions WHERE accountid = ?");
    $session_check_stmt->bind_param("i", $accountId);
    $session_check_stmt->execute();
    $session_check_result = $session_check_stmt->get_result();

    if ($session_check_result->num_rows > 0) {
        // Terminate the existing session if one is found
        $existing_session = $session_check_result->fetch_assoc();
        $existing_session_id = $existing_session['session_id'];

        // Delete the old session
        $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $delete_stmt->bind_param("s", $existing_session_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // Regenerate session ID to ensure session security
    session_regenerate_id(true);
    $new_session_id = session_id();

    // Store session data specifically for employee
    $_SESSION['employee_accountId'] = $accountId;
    $_SESSION['employee_userType'] = $userType;
    $_SESSION['employee_fName'] = $userData['fName'] ?? '';
    $_SESSION['employee_mName'] = $userData['mName'] ?? '';
    $_SESSION['employee_lName'] = $userData['lName'] ?? '';
    $_SESSION['employee_employeeId'] = $userData['employeeId'] ?? '';  
    $_SESSION['employee_accountId'] = $accountId;
    $_SESSION['employee_position'] = $userData['position'] ?? '';  
    $_SESSION['employee_timeout'] = time();

    // Store additional fields in session if provided
    foreach ($additionalFields as $field) {
        $_SESSION['employee_' . $field] = $userData[$field] ?? null;
    }

    // Insert new session into the user_sessions table
    $insert_stmt = $conn->prepare(
        "INSERT INTO user_sessions (session_id, accountid, login_time, last_activity, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert_stmt->bind_param("sissss", $new_session_id, $accountId, $login_time, $last_activity, $ip_address, $user_agent);
    $insert_stmt->execute();
    $insert_stmt->close();
}

function manageGuestSession($accountId, $userData, $userType, $additionalFields)
{
    global $conn;

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;

    // Check if there's an existing session for this account
    $session_check_stmt = $conn->prepare("SELECT session_id FROM user_sessions WHERE accountid = ?");
    $session_check_stmt->bind_param("i", $accountId);
    $session_check_stmt->execute();
    $session_check_result = $session_check_stmt->get_result();

    if ($session_check_result->num_rows > 0) {
        // Terminate the existing session if one is found
        $existing_session = $session_check_result->fetch_assoc();
        $existing_session_id = $existing_session['session_id'];

        // Delete the old session
        $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $delete_stmt->bind_param("s", $existing_session_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // Regenerate session ID to ensure session security
    session_regenerate_id(true);
    $new_session_id = session_id();

    $_SESSION['accountId'] = $accountId;
    $_SESSION['userType'] = $userType;
    $_SESSION['fName'] = $userData['fName'] ?? '';
    $_SESSION['mName'] = $userData['mName'] ?? '';
    $_SESSION['lName'] = $userData['lName'] ?? '';
    $_SESSION['clientId'] = $userData['clientId'] ?? '';  // Added agent_id to session
    $_SESSION['clientCode'] = $userData['clientCode'] ?? '';  // Added agent_Code to session
    $_SESSION['clientRole'] = $userData['clientRole'] ?? '';  // Added agent_agentRole to session
    $_SESSION['clientType'] = $userData['clientType'] ?? '';  // Added agent_agentType to session
    $_SESSION['branchId'] = $userData['branchId'] ?? '';  // Added branch_id to session
    $_SESSION['timeout'] = time();

    // Store additional fields in session if provided
    // foreach ($additionalFields as $field) {
    //     $_SESSION['employee_' . $field] = $userData[$field] ?? null;
    // }

    // Insert new session into the user_sessions table
    $insert_stmt = $conn->prepare(
        "INSERT INTO user_sessions (session_id, accountid, login_time, last_activity, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert_stmt->bind_param("sissss", $new_session_id, $accountId, $login_time, $last_activity, $ip_address, $user_agent);
    $insert_stmt->execute();
    $insert_stmt->close();
}

?>