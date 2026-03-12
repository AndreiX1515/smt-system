<?php
require_once '../backend/i18n_helper.php';

// 현재 언어 설정
$currentLang = getCurrentLanguage();

// URL 파라미터에서 booking_id 가져오기
$bookingId = $_GET['booking_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('reservation_summary', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/button.js" defer></script>
    <script src="../js/check.js" defer></script>
    <script src="../js/reservation-sum-pay.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="javascript:history.back();"><img src="../images/ico_back_black.svg"></a>
            <div class="title"><?php echoI18nText('reservation', $currentLang); ?></div>
            <div></div>
        </header>
        <div class="mt16 px20 pb24 border-bottom10">
            <div class="progress-type1">
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
                <i class="active"></i>
            </div>
            <div class="text fz14 fw600 lh22 black12 mt8 departure-info"><span class="text fw 700"><?php echoI18nText('departure', $currentLang); ?></span></div>
            <h3 class="text fz20 fw600 lh28 black12 mt24"><?php echoI18nTextHTML('check_reservation_details', $currentLang); ?></h3>
            <div class="text fz14 fw600 black12 lh22"><?php echoI18nText('booking_guests', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="booking-guests">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('room_options', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="selected-rooms">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('booker_info', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="customer-info">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('traveler_info', $currentLang); ?></div>    
            <div class="traveler-info">
                <!-- JavaScript에서 동적으로 생성됩니다 -->
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('extra_luggage', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="baggage-option">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('breakfast_request', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="breakfast-option">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('wifi_rental', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="wifi-option">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('seat_request', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="seat-request">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('other_requests', $currentLang); ?></div>    
            <div class="card-type8 pink mt8">
                <ul class="other-request">
                    <!-- JavaScript에서 동적으로 생성됩니다 -->
                </ul>
            </div>
        </div>
        <div class="px20 pb24 border-bottom10">
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('payment_method', $currentLang); ?></div>    
            <label class="radio-bank mt4">
                <input type="radio" name="payment" value="bank" checked>
                <span class="text fz14 fw400 lh22 black12"><?php echoI18nText('bank_transfer', $currentLang); ?></span>
            </label>
        </div>
        <div class="px20 pb24 border-bottom10">
            <div class="text fz14 fw600 black12 lh22 mt24"><?php echoI18nText('payment_info', $currentLang); ?></div>    
            <ul class="mt8 pb24 border-bottomea payment-details">
                <!-- JavaScript에서 동적으로 생성됩니다 -->
            </ul>
            <div class="mt24 align both vm">
                <div class="text fz16 fw600 lh24 black12"><?php echoI18nText('total_amount', $currentLang); ?></div>
                <strong class="text fz20 fw600 lh28 black12 total-amount">₱0</strong>
            </div>
            <p class="align right mt4 text fz14 fw400 grayb0 lh140"><?php echoI18nText('fees_included', $currentLang); ?></p>
        </div>
        <div class="mt28 px20">
            <!-- 전체 동의 체크박스 -->
            <ul class="check-type-all" id="checkBoxWrap">
                <li>
                    <div class="check-all">
                        <label for="agreeCheck">
                            <input type="checkbox" id="agreeCheck"/>
                            <span><?php echoI18nText('agree_all', $currentLang); ?></span>
                        </label>
                    </div>
                </li>
            </ul>
            
            <!-- 개별 동의 항목 -->
            <ul class="check-type5">
                <li class="mt20 px12">
                <div class="align both w100">
                    <label for="chk1">
                        <input type="checkbox" id="chk1" class="chk-each" />
                        <span><?php echoI18nText('privacy_collection', $currentLang); ?></span>
                    </label>
                    <a href="terms.php?category=privacy_collection&lang=<?php echo $currentLang; ?>">
                        <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                    </a>
                </div>
                </li>
                <li class="mt16 px12">
                <div class="align both w100">
                    <label for="chk2">
                        <input type="checkbox" id="chk2" class="chk-each" />
                        <span><?php echoI18nText('privacy_sharing', $currentLang); ?></span>
                    </label>
                    <a href="terms.php?category=privacy_sharing&lang=<?php echo $currentLang; ?>">
                        <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                    </a>
                </div>
                </li>
                <li class="mt16 px12">
                <div class="align both w100">
                    <label for="chk3">
                        <input type="checkbox" id="chk3" class="chk-each" />
                        <span><?php echoI18nText('marketing_consent', $currentLang); ?></span>
                    </label>
                    <a href="terms.php?category=marketing_consent&lang=<?php echo $currentLang; ?>">
                        <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                    </a>
                </div>
                </li>
            </ul>

            <div class="mt24">
                <ul class="check-type-all type2" id="checkBoxWrap2">
                    <li>
                        <div class="check-all align both w100">
                            <label for="agreeCheck2">
                                <input type="checkbox" id="agreeCheck2"/>
                                <span><?php echoI18nText('travel_terms_consent', $currentLang); ?></span>
                            </label>
                            <a href="terms.php?category=terms&lang=<?php echo $currentLang; ?>">
                                <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                            </a>
                            <p class="p"><?php echoI18nText('cancellation_terms_confirmed', $currentLang); ?></p>
                        </div>
                    </li>
                </ul>
            </div>
            
            <div class="mt88 pb12">
                <a class="btn primary lg pay-button" href="javascript:void(0);"><?php echoI18nText('pay', $currentLang); ?> ₱0</a>
            </div>
        </div>
        
    </div>
</body>
</html>