<?php
require_once '../backend/i18n_helper.php';

// URL 파라미터에서 booking_id 가져오기
$bookingId = $_GET['booking_id'] ?? $_GET['bookingId'] ?? '';

// 예약 정보 로드
$bookingInfo = null;
$scheduleData = null; // 화면/JS에서 쓰는 "일정 배열"
$guideData = null;    // guide-location.php와 동일한 구조

if ($bookingId) {
    try {
        // 데이터베이스 연결
        require_once '../backend/conn.php';
        
        // 예약 정보 조회
        $sql = "SELECT 
                    b.bookingId,
                    b.accountId,
                    b.guideId,
                    b.packageId,
                    b.packageName,
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
                    b.createdAt,
                    b.contactEmail,
                    b.contactPhone,
                    p.packageName as productName,
                    p.packageImage as thumbnail,
                    a.emailAddress,
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

            // guide-location.php와 동일한 방식으로 guideId 결정
            $guideId = $bookingInfo['guideId'] ?? null;
            if (empty($guideId)) {
                $tbl = $conn->query("SHOW TABLES LIKE 'booking_guides'");
                if ($tbl && $tbl->num_rows > 0) {
                    $gs = $conn->prepare("SELECT guideId FROM booking_guides WHERE bookingId = ? LIMIT 1");
                    if ($gs) {
                        $gs->bind_param('s', $bookingId);
                        $gs->execute();
                        $gRes = $gs->get_result();
                        if ($gRes && $gRes->num_rows > 0) {
                            $guideId = $gRes->fetch_assoc()['guideId'] ?? null;
                        }
                        $gs->close();
                    }
                }
            }

            // 가이드 기본 정보 + 현재 위치(guide_locations active)
            $guideData = [
                'guideId' => null,
                'guideName' => '',
                'phone' => '',
                'profileImage' => '../images/@img_profile.svg',
                'about' => '',
                'currentLatitude' => 37.5665,
                'currentLongitude' => 126.9780,
                'location' => 'Seoul, Korea',
                'lastLocationUpdate' => date('Y-m-d H:i:s')
            ];

            if (!empty($guideId)) {
                $gid = (int)$guideId;
                $gstmt = $conn->prepare("SELECT guideId, guideName, profileImage, phoneNumber, introduction FROM guides WHERE guideId = ? LIMIT 1");
                if ($gstmt) {
                    $gstmt->bind_param('i', $gid);
                    $gstmt->execute();
                    $gRow = $gstmt->get_result()->fetch_assoc();
                    $gstmt->close();

                    if ($gRow) {
                        $guideData['guideId'] = $gRow['guideId'] ?? $gid;
                        // 하드코딩 기본값 금지: DB 값이 없으면 공란
                        $guideData['guideName'] = $gRow['guideName'] ?? '';
                        $guideData['phone'] = $gRow['phoneNumber'] ?? '';
                        $guideData['profileImage'] = !empty($gRow['profileImage'])
                            ? ('../' . ltrim((string)$gRow['profileImage'], '/'))
                            : '../images/@img_profile.svg';
                        $guideData['about'] = $gRow['introduction'] ?? '';
                    }
                }

                $lq = $conn->prepare("SELECT latitude, longitude, address, updatedAt FROM guide_locations WHERE guideId = ? AND isActive = 1 ORDER BY updatedAt DESC LIMIT 1");
                if ($lq) {
                    $lq->bind_param('i', $gid);
                    $lq->execute();
                    $lr = $lq->get_result()->fetch_assoc();
                    $lq->close();
                    if ($lr) {
                        if (!empty($lr['latitude'])) $guideData['currentLatitude'] = (float)$lr['latitude'];
                        if (!empty($lr['longitude'])) $guideData['currentLongitude'] = (float)$lr['longitude'];
                        if (!empty($lr['address'])) $guideData['location'] = (string)$lr['address'];
                        if (!empty($lr['updatedAt'])) $guideData['lastLocationUpdate'] = (string)$lr['updatedAt'];
                    }
                }
            }

            // itinerary(패키지 일정) 로드
            // - 요구사항: "상품 > 당일 일정표"에 저장된 관광지가 시간순으로 보여야 함
            // - 신규 저장 구조(package_schedules + package_attractions)가 있으면 우선 사용
            // - 없으면 package_itinerary(레거시)로 fallback
            $scheduleData = [];
            $packageId = isset($bookingInfo['packageId']) ? (int)$bookingInfo['packageId'] : 0;
            if ($packageId > 0 && !empty($bookingInfo['departureDate'])) {
                $departureDate = new DateTime($bookingInfo['departureDate']);

                // 1) 신규: package_schedules + package_attractions
                $schTbl = $conn->query("SHOW TABLES LIKE 'package_schedules'");
                if ($schTbl && $schTbl->num_rows > 0) {
                    $sstmt = $conn->prepare("SELECT schedule_id, day_number, description, start_time, end_time,
                                                    airport_location, airport_address, airport_description,
                                                    accommodation_name, accommodation_address, accommodation_description,
                                                    transportation_description, breakfast, lunch, dinner
                                             FROM package_schedules
                                             WHERE package_id = ?
                                             ORDER BY day_number ASC, schedule_id ASC");
                    if ($sstmt) {
                        $sstmt->bind_param('i', $packageId);
                        $sstmt->execute();
                        $sres = $sstmt->get_result();
                        $rows = [];
                        while ($r = $sres->fetch_assoc()) $rows[] = $r;
                        $sstmt->close();

                        // attractions mapping
                        $attrMap = [];
                        $attrTbl = $conn->query("SHOW TABLES LIKE 'package_attractions'");
                        if ($attrTbl && $attrTbl->num_rows > 0 && !empty($rows)) {
                            $ids = [];
                            foreach ($rows as $r) {
                                $sid = (int)($r['schedule_id'] ?? 0);
                                if ($sid > 0) $ids[] = $sid;
                            }
                            if (!empty($ids)) {
                                $in = implode(',', array_fill(0, count($ids), '?'));
                                $types = str_repeat('i', count($ids));
                                $stmtA = $conn->prepare("SELECT schedule_id, attraction_name, attraction_address, attraction_description, attraction_image, start_time, end_time, visit_order
                                                         FROM package_attractions
                                                         WHERE schedule_id IN ($in)
                                                         ORDER BY schedule_id ASC, start_time ASC, visit_order ASC, attraction_id ASC");
                                if ($stmtA) {
                                    $params = $ids;
                                    $bind = [];
                                    $bind[] = $types;
                                    foreach ($params as $i => $_) $bind[] = &$params[$i];
                                    call_user_func_array([$stmtA, 'bind_param'], $bind);
                                    $stmtA->execute();
                                    $ares = $stmtA->get_result();
                                    while ($a = $ares->fetch_assoc()) {
                                        $sid = (int)($a['schedule_id'] ?? 0);
                                        if (!isset($attrMap[$sid])) $attrMap[$sid] = [];
                                        $attrMap[$sid][] = $a;
                                    }
                                    $stmtA->close();
                                }
                            }
                        }

                        foreach ($rows as $r) {
                            $dayNumber = (int)($r['day_number'] ?? 1);
                            $date = clone $departureDate;
                            $date->add(new DateInterval('P' . max(0, $dayNumber - 1) . 'D'));
                            $sid = (int)($r['schedule_id'] ?? 0);
                            $atts = $attrMap[$sid] ?? [];

                            $activities = [];
                            if (!empty($atts)) {
                                foreach ($atts as $a) {
                                    $nm = trim((string)($a['attraction_name'] ?? ''));
                                    if ($nm !== '') $activities[] = $nm;
                                }
                            }
                            if (empty($activities)) {
                                $fallbackTitle = trim((string)($r['description'] ?? ''));
                                if ($fallbackTitle === '') $fallbackTitle = trim((string)($r['airport_location'] ?? ''));
                                if ($fallbackTitle !== '') $activities[] = $fallbackTitle;
                            }

                            $scheduleData[] = [
                                'date' => $date->format('Y-m-d'),
                                'day_number' => $dayNumber,
                                'activities' => $activities,
                                'start_time' => $r['start_time'] ?? null,
                                'end_time' => $r['end_time'] ?? null,
                                'description' => $r['description'] ?? '',
                                'airport_location' => $r['airport_location'] ?? '',
                                'airport_address' => $r['airport_address'] ?? '',
                                'airport_description' => $r['airport_description'] ?? '',
                                'accommodation_name' => $r['accommodation_name'] ?? '',
                                'accommodation_address' => $r['accommodation_address'] ?? '',
                                'accommodation_description' => $r['accommodation_description'] ?? '',
                                'transportation_description' => $r['transportation_description'] ?? '',
                                'meals' => [
                                    'breakfast' => $r['breakfast'] ?? null,
                                    'lunch' => $r['lunch'] ?? null,
                                    'dinner' => $r['dinner'] ?? null,
                                ],
                                // 확장 데이터(필요시 js에서 사용)
                                'attractions' => array_map(function ($a) {
                                    // 이미지 경로 보정:
                                    // - DB에는 파일명만 저장되는 케이스가 있음(tpl_xxx.jpeg)
                                    // - 실제 파일은 uploads/products/ 하위에 존재하므로 경로를 보완한다.
                                    $img = trim((string)($a['attraction_image'] ?? ''));
                                    if ($img !== '') {
                                        // 이미 경로/URL이 있는 경우는 그대로 사용
                                        if (!(str_starts_with($img, 'http://') || str_starts_with($img, 'https://') || str_starts_with($img, '/') || str_contains($img, '/'))) {
                                            // filename only → uploads/products/
                                            $img = 'uploads/products/' . $img;
                                        } else {
                                            $img = ltrim($img, '/');
                                        }
                                    }
                                    return [
                                        'name' => $a['attraction_name'] ?? '',
                                        'address' => $a['attraction_address'] ?? '',
                                        'description' => $a['attraction_description'] ?? '',
                                        'image' => $img,
                                        'start_time' => $a['start_time'] ?? null,
                                        'end_time' => $a['end_time'] ?? null,
                                        'visit_order' => $a['visit_order'] ?? 0,
                                    ];
                                }, $atts),
                            ];
                        }
                    }
                }

                // 2) fallback: package_itinerary(레거시)
                if (empty($scheduleData)) {
                    $itinTbl = $conn->query("SHOW TABLES LIKE 'package_itinerary'");
                    if ($itinTbl && $itinTbl->num_rows > 0) {
                        $itinSql = "SELECT itineraryId, dayNumber, title, description, activities, accommodation, meals, transportation, startTime, endTime, location, locationAddress, notes
                                    FROM package_itinerary
                                    WHERE packageId = ?
                                    ORDER BY dayNumber ASC, itineraryId ASC";
                        $itinStmt = $conn->prepare($itinSql);
                        if ($itinStmt) {
                            $itinStmt->bind_param('i', $packageId);
                            $itinStmt->execute();
                            $itinRes = $itinStmt->get_result();
                            while ($r = $itinRes->fetch_assoc()) {
                                $dayNumber = (int)($r['dayNumber'] ?? 1);
                                $date = clone $departureDate;
                                $date->add(new DateInterval('P' . max(0, $dayNumber - 1) . 'D'));

                                $activities = [];
                                if (!empty($r['activities'])) {
                                    $lines = preg_split("/\r\n|\n|\r/", (string)$r['activities']);
                                    foreach ($lines as $line) {
                                        $t = trim($line);
                                        if ($t !== '') $activities[] = $t;
                                    }
                                }
                                if (empty($activities)) {
                                    if (!empty($r['title'])) $activities[] = (string)$r['title'];
                                    else if (!empty($r['location'])) $activities[] = (string)$r['location'];
                                }

                                $scheduleData[] = [
                                    'date' => $date->format('Y-m-d'),
                                    'day_number' => $dayNumber,
                                    'activities' => $activities,
                                    'start_time' => $r['startTime'] ?? null,
                                    'end_time' => $r['endTime'] ?? null,
                                    'description' => $r['description'] ?? '',
                                    'airport_location' => $r['location'] ?? '',
                                    'airport_address' => $r['locationAddress'] ?? '',
                                    'airport_description' => $r['notes'] ?? '',
                                    'accommodation_name' => $r['accommodation'] ?? '',
                                    'accommodation_address' => '',
                                    'accommodation_description' => '',
                                    'transportation_description' => $r['transportation'] ?? '',
                                    'meals' => [
                                        'breakfast' => null,
                                        'lunch' => null,
                                        'dinner' => null
                                    ],
                                    // 레거시에는 관광지별 이미지/시간 정보가 없을 수 있어 attractions는 비움
                                    'attractions' => []
                                ];
                            }
                            $itinStmt->close();
                        }
                    }
                }
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Schedule page error: " . $e->getMessage());
    }
}

// 현재 언어 설정
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('travel_schedule', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <!-- Kakao Maps -->
    <script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=c9d9068a507832cb391cf6ed52897501&libraries=services&autoload=false" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/button.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/schedule.js?v=20251226_schedulefix1" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type1">
            <a class="btn-mypage" href="/home.html"><img src="../images/ico_big_close.svg"></a>
            <div class="title"></div>
            <a class="btn-bell" href="guide-notice.html<?php echo !empty($bookingId) ? '?booking_id=' . htmlspecialchars($bookingId) : ''; ?>">
                <img src="../images/ico_bell.svg">
                <!-- unread badge: JS에서 실제 count 로 갱신 -->
                <i class="num" style="display:none;">0</i>
            </a>
        </header>
        <div class="px20 mt20 mb72">
            <ul class="tab-type3">
                <li><a class="btn-tab3 active" href="schedule.php?booking_id=<?php echo htmlspecialchars($bookingId ?? ''); ?>">Travel Itinerary</a></li>
                <li><a class="btn-tab3" href="guide-location.php?booking_id=<?php echo htmlspecialchars($bookingId ?? ''); ?>">Guide Location</a></li>
            </ul>
            <div class="map-type1 mt20">
                <!-- Kakao Maps: schedule.js에서 렌더 -->
                <div id="map" style="width: 100%; height: 300px; background: #f0f0f0; border-radius: 14px;"></div>
                <div class="card-type4">
                    <div class="align vm both text fz14 fw600 lh22 black12">
                        Guide Info
                        <button class="btn-folding btn-fold1" type="button"><img src="../images/ico_arrow_down_gray.svg" alt=""></button>
                    </div>
                    <div class="align both mt8" id="profile">
                        <div>
                            <div class="align gap12">
                                <img class="profile" src="<?php echo $guideData['profileImage'] ?? '../images/@img_profile.svg'; ?>" alt="">
                                <div>
                                    <div class="text fz12 fw500 lh16 grayb0"><?php echoI18nText('guide', $currentLang); ?></div>
                                    <div class="text fz14 fw600 lh22 black12"><?php echo !empty($guideData['guideName']) ? htmlspecialchars($guideData['guideName']) : ''; ?></div>
                                    <div class="text fz13 fw400 lh16 black12"><?php echo !empty($guideData['phone']) ? htmlspecialchars($guideData['phone']) : ''; ?></div>
                                </div>
                            </div>
                        </div>
                        <button class="btn-profile-view" type="button"><img src="../images/ico_go_round.svg" alt=""></button>
                    </div>
                </div>
            </div>
            <div class="scroll_x">
                <ul class="calendar-type1 mt20" id="calendarContainer">
                    <!-- JavaScript에서 동적으로 로드됨 -->
                </ul>
            </div>
            <div class="align vm both text fz16 fw600 lh24 black12 mt20">
                <?php echoI18nText('itinerary_details', $currentLang); ?>
                <button class="text btn-folding btn-fold2" type="button"><img src="../images/ico_arrow_down_gray.svg" alt=""></button>
            </div>
            <!-- <div class="mt12">
                <a href="../backend/api/schedule-export.php?booking_id=<?php echo htmlspecialchars($bookingId ?? ''); ?>&format=xlsx" class="btn line lg active">
                    <?php echoI18nText('download_full_schedule', $currentLang); ?>
                </a>
            </div> -->
            <div class="scroll_x" id="card">
                <ul class="list-type4 mt12" id="scheduleList" data-skip-hangul-scrub="1">
                    <!-- JavaScript에서 동적으로 로드됨 -->
                </ul>
            </div>
            <div class="align vm both text fz16 fw600 lh24 black12 mt20">
                <?php echoI18nText('timeline_view', $currentLang); ?>
                <button class="text btn-folding btn-fold3" type="button"><img src="../images/ico_arrow_down_gray.svg" alt=""></button>
            </div>
            <ul class="list-type5 mt12" id="timelineList" data-skip-hangul-scrub="1">
                <!-- JavaScript에서 동적으로 로드됨 -->
            </ul>
        </div>
    </div>

    <div class="layer"></div>
    <div class="profile-modal">
        <div class="header align both vm">
            <div class="text fz16 fw600 lh24 white"><?php echoI18nText('certified_guide', $currentLang); ?></div>
            <button class="btn-close-modal" type="button"><img src="../images/ico_close_white.svg" alt=""></button>
        </div>
        <div>
            <img class="img-profile" src="../images/@img_profile.png" alt="">
        </div>
        <div class="form-wrap">
            <div class="input-wrap">
                <img src="../images/ico_mem_red.svg" alt="" style="width: 24px; height: 24px;">
                <div class="text fz14 fw500 lh22 black12 ml8"><?php echo !empty($guideData['guideName']) ? htmlspecialchars($guideData['guideName']) : ''; ?></div>
            </div>
            <div style="width: 100%; height: 1px; background: #FF9800;"></div>
            <div class="input-wrap">
                <img src="../images/ico_tel_red.svg" alt="" style="width: 24px; height: 20px;">
                <div class="text fz14 fw500 lh22 black12 ml8"><?php echo !empty($guideData['phone']) ? htmlspecialchars($guideData['phone']) : ''; ?></div>
            </div>
            <div style="width: 100%; height: 1px; background: #FF9800;"></div>
            <div class="about">
                <img src="../images/ico_docu_red.svg" alt="" style="width: 24px; height: 24px;">
                <div class="text fz14 fw500 lh22 black12 ml8"><?php echoI18nText('about_me', $currentLang); ?></div>
            </div>
            <p class="text fz13 fw400 lh19 black4e">
                <?php echo !empty($guideData['about']) ? htmlspecialchars($guideData['about']) : ''; ?>
            </p>
        </div>
    </div>

    <script>
        // 예약 정보를 JavaScript에서 사용할 수 있도록 전역 변수로 설정
        window.bookingInfo = <?php echo json_encode($bookingInfo); ?>;
        window.scheduleData = <?php echo json_encode($scheduleData); ?>;
        window.guideData = <?php echo json_encode($guideData); ?>;
        window.currentLang = '<?php echo $currentLang; ?>';
    </script>
</body>
</html>
