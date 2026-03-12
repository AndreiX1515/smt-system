<?php

session_start();
require "../../conn.php"; // Move up to the parent directory

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize response array
$response = array();

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
    $position = $_POST['empPosition'];

    // Validation: Ensure required fields are not empty
    if (empty($fName) || empty($password) || empty($branchId)) {
        $response['status'] = 'error';
        $response['message'] = 'Required fields cannot be empty.';
        echo json_encode($response);
        exit();
    }

    // Hash the password before storing it
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Fetch employee count
    $sql_count = "SELECT COUNT(*) AS total FROM employee";
    $result_count = mysqli_query($conn, $sql_count);
    $row_count = mysqli_fetch_assoc($result_count);
    $nextId = $row_count['total'] + 1;

    // Format the new employeeId
    $newEmployeeId = sprintf("E%03d", $nextId);
    $empUsername = 'SMT-' . $newEmployeeId;

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Insert into accounts table
        $sql_account = "INSERT INTO accounts (email, password, otp, accountStatus, accountType, createdAt) VALUES (?, ?, '', 'active', 'employee', NOW())";
        $stmt = mysqli_prepare($conn, $sql_account);
        mysqli_stmt_bind_param($stmt, "ss", $empUsername, $hashed_password);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error inserting into accounts table: " . mysqli_error($conn));
        }

        $accountId = mysqli_insert_id($conn);

        // Insert employee details
        $sql_employee = "INSERT INTO employee (employeeId, accountId, fName, lName, mName, position, countryCode, contactNo, branch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_employee = mysqli_prepare($conn, $sql_employee);
        mysqli_stmt_bind_param($stmt_employee, "sisssssss", $empUsername, $accountId, $fName, $lName, $mName, $position, $countryCode, $contactNo, $branchId);

        if (!mysqli_stmt_execute($stmt_employee)) {
            throw new Exception("Error inserting into employees table: " . mysqli_error($conn));
        }

        // Commit transaction
        mysqli_commit($conn);

        $response['status'] = 'success';
        $response['message'] = "Employee added successfully with ID: $empUsername!";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }

    // Close statements
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmt_employee);
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

// Send the response as JSON
echo json_encode($response);

// Close the database connection
mysqli_close($conn);

?>
