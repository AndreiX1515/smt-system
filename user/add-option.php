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
$adults = $_GET['adults'] ?? '0';
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
        $packageId = isset($booking['packageId']) ? $booking['packageId'] : $packageId;
        $departureDate = isset($booking['departureDate']) ? $booking['departureDate'] : $departureDate;
        $departureTime = (isset($booking['departureTime']) && $booking['departureTime'] !== null) ? (string)$booking['departureTime'] : (string)$departureTime;
        if (isset($booking['packageName']) && $booking['packageName'] !== null && $booking['packageName'] !== '') $packageName = $booking['packageName'];
        if (isset($booking['packagePrice']) && $booking['packagePrice'] !== null && $booking['packagePrice'] !== '') $packagePrice = $booking['packagePrice'];
        if (array_key_exists('adults', $booking)) $adults = (string)$booking['adults'];
        if (array_key_exists('children', $booking)) $children = (string)$booking['children'];
        if (array_key_exists('infants', $booking)) $infants = (string)$booking['infants'];
        if (isset($booking['totalAmount']) && $booking['totalAmount'] !== null && $booking['totalAmount'] !== '') $totalAmount = (string)$booking['totalAmount'];
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
    <title><?php echoI18nText('add_options', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/button.js" defer></script>
    <script src="../js/select.js" defer></script>
    <script src="../js/add-option.js?v=20251218_3" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="#none"><img src="../images/ico_back_black.svg"></a>
            <div class="title"><?php echoI18nText('reservation', $currentLang); ?></div>
            <div></div>
        </header>
        <div class="mt16 px20 pb24">
            <div class="progress-type1">
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
                <i></i>
            </div>
            <div class="text fz14 fw600 lh22 black12 mt8 departure-info"><?php 
                $departureDateTime = (!empty($departureDate) && !empty($departureTime)) ? ($departureDate . ' ' . $departureTime) : $departureDate;
                echo formatDate($departureDateTime, 'Y-m-d H:i', $currentLang); 
            ?> <span class="text fw 700"><?php echoI18nText('departure', $currentLang); ?></span></div>
            <h3 class="text fz20 fw600 lh28 black12 mt24"><?php echoI18nTextHTML('add_options_title', $currentLang); ?></h3>

            <div class="mt32">
                <ul class="mt12">
                   <li>
                        <label class="label-input mb6" for=""><?php echoI18nText('extra_luggage', $currentLang); ?></label>
                        <div class="custom-select">
                            <div class="select-trigger">
                                <span class="placeholder"><?php echoI18nText('not_selected', $currentLang); ?></span>
                            </div>
                            <ul class="select-options" style="display: none;">
                                <li><?php echoI18nText('option_1', $currentLang); ?></li>
                                <li><?php echoI18nText('option_2', $currentLang); ?></li>
                                <li><?php echoI18nText('option_3', $currentLang); ?></li>
                            </ul>
                        </div>
                   </li>
                   <li class="mt20">
                        <label class="label-input mb6" for=""><?php echoI18nText('breakfast_request', $currentLang); ?></label>
                        <div class="align vm gap10 mt6">
                            <button class="btn line lg btn-apply active" type="button"><?php echoI18nText('apply', $currentLang); ?></button>
                            <button class="btn line lg btn-apply" type="button"><?php echoI18nText('not_apply', $currentLang); ?></button>
                        </div>
                   </li>
                   <li class="mt20">
                        <label class="label-input mb6" for=""><?php echoI18nText('wifi_rental', $currentLang); ?></label>
                        <div class="align vm gap10 mt6">
                            <button class="btn line lg btn-wifi active" type="button"><?php echoI18nText('apply', $currentLang); ?></button>
                            <button class="btn line lg btn-wifi" type="button"><?php echoI18nText('not_apply', $currentLang); ?></button>
                        </div>
                   </li>
                   <li class="mt20">
                        <label class="label-input mb6" for=""><?php echoI18nText('seat_request', $currentLang); ?></label>
                        <textarea class="textarea-type2" name="seatRequest" id="seatRequest" cols="30" rows="10" placeholder="<?php echoI18nText('enter_content', $currentLang); ?>"></textarea>
                    </li>
                   <li class="mt20">
                        <label class="label-input mb6" for=""><?php echoI18nText('other_requests', $currentLang); ?></label>
                        <textarea class="textarea-type2" name="otherRequest" id="otherRequest" cols="30" rows="10" placeholder="<?php echoI18nText('enter_content', $currentLang); ?>"></textarea>
                    </li>
                </ul>
                <div class="mt88 pb12">
                    <a class="btn primary lg" id="next-btn" href="javascript:void(0);"><?php echoI18nText('next', $currentLang); ?></a>
                </div>
            </div>
        </div>
        
    </div>
</body>
</html>