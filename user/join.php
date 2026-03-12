<?php
require_once '../backend/i18n_helper.php';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('join', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="icon" type="image/svg+xml" href="../images/ico_travel.svg">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <script src="../js/password.js" defer></script>
    <script src="../js/input_member.js" defer></script>
    <script src="../js/check.js" defer></script>
    <script src="../js/validation.js" defer></script>
    <script src="../js/api.js" defer></script>
    <script src="../js/join.js?v=20251226_joinfix1" defer></script>
</head>
<body>
    <div class="main">
        <header class="header-type2">
            <a class="btn-mypage" href="javascript:history.back();"><img src="../images/ico_back_black.svg"></a>
            <div class="title">Sign up</div>
            <div></div>
        </header>

        <div class="px20 pb130">
            <div class="mt28">
                <label class="label-input mb6" for="name"><span data-i18n="name">이름</span><span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                <input class="input-type1" id="name" type="text" data-i18n-placeholder="name" placeholder="이름">
                <label class="label-input mb6 mt16" for="email"><span data-i18n="email">이메일</span><span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                <div class="input-wrap2">
                    <input class="input-type1" id="email" type="email" data-i18n-placeholder="email" placeholder="이메일">
                    <button class="btn line inactive sm" type="button" data-i18n="duplicateCheck">Check Duplicate</button>
                </div>
                <div class="text fz12 fw400 lh16 reded mt4" data-i18n="invalidEmailFormat" style="display: none;">이메일 형식이 올바르지 않습니다.</div>
                <label class="label-input mb6 mt16" for="phone"><span data-i18n="phone">연락처</span></label>
                <div class="align vm relative">
                    <select class="select-type1" name="countryCode" id="countryCodeSelect">
                        <option value="+63">+63</option>
                    </select>
                    <input class="input-type2" id="phone" type="tel" data-i18n-placeholder="phonePlaceholder" placeholder="'-' 없이 숫자만 입력">
                </div>
                <div class="text fz12 fw400 lh16 reded mt4" data-i18n="invalidPhoneFormat" style="display: none;">연락처 형식이 올바르지 않습니다.</div>

                <label class="label-input mb6 mt16" for="password"><span data-i18n="password">비밀번호</span><span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                <div class="input-wrap1 mt14">
                    <input class="input-type1" id="password" type="password" data-i18n-placeholder="passwordPlaceholder" placeholder="8~12자, 영문/숫자/특수문자 포함">
                    <button class="btn-eye" type="button"><img src="../images/ico_eye_off.svg" alt=""></button>
                </div>
                <div class="text fz12 fw400 lh16 gray96 mt4" data-i18n="passwordRule">8-12 characters, including letters, numbers, and special characters</div>
                <div class="text fz12 fw400 lh16 reded mt4" data-i18n="invalidPasswordFormat" style="display: none;">비밀번호 형식이 올바르지 않습니다.</div>

                <label class="label-input mb6 mt16" for="password2"><span data-i18n="passwordConfirm">비밀번호 확인</span><span class="text fz14 fw500 reded lh22 ml3">*</span></label>
                <div class="input-wrap1 mt14">
                    <input class="input-type1" id="password2" type="password" data-i18n-placeholder="passwordConfirmPlaceholder" placeholder="비밀번호 확인">
                    <button class="btn-eye" type="button"><img src="../images/ico_eye_off.svg" alt=""></button>
                </div>
                <div class="text fz12 fw400 lh16 reded mt4" data-i18n="passwordMismatch" style="display: none;">비밀번호가 일치하지 않습니다.</div>

                <div class="mt28">
                    <!-- 전체 동의 체크박스 -->
                    <ul class="check-type-all" id="checkBoxWrap">
                        <li>
                            <div class="check-all">
                              <label for="agreeCheck">
                                <input type="checkbox" id="agreeCheck"/>
                                <span data-i18n="agreeAll">전체 동의</span>
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
                              <span data-i18n="privacyCollection">개인정보 수집 및 이용 (필수)</span>
                            </label>
                            <a href="terms.php?category=privacy_collection&from=join&lang=<?php echo htmlspecialchars($currentLang); ?>" class="terms-link" aria-label="개인정보 수집 및 이용 상세">
                                <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                            </a>
                        </div>
                      </li>
                      <li class="mt16 px12">
                        <div class="align both w100">
                            <label for="chk2">
                              <input type="checkbox" id="chk2" class="chk-each" />
                              <span data-i18n="privacyThirdParty">개인정보 제3자 제공 (필수)</span>
                            </label>
                            <a href="terms.php?category=privacy_sharing&from=join&lang=<?php echo htmlspecialchars($currentLang); ?>" class="terms-link" aria-label="개인정보 제3자 제공 상세">
                                <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                            </a>
                        </div>
                      </li>
                      <li class="mt16 px12">
                        <div class="align both w100">
                            <label for="chk3">
                              <input type="checkbox" id="chk3" class="chk-each" />
                              <span data-i18n="marketingConsent">마케팅 이용 동의 (선택)</span>
                            </label>
                            <a href="terms.php?category=marketing_consent&from=join&lang=<?php echo htmlspecialchars($currentLang); ?>" class="terms-link" aria-label="마케팅 이용 동의 상세">
                                <img style="width: auto" src="../images/ico_arrow_right_black.svg" alt="">
                            </a>
                        </div>
                      </li>
                    </ul>
                  </div>
            </div>
            <div class="fixed-bottom px20">
                <button class="btn primary lg inactive mt20" type="button" id="joinBtn" onclick="handleJoin()" data-i18n="join" disabled>회원가입</button>
            </div>
        </div>
    </div>

    <!-- 이메일 등 확인 결과 팝업 -->
    <div class="layer" id="emailCheckLayer" style="display: none;"></div>
    <div class="alert-modal" id="emailCheckPopup" style="display: none;">
        <div class="guide" id="emailCheckMessage" data-i18n="emailCheckMessage">Email check result message</div>
        <div class="align center gap12 mt20">
            <button class="btn min-width line lg gray4e" type="button" id="emailCheckOkBtn" data-i18n="confirm">Confirm</button>
        </div>
    </div>

    <!-- 회원가입 완료 팝업 -->
    <div id="joinSuccessModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
        <div style="width:min(320px, calc(100% - 48px)); background:#fff; border-radius:12px; padding:20px 18px; box-shadow:0 10px 30px rgba(0,0,0,0.18); text-align:center;">
            <div class="text fz16 fw600 lh22 black12" data-i18n="joinSuccessTitle">Registration has been completed.</div>
            <div class="text fz13 fw400 lh18 gray96 mt8" data-i18n="joinSuccessDesc">Please log in</div>
            <div class="mt16 align center">
                <button type="button" id="joinSuccessOkBtn" class="btn min-width line lg" data-i18n="confirm">Confirm</button>
            </div>
        </div>
    </div>
</body>
</html>


