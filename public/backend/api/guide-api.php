<?php
/**
 * Guide Admin API
 * 모든 Guide 관련 API 엔드포인트를 처리합니다.
 */

// 출력 버퍼링 시작 (에러 캡처를 위해)
ob_start();

// 개발 환경에서 에러 표시 (디버깅용)
// warning/notice가 JSON 응답에 섞이지 않게 화면 출력은 끄고 로그로만 남깁니다.
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

// PHP 8+에서 bind_param(...$params) 사용 시 "by reference" 오류가 나므로 helper로 처리
function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array $params): void {
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = $params[$k];
    }
    $bindParams = array_merge([$types], $refs);
    $tmp = [];
    foreach ($bindParams as $k => $v) {
        $tmp[$k] = &$bindParams[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

function safe_table_exists(mysqli $conn, string $table): bool {
    $t = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return ($t && $t->num_rows > 0);
}

/**
 * packages 테이블의 "상품명" 컬럼 표현식(p.`...`)을 반환
 * - 운영/로컬 스키마 차이(packageName vs package_name 등) 흡수
 */
function packages_package_name_expr(mysqli $conn): string {
    if (!safe_table_exists($conn, 'packages')) return "''";
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM packages");
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            $field = (string)($c['Field'] ?? '');
            if ($field !== '') $cols[strtolower($field)] = $field;
        }
    }
    $field =
        $cols['packagename']
        ?? $cols['package_name']
        ?? $cols['package_title']
        ?? $cols['name']
        ?? null;
    if (!$field) return "''";
    return "p.`{$field}`";
}

/**
 * bookings 테이블의 "상품명" 컬럼 표현식(b.`...`)을 반환(없으면 빈 문자열)
 */
function bookings_package_name_expr(array $bookingsColumns): string {
    // $bookingsColumns 는 lowercase 목록
    if (in_array('packagename', $bookingsColumns, true)) return "b.packageName";
    if (in_array('package_name', $bookingsColumns, true)) return "b.package_name";
    if (in_array('package', $bookingsColumns, true)) return "b.package";
    return '';
}

/**
 * booking_travelers 컬럼명 편차 흡수
 */
function booking_travelers_column_map(mysqli $conn): array {
    $map = [
        'bookingId' => 'transactNo',
        'firstName' => 'firstName',
        'lastName' => 'lastName',
        'passportNumber' => 'passportNumber',
        'travelerType' => 'travelerType',
    ];
    $t = $conn->query("SHOW TABLES LIKE 'booking_travelers'");
    if (!$t || $t->num_rows === 0) return $map;

    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM booking_travelers");
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            $cols[strtolower((string)$c['Field'])] = (string)$c['Field'];
        }
    }

    // booking id
    if (isset($cols['bookingid'])) $map['bookingId'] = $cols['bookingid'];
    else if (isset($cols['transactno'])) $map['bookingId'] = $cols['transactno'];
    else if (isset($cols['booking_id'])) $map['bookingId'] = $cols['booking_id'];

    // first name
    $map['firstName'] =
        $cols['firstname'] ?? $cols['first_name'] ?? $cols['fname'] ?? $cols['f_name'] ?? ($cols['givenname'] ?? ($cols['given_name'] ?? ($map['firstName'])));
    // last name
    $map['lastName'] =
        $cols['lastname'] ?? $cols['last_name'] ?? $cols['lname'] ?? $cols['l_name'] ?? ($cols['surname'] ?? ($cols['familyname'] ?? ($cols['family_name'] ?? ($map['lastName']))));
    // passport no
    $map['passportNumber'] =
        $cols['passportnumber'] ?? $cols['passport_no'] ?? $cols['passportno'] ?? $cols['passport_number'] ?? ($cols['passport'] ?? ($map['passportNumber']));
    // traveler type
    $map['travelerType'] =
        $cols['travelertype'] ?? $cols['traveler_type'] ?? $cols['type'] ?? ($cols['ageoption'] ?? ($cols['age_option'] ?? ($map['travelerType'])));
    return $map;
}

/**
 * packages.product_pricing(JSON)에서 optionName 후보를 최대한 추출
 */
function extract_people_options_from_pricing(?string $pricingRaw): array {
    $peopleOptions = [];
    if (!is_string($pricingRaw) || trim($pricingRaw) === '') return $peopleOptions;
    $decoded = json_decode($pricingRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return $peopleOptions;

    $stack = [$decoded];
    while (!empty($stack)) {
        $cur = array_pop($stack);
        if (!is_array($cur)) continue;
        foreach ($cur as $k => $v) {
            if (is_array($v)) {
                $stack[] = $v;
                continue;
            }
            $lk = strtolower((string)$k);
            if (in_array($lk, ['optionname', 'option_name', 'name'], true) && is_string($v) && trim($v) !== '') {
                $peopleOptions[] = trim($v);
            }
        }
    }
    $peopleOptions = array_values(array_unique(array_filter($peopleOptions)));
    return $peopleOptions;
}

function map_traveler_type_to_people_option(string $travelerType, array $options): string {
    $t = strtolower(trim($travelerType));
    $opts = array_values(array_filter(array_map('strval', $options)));
    if (empty($opts)) {
        return match ($t) {
            'child' => 'Child',
            'infant' => 'Infant',
            default => 'Adult',
        };
    }

    $needle = match ($t) {
        'child' => 'child',
        'infant' => 'infant',
        default => 'adult',
    };
    foreach ($opts as $o) {
        $lo = strtolower($o);
        if (str_contains($lo, $needle)) return $o;
    }
    // 휴리스틱 실패 시: 순서 기반 fallback(Adult/Child/Infant로 간주)
    if ($t === 'adult') return $opts[0] ?? 'Adult';
    if ($t === 'child') return $opts[1] ?? ($opts[0] ?? 'Child');
    if ($t === 'infant') return $opts[2] ?? ($opts[0] ?? 'Infant');
    return $opts[0];
}

function combine_date_time_if_time_only(?string $date, ?string $timeOrDateTime): string {
    $d = trim((string)($date ?? ''));
    $t = trim((string)($timeOrDateTime ?? ''));
    if ($t === '') return '';
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $t)) return $t;
    if ($d === '') return $t;
    return $d . ' ' . $t;
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

// 테스트용: ?test_guide=1 이면 임시 guide 세션 구성(개발/테스트 전용)
try {
    $testGuide = $_GET['test_guide'] ?? ($input['test_guide'] ?? null);
    if ((string)$testGuide === '1') {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['guide_accountId'])) {
            $r = $conn->query("SELECT a.accountId, a.emailAddress
                               FROM accounts a
                               INNER JOIN guides g ON a.accountId = g.accountId
                               WHERE a.accountType = 'guide'
                               LIMIT 1");
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $gid = intval($row['accountId'] ?? 0);
                $email = (string)($row['emailAddress'] ?? '');
                if ($gid > 0) {
                    $_SESSION['guide_accountId'] = $gid;
                    $_SESSION['guide_userType'] = 'guide';
                    $_SESSION['guide_emailAddress'] = $email;
                }
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

try {
    switch ($action) {
        // ========== 가이드 배정 예약 목록 ==========
        case 'getAssignedBookings':
            getAssignedBookings($conn, $input);
            break;
        case 'getAssignedBookingDetail':
            getAssignedBookingDetail($conn, $input);
            break;
        case 'getTodayBookings':
            getTodayBookings($conn, $input);
            break;
        case 'getTodayScheduleDetail':
            getTodayScheduleDetail($conn, $input);
            break;
            
        default:
            send_error_response('Invalid action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log("Guide API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_error_response('An error occurred: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Guide API fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_error_response('A fatal error occurred: ' . $e->getMessage(), 500);
}

// ========== 가이드 배정 예약 목록 함수 ==========

function getAssignedBookings($conn, $input) {
    try {
        // 세션 확인 (guide 로그인 확인)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // guide 세션 확인
        // 여러 세션 변수명 시도 (기존 시스템과 호환)
        $guideAccountId = $_SESSION['guide_accountId'] ?? ($_SESSION['accountId'] ?? null);
        
        // accountId가 없으면 accounts 테이블에서 guide 타입 계정 찾기 (개발용)
        if (empty($guideAccountId)) {
            // 세션에 accountId가 없지만 userType이 guide인 경우
            if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'guide') {
                // guideId로 accountId 찾기
                if (isset($_SESSION['guideId'])) {
                    $guideIdCheckSql = "SELECT accountId FROM guides WHERE guideId = ?";
                    $guideIdCheckStmt = $conn->prepare($guideIdCheckSql);
                    if ($guideIdCheckStmt) {
                        $guideIdCheckStmt->bind_param('i', $_SESSION['guideId']);
                        $guideIdCheckStmt->execute();
                        $guideIdResult = $guideIdCheckStmt->get_result();
                        if ($guideIdResult && $guideIdResult->num_rows > 0) {
                            $guideIdRow = $guideIdResult->fetch_assoc();
                            $guideAccountId = $guideIdRow['accountId'];
                        }
                        $guideIdCheckStmt->close();
                    }
                }
            }
        }
        
        // 여전히 없으면 accounts 테이블에서 guide 타입 계정 찾기 (개발/테스트용)
        if (empty($guideAccountId)) {
            // 임시로 첫 번째 guide 계정 사용 (개발용)
            $tempGuideSql = "SELECT a.accountId FROM accounts a 
                            INNER JOIN guides g ON a.accountId = g.accountId 
                            WHERE a.accountType = 'guide' 
                            LIMIT 1";
            $tempGuideResult = $conn->query($tempGuideSql);
            if ($tempGuideResult && $tempGuideResult->num_rows > 0) {
                $tempGuideRow = $tempGuideResult->fetch_assoc();
                $guideAccountId = $tempGuideRow['accountId'];
                // 세션에 저장 (다음 요청을 위해)
                $_SESSION['guide_accountId'] = $guideAccountId;
            }
        }
        
        if (empty($guideAccountId)) {
            send_error_response('Guide login required. Please log in as a guide.', 401);
        }
        
        // guide의 guideId 가져오기
        $guideId = null;
        $guideSql = "SELECT guideId FROM guides WHERE accountId = ?";
        $guideStmt = $conn->prepare($guideSql);
        if ($guideStmt) {
            $guideAccountIdInt = intval($guideAccountId);
            $guideStmt->bind_param('i', $guideAccountIdInt);
            $guideStmt->execute();
            $guideResult = $guideStmt->get_result();
            if ($guideResult && $guideResult->num_rows > 0) {
                $guideData = $guideResult->fetch_assoc();
                $guideId = $guideData['guideId'];
            }
            $guideStmt->close();
        }
        
        if (empty($guideId)) {
            send_error_response('Guide ID not found', 404);
        }
        
        // 페이지네이션 파라미터
        $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        // 필터 파라미터
        $search = $input['search'] ?? '';
        $selectedDate = $input['selectedDate'] ?? null;
        
        // bookings 테이블 컬럼 확인
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }
        
        // packages 테이블 존재 확인
        $packagesTableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
        $hasPackagesTable = ($packagesTableCheck && $packagesTableCheck->num_rows > 0);
        
        // packages 테이블 컬럼 확인
        $packagesColumns = [];
        if ($hasPackagesTable) {
            $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
            if ($packageColumnResult) {
                while ($col = $packageColumnResult->fetch_assoc()) {
                    $packagesColumns[] = strtolower($col['Field']);
                }
            }
        }

        // packageId 컬럼명 편차 흡수(bookings/packages)
        $bookingPackageIdCol = '';
        if (in_array('packageid', $bookingsColumns, true)) $bookingPackageIdCol = 'b.packageId';
        else if (in_array('package_id', $bookingsColumns, true)) $bookingPackageIdCol = 'b.package_id';

        $packagePkCol = '';
        if ($hasPackagesTable) {
            try {
                $pCols = [];
                $pr = $conn->query("SHOW COLUMNS FROM packages");
                while ($pr && ($c = $pr->fetch_assoc())) $pCols[strtolower((string)$c['Field'])] = (string)$c['Field'];
                if (isset($pCols['packageid'])) $packagePkCol = 'p.' . $pCols['packageid'];
                else if (isset($pCols['package_id'])) $packagePkCol = 'p.' . $pCols['package_id'];
                else $packagePkCol = 'p.packageId';
            } catch (Throwable $e) {
                $packagePkCol = 'p.packageId';
            }
        }
        $canJoinPackages = ($hasPackagesTable && $bookingPackageIdCol !== '');
        
        // client 테이블 존재 확인
        $clientTableCheck = $conn->query("SHOW TABLES LIKE 'client'");
        $hasClientTable = ($clientTableCheck && $clientTableCheck->num_rows > 0);
        
        // 컬럼명 결정
        $dateColumn = in_array('departuredate', $bookingsColumns) ? 'b.departureDate' : 
                      (in_array('startdate', $bookingsColumns) ? 'b.startDate' : 'b.createdAt');
        $returnDateColumn = in_array('returndate', $bookingsColumns) ? 'b.returnDate' : null;
        
        // packageName: bookings 상품명이 비어있으면 packages 상품명으로 fallback
        $pPackageNameExpr = packages_package_name_expr($conn);
        $bPackageNameExpr = bookings_package_name_expr($bookingsColumns);
        if ($bPackageNameExpr !== '') {
            $packageNameColumn = $canJoinPackages && $pPackageNameExpr !== "''"
                ? "COALESCE(NULLIF($bPackageNameExpr,''), $pPackageNameExpr)"
                : $bPackageNameExpr;
        } else {
            $packageNameColumn = $canJoinPackages ? $pPackageNameExpr : "''";
        }
        
        // 인원 수 계산
        $travelersSelect = '0';
        if (in_array('adults', $bookingsColumns) || in_array('numberoftravelers', $bookingsColumns)) {
            if (in_array('numberoftravelers', $bookingsColumns)) {
                $travelersSelect = 'b.numberOfTravelers';
            } else {
                $adults = in_array('adults', $bookingsColumns) ? 'b.adults' : '0';
                $children = in_array('children', $bookingsColumns) ? 'b.children' : '0';
                $infants = in_array('infants', $bookingsColumns) ? 'b.infants' : '0';
                $travelersSelect = "COALESCE($adults, 0) + COALESCE($children, 0) + COALESCE($infants, 0)";
            }
        }
        
        // 예약자명 (client 테이블에서 가져오기)
        $reserverNameSelect = "'' as reserverName";
        if ($hasClientTable && in_array('accountid', $bookingsColumns)) {
            $reserverNameSelect = "CONCAT(COALESCE(c.fName, ''), ' ', COALESCE(c.lName, '')) as reserverName";
        }
        
        // duration 계산 (진행 상태 결정에 필요) - package_schedules 우선 사용
        $durationDays = "COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1)";

        // returnDate 계산식 (진행 상태 결정에 사용)
        $returnDateExpression = '';
        if ($returnDateColumn) {
            $returnDateExpression = "DATE($returnDateColumn)";
        } else {
            $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL ($durationDays - 1) DAY)";
        }
        
        // 진행 상태 결정 (여행 시작일과 종료일을 비교)
        $statusSelect = "CASE 
            WHEN b.bookingStatus = 'cancelled' THEN 'Cancelled'
            WHEN b.bookingStatus = 'completed' THEN 'Trip completed'
            WHEN b.bookingStatus = 'confirmed' AND DATE($dateColumn) > CURDATE() THEN 'Scheduled departure'
            WHEN b.bookingStatus = 'confirmed' AND DATE($dateColumn) <= CURDATE() AND $returnDateExpression >= CURDATE() THEN 'Traveling'
            WHEN b.bookingStatus = 'confirmed' AND $returnDateExpression < CURDATE() THEN 'Trip completed'
            ELSE 'Check required'
        END as progressStatus";
        
        // SELECT 절 구성
        $selectFields = [
            'b.bookingId',
            $packageNameColumn . ' as packageName',
            "DATE($dateColumn) as travelStartDate",
            $reserverNameSelect,
            "$travelersSelect as numberOfPeople",
            $statusSelect
        ];
        
        $fromClause = "FROM bookings b";
        $joinClause = "";
        
        // packages JOIN은 packageName fallback/검색을 위해 필요할 수 있으므로, 가능한 경우 항상 붙인다.
        if ($canJoinPackages) {
            $joinClause .= " LEFT JOIN packages p ON {$bookingPackageIdCol} = {$packagePkCol}";
        }
        
        if ($hasClientTable && in_array('accountid', $bookingsColumns)) {
            $joinClause .= " LEFT JOIN client c ON b.accountId = c.accountId";
        }
        
        // guide 배정 매핑: bookings.guideId 우선, 없으면 booking_guides(있을 때)로 fallback
        $hasGuideIdColumn = in_array('guideid', $bookingsColumns);
        $bookingGuidesTableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
        $hasBookingGuidesTable = ($bookingGuidesTableCheck && $bookingGuidesTableCheck->num_rows > 0);
        $guideWhereField = 'b.guideId';
        if (!$hasGuideIdColumn) {
            if ($hasBookingGuidesTable) {
                $guideWhereField = 'bg.guideId';
                $joinClause .= " INNER JOIN booking_guides bg ON b.bookingId = bg.bookingId";
            } else {
                send_error_response('Guide assignment mapping not found (bookings.guideId or booking_guides)', 500);
            }
        }
        
        // WHERE 조건 구성
        $whereConditions = [$guideWhereField . ' = ?'];
        $whereParams = [$guideId];
        $whereTypes = 'i';
        
        // 검색 필터 (client 조인 유무에 따라 조건 변경)
        if (!empty($search)) {
            $searchParam = '%' . $search . '%';
            if ($hasClientTable && in_array('accountid', $bookingsColumns)) {
                $whereConditions[] = "($packageNameColumn LIKE ? OR c.fName LIKE ? OR c.lName LIKE ?)";
                $whereParams[] = $searchParam;
                $whereParams[] = $searchParam;
                $whereParams[] = $searchParam;
                $whereTypes .= 'sss';
            } else {
                $whereConditions[] = "($packageNameColumn LIKE ?)";
                $whereParams[] = $searchParam;
                $whereTypes .= 's';
            }
        }
        
        // 날짜 필터
        if (!empty($selectedDate)) {
            $whereConditions[] = "DATE($dateColumn) = ?";
            $whereParams[] = $selectedDate;
            $whereTypes .= 's';
        }
        
        $whereClause = "WHERE " . implode(' AND ', $whereConditions);
        
        // 총 개수 조회
        $countSql = "
            SELECT COUNT(*) as total
            $fromClause $joinClause
            $whereClause
        ";
        
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) {
            throw new Exception('Failed to prepare count query: ' . $conn->error);
        }
        if (!empty($whereParams)) {
            mysqli_bind_params_by_ref($countStmt, $whereTypes, $whereParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalCount = 0;
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $totalCount = intval($countRow['total'] ?? 0);
        }
        $countStmt->close();
        
        // 데이터 조회
        $dataSql = "
            SELECT " . implode(', ', $selectFields) . "
            $fromClause $joinClause
            $whereClause
            ORDER BY $dateColumn DESC
            LIMIT ? OFFSET ?
        ";
        
        $dataStmt = $conn->prepare($dataSql);
        if (!$dataStmt) {
            throw new Exception('Failed to prepare data query: ' . $conn->error);
        }
        $dataParams = array_merge($whereParams, [$limit, $offset]);
        $dataTypes = $whereTypes . 'ii';
        mysqli_bind_params_by_ref($dataStmt, $dataTypes, $dataParams);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();
        
        $bookings = [];
        while ($row = $dataResult->fetch_assoc()) {
            $bookings[] = [
                'bookingId' => $row['bookingId'],
                'packageName' => $row['packageName'] ?? '',
                'travelStartDate' => $row['travelStartDate'] ?? '',
                'reserverName' => trim($row['reserverName'] ?? ''),
                'numberOfPeople' => intval($row['numberOfPeople'] ?? 0),
                'progressStatus' => $row['progressStatus'] ?? 'Check required'
            ];
        }
        $dataStmt->close();
        
        $totalPages = (int)ceil($totalCount / $limit);
        if ($totalPages < 1) $totalPages = 1;
        
        send_success_response([
            'bookings' => $bookings,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'limit' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        send_error_response('Failed to get assigned bookings: ' . $e->getMessage(), 500);
    }
}

/**
 * 오늘 날짜 기준 "진행 중인" 예약 목록 조회
 * - today-schedule-detail.html에서 bookingId가 없을 때 자동 선택/목록 표시용
 */
function getTodayBookings($conn, $input) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $guideAccountId = $_SESSION['guide_accountId'] ?? ($_SESSION['accountId'] ?? null);
        if (empty($guideAccountId)) {
            send_error_response('Guide login required', 401);
        }
        $guideAccountId = intval($guideAccountId);

        // guideId 찾기
        $guideIdSql = "SELECT guideId FROM guides WHERE accountId = ? LIMIT 1";
        $guideIdStmt = $conn->prepare($guideIdSql);
        $guideIdStmt->bind_param('i', $guideAccountId);
        $guideIdStmt->execute();
        $guideIdResult = $guideIdStmt->get_result();
        $guideId = null;
        if ($guideIdResult && $guideIdResult->num_rows > 0) {
            $guideIdRow = $guideIdResult->fetch_assoc();
            $guideId = $guideIdRow['guideId'];
        }
        $guideIdStmt->close();

        if (empty($guideId)) {
            send_error_response('Guide ID not found', 404);
        }

        $today = date('Y-m-d');

        // bookings/packages 스키마 확인
        $bookingsColumns = [];
        $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
        if ($bookingColumnResult) {
            while ($col = $bookingColumnResult->fetch_assoc()) {
                $bookingsColumns[] = strtolower($col['Field']);
            }
        }

        $packagesColumns = [];
        $packagesTableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
        $hasPackagesTable = ($packagesTableCheck && $packagesTableCheck->num_rows > 0);
        if ($hasPackagesTable) {
            $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
            if ($packageColumnResult) {
                while ($col = $packageColumnResult->fetch_assoc()) {
                    $packagesColumns[] = strtolower($col['Field']);
                }
            }
        }

        $dateColumn = in_array('departuredate', $bookingsColumns) ? 'b.departureDate' :
            (in_array('startdate', $bookingsColumns) ? 'b.startDate' : 'b.createdAt');
        $returnDateColumn = in_array('returndate', $bookingsColumns) ? 'b.returnDate' : null;
        $hasBookingPackageName = in_array('packagename', $bookingsColumns, true);
        $pPackageNameExpr = packages_package_name_expr($conn);
        $bPackageNameExpr = bookings_package_name_expr($bookingsColumns);
        $packageNameColumn = ($hasPackagesTable && $bPackageNameExpr !== '' && $pPackageNameExpr !== "''")
            ? "COALESCE(NULLIF($bPackageNameExpr,''), $pPackageNameExpr)"
            : ($bPackageNameExpr !== '' ? $bPackageNameExpr : $pPackageNameExpr);

        // duration 계산 - package_schedules 우선 사용
        $durationDays = "COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1)";

        // returnDate 계산식 (진행 상태 결정에 사용)
        if ($returnDateColumn) {
            $returnDateExpression = "DATE($returnDateColumn)";
        } else {
            $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL ($durationDays - 1) DAY)";
        }

        $joinPackages = $hasPackagesTable ? "LEFT JOIN packages p ON b.packageId = p.packageId" : "";
        if (!$hasPackagesTable) {
            // packages 테이블이 없으면 bookings.packageName 우선
            $packageNameColumn = 'b.packageName';
            $durationDays = 'NULL';
        }

        // guide 배정 매핑: bookings.guideId 우선, 없으면 booking_guides로 fallback
        $hasGuideIdColumn = in_array('guideid', $bookingsColumns);
        $bookingGuidesTableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
        $hasBookingGuidesTable = ($bookingGuidesTableCheck && $bookingGuidesTableCheck->num_rows > 0);
        $guideJoin = '';
        $guideWhereField = 'b.guideId';
        if (!$hasGuideIdColumn) {
            if ($hasBookingGuidesTable) {
                $guideJoin = "INNER JOIN booking_guides bg ON b.bookingId = bg.bookingId";
                $guideWhereField = 'bg.guideId';
            } else {
                send_error_response('Guide assignment mapping not found (bookings.guideId or booking_guides)', 500);
            }
        }

        $sql = "SELECT
            b.bookingId,
            $packageNameColumn AS packageName,
            DATE($dateColumn) AS travelStartDate,
            $returnDateExpression AS travelEndDate
        FROM bookings b
        $guideJoin
        $joinPackages
        WHERE $guideWhereField = ?
          AND DATE($dateColumn) <= ?
          AND $returnDateExpression >= ?
        ORDER BY DATE($dateColumn) ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $guideId, $today, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
        }
        $stmt->close();

        send_success_response(['bookings' => $rows, 'today' => $today]);
    } catch (Exception $e) {
        send_error_response('Failed to get today bookings: ' . $e->getMessage());
    }
}

function getAssignedBookingDetail($conn, $input) {
    try {
        // 세션 확인
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $guideAccountId = $_SESSION['guide_accountId'] ?? null;
        if (empty($guideAccountId)) {
            send_error_response('Guide login required', 401);
        }
        
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        
        // guideId 찾기
        $guideIdSql = "SELECT guideId FROM guides WHERE accountId = ? LIMIT 1";
        $guideIdStmt = $conn->prepare($guideIdSql);
        $guideIdStmt->bind_param('s', $guideAccountId);
        $guideIdStmt->execute();
        $guideIdResult = $guideIdStmt->get_result();
        $guideId = null;
        if ($guideIdResult && $guideIdResult->num_rows > 0) {
            $guideIdRow = $guideIdResult->fetch_assoc();
            $guideId = $guideIdRow['guideId'];
        }
        $guideIdStmt->close();
        
        if (empty($guideId)) {
            send_error_response('Guide ID not found', 404);
        }
        
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
        $packagesTableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
        $hasPackagesTable = ($packagesTableCheck && $packagesTableCheck->num_rows > 0);
        if ($hasPackagesTable) {
            $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
            if ($packageColumnResult) {
                while ($col = $packageColumnResult->fetch_assoc()) {
                    $packagesColumns[] = strtolower($col['Field']);
                }
            }
        }
        
        $dateColumn = in_array('departuredate', $bookingsColumns) ? 'b.departureDate' : 
                      (in_array('startdate', $bookingsColumns) ? 'b.startDate' : 'b.createdAt');
        $returnDateColumn = in_array('returndate', $bookingsColumns) ? 'b.returnDate' : null;
        $pPackageNameExpr = packages_package_name_expr($conn);
        $bPackageNameExpr = bookings_package_name_expr($bookingsColumns);
        $packageNameColumn = ($hasPackagesTable && $bPackageNameExpr !== '' && $pPackageNameExpr !== "''")
            ? "COALESCE(NULLIF($bPackageNameExpr,''), $pPackageNameExpr)"
            : ($bPackageNameExpr !== '' ? $bPackageNameExpr : $pPackageNameExpr);

        // 미팅 정보(packages) - 운영 스키마 편차 대응
        $meetingTimeSelect = 'NULL as meetingTime';
        $meetingLocationSelect = 'NULL as meetingLocation';
        $meetingAddressSelect = 'NULL as meetingAddress';
        if ($hasPackagesTable) {
            if (in_array('meeting_time', $packagesColumns, true)) $meetingTimeSelect = 'p.meeting_time as meetingTime';
            else if (in_array('meetingtime', $packagesColumns, true)) $meetingTimeSelect = 'p.meetingTime as meetingTime';

            if (in_array('meeting_location', $packagesColumns, true)) $meetingLocationSelect = 'p.meeting_location as meetingLocation';
            else if (in_array('meetinglocation', $packagesColumns, true)) $meetingLocationSelect = 'p.meetingLocation as meetingLocation';

            if (in_array('meeting_address', $packagesColumns, true)) $meetingAddressSelect = 'p.meeting_address as meetingAddress';
            else if (in_array('meetingaddress', $packagesColumns, true)) $meetingAddressSelect = 'p.meetingAddress as meetingAddress';
        }
        
        // duration 계산 - package_schedules 우선 사용
        $durationDays = "COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1)";

        // returnDate 계산식
        $returnDateExpression = '';
        if ($returnDateColumn) {
            $returnDateExpression = $returnDateColumn;
        } else {
            $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL ($durationDays - 1) DAY)";
        }
        
        // guide 배정 매핑: bookings.guideId 우선, 없으면 booking_guides로 fallback
        $hasGuideIdColumn = in_array('guideid', $bookingsColumns);
        $bookingGuidesTableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
        $hasBookingGuidesTable = ($bookingGuidesTableCheck && $bookingGuidesTableCheck->num_rows > 0);
        $guideJoin = '';
        $guideWhereField = 'b.guideId';
        if (!$hasGuideIdColumn) {
            if ($hasBookingGuidesTable) {
                $guideJoin = "INNER JOIN booking_guides bg ON b.bookingId = bg.bookingId";
                $guideWhereField = 'bg.guideId';
            } else {
                send_error_response('Guide assignment mapping not found (bookings.guideId or booking_guides)', 500);
            }
        }

        $sql = "SELECT 
            b.*,
            $packageNameColumn as packageName,
            $durationDays as durationDays,
            DATE($dateColumn) as travelStartDate,
            $returnDateExpression as travelEndDate,
            $meetingTimeSelect,
            $meetingLocationSelect,
            $meetingAddressSelect,
            c.fName,
            c.lName,
            c.emailAddress,
            c.contactNo
        FROM bookings b
        $guideJoin
        LEFT JOIN packages p ON b.packageId = p.packageId
        LEFT JOIN client c ON b.accountId = c.accountId
        WHERE b.bookingId = ? AND $guideWhereField = ?";
        
        $stmt = $conn->prepare($sql);
        // bookingId는 환경에 따라 문자열(BK...)일 수 있어 문자열로 바인딩
        $stmt->bind_param('si', $bookingId, $guideId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Booking not found', 404);
        }
        
        $booking = $result->fetch_assoc();
        $stmt->close();

        // meetingTime이 TIME 값(날짜 없이)인 환경이면 travelStartDate와 결합
        try {
            $booking['meetingTime'] = combine_date_time_if_time_only($booking['travelStartDate'] ?? null, $booking['meetingTime'] ?? null);
        } catch (Throwable $e) { /* ignore */ }

        // 미팅 장소명+주소를 한 칸에서 보여줘야 하는 UI(guide) 대응: meetingLocation에 주소를 병합
        try {
            $ml = trim((string)($booking['meetingLocation'] ?? ''));
            $ma = trim((string)($booking['meetingAddress'] ?? ''));
            if ($ml !== '' && $ma !== '') {
                // 이미 포함되어 있으면 중복 결합 방지
                if (stripos($ml, $ma) === false) {
                    $booking['meetingLocation'] = $ml . ' ' . $ma;
                }
            } elseif ($ml === '' && $ma !== '') {
                $booking['meetingLocation'] = $ma;
            }
        } catch (Throwable $e) { /* ignore */ }
        
        // 항공편 정보 조회 (package_flights 테이블)
        $flights = [];
        $flightsTableCheck = $conn->query("SHOW TABLES LIKE 'package_flights'");
        if ($flightsTableCheck && $flightsTableCheck->num_rows > 0 && !empty($booking['packageId'])) {
            $flightsSql = "SELECT 
                flight_type,
                flight_number,
                departure_time,
                arrival_time,
                departure_point,
                destination
            FROM package_flights 
            WHERE package_id = ? 
            ORDER BY flight_type";
            $flightsStmt = $conn->prepare($flightsSql);
            if ($flightsStmt) {
                $flightsStmt->bind_param('i', $booking['packageId']);
                $flightsStmt->execute();
                $flightsResult = $flightsStmt->get_result();
                while ($flight = $flightsResult->fetch_assoc()) {
                    $flights[$flight['flight_type']] = $flight;
                }
                $flightsStmt->close();
            }
        }
        // 항공편 시간에 날짜를 합쳐서(YYYY-MM-DD HH:MM:SS) 가이드 UI에 "출발일시/도착일시"로 표시되게 한다.
        $startDate = trim((string)($booking['travelStartDate'] ?? ''));
        $endDate = trim((string)($booking['travelEndDate'] ?? ''));
        $combineDateTime = function (?string $date, ?string $time): string {
            $d = trim((string)($date ?? ''));
            $t = trim((string)($time ?? ''));
            if ($t === '') return '';
            // 이미 날짜가 포함된 경우(YYYY-MM-DD ...) 그대로 사용
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $t)) return $t;
            if ($d === '') return $t;
            // TIME 값은 보통 HH:MM:SS 형태
            return $d . ' ' . $t;
        };
        foreach ($flights as $k => $f) {
            $type = strtolower(trim((string)($f['flight_type'] ?? $k)));
            $useDate = $startDate;
            if (in_array($type, ['return','inbound','back'], true)) $useDate = ($endDate !== '' ? $endDate : $startDate);
            if (in_array($type, ['departure','outbound','go'], true)) $useDate = $startDate;
            $f['departure_time'] = $combineDateTime($useDate, $f['departure_time'] ?? null);
            $f['arrival_time'] = $combineDateTime($useDate, $f['arrival_time'] ?? null);
            $flights[$k] = $f;
        }
        $booking['flights'] = $flights;
        
        // 예약 인원 및 룸 옵션
        $adults = in_array('adults', $bookingsColumns) ? intval($booking['adults'] ?? 0) : 0;
        $children = in_array('children', $bookingsColumns) ? intval($booking['children'] ?? 0) : 0;
        $infants = in_array('infants', $bookingsColumns) ? intval($booking['infants'] ?? 0) : 0;
        $booking['reservationPeople'] = [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants
        ];
        
        // 룸 옵션 조회 (booking_rooms 테이블 - 스키마 편차 대응)
        $rooms = [];
        $roomsTableCheck = $conn->query("SHOW TABLES LIKE 'booking_rooms'");
        if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
            $roomsCols = [];
            $roomsColRes = $conn->query("SHOW COLUMNS FROM booking_rooms");
            if ($roomsColRes) {
                while ($rc = $roomsColRes->fetch_assoc()) {
                    $roomsCols[] = strtolower($rc['Field']);
                }
            }
            $roomsBookingIdCol = null;
            if (in_array('bookingid', $roomsCols, true)) $roomsBookingIdCol = 'bookingId';
            else if (in_array('transactno', $roomsCols, true)) $roomsBookingIdCol = 'transactNo';
            else if (in_array('booking_id', $roomsCols, true)) $roomsBookingIdCol = 'booking_id';

            if ($roomsBookingIdCol) {
                $roomsSql = "SELECT roomType, roomCount FROM booking_rooms WHERE {$roomsBookingIdCol} = ?";
                $roomsStmt = $conn->prepare($roomsSql);
                if ($roomsStmt) {
                    $roomsStmt->bind_param('s', $bookingId);
                    $roomsStmt->execute();
                    $roomsResult = $roomsStmt->get_result();
                    while ($room = $roomsResult->fetch_assoc()) {
                        $rooms[] = $room;
                    }
                    $roomsStmt->close();
                }
            }
        }
        $booking['rooms'] = $rooms;
        
        // 인원 옵션명(product_pricing) 추출 (있으면 travelerType -> optionName 매칭에 사용)
        $pricingRaw = $booking['product_pricing'] ?? ($booking['productPricing'] ?? '');
        $peopleOptions = extract_people_options_from_pricing(is_string($pricingRaw) ? $pricingRaw : null);
        // peopleOptions를 booking 객체에 포함 (클라이언트에서 사용)
        $booking['peopleOptions'] = $peopleOptions;

        // 여행자 정보 조회 (booking_travelers 테이블, 스키마 편차 흡수)
        $travelers = [];
        $travelersTableCheck = $conn->query("SHOW TABLES LIKE 'booking_travelers'");
        if ($travelersTableCheck && $travelersTableCheck->num_rows > 0) {
            $tmap = booking_travelers_column_map($conn);
            $travelerColumns = [];
            $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
            if ($travelerColumnCheck) {
                while ($col = $travelerColumnCheck->fetch_assoc()) {
                    $travelerColumns[] = strtolower($col['Field']);
                }
            }
            
            $travelerBookingIdColumn = $tmap['bookingId'] ?? 'transactNo';
            
            $orderBy = [];
            if (in_array('travelertype', $travelerColumns)) {
                $orderBy[] = "CASE travelerType WHEN 'adult' THEN 1 WHEN 'child' THEN 2 WHEN 'infant' THEN 3 END";
            }
            if (in_array('ismaintraveler', $travelerColumns)) {
                $orderBy[] = 'isMainTraveler DESC';
            }
            if (in_array('bookingtravelerid', $travelerColumns)) {
                $orderBy[] = 'bookingTravelerId ASC';
            }
            $orderByClause = !empty($orderBy) ? 'ORDER BY ' . implode(', ', $orderBy) : '';
            
            $firstCol = $tmap['firstName'] ?? 'firstName';
            $lastCol = $tmap['lastName'] ?? 'lastName';
            $passCol = $tmap['passportNumber'] ?? 'passportNumber';
            $typeCol = $tmap['travelerType'] ?? 'travelerType';
            $travelersSql = "SELECT 
                `$firstCol` as firstName,
                `$lastCol` as lastName,
                `$passCol` as passportNumber,
                `$typeCol` as travelerType
            FROM booking_travelers
            WHERE `$travelerBookingIdColumn` = ?
            $orderByClause";
            $travelersStmt = $conn->prepare($travelersSql);
            if ($travelersStmt) {
                $travelersStmt->bind_param('s', $bookingId);
                $travelersStmt->execute();
                $travelersResult = $travelersStmt->get_result();
                while ($traveler = $travelersResult->fetch_assoc()) {
                    $travelerType = strtolower($traveler['travelerType'] ?? 'adult');
                    $peopleOption = map_traveler_type_to_people_option($travelerType, $peopleOptions);
                    $travelers[] = [
                        'firstName' => $traveler['firstName'] ?? '',
                        'lastName' => $traveler['lastName'] ?? '',
                        'passportNumber' => $traveler['passportNumber'] ?? '',
                        'peopleOption' => $peopleOption
                    ];
                }
                $travelersStmt->close();
            }
        }
        $booking['travelers'] = $travelers;
        
        send_success_response(['booking' => $booking]);
    } catch (Exception $e) {
        send_error_response('Failed to get booking detail: ' . $e->getMessage());
    }
}

function getTodayScheduleDetail($conn, $input) {
    try {
        // 세션 확인
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $guideAccountId = $_SESSION['guide_accountId'] ?? null;
        if (empty($guideAccountId)) {
            send_error_response('Guide login required', 401);
        }
        
        $bookingId = $input['bookingId'] ?? $input['id'] ?? null;
        if (empty($bookingId)) {
            send_error_response('Booking ID is required');
        }
        
        // guideId 찾기
        $guideIdSql = "SELECT guideId FROM guides WHERE accountId = ? LIMIT 1";
        $guideIdStmt = $conn->prepare($guideIdSql);
        $guideIdStmt->bind_param('s', $guideAccountId);
        $guideIdStmt->execute();
        $guideIdResult = $guideIdStmt->get_result();
        $guideId = null;
        if ($guideIdResult && $guideIdResult->num_rows > 0) {
            $guideIdRow = $guideIdResult->fetch_assoc();
            $guideId = $guideIdRow['guideId'];
        }
        $guideIdStmt->close();
        
        if (empty($guideId)) {
            send_error_response('Guide ID not found', 404);
        }
        
        // 오늘 날짜 (여행 기간 내 포함 여부를 확인)
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
        $packagesTableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
        $hasPackagesTable = ($packagesTableCheck && $packagesTableCheck->num_rows > 0);
        if ($hasPackagesTable) {
            $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
            if ($packageColumnResult) {
                while ($col = $packageColumnResult->fetch_assoc()) {
                    $packagesColumns[] = strtolower($col['Field']);
                }
            }
        }
        
        $dateColumn = in_array('departuredate', $bookingsColumns) ? 'b.departureDate' : 
                      (in_array('startdate', $bookingsColumns) ? 'b.startDate' : 'b.createdAt');
        $returnDateColumn = in_array('returndate', $bookingsColumns) ? 'b.returnDate' : null;
        $pPackageNameExpr = packages_package_name_expr($conn);
        $bPackageNameExpr = bookings_package_name_expr($bookingsColumns);
        $packageNameColumn = ($hasPackagesTable && $bPackageNameExpr !== '' && $pPackageNameExpr !== "''")
            ? "COALESCE(NULLIF($bPackageNameExpr,''), $pPackageNameExpr)"
            : ($bPackageNameExpr !== '' ? $bPackageNameExpr : $pPackageNameExpr);
        
        // duration 계산 - package_schedules 우선 사용
        $durationDays = "COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, p.durationDays, 1)";

        // returnDate 계산식
        $returnDateExpression = '';
        if ($returnDateColumn) {
            $returnDateExpression = $returnDateColumn;
        } else {
            $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL ($durationDays - 1) DAY)";
        }
        
        // guide 배정 매핑: bookings.guideId 우선, 없으면 booking_guides로 fallback
        $hasGuideIdColumn = in_array('guideid', $bookingsColumns);
        $bookingGuidesTableCheck = $conn->query("SHOW TABLES LIKE 'booking_guides'");
        $hasBookingGuidesTable = ($bookingGuidesTableCheck && $bookingGuidesTableCheck->num_rows > 0);
        $guideJoin = '';
        $guideWhereField = 'b.guideId';
        if (!$hasGuideIdColumn) {
            if ($hasBookingGuidesTable) {
                $guideJoin = "INNER JOIN booking_guides bg ON b.bookingId = bg.bookingId";
                $guideWhereField = 'bg.guideId';
            } else {
                send_error_response('Guide assignment mapping not found (bookings.guideId or booking_guides)', 500);
            }
        }

        $sql = "SELECT 
            b.*,
            $packageNameColumn as packageName,
            $durationDays as durationDays,
            DATE($dateColumn) as travelStartDate,
            $returnDateExpression as travelEndDate,
            c.fName,
            c.lName,
            c.emailAddress,
            c.contactNo
        FROM bookings b
        $guideJoin
        LEFT JOIN packages p ON b.packageId = p.packageId
        LEFT JOIN client c ON b.accountId = c.accountId
        WHERE b.bookingId = ?
          AND $guideWhereField = ?
          AND DATE($dateColumn) <= ?
          AND $returnDateExpression >= ?";
        
        $stmt = $conn->prepare($sql);
        // bookingId는 문자열 가능
        $stmt->bind_param('siss', $bookingId, $guideId, $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_error_response('Schedule not found', 404);
        }
        
        $schedule = $result->fetch_assoc();
        $stmt->close();

        // meetingTime이 TIME 값(날짜 없이)인 환경이면 travelStartDate와 결합
        try {
            $schedule['meetingTime'] = combine_date_time_if_time_only($schedule['travelStartDate'] ?? null, $schedule['meetingTime'] ?? null);
        } catch (Throwable $e) { /* ignore */ }
        
        // 항공편 정보 조회 (package_flights 테이블)
        $flights = [];
        $flightsTableCheck = $conn->query("SHOW TABLES LIKE 'package_flights'");
        if ($flightsTableCheck && $flightsTableCheck->num_rows > 0 && !empty($schedule['packageId'])) {
            $flightsSql = "SELECT 
                flight_type,
                flight_number,
                departure_time,
                arrival_time,
                departure_point,
                destination
            FROM package_flights 
            WHERE package_id = ? 
            ORDER BY flight_type";
            $flightsStmt = $conn->prepare($flightsSql);
            if ($flightsStmt) {
                $flightsStmt->bind_param('i', $schedule['packageId']);
                $flightsStmt->execute();
                $flightsResult = $flightsStmt->get_result();
                while ($flight = $flightsResult->fetch_assoc()) {
                    $flights[$flight['flight_type']] = $flight;
                }
                $flightsStmt->close();
            }
        }
        $schedule['flights'] = $flights;
        
        // 인원 옵션명(product_pricing) 추출 (있으면 travelerType -> optionName 매칭에 사용)
        $pricingRaw = $schedule['product_pricing'] ?? ($schedule['productPricing'] ?? '');
        $peopleOptions = extract_people_options_from_pricing(is_string($pricingRaw) ? $pricingRaw : null);

        // 예약 인원 및 룸 옵션
        $adults = in_array('adults', $bookingsColumns) ? intval($schedule['adults'] ?? 0) : 0;
        $children = in_array('children', $bookingsColumns) ? intval($schedule['children'] ?? 0) : 0;
        $infants = in_array('infants', $bookingsColumns) ? intval($schedule['infants'] ?? 0) : 0;
        $schedule['reservationPeople'] = [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants
        ];
        
        // 룸 옵션 조회 (booking_rooms 테이블 - 스키마 편차 대응)
        $rooms = [];
        $roomsTableCheck = $conn->query("SHOW TABLES LIKE 'booking_rooms'");
        if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
            $roomsCols = [];
            $roomsColRes = $conn->query("SHOW COLUMNS FROM booking_rooms");
            if ($roomsColRes) {
                while ($rc = $roomsColRes->fetch_assoc()) {
                    $roomsCols[] = strtolower($rc['Field']);
                }
            }
            $roomsBookingIdCol = null;
            if (in_array('bookingid', $roomsCols, true)) $roomsBookingIdCol = 'bookingId';
            else if (in_array('transactno', $roomsCols, true)) $roomsBookingIdCol = 'transactNo';
            else if (in_array('booking_id', $roomsCols, true)) $roomsBookingIdCol = 'booking_id';

            if ($roomsBookingIdCol) {
                $roomsSql = "SELECT roomType, roomCount FROM booking_rooms WHERE {$roomsBookingIdCol} = ?";
                $roomsStmt = $conn->prepare($roomsSql);
                if ($roomsStmt) {
                    $roomsStmt->bind_param('s', $bookingId);
                    $roomsStmt->execute();
                    $roomsResult = $roomsStmt->get_result();
                    while ($room = $roomsResult->fetch_assoc()) {
                        $rooms[] = $room;
                    }
                    $roomsStmt->close();
                }
            }
        }
        $schedule['rooms'] = $rooms;
        
        // 여행자 정보 조회 (booking_travelers 테이블, 스키마 편차 흡수)
        $travelers = [];
        $travelersTableCheck = $conn->query("SHOW TABLES LIKE 'booking_travelers'");
        if ($travelersTableCheck && $travelersTableCheck->num_rows > 0) {
            $tmap = booking_travelers_column_map($conn);
            $travelerColumns = [];
            $travelerColumnCheck = $conn->query("SHOW COLUMNS FROM booking_travelers");
            if ($travelerColumnCheck) {
                while ($col = $travelerColumnCheck->fetch_assoc()) {
                    $travelerColumns[] = strtolower($col['Field']);
                }
            }
            
            $travelerBookingIdColumn = $tmap['bookingId'] ?? 'transactNo';
            
            $orderBy = [];
            if (in_array('travelertype', $travelerColumns)) {
                $orderBy[] = "CASE travelerType WHEN 'adult' THEN 1 WHEN 'child' THEN 2 WHEN 'infant' THEN 3 END";
            }
            if (in_array('ismaintraveler', $travelerColumns)) {
                $orderBy[] = 'isMainTraveler DESC';
            }
            if (in_array('bookingtravelerid', $travelerColumns)) {
                $orderBy[] = 'bookingTravelerId ASC';
            }
            $orderByClause = !empty($orderBy) ? 'ORDER BY ' . implode(', ', $orderBy) : '';
            
            $firstCol = $tmap['firstName'] ?? 'firstName';
            $lastCol = $tmap['lastName'] ?? 'lastName';
            $passCol = $tmap['passportNumber'] ?? 'passportNumber';
            $typeCol = $tmap['travelerType'] ?? 'travelerType';
            $travelersSql = "SELECT 
                `$firstCol` as firstName,
                `$lastCol` as lastName,
                `$passCol` as passportNumber,
                `$typeCol` as travelerType
            FROM booking_travelers
            WHERE `$travelerBookingIdColumn` = ?
            $orderByClause";
            $travelersStmt = $conn->prepare($travelersSql);
            if ($travelersStmt) {
                $travelersStmt->bind_param('s', $bookingId);
                $travelersStmt->execute();
                $travelersResult = $travelersStmt->get_result();
                while ($traveler = $travelersResult->fetch_assoc()) {
                    $travelerType = strtolower($traveler['travelerType'] ?? 'adult');
                    $peopleOption = map_traveler_type_to_people_option($travelerType, $peopleOptions);
                    $travelers[] = [
                        'firstName' => $traveler['firstName'] ?? '',
                        'lastName' => $traveler['lastName'] ?? '',
                        'passportNumber' => $traveler['passportNumber'] ?? '',
                        'peopleOption' => $peopleOption
                    ];
                }
                $travelersStmt->close();
            }
        }
        $schedule['travelers'] = $travelers;
        
        send_success_response(['schedule' => $schedule]);
    } catch (Exception $e) {
        send_error_response('Failed to get schedule detail: ' . $e->getMessage());
    }
}
?>
