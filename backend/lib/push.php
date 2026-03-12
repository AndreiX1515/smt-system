<?php
/**
 * Push notification helper (no external provider required).
 *
 * This file implements:
 * - device token registration (device_tokens)
 * - push queue (push_queue) to be processed by an external worker (FCM/APNs)
 *
 * NOTE:
 * - This code does NOT send pushes by itself (no network/provider credentials in code).
 * - It provides a reliable "hook" so apps can register tokens and the backend can enqueue pushes.
 */

function push_tables_ensure(mysqli $conn): void {
    // device_tokens: one account can have multiple devices
    $conn->query("
        CREATE TABLE IF NOT EXISTS device_tokens (
            tokenId INT AUTO_INCREMENT PRIMARY KEY,
            accountId INT NOT NULL,
            deviceToken VARCHAR(512) NOT NULL,
            platform VARCHAR(16) DEFAULT 'unknown',
            isActive TINYINT(1) DEFAULT 1,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            lastSeenAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_account_token (accountId, deviceToken),
            KEY idx_device_token (deviceToken),
            KEY idx_account_active (accountId, isActive)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // push_queue: to be processed by an external worker
    $conn->query("
        CREATE TABLE IF NOT EXISTS push_queue (
            pushId INT AUTO_INCREMENT PRIMARY KEY,
            accountId INT NOT NULL,
            deviceToken VARCHAR(512) NOT NULL,
            platform VARCHAR(16) DEFAULT 'unknown',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            actionUrl VARCHAR(512) DEFAULT NULL,
            data JSON DEFAULT NULL,
            status VARCHAR(16) DEFAULT 'pending',
            provider VARCHAR(32) DEFAULT NULL,
            error TEXT DEFAULT NULL,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            sentAt DATETIME DEFAULT NULL,
            KEY idx_status_created (status, createdAt),
            KEY idx_account_created (accountId, createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function push_register_device_token(mysqli $conn, int $accountId, string $deviceToken, string $platform = 'unknown'): array {
    $deviceToken = trim($deviceToken);
    $platform = strtolower(trim($platform));
    if ($platform === '') $platform = 'unknown';

    if ($accountId <= 0 || $deviceToken === '') {
        return ['ok' => false, 'error' => 'Missing required fields'];
    }

    push_tables_ensure($conn);

    $sql = "
        INSERT INTO device_tokens (accountId, deviceToken, platform, isActive, lastSeenAt)
        VALUES (?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            platform = VALUES(platform),
            isActive = 1,
            lastSeenAt = NOW(),
            updatedAt = NOW()
    ";
    $st = $conn->prepare($sql);
    if (!$st) return ['ok' => false, 'error' => $conn->error ?: 'prepare failed'];
    $st->bind_param('iss', $accountId, $deviceToken, $platform);
    $ok = $st->execute();
    $err = $st->error ?: '';
    $st->close();

    return ['ok' => (bool)$ok, 'error' => $ok ? null : $err];
}

function push_unregister_device_token(mysqli $conn, int $accountId, string $deviceToken): array {
    $deviceToken = trim($deviceToken);
    if ($accountId <= 0 || $deviceToken === '') {
        return ['ok' => false, 'error' => 'Missing required fields'];
    }

    push_tables_ensure($conn);

    $st = $conn->prepare("UPDATE device_tokens SET isActive = 0, updatedAt = NOW() WHERE accountId = ? AND deviceToken = ?");
    if (!$st) return ['ok' => false, 'error' => $conn->error ?: 'prepare failed'];
    $st->bind_param('is', $accountId, $deviceToken);
    $ok = $st->execute();
    $err = $st->error ?: '';
    $st->close();
    return ['ok' => (bool)$ok, 'error' => $ok ? null : $err];
}

function push_enqueue_for_account(mysqli $conn, int $accountId, string $title, string $message, string $actionUrl = '', array $data = []): array {
    if ($accountId <= 0) return ['ok' => false, 'queued' => 0, 'error' => 'invalid accountId'];

    push_tables_ensure($conn);

    $tokens = [];
    $st = $conn->prepare("SELECT deviceToken, platform FROM device_tokens WHERE accountId = ? AND isActive = 1 ORDER BY lastSeenAt DESC LIMIT 20");
    if ($st) {
        $st->bind_param('i', $accountId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) $tokens[] = $r;
        $st->close();
    }
    if (empty($tokens)) return ['ok' => true, 'queued' => 0];

    $queued = 0;
    $payload = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    $actionUrl = trim((string)$actionUrl);
    foreach ($tokens as $t) {
        $token = (string)($t['deviceToken'] ?? '');
        if ($token === '') continue;
        $platform = (string)($t['platform'] ?? 'unknown');

        $q = $conn->prepare("
            INSERT INTO push_queue (accountId, deviceToken, platform, title, message, actionUrl, data, status, createdAt)
            VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, 'pending', NOW())
        ");
        if (!$q) continue;
        $q->bind_param('issssss', $accountId, $token, $platform, $title, $message, $actionUrl, $payload);
        if ($q->execute()) $queued++;
        $q->close();
    }

    return ['ok' => true, 'queued' => $queued];
}


// ============================================================
// Expo Push (ReactNativeWebView / Expo)
// - Token is stored in accounts.expoPushToken as ExponentPushToken[xxxx]
// - This sends pushes directly to Expo push gateway.
// ============================================================

function expo_normalize_token(string $t): string {
    $t = trim($t);
    if ($t === '') return '';
    if (preg_match('/^ExponentPushToken\\[[^\\]]+\\]$/', $t)) return $t;
    if (preg_match('/^Expo(nent)?PushToken\\[([^\\]]+)\\]$/', $t, $m)) {
        return 'ExponentPushToken[' . $m[2] . ']';
    }
    if (preg_match('/\\[([^\\]]+)\\]/', $t, $m)) {
        return 'ExponentPushToken[' . $m[1] . ']';
    }
    return 'ExponentPushToken[' . $t . ']';
}

function expo_send_push(string $expoPushToken, string $title, string $body, array $data = []): array {
    $token = expo_normalize_token($expoPushToken);
    if ($token === '') return ['ok' => false, 'httpStatus' => 0, 'error' => 'missing token'];

    $message = [
        'to' => $token,
        'sound' => 'default',
        'title' => $title,
        'body' => $body,
        'data' => (object)($data ?: ['someData' => 'goes here']),
    ];
    $url = 'https://exp.host/--/api/v2/push/send';
    $payload = json_encode($message, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-encoding: gzip, deflate',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = $errno ? curl_error($ch) : null;
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) return ['ok' => false, 'httpStatus' => $status, 'error' => $err ?: 'curl error', 'raw' => $raw];
        $decoded = null;
        if (is_string($raw) && $raw !== '') $decoded = json_decode($raw, true);
        return ['ok' => ($status >= 200 && $status < 300), 'httpStatus' => $status, 'response' => $decoded ?? $raw];
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nAccept-encoding: gzip, deflate\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 8,
        ]
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $h, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
    }
    $decoded = null;
    if (is_string($raw) && $raw !== '') $decoded = json_decode($raw, true);
    return ['ok' => ($status >= 200 && $status < 300), 'httpStatus' => $status, 'response' => $decoded ?? $raw];
}

function expo_send_push_for_account(mysqli $conn, int $accountId, string $title, string $body, string $actionUrl = '', array $data = []): array {
    if ($accountId <= 0) return ['ok' => false, 'error' => 'invalid accountId'];

    // accounts.expoPushToken 컬럼이 없으면 발송 불가
    $cols = [];
    $cr = $conn->query("SHOW COLUMNS FROM accounts");
    while ($cr && ($row = $cr->fetch_assoc())) $cols[strtolower($row['Field'])] = true;
    if (!isset($cols['expopushtoken'])) return ['ok' => true, 'skipped' => 'no column'];

    $st = $conn->prepare("SELECT expoPushToken FROM accounts WHERE accountId = ? LIMIT 1");
    if (!$st) return ['ok' => false, 'error' => 'prepare failed'];
    $st->bind_param('i', $accountId);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    $token = trim((string)($row['expoPushToken'] ?? ''));
    if ($token === '') return ['ok' => true, 'skipped' => 'no token'];

    $payload = $data;
    if ($actionUrl !== '') $payload['actionUrl'] = $actionUrl;
    return expo_send_push($token, $title, $body, $payload);
}


