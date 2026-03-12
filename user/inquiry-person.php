<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('oneOnOneInquiry', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/inquiry-person.js?v=20251218_2" defer></script>
</head>
<body>
    <div class="main bg white mh100 pb20">
        <header class="header-type2 bg white" style="box-shadow: none;">
            <a class="btn-mypage" href="javascript:history.back();"><img src="../images/ico_back_black.svg"></a>
            <div class="title" data-i18n="oneOnOneInquiry">1:1 Inquiry</div>
            <div></div>
        </header>
       
        <div class="px20 pb20 mt28">
            <ul>
                <li>
                    <label class="label-input mb6" for="txt1" data-i18n="replyEmailAddress">   <span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                    <input class="input-type1" id="txt1" type="text" data-i18n-placeholder="email" placeholder="email">
                    <div class="text fz12 fw400 lh16 reded mt4" data-i18n="invalidEmailFormat" style="display: none;">   .</div>
                </li>
                <li class="mt16">
                    <label class="label-input mb6" for="txt2" data-i18n="replyPhoneNumber">   <span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                    <div class="align vm relative">
                        <select class="select-type1" name="countryCode" id="countryCodeSelect">
                            <option value="+63">+63</option>
                        </select>
                        <input class="input-type2" id="txt2" type="tel" data-i18n-placeholder="phonePlaceholder" placeholder="'-'   ">
                    </div>
                    <div class="text fz12 fw400 lh16 reded mt4" data-i18n="invalidPhoneFormat" style="display: none;">   .</div>
                </li>
                <li class="mt16">
                    <label class="label-input mb6" for="" data-i18n="inquiryType"> <span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                    <div class="custom-select">
                        <div class="select-trigger">
                            <span class="placeholder gray" data-i18n="selectInquiryType">  </span>
                        </div>
                        <ul class="select-options" style="display: none;">
                            <li data-value="product" data-i18n="productInquiry">Product Inquiry</li>
                            <li data-value="reservation" data-i18n="reservationInquiry">Reservation Inquiry</li>
                            <li data-value="payment" data-i18n="paymentInquiry">Payment Inquiry</li>
                            <li data-value="cancellation" data-i18n="cancellationInquiry">Cancellation Inquiry</li>
                            <li data-value="other" data-i18n="otherInquiry">Other</li>
                        </ul>
                    </div>
                </li>
                <li class="mt16">
                    <label class="label-input mb6" for="txt3" data-i18n="inquiryContent"> <span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                    <input class="input-type1" id="txt3" type="text" data-i18n-placeholder="enterTitle" placeholder=" ">
                    <textarea class="textarea-type1 mt16" name="" id="txt4" cols="30" rows="10" data-i18n-placeholder="enterContent" placeholder=" "></textarea>
                </li>
                <li class="mt16">
                    <label class="label-input mb6" for="" data-i18n="fileAttachment"></label>
                    <button class="btn line lg ico5 mt10" type="button" id="uploadBtn" data-i18n="upload"></button>
                    <input id="inquiryFiles" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.pdf" style="display:none;">
                    <ul id="selectedFilesList" class="mt10" style="list-style:none; padding-left:0;"></ul>
                </li>
            </ul>
            <div class="text fz12 fw400 lh16 gray96 mt10" data-i18n="fileUploadLimit">*     5  </div>
            <div class="text fz12 fw400 lh16 gray96" data-i18n="fileFormatLimit">* JPG, JPEG, PNG, GIF, PDF    ,   10MB  .</div>
            <div class="mt98">
                <button class="btn primary lg inactive" type="button" data-i18n="register"></button>
            </div>
        </div>
    </div>

    <!--    (:  Case 1) -->
    <div id="inquirySuccessModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
        <div style="width:min(320px, calc(100% - 48px)); background:#fff; border-radius:12px; padding:20px 18px; box-shadow:0 10px 30px rgba(0,0,0,0.18); text-align:center;">
            <div class="text fz16 fw600 lh22 black12" data-i18n="inquirySuccessTitle"> </div>
            <div class="text fz13 fw400 lh18 gray96 mt8" data-i18n="inquirySuccessDesc">    .</div>
            <div class="mt16">
                <button type="button" id="inquirySuccessOkBtn" class="btn line lg w100" data-i18n="confirm"></button>
            </div>
        </div>
    </div>
</body>
</html>


