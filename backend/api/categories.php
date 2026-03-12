<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../conn.php';

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    //   (     )    fallback
    $tbl1 = $conn->query("SHOW TABLES LIKE 'product_main_categories'");
    $tbl2 = $conn->query("SHOW TABLES LIKE 'product_sub_categories'");
    $hasTables = ($tbl1 && $tbl1->num_rows > 0) && ($tbl2 && $tbl2->num_rows > 0);

    if (!$hasTables) {
        send_json([
            'success' => true,
            'data' => [
                'mainCategories' => [
                    ['code' => 'season', 'name' => '', 'subCategories' => []],
                    ['code' => 'region', 'name' => '', 'subCategories' => []],
                    ['code' => 'theme', 'name' => '', 'subCategories' => []],
                    ['code' => 'private', 'name' => '', 'subCategories' => []],
                    ['code' => 'daytrip', 'name' => '', 'subCategories' => []],
                ]
            ]
        ]);
    }

    $main = [];
    $mainRes = $conn->query("SELECT mainCategoryId, code, name, sortOrder FROM product_main_categories ORDER BY sortOrder ASC, mainCategoryId ASC");
    while ($mainRes && ($row = $mainRes->fetch_assoc())) {
        $mainId = intval($row['mainCategoryId']);
        $main[$mainId] = [
            'mainCategoryId' => $mainId,
            'code' => $row['code'],
            'name' => $row['name'],
            'sortOrder' => isset($row['sortOrder']) ? intval($row['sortOrder']) : 0,
            'subCategories' => []
        ];
    }

    $subRes = $conn->query("SELECT subCategoryId, mainCategoryId, code, name, sortOrder FROM product_sub_categories ORDER BY mainCategoryId ASC, sortOrder ASC, subCategoryId ASC");
    while ($subRes && ($row = $subRes->fetch_assoc())) {
        $mid = intval($row['mainCategoryId']);
        if (!isset($main[$mid])) continue;
        $main[$mid]['subCategories'][] = [
            'subCategoryId' => intval($row['subCategoryId']),
            'code' => $row['code'],
            'name' => $row['name'],
            'sortOrder' => isset($row['sortOrder']) ? intval($row['sortOrder']) : 0
        ];
    }

    send_json([
        'success' => true,
        'data' => [
            'mainCategories' => array_values($main)
        ]
    ]);
} catch (Exception $e) {
    send_json(['success' => false, 'message' => '  .'], 500);
}


