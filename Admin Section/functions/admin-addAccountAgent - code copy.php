


<?php

// Step 1: Start session and include database connection
session_start();
require "../../conn.php";

// Step 2: Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Step 3: Initialize response array
$response = array();

// Step 4: Start output buffering to prevent unexpected output
ob_start();

// Step 5: Start a database transaction to maintain data integrity
mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Step 6: Retrieve and sanitize form data
        $fName = $_POST['firstName'];
        $lName = $_POST['lastName'];
        $mName = $_POST['middleName'];
        $suffix = $_POST['Suffix'];
        $countryCode = $_POST['countryCode'];
        $contactNo = $_POST['contactNo'];
        $password = $_POST['password'];
        $branchId = $_POST['branchId'];
        $accountType = $_POST['accountType'];
        $agentType = $_POST['agentType'];
        $agentRole = $_POST['agentRole'];
        $agentCode = "BU" . $branchId;

        // Step 7: Validate required fields
        if (empty($fName) || empty($lName) || empty($password) || empty($branchId)) {
            throw new Exception("Required fields cannot be empty.");
        }

        // Step 8: Hash the password before storing it
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Step 9: Check account type
        if ($accountType === 'agent') {
            // Step 10: Fetch the latest agentId for the given branchId
            $sql_max_id = "SELECT MAX(CAST(SUBSTRING(agentId, 5) AS UNSIGNED)) AS maxId FROM agent WHERE branchId = ?";
            $stmt_max_id = mysqli_prepare($conn, $sql_max_id);
            mysqli_stmt_bind_param($stmt_max_id, "i", $branchId);
            mysqli_stmt_execute($stmt_max_id);
            $result_max_id = mysqli_stmt_get_result($stmt_max_id);

            if (!$result_max_id) {
                throw new Exception("Error fetching max agent ID: " . mysqli_error($conn));
            }

            $max_id_row = mysqli_fetch_assoc($result_max_id);
            $nextId = ($max_id_row['maxId'] !== null) ? $max_id_row['maxId'] + 1 : 1;
            $newAgentId = sprintf('%s-A%03d', $agentCode, $nextId);

            // Step 11: Insert into accounts table
            $sql_account = "INSERT INTO accounts (email, password, otp, accountStatus, accountType, createdAt) 
                            VALUES (?, ?, '', 'active', ?, NOW())";
            $stmt_account = mysqli_prepare($conn, $sql_account);
            mysqli_stmt_bind_param($stmt_account, "sss", $newAgentId, $hashed_password, $accountType);

            if (!mysqli_stmt_execute($stmt_account)) {
                throw new Exception("Error inserting into accounts table: " . mysqli_error($conn));
            }

            $accountId = mysqli_insert_id($conn);

            // Step 12: Insert into agent table
            $sql_agent = "INSERT INTO agent (agentId, agentCode, accountId, branchId, fName, lName, mName, countryCode, contactNo, agentType, agentRole, comissionRate, seats)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 0)";
            $stmt_agent = mysqli_prepare($conn, $sql_agent);
            mysqli_stmt_bind_param($stmt_agent, "ssissssssss", $newAgentId, $agentCode, $accountId, $branchId, $fName, $lName, $mName, $countryCode, $contactNo, $agentType, $agentRole);

            if (!mysqli_stmt_execute($stmt_agent)) {
                throw new Exception("Error inserting into agent table: " . mysqli_error($conn));
            }

            // Step 13: Commit transaction
            mysqli_commit($conn);
            $response = ['status' => 'success', 'message' => "User added successfully with Agent Code: $newAgentId!"];
        }
        // Logic for guest account type
        elseif ($accountType === 'guest') {
            // Validate if Agent Code exists
            $sql_validate_agent = "SELECT COUNT(*) AS count FROM agent WHERE agentCode = ? AND branchId = ?";
            $stmt_validate_agent = mysqli_prepare($conn, $sql_validate_agent);

            if (!$stmt_validate_agent) {
                throw new Exception("Prepare statement failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt_validate_agent, "si", $agentCode, $branchId);
            mysqli_stmt_execute($stmt_validate_agent);
            $result_validate_agent = mysqli_stmt_get_result($stmt_validate_agent);
            $row_validate_agent = mysqli_fetch_assoc($result_validate_agent);

            if ($row_validate_agent['count'] == 0) {
                throw new Exception("Error: Agent Code or Branch ID not found.");
            }

            // Fetch the latest client ID for the given branchId
            $sql_max_id = "SELECT MAX(CAST(SUBSTRING(clientId, LOCATE('-', clientId) + 1) AS UNSIGNED)) AS maxId 
                   FROM client 
                   WHERE branchId = ?";

            $stmt_max_id = mysqli_prepare($conn, $sql_max_id);

            if (!$stmt_max_id) {
                throw new Exception("Prepare statement failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt_max_id, "i", $branchId);
            mysqli_stmt_execute($stmt_max_id);
            $result_max_id = mysqli_stmt_get_result($stmt_max_id);
            $max_id_row = mysqli_fetch_assoc($result_max_id);

            // Determine next client ID based on branch
            $nextId = ($max_id_row['maxId'] !== null) ? $max_id_row['maxId'] + 1 : 1;
            $newClientId = ($nextId < 10) ? 'C00' . $nextId : (($nextId < 100) ? 'C0' . $nextId : 'C' . $nextId);
            $clientUsername = $agentCode . '-' . $newClientId;

            // Insert into accounts table
            $sql_account = "INSERT INTO accounts (email, password, otp, accountStatus, accountType, createdAt) 
                    VALUES (?, ?, '', 'active', ?, NOW())";
            $stmt_account = mysqli_prepare($conn, $sql_account);

            if (!$stmt_account) {
                throw new Exception("Prepare statement failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt_account, "sss", $clientUsername, $hashed_password, $accountType);

            if (!mysqli_stmt_execute($stmt_account)) {
                throw new Exception("Error inserting into accounts table: " . mysqli_error($conn));
            }

            $accountId = mysqli_insert_id($conn);

            // Insert into client table
            $sql_guest = "INSERT INTO client (clientId, clientCode, accountId, branchId, fName, lName, mName, countryCode, contactNo, clientType, clientRole)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_guest = mysqli_prepare($conn, $sql_guest);

            if (!$stmt_guest) {
                throw new Exception("Prepare statement failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt_guest, "ssiisssssss", $newClientId, $agentCode, $accountId, $branchId, $fName, $lName, $mName, $countryCode, $contactNo, $agentType, $clientRole);

            if (!mysqli_stmt_execute($stmt_guest)) {
                throw new Exception("Error inserting into client table: " . mysqli_error($conn));
            }

            // Commit transaction
            mysqli_commit($conn);
            $response = ['status' => 'success', 'message' => "User added successfully with Client ID: $newClientId!"];
        }
        
    } else {
        throw new Exception('Invalid request method.');
    }
} catch (Exception $e) {
    // Step 14: Rollback transaction in case of error
    mysqli_rollback($conn);
    ob_end_clean(); // Clear any buffered output
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Step 15: Send JSON response
echo json_encode($response);

// Step 16: Close database connection
mysqli_close($conn);
