<?php
/**
 * Passport OCR API - AWS Textract AnalyzeID
 * POST: 여권 이미지를 받아 텍스트 추출 후 반환
 */

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../config/aws_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\Textract\TextractClient;
use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

// POST만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 세션 인증 체크 (에이전트: agent_accountId, 관리자: user_id)
$isAgent = !empty($_SESSION['agent_accountId']);
$isAdmin = !empty($_SESSION['user_id']) && in_array($_SESSION['account_type'] ?? '', ['admin', 'admin_ph', 'admin_kr', 'employee']);
if (!$isAgent && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 파일 수신 체크
if (!isset($_FILES['passport_image']) || $_FILES['passport_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image file uploaded']);
    exit;
}

$file = $_FILES['passport_image'];

// 파일 타입 검증
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only JPEG and PNG images are supported']);
    exit;
}

// 파일 크기 제한 (10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
    exit;
}

// 이미지 바이트 읽기
$imageBytes = file_get_contents($file['tmp_name']);
if ($imageBytes === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to read image file']);
    exit;
}

try {
    $textract = new TextractClient([
        'region'  => 'us-east-1',  // AnalyzeID는 us-east-1에서만 지원
        'version' => AWS_TEXTRACT_VERSION,
    ]);

    $result = $textract->analyzeID([
        'DocumentPages' => [
            [
                'Bytes' => $imageBytes,
            ],
        ],
    ]);

    // IdentityDocuments에서 필드 추출
    $identityDocuments = $result->get('IdentityDocuments');
    if (empty($identityDocuments)) {
        echo json_encode([
            'success' => false,
            'message' => 'No identity document detected in the image'
        ]);
        exit;
    }

    $doc = $identityDocuments[0];
    $fields = $doc['IdentityDocumentFields'] ?? [];

    // 필드 매핑 (Textract 필드명 → 우리 필드명)
    $fieldMapping = [
        'FIRST_NAME'        => 'firstName',
        'LAST_NAME'         => 'lastName',
        'MIDDLE_NAME'       => 'middleName',
        'DATE_OF_BIRTH'     => 'dateOfBirth',
        'DOCUMENT_NUMBER'   => 'passportNumber',
        'DATE_OF_ISSUE'     => 'passportIssueDate',
        'EXPIRATION_DATE'   => 'passportExpiryDate',
        'PLACE_OF_BIRTH'    => 'placeOfBirth',
        'MRZ_CODE'          => 'mrzCode',
    ];

    // 국적 관련 필드 (여러 가능한 필드명)
    $nationalityFields = ['NATIONALITY', 'COUNTRY', 'PLACE_OF_ISSUE'];

    $extracted = [];
    $confidences = [];
    $placeOfBirthRaw = '';  // nationality 폴백용

    foreach ($fields as $field) {
        $type = $field['Type']['Text'] ?? '';
        $value = $field['ValueDetection']['Text'] ?? '';
        $confidence = $field['ValueDetection']['Confidence'] ?? 0;

        // 빈 값이면 스킵 (confidence만 높고 value가 없는 필드 무시)
        if (isset($fieldMapping[$type])) {
            $key = $fieldMapping[$type];
            if (trim($value) !== '') {
                $extracted[$key] = $value;
                $confidences[$key] = round($confidence, 1);
            }
        }

        if (in_array($type, $nationalityFields) && !isset($extracted['nationality']) && trim($value) !== '') {
            $extracted['nationality'] = $value;
            $confidences['nationality'] = round($confidence, 1);
        }

        // PLACE_OF_BIRTH를 nationality 폴백으로 저장
        if ($type === 'PLACE_OF_BIRTH' && trim($value) !== '' && $confidence > 50) {
            $placeOfBirthRaw = $value;
        }
    }

    // nationality가 없으면 PLACE_OF_BIRTH로 추정
    if (empty($extracted['nationality']) && !empty($placeOfBirthRaw)) {
        // 필리핀 여권 PLACE_OF_BIRTH에서 nationality 추정
        $pobLower = strtolower($placeOfBirthRaw);
        if (strpos($pobLower, 'filipin') !== false || strpos($pobLower, 'filirin') !== false || strpos($pobLower, 'philippin') !== false) {
            $extracted['nationality'] = 'FILIPINO';
            $confidences['nationality'] = 80;
        }
    }

    // nationality가 여전히 없으면 여권 문서번호 패턴으로 추정
    if (empty($extracted['nationality']) && !empty($extracted['passportNumber'])) {
        $passportNo = $extracted['passportNumber'];
        // 필리핀 여권번호: P + 7자리 숫자 + 1자리 알파벳 (예: P0887921D)
        if (preg_match('/^P\d{7}[A-Z]$/', $passportNo)) {
            $extracted['nationality'] = 'FILIPINO';
            $confidences['nationality'] = 70;
        }
    }

    // 성별 추출 — MRZ 코드에서 파싱 (MRZ 2번째 줄 21번째 문자가 성별)
    if (!empty($extracted['mrzCode'])) {
        $mrz = $extracted['mrzCode'];
        // MRZ는 보통 2줄, 각 44자. 성별은 2번째 줄 위치 20 (0-indexed)
        // 전체 문자열에서 44+20 = 64번째 위치
        $genderChar = '';
        if (strlen($mrz) >= 65) {
            $genderChar = strtoupper(substr($mrz, 64, 1));
        } else {
            // MRZ에서 M 또는 F를 찾기 (날짜 사이에 위치)
            if (preg_match('/\d{6}([MF<])\d{6}/', $mrz, $m)) {
                $genderChar = $m[1];
            }
        }
        if ($genderChar === 'M') {
            $extracted['gender'] = 'male';
            $confidences['gender'] = $confidences['mrzCode'] ?? 90;
        } else if ($genderChar === 'F') {
            $extracted['gender'] = 'female';
            $confidences['gender'] = $confidences['mrzCode'] ?? 90;
        }
    }

    // 날짜 포맷 정규화
    $dateFields = ['dateOfBirth', 'passportIssueDate', 'passportExpiryDate'];
    foreach ($dateFields as $dateField) {
        if (isset($extracted[$dateField])) {
            $normalized = normalizeDateFormat($extracted[$dateField]);
            if ($normalized) {
                $extracted[$dateField] = $normalized;
            }
        }
    }

    echo json_encode([
        'success'     => true,
        'data'        => $extracted,
        'confidences' => $confidences,
    ]);

} catch (AwsException $e) {
    $errorCode = $e->getAwsErrorCode();
    $errorMsg = $e->getAwsErrorMessage();

    error_log("Textract AnalyzeID error: [{$errorCode}] {$errorMsg}");

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'OCR processing failed: ' . ($errorMsg ?: 'Unknown error'),
        'code'    => $errorCode,
    ]);
} catch (\Throwable $e) {
    $logFile = __DIR__ . '/../../logs/ocr_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: " . get_class($e) . " | " . $e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'OCR error: ' . $e->getMessage(),
    ]);
}

/**
 * 다양한 날짜 형식을 YYYY-MM-DD로 정규화
 */
function normalizeDateFormat(string $dateStr): ?string {
    $dateStr = trim($dateStr);
    if (empty($dateStr)) return null;

    // 이미 YYYY-MM-DD 형식이면 그대로
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }

    // 월 이름 약어 매핑
    $months = [
        'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
        'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
        'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
        'JANUARY' => '01', 'FEBRUARY' => '02', 'MARCH' => '03',
        'APRIL' => '04', 'JUNE' => '06', 'JULY' => '07',
        'AUGUST' => '08', 'SEPTEMBER' => '09', 'OCTOBER' => '10',
        'NOVEMBER' => '11', 'DECEMBER' => '12',
    ];

    // DD MMM YYYY or DD/MMM/YYYY (여권에서 흔한 형식: 15 JAN 1990, 15/JAN/1990)
    if (preg_match('/(\d{1,2})[\s\/\-]([A-Za-z]+)[\s\/\-](\d{4})/', $dateStr, $m)) {
        $month = $months[strtoupper($m[2])] ?? null;
        if ($month) {
            return sprintf('%s-%s-%02d', $m[3], $month, (int)$m[1]);
        }
    }

    // MM/DD/YYYY or MM-DD-YYYY
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $dateStr, $m)) {
        return sprintf('%s-%s-%s', $m[3], $m[1], $m[2]);
    }

    // DD/MM/YYYY (유럽식 - day > 12이면 확실)
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $dateStr, $m)) {
        if ((int)$m[1] > 12) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
    }

    // YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/-](\d{2})[\/-](\d{2})$/', $dateStr, $m)) {
        return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
    }

    // DDMMYYYY (MRZ 형식 등)
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dateStr, $m)) {
        return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
    }

    // strtotime 시도
    $ts = strtotime($dateStr);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}
