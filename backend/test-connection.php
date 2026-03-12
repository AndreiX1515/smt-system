<?php
/**
 *    
 * URL: /backend/test-connection.php
 */

//   
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

// HTML 
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>  </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
        }
        .status-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .status-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .info-item strong {
            display: block;
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-item span {
            color: #212529;
            font-size: 16px;
            word-break: break-all;
        }
        .test-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #dee2e6;
        }
        .test-section h2 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .query-result {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #dee2e6;
            max-height: 300px;
            overflow-y: auto;
        }
        .query-result table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .query-result th {
            background: #495057;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }
        .query-result td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .query-result tr:hover {
            background: #e9ecef;
        }
        .error-details {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .error-details pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 12px;
            margin-top: 10px;
        }
        .refresh-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .refresh-btn:hover {
            background: #5568d3;
        }
        .timestamp {
            color: #6c757d;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔌   </h1>
            <p>    </p>
        </div>
        <div class="content">
            <?php
            //  
            $servername = "localhost";
            $username = "root";
            $password = "cloud1234";
            $dbname = "smarttravel";
            $port = 3306;
            
            $connection_status = "unknown";
            $connection_error = null;
            $conn = null;
            $test_query_result = null;
            $test_query_error = null;
            
            // MySQLi  
            if (!extension_loaded('mysqli')) {
                $connection_status = "failed";
                $connection_error = [
                    'message' => 'MySQLi     .',
                    'code' => 'EXTENSION_NOT_LOADED',
                    'solution' => 'PHP MySQLi    . (: sudo apt-get install php-mysqli  php.ini extension=mysqli )'
                ];
            } else {
                //  
                try {
                    $conn = new mysqli($servername, $username, $password, $dbname, $port);
                
                if ($conn->connect_error) {
                    $connection_status = "failed";
                    $connection_error = [
                        'message' => $conn->connect_error,
                        'code' => $conn->connect_errno
                    ];
                } else {
                    $connection_status = "success";
                    $conn->set_charset("utf8");
                    
                    //   
                    $test_query = "SELECT VERSION() as mysql_version, DATABASE() as current_database, NOW() as server_time";
                    $result = $conn->query($test_query);
                    
                    if ($result) {
                        $test_query_result = $result->fetch_assoc();
                    } else {
                        $test_query_error = $conn->error;
                    }
                    
                    //   
                    $tables_query = "SHOW TABLES";
                    $tables_result = $conn->query($tables_query);
                    $tables = [];
                    if ($tables_result) {
                        while ($row = $tables_result->fetch_array()) {
                            $tables[] = $row[0];
                        }
                    }
                } catch (Exception $e) {
                    $connection_status = "failed";
                    $connection_error = [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    ];
                }
            }
            ?>
            
            <!--   -->
            <?php if ($connection_status === "success"): ?>
                <div class="status-box status-success">
                    <h2>✅  </h2>
                    <p>  .</p>
                </div>
            <?php else: ?>
                <div class="status-box status-error">
                    <h2>❌  </h2>
                    <p>  .</p>
                    <?php if ($connection_error): ?>
                        <div class="error-details">
                            <strong> :</strong>
                            <p><strong>:</strong> <?php echo htmlspecialchars($connection_error['message']); ?></p>
                            <p><strong> :</strong> <?php echo htmlspecialchars($connection_error['code']); ?></p>
                            <?php if (isset($connection_error['solution'])): ?>
                                <p><strong> :</strong> <?php echo htmlspecialchars($connection_error['solution']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!--   -->
            <div class="status-box status-info">
                <h2>📋  </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong></strong>
                        <span><?php echo htmlspecialchars($servername); ?></span>
                    </div>
                    <div class="info-item">
                        <strong></strong>
                        <span><?php echo htmlspecialchars($port); ?></span>
                    </div>
                    <div class="info-item">
                        <strong></strong>
                        <span><?php echo htmlspecialchars($dbname); ?></span>
                    </div>
                    <div class="info-item">
                        <strong></strong>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($connection_status === "success"): ?>
                <!--    -->
                <div class="test-section">
                    <h2>🧪  </h2>
                    <?php if ($test_query_result): ?>
                        <div class="status-box status-success">
                            <h3>✅   </h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>MySQL </strong>
                                    <span><?php echo htmlspecialchars($test_query_result['mysql_version']); ?></span>
                                </div>
                                <div class="info-item">
                                    <strong> </strong>
                                    <span><?php echo htmlspecialchars($test_query_result['current_database']); ?></span>
                                </div>
                                <div class="info-item">
                                    <strong> </strong>
                                    <span><?php echo htmlspecialchars($test_query_result['server_time']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($test_query_error): ?>
                        <div class="status-box status-error">
                            <h3>❌   </h3>
                            <p><?php echo htmlspecialchars($test_query_error); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!--   -->
                    <?php if (!empty($tables)): ?>
                        <div class="test-section">
                            <h2>📊    (<?php echo count($tables); ?>)</h2>
                            <div class="query-result">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tables as $index => $table): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($table); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- PHP   -->
            <div class="test-section">
                <h2>📝 PHP  </h2>
                <div class="status-box status-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>PHP </strong>
                            <span><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>MySQLi </strong>
                            <span><?php echo extension_loaded('mysqli') ? '✅ ' : '❌ '; ?></span>
                        </div>
                        <div class="info-item">
                            <strong> </strong>
                            <span><?php echo ini_get('display_errors') ? '✅ ' : '❌ '; ?></span>
                        </div>
                        <div class="info-item">
                            <strong> </strong>
                            <span><?php echo ini_get('log_errors') ? '✅ ' : '❌ '; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="timestamp">
                 : <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            
            <a href="test-connection.php" class="refresh-btn">🔄 </a>
        </div>
    </div>
</body>
</html>

