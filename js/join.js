// handleJoin에서도 이메일 중복 확인 여부를 확인할 수 있도록 전역 플래그로 유지
window.__joinIsEmailChecked = window.__joinIsEmailChecked === true;

document.addEventListener("DOMContentLoaded", async function () {
    // 다국어 텍스트 로드
    await loadServerTexts();

    const nameInput = document.getElementById("name");
    const emailInput = document.getElementById("email");
    const phoneInput = document.getElementById("phone");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("password2");
    const joinBtn = document.getElementById("joinBtn");
    const duplicateCheckBtn = document.querySelector(".btn.line.inactive.sm");
    const countryCodeSel = document.getElementById("countryCodeSelect");
    const requiredChk1 = document.getElementById("chk1");
    const requiredChk2 = document.getElementById("chk2");
    const marketingChk = document.getElementById("chk3");

    let isEmailChecked = window.__joinIsEmailChecked === true;

    // SMT 수정 시작 - 약관 페이지 이동/복귀 시 입력 데이터 유지
    // - DOMContentLoaded로만 복원하면, 브라우저가 bfcache(pageshow)로 복귀하는 케이스에서 복원이 누락될 수 있음.
    let savedCountryCode = null; // 국가 코드는 API 로드 후 복원
    const restoreJoinFormData = () => {
        const savedData = sessionStorage.getItem('joinFormData');
        if (!savedData) return;
        try {
            const data = JSON.parse(savedData);
            if (nameInput && (data.name ?? '') !== '') nameInput.value = data.name;
            if (emailInput && (data.email ?? '') !== '') emailInput.value = data.email;
            if (phoneInput && (data.phone ?? '') !== '') phoneInput.value = data.phone;
            if ((data.countryCode ?? '') !== '') savedCountryCode = data.countryCode;
            if (passwordInput && (data.password ?? '') !== '') passwordInput.value = data.password;
            if (confirmPasswordInput && (data.password2 ?? '') !== '') confirmPasswordInput.value = data.password2;
            if (requiredChk1) requiredChk1.checked = !!data.chk1;
            if (requiredChk2) requiredChk2.checked = !!data.chk2;
            if (marketingChk) marketingChk.checked = !!data.chk3;

            if (data.isEmailChecked) {
                isEmailChecked = true;
                window.__joinIsEmailChecked = true;
                if (duplicateCheckBtn) {
                    const currentLang = getCurrentLanguage();
                    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                    duplicateCheckBtn.textContent = texts.checkComplete || "Checked";
                    duplicateCheckBtn.classList.remove("inactive");
                    duplicateCheckBtn.classList.add("primary");
                }
            }
        } catch (e) {
            console.error('Failed to restore join form data:', e);
        } finally {
            sessionStorage.removeItem('joinFormData');
        }
    };
    restoreJoinFormData();

    // bfcache 복귀 포함: pageshow에서도 복원 시도
    window.addEventListener('pageshow', () => {
        restoreJoinFormData();
        checkFormValidity();
    });

    // 약관 링크 클릭 시 입력 데이터 저장
    document.querySelectorAll('.terms-link').forEach(link => {
        link.addEventListener('click', function () {
            const formData = {
                name: nameInput?.value || '',
                email: emailInput?.value || '',
                phone: phoneInput?.value || '',
                countryCode: countryCodeSel?.value || '+63',
                password: passwordInput?.value || '',
                password2: confirmPasswordInput?.value || '',
                chk1: requiredChk1?.checked || false,
                chk2: requiredChk2?.checked || false,
                chk3: marketingChk?.checked || false,
                isEmailChecked: (window.__joinIsEmailChecked === true)
            };
            sessionStorage.setItem('joinFormData', JSON.stringify(formData));
        });
    });
    // SMT 수정 완료
    
    // 국가코드 로드 (국가명 + 국가번호 표시)
    try {
        const res = await fetch('../backend/api/countries.php');
        const json = await res.json();
        if (json?.success && Array.isArray(json.countries) && countryCodeSel) {
            // SMT 수정 시작 - sessionStorage에서 복원한 국가 코드 우선 사용
            const cur = savedCountryCode || countryCodeSel.value || '+63';
            // SMT 수정 완료
            countryCodeSel.innerHTML = '';
            json.countries.forEach(c => {
                const code = String(c?.code || '').trim();
                const name = String(c?.name || '').trim();
                if (!code) return;
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = name ? `${name} (${code})` : code;
                countryCodeSel.appendChild(opt);
            });
            countryCodeSel.value = cur;
        }
    } catch (e) {
        // API 실패 시 최소 fallback
        if (countryCodeSel && countryCodeSel.options.length <= 1) {
            // SMT 수정 시작 - sessionStorage에서 복원한 국가 코드 우선 사용
            const cur = savedCountryCode || countryCodeSel.value || '+63';
            // SMT 수정 완료
            const fallback = [
                { code: '+63', name: 'Philippines' },
                { code: '+82', name: 'South Korea' },
                { code: '+1', name: 'United States' },
                { code: '+81', name: 'Japan' }
            ];
            countryCodeSel.innerHTML = '';
            fallback.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.code;
                opt.textContent = `${c.name} (${c.code})`;
                countryCodeSel.appendChild(opt);
            });
            countryCodeSel.value = cur;
        }
    }

    // 에러 메시지 표시 함수
    function showError(fieldId, messageKey) {
        const errorElement = document.querySelector(`[data-i18n="${messageKey}"]`);
        if (errorElement) {
            errorElement.style.display = 'block';
        }
    }

    // 에러 메시지 숨김 함수
    function hideError(messageKey) {
        const errorElement = document.querySelector(`[data-i18n="${messageKey}"]`);
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }

    // 모든 에러 메시지 숨김
    function hideAllErrors() {
        hideError('invalidEmailFormat');
        hideError('invalidPhoneFormat');
        hideError('invalidPasswordFormat');
        hideError('passwordMismatch');
    }

    // 이메일 중복 확인 버튼
    if (duplicateCheckBtn) {
        duplicateCheckBtn.addEventListener("click", async function() {
            const email = emailInput.value.trim();
            
            if (!email) {
                const currentLang = getCurrentLanguage();
                const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
                showAlertModal(texts.enterEmail || "이메일을 입력해주세요.");
                return;
            }
            
            if (!isValidEmail(email)) {
                const currentLang = getCurrentLanguage();
                const isEn = (currentLang === 'en' || currentLang === 'eng' || currentLang === 'tl');
                showAlertModal(isEn ? "The email format is not correct." : "올바른 이메일 형식을 입력해주세요.");
                return;
            }
            
            try {
                // 로딩 표시
                this.disabled = true;
                const currentLang = getCurrentLanguage();
                const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                this.textContent = texts.checking || "Checking...";
                
                // API 호출
                const result = await api.checkEmailAvailability(email);
                
                if (result.success && result.available) {
                    isEmailChecked = true;
                    window.__joinIsEmailChecked = true;
                    this.textContent = texts.checkComplete || "Checked";
                    this.classList.remove("inactive");
                    this.classList.add("primary");
                    showAlertModal(texts.emailAvailable || "This email is available.");
                } else if (result.success && result.available === false) {
                    // Unavailable email
                    showAlertModal(texts.emailUnavailable || "This email cannot be used.");
                } else {
                    showAlertModal(result.message || texts.emailCheckFailed || "Email check failed.");
                }
                
                checkFormValidity();
                
            } catch (error) {
                console.error('Email check error:', error);
                const currentLang = getCurrentLanguage();
                const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                alert(texts.networkError || "A network error occurred. Please try again.");
            } finally {
                // 로딩 해제
                this.disabled = false;
                if (!isEmailChecked) {
                    const currentLang = getCurrentLanguage();
                    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                    this.textContent = texts.duplicateCheck || "Check Duplicate";
                }
            }
        });
    }

    // 폼 유효성 검사
    function checkFormValidity() {
        // 함수 내부에서 모든 요소를 다시 찾기 (클로저 의존 제거)
        const nameInputEl = document.getElementById("name");
        const emailInputEl = document.getElementById("email");
        const passwordInputEl = document.getElementById("password");
        const confirmPasswordInputEl = document.getElementById("password2");
        const requiredChk1El = document.getElementById("chk1");
        const requiredChk2El = document.getElementById("chk2");
        const joinBtnEl = document.getElementById("joinBtn");
        
        // 요소들이 존재하는지 확인
        if (!nameInputEl || !emailInputEl || !passwordInputEl || !confirmPasswordInputEl || !requiredChk1El || !requiredChk2El || !joinBtnEl) {
            console.warn('checkFormValidity: Required form elements not found', {
                nameInput: !!nameInputEl,
                emailInput: !!emailInputEl,
                passwordInput: !!passwordInputEl,
                confirmPasswordInput: !!confirmPasswordInputEl,
                requiredChk1: !!requiredChk1El,
                requiredChk2: !!requiredChk2El,
                joinBtn: !!joinBtnEl
            });
            return;
        }
        
        const name = nameInputEl.value.trim();
        const email = emailInputEl.value.trim();
        const password = passwordInputEl.value.trim();
        const confirmPassword = confirmPasswordInputEl.value.trim();
        const agreeRequired = (!!requiredChk1El.checked) && (!!requiredChk2El.checked);
        
        // 필수값이 모두 입력되었는지 확인 (형식 검증 및 이메일 중복 확인은 handleJoin에서 체크)
        // Check Duplicate 누르기 전이라도 모든 항목이 채워지면 활성화
        const isValid = name && 
                       email && 
                       password && 
                       confirmPassword && 
                       password === confirmPassword && 
                       agreeRequired;
        
        // Requirement (#108): if invalid, show a message instead of \"button does nothing\".
        // Keep the button clickable (handleJoin will display the specific reason).
        if (String(joinBtnEl.dataset.submitting || '') !== '1') {
            joinBtnEl.disabled = false;
        }
        if (isValid) {
            joinBtnEl.classList.remove("inactive");
        } else {
            joinBtnEl.classList.add("inactive");
        }
        
        // 디버깅용 로그
        console.log('checkFormValidity:', {
            name: !!name,
            email: !!email,
            password: !!password,
            confirmPassword: !!confirmPassword,
            passwordsMatch: password === confirmPassword,
            agreeRequired: agreeRequired,
            chk1Checked: requiredChk1El.checked,
            chk2Checked: requiredChk2El.checked,
            isValid: isValid,
            buttonInactive: joinBtnEl.classList.contains("inactive"),
            buttonDisabled: joinBtnEl.disabled
        });
    }
    
    // 전역에서 접근 가능하도록 등록 (이메일 확인 팝업 닫힌 후 호출용)
    window.checkFormValidity = checkFormValidity;

    // 이메일 유효성 검사
    function isValidEmail(email) {
        if (!email || email.trim() === '') {
            return false;
        }
        
        // 더 강력한 이메일 정규식
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        const isValid = emailRegex.test(email.trim());
        
        console.log(`Email validation: ${email} -> ${isValid}`);
        return isValid;
    }

    // 입력 필드 변경 시 폼 유효성 재검사
    [nameInput, emailInput, phoneInput, passwordInput, document.getElementById("password2")].forEach(input => {
        if (input) {
            input.addEventListener("input", function() {
                // 이메일이 변경되면 중복 확인 리셋
                if (input === emailInput) {
                    isEmailChecked = false;
                    window.__joinIsEmailChecked = false;
                    if (duplicateCheckBtn) {
                        const currentLang = getCurrentLanguage();
                        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                        duplicateCheckBtn.textContent = texts.duplicateCheck || "Check Duplicate";
                        duplicateCheckBtn.classList.add("inactive");
                        duplicateCheckBtn.classList.remove("primary");
                    }
                    hideError('invalidEmailFormat');
                }

                // 각 필드별 에러 메시지 숨김
                if (input === phoneInput) {
                    hideError('invalidPhoneFormat');
                }
                if (input === passwordInput) {
                    hideError('invalidPasswordFormat');
                }
                if (input === document.getElementById("password2")) {
                    hideError('passwordMismatch');
                }

                checkFormValidity();
            });
        }
    });

    // SMT 수정 시작 - blur 이벤트에서 유효성 검사 후 에러 메시지 표시
    // 이메일 형식 검사
    if (emailInput) {
        emailInput.addEventListener("blur", function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                showError('email', 'invalidEmailFormat');
            } else {
                hideError('invalidEmailFormat');
            }
        });
    }

    // 연락처 형식 검사
    if (phoneInput) {
        phoneInput.addEventListener("blur", function() {
            const phone = this.value.trim();
            if (phone) {
                const phoneRegex = /^[0-9\s-]+$/;
                if (!phoneRegex.test(phone)) {
                    showError('phone', 'invalidPhoneFormat');
                } else {
                    hideError('invalidPhoneFormat');
                }
            }
        });
    }

    // 비밀번호 형식 검사 (8~12자, 영문/숫자/특수문자 포함)
    if (passwordInput) {
        passwordInput.addEventListener("blur", function() {
            const password = this.value;
            if (password) {
                // 8~12자, 영문/숫자/특수문자 각각 1개 이상 포함
                const hasLetter = /[a-zA-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
                const isLengthValid = password.length >= 8 && password.length <= 12;

                if (!isLengthValid || !hasLetter || !hasNumber || !hasSpecial) {
                    showError('password', 'invalidPasswordFormat');
                } else {
                    hideError('invalidPasswordFormat');
                }
            }
        });
    }

    // 비밀번호 확인 검사
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener("blur", function() {
            const password = passwordInput?.value || '';
            const confirmPassword = this.value;
            if (confirmPassword && password !== confirmPassword) {
                showError('password2', 'passwordMismatch');
            } else {
                hideError('passwordMismatch');
            }
        });
    }
    // SMT 수정 완료

    // 초기 폼 상태 확인
    checkFormValidity();

    // 필수 약관 체크 변경 시 버튼 상태 반영
    [requiredChk1, requiredChk2].forEach(chk => {
        if (chk) chk.addEventListener('change', checkFormValidity);
    });
    
    // "전체 동의" 체크박스 변경 시에도 폼 유효성 재검사
    // check.js에서 개별 체크박스를 업데이트한 후에 실행되도록 약간의 지연 추가
    const agreeCheckAll = document.getElementById("agreeCheck");
    if (agreeCheckAll) {
        agreeCheckAll.addEventListener('change', function() {
            // check.js의 이벤트가 먼저 처리되도록 약간의 지연
            setTimeout(() => {
                checkFormValidity();
            }, 10);
        });
    }
});

// 회원가입 처리 함수
async function handleJoin() {
    const name = document.getElementById("name")?.value.trim();
    const email = document.getElementById("email")?.value.trim();
    const phone = document.getElementById("phone")?.value.trim();
    const countryCode = document.getElementById("countryCodeSelect")?.value || '';
    const password = document.getElementById("password")?.value.trim();
    const confirmPassword = document.getElementById("password2")?.value.trim();
    const chk1 = document.getElementById("chk1")?.checked;
    const chk2 = document.getElementById("chk2")?.checked;
    const currentLang = getCurrentLanguage();
    const isEn = (currentLang === 'en' || currentLang === 'eng' || currentLang === 'tl');
    
    // 필수 필드 확인
    if (!name || !email || !password || !confirmPassword) {
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
        alert(texts.enterAllFields || "Please fill in all required fields.");
        return;
    }

    // 필수 약관 체크
    if (!chk1 || !chk2) {
        alert("Please agree to the required terms.");
        return;
    }

    // 요구사항(id 51): 이메일 중복 확인을 하지 않은 경우 이메일 확인 요청 팝업 노출
    if (email && window.__joinIsEmailChecked !== true) {
        showAlertModal("Please check email duplication first.");
        return;
    }

    // 이메일 형식
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert("The email format is not correct.");
        return;
    }

    // 연락처 형식(선택 입력): 값이 있을 때만 검사
    if (phone) {
    const phoneRegex = /^[0-9\s-]+$/;
    if (!phoneRegex.test(phone)) {
        alert("Contact format is not correct.");
        return;
        }
    }
    
    // 비밀번호 확인
    if (password !== confirmPassword) {
        alert("Passwords do not match.");
        return;
    }
    
    try {
        // 로딩 표시
        const joinBtn = document.getElementById("joinBtn");
        if (joinBtn) {
            joinBtn.dataset.submitting = '1';
            joinBtn.disabled = true;
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
            joinBtn.textContent = texts.joining || "Signing up...";
        }
        
        const fullPhone = phone ? (countryCode ? `${countryCode} ${phone}` : phone) : null;
        const result = await api.register(name, email, fullPhone, password);
        
        if (result.success) {
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
            // Sign Up Complete: 모달(메인/서브/버튼)로 노출
            const modal = document.getElementById('joinSuccessModal');
            const okBtn = document.getElementById('joinSuccessOkBtn');
            if (modal && okBtn) {
                modal.style.display = 'flex';
                okBtn.onclick = () => {
                    modal.style.display = 'none';
                    // 로그인 안내 문구에 맞춰 로그인 페이지로 이동
                    location.href = '/user/login.html';
                };
            } else {
                // fallback (기존 alert)
                alert(texts.joinSuccess || "Registration has been completed.\nPlease log in");
                location.href = '/user/login.html';
            }

            // SMT 수정 시작 - 회원가입 성공 시 sessionStorage 정리
            sessionStorage.removeItem('joinFormData');
            // SMT 수정 완료
        } else {
            const msg = String(result.message || '');
            if (isEn && (msg.includes('이미 사용 중인 이메일') || msg.toLowerCase().includes('email'))) {
                alert((globalLanguageTexts[currentLang] || {}).emailUnavailable || "This email cannot be used.");
            } else {
                alert(result.message || "Registration failed.");
            }
            // 실패 시에만 버튼 상태 복원
            const joinBtn = document.getElementById("joinBtn");
            if (joinBtn) {
                joinBtn.disabled = false;
                joinBtn.dataset.submitting = '0';
                const currentLang = getCurrentLanguage();
                const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
                joinBtn.textContent = texts.join || "Sign up";
            }
        }
        
    } catch (error) {
        console.error('Registration error:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
        alert(texts.networkError || "A network error occurred. Please try again.");
        
        // 오류 시에만 버튼 상태 복원
        const joinBtn = document.getElementById("joinBtn");
        if (joinBtn) {
            joinBtn.disabled = false;
            joinBtn.dataset.submitting = '0';
            joinBtn.textContent = texts.join || "Sign up";
        }
    }
}

// 이메일 중복 확인 모달
function showAlertModal(message) {
    const layer = document.getElementById("emailCheckLayer");
    const popup = document.getElementById("emailCheckPopup");
    const msgEl = document.getElementById("emailCheckMessage");
    const okBtn = document.getElementById("emailCheckOkBtn");
  
    // 혹시 모달 DOM이 없는 페이지면 alert로 fallback
    if (!layer || !popup || !msgEl || !okBtn) {
      alert(message);
      return;
    }
  
    msgEl.textContent = message;
  
    layer.style.display = "block";
    popup.style.display = "block";
  
    // 이벤트 중복 방지: onclick으로 덮어쓰기
    okBtn.onclick = hideAlertModal;
    layer.onclick = hideAlertModal; // 바깥 클릭 닫기(원치 않으면 제거)
}
  
function hideAlertModal() {
    const layer = document.getElementById("emailCheckLayer");
    const popup = document.getElementById("emailCheckPopup");
  
    if (layer) layer.style.display = "none";
    if (popup) popup.style.display = "none";
    
    // 팝업 닫힌 후 폼 유효성 재검사하여 버튼 상태 업데이트
    if (typeof window.checkFormValidity === 'function') {
        window.checkFormValidity();
    }
}