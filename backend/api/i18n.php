<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require "../conn.php";
require "../i18n_helper.php";

//  
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_texts':
            getTexts($conn);
            break;
        case 'get_package_i18n':
            getPackageI18n($conn);
            break;
        case 'get_packages_i18n':
            getPackagesI18n($conn);
            break;
        case 'get_notice_i18n':
            getNoticeI18n($conn);
            break;
        case 'save_user_language':
            saveUserLanguage($conn);
            break;
        case 'get_user_language':
            getUserLanguage($conn);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} catch (Exception $e) {
    error_log("i18n API error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '  .'], 500);
}

//   
function getTexts($conn) {
    $lang = $_GET['lang'] ?? 'ko';
    
    //   
    if (!in_array($lang, ['ko', 'en', 'tl'])) {
        $lang = 'ko';
    }
    
    // i18n_texts      
    $sql = "SELECT textKey, textValue FROM i18n_texts WHERE languageCode = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => '   .'], 500);
    }
    
    $stmt->bind_param("s", $lang);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $texts = [];
    while ($row = $result->fetch_assoc()) {
        $texts[$row['textKey']] = $row['textValue'];
    }
    
    $stmt->close();
    
    send_json_response([
        'success' => true,
        'data' => $texts
    ]);
}

//    
function getPackageI18n($conn) {
    $packageId = $_GET['package_id'] ?? '';
    $lang = $_GET['lang'] ?? 'ko';
    
    if (empty($packageId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    if (!in_array($lang, ['ko', 'en', 'tl'])) {
        $lang = 'ko';
    }
    
    //     ( )
    send_json_response([
        'success' => true,
        'data' => []
    ]);
}

//     
function getPackagesI18n($conn) {
    $lang = $_GET['lang'] ?? 'ko';
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $category = $_GET['category'] ?? '';
    
    if (!in_array($lang, ['ko', 'en', 'tl'])) {
        $lang = 'ko';
    }
    
    //      ( )
    send_json_response([
        'success' => true,
        'data' => []
    ]);
}

//    
function getNoticeI18n($conn) {
    $noticeId = $_GET['notice_id'] ?? '';
    $lang = $_GET['lang'] ?? 'ko';
    
    if (empty($noticeId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    if (!in_array($lang, ['ko', 'en', 'tl'])) {
        $lang = 'ko';
    }
    
    //     ( )
    send_json_response([
        'success' => true,
        'data' => []
    ]);
}

//    
function saveUserLanguage($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $accountId = $input['account_id'] ?? '';
    $language = $input['language'] ?? 'ko';
    
    if (empty($accountId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    if (!in_array($language, ['ko', 'en', 'tl'])) {
        $language = 'ko';
    }
    
    //     ( )
    send_json_response([
        'success' => true,
        'message' => '  .'
    ]);
}

//    
function getUserLanguage($conn) {
    $accountId = $_GET['account_id'] ?? '';
    
    if (empty($accountId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    //     ( )
    send_json_response([
        'success' => true,
        'data' => ['preferredLanguage' => 'ko']
    ]);
}

?>

