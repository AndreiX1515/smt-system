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

$name = sanitize_input($input['name'] ?? '');
$phone = sanitize_input($input['phone'] ?? '');

//   
if (empty($name) || empty($phone)) {
    send_json_response(['success' => false, 'message' => '   .'], 400);
}

//    (,  )
$phone = preg_replace('/[^0-9]/', '', $phone);
//  : +63/63/0/       
$phoneNoCc = $phone;
if (strpos($phoneNoCc, '63') === 0) {
    $phoneNoCc = substr($phoneNoCc, 2);
}
if (strpos($phoneNoCc, '0') === 0) {
    $phoneNoCc = ltrim($phoneNoCc, '0');
}
$phoneWithCc = '63' . $phoneNoCc;

try {
    //   : accounts.emailAddress/email, client.contactNo/phone 
    $accCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM accounts");
    while ($colRes && ($c = $colRes->fetch_assoc())) $accCols[strtolower($c['Field'])] = $c['Field'];
    $emailCol = $accCols['emailaddress'] ?? ($accCols['email'] ?? 'emailAddress');

    $hasClient = false;
    $t = $conn->query("SHOW TABLES LIKE 'client'");
    $hasClient = ($t && $t->num_rows > 0);
    if (!$hasClient) {
        send_json_response(['success' => false, 'message' => 'No account matches the information you entered.'], 404);
    }

    $clientCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM client");
    while ($cRes && ($c = $cRes->fetch_assoc())) $clientCols[strtolower($c['Field'])] = $c['Field'];
    $fCol = $clientCols['fname'] ?? null;
    $lCol = $clientCols['lname'] ?? null;
    $contactCol = $clientCols['contactno'] ?? ($clientCols['phoneno'] ?? ($clientCols['phone'] ?? null));
    if (!$contactCol) {
        send_json_response(['success' => false, 'message' => 'No account matches the information you entered.'], 404);
    }

    //    
    // - contactNo   (+63, ,  )   
    // -  63 /   
    // -  /   
    $nameNorm = preg_replace('/\s+/', '', mb_strtolower((string)$name, 'UTF-8'));

    $selectNameExpr = "'' AS fName, '' AS lName";
    if ($fCol) $selectNameExpr = "c.`{$fCol}` AS fName, " . ($lCol ? "c.`{$lCol}` AS lName" : "'' AS lName");
    $fullNameExpr = $fCol
        ? "LOWER(REPLACE(CONCAT(COALESCE(TRIM(c.`{$fCol}`),''), COALESCE(TRIM(" . ($lCol ? "c.`{$lCol}`" : "''") . "),'')), ' ', ''))"
        : "''";

    $stmt = $conn->prepare("
        SELECT a.accountId, a.username, a.`{$emailCol}` AS emailAddress, {$selectNameExpr}, c.`{$contactCol}` AS contactNo
        FROM accounts a
        JOIN client c ON a.accountId = c.accountId
        WHERE {$fullNameExpr} = ?
          AND (
            REPLACE(REPLACE(REPLACE(c.`{$contactCol}`, '-', ''), ' ', ''), '+', '') = ?
            OR REPLACE(REPLACE(REPLACE(c.`{$contactCol}`, '-', ''), ' ', ''), '+', '') = ?
          )
        LIMIT 1
    ");
    $stmt->bind_param("sss", $nameNorm, $phoneNoCc, $phoneWithCc);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        //   
        $email = $user['emailAddress'];
        $maskedEmail = maskEmail($email);
        
        //  
        log_activity("ID  : {$name} ({$phone}) -> {$email}");
        
        send_json_response([
            'success' => true,
            'message' => 'ID found.',
            'data' => [
                'email' => $email,
                'maskedEmail' => $maskedEmail,
                'username' => $user['username']
            ]
        ]);
    } else {
        send_json_response(['success' => false, 'message' => 'No account matches the information you entered.'], 404);
    }
    
} catch (Exception $e) {
    log_activity("ID  : " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'A server error occurred.'], 500);
}

//   
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }
    
    $username = $parts[0];
    $domain = $parts[1];
    
    // : '@'  " "   
    $len = strlen($username);
    if ($len <= 3) {
        return $username . '@' . $domain;
    }
    $maskedUsername = substr($username, 0, 3) . str_repeat('*', max(0, $len - 3));
    return $maskedUsername . '@' . $domain;
}
?>
