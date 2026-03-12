<?php
require __DIR__ . "/../conn.php";

// GET  
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['success' => false, 'message' => 'GET  .'], 405);
}

$type = $_GET['type'] ?? '';
$lang = $_GET['lang'] ?? ($_GET['language'] ?? null);
if (!$lang && isset($_SESSION['language'])) $lang = $_SESSION['language'];
$lang = in_array($lang, ['ko','en','tl'], true) ? $lang : 'en';

switch ($type) {
    case 'terms':
        handleGetTerms($lang);
        break;
    case 'privacy':
        handleGetPrivacyPolicy($lang);
        break;
    case 'company':
        handleGetCompanyInfo();
        break;
    case 'partnership':
        handleGetPartnershipInfo();
        break;
    case 'contact':
        handleGetContactInfo();
        break;
    case 'footer':
        handleGetFooterInfo();
        break;
    default:
        send_json_response(['success' => false, 'message' => ' .'], 400);
}

function fetchTermsContent($category, $lang) {
    global $conn;
    $check = $conn->query("SHOW TABLES LIKE 'terms'");
    if (!$check || $check->num_rows === 0) return null;

    $stmt = $conn->prepare("SELECT content, updatedAt FROM terms WHERE category = ? AND language = ? ORDER BY updatedAt DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ss', $category, $lang);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

//  
function handleGetTerms($lang) {
    try {
        $row = fetchTermsContent('terms', $lang);
        if ($row) {
            send_json_response(['success' => true, 'data' => ['content' => $row['content'] ?? '', 'updatedAt' => $row['updatedAt'] ?? null]]);
        }
        sendDefaultTerms();
        
    } catch (Exception $e) {
        error_log("Get terms error: " . $e->getMessage());
        sendDefaultTerms();
    }
}

//  
function handleGetPrivacyPolicy($lang) {
    try {
        // privacy policy privacy_collection 
        $row = fetchTermsContent('privacy_collection', $lang);
        if ($row) {
            send_json_response(['success' => true, 'data' => ['content' => $row['content'] ?? '', 'updatedAt' => $row['updatedAt'] ?? null]]);
        }
        sendDefaultPrivacyPolicy();
        
    } catch (Exception $e) {
        error_log("Get privacy policy error: " . $e->getMessage());
        sendDefaultPrivacyPolicy();
    }
}

//   
function handleGetCompanyInfo() {
    try {
        global $conn;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'company_info'");
        
        if ($tableCheck->num_rows === 0) {
            sendDefaultCompanyInfo();
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT content, updatedAt 
            FROM company_info 
            WHERE type = 'company' AND isActive = 1 
            ORDER BY updatedAt DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            send_json_response([
                'success' => true,
                'data' => [
                    'content' => $data['content'],
                    'updatedAt' => $data['updatedAt']
                ]
            ]);
        } else {
            sendDefaultCompanyInfo();
        }
        
    } catch (Exception $e) {
        error_log("Get company info error: " . $e->getMessage());
        sendDefaultCompanyInfo();
    }
}

//   
function handleGetPartnershipInfo() {
    try {
        global $conn;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'company_info'");
        
        if ($tableCheck->num_rows === 0) {
            sendDefaultPartnershipInfo();
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT content, updatedAt 
            FROM company_info 
            WHERE type = 'partnership' AND isActive = 1 
            ORDER BY updatedAt DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            send_json_response([
                'success' => true,
                'data' => [
                    'content' => $data['content'],
                    'updatedAt' => $data['updatedAt']
                ]
            ]);
        } else {
            sendDefaultPartnershipInfo();
        }
        
    } catch (Exception $e) {
        error_log("Get partnership info error: " . $e->getMessage());
        sendDefaultPartnershipInfo();
    }
}

//   
function handleGetContactInfo() {
    try {
        global $conn;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'company_info'");
        
        if ($tableCheck->num_rows === 0) {
            sendDefaultContactInfo();
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT content, updatedAt 
            FROM company_info 
            WHERE type = 'contact' AND isActive = 1 
            ORDER BY updatedAt DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            send_json_response([
                'success' => true,
                'data' => [
                    'content' => $data['content'],
                    'updatedAt' => $data['updatedAt']
                ]
            ]);
        } else {
            sendDefaultContactInfo();
        }
        
    } catch (Exception $e) {
        error_log("Get contact info error: " . $e->getMessage());
        sendDefaultContactInfo();
    }
}

//   ( )
function handleGetFooterInfo() {
    try {
        global $conn;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'company_info'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            send_json_response(['success' => true, 'data' => ['companyInfo' => null]]);
        }

        $res = $conn->query("SELECT * FROM company_info ORDER BY infoId ASC LIMIT 1");
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [];

        $companyInfo = [
            'companyName' => $row['companyName'] ?? '',
            'representative' => $row['representative'] ?? '',
            'address' => $row['address'] ?? '',
            'businessRegistration' => $row['businessRegistration'] ?? '',
            'telecomRegistration' => $row['telecomRegistration'] ?? '',
            'phoneLocal' => $row['phoneLocal'] ?? '',
            'phoneInternational' => $row['phoneInternational'] ?? '',
            'email' => $row['email'] ?? '',
            'fax' => $row['fax'] ?? '',
            'website' => $row['website'] ?? '',
            'operatingHours' => $row['operatingHours'] ?? '',
            'updatedAt' => $row['updatedAt'] ?? null
        ];

        send_json_response(['success' => true, 'data' => ['companyInfo' => $companyInfo]]);
    } catch (Exception $e) {
        error_log("Get footer company info error: " . $e->getMessage());
        send_json_response(['success' => true, 'data' => ['companyInfo' => null]]);
    }
}

//   
function sendDefaultTerms() {
    $termsContent = "
        <h2>1 ()</h2>
        <p>  Smart Travel( '')         ,      .</p>
        
        <h2>2 ()</h2>
        <p>      .</p>
        <ul>
            <li>1. ''    ,     .</li>
            <li>2. ''           .</li>
            <li>3. ''      ,           .</li>
        </ul>
        
        <h2>3 (   )</h2>
        <p>1.           .</p>
        <p>2.          ,           7  .</p>
        
        <h2>4 ( )</h2>
        <p>    :</p>
        <ul>
            <li>1.    </li>
            <li>2.    </li>
            <li>3.   </li>
            <li>4.    </li>
        </ul>
        
        <h2>5 ( )</h2>
        <p>1.     ,   ,            .</p>
        <p>2.  1         3    . ,         .</p>
    ";
    
    send_json_response([
        'success' => true,
        'data' => [
            'content' => $termsContent,
            'updatedAt' => '2025-01-20 00:00:00'
        ]
    ]);
}

//   
function sendDefaultPrivacyPolicy() {
    $privacyContent = "
        <h2>1 ( )</h2>
        <p>     .         ,      18         .</p>
        <ul>
            <li>1.    </li>
            <li>2.   </li>
            <li>3.     </li>
            <li>4.    </li>
        </ul>
        
        <h2>2 (   )</h2>
        <p>1.     ·       ·   ·.</p>
        <p>2.        :</p>
        <ul>
            <li>-  :  </li>
            <li>-  :    5</li>
            <li>-  :     3</li>
        </ul>
        
        <h2>3 ( 3 )</h2>
        <p>   1( )    ,  ,      17    3 .</p>
        
        <h2>4 ( )</h2>
        <p>          :</p>
        <ul>
            <li>-  :   </li>
            <li>-  :   </li>
        </ul>
    ";
    
    send_json_response([
        'success' => true,
        'data' => [
            'content' => $privacyContent,
            'updatedAt' => '2025-01-20 00:00:00'
        ]
    ]);
}

//    
function sendDefaultCompanyInfo() {
    $companyContent = "
        <h2> </h2>
        <p>Smart Travel        .     ,                  .</p>
        
        <h2></h2>
        <p>              .</p>
        
        <h2></h2>
        <p>        .</p>
        
        <h2> </h2>
        <ul>
            <li>•  :    .</li>
            <li>• :     .</li>
            <li>• :    .</li>
            <li>• :   .</li>
        </ul>
        
        <h2> </h2>
        <ul>
            <li>•     </li>
            <li>•   </li>
            <li>•    </li>
            <li>•   </li>
            <li>• 24  </li>
        </ul>
    ";
    
    send_json_response([
        'success' => true,
        'data' => [
            'content' => $companyContent,
            'updatedAt' => '2025-01-20 00:00:00'
        ]
    ]);
}

//    
function sendDefaultPartnershipInfo() {
    $partnershipContent = "
        <h2> </h2>
        <p>Smart Travel         .</p>
        
        <h2> </h2>
        <ul>
            <li>•   </li>
            <li>•   </li>
            <li>•   </li>
            <li>•    </li>
            <li>•    </li>
            <li>•    </li>
        </ul>
        
        <h2> </h2>
        <ul>
            <li>•   </li>
            <li>•    ( )</li>
            <li>•    </li>
            <li>•   </li>
            <li>•   </li>
        </ul>
        
        <h2> </h2>
        <p>        .</p>
        <ul>
            <li>• : partnership@smarttravel.com</li>
            <li>• : +82-2-1234-5678</li>
            <li>• : </li>
        </ul>
        
        <h2> </h2>
        <ol>
            <li>1.    </li>
            <li>2.   </li>
            <li>3.     </li>
            <li>4.   </li>
            <li>5.    </li>
            <li>6.   </li>
        </ol>
    ";
    
    send_json_response([
        'success' => true,
        'data' => [
            'content' => $partnershipContent,
            'updatedAt' => '2025-01-20 00:00:00'
        ]
    ]);
}

//    
function sendDefaultContactInfo() {
    $contactContent = "
        <h2> </h2>
        
        <h3></h3>
        <ul>
            <li>• :    123, Smart Travel </li>
            <li>• : +82-2-1234-5678</li>
            <li>• : +82-2-1234-5679</li>
            <li>• : info@smarttravel.com</li>
        </ul>
        
        <h3></h3>
        <ul>
            <li>• : +82-2-1234-5680</li>
            <li>• : support@smarttravel.com</li>
            <li>• :  09:00 - 18:00 (, ,  )</li>
        </ul>
        
        <h3> </h3>
        <ul>
            <li>•    : +82-10-1234-5678</li>
            <li>• 24 </li>
        </ul>
        
        <h3> </h3>
        <ul>
            <li>• : Smart Travel </li>
            <li>• : </li>
            <li>• : 123-45-67890</li>
            <li>• : 2025--1234</li>
        </ul>
    ";
    
    send_json_response([
        'success' => true,
        'data' => [
            'content' => $contactContent,
            'updatedAt' => '2025-01-20 00:00:00'
        ]
    ]);
}
?>
