<?php
/**
 * Google Sheets ↔ DB 양방향 재고 보정 Cron
 *
 * 실행: every 2 min - crontab: 0/2 * * * * php /var/www/html/backend/cron/sync_sheets_inventory.php
 *
 * 1) 시스템 → 시트: DB의 active 예약 인원 합계 → 시트 APP 셀
 * 2) 시트 → 시스템: R + APP → DB capacity
 */

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../lib/google_sheets.php';

$config = gs_load_config();
$lockPath = $config['sync_lock_path'];

// ── 이중 실행 방지 ──
$lockFp = fopen($lockPath, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    gs_log("CRON SKIPPED - another instance running");
    exit(0);
}

gs_log("CRON START");
$startTime = microtime(true);

try {
    $saKeyPath = $config['service_account_key_path'];
    if (!file_exists($saKeyPath)) {
        gs_log("CRON ABORT - SA key not found: $saKeyPath");
        exit(0);
    }

    $token = gs_get_cached_token($saKeyPath, $config['token_cache_path']);
    $spreadsheetId = $config['spreadsheet_id'];
    $today = date('Y-m-d');

    $totalAppUpdates = 0;
    $totalCapUpdates = 0;

    foreach ($config['sheets'] as $sheetConfig) {
        $sheetName = $sheetConfig['sheet_name'];
        $colPkg = $sheetConfig['col_package_id'];
        $colDate = $sheetConfig['col_date'];
        $colR = $sheetConfig['col_r'];
        $colApp = $sheetConfig['col_app'];
        $startRow = $sheetConfig['data_start_row'];

        // 전체 데이터 읽기 (4개 열)
        $ranges = [
            "'{$sheetName}'!{$colPkg}{$startRow}:{$colPkg}",
            "'{$sheetName}'!{$colDate}{$startRow}:{$colDate}",
            "'{$sheetName}'!{$colR}{$startRow}:{$colR}",
            "'{$sheetName}'!{$colApp}{$startRow}:{$colApp}",
        ];

        $result = gs_batch_read($token, $spreadsheetId, $ranges);
        $valueRanges = $result['valueRanges'] ?? [];

        $pkgValues = $valueRanges[0]['values'] ?? [];
        $dateValues = $valueRanges[1]['values'] ?? [];
        $rValues = $valueRanges[2]['values'] ?? [];
        $appValues = $valueRanges[3]['values'] ?? [];

        $maxRows = max(count($pkgValues), count($dateValues), count($rValues), count($appValues));

        // APP 업데이트를 모아서 batchUpdate
        $appUpdates = [];

        for ($i = 0; $i < $maxRows; $i++) {
            $rowPkgId = (int)($pkgValues[$i][0] ?? 0);
            $rowDateStr = $dateValues[$i][0] ?? '';
            $rowR = (int)($rValues[$i][0] ?? 0);
            $rowApp = (int)($appValues[$i][0] ?? 0);

            if ($rowPkgId <= 0) continue;

            $parsedDate = gs_parse_date($rowDateStr);
            if ($parsedDate === null) continue;

            // 과거 날짜 skip
            if ($parsedDate < $today) continue;

            $rowNum = $startRow + $i;

            // ── [시스템 → 시트] APP 동기화 ──
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)), 0) AS total_pax
                 FROM bookings
                 WHERE packageId = ? AND DATE(departureDate) = ?
                 AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                 AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')"
            );
            $stmt->bind_param('is', $rowPkgId, $parsedDate);
            $stmt->execute();
            $dbRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $dbPax = (int)($dbRow['total_pax'] ?? 0);

            if ($rowApp !== $dbPax) {
                $appUpdates[] = [
                    'range'  => "'{$sheetName}'!{$colApp}{$rowNum}",
                    'values' => [[$dbPax]],
                ];
                gs_log("CRON APP_FIX row={$rowNum} pkg={$rowPkgId} date={$parsedDate} sheet={$rowApp} db={$dbPax}");
                $totalAppUpdates++;
                // 업데이트된 APP 값 사용
                $rowApp = $dbPax;
            }

            // ── [시트 → 시스템] Capacity 동기화 ──
            $newCapacity = $rowR + $rowApp;

            $capStmt = $conn->prepare(
                "SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1"
            );
            $capStmt->bind_param('is', $rowPkgId, $parsedDate);
            $capStmt->execute();
            $capRow = $capStmt->get_result()->fetch_assoc();
            $capStmt->close();

            if ($capRow) {
                $currentCap = (int)$capRow['capacity'];
                if ($currentCap !== $newCapacity) {
                    $upStmt = $conn->prepare(
                        "UPDATE package_available_dates SET capacity = ? WHERE package_id = ? AND available_date = ?"
                    );
                    $upStmt->bind_param('iis', $newCapacity, $rowPkgId, $parsedDate);
                    $upStmt->execute();
                    $upStmt->close();
                    gs_log("CRON CAP_FIX pkg={$rowPkgId} date={$parsedDate} old={$currentCap} new={$newCapacity}");
                    $totalCapUpdates++;
                }
            } else {
                // 행이 없으면 INSERT
                $insStmt = $conn->prepare(
                    "INSERT INTO package_available_dates (package_id, available_date, capacity, status) VALUES (?, ?, ?, 'open')"
                );
                $insStmt->bind_param('isi', $rowPkgId, $parsedDate, $newCapacity);
                $insStmt->execute();
                $insStmt->close();
                gs_log("CRON CAP_INSERT pkg={$rowPkgId} date={$parsedDate} capacity={$newCapacity}");
                $totalCapUpdates++;
            }
        }

        // batchUpdate로 APP 셀 일괄 쓰기
        if (!empty($appUpdates)) {
            gs_batch_update($token, $spreadsheetId, $appUpdates);
        }
    }

    $elapsed = round((microtime(true) - $startTime) * 1000);
    gs_log("CRON END appUpdates={$totalAppUpdates} capUpdates={$totalCapUpdates} elapsed={$elapsed}ms");

} catch (Throwable $e) {
    gs_log("CRON ERROR: " . $e->getMessage());
} finally {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockPath);
}
