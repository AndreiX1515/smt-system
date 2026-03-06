<?php
/**
 * Agent Admin API
 * 모든 Agent 관련 API 엔드포인트를 처리합니다.
 */

// 출력 버퍼링 시작 (에러 캡처를 위해)
ob_start();

// 개발 환경에서 에러 표시 (디버깅용)
// 다운로드 응답(CSV/파일)에 PHP warning/notice가 섞이면 파일이 깨지므로 화면 출력은 끄고 로그로만 남깁니다.
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// 기존 backend/conn.php 사용
$conn_file = __DIR__ . '/../../../backend/conn.php';
if (!file_exists($conn_file)) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found: ' . $conn_file . ' | Resolved: ' . realpath($conn_file) . ' | __DIR__: ' . __DIR__
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once $conn_file;
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load database connection: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 데이터베이스 연결 확인
if (!isset($conn) || !$conn) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not established'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Email notification service for booking confirmations
require_once __DIR__ . '/../../../backend/services/email_notification_service.php';

// Booking payments helper library
require_once __DIR__ . '/../../../backend/lib/booking_payments.php';

// JSON 응답 헬퍼 함수 (conn.php에 이미 정의되어 있을 수 있으므로 확인)
if (!function_exists('send_json_response')) {
    function send_json_response($data, $status_code = 200) {
        if (ob_get_level() > 0) {
            ob_clean(); // 출력 버퍼 지우기
        }
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (ob_get_level() > 0) {
            ob_end_flush(); // 출력 버퍼 플러시
        }
        exit;
    }
}

// 에러 응답 함수
if (!function_exists('send_error_response')) {
    function send_error_response($message, $status_code = 400) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit;
    }
}

// 성공 응답 함수
if (!function_exists('send_success_response')) {
    function send_success_response($data = [], $message = 'Success') {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit;
    }
}

// PHP 8+에서 bind_param 가변 인자(by-ref) 안전 처리
if (!function_exists('mysqli_bind_params_by_ref')) {
    /**
     * @param mysqli_stmt $stmt
     * @param string $types
     * @param array $params (값 배열; 내부에서 참조로 변환)
     */
    function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array &$params): void {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $_) {
            $bind[] = &$params[$k];
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
            throw new RuntimeException('Failed to bind params');
        }
    }
}

// Visa 관련 헬퍼 함수들 (switch 전에 미리 정의)
if (!function_exists('__agent_visa_applications_has_column')) {
    function __agent_visa_applications_has_column(mysqli $conn, string $c): bool {
        $r = $conn->query("SHOW COLUMNS FROM visa_applications LIKE '$c'");
        return ($r && $r->num_rows > 0);
    }
}

if (!function_exists('__agent_ensure_visa_applications_updated_at')) {
    function __agent_ensure_visa_applications_updated_at(mysqli $conn): void {
        try {
            $t = $conn->query("SHOW TABLES LIKE 'visa_applications'");
            if (!$t || $t->num_rows === 0) return;
            if (__agent_visa_applications_has_column($conn, 'updatedAt')) return;
            $conn->query("ALTER TABLE visa_applications ADD COLUMN updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Throwable $e) {}
    }
}

if (!function_exists('__agent_mapVisaDbToUiStatus')) {
    function __agent_mapVisaDbToUiStatus(string $db): string {
        $db = strtolower(trim($db));
        if ($db === 'document_required' || $db === 'pending') return 'pending';
        if ($db === 'under_review') return 'reviewing';
        if ($db === 'approved' || $db === 'completed') return 'approved';
        if ($db === 'rejected') return 'rejected';
        return 'pending';
    }
}

if (!function_exists('__agent_mapVisaUiToDbStatus')) {
    function __agent_mapVisaUiToDbStatus(string $ui): string {
        $ui = strtolower(trim($ui));
        if ($ui === 'reviewing' || $ui === 'under_review') return 'under_review';
        if ($ui === 'approved') return 'approved';
        if ($ui === 'rejected') return 'rejected';
        return 'pending';
    }
}

if (!function_exists('__agent_verify_visa_ownership')) {
    function __agent_verify_visa_ownership(mysqli $conn, int $agentAccountId, int $visaApplicationId): bool {
        $sql = "SELECT 1 FROM visa_applications v
                JOIN bookings b ON v.transactNo = b.bookingId
                WHERE v.applicationId = ? AND b.agentId = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $visaApplicationId, $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = ($result->num_rows > 0);
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('__agent_extractVisaDocumentsFromNotes')) {
    function __agent_extractVisaDocumentsFromNotes($notes): array {
        if ($notes === null) return [];
        $txt = trim((string)$notes);
        if ($txt === '') return [];
        $j = json_decode($txt, true);
        if (is_array($j) && isset($j['documents']) && is_array($j['documents'])) {
            return $j['documents'];
        }
        return [];
    }
}

if (!function_exists('__agent_extractVisaFileFromNotes')) {
    function __agent_extractVisaFileFromNotes($notes): string {
        if ($notes === null) return '';
        $txt = trim((string)$notes);
        if ($txt === '') return '';
        $j = json_decode($txt, true);
        if (!is_array($j)) return '';
        $v = $j['visaFile'] ?? ($j['visa_file'] ?? ($j['visaUrl'] ?? ($j['visaDocument'] ?? '')));
        return trim((string)$v);
    }
}

if (!function_exists('__agent_mergeVisaNotesSetKey')) {
    function __agent_mergeVisaNotesSetKey($existingNotes, string $key, $value): string {
        $base = [];
        $txt = trim((string)($existingNotes ?? ''));
        if ($txt !== '') {
            $j = json_decode($txt, true);
            if (is_array($j)) $base = $j;
            else $base = ['notesText' => $txt];
        }
        $base[$key] = $value;
        return json_encode($base, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('__agent_computeVisaDerivedStatus')) {
    function __agent_computeVisaDerivedStatus(string $notesJson): string {
        $notesJson = trim($notesJson);
        $docs = __agent_extractVisaDocumentsFromNotes($notesJson);
        $visaFile = __agent_extractVisaFileFromNotes($notesJson);
        if (trim((string)$visaFile) !== '') return 'approved';

        $requiredNew = ['passport', 'visaApplicationForm', 'bankCertificate', 'bankStatement'];

        $hasNewStyleKeys = false;
        foreach ($requiredNew as $k) {
            if (array_key_exists($k, $docs)) {
                $hasNewStyleKeys = true;
                break;
            }
        }

        $presentNew = 0;
        foreach ($requiredNew as $k) {
            $p = isset($docs[$k]) ? trim((string)$docs[$k]) : '';
            if ($p !== '') $presentNew++;
        }

        $isNewStyleApp = $hasNewStyleKeys || $presentNew > 0;

        if ($isNewStyleApp) {
            if ($presentNew === count($requiredNew)) return 'reviewing';
            return 'rejected';
        }

        return 'pending';
    }
}

// 요청 메서드 확인
$method = $_SERVER['REQUEST_METHOD'];

// JSON 입력 받기 (먼저 JSON body를 읽어서 action도 포함)
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET 파라미터 병합
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

// POST 데이터와 병합
if ($method === 'POST' && !empty($_POST)) {
    $input = array_merge($input, $_POST);
}

// 멀티파트로 전달된 JSON 페이로드 처리
if (isset($input['data']) && is_string($input['data'])) {
    $decodedPayload = json_decode($input['data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
        $input = array_merge($input, $decodedPayload);
    }
    unset($input['data']);
}

// action 파라미터 확인 (GET, POST, JSON body 모두에서 확인)
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
    switch ($action) {
        // ========== Overview 관련 ==========
        case 'getOverview':
            getOverview($conn);
            break;
            
        case 'getTodayItineraries':
            getTodayItineraries($conn);
            break;

        case 'getBestPricePackages':
            getBestPricePackages($conn);
            break;

        case 'getSaleProducts':
            getSaleProducts($conn);
            break;

        case 'getWaitingCancelledBookings':
            getWaitingCancelledBookings($conn);
            break;

        case 'extendPaymentDeadline':
            extendPaymentDeadline($conn, $input);
            break;

        case 'getAgentDepositRate':
            getAgentDepositRate($conn);
            break;
            
        // ========== 예약 관련 ==========
        case 'getReservations':
            getReservations($conn, $input);
            break;
            
        case 'getReservationDetail':
            getReservationDetail($conn, $input);
            break;

        // ========== 집합 위치/공지사항(가이드 등록 내역) ==========
        case 'getLocationHistory':
            getLocationHistory($conn, $input);
            break;

        case 'getLatestMeetingLocation':
            getLatestMeetingLocation($conn, $input);
            break;

        case 'getMeetingLocationDetail':
            getMeetingLocationDetail($conn, $input);
            break;

        case 'getNotices':
            getNotices($conn, $input);
            break;

        case 'createNotice':
            createNotice($conn, $input);
            break;

        case 'getLatestNotice':
            getLatestNotice($conn, $input);
            break;

        case 'getNoticeDetail':
            getNoticeDetail($conn, $input);
            break;
            
        case 'createReservation':
            createReservation($conn, $input);
            break;
            
        case 'updatePaymentInfo':
            updatePaymentInfo($conn, $input);
            break;

        case 'deleteIncompleteReservation':
            deleteIncompleteReservation($conn, $input);
            break;

        case 'updateReservationStatus':
            updateReservationStatus($conn, $input);
            break;
            
        case 'confirmDeposit':
            confirmDeposit($conn, $input);
            break;
            
        case 'confirmBalance':
            confirmBalance($conn, $input);
            break;
            
        case 'removeDepositProofFile':
            removeDepositProofFile($conn, $input);
            break;
            
        case 'setPaymentDeadline':
            setPaymentDeadline($conn, $input);
            break;
            
        case 'cancelReservation':
            cancelReservation($conn, $input);
            break;

        case 'uploadProofFile':
            uploadProofFile($conn, $input);
            break;
            
        case 'downloadProofFile':
            downloadProofFile($conn, $input);
            break;
            
        case 'removeProofFile':
            removeProofFile($conn, $input);
            break;

        // 3단계 결제 증빙 파일 관리
        case 'uploadPaymentProofFile':
            uploadPaymentProofFile($conn, $input);
            break;

        case 'downloadPaymentProofFile':
            downloadPaymentProofFile($conn, $input);
            break;

        case 'deletePaymentProofFile':
            deletePaymentProofFile($conn, $input);
            break;

        // ========== 고객 관련 ==========
        case 'getCustomers':
            getCustomers($conn, $input);
            break;
            
        case 'getCustomerDetail':
            getCustomerDetail($conn, $input);
            break;
            
        case 'createCustomer':
            createCustomer($conn, $input);
            break;
            
        case 'updateCustomer':
            updateCustomer($conn, $input);
            break;
            
        case 'deleteCustomer':
            deleteCustomer($conn, $input);
            break;
            
        case 'resetPassword':
            resetPassword($conn, $input);
            break;
            
        case 'downloadCustomers':
            downloadCustomers($conn, $input);
            break;
            
        case 'downloadCustomerSample':
            downloadCustomerSample();
            break;
            
        case 'downloadReservations':
            downloadReservations($conn, $input);
            break;
            
        case 'batchUploadCustomers':
            batchUploadCustomers($conn);
            break;

        case 'searchB2CCustomer':
            searchB2CCustomer($conn, $input);
            break;

        case 'assignExistingCustomer':
            assignExistingCustomer($conn, $input);
            break;

        // ========== 문의 관련 ==========
        case 'getInquiries':
            getInquiries($conn, $input);
            break;
            
        case 'getInquiryDetail':
            getInquiryDetail($conn, $input);
            break;
            
        case 'createInquiry':
        case 'registerInquiry':
            createInquiry($conn, $input);
            break;
            
        case 'updateInquiry':
            updateInquiry($conn, $input);
            break;

        case 'downloadInquiryAttachment':
            downloadInquiryAttachment($conn, $input);
            break;

        // ========== 메시지 관리 ==========
        case 'getMessageThreads':
            getAgentMessageThreads($conn, $input);
            break;
        case 'getMessageThreadDetail':
            getAgentMessageThreadDetail($conn, $input);
            break;
        case 'createMessageThread':
            createAgentMessageThread($conn, $input);
            break;
        case 'getMessageThreadByBookingId':
            getAgentMessageThreadByBookingId($conn, $input);
            break;
        case 'sendMessage':
            handleAgentSendMessage($conn, $input);
            break;
        case 'getMessageUnreadCount':
            getAgentMessageUnreadCount($conn, $input);
            break;

        // ========== 항공편 정보 관련 ==========
        case 'getFlightInfo':
            getFlightInfo($conn, $input);
            break;
        case 'getPackageFlights':
            getPackageFlights($conn, $input);
            break;

        // ========== 24시간 내 수정 관련 ==========
        case 'updateCustomerInfo':
            updateCustomerInfo($conn, $input);
            break;
        case 'searchPackages':
            searchPackagesForAgent($conn, $input);
            break;

        case 'updateTravelerInfo':
            updateTravelerInfo($conn, $input);
            break;

        case 'acknowledgeRejection':
            acknowledgeRejectionAgent($conn, $input);
            break;

        case 'updateRoomOptions':
            updateRoomOptions($conn, $input);
            break;

        case 'getRoomingAssignments':
            getRoomingAssignments($conn, $input);
            break;
        case 'saveRoomingAssignments':
            saveAgentRoomingAssignments($conn, $input);
            break;

        case 'getAirlineOptionsByName':
            getAirlineOptionsByName($conn, $input);
            break;

        case 'saveTravelerOptions':
            saveTravelerOptions($conn, $input);
            break;

        // ========== 비자 관리 ==========
        case 'getAgentVisaApplications':
            getAgentVisaApplications($conn, $input);
            break;

        case 'getAgentVisaApplicationDetail':
            getAgentVisaApplicationDetail($conn, $input);
            break;

        case 'updateAgentVisaDocument':
            updateAgentVisaDocument($conn, $input);
            break;

        case 'deleteAgentVisaDocument':
            deleteAgentVisaDocument($conn, $input);
            break;

        case 'updateAgentVisaFile':
            updateAgentVisaFile($conn, $input);
            break;

        case 'deleteAgentVisaFile':
            deleteAgentVisaFile($conn, $input);
            break;

        case 'updateAgentVisaSend':
            updateAgentVisaSend($conn, $input);
            break;

        case 'getVisaApplicationsByBookingId':
            getVisaApplicationsByBookingId($conn, $input);
            break;

        // ========== Extra Options 관련 ==========
        case 'getExtraOptionsList':
            getExtraOptionsList($conn, $input);
            break;

        case 'getExtraOptionsDetail':
            getExtraOptionsDetail($conn, $input);
            break;

        case 'saveExtraOptions':
            saveExtraOptions($conn, $input);
            break;

        case 'uploadOptionPaymentProof':
            uploadOptionPaymentProof($conn, $input);
            break;

        case 'deleteOptionPaymentProof':
            deleteOptionPaymentProof($conn, $input);
            break;

        default:
            // 브라우저/확장프로그램/프리로드 등으로 agent-api.php가 파라미터 없이 호출되는 경우가 있어
            // 콘솔에 400이 남는 문제를 방지합니다. (의도치 않은 GET에 한해 조용히 종료)
            if ($method === 'GET' && ($action === null || $action === '') && empty($_GET)) {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                http_response_code(204); // No Content
                exit;
            }
            send_error_response('Invalid action: ' . $action, 400);
    }
} catch (Exception $e) {
    ob_clean(); // 출력 버퍼 지우기
    error_log('API Error: ' . $e->getMessage());
    error_log('API Trace: ' . $e->getTraceAsString());
    send_error_response('Server error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(), 500);
} catch (Error $e) {
    ob_clean(); // 출력 버퍼 지우기
    error_log('API Fatal Error: ' . $e->getMessage());
    error_log('API Trace: ' . $e->getTraceAsString());
    send_error_response('Fatal error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(), 500);
} catch (Throwable $e) {
    ob_clean(); // 출력 버퍼 지우기
    error_log('API Throwable Error: ' . $e->getMessage());
    error_log('API Trace: ' . $e->getTraceAsString());
    send_error_response('Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(), 500);
}

/**
 * Agent 문의 첨부파일 다운로드
 * - 새 창으로 열리는 대신 Content-Disposition: attachment 로 강제 다운로드
 * - 보안: 로그인한 agent 본인 문의(inquiries.accountId) + 해당 inquiryId의 첨부(inquiry_attachments.filePath)만 허용
 */
function normalize_inquiry_attachment_rel_path(string $path): string {
    $s = trim($path);
    if ($s === '') return '';
    $s = str_replace('\\', '/', $s);

    // data URL, javascript 등은 차단
    if (preg_match('/^\s*(data|javascript):/i', $s)) return '';

    // URL이면 uploads/ 이후만 허용
    if (preg_match('/^https?:\/\/[^\/]+\/(uploads\/.*)$/i', $s, $m)) {
        $s = $m[1];
    }

    // 절대경로(/uploads/...)면 리딩 슬래시 제거
    if (str_starts_with($s, '/')) {
        $s = ltrim($s, '/');
    }

    // 일부 환경에서 /smart-travel2/ 등이 섞여 저장되는 케이스 정리
    $s = str_replace('smart-travel2/', '', $s);
    $s = ltrim($s, '/');
    $s = preg_replace('#/uploads/uploads/#', '/uploads/', $s);
    $s = ltrim($s, '/');

    // uploads/ 하위만 허용 (보안)
    if (!preg_match('#^uploads/#', $s)) return '';
    if (strpos($s, '..') !== false) return '';
    return $s;
}

function downloadInquiryAttachment($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $inquiryId = $input['inquiryId'] ?? $input['id'] ?? null;
        $filePath = (string)($input['filePath'] ?? '');
        if (empty($inquiryId) || empty($filePath)) {
            send_error_response('Inquiry ID and filePath are required', 400);
        }
        $reqRel = normalize_inquiry_attachment_rel_path($filePath);
        if ($reqRel === '') {
            send_error_response('Invalid file path', 400);
        }

        // 첨부 테이블 확인
        $t = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
        if (!$t || $t->num_rows === 0) {
            send_error_response('Attachment not found', 404);
        }

        // filePath 컬럼명 변형(path) 대응
        $cols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM inquiry_attachments");
        if ($colRes) {
            while ($r = $colRes->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);
        }
        $pathCol = in_array('filepath', $cols, true) ? 'filePath' : (in_array('path', $cols, true) ? 'path' : 'filePath');
        $nameCol = in_array('originalname', $cols, true) ? 'originalName' : (in_array('filename', $cols, true) ? 'fileName' : (in_array('name', $cols, true) ? 'name' : 'fileName'));
        $typeCol = in_array('filetype', $cols, true) ? 'fileType' : (in_array('type', $cols, true) ? 'type' : null);
        $sizeCol = in_array('filesize', $cols, true) ? 'fileSize' : (in_array('size', $cols, true) ? 'size' : null);

        // NOTE: filePath 저장형태(상대/절대/URL)가 환경마다 달라서
        //       SQL에서 `= ?`로 정확히 매칭하지 않고, 문의ID+작성자 범위로 모두 가져와 PHP에서 정규화 매칭한다.
        $sql = "SELECT ia.`{$pathCol}` AS filePath, ia.`{$nameCol}` AS originalName"
            . ($typeCol ? ", ia.`{$typeCol}` AS fileType" : ", '' AS fileType")
            . ($sizeCol ? ", ia.`{$sizeCol}` AS fileSize" : ", NULL AS fileSize")
            . " FROM inquiry_attachments ia
                INNER JOIN inquiries i ON ia.inquiryId = i.inquiryId
               WHERE ia.inquiryId = ? AND i.accountId = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) send_error_response('Failed to prepare download query: ' . ($conn->error ?: 'unknown'), 500);
        $iid = (int)$inquiryId;
        $aid = (int)$agentAccountId;
        $stmt->bind_param('ii', $iid, $aid);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if (!$res || $res->num_rows <= 0) {
            send_error_response('Attachment not found', 404);
        }

        $row = null;
        $rel = '';
        while ($r = $res->fetch_assoc()) {
            $dbRel = normalize_inquiry_attachment_rel_path((string)($r['filePath'] ?? ''));
            if ($dbRel !== '' && $dbRel === $reqRel) {
                $row = $r;
                $rel = $dbRel;
                break;
            }
        }
        if (!$row || $rel === '') {
            send_error_response('Attachment not found', 404);
        }

        $absolutePath = __DIR__ . '/../../../' . $rel;
        if (!is_file($absolutePath)) {
            send_error_response('File not found', 404);
        }

        $origName = (string)($row['originalName'] ?? basename($absolutePath));
        if ($origName === '') $origName = basename($absolutePath);
        $mime = @mime_content_type($absolutePath) ?: 'application/octet-stream';
        $size = filesize($absolutePath);

        while (ob_get_level() > 0) { @ob_end_clean(); }

        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName);
        if ($fallback === '' || $fallback === null) $fallback = 'attachment';
        $utf8Name = rawurlencode($origName);

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Transfer-Encoding: binary');
        header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''{$utf8Name}");
        if (is_numeric($size)) header('Content-Length: ' . $size);
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($absolutePath);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download attachment: ' . $e->getMessage(), 500);
    }
}

// ========== Overview 함수들 ==========

/**
 * agent 테이블의 스키마 차이를 흡수하면서 branch/company 범위를 조회합니다.
 * (운영/로컬 환경에 따라 branchId/companyId 컬럼이 없거나 이름이 다를 수 있음)
 */
function get_agent_scope($conn, $agentAccountId) {
    $scope = [
        'branchId' => null,
        'companyId' => null,
        'agentBranchCol' => null,
        'agentCompanyCol' => null,
    ];

    // agent 테이블 존재 확인
    $agentTable = $conn->query("SHOW TABLES LIKE 'agent'");
    if (!$agentTable || $agentTable->num_rows === 0) {
        return $scope;
    }

    // 컬럼 맵 (lowercase => actual)
    $cols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM agent");
    if ($colRes) {
        while ($col = $colRes->fetch_assoc()) {
            $f = (string)($col['Field'] ?? '');
            if ($f !== '') $cols[strtolower($f)] = $f;
        }
    }

    $branchCol = $cols['branchid'] ?? $cols['branch_id'] ?? null;
    $companyCol = $cols['companyid'] ?? $cols['company_id'] ?? null;
    $scope['agentBranchCol'] = $branchCol;
    $scope['agentCompanyCol'] = $companyCol;

    // 둘 다 없으면 조회 불가(필터 미적용)
    if (!$branchCol && !$companyCol) {
        return $scope;
    }

    // SELECT 리스트 구성
    $selectParts = [];
    $selectParts[] = $branchCol ? ("`{$branchCol}` AS branchId") : ("NULL AS branchId");
    $selectParts[] = $companyCol ? ("`{$companyCol}` AS companyId") : ("NULL AS companyId");
    $sql = "SELECT " . implode(', ', $selectParts) . " FROM agent WHERE accountId = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return $scope;
    $stmt->bind_param('i', $agentAccountId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $scope['branchId'] = isset($row['branchId']) ? $row['branchId'] : null;
        $scope['companyId'] = isset($row['companyId']) ? $row['companyId'] : null;
    }
    $stmt->close();

    return $scope;
}

function getOverview($conn) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // agent 세션 확인
        // 보안: agent 전용 API는 agent_accountId만 허용 (일반 accountId로 우회 방지)
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;
        
        // 예약 현황: super admin과 동일한 상태값 기준 (pending 별도 카운트)
        $bookingStatusSql = "
            SELECT
                SUM(CASE WHEN b.bookingStatus = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN b.bookingStatus IN ('waiting_down_payment', 'checking_down_payment') THEN 1 ELSE 0 END) as waiting_down,
                SUM(CASE WHEN b.bookingStatus IN ('waiting_second_payment', 'checking_second_payment') THEN 1 ELSE 0 END) as waiting_second,
                SUM(CASE WHEN b.bookingStatus IN ('waiting_balance', 'checking_balance') THEN 1 ELSE 0 END) as waiting_balance,
                SUM(CASE WHEN b.bookingStatus = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM bookings b
            WHERE b.agentId = ?
            AND COALESCE(b.bookingStatus, '') != ''
            AND b.bookingStatus != 'draft'
        ";
        $bst = $conn->prepare($bookingStatusSql);
        if (!$bst) throw new Exception('Failed to prepare booking status query: ' . $conn->error);
        $bst->bind_param('i', $agentAccountId);
        $bst->execute();
        $bookingStatus = $bst->get_result()->fetch_assoc();
        $bst->close();
        
        // 문의 현황: 미답변, 처리중
        // inquiries 테이블 컬럼 확인
        $inquiryColumns = [];
        $inquiryColumnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($inquiryColumnResult) {
            while ($col = $inquiryColumnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        
        // 문의 현황: super admin과 동일하게 미답변/처리중으로 변경
        $hasInquiryId = in_array('inquiryid', $inquiryColumns, true);
        $replyTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
        $hasReplies = ($replyTableCheck && $replyTableCheck->num_rows > 0);
        $hasInquiryStatus = in_array('status', $inquiryColumns, true);

        $inquiryStatus = ['unanswered' => 0, 'processing' => 0];
        if ($hasInquiryId && $hasReplies) {
            // 미답변: reply가 없는 문의
            $unansweredSql = "
                SELECT COUNT(*) as count
                FROM inquiries i
                WHERE i.accountId = ?
                AND NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)
            ";
            $st = $conn->prepare($unansweredSql);
            if ($st) {
                $st->bind_param('i', $agentAccountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) $inquiryStatus['unanswered'] = (int)($row['count'] ?? 0);
            }

            // 처리중: status = 'in_progress'
            if ($hasInquiryStatus) {
                $processingSql = "
                    SELECT COUNT(*) as count
                    FROM inquiries i
                    WHERE i.accountId = ?
                    AND i.status = 'in_progress'
                ";
                $st = $conn->prepare($processingSql);
                if ($st) {
                    $st->bind_param('i', $agentAccountId);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    if ($row) $inquiryStatus['processing'] = (int)($row['count'] ?? 0);
                }
            }
        } else if ($hasInquiryStatus) {
            // fallback: status 컬럼 기반
            $sql = "SELECT
                        SUM(CASE WHEN status = 'open' OR status IS NULL THEN 1 ELSE 0 END) as unanswered,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as processing
                    FROM inquiries
                    WHERE accountId = ?";
            $st = $conn->prepare($sql);
            if ($st) {
                $st->bind_param('i', $agentAccountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) $inquiryStatus = $row;
            }
        }
        
        send_success_response([
            'bookingStatus' => [
                'pending' => (int)($bookingStatus['pending'] ?? 0),
                'waitingDown' => (int)($bookingStatus['waiting_down'] ?? 0),
                'waitingSecond' => (int)($bookingStatus['waiting_second'] ?? 0),
                'waitingBalance' => (int)($bookingStatus['waiting_balance'] ?? 0),
                'rejected' => (int)($bookingStatus['rejected'] ?? 0)
            ],
            'inquiryStatus' => [
                'unanswered' => (int)($inquiryStatus['unanswered'] ?? 0),
                'processing' => (int)($inquiryStatus['processing'] ?? 0)
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get overview: ' . $e->getMessage());
    }
}

function getWaitingCancelledBookings($conn) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $stmt = $conn->prepare("
            SELECT bookingId, packageName, departureDate, paymentType, updatedAt
            FROM bookings
            WHERE agentId = ? AND bookingStatus = 'waiting_cancelled'
            ORDER BY updatedAt ASC
        ");
        $stmt->bind_param('i', $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        $stmt->close();

        $now = new DateTime();
        $items = [];

        foreach ($bookings as $b) {
            $bookingId = $b['bookingId'];
            $payments = getPaymentsByBookingId($conn, $bookingId);

            // Determine lastPaidStep and nextUnpaidStep
            $lastPaidStep = null;
            $nextUnpaidStep = null;
            $nextUnpaidDueDate = null;
            foreach ($payments as $p) {
                if ($p['status'] === 'confirmed') {
                    $lastPaidStep = $p['paymentStep'];
                } else if ($nextUnpaidStep === null && ($p['status'] !== 'confirmed')) {
                    $nextUnpaidStep = $p['paymentStep'];
                    $nextUnpaidDueDate = $p['dueDate'] ?? null;
                }
            }

            // Get previousStatus from booking_status_history
            $previousStatus = null;
            $histStmt = $conn->prepare("
                SELECT previousStatus FROM booking_status_history
                WHERE bookingId = ? AND newStatus = 'waiting_cancelled'
                ORDER BY changedAt DESC LIMIT 1
            ");
            $histStmt->bind_param('s', $bookingId);
            $histStmt->execute();
            $histRow = $histStmt->get_result()->fetch_assoc();
            if ($histRow) {
                $previousStatus = $histRow['previousStatus'];
            }
            $histStmt->close();

            // Days until departure
            $depDate = new DateTime($b['departureDate']);
            $daysUntilDeparture = (int)$now->diff($depDate)->format('%r%a');

            // Grace deadline = updatedAt + 24 hours
            $updatedAt = new DateTime($b['updatedAt']);
            $graceDeadline = clone $updatedAt;
            $graceDeadline->modify('+24 hours');
            $graceRemainingSeconds = $graceDeadline->getTimestamp() - $now->getTimestamp();
            if ($graceRemainingSeconds < 0) {
                $graceRemainingSeconds = 0;
            }

            // waiting_cancelled 진입 횟수 체크 (2번 이상이면 자가 연장 불가)
            $wcCountStmt = $conn->prepare("
                SELECT COUNT(*) as cnt FROM booking_status_history
                WHERE bookingId = ? AND newStatus = 'waiting_cancelled'
            ");
            $wcCountStmt->bind_param('s', $bookingId);
            $wcCountStmt->execute();
            $wcCountRow = $wcCountStmt->get_result()->fetch_assoc();
            $wcCountStmt->close();
            $wcCount = (int)($wcCountRow['cnt'] ?? 0);
            $extensionExhausted = $wcCount >= 2;

            // 결제 증빙 업로드 이력 확인
            $hasAnyProof = false;
            foreach ($payments as $p) {
                if (!empty($p['filePath']) || $p['status'] === 'confirmed') {
                    $hasAnyProof = true;
                    break;
                }
            }

            // canExtend: paymentType != 'full' AND departure >= 34 AND wcCount < 2 AND hasProof
            $canExtend = $b['paymentType'] !== 'full'
                && $daysUntilDeparture >= 34
                && $wcCount < 2
                && $hasAnyProof;

            // 연장 불가 사유
            $cannotExtendReason = null;
            if (!$canExtend) {
                if ($b['paymentType'] === 'full') {
                    $cannotExtendReason = 'Full payment bookings cannot be extended.';
                } else if ($daysUntilDeparture < 34) {
                    $cannotExtendReason = 'Departure is within 34 days - extension not available.';
                } else if ($wcCount >= 2) {
                    $cannotExtendReason = '이 예약은 이미 waiting_cancelled 상태를 경험했기 때문에 자동 연장이 불가능합니다. 관리자에게 문의해주세요.';
                } else if (!$hasAnyProof) {
                    $cannotExtendReason = '결제 증빙이 업로드된 적 없는 예약은 연장할 수 없습니다.';
                }
            }

            $items[] = [
                'bookingId' => $bookingId,
                'packageName' => $b['packageName'],
                'departureDate' => $b['departureDate'],
                'paymentType' => $b['paymentType'],
                'lastPaidStep' => $lastPaidStep,
                'nextUnpaidStep' => $nextUnpaidStep,
                'nextUnpaidDueDate' => $nextUnpaidDueDate,
                'previousStatus' => $previousStatus,
                'daysUntilDeparture' => $daysUntilDeparture,
                'graceDeadline' => $graceDeadline->format('Y-m-d H:i:s'),
                'graceRemainingSeconds' => $graceRemainingSeconds,
                'canExtend' => $canExtend,
                'extensionExhausted' => $extensionExhausted,
                'cannotExtendReason' => $cannotExtendReason
            ];
        }

        send_success_response($items);
    } catch (Exception $e) {
        send_error_response('Failed to get waiting cancelled bookings: ' . $e->getMessage());
    }
}

function extendPaymentDeadline($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // Validate booking belongs to agent and is waiting_cancelled
        $stmt = $conn->prepare("
            SELECT bookingId, paymentType, departureDate, bookingStatus
            FROM bookings
            WHERE bookingId = ? AND agentId = ? AND bookingStatus = 'waiting_cancelled'
        ");
        $stmt->bind_param('si', $bookingId, $agentAccountId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            send_error_response('Booking not found or not eligible for extension');
        }

        // 결제 증빙이 하나라도 업로드된 적 있어야 연장 가능
        $payments = getPaymentsByBookingId($conn, $bookingId);
        $hasProof = false;
        foreach ($payments as $p) {
            if (!empty($p['filePath']) || $p['status'] === 'confirmed') {
                $hasProof = true;
                break;
            }
        }
        if (!$hasProof) {
            send_error_response('결제 증빙이 업로드된 적 없는 예약은 연장할 수 없습니다.');
        }

        $now = new DateTime();
        $depDate = new DateTime($booking['departureDate']);
        $daysUntilDeparture = (int)$now->diff($depDate)->format('%r%a');
        if ($daysUntilDeparture < 40) {
            send_error_response('Cannot extend: departure is within 40 days. Please contact admin.');
        }

        // Get next unpaid (payments already fetched above for proof check)
        $nextUnpaidStep = null;
        $currentDueDate = null;
        foreach ($payments as $p) {
            if ($p['status'] !== 'confirmed') {
                $nextUnpaidStep = $p['paymentStep'];
                $currentDueDate = $p['dueDate'] ?? null;
                break;
            }
        }

        if (!$nextUnpaidStep) {
            send_error_response('No unpaid payment step found');
        }

        // Per-step extension 추적: 해당 step에 대해 이미 extend 한 적 있는지 확인
        $extCountStmt = $conn->prepare("
            SELECT COUNT(*) as cnt FROM booking_status_history
            WHERE bookingId = ? AND changedByType = 'agent'
            AND changeReason LIKE CONCAT('%extended%', ?, '%')
        ");
        $extCountStmt->bind_param('ss', $bookingId, $nextUnpaidStep);
        $extCountStmt->execute();
        $extCountRow = $extCountStmt->get_result()->fetch_assoc();
        $extCountStmt->close();
        if ((int)($extCountRow['cnt'] ?? 0) >= 1) {
            send_error_response("이 결제 단계({$nextUnpaidStep})는 이미 연장된 적이 있습니다. 추가 연장은 관리자에게 문의해주세요.");
        }

        // Calculate new due date (+3 days from current due date)
        $dueDateObj = $currentDueDate ? new DateTime($currentDueDate) : new DateTime();
        $dueDateObj->modify('+3 days');
        $newDueDate = $dueDateObj->format('Y-m-d');

        // Get previousStatus from booking_status_history
        $histStmt = $conn->prepare("
            SELECT previousStatus FROM booking_status_history
            WHERE bookingId = ? AND newStatus = 'waiting_cancelled'
            ORDER BY changedAt DESC LIMIT 1
        ");
        $histStmt->bind_param('s', $bookingId);
        $histStmt->execute();
        $histRow = $histStmt->get_result()->fetch_assoc();
        $histStmt->close();
        $previousStatus = $histRow['previousStatus'] ?? 'pending';

        // Begin transaction
        $conn->begin_transaction();

        // 1. Update booking_payments dueDate
        upsertPayment($conn, $bookingId, $nextUnpaidStep, ['dueDate' => $newDueDate]);

        // 2. Update legacy columns in bookings table
        $legacyMap = [
            'down' => 'downPaymentDueDate',
            'second' => 'advancePaymentDueDate',
            'balance' => 'balanceDueDate',
            'middle' => 'downPaymentDueDate',
            'middle_balance' => 'balanceDueDate',
            'full' => 'fullPaymentDueDate'
        ];
        $legacyCol = $legacyMap[$nextUnpaidStep] ?? null;
        if ($legacyCol) {
            $updateSql = "UPDATE bookings SET $legacyCol = ? WHERE bookingId = ?";
            $upStmt = $conn->prepare($updateSql);
            $upStmt->bind_param('ss', $newDueDate, $bookingId);
            $upStmt->execute();
            $upStmt->close();
        }

        // 3. Restore bookingStatus to previousStatus
        $restoreStmt = $conn->prepare("UPDATE bookings SET bookingStatus = ? WHERE bookingId = ?");
        $restoreStmt->bind_param('ss', $previousStatus, $bookingId);
        $restoreStmt->execute();
        $restoreStmt->close();

        // 4. Log to booking_status_history
        $agentName = $_SESSION['agent_name'] ?? $_SESSION['agent_userId'] ?? 'agent';
        $logStmt = $conn->prepare("
            INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changeReason)
            VALUES (?, 'waiting_cancelled', ?, ?, 'agent', ?)
        ");
        $reason = "Payment deadline extended ($nextUnpaidStep) +3 days: $newDueDate";
        $logStmt->bind_param('ssss', $bookingId, $previousStatus, $agentName, $reason);
        $logStmt->execute();
        $logStmt->close();

        // 5. Add reservation history
        addReservationHistory($conn, $bookingId, "Agent extended payment deadline: $nextUnpaidStep due date changed to $newDueDate (was: " . ($currentDueDate ?? 'N/A') . ")");

        $conn->commit();

        // Send deadline extended email
        try {
            if (function_exists('send_deadline_extended_email')) {
                send_deadline_extended_email($conn, $bookingId, $nextUnpaidStep, $newDueDate, $agentName);
            }
        } catch (Throwable $emailEx) {
            error_log("Failed to send deadline extended email for {$bookingId}: " . $emailEx->getMessage());
        }

        send_success_response([
            'bookingId' => $bookingId,
            'paymentStep' => $nextUnpaidStep,
            'previousDueDate' => $currentDueDate,
            'newDueDate' => $newDueDate,
            'restoredStatus' => $previousStatus
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to extend payment deadline: ' . $e->getMessage());
    }
}

function getTodayItineraries($conn) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // agent 세션 확인
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;
        
        $today = date('Y-m-d');
        
        // bookings 테이블 컬럼 확인
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }
        
        // packages 테이블 컬럼 확인
        $packagesColumns = [];
        $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
        if ($packageColumnResult) {
            while ($col = $packageColumnResult->fetch_assoc()) {
                $packagesColumns[] = strtolower($col['Field']);
            }
        }
        
        // guides 테이블 존재 확인
        $guidesTableCheck = $conn->query("SHOW TABLES LIKE 'guides'");
        $hasGuidesTable = ($guidesTableCheck && $guidesTableCheck->num_rows > 0);
        
        // guides 테이블 컬럼 확인
        $guidesColumns = [];
        if ($hasGuidesTable) {
            $guideColumnResult = $conn->query("SHOW COLUMNS FROM guides");
            if ($guideColumnResult) {
                while ($col = $guideColumnResult->fetch_assoc()) {
                    $guidesColumns[] = strtolower($col['Field']);
                }
            }
        }
        
        $hasGuideId = in_array('guideid', $bookingsColumns);
        $hasGuideName = $hasGuidesTable && (in_array('guidename', $guidesColumns) || in_array('name', $guidesColumns));
        $hasDurationDays = in_array('duration_days', $packagesColumns) || in_array('durationdays', $packagesColumns);
        $hasDuration = in_array('duration', $packagesColumns);
        
        // returnDate 계산식 (WHERE 절에서도 사용)
        // package_schedules의 MAX(day_number)를 우선 사용하고, 없으면 duration_days fallback
        $returnDateExpression = "DATE_ADD(b.departureDate, INTERVAL (COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1) - 1) DAY)";

        // SELECT 절 구성
        $selectFields = [
            'b.bookingId',
            'b.packageId',
            'p.packageName',
            'b.departureDate',
            'b.departureTime',
            $returnDateExpression . ' as returnDate'
        ];
        
        $selectFields[] = 'b.adults';
        $selectFields[] = 'b.children';
        $selectFields[] = 'b.infants';
        $selectFields[] = "CONCAT(c.fName, ' ', c.lName) as customerName";
        $selectFields[] = 'c.clientType';
        
        // guideName 추가
        if ($hasGuideName) {
            if (in_array('guidename', $guidesColumns)) {
                $selectFields[] = 'g.guideName';
            } elseif (in_array('name', $guidesColumns)) {
                $selectFields[] = 'g.name as guideName';
            }
        } else {
            $selectFields[] = "NULL as guideName";
        }
        
        $sql = "
            SELECT " . implode(', ', $selectFields) . "
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN client c ON b.accountId = c.accountId
        ";
        
        if ($hasGuideId && $hasGuidesTable) {
            $sql .= " LEFT JOIN guides g ON b.guideId = g.guideId";
        } else {
            $sql .= " LEFT JOIN (SELECT NULL as guideName) g ON 1=1";
        }
        
        // 오늘이 여행 기간에 포함된 예약 조회 (departureDate <= 오늘 <= returnDate)
        $sql .= "
            WHERE b.departureDate <= ?
            AND {$returnDateExpression} >= ?
            AND b.bookingStatus IN ('pending','confirmed')
            AND b.agentId = ?
            ORDER BY b.departureDate DESC, p.packageName ASC
            LIMIT 20
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $today, $today, $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $itineraries = [];
        while ($row = $result->fetch_assoc()) {
            // agent overview에서는 해당 에이전트 예약만 조회하므로 고객유형은 B2B로 고정
            $customerType = 'B2B';
            
            // 여행 기간 포맷팅 (YYYY-MM-DD - YYYY-MM-DD)
            $departureDate = $row['departureDate'] ?? '';
            $returnDate = $row['returnDate'] ?? '';
            $travelPeriod = '';
            if ($departureDate && $returnDate) {
                $departureFormatted = date('Y-m-d', strtotime($departureDate));
                $returnFormatted = date('Y-m-d', strtotime($returnDate));
                $travelPeriod = $departureFormatted . ' - ' . $returnFormatted;
            } elseif ($departureDate) {
                $travelPeriod = date('Y-m-d', strtotime($departureDate));
            }
            
            $itineraries[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                'travelPeriod' => $travelPeriod,
                'customerType' => $customerType,
                'numPeople' => (int)($row['adults'] ?? 0) + (int)($row['children'] ?? 0) + (int)($row['infants'] ?? 0),
                'guideName' => $row['guideName'] ?? '미배정'
            ];
        }
        
        send_success_response($itineraries);
    } catch (Exception $e) {
        send_error_response('Failed to get today itineraries: ' . $e->getMessage());
    }
}

/**
 * Best Price Packages - 가장 저렴한 가격의 패키지 상위 10개 조회
 * - package_available_dates 테이블에서 각 패키지별 최저가 날짜를 찾아서 반환
 */
function getBestPricePackages($conn) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // package_available_dates 테이블 존재 확인
        $tableCheck = $conn->query("SHOW TABLES LIKE 'package_available_dates'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            send_success_response([]);
            return;
        }

        // 각 패키지별 최저가 날짜와 해당 가격 조회
        // - 오늘 이후 날짜만
        // - status IN ('available', 'confirmed', 'open')
        // - 잔여 좌석(capacity - 동적 예약수) > 0
        // - 가격순 정렬 후 상위 10개
        $sql = "
            SELECT
                p.packageId,
                p.packageName,
                p.thumbnail_image,
                p.product_images,
                pa.available_date AS bestDate,
                pa.price AS bestPrice,
                (pa.capacity - COALESCE(bk.booked, 0)) AS remainingSeats,
                pa.childPrice,
                pa.singlePrice
            FROM packages p
            INNER JOIN (
                SELECT pad2.package_id, MIN(pad2.price) as min_price
                FROM package_available_dates pad2
                LEFT JOIN (
                    SELECT packageId, departureDate,
                           SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                    FROM bookings
                    WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                      AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                    GROUP BY packageId, departureDate
                ) bk2 ON bk2.packageId = pad2.package_id AND bk2.departureDate = pad2.available_date
                WHERE pad2.available_date >= CURDATE()
                  AND pad2.status IN ('available', 'confirmed', 'open')
                  AND pad2.price > 0
                  AND (pad2.capacity - COALESCE(bk2.booked, 0)) > 0
                GROUP BY pad2.package_id
            ) minp ON p.packageId = minp.package_id
            INNER JOIN package_available_dates pa
                ON pa.package_id = minp.package_id
                AND pa.price = minp.min_price
                AND pa.available_date >= CURDATE()
                AND pa.status IN ('available', 'confirmed', 'open')
            LEFT JOIN (
                SELECT packageId, departureDate,
                       SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                FROM bookings
                WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                  AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                GROUP BY packageId, departureDate
            ) bk ON bk.packageId = pa.package_id AND bk.departureDate = pa.available_date
            WHERE p.isActive = 1
              AND (p.status IS NULL OR p.status = 'active')
              AND (pa.capacity - COALESCE(bk.booked, 0)) > 0
            GROUP BY p.packageId
            ORDER BY pa.price ASC
            LIMIT 10
        ";

        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }

        $packages = [];
        while ($row = $result->fetch_assoc()) {
            // 이미지 처리
            $imageUrl = '';
            if (!empty($row['thumbnail_image'])) {
                $imageUrl = $row['thumbnail_image'];
            } elseif (!empty($row['product_images'])) {
                $decoded = json_decode($row['product_images'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    $imageUrl = is_array($decoded[0]) ? ($decoded[0]['en'] ?? '') : $decoded[0];
                }
            }

            // 이미지 경로 정규화
            if ($imageUrl && !str_starts_with($imageUrl, 'http') && !str_starts_with($imageUrl, '/')) {
                $imageUrl = '/uploads/products/' . $imageUrl;
            }

            $packages[] = [
                'packageId' => (int)$row['packageId'],
                'packageName' => $row['packageName'],
                'imageUrl' => $imageUrl,
                'bestDate' => $row['bestDate'],
                'bestPrice' => (float)$row['bestPrice'],
                'remainingSeats' => (int)($row['remainingSeats'] ?? 0),
                'childPrice' => $row['childPrice'] ? (float)$row['childPrice'] : null,
                'singlePrice' => $row['singlePrice'] ? (float)$row['singlePrice'] : null,
                'formattedPrice' => '₱' . number_format((float)$row['bestPrice'], 0)
            ];
        }

        send_success_response($packages);
    } catch (Exception $e) {
        send_error_response('Failed to get best price packages: ' . $e->getMessage());
    }
}

/**
 * 에이전트 예약금 비율 조회
 * - 반환: depositRate (0~1)
 * - 기본값 10% 반환
 */
function getAgentDepositRate($conn) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // 기본 예약금 비율 10%
        $depositRate = 0.1;

        send_success_response([
            'depositRate' => $depositRate,
        ], 'Success');
    } catch (Throwable $e) {
        send_success_response(['depositRate' => 0.1], 'Success');
    }
}

// ========== 예약 관련 함수들 ==========

function getReservations($conn, $input) {
    try {
        $page = isset($input['page']) ? (int)$input['page'] : 1;
        $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // bookings/packages 스키마에 따라 returnDate 계산식 구성 (bookings.returnDate 컬럼이 없는 환경 대응)
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }
        $packagesColumns = [];
        $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
        if ($packageColumnResult) {
            while ($col = $packageColumnResult->fetch_assoc()) {
                $packagesColumns[] = strtolower($col['Field']);
            }
        }
        $hasReturnDateCol = in_array('returndate', $bookingsColumns, true);
        $hasDurationDaysSnake = in_array('duration_days', $packagesColumns, true);
        $hasDurationDaysCamel = in_array('durationdays', $packagesColumns, true);
        $hasDuration = in_array('duration', $packagesColumns, true);

        if ($hasReturnDateCol) {
            $returnDateExpression = "b.returnDate";
        } else {
            // package_schedules의 MAX(day_number)를 우선 사용
            $returnDateExpression = "DATE_ADD(b.departureDate, INTERVAL (COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1) - 1) DAY)";
        }
        
        $where = [];
        $params = [];
        $types = '';

        // NOTE:
        // - 이 환경(bookings 테이블)에는 고객 accountId 컬럼이 없고, b.accountId는 에이전트 계정으로 사용됩니다.
        // - 예약자명은 booking_travelers(대표 여행자) / booking(구버전 테이블) / selectedOptions.customerInfo 등에서 복구합니다.
        // - client 조인은 환경별로 존재할 수 있으므로 유지하되, 이름의 1순위는 traveler/booking으로 둡니다.
        $customerJoinKey = "b.accountId";

        // 보안: 예약 목록은 로그인한 에이전트 본인 예약만
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $where[] = "b.agentId = ?";
        $params[] = (int)$agentAccountId;
        $types .= 'i';

        // draft 예약 제외
        $where[] = "b.bookingStatus != 'draft'";

        // 검색 조건(기본: All) + 검색 타입(퍼블리싱: Product Name / Reservation Name)
        if (!empty($input['search'])) {
            $searchTerm = '%' . (string)$input['search'] . '%';
            $searchType = isset($input['searchType']) ? (string)$input['searchType'] : '';

            // 예약자명은 booking_travelers(대표 여행자)/client(있으면)에서 검색
            $customerNameWhere = "(bt.firstName LIKE ? OR bt.lastName LIKE ? OR c.fName LIKE ? OR c.lName LIKE ?)";

            if ($searchType === 'product' || $searchType === 'packageName') {
                $where[] = "p.packageName LIKE ?";
                $params[] = $searchTerm;
                $types .= 's';
            } elseif ($searchType === 'customer' || $searchType === 'customerName') {
                $where[] = $customerNameWhere;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ssss';
            } elseif ($searchType === 'bookingId' || $searchType === 'bookingNumber') {
                $where[] = "b.bookingId LIKE ?";
                $params[] = $searchTerm;
                $types .= 's';
            } else {
                $where[] = "(p.packageName LIKE ? OR b.bookingId LIKE ? OR $customerNameWhere)";
                // packageName + bookingId
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
                // customerNameWhere (4)
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ssss';
            }
        }
        
        // Travel start date(출발일) 필터 (UI 요구사항)
        if (!empty($input['travelStartDate'])) {
            $dateRange = explode(',', (string)$input['travelStartDate']);
            if (count($dateRange) === 2) {
                $where[] = "DATE(b.departureDate) >= ? AND DATE(b.departureDate) <= ?";
                $params[] = $dateRange[0];
                $params[] = $dateRange[1];
                $types .= 'ss';
            } else {
                // 단일 값이면 해당일 이후
                $where[] = "DATE(b.departureDate) >= ?";
                $params[] = (string)$input['travelStartDate'];
                $types .= 's';
            }
        }
        
        // 예약 상태 필터 (새로운 11단계 상태값 지원)
        if (!empty($input['reservationStatus'])) {
            $status = strtolower(trim($input['reservationStatus']));
            $validStatuses = [
                'waiting_down_payment', 'checking_down_payment',
                'waiting_second_payment', 'checking_second_payment',
                'waiting_balance', 'checking_balance',
                'rejected', 'confirmed', 'completed', 'cancelled', 'refunded'
            ];

            if (in_array($status, $validStatuses, true)) {
                $where[] = "LOWER(TRIM(b.bookingStatus)) = ?";
                $params[] = $status;
                $types .= 's';
            } elseif ($status === 'pending_deposit' || $status === 'pending') {
                // 하위 호환성: pending/pending_deposit → waiting/checking down payment
                $where[] = "LOWER(TRIM(b.bookingStatus)) IN ('waiting_down_payment','checking_down_payment','pending')";
            } elseif ($status === 'pending_balance' || $status === 'partial') {
                // 하위 호환성: pending_balance/partial → waiting/checking second/balance
                $where[] = "LOWER(TRIM(b.bookingStatus)) IN ('waiting_second_payment','checking_second_payment','waiting_balance','checking_balance')";
            } else {
                $where[] = "LOWER(TRIM(b.bookingStatus)) = ?";
                $params[] = $status;
                $types .= 's';
            }
        }
        
        // 결제 상태 필터
        if (!empty($input['paymentStatus'])) {
            $where[] = "b.paymentStatus = ?";
            $params[] = $input['paymentStatus'];
            $types .= 's';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // 정렬 순서
        $orderBy = "ORDER BY b.createdAt DESC";

        // Travel start date(출발일) 정렬 우선 적용
        if (!empty($input['travelStartDateSort'])) {
            if ($input['travelStartDateSort'] === 'asc') {
                $orderBy = "ORDER BY b.departureDate ASC, b.createdAt DESC";
            } elseif ($input['travelStartDateSort'] === 'desc') {
                $orderBy = "ORDER BY b.departureDate DESC, b.createdAt DESC";
            }
        } elseif (!empty($input['sortOrder'])) {
            if ($input['sortOrder'] === 'oldest') {
                $orderBy = "ORDER BY b.createdAt ASC";
            } else {
                $orderBy = "ORDER BY b.createdAt DESC";
            }
        }
        
        // 전체 개수 조회
        $countSql = "
            SELECT COUNT(*) as total
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN (
                SELECT bt1.transactNo, bt1.firstName, bt1.lastName
                FROM booking_travelers bt1
                INNER JOIN (
                    SELECT transactNo, MAX(bookingTravelerId) AS maxId
                    FROM booking_travelers
                    WHERE isMainTraveler = 1
                    GROUP BY transactNo
                ) x ON x.maxId = bt1.bookingTravelerId
            ) bt ON bt.transactNo = b.transactNo
            LEFT JOIN client c ON c.accountId = {$customerJoinKey}
            $whereClause
        ";

        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            if ($types) {
                mysqli_bind_params_by_ref($countStmt, $types, $params);
            }
            $countStmt->execute();
            $totalResult = $countStmt->get_result();
        } else {
            $totalResult = $conn->query($countSql);
        }
        $total = $totalResult->fetch_assoc()['total'];
        
        // 목록 조회
        $sql = "
            SELECT
                b.bookingId,
                b.packageId,
                p.packageName,
                b.departureDate,
                {$returnDateExpression} as returnDate,
                b.totalAmount,
                -- 예약자명 후보(대표 여행자/client)
                TRIM(CONCAT(COALESCE(bt.firstName,''), ' ', COALESCE(bt.lastName,''))) as travelerName,
                TRIM(CONCAT(COALESCE(c.fName,''), ' ', COALESCE(c.lName,''))) as clientName,
                (b.adults + b.children + b.infants) as numPeople,
                b.bookingStatus,
                b.paymentStatus,
                b.createdAt,
                b.selectedOptions
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN (
                SELECT bt1.transactNo, bt1.firstName, bt1.lastName
                FROM booking_travelers bt1
                INNER JOIN (
                    SELECT transactNo, MAX(bookingTravelerId) AS maxId
                    FROM booking_travelers
                    WHERE isMainTraveler = 1
                    GROUP BY transactNo
                ) x ON x.maxId = bt1.bookingTravelerId
            ) bt ON bt.transactNo = b.transactNo
            LEFT JOIN client c ON c.accountId = {$customerJoinKey}
            $whereClause
            $orderBy
            LIMIT ? OFFSET ?
        ";
        
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        if ($types) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reservations = [];
        $rowNum = $total - $offset;
        while ($row = $result->fetch_assoc()) {
            // 상태 배지 결정
            $statusBadge = getBookingStatusBadge($row['bookingStatus'], $row['paymentStatus']);
            
            // 날짜 포맷팅
            $departureDate = $row['departureDate'] ? date('Y-m-d', strtotime($row['departureDate'])) : '';
            $returnDate = $row['returnDate'] ? date('Y-m-d', strtotime($row['returnDate'])) : '';
            
            // SMT 수정: customerAccountId 컬럼이 없거나 client join이 실패한 경우 selectedOptions.customerInfo로 fallback
            $customerName = '';
            $tName = trim((string)($row['travelerName'] ?? ''));
            $cName = trim((string)($row['clientName'] ?? ''));
            if ($tName !== '') $customerName = $tName;
            else if ($cName !== '') $customerName = $cName;
            if ($customerName === '' && !empty($row['selectedOptions'])) {
                $so = json_decode((string)$row['selectedOptions'], true);
                if (is_array($so)) {
                    $ci = $so['customerInfo'] ?? [];
                    $fn = trim((string)($ci['fName'] ?? $ci['firstName'] ?? $ci['customerFirstName'] ?? ''));
                    $ln = trim((string)($ci['lName'] ?? $ci['lastName'] ?? $ci['customerLastName'] ?? ''));
                    $nm = trim($fn . ' ' . $ln);
                    if ($nm !== '') $customerName = $nm;
                    if ($customerName === '' && !empty($ci['name'])) $customerName = trim((string)$ci['name']);
                }
            }

            $reservations[] = [
                'rowNum' => $rowNum--,
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                // Debug/compat fields (front can fall back if needed)
                'travelerName' => trim((string)($row['travelerName'] ?? '')),
                'clientName' => trim((string)($row['clientName'] ?? '')),
                'customerName' => $customerName !== '' ? $customerName : 'N/A',
                'reserverName' => $customerName !== '' ? $customerName : 'N/A',
                'departureDate' => $departureDate,
                'returnDate' => $returnDate,
                'totalAmount' => $row['totalAmount'] ?? 0,
                'numPeople' => (int)$row['numPeople'],
                'bookingStatus' => $row['bookingStatus'],
                'paymentStatus' => $row['paymentStatus'],
                'status' => $statusBadge['status'],
                'statusLabel' => $statusBadge['label'],
                'statusClass' => $statusBadge['class']
            ];
        }
        
        send_success_response([
            'reservations' => $reservations,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get reservations: ' . $e->getMessage());
    }
}

function getBookingStatusBadge($bookingStatus, $paymentStatus) {
    // DB에 한글로 저장된 경우 영어로 변환
    $bookingStatus = normalizeBookingStatus($bookingStatus);
    $paymentStatus = normalizePaymentStatus($paymentStatus);

    // 새로운 11단계 상태값 지원
    $statusBadgeMap = [
        'waiting_down_payment' => ['status' => 'waiting_down_payment', 'label' => 'Waiting for Down Payment', 'class' => 'badge-wait-down'],
        'checking_down_payment' => ['status' => 'checking_down_payment', 'label' => 'Checking Down Payment', 'class' => 'badge-check-down'],
        'waiting_second_payment' => ['status' => 'waiting_second_payment', 'label' => 'Waiting for Second Payment', 'class' => 'badge-wait-second'],
        'checking_second_payment' => ['status' => 'checking_second_payment', 'label' => 'Checking Second Payment', 'class' => 'badge-check-second'],
        'waiting_balance' => ['status' => 'waiting_balance', 'label' => 'Waiting for Balance', 'class' => 'badge-wait-balance'],
        'checking_balance' => ['status' => 'checking_balance', 'label' => 'Checking Balance', 'class' => 'badge-check-balance'],
        'rejected' => ['status' => 'rejected', 'label' => 'Payment Rejected', 'class' => 'badge-rejected'],
        'confirmed' => ['status' => 'confirmed', 'label' => 'Reservation Confirmed', 'class' => 'badge-confirmed'],
        'completed' => ['status' => 'completed', 'label' => 'Trip Completed', 'class' => 'badge-completed'],
        'cancelled' => ['status' => 'cancelled', 'label' => 'Reservation Cancelled', 'class' => 'badge-cancelled'],
        'refunded' => ['status' => 'refunded', 'label' => 'Refund Completed', 'class' => 'badge-refunded']
    ];

    $statusLower = strtolower(trim($bookingStatus));
    if (isset($statusBadgeMap[$statusLower])) {
        return $statusBadgeMap[$statusLower];
    }

    // 하위 호환성: 기존 상태값 처리
    if ($bookingStatus === 'pending') {
        return $statusBadgeMap['waiting_down_payment'];
    } elseif ($bookingStatus === 'refund_completed') {
        return $statusBadgeMap['refunded'];
    }

    return ['status' => $bookingStatus, 'label' => $bookingStatus, 'class' => 'badge-gray'];
}

function normalizeBookingStatus($status) {
    if (empty($status)) return $status;
    
    // 한글 상태를 영어로 변환
    $statusMap = [
        '예약 확정' => 'confirmed',
        '여행 완료' => 'completed',
        '예약 취소' => 'cancelled',
        '환불 완료' => 'refunded',
        'refund_completed' => 'refunded',
        '선금 확인 전' => 'pending_deposit',
        '잔금 확인 전' => 'pending_balance'
    ];
    
    $statusLower = strtolower(trim($status));
    foreach ($statusMap as $korean => $english) {
        if (strtolower($korean) === $statusLower || $status === $korean) {
            return $english;
        }
    }
    
    return $status;
}

function normalizePaymentStatus($status) {
    if (empty($status)) return $status;
    
    // 한글 상태를 영어로 변환
    $statusMap = [
        '선금 확인 전' => 'pending',
        '잔금 확인 전' => 'partial',
        '선금 확인' => 'partial',
        '전액 확인' => 'paid',
        'paid' => 'paid',
        'partial' => 'partial',
        'pending' => 'pending'
    ];
    
    $statusLower = strtolower(trim($status));
    foreach ($statusMap as $korean => $english) {
        if (strtolower($korean) === $statusLower || $status === $korean) {
            return $english;
        }
    }
    
    return $status;
}

function getReservationDetail($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 보안: agent 전용 상세는 반드시 본인 예약만
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        
        // bookings 테이블 컬럼 확인
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }

        // packages 테이블 컬럼 확인 (미팅 시간/장소 컬럼명 편차 대응)
        $packagesColumns = [];
        $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
        if ($packageColumnResult) {
            while ($col = $packageColumnResult->fetch_assoc()) {
                $packagesColumns[strtolower((string)$col['Field'])] = (string)$col['Field'];
            }
        }
        $meetingTimeCol = $packagesColumns['meeting_time'] ?? $packagesColumns['meetingtime'] ?? null;
        $meetingLocCol = $packagesColumns['meeting_location'] ?? $packagesColumns['meetinglocation'] ?? $packagesColumns['meetingpoint'] ?? $packagesColumns['meeting_point'] ?? null;
        $meetingTimeExpr = $meetingTimeCol ? ("p.`{$meetingTimeCol}`") : "''";
        $meetingLocExpr = $meetingLocCol ? ("p.`{$meetingLocCol}`") : "''";
        
        // guideId 컬럼이 없으면 추가
        $hasGuideId = in_array('guideid', $bookingsColumns);
        if (!$hasGuideId) {
            $alterSql = "ALTER TABLE bookings ADD COLUMN guideId INT NULL";
            try {
                $conn->query($alterSql);
                $hasGuideId = true;
                $bookingsColumns[] = 'guideid';
            } catch (Exception $e) {
                // 컬럼 추가 실패는 무시 (이미 존재하거나 다른 이유)
            }
        }
        
        // guides 테이블 존재 및 컬럼 확인
        $guideColumns = [];
        $guidesTableExists = false;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'guides'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $guidesTableExists = true;
            $guideColumnResult = $conn->query("SHOW COLUMNS FROM guides");
            if ($guideColumnResult) {
                while ($col = $guideColumnResult->fetch_assoc()) {
                    $guideColumns[] = strtolower($col['Field']);
                }
            }
        }
        
        // 가이드 전화번호 컬럼명 확인
        $guidePhoneCol = 'NULL';
        if ($guidesTableExists && $hasGuideId) {
            if (in_array('guidephone', $guideColumns)) {
                $guidePhoneCol = 'g.guidePhone';
            } elseif (in_array('phonenumber', $guideColumns)) {
                $guidePhoneCol = 'g.phoneNumber';
            } elseif (in_array('phone', $guideColumns)) {
                $guidePhoneCol = 'g.phone';
            }
        }
        
        // 가이드 이메일 컬럼명 확인
        $guideEmailCol = 'NULL';
        if ($guidesTableExists && $hasGuideId) {
            if (in_array('guideemail', $guideColumns)) {
                $guideEmailCol = 'g.guideEmail';
            } elseif (in_array('email', $guideColumns)) {
                $guideEmailCol = 'g.email';
            }
        }
        
        // guideName 컬럼도 확인
        $guideNameCol = 'NULL';
        if ($guidesTableExists && $hasGuideId && in_array('guidename', $guideColumns)) {
            $guideNameCol = 'g.guideName';
        }
        
        // 고객 accountId 컬럼(환경별) 확인: bookings.accountId는 agent 소유자이므로 고객 조인에 사용하면 안됨
        $customerAccountIdCol = null;
        if (in_array('customeraccountid', $bookingsColumns, true)) $customerAccountIdCol = 'customerAccountId';
        else if (in_array('customer_account_id', $bookingsColumns, true)) $customerAccountIdCol = 'customer_account_id';
        else if (in_array('customerid', $bookingsColumns, true)) $customerAccountIdCol = 'customerId';
        else if (in_array('userid', $bookingsColumns, true)) $customerAccountIdCol = 'userId';
        $customerJoinKey = $customerAccountIdCol ? ("COALESCE(b.`{$customerAccountIdCol}`, b.accountId)") : "b.accountId";

        // JOIN 조건 구성
        $guideJoin = '';
        if ($guidesTableExists && $hasGuideId) {
            $guideJoin = "LEFT JOIN guides g ON b.guideId = g.guideId";
        }
        
        // 결제 관련 컬럼 확인 (통일: downPayment*, balanceFile 사용)
        $hasDownPaymentDueDate = in_array('downpaymentduedate', $bookingsColumns);
        $hasBalanceDueDate = in_array('balanceduedate', $bookingsColumns);
        $hasDownPaymentFile = in_array('downpaymentfile', $bookingsColumns);
        $hasBalanceFile = in_array('balancefile', $bookingsColumns);

        // IMPORTANT: SELECT에서 다시 "AS xxx"로 alias를 붙이므로, 표현식에는 alias를 포함하지 않습니다.
        // 레거시 호환을 위해 alias는 deposit*/balance* 유지
        $depositDeadlineCol = $hasDownPaymentDueDate ? 'b.downPaymentDueDate' : 'NULL';
        $balanceDeadlineCol = $hasBalanceDueDate ? 'b.balanceDueDate' : 'NULL';
        $depositProofFileCol = $hasDownPaymentFile ? 'b.downPaymentFile' : 'NULL';
        $balanceProofFileCol = $hasBalanceFile ? 'b.balanceFile' : 'NULL';
        
        $sql = "
            SELECT 
                b.*,
                p.packageName,
                p.duration_days,
                {$meetingTimeExpr} as meetingTime,
                {$meetingLocExpr} as meetingLocation,
                c.fName,
                c.lName,
                c.emailAddress,
                c.contactNo,
                $guideNameCol as guideName,
                $guidePhoneCol as guidePhone,
                $guideEmailCol as guideEmail,
                $depositDeadlineCol as depositDeadline,
                $balanceDeadlineCol as balanceDeadline,
                $depositProofFileCol as depositProofFile,
                $balanceProofFileCol as balanceProofFile
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN client c ON c.accountId = {$customerJoinKey}
            $guideJoin
            WHERE b.bookingId = ?
              AND b.agentId = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $bookingId, $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Reservation not found', 404);
        }
        
        $booking = $result->fetch_assoc();
        
        // selectedOptions 파싱
        $selectedOptions = [];
        if (!empty($booking['selectedOptions'])) {
            $decoded = json_decode($booking['selectedOptions'], true);
            if (is_array($decoded)) {
                $selectedOptions = $decoded;
            }
        }

        // 상품 인원 옵션 라벨(가격옵션) 제공: Reservation Information의 Number of Guests 표시용
        $pricingLabels = ['adult' => null, 'child' => null, 'infant' => null];
        $pricingOptions = [];
        try {
            $optTbl = $conn->query("SHOW TABLES LIKE 'package_pricing_options'");
            if ($optTbl && $optTbl->num_rows > 0) {
                $pid = (int)($booking['packageId'] ?? 0);
                if ($pid > 0) {
                    $optCols = [];
                    $cr = $conn->query("SHOW COLUMNS FROM package_pricing_options");
                    if ($cr) {
                        while ($c = $cr->fetch_assoc()) $optCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
                    }
                    $optNameCol = $optCols['optionname'] ?? $optCols['option_name'] ?? $optCols['name'] ?? null;
                    $priceCol = $optCols['price'] ?? null;
                    if ($optNameCol) {
                        $q = "SELECT `{$optNameCol}` AS optionName" . ($priceCol ? ", `{$priceCol}` AS price" : ", NULL AS price") . " FROM package_pricing_options WHERE packageId = ? ORDER BY id ASC";
                        $st = $conn->prepare($q);
                        if ($st) {
                            $st->bind_param('i', $pid);
                            $st->execute();
                            $rs = $st->get_result();
                            while ($r = $rs->fetch_assoc()) $pricingOptions[] = $r;
                            $st->close();
                        }
                    }

                    // label 매핑: optionName 키워드 우선 → 없으면 순서대로 adult/child/infant
                    $norm = function ($name) {
                        $s = strtolower(trim((string)$name));
                        if ($s === '') return null;
                        if (strpos($s, 'adult') !== false || strpos($s, '성인') !== false) return 'adult';
                        if (strpos($s, 'child') !== false || strpos($s, 'kid') !== false || strpos($s, '아동') !== false) return 'child';
                        if (strpos($s, 'infant') !== false || strpos($s, 'baby') !== false || strpos($s, '유아') !== false) return 'infant';
                        return null;
                    };
                    foreach ($pricingOptions as $opt) {
                        $t = $norm($opt['optionName'] ?? '');
                        if ($t && empty($pricingLabels[$t])) $pricingLabels[$t] = (string)$opt['optionName'];
                    }
                    if (empty($pricingLabels['adult']) && empty($pricingLabels['child']) && empty($pricingLabels['infant']) && !empty($pricingOptions)) {
                        $types = ['adult', 'child', 'infant'];
                        for ($i = 0; $i < min(3, count($pricingOptions)); $i++) {
                            $nm = trim((string)($pricingOptions[$i]['optionName'] ?? ''));
                            if ($nm !== '' && empty($pricingLabels[$types[$i]])) $pricingLabels[$types[$i]] = $nm;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // SMT 수정: 예약 고객 정보 보정 (client join 실패/미존재 환경 대비)
        $ci = is_array($selectedOptions) ? ($selectedOptions['customerInfo'] ?? []) : [];
        $customerF = trim((string)($booking['fName'] ?? ''));
        $customerL = trim((string)($booking['lName'] ?? ''));
        if ($customerF === '' && is_array($ci)) $customerF = trim((string)($ci['fName'] ?? $ci['firstName'] ?? $ci['customerFirstName'] ?? ''));
        if ($customerL === '' && is_array($ci)) $customerL = trim((string)($ci['lName'] ?? $ci['lastName'] ?? $ci['customerLastName'] ?? ''));
        $customerName = trim($customerF . ' ' . $customerL);
        if ($customerName === '' && is_array($ci) && !empty($ci['name'])) $customerName = trim((string)$ci['name']);

        // JS(`admin/js/agent-reservation-detail.js`)가 기대하는 키들로 제공
        $booking['customerFirstName'] = $customerF;
        $booking['customerLastName'] = $customerL;
        $booking['customerName'] = $customerName;
        $booking['customerEmail'] = (string)($booking['emailAddress'] ?? ($ci['email'] ?? $ci['emailAddress'] ?? $booking['contactEmail'] ?? ''));
        $booking['customerPhone'] = (string)($booking['contactNo'] ?? ($ci['phone'] ?? $ci['contactNo'] ?? $booking['contactPhone'] ?? ''));
        if (is_array($ci) && isset($ci['countryCode']) && $ci['countryCode'] !== '') {
            $booking['countryCode'] = (string)$ci['countryCode'];
        }

        // SMT 수정: 항공편 정보(package_flights) 제공
        $booking['outboundFlight'] = null;
        $booking['inboundFlight'] = null;
        $booking['optionCategoryName'] = null; // 옵션 카테고리명 (airline_name)
        try {
            $pfCheck = $conn->query("SHOW TABLES LIKE 'package_flights'");
            if ($pfCheck && $pfCheck->num_rows > 0) {
                $pf = $conn->prepare("SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination FROM package_flights WHERE package_id = ? ORDER BY flight_type ASC LIMIT 5");
                if ($pf) {
                    $pid = (int)($booking['packageId'] ?? 0);
                    $pf->bind_param('i', $pid);
                    $pf->execute();
                    $pfRes = $pf->get_result();
                    $flights = [];
                    while ($r = $pfRes->fetch_assoc()) $flights[] = $r;
                    $pf->close();

                    $departureDate = (string)($booking['departureDate'] ?? '');
                    // package_schedules 기준으로 duration 조회
                    $duration = 0;
                    $durationStmt = $conn->prepare("SELECT MAX(day_number) AS maxDay FROM package_schedules WHERE package_id = ?");
                    if ($durationStmt) {
                        $pkgIdForDur = (int)($booking['packageId'] ?? 0);
                        $durationStmt->bind_param('i', $pkgIdForDur);
                        $durationStmt->execute();
                        $durRow = $durationStmt->get_result()->fetch_assoc();
                        $duration = (int)($durRow['maxDay'] ?? 0);
                        $durationStmt->close();
                    }
                    if ($duration <= 0) $duration = (int)($booking['duration_days'] ?? 0);
                    if ($duration <= 0) $duration = 5;
                    $returnDate = $departureDate;
                    try {
                        if ($departureDate !== '') {
                            $dt = new DateTime($departureDate);
                            $dt->modify('+' . max(0, $duration - 1) . ' days');
                            $returnDate = $dt->format('Y-m-d');
                        }
                    } catch (Throwable $e) { /* ignore */ }

                    $mk = function($row, $baseDate) {
                        if (!$row) return null;
                        $flightNo = trim((string)($row['flight_number'] ?? ''));
                        if ($flightNo === '') return null;
                        $depTime = trim((string)($row['departure_time'] ?? ''));
                        $arrTime = trim((string)($row['arrival_time'] ?? ''));
                        return [
                            'flightNumber' => $flightNo,
                            'departureDateTime' => ($baseDate && $depTime) ? ($baseDate . ' ' . $depTime) : ($baseDate ?: null),
                            'arrivalDateTime' => ($baseDate && $arrTime) ? ($baseDate . ' ' . $arrTime) : ($baseDate ?: null),
                            'departureAirport' => (string)($row['departure_point'] ?? ''),
                            'arrivalAirport' => (string)($row['destination'] ?? ''),
                        ];
                    };

                    foreach ($flights as $r) {
                        $t = strtolower(trim((string)($r['flight_type'] ?? '')));
                        if ($t === 'departure' && $booking['outboundFlight'] === null) {
                            $booking['outboundFlight'] = $mk($r, $departureDate);
                            // 옵션 카테고리명 (airline_name) 저장
                            if (empty($booking['optionCategoryName']) && !empty($r['airline_name'])) {
                                $booking['optionCategoryName'] = trim((string)$r['airline_name']);
                            }
                        } elseif (($t === 'return' || $t === 'inbound') && $booking['inboundFlight'] === null) {
                            $booking['inboundFlight'] = $mk($r, $returnDate);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore (non-critical)
        }
        
        // 여행자 정보 조회 (booking_travelers 테이블, www/user와 동일하게 transactNo 사용)
        // www/user에서는 transactNo 컬럼에 bookingId 값을 저장하므로, 조회 시에도 bookingId 사용
        $searchValue = $booking['bookingId']; // www/user와 동일하게 bookingId 사용
        
        // booking_travelers 테이블 컬럼 확인
        $travelerColumns = [];
        $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
        if ($travelerColumnCheck) {
            while ($col = $travelerColumnCheck->fetch_assoc()) {
                $travelerColumns[] = strtolower($col['Field']);
            }
        }
        
        // www/user와 동일하게 transactNo 컬럼 사용 (값은 bookingId)
        $travelerBookingIdColumn = 'transactNo';
        if (!in_array('transactno', $travelerColumns)) {
            if (in_array('bookingid', $travelerColumns)) {
                $travelerBookingIdColumn = 'bookingId';
            }
        }
        
        // ORDER BY 절 구성 (www/user와 동일한 순서)
        $orderBy = [];
        // 1. travelerType 순서 (adult -> child -> infant)
        $orderBy[] = 'CASE travelerType WHEN \'adult\' THEN 1 WHEN \'child\' THEN 2 WHEN \'infant\' THEN 3 ELSE 99 END';
        // 2. isMainTraveler DESC
        if (in_array('ismaintraveler', $travelerColumns)) {
            $orderBy[] = 'isMainTraveler DESC';
        }
        // 3. bookingTravelerId ASC
        if (in_array('bookingtravelerid', $travelerColumns)) {
            $orderBy[] = 'bookingTravelerId ASC';
        } elseif (in_array('travelerid', $travelerColumns)) {
            $orderBy[] = 'travelerId ASC';
        }
        $orderByClause = !empty($orderBy) ? 'ORDER BY ' . implode(', ', $orderBy) : '';
        
        $travelersSql = "
            SELECT *
            FROM booking_travelers
            WHERE $travelerBookingIdColumn = ?
            $orderByClause
        ";
        $travelersStmt = $conn->prepare($travelersSql);
        $travelersStmt->bind_param("s", $searchValue);
        $travelersStmt->execute();
        $travelersResult = $travelersStmt->get_result();
        
        // 디버깅: SQL 쿼리 및 결과 확인
        error_log("Traveler SQL: " . $travelersSql);
        error_log("Search Value: " . $searchValue);
        error_log("Traveler Count: " . $travelersResult->num_rows);
        
        $travelers = [];
        while ($traveler = $travelersResult->fetch_assoc()) {
            // 디버깅: 첫 번째 여행자 데이터 확인
            if (count($travelers) === 0) {
                error_log("First traveler raw data: " . json_encode($traveler));
            }
            
            // 생년월일로부터 나이 계산 함수
            $calculateAge = function($birthDate) {
                if (!$birthDate) return null;
                try {
                    $birth = new DateTime($birthDate);
                    $today = new DateTime();
                    return $today->diff($birth)->y;
                } catch (Exception $e) {
                    return null;
                }
            };
            
            // www/user와 동일한 컬럼명으로 매핑
            $birthDate = $traveler['birthDate'] ?? $traveler['dateOfBirth'] ?? $traveler['birthdate'] ?? null;
            $age = $traveler['age'] ?? null;
            // age가 없으면 생년월일로부터 계산
            if (empty($age) && $birthDate) {
                $age = $calculateAge($birthDate);
            }
            
            // travelerType:
            // - create-reservation에서는 상품 pricing option name(option_name)을 그대로 저장할 수 있음
            // - 따라서 unknown type도 강제로 adult로 바꾸지 않고, 원문을 유지한다.
            $travelerTypeRaw = $traveler['travelerType'] ?? $traveler['type'] ?? 'adult';
            $travelerType = trim((string)$travelerTypeRaw);
            if ($travelerType === '') $travelerType = 'adult';
            
            // title 정규화
            $titleRaw = $traveler['title'] ?? '';
            $title = strtoupper(trim($titleRaw));
            if (!in_array($title, ['MR', 'MRS', 'MS'])) {
                $title = 'MR'; // 기본값
            }
            
            // gender 정규화
            $genderRaw = $traveler['gender'] ?? '';
            $gender = strtolower(trim($genderRaw));
            if (!in_array($gender, ['male', 'female', 'm', 'f', '남성', '여성'])) {
                $gender = 'male'; // 기본값
            } elseif ($gender === 'm' || $gender === '남성') {
                $gender = 'male';
            } elseif ($gender === 'f' || $gender === '여성') {
                $gender = 'female';
            }
            
            // visaRequired 정규화
            $visaRequired = 0;
            if (isset($traveler['visaStatus']) && $traveler['visaStatus'] !== 'not_required' && $traveler['visaStatus'] !== 'Not applied') {
                $visaRequired = 1;
            } elseif (isset($traveler['visaRequired'])) {
                $visaRequired = (int)$traveler['visaRequired'];
            }
            
            // visaType 결정 (group, individual, with_visa)
            $visaType = $traveler['visaType'] ?? $traveler['visa_type'] ?? 'with_visa';
            if ($visaRequired && $visaType === 'with_visa') {
                $visaType = 'individual'; // visa required인데 type이 with_visa면 individual로 기본 설정
            }

            $travelers[] = [
                'bookingTravelerId' => $traveler['bookingTravelerId'] ?? null,
                'travelerType' => $travelerType,
                'title' => $title,
                'firstName' => $traveler['firstName'] ?? $traveler['fName'] ?? '',
                'lastName' => $traveler['lastName'] ?? $traveler['lName'] ?? '',
                'gender' => $gender,
                'age' => $age,
                'dateOfBirth' => $birthDate,
                'nationality' => $traveler['nationality'] ?? '',
                'passportNumber' => $traveler['passportNumber'] ?? $traveler['passportNo'] ?? '',
                'passportIssueDate' => $traveler['passportIssueDate'] ?? $traveler['passportIssuedDate'] ?? null,
                'passportExpiryDate' => $traveler['passportExpiry'] ?? $traveler['passportExpiryDate'] ?? $traveler['passportExp'] ?? null,
                'passportImage' => $traveler['passportImage'] ?? '',
                'visaDocument' => $traveler['visaDocument'] ?? '',
                'visaRequired' => $visaRequired,
                'visaType' => $visaType,
                'visaStatus' => $traveler['visaStatus'] ?? 'not_required',
                'isMainTraveler' => isset($traveler['isMainTraveler']) ? (int)$traveler['isMainTraveler'] : 0,
                'specialRequests' => $traveler['specialRequests'] ?? '',
                'profile_source' => $traveler['profile_source'] ?? '',
                'childRoom' => isset($traveler['childRoom']) ? (int)$traveler['childRoom'] : 0,
                'infantSeat' => isset($traveler['infantSeat']) ? (int)$traveler['infantSeat'] : 0
            ];
        }

        // booking_travelers 레코드가 없는 경우:
        // - 예약 목록은 bookings.adults/children/infants 합으로 "예약 인원"을 표시하므로 숫자가 맞게 보이지만,
        // - 상세 화면은 booking_travelers 기반이라 "여행자 정보가 없습니다"가 표시됨.
        // 기획/운영 상 최소한 인원수만큼 행을 보여야 하는 경우가 많아서,
        // traveler 정보가 없으면 bookings의 인원 카운트를 기반으로 placeholder traveler를 생성해 반환한다.
        if (empty($travelers)) {
            $adultsCnt = (int)($booking['adults'] ?? 0);
            $childrenCnt = (int)($booking['children'] ?? 0);
            $infantsCnt = (int)($booking['infants'] ?? 0);
            $totalCnt = $adultsCnt + $childrenCnt + $infantsCnt;
            if ($totalCnt > 0) {
                $idx = 0;
                $mk = function (string $type) use (&$idx) {
                    $isMain = ($idx === 0) ? 1 : 0;
                    $idx++;
                    return [
                        'travelerType' => $type,
                        'title' => 'MR',
                        'firstName' => '',
                        'lastName' => '',
                        'gender' => 'male',
                        'age' => null,
                        'dateOfBirth' => null,
                        'nationality' => '',
                        'passportNumber' => '',
                        'passportIssueDate' => null,
                        'passportExpiryDate' => null,
                        'passportImage' => '',
                        'visaRequired' => 0,
                        'visaStatus' => 'not_required',
                        'isMainTraveler' => $isMain,
                        'specialRequests' => '',
                        'isPlaceholder' => 1
                    ];
                };
                for ($i = 0; $i < $adultsCnt; $i++) $travelers[] = $mk('adult');
                for ($i = 0; $i < $childrenCnt; $i++) $travelers[] = $mk('child');
                for ($i = 0; $i < $infantsCnt; $i++) $travelers[] = $mk('infant');
            }
        }

        // 여행자별 항공 옵션 조회 (booking_traveler_options 테이블)
        $travelerOptionsCheck = $conn->query("SHOW TABLES LIKE 'booking_traveler_options'");
        if ($travelerOptionsCheck && $travelerOptionsCheck->num_rows > 0) {
            $optionsSql = "SELECT traveler_index, option_id, price FROM booking_traveler_options WHERE booking_id = ?";
            $optionsStmt = $conn->prepare($optionsSql);
            if ($optionsStmt) {
                $optionsStmt->bind_param('s', $bookingId);
                $optionsStmt->execute();
                $optionsResult = $optionsStmt->get_result();

                // 인덱스별로 옵션 그룹화
                $travelerFlightOptions = [];
                while ($optRow = $optionsResult->fetch_assoc()) {
                    $idx = (int)$optRow['traveler_index'];
                    if (!isset($travelerFlightOptions[$idx])) {
                        $travelerFlightOptions[$idx] = [
                            'flightOptions' => [],
                            'flightOptionPrices' => []
                        ];
                    }
                    $optionId = $optRow['option_id'];
                    $optionPrice = (float)$optRow['price'];
                    $travelerFlightOptions[$idx]['flightOptions'][] = $optionId;
                    $travelerFlightOptions[$idx]['flightOptionPrices'][$optionId] = $optionPrice;
                }
                $optionsStmt->close();

                // travelers 배열에 항공 옵션 추가
                foreach ($travelers as $idx => &$traveler) {
                    if (isset($travelerFlightOptions[$idx])) {
                        $traveler['flightOptions'] = $travelerFlightOptions[$idx]['flightOptions'];
                        $traveler['flightOptionPrices'] = $travelerFlightOptions[$idx]['flightOptionPrices'];
                    } else {
                        $traveler['flightOptions'] = [];
                        $traveler['flightOptionPrices'] = [];
                    }
                }
                unset($traveler); // 참조 해제
            }
        }

        // 결제 상태 정보 추가 (통일: downPayment*, balanceFile 사용, 레거시 호환 alias 유지)
        $booking['depositConfirmed'] = !empty($booking['downPaymentConfirmedAt']);
        $booking['balanceConfirmed'] = !empty($booking['balanceConfirmedAt']);
        $booking['depositStatus'] = $booking['depositStatus'] ?? null;
        $booking['balanceStatus'] = $booking['balanceStatus'] ?? null;
        // 금액: downPaymentAmount 사용
        $booking['depositConfirmedAmount'] = $booking['downPaymentAmount'] ?? 0;
        $booking['balanceConfirmedAmount'] = $booking['balanceAmount'] ?? max(0, ((float)$booking['totalAmount']) - ((float)($booking['downPaymentAmount'] ?? 0)));
        // deadline/dueDate 정규화 (통일된 컬럼 사용)
        $dd = $booking['downPaymentDueDate'] ?? null;
        if ($dd !== null && trim((string)$dd) === '') $dd = null;
        $bd = $booking['balanceDueDate'] ?? null;
        if ($bd !== null && trim((string)$bd) === '') $bd = null;

        $booking['depositDueDate'] = $dd;
        $booking['balanceDueDate'] = $bd;
        // 파일: 통일된 컬럼 사용, 레거시 alias 유지
        $booking['depositProofFile'] = $booking['downPaymentFile'] ?? null;
        $booking['balanceProofFile'] = $booking['balanceFile'] ?? null;
        
        // 예약 이력 조회
        $history = [];
        $historyTableCheck = $conn->query("SHOW TABLES LIKE 'booking_history'");
        if ($historyTableCheck && $historyTableCheck->num_rows > 0) {
            $historySql = "SELECT description, createdAt FROM booking_history WHERE bookingId = ? ORDER BY createdAt DESC LIMIT 20";
            $historyStmt = $conn->prepare($historySql);
            $historyStmt->bind_param("s", $bookingId);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            while ($historyRow = $historyResult->fetch_assoc()) {
                $history[] = [
                    'description' => $historyRow['description'],
                    'createdAt' => $historyRow['createdAt'],
                    'timestamp' => $historyRow['createdAt']
                ];
            }
        }

        // check_reject 상태인 경우 booking_change_requests에서 거절 정보 조회
        $rejectedRequest = null;
        if (strtolower($booking['bookingStatus'] ?? '') === 'check_reject') {
            try {
                $crStmt = $conn->prepare("SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'rejected' ORDER BY processedAt DESC LIMIT 1");
                if ($crStmt) {
                    $crStmt->bind_param('s', $bookingId);
                    $crStmt->execute();
                    $crResult = $crStmt->get_result();
                    $cr = $crResult->fetch_assoc();
                    $crStmt->close();
                    if ($cr) {
                        $rejectedRequest = [
                            'id' => $cr['id'],
                            'changeType' => $cr['changeType'],
                            'rejectReason' => $cr['rejectReason'] ?? '',
                            'processedBy' => $cr['processedBy'] ?? '',
                            'processedAt' => $cr['processedAt'] ?? ''
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // pending_update 상태인 경우 pending change request 정보 조회
        $pendingChangeRequest = null;
        if (strtolower($booking['bookingStatus'] ?? '') === 'pending_update') {
            try {
                $pcrStmt = $conn->prepare("SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' ORDER BY requestedAt DESC LIMIT 1");
                if ($pcrStmt) {
                    $pcrStmt->bind_param('s', $bookingId);
                    $pcrStmt->execute();
                    $pcrResult = $pcrStmt->get_result();
                    $pcr = $pcrResult->fetch_assoc();
                    $pcrStmt->close();
                    if ($pcr) {
                        $pendingChangeRequest = [
                            'id' => $pcr['id'],
                            'changeType' => $pcr['changeType'],
                            'originalStatus' => $pcr['originalStatus'] ?? '',
                            'originalPaymentStatus' => $pcr['originalPaymentStatus'] ?? '',
                            'previousData' => $pcr['previousData'] ? json_decode($pcr['previousData'], true) : null,
                            'newData' => $pcr['newData'] ? json_decode($pcr['newData'], true) : null,
                            'requestedBy' => $pcr['requestedBy'] ?? '',
                            'requestedByType' => $pcr['requestedByType'] ?? 'agent',
                            'requestedAt' => $pcr['requestedAt'] ?? ''
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // Get payment data from new booking_payments table
        $paymentData = getPaymentsWithLegacyFormat($conn, $bookingId);
        $payments = $paymentData['payments'];
        $legacyPayments = $paymentData['legacy'];

        // Merge legacy payment data into booking for backward compatibility
        foreach ($legacyPayments as $key => $value) {
            // Only set if not already present or empty
            if (!isset($booking[$key]) || $booking[$key] === null || $booking[$key] === '') {
                $booking[$key] = $value;
            }
        }

        send_success_response([
            'booking' => $booking,
            'selectedOptions' => $selectedOptions,
            'travelers' => $travelers,
            'history' => $history,
            'pricingLabels' => $pricingLabels,
            'pricingOptions' => $pricingOptions,
            'rejectedRequest' => $rejectedRequest,
            'pendingChangeRequest' => $pendingChangeRequest,
            'payments' => $payments  // New normalized payments array
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get reservation detail: ' . $e->getMessage());
    }
}

function createReservation($conn, $input) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // 보안: agent 전용 API는 agent_accountId만 허용
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $files = $_FILES ?? [];

        // 3단계 Payment 파일 경로
        $downPaymentFilePath = null;
        $secondPaymentFilePath = null;
        $balanceFilePath = null;

        // 필수 필드 검증
        $requiredFields = ['packageId', 'departureDate', 'customerInfo', 'travelers'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // 결제 deadline은 출발일 기준으로 아래에서 자동 계산됨
        // (규칙은 2570~2647 라인 참조)

        // 트랜잭션 시작
        $conn->begin_transaction();

        try {
            // 잔여 좌석 검증
            $packageId = (int)$input['packageId'];
            $departureDate = $input['departureDate'];
            // 좌석 점유 인원만 카운트 (lap infant 제외)
            $travelerCount = 0;
            foreach (($input['travelers'] ?? []) as $t) {
                $tType = strtolower($t['type'] ?? '');
                if ($tType === 'infant' && empty($t['infantSeat'])) continue;
                $travelerCount++;
            }

            // 1) maxSeats 조회 (package_available_dates 또는 packages.maxParticipants)
            $maxSeats = 0;
            $paRow = null;
            $paStmt = $conn->prepare("SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
            if ($paStmt) {
                $paStmt->bind_param('is', $packageId, $departureDate);
                $paStmt->execute();
                $paResult = $paStmt->get_result();
                if ($paRow = $paResult->fetch_assoc()) {
                    $maxSeats = (int)($paRow['capacity'] ?? 0);
                }
                $paStmt->close();
            }
            // package_available_dates에 행이 없을 때만 packages.maxParticipants fallback
            // (행이 있고 capacity=0이면 마감 → fallback하지 않음)
            if (!$paRow) {
                $pkgStmt = $conn->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
                if ($pkgStmt) {
                    $pkgStmt->bind_param('i', $packageId);
                    $pkgStmt->execute();
                    $pkgResult = $pkgStmt->get_result();
                    if ($pkgRow = $pkgResult->fetch_assoc()) {
                        $maxSeats = (int)($pkgRow['maxParticipants'] ?? 0);
                    }
                    $pkgStmt->close();
                }
            }

            // 2) 이미 예약된 좌석 수 조회
            $bookedSeats = 0;
            $bkStmt = $conn->prepare("
                SELECT SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                FROM bookings
                WHERE packageId = ? AND departureDate = ?
                  AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                  AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
            ");
            if ($bkStmt) {
                $bkStmt->bind_param('is', $packageId, $departureDate);
                $bkStmt->execute();
                $bkResult = $bkStmt->get_result();
                if ($bkRow = $bkResult->fetch_assoc()) {
                    $bookedSeats = (int)($bkRow['booked'] ?? 0);
                }
                $bkStmt->close();
            }

            // 3) 잔여 좌석 계산 및 검증
            $remainingSeats = $maxSeats - $bookedSeats;
            if ($travelerCount > $remainingSeats) {
                $conn->rollback();
                send_error_response("Not enough seats available. Remaining: {$remainingSeats}, Requested: {$travelerCount}", 400);
            }

            // 고객 정보 처리 (기존 고객 또는 신규 고객)
            // NOTE: bookings.accountId는 "예약 생성한 agent" 소유자로 사용됨(접근 제어/리스트 필터).
            //       고객 accountId는 별도 컬럼(있으면) 또는 selectedOptions.customerInfo로 보관.
            $customerAccountId = null;
            if (!empty($input['customerInfo']['accountId'])) {
                $customerAccountId = (int)$input['customerInfo']['accountId'];
            } else {
                // 신규 고객 생성
                $customerAccountId = (int)createNewCustomer($conn, $input['customerInfo']);
            }
            
            // 예약 번호 생성
            $bookingId = generateBookingId($conn);
            
            // 신규 예약 생성 시에는 Down Payment 파일만 업로드 가능
            // (Second Payment는 Down Payment 확정 후, Balance는 Second Payment 확정 후에만 업로드 가능)
            if (isset($files['downPaymentFile']) && $files['downPaymentFile']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../uploads/payment/down/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $extension = strtolower(pathinfo($files['downPaymentFile']['name'], PATHINFO_EXTENSION));
                $extension = preg_replace('/[^a-z0-9]/', '', $extension);
                $extension = $extension ? '.' . $extension : '';
                $fileName = 'downPayment_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
                $uploadPath = $uploadDir . $fileName;
                if (move_uploaded_file($files['downPaymentFile']['tmp_name'], $uploadPath)) {
                    $downPaymentFilePath = 'uploads/payment/down/' . $fileName;
                }
            }

            // 신규 예약에서 Second Payment, Balance 파일 업로드 시도 시 경고 로그
            if (isset($files['secondPaymentFile']) && $files['secondPaymentFile']['error'] === UPLOAD_ERR_OK) {
                error_log("Warning: Second Payment file upload attempted during reservation creation - ignored (requires Down Payment confirmation first)");
            }
            if (isset($files['balanceFile']) && $files['balanceFile']['error'] === UPLOAD_ERR_OK) {
                error_log("Warning: Balance file upload attempted during reservation creation - ignored (requires Second Payment confirmation first)");
            }
            
            // 예약 정보 저장
            $packageId = $input['packageId'];
            $departureDate = $input['departureDate'];
            $departureTime = $input['departureTime'] ?? '12:20:00';
            // NOTE:
            // - traveler.type는 pricing option name(option_name)을 그대로 가질 수 있음
            // - adults/children/infants는 travelers를 기준으로 재계산하여 저장/금액계산/룸규칙에 사용한다.
            $travelerRows = (isset($input['travelers']) && is_array($input['travelers'])) ? $input['travelers'] : [];
            $adults = 0;
            $children = 0;
            $infants = 0;
            foreach ($travelerRows as $tr) {
                if (!is_array($tr)) continue;
                $t = strtolower(trim((string)($tr['type'] ?? $tr['travelerType'] ?? '')));
                if ($t === '') $t = 'adult';
                if (strpos($t, 'infant') !== false || strpos($t, 'baby') !== false || strpos($t, '유아') !== false) {
                    $infants++;
                } elseif (strpos($t, 'child') !== false || strpos($t, 'kid') !== false || strpos($t, '아동') !== false) {
                    $children++;
                } else {
                    $adults++;
                }
            }
            
            // 가격 계산 (Agent 예약은 B2B 가격 적용)
            // - 기획: "인원 옵션"은 상품에 등록된 인원별 요금(package_pricing_options)과 일치해야 함
            // - 우선순위: 날짜별 가격(package_available_dates) -> packages 기본가격
            // - Agent 예약은 항상 B2B 가격 사용
            $packageSql = "SELECT packagePrice, b2b_price, childPrice, b2b_child_price, infantPrice, b2b_infant_price, infant_seat_price, b2b_infant_seat_price FROM packages WHERE packageId = ?";
            $packageStmt = $conn->prepare($packageSql);
            $packageStmt->bind_param("i", $packageId);
            $packageStmt->execute();
            $packageResult = $packageStmt->get_result();
            $package = $packageResult->fetch_assoc();

            // B2B 가격 우선 사용 (없으면 일반가격 fallback)
            $adultPrice = (!empty($package['b2b_price'])) ? $package['b2b_price'] : ($package['packagePrice'] ?? 0);
            $childPrice = (!empty($package['b2b_child_price'])) ? $package['b2b_child_price'] : ($package['childPrice'] ?? max($adultPrice - 5000, 0));
            // Infant 가격: 설정 안되어 있으면 기본 9000페소
            $infantPrice = (!empty($package['b2b_infant_price'])) ? $package['b2b_infant_price'] : (!empty($package['infantPrice']) ? $package['infantPrice'] : 9000);
            $infantSeatPrice = (!empty($package['b2b_infant_seat_price']))
                ? $package['b2b_infant_seat_price']
                : (!empty($package['infant_seat_price']) ? $package['infant_seat_price'] : $infantPrice);

            // 날짜별 B2B 가격 조회 (package_available_dates)
            // + Sale 할인 적용
            $saleDiscountAmount = 0;
            try {
                $dateStmt = $conn->prepare("SELECT id, price, b2b_price, childPrice, b2b_child_price, infant_price, b2b_infant_price, infant_seat_price, b2b_infant_seat_price FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                if ($dateStmt) {
                    $dateStmt->bind_param("is", $packageId, $departureDate);
                    $dateStmt->execute();
                    $dateResult = $dateStmt->get_result();
                    if ($dateRow = $dateResult->fetch_assoc()) {
                        $dateId = (int)$dateRow['id'];

                        // Sale 할인 조회 (saleId, saleName도 함께 저장)
                        $saleId = null;
                        $saleName = null;
                        try {
                            $saleStmt = $conn->prepare("
                                SELECT s.id, s.sale_name, s.discount_amount
                                FROM sale_items si
                                INNER JOIN sales s ON s.id = si.sale_id
                                WHERE si.package_available_date_id = ?
                                  AND s.is_active = 1
                                  AND CURDATE() BETWEEN s.sale_start_date AND s.sale_end_date
                                LIMIT 1
                            ");
                            if ($saleStmt) {
                                $saleStmt->bind_param("i", $dateId);
                                $saleStmt->execute();
                                $saleResult = $saleStmt->get_result();
                                if ($saleRow = $saleResult->fetch_assoc()) {
                                    $saleId = (int)($saleRow['id'] ?? 0);
                                    $saleName = $saleRow['sale_name'] ?? null;
                                    $saleDiscountAmount = (float)($saleRow['discount_amount'] ?? 0);
                                }
                                $saleStmt->close();
                            }
                        } catch (Throwable $e) {
                            // ignore - Sale 조회 실패 시 할인 없이 진행
                        }

                        // 날짜별 B2B 가격이 있으면 사용 (할인 적용)
                        if (!empty($dateRow['b2b_price'])) {
                            $adultPrice = max((float)$dateRow['b2b_price'] - $saleDiscountAmount, 0);
                        } elseif (!empty($dateRow['price'])) {
                            // B2B 가격 없으면 일반 날짜가격 사용 (할인 적용)
                            $adultPrice = max((float)$dateRow['price'] - $saleDiscountAmount, 0);
                        }
                        if (!empty($dateRow['b2b_child_price'])) {
                            $childPrice = (float)$dateRow['b2b_child_price'];
                        } elseif (!empty($dateRow['childPrice'])) {
                            $childPrice = (float)$dateRow['childPrice'];
                        } else {
                            // 날짜별 childPrice가 없으면 adultPrice - 5000으로 재계산
                            $childPrice = max($adultPrice - 5000, 0);
                        }
                        if (!empty($dateRow['b2b_infant_price'])) {
                            $infantPrice = (float)$dateRow['b2b_infant_price'];
                        } elseif (!empty($dateRow['infant_price'])) {
                            $infantPrice = (float)$dateRow['infant_price'];
                        }
                        if (!empty($dateRow['b2b_infant_seat_price'])) {
                            $infantSeatPrice = (float)$dateRow['b2b_infant_seat_price'];
                        } elseif (!empty($dateRow['infant_seat_price'])) {
                            $infantSeatPrice = (float)$dateRow['infant_seat_price'];
                        }
                    }
                    $dateStmt->close();
                }
            } catch (Throwable $e) {
                // ignore - 날짜별 가격 조회 실패 시 패키지 기본 가격 사용
            }

            // package_pricing_options가 있으면 해당 값으로 덮어쓴다
            // + traveler.type(=option_name) 기준으로 직접 매칭할 수 있도록 맵을 구성한다.
            $pricingMap = []; // lower(option_name) => price
            try {
                $hasPricingTable = false;
                $tbl = $conn->query("SHOW TABLES LIKE 'package_pricing_options'");
                if ($tbl && $tbl->num_rows > 0) $hasPricingTable = true;
                if ($hasPricingTable) {
                    $ps = $conn->prepare("SELECT option_name, price FROM package_pricing_options WHERE package_id = ? ORDER BY pricing_id ASC");
                    if ($ps) {
                        $ps->bind_param('i', $packageId);
                        $ps->execute();
                        $pr = $ps->get_result();
                        while ($r = $pr->fetch_assoc()) {
                            $name = strtolower(trim((string)($r['option_name'] ?? '')));
                            $price = isset($r['price']) ? floatval($r['price']) : 0;
                            if ($name === '') continue;
                            $pricingMap[$name] = $price;
                            if ($name === 'adult' || $name === 'adults' || str_contains($name, 'adult') || str_contains($name, '성인')) {
                                $adultPrice = $price;
                            } elseif ($name === 'child' || str_contains($name, 'child') || str_contains($name, '아동')) {
                                $childPrice = $price;
                            } elseif ($name === 'infant' || str_contains($name, 'infant') || str_contains($name, '유아')) {
                                $infantPrice = $price;
                            }
                        }
                        $ps->close();
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }

            // Base amount:
            // - traveler.type가 option_name과 직접 일치하면 해당 price 적용
            // - 매칭 실패 시 adult/child/infant 분류 가격으로 fallback
            $baseAmount = 0;
            foreach ($travelerRows as $tr) {
                if (!is_array($tr)) continue;
                $typeRaw = trim((string)($tr['type'] ?? $tr['travelerType'] ?? ''));
                $type = strtolower($typeRaw);
                if ($type === '') $type = 'adult';
                if (array_key_exists($type, $pricingMap)) {
                    $baseAmount += (float)$pricingMap[$type];
                    continue;
                }
                if (strpos($type, 'infant') !== false || strpos($type, 'baby') !== false || strpos($type, '유아') !== false) {
                    if (filter_var($tr['infantSeat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $baseAmount += (float)$infantSeatPrice;
                    } else {
                        $baseAmount += (float)$infantPrice;
                    }
                } elseif (strpos($type, 'child') !== false || strpos($type, 'kid') !== false || strpos($type, '아동') !== false) {
                    $baseAmount += (float)$childPrice;
                } else {
                    $baseAmount += (float)$adultPrice;
                }
            }
            
            // 룸 옵션 가격 계산 (1인 예약이어도 싱글룸 추가요금 부과)
            $roomAmount = 0;
            if (!empty($input['selectedRooms'])) {
                foreach ($input['selectedRooms'] as $room) {
                    if (!is_array($room)) continue;
                    $roomPrice = (float)($room['roomPrice'] ?? $room['price'] ?? 0);
                    $roomCount = (int)($room['count'] ?? 1);
                    if ($roomCount <= 0) continue;
                    $roomAmount += $roomPrice * $roomCount;
                }
            }
            
            // 추가 옵션 가격 계산
            // - 프론트 구현/환경에 따라 selectedOptions가 "배열(옵션 리스트)" 또는 "객체(옵션 값 맵)" 형태로 올 수 있어,
            //   배열(list) + 각 원소가 배열인 경우에만 금액 계산을 수행한다.
            $optionsAmount = 0;
            if (!empty($input['selectedOptions']) && is_array($input['selectedOptions'])) {
                foreach ($input['selectedOptions'] as $option) {
                    if (!is_array($option)) continue;
                    $optionsAmount += (float)($option['price'] ?? 0) * (float)($option['quantity'] ?? 0);
                }
            }

            // 항공 옵션 가격 계산 (여행자별 flightOptionPrices 합산)
            $flightOptionsAmount = 0;
            foreach ($travelerRows as $tr) {
                if (!is_array($tr)) continue;
                if (!empty($tr['flightOptionPrices']) && is_array($tr['flightOptionPrices'])) {
                    foreach ($tr['flightOptionPrices'] as $price) {
                        $flightOptionsAmount += (float)$price;
                    }
                }
            }

            // Visa 금액 계산 (여행자별 visaType 기준)
            $visaAmount = 0;
            foreach ($travelerRows as $tr) {
                if (!is_array($tr)) continue;
                $visaType = strtolower(trim((string)($tr['visaType'] ?? 'with_visa')));
                if ($visaType === 'group') {
                    $visaAmount += 1500; // Group Visa +₱1500
                } elseif ($visaType === 'individual') {
                    $visaAmount += 1900; // Individual Visa +₱1900
                }
            }

            $totalAmount = $baseAmount + $roomAmount + $optionsAmount + $flightOptionsAmount + $visaAmount;
            
            // selectedOptions JSON 생성
            $selectedOptions = [
                'selectedRooms' => $input['selectedRooms'] ?? [],
                'selectedOptions' => $input['selectedOptions'] ?? [],
                'customerInfo' => $input['customerInfo'] ?? [],
                'seatRequest' => $input['seatRequest'] ?? '',
                'otherRequest' => $input['otherRequest'] ?? '',
                'memo' => $input['memo'] ?? ''
            ];
            // 고객 accountId는 실제 고객으로 보정 (bookings.accountId는 agent 소유자)
            try {
                if (!isset($selectedOptions['customerInfo']) || !is_array($selectedOptions['customerInfo'])) $selectedOptions['customerInfo'] = [];
                $selectedOptions['customerInfo']['accountId'] = $customerAccountId;
            } catch (Throwable $e) { /* ignore */ }
            
            // bookings 테이블 컬럼 확인 (INSERT 전에 확인)
            $bookingsColumns = [];
            $bookingsColumnCheck = $conn->query("SHOW COLUMNS FROM bookings");
            if ($bookingsColumnCheck) {
                while ($col = $bookingsColumnCheck->fetch_assoc()) {
                    $bookingsColumns[] = strtolower($col['Field']);
                }
            }

            // 고객 accountId 저장 컬럼(환경별) 지원
            $customerAccountIdCol = null;
            if (in_array('customeraccountid', $bookingsColumns, true)) $customerAccountIdCol = 'customerAccountId';
            else if (in_array('customer_account_id', $bookingsColumns, true)) $customerAccountIdCol = 'customer_account_id';
            else if (in_array('customerid', $bookingsColumns, true)) $customerAccountIdCol = 'customerId';
            else if (in_array('userid', $bookingsColumns, true)) $customerAccountIdCol = 'userId';

            // 레거시 deposit* 컬럼 대신 downPayment* 컬럼 사용 (이미 테이블에 존재해야 함)
            
            $packageNameSql = "SELECT packageName, destination, COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = packageId), duration_days, 1) as duration_days FROM packages WHERE packageId = ?";
            $packageNameStmt = $conn->prepare($packageNameSql);
            $packageNameStmt->bind_param("i", $packageId);
            $packageNameStmt->execute();
            $packageNameResult = $packageNameStmt->get_result();
            $packageNameRow = $packageNameResult->fetch_assoc();
            $packageName = $packageNameRow['packageName'] ?? '';
            $packageDestination = $packageNameRow['destination'] ?? 'Korea';
            $packageDurationDays = (int)($packageNameRow['duration_days'] ?? 3);
            
            $selectedOptionsJson = json_encode($selectedOptions, JSON_UNESCAPED_UNICODE);
            $specialRequests = ($input['seatRequest'] ?? '') . ($input['otherRequest'] ?? '');
            $contactEmail = $input['customerInfo']['email'] ?? '';
            $contactPhone = $input['customerInfo']['phone'] ?? '';
            
            // 3단계 결제 금액 계산 (Visa Fee, Flight Options 포함)
            // Visa Fee 계산: Group ₱1,500, Individual ₱1,900
            $visaFee = 0;
            $flightOptionsTotal = 0;
            foreach ($travelerRows as $tr) {
                $visaType = strtolower(trim((string)($tr['visaType'] ?? '')));
                if ($visaType === 'group') {
                    $visaFee += 1500;
                } elseif ($visaType === 'individual') {
                    $visaFee += 1900;
                }
                // Flight Options
                if (isset($tr['flightOptionPrices']) && is_array($tr['flightOptionPrices'])) {
                    foreach ($tr['flightOptionPrices'] as $price) {
                        $flightOptionsTotal += (float)$price;
                    }
                }
            }

            // 출발일까지 남은 일수 계산
            $daysUntilDeparture = null;
            if (!empty($departureDate)) {
                $depDateTime = new DateTime($departureDate);
                $todayDateTime = new DateTime();
                $todayDateTime->setTime(0, 0, 0);
                $depDateTime->setTime(0, 0, 0);
                $daysUntilDeparture = (int)$todayDateTime->diff($depDateTime)->format('%r%a');
            }

            // ========== 결제 규칙 (승인일 기준 - 생성 시점에서는 임시값 사용) ==========
            // 규칙 1: 출발일까지 34일 미만 → Full Payment만, 3시간 데드라인
            // 규칙 2: 출발일까지 34~39일 → Full Payment만, +24시간
            // 규칙 3: 출발일까지 40~44일 → Middle 또는 Full 선택
            // 규칙 4: 출발일까지 44일 초과 → Staged/Middle/Full 선택
            // ※ 최종 deadline은 관리자 승인 시 approvedAt 기준으로 재계산됨

            require_once __DIR__ . '/../../../backend/lib/deadline_calculator.php';

            $userRequestedPaymentType = $input['paymentType'] ?? 'staged';
            if (!in_array($userRequestedPaymentType, ['staged', 'middle', 'full'])) {
                $userRequestedPaymentType = 'staged';
            }

            // 유효한 결제 타입 검증
            $validTypes = getValidPaymentTypes($daysUntilDeparture ?? 999);
            if (!in_array($userRequestedPaymentType, $validTypes)) {
                $userRequestedPaymentType = $validTypes[0]; // 첫 번째 유효 타입으로 강제
            }

            // 임시 승인일 = NOW() (실제 deadline은 승인 시 재계산)
            $tempApprovalDate = date('Y-m-d H:i:s');
            $deadlines = calculateBookingDeadlines($userRequestedPaymentType, $daysUntilDeparture ?? 999, $departureDate, $tempApprovalDate);
            $paymentType = $deadlines['paymentType'];

            // 금액 계산
            if ($paymentType === 'full') {
                $downPaymentAmount = 0;
                $downPaymentDueDate = null;
                $advancePaymentAmount = 0;
                $advancePaymentDueDate = null;
                $balanceAmount = 0;
                $balanceDueDate = null;
                $fullPaymentAmount = $totalAmount;
                $fullPaymentDueDate = $deadlines['fullPaymentDueDate'];
            } else if ($paymentType === 'middle') {
                $travelerCount = $adults + $children;
                $middleAmount = (5000 * $travelerCount) + (10000 * $travelerCount) + $visaFee;
                $downPaymentAmount = $middleAmount; // legacy 호환
                $downPaymentDueDate = $deadlines['secondPaymentDueDate']; // middle deadline
                $advancePaymentAmount = $middleAmount;
                $advancePaymentDueDate = null;
                $balanceAmount = max(0, $totalAmount - $middleAmount);
                $balanceDueDate = $deadlines['balanceDueDate'];
                $fullPaymentAmount = null;
                $fullPaymentDueDate = null;
            } else {
                // Staged
                $downPaymentAmount = 5000 * ($adults + $children);
                $downPaymentDueDate = $deadlines['downPaymentDueDate'];
                $advancePaymentAmount = (10000 * ($adults + $children)) + $visaFee;
                $advancePaymentDueDate = $deadlines['secondPaymentDueDate'];
                $balanceAmount = max(0, $totalAmount - $downPaymentAmount - $advancePaymentAmount);
                $balanceDueDate = $deadlines['balanceDueDate'];
                $fullPaymentAmount = null;
                $fullPaymentDueDate = null;
            }
            
            // INSERT 쿼리 구성 (3단계 결제 정보 포함)
            $customerColsSql = '';
            $customerColsValuesSql = '';
            if (!empty($customerAccountIdCol)) {
                $customerColsSql = ", {$customerAccountIdCol}";
                $customerColsValuesSql = ", ?";
            }

            // Agent 예약은 항상 B2B price_tier
            $priceTier = 'B2B';

            $insertSql = "
                INSERT INTO bookings (
                    bookingId, accountId, agentId, packageId, packageName, packagePrice, price_tier,
                    departureDate, departureTime, adults, children, infants,
                    totalAmount, bookingStatus, paymentStatus, paymentType, selectedOptions,
                    specialRequests, contactEmail, contactPhone{$customerColsSql},
                    downPaymentAmount, downPaymentDueDate,
                    advancePaymentAmount, advancePaymentDueDate,
                    balanceAmount, balanceDueDate,
                    fullPaymentAmount, fullPaymentDueDate,
                    adultPrice, childPrice, infantPrice, infantSeatPrice, visaFee, flightOptionFee,
                    saleId, saleName, saleDiscountAmount,
                    createdAt
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'pending', ?, ?, ?, ?, ?{$customerColsValuesSql}, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $insertStmt = $conn->prepare($insertSql);
            // 타입 문자열: s(bookingId), i(accountId), i(agentId), i(packageId), s(packageName), d(adultPrice), s(priceTier),
            //              s(departureDate), s(departureTime), i(adults), i(children), i(infants),
            //              d(totalAmount), s(paymentType), s(selectedOptionsJson), s(specialRequests), s(contactEmail), s(contactPhone),
            //              [i(customerAccountId)], d(downPaymentAmount), s(downPaymentDueDate),
            //              d(advancePaymentAmount), s(advancePaymentDueDate), d(balanceAmount), s(balanceDueDate),
            //              d(fullPaymentAmount), s(fullPaymentDueDate)
            // accountId와 agentId 모두 agentAccountId로 저장
            if (!empty($customerAccountIdCol)) {
                $insertStmt->bind_param(
                    "siiisdsssiiidsssssidsdsdsdsddddddisd",
                    $bookingId, $agentAccountId, $agentAccountId, $packageId, $packageName, $adultPrice, $priceTier,
                    $departureDate, $departureTime, $adults, $children, $infants,
                    $totalAmount, $paymentType, $selectedOptionsJson, $specialRequests,
                    $contactEmail, $contactPhone, $customerAccountId,
                    $downPaymentAmount, $downPaymentDueDate,
                    $advancePaymentAmount, $advancePaymentDueDate,
                    $balanceAmount, $balanceDueDate,
                    $fullPaymentAmount, $fullPaymentDueDate,
                    $adultPrice, $childPrice, $infantPrice, $infantSeatPrice, $visaFee, $flightOptionsTotal,
                    $saleId, $saleName, $saleDiscountAmount
                );
            } else {
                $insertStmt->bind_param(
                    "siiisdsssiiidsssssdsdsdsdsddddddisd",
                    $bookingId, $agentAccountId, $agentAccountId, $packageId, $packageName, $adultPrice, $priceTier,
                    $departureDate, $departureTime, $adults, $children, $infants,
                    $totalAmount, $paymentType, $selectedOptionsJson, $specialRequests,
                    $contactEmail, $contactPhone,
                    $downPaymentAmount, $downPaymentDueDate,
                    $advancePaymentAmount, $advancePaymentDueDate,
                    $balanceAmount, $balanceDueDate,
                    $fullPaymentAmount, $fullPaymentDueDate,
                    $adultPrice, $childPrice, $infantPrice, $infantSeatPrice, $visaFee, $flightOptionsTotal,
                    $saleId, $saleName, $saleDiscountAmount
                );
            }
            $insertStmt->execute();
            
            // transactNo 컬럼 처리
            $hasTransactNo = in_array('transactno', $bookingsColumns);
            if (!$hasTransactNo) {
                try {
                    $alterSql = "ALTER TABLE bookings ADD COLUMN transactNo VARCHAR(20) AFTER bookingId";
                    $conn->query($alterSql);
                    $updateSql = "UPDATE bookings SET transactNo = bookingId WHERE bookingId = ? AND (transactNo IS NULL OR transactNo = '')";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("s", $bookingId);
                    $updateStmt->execute();
                } catch (Exception $e) {
                    // 컬럼 추가 실패는 무시
                }
            } else {
                $updateSql = "UPDATE bookings SET transactNo = ? WHERE bookingId = ? AND (transactNo IS NULL OR transactNo = '')";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ss", $bookingId, $bookingId);
                $updateStmt->execute();
            }

            // Down Payment 파일 경로 저장 (신규 예약에서는 Down Payment만 업로드 가능)
            if ($downPaymentFilePath && in_array('downpaymentfile', $bookingsColumns)) {
                $downPaymentFileStmt = $conn->prepare("UPDATE bookings SET downPaymentFile = ? WHERE bookingId = ?");
                $downPaymentFileStmt->bind_param("ss", $downPaymentFilePath, $bookingId);
                $downPaymentFileStmt->execute();
                $downPaymentFileStmt->close();
            }

            // Full Payment 파일 업로드 처리 (paymentType이 'full'인 경우만)
            if ($paymentType === 'full' && isset($files['fullPaymentFile']) && $files['fullPaymentFile']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../uploads/payment/full/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $extension = strtolower(pathinfo($files['fullPaymentFile']['name'], PATHINFO_EXTENSION));
                $extension = preg_replace('/[^a-z0-9]/', '', $extension);
                $extension = $extension ? '.' . $extension : '';
                $fileName = 'fullPayment_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
                $uploadPath = $uploadDir . $fileName;
                if (move_uploaded_file($files['fullPaymentFile']['tmp_name'], $uploadPath)) {
                    $fullPaymentFilePath = 'uploads/payment/full/' . $fileName;
                    $fullPaymentFileStmt = $conn->prepare("UPDATE bookings SET fullPaymentFile = ?, fullPaymentUploadedAt = NOW() WHERE bookingId = ?");
                    $fullPaymentFileStmt->bind_param("ss", $fullPaymentFilePath, $bookingId);
                    $fullPaymentFileStmt->execute();
                    $fullPaymentFileStmt->close();
                }
            }

            // www/user와 동일하게 transactNo에 bookingId 값을 저장
            // booking_travelers 테이블의 transactNo 컬럼에는 항상 bookingId 값이 저장됨
            $transactNoForTravelers = $bookingId; // www/user와 동일하게 bookingId 사용
            
            // 여행자 정보 저장 (booking_travelers 테이블 컬럼명 확인)
            $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers LIKE 'transactNo'");
            $useTransactNo = ($travelerColumnCheck->num_rows > 0);
            $bookingIdColumn = $useTransactNo ? 'transactNo' : 'bookingId';
            
            $firstNameColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers LIKE 'firstName'");
            $useFirstName = ($firstNameColumnCheck->num_rows > 0);
            $firstNameColumn = $useFirstName ? 'firstName' : 'fName';
            $lastNameColumn = $useFirstName ? 'lastName' : 'lName';

            // childRoom 컬럼 자동 생성 (없으면)
            $childRoomCheck = $conn->query("SHOW COLUMNS FROM booking_travelers LIKE 'childRoom'");
            if ($childRoomCheck && $childRoomCheck->num_rows === 0) {
                try {
                    $conn->query("ALTER TABLE booking_travelers ADD COLUMN childRoom TINYINT(1) DEFAULT 0 COMMENT 'Child room option (1=Yes, 0=No)'");
                    error_log("Created childRoom column in booking_travelers table");
                } catch (Exception $e) {
                    error_log("Failed to create childRoom column: " . $e->getMessage());
                }
            }

            // infantSeat 컬럼 자동 생성 (없으면)
            $infantSeatCheck = $conn->query("SHOW COLUMNS FROM booking_travelers LIKE 'infantSeat'");
            if ($infantSeatCheck && $infantSeatCheck->num_rows === 0) {
                try {
                    $conn->query("ALTER TABLE booking_travelers ADD COLUMN infantSeat TINYINT(1) DEFAULT 0 COMMENT 'Infant seat option (1=occupies seat, 0=lap infant)'");
                    error_log("Created infantSeat column in booking_travelers table");
                } catch (Exception $e) {
                    error_log("Failed to create infantSeat column: " . $e->getMessage());
                }
            }

            foreach ($input['travelers'] as $index => $traveler) {
                // passport photo upload: create-reservation에서 FormData(passportPhoto_{idx})로 전송됨
                // - 업로드가 있으면 traveler.passportImage로 저장하여 상세 화면에서 노출 가능 (요구사항 id 61-3, 66)
                try {
                    if (is_array($traveler)) {
                        $photoKey = (string)($traveler['passportPhotoKey'] ?? '');
                        if ($photoKey !== '' && isset($files[$photoKey]) && is_array($files[$photoKey]) && ($files[$photoKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $tmp = $files[$photoKey]['tmp_name'] ?? '';
                            if ($tmp !== '' && is_uploaded_file($tmp)) {
                                $uploadsDir = __DIR__ . '/../../../uploads/passports';
                                if (!is_dir($uploadsDir)) {
                                    @mkdir($uploadsDir, 0755, true);
                                }
                                $origName = (string)($files[$photoKey]['name'] ?? 'passport_photo');
                                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                                if ($ext === '') $ext = 'jpg';
                                $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
                                if ($safeExt === '') $safeExt = 'jpg';
                                $fileName = 'booking_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$bookingId) . '_traveler_' . intval($index) . '_' . time() . '.' . $safeExt;
                                $dest = $uploadsDir . '/' . $fileName;
                                if (move_uploaded_file($tmp, $dest)) {
                                    $traveler['passportImage'] = 'uploads/passports/' . $fileName;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore
                }

                // visa document upload: create-reservation에서 FormData(visaDocument_{idx})로 전송됨
                try {
                    if (is_array($traveler)) {
                        $visaKey = (string)($traveler['visaDocumentKey'] ?? '');
                        if ($visaKey !== '' && isset($files[$visaKey]) && is_array($files[$visaKey]) && ($files[$visaKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $tmp = $files[$visaKey]['tmp_name'] ?? '';
                            if ($tmp !== '' && is_uploaded_file($tmp)) {
                                $uploadsDir = __DIR__ . '/../../../uploads/visa';
                                if (!is_dir($uploadsDir)) {
                                    @mkdir($uploadsDir, 0755, true);
                                }
                                $origName = (string)($files[$visaKey]['name'] ?? 'visa_document');
                                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                                if ($ext === '') $ext = 'pdf';
                                $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
                                if (!in_array($safeExt, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) $safeExt = 'pdf';
                                $fileName = 'visa_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$bookingId) . '_traveler_' . intval($index) . '_' . time() . '.' . $safeExt;
                                $dest = $uploadsDir . '/' . $fileName;
                                if (move_uploaded_file($tmp, $dest)) {
                                    $traveler['visaDocument'] = 'uploads/visa/' . $fileName;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore
                }

                // booking_travelers 테이블 컬럼 확인
                $travelerColumns = [];
                $travelerColumnResult = $conn->query("SHOW COLUMNS FROM booking_travelers");
                while ($col = $travelerColumnResult->fetch_assoc()) {
                    $travelerColumns[] = strtolower($col['Field']);
                }
                
                $travelerFields = [];
                $travelerValues = [];
                $travelerTypes = '';
                
                // 기본 필드 (www/user와 동일하게 transactNo 컬럼에 bookingId 값 저장)
                $travelerFields[] = $bookingIdColumn;
                $travelerValues[] = $transactNoForTravelers; // www/user와 동일하게 bookingId 값 사용
                $travelerTypes .= 's';
                
                // travelerIndex 컬럼이 있으면 추가
                if (in_array('travelerindex', $travelerColumns)) {
                $travelerFields[] = 'travelerIndex';
                $travelerValues[] = $index;
                $travelerTypes .= 'i';
                }
                
                // isMainTraveler 컬럼이 있으면 추가
                if (in_array('ismaintraveler', $travelerColumns)) {
                $isMain = ($index === 0 || ($traveler['isMainTraveler'] ?? false)) ? 1 : 0;
                    $travelerFields[] = 'isMainTraveler';
                $travelerValues[] = $isMain;
                $travelerTypes .= 'i';
                }
                
                // travelerType 컬럼이 있으면 추가
                if (in_array('travelertype', $travelerColumns)) {
                $travelerType = $traveler['type'] ?? 'adult';
                    $travelerFields[] = 'travelerType';
                $travelerValues[] = $travelerType;
                $travelerTypes .= 's';
                }
                
                // title
                if (in_array('title', $travelerColumns)) {
                    $travelerFields[] = 'title';
                    $travelerValues[] = $traveler['title'] ?? '';
                    $travelerTypes .= 's';
                }
                
                // firstName / fName
                if (in_array('firstname', $travelerColumns)) {
                    $travelerFields[] = 'firstName';
                    $travelerValues[] = $traveler['firstName'] ?? '';
                    $travelerTypes .= 's';
                } elseif (in_array('fname', $travelerColumns)) {
                    $travelerFields[] = 'fName';
                    $travelerValues[] = $traveler['firstName'] ?? '';
                    $travelerTypes .= 's';
                }
                
                // lastName / lName
                if (in_array('lastname', $travelerColumns)) {
                    $travelerFields[] = 'lastName';
                    $travelerValues[] = $traveler['lastName'] ?? '';
                    $travelerTypes .= 's';
                } elseif (in_array('lname', $travelerColumns)) {
                    $travelerFields[] = 'lName';
                    $travelerValues[] = $traveler['lastName'] ?? '';
                    $travelerTypes .= 's';
                }
                
                // gender
                if (in_array('gender', $travelerColumns)) {
                    // DB enum('male','female') 호환을 위해 다양한 입력을 정규화
                    $g = strtolower(trim((string)($traveler['gender'] ?? '')));
                    if ($g === 'm' || $g === 'male' || $g === 'man' || $g === 'mr') $g = 'male';
                    elseif ($g === 'f' || $g === 'female' || $g === 'woman' || $g === 'ms' || $g === 'mrs') $g = 'female';
                    else $g = 'male'; // 기본값(필수 컬럼이라 비워둘 수 없음)
                    $travelerFields[] = 'gender';
                    $travelerValues[] = $g;
                    $travelerTypes .= 's';
                }
                
                // birthDate (여러 가능한 컬럼명 확인, YYYY-MM-DD 형식으로 변환)
                $birthDate = null;
                if (!empty($traveler['birthDate'])) {
                    // YYYYMMDD 형식을 YYYY-MM-DD로 변환
                    $birthDateStr = $traveler['birthDate'];
                    if (strlen($birthDateStr) === 8 && is_numeric($birthDateStr)) {
                        // YYYYMMDD 형식인 경우
                        $birthDate = substr($birthDateStr, 0, 4) . '-' . substr($birthDateStr, 4, 2) . '-' . substr($birthDateStr, 6, 2);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDateStr)) {
                        // 이미 YYYY-MM-DD 형식인 경우
                        $birthDate = $birthDateStr;
                    } else {
                        // 그 외 (날짜 파싱 시도)
                        try {
                            $date = new DateTime($birthDateStr);
                            $birthDate = $date->format('Y-m-d');
                        } catch (Exception $e) {
                            $birthDate = null;
                        }
                    }
                    
                    if ($birthDate) {
                        if (in_array('birthdate', $travelerColumns)) {
                    $travelerFields[] = 'birthDate';
                            $travelerValues[] = $birthDate;
                            $travelerTypes .= 's';
                        } elseif (in_array('dateofbirth', $travelerColumns)) {
                            $travelerFields[] = 'dateOfBirth';
                            $travelerValues[] = $birthDate;
                    $travelerTypes .= 's';
                }
                    }
                }
                
                // age (생년월일로부터 자동 계산)
                if (in_array('age', $travelerColumns)) {
                    $age = null;
                    if (isset($traveler['age']) && $traveler['age'] > 0) {
                        $age = (int)$traveler['age'];
                    } elseif ($birthDate) {
                        // 생년월일로부터 나이 계산
                        try {
                            $birth = new DateTime($birthDate);
                            $today = new DateTime();
                            $age = $today->diff($birth)->y;
                        } catch (Exception $e) {
                            $age = null;
                        }
                    }
                    if ($age !== null) {
                    $travelerFields[] = 'age';
                        $travelerValues[] = $age;
                    $travelerTypes .= 'i';
                    }
                }
                
                // contact
                if (in_array('contact', $travelerColumns) && !empty($traveler['contact'])) {
                    $travelerFields[] = 'contact';
                    $travelerValues[] = $traveler['contact'];
                    $travelerTypes .= 's';
                }
                
                // email
                if (in_array('email', $travelerColumns) && !empty($traveler['email'])) {
                    $travelerFields[] = 'email';
                    $travelerValues[] = $traveler['email'];
                    $travelerTypes .= 's';
                }
                
                // nationality
                if (in_array('nationality', $travelerColumns) && !empty($traveler['nationality'])) {
                    $travelerFields[] = 'nationality';
                    $travelerValues[] = $traveler['nationality'];
                    $travelerTypes .= 's';
                }
                
                // passportNumber
                if (in_array('passportnumber', $travelerColumns) && !empty($traveler['passportNumber'])) {
                    $travelerFields[] = 'passportNumber';
                    $travelerValues[] = $traveler['passportNumber'];
                    $travelerTypes .= 's';
                }
                
                // passportIssueDate (있는 경우, YYYY-MM-DD 형식으로 변환)
                $passportIssueDate = null;
                if (!empty($traveler['passportIssueDate']) || !empty($traveler['passportIssue'])) {
                    $passportIssueDateStr = $traveler['passportIssueDate'] ?? $traveler['passportIssue'] ?? null;
                    if ($passportIssueDateStr) {
                        if (strlen($passportIssueDateStr) === 8 && is_numeric($passportIssueDateStr)) {
                            $passportIssueDate = substr($passportIssueDateStr, 0, 4) . '-' . substr($passportIssueDateStr, 4, 2) . '-' . substr($passportIssueDateStr, 6, 2);
                        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportIssueDateStr)) {
                            $passportIssueDate = $passportIssueDateStr;
                        } else {
                            try {
                                $date = new DateTime($passportIssueDateStr);
                                $passportIssueDate = $date->format('Y-m-d');
                            } catch (Exception $e) {
                                $passportIssueDate = null;
                            }
                        }
                    }
                    
                    if ($passportIssueDate) {
                        if (in_array('passportissuedate', $travelerColumns)) {
                            $travelerFields[] = 'passportIssueDate';
                            $travelerValues[] = $passportIssueDate;
                            $travelerTypes .= 's';
                        } elseif (in_array('passportissueddate', $travelerColumns)) {
                            $travelerFields[] = 'passportIssuedDate';
                            $travelerValues[] = $passportIssueDate;
                            $travelerTypes .= 's';
                        }
                    }
                }
                
                // passportExpiry (여러 가능한 컬럼명 확인, YYYY-MM-DD 형식으로 변환)
                $passportExpiry = null;
                if (!empty($traveler['passportExpiry'])) {
                    $passportExpiryStr = $traveler['passportExpiry'];
                    if (strlen($passportExpiryStr) === 8 && is_numeric($passportExpiryStr)) {
                        // YYYYMMDD 형식인 경우
                        $passportExpiry = substr($passportExpiryStr, 0, 4) . '-' . substr($passportExpiryStr, 4, 2) . '-' . substr($passportExpiryStr, 6, 2);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportExpiryStr)) {
                        // 이미 YYYY-MM-DD 형식인 경우
                        $passportExpiry = $passportExpiryStr;
                    } else {
                        try {
                            $date = new DateTime($passportExpiryStr);
                            $passportExpiry = $date->format('Y-m-d');
                        } catch (Exception $e) {
                            $passportExpiry = null;
                        }
                    }
                    
                    if ($passportExpiry) {
                        if (in_array('passportexpiry', $travelerColumns)) {
                    $travelerFields[] = 'passportExpiry';
                            $travelerValues[] = $passportExpiry;
                            $travelerTypes .= 's';
                        } elseif (in_array('passportexpirydate', $travelerColumns)) {
                            $travelerFields[] = 'passportExpiryDate';
                            $travelerValues[] = $passportExpiry;
                    $travelerTypes .= 's';
                }
                    }
                }
                
                // passportImage (있는 경우)
                if (!empty($traveler['passportImage']) && in_array('passportimage', $travelerColumns)) {
                    $travelerFields[] = 'passportImage';
                    $travelerValues[] = $traveler['passportImage'];
                    $travelerTypes .= 's';
                }

                // visaDocument (있는 경우)
                if (!empty($traveler['visaDocument']) && in_array('visadocument', $travelerColumns)) {
                    $travelerFields[] = 'visaDocument';
                    $travelerValues[] = $traveler['visaDocument'];
                    $travelerTypes .= 's';
                }

                // visaStatus (visaRequired 대신 visaStatus 사용하는 경우)
                if (in_array('visastatus', $travelerColumns)) {
                    $visaStatus = 'not_required';
                    if (!empty($traveler['visaRequired']) && $traveler['visaRequired']) {
                        $visaStatus = 'applied';
                    }
                    $travelerFields[] = 'visaStatus';
                    $travelerValues[] = $visaStatus;
                    $travelerTypes .= 's';
                } elseif (in_array('visarequired', $travelerColumns)) {
                    // visaRequired (boolean)를 사용하는 경우
                    $travelerFields[] = 'visaRequired';
                    $travelerValues[] = isset($traveler['visaRequired']) ? (int)$traveler['visaRequired'] : 0;
                    $travelerTypes .= 'i';
                }

                // visaType (group, individual, with_visa, foreign)
                if (in_array('visatype', $travelerColumns)) {
                    $visaType = $traveler['visaType'] ?? 'with_visa';
                    if (!in_array($visaType, ['group', 'individual', 'with_visa', 'foreign'])) {
                        $visaType = 'with_visa';
                    }
                    $travelerFields[] = 'visaType';
                    $travelerValues[] = $visaType;
                    $travelerTypes .= 's';
                }

                // childRoom (child 타입일 때만 의미 있음)
                if (in_array('childroom', $travelerColumns)) {
                    $childRoom = isset($traveler['childRoom']) ? (int)$traveler['childRoom'] : 0;
                    $travelerFields[] = 'childRoom';
                    $travelerValues[] = $childRoom;
                    $travelerTypes .= 'i';
                }

                // infantSeat (infant 타입일 때만 의미 있음)
                if (in_array('infantseat', $travelerColumns)) {
                    $infantSeat = isset($traveler['infantSeat']) ? (int)$traveler['infantSeat'] : 0;
                    $travelerFields[] = 'infantSeat';
                    $travelerValues[] = $infantSeat;
                    $travelerTypes .= 'i';
                }

                // profile_source
                if (in_array('profile_source', $travelerColumns)) {
                    $profileSource = $traveler['profile_source'] ?? $traveler['profileSource'] ?? '';
                    $travelerFields[] = 'profile_source';
                    $travelerValues[] = $profileSource;
                    $travelerTypes .= 's';
                }

                // specialRequests 또는 remarks
                if (!empty($traveler['remarks']) || !empty($traveler['specialRequests'])) {
                    $remarks = $traveler['remarks'] ?? $traveler['specialRequests'] ?? '';
                    if (in_array('specialrequests', $travelerColumns)) {
                        $travelerFields[] = 'specialRequests';
                        $travelerValues[] = $remarks;
                        $travelerTypes .= 's';
                    } elseif (in_array('remarks', $travelerColumns)) {
                    $travelerFields[] = 'remarks';
                        $travelerValues[] = $remarks;
                    $travelerTypes .= 's';
                    }
                }
                
                $travelerPlaceholders = str_repeat('?,', count($travelerFields) - 1) . '?';
                $travelerSql = "INSERT INTO booking_travelers (" . implode(', ', $travelerFields) . ") VALUES ($travelerPlaceholders)";
                
                $travelerStmt = $conn->prepare($travelerSql);
                if (!$travelerStmt) {
                    throw new Exception('Failed to prepare traveler SQL: ' . $conn->error);
                }
                
                $travelerStmt->bind_param($travelerTypes, ...$travelerValues);
                if (!$travelerStmt->execute()) {
                    throw new Exception('Failed to execute traveler insert: ' . $travelerStmt->error . ' | SQL: ' . $travelerSql);
                }

                // 비자 신청 자동 생성: visaRequired가 true인 경우
                $bookingTravelerId = $conn->insert_id;
                if (!empty($traveler['visaRequired']) && $traveler['visaRequired'] && $bookingTravelerId > 0) {
                    try {
                        // applicationNo 생성 (VA + 날짜 + 랜덤3자리)
                        $visaApplicationNo = 'VA' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

                        // 신청자 이름 조합
                        $visaApplicantName = trim(($traveler['firstName'] ?? '') . ' ' . ($traveler['lastName'] ?? ''));
                        if (empty($visaApplicantName)) {
                            $visaApplicantName = 'Unknown';
                        }

                        // 귀국일 계산 (출발일 + 패키지 기간)
                        $visaReturnDate = date('Y-m-d', strtotime($departureDate . ' + ' . ($packageDurationDays - 1) . ' days'));

                        // visaType 결정 (group 또는 individual, 기본값 individual)
                        $visaType = $traveler['visaType'] ?? 'individual';
                        if (!in_array($visaType, ['group', 'individual'])) {
                            $visaType = 'individual';
                        }

                        // visa_applications INSERT
                        $visaInsertSql = "
                            INSERT INTO visa_applications (
                                applicationNo, accountId, transactNo, bookingTravelerId,
                                applicantName, visaType, destinationCountry,
                                applicationDate, departureDate, returnDate, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'pending')
                        ";
                        $visaStmt = $conn->prepare($visaInsertSql);
                        if ($visaStmt) {
                            $visaStmt->bind_param('sisisssss',
                                $visaApplicationNo, $customerAccountId, $bookingId, $bookingTravelerId,
                                $visaApplicantName, $visaType, $packageDestination, $departureDate, $visaReturnDate
                            );
                            $visaStmt->execute();
                            $visaStmt->close();
                        }
                    } catch (Exception $visaEx) {
                        // 비자 신청 생성 실패해도 예약은 계속 진행 (로그만 기록)
                        error_log("Auto visa application creation failed: " . $visaEx->getMessage());
                    }
                }

                // 항공 옵션 저장 (booking_traveler_options)
                // flightOptions: 배열 [optionId1, optionId2, ...]
                // flightOptionPrices: 객체 {optionId: price, ...}
                if (!empty($traveler['flightOptions']) && is_array($traveler['flightOptions'])) {
                    foreach ($traveler['flightOptions'] as $optionId) {
                        if (empty($optionId)) continue;
                        $optionPrice = 0;
                        // flightOptionPrices에서 optionId로 가격 조회
                        if (!empty($traveler['flightOptionPrices']) && is_array($traveler['flightOptionPrices'])) {
                            if (isset($traveler['flightOptionPrices'][$optionId])) {
                                $optionPrice = (float)$traveler['flightOptionPrices'][$optionId];
                            }
                        }
                        try {
                            $optionInsertSql = "INSERT INTO booking_traveler_options (booking_id, traveler_index, option_id, price) VALUES (?, ?, ?, ?)";
                            $optionStmt = $conn->prepare($optionInsertSql);
                            if ($optionStmt) {
                                $optionStmt->bind_param('siid', $bookingId, $index, $optionId, $optionPrice);
                                $optionStmt->execute();
                                $optionStmt->close();
                            }
                        } catch (Exception $optEx) {
                            error_log("Failed to save traveler flight option: " . $optEx->getMessage());
                        }
                    }
                }

                $travelerStmt->close();
            }

            // infantsWithSeat 동기화: 좌석 점유 인팬트 수 계산 후 bookings 업데이트
            $infantsWithSeat = 0;
            foreach ($input['travelers'] as $t) {
                if (strtolower($t['type'] ?? '') === 'infant' && filter_var($t['infantSeat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $infantsWithSeat++;
                }
            }
            $iwsStmt = $conn->prepare("UPDATE bookings SET infantsWithSeat = ? WHERE bookingId = ?");
            if ($iwsStmt) {
                $iwsStmt->bind_param('is', $infantsWithSeat, $bookingId);
                $iwsStmt->execute();
                $iwsStmt->close();
            }

            $conn->commit();

            // Booking confirmation email is now sent when admin approves the booking (pending → approved)
            // See approveB2BBooking() in super-api.php

            send_success_response(['bookingId' => $bookingId], 'Reservation created successfully');

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        send_error_response('Failed to create reservation: ' . $e->getMessage());
    }
}

function updateReservationStatus($conn, $input) {
    try {
        // 에이전트는 예약 상태를 수정할 수 없음 (요구사항)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['agent_accountId'])) {
            send_error_response('Agent cannot update reservation status', 403);
        }

        $bookingId = $input['bookingId'] ?? '';
        $status = $input['status'] ?? '';
        
        if (empty($bookingId) || empty($status)) {
            send_error_response('Booking ID and status are required');
        }
        
        // 한글 상태를 영어로 변환
        $status = normalizeBookingStatus($status);
        
        $sql = "UPDATE bookings SET bookingStatus = ? WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $status, $bookingId);
        $stmt->execute();
        
        send_success_response([], 'Reservation status updated successfully');
        
    } catch (Exception $e) {
        send_error_response('Failed to update reservation status: ' . $e->getMessage());
    }
}

function confirmDeposit($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $amount = isset($input['amount']) ? floatval($input['amount']) : 0;
        $dueDate = $input['dueDate'] ?? null;

        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        if ($amount <= 0) {
            send_error_response('Amount must be greater than 0');
        }

        // 통일: downPayment* 컬럼 사용
        $updateFields = [
            'downPaymentAmount = ?',
            'downPaymentConfirmedAt = NOW()'
        ];
        $updateValues = [$amount];
        $updateTypes = 'd';

        if ($dueDate) {
            $updateFields[] = 'downPaymentDueDate = ?';
            $updateValues[] = $dueDate;
            $updateTypes .= 's';
        }

        $updateValues[] = $bookingId;
        $updateTypes .= 's';

        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($updateTypes, ...$updateValues);
        $stmt->execute();

        send_success_response([], 'Down payment confirmed successfully');

    } catch (Exception $e) {
        send_error_response('Failed to confirm down payment: ' . $e->getMessage());
    }
}

function removeDepositProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';

        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 통일: downPaymentFile 컬럼 사용
        $stmt = $conn->prepare("SELECT downPaymentFile FROM bookings WHERE bookingId = ?");
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Booking not found');
        }

        $booking = $result->fetch_assoc();
        $filePath = $booking['downPaymentFile'] ?? '';

        // 파일 삭제
        if (!empty($filePath)) {
            $filePathClean = str_replace('/smart-travel2/', '/', $filePath);
            $filePathClean = str_replace('smart-travel2/', '', $filePathClean);
            $filePathClean = preg_replace('#/uploads/uploads/#', '/uploads/', $filePathClean);
            $filePathClean = ltrim($filePathClean, '/');

            $filePath1 = __DIR__ . '/../../../' . $filePathClean;
            $filePath2 = __DIR__ . '/../../../../' . $filePathClean;

            if (file_exists($filePath1)) {
                unlink($filePath1);
            } elseif (file_exists($filePath2)) {
                unlink($filePath2);
            }
        }

        // DB에서 파일 경로 제거
        $updateStmt = $conn->prepare("UPDATE bookings SET downPaymentFile = NULL, downPaymentFileName = NULL WHERE bookingId = ?");
        $updateStmt->bind_param("s", $bookingId);
        $updateStmt->execute();

        send_success_response([], 'Down payment proof file removed successfully');

    } catch (Exception $e) {
        send_error_response('Failed to remove down payment proof file: ' . $e->getMessage());
    }
}

function confirmBalance($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $amount = isset($input['amount']) ? floatval($input['amount']) : 0;
        $dueDate = $input['dueDate'] ?? null;
        
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        
        if ($amount <= 0) {
            send_error_response('Amount must be greater than 0');
        }
        
        // bookings 테이블 컬럼 확인
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }
        
        // 업데이트할 필드들 구성
        $updateFields = [];
        $updateValues = [];
        $updateTypes = '';
        
        // balanceConfirmedAmount 컬럼 확인
        if (in_array('balanceconfirmedamount', $bookingsColumns)) {
            $updateFields[] = 'balanceConfirmedAmount = ?';
            $updateValues[] = $amount;
            $updateTypes .= 'd';
        }
        
        // balanceConfirmed 컬럼 확인
        if (in_array('balanceconfirmed', $bookingsColumns)) {
            $updateFields[] = 'balanceConfirmed = 1';
        }
        
        // balanceStatus 컬럼 확인
        if (in_array('balancestatus', $bookingsColumns)) {
            $updateFields[] = 'balanceStatus = ?';
            $updateValues[] = 'confirmed';
            $updateTypes .= 's';
        }
        
        // balanceDueDate 컬럼 확인
        if ($dueDate && in_array('balanceduedate', $bookingsColumns)) {
            $updateFields[] = 'balanceDueDate = ?';
            $updateValues[] = $dueDate;
            $updateTypes .= 's';
        }
        
        if (empty($updateFields)) {
            send_error_response('No balance-related columns found in bookings table');
        }
        
        $updateValues[] = $bookingId;
        $updateTypes .= 's';
        
        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($updateTypes, ...$updateValues);
        $stmt->execute();
        
        send_success_response([], 'Balance confirmed successfully');
        
    } catch (Exception $e) {
        send_error_response('Failed to confirm balance: ' . $e->getMessage());
    }
}

// ========== 고객 관련 함수들 ==========

function getCustomers($conn, $input) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        // 테이블 존재 확인
        $tableCheck = $conn->query("SHOW TABLES LIKE 'client'");
        if ($tableCheck->num_rows === 0) {
            throw new Exception('client table does not exist');
        }

        // client 컬럼 확인 (필터/선택 필드 구성에 사용)
        $columnsCheck = $conn->query("SHOW COLUMNS FROM client");
        $clientColumns = [];
        while ($col = $columnsCheck->fetch_assoc()) {
            $clientColumns[] = strtolower($col['Field']);
        }

        // 에이전트 소속(회사) 범위 확인
        $scope = function_exists('get_agent_scope') ? get_agent_scope($conn, $agentAccountId) : ['companyId' => null];
        $companyId = isset($scope['companyId']) ? $scope['companyId'] : null;
        
        $page = isset($input['page']) ? (int)$input['page'] : 1;
        $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
        // SMT 수정: UI 요구(10건씩) 등 호출값을 존중하되 상한만 둔다
        if ($limit <= 0) $limit = 20;
        if ($limit > 100) $limit = 100;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        $types = '';

        // ===== 에이전트 고객 필터링: client.agentId 기준 =====
        // 해당 에이전트가 등록한 고객만 조회 (client.agentId = 로그인한 에이전트의 accountId)
        if (in_array('agentid', $clientColumns, true)) {
            $where[] = "c.agentId = ?";
            $params[] = $agentAccountId;
            $types .= 'i';
        }
        // ===== 에이전트 고객 필터링 완료 =====
        
        // 검색: "고객명 기준" (이메일/연락처 제외)
        if (!empty($input['search'])) {
            $where[] = "(TRIM(CONCAT(COALESCE(c.fName,''),' ',COALESCE(c.lName,''))) LIKE ? OR TRIM(CONCAT(COALESCE(c.lName,''),' ',COALESCE(c.fName,''))) LIKE ? OR c.fName LIKE ? OR c.lName LIKE ?)";
            $searchTerm = '%' . $input['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // COUNT 쿼리 - JOIN을 포함하여 정확한 카운트
        $countSql = "SELECT COUNT(*) as total FROM client c LEFT JOIN accounts a ON c.accountId = a.accountId $whereClause";
        
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            if (!$countStmt) {
                throw new Exception('Failed to prepare count query: ' . $conn->error . ' | SQL: ' . $countSql);
            }
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            if (!$countStmt->execute()) {
                throw new Exception('Failed to execute count query: ' . $countStmt->error);
            }
            $totalResult = $countStmt->get_result();
        } else {
            $totalResult = $conn->query($countSql);
            if (!$totalResult) {
                throw new Exception('Failed to execute count query: ' . $conn->error . ' | SQL: ' . $countSql);
            }
        }
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow ? (int)$totalRow['total'] : 0;
        
        // 옵셔널 컬럼들을 선택적으로 포함
        $genderCol = in_array('gender', $clientColumns) ? 'c.gender' : "NULL as gender";
        $dobCol = in_array('dateofbirth', $clientColumns) ? 'c.dateOfBirth' : "NULL as dateOfBirth";
        $nationalityCol = in_array('nationality', $clientColumns) ? 'c.nationality' : "NULL as nationality";
        $passportNumCol = in_array('passportnumber', $clientColumns) ? 'c.passportNumber' : "NULL as passportNumber";
        $passportExpCol = in_array('passportexpiry', $clientColumns) ? 'c.passportExpiry' : "NULL as passportExpiry";
        $emailCol = in_array('emailaddress', $clientColumns) ? 'COALESCE(a.emailAddress, c.emailAddress)' : 'a.emailAddress';
        $hasCreatedAt = in_array('createdat', $clientColumns);
        $createdAtCol = $hasCreatedAt ? 'COALESCE(a.createdAt, c.createdAt)' : 'a.createdAt';
        
        $sql = "
            SELECT 
                c.accountId,
                c.fName,
                c.lName,
                $genderCol,
                $dobCol,
                c.contactNo,
                $emailCol as emailAddress,
                $nationalityCol,
                $passportNumCol,
                $passportExpCol,
                $createdAtCol as createdAt
            FROM client c
            LEFT JOIN accounts a ON c.accountId = a.accountId
            $whereClause
            ORDER BY $createdAtCol DESC
            LIMIT ? OFFSET ?
        ";
        
        // LIMIT과 OFFSET 파라미터 추가
        $limitParams = [$limit, $offset];
        $limitTypes = 'ii';
        
        // 기존 파라미터와 병합
        if (!empty($params)) {
            $allParams = array_merge($params, $limitParams);
            $allTypes = $types . $limitTypes;
        } else {
            $allParams = $limitParams;
            $allTypes = $limitTypes;
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        
        mysqli_bind_params_by_ref($stmt, $allTypes, $allParams);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $customers = [];
        $rowNum = $total - $offset;
        while ($row = $result->fetch_assoc()) {
            $fName = $row['fName'] ?? '';
            $lName = $row['lName'] ?? '';
            $customerName = trim($fName . ' ' . $lName);
            
            // 상태 확인 (accounts 테이블의 status 또는 accountStatus)
            $statusCheck = $conn->query("SHOW COLUMNS FROM accounts LIKE 'status'");
            $hasStatus = $statusCheck && $statusCheck->num_rows > 0;
            $statusCheck2 = $conn->query("SHOW COLUMNS FROM accounts LIKE 'accountStatus'");
            $hasAccountStatus = $statusCheck2 && $statusCheck2->num_rows > 0;
            
            $status = 'active';
            if ($hasStatus || $hasAccountStatus) {
                $statusCol = $hasStatus ? 'a.status' : 'a.accountStatus';
                $statusSql = "SELECT $statusCol as status FROM accounts a WHERE a.accountId = ?";
                $statusStmt = $conn->prepare($statusSql);
                $statusStmt->bind_param('i', $row['accountId']);
                $statusStmt->execute();
                $statusResult = $statusStmt->get_result();
                if ($statusResult->num_rows > 0) {
                    $statusRow = $statusResult->fetch_assoc();
                    $status = $statusRow['status'] ?? 'active';
                }
                $statusStmt->close();
            }
            
            $customers[] = [
                'rowNum' => $rowNum--,
                'accountId' => $row['accountId'],
                'customerName' => $customerName,
                'email' => $row['emailAddress'] ?? '',
                'phone' => $row['contactNo'] ?? '',
                'fName' => $fName,
                'lName' => $lName,
                'gender' => $row['gender'] ?? '',
                'dateOfBirth' => $row['dateOfBirth'] ?? null,
                'contactNo' => $row['contactNo'] ?? '',
                'emailAddress' => $row['emailAddress'] ?? '',
                'nationality' => $row['nationality'] ?? '',
                'passportNumber' => $row['passportNumber'] ?? '',
                'passportExpiry' => $row['passportExpiry'] ?? null,
                'createdAt' => $row['createdAt'] ?? '',
                'status' => $status
            ];
        }
        
        send_success_response([
            'customers' => $customers,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        error_log('getCustomers error: ' . $e->getMessage());
        error_log('getCustomers trace: ' . $e->getTraceAsString());
        send_error_response('Failed to get customers: ' . $e->getMessage() . ' | SQL Error: ' . ($conn->error ?? 'N/A'));
    }
}

function getCustomerDetail($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? '';
        
        if (empty($accountId)) {
            send_error_response('Account ID is required');
        }

        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;
        
        // client 테이블 컬럼 확인
        $clientColumnCheck = $conn->query("SHOW COLUMNS FROM client");
        $clientColumns = [];
        while ($col = $clientColumnCheck->fetch_assoc()) {
            $clientColumns[] = strtolower($col['Field']);
        }

        // 에이전트 고객 접근 권한 검증: client.agentId = 로그인한 에이전트의 accountId
        if (in_array('agentid', $clientColumns, true)) {
            $chk = $conn->prepare("
                SELECT 1
                FROM client c
                WHERE c.accountId = ?
                  AND c.agentId = ?
                LIMIT 1
            ");
            if ($chk) {
                $aid = (int)$accountId;
                $chk->bind_param('ii', $aid, $agentAccountId);
                $chk->execute();
                $ok = $chk->get_result()->num_rows > 0;
                $chk->close();
                if (!$ok) {
                    send_error_response('Access denied', 403);
                }
            }
        }
        
        // passportIssueDate 컬럼이 없으면 생성
        if (!in_array('passportissuedate', $clientColumns)) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN passportIssueDate DATE NULL");
                $clientColumns[] = 'passportissuedate';
                error_log("Created passportIssueDate column in client table");
            } catch (Exception $e) {
                error_log("Failed to create passportIssueDate column: " . $e->getMessage());
            }
        }
        
        // 고객 정보 조회 (더 많은 정보 포함)
        // agreementContent 컬럼 확인
        // IMPORTANT: 여기 값들은 SELECT에서 다시 "AS xxx"로 alias를 붙이므로,
        // 표현식에는 alias를 포함하지 않습니다. (예: "NULL AS agreementContent" 금지)
        $agreementCol = in_array('agreementcontent', $clientColumns) ? 'c.agreementContent' :
                       (in_array('agreement', $clientColumns) ? 'c.agreement' : 'NULL');
        $countryOfResidenceCol = in_array('countryofresidence', $clientColumns) ? 'c.countryOfResidence' :
                                (in_array('residencecountry', $clientColumns) ? 'c.residenceCountry' : 'NULL');
        $visaInfoCol = in_array('visainformation', $clientColumns) ? 'c.visaInformation' :
                      (in_array('visainfo', $clientColumns) ? 'c.visaInfo' : 'NULL');
        $remarksCol = in_array('remarks', $clientColumns) ? 'c.remarks' :
                     (in_array('note', $clientColumns) ? 'c.note' : 'NULL');
        
        // NOTE: 일부 환경에서 accounts는 있으나 client 레코드가 없는 경우가 있어
        // accounts 기준으로 LEFT JOIN하여 "초기화(빈 화면)"를 방지한다.
        $sql = "
            SELECT 
                c.*,
                $agreementCol as agreementContent,
                $countryOfResidenceCol as countryOfResidence,
                $visaInfoCol as visaInformation,
                $remarksCol as remarks,
                a.emailAddress as accountEmail,
                a.username,
                a.accountType,
                a.createdAt as accountCreatedAt,
                '' as companyName
            FROM accounts a
            LEFT JOIN client c ON a.accountId = c.accountId
            WHERE a.accountId = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 고객이 없어도 빈 객체 반환 (에러 처리하지 않음)
        $customer = [];
        if ($result->num_rows > 0) {
            $customer = $result->fetch_assoc();

            // branchName은 companyName으로 대체
            $customer['branchName'] = trim((string)($customer['companyName'] ?? ''));
            
            // passportIssueDate 필드명 정규화 (여러 가능한 필드명 확인)
            if (empty($customer['passportIssueDate']) && isset($customer['passportIssuedDate'])) {
                $customer['passportIssueDate'] = $customer['passportIssuedDate'];
            }
            if (empty($customer['passportIssueDate']) && isset($customer['passportIssue'])) {
                $customer['passportIssueDate'] = $customer['passportIssue'];
            }
        }
        
        // 디버깅: profileImage 확인
        error_log("getCustomerDetail - accountId: $accountId, profileImage: " . ($customer['profileImage'] ?? 'NULL'));
        
        // profileImage 경로를 전체 URL로 변환 (있는 경우)
        if (!empty($customer['profileImage'])) {
            // 상대 경로인 경우 전체 URL로 변환
            if (!preg_match('/^(https?:\/\/|data:)/', $customer['profileImage'])) {
                // smart-travel2 제거 및 경로 정규화
                $profileImage = (string)$customer['profileImage'];
                // backslash → slash
                $profileImage = str_replace('\\', '/', $profileImage);
                
                // smart-travel2 제거
                $profileImage = str_replace('/smart-travel2/', '/', $profileImage);
                $profileImage = str_replace('smart-travel2/', '', $profileImage);
                
                // uploads/uploads 중복 제거
                $profileImage = preg_replace('#/uploads/uploads/#', '/uploads/', $profileImage);

                // 레거시 케이스:
                // - 파일명만 저장된 경우(passport_*.jpg 등): uploads/passports/ 로 간주
                if ($profileImage !== '' && strpos($profileImage, '/') === false) {
                    $profileImage = 'uploads/passports/' . $profileImage;
                }
                // - passports/xxx 형태: uploads/passports/xxx 로 간주
                if (strpos($profileImage, 'passports/') === 0) {
                    $profileImage = 'uploads/' . $profileImage;
                }
                
                // /로 시작하지 않으면 추가
                if (strpos($profileImage, '/') !== 0) {
                    $profileImage = '/' . $profileImage;
                }
                
                $customer['profileImage'] = $profileImage;
            }
        }

        // ===== SMT 수정: Traveler Information 필드 정규화 (환경별 컬럼명 편차 흡수) =====
        // 프론트(admin/js/agent-customer-detail.js)가 기대하는 키:
        // travelerFirstName/travelerLastName/dateOfBirth/passportNumber/passportIssueDate/passportExpiry/profileImage/countryCode/contactNo/...
        $pick = function (array $arr, array $keys) {
            foreach ($keys as $k) {
                if (!is_string($k) || $k === '') continue;
                if (!array_key_exists($k, $arr)) continue;
                $v = $arr[$k];
                if ($v === null) continue;
                $s = trim((string)$v);
                if ($s !== '') return $v;
            }
            return null;
        };
        $normalizeDate = function ($v) {
            if ($v === null) return null;
            $s = trim((string)$v);
            if ($s === '') return null;
            // YYYYMMDD
            if (preg_match('/^\d{8}$/', $s)) {
                return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
            }
            // YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
            // DATETIME
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+/', $s)) return substr($s, 0, 10);
            // fallback parse
            try {
                $dt = new DateTime($s);
                return $dt->format('Y-m-d');
            } catch (Throwable $e) {
                return null;
            }
        };

        // traveler name: travelerFirstName/LastName 우선, 없으면 fName/lName(구 스키마) 사용
        $tF = $pick($customer, ['travelerFirstName', 'traveler_first_name', 'travelerFName', 'firstName', 'first_name', 'givenName', 'given_name', 'fName', 'fname']);
        $tL = $pick($customer, ['travelerLastName', 'traveler_last_name', 'travelerLName', 'lastName', 'last_name', 'familyName', 'family_name', 'lName', 'lname']);
        if (empty($customer['travelerFirstName']) && $tF !== null) $customer['travelerFirstName'] = (string)$tF;
        if (empty($customer['travelerLastName']) && $tL !== null) $customer['travelerLastName'] = (string)$tL;

        // date of birth
        $dob = $pick($customer, ['dateOfBirth', 'birthDate', 'birth_date', 'dob', 'travelerBirth', 'traveler_birth']);
        if (empty($customer['dateOfBirth']) && $dob !== null) $customer['dateOfBirth'] = $normalizeDate($dob);

        // passport number
        $ppNo = $pick($customer, ['passportNumber', 'passportNo', 'passport_no', 'passport']);
        if (empty($customer['passportNumber']) && $ppNo !== null) $customer['passportNumber'] = (string)$ppNo;

        // passport issue / expiry
        $ppIssue = $pick($customer, ['passportIssueDate', 'passportIssuedDate', 'passportIssue', 'passport_issue', 'passportIssueDt']);
        if (empty($customer['passportIssueDate']) && $ppIssue !== null) $customer['passportIssueDate'] = $normalizeDate($ppIssue);

        $ppExp = $pick($customer, ['passportExpiry', 'passportExpiryDate', 'passportExp', 'passportExpire', 'passportExpiredDate', 'passport_expire', 'passport_expiry']);
        // 프론트는 passportExpiry 키를 우선 읽음
        if (empty($customer['passportExpiry']) && $ppExp !== null) $customer['passportExpiry'] = $normalizeDate($ppExp);

        // country code / contact
        $cc = $pick($customer, ['countryCode', 'country_code', 'phoneCountryCode']);
        if (empty($customer['countryCode']) && $cc !== null) $customer['countryCode'] = (string)$cc;
        $cn = $pick($customer, ['contactNo', 'contact_no', 'phone', 'mobile']);
        if (empty($customer['contactNo']) && $cn !== null) $customer['contactNo'] = (string)$cn;

        // profile image fallback (여권 사진)
        if (empty($customer['profileImage'])) {
            $pi = $pick($customer, ['profileImage', 'passportPhoto', 'passportImage', 'passport_photo', 'passport_photo_path']);
            if ($pi !== null) {
                $p = str_replace('\\', '/', (string)$pi);
                $p = str_replace('/smart-travel2/', '/', $p);
                $p = str_replace('smart-travel2/', '', $p);
                $p = preg_replace('#/uploads/uploads/#', '/uploads/', $p);
                if ($p !== '' && strpos($p, '/') === false) $p = 'uploads/passports/' . $p;
                if (strpos($p, 'passports/') === 0) $p = 'uploads/' . $p;
                if ($p !== '' && strpos($p, '/') !== 0 && !preg_match('/^(https?:\/\/|data:)/', $p)) $p = '/' . $p;
                $customer['profileImage'] = $p;
            }
        }
        // ===== SMT 수정 완료 =====
        
        // 예약 내역 조회 (더 자세한 정보)
        // returnDate 컬럼 확인
        $bookingColumnCheck = $conn->query("SHOW COLUMNS FROM bookings");
        $bookingColumns = [];
        while ($col = $bookingColumnCheck->fetch_assoc()) {
            $bookingColumns[] = strtolower($col['Field']);
        }
        // SELECT에서 "AS returnDate"를 다시 붙이므로 alias 없는 표현식만 사용
        $returnDateCol = in_array('returndate', $bookingColumns) ? 'b.returnDate' :
                        (in_array('arrivaldate', $bookingColumns) ? 'b.arrivalDate' : 'NULL');
        
        // SMT 수정(id 75):
        // - 에이전트가 생성한 B2B 예약은 bookings.accountId(소유자)=agent 이고,
        //   실제 고객은 bookings.customerAccountId(환경별) 또는 selectedOptions.customerInfo.accountId 에 저장될 수 있음.
        // - 고객 상세의 "예약 내역"은 고객 기준으로 조회해야 한다.
        $customerAccountIdCol = null;
        if (in_array('customeraccountid', $bookingColumns, true)) $customerAccountIdCol = 'customerAccountId';
        else if (in_array('customer_account_id', $bookingColumns, true)) $customerAccountIdCol = 'customer_account_id';
        else if (in_array('customerid', $bookingColumns, true)) $customerAccountIdCol = 'customerId';
        else if (in_array('userid', $bookingColumns, true)) $customerAccountIdCol = 'userId';

        $whereBooking = '';
        $bt = '';
        $bp = [];
        if (!empty($customerAccountIdCol)) {
            $whereBooking = "WHERE (b.`{$customerAccountIdCol}` = ? OR b.accountId = ?)";
            $bt = 'ii';
            $bp = [(int)$accountId, (int)$accountId];
        } else {
            // fallback: selectedOptions JSON 매칭
            $whereBooking = "WHERE (JSON_EXTRACT(b.selectedOptions, '$.customerInfo.accountId') = ? OR b.accountId = ?)";
            $bt = 'ii';
            $bp = [(int)$accountId, (int)$accountId];
        }

        // 예약 내역 페이지네이션 파라미터
        $bookingsPage = isset($input['bookingsPage']) ? max(1, (int)$input['bookingsPage']) : 1;
        $bookingsLimit = isset($input['bookingsLimit']) ? max(1, (int)$input['bookingsLimit']) : 10;
        $bookingsOffset = ($bookingsPage - 1) * $bookingsLimit;
        
        // 예약 내역 총 건수 조회
        $bookingsCountSql = "
            SELECT COUNT(*) as total
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            $whereBooking
        ";
        $bookingsCountStmt = $conn->prepare($bookingsCountSql);
        $bookingsCountStmt->bind_param($bt, ...$bp);
        $bookingsCountStmt->execute();
        $bookingsCountResult = $bookingsCountStmt->get_result();
        $bookingsTotal = $bookingsCountResult->fetch_assoc()['total'] ?? 0;
        $bookingsCountStmt->close();
        
        $bookingsSql = "
            SELECT 
                b.bookingId,
                b.packageId,
                p.packageName,
                b.departureDate,
                $returnDateCol as returnDate,
                b.bookingStatus,
                b.totalAmount,
                (b.adults + b.children + b.infants) as numPeople,
                b.createdAt as bookingDate
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            $whereBooking
            ORDER BY b.createdAt DESC
            LIMIT ? OFFSET ?
        ";
        $bookingsStmt = $conn->prepare($bookingsSql);
        $bookingsStmt->bind_param($bt . 'ii', ...array_merge($bp, [$bookingsLimit, $bookingsOffset]));
        $bookingsStmt->execute();
        $bookingsResult = $bookingsStmt->get_result();
        
        $bookings = [];
        while ($booking = $bookingsResult->fetch_assoc()) {
            $bookings[] = $booking;
        }
        $bookingsStmt->close();
        
        // 문의 내역 페이지네이션 파라미터
        $inquiriesPage = isset($input['inquiriesPage']) ? max(1, (int)$input['inquiriesPage']) : 1;
        $inquiriesLimit = isset($input['inquiriesLimit']) ? max(1, (int)$input['inquiriesLimit']) : 10;
        $inquiriesOffset = ($inquiriesPage - 1) * $inquiriesLimit;
        
        // 문의 내역 총 건수 조회
        $inquiriesCountSql = "SELECT COUNT(*) as total FROM inquiries i WHERE i.accountId = ?";
        $inquiriesCountStmt = $conn->prepare($inquiriesCountSql);
        $inquiriesCountStmt->bind_param("i", $accountId);
        $inquiriesCountStmt->execute();
        $inquiriesCountResult = $inquiriesCountStmt->get_result();
        $inquiriesTotal = $inquiriesCountResult->fetch_assoc()['total'] ?? 0;
        $inquiriesCountStmt->close();
        
        // 문의 내역 조회
        // 컬럼명 확인 (category 또는 inquiryType, subject 또는 inquiryTitle)
        $columnCheck = $conn->query("SHOW COLUMNS FROM inquiries");
        $inquiryColumns = [];
        while ($col = $columnCheck->fetch_assoc()) {
            $inquiryColumns[] = strtolower($col['Field']);
        }
        
        $categoryColumn = in_array('category', $inquiryColumns) ? 'category' : 
                         (in_array('inquirytype', $inquiryColumns) ? 'inquiryType' : 'category');
        $titleColumn = in_array('subject', $inquiryColumns) ? 'subject' : 
                      (in_array('inquirytitle', $inquiryColumns) ? 'inquiryTitle' : 'subject');
        
        // 처리자 컬럼 확인
        $processingPersonCol = in_array('processedby', $inquiryColumns) ? 'i.processedBy' : 
                              (in_array('processingperson', $inquiryColumns) ? 'i.processingPerson' : 
                              (in_array('processor', $inquiryColumns) ? 'i.processor' : 'NULL'));
        
        $inquiriesSql = "
            SELECT 
                i.inquiryId,
                i.$categoryColumn as inquiryType,
                i.$titleColumn as inquiryTitle,
                i.status,
                i.createdAt,
                $processingPersonCol as processingPerson,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId) THEN '답변완료'
                    ELSE '미답변'
                END as replyStatus
            FROM inquiries i
            WHERE i.accountId = ?
            ORDER BY i.createdAt DESC
            LIMIT ? OFFSET ?
        ";
        $inquiriesStmt = $conn->prepare($inquiriesSql);
        $inquiriesStmt->bind_param("iii", $accountId, $inquiriesLimit, $inquiriesOffset);
        $inquiriesStmt->execute();
        $inquiriesResult = $inquiriesStmt->get_result();
        
        $inquiries = [];
        while ($inquiry = $inquiriesResult->fetch_assoc()) {
            $inquiries[] = $inquiry;
        }
        $inquiriesStmt->close();
        
        send_success_response([
            'customer' => $customer,
            'bookings' => $bookings,
            'bookingsPagination' => [
                'total' => (int)$bookingsTotal,
                'page' => $bookingsPage,
                'limit' => $bookingsLimit,
                'totalPages' => ceil($bookingsTotal / $bookingsLimit)
            ],
            'inquiries' => $inquiries,
            'inquiriesPagination' => [
                'total' => (int)$inquiriesTotal,
                'page' => $inquiriesPage,
                'limit' => $inquiriesLimit,
                'totalPages' => ceil($inquiriesTotal / $inquiriesLimit)
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get customer detail: ' . $e->getMessage());
    }
}

function createCustomerRecord($conn, $input, $files = null) {
    $files = $files ?? $_FILES;
    try {
        // 세션 확인 (agent 로그인 확인) - companyId 자동 주입/권한 범위용
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;

        // accounts 테이블 스키마 편차 대응 (email/emailAddress, accountStatus/status)
        $accountsColMap = [];
        $accountsColsLower = [];
        try {
            $accountColumnResult0 = $conn->query("SHOW COLUMNS FROM accounts");
            if ($accountColumnResult0) {
                while ($row0 = $accountColumnResult0->fetch_assoc()) {
                    $f = (string)($row0['Field'] ?? '');
                    if ($f === '') continue;
                    $k = strtolower($f);
                    $accountsColMap[$k] = $f; // 실제 컬럼명(대소문자 포함)
                    $accountsColsLower[] = $k;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        $emailCol = $accountsColMap['emailaddress'] ?? ($accountsColMap['email'] ?? 'emailAddress');

        // 필수 필드 검증
        // lastName은 환경/기획에 따라 선택값일 수 있어 필수에서 제외
        $requiredFields = ['firstName', 'email', 'phone'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }
        $input['lastName'] = $input['lastName'] ?? '';

        // 공통 companyId resolve (입력값 우선, 없으면 agent 세션 scope)
        $resolvedCompanyId = !empty($input['companyId']) ? (int)$input['companyId'] : null;
        if (empty($resolvedCompanyId) && !empty($agentAccountId) && function_exists('get_agent_scope')) {
            $scope = get_agent_scope($conn, (int)$agentAccountId);
            if (!empty($scope['companyId'])) {
                $resolvedCompanyId = (int)$scope['companyId'];
            }
        }
        
        // 이메일 중복 확인
        $emailCheckSql = "SELECT accountId FROM accounts WHERE `{$emailCol}` = ?";
        $emailCheckStmt = $conn->prepare($emailCheckSql);
        $emailCheckStmt->bind_param("s", $input['email']);
        $emailCheckStmt->execute();
        $emailResult = $emailCheckStmt->get_result();
        
        if ($emailResult->num_rows > 0) {
            throw new Exception('Email already exists');
        }
        
        // 여권 사진 업로드 처리
        $passportPhotoPath = null;
        error_log("createCustomer - Checking for passportPhoto file upload");
        error_log("createCustomer - _FILES: " . print_r($files, true));
        
        if (isset($files['passportPhoto']) && $files['passportPhoto']['error'] === UPLOAD_ERR_OK) {
            error_log("createCustomer - passportPhoto file found, error code: " . $files['passportPhoto']['error']);
            // 실제 저장 경로: /var/www/html/uploads/passports/
            // __DIR__ = /var/www/html/admin/backend/api/
            // ../../../ = /var/www/html/
            $uploadDir = __DIR__ . '/../../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                error_log("createCustomer - Created upload directory: " . $uploadDir);
            }
            error_log("createCustomer - Upload directory resolved: " . realpath($uploadDir));
            
            $fileExtension = pathinfo($files['passportPhoto']['name'], PATHINFO_EXTENSION);
            $fileName = 'passport_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            error_log("createCustomer - Attempting to move file to: " . $uploadPath);
            if (move_uploaded_file($files['passportPhoto']['tmp_name'], $uploadPath)) {
                // 웹에서 접근 가능한 경로: /uploads/passports/
                $passportPhotoPath = 'uploads/passports/' . $fileName;
                error_log("createCustomer - File uploaded successfully: " . $passportPhotoPath);
            } else {
                error_log("createCustomer - Failed to move uploaded file");
            }
        } else {
            if (isset($files['passportPhoto'])) {
                error_log("createCustomer - passportPhoto file error code: " . $files['passportPhoto']['error']);
            } else {
                error_log("createCustomer - passportPhoto not found in _FILES");
            }
        }
        
        $conn->begin_transaction();
        
        try {
            // accounts 테이블에 먼저 생성 (password 또는 passwordHash 컬럼 확인)
            // - createNewCustomer()와 동일하게 스키마 편차를 흡수
            $passwordColumn = isset($accountsColMap['password']) ? $accountsColMap['password'] : (isset($accountsColMap['passwordhash']) ? $accountsColMap['passwordhash'] : 'password');
            $statusColumn = $accountsColMap['accountstatus'] ?? ($accountsColMap['status'] ?? null);
            $hasUsername = isset($accountsColMap['username']);
            
            // username 필드가 있으면 이메일을 username으로 사용하거나 firstName + lastName 조합 사용
            $accountFields = [$emailCol, $passwordColumn, 'accountType'];
            // 고객 등록(에이전트가 등록한 사용자)도 "고객 계정"이므로 accountType은 guest로 유지한다.
            // (accountType=agent로 저장하면 권한이 섞여서 관리자/에이전트 기능이 열릴 수 있음)
            $accountValues = [$input['email'], password_hash($input['password'] ?? 'temp123', PASSWORD_DEFAULT), 'guest'];
            $accountTypes = 'sss';
            
            // 에이전트가 등록한 고객도 로그인 가능해야 하므로 accountStatus/status를 명시적으로 active로 세팅
            // (기본값이 inactive/pending인 환경에서 로그인 불가 이슈 방지)
            if (!empty($statusColumn)) {
                $accountFields[] = $statusColumn;
                $accountValues[] = 'active';
                $accountTypes .= 's';
            }

            if ($hasUsername) {
                // username이 필수이면 이메일을 username으로 사용 (또는 firstName + lastName 조합)
                $username = $input['email']; // 이메일을 username으로 사용
                // 또는: $username = strtolower($input['firstName'] . $input['lastName']);
                $accountFields[] = 'username';
                $accountValues[] = $username;
                $accountTypes .= 's';
            }
            
            // createdAt은 DEFAULT CURRENT_TIMESTAMP가 설정되어 있으면 자동으로 값이 들어가므로 명시적으로 추가하지 않음
            // 만약 명시적으로 추가해야 한다면 아래 주석을 해제
            /*
            if (in_array('createdAt', $accountColumns)) {
                $accountFields[] = 'createdAt';
                $accountValues[] = date('Y-m-d H:i:s');
                $accountTypes .= 's';
            }
            */
            
            $accountPlaceholders = str_repeat('?,', count($accountFields) - 1) . '?';
            $accountSql = "INSERT INTO accounts (" . implode(', ', $accountFields) . ") VALUES ($accountPlaceholders)";
            
            $accountStmt = $conn->prepare($accountSql);
            $accountStmt->bind_param($accountTypes, ...$accountValues);
            $accountStmt->execute();
            $accountId = $conn->insert_id;
            
            // client 테이블 컬럼 확인 (countryCode, memo 등이 있는지 확인)
            $clientColumns = [];
            $columnResult = $conn->query("SHOW COLUMNS FROM client");
            while ($row = $columnResult->fetch_assoc()) {
                $clientColumns[] = strtolower($row['Field']); // 대소문자 구분 없이 저장
            }
            
            // clientId 생성 (필수 필드 - DB 스키마에 UNIQUE NOT NULL로 정의됨)
            $clientId = null;
            if (in_array('clientid', $clientColumns)) {
                // clientId 형식: CLI + 6자리 accountId (앞에 0 패딩)
                $clientId = 'CLI' . str_pad($accountId, 6, '0', STR_PAD_LEFT);
            }
            
            // client 테이블에 생성
            $clientFields = [];
            $clientValues = [];
            $clientTypes = '';
            
            // 필수 필드: clientId (컬럼이 존재하면 항상 추가)
            if (in_array('clientid', $clientColumns)) {
                if (empty($clientId)) {
                    throw new Exception('Failed to generate clientId');
                }
                $clientFields[] = 'clientId';
                $clientValues[] = $clientId;
                $clientTypes .= 's';
            }
            
            // 필수 필드: accountId
            $clientFields[] = 'accountId';
            $clientValues[] = $accountId;
            $clientTypes .= 'i';
            
            // 필수 필드: fName (고객명 - Traveler와 별도)
            $clientFields[] = 'fName';
            $clientValues[] = (string)($input['firstName'] ?? '');
            $clientTypes .= 's';
            
            // 필수 필드: lName (고객명 - Traveler와 별도)
            $clientFields[] = 'lName';
            $clientValues[] = (string)($input['lastName'] ?? '');
            $clientTypes .= 's';
            
            // 필수 필드: contactNo
            $clientFields[] = 'contactNo';
            $clientValues[] = $input['phone'];
            $clientTypes .= 's';

            // travelerFirstName / travelerLastName 컬럼 처리 (고객명과 여행자명 분리 저장)
            if (!in_array('travelerfirstname', $clientColumns, true) && (isset($input['travelerFirstName']) || isset($input['travelerLastName']))) {
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN travelerFirstName VARCHAR(100) NULL");
                    $clientColumns[] = 'travelerfirstname';
                } catch (Throwable $e) { /* ignore */ }
            }
            if (!in_array('travelerlastname', $clientColumns, true) && (isset($input['travelerFirstName']) || isset($input['travelerLastName']))) {
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN travelerLastName VARCHAR(100) NULL");
                    $clientColumns[] = 'travelerlastname';
                } catch (Throwable $e) { /* ignore */ }
            }
            if (isset($input['travelerFirstName']) && in_array('travelerfirstname', $clientColumns, true)) {
                $clientFields[] = 'travelerFirstName';
                $clientValues[] = (string)$input['travelerFirstName'];
                $clientTypes .= 's';
            }
            if (isset($input['travelerLastName']) && in_array('travelerlastname', $clientColumns, true)) {
                $clientFields[] = 'travelerLastName';
                $clientValues[] = (string)$input['travelerLastName'];
                $clientTypes .= 's';
            }
            
            // companyId 처리
            if (in_array('companyid', $clientColumns)) {
                if (!empty($resolvedCompanyId)) {
                    $clientFields[] = 'companyId';
                    $clientValues[] = $resolvedCompanyId;
                    $clientTypes .= 'i';
                }
            }
            
            // emailAddress 컬럼이 있으면 추가
            if (in_array('emailaddress', $clientColumns)) {
                $clientFields[] = 'emailAddress';
                $clientValues[] = $input['email'];
                $clientTypes .= 's';
            }
            
            // countryCode 컬럼이 있으면 추가 (기본값 처리)
            if (in_array('countrycode', $clientColumns)) {
                $clientFields[] = 'countryCode';
                $clientValues[] = $input['countryCode'] ?? '+82';
                $clientTypes .= 's';
            }
            
            // clientType 컬럼이 있으면 추가 (기본값 'Retailer')
            if (in_array('clienttype', $clientColumns)) {
                $clientFields[] = 'clientType';
                // 에이전트가 등록한 회원은 B2B 고객으로 분류
                $clientValues[] = 'Wholeseller';
                $clientTypes .= 's';
            }
            
            // clientRole 컬럼이 있으면 추가 (기본값 'Sub-Agent')
            if (in_array('clientrole', $clientColumns)) {
                $clientFields[] = 'clientRole';
                $clientValues[] = 'Sub-Agent';
                $clientTypes .= 's';
            }

            // agentId 컬럼이 있으면 등록한 에이전트 accountId 저장
            if (in_array('agentid', $clientColumns) && !empty($agentAccountId)) {
                $clientFields[] = 'agentId';
                $clientValues[] = (int)$agentAccountId;
                $clientTypes .= 'i';
            }

            // dateOfBirth 컬럼이 있고 여행자 생년월일이 있으면 추가
            if (in_array('dateofbirth', $clientColumns) && !empty($input['travelerBirth'])) {
                $clientFields[] = 'dateOfBirth';
                $clientValues[] = $input['travelerBirth'];
                $clientTypes .= 's';
            }
            
            // gender 컬럼이 있고 여행자 성별이 있으면 추가
            if (in_array('gender', $clientColumns) && !empty($input['travelerGender'])) {
                $clientFields[] = 'gender';
                $clientValues[] = $input['travelerGender'];
                $clientTypes .= 's';
            }
            
            // nationality 컬럼이 있고 여행자 출신국가가 있으면 추가
            if (in_array('nationality', $clientColumns) && !empty($input['travelerNationality'])) {
                $clientFields[] = 'nationality';
                $clientValues[] = $input['travelerNationality'];
                $clientTypes .= 's';
            }
            
            // passportNumber 컬럼이 있고 여행자 여권번호가 있으면 추가
            if (in_array('passportnumber', $clientColumns) && !empty($input['travelerPassportNo'])) {
                $clientFields[] = 'passportNumber';
                $clientValues[] = $input['travelerPassportNo'];
                $clientTypes .= 's';
            }
            
            // passportIssueDate 컬럼이 없으면 생성
            if (!in_array('passportissuedate', $clientColumns)) {
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN passportIssueDate DATE NULL");
                    $clientColumns[] = 'passportissuedate';
                    error_log("Created passportIssueDate column in client table");
                } catch (Exception $e) {
                    error_log("Failed to create passportIssueDate column: " . $e->getMessage());
                }
            }
            // passportIssueDate 컬럼이 있고 여행자 여권 발행일이 있으면 추가
            if (in_array('passportissuedate', $clientColumns) && isset($input['travelerPassportIssue']) && $input['travelerPassportIssue'] !== '') {
                $issueDate = $input['travelerPassportIssue'];
                // 날짜 형식 변환 (YYYYMMDD -> YYYY-MM-DD 또는 이미 YYYY-MM-DD 형식인 경우 그대로 사용)
                if (strlen($issueDate) === 8 && is_numeric($issueDate)) {
                    // YYYYMMDD 형식인 경우
                    $issueDate = substr($issueDate, 0, 4) . '-' . substr($issueDate, 4, 2) . '-' . substr($issueDate, 6, 2);
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
                    // 유효한 날짜 형식이 아니면 로그만 남기고 저장하지 않음
                    error_log("Invalid passportIssueDate format: " . $issueDate);
                    $issueDate = null;
                }
                
                if ($issueDate) {
                    $clientFields[] = 'passportIssueDate';
                    $clientValues[] = $issueDate;
                    $clientTypes .= 's';
                    error_log("Adding passportIssueDate to client: " . $issueDate);
                }
            }
            
            // passportExpiry 컬럼이 있고 여행자 여권 만료일이 있으면 추가
            if (in_array('passportexpiry', $clientColumns) && !empty($input['travelerPassportExpire'])) {
                $clientFields[] = 'passportExpiry';
                $clientValues[] = $input['travelerPassportExpire'];
                $clientTypes .= 's';
            }
            
            // title 컬럼이 있고 여행자 호칭이 있으면 추가
            if (!in_array('title', $clientColumns) && !empty($input['travelerTitle'])) {
                // 컬럼이 없으면 생성
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN title VARCHAR(10) NULL");
                    $clientColumns[] = 'title';
                    error_log("Created title column in client table");
                } catch (Exception $e) {
                    error_log("Failed to create title column: " . $e->getMessage());
                }
            }
            if (in_array('title', $clientColumns) && !empty($input['travelerTitle'])) {
                $clientFields[] = 'title';
                $clientValues[] = $input['travelerTitle'];
                $clientTypes .= 's';
            }
            
            // memo 컬럼이 있고 메모가 있으면 추가
            if (!in_array('memo', $clientColumns) && !empty($input['memo'])) {
                // 컬럼이 없으면 생성
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN memo TEXT NULL");
                    $clientColumns[] = 'memo';
                    error_log("Created memo column in client table");
                } catch (Exception $e) {
                    error_log("Failed to create memo column: " . $e->getMessage());
                }
            }
            if (in_array('memo', $clientColumns) && isset($input['memo'])) {
                $clientFields[] = 'memo';
                $clientValues[] = $input['memo'] ?? '';
                $clientTypes .= 's';
            }
            
            // profileImage 컬럼이 없으면 생성
            if (!in_array('profileimage', $clientColumns)) {
                // 컬럼이 없으면 생성 (사진이 있든 없든 생성)
                try {
                    $conn->query("ALTER TABLE client ADD COLUMN profileImage VARCHAR(255) NULL");
                    $clientColumns[] = 'profileimage';
                    error_log("Created profileImage column in client table");
                } catch (Exception $e) {
                    error_log("Failed to create profileImage column: " . $e->getMessage());
                }
            }
            // profileImage 컬럼이 있고 여권 사진이 있으면 추가
            if (in_array('profileimage', $clientColumns) && $passportPhotoPath) {
                $clientFields[] = 'profileImage';
                $clientValues[] = $passportPhotoPath;
                $clientTypes .= 's';
                
                error_log("Creating customer with profileImage: " . $passportPhotoPath);
            } elseif ($passportPhotoPath) {
                // profileImage 컬럼이 없는데 사진이 업로드된 경우 로그
                error_log("WARNING: profileImage column not found but passport photo was uploaded: " . $passportPhotoPath);
            } else {
                // profileImage 컬럼이 있는데 사진이 없는 경우
                error_log("INFO: profileImage column exists but no passport photo uploaded. passportPhotoPath: " . ($passportPhotoPath ?? 'NULL'));
            }
            
            // createdAt 컬럼이 있으면 추가 (DEFAULT CURRENT_TIMESTAMP가 있으면 자동으로 값이 들어가므로 명시적으로 추가하지 않음)
            // 만약 명시적으로 추가해야 한다면 아래 주석을 해제
            /*
            if (in_array('createdAt', $clientColumns)) {
                $clientFields[] = 'createdAt';
                $clientValues[] = date('Y-m-d H:i:s');
                $clientTypes .= 's';
            }
            */
            
            $placeholders = str_repeat('?,', count($clientFields) - 1) . '?';
            $clientSql = "INSERT INTO client (" . implode(', ', $clientFields) . ") VALUES ($placeholders)";
            
            $clientStmt = $conn->prepare($clientSql);
            if (!$clientStmt) {
                throw new Exception('Failed to prepare client SQL: ' . $conn->error . ' | SQL: ' . $clientSql);
            }
            
            $clientStmt->bind_param($clientTypes, ...$clientValues);
            if (!$clientStmt->execute()) {
                throw new Exception('Failed to execute client insert: ' . $clientStmt->error . ' | SQL: ' . $clientSql . ' | Values: ' . json_encode($clientValues));
            }
            $customerId = $conn->insert_id;

            // ===== 계약 정보(Agent 계약) 저장: agent 테이블이 있으면 하위 에이전트 레코드 생성 =====
            $agentTable = $conn->query("SHOW TABLES LIKE 'agent'");
            if ($agentTable && $agentTable->num_rows > 0) {
                // 날짜 포맷 보정 (YYYYMMDD 또는 YYYY-MM-DD)
                $toDate = function ($v) {
                    $v = trim((string)$v);
                    if ($v === '') return null;
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
                    if (preg_match('/^\d{8}$/', $v)) {
                        return substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2);
                    }
                    return null;
                };

                $contractStart = isset($input['contractStartDate']) ? $toDate($input['contractStartDate']) : null;
                $contractEnd = isset($input['contractEndDate']) ? $toDate($input['contractEndDate']) : null;
                $contractMemo = isset($input['contractMemo']) ? (string)$input['contractMemo'] : null;

                // agentId는 UNIQUE NOT NULL (VARCHAR(20))
                $agentId = 'AGT' . str_pad((string)$accountId, 6, '0', STR_PAD_LEFT);

                // fName/lName/contactNo는 NOT NULL
                $aF = (string)($input['firstName'] ?? '');
                $aL = (string)($input['lastName'] ?? '');
                if ($aF === '') $aF = (string)($input['travelerFirstName'] ?? 'Customer');
                if ($aL === '') $aL = (string)($input['travelerLastName'] ?? '');

                $agentSql = "
                    INSERT INTO agent
                        (agentId, accountId, companyId, fName, lName, countryCode, contactNo, agentType, agentRole, contractStartDate, contractEndDate, memo)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, 'Wholeseller', 'Sub-Agent', ?, ?, ?)
                ";
                $agentStmt = $conn->prepare($agentSql);
                if ($agentStmt) {
                    // companyId는 nullable
                    $cid = !empty($resolvedCompanyId) ? (int)$resolvedCompanyId : null;
                    $cc = (string)($input['countryCode'] ?? '+82');
                    $cn = (string)($input['phone'] ?? '');

                    // bind_param은 null을 직접 넣기 어려우므로 변수로 전달
                    $iAccountId = (int)$accountId;
                    $iCompanyId = $cid;
                    $dStart = $contractStart;
                    $dEnd = $contractEnd;
                    $m = $contractMemo;

                    // contractStartDate/contractEndDate/memo는 NULL 허용
                    $agentStmt->bind_param(
                        'siisssssss',
                        $agentId,
                        $iAccountId,
                        $iCompanyId,
                        $aF,
                        $aL,
                        $cc,
                        $cn,
                        $dStart,
                        $dEnd,
                        $m
                    );
                    $agentStmt->execute();
                    $agentStmt->close();
                }
            }
            
            // 메모가 있으면 별도 테이블에 저장하거나 notes 필드에 저장 (DB 구조에 따라)
            // 현재는 client 테이블에 직접 저장하지 않고, 필요시 별도 테이블 사용 가능
            
            $conn->commit();
            
            return [
                'accountId' => $accountId,
                'customerId' => $customerId
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            // 업로드된 파일이 있으면 삭제
            // 기존 파일 삭제 (여러 경로 확인)
            if ($passportPhotoPath) {
                // __DIR__ = /var/www/htm/backend/api/
                // ../../../ = /var/www/html/
                $oldFilePath1 = __DIR__ . '/../../../' . $passportPhotoPath;
                $oldFilePath2 = __DIR__ . '/../../../' . ltrim($passportPhotoPath, '/');
                if (file_exists($oldFilePath1)) {
                    unlink($oldFilePath1);
                } elseif (file_exists($oldFilePath2)) {
                    unlink($oldFilePath2);
                }
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        throw $e;
    }
}

function createCustomer($conn, $input) {
    try {
        $result = createCustomerRecord($conn, $input, $_FILES);
        send_success_response($result, 'Customer created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create customer: ' . $e->getMessage());
    }
}

/**
 * 이메일로 B2C 고객을 검색 (에이전트 소속이 아닌 일반 회원)
 */
function searchB2CCustomer($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $email = trim($input['email'] ?? '');
        if (empty($email)) {
            send_error_response('Email is required');
        }

        // accounts 테이블 스키마 확인 (email vs emailAddress)
        $accountsColMap = [];
        $colRes = $conn->query("SHOW COLUMNS FROM accounts");
        if ($colRes) {
            while ($row = $colRes->fetch_assoc()) {
                $f = (string)($row['Field'] ?? '');
                if ($f !== '') $accountsColMap[strtolower($f)] = $f;
            }
        }
        $emailCol = $accountsColMap['emailaddress'] ?? ($accountsColMap['email'] ?? 'emailAddress');

        // accounts에서 이메일로 검색
        $sql = "SELECT a.accountId, a.`{$emailCol}` AS email, a.accountType
                FROM accounts a
                WHERE a.`{$emailCol}` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('No customer found with this email.');
        }

        $account = $result->fetch_assoc();
        $accountId = (int)$account['accountId'];

        // accountType이 agent면 에이전트 계정이므로 B2C 고객이 아님
        if (($account['accountType'] ?? '') === 'agent') {
            send_error_response('This account is an agent, not a customer.');
        }

        // client 테이블에서 정보 확인
        $clientColMap = [];
        $cRes = $conn->query("SHOW COLUMNS FROM client");
        if ($cRes) {
            while ($row = $cRes->fetch_assoc()) {
                $f = (string)($row['Field'] ?? '');
                if ($f !== '') $clientColMap[strtolower($f)] = $f;
            }
        }
        $hasClientType = isset($clientColMap['clienttype']);
        $hasAgentId = isset($clientColMap['agentid']);

        $clientSql = "SELECT c.* FROM client c WHERE c.accountId = ? LIMIT 1";
        $clientStmt = $conn->prepare($clientSql);
        $clientStmt->bind_param('i', $accountId);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $clientRow = $clientResult->fetch_assoc();

        // 이미 에이전트에 소속된 B2B 고객인지 확인
        if ($clientRow) {
            $clientType = strtolower($clientRow['clientType'] ?? '');
            $agentId = $clientRow['agentId'] ?? null;

            if (in_array($clientType, ['wholeseller', 'wholesaler']) && !empty($agentId)) {
                send_error_response('This customer is already assigned to an agent (B2B).');
            }
        }

        // 이름 조합
        $firstName = $clientRow['fName'] ?? '';
        $lastName = $clientRow['lName'] ?? '';
        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') $name = $account['email'];
        $phone = $clientRow['contactNo'] ?? '';

        send_success_response([
            'accountId' => $accountId,
            'name' => $name,
            'email' => $account['email'],
            'phone' => $phone
        ], 'Customer found');

    } catch (Exception $e) {
        send_error_response('Search failed: ' . $e->getMessage());
    }
}

/**
 * B2C 고객을 에이전트 소속 B2B로 전환
 */
function assignExistingCustomer($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $targetAccountId = (int)($input['targetAccountId'] ?? 0);
        if (empty($targetAccountId)) {
            send_error_response('Target account ID is required');
        }

        // 에이전트의 companyId 조회
        $scope = get_agent_scope($conn, $agentAccountId);
        $companyId = !empty($scope['companyId']) ? (int)$scope['companyId'] : null;

        // 대상 고객 유효성 재검증 (race condition 방지)
        $accountsColMap = [];
        $colRes = $conn->query("SHOW COLUMNS FROM accounts");
        if ($colRes) {
            while ($row = $colRes->fetch_assoc()) {
                $f = (string)($row['Field'] ?? '');
                if ($f !== '') $accountsColMap[strtolower($f)] = $f;
            }
        }
        $emailCol = $accountsColMap['emailaddress'] ?? ($accountsColMap['email'] ?? 'emailAddress');

        $checkSql = "SELECT accountId, `{$emailCol}` AS email, accountType FROM accounts WHERE accountId = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $targetAccountId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            send_error_response('Target account not found.');
        }
        $targetAccount = $checkResult->fetch_assoc();

        if (($targetAccount['accountType'] ?? '') === 'agent') {
            send_error_response('Cannot assign an agent account.');
        }

        // client 테이블 컬럼 확인
        $clientColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM client");
        while ($row = $columnResult->fetch_assoc()) {
            $clientColumns[] = strtolower($row['Field']);
        }

        // client 레코드 존재 여부 확인
        $clientCheckSql = "SELECT * FROM client WHERE accountId = ? LIMIT 1";
        $clientCheckStmt = $conn->prepare($clientCheckSql);
        $clientCheckStmt->bind_param('i', $targetAccountId);
        $clientCheckStmt->execute();
        $clientCheckResult = $clientCheckStmt->get_result();
        $existingClient = $clientCheckResult->fetch_assoc();

        if ($existingClient) {
            $clientType = strtolower($existingClient['clientType'] ?? '');
            $existingAgentId = $existingClient['agentId'] ?? null;
            if (in_array($clientType, ['wholeseller', 'wholesaler']) && !empty($existingAgentId)) {
                send_error_response('This customer is already assigned to an agent.');
            }
        }

        $conn->begin_transaction();
        try {
            if ($existingClient) {
                // UPDATE existing client record
                $updateParts = [];
                $updateValues = [];
                $updateTypes = '';

                if (in_array('clienttype', $clientColumns)) {
                    $updateParts[] = "clientType = ?";
                    $updateValues[] = 'Wholeseller';
                    $updateTypes .= 's';
                }
                if (in_array('companyid', $clientColumns) && $companyId) {
                    $updateParts[] = "companyId = ?";
                    $updateValues[] = $companyId;
                    $updateTypes .= 'i';
                }
                if (in_array('agentid', $clientColumns)) {
                    $updateParts[] = "agentId = ?";
                    $updateValues[] = $agentAccountId;
                    $updateTypes .= 'i';
                }
                if (in_array('clientrole', $clientColumns)) {
                    $updateParts[] = "clientRole = ?";
                    $updateValues[] = 'Sub-Agent';
                    $updateTypes .= 's';
                }

                if (!empty($updateParts)) {
                    $updateSql = "UPDATE client SET " . implode(', ', $updateParts) . " WHERE accountId = ?";
                    $updateValues[] = $targetAccountId;
                    $updateTypes .= 'i';

                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param($updateTypes, ...$updateValues);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            } else {
                // INSERT new client record
                $clientFields = [];
                $clientValues = [];
                $clientTypes = '';

                if (in_array('clientid', $clientColumns)) {
                    $clientId = 'CLI' . str_pad($targetAccountId, 6, '0', STR_PAD_LEFT);
                    $clientFields[] = 'clientId';
                    $clientValues[] = $clientId;
                    $clientTypes .= 's';
                }

                $clientFields[] = 'accountId';
                $clientValues[] = $targetAccountId;
                $clientTypes .= 'i';

                $clientFields[] = 'fName';
                $clientValues[] = '';
                $clientTypes .= 's';

                $clientFields[] = 'lName';
                $clientValues[] = '';
                $clientTypes .= 's';

                $clientFields[] = 'contactNo';
                $clientValues[] = '';
                $clientTypes .= 's';

                if (in_array('emailaddress', $clientColumns)) {
                    $clientFields[] = 'emailAddress';
                    $clientValues[] = $targetAccount['email'];
                    $clientTypes .= 's';
                }

                if (in_array('clienttype', $clientColumns)) {
                    $clientFields[] = 'clientType';
                    $clientValues[] = 'Wholeseller';
                    $clientTypes .= 's';
                }
                if (in_array('clientrole', $clientColumns)) {
                    $clientFields[] = 'clientRole';
                    $clientValues[] = 'Sub-Agent';
                    $clientTypes .= 's';
                }
                if (in_array('companyid', $clientColumns) && $companyId) {
                    $clientFields[] = 'companyId';
                    $clientValues[] = $companyId;
                    $clientTypes .= 'i';
                }
                if (in_array('agentid', $clientColumns)) {
                    $clientFields[] = 'agentId';
                    $clientValues[] = $agentAccountId;
                    $clientTypes .= 'i';
                }

                $placeholders = str_repeat('?,', count($clientFields) - 1) . '?';
                $insertSql = "INSERT INTO client (" . implode(', ', $clientFields) . ") VALUES ($placeholders)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param($clientTypes, ...$clientValues);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // agent 테이블에 레코드 INSERT (createCustomerRecord 패턴과 동일)
            $agentTable = $conn->query("SHOW TABLES LIKE 'agent'");
            if ($agentTable && $agentTable->num_rows > 0) {
                // 이미 agent 레코드가 있는지 확인
                $agentCheck = $conn->prepare("SELECT id FROM agent WHERE accountId = ? LIMIT 1");
                $agentCheck->bind_param('i', $targetAccountId);
                $agentCheck->execute();
                $agentCheckResult = $agentCheck->get_result();

                if ($agentCheckResult->num_rows === 0) {
                    $agentId = 'AGT' . str_pad((string)$targetAccountId, 6, '0', STR_PAD_LEFT);
                    $aF = $existingClient ? (string)($existingClient['fName'] ?? '') : '';
                    $aL = $existingClient ? (string)($existingClient['lName'] ?? '') : '';
                    $cn = $existingClient ? (string)($existingClient['contactNo'] ?? '') : '';
                    if ($aF === '') $aF = 'Customer';

                    $agentSql = "INSERT INTO agent (agentId, accountId, companyId, fName, lName, countryCode, contactNo, agentType, agentRole)
                                 VALUES (?, ?, ?, ?, ?, '+82', ?, 'Wholeseller', 'Sub-Agent')";
                    $agentStmt = $conn->prepare($agentSql);
                    if ($agentStmt) {
                        $agentStmt->bind_param('siisss', $agentId, $targetAccountId, $companyId, $aF, $aL, $cn);
                        $agentStmt->execute();
                        $agentStmt->close();
                    }
                }
                $agentCheck->close();
            }

            $conn->commit();
            send_success_response(['accountId' => $targetAccountId], 'Customer assigned successfully');

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        send_error_response('Assignment failed: ' . $e->getMessage());
    }
}

function updateCustomer($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? '';
        
        if (empty($accountId)) {
            send_error_response('Account ID is required');
        }
        
        $conn->begin_transaction();
        
        // accounts 테이블 업데이트
        $accountUpdates = [];
        $accountParams = [];
        $accountTypes = '';
        
        // 이메일 업데이트
        if (isset($input['email'])) {
            $accountUpdates[] = "emailAddress = ?";
            $accountParams[] = $input['email'];
            $accountTypes .= 's';
        }

        // 비밀번호 수동 업데이트 (화면에서 직접 입력 저장 / 자동생성 저장)
        if (isset($input['password']) && is_string($input['password']) && trim($input['password']) !== '') {
            $columnCheck = $conn->query("SHOW COLUMNS FROM accounts LIKE 'password'");
            $passwordColumn = ($columnCheck->num_rows > 0) ? 'password' : 'passwordHash';
            $accountUpdates[] = "$passwordColumn = ?";
            $accountParams[] = password_hash($input['password'], PASSWORD_DEFAULT);
            $accountTypes .= 's';
        }
        
        // 비밀번호 업데이트 (resetPassword 액션일 때)
        if (isset($input['resetPassword']) && $input['resetPassword'] === true) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM accounts LIKE 'password'");
            $passwordColumn = ($columnCheck->num_rows > 0) ? 'password' : 'passwordHash';
            $defaultPassword = password_hash('123456', PASSWORD_DEFAULT); // 기본 비밀번호
            $accountUpdates[] = "$passwordColumn = ?";
            $accountParams[] = $defaultPassword;
            $accountTypes .= 's';
        }
        
        if (!empty($accountUpdates)) {
            $accountParams[] = $accountId;
            $accountTypes .= 'i';
            $accountSql = "UPDATE accounts SET " . implode(', ', $accountUpdates) . " WHERE accountId = ?";
            $accountStmt = $conn->prepare($accountSql);
            $accountStmt->bind_param($accountTypes, ...$accountParams);
            $accountStmt->execute();
        }
        
        // client 테이블 업데이트
        $clientColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM client");
        while ($row = $columnResult->fetch_assoc()) {
            $clientColumns[] = strtolower($row['Field']);
        }

        // client 레코드가 없으면 최소 레코드를 먼저 생성 (저장 후 '초기화' 문제 방지)
        $clientExistsStmt = $conn->prepare("SELECT 1 FROM client WHERE accountId = ? LIMIT 1");
        $clientExistsStmt->bind_param('i', $accountId);
        $clientExistsStmt->execute();
        $clientExists = $clientExistsStmt->get_result()->num_rows > 0;
        $clientExistsStmt->close();

        if (!$clientExists) {
            $insertFields = ['accountId'];
            $insertValues = [(int)$accountId];
            $insertTypes = 'i';
            $insertPH = ['?'];

            if (in_array('clientid', $clientColumns, true)) {
                $insertFields[] = 'clientId';
                $insertValues[] = 'CLI' . str_pad((string)$accountId, 6, '0', STR_PAD_LEFT);
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('fname', $clientColumns, true)) {
                $insertFields[] = 'fName';
                $insertValues[] = (string)($input['firstName'] ?? '');
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('lname', $clientColumns, true)) {
                $insertFields[] = 'lName';
                $insertValues[] = (string)($input['lastName'] ?? '');
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('contactno', $clientColumns, true)) {
                $insertFields[] = 'contactNo';
                $insertValues[] = (string)($input['phone'] ?? '');
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('emailaddress', $clientColumns, true)) {
                $insertFields[] = 'emailAddress';
                $insertValues[] = (string)($input['email'] ?? '');
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('countrycode', $clientColumns, true) && isset($input['countryCode'])) {
                $insertFields[] = 'countryCode';
                $insertValues[] = (string)$input['countryCode'];
                $insertTypes .= 's';
                $insertPH[] = '?';
            }
            if (in_array('clienttype', $clientColumns, true)) {
                $insertFields[] = 'clientType';
                $insertValues[] = 'Wholeseller';
                $insertTypes .= 's';
                $insertPH[] = '?';
            }

            $insSql = "INSERT INTO client (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPH) . ")";
            $insStmt = $conn->prepare($insSql);
            if ($insStmt) {
                $insStmt->bind_param($insertTypes, ...$insertValues);
                $insStmt->execute();
                $insStmt->close();
            }
        }
        
        $clientUpdates = [];
        $clientParams = [];
        $clientTypes = '';
        
        // 이름
        if (isset($input['firstName'])) {
            $clientUpdates[] = "fName = ?";
            $clientParams[] = $input['firstName'];
            $clientTypes .= 's';
        }
        
        if (isset($input['lastName'])) {
            $clientUpdates[] = "lName = ?";
            $clientParams[] = $input['lastName'];
            $clientTypes .= 's';
        }
        
        // 연락처
        if (isset($input['phone'])) {
            $clientUpdates[] = "contactNo = ?";
            $clientParams[] = $input['phone'];
            $clientTypes .= 's';
        }
        
        // 국가 코드
        if (isset($input['countryCode']) && in_array('countrycode', $clientColumns)) {
            $clientUpdates[] = "countryCode = ?";
            $clientParams[] = $input['countryCode'];
            $clientTypes .= 's';
        }
        
        // 메모 - 컬럼이 없으면 생성
        if (isset($input['memo']) && !in_array('memo', $clientColumns)) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN memo TEXT NULL");
                $clientColumns[] = 'memo';
                error_log("Created memo column in client table");
            } catch (Exception $e) {
                error_log("Failed to create memo column: " . $e->getMessage());
            }
        }
        if (isset($input['memo']) && in_array('memo', $clientColumns)) {
            $clientUpdates[] = "memo = ?";
            $clientParams[] = $input['memo'];
            $clientTypes .= 's';
        }
        
        // agreementContent 컬럼 처리
        if (!in_array('agreementcontent', $clientColumns) && isset($input['agreementContent'])) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN agreementContent TEXT NULL");
                $clientColumns[] = 'agreementcontent';
                error_log("Created agreementContent column in client table");
            } catch (Exception $e) {
                error_log("Failed to create agreementContent column: " . $e->getMessage());
            }
        }
        if (isset($input['agreementContent']) && in_array('agreementcontent', $clientColumns)) {
            $clientUpdates[] = "agreementContent = ?";
            $clientParams[] = $input['agreementContent'];
            $clientTypes .= 's';
        }
        
        // countryOfResidence 컬럼 처리
        if (!in_array('countryofresidence', $clientColumns) && isset($input['travelerCountryOfResidence'])) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN countryOfResidence VARCHAR(100) NULL");
                $clientColumns[] = 'countryofresidence';
                error_log("Created countryOfResidence column in client table");
            } catch (Exception $e) {
                error_log("Failed to create countryOfResidence column: " . $e->getMessage());
            }
        }
        if (isset($input['travelerCountryOfResidence']) && in_array('countryofresidence', $clientColumns)) {
            $clientUpdates[] = "countryOfResidence = ?";
            $clientParams[] = $input['travelerCountryOfResidence'];
            $clientTypes .= 's';
        }
        
        // visaInformation 컬럼 처리
        if (!in_array('visainformation', $clientColumns) && isset($input['travelerVisaInformation'])) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN visaInformation VARCHAR(255) NULL");
                $clientColumns[] = 'visainformation';
                error_log("Created visaInformation column in client table");
            } catch (Exception $e) {
                error_log("Failed to create visaInformation column: " . $e->getMessage());
            }
        }
        if (isset($input['travelerVisaInformation']) && in_array('visainformation', $clientColumns)) {
            $clientUpdates[] = "visaInformation = ?";
            $clientParams[] = $input['travelerVisaInformation'];
            $clientTypes .= 's';
        }
        
        // remarks 컬럼 처리
        if (!in_array('remarks', $clientColumns) && isset($input['travelerRemarks'])) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN remarks TEXT NULL");
                $clientColumns[] = 'remarks';
                error_log("Created remarks column in client table");
            } catch (Exception $e) {
                error_log("Failed to create remarks column: " . $e->getMessage());
            }
        }
        if (isset($input['travelerRemarks']) && in_array('remarks', $clientColumns)) {
            $clientUpdates[] = "remarks = ?";
            $clientParams[] = $input['travelerRemarks'];
            $clientTypes .= 's';
        }
        
        // 여행자 정보 - title 컬럼이 없으면 생성
        if (isset($input['title']) && !in_array('title', $clientColumns)) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN title VARCHAR(10) NULL");
                $clientColumns[] = 'title';
                error_log("Created title column in client table");
            } catch (Exception $e) {
                error_log("Failed to create title column: " . $e->getMessage());
            }
        }
        if (isset($input['title']) && in_array('title', $clientColumns)) {
            $clientUpdates[] = "title = ?";
            $clientParams[] = $input['title'];
            $clientTypes .= 's';
        }
        
        if (isset($input['travelerGender']) && in_array('gender', $clientColumns)) {
            $clientUpdates[] = "gender = ?";
            $clientParams[] = $input['travelerGender'];
            $clientTypes .= 's';
        }
        
        if (isset($input['travelerBirth']) && in_array('dateofbirth', $clientColumns)) {
            // YYYYMMDD 형식을 YYYY-MM-DD로 변환
            $birthDate = $input['travelerBirth'];
            if (strlen($birthDate) === 8 && is_numeric($birthDate)) {
                $birthDate = substr($birthDate, 0, 4) . '-' . substr($birthDate, 4, 2) . '-' . substr($birthDate, 6, 2);
            }
            $clientUpdates[] = "dateOfBirth = ?";
            $clientParams[] = $birthDate;
            $clientTypes .= 's';
        }
        
        if (isset($input['travelerNationality']) && in_array('nationality', $clientColumns)) {
            $clientUpdates[] = "nationality = ?";
            $clientParams[] = $input['travelerNationality'];
            $clientTypes .= 's';
        }
        
        if (isset($input['travelerPassportNo']) && in_array('passportnumber', $clientColumns)) {
            $clientUpdates[] = "passportNumber = ?";
            $clientParams[] = $input['travelerPassportNo'];
            $clientTypes .= 's';
        }
        
        // passportIssueDate 컬럼이 없으면 생성
        if (isset($input['travelerPassportIssue']) && !in_array('passportissuedate', $clientColumns)) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN passportIssueDate DATE NULL");
                $clientColumns[] = 'passportissuedate';
                error_log("Created passportIssueDate column in client table");
            } catch (Exception $e) {
                error_log("Failed to create passportIssueDate column: " . $e->getMessage());
            }
        }
        if (isset($input['travelerPassportIssue']) && in_array('passportissuedate', $clientColumns)) {
            $issueDate = $input['travelerPassportIssue'];
            if (strlen($issueDate) === 8 && is_numeric($issueDate)) {
                $issueDate = substr($issueDate, 0, 4) . '-' . substr($issueDate, 4, 2) . '-' . substr($issueDate, 6, 2);
            }
            $clientUpdates[] = "passportIssueDate = ?";
            $clientParams[] = $issueDate;
            $clientTypes .= 's';
        }
        
        if (isset($input['travelerPassportExpire']) && in_array('passportexpiry', $clientColumns)) {
            $expiryDate = $input['travelerPassportExpire'];
            if (strlen($expiryDate) === 8 && is_numeric($expiryDate)) {
                $expiryDate = substr($expiryDate, 0, 4) . '-' . substr($expiryDate, 4, 2) . '-' . substr($expiryDate, 6, 2);
            }
            $clientUpdates[] = "passportExpiry = ?";
            $clientParams[] = $expiryDate;
            $clientTypes .= 's';
        }

        // travelerFirstName / travelerLastName 컬럼 처리 (고객명과 여행자명 분리 저장)
        if (!in_array('travelerfirstname', $clientColumns) && (isset($input['travelerFirstName']) || isset($input['travelerLastName']))) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN travelerFirstName VARCHAR(100) NULL");
                $clientColumns[] = 'travelerfirstname';
                error_log("Created travelerFirstName column in client table");
            } catch (Exception $e) {
                error_log("Failed to create travelerFirstName column: " . $e->getMessage());
            }
        }
        if (!in_array('travelerlastname', $clientColumns) && (isset($input['travelerFirstName']) || isset($input['travelerLastName']))) {
            try {
                $conn->query("ALTER TABLE client ADD COLUMN travelerLastName VARCHAR(100) NULL");
                $clientColumns[] = 'travelerlastname';
                error_log("Created travelerLastName column in client table");
            } catch (Exception $e) {
                error_log("Failed to create travelerLastName column: " . $e->getMessage());
            }
        }
        if (isset($input['travelerFirstName']) && in_array('travelerfirstname', $clientColumns)) {
            $clientUpdates[] = "travelerFirstName = ?";
            $clientParams[] = (string)$input['travelerFirstName'];
            $clientTypes .= 's';
        }
        if (isset($input['travelerLastName']) && in_array('travelerlastname', $clientColumns)) {
            $clientUpdates[] = "travelerLastName = ?";
            $clientParams[] = (string)$input['travelerLastName'];
            $clientTypes .= 's';
        }
        
        // 여권 사진 업로드 처리
        $passportPhotoPath = null;
        if (isset($_FILES['passportPhoto']) && $_FILES['passportPhoto']['error'] === UPLOAD_ERR_OK) {
            // 실제 저장 경로: /var/www/html/uploads/passports/
            // __DIR__ = /var/www/html/admin/backend/api/
            // ../../../ = /var/www/html/
            $uploadDir = __DIR__ . '/../../../uploads/passports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['passportPhoto']['name'], PATHINFO_EXTENSION);
            $fileName = 'passport_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['passportPhoto']['tmp_name'], $uploadPath)) {
                // 웹에서 접근 가능한 경로
                $passportPhotoPath = 'uploads/passports/' . $fileName;
                
                // profileImage 컬럼이 없으면 생성
                if (!in_array('profileimage', $clientColumns)) {
                    try {
                        $conn->query("ALTER TABLE client ADD COLUMN profileImage VARCHAR(255) NULL");
                        $clientColumns[] = 'profileimage';
                        error_log("Created profileImage column in client table");
                    } catch (Exception $e) {
                        error_log("Failed to create profileImage column: " . $e->getMessage());
                    }
                }
                
                // 기존 여권 사진 삭제
                $oldPhotoSql = "SELECT profileImage FROM client WHERE accountId = ?";
                $oldPhotoStmt = $conn->prepare($oldPhotoSql);
                $oldPhotoStmt->bind_param("i", $accountId);
                $oldPhotoStmt->execute();
                $oldPhotoResult = $oldPhotoStmt->get_result();
                if ($oldPhotoRow = $oldPhotoResult->fetch_assoc()) {
                    // 기존 파일 삭제 (여러 경로 확인)
                    $oldImagePath = $oldPhotoRow['profileImage'];
                    if (!empty($oldImagePath)) {
                        // smart-travel2 제거 및 경로 정규화
                        $oldImagePathClean = str_replace('/smart-travel2/', '/', $oldImagePath);
                        $oldImagePathClean = str_replace('smart-travel2/', '', $oldImagePathClean);
                        $oldImagePathClean = preg_replace('#/uploads/uploads/#', '/uploads/', $oldImagePathClean);
                        $oldImagePathClean = ltrim($oldImagePathClean, '/');
                        
                        $oldFilePath1 = __DIR__ . '/../../../' . $oldImagePathClean;
                        $oldFilePath2 = __DIR__ . '/../../../../' . $oldImagePathClean;
                        
                        if (file_exists($oldFilePath1)) {
                            unlink($oldFilePath1);
                        } elseif (file_exists($oldFilePath2)) {
                            unlink($oldFilePath2);
                        }
                    }
                }
                
                if (in_array('profileimage', $clientColumns)) {
                    $clientUpdates[] = "profileImage = ?";
                    $clientParams[] = $passportPhotoPath;
                    $clientTypes .= 's';
                }
            }
        }
        
        if (!empty($clientUpdates)) {
            $clientParams[] = $accountId;
            $clientTypes .= 'i';
            $clientSql = "UPDATE client SET " . implode(', ', $clientUpdates) . " WHERE accountId = ?";
            $clientStmt = $conn->prepare($clientSql);
            if (!$clientStmt) {
                throw new Exception('Failed to prepare client SQL: ' . $conn->error);
            }
            $clientStmt->bind_param($clientTypes, ...$clientParams);
            if (!$clientStmt->execute()) {
                throw new Exception('Failed to execute client update: ' . $clientStmt->error);
            }
        }
        
        $conn->commit();
        
        send_success_response([], 'Customer updated successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        // 기존 파일 삭제 (여러 경로 확인)
        if (isset($passportPhotoPath) && $passportPhotoPath) {
            $oldFilePath1 = __DIR__ . '/../../../../' . $passportPhotoPath;
            $oldFilePath2 = __DIR__ . '/../../../../' . ltrim($passportPhotoPath, '/');
            if (file_exists($oldFilePath1)) {
                unlink($oldFilePath1);
            } elseif (file_exists($oldFilePath2)) {
                unlink($oldFilePath2);
            }
        }
        send_error_response('Failed to update customer: ' . $e->getMessage());
    }
}

function deleteCustomer($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? '';
        
        if (empty($accountId)) {
            send_error_response('Account ID is required');
        }
        
        $conn->begin_transaction();
        
        // client 테이블에서 삭제
        $clientSql = "DELETE FROM client WHERE accountId = ?";
        $clientStmt = $conn->prepare($clientSql);
        $clientStmt->bind_param("i", $accountId);
        $clientStmt->execute();
        
        // accounts 테이블에서 삭제
        $accountSql = "DELETE FROM accounts WHERE accountId = ?";
        $accountStmt = $conn->prepare($accountSql);
        $accountStmt->bind_param("i", $accountId);
        $accountStmt->execute();
        
        $conn->commit();
        
        send_success_response([], 'Customer deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to delete customer: ' . $e->getMessage());
    }
}

function resetPassword($conn, $input) {
    try {
        $accountId = $input['accountId'] ?? '';
        
        if (empty($accountId)) {
            send_error_response('Account ID is required');
        }
        
        // 비밀번호 컬럼 확인
        $columnCheck = $conn->query("SHOW COLUMNS FROM accounts LIKE 'password'");
        $passwordColumn = ($columnCheck->num_rows > 0) ? 'password' : 'passwordHash';
        
        // 기본 비밀번호 생성 (123456)
        $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
        
        $sql = "UPDATE accounts SET $passwordColumn = ? WHERE accountId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $defaultPassword, $accountId);
        $stmt->execute();
        
        send_success_response([], 'Password reset successfully');
        
    } catch (Exception $e) {
        send_error_response('Failed to reset password: ' . $e->getMessage());
    }
}

// ========== 문의 관련 함수들 ==========

function getInquiries($conn, $input) {
    try {
        $page = isset($input['page']) ? (int)$input['page'] : 1;
        $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        // Agent의 accountId 가져오기 (세션에서)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? null);
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // inquiries 컬럼 맵 (스키마 차이 흡수)
        $cols = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $f = (string)($col['Field'] ?? '');
                if ($f !== '') $cols[strtolower($f)] = $f;
            }
        }
        // lower-case 컬럼명 배열(편의)
        $inquiryColumns = array_keys($cols);

        // 필수/핵심 컬럼 탐색
        $idCol = $cols['inquiryid'] ?? ($cols['id'] ?? null);
        $accountIdCol = $cols['accountid'] ?? ($cols['account_id'] ?? ($cols['userid'] ?? ($cols['user_id'] ?? null)));
        $statusCol = $cols['status'] ?? ($cols['inquirystatus'] ?? null);
        $createdAtCol = $cols['createdat'] ?? ($cols['created_at'] ?? ($cols['registrationdate'] ?? ($cols['regdate'] ?? null)));
        $subjectCol = $cols['subject'] ?? ($cols['inquirytitle'] ?? ($cols['title'] ?? null));
        $contentCol = $cols['content'] ?? ($cols['inquirycontent'] ?? null);
        $categoryCol = $cols['category'] ?? ($cols['inquirytype'] ?? ($cols['type'] ?? null));

        if (!$idCol || !$accountIdCol) {
            send_error_response("Failed to get inquiries: inquiries schema missing required columns (id/accountId)");
        }
        
        $where = [];
        $params = [];
        $types = '';
        
        // Agent는 자신이 작성한 문의만 조회
        if ($accountIdCol) {
            $where[] = "i.`{$accountIdCol}` = ?";
            $params[] = $agentAccountId;
            $types .= 'i';
        }
        
        // 검색 (문의 제목)
        if (!empty($input['search'])) {
            if ($subjectCol) {
                $where[] = "(i.`{$subjectCol}` LIKE ?)";
                $searchTerm = '%' . $input['search'] . '%';
                $params[] = $searchTerm;
                $types .= 's';
            }
        }
        
        // 문의 유형 필터
        if (!empty($input['inquiryType'])) {
            // UI: general/reservation/payment/refund/other
            $uiType = strtolower(trim((string)$input['inquiryType']));
            // DB(inquiry.php): category: general|booking|visa|payment|technical|complaint|suggestion
            $dbType = $uiType;
            if ($uiType === 'reservation') $dbType = 'booking';
            if ($uiType === 'refund') $dbType = 'payment';
            if ($uiType === 'other') $dbType = 'general';

            if ($categoryCol) {
                $where[] = "i.`{$categoryCol}` = ?";
                $params[] = $dbType;
                $types .= 's';
            }
        }
        
        // === 새 UI 필터 ===
        // 1) Processing Status: received / in_progress / processing_complete
        // 2) Response Status: not_responded / response_complete
        // (기존 호환: status=pending/processing/completed 도 처리 상태로 간주)

        // reply 존재 여부를 판단하기 위한 스키마 확인
        $hasReplyContentCol = isset($cols['replycontent']);
        $hasReplyTable = false;
        $replyIdField = null;
        if (!$hasReplyContentCol) {
            $replyTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
            if ($replyTableCheck && $replyTableCheck->num_rows > 0) {
                $hasReplyTable = true;
                $replyColumns = [];
                $replyColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_replies");
                if ($replyColumnResult) {
                    while ($col = $replyColumnResult->fetch_assoc()) {
                        $replyColumns[] = strtolower($col['Field']);
                    }
                }
                $replyIdField = in_array('inquiryid', $replyColumns, true) ? 'inquiryId' : (in_array('inquiry_id', $replyColumns, true) ? 'inquiry_id' : null);
            }
        }

        // 처리 상태 필터
        $processingStatus = $input['processingStatus'] ?? null;
        if (empty($processingStatus) && !empty($input['status'])) {
            // legacy: pending/processing/completed → processingStatus 로 매핑
            $legacy = strtolower(trim((string)$input['status']));
            if ($legacy === 'pending') $processingStatus = 'received';
            if ($legacy === 'processing') $processingStatus = 'in_progress';
            if ($legacy === 'completed') $processingStatus = 'processing_complete';
        }
        if (!empty($processingStatus) && $statusCol) {
            $ps = strtolower(trim((string)$processingStatus));
            if ($ps === 'received') {
                $where[] = "i.`{$statusCol}` IN ('open')";
            } elseif ($ps === 'in_progress') {
                $where[] = "i.`{$statusCol}` IN ('in_progress')";
            } elseif ($ps === 'processing_complete') {
                $where[] = "i.`{$statusCol}` IN ('resolved','closed')";
            }
        }

        // 답변 여부 필터
        if (!empty($input['responseStatus'])) {
            $rs = strtolower(trim((string)$input['responseStatus']));
            if ($hasReplyContentCol) {
                if ($rs === 'not_responded') {
                    $where[] = "(COALESCE(i.`replyContent`, '') = '')";
                } elseif ($rs === 'response_complete') {
                    $where[] = "(COALESCE(i.`replyContent`, '') <> '')";
                }
            } elseif ($hasReplyTable && $replyIdField) {
                if ($rs === 'not_responded') {
                    $where[] = "NOT EXISTS (SELECT 1 FROM inquiry_replies r WHERE r.`{$replyIdField}` = i.`{$idCol}`)";
                } elseif ($rs === 'response_complete') {
                    $where[] = "EXISTS (SELECT 1 FROM inquiry_replies r WHERE r.`{$replyIdField}` = i.`{$idCol}`)";
                }
            } elseif ($statusCol) {
                // fallback: status로 판단
                if ($rs === 'not_responded') {
                    $where[] = "i.`{$statusCol}` IN ('open','in_progress')";
                } elseif ($rs === 'response_complete') {
                    $where[] = "i.`{$statusCol}` IN ('resolved','closed')";
                }
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM inquiries i $whereClause";
        
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            mysqli_bind_params_by_ref($countStmt, $types, $params);
            $countStmt->execute();
            $totalResult = $countStmt->get_result();
        } else {
            $totalResult = $conn->query($countSql);
        }
        $total = $totalResult->fetch_assoc()['total'];
        
        // SELECT용 표현식 구성 (alias는 SELECT에서 통일)
        $titleExpr = $subjectCol ? "i.`{$subjectCol}`" : "''";

        // inquiryType는 category/type 기반으로 UI 타입으로 매핑
        if ($categoryCol) {
            $typeExpr = "CASE 
                WHEN i.`{$categoryCol}` = 'booking' THEN 'reservation'
                WHEN i.`{$categoryCol}` = 'payment' THEN 'payment'
                WHEN i.`{$categoryCol}` = 'visa' THEN 'other'
                WHEN i.`{$categoryCol}` = 'technical' THEN 'other'
                WHEN i.`{$categoryCol}` = 'complaint' THEN 'other'
                WHEN i.`{$categoryCol}` = 'suggestion' THEN 'other'
                WHEN i.`{$categoryCol}` = 'refund' THEN 'refund'
                ELSE 'general'
            END";
        } else {
            $typeExpr = "NULL";
        }
        
        // 정렬
        $sortOrder = (!empty($input['sort']) && $input['sort'] === 'oldest') ? 'ASC' : 'DESC';
        $orderCol = $createdAtCol ? "i.`{$createdAtCol}`" : "i.`{$idCol}`";
        
        $sql = "
            SELECT 
                i.`{$idCol}` AS inquiryId,
                $titleExpr AS inquiryTitle,
                $typeExpr AS inquiryType,
                " . ($statusCol ? "i.`{$statusCol}`" : "NULL") . " AS dbStatus,
                " . ($hasReplyContentCol ? "i.`replyContent`" : "NULL") . " AS replyContent,
                " . ($createdAtCol ? "i.`{$createdAtCol}`" : "NULL") . " AS createdAt
            FROM inquiries i
            $whereClause
            ORDER BY $orderCol $sortOrder
            LIMIT ? OFFSET ?
        ";
        
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $inquiries = [];
        $rowNum = $total - $offset;
        while ($row = $result->fetch_assoc()) {
            $dbStatus = strtolower((string)($row['dbStatus'] ?? 'open'));
            // Processing Status
            $processingStatusUi = 'received';
            if ($dbStatus === 'in_progress') $processingStatusUi = 'in_progress';
            elseif ($dbStatus === 'resolved' || $dbStatus === 'closed') $processingStatusUi = 'processing_complete';

            $processingLabel = 'Received';
            if ($processingStatusUi === 'in_progress') $processingLabel = 'In Progress';
            if ($processingStatusUi === 'processing_complete') $processingLabel = 'Processing Complete';

            // Response Status (reply 존재 여부)
            $hasReply = false;
            if ($hasReplyContentCol) {
                $hasReply = !empty(trim((string)($row['replyContent'] ?? '')));
            } elseif ($hasReplyTable && $replyIdField) {
                $chkSql = "SELECT 1 FROM inquiry_replies r WHERE r.`{$replyIdField}` = ? LIMIT 1";
                $chk = $conn->prepare($chkSql);
                $iid = (int)$row['inquiryId'];
                $chk->bind_param('i', $iid);
                $chk->execute();
                $hasReply = $chk->get_result()->num_rows > 0;
                $chk->close();
            } else {
                $hasReply = ($processingStatusUi === 'processing_complete');
            }

            $responseStatusUi = $hasReply ? 'response_complete' : 'not_responded';
            $responseLabel = $hasReply ? 'Response Complete' : 'Not Responded';

            $inquiries[] = [
                'rowNum' => $rowNum--,
                'inquiryId' => $row['inquiryId'],
                'inquiryTitle' => $row['inquiryTitle'],
                'inquiryType' => $row['inquiryType'] ?? null,
                'processingStatus' => $processingStatusUi,
                'processingLabel' => $processingLabel,
                'responseStatus' => $responseStatusUi,
                'responseLabel' => $responseLabel,
                'createdAt' => $row['createdAt']
            ];
        }
        
        send_success_response([
            'inquiries' => $inquiries,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get inquiries: ' . $e->getMessage());
    }
}

function getInquiryDetail($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? '';
        
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        
        // inquiries 테이블 컬럼 확인
        $inquiryColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        
        $useSubjectColumn = in_array('subject', $inquiryColumns);
        $titleField = $useSubjectColumn ? 'subject' : 'inquiryTitle';
        $contentField = $useSubjectColumn ? 'content' : 'inquiryContent';
        
        // SELECT 절 동적 구성
        $selectFields = ['i.*'];
        
        // 별칭 추가 (일관성을 위해)
        if ($useSubjectColumn) {
            $selectFields[] = 'i.subject as inquiryTitle';
            $selectFields[] = 'i.content as inquiryContent';
        }
        
        // replyContent 컬럼이 있는지 확인
        $hasReplyContent = in_array('replycontent', $inquiryColumns);
        if ($hasReplyContent) {
            $selectFields[] = 'i.replyContent';
        }
        
            $sql = "
                SELECT 
                " . implode(', ', $selectFields) . ",
                    c.fName,
                    c.lName,
                    c.emailAddress,
                    c.contactNo
                FROM inquiries i
                LEFT JOIN client c ON i.accountId = c.accountId
                WHERE i.inquiryId = ?
            ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Inquiry not found', 404);
        }
        
        $inquiry = $result->fetch_assoc();
        
        // inquiryTitle, inquiryContent 필드가 없으면 subject, content에서 매핑
        if (!isset($inquiry['inquiryTitle']) && isset($inquiry['subject'])) {
            $inquiry['inquiryTitle'] = $inquiry['subject'];
        }
        if (!isset($inquiry['inquiryContent']) && isset($inquiry['content'])) {
            $inquiry['inquiryContent'] = $inquiry['content'];
        }
        
        // replyContent가 없으면 inquiry_replies 테이블에서 가져오기
        if (!$hasReplyContent || empty($inquiry['replyContent'])) {
            $replyCheck = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
            if ($replyCheck && $replyCheck->num_rows > 0) {
                // inquiry_replies 테이블 컬럼 확인
                $replyColumns = [];
                $replyColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_replies");
                if ($replyColumnResult) {
                    while ($col = $replyColumnResult->fetch_assoc()) {
                        $replyColumns[] = strtolower($col['Field']);
                    }
                }
                
                $replyContentField = in_array('replycontent', $replyColumns) ? 'replyContent' : (in_array('content', $replyColumns) ? 'content' : 'message');
                $replyIdField = in_array('replyid', $replyColumns) ? 'replyId' : 'id';
                
                $replySql = "
                    SELECT $replyContentField as replyContent, createdAt as repliedAt
                    FROM inquiry_replies
                    WHERE inquiryId = ?
                    ORDER BY createdAt DESC
                    LIMIT 1
                ";
                $replyStmt = $conn->prepare($replySql);
                $replyStmt->bind_param("i", $inquiryId);
                $replyStmt->execute();
                $replyResult = $replyStmt->get_result();
                
                if ($replyResult && $replyResult->num_rows > 0) {
                    $reply = $replyResult->fetch_assoc();
                    $inquiry['replyContent'] = $reply['replyContent'] ?? '';
                    $inquiry['repliedAt'] = $reply['repliedAt'] ?? null;
                }
            }
        }
        
        // 첨부파일 조회 (inquiry_attachments 테이블이 있는 경우)
        $attachmentCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
        if ($attachmentCheck && $attachmentCheck->num_rows > 0) {
            // 컬럼명 편차(fileName/name, filePath/path 등) 대응
            $aCols = [];
            $colRes = $conn->query("SHOW COLUMNS FROM inquiry_attachments");
            if ($colRes) {
                while ($c = $colRes->fetch_assoc()) $aCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
            }
            $fileNameCol = $aCols['filename'] ?? $aCols['name'] ?? 'fileName';
            $filePathCol = $aCols['filepath'] ?? $aCols['path'] ?? 'filePath';
            $fileSizeCol = $aCols['filesize'] ?? $aCols['size'] ?? null;
            $fileTypeCol = $aCols['filetype'] ?? $aCols['type'] ?? null;

            $attachmentSql = "SELECT "
                . " `{$fileNameCol}` AS fileName, `{$filePathCol}` AS filePath"
                . ($fileSizeCol ? ", `{$fileSizeCol}` AS fileSize" : ", NULL AS fileSize")
                . ($fileTypeCol ? ", `{$fileTypeCol}` AS fileType" : ", '' AS fileType")
                . " FROM inquiry_attachments WHERE inquiryId = ?";

            $attachmentStmt = $conn->prepare($attachmentSql);
            $attachmentStmt->bind_param("i", $inquiryId);
            $attachmentStmt->execute();
            $attachmentResult = $attachmentStmt->get_result();
            
            $attachments = [];
            while ($attachment = $attachmentResult->fetch_assoc()) {
                // filePath 저장형태(상대/절대/URL) 정규화 → 프론트는 이 값을 그대로 downloadInquiryAttachment에 넘긴다
                $attachment['filePath'] = normalize_inquiry_attachment_rel_path((string)($attachment['filePath'] ?? '')) ?: (string)($attachment['filePath'] ?? '');
                $attachments[] = $attachment;
            }
            $inquiry['attachments'] = $attachments;
        }
        
        send_success_response(['inquiry' => $inquiry]);
    } catch (Exception $e) {
        send_error_response('Failed to get inquiry detail: ' . $e->getMessage());
    }
}

function createInquiry($conn, $input) {
    try {
        $requiredFields = ['inquiryTitle', 'inquiryContent'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                send_error_response("Field '$field' is required");
            }
        }

        // Agent 인증: 문의 작성자는 반드시 로그인한 에이전트여야 함
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        
        // inquiries 테이블 컬럼명 확인 (inquiryTitle/inquiryContent 또는 subject/content)
        $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
        if ($tableCheck->num_rows === 0) {
            send_error_response('Inquiries table does not exist');
        }
        
        // 모든 컬럼 확인
        $inquiryColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        
        $useSubjectColumn = in_array('subject', $inquiryColumns);
        $useCategoryColumn = in_array('category', $inquiryColumns);
        $useInquiryTypeColumn = in_array('inquirytype', $inquiryColumns);
        $useInquiryNoColumn = in_array('inquiryno', $inquiryColumns);
        
        // 필드명 결정
        $titleField = $useSubjectColumn ? 'subject' : 'inquiryTitle';
        $contentField = $useSubjectColumn ? 'content' : 'inquiryContent';
        
        // INSERT 쿼리 구성
        $fields = [];
        $placeholders = [];
        $types = '';
        $params = [];
        
        // accountId는 로그인한 에이전트로 고정 (리스트에서도 동일한 기준으로 필터링됨)
        $accountId = (int)$agentAccountId;
        
        // accountId가 NULL을 허용하는지 확인
        $accountIdNullable = false;
        $accountIdColumnResult = $conn->query("SHOW COLUMNS FROM inquiries WHERE Field = 'accountId'");
        if ($accountIdColumnResult && $accountIdColumnResult->num_rows > 0) {
            $accountIdColumn = $accountIdColumnResult->fetch_assoc();
            $accountIdNullable = ($accountIdColumn['Null'] === 'YES');
        }
        if (empty($accountId) && !$accountIdNullable) {
            send_error_response('Agent login required', 401);
        }
        
        // accountId가 NULL이 아닌 경우 유효성 검증
        if ($accountId !== null) {
            $accountCheckStmt = $conn->prepare("SELECT accountId FROM accounts WHERE accountId = ?");
            $accountCheckStmt->bind_param("i", $accountId);
            $accountCheckStmt->execute();
            $accountCheckResult = $accountCheckStmt->get_result();
            
            if ($accountCheckResult->num_rows === 0) {
                send_error_response('Invalid accountId: account does not exist');
        }
        }
        
        // accountId 필드 추가
        if ($accountId !== null) {
            $fields[] = 'accountId';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = (int)$accountId;
        } elseif ($accountIdNullable) {
            // NULL을 허용하는 경우 NULL 사용
            $fields[] = 'accountId';
            $placeholders[] = 'NULL';
        }
        
        // 제목과 내용 필드 추가
        $fields[] = $titleField;
        $fields[] = $contentField;
        $placeholders[] = '?';
        $placeholders[] = '?';
        $types .= 'ss';
        $params[] = $input['inquiryTitle'] ?? $input['subject'] ?? '';
        $params[] = $input['inquiryContent'] ?? $input['content'] ?? '';
        
        // inquiryNo 생성 (필수인 경우)
        if ($useInquiryNoColumn) {
            $inquiryNo = $input['inquiryNo'] ?? null;
            if (empty($inquiryNo)) {
                // inquiryNo 자동 생성 (예: INQ20251106001 형식)
                $datePrefix = date('Ymd');
                $lastInquiryResult = $conn->query("SELECT inquiryNo FROM inquiries WHERE inquiryNo LIKE 'INQ{$datePrefix}%' ORDER BY inquiryNo DESC LIMIT 1");
                $lastNumber = 0;
                if ($lastInquiryResult && $lastInquiryResult->num_rows > 0) {
                    $lastInquiry = $lastInquiryResult->fetch_assoc();
                    $lastNo = $lastInquiry['inquiryNo'];
                    $lastNumber = (int)substr($lastNo, -3);
                }
                $nextNumber = $lastNumber + 1;
                $inquiryNo = 'INQ' . $datePrefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            }
            $fields[] = 'inquiryNo';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $inquiryNo;
        }
        
        // 타입 필드가 있으면 추가 (category 또는 inquiryType)
        if ($useCategoryColumn) {
            $fields[] = 'category';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $input['category'] ?? $input['inquiryType'] ?? 'general';
        } elseif ($useInquiryTypeColumn) {
            $fields[] = 'inquiryType';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $input['inquiryType'] ?? $input['category'] ?? 'general';
        }
        
        // status 필드 추가
        if (in_array('status', $inquiryColumns)) {
            // status 컬럼의 ENUM 값 확인
            $statusEnumValues = [];
            $statusColumnResult = $conn->query("SHOW COLUMNS FROM inquiries WHERE Field = 'status'");
            if ($statusColumnResult && $statusColumnResult->num_rows > 0) {
                $statusColumn = $statusColumnResult->fetch_assoc();
                $type = $statusColumn['Type'];
                // ENUM('value1','value2',...) 형식에서 값 추출
                if (preg_match("/^enum\s*\((.+)\)$/i", $type, $matches)) {
                    $enumValues = explode(',', $matches[1]);
                    foreach ($enumValues as $val) {
                        $statusEnumValues[] = trim($val, "'\"");
                    }
                }
            }
            
            // 기본값 결정 (pending이나 open 중 사용 가능한 값 선택)
            $defaultStatus = 'pending';
            if (!empty($statusEnumValues)) {
                if (in_array('pending', $statusEnumValues)) {
                    $defaultStatus = 'pending';
                } elseif (in_array('open', $statusEnumValues)) {
                    $defaultStatus = 'open';
                } elseif (in_array('in_progress', $statusEnumValues)) {
                    $defaultStatus = 'in_progress';
                } else {
                    // 첫 번째 ENUM 값 사용
                    $defaultStatus = $statusEnumValues[0];
                }
            }
            
            $fields[] = 'status';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $input['status'] ?? $defaultStatus;
        }
        
        // createdAt 필드 추가
        if (in_array('createdat', $inquiryColumns)) {
            $fields[] = 'createdAt';
            $placeholders[] = 'NOW()';
        }
        
        $sql = "INSERT INTO inquiries (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare SQL: ' . $conn->error);
        }
        
        mysqli_bind_params_by_ref($stmt, $types, $params);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute: ' . $stmt->error);
        }
        
        $inquiryId = $conn->insert_id;
        
        // 첨부파일 업로드 처리
        if (!empty($_FILES)) {
            $attachmentTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
            if ($attachmentTableCheck && $attachmentTableCheck->num_rows > 0) {
                // 업로드 디렉토리 설정
                // 실제 저장 경로: /var/www/html/uploads/inquiries/
                // __DIR__ = /var/www/html/admin/backend/api/
                // ../../../ = /var/www/html/
                $uploadDir = __DIR__ . '/../../../uploads/inquiries/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // inquiry_attachments 테이블 컬럼 확인
                $attachmentColumns = [];
                $attachmentColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_attachments");
                if ($attachmentColumnResult) {
                    while ($col = $attachmentColumnResult->fetch_assoc()) {
                        $attachmentColumns[] = strtolower($col['Field']);
                    }
                }
                
                // $_FILES에서 파일 처리
                foreach ($_FILES as $key => $file) {
                    // file_0, file_1 등의 형식으로 전송된 파일만 처리
                    if (strpos($key, 'file_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $fileName = 'inquiry_' . $inquiryId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $filePath = 'uploads/inquiries/' . $fileName;
                            $fileSize = $file['size'];
                            $fileType = $file['type'];
                            $originalFileName = $file['name'];
                            
                            // inquiry_attachments 테이블에 저장
                            $attachmentFields = [];
                            $attachmentPlaceholders = [];
                            $attachmentTypes = '';
                            $attachmentParams = [];
                            
                            // inquiryId 필드
                            if (in_array('inquiryid', $attachmentColumns)) {
                                $attachmentFields[] = 'inquiryId';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $inquiryId;
                            }
                            
                            // fileName 필드
                            if (in_array('filename', $attachmentColumns)) {
                                $attachmentFields[] = 'fileName';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $originalFileName;
                            } elseif (in_array('name', $attachmentColumns)) {
                                $attachmentFields[] = 'name';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $originalFileName;
                            }
                            
                            // filePath 필드
                            if (in_array('filepath', $attachmentColumns)) {
                                $attachmentFields[] = 'filePath';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $filePath;
                            } elseif (in_array('path', $attachmentColumns)) {
                                $attachmentFields[] = 'path';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $filePath;
                            }
                            
                            // fileSize 필드
                            if (in_array('filesize', $attachmentColumns)) {
                                $attachmentFields[] = 'fileSize';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $fileSize;
                            } elseif (in_array('size', $attachmentColumns)) {
                                $attachmentFields[] = 'size';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $fileSize;
                            }
                            
                            // fileType 필드
                            if (in_array('filetype', $attachmentColumns)) {
                                $attachmentFields[] = 'fileType';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $fileType;
                            } elseif (in_array('type', $attachmentColumns)) {
                                $attachmentFields[] = 'type';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $fileType;
                            }
                            
                            // uploadedBy 필드 (accountId 사용)
                            if (in_array('uploadedby', $attachmentColumns)) {
                                // uploadedBy 컬럼이 NULL을 허용하는지 확인
                                $uploadedByNullable = false;
                                $uploadedByColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_attachments WHERE Field = 'uploadedBy'");
                                if ($uploadedByColumnResult && $uploadedByColumnResult->num_rows > 0) {
                                    $uploadedByColumn = $uploadedByColumnResult->fetch_assoc();
                                    $uploadedByNullable = ($uploadedByColumn['Null'] === 'YES');
                                }
                                
                                // accountId 사용 (이미 createInquiry 함수에서 설정됨)
                                $uploadedByValue = $accountId ?? null;
                                
                                // NULL을 허용하지 않으면 기본값 설정
                                if ($uploadedByValue === null && !$uploadedByNullable) {
                                    // 기존 계정 찾기 (accountId 설정 로직과 동일)
                                    $agentAccountResult = $conn->query("SELECT accountId FROM accounts WHERE accountType = 'agent' LIMIT 1");
                                    if ($agentAccountResult && $agentAccountResult->num_rows > 0) {
                                        $agentAccount = $agentAccountResult->fetch_assoc();
                                        $uploadedByValue = $agentAccount['accountId'];
                                    } else {
                                        $anyAccountResult = $conn->query("SELECT accountId FROM accounts LIMIT 1");
                                        if ($anyAccountResult && $anyAccountResult->num_rows > 0) {
                                            $anyAccount = $anyAccountResult->fetch_assoc();
                                            $uploadedByValue = $anyAccount['accountId'];
                                        } else {
                                            $uploadedByValue = 0; // 최후의 수단
                                        }
                                    }
                                }
                                
                                // uploadedBy 필드 추가 (NULL 허용이거나 값이 있는 경우)
                                if ($uploadedByValue !== null || $uploadedByNullable) {
                                    $attachmentFields[] = 'uploadedBy';
                                    if ($uploadedByValue !== null) {
                                        $attachmentPlaceholders[] = '?';
                                        $attachmentTypes .= 'i';
                                        $attachmentParams[] = (int)$uploadedByValue;
                                    } else {
                                        $attachmentPlaceholders[] = 'NULL';
                                    }
                                }
                            }
                            
                            // createdAt 필드
                            if (in_array('createdat', $attachmentColumns)) {
                                $attachmentFields[] = 'createdAt';
                                $attachmentPlaceholders[] = 'NOW()';
                            }
                            
                            if (!empty($attachmentFields)) {
                                $attachmentSql = "INSERT INTO inquiry_attachments (" . implode(', ', $attachmentFields) . ") VALUES (" . implode(', ', $attachmentPlaceholders) . ")";
                                
                                // NOW()가 포함된 경우와 아닌 경우 분리 처리
                                $finalPlaceholders = [];
                                $finalParams = [];
                                $finalTypes = '';
                                
                                foreach ($attachmentPlaceholders as $idx => $placeholder) {
                                    if ($placeholder === 'NOW()') {
                                        $finalPlaceholders[] = 'NOW()';
                                    } else {
                                        $finalPlaceholders[] = '?';
                                        $finalParams[] = $attachmentParams[$idx];
                                        $finalTypes .= $attachmentTypes[$idx];
                                    }
                                }
                                
                                $attachmentSql = "INSERT INTO inquiry_attachments (" . implode(', ', $attachmentFields) . ") VALUES (" . implode(', ', $finalPlaceholders) . ")";
                                
                                $attachmentStmt = $conn->prepare($attachmentSql);
                                if ($attachmentStmt) {
                                    if (!empty($finalParams)) {
                                        mysqli_bind_params_by_ref($attachmentStmt, $finalTypes, $finalParams);
                                    }
                                    $attachmentStmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        send_success_response(['inquiryId' => $inquiryId], 'Inquiry created successfully');
        
    } catch (Exception $e) {
        send_error_response('Failed to create inquiry: ' . $e->getMessage());
    }
}

function updateInquiry($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? '';
        
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        
        $updates = [];
        $params = [];
        $types = '';
        
        // 컬럼명 확인
        $columnCheck = $conn->query("SHOW COLUMNS FROM inquiries LIKE 'subject'");
        $useSubjectColumn = ($columnCheck->num_rows > 0);
        
        if (isset($input['inquiryTitle'])) {
            $fieldName = $useSubjectColumn ? 'subject' : 'inquiryTitle';
            $updates[] = "$fieldName = ?";
            $params[] = $input['inquiryTitle'];
            $types .= 's';
        }
        
        if (isset($input['inquiryContent'])) {
            $fieldName = $useSubjectColumn ? 'content' : 'inquiryContent';
            $updates[] = "$fieldName = ?";
            $params[] = $input['inquiryContent'];
            $types .= 's';
        }
        
        if (isset($input['responseContent']) || isset($input['replyContent'])) {
            $replyContent = $input['responseContent'] ?? $input['replyContent'] ?? '';
            $updates[] = "replyContent = ?";
            $params[] = $replyContent;
            $types .= 's';
        }
        
        if (isset($input['status'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
            $types .= 's';
        }
        
        if (empty($updates)) {
            send_error_response('No fields to update');
        }
        
        $params[] = $inquiryId;
        $types .= 'i';
        
        $sql = "UPDATE inquiries SET " . implode(', ', $updates) . " WHERE inquiryId = ?";
        
        $stmt = $conn->prepare($sql);
        mysqli_bind_params_by_ref($stmt, $types, $params);
        $stmt->execute();
        
        // 첨부파일 업로드 처리 (새로 추가된 파일들)
        if (!empty($_FILES)) {
            $attachmentTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
            if ($attachmentTableCheck && $attachmentTableCheck->num_rows > 0) {
                // 업로드 디렉토리 설정
                // 실제 저장 경로: /var/www/html/uploads/inquiries/
                // __DIR__ = /var/www/html/admin/backend/api/
                // ../../../ = /var/www/html/
                $uploadDir = __DIR__ . '/../../../uploads/inquiries/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // inquiry_attachments 테이블 컬럼 확인
                $attachmentColumns = [];
                $attachmentColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_attachments");
                if ($attachmentColumnResult) {
                    while ($col = $attachmentColumnResult->fetch_assoc()) {
                        $attachmentColumns[] = strtolower($col['Field']);
                    }
                }
                
                // accountId 가져오기 (uploadedBy용)
                $accountId = $input['accountId'] ?? null;
                if (empty($accountId)) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $accountId = $_SESSION['accountId'] ?? null;
                }
                
                // $_FILES에서 파일 처리
                foreach ($_FILES as $key => $file) {
                    // file_0, file_1 등의 형식으로 전송된 파일만 처리
                    if (strpos($key, 'file_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $fileName = 'inquiry_' . $inquiryId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $filePath = 'uploads/inquiries/' . $fileName;
                            $fileSize = $file['size'];
                            $fileType = $file['type'];
                            $originalFileName = $file['name'];
                            
                            // inquiry_attachments 테이블에 저장
                            $attachmentFields = [];
                            $attachmentPlaceholders = [];
                            $attachmentTypes = '';
                            $attachmentParams = [];
                            
                            // inquiryId 필드
                            if (in_array('inquiryid', $attachmentColumns)) {
                                $attachmentFields[] = 'inquiryId';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $inquiryId;
                            }
                            
                            // fileName 필드
                            if (in_array('filename', $attachmentColumns)) {
                                $attachmentFields[] = 'fileName';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $originalFileName;
                            } elseif (in_array('name', $attachmentColumns)) {
                                $attachmentFields[] = 'name';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $originalFileName;
                            }
                            
                            // filePath 필드
                            if (in_array('filepath', $attachmentColumns)) {
                                $attachmentFields[] = 'filePath';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $filePath;
                            } elseif (in_array('path', $attachmentColumns)) {
                                $attachmentFields[] = 'path';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $filePath;
                            }
                            
                            // fileSize 필드
                            if (in_array('filesize', $attachmentColumns)) {
                                $attachmentFields[] = 'fileSize';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $fileSize;
                            } elseif (in_array('size', $attachmentColumns)) {
                                $attachmentFields[] = 'size';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $fileSize;
                            }
                            
                            // fileType 필드
                            if (in_array('filetype', $attachmentColumns)) {
                                $attachmentFields[] = 'fileType';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $fileType;
                            } elseif (in_array('type', $attachmentColumns)) {
                                $attachmentFields[] = 'type';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $fileType;
                            }
                            
                            // uploadedBy 필드 (accountId 사용)
                            if (in_array('uploadedby', $attachmentColumns)) {
                                $uploadedByNullable = false;
                                $uploadedByColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_attachments WHERE Field = 'uploadedBy'");
                                if ($uploadedByColumnResult && $uploadedByColumnResult->num_rows > 0) {
                                    $uploadedByColumn = $uploadedByColumnResult->fetch_assoc();
                                    $uploadedByNullable = ($uploadedByColumn['Null'] === 'YES');
                                }
                                
                                $uploadedByValue = $accountId ?? null;
                                
                                if ($uploadedByValue === null && !$uploadedByNullable) {
                                    $agentAccountResult = $conn->query("SELECT accountId FROM accounts WHERE accountType = 'agent' LIMIT 1");
                                    if ($agentAccountResult && $agentAccountResult->num_rows > 0) {
                                        $agentAccount = $agentAccountResult->fetch_assoc();
                                        $uploadedByValue = $agentAccount['accountId'];
                                    } else {
                                        $anyAccountResult = $conn->query("SELECT accountId FROM accounts LIMIT 1");
                                        if ($anyAccountResult && $anyAccountResult->num_rows > 0) {
                                            $anyAccount = $anyAccountResult->fetch_assoc();
                                            $uploadedByValue = $anyAccount['accountId'];
                                        } else {
                                            $uploadedByValue = 0;
                                        }
                                    }
                                }
                                
                                if ($uploadedByValue !== null || $uploadedByNullable) {
                                    $attachmentFields[] = 'uploadedBy';
                                    if ($uploadedByValue !== null) {
                                        $attachmentPlaceholders[] = '?';
                                        $attachmentTypes .= 'i';
                                        $attachmentParams[] = (int)$uploadedByValue;
                                    } else {
                                        $attachmentPlaceholders[] = 'NULL';
                                    }
                                }
                            }
                            
                            // createdAt 필드
                            if (in_array('createdat', $attachmentColumns)) {
                                $attachmentFields[] = 'createdAt';
                                $attachmentPlaceholders[] = 'NOW()';
                            }
                            
                            if (!empty($attachmentFields)) {
                                // NOW()와 NULL 처리
                                $finalPlaceholders = [];
                                $finalParams = [];
                                $finalTypes = '';
                                
                                foreach ($attachmentPlaceholders as $idx => $placeholder) {
                                    if ($placeholder === 'NOW()' || $placeholder === 'NULL') {
                                        $finalPlaceholders[] = $placeholder;
                                    } else {
                                        $finalPlaceholders[] = '?';
                                        $finalParams[] = $attachmentParams[$idx];
                                        $finalTypes .= $attachmentTypes[$idx];
                                    }
                                }
                                
                                $attachmentSql = "INSERT INTO inquiry_attachments (" . implode(', ', $attachmentFields) . ") VALUES (" . implode(', ', $finalPlaceholders) . ")";
                                
                                $attachmentStmt = $conn->prepare($attachmentSql);
                                if ($attachmentStmt) {
                                    if (!empty($finalParams)) {
                                        $attachmentStmt->bind_param($finalTypes, ...$finalParams);
                                    }
                                    $attachmentStmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        send_success_response([], 'Inquiry updated successfully');
        
    } catch (Exception $e) {
        send_error_response('Failed to update inquiry: ' . $e->getMessage());
    }
}

// ========== 헬퍼 함수들 ==========

function generateBookingId($conn) {
    $prefix = 'BK';
    $date = date('Ymd');
    
    // 오늘 날짜로 시작하는 예약 번호 개수 확인
    $sql = "SELECT COUNT(*) as count FROM bookings WHERE bookingId LIKE ?";
    $likePattern = $prefix . $date . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = (int)$row['count'] + 1;
    
    // 3자리 숫자로 포맷
    $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
    
    return $prefix . $date . $sequence;
}

function createNewCustomer($conn, $customerInfo) {
    // 이메일 중복 확인
    $emailCheckSql = "SELECT accountId FROM accounts WHERE emailAddress = ?";
    $emailCheckStmt = $conn->prepare($emailCheckSql);
    $emailCheckStmt->bind_param("s", $customerInfo['email']);
    $emailCheckStmt->execute();
    $emailResult = $emailCheckStmt->get_result();
    
    if ($emailResult->num_rows > 0) {
        $row = $emailResult->fetch_assoc();
        return $row['accountId'];
    }
    
    // accounts 테이블에 생성 (스키마 차이 흡수)
    $accountColumns = [];
    $accountColumnResult = $conn->query("SHOW COLUMNS FROM accounts");
    if ($accountColumnResult) {
        while ($col = $accountColumnResult->fetch_assoc()) {
            $accountColumns[] = strtolower($col['Field']);
        }
    }

    // password 또는 passwordHash 컬럼 확인
    $passwordColumn = in_array('password', $accountColumns, true) ? 'password' : 'passwordHash';
    $emailColumn = in_array('emailaddress', $accountColumns, true) ? 'emailAddress' : (in_array('email', $accountColumns, true) ? 'email' : 'emailAddress');
    $hasUsername = in_array('username', $accountColumns, true);
    $statusColumn = in_array('accountstatus', $accountColumns, true) ? 'accountStatus' : (in_array('status', $accountColumns, true) ? 'status' : null);
    $hasCreatedAt = in_array('createdat', $accountColumns, true);

    $email = trim((string)($customerInfo['email'] ?? ''));
    $firstName = trim((string)($customerInfo['firstName'] ?? ''));
    $lastName = trim((string)($customerInfo['lastName'] ?? ''));

    // username 생성 (NOT NULL 제약/기본값 없음 대응)
    $username = null;
    if ($hasUsername) {
        $base = '';
        if ($email !== '' && strpos($email, '@') !== false) {
            $base = explode('@', strtolower($email))[0];
        } elseif (($firstName . $lastName) !== '') {
            $base = strtolower($firstName . '.' . $lastName);
        } else {
            $base = 'user';
        }
        $base = preg_replace('/[^a-z0-9._-]/', '', $base);
        if ($base === '') $base = 'user';
        $base = substr($base, 0, 30);

        $username = $base;
        // 중복 시 suffix 추가
        $checkStmt = $conn->prepare("SELECT accountId FROM accounts WHERE username = ? LIMIT 1");
        if ($checkStmt) {
            for ($i = 0; $i < 20; $i++) {
                $checkStmt->bind_param('s', $username);
                $checkStmt->execute();
                $r = $checkStmt->get_result();
                if (!$r || $r->num_rows === 0) break;
                $suffix = (string)random_int(10, 9999);
                $username = substr($base, 0, max(1, 30 - strlen($suffix))) . $suffix;
            }
            $checkStmt->close();
        }
    }

    $passwordHash = password_hash($customerInfo['password'] ?? 'temp123', PASSWORD_DEFAULT);

    $accountFields = [$emailColumn, $passwordColumn, 'accountType'];
    $accountValues = [$email, $passwordHash, 'guest'];
    $accountTypes = 'sss';

    if ($hasUsername) {
        $accountFields[] = 'username';
        $accountValues[] = $username ?: $email;
        $accountTypes .= 's';
    }
    if ($statusColumn) {
        $accountFields[] = $statusColumn;
        $accountValues[] = 'active';
        $accountTypes .= 's';
    }
    if ($hasCreatedAt) {
        $accountFields[] = 'createdAt';
        // createdAt은 NOW()로 넣기 위해 placeholder 대신 SQL에 직접 사용
    }

    $placeholders = [];
    foreach ($accountFields as $f) {
        if ($f === 'createdAt') $placeholders[] = 'NOW()';
        else $placeholders[] = '?';
    }
    $accountSql = "INSERT INTO accounts (" . implode(', ', $accountFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $accountStmt = $conn->prepare($accountSql);
    if (!$accountStmt) {
        throw new Exception('Failed to prepare account insert: ' . $conn->error);
    }
    $accountStmt->bind_param($accountTypes, ...$accountValues);
    $accountStmt->execute();
    $accountId = $conn->insert_id;
    $accountStmt->close();

    // client 테이블에 생성 (컬럼 차이 흡수)
    $clientTableCheck = $conn->query("SHOW TABLES LIKE 'client'");
    if ($clientTableCheck && $clientTableCheck->num_rows > 0) {
        $clientColumns = [];
        $clientColumnResult = $conn->query("SHOW COLUMNS FROM client");
        if ($clientColumnResult) {
            while ($col = $clientColumnResult->fetch_assoc()) {
                $clientColumns[] = strtolower($col['Field']);
            }
        }

        $fields = [];
        $values = [];
        $types = '';

        // clientId (필수 환경 대비)
        if (in_array('clientid', $clientColumns, true)) {
            $fields[] = 'clientId';
            $values[] = 'CLI' . str_pad((string)$accountId, 6, '0', STR_PAD_LEFT);
            $types .= 's';
        }
        $fields[] = 'accountId';
        $values[] = $accountId;
        $types .= 'i';

        if (in_array('fname', $clientColumns, true)) { $fields[] = 'fName'; $values[] = $firstName; $types .= 's'; }
        if (in_array('lname', $clientColumns, true)) { $fields[] = 'lName'; $values[] = $lastName; $types .= 's'; }
        if (in_array('emailaddress', $clientColumns, true)) { $fields[] = 'emailAddress'; $values[] = $email; $types .= 's'; }
        $phone = trim((string)($customerInfo['phone'] ?? ''));
        if (in_array('contactno', $clientColumns, true)) { $fields[] = 'contactNo'; $values[] = $phone; $types .= 's'; }
        // agent 생성 고객은 B2B로 분류(가능한 스키마에만)
        if (in_array('clienttype', $clientColumns, true)) { $fields[] = 'clientType'; $values[] = 'Wholeseller'; $types .= 's'; }

        $createdAtCol = in_array('createdat', $clientColumns, true) ? 'createdAt' : null;
        if ($createdAtCol) {
            $fields[] = 'createdAt';
            // createdAt NOW()
        }

        $ph = [];
        foreach ($fields as $f) {
            if ($f === 'createdAt') $ph[] = 'NOW()';
            else $ph[] = '?';
        }
        $clientSql = "INSERT INTO client (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $ph) . ")";
        $clientStmt = $conn->prepare($clientSql);
        if ($clientStmt) {
            if ($types !== '') {
                mysqli_bind_params_by_ref($clientStmt, $types, $values);
            }
            $clientStmt->execute();
            $clientStmt->close();
        }
    }

    return $accountId;
}

function downloadCustomers($conn, $input) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        // 출력 버퍼 정리 (CSV 다운로드를 위해)
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // 검색 조건
        $search = $input['search'] ?? '';
        
        // 컬럼 존재 여부 확인 (옵셔널 컬럼들)
        $columnsCheck = $conn->query("SHOW COLUMNS FROM client");
        $clientColumns = [];
        while ($col = $columnsCheck->fetch_assoc()) {
            $clientColumns[] = strtolower($col['Field']);
        }
        
        // 옵셔널 컬럼들을 선택적으로 포함
        $emailCol = in_array('emailaddress', $clientColumns) ? 'COALESCE(a.emailAddress, c.emailAddress)' : 'a.emailAddress';
        $hasCreatedAt = in_array('createdat', $clientColumns);
        $createdAtCol = $hasCreatedAt ? 'COALESCE(a.createdAt, c.createdAt)' : 'a.createdAt';
        
        // 쿼리 구성
        $sql = "
            SELECT 
                c.accountId,
                c.fName,
                c.lName,
                $emailCol as emailAddress,
                c.contactNo,
                $createdAtCol as createdAt
            FROM client c
            LEFT JOIN accounts a ON c.accountId = a.accountId
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';

        // 에이전트 소속(회사) 필터 + B2B 등록 고객(clientType/clientRole) 필터
        $scope = function_exists('get_agent_scope') ? get_agent_scope($conn, $agentAccountId) : ['companyId' => null];
        $companyId = isset($scope['companyId']) ? $scope['companyId'] : null;
        if (in_array('companyid', $clientColumns, true) && !empty($companyId)) {
            $sql .= " AND c.companyId = ?";
            $params[] = (int)$companyId;
            $types .= 'i';
        }
        if (in_array('clienttype', $clientColumns, true)) {
            $sql .= " AND c.clientType = ?";
            $params[] = 'Wholeseller';
            $types .= 's';
        }
        if (in_array('clientrole', $clientColumns, true)) {
            $sql .= " AND c.clientRole = ?";
            $params[] = 'Sub-Agent';
            $types .= 's';
        }
        
        // 검색: "고객명 기준" (이메일/연락처 제외)
        if (!empty($search)) {
            $sql .= " AND (TRIM(CONCAT(COALESCE(c.fName,''),' ',COALESCE(c.lName,''))) LIKE ? OR TRIM(CONCAT(COALESCE(c.lName,''),' ',COALESCE(c.fName,''))) LIKE ? OR c.fName LIKE ? OR c.lName LIKE ?)";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $types .= 'ssss';
        }
        
        $sql .= " ORDER BY $createdAtCol DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        // CSV 헤더 설정
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM 추가 (Excel에서 한글 깨짐 방지)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // 헤더 작성 (escape 파라미터 추가로 deprecated 경고 방지)
        fputcsv($output, ['No', 'Customer Name', 'Email', 'Contact', 'Registration Date'], ',', '"', '');
        
        // 데이터 작성
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $fName = $row['fName'] ?? '';
            $lName = $row['lName'] ?? '';
            $customerName = trim($fName . ' ' . $lName);
            $email = $row['emailAddress'] ?? '';
            $contact = $row['contactNo'] ?? '';
            $regDate = $row['createdAt'] ?? '';
            
            // 연락처를 문자열로 강제 변환 (Excel에서 과학적 표기법 방지)
            // 앞에 작은따옴표를 추가하여 텍스트로 인식되도록 함
            if ($contact) {
                // 숫자로만 구성된 경우 작은따옴표 추가
                if (preg_match('/^\d+$/', $contact)) {
                    $contact = "'" . $contact;
                }
            }
            
            // 날짜 포맷팅 (Excel이 인식하는 형식: YYYY-MM-DD HH:MM:SS)
            if ($regDate) {
                try {
                    $dateObj = new DateTime($regDate);
                    $regDate = $dateObj->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // 날짜 파싱 실패 시 원본 값 유지
                }
            }
            
            // escape 파라미터 추가로 deprecated 경고 방지
            fputcsv($output, [
                $no++,
                $customerName,
                $email,
                $contact,
                $regDate
            ], ',', '"', '');
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log('downloadCustomers error: ' . $e->getMessage());
        error_log('downloadCustomers trace: ' . $e->getTraceAsString());
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to download customers: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function downloadCustomerSample() {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer_sample.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $headers = [
        'Customer First Name',
        'Customer Last Name',
        'Email',
        'Country Code',
        'Contact',
        'Password',
        'Note',
        'Traveler Title',
        'Traveler First Name',
        'Traveler Last Name',
        'Traveler Gender',
        'Traveler Age',
        'Traveler Birth (YYYY-MM-DD)',
        'Traveler Nationality',
        'Traveler Passport Number',
        'Traveler Passport Issue Date (YYYY-MM-DD)',
        'Traveler Passport Expiry Date (YYYY-MM-DD)'
    ];
    fputcsv($output, $headers, ',', '"', '');
    
    $sampleRows = [
        [
            'Juan',
            'Dela Cruz',
            'juan@example.com',
            '+63',
            '9171234567',
            'Temp123!',
            'VIP customer',
            'MR',
            'Juan',
            'Dela Cruz',
            'male',
            '35',
            '1990-05-12',
            'Philippines',
            'PP1234567',
            '2020-01-01',
            '2030-01-01'
        ],
        [
            'Maria',
            'Santos',
            'maria@example.com',
            '+82',
            '1012345678',
            'Temp123!',
            'Preferred traveler',
            'MS',
            'Maria',
            'Santos',
            'female',
            '29',
            '1996-08-23',
            'South Korea',
            'KP7654321',
            '2021-03-15',
            '2031-03-14'
        ]
    ];
    
    foreach ($sampleRows as $row) {
        fputcsv($output, $row, ',', '"', '');
    }
    
    fclose($output);
    exit;
}

function downloadReservations($conn, $input) {
    try {
        // 출력 버퍼 정리 (CSV 다운로드를 위해)
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        $where = [];
        $params = [];
        $types = '';

        // 보안: 다운로드도 로그인한 에이전트 본인 예약만
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $where[] = "b.agentId = ?";
        $params[] = (int)$agentAccountId;
        $types .= 'i';

        // 검색 조건
        if (!empty($input['search'])) {
            $where[] = "(p.packageName LIKE ? OR c.fName LIKE ? OR c.lName LIKE ? OR b.bookingId LIKE ?)";
            $searchTerm = '%' . $input['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        // 여행 시작일 필터
        if (!empty($input['travelStartDate'])) {
            $where[] = "b.departureDate >= ?";
            $params[] = $input['travelStartDate'];
            $types .= 's';
        }
        
        // 상태 필터
        if (!empty($input['status'])) {
            if ($input['status'] === 'pending_deposit') {
                $where[] = "b.paymentStatus = 'pending' AND b.bookingStatus = 'confirmed'";
            } elseif ($input['status'] === 'pending_balance') {
                $where[] = "b.paymentStatus = 'partial' AND b.bookingStatus = 'confirmed'";
            } else {
                $where[] = "b.bookingStatus = ?";
                $params[] = $input['status'];
                $types .= 's';
            }
        }
        
        // 검색 타입 필터
        if (!empty($input['searchType']) && !empty($input['search'])) {
            $searchTerm = '%' . $input['search'] . '%';
            // 기존 검색 조건 제거
            $where = array_filter($where, function($w) {
                return strpos($w, 'LIKE') === false;
            });
            $params = array_filter($params, function($p) use ($searchTerm) {
                return $p !== $searchTerm;
            });
            $types = preg_replace('/s{4}/', '', $types);
            
            // searchType 값 매핑 (HTML의 value와 API의 기대값 매핑)
            $searchType = $input['searchType'] ?? '';
            if ($searchType === 'product' || $searchType === 'packageName') {
                $where[] = "p.packageName LIKE ?";
                $params[] = $searchTerm;
                $types .= 's';
            } elseif ($searchType === 'customer' || $searchType === 'customerName') {
                $where[] = "(c.fName LIKE ? OR c.lName LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
            } elseif ($searchType === 'bookingId' || $searchType === 'bookingNumber') {
                $where[] = "b.bookingId LIKE ?";
                $params[] = $searchTerm;
                $types .= 's';
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // 쿼리 구성
        $sql = "
            SELECT 
                b.bookingId,
                p.packageName,
                b.departureDate,
                CONCAT(c.fName, ' ', c.lName) as reserverName,
                (b.adults + b.children + b.infants) as numPeople,
                b.bookingStatus,
                b.paymentStatus,
                b.createdAt
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN client c ON b.accountId = c.accountId
            $whereClause
            ORDER BY b.createdAt DESC
        ";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        // CSV 헤더 설정
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reservations_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM 추가 (Excel에서 한글 깨짐 방지)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // 헤더 작성
        fputcsv($output, ['No', 'Booking Number', 'Package Name', 'Travel Start Date', 'Reserver Name', 'Number of People', 'Status', 'Registration Date'], ',', '"', '');
        
        // 데이터 작성
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $bookingId = $row['bookingId'] ?? '';
            $packageName = $row['packageName'] ?? 'N/A';
            $departureDate = $row['departureDate'] ?? '';
            $reserverName = $row['reserverName'] ?? 'N/A';
            $numPeople = (int)($row['numPeople'] ?? 0);
            $bookingStatus = $row['bookingStatus'] ?? '';
            $paymentStatus = $row['paymentStatus'] ?? '';
            $createdAt = $row['createdAt'] ?? '';
            
            // 상태 배지 결정
            $statusBadge = getBookingStatusBadge($bookingStatus, $paymentStatus);
            $statusLabel = $statusBadge['label'] ?? $bookingStatus;
            
            // 날짜 포맷팅 (Excel이 인식하는 형식: YYYY-MM-DD)
            if ($departureDate) {
                try {
                    $dateObj = new DateTime($departureDate);
                    $departureDate = $dateObj->format('Y-m-d');
                } catch (Exception $e) {
                    // 날짜 파싱 실패 시 원본 값 유지
                }
            }
            
            // 등록일 포맷팅
            if ($createdAt) {
                try {
                    $dateObj = new DateTime($createdAt);
                    $createdAt = $dateObj->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // 날짜 파싱 실패 시 원본 값 유지
                }
            }
            
            // escape 파라미터 추가로 deprecated 경고 방지
            fputcsv($output, [
                $no++,
                $bookingId,
                $packageName,
                $departureDate,
                $reserverName,
                $numPeople,
                $statusLabel,
                $createdAt
            ], ',', '"', '');
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log('downloadReservations error: ' . $e->getMessage());
        error_log('downloadReservations trace: ' . $e->getTraceAsString());
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Failed to download reservations: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function normalizeCsvHeaderKey($header) {
    return strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($header)));
}

function getCsvValueByHeader($row, $headerIndex, $aliases) {
    foreach ((array)$aliases as $alias) {
        $normalized = normalizeCsvHeaderKey($alias);
        if (isset($headerIndex[$normalized])) {
            return trim($row[$headerIndex[$normalized]] ?? '');
        }
    }
    return '';
}

function normalizeCsvDateValue($value) {
    if (!$value) return '';
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }
    if (preg_match('/^\d{4}[\/\.]\d{1,2}[\/\.]\d{1,2}$/', $value)) {
        $parts = preg_split('/[\/\.]/', $value);
        return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
    }
    return '';
}

function splitCountryCodeFromContact($countryCode, $contact) {
    $countryCode = trim($countryCode ?? '');
    $contact = trim($contact ?? '');
    
    if (!$countryCode && preg_match('/^\s*(\+\d{1,4})\s*(.*)$/', $contact, $matches)) {
        $countryCode = $matches[1];
        $contact = trim($matches[2]);
    }
    
    $contact = preg_replace('/\s+/', '', $contact);
    
    return [$countryCode ?: '+63', $contact];
}

function batchUploadCustomers($conn) {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            send_error_response('파일 업로드에 실패했습니다.');
        }
        
        $file = $_FILES['file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // 파일 형식 검증
        if (!in_array($fileExt, ['csv', 'xlsx', 'xls'])) {
            send_error_response('CSV 또는 Excel 파일만 업로드 가능합니다.');
        }
        
        $rows = [];
        $headerIndex = [];
        if ($fileExt === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                send_error_response('파일을 읽을 수 없습니다.');
            }
            
            $firstLine = fgets($handle);
            if (substr($firstLine, 0, 3) === chr(0xEF).chr(0xBB).chr(0xBF)) {
                $firstLine = substr($firstLine, 3);
            }
            $headers = str_getcsv($firstLine);
            foreach ($headers as $idx => $header) {
                $headerIndex[normalizeCsvHeaderKey($header)] = $idx;
            }
            
            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row, fn($value) => trim((string)$value) !== ''))) {
                    continue;
                }
                $rows[] = $row;
            }
            fclose($handle);
        } else {
            send_error_response('Excel 파일 처리는 현재 지원하지 않습니다. CSV 파일을 사용해주세요.');
        }
        
        if (empty($rows)) {
            send_error_response('업로드된 파일에 유효한 데이터가 없습니다.');
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $customerFirstName = getCsvValueByHeader($row, $headerIndex, ['Customer First Name', 'First Name']);
            $customerLastName = getCsvValueByHeader($row, $headerIndex, ['Customer Last Name', 'Last Name']);
            $email = getCsvValueByHeader($row, $headerIndex, ['Email']);
            $countryCode = getCsvValueByHeader($row, $headerIndex, ['Country Code']);
            $contact = getCsvValueByHeader($row, $headerIndex, ['Contact', 'Phone']);
            $password = getCsvValueByHeader($row, $headerIndex, ['Password']);
            $note = getCsvValueByHeader($row, $headerIndex, ['Note', 'Memo']);
            $travelerTitle = getCsvValueByHeader($row, $headerIndex, ['Traveler Title']);
            $travelerFirstName = getCsvValueByHeader($row, $headerIndex, ['Traveler First Name']);
            $travelerLastName = getCsvValueByHeader($row, $headerIndex, ['Traveler Last Name']);
            $travelerGender = getCsvValueByHeader($row, $headerIndex, ['Traveler Gender']);
            $travelerNationality = getCsvValueByHeader($row, $headerIndex, ['Traveler Nationality']);
            $travelerPassportNo = getCsvValueByHeader($row, $headerIndex, ['Traveler Passport Number']);
            $travelerBirth = normalizeCsvDateValue(getCsvValueByHeader($row, $headerIndex, ['Traveler Birth', 'Traveler Birth (YYYY-MM-DD)']));
            $passportIssue = normalizeCsvDateValue(getCsvValueByHeader($row, $headerIndex, ['Traveler Passport Issue Date', 'Traveler Passport Issue Date (YYYY-MM-DD)']));
            $passportExpiry = normalizeCsvDateValue(getCsvValueByHeader($row, $headerIndex, ['Traveler Passport Expiry Date', 'Traveler Passport Expiry Date (YYYY-MM-DD)']));
            
            [$resolvedCountryCode, $resolvedContact] = splitCountryCodeFromContact($countryCode, $contact);
            
            $firstName = $customerFirstName ?: $travelerFirstName;
            $lastName = $customerLastName ?: $travelerLastName;
            
            if (empty($firstName) || empty($email) || empty($resolvedContact)) {
                $errors[] = "Row " . ($index + 2) . ": 필수 필드(이름, 이메일, 연락처)가 누락되었습니다.";
                $errorCount++;
                continue;
            }
            
            $customerInput = [
                'firstName' => $firstName,
                'lastName' => $lastName ?: '',
                'email' => $email,
                'phone' => $resolvedContact,
                'password' => $password ?: 'temp123',
                'countryCode' => $resolvedCountryCode,
                'memo' => $note,
                'travelerTitle' => strtoupper($travelerTitle ?: 'MR'),
                'travelerFirstName' => $travelerFirstName ?: $firstName,
                'travelerLastName' => $travelerLastName ?: $lastName,
                'travelerGender' => strtolower($travelerGender ?: 'male'),
                'travelerNationality' => $travelerNationality,
                'travelerPassportNo' => $travelerPassportNo,
                'travelerPassportIssue' => $passportIssue,
                'travelerPassportExpire' => $passportExpiry,
                'travelerBirth' => $travelerBirth
            ];
            
            try {
                createCustomerRecord($conn, $customerInput, []);
                $successCount++;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // 샘플 데이터를 반복 업로드하는 QA 시나리오에서 중복 이메일은 "스킵" 처리
                if (stripos($msg, 'Email already exists') !== false) {
                    $successCount++;
                    $errors[] = "Row " . ($index + 2) . ": 이미 존재하는 이메일이라 스킵되었습니다. ({$email})";
                    continue;
                }
                $errors[] = "Row " . ($index + 2) . ": " . $msg;
                $errorCount++;
            }
        }
        
        send_success_response([
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'errors' => $errors
        ], "총 {$successCount}건이 등록되었습니다. " . ($errorCount > 0 ? "{$errorCount}건의 오류가 발생했습니다." : ""));
        
    } catch (Exception $e) {
        send_error_response('일괄 등록 중 오류가 발생했습니다: ' . $e->getMessage());
    }
}

/**
 * 항공편 정보 조회
 */
function getFlightInfo($conn, $input) {
    $flightId = $input['flightId'] ?? null;
    
    if (!$flightId) {
        send_error_response('항공편 ID가 필요합니다.', 400);
        return;
    }
    
    try {
        $flightId = intval($flightId);
        
        // flight 테이블에서 항공편 상세 정보 조회
        $query = "SELECT 
                    f.flightId,
                    f.packageId,
                    f.origin,
                    f.destination,
                    f.flightName,
                    f.flightCode,
                    f.flightDepartureDate,
                    f.flightDepartureTime,
                    f.flightArrivalDate,
                    f.flightArrivalTime,
                    f.returnOrigin,
                    f.returnDestination,
                    f.returnFlightName,
                    f.returnFlightCode,
                    f.returnDepartureDate,
                    f.returnDepartureTime,
                    f.returnArrivalDate,
                    f.returnArrivalTime,
                    f.flightPrice,
                    f.landPrice,
                    f.availSeats,
                    f.is_active
                  FROM flight f
                  WHERE f.flightId = ? AND f.is_active = 1";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $flightId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('항공편 정보를 찾을 수 없습니다.', 404);
            return;
        }
        
        $flight = $result->fetch_assoc();
        
        // 예약된 좌석 수 계산
        // bookings 테이블에 flightId 컬럼이 있는지 확인
        $bookingsColumns = [];
        $bookingsColumnCheck = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingsColumnCheck) {
            while ($col = $bookingsColumnCheck->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }
        
        $hasFlightId = in_array('flightid', $bookingsColumns);
        $hasBookingStatus = in_array('bookingstatus', $bookingsColumns);
        
        if ($hasFlightId && $hasBookingStatus) {
            // bookings 테이블 사용
        $bookedQuery = "SELECT COUNT(*) as booked_seats 
                           FROM bookings 
                           WHERE flightId = ? AND bookingStatus IN ('confirmed', 'pending')";
        } else {
            // flightId가 없으면 0 반환
            $flight['bookedSeats'] = 0;
            $flight['remainingSeats'] = intval($flight['availSeats']);
            send_success_response($flight, '항공편 정보를 성공적으로 조회했습니다.');
            return;
        }
        
        $bookedStmt = $conn->prepare($bookedQuery);
        $bookedStmt->bind_param('i', $flightId);
        $bookedStmt->execute();
        $bookedResult = $bookedStmt->get_result();
        $bookedData = $bookedResult->fetch_assoc();
        
        $flight['bookedSeats'] = intval($bookedData['booked_seats'] ?? 0);
        $flight['remainingSeats'] = intval($flight['availSeats']) - $flight['bookedSeats'];
        
        send_success_response($flight, '항공편 정보를 성공적으로 조회했습니다.');
        
    } catch (Exception $e) {
        error_log('getFlightInfo Error: ' . $e->getMessage());
        error_log('getFlightInfo Trace: ' . $e->getTraceAsString());
        send_error_response('항공편 정보 조회 중 오류가 발생했습니다: ' . $e->getMessage());
    }
}

/**
 * 패키지 항공편 정보 조회 (package_flights 기반)
 * - product_availability(package_available_dates)에 flight_id가 없는 환경에서도 항공편 섹션을 노출하기 위한 fallback (dev_task id 62)
 */
function getPackageFlights($conn, $input) {
    $packageId = isset($input['packageId']) ? (int)$input['packageId'] : 0;
    $departureDate = trim((string)($input['departureDate'] ?? ''));
    $durationDays = isset($input['durationDays']) ? (int)$input['durationDays'] : 0;

    if ($packageId <= 0 || $departureDate === '') {
        send_error_response('packageId and departureDate are required', 400);
        return;
    }
    if ($durationDays <= 0) $durationDays = 5;

    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'package_flights'");
        if (!$tbl || $tbl->num_rows <= 0) {
            send_success_response([
                'outboundFlight' => null,
                'inboundFlight' => null
            ], 'Success');
            return;
        }

        $returnDate = $departureDate;
        try {
            $dt = new DateTime($departureDate);
            $dt->modify('+' . max(0, $durationDays - 1) . ' days');
            $returnDate = $dt->format('Y-m-d');
        } catch (Throwable $e) { /* ignore */ }

        $st = $conn->prepare("SELECT flight_type, flight_number, airline_name, departure_time, arrival_time, departure_point, destination FROM package_flights WHERE package_id = ? ORDER BY flight_type ASC LIMIT 10");
        if (!$st) {
            send_error_response('Failed to prepare query', 500);
            return;
        }
        $st->bind_param('i', $packageId);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($r = $rs->fetch_assoc()) $rows[] = $r;
        $st->close();

        $mk = function($row, $baseDate) {
            if (!$row) return null;
            $flightNo = trim((string)($row['flight_number'] ?? ''));
            if ($flightNo === '') return null;
            $depTime = trim((string)($row['departure_time'] ?? ''));
            $arrTime = trim((string)($row['arrival_time'] ?? ''));
            return [
                'flightNumber' => $flightNo,
                'airlineName' => trim((string)($row['airline_name'] ?? '')),
                'departureDateTime' => ($baseDate && $depTime) ? ($baseDate . ' ' . $depTime) : ($baseDate ?: null),
                'arrivalDateTime' => ($baseDate && $arrTime) ? ($baseDate . ' ' . $arrTime) : ($baseDate ?: null),
                'departureAirport' => (string)($row['departure_point'] ?? ''),
                'arrivalAirport' => (string)($row['destination'] ?? ''),
            ];
        };

        $outbound = null;
        $inbound = null;
        foreach ($rows as $r) {
            $t = strtolower(trim((string)($r['flight_type'] ?? '')));
            if ($t === 'departure' && $outbound === null) {
                $outbound = $mk($r, $departureDate);
            } elseif (($t === 'return' || $t === 'inbound') && $inbound === null) {
                $inbound = $mk($r, $returnDate);
            }
        }

        send_success_response([
            'outboundFlight' => $outbound,
            'inboundFlight' => $inbound
        ], 'Success');
    } catch (Exception $e) {
        send_error_response('Failed to get package flights: ' . $e->getMessage(), 500);
    }
}

// 예약 취소
function cancelReservation($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $reason = $input['reason'] ?? '';

        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 바로 cancelled 상태로 변경 (pending_update 거치지 않음)
        $sql = "UPDATE bookings SET bookingStatus = 'cancelled', cancelledAt = NOW() WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();

        // Google Sheets APP 동기화
        try {
            require_once __DIR__ . '/../../../backend/lib/google_sheets.php';
            gs_sync_by_booking_id($conn, $bookingId);
        } catch (Exception $gsEx) {
            error_log("Sheets sync failed (agent-cancel): " . $gsEx->getMessage());
        }

        // 예약 이력 추가 (사유 포함)
        $historyMsg = '예약 취소';
        if (!empty($reason)) {
            $historyMsg .= ' - ' . $reason;
        }
        addReservationHistory($conn, $bookingId, $historyMsg);

        send_success_response([], 'Reservation cancelled successfully');
    } catch (Exception $e) {
        send_error_response('Failed to cancel reservation: ' . $e->getMessage());
    }
}

// ========== Product Edit (승인 필요 플로우) ==========

// 증빙 파일 업로드 (레거시 - 현행 컬럼명 사용으로 통일)
function uploadProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $type = $input['type'] ?? ''; // 'deposit'/'down' or 'balance'

        if (empty($bookingId) || empty($type)) {
            send_error_response('Booking ID and type are required');
        }

        // 레거시 타입 매핑: deposit -> down
        if ($type === 'deposit') $type = 'down';

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            send_error_response('File upload failed');
        }

        // 보안: agent 또는 admin 로그인 필요
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        $isAdmin = !empty($adminAccountId);

        if (empty($agentAccountId) && empty($adminAccountId)) {
            send_error_response('Login required', 401);
        }

        // 컬럼명 매핑 (통일된 현행 컬럼 사용)
        $columnMap = [
            'down' => ['file' => 'downPaymentFile', 'fileName' => 'downPaymentFileName'],
            'balance' => ['file' => 'balanceFile', 'fileName' => 'balanceFileName']
        ];
        if (!isset($columnMap[$type])) {
            send_error_response('Invalid type. Must be: down or balance');
        }
        $cols = $columnMap[$type];

        // Admin은 모든 예약에 접근 가능, Agent는 본인 예약만 (accountId 또는 agentId로 매칭)
        if ($isAdmin) {
            $chk = $conn->prepare("SELECT bookingStatus, paymentStatus, COALESCE(downPaymentFile,'') AS downPaymentFile, COALESCE(balanceFile,'') AS balanceFile FROM bookings WHERE bookingId = ? LIMIT 1");
            $chk->bind_param("s", $bookingId);
        } else {
            // Agent는 accountId로 직접 매칭되거나, agentId가 agent 테이블의 id와 매칭되는 경우 접근 가능
            $chk = $conn->prepare("SELECT bookingStatus, paymentStatus, COALESCE(downPaymentFile,'') AS downPaymentFile, COALESCE(balanceFile,'') AS balanceFile FROM bookings WHERE bookingId = ? AND (accountId = ? OR agentId IN (SELECT id FROM agent WHERE accountId = ?)) LIMIT 1");
            $chk->bind_param("sii", $bookingId, $agentAccountId, $agentAccountId);
        }
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$row) send_error_response('Reservation not found or access denied', 404);
        $bs = normalizeBookingStatus($row['bookingStatus'] ?? '');
        $ps = normalizePaymentStatus($row['paymentStatus'] ?? '');
        $hasDown = !empty(trim((string)$row['downPaymentFile']));
        $hasBal = !empty(trim((string)$row['balanceFile']));

        // 허용: 입금대기/확정 상태에서만, 단계별로 업로드
        $allowedBookingStatuses = ['pending', 'confirmed', 'waiting_down_payment', 'waiting_second_payment', 'waiting_balance'];
        if (!in_array($bs, $allowedBookingStatuses, true) || !in_array($ps, ['pending','partial',''], true)) {
            send_error_response('File upload is not allowed in this status', 403);
        }
        if ($type === 'down') {
            if ($hasDown) send_error_response('Down payment proof already uploaded', 400);
        } else {
            if (!$hasDown) send_error_response('Down payment proof is required before balance proof', 400);
            if ($hasBal) send_error_response('Balance proof already uploaded', 400);
        }

        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/../../../uploads/proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'proof_' . $type . '_' . $bookingId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $filePath = 'uploads/proofs/' . $fileName;

            // 기존 파일 삭제
            $oldFileSql = "SELECT {$cols['file']} FROM bookings WHERE bookingId = ?";
            $oldFileStmt = $conn->prepare($oldFileSql);
            $oldFileStmt->bind_param("s", $bookingId);
            $oldFileStmt->execute();
            $oldFileResult = $oldFileStmt->get_result();
            if ($oldFileRow = $oldFileResult->fetch_assoc()) {
                $oldFilePath = $oldFileRow[$cols['file']];
                if (!empty($oldFilePath)) {
                    $oldFilePathClean = str_replace('/smart-travel2/', '/', $oldFilePath);
                    $oldFilePathClean = ltrim($oldFilePathClean, '/');
                    $oldFileAbsolutePath = __DIR__ . '/../../../' . $oldFilePathClean;
                    if (file_exists($oldFileAbsolutePath)) {
                        unlink($oldFileAbsolutePath);
                    }
                }
            }

            $updateSql = "UPDATE bookings SET {$cols['file']} = ?, {$cols['fileName']} = ? WHERE bookingId = ?";
            $updateStmt = $conn->prepare($updateSql);
            $originalFileName = $file['name'];
            $updateStmt->bind_param("sss", $filePath, $originalFileName, $bookingId);
            $updateStmt->execute();

            // 예약 이력 추가
            addReservationHistory($conn, $bookingId, ($type === 'down' ? 'Down Payment' : 'Balance') . ' 증빙 파일 업로드: ' . $file['name']);

            send_success_response(['filePath' => $filePath], 'File uploaded successfully');
        } else {
            send_error_response('Failed to move uploaded file');
        }
    } catch (Exception $e) {
        send_error_response('Failed to upload proof file: ' . $e->getMessage());
    }
}

// 증빙 파일 다운로드 (레거시 - 현행 컬럼명 사용으로 통일)
function downloadProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $type = $input['type'] ?? ''; // 'deposit'/'down' or 'balance'

        if (empty($bookingId) || empty($type)) {
            send_error_response('Booking ID and type are required');
        }

        // 레거시 타입 매핑: deposit -> down
        if ($type === 'deposit') $type = 'down';

        // 보안: agent 전용(본인 예약만)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // 컬럼명 매핑 (통일된 현행 컬럼 사용)
        $columnMap = [
            'down' => 'downPaymentFile',
            'balance' => 'balanceFile'
        ];
        if (!isset($columnMap[$type])) {
            send_error_response('Invalid type. Must be: down or balance');
        }
        $columnName = $columnMap[$type];

        $sql = "SELECT $columnName FROM bookings WHERE bookingId = ? AND accountId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $bookingId, $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $filePath = $row[$columnName];
            if (!empty($filePath)) {
                $filePathClean = str_replace('/smart-travel2/', '/', $filePath);
                $filePathClean = ltrim($filePathClean, '/');
                $absolutePath = __DIR__ . '/../../../' . $filePathClean;

                if (file_exists($absolutePath)) {
                    while (ob_get_level() > 0) { @ob_end_clean(); }
                    header('Content-Type: application/octet-stream');
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                    header('Content-Length: ' . filesize($absolutePath));
                    readfile($absolutePath);
                    exit;
                }
            }
        }

        send_error_response('File not found');
    } catch (Exception $e) {
        send_error_response('Failed to download proof file: ' . $e->getMessage());
    }
}

// 증빙 파일 삭제 (레거시 - 현행 컬럼명 사용으로 통일)
function removeProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $type = $input['type'] ?? ''; // 'deposit'/'down' or 'balance'

        if (empty($bookingId) || empty($type)) {
            send_error_response('Booking ID and type are required');
        }

        // 레거시 타입 매핑: deposit -> down
        if ($type === 'deposit') $type = 'down';

        // 보안: agent 전용(본인 예약만) + 상태별 삭제 허용
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // 컬럼명 매핑 (통일된 현행 컬럼 사용)
        $columnMap = [
            'down' => ['file' => 'downPaymentFile', 'fileName' => 'downPaymentFileName'],
            'balance' => ['file' => 'balanceFile', 'fileName' => 'balanceFileName']
        ];
        if (!isset($columnMap[$type])) {
            send_error_response('Invalid type. Must be: down or balance');
        }
        $cols = $columnMap[$type];

        $sql = "SELECT bookingStatus, paymentStatus, {$cols['file']} FROM bookings WHERE bookingId = ? AND accountId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $bookingId, $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $bs = normalizeBookingStatus($row['bookingStatus'] ?? '');
            if (in_array($bs, ['cancelled','completed','refunded'], true)) {
                send_error_response('File delete is not allowed in this status', 403);
            }
            $filePath = $row[$cols['file']] ?? '';
            if (!empty($filePath)) {
                $filePathClean = str_replace('/smart-travel2/', '/', $filePath);
                $filePathClean = ltrim($filePathClean, '/');
                $absolutePath = __DIR__ . '/../../../' . $filePathClean;

                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }
        }

        $updateSql = "UPDATE bookings SET {$cols['file']} = NULL, {$cols['fileName']} = NULL WHERE bookingId = ? AND accountId = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $bookingId, $agentAccountId);
        $updateStmt->execute();

        // 예약 이력 추가
        addReservationHistory($conn, $bookingId, ($type === 'down' ? 'Down Payment' : 'Balance') . ' 증빙 파일 삭제');

        send_success_response([], 'File removed successfully');
    } catch (Exception $e) {
        send_error_response('Failed to remove proof file: ' . $e->getMessage());
    }
}

// ========== 3단계 결제 증빙 파일 관리 함수 ==========

// 3단계 결제 증빙 파일 업로드
function uploadPaymentProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $paymentType = $input['paymentType'] ?? ''; // 'down', 'second', 'balance'

        if (empty($bookingId) || empty($paymentType)) {
            send_error_response('Booking ID and payment type are required');
        }

        if (!in_array($paymentType, ['down', 'second', 'balance', 'full'])) {
            send_error_response('Invalid payment type. Must be: down, second, balance, or full');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            send_error_response('File upload failed');
        }

        // 세션 확인
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        $isAdmin = !empty($adminAccountId);

        if (empty($agentAccountId) && empty($adminAccountId)) {
            send_error_response('Login required', 401);
        }

        // 컬럼 이름 매핑
        $columnMap = [
            'down' => ['file' => 'downPaymentFile', 'fileName' => 'downPaymentFileName', 'confirmedAt' => 'downPaymentConfirmedAt'],
            'second' => ['file' => 'advancePaymentFile', 'fileName' => 'advancePaymentFileName', 'confirmedAt' => 'advancePaymentConfirmedAt'],
            'balance' => ['file' => 'balanceFile', 'fileName' => 'balanceFileName', 'confirmedAt' => 'balanceConfirmedAt'],
            'full' => ['file' => 'fullPaymentFile', 'fileName' => 'fullPaymentFileName', 'confirmedAt' => 'fullPaymentConfirmedAt']
        ];
        $cols = $columnMap[$paymentType];

        // 예약 확인 및 권한 체크
        if ($isAdmin) {
            // 관리자는 모든 예약에 접근 가능
            $chk = $conn->prepare("SELECT bookingId, bookingStatus, departureDate, paymentType AS bookingPaymentType, downPaymentConfirmedAt, advancePaymentConfirmedAt, balanceConfirmedAt FROM bookings WHERE bookingId = ? LIMIT 1");
            $chk->bind_param("s", $bookingId);
        } else {
            // 에이전트는 자신이 담당하는 예약에만 접근 가능 (accountId 또는 agentId 매칭)
            $chk = $conn->prepare("SELECT bookingId, bookingStatus, departureDate, paymentType AS bookingPaymentType, downPaymentConfirmedAt, advancePaymentConfirmedAt, balanceConfirmedAt FROM bookings WHERE bookingId = ? AND (accountId = ? OR agentId IN (SELECT id FROM agent WHERE accountId = ?)) LIMIT 1");
            $chk->bind_param("sii", $bookingId, $agentAccountId, $agentAccountId);
        }
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row) {
            send_error_response('Reservation not found or access denied', 404);
        }

        // 특정 상태에서는 파일 업로드 차단
        $blockedStatuses = ['cancelled', 'pending', 'pending_update'];
        if (in_array($row['bookingStatus'] ?? '', $blockedStatuses, true)) {
            send_error_response('Cannot upload payment proof in current reservation status', 403);
        }

        // 단계별 검증: Second는 Down 확인 후, Balance는 Second 확인 후 (middle 결제는 second 단계 없음)
        $bookingPaymentType = $row['bookingPaymentType'] ?? '';
        if ($paymentType === 'second' && empty($row['downPaymentConfirmedAt'])) {
            send_error_response('Second Payment can only be uploaded after Down Payment is confirmed', 403);
        }
        if ($paymentType === 'balance') {
            if ($bookingPaymentType === 'middle') {
                // middle 결제: down(middle) 확인 후 balance 업로드 가능
                if (empty($row['downPaymentConfirmedAt'])) {
                    send_error_response('Balance can only be uploaded after Middle Payment is confirmed', 403);
                }
            } else {
                // staged 결제: second 확인 후 balance 업로드 가능
                if (empty($row['advancePaymentConfirmedAt'])) {
                    send_error_response('Balance can only be uploaded after Second Payment is confirmed', 403);
                }
            }
        }

        // 파일 처리
        $file = $_FILES['file'];
        $uploadDirMap = [
            'down' => __DIR__ . '/../../../uploads/payment/down/',
            'second' => __DIR__ . '/../../../uploads/payment/second/',
            'balance' => __DIR__ . '/../../../uploads/payment/balance/',
            'full' => __DIR__ . '/../../../uploads/payment/full/'
        ];
        $uploadDir = $uploadDirMap[$paymentType];

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $originalFileName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            send_error_response('Invalid file type. Allowed: jpg, jpeg, png, gif, pdf');
        }

        $newFileName = $paymentType . '_' . $bookingId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            send_error_response('Failed to save uploaded file');
        }

        $relativePathMap = [
            'down' => 'uploads/payment/down/',
            'second' => 'uploads/payment/second/',
            'balance' => 'uploads/payment/balance/',
            'full' => 'uploads/payment/full/'
        ];
        $filePath = $relativePathMap[$paymentType] . $newFileName;

        // DB 업데이트 (파일 업로드 시 상태를 checking_*로 자동 전환)
        $statusMap = [
            'down' => 'checking_down_payment',
            'second' => 'checking_second_payment',
            'balance' => 'checking_balance',
            'full' => 'checking_full_payment'
        ];
        $newStatus = $statusMap[$paymentType];

        // Update new booking_payments table
        $now = date('Y-m-d H:i:s');
        upsertPayment($conn, $bookingId, $paymentType, [
            'filePath' => $filePath,
            'fileName' => $originalFileName,
            'uploadedAt' => $now,
            'status' => 'uploaded'
        ]);

        // Update legacy bookings columns for backward compatibility
        $uploadedAtCol = str_replace('File', 'UploadedAt', $cols['file']);
        $updateSql = "UPDATE bookings SET {$cols['file']} = ?, {$cols['fileName']} = ?, {$uploadedAtCol} = ?, bookingStatus = ? WHERE bookingId = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("sssss", $filePath, $originalFileName, $now, $newStatus, $bookingId);
        $stmt->execute();
        $stmt->close();

        // waiting_cancelled 상태에서 결제증빙 업로드 시 기한 연장
        $extendedSteps = [];
        if (($row['bookingStatus'] ?? '') === 'waiting_cancelled') {
            $daysUntilDep = !empty($row['departureDate'])
                ? (int)(new DateTime())->diff(new DateTime($row['departureDate']))->format('%r%a')
                : 999;

            $payments = getPaymentsByBookingId($conn, $bookingId);

            if ($daysUntilDep <= 31) {
                // 출발 31일 이내: 모든 미확인 결제 단계 기한을 내일(+1일)로 설정
                $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
                $legacyDueDateMap = [
                    'down' => 'downPaymentDueDate',
                    'second' => 'advancePaymentDueDate',
                    'balance' => 'balanceDueDate',
                    'full' => 'fullPaymentDueDate',
                    'middle' => 'downPaymentDueDate',
                    'middle_balance' => 'balanceDueDate'
                ];
                foreach ($payments as $p) {
                    $step = $p['paymentStep'];
                    if ($p['status'] === 'confirmed') continue;
                    upsertPayment($conn, $bookingId, $step, ['dueDate' => $tomorrowDate]);
                    $legacyCol = $legacyDueDateMap[$step] ?? null;
                    if ($legacyCol) {
                        $legStmt = $conn->prepare("UPDATE bookings SET $legacyCol = ? WHERE bookingId = ?");
                        $legStmt->bind_param('ss', $tomorrowDate, $bookingId);
                        $legStmt->execute();
                        $legStmt->close();
                    }
                    $extendedSteps[] = $step;
                }
            } elseif ($paymentType === 'down') {
                // 기존 로직: down 업로드 시 동일 기한인 나머지 단계 +3일 연장
                $uploadedDueDate = null;
                foreach ($payments as $p) {
                    if ($p['paymentStep'] === 'down' || $p['paymentStep'] === 'middle') {
                        $uploadedDueDate = $p['dueDate'] ?? null;
                        break;
                    }
                }
                if ($uploadedDueDate) {
                    $newDueDate = date('Y-m-d', strtotime($uploadedDueDate . ' +3 days'));
                    $legacyDueDateMap = [
                        'second' => 'advancePaymentDueDate',
                        'balance' => 'balanceDueDate',
                        'middle_balance' => 'balanceDueDate'
                    ];
                    foreach ($payments as $p) {
                        $step = $p['paymentStep'];
                        if ($step === 'down' || $step === 'middle' || $step === 'full') continue;
                        if ($p['status'] === 'confirmed') continue;
                        if (($p['dueDate'] ?? '') === $uploadedDueDate) {
                            upsertPayment($conn, $bookingId, $step, ['dueDate' => $newDueDate]);
                            $legacyCol = $legacyDueDateMap[$step] ?? null;
                            if ($legacyCol) {
                                $legStmt = $conn->prepare("UPDATE bookings SET $legacyCol = ? WHERE bookingId = ?");
                                $legStmt->bind_param('ss', $newDueDate, $bookingId);
                                $legStmt->execute();
                                $legStmt->close();
                            }
                            $extendedSteps[] = $step;
                        }
                    }
                }
            }
        }

        // 이력 추가
        $typeLabels = ['down' => 'Down Payment', 'second' => 'Second Payment', 'balance' => 'Balance', 'full' => 'Full Payment'];
        $historyMsg = $typeLabels[$paymentType] . ' proof file uploaded: ' . $originalFileName;
        if (!empty($extendedSteps)) {
            $daysUntilDep = !empty($row['departureDate'])
                ? (int)(new DateTime())->diff(new DateTime($row['departureDate']))->format('%r%a')
                : 999;
            if ($daysUntilDep <= 31) {
                $historyMsg .= ' | All pending due dates set to tomorrow (+1 day) for: ' . implode(', ', $extendedSteps);
            } else {
                $historyMsg .= ' | Due dates extended +3 days for: ' . implode(', ', $extendedSteps);
            }
        }
        addReservationHistory($conn, $bookingId, $historyMsg);

        send_success_response(['filePath' => $filePath, 'extendedSteps' => $extendedSteps], 'File uploaded successfully');
    } catch (Exception $e) {
        send_error_response('Failed to upload payment proof file: ' . $e->getMessage());
    }
}

// 3단계 결제 증빙 파일 다운로드
function downloadPaymentProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $paymentType = $input['paymentType'] ?? '';

        if (empty($bookingId) || empty($paymentType)) {
            send_error_response('Booking ID and payment type are required');
        }

        if (!in_array($paymentType, ['down', 'second', 'balance', 'full'])) {
            send_error_response('Invalid payment type');
        }

        // 세션 확인
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        $isAdmin = !empty($adminAccountId);

        if (empty($agentAccountId) && empty($adminAccountId)) {
            send_error_response('Login required', 401);
        }

        // First check access rights
        if ($isAdmin) {
            $sql = "SELECT bookingId FROM bookings WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $bookingId);
        } else {
            $sql = "SELECT bookingId FROM bookings WHERE bookingId = ? AND (accountId = ? OR agentId IN (SELECT id FROM agent WHERE accountId = ?))";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $bookingId, $agentAccountId, $agentAccountId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            send_error_response('Reservation not found or access denied', 404);
        }

        // Try to get file from new booking_payments table first
        $payment = getPaymentByStep($conn, $bookingId, $paymentType);
        $filePath = null;

        if ($payment && !empty($payment['filePath'])) {
            $filePath = $payment['filePath'];
        } else {
            // Fallback to legacy columns
            $columnMap = [
                'down' => 'downPaymentFile',
                'second' => 'advancePaymentFile',
                'balance' => 'balanceFile',
                'full' => 'fullPaymentFile'
            ];
            $fileColumn = $columnMap[$paymentType];

            $legacyStmt = $conn->prepare("SELECT $fileColumn FROM bookings WHERE bookingId = ?");
            $legacyStmt->bind_param("s", $bookingId);
            $legacyStmt->execute();
            $legacyResult = $legacyStmt->get_result();
            $legacyRow = $legacyResult->fetch_assoc();
            $legacyStmt->close();

            if ($legacyRow && !empty($legacyRow[$fileColumn])) {
                $filePath = $legacyRow[$fileColumn];
            }
        }

        if (empty($filePath)) {
            send_error_response('File not found', 404);
        }
        $absolutePath = __DIR__ . '/../../../' . ltrim($filePath, '/');

        if (!file_exists($absolutePath)) {
            send_error_response('File not found on server', 404);
        }

        // 버퍼 정리 및 파일 다운로드
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($absolutePath));
        readfile($absolutePath);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download file: ' . $e->getMessage());
    }
}

// 3단계 결제 증빙 파일 삭제
function deletePaymentProofFile($conn, $input) {
    try {
        $bookingId = $input['bookingId'] ?? '';
        $paymentType = $input['paymentType'] ?? '';

        if (empty($bookingId) || empty($paymentType)) {
            send_error_response('Booking ID and payment type are required');
        }

        if (!in_array($paymentType, ['down', 'second', 'balance', 'full'])) {
            send_error_response('Invalid payment type');
        }

        // 세션 확인
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        $adminAccountId = $_SESSION['admin_accountId'] ?? null;
        $isAdmin = !empty($adminAccountId);

        if (empty($agentAccountId) && empty($adminAccountId)) {
            send_error_response('Login required', 401);
        }

        $columnMap = [
            'down' => ['file' => 'downPaymentFile', 'fileName' => 'downPaymentFileName', 'confirmedAt' => 'downPaymentConfirmedAt'],
            'second' => ['file' => 'advancePaymentFile', 'fileName' => 'advancePaymentFileName', 'confirmedAt' => 'advancePaymentConfirmedAt'],
            'balance' => ['file' => 'balanceFile', 'fileName' => 'balanceFileName', 'confirmedAt' => 'balanceConfirmedAt'],
            'full' => ['file' => 'fullPaymentFile', 'fileName' => 'fullPaymentFileName', 'confirmedAt' => 'fullPaymentConfirmedAt']
        ];
        $cols = $columnMap[$paymentType];

        // 예약 확인
        if ($isAdmin) {
            $sql = "SELECT bookingId FROM bookings WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $bookingId);
        } else {
            $sql = "SELECT bookingId FROM bookings WHERE bookingId = ? AND (accountId = ? OR agentId IN (SELECT id FROM agent WHERE accountId = ?))";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $bookingId, $agentAccountId, $agentAccountId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            send_error_response('Reservation not found or access denied', 404);
        }

        // Check from new booking_payments table first
        $payment = getPaymentByStep($conn, $bookingId, $paymentType);
        $filePath = null;
        $isConfirmed = false;

        if ($payment) {
            $filePath = $payment['filePath'];
            $isConfirmed = ($payment['status'] === 'confirmed');
        } else {
            // Fallback to legacy columns
            $legacyStmt = $conn->prepare("SELECT {$cols['file']}, {$cols['confirmedAt']} FROM bookings WHERE bookingId = ?");
            $legacyStmt->bind_param("s", $bookingId);
            $legacyStmt->execute();
            $legacyResult = $legacyStmt->get_result();
            $legacyRow = $legacyResult->fetch_assoc();
            $legacyStmt->close();

            if ($legacyRow) {
                $filePath = $legacyRow[$cols['file']];
                $isConfirmed = !empty($legacyRow[$cols['confirmedAt']]);
            }
        }

        // 이미 확인된 결제는 파일 삭제 불가
        if ($isConfirmed) {
            send_error_response('Cannot delete file after payment is confirmed', 403);
        }

        if (!empty($filePath)) {
            $absolutePath = __DIR__ . '/../../../' . ltrim($filePath, '/');
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }
        }

        // Update new booking_payments table
        upsertPayment($conn, $bookingId, $paymentType, [
            'filePath' => null,
            'fileName' => null,
            'uploadedAt' => null,
            'status' => 'pending'
        ]);

        // Update legacy bookings columns for backward compatibility
        $uploadedAtCol = str_replace('File', 'UploadedAt', $cols['file']);
        $updateSql = "UPDATE bookings SET {$cols['file']} = NULL, {$cols['fileName']} = NULL, {$uploadedAtCol} = NULL WHERE bookingId = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $stmt->close();

        // 이력 추가
        $typeLabels = ['down' => 'Down Payment', 'second' => 'Second Payment', 'balance' => 'Balance', 'full' => 'Full Payment'];
        addReservationHistory($conn, $bookingId, $typeLabels[$paymentType] . ' proof file deleted');

        send_success_response([], 'File deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete file: ' . $e->getMessage());
    }
}

/**
 * 현재 예약의 여행자 bookingTravelerId 목록 조회
 */
function __get_current_traveler_ids($conn, $bookingId) {
    $ids = [];
    $travelerColumns = [];
    $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
    if ($travelerColumnCheck) {
        while ($col = $travelerColumnCheck->fetch_assoc()) {
            $travelerColumns[] = strtolower($col['Field']);
        }
    }
    $bookingIdCol = in_array('transactno', $travelerColumns) ? 'transactNo' : 'bookingId';
    $stmt = $conn->prepare("SELECT bookingTravelerId FROM booking_travelers WHERE $bookingIdCol = ?");
    if ($stmt) {
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['bookingTravelerId'])) {
                $ids[] = (int)$row['bookingTravelerId'];
            }
        }
        $stmt->close();
    }
    return $ids;
}

/**
 * pending_update 상태에서 변경 요청 생성 시, 올바른 originalStatus를 조회
 */
function __get_true_original_status_agent($conn, $bookingId, $currentStatus) {
    if (!in_array($currentStatus, ['pending_update', 'check_reject'])) {
        return $currentStatus;
    }

    $stmt = $conn->prepare("SELECT originalStatus FROM booking_change_requests WHERE bookingId = ? AND status = 'pending' AND originalStatus IS NOT NULL AND originalStatus NOT IN ('pending_update', 'check_reject') ORDER BY requestedAt ASC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['originalStatus'])) {
            return $row['originalStatus'];
        }
    }

    $stmt2 = $conn->prepare("SELECT originalStatus FROM booking_change_requests WHERE bookingId = ? AND originalStatus IS NOT NULL AND originalStatus NOT IN ('pending_update', 'check_reject') ORDER BY requestedAt DESC LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('s', $bookingId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $stmt2->close();
        if ($row2 && !empty($row2['originalStatus'])) {
            return $row2['originalStatus'];
        }
    }

    return 'confirmed';
}

// 예약 이력 추가 헬퍼 함수
function addReservationHistory($conn, $bookingId, $description, $extra = []) {
    try {
        // booking_history 테이블 존재 확인
        $tableCheck = $conn->query("SHOW TABLES LIKE 'booking_history'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS booking_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bookingId VARCHAR(50) NOT NULL,
                description TEXT,
                changeType VARCHAR(50) NULL,
                changedBy VARCHAR(100) NULL,
                changedByType VARCHAR(20) NULL,
                previousData LONGTEXT NULL,
                newData LONGTEXT NULL,
                reason TEXT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bookingId (bookingId)
            )");
        }

        $changeType = $extra['changeType'] ?? null;
        $changedBy = $extra['changedBy'] ?? null;
        $changedByType = $extra['changedByType'] ?? null;
        $previousData = $extra['previousData'] ?? null;
        $newData = $extra['newData'] ?? null;
        $reason = $extra['reason'] ?? null;

        $sql = "INSERT INTO booking_history (bookingId, description, changeType, changedBy, changedByType, previousData, newData, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $bookingId, $description, $changeType, $changedBy, $changedByType, $previousData, $newData, $reason);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log('Failed to add reservation history: ' . $e->getMessage());
    }
}

function deleteInquiry($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? '';
        
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        
        // Agent의 accountId 가져오기 (세션에서)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? null);
        
        if (empty($agentAccountId)) {
            send_error_response('Authentication required');
        }
        
        // Agent는 자신이 작성한 문의만 삭제 가능
        $checkSql = "SELECT inquiryId FROM inquiries WHERE inquiryId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $inquiryId, $agentAccountId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            send_error_response('Inquiry not found or access denied');
        }
        
        // 첨부파일 삭제
        $attachmentCheck = $conn->query("SHOW TABLES LIKE 'inquiry_attachments'");
        if ($attachmentCheck && $attachmentCheck->num_rows > 0) {
            $attachmentSql = "SELECT filePath FROM inquiry_attachments WHERE inquiryId = ?";
            $attachmentStmt = $conn->prepare($attachmentSql);
            $attachmentStmt->bind_param("i", $inquiryId);
            $attachmentStmt->execute();
            $attachmentResult = $attachmentStmt->get_result();
            
            while ($attachment = $attachmentResult->fetch_assoc()) {
                $filePath = $attachment['filePath'];
                if ($filePath && file_exists(__DIR__ . '/../../../' . $filePath)) {
                    unlink(__DIR__ . '/../../../' . $filePath);
                }
            }
            
            $deleteAttachmentSql = "DELETE FROM inquiry_attachments WHERE inquiryId = ?";
            $deleteAttachmentStmt = $conn->prepare($deleteAttachmentSql);
            $deleteAttachmentStmt->bind_param("i", $inquiryId);
            $deleteAttachmentStmt->execute();
        }
        
        // 문의 삭제
        $sql = "DELETE FROM inquiries WHERE inquiryId = ? AND accountId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $inquiryId, $agentAccountId);
        $stmt->execute();
        
        send_success_response(['message' => 'Inquiry deleted successfully']);
    } catch (Exception $e) {
        send_error_response('Failed to delete inquiry: ' . $e->getMessage());
    }
}

function submitInquiryReply($conn, $input) {
    try {
        $inquiryId = $input['inquiryId'] ?? '';
        $replyContent = $input['replyContent'] ?? '';
        
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        
        if (empty($replyContent)) {
            send_error_response('Reply content is required');
        }
        
        // Agent의 accountId 가져오기 (세션에서)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['accountId'] ?? null;
        
        if (empty($agentAccountId)) {
            send_error_response('Authentication required');
        }
        
        // 문의가 존재하고 Agent가 작성한 것인지 확인
        $checkSql = "SELECT inquiryId FROM inquiries WHERE inquiryId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $inquiryId, $agentAccountId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            send_error_response('Inquiry not found or access denied');
        }
        
        // inquiries 테이블 컬럼 확인
        $inquiryColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        
        $hasReplyContent = in_array('replycontent', $inquiryColumns);
        $hasRepliedAt = in_array('repliedat', $inquiryColumns);
        $hasStatus = in_array('status', $inquiryColumns);
        
        // replyContent 컬럼이 있으면 직접 업데이트, 없으면 inquiry_replies 테이블 사용
        if ($hasReplyContent) {
            $updates = [];
            $params = [];
            $types = '';
            
            $updates[] = "replyContent = ?";
            $params[] = $replyContent;
            $types .= 's';
            
            if ($hasRepliedAt) {
                $updates[] = "repliedAt = NOW()";
            }
            
            if ($hasStatus) {
                $updates[] = "status = ?";
                $params[] = 'completed';
                $types .= 's';
            }
            
            $params[] = $inquiryId;
            $types .= 'i';
            
            $sql = "UPDATE inquiries SET " . implode(', ', $updates) . " WHERE inquiryId = ?";
            $stmt = $conn->prepare($sql);
            mysqli_bind_params_by_ref($stmt, $types, $params);
            $stmt->execute();
        } else {
            // inquiry_replies 테이블 사용
            $replyTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
            if ($replyTableCheck && $replyTableCheck->num_rows > 0) {
                $replyColumns = [];
                $replyColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_replies");
                if ($replyColumnResult) {
                    while ($col = $replyColumnResult->fetch_assoc()) {
                        $replyColumns[] = strtolower($col['Field']);
                    }
                }
                
                $replyContentField = in_array('replycontent', $replyColumns) ? 'replyContent' : (in_array('content', $replyColumns) ? 'content' : 'message');
                $inquiryIdField = in_array('inquiryid', $replyColumns) ? 'inquiryId' : 'inquiry_id';
                
                $replySql = "INSERT INTO inquiry_replies ($inquiryIdField, $replyContentField, createdAt) VALUES (?, ?, NOW())";
                $replyStmt = $conn->prepare($replySql);
                $replyStmt->bind_param("is", $inquiryId, $replyContent);
                $replyStmt->execute();
            }
            
            // 상태 업데이트
            if ($hasStatus) {
                $statusSql = "UPDATE inquiries SET status = ? WHERE inquiryId = ?";
                $statusStmt = $conn->prepare($statusSql);
                $status = 'completed';
                $statusStmt->bind_param("si", $status, $inquiryId);
                $statusStmt->execute();
            }
        }
        
        // 답변 첨부파일 업로드 처리
        if (!empty($_FILES)) {
            $attachmentTableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_reply_attachments'");
            if ($attachmentTableCheck && $attachmentTableCheck->num_rows > 0) {
                $uploadDir = __DIR__ . '/../../../uploads/inquiries/replies/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $attachmentColumns = [];
                $attachmentColumnResult = $conn->query("SHOW COLUMNS FROM inquiry_reply_attachments");
                if ($attachmentColumnResult) {
                    while ($col = $attachmentColumnResult->fetch_assoc()) {
                        $attachmentColumns[] = strtolower($col['Field']);
                    }
                }
                
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'reply_file_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $fileName = 'reply_' . $inquiryId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $filePath = 'uploads/inquiries/replies/' . $fileName;
                            $fileSize = $file['size'];
                            $fileType = $file['type'];
                            $originalFileName = $file['name'];
                            
                            $attachmentFields = [];
                            $attachmentPlaceholders = [];
                            $attachmentTypes = '';
                            $attachmentParams = [];
                            
                            if (in_array('inquiryid', $attachmentColumns)) {
                                $attachmentFields[] = 'inquiryId';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $inquiryId;
                            }
                            
                            if (in_array('filename', $attachmentColumns)) {
                                $attachmentFields[] = 'fileName';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $originalFileName;
                            }
                            
                            if (in_array('filepath', $attachmentColumns)) {
                                $attachmentFields[] = 'filePath';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $filePath;
                            }
                            
                            if (in_array('filesize', $attachmentColumns)) {
                                $attachmentFields[] = 'fileSize';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 'i';
                                $attachmentParams[] = $fileSize;
                            }
                            
                            if (in_array('filetype', $attachmentColumns)) {
                                $attachmentFields[] = 'fileType';
                                $attachmentPlaceholders[] = '?';
                                $attachmentTypes .= 's';
                                $attachmentParams[] = $fileType;
                            }
                            
                            if (!empty($attachmentFields)) {
                                $attachmentSql = "INSERT INTO inquiry_reply_attachments (" . implode(', ', $attachmentFields) . ") VALUES (" . implode(', ', $attachmentPlaceholders) . ")";
                                $attachmentStmt = $conn->prepare($attachmentSql);
                                $attachmentStmt->bind_param($attachmentTypes, ...$attachmentParams);
                                $attachmentStmt->execute();
                            }
                        }
                    }
                }
            }
        }
        
        send_success_response(['message' => 'Reply submitted successfully']);
    } catch (Exception $e) {
        send_error_response('Failed to submit reply: ' . $e->getMessage());
    }
}
// =========================
// 집합 위치/공지사항 (가이드 등록 내역) - 공통(Agent/Super/Admin)
// =========================

function __require_admin_or_agent_session(): void {
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    $agentAccountId = $_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? null);
    if (empty($adminAccountId) && empty($agentAccountId)) {
        send_error_response('Login required', 401);
    }
}

function __require_admin_agent_or_guide_session(): void {
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    $guideAccountId = $_SESSION['guide_accountId'] ?? null;
    if (empty($adminAccountId) && empty($agentAccountId) && empty($guideAccountId)) {
        send_error_response('Login required', 401);
    }
}

function __get_guide_id_by_account(mysqli $conn, int $guideAccountId): ?int {
    if ($guideAccountId <= 0) return null;
    if (!__table_exists($conn, 'guides')) return null;
    $st = $conn->prepare("SELECT guideId FROM guides WHERE accountId = ? LIMIT 1");
    if (!$st) return null;
    $st->bind_param('i', $guideAccountId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $gid = $row['guideId'] ?? null;
    if (is_numeric($gid) && intval($gid) > 0) return intval($gid);
    return null;
}

function __require_booking_access_common(mysqli $conn, string $bookingId): void {
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (!empty($adminAccountId)) return; // admin has full access

    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (!empty($agentAccountId)) {
        // Agent can access only their own bookings
        $st = $conn->prepare("SELECT 1 FROM bookings WHERE bookingId = ? AND accountId = ? LIMIT 1");
        if (!$st) send_error_response('Failed to prepare query', 500);
        $st->bind_param('si', $bookingId, $agentAccountId);
        $st->execute();
        $ok = $st->get_result()->num_rows > 0;
        $st->close();
        if (!$ok) send_error_response('Access denied', 403);
        return;
    }

    $guideAccountId = $_SESSION['guide_accountId'] ?? null;
    if (!empty($guideAccountId)) {
        $guideId = __get_guide_id_by_account($conn, intval($guideAccountId));
        $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
        if (!empty($guideId) && !empty($assignedGuideId) && intval($guideId) === intval($assignedGuideId)) {
            return;
        }
        send_error_response('Access denied', 403);
    }

    send_error_response('Login required', 401);
}

function __require_booking_access(mysqli $conn, string $bookingId): void {
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    if (!empty($adminAccountId)) return; // admin은 전체 접근

    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    // 에이전트는 본인 예약만 접근 가능
    $st = $conn->prepare("SELECT 1 FROM bookings WHERE bookingId = ? AND accountId = ? LIMIT 1");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('si', $bookingId, $agentAccountId);
    $st->execute();
    $ok = $st->get_result()->num_rows > 0;
    $st->close();
    if (!$ok) send_error_response('Access denied', 403);
}

function __get_assigned_guide_id(mysqli $conn, string $bookingId): ?int {
    // bookings.guideId가 있으면 사용
    $hasGuideIdCol = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM bookings LIKE 'guideId'");
        $hasGuideIdCol = ($col && $col->num_rows > 0);
    } catch (Throwable $e) { $hasGuideIdCol = false; }

    if ($hasGuideIdCol) {
        $st = $conn->prepare("SELECT guideId FROM bookings WHERE bookingId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('s', $bookingId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $gid = $row['guideId'] ?? null;
            if (is_numeric($gid) && intval($gid) > 0) return intval($gid);
        }
    }

    // booking_guides 테이블이 있으면 fallback
    if (__table_exists($conn, 'booking_guides')) {
        $st = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? ORDER BY guideId DESC LIMIT 1");
        if ($st) {
            $st->bind_param('s', $bookingId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $gid = $row['guideId'] ?? null;
            if (is_numeric($gid) && intval($gid) > 0) return intval($gid);
        }
    }

    return null;
}

function __table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return ($res && $res->num_rows > 0);
}

function getLocationHistory($conn, $input) {
    __require_admin_agent_or_guide_session();
    $bookingId = (string)($input['bookingId'] ?? ($input['id'] ?? ''));
    if ($bookingId === '') send_error_response('Booking ID is required', 400);
    __require_booking_access_common($conn, $bookingId);

    if (!__table_exists($conn, 'meeting_locations')) {
        send_success_response(['locations' => [], 'totalPages' => 1, 'totalCount' => 0], 'Success');
    }

    // "배정된 가이드"만 조회 (guideId가 없으면 0건)
    $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
    if (empty($assignedGuideId)) {
        send_success_response(['locations' => [], 'totalPages' => 1, 'totalCount' => 0], 'Success');
    }

    $page = is_numeric($input['page'] ?? null) ? max(1, intval($input['page'])) : 1;
    $limit = is_numeric($input['limit'] ?? null) ? max(1, intval($input['limit'])) : 10;
    if ($limit > 50) $limit = 50;
    $offset = ($page - 1) * $limit;

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    $cnt = 0;
    // 관리자 공통 페이지에서는 삭제건도 포함하여 집계/노출
    $cst = $conn->prepare("SELECT COUNT(*) AS cnt FROM meeting_locations WHERE bookingId = ? AND guideId = ?");
    if ($cst) {
        $cst->bind_param('si', $bookingId, $assignedGuideId);
        $cst->execute();
        $r = $cst->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $cst->close();
        $cnt = intval($row['cnt'] ?? 0);
    }
    $totalPages = max(1, (int)ceil($cnt / $limit));

    $st = $conn->prepare($hasStatus ? "
        SELECT locationId, bookingId, meetingTime, locationName, address, latitude, longitude, content, createdAt, status
        FROM meeting_locations
        WHERE bookingId = ? AND guideId = ?
        ORDER BY (CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) ASC, meetingTime DESC, createdAt DESC
        LIMIT ? OFFSET ?
    " : "
        SELECT locationId, bookingId, meetingTime, locationName, address, latitude, longitude, content, createdAt
        FROM meeting_locations
        WHERE bookingId = ? AND guideId = ?
        ORDER BY meetingTime DESC, createdAt DESC
        LIMIT ? OFFSET ?
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('siii', $bookingId, $assignedGuideId, $limit, $offset);
    $st->execute();
    $res = $st->get_result();
    $items = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
        $items[] = [
            'locationId' => intval($row['locationId']),
            'bookingId' => (string)($row['bookingId'] ?? ''),
            'meetingTime' => (string)($row['meetingTime'] ?? ''),
            'placeName' => (string)($row['locationName'] ?? ''),
            'locationName' => (string)($row['locationName'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'latitude' => isset($row['latitude']) ? floatval($row['latitude']) : null,
            'longitude' => isset($row['longitude']) ? floatval($row['longitude']) : null,
            'content' => (string)($row['content'] ?? ''),
            'createdAt' => (string)($row['createdAt'] ?? ''),
            'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
        ];
    }
    $st->close();

    send_success_response(['locations' => $items, 'totalPages' => $totalPages, 'totalCount' => $cnt], 'Success');
}

function getLatestMeetingLocation($conn, $input) {
    __require_admin_agent_or_guide_session();
    $bookingId = (string)($input['bookingId'] ?? ($input['id'] ?? ''));
    if ($bookingId === '') send_error_response('Booking ID is required', 400);
    __require_booking_access_common($conn, $bookingId);

    if (!__table_exists($conn, 'meeting_locations')) {
        send_success_response(['location' => null], 'Success');
    }

    $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
    if (empty($assignedGuideId)) {
        send_success_response(['location' => null], 'Success');
    }

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    // 최신은 "active 우선, 없으면 deleted" (요구사항: 삭제건도 공통 페이지에서 확인 가능)
    $st = $conn->prepare($hasStatus ? "
        SELECT locationId, bookingId, meetingTime, locationName, address, latitude, longitude, content, createdAt, status
        FROM meeting_locations
        WHERE bookingId = ? AND guideId = ?
        ORDER BY (CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) ASC, meetingTime DESC, createdAt DESC
        LIMIT 1
    " : "
        SELECT locationId, bookingId, meetingTime, locationName, address, latitude, longitude, content, createdAt
        FROM meeting_locations
        WHERE bookingId = ? AND guideId = ?
        ORDER BY meetingTime DESC, createdAt DESC
        LIMIT 1
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('si', $bookingId, $assignedGuideId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();

    if (!$row) {
        send_success_response(['location' => null], 'Success');
    }

    $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
    send_success_response(['location' => [
        'locationId' => intval($row['locationId']),
        'bookingId' => (string)($row['bookingId'] ?? ''),
        'meetingTime' => (string)($row['meetingTime'] ?? ''),
        'locationName' => (string)($row['locationName'] ?? ''),
        'placeName' => (string)($row['locationName'] ?? ''),
        'address' => (string)($row['address'] ?? ''),
        'latitude' => isset($row['latitude']) ? floatval($row['latitude']) : null,
        'longitude' => isset($row['longitude']) ? floatval($row['longitude']) : null,
        'content' => (string)($row['content'] ?? ''),
        'createdAt' => (string)($row['createdAt'] ?? ''),
        'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
    ]], 'Success');
}

function getMeetingLocationDetail($conn, $input) {
    __require_admin_agent_or_guide_session();
    $locationId = $input['locationId'] ?? ($input['id'] ?? null);
    if (!is_numeric($locationId)) send_error_response('Location ID is required', 400);
    $locationId = intval($locationId);

    if (!__table_exists($conn, 'meeting_locations')) {
        send_error_response('Not found', 404);
    }

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    $st = $conn->prepare($hasStatus ? "
        SELECT locationId, bookingId, guideId, meetingTime, locationName, address, latitude, longitude, content, createdAt, status
        FROM meeting_locations
        WHERE locationId = ?
        LIMIT 1
    " : "
        SELECT locationId, bookingId, guideId, meetingTime, locationName, address, latitude, longitude, content, createdAt
        FROM meeting_locations
        WHERE locationId = ?
        LIMIT 1
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('i', $locationId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if (!$row) send_error_response('Not found', 404);

    // bookingId 기준 접근권한 체크 + 배정 가이드 일치 체크
    $bookingId = (string)($row['bookingId'] ?? '');
    if ($bookingId !== '') {
        __require_booking_access_common($conn, $bookingId);
        $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
        if (!empty($assignedGuideId) && isset($row['guideId']) && intval($row['guideId']) !== intval($assignedGuideId)) {
            send_error_response('Access denied', 403);
        }
    }

    $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
    send_success_response(['location' => [
        'locationId' => intval($row['locationId']),
        'bookingId' => (string)($row['bookingId'] ?? ''),
        'meetingTime' => (string)($row['meetingTime'] ?? ''),
        'locationName' => (string)($row['locationName'] ?? ''),
        'placeName' => (string)($row['locationName'] ?? ''),
        'address' => (string)($row['address'] ?? ''),
        'latitude' => isset($row['latitude']) ? floatval($row['latitude']) : null,
        'longitude' => isset($row['longitude']) ? floatval($row['longitude']) : null,
        'content' => (string)($row['content'] ?? ''),
        'createdAt' => (string)($row['createdAt'] ?? ''),
        'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
    ]], 'Success');
}

function getNotices($conn, $input) {
    __require_admin_agent_or_guide_session();
    $bookingId = (string)($input['bookingId'] ?? ($input['id'] ?? ''));
    if ($bookingId === '') send_error_response('Booking ID is required', 400);
    __require_booking_access_common($conn, $bookingId);

    if (!__table_exists($conn, 'guide_announcements')) {
        send_success_response(['notices' => [], 'totalPages' => 1, 'totalCount' => 0], 'Success');
    }

    // "배정된 가이드"만 조회 (guideId 없으면 0건)
    $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
    if (empty($assignedGuideId)) {
        send_success_response(['notices' => [], 'totalPages' => 1, 'totalCount' => 0], 'Success');
    }

    $page = is_numeric($input['page'] ?? null) ? max(1, intval($input['page'])) : 1;
    $limit = is_numeric($input['limit'] ?? null) ? max(1, intval($input['limit'])) : 10;
    if ($limit > 50) $limit = 50;
    $offset = ($page - 1) * $limit;

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    $cnt = 0;
    // 관리자 공통 페이지에서는 삭제건도 포함하여 집계/노출
    $cst = $conn->prepare("SELECT COUNT(*) AS cnt FROM guide_announcements WHERE bookingId = ? AND guideId = ?");
    if ($cst) {
        $cst->bind_param('si', $bookingId, $assignedGuideId);
        $cst->execute();
        $r = $cst->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $cst->close();
        $cnt = intval($row['cnt'] ?? 0);
    }
    $totalPages = max(1, (int)ceil($cnt / $limit));

    $st = $conn->prepare($hasStatus ? "
        SELECT announcementId, bookingId, title, content, createdAt, status
        FROM guide_announcements
        WHERE bookingId = ? AND guideId = ?
        ORDER BY (CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) ASC, createdAt DESC
        LIMIT ? OFFSET ?
    " : "
        SELECT announcementId, bookingId, title, content, createdAt
        FROM guide_announcements
        WHERE bookingId = ? AND guideId = ?
        ORDER BY createdAt DESC
        LIMIT ? OFFSET ?
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('siii', $bookingId, $assignedGuideId, $limit, $offset);
    $st->execute();
    $res = $st->get_result();
    $items = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
        $items[] = [
            'noticeId' => intval($row['announcementId']),
            'announcementId' => intval($row['announcementId']),
            'bookingId' => (string)($row['bookingId'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
            'createdAt' => (string)($row['createdAt'] ?? ''),
            'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
        ];
    }
    $st->close();

    send_success_response(['notices' => $items, 'totalPages' => $totalPages, 'totalCount' => $cnt], 'Success');
}

function createNotice($conn, $input) {
    __require_admin_agent_or_guide_session();

    // 가이드는 본인 배정 예약에 대해서만 등록 가능 (Admin/Agent는 이 엔드포인트 사용 불가)
    $guideAccountId = $_SESSION['guide_accountId'] ?? null;
    if (empty($guideAccountId)) {
        send_error_response('Guide login required', 403);
    }
    $guideId = __get_guide_id_by_account($conn, intval($guideAccountId));
    if (empty($guideId)) {
        send_error_response('Guide ID not found', 404);
    }

    $bookingId = (string)($input['bookingId'] ?? ($input['id'] ?? ''));
    if ($bookingId === '') send_error_response('Booking ID is required', 400);
    __require_booking_access_common($conn, $bookingId);

    if (!__table_exists($conn, 'guide_announcements')) {
        send_error_response('guide_announcements table not found', 500);
    }

    $title = trim((string)($input['title'] ?? ($input['notice_title'] ?? '')));
    $content = trim((string)($input['content'] ?? ($input['notice_content'] ?? '')));
    if ($title === '' || $content === '') {
        send_error_response('Title and content are required', 400);
    }

    // title 컬럼(255) 보호
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($title, 'UTF-8') > 255) {
            $title = mb_substr($title, 0, 255, 'UTF-8');
        }
    } else {
        if (strlen($title) > 255) $title = substr($title, 0, 255);
    }

    // 배정된 가이드가 맞는지 재확인
    $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
    if (empty($assignedGuideId) || intval($assignedGuideId) !== intval($guideId)) {
        send_error_response('Access denied', 403);
    }

    $st = $conn->prepare("INSERT INTO guide_announcements (guideId, bookingId, title, content, status) VALUES (?, ?, ?, ?, 'active')");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('isss', $guideId, $bookingId, $title, $content);
    $ok = $st->execute();
    $newId = $conn->insert_id;
    $st->close();
    if (!$ok) send_error_response('Failed to create notice', 500);

    // 사용자(예약자)에게 "가이드 공지" 알림 생성 (미확인 배지/알림함 연동)
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($tbl && $tbl->num_rows > 0) {
            // notifications 컬럼 확인 (category 컬럼이 있으면 guide_notice로 저장)
            $ncols = [];
            $nr = $conn->query("SHOW COLUMNS FROM notifications");
            while ($nr && ($c = $nr->fetch_assoc())) {
                $f = (string)($c['Field'] ?? '');
                if ($f !== '') $ncols[strtolower($f)] = $f;
            }
            $hasCategory = isset($ncols['category']);
            $typeCol = $ncols['notificationtype'] ?? ($ncols['type'] ?? null);

            // bookings에서 수신자 accountId 해석 (B2B: customerAccountId 우선)
            $bcols = [];
            $br = $conn->query("SHOW COLUMNS FROM bookings");
            while ($br && ($c = $br->fetch_assoc())) {
                $f = (string)($c['Field'] ?? '');
                if ($f !== '') $bcols[strtolower($f)] = $f;
            }
            $hasCustomerAccountId = isset($bcols['customeraccountid']);
            $recipient = null;
            $q = $hasCustomerAccountId
                ? "SELECT COALESCE(NULLIF(customerAccountId,0), accountId) AS uid FROM bookings WHERE bookingId = ? LIMIT 1"
                : "SELECT accountId AS uid FROM bookings WHERE bookingId = ? LIMIT 1";
            $stU = $conn->prepare($q);
            if ($stU) {
                $stU->bind_param('s', $bookingId);
                $stU->execute();
                $rowU = $stU->get_result()->fetch_assoc();
                $stU->close();
                if (isset($rowU['uid']) && is_numeric($rowU['uid'])) $recipient = intval($rowU['uid']);
            }

            if (!empty($recipient) && !empty($typeCol)) {
                // notificationType enum에는 guide_notice가 없으므로 general로 저장 + category로 구분
                $ntype = 'general';
                $cat = 'guide_notice';
                $nTitle = 'Guide Notice';
                $nMsg = $title;
                $actionUrl = "guide-notice.html?booking_id=" . rawurlencode($bookingId);

                if ($hasCategory) {
                    $ins = $conn->prepare("INSERT INTO notifications (accountId, `{$typeCol}`, `{$ncols['category']}`, title, message, isRead, priority, actionUrl, createdAt) VALUES (?, ?, ?, ?, ?, 0, 'high', ?, NOW())");
                    if ($ins) {
                        $ins->bind_param('isssss', $recipient, $ntype, $cat, $nTitle, $nMsg, $actionUrl);
                        @$ins->execute();
                        @$ins->close();
                    }
                } else {
                    $ins = $conn->prepare("INSERT INTO notifications (accountId, `{$typeCol}`, title, message, isRead, priority, actionUrl, createdAt) VALUES (?, ?, ?, ?, 0, 'high', ?, NOW())");
                    if ($ins) {
                        $ins->bind_param('issss', $recipient, $ntype, $nTitle, $nMsg, $actionUrl);
                        @$ins->execute();
                        @$ins->close();
                    }
                }
            }
        }
    } catch (Throwable $_) {}

    send_success_response(['noticeId' => intval($newId)], 'Success');
}

function getLatestNotice($conn, $input) {
    __require_admin_agent_or_guide_session();
    $bookingId = (string)($input['bookingId'] ?? ($input['id'] ?? ''));
    if ($bookingId === '') send_error_response('Booking ID is required', 400);
    __require_booking_access_common($conn, $bookingId);

    if (!__table_exists($conn, 'guide_announcements')) {
        send_success_response(['notice' => null], 'Success');
    }

    $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
    if (empty($assignedGuideId)) {
        send_success_response(['notice' => null], 'Success');
    }

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    // 최신은 "active 우선, 없으면 deleted" (요구사항: 삭제건도 공통 페이지에서 확인 가능)
    $st = $conn->prepare($hasStatus ? "
        SELECT announcementId, bookingId, title, content, createdAt, status
        FROM guide_announcements
        WHERE bookingId = ? AND guideId = ?
        ORDER BY (CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) ASC, createdAt DESC
        LIMIT 1
    " : "
        SELECT announcementId, bookingId, title, content, createdAt
        FROM guide_announcements
        WHERE bookingId = ? AND guideId = ?
        ORDER BY createdAt DESC
        LIMIT 1
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('si', $bookingId, $assignedGuideId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if (!$row) send_success_response(['notice' => null], 'Success');

    $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
    send_success_response(['notice' => [
        'noticeId' => intval($row['announcementId']),
        'announcementId' => intval($row['announcementId']),
        'bookingId' => (string)($row['bookingId'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'content' => (string)($row['content'] ?? ''),
        'createdAt' => (string)($row['createdAt'] ?? ''),
        'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
    ]], 'Success');
}

function getNoticeDetail($conn, $input) {
    __require_admin_agent_or_guide_session();
    $noticeId = $input['noticeId'] ?? ($input['announcementId'] ?? ($input['id'] ?? null));
    if (!is_numeric($noticeId)) send_error_response('Notice ID is required', 400);
    $noticeId = intval($noticeId);

    if (!__table_exists($conn, 'guide_announcements')) {
        send_error_response('Not found', 404);
    }

    $hasStatus = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM guide_announcements LIKE 'status'");
        $hasStatus = ($c && $c->num_rows > 0);
    } catch (Throwable $e) { $hasStatus = false; }

    $st = $conn->prepare($hasStatus ? "
        SELECT announcementId, bookingId, guideId, title, content, createdAt, status
        FROM guide_announcements
        WHERE announcementId = ?
        LIMIT 1
    " : "
        SELECT announcementId, bookingId, guideId, title, content, createdAt
        FROM guide_announcements
        WHERE announcementId = ?
        LIMIT 1
    ");
    if (!$st) send_error_response('Failed to prepare query', 500);
    $st->bind_param('i', $noticeId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if (!$row) send_error_response('Not found', 404);

    // bookingId 기준 접근권한 체크 + 배정 가이드 일치 체크
    $bookingId = (string)($row['bookingId'] ?? '');
    if ($bookingId !== '') {
        __require_booking_access_common($conn, $bookingId);
        $assignedGuideId = __get_assigned_guide_id($conn, $bookingId);
        if (!empty($assignedGuideId) && isset($row['guideId']) && intval($row['guideId']) !== intval($assignedGuideId)) {
            send_error_response('Access denied', 403);
        }
    }

    $stRaw = $hasStatus ? strtolower((string)($row['status'] ?? 'active')) : 'active';
    send_success_response(['notice' => [
        'noticeId' => intval($row['announcementId']),
        'announcementId' => intval($row['announcementId']),
        'bookingId' => (string)($row['bookingId'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'content' => (string)($row['content'] ?? ''),
        'createdAt' => (string)($row['createdAt'] ?? ''),
        'status' => ($stRaw === 'deleted') ? 'deleted' : 'register',
    ]], 'Success');
}

// ========== 24시간 내 수정 관련 함수 ==========

/**
 * 예약 고객 정보 수정 (24시간 내)
 */
function updateCustomerInfo($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        // 예약 정보 조회 및 소유권 확인
        $checkSql = "SELECT bookingId, accountId, selectedOptions, createdAt FROM bookings WHERE bookingId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found or access denied', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        // 출발 한달 전까지만 수정 가능
        $departureDate = $booking['departureDate'] ?? '';
        if (!empty($departureDate)) {
            $departure = strtotime($departureDate);
            $oneMonthBefore = strtotime('-1 month', $departure);
            $now = time();
            if ($now >= $oneMonthBefore) {
                send_error_response('Edit is only allowed until one month before departure date', 403);
            }
        }

        // 기존 selectedOptions 파싱
        $selectedOptions = [];
        if (!empty($booking['selectedOptions'])) {
            $selectedOptions = json_decode($booking['selectedOptions'], true);
            if (!is_array($selectedOptions)) {
                $selectedOptions = [];
            }
        }

        // customerInfo 업데이트
        $customerInfo = $selectedOptions['customerInfo'] ?? [];
        if (!is_array($customerInfo)) {
            $customerInfo = [];
        }

        // 입력받은 필드 업데이트
        if (isset($input['firstName'])) $customerInfo['fName'] = trim($input['firstName']);
        if (isset($input['lastName'])) $customerInfo['lName'] = trim($input['lastName']);
        if (isset($input['email'])) $customerInfo['email'] = trim($input['email']);
        if (isset($input['phone'])) $customerInfo['phone'] = trim($input['phone']);
        if (isset($input['countryCode'])) $customerInfo['countryCode'] = trim($input['countryCode']);

        $selectedOptions['customerInfo'] = $customerInfo;

        // JSON으로 저장
        $updatedJson = json_encode($selectedOptions, JSON_UNESCAPED_UNICODE);

        // bookings 테이블 업데이트
        $updateSql = "UPDATE bookings SET selectedOptions = ? WHERE bookingId = ? AND accountId = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('ssi', $updatedJson, $bookingId, $agentAccountId);
        $updateStmt->execute();

        if ($updateStmt->affected_rows >= 0) {
            send_success_response(['updated' => true], 'Customer info updated successfully');
        } else {
            send_error_response('Failed to update customer info', 500);
        }

        $updateStmt->close();

    } catch (Exception $e) {
        send_error_response('Failed to update customer info: ' . $e->getMessage(), 500);
    }
}

/**
 * 여행자 정보 수정
 */
function updateTravelerInfo($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        $travelers = $input['travelers'] ?? [];
        if (empty($travelers) || !is_array($travelers)) {
            send_error_response('Travelers data is required', 400);
        }

        // selectedRooms 받기 (Agent가 room option도 함께 변경하는 경우)
        $selectedRooms = $input['selectedRooms'] ?? null;

        // 예약 정보 조회 및 소유권 확인 (비자 신청용 추가 필드 포함)
        $checkSql = "SELECT b.bookingId, b.accountId, b.agentId, b.createdAt, b.packageId, b.departureDate,
                            b.bookingStatus, b.paymentStatus, COALESCE(b.edit_allowed, 0) as edit_allowed,
                            COALESCE(b.customerAccountId, b.accountId) as customerAccountId,
                            p.destination as packageDestination,
                            COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 3) as packageDurationDays
                     FROM bookings b
                     LEFT JOIN packages p ON b.packageId = p.packageId
                     WHERE b.bookingId = ? AND b.agentId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found or access denied', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        // pending_update 또는 check_reject 상태에서는 추가 수정 불가
        $currentStatus = strtolower($booking['bookingStatus'] ?? '');
        if ($currentStatus === 'pending_update') {
            send_error_response('There is already a pending change request. Please wait for approval.', 400);
        }
        if ($currentStatus === 'check_reject') {
            send_error_response('Previous change request was rejected. Please confirm the rejection first.', 400);
        }

        // === 일수 기반 수정 분기 로직 ===
        $departureDate = $booking['departureDate'] ?? '';
        $editAllowedFlag = (int)($booking['edit_allowed'] ?? 0);
        $editMode = $input['editMode'] ?? 'locked';
        $isAddRemoveClient = (bool)($input['isAddRemove'] ?? false);

        // 서버 사이드 남은 일수 계산
        $daysRemaining = null;
        if (!empty($departureDate)) {
            $departure = new DateTime($departureDate);
            $now = new DateTime('today');
            $diff = $now->diff($departure);
            $daysRemaining = $diff->invert ? -$diff->days : $diff->days;
        }

        // 서버 사이드 추가/삭제 검증
        $currentTravelerIds = __get_current_traveler_ids($conn, $bookingId);
        $currentTravelerCount = count($currentTravelerIds);
        $isAddRemoveServer = false;
        if (count($travelers) !== $currentTravelerCount) {
            $isAddRemoveServer = true;
        } else {
            $pendingIds = array_filter(array_map(function($t) {
                return $t['bookingTravelerId'] ?? null;
            }, $travelers));
            foreach ($currentTravelerIds as $existId) {
                if (!in_array($existId, $pendingIds)) {
                    $isAddRemoveServer = true;
                    break;
                }
            }
            // 새 여행자 (bookingTravelerId 없는) 존재 확인
            foreach ($travelers as $t) {
                if (empty($t['bookingTravelerId'])) {
                    $isAddRemoveServer = true;
                    break;
                }
            }
        }
        $isAddRemove = $isAddRemoveClient || $isAddRemoveServer;

        // 수정 모드 결정: 34일 이상 또는 edit_allowed → 직접 수정, 34일 미만 → pending_update
        $canDirectEdit = false;
        if ($editAllowedFlag === 1) {
            $canDirectEdit = true;
        } elseif ($daysRemaining === null) {
            $canDirectEdit = true; // 출발일 미설정
        } elseif ($daysRemaining >= 34) {
            $canDirectEdit = true;
        }
        // else: < 34일 → pending_update 경로

        $changeType = $isAddRemove ? 'travelers_add_remove' : 'travelers';

        // 인원 변경 시 재고 확인 (동적 계산: bookings 테이블 기반)
        // 좌석 점유 인원만 카운트 (lap infant 제외)
        $newSeatCount = 0;
        foreach ($travelers as $t) {
            $tType = strtolower($t['travelerType'] ?? $t['type'] ?? '');
            if ($tType === 'infant' && empty($t['infantSeat'])) continue; // lap infant
            $newSeatCount++;
        }
        // 기존 좌석 점유 인원: 전체 여행자 중 lap infant 제외
        $currentSeatCount = 0;
        $existingTravStmt = $conn->prepare("SELECT travelerType, infantSeat FROM booking_travelers WHERE transactNo = ?");
        if ($existingTravStmt) {
            $existingTravStmt->bind_param('s', $bookingId);
            $existingTravStmt->execute();
            $existingTravResult = $existingTravStmt->get_result();
            while ($eRow = $existingTravResult->fetch_assoc()) {
                $eType = strtolower($eRow['travelerType'] ?? '');
                if ($eType === 'infant' && empty($eRow['infantSeat'])) continue;
                $currentSeatCount++;
            }
            $existingTravStmt->close();
        }
        $seatDifference = $newSeatCount - $currentSeatCount;

        if (!$canDirectEdit) {
            // === pending_update 경로 (승인 필요) ===
            // 트랜잭션 시작: 좌석 체크 + change request + bookings 업데이트를 원자적으로 처리
            $conn->begin_transaction();
            try {
                $packageId = $booking['packageId'] ?? '';

                // FOR UPDATE 잠금으로 동시성 보호 (인원 증가 시)
                if ($seatDifference > 0 && !empty($packageId) && !empty($departureDate)) {
                    // 1) capacity 조회
                    $capStmt = $conn->prepare(
                        "SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1"
                    );
                    $maxSeats = 0;
                    if ($capStmt) {
                        $capStmt->bind_param('is', $packageId, $departureDate);
                        $capStmt->execute();
                        $capRow = $capStmt->get_result()->fetch_assoc();
                        if ($capRow) {
                            $maxSeats = (int)($capRow['capacity'] ?? 0);
                        }
                        $capStmt->close();
                    }
                    // capacity fallback: package_available_dates 행이 없을 때만 packages.maxParticipants
                    if (!$capRow) {
                        $pkgStmt = $conn->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
                        if ($pkgStmt) {
                            $pkgStmt->bind_param('i', $packageId);
                            $pkgStmt->execute();
                            $pkgRow = $pkgStmt->get_result()->fetch_assoc();
                            if ($pkgRow) {
                                $maxSeats = (int)($pkgRow['maxParticipants'] ?? 0);
                            }
                            $pkgStmt->close();
                        }
                    }
                    // 2) 실제 예약 수 동적 계산 (FOR UPDATE 잠금)
                    $bookedSeats = 0;
                    $bkStmt = $conn->prepare(
                        "SELECT SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                         FROM bookings
                         WHERE packageId = ? AND departureDate = ?
                           AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                           AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                         FOR UPDATE"
                    );
                    if ($bkStmt) {
                        $bkStmt->bind_param('is', $packageId, $departureDate);
                        $bkStmt->execute();
                        $bkRow = $bkStmt->get_result()->fetch_assoc();
                        if ($bkRow) {
                            $bookedSeats = (int)($bkRow['booked'] ?? 0);
                        }
                        $bkStmt->close();
                    }
                    $remainingSeats = $maxSeats - $bookedSeats;
                    if ($seatDifference > $remainingSeats) {
                        $conn->rollback();
                        send_error_response('Not enough available seats. Remaining: ' . $remainingSeats . ', Requested: ' . $seatDifference, 400);
                    }
                }

                // 현재 여행자 정보 조회
                $travelerColumns = [];
                $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
                if ($travelerColumnCheck) {
                    while ($col = $travelerColumnCheck->fetch_assoc()) {
                        $travelerColumns[] = strtolower($col['Field']);
                    }
                }
                $travelerBookingIdColumn = in_array('transactno', $travelerColumns) ? 'transactNo' : 'bookingId';

                $currentTravelersSql = "SELECT * FROM booking_travelers WHERE $travelerBookingIdColumn = ?";
                $currentTravelersStmt = $conn->prepare($currentTravelersSql);
                $currentTravelersStmt->bind_param('s', $bookingId);
                $currentTravelersStmt->execute();
                $currentTravelersResult = $currentTravelersStmt->get_result();
                $currentTravelers = [];
                while ($row = $currentTravelersResult->fetch_assoc()) {
                    $currentTravelers[] = $row;
                }
                $currentTravelersStmt->close();

                // 현재 room 정보 조회
                $currentRooms = [];
                try {
                    $roomStmt = $conn->prepare("SELECT selectedOptions, selectedRooms FROM bookings WHERE bookingId = ?");
                    if ($roomStmt) {
                        $roomStmt->bind_param('s', $bookingId);
                        $roomStmt->execute();
                        $roomResult = $roomStmt->get_result();
                        $roomRow = $roomResult->fetch_assoc();
                        $roomStmt->close();

                        if ($roomRow) {
                            $srRaw = $roomRow['selectedRooms'] ?? '';
                            if (!empty($srRaw)) {
                                $tmp = json_decode($srRaw, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                                    $currentRooms = $tmp;
                                }
                            }
                            if (empty($currentRooms) && !empty($roomRow['selectedOptions'])) {
                                $soObj = json_decode($roomRow['selectedOptions'], true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($soObj['selectedRooms'])) {
                                    $currentRooms = $soObj['selectedRooms'];
                                }
                            }
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }

                // 기존 bookings 컬럼값 조회 (선차감/복원용)
                $bkInfoStmt = $conn->prepare("SELECT adults, children, infants, totalAmount, visaFee, flightOptionFee, adultPrice, childPrice, infantPrice FROM bookings WHERE bookingId = ? LIMIT 1");
                $bkInfoStmt->bind_param('s', $bookingId);
                $bkInfoStmt->execute();
                $bkInfo = $bkInfoStmt->get_result()->fetch_assoc();
                $bkInfoStmt->close();

                // 새 인원 카운트 계산
                $newAdults = 0;
                $newChildren = 0;
                $newInfants = 0;
                foreach ($travelers as $t) {
                    $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                    if ($type === 'adult') $newAdults++;
                    elseif ($type === 'child') $newChildren++;
                    elseif ($type === 'infant') $newInfants++;
                }

                // totalAmount 계산
                $adultPrice = floatval($bkInfo['adultPrice'] ?? 0);
                $childPriceVal = floatval($bkInfo['childPrice'] ?? 0) ?: max($adultPrice - 5000, 0);
                $infantPriceVal = floatval($bkInfo['infantPrice'] ?? 0) ?: 9000;

                $calculatedNewTotal = 0;
                $calculatedVisaFee = 0;
                $calculatedFlightOptionFee = 0;
                foreach ($travelers as $t) {
                    $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
                    if ($type === 'adult') {
                        $calculatedNewTotal += $adultPrice;
                    } elseif ($type === 'child') {
                        $calculatedNewTotal += $childPriceVal;
                    } elseif ($type === 'infant') {
                        $calculatedNewTotal += $infantPriceVal;
                    }
                    // Visa 요금
                    $visaType = strtolower(trim($t['visaType'] ?? ''));
                    if ($visaType === 'group') { $calculatedVisaFee += 1500; $calculatedNewTotal += 1500; }
                    elseif ($visaType === 'individual') { $calculatedVisaFee += 1900; $calculatedNewTotal += 1900; }
                    // Flight Options
                    if (!empty($t['flightOptionPrices']) && is_array($t['flightOptionPrices'])) {
                        foreach ($t['flightOptionPrices'] as $price) {
                            $fp = floatval($price);
                            $calculatedFlightOptionFee += $fp;
                            $calculatedNewTotal += $fp;
                        }
                    }
                }
                // Room 요금
                $pendingRooms = $selectedRooms ?? $currentRooms;
                if (is_array($pendingRooms)) {
                    foreach ($pendingRooms as $r) {
                        $count = intval($r['count'] ?? $r['roomCount'] ?? 0);
                        $price = floatval($r['roomPrice'] ?? $r['room_price'] ?? 0);
                        if ($count > 0 && $price > 0) {
                            $calculatedNewTotal += $count * $price;
                        }
                    }
                }

                $isIncrease = ($seatDifference > 0);

                // previousData 구성
                $previousDataArray = [
                    'originalTravelers' => $currentTravelers,
                    'originalRooms' => $currentRooms,
                    'originalAdults' => (int)$bkInfo['adults'],
                    'originalChildren' => (int)$bkInfo['children'],
                    'originalInfants' => (int)$bkInfo['infants'],
                    'originalTotalAmount' => (float)$bkInfo['totalAmount'],
                    'originalVisaFee' => (float)$bkInfo['visaFee'],
                    'originalFlightOptionFee' => (float)$bkInfo['flightOptionFee']
                ];
                $previousData = json_encode($previousDataArray, JSON_UNESCAPED_UNICODE);

                // newData 구성
                $newDataArray = [
                    'pendingTravelers' => $travelers,
                    'newAdults' => $newAdults,
                    'newChildren' => $newChildren,
                    'newInfants' => $newInfants,
                    'newTotalAmount' => $calculatedNewTotal,
                    'newVisaFee' => $calculatedVisaFee,
                    'newFlightOptionFee' => $calculatedFlightOptionFee,
                    'preDeducted' => $isIncrease
                ];
                if ($selectedRooms !== null) {
                    $newDataArray['selectedRooms'] = $selectedRooms;
                }
                $newData = json_encode($newDataArray, JSON_UNESCAPED_UNICODE);

                // booking_change_requests에 변경 요청 저장
                $requestedBy = $_SESSION['agent_username'] ?? $_SESSION['username'] ?? 'agent';
                $trueOriginalStatus = __get_true_original_status_agent($conn, $bookingId, $booking['bookingStatus']);
                $changeRequestSql = "INSERT INTO booking_change_requests (bookingId, changeType, originalStatus, originalPaymentStatus, previousData, newData, requestedBy, requestedByType, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'agent', 'pending')";
                $changeRequestStmt = $conn->prepare($changeRequestSql);
                $changeRequestStmt->bind_param('sssssss', $bookingId, $changeType, $trueOriginalStatus, $booking['paymentStatus'], $previousData, $newData, $requestedBy);
                $changeRequestStmt->execute();
                $changeRequestStmt->close();

                // infantsWithSeat 계산
                $newInfantsWithSeat = 0;
                foreach ($travelers as $t) {
                    if (strtolower($t['type'] ?? '') === 'infant' && filter_var($t['infantSeat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $newInfantsWithSeat++;
                    }
                }

                if ($isIncrease) {
                    $pendingStmt = $conn->prepare("UPDATE bookings SET adults = ?, children = ?, infants = ?, infantsWithSeat = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, bookingStatus = 'pending_update', updatedAt = NOW() WHERE bookingId = ?");
                    $pendingStmt->bind_param('iiiiddds', $newAdults, $newChildren, $newInfants, $newInfantsWithSeat, $calculatedNewTotal, $calculatedVisaFee, $calculatedFlightOptionFee, $bookingId);
                } else {
                    $pendingStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'pending_update', updatedAt = NOW() WHERE bookingId = ?");
                    $pendingStmt->bind_param('s', $bookingId);
                }
                $pendingStmt->execute();
                $pendingStmt->close();

                // 이력 기록
                $historyDesc = $isAddRemove
                    ? "Travelers add/remove change request by agent (pending approval)"
                    : "Traveler info change request by agent (pending approval)";
                addReservationHistory($conn, $bookingId, $historyDesc, [
                    'changeType' => $changeType,
                    'changedBy' => $requestedBy,
                    'changedByType' => 'agent',
                    'previousData' => $previousData,
                    'newData' => $newData
                ]);

                $conn->commit();

                send_success_response([
                    'status' => 'pending_update',
                    'message' => 'Change request submitted. Waiting for admin approval.'
                ]);
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }

        // === Capacity 체크 (직접 수정 경로, 인원 증가 시) ===
        $packageId = $booking['packageId'] ?? '';
        if ($seatDifference > 0 && !empty($packageId) && !empty($departureDate)) {
            $capStmt = $conn->prepare(
                "SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1"
            );
            $maxSeats = 0;
            if ($capStmt) {
                $capStmt->bind_param('is', $packageId, $departureDate);
                $capStmt->execute();
                $capRow = $capStmt->get_result()->fetch_assoc();
                if ($capRow) {
                    $maxSeats = (int)($capRow['capacity'] ?? 0);
                }
                $capStmt->close();
            }
            if ($maxSeats <= 0) {
                $pkgStmt = $conn->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
                if ($pkgStmt) {
                    $pkgStmt->bind_param('i', $packageId);
                    $pkgStmt->execute();
                    $pkgRow = $pkgStmt->get_result()->fetch_assoc();
                    if ($pkgRow) {
                        $maxSeats = (int)($pkgRow['maxParticipants'] ?? 0);
                    }
                    $pkgStmt->close();
                }
            }
            $bookedSeats = 0;
            $bkStmt = $conn->prepare(
                "SELECT SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS booked
                 FROM bookings
                 WHERE packageId = ? AND departureDate = ?
                   AND (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','rejected'))
                   AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')"
            );
            if ($bkStmt) {
                $bkStmt->bind_param('is', $packageId, $departureDate);
                $bkStmt->execute();
                $bkRow = $bkStmt->get_result()->fetch_assoc();
                if ($bkRow) {
                    $bookedSeats = (int)($bkRow['booked'] ?? 0);
                }
                $bkStmt->close();
            }
            $remainingSeats = $maxSeats - $bookedSeats;
            if ($seatDifference > $remainingSeats) {
                send_error_response('Not enough available seats. Remaining: ' . $remainingSeats . ', Requested: ' . $seatDifference, 400);
            }
        }

        // === 이력 기록을 위한 기존 여행자 정보 조회 ===
        $previousTravelers = [];
        try {
            $prevTravStmt = $conn->prepare("SELECT * FROM booking_travelers WHERE transactNo = ?");
            if ($prevTravStmt) {
                $prevTravStmt->bind_param('s', $bookingId);
                $prevTravStmt->execute();
                $prevTravResult = $prevTravStmt->get_result();
                while ($row = $prevTravResult->fetch_assoc()) {
                    $previousTravelers[] = $row;
                }
                $prevTravStmt->close();
            }
        } catch (Throwable $e) { /* ignore */ }

        // === 인원수/금액 재계산 ===
        $bkInfoStmt = $conn->prepare("SELECT adults, children, infants, totalAmount, visaFee, flightOptionFee, adultPrice, childPrice, infantPrice, selectedRooms, selectedOptions FROM bookings WHERE bookingId = ? LIMIT 1");
        $bkInfoStmt->bind_param('s', $bookingId);
        $bkInfoStmt->execute();
        $bkInfo = $bkInfoStmt->get_result()->fetch_assoc();
        $bkInfoStmt->close();

        // 새 인원 카운트 계산
        $newAdults = 0;
        $newChildren = 0;
        $newInfants = 0;
        foreach ($travelers as $t) {
            $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
            if ($type === 'adult') $newAdults++;
            elseif ($type === 'child') $newChildren++;
            elseif ($type === 'infant') $newInfants++;
        }

        // totalAmount 계산
        $adultPrice = floatval($bkInfo['adultPrice'] ?? 0);
        $childPriceVal = floatval($bkInfo['childPrice'] ?? 0) ?: max($adultPrice - 5000, 0);
        $infantPriceVal = floatval($bkInfo['infantPrice'] ?? 0) ?: 9000;

        $calculatedNewTotal = 0;
        $calculatedVisaFee = 0;
        $calculatedFlightOptionFee = 0;
        foreach ($travelers as $t) {
            $type = strtolower(trim($t['travelerType'] ?? $t['type'] ?? 'adult'));
            if ($type === 'adult') {
                $calculatedNewTotal += $adultPrice;
            } elseif ($type === 'child') {
                $calculatedNewTotal += $childPriceVal;
            } elseif ($type === 'infant') {
                $calculatedNewTotal += $infantPriceVal;
            }
            // Visa 요금
            $visaType = strtolower(trim($t['visaType'] ?? ''));
            if ($visaType === 'group') { $calculatedVisaFee += 1500; $calculatedNewTotal += 1500; }
            elseif ($visaType === 'individual') { $calculatedVisaFee += 1900; $calculatedNewTotal += 1900; }
            // Flight Options
            if (!empty($t['flightOptionPrices']) && is_array($t['flightOptionPrices'])) {
                foreach ($t['flightOptionPrices'] as $price) {
                    $fp = floatval($price);
                    $calculatedFlightOptionFee += $fp;
                    $calculatedNewTotal += $fp;
                }
            }
        }
        // Room 요금
        $currentRooms = [];
        $srRaw = $bkInfo['selectedRooms'] ?? '';
        if (!empty($srRaw)) {
            $tmp = json_decode($srRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $currentRooms = $tmp;
            }
        }
        if (empty($currentRooms) && !empty($bkInfo['selectedOptions'])) {
            $soObj = json_decode($bkInfo['selectedOptions'], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($soObj['selectedRooms'])) {
                $currentRooms = $soObj['selectedRooms'];
            }
        }
        $pendingRooms = $selectedRooms ?? $currentRooms;
        if (is_array($pendingRooms)) {
            foreach ($pendingRooms as $r) {
                $count = intval($r['count'] ?? $r['roomCount'] ?? 0);
                $price = floatval($r['roomPrice'] ?? $r['room_price'] ?? 0);
                if ($count > 0 && $price > 0) {
                    $calculatedNewTotal += $count * $price;
                }
            }
        }

        // === 직접 수정 경로 ===
        // 비자 신청용 변수 추출
        $customerAccountId = $booking['customerAccountId'] ?? $agentAccountId;
        $packageDestination = $booking['packageDestination'] ?? 'Korea';
        $packageDurationDays = (int)($booking['packageDurationDays'] ?? 3);

        // booking_travelers 테이블 컬럼 확인
        $travelerColumns = [];
        $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
        if ($travelerColumnCheck) {
            while ($col = $travelerColumnCheck->fetch_assoc()) {
                $travelerColumns[] = strtolower($col['Field']);
            }
        }

        // transactNo 또는 bookingId 컬럼 확인
        $travelerBookingIdColumn = 'transactNo';
        if (!in_array('transactno', $travelerColumns)) {
            if (in_array('bookingid', $travelerColumns)) {
                $travelerBookingIdColumn = 'bookingId';
            }
        }

        // 기존 비자 신청 정보 백업 (여행자 이름으로 매칭하여 보존)
        $existingVisaApplications = [];
        try {
            $getVisaSql = "SELECT visaApplicationId, applicantName, visaType, status,
                                  passport, visaApplicationForm, bankCertificate, bankStatement,
                                  additionalDocuments, visaSend, notes
                           FROM visa_applications WHERE transactNo = ?";
            $getVisaStmt = $conn->prepare($getVisaSql);
            if ($getVisaStmt) {
                $getVisaStmt->bind_param('s', $bookingId);
                $getVisaStmt->execute();
                $visaResult = $getVisaStmt->get_result();
                while ($visaRow = $visaResult->fetch_assoc()) {
                    // 이름을 key로 저장하여 나중에 매칭
                    $name = strtolower(trim($visaRow['applicantName'] ?? ''));
                    if (!empty($name)) {
                        $existingVisaApplications[$name] = $visaRow;
                    }
                }
                $getVisaStmt->close();
            }
        } catch (Exception $e) {
            error_log("Failed to backup existing visa applications: " . $e->getMessage());
        }

        // 기존 여행자 삭제 후 새로 삽입
        $deleteSql = "DELETE FROM booking_travelers WHERE $travelerBookingIdColumn = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('s', $bookingId);
        $deleteStmt->execute();
        $deleteStmt->close();

        // 비자 신청은 나중에 처리 (더 이상 필요 없는 것만 삭제)
        // 기존 비자 신청 목록 (삭제 대상 추적용)
        $visaApplicationsToKeep = [];

        // 새 여행자 정보 삽입
        $insertedCount = 0;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/passports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($travelers as $index => $traveler) {
            $travelerType = trim($traveler['travelerType'] ?? 'adult');
            $title = strtoupper(trim($traveler['title'] ?? 'MR'));
            $firstName = trim($traveler['firstName'] ?? '');
            $lastName = trim($traveler['lastName'] ?? '');
            $gender = strtolower(trim($traveler['gender'] ?? 'male'));
            // JS에서 birthDate 또는 dateOfBirth로 보낼 수 있음
            $birthDate = !empty($traveler['birthDate']) ? trim($traveler['birthDate']) : (!empty($traveler['dateOfBirth']) ? trim($traveler['dateOfBirth']) : null);
            $nationality = trim($traveler['nationality'] ?? '');
            $passportNumber = trim($traveler['passportNumber'] ?? '');
            // passportIssueDate, passportExpiryDate
            $passportIssueDate = !empty($traveler['passportIssueDate']) ? trim($traveler['passportIssueDate']) : null;
            $passportExpiryDate = !empty($traveler['passportExpiryDate']) ? trim($traveler['passportExpiryDate']) : null;
            $isMainTraveler = isset($traveler['isPrimary']) && $traveler['isPrimary'] ? 1 : (($index === 0) ? 1 : 0);
            $specialRequests = trim($traveler['specialRequests'] ?? '');
            // visaStatus and visaType
            $visaRequired = $traveler['visaRequired'] ?? false;
            $visaStatus = $visaRequired ? 'applied' : 'not_required';
            $travelerVisaType = $traveler['visaType'] ?? 'with_visa';
            if (!in_array($travelerVisaType, ['group', 'individual', 'with_visa', 'foreign'])) {
                $travelerVisaType = 'with_visa';
            }

            // 여권 이미지 처리
            $passportImage = null;
            $passportImageInput = $traveler['passportImage'] ?? null;
            if ($passportImageInput !== null) {
                if (strpos($passportImageInput, 'data:image') === 0) {
                    // Base64 이미지 - 파일로 저장
                    $matches = [];
                    if (preg_match('/^data:image\/(\w+);base64,/', $passportImageInput, $matches)) {
                        $ext = $matches[1];
                        $base64Data = substr($passportImageInput, strpos($passportImageInput, ',') + 1);
                        $imageData = base64_decode($base64Data);
                        if ($imageData !== false) {
                            $filename = $bookingId . '_' . $index . '_' . time() . '.' . $ext;
                            $filepath = $uploadDir . $filename;
                            if (file_put_contents($filepath, $imageData)) {
                                $passportImage = '/uploads/passports/' . $filename;
                            }
                        }
                    }
                } elseif (!empty($passportImageInput) && strpos($passportImageInput, '/uploads/') !== false) {
                    // 기존 경로 유지
                    $passportImage = $passportImageInput;
                }
                // null이면 이미지 없음 (삭제)
            }

            // Visa Document 처리
            $visaDocument = null;
            $visaUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/visa/';
            if (!is_dir($visaUploadDir)) {
                mkdir($visaUploadDir, 0755, true);
            }
            $visaDocumentInput = $traveler['visaDocument'] ?? null;
            if ($visaDocumentInput !== null) {
                if (strpos($visaDocumentInput, 'data:') === 0) {
                    // Base64 파일 - 파일로 저장
                    $matches = [];
                    if (preg_match('/^data:(image\/(\w+)|application\/pdf);base64,/', $visaDocumentInput, $matches)) {
                        $ext = isset($matches[2]) ? $matches[2] : 'pdf';
                        $base64Data = substr($visaDocumentInput, strpos($visaDocumentInput, ',') + 1);
                        $fileData = base64_decode($base64Data);
                        if ($fileData !== false) {
                            $filename = 'visa_' . $bookingId . '_traveler_' . $index . '_' . time() . '.' . $ext;
                            $filepath = $visaUploadDir . $filename;
                            if (file_put_contents($filepath, $fileData)) {
                                $visaDocument = 'uploads/visa/' . $filename;
                            }
                        }
                    }
                } elseif (!empty($visaDocumentInput) && strpos($visaDocumentInput, 'uploads/visa') !== false) {
                    // 기존 경로 유지
                    $visaDocument = $visaDocumentInput;
                }
                // null이면 삭제
            }

            // 필수 필드 체크
            if (empty($firstName) && empty($lastName)) {
                continue; // 이름이 없는 여행자는 건너뜀
            }

            // childRoom, infantSeat 값 추출
            $childRoomVal = isset($traveler['childRoom']) ? (int)$traveler['childRoom'] : 0;
            $infantSeatVal = isset($traveler['infantSeat']) ? (int)$traveler['infantSeat'] : 0;

            $insertSql = "INSERT INTO booking_travelers
                ($travelerBookingIdColumn, travelerType, title, firstName, lastName, gender, birthDate, nationality, passportNumber, passportIssueDate, passportExpiry, passportImage, visaDocument, isMainTraveler, visaStatus, visaType, specialRequests, childRoom, infantSeat, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param('sssssssssssssisssii',
                $bookingId,
                $travelerType,
                $title,
                $firstName,
                $lastName,
                $gender,
                $birthDate,
                $nationality,
                $passportNumber,
                $passportIssueDate,
                $passportExpiryDate,
                $passportImage,
                $visaDocument,
                $isMainTraveler,
                $visaStatus,
                $travelerVisaType,
                $specialRequests,
                $childRoomVal,
                $infantSeatVal
            );
            $insertStmt->execute();

            // 비자 신청 처리: visaRequired가 true인 경우
            $bookingTravelerId = $conn->insert_id;
            if ($visaRequired && $bookingTravelerId > 0) {
                try {
                    // 신청자 이름 조합
                    $visaApplicantName = trim($firstName . ' ' . $lastName);
                    if (empty($visaApplicantName)) {
                        $visaApplicantName = 'Unknown';
                    }
                    $visaApplicantNameKey = strtolower($visaApplicantName);

                    // 기존 비자 신청이 있는지 확인
                    if (isset($existingVisaApplications[$visaApplicantNameKey])) {
                        // 기존 비자 신청이 있음 - bookingTravelerId만 업데이트하고 기존 데이터 보존
                        $existingVisa = $existingVisaApplications[$visaApplicantNameKey];
                        $existingVisaId = $existingVisa['visaApplicationId'];

                        // 보존할 비자 신청 목록에 추가
                        $visaApplicationsToKeep[] = $existingVisaId;

                        // bookingTravelerId와 visaType만 업데이트 (문서, 상태 등은 보존)
                        $updateVisaSql = "UPDATE visa_applications SET bookingTravelerId = ?, visaType = ? WHERE visaApplicationId = ?";
                        $updateVisaStmt = $conn->prepare($updateVisaSql);
                        if ($updateVisaStmt) {
                            $updateVisaStmt->bind_param('isi', $bookingTravelerId, $travelerVisaType, $existingVisaId);
                            $updateVisaStmt->execute();
                            $updateVisaStmt->close();
                        }
                    } else {
                        // 기존 비자 신청이 없음 - 새로 생성
                        $visaApplicationNo = 'VA' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
                        $visaReturnDate = !empty($departureDate) ? date('Y-m-d', strtotime($departureDate . ' + ' . ($packageDurationDays - 1) . ' days')) : null;

                        $visaInsertSql = "
                            INSERT INTO visa_applications (
                                applicationNo, accountId, transactNo, bookingTravelerId,
                                applicantName, visaType, destinationCountry,
                                applicationDate, departureDate, returnDate, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'pending')
                        ";
                        $visaStmt = $conn->prepare($visaInsertSql);
                        if ($visaStmt) {
                            $visaStmt->bind_param('sisisssss',
                                $visaApplicationNo, $customerAccountId, $bookingId, $bookingTravelerId,
                                $visaApplicantName, $travelerVisaType, $packageDestination, $departureDate, $visaReturnDate
                            );
                            $visaStmt->execute();
                            // 새로 생성된 비자 신청도 보존 목록에 추가
                            $newVisaId = $conn->insert_id;
                            if ($newVisaId > 0) {
                                $visaApplicationsToKeep[] = $newVisaId;
                            }
                            $visaStmt->close();
                        }
                    }
                } catch (Exception $visaEx) {
                    error_log("Auto visa application creation/update failed (updateTravelerInfo): " . $visaEx->getMessage());
                }
            }

            $insertedCount++;
            $insertStmt->close();
        }

        // 더 이상 필요 없는 비자 신청 삭제 (보존 목록에 없는 것만)
        try {
            if (!empty($existingVisaApplications)) {
                $allExistingIds = array_column($existingVisaApplications, 'visaApplicationId');
                $idsToDelete = array_diff($allExistingIds, $visaApplicationsToKeep);
                if (!empty($idsToDelete)) {
                    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                    $deleteOrphanVisaSql = "DELETE FROM visa_applications WHERE visaApplicationId IN ($placeholders)";
                    $deleteOrphanStmt = $conn->prepare($deleteOrphanVisaSql);
                    if ($deleteOrphanStmt) {
                        $types = str_repeat('i', count($idsToDelete));
                        $deleteOrphanStmt->bind_param($types, ...array_values($idsToDelete));
                        $deleteOrphanStmt->execute();
                        $deleteOrphanStmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Failed to clean up orphan visa applications: " . $e->getMessage());
        }

        // infantsWithSeat 동기화 + 인원수/금액 재계산 반영
        $infantsWithSeatCount = 0;
        foreach ($travelers as $t) {
            if (strtolower($t['type'] ?? $t['travelerType'] ?? '') === 'infant' && filter_var($t['infantSeat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $infantsWithSeatCount++;
            }
        }
        $recalcStmt = $conn->prepare("UPDATE bookings SET adults = ?, children = ?, infants = ?, infantsWithSeat = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, updatedAt = NOW() WHERE bookingId = ?");
        if ($recalcStmt) {
            $recalcStmt->bind_param('iiiiddds', $newAdults, $newChildren, $newInfants, $infantsWithSeatCount, $calculatedNewTotal, $calculatedVisaFee, $calculatedFlightOptionFee, $bookingId);
            $recalcStmt->execute();
            $recalcStmt->close();
        }

        // selectedRooms 업데이트 (Agent가 room option도 변경한 경우)
        if ($selectedRooms !== null) {
            try {
                $roomJson = json_encode($selectedRooms, JSON_UNESCAPED_UNICODE);
                $roomUpdateStmt = $conn->prepare("UPDATE bookings SET selectedRooms = ? WHERE bookingId = ?");
                if ($roomUpdateStmt) {
                    $roomUpdateStmt->bind_param('ss', $roomJson, $bookingId);
                    $roomUpdateStmt->execute();
                    $roomUpdateStmt->close();
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        // 이력 로깅
        $historyDesc = $isAddRemove
            ? "Travelers add/remove applied directly by agent (count: " . count($travelers) . ")"
            : "Traveler info updated directly by agent";
        addReservationHistory($conn, $bookingId, $historyDesc, [
            'changeType' => $changeType,
            'changedBy' => $_SESSION['agent_username'] ?? 'agent',
            'changedByType' => 'agent',
            'previousData' => json_encode($previousTravelers, JSON_UNESCAPED_UNICODE),
            'newData' => json_encode($travelers, JSON_UNESCAPED_UNICODE)
        ]);

        send_success_response([
            'updated' => true,
            'travelers_count' => $insertedCount
        ], 'Traveler info updated successfully');

    } catch (Exception $e) {
        send_error_response('Failed to update traveler info: ' . $e->getMessage(), 500);
    }
}

/**
 * 패키지 검색 (Agent용)
 */
function searchPackagesForAgent($conn, $input) {
    try {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        if (empty($keyword)) {
            send_success_response([], 'No keyword provided');
            return;
        }

        $searchTerm = '%' . $keyword . '%';
        $sql = "SELECT packageId, packageName, COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = packageId), duration_days, 1) as duration_days
                FROM packages
                WHERE packageName LIKE ? AND isActive = 1
                ORDER BY packageName ASC
                LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $packages[] = [
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName'],
                'duration_days' => (int)($row['duration_days'] ?? 5)
            ];
        }
        $stmt->close();

        send_success_response($packages, 'Packages found');
    } catch (Exception $e) {
        send_error_response('Failed to search packages: ' . $e->getMessage(), 500);
    }
}

/**
 * Agent가 거부 확인 시 booking_change_requests에서 원래 상태로 복원
 */
function acknowledgeRejectionAgent($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }

        // 예약 소유권 확인 및 check_reject 상태인지 확인
        $checkSql = "SELECT bookingId, bookingStatus FROM bookings WHERE bookingId = ? AND agentId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $booking = $result->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found or access denied');
        }

        if ($booking['bookingStatus'] !== 'check_reject') {
            send_error_response('Only check_reject bookings can be acknowledged. Current status: ' . $booking['bookingStatus']);
        }

        // booking_change_requests에서 거절된 변경 요청 조회
        $changeReqSql = "SELECT * FROM booking_change_requests WHERE bookingId = ? AND status = 'rejected' ORDER BY processedAt DESC LIMIT 1";
        $changeReqStmt = $conn->prepare($changeReqSql);
        $changeReqStmt->bind_param('s', $bookingId);
        $changeReqStmt->execute();
        $changeReqResult = $changeReqStmt->get_result();
        $changeRequest = $changeReqResult->fetch_assoc();
        $changeReqStmt->close();

        if (!$changeRequest) {
            send_error_response('No rejected change request found for this booking');
        }

        // 원래 상태로 복원 (pending_update/check_reject 오염 방지)
        $rawOriginalStatus = $changeRequest['originalStatus'] ?? 'confirmed';
        $originalStatus = $rawOriginalStatus;
        if (in_array($originalStatus, ['pending_update', 'check_reject'])) {
            $originalStatus = __get_true_original_status_agent($conn, $bookingId, $originalStatus);
        }
        $originalPaymentStatus = $changeRequest['originalPaymentStatus'];

        if ($originalPaymentStatus !== null) {
            $sql = "UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $originalStatus, $originalPaymentStatus, $bookingId);
        } else {
            $sql = "UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $originalStatus, $bookingId);
        }
        $stmt->execute();
        $stmt->close();

        // 선차감 복원 안전장치: reject에서 이미 복원했지만, 미복원 케이스 대비
        $ackNewData = json_decode($changeRequest['newData'] ?? '{}', true);
        $ackPreDeducted = (bool)($ackNewData['preDeducted'] ?? false);
        if ($ackPreDeducted && !empty($changeRequest['previousData'])) {
            $ackPreviousData = json_decode($changeRequest['previousData'], true);
            $origAdults = (int)($ackPreviousData['originalAdults'] ?? 0);
            $origChildren = (int)($ackPreviousData['originalChildren'] ?? 0);
            $origInfants = (int)($ackPreviousData['originalInfants'] ?? 0);
            $origTotalAmount = (float)($ackPreviousData['originalTotalAmount'] ?? 0);
            $origVisaFee = (float)($ackPreviousData['originalVisaFee'] ?? 0);
            $origFlightOptionFee = (float)($ackPreviousData['originalFlightOptionFee'] ?? 0);

            // 현재 bookings가 newData 값과 같다면 아직 미복원 → 원본으로 복원
            $curBkStmt = $conn->prepare("SELECT adults, children, infants FROM bookings WHERE bookingId = ? LIMIT 1");
            $curBkStmt->bind_param('s', $bookingId);
            $curBkStmt->execute();
            $curBk = $curBkStmt->get_result()->fetch_assoc();
            $curBkStmt->close();

            $newAdultsExpected = (int)($ackNewData['newAdults'] ?? 0);
            $newChildrenExpected = (int)($ackNewData['newChildren'] ?? 0);
            $newInfantsExpected = (int)($ackNewData['newInfants'] ?? 0);

            if ($curBk && (int)$curBk['adults'] === $newAdultsExpected && (int)$curBk['children'] === $newChildrenExpected && (int)$curBk['infants'] === $newInfantsExpected) {
                // 아직 미복원 → 원본으로 복원
                $restoreStmt = $conn->prepare("UPDATE bookings SET adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ? WHERE bookingId = ?");
                $restoreStmt->bind_param('iiiddds', $origAdults, $origChildren, $origInfants, $origTotalAmount, $origVisaFee, $origFlightOptionFee, $bookingId);
                $restoreStmt->execute();
                $restoreStmt->close();
            }
        }

        // travelers 변경 요청이 거절된 경우 원본 traveler 데이터 복원
        if (in_array($changeRequest['changeType'], ['travelers', 'travelers_add_remove']) && !empty($changeRequest['previousData'])) {
            $previousData = json_decode($changeRequest['previousData'], true);
            $originalTravelers = $previousData['originalTravelers'] ?? [];

            if (!empty($originalTravelers)) {
                // 현재 travelers 삭제
                $deleteSql = "DELETE FROM booking_travelers WHERE transactNo = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param('s', $bookingId);
                $deleteStmt->execute();
                $deleteStmt->close();

                // 원본 travelers 복원
                foreach ($originalTravelers as $tr) {
                    $travelerType = $tr['travelerType'] ?? 'adult';
                    $title = $tr['title'] ?? null;
                    $firstName = $tr['firstName'] ?? '';
                    $lastName = $tr['lastName'] ?? '';
                    $birthDate = $tr['birthDate'] ?? null;
                    $gender = $tr['gender'] ?? null;
                    $nationality = $tr['nationality'] ?? '';
                    $passportNumber = $tr['passportNumber'] ?? '';
                    $passportIssueDate = $tr['passportIssueDate'] ?? null;
                    $passportExpiry = $tr['passportExpiry'] ?? null;
                    $passportImage = $tr['passportImage'] ?? null;
                    $visaDocument = $tr['visaDocument'] ?? null;
                    $visaStatus = $tr['visaStatus'] ?? 'not_required';
                    $visaType = $tr['visaType'] ?? null;
                    $specialRequests = $tr['specialRequests'] ?? null;
                    $isMainTraveler = (int)($tr['isMainTraveler'] ?? 0);
                    $reservationStatus = $tr['reservationStatus'] ?? null;
                    $childRoom = (int)($tr['childRoom'] ?? 0);
                    $infantSeat = (int)($tr['infantSeat'] ?? 0);
                    $profileSource = $tr['profile_source'] ?? $tr['profileSource'] ?? '';

                    // null 또는 빈 날짜 값 처리
                    $birthDateVal = (!empty($birthDate) && $birthDate !== '0000-00-00') ? $birthDate : null;
                    $passportIssueDateVal = (!empty($passportIssueDate) && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                    $passportExpiryVal = (!empty($passportExpiry) && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;

                    $insertSql = "INSERT INTO booking_travelers (transactNo, travelerType, title, firstName, lastName, birthDate, gender, nationality, passportNumber, passportIssueDate, passportExpiry, passportImage, visaDocument, visaStatus, visaType, specialRequests, isMainTraveler, reservationStatus, childRoom, infantSeat, profile_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if ($insertStmt) {
                        $insertStmt->bind_param('sssssssssssssssssssss',
                            $bookingId, $travelerType, $title, $firstName, $lastName,
                            $birthDateVal, $gender, $nationality, $passportNumber,
                            $passportIssueDateVal, $passportExpiryVal, $passportImage,
                            $visaDocument, $visaStatus, $visaType, $specialRequests,
                            $isMainTraveler, $reservationStatus, $childRoom, $infantSeat, $profileSource
                        );
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
            }
        }

        send_success_response([], 'Rejection acknowledged and booking status restored successfully');
    } catch (Exception $e) {
        send_error_response('Failed to acknowledge rejection: ' . $e->getMessage());
    }
}

/**
 * 룸 옵션 수정
 */
function updateRoomOptions($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        // 예약 정보 조회 및 소유권 확인
        $checkSql = "SELECT bookingId, accountId, selectedOptions FROM bookings WHERE bookingId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found or access denied', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        // selectedRooms 파싱
        $selectedRooms = isset($input['selectedRooms']) ? json_decode($input['selectedRooms'], true) : [];
        if (!is_array($selectedRooms)) {
            $selectedRooms = [];
        }

        // 기존 selectedOptions 가져오기
        $existingOptions = [];
        if (!empty($booking['selectedOptions'])) {
            $existingOptions = json_decode($booking['selectedOptions'], true);
            if (!is_array($existingOptions)) {
                $existingOptions = [];
            }
        }

        // selectedRooms 업데이트
        $existingOptions['selectedRooms'] = $selectedRooms;

        // JSON으로 저장
        $updatedOptionsJson = json_encode($existingOptions, JSON_UNESCAPED_UNICODE);

        $updateSql = "UPDATE bookings SET selectedOptions = ?, updatedAt = NOW() WHERE bookingId = ? AND accountId = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('ssi', $updatedOptionsJson, $bookingId, $agentAccountId);
        $updateStmt->execute();

        if ($updateStmt->affected_rows >= 0) {
            send_success_response(['updated' => true], 'Room options updated successfully');
        } else {
            send_error_response('Failed to update room options', 500);
        }

        $updateStmt->close();

    } catch (Exception $e) {
        send_error_response('Failed to update room options: ' . $e->getMessage(), 500);
    }
}

/**
 * Step 2: 결제 정보만 업데이트
 * - 예약 생성 2단계 분리를 위한 API
 * - Step 1에서 생성된 예약에 결제 정보 추가
 */
function updatePaymentInfo($conn, $input) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $files = $_FILES ?? [];

        // 필수 필드 검증
        $bookingId = trim((string)($input['bookingId'] ?? ''));
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        // 예약 존재 및 소유권 확인
        $checkStmt = $conn->prepare("SELECT accountId, bookingStatus, totalAmount, departureDate, adults, children, visaFee FROM bookings WHERE bookingId = ?");
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $booking = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            send_error_response('Booking not found', 404);
        }
        if ((int)$booking['accountId'] !== $agentAccountId) {
            send_error_response('Unauthorized access to this booking', 403);
        }

        // 결제 금액 정보
        $totalAmount = (float)($booking['totalAmount'] ?? 0);
        $departureDate = $booking['departureDate'] ?? null;
        $adults = (int)($booking['adults'] ?? 0);
        $children = (int)($booking['children'] ?? 0);
        $visaFee = (float)($booking['visaFee'] ?? 0);

        // 출발일까지 남은 일수 계산
        $daysUntilDeparture = null;
        if (!empty($departureDate)) {
            $depDateTime = new DateTime($departureDate);
            $todayDateTime = new DateTime();
            $todayDateTime->setTime(0, 0, 0);
            $depDateTime->setTime(0, 0, 0);
            $daysUntilDeparture = (int)$todayDateTime->diff($depDateTime)->format('%r%a');
        }

        // ========== 결제 규칙 (승인일 기준 - 생성 시점에서는 임시값 사용) ==========
        // 규칙 1: <34일 → Full Payment만, 3시간 데드라인
        // 규칙 2: 34~39일 → Full Payment만, +24시간
        // 규칙 3: 40~44일 → Middle 또는 Full 선택
        // 규칙 4: >44일 → Staged/Middle/Full 선택
        // ※ 최종 deadline은 관리자 승인 시 approvedAt 기준으로 재계산됨

        require_once __DIR__ . '/../../../backend/lib/deadline_calculator.php';

        $userRequestedPaymentType = $input['paymentType'] ?? 'staged';
        if (!in_array($userRequestedPaymentType, ['staged', 'middle', 'full'])) {
            $userRequestedPaymentType = 'staged';
        }

        // 유효한 결제 타입 검증
        $validTypes = getValidPaymentTypes($daysUntilDeparture ?? 999);
        if (!in_array($userRequestedPaymentType, $validTypes)) {
            $userRequestedPaymentType = $validTypes[0];
        }

        // 임시 승인일 = NOW() (실제 deadline은 승인 시 재계산)
        $tempApprovalDate = date('Y-m-d H:i:s');
        $deadlines = calculateBookingDeadlines($userRequestedPaymentType, $daysUntilDeparture ?? 999, $departureDate, $tempApprovalDate);
        $paymentType = $deadlines['paymentType'];

        if ($paymentType === 'full') {
            $downPaymentAmount = 0;
            $downPaymentDueDate = null;
            $advancePaymentAmount = 0;
            $advancePaymentDueDate = null;
            $balanceAmount = 0;
            $balanceDueDate = null;
            $fullPaymentAmount = $totalAmount;
            $fullPaymentDueDate = $deadlines['fullPaymentDueDate'];
        } else if ($paymentType === 'middle') {
            $travelerCount = $adults + $children;
            $middleAmount = (5000 * $travelerCount) + (10000 * $travelerCount) + $visaFee;
            $downPaymentAmount = $middleAmount;
            $downPaymentDueDate = $deadlines['secondPaymentDueDate']; // middle deadline
            $advancePaymentAmount = $middleAmount;
            $advancePaymentDueDate = null;
            $balanceAmount = max(0, $totalAmount - $middleAmount);
            $balanceDueDate = $deadlines['balanceDueDate'];
            $fullPaymentAmount = null;
            $fullPaymentDueDate = null;
        } else {
            // Staged
            $downPaymentAmount = isset($input['downPaymentAmount']) ? (float)$input['downPaymentAmount'] : 5000 * ($adults + $children);
            $downPaymentDueDate = $deadlines['downPaymentDueDate'];
            $advancePaymentAmount = isset($input['advancePaymentAmount']) ? (float)$input['advancePaymentAmount'] : (10000 * ($adults + $children)) + $visaFee;
            $advancePaymentDueDate = $deadlines['secondPaymentDueDate'];
            $balanceAmount = isset($input['balanceAmount']) ? (float)$input['balanceAmount'] : max(0, $totalAmount - $downPaymentAmount - $advancePaymentAmount);
            $balanceDueDate = $deadlines['balanceDueDate'];
            $fullPaymentAmount = null;
            $fullPaymentDueDate = null;
        }

        // 파일 업로드 처리
        $downPaymentFilePath = null;
        $fullPaymentFilePath = null;
        $middlePaymentFilePath = null;

        if ($paymentType === 'staged' && isset($files['downPaymentFile']) && $files['downPaymentFile']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../../uploads/payment/down/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = strtolower(pathinfo($files['downPaymentFile']['name'], PATHINFO_EXTENSION));
            $extension = preg_replace('/[^a-z0-9]/', '', $extension);
            $extension = $extension ? '.' . $extension : '';
            $fileName = 'downPayment_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($files['downPaymentFile']['tmp_name'], $uploadPath)) {
                $downPaymentFilePath = 'uploads/payment/down/' . $fileName;
            }
        }

        if ($paymentType === 'full' && isset($files['fullPaymentFile']) && $files['fullPaymentFile']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../../uploads/payment/full/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = strtolower(pathinfo($files['fullPaymentFile']['name'], PATHINFO_EXTENSION));
            $extension = preg_replace('/[^a-z0-9]/', '', $extension);
            $extension = $extension ? '.' . $extension : '';
            $fileName = 'fullPayment_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($files['fullPaymentFile']['tmp_name'], $uploadPath)) {
                $fullPaymentFilePath = 'uploads/payment/full/' . $fileName;
            }
        }

        // Middle Payment 파일 업로드 (downPaymentFilePath 필드에 저장)
        if ($paymentType === 'middle' && isset($files['middlePaymentFile']) && $files['middlePaymentFile']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../../uploads/payment/middle/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = strtolower(pathinfo($files['middlePaymentFile']['name'], PATHINFO_EXTENSION));
            $extension = preg_replace('/[^a-z0-9]/', '', $extension);
            $extension = $extension ? '.' . $extension : '';
            $fileName = 'middlePayment_' . $bookingId . '_' . time() . '_' . uniqid() . $extension;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($files['middlePaymentFile']['tmp_name'], $uploadPath)) {
                $middlePaymentFilePath = 'uploads/payment/middle/' . $fileName;
                // Middle Payment 파일은 downPaymentFilePath에 저장
                $downPaymentFilePath = $middlePaymentFilePath;
            }
        }

        // Create payment records in new booking_payments table
        $amounts = [];
        $dueDates = [];

        switch ($paymentType) {
            case 'staged':
                $amounts = [
                    'down' => $downPaymentAmount,
                    'second' => $advancePaymentAmount,
                    'balance' => $balanceAmount
                ];
                $dueDates = [
                    'down' => $downPaymentDueDate,
                    'second' => $advancePaymentDueDate,
                    'balance' => $balanceDueDate
                ];
                break;
            case 'middle':
                $amounts = [
                    'middle' => $downPaymentAmount,  // Middle payment stored in downPaymentAmount
                    'middle_balance' => $balanceAmount
                ];
                $dueDates = [
                    'middle' => $downPaymentDueDate,
                    'middle_balance' => $balanceDueDate
                ];
                break;
            case 'full':
                $amounts = ['full' => $fullPaymentAmount];
                $dueDates = ['full' => $fullPaymentDueDate];
                break;
        }

        // Delete existing payments and create new ones
        deletePaymentsForBooking($conn, $bookingId);
        createPaymentsForBooking($conn, $bookingId, $paymentType, $amounts, $dueDates);

        // UPDATE 쿼리 구성 (결제 정보 업데이트) - Legacy columns for backward compatibility
        $updateFields = [
            'bookingStatus = ?',
            'paymentType = ?',
            'downPaymentAmount = ?',
            'downPaymentDueDate = ?',
            'advancePaymentAmount = ?',
            'advancePaymentDueDate = ?',
            'balanceAmount = ?',
            'balanceDueDate = ?',
            'fullPaymentAmount = ?',
            'fullPaymentDueDate = ?',
            'updatedAt = NOW()'
        ];
        $newBookingStatus = 'pending';
        $params = [
            $newBookingStatus,
            $paymentType,
            $downPaymentAmount,
            $downPaymentDueDate,
            $advancePaymentAmount,
            $advancePaymentDueDate,
            $balanceAmount,
            $balanceDueDate,
            $fullPaymentAmount,
            $fullPaymentDueDate
        ];
        $types = 'ssdsdsdsds';

        // 파일 경로 추가
        if ($downPaymentFilePath) {
            $updateFields[] = 'downPaymentFile = ?';
            $params[] = $downPaymentFilePath;
            $types .= 's';
        }
        if ($fullPaymentFilePath) {
            $updateFields[] = 'fullPaymentFile = ?';
            $params[] = $fullPaymentFilePath;
            $types .= 's';
        }

        $params[] = $bookingId;
        $types .= 's';

        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE bookingId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update payment info: ' . $stmt->error);
        }
        $stmt->close();

        // 예약 이력 추가
        try {
            $historyStmt = $conn->prepare("
                INSERT INTO booking_history (bookingId, action, description, createdAt, createdBy)
                VALUES (?, 'payment_info_updated', ?, NOW(), ?)
            ");
            $historyDesc = "Payment info updated: type={$paymentType}";
            $historyStmt->bind_param('ssi', $bookingId, $historyDesc, $agentAccountId);
            $historyStmt->execute();
            $historyStmt->close();
        } catch (Exception $e) {
            // 이력 저장 실패는 무시
        }

        send_success_response([
            'bookingId' => $bookingId,
            'paymentType' => $paymentType,
            'bookingStatus' => $newBookingStatus
        ], 'Payment info updated successfully');

    } catch (Exception $e) {
        send_error_response('Failed to update payment info: ' . $e->getMessage(), 500);
    }
}

/**
 * 미완료 예약 삭제
 * - Step 2 (결제 페이지)에서 이탈 시 예약을 삭제
 * - booking_travelers도 함께 삭제
 */
function deleteIncompleteReservation($conn, $input) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        // 필수 필드 검증
        $bookingId = trim((string)($input['bookingId'] ?? ''));
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        // 예약 존재 및 소유권 확인
        $checkStmt = $conn->prepare("SELECT accountId, bookingStatus FROM bookings WHERE bookingId = ?");
        $checkStmt->bind_param('s', $bookingId);
        $checkStmt->execute();
        $booking = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$booking) {
            // 이미 삭제되었거나 없는 예약
            send_success_response(['deleted' => false, 'reason' => 'Booking not found']);
            return;
        }

        if ((int)$booking['accountId'] !== $agentAccountId) {
            send_error_response('Unauthorized access to this booking', 403);
        }

        // draft 상태인 경우만 삭제 허용
        if ($booking['bookingStatus'] !== 'draft') {
            send_success_response(['deleted' => false, 'reason' => 'Only draft bookings can be deleted']);
            return;
        }

        // 트랜잭션 시작
        $conn->begin_transaction();

        try {
            // booking_travelers 삭제
            $deleteTravelersStmt = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
            $deleteTravelersStmt->bind_param('s', $bookingId);
            $deleteTravelersStmt->execute();
            $deleteTravelersStmt->close();

            // bookings 삭제
            $deleteBookingStmt = $conn->prepare("DELETE FROM bookings WHERE bookingId = ?");
            $deleteBookingStmt->bind_param('s', $bookingId);
            $deleteBookingStmt->execute();
            $deletedRows = $deleteBookingStmt->affected_rows;
            $deleteBookingStmt->close();

            $conn->commit();

            send_success_response([
                'deleted' => $deletedRows > 0,
                'bookingId' => $bookingId
            ], 'Incomplete reservation deleted successfully');

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        send_error_response('Failed to delete reservation: ' . $e->getMessage(), 500);
    }
}

/**
 * 항공사명으로 옵션 조회 (예약 페이지용)
 */
function getAirlineOptionsByName($conn, $input) {
    $airlineName = $input['airlineName'] ?? '';

    if (empty($airlineName)) {
        send_success_response(['categories' => []]);
        return;
    }

    // 카테고리 조회
    $catSql = "SELECT category_id, category_name, category_name_en
               FROM airline_option_categories
               WHERE airline_name = ? AND is_active = 1
               ORDER BY sort_order, category_id";
    $catStmt = $conn->prepare($catSql);
    $catStmt->bind_param('s', $airlineName);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    $categories = [];
    while ($cat = $catResult->fetch_assoc()) {
        // 각 카테고리의 옵션 조회
        $optSql = "SELECT option_id, option_name, option_name_en, price
                   FROM airline_options
                   WHERE category_id = ? AND is_active = 1
                   ORDER BY sort_order, option_id";
        $optStmt = $conn->prepare($optSql);
        $optStmt->bind_param('i', $cat['category_id']);
        $optStmt->execute();
        $optResult = $optStmt->get_result();

        $options = [];
        while ($opt = $optResult->fetch_assoc()) {
            $opt['price'] = floatval($opt['price']);
            $opt['option_id'] = intval($opt['option_id']);
            $options[] = $opt;
        }
        $optStmt->close();

        $cat['category_id'] = intval($cat['category_id']);
        $cat['options'] = $options;
        $categories[] = $cat;
    }
    $catStmt->close();

    send_success_response(['categories' => $categories]);
}

// ========== Extra Options 함수들 ==========

/**
 * Extra Options 리스트 조회 (Agent)
 */
function getExtraOptionsList($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    try {
        $sql = "SELECT b.bookingId, b.departureDate, b.flightOptionFee, b.bookingStatus,
                       b.adults, b.children, b.infants,
                       p.packageName,
                       DATE_ADD(b.departureDate, INTERVAL (COALESCE(p.duration_days, p.durationDays, 1) - 1) DAY) as returnDate,
                       bop.option_payment_status, bop.option_payment_amount
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN booking_option_payments bop ON b.bookingId = bop.booking_id
                WHERE b.agentId IN (SELECT id FROM agent WHERE accountId = ?)
                  AND b.bookingStatus NOT IN ('draft','cancelled')
                ORDER BY b.createdAt DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $agentAccountId);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $pax = intval($row['adults'] ?? 0) + intval($row['children'] ?? 0) + intval($row['infants'] ?? 0);
            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                'departureDate' => $row['departureDate'],
                'returnDate' => $row['returnDate'] ?? '',
                'pax' => $pax,
                'bookingStatus' => $row['bookingStatus'],
                'flightOptionFee' => floatval($row['flightOptionFee'] ?? 0),
                'optionPaymentStatus' => $row['option_payment_status'] ?? 'not_set',
                'optionPaymentAmount' => floatval($row['option_payment_amount'] ?? 0)
            ];
        }
        $stmt->close();

        send_success_response(['bookings' => $bookings]);
    } catch (Exception $e) {
        send_error_response('Failed to load extra options list: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 상세 조회 (Agent)
 */
function getExtraOptionsDetail($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    try {
        // 예약 기본 정보 조회 + 권한 확인
        $sql = "SELECT b.bookingId, b.departureDate, b.flightOptionFee,
                       b.bookingStatus, b.adults, b.children, b.infants,
                       b.packageId, p.packageName,
                       DATE_ADD(b.departureDate, INTERVAL (COALESCE(p.duration_days, p.durationDays, 1) - 1) DAY) as returnDate
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE b.bookingId = ?
                  AND b.agentId IN (SELECT id FROM agent WHERE accountId = ?)
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $bookingId, $agentAccountId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) send_error_response('Reservation not found or access denied', 404);

        // Traveler 정보 조회
        $travelersStmt = $conn->prepare("SELECT * FROM booking_travelers WHERE transactNo = ? ORDER BY CASE travelerType WHEN 'adult' THEN 1 WHEN 'child' THEN 2 WHEN 'infant' THEN 3 ELSE 99 END, isMainTraveler DESC, bookingTravelerId ASC");
        $travelersStmt->bind_param('s', $bookingId);
        $travelersStmt->execute();
        $travelersResult = $travelersStmt->get_result();
        $travelers = [];
        while ($t = $travelersResult->fetch_assoc()) {
            $travelers[] = [
                'firstName' => $t['firstName'] ?? '',
                'lastName' => $t['lastName'] ?? '',
                'travelerType' => $t['travelerType'] ?? 'adult',
                'isMainTraveler' => intval($t['isMainTraveler'] ?? 0)
            ];
        }
        $travelersStmt->close();

        // 여행자별 기존 옵션 조회
        $optionsSql = "SELECT traveler_index, option_id, price FROM booking_traveler_options WHERE booking_id = ?";
        $optionsStmt = $conn->prepare($optionsSql);
        $optionsStmt->bind_param('s', $bookingId);
        $optionsStmt->execute();
        $optionsResult = $optionsStmt->get_result();
        $travelerOptions = [];
        while ($opt = $optionsResult->fetch_assoc()) {
            $idx = intval($opt['traveler_index']);
            if (!isset($travelerOptions[$idx])) $travelerOptions[$idx] = [];
            $travelerOptions[$idx][] = [
                'optionId' => intval($opt['option_id']),
                'price' => floatval($opt['price'])
            ];
        }
        $optionsStmt->close();

        // 항공사 이름 추출 (package_flights에서)
        $airlineName = '';
        $flightStmt = $conn->prepare("SELECT airline_name FROM package_flights WHERE package_id = ? LIMIT 1");
        if ($flightStmt) {
            $flightStmt->bind_param('i', $booking['packageId']);
            $flightStmt->execute();
            $flightRow = $flightStmt->get_result()->fetch_assoc();
            if ($flightRow) $airlineName = $flightRow['airline_name'] ?? '';
            $flightStmt->close();
        }

        // 항공사 옵션 카테고리 조회
        $categories = [];
        if (!empty($airlineName)) {
            $catSql = "SELECT category_id, category_name, category_name_en FROM airline_option_categories WHERE airline_name = ? AND is_active = 1 ORDER BY sort_order, category_id";
            $catStmt = $conn->prepare($catSql);
            $catStmt->bind_param('s', $airlineName);
            $catStmt->execute();
            $catResult = $catStmt->get_result();
            while ($cat = $catResult->fetch_assoc()) {
                $optSql2 = "SELECT option_id, option_name, option_name_en, price FROM airline_options WHERE category_id = ? AND is_active = 1 ORDER BY sort_order, option_id";
                $optStmt2 = $conn->prepare($optSql2);
                $optStmt2->bind_param('i', $cat['category_id']);
                $optStmt2->execute();
                $optResult2 = $optStmt2->get_result();
                $opts = [];
                while ($o = $optResult2->fetch_assoc()) {
                    $o['price'] = floatval($o['price']);
                    $o['option_id'] = intval($o['option_id']);
                    $opts[] = $o;
                }
                $optStmt2->close();
                $cat['category_id'] = intval($cat['category_id']);
                $cat['options'] = $opts;
                $categories[] = $cat;
            }
            $catStmt->close();
        }

        // 옵션 결제 상태 조회
        $paymentInfo = null;
        $payStmt = $conn->prepare("SELECT * FROM booking_option_payments WHERE booking_id = ? LIMIT 1");
        $payStmt->bind_param('s', $bookingId);
        $payStmt->execute();
        $payRow = $payStmt->get_result()->fetch_assoc();
        $payStmt->close();
        if ($payRow) {
            $paymentInfo = [
                'status' => $payRow['option_payment_status'],
                'amount' => floatval($payRow['option_payment_amount']),
                'file' => $payRow['option_payment_file'],
                'fileName' => $payRow['option_payment_file_name'],
                'uploadedAt' => $payRow['option_payment_uploaded_at'],
                'confirmedAt' => $payRow['option_payment_confirmed_at'],
                'rejectedAt' => $payRow['option_payment_rejected_at'],
                'rejectionReason' => $payRow['option_payment_rejection_reason']
            ];
        }

        send_success_response([
            'booking' => [
                'bookingId' => $booking['bookingId'],
                'packageName' => $booking['packageName'] ?? '',
                'departureDate' => $booking['departureDate'],
                'returnDate' => $booking['returnDate'] ?? '',
                'pax' => intval($booking['adults'] ?? 0) + intval($booking['children'] ?? 0) + intval($booking['infants'] ?? 0),
                'adults' => intval($booking['adults'] ?? 0),
                'children' => intval($booking['children'] ?? 0),
                'infants' => intval($booking['infants'] ?? 0),
                'flightOptionFee' => floatval($booking['flightOptionFee'] ?? 0)
            ],
            'travelers' => $travelers,
            'travelerOptions' => $travelerOptions,
            'airlineName' => $airlineName,
            'categories' => $categories,
            'paymentInfo' => $paymentInfo
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to load extra options detail: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 저장 (Agent)
 */
function saveExtraOptions($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    $travelerOptions = $input['travelerOptions'] ?? [];
    $totalOptionFee = floatval($input['totalOptionFee'] ?? 0);

    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    // 권한 확인 + 출발일 조회
    $chk = $conn->prepare("SELECT bookingId, DATE(departureDate) as depDate, COALESCE(flightOptionFee, 0) as prevFee FROM bookings WHERE bookingId = ? AND agentId IN (SELECT id FROM agent WHERE accountId = ?) LIMIT 1");
    $chk->bind_param('si', $bookingId, $agentAccountId);
    $chk->execute();
    $chkRow = $chk->get_result()->fetch_assoc();
    if (!$chkRow) {
        $chk->close();
        send_error_response('Reservation not found or access denied', 404);
    }
    $chk->close();

    // 출발 7일 전까지만 변경 가능
    $depDate = $chkRow['depDate'] ?? '';
    if ($depDate !== '') {
        $depTs = strtotime($depDate);
        $cutoffTs = $depTs - (7 * 86400); // 7일 전
        if (time() >= $cutoffTs) {
            send_error_response('Extra options can only be changed up to 7 days before departure', 400);
        }
    }

    try {
        $conn->begin_transaction();

        // 기존 옵션 삭제
        $delStmt = $conn->prepare("DELETE FROM booking_traveler_options WHERE booking_id = ?");
        $delStmt->bind_param('s', $bookingId);
        $delStmt->execute();
        $delStmt->close();

        // 새 옵션 삽입
        if (!empty($travelerOptions)) {
            $insStmt = $conn->prepare("INSERT INTO booking_traveler_options (booking_id, traveler_index, option_id, price) VALUES (?, ?, ?, ?)");
            foreach ($travelerOptions as $item) {
                $travelerIndex = intval($item['travelerIndex']);
                $optionId = intval($item['optionId']);
                $price = floatval($item['price'] ?? 0);
                $insStmt->bind_param('siid', $bookingId, $travelerIndex, $optionId, $price);
                $insStmt->execute();
            }
            $insStmt->close();
        }

        // bookings.flightOptionFee 업데이트
        $feeStmt = $conn->prepare("UPDATE bookings SET flightOptionFee = ? WHERE bookingId = ?");
        $feeStmt->bind_param('ds', $totalOptionFee, $bookingId);
        $feeStmt->execute();
        $feeStmt->close();

        // booking_option_payments upsert — 옵션이 있으면 pending_payment, 없으면 not_set
        $newStatus = (!empty($travelerOptions) && $totalOptionFee > 0) ? 'pending_payment' : 'not_set';
        $upsertSql = "INSERT INTO booking_option_payments (booking_id, option_payment_status, option_payment_amount)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE
                           option_payment_status = VALUES(option_payment_status),
                           option_payment_amount = VALUES(option_payment_amount),
                           option_payment_file = NULL,
                           option_payment_file_name = NULL,
                           option_payment_uploaded_at = NULL,
                           option_payment_rejected_at = NULL,
                           option_payment_rejection_reason = NULL";
        $upsertStmt = $conn->prepare($upsertSql);
        $upsertStmt->bind_param('ssd', $bookingId, $newStatus, $totalOptionFee);
        $upsertStmt->execute();
        $upsertStmt->close();

        // booking_history에 이력 기록
        try {
            $prevFee = floatval($chkRow['prevFee'] ?? 0);

            $agentName = '';
            $anStmt = $conn->prepare("SELECT COALESCE(NULLIF(agencyName,''), CONCAT(fName,' ',lName)) as name FROM agent WHERE accountId = ? LIMIT 1");
            $anStmt->bind_param('i', $agentAccountId);
            $anStmt->execute();
            $anRow = $anStmt->get_result()->fetch_assoc();
            $anStmt->close();
            if ($anRow) $agentName = trim($anRow['name']);

            $desc = 'Extra options updated by Agent' . ($agentName ? " ({$agentName})" : '') . '. Option fee: ₱' . number_format($prevFee, 0) . ' → ₱' . number_format($totalOptionFee, 0);
            $hStmt = $conn->prepare("INSERT INTO booking_history (bookingId, description, changeType, changedBy, changedByType) VALUES (?, ?, 'extra_options', ?, 'agent')");
            $hStmt->bind_param('sss', $bookingId, $desc, $agentName);
            $hStmt->execute();
            $hStmt->close();
        } catch (Throwable $e) {
            error_log('Failed to log extra options history: ' . $e->getMessage());
        }

        $conn->commit();
        send_success_response(['status' => $newStatus, 'amount' => $totalOptionFee], 'Extra options saved successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to save extra options: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 결제 증빙 업로드 (Agent)
 */
function uploadOptionPaymentProof($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        send_error_response('File upload failed');
    }

    // 권한 확인
    $chk = $conn->prepare("SELECT bookingId FROM bookings WHERE bookingId = ? AND agentId IN (SELECT id FROM agent WHERE accountId = ?) LIMIT 1");
    $chk->bind_param('si', $bookingId, $agentAccountId);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        send_error_response('Reservation not found or access denied', 404);
    }
    $chk->close();

    try {
        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/../../../uploads/payment/options/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $originalFileName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            send_error_response('Invalid file type. Allowed: jpg, jpeg, png, gif, pdf');
        }

        $newFileName = 'option_' . $bookingId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            send_error_response('Failed to save uploaded file');
        }

        $filePath = 'uploads/payment/options/' . $newFileName;
        $now = date('Y-m-d H:i:s');

        // booking_option_payments 업데이트
        $sql = "UPDATE booking_option_payments
                SET option_payment_file = ?, option_payment_file_name = ?,
                    option_payment_uploaded_at = ?, option_payment_status = 'checking',
                    option_payment_rejected_at = NULL, option_payment_rejection_reason = NULL
                WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $filePath, $originalFileName, $now, $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response([
            'file' => $filePath,
            'fileName' => $originalFileName,
            'uploadedAt' => $now,
            'status' => 'checking'
        ], 'Payment proof uploaded successfully');
    } catch (Exception $e) {
        send_error_response('Failed to upload payment proof: ' . $e->getMessage(), 500);
    }
}

/**
 * Extra Options 결제 증빙 삭제 (Agent)
 */
function deleteOptionPaymentProof($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) send_error_response('Login required', 401);

    $bookingId = $input['bookingId'] ?? '';
    if (empty($bookingId)) send_error_response('Booking ID is required', 400);

    // 권한 확인 + 현재 파일 경로 조회
    $chk = $conn->prepare("SELECT bop.option_payment_file, bop.option_payment_status
                            FROM booking_option_payments bop
                            JOIN bookings b ON bop.booking_id = b.bookingId
                            WHERE bop.booking_id = ? AND b.agentId IN (SELECT id FROM agent WHERE accountId = ?)
                            LIMIT 1");
    $chk->bind_param('si', $bookingId, $agentAccountId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) send_error_response('Record not found or access denied', 404);

    // confirmed 상태에서는 삭제 불가
    if ($row['option_payment_status'] === 'confirmed') {
        send_error_response('Cannot delete proof file after confirmation', 403);
    }

    try {
        // 파일 삭제
        if (!empty($row['option_payment_file'])) {
            $fullPath = __DIR__ . '/../../../' . $row['option_payment_file'];
            if (file_exists($fullPath)) @unlink($fullPath);
        }

        // DB 업데이트
        $stmt = $conn->prepare("UPDATE booking_option_payments
                                SET option_payment_file = NULL, option_payment_file_name = NULL,
                                    option_payment_uploaded_at = NULL, option_payment_status = 'pending_payment'
                                WHERE booking_id = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();

        send_success_response(['status' => 'pending_payment'], 'Payment proof deleted successfully');
    } catch (Exception $e) {
        send_error_response('Failed to delete payment proof: ' . $e->getMessage(), 500);
    }
}

/**
 * 여행자 옵션 저장
 */
function saveTravelerOptions($conn, $input) {
    $bookingId = $input['bookingId'] ?? '';
    $travelerOptions = $input['travelerOptions'] ?? [];

    if (empty($bookingId)) {
        send_error_response('Booking ID is required', 400);
        return;
    }

    try {
        $conn->begin_transaction();

        // 기존 옵션 삭제
        $delStmt = $conn->prepare("DELETE FROM booking_traveler_options WHERE booking_id = ?");
        $delStmt->bind_param('s', $bookingId);
        $delStmt->execute();
        $delStmt->close();

        // 새 옵션 삽입
        if (!empty($travelerOptions)) {
            $insStmt = $conn->prepare("INSERT INTO booking_traveler_options (booking_id, traveler_index, option_id, price) VALUES (?, ?, ?, ?)");

            foreach ($travelerOptions as $item) {
                $travelerIndex = intval($item['travelerIndex']);
                $optionId = intval($item['optionId']);
                $price = floatval($item['price'] ?? 0);

                $insStmt->bind_param('siid', $bookingId, $travelerIndex, $optionId, $price);
                $insStmt->execute();
            }
            $insStmt->close();
        }

        $conn->commit();
        send_success_response([], 'Traveler options saved successfully');

    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to save traveler options: ' . $e->getMessage(), 500);
    }
}

// ========== 비자 관리 헬퍼 함수들 ==========

if (!function_exists('__agent_visa_applications_has_column')) {
    function __agent_visa_applications_has_column(mysqli $conn, string $c): bool {
        $r = $conn->query("SHOW COLUMNS FROM visa_applications LIKE '$c'");
        return ($r && $r->num_rows > 0);
    }
}

if (!function_exists('__agent_ensure_visa_applications_updated_at')) {
    function __agent_ensure_visa_applications_updated_at(mysqli $conn): void {
        try {
            $t = $conn->query("SHOW TABLES LIKE 'visa_applications'");
            if (!$t || $t->num_rows === 0) return;
            if (__agent_visa_applications_has_column($conn, 'updatedAt')) return;
            $conn->query("ALTER TABLE visa_applications ADD COLUMN updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Throwable $e) {}
    }
}

if (!function_exists('__agent_mapVisaDbToUiStatus')) {
    function __agent_mapVisaDbToUiStatus(string $db): string {
        $db = strtolower(trim($db));
        if ($db === 'document_required' || $db === 'pending') return 'pending';
        if ($db === 'under_review') return 'reviewing';
        if ($db === 'approved' || $db === 'completed') return 'approved';
        if ($db === 'rejected') return 'rejected';
        return 'pending';
    }
}

if (!function_exists('__agent_mapVisaUiToDbStatus')) {
    function __agent_mapVisaUiToDbStatus(string $ui): string {
        $ui = strtolower(trim($ui));
        if ($ui === 'reviewing' || $ui === 'under_review') return 'under_review';
        if ($ui === 'approved') return 'approved';
        if ($ui === 'rejected') return 'rejected';
        if ($ui === 'pending' || $ui === 'document_required') return 'document_required';
        return $ui;
    }
}

if (!function_exists('__agent_extractVisaDocumentsFromNotes')) {
    function __agent_extractVisaDocumentsFromNotes($notes): array {
        if ($notes === null) return [];
        $txt = trim((string)$notes);
        if ($txt === '') return [];
        $j = json_decode($txt, true);
        if (is_array($j) && isset($j['documents']) && is_array($j['documents'])) {
            return $j['documents'];
        }
        return [];
    }
}

if (!function_exists('__agent_extractVisaFileFromNotes')) {
    function __agent_extractVisaFileFromNotes($notes): string {
        if ($notes === null) return '';
        $txt = trim((string)$notes);
        if ($txt === '') return '';
        $j = json_decode($txt, true);
        if (!is_array($j)) return '';
        $v = $j['visaFile'] ?? ($j['visa_file'] ?? ($j['visaUrl'] ?? ($j['visaDocument'] ?? '')));
        return trim((string)$v);
    }
}

if (!function_exists('__agent_mergeVisaNotesSetKey')) {
    function __agent_mergeVisaNotesSetKey($existingNotes, string $key, $value): string {
        $base = [];
        $txt = trim((string)($existingNotes ?? ''));
        if ($txt !== '') {
            $j = json_decode($txt, true);
            if (is_array($j)) $base = $j;
            else $base = ['notesText' => $txt];
        }
        $base[$key] = $value;
        return json_encode($base, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('__agent_computeVisaDerivedStatus')) {
    function __agent_computeVisaDerivedStatus(string $notesJson): string {
        $notesJson = trim($notesJson);
        $docs = __agent_extractVisaDocumentsFromNotes($notesJson);
        $visaFile = __agent_extractVisaFileFromNotes($notesJson);
        if (trim((string)$visaFile) !== '') return 'approved';

        $requiredNew = ['passport', 'visaApplicationForm', 'bankCertificate', 'bankStatement'];

        $hasNewStyleKeys = false;
        foreach ($requiredNew as $k) {
            if (array_key_exists($k, $docs)) {
                $hasNewStyleKeys = true;
                break;
            }
        }

        $presentNew = 0;
        foreach ($requiredNew as $k) {
            $p = isset($docs[$k]) ? trim((string)$docs[$k]) : '';
            if ($p !== '') $presentNew++;
        }

        $isNewStyleApp = $hasNewStyleKeys || $presentNew > 0;

        if ($isNewStyleApp) {
            if ($presentNew === count($requiredNew)) return 'reviewing';
            return 'rejected';
        }

        return 'pending';
    }
}

if (!function_exists('__agent_uploads_abs_from_rel')) {
    function __agent_uploads_abs_from_rel(string $rel): string {
        $rel = str_replace('\\', '/', trim($rel));
        if ($rel === '') return '';
        if (str_starts_with($rel, 'uploads/')) $rel = '/' . $rel;
        if (!str_starts_with($rel, '/uploads/')) return '';
        if (str_contains($rel, '..')) return '';
        $root = realpath(__DIR__ . '/../../..') ?: '';
        if ($root === '') return '';
        $abs = realpath($root . '/' . ltrim($rel, '/'));
        if ($abs === false) return '';
        $uploadsRoot = realpath($root . '/uploads');
        if ($uploadsRoot === false) return '';
        if (!str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR)) return '';
        return $abs;
    }
}

if (!function_exists('__agent_uploads_rel_normalize')) {
    function __agent_uploads_rel_normalize(string $p): string {
        $p = str_replace('\\', '/', trim($p));
        if ($p === '') return '';
        if (str_starts_with($p, 'uploads/')) $p = '/' . $p;
        if (!str_starts_with($p, '/uploads/')) return '';
        return $p;
    }
}

/**
 * 에이전트가 본인 예약의 비자 신청에 접근 가능한지 확인
 */
function __agent_verify_visa_ownership(mysqli $conn, int $agentAccountId, int $visaApplicationId): bool {
    $stmt = $conn->prepare("
        SELECT 1 FROM visa_applications v
        JOIN bookings b ON v.transactNo = b.bookingId
        WHERE v.applicationId = ? AND b.agentId = ?
        LIMIT 1
    ");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $visaApplicationId, $agentAccountId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($result);
}

// ========== 비자 관리 API 함수들 ==========

/**
 * 에이전트 비자 신청 목록 조회 (Group 비자만, 본인 예약만)
 * - bookingId 기준으로 그룹핑하여 반환
 * - 대표 신청자(첫 번째)와 동행자 목록을 함께 제공
 */
function getAgentVisaApplications($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        error_log("getAgentVisaApplications - agentAccountId: " . var_export($agentAccountId, true));
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        error_log("getAgentVisaApplications - before ensure_updated_at");
        __agent_ensure_visa_applications_updated_at($conn);
        error_log("getAgentVisaApplications - after ensure_updated_at");

        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        $whereConditions = ["v.visaType IN ('group', 'individual')", "b.agentId = ?"];
        $params = [$agentAccountId];
        $types = 'i';

        if (!empty($input['status'])) {
            $ui = strtolower((string)$input['status']);
            if ($ui === 'pending') {
                $whereConditions[] = "(v.status IN ('pending','document_required'))";
            } elseif ($ui === 'reviewing') {
                $whereConditions[] = "v.status = 'under_review'";
            } elseif ($ui === 'approved') {
                $whereConditions[] = "(v.status IN ('approved','completed'))";
            } elseif ($ui === 'rejected') {
                $whereConditions[] = "v.status = 'rejected'";
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // bookingId 기준 고유 건수 카운트
        $countSql = "SELECT COUNT(DISTINCT v.transactNo) as total
                     FROM visa_applications v
                     JOIN bookings b ON v.transactNo = b.bookingId
                     $whereClause";
        error_log("getAgentVisaApplications - countSql: $countSql, params: " . json_encode($params));
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) {
            error_log("getAgentVisaApplications - prepare failed: " . $conn->error);
            throw new Exception("Query prepare failed: " . $conn->error);
        }
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
        error_log("getAgentVisaApplications - totalCount (bookings): $totalCount");

        $sortOrder = $input['sortOrder'] ?? 'latest';
        $bookingOrderBy = $sortOrder === 'oldest'
            ? 'MIN(COALESCE(v.updatedAt, v.applicationDate)) ASC'
            : 'MAX(COALESCE(v.updatedAt, v.applicationDate)) DESC';

        // 페이지네이션용 bookingId 목록 조회
        $bookingIdsSql = "SELECT v.transactNo as bookingId
                          FROM visa_applications v
                          JOIN bookings b ON v.transactNo = b.bookingId
                          $whereClause
                          GROUP BY v.transactNo
                          ORDER BY $bookingOrderBy
                          LIMIT ? OFFSET ?";

        $bookingIdsParams = array_merge($params, [$limit, $offset]);
        $bookingIdsTypes = $types . 'ii';

        $bookingIdsStmt = $conn->prepare($bookingIdsSql);
        if (!$bookingIdsStmt) {
            throw new Exception("Booking IDs query prepare failed: " . $conn->error);
        }
        $bookingIdsStmt->bind_param($bookingIdsTypes, ...$bookingIdsParams);
        $bookingIdsStmt->execute();
        $bookingIdsResult = $bookingIdsStmt->get_result();

        $bookingIds = [];
        while ($row = $bookingIdsResult->fetch_assoc()) {
            $bookingIds[] = $row['bookingId'];
        }
        $bookingIdsStmt->close();

        if (empty($bookingIds)) {
            send_success_response([
                'applications' => [],
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => (int)$totalCount,
                    'limit' => $limit
                ]
            ]);
            return;
        }

        // 해당 bookingId들의 모든 신청자 정보 조회
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $dataSql = "SELECT
            v.applicationId as visaApplicationId,
            v.applicationNo,
            v.applicantName,
            v.visaType,
            v.status,
            COALESCE(v.updatedAt, v.applicationDate) as createdAt,
            v.transactNo as bookingId,
            b.departureDate as travelStartDate,
            b.createdAt as dateBooked,
            a.agencyName
        FROM visa_applications v
        JOIN bookings b ON v.transactNo = b.bookingId
        LEFT JOIN agent a ON b.agentId = a.accountId
        WHERE v.transactNo IN ($placeholders) AND v.visaType IN ('group', 'individual')
        ORDER BY v.transactNo, v.applicationId ASC";

        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) {
            throw new Exception("Data query prepare failed: " . $conn->error);
        }
        $dataTypes = str_repeat('s', count($bookingIds));
        $dataStmt->bind_param($dataTypes, ...$bookingIds);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        // bookingId 기준으로 그룹핑
        $grouped = [];
        while ($row = $dataResult->fetch_assoc()) {
            $bookingId = $row['bookingId'];
            $uiStatus = __agent_mapVisaDbToUiStatus((string)($row['status'] ?? 'pending'));
            $createdAt = $row['createdAt'] ?? '';
            if ($createdAt) {
                $createdAt = str_replace('T', ' ', $createdAt);
                if (strlen($createdAt) >= 16) $createdAt = substr($createdAt, 0, 16);
            }

            $traveler = [
                'visaApplicationId' => $row['visaApplicationId'] ?? '',
                'applicationNo' => $row['applicationNo'] ?? '',
                'applicantName' => $row['applicantName'] ?? '',
                'visaType' => $row['visaType'] ?? '',
                'status' => $uiStatus,
                'createdAt' => $createdAt
            ];

            if (!isset($grouped[$bookingId])) {
                $grouped[$bookingId] = [
                    'bookingId' => $bookingId,
                    'travelStartDate' => $row['travelStartDate'] ?? '',
                    'dateBooked' => $row['dateBooked'] ?? '',
                    'agencyName' => $row['agencyName'] ?? '',
                    'travelers' => []
                ];
            }
            $grouped[$bookingId]['travelers'][] = $traveler;
        }
        $dataStmt->close();

        // bookingIds 순서대로 정렬하여 결과 생성
        $applications = [];
        $rowNum = $totalCount - $offset;
        foreach ($bookingIds as $bookingId) {
            if (isset($grouped[$bookingId])) {
                $group = $grouped[$bookingId];
                $group['travelerCount'] = count($group['travelers']);
                $group['rowNum'] = $rowNum--;

                // 대표 신청자 정보 (첫 번째)
                if (!empty($group['travelers'])) {
                    $rep = $group['travelers'][0];
                    $group['representativeName'] = $rep['applicantName'];
                    $group['representativeStatus'] = $rep['status'];
                    $group['representativeId'] = $rep['visaApplicationId'];
                    $group['representativeCreatedAt'] = $rep['createdAt'];
                    $group['representativeVisaType'] = $rep['visaType'];
                }

                $applications[] = $group;
            }
        }

        send_success_response([
            'applications' => $applications,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'totalCount' => (int)$totalCount,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get visa applications: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 신청 상세 조회
 */
function getAgentVisaApplicationDetail($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        if (empty($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        $sql = "SELECT
            v.*,
            c.fName,
            c.lName,
            c.emailAddress,
            c.contactNo
        FROM visa_applications v
        LEFT JOIN client c ON v.accountId = c.accountId
        WHERE v.applicationId = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $visaApplicationId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Visa application not found', 404);
        }

        $application = $result->fetch_assoc();
        $stmt->close();

        // booking 정보 가져오기
        $bookingId = $application['transactNo'] ?? '';
        if (!empty($bookingId)) {
            $bkStmt = $conn->prepare("
                SELECT
                    bookingId, packageName, departureDate,
                    totalAmount, paymentStatus, bookingStatus,
                    createdAt as bookingCreatedAt
                FROM bookings
                WHERE bookingId = ?
                LIMIT 1
            ");
            if ($bkStmt) {
                $bkStmt->bind_param('s', $bookingId);
                $bkStmt->execute();
                $bookingRow = $bkStmt->get_result()->fetch_assoc();
                $bkStmt->close();

                if ($bookingRow) {
                    $application['bookingId'] = $bookingRow['bookingId'] ?? '';
                    if (!empty($bookingRow['packageName'])) {
                        $application['packageName'] = $bookingRow['packageName'];
                    }
                    $application['departureDate'] = $bookingRow['departureDate'] ?? '';
                    $application['bookingStatus'] = $bookingRow['bookingStatus'] ?? '';
                }
            }
        }

        if (empty($application['packageName']) && !empty($application['destinationCountry'])) {
            $application['packageName'] = $application['destinationCountry'];
        }

        // booking_travelers에서 신청자 정보 보강
        if (!empty($bookingId)) {
            $travelerRow = null;
            $tid = intval($application['bookingTravelerId'] ?? 0);

            if ($tid > 0) {
                $t = $conn->prepare("
                    SELECT
                        title, firstName, lastName, birthDate, gender, nationality,
                        passportNumber, passportIssueDate, passportExpiry, passportImage
                    FROM booking_travelers
                    WHERE transactNo = ? AND bookingTravelerId = ?
                    LIMIT 1
                ");
                if ($t) {
                    $t->bind_param('si', $bookingId, $tid);
                    $t->execute();
                    $travelerRow = $t->get_result()->fetch_assoc();
                    $t->close();
                }
            }

            if (!$travelerRow) {
                $t3 = $conn->prepare("
                    SELECT
                        title, firstName, lastName, birthDate, gender, nationality,
                        passportNumber, passportIssueDate, passportExpiry, passportImage
                    FROM booking_travelers
                    WHERE transactNo = ?
                    ORDER BY isMainTraveler DESC, bookingTravelerId ASC
                    LIMIT 1
                ");
                if ($t3) {
                    $t3->bind_param('s', $bookingId);
                    $t3->execute();
                    $travelerRow = $t3->get_result()->fetch_assoc();
                    $t3->close();
                }
            }

            if ($travelerRow) {
                $tr = $travelerRow;
                if (isset($tr['firstName'])) $application['fName'] = $tr['firstName'];
                if (isset($tr['lastName'])) $application['lName'] = $tr['lastName'];
                if (isset($tr['title'])) $application['honorific'] = $tr['title'];
                if (isset($tr['gender'])) $application['gender'] = $tr['gender'];
                if (isset($tr['nationality'])) $application['nationality'] = $tr['nationality'];
                if (isset($tr['passportNumber'])) $application['passportNumber'] = $tr['passportNumber'];
                if (isset($tr['passportIssueDate'])) $application['passportIssueDate'] = $tr['passportIssueDate'];
                if (isset($tr['passportExpiry'])) $application['passportExpiryDate'] = $tr['passportExpiry'];

                if (!empty($tr['birthDate'])) {
                    try {
                        $bd = new DateTime($tr['birthDate']);
                        $today = new DateTime('today');
                        $application['age'] = $bd->diff($today)->y;
                        $application['birthDate'] = $tr['birthDate'];
                    } catch (Exception $e) {}
                }

                if (!empty($tr['passportImage'])) {
                    $pi = (string)$tr['passportImage'];
                    $piTrim = trim($pi);
                    if (strpos($piTrim, 'data:') === 0) {
                        $application['passportPhoto'] = $piTrim;
                    } else if (preg_match('/^[A-Za-z0-9+\\/]+=*$/', $piTrim)) {
                        $application['passportPhoto'] = 'data:image/jpeg;base64,' . $piTrim;
                    } else {
                        $application['passportPhoto'] = $piTrim;
                    }
                }
            }
        }

        // 상태 정규화
        $application['status'] = __agent_mapVisaDbToUiStatus((string)($application['status'] ?? 'pending'));

        send_success_response([
            'application' => $application
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get visa application detail: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 서류 업로드
 */
function updateAgentVisaDocument($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        __agent_ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $docKey = trim((string)($input['docKey'] ?? ''));
        $filePath = trim((string)($input['filePath'] ?? ''));

        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        if ($docKey === '') {
            send_error_response('docKey is required');
        }
        if ($filePath === '') {
            send_error_response('filePath is required');
        }

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        // 허용된 문서 키 목록
        $allowed = ['passport', 'visaApplicationForm', 'bankCertificate', 'bankStatement', 'additionalDocuments'];
        $docKeyNorm = $docKey;
        $map = [
            'passportcopy' => 'passport',
            'bankcertificate' => 'bankCertificate',
            'bankstatement' => 'bankStatement',
            'visaapplicationform' => 'visaApplicationForm',
            'additionaldocuments' => 'additionalDocuments'
        ];
        $lowerKey = strtolower($docKey);
        if (isset($map[$lowerKey])) {
            $docKeyNorm = $map[$lowerKey];
        }

        if (!in_array($docKeyNorm, $allowed, true)) {
            send_error_response('Invalid docKey: ' . $docKeyNorm);
        }

        // 기존 notes 읽기
        $existingNotes = '';
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if (!$st0) send_error_response('Failed to prepare read');
        $st0->bind_param('i', $visaApplicationId);
        $st0->execute();
        $existingNotes = (string)($st0->get_result()->fetch_assoc()['notes'] ?? '');
        $st0->close();

        $j = [];
        if (trim($existingNotes) !== '') {
            $tmp = json_decode($existingNotes, true);
            if (is_array($tmp)) $j = $tmp;
        }
        if (!isset($j['documents']) || !is_array($j['documents'])) $j['documents'] = [];
        $j['documents'][$docKeyNorm] = $filePath;

        $finalNotes = json_encode($j, JSON_UNESCAPED_UNICODE);
        $derivedUi = __agent_computeVisaDerivedStatus($finalNotes);
        $derivedDb = __agent_mapVisaUiToDbStatus($derivedUi);

        $sql = __agent_visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $st = $conn->prepare($sql);
        if (!$st) send_error_response('Failed to prepare update');
        $st->bind_param('ssi', $finalNotes, $derivedDb, $visaApplicationId);
        $st->execute();
        $st->close();

        send_success_response([
            'status' => $derivedUi
        ], 'Document uploaded');
    } catch (Exception $e) {
        send_error_response('Failed to update visa document: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 서류 삭제
 */
function deleteAgentVisaDocument($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        __agent_ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $docKey = strtolower(trim((string)($input['docKey'] ?? $input['documentKey'] ?? '')));

        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        if ($docKey === '') {
            send_error_response('docKey is required');
        }

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        $map = [
            'passportcopy' => 'passport',
            'bankcertificate' => 'bankCertificate',
            'bankstatement' => 'bankStatement',
            'visaapplicationform' => 'visaApplicationForm',
            'additionaldocuments' => 'additionalDocuments'
        ];
        $docKeyNorm = $map[$docKey] ?? $docKey;
        $allowed = ['passport', 'bankCertificate', 'bankStatement', 'visaApplicationForm', 'additionalDocuments'];
        if (!in_array($docKeyNorm, $allowed, true)) {
            send_error_response('Invalid docKey: ' . $docKeyNorm);
        }

        // 기존 notes 읽기
        $existingNotes = '';
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if (!$st0) send_error_response('Failed to prepare read');
        $st0->bind_param('i', $visaApplicationId);
        $st0->execute();
        $existingNotes = (string)($st0->get_result()->fetch_assoc()['notes'] ?? '');
        $st0->close();

        $j = [];
        if (trim($existingNotes) !== '') {
            $tmp = json_decode($existingNotes, true);
            if (is_array($tmp)) $j = $tmp;
        }
        if (!isset($j['documents']) || !is_array($j['documents'])) $j['documents'] = [];
        $j['documents'][$docKeyNorm] = '';

        $finalNotes = json_encode($j, JSON_UNESCAPED_UNICODE);
        $derivedUi = __agent_computeVisaDerivedStatus($finalNotes);
        $derivedDb = __agent_mapVisaUiToDbStatus($derivedUi);

        $sql = __agent_visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $st = $conn->prepare($sql);
        if (!$st) send_error_response('Failed to prepare update');
        $st->bind_param('ssi', $finalNotes, $derivedDb, $visaApplicationId);
        $st->execute();
        $st->close();

        send_success_response([
            'status' => $derivedUi
        ], 'Document deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete visa document: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 파일 업로드
 */
function updateAgentVisaFile($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        __agent_ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $visaFilePath = $input['visaFilePath'] ?? ($input['visaFile'] ?? ($input['filePath'] ?? null));

        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        $vf = trim((string)($visaFilePath ?? ''));
        if ($vf === '') {
            send_error_response('visaFilePath is required');
        }

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        // 기존 notes 읽고 merge
        $existingNotes = null;
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if ($st0) {
            $st0->bind_param('i', $visaApplicationId);
            $st0->execute();
            $existingNotes = ($st0->get_result()->fetch_assoc()['notes'] ?? null);
            $st0->close();
        }

        $finalNotes = __agent_mergeVisaNotesSetKey($existingNotes, 'visaFile', $vf);

        // 비자 파일 업로드 시점에 상태는 "발급 완료"로 자동 전환
        $dbStatus = __agent_mapVisaUiToDbStatus('approved');
        $sql = __agent_visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare update');
        }
        $stmt->bind_param('ssi', $finalNotes, $dbStatus, $visaApplicationId);
        $stmt->execute();
        $stmt->close();

        send_success_response([], 'Visa file updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update visa file: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 파일 삭제
 */
function deleteAgentVisaFile($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        __agent_ensure_visa_applications_updated_at($conn);

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        // 기존 notes 읽기
        $existingNotes = '';
        $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
        if (!$st0) send_error_response('Failed to prepare read');
        $st0->bind_param('i', $visaApplicationId);
        $st0->execute();
        $existingNotes = (string)($st0->get_result()->fetch_assoc()['notes'] ?? '');
        $st0->close();

        $j = [];
        if (trim($existingNotes) !== '') {
            $tmp = json_decode($existingNotes, true);
            if (is_array($tmp)) $j = $tmp;
        }

        // 현재 파일 경로 추출 후 실제 파일 삭제(uploads 하위만)
        $visaFile = __agent_extractVisaFileFromNotes($existingNotes);
        $visaFileNorm = __agent_uploads_rel_normalize($visaFile);
        if ($visaFileNorm !== '') {
            $abs = __agent_uploads_abs_from_rel($visaFileNorm);
            if ($abs !== '' && is_file($abs) && is_writable($abs)) {
                @unlink($abs);
            }
        }

        // notes에서 visaFile 키 제거
        if (isset($j['visaFile'])) unset($j['visaFile']);
        if (isset($j['visa_file'])) unset($j['visa_file']);
        if (isset($j['visaUrl'])) unset($j['visaUrl']);
        if (isset($j['visaDocument'])) unset($j['visaDocument']);

        $finalNotes = json_encode($j, JSON_UNESCAPED_UNICODE);
        $derivedUi = __agent_computeVisaDerivedStatus($finalNotes);
        $derivedDb = __agent_mapVisaUiToDbStatus($derivedUi);

        $sql = __agent_visa_applications_has_column($conn, 'updatedAt')
            ? "UPDATE visa_applications SET notes = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET notes = ?, status = ? WHERE applicationId = ?";
        $st = $conn->prepare($sql);
        if (!$st) send_error_response('Failed to prepare update');
        $st->bind_param('ssi', $finalNotes, $derivedDb, $visaApplicationId);
        $st->execute();
        $st->close();

        send_success_response([
            'status' => $derivedUi
        ], 'Visa file deleted');
    } catch (Exception $e) {
        send_error_response('Failed to delete visa file: ' . $e->getMessage());
    }
}

/**
 * 에이전트 비자 visaSend 상태 업데이트 (Individual visa용)
 */
function updateAgentVisaSend($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }
        $agentAccountId = (int)$agentAccountId;

        $visaApplicationId = $input['visaApplicationId'] ?? $input['id'] ?? null;
        $visaSend = $input['visaSend'] ?? null;

        if (empty($visaApplicationId) || !is_numeric($visaApplicationId)) {
            send_error_response('Visa Application ID is required');
        }
        $visaApplicationId = (int)$visaApplicationId;

        if ($visaSend === null || ($visaSend !== 0 && $visaSend !== 1 && $visaSend !== '0' && $visaSend !== '1')) {
            send_error_response('visaSend must be 0 or 1');
        }
        $visaSend = (int)$visaSend;

        // 권한 확인
        if (!__agent_verify_visa_ownership($conn, $agentAccountId, $visaApplicationId)) {
            send_error_response('Unauthorized access to this visa application', 403);
        }

        // visaSend 컬럼 존재 여부 확인
        $hasVisaSend = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM visa_applications LIKE 'visaSend'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasVisaSend = true;
        }

        if (!$hasVisaSend) {
            // 컬럼이 없으면 추가
            $conn->query("ALTER TABLE visa_applications ADD COLUMN visaSend TINYINT(1) DEFAULT 0");
        }

        // visaSend 업데이트 및 상태 변경
        // visaSend가 1(Yes)이면 status를 under_review로, 0(No)이면 pending으로
        $newStatus = $visaSend === 1 ? 'under_review' : 'pending';

        $hasUpdatedAt = __agent_visa_applications_has_column($conn, 'updatedAt');
        $sql = $hasUpdatedAt
            ? "UPDATE visa_applications SET visaSend = ?, status = ?, updatedAt = CURRENT_TIMESTAMP WHERE applicationId = ?"
            : "UPDATE visa_applications SET visaSend = ?, status = ? WHERE applicationId = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare update');
        }
        $stmt->bind_param('isi', $visaSend, $newStatus, $visaApplicationId);
        $stmt->execute();
        $stmt->close();

        send_success_response([
            'visaSend' => $visaSend,
            'status' => $newStatus
        ], 'Documents Send status updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update Documents Send status: ' . $e->getMessage());
    }
}

/**
 * 현재 진행 중인 세일 상품 조회 (Agent Overview용)
 */
function getSaleProducts($conn) {
    try {
        // 세션 확인 (agent 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        // sales, sale_items 테이블 존재 확인
        $tableCheck = $conn->query("SHOW TABLES LIKE 'sales'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            send_success_response([]);
            return;
        }

        $tableCheck2 = $conn->query("SHOW TABLES LIKE 'sale_items'");
        if (!$tableCheck2 || $tableCheck2->num_rows === 0) {
            send_success_response([]);
            return;
        }

        // 현재 진행 중인 세일 상품 조회 (B2B 가격 기준)
        // bookings 테이블에서 실제 예약 수를 계산하여 정확한 재고 파악
        $sql = "
            SELECT
                p.packageId,
                p.packageName,
                p.thumbnail_image,
                pad.available_date,
                pad.capacity,
                COALESCE(pad.b2b_price, pad.price) AS original_price,
                (COALESCE(pad.b2b_price, pad.price) - s.discount_amount) AS sale_price,
                s.discount_amount,
                COALESCE(booked.total_booked, 0) AS booked_seats,
                (pad.capacity - COALESCE(booked.total_booked, 0)) AS remaining_seats,
                s.sale_name,
                s.sale_end_date
            FROM sales s
            INNER JOIN sale_items si ON si.sale_id = s.id
            INNER JOIN package_available_dates pad ON pad.id = si.package_available_date_id
            INNER JOIN packages p ON p.packageId = pad.package_id
            LEFT JOIN (
                SELECT packageId, departureDate,
                       SUM(COALESCE(adults,0) + COALESCE(children,0) + COALESCE(infantsWithSeat,0)) AS total_booked
                FROM bookings
                WHERE (bookingStatus IS NULL OR bookingStatus NOT IN ('cancelled','draft'))
                  AND (paymentStatus IS NULL OR paymentStatus <> 'refunded')
                GROUP BY packageId, departureDate
            ) booked ON booked.packageId = p.packageId AND booked.departureDate = pad.available_date
            WHERE s.is_active = 1
              AND CURDATE() BETWEEN s.sale_start_date AND s.sale_end_date
              AND pad.available_date >= CURDATE()
              AND pad.status IN ('available', 'confirmed', 'open')
              AND (pad.capacity - COALESCE(booked.total_booked, 0)) > 0
              AND p.isActive = 1
            ORDER BY pad.available_date ASC, COALESCE(pad.b2b_price, pad.price) ASC
            LIMIT 100
        ";

        $result = $conn->query($sql);
        if (!$result) {
            send_success_response([]);
            return;
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'packageId' => $row['packageId'],
                'packageName' => $row['packageName'],
                'thumbnailImage' => $row['thumbnail_image'],
                'availableDate' => $row['available_date'],
                'originalPrice' => floatval($row['original_price']),
                'salePrice' => floatval($row['sale_price']),
                'discountAmount' => floatval($row['discount_amount']),
                'formattedOriginalPrice' => '₱' . number_format($row['original_price'], 0),
                'formattedSalePrice' => '₱' . number_format($row['sale_price'], 0),
                'formattedDiscount' => '-₱' . number_format($row['discount_amount'], 0),
                'remainingSeats' => intval($row['remaining_seats']),
                'saleName' => $row['sale_name'],
                'saleEndDate' => $row['sale_end_date']
            ];
        }

        send_success_response($products);
    } catch (Exception $e) {
        send_error_response('Failed to get sale products: ' . $e->getMessage());
    }
}

// ========== 메시지 관리 함수 ==========

function generateAgentThreadNo($conn) {
    $prefix = 'MSG' . date('Ymd');
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(threadNo, 12) AS UNSIGNED)) AS maxSeq FROM message_threads WHERE threadNo LIKE '{$prefix}%'");
    $row = $result ? $result->fetch_assoc() : null;
    $seq = ($row && $row['maxSeq']) ? intval($row['maxSeq']) + 1 : 1;
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function getAgentMessageThreads($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $page = max(1, intval($input['page'] ?? 1));
        $limit = max(1, min(100, intval($input['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $status = $input['status'] ?? '';
        $search = trim($input['search'] ?? '');
        $sortOrder = ($input['sortOrder'] ?? 'latest') === 'oldest' ? 'ASC' : 'DESC';

        $where = ['t.agentAccountId = ?'];
        $params = [$agentAccountId];
        $types = 'i';

        if ($status && in_array($status, ['open','in_progress','resolved','closed'])) {
            $where[] = 't.status = ?';
            $params[] = $status;
            $types .= 's';
        }
        if ($search !== '') {
            $where[] = 't.subject LIKE ?';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $types .= 's';
        }
        $dateFrom = trim($input['dateFrom'] ?? '');
        $dateTo = trim($input['dateTo'] ?? '');
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 't.createdAt >= ?';
            $params[] = $dateFrom . ' 00:00:00';
            $types .= 's';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 't.createdAt <= ?';
            $params[] = $dateTo . ' 23:59:59';
            $types .= 's';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count
        $countSql = "SELECT COUNT(*) AS cnt FROM message_threads t {$whereClause}";
        $stmt = $conn->prepare($countSql);
        $bindParams = [];
        foreach ($params as $k => &$v) $bindParams[] = &$params[$k];
        array_unshift($bindParams, $types);
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();
        $totalCount = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        // List
        $sql = "SELECT t.threadId, t.threadNo, t.subject, t.status, t.category, t.lastMessageAt, t.createdAt,
                    (SELECT COUNT(*) FROM message_items mi WHERE mi.threadId = t.threadId AND mi.isRead = 0 AND mi.senderType = 'admin') AS unreadCount,
                    (SELECT mi.content FROM message_items mi WHERE mi.threadId = t.threadId ORDER BY mi.createdAt DESC LIMIT 1) AS lastMessage
                FROM message_threads t
                {$whereClause}
                ORDER BY t.lastMessageAt {$sortOrder}, t.createdAt {$sortOrder}
                LIMIT ? OFFSET ?";

        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$limit, $offset]);

        $stmt = $conn->prepare($sql);
        $bindParams = [];
        foreach ($listParams as $k => &$v) $bindParams[] = &$listParams[$k];
        array_unshift($bindParams, $listTypes);
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $threads = [];
        $rowNum = $totalCount - $offset;
        while ($row = $result->fetch_assoc()) {
            $lastMsg = strip_tags($row['lastMessage'] ?? '');
            if (mb_strlen($lastMsg) > 50) $lastMsg = mb_substr($lastMsg, 0, 50) . '...';
            $threads[] = [
                'threadId' => intval($row['threadId']),
                'threadNo' => $row['threadNo'],
                'subject' => $row['subject'],
                'status' => $row['status'],
                'category' => $row['category'],
                'unreadCount' => intval($row['unreadCount']),
                'lastMessage' => $lastMsg,
                'lastMessageAt' => $row['lastMessageAt'],
                'createdAt' => $row['createdAt'],
                'rowNum' => $rowNum--
            ];
        }
        $stmt->close();

        send_success_response([
            'threads' => $threads,
            'pagination' => [
                'totalCount' => intval($totalCount),
                'currentPage' => $page,
                'totalPages' => max(1, ceil($totalCount / $limit)),
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get message threads: ' . $e->getMessage());
    }
}

function getAgentMessageThreadDetail($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $threadId = intval($input['threadId'] ?? $input['id'] ?? 0);
        if (!$threadId) send_error_response('Thread ID is required');

        // Thread info (only if agent owns this thread)
        $stmt = $conn->prepare("SELECT t.* FROM message_threads t WHERE t.threadId = ? AND t.agentAccountId = ?");
        $stmt->bind_param('ii', $threadId, $agentAccountId);
        $stmt->execute();
        $thread = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$thread) send_error_response('Thread not found', 404);

        // Mark admin messages as read
        $stmtRead = $conn->prepare("UPDATE message_items SET isRead = 1, readAt = NOW() WHERE threadId = ? AND senderType = 'admin' AND isRead = 0");
        $stmtRead->bind_param('i', $threadId);
        $stmtRead->execute();
        $stmtRead->close();

        // All messages
        $stmtMsg = $conn->prepare("SELECT mi.messageId, mi.senderId, mi.senderType, mi.content, mi.isRead, mi.readAt, mi.createdAt,
                a.firstName AS senderFirstName, a.lastName AS senderLastName
            FROM message_items mi
            LEFT JOIN accounts a ON mi.senderId = a.accountId
            WHERE mi.threadId = ?
            ORDER BY mi.createdAt ASC");
        $stmtMsg->bind_param('i', $threadId);
        $stmtMsg->execute();
        $msgResult = $stmtMsg->get_result();

        $messages = [];
        $messageIds = [];
        while ($row = $msgResult->fetch_assoc()) {
            $messageIds[] = intval($row['messageId']);
            $messages[] = [
                'messageId' => intval($row['messageId']),
                'senderId' => intval($row['senderId']),
                'senderType' => $row['senderType'],
                'senderName' => trim(($row['senderFirstName'] ?? '') . ' ' . ($row['senderLastName'] ?? '')) ?: ($row['senderType'] === 'admin' ? 'Admin' : 'Agent'),
                'content' => $row['content'],
                'isRead' => intval($row['isRead']),
                'readAt' => $row['readAt'],
                'createdAt' => $row['createdAt'],
                'attachments' => []
            ];
        }
        $stmtMsg->close();

        // Attachments
        if ($messageIds) {
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $stmtAtt = $conn->prepare("SELECT attachmentId, messageId, fileName, filePath, fileSize, fileType FROM message_attachments WHERE messageId IN ({$placeholders})");
            $attTypes = str_repeat('i', count($messageIds));
            $bindParams = [];
            foreach ($messageIds as $k => &$v) $bindParams[] = &$messageIds[$k];
            array_unshift($bindParams, $attTypes);
            call_user_func_array([$stmtAtt, 'bind_param'], $bindParams);
            $stmtAtt->execute();
            $attResult = $stmtAtt->get_result();
            $attMap = [];
            while ($att = $attResult->fetch_assoc()) {
                $mid = intval($att['messageId']);
                if (!isset($attMap[$mid])) $attMap[$mid] = [];
                $ext = strtolower(pathinfo($att['fileName'], PATHINFO_EXTENSION));
                $attMap[$mid][] = [
                    'attachmentId' => intval($att['attachmentId']),
                    'fileName' => $att['fileName'],
                    'filePath' => $att['filePath'],
                    'fileSize' => intval($att['fileSize']),
                    'fileType' => $att['fileType'],
                    'isImage' => in_array($ext, ['jpg','jpeg','png','gif','webp'])
                ];
            }
            $stmtAtt->close();
            foreach ($messages as &$msg) {
                $msg['attachments'] = $attMap[$msg['messageId']] ?? [];
            }
            unset($msg);
        }

        send_success_response([
            'thread' => [
                'threadId' => intval($thread['threadId']),
                'threadNo' => $thread['threadNo'],
                'subject' => $thread['subject'],
                'status' => $thread['status'],
                'category' => $thread['category'],
                'bookingId' => $thread['bookingId'] ?? null,
                'createdAt' => $thread['createdAt']
            ],
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get thread detail: ' . $e->getMessage());
    }
}

function getAgentMessageThreadByBookingId($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $bookingId = trim($input['bookingId'] ?? '');
        if ($bookingId === '') send_error_response('Booking ID is required');

        $stmt = $conn->prepare("SELECT threadId FROM message_threads WHERE bookingId = ? AND agentAccountId = ? LIMIT 1");
        $stmt->bind_param('si', $bookingId, $agentAccountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        send_success_response([
            'exists' => !!$row,
            'threadId' => $row ? intval($row['threadId']) : null
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to check thread: ' . $e->getMessage());
    }
}

function createAgentMessageThread($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $subject = trim($input['subject'] ?? '');
        $content = trim($input['content'] ?? '');
        $category = $input['category'] ?? 'general';
        $bookingId = trim($input['bookingId'] ?? '') ?: null;

        if ($subject === '') send_error_response('Subject is required');
        if ($content === '' || $content === '<p><br></p>') send_error_response('Message content is required');

        $validCategories = [
            'general','product','booking','payment','cancellation','other',
            'booking_new','booking_change','booking_cancel',
            'product_detail','product_price','product_promo',
            'payment_method','payment_confirm','payment_receipt',
            'travel_visa','travel_insurance',
            'local_hotel','local_transport','local_tour'
        ];
        if (!in_array($category, $validCategories)) {
            $category = 'general';
        }

        $conn->begin_transaction();

        $threadNo = generateAgentThreadNo($conn);

        $stmtThread = $conn->prepare("INSERT INTO message_threads (threadNo, subject, createdBy, agentAccountId, bookingId, category, status, lastMessageAt, createdAt) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())");
        $stmtThread->bind_param('ssiiss', $threadNo, $subject, $agentAccountId, $agentAccountId, $bookingId, $category);
        if (!$stmtThread->execute()) {
            $conn->rollback();
            send_error_response('Failed to create thread: ' . $stmtThread->error);
        }
        $threadId = $conn->insert_id;
        $stmtThread->close();

        $stmtMsg = $conn->prepare("INSERT INTO message_items (threadId, senderId, senderType, content, createdAt) VALUES (?, ?, 'agent', ?, NOW())");
        $stmtMsg->bind_param('iis', $threadId, $agentAccountId, $content);
        if (!$stmtMsg->execute()) {
            $conn->rollback();
            send_error_response('Failed to create message: ' . $stmtMsg->error);
        }
        $messageId = $conn->insert_id;
        $stmtMsg->close();

        // Handle file attachments
        $attachments = handleAgentMessageAttachments($conn, $threadId, $messageId, $agentAccountId);

        $conn->commit();

        send_success_response([
            'threadId' => $threadId,
            'threadNo' => $threadNo,
            'messageId' => $messageId,
            'attachments' => $attachments
        ], 'Message thread created successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to create thread: ' . $e->getMessage());
    }
}

function handleAgentSendMessage($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $threadId = intval($input['threadId'] ?? 0);
        $content = trim($input['content'] ?? '');

        if (!$threadId) send_error_response('Thread ID is required');

        $hasFiles = !empty($_FILES['files']) && is_array($_FILES['files']['name']) && $_FILES['files']['error'][0] === UPLOAD_ERR_OK;
        $hasContent = $content !== '' && $content !== '<p><br></p>';
        if (!$hasContent && !$hasFiles) send_error_response('Please enter a message or attach a file');

        // Verify thread belongs to this agent
        $stmtCheck = $conn->prepare("SELECT threadId, status FROM message_threads WHERE threadId = ? AND agentAccountId = ?");
        $stmtCheck->bind_param('ii', $threadId, $agentAccountId);
        $stmtCheck->execute();
        $thread = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if (!$thread) send_error_response('Thread not found', 404);
        if ($thread['status'] === 'closed') send_error_response('Cannot send message to a closed thread');

        $conn->begin_transaction();

        $stmtMsg = $conn->prepare("INSERT INTO message_items (threadId, senderId, senderType, content, createdAt) VALUES (?, ?, 'agent', ?, NOW())");
        $stmtMsg->bind_param('iis', $threadId, $agentAccountId, $content);
        if (!$stmtMsg->execute()) {
            $conn->rollback();
            send_error_response('Failed to send message: ' . $stmtMsg->error);
        }
        $messageId = $conn->insert_id;
        $stmtMsg->close();

        $conn->query("UPDATE message_threads SET lastMessageAt = NOW() WHERE threadId = {$threadId}");

        $attachments = handleAgentMessageAttachments($conn, $threadId, $messageId, $agentAccountId);

        $conn->commit();

        send_success_response([
            'messageId' => $messageId,
            'attachments' => $attachments
        ], 'Message sent successfully');
    } catch (Exception $e) {
        $conn->rollback();
        send_error_response('Failed to send message: ' . $e->getMessage());
    }
}

function handleAgentMessageAttachments($conn, $threadId, $messageId, $uploadedBy) {
    $attachments = [];
    if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $uploadDir = realpath(__DIR__ . '/../../../uploads/messages');
        if (!$uploadDir) {
            $uploadDir = __DIR__ . '/../../../uploads/messages';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        }

        $allowedExts = ['jpg','jpeg','png','gif','pdf'];
        $maxSize = 10 * 1024 * 1024;
        $maxFiles = 5;

        $fileCount = min(count($_FILES['files']['name']), $maxFiles);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $originalName = $_FILES['files']['name'][$i];
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileSize = $_FILES['files']['size'][$i];
            $fileType = $_FILES['files']['type'][$i];

            if ($fileSize > $maxSize) continue;

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $storedName = 'msg_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '_' . $safeName;
            $destPath = $uploadDir . '/' . $storedName;

            if (move_uploaded_file($tmpPath, $destPath)) {
                $relPath = 'uploads/messages/' . $storedName;
                $stmtAtt = $conn->prepare("INSERT INTO message_attachments (messageId, threadId, fileName, filePath, fileSize, fileType, uploadedBy, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmtAtt->bind_param('iissisi', $messageId, $threadId, $originalName, $relPath, $fileSize, $fileType, $uploadedBy);
                $stmtAtt->execute();
                $attachments[] = [
                    'attachmentId' => $conn->insert_id,
                    'fileName' => $originalName,
                    'filePath' => $relPath,
                    'fileSize' => $fileSize,
                    'fileType' => $fileType
                ];
                $stmtAtt->close();
            }
        }
    }
    return $attachments;
}

function getAgentMessageUnreadCount($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $agentAccountId = intval($_SESSION['agent_accountId'] ?? ($_SESSION['accountId'] ?? 0));
        if (!$agentAccountId) send_error_response('Agent login required', 401);

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM message_items mi INNER JOIN message_threads t ON mi.threadId = t.threadId WHERE t.agentAccountId = ? AND mi.isRead = 0 AND mi.senderType = 'admin'");
        $stmt->bind_param('i', $agentAccountId);
        $stmt->execute();
        $count = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        send_success_response(['unreadCount' => $count]);
    } catch (Exception $e) {
        send_error_response('Failed to get unread count: ' . $e->getMessage());
    }
}

// ========== Visa Applications by Booking ==========
function getVisaApplicationsByBookingId($conn, $input) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $agentAccountId = $_SESSION['agent_accountId'] ?? null;
    if (empty($agentAccountId)) {
        send_error_response('Agent login required', 401);
    }
    $agentAccountId = (int)$agentAccountId;

    $bookingId = $input['bookingId'] ?? null;
    if (empty($bookingId)) {
        send_error_response('Booking ID is required');
    }

    // 에이전트 소유 booking 검증
    $chk = $conn->prepare("SELECT 1 FROM bookings WHERE bookingId = ? AND agentId IN (SELECT id FROM agent WHERE accountId = ?) LIMIT 1");
    $chk->bind_param('si', $bookingId, $agentAccountId);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $chk->close();
        send_error_response('Unauthorized access to this booking', 403);
    }
    $chk->close();

    // visa_applications 조회 + booking_travelers JOIN
    $sql = "SELECT
                v.applicationId,
                v.transactNo,
                v.bookingTravelerId,
                v.applicantName,
                v.visaType,
                v.status,
                v.notes,
                v.visaSend,
                v.createdAt,
                v.updatedAt,
                bt.travelerType,
                bt.firstName AS btFirstName,
                bt.lastName AS btLastName
            FROM visa_applications v
            LEFT JOIN booking_travelers bt ON v.bookingTravelerId = bt.bookingTravelerId
            WHERE v.transactNo = ?
            ORDER BY v.applicationId ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $uiStatus = __agent_mapVisaDbToUiStatus($row['status'] ?? 'pending');
        $documents = __agent_extractVisaDocumentsFromNotes($row['notes']);
        $visaFile = __agent_extractVisaFileFromNotes($row['notes']);

        $applications[] = [
            'applicationId' => (int)$row['applicationId'],
            'transactNo'    => $row['transactNo'],
            'bookingTravelerId' => $row['bookingTravelerId'] ? (int)$row['bookingTravelerId'] : null,
            'applicantName' => $row['applicantName'] ?? '',
            'visaType'      => $row['visaType'] ?? '',
            'status'        => $uiStatus,
            'dbStatus'      => $row['status'] ?? 'pending',
            'visaSend'      => (int)($row['visaSend'] ?? 0),
            'documents'     => $documents,
            'visaFile'      => $visaFile,
            'travelerType'  => $row['travelerType'] ?? 'adult',
            'btFirstName'   => $row['btFirstName'] ?? '',
            'btLastName'    => $row['btLastName'] ?? '',
            'createdAt'     => $row['createdAt'] ?? '',
            'updatedAt'     => $row['updatedAt'] ?? ''
        ];
    }
    $stmt->close();

    send_success_response([
        'applications' => $applications,
        'totalCount'   => count($applications)
    ]);
}

// ============================================
// Rooming Assignments (Room Options Tab)
// ============================================

function getRoomingAssignments($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? '';
        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }

        // 예약 소유권 확인 + departureDate, packageId 조회
        $checkSql = "SELECT bookingId, departureDate, packageId FROM bookings WHERE bookingId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found or access denied', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        $departureDate = $booking['departureDate'] ?? '';
        if (empty($departureDate)) {
            send_success_response(['assignments' => []]);
            return;
        }

        // booking_travelers에서 해당 예약의 traveler ID 목록 조회
        $travelerIds = [];
        $tSql = "SELECT bookingTravelerId FROM booking_travelers WHERE transactNo = ?";
        $tStmt = $conn->prepare($tSql);
        $tStmt->bind_param('s', $bookingId);
        $tStmt->execute();
        $tResult = $tStmt->get_result();
        while ($row = $tResult->fetch_assoc()) {
            $travelerIds[] = (int)$row['bookingTravelerId'];
        }
        $tStmt->close();

        if (empty($travelerIds)) {
            send_success_response(['assignments' => []]);
            return;
        }

        // rooming_assignments에서 해당 traveler들의 배정 데이터 조회
        $placeholders = implode(',', array_fill(0, count($travelerIds), '?'));
        $types = str_repeat('i', count($travelerIds));
        $sql = "SELECT booking_traveler_id AS bookingTravelerId, room_type AS roomType, room_number AS roomNumber, remarks
                FROM rooming_assignments
                WHERE departure_date = ? AND booking_traveler_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $bindTypes = 's' . $types;
        $bindParams = array_merge([$departureDate], $travelerIds);
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $res = $stmt->get_result();

        $assignments = [];
        while ($row = $res->fetch_assoc()) {
            $assignments[] = [
                'bookingTravelerId' => (int)$row['bookingTravelerId'],
                'roomType' => $row['roomType'],
                'roomNumber' => $row['roomNumber'] !== null ? (int)$row['roomNumber'] : null,
                'remarks' => $row['remarks']
            ];
        }
        $stmt->close();

        send_success_response(['assignments' => $assignments]);
    } catch (Exception $e) {
        send_error_response('Failed to get rooming assignments: ' . $e->getMessage(), 500);
    }
}

function saveAgentRoomingAssignments($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $agentAccountId = $_SESSION['agent_accountId'] ?? null;
        if (empty($agentAccountId)) {
            send_error_response('Agent login required', 401);
        }

        $bookingId = $input['bookingId'] ?? '';
        $assignments = $input['assignments'] ?? [];

        if (empty($bookingId)) {
            send_error_response('Booking ID is required', 400);
        }
        if (!is_array($assignments)) {
            send_error_response('assignments array is required', 400);
        }

        // 예약 소유권 확인 + departureDate, packageId 자동 조회
        $checkSql = "SELECT bookingId, departureDate, packageId FROM bookings WHERE bookingId = ? AND accountId = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('si', $bookingId, $agentAccountId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            send_error_response('Reservation not found or access denied', 404);
        }

        $booking = $result->fetch_assoc();
        $checkStmt->close();

        $departureDate = $booking['departureDate'] ?? '';
        $packageId = (int)($booking['packageId'] ?? 0);

        if (empty($departureDate)) {
            send_error_response('Departure date not found for this booking', 400);
        }

        // 해당 예약의 기존 배정 삭제 (traveler ID 기반)
        $travelerIds = [];
        $tSql = "SELECT bookingTravelerId FROM booking_travelers WHERE transactNo = ?";
        $tStmt = $conn->prepare($tSql);
        $tStmt->bind_param('s', $bookingId);
        $tStmt->execute();
        $tResult = $tStmt->get_result();
        while ($row = $tResult->fetch_assoc()) {
            $travelerIds[] = (int)$row['bookingTravelerId'];
        }
        $tStmt->close();

        if (!empty($travelerIds)) {
            $placeholders = implode(',', array_fill(0, count($travelerIds), '?'));
            $types = str_repeat('i', count($travelerIds));
            $delSql = "DELETE FROM rooming_assignments WHERE departure_date = ? AND booking_traveler_id IN ($placeholders)";
            $delStmt = $conn->prepare($delSql);
            $bindTypes = 's' . $types;
            $bindParams = array_merge([$departureDate], $travelerIds);
            $delStmt->bind_param($bindTypes, ...$bindParams);
            $delStmt->execute();
            $delStmt->close();
        }

        // 새 배정 삽입
        if (!empty($assignments)) {
            $sql = "INSERT INTO rooming_assignments (departure_date, package_id, booking_traveler_id, room_number, room_type, luggage, tipping, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        room_number = VALUES(room_number),
                        room_type = VALUES(room_type),
                        luggage = VALUES(luggage),
                        tipping = VALUES(tipping),
                        remarks = VALUES(remarks),
                        package_id = VALUES(package_id)";

            $stmt = $conn->prepare($sql);
            $savedCount = 0;

            foreach ($assignments as $a) {
                $travelerId = (int)($a['bookingTravelerId'] ?? 0);
                if ($travelerId <= 0) continue;

                // 소유권 확인: 해당 traveler가 이 예약에 속하는지
                if (!in_array($travelerId, $travelerIds)) continue;

                $roomNumber = isset($a['roomNumber']) && $a['roomNumber'] !== '' && $a['roomNumber'] !== null ? (int)$a['roomNumber'] : null;
                $roomType = $a['roomType'] ?? null;
                if ($roomType !== null && $roomType !== '') {
                    $roomType = substr($roomType, 0, 50);
                } else {
                    $roomType = null;
                }
                $luggage = $a['luggage'] ?? null;
                $tipping = $a['tipping'] ?? null;
                $remarks = $a['remarks'] ?? null;

                $stmt->bind_param('siisssss', $departureDate, $packageId, $travelerId, $roomNumber, $roomType, $luggage, $tipping, $remarks);
                $stmt->execute();
                $savedCount++;
            }
            $stmt->close();
        } else {
            $savedCount = 0;
        }

        send_success_response(['savedCount' => $savedCount], "Saved $savedCount assignments successfully");
    } catch (Exception $e) {
        send_error_response('Failed to save rooming assignments: ' . $e->getMessage(), 500);
    }
}

?>
