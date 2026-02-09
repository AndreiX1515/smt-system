<?php
// 이미지 업로드 API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// 업로드 디렉토리
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/shop/';

// 디렉토리 없으면 생성
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 파일 체크
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMsg = isset($_FILES['image']) ? 'Upload error: ' . $_FILES['image']['error'] : 'No file uploaded';
    echo json_encode(['error' => $errorMsg]);
    exit();
}

$file = $_FILES['image'];

// 허용 확장자 및 MIME 타입
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-png'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mimeType = $file['type'];

// finfo로도 체크
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($detectedMime) {
        $mimeType = $detectedMime;
    }
}

// 확장자 또는 MIME 타입 중 하나라도 허용되면 통과
$extValid = in_array($ext, $allowedExts);
$mimeValid = in_array($mimeType, $allowedTypes);

if (!$extValid && !$mimeValid) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF, WEBP images are allowed (ext: ' . $ext . ', mime: ' . $mimeType . ')']);
    exit();
}

// 파일 크기 제한
$uploadType = $_POST['type'] ?? '';
$maxSize = ($uploadType === 'description') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size must be less than 5MB']);
    exit();
}

// 파일명 생성
$filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$filepath = $uploadDir . $filename;

// 파일 이동
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $url = '/uploads/shop/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
}
?>
