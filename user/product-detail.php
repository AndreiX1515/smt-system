<?php
// 세션은 conn.php에서 시작 (세션 설정 적용을 위해 conn.php가 먼저)
require "../backend/conn.php";
require "../backend/i18n_helper.php";

// 현재 언어 설정
$currentLang = getCurrentLanguage();

// B2B/B2C 구분: account_type 세션 기반만 사용
// - B2B = 에이전트 계정 자체 (accountType = agent/admin_ph/admin_kr)
// - B2C = 모든 일반 고객 (에이전트 소속 여부 무관)
// 주의: agent_accountId 세션은 관리자 패널용이므로 여기서 체크하지 않음
//       (같은 브라우저에서 관리자 로그인 후 유저 페이지 접속 시 잔류할 수 있음)
$isB2B = false;
try {
    $sessionAccountType = strtolower(trim((string)($_SESSION['account_type'] ?? '')));

    if (in_array($sessionAccountType, ['agent', 'admin_ph', 'admin_kr'], true)) {
        $isB2B = true;
    }
} catch (Throwable $e) {
    // ignore (default: B2C)
}

// URL에서 상품 ID 가져오기
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
// SMT 수정 시작
$departureDate = isset($_GET['departureDate']) ? $_GET['departureDate'] : null;
// SMT 수정 종료
if ($productId <= 0) {
    // 잘못된 상품 ID면 언어/권한 플로우(index.html)로 보내지 말고 홈으로 돌려보냄
    header('Location: ../home.html?lang=' . urlencode($currentLang) . '&reason=invalid_product');
    exit;
}

// 상품 조회수(일별) 집계: packages 테이블에 viewCount 컬럼이 없어서 별도 테이블(package_views)을 사용합니다.
// 관리자 대시보드(overview)에서 조회수/예약률 계산에 사용됩니다.
try {
    $conn->query("CREATE TABLE IF NOT EXISTS package_views (
        packageId INT NOT NULL,
        viewDate DATE NOT NULL,
        viewCount INT NOT NULL DEFAULT 0,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (packageId, viewDate),
        INDEX idx_viewDate (viewDate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $today = date('Y-m-d');
    $pv = $conn->prepare("INSERT INTO package_views (packageId, viewDate, viewCount)
                          VALUES (?, ?, 1)
                          ON DUPLICATE KEY UPDATE viewCount = viewCount + 1");
    if ($pv) {
        $pv->bind_param('is', $productId, $today);
        $pv->execute();
        $pv->close();
    }
} catch (Exception $e) {
    error_log("package_views increment failed: " . $e->getMessage());
}

// 상품 기본 정보 조회
// - 기존 구현은 departureDate가 있을 때 일부 컬럼만 조회하여(패키지명/이미지 등 누락) 화면이 비어 보일 수 있음
// - 또한 템플릿에서 사용하는 packages 컬럼(예: usage_guide_file, refund_days 등)이 SELECT 목록에 없으면 항상 null로 들어옴
// → 항상 p.* 를 조회하고, bookedSeats만 서브쿼리로 계산하도록 통합

// 상품 코드 생성 (packageId 기반, 예: KOR00209)
// NOTE: packages 테이블에 별도 productCode 컬럼은 현재 없음. (있다면 해당 값 우선 사용 권장)
// 관리자 화면의 포맷(KOR + 5자리 0패딩)과 동일하게 맞춘다.
$productCode = 'KOR' . str_pad((string)$productId, 5, '0', STR_PAD_LEFT);

$sql = "SELECT
            p.*,
            COALESCE(b.booking_count, 0) AS bookedSeats
        FROM packages p
        LEFT JOIN (
            SELECT packageId, COUNT(*) AS booking_count
            FROM bookings
            WHERE paymentStatus <> 'refunded' ";

if ($departureDate) {
    $sql .= " AND DATE(departureDate) = ? ";
}

$sql .= " GROUP BY packageId
        ) b ON p.packageId = b.packageId
        WHERE p.packageId = ? AND p.isActive = 1";

$stmt = $conn->prepare($sql);
if ($departureDate) {
    $stmt->bind_param("si", $departureDate, $productId);
} else {
    $stmt->bind_param("i", $productId);
}

// $sql = "SELECT 
//     p.packageId,
//     p.packageName,
//     p.packageCategory,
//     p.subCategory,
//     p.packagePrice,
//     p.packageDuration,
//     p.packageType,
//     p.packageDescription,
//     p.thumbnail_image,
//     p.product_images,
//     p.detail_image,
//     p.included_items,
//     p.excluded_items,
//     p.meeting_time,
//     p.meeting_location,
//     p.meeting_address,
//     p.minParticipants,
//     p.maxParticipants,
//     p.sales_period,
//     p.createdAt
// FROM packages p 
// WHERE p.packageId = ? AND p.isActive = 1";

// // 상품 코드 생성 (packageId 기반, 예: KOR138)
// $productCode = 'KOR' . $productId;

// $stmt = $conn->prepare($sql);
// $stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

// 상품이 DB에 없더라도(또는 DB가 비어 있어도) 페이지 자체는 렌더링해서
// product-detail.js의 fallback 샘플 데이터가 동작할 수 있도록 합니다.
$product = null;
$productFound = ($result && $result->num_rows > 0);
if ($productFound) {
    $product = $result->fetch_assoc();
}
$stmt->close();

// 상품 판매대상(sales_target) 접근 제어: 제거됨
// 이중 가격 시스템으로 변경되어 모든 상품이 모든 사용자에게 노출됨
// B2B 사용자는 B2B 가격으로, B2C 사용자는 B2C 가격으로 예약함

if (!$productFound) {
    $product = [
        'packageId' => $productId,
        'packageName' => '스마트 트래블 패키지',
        'packageCategory' => '',
        'subCategory' => '',
        'packagePrice' => 0,
        'packageDuration' => '',
        'packageType' => 'standard',
        'packageDescription' => '',
        'thumbnail_image' => '',
        'product_images' => '[]',
        'detail_image' => '',
        'included_items' => '',
        'excluded_items' => '',
        'meeting_time' => '',
        'meeting_location' => '',
        'meeting_address' => '',
        'minParticipants' => 0,
        'maxParticipants' => 0,
        'sales_period' => null,
        'createdAt' => null,
        'bookedSeats' => 0,
        // 선택 컬럼(환경마다 있을 수 있음) - 화면에서 사용하므로 fallback도 넣어둠
        'usage_guide_file' => '',
        'usage_guide_name' => '',
        'refund_days' => null
    ];
}

// SMT 수정 시작
$product['bookedSeats'] = intval($product['bookedSeats'] ?? 0);
$product['maxParticipants'] = intval($product['maxParticipants'] ?? 0);
// SMT 수정 종료

// 항공편/일정/이용안내는 DB 상품이 있을 때만 조회 (없으면 JS fallback이 렌더링)
$flights = [];
$schedules = [];
$guides = [];
if ($productFound) {
    // 항공편 정보 조회
    $sql_flights = "SELECT 
        flight_type,
        flight_number,
        departure_time,
        arrival_time,
        departure_point,
        destination
    FROM package_flights 
    WHERE package_id = ? 
    ORDER BY flight_type";

    $stmt_flights = $conn->prepare($sql_flights);
    $stmt_flights->bind_param("i", $productId);
    $stmt_flights->execute();
    $result_flights = $stmt_flights->get_result();

    while ($row = $result_flights->fetch_assoc()) {
        $flights[$row['flight_type']] = $row;
    }
    $stmt_flights->close();

    // 일정표 정보 조회 (+ schedule_id: 다중 관광지(package_attractions) 연결용)
    $sql_schedules = "SELECT 
        schedule_id,
        day_number,
        description,
        start_time,
        end_time,
        airport_location,
        airport_address,
        airport_description,
        airport_image,
        accommodation_name,
        accommodation_address,
        accommodation_description,
        accommodation_image,
        transportation_description,
        breakfast,
        lunch,
        dinner
    FROM package_schedules 
    WHERE package_id = ? 
    ORDER BY day_number";

    $stmt_schedules = $conn->prepare($sql_schedules);
    $stmt_schedules->bind_param("i", $productId);
    $stmt_schedules->execute();
    $result_schedules = $stmt_schedules->get_result();

    $scheduleIds = [];
    while ($row = $result_schedules->fetch_assoc()) {
        $row['attractions'] = [];
        $schedules[] = $row;
        if (!empty($row['schedule_id'])) $scheduleIds[] = (int)$row['schedule_id'];
    }
    $stmt_schedules->close();

    // 다중 관광지 조회(package_attractions) → schedule_id 기준으로 매핑
    if (!empty($scheduleIds)) {
        $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $types = str_repeat('i', count($scheduleIds));
        $sql_attr = "SELECT
            attraction_id,
            schedule_id,
            attraction_name,
            attraction_address,
            attraction_description,
            attraction_image,
            visit_order,
            start_time,
            end_time
        FROM package_attractions
        WHERE schedule_id IN ($placeholders)
        ORDER BY schedule_id ASC, visit_order ASC, attraction_id ASC";

        $stmt_attr = $conn->prepare($sql_attr);
        $bind = [];
        $bind[] = &$types;
        foreach ($scheduleIds as $k => $v) $bind[] = &$scheduleIds[$k];
        call_user_func_array([$stmt_attr, 'bind_param'], $bind);
        $stmt_attr->execute();
        $result_attr = $stmt_attr->get_result();
        $attrBySchedule = [];
        while ($a = $result_attr->fetch_assoc()) {
            $sid = (int)$a['schedule_id'];
            if (!isset($attrBySchedule[$sid])) $attrBySchedule[$sid] = [];
            $attrBySchedule[$sid][] = $a;
        }
        $stmt_attr->close();

        // schedules에 주입
        foreach ($schedules as &$sc) {
            $sid = (int)($sc['schedule_id'] ?? 0);
            if ($sid > 0 && isset($attrBySchedule[$sid])) {
                $sc['attractions'] = $attrBySchedule[$sid];
            }
        }
        unset($sc);
    }

    // 이용안내 정보 조회
    $sql_guides = "SELECT 
        guide_type,
        guide_description
    FROM package_usage_guide 
    WHERE package_id = ?";

    $stmt_guides = $conn->prepare($sql_guides);
    $stmt_guides->bind_param("i", $productId);
    $stmt_guides->execute();
    $result_guides = $stmt_guides->get_result();

    while ($row = $result_guides->fetch_assoc()) {
        $guides[$row['guide_type']] = $row['guide_description'];
    }
    $stmt_guides->close();
}

// 공통 숙소 정보 조회 (package_accommodations 테이블 우선, 없으면 packages 테이블 폴백)
$commonAccommodations = [];
if ($productFound) {
    try {
        $accomStmt = $conn->prepare("
            SELECT id, sort_order, accommodation_name, accommodation_address,
                   accommodation_description, accommodation_image
            FROM package_accommodations
            WHERE package_id = ?
            ORDER BY sort_order ASC
        ");
        if ($accomStmt) {
            $accomStmt->bind_param('i', $productId);
            $accomStmt->execute();
            $accomResult = $accomStmt->get_result();
            while ($row = $accomResult->fetch_assoc()) {
                $commonAccommodations[] = [
                    'id' => $row['id'],
                    'sortOrder' => $row['sort_order'],
                    'name' => $row['accommodation_name'] ?? '',
                    'address' => $row['accommodation_address'] ?? '',
                    'description' => $row['accommodation_description'] ?? '',
                    'image' => $row['accommodation_image'] ?? ''
                ];
            }
            $accomStmt->close();
        }
    } catch (Exception $e) {
        // 테이블이 없을 수 있음
    }

    // 다중 숙소가 없으면 packages 테이블의 common_ 필드로 폴백
    if (empty($commonAccommodations)) {
        $oldName = $product['common_accommodation_name'] ?? '';
        $oldAddr = $product['common_accommodation_address'] ?? '';
        $oldDesc = $product['common_accommodation_description'] ?? '';
        $oldImg = $product['common_accommodation_image'] ?? '';
        if ($oldName || $oldAddr || $oldDesc || $oldImg) {
            $commonAccommodations[] = [
                'id' => 0,
                'sortOrder' => 0,
                'name' => $oldName,
                'address' => $oldAddr,
                'description' => $oldDesc,
                'image' => $oldImg
            ];
        }
    }
}

// 공통 교통 정보
$commonTransportation = $product['common_transportation_description'] ?? '';

// 상품 이미지 처리
function normalize_product_image_src($raw): string {
    $s = trim((string)$raw);
    if ($s === '') return '';
    $s = str_replace('\\', '/', $s);
    // absolute
    if (preg_match('/^https?:\/\//i', $s)) return $s;
    if (str_starts_with($s, '/')) return $s;
    // legacy
    if (str_starts_with($s, 'uploads/')) return '/' . $s;
    if (str_starts_with($s, 'products/')) return '/uploads/' . $s;
    // built-in image assets saved in DB like "@img_..."
    if (str_starts_with($s, '@')) return '/images/' . $s;
    // filename only (most uploaded product images)
    if (!str_contains($s, '/')) return '/uploads/products/' . $s;
    return $s;
}

$productImages = [];
// 1) thumbnail_image 우선
if (!empty($product['thumbnail_image'])) {
    $productImages[] = normalize_product_image_src($product['thumbnail_image']);
}

// 2) product_images (JSON 배열 또는 언어 객체) - DB 값 그대로 사용
if (!empty($product['product_images'])) {
    $decodedImages = json_decode((string)$product['product_images'], true);
    if (is_array($decodedImages)) {
        // {en:[], tl:[]} 또는 {en:'', tl:''} 케이스도 지원
        $keys = array_keys($decodedImages);
        $isAssoc = ($keys !== range(0, count($keys) - 1));
        if ($isAssoc) {
            $lang = in_array($currentLang, ['en', 'tl'], true) ? $currentLang : 'en';
            $pick = $decodedImages[$lang] ?? ($decodedImages['en'] ?? null);
            if (is_string($pick) && $pick !== '') {
                $productImages[] = normalize_product_image_src($pick);
            } elseif (is_array($pick)) {
                foreach ($pick as $img) {
                    if (!is_string($img) || trim($img) === '') continue;
                    $productImages[] = normalize_product_image_src($img);
                }
            }
        } else {
            foreach ($decodedImages as $img) {
                if (!is_string($img) || trim($img) === '') continue;
                $productImages[] = normalize_product_image_src($img);
            }
        }
    } elseif (is_string($decodedImages) && trim($decodedImages) !== '') {
        $productImages[] = normalize_product_image_src($decodedImages);
    } elseif (is_string($product['product_images']) && trim((string)$product['product_images']) !== '') {
        // JSON 파싱 실패했지만 문자열로 저장된 케이스
        $productImages[] = normalize_product_image_src($product['product_images']);
    }
}

// 3) 레거시/보조 이미지 컬럼들 (존재할 때만)
if (!empty($product['packageImageUrl'])) $productImages[] = normalize_product_image_src($product['packageImageUrl']);
if (!empty($product['packageImage'])) $productImages[] = normalize_product_image_src($product['packageImage']);
// detail_image는 상품 소개 이미지로, 상단 슬라이더에서 제외 (Thumbnail + Product images만 표시)

// 중복 제거(순서 유지)
if (!empty($productImages)) {
    $seen = [];
    $uniq = [];
    foreach ($productImages as $img) {
        if (!is_string($img) || trim($img) === '') continue;
        if (isset($seen[$img])) continue;
        $seen[$img] = true;
        $uniq[] = $img;
    }
    $productImages = $uniq;
}

// 카테고리 표시: 카테고리 관리 테이블(product_main/sub_categories)의 name을 우선 사용
$mainCategory = getCategoryName($product['packageCategory'], $currentLang);
$subCategory = getSubCategoryName($product['subCategory'], $currentLang, $product['packageCategory']);

// 미팅 시간 다국어 포맷팅
$meetingTime = '';
if (!empty($product['meeting_time'])) {
    $meetingTime = formatDate($product['meeting_time'], 'Y-m-d(D) H:i', $currentLang);
}

// 항공편 시간 다국어 포맷팅 함수
// - package_flights의 departure_time/arrival_time은 환경에 따라 TIME 또는 DATETIME 문자열일 수 있음
// - TIME만 있는 경우, 페이지의 departureDate(선택된 출발일)가 있으면 날짜를 결합해 표기한다.
function formatFlightTime($time, $lang = null, $fallbackDate = null) {
    if (empty($time)) return '';
    if ($lang === null) $lang = getCurrentLanguage();
    $t = trim((string)$time);
    $fb = $fallbackDate ? trim((string)$fallbackDate) : '';

    // HH:MM(:SS) 형태면 날짜 결합 (가능할 때)
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) {
        if ($fb !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fb)) {
            $t = $fb . ' ' . $t;
        } else {
            // 날짜가 없으면 시간만 출력
            return substr($t, 0, 5);
        }
    }
    return formatDate($t, 'm.d(D) H:i', $lang);
}

// HTML 태그 제거 및 줄바꿈 유지 (WYSIWYG 에디터에서 저장된 콘텐츠 정리용)
function cleanHtmlToText($html) {
    if (empty($html)) return '';
    $text = (string)$html;
    // <br> 태그를 줄바꿈으로 변환
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    // </p> 태그를 줄바꿈으로 변환
    $text = preg_replace('/<\/p>/i', "\n", $text);
    // 나머지 HTML 태그 제거
    $text = strip_tags($text);
    // HTML 엔티티 디코드
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    // 연속 줄바꿈 정리 (3개 이상 -> 2개)
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

// 소요시간(종료-시작) 포맷: 요구사항 "Duration"은 시간 범위가 아니라 소요시간이어야 함
function formatDuration($startTime, $endTime, $lang = null) {
    $s = trim((string)$startTime);
    $e = trim((string)$endTime);
    if ($s === '' || $e === '') return '';
    // HH:MM(:SS)
    $toMin = function ($v) {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) return null;
        return ((int)$m[1]) * 60 + ((int)$m[2]);
    };
    $a = $toMin($s);
    $b = $toMin($e);
    if ($a === null || $b === null) return '';
    $diff = max(0, $b - $a);
    $h = intdiv($diff, 60);
    $m = $diff % 60;
    if ($lang === null) $lang = getCurrentLanguage();
    $isKo = (string)$lang === 'ko';
    if ($isKo) {
        if ($h <= 0) return $m . '분';
        if ($m === 0) return $h . '시간';
        return $h . '시간 ' . $m . '분';
    }
    if ($h <= 0) return $m . 'm';
    if ($m === 0) return $h . 'h';
    return $h . 'h ' . $m . 'm';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['packageName']); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($product['packageName']); ?> - <?php echoI18nText('smart_travel', $currentLang); ?> <?php echoI18nText('product_detail', $currentLang); ?> <?php echoI18nText('booking', $currentLang); ?>">
    <link rel="stylesheet" href="../css/main.css?v=20260102">
    <script src="../js/button.js" defer></script>
    <script src="../js/tab.js" defer></script>
    <script src="../js/calendar.js" defer></script>
    <!-- slider -->
    <link rel="stylesheet" type="text/css" href="../js/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="../js/slick/slick-theme.css"/>
    <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js" defer></script>
    <script type="text/javascript" src="//code.jquery.com/jquery-migrate-1.2.1.min.js" defer></script>
    <script type="text/javascript" src="../js/slick/slick.min.js" defer></script>
    <script src="../js/slider.js" defer></script>
    <script src="../js/product-detail.js?v=20250204_contactagent4" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script>
        // Tab sticky shadow effect
        document.addEventListener('DOMContentLoaded', function() {
            const tabContainer = document.getElementById('tabStickyContainer');
            if (tabContainer) {
                const observer = new IntersectionObserver(
                    ([e]) => e.target.toggleAttribute('stuck', e.intersectionRatio < 1),
                    { threshold: [1] }
                );
                observer.observe(tabContainer);

                // Add shadow when stuck
                const checkSticky = () => {
                    const rect = tabContainer.getBoundingClientRect();
                    if (rect.top <= 0) {
                        tabContainer.classList.add('is-sticky');
                    } else {
                        tabContainer.classList.remove('is-sticky');
                    }
                };
                window.addEventListener('scroll', checkSticky);
                checkSticky();
            }
        });
    </script>
    <script>
        // 페이지 로드 시 localStorage에서 언어 설정을 읽어와서 URL에 적용
        document.addEventListener('DOMContentLoaded', function() {
            // NOTE: ko는 미지원. en/tl만 허용.
            let savedLanguage = localStorage.getItem('selectedLanguage') || 'en';
            if (savedLanguage !== 'en' && savedLanguage !== 'tl') savedLanguage = 'en';
            const urlParams = new URLSearchParams(window.location.search);
            const urlLang = urlParams.get('lang');

            // URL에 lang 파라미터가 없거나 localStorage의 언어와 다르면 localStorage 우선
            if (!urlLang || (urlLang !== 'en' && urlLang !== 'tl') || urlLang !== savedLanguage) {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('lang', savedLanguage);
                window.history.replaceState({}, '', currentUrl.toString());

                // 페이지 새로고침하여 언어 적용 - use location.replace to avoid adding history entry
                window.location.replace(currentUrl.toString());
            }
        });
    </script>
    <!-- SMT 수정 시작 -->
    <script>
        window.smartTravelProduct = {
            packageId: <?php echo (int)$product['packageId']; ?>,
            bookingStatusApi: '/backend/api/booking_status.php' 
        };
    </script>
    <script>
        // JS에서 예약 버튼 노출 여부 제어에 사용
        window.smartTravelUser = {
            isB2B: <?php echo $isB2B ? 'true' : 'false'; ?>,
            lang: <?php echo json_encode($currentLang, JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <!-- SMT 수정 종료 -->
</head>
<body>
    <!-- Skip Navigation -->
    
    <div class="main" style="padding-bottom: 80px" id="main-content" role="main">
        <!-- Section 1: 뒤로가기 버튼 -->
        <header class="header-type2" style="position: absolute; top: 0; left: 0; right: 0; z-index: 10; background: transparent;">
            <!-- <a class="btn-back" href="javascript:history.back();" style="background: rgba(0,0,0,0.3); border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                </a> -->
                <img src="../images/ico_back_black.svg" id="productBackButton" style="cursor: pointer;" alt="">
            <!-- 요구사항(id 48): 타이틀 영역은 대분류에 따라 변경되지 않고 'Package product'로 고정 -->
            <div class="title">Package product</div>
            <div></div>
        </header>
        
        <!-- Section 2: Product Top Area -->
        <?php if (!empty($productImages)): ?>
        <div class="slider-wrap type2" role="region" aria-label="<?php echoI18nText('slider_label', $currentLang); ?>" style="position: relative; border-radius: 8px; overflow: hidden;">
           <div class="slider">
                <?php $imageCount = count($productImages); ?>
                <?php foreach ($productImages as $index => $imageSrc): ?>
                    <div>
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echoI18nText('product_images', $currentLang); ?>" loading="lazy" style="width: 100%; height: auto; display: block;">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Product Code Overlay -->
            <div id="productCodeOverlay" class="product-code-overlay" style="position: absolute; bottom: 12px; left: 12px; background: rgba(46,46,46,0.6); color: #f3f3f3; padding: 1px 10px 3px 10px; border-radius: 100px; font-size: 12px; font-weight: 500; line-height: 16px; letter-spacing: 0.2px; z-index: 5;">
                <?php echo htmlspecialchars($productCode); ?>
            </div>
            <div id="imageCounterOverlay" class="image-counter-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(46,46,46,0.6); color: #f3f3f3; padding: 1px 10px 3px 10px; border-radius: 100px; font-size: 12px; font-weight: 500; line-height: 16px; letter-spacing: 0.2px; z-index: 5;">
                <span id="imageCounterCurrent">1</span>/<span id="imageCounterTotal"><?php echo (int)$imageCount; ?></span>
            </div>
       </div>
       <?php endif; ?>
       <div class="px20 pb24 mt16 border-bottom10">
           <ul class="breadcrumbs-type1">
                <li><?php echo $mainCategory; ?></li>
                <li><?php echo $subCategory; ?></li>
           </ul>
           <h3 class="text fz20 fw600 lh28 black12"><?php echo htmlspecialchars($product['packageName']); ?></h3>
           <div class="label secondary mt16"><?php echoI18nText('departure_confirmed', $currentLang); ?></div>
       </div>
       <div class="px20 mt32 pb24 border-bottom10">
            <div class="text fz16 fw600 lh24 black12"><?php echoI18nText('available_booking', $currentLang); ?></div>
            <div class="calendar-type2-wrap mt12">
                <div class="align both vm mb12">
                    <div class="text fz16 fw600 lh24 black12" id="calendar-month" aria-live="polite">April 2025</div>
                    <div class="align gap10">
                        <button type="button" aria-label="<?php echoI18nText('previous_month', $currentLang); ?>" class="btn-prev-month">
                            <img src="../images/ico_arrow_round_left.svg" alt="">
                        </button>
                        <button type="button" aria-label="<?php echoI18nText('next_month', $currentLang); ?>" class="btn-next-month">
                            <img src="../images/ico_arrow_round_right.svg" alt="">
                        </button>
                    </div>
                </div>

                <table class="calendar" role="grid" aria-labelledby="calendar-month">
                    <caption class="sr-only">
                      <?php echoI18nText('select_date', $currentLang); ?>
                    </caption>
                    <colgroup>
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                      <col style="width: 14.2%">
                    </colgroup>
                    <thead>
                      <tr>
                        <th><?php echo getDayName('SUN', $currentLang); ?></th>
                        <th><?php echo getDayName('MON', $currentLang); ?></th>
                        <th><?php echo getDayName('TUE', $currentLang); ?></th>
                        <th><?php echo getDayName('WED', $currentLang); ?></th>
                        <th><?php echo getDayName('THU', $currentLang); ?></th>
                        <th><?php echo getDayName('FRI', $currentLang); ?></th>
                        <th><?php echo getDayName('SAT', $currentLang); ?></th>
                      </tr>
                    </thead>
                    <tbody id="calendar-body">
                      <!-- 캘린더는 JavaScript에서 동적으로 생성됩니다 -->
                    </tbody>
                  </table>
            </div>
            <div class="mt12">
                <div class="text fz14 fw400 lh22 black12 align gap8">
                <!-- SMT 수정 시작 -->
                    <span class="text fz14 fw400 lh22 gray6b ico-mem-gray">
                        <?php echoI18nText('booking', $currentLang); ?>
                    </span>
                    <span id="current-booked-seats">
                        <?php echo $product['bookedSeats']; ?>
                    </span>
                    /
                    <span id="current-max-participants">
                        <?php echo $product['maxParticipants']; ?>
                    </span>
                    (<?php echoI18nText('min_departure', $currentLang); ?>:
                        <span id="current-min-participants">
                            <?php echo $product['minParticipants']; ?>
                        </span><?php echoI18nText('people', $currentLang); ?>)
                </div>
            </div>            
                <!-- SMT 수정 종료 -->

                    <!--  <div class="text fz14 fw400 lh22 black12 align gap8">  -->
                        <!-- <span class="text fz14 fw400 lh22 gray6b ico-mem-gray"><?php echoI18nText('booking', $currentLang); ?></span> -->
                    <!-- 4/<?php echo $product['maxParticipants']; ?> (<?php echoI18nText('min_departure', $currentLang); ?>: <?php echo $product['minParticipants']; ?><?php echoI18nText('people', $currentLang); ?>) -->
                <!-- </div>  -->

                <?php if (isset($flights['departure'])): ?>
                <div class="text fz14 fw400 lh22 black12 align gap8 mt4">
                    <span class="text fz14 fw400 lh22 gray6b ico-airplane-gray"><?php echoI18nText('departure_date', $currentLang); ?></span>
                    <?php echo formatFlightTime($flights['departure']['departure_time'], $currentLang, $departureDate); ?>
                    · <?php echo htmlspecialchars($flights['departure']['flight_number']); ?>
                    <?php if (!empty($flights['departure']['departure_point']) || !empty($flights['departure']['destination'])): ?>
                        (<?php echo htmlspecialchars(trim(($flights['departure']['departure_point'] ?? '') . ' → ' . ($flights['departure']['destination'] ?? ''))); ?>)
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (isset($flights['return'])): ?>
                <div class="text fz14 fw400 lh22 black12 align gap8 mt4 ml20">
                    <span class="text fz14 fw400 lh22 gray6b "><?php echoI18nText('return_date', $currentLang); ?></span>
                    <?php echo formatFlightTime($flights['return']['departure_time'], $currentLang, $departureDate); ?>
                    · <?php echo htmlspecialchars($flights['return']['flight_number']); ?>
                    <?php if (!empty($flights['return']['departure_point']) || !empty($flights['return']['destination'])): ?>
                        (<?php echo htmlspecialchars(trim(($flights['return']['departure_point'] ?? '') . ' → ' . ($flights['return']['destination'] ?? ''))); ?>)
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
       </div>
       <div class="border-bottomb0 tab-sticky-container" id="tabStickyContainer">
           <div class="tab-scroll-wrapper scroll-x2">
                <ul class="tab-type2 gap16">
                    <li><a class="btn-tab2 active" href="#product_intro"><?php echoI18nText('product_intro', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#schedule"><?php echoI18nText('schedule', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#inclusive"><?php echoI18nText('inclusive_exclusive', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#use_guide"><?php echoI18nText('usage_guide', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#cancellation_refund"><?php echoI18nText('cancellation_refund', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#visa_application"><?php echoI18nText('visa_application', $currentLang); ?></a></li>
                    <li><a class="btn-tab2" href="#terms_conditions">Terms and Conditions</a></li>
                </ul>
           </div>
       </div>
       <div class="px20" id="product_intro">
            <div class="text fz16 fw600 lh24 black12 mt36"><?php echoI18nText('product_intro', $currentLang); ?></div>
        </div>
        <div class="mt8 img-details" >
            <?php if (!empty($product['detail_image'])): ?>
                <img class="w100" src="<?php echo htmlspecialchars(normalize_product_image_src($product['detail_image'])); ?>" alt="<?php echoI18nText('product_images', $currentLang); ?>">
            <?php endif; ?>
            <div class="btn-wrap"><button class="btn-product btn line lg align vm center gap4" type="button" aria-expanded="false" aria-controls="product-description"><?php echoI18nText('expand_intro', $currentLang); ?><img src="../images/ico_arrow_down_black.svg" alt=""></button></div>
        </div>
        
        <!-- 상품 소개 펼치기 영역 -->
        <div id="product-description" class="px20 pb20 border-bottomea" style="display: none;">
            <?php if (!empty($product['packageDescription'])): ?>
            <div class="text fz14 fw400 lh22 black12 mt16"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($product['packageDescription']))); ?></div>
            <?php else: ?>
            <div class="text fz14 fw400 lh22 black12 mt16"><?php echoI18nText('no_intro', $currentLang); ?></div>
            <?php endif; ?>
        </div>
       <div class="px20 pb36 border-bottomea" id="schedule">
            <div class="text fz16 fw600 lh24 black12 mt36"><?php echoI18nText('schedule', $currentLang); ?></div>
            <div class="card-type6 mt20">
                <div class="text fz16 fw600 lh24 black12"><?php echoI18nText('meeting_info', $currentLang); ?></div>
                <ul>
                    <?php if (!empty($product['meeting_time'])): ?>
                    <li class="text fz14 fw400 lh22 black12">
                        <span><?php echoI18nText('time', $currentLang); ?></span>
                        <?php echo $meetingTime; ?>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($product['meeting_location'])): ?>
                    <li class="text fz14 fw400 lh22 black12 mt4">
                        <span><?php echoI18nText('location', $currentLang); ?></span>
                        <?php echo htmlspecialchars($product['meeting_location']); ?>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($product['meeting_address'])): ?>
                    <li class="text fz14 fw400 lh22 black12 mt4">
                        <span><?php echoI18nText('address', $currentLang); ?></span>
                        <?php echo htmlspecialchars($product['meeting_address']); ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <ul class="list-type6 mt20">
                <?php foreach ($schedules as $schedule): ?>
                <li>
                    <a href="#none" class="align both vm btn-folding">
                        <span class="text fz14 fw600 lh22 reded"><?php echo $schedule['day_number']; ?><?php echoI18nText('day', $currentLang); ?></span>
                        <div class="text fz14 fw600 lh22 black12"><?php echo htmlspecialchars(cleanHtmlToText($schedule['description'])); ?></div>
                        <img src="../images/ico_arrow_down_black.svg" alt="">
                    </a>
                    <div class="card-wrap mt16">
                        <?php if (!empty($schedule['attractions']) && is_array($schedule['attractions'])): ?>
                            <?php foreach ($schedule['attractions'] as $att): ?>
                                <div class="attraction-card" style="display: flex; gap: 2px; margin-bottom: 0;">
                                    <!-- Left: Icon + Line -->
                                    <div style="display: flex; flex-direction: column; align-items: center; flex-shrink: 0;">
                                        <img src="../images/ico_location_black.svg" alt="" style="width: 24px; height: 24px;">
                                        <div style="flex: 1; width: 1px; background: #EAEAEA; min-height: 20px;"></div>
                                    </div>
                                    <!-- Right: Content -->
                                    <div style="flex: 1; padding-bottom: 20px; display: flex; flex-direction: column; gap: 10px;">
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php if (!empty($att['attraction_name'])): ?>
                                            <div class="text fz14 fw600 lh22 black12"><?php echo htmlspecialchars($att['attraction_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($att['attraction_address'])): ?>
                                            <div class="text fz14 fw500 lh22" style="color: #B0B0B0;"><?php echo htmlspecialchars($att['attraction_address']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($att['attraction_image'])): ?>
                                        <img style="width: 100%; height: auto; border-radius: 8px;" src="../uploads/products/<?php echo htmlspecialchars($att['attraction_image']); ?>" alt="<?php echoI18nText('product_images', $currentLang); ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($att['attraction_description'])): ?>
                                        <div class="text fz12 fw500 lh16 black12" style="letter-spacing: 0.2px;"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($att['attraction_description']))); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($att['start_time']) && !empty($att['end_time'])): ?>
                                        <div style="display: flex; align-items: center; gap: 2px;">
                                            <img src="../images/ico_time_red.svg" alt="" style="width: 18px; height: 18px;">
                                            <span class="text fz13 fw400 lh19 reded"><?php echo htmlspecialchars(formatDuration($att['start_time'], $att['end_time'], $currentLang)); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($schedule['airport_description']) || !empty($schedule['airport_location'])): ?>
                            <div class="attraction-card" style="display: flex; gap: 2px; margin-bottom: 0;">
                                <!-- Left: Icon + Line -->
                                <div style="display: flex; flex-direction: column; align-items: center; flex-shrink: 0;">
                                    <img src="../images/ico_location_black.svg" alt="" style="width: 24px; height: 24px;">
                                    <div style="flex: 1; width: 1px; background: #EAEAEA; min-height: 20px;"></div>
                                </div>
                                <!-- Right: Content -->
                                <div style="flex: 1; padding-bottom: 20px; display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <?php if (!empty($schedule['airport_location'])): ?>
                                        <div class="text fz14 fw600 lh22 black12"><?php echo htmlspecialchars($schedule['airport_location']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($schedule['airport_address'])): ?>
                                        <div class="text fz14 fw500 lh22" style="color: #B0B0B0;"><?php echo htmlspecialchars($schedule['airport_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($schedule['airport_image'])): ?>
                                    <img style="width: 100%; height: 193px; object-fit: cover; border-radius: 8px;" src="../uploads/products/<?php echo htmlspecialchars($schedule['airport_image']); ?>" alt="<?php echoI18nText('airport', $currentLang); ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($schedule['airport_description'])): ?>
                                    <div class="text fz12 fw500 lh16 black12" style="letter-spacing: 0.2px;"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($schedule['airport_description']))); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($schedule['start_time']) && !empty($schedule['end_time'])): ?>
                                    <div style="display: flex; align-items: center; gap: 2px;">
                                        <img src="../images/ico_time_red.svg" alt="" style="width: 18px; height: 18px;">
                                        <span class="text fz13 fw400 lh19 reded"><?php echo htmlspecialchars(formatDuration($schedule['start_time'], $schedule['end_time'], $currentLang)); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="px12">
                            <?php if (!empty($schedule['breakfast']) || !empty($schedule['lunch']) || !empty($schedule['dinner'])): ?>
                            <div class="card-type7 mt10">
                                <div class="title ico3"><?php echoI18nText('meals', $currentLang); ?></div>
                                <div class="pt10">
                                    <?php if (!empty($schedule['breakfast'])): ?>
                                    <div class="text fz14 fw500 lh22 black12 align gap8">
                                        <span class="text fz14 fw500 lh22 gray6b"><?php echoI18nText('breakfast', $currentLang); ?></span>
                                        <?php echo nl2br(htmlspecialchars(cleanHtmlToText($schedule['breakfast']))); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($schedule['lunch'])): ?>
                                    <div class="text fz14 fw500 lh22 black12 mt8 align gap8">
                                        <span class="text fz14 fw500 lh22 gray6b"><?php echoI18nText('lunch', $currentLang); ?></span>
                                        <?php echo nl2br(htmlspecialchars(cleanHtmlToText($schedule['lunch']))); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($schedule['dinner'])): ?>
                                    <div class="text fz14 fw500 lh22 black12 mt8 align gap8">
                                        <span class="text fz14 fw500 lh22 gray6b"><?php echoI18nText('dinner', $currentLang); ?></span>
                                        <?php echo nl2br(htmlspecialchars(cleanHtmlToText($schedule['dinner']))); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (!empty($commonAccommodations) || !empty($commonTransportation)): ?>
        <div class="px20 pb20 border-bottomea" id="common_info">
            <div class="text fz16 fw600 lh24 black12 mt36"><?php echoI18nText('common_info', $currentLang); ?></div>
            <?php if (!empty($commonAccommodations)): ?>
            <div class="mt20">
                <div class="text fz14 fw600 lh22 black12"><?php echoI18nText('accommodation', $currentLang); ?></div>
                <?php foreach ($commonAccommodations as $accom): ?>
                <div class="card-type7 mt8">
                    <div class="pt10">
                        <?php if (!empty($accom['image'])): ?>
                        <img style="width: 152px; height: auto; border-radius: 4px;" src="../uploads/products/<?php echo htmlspecialchars($accom['image']); ?>" alt="<?php echoI18nText('accommodation', $currentLang); ?>">
                        <?php endif; ?>
                        <?php if (!empty($accom['name'])): ?>
                        <div class="text fz14 fw600 lh22 black12 mt8"><?php echo htmlspecialchars($accom['name']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($accom['address'])): ?>
                        <div class="text fz12 fw500 lh16 grayb0"><?php echo htmlspecialchars($accom['address']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($accom['description'])): ?>
                        <div class="text fz12 fw500 lh16 black12 mt8"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($accom['description']))); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($commonTransportation)): ?>
            <div class="mt20">
                <div class="text fz14 fw600 lh22 black12"><?php echoI18nText('transportation', $currentLang); ?></div>
                <div class="card-type7 mt8">
                    <div class="pt10">
                        <div class="text fz14 fw500 lh22 black12"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($commonTransportation))); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="px20 pb20 border-bottomea" id="inclusive">
            <div class="text fz14 fw600 lh24 black12 mt36"><?php echoI18nText('inclusive_exclusive', $currentLang); ?></div>
            <?php if (!empty($product['included_items'])): ?>
            <div class="text fz14 fw500 lh22 black12 mt20"><?php echoI18nText('included', $currentLang); ?></div>
            <?php 
                $included_lines = explode("\n", $product['included_items']);
                foreach ($included_lines as $index => $line):
                    $line = trim($line);
                    if (!empty($line)):
            ?>
            <div class="text fz14 fw500 lh22 black12 ico1 <?php echo $index === 0 ? 'mt4' : 'mt8'; ?>"><?php echo htmlspecialchars($line); ?></div>
            <?php 
                    endif;
                endforeach;
            ?>
            <?php endif; ?>
            <?php if (!empty($product['excluded_items'])): ?>
            <div class="text fz14 fw500 lh22 black12 mt12"><?php echoI18nText('excluded', $currentLang); ?></div>
            <?php 
                $excluded_lines = explode("\n", $product['excluded_items']);
                foreach ($excluded_lines as $index => $line):
                    $line = trim($line);
                    if (!empty($line)):
            ?>
            <div class="text fz14 fw500 lh22 black12 ico2 <?php echo $index === 0 ? 'mt4' : 'mt8'; ?>"><?php echo htmlspecialchars($line); ?></div>
            <?php 
                    endif;
                endforeach;
            ?>
            <?php endif; ?>
        </div>
        <div class="px20 pb20 border-bottomea" id="use_guide">
            <div class="text fz14 fw600 lh24 black12 mt36"><?php echoI18nText('usage_guide', $currentLang); ?></div>
            <?php if (!empty($guides['usage'])): ?>
            <div class="text fz14 fw400 lh22 black12 mt20"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($guides['usage']))); ?></div>
            <?php else: ?>
            <div class="text fz14 fw400 lh22 black12 mt20"><?php echoI18nText('default_usage_guide', $currentLang); ?></div>
            <?php endif; ?>
            <?php if (!empty($product['usage_guide_file'])): ?>
                <a class="btn line lg active ico2 mt16" href="../uploads/usage_guides/<?php echo htmlspecialchars($product['usage_guide_file']); ?>" download="<?php echo htmlspecialchars($product['usage_guide_name'] ?? 'usage_guide.pdf'); ?>">
                    <?php echoI18nText('download_guide', $currentLang); ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="px20 pb20 border-bottomea" id="cancellation_refund">
            <div class="text fz14 fw600 lh24 black12 mt36"><?php echoI18nText('cancellation_refund', $currentLang); ?></div>
            <?php if (!empty($guides['cancellation'])): ?>
            <div class="text fz14 fw400 lh22 black12 mt12"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($guides['cancellation']))); ?></div>
            <?php else: ?>
            <ul>
                <li class="text fz14 fw400 lh22 black12">• Before 15 days of departure: 100% tour fare refund(No cancellation charge)</li>
                <li class="text fz14 fw400 lh22 black12">• Before 8-14 days of departure: 50% tour fare refund(50% cancellation charge)</li>
                <li class="text fz14 fw400 lh22 black12">• Before 4-7 days of departure: 30% tour fare refund(70% cancellation charge)</li>
                <li class="text fz14 fw400 lh22 black12">• Before 1-3 days of departure: 0% tour fare refund(100% cancellation charge)</li>
            </ul>
            <?php endif; ?>
        </div>
        <div class="px20 pb20" id="visa_application">
            <div class="text fz14 fw600 lh24 black12 mt36"><?php echoI18nText('visa_application', $currentLang); ?></div>
            <?php if (!empty($guides['visa'])): ?>
            <div class="text fz14 fw400 lh22 black12 mt20"><?php echo nl2br(htmlspecialchars(cleanHtmlToText($guides['visa']))); ?></div>
            <?php else: ?>
            <div class="text fz14 fw400 lh22 black12 mt20"><?php echoI18nText('default_visa_guide', $currentLang); ?></div>
            <?php endif; ?>
        </div>
        <div class="px20 pb20 border-bottomea" id="terms_conditions">
            <div class="text fz14 fw600 lh24 black12 mt36">Terms and Conditions</div>
            <div class="text fz14 fw400 lh22 black12 mt20">
                <p>By confirming a booking with SMT Escape, the client agrees to the following:</p>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">BOOKING AND PAYMENTS</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Bookings are final and non-cancellable. Full payment is forfeited if canceled.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Unused services, including accommodations, tours, meals, and transfers, are non-refundable.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Any unauthorized changes to bookings or travel documents render the booking null and void.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">FLIGHTS, CHANGES &amp; CANCELLATIONS</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Travel Escape is not liable for any missed flights, transfers, tours, or entrance passes due to the client's actions, delays, or failure to comply with schedules.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Flight changes and cancellations made by the airline are subject to applicable flexibility options offered by the airline, such as rebooking within 30 days or travel fund.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Land arrangements such as hotels, transportation, entrance fees, tour guide, meals, and other services in the land are not refundable and not rebookable due to flight changes or cancellations.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">TOURS, TRANSFERS &amp; ITINERARY CHANGES</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- All transfers and tours are seat-in-coach unless otherwise specified.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Itineraries, schedules, transport, or accommodations might change due to operational requirements, weather, or other circumstances beyond its control.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- All incidental costs, penalties, or additional expenses incurred during travel are the client's responsibility.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">FORCE MAJEURE</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Travel Escape shall not be held liable for any delays, cancellations, or other issues arising from events outside of its control, such as natural disasters, political unrest, or strikes.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">TRAVEL DOCUMENTS &amp; IMMIGRATION</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Clients must carry a valid passport with at least six (6) months validity from the date of travel, along with visas (if applicable) and other required travel or income documents.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Clients are responsible for the necessary documents needed for their travel. If they need assistance, we can help obtaining those documents.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Travel Escape is not responsible for decisions made by immigration authorities; all payments are non-refundable if denied boarding or entry.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">SAFETY, HEALTH &amp; INSURANCE</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Travel Escape cannot guarantee the safety of its clients, and it is the client's responsibility to take appropriate precautions to protect their health and safety during the trip.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- The client is strongly encouraged to purchase travel insurance to cover any unexpected events, such as trip cancellations, medical emergencies, or lost luggage.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">LIABILITY</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Travel Escape shall not be held liable for any loss, injury, or damage to clients or their property during the trip, unless caused by the company's proven negligence.</div>
                        <div class="text fz14 fw400 lh22 black12 mt4">- Travel Escape is not liable for actions or omissions of third-party providers and is indemnified from related claims.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">INTELLECTUAL PROPERTY</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Clients agree to respect the intellectual property of Travel Escape and its partners, and not use any of the company materials or trademarks without permission.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">GOVERNMENT LAW &amp; DISPUTES</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- Any disputes arising from the agreement should be resolved in accordance with the laws of the country where the travel company is located.</div>
                    </div>
                </div>

                <div class="mt16">
                    <div class="text fz14 fw600 lh22 black12">PRIVACY</div>
                    <div class="mt4">
                        <div class="text fz14 fw400 lh22 black12">- The client's personal information will be collected, processed, and used in accordance with Travel Escape's Privacy Policy.</div>
                    </div>
                </div>
            </div>
            <div class="card-type px16 py14 align both vm mt60" style="margin-bottom: 80px;">
                <div class="align gap8 vm">
                    <img src="../images/ico_inquiry_red.svg" alt="">
                    <div class="text fz14 fw500 lh20 black12"><?php echoI18nText('inquiry_question', $currentLang); ?></div>
                </div>
                <a href="inquiry.php?lang=<?php echo urlencode($currentLang); ?>&returnUrl=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? ('/user/product-detail.php?id=' . (int)$productId)); ?>" class="text fz14 fw500 lh20 reded"><?php echoI18nText('customer_support', $currentLang); ?> <img src="../images/ico_arrow_right_red.svg" alt=""></a>
            </div>
        </div>

    </div>

    <!-- B2C 사용자용 Contact Agent 버튼 (탭 영역 밖에 위치) -->
    <div id="b2cContactAgentBar" class="fixed-bottom-bar b2c-contact-bar" style="position:fixed;bottom:0;left:0;right:0;background:white;border-top:1px solid #e9ecef;padding:16px 20px;z-index:1000;box-shadow:0 -2px 10px rgba(0,0,0,0.1);display:<?php echo $isB2B ? 'none' : 'block'; ?>;">
        <button class="btn primary lg w100" type="button" onclick="goToContactAgent()">
            Contact Agent
        </button>
    </div>
</body>
</html>
