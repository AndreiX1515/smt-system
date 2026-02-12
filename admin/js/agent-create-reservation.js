/**
 * Agent Admin - Create Reservation Page JavaScript
 */

let selectedPackage = null;
let selectedCustomer = null;
let travelers = [];
let selectedRooms = [];
let selectedOptions = {};
let depositProofFile = null;
let currentTravelerIndex = 0;
let previousPackageId = null; // 이전 상품 ID 저장 (상품 변경 감지용)
let selectedDateInfo = null; // 선택된 날짜의 상세 정보
let availableDates = []; // 가용 가능한 날짜 목록
let calendarCurrentMonth = new Date().getMonth() + 1; // 현재 캘린더 월 (1-12)
let calendarCurrentYear = new Date().getFullYear(); // 현재 캘린더 연도
let selectedDateInCalendar = null; // 캘린더에서 선택한 날짜 (YYYY-MM-DD 형식)
let availableDatesByMonth = {}; // 월별 가용 가능한 날짜 (캐싱용)

// 날짜 선택 뷰 관련 전역 변수
let currentDateView = 'calendar';  // 'calendar' 또는 'table'
let currentDateSort = 'date';      // 'date' 또는 'price'
let allAvailableDates = [];        // 테이블용 전체 날짜 목록

// 항공 옵션 관련 전역 변수
let currentAirlineName = ''; // 현재 선택된 항공사명
let airlineOptionCategories = []; // 항공사 옵션 카테고리 및 옵션 목록

// ===== 인원 옵션(상품별 pricingOptions 기반) =====
// 요구사항:
// - "Number of people option"은 상품에 등록된 인원별 요금 정보(package_pricing_options)와 1:1로 일치해야 함
// 구현:
// - traveler.type 값을 option_name(원문) 그대로 사용
// - price lookup은 option_name(소문자) 기준으로 매핑
let __pricingByTravelerType = {};          // key(lower option name) -> price(number)
let __labelByTravelerType = {};            // key(lower option name) -> label(original option name)
let __allowedTravelerTypes = [];           // array of lower option name (in display order)

function __normalizeTravelerTypeFromOptionName(name) {
    const raw = String(name || '').trim();
    if (!raw) return null;
    return raw.toLowerCase();
}

function __applyPackagePricingOptions(pkg) {
    __pricingByTravelerType = {};
    __labelByTravelerType = {};
    __allowedTravelerTypes = [];

    const pOpts = Array.isArray(pkg?.pricingOptions) ? pkg.pricingOptions : null;
    if (pOpts && pOpts.length) {
        for (const opt of pOpts) {
            const name = opt?.optionName ?? opt?.option_name ?? opt?.name ?? '';
            const type = __normalizeTravelerTypeFromOptionName(name);
            if (!type) continue; // key(lower)
            const price = Number(opt?.price);
            if (Number.isFinite(price)) __pricingByTravelerType[type] = price;
            const label = String(name || '').trim();
            if (label) __labelByTravelerType[type] = label;
            __allowedTravelerTypes.push(type);
        }
    }

    // fallback: pricingOptions가 없으면 기존 adult/child/infant 키를 최소 제공
    // Agent 예약은 B2B 가격 우선 사용
    if (!__allowedTravelerTypes.length) {
        const adultKey = 'adult';
        __allowedTravelerTypes = ['adult', 'child', 'infant'];
        __labelByTravelerType['adult'] = getText('adult');
        __labelByTravelerType['child'] = getText('child');
        __labelByTravelerType['infant'] = getText('infant');
        // B2B 가격 우선, 없으면 일반 가격 사용
        const adultFallback = Number(pkg?.b2bPrice ?? pkg?.b2b_price ?? pkg?.packagePrice);
        if (Number.isFinite(adultFallback)) __pricingByTravelerType['adult'] = adultFallback;
        const childFallback = Number(pkg?.b2bChildPrice ?? pkg?.b2b_child_price ?? pkg?.childPrice);
        if (Number.isFinite(childFallback) && childFallback > 0) __pricingByTravelerType['child'] = childFallback;
        const infantFallback = Number(pkg?.b2bInfantPrice ?? pkg?.b2b_infant_price ?? pkg?.infantPrice);
        if (Number.isFinite(infantFallback) && infantFallback > 0) __pricingByTravelerType['infant'] = infantFallback;
        const infantSeatFallback = Number(pkg?.b2bInfantSeatPrice ?? pkg?.b2b_infant_seat_price ?? pkg?.infantSeatPrice ?? pkg?.infant_seat_price);
        if (Number.isFinite(infantSeatFallback) && infantSeatFallback > 0) __pricingByTravelerType['infant_seat'] = infantSeatFallback;
    }
}

// 날짜별 가격 적용 (package_availability의 childPrice, infantPrice, singlePrice 사용)
// Agent 예약은 B2B 가격 우선 (API가 agent 세션 감지 시 price 필드에 b2b_price를 반환)
function __applyDateSpecificPricing(dateInfo) {
    if (!dateInfo) return;

    // adult 가격: b2bPrice 우선, 없으면 price 사용 (API가 agent일 때 이미 b2b price를 price에 설정)
    const adultPrice = Number(dateInfo.b2bPrice ?? dateInfo.price);
    if (Number.isFinite(adultPrice) && adultPrice > 0) {
        __pricingByTravelerType['adult'] = adultPrice;
    }

    // child 가격: b2bChildPrice 우선
    const childPrice = Number(dateInfo.b2bChildPrice ?? dateInfo.childPrice);
    if (Number.isFinite(childPrice) && childPrice > 0) {
        __pricingByTravelerType['child'] = childPrice;
    }

    // infant 가격: b2bInfantPrice 우선
    const infantPrice = Number(dateInfo.b2bInfantPrice ?? dateInfo.infantPrice);
    if (Number.isFinite(infantPrice) && infantPrice > 0) {
        __pricingByTravelerType['infant'] = infantPrice;
    }

    // infant seat 가격: b2bInfantSeatPrice 우선
    const infantSeatPrice = Number(dateInfo.b2bInfantSeatPrice ?? dateInfo.infantSeatPrice);
    if (Number.isFinite(infantSeatPrice) && infantSeatPrice > 0) {
        __pricingByTravelerType['infant_seat'] = infantSeatPrice;
    }

    // single 가격 (singlePrice는 싱글룸 추가요금으로 사용)
    if (dateInfo.singlePrice !== null && dateInfo.singlePrice !== undefined) {
        const singlePrice = Number(dateInfo.singlePrice);
        if (Number.isFinite(singlePrice)) __pricingByTravelerType['single'] = singlePrice;
    }

    console.log('[Pricing] Date-specific pricing applied:', __pricingByTravelerType);
}

function __getUnitPrice(type) {
    const key = String(type || '').toLowerCase();
    const v = __pricingByTravelerType[key];
    return Number.isFinite(v) ? v : 0;
}

// 여행자별 가격 계산 (Infant/Child 특별 로직 적용)
// - Infant: DB 가격 있으면 사용, 없으면 기본 10,000페소
// - Child (Room Yes): 항상 성인 가격
// - Child (Room No): DB 가격 있으면 DB 가격, 없으면 성인가격×70%
function __getTravelerPrice(traveler) {
    if (!traveler) return 0;
    const type = __classifyTypeKey(traveler.type);
    const adultPrice = __getUnitPrice('adult');

    if (type === 'infant') {
        if (traveler.infantSeat) {
            const seatPrice = __pricingByTravelerType['infant_seat'];
            if (Number.isFinite(seatPrice) && seatPrice > 0) return seatPrice;
        }
        const dbPrice = __pricingByTravelerType['infant'];
        return (Number.isFinite(dbPrice) && dbPrice > 0) ? dbPrice : 10000;
    }

    if (type === 'child') {
        // Child Room = Yes → 항상 성인 가격
        if (traveler.childRoom === true) {
            return adultPrice;
        }
        // Child Room = No → DB 가격 있으면 DB 가격, 없으면 성인가격×80%
        const dbPrice = __pricingByTravelerType['child'];
        return (Number.isFinite(dbPrice) && dbPrice > 0) ? dbPrice : Math.round(adultPrice * 0.8);
    }

    // Adult (및 기타 타입)
    return __getUnitPrice(traveler.type);
}

function __renderTravelerTypeOptionsHtml(selectedValue) {
    const allowed = Array.isArray(__allowedTravelerTypes) && __allowedTravelerTypes.length
        ? __allowedTravelerTypes
        : ['adult'];
    return allowed.map((t) => {
        const label = __labelByTravelerType?.[t] ? __labelByTravelerType[t] : t;
        const selected = (String(selectedValue || '').toLowerCase() === String(t)) ? 'selected' : '';
        return `<option value="${t}" ${selected}>${escapeHtml(label)}</option>`;
    }).join('');
}

function __syncTravelerTypeSelectsWithPackage() {
    const allowed = Array.isArray(__allowedTravelerTypes) && __allowedTravelerTypes.length
        ? __allowedTravelerTypes
        : ['adult'];

    travelers.forEach((tr) => {
        if (!tr) return;
        const cur = String(tr.type || '').toLowerCase();
        if (!allowed.includes(cur)) tr.type = allowed[0];
        else tr.type = cur;
    });

    document.querySelectorAll('.traveler-type').forEach((sel) => {
        const current = sel.value;
        sel.innerHTML = __renderTravelerTypeOptionsHtml(current);
        const v = String(sel.value || '').toLowerCase();
        if (!allowed.includes(v)) sel.value = allowed[0];
        else sel.value = v;
    });
}

function __classifyTypeKey(key) {
    const s = String(key || '').toLowerCase();
    if (s.includes('infant') || s.includes('baby') || s.includes('유아')) return 'infant';
    if (s.includes('child') || s.includes('kid') || s.includes('아동')) return 'child';
    return 'adult';
}

// Promise를 저장하기 위한 변수
let __resetRoomConfirmResolve = null;

function __showResetRoomConfirmModal() {
    return new Promise((resolve) => {
        __resetRoomConfirmResolve = resolve;
        openModal('people-change-confirm-modal');

        const cancelBtn = document.getElementById('people-change-cancel-btn');
        const continueBtn = document.getElementById('people-change-continue-btn');

        const cleanup = () => {
            cancelBtn?.removeEventListener('click', onCancel);
            continueBtn?.removeEventListener('click', onContinue);
        };

        const onCancel = () => {
            cleanup();
            closeModal('people-change-confirm-modal');
            resolve(false);
        };

        const onContinue = () => {
            cleanup();
            closeModal('people-change-confirm-modal');
            resolve(true);
        };

        cancelBtn?.addEventListener('click', onCancel);
        continueBtn?.addEventListener('click', onContinue);
    });
}

async function __confirmResetRoomOptionsIfSelected() {
    if (!Array.isArray(selectedRooms) || selectedRooms.length === 0) return true;
    const confirmed = await __showResetRoomConfirmModal();
    if (!confirmed) return false;
    selectedRooms = [];
    updateRoomOptionDisplay();
    return true;
}

function __showMustKeepOneTravelerPopup() {
    const modalId = 'min-one-traveler-modal';
    try {
        let modal = document.getElementById(modalId);
        if (!modal) {
            modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title"> </h3>
                        <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('${modalId}')">
                            <img src="../image/button-close2.svg" alt="">
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="jw-mgb8">${escapeHtml(getText('mustKeepOneTraveler'))}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="jw-button typeB" onclick="closeModal('${modalId}')">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        openModal(modalId);
    } catch (e) {
        alert(getText('mustKeepOneTraveler'));
    }
}

function __rerenderTravelersTable() {
    const tbody = document.getElementById('travelers-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    travelers.forEach((t, i) => {
        if (!t) return;
        t.index = i;
        // lead traveler 보정: 존재하지 않으면 첫 번째를 대표로
        if (i === 0 && travelers.every(x => !x?.isMainTraveler)) t.isMainTraveler = true;
        renderTravelerRow(t);
    });
    // 총 금액 재계산
    calculateTotalAmount();
}

// Passport photo URL normalize helper:
// - DB/환경별로 profileImage/passportImage가 "\uploads\..." / "uploads/..." / "/uploads/..." / "passports/..." 형태로 섞여 들어올 수 있음
// - 미리보기(새 창)와 파일명 표시가 안정적으로 동작하도록 절대 URL로 정규화
function normalizePassportImageUrl(raw) {
    const s0 = String(raw || '').trim();
    if (!s0) return '';
    // allow already absolute/data urls
    if (/^(https?:\/\/|data:)/i.test(s0)) return s0;

    let p = s0.replace(/\\/g, '/');
    // legacy path cleanup
    p = p.replace('/smart-travel2/', '/').replace('smart-travel2/', '');
    p = p.replace(/\/uploads\/uploads\//g, '/uploads/');

    // filename only -> uploads/passports/<file>
    if (p && !p.includes('/')) {
        p = `uploads/passports/${p}`;
    }
    // passports/<file> -> uploads/passports/<file>
    if (p.startsWith('passports/')) {
        p = `uploads/${p}`;
    }
    // ../www/uploads/... -> /uploads/...
    if (p.startsWith('../')) {
        p = '/' + p.replace(/^\.\.\/+/, '').replace(/^www\//, '');
    }
    if (!p.startsWith('/')) p = '/' + p.replace(/^\/+/, '');

    try {
        return (window.location.origin || '') + p;
    } catch (_) {
        return p;
    }
}

// 에이전트 예약금 비율(0~1)
let agentDepositRate = 0.1; // 기본값(백엔드에서 못 가져오면 사용)

// 선금(Advance payment) 자동/수동 입력 구분
let isDepositManuallyEdited = false;
let isProgrammaticDepositUpdate = false;

// 계산 검증용 디버그 패널 (?calcDebug=1일 때만 표시)
const __calcDebugEnabled = (function () {
    try {
        return new URLSearchParams(window.location.search).get('calcDebug') === '1';
    } catch (e) {
        return false;
    }
})();
let __calcDebugEl = null;

// 모달 상태
let selectedProductInModal = null;
let selectedCustomerInModal = null;
let selectedRoomsInModal = [];
let searchedProductsCache = []; // 검색된 상품 목록 캐시

// 예약 이력 관리
let reservationHistory = [];

function addReservationHistory(description) {
    const now = new Date();
    const historyItem = {
        description: description,
        createdAt: formatHistoryDateTime(now),
        timestamp: now.toISOString()
    };
    reservationHistory.push(historyItem);
    renderReservationHistory();
}

function renderReservationHistory() {
    const historyList = document.getElementById('historyList');
    if (!historyList) return;
    
    if (reservationHistory.length === 0) {
        historyList.innerHTML = '<div class="history-item"><div class="history-time">-</div><div class="history-description">예약 생성 중...</div></div>';
        return;
    }
    
    historyList.innerHTML = reservationHistory.map(item => `
        <div class="history-item">
            <div class="history-time">${item.createdAt}</div>
            <div class="history-description">${escapeHtmlForHistory(item.description)}</div>
        </div>
    `).join('');
}

function formatHistoryDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

function escapeHtmlForHistory(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 다국어 텍스트
const i18nTexts = {
    kor: {
        adult: '성인',
        child: '아동',
        infant: '유아',
        visaNo: '비자 불필요',
        visaGroup: '단체 비자 +₱1500',
        visaIndividual: '개인 비자 +₱1900',
        male: '남성',
        female: '여성',
        other: '기타',
        firstName: '이름',
        lastName: '성',
        age: '숫자 입력',
        contact: '연락처',
        email: '이메일',
        nationality: '국적',
        passportNumber: '여권번호',
        remarks: '비고',
        searching: '검색 중...',
        noResults: '검색 결과가 없습니다.',
        errorOccurred: '검색 중 오류가 발생했습니다.',
        loading: '로딩 중...',
        noRoomOptions: '사용 가능한 룸 옵션이 없습니다.',
        cannotLoadRoomOptions: '룸 옵션을 불러올 수 없습니다.',
        errorLoadingRoomOptions: '룸 옵션을 불러오는 중 오류가 발생했습니다.',
        selectRoomOption: '룸 옵션 선택',
        selectRoomOptionCount: '룸 옵션 선택 ({count}개)',
        people: '명',
        capacity: '인원',
        price: '가격',
        pleaseSelectProduct: '상품을 선택해주세요.',
        pleaseSelectCustomer: '고객을 선택해주세요.',
        pleaseEnterProductName: '상품명을 입력해주세요.',
        requiredFields: '필수값을 입력해주세요.',
        pleaseSelectDate: '날짜를 선택해주세요.',
        selectTravelStartDate: '여행 시작일을 선택해주세요.',
        enterCustomerInfo: '예약 고객 정보를 모두 입력해주세요.',
        enterTravelerInfo: '최소 1명의 여행자 정보를 입력해주세요.',
        enterTravelerName: '{index}번째 여행자의 이름을 입력해주세요.',
        enterDepositInfo: '선금과 선금 입금 기한을 입력해주세요.',
        reservationCreated: '예약이 생성되었습니다.',
        reservationFailed: '예약 생성에 실패했습니다: {message}',
        reservationError: '예약 생성 중 오류가 발생했습니다.',
        failedToLoadProduct: '상품 정보를 불러오는데 실패했습니다.',
        errorLoadingProduct: '상품 정보를 불러오는 중 오류가 발생했습니다.',
        failedToLoadCustomer: '고객 정보를 불러오는데 실패했습니다.',
        errorLoadingCustomer: '고객 정보를 불러오는 중 오류가 발생했습니다.',
        resetRoomOptions: '룸 옵션 선택 후 인원 변경 시, 룸 옵션이 초기화됩니다. 계속하시겠습니까?',
        deleteTraveler: '테이블을 삭제하시겠습니까?',
        depositFileTooLarge: '파일 크기가 10MB를 초과했습니다.',
        depositFileSelected: '선택된 파일',
        fileUploadError: '파일 업로드 중 오류가 발생했습니다.',
        mustKeepOneTraveler: '최소 하나의 데이터는 남겨야 합니다.'
    },
    eng: {
        adult: 'Adult',
        child: 'Child',
        infant: 'Infant',
        visaNo: 'With Visa',
        visaGroup: 'Group Visa +₱1500',
        visaIndividual: 'Individual Visa +₱1900',
        visaForeign: 'Foreign Passport',
        male: 'Male',
        female: 'Female',
        other: 'Other',
        firstName: 'First Name',
        lastName: 'Last Name',
        age: 'Enter number',
        contact: 'Contact',
        email: 'Email',
        nationality: 'Nationality',
        passportNumber: 'Passport Number',
        remarks: 'Remarks',
        searching: 'Searching...',
        noResults: 'No search results',
        errorOccurred: 'An error occurred while searching',
        loading: 'Loading...',
        noRoomOptions: 'No room options available',
        cannotLoadRoomOptions: 'Cannot load room options',
        errorLoadingRoomOptions: 'An error occurred while loading room options',
        selectRoomOption: 'Select Room Option',
        selectRoomOptionCount: 'Select Room Option ({count})',
        people: ' people',
        capacity: 'Capacity',
        price: 'Price',
        pleaseSelectProduct: 'Please select a product.',
        pleaseSelectCustomer: 'Please select a customer.',
        pleaseEnterProductName: 'Please enter product name.',
        requiredFields: 'Please enter required fields.',
        pleaseSelectDate: 'Please select a date.',
        selectTravelStartDate: 'Please select travel start date.',
        enterCustomerInfo: 'Please enter all customer information.',
        enterTravelerInfo: 'Please enter at least 1 traveler information.',
        enterTravelerName: 'Please enter the name of traveler {index}.',
        enterDepositInfo: 'Please enter deposit amount and due date.',
        reservationCreated: 'Reservation created successfully.',
        reservationFailed: 'Failed to create reservation: {message}',
        reservationError: 'An error occurred while creating reservation.',
        failedToLoadProduct: 'Failed to load product information.',
        errorLoadingProduct: 'An error occurred while loading product information.',
        failedToLoadCustomer: 'Failed to load customer information.',
        errorLoadingCustomer: 'An error occurred while loading customer information.',
        resetRoomOptions: 'Changing the number of people after selecting room options will reset the room options. Do you want to continue?',
        deleteTraveler: 'Do you want to delete the selected item?',
        depositFileTooLarge: 'File size must be less than 10MB.',
        depositFileSelected: 'Selected file',
        fileUploadError: 'An error occurred while processing the file.',
        mustKeepOneTraveler: 'At least one input value must be left.'
    }
};

// 현재 언어 가져오기
function getCurrentLang() {
    const langCookie = document.cookie.split('; ').find(row => row.startsWith('lang='));
    const lang = langCookie ? decodeURIComponent(langCookie.split('=')[1] || '') : 'eng';
    // Admin 요구사항: eng/tl만 사용 (tl은 번역 데이터가 없으므로 eng로 fallback)
    if (lang === 'eng' || lang === 'tl') return 'eng';
    return 'kor';
}

// 다국어 텍스트 가져오기
function getText(key, params = {}) {
    const lang = getCurrentLang();
    const langKey = lang === 'eng' ? 'eng' : 'kor';
    let text = i18nTexts[langKey][key] || i18nTexts['kor'][key] || key;
    
    // 파라미터 치환
    if (params) {
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
    }
    
    return text;
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
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }

    // 국가코드 옵션 전체 로드(예약 고객 연락처)
    try {
        await populateCountryCodeSelect(document.getElementById('country_code'), '+63');
    } catch (e) {
        // ignore
    }

    // 에이전트 예약금 비율 로드 (0~1)
    try {
        const r = await fetch('../backend/api/agent-api.php?action=getAgentDepositRate', { credentials: 'same-origin' });
        const j = await r.json();
        if (j && j.success && typeof j.data?.depositRate === 'number') {
            const v = j.data.depositRate;
            if (v >= 0 && v <= 1) agentDepositRate = v;
        }
    } catch (e) {
        // ignore (fallback to default)
    }
    
    // HTML lang 속성 즉시 설정 (초기 로딩 시) - 가장 먼저 실행
    const htmlLang = document.getElementById('html-lang');
    if (htmlLang) {
        const currentLang = getCurrentLang();
        const langValue = currentLang === 'eng' ? 'en' : 'ko';
        const currentHtmlLang = htmlLang.getAttribute('lang');
        if (currentHtmlLang !== langValue) {
            htmlLang.setAttribute('lang', langValue);
        }
        // 날짜 입력 필드에도 직접 lang 속성 설정
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.setAttribute('lang', langValue);
        });
    }
    
    initializeCreateReservation();
    
    // 언어 변경 이벤트 리스너 (다른 스크립트에서 언어 변경 시 호출)
    // 약간의 지연을 두어 다른 스크립트의 초기화가 완료된 후 실행되도록 함
    setTimeout(() => {
        window.addEventListener('languageChanged', function(e) {
            // 이벤트가 이미 처리 중인지 확인
            if (isUpdatingLanguage) return;
            updateDynamicContentLanguage();
        });
    }, 100);
    
    // 초기 다국어 적용 (select 옵션 등)
    setTimeout(() => {
        if (typeof language_apply === 'function') {
            const currentLang = getCurrentLang();
            language_apply(currentLang);
        }
    }, 200);

    // URL 파라미터로 전달된 packageId와 date 처리 (Best Price Packages에서 Book 클릭 시)
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedPackageId = urlParams.get('packageId');
    const preselectedDate = urlParams.get('date');
    const existingBookingId = urlParams.get('bookingId');
    const editMode = urlParams.get('mode');

    // 기존 예약 편집 모드 (결제 페이지에서 Back 클릭 시)
    if (existingBookingId) {
        setTimeout(async () => {
            await loadExistingReservation(existingBookingId);
        }, 500);
    } else if (preselectedPackageId) {
        // 약간의 지연 후 상품 로드 (초기화 완료 후 실행)
        setTimeout(async () => {
            try {
                // 상품 상세 정보 로드
                await loadProductDetail(preselectedPackageId);

                // 날짜가 지정된 경우 해당 날짜 선택
                if (preselectedDate && selectedPackage) {
                    // 날짜 파싱
                    const dateObj = new Date(preselectedDate);
                    if (!isNaN(dateObj.getTime())) {
                        // 날짜 표시 형식 설정
                        const displayDate = dateObj.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit'
                        });

                        // 날짜 값 설정
                        selectedDateInCalendar = preselectedDate;
                        document.getElementById('departure_date').value = displayDate;
                        document.getElementById('departure_date_value').value = preselectedDate;

                        // 선택한 날짜의 가용성 정보 찾기
                        const dateYear = dateObj.getFullYear();
                        const dateMonth = dateObj.getMonth() + 1;
                        await loadAvailableDates(preselectedPackageId, dateYear, dateMonth);

                        // availableDates에서 해당 날짜 정보 찾기
                        const cacheKey = `${dateYear}-${dateMonth}`;
                        const monthDates = availableDatesByMonth[cacheKey] || [];
                        const dateInfo = monthDates.find(d => d.date === preselectedDate || d.availableDate === preselectedDate);
                        if (dateInfo) {
                            selectedDateInfo = dateInfo;
                            // 날짜별 가격 적용 (할인 가격 포함)
                            __applyDateSpecificPricing(selectedDateInfo);
                        }

                        // 날짜 상세 정보 로드 (여행 기간, 미팅 시간/장소, 항공편 정보)
                        await loadDateDetailInfo(preselectedPackageId, preselectedDate);

                        // 총액 계산
                        if (typeof calculateTotalAmount === 'function') {
                            calculateTotalAmount();
                        }
                        if (typeof updateOrderSummary === 'function') {
                            updateOrderSummary();
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading preselected package:', error);
            }
        }, 500);
    }
});

// 기존 예약 불러오기 (결제 페이지에서 Back 클릭 시)
let currentEditingBookingId = null;

async function loadExistingReservation(bookingId) {
    try {
        currentEditingBookingId = bookingId;

        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'getReservationDetail',
                bookingId: bookingId
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load reservation');
        }

        const booking = result.data.booking || {};
        const travelersData = result.data.travelers || [];
        const selectedOptionsData = result.data.selectedOptions || {};

        // 1. 상품 정보 로드
        if (booking.packageId) {
            await loadProductDetail(booking.packageId);
        }

        // 2. 날짜 설정
        if (booking.departureDate) {
            const dateObj = new Date(booking.departureDate);
            if (!isNaN(dateObj.getTime())) {
                selectedDateInCalendar = booking.departureDate;
                const displayDate = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });
                const departureDateEl = document.getElementById('departure_date');
                const departureDateValueEl = document.getElementById('departure_date_value');
                if (departureDateEl) departureDateEl.value = displayDate;
                if (departureDateValueEl) departureDateValueEl.value = booking.departureDate;

                // 날짜 상세 정보 로드 및 가격 적용
                const dateYear = dateObj.getFullYear();
                const dateMonth = dateObj.getMonth() + 1;
                await loadAvailableDates(booking.packageId, dateYear, dateMonth);
                const cacheKey = `${dateYear}-${dateMonth}`;
                const monthDates = availableDatesByMonth[cacheKey] || [];
                const dateInfo = monthDates.find(d => d.date === booking.departureDate || d.availableDate === booking.departureDate);
                if (dateInfo) {
                    selectedDateInfo = dateInfo;
                    __applyDateSpecificPricing(selectedDateInfo);
                }
                await loadDateDetailInfo(booking.packageId, booking.departureDate);
            }
        }

        // 3. 고객 정보 설정
        const customerInfo = selectedOptionsData.customerInfo || {};
        const customerName = (customerInfo.firstName || '') + ' ' + (customerInfo.lastName || '');
        const userNameEl = document.getElementById('user_name');
        const userEmailEl = document.getElementById('user_email');
        const userPhoneEl = document.getElementById('user_phone');
        const countryCodeEl = document.getElementById('country_code');
        const customerAccountIdEl = document.getElementById('customer_account_id');

        if (userNameEl) userNameEl.value = customerName.trim() || booking.customerName || '';
        if (userEmailEl) userEmailEl.value = customerInfo.email || booking.contactEmail || '';
        if (userPhoneEl) userPhoneEl.value = customerInfo.phone || booking.contactPhone || '';
        if (countryCodeEl && customerInfo.countryCode) countryCodeEl.value = customerInfo.countryCode;
        if (customerAccountIdEl && booking.customerAccountId) customerAccountIdEl.value = booking.customerAccountId;

        // 5. 여행자 정보 설정
        if (travelersData.length > 0) {
            travelerModalData = travelersData.map((t, idx) => ({
                isPrimary: t.isMainTraveler === 1 || idx === 0,
                type: t.travelerType || 'adult',
                title: t.title || 'MR',
                firstName: t.firstName || '',
                lastName: t.lastName || '',
                gender: t.gender || 'male',
                birthDate: t.dateOfBirth || '',
                nationality: t.nationality || '',
                passportNumber: t.passportNumber || '',
                passportIssueDate: t.passportIssueDate || '',
                passportExpiry: t.passportExpiryDate || '',
                passportImage: t.passportImage || '',
                visaRequired: t.visaRequired || false,
                visaType: t.visaType || 'with_visa',
                childRoom: t.childRoom || false,
                infantSeat: t.infantSeat || false,
                profile_source: t.profile_source || t.profileSource || '',
                remarks: t.specialRequests || '',
                flightOptions: t.flightOptions || [],
                flightOptionPrices: t.flightOptionPrices || {}
            }));
            travelers = travelerModalData;
        }

        // 6. 객실 옵션 설정
        if (selectedOptionsData.selectedRooms && selectedOptionsData.selectedRooms.length > 0) {
            selectedRooms = selectedOptionsData.selectedRooms;
            updateRoomOptionDisplay();
        }

        // 7. 기타 요청사항 설정
        if (selectedOptionsData.otherRequest) {
            const otherRequestEditor = document.getElementById('other_request');
            if (otherRequestEditor) {
                otherRequestEditor.value = selectedOptionsData.otherRequest;
            }
        }

        // 8. 총액 계산 및 UI 업데이트
        calculateTotalAmount();
        updateOrderSummary();

        console.log('Existing reservation loaded:', bookingId);

    } catch (error) {
        console.error('Error loading existing reservation:', error);
        alert('Failed to load reservation: ' + error.message);
    }
}

/**
 * 예약 수정 데이터 로드 (reservation-detail에서 Edit 클릭 시)
 * sessionStorage에 저장된 데이터를 불러와서 폼에 채움
 */
async function loadEditReservationData() {
    try {
        const savedData = sessionStorage.getItem('editReservationData');
        if (!savedData) {
            console.warn('No edit reservation data found in sessionStorage');
            return;
        }

        const data = JSON.parse(savedData);
        const booking = data.booking || {};
        const travelersData = data.travelers || [];
        const selectedOptionsData = data.selectedOptions || {};

        // sessionStorage 데이터 사용 후 삭제
        sessionStorage.removeItem('editReservationData');

        console.log('Loading edit reservation data:', data);

        // 1. 상품 정보 로드
        if (booking.packageId) {
            await loadProductDetail(booking.packageId);
        }

        // 2. 날짜 설정
        const departureDate = booking.departureDate || booking.departure_date;
        if (departureDate) {
            const dateObj = new Date(departureDate);
            if (!isNaN(dateObj.getTime())) {
                selectedDateInCalendar = departureDate;
                const displayDate = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });
                const departureDateEl = document.getElementById('departure_date');
                const departureDateValueEl = document.getElementById('departure_date_value');
                if (departureDateEl) departureDateEl.value = displayDate;
                if (departureDateValueEl) departureDateValueEl.value = departureDate;

                // 날짜 상세 정보 로드 및 가격 적용
                if (booking.packageId) {
                    const dateYear = dateObj.getFullYear();
                    const dateMonth = dateObj.getMonth() + 1;
                    await loadAvailableDates(booking.packageId, dateYear, dateMonth);
                    const cacheKey = `${dateYear}-${dateMonth}`;
                    const monthDates = availableDatesByMonth[cacheKey] || [];
                    const dateInfo = monthDates.find(d => d.date === departureDate || d.availableDate === departureDate);
                    if (dateInfo) {
                        selectedDateInfo = dateInfo;
                        __applyDateSpecificPricing(selectedDateInfo);
                    }
                    await loadDateDetailInfo(booking.packageId, departureDate);
                }
            }
        }

        // 3. 고객 정보 설정
        const customerInfo = selectedOptionsData.customerInfo || {};
        const customerName = customerInfo.firstName && customerInfo.lastName
            ? `${customerInfo.firstName} ${customerInfo.lastName}`.trim()
            : (booking.customerName || booking.customer_name || '');
        const userNameEl = document.getElementById('user_name');
        const userEmailEl = document.getElementById('user_email');
        const userPhoneEl = document.getElementById('user_phone');
        const countryCodeEl = document.getElementById('country_code');
        const customerAccountIdEl = document.getElementById('customer_account_id');

        if (userNameEl) userNameEl.value = customerName;
        if (userEmailEl) userEmailEl.value = customerInfo.email || booking.contactEmail || booking.contact_email || '';
        if (userPhoneEl) userPhoneEl.value = customerInfo.phone || booking.contactPhone || booking.contact_phone || '';
        if (countryCodeEl && customerInfo.countryCode) countryCodeEl.value = customerInfo.countryCode;
        if (customerAccountIdEl && (booking.customerAccountId || booking.customer_account_id)) {
            customerAccountIdEl.value = booking.customerAccountId || booking.customer_account_id;
        }

        // 4. 여행자 정보 설정
        if (travelersData.length > 0) {
            travelerModalData = travelersData.map((t, idx) => ({
                isPrimary: t.isMainTraveler === 1 || t.is_main_traveler === 1 || idx === 0,
                type: t.travelerType || t.traveler_type || 'adult',
                title: t.title || 'MR',
                firstName: t.firstName || t.first_name || '',
                lastName: t.lastName || t.last_name || '',
                gender: t.gender || 'male',
                birthDate: t.dateOfBirth || t.date_of_birth || t.birthDate || '',
                nationality: t.nationality || '',
                passportNo: t.passportNumber || t.passport_number || '',
                passportIssueDate: t.passportIssueDate || t.passport_issue_date || '',
                passportExpiry: t.passportExpiryDate || t.passport_expiry_date || t.passportExpiry || '',
                passportPhotoUrl: t.passportImage || t.passport_image || '',
                visaRequired: t.visaRequired || t.visa_required || false,
                visaType: t.visaType || t.visa_type || 'with_visa',
                visaDocumentUrl: t.visaDocument || t.visa_document || '',
                childRoom: t.childRoom || t.child_room || false,
                infantSeat: t.infantSeat || t.infant_seat || false,
                profile_source: t.profile_source || t.profileSource || '',
                remarks: t.specialRequests || t.special_requests || '',
                flightOptions: t.flightOptions || t.flight_options || [],
                flightOptionPrices: t.flightOptionPrices || t.flight_option_prices || {}
            }));
            travelers = travelerModalData.map(t => convertToTravelersFormat(t));
        }

        // 5. 객실 옵션 설정
        const roomOptionsData = selectedOptionsData.selectedRooms || data.roomOptions || [];
        if (roomOptionsData.length > 0) {
            selectedRooms = roomOptionsData.map(r => ({
                roomId: r.roomId || r.room_id,
                roomType: r.roomType || r.room_type,
                roomPrice: r.roomPrice || r.room_price || 0,
                capacity: r.capacity || 1,
                count: r.count || r.quantity || 1
            }));
            selectedRoomsInModal = [...selectedRooms];
            updateRoomOptionDisplay();
        }

        // 6. 기타 요청사항 설정
        const otherRequest = selectedOptionsData.otherRequest || booking.otherRequest || booking.other_request || '';
        if (otherRequest) {
            // Quill 에디터가 있으면 에디터에 설정
            const etcReqEditorEl = document.getElementById('etc_req_editor');
            if (etcReqEditorEl && etcReqEditorEl.__quill) {
                etcReqEditorEl.__quill.root.innerHTML = otherRequest;
            }
        }

        // 7. 메모 설정
        const agentNote = booking.agentNote || booking.agent_note || booking.memo || '';
        if (agentNote) {
            const memoEditorEl = document.getElementById('memo_editor');
            if (memoEditorEl && memoEditorEl.__quill) {
                memoEditorEl.__quill.root.innerHTML = agentNote;
            }
        }

        // 8. UI 업데이트
        updateTravelerSummary();
        calculateTotalAmount();
        updateOrderSummary();

        console.log('Edit reservation data loaded successfully');

    } catch (error) {
        console.error('Error loading edit reservation data:', error);
        alert('Failed to load reservation data: ' + error.message);
    }
}

// 동적 콘텐츠 언어 업데이트
let isUpdatingLanguage = false; // 무한 루프 방지 플래그

function updateDynamicContentLanguage() {
    // 무한 루프 방지
    if (isUpdatingLanguage) return;
    isUpdatingLanguage = true;
    
    try {
        const lang = getCurrentLang();
        
        // HTML lang 속성 업데이트 (날짜 입력 필드의 언어 설정)
        const htmlLang = document.getElementById('html-lang');
        if (htmlLang) {
            const newLang = lang === 'eng' ? 'en' : 'ko';
            const currentLang = htmlLang.getAttribute('lang');
            if (currentLang !== newLang) {
                htmlLang.setAttribute('lang', newLang);
                // 날짜 입력 필드에도 직접 lang 속성 설정
                document.querySelectorAll('input[type="date"]').forEach(input => {
                    input.setAttribute('lang', newLang);
                    // 값을 임시로 저장했다가 복원 (브라우저가 lang 변경을 인식하도록)
                    const value = input.value;
                    if (value) {
                        input.value = '';
                        setTimeout(() => {
                            input.value = value;
                        }, 10);
                    }
                });
            }
        }
        
        // 기존 여행자 행들의 select 옵션 업데이트
        document.querySelectorAll('.traveler-type option').forEach(option => {
            if (option.dataset.lanEng) {
                const key = option.value === 'adult' ? 'adult' : option.value === 'child' ? 'child' : 'infant';
                option.textContent = getText(key);
            }
        });
        
        document.querySelectorAll('.traveler-visa option').forEach(option => {
            const keyMap = { 'with_visa': 'visaNo', 'group': 'visaGroup', 'individual': 'visaIndividual', 'foreign': 'visaForeign' };
            const key = keyMap[option.value] || 'visaNo';
            option.textContent = getText(key);
        });
        
        document.querySelectorAll('.traveler-gender option').forEach(option => {
            if (option.dataset.lanEng) {
                const key = option.value === 'male' ? 'male' : option.value === 'female' ? 'female' : 'other';
                option.textContent = getText(key);
            }
        });
        
        // placeholder 업데이트
        document.querySelectorAll('.traveler-firstname').forEach(input => {
            if (input.dataset.lanEngPlaceholder) {
                input.placeholder = getText('firstName');
            }
        });
        
        document.querySelectorAll('.traveler-lastname').forEach(input => {
            if (input.dataset.lanEngPlaceholder) {
                input.placeholder = getText('lastName');
            }
        });
        
        document.querySelectorAll('.traveler-age').forEach(input => {
            if (input.dataset.lanEngPlaceholder) {
                input.placeholder = getText('age');
            }
        });
        
        document.querySelectorAll('.traveler-nationality').forEach(input => {
            if (input.dataset.lanEngPlaceholder) {
                input.placeholder = getText('nationality');
            }
        });
        
        document.querySelectorAll('.traveler-passport').forEach(input => {
            if (input.dataset.lanEngPlaceholder) {
                input.placeholder = getText('passportNumber');
            }
        });
        
        // 룸 옵션 버튼 텍스트 업데이트
        updateRoomOptionDisplay();
    } finally {
        isUpdatingLanguage = false;
    }
}

function initializeCreateReservation() {
    // HTML lang 속성 초기 설정 (날짜 입력 필드의 언어 설정)
    const htmlLang = document.getElementById('html-lang');
    if (htmlLang) {
        const currentLang = getCurrentLang();
        const langValue = currentLang === 'eng' ? 'en' : 'ko';
        htmlLang.setAttribute('lang', langValue);
        // 날짜 입력 필드에도 직접 lang 속성 설정
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.setAttribute('lang', langValue);
        });
    }
    
    // 상품 검색 버튼
    const productSearchBtn = document.getElementById('product_search_btn');
    if (productSearchBtn) {
        productSearchBtn.addEventListener('click', openProductSearchModal);
    }
    
    // 고객 검색 버튼
    const customerSearchBtn = document.getElementById('customer_search_btn');
    if (customerSearchBtn) {
        customerSearchBtn.addEventListener('click', openCustomerSearchModal);
    }
    
    // 고객 추가 버튼
    const addTravelerBtn = document.getElementById('add_traveler_btn');
    if (addTravelerBtn) {
        addTravelerBtn.addEventListener('click', addTraveler);
    }
    
    // 룸 옵션 선택 버튼
    const roomOptionBtn = document.getElementById('room_option_btn');
    if (roomOptionBtn) {
        roomOptionBtn.addEventListener('click', openRoomOptionModal);
    }
    
    // 저장 버튼
    const saveButton = document.getElementById('saveBtn');
    if (saveButton) {
        saveButton.addEventListener('click', handleSave);
    }
    
    // 테스트 입력 버튼
    const testFillBtn = document.getElementById('test-fill-btn');
    if (testFillBtn) {
        testFillBtn.addEventListener('click', fillTestData);
    }
    
    initializeDepositProofUpload();
    initializeFullPaymentFileUpload();

    // 선금 입금기한: 오늘~7일 이내만 선택 가능 (UI 제약)
    try {
        const depositDueInput = document.getElementById('deposit_due');
        if (depositDueInput) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const max = new Date(today);
            max.setDate(max.getDate() + 7);
            depositDueInput.min = today.toISOString().split('T')[0];
            depositDueInput.max = max.toISOString().split('T')[0];
        }
    } catch (e) {}
    
    // 선금 입력 필드에 이벤트 리스너 추가 (선금 변경 시 잔금 자동 재계산 + 수동 변경 감지)
    const depositInput = document.getElementById('pay_deposit');
    if (depositInput) {
        depositInput.addEventListener('input', () => {
            if (!isProgrammaticDepositUpdate) isDepositManuallyEdited = true;
            updateBalance();
            __renderCalcDebug();
        });
        depositInput.addEventListener('change', () => {
            if (!isProgrammaticDepositUpdate) isDepositManuallyEdited = true;
            updateBalance();
            __renderCalcDebug();
        });
    }
    
    // 상품 검색 모달 내 검색 버튼
    const productSearchSubmit = document.getElementById('product-search-submit');
    if (productSearchSubmit) {
        productSearchSubmit.addEventListener('click', searchProducts);
    }
    
    // 상품 검색 모달 내 입력 필드 엔터키 처리
    const productSearchInput = document.getElementById('product-search-input');
    if (productSearchInput) {
        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
    
    // 고객 검색 모달 내 검색 버튼
    const customerSearchSubmit = document.getElementById('customer-search-submit');
    if (customerSearchSubmit) {
        customerSearchSubmit.addEventListener('click', searchCustomers);
    }
    
    // 고객 검색 모달 내 입력 필드 엔터키 처리
    const customerSearchInput = document.getElementById('customer-search-input');
    if (customerSearchInput) {
        customerSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchCustomers();
            }
        });
    }
    
    // 여행 고객 검색 모달 내 검색 버튼
    const travelCustomerSearchSubmit = document.getElementById('travel-customer-search-submit');
    if (travelCustomerSearchSubmit) {
        travelCustomerSearchSubmit.addEventListener('click', () => {
            searchTravelCustomers(1);
        });
    }
    
    // 여행 고객 검색 모달 내 입력 필드 엔터키 처리
    const travelCustomerSearchInput = document.getElementById('travel-customer-search-input');
    if (travelCustomerSearchInput) {
        travelCustomerSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchTravelCustomers(1);
            }
        });
    }
    
    // 여행 시작일 달력 버튼
    const departureDateBtn = document.getElementById('departure_date_btn');
    if (departureDateBtn) {
        departureDateBtn.addEventListener('click', openDatePickerModal);
    }
    
    // 날짜 선택 확인 버튼
    const confirmDateSelectionBtn = document.getElementById('confirm-date-selection');
    if (confirmDateSelectionBtn) {
        confirmDateSelectionBtn.addEventListener('click', confirmDateSelection);
    }
    
    // 캘린더 월 네비게이션
    const calendarPrevBtn = document.getElementById('calendar-prev-month');
    const calendarNextBtn = document.getElementById('calendar-next-month');
    if (calendarPrevBtn) {
        calendarPrevBtn.addEventListener('click', () => {
            calendarCurrentMonth--;
            if (calendarCurrentMonth < 1) {
                calendarCurrentMonth = 12;
                calendarCurrentYear--;
            }
            renderCalendar();
        });
    }
    if (calendarNextBtn) {
        calendarNextBtn.addEventListener('click', () => {
            calendarCurrentMonth++;
            if (calendarCurrentMonth > 12) {
                calendarCurrentMonth = 1;
                calendarCurrentYear++;
            }
            renderCalendar();
        });
    }
    
    // 초기 여행자 1명 추가
    addTraveler();

    // calc debug
    __renderCalcDebug();
}

// 여행 종료일 계산
function getPackageDurationDays(pkg) {
    if (!pkg) return 0;

    // 우선순위:
    // 1) durationDays (신규/표준 컬럼)
    // 2) packageDuration/duration (환경별)
    // 3) duration_days (구 컬럼)
    // 4) schedules 최대 day_number (실제 등록 일정 기반 보정)
    let d = Number.parseInt(pkg.durationDays ?? pkg.packageDuration ?? pkg.duration ?? NaN, 10);
    if (!Number.isFinite(d) || d <= 0) {
        d = Number.parseInt(pkg.duration_days ?? NaN, 10);
    }

    const schedules = Array.isArray(pkg.schedules) ? pkg.schedules : [];
    let maxDay = 0;
    for (const s of schedules) {
        const n = Number.parseInt(s?.day_number ?? s?.dayNumber ?? s?.day ?? NaN, 10);
        if (Number.isFinite(n) && n > maxDay) maxDay = n;
    }
    if (maxDay > 0) d = maxDay;

    return (Number.isFinite(d) && d > 0) ? d : 0;
}

function updateReturnDate() {
    const departureDateValueInput = document.getElementById('departure_date_value');
    if (!departureDateValueInput || !departureDateValueInput.value || !selectedPackage) return;

    const tripRangeWrap = document.getElementById('trip_range_wrap');
    const tripRangeInput = document.getElementById('trip_range');
    const meetTimeWrap = document.getElementById('meet_time_wrap');
    const meetTimeInput = document.getElementById('meet_time');
    const meetPlaceWrap = document.getElementById('meet_place_wrap');
    const meetPlaceInput = document.getElementById('meet_place');

    const departureDate = new Date(departureDateValueInput.value);
    const durationDays = getPackageDurationDays(selectedPackage);
    if (!isNaN(departureDate.getTime()) && durationDays > 0 && tripRangeInput) {
        const returnDate = new Date(departureDate);
        returnDate.setDate(returnDate.getDate() + durationDays - 1);
        const endStr = `${returnDate.getFullYear()}-${String(returnDate.getMonth() + 1).padStart(2, '0')}-${String(returnDate.getDate()).padStart(2, '0')}`;
        tripRangeInput.value = `${departureDateValueInput.value} - ${endStr}`;
        tripRangeWrap?.classList.remove('hidden');
    }

    if (meetPlaceInput) {
        // NOTE: meeting_location이 빈 문자열('')로 내려오는 환경이 있어 || 로 fallback 처리
        const place = String(
            selectedPackage.meeting_location ||
            selectedPackage.meetingLocation ||
            selectedPackage.meetingPoint ||
            selectedPackage.meeting_point ||
            selectedPackage.meeting_address ||
            selectedPackage.meetingAddress ||
            ''
        ).trim();
        meetPlaceInput.value = place;
        if (place) meetPlaceWrap?.classList.remove('hidden');
        else meetPlaceWrap?.classList.add('hidden');
    }

    if (meetTimeInput) {
        // meeting_time도 빈 문자열일 수 있어 || fallback 처리
        const t = String(selectedPackage.meeting_time || selectedPackage.meetingTime || '').trim();
        if (t) {
            const hhmm = t.length >= 5 ? t.substring(0, 5) : t;
            meetTimeInput.value = `${departureDateValueInput.value} ${hhmm}`;
            meetTimeWrap?.classList.remove('hidden');
        } else {
            meetTimeWrap?.classList.add('hidden');
        }
    }
}

// 모달 열기/닫기
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // 룸 옵션 모달: 외부 영역 클릭 시 닫기
        if (modalId === 'room-option-modal') {
            modal.addEventListener('click', handleRoomModalOutsideClick);
        }
    }
}

// 룸 옵션 모달 외부 클릭 핸들러
function handleRoomModalOutsideClick(e) {
    const modal = document.getElementById('room-option-modal');
    const modalContent = modal.querySelector('.modal-content');
    if (e.target === modal && !modalContent.contains(e.target)) {
        closeModal('room-option-modal');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';

        // 룸 옵션 모달: 이벤트 리스너 제거
        if (modalId === 'room-option-modal') {
            modal.removeEventListener('click', handleRoomModalOutsideClick);
        }
    }
}

// 전역 함수로 등록 (HTML에서 onclick으로 호출)
window.closeModal = closeModal;
window.confirmProductSelection = confirmProductSelection;
window.confirmCustomerSelection = confirmCustomerSelection;
window.confirmRoomSelection = confirmRoomSelection;
window.openProductSearchModal = openProductSearchModal;
window.searchProducts = searchProducts;
window.openProductFlyer = openProductFlyer;
window.openProductDetail = openProductDetail;
window.openProductItinerary = openProductItinerary;
window.openTravelerModal = openTravelerModal;
window.closeTravelerModal = closeTravelerModal;
window.addTravelerCard = addTravelerCard;
window.deleteTravelerCard = deleteTravelerCard;
window.setPrimaryTraveler = setPrimaryTraveler;
window.saveTravelersFromModal = saveTravelersFromModal;

// 여행자 모달 데이터
let travelerModalData = [];
let travelerCardIdCounter = 0;

// 삭제 대기 파일 목록 (Save 시 서버에서 삭제)
let pendingDeleteFiles = [];

// travelers → 모달 형식 변환
function convertToModalFormat(t) {
    return {
        id: t.id || 0,
        type: t.type || 'adult',
        visaRequired: t.visaRequired || false,
        visaType: t.visaType || 'with_visa',
        title: t.title || 'Mr',
        gender: t.gender || 'male',
        firstName: t.firstName || '',
        lastName: t.lastName || '',
        age: t.age || null,
        birthDate: t.birthDate || '',
        nationality: t.nationality || '',
        passportNo: t.passportNumber || t.passportNo || '',
        passportIssueDate: t.passportIssueDate || '',
        passportExpiry: t.passportExpiry || '',
        passportPhotoFile: t.passportPhotoFile || null,
        passportPhotoUrl: t.passportImage || t.passportPhotoUrl || '',
        isPrimary: t.isMainTraveler || t.isPrimary || false,
        childRoom: t.childRoom || false,
        infantSeat: t.infantSeat || false,
        profile_source: t.profile_source || t.profileSource || '',
        flightOptions: t.flightOptions || [],
        flightOptionPrices: t.flightOptionPrices || {}
    };
}

// 모달 → travelers 형식 변환
function convertToTravelersFormat(t) {
    return {
        id: t.id || 0,
        type: t.type || 'adult',
        visaRequired: t.visaRequired || false,
        visaType: t.visaType || '',
        title: t.title || 'Mr',
        gender: t.gender || 'male',
        firstName: t.firstName || '',
        lastName: t.lastName || '',
        age: t.age || null,
        birthDate: t.birthDate || '',
        nationality: t.nationality || '',
        passportNumber: t.passportNo || t.passportNumber || '',
        passportIssueDate: t.passportIssueDate || '',
        passportExpiry: t.passportExpiry || '',
        passportPhotoFile: t.passportPhotoFile || null,
        passportImage: t.passportPhotoUrl || t.passportImage || '',
        visaDocumentFile: t.visaDocumentFile || null,
        visaDocument: t.visaDocumentUrl || t.visaDocument || '',
        isMainTraveler: t.isPrimary || t.isMainTraveler || false,
        childRoom: t.childRoom || false,
        infantSeat: t.infantSeat || false,
        profile_source: t.profile_source || t.profileSource || '',
        flightOptions: t.flightOptions || [],
        flightOptionPrices: t.flightOptionPrices || {}
    };
}

// 여행자 모달 열기
async function openTravelerModal() {
    // 삭제 대기 목록 초기화 (모달 취소 시 이전 삭제 요청 무효화)
    pendingDeleteFiles = [];

    // 항공 옵션이 로드되어 있지 않으면 재로드 시도
    if (currentAirlineName && airlineOptionCategories.length === 0) {
        console.log('Reloading airline options for:', currentAirlineName);
        await loadAirlineOptions(currentAirlineName);
    }

    // currentAirlineName이 없지만 항공편 정보가 있는 경우, 항공사명 추출 시도
    if (!currentAirlineName || airlineOptionCategories.length === 0) {
        const outFlightNo = document.getElementById('out_flight_no');
        if (outFlightNo && outFlightNo.value) {
            const flightValue = outFlightNo.value.trim();
            let detectedAirline = '';

            // 1. 항공사 코드에서 추출 시도 (예: "5J188" → "Cebu Pacific")
            detectedAirline = getAirlineNameFromFlightNumber(flightValue);

            // 2. 알려진 항공사명이 포함되어 있는지 확인 (예: "Cebu Pacific 5J 123")
            if (!detectedAirline) {
                const knownAirlines = ['Cebu Pacific', 'Philippine Airlines', 'AirAsia', 'Korean Air', 'Asiana'];
                for (const airline of knownAirlines) {
                    if (flightValue.toLowerCase().includes(airline.toLowerCase())) {
                        detectedAirline = airline;
                        break;
                    }
                }
            }

            if (detectedAirline) {
                console.log('Detected airline from flight info:', detectedAirline);
                await loadAirlineOptions(detectedAirline);
            }
        }
    }

    // 기존 여행자 데이터를 모달에 로드 (travelers → 모달 형식 변환)
    travelerModalData = (travelers || []).map(t => convertToModalFormat(t));
    travelerCardIdCounter = travelerModalData.length;

    // 여행자가 없고 Contact Person 정보가 있으면 자동으로 첫 번째 여행자 생성
    if (travelerModalData.length === 0) {
        const contactPerson = getContactPersonData();
        if (contactPerson && contactPerson.name) {
            const travelerFromContact = createTravelerFromContactPerson(contactPerson);
            travelerModalData.push(travelerFromContact);
            travelerCardIdCounter = 1;
        }
    }

    renderTravelerCards();
    openModal('traveler-modal');
}

// Contact Person 정보 가져오기
function getContactPersonData() {
    const name = document.getElementById('user_name')?.value || '';
    const email = document.getElementById('user_email')?.value || '';
    const phone = document.getElementById('user_phone')?.value || '';
    const countryCode = document.getElementById('country_code')?.value || '';
    const accountId = document.getElementById('customer_account_id')?.value || '';

    return {
        name: name,
        email: email,
        phone: phone,
        countryCode: countryCode,
        accountId: accountId
    };
}

// Contact Person에서 여행자 정보 생성
function createTravelerFromContactPerson(contactPerson) {
    // selectedCustomer가 있으면 전체 고객 데이터 사용
    const customer = selectedCustomer || {};

    // 이름: selectedCustomer의 fName/lName 우선, 없으면 contactPerson.name 파싱
    let firstName = customer.fName || '';
    let lastName = customer.lName || '';

    if (!firstName && !lastName && contactPerson.name) {
        const nameParts = (contactPerson.name || '').trim().split(/\s+/);
        if (nameParts.length >= 2) {
            lastName = nameParts.pop();
            firstName = nameParts.join(' ');
        } else if (nameParts.length === 1) {
            firstName = nameParts[0];
        }
    }

    // 나이 계산
    const birthDate = customer.dateOfBirth || '';
    const age = birthDate ? calculateAge(birthDate) : null;

    // 나이 기반 type 자동 계산
    let type = 'adult';
    if (age !== null) {
        if (age < 2) type = 'infant';
        else if (age < 7) type = 'child';
    }

    // 성별 및 title 설정
    const gender = (String(customer.gender || '').toLowerCase() === 'female') ? 'female' : 'male';
    const title = (gender === 'female') ? 'Ms' : 'Mr';

    travelerCardIdCounter++;
    return {
        id: travelerCardIdCounter,
        type: type,
        visaRequired: false,
        visaType: 'with_visa',
        title: title,
        gender: gender,
        firstName: firstName,
        lastName: lastName,
        age: age,
        birthDate: birthDate,
        nationality: customer.nationality || '',
        passportNo: customer.passportNumber || customer.passportNo || '',
        passportIssueDate: customer.passportIssueDate || customer.passportIssuedDate || '',
        passportExpiry: customer.passportExpiry || '',
        passportPhotoFile: null,
        passportPhotoUrl: customer.profileImage || customer.passportImage || customer.passportPhoto || '',
        visaDocumentFile: null,
        visaDocumentUrl: customer.visaDocument || '',
        isPrimary: true,
        childRoom: false,
        infantSeat: false,
        profile_source: '',
        // Contact Person 연동 정보
        fromContactPerson: true,
        contactEmail: contactPerson.email || customer.accountEmail || customer.emailAddress || '',
        contactPhone: contactPerson.phone || customer.contactNo || '',
        contactAccountId: contactPerson.accountId || customer.accountId || ''
    };
}

// Contact Person에서 여행자 추가 (수동)
window.addTravelerFromContactPerson = function() {
    const contactPerson = getContactPersonData();
    if (!contactPerson || !contactPerson.name) {
        alert('Please fill in Contact Person information first.');
        return;
    }

    const travelerFromContact = createTravelerFromContactPerson(contactPerson);
    // Primary 설정
    if (travelerModalData.length === 0) {
        travelerFromContact.isPrimary = true;
    } else {
        travelerFromContact.isPrimary = false;
    }
    travelerModalData.push(travelerFromContact);
    renderTravelerCards();
};

// 여행자 모달 닫기
function closeTravelerModal() {
    closeModal('traveler-modal');
}

// Type 라벨 반환 함수
function getTypeLabel(type) {
    switch(type) {
        case 'infant': return 'Infant';
        case 'child': return 'Child';
        case 'adult':
        default: return 'Adult';
    }
}

// 여행자 카드 렌더링
function renderTravelerCards() {
    const container = document.getElementById('traveler-cards-container');
    const countEl = document.getElementById('traveler-modal-count');

    if (!container) return;

    if (travelerModalData.length === 0) {
        container.innerHTML = '<div class="no-travelers-message">No travelers added. Click "+ Add Traveler" to add.</div>';
        if (countEl) countEl.textContent = '0 Travelers';
        renderTravelerSidebar();
        return;
    }

    if (countEl) countEl.textContent = `${travelerModalData.length} Traveler${travelerModalData.length > 1 ? 's' : ''}`;

    let html = '';
    travelerModalData.forEach((traveler, index) => {
        const isPrimary = traveler.isPrimary || index === 0;
        const hasPassportPhoto = traveler.passportPhotoFile || traveler.passportPhotoUrl;
        const photoFileName = traveler.passportPhotoFile ? traveler.passportPhotoFile.name : (traveler.passportPhotoUrl ? traveler.passportPhotoUrl.split('/').pop() : '');
        const typeLabel = getTypeLabel(traveler.type);
        const showDateWarning = traveler.passportIssueDate && traveler.passportExpiry &&
            new Date(traveler.passportIssueDate) >= new Date(traveler.passportExpiry);

        html += `
            <div class="traveler-card ${isPrimary ? 'is-primary' : ''}" data-index="${index}">
                <div class="traveler-card-header">
                    <div class="traveler-card-title">
                        <span>Traveler ${index + 1}</span>
                        ${isPrimary ? '<span class="badge-primary">Primary</span>' : ''}
                    </div>
                    <div class="traveler-card-actions">
                        ${!isPrimary ? `<button type="button" class="btn-set-primary" onclick="setPrimaryTraveler(${index})">Set as Primary</button>` : ''}
                        <button type="button" class="btn-delete-traveler" onclick="deleteTravelerCard(${index})">Delete</button>
                    </div>
                </div>
                <div class="traveler-card-body">
                    <!-- Row 1: Title, Gender, First Name, Last Name -->
                    <div class="form-group">
                        <label>Title</label>
                        <select onchange="updateTravelerField(${index}, 'title', this.value)">
                            <option value="Mr" ${traveler.title === 'Mr' ? 'selected' : ''}>Mr</option>
                            <option value="Ms" ${traveler.title === 'Ms' ? 'selected' : ''}>Ms</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select onchange="updateTravelerField(${index}, 'gender', this.value)">
                            <option value="male" ${traveler.gender === 'male' ? 'selected' : ''}>Male</option>
                            <option value="female" ${traveler.gender === 'female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>First Name <span style="color:#DC2626;">*</span></label>
                        <input type="text" value="${escapeHtml(traveler.firstName || '')}" onchange="updateTravelerField(${index}, 'firstName', this.value)" placeholder="First Name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span style="color:#DC2626;">*</span></label>
                        <input type="text" value="${escapeHtml(traveler.lastName || '')}" onchange="updateTravelerField(${index}, 'lastName', this.value)" placeholder="Last Name">
                    </div>

                    <!-- Row 2: Type, Age, Date of Birth, Nationality -->
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" id="traveler-type-${index}" value="${typeLabel}" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="text" id="traveler-age-${index}" value="${traveler.age != null ? traveler.age : '-'}" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" value="${traveler.birthDate || ''}" min="1900-01-01" max="2099-12-31" onchange="updateTravelerBirthDate(${index}, this.value)">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" value="${escapeHtml(traveler.nationality || '')}" onchange="updateTravelerField(${index}, 'nationality', this.value)" placeholder="Nationality">
                    </div>

                    <!-- Row 3: Passport No., Issue Date, Expiration Date, Visa Application -->
                    <div class="form-group">
                        <label>Passport No.</label>
                        <input type="text" value="${escapeHtml(traveler.passportNo || '')}" onchange="updateTravelerField(${index}, 'passportNo', this.value)" placeholder="Passport Number">
                    </div>
                    <div class="form-group">
                        <label>Passport Issue Date</label>
                        <input type="date" value="${traveler.passportIssueDate || ''}" min="1900-01-01" max="2099-12-31" onchange="updatePassportIssueDate(${index}, this.value)">
                    </div>
                    <div class="form-group">
                        <label>Passport Expiration Date</label>
                        <input type="date" value="${traveler.passportExpiry || ''}" min="1900-01-01" max="2099-12-31" onchange="updatePassportExpirationDate(${index}, this.value)">
                    </div>
                    <div class="form-group">
                        <label>Visa Application</label>
                        <select id="visa-select-${index}" onchange="handleVisaTypeChange(${index}, this.value)">
                            <option value="" ${!traveler.visaType ? 'selected' : ''} disabled>Select option</option>
                            <option value="with_visa" ${traveler.visaType === 'with_visa' ? 'selected' : ''}>With Visa</option>
                            <option value="group" ${traveler.visaType === 'group' ? 'selected' : ''}>Group Visa +₱1500</option>
                            <option value="individual" ${traveler.visaType === 'individual' ? 'selected' : ''}>Individual Visa +₱1900</option>
                            <option value="foreign" ${traveler.visaType === 'foreign' ? 'selected' : ''}>Foreign Passport</option>
                        </select>
                    </div>

                    <!-- Passport Date Warning -->
                    <div class="form-group col-span-4 passport-date-warning-container" id="passport-date-warning-${index}" style="display: ${showDateWarning ? 'block' : 'none'};">
                        <span class="passport-date-warning">Issue date cannot be later than expiration date.</span>
                    </div>

                    <!-- Row 4: Passport Photo + Visa Upload + Child Room Option -->
                    <div class="form-group">
                        <label>Passport Photo</label>
                        <div class="passport-photo-upload">
                            <input type="file" id="passport-photo-${index}" accept="image/*" onchange="handlePassportPhotoUpload(${index}, this)" style="display:none;">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('passport-photo-${index}').click()">
                                <img src="../image/upload.svg" alt=""> Upload Photo
                            </button>
                            <div class="passport-photo-info ${hasPassportPhoto ? '' : 'hidden'}" id="passport-photo-info-${index}">
                                <span class="photo-filename">${escapeHtml(photoFileName)}</span>
                                <div class="file-action-buttons">
                                    <button type="button" class="btn-file-action btn-preview" onclick="previewPassportPhoto(${index})" title="Preview">
                                        <img src="../image/search2.svg" alt="Preview">
                                    </button>
                                    <button type="button" class="btn-file-action btn-download" onclick="downloadPassportPhoto(${index})" title="Download">
                                        <img src="../image/buttun-download.svg" alt="Download">
                                    </button>
                                    <button type="button" class="btn-file-action btn-delete" onclick="removePassportPhoto(${index})" title="Delete">
                                        <img src="../image/button-close2.svg" alt="Delete">
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group visa-upload-container" id="visa-upload-container-${index}" style="display: ${traveler.visaType === 'with_visa' || traveler.visaType === 'foreign' ? 'block' : 'none'};">
                        <label>Visa Document</label>
                        <div class="visa-document-upload">
                            <input type="file" id="visa-document-${index}" accept="image/*,.pdf" onchange="handleVisaDocumentUpload(${index}, this)" style="display:none;">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('visa-document-${index}').click()">
                                <img src="../image/upload.svg" alt=""> Upload Visa
                            </button>
                            <div class="visa-document-info ${traveler.visaDocumentFile || traveler.visaDocumentUrl ? '' : 'hidden'}" id="visa-document-info-${index}">
                                <span class="visa-filename">${escapeHtml(traveler.visaDocumentFile?.name || traveler.visaDocumentUrl?.split('/').pop() || '')}</span>
                                <div class="file-action-buttons">
                                    <button type="button" class="btn-file-action btn-preview" onclick="previewVisaDocument(${index})" title="Preview">
                                        <img src="../image/search2.svg" alt="Preview">
                                    </button>
                                    <button type="button" class="btn-file-action btn-download" onclick="downloadVisaDocument(${index})" title="Download">
                                        <img src="../image/buttun-download.svg" alt="Download">
                                    </button>
                                    <button type="button" class="btn-file-action btn-delete" onclick="removeVisaDocument(${index})" title="Delete">
                                        <img src="../image/button-close2.svg" alt="Delete">
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="child-room-container-${index}" style="display: ${traveler.type === 'child' ? 'block' : 'none'};">
                        <label>Child Room</label>
                        <select onchange="updateTravelerField(${index}, 'childRoom', this.value === 'yes')">
                            <option value="no" ${!traveler.childRoom ? 'selected' : ''}>No</option>
                            <option value="yes" ${traveler.childRoom ? 'selected' : ''}>Yes</option>
                        </select>
                    </div>
                    <div class="form-group" id="infant-seat-container-${index}" style="display: ${traveler.type === 'infant' ? 'block' : 'none'};">
                        <label>Infant Seat</label>
                        <select onchange="updateTravelerField(${index}, 'infantSeat', this.value === 'yes')">
                            <option value="no" ${!traveler.infantSeat ? 'selected' : ''}>No (Lap)</option>
                            <option value="yes" ${traveler.infantSeat ? 'selected' : ''}>Yes (Seat)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Profile/Source of Income <span style="color:#DC2626;">*</span></label>
                        <input type="text" value="${escapeHtml(traveler.profile_source || '')}" placeholder="Profile/Source of Income" onchange="updateTravelerField(${index}, 'profile_source', this.value)">
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    // 사이드바 네비게이션 렌더링
    renderTravelerSidebar();
}

// 여행자 사이드바 네비게이션 렌더링
function renderTravelerSidebar() {
    const sidebarNav = document.getElementById('traveler-sidebar-nav');
    if (!sidebarNav) return;

    if (travelerModalData.length === 0) {
        sidebarNav.innerHTML = '<div style="padding: 20px; text-align: center; color: #9CA3AF; font-size: 13px;">No travelers</div>';
        return;
    }

    let html = '';
    travelerModalData.forEach((traveler, index) => {
        const isPrimary = traveler.isPrimary || index === 0;
        const fullName = `${traveler.firstName || ''} ${traveler.lastName || ''}`.trim() || 'No Name';
        const typeLabel = getTypeLabel(traveler.type);

        html += `
            <div class="sidebar-nav-item ${isPrimary ? 'is-primary' : ''}" data-index="${index}" onclick="scrollToTraveler(${index})">
                <span class="nav-item-number">${index + 1}</span>
                <div class="nav-item-info">
                    <div class="nav-item-name">${escapeHtml(fullName)}</div>
                    <div class="nav-item-type">${typeLabel}</div>
                </div>
                ${isPrimary ? '<span class="nav-item-badge">Primary</span>' : ''}
            </div>
        `;
    });

    sidebarNav.innerHTML = html;
}

// 해당 traveler 카드로 스크롤
window.scrollToTraveler = function(index) {
    const container = document.getElementById('traveler-cards-container');
    const mainArea = document.querySelector('.traveler-modal-main');
    if (!container || !mainArea) return;

    const card = container.querySelector(`.traveler-card[data-index="${index}"]`);
    if (!card) return;

    // 스크롤 애니메이션
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // 하이라이트 효과
    card.style.transition = 'box-shadow 0.3s';
    card.style.boxShadow = '0 0 0 3px rgba(0, 80, 200, 0.3)';
    setTimeout(() => {
        card.style.boxShadow = '';
    }, 1500);

    // 사이드바 active 상태 업데이트
    updateSidebarActive(index);
};

// 사이드바 active 상태 업데이트
function updateSidebarActive(activeIndex) {
    const sidebarNav = document.getElementById('traveler-sidebar-nav');
    if (!sidebarNav) return;

    const items = sidebarNav.querySelectorAll('.sidebar-nav-item');
    items.forEach((item, i) => {
        if (i === activeIndex) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// 여행자 필드 업데이트
window.updateTravelerField = function(index, field, value) {
    if (travelerModalData[index]) {
        travelerModalData[index][field] = value;

        // 이름 변경 시 사이드바 업데이트
        if (field === 'firstName' || field === 'lastName') {
            updateSidebarNavItem(index);
        }
    }
};

// 사이드바 개별 아이템 업데이트 (이름)
function updateSidebarNavItem(index) {
    const sidebarNav = document.getElementById('traveler-sidebar-nav');
    if (!sidebarNav) return;

    const item = sidebarNav.querySelector(`.sidebar-nav-item[data-index="${index}"]`);
    if (!item) return;

    const traveler = travelerModalData[index];
    if (!traveler) return;

    const nameEl = item.querySelector('.nav-item-name');
    if (nameEl) {
        const fullName = `${traveler.firstName || ''} ${traveler.lastName || ''}`.trim() || 'No Name';
        nameEl.textContent = fullName;
    }
}

// 사이드바 개별 아이템 Type 업데이트
function updateSidebarNavItemType(index, type) {
    const sidebarNav = document.getElementById('traveler-sidebar-nav');
    if (!sidebarNav) return;

    const item = sidebarNav.querySelector(`.sidebar-nav-item[data-index="${index}"]`);
    if (!item) return;

    const typeEl = item.querySelector('.nav-item-type');
    if (typeEl) {
        typeEl.textContent = getTypeLabel(type);
    }
}

// 생년월일 업데이트 시 나이와 Type 자동 계산
window.updateTravelerBirthDate = function(index, birthDate) {
    if (travelerModalData[index]) {
        travelerModalData[index].birthDate = birthDate;
        // 나이 자동 계산
        if (birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            travelerModalData[index].age = age;

            // Type 자동 계산 (미국식 나이 기준)
            // 0~2세 미만: infant, 2~7세: child, 7세 이상: adult
            let type;
            if (age < 2) {
                type = 'infant';
            } else if (age < 7) {
                type = 'child';
            } else {
                type = 'adult';
            }
            travelerModalData[index].type = type;

            // UI 업데이트 - 나이 필드
            const ageInput = document.getElementById(`traveler-age-${index}`);
            if (ageInput) ageInput.value = age;

            // UI 업데이트 - Type 필드
            const typeInput = document.getElementById(`traveler-type-${index}`);
            if (typeInput) typeInput.value = getTypeLabel(type);

            // 사이드바 Type 업데이트
            updateSidebarNavItemType(index, type);

            // Child Room 컨테이너 표시/숨김
            const childRoomContainer = document.getElementById(`child-room-container-${index}`);
            if (childRoomContainer) {
                childRoomContainer.style.display = type === 'child' ? 'block' : 'none';
                // child가 아니면 childRoom 값 초기화
                if (type !== 'child') {
                    travelerModalData[index].childRoom = false;
                }
            }

            // Infant Seat 컨테이너 표시/숨김
            const infantSeatContainer = document.getElementById(`infant-seat-container-${index}`);
            if (infantSeatContainer) {
                infantSeatContainer.style.display = type === 'infant' ? 'block' : 'none';
                if (type !== 'infant') {
                    travelerModalData[index].infantSeat = false;
                }
            }
        } else {
            // 생년월일이 비어있으면 나이와 Type 초기화
            travelerModalData[index].age = null;
            travelerModalData[index].type = 'adult';
            travelerModalData[index].childRoom = false;
            travelerModalData[index].infantSeat = false;

            const ageInput = document.getElementById(`traveler-age-${index}`);
            if (ageInput) ageInput.value = '-';

            const typeInput = document.getElementById(`traveler-type-${index}`);
            if (typeInput) typeInput.value = 'Adult';

            // Child Room 컨테이너 숨김
            const childRoomContainer = document.getElementById(`child-room-container-${index}`);
            if (childRoomContainer) {
                childRoomContainer.style.display = 'none';
            }

            // Infant Seat 컨테이너 숨김
            const infantSeatContainer = document.getElementById(`infant-seat-container-${index}`);
            if (infantSeatContainer) {
                infantSeatContainer.style.display = 'none';
            }
        }
    }
};

// 여권 사진 업로드 처리
window.handlePassportPhotoUpload = function(index, input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        travelerModalData[index].passportPhotoFile = file;

        // UI 업데이트
        const infoEl = document.getElementById(`passport-photo-info-${index}`);
        if (infoEl) {
            infoEl.classList.remove('hidden');
            const filenameEl = infoEl.querySelector('.photo-filename');
            if (filenameEl) filenameEl.textContent = file.name;
        }
    }
};

// 여권 사진 제거
window.removePassportPhoto = function(index) {
    if (travelerModalData[index]) {
        // 기존 서버 URL이 있으면 삭제 대기 목록에 추가
        const existingUrl = travelerModalData[index].passportPhotoUrl;
        if (existingUrl && typeof existingUrl === 'string' && existingUrl.trim()) {
            pendingDeleteFiles.push({
                fileUrl: existingUrl,
                fileType: 'passport'
            });
        }

        travelerModalData[index].passportPhotoFile = null;
        travelerModalData[index].passportPhotoUrl = null;

        // UI 업데이트
        const infoEl = document.getElementById(`passport-photo-info-${index}`);
        if (infoEl) infoEl.classList.add('hidden');

        // 파일 입력 초기화
        const fileInput = document.getElementById(`passport-photo-${index}`);
        if (fileInput) fileInput.value = '';
    }
};

// 여권 사진 미리보기
window.previewPassportPhoto = function(index) {
    const traveler = travelerModalData[index];
    if (!traveler) return;

    let imageUrl = null;
    let fileName = 'Passport Photo';

    // 새로 업로드한 파일인 경우
    if (traveler.passportPhotoFile) {
        imageUrl = URL.createObjectURL(traveler.passportPhotoFile);
        fileName = traveler.passportPhotoFile.name;
    }
    // 기존 서버에 저장된 이미지인 경우
    else if (traveler.passportPhotoUrl) {
        imageUrl = normalizePassportImageUrl(traveler.passportPhotoUrl);
        fileName = traveler.passportPhotoUrl.split('/').pop() || 'Passport Photo';
    }

    if (!imageUrl) {
        alert('No passport photo available');
        return;
    }

    // 파일 뷰어 모달 사용
    const modal = document.getElementById('file-viewer-modal');
    const title = document.getElementById('file-viewer-title');
    const content = document.getElementById('file-viewer-content');

    if (modal && title && content) {
        title.textContent = `Passport Photo - ${fileName}`;
        content.innerHTML = `<img src="${imageUrl}" alt="Passport Photo" style="max-width: 100%; max-height: 70vh; object-fit: contain;">`;
        modal.style.display = 'flex';
    }
};

// 여권 사진 다운로드
window.downloadPassportPhoto = function(index) {
    const traveler = travelerModalData[index];
    if (!traveler) return;

    let downloadUrl = null;
    let fileName = 'passport_photo';

    // 새로 업로드한 파일인 경우
    if (traveler.passportPhotoFile) {
        downloadUrl = URL.createObjectURL(traveler.passportPhotoFile);
        fileName = traveler.passportPhotoFile.name;
    }
    // 기존 서버에 저장된 이미지인 경우
    else if (traveler.passportPhotoUrl) {
        downloadUrl = normalizePassportImageUrl(traveler.passportPhotoUrl);
        fileName = traveler.passportPhotoUrl.split('/').pop() || 'passport_photo';
    }

    if (!downloadUrl) {
        alert('No passport photo available');
        return;
    }

    // 다운로드 링크 생성 및 클릭
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = fileName;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    // Blob URL인 경우 정리 (새 파일이 아닌 경우 - 서버 URL)
    if (!traveler.passportPhotoFile && downloadUrl.startsWith('blob:')) {
        URL.revokeObjectURL(downloadUrl);
    }
};

// Visa Type 변경 핸들러
window.handleVisaTypeChange = function(index, value) {
    updateTravelerField(index, 'visaType', value);
    // with_visa, foreign은 이미 비자가 있으므로 visaRequired = false
    const hasVisa = value === 'with_visa' || value === 'foreign';
    updateTravelerField(index, 'visaRequired', !hasVisa && value !== '');

    // Visa Upload 컨테이너 표시/숨김 (with_visa, foreign일 때 표시)
    const container = document.getElementById(`visa-upload-container-${index}`);
    if (container) {
        container.style.display = hasVisa ? 'block' : 'none';
    }

    // with_visa, foreign이 아닌 경우 기존 visa document 초기화
    if (!hasVisa) {
        removeVisaDocument(index);
    }
};

// Visa Document 업로드 처리
window.handleVisaDocumentUpload = function(index, input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];

        // 파일 크기 체크 (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('File size must be less than 10MB');
            input.value = '';
            return;
        }

        // 파일 형식 체크
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            alert('Only image files (JPEG, PNG, GIF) and PDF are allowed');
            input.value = '';
            return;
        }

        travelerModalData[index].visaDocumentFile = file;

        // UI 업데이트
        const infoEl = document.getElementById(`visa-document-info-${index}`);
        if (infoEl) {
            infoEl.classList.remove('hidden');
            const filenameEl = infoEl.querySelector('.visa-filename');
            if (filenameEl) filenameEl.textContent = file.name;
        }
    }
};

// Visa Document 제거
window.removeVisaDocument = function(index) {
    if (travelerModalData[index]) {
        // 기존 서버 URL이 있으면 삭제 대기 목록에 추가
        const existingUrl = travelerModalData[index].visaDocumentUrl;
        if (existingUrl && typeof existingUrl === 'string' && existingUrl.trim()) {
            pendingDeleteFiles.push({
                fileUrl: existingUrl,
                fileType: 'visa'
            });
        }

        travelerModalData[index].visaDocumentFile = null;
        travelerModalData[index].visaDocumentUrl = null;

        // UI 업데이트
        const infoEl = document.getElementById(`visa-document-info-${index}`);
        if (infoEl) infoEl.classList.add('hidden');

        // 파일 입력 초기화
        const fileInput = document.getElementById(`visa-document-${index}`);
        if (fileInput) fileInput.value = '';
    }
};

// Visa Document 미리보기
window.previewVisaDocument = function(index) {
    const traveler = travelerModalData[index];
    if (!traveler) return;

    let fileUrl = null;
    let fileName = 'Visa Document';
    let fileType = '';

    // 새로 업로드한 파일인 경우
    if (traveler.visaDocumentFile) {
        fileUrl = URL.createObjectURL(traveler.visaDocumentFile);
        fileName = traveler.visaDocumentFile.name;
        fileType = traveler.visaDocumentFile.type;
    }
    // 기존 서버에 저장된 파일인 경우
    else if (traveler.visaDocumentUrl) {
        fileUrl = normalizePassportImageUrl(traveler.visaDocumentUrl);
        fileName = traveler.visaDocumentUrl.split('/').pop() || 'Visa Document';
        // 확장자로 파일 타입 추정
        const ext = fileName.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            fileType = 'application/pdf';
        } else {
            fileType = 'image/' + ext;
        }
    }

    if (!fileUrl) {
        alert('No visa document available');
        return;
    }

    // 파일 뷰어 모달 사용
    const modal = document.getElementById('file-viewer-modal');
    const title = document.getElementById('file-viewer-title');
    const content = document.getElementById('file-viewer-content');

    if (modal && title && content) {
        title.textContent = `Visa Document - ${fileName}`;

        // PDF인 경우 iframe 사용, 이미지인 경우 img 태그 사용
        if (fileType === 'application/pdf') {
            content.innerHTML = `<iframe src="${fileUrl}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
        } else {
            content.innerHTML = `<img src="${fileUrl}" alt="Visa Document" style="max-width: 100%; max-height: 70vh; object-fit: contain;">`;
        }

        modal.style.display = 'flex';
    }
};

// Visa Document 다운로드
window.downloadVisaDocument = function(index) {
    const traveler = travelerModalData[index];
    if (!traveler) return;

    let downloadUrl = null;
    let fileName = 'visa_document';

    // 새로 업로드한 파일인 경우
    if (traveler.visaDocumentFile) {
        downloadUrl = URL.createObjectURL(traveler.visaDocumentFile);
        fileName = traveler.visaDocumentFile.name;
    }
    // 기존 서버에 저장된 파일인 경우
    else if (traveler.visaDocumentUrl) {
        downloadUrl = normalizePassportImageUrl(traveler.visaDocumentUrl);
        fileName = traveler.visaDocumentUrl.split('/').pop() || 'visa_document';
    }

    if (!downloadUrl) {
        alert('No visa document available');
        return;
    }

    // 다운로드 링크 생성 및 클릭
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = fileName;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    // Blob URL인 경우 정리 (새 파일이 아닌 경우 - 서버 URL)
    if (!traveler.visaDocumentFile && downloadUrl.startsWith('blob:')) {
        URL.revokeObjectURL(downloadUrl);
    }
};

// 여권 발행일 업데이트
window.updatePassportIssueDate = function(index, value) {
    if (travelerModalData[index]) {
        travelerModalData[index].passportIssueDate = value;
        validatePassportDates(index);
    }
};

// 여권 만료일 업데이트
window.updatePassportExpirationDate = function(index, value) {
    if (travelerModalData[index]) {
        travelerModalData[index].passportExpiry = value;
        validatePassportDates(index);
    }
};

// 여권 날짜 유효성 검사 (발행일 > 만료일 체크)
function validatePassportDates(index) {
    const t = travelerModalData[index];
    const warningEl = document.getElementById(`passport-date-warning-${index}`);

    if (!warningEl) return;

    if (t.passportIssueDate && t.passportExpiry) {
        const issueDate = new Date(t.passportIssueDate);
        const expiryDate = new Date(t.passportExpiry);

        if (issueDate >= expiryDate) {
            warningEl.style.display = 'block';
        } else {
            warningEl.style.display = 'none';
        }
    } else {
        warningEl.style.display = 'none';
    }
}

// 여행자 카드 추가
function addTravelerCard() {
    travelerCardIdCounter++;
    const newTraveler = {
        id: travelerCardIdCounter,
        type: 'adult',
        visaRequired: false,
        visaType: '',
        title: 'Mr',
        gender: 'male',
        firstName: '',
        lastName: '',
        age: null,
        birthDate: '',
        nationality: '',
        passportNo: '',
        passportIssueDate: '',
        passportExpiry: '',
        passportPhotoFile: null,
        passportPhotoUrl: null,
        visaDocumentFile: null,
        visaDocumentUrl: null,
        isPrimary: travelerModalData.length === 0,
        childRoom: false,
        infantSeat: false,
        profile_source: ''
    };
    travelerModalData.push(newTraveler);
    renderTravelerCards();
}

// 여행자 카드 삭제
function deleteTravelerCard(index) {
    if (travelerModalData.length <= 1) {
        alert('At least one traveler is required.');
        return;
    }

    const wasPrimary = travelerModalData[index].isPrimary;
    travelerModalData.splice(index, 1);

    // 삭제된 카드가 primary였으면 첫 번째를 primary로 설정
    if (wasPrimary && travelerModalData.length > 0) {
        travelerModalData[0].isPrimary = true;
    }

    renderTravelerCards();
}

// 대표 여행자 설정
function setPrimaryTraveler(index) {
    travelerModalData.forEach((t, i) => {
        t.isPrimary = (i === index);
    });
    renderTravelerCards();
}

// 모달에서 여행자 저장
function saveTravelersFromModal() {
    // 유효성 검사
    for (let i = 0; i < travelerModalData.length; i++) {
        const t = travelerModalData[i];
        const missing = [];
        if (!t.firstName) missing.push('First Name');
        if (!t.lastName) missing.push('Last Name');
        if (!t.profile_source || !t.profile_source.trim()) missing.push('Profile/Source of Income');
        if (missing.length > 0) {
            alert(`Traveler ${i + 1}: ${missing.join(', ')} is required.`);
            return;
        }
    }

    // 삭제 대기 파일들을 서버에서 삭제
    if (pendingDeleteFiles.length > 0) {
        deletePendingFiles();
    }

    // travelers 배열에 저장 (모달 형식 → travelers 형식 변환)
    travelers.length = 0; // 기존 배열 비우기
    travelerModalData.forEach(t => {
        travelers.push(convertToTravelersFormat(t));
    });

    // 메인 페이지 요약 업데이트
    updateTravelerSummary();

    // 모달 닫기
    closeTravelerModal();
}

// 삭제 대기 파일들을 서버에서 삭제
async function deletePendingFiles() {
    const filesToDelete = [...pendingDeleteFiles];
    pendingDeleteFiles = []; // 목록 초기화

    for (const fileInfo of filesToDelete) {
        try {
            const response = await fetch('/admin/backend/api/delete_traveler_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fileUrl: fileInfo.fileUrl,
                    fileType: fileInfo.fileType
                })
            });

            const result = await response.json();
            if (result.success) {
                console.log(`[DELETE] Successfully deleted ${fileInfo.fileType} file:`, fileInfo.fileUrl);
            } else {
                console.warn(`[DELETE] Failed to delete ${fileInfo.fileType} file:`, result.message);
            }
        } catch (error) {
            console.error(`[DELETE] Error deleting ${fileInfo.fileType} file:`, error);
        }
    }
}

// 메인 페이지 여행자 요약 업데이트
function updateTravelerSummary() {
    const listEl = document.getElementById('traveler-summary-list');
    const countEl = document.getElementById('traveler-summary-count');

    if (!listEl) return;

    if (!travelers || travelers.length === 0) {
        listEl.innerHTML = '<p class="no-traveler-message">No travelers added yet. Click "Manage Travelers" to add.</p>';
        if (countEl) countEl.textContent = '0 Travelers';
        return;
    }

    if (countEl) countEl.textContent = `${travelers.length} Traveler${travelers.length > 1 ? 's' : ''}`;

    let html = '';
    travelers.forEach((t, index) => {
        const isPrimary = t.isMainTraveler || index === 0;
        const fullName = `${t.title || ''} ${t.firstName || ''} ${t.lastName || ''}`.trim() || 'No Name';

        // 상세 정보
        const typeLabel = t.type === 'adult' ? 'Adult' : (t.type === 'child' ? 'Child' : (t.type === 'infant' ? 'Infant' : t.type));
        const visaLabel = t.visaRequired ? 'Visa Applied' : '';
        const ageLabel = t.age ? `${t.age}y` : '';
        const passportLabel = t.passportNumber ? `PP: ${t.passportNumber}` : '';

        const detailParts = [typeLabel, t.gender, t.nationality, ageLabel, visaLabel, passportLabel].filter(Boolean);
        const details = detailParts.join(' / ');

        html += `
            <div class="traveler-summary-item ${isPrimary ? 'is-primary' : ''}">
                <div class="traveler-summary-info">
                    ${isPrimary ? '<span class="badge-primary">Primary</span>' : ''}
                    <span class="traveler-summary-name">${escapeHtml(fullName)}</span>
                </div>
                <span class="traveler-summary-details">${escapeHtml(details)}</span>
            </div>
        `;
    });

    listEl.innerHTML = html;
}

// 파일 뷰어 모달 열기 (공통)
function openFileViewerModal(title, fileUrl) {
    const titleEl = document.getElementById('file-viewer-title');
    const contentEl = document.getElementById('file-viewer-content');

    if (titleEl) titleEl.textContent = title;

    // 파일 확장자 확인
    const ext = fileUrl.split('.').pop().toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        // 이미지 파일
        contentEl.innerHTML = `<img src="${fileUrl}" alt="${title}" />`;
    } else if (ext === 'pdf') {
        // PDF 파일
        contentEl.innerHTML = `<iframe src="${fileUrl}" title="${title}"></iframe>`;
    } else {
        // 기타 파일
        contentEl.innerHTML = `<p style="padding: 40px;">미리보기를 지원하지 않는 파일 형식입니다.</p>`;
    }

    openModal('file-viewer-modal');
}

// 상품 Flyer 보기 (모달 뷰어)
function openProductFlyer(packageId) {
    // 캐시에서 상품 찾기
    const pkg = searchedProductsCache.find(p => p.packageId == packageId);
    if (!pkg) {
        alert('상품 정보를 찾을 수 없습니다.');
        return;
    }

    // flyer_file 경로 확인
    const flyerFile = pkg.flyer_file;
    if (!flyerFile) {
        alert('등록된 Flyer 파일이 없습니다.');
        return;
    }

    // 파일 경로 생성 및 모달 뷰어로 열기
    const fileUrl = `${window.location.origin}/uploads/products/${flyerFile}`;
    openFileViewerModal('Flyer', fileUrl);
}

// 상품 Detail 보기 (모달 뷰어 - 세로 스크롤)
function openProductDetail(packageId) {
    // 캐시에서 상품 찾기
    const pkg = searchedProductsCache.find(p => p.packageId == packageId);
    if (!pkg) {
        alert('상품 정보를 찾을 수 없습니다.');
        return;
    }

    // detail_file 경로 확인
    const detailFile = pkg.detail_file;
    if (!detailFile) {
        alert('등록된 Detail 파일이 없습니다.');
        return;
    }

    // 파일 경로 생성 및 모달 뷰어로 열기
    const fileUrl = `${window.location.origin}/uploads/products/${detailFile}`;
    openFileViewerModal('Detail', fileUrl);
}

// 상품 Itinerary 다운로드
function openProductItinerary(packageId) {
    // 캐시에서 상품 찾기
    const pkg = searchedProductsCache.find(p => p.packageId == packageId);
    if (!pkg) {
        alert('상품 정보를 찾을 수 없습니다.');
        return;
    }

    // itinerary_file 경로 확인
    const itineraryFile = pkg.itinerary_file;
    if (!itineraryFile) {
        alert('등록된 Itinerary 파일이 없습니다.');
        return;
    }

    // 파일 다운로드
    const fileUrl = `${window.location.origin}/uploads/products/${itineraryFile}`;
    const link = document.createElement('a');
    link.href = fileUrl;
    link.download = itineraryFile;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 현재 선택된 상품 카테고리 (필터)
let currentProductCategory = '';

// 카테고리별 필터링
function filterByCategory(category) {
    currentProductCategory = category;
    document.querySelectorAll('#product-search-modal .category-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.category === category);
    });
    searchProducts();
}

// 상품 검색 모달 열기
function openProductSearchModal() {
    selectedProductInModal = null;
    currentProductCategory = '';
    document.getElementById('product-search-input').value = '';
    document.getElementById('product-search-results').innerHTML = '';
    // 카테고리 탭 초기화
    document.querySelectorAll('#product-search-modal .category-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.category === '');
    });
    // SMT (#164): disable confirm until a product is selected
    try {
        const btn = document.getElementById('product-search-confirm');
        if (btn) btn.disabled = true;
    } catch (_) {}
    openModal('product-search-modal');
    // 모달 열릴 때 전체 상품 목록 자동 로드
    searchProducts();
}

// 상품 검색
async function searchProducts() {
    const searchInput = document.getElementById('product-search-input');
    const searchTerm = searchInput.value.trim();
    const resultsContainer = document.getElementById('product-search-results');
    
    try {
        resultsContainer.innerHTML = `<div class="is-center">${getText('searching')}</div>`;

        // B2B 상품만
        const qs = new URLSearchParams();
        qs.set('limit', '50');
        qs.set('salesTarget', 'B2B');
        if (searchTerm) qs.set('search', searchTerm);
        if (currentProductCategory) qs.set('category', currentProductCategory);
        const apiUrl = `${window.location.origin}/backend/api/packages.php?${qs.toString()}`;
        const response = await fetch(apiUrl, { credentials: 'same-origin' });
        const responseText = await response.text();
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 200)}`);
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
        }
        
        if (result.success && result.data && result.data.length > 0) {
            // 카테고리별 정렬: LAND ONLY(private) → Korea(season) → Other Country(us)
            const categoryOrder = { 'private': 0, 'season': 1, 'us': 2 };
            const sortedData = result.data.sort((a, b) => {
                const orderA = categoryOrder[a.packageCategory] ?? 99;
                const orderB = categoryOrder[b.packageCategory] ?? 99;
                return orderA - orderB;
            });

            // 검색 결과를 캐시에 저장
            searchedProductsCache = sortedData;

            let html = '<div class="product-list">';
            sortedData.forEach(pkg => {
                const descText = htmlToPlainText(pkg.packageDescription || '');
                const hasFlyer = pkg.flyer_file ? 'has-file' : 'no-file';
                const hasDetail = pkg.detail_file ? 'has-file' : 'no-file';
                const hasItinerary = pkg.itinerary_file ? 'has-file' : 'no-file';
                // (HTML 태그 수정) 디코딩 적용
                html += `
                    <div class="product-item" data-package-id="${pkg.packageId}" onclick="selectProductInModal(${pkg.packageId})">
                        <div class="product-item-content">
                            <div class="product-info">
                                <div class="product-name">${escapeHtml(decodeHtmlEntities(pkg.packageName) || '')}</div>
                                <div class="product-price">₱${formatCurrency(pkg.b2bPrice || pkg.packagePrice || 0)}</div>
                                <div class="product-description">${escapeHtml(descText.substring(0, 100))}...</div>
                            </div>
                            <div class="product-actions">
                                <button type="button" class="btn-product-action ${hasFlyer}" onclick="event.stopPropagation(); openProductFlyer(${pkg.packageId})" title="Flyer 보기">Flyer</button>
                                <button type="button" class="btn-product-action ${hasDetail}" onclick="event.stopPropagation(); openProductDetail(${pkg.packageId})" title="Detail 보기">Detail</button>
                                <button type="button" class="btn-product-action btn-download ${hasItinerary}" onclick="event.stopPropagation(); openProductItinerary(${pkg.packageId})" title="Itinerary 다운로드">↓ Itinerary</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            resultsContainer.innerHTML = html;
        } else {
            resultsContainer.innerHTML = `<div class="is-center">${getText('noResults')}</div>`;
        }
    } catch (error) {
        console.error('Error searching products:', error);
        resultsContainer.innerHTML = `<div class="is-center">${getText('errorOccurred')}</div>`;
    }
}

// HTML(Quill 저장값 포함) → 순수 텍스트 변환
function htmlToPlainText(input) {
    const html = (input ?? '').toString();
    if (!html) return '';
    try {
        const div = document.createElement('div');
        div.innerHTML = html;
        const text = (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
        return text;
    } catch (e) {
        // fallback (최소 보호)
        return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }
}

// 모달에서 상품 선택
window.selectProductInModal = function(packageId) {
    // 이전 선택 제거
    document.querySelectorAll('.product-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // 현재 선택 표시
    const selectedItem = document.querySelector(`[data-package-id="${packageId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
    }
    
    selectedProductInModal = packageId;
    // SMT (#164): enable confirm when selected
    try {
        const btn = document.getElementById('product-search-confirm');
        if (btn) btn.disabled = false;
    } catch (_) {}
};

// 상품 선택 확인 → Next 버튼: 상품 선택 후 날짜 선택 모달로 이동
async function confirmProductSelection() {
    if (!selectedProductInModal) {
        alert(getText('pleaseSelectProduct'));
        return;
    }

    // 상품 변경 감지 및 여행 시작일 초기화
    if (previousPackageId !== null && previousPackageId !== selectedProductInModal) {
        // 상품이 바뀌면 선금 자동 계산으로 복귀
        isDepositManuallyEdited = false;

        const departureDateInput = document.getElementById('departure_date');
        const departureDateValueInput = document.getElementById('departure_date_value');
        const departureDateBtn = document.getElementById('departure_date_btn');
        const returnDateInput = document.getElementById('return_date');
        if (departureDateInput) {
            departureDateInput.value = '';
            departureDateInput.setAttribute('readonly', 'readonly');
            departureDateInput.disabled = true;
        }
        if (departureDateValueInput) {
            departureDateValueInput.value = '';
        }
        if (departureDateBtn) {
            departureDateBtn.disabled = true;
        }
        if (returnDateInput) {
            returnDateInput.value = '';
            returnDateInput.disabled = true;
        }
        selectedDateInfo = null;
        selectedDateInCalendar = null;
        availableDates = [];
        availableDatesByMonth = {};

        // 항공편 정보 섹션 제거
        removeFlightInfoSection();
    }

    previousPackageId = selectedProductInModal;

    // 상품 정보 로드 (await으로 완료 대기)
    await loadProductDetail(selectedProductInModal);

    // 상품 검색 모달 닫기
    closeModal('product-search-modal');

    // 예약 이력 업데이트
    if (selectedProductInModal) {
        addReservationHistory('상품 선택: ' + (selectedProductInModal.packageName || selectedProductInModal));
    }

    // 바로 날짜 선택 모달 열기
    setTimeout(() => {
        openDatePickerModal();
    }, 300);
}

// 상품 상세 정보 로드
async function loadProductDetail(packageId) {
    try {
        // NOTE: agent 예약 생성 화면은 상품의 인원별 요금(option_name/price)을 그대로 보여야 함
        // - packages.php는 리스트용으로 pricingOptions가 누락될 수 있어 package-detail.php를 사용한다.
        const apiUrl = `${window.location.origin}/backend/api/package-detail.php?id=${encodeURIComponent(packageId)}`;
        const response = await fetch(apiUrl, { credentials: 'same-origin' });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result?.success) {
            throw new Error(result?.message || `HTTP ${response.status}`);
        }
        
        if (result.success && result.data && result.data.package) {
            const pkg = result.data.package;
            // pricingOptions(인원 옵션) 합치기
            selectedPackage = {
                ...pkg,
                pricingOptions: Array.isArray(result.data.pricingOptions) ? result.data.pricingOptions : [],
                // 여행기간 보정용: 실제 등록된 스케줄(day_number) 전달
                schedules: Array.isArray(result.data.schedules) ? result.data.schedules : []
            };

            // durationDays 호환 (durationDays가 없을 때만 duration_days로 보완)
            if (selectedPackage && selectedPackage.durationDays == null && selectedPackage.duration_days != null) {
                selectedPackage.durationDays = selectedPackage.duration_days;
            }

            // 인원 옵션(pricingOptions) 적용: traveler type select / 총액 계산이 상품 등록값과 일치하도록
            __applyPackagePricingOptions(selectedPackage);
            __syncTravelerTypeSelectsWithPackage();
            
            // 상품명 표시
            document.getElementById('product_name').value = selectedPackage.packageName || '';
            document.getElementById('package_id').value = selectedPackage.packageId || '';
            
            // 여행 시작일 입력 활성화
            const departureDateInput = document.getElementById('departure_date');
            const departureDateBtn = document.getElementById('departure_date_btn');
            departureDateInput.disabled = false;
            departureDateInput.removeAttribute('readonly');
            if (departureDateBtn) {
                departureDateBtn.disabled = false;
            }
            
            // 날짜별 가용성 확인 및 불러오기
            await loadAvailableDates(packageId);
            
            // 총 금액 계산
            calculateTotalAmount();
            updateOrderSummary();
        } else {
            alert(getText('failedToLoadProduct'));
        }
    } catch (error) {
        console.error('Error loading product detail:', error);
        alert(getText('errorLoadingProduct'));
    }
}

// 날짜별 가용성 불러오기 (여러 월 지원)
async function loadAvailableDates(packageId, year = null, month = null) {
    try {
        const today = new Date();
        const targetYear = year || today.getFullYear();
        const targetMonth = month || today.getMonth() + 1;
        const cacheKey = `${targetYear}-${targetMonth}`;
        
        // 이미 로드된 월이면 캐시에서 반환
        if (availableDatesByMonth[cacheKey]) {
            return availableDatesByMonth[cacheKey];
        }
        
        // product_availability.php API 호출
        const availabilityUrl = `${window.location.origin}/backend/api/product_availability.php?id=${encodeURIComponent(packageId)}&year=${targetYear}&month=${targetMonth}`;
        const response = await fetch(availabilityUrl);
        const responseText = await response.text();
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 200)}`);
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
        }
        
        if (result.success && result.data && result.data.availability) {
            const dates = result.data.availability.filter(date => 
                date.status === 'available' && date.remainingSeats > 0
            );
            
            // 캐시에 저장
            availableDatesByMonth[cacheKey] = dates;
            
            // 현재 월이면 전역 변수에도 저장
            if (targetYear === calendarCurrentYear && targetMonth === calendarCurrentMonth) {
                availableDates = dates;
            }
            
            console.log(`Available dates loaded for ${targetYear}-${targetMonth}:`, dates);
            return dates;
        } else {
            console.warn('Failed to load available dates:', result);
            availableDatesByMonth[cacheKey] = [];
            return [];
        }
    } catch (error) {
        console.error('Error loading available dates:', error);
        const cacheKey = `${year || calendarCurrentYear}-${month || calendarCurrentMonth}`;
        availableDatesByMonth[cacheKey] = [];
        return [];
    }
}

// 첫 번째 가용 날짜의 월 찾기
async function findFirstAvailableMonth(packageId) {
    const today = new Date();

    // 향후 12개월 탐색하여 첫 번째 available 날짜의 월 반환
    for (let i = 0; i < 12; i++) {
        const targetDate = new Date(today.getFullYear(), today.getMonth() + i, 1);
        const year = targetDate.getFullYear();
        const month = targetDate.getMonth() + 1;

        const dates = await loadAvailableDates(packageId, year, month);
        if (dates && dates.length > 0) {
            // 첫 번째 가용 날짜의 월 반환
            const firstDate = new Date(dates[0].availableDate);
            return { year: firstDate.getFullYear(), month: firstDate.getMonth() + 1 };
        }
    }

    // 12개월 내 가용 날짜 없으면 현재 월 반환
    return { year: today.getFullYear(), month: today.getMonth() + 1 };
}

// 날짜 선택 모달 열기
async function openDatePickerModal() {
    if (!selectedPackage) {
        alert(getText('pleaseSelectProduct') || '상품을 먼저 선택해주세요.');
        return;
    }

    // 뷰 상태 초기화
    currentDateView = 'calendar';
    currentDateSort = 'date';

    // 뷰 탭 초기화
    document.querySelectorAll('#date-picker-modal .view-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.view === 'calendar');
    });

    // 컨테이너 초기화
    document.getElementById('calendar-container').style.display = 'block';
    const tableContainer = document.getElementById('dates-table-container');
    if (tableContainer) tableContainer.style.display = 'none';

    // 첫 번째 가용 날짜의 월 찾기
    const firstAvailable = await findFirstAvailableMonth(selectedPackage.packageId);
    calendarCurrentMonth = firstAvailable.month;
    calendarCurrentYear = firstAvailable.year;

    // 캘린더 렌더링
    renderCalendar();

    // 모달 열기
    openModal('date-picker-modal');
}

// 뷰 전환 함수
window.switchDateView = function(view) {
    currentDateView = view;

    // 탭 활성화 상태 변경
    document.querySelectorAll('#date-picker-modal .view-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.view === view);
    });

    // 뷰 전환
    const calendarContainer = document.getElementById('calendar-container');
    const tableContainer = document.getElementById('dates-table-container');

    if (view === 'calendar') {
        calendarContainer.style.display = 'block';
        tableContainer.style.display = 'none';
    } else {
        calendarContainer.style.display = 'none';
        tableContainer.style.display = 'block';
        renderDatesTable();
    }
};

// 테이블 뷰 렌더링
async function renderDatesTable() {
    const tbody = document.getElementById('dates-table-body');
    if (!tbody || !selectedPackage) return;

    tbody.innerHTML = '<tr><td colspan="4" class="is-center">Loading...</td></tr>';

    // 모든 가용 날짜 수집 (캐시된 모든 월)
    allAvailableDates = [];
    for (const [key, dates] of Object.entries(availableDatesByMonth)) {
        allAvailableDates.push(...dates);
    }

    // 추가로 다음 6개월 로드
    const today = new Date();
    for (let i = 0; i < 6; i++) {
        const targetDate = new Date(today.getFullYear(), today.getMonth() + i, 1);
        const year = targetDate.getFullYear();
        const month = targetDate.getMonth() + 1;
        const cacheKey = `${year}-${month}`;

        if (!availableDatesByMonth[cacheKey]) {
            const dates = await loadAvailableDates(selectedPackage.packageId, year, month);
            if (dates.length > 0) {
                allAvailableDates.push(...dates.filter(d =>
                    !allAvailableDates.some(existing => existing.availableDate === d.availableDate)
                ));
            }
        }
    }

    // 과거 날짜 필터링
    const todayStr = today.toISOString().split('T')[0];
    allAvailableDates = allAvailableDates.filter(d => d.availableDate >= todayStr && d.remainingSeats > 0);

    // 정렬 적용
    sortDatesArray();

    // 렌더링
    renderDatesTableRows();
}

// 날짜 배열 정렬
function sortDatesArray() {
    if (currentDateSort === 'date') {
        allAvailableDates.sort((a, b) => a.availableDate.localeCompare(b.availableDate));
    } else if (currentDateSort === 'price') {
        allAvailableDates.sort((a, b) => ((a.b2bPrice ?? a.price) || 0) - ((b.b2bPrice ?? b.price) || 0));
    }
}

// 테이블 정렬
window.sortDatesTable = function(sortBy) {
    currentDateSort = sortBy;

    // 버튼 활성화 상태
    document.querySelectorAll('#date-picker-modal .sort-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.sort === sortBy);
        // 버튼 텍스트 업데이트
        if (btn.dataset.sort === 'date') {
            btn.textContent = 'Date ↑';
        } else if (btn.dataset.sort === 'price') {
            btn.textContent = 'Price ↑';
        }
    });

    sortDatesArray();
    renderDatesTableRows();
};

// 테이블 행 렌더링
function renderDatesTableRows() {
    const tbody = document.getElementById('dates-table-body');
    if (!tbody) return;

    if (allAvailableDates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="is-center">No available dates</td></tr>';
        return;
    }

    let html = '';
    allAvailableDates.forEach(date => {
        const dateObj = new Date(date.availableDate);
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        const formattedDate = `${yyyy}-${mm}-${dd}`;
        const price = formatCurrency(date.b2bPrice ?? date.price ?? 0);
        const isOnSale = date.isOnSale || date.discountAmount > 0;
        const discountHtml = isOnSale && date.discountAmount > 0
            ? `<span class="discount-badge">-₱${formatCurrency(date.discountAmount)}</span>`
            : '-';
        const selected = selectedDateInCalendar === date.availableDate ? 'selected' : '';
        const saleClass = isOnSale ? 'on-sale' : '';

        html += `
            <tr class="${selected} ${saleClass}"
                data-date="${date.availableDate}"
                data-availability-id="${date.availabilityId}"
                onclick="selectDateInTable('${date.availableDate}', ${date.availabilityId})">
                <td>${formattedDate}</td>
                <td>₱${price}</td>
                <td>${discountHtml}</td>
                <td>${date.remainingSeats} seats</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// 테이블에서 날짜 선택
window.selectDateInTable = function(dateStr, availabilityId) {
    selectedDateInCalendar = dateStr;

    // 테이블 행 선택 상태 업데이트
    document.querySelectorAll('#dates-table-body tr').forEach(tr => {
        tr.classList.remove('selected');
    });
    const selectedRow = document.querySelector(`#dates-table-body tr[data-date="${dateStr}"]`);
    if (selectedRow) {
        selectedRow.classList.add('selected');
    }

    // 선택된 날짜 정보 저장
    selectedDateInfo = allAvailableDates.find(d => d.availableDate === dateStr);

    // 캘린더의 월 업데이트 (뷰 전환 시 동기화)
    const dateObj = new Date(dateStr);
    calendarCurrentYear = dateObj.getFullYear();
    calendarCurrentMonth = dateObj.getMonth() + 1;

    // 정보 표시
    updateCalendarInfo();
};

// 캘린더 렌더링
async function renderCalendar() {
    const calendarBody = document.getElementById('calendar-body');
    const monthDisplay = document.getElementById('calendar-month-display');
    
    if (!calendarBody || !selectedPackage) return;
    
    // 월 표시 업데이트
    const monthNames = getCurrentLang() === 'eng' 
        ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
        : ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'];
    
    if (monthDisplay) {
        monthDisplay.textContent = `${monthNames[calendarCurrentMonth - 1]} ${calendarCurrentYear}`;
    }
    
    // 해당 월의 가용 가능한 날짜 로드
    await loadAvailableDates(selectedPackage.packageId, calendarCurrentYear, calendarCurrentMonth);
    const monthDates = availableDatesByMonth[`${calendarCurrentYear}-${calendarCurrentMonth}`] || [];
    
    // 가용 가능한 날짜 맵 생성
    const availabilityMap = {};
    monthDates.forEach(date => {
        const dateObj = new Date(date.availableDate);
        const day = dateObj.getDate();
        availabilityMap[day] = date;
    });
    
    // 캘린더 생성
    const firstDay = new Date(calendarCurrentYear, calendarCurrentMonth - 1, 1).getDay();
    const daysInMonth = new Date(calendarCurrentYear, calendarCurrentMonth, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    let calendarHtml = '';
    let date = 1;
    
    for (let week = 0; week < 6; week++) {
        calendarHtml += '<tr>';
        
        for (let day = 0; day < 7; day++) {
            if (week === 0 && day < firstDay) {
                calendarHtml += '<td class="inactive"></td>';
            } else if (date > daysInMonth) {
                calendarHtml += '<td class="inactive"></td>';
            } else {
                const currentDate = new Date(calendarCurrentYear, calendarCurrentMonth - 1, date);
                currentDate.setHours(0, 0, 0, 0);
                //const dateStr = currentDate.toISOString().split('T')[0];
                // SMT 수정 시작
                const dateStr = `${calendarCurrentYear}-${String(calendarCurrentMonth).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                // SMT 수정 종료
                const isPast = currentDate < today;
                const availabilityInfo = availabilityMap[date];
                const isSelected = selectedDateInCalendar === dateStr;
                
                let cellClass = '';
                let cellContent = date;
                let clickEvent = '';
                
                if (isPast) {
                    cellClass = 'inactive';
                } else if (availabilityInfo && availabilityInfo.remainingSeats > 0) {
                    cellClass = 'available';
                    const price = Math.floor((availabilityInfo.b2bPrice ?? availabilityInfo.price) / 1000);
                    const isOnSale = availabilityInfo.isOnSale || availabilityInfo.discountAmount > 0;
                    const discountAmount = availabilityInfo.discountAmount || 0;

                    let priceHtml = `<p class="text fz12 fw400 lh16">₱${price}K</p>`;
                    if (isOnSale && discountAmount > 0) {
                        cellClass += ' on-sale';
                        const discountK = Math.floor(discountAmount / 1000);
                        priceHtml = `
                            <p class="text fz12 fw600 lh16" style="color: #DC2626;">₱${price}K</p>
                            <span class="sale-badge">-${discountK > 0 ? discountK + 'K' : discountAmount}</span>
                        `;
                    }

                    cellContent = `
                        ${date}
                        ${priceHtml}
                    `;
                    clickEvent = `onclick="selectDateInCalendar('${dateStr}', ${availabilityInfo.availabilityId})"`;
                } else {
                    cellClass = 'inactive';
                }
                
                if (isSelected) {
                    cellClass += ' selected';
                }
                
                if (currentDate.getTime() === today.getTime()) {
                    cellClass += ' today';
                }
                
                calendarHtml += `<td class="${cellClass.trim()}" ${clickEvent} role="gridcell" tabindex="0">${cellContent}</td>`;
                date++;
            }
        }
        
        calendarHtml += '</tr>';
        
        if (date > daysInMonth) break;
    }
    
    calendarBody.innerHTML = calendarHtml;
    
    // 다국어 적용
    if (typeof language_apply === 'function') {
        const currentLang = getCurrentLang();
        language_apply(currentLang);
    }
}

// 캘린더에서 날짜 선택
window.selectDateInCalendar = function(dateStr, availabilityId) {
    selectedDateInCalendar = dateStr;
    
    // 선택된 날짜 하이라이트
    document.querySelectorAll('#calendar-body td').forEach(td => {
        td.classList.remove('selected');
    });
    
    const selectedCell = Array.from(document.querySelectorAll('#calendar-body td')).find(td => {
        return td.getAttribute('onclick') && td.getAttribute('onclick').includes(dateStr);
    });
    
    if (selectedCell) {
        selectedCell.classList.add('selected');
    }
    
    // 선택된 날짜 정보 저장
    const monthDates = availableDatesByMonth[`${calendarCurrentYear}-${calendarCurrentMonth}`] || [];
    selectedDateInfo = monthDates.find(date => date.availableDate === dateStr);
    
    // 날짜 정보 표시
    updateCalendarInfo();
};

// 캘린더 정보 업데이트
function updateCalendarInfo() {
    const calendarInfo = document.getElementById('calendar-info');
    if (!calendarInfo || !selectedDateInfo) {
        if (calendarInfo) calendarInfo.innerHTML = '';
        return;
    }

    const date = new Date(selectedDateInfo.availableDate);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formattedDate = `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    const price = formatCurrency(selectedDateInfo.b2bPrice ?? selectedDateInfo.price);
    const remainingSeats = selectedDateInfo.remainingSeats;

    // Sale 할인 정보 표시
    const isOnSale = selectedDateInfo.isOnSale || selectedDateInfo.discountAmount > 0;
    const discountAmount = selectedDateInfo.discountAmount || 0;
    const originalPrice = selectedDateInfo.originalB2bPrice || selectedDateInfo.originalPrice;
    const saleName = selectedDateInfo.saleName || '';

    let priceHtml = `<strong>Price:</strong> ₱${price}`;
    if (isOnSale && discountAmount > 0 && originalPrice) {
        priceHtml = `
            <strong>Price:</strong>
            <span style="text-decoration: line-through; color: #9CA3AF;">₱${formatCurrency(originalPrice)}</span>
            <span style="color: #DC2626; font-weight: 600;"> ₱${price}</span>
            <span style="background: #DC2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-left: 6px;">-₱${formatCurrency(discountAmount)}</span>
        `;
    }

    let saleNameHtml = '';
    if (isOnSale && saleName) {
        saleNameHtml = `
            <div class="calendar-info-item" style="color: #DC2626;">
                <strong>Sale:</strong> ${escapeHtml(saleName)}
            </div>
        `;
    }

    calendarInfo.innerHTML = `
        <div class="calendar-info-item">
            <strong>Selected Date:</strong> ${formattedDate}
        </div>
        <div class="calendar-info-item">
            ${priceHtml}
        </div>
        ${saleNameHtml}
        <div class="calendar-info-item">
            <strong>Remaining Seats:</strong> ${remainingSeats}
        </div>
    `;
}

// 날짜 선택 확인
async function confirmDateSelection() {
    if (!selectedDateInCalendar) {
        alert(getText('pleaseSelectDate') || '날짜를 선택해주세요.');
        return;
    }
    
    // selectedDateInfo가 없으면 가용 날짜 목록에서 찾기
    if (!selectedDateInfo) {
        const monthDates = availableDatesByMonth[`${calendarCurrentYear}-${calendarCurrentMonth}`] || [];
        selectedDateInfo = monthDates.find(date => date.availableDate === selectedDateInCalendar);
        
        // 그래도 없으면 다른 월의 가용 날짜에서 찾기
        if (!selectedDateInfo) {
            for (const [key, dates] of Object.entries(availableDatesByMonth)) {
                const found = dates.find(date => date.availableDate === selectedDateInCalendar);
                if (found) {
                    selectedDateInfo = found;
                    break;
                }
            }
        }
    }

    // 날짜별 가격 적용 (childPrice, infantPrice, singlePrice)
    __applyDateSpecificPricing(selectedDateInfo);

    // 날짜 입력 필드 업데이트
    const departureDateInput = document.getElementById('departure_date');
    const departureDateValueInput = document.getElementById('departure_date_value');
    const date = new Date(selectedDateInCalendar);
    
    const formattedDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const displayDate = getCurrentLang() === 'eng' 
        ? date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
        : `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`;
    
    if (departureDateInput) {
        departureDateInput.value = displayDate;
    }
    if (departureDateValueInput) {
        departureDateValueInput.value = formattedDate;
    }
    
    // 여행 종료일 계산
    updateReturnDate();
    
    // 선택한 날짜의 상세 정보 불러오기
    await loadDateDetailInfo(selectedPackage.packageId, formattedDate);
    
    // 모달 닫기
    closeModal('date-picker-modal');
    
    // 총 금액 계산
    calculateTotalAmount();
    
    // 예약 이력 업데이트
    addReservationHistory('출발일 선택: ' + formattedDate);
}

// 선택한 날짜의 상세 정보 불러오기 (여행 기간, 미팅 시간, 미팅 장소)
async function loadDateDetailInfo(packageId, date) {
    try {
        // 패키지 상세 정보에서 미팅 정보 가져오기
        const detailUrl = `${window.location.origin}/backend/api/packages.php?id=${encodeURIComponent(packageId)}`;
        const response = await fetch(detailUrl, { credentials: 'same-origin' });
        const responseText = await response.text();
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 200)}`);
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
        }
        
        if (result.success && result.data) {
            const pkg = result.data;

            // 선택 패키지의 최신 정보 반영 후 UI 갱신
            if (selectedPackage) {
                // meeting 정보: 환경별 컬럼명 편차 흡수
                const mt = (pkg.meeting_time != null) ? String(pkg.meeting_time).trim() : '';
                if (mt) selectedPackage.meeting_time = pkg.meeting_time;
                const mt2 = (pkg.meetingTime != null) ? String(pkg.meetingTime).trim() : '';
                if (mt2) selectedPackage.meetingTime = pkg.meetingTime;

                const ml = (pkg.meeting_location != null) ? String(pkg.meeting_location).trim() : '';
                if (ml) selectedPackage.meeting_location = pkg.meeting_location;
                const ml2 = (pkg.meetingLocation != null) ? String(pkg.meetingLocation).trim() : '';
                if (ml2) selectedPackage.meetingLocation = pkg.meetingLocation;
                const mp = (pkg.meetingPoint != null) ? String(pkg.meetingPoint).trim() : '';
                if (mp) selectedPackage.meetingPoint = pkg.meetingPoint;

                const ma = (pkg.meeting_address != null) ? String(pkg.meeting_address).trim() : '';
                if (ma) selectedPackage.meeting_address = pkg.meeting_address;
                const ma2 = (pkg.meetingAddress != null) ? String(pkg.meetingAddress).trim() : '';
                if (ma2) selectedPackage.meetingAddress = pkg.meetingAddress;

                // duration: durationDays 우선 (duration_days는 구 컬럼)
                if (pkg.durationDays != null) selectedPackage.durationDays = pkg.durationDays;
                if (pkg.duration_days != null) selectedPackage.duration_days = pkg.duration_days;
                if (selectedPackage.durationDays == null && selectedPackage.duration_days != null) {
                    selectedPackage.durationDays = selectedPackage.duration_days;
                }
            }
            updateReturnDate();
            
            // 항공편 정보 확인 및 표시
            if (selectedDateInfo && selectedDateInfo.flightId) {
                await loadFlightInfo(selectedDateInfo.flightId);
            } else {
                // flightId가 없는 환경 fallback: package_flights 기반으로 노출
                await fetchPackageFlightsFallback(packageId, date, (getPackageDurationDays(selectedPackage) || pkg.durationDays || pkg.duration_days || pkg.duration || 5));
            }
        }
    } catch (error) {
        console.error('Error loading date detail info:', error);
    }
}

// 항공편 정보 불러오기
async function loadFlightInfo(flightId) {
    try {
        // agent-api.php의 getFlightInfo 사용
        await fetchFlightDetails(flightId);
    } catch (error) {
        console.error('Error loading flight info:', error);
    }
}

// 항공편 상세 정보 조회
async function fetchFlightDetails(flightId) {
    try {
        // agent-api.php에 항공편 조회 API 추가 필요
        // 임시로 product_availability.php의 응답에서 flight 정보 확인
        const response = await fetch(`../backend/api/agent-api.php?action=getFlightInfo&flightId=${flightId}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            await renderFlightInfoSection(result.data);
        } else {
            console.warn('Flight info not available from API, using date info');
            // API가 없으면 날짜 정보에서 추출 가능한 정보만 사용
            if (selectedDateInfo) {
                await renderFlightInfoSectionFromDateInfo(selectedDateInfo);
            }
        }
    } catch (error) {
        console.error('Error fetching flight details:', error);
        // API 호출 실패 시 날짜 정보에서 추출 가능한 정보만 사용
        if (selectedDateInfo) {
            await renderFlightInfoSectionFromDateInfo(selectedDateInfo);
        }
    }
}

// 날짜 정보에서 항공편 정보 섹션 렌더링 (임시)
async function renderFlightInfoSectionFromDateInfo(dateInfo) {
    // flightId가 없거나 flight API 실패 시 package_flights 기반 fallback 시도
    console.log('Flight info from date info:', dateInfo);
    try {
        const pid = selectedPackage?.packageId || selectedPackage?.id || document.getElementById('package_id')?.value;
        const dep = selectedDateInCalendar || document.getElementById('departure_date_value')?.value || '';
        if (pid && dep) {
            await fetchPackageFlightsFallback(pid, dep, (selectedPackage?.duration_days || selectedPackage?.durationDays || 5));
        }
    } catch (_) {}
}

// 항공편 정보 섹션 렌더링
async function renderFlightInfoSection(flight) {
    const flightSection = document.getElementById('flight-info-section');
    if (!flightSection) return;

    // 출국편 (Departure flight) 정보 채우기
    const outFlightNo = document.getElementById('out_flight_no');
    const outDepartDt = document.getElementById('out_depart_dt');
    const outArriveDt = document.getElementById('out_arrive_dt');
    const outDepartAirport = document.getElementById('out_depart_airport');
    const outArriveAirport = document.getElementById('out_arrive_airport');

    const flightCode = [flight.flightName, flight.flightCode].filter(Boolean).join(' ').trim();
    const outDepartDateTime = [formatDate(flight.flightDepartureDate), flight.flightDepartureTime].filter(Boolean).join(' ').trim();
    const outArriveDateTime = [formatDate(flight.flightArrivalDate), flight.flightArrivalTime].filter(Boolean).join(' ').trim();

    if (outFlightNo) outFlightNo.value = flightCode || '';
    if (outDepartDt) outDepartDt.value = outDepartDateTime || '';
    if (outArriveDt) outArriveDt.value = outArriveDateTime || '';
    if (outDepartAirport) outDepartAirport.value = flight.origin || '';
    if (outArriveAirport) outArriveAirport.value = flight.destination || '';

    // 항공사 옵션 로드 (flightName이 항공사명)
    let airlineName = flight.flightName || flight.airlineName || '';
    // airline_name이 비어있으면 flight code에서 추출 시도
    if (!airlineName && flightCode) {
        airlineName = getAirlineNameFromFlightNumber(flightCode);
    }
    if (airlineName) {
        await loadAirlineOptions(airlineName);
    }

    // 귀국편 (Return trip) 정보 채우기
    const inFlightNo = document.getElementById('in_flight_no');
    const inDepartDt = document.getElementById('in_depart_dt');
    const inArriveDt = document.getElementById('in_arrive_dt');
    const inDepartAirport = document.getElementById('in_depart_airport');
    const inArriveAirport = document.getElementById('in_arrive_airport');

    const returnFlightCode = [flight.returnFlightName, flight.returnFlightCode].filter(Boolean).join(' ').trim();
    const inDepartDateTime = [formatDate(flight.returnDepartureDate), flight.returnDepartureTime].filter(Boolean).join(' ').trim();
    const inArriveDateTime = [formatDate(flight.returnArrivalDate), flight.returnArrivalTime].filter(Boolean).join(' ').trim();

    if (inFlightNo) inFlightNo.value = returnFlightCode || '';
    if (inDepartDt) inDepartDt.value = inDepartDateTime || '';
    if (inArriveDt) inArriveDt.value = inArriveDateTime || '';
    if (inDepartAirport) inDepartAirport.value = flight.returnOrigin || '';
    if (inArriveAirport) inArriveAirport.value = flight.returnDestination || '';

    // 섹션 표시
    flightSection.classList.remove('hidden');

    // 다국어 적용
    if (typeof language_apply === 'function') {
        const currentLang = getCurrentLang();
        language_apply(currentLang);
    }
}

async function fetchPackageFlightsFallback(packageId, departureDate, durationDays) {
    try {
        const pid = Number(packageId) || 0;
        const dep = String(departureDate || '').slice(0, 10);
        if (!pid || !dep) return;
        const dur = Number(durationDays) || 5;
        const params = new URLSearchParams({
            action: 'getPackageFlights',
            packageId: String(pid),
            departureDate: dep,
            durationDays: String(dur)
        });
        const res = await fetch(`../backend/api/agent-api.php?${params.toString()}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (json && json.success && json.data && (json.data.outboundFlight || json.data.inboundFlight)) {
            await renderPackageFlightsSection(json.data);
        } else {
            // 항공편 없음 → 섹션 제거
            removeFlightInfoSection();
        }
    } catch (e) {
        // ignore
    }
}

async function renderPackageFlightsSection(data) {
    const flightSection = document.getElementById('flight-info-section');
    if (!flightSection) return;

    const out = data?.outboundFlight || null;
    const inn = data?.inboundFlight || null;

    // Departure flight (출국편) 정보 채우기
    if (out) {
        const outFlightNo = document.getElementById('out_flight_no');
        const outDepartDt = document.getElementById('out_depart_dt');
        const outArriveDt = document.getElementById('out_arrive_dt');
        const outDepartAirport = document.getElementById('out_depart_airport');
        const outArriveAirport = document.getElementById('out_arrive_airport');

        if (outFlightNo) outFlightNo.value = out.flightNumber || '';
        if (outDepartDt) outDepartDt.value = out.departureDateTime || '';
        if (outArriveDt) outArriveDt.value = out.arrivalDateTime || '';
        if (outDepartAirport) outDepartAirport.value = out.departureAirport || '';
        if (outArriveAirport) outArriveAirport.value = out.arrivalAirport || '';

        // 항공사 옵션 로드 (출발편 기준)
        let airlineName = out.airlineName || out.airline_name || '';
        // airline_name이 비어있으면 flight number에서 추출 시도
        if (!airlineName && out.flightNumber) {
            airlineName = getAirlineNameFromFlightNumber(out.flightNumber);
        }
        if (airlineName) {
            await loadAirlineOptions(airlineName);
        }
    }

    // Return trip (귀국편) 정보 채우기
    if (inn) {
        const inFlightNo = document.getElementById('in_flight_no');
        const inDepartDt = document.getElementById('in_depart_dt');
        const inArriveDt = document.getElementById('in_arrive_dt');
        const inDepartAirport = document.getElementById('in_depart_airport');
        const inArriveAirport = document.getElementById('in_arrive_airport');

        if (inFlightNo) inFlightNo.value = inn.flightNumber || '';
        if (inDepartDt) inDepartDt.value = inn.departureDateTime || '';
        if (inArriveDt) inArriveDt.value = inn.arrivalDateTime || '';
        if (inDepartAirport) inDepartAirport.value = inn.departureAirport || '';
        if (inArriveAirport) inArriveAirport.value = inn.arrivalAirport || '';
    }

    // 섹션 표시
    flightSection.classList.remove('hidden');

    try {
        if (typeof language_apply === 'function') language_apply(getCurrentLang());
    } catch (_) {}
}

// 항공편 정보 섹션 숨기기 및 값 초기화
function removeFlightInfoSection() {
    const flightSection = document.getElementById('flight-info-section');
    if (flightSection) {
        flightSection.classList.add('hidden');

        // 값 초기화
        const fields = ['out_flight_no', 'out_depart_dt', 'out_arrive_dt', 'out_depart_airport', 'out_arrive_airport',
                        'in_flight_no', 'in_depart_dt', 'in_arrive_dt', 'in_depart_airport', 'in_arrive_airport'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    // 항공 옵션도 초기화
    currentAirlineName = '';
    airlineOptionCategories = [];
}

// Flight number에서 항공사명 추출 (예: "5J188" → "Cebu Pacific")
function getAirlineNameFromFlightNumber(flightNumber) {
    if (!flightNumber) return '';

    // 항공사 코드 → 항공사명 매핑
    const airlineCodeMap = {
        '5J': 'Cebu Pacific',
        'PR': 'Philippine Airlines',
        'Z2': 'AirAsia Philippines',
        'KE': 'Korean Air',
        'OZ': 'Asiana Airlines',
        'SQ': 'Singapore Airlines',
        'CX': 'Cathay Pacific',
        'TW': 'T\'way Air',
        '7C': 'Jeju Air',
        'LJ': 'Jin Air',
        'BX': 'Air Busan'
    };

    // flight number에서 항공사 코드 추출 (처음 2글자)
    const flightStr = String(flightNumber).toUpperCase().trim();
    const airlineCode = flightStr.substring(0, 2);

    return airlineCodeMap[airlineCode] || '';
}

// 항공사별 옵션 로드
async function loadAirlineOptions(airlineName) {
    if (!airlineName) {
        currentAirlineName = '';
        airlineOptionCategories = [];
        return;
    }

    try {
        const response = await fetch(`../backend/api/agent-api.php?action=getAirlineOptionsByName&airlineName=${encodeURIComponent(airlineName)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success) {
            currentAirlineName = airlineName;
            airlineOptionCategories = result.data?.categories || [];
            console.log('Airline options loaded:', airlineOptionCategories);
        } else {
            currentAirlineName = '';
            airlineOptionCategories = [];
        }
    } catch (error) {
        console.error('Error loading airline options:', error);
        currentAirlineName = '';
        airlineOptionCategories = [];
    }
}

// 여행자 카드에 항공 옵션 섹션 HTML 생성
function renderFlightOptionsForTraveler(travelerIndex) {
    if (!airlineOptionCategories || airlineOptionCategories.length === 0) {
        return '';
    }

    const traveler = travelerModalData[travelerIndex];
    const selectedOptionIds = (traveler?.flightOptions || []).map(id => Number(id));

    let html = `
        <div class="flight-options-section" data-traveler-index="${travelerIndex}">
            <div class="flight-options-header">
                <span class="flight-options-title">Flight Options</span>
                <span class="flight-options-airline">${escapeHtml(currentAirlineName)}</span>
            </div>
            <div class="flight-options-body">
    `;

    airlineOptionCategories.forEach(cat => {
        html += `
            <div class="flight-option-category">
                <span class="category-label">${escapeHtml(cat.category_name_en || cat.category_name)}</span>
                <div class="category-options">
        `;

        (cat.options || []).forEach(opt => {
            const optId = Number(opt.option_id);
            const isChecked = selectedOptionIds.includes(optId);
            const priceText = opt.price > 0 ? `+PHP ${formatNumber(opt.price)}` : 'Free';
            html += `
                <label class="option-checkbox">
                    <input type="checkbox"
                           data-option-id="${optId}"
                           data-option-price="${opt.price}"
                           data-category-id="${cat.category_id}"
                           ${isChecked ? 'checked' : ''}
                           onchange="updateTravelerFlightOption(${travelerIndex}, ${optId}, ${opt.price}, this.checked)">
                    <span class="option-name">${escapeHtml(opt.option_name_en || opt.option_name)}</span>
                    <span class="option-price">${priceText}</span>
                </label>
            `;
        });

        html += `
                </div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    return html;
}

// 여행자 항공 옵션 업데이트
window.updateTravelerFlightOption = function(travelerIndex, optionId, price, isChecked) {
    if (!travelerModalData[travelerIndex]) return;

    // 타입 일관성을 위해 숫자로 변환
    const numOptionId = Number(optionId);
    const numPrice = Number(price) || 0;

    if (!travelerModalData[travelerIndex].flightOptions) {
        travelerModalData[travelerIndex].flightOptions = [];
    }
    if (!travelerModalData[travelerIndex].flightOptionPrices) {
        travelerModalData[travelerIndex].flightOptionPrices = {};
    }

    const options = travelerModalData[travelerIndex].flightOptions;
    const prices = travelerModalData[travelerIndex].flightOptionPrices;

    if (isChecked) {
        if (!options.includes(numOptionId)) {
            options.push(numOptionId);
            prices[numOptionId] = numPrice;
        }
    } else {
        const idx = options.indexOf(numOptionId);
        if (idx > -1) {
            options.splice(idx, 1);
            delete prices[numOptionId];
        }
    }

    // 총액 재계산
    calculateTotalAmount();
};

// 숫자 포맷팅 (천단위 콤마)
function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num || 0);
}

// 고객 검색 모달 열기
function openCustomerSearchModal() {
    selectedCustomerInModal = null;
    document.getElementById('customer-search-input').value = '';
    searchCustomers(); // 초기 로드
    openModal('customer-search-modal');
}

// 고객 검색
let currentCustomerPage = 1;
const customerLimit = 20;

async function searchCustomers(page = 1) {
    const searchInput = document.getElementById('customer-search-input');
    const searchTerm = searchInput.value.trim();
    const resultsContainer = document.getElementById('customer-search-results');
    const countEl = document.getElementById('customer-search-count');
    
    currentCustomerPage = page;
    
    try {
        resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('searching')}</td></tr>`;
        if (countEl) countEl.textContent = '0';
        
        const params = new URLSearchParams({
            action: 'getCustomers',
            page: page,
            limit: customerLimit
        });
        
        if (searchTerm) {
            params.append('search', searchTerm);
        }
        
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, { credentials: 'same-origin' });
        const result = await response.json();
        
        if (result.success && result.data && result.data.customers && result.data.customers.length > 0) {
            const total = Number(result?.data?.pagination?.total ?? result?.data?.total ?? result.data.customers.length) || 0;
            if (countEl) countEl.textContent = String(total);
            let html = '';
            result.data.customers.forEach(customer => {
                const fullName = `${customer.fName || ''} ${customer.lName || ''}`.trim();
                const name = customer.customerName || fullName || '';
                const email = customer.emailAddress || customer.email || '-';
                const phone = customer.contactNo || customer.phone || '-';
                const createdAt = customer.createdAt ? formatDate(String(customer.createdAt)) + ' ' + (String(customer.createdAt).split(' ')[1] || '').slice(0,5) : '-';
                html += `
                    <tr onclick="selectCustomerInModal(${customer.accountId})">
                        <td class="is-center">
                            <input type="radio" name="customer_select" value="${customer.accountId}">
                        </td>
                        <td>${escapeHtml(name)}</td>
                        <td>${escapeHtml(email)}</td>
                        <td>${escapeHtml(phone)}</td>
                        <td class="is-center">${escapeHtml(createdAt)}</td>
                    </tr>
                `;
            });
            resultsContainer.innerHTML = html;
            
            // 페이지네이션 렌더링
            renderCustomerPagination(result.data.pagination);
        } else {
            if (countEl) countEl.textContent = '0';
            resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('noResults')}</td></tr>`;
            document.getElementById('customer-pagination').innerHTML = '';
        }
    } catch (error) {
        console.error('Error searching customers:', error);
        if (countEl) countEl.textContent = '0';
        resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('errorOccurred')}</td></tr>`;
    }
}

// 페이지네이션 렌더링
function renderCustomerPagination(pagination) {
    const container = document.getElementById('customer-pagination');
    if (!pagination || pagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="contents">';
    
    // 첫 페이지
    html += `<button type="button" class="first" ${currentCustomerPage === 1 ? 'aria-disabled="true"' : ''} onclick="searchCustomers(1)"><img src="../image/first.svg" alt=""></button>`;
    
    // 이전 페이지
    html += `<button type="button" class="prev" ${currentCustomerPage === 1 ? 'aria-disabled="true"' : ''} onclick="searchCustomers(${currentCustomerPage - 1})"><img src="../image/prev.svg" alt=""></button>`;
    
    // 페이지 번호
    html += '<div class="page" role="list">';
    const startPage = Math.max(1, currentCustomerPage - 2);
    const endPage = Math.min(pagination.totalPages, currentCustomerPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button type="button" class="p ${i === currentCustomerPage ? 'show' : ''}" role="listitem" onclick="searchCustomers(${i})">${i}</button>`;
    }
    html += '</div>';
    
    // 다음 페이지
    html += `<button type="button" class="next" ${currentCustomerPage === pagination.totalPages ? 'aria-disabled="true"' : ''} onclick="searchCustomers(${currentCustomerPage + 1})"><img src="../image/next.svg" alt=""></button>`;
    
    // 마지막 페이지
    html += `<button type="button" class="last" ${currentCustomerPage === pagination.totalPages ? 'aria-disabled="true"' : ''} onclick="searchCustomers(${pagination.totalPages})"><img src="../image/last.svg" alt=""></button>`;
    
    html += '</div>';
    container.innerHTML = html;
}

// 모달에서 고객 선택
window.selectCustomerInModal = function(accountId) {
    // 라디오 버튼 업데이트
    document.querySelectorAll('input[name="customer_select"]').forEach(radio => {
        radio.checked = (radio.value == accountId);
    });
    
    selectedCustomerInModal = accountId;
};

// 고객 선택 확인
async function confirmCustomerSelection() {
    const selectedRadio = document.querySelector('input[name="customer_select"]:checked');
    if (!selectedRadio) {
        alert(getText('pleaseSelectCustomer'));
        return;
    }
    
    const accountId = selectedRadio.value;
    
    try {
        const response = await fetch(`../backend/api/agent-api.php?action=getCustomerDetail&accountId=${accountId}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success && result.data && result.data.customer) {
            const customer = result.data.customer;
            selectedCustomer = customer;
            
            // 고객 정보 표시
            document.getElementById('user_name').value = `${customer.fName || ''} ${customer.lName || ''}`.trim();
            document.getElementById('user_email').value = customer.accountEmail || customer.emailAddress || '';
            document.getElementById('user_phone').value = customer.contactNo || '';
            document.getElementById('country_code').value = customer.countryCode || '+63';
            document.getElementById('customer_account_id').value = customer.accountId || '';
            
            // 나이 계산
            const age = customer.dateOfBirth ? calculateAge(customer.dateOfBirth) : null;

            // 나이 기반 type 자동 계산
            let type = 'adult';
            if (age !== null) {
                if (age < 2) type = 'infant';
                else if (age < 7) type = 'child';
            }

            // 성별 기반 title 설정
            const gender = (String(customer.gender || '').toLowerCase() === 'female') ? 'female' : 'male';
            const title = (gender === 'female') ? 'Ms' : 'Mr';

            // 새 여행자 데이터 생성 (travelers 형식)
            const newTraveler = {
                id: 1,
                type: type,
                visaRequired: false,
                visaType: '',
                title: title,
                gender: gender,
                firstName: customer.fName || '',
                lastName: customer.lName || '',
                age: age,
                birthDate: customer.dateOfBirth || '',
                nationality: customer.nationality || '',
                passportNumber: customer.passportNumber || customer.passportNo || '',
                passportIssueDate: customer.passportIssueDate || customer.passportIssuedDate || customer.passport_issue_date || customer.passportIssued || '',
                passportExpiry: customer.passportExpiry || customer.passport_expiry || '',
                passportPhotoFile: null,
                passportImage: customer.profileImage || customer.passportImage || customer.passportPhoto || '',
                visaDocumentFile: null,
                visaDocument: customer.visaDocument || '',
                isMainTraveler: true,
                fromContactPerson: true,
                contactEmail: customer.accountEmail || customer.emailAddress || '',
                contactPhone: customer.contactNo || '',
                contactAccountId: customer.accountId || ''
            };

            // travelers 배열에 추가 또는 첫 번째 여행자 교체
            if (travelers.length === 0) {
                travelers.push(newTraveler);
            } else {
                // 첫 번째 여행자 정보 업데이트
                travelers[0] = { ...travelers[0], ...newTraveler };
            }

            // 여행자 요약 UI 업데이트
            updateTravelerSummary();
            
            closeModal('customer-search-modal');
            
            // 예약 이력 업데이트
            const customerName = `${customer.fName || ''} ${customer.lName || ''}`.trim();
            addReservationHistory('고객 선택: ' + (customerName || '고객'));
        } else {
            alert(getText('failedToLoadCustomer'));
        }
    } catch (error) {
        console.error('Error loading customer detail:', error);
        alert(getText('errorLoadingCustomer'));
    }
}

// 여행 고객 검색 모달 열기
function openTravelCustomerSearchModal() {
    document.getElementById('travel-customer-search-input').value = '';
    // 모달 진입 시 선택 상태를 초기화(칩으로 확인 가능)
    try {
        __travelCustomerSelectedMap = new Map();
    } catch (_) {}
    searchTravelCustomers(1); // 초기 로드
    openModal('travel-customer-search-modal');
}

// 여행 고객 검색
let currentTravelCustomerPage = 1;
const travelCustomerLimit = 20;

// travel customer modal selection state
let __travelCustomerSelectedMap = new Map(); // accountId(string) -> name(string)

function __renderTravelCustomerChips() {
    const wrap = document.getElementById('travel-customer-selected-chips');
    if (!wrap) return;
    const items = Array.from(__travelCustomerSelectedMap.entries());
    if (items.length === 0) {
        wrap.innerHTML = '';
        return;
    }
    wrap.innerHTML = items.map(([id, name]) => {
        return `
            <span class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#F5F5F5;margin-right:6px;margin-bottom:6px;">
                <span>${escapeHtml(name || id)}</span>
                <button type="button" data-id="${escapeHtml(id)}" aria-label="remove" style="background:none;border:none;cursor:pointer;padding:2px 4px;">
                    <img src="../image/button-close2.svg" alt="" style="width:14px;height:14px;">
                </button>
            </span>
        `;
    }).join('');
    wrap.querySelectorAll('button[data-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id') || '';
            if (!id) return;
            __travelCustomerSelectedMap.delete(id);
            // 체크박스도 해제
            document.querySelectorAll('input.travel-customer-checkbox').forEach(cb => {
                if (String(cb.value) === String(id)) cb.checked = false;
            });
            __renderTravelCustomerChips();
        });
    });
}

async function searchTravelCustomers(page = 1) {
    const searchInput = document.getElementById('travel-customer-search-input');
    const searchTerm = searchInput.value.trim();
    const resultsContainer = document.getElementById('travel-customer-search-results');
    const countEl = document.getElementById('travel-customer-search-count');
    
    currentTravelCustomerPage = page;
    
    try {
        resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('searching')}</td></tr>`;
        if (countEl) countEl.textContent = '0';
        
        const params = new URLSearchParams({
            action: 'getCustomers',
            page: page,
            limit: travelCustomerLimit
        });
        
        if (searchTerm) {
            params.append('search', searchTerm);
        }
        
        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, { credentials: 'same-origin' });
        const result = await response.json();
        
        if (result.success && result.data && result.data.customers && result.data.customers.length > 0) {
            const total = Number(result?.data?.pagination?.total ?? result?.data?.total ?? result.data.customers.length) || 0;
            if (countEl) countEl.textContent = String(total);
            let html = '';
            result.data.customers.forEach(customer => {
                const fullName = `${customer.fName || ''} ${customer.lName || ''}`.trim();
                const name = customer.customerName || fullName || '';
                const email = customer.emailAddress || customer.email || '-';
                const phone = customer.contactNo || customer.phone || '-';
                const createdAt = customer.createdAt ? formatDate(String(customer.createdAt)) + ' ' + (String(customer.createdAt).split(' ')[1] || '').slice(0,5) : '-';
                const checked = __travelCustomerSelectedMap.has(String(customer.accountId)) ? 'checked' : '';
                html += `
                    <tr>
                        <td class="is-center">
                            <input type="checkbox" name="travel_customer_select" value="${customer.accountId}" class="travel-customer-checkbox" data-name="${escapeHtml(name)}" ${checked}>
                        </td>
                        <td>${escapeHtml(name)}</td>
                        <td>${escapeHtml(email)}</td>
                        <td>${escapeHtml(phone)}</td>
                        <td class="is-center">${escapeHtml(createdAt)}</td>
                    </tr>
                `;
            });
            resultsContainer.innerHTML = html;

            // 체크박스 변경 → 칩 동기화
            resultsContainer.querySelectorAll('input.travel-customer-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const id = String(cb.value || '');
                    const nm = String(cb.getAttribute('data-name') || '').trim();
                    if (!id) return;
                    if (cb.checked) __travelCustomerSelectedMap.set(id, nm || id);
                    else __travelCustomerSelectedMap.delete(id);
                    __renderTravelCustomerChips();
                });
            });
            __renderTravelCustomerChips();
            
            // 페이지네이션 렌더링
            renderTravelCustomerPagination(result.data.pagination);
        } else {
            if (countEl) countEl.textContent = '0';
            resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('noResults')}</td></tr>`;
            document.getElementById('travel-customer-pagination').innerHTML = '';
        }
    } catch (error) {
        console.error('Error searching travel customers:', error);
        if (countEl) countEl.textContent = '0';
        resultsContainer.innerHTML = `<tr><td colspan="5" class="is-center">${getText('errorLoadingCustomer')}</td></tr>`;
        document.getElementById('travel-customer-pagination').innerHTML = '';
    }
}

// 여행 고객 페이지네이션 렌더링
function renderTravelCustomerPagination(pagination) {
    const paginationContainer = document.getElementById('travel-customer-pagination');
    if (!pagination || !paginationContainer) return;
    
    let html = '<div class="contents">';
    
    // 첫 페이지
    const totalPages = Number(pagination.totalPages || pagination.total_pages || 1) || 1;
    const currentPage = Number(pagination.page || currentTravelCustomerPage || 1) || 1;
    currentTravelCustomerPage = currentPage;
    html += `<button type="button" class="first" ${currentPage === 1 ? 'aria-disabled="true"' : ''} onclick="searchTravelCustomers(1)"><img src="../image/first.svg" alt=""></button>`;
    
    // 이전 페이지
    html += `<button type="button" class="prev" ${currentPage === 1 ? 'aria-disabled="true"' : ''} onclick="searchTravelCustomers(${Math.max(1, currentPage - 1)})"><img src="../image/prev.svg" alt=""></button>`;
    
    // 페이지 번호
    html += '<div class="page" role="list">';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    for (let i = startPage; i <= endPage; i++) {
        html += `<button type="button" class="p ${i === currentPage ? 'show' : ''}" role="listitem" ${i === currentPage ? 'aria-current="page"' : ''} onclick="searchTravelCustomers(${i})">${i}</button>`;
    }
    html += '</div>';
    
    // 다음 페이지
    html += `<button type="button" class="next" ${currentPage === totalPages ? 'aria-disabled="true"' : ''} onclick="searchTravelCustomers(${Math.min(totalPages, currentPage + 1)})"><img src="../image/next.svg" alt=""></button>`;
    
    // 마지막 페이지
    html += `<button type="button" class="last" ${currentPage === totalPages ? 'aria-disabled="true"' : ''} onclick="searchTravelCustomers(${totalPages})"><img src="../image/last.svg" alt=""></button>`;
    
    html += '</div>';
    paginationContainer.innerHTML = html;
}

// 여행 고객 복수 선택 확인
async function confirmTravelCustomerSelection() {
    const selectedCheckboxes = document.querySelectorAll('input[name="travel_customer_select"]:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert(getText('pleaseSelectCustomer'));
        return;
    }
    
    const selectedAccountIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    try {
        // 선택한 모든 고객 정보 가져오기
        const customerPromises = selectedAccountIds.map(accountId =>
            fetch(`../backend/api/agent-api.php?action=getCustomerDetail&accountId=${accountId}`, { credentials: 'same-origin' })
                .then(res => res.json())
        );
        
        const results = await Promise.all(customerPromises);
        
        // 룸 옵션 선택 후 인원 변경(추가) 시 경고
        if (!(await __confirmResetRoomOptionsIfSelected())) return;

        // 각 고객을 여행자로 추가
        for (const result of results) {
            if (result.success && result.data && result.data.customer) {
                const customer = result.data.customer;
                
                // 여행자 추가
                const defaultType = (Array.isArray(__allowedTravelerTypes) && __allowedTravelerTypes.length)
                    ? __allowedTravelerTypes[0]
                    : 'adult';
                const newTraveler = {
                    index: travelers.length,
                    isMainTraveler: travelers.length === 0, // 첫 번째만 대표 여행자
                    type: defaultType,
                    visaRequired: false,
                    visaType: '',
                    title: customer.gender === 'female' ? 'MS' : 'MR',
                    firstName: customer.fName || '',
                    lastName: customer.lName || '',
                    gender: customer.gender || 'male',
                    age: customer.dateOfBirth ? calculateAge(customer.dateOfBirth) : '',
                    birthDate: customer.dateOfBirth || '',
                    contact: customer.contactNo || '',
                    email: customer.accountEmail || customer.emailAddress || '',
                    nationality: customer.nationality || '',
                    passportNumber: customer.passportNumber || '',
                    passportIssueDate:
                        customer.passportIssueDate ||
                        customer.passportIssuedDate ||
                        customer.passport_issue_date ||
                        customer.passportIssued ||
                        '',
                    passportExpiry: customer.passportExpiry || '',
                    passportImage: customer.profileImage || customer.passportImage || customer.passportPhoto || '',
                    visaDocumentFile: null,
                    visaDocument: customer.visaDocument || '',
                    accountId: customer.accountId || customer.id || null,
                    remarks: ''
                };
                
                travelers.push(newTraveler);

                // travelerModalData에도 추가 (모달이 열려있을 때 실시간 반영)
                travelerModalData.push(convertToModalFormat(newTraveler));
            }
        }
        __rerenderTravelersTable();
        updateTravelerSummary();

        // Manage Travelers 모달이 열려있으면 카드 다시 렌더링
        const travelerModal = document.getElementById('traveler-modal');
        if (travelerModal && travelerModal.style.display === 'flex') {
            renderTravelerCards();
        }

        // 모달 닫기
        closeModal('travel-customer-search-modal');
        
    } catch (error) {
        console.error('Error loading travel customer details:', error);
        alert(getText('errorLoadingCustomer'));
    }
}

// 여행자 추가
async function addTraveler() {
    const tbody = document.getElementById('travelers-tbody');
    if (!tbody) return;

    // 룸 옵션 선택 후 인원 변경(추가) 시 경고
    if (!(await __confirmResetRoomOptionsIfSelected())) return;

    const defaultType = (Array.isArray(__allowedTravelerTypes) && __allowedTravelerTypes.length)
        ? __allowedTravelerTypes[0]
        : 'adult';
    
    const newTraveler = {
        index: travelers.length,
        isMainTraveler: travelers.length === 0,
        type: defaultType,
        visaRequired: false,
        visaType: '',
        title: 'MR',
        firstName: '',
        lastName: '',
        gender: 'male',
        age: '',
        birthDate: '',
        contact: '',
        email: '',
        nationality: '',
        passportNumber: '',
        passportIssueDate: '',
        passportExpiry: '',
        visaDocumentFile: null,
        visaDocument: '',
        remarks: ''
    };

    travelers.push(newTraveler);
    __rerenderTravelersTable();
}

// 여행자 행 렌더링
function renderTravelerRow(traveler) {
    const tbody = document.getElementById('travelers-tbody');
    if (!tbody) return;
    
    const row = document.createElement('tr');
    row.id = `traveler-row-${traveler.index}`;
    // traveler.type이 상품 허용 옵션 밖이면 보정
    const allowed = (Array.isArray(__allowedTravelerTypes) && __allowedTravelerTypes.length) ? __allowedTravelerTypes : ['adult'];
    if (!allowed.includes(traveler.type)) traveler.type = allowed[0];
    row.innerHTML = `
        <td class="is-center">${traveler.index + 1}</td>
        <td class="is-center">
            <input type="radio" name="lead_traveler" value="${traveler.index}" ${traveler.isMainTraveler ? 'checked' : ''}>
        </td>
        <td class="show">
            <div class="cell">
                <select class="select traveler-type">
                    ${__renderTravelerTypeOptionsHtml(traveler.type)}
                </select>
            </div>
        </td>
        <td class="show">
            <div class="cell">
                <select class="select traveler-visa">
                    <option value="with_visa" ${!traveler.visaType || traveler.visaType === 'with_visa' ? 'selected' : ''}>${getText('visaNo')}</option>
                    <option value="group" ${traveler.visaType === 'group' ? 'selected' : ''}>${getText('visaGroup')}</option>
                    <option value="individual" ${traveler.visaType === 'individual' ? 'selected' : ''}>${getText('visaIndividual')}</option>
                    <option value="foreign" ${traveler.visaType === 'foreign' ? 'selected' : ''}>${getText('visaForeign')}</option>
                </select>
            </div>
        </td>
        <td class="show">
            <div class="cell">
                <select class="select w-auto traveler-title">
                    <option value="MR" ${traveler.title === 'MR' ? 'selected' : ''}>MR</option>
                    <option value="MS" ${traveler.title === 'MS' ? 'selected' : ''}>MS</option>
                </select>
            </div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="text" class="form-control traveler-firstname" placeholder="${getText('firstName')}" data-lan-eng-placeholder="${getText('firstName')}" value="${escapeHtml(traveler.firstName || '')}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="text" class="form-control traveler-lastname" placeholder="${getText('lastName')}" data-lan-eng-placeholder="${getText('lastName')}" value="${escapeHtml(traveler.lastName || '')}"></div>
        </td>
        <td class="show">
            <div class="cell">
                <select class="select w-auto traveler-gender">
                    <option value="male" ${traveler.gender === 'male' ? 'selected' : ''}>Male</option>
                    <option value="female" ${traveler.gender === 'female' ? 'selected' : ''}>Female</option>
                </select>
            </div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="number" class="form-control traveler-age" placeholder="${getText('age')}" data-lan-eng-placeholder="${getText('age')}" value="${traveler.age || ''}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="date" class="form-control traveler-birthdate" lang="${getCurrentLang() === 'eng' ? 'en' : 'ko'}" min="1900-01-01" max="2099-12-31" value="${traveler.birthDate ? formatDateForInput(traveler.birthDate) : ''}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="text" class="form-control traveler-nationality" placeholder="${getText('nationality')}" data-lan-eng-placeholder="${getText('nationality')}" value="${escapeHtml(traveler.nationality || '')}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="text" class="form-control traveler-passport" placeholder="${getText('passportNumber')}" data-lan-eng-placeholder="${getText('passportNumber')}" value="${escapeHtml(traveler.passportNumber || '')}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="date" class="form-control traveler-passport-issue" lang="${getCurrentLang() === 'eng' ? 'en' : 'ko'}" min="1900-01-01" max="2099-12-31" value="${traveler.passportIssueDate ? formatDateForInput(traveler.passportIssueDate) : ''}"></div>
        </td>
        <td class="is-center">
            <div class="cell"><input type="date" class="form-control traveler-passport-expiry" lang="${getCurrentLang() === 'eng' ? 'en' : 'ko'}" min="1900-01-01" max="2099-12-31" value="${traveler.passportExpiry ? formatDateForInput(traveler.passportExpiry) : ''}"></div>
        </td>
        <td class="is-center">
            <div class="cell">
                <label class="inputFile">
                    <input type="file" class="traveler-passport-photo" accept="image/*">
                    <button type="button" class="btn-upload"><img src="../image/upload3.svg" alt=""> <span data-lan-eng="Image upload">Image upload</span></button>
                </label>
                <!-- 요구사항(id 66): 업로드 후 파일 UI 노출 -->
                <div class="file-info traveler-passport-photo-info" style="display:none; margin-top:8px;">
                    <div class="field-row jw-center" style="justify-content:space-between;">
                        <div class="jw-center jw-gap10">
                            <img src="../image/file.svg" alt="">
                            <span class="traveler-passport-photo-name" style="font-weight:500;"></span>
                        </div>
                        <div class="jw-center jw-gap10">
                            <button type="button" class="jw-button typeF traveler-passport-photo-preview" aria-label="download">
                                <img src="../image/buttun-download.svg" alt="">
                            </button>
                            <button type="button" class="jw-button typeF traveler-passport-photo-remove" aria-label="remove">
                                <img src="../image/button-close2.svg" alt="">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </td>
        <td class="is-center">
            <div class="jw-center"><button type="button" class="jw-button traveler-delete" aria-label="row delete" onclick="deleteTraveler(${traveler.index})"><img src="../image/trash.svg" alt=""></button></div>
        </td>
    `;
    
    tbody.appendChild(row);
    
    // 이벤트 리스너 추가
    attachTravelerEventListeners(row, traveler.index);

    // 기존 여권 사진(프로필 이미지)이 있는 경우 즉시 파일 UI를 노출 (customer search/여행고객 추가 후에도 보이도록)
    // NOTE: __rerenderTravelersTable은 tbody를 통째로 다시 그리므로, render 시점에 반영이 필요함.
    try {
        updateTravelerRow(traveler.index);
    } catch (_) {}
    
    // 다국어 적용 (select 옵션과 placeholder 업데이트)
    if (typeof language_apply === 'function') {
        const currentLang = getCurrentLang();
        language_apply(currentLang);
    }
    
    // jw_select 재적용
    if (typeof jw_select === 'function') {
        setTimeout(() => {
            jw_select();
        }, 100);
    }
}

function addTravelerWithData(data = {}) {
    addTraveler();
    const idx = travelers.length - 1;
    if (idx < 0) return;
    
    travelers[idx] = {
        ...travelers[idx],
        ...data,
        index: idx
    };
    
    updateTravelerRow(idx);
}

// 여행자 행 업데이트
function updateTravelerRow(index) {
    const traveler = travelers[index];
    if (!traveler) return;
    const row = document.getElementById(`traveler-row-${index}`);
    if (!row) {
        renderTravelerRow(traveler);
        return;
    }
    const firstNameInput = row.querySelector('.traveler-firstname');
    const lastNameInput = row.querySelector('.traveler-lastname');
    const genderSelect = row.querySelector('.traveler-gender');
    const ageInput = row.querySelector('.traveler-age');
    const birthDateInput = row.querySelector('.traveler-birthdate');
    const nationalityInput = row.querySelector('.traveler-nationality');
    const passportInput = row.querySelector('.traveler-passport');
    const passportIssueInput = row.querySelector('.traveler-passport-issue');
    const passportExpiryInput = row.querySelector('.traveler-passport-expiry');
    const titleSelect = row.querySelector('.traveler-title');
    const typeSelect = row.querySelector('.traveler-type');
    const visaSelect = row.querySelector('.traveler-visa');
    const mainTravelerRadio = row.querySelector('input[name="lead_traveler"]');
    const passportPhotoInfo = row.querySelector('.traveler-passport-photo-info');
    const passportPhotoName = row.querySelector('.traveler-passport-photo-name');
    const passportPhotoPreviewBtn = row.querySelector('.traveler-passport-photo-preview');
    const passportPhotoRemoveBtn = row.querySelector('.traveler-passport-photo-remove');
    const passportPhotoInput = row.querySelector('.traveler-passport-photo');

    if (firstNameInput) {
        firstNameInput.value = traveler.firstName || '';
        firstNameInput.placeholder = getText('firstName');
        firstNameInput.setAttribute('data-lan-eng-placeholder', getText('firstName'));
    }

    if (lastNameInput) {
        lastNameInput.value = traveler.lastName || '';
        lastNameInput.placeholder = getText('lastName');
        lastNameInput.setAttribute('data-lan-eng-placeholder', getText('lastName'));
    }

    if (genderSelect) {
        const gv = (traveler.gender === 'female') ? 'female' : 'male';
        genderSelect.value = gv;
    }

    if (ageInput) {
        ageInput.value = traveler.age || '';
        ageInput.placeholder = getText('age');
        ageInput.setAttribute('data-lan-eng-placeholder', getText('age'));
    }

    if (birthDateInput) {
        birthDateInput.value = traveler.birthDate ? formatDateForInput(traveler.birthDate) : '';
        birthDateInput.setAttribute('lang', getCurrentLang() === 'eng' ? 'en' : 'ko');
    }

    if (nationalityInput) {
        nationalityInput.value = traveler.nationality || '';
        nationalityInput.placeholder = getText('nationality');
        nationalityInput.setAttribute('data-lan-eng-placeholder', getText('nationality'));
    }

    if (passportInput) {
        passportInput.value = traveler.passportNumber || '';
        passportInput.placeholder = getText('passportNumber');
        passportInput.setAttribute('data-lan-eng-placeholder', getText('passportNumber'));
    }

    if (passportIssueInput) {
        passportIssueInput.value = traveler.passportIssueDate ? formatDateForInput(traveler.passportIssueDate) : '';
        passportIssueInput.setAttribute('lang', getCurrentLang() === 'eng' ? 'en' : 'ko');
    }

    if (passportExpiryInput) {
        passportExpiryInput.value = traveler.passportExpiry ? formatDateForInput(traveler.passportExpiry) : '';
        passportExpiryInput.setAttribute('lang', getCurrentLang() === 'eng' ? 'en' : 'ko');
    }

    if (titleSelect) {
        titleSelect.value = traveler.title || 'MR';
    }

    if (typeSelect) {
        typeSelect.value = traveler.type || 'adult';
    }

    if (visaSelect) {
        visaSelect.value = traveler.visaType || 'with_visa';
    }

    if (mainTravelerRadio) {
        mainTravelerRadio.checked = traveler.isMainTraveler || traveler.index === 0;
    }

    // Existing passport image (from customer profile) should be visible in Traveler Information
    try {
        const raw = String(
            traveler.passportImage ||
            traveler.profileImage ||
            traveler.passportPhoto ||
            ''
        ).trim();
        const imgUrl = normalizePassportImageUrl(raw);
        if (imgUrl) {
            // 정규화된 URL을 traveler에 저장(재렌더링 시 재사용)
            traveler.passportImage = imgUrl;
            if (passportPhotoName) {
                try {
                    const u = new URL(imgUrl);
                    const parts = (u.pathname || '').split('/');
                    passportPhotoName.textContent = parts[parts.length - 1] || 'passport_photo';
                } catch (_) {
                    const parts = imgUrl.split('/');
                    passportPhotoName.textContent = parts[parts.length - 1] || 'passport_photo';
                }
            }
            if (passportPhotoInfo) passportPhotoInfo.style.display = 'block';
            if (passportPhotoPreviewBtn) {
                passportPhotoPreviewBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    window.open(imgUrl, '_blank', 'noopener');
                };
            }
            if (passportPhotoRemoveBtn) {
                passportPhotoRemoveBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    traveler.passportImage = '';
                    traveler.passportPhotoFile = null;
                    try {
                        const prevUrl = traveler.passportPhotoPreviewUrl;
                        if (prevUrl) URL.revokeObjectURL(prevUrl);
                    } catch (_) {}
                    traveler.passportPhotoPreviewUrl = null;
                    if (passportPhotoInput) passportPhotoInput.value = '';
                    if (passportPhotoInfo) passportPhotoInfo.style.display = 'none';
                };
            }
        } else {
            // if no existing image and no selected file, keep hidden
            if (!traveler.passportPhotoFile && passportPhotoInfo) passportPhotoInfo.style.display = 'none';
        }
    } catch (_) {}
}


// 여행자 이벤트 리스너 추가
function attachTravelerEventListeners(row, index) {
    const typeSelect = row.querySelector('.traveler-type');
    const visaSelect = row.querySelector('.traveler-visa');
    const titleSelect = row.querySelector('.traveler-title');
    const firstNameInput = row.querySelector('.traveler-firstname');
    const lastNameInput = row.querySelector('.traveler-lastname');
    const genderSelect = row.querySelector('.traveler-gender');
    const ageInput = row.querySelector('.traveler-age');
    const birthDateInput = row.querySelector('.traveler-birthdate');
    const nationalityInput = row.querySelector('.traveler-nationality');
    const passportInput = row.querySelector('.traveler-passport');
    const passportIssueInput = row.querySelector('.traveler-passport-issue');
    const passportExpiryInput = row.querySelector('.traveler-passport-expiry');
    const passportPhotoInput = row.querySelector('.traveler-passport-photo');
    const passportPhotoInfo = row.querySelector('.traveler-passport-photo-info');
    const passportPhotoName = row.querySelector('.traveler-passport-photo-name');
    const passportPhotoPreviewBtn = row.querySelector('.traveler-passport-photo-preview');
    const passportPhotoRemoveBtn = row.querySelector('.traveler-passport-photo-remove');
    const mainTravelerRadio = row.querySelector('input[name="lead_traveler"]');
    
    typeSelect?.addEventListener('change', async function() {
        const prev = travelers[index]?.type;
        const next = typeSelect.value;
        // 룸 옵션 선택 후 인원 변경 시 경고
        if (!(await __confirmResetRoomOptionsIfSelected())) {
            typeSelect.value = prev || 'adult';
            return;
        }
        travelers[index].type = next;
    });
    
    visaSelect?.addEventListener('change', () => {
        travelers[index].visaType = visaSelect.value;
        const hasVisa = visaSelect.value === 'with_visa' || visaSelect.value === 'foreign';
        travelers[index].visaRequired = !hasVisa;
    });
    
    titleSelect?.addEventListener('change', () => {
        travelers[index].title = titleSelect.value;
    });
    
    firstNameInput?.addEventListener('input', () => {
        travelers[index].firstName = firstNameInput.value;
    });
    
    lastNameInput?.addEventListener('input', () => {
        travelers[index].lastName = lastNameInput.value;
    });
    
    genderSelect?.addEventListener('change', () => {
        travelers[index].gender = genderSelect.value;
    });
    
    ageInput?.addEventListener('input', () => {
        travelers[index].age = parseInt(ageInput.value) || null;
    });
    
    birthDateInput?.addEventListener('change', () => {
        travelers[index].birthDate = birthDateInput.value;
        if (birthDateInput.value) {
            const age = calculateAge(birthDateInput.value);
            travelers[index].age = age;
            ageInput.value = age;
        }
    });
    
    nationalityInput?.addEventListener('input', () => {
        travelers[index].nationality = nationalityInput.value;
    });
    
    passportInput?.addEventListener('input', () => {
        travelers[index].passportNumber = passportInput.value;
    });
    
    passportIssueInput?.addEventListener('change', () => {
        travelers[index].passportIssueDate = passportIssueInput.value;
    });
    
    passportExpiryInput?.addEventListener('change', () => {
        travelers[index].passportExpiry = passportExpiryInput.value;
    });

    // 여권 사진 업로드 → 로컬 미리보기 + 파일 UI
    passportPhotoInput?.addEventListener('change', () => {
        const file = passportPhotoInput.files && passportPhotoInput.files[0] ? passportPhotoInput.files[0] : null;
        if (!file) return;

        // 기존 preview URL 정리
        try {
            const prevUrl = travelers[index]?.passportPhotoPreviewUrl;
            if (prevUrl) URL.revokeObjectURL(prevUrl);
        } catch (_) {}

        const url = URL.createObjectURL(file);
        travelers[index].passportPhotoFile = file;
        travelers[index].passportPhotoPreviewUrl = url;
        // 백엔드 업로드 키는 저장 시점에 부여

        if (passportPhotoName) passportPhotoName.textContent = file.name || 'passport_photo';
        if (passportPhotoInfo) passportPhotoInfo.style.display = 'block';

        if (passportPhotoPreviewBtn) {
            passportPhotoPreviewBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (url) window.open(url, '_blank', 'noopener');
            };
        }
        if (passportPhotoRemoveBtn) {
            passportPhotoRemoveBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                try { if (url) URL.revokeObjectURL(url); } catch (_) {}
                travelers[index].passportPhotoFile = null;
                travelers[index].passportPhotoPreviewUrl = null;
                if (passportPhotoInput) passportPhotoInput.value = '';
                if (passportPhotoInfo) passportPhotoInfo.style.display = 'none';
            };
        }
    });
    
    mainTravelerRadio?.addEventListener('change', () => {
        if (mainTravelerRadio.checked) {
            travelers.forEach((t, i) => {
                t.isMainTraveler = (i === index);
            });
            // 모든 라디오 버튼 업데이트
            document.querySelectorAll('input[name="lead_traveler"]').forEach((radio, i) => {
                radio.checked = (i === index);
            });
        }
    });
}

// 여행자 삭제
window.deleteTraveler = async function(index) {
    // Travel Customer Information: 최소 1명은 유지되어야 함
    if (Array.isArray(travelers) && travelers.length <= 1) {
        __showMustKeepOneTravelerPopup();
        return;
    }
    if (!confirm(getText('deleteTraveler'))) {
        return;
    }

    // 룸 옵션 선택 후 인원 변경(삭제) 시 경고
    if (!(await __confirmResetRoomOptionsIfSelected())) return;

    travelers.splice(index, 1);
    // 대표 여행자 보정
    if (travelers.length > 0 && travelers.every(t => !t.isMainTraveler)) {
        travelers[0].isMainTraveler = true;
    }
    __rerenderTravelersTable();
};

// 룸 옵션 선택 모달 열기
function openRoomOptionModal() {
    if (!selectedPackage || !selectedPackage.packageId) {
        alert(getText('pleaseSelectProduct'));
        return;
    }
    
    selectedRoomsInModal = [...selectedRooms];
    openModal('room-option-modal');
    loadRoomOptions();
}

// 기본 룸 옵션 데이터
const defaultRoomOptions = [
    { roomId: 'standard', roomType: 'Standard room', capacity: 2, roomPrice: 0 },
    { roomId: 'double', roomType: 'Double room', capacity: 2, roomPrice: 0 },
    { roomId: 'triple', roomType: 'Triple room', capacity: 3, roomPrice: 0 },
    { roomId: 'family', roomType: 'Family room', capacity: 4, roomPrice: 0 },
    { roomId: 'single', roomType: 'Single Supplement Surcharge', capacity: 1, roomPrice: 10000 }
];

// 현재 로드된 룸 옵션 데이터 (API에서 가져온 데이터 저장)
let currentRoomOptions = [];

// 룸 옵션 로드
async function loadRoomOptions() {
    const container = document.getElementById('room-option-list');
    if (!container) return;
    
    try {
        container.innerHTML = `<div class="is-center">${getText('loading')}</div>`;
        
        let roomOptions = [];
        
        // 룸 옵션 API 호출 시도
        try {
            const response = await fetch(`../backend/api/package-options.php?packageId=${selectedPackage.packageId}`);
            const result = await response.json();
            
            if (result.success && result.data && result.data.roomOptions && result.data.roomOptions.length > 0) {
                roomOptions = result.data.roomOptions;
                // API에서 가져온 룸 옵션을 전역 변수에 저장
                currentRoomOptions = roomOptions;
            } else {
                // API에서 데이터가 없으면 기본 데이터 사용
                roomOptions = defaultRoomOptions;
                currentRoomOptions = defaultRoomOptions;
            }
        } catch (error) {
            console.error('Error loading room options from API:', error);
            // API 에러 시 기본 데이터 사용
            roomOptions = defaultRoomOptions;
            currentRoomOptions = defaultRoomOptions;
        }
        
        // 룸 옵션 목록 렌더링 (b2b-booking-detail 스타일)
        let html = '';
        roomOptions.forEach(room => {
            const existingRoom = selectedRoomsInModal.find(r => r.roomId === room.roomId);
            const count = existingRoom ? existingRoom.count : 0;
            const isSelected = count > 0;

            const displayPrice = __getRoomDisplayPrice(room);
            const priceText = displayPrice > 0 ? `+₱${formatCurrency(displayPrice)}` : 'Included';
            html += `
                <div class="room-option-card ${isSelected ? 'selected' : ''}" data-room-id="${room.roomId}">
                    <div class="room-option-card-row">
                        <span class="room-option-card-name">${escapeHtml(room.roomType || '')}</span>
                        <span class="room-option-card-capacity">${room.capacity || 1} guests</span>
                        <span class="room-option-card-price ${displayPrice > 0 ? 'has-price' : ''}">${priceText}</span>
                        <div class="room-option-card-controls">
                            <button type="button" class="room-qty-btn" onclick="changeRoomQuantity('${room.roomId}', -1)" ${count === 0 ? 'disabled' : ''}>-</button>
                            <span class="room-qty-value ${count > 0 ? 'has-value' : ''}" id="room-qty-${room.roomId}">${count}</span>
                            <button type="button" class="room-qty-btn" onclick="changeRoomQuantity('${room.roomId}', 1)">+</button>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // 주문 요약 업데이트
        updateOrderSummary();
        updateRoomCombinationBanner();
        
    } catch (error) {
        console.error('Error loading room options:', error);
        // 에러 시 기본 데이터 사용
        currentRoomOptions = defaultRoomOptions;
        let html = '';
        defaultRoomOptions.forEach(room => {
            const existingRoom = selectedRoomsInModal.find(r => r.roomId === room.roomId);
            const count = existingRoom ? existingRoom.count : 0;
            const isSelected = count > 0;

            const displayPrice = __getRoomDisplayPrice(room);
            const priceText = displayPrice > 0 ? `+₱${formatCurrency(displayPrice)}` : 'Included';
            html += `
                <div class="room-option-card ${isSelected ? 'selected' : ''}" data-room-id="${room.roomId}">
                    <div class="room-option-card-row">
                        <span class="room-option-card-name">${escapeHtml(room.roomType || '')}</span>
                        <span class="room-option-card-capacity">${room.capacity || 1} guests</span>
                        <span class="room-option-card-price ${displayPrice > 0 ? 'has-price' : ''}">${priceText}</span>
                        <div class="room-option-card-controls">
                            <button type="button" class="room-qty-btn" onclick="changeRoomQuantity('${room.roomId}', -1)" ${count === 0 ? 'disabled' : ''}>-</button>
                            <span class="room-qty-value ${count > 0 ? 'has-value' : ''}" id="room-qty-${room.roomId}">${count}</span>
                            <button type="button" class="room-qty-btn" onclick="changeRoomQuantity('${room.roomId}', 1)">+</button>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
        updateOrderSummary();
        updateRoomCombinationBanner();
    }
}

// 룸 수량 변경
window.changeRoomQuantity = function(roomId, change) {
    // API에서 가져온 룸 옵션에서 먼저 찾기, 없으면 기본 데이터에서 찾기
    const room = currentRoomOptions.find(r => r.roomId === roomId) || 
                defaultRoomOptions.find(r => r.roomId === roomId) ||
                selectedRoomsInModal.find(r => r.roomId === roomId);
    if (!room) {
        console.warn(`Room not found: ${roomId}`);
        return;
    }
    
    const existingIndex = selectedRoomsInModal.findIndex(r => r.roomId === roomId);
    const currentCount = existingIndex >= 0 ? selectedRoomsInModal[existingIndex].count : 0;
    const newCount = Math.max(0, currentCount + change);
    
    if (newCount === 0) {
        // 수량이 0이면 제거
        selectedRoomsInModal = selectedRoomsInModal.filter(r => r.roomId !== roomId);
    } else {
        if (existingIndex >= 0) {
            // 기존 항목 업데이트 (가격 정보는 유지)
            selectedRoomsInModal[existingIndex].count = newCount;
        } else {
            // 새 항목 추가 (API에서 가져온 가격 정보 사용)
            selectedRoomsInModal.push({
                roomId: room.roomId,
                roomType: room.roomType,
                roomPrice: room.roomPrice || 0, // API에서 가져온 가격 사용
                capacity: room.capacity || 1,
                count: newCount
            });
        }
    }

    // UI 직접 업데이트 (b2b-booking-detail 스타일)
    const qtyEl = document.getElementById(`room-qty-${roomId}`);
    if (qtyEl) {
        qtyEl.textContent = newCount;
        // has-value 클래스 토글
        if (newCount > 0) {
            qtyEl.classList.add('has-value');
        } else {
            qtyEl.classList.remove('has-value');
        }
    }

    // 마이너스 버튼 비활성화
    const minusBtn = qtyEl?.parentElement?.querySelector('.room-qty-btn');
    if (minusBtn) minusBtn.disabled = (newCount === 0);

    // 카드 선택 상태 토글
    const card = document.querySelector(`.room-option-card[data-room-id="${roomId}"]`);
    if (card) {
        if (newCount > 0) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }

    // 주문 요약 업데이트
    updateOrderSummary();
    updateRoomCombinationBanner();
};

// 주문 요약 업데이트 (calculateTotalAmount와 동일한 계산 로직 사용)
function updateOrderSummary() {
    const summaryContainer = document.getElementById('order-summary-list');
    const amountContainer = document.getElementById('order-amount-value');
    if (!summaryContainer || !amountContainer) return;

    // calculateTotalAmount와 동일한 계산 로직 사용
    let totalAmount = 0;
    let summaryHtml = '';

    // 상품 기본 가격: 여행자별 가격 계산 (Infant/Child 특별 로직 적용)
    if (selectedPackage) {
        // Adult, Child(Room Yes), Child(Room No), Infant 별로 집계
        const priceGroups = new Map(); // key -> { label, count, total }
        travelers.forEach(t => {
            if (!t) return;
            const type = __classifyTypeKey(t.type);
            const price = __getTravelerPrice(t);

            let key, label;
            if (type === 'infant') {
                if (t.infantSeat) {
                    key = 'infant_seat';
                    label = 'Infant (Seat)';
                } else {
                    key = 'infant_no_seat';
                    label = 'Infant (No Seat)';
                }
            } else if (type === 'child') {
                if (t.childRoom === true) {
                    key = 'child_room_yes';
                    label = 'Child (Room)';
                } else {
                    key = 'child_room_no';
                    label = 'Child (No Room)';
                }
            } else {
                key = 'adult';
                label = __labelByTravelerType?.['adult'] || 'Adult';
            }

            if (!priceGroups.has(key)) {
                priceGroups.set(key, { label, count: 0, total: 0, unitPrice: price });
            }
            const group = priceGroups.get(key);
            group.count++;
            group.total += price;
        });

        for (const [, group] of priceGroups.entries()) {
            totalAmount += group.total;
            summaryHtml += `
                <div class="order-summary-item">
                    <span>${escapeHtml(group.label)} x${group.count}</span>
                    <span class="price">${formatCurrency(group.total)}(₱)</span>
                </div>
            `;
        }
    }

    // 룸 옵션 가격 (calculateTotalAmount와 동일한 로직)
    selectedRoomsInModal.forEach(room => {
        if (room.count > 0) {
            const roomTotal = __roomLineTotal(room);
            totalAmount += roomTotal;
            summaryHtml += `
                <div class="order-summary-item">
                    <span>${escapeHtml(room.roomType || '')} x${room.count}</span>
                    <span class="price">${formatCurrency(roomTotal)}(₱)</span>
                </div>
            `;
        }
    });

    if (summaryHtml === '') {
        summaryHtml = '<div class="order-summary-item" data-lan-eng="No items selected">No items selected</div>';
    }

    summaryContainer.innerHTML = summaryHtml;
    amountContainer.textContent = `${formatCurrency(totalAmount)}(₱)`;
}

// 싱글룸 추가요금 규칙:
// - 예약 인원에 상관없이 Single room 선택 시 항상 추가 요금 부과
// - 룸 수용 인원 계산 시: adult + childRoom=Yes인 child만 포함, 나머지 child와 infant는 제외
function __bookingAdultsOnly() {
    try {
        return travelers.filter(t => {
            if (!t) return false;
            const type = __classifyTypeKey(t.type);
            // Adult는 항상 포함
            if (type === 'adult') return true;
            // Child는 childRoom이 Yes인 경우만 포함
            if (type === 'child' && t.childRoom === true) return true;
            // Infant와 childRoom=No인 Child는 제외
            return false;
        }).length;
    } catch (_) {
        return 0;
    }
}

function __isSingleRoom(room) {
    if (!room) return false;
    const id = String(room.roomId || room.id || '').toLowerCase();
    const nm = String(room.roomType || room.name || '').toLowerCase();
    if (room.isSingleRoom === true || room.isSingleRoom === 1) return true;
    return id === 'single' || id.includes('single') || nm.includes('single') || nm.includes('싱글');
}

// 룸 표시 가격 가져오기 (싱글룸은 날짜별 singlePrice 우선)
function __getRoomDisplayPrice(room) {
    let unitPrice = Number(room?.roomPrice) || 0;

    // 싱글룸인 경우 날짜별 singlePrice 우선 사용
    if (__isSingleRoom(room)) {
        const dateSpecificSinglePrice = __pricingByTravelerType['single'];
        if (Number.isFinite(dateSpecificSinglePrice) && dateSpecificSinglePrice > 0) {
            unitPrice = dateSpecificSinglePrice;
        }
    }

    return unitPrice;
}

function __roomLineTotal(room) {
    const unitPrice = __getRoomDisplayPrice(room);
    const base = unitPrice * (Number(room?.count) || 1);
    if (base <= 0) return 0;
    // 싱글룸은 예약 인원과 상관없이 항상 추가 요금 적용
    return base;
}

// 룸 조합 배너 업데이트 및 인원 검증
function updateRoomCombinationBanner() {
    const banner = document.getElementById('room-combination-count');
    if (!banner) return;

    // 총 예약 인원 수 계산 (adult + childRoom=Yes인 child만 포함)
    let totalBookingGuests = __bookingAdultsOnly();

    // 각 룸타입 수량 × 수용 인원 합 계산 (select-room.js와 동일한 방식)
    let totalCapacity = 0;
    selectedRoomsInModal.forEach(room => {
        const roomCapacity = (room.capacity || 0) * (room.count || 0);
        totalCapacity += roomCapacity;
    });

    banner.textContent = `(${totalCapacity}/${totalBookingGuests} ${getText('People') || '명'})`;

    // 인원 검증 및 버튼 활성화/비활성화 (select-room.js의 validateRoomSelection과 동일한 로직)
    validateRoomCapacity(totalCapacity, totalBookingGuests);
}

// 인원 검증 함수 (select-room.js의 validateRoomSelection과 동일한 로직)
function validateRoomCapacity(totalCapacity, totalBookingGuests) {
    const confirmBtn = document.getElementById('confirm-room-selection-btn');
    if (!confirmBtn) return;
    
    // 초기 상태 (객실 미선택)
    if (totalCapacity === 0) {
        confirmBtn.disabled = true;
        return;
    }
    
    // 요구 인원이 0이면 버튼 비활성화
    if (totalBookingGuests === 0) {
        confirmBtn.disabled = true;
        return;
    }
    
    // 총 예약 인원 수 = 각 룸타입 수량 × 수용 인원 합 검증
    // 수용 인원이 부족한 경우
    if (totalCapacity < totalBookingGuests) {
        confirmBtn.disabled = true;
        return;
    }
    
    // 수용 인원이 예약 인원보다 많은 경우
    if (totalCapacity > totalBookingGuests) {
        confirmBtn.disabled = true;
        return;
    }
    
    // 수용 인원이 예약 인원과 정확히 일치하는 경우만 버튼 활성화
    if (totalCapacity === totalBookingGuests) {
        confirmBtn.disabled = false;
        return;
    }
}

// 룸 옵션 선택 확인 (select-room.js와 동일한 검증 로직)
function confirmRoomSelection() {
    // 총 예약 인원 수 계산 (adult + childRoom=Yes인 child만 포함)
    let totalBookingGuests = __bookingAdultsOnly();
    
    // 각 룸타입 수량 × 수용 인원 합 계산 (select-room.js와 동일한 방식)
    let totalCapacity = 0;
    selectedRoomsInModal.forEach(room => {
        const roomCapacity = (room.capacity || 0) * (room.count || 0);
        totalCapacity += roomCapacity;
    });
    
    // 초기 상태 (객실 미선택)
    if (totalCapacity === 0) {
        const lang = getCurrentLang();
        if (lang === 'eng') {
            alert('Please select rooms.');
        } else {
            alert('객실을 선택해주세요.');
        }
        return;
    }
    
    // 수용 인원이 부족한 경우
    if (totalCapacity < totalBookingGuests) {
        const lang = getCurrentLang();
        if (lang === 'eng') {
            alert('Insufficient room capacity.');
        } else {
            alert('객실 수용 인원이 부족합니다.');
        }
        return;
    }
    
    // 수용 인원이 예약 인원보다 많은 경우
    if (totalCapacity > totalBookingGuests) {
        const lang = getCurrentLang();
        if (lang === 'eng') {
            alert(`The number of people does not match the room capacity. Selected capacity: ${totalCapacity}, Required: ${totalBookingGuests}.`);
        } else {
            alert(`선택한 객실의 수용 인원이 예약 인원과 일치하지 않습니다. 선택된 수용 인원: ${totalCapacity}명, 요구 인원: ${totalBookingGuests}명`);
        }
        return;
    }
    
    // 수용 인원이 예약 인원과 정확히 일치하는 경우만 진행
    if (totalCapacity === totalBookingGuests) {
        selectedRooms = [...selectedRoomsInModal];
        updateRoomOptionDisplay();
        // 룸 옵션 선택 후 Order Amount 업데이트 (updateOrderSummary와 동일한 계산)
        calculateTotalAmount();
        
        // 예약 이력 업데이트
        const roomCount = selectedRooms.reduce((sum, r) => sum + (r.count || 0), 0);
        addReservationHistory(`룸 옵션 선택: ${roomCount}개`);
        
        closeModal('room-option-modal');
        return;
    }
}

// 룸 옵션 표시 업데이트
function updateRoomOptionDisplay() {
    const roomOptionBtn = document.getElementById('room_option_btn');
    const roomListEl = document.getElementById('selected-rooms-list');

    if (roomOptionBtn && selectedRooms.length > 0) {
        const totalRooms = selectedRooms.reduce((sum, room) => sum + room.count, 0);
        const lang = getCurrentLang();
        if (lang === 'eng') {
            roomOptionBtn.textContent = getText('selectRoomOptionCount', { count: totalRooms });
        } else {
            roomOptionBtn.textContent = getText('selectRoomOptionCount', { count: totalRooms });
        }
    } else if (roomOptionBtn) {
        roomOptionBtn.textContent = getText('selectRoomOption');
    }

    // 선택된 룸 목록 표시
    if (roomListEl) {
        if (selectedRooms.length === 0) {
            roomListEl.innerHTML = '';
        } else {
            let html = '';
            selectedRooms.forEach(room => {
                const roomName = room.roomType || room.name || room.roomName || 'Room';
                const count = room.count || 0;
                const capacity = room.capacity || 0;
                const price = room.roomPrice || room.price || 0;
                const totalPrice = price * count;

                html += `
                    <div class="selected-room-item">
                        <div class="selected-room-info">
                            <span class="selected-room-name">${escapeHtml(roomName)}</span>
                            <span class="selected-room-count">x${count}</span>
                        </div>
                        <div class="selected-room-details">
                            <span class="selected-room-capacity">${capacity} pax/room</span>
                            <span class="selected-room-price">₱${totalPrice.toLocaleString()}</span>
                        </div>
                    </div>
                `;
            });
            roomListEl.innerHTML = html;
        }
    }
}

// 총 금액 계산
// 선금 자동 계산 함수
// Advance payment = Order Amount × (1 - 예약금 비율)
function calculateAdvancePayment(orderAmount) {
    return orderAmount * (1 - agentDepositRate);
}

// 잔금 계산 함수
// Balance = Order Amount - Advance payment
// (요구사항 예시: 10,000 / 10% → Advance payment 9,000 → Balance 1,000)
function calculateBalanceAmount(orderAmount, advancePayment) {
    const balance = orderAmount - advancePayment;
    return Math.max(0, balance); // 음수 방지
}

function calculateTotalAmount() {
    let total = 0;

    // 상품 기본 가격: 여행자별 가격 계산 (Infant/Child 특별 로직 적용)
    if (selectedPackage) {
        travelers.forEach(t => {
            if (!t) return;
            total += __getTravelerPrice(t);
        });
    }

    // 룸 옵션 가격
    selectedRooms.forEach(room => {
        total += __roomLineTotal(room);
    });

    // 항공 옵션 가격 (여행자별)
    travelers.forEach(t => {
        if (t?.flightOptionPrices) {
            Object.values(t.flightOptionPrices).forEach(price => {
                total += (parseFloat(price) || 0);
            });
        }
    });

    // Visa 금액 계산 (travelerModalData 또는 travelers 사용)
    const visaData = travelerModalData.length > 0 ? travelerModalData : travelers;
    visaData.forEach(t => {
        const visaType = t?.visaType || 'with_visa';
        if (visaType === 'group') {
            total += 1500; // Group Visa +₱1500
        } else if (visaType === 'individual') {
            total += 1900; // Individual Visa +₱1900
        }
    });

    const totalInput = document.getElementById('pay_total');
    if (totalInput) {
        totalInput.value = formatCurrency(total);
    }

    // 3단계 Payment 금액 업데이트
    updateThreeStepPayments(total);

    // Payment 기한 업데이트
    updatePaymentDeadlines();

    // Full Payment 금액도 업데이트 (탭이 full인 경우)
    if (selectedPaymentType === 'full') {
        updateFullPaymentAmount();
    }

    __renderCalcDebug();
}

// 날짜를 YYYY-MM-DD 형식으로 변환 (로컬 시간대 기준)
function formatDateLocal(date) {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// [UNUSED] 3단계 Payment 데드라인 계산 - 실제로는 calculateDownPaymentDeadline() 등 개별 함수 사용
// function calculatePaymentDeadlines() {
//     const today = new Date();
//     today.setHours(0, 0, 0, 0);
//     const departureDateVal = document.getElementById('departure_date_value')?.value || '';
//     const departureDate = departureDateVal ? new Date(departureDateVal) : null;
//
//     if (departureDate) {
//         departureDate.setHours(0, 0, 0, 0);
//         const daysUntilDeparture = Math.ceil((departureDate - today) / (1000 * 60 * 60 * 24));
//
//         // 특수 케이스: 출발일 3일 이내 → 모두 당일
//         if (daysUntilDeparture <= 3) {
//             const todayStr = formatDateLocal(today);
//             return {
//                 down: todayStr,
//                 second: todayStr,
//                 balance: todayStr
//             };
//         }
//
//         // Down Payment: 예약일 + 3일
//         const downDeadline = new Date(today);
//         downDeadline.setDate(downDeadline.getDate() + 3);
//
//         // Second Payment: 출발 30일 이내면 예약일+3일, 아니면 예약일+30일
//         const secondDeadline = new Date(today);
//         if (daysUntilDeparture <= 30) {
//             secondDeadline.setDate(secondDeadline.getDate() + 3);
//         } else {
//             secondDeadline.setDate(secondDeadline.getDate() + 30);
//         }
//
//         // Balance: 출발 30일 이내면 예약일+3일, 아니면 출발일-30일
//         let balanceDeadline;
//         if (daysUntilDeparture <= 30) {
//             balanceDeadline = new Date(today);
//             balanceDeadline.setDate(balanceDeadline.getDate() + 3);
//         } else {
//             balanceDeadline = new Date(departureDate);
//             balanceDeadline.setDate(balanceDeadline.getDate() - 30);
//         }
//
//         // Balance가 Second보다 짧으면 Second와 동일하게
//         if (balanceDeadline < secondDeadline) {
//             balanceDeadline = new Date(secondDeadline);
//         }
//
//         return {
//             down: formatDateLocal(downDeadline),
//             second: formatDateLocal(secondDeadline),
//             balance: formatDateLocal(balanceDeadline)
//         };
//     }
//
//     // 출발일 없으면 기본값
//     const downDeadline = new Date(today);
//     downDeadline.setDate(downDeadline.getDate() + 3);
//     return {
//         down: formatDateLocal(downDeadline),
//         second: '',
//         balance: ''
//     };
// }

// 3단계 Payment 금액 계산 및 표시
function updateThreeStepPayments(total) {
    const DOWN_PAYMENT_PER_PERSON = 5000;
    const SECOND_PAYMENT_PER_PERSON = 10000;

    // Infant를 제외한 인원수 계산 (Adult + Child만)
    const nonInfantCount = countNonInfantTravelers();

    // Down Payment: 5,000 × (Adult + Child 인원수)
    const downPaymentTotal = DOWN_PAYMENT_PER_PERSON * nonInfantCount;
    const downPaymentInput = document.getElementById('down_payment_amount');
    if (downPaymentInput) {
        downPaymentInput.value = formatCurrency(downPaymentTotal);
    }

    // Second Payment: 10,000 × (Adult + Child 인원수)
    const secondPaymentTotal = SECOND_PAYMENT_PER_PERSON * nonInfantCount;
    const secondPaymentInput = document.getElementById('second_payment_amount');
    if (secondPaymentInput) {
        secondPaymentInput.value = formatCurrency(secondPaymentTotal);
    }

    // Balance: 총액 - Down Payment - Second Payment (Infant 금액은 여기 포함)
    const balanceAmount = Math.max(0, total - downPaymentTotal - secondPaymentTotal);
    const balanceInput = document.getElementById('balance_amount');
    if (balanceInput) {
        balanceInput.value = formatCurrency(balanceAmount);
    }
}

// Infant를 제외한 여행자 수 계산
function countNonInfantTravelers() {
    if (!travelers || travelers.length === 0) return 0;

    return travelers.filter(t => {
        if (!t) return false;
        const type = String(t.type || '').toLowerCase();
        // infant 타입 제외
        return type !== 'infant';
    }).length;
}

// Payment 기한 계산 및 표시
function updatePaymentDeadlines() {
    const departureDate = getSelectedDepartureDate();
    if (!departureDate) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Down Payment 기한 계산
    const downPaymentDue = calculateDownPaymentDeadline(today, departureDate);
    const downPaymentDueInput = document.getElementById('down_payment_due');
    if (downPaymentDueInput && downPaymentDue) {
        downPaymentDueInput.value = formatDateForInput(downPaymentDue);
    }

    // Second Payment 기한: Down Payment Confirm + 30일 (출발 30일 이내면 3일)
    // 현재는 예약 생성 시점이므로 Down Payment Confirm 날짜가 없음
    // 임시로 Down Payment 기한 + 30일로 설정
    const secondPaymentDue = calculateSecondPaymentDeadline(downPaymentDue, departureDate);
    const secondPaymentDueInput = document.getElementById('second_payment_due');
    if (secondPaymentDueInput && secondPaymentDue) {
        secondPaymentDueInput.value = formatDateForInput(secondPaymentDue);
    }

    // Balance 기한 계산
    const balanceDue = calculateBalanceDeadline(secondPaymentDue, departureDate);
    const balanceDueInput = document.getElementById('balance_due');
    if (balanceDueInput && balanceDue) {
        balanceDueInput.value = formatDateForInput(balanceDue);
    }
}

// 선택된 출발일 가져오기
function getSelectedDepartureDate() {
    const departureDateInput = document.getElementById('departure_date_value');
    if (!departureDateInput || !departureDateInput.value) return null;
    const date = new Date(departureDateInput.value);
    date.setHours(0, 0, 0, 0);
    return date;
}

// 날짜를 input[type=date] 형식으로 변환
function formatDateForInput(date) {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// ========== 결제 Deadline 규칙 ==========
// 규칙 1: 출발일까지 34일 이내 → Full Payment만, deadline +1일 (24시간)
// 규칙 2: 출발일까지 44일 이내 (35~44일) → Full Payment만, deadline +3일
// 규칙 3: 출발일까지 44일 초과 → Staged Payment
//         - Down Payment: 예약일 + 3일
//         - Second Payment: Down Payment deadline + 30일
//         - Balance: 출발일 - 30일

// 출발일까지 남은 일수 계산
function getDaysUntilDeparture(departureDate) {
    if (!departureDate) return null;
    const departure = new Date(departureDate);
    departure.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.ceil((departure - today) / (1000 * 60 * 60 * 24));
}

// Full Payment 강제 여부 (44일 이내)
function isFullPaymentRequired(departureDate) {
    const days = getDaysUntilDeparture(departureDate);
    return days !== null && days <= 44;
}

// Down Payment 기한 계산
function calculateDownPaymentDeadline(reservationDate, departureDate) {
    const today = new Date(reservationDate || new Date());
    today.setHours(0, 0, 0, 0);

    const daysUntilDeparture = getDaysUntilDeparture(departureDate);

    // 44일 이내: Full Payment 강제 (Down Payment 없음)
    if (daysUntilDeparture !== null && daysUntilDeparture <= 44) {
        return null;
    }

    // 44일 초과: 예약일 + 3일
    const deadline = new Date(today);
    deadline.setDate(deadline.getDate() + 3);
    return deadline;
}

// Second Payment 기한 계산
function calculateSecondPaymentDeadline(downPaymentDeadline, departureDate) {
    if (!departureDate) return null;

    const daysUntilDeparture = getDaysUntilDeparture(departureDate);

    // 44일 이내: Full Payment 강제 (Second Payment 없음)
    if (daysUntilDeparture !== null && daysUntilDeparture <= 44) {
        return null;
    }

    // 44일 초과: Down Payment deadline + 30일
    if (!downPaymentDeadline) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        downPaymentDeadline = new Date(today);
        downPaymentDeadline.setDate(downPaymentDeadline.getDate() + 3);
    }
    const deadline = new Date(downPaymentDeadline);
    deadline.setDate(deadline.getDate() + 30);
    return deadline;
}

// Balance 기한 계산
function calculateBalanceDeadline(secondPaymentDeadline, departureDate) {
    if (!departureDate) return null;

    const departure = new Date(departureDate);
    departure.setHours(0, 0, 0, 0);

    const daysUntilDeparture = getDaysUntilDeparture(departureDate);

    // 44일 이내: Full Payment 강제 (Balance 없음)
    if (daysUntilDeparture !== null && daysUntilDeparture <= 44) {
        return null;
    }

    // 44일 초과: 출발일 - 30일
    const deadline = new Date(departure);
    deadline.setDate(deadline.getDate() - 30);
    return deadline;
}

// Full Payment 기한 계산
function calculateFullPaymentDeadline(departureDate) {
    const daysUntilDeparture = getDaysUntilDeparture(departureDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // 34일 이내: +1일 (24시간)
    if (daysUntilDeparture !== null && daysUntilDeparture <= 34) {
        const deadline = new Date(today);
        deadline.setDate(deadline.getDate() + 1);
        return deadline;
    }

    // 35~44일: +3일
    // 44일 초과는 Staged이므로 Full Payment 사용 안 함 (기본값 +3일)
    const deadline = new Date(today);
    deadline.setDate(deadline.getDate() + 3);
    return deadline;
}

// 잔금 계산
// Balance = Order Amount - Advance payment
function updateBalance() {
    const depositInput = document.getElementById('pay_deposit');
    const totalInput = document.getElementById('pay_total');
    const balanceInput = document.getElementById('pay_balance');
    
    if (depositInput && totalInput && balanceInput) {
        const total = parseFloat(totalInput.value.replace(/[^\d.]/g, '')) || 0;
        const advancePayment = parseFloat(depositInput.value) || 0;
        
        // Balance = Order Amount - Advance payment
        const balance = calculateBalanceAmount(total, advancePayment);
        balanceInput.value = formatCurrency(balance);
    }
}

function __ensureCalcDebugPanel() {
    if (!__calcDebugEnabled) return null;
    if (__calcDebugEl) return __calcDebugEl;
    const el = document.createElement('div');
    el.id = 'calc-debug-panel';
    el.style.position = 'fixed';
    el.style.right = '16px';
    el.style.bottom = '16px';
    el.style.zIndex = '99999';
    el.style.background = 'rgba(17, 24, 39, 0.92)';
    el.style.color = '#fff';
    el.style.padding = '12px 14px';
    el.style.borderRadius = '10px';
    el.style.fontSize = '12px';
    el.style.lineHeight = '1.4';
    el.style.width = '320px';
    el.style.boxShadow = '0 10px 24px rgba(0,0,0,0.25)';
    el.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <strong>Calc Debug</strong>
            <button type="button" id="calc-debug-close" style="background:none;border:1px solid rgba(255,255,255,0.35);color:#fff;border-radius:8px;padding:2px 8px;cursor:pointer;">X</button>
        </div>
        <div id="calc-debug-body"></div>
    `;
    document.body.appendChild(el);
    el.querySelector('#calc-debug-close')?.addEventListener('click', () => {
        el.remove();
        __calcDebugEl = null;
    });
    __calcDebugEl = el;
    return el;
}

function __renderCalcDebug() {
    if (!__calcDebugEnabled) return;
    const el = __ensureCalcDebugPanel();
    if (!el) return;
    const body = el.querySelector('#calc-debug-body');
    if (!body) return;

    const totalInput = document.getElementById('pay_total');
    const depositInput = document.getElementById('pay_deposit');
    const balanceInput = document.getElementById('pay_balance');
    const total = totalInput ? (parseFloat((totalInput.value || '').replace(/[^\d.]/g, '')) || 0) : 0;
    const deposit = depositInput ? (parseFloat(depositInput.value) || 0) : 0;
    const balance = balanceInput ? (parseFloat((balanceInput.value || '').replace(/[^\d.]/g, '')) || 0) : 0;
    const rate = (typeof agentDepositRate === 'number' && agentDepositRate >= 0) ? agentDepositRate : 0;
    const depositPortion = total * rate;
    const expectedAdvancePayment = total * (1 - rate);
    const expectedBalance = Math.max(0, total - deposit - depositPortion);

    body.innerHTML = `
        <div><b>Order Amount</b>: ${formatCurrency(total)}</div>
        <div><b>Deposit Rate</b>: ${(rate * 100).toFixed(2)}%</div>
        <div><b>Deposit Portion</b> (Order×Rate): ${formatCurrency(depositPortion)}</div>
        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.2);margin:8px 0;">
        <div><b>Advance payment</b> (input): ${formatCurrency(deposit)}</div>
        <div><b>Advance payment</b> (expected): ${formatCurrency(expectedAdvancePayment)}</div>
        <div><b>Balance</b> (input): ${formatCurrency(balance)}</div>
        <div><b>Balance</b> (expected): ${formatCurrency(expectedBalance)}</div>
        <div style="margin-top:6px;opacity:.85;">auto-fill locked: ${isDepositManuallyEdited ? 'YES' : 'NO'}</div>
    `;
}

// 3단계 Payment 파일 업로드 상태
let downPaymentFile = null;
let secondPaymentFile = null;
let balancePaymentFile = null;

// Full Payment 관련 변수
let selectedPaymentType = 'staged'; // 'staged' or 'full'
let fullPaymentFile = null;

// 결제 확정 상태 (예약 편집 시 서버에서 받아온 값으로 설정)
let paymentConfirmationStatus = {
    downPaymentConfirmed: false,
    secondPaymentConfirmed: false
};

/**
 * Second Payment 섹션 활성화/비활성화
 * @param {boolean} enabled - true면 활성화, false면 비활성화
 */
function setSecondPaymentEnabled(enabled) {
    const notice = document.getElementById('second_payment_disabled_notice');
    const fields = document.getElementById('second_payment_fields');

    if (notice) notice.style.display = enabled ? 'none' : 'block';
    if (fields) fields.style.display = enabled ? 'flex' : 'none';

    paymentConfirmationStatus.downPaymentConfirmed = enabled;
}

/**
 * Balance 섹션 활성화/비활성화
 * @param {boolean} enabled - true면 활성화, false면 비활성화
 */
function setBalanceEnabled(enabled) {
    const notice = document.getElementById('balance_disabled_notice');
    const fields = document.getElementById('balance_fields');

    if (notice) notice.style.display = enabled ? 'none' : 'block';
    if (fields) fields.style.display = enabled ? 'flex' : 'none';

    paymentConfirmationStatus.secondPaymentConfirmed = enabled;
}

/**
 * 예약 데이터로부터 결제 확정 상태 설정
 * @param {Object} bookingData - 예약 데이터 (downPaymentConfirmedAt, advancePaymentConfirmedAt 포함)
 */
function updatePaymentSectionsFromBookingData(bookingData) {
    if (!bookingData) return;

    // Down Payment 확정 여부 확인
    const downPaymentConfirmed = !!(bookingData.downPaymentConfirmedAt && bookingData.downPaymentConfirmedAt !== '0000-00-00 00:00:00');
    setSecondPaymentEnabled(downPaymentConfirmed);

    // Second Payment (advancePayment) 확정 여부 확인
    const secondPaymentConfirmed = !!(bookingData.advancePaymentConfirmedAt && bookingData.advancePaymentConfirmedAt !== '0000-00-00 00:00:00');
    setBalanceEnabled(secondPaymentConfirmed);
}

function initializeDepositProofUpload() {
    // Down Payment 파일 업로드
    initializePaymentFileUpload(
        'down_payment_file_btn',
        'down_payment_file_input',
        'down_payment_file_remove',
        'down_payment_file_info',
        'down_payment_file_name',
        (file) => { downPaymentFile = file; },
        () => { downPaymentFile = null; }
    );

    // Second Payment 파일 업로드
    initializePaymentFileUpload(
        'second_payment_file_btn',
        'second_payment_file_input',
        'second_payment_file_remove',
        'second_payment_file_info',
        'second_payment_file_name',
        (file) => { secondPaymentFile = file; },
        () => { secondPaymentFile = null; }
    );

    // Balance 파일 업로드
    initializePaymentFileUpload(
        'balance_file_btn',
        'balance_file_input',
        'balance_file_remove',
        'balance_file_info',
        'balance_file_name',
        (file) => { balancePaymentFile = file; },
        () => { balancePaymentFile = null; }
    );
}

function initializePaymentFileUpload(btnId, inputId, removeId, infoId, nameId, onFileSelect, onFileRemove) {
    const uploadBtn = document.getElementById(btnId);
    const fileInput = document.getElementById(inputId);
    const removeBtn = document.getElementById(removeId);
    const fileInfo = document.getElementById(infoId);
    const fileNameEl = document.getElementById(nameId);

    if (!uploadBtn || !fileInput) return;

    uploadBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            alert('File size must be less than 10MB');
            event.target.value = '';
            return;
        }

        onFileSelect(file);
        if (fileNameEl) {
            fileNameEl.textContent = `${file.name} (${formatFileSize(file.size)})`;
        }
        if (fileInfo) {
            fileInfo.style.display = 'block';
        }
    });

    removeBtn?.addEventListener('click', () => {
        onFileRemove();
        if (fileInput) fileInput.value = '';
        if (fileInfo) fileInfo.style.display = 'none';
        if (fileNameEl) fileNameEl.textContent = '';
    });
}

function clearDepositProofFile() {
    downPaymentFile = null;
    secondPaymentFile = null;
    balancePaymentFile = null;

    ['down_payment', 'second_payment', 'balance'].forEach(prefix => {
        const fileInput = document.getElementById(`${prefix}_file_input`);
        const fileInfo = document.getElementById(`${prefix}_file_info`);
        const fileNameEl = document.getElementById(`${prefix}_file_name`);
        if (fileInput) fileInput.value = '';
        if (fileInfo) fileInfo.style.display = 'none';
        if (fileNameEl) fileNameEl.textContent = '';
    });
}

// 저장 처리
async function handleSave() {
    try {
        // 필수 필드 검증
        if (!selectedPackage || !selectedPackage.packageId) {
            alert(getText('requiredFields') + '\n' + getText('pleaseSelectProduct'));
            return;
        }
        
        const departureDateInput = document.getElementById('departure_date');
        const departureDateValueInput = document.getElementById('departure_date_value');
        if (!departureDateInput || !departureDateInput.value || !departureDateValueInput || !departureDateValueInput.value) {
            alert(getText('requiredFields') + '\n' + getText('selectTravelStartDate'));
            return;
        }
        
        const userNameInput = document.getElementById('user_name');
        const userEmailInput = document.getElementById('user_email');
        const userPhoneInput = document.getElementById('user_phone');
        
        if (!userNameInput?.value || !userEmailInput?.value || !userPhoneInput?.value) {
            alert(getText('requiredFields') + '\n' + getText('enterCustomerInfo'));
            return;
        }
        
        if (!travelers || travelers.length === 0) {
            alert(getText('requiredFields') + '\n' + getText('enterTravelerInfo'));
            return;
        }

        // 여행자 정보 검증
        for (let i = 0; i < travelers.length; i++) {
            const traveler = travelers[i];
            if (!traveler.firstName || !traveler.lastName) {
                alert(getText('requiredFields') + '\n' + getText('enterTravelerName', { index: i + 1 }));
                return;
            }
        }

        // Room Options 필수 검증
        if (!selectedRooms || selectedRooms.length === 0) {
            alert(getText('requiredFields') + '\nPlease select room options.');
            return;
        }

        // 고객 정보
        const nameParts = userNameInput.value.trim().split(' ');
        const customerInfo = {
            accountId: document.getElementById('customer_account_id')?.value || null,
            firstName: nameParts[0] || '',
            lastName: nameParts.slice(1).join(' ') || '',
            email: userEmailInput.value,
            phone: userPhoneInput.value,
            countryCode: document.getElementById('country_code')?.value || '+63'
        };
        
        // 인원 수 계산 (travelers 기반 → adult/child/infant 분류)
        let adults = 0, children = 0, infants = 0;
        let visaFeeTotal = 0;
        let flightOptionFeeTotal = 0;
        travelers.forEach(t => {
            const cls = __classifyTypeKey(t?.type);
            if (cls === 'infant') infants += 1;
            else if (cls === 'child') children += 1;
            else adults += 1;

            // Visa Fee 계산
            const visaType = String(t.visaType || '').toLowerCase();
            if (visaType === 'group') visaFeeTotal += 1500;
            else if (visaType === 'individual') visaFeeTotal += 1900;

            // Flight Option Fee는 Extra Options 페이지에서 별도 관리 (예약 생성 시 0)
        });

        const seatRequestValue = getEditorPlainText('seat_req_editor');
        const otherRequestValue = getEditorPlainText('etc_req_editor');
        const memoValue = getEditorPlainText('memo_editor');

        // 예약 생성 데이터
        const reservationData = {
            action: 'createReservation',
            packageId: selectedPackage.packageId,
            departureDate: departureDateValueInput.value,
            departureTime: '12:20:00',
            customerInfo: customerInfo,
            travelers: travelers.map(t => ({
                type: t.type,
                title: t.title,
                firstName: t.firstName,
                lastName: t.lastName,
                gender: t.gender,
                age: t.age,
                birthDate: t.birthDate,
                contact: t.contact || '',
                email: t.contactEmail || '',
                nationality: t.nationality,
                passportNumber: t.passportNumber,
                passportIssueDate: t.passportIssueDate,
                passportExpiry: t.passportExpiry,
                visaRequired: t.visaRequired,
                visaType: t.visaType || '',
                isMainTraveler: t.isMainTraveler,
                childRoom: t.childRoom || false,
                infantSeat: t.infantSeat || false,
                profile_source: t.profile_source || '',
                remarks: t.remarks || '',
                // passportPhotoKey는 FormData 파일 필드명과 매칭(backend가 업로드 후 passportImage로 저장)
                passportPhotoKey: null,
                // visaDocumentKey는 FormData 파일 필드명과 매칭(backend가 업로드 후 visaDocument로 저장)
                visaDocumentKey: null,
                // 항공 옵션은 Extra Options 페이지에서 별도 관리
                flightOptions: {},
                flightOptionPrices: {}
            })),
            adults: adults,
            children: children,
            infants: infants,
            selectedRooms: selectedRooms,
            selectedOptions: selectedOptions,
            seatRequest: seatRequestValue,
            otherRequest: otherRequestValue,
            memo: memoValue,
            // Payment Type - 기본값 staged (Step 2에서 설정)
            paymentType: 'staged',
            // 예약 시점 단가 및 비용 정보
            adultPrice: (typeof __getUnitPrice === 'function' ? __getUnitPrice('adult') : 0) || 0,
            childPrice: (typeof __getUnitPrice === 'function' ? __getUnitPrice('child') : 0) || 0,
            infantPrice: (typeof __getUnitPrice === 'function' ? __getUnitPrice('infant') : 0) || 10000,
            visaFee: visaFeeTotal || 0,
            flightOptionFee: flightOptionFeeTotal || 0
        };

        const formData = new FormData();

        formData.append('action', 'createReservation');
        formData.append('data', JSON.stringify(reservationData));

        // 여행자 여권사진 파일 첨부 (요구사항 id 61-3, 66)
        try {
            reservationData.travelers.forEach((t, idx) => {
                const f = travelers[idx]?.passportPhotoFile || null;
                if (f) {
                    const key = `passportPhoto_${idx}`;
                    t.passportPhotoKey = key;
                    formData.append(key, f);
                }
            });
            // JSON에 passportPhotoKey 반영
            formData.set('data', JSON.stringify(reservationData));
        } catch (e) {
            console.warn('Failed to attach passport photos:', e);
        }

        // 여행자 비자 문서 파일 첨부
        try {
            reservationData.travelers.forEach((t, idx) => {
                const f = travelers[idx]?.visaDocumentFile || null;
                if (f) {
                    const key = `visaDocument_${idx}`;
                    t.visaDocumentKey = key;
                    formData.append(key, f);
                }
            });
            // JSON에 visaDocumentKey 반영
            formData.set('data', JSON.stringify(reservationData));
        } catch (e) {
            console.warn('Failed to attach visa documents:', e);
        }

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
            throw new Error(getText('reservationError'));
        }

        if (result.success) {
            // 예약 이력 업데이트
            addReservationHistory('예약 생성');

            // Step 2: 결제 정보 페이지로 이동
            const bookingId = result.data && result.data.bookingId;
            if (bookingId) {
                window.location.href = `create-reservation-payment.html?bookingId=${bookingId}`;
            } else {
                alert('Reservation created but booking ID is missing.');
                window.location.href = 'reservation-list.html';
            }
        } else {
            alert(getText('reservationFailed', { message: result.message }));
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert(getText('reservationError'));
    }
}

// 유틸리티 함수들
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US').format(Math.round(amount || 0));
}

function getEditorPlainText(editorId) {
    const editor = document.getElementById(editorId);
    if (!editor) return '';
    return editor.innerText.replace(/\u00a0/g, ' ').trim();
}

function setEditorPlainText(editorId, value) {
    const editor = document.getElementById(editorId);
    if (!editor) return;
    editor.innerHTML = value ? value.replace(/\n/g, '<br>') : '';
}

function formatFileSize(bytes) {
    if (!bytes) return '0B';
    if (bytes < 1024) return `${bytes}B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)}KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)}MB`;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
}

function formatDateForInput(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
}

function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return null;
    const birth = new Date(dateOfBirth);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
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

// 테스트 데이터 채우기
async function fillTestData() {
    try {
        console.log('테스트 데이터 채우기 시작...');
        clearDepositProofFile();
        
        // 1. DB에서 상품 정보 가져오기
        const packagesUrl = `${window.location.origin}/backend/api/packages.php?limit=10`;
        const packagesResponse = await fetch(packagesUrl, { credentials: 'same-origin' });
        const packagesText = await packagesResponse.text();
        if (!packagesResponse.ok) {
            throw new Error(`HTTP ${packagesResponse.status}: ${packagesText.substring(0, 200)}`);
        }
        let packagesResult;
        try {
            packagesResult = JSON.parse(packagesText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${packagesText.substring(0, 200)}`);
        }
        
        let testPackage = null;
        if (packagesResult.success && packagesResult.data && packagesResult.data.length > 0) {
            // 첫 번째 상품 사용
            testPackage = packagesResult.data[0];
            console.log('선택된 상품:', testPackage);
            
            // 상품 정보 설정
            selectedPackage = testPackage;
            selectedProductInModal = testPackage.packageId;
            previousPackageId = testPackage.packageId;
            document.getElementById('product_name').value = testPackage.packageName || '';
            document.getElementById('package_id').value = testPackage.packageId || '';
            
            // 여행 시작일 입력 활성화
            const departureDateInput = document.getElementById('departure_date');
            const departureDateBtn = document.getElementById('departure_date_btn');
            departureDateInput.disabled = false;
            departureDateInput.removeAttribute('readonly');
            if (departureDateBtn) {
                departureDateBtn.disabled = false;
            }
            
            // 가용 날짜 로드
            await loadAvailableDates(testPackage.packageId);
            
            // 가용 날짜 중 첫 번째 날짜 선택 (30일 후)
            const today = new Date();
            const futureDate = new Date(today);
            futureDate.setDate(futureDate.getDate() + 30);
            const dateStr = futureDate.toISOString().split('T')[0];
            
            // 날짜 선택 (가용 날짜가 있으면 첫 번째, 없으면 임의 날짜)
            if (availableDates.length > 0) {
                selectedDateInCalendar = availableDates[0];
            } else {
                selectedDateInCalendar = dateStr;
            }
            
            // 날짜 적용
            const selectedDate = new Date(selectedDateInCalendar);
            document.getElementById('departure_date').value = selectedDate.toLocaleDateString('ko-KR');
            document.getElementById('departure_date_value').value = selectedDateInCalendar;
            
            // 종료일 계산 (duration_days 또는 durationDays 사용)
            // return_date 필드는 제거되었으므로 주석 처리
            // const duration = testPackage.durationDays || testPackage.duration_days || 5;
            // const returnDate = new Date(selectedDate);
            // returnDate.setDate(returnDate.getDate() + duration - 1);
            // const returnDateInput = document.getElementById('return_date');
            // if (returnDateInput) {
            //     returnDateInput.value = returnDate.toLocaleDateString('ko-KR');
            //     returnDateInput.disabled = false;
            // }
        } else {
            alert('상품 정보를 불러올 수 없습니다. DB에 상품이 있는지 확인해주세요.');
            return;
        }
        
        // 2. DB에서 고객 정보 가져오기
        const customersUrl = `${window.location.origin}/admin/backend/api/agent-api.php?action=getCustomers&limit=10`;
        const customersResponse = await fetch(customersUrl);
        const customersText = await customersResponse.text();
        if (!customersResponse.ok) {
            throw new Error(`HTTP ${customersResponse.status}: ${customersText.substring(0, 200)}`);
        }
        let customersResult;
        try {
            customersResult = JSON.parse(customersText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${customersText.substring(0, 200)}`);
        }
        
        let testCustomer = null;
        if (customersResult.success && customersResult.data && customersResult.data.customers && customersResult.data.customers.length > 0) {
            // 첫 번째 고객 사용
            testCustomer = customersResult.data.customers[0];
            console.log('선택된 고객:', testCustomer);
            
            // 고객 상세 정보 가져오기
            const detailUrl = `${window.location.origin}/admin/backend/api/agent-api.php?action=getCustomerDetail&accountId=${encodeURIComponent(testCustomer.accountId)}`;
            const customerDetailResponse = await fetch(detailUrl);
            const detailText = await customerDetailResponse.text();
            if (!customerDetailResponse.ok) {
                throw new Error(`HTTP ${customerDetailResponse.status}: ${detailText.substring(0, 200)}`);
            }
            let customerDetailResult;
            try {
                customerDetailResult = JSON.parse(detailText);
            } catch (parseError) {
                throw new Error(`Invalid JSON response: ${detailText.substring(0, 200)}`);
            }
            
            if (customerDetailResult.success && customerDetailResult.data && customerDetailResult.data.customer) {
                const customerDetail = customerDetailResult.data.customer;
                selectedCustomer = customerDetail;
                
                // 예약 고객 정보 채우기
                document.getElementById('user_name').value = `${customerDetail.fName || ''} ${customerDetail.lName || ''}`.trim();
                document.getElementById('user_email').value = customerDetail.accountEmail || customerDetail.emailAddress || testCustomer.emailAddress || '';
                document.getElementById('user_phone').value = customerDetail.contactNo || testCustomer.contactNo || '';
                document.getElementById('country_code').value = customerDetail.countryCode || '+63';
                document.getElementById('customer_account_id').value = customerDetail.accountId || testCustomer.accountId || '';
            } else {
                // 상세 정보가 없으면 기본 정보만 사용
                document.getElementById('user_name').value = `${testCustomer.fName || ''} ${testCustomer.lName || ''}`.trim();
                document.getElementById('user_email').value = testCustomer.emailAddress || '';
                document.getElementById('user_phone').value = testCustomer.contactNo || '';
                document.getElementById('country_code').value = '+63';
                document.getElementById('customer_account_id').value = testCustomer.accountId || '';
            }
        } else {
            // 고객이 없으면 임의 값 사용
            document.getElementById('user_name').value = 'Test User';
            document.getElementById('user_email').value = 'test@example.com';
            document.getElementById('user_phone').value = '1234567890';
            document.getElementById('country_code').value = '+63';
        }
        
        // 3. 여행자 정보 추가 (3명)
        travelers = []; // 초기화
        const tbody = document.getElementById('travelers-tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }
        
        // 첫 번째 여행자 (대표 여행자) - 고객 정보 사용
        const baseMainTraveler = {
            isMainTraveler: true,
            type: 'adult',
            visaRequired: false,
            title: 'MR',
            firstName: 'John',
            lastName: 'Doe',
            gender: 'male',
            age: 30,
            birthDate: '1994-01-15',
            contact: '1234567890',
            email: 'test1@example.com',
            nationality: 'Philippines',
            passportNumber: 'P12345678',
            passportExpiry: '2028-12-31',
            remarks: 'Main traveler'
        };
        
        const firstTravelerData = testCustomer ? {
            ...baseMainTraveler,
            firstName: testCustomer.fName || baseMainTraveler.firstName,
            lastName: testCustomer.lName || baseMainTraveler.lastName,
            gender: testCustomer.gender || baseMainTraveler.gender,
            age: testCustomer.dateOfBirth ? calculateAge(testCustomer.dateOfBirth) : baseMainTraveler.age,
            birthDate: testCustomer.dateOfBirth || baseMainTraveler.birthDate,
            contact: testCustomer.contactNo || baseMainTraveler.contact,
            email: testCustomer.emailAddress || baseMainTraveler.email,
            nationality: testCustomer.nationality || baseMainTraveler.nationality,
            passportNumber: testCustomer.passportNumber || baseMainTraveler.passportNumber,
            passportExpiry: testCustomer.passportExpiry || baseMainTraveler.passportExpiry
        } : baseMainTraveler;
        
        addTravelerWithData(firstTravelerData);
        
        // 두 번째 여행자
        addTravelerWithData({
            isMainTraveler: false,
            type: 'adult',
            visaRequired: true,
            title: 'MS',
            firstName: 'Maria',
            lastName: 'Santos',
            gender: 'female',
            age: 28,
            birthDate: '1996-03-20',
            contact: '9876543210',
            email: 'maria@example.com',
            nationality: 'Philippines',
            passportNumber: 'P87654321',
            passportExpiry: '2029-06-30',
            remarks: 'Second traveler'
        });
        
        // 세 번째 여행자 (아동)
        addTravelerWithData({
            isMainTraveler: false,
            type: 'child',
            visaRequired: false,
            title: 'MR',
            firstName: 'Juan',
            lastName: 'Santos',
            gender: 'male',
            age: 8,
            birthDate: '2016-07-10',
            contact: '9876543210',
            email: 'maria@example.com',
            nationality: 'Philippines',
            passportNumber: 'P11111111',
            passportExpiry: '2027-05-15',
            remarks: 'Child traveler'
        });
        
        // 4. 예약 정보 채우기
        // 기내 수화물 추가 (opt_breakfast는 빈 옵션이 있으므로 skip)
        
        // 조식 신청
        const breakfastSelect = document.getElementById('opt_breakfast2');
        if (breakfastSelect) {
            const breakfastOption = Array.from(breakfastSelect.options).find(opt => opt.textContent.includes('신청') || opt.getAttribute('data-lan-eng') === 'Applied');
            if (breakfastOption) {
                breakfastSelect.value = breakfastOption.value || breakfastOption.textContent;
            }
        }
        
        // 와이파이 대여
        const wifiSelect = document.getElementById('opt_wifi');
        if (wifiSelect) {
            const wifiOption = Array.from(wifiSelect.options).find(opt => opt.textContent.includes('신청') || opt.getAttribute('data-lan-eng') === 'Applied');
            if (wifiOption) {
                wifiSelect.value = wifiOption.value || wifiOption.textContent;
            }
        }
        
        // 기내 수화물 추가
        const baggageSelect = document.getElementById('opt_baggage');
        if (baggageSelect) {
            const baggageOption = Array.from(baggageSelect.options).find(opt => opt.textContent.includes('20kg') || opt.getAttribute('data-lan-eng') === 'Add 20kg');
            if (baggageOption) {
                baggageSelect.value = baggageOption.value || baggageOption.textContent;
            }
        }
        
        // 항공 좌석 요청사항
        setEditorPlainText('seat_req_editor', '창가 자리 부탁드립니다.\n조용한 구역 선호합니다.');
        
        // 기타 요청사항
        setEditorPlainText('etc_req_editor', '특별 식사 요청: 할랄 식사\n공항 픽업 서비스 요청');
        
        // 메모
        setEditorPlainText('memo_editor', '테스트 예약입니다.\n고객 연락처 확인 완료.\n특별 요청사항 확인 필요.');
        
        // 5. 결제 정보 채우기
        // 총 금액 계산 (나중에 자동 계산될 예정이지만 임시로 설정)
        const basePrice = testPackage.packagePrice || 50000;
        const totalAmount = basePrice * travelers.length;
        document.getElementById('pay_total').value = formatCurrency(totalAmount);
        
        // 선금 입금 기한 (7일 후)
        const depositDueDate = new Date();
        depositDueDate.setDate(depositDueDate.getDate() + 7);
        document.getElementById('deposit_due').value = depositDueDate.toISOString().split('T')[0];
        
        // 총 금액 재계산 (선금과 잔금도 자동 계산됨)
        calculateTotalAmount();
        
        console.log('테스트 데이터 채우기 완료!');
        alert('테스트 데이터가 채워졌습니다!');
        
    } catch (error) {
        console.error('테스트 데이터 채우기 중 오류:', error);
        alert('테스트 데이터 채우기 중 오류가 발생했습니다: ' + error.message);
    }
}

// ============================================
// Full Payment 관련 함수들
// ============================================

/**
 * 결제 유형 탭 전환 (3단계 결제 / 전액 결제)
 * @param {string} paymentType - 'staged' or 'full'
 */
function switchPaymentType(paymentType) {
    selectedPaymentType = paymentType;

    // Update hidden field
    const paymentTypeInput = document.getElementById('payment_type');
    if (paymentTypeInput) paymentTypeInput.value = paymentType;

    // Update tab button states
    document.querySelectorAll('.payment-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`[data-payment-type="${paymentType}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    // Show/hide content sections
    const stagedContent = document.getElementById('staged_payment_content');
    const fullContent = document.getElementById('full_payment_content');

    if (stagedContent) stagedContent.style.display = paymentType === 'staged' ? 'block' : 'none';
    if (fullContent) fullContent.style.display = paymentType === 'full' ? 'block' : 'none';

    // Update amounts when switching to full payment
    if (paymentType === 'full') {
        updateFullPaymentAmount();
    }
}

/**
 * Full Payment 금액 업데이트 (= 총 주문 금액)
 */
function updateFullPaymentAmount() {
    const payTotalEl = document.getElementById('pay_total');
    const total = payTotalEl ? payTotalEl.value : '0';

    const fullPaymentAmountInput = document.getElementById('full_payment_amount');
    const fullPayTotalInput = document.getElementById('full_pay_total');

    if (fullPaymentAmountInput) {
        fullPaymentAmountInput.value = total;
    }
    if (fullPayTotalInput) {
        fullPayTotalInput.value = total;
    }

    // Update deadline display
    updateFullPaymentDeadline();
}

/**
 * Full Payment 마감일 계산 (예약일로부터 3일 이내)
 * @returns {string} - 마감일 문자열
 */
function calculateFullPaymentDeadline() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // 출발일 확인
    const departureDateInput = document.getElementById('departure_date_value');
    const departureDateVal = departureDateInput ? departureDateInput.value : '';
    const departureDate = departureDateVal ? new Date(departureDateVal) : null;

    // 기본: 오늘 + 3일
    const defaultDeadline = new Date(today);
    defaultDeadline.setDate(defaultDeadline.getDate() + 3);

    if (departureDate) {
        departureDate.setHours(0, 0, 0, 0);
        const daysUntilDeparture = Math.ceil((departureDate - today) / (1000 * 60 * 60 * 24));

        // 출발일이 3일 이내면 오늘까지
        if (daysUntilDeparture <= 3) {
            return formatDateForDisplay(today);
        }
    }

    return formatDateForDisplay(defaultDeadline);
}

/**
 * 날짜를 표시용 문자열로 변환
 * @param {Date} date
 * @returns {string}
 */
function formatDateForDisplay(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Full Payment 마감일 표시 업데이트
 */
function updateFullPaymentDeadline() {
    const deadlineDisplay = document.getElementById('full_payment_deadline_display');
    if (deadlineDisplay) {
        const deadline = calculateFullPaymentDeadline();
        deadlineDisplay.textContent = deadline;
    }
}

/**
 * Full Payment 파일 데이터 초기화
 */
function clearFullPaymentData() {
    fullPaymentFile = null;
    const fileInfo = document.getElementById('full_payment_file_info');
    const fileInput = document.getElementById('full_payment_file_input');
    if (fileInfo) fileInfo.style.display = 'none';
    if (fileInput) fileInput.value = '';
}

/**
 * Full Payment 파일 업로드 초기화
 */
function initializeFullPaymentFileUpload() {
    initializePaymentFileUpload(
        'full_payment_file_btn',
        'full_payment_file_input',
        'full_payment_file_remove',
        'full_payment_file_info',
        'full_payment_file_name',
        (file) => { fullPaymentFile = file; },
        () => { fullPaymentFile = null; }
    );
}
