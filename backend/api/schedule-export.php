<?php
// Full itinerary export (Excel-compatible)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conn.php';

/**
 * Quill/HTML  /CSV   "" .
 * -  
 * - <li> "- "  
 * - <br>, </p>, </div>, </li>  
 */
function schedule_export_html_to_text($value): string {
    $s = (string)($value ?? '');
    if ($s === '') return '';

    // Quill UI  
    $s = preg_replace('/<span[^>]*class="ql-ui"[^>]*>.*?<\/span>/is', '', $s);

    // / 
    $s = preg_replace('/<li[^>]*>/i', "- ", $s);
    $s = preg_replace('/<\/li>/i', "\n", $s);
    $s = preg_replace('/<br\s*\/?>/i', "\n", $s);
    $s = preg_replace('/<\/p>/i', "\n", $s);
    $s = preg_replace('/<\/div>/i', "\n", $s);

    //   +  
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // / 
    $s = preg_replace("/\r\n?/", "\n", $s);
    $s = preg_replace("/[ \t]+\n/", "\n", $s);
    $s = preg_replace("/\n{3,}/", "\n\n", $s);
    return trim($s);
}

function schedule_export_cell($value): string {
    //  HTML ->   (/    )
    return schedule_export_html_to_text($value);
}

//        
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$bookingId = $_GET['booking_id'] ?? $_GET['bookingId'] ?? $_GET['id'] ?? '';
$bookingId = (string)$bookingId;
if ($bookingId === '' || $bookingId === 'undefined') {
    http_response_code(400);
    echo 'booking_id is required';
    exit;
}

// format=xlsx|xls|csv
$format = strtolower((string)($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'xls', 'csv'], true)) $format = 'xlsx';

// booking + package
// NOTE:
// - For B2B bookings, bookings.accountId is often the agent(owner) account.
// - The actual customer is stored in bookings.customerAccountId (if exists) or selectedOptions.customerInfo.accountId.
// - Export should be accessible to the customer who owns the booking (not just the creator).
$sel = "bookingId, accountId, packageId, departureDate, departureTime, guideId, customerAccountId, selectedOptions";

$bst = $conn->prepare("SELECT {$sel} FROM bookings WHERE bookingId = ? LIMIT 1");
if (!$bst) {
    http_response_code(500);
    echo 'Failed to prepare booking query';
    exit;
}
$bst->bind_param('s', $bookingId);
$bst->execute();
$b = $bst->get_result()->fetch_assoc();
$bst->close();

if (!$b) {
    http_response_code(404);
    echo 'Booking not found';
    exit;
}

// Optional access control: if user session exists, enforce owner match
// - Allow booking creator(accountId)
// - Allow customer(customerAccountId or selectedOptions.customerInfo.accountId)
// - Allow non-user roles (admin/agent/guide/cs) to access for support
try {
    $sid = $_SESSION['user_id'] ?? ($_SESSION['accountId'] ?? null);
    if ($sid !== null && (int)$sid > 0) {
        $sid = (int)$sid;
        $ownerId = (int)($b['accountId'] ?? 0);
        if ($sid !== $ownerId) {
            $ok = false;

            // customerAccountId 확인
            $cid = (int)($b['customerAccountId'] ?? 0);
            if ($cid > 0 && $sid === $cid) $ok = true;

            // 3) allow support roles (admin/agent/guide/cs) if present
            if (!$ok) {
                $atype = strtolower((string)($_SESSION['account_type'] ?? $_SESSION['accountType'] ?? ''));
                if ($atype && $atype !== 'user' && $atype !== 'client') $ok = true;
            }

            if (!$ok) {
                http_response_code(403);
                echo 'Forbidden';
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

$packageId = (int)($b['packageId'] ?? 0);
$departureDateRaw = (string)($b['departureDate'] ?? '');
if ($packageId <= 0 || $departureDateRaw === '') {
    http_response_code(400);
    echo 'Invalid booking data';
    exit;
}

// Load package/meeting info (best-effort)
$pkg = [];
try {
    // NOTE: packages table columns vary; keep to known columns in this DB.
    $ps = $conn->prepare("SELECT packageName, meeting_time, meetingTime, meeting_location, meetingPoint, meeting_address FROM packages WHERE packageId = ? LIMIT 1");
    if ($ps) {
        $ps->bind_param('i', $packageId);
        $ps->execute();
        $pkg = $ps->get_result()->fetch_assoc() ?: [];
        $ps->close();
    }
} catch (Throwable $e) {
    // ignore
}

// Load guide name (best-effort)
$guideName = '';
try {
    $gid = null;
    $gs = $conn->prepare("SELECT guideId FROM bookings WHERE bookingId = ? LIMIT 1");
    if ($gs) {
        $gs->bind_param('s', $bookingId);
        $gs->execute();
        $row = $gs->get_result()->fetch_assoc();
        $gs->close();
        $gid = $row['guideId'] ?? null;
    }
    if (empty($gid)) {
        $tbl = $conn->query("SHOW TABLES LIKE 'booking_guides'");
        if ($tbl && $tbl->num_rows > 0) {
            $gs2 = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? LIMIT 1");
            if ($gs2) {
                $gs2->bind_param('s', $bookingId);
                $gs2->execute();
                $row2 = $gs2->get_result()->fetch_assoc();
                $gs2->close();
                $gid = $row2['guideId'] ?? null;
            }
        }
    }
    if (!empty($gid)) {
        $gid = (int)$gid;
        $gn = $conn->prepare("SELECT guideName FROM guides WHERE guideId = ? LIMIT 1");
        if ($gn) {
            $gn->bind_param('i', $gid);
            $gn->execute();
            $rgn = $gn->get_result()->fetch_assoc();
            $gn->close();
            $guideName = (string)($rgn['guideName'] ?? '');
        }
    }
} catch (Throwable $e) {
    // ignore
}

// Load schedules from package_schedules + attractions
$schedules = [];
$st = $conn->query("SHOW TABLES LIKE 'package_schedules'");
if ($st && $st->num_rows > 0) {
    $sql = "SELECT schedule_id, day_number, description, start_time, end_time,
                   airport_location, airport_address, airport_description,
                   accommodation_name, accommodation_address, accommodation_description,
                   transportation_description, breakfast, lunch, dinner
            FROM package_schedules
            WHERE package_id = ?
            ORDER BY day_number ASC, schedule_id ASC";
    $ps = $conn->prepare($sql);
    if ($ps) {
        $ps->bind_param('i', $packageId);
        $ps->execute();
        $res = $ps->get_result();
        while ($r = $res->fetch_assoc()) {
            $schedules[] = $r;
        }
        $ps->close();
    }
}

// Map schedule_id -> attractions
$attractionsBySchedule = [];
$atTbl = $conn->query("SHOW TABLES LIKE 'package_attractions'");
if ($atTbl && $atTbl->num_rows > 0 && !empty($schedules)) {
    $ids = array_map(fn($r) => (int)$r['schedule_id'], $schedules);
    $ids = array_values(array_filter($ids, fn($v) => $v > 0));
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT schedule_id, attraction_name, attraction_address, attraction_description, start_time, end_time, visit_order
                                FROM package_attractions
                                WHERE schedule_id IN ($in)
                                ORDER BY schedule_id ASC, start_time ASC, visit_order ASC, attraction_id ASC");
        if ($stmt) {
            $params = $ids;
            $bind = [];
            $bind[] = $types;
            foreach ($params as $i => $_) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            $stmt->execute();
            $rr = $stmt->get_result();
            while ($a = $rr->fetch_assoc()) {
                $sid = (int)($a['schedule_id'] ?? 0);
                if (!isset($attractionsBySchedule[$sid])) {
                    $attractionsBySchedule[$sid] = [];
                }
                $attractionsBySchedule[$sid][] = $a;
            }
            $stmt->close();
        }
    }
}

$departureDate = new DateTime($departureDateRaw);

$headers = [
    'Booking ID',
    'Package ID',
    'Day',
    'Date',
    'Start Time',
    'End Time',
    'Type',
    'Name',
    'Address',
    'Description',
    'Accommodation',
    'Transportation',
    'Breakfast',
    'Lunch',
    'Dinner',
];

// 0) XLSX output (template-based)
if ($format === 'xlsx') {
    $template = __DIR__ . '/../../_templates/schedule_template.xlsx';
    if (!is_file($template)) {
        // fallback to legacy xls if template missing
        $format = 'xls';
    } else {
        // Build day payload matching template blocks (up to 3 days)
        $maxDay = 0;
        foreach ($schedules as $s) {
            $d = (int)($s['day_number'] ?? 0);
            if ($d > $maxDay) $maxDay = $d;
        }
        $maxDay = max(1, min(3, $maxDay));

        $daysPayload = [];
        for ($day = 1; $day <= $maxDay; $day++) {
            // find schedule row
            $srow = null;
            foreach ($schedules as $s) {
                if ((int)($s['day_number'] ?? 0) === $day) { $srow = $s; break; }
            }
            $d = clone $departureDate;
            $d->add(new DateInterval('P' . max(0, $day - 1) . 'D'));
            $dateYmd = $d->format('Y-m-d');

            $sid = $srow ? (int)($srow['schedule_id'] ?? 0) : 0;
            $atts = ($sid > 0) ? ($attractionsBySchedule[$sid] ?? []) : [];
            // already ordered by start_time, visit_order
            $attsPayload = [];
            foreach ($atts as $a) {
                $attsPayload[] = [
                    'start_time' => (string)($a['start_time'] ?? ''),
                    'end_time' => (string)($a['end_time'] ?? ''),
                    'name' => schedule_export_cell($a['attraction_name'] ?? ''),
                    'address' => schedule_export_cell($a['attraction_address'] ?? ''),
                ];
            }

            // summary: prefer schedule description then airport_location
            $summary = '';
            if ($srow) {
                $summary = trim(schedule_export_cell($srow['description'] ?? ''));
                if ($summary === '') $summary = trim(schedule_export_cell($srow['airport_location'] ?? ''));
            }

            $daysPayload[] = [
                'day' => $day,
                'date' => $dateYmd,
                'summary' => $summary,
                'attractions' => $attsPayload,
                'accommodation_name' => $srow ? schedule_export_cell($srow['accommodation_name'] ?? '') : '',
                'accommodation_address' => $srow ? schedule_export_cell($srow['accommodation_address'] ?? '') : '',
                'transportation' => $srow ? schedule_export_cell($srow['transportation_description'] ?? '') : '',
                'breakfast' => $srow ? schedule_export_cell($srow['breakfast'] ?? '') : '',
                'lunch' => $srow ? schedule_export_cell($srow['lunch'] ?? '') : '',
                'dinner' => $srow ? schedule_export_cell($srow['dinner'] ?? '') : '',
            ];
        }

        // trip period: start = departureDate + booking departureTime(if exists), end = last attraction end_time on last day (fallback: schedule end_time)
        $bookingTime = (string)($b['departureTime'] ?? '');
        $startTime = $bookingTime ?: ($daysPayload[0]['attractions'][0]['start_time'] ?? '');
        $endDay = $daysPayload[count($daysPayload)-1] ?? null;
        $endTime = '';
        if ($endDay && !empty($endDay['attractions'])) {
            $last = $endDay['attractions'][count($endDay['attractions'])-1];
            $endTime = (string)($last['end_time'] ?? '');
        }
        if ($endTime === '' && $srow) $endTime = (string)($srow['end_time'] ?? '');

        $tripPeriod = trim($departureDateRaw);
        if ($startTime !== '') $tripPeriod .= ' ' . substr($startTime, 0, 5);
        if ($endDay) {
            $tripPeriod .= ' - ' . (string)($endDay['date'] ?? '');
            if ($endTime !== '') $tripPeriod .= ' ' . substr($endTime, 0, 5);
        }

        $meetingTime = (string)($pkg['meeting_time'] ?? ($pkg['meetingTime'] ?? ''));
        if ($meetingTime !== '' && strlen($meetingTime) === 5) $meetingTime .= ':00';

        // Meeting place fallback:
        // - Prefer packages.meeting_location/meetingPoint + meeting_address
        // - If empty, fall back to day1 schedule airport_location/airport_address (DB schedule data)
        $meetingPlaceName = trim((string)($pkg['meeting_location'] ?? ($pkg['meetingPoint'] ?? '')));
        $meetingPlaceAddress = trim((string)($pkg['meeting_address'] ?? ''));
        if ($meetingPlaceName === '' || $meetingPlaceAddress === '') {
            $day1 = null;
            foreach ($schedules as $s) {
                if ((int)($s['day_number'] ?? 0) === 1) { $day1 = $s; break; }
            }
            if ($day1) {
                if ($meetingPlaceName === '') $meetingPlaceName = trim(schedule_export_cell($day1['airport_location'] ?? ''));
                if ($meetingPlaceAddress === '') $meetingPlaceAddress = trim(schedule_export_cell($day1['airport_address'] ?? ''));
            }
        }

        $payload = [
            'productName' => (string)($pkg['packageName'] ?? ''),
            'tripPeriod' => $tripPeriod,
            'meetingGuide' => $guideName,
            'meetingTime' => $meetingTime,
            'meetingPlaceName' => $meetingPlaceName,
            'meetingPlaceAddress' => $meetingPlaceAddress,
            'days' => $daysPayload,
        ];

        $tmpDir = sys_get_temp_dir();
        $jsonPath = $tmpDir . '/schedule_export_' . preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $bookingId) . '_' . uniqid() . '.json';
        $outPath = $tmpDir . '/schedule_export_' . preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $bookingId) . '_' . uniqid() . '.xlsx';
        file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $py = __DIR__ . '/../scripts/schedule_export_xlsx.py';
        // IMPORTANT: Apache 환경의 PATH에서는 venv python이 아닐 수 있으므로, 가능한 경우 venv python을 고정 사용한다.
        $python = __DIR__ . '/../../.venv-e2e/bin/python3';
        if (!is_file($python) || !is_executable($python)) $python = 'python3';
        $cmd = $python . ' ' . escapeshellarg($py) . ' ' . escapeshellarg($template) . ' ' . escapeshellarg($jsonPath) . ' ' . escapeshellarg($outPath);
        $des = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $des, $pipes);
        $stdout = '';
        $stderr = '';
        $code = 1;
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            $code = proc_close($proc);
        }
        @unlink($jsonPath);

        if ($code !== 0 || !is_file($outPath) || filesize($outPath) < 2000) {
            @unlink($outPath);
            // fallback to xls if generation failed
            $format = 'xls';
        } else {
            $filename = "schedule_{$bookingId}.xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($outPath));
            readfile($outPath);
            @unlink($outPath);
            exit;
        }
    }
}

// 1) Excel(.xls) output (HTML table; Excel opens it as spreadsheet)
if ($format === 'xls') {
    $filename = "itinerary_{$bookingId}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Excel-friendly UTF-8 BOM
    echo "\xEF\xBB\xBF";

    echo "<html><head><meta charset=\"utf-8\"></head><body>";
    echo "<table border=\"1\" cellpadding=\"4\" cellspacing=\"0\">";
    echo "<tr>";
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
    }
    echo "</tr>";

    foreach ($schedules as $s) {
        $day = (int)($s['day_number'] ?? 1);
        $d = clone $departureDate;
        $d->add(new DateInterval('P' . max(0, $day - 1) . 'D'));

        $sid = (int)($s['schedule_id'] ?? 0);
        $atts = $sid > 0 ? ($attractionsBySchedule[$sid] ?? []) : [];

        if (!empty($atts)) {
            foreach ($atts as $a) {
                $row = [
                    $bookingId,
                    $packageId,
                    $day,
                    $d->format('Y-m-d'),
                    $a['start_time'] ?? '',
                    $a['end_time'] ?? '',
                    'Attraction',
                    $a['attraction_name'] ?? '',
                    $a['attraction_address'] ?? '',
                    $a['attraction_description'] ?? '',
                    $s['accommodation_name'] ?? '',
                    $s['transportation_description'] ?? '',
                    $s['breakfast'] ?? '',
                    $s['lunch'] ?? '',
                    $s['dinner'] ?? '',
                ];

                echo '<tr>';
                foreach ($row as $cell) {
                    $cell = schedule_export_cell($cell);
                    echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
        } else {
            $row = [
                $bookingId,
                $packageId,
                $day,
                $d->format('Y-m-d'),
                $s['start_time'] ?? '',
                $s['end_time'] ?? '',
                'Schedule',
                $s['airport_location'] ?? '',
                $s['airport_address'] ?? '',
                $s['airport_description'] ?? ($s['description'] ?? ''),
                $s['accommodation_name'] ?? '',
                $s['transportation_description'] ?? '',
                $s['breakfast'] ?? '',
                $s['lunch'] ?? '',
                $s['dinner'] ?? '',
            ];

            echo '<tr>';
            foreach ($row as $cell) {
                $cell = schedule_export_cell($cell);
                echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
    }

    echo "</table></body></html>";
    exit;
}

// 2) CSV output (format=csv)
$filename = "itinerary_{$bookingId}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, $headers);

foreach ($schedules as $s) {
    $day = (int)($s['day_number'] ?? 1);
    $d = clone $departureDate;
    $d->add(new DateInterval('P' . max(0, $day - 1) . 'D'));

    $sid = (int)($s['schedule_id'] ?? 0);
    $atts = $sid > 0 ? ($attractionsBySchedule[$sid] ?? []) : [];

    if (!empty($atts)) {
        foreach ($atts as $a) {
            $csvRow = [
                $bookingId,
                $packageId,
                $day,
                $d->format('Y-m-d'),
                $a['start_time'] ?? '',
                $a['end_time'] ?? '',
                'Attraction',
                $a['attraction_name'] ?? '',
                $a['attraction_address'] ?? '',
                $a['attraction_description'] ?? '',
                $s['accommodation_name'] ?? '',
                $s['transportation_description'] ?? '',
                $s['breakfast'] ?? '',
                $s['lunch'] ?? '',
                $s['dinner'] ?? '',
            ];
            $csvRow = array_map('schedule_export_cell', $csvRow);
            fputcsv($out, $csvRow);
        }
    } else {
        $csvRow = [
            $bookingId,
            $packageId,
            $day,
            $d->format('Y-m-d'),
            $s['start_time'] ?? '',
            $s['end_time'] ?? '',
            'Schedule',
            $s['airport_location'] ?? '',
            $s['airport_address'] ?? '',
            $s['airport_description'] ?? ($s['description'] ?? ''),
            $s['accommodation_name'] ?? '',
            $s['transportation_description'] ?? '',
            $s['breakfast'] ?? '',
            $s['lunch'] ?? '',
            $s['dinner'] ?? '',
        ];
        $csvRow = array_map('schedule_export_cell', $csvRow);
        fputcsv($out, $csvRow);
    }
}

fclose($out);
exit;


