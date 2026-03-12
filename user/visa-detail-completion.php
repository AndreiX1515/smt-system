<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('smart_travel', $currentLang); ?> | <?php echoI18nText('visaApplicationDetailIssued', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/button.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/visa-detail-completion.js" defer></script>
</head>
<body>
    <div class="main bg white mh100">
        <header class="header-type2 bg white">
            <a class="btn-mypage" href="#none"><img src="../images/ico_back_black.svg"></a>
            <div class="title"></div>
            <div></div>
        </header>
       
        <div class="px20 pb20 mt24">
            <div class="text fz20 fw600 lh28 black12" data-i18n="visaIssuanceCompleted">
                <?php echoI18nTextHTML('visaIssuanceCompleted', $currentLang); ?><br>
                <span data-i18n="pleaseCheckIssuedVisa"><?php echoI18nText('pleaseCheckIssuedVisa', $currentLang); ?></span>
            </div>
            <div class="fixed-bottom px20">
                <button class="btn primary lg ico1" type="button" data-i18n="visaFileDownload"><?php echoI18nText('visaFileDownload', $currentLang); ?></button>
            </div>
        </div>
       
</body>
</html>