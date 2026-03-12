<?php
/**
 * Verify template data completeness in DB (product_templates/templates.data JSON).
 *
 * Usage:
 *   php scripts/verify_template_testfill.php            # checks latest 5
 *   php scripts/verify_template_testfill.php 10         # checks latest 10
 *   php scripts/verify_template_testfill.php 5 32 30    # checks specific ids (optional after limit)
 */

require __DIR__ . '/../backend/conn.php';

$limit = 5;
$ids = [];
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limit = max(1, min(50, intval($argv[1])));
}
for ($i = 2; $i < count($argv); $i++) {
    if (is_numeric($argv[$i])) $ids[] = intval($argv[$i]);
}

// resolve table
$tbl = 'product_templates';
$idCol = 'templateId';
$r = $conn->query("SHOW TABLES LIKE 'product_templates'");
if (!$r || $r->num_rows === 0) {
    $tbl = 'templates';
    $idCol = 'id';
}

function decode_template_data($raw) {
    if (!is_string($raw) || $raw === '') return [null, 'empty'];
    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return [$d, null];
    $d2 = json_decode(stripslashes($raw), true);
    if (json_last_error() === JSON_ERROR_NONE) return [$d2, null];
    return [null, json_last_error_msg()];
}

function is_nonempty_string($v) {
    return is_string($v) && trim($v) !== '';
}

function is_upload_path($v) {
    if (!is_string($v)) return false;
    $s = trim($v);
    if ($s === '') return false;
    if (preg_match('/^(blob:|data:)/i', $s)) return false;
    if (preg_match('/picsum\.photos/i', $s)) return false;
    // allow absolute URLs on same domain as well (e.g. https://www.smt-escape.com/uploads/...)
    if (str_starts_with($s, '/uploads/')) return true;
    if (preg_match('#^https?://www\.smt-escape\.com/uploads/#i', $s)) return true;
    return false;
}

function check_template($data) {
    $missing = [];

    // Product images
    $thumb = $data['product']['images']['thumbnail'] ?? '';
    $detail = $data['product']['images']['detailImage'] ?? '';
    $productImages = $data['product']['images']['productImages'] ?? [];
    if (!is_upload_path($thumb)) $missing[] = 'product.images.thumbnail';
    if (!is_upload_path($detail)) $missing[] = 'product.images.detailImage';
    if (!is_array($productImages) || count(array_filter($productImages, 'is_upload_path')) < 1) $missing[] = 'product.images.productImages(>=1)';

    // Included/Excluded
    $inc = $data['included'] ?? null;
    $exc = $data['excluded'] ?? null;
    if (!is_array($inc) || count(array_filter($inc, 'is_nonempty_string')) < 1) $missing[] = 'included(>=1)';
    if (!is_array($exc) || count(array_filter($exc, 'is_nonempty_string')) < 1) $missing[] = 'excluded(>=1)';

    // Pricing
    $pp = $data['pricing']['perPerson'] ?? null;
    if (!is_array($pp) || count($pp) < 1) $missing[] = 'pricing.perPerson(>=1)';
    $single = $data['pricing']['singleRoomFee'] ?? '';
    if (!is_nonempty_string($single)) $missing[] = 'pricing.singleRoomFee';

    // Usage guide PDF
    $pdf = $data['usageGuide']['file']['filePath'] ?? '';
    if (!is_upload_path($pdf) || !preg_match('/\.pdf$/i', $pdf)) $missing[] = 'usageGuide.file.filePath(.pdf)';

    // Schedule meeting
    $m = $data['schedule']['meeting'] ?? null;
    if (!is_array($m)) {
        $missing[] = 'schedule.meeting';
    } else {
        if (!is_nonempty_string($m['hour'] ?? '')) $missing[] = 'schedule.meeting.hour';
        if (!is_nonempty_string($m['minute'] ?? '')) $missing[] = 'schedule.meeting.minute';
        if (!is_nonempty_string($m['placeName'] ?? '')) $missing[] = 'schedule.meeting.placeName';
        if (!is_nonempty_string($m['placeAddress'] ?? '')) $missing[] = 'schedule.meeting.placeAddress';
    }

    // Schedule days
    $days = $data['schedule']['days'] ?? null;
    if (!is_array($days) || count($days) < 1) {
        $missing[] = 'schedule.days(>=1)';
    } else {
        foreach ($days as $i => $day) {
            $idx = $i + 1;
            if (!is_nonempty_string($day['summary'] ?? '')) $missing[] = "schedule.days[$idx].summary";

            $atts = $day['attractions'] ?? null;
            if (!is_array($atts) || count($atts) < 1) {
                $missing[] = "schedule.days[$idx].attractions(>=1)";
            } else {
                $a0 = $atts[0] ?? [];
                if (!is_nonempty_string($a0['name'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].name";
                if (!is_nonempty_string($a0['address'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].address";
                if (!is_nonempty_string($a0['infoHtml'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].infoHtml";
                if (!is_upload_path($a0['image'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].image";
                if (!is_nonempty_string($a0['startHour'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].startHour";
                if (!is_nonempty_string($a0['startMinute'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].startMinute";
                if (!is_nonempty_string($a0['endHour'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].endHour";
                if (!is_nonempty_string($a0['endMinute'] ?? '')) $missing[] = "schedule.days[$idx].attractions[1].endMinute";
            }

            $acc = $day['accommodation'] ?? null;
            if (!is_array($acc)) {
                $missing[] = "schedule.days[$idx].accommodation";
            } else {
                if (!is_nonempty_string($acc['name'] ?? '')) $missing[] = "schedule.days[$idx].accommodation.name";
                if (!is_nonempty_string($acc['address'] ?? '')) $missing[] = "schedule.days[$idx].accommodation.address";
                if (!is_nonempty_string($acc['descriptionHtml'] ?? '')) $missing[] = "schedule.days[$idx].accommodation.descriptionHtml";
                if (!is_upload_path($acc['image'] ?? '')) $missing[] = "schedule.days[$idx].accommodation.image";
            }
        }
    }

    return $missing;
}

// fetch rows
$rows = [];
if (count($ids) > 0) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT $idCol AS id, templateName, createdAt, updatedAt, data FROM $tbl WHERE $idCol IN ($in) ORDER BY updatedAt DESC, createdAt DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
} else {
    $sql = "SELECT $idCol AS id, templateName, createdAt, updatedAt, data FROM $tbl ORDER BY updatedAt DESC, createdAt DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
}

$out = [];
foreach ($rows as $row) {
    [$data, $err] = decode_template_data($row['data'] ?? '');
    $missing = [];
    if ($data === null) {
        $missing[] = 'data.json_decode_failed: ' . ($err ?: 'unknown');
    } else {
        $missing = check_template($data);
    }

    $out[] = [
        'id' => $row['id'],
        'templateName' => $row['templateName'] ?? '',
        'createdAt' => $row['createdAt'] ?? null,
        'updatedAt' => $row['updatedAt'] ?? null,
        'missingCount' => count($missing),
        'missing' => $missing,
    ];
}

echo json_encode([
    'table' => $tbl,
    'checked' => count($out),
    'results' => $out
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


