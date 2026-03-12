<?php
require_once '../backend/i18n_helper.php';

//   
$currentLang = getCurrentLanguage();

// URL    
$bookingId = $_GET['booking_id'] ?? '';
$packageId = $_GET['package_id'] ?? '';
$departureDate = $_GET['departure_date'] ?? '';
$departureTime = $_GET['departure_time'] ?? '';
$packageName = urldecode($_GET['package_name'] ?? '');
$packagePrice = $_GET['package_price'] ?? '0';
$adults = $_GET['adults'] ?? '1';
$children = $_GET['children'] ?? '0';
$infants = $_GET['infants'] ?? '0';
$totalAmount = $_GET['total_amount'] ?? '0';
$selectedRooms = $_GET['selected_rooms'] ?? '{}';

// booking_id  DB  
if (!empty($bookingId)) {
    require_once '../backend/conn.php';
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE bookingId = ?");
    $stmt->bind_param("s", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        $packageId = $booking['packageId'];
        $departureDate = $booking['departureDate'];
        $departureTime = $booking['departureTime'] ?: '';
        $packageName = $booking['packageName'] ?: $packageName;
        $packagePrice = $booking['packagePrice'] ?: $packagePrice;
        $adults = $booking['adults'] ?: $adults;
        $children = $booking['children'] ?: $children;
        $infants = $booking['infants'] ?: $infants;
        $totalAmount = $booking['totalAmount'] ?: $totalAmount;
    }
    $stmt->close();
}

//    (  ):
// -   :    (package_available_dates.departure_time > flight.flightDepartureTime > package_flights.departure_time)
// -   : (packages.meeting_time/meetingTime)
try {
    if (!empty($packageId) && !empty($departureDate) && isset($conn) && $conn) {
        $pid = (int)$packageId;
        $dt = trim((string)$departureDate);
        $fallback = trim((string)$departureTime);
        $resolved = '';

        $tbl = $conn->query("SHOW TABLES LIKE 'package_available_dates'");
        if ($tbl && $tbl->num_rows > 0) {
            $st = $conn->prepare("SELECT departure_time FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
            if ($st) {
                $st->bind_param('is', $pid, $dt);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $resolved = substr(trim((string)($row['departure_time'] ?? '')), 0, 5);
            }
        }

        if ($resolved === '') {
            $tbl = $conn->query("SHOW TABLES LIKE 'flight'");
            if ($tbl && $tbl->num_rows > 0) {
                $st = $conn->prepare("SELECT flightDepartureTime FROM flight WHERE packageId = ? AND is_active = 1 AND DATE(flightDepartureDate) = ? LIMIT 1");
                if ($st) {
                    $st->bind_param('is', $pid, $dt);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $resolved = substr(trim((string)($row['flightDepartureTime'] ?? '')), 0, 5);
                }
            }
        }

        if ($resolved === '') {
            $tbl = $conn->query("SHOW TABLES LIKE 'package_flights'");
            if ($tbl && $tbl->num_rows > 0) {
                $st = $conn->prepare("SELECT departure_time FROM package_flights WHERE package_id = ? AND flight_type = 'departure' LIMIT 1");
                if ($st) {
                    $st->bind_param('i', $pid);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $raw = trim((string)($row['departure_time'] ?? ''));
                    if (preg_match('/^\d{2}:\d{2}/', $raw)) $resolved = substr($raw, 0, 5);
                    else if (preg_match('/\b(\d{2}:\d{2})\b/', $raw, $m)) $resolved = $m[1];
                }
            }
        }

        if ($resolved === '') {
            $st = $conn->prepare("SELECT meeting_time, meetingTime FROM packages WHERE packageId = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $pid);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $raw = trim((string)($row['meeting_time'] ?? ''));
                if ($raw === '') $raw = trim((string)($row['meetingTime'] ?? ''));
                $resolved = substr($raw, 0, 5);
            }
        }

        $departureTime = $resolved !== '' ? $resolved : $fallback;
    }
} catch (Throwable $_) { }
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('enter_customer_info', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/button.js" defer></script>
    <script src="../js/enter-customer-info.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../js/enter-customer-info.js')); ?>" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="#none"><img src="../images/ico_back_black.svg"></a>
            <div class="title"></div>
            <div></div>
        </header>
        <div class="mt16 px20 pb24 border-bottom10">
            <div class="progress-type1">
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
                <i></i>
                <i></i>
            </div>
            <div class="text fz14 fw600 lh22 black12 mt8 departure-info"><?php 
                $departureDateTime = (!empty($departureDate) && !empty($departureTime)) ? ($departureDate . ' ' . $departureTime) : $departureDate;
                echo formatDate($departureDateTime, 'Y-m-d H:i', $currentLang); 
            ?> <span class="text fw 700"><?php echoI18nText('departure', $currentLang); ?></span></div>
            <h3 class="text fz20 fw600 lh28 black12 mt24"><?php echoI18nTextHTML('enter_customer_info_title', $currentLang); ?></h3>

            <div class="mt32">
                <h3 class="text fz16 fw600 lh24 black12"><?php echoI18nText('booker_info', $currentLang); ?></h3>
                <div class="align right"> 
                    <label class="align vm" for="chk1">
                        <input type="checkbox" id="chk1" value="">
                        <p class="text fz12 fw500 lh16 gray96 ml4"><?php echoI18nText('load_account_info', $currentLang); ?></p>
                    </label>
                </div>
                <ul class="mt12">
                    <li>
                        <label class="label-input mb6" for="txt1"><?php echoI18nText('name', $currentLang); ?></label>
                        <input class="input-type1" id="txt1" type="text" placeholder="<?php echoI18nText('name', $currentLang); ?>" autocomplete="off">
                        <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('name_required', $currentLang); ?></div>
                    </li>
                    <li class="mt16">
                        <label class="label-input mb6" for="email"><?php echoI18nText('email', $currentLang); ?></label>
                        <input class="input-type1" id="email" type="email" placeholder="<?php echoI18nText('email', $currentLang); ?>" autocomplete="off">
                        <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('email_invalid', $currentLang); ?></div>
                    </li>
                    <li class="mt16">
                        <label class="label-input mb6" for="phone"><?php echoI18nText('phone', $currentLang); ?></label>
                        <div class="align vm relative">
                            <select class="select-type1" name="" id="">
                                <option value="">+63</option>
                            </select>
                            <input class="input-type2" id="phone" type="text" placeholder="<?php echoI18nText('phone_placeholder', $currentLang); ?>" autocomplete="off">
                        </div>
                        <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('phone_invalid', $currentLang); ?></div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="px20 pt24">
            <h3 class="text fz16 fw600 lh24 black12"><?php echoI18nText('traveler_info', $currentLang); ?></h3>
            <ul class="mt12 traveler-list">
                <!-- JavaScript   -->
            </ul>
            <div class="mt50 pb12">
                <a class="btn primary lg inactive" id="next-btn" href="javascript:void(0);" style="pointer-events: none;"><?php echoI18nText('next', $currentLang); ?></a>
            </div>
        </div>
        
    </div>
</body>
</html>