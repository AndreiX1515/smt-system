<?php
require_once '../backend/i18n_helper.php';

//   
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('reservation_history', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/button.js" defer></script>
    <script src="../js/reservation-history.js?v=<?php echo filemtime('../js/reservation-history.js'); ?>" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main bg grayfa mh100">
        <header class="header-type2 bg white">
            <a class="btn-mypage" href="mypage.html"><img src="../images/ico_back_black.svg" alt=""></a>
            <div class="title">Reservation Details</div>
            <div></div>
        </header>
        <ul class="tab-type2 bg white px20">
            <li><a class="btn-tab2 active" href="#none">Scheduled<span class="tab-count">0</span></a></li>
            <li><a class="btn-tab2" href="#none">Past<span class="tab-count">0</span></a></li>
            <li><a class="btn-tab2" href="#none">Canceled<span class="tab-count">0</span></a></li>
        </ul>
        <div class="px20 mt24" id="intended">
            <!--   Scheduled     -->
        </div>

        <div class="px20 mt24" id="past">
            <!--   Past     -->
        </div>

        <div class="px20 mt24" id="canceled">
            <!--   Canceled     -->
        </div>
        <div class="px20 pb20">
            <div class="card-type8 gray mt16">
                <div class="align vm both">
                    <div class="align vm">
                        <img src="../images/ico_call.svg" alt="">
                    <div class="text fz14 fw500 lh20 black12 ml8">Do you have any questions?</div>
                </div>
                <a class="text fz14 fw500 lh22 reded" href="inquiry.php">Customer Support<img src="../images/ico_arrow_right_red.svg" alt=""></a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>