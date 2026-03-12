<?php
require_once '../backend/i18n_helper.php';

// URL  booking_id 
$bookingId = $_GET['booking_id'] ?? $_GET['bookingId'] ?? '';
// :    " "   
$targetDate = date('Y-m-d');

//      
$bookingInfo = null;
$guideData = null;
$locationHistory = null;

if ($bookingId) {
    try {
        //  
        require_once '../backend/conn.php';
        
        //   
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

            // booking  guideId   ( booking_guides  )
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

            // guide  
            if (!empty($guideId)) {
                $gstmt = $conn->prepare("SELECT guideId, guideName, profileImage, phoneNumber, introduction FROM guides WHERE guideId = ? LIMIT 1");
                if ($gstmt) {
                    $gid = (int)$guideId;
                    $gstmt->bind_param('i', $gid);
                    $gstmt->execute();
                    $gRow = $gstmt->get_result()->fetch_assoc();
                    $gstmt->close();

                    //  (guide_locations  active)
                    $curLat = 37.5665;
                    $curLng = 126.9780;
                    $curAddr = 'Seoul, Korea';
                    $lastUpdate = date('Y-m-d H:i:s');
                    $lq = $conn->prepare("SELECT latitude, longitude, address, updatedAt FROM guide_locations WHERE guideId = ? AND isActive = 1 ORDER BY updatedAt DESC LIMIT 1");
                    if ($lq) {
                        $lq->bind_param('i', $gid);
                        $lq->execute();
                        $lr = $lq->get_result()->fetch_assoc();
                        $lq->close();
                        if ($lr) {
                            if (!empty($lr['latitude'])) $curLat = (float)$lr['latitude'];
                            if (!empty($lr['longitude'])) $curLng = (float)$lr['longitude'];
                            if (!empty($lr['address'])) $curAddr = (string)$lr['address'];
                            if (!empty($lr['updatedAt'])) $lastUpdate = (string)$lr['updatedAt'];
                        }
                    }

                    $guideData = [
                        'guideId' => $gRow['guideId'] ?? $gid,
                        'guideName' => $gRow['guideName'] ?? 'Guide',
                        'phone' => $gRow['phoneNumber'] ?? '',
                        'profileImage' => $gRow['profileImage'] ? ('../' . ltrim((string)$gRow['profileImage'], '/')) : '../images/@img_profile.svg',
                        'about' => $gRow['introduction'] ?? '',
                        'currentLatitude' => $curLat,
                        'currentLongitude' => $curLng,
                        'location' => $curAddr,
                        'lastLocationUpdate' => $lastUpdate
                    ];

                    // location-registration.html → meeting_locations   
                    // :
                    // 1)         
                    // 2) :  (meetingTime)  1 
                    // 3) :   (  )
                    // 4)     ,        

                    // OSRM()  () .   null .
                    $route_seconds = function ($fromLat, $fromLng, $toLat, $toLng) {
                        $fl = is_numeric($fromLat) ? (float)$fromLat : null;
                        $fg = is_numeric($fromLng) ? (float)$fromLng : null;
                        $tl = is_numeric($toLat) ? (float)$toLat : null;
                        $tg = is_numeric($toLng) ? (float)$toLng : null;
                        if ($fl === null || $fg === null || $tl === null || $tg === null) return null;

                        // OSRM public API
                        $url = "https://router.project-osrm.org/route/v1/driving/"
                            . rawurlencode($fg . "," . $fl) . ";"
                            . rawurlencode($tg . "," . $tl)
                            . "?overview=false";
                        $ctx = stream_context_create([
                            'http' => [
                                'method' => 'GET',
                                'timeout' => 4,
                                'header' => "User-Agent: smt-escape/1.0\r\nAccept: application/json\r\n",
                            ]
                        ]);
                        $raw = @file_get_contents($url, false, $ctx);
                        if ($raw === false) return null;
                        $json = json_decode($raw, true);
                        if (!is_array($json)) return null;
                        if (($json['code'] ?? '') !== 'Ok') return null;
                        $routes = $json['routes'] ?? null;
                        if (!is_array($routes) || empty($routes)) return null;
                        $sec = $routes[0]['duration'] ?? null;
                        return is_numeric($sec) ? (int)round($sec) : null;
                    };

                    $format_duration = function ($seconds) {
                        if (!is_numeric($seconds) || (int)$seconds <= 0) return '';
                        $sec = (int)$seconds;
                        $mins = (int)round($sec / 60);
                        $h = (int)floor($mins / 60);
                        $m = $mins % 60;
                        //  : About 1h 40m / About 40m
                        if ($h <= 0) return "About " . max(1, $m) . "m";
                        return "About {$h}h" . ($m > 0 ? " {$m}m" : "");
                    };

                    $locationHistory = [];
                    // soft delete(status)   active
                    $hasStatusCol = false;
                    try {
                        $c = $conn->query("SHOW COLUMNS FROM meeting_locations LIKE 'status'");
                        $hasStatusCol = ($c && $c->num_rows > 0);
                    } catch (Throwable $_) { $hasStatusCol = false; }

                    $mSql = "
                        SELECT meetingTime, locationName, address, latitude, longitude, content, createdAt
                        FROM meeting_locations
                        WHERE guideId = ?
                          AND bookingId = ?
                          AND DATE(createdAt) = ?
                    ";
                    if ($hasStatusCol) {
                        $mSql .= " AND status = 'active' ";
                    }
                    $mSql .= " ORDER BY meetingTime ASC, createdAt ASC LIMIT 50 ";

                    $mstmt = $conn->prepare($mSql);
                    if ($mstmt) {
                        $bid = (string)$bookingId;
                        $dt = (string)$targetDate;
                        $mstmt->bind_param('iss', $gid, $bid, $dt);
                        $mstmt->execute();
                        $mres = $mstmt->get_result();
                        $rows = [];
                        while ($r = $mres->fetch_assoc()) {
                            $rows[] = $r;
                        }
                        $mstmt->close();

                        //   sequence 
                        $seq = 0;
                        $prev = null;
                        foreach ($rows as $r) {
                            $seq++;
                            $time = '';
                            if (!empty($r['meetingTime'])) {
                                $tp = explode(':', (string)$r['meetingTime']);
                                if (count($tp) >= 2) $time = sprintf('%02d:%02d', (int)$tp[0], (int)$tp[1]);
                            }
                            $reg = '';
                            $dur = ''; //    →    
                            if (!empty($r['createdAt'])) {
                                $dt = new DateTime($r['createdAt']);
                                $reg = 'Registered (' . $dt->format('H:i:s') . ')';
                            }

                            // () :    prev->cur
                            if ($prev) {
                                $sec = $route_seconds($prev['latitude'] ?? null, $prev['longitude'] ?? null, $r['latitude'] ?? null, $r['longitude'] ?? null);
                                if ($sec === null) {
                                    // fallback:  (30km/h ) 
                                    $fl = is_numeric($prev['latitude'] ?? null) ? (float)$prev['latitude'] : null;
                                    $fg = is_numeric($prev['longitude'] ?? null) ? (float)$prev['longitude'] : null;
                                    $tl = is_numeric($r['latitude'] ?? null) ? (float)$r['latitude'] : null;
                                    $tg = is_numeric($r['longitude'] ?? null) ? (float)$r['longitude'] : null;
                                    if ($fl !== null && $fg !== null && $tl !== null && $tg !== null) {
                                        $rad = 6371000.0;
                                        $dLat = deg2rad($tl - $fl);
                                        $dLng = deg2rad($tg - $fg);
                                        $a = sin($dLat/2)**2 + cos(deg2rad($fl))*cos(deg2rad($tl))*sin($dLng/2)**2;
                                        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                                        $dist = $rad * $c; // meters
                                        $speed = 30000/3600; // 30 km/h in m/s
                                        $sec = (int)round($dist / $speed);
                                    }
                                }
                                $dur = $format_duration($sec);
                            }

                            $locationHistory[] = [
                                'sequence' => $seq,
                                'time' => $time ?: '00:00',
                                'location' => $r['locationName'] ?? '',
                                'address' => $r['address'] ?? '',
                                'latitude' => $r['latitude'] ?? null,
                                'longitude' => $r['longitude'] ?? null,
                                'description' => $r['content'] ?? '',
                                'registered' => $reg,
                                'duration' => $dur
                            ];

                            $prev = $r;
                        }
                    }

                    // :  (  )
                    if (!empty($locationHistory)) {
                        usort($locationHistory, function ($a, $b) {
                            return (int)($b['sequence'] ?? 0) <=> (int)($a['sequence'] ?? 0);
                        });
                    }
                }
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Guide location page error: " . $e->getMessage());
    }
}

//   
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('guide_location', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <!-- Kakao Maps -->
    <script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=c9d9068a507832cb391cf6ed52897501&libraries=services&autoload=false" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/guide-location.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type1">
        <a class="btn-back" href="../home.html?lang=<?php echo $currentLang; ?>"><img src="../images/ico_close.svg"></a>
            <div class="title"></div>
            <a class="btn-bell" href="guide-notice.html?booking_id=<?php echo urlencode($bookingId); ?>">
                <img src="../images/ico_bell.svg">
                <!-- unread badge: JS  count   -->
                <i class="num" style="display:none;">0</i>
            </a>
        </header>
        <div class="px20 mt20 mb72">
            <ul class="tab-type3">
                <li><a class="btn-tab3" href="schedule.php?booking_id=<?php echo htmlspecialchars($bookingId); ?>"><?php echoI18nText('travel_schedule', $currentLang); ?></a></li>
                <li><a class="btn-tab3 active" href="guide-location.php?booking_id=<?php echo htmlspecialchars($bookingId); ?>"><?php echoI18nText('guide_location', $currentLang); ?></a></li>
            </ul>
            <div class="map-type1 mt20">
                <!-- Naver Maps -->
                <div id="map" style="width: 100%; height: 300px; background: #f0f0f0; border-radius: 14px;"></div>
                <div class="card-type4">
                    <div class="align vm both text fz14 fw600 lh22 black12">
                        <?php echoI18nText('guide_info', $currentLang); ?>
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
            <div class="align vm both text fz16 fw600 lh24 black12 mt20">
                <?php echoI18nText('history', $currentLang); ?>
            </div>
            <ul class="list-type5 mt12" id="locationHistoryList">
                <?php if ($locationHistory && is_array($locationHistory)): ?>
                    <?php foreach ($locationHistory as $index => $location): ?>
                        <li>
                            <div class="align gap14">
                                <i class="num"><?php echo (int)($location['sequence'] ?? ($index + 1)); ?></i>
                                <div>
                                    <div class="text fz18 fw500 lh26 reded"><?php echo htmlspecialchars($location['time']); ?></div>
                                    <div class="text fz14 fw600 lh22 black12 mt6"><?php echo htmlspecialchars($location['location']); ?></div>
                                    <div class="text fz13 fw400 lh19 grayb0 mt6"><?php echo htmlspecialchars($location['address']); ?></div>
                                    <div class="text fz13 fw400 lh19 black12"><?php echo htmlspecialchars($location['description']); ?></div>
                                    <p class="text fz12 fw400 lh16 grayb0 mt6"><?php echo htmlspecialchars($location['registered']); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($location['duration'])): ?>
                            <div class="text fz14 fw600 lh22 green1b align gap14 py12 mt16">
                                <i class="time"></i>
                                <?php echo htmlspecialchars($location['duration']); ?>
                            </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <div class="align center mt40">
                            <p class="text fz14 fw400 lh22 gray96"><?php echoI18nText('no_location_history', $currentLang); ?></p>
                        </div>
                    </li>
                <?php endif; ?>
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
        //   JavaScript      
        window.bookingInfo = <?php echo json_encode($bookingInfo); ?>;
        window.guideData = <?php echo json_encode($guideData); ?>;
        window.locationHistory = <?php echo json_encode($locationHistory); ?>;
        // meeting location list (today only, sequence desc)
        window.meetingLocations = <?php echo json_encode($locationHistory); ?>;
        window.currentLang = '<?php echo $currentLang; ?>';
    </script>
</body>
</html>
