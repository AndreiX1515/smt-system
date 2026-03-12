<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 주소 검색 API (Google Places Text Search)
// - 관리자 템플릿 등록 등에서 주소 검색 모달이 필요합니다.
// - 브라우저 SDK(도메인 제한) 이슈를 피하기 위해 서버에서 Google Places API를 호출합니다.
// - API KEY는 코드에 포함하지 않고 환경변수로만 받습니다.

$q = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));
if ($q === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Query is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey =
    (string)($_ENV['GOOGLE_MAPS_API_KEY'] ?? '')
    ?: (string)(getenv('GOOGLE_MAPS_API_KEY') ?: '');
if ($apiKey === '') {
    // 운영/스테이징에서 키가 누락된 경우가 있어도,
    // 관리자 UI에서 클라이언트 fallback(카카오 등)을 할 수 있도록 200으로 내려준다.
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'GOOGLE_MAPS_API_KEY is not configured on the server.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lang = strtolower(trim((string)($_GET['lang'] ?? 'en')));
// Google supports 'en', 'ko', 'tl' (Filipino) in many APIs.
if (!in_array($lang, ['en', 'ko', 'tl'], true)) $lang = 'en';

$endpoint = 'https://maps.googleapis.com/maps/api/place/textsearch/json'
    . '?query=' . urlencode($q)
    . '&language=' . urlencode($lang)
    . '&key=' . urlencode($apiKey);

$raw = null;
try {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code < 200 || $code >= 300) {
            throw new Exception('Google Places request failed (HTTP ' . $code . ')');
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($endpoint, false, $ctx);
        if ($raw === false) throw new Exception('Google Places request failed');
    }
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Address search failed: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$json = json_decode((string)$raw, true);
$status = strtoupper((string)($json['status'] ?? ''));
if ($status !== 'OK' && $status !== 'ZERO_RESULTS') {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Google Places error: ' . ($json['status'] ?? 'UNKNOWN'),
        'details' => $json['error_message'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = [];
foreach (($json['results'] ?? []) as $r) {
    if (!is_array($r)) continue;
    $name = (string)($r['name'] ?? '');
    $addr = (string)($r['formatted_address'] ?? ($r['vicinity'] ?? $name));
    $lat = $r['geometry']['location']['lat'] ?? null;
    $lng = $r['geometry']['location']['lng'] ?? null;
    $results[] = [
        'name' => $name ?: $addr,
        'address' => $addr ?: $name,
        'lat' => is_numeric($lat) ? (float)$lat : null,
        'lng' => is_numeric($lng) ? (float)$lng : null,
    ];
    if (count($results) >= 10) break;
}

echo json_encode([
    'success' => true,
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
exit;



