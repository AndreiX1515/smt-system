<?php
// Secure file download helper
// Usage: /backend/api/download.php?file=/uploads/visa/upload_xxx.pdf

require_once __DIR__ . '/../conn.php';

//   (user/admin  )
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function download_is_authenticated(): bool {
    return (!empty($_SESSION['accountId']) || !empty($_SESSION['admin_accountId']) || !empty($_SESSION['agent_accountId']) || !empty($_SESSION['guide_accountId']) || !empty($_SESSION['cs_accountId']));
}

function download_send_error(int $code, string $message): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!download_is_authenticated()) {
    download_send_error(401, 'Unauthorized');
}

$file = $_GET['file'] ?? '';
$file = is_string($file) ? trim($file) : '';
// / : inline=1  Content-Disposition inline 
$inline = $_GET['inline'] ?? $_GET['disposition'] ?? '';
$isInline = false;
if (is_string($inline)) {
    $v = strtolower(trim($inline));
    $isInline = ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'inline');
}

if ($file === '' || $file === 'undefined') {
    download_send_error(400, 'file parameter is required');
}

//  : uploads 
// - "/uploads/..."  "uploads/..."  
$file = str_replace('\\', '/', $file);
if (str_starts_with($file, 'uploads/')) {
    $rel = '/' . $file;
} else {
    $rel = $file;
}

if (!str_starts_with($rel, '/uploads/')) {
    download_send_error(403, 'Forbidden');
}

//   
if (str_contains($rel, '..')) {
    download_send_error(403, 'Forbidden');
}

$abs = realpath(__DIR__ . '/../../' . ltrim($rel, '/'));
if ($abs === false) {
    download_send_error(404, 'File not found');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
    download_send_error(500, 'Uploads directory not configured');
}

// realpath   uploads  
if (!str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR)) {
    download_send_error(403, 'Forbidden');
}

if (!is_file($abs) || !is_readable($abs)) {
    download_send_error(404, 'File not found');
}

$name = basename($abs);
$size = filesize($abs);
if ($size === false) $size = 0;

//  MIME 
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (in_array($ext, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
else if ($ext === 'png') $mime = 'image/png';
else if ($ext === 'gif') $mime = 'image/gif';
else if ($ext === 'pdf') $mime = 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($abs);
exit;



