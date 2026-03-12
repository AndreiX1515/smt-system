<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('change_password', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/password.js" defer></script>
    <script src="../js/input_member.js" defer></script>
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

        <div class="px20">
            <div class="input-wrap1 mt14">
                <input class="input-type1" id="password" type="password" placeholder="<?php echoI18nText('password_placeholder', $currentLang); ?>" data-i18n-placeholder="password_placeholder">
                <button class="btn-eye" type="button"><img src="../images/ico_eye_off.svg" alt=""></button>
            </div>
            <div class="input-wrap1 mt14">
                <input class="input-type1" id="password2" type="password" placeholder="<?php echoI18nText('password_confirm_placeholder', $currentLang); ?>" data-i18n-placeholder="password_confirm_placeholder">
                <button class="btn-eye" type="button"><img src="../images/ico_eye_off.svg" alt=""></button>
            </div>
            <div class="text fz12 fw400 lh16 gray96 mt4" data-i18n="password_requirements"><?php echoI18nText('password_requirements', $currentLang); ?></div>

            <!--    -->
            <div class="text fz12 fw400 lh19 reded mt12" id="password_alert" style="display: none;" data-i18n="password_mismatch"><?php echoI18nText('password_mismatch', $currentLang); ?><br><?php echoI18nText('password_format_error', $currentLang); ?></div>

            <div class="fixed-bottom px20">
                <button class="btn primary lg inactive mt32" type="button" onclick="location.href='../home.html'" data-i18n="change_password"><?php echoI18nText('change_password', $currentLang); ?></button>
            </div>
        </div>
    </div>
</body>
</html>


