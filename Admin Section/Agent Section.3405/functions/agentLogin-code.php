<?php
require "../../conn.php";
session_start();



$response = []; // Initialize response array

if (isset($_POST['login'])) {
    $email = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $flightId = isset($_POST['flightid']) ? $_POST['flightid'] : null; // Ensure $flightId is captured

    if (!empty($email) && !empty($password)) {
        // Verify account existence and fetch details
        $sqlAccount = "SELECT * FROM accounts WHERE email = ?";
        $stmtAccount = $conn->prepare($sqlAccount);
        $stmtAccount->bind_param('s', $email);
        $stmtAccount->execute();
        $resultAccount = $stmtAccount->get_result();

        if ($resultAccount->num_rows > 0) {
            $account = $resultAccount->fetch_assoc();
            $storedPassword = $account['password']; // Fetch stored password

            if ($password == $storedPassword) {
                // Check account status
                if ($account['accountStatus'] === 'active') {
                    $accountType = $account['accountType'];
                    $accountId = $account['accountId'];
                    $defaultPasswordStat = $account['defaultPasswordStat'];

                    if ($accountType === 'agent') {
                        handleLogin($accountId, 
                        'agent', 
                        "SELECT agent.*, accounts.emailAddress 
                        FROM agent 
                        JOIN accounts ON agent.accountId = accounts.accountId 
                        WHERE agent.accountId = ?", 
                        ['branchId'], 
                        $flightId, 
                        $defaultPasswordStat);

                    } elseif ($accountType === 'guest') {
                        handleLogin($accountId, 'guest', "SELECT client.*, accounts.emailAddress 
                        FROM client
                        JOIN accounts ON client.accountId = accounts.accountId 
                        WHERE client.accountId = ?", 
                        ['position', 'countryCode', 'contactNo', 'branch'], 
                        $flightId, 
                        $defaultPasswordStat);

                    } elseif ($accountType === 'employee') {
                        handleLoginEmployee($accountId, 'employee', "SELECT * FROM employee WHERE accountId = ?", ['position', 'countryCode', 'contactNo', 'branch']);
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
function handleLogin($accountId, $userType, $query, $additionalFields = [], $flightId, $defaultPasswordStat)
{
    global $conn, $response;

    // Debugging: Log input parameters
    error_log("handleLogin called with Account ID: $accountId, User Type: $userType, Flight ID: " . print_r($flightId, true));

    // Prepare statement
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("MySQL Prepare Error: " . $conn->error);
        $response['success'] = false;
        $response['message'] = "Database error. Please try again later.";
        return;
    }

    $stmt->bind_param('i', $accountId);
    $executeStatus = $stmt->execute();

    // Check execution
    if (!$executeStatus) {
        error_log("MySQL Execute Error: " . $stmt->error);
        $response['success'] = false;
        $response['message'] = "Database query failed. Please try again.";
        return;
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();

        // Debugging: Confirm fetched data
        error_log("User details fetched: " . print_r($userDetails, true));

        // Store flightId in user details if provided
        $userDetails['flightId'] = !empty($flightId) ? $flightId : null;
        error_log("Final Flight ID Assigned: " . print_r($userDetails['flightId'], true));

        // Handle session based on user type
        if ($userType === 'agent') {
            manageAgentSession($accountId, $userDetails, $userType, $flightId, $additionalFields, $defaultPasswordStat);
        } elseif ($userType === 'guest') {
            manageGuestSession($accountId, $userDetails, $userType, $flightId, $additionalFields, $defaultPasswordStat);
        }

        $response['success'] = true;
        $response['message'] = ucfirst($userType) . " login successful.";
    } else {
        error_log("No user details found for Account ID: $accountId");
        $response['success'] = false;
        $response['message'] = ucfirst($userType) . " details not found.";
    }
}

// Function to manage session for agents
function manageAgentSession($accountId, $userData, $userType, $flightId, $additionalFields, $defaultPasswordStat)
{
    global $conn;

    // session_start(); // Ensure session is started
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;

    try {
        // Check for existing session
        $session_check_stmt = $conn->prepare("SELECT session_id FROM user_sessions WHERE accountid = ?");

        if (!$session_check_stmt) {
            throw new Exception("Session check statement preparation failed: " . $conn->error);
        }

        $session_check_stmt->bind_param("i", $accountId);
        $session_check_stmt->execute();
        $session_check_result = $session_check_stmt->get_result();

        if ($session_check_result->num_rows > 0) {
            // Terminate the existing session
            $existing_session = $session_check_result->fetch_assoc();
            $existing_session_id = $existing_session['session_id'];

            $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            if (!$delete_stmt) {
                throw new Exception("Failed to prepare session delete statement: " . $conn->error);
            }
            $delete_stmt->bind_param("s", $existing_session_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Store agent details in session
        $_SESSION['agent_accountId'] = $accountId;
        $_SESSION['agent_userType'] = $userType;
        $_SESSION['agent_fName'] = $userData['fName'] ?? null;
        $_SESSION['agent_mName'] = $userData['mName'] ?? null;
        $_SESSION['agent_lName'] = $userData['lName'] ?? null;
        $_SESSION['agentId'] = $userData['agentId'] ?? null;
        $_SESSION['agentCode'] = $userData['agentCode'] ?? null;
        $_SESSION['agentRole'] = $userData['agentRole'] ?? null;
        $_SESSION['agentType'] = $userData['agentType'] ?? null;
        $_SESSION['agent_branchId'] = $userData['branchId'] ?? null;
        $_SESSION['agent_flightId'] = $flightId ?? null;
        $_SESSION['agent_emailAddress'] = $userData['emailAddress'] ?? null;
        $_SESSION['agent_timeout'] = time();

        unset($_SESSION['flightid']);

        // Store additional fields in session
        foreach ($additionalFields as $field) {
            $_SESSION['agent_' . $field] = $userData[$field] ?? null;
        }

        // Insert new session into DB
        $insert_stmt = $conn->prepare(
            "INSERT INTO user_sessions (session_id, accountid, login_time, last_activity, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$insert_stmt) {
            throw new Exception("Failed to prepare session insert statement: " . $conn->error);
        }

        $insert_stmt->bind_param("sissss", $new_session_id, $accountId, $login_time, $last_activity, $ip_address, $user_agent);
        $insert_stmt->execute();
        $insert_stmt->close();

        // Debug log
        error_log("Session successfully started for account ID: $accountId");

        // Return response
        echo json_encode([
            "success" => true,
            "accountType" => "agent",
            "flightId" => $_SESSION['agent_flightId'] ?? 'Not received',
            "defaultPasswordStat" => $defaultPasswordStat,
            "userType" => $userType
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Session Management Error: " . $e->getMessage());

        echo json_encode([
            "success" => false,
            "message" => "Session error: " . $e->getMessage()
        ]);
        exit();
    }
}


// Function to manage session for Client
function manageGuestSession($accountId, $userData, $userType, $flightId, $additionalFields, $defaultPasswordStat)
{
    global $conn;

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $login_time = date('Y-m-d H:i:s');
    $last_activity = $login_time;

    try {
        // Check for existing session
        $session_check_stmt = $conn->prepare("SELECT session_id FROM user_sessions WHERE accountid = ?");

        if (!$session_check_stmt) {
            throw new Exception("Session check statement preparation failed: " . $conn->error);
        }

        $session_check_stmt->bind_param("i", $accountId);
        $session_check_stmt->execute();
        $session_check_result = $session_check_stmt->get_result();

        if ($session_check_result->num_rows > 0) {
            // Terminate the existing session
            $existing_session = $session_check_result->fetch_assoc();
            $existing_session_id = $existing_session['session_id'];

            $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            if (!$delete_stmt) {
                throw new Exception("Failed to prepare session delete statement: " . $conn->error);
            }
            $delete_stmt->bind_param("s", $existing_session_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Store guest details in session with Client naming convention
        $_SESSION['client_accountId'] = $accountId;
        $_SESSION['client_userType'] = $userType;
        $_SESSION['client_fName'] = $userData['fName'] ?? '';
        $_SESSION['client_mName'] = $userData['mName'] ?? '';
        $_SESSION['client_lName'] = $userData['lName'] ?? '';
        $_SESSION['clientId'] = $userData['clientId'] ?? '';
        $_SESSION['clientCode'] = $userData['clientCode'] ?? '';
        $_SESSION['clientRole'] = $userData['clientRole'] ?? '';
        $_SESSION['clientType'] = $userData['clientType'] ?? '';
        $_SESSION['client_branchId'] = $userData['branchId'] ?? '';
        $_SESSION['client_flightId'] = $flightId ?? '';
        $_SESSION['client_timeout'] = time();

        unset($_SESSION['flightid']); // Ensure flight ID is only stored in session

        // Insert new session into DB
        $insert_stmt = $conn->prepare(
            "INSERT INTO user_sessions (session_id, accountid, login_time, last_activity, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$insert_stmt) {
            throw new Exception("Failed to prepare session insert statement: " . $conn->error);
        }

        $insert_stmt->bind_param("sissss", $new_session_id, $accountId, $login_time, $last_activity, $ip_address, $user_agent);
        $insert_stmt->execute();
        $insert_stmt->close();

        // Debug log
        error_log("Guest session successfully started for account ID: $accountId");

        // Return response
        echo json_encode([
            "success" => true,
            "accountType" => "guest",
            "flightId" => $_SESSION['client_flightId'] ?? 'Not received',
            "defaultPasswordStat" => $defaultPasswordStat,
            "userType" => $userType
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Session Management Error: " . $e->getMessage());

        echo json_encode([
            "success" => false,
            "message" => "Session error: " . $e->getMessage()
        ]);
        exit();
    }
}


// ================================================================== //

function handleLoginEmployee($accountId, $userType, $query, $additionalFields = [])
{
    global $conn, $response;

    // Debugging: Log input parameters
    error_log("handleLoginEmployee called with Account ID: $accountId, User Type: $userType");

    // Ensure the userType is 'employee' before proceeding
    if ($userType !== 'employee') {
        $response['success'] = false;
        $response['message'] = "Invalid user type. Only employees are allowed to log in.";
        return;
    }

    // Prepare statement
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("MySQL Prepare Error: " . $conn->error);
        $response['success'] = false;
        $response['message'] = "Database error. Please try again later.";
        return;
    }

    $stmt->bind_param('i', $accountId);
    $executeStatus = $stmt->execute();

    // Check execution
    if (!$executeStatus) {
        error_log("MySQL Execute Error: " . $stmt->error);
        $response['success'] = false;
        $response['message'] = "Database query failed. Please try again.";
        return;
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();

        // Debugging: Confirm fetched data
        error_log("User details fetched: " . print_r($userDetails, true));

        // Handle employee session
        manageEmployeeSession($accountId, $userDetails, $userType, $additionalFields);

        $response['success'] = true;
        $response['message'] = "Employee login successful.";
    } else {
        error_log("No user details found for Account ID: $accountId");
        $response['success'] = false;
        $response['message'] = "Employee details not found.";
    }
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
    $_SESSION['employee_fName'] = $userData['fName'] ?? null;
    $_SESSION['employee_mName'] = $userData['mName'] ?? null;
    $_SESSION['employee_lName'] = $userData['lName'] ?? null;
    $_SESSION['employee_employeeId'] = $userData['employeeId'] ?? null;
    $_SESSION['employee_accountId'] = $accountId;
    $_SESSION['employee_emailAddress'] = $userData['emailAddress'] ?? null;
    $_SESSION['employee_position'] = $userData['position'] ?? null;
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

header('Content-Type: application/json');
