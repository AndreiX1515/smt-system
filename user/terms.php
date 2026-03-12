<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('termsOfUse', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/api.js" defer></script>
    <script src="../js/company-info.js" defer></script>
    <script src="../js/button.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/terms-page.js" defer></script>
</head>
<body>
    <div class="main bg white mh100 pb20">
        <header class="header-type2 bg white">
            <a class="btn-mypage" href="javascript:history.back();"><img src="../images/ico_back_black.svg"></a>
            <div class="title"></div>
            <div></div>
        </header>
       
        <div class="px20 pb20 mt20">
            <div class="text fz16 fw600 lh24 black12 mb12" id="termsTitle"></div>
            <div class="text fz14 fw400 lh22 black12" id="termsContent">  ...</div>
        </div>
    </div>
</body>
</html>


