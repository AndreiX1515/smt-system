<?php
/**
 * CS (Customer Service) Admin API
 * CS 관련 API 엔드포인트를 처리합니다.
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
        'message' => 'Database connection file not found'
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

// JSON 응답 헬퍼 함수
if (!function_exists('send_json_response')) {
    function send_json_response($data, $status_code = 200) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (ob_get_level() > 0) {
            ob_end_flush();
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

// 요청 메서드 확인
$method = $_SERVER['REQUEST_METHOD'];

// mysqli::bind_param 은 참조 전달이 필요합니다.
// PHP 8+ 에서 bind_param($types, ...$params) 형태는 "참조가 아닌 값"이 전달되어 Fatal Error 로 이어질 수 있습니다.
if (!function_exists('mysqli_bind_params_by_ref')) {
    function mysqli_bind_params_by_ref($stmt, string $types, array &$params): bool {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

// JSON 입력 받기
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET 파라미터 병합
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

// POST 데이터와 병합
if ($method === 'POST' && !empty($_POST)) {
    $input = array_merge($input, $_POST);
}

// action 파라미터 확인
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
    switch ($action) {
        // ========== 문의 목록 ==========
        case 'getInquiries':
            getInquiries($conn, $input);
            break;
            
        case 'getInquiryDetail':
            getInquiryDetail($conn, $input);
            break;
            
        case 'createReply':
            createReply($conn, $input);
            break;
            
        case 'updateInquiryStatus':
            updateInquiryStatus($conn, $input);
            break;
            
        case 'downloadInquiries':
            downloadInquiries($conn, $input);
            break;
            
        case 'downloadAttachment':
            downloadAttachment($conn, $input);
            break;
            
        default:
            send_error_response('Invalid action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log("CS API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_error_response('An error occurred: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("CS API fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_error_response('A fatal error occurred: ' . $e->getMessage(), 500);
}

// ========== 문의 목록 함수 ==========

/**
 * CS API 접근 권한 체크
 * - CS 페이지(`admin/cs/inquiry-list.html`)는 check-session.php에서 cs_accountId를 authenticated로 인정합니다.
 * - 그런데 본 API가 admin_accountId만 체크하면 UI는 로그인인데 데이터 호출은 401로 실패합니다.
 * - 따라서 cs/admin 둘 다 허용합니다.
 */
function require_cs_or_admin_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $csAccountId = $_SESSION['cs_accountId'] ?? null;
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;

    if (!empty($csAccountId) || !empty($adminAccountId)) {
        return;
    }

    send_error_response('CS/Admin login required', 401);
}

function is_cs_only_session(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $csAccountId = $_SESSION['cs_accountId'] ?? null;
    $adminAccountId = $_SESSION['admin_accountId'] ?? null;
    return (!empty($csAccountId) && empty($adminAccountId));
}

/**
 * CS UI 상태(pending/processing/completed) → DB enum(open/in_progress/resolved/closed) 매핑
 */
function normalize_inquiry_status(string $status): string {
    $st = strtolower(trim($status));
    return match ($st) {
        'pending' => 'open',
        'processing' => 'in_progress',
        'completed' => 'resolved',
        default => $st,
    };
}

/**
 * CS UI Processing Status(pending/processing/completed) → DB status 목록으로 변환 (필터용)
 * - completed는 resolved/closed 모두 포함
 */
function processing_status_to_db_values(?string $ui): array {
    $st = strtolower(trim((string)($ui ?? '')));
    return match ($st) {
        'pending' => ['open'],
        'processing' => ['in_progress'],
        'completed' => ['resolved', 'closed'],
        default => ($st !== '' ? [$st] : []),
    };
}

/**
 * DB category(enum) / (optional) inquiries.inquiryType 값을 UI 5종(product/reservation/payment/cancel/other)으로 정규화
 */
function normalize_ui_inquiry_type(?string $raw): string {
    $v = strtolower(trim((string)($raw ?? '')));
    if ($v === '') return 'other';

    // already UI values
    if (in_array($v, ['product','reservation','payment','cancel','cancellation','other'], true)) {
        return ($v === 'cancellation') ? 'cancel' : $v;
    }

    // DB category values
    return match ($v) {
        'booking' => 'reservation',
        'payment' => 'payment',
        'complaint' => 'cancel',
        'suggestion' => 'other',
        // general 은 Product Inquiry
        'general' => 'product',
        // visa/technical 은 요구 UI(5종) 안에 포함되지 않으므로 Other 로 표시
        'visa', 'technical' => 'other',
        default => 'other',
    };
}

function inquiry_type_label(string $uiType): string {
    $t = normalize_ui_inquiry_type($uiType);
    return match ($t) {
        'product' => 'Product Inquiry',
        'reservation' => 'Reservation Inquiry',
        'payment' => 'Payment Inquiry',
        'cancel' => 'Cancellation Inquiry',
        default => 'Other',
    };
}

/**
 * UI 5종 → DB category(enum)로 매핑
 * @return string[] DB category 후보들
 */
function ui_inquiry_type_to_db_categories(?string $uiType): array {
    $t = strtolower(trim((string)($uiType ?? '')));
    if ($t === 'cancellation') $t = 'cancel';
    return match ($t) {
        'product' => ['general'],
        'reservation' => ['booking'],
        'payment' => ['payment'],
        'cancel' => ['complaint'],
        'other' => ['suggestion'],
        default => [],
    };
}

// ---- DB schema helpers (운영/로컬 스키마 차이 흡수) ----
function table_exists($conn, $tableName) {
    static $cache = [];
    $k = strtolower((string)$tableName);
    if (array_key_exists($k, $cache)) return $cache[$k];
    $safe = str_replace(['\\', "'"], ['\\\\', "\\'"], (string)$tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $cache[$k] = ($res && $res->num_rows > 0);
    return $cache[$k];
}

/**
 * @return array<string,string> lowercase_field => actual_field
 */
function table_columns_map($conn, $tableName) {
    static $cache = [];
    $k = strtolower((string)$tableName);
    if (array_key_exists($k, $cache)) return $cache[$k];
    $map = [];
    $res = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $field = (string)($col['Field'] ?? '');
            if ($field !== '') $map[strtolower($field)] = $field;
        }
    }
    $cache[$k] = $map;
    return $map;
}

/**
 * 첨부파일 경로 정규화
 * - DB에 절대경로(/var/www/html/...)나 전체 URL(https://.../uploads/...)이 섞여있어도
 *   "웹 루트 기준 상대경로(uploads/...)" 형태로 통일합니다.
 * - downloadAttachment 에서도 같은 규칙으로 실제 파일 시스템 경로를 계산합니다.
 */
function normalize_attachment_rel_path(?string $filePath): string {
    $p = trim((string)($filePath ?? ''));
    if ($p === '') return '';

    // full URL이면 path 부분만 사용
    if (preg_match('/^https?:\/\//i', $p)) {
        $u = parse_url($p);
        $p = (string)($u['path'] ?? '');
    }

    // 쿼리스트링/프래그먼트 제거
    $p = preg_replace('/[?#].*$/', '', $p);

    // 윈도우 경로 구분자 방지
    $p = str_replace('\\', '/', $p);

    // document root가 섞여 들어온 경우 제거
    // 예: /var/www/html/uploads/...  또는  var/www/html/uploads/...
    $p = preg_replace('#^/var/www/html/#', '', $p);
    $p = preg_replace('#^var/www/html/#', '', $p);

    // uploads/ 이하만 남기기(그 외는 일단 루트 상대경로로 처리)
    $pos = stripos($p, 'uploads/');
    if ($pos !== false) {
        $p = substr($p, $pos);
    }

    // 선행 슬래시 제거 (상대경로화)
    $p = ltrim($p, '/');

    return $p;
}

function getInquiries($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        // 페이지네이션 파라미터
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        // inquiries 테이블 컬럼 확인 (운영/로컬 스키마 차이 흡수)
        $inquiryColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        $hasInquiryTypeCol = in_array('inquirytype', $inquiryColumns, true);
        
        // 필터 파라미터
        $search = $input['search'] ?? ''; // 검색어
        $inquiryType = $input['inquiryType'] ?? ''; // 문의 유형
        $replyStatus = $input['replyStatus'] ?? ''; // 'answered' or 'unanswered'
        $processingStatus = $input['processingStatus'] ?? ''; // 'pending', 'processing', 'completed'
        $sortOrder = $input['sortOrder'] ?? 'latest'; // 'latest' or 'oldest'
        
        // WHERE 조건 구성
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // 검색 필터 (스키마 차이 흡수: 존재하는 컬럼만 대상으로 LIKE)
        if (!empty($search)) {
            $searchCols = [];
            if (in_array('subject', $inquiryColumns, true)) $searchCols[] = "i.subject LIKE ?";
            if (in_array('inquirytitle', $inquiryColumns, true)) $searchCols[] = "i.inquiryTitle LIKE ?";
            if (in_array('content', $inquiryColumns, true)) $searchCols[] = "i.content LIKE ?";
            if (in_array('inquirycontent', $inquiryColumns, true)) $searchCols[] = "i.inquiryContent LIKE ?";

            if (!empty($searchCols)) {
                $whereConditions[] = '(' . implode(' OR ', $searchCols) . ')';
                $searchParam = '%' . $search . '%';
                foreach ($searchCols as $_) { $params[] = $searchParam; $types .= 's'; }
            }
        }
        
        // 문의 유형 필터
        if (!empty($inquiryType)) {
            // UI(5종) 값이면 DB category로 변환해 필터링
            $dbCats = ui_inquiry_type_to_db_categories($inquiryType);
            if (!empty($dbCats)) {
                $placeholders = implode(',', array_fill(0, count($dbCats), '?'));
                $whereParts = [];
                $whereParts[] = "i.category IN ($placeholders)";
                foreach ($dbCats as $c) { $params[] = $c; $types .= 's'; }
                if ($hasInquiryTypeCol) {
                    $whereParts[] = "i.inquiryType = ?";
                    $params[] = normalize_ui_inquiry_type($inquiryType);
                    $types .= 's';
                }
                $whereConditions[] = '(' . implode(' OR ', $whereParts) . ')';
            } else {
                // legacy/직접 DB 값(category) 필터
                $whereParts = ["i.category = ?"];
                $params[] = $inquiryType; $types .= 's';
                if ($hasInquiryTypeCol) {
                    $whereParts[] = "i.inquiryType = ?";
                    $params[] = $inquiryType; $types .= 's';
                }
                $whereConditions[] = '(' . implode(' OR ', $whereParts) . ')';
            }
        }
        
        // 답변 여부 필터
        // - 일부 환경에서 inquiry_replies에 시스템/임시 레코드가 들어가 replyStatus가 오판될 수 있어
        //   "관리자/CS가 작성한 답변"만 답변으로 인정합니다.
        //   (accounts 테이블이 없으면 기존 로직으로 fallback)
        $replyExistsSql = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
        $replyNotExistsSql = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
        if (table_exists($conn, 'accounts') && table_exists($conn, 'inquiry_replies')) {
            $replyExistsSql = "EXISTS (
                SELECT 1
                FROM inquiry_replies ir
                JOIN accounts ar ON ir.authorId = ar.accountId
                WHERE ir.inquiryId = i.inquiryId
                  AND ar.accountType IN ('admin_ph','admin_kr','super','employee','cs')
            )";
            $replyNotExistsSql = "NOT EXISTS (
                SELECT 1
                FROM inquiry_replies ir
                JOIN accounts ar ON ir.authorId = ar.accountId
                WHERE ir.inquiryId = i.inquiryId
                  AND ar.accountType IN ('admin_ph','admin_kr','super','employee','cs')
            )";
        }
        if ($replyStatus === 'answered') {
            $whereConditions[] = $replyExistsSql;
        } elseif ($replyStatus === 'unanswered') {
            $whereConditions[] = $replyNotExistsSql;
        }
        
        // 처리 상태 필터
        if (!empty($processingStatus)) {
            $vals = processing_status_to_db_values((string)$processingStatus);
            if (count($vals) === 1) {
                $whereConditions[] = "i.status = ?";
                $params[] = $vals[0];
                $types .= 's';
            } elseif (count($vals) > 1) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            }
        }

        // CS 담당자 전용: 회원(guest) 문의만 노출
        // (admin 화면은 전체 조회 가능)
        $joins = '';
        if (is_cs_only_session()) {
            $joins .= " INNER JOIN accounts acc ON i.accountId = acc.accountId";
            $whereConditions[] = "acc.accountType = 'guest'";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // 총 개수 조회
        $countSql = "SELECT COUNT(*) as total FROM inquiries i $joins $whereClause";
        
        $countStmt = null;
        if (!empty($params)) {
            $countStmt = $conn->prepare($countSql);
            if ($countStmt) {
                mysqli_bind_params_by_ref($countStmt, $types, $params);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
            } else {
                $countResult = $conn->query($countSql);
            }
        } else {
            $countResult = $conn->query($countSql);
        }
        
        $totalCount = 0;
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $totalCount = intval($countRow['total'] ?? 0);
        }
        if ($countStmt) {
            $countStmt->close();
        }
        
        // 컬럼명 결정
        $categoryColumn = in_array('category', $inquiryColumns) ? 'i.category' : 
                         (in_array('inquirytype', $inquiryColumns) ? 'i.inquiryType' : 'NULL');
        $titleColumn = in_array('subject', $inquiryColumns) ? 'i.subject' : 
                      (in_array('inquirytitle', $inquiryColumns) ? 'i.inquiryTitle' : 'NULL');
        
        // 정렬 순서
        // - latest/oldest: createdAt 기준
        // - unanswered_first/answered_first: Response Status 기준(요구사항 #155)
        //   replyExistsSql는 "관리자/CS 답변 존재" 기준을 그대로 사용한다.
        $replySortExpr = "CASE WHEN {$replyExistsSql} THEN 1 ELSE 0 END";
        if ($sortOrder === 'oldest') {
            $orderBy = 'i.createdAt ASC';
        } elseif ($sortOrder === 'unanswered_first') {
            $orderBy = "{$replySortExpr} ASC, i.createdAt DESC";
        } elseif ($sortOrder === 'answered_first') {
            $orderBy = "{$replySortExpr} DESC, i.createdAt DESC";
        } else {
            $orderBy = 'i.createdAt DESC';
        }
        
        // 데이터 조회
        $dataSql = "
            SELECT 
                i.inquiryId,
                $categoryColumn as inquiryType,
                $titleColumn as inquiryTitle,
                i.status,
                i.createdAt,
                CASE 
                    WHEN $replyExistsSql THEN 'Response Complete'
                    ELSE 'Not Responded'
                END as replyStatus,
                CASE 
                    WHEN i.status = 'pending' OR i.status = 'open' THEN 'Received'
                    WHEN i.status = 'processing' OR i.status = 'in_progress' THEN 'In Progress'
                    WHEN i.status = 'completed' OR i.status = 'resolved' OR i.status = 'closed' THEN 'Processing Complete'
                    ELSE i.status
                END as processingStatus
            FROM inquiries i
            $joins
            $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) {
            throw new Exception('Failed to prepare data query: ' . $conn->error);
        }
        
        if (!empty($dataParams)) {
            mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        }
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $inquiries = [];
        $rowNum = $totalCount - $offset;
        while ($row = $dataResult->fetch_assoc()) {
            $uiType = normalize_ui_inquiry_type($row['inquiryType'] ?? '');
            $inquiries[] = [
                'inquiryId' => $row['inquiryId'],
                'inquiryType' => $uiType,
                'inquiryTypeLabel' => inquiry_type_label($uiType),
                'inquiryTitle' => $row['inquiryTitle'] ?? '',
                'createdAt' => $row['createdAt'] ?? '',
                'replyStatus' => $row['replyStatus'] ?? 'Not Responded',
                'processingStatus' => $row['processingStatus'] ?? 'Received',
                'rowNum' => $rowNum--
            ];
        }
        $dataStmt->close();
        
        $totalPages = ceil($totalCount / $limit);
        
        send_success_response([
            'inquiries' => $inquiries,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        send_error_response('Failed to get inquiries: ' . $e->getMessage());
    }
}

function getInquiryDetail($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        $inquiryId = $input['inquiryId'] ?? $input['id'] ?? null;
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
        
        $categoryColumn = in_array('category', $inquiryColumns) ? 'i.category' : 
                         (in_array('inquirytype', $inquiryColumns) ? 'i.inquiryType' : 'NULL');
        $titleColumn = in_array('subject', $inquiryColumns) ? 'i.subject' : 
                      (in_array('inquirytitle', $inquiryColumns) ? 'i.inquiryTitle' : 'NULL');
        $contentColumn = in_array('content', $inquiryColumns) ? 'i.content' : 
                        (in_array('inquirycontent', $inquiryColumns) ? 'i.inquiryContent' : 'NULL');
        
        // 운영/로컬 DB 스키마 차이 때문에 JOIN 대상 테이블이 없을 수 있어 동적으로 구성
        $clientExists = table_exists($conn, 'client');
        $companyExists = table_exists($conn, 'company');
        $branchTable = table_exists($conn, 'branch') ? 'branch' : null;

        $joins = '';
        $selectClient = "NULL as fName, NULL as lName, NULL as emailAddress, NULL as contactNo";
        $selectClientType = "NULL as clientType, NULL as companyId";
        if ($clientExists) {
            $cCols = table_columns_map($conn, 'client');
            $cF = $cCols['fname'] ?? $cCols['firstname'] ?? $cCols['first_name'] ?? null;
            $cL = $cCols['lname'] ?? $cCols['lastname'] ?? $cCols['last_name'] ?? null;
            $cE = $cCols['emailaddress'] ?? $cCols['email'] ?? null;
            $cP = $cCols['contactno'] ?? $cCols['phonenumber'] ?? $cCols['phone'] ?? $cCols['contact_phone'] ?? null;
            $cType = $cCols['clienttype'] ?? null;
            $cCompanyId = $cCols['companyid'] ?? null;

            $joins .= " LEFT JOIN client c ON i.accountId = c.accountId";
            $selectClient = sprintf(
                "%s as fName, %s as lName, %s as emailAddress, %s as contactNo",
                $cF ? "c.`{$cF}`" : "NULL",
                $cL ? "c.`{$cL}`" : "NULL",
                $cE ? "c.`{$cE}`" : "NULL",
                $cP ? "c.`{$cP}`" : "NULL"
            );
            $selectClientType = sprintf(
                "%s as clientType, %s as companyId",
                $cType ? "c.`{$cType}`" : "NULL",
                $cCompanyId ? "c.`{$cCompanyId}`" : "NULL"
            );
        }

        $selectCompany = "NULL as companyName";
        $selectBranch = "NULL as branchName";
        if ($companyExists && $clientExists) {
            $coCols = table_columns_map($conn, 'company');
            $coId = $coCols['companyid'] ?? null;
            $coName = $coCols['companyname'] ?? null;
            $coBranchId = $coCols['branchid'] ?? null;
            if ($coId && $coName) {
                // client.companyId -> company.companyId
                $cCols = table_columns_map($conn, 'client');
                $cCompanyId = $cCols['companyid'] ?? 'companyId';
                $selectCompany = "'' as companyName";
                if ($branchTable && $coBranchId) {
                    $bCols = table_columns_map($conn, $branchTable);
                    $bId = $bCols['branchid'] ?? null;
                    $bName = $bCols['branchname'] ?? null;
                    if ($bId && $bName) {
                        $joins .= " LEFT JOIN `{$branchTable}` b ON ''";
                        $selectBranch = "b.`{$bName}` as branchName";
                    }
                }
            }
        }

        // CS 담당자 전용: 회원(guest) 문의만 접근 허용
        $accJoin = '';
        $accWhere = '';
        if (is_cs_only_session()) {
            $accJoin = " INNER JOIN accounts acc ON i.accountId = acc.accountId";
            $accWhere = " AND acc.accountType = 'guest'";
        }

        $sql = "SELECT 
            i.*,
            $categoryColumn as category,
            $titleColumn as inquiryTitle,
            $contentColumn as inquiryContent,
            $selectClient,
            $selectClientType,
            $selectCompany,
            $selectBranch
        FROM inquiries i
        $accJoin
        $joins
        WHERE i.inquiryId = ? $accWhere";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Inquiry not found', 404);
        }
        
        $inquiry = $result->fetch_assoc();
        
        // 원본 DB 값 확인 (i.*로 가져온 값 중에서)
        // category 컬럼이 있으면 그것을 사용, 없으면 inquiryType 컬럼 사용
        $rawCategoryValue = '';
        if (isset($inquiry['category']) && $inquiry['category'] !== null && $inquiry['category'] !== '') {
            $rawCategoryValue = $inquiry['category'];
        } elseif (isset($inquiry['inquiryType']) && $inquiry['inquiryType'] !== null && $inquiry['inquiryType'] !== '') {
            $rawCategoryValue = $inquiry['inquiryType'];
        }
        
        // 원본 값 저장 (디버깅용)
        $inquiry['rawCategory'] = $rawCategoryValue;
        
        // UI용 문의유형 정규화 (원본 값 사용)
        $inquiry['inquiryType'] = normalize_ui_inquiry_type($rawCategoryValue);
        
        // 디버깅 로그 (개발 환경에서만)
        if (isset($_GET['debug']) || (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false))) {
            error_log("CS API getInquiryDetail - inquiryId: {$inquiryId}, rawCategoryValue: " . var_export($rawCategoryValue, true) . ", normalizedType: " . var_export($inquiry['inquiryType'], true) . ", allFields: " . implode(', ', array_keys($inquiry)));
        }

        // Agent Name(지점명) 계산:
        // - client.clientType이 wholeseller/wholesaler/wholesale 인 경우 company/branch join 값 사용
        $clientType = strtolower(trim((string)($inquiry['clientType'] ?? '')));
        $isB2B = in_array($clientType, ['wholeseller', 'wholesaler', 'wholesale'], true);

        $resolvedBranchName = trim((string)($inquiry['branchName'] ?? ''));
        $resolvedCompanyName = trim((string)($inquiry['companyName'] ?? ''));

        if (!$isB2B) {
            // B2C(소속 없음)는 Agent Name을 비워야 함
            $inquiry['agentName'] = '';
            $inquiry['companyName'] = '';
            $inquiry['branchName'] = '';
        } else {
            // B2B: 지점명 우선, 없으면 회사명
            $inquiry['companyName'] = $resolvedCompanyName;
            $inquiry['branchName'] = $resolvedBranchName;
            $inquiry['agentName'] = $resolvedBranchName !== '' ? $resolvedBranchName : $resolvedCompanyName;
        }
        
        // 첨부파일 조회
        $attachments = [];
        if (table_exists($conn, 'inquiry_attachments')) {
            $attachmentsSql = "SELECT * FROM inquiry_attachments WHERE inquiryId = ?";
            $attachmentsStmt = $conn->prepare($attachmentsSql);
            if ($attachmentsStmt) {
                $attachmentsStmt->bind_param('i', $inquiryId);
                $attachmentsStmt->execute();
                $attachmentsResult = $attachmentsStmt->get_result();
                while ($attachment = $attachmentsResult->fetch_assoc()) {
                    // 필드명 차이 흡수: filePath vs path, fileName vs name, originalName 등
                    $rawPath = $attachment['filePath'] ?? ($attachment['path'] ?? '');
                    $filePath = normalize_attachment_rel_path($rawPath);
                    $fileName = $attachment['fileName'] ?? ($attachment['name'] ?? ($attachment['originalName'] ?? ''));
                    $origName = $attachment['originalName'] ?? ($attachment['fileName'] ?? ($attachment['name'] ?? ''));
                    $attachments[] = array_merge($attachment, [
                        'filePath' => $filePath,
                        'fileName' => $fileName,
                        'originalName' => $origName,
                    ]);
                }
                $attachmentsStmt->close();
            }
        }
        $inquiry['attachments'] = $attachments;
        
        // 답변 목록 조회
        $repliesSql = "SELECT * FROM inquiry_replies WHERE inquiryId = ? ORDER BY createdAt ASC";
        $repliesStmt = $conn->prepare($repliesSql);
        $repliesStmt->bind_param('i', $inquiryId);
        $repliesStmt->execute();
        $repliesResult = $repliesStmt->get_result();
        
        $replies = [];
        // 운영/로컬 환경에서 inquiry_reply_attachments 테이블이 없을 수 있음 → 없으면 조회 시도 자체를 하지 않음
        $hasReplyAttachmentsTable = table_exists($conn, 'inquiry_reply_attachments');
        while ($reply = $repliesResult->fetch_assoc()) {
            // 답변 첨부파일 조회
            $replyAttachments = [];
            if ($hasReplyAttachmentsTable) {
                $replyAttachmentsSql = "SELECT * FROM inquiry_reply_attachments WHERE replyId = ?";
                $replyAttachmentsStmt = $conn->prepare($replyAttachmentsSql);
                if ($replyAttachmentsStmt) {
                    $replyId = intval($reply['replyId'] ?? 0);
                    $replyAttachmentsStmt->bind_param('i', $replyId);
                    $replyAttachmentsStmt->execute();
                    $replyAttachmentsResult = $replyAttachmentsStmt->get_result();
                    while ($replyAttachment = $replyAttachmentsResult->fetch_assoc()) {
                        $replyAttachments[] = $replyAttachment;
                    }
                    $replyAttachmentsStmt->close();
                }
            }
            $reply['attachments'] = $replyAttachments;
            $replies[] = $reply;
        }
        $repliesStmt->close();
        
        $stmt->close();
        
        send_success_response([
            'inquiry' => $inquiry,
            'replies' => $replies
        ]);
    } catch (Exception $e) {
        send_error_response('Failed to get inquiry detail: ' . $e->getMessage());
    }
}

function createReply($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        $inquiryId = $input['inquiryId'] ?? null;
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        
        $content = $input['content'] ?? '';
        if (empty($content)) {
            send_error_response('Reply content is required');
        }

        // 작성자(accountId) 결정: CS 계정이면 cs_accountId, 아니면 admin_accountId
        $authorId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['cs_accountId'])) {
            $authorId = intval($_SESSION['cs_accountId']);
        } elseif (!empty($_SESSION['admin_accountId'])) {
            $authorId = intval($_SESSION['admin_accountId']);
        } elseif (!empty($_SESSION['user_id'])) {
            // 일부 레거시 세션 호환
            $authorId = intval($_SESSION['user_id']);
        }
        if (empty($authorId)) {
            send_error_response('Author session is missing', 401);
        }
        
        // inquiry_replies 테이블 컬럼 확인
        $replyColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiry_replies");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $replyColumns[] = strtolower($col['Field']);
            }
        }
        
        $contentColumn = in_array('content', $replyColumns) ? 'content' : 
                        (in_array('replycontent', $replyColumns) ? 'replyContent' : 'content');

        $authorColumn = in_array('authorid', $replyColumns) ? 'authorId' :
            (in_array('author_id', $replyColumns) ? 'author_id' : 'authorId');
        
        // 답변 등록
        $sql = "INSERT INTO inquiry_replies (inquiryId, $authorColumn, $contentColumn, createdAt) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare reply insert: ' . ($conn->error ?: 'unknown'), 500);
        }
        $stmt->bind_param('iis', $inquiryId, $authorId, $content);
        if (!$stmt->execute()) {
            send_error_response('Failed to create reply: ' . ($stmt->error ?: 'unknown'), 500);
        }
        $replyId = $conn->insert_id;
        $stmt->close();
        
        // 첨부파일 처리
        if (!empty($_FILES)) {
            $uploadDir = __DIR__ . '/../../../uploads/inquiries/replies/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // inquiry_reply_attachments 테이블 확인 및 생성
            $tableCheck = $conn->query("SHOW TABLES LIKE 'inquiry_reply_attachments'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                $createTableSql = "CREATE TABLE IF NOT EXISTS inquiry_reply_attachments (
                    attachmentId INT AUTO_INCREMENT PRIMARY KEY,
                    replyId INT NOT NULL,
                    fileName VARCHAR(255),
                    filePath VARCHAR(500),
                    fileSize INT,
                    fileType VARCHAR(100),
                    originalName VARCHAR(255),
                    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (replyId) REFERENCES inquiry_replies(replyId) ON DELETE CASCADE
                )";
                $conn->query($createTableSql);
            }
            
            foreach ($_FILES as $key => $file) {
                if (strpos($key, 'attachments[') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = 'reply_' . $replyId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $filePath = 'uploads/inquiries/replies/' . $fileName;
                        $fileSize = $file['size'];
                        $fileType = $file['type'];
                        $originalFileName = $file['name'];
                        
                        $attachmentSql = "INSERT INTO inquiry_reply_attachments (replyId, fileName, filePath, fileSize, fileType, originalName) VALUES (?, ?, ?, ?, ?, ?)";
                        $attachmentStmt = $conn->prepare($attachmentSql);
                        $attachmentStmt->bind_param('ississ', $replyId, $fileName, $filePath, $fileSize, $fileType, $originalFileName);
                        $attachmentStmt->execute();
                        $attachmentStmt->close();
                    }
                }
            }
        }
        
        // 답변 여부(Reply Status)와 처리상태(Processing Status)는 분리되어야 하므로
        // 답변 등록만 하고 inquiries.status 는 자동 변경하지 않습니다.
        // (처리상태 변경은 updateInquiryStatus 액션을 통해서만 수행)
        
        send_success_response(['replyId' => $replyId], 'Reply created successfully');
    } catch (Exception $e) {
        send_error_response('Failed to create reply: ' . $e->getMessage());
    }
}

function updateInquiryStatus($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        $inquiryId = $input['inquiryId'] ?? null;
        if (empty($inquiryId)) {
            send_error_response('Inquiry ID is required');
        }
        $inquiryId = intval($inquiryId);
        if ($inquiryId <= 0) {
            send_error_response('Invalid inquiry ID');
        }
        
        $status = $input['status'] ?? null;
        if (empty($status)) {
            send_error_response('Status is required');
        }

        // UI 값(pending/processing/completed) → DB 값(open/in_progress/resolved/closed)
        $normalized = normalize_inquiry_status((string)$status);
        $allowed = ['open', 'in_progress', 'resolved', 'closed', 'pending', 'processing', 'completed'];
        if (!in_array(strtolower((string)$status), $allowed, true) && !in_array($normalized, ['open','in_progress','resolved','closed'], true)) {
            send_error_response('Invalid status value', 400);
        }
        
        $sql = "UPDATE inquiries SET status = ? WHERE inquiryId = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_error_response('Failed to prepare status update: ' . ($conn->error ?: 'unknown'), 500);
        }
        $stmt->bind_param('si', $normalized, $inquiryId);
        if (!$stmt->execute()) {
            send_error_response('Failed to update status: ' . ($stmt->error ?: 'unknown'), 500);
        }
        
        // 실제로 업데이트가 되었는지 확인
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affectedRows === 0) {
            // 업데이트된 행이 없음 - inquiryId가 존재하지 않거나 이미 같은 상태일 수 있음
            // 존재 여부 확인
            $checkStmt = $conn->prepare("SELECT inquiryId FROM inquiries WHERE inquiryId = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('i', $inquiryId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                if ($checkResult->num_rows === 0) {
                    $checkStmt->close();
                    send_error_response('Inquiry not found', 404);
                }
                $checkStmt->close();
            }
            // inquiryId가 존재하지만 상태가 이미 같은 경우도 성공으로 처리
        }
        
        send_success_response([], 'Status updated successfully');
    } catch (Exception $e) {
        send_error_response('Failed to update status: ' . $e->getMessage());
    }
}

function downloadInquiries($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        // 필터 파라미터 (getInquiries와 동일)
        $search = $input['search'] ?? '';
        $inquiryType = $input['inquiryType'] ?? '';
        $replyStatus = $input['replyStatus'] ?? '';
        $processingStatus = $input['processingStatus'] ?? '';
        $sortOrder = $input['sortOrder'] ?? 'latest';
        
        // WHERE 조건 구성 (getInquiries와 동일)
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $searchCols = [];
            if (in_array('subject', $inquiryColumns, true)) $searchCols[] = "i.subject LIKE ?";
            if (in_array('inquirytitle', $inquiryColumns, true)) $searchCols[] = "i.inquiryTitle LIKE ?";
            if (in_array('content', $inquiryColumns, true)) $searchCols[] = "i.content LIKE ?";
            if (in_array('inquirycontent', $inquiryColumns, true)) $searchCols[] = "i.inquiryContent LIKE ?";

            if (!empty($searchCols)) {
                $whereConditions[] = '(' . implode(' OR ', $searchCols) . ')';
                $searchParam = '%' . $search . '%';
                foreach ($searchCols as $_) { $params[] = $searchParam; $types .= 's'; }
            }
        }
        
        // inquiries 컬럼 확인 (inquiryType 컬럼 존재 여부)
        $inquiryColumns = [];
        $columnResult = $conn->query("SHOW COLUMNS FROM inquiries");
        if ($columnResult) {
            while ($col = $columnResult->fetch_assoc()) {
                $inquiryColumns[] = strtolower($col['Field']);
            }
        }
        $hasInquiryTypeCol = in_array('inquirytype', $inquiryColumns, true);
        $categoryColumn = in_array('category', $inquiryColumns, true) ? 'i.category' :
            ($hasInquiryTypeCol ? 'i.inquiryType' : 'NULL');
        $titleColumn = in_array('subject', $inquiryColumns, true) ? 'i.subject' :
            (in_array('inquirytitle', $inquiryColumns, true) ? 'i.inquiryTitle' : 'NULL');

        if (!empty($inquiryType)) {
            $dbCats = ui_inquiry_type_to_db_categories($inquiryType);
            if (!empty($dbCats)) {
                $placeholders = implode(',', array_fill(0, count($dbCats), '?'));
                $whereParts = [];
                $whereParts[] = "i.category IN ($placeholders)";
                foreach ($dbCats as $c) { $params[] = $c; $types .= 's'; }
                if ($hasInquiryTypeCol) {
                    $whereParts[] = "i.inquiryType = ?";
                    $params[] = normalize_ui_inquiry_type($inquiryType);
                    $types .= 's';
                }
                $whereConditions[] = '(' . implode(' OR ', $whereParts) . ')';
            } else {
                $whereParts = ["i.category = ?"];
                $params[] = $inquiryType; $types .= 's';
                if ($hasInquiryTypeCol) {
                    $whereParts[] = "i.inquiryType = ?";
                    $params[] = $inquiryType; $types .= 's';
                }
                $whereConditions[] = '(' . implode(' OR ', $whereParts) . ')';
            }
        }
        
        // 답변 여부 필터(관리자/CS 답변만 카운트) - getInquiries와 동일 규칙
        $replyExistsSql = "EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
        $replyNotExistsSql = "NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
        if (table_exists($conn, 'accounts') && table_exists($conn, 'inquiry_replies')) {
            $replyExistsSql = "EXISTS (
                SELECT 1
                FROM inquiry_replies ir
                JOIN accounts ar ON ir.authorId = ar.accountId
                WHERE ir.inquiryId = i.inquiryId
                  AND ar.accountType IN ('admin_ph','admin_kr','super','employee','cs')
            )";
            $replyNotExistsSql = "NOT EXISTS (
                SELECT 1
                FROM inquiry_replies ir
                JOIN accounts ar ON ir.authorId = ar.accountId
                WHERE ir.inquiryId = i.inquiryId
                  AND ar.accountType IN ('admin_ph','admin_kr','super','employee','cs')
            )";
        }
        if ($replyStatus === 'answered') {
            $whereConditions[] = $replyExistsSql;
        } elseif ($replyStatus === 'unanswered') {
            $whereConditions[] = $replyNotExistsSql;
        }
        
        if (!empty($processingStatus)) {
            $vals = processing_status_to_db_values((string)$processingStatus);
            if (count($vals) === 1) {
                $whereConditions[] = "i.status = ?";
                $params[] = $vals[0];
                $types .= 's';
            } elseif (count($vals) > 1) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $whereConditions[] = "i.status IN ($placeholders)";
                foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // CSV도 리스트와 동일 정렬 규칙 적용
        $replySortExpr = "CASE WHEN {$replyExistsSql} THEN 1 ELSE 0 END";
        if ($sortOrder === 'oldest') {
            $orderBy = 'i.createdAt ASC';
        } elseif ($sortOrder === 'unanswered_first') {
            $orderBy = "{$replySortExpr} ASC, i.createdAt DESC";
        } elseif ($sortOrder === 'answered_first') {
            $orderBy = "{$replySortExpr} DESC, i.createdAt DESC";
        } else {
            $orderBy = 'i.createdAt DESC';
        }
        
        $sql = "
            SELECT 
                i.inquiryId,
                $categoryColumn as inquiryType,
                $titleColumn as inquiryTitle,
                i.status,
                i.createdAt,
                CASE 
                    WHEN $replyExistsSql THEN 'Response Complete'
                    ELSE 'Not Responded'
                END as replyStatus,
                CASE 
                    WHEN i.status = 'pending' OR i.status = 'open' THEN 'Received'
                    WHEN i.status = 'processing' OR i.status = 'in_progress' THEN 'In Progress'
                    WHEN i.status = 'completed' OR i.status = 'resolved' OR i.status = 'closed' THEN 'Processing Complete'
                    ELSE i.status
                END as processingStatus
            FROM inquiries i
            $whereClause
            ORDER BY $orderBy
        ";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare CSV query: ' . ($conn->error ?: 'unknown'));
        }
        if (!empty($params)) {
            mysqli_bind_params_by_ref($stmt, $types, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        // CSV headers (English)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inquiries_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM 추가 (Excel에서 한글 깨짐 방지)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV header row (요구사항 문구)
        fputcsv($output, ['No', 'Inquiry type', 'Inquiry title', 'Date created', 'Response status', 'Processing status'], ',', '"', '\\');
        
        $rowNum = 1;
        while ($row = $result->fetch_assoc()) {
            $uiType = normalize_ui_inquiry_type($row['inquiryType'] ?? '');
            fputcsv($output, [
                $rowNum++,
                inquiry_type_label($uiType),
                $row['inquiryTitle'] ?? '',
                $row['createdAt'] ?? '',
                $row['replyStatus'] ?? 'Not Responded',
                $row['processingStatus'] ?? 'Received'
            ], ',', '"', '\\');
        }
        
        $stmt->close();
        fclose($output);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download inquiries: ' . $e->getMessage());
    }
}

function downloadAttachment($conn, $input) {
    try {
        require_cs_or_admin_session();
        
        $filePath = $input['filePath'] ?? '';
        if (empty($filePath)) {
            send_error_response('File path is required');
        }
        
        // 보안: 경로 traversal 차단
        if (strpos($filePath, '..') !== false) {
            send_error_response('Invalid file path');
        }

        // 절대경로/URL 섞임을 상대경로로 정규화
        $safeRel = normalize_attachment_rel_path((string)$filePath);
        if ($safeRel === '') {
            send_error_response('Invalid file path');
        }

        // 프로젝트 웹 루트 기준으로 파일 위치 계산
        $webRoot = realpath(__DIR__ . '/../../../');
        if ($webRoot === false) {
            send_error_response('Server path error', 500);
        }
        $fullPath = $webRoot . '/' . $safeRel;

        // realpath로 최종 경로를 고정하고, 웹 루트 밖으로 나가지 못하게 제한
        $resolved = realpath($fullPath);
        if ($resolved === false || strpos($resolved, $webRoot . DIRECTORY_SEPARATOR) !== 0) {
            send_error_response('File not found', 404);
        }
        
        if (!file_exists($resolved)) {
            send_error_response('File not found', 404);
        }
        
        // fileName은 query로 넘기는 경우가 많아 원본명이 별도로 없으면 basename 사용
        $fileName = basename($resolved);
        $fileSize = filesize($resolved);
        $fileType = @mime_content_type($resolved) ?: 'application/octet-stream';

        // JSON/에러 출력 버퍼가 파일 앞에 붙으면 다운로드가 깨지므로 버퍼를 모두 제거
        while (ob_get_level() > 0) { @ob_end_clean(); }

        // RFC 5987 filename* 지원(한글/공백/괄호 등 안전)
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName);
        if ($fallback === '' || $fallback === null) $fallback = 'attachment';
        $utf8Name = rawurlencode($fileName);

        header('Content-Type: ' . $fileType);
        header('X-Content-Type-Options: nosniff');
        header('Content-Transfer-Encoding: binary');
        header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''{$utf8Name}");
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($resolved);
        exit;
    } catch (Exception $e) {
        send_error_response('Failed to download attachment: ' . $e->getMessage());
    }
}

