document.addEventListener("DOMContentLoaded", async function () {
    // 다국어 텍스트 로드
    await loadServerTexts();
    
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const loginBtn = document.getElementById("loginBtn");
    const autoLoginCheckbox = document.querySelector('input[type="checkbox"]');
    const closeLoginBtn = document.getElementById('closeLoginBtn');

    // 닫기(모달 닫기 대체): 이전 페이지로, 없으면 홈으로
    if (closeLoginBtn) {
        closeLoginBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goBackOrHome();
        });
    }

    // 자동로그인 체크 상태 복원
    try {
        if (autoLoginCheckbox) {
            autoLoginCheckbox.checked = localStorage.getItem('autoLogin') === 'true';
        }
    } catch (_) {}

    // 이미 로그인 상태면 로그인 페이지에 머물지 않고 홈으로 이동
    // NOTE: localStorage만 믿으면 세션 만료/쿠키 삭제 시 "로그인 -> 홈" 무한 루프가 발생할 수 있어,
    //       서버 세션(check-session.php)로 한번 더 검증한다.
    try {
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');
        const userEmail = localStorage.getItem('userEmail');
        if (isLoggedIn && userId && userEmail) {
            let serverOk = false;
            try {
                const r = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
                const j = await r.json().catch(() => ({}));
                serverOk = !!(j && j.success && j.isLoggedIn);
            } catch (e) {
                // 네트워크 오류 등은 즉시 홈으로 보내지 않음(사용자가 로그인 재시도 가능)
                serverOk = false;
            }

            if (serverOk) {
                // If we have a post-login return URL (booking flow etc), prefer it over home.
                const redirectUrl = (typeof getSafeRedirectAfterLogin === 'function') ? getSafeRedirectAfterLogin() : null;
                if (redirectUrl) {
                    try { sessionStorage.removeItem('redirectAfterLogin'); } catch (_) {}
                    location.replace(redirectUrl);
                    return;
                }
                const currentLang = (typeof getCurrentLanguage === 'function')
                    ? getCurrentLanguage()
                    : (localStorage.getItem('selectedLanguage') || 'en');
                location.replace(`../home.html?lang=${encodeURIComponent(currentLang)}`);
                return;
            }

            // 세션이 없는데 localStorage만 남아있는 경우 정리
            try { localStorage.removeItem('isLoggedIn'); } catch (_) {}
            try { localStorage.removeItem('userId'); } catch (_) {}
            try { localStorage.removeItem('userEmail'); } catch (_) {}
            try { localStorage.removeItem('username'); } catch (_) {}
            try { localStorage.removeItem('accountType'); } catch (_) {}
        }
    } catch (_) {}

    // 팝업 닫기 이벤트 설정
    const noMemberOkBtn = document.getElementById("noMemberOkBtn");
    const loginLimitOkBtn = document.getElementById("loginLimitOkBtn");
    
    if (noMemberOkBtn) {
        noMemberOkBtn.addEventListener("click", hideNoMemberPopup);
    }
    
    if (loginLimitOkBtn) {
        loginLimitOkBtn.addEventListener("click", hideLoginLimitPopup);
    }

    // 폼 유효성 검사
    function checkFormValidity() {
        const emailOrId = emailInput?.value.trim();
        const password = passwordInput?.value.trim();

        // 로그인 입력은 이메일/아이디(username) 모두 허용
        // - '@'가 포함된 경우만 이메일 형식 검증
        const looksLikeEmail = !!emailOrId && emailOrId.includes('@');
        const isValid = !!emailOrId && !!password && (!looksLikeEmail || isValidEmail(emailOrId));
        
        if (loginBtn) {
            if (isValid) {
                loginBtn.classList.remove("inactive");
                loginBtn.disabled = false;
            } else {
                loginBtn.classList.add("inactive");
                loginBtn.disabled = true;
            }
        }
    }

    // 이메일 유효성 검사
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // 입력 필드 변경 시 폼 유효성 재검사
    [emailInput, passwordInput].forEach(input => {
        if (input) {
            input.addEventListener("input", checkFormValidity);
        }
    });

    // 엔터키로 로그인
    [emailInput, passwordInput].forEach(input => {
        if (input) {
            input.addEventListener("keypress", function(e) {
                if (e.key === "Enter") {
                    if (!loginBtn.classList.contains("inactive")) {
                        handleLogin();
                    }
                }
            });
        }
    });

    // 초기 폼 상태 확인
    checkFormValidity();
    
    // 로그인 버튼 클릭 이벤트 추가
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleLogin();
        });
    }
});

function goBackOrHome() {
    history.back();
}

// 로그인 처리 함수
async function handleLogin() {
    const email = document.getElementById("email")?.value.trim();
    const password = document.getElementById("password")?.value.trim();
    const autoLogin = document.querySelector('input[type="checkbox"]')?.checked;
    
    // 필수 필드 확인
    if (!email || !password) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.enterEmailPassword || "Please enter your email/ID and password.");
        return;
    }
    
    // 이메일 형식 확인: '@'가 포함된 경우만 검증 (아이디 로그인 허용)
    if (email && email.includes('@') && !isValidEmail(email)) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.invalidEmailFormat || "The email format is not correct.");
        return;
    }
    
    try {
        // 로딩 표시
        const loginBtn = document.getElementById("loginBtn");
        if (loginBtn) {
            loginBtn.disabled = true;
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
            loginBtn.textContent = texts.loggingIn || "Logging in...";
        }
        
        // API 객체 확인
        if (typeof api === 'undefined') {
            throw new Error('API is not available. Please check api.js.');
        }
        
        console.log('API 객체:', api);
        console.log('로그인 요청:', { email, password: password ? '***' : 'empty', autoLogin });
        console.log('API 엔드포인트:', api.endpoints.auth);
        
        // 직접 fetch 사용 (디버깅용)
        console.log('Starting direct fetch request...');
        
        // AbortController로 타임아웃 설정
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            console.log('Request timeout after 5 seconds');
            controller.abort();
        }, 5000);
        
        const response = await fetch(api.endpoints.auth, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ email, password, rememberMe: autoLogin }),
            credentials: 'include',
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        console.log('Fetch response status:', response.status);
        console.log('Fetch response headers:', response.headers);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Fetch error response:', errorText);
            
            // 401 또는 403 에러인 경우 응답 본문 파싱 시도
            let errorResult = null;
            try {
                errorResult = JSON.parse(errorText);
            } catch (e) {
                // 파싱 실패 시 기본 처리
            }
            
            // SMT 수정 시작
            // 로그인 실패 횟수 초과 또는 제한 상태 확인
            if (response.status === 403 && errorResult?.errorCode === 'LOGIN_LIMITED') {
                showLoginLimitPopup();
                return;
            }

            // 401 에러 - 회원정보 없음 또는 비밀번호 불일치 처리
            if (response.status === 401 && errorResult) {
                if (errorResult.errorCode === 'USER_NOT_FOUND' || errorResult.errorCode === 'INVALID_CREDENTIALS') {
                    showNoMemberPopup();
                    return;
                }
            }
            // SMT 수정 완료

            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Direct fetch result:', result);
        
        console.log('로그인 응답:', result);
        console.log('응답 타입:', typeof result);
        console.log('응답 success:', result?.success);
        console.log('응답 message:', result?.message);
        
        if (result.success) {
            // 사용자 정보 저장 - API 응답 구조에 맞게 수정
            localStorage.setItem("userInfo", JSON.stringify(result.user));
            localStorage.setItem("userData", JSON.stringify(result.user));
            localStorage.setItem("isLoggedIn", "true");
            localStorage.setItem("userEmail", result.user.email);
            localStorage.setItem("userId", result.user.accountId);
            localStorage.setItem("accountId", result.user.accountId);
            localStorage.setItem("username", `${result.user.firstName || ''} ${result.user.lastName || ''}`.trim());
            localStorage.setItem("firstName", result.user.firstName || '');
            localStorage.setItem("lastName", result.user.lastName || '');
            localStorage.setItem("phoneNumber", result.user.phoneNumber || '');
            localStorage.setItem("accountRole", result.user.accountRole || '');
            // auth-guard.js가 사용하는 accountType 저장 (guide/agent/admin/cs 분기용)
            localStorage.setItem("accountType", result.user.accountType || result.user.accountRole || '');
            // SMT 수정: B2B/B2C 판별 안정화를 위해 clientType/clientRole 저장
            if (result.user.clientType !== undefined) {
                localStorage.setItem("clientType", String(result.user.clientType || ''));
            }
            if (result.user.clientRole !== undefined) {
                localStorage.setItem("clientRole", String(result.user.clientRole || ''));
            }
            // 자동 로그인 상태 저장 (auth-guard.js에서 사용)
            if (autoLogin) {
                localStorage.setItem('autoLogin', 'true');
                // 자동로그인 사용자는 언어 설정이 없더라도 재접속 시 언어 선택 페이지가 뜨지 않도록 기본값을 확정한다.
                try {
                    const savedLang = localStorage.getItem('selectedLanguage');
                    if (savedLang !== 'en' && savedLang !== 'tl') {
                        localStorage.setItem('selectedLanguage', 'en');
                    }
                } catch (_) {}
                // 자동로그인 사용자는 재접속 시 언어/권한 안내를 다시 보지 않도록 온보딩 플래그를 확정 저장
                localStorage.setItem('languageOnboarded', '1');
                localStorage.setItem('permitConfirmed', '1');
            } else {
                localStorage.removeItem('autoLogin');
            }

            console.log('사용자 정보 localStorage 저장 완료:', {
                userId: result.user.accountId,
                email: result.user.email,
                name: `${result.user.firstName} ${result.user.lastName}`
            });

            // 계정 타입별 리다이렉트
            const accountType = (result.user.accountType || result.user.accountRole || '').toLowerCase();
            if (accountType === 'guide') {
                // 사용자(가이드) 전용 마이페이지로 이동
                location.href = "guide-mypage.html";
                return;
            }

            // 일반 고객: 로그인 전 접근 페이지가 있으면 복귀, 없으면 홈
            const redirectUrl = (typeof getSafeRedirectAfterLogin === 'function') ? getSafeRedirectAfterLogin() : null;
            if (redirectUrl) {
                try { sessionStorage.removeItem('redirectAfterLogin'); } catch (_) {}
                location.href = redirectUrl;
                return;
            }
            location.href = "../home.html";
        } else {
            // 로그인 실패 처리
            if (result.errorCode === 'LOGIN_LIMITED') {
                // 5회 연속 로그인 실패
                showLoginLimitPopup();
            } else if (result.errorCode === 'USER_NOT_FOUND' || result.errorCode === 'INVALID_CREDENTIALS') {
                // 존재하지 않는 회원정보
                showNoMemberPopup();
            } else {
                // 기타 오류는 alert 표시
                alert(result.message || "Login failed.");
            }
        }
        
    } catch (error) {
        console.error('Login error:', error);
        console.error('Error name:', error.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        // 더 구체적인 오류 메시지 제공
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            alert("Unable to connect to the server. Please check your network connection.");
        } else if (error.message.includes('API 객체가 로드되지 않았습니다')) {
            alert("API loading error. Please refresh the page.");
        } else if (error.message.includes('Failed to fetch')) {
            alert("Network error. Please check your internet connection.");
        } else if (error.message.includes('JSON')) {
            alert("Unable to process the server response. Please try again later.");
        } else {
            alert("An error occurred while logging in: " + error.message);
        }
    } finally {
        // 로딩 해제
        const loginBtn = document.getElementById("loginBtn");
        if (loginBtn) {
            loginBtn.disabled = false;
            // i18n 텍스트로 복원
            try {
                loginBtn.textContent = (typeof getText === 'function') ? getText('logIn') : 'Log In';
            } catch (_) {
                loginBtn.textContent = 'Log In';
            }
        }
    }
}

// redirectAfterLogin is stored by booking flow / auth-guard.
// Return a safe same-origin URL (absolute or relative), or null.
function getSafeRedirectAfterLogin() {
    try {
        const raw = sessionStorage.getItem('redirectAfterLogin') || '';
        if (!raw) return null;
        const decoded = decodeURIComponent(raw);
        // allow relative paths
        if (decoded.startsWith('/')) return decoded;
        if (decoded.startsWith('../') || decoded.startsWith('./')) return decoded;
        const u = new URL(decoded, window.location.origin);
        if (u.origin !== window.location.origin) return null;
        return u.pathname + u.search + u.hash;
    } catch (_) {
        return null;
    }
}

// 이메일 유효성 검사 함수
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// 사용자 정보 저장 함수
function saveUserInfo(user) {
    localStorage.setItem("userInfo", JSON.stringify(user));
}

// 성공 메시지 표시 함수
function showSuccessMessage(message) {
    alert(message);
}

// 에러 메시지 표시 함수
function showErrorMessage(message) {
    alert(message);
}

// 존재하지 않는 회원정보 팝업 표시
function showNoMemberPopup() {
    const noMemberLayer = document.getElementById("noMemberLayer");
    const noMemberPopup = document.getElementById("noMemberPopup");
    if (noMemberLayer && noMemberPopup) {
        noMemberLayer.style.display = 'block';
        noMemberPopup.style.display = 'flex';
    }
}

// 존재하지 않는 회원정보 팝업 숨기기
function hideNoMemberPopup() {
    const noMemberLayer = document.getElementById("noMemberLayer");
    const noMemberPopup = document.getElementById("noMemberPopup");
    if (noMemberLayer && noMemberPopup) {
        noMemberLayer.style.display = 'none';
        noMemberPopup.style.display = 'none';
    }
}

// 5회 연속 로그인 실패 팝업 표시
function showLoginLimitPopup() {
    const loginLimitLayer = document.getElementById("loginLimitLayer");
    const loginLimitPopup = document.getElementById("loginLimitPopup");
    if (loginLimitLayer && loginLimitPopup) {
        loginLimitLayer.style.display = 'block';
        loginLimitPopup.style.display = 'flex';
    }
}

// 5회 연속 로그인 실패 팝업 숨기기
function hideLoginLimitPopup() {
    const loginLimitLayer = document.getElementById("loginLimitLayer");
    const loginLimitPopup = document.getElementById("loginLimitPopup");
    if (loginLimitLayer && loginLimitPopup) {
        loginLimitLayer.style.display = 'none';
        loginLimitPopup.style.display = 'none';
    }
}