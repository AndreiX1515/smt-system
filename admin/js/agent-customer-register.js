/**
 * Agent Admin - Customer Register Page JavaScript
 */

let passportPhotoFile = null;

// 다국어 텍스트
const texts = {
    ko: {
        imageOnly: '이미지 파일만 업로드 가능합니다.',
        requiredFields: '고객명, 이메일, 연락처는 필수 항목입니다.',
        enterCustomerName: '고객명을 입력해주세요.',
        invalidEmail: '올바른 이메일 형식을 입력해주세요.',
        invalidBirthDate: '생년월일은 YYYYMMDD 형식으로 입력해주세요.',
        invalidPassportIssue: '여권 발행일은 YYYYMMDD 형식으로 입력해주세요.',
        invalidPassportExpire: '여권 만료일은 YYYYMMDD 형식으로 입력해주세요.',
        saved: '고객이 등록되었습니다.',
        saveFailed: '고객 등록에 실패했습니다: ',
        error: '고객 등록 중 오류가 발생했습니다: ',
        testData: '테스트 데이터 입력',
        fillTestData: 'Fill Test Data',
        testDataFilled: ' - 모든 필드가 채워졌습니다.'
    },
    en: {
        imageOnly: 'Only image files can be uploaded.',
        requiredFields: 'Customer name, email, and contact are required fields.',
        enterCustomerName: 'Please enter customer name.',
        invalidEmail: 'Please enter a valid email format.',
        invalidBirthDate: 'Date of birth must be in YYYYMMDD format.',
        invalidPassportIssue: 'Passport issue date must be in YYYYMMDD format.',
        invalidPassportExpire: 'Passport expiry date must be in YYYYMMDD format.',
        saved: 'Customer has been registered.',
        saveFailed: 'Customer registration failed: ',
        error: 'An error occurred while registering customer: ',
        parseError: 'Unable to parse server response.',
        unknownError: 'Unknown error',
        testData: 'Fill Test Data',
        testDataFilled: ' - All fields have been filled.'
    }
};

// 세션 확인 함수
async function checkSessionAndRedirect(userType = 'agent') {
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated || sessionData.userType !== 'agent') {
            window.location.href = '../index.html';
            return false;
        }
        return true;
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return false;
    }
}

function getCurrentLang() {
    return getCookie('lang') || 'eng';
}

function getText(key) {
    const lang = getCurrentLang();
    const langTexts = lang === 'eng' ? texts.en : texts.ko;
    return langTexts[key] || key;
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

async function populateCountryCodeSelect(selectEl, preferredCode = '+63') {
    if (!selectEl) return;
    const desired = (selectEl.getAttribute('data-selected') || selectEl.value || preferredCode || '+63').toString().trim() || '+63';
    selectEl.setAttribute('data-selected', desired);
    try {
        const res = await fetch('/backend/api/countries.php', { credentials: 'same-origin' });
        const json = await res.json();
        const countries = Array.isArray(json?.countries) ? json.countries : [];
        if (!countries.length) throw new Error('No countries');

        // rebuild options safely
        selectEl.innerHTML = '';
        for (const c of countries) {
            const code = (c?.code ?? '').toString().trim();
            const name = (c?.name ?? '').toString().trim();
            if (!code) continue;
            const opt = document.createElement('option');
            opt.value = code;
            opt.textContent = name ? `${code} (${name})` : code;
            selectEl.appendChild(opt);
        }

        // ensure desired exists
        selectEl.value = desired;
        if (selectEl.value !== desired) {
            const opt = document.createElement('option');
            opt.value = desired;
            opt.textContent = desired;
            selectEl.insertBefore(opt, selectEl.firstChild);
            selectEl.value = desired;
        }

        // jw_select 커스텀 셀렉트 UI 갱신(옵션 변경 반영)
        try {
            if (typeof window.refreshAllJwSelect === 'function') window.refreshAllJwSelect();
            else if (typeof window.jw_select === 'function') window.jw_select();
        } catch (_) {}
    } catch (e) {
        // fallback: keep existing options
        selectEl.value = desired;
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    // 세션 확인
    const isAuthenticated = await checkSessionAndRedirect('agent');
    if (!isAuthenticated) return;

    // 국가코드 옵션 전체 로드
    try {
        await populateCountryCodeSelect(document.getElementById('country_code'), '+63');
    } catch (e) {
        // ignore
    }
    
    // 저장 버튼 이벤트
    const saveButton = document.getElementById('saveBtn') || document.querySelector('.page-toolbar-actions .jw-button.typeB');
    if (saveButton) {
        saveButton.addEventListener('click', handleSave);
    }
    
    // 테스트 데이터 버튼 이벤트 (있는 경우)
    const testDataBtn = document.getElementById('testDataBtn');
    if (testDataBtn) {
        testDataBtn.addEventListener('click', fillTestData);
    }
    
    // 비밀번호 자동 입력 버튼
    const autoPasswordBtn = document.querySelector('.input-box .jw-button.typeD');
    if (autoPasswordBtn) {
        autoPasswordBtn.addEventListener('click', async function() {
            const passwordInput = document.getElementById('cust_pw');
            if (passwordInput) {
                const pw = generateRandomPassword();
                passwordInput.value = pw;
                // 클립보드 복사(가능하면)
                try {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        await navigator.clipboard.writeText(pw);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = pw;
                        ta.setAttribute('readonly', 'readonly');
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                } catch (e) {
                    // ignore
                }
            }
        });
    }
    
    // 여권 사진 파일 선택 (등록 화면은 로컬 파일 프리뷰만 제공)
    const passportInput = document.getElementById('file-passport');
    if (passportInput) {
        passportInput.addEventListener('change', handlePassportFileSelect);
    }

    // 여권 사진 삭제 버튼
    const deleteBtn = document.getElementById('deletePassportBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', handlePassportFileClear);
    }

    // 다운로드 버튼은 업로드 전이라 동작 의미가 없어 disable 유지(미리보기는 클릭으로 가능)

    // Date of birth → Age 자동 갱신 (요구사항 #161)
    const birthInput = document.getElementById('birth');
    const ageInput = document.getElementById('age');
    if (birthInput && ageInput) {
        const updateAge = () => {
            const raw = String(birthInput.value || '').trim();
            if (!raw) { ageInput.value = ''; return; }
            const age = calculateAgeFromDobInput(raw);
            ageInput.value = (age >= 0 && age <= 150) ? String(age) : '';
        };
        birthInput.addEventListener('input', updateAge);
        birthInput.addEventListener('blur', updateAge);
    }
});

function calculateAgeFromDobInput(value) {
    const s = String(value || '').trim();
    let d = null;
    if (/^\d{8}$/.test(s)) {
        const y = s.slice(0, 4);
        const m = s.slice(4, 6);
        const dd = s.slice(6, 8);
        d = new Date(`${y}-${m}-${dd}T00:00:00`);
    } else if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
        d = new Date(`${s.slice(0, 10)}T00:00:00`);
    } else {
        d = new Date(s);
    }
    if (!d || Number.isNaN(d.getTime())) return -1;
    const today = new Date();
    let age = today.getFullYear() - d.getFullYear();
    const md = today.getMonth() - d.getMonth();
    if (md < 0 || (md === 0 && today.getDate() < d.getDate())) age--;
    return age;
}

function generateRandomPassword() {
    const length = 12;
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

function generateRandomString(length) {
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return result;
}

function generateRandomNumber(length) {
    let result = '';
    for (let i = 0; i < length; i++) {
        result += Math.floor(Math.random() * 10);
    }
    return result;
}

function generateRandomDate(startYear = 1950, endYear = 2005) {
    const year = Math.floor(Math.random() * (endYear - startYear + 1)) + startYear;
    const month = String(Math.floor(Math.random() * 12) + 1).padStart(2, '0');
    const day = String(Math.floor(Math.random() * 28) + 1).padStart(2, '0');
    return `${year}${month}${day}`;
}

function generatePassportNumber() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const letter1 = letters[Math.floor(Math.random() * letters.length)];
    const letter2 = letters[Math.floor(Math.random() * letters.length)];
    const numbers = generateRandomNumber(7);
    return `${letter1}${letter2}${numbers}`;
}

function fillTestData() {
    // 기본 정보
    const firstName = generateRandomString(5);
    const lastName = generateRandomString(6);
    const fullName = `${firstName} ${lastName}`;
    
    // 고객명
    const custNameInput = document.getElementById('cust_name');
    if (custNameInput) custNameInput.value = fullName;
    
    // 국가 코드는 옵션에서 랜덤 선택(전체 로드된 리스트 우선)
    const countryCodeSelect = document.getElementById('country_code');
    if (countryCodeSelect) {
        const opts = Array.from(countryCodeSelect.options || []).map(o => o.value).filter(Boolean);
        const fallback = ['+63', '+82', '+81', '+1'];
        const pool = opts.length ? opts : fallback;
        const randomCountryCode = pool[Math.floor(Math.random() * pool.length)];
        countryCodeSelect.value = randomCountryCode;
        countryCodeSelect.setAttribute('data-selected', randomCountryCode);
        countryCodeSelect.dispatchEvent(new Event('change'));
    }
    
    // 연락처 (10자리 숫자)
    const phoneInput = document.getElementById('cust_phone');
    if (phoneInput) phoneInput.value = generateRandomNumber(10);
    
    // 이메일
    const email = `test${generateRandomNumber(5)}@example.com`;
    const emailInput = document.getElementById('cust_email');
    if (emailInput) emailInput.value = email;
    
    // 비밀번호
    const passwordInput = document.getElementById('cust_pw');
    if (passwordInput) passwordInput.value = generateRandomPassword();
    
    // Note (에디터) - Quill 에디터에 내용 삽입
    const noteKo = `테스트 메모입니다. 고객 등록 테스트를 위해 생성되었습니다. 생성 시간: ${new Date().toLocaleString('ko-KR')}`;
    const noteEn = `Test memo for customer registration. Created at ${new Date().toLocaleString('en-US')}`;
    const note = getCurrentLang() === 'eng' ? noteEn : noteKo;
    
    // Quill 에디터에 내용 삽입 (multi-editor.js: .jweditor에 __quill)
    setTimeout(() => {
        const editorArea = document.querySelector('.jweditor');
        if (!editorArea) return;
        const quill =
            editorArea.__quill ||
            (window.Quill && typeof window.Quill.find === 'function' ? window.Quill.find(editorArea) : null);
        if (quill && quill.root) quill.root.innerHTML = `<p>${note}</p>`;
        else editorArea.innerHTML = `<p>${note}</p>`;
    }, 100);

    // SMT 수정: Contract Information 섹션 제거(테스트 데이터도 더 이상 채우지 않음)
    
    // 여행자(폼) 테스트 데이터
    const titleEl = document.getElementById('title');
    const tf = document.getElementById('first_name');
    const tl = document.getElementById('last_name');
    const tg = document.getElementById('gender');
    const ta = document.getElementById('age');
    const tb = document.getElementById('birth');
    const tn = document.getElementById('nationality');
    const tpn = document.getElementById('passport_no');
    const tpi = document.getElementById('passport_issue');
    const tpe = document.getElementById('passport_expire');

    // 요구사항: Gender 옵션은 Male/Female 만
    const genders = ['Male', 'Female'];
    const randomGender = genders[Math.floor(Math.random() * genders.length)];
    const age = Math.floor(Math.random() * 41) + 20;
    const currentYear = new Date().getFullYear();
    const birthYear = currentYear - age;
    const birthDate = generateRandomDate(birthYear - 1, birthYear);
    const nationalities = ['Philippines', 'South Korea', 'Japan', 'United States', 'China', 'Thailand', 'Vietnam'];
    const randomNationality = nationalities[Math.floor(Math.random() * nationalities.length)];

    if (titleEl) titleEl.value = 'MR';
    if (tf) tf.value = firstName;
    if (tl) tl.value = lastName;
    if (tg) tg.value = randomGender;
    if (ta) ta.value = String(age);
    if (tb) tb.value = birthDate;
    if (tn) tn.value = randomNationality;
    if (tpn) tpn.value = generatePassportNumber();

    const passportIssueYear = (parseInt(birthDate.substring(0, 4), 10) || (currentYear - 30)) + 18;
    const passportIssue = generateRandomDate(passportIssueYear, passportIssueYear + 5);
    const passportExpireYear = (parseInt(passportIssue.substring(0, 4), 10) || currentYear) + 10;
    const passportExpire = generateRandomDate(passportExpireYear - 1, passportExpireYear);

    if (tpi) tpi.value = passportIssue;
    if (tpe) tpe.value = passportExpire;
    
    alert(getText('testData') + getText('testDataFilled'));
}

function normalizeYYYYMMDDToYYYYMMDD(dateStr) {
    const v = (dateStr || '').trim();
    if (!v) return '';
    // YYYY-MM-DD -> YYYYMMDD
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v.replace(/-/g, '');
    // YYYYMMDD
    if (/^\d{8}$/.test(v)) return v;
    return '';
}

function formatDateToDb(dateStr) {
    const v = normalizeYYYYMMDDToYYYYMMDD(dateStr);
    if (!v) return '';
    return `${v.substring(0, 4)}-${v.substring(4, 6)}-${v.substring(6, 8)}`;
}

function handlePassportFileSelect(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        alert(getText('imageOnly'));
        e.target.value = '';
        return;
    }
    passportPhotoFile = file;

    const thumb = document.querySelector('.upload-box .thumb');
    const meta = document.querySelector('.upload-box .upload-meta');
    const nameEl = document.getElementById('passportFileName');
    const infoEl = document.getElementById('passportFileInfo');
    const dlBtn = document.getElementById('downloadPassportBtn');
    if (nameEl) nameEl.textContent = file.name || 'Image';
    if (infoEl) infoEl.textContent = `${(file.type || 'image').split('/')[1] || 'img'}, ${Math.round((file.size || 0) / 1024)}KB`;
    if (dlBtn) dlBtn.disabled = true;

    if (thumb) {
        const url = URL.createObjectURL(file);
        thumb.style.backgroundImage = `url("${url}")`;
        thumb.style.backgroundSize = 'cover';
        thumb.style.backgroundPosition = 'center';
        // 페이지에서만 쓰고 종료 시 자동 해제되므로 즉시 revoke는 하지 않음
    }
    if (meta) meta.style.display = 'block';
}

function handlePassportFileClear() {
    passportPhotoFile = null;
    const input = document.getElementById('file-passport');
    if (input) input.value = '';
    const thumb = document.querySelector('.upload-box .thumb');
    const meta = document.querySelector('.upload-box .upload-meta');
    if (thumb) thumb.style.backgroundImage = '';
    if (meta) meta.style.display = 'none';
}

async function handleSave() {
    try {
        // 기본 정보 수집
        const customerName = document.getElementById('cust_name')?.value.trim() || '';
        const countryCode = document.getElementById('country_code')?.value || '+63';
        const phone = document.getElementById('cust_phone')?.value.trim() || '';
        const email = document.getElementById('cust_email')?.value.trim() || '';
        const passwordInput = document.getElementById('cust_pw');
        let password = passwordInput?.value || '';
        if (!password) {
            password = generateRandomPassword();
            if (passwordInput) passwordInput.value = password;
            // 자동 생성된 비밀번호는 클립보드 복사 시도
            try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    await navigator.clipboard.writeText(password);
                }
            } catch (e) { /* ignore */ }
        }
        // Note 에디터에서 내용 가져오기
        const editorArea = document.querySelector('.jweditor');
        let memo = '';
        if (editorArea) {
            const quill =
                editorArea.__quill ||
                (window.Quill && typeof window.Quill.find === 'function' ? window.Quill.find(editorArea) : null);
            if (quill && quill.root) memo = quill.root.innerHTML || '';
            else memo = editorArea.innerHTML || '';
        }
        
        // 필수 필드 검증
        if (!customerName || !email || !phone) {
            alert(getText('requiredFields'));
            return;
        }
        
        const nameParts = customerName.trim().split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';
        
        if (!firstName) {
            alert(getText('enterCustomerName'));
            return;
        }
        
        // 이메일 형식 검증
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert(getText('invalidEmail'));
            return;
        }
        
        // SMT 수정: Contract Information 섹션 제거(데이터 전송도 제거)

        // 여행자(폼) 정보 수집 (선택)
        const travelerTitle = document.getElementById('title')?.value || '';
        const travelerFirstName = document.getElementById('first_name')?.value.trim() || '';
        const travelerLastName = document.getElementById('last_name')?.value.trim() || '';
        const travelerGender = document.getElementById('gender')?.value || '';
        const travelerAge = document.getElementById('age')?.value || '';
        const travelerBirth = document.getElementById('birth')?.value.trim() || '';
        const travelerNationality = document.getElementById('nationality')?.value.trim() || '';
        const travelerPassportNo = document.getElementById('passport_no')?.value.trim() || '';
        const travelerPassportIssue = document.getElementById('passport_issue')?.value.trim() || '';
        const travelerPassportExpire = document.getElementById('passport_expire')?.value.trim() || '';
        
        // FormData 생성
        const formData = new FormData();
        formData.append('action', 'createCustomer');
        formData.append('firstName', firstName);
        formData.append('lastName', lastName);
        formData.append('email', email);
        formData.append('countryCode', countryCode);
        formData.append('phone', phone);
        formData.append('password', password);
        formData.append('memo', memo);
        
        // 여행자 정보(선택)
        if (travelerTitle) formData.append('travelerTitle', travelerTitle);
        if (travelerFirstName || firstName) formData.append('travelerFirstName', travelerFirstName || firstName);
        if (travelerLastName || lastName) formData.append('travelerLastName', travelerLastName || lastName);
        if (travelerGender) formData.append('travelerGender', travelerGender);
        if (travelerAge !== '') formData.append('travelerAge', travelerAge);
        if (travelerBirth) formData.append('travelerBirth', formatDateToDb(travelerBirth));
        if (travelerNationality) formData.append('travelerNationality', travelerNationality);
        if (travelerPassportNo) formData.append('travelerPassportNo', travelerPassportNo);
        if (travelerPassportIssue) formData.append('travelerPassportIssue', formatDateToDb(travelerPassportIssue));
        if (travelerPassportExpire) formData.append('travelerPassportExpire', formatDateToDb(travelerPassportExpire));

        // 여권 사진 업로드(선택)
        const passportFile = document.getElementById('file-passport')?.files?.[0] || passportPhotoFile;
        if (passportFile) formData.append('passportPhoto', passportFile);
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response:', responseText);
            throw new Error(getText('parseError'));
        }
        
        if (result.success) {
            window.location.href = 'customer-list.html';
        } else {
            alert(getText('saveFailed') + (result.message || getText('unknownError')));
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert(getText('error') + error.message);
    }
}
