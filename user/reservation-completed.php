<?php
require_once '../backend/i18n_helper.php';
require_once '../backend/conn.php';

//   
$currentLang = getCurrentLanguage();

// URL   ID 
$bookingId = $_GET['bookingId'] ?? '';

// bookingId 'undefined'   
if ($bookingId === 'undefined' || $bookingId === '') {
    $bookingId = '';
}

//    
$bookingInfo = null;
if ($bookingId && $bookingId !== 'undefined') {
    try {
        //    
        $sql = "SELECT 
                    b.bookingId,
                    b.packageId,
                    b.totalAmount,
                    b.departureDate,
                    b.departureTime,
                    b.adults,
                    b.children,
                    b.infants,
                    b.packageName,
                    b.packagePrice,
                    p.product_images,
                    COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) as duration_days
                FROM bookings b
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE b.bookingId = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bookingInfo = $result->fetch_assoc();

            // Status label (B2C unified wording)
            $bs = strtolower(trim((string)($bookingInfo['bookingStatus'] ?? '')));
            $ps = strtolower(trim((string)($bookingInfo['paymentStatus'] ?? '')));
            $statusLabel = 'Payment Suspended';
            if ($bs === 'completed') $statusLabel = 'Trip Completed';
            else if ($ps === 'refunded' || $bs === 'refund_completed') $statusLabel = 'Refund Completed';
            else if ($ps === 'failed' || $bs === 'cancelled' || $bs === 'canceled') $statusLabel = 'Payment Canceled';
            else if ($ps === 'paid') $statusLabel = 'Payment Completed';
            else $statusLabel = 'Payment Suspended';
            $bookingInfo['statusLabel'] = $statusLabel;
            
            // return_date  (departureDate + duration_days)
            if (!empty($bookingInfo['departureDate']) && !empty($bookingInfo['duration_days'])) {
                $departureTimestamp = strtotime($bookingInfo['departureDate']);
                $returnTimestamp = strtotime('+' . ($bookingInfo['duration_days'] - 1) . ' days', $departureTimestamp);
                $bookingInfo['return_date'] = date('Y-m-d', $returnTimestamp);
                $bookingInfo['return_time'] = '19:05:00'; // ,  
            } else {
                $bookingInfo['return_date'] = '';
                $bookingInfo['return_time'] = '';
            }
            
            // duration  (5D4N )
            if (!empty($bookingInfo['duration_days'])) {
                $nights = max(1, $bookingInfo['duration_days'] - 1);
                $bookingInfo['duration'] = $bookingInfo['duration_days'] . 'D' . $nights . 'N';
            } else {
                $bookingInfo['duration'] = 'N/A';
            }
            
            // reservation_number bookingId 
            $bookingInfo['reservation_number'] = $bookingInfo['bookingId'];
            
            // packageName   
            if (empty($bookingInfo['packageName']) && !empty($bookingInfo['packageId'])) {
                $packageSql = "SELECT packageName FROM packages WHERE packageId = ?";
                $packageStmt = $conn->prepare($packageSql);
                $packageStmt->bind_param("i", $bookingInfo['packageId']);
                $packageStmt->execute();
                $packageResult = $packageStmt->get_result();
                if ($packageRow = $packageResult->fetch_assoc()) {
                    $bookingInfo['packageName'] = $packageRow['packageName'];
                }
                $packageStmt->close();
            }
        } else {
            //     
            $bookingInfo = [
                'bookingId' => $bookingId,
                'packageName' => 'Package Not Found',
                'packagePrice' => 0,
                'reservation_number' => 'N/A',
                'departureDate' => '',
                'departureTime' => '',
                'return_date' => '',
                'return_time' => '',
                'duration' => 'N/A',
                'adults' => 0,
                'children' => 0,
                'infants' => 0,
                'totalAmount' => 0,
                'product_images' => null
            ];
        }
    } catch (Exception $e) {
        //     
        $bookingInfo = [
            'bookingId' => $bookingId,
            'packageName' => 'Error loading package info',
            'packagePrice' => 0,
            'reservation_number' => 'N/A',
            'departureDate' => '',
            'departureTime' => '',
            'return_date' => '',
            'return_time' => '',
            'duration' => 'N/A',
            'adults' => 0,
            'children' => 0,
            'infants' => 0,
            'totalAmount' => 0,
            'product_images' => null
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('reservation', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/button.js" defer></script>
    <script src="../js/check.js" defer></script>
    <script src="../js/reservation-completed.js" defer></script>
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
        <div class="mt44 px20 pb24 border-bottom10">
            <img class="img-check" src="../images/img_check_green.svg" alt="">
            <p class="text fz20 fw600 lh28 black12 txt-center mt16"><?php echoI18nTextHTML('reservation_completed', $currentLang); ?></p>
            <p class="text fz16 fw400 lh24 black12 txt-center mt8"><?php echoI18nText('complete_deposit', $currentLang); ?></p>
            <?php if (!empty($bookingInfo['statusLabel'])): ?>
                <div class="txt-center mt8">
                    <span class="label secondary"><?php echo htmlspecialchars((string)$bookingInfo['statusLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            
            <!--    -->
            <div class="mt24 p16" style="background: #F5F5F5; border-radius: 8px;">
                <div class="text fz14 fw600 lh22 black12 mb8"><?php echoI18nText('deposit_account_info', $currentLang); ?></div>
                <div class="text fz14 fw400 lh22 black12"><?php 
                    //    (   )
                    // $bankName = getI18nText('bank_name', $currentLang);
                    // $accountNumber = '123-456789-00-000';
                    // $accountHolder = getI18nText('account_holder', $currentLang);
                    // echo htmlspecialchars($bankName . ' ' . $accountNumber . ' (' . getI18nText('account_holder_label', $currentLang) . ': ' . $accountHolder . ')');
                    echo htmlspecialchars('BDO Bank 007800151678 (Account holder: Jedkim)');
                ?></div>
            </div>
            
            <div class="align both vm gap12 mt24">
                <a class="btn line lg active" href="../home.html"><?php echoI18nText('home', $currentLang); ?></a>
                <a class="btn primary lg" href="reservation-history.php"><?php echoI18nText('check_reservation_details_btn', $currentLang); ?></a>
            </div>
        </div>
        <div class="px20 mt22">
            <ul>
                <li class="pb16 border-bottomea">
                    <div class="align vm gap8">
                        <?php
                        //   
                        $packageImage = '../images/@img_thumbnail.png';
                        if (!empty($bookingInfo['product_images'])) {
                            $images = json_decode($bookingInfo['product_images'], true);
                            if (is_array($images) && !empty($images[0])) {
                                $imagePath = $images[0];
                                if (strpos($imagePath, 'http') === 0) {
                                    $packageImage = $imagePath;
                                } else {
                                    $packageImage = '../uploads/products/' . basename($imagePath);
                                }
                            }
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($packageImage); ?>" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                        <div class="mxw100 hidden">
                            <div class="text fz14 fw500 lh22 black12 ellipsis1"><?php echo htmlspecialchars($bookingInfo['packageName'] ?? 'Error loading package info'); ?></div>
                            <p class="text fz14 fw600 lh22 black12 mt4">₱<?php echo number_format($bookingInfo['totalAmount'] ?? 0); ?></p>
                        </div>
                    </div>
                    <div class="text fz14 fw600 lh22 black12 mt22"><?php echoI18nText('reservation_number', $currentLang); ?></div>
                    <p class="text fz14 lh22 black12 mt4"><?php echo htmlspecialchars($bookingId ?: ($bookingInfo['reservation_number'] ?? 'N/A')); ?></p>
                </li>
                <li class="py16 border-bottomea">
                    <div class="text fz14 fw600 lh22 black12" data-i18n="trip_dates"><?php echoI18nText('trip_dates', $currentLang); ?></div>
                    <p class="text fz14 lh22 black12 mt4">
                        <?php
                        //  /
                        if (!empty($bookingInfo['departureDate']) && !empty($bookingInfo['departureTime'])) {
                            $departureDateTime = $bookingInfo['departureDate'] . ' ' . $bookingInfo['departureTime'];
                            echo formatDate($departureDateTime, 'Y-m-d H:i', $currentLang);
                        } else {
                            echo 'N/A';
                        }
                        ?>
                        <?php if (!empty($bookingInfo['return_date']) && !empty($bookingInfo['return_time'])): ?>
                             - 
                            <?php
                            $returnDateTime = $bookingInfo['return_date'] . ' ' . $bookingInfo['return_time'];
                            echo formatDate($returnDateTime, 'Y-m-d H:i', $currentLang);
                            ?>
                        <?php endif; ?>
                        <?php if (!empty($bookingInfo['duration']) && $bookingInfo['duration'] !== 'N/A'): ?>
                            <br>(<?php echo htmlspecialchars($bookingInfo['duration']); ?>)
                        <?php endif; ?>
                    </p>
                </li>
                <li class="py16 border-bottomea">
                    <div class="text fz14 fw600 lh22 black12" data-i18n="guests"><?php echoI18nText('guests', $currentLang); ?></div>
                    <p class="text fz14 lh22 black12 mt4" id="guests-info">
                        <?php
                        $guests = [];
                        if (!empty($bookingInfo['adults']) && $bookingInfo['adults'] > 0) {
                            $guests[] = '<span data-i18n-key="adult">' . htmlspecialchars(getI18nText('adult', $currentLang)) . '</span>x' . $bookingInfo['adults'];
                        }
                        if (!empty($bookingInfo['children']) && $bookingInfo['children'] > 0) {
                            $guests[] = '<span data-i18n-key="child">' . htmlspecialchars(getI18nText('child', $currentLang)) . '</span>x' . $bookingInfo['children'];
                        }
                        if (!empty($bookingInfo['infants']) && $bookingInfo['infants'] > 0) {
                            $guests[] = '<span data-i18n-key="infant">' . htmlspecialchars(getI18nText('infant', $currentLang)) . '</span>x' . $bookingInfo['infants'];
                        }
                        echo implode(', ', $guests ?: ['N/A']);
                        ?>
                    </p>
                </li>
            </ul>
        </div>
        
    </div>
</body>
</html>