<?php
/**
 * SMT  - Flyer/Detail/Itinerary   API
 * POST /backend/api/upload-product-file.php
 *
 * Parameters:
 *   - file:  
 *   - type:   (flyer, detail, itinerary)
 *
 * Response:
 *   - success: boolean
 *   - path:   
 *   - message:   ( )
 */

require __DIR__ . '/../../backend/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST is allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

//   
$accountId = null;
if (isset($_SESSION['admin_accountId'])) $accountId = intval($_SESSION['admin_accountId']);

if (empty($accountId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

//  
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => ' php.ini upload_max_filesize .',
        UPLOAD_ERR_FORM_SIZE => '  MAX_FILE_SIZE .',
        UPLOAD_ERR_PARTIAL => '  .',
        UPLOAD_ERR_NO_FILE => '  .',
        UPLOAD_ERR_NO_TMP_DIR => '  .',
        UPLOAD_ERR_CANT_WRITE => '    .',
        UPLOAD_ERR_EXTENSION => 'PHP    .'
    ];
    $errorCode = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
    $message = $errorMessages[$errorCode] ?? '  .';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

//   
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
if (!in_array($type, ['flyer', 'detail', 'itinerary'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '   .'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'];
$originalName = $file['name'];
$tmpPath = $file['tmp_name'];
$fileSize = $file['size'];

//    (10MB)
$maxSize = 10 * 1024 * 1024;
if ($fileSize > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '  10MB   .'], JSON_UNESCAPED_UNICODE);
    exit;
}

//   
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = [];
switch ($type) {
    case 'flyer':
    case 'detail':
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        break;
    case 'itinerary':
        $allowedExtensions = ['pdf'];
        break;
}

if (!in_array($ext, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '   . : ' . implode(', ', $allowedExtensions)], JSON_UNESCAPED_UNICODE);
    exit;
}

//   
$uploadDir = __DIR__ . '/../../uploads/products/' . $type . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

//   
$uniqueName = uniqid($type . '_') . '_' . time() . '.' . $ext;
$destinationPath = $uploadDir . $uniqueName;

//  
if (!move_uploaded_file($tmpPath, $destinationPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '  .'], JSON_UNESCAPED_UNICODE);
    exit;
}

//   
$relativePath = 'uploads/products/' . $type . '/' . $uniqueName;

echo json_encode([
    'success' => true,
    'path' => $relativePath,
    'originalName' => $originalName,
    'size' => $fileSize
], JSON_UNESCAPED_UNICODE);
