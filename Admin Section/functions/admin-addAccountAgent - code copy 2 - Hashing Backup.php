<?php

session_start();
require "../../conn.php"; // Move up to the parent directory

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize response array
$response = array();

// Start output buffering to prevent unexpected output
ob_start();

// Start a database transaction
mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve and sanitize form data
        $fName = $_POST['firstName'];
        $lName = $_POST['lastName'];
        $mName = $_POST['middleName'];
        $Suffix = $_POST['Suffix'];
        $countryCode = $_POST['countryCode'];
        $contactNo = $_POST['contactNo'];
        $password = $_POST['password'];
        $branchId = $_POST['branchId'];
        $accountType = $_POST['accountType'];
        $agentType = $_POST['agentType'];
        
        $agentCode = "BU" . $_POST['branchId'];

        // Validation: Ensure required fields are not empty
        if (empty($fName) || empty($password) || empty($branchId)) {
            throw new Exception("Required fields cannot be empty.");
        }

        // Hash the password before storing it
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($accountType === 'agent') {
            $agentRole = $_POST['agentRole'];
        
            // Start transaction
            mysqli_begin_transaction($conn);
        
            try {
                // Validate BranchId (must not be empty or invalid)
                if (empty($branchId) || !is_numeric($branchId)) {
                    throw new Exception("Invalid Branch ID.");
                }
        
                // Fetch the latest agent count for the specific BranchId
                $sql_count_agents = "SELECT COUNT(*) AS agentCount FROM agent WHERE branchId = ?";

                $stmt_count = mysqli_prepare($conn, $sql_count_agents);
                if (!$stmt_count) {
                    throw new Exception("Error preparing count query: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt_count, "i", $branchId);
                mysqli_stmt_execute($stmt_count);
                $result_count = mysqli_stmt_get_result($stmt_count);
                if (!$result_count) {
                    throw new Exception("Error executing count query: " . mysqli_error($conn));
                }
        
                $count_row = mysqli_fetch_assoc($result_count);
                $agentCount = $count_row['agentCount'] ?? 0;
        
                // Increment agent count
                $nextId = $agentCount + 1;
        
                // Maintain A00 format (A001, A002, etc.)
                if ($nextId < 10) {
                    $newAgentId = 'A00' . $nextId; // A001 - A009
                } elseif ($nextId < 100) {
                    $newAgentId = 'A0' . $nextId;  // A010 - A099
                } else {
                    $newAgentId = 'A' . $nextId;   // A100 and beyond
                }
        
                $agentUsername = $agentCode . '-' . $newAgentId;
        
                // Insert into accounts table
                $sql_account = "INSERT INTO accounts (email, password, otp, accountStatus, accountType, createdAt) 
                                VALUES (?, ?, '', 'active', ?, NOW())";
                $stmt = mysqli_prepare($conn, $sql_account);
                if (!$stmt) {
                    throw new Exception("Error preparing account insert: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, "sss", $agentUsername, $hashed_password, $accountType);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error inserting into accounts table: " . mysqli_error($conn));
                }
        
                $accountId = mysqli_insert_id($conn);
                if (!$accountId) {
                    throw new Exception("Failed to retrieve inserted Account ID.");
                }
        
                // Insert into agent table
                $sql_agent = "INSERT INTO agent (agentId, agentCode, accountId, branchId, fName, lName, mName, countryCode, contactNo, agentType, agentRole, comissionRate, seats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 0)";
                $stmt_agent = mysqli_prepare($conn, $sql_agent);
                if (!$stmt_agent) {
                    throw new Exception("Error preparing agent insert: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_agent, "ssissssssss", $agentUsername, $agentCode, $accountId, $branchId, $fName, $lName, $mName, $countryCode, $contactNo, $agentType, $agentRole);
                if (!mysqli_stmt_execute($stmt_agent)) {
                    throw new Exception("Error inserting into agent table: " . mysqli_error($conn));
                }
        
                // Commit transaction if everything is successful
                mysqli_commit($conn);
        
                $response = ['status' => 'success', 'message' => "User added successfully with Agent Code: $agentUsername!"];
        
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        

        if ($accountType === 'guest') {
            $clientRole = $_POST['clientRole'];
        
            // Start transaction
            mysqli_begin_transaction($conn);
        
            try {
                // Validate BranchId (must not be empty or invalid)
                if (empty($branchId) || !is_numeric($branchId)) {
                    throw new Exception("Invalid Branch ID.");
                }
        
                // Fetch the latest guest count for the specific BranchId
                $sql_count_guests = "SELECT COUNT(*) AS guestCount FROM client WHERE branchId = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count_guests);
                if (!$stmt_count) {
                    throw new Exception("Error preparing count query: " . mysqli_error($conn));
                }
        
                mysqli_stmt_bind_param($stmt_count, "i", $branchId);
                mysqli_stmt_execute($stmt_count);
                $result_count = mysqli_stmt_get_result($stmt_count);
                if (!$result_count) {
                    throw new Exception("Error executing count query: " . mysqli_error($conn));
                }
        
                $count_row = mysqli_fetch_assoc($result_count);
                $guestCount = $count_row['guestCount'] ?? 0;
        
                // Increment guest count
                $nextId = $guestCount + 1;
        
                // Maintain C00 format (C001, C002, etc.)
                if ($nextId < 10) {
                    $newClientId = 'C00' . $nextId; // C001 - C009
                } elseif ($nextId < 100) {
                    $newClientId = 'C0' . $nextId;  // C010 - C099
                } else {
                    $newClientId = 'C' . $nextId;   // C100 and beyond
                }
        
                $clientUsername = $agentCode . '-' . $newClientId;
        
                // Insert into accounts table
                $sql_account = "INSERT INTO accounts (email, password, otp, accountStatus, accountType, createdAt) 
                                VALUES (?, ?, '', 'active', ?, NOW())";
                $stmt = mysqli_prepare($conn, $sql_account);
                if (!$stmt) {
                    throw new Exception("Error preparing account insert: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, "sss", $clientUsername, $hashed_password, $accountType);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error inserting into accounts table: " . mysqli_error($conn));
                }
        
                $accountId = mysqli_insert_id($conn);
                if (!$accountId) {
                    throw new Exception("Failed to retrieve inserted Account ID.");
                }
        
                // Insert into client table
                $sql_guest = "INSERT INTO client (clientId, clientCode, accountId, branchId, companyId, fName, lName, mName, countryCode, contactNo, clientType, clientRole, seats) 
                              VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt_guest = mysqli_prepare($conn, $sql_guest);
                if (!$stmt_guest) {
                    throw new Exception("Error preparing client insert: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_guest, "ssiisssssss",  $clientUsername, $agentCode, $accountId, $branchId, $fName, $lName, $mName, $countryCode, $contactNo, $agentType, $clientRole);
                if (!mysqli_stmt_execute($stmt_guest)) {
                    throw new Exception("Error inserting into client table: " . mysqli_error($conn));
                }
        
                // Commit transaction if everything is successful
                mysqli_commit($conn);
        
                $response = ['status' => 'success', 'message' => "User added successfully with Client ID: $clientUsername!"];
        
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
    } else {
        throw new Exception('Invalid request method.');
    }
} catch (Exception $e) {
    mysqli_rollback($conn); // Rollback any changes on error
    ob_end_clean(); // Clear any buffered output
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Send the response as JSON
echo json_encode($response);

// Close the database connection
mysqli_close($conn);
