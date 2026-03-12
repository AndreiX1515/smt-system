<?php
require_once '../backend/i18n_helper.php';

//   
$currentLang = getCurrentLanguage();

// URL    
$travelerType = $_GET['type'] ?? 'adult';
$travelerLabel = $_GET['label'] ?? '';
$travelerIndex = $_GET['index'] ?? '1';
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('enter_traveler_info', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/button.js" defer></script>
    <script src="../js/passport_upload.js" defer></script>
    <script src="../js/enter-traveler-info.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../js/enter-traveler-info.js')); ?>" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="#none"><img src="../images/ico_close_black.svg"></a>
            <div class="title"><?php echoI18nText('enter_traveler_info', $currentLang); ?></div>
            <div></div>
        </header>
           <div class="px20 mt16">
            <div class="text fz16 fw600 lh24 black12">
                <?php
                    $label = trim((string)$travelerLabel);
                    if ($label !== '') {
                        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . 'x' . htmlspecialchars((string)$travelerIndex, ENT_QUOTES, 'UTF-8');
                    } else {
                        echoI18nText($travelerType, $currentLang);
                        echo htmlspecialchars((string)$travelerIndex, ENT_QUOTES, 'UTF-8');
                    }
                ?>
            </div>
            <ul>
                <li>
                    <div class="text fz14 fw500 lh22 gray6b mt12"><?php echoI18nText('title', $currentLang); ?></div>
                    <div class="align vm gap10 mt6">
                        <button class="btn line lg btn-title active" type="button">MS</button>
                        <button class="btn line lg btn-title" type="button">MR</button>
                    </div>
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt1"><?php echoI18nText('first_name', $currentLang); ?></label>
                    <input class="input-type1" id="txt1" type="text" placeholder="<?php echoI18nText('first_name', $currentLang); ?>">
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt2"><?php echoI18nText('last_name', $currentLang); ?></label>
                    <input class="input-type1" id="txt2" type="text" placeholder="<?php echoI18nText('last_name', $currentLang); ?>">
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt3"><?php echoI18nText('age', $currentLang); ?></label>
                    <input class="input-type1" id="txt3" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="<?php echoI18nText('enter_number', $currentLang); ?>">
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt4"><?php echoI18nText('birth_date', $currentLang); ?></label>
                    <input class="input-type1" id="txt4" type="number" placeholder="YYYYMMDD">
                    <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('invalid_format', $currentLang); ?></div>
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for=""><?php echoI18nText('gender', $currentLang); ?></label>
                    <div class="align vm gap10 mt6">
                        <button class="btn line lg btn-gender active" type="button"><?php echoI18nText('male', $currentLang); ?></button>
                        <button class="btn line lg btn-gender" type="button"><?php echoI18nText('female', $currentLang); ?></button>
                    </div>
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt5"><?php echoI18nText('nationality', $currentLang); ?></label>
                    <input class="input-type1" id="txt5" type="text" placeholder="<?php echoI18nText('nationality', $currentLang); ?>">
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt6"><?php echoI18nText('passport_number', $currentLang); ?></label>
                    <input class="input-type1" id="txt6" type="text" placeholder="P1234567">
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt7"><?php echoI18nText('passport_issue_date', $currentLang); ?></label>
                    <input class="input-type1" id="txt7" type="number" placeholder="YYYYMMDD">
                    <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('invalid_format', $currentLang); ?></div>
                </li>
                <li class="mt20">
                    <label class="label-input mb6" for="txt8"><?php echoI18nText('passport_expiry_date', $currentLang); ?></label>
                    <input class="input-type1" id="txt8" type="number" placeholder="YYYYMMDD">
                    <div class="text fz12 fw400 lh16 reded mt4" style="display: none;"><?php echoI18nText('invalid_format', $currentLang); ?></div>
                </li>
                <li class="mt20">
                    <div class="upload-box">
                        <label for="passportUpload" class="upload-label">
                            <span class="upload-icon"><img src="../images/ico_upload_black.svg" alt=""></span>
                            <span class="upload-text"><?php echoI18nText('upload', $currentLang); ?></span>
                        </label>
                        <input type="file" id="passportUpload" hidden />
                    </div>
                    <img id="passportPreview" style="margin-top:10px; max-width:100%; display:none;" />
                </li>
                <li class="mt20">
                    <div class="text fz14 fw500 lh22 gray6b mt12"><?php echoI18nText('visa_application', $currentLang); ?></div>
                    <div class="align vm gap10 mt6">
                        <button class="btn line lg btn-visa active" type="button"><?php echoI18nText('apply', $currentLang); ?></button>
                        <button class="btn line lg btn-visa" type="button"><?php echoI18nText('not_apply', $currentLang); ?></button>
                    </div>
                </li>
            </ul>
            <div class="mt88 pb12">
                <button class="btn primary lg inactive" id="next-btn" type="button" style="pointer-events: none;"><?php echoI18nText('next', $currentLang); ?></button>
            </div>
            <!--    -->
            <div id="toast-container" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 1000; display: none;"></div>
            
       </div>
        
    </div>
</body>
</html>