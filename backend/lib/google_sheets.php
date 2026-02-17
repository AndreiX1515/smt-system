<?php
/**
 * Google Sheets API 헬퍼 라이브러리
 * - JWT(RS256) 인증 + 토큰 캐싱
 * - 시트 읽기/쓰기
 * - 행 탐색 (packageId + date)
 * - 예약 → 시트 동기화 (calculate-and-SET)
 */

function gs_load_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/google_sheets_config.php';
    }
    return $config;
}

function gs_log(string $message): void {
    $config = gs_load_config();
    $logPath = $config['log_path'] ?? '/var/www/html/logs/sheets_sync.log';
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

// ─── JWT 인증 ───

function gs_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function gs_get_access_token(string $saKeyPath): string {
    if (!file_exists($saKeyPath)) {
        throw new RuntimeException("Service account key not found: $saKeyPath");
    }

    $sa = json_decode(file_get_contents($saKeyPath), true);
    if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
        throw new RuntimeException("Invalid service account key file");
    }

    $now = time();
    $header = gs_base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = gs_base64url_encode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signInput = "$header.$claim";
    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) {
        throw new RuntimeException("Failed to parse private key");
    }

    openssl_sign($signInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $jwt = $signInput . '.' . gs_base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Token exchange failed (HTTP $httpCode): $response");
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException("No access_token in response: $response");
    }

    return $data['access_token'];
}

function gs_get_cached_token(string $saKeyPath, string $cachePath): string {
    if (file_exists($cachePath)) {
        $cached = json_decode(file_get_contents($cachePath), true);
        if ($cached && !empty($cached['access_token']) && !empty($cached['expires_at'])) {
            // 만료 2분 전까지 재사용
            if ($cached['expires_at'] > time() + 120) {
                return $cached['access_token'];
            }
        }
    }

    $token = gs_get_access_token($saKeyPath);

    $cacheData = [
        'access_token' => $token,
        'expires_at'   => time() + 3500, // ~58분
    ];
    @file_put_contents($cachePath, json_encode($cacheData), LOCK_EX);

    return $token;
}

// ─── Range 인코딩 (한글 시트명 지원) ───

function gs_encode_range(string $range): string {
    // Google Sheets API는 range에서 시트명만 퍼센트인코딩, !:는 그대로
    // 예: "'시트1'!A1:Z10" → "'%EC%8B%9C%ED%8A%B81'!A1:Z10"
    if (strpos($range, '!') !== false) {
        [$sheet, $cells] = explode('!', $range, 2);
        return rawurlencode($sheet) . '!' . $cells;
    }
    return rawurlencode($range);
}

// ─── 시트 읽기/쓰기 ───

function gs_read(string $token, string $spreadsheetId, string $range): array {
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
        . urlencode($spreadsheetId)
        . '/values/' . gs_encode_range($range)
        . '?valueRenderOption=FORMATTED_VALUE';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Sheets read failed (HTTP $httpCode): $response");
    }

    return json_decode($response, true) ?: [];
}

function gs_write(string $token, string $spreadsheetId, string $range, array $values): array {
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
        . urlencode($spreadsheetId)
        . '/values/' . gs_encode_range($range)
        . '?valueInputOption=USER_ENTERED';

    $body = json_encode(['values' => $values]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Sheets write failed (HTTP $httpCode): $response");
    }

    return json_decode($response, true) ?: [];
}

function gs_batch_read(string $token, string $spreadsheetId, array $ranges): array {
    $params = array_map(function ($r) { return 'ranges=' . gs_encode_range($r); }, $ranges);
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
        . urlencode($spreadsheetId)
        . '/values:batchGet?valueRenderOption=FORMATTED_VALUE&'
        . implode('&', $params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Sheets batchGet failed (HTTP $httpCode): $response");
    }

    return json_decode($response, true) ?: [];
}

function gs_batch_update(string $token, string $spreadsheetId, array $data): array {
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
        . urlencode($spreadsheetId)
        . '/values:batchUpdate';

    $body = json_encode([
        'valueInputOption' => 'USER_ENTERED',
        'data' => $data,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Sheets batchUpdate failed (HTTP $httpCode): $response");
    }

    return json_decode($response, true) ?: [];
}

// ─── 날짜 파싱 ───

function gs_parse_date(string $dateStr): ?string {
    $dateStr = trim($dateStr);
    if ($dateStr === '') return null;

    // YYYY-MM-DD (이미 정규 형식)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }

    // YYYY.MM.DD 또는 YYYY. M. D (공백 포함)
    if (preg_match('/^(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    // DD-Mon 또는 DD-Mon-YYYY (예: 01-Mar, 06-Mar-2026)
    if (preg_match('/^\d{1,2}-[A-Za-z]{3}/', $dateStr)) {
        $ts = strtotime($dateStr);
        if ($ts !== false) {
            $parsed = date('Y-m-d', $ts);
            // 연도가 없으면 현재 연도 적용
            if (!preg_match('/\d{4}/', $dateStr)) {
                $parsed = date('Y') . '-' . date('m-d', $ts);
            }
            return $parsed;
        }
    }

    // Mon D 또는 Mon DD (예: Mar 6, Mar 28)
    if (preg_match('/^[A-Za-z]{3}\s+\d{1,2}$/', $dateStr)) {
        $ts = strtotime($dateStr . ' ' . date('Y'));
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
    }

    // 일반 strtotime 시도
    $ts = strtotime($dateStr);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

// ─── 열 문자 → 숫자 변환 ───

function gs_col_to_index(string $col): int {
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index; // 1-based (A=1, B=2, Z=26)
}

// ─── 행 탐색 ───

function gs_find_row(string $token, string $spreadsheetId, array $sheetConfig, int $packageId, string $date): ?int {
    $sheetName = $sheetConfig['sheet_name'];
    $colPkg = $sheetConfig['col_package_id'];
    $colDate = $sheetConfig['col_date'];
    $startRow = $sheetConfig['data_start_row'];

    // 패키지ID 열과 날짜 열을 배치로 읽기
    $ranges = [
        "'{$sheetName}'!{$colPkg}{$startRow}:{$colPkg}",
        "'{$sheetName}'!{$colDate}{$startRow}:{$colDate}",
    ];

    $result = gs_batch_read($token, $spreadsheetId, $ranges);
    $valueRanges = $result['valueRanges'] ?? [];

    $pkgValues = $valueRanges[0]['values'] ?? [];
    $dateValues = $valueRanges[1]['values'] ?? [];

    $maxRows = max(count($pkgValues), count($dateValues));

    for ($i = 0; $i < $maxRows; $i++) {
        $rowPkgId = (int)($pkgValues[$i][0] ?? 0);
        $rowDateStr = $dateValues[$i][0] ?? '';

        if ($rowPkgId !== $packageId) continue;

        $parsedDate = gs_parse_date($rowDateStr);
        if ($parsedDate === $date) {
            return $startRow + $i; // 실제 시트 행 번호
        }
    }

    return null;
}

// ─── 핵심: 예약 → 시트 APP 동기화 (calculate-and-SET) ───

function gs_sync_booking_to_sheet(mysqli $conn, int $packageId, string $departureDate): bool {
    if ($packageId <= 0 || $departureDate === '') {
        return false;
    }

    $config = gs_load_config();
    $saKeyPath = $config['service_account_key_path'];
    $spreadsheetId = $config['spreadsheet_id'];
    $cachePath = $config['token_cache_path'];

    // SA 키 파일이 없으면 조용히 종료 (아직 설정 전)
    if (!file_exists($saKeyPath)) {
        return false;
    }

    // 1. DB에서 해당 패키지+날짜의 active 예약 인원 합계 계산
    $sql = "SELECT COALESCE(SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)), 0) AS total_pax
            FROM bookings
            WHERE packageId = ? AND DATE(departureDate) = ?
            AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
            AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $packageId, $departureDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $totalPax = (int)($row['total_pax'] ?? 0);

    // 2. 시트에서 해당 행 찾기
    $token = gs_get_cached_token($saKeyPath, $cachePath);

    foreach ($config['sheets'] as $sheetConfig) {
        $rowNum = gs_find_row($token, $spreadsheetId, $sheetConfig, $packageId, $departureDate);
        if ($rowNum === null) continue;

        // 3. APP 셀에 값 SET
        $appCol = $sheetConfig['col_app'];
        $range = "'{$sheetConfig['sheet_name']}'!{$appCol}{$rowNum}";
        gs_write($token, $spreadsheetId, $range, [[$totalPax]]);

        gs_log("SYNC packageId={$packageId} date={$departureDate} APP={$totalPax} row={$rowNum}");
        return true;
    }

    gs_log("ROW_NOT_FOUND packageId={$packageId} date={$departureDate}");
    return false;
}

// ─── 헬퍼: bookingId로 packageId, departureDate 조회 후 동기화 ───

function gs_sync_by_booking_id(mysqli $conn, string $bookingId): bool {
    $stmt = $conn->prepare("SELECT packageId, DATE(departureDate) AS depDate FROM bookings WHERE bookingId = ? LIMIT 1");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['packageId']) || empty($row['depDate'])) {
        return false;
    }

    return gs_sync_booking_to_sheet($conn, (int)$row['packageId'], $row['depDate']);
}

// ─── CLI 테스트 모드 ───

if (PHP_SAPI === 'cli' && isset($argv[1]) && $argv[1] === '--test-auth') {
    echo "Testing Google Sheets authentication...\n";
    $config = gs_load_config();
    try {
        $token = gs_get_access_token($config['service_account_key_path']);
        echo "OK - Access token obtained (first 20 chars): " . substr($token, 0, 20) . "...\n";

        // 읽기 테스트
        $sheetConfig = $config['sheets'][0];
        $range = "'{$sheetConfig['sheet_name']}'!A1:C5";
        $data = gs_read($token, $config['spreadsheet_id'], $range);
        echo "Read test - rows: " . count($data['values'] ?? []) . "\n";
        if (!empty($data['values'])) {
            foreach ($data['values'] as $i => $row) {
                echo "  Row $i: " . implode(' | ', $row) . "\n";
            }
        }
        echo "\nAll tests passed!\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}
