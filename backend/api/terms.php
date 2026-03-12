<?php
require __DIR__ . '/../conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$category = $_GET['category'] ?? 'terms';
$language = $_GET['language'] ?? ($_GET['lang'] ?? 'en');

$allowedCategories = [
    'terms',
    'privacy_collection',
    'privacy_sharing',
    'marketing_consent',
    'cancellation_fee_special',
    'unique_identifier_collection'
];
$allowedLang = ['ko', 'en', 'tl'];

if (!in_array($category, $allowedCategories, true)) {
    $category = 'terms';
}
if (!in_array($language, $allowedLang, true)) {
    $language = 'en';
}

try {
    $check = $conn->query("SHOW TABLES LIKE 'terms'");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => true, 'data' => ['category' => $category, 'language' => $language, 'content' => '']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("SELECT content FROM terms WHERE category = ? AND language = ? LIMIT 1");
    $stmt->bind_param('ss', $category, $language);
    $stmt->execute();
    $res = $stmt->get_result();
    $content = '';
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $content = $row['content'] ?? '';
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'category' => $category,
            'language' => $language,
            'content' => $content
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load terms', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}


