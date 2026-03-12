<?php
require "../conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'POST method required'], 405);
    exit;
}

//   (user/admin   )
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isAuthenticated()) {
    send_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['file'];
    $upload_type = $_POST['type'] ?? 'other';
    $related_id = $_POST['related_id'] ?? null;

    // Validate file
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum 10MB allowed.');
    }

    // Allowed file types
    // NOTE: $file['type'] /    application/octet-stream    
    //  finfo MIME ,  fallback .
    $allowed_types = [
        // images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        // iOS/HEIC (    )
        'image/heic', 'image/heif',
        // documents
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $clientMime = (string)($file['type'] ?? '');
    $serverMime = '';
    try {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $serverMime = (string)finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
    } catch (Throwable $t) { $serverMime = ''; }

    $mimeToCheck = $serverMime ?: $clientMime;

    // image     fallback  (  MIME  application/octet-stream )
    $isImageUpload = (strpos($upload_type, 'template') === 0) || ($upload_type === 'products') || ($upload_type === 'packages') || ($upload_type === 'package') || ($upload_type === 'visa') || ($upload_type === 'visas');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $imageExts = ['jpg','jpeg','png','gif','webp','heic','heif'];
    $mimeAllowed = in_array($mimeToCheck, $allowed_types, true);
    $extAllowedForImage = ($isImageUpload && in_array($ext, $imageExts, true));

    if (!$mimeAllowed && !$extAllowedForImage) {
        throw new Exception('File type not allowed');
    }

    // Create upload directory if not exists
    //    (/var/www/html/uploads) 
    $upload_dir = __DIR__ . '/../../uploads/' . $upload_type . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename (shorter format)
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = date('Ymd') . '_' . substr(uniqid(), -6) . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // =====   DB (file_uploads) =====
    // NOTE:
    // -   file_uploads    (0)    .
    // -      relatedId(templateId)    .
    try {
        // file_uploads  , relatedType template  
        $tblCheck = $conn->query("SHOW TABLES LIKE 'file_uploads'");
        if ($tblCheck && $tblCheck->num_rows > 0) {
            $rs = $conn->query("SHOW COLUMNS FROM file_uploads LIKE 'relatedType'");
            $col = $rs ? $rs->fetch_assoc() : null;
            $typeDef = isset($col['Type']) ? (string)$col['Type'] : '';
            if ($typeDef !== '' && stripos($typeDef, "enum(") === 0 && stripos($typeDef, "'template'") === false) {
                //  enum template 
                // ()    + template 
                $conn->query("ALTER TABLE file_uploads MODIFY relatedType ENUM('visa','profile','inquiry','package','template') NOT NULL");
            }

            // upload_type(folder) -> relatedType ( )
            $relatedType = null;
            if (strpos($upload_type, 'template') === 0) {
                $relatedType = 'template';
            } else if ($upload_type === 'products' || $upload_type === 'packages' || $upload_type === 'package') {
                $relatedType = 'package';
            } else if ($upload_type === 'passports' || $upload_type === 'profiles' || $upload_type === 'profile') {
                $relatedType = 'profile';
            } else if ($upload_type === 'inquiries' || $upload_type === 'inquiry') {
                $relatedType = 'inquiry';
            } else if ($upload_type === 'visa' || $upload_type === 'visas') {
                $relatedType = 'visa';
            }

            if (!empty($relatedType)) {
                $accountId = $_SESSION['accountId'] ?? ($_SESSION['admin_accountId'] ?? ($_SESSION['user_id'] ?? 0));
                $accountId = intval($accountId);

                $rid = 0;
                if ($related_id !== null && $related_id !== '') {
                    $rid = is_numeric($related_id) ? intval($related_id) : 0;
                }

                $stmt = $conn->prepare("INSERT INTO file_uploads (accountId, relatedType, relatedId, fileName, originalName, filePath, fileSize, fileType)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $relPath = '/uploads/' . $upload_type . '/' . $new_filename;
                    $size = intval($file['size']);
                    $mime = (string)($file['type'] ?? '');
                    $orig = (string)($file['name'] ?? '');
                    $stmt->bind_param('isisssis', $accountId, $relatedType, $rid, $new_filename, $orig, $relPath, $size, $mime);
                    @$stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Throwable $t) {
        //       
    }

    // Save file info to database (simulate)
    $file_info = [
        'uploadId' => rand(1000, 9999),
        'originalName' => $file['name'],
        'fileName' => $new_filename,
        'filePath' => '/uploads/' . $upload_type . '/' . $new_filename,
        'fileSize' => $file['size'],
        //  MIME    MIME 
        'mimeType' => ($serverMime ?: $clientMime),
        'uploadType' => $upload_type,
        'relatedId' => $related_id,
        'uploadedAt' => date('Y-m-d H:i:s')
    ];

    $who = $_SESSION['accountId'] ?? ($_SESSION['admin_accountId'] ?? ($_SESSION['user_id'] ?? ''));
    log_activity("File uploaded: " . $file['name'] . " by user " . $who);

    send_json_response([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => $file_info
    ]);

} catch (Exception $e) {
    log_activity("File upload error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}

function isAuthenticated() {
    // user/admin/agent/guide 세션 확인
    return (!empty($_SESSION['accountId']) || !empty($_SESSION['admin_accountId']) || !empty($_SESSION['user_id']) || !empty($_SESSION['agent_accountId']) || !empty($_SESSION['guide_accountId']) || !empty($_SESSION['cs_accountId']));
}
?>