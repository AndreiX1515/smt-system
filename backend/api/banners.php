<?php
require __DIR__ . '/../conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    $check = $conn->query("SHOW TABLES LIKE 'banners'");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => true, 'data' => ['banners' => []]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = $conn->query("SELECT bannerId, bannerOrder, imageUrl, url FROM banners ORDER BY bannerOrder ASC, bannerId ASC");
    $banners = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $img = $row['imageUrl'] ?? '';
        $link = $row['url'] ?? '';

        // normalize image url: allow absolute or relative (/uploads/..)
        if (is_string($img) && $img !== '' && str_starts_with($img, '/')) {
            // keep as absolute path; frontend will make it absolute if needed
        }

        $banners[] = [
            'bannerId' => intval($row['bannerId'] ?? 0),
            'bannerOrder' => intval($row['bannerOrder'] ?? 0),
            'imageUrl' => $img,
            'url' => $link
        ];
    }

    echo json_encode(['success' => true, 'data' => ['banners' => $banners]], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load banners', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}


