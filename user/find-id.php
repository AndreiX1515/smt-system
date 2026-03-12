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
    <title><?php echoI18nText('findId', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/api.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/find-id.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="#none"><img src="../images/ico_back_black.svg"></a>
            <div class="title">Find ID</div>
            <div></div>
        </header>

        <div class="px20">
            <div class="mt28">
                <label class="label-input mb6" for="name" data-i18n="name"><?php echoI18nText('name', $currentLang); ?></label>
                <input class="input-type1" id="name" type="text" data-i18n-placeholder="name" placeholder="<?php echoI18nText('name', $currentLang); ?>">
                <label class="label-input mb6 mt16" for="phone" data-i18n="phone"><?php echoI18nText('phone', $currentLang); ?></label>
                <div class="align vm relative">
                    <select class="select-type1" name="countryCode" id="countryCodeSelect">
                        <option value="+63">+63</option>
                    </select>
                    <input class="input-type2" id="phone" type="tel" data-i18n-placeholder="phonePlaceholder" placeholder="<?php echoI18nText('phonePlaceholder', $currentLang); ?>">
                </div>
            </div>
            <div class="fixed-bottom px20">
                <button class="btn primary lg inactive mt32" type="button" id="findIdBtn" data-i18n="findId"><?php echoI18nText('findId', $currentLang); ?></button>
            </div>
        </div>
        
        <!--     (Case 1) -->
        <div class="layer" id="noMemberLayer" style="display: none;"></div>
        <div class="alert-modal" id="noMemberPopup" style="display: none;">
            <div class="guide" data-i18n="noMemberInfo">No account matches the information you entered.</div>
            <div class="align gap12">
                <button class="btn line lg gray4e" type="button" id="noMemberOkBtn" data-i18n="confirm" style="width: 100%;">Confirm</button>
            </div>
        </div>
    </div>
</body>
</html>


