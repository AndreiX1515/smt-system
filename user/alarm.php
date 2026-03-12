<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('alarm', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/tab.js" defer></script>
    <script src="../js/button.js" defer></script>
    <script src="../js/alarm.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2" style="box-shadow: none;">
            <a class="btn-mypage" href="/home.html"><img src="../images/ico_back_black.svg"></a>
            <div class="title">Notification</div>
            <div></div>
        </header>
       
       <div>
            <ul class="tab-type2 px20" style="box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.06);">
                <li><a class="btn-tab2 btn-alarmtab active" href="#entire" data-i18n="all"><?php echoI18nText('all', $currentLang); ?></a></li>
                <li><a class="btn-tab2 btn-alarmtab" href="#reservation_schedule" data-i18n="reservation_schedule"><?php echoI18nText('reservation_schedule', $currentLang); ?></a></li>
                <li><a class="btn-tab2 btn-alarmtab" href="#visa" data-i18n="visa"><?php echoI18nText('visa', $currentLang); ?></a></li>
            </ul>
            <ul class="tab-content mt20" id="entire">
                <!--   JavaScript   -->
            </ul>
            <ul class="tab-content mt20" id="reservation_schedule" style="display: none;">
                <!--   JavaScript   -->
            </ul>
            <ul class="tab-content mt20" id="visa" style="display: none;">
                <!--   JavaScript   -->
            </ul>
       </div>

    </div>
</body>
</html>


