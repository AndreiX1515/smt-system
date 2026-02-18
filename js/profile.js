// 사용자 프로필 관련 API 함수들

// 다국어 텍스트 로드
async function loadProfileTexts() {
    try {
        await loadServerTexts();
    } catch (error) {
        console.error('Failed to load profile texts:', error);
    }
}

// 사용자 프로필 조회
async function getUserProfile(userId) {
    try {
        const response = await fetch('../backend/api/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_profile',
                accountId: userId
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Get user profile error:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        return { success: false, message: texts.networkError || 'Network error. Please try again.' };
    }
}

// 사용자 프로필 업데이트
async function updateUserProfile(profileData) {
    try {
        const response = await fetch('../backend/api/profile.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_profile',
                ...profileData
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Update user profile error:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        return { success: false, message: texts.networkError || 'Network error. Please try again.' };
    }
}

// 비밀번호 변경
async function changePassword(passwordData) {
    try {
        const response = await fetch('../backend/api/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'change_password',
                ...passwordData
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Change password error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// 마이페이지 정보 로드
async function loadMyPageInfo() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        // SMT 수정: 비로그인 마이페이지는 로그인으로 강제 리다이렉트하지 않는다 (guest mypage 요구사항)
        if (window.location.pathname.includes('mypage.html')) {
            return;
        }
        location.href = 'login.html';
        return;
    }
    
    try {
        const result = await getUserProfile(userId);
        
        if (result.success) {
            renderProfileInfo(result.data);
        } else {
            console.error('Failed to load profile:', result.message);
        }
        
    } catch (error) {
        console.error('Load mypage error:', error);
    }
}

// 프로필 정보 렌더링
function renderProfileInfo(profile) {
    // 사용자명 표시
    const usernameEl = document.querySelector('.username');
    if (usernameEl) usernameEl.textContent = profile.username || '';
    
    // 이메일 표시
    const emailEl = document.querySelector('.email');
    if (emailEl) emailEl.textContent = profile.emailAddress || '';
    
    // 이름 표시
    const nameEl = document.querySelector('.name');
    if (nameEl) nameEl.textContent = `${profile.fName || ''} ${profile.lName || ''}`.trim();
    
    // 연락처 표시
    const contactEl = document.querySelector('.contact');
    if (contactEl) contactEl.textContent = profile.contactNo || '';
    
    // 회원 유형 표시
    const typeEl = document.querySelector('.account-type');
    if (typeEl) typeEl.textContent = getAccountTypeText(profile.accountType);
    
    // 가입일 표시
    const joinDateEl = document.querySelector('.join-date');
    if (joinDateEl && profile.createdAt) {
        const joinDate = new Date(profile.createdAt);
        joinDateEl.textContent = joinDate.toLocaleDateString('en-US');
    }
}

// 계정 타입 텍스트 반환
function getAccountTypeText(accountType) {
    const typeMap = {
        'guest': 'Member',
        'agent': 'Agent',
        'employee': 'Staff',
        'admin': 'Admin'
    };
    return typeMap[accountType] || accountType;
}

// 프로필 편집 폼 로드
async function loadEditProfileForm() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        location.href = 'login.html';
        return;
    }
    
    try {
        const result = await getUserProfile(userId);
        
        if (result.success && result.profile) {
            fillEditForm(result.profile);
            // 국가코드 옵션 로드
            await initProfileCountryCodes();
            // 이메일 중복체크 버튼 초기화
            initEditProfileEmailCheck(result.profile);
        } else {
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
            alert(texts.profileLoadFailed || 'Unable to load your profile.');
            history.back();
        }
        
    } catch (error) {
        console.error('Load edit profile error:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.profileLoadError || 'An error occurred while loading your profile.');
    }
}

let __editProfileOriginalEmail = '';
let __editProfileEmailChecked = false;

async function initProfileCountryCodes() {
    const sel = document.getElementById('countryCodeSelect');
    if (!sel) return;

    try {
        const res = await fetch('../backend/api/countries.php');
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
        // 실패 시 기본 옵션 유지
    }
}

function initEditProfileEmailCheck(profile) {
    const emailInput = document.getElementById('email');
    const checkBtn = document.getElementById('emailCheckBtn');
    if (!emailInput || !checkBtn) return;

    __editProfileOriginalEmail = (profile?.email || profile?.emailAddress || '').trim();
    __editProfileEmailChecked = false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const setDisabled = (label = 'Check duplicate') => {
        checkBtn.disabled = true;
        checkBtn.classList.add('inactive');
        checkBtn.classList.remove('primary');
        checkBtn.textContent = label;
    };

    const setNeedCheck = () => {
        // “중복체크 필요” 상태는 클릭 가능 + visually active
        checkBtn.disabled = false;
        checkBtn.classList.remove('inactive');
        checkBtn.classList.add('primary');
        checkBtn.textContent = 'Check duplicate';
    };

    const setChecked = () => {
        __editProfileEmailChecked = true;
        checkBtn.disabled = true;
        checkBtn.classList.remove('inactive');
        checkBtn.classList.add('primary');
        checkBtn.textContent = 'Checked';
    };

    // 초기: 기존 이메일이면 체크 불필요
    if (emailInput.value.trim() && emailInput.value.trim() === __editProfileOriginalEmail) {
        setChecked();
    } else {
        // 신규/빈값: 비활성
        setDisabled('Check duplicate');
    }

    emailInput.addEventListener('input', () => {
        const v = emailInput.value.trim();
        // 이메일이 비어있거나 형식이 틀리면 중복체크 버튼 비활성
        if (!v || !emailRegex.test(v)) {
            __editProfileEmailChecked = false;
            setDisabled('Check duplicate');
        }
        // 이메일이 기존과 같으면 체크 통과(체크 불필요)
        else if (v === __editProfileOriginalEmail) {
            setChecked();
        }
        // 이메일이 변경되었고 유효하면 중복체크 필요 상태로 전환
        else {
            __editProfileEmailChecked = false;
            setNeedCheck();
        }
        checkSaveButtonState();
    });

    checkBtn.addEventListener('click', async () => {
        const email = emailInput.value.trim();
        const lang = getCurrentLanguage();

        // 이메일 형식 검증(요구 문구)
        if (!emailRegex.test(email)) {
            alert('The email format is not correct.');
            return;
        }

        // 기존 이메일이면 체크 불필요
        if (email === __editProfileOriginalEmail) {
            setChecked();
            checkSaveButtonState();
            return;
        }

        try {
            checkBtn.disabled = true;
            checkBtn.textContent = 'Checking...';
            const userId = localStorage.getItem('userId');
            const result = await api.checkEmailAvailability(email, userId);
            if (result?.success && result?.available) {
                alert('Email is available.');
                setChecked();
            } else {
                alert(result?.message || 'This email is already in use.');
                __editProfileEmailChecked = false;
                checkBtn.disabled = false;
                setNeedCheck();
            }
        } catch (e) {
            __editProfileEmailChecked = false;
            checkBtn.disabled = false;
            setNeedCheck();
            alert('Network error. Please try again.');
        } finally {
            checkSaveButtonState();
        }
    });
}

// 편집 폼에 현재 정보 채우기
function fillEditForm(profile) {
    console.log('fillEditForm - profile data:', profile);
    
    const nameInput = document.getElementById('name');
    if (nameInput) {
        // 백엔드 API에서 반환하는 필드명 사용
        let fullName = '';
        
        // firstName과 lastName 조합 로직
        const firstNameTrim = (profile.firstName || '').trim();
        const lastNameTrim = (profile.lastName || '').trim();
        
        if (firstNameTrim && lastNameTrim) {
            // firstName에 공백이 있으면 이미 성과 이름이 모두 포함된 것으로 간주
            if (firstNameTrim.includes(' ')) {
                // firstName에 이미 공백이 있으면 그대로 사용 (중복 방지)
                fullName = firstNameTrim;
            } else {
                // firstName이 단일 단어면 lastName과 조합
                fullName = `${firstNameTrim} ${lastNameTrim}`.trim();
            }
        } else if (firstNameTrim) {
            fullName = firstNameTrim;
        } else if (lastNameTrim) {
            fullName = lastNameTrim;
        } else if (profile.username) {
            fullName = (profile.username || '').trim();
        }
        
        console.log('fillEditForm - firstName:', firstNameTrim, 'lastName:', lastNameTrim, 'fullName:', fullName);
        nameInput.value = fullName;
    }
    
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        const raw = (profile.phoneNumber || '').toString();
        // "+63 900..." 같이 들어오는 경우 분리
        const m = raw.match(/^\s*(\+\d+)\s*(.*)\s*$/);
        if (m) {
            const sel = document.getElementById('countryCodeSelect');
            if (sel) sel.value = m[1];
            phoneInput.value = (m[2] || '').replace(/[-\s()]/g, '');
        } else {
            phoneInput.value = raw.replace(/[-\s()]/g, '');
        }
    }
    
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.value = profile.email || '';
        // 이메일은 수정 가능
        emailInput.disabled = false;
        emailInput.readOnly = false;
    }
    
    // 저장 버튼 활성화 체크
    checkSaveButtonState();
    
    // 입력 필드 변경 시 저장 버튼 상태 업데이트
    if (nameInput) {
        nameInput.addEventListener('input', checkSaveButtonState);
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', checkSaveButtonState);
    }
    if (emailInput) {
        emailInput.addEventListener('input', checkSaveButtonState);
    }
}

// 저장 버튼 활성화 상태 확인
function checkSaveButtonState() {
    const saveBtn = document.getElementById('saveBtn');
    if (!saveBtn) return;
    
    const name = document.getElementById('name')?.value.trim();
    const phone = document.getElementById('phone')?.value.trim();
    const email = document.getElementById('email')?.value.trim();
    
    // 이메일 중복체크(변경된 경우 필수)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isEmailValid = email && emailRegex.test(email);
    const needsEmailCheck = (email && __editProfileOriginalEmail && email !== __editProfileOriginalEmail);
    const isEmailCheckedOk = needsEmailCheck ? __editProfileEmailChecked : true;
    
    // 모든 필수 필드가 채워지고 이메일이 유효한 경우 활성화
    const isValid = name && phone && email && isEmailValid && isEmailCheckedOk;
    
    if (isValid) {
        saveBtn.classList.remove('inactive');
        saveBtn.classList.add('active');
        saveBtn.disabled = false;
    } else {
        saveBtn.classList.remove('active');
        saveBtn.classList.add('inactive');
        saveBtn.disabled = true;
    }
}

// 프로필 업데이트 처리
async function handleUpdateProfile() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.loginRequired || 'Login required.');
        location.href = 'login.html';
        return;
    }
    
    const name = document.getElementById('name')?.value.trim();
    const phone = document.getElementById('phone')?.value.trim();
    const email = document.getElementById('email')?.value.trim();
    const countryCode = document.getElementById('countryCodeSelect')?.value || '';
    
    if (!name || !phone || !email) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.enterAllRequiredFields || 'Please enter all required fields.');
        return;
    }
    
    // 이메일 형식 검증(요구 문구)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('The email format is not correct.');
        return;
    }

    // 연락처 형식 검증(요구 문구)
    const phoneRegex = /^[0-9\s-]+$/;
    if (!phoneRegex.test(phone)) {
        const currentLang = getCurrentLanguage();
        const isEn = (currentLang === 'en' || currentLang === 'eng' || currentLang === 'tl');
        alert('Contact format is not correct.');
        return;
    }

    // 이메일이 변경된 경우 중복 확인 필수
    if (__editProfileOriginalEmail && email !== __editProfileOriginalEmail && !__editProfileEmailChecked) {
        alert('Please check email duplication first.');
        return;
    }
    
    // 이름을 성과 이름으로 분리 (간단한 처리)
    const nameParts = name.split(' ');
    const fname = nameParts[0] || '';
    const lname = nameParts.slice(1).join(' ') || '';
    
    const profileData = {
        user_id: userId,
        fname: fname,
        lname: lname,
        contact_no: (countryCode ? `${countryCode} ${phone}` : phone),
        email: email
    };
    
    // 로딩 표시
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        saveBtn.textContent = texts.saving || 'Saving...';
    }
    
    try {
        const result = await updateUserProfile(profileData);
        
        if (result.success) {
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
            alert(texts.profileUpdated || 'Your profile has been updated.');
            // 저장 직후 다른 화면에서도 즉시 반영되도록 localStorage 동기화
            try {
                localStorage.setItem('userEmail', email);
                const raw = localStorage.getItem('userInfo');
                const ui = raw ? JSON.parse(raw) : {};
                ui.email = email;
                ui.emailAddress = email;
                ui.firstName = fname;
                ui.lastName = lname;
                ui.phoneNumber = (countryCode ? `${countryCode} ${phone}` : phone);
                localStorage.setItem('userInfo', JSON.stringify(ui));
            } catch (_) {}

            // 요구사항: 저장 후 이전 페이지로 복귀
            history.back();
        } else {
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
            alert(result.message || texts.profileUpdateFailed || 'Failed to update profile.');
        }
        
    } catch (error) {
        console.error('Update profile error:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
        alert(texts.profileUpdateError || 'An error occurred while updating profile.');
    } finally {
        // 로딩 해제
        if (saveBtn) {
            saveBtn.disabled = false;
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
            saveBtn.textContent = texts.save || 'Save';
        }
    }
}

// 비밀번호 변경 처리
async function handleChangePassword() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        alert('Login required.');
        location.href = 'login.html';
        return;
    }
    
    const currentPassword = document.getElementById('currentPassword')?.value;
    const newPassword = document.getElementById('newPassword')?.value;
    const confirmPassword = document.getElementById('confirmPassword')?.value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all fields.');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match.');
        return;
    }
    
    if (newPassword.length < 6) {
        alert('New password must be at least 6 characters.');
        return;
    }
    
    const passwordData = {
        user_id: userId,
        current_password: currentPassword,
        new_password: newPassword
    };
    
    // 로딩 표시
    const changeBtn = document.getElementById('changePasswordBtn');
    if (changeBtn) {
        changeBtn.disabled = true;
        changeBtn.textContent = 'Changing...';
    }
    
    try {
        const result = await changePassword(passwordData);
        
        if (result.success) {
            alert('Password has been changed.');
            // 폼 초기화
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            // 마이페이지로 이동
            location.href = 'mypage.html';
        } else {
            alert(result.message || 'Failed to change password.');
        }
        
    } catch (error) {
        console.error('Change password error:', error);
        alert('An error occurred while changing password.');
    } finally {
        // 로딩 해제
        if (changeBtn) {
            changeBtn.disabled = false;
            changeBtn.textContent = 'Change';
        }
    }
}

// edit-profile은 bfcache(history back/forward)로 복귀 시 DOMContentLoaded가 다시 실행되지 않을 수 있어,
// pageshow에서 다시 로드하여 "변경된 내용이 반영되지 않음" 문제를 방지한다.
function __refreshEditProfileOnShow(e) {
    try {
        if (!window.location.pathname.includes('edit-profile.html')) return;
        // persisted(캐시) 여부와 관계없이 항상 최신값을 재조회
        loadEditProfileForm();
    } catch (err) {
        console.warn('Failed to refresh edit-profile on pageshow:', err);
    }
}

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', async function() {
    // 다국어 텍스트 로드
    await loadProfileTexts();
    
    // SMT 수정: 마이페이지는 guest 뷰가 있어야 하므로 profile.js가 강제로 로드/리다이렉트하지 않음
    
    // 프로필 편집 페이지에서 편집 폼 로드
    if (window.location.pathname.includes('edit-profile.html')) {
        loadEditProfileForm();
    }
    
    // 로그인 체크
    const loginStatus = checkLoginStatus();
    if (!loginStatus.isLoggedIn && 
        (window.location.pathname.includes('edit-profile.html') ||
         window.location.pathname.includes('change-password.html'))) {
        location.href = 'login.html';
    }
});

// bfcache 복귀 포함: 화면이 다시 보여질 때 프로필을 재조회
window.addEventListener('pageshow', __refreshEditProfileOnShow);

// 로그인 상태 확인 (기존 api.js와 중복 방지를 위해 조건부 정의)
if (typeof checkLoginStatus === 'undefined') {
    function checkLoginStatus() {
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userEmail = localStorage.getItem('userEmail');
        const username = localStorage.getItem('username');
        
        return {
            isLoggedIn,
            userEmail,
            username
        };
    }
}