<?php
/**
 * Public Popups API (for user-facing pages)
 * - Returns active popups registered by super admin
 * - No admin session required
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conn.php';

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return ($res && $res->num_rows > 0);
}

function resolve_popup_pk(mysqli $conn): string {
    $colsRes = $conn->query("SHOW COLUMNS FROM popups");
    $cols = [];
    if ($colsRes) {
        while ($r = $colsRes->fetch_assoc()) $cols[strtolower($r['Field'])] = $r['Field'];
    }
    return $cols['popupid'] ?? ($cols['id'] ?? 'popupId');
}

function has_col(mysqli $conn, string $col): bool {
    $c = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM popups LIKE '{$c}'");
    return ($res && $res->num_rows > 0);
}

try {
    if (!table_exists($conn, 'popups')) {
        send_json_response(['success' => true, 'data' => ['popups' => []]]);
    }

    $idCol = resolve_popup_pk($conn);

    $hasStart = has_col($conn, 'startDate');
    $hasEnd = has_col($conn, 'endDate');
    $hasCreatedAt = has_col($conn, 'createdAt');

    $where = [];
    $params = [];
    $types = '';

    //  " (start~end)   "  
    $today = date('Y-m-d');
    if ($hasStart && $hasEnd) {
        // start/end     
        $where[] = "(startDate IS NOT NULL AND startDate <> '' AND endDate IS NOT NULL AND endDate <> '' AND DATE(startDate) <= ? AND DATE(endDate) >= ?)";
        $params[] = $today;
        $params[] = $today;
        $types .= 'ss';
    } else {
        //    (  )
        send_json_response(['success' => true, 'data' => ['popups' => []]]);
    }

    $whereClause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderBy = $hasCreatedAt ? 'ORDER BY createdAt DESC' : "ORDER BY `{$idCol}` DESC";

    $sql = "SELECT * FROM popups {$whereClause} {$orderBy} LIMIT 20";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => 'Failed to prepare query: ' . $conn->error], 500);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $id = $row[$idCol] ?? null;
        $imageUrl = $row['imageUrl'] ?? $row['image_url'] ?? '';
        if (is_string($imageUrl) && $imageUrl !== '' && !preg_match('#^(https?:)?//#', $imageUrl)) {
            $imageUrl = '/' . ltrim($imageUrl, '/');
        }
        $out[] = [
            'popupId' => is_numeric($id) ? intval($id) : $id,
            'title' => $row['title'] ?? '',
            'imageUrl' => $imageUrl,
            'link' => $row['link'] ?? '',
            'target' => $row['target'] ?? '',
            'startDate' => $row['startDate'] ?? null,
            'endDate' => $row['endDate'] ?? null,
        ];
    }
    $stmt->close();

    send_json_response(['success' => true, 'data' => ['popups' => $out]]);
} catch (Exception $e) {
    send_json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
