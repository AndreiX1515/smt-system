<?php
require_once '../backend/i18n_helper.php';
require_once '../backend/conn.php';

//   
$currentLang = getCurrentLanguage();

// SMT   - booking_id     ( )
$bookingId = $_GET['booking_id'] ?? '';
$packageId = $_GET['package_id'] ?? '';
$departureDate = $_GET['departure_date'] ?? '';
$departureTime = $_GET['departure_time'] ?? '';
$packageName = urldecode($_GET['package_name'] ?? '');
$packagePrice = $_GET['package_price'] ?? '0';
$adults = 0;
$children = 0;
$infants = 0;
$dbLoadSuccess = false;

if (!empty($bookingId)) {
    $stmt = $conn->prepare("
        SELECT packageId, packageName, packagePrice, departureDate, departureTime, adults, children, infants
        FROM bookings
        WHERE bookingId = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $res = $stmt->get_result();
        $booking = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($booking) {
            $packageId = $booking['packageId'];
            $packageName = $booking['packageName'] ?? '';
            $packagePrice = $booking['packagePrice'] ?? '0';
            $departureDate = $booking['departureDate'];
            $departureTime = $booking['departureTime'] ?? '';
            $adults = (int)($booking['adults'] ?? 0);
            $children = (int)($booking['children'] ?? 0);
            $infants = (int)($booking['infants'] ?? 0);
            $dbLoadSuccess = true;
        }
    }
}
// SMT  

//    formatDate 
$departureDateTime = $departureDate;
if (!empty($departureDate) && !empty($departureTime)) {
    $departureDateTime = $departureDate . ' ' . $departureTime;
}

//    :
// -   :    (package_available_dates.departure_time > flight.flightDepartureTime > package_flights.departure_time)
// -   : (packages.meeting_time/meetingTime)
try {
    $resolveTime = function ($conn, $packageId, $departureDate, $fallback) {
        $pid = (int)$packageId;
        $dt = trim((string)$departureDate);
        $fb = trim((string)$fallback);
        if ($pid <= 0 || $dt === '') return $fb;

        // 1) package_available_dates.departure_time ()
        try {
            $tbl = $conn->query("SHOW TABLES LIKE 'package_available_dates'");
            if ($tbl && $tbl->num_rows > 0) {
                $st = $conn->prepare("SELECT departure_time FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                if ($st) {
                    $st->bind_param('is', $pid, $dt);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $t = substr(trim((string)($row['departure_time'] ?? '')), 0, 5);
                    if ($t !== '') return $t;
                }
            }
        } catch (Throwable $_) { }

        // 2) flight.flightDepartureTime ()
        try {
            $tbl = $conn->query("SHOW TABLES LIKE 'flight'");
            if ($tbl && $tbl->num_rows > 0) {
                $st = $conn->prepare("SELECT flightDepartureTime FROM flight WHERE packageId = ? AND is_active = 1 AND DATE(flightDepartureDate) = ? LIMIT 1");
                if ($st) {
                    $st->bind_param('is', $pid, $dt);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $t = substr(trim((string)($row['flightDepartureTime'] ?? '')), 0, 5);
                    if ($t !== '') return $t;
                }
            }
        } catch (Throwable $_) { }

        // 3) package_flights.departure_time ()
        try {
            $tbl = $conn->query("SHOW TABLES LIKE 'package_flights'");
            if ($tbl && $tbl->num_rows > 0) {
                $st = $conn->prepare("SELECT departure_time FROM package_flights WHERE package_id = ? AND flight_type = 'departure' LIMIT 1");
                if ($st) {
                    $st->bind_param('i', $pid);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $raw = trim((string)($row['departure_time'] ?? ''));
                    $t = '';
                    if (preg_match('/^\d{2}:\d{2}/', $raw)) $t = substr($raw, 0, 5);
                    else if (preg_match('/\b(\d{2}:\d{2})\b/', $raw, $m)) $t = $m[1];
                    if ($t !== '') return $t;
                }
            }
        } catch (Throwable $_) { }

        // 4) packages meeting_time/meetingTime ()
        try {
            $st = $conn->prepare("SELECT meeting_time, meetingTime FROM packages WHERE packageId = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $pid);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $raw = trim((string)($row['meeting_time'] ?? ''));
                if ($raw === '') $raw = trim((string)($row['meetingTime'] ?? ''));
                $t = substr($raw, 0, 5);
                if ($t !== '') return $t;
            }
        } catch (Throwable $_) { }

        return $fb;
    };

    $departureTime = $resolveTime($conn, $packageId, $departureDate, $departureTime);
    $departureDateTime = (!empty($departureDate) && !empty($departureTime)) ? ($departureDate . ' ' . $departureTime) : $departureDate;
} catch (Throwable $_) { }
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('select_guests', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <!-- SMT   - PHP    JavaScript  -->
    <script>
        window.phpBookingData = {
            bookingId: <?php echo json_encode($bookingId); ?>,
            packageId: <?php echo json_encode($packageId); ?>,
            packageName: <?php echo json_encode($packageName); ?>,
            packagePrice: <?php echo json_encode($packagePrice); ?>,
            departureDate: <?php echo json_encode($departureDate); ?>,
            departureTime: <?php echo json_encode($departureTime); ?>,
            adults: <?php echo json_encode($adults); ?>,
            children: <?php echo json_encode($children); ?>,
            infants: <?php echo json_encode($infants); ?>,
            dbLoadSuccess: <?php echo json_encode($dbLoadSuccess); ?>
        };
    </script>
    <!-- SMT   -->
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/booking.js" defer></script>
    <script src="../js/select-reservation.js?v=<?php echo time(); ?>" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="#none" aria-label="<?php echoI18nText('back', $currentLang); ?>"><img src="../images/ico_back_black.svg" alt=""></a>
            <h1 class="title"><?php echoI18nText('book_now', $currentLang); ?></h1>
            <div></div>
        </header>
        <div class="mt16 px20 pb24 border-bottom10">
            <div class="progress-type1">
                <i class="active"></i>
                <i></i>
                <i></i>
                <i></i>
                <i></i>
            </div>
            <div class="text fz14 fw600 lh22 black12 mt8"><?php echo formatDate($departureDateTime, $currentLang); ?> <span class="text fw 700"><?php echoI18nText('departure', $currentLang); ?></span></div>
            <h3 class="text fz20 fw600 lh28 black12 mt24"><?php echoI18nText('select_guests_title', $currentLang); ?></h3>

            <!-- 인원 옵션은 DB(pricingOptions) 기준으로 JS에서 동적으로 렌더링 -->
            <ul class="mt32" id="guestOptionsList"></ul>
        </div>
        <div class="px20 mt24">
            <!-- 선택한 인원 옵션 요약은 JS에서 동적으로 렌더링 -->
            <div id="guestSummaryList"></div>
            <div class="mt24 align both vm">
                <div class="text fz16 fw600 lh24 black12"><?php echoI18nText('total_amount', $currentLang); ?></div>
                <strong class="text fz20 fw600 lh28 black12 total-amount">₱0</strong>
            </div>
            <p class="align right mt4 text fz14 fw400 grayb0 lh140"><?php echoI18nText('fees_included', $currentLang); ?></p>

            <div class="mt88 mb12">
                <a class="btn primary lg next-btn" href="javascript:void(0);"><?php echoI18nText('next', $currentLang); ?></a>
            </div>
        </div>
    </div>

    <!-- Out of Stock Popup -->
    <div class="layer" id="stockLayer"></div>
    <div class="alert-modal" id="stockPopup" style="display:none;">
        <div class="guide" id="stockPopupMsg">Out of stock</div>
        <div class="guide-sub">Unable to purchase due to insufficient stock.</div>
        <div class="align center mt32">
            <button class="btn" type="button" id="stockPopupOkBtn" style="width: 120px; height: 46px; background: #fff; border: 1px solid #b0b0b0; border-radius: 4px; color: #4e4e4e; font-size: 14px; font-weight: 500;"><?php echoI18nText('confirm', $currentLang); ?></button>
        </div>
    </div>
</body>
</html>