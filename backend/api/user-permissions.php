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
$userId = $input['userId'] ?? '';

//   
if (empty($userId)) {
    send_json_response(['success' => false, 'message' => ' ID .'], 400);
}

switch ($action) {
    case 'get_permissions':
        handleGetPermissions($userId);
        break;
    case 'update_permissions':
        handleUpdatePermissions($userId, $input);
        break;
    case 'get_available_roles':
        handleGetAvailableRoles();
        break;
    default:
        send_json_response(['success' => false, 'message' => ' .'], 400);
}

//    
function handleGetPermissions($userId) {
    global $conn;
    
    try {
        //     
        $stmt = $conn->prepare("
            SELECT
                a.accountId,
                a.username,
                a.emailAddress,
                a.accountType,
                a.accountStatus,
                c.clientId,
                c.fName,
                c.lName,
                c.clientType,
                c.clientRole,
                c.seats
            FROM accounts a
            LEFT JOIN client c ON a.accountId = c.accountId
            WHERE a.accountId = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }
        
        $user = $result->fetch_assoc();
        
        //
        $permissions = [
            'accountId' => $user['accountId'],
            'username' => $user['username'],
            'email' => $user['emailAddress'],
            'accountType' => $user['accountType'],
            'accountStatus' => $user['accountStatus'],
            'clientInfo' => [
                'clientId' => $user['clientId'],
                'name' => trim($user['fName'] . ' ' . $user['lName']),
                'clientType' => $user['clientType'],
                'clientRole' => $user['clientRole'],
                'seats' => $user['seats']
            ],
            'availableAccountTypes' => ['guest', 'agent', 'employee'],
            'availableClientTypes' => ['Retailer', 'Wholeseller'],
            'availableClientRoles' => ['Head Agent', 'Sub-Agent']
        ];
        
        log_activity("User permissions retrieved for: {$userId}");
        
        send_json_response([
            'success' => true,
            'data' => $permissions
        ]);
        
    } catch (Exception $e) {
        log_activity("Get permissions error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function handleUpdatePermissions($userId, $input) {
    global $conn;
    
    $accountType = $input['accountType'] ?? '';
    $clientType = $input['clientType'] ?? '';
    $clientRole = $input['clientRole'] ?? '';
    $seats = $input['seats'] ?? 1;
    
    //  
    $validAccountTypes = ['guest', 'agent', 'employee'];
    $validClientTypes = ['Retailer', 'Wholeseller'];
    $validClientRoles = ['Head Agent', 'Sub-Agent'];
    
    if (!empty($accountType) && !in_array($accountType, $validAccountTypes)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    if (!empty($clientType) && !in_array($clientType, $validClientTypes)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    if (!empty($clientRole) && !in_array($clientRole, $validClientRoles)) {
        send_json_response(['success' => false, 'message' => '   .'], 400);
    }
    
    if (!is_numeric($seats) || $seats < 1) {
        send_json_response(['success' => false, 'message' => '  1  .'], 400);
    }
    
    try {
        $conn->begin_transaction();
        
        // accounts  
        if (!empty($accountType)) {
            $stmt = $conn->prepare("UPDATE accounts SET accountType = ?, updatedAt = NOW() WHERE accountId = ?");
            $stmt->bind_param("si", $accountType, $userId);
            $stmt->execute();
        }
        
        // client  
        $updateFields = [];
        $params = [];
        $types = '';
        
        if (!empty($clientType)) {
            $updateFields[] = "clientType = ?";
            $params[] = $clientType;
            $types .= 's';
        }
        
        if (!empty($clientRole)) {
            $updateFields[] = "clientRole = ?";
            $params[] = $clientRole;
            $types .= 's';
        }
        
        if (!empty($seats)) {
            $updateFields[] = "seats = ?";
            $params[] = $seats;
            $types .= 'i';
        }
        
        if (!empty($updateFields)) {
            $params[] = $userId;
            $types .= 'i';
            
            $query = "UPDATE client SET " . implode(', ', $updateFields) . " WHERE accountId = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
        
        $conn->commit();
        
        log_activity("User permissions updated for: {$userId}");
        
        send_json_response([
            'success' => true,
            'message' => '  .'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity("Update permissions error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//     
function handleGetAvailableRoles() {
    $roles = [
        'accountTypes' => [
            ['value' => 'guest', 'label' => ' ', 'description' => '  '],
            ['value' => 'agent', 'label' => '', 'description' => '   '],
            ['value' => 'employee', 'label' => '', 'description' => '  ']
        ],
        'clientTypes' => [
            ['value' => 'Retailer', 'label' => '', 'description' => '  '],
            ['value' => 'Wholeseller', 'label' => '', 'description' => '  ']
        ],
        'clientRoles' => [
            ['value' => 'Sub-Agent', 'label' => ' ', 'description' => ' '],
            ['value' => 'Head Agent', 'label' => ' ', 'description' => ' ']
        ]
    ];
    
    send_json_response([
        'success' => true,
        'data' => $roles
    ]);
}
?>
