<?php
/**
 * log_js_error.php
 * Receives JS errors from the frontend and writes them via AppLogger.
 *
 * Expects a POST request with JSON body:
 * {
 *   "message": "Something went wrong",
 *   "source":  "nav_super.php",
 *   "level":   "error" | "warn" | "info",
 *   "context": { ...optional key-value pairs }
 * }
 *
 * Usage (frontend):
 *   NavLogger.remote('Missing panel', 'error', { selector: '#sub-dashboard' });
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/error_logger.php'; // adjust path if needed

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$message = isset($data['message']) && is_string($data['message'])
    ? trim($data['message'])
    : 'JS error (no message provided)';

$source  = isset($data['source']) && is_string($data['source'])
    ? trim($data['source'])
    : 'frontend';

$level   = in_array($data['level'] ?? '', ['debug', 'info', 'warn', 'error'], true)
    ? $data['level']
    : 'error';

$context = isset($data['context']) && is_array($data['context'])
    ? $data['context']
    : [];

// Append user-agent and IP for traceability
$context['_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$context['_ip'] = $_SERVER['REMOTE_ADDR']     ?? 'unknown';

// ── Log it ────────────────────────────────────────────────────────────────────
AppLogger::log($message, $source, $level, $context);

// ── Respond ───────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['ok' => true]);