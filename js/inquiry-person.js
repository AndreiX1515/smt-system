// 문의 작성 페이지 기능
document.addEventListener('DOMContentLoaded', async function() {
    // html/php 모두 지원 (현재 운영은 inquiry-person.php)
    if (window.location.pathname.includes('inquiry-person')) {
        await initializeInquiryPersonPage();
    }
});

const MAX_ATTACHMENTS = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10MB
const ALLOWED_EXTS = new Set(['jpg', 'jpeg', 'png', 'gif', 'pdf']);
let selectedFiles = [];
let selectedInquiryType = '';

// 문의 작성 페이지 초기화
async function initializeInquiryPersonPage() {
    // 다국어 텍스트 로드
    await loadServerTexts();

    // 로그인 상태 확인 + userId 확보(localStorage 누락 대비)
    await ensureUserSession();
    
    // 사용자 정보 로드
    await loadUserInfo();

    // 국가코드 로드
    await initCountryCodes();

    // 문의유형 셀렉트 초기화
    initInquiryTypeSelect();

    // 첨부파일 업로드 초기화
    initFileUpload();
    
    // 폼 유효성 검사 설정
    setupFormValidation();
    
    // 등록 버튼 이벤트 설정
    setupRegisterButton();

    // 초기 상태에서 버튼 disabled/active 반영
    checkFormValidity();
}

async function ensureUserSession() {
    try {
        // userId가 이미 있으면 OK
        const existing = localStorage.getItem('userId');
        if (existing) return;

        const res = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
        const json = await res.json();

        // backend/api/check-session.php: { success, isLoggedIn, user:{id,...} }
        if (json && json.success && json.isLoggedIn && json.user && json.user.id) {
            localStorage.setItem('userId', String(json.user.id));
            return;
        }
    } catch (e) {
        // ignore
    }
}

async function initCountryCodes() {
    const sel = document.getElementById('countryCodeSelect');
    if (!sel) return;

    try {
        const res = await fetch('../backend/api/countries.php', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json?.success || !Array.isArray(json.countries)) return;

        const cur = sel.value || '+63';
        sel.innerHTML = '';
        json.countries.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.code;
            opt.textContent = c.code;
            sel.appendChild(opt);
        });
        sel.value = cur;
    } catch (e) {
        // 실패 시 기본값 유지
    }
}

function initInquiryTypeSelect() {
    const wrap = document.querySelector('.custom-select');
    if (!wrap) return;
    const trigger = wrap.querySelector('.select-trigger');
    const placeholder = wrap.querySelector('.placeholder');
    const options = wrap.querySelector('.select-options');
    if (!trigger || !placeholder || !options) return;

    const close = () => { options.style.display = 'none'; };
    const open = () => { options.style.display = 'block'; };

    trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (options.style.display === 'none' || !options.style.display) open();
        else close();
    });

    options.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        const val = li.getAttribute('data-value') || '';
        selectedInquiryType = val;
        placeholder.textContent = li.textContent || '';
        placeholder.classList.remove('gray');
        close();
        checkFormValidity();
    });

    document.addEventListener('click', close);
}

function initFileUpload() {
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('inquiryFiles');
    if (!uploadBtn || !fileInput) return;

    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files || []);
        if (!files.length) return;

        for (const f of files) {
            if (selectedFiles.length >= MAX_ATTACHMENTS) break;
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (!ALLOWED_EXTS.has(ext)) continue;
            if (f.size > MAX_FILE_BYTES) continue;
            selectedFiles.push(f);
        }

        // 최대 개수 초과 안내
        if ((selectedFiles.length >= MAX_ATTACHMENTS) && (files.length > 0)) {
            // 남는 파일은 무시됨
        }

        // input 리셋(같은 파일 재선택 허용)
        fileInput.value = '';
        renderSelectedFiles();
    });

    renderSelectedFiles();
}

function renderSelectedFiles() {
    const list = document.getElementById('selectedFilesList');
    if (!list) return;
    list.innerHTML = '';
    // i18n: 삭제 라벨
    // - 요구사항: 업로드한 파일에서 삭제 버튼이 en일 때 "Delete"로 보여야 함
    const deleteLabel = (typeof getText === 'function')
        ? getText('delete')
        : ((getCurrentLanguage && (getCurrentLanguage() === 'en' || getCurrentLanguage() === 'tl')) ? 'Delete' : '삭제');
    selectedFiles.forEach((f, idx) => {
        const li = document.createElement('li');
        li.style.display = 'flex';
        li.style.justifyContent = 'space-between';
        li.style.alignItems = 'center';
        li.style.gap = '8px';
        li.style.padding = '6px 0';
        li.innerHTML = `
            <span class="text fz13 fw400 lh19 gray96" style="word-break:break-all;">${escapeHtml(f.name)}</span>
            <button type="button" class="btn line sm" data-idx="${idx}">${escapeHtml(deleteLabel)}</button>
        `;
        list.appendChild(li);
    });
    list.querySelectorAll('button[data-idx]').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.getAttribute('data-idx'), 10);
            if (!Number.isFinite(i)) return;
            selectedFiles.splice(i, 1);
            renderSelectedFiles();
            checkFormValidity();
        });
    });
}

function escapeHtml(s) {
    return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// 사용자 정보 로드
async function loadUserInfo() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        console.log('User not logged in');
        return;
    }
    
    try {
        // 사용자 프로필 정보 가져오기
        const response = await fetch('../backend/api/profile.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_profile',
                accountId: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.profile) {
            fillUserInfo(result.profile);
        } else {
            console.log('Failed to load user profile:', result.message);
        }
        
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

// 사용자 정보로 폼 채우기
function fillUserInfo(profile) {
    console.log('fillUserInfo - profile data:', profile);
    
    // 이메일 필드 (회원 정보에 등록된 이메일 자동 입력)
    const emailInput = document.getElementById('txt1');
    if (emailInput) {
        // email 또는 emailAddress 필드 확인
        const email = profile.email || profile.emailAddress || '';
        if (email) {
            emailInput.value = email;
            console.log('이메일 자동 입력:', email);
        }
    }
    
    // 전화번호 필드 (회원 정보에 등록된 휴대폰 번호 자동 입력)
    const phoneInput = document.getElementById('txt2');
    if (phoneInput) {
        // phoneNumber 또는 contactNo 필드 확인
        const phoneNumber = profile.phoneNumber || profile.contactNo || '';
        if (phoneNumber) {
            // Normalize to digits only for the input (country code is selected separately)
            // Requirement (#151): do not inject '+' into the phone input because validation rejects it.
            const cleanPhone = String(phoneNumber).replace(/[^0-9]/g, '');
            phoneInput.value = cleanPhone;
        }
    }
}

// 폼 유효성 검사 설정
function setupFormValidation() {
    const emailInput = document.getElementById('txt1');
    const phoneInput = document.getElementById('txt2');
    const registerBtn = document.querySelector('button[data-i18n="register"]');
    
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmail(this);
            checkFormValidity();
        });
    }
    
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            validatePhone(this);
            checkFormValidity();
        });
    }
    
    // 문의 내용 필드들
    const contentInput = document.getElementById('txt3');
    const textarea = document.querySelector('textarea');
    
    if (contentInput) {
        contentInput.addEventListener('input', checkFormValidity);
    }
    
    if (textarea) {
        textarea.addEventListener('input', checkFormValidity);
    }
}

// 이메일 유효성 검사
function validateEmail(input) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const errorDiv = input.parentElement.querySelector('[data-i18n="invalidEmailFormat"]');
    
    if (emailRegex.test(input.value)) {
        input.classList.remove('error');
        if (errorDiv) errorDiv.style.display = 'none';
        return true;
    } else {
        input.classList.add('error');
        if (errorDiv) errorDiv.style.display = 'block';
        return false;
    }
}

// 전화번호 유효성 검사
function validatePhone(input) {
    const phoneRegex = /^[0-9\s-]+$/;
    // phone input은 wrapper(div.align...) 안에 있고 에러 div는 li 하위 sibling이라 parentElement에 없을 수 있음
    const errorDiv = input.closest('li')?.querySelector('[data-i18n="invalidPhoneFormat"]') || null;
    
    if (phoneRegex.test(input.value) && input.value.length >= 8) {
        input.classList.remove('error');
        if (errorDiv) errorDiv.style.display = 'none';
        return true;
    } else {
        input.classList.add('error');
        if (errorDiv) errorDiv.style.display = 'block';
        return false;
    }
}

// 폼 유효성 검사
function checkFormValidity() {
    const emailInput = document.getElementById('txt1');
    const phoneInput = document.getElementById('txt2');
    const contentInput = document.getElementById('txt3');
    const textarea = document.getElementById('txt4') || document.querySelector('textarea');
    const registerBtn = document.querySelector('button[data-i18n="register"]');
    
    const isEmailValid = emailInput && validateEmail(emailInput);
    const isPhoneValid = phoneInput && validatePhone(phoneInput);
    const hasTitle = contentInput && contentInput.value.trim();
    const hasBody = textarea && textarea.value.trim();
    const hasType = !!selectedInquiryType;
    
    if (isEmailValid && isPhoneValid && hasTitle && hasBody && hasType) {
        registerBtn.classList.remove('inactive');
        registerBtn.disabled = false;
    } else {
        registerBtn.classList.add('inactive');
        registerBtn.disabled = true;
    }
}

// 등록 버튼 이벤트 설정
function setupRegisterButton() {
    const registerBtn = document.querySelector('button[data-i18n="register"]');
    
    if (registerBtn) {
        registerBtn.addEventListener('click', handleInquirySubmit);
    }
}

// 문의 제출 처리
async function handleInquirySubmit() {
    const emailInput = document.getElementById('txt1');
    const phoneInput = document.getElementById('txt2');
    const contentInput = document.getElementById('txt3');
    const textarea = document.getElementById('txt4') || document.querySelector('textarea');
    const registerBtn = document.querySelector('button[data-i18n="register"]');
    const countryCodeSel = document.getElementById('countryCodeSelect');
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en || {};
    
    // 유효성 검사
    if (!validateEmail(emailInput) || !validatePhone(phoneInput)) {
        alert(texts.enterValidInfo || 'Please enter valid information.');
        return;
    }
    
    if (!selectedInquiryType) {
        alert(texts.selectInquiryType || 'Please select an inquiry type.');
        return;
    }

    const title = contentInput?.value.trim() || '';
    const message = textarea?.value.trim() || '';
    if (!title || !message) {
        alert(texts.enterInquiryContent || 'Please enter your inquiry.');
        return;
    }

    let userId = localStorage.getItem('userId');
    if (!userId) {
        // 세션 기반 fallback
        try {
            const res = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
            const json = await res.json();
            if (json && json.success && json.isLoggedIn && json.user && json.user.id) {
                userId = String(json.user.id);
                localStorage.setItem('userId', userId);
            }
        } catch (e) {}
        if (!userId) {
            alert(texts.loginRequired || 'Login required.');
            // /user/login.html 은 리다이렉트 페이지일 수 있어, 실동작 페이지로 이동
            window.location.href = 'login.php';
            return;
        }
    }
    
    // 로딩 상태
    registerBtn.disabled = true;
    registerBtn.textContent = texts.submitting || 'Submitting...';
    
    try {
        const cc = countryCodeSel?.value || '';
        // phone input should NOT contain '+'; country code is handled separately by select
        const phoneDigits = String(phoneInput.value || '').replace(/[^0-9]/g, '');
        const ccDigits = String(cc || '').replace(/[^0-9]/g, '');
        const fullPhone = (ccDigits ? `+${ccDigits} ` : '') + phoneDigits;

        // 문의 유형은 5종(UI) 그대로 서버로 전달합니다.
        // 서버(inquiry.php)가 product/reservation/payment/cancellation/other 를 DB enum으로 정확히 매핑합니다.
        const category = (selectedInquiryType && typeof selectedInquiryType === 'string')
            ? selectedInquiryType
            : 'product';

        // 첨부 포함: FormData(multipart)
        const formData = new FormData();
        formData.append('action', 'create_inquiry');
        formData.append('accountId', userId);
        formData.append('email', emailInput.value.trim());
        formData.append('phone', fullPhone);
        formData.append('category', category);
        formData.append('subject', title);
        formData.append('content', message);
        formData.append('priority', 'medium');

        selectedFiles.slice(0, MAX_ATTACHMENTS).forEach(f => {
            formData.append('files[]', f, f.name);
        });

        const response = await fetch('../backend/api/inquiry.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        // 실패 시 HTML(리다이렉트/에러페이지) 등으로 내려오는 경우가 있어 방어적으로 파싱
        const rawText = await response.text();
        let result = null;
        try {
            result = JSON.parse(rawText);
        } catch (e) {
            throw new Error(`Server response is not JSON. (HTTP ${response.status})`);
        }
        
        if (result.success) {
            // 기획서: 팝업 Case 1 (문의 전송 완료)
            const modal = document.getElementById('inquirySuccessModal');
            const okBtn = document.getElementById('inquirySuccessOkBtn');
            if (modal && okBtn) {
                modal.style.display = 'flex';
                okBtn.onclick = () => {
                    modal.style.display = 'none';
                    location.href = 'inquiry.php';
                };
            } else {
                alert(texts.inquirySubmitted || 'Your inquiry has been submitted.');
                location.href = 'inquiry.php';
            }
        } else {
            alert(result.message || texts.inquirySubmitFailed || '문의 제출에 실패했습니다.');
        }
        
    } catch (error) {
        console.error('Inquiry submission error:', error);
        alert(texts.networkError || '네트워크 오류가 발생했습니다. 다시 시도해주세요.');
    } finally {
        registerBtn.disabled = false;
        registerBtn.textContent = texts.register || '등록';
    }
}
