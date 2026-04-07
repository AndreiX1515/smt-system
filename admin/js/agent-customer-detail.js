/**
 * Agent Admin - Customer Detail Page JavaScript
 */

let currentAccountId = null;
let currentCustomerData = null; // 현재 고객 데이터 저장
let currentLang = 'ko';
let bookingsPage = 1;
let inquiriesPage = 1;
const itemsPerPage = 10;

// 다국어 텍스트
const texts = {
    ko: {
        loading: '로딩 중...',
        noData: '데이터가 없습니다.',
        noBookings: '예약 내역이 없습니다.',
        noInquiries: '문의 내역이 없습니다.',
        saved: '저장되었습니다.',
        saveFailed: '저장에 실패했습니다.',
        error: '오류가 발생했습니다.',
        confirmPasswordReset: '비밀번호를 초기화하시겠습니까?',
        passwordResetSuccess: '비밀번호가 생성되어 저장되었습니다. (클립보드에 복사됨)',
        passwordResetFailed: '비밀번호 생성/저장에 실패했습니다.'
    },
    en: {
        loading: 'Loading...',
        noData: 'No data available.',
        noBookings: 'No reservation history.',
        noInquiries: 'No inquiry history.',
        saved: 'Saved successfully.',
        saveFailed: 'Failed to save.',
        error: 'An error occurred.',
        confirmPasswordReset: 'Do you want to reset the password?',
        passwordResetSuccess: 'A new password has been generated and saved. (Copied to clipboard)',
        passwordResetFailed: 'Failed to generate/save password.'
    }
};

function getCurrentLang() {
    // 관리자 UI는 lang 쿠키(eng/tl) 사용, 기본값 eng
    const raw = (document.cookie.split(';').find(c => c.trim().startsWith('lang=')) || '').split('=')[1] || 'eng';
    const v = decodeURIComponent(String(raw || '').trim());
    if (v === 'eng' || v === 'en') return 'eng';
    if (v === 'tl') return 'tl';
    return 'kor';
}

function getText(key) {
    const langKey = (currentLang === 'eng' || currentLang === 'tl') ? 'en' : 'ko';
    return texts[langKey]?.[key] || texts['ko'][key] || key;
}

async function populateCountryCodeSelect(selectEl, preferredCode = '+63') {
    if (!selectEl) return;
    const desired = (selectEl.getAttribute('data-selected') || selectEl.value || preferredCode || '+63').toString().trim() || '+63';
    selectEl.setAttribute('data-selected', desired);
    try {
        const res = await fetch('/smt-system/backend/api/countries.php', { credentials: 'same-origin' });
        const json = await res.json();
        const countries = Array.isArray(json?.countries) ? json.countries : [];
        if (!countries.length) throw new Error('No countries');

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

        selectEl.value = desired;
        if (selectEl.value !== desired) {
            const opt = document.createElement('option');
            opt.value = desired;
            opt.textContent = desired;
            selectEl.insertBefore(opt, selectEl.firstChild);
            selectEl.value = desired;
        }

        // jw_select 커스텀 셀렉트는 옵션 DOM 변경을 자동 반영하지 않음 → UI 재동기화
        try {
            if (typeof window.refreshAllJwSelect === 'function') window.refreshAllJwSelect();
            else if (typeof window.jw_select === 'function') window.jw_select();
        } catch (_) {}
    } catch (e) {
        selectEl.value = desired;
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    // 세션 확인
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated || sessionData.userType !== 'agent') {
            window.location.href = '../index.html';
            return;
        }
        
        currentLang = getCurrentLang();
        
        // URL에서 accountId 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        currentAccountId = urlParams.get('id') || urlParams.get('accountId');
        
        // 국가코드 옵션 전체 로드(고객 정보 렌더 전에 미리)
        try {
            await populateCountryCodeSelect(document.getElementById('country_code'), '+63');
        } catch (e) { /* ignore */ }

        if (currentAccountId) {
            loadCustomerDetail();
        } else {
            showError('Customer ID is missing.');
        }
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
    }
    
    // 저장 버튼 이벤트
    const saveButton = document.getElementById('saveBtn') || document.querySelector('.page-toolbar-actions .jw-button.typeB');
    if (saveButton) {
        saveButton.addEventListener('click', handleSave);
    }
    
    // 비밀번호 초기화 버튼
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    if (resetPasswordBtn) {
        resetPasswordBtn.addEventListener('click', handlePasswordReset);
    }
    
    // 여권 사진 다운로드/삭제 버튼
    const downloadPassportBtn = document.getElementById('downloadPassportBtn');
    const deletePassportBtn = document.getElementById('deletePassportBtn');
    if (downloadPassportBtn) {
        downloadPassportBtn.addEventListener('click', handleDownloadPassport);
    }
    if (deletePassportBtn) {
        deletePassportBtn.addEventListener('click', handleDeletePassport);
    }
    
    // 여권 사진 파일 선택
    const passportFileInput = document.getElementById('file-passport');
    if (passportFileInput) {
        passportFileInput.addEventListener('change', handlePassportFileSelect);
    }

    // Passport photo 수정이 안되는 케이스 방어 (#163):
    // - label/thumb 클릭이 CSS/오버레이로 막히더라도 upload-box 아무 곳이나 클릭하면 파일 선택이 열리게 함
    const uploadBox = document.querySelector('.upload-box');
    if (uploadBox && passportFileInput) {
        uploadBox.addEventListener('click', (e) => {
            // 버튼(다운로드/삭제) 클릭은 제외
            if (e?.target && e.target.closest && e.target.closest('button')) return;
            // 파일 input 자체 클릭도 제외(중복)
            if (e?.target === passportFileInput) return;
            try { passportFileInput.click(); } catch (_) {}
        });
    }

    // Date of birth → Age 자동 갱신 (요구사항 #162)
    const birthInput = document.getElementById('birth');
    const ageInput = document.getElementById('age');
    if (birthInput && ageInput) {
        const updateAge = () => {
            const raw = String(birthInput.value || '').trim();
            if (!raw) { ageInput.value = ''; return; }
            const age = calculateAge(raw);
            ageInput.value = (age >= 0 && age <= 150) ? String(age) : '';
        };
        birthInput.addEventListener('input', updateAge);
        birthInput.addEventListener('blur', updateAge);
    }
});

async function loadCustomerDetail() {
    try {
        showLoading();
        
        const params = new URLSearchParams({
            action: 'getCustomerDetail',
            accountId: currentAccountId,
            bookingsPage: bookingsPage,
            bookingsLimit: itemsPerPage,
            inquiriesPage: inquiriesPage,
            inquiriesLimit: itemsPerPage
        });
        
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, {
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            renderCustomerDetail(result.data);
        } else {
            // API 에러가 발생해도 빈 데이터로 렌더링 (데이터가 없을 수 있음)
            console.warn('API returned error, but rendering with empty data:', result.message);
            renderCustomerDetail({
                customer: {},
                bookings: [],
                bookingsPagination: { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 },
                inquiries: [],
                inquiriesPagination: { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 }
            });
        }
    } catch (error) {
        console.error('Error loading customer detail:', error);
        // 네트워크 오류 등 실제 오류인 경우에만 에러 표시
        // 데이터가 없는 경우는 빈 데이터로 렌더링
        renderCustomerDetail({
            customer: {},
            bookings: [],
            bookingsPagination: { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 },
            inquiries: [],
            inquiriesPagination: { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 }
        });
    } finally {
        hideLoading();
    }
}

function renderCustomerDetail(data) {
    const customer = data.customer || {};
    const bookings = data.bookings || [];
    const inquiries = data.inquiries || [];
    const bookingsPagination = data.bookingsPagination || { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 };
    const inquiriesPagination = data.inquiriesPagination || { total: 0, page: 1, limit: itemsPerPage, totalPages: 0 };
    
    // 전역 변수에 customer 데이터 저장
    currentCustomerData = customer;
    
    console.log('renderCustomerDetail - Full customer data:', customer);
    console.log('renderCustomerDetail - profileImage:', customer.profileImage);
    
    // 기본 정보 - 데이터가 없어도 빈 값으로 설정
    const customerNameInput = document.getElementById('cust_name');
    if (customerNameInput) {
        const fn = (customer.fName ?? '').toString().trim();
        const ln = (customer.lName ?? '').toString().trim();
        const full = (fn + (ln ? (' ' + ln) : '')).trim();
        customerNameInput.value = full;
    }
    
    // 이메일
    const emailInput = document.getElementById('cust_email');
    if (emailInput) {
        emailInput.value = customer.accountEmail || customer.emailAddress || '';
    }
    
    // 연락처
    const phoneInput = document.getElementById('cust_phone');
    if (phoneInput) {
        phoneInput.value = customer.contactNo || '';
    }
    
    // 국가 코드
    const countryCodeSelect = document.getElementById('country_code');
    if (countryCodeSelect) {
        const desired = (customer.countryCode || '+63').toString().trim() || '+63';
        countryCodeSelect.setAttribute('data-selected', desired);
        countryCodeSelect.value = desired;
        // 옵션이 하드코딩/부족한 경우를 대비해 다시 한 번 전체 로드 후 재선택
        populateCountryCodeSelect(countryCodeSelect, desired).catch(() => {});
    }
    
    // 고객 번호
    const custNoInput = document.getElementById('cust_no');
    if (custNoInput) {
        custNoInput.value = customer.clientId || '';
    }
    
    // 소속 지점명
    const branchInput = document.getElementById('cust_branch');
    if (branchInput) {
        branchInput.value = customer.branchName || '';
    }
    
    // 등록일시
    const createdAtInput = document.getElementById('created_at');
    if (createdAtInput) {
        if (customer.accountCreatedAt || customer.createdAt) {
            const date = new Date(customer.accountCreatedAt || customer.createdAt);
            createdAtInput.value = formatDateTime(date);
        } else {
            createdAtInput.value = '';
        }
    }
    
    // Note (에디터) - Quill 에디터에 내용 설정 (데이터가 없어도 빈 값으로 설정)
    const memo = customer.memo || customer.note || '';
    setTimeout(() => {
        const editorArea = document.querySelector('.jweditor');
        if (!editorArea) return;

        // multi-editor.js는 new Quill(editorArea, ...) 형태라 __quill은 .jweditor에 붙는다.
        // (일부 환경에서 Quill.find도 가능)
        const quill =
            editorArea.__quill ||
            (window.Quill && typeof window.Quill.find === 'function' ? window.Quill.find(editorArea) : null);

        if (quill && quill.root) {
            quill.root.innerHTML = memo;
        } else {
            // Quill이 아직 초기화 전이면 innerHTML을 심어두고, board()가 읽어가게 한다.
            editorArea.innerHTML = memo;
            if (typeof window.board === 'function') {
                window.board();
                setTimeout(() => {
                    const q = editorArea.__quill || (window.Quill && typeof window.Quill.find === 'function' ? window.Quill.find(editorArea) : null);
                    if (q && q.root) q.root.innerHTML = memo;
                }, 100);
            }
        }
    }, 300); // Quill 초기화 대기
    
    // 여행자 정보
    // IMPORTANT: Customer(기본 정보)와 Traveler(여행자 정보)는 완전 별도.
    // - Traveler 입력칸은 traveler* 컬럼만 표시한다. (customer fName/lName 등으로 보완/대체 금지)
    const pick = (obj, keys) => {
        for (const k of keys) {
            const v = obj?.[k];
            if (v !== undefined && v !== null && String(v).trim() !== '') return v;
        }
        return '';
    };
    const normDob = pick(customer, ['dateOfBirth', 'birthDate', 'birth', 'travelerBirth', 'travelerDOB', 'dob']);
    // IMPORTANT: Traveler Name/Last Name은 고객명(fName/lName)과 별도 저장( travelerFirstName/LastName )을 우선 사용
    const normLastName = pick(customer, ['travelerLastName', 'travelerLName']);
    const normFirstName = pick(customer, ['travelerFirstName', 'travelerFName']);
    const normPassportIssue = pick(customer, ['passportIssueDate', 'passportIssuedDate', 'passportIssue', 'passportIssueDt', 'passport_issue']);
    const normPassportExpire = pick(customer, ['passportExpiry', 'passportExpiryDate', 'passportExp', 'passportExpire', 'passportExpiredDate', 'passport_expire']);
    const normPassportNo = pick(customer, ['passportNumber', 'passportNo', 'passport_no']);
    const normNationality = pick(customer, ['nationality', 'countryOfOrigin']);
    const firstNameInput = document.getElementById('first_name');
    if (firstNameInput) {
        firstNameInput.value = normFirstName || '';
    }
    
    const lastNameInput = document.getElementById('last_name');
    if (lastNameInput) {
        lastNameInput.value = normLastName || '';
    }
    
    // 호칭 (title 필드가 있으면)
    const titleSelect = document.getElementById('title');
    if (titleSelect) {
        if (customer.title) {
            // 요구사항: Title 옵션은 MR/MS 만
            const titleValue = customer.title.toUpperCase();
            if (['MR', 'MS'].includes(titleValue)) {
                titleSelect.value = titleValue;
            } else if (titleValue === 'M') {
                titleSelect.value = 'MR';
            } else {
                titleSelect.value = 'MR'; // 기본값
            }
        } else {
            titleSelect.value = 'MR'; // 기본값
        }
        // select 업데이트를 위해 change 이벤트 발생
        titleSelect.dispatchEvent(new Event('change'));
    }
    
    // 성별
    const genderSelect = document.getElementById('gender');
    if (genderSelect) {
        if (customer.gender) {
            const genderValue = customer.gender.toLowerCase();
            // 요구사항: Gender 옵션은 Male/Female 만
            if (genderValue === 'male' || genderValue === '남성' || genderValue === 'm' || genderValue === 'male') {
                genderSelect.value = 'Male';
            } else if (genderValue === 'female' || genderValue === '여성' || genderValue === 'f' || genderValue === 'female') {
                genderSelect.value = 'Female';
            } else {
                genderSelect.value = 'Male';
            }
        } else {
            genderSelect.value = 'Male'; // 기본값
        }
        // select 업데이트를 위해 change 이벤트 발생
        genderSelect.dispatchEvent(new Event('change'));
    }
    
    // 나이 (dateOfBirth에서 계산)
    const ageInput = document.getElementById('age');
    if (ageInput) {
        const directAge = pick(customer, ['age', 'travelerAge']);
        if (directAge !== '' && !Number.isNaN(Number(directAge))) {
            ageInput.value = Number(directAge);
        } else if (normDob) {
            const age = calculateAge(normDob);
            if (age >= 0) {
                ageInput.value = age;
            } else {
                ageInput.value = '';
            }
        } else {
            ageInput.value = '';
        }
    }
    
    // 생년월일
    const birthInput = document.getElementById('birth');
    if (birthInput) {
        birthInput.value = normDob ? formatDateYYYYMMDD(normDob) : '';
    }
    
    // 출신국가
    const nationalityInput = document.getElementById('nationality');
    if (nationalityInput) {
        nationalityInput.value = normNationality || '';
    }
    
    // 여권번호
    const passportNoInput = document.getElementById('passport_no');
    if (passportNoInput) {
        passportNoInput.value = normPassportNo || '';
    }
    
    // 여권 발행일 (여러 가능한 필드명 확인)
    const passportIssueInput = document.getElementById('passport_issue');
    if (passportIssueInput) {
        passportIssueInput.value = normPassportIssue ? formatDateYYYYMMDD(normPassportIssue) : '';
    }
    
    // 여권 만료일 (여러 가능한 필드명 확인)
    const passportExpireInput = document.getElementById('passport_expire');
    if (passportExpireInput) {
        passportExpireInput.value = normPassportExpire ? formatDateYYYYMMDD(normPassportExpire) : '';
    }
    
    // 여권 사진 (데이터가 없어도 처리)
    const rawPassportImg = pick(customer, ['profileImage', 'passportPhoto', 'passportImage', 'passport_photo']);
    if (rawPassportImg) {
        // 상대 경로인 경우 전체 URL로 변환
        let imageUrl = rawPassportImg;
        console.log('Original profileImage:', imageUrl);
        
        if (imageUrl && !imageUrl.startsWith('http://') && !imageUrl.startsWith('https://') && !imageUrl.startsWith('data:')) {
            // backslash → slash
            imageUrl = imageUrl.replace(/\\/g, '/');
            // smart-travel2 제거 및 경로 정규화
            imageUrl = imageUrl.replace('/smart-travel2/', '/');
            imageUrl = imageUrl.replace('smart-travel2/', '');
            
            // uploads/uploads 중복 제거
            imageUrl = imageUrl.replace(/\/uploads\/uploads\//g, '/uploads/');

            // 레거시: 파일명만 있는 경우(passport_*.jpg 등) → uploads/passports 로 가정
            if (!imageUrl.includes('/') && imageUrl) {
                imageUrl = `uploads/passports/${imageUrl}`;
            }
            // 레거시: passports/xxx 형태 → uploads/passports/xxx
            if (imageUrl.startsWith('passports/')) {
                imageUrl = `uploads/${imageUrl}`;
            }
            
            // 상대 경로를 전체 URL로 변환
            if (imageUrl.startsWith('/')) {
                // /로 시작하는 경우 (예: /uploads/passports/...)
                imageUrl = window.location.origin + imageUrl;
            } else if (imageUrl.startsWith('../')) {
                // ../www/uploads/passports/... 형식 처리
                imageUrl = window.location.origin + '/' + imageUrl.replace('../www/', '');
            } else {
                // uploads/passports/... 형식 처리(또는 그 하위)
                imageUrl = window.location.origin + '/' + imageUrl.replace(/^\/+/, '');
            }
        }
        
        console.log('Converted imageUrl:', imageUrl);
        displayPassportImage(imageUrl);
    } else {
        console.log('No profileImage in customer data');
        // 여권 사진이 없으면 빈 상태로 표시
        const thumb = document.querySelector('.upload-box .thumb');
        const uploadMeta = document.querySelector('.upload-box .upload-meta');
        if (thumb) {
            thumb.style.backgroundImage = '';
            thumb.style.display = 'block'; // 기본 상태 유지 (회색 배경)
        }
        if (uploadMeta) {
            uploadMeta.style.display = 'none';
        }
    }
    
    // 동의 내용
    const agreementContent = document.getElementById('agreementContent');
    if (agreementContent) {
        agreementContent.value = customer.agreementContent || customer.agreement || '';
    }
    
    // 여행자 정보 렌더링
    // - 퍼블리싱 원본(customer-detail.html) 기준: 하단 "여행자 정보"는 입력 폼 형태라 별도 테이블 렌더링이 없음.
    // - 기존 테이블 렌더링 함수는 DOM이 없으면 no-op.
    renderTravelers(customer);
    
    // 예약 내역 렌더링
    renderBookings(bookings);
    renderBookingsPagination(bookingsPagination);
    
    // 문의 내역 렌더링
    renderInquiries(inquiries);
    renderInquiriesPagination(inquiriesPagination);
}

function renderTravelers(customer) {
    const tbody = document.getElementById('travelersTableBody');
    if (!tbody) return;
    
    // 고객 정보를 여행자 정보로 표시
    if (!customer.fName && !customer.lName) {
        tbody.innerHTML = '<tr><td colspan="10" class="is-center">여행자 정보가 없습니다.</td></tr>';
        return;
    }
    
    const gender = customer.gender || 'Male';
    const genderText = gender === 'Male' || gender === 'male' || gender === '남성' || gender === 'M' ? 'Male' : 
                      gender === 'Female' || gender === 'female' || gender === '여성' || gender === 'F' ? 'Female' : 'Other';
    
    const age = customer.dateOfBirth ? calculateAge(customer.dateOfBirth) : '';
    const passportExpiry = customer.passportExpiry ? formatDate(customer.passportExpiry) : '-';
    
    tbody.innerHTML = `
        <tr>
            <td class="no is-center">1</td>
            <td>${escapeHtml((customer.fName || '') + ' ' + (customer.lName || ''))}</td>
            <td class="is-center">${genderText}</td>
            <td class="is-center">${age || '-'}</td>
            <td>${escapeHtml(customer.nationality || '-')}</td>
            <td>${escapeHtml(customer.countryOfResidence || customer.nationality || '-')}</td>
            <td>${escapeHtml(customer.passportNumber || '-')}</td>
            <td class="is-center">${passportExpiry}</td>
            <td>${escapeHtml(customer.visaInformation || '-')}</td>
            <td>${escapeHtml(customer.remarks || '-')}</td>
        </tr>
    `;
}

function renderBookings(bookings) {
    const tbody = document.getElementById('bookings-tbody');
    if (!tbody) return;
    
    if (bookings.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="is-center">${getText('noBookings')}</td></tr>`;
        return;
    }
    
    let html = '';
    bookings.forEach((booking, index) => {
        const reservationDate = booking.bookingDate ? formatDate(booking.bookingDate) : '-';
        const departureDate = booking.departureDate ? formatDate(booking.departureDate) : '-';
        const status = getBookingStatusText(booking.bookingStatus);
        const numPeople = booking.numPeople || (Number(booking.adults || 0) + Number(booking.children || 0) + Number(booking.infants || 0)) || 0;
        const totalAmount = booking.totalAmount != null ? formatCurrency(Number(booking.totalAmount) || 0) : '';
        
        // (HTML 태그 수정) 디코딩 적용
        html += `
            <tr onclick="goToReservationDetail('${booking.bookingId}')">
                <td class="no is-center">${bookings.length - index}</td>
                <td class="ellipsis">${escapeHtml(decodeHtmlEntities(booking.packageName) || '-')}</td>
                <td class="is-center">${reservationDate}</td>
                <td class="is-center">${departureDate}</td>
                <td class="is-center">${status}</td>
                <td class="is-center">${numPeople}</td>
                <td class="is-center">${totalAmount}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function renderInquiries(inquiries) {
    const tbody = document.getElementById('inquiries-tbody');
    if (!tbody) return;
    
    if (inquiries.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="is-center">${getText('noInquiries')}</td></tr>`;
        return;
    }
    
    let html = '';
    inquiries.forEach((inquiry, index) => {
        const inquiryDate = inquiry.createdAt ? formatDate(inquiry.createdAt) : '-';
        const inquiryType = getInquiryTypeText(inquiry.inquiryType);
        const title = inquiry.inquiryTitle || inquiry.subject || '-';

        // 답변 여부 (replyStatus 우선)
        const replyStatusRaw = (inquiry.replyStatus || '').toString();
        const isResponded = replyStatusRaw.includes('답변완료') || replyStatusRaw.toLowerCase().includes('answered');
        const responseStatus = isResponded ? 'Response Complete' : 'Not Responded';

        // 처리 상태 (inquiry.status 기반)
        const processingStatus = getInquiryStatusText(inquiry.status);
        
        html += `
            <tr onclick="goToInquiryDetail(${inquiry.inquiryId})">
                <td class="no is-center">${inquiries.length - index}</td>
                <td class="is-center">${escapeHtml(inquiryType)}</td>
                <td class="ellipsis">${escapeHtml(title)}</td>
                <td class="is-center">${inquiryDate}</td>
                <td class="is-center">${escapeHtml(responseStatus)}</td>
                <td class="is-center">${escapeHtml(processingStatus)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function goToReservationDetail(bookingId) {
    window.location.href = `reservation-detail.html?id=${bookingId}`;
}

function goToInquiryDetail(inquiryId) {
    window.location.href = `inquiry-detail.html?id=${inquiryId}`;
}

function renderBookingsPagination(pagination) {
    const pagebox = document.querySelector('#bookings-tbody')?.closest('.card-panel')?.querySelector('.jw-pagebox');
    if (!pagebox) return;
    
    const pageContainer = pagebox.querySelector('.page');
    if (!pageContainer) return;
    
    const totalPages = pagination.totalPages || 0;
    const current = pagination.page || 1;
    
    if (totalPages <= 0) {
        pageContainer.innerHTML = '';
        const firstBtn = pagebox.querySelector('.first');
        const prevBtn = pagebox.querySelector('.prev');
        const nextBtn = pagebox.querySelector('.next');
        const lastBtn = pagebox.querySelector('.last');
        if (firstBtn) firstBtn.disabled = true;
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        if (lastBtn) lastBtn.disabled = true;
        return;
    }
    
    let pageNumbers = [];
    const maxPages = 5;
    let startPage = Math.max(1, current - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pageNumbers.push(i);
    }
    
    pageContainer.innerHTML = pageNumbers.map(page => `
        <button type="button" class="p ${page === current ? 'show' : ''}" 
                role="listitem" ${page === current ? 'aria-current="page"' : ''}
                onclick="goToBookingsPage(${page})">${page}</button>
    `).join('');
    
    // 첫 페이지 / 이전 페이지 버튼
    const firstBtn = pagebox.querySelector('.first');
    const prevBtn = pagebox.querySelector('.prev');
    if (firstBtn && prevBtn) {
        const disabled = current === 1;
        firstBtn.disabled = disabled;
        prevBtn.disabled = disabled;
        firstBtn.setAttribute('aria-disabled', disabled);
        prevBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            firstBtn.onclick = () => goToBookingsPage(1);
            prevBtn.onclick = () => goToBookingsPage(current - 1);
        } else {
            firstBtn.onclick = null;
            prevBtn.onclick = null;
        }
    }
    
    // 다음 페이지 / 마지막 페이지 버튼
    const nextBtn = pagebox.querySelector('.next');
    const lastBtn = pagebox.querySelector('.last');
    if (nextBtn && lastBtn) {
        const disabled = current === totalPages;
        nextBtn.disabled = disabled;
        lastBtn.disabled = disabled;
        nextBtn.setAttribute('aria-disabled', disabled);
        lastBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            nextBtn.onclick = () => goToBookingsPage(current + 1);
            lastBtn.onclick = () => goToBookingsPage(totalPages);
        } else {
            nextBtn.onclick = null;
            lastBtn.onclick = null;
        }
    }
}

function renderInquiriesPagination(pagination) {
    const pagebox = document.querySelector('#inquiries-tbody')?.closest('.card-panel')?.querySelector('.jw-pagebox');
    if (!pagebox) return;
    
    const pageContainer = pagebox.querySelector('.page');
    if (!pageContainer) return;
    
    const totalPages = pagination.totalPages || 0;
    const current = pagination.page || 1;
    
    if (totalPages <= 0) {
        pageContainer.innerHTML = '';
        const firstBtn = pagebox.querySelector('.first');
        const prevBtn = pagebox.querySelector('.prev');
        const nextBtn = pagebox.querySelector('.next');
        const lastBtn = pagebox.querySelector('.last');
        if (firstBtn) firstBtn.disabled = true;
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        if (lastBtn) lastBtn.disabled = true;
        return;
    }
    
    let pageNumbers = [];
    const maxPages = 5;
    let startPage = Math.max(1, current - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pageNumbers.push(i);
    }
    
    pageContainer.innerHTML = pageNumbers.map(page => `
        <button type="button" class="p ${page === current ? 'show' : ''}" 
                role="listitem" ${page === current ? 'aria-current="page"' : ''}
                onclick="goToInquiriesPage(${page})">${page}</button>
    `).join('');
    
    // 첫 페이지 / 이전 페이지 버튼
    const firstBtn = pagebox.querySelector('.first');
    const prevBtn = pagebox.querySelector('.prev');
    if (firstBtn && prevBtn) {
        const disabled = current === 1;
        firstBtn.disabled = disabled;
        prevBtn.disabled = disabled;
        firstBtn.setAttribute('aria-disabled', disabled);
        prevBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            firstBtn.onclick = () => goToInquiriesPage(1);
            prevBtn.onclick = () => goToInquiriesPage(current - 1);
        } else {
            firstBtn.onclick = null;
            prevBtn.onclick = null;
        }
    }
    
    // 다음 페이지 / 마지막 페이지 버튼
    const nextBtn = pagebox.querySelector('.next');
    const lastBtn = pagebox.querySelector('.last');
    if (nextBtn && lastBtn) {
        const disabled = current === totalPages;
        nextBtn.disabled = disabled;
        lastBtn.disabled = disabled;
        nextBtn.setAttribute('aria-disabled', disabled);
        lastBtn.setAttribute('aria-disabled', disabled);
        if (!disabled) {
            nextBtn.onclick = () => goToInquiriesPage(current + 1);
            lastBtn.onclick = () => goToInquiriesPage(totalPages);
        } else {
            nextBtn.onclick = null;
            lastBtn.onclick = null;
        }
    }
}

function goToBookingsPage(page) {
    bookingsPage = page;
    loadCustomerDetail();
    // 예약 내역 섹션으로 스크롤
    const bookingsSection = document.querySelector('#bookings-tbody')?.closest('.card-panel');
    if (bookingsSection) {
        bookingsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function goToInquiriesPage(page) {
    inquiriesPage = page;
    loadCustomerDetail();
    // 문의 내역 섹션으로 스크롤
    const inquiriesSection = document.querySelector('#inquiries-tbody')?.closest('.card-panel');
    if (inquiriesSection) {
        inquiriesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function handleSave() {
    try {
        // 고객명 (기본 정보) - Traveler Name/Last Name과 별개로 저장
        const customerName = document.getElementById('cust_name')?.value.trim() || '';
        // 여행자 정보(여권/여행자): Traveler Name/Last Name (Customer Name과 별도)
        const travelerFirstName = document.getElementById('first_name')?.value.trim() || '';
        const travelerLastName = document.getElementById('last_name')?.value.trim() || '';
        const email = document.getElementById('cust_email')?.value.trim() || '';
        const phone = document.getElementById('cust_phone')?.value.trim() || '';
        const countryCode = document.getElementById('country_code')?.value || '+63';
        const password = document.getElementById('cust_pw')?.value ?? '';
        
        // 필수 필드 검증
        const errors = [];
        if (!customerName) {
            errors.push('고객명을 입력해주세요.');
        }
        if (!email) {
            errors.push('이메일을 입력해주세요.');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push('올바른 이메일 형식을 입력해주세요.');
        }
        if (!phone) {
            errors.push('연락처를 입력해주세요.');
        }
        
        if (errors.length > 0) {
            alert(errors.join('\n'));
            return;
        }
        
        // Note 에디터에서 내용 가져오기
        const editorArea = document.querySelector('.jweditor');
        let memo = '';
        if (editorArea) {
            const quill =
                editorArea.__quill ||
                (window.Quill && typeof window.Quill.find === 'function' ? window.Quill.find(editorArea) : null);
            if (quill && quill.root) memo = (quill.root.innerHTML || '').trim();
            else {
                // Quill이 아직 초기화되지 않은 경우
                const qlEditor = editorArea.querySelector('.ql-editor');
                memo = (qlEditor ? qlEditor.innerHTML : editorArea.innerHTML).trim();
            }
        }
        
        // 여행자 정보
        const title = document.getElementById('title')?.value || '';
        const gender = document.getElementById('gender')?.value || '';
        const age = document.getElementById('age')?.value || '';
        const birth = document.getElementById('birth')?.value.trim() || '';
        const nationality = document.getElementById('nationality')?.value.trim() || '';
        const passportNo = document.getElementById('passport_no')?.value.trim() || '';
        const passportIssue = document.getElementById('passport_issue')?.value.trim() || '';
        const passportExpire = document.getElementById('passport_expire')?.value.trim() || '';
        
        // 고객명에서 first/last 분리 (client.fName/lName에 저장)
        let customerFirstName = '';
        let customerLastName = '';
        if (customerName) {
            const parts = customerName.trim().split(/\s+/);
            customerFirstName = parts[0] || '';
            customerLastName = parts.slice(1).join(' ') || '';
        }
        
        const formData = new FormData();
        formData.append('action', 'updateCustomer');
        formData.append('accountId', currentAccountId);
        // 고객명(기본 정보)
        formData.append('firstName', customerFirstName);
        formData.append('lastName', customerLastName);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('countryCode', countryCode);
        formData.append('memo', memo);
        formData.append('title', title);
        // 동의 내용
        const agreementContent = document.getElementById('agreementContent')?.value.trim() || '';
        formData.append('agreementContent', agreementContent);
        
        // 여행자 정보 (고객명과 별도 저장)
        // IMPORTANT: Customer 저장 시 Traveler를 "자동 보완/동기화"하지 않는다.
        // - 사용자가 실제로 변경한 경우에만 전송하여 기존 Traveler 데이터를 덮어쓰지 않도록 한다.
        const prevTravelerFirstName = (currentCustomerData?.travelerFirstName ?? currentCustomerData?.travelerFName ?? '').toString();
        const prevTravelerLastName = (currentCustomerData?.travelerLastName ?? currentCustomerData?.travelerLName ?? '').toString();
        const travelerNameChanged =
            travelerFirstName !== prevTravelerFirstName || travelerLastName !== prevTravelerLastName;
        if (travelerNameChanged) {
            formData.append('travelerFirstName', travelerFirstName);
            formData.append('travelerLastName', travelerLastName);
        }
        formData.append('travelerGender', gender);
        formData.append('travelerAge', age);
        formData.append('travelerBirth', birth);
        formData.append('travelerNationality', nationality);
        formData.append('travelerPassportNo', passportNo);
        formData.append('travelerPassportIssue', passportIssue);
        formData.append('travelerPassportExpire', passportExpire);
        
        // 거주국가, 비자 정보, 비고 (전역 변수에서 가져오기)
        formData.append('travelerCountryOfResidence', currentCustomerData?.countryOfResidence || '');
        formData.append('travelerVisaInformation', currentCustomerData?.visaInformation || '');
        formData.append('travelerRemarks', currentCustomerData?.remarks || '');
        
        // 여권 사진 파일
        const passportFile = document.getElementById('file-passport')?.files[0];
        if (passportFile) {
            formData.append('passportPhoto', passportFile);
        }

        // 비밀번호 수동 저장 (값이 입력된 경우만)
        if (typeof password === 'string' && password.trim() !== '') {
            formData.append('password', password);
        }
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(getText('saved'));
            loadCustomerDetail(); // 재로드
        } else {
            alert(getText('saveFailed') + ': ' + result.message);
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert(getText('error'));
    }
}

function generatePassword(length = 12) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
    let out = '';
    const buf = new Uint32Array(length);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
        window.crypto.getRandomValues(buf);
        for (let i = 0; i < length; i++) out += chars[buf[i] % chars.length];
        return out;
    }
    // fallback (낮은 보안성, 구형 브라우저 대비)
    for (let i = 0; i < length; i++) out += chars[Math.floor(Math.random() * chars.length)];
    return out;
}

async function copyTextToClipboard(text) {
    try {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch (_) {}
    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok;
    } catch (_) {
        return false;
    }
}

async function handlePasswordReset() {
    if (!confirm(getText('confirmPasswordReset'))) {
        return;
    }
    
    try {
        const newPw = generatePassword(12);
        const pwInput = document.getElementById('cust_pw');
        if (pwInput) pwInput.value = newPw;

        // 저장 (updateCustomer로 통일)
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'updateCustomer',
                accountId: currentAccountId,
                password: newPw
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await copyTextToClipboard(newPw);
            alert(getText('passwordResetSuccess'));
        } else {
            alert(getText('passwordResetFailed') + ': ' + result.message);
        }
    } catch (error) {
        console.error('Error resetting password:', error);
        alert(getText('error'));
    }
}

function handleDownloadPassport() {
    // TODO: 여권 사진 다운로드 구현
    const imageSrc = document.querySelector('.upload-box .thumb')?.style.backgroundImage;
    if (imageSrc) {
        const url = imageSrc.replace('url("', '').replace('")', '');
        window.open(url, '_blank');
    }
}

function handleDeletePassport() {
    if (!confirm('Do you want to delete the passport photo?')) {
        return;
    }
    
    // TODO: 서버에서 여권 사진 삭제 API 호출
    const thumb = document.querySelector('.upload-box .thumb');
    const uploadMeta = document.querySelector('.upload-box .upload-meta');
    if (thumb) {
        thumb.style.backgroundImage = '';
        thumb.style.display = 'none';
    }
    if (uploadMeta) {
        uploadMeta.style.display = 'none';
    }
    
    document.getElementById('file-passport').value = '';
}

function handlePassportFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('Only image files can be uploaded.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        displayPassportImage(e.target.result);
    };
    reader.readAsDataURL(file);
}

function displayPassportImage(imageSrc) {
    const thumb = document.querySelector('.upload-box .thumb');
    const uploadMeta = document.querySelector('.upload-box .upload-meta');
    
    console.log('displayPassportImage called with:', imageSrc);
    
    if (!thumb || !uploadMeta) {
        console.error('upload-box elements not found');
        return;
    }
    
    if (imageSrc && imageSrc !== 'null' && imageSrc !== 'undefined' && imageSrc.trim() !== '') {
        // 이미지 로드 테스트
        const img = new Image();
        img.onload = function() {
            console.log('Image loaded successfully:', imageSrc);
            if (thumb) {
                thumb.style.backgroundImage = `url("${imageSrc}")`;
                thumb.style.display = 'block';
                thumb.style.backgroundSize = 'cover';
                thumb.style.backgroundPosition = 'center';
                thumb.style.width = '110px';
                thumb.style.height = '110px';
                thumb.style.minWidth = '110px';
                thumb.style.minHeight = '110px';
                thumb.style.borderRadius = '10px';
                thumb.style.overflow = 'hidden';
            }
            
            if (uploadMeta) {
                uploadMeta.style.display = 'flex';
                // 파일 정보 업데이트 (있는 경우)
                const fileInfo = uploadMeta.querySelector('.file-info');
                if (fileInfo) {
                    // URL에서 파일명 추출
                    try {
                        const url = new URL(imageSrc);
                        const fileName = url.pathname.split('/').pop() || '이미지';
                        const extension = fileName.split('.').pop()?.toLowerCase() || 'jpg';
                        // 파일 크기 추정 (실제로는 서버에서 가져와야 함)
                        fileInfo.textContent = `${extension}, 이미지`;
                    } catch (e) {
                        // URL 파싱 실패 시 기본값 (상대 경로인 경우)
                        const fileName = imageSrc.split('/').pop() || '이미지';
                        const extension = fileName.split('.').pop()?.toLowerCase() || 'jpg';
                        fileInfo.textContent = `${extension}, 이미지`;
                    }
                }
            }
        };
        img.onerror = function() {
            console.error('Failed to load image:', imageSrc);
            // 이미지 로드 실패 시에도 기본 스타일 유지
            if (thumb) {
                thumb.style.backgroundImage = '';
                thumb.style.display = 'block';
            }
            if (uploadMeta) {
                uploadMeta.style.display = 'none';
            }
        };
        img.src = imageSrc;
    } else {
        console.log('No image source provided or invalid:', imageSrc);
        if (thumb) {
            thumb.style.backgroundImage = '';
            thumb.style.display = 'block';
        }
        if (uploadMeta) {
            uploadMeta.style.display = 'none';
        }
    }
}

// 유틸리티 함수들
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
}

function formatDateYYYYMMDD(dateString) {
    if (!dateString) return '';
    
    // 이미 YYYYMMDD 형식인 경우
    if (typeof dateString === 'string' && /^\d{8}$/.test(dateString)) {
        return dateString;
    }
    
    // YYYY-MM-DD 형식인 경우
    if (typeof dateString === 'string' && /^\d{4}-\d{2}-\d{2}/.test(dateString)) {
        return dateString.replace(/-/g, '').substring(0, 8);
    }
    
    // Date 객체나 다른 형식인 경우
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            console.warn('Invalid date:', dateString);
            return '';
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}${month}${day}`;
    } catch (e) {
        console.error('Error formatting date:', dateString, e);
        return '';
    }
}

function formatDateTime(date) {
    if (!date) return '';
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US').format(amount);
}

function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return 0;
    // Support YYYYMMDD input (e.g. 19991231) as well as YYYY-MM-DD / Date-parseable strings
    const s = String(dateOfBirth).trim();
    let birth = null;
    if (/^\d{8}$/.test(s)) {
        const y = s.slice(0, 4);
        const m = s.slice(4, 6);
        const d = s.slice(6, 8);
        birth = new Date(`${y}-${m}-${d}T00:00:00`);
    } else if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
        birth = new Date(`${s.slice(0, 10)}T00:00:00`);
    } else {
        birth = new Date(s);
    }
    if (!birth || Number.isNaN(birth.getTime())) return -1;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

function getBookingStatusText(status) {
    const lang = getCurrentLang();
    const statusMap = {
        'confirmed': lang === 'tl' ? 'Nakumpirma' : 'Confirmed',
        'pending': lang === 'tl' ? 'Naghihintay' : 'Pending',
        'cancelled': lang === 'tl' ? 'Nakansela' : 'Cancelled',
        'canceled': lang === 'tl' ? 'Nakansela' : 'Cancelled',
        'completed': lang === 'tl' ? 'Natapos' : 'Completed'
    };
    return statusMap[status?.toLowerCase()] || status || '-';
}

function getInquiryTypeText(type) {
    const lang = getCurrentLang();
    const typeMap = {
        'product': lang === 'tl' ? 'Tanong sa Produkto' : 'Product Inquiry',
        'booking': lang === 'tl' ? 'Tanong sa Pag-book' : 'Booking Inquiry',
        'payment': lang === 'tl' ? 'Tanong sa Pagbabayad' : 'Payment Inquiry',
        'general': lang === 'tl' ? 'Pangkalahatang Tanong' : 'General Inquiry',
        'complaint': lang === 'tl' ? 'Reklamo' : 'Complaint'
    };
    return typeMap[type?.toLowerCase()] || type || '-';
}

function getInquiryStatusText(status) {
    const lang = getCurrentLang();
    const statusMap = {
        'pending': lang === 'tl' ? 'Natanggap' : 'Received',
        'processing': lang === 'tl' ? 'Pinoproseso' : 'In Progress',
        'in_progress': lang === 'tl' ? 'Pinoproseso' : 'In Progress',
        'resolved': lang === 'tl' ? 'Nalutas' : 'Resolved',
        'closed': lang === 'tl' ? 'Isinara' : 'Closed',
        'completed': lang === 'tl' ? 'Natapos' : 'Completed'
    };
    return statusMap[status?.toLowerCase()] || status || '-';
}

function showLoading() {
    // 로딩 상태 표시 (필요시 구현)
}

function hideLoading() {
    // 로딩 종료 (필요시 구현)
}

function showError(message) {
    alert(message);
}

function handleEdit() {
    // 수정 모드로 전환 (필드 활성화)
    const inputs = document.querySelectorAll('#cust_name, #cust_email, #cust_phone, #country_code, #title, #first_name, #last_name, #gender, #age, #birth, #nationality, #passport_no, #passport_issue, #passport_expire');
    inputs.forEach(input => {
        if (input) input.disabled = false;
    });
    
    // 저장 버튼 활성화
    const saveButton = document.getElementById('saveBtn') || document.querySelector('.page-toolbar-actions .jw-button.typeB');
    if (saveButton) {
        saveButton.style.display = 'block';
    }
    
    // 수정 버튼 숨기기
    const editBtn = document.getElementById('editBtn');
    if (editBtn) {
        editBtn.style.display = 'none';
    }
}

async function handleDelete() {
    if (!confirm('고객을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
        return;
    }
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'deleteCustomer',
                accountId: currentAccountId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('고객이 삭제되었습니다.');
            window.location.href = 'customer-list.html';
        } else {
            alert('삭제에 실패했습니다: ' + (result.message || '알 수 없는 오류'));
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        alert('삭제 중 오류가 발생했습니다.');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// (HTML 태그 수정) HTML 엔티티 디코딩 함수
function decodeHtmlEntities(str) {
    if (!str) return str;
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}
