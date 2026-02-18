<?php
require_once '../backend/i18n_helper.php';
require_once '../backend/conn.php';

// 현재 언어 설정
$currentLang = getCurrentLanguage();

// URL 파라미터에서 예약 ID 가져오기
$bookingId = $_GET['id'] ?? $_GET['bookingId'] ?? $_GET['booking_id'] ?? '';

// bookingId가 'undefined' 문자열인 경우 처리
if ($bookingId === 'undefined' || $bookingId === '') {
    $bookingId = '';
}

// 디버깅을 위한 로그
error_log("Reservation Detail - Booking ID: " . $bookingId);

// 예약 정보를 데이터베이스에서 가져오기
$bookingInfo = null;
if ($bookingId && $bookingId !== 'undefined') {
    try {
        // 데이터베이스에서 예약 정보 조회
        $sql = "SELECT 
                    b.bookingId,
                    b.accountId,
                    b.guideId,
                    b.packageId,
                    b.packageName,
                    b.packagePrice,
                    b.departureDate,
                    b.departureTime,
                    b.adults,
                    b.children,
                    b.infants,
                    b.totalAmount,
                    b.bookingStatus,
                    b.paymentStatus,
                    b.specialRequests,
                    b.selectedOptions,
                    b.selectedRooms,
                    b.createdAt,
                    b.contactEmail,
                    b.contactPhone,
                    p.packageName as productName,
                    p.product_images as thumbnail,
                    p.packagePrice as adultPrice,
                    p.childPrice,
                    p.infantPrice,
                    a.emailAddress as accountEmail,
                    c.fName,
                    c.lName,
                    c.contactNo
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                LEFT JOIN accounts a ON b.accountId = a.accountId
                LEFT JOIN client c ON b.accountId = c.accountId
                WHERE b.bookingId = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bookingInfo = $result->fetch_assoc();
        } else {
            // 예약 정보가 없으면 기본값 사용
            $bookingInfo = [
                'bookingId' => $bookingId,
                'packageName' => 'Package Not Found',
                'packagePrice' => 0,
                'departureDate' => '',
                'departureTime' => '',
                'return_date' => '',
                'return_time' => '',
                'duration' => 'N/A',
                'adults' => 0,
                'children' => 0,
                'infants' => 0,
                'totalAmount' => 0,
                'bookingStatus' => 'unknown',
                'paymentStatus' => 'unknown',
                'specialRequests' => '',
                'createdAt' => '',
                'emailAddress' => '',
                'fName' => '',
                'lName' => '',
                'contactNo' => ''
            ];
        }
    } catch (Exception $e) {
        // 오류 발생 시 기본값 사용
        $bookingInfo = [
            'bookingId' => $bookingId,
            'packageName' => 'Error loading package info',
            'packagePrice' => 0,
            'departureDate' => '',
            'departureTime' => '',
            'return_date' => '',
            'return_time' => '',
            'duration' => 'N/A',
            'adults' => 0,
            'children' => 0,
            'infants' => 0,
            'totalAmount' => 0,
            'bookingStatus' => 'unknown',
            'paymentStatus' => 'unknown',
            'specialRequests' => '',
            'createdAt' => '',
            'emailAddress' => '',
            'fName' => '',
            'lName' => '',
            'contactNo' => ''
        ];
    }
}

// ===== 가이드 정보(배정된 경우) + "여행중" 판정 =====
$guideInfo = null;
$showGuideSection = false;
$showGuideLocationButton = false;
try {
    if (!empty($bookingInfo) && !empty($bookingInfo['bookingId'])) {
        $showGuideSection = false;
        $showGuideLocationButton = false;

        // guideId 우선: bookings.guideId, 없으면 booking_guides fallback
        $guideId = $bookingInfo['guideId'] ?? null;
        if (empty($guideId)) {
            $tbl = $conn->query("SHOW TABLES LIKE 'booking_guides'");
            if ($tbl && $tbl->num_rows > 0) {
                $gs = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? LIMIT 1");
                if ($gs) {
                    $bid = (string)$bookingInfo['bookingId'];
                    $gs->bind_param('s', $bid);
                    $gs->execute();
                    $gRes = $gs->get_result();
                    if ($gRes && $gRes->num_rows > 0) {
                        $guideId = $gRes->fetch_assoc()['guideId'] ?? null;
                    }
                    $gs->close();
                }
            }
        }

        if (!empty($guideId)) {
            $gid = (int)$guideId;
            $gstmt = $conn->prepare("SELECT guideId, guideName, profileImage, phoneNumber, introduction FROM guides WHERE guideId = ? LIMIT 1");
            if ($gstmt) {
                $gstmt->bind_param('i', $gid);
                $gstmt->execute();
                $gRow = $gstmt->get_result()->fetch_assoc();
                $gstmt->close();

                if ($gRow) {
                    $showGuideSection = true;
                    $guideInfo = [
                        'guideId' => (int)($gRow['guideId'] ?? $gid),
                        'guideName' => (string)($gRow['guideName'] ?? ''),
                        'phone' => (string)($gRow['phoneNumber'] ?? ''),
                        'about' => (string)($gRow['introduction'] ?? ''),
                        'profileImage' => !empty($gRow['profileImage'])
                            ? ('../' . ltrim((string)$gRow['profileImage'], '/'))
                            : '../images/@img_profile_square.png',
                    ];
                }
            }

            // "여행중" 판단: 예약 확정 + 오늘이 여행 기간 내
            $bookingStatus = (string)($bookingInfo['bookingStatus'] ?? '');
            $dep = (string)($bookingInfo['departureDate'] ?? '');
            $duration = 0;
            if (!empty($bookingInfo['packageId'])) {
                $pid = (int)$bookingInfo['packageId'];
                $dstmt = $conn->prepare("SELECT COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = ?), p.duration_days, 1) as duration_days FROM packages p WHERE p.packageId = ? LIMIT 1");
                if ($dstmt) {
                    $dstmt->bind_param('ii', $pid, $pid);
                    $dstmt->execute();
                    $dRow = $dstmt->get_result()->fetch_assoc();
                    $dstmt->close();
                    $duration = (int)($dRow['duration_days'] ?? 0);
                }
            }
            if ($duration <= 0) $duration = 1;

            if ($bookingStatus === 'confirmed' && $dep !== '') {
                $start = new DateTime($dep);
                $end = clone $start;
                $end->add(new DateInterval('P' . max(0, $duration - 1) . 'D'));
                $today = new DateTime(date('Y-m-d'));

                // 날짜만 비교
                $start->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                $today->setTime(12, 0, 0);

                if ($today >= $start && $today <= $end) {
                    $showGuideLocationButton = true;
                }
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

// 상태별 CSS 클래스 및 텍스트 반환 함수
function getBookingStatusInfo($status, $lang) {
    $statusMap = [
        'ko' => [
            'confirmed' => ['class' => 'primary', 'text' => '예약 완료'],
            'pending' => ['class' => 'secondary', 'text' => '예약 대기'],
            'cancelled' => ['class' => 'danger', 'text' => '예약 취소'],
            'completed' => ['class' => 'primary', 'text' => '여행 완료'],
            'default' => ['class' => 'secondary', 'text' => '확인 중']
        ],
        'en' => [
            'confirmed' => ['class' => 'primary', 'text' => 'Booking Confirmed'],
            'pending' => ['class' => 'secondary', 'text' => 'Booking Pending'],
            'cancelled' => ['class' => 'danger', 'text' => 'Booking Cancelled'],
            'completed' => ['class' => 'primary', 'text' => 'Trip Completed'],
            'default' => ['class' => 'secondary', 'text' => 'Checking']
        ],
        'tl' => [
            'confirmed' => ['class' => 'primary', 'text' => 'Nakumpirma ang Booking'],
            'pending' => ['class' => 'secondary', 'text' => 'Naghihintay ng Booking'],
            'cancelled' => ['class' => 'danger', 'text' => 'Nakansela ang Booking'],
            'completed' => ['class' => 'primary', 'text' => 'Natapos ang Trip'],
            'default' => ['class' => 'secondary', 'text' => 'Sinusuri']
        ]
    ];
    
    $langMap = $statusMap[$lang] ?? $statusMap['ko'];
    return $langMap[$status] ?? $langMap['default'];
}

// 가격 포맷팅 함수
function formatPrice($price) {
    if (is_numeric($price)) {
        return '₱' . number_format($price);
    }
    return '₱0';
}

// 날짜 포맷팅 함수는 i18n_helper.php에 이미 정의되어 있음

$statusInfo = getBookingStatusInfo($bookingInfo['bookingStatus'] ?? 'unknown', $currentLang);

// 다운로드 링크(전체 일정/안내문) 준비
$fullScheduleDownloadUrl = '';
$usageGuideDownloadUrl = '';
try {
    if (!empty($bookingInfo['bookingId'])) {
        // 전체 일정 다운로드: 백엔드 export API (기본은 Excel .xls, 필요시 format=csv)
        // Export must follow the provided Excel template
        $fullScheduleDownloadUrl = '../backend/api/schedule-export.php?booking_id=' . rawurlencode((string)$bookingInfo['bookingId']) . '&format=xlsx';
    }
    $pkgId = isset($bookingInfo['packageId']) ? (int)$bookingInfo['packageId'] : 0;
    if ($pkgId > 0) {
        $st = $conn->prepare("SELECT usage_guide_file FROM packages WHERE packageId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $pkgId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $fn = $row['usage_guide_file'] ?? '';
            if (!empty($fn)) {
                $usageGuideDownloadUrl = '../uploads/usage_guides/' . rawurlencode((string)$fn);
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('reservation_detail', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/tab.js" defer></script>
    <script src="../js/reservation-detail.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main bg white mh100">
        <header class="header-type2 bg white">
            <a class="btn-back" href="javascript:void(0);" onclick="goBack()"><img src="../images/ico_back_black.svg"></a>
            <div class="title"><?php echoI18nText('reservation_detail', $currentLang); ?></div>
            <div></div>
        </header>
        
        <div class="px20 pb20 mt18 border-bottom10">
            <?php
            // 요구사항(id 44):
            // - 예약 상세 화면의 상태 칩/상단 구성은 bookingStatus+paymentStatus 조합 기준으로 보여야 한다.
            //   Case 1: Payment Suspended
            //   Case 2: Payment Completed / Trip Completed
            //   Case 3: Payment Canceled / Refund Completed
            $bs = strtolower((string)($bookingInfo['bookingStatus'] ?? ''));
            $ps = strtolower((string)($bookingInfo['paymentStatus'] ?? ''));

            $statusKey = '';
            if (in_array($bs, ['temporary_save','temporary','draft','temp','saved','saved_draft'], true)) {
                $statusKey = 'temporary_save';
            } elseif ($bs === 'refunded' || $bs === 'refund_completed' || $ps === 'refunded') {
                $statusKey = 'refund_completed';
            } elseif ($bs === 'completed') {
                $statusKey = 'trip_completed';
            } elseif ($bs === 'cancelled' || $bs === 'canceled' || $ps === 'failed') {
                $statusKey = 'payment_canceled';
            } elseif ($bs === 'pending') {
                // B2C 고객은 직접 예약할 수 없으므로 확정 대기중으로 표시
                $statusKey = 'pending_confirmation';
            } elseif ($bs === 'confirmed') {
                // B2C 요구사항: 확정 직후 입금 전 상태는 Payment Suspended
                $statusKey = ($ps === 'paid') ? 'payment_completed' : 'payment_suspended';
            } elseif ($ps === 'paid') {
                $statusKey = 'payment_completed';
            } elseif ($ps === 'pending' || $ps === 'partial' || $ps === '' || $ps === 'null') {
                $statusKey = 'payment_suspended';
            } else {
                $statusKey = 'payment_suspended';
            }

            $labelText = '';
            $labelClass = 'secondary';
            if ($statusKey === 'payment_completed') { $labelText = 'Payment Completed'; $labelClass = 'primary'; }
            else if ($statusKey === 'trip_completed') { $labelText = 'Trip Completed'; $labelClass = 'primary'; }
            else if ($statusKey === 'refund_completed') { $labelText = 'Refund Completed'; $labelClass = 'primary'; }
            else if ($statusKey === 'payment_canceled') { $labelText = 'Reservation cancellation'; $labelClass = 'primary'; }
            else if ($statusKey === 'temporary_save') { $labelText = 'Temporary save'; $labelClass = 'secondary'; }
            else if ($statusKey === 'pending_confirmation') { $labelText = 'Pending Confirmation'; $labelClass = 'secondary'; }
            else { $labelText = 'Awaiting Payment'; $labelClass = 'primary'; }

            // 상단 상태 칩
            echo '<div class="label ' . $labelClass . '" id="bookingStatus">' . htmlspecialchars($labelText) . '</div>';

            // Case 별 상단 안내 박스
            if ($statusKey === 'temporary_save') {
                $continueText = $currentLang === 'ko' ? '예약 계속하기' : ($currentLang === 'tl' ? 'Magpatuloy sa Booking' : 'Continue Booking');
                $tempSaveDesc = $currentLang === 'ko'
                    ? '이 예약은 아직 완료되지 않았습니다. 아래 버튼을 눌러 예약을 계속 진행하세요.'
                    : ($currentLang === 'tl'
                        ? 'Hindi pa kumpleto ang booking na ito. Pindutin ang button sa ibaba para magpatuloy.'
                        : 'This booking is not yet completed. Press the button below to continue.');
                echo '<div class="mt12 p16" style="background: #FFF8E1; border-radius: 8px; border: 1px solid #FFE082;">';
                echo '<div class="text fz14 fw400 lh22 black12 mt12">' . htmlspecialchars($tempSaveDesc) . '</div>';
                echo '<a href="select-reservation.php?booking_id=' . htmlspecialchars($bookingInfo['bookingId'] ?? '') . '&lang=' . htmlspecialchars($currentLang) . '" class="btn primary lg mt12">' . htmlspecialchars($continueText) . '</a>';
                echo '</div>';
            } elseif ($statusKey === 'payment_suspended') {
                // 입금 안내 (Figma: node-id=2001-77344)
                $msg = $currentLang === 'ko'
                    ? '입금 확인 후 예약이 확정됩니다.'
                    : ($currentLang === 'tl'
                        ? 'Makukumpirma ang booking pagkatapos makumpirma ang bayad.'
                        : 'reservation will be confirmed after the payment is verified.');
                $bankInfo = $currentLang === 'ko'
                    ? 'BDO Bank 007800151678 (예금주: Jedkim)'
                    : ($currentLang === 'tl'
                        ? 'BDO Bank 007800151678 (Account holder: Jedkim)'
                        : 'BDO Bank 007800151678 (Account holder: Jedkim)');
                echo '<div class="mt12" style="background: #F5F5F5; border-radius: 8px; padding: 16px;">';
                echo '<div class="text fz14 fw400 lh22 black12">' . htmlspecialchars($msg) . '</div>';
                echo '<div class="text fz14 fw400 lh22 black12" style="margin-top: 8px;">' . htmlspecialchars($bankInfo) . '</div>';
                echo '</div>';
            } elseif ($statusKey === 'payment_canceled' || $statusKey === 'refund_completed') {
                $msg = $currentLang === 'ko'
                    ? '최종 환불에는 영업일 기준 5~7일이 소요될 수 있습니다.'
                    : ($currentLang === 'tl'
                        ? 'Ang final refund ay maaaring tumagal ng 5-7 araw ng negosyo.'
                        : 'The final refund may take 5~7 business days.');
                if ($statusKey === 'refund_completed') {
                    $msg = $currentLang === 'ko'
                        ? '환불이 완료되었습니다.'
                        : ($currentLang === 'tl'
                            ? 'Natapos na ang refund.'
                            : 'Refund completed.');
                }
                echo '<p class="text fz16 fw600 lh24 black12 mt10">' . htmlspecialchars($msg) . '</p>';
            }
            ?>
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('product_info', $currentLang); ?></div>
            <a href="<?php echo isset($bookingInfo['packageId']) && $bookingInfo['packageId'] ? 'product-detail.php?id=' . htmlspecialchars($bookingInfo['packageId']) : '#'; ?>" id="productLink" style="display: block;">
                <?php
                // 썸네일 이미지 경로 처리
                $thumbnailSrc = '../images/@img_thumbnail.png';
                if (!empty($bookingInfo['thumbnail'])) {
                    if (strpos($bookingInfo['thumbnail'], 'http') === 0) {
                        $thumbnailSrc = $bookingInfo['thumbnail'];
                    } else {
                        // product_images 배열 처리
                        $thumbnail = $bookingInfo['thumbnail'];
                        try {
                            $images = json_decode($thumbnail, true);
                            if (is_array($images) && count($images) > 0) {
                                $thumbnailSrc = '../uploads/products/' . $images[0];
                            } else {
                                $thumbnailSrc = '../uploads/products/' . $thumbnail;
                            }
                        } catch (Exception $e) {
                            $thumbnailSrc = '../uploads/products/' . $thumbnail;
                        }
                    }
                }

                // 상품명
                $pn1 = trim((string)($bookingInfo['packageName'] ?? ''));
                $pn2 = trim((string)($bookingInfo['productName'] ?? ''));
                $prodName = ($pn1 !== '') ? $pn1 : (($pn2 !== '') ? $pn2 : 'Package Not Found');

                // 가격
                $productPrice = $bookingInfo['packagePrice'] ?? $bookingInfo['adultPrice'] ?? 0;
                ?>
                <div class="product-card" style="display: flex; gap: 12px; padding: 12px 0; align-items: center; border: none; background: transparent;">
                    <!-- 썸네일 이미지 -->
                    <div style="width: 60px; height: 60px; flex-shrink: 0;">
                        <img src="<?php echo htmlspecialchars($thumbnailSrc); ?>" alt="" id="productImage" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;" onerror="this.src='../images/@img_thumbnail.png'">
                    </div>
                    <!-- 상품명 -->
                    <div style="flex: 1; min-width: 0;">
                        <div class="text fz14 fw500 lh22 black12 ellipsis2" id="productName" style="word-break: break-word;"><?php echo htmlspecialchars($prodName); ?></div>
                    </div>
                    <!-- 화살표 -->
                    <div style="flex-shrink: 0;">
                        <img src="../images/ico_arrow_right_black.svg" alt="">
                    </div>
                </div>
            </a>
            <div class="text fz14 fw600 lh22 gray96 mt12"><?php echoI18nText('trip_dates', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="tripDates">
                <?php 
                if ($bookingInfo['departureDate']) {
                    // 패키지에서 duration 가져오기 (package_schedules 우선)
                    $durationSql = "SELECT COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = ?), p.duration_days, 1) as duration_days FROM packages p WHERE p.packageId = ?";
                    $durationStmt = $conn->prepare($durationSql);
                    $pkgIdForDur = (int)$bookingInfo['packageId'];
                    $durationStmt->bind_param("ii", $pkgIdForDur, $pkgIdForDur);
                    $durationStmt->execute();
                    $durationResult = $durationStmt->get_result();
                    $duration = 5; // 기본값
                    if ($durationRow = $durationResult->fetch_assoc()) {
                        $duration = $durationRow['duration_days'] ?? 5;
                    }
                    $durationStmt->close();
                    
                    $departureDateTime = $bookingInfo['departureDate'] . ' ' . ($bookingInfo['departureTime'] ?? '12:20:00');
                    $departureDate = new DateTime($departureDateTime);
                    $endDate = clone $departureDate;
                    $endDate->add(new DateInterval('P' . ($duration - 1) . 'D'));
                    
                    $returnDateTime = $endDate->format('Y-m-d') . ' 19:05:00';
                    echo formatDate($departureDateTime, 'Y-m-d H:i', $currentLang);
                    echo ' - ';
                    echo formatDate($returnDateTime, 'Y-m-d H:i', $currentLang);
                    $nights = max(1, $duration - 1);
                    echo ' (' . $duration . 'D' . $nights . 'N)';
                } else {
                    echoI18nText('loading_trip_schedule', $currentLang);
                }
                ?>
            </div>
        </div>
        
        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('reservation_info', $currentLang); ?></div>
        </div>
        
        <div class="px20 pb20 border-bottom10">
            <div class="text fz14 fw600 lh22 gray96 mt16"><?php echoI18nText('reservation_number', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="reservationNo"><?php echo htmlspecialchars($bookingInfo['bookingId'] ?? 'N/A'); ?></div>
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('reservation_status', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="reservationStatus"><?php echo $statusInfo['text']; ?></div>
            
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('guests', $currentLang); ?></div>
            <div id="guestsInfo">
                <?php
                // selectedOptions(JSON) 파싱 (guestOptions 포함)
                $selectedOptions = [];
                if (!empty($bookingInfo['selectedOptions'])) {
                    $selectedOptions = json_decode((string)$bookingInfo['selectedOptions'], true);
                    if (!is_array($selectedOptions)) $selectedOptions = [];
                }
                // guestOptions 위치: root.guestOptions 또는 root.selectedOptions.guestOptions
                $guestOptions = $selectedOptions['guestOptions'] ?? null;
                if (!is_array($guestOptions)) {
                    $nested = $selectedOptions['selectedOptions'] ?? null;
                    if (is_array($nested) && isset($nested['guestOptions']) && is_array($nested['guestOptions'])) {
                        $guestOptions = $nested['guestOptions'];
                    } else {
                        $guestOptions = [];
                    }
                }

                // 옵션 단가가 빠진 케이스 대비: package_pricing_options에서 optionName->price 조회 (DB 기반)
                $priceMap = [];
                try {
                    $tbl = $conn->query("SHOW TABLES LIKE 'package_pricing_options'");
                    if ($tbl && $tbl->num_rows > 0 && !empty($bookingInfo['packageId'])) {
                        $pid = (int)$bookingInfo['packageId'];
                        $ps = $conn->prepare("SELECT option_name, price FROM package_pricing_options WHERE package_id = ? ORDER BY pricing_id ASC");
                        if ($ps) {
                            $ps->bind_param('i', $pid);
                            $ps->execute();
                            $res = $ps->get_result();
                            while ($r = $res->fetch_assoc()) {
                                $k = trim((string)($r['option_name'] ?? ''));
                                if ($k !== '') $priceMap[$k] = isset($r['price']) ? (float)$r['price'] : null;
                            }
                            $ps->close();
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }

                $rendered = 0;
                foreach ((array)$guestOptions as $opt) {
                    if (!is_array($opt)) continue;
                    $name = trim((string)($opt['name'] ?? ($opt['optionName'] ?? ($opt['title'] ?? ''))));
                    $qty = $opt['qty'] ?? ($opt['quantity'] ?? 0);
                    $qty = is_numeric($qty) ? (int)$qty : 0;
                    if ($name === '' || $qty <= 0) continue;

                    $unit = $opt['unitPrice'] ?? ($opt['price'] ?? ($opt['optionPrice'] ?? null));
                    $unit = is_numeric($unit) ? (float)$unit : ($priceMap[$name] ?? null);
                    $total = (is_numeric($unit) ? ((float)$unit * $qty) : 0);

                    echo '<div class="align both vm mt4">';
                    echo '<p class="text fz14 fw400 lh22 black12">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . 'x' . $qty . '</p>';
                    echo '<span class="text fz14 fw400 lh22 black12">' . formatPrice($total) . '</span>';
                    echo '</div>';
                    $rendered++;
                }

                if ($rendered === 0) {
                    // guestOptions가 없으면 bookings 테이블의 adults/children/infants로 표시
                    $adults = (int)($bookingInfo['adults'] ?? 0);
                    $children = (int)($bookingInfo['children'] ?? 0);
                    $infants = (int)($bookingInfo['infants'] ?? 0);
                    if ($adults > 0 || $children > 0 || $infants > 0) {
                        if ($adults > 0) {
                            echo '<div class="mt4"><p class="text fz14 fw400 lh22 black12">Adult x' . $adults . '</p></div>';
                        }
                        if ($children > 0) {
                            echo '<div class="mt4"><p class="text fz14 fw400 lh22 black12">Child x' . $children . '</p></div>';
                        }
                        if ($infants > 0) {
                            echo '<div class="mt4"><p class="text fz14 fw400 lh22 black12">Infant x' . $infants . '</p></div>';
                        }
                    } else {
                        echo '<div class="text fz14 fw400 lh22 black12 mt4">-</div>';
                    }
                }
                ?>
            </div>
            
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('room_option', $currentLang); ?></div>
            <div id="roomInfo">
                <?php
                // selectedRooms: bookings.selectedRooms 컬럼이 authoritative (JSON)
                $selectedRooms = [];
                if (!empty($bookingInfo['selectedRooms'])) {
                    $tmp = json_decode((string)$bookingInfo['selectedRooms'], true);
                    if (is_array($tmp)) $selectedRooms = $tmp;
                }
                // fallback: selectedOptions 내부(구 구조)
                if (empty($selectedRooms)) {
                    $selectedRooms = $selectedOptions['selectedRooms'] ?? [];
                    if (empty($selectedRooms) && !empty($selectedOptions['rooms'])) {
                        $selectedRooms = $selectedOptions['rooms'];
                    }
                }
                
                if (is_array($selectedRooms) && count($selectedRooms) > 0) {
                    $roomNames = [
                        'standard' => getI18nText('standard_room', $currentLang),
                        'double' => getI18nText('double_room', $currentLang),
                        'triple' => getI18nText('triple_room', $currentLang),
                        'family' => getI18nText('family_room', $currentLang),
                        'single' => getI18nText('single_room', $currentLang)
                    ];

                    foreach ($selectedRooms as $roomId => $roomData) {
                        if (!is_array($roomData)) continue;

                        $roomCount = (int)($roomData['count'] ?? 1);
                        // roomPrice 또는 price 지원
                        $unitPrice = (float)($roomData['roomPrice'] ?? $roomData['price'] ?? 0);
                        $roomPrice = $roomData['totalPrice'] ?? ($unitPrice * $roomCount);
                        // roomType, name, roomId 순서로 이름 확인
                        $roomIdKey = $roomData['roomId'] ?? $roomId;
                        $roomName = $roomData['roomType'] ?? $roomData['name'] ?? ($roomNames[$roomIdKey] ?? $roomIdKey);

                        echo '<div class="mt4">';
                        echo '<p class="text fz14 fw400 lh22 black12">' . htmlspecialchars($roomName) . ' x' . $roomCount . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="mt4">';
                    echo '<p class="text fz14 fw400 lh22 black12">' . htmlspecialchars(getI18nText('standard_room', $currentLang)) . ' x1</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('flight_options', $currentLang); ?></div>
            <div id="flightOptionsInfo">
                <?php
                // Flight Options 조회 (booking_traveler_options + airline_options)
                $flightOptions = [];
                if (!empty($bookingInfo['bookingId'])) {
                    try {
                        $foSql = "SELECT
                                    bto.traveler_index,
                                    ao.option_name,
                                    ao.option_name_en,
                                    aoc.category_name,
                                    aoc.category_name_en
                                  FROM booking_traveler_options bto
                                  LEFT JOIN airline_options ao ON bto.option_id = ao.option_id
                                  LEFT JOIN airline_option_categories aoc ON ao.category_id = aoc.category_id
                                  WHERE bto.booking_id = ?
                                  ORDER BY aoc.sort_order, bto.traveler_index";
                        $foStmt = $conn->prepare($foSql);
                        $foStmt->bind_param("s", $bookingInfo['bookingId']);
                        $foStmt->execute();
                        $foResult = $foStmt->get_result();
                        while ($fo = $foResult->fetch_assoc()) {
                            $flightOptions[] = $fo;
                        }
                        $foStmt->close();
                    } catch (Exception $e) {
                        error_log("Flight options query error: " . $e->getMessage());
                    }
                }

                if (!empty($flightOptions)) {
                    // 카테고리별로 그룹화
                    $groupedOptions = [];
                    foreach ($flightOptions as $fo) {
                        $catName = $currentLang === 'ko' ? ($fo['category_name'] ?? 'Other') : ($fo['category_name_en'] ?? $fo['category_name'] ?? 'Other');
                        $optName = $currentLang === 'ko' ? ($fo['option_name'] ?? '') : ($fo['option_name_en'] ?? $fo['option_name'] ?? '');
                        if (!isset($groupedOptions[$catName])) {
                            $groupedOptions[$catName] = [];
                        }
                        // 같은 옵션이 여러 traveler에게 있으면 카운트
                        if (!isset($groupedOptions[$catName][$optName])) {
                            $groupedOptions[$catName][$optName] = 0;
                        }
                        $groupedOptions[$catName][$optName]++;
                    }

                    foreach ($groupedOptions as $category => $options) {
                        echo '<div class="mt8">';
                        echo '<p class="text fz13 fw500 lh20 gray96">' . htmlspecialchars($category) . '</p>';
                        foreach ($options as $optionName => $count) {
                            $displayText = $optionName;
                            if ($count > 1) {
                                $displayText .= ' x' . $count;
                            }
                            echo '<p class="text fz14 fw400 lh22 black12 mt2">' . htmlspecialchars($displayText) . '</p>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="text fz14 fw400 lh22 black12 mt4">' . htmlspecialchars(getI18nText('not_selected', $currentLang)) . '</div>';
                }
                ?>
            </div>

            <?php
            // Other Requests 텍스트 추출
            $otherReqText = trim((string)($bookingInfo['specialRequests'] ?? ''));
            if ($otherReqText === '') {
                $otherReqText = trim((string)($selectedOptions['otherRequests'] ?? $selectedOptions['other_requests'] ?? $selectedOptions['specialRequests'] ?? $selectedOptions['special_requests'] ?? ''));
                if (is_array($selectedOptions['otherRequests'] ?? null)) {
                    $otherReqText = trim((string)($selectedOptions['otherRequests']['value'] ?? ''));
                }
            }
            ?>

            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('other_requests', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="otherRequests"><?php echo htmlspecialchars($otherReqText !== '' ? $otherReqText : getI18nText('no_requests', $currentLang)); ?></div>

            <!-- [TEMPORARILY HIDDEN - Total Price]
                 Uncomment below to restore total price display
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('total_price', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="totalPrice"><?php echo formatPrice($bookingInfo['totalAmount'] ?? 0); ?></div>
            -->
        </div>
        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('booker_info', $currentLang); ?></div>
        </div>

        <div class="px20 pb20 border-bottom10">
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('booker_info', $currentLang); ?></div>

            <div class="card-type8 pink mt8">
                <ul>
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12"><?php echoI18nText('name', $currentLang); ?></div>
                        <div class="text fz14 fw400 lh22 black12" id="bookerName">
                            <?php 
                            $bookerName = trim(($bookingInfo['fName'] ?? '') . ' ' . ($bookingInfo['lName'] ?? ''));
                            if (empty($bookerName)) {
                                // client 테이블에 없으면 selectedOptions.customerInfo에서 가져오기
                                $custInfo = $selectedOptions['customerInfo'] ?? [];
                                $bookerName = trim(($custInfo['firstName'] ?? '') . ' ' . ($custInfo['lastName'] ?? ''));
                            }
                            if (empty($bookerName)) {
                                $bookerName = getI18nText('db_no_data', $currentLang);
                            }
                            echo htmlspecialchars($bookerName);
                            ?>
                        </div>
                    </li>
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12 mt12"><?php echoI18nText('email', $currentLang); ?></div>
                        <div class="text fz14 fw400 lh22 black12" id="bookerEmail">
                            <?php 
                            $bookerEmail = $bookingInfo['contactEmail'] ?? $bookingInfo['accountEmail'] ?? '';
                            if (empty($bookerEmail)) {
                                $bookerEmail = getI18nText('db_no_data', $currentLang);
                            }
                            echo htmlspecialchars($bookerEmail);
                            ?>
                        </div>
                    </li>
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12 mt12"><?php echoI18nText('phone', $currentLang); ?></div>
                        <div class="text fz14 fw400 lh22 black12" id="bookerPhone">
                            <?php 
                            $bookerPhone = $bookingInfo['contactPhone'] ?? $bookingInfo['contactNo'] ?? '';
                            if (empty($bookerPhone)) {
                                $bookerPhone = getI18nText('db_no_data', $currentLang);
                            }
                            echo htmlspecialchars($bookerPhone);
                            ?>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('traveler_info', $currentLang); ?></div>
            <div class="mt12" id="travelersInfo">
                <?php
                // DB에서 여행자 정보 조회
                $travelers = [];
                if (!empty($bookingInfo['bookingId'])) {
                    try {
                        $travelerSql = "SELECT 
                            travelerType, firstName, lastName, birthDate, 
                            gender, nationality, passportNumber, passportExpiry,
                            title, isMainTraveler
                        FROM booking_travelers 
                        WHERE transactNo = ? 
                        ORDER BY 
                            CASE travelerType WHEN 'adult' THEN 1 WHEN 'child' THEN 2 WHEN 'infant' THEN 3 END,
                            isMainTraveler DESC,
                            bookingTravelerId";
                        $travelerStmt = $conn->prepare($travelerSql);
                        $travelerStmt->bind_param("s", $bookingInfo['bookingId']);
                        $travelerStmt->execute();
                        $travelerResult = $travelerStmt->get_result();
                        while ($traveler = $travelerResult->fetch_assoc()) {
                            $travelers[] = $traveler;
                        }
                        $travelerStmt->close();
                    } catch (Exception $e) {
                        error_log("Traveler query error: " . $e->getMessage());
                    }
                }
                
                if (empty($travelers)) {
                    echo '<div class="text fz14 fw400 lh22 black12">' . htmlspecialchars(getI18nText('db_no_data', $currentLang)) . '</div>';
                } else {
                    // dev_tasks #122: travelerType 대소문자/이름 공백 케이스에서도 리스트가 항상 보이도록 보강
                    $seq = ['adult' => 0, 'child' => 0, 'infant' => 0];
                    foreach ($travelers as $traveler) {
                        $rawType = (string)($traveler['travelerType'] ?? 'adult');
                        $typeKey = strtolower($rawType);
                        if (!isset($seq[$typeKey])) $seq[$typeKey] = 0;
                        $seq[$typeKey] += 1;

                        $name = trim(($traveler['firstName'] ?? '') . ' ' . ($traveler['lastName'] ?? ''));
                        if ($name === '') {
                            // fallback: "Adult 1" etc.
                            $labelType = ucfirst($typeKey);
                            $name = $labelType . ' ' . $seq[$typeKey];
                        }

                        echo '<a href="traveler-info-detail.html?bookingId=' . htmlspecialchars($bookingInfo['bookingId']) . '&type=' . htmlspecialchars($typeKey) . '&name=' . urlencode($name) . '" class="align both vm py12">';
                        echo '<div class="align vm">';
                        echo '<div class="text fz14 fw500 lh22 black12">' . htmlspecialchars(getI18nText($typeKey, $currentLang)) . ': ' . htmlspecialchars($name) . '</div>';
                        if ($traveler['isMainTraveler'] ?? false) {
                            echo '<span class="label green ml12">' . htmlspecialchars(getI18nText('main_traveler', $currentLang)) . '</span>';
                        }
                        echo '</div>';
                        echo '<img src="../images/ico_arrow_right_black.svg" alt="">';
                        echo '</a>';
                    }
                }
                ?>
            </div>
        </div>
        <!-- [TEMPORARILY HIDDEN - Payment Information Section]
             Uncomment below to restore payment information display
        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('payment_info', $currentLang); ?></div>
        </div>

        <div class="px20 pb20 border-bottom10">
            <div class="text fz14 fw600 lh22 gray96 mt16"><?php echoI18nText('payment_method', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="paymentMethod">
                <?php
                $paymentStatus = $bookingInfo['paymentStatus'] ?? '';
                $paymentMethod = $bookingInfo['paymentMethod'] ?? '';
                if ($paymentStatus === 'pending') {
                    echo htmlspecialchars(getI18nText('bank_transfer', $currentLang));
                } elseif (!empty($paymentMethod)) {
                    echo htmlspecialchars($paymentMethod);
                } else {
                    echo htmlspecialchars(getI18nText('not_selected', $currentLang));
                }
                ?>
            </div>

            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('order_amount', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="orderAmount"><?php echo formatPrice($bookingInfo['totalAmount'] ?? 0); ?></div>

            <div class="text fz14 fw600 lh22 gray96 mt20"><?php echoI18nText('total_amount_paid', $currentLang); ?></div>
            <div class="text fz14 fw400 lh22 black12 mt4" id="totalAmountPaid"><?php echo formatPrice($bookingInfo['totalAmount'] ?? 0); ?></div>
        </div>
        [END TEMPORARILY HIDDEN - Payment Information Section] -->

        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('trip_schedule', $currentLang); ?></div>
        </div>
        
        <div class="px20 pb20 border-bottom10">
            <?php
            // 요구사항: "일정 보러가기" → 해당 상품 상세 페이지의 일정표 탭으로 이동
            // product-detail.js 에서 tab=schedule|itinerary 를 감지해 #schedule 로 스크롤합니다.
            $scheduleViewUrl = '#';
            if (!empty($bookingInfo['packageId'])) {
                $scheduleViewUrl = 'product-detail.php?id=' . rawurlencode((string)$bookingInfo['packageId']) . '&tab=schedule';
            }
            ?>
            <!-- <a href="<?php echo htmlspecialchars($scheduleViewUrl); ?>" class="btn line lg active" id="scheduleLink"><?php echoI18nText('view_schedule', $currentLang); ?></a> -->
            <a href="<?php echo htmlspecialchars($scheduleViewUrl); ?>" class="btn secondary lg" id="scheduleLink"><?php echoI18nText('view_schedule', $currentLang); ?></a>
            <?php if (!empty($fullScheduleDownloadUrl)): ?>
                <a href="<?php echo htmlspecialchars($fullScheduleDownloadUrl); ?>" class="btn line lg ico2 active mt12"><?php echoI18nText('download_full_schedule', $currentLang); ?></a>
            <?php else: ?>
                <a href="#" class="btn line lg ico2 mt12" style="pointer-events:none; opacity:.5;"><?php echoI18nText('download_full_schedule', $currentLang); ?></a>
            <?php endif; ?>
        </div>
        
        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('usage_guide', $currentLang); ?></div>
        </div>
        
        <div class="px20 pb20 border-bottom10">
            <div class="text fz14 fw400 lh22 black12"><?php echoI18nText('usage_guide_text', $currentLang); ?></div>
            <?php if (!empty($usageGuideDownloadUrl)): ?>
                <a href="<?php echo htmlspecialchars($usageGuideDownloadUrl); ?>" class="btn line lg ico2 active mt12" download><?php echoI18nText('download_guide', $currentLang); ?></a>
            <?php else: ?>
                <a href="#" class="btn line lg ico2 mt12" style="pointer-events:none; opacity:.5;"><?php echoI18nText('download_guide', $currentLang); ?></a>
            <?php endif; ?>
        </div>
        
        <div class="px20 border-bottomf3">
            <div class="text fz16 fw600 lh24 black12 py12"><?php echoI18nText('guide_info', $currentLang); ?></div>
        </div>
        
        <div class="px20 pb20 border-bottom10">
            <?php if (!$showGuideSection): ?>
                <div class="text fz14 fw400 lh22 gray96 mt16">No guide has been assigned yet.</div>
            <?php else: ?>
            <div class="align both mt16">
                <div>
                    <div class="text fz14 fw600 lh22 gray96"><?php echoI18nText('name', $currentLang); ?></div>
                    <p class="text fz14 fw400 lh22 black12 mt4" id="guideName"><?php echo htmlspecialchars($guideInfo['guideName'] ?? ''); ?></p>

                    <div class="text fz14 fw600 lh22 gray96 mt16"><?php echoI18nText('contact', $currentLang); ?></div>
                    <p class="text fz14 fw400 lh22 black12 mt4" id="guideContact"><?php echo htmlspecialchars($guideInfo['phone'] ?? ''); ?></p>

                    <div class="text fz14 fw600 lh22 gray96 mt16"><?php echoI18nText('about_me', $currentLang); ?></div>
                    <div class="text fz14 fw400 lh22 black12 mt4" id="guideAbout"><?php echo ($guideInfo['about'] ?? ''); ?></div>

                </div>
                <img class="img-80 rounded8" src="<?php echo htmlspecialchars($guideInfo['profileImage'] ?? '../images/@img_profile_square.png'); ?>" alt="" id="guideImage" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" onerror="this.src='../images/@img_profile_square.png'">
            </div>
            <div class="mt16">
                <?php if ($showGuideLocationButton): ?>
                    <a href="guide-location.php?booking_id=<?php echo htmlspecialchars($bookingInfo['bookingId'] ?? ''); ?>&date=<?php echo date('Y-m-d'); ?>" class="btn primary lg" id="guideLocationLink"><?php echoI18nText('check_guide_location', $currentLang); ?></a>
                <?php else: ?>
                    <a href="#" class="btn primary lg" id="guideLocationLink" style="pointer-events:none; opacity:.5;"><?php echoI18nText('check_guide_location', $currentLang); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="card-type12 mt40">
                <div class="text fz16 fw600 lh24 black12"><?php echoI18nText('cancellation_policy', $currentLang); ?></div>
                <p class="text fz14 fw400 lh22 black12 mt12">
                    <?php
                    if ($currentLang === 'ko') {
                        echo '• 출발 15일 전: 100% 투어 요금 환불(취소 수수료 없음)<br>';
                        echo '• 출발 8-14일 전: 50% 투어 요금 환불(50% 취소 수수료)<br>';
                        echo '• 출발 4-7일 전: 30% 투어 요금 환불(70% 취소 수수료)<br>';
                        echo '• 출발 1-3일 전: 0% 투어 요금 환불(100% 취소 수수료)';
                    } else if ($currentLang === 'tl') {
                        echo '• Bago ang 15 araw ng pag-alis: 100% refund ng tour fare(Walang cancellation charge)<br>';
                        echo '• Bago ang 8-14 araw ng pag-alis: 50% refund ng tour fare(50% cancellation charge)<br>';
                        echo '• Bago ang 4-7 araw ng pag-alis: 30% refund ng tour fare(70% cancellation charge)<br>';
                        echo '• Bago ang 1-3 araw ng pag-alis: 0% refund ng tour fare(100% cancellation charge)';
                    } else {
                        echo '• Before 15 days of departure: 100% tour fare refund(No cancellation charge)<br>';
                        echo '• Before 8-14 days of departure: 50% tour fare refund(50% cancellation charge)<br>';
                        echo '• Before 4-7 days of departure: 30% tour fare refund(70% cancellation charge)<br>';
                        echo '• Before 1-3 days of departure: 0% tour fare refund(100% cancellation charge)';
                    }
                    ?>
                </p>
            </div>

            <div class="mt40">
                <a href="inquiry.html?bookingId=<?php echo htmlspecialchars($bookingInfo['bookingId'] ?? ''); ?>&type=cancellation" class="btn line danger lg mt16" id="cancellationLink"><?php echoI18nText('cancel_reservation', $currentLang); ?></a>
            </div>
            <div class="card-type grayf3 px16 py14 align both vm mt40">
                <div class="align gap8 vm">
                    <img src="../images/ico_inquiry_red.svg" alt="">
                    <div class="text fz14 fw500 lh20 black12"><?php echoI18nText('have_questions', $currentLang); ?></div>
                </div>
                <a href="inquiry.html" class="text fz14 fw500 lh20 reded"><?php echoI18nText('customer_support', $currentLang); ?><img src="../images/ico_arrow_right_red.svg" alt=""></a>
            </div>
        </div>
    </div>
    
    <script>
        // 예약 정보를 JavaScript에서 사용할 수 있도록 전역 변수로 설정
        window.bookingInfo = <?php echo json_encode($bookingInfo, JSON_UNESCAPED_UNICODE); ?>;
        window.currentLang = '<?php echo $currentLang; ?>';

        function goBack() {
            var params = new URLSearchParams(window.location.search);
            var from = params.get('from');
            var lang = params.get('lang') || 'en';
            if (from === 'mypage') {
                window.location.href = 'mypage.html?lang=' + lang;
            } else {
                window.location.href = '../home.html';
            }
        }
    </script>
</body>
</html>

