<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry | Smart Travel</title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <script src="../js/tab-type1.js" defer></script>
    <script src="../js/inquiry.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main bg white mh100 pb20">
        <header class="header-type2 bg white" style="box-shadow: none;">
            <!-- NOTE: api.js .btn-mypage + href="#none"   ,   #none   -->
            <a class="btn-mypage" href="javascript:void(0);"><img src="../images/ico_back_black.svg"></a>
            <div class="title">Customer Support</div>
            <div></div>
        </header>
        <ul class="tab-type1 bg white">
            <li><a class="btn-tab1 active" data-target="inquiring" href="#none">Contact us</a></li>
            <li><a class="btn-tab1" data-target="inquiryDetails" href="#none">Inquiry History</a></li>
        </ul>
       
        <div class="px20 mt38" id="inquiring">
            <h3 class="text fz20 fw600 lh28 black12">Do you have any questions?</h3>
            <ul class="list-type9 mt24">
                <li>
                    <!--    -->
                    <a href="#none" id="callSupportLink">
                        <div>
                            <div class="text fz16 fw600 lh24 reded">Call Support</div>
                            <p class="text fz14 fw500 lh22 black4e mt10">Weekdays 9 AM - 6 PM</p>
                        </div>
                        <img src="../images/ico_right_gray2.svg" alt="">
                    </a>
                </li>
                <li>
                    <a href="inquiry-person.php">
                        <div>
                            <div class="text fz16 fw600 lh24 reded">Send a 1:1 Inquiry</div>
                            <p class="text fz14 fw500 lh22 black4e mt10">Please leave your message</p>
                        </div>
                        <img src="../images/ico_right_gray2.svg" alt="">
                    </a>
                </li>
            </ul>
        </div>
        <div class="mt20" id="inquiryDetails" style="display: none;">
            <div class="text fz14 fw500 lh22 gray6b px20" id="totalInquiriesCount">Total 0 items</div>
            <ul class="mt8" id="inquiryListContainer"></ul>
            <div class="px20 mt16" id="inquiryPagination"></div>
        </div>

        <!-- NOTE: Call Support (system UI style) -->
        <div class="layer" id="callSupportLayer" style="display:none;"></div>
        <div class="alert-modal" id="callSupportPopup" style="display:none;">
            <div class="guide">Call Support</div>
            <div class="guide-sub" id="callSupportDesc">Choose a phone number to call.</div>
            <div class="align gap12 mt16">
                <button class="btn line lg flex1" type="button" id="callSupportDomesticBtn">Domestic</button>
                <button class="btn primary lg flex1" type="button" id="callSupportInternationalBtn">International</button>
            </div>
            <div class="align mt12">
                <button class="btn line lg" type="button" id="callSupportCloseBtn" style="width:100%;">Cancel</button>
            </div>
        </div>
    </div>
       
</body>
</html>

