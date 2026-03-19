<?php
/**
 * Admin  /  
 *      .
 */

require "../../../backend/conn.php";

header('Content-Type: text/html; charset=utf-8');

// :         
// : .htaccess IP    

$action = $_GET['action'] ?? 'check';
$email = $_POST['email'] ?? 'admin@smarttravel.com';
$password = $_POST['password'] ?? 'admin123';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin  </title>
    <style>
        body {
            font-family: 'Noto Sans KR', sans-serif;
            max-width: 600px;
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #0056b3;
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
        .account-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .account-info h3 {
            margin-top: 0;
        }
        .account-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin  </h1>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
            try {
                // accounts       
                $columnsCheck = $conn->query("SHOW COLUMNS FROM accounts");
                $columns = [];
                while ($col = $columnsCheck->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
                
                // updatedAt   
                if (!in_array('updatedAt', $columns)) {
                    $alterSql = "ALTER TABLE accounts ADD COLUMN updatedAt DATETIME NULL DEFAULT NULL";
                    if ($conn->query($alterSql)) {
                        echo '<div class="message info">ℹ️ updatedAt  .</div>';
                        $columns[] = 'updatedAt';
                    }
                }
                
                //   
                $checkSql = "SELECT accountId FROM accounts WHERE emailAddress = ? AND accountType IN ('admin_ph','admin_kr')";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('s', $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    //    
                    $updateFields = ['password = ?', "accountStatus = 'active'"];
                    $updateValues = [$password];
                    $paramTypes = 's';
                    
                    // updatedAt   
                    if (in_array('updatedAt', $columns)) {
                        $updateFields[] = 'updatedAt = NOW()';
                    }
                    
                    $updateSql = "UPDATE accounts SET " . implode(', ', $updateFields) . " WHERE emailAddress = ? AND accountType IN ('admin_ph','admin_kr')";
                    $updateValues[] = $email;
                    $paramTypes .= 's';
                    
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param($paramTypes, ...$updateValues);
                    
                    if ($updateStmt->execute()) {
                        echo '<div class="message success">✅ Admin    !</div>';
                        echo '<div class="account-info">';
                        echo '<h3> </h3>';
                        echo '<p><strong>:</strong> ' . htmlspecialchars($email) . '</p>';
                        echo '<p><strong>:</strong> ' . htmlspecialchars($password) . '</p>';
                        echo '<p><a href="../index.html" style="color: #007bff;">   →</a></p>';
                        echo '</div>';
                    } else {
                        throw new Exception("  : " . $updateStmt->error);
                    }
                    $updateStmt->close();
                } else {
                    //   
                    // columns    
                    $fields = ['emailAddress', 'password', 'accountType', 'accountStatus'];
                    $values = [$email, $password, 'admin_ph', 'active'];
                    
                    // createdAt   
                    if (in_array('createdAt', $columns)) {
                        $fields[] = 'createdAt';
                        $values[] = date('Y-m-d H:i:s');
                    }
                    
                    // updatedAt   
                    if (in_array('updatedAt', $columns)) {
                        $fields[] = 'updatedAt';
                        $values[] = date('Y-m-d H:i:s');
                    }
                    
                    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                    $insertSql = "INSERT INTO accounts (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                    
                    $insertStmt = $conn->prepare($insertSql);
                    $types = str_repeat('s', count($fields));
                    $insertStmt->bind_param($types, ...$values);
                    
                    if ($insertStmt->execute()) {
                        echo '<div class="message success">✅ Admin   !</div>';
                        echo '<div class="account-info">';
                        echo '<h3> </h3>';
                        echo '<p><strong>:</strong> ' . htmlspecialchars($email) . '</p>';
                        echo '<p><strong>:</strong> ' . htmlspecialchars($password) . '</p>';
                        echo '<p><a href="../index.html" style="color: #007bff;">   →</a></p>';
                        echo '</div>';
                    } else {
                        throw new Exception("  : " . $insertStmt->error);
                    }
                    $insertStmt->close();
                }
                $checkStmt->close();
                
            } catch (Exception $e) {
                echo '<div class="message error">❌  : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            //  admin  
            try {
                $checkSql = "SELECT accountId, emailAddress, accountStatus, createdAt FROM accounts WHERE accountType IN ('admin_ph','admin_kr')";
                $result = $conn->query($checkSql);
                
                if ($result && $result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    echo '<div class="message info">ℹ️ Admin   .</div>';
                    echo '<div class="account-info">';
                    echo '<h3> Admin  </h3>';
                    echo '<p><strong>:</strong> ' . htmlspecialchars($admin['emailAddress']) . '</p>';
                    echo '<p><strong>:</strong> ' . htmlspecialchars($admin['accountStatus']) . '</p>';
                    echo '<p><strong>:</strong> ' . htmlspecialchars($admin['createdAt'] ?? 'N/A') . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="message info">ℹ️ Admin  .  .</div>';
                }
            } catch (Exception $e) {
                echo '<div class="message error">❌  : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>

        <form method="POST" action="?action=create">
            <div class="form-group">
                <label for="email"> ()</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"></label>
                <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>" required>
            </div>
            
            <button type="submit">Admin  / </button>
        </form>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <p><strong>:</strong>  /       .</p>
            <p>: <code>.htaccess</code>  IP    </p>
        </div>
    </div>
</body>
</html>
?>

