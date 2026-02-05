<?php
/**
 *    
 * (branch) (agent)   
 */

require __DIR__ . '/../../../backend/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>  </title>
    <style>
        body {
            font-family: 'Noto Sans KR', sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
        }
        button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>  </h1>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init'])) {
            try {
                $conn->begin_transaction();
                
                // 1. (branch)      
                $branchCheck = $conn->query("SHOW TABLES LIKE 'branch'");
                if ($branchCheck && $branchCheck->num_rows > 0) {
                    //    
                    $existingBranches = $conn->query("SELECT COUNT(*) as count FROM branch");
                    $count = $existingBranches ? $existingBranches->fetch_assoc()['count'] : 0;
                    
                    if ($count == 0) {
                        //   
                        $columnsCheck = $conn->query("SHOW COLUMNS FROM branch");
                        $columns = [];
                        while ($col = $columnsCheck->fetch_assoc()) {
                            $columns[] = $col['Field'];
                        }
                        
                        //   
                        $sampleBranches = [
                            [' ', '   123', '02-1234-5678', 'seoul@smarttravel.com'],
                            [' ', '   456', '051-1234-5678', 'busan@smarttravel.com'],
                            [' ', '   789', '064-1234-5678', 'jeju@smarttravel.com'],
                            [' ', '   321', '032-1234-5678', 'incheon@smarttravel.com'],
                            [' ', '   654', '053-1234-5678', 'daegu@smarttravel.com']
                        ];
                        
                        foreach ($sampleBranches as $branch) {
                            $fields = ['branchName'];
                            $values = [$branch[0]];
                            $placeholders = '?';
                            $types = 's';
                            
                            // address   
                            if (in_array('address', $columns)) {
                                $fields[] = 'address';
                                $values[] = $branch[1];
                                $placeholders .= ',?';
                                $types .= 's';
                            }
                            
                            // phone   
                            if (in_array('phone', $columns) || in_array('phoneNumber', $columns)) {
                                $phoneCol = in_array('phone', $columns) ? 'phone' : 'phoneNumber';
                                $fields[] = $phoneCol;
                                $values[] = $branch[2];
                                $placeholders .= ',?';
                                $types .= 's';
                            }
                            
                            // email   
                            if (in_array('email', $columns) || in_array('emailAddress', $columns)) {
                                $emailCol = in_array('email', $columns) ? 'email' : 'emailAddress';
                                $fields[] = $emailCol;
                                $values[] = $branch[3];
                                $placeholders .= ',?';
                                $types .= 's';
                            }
                            
                            // createdAt   
                            if (in_array('createdAt', $columns)) {
                                $fields[] = 'createdAt';
                                $values[] = date('Y-m-d H:i:s');
                                $placeholders .= ',?';
                                $types .= 's';
                            }
                            
                            $sql = "INSERT INTO branch (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param($types, ...$values);
                            $stmt->execute();
                            $stmt->close();
                        }
                        
                        echo '<div class="message success">✅ ' . count($sampleBranches) . '  .</div>';
                    } else {
                        echo '<div class="message info">ℹ️  ' . $count . '  .</div>';
                    }
                } else {
                    echo '<div class="message error">❌ branch   .</div>';
                }
                
                // 2. (agent)   
                $agentCheck = $conn->query("SHOW TABLES LIKE 'agent'");
                $accountsCheck = $conn->query("SHOW TABLES LIKE 'accounts'");
                
                if ($agentCheck && $agentCheck->num_rows > 0 && $accountsCheck && $accountsCheck->num_rows > 0) {
                    //    
                    $existingAgents = $conn->query("SELECT COUNT(*) as count FROM agent");
                    $agentCount = $existingAgents ? $existingAgents->fetch_assoc()['count'] : 0;
                    
                    if ($agentCount == 0) {
                        //  ID 
                        $branchesResult = $conn->query("SELECT branchId FROM branch LIMIT 5");
                        $branchIds = [];
                        if ($branchesResult) {
                            while ($row = $branchesResult->fetch_assoc()) {
                                $branchIds[] = $row['branchId'];
                            }
                        }
                        
                        if (count($branchIds) > 0) {
                            // agent   
                            $agentColumnsCheck = $conn->query("SHOW COLUMNS FROM agent");
                            $agentColumns = [];
                            while ($col = $agentColumnsCheck->fetch_assoc()) {
                                $agentColumns[] = $col['Field'];
                            }
                            
                            // accounts   
                            $accountColumnsCheck = $conn->query("SHOW COLUMNS FROM accounts");
                            $accountColumns = [];
                            while ($col = $accountColumnsCheck->fetch_assoc()) {
                                $accountColumns[] = $col['Field'];
                            }
                            
                            //   
                            $sampleAgents = [
                                ['', '', '', 'agent1@smarttravel.com', 'agent123', $branchIds[0] ?? 1],
                                ['', '', '', 'agent2@smarttravel.com', 'agent123', $branchIds[1] ?? 1],
                                ['', '', '', 'agent3@smarttravel.com', 'agent123', $branchIds[2] ?? 1],
                                ['', '', '', 'agent4@smarttravel.com', 'agent123', $branchIds[3] ?? 1],
                                ['', '', '', 'agent5@smarttravel.com', 'agent123', $branchIds[4] ?? 1]
                            ];
                            
                            foreach ($sampleAgents as $idx => $agent) {
                                // accounts   
                                $accountFields = ['emailAddress', 'password', 'accountType', 'accountStatus'];
                                $accountValues = [$agent[3], $agent[4], 'agent', 'active'];
                                $accountTypes = 'ssss';
                                
                                if (in_array('createdAt', $accountColumns)) {
                                    $accountFields[] = 'createdAt';
                                    $accountValues[] = date('Y-m-d H:i:s');
                                    $accountTypes .= 's';
                                }
                                
                                $accountPlaceholders = str_repeat('?,', count($accountFields) - 1) . '?';
                                $accountSql = "INSERT INTO accounts (" . implode(', ', $accountFields) . ") VALUES ($accountPlaceholders)";
                                $accountStmt = $conn->prepare($accountSql);
                                $accountStmt->bind_param($accountTypes, ...$accountValues);
                                $accountStmt->execute();
                                $accountId = $conn->insert_id;
                                $accountStmt->close();
                                
                                // agent  
                                $agentFields = ['accountId', 'branchId', 'fName', 'lName'];
                                $agentValues = [$accountId, $agent[5], $agent[0], $agent[2]];
                                $agentTypes = 'iiss';
                                
                                if (in_array('mName', $agentColumns)) {
                                    $agentFields[] = 'mName';
                                    $agentValues[] = $agent[1];
                                    $agentTypes .= 's';
                                }
                                
                                // agentCode 
                                if (in_array('agentCode', $agentColumns)) {
                                    $agentFields[] = 'agentCode';
                                    $agentCode = 'AGT' . str_pad($accountId, 4, '0', STR_PAD_LEFT);
                                    $agentValues[] = $agentCode;
                                    $agentTypes .= 's';
                                }
                                
                                // agentRole 
                                if (in_array('agentRole', $agentColumns)) {
                                    $agentFields[] = 'agentRole';
                                    $agentValues[] = 'manager';
                                    $agentTypes .= 's';
                                }
                                
                                // agentType 
                                if (in_array('agentType', $agentColumns)) {
                                    $agentFields[] = 'agentType';
                                    $agentValues[] = 'B2B';
                                    $agentTypes .= 's';
                                }
                                
                                if (in_array('createdAt', $agentColumns)) {
                                    $agentFields[] = 'createdAt';
                                    $agentValues[] = date('Y-m-d H:i:s');
                                    $agentTypes .= 's';
                                }
                                
                                $agentPlaceholders = str_repeat('?,', count($agentFields) - 1) . '?';
                                $agentSql = "INSERT INTO agent (" . implode(', ', $agentFields) . ") VALUES ($agentPlaceholders)";
                                $agentStmt = $conn->prepare($agentSql);
                                $agentStmt->bind_param($agentTypes, ...$agentValues);
                                $agentStmt->execute();
                                $agentStmt->close();
                            }
                            
                            echo '<div class="message success">✅ ' . count($sampleAgents) . '  .</div>';
                        } else {
                            echo '<div class="message error">❌      .</div>';
                        }
                    } else {
                        echo '<div class="message info">ℹ️  ' . $agentCount . '  .</div>';
                    }
                } else {
                    echo '<div class="message error">❌ agent  accounts   .</div>';
                }
                
                $conn->commit();
                
                echo '<div class="message success"><strong>✅    !</strong></div>';
                
                //   
                echo '<h2>  </h2>';
                $branches = $conn->query("SELECT branchId, branchName FROM branch ORDER BY branchId");
                if ($branches && $branches->num_rows > 0) {
                    echo '<table><tr><th>ID</th><th></th></tr>';
                    while ($row = $branches->fetch_assoc()) {
                        echo '<tr><td>' . htmlspecialchars($row['branchId']) . '</td><td>' . htmlspecialchars($row['branchName']) . '</td></tr>';
                    }
                    echo '</table>';
                }
                
                echo '<h2>  </h2>';
                $agents = $conn->query("SELECT a.accountId, a.emailAddress, ag.fName, ag.lName, ag.branchId FROM accounts a INNER JOIN agent ag ON a.accountId = ag.accountId WHERE a.accountType = 'agent' LIMIT 10");
                if ($agents && $agents->num_rows > 0) {
                    echo '<table><tr><th>Account ID</th><th></th><th></th><th> ID</th></tr>';
                    while ($row = $agents->fetch_assoc()) {
                        $name = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                        echo '<tr><td>' . htmlspecialchars($row['accountId']) . '</td><td>' . htmlspecialchars($row['emailAddress']) . '</td><td>' . htmlspecialchars($name) . '</td><td>' . htmlspecialchars($row['branchId']) . '</td></tr>';
                    }
                    echo '</table>';
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                echo '<div class="message error">❌  : ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<div class="message error">: ' . htmlspecialchars($e->getFile()) . ':' . htmlspecialchars($e->getLine()) . '</div>';
            }
        } else {
            echo '<div class="message info">    .</div>';
        }
        ?>

        <form method="POST">
            <button type="submit" name="init" value="1">  </button>
        </form>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <p><strong>:</strong>        .</p>
        </div>
    </div>
</body>
</html>
?>

