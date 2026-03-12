<?php
/**
 * Postmark API mailer
 *
 * Configure via environment variables:
 * - POSTMARK_API_TOKEN (Server API Token)
 * - MAIL_FROM (sender email)
 * - MAIL_FROM_NAME (sender name, default "SMT Escape")
 */

function mailer_send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): array {
    // [TEST SERVER] 실제 이메일 발송 비활성화 - 로그만 기록
    error_log("[MAIL-DRY-RUN] To: {$toEmail} | Subject: {$subject}");
    return ['ok' => true, 'via' => 'dry-run', 'message_id' => 'dry-run-' . uniqid()];

    $apiToken = trim((string)getenv('POSTMARK_API_TOKEN'));
    $from = trim((string)getenv('MAIL_FROM'));
    $fromName = trim((string)getenv('MAIL_FROM_NAME')) ?: 'SMT Escape';

    if ($apiToken === '' || $from === '') {
        return [
            'ok' => false,
            'via' => 'none',
            'error' => 'Postmark API token or FROM email not configured',
        ];
    }

    $fromHeader = sprintf('%s <%s>', $fromName, $from);

    $payload = json_encode([
        'From' => $fromHeader,
        'To' => $toEmail,
        'Subject' => $subject,
        'HtmlBody' => $htmlBody,
        'TextBody' => $textBody !== '' ? $textBody : strip_tags($htmlBody),
        'MessageStream' => 'outbound',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Postmark-Server-Token: ' . $apiToken,
            ]),
            'content' => $payload,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents('https://api.postmarkapp.com/email', false, $context);

    if ($response === false) {
        return [
            'ok' => false,
            'via' => 'postmark',
            'error' => 'Failed to connect to Postmark API',
        ];
    }

    $result = json_decode($response, true);

    // HTTP 상태 코드 확인
    $httpCode = 0;
    if (isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $httpCode = (int)($matches[1] ?? 0);
    }

    if ($httpCode === 200 && isset($result['MessageID'])) {
        return [
            'ok' => true,
            'via' => 'postmark',
            'message_id' => $result['MessageID'],
        ];
    }

    return [
        'ok' => false,
        'via' => 'postmark',
        'error' => $result['Message'] ?? "HTTP {$httpCode}",
        'error_code' => $result['ErrorCode'] ?? null,
    ];
}
