// 예약 인원 선택 페이지 기능

// i18n policy: default EN, only EN/TL supported
const i18nTexts = {
    'en': {
        invalidAccess: 'Invalid access.',
        loadingPackage: 'Loading package information...',
        minGuestsRequired: 'Please select guests.',
        loginRequired: 'Login is required. Would you like to go to the login page?',
        validationError: 'An error occurred during booking validation:',
        adult: 'Adult',
        child: 'Child',
        infant: 'Infant',
        departure: 'Departure',
        maxGuestsReached: 'You can select up to 10 guests in total.',
        outOfStock: 'Out of stock.',
        stockCheckFailed: 'Failed to check availability. Please try again.',
        confirm: 'OK'
    },
    'tl': {
        invalidAccess: 'Hindi wastong pag-access.',
        loadingPackage: 'Naglo-load ng impormasyon ng package...',
        minGuestsRequired: 'Pumili ng mga bisita.',
        loginRequired: 'Kailangan ng login. Gusto mo bang pumunta sa login page?',
        validationError: 'May error na naganap sa pag-validate ng booking:',
        adult: 'Matanda',
        child: 'Bata',
        infant: 'Sanggol',
        departure: 'Alis',
        maxGuestsReached: 'Maaari kang pumili ng hanggang 10 na bisita sa kabuuan.',
        outOfStock: 'Walang sapat na stock.',
        stockCheckFailed: 'Hindi ma-check ang availability. Subukang muli.',
        confirm: 'OK'
    }
};

// 현재 언어에 따른 텍스트 가져오기
function getI18nText(key) {
    const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
    const currentLang = (cur === 'tl') ? 'tl' : 'en';
    const texts = i18nTexts[currentLang] || i18nTexts['en'];
    return texts[key] || key;
}

// dev_tasks #116: 세션/로컬스토리지 로그인 상태가 어긋나는 케이스가 있어
// 예약 플로우에서는 서버 세션(check-session.php)을 기준으로 localStorage를 동기화한다.
async function syncAuthFromSession() {
    try {
        const r = await fetch('../backend/api/check-session.php', { credentials: 'include' });
        const j = await r.json().catch(() => ({}));
        const ok = !!(j && j.success && j.isLoggedIn && j.user && j.user.id);
        if (ok) {
            try { localStorage.setItem('isLoggedIn', 'true'); } catch (_) {}
            try { localStorage.setItem('userId', String(j.user.id)); } catch (_) {}
            try { localStorage.setItem('userEmail', String(j.user.email || '')); } catch (_) {}
            try { localStorage.setItem('accountType', String(j.user.accountType || j.user.account_type || '')); } catch (_) {}
            try { localStorage.setItem('username', String(j.user.displayName || j.user.username || '')); } catch (_) {}
            // B2B/B2C hints
            try {
                if (j.user.isB2B === true) {
                    localStorage.setItem('clientType', 'wholeseller');
                }
            } catch (_) {}
            return true;
        }
        return false;
    } catch (_) {
        return false;
    }
}

// B2B 사용자 여부 확인 (agent/admin_ph/admin_kr = B2B, 나머지 = B2C)
function isB2BUser() {
    const accountType = String(localStorage.getItem('accountType') || '').toLowerCase();
    const clientType = String(localStorage.getItem('clientType') || '').toLowerCase();
    // agent, admin_ph, admin_kr 또는 wholeseller 타입은 B2B
    return accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr' || clientType === 'wholeseller';
}

let bookingInfo = {
    packageId: null,
    flightId: null,
    departureDate: null,
    departureTime: '',
    packageName: '',
    // guestOptions: [{ name, unitPrice, qty }]
    guestOptions: [],
    totalAmount: 0,
    bookingId: null,
    priceTier: 'B2C' // B2B or B2C - determined by user type
};

let countersInitialized = false; // 카운터 초기화 플래그

function normalizeHHMM(value) {
    const s = String(value || '').trim();
    if (!s) return '';
    const m = s.match(/(\d{1,2}):(\d{2})/);
    if (!m) return '';
    return `${String(m[1]).padStart(2, '0')}:${m[2]}`;
}

function showStockPopup(message) {
    const layer = document.getElementById('stockLayer');
    const popup = document.getElementById('stockPopup');
    const msgEl = document.getElementById('stockPopupMsg');
    if (!layer || !popup) {
        alert(message || getI18nText('outOfStock'));
        return;
    }
    if (msgEl) msgEl.textContent = String(message || getI18nText('outOfStock'));
    layer.classList.add('active');
    popup.style.display = 'flex';
}

function hideStockPopup() {
    const layer = document.getElementById('stockLayer');
    const popup = document.getElementById('stockPopup');
    if (layer) layer.classList.remove('active');
    if (popup) popup.style.display = 'none';
}

function toIntOr0(v) {
    const n = parseInt(v, 10);
    return Number.isFinite(n) ? n : 0;
}

async function checkInventoryOrPopup() {
    const packageId = bookingInfo.packageId;
    const departureDate = bookingInfo.departureDate;
    // "선택 수량의 합" 기준 (DB 인원 옵션 선택 합)
    const requested = (bookingInfo.guestOptions || []).reduce((acc, o) => acc + Number(o?.qty || 0), 0);
    if (!packageId || !departureDate || requested <= 0) return true;

    try {
        const url = `../backend/api/booking_status.php?packageId=${encodeURIComponent(packageId)}&departureDate=${encodeURIComponent(departureDate)}`;
        const res = await fetch(url, { credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json || !json.success) {
            showStockPopup(getI18nText('stockCheckFailed'));
            return false;
        }
        const remaining = Number(json.remainingSeats ?? 0);
        if (remaining < requested) {
            showStockPopup(getI18nText('outOfStock'));
            return false;
        }
        return true;
    } catch (e) {
        showStockPopup(getI18nText('stockCheckFailed'));
        return false;
    }
}

function normalizeGuestOptionsFromPackageData(packageData) {
    const opts = Array.isArray(packageData?.pricingOptions) ? packageData.pricingOptions : [];
    const out = [];
    const useB2B = isB2BUser();

    for (const o of opts) {
        const name = String(o?.optionName || '').trim();
        // B2B 사용자면 B2B 가격 사용, 없으면 기본 가격 fallback
        const b2cPrice = Number(o?.price ?? o?.optionPrice ?? NaN);
        const b2bPrice = Number(o?.b2bPrice ?? o?.b2b_price ?? NaN);
        const unitPrice = useB2B && Number.isFinite(b2bPrice) ? b2bPrice : b2cPrice;
        if (!name || !Number.isFinite(unitPrice)) continue;
        out.push({ name, unitPrice, qty: 0 });
    }
    // fallback: pricingOptions가 없으면 packagePrice 기반 1행만 노출
    if (!out.length) {
        // B2B 사용자면 B2B 가격 사용
        const b2cPrice = Number(packageData?.packagePrice ?? 0);
        const b2bPrice = Number(packageData?.b2bPrice ?? packageData?.b2b_price ?? NaN);
        const p = useB2B && Number.isFinite(b2bPrice) ? b2bPrice : b2cPrice;
        if (Number.isFinite(p) && p > 0) {
            out.push({ name: getI18nText('adult'), unitPrice: p, qty: 0 });
        }
    }
    return out;
}

function normalizeSavedGuestOptions(value) {
    const arr = Array.isArray(value) ? value : [];
    return arr
        .map((x) => ({
            name: String(x?.name || x?.optionName || '').trim(),
            unitPrice: Number(x?.unitPrice ?? x?.price ?? NaN),
            qty: Math.max(0, parseInt(x?.qty ?? x?.quantity ?? 0, 10) || 0),
        }))
        .filter((x) => x.name && Number.isFinite(x.unitPrice));
}

function mergeGuestOptionsByName(baseOptions, savedOptions) {
    const base = Array.isArray(baseOptions) ? baseOptions : [];
    const saved = normalizeSavedGuestOptions(savedOptions);
    if (!saved.length) return base;
    const byName = new Map(saved.map((x) => [x.name, x]));
    return base.map((b) => {
        const hit = byName.get(b.name);
        return hit ? { ...b, qty: hit.qty } : b;
    });
}

function ensureToastContainer() {
    let c = document.getElementById('toast-container');
    if (c) return c;
    c = document.createElement('div');
    c.id = 'toast-container';
    c.style.cssText = 'position:fixed;left:0;right:0;bottom:20px;display:none;z-index:10001;pointer-events:none;';
    document.body.appendChild(c);
    return c;
}

function showToast(message, type = 'info') {
    const container = ensureToastContainer();
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = String(message || '');
    toast.style.cssText = 'max-width:calc(100% - 40px);margin:0 auto 10px auto;padding:12px 14px;border-radius:10px;color:#fff;font-size:14px;line-height:1.35;opacity:1;transition:opacity .25s;';
    if (type === 'error') toast.style.background = 'rgba(237, 27, 35, 0.92)';
    else if (type === 'success') toast.style.background = 'rgba(0, 128, 0, 0.9)';
    else toast.style.background = 'rgba(0, 0, 0, 0.75)';

    container.appendChild(toast);
    container.style.display = 'block';

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
            if (container.children.length === 0) container.style.display = 'none';
        }, 250);
    }, 2500);
}

function renderGuestOptions() {
    const list = document.getElementById('guestOptionsList');
    if (!list) return;
    const fmt = (n) => `₱${new Intl.NumberFormat('en-US').format(Number(n || 0))}`;
    list.innerHTML = (bookingInfo.guestOptions || []).map((o, idx) => `
        <li class="align vm both ${idx > 0 ? 'mt20' : ''} guest-row" data-idx="${idx}">
            <div>
                <p class="text fz14 fw500 lh22">${escapeHtml(o.name)}</p>
                <strong class="text fz14 fw600 lh24">${fmt(o.unitPrice)}</strong>
            </div>
            <div class="counter" data-idx="${idx}">
                <button class="btn-minus" type="button"><img src="../images/ico_minus.svg" alt=""></button>
                <p class="count-value">${Number(o.qty || 0)}</p>
                <button class="btn-plus" type="button"><img src="../images/ico_plus.svg" alt=""></button>
            </div>
        </li>
    `).join('');
    setupGuestCounters();
}

function escapeHtml(s) {
    return String(s || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function setupGuestCounters() {
    const counters = document.querySelectorAll('#guestOptionsList .counter');
    counters.forEach((counter) => {
        const idx = parseInt(counter.dataset.idx, 10);
        const minusBtn = counter.querySelector('.btn-minus');
        const plusBtn = counter.querySelector('.btn-plus');
        const countValue = counter.querySelector('.count-value');
        if (!Number.isFinite(idx) || !minusBtn || !plusBtn || !countValue) return;

        const onChange = (delta) => {
            const opt = bookingInfo.guestOptions?.[idx];
            if (!opt) return;
            opt.qty = Math.max(0, Number(opt.qty || 0) + delta);
            countValue.textContent = String(opt.qty);
            updateGuestSummaryAndTotal();
        };
        minusBtn.onclick = () => onChange(-1);
        plusBtn.onclick = () => onChange(+1);
    });
}

function updateGuestSummaryAndTotal() {
    const summary = document.getElementById('guestSummaryList');
    const totalEl = document.querySelector('.total-amount');
    const nextBtn = document.querySelector('.btn.primary.lg, .next-btn');
    const fmt = (n) => `₱${new Intl.NumberFormat('en-US').format(Number(n || 0))}`;

    const selected = (bookingInfo.guestOptions || []).filter((o) => Number(o.qty || 0) > 0);
    const total = selected.reduce((acc, o) => acc + (Number(o.unitPrice || 0) * Number(o.qty || 0)), 0);
    bookingInfo.totalAmount = total;

    if (summary) {
        summary.innerHTML = selected.map((o, idx) => `
            <div class="align both vm ${idx > 0 ? 'mt8' : ''}">
                <p class="text fz14 fw400 lh22 black12">${escapeHtml(o.name)}x${Number(o.qty || 0)}</p>
                <span class="text fz14 fw400 lh22 black12">${fmt(Number(o.unitPrice || 0) * Number(o.qty || 0))}</span>
            </div>
        `).join('');
    }
    if (totalEl) totalEl.textContent = fmt(total);

    const totalGuests = selected.reduce((acc, o) => acc + Number(o.qty || 0), 0);
    if (nextBtn) {
        if (totalGuests > 0) {
            nextBtn.classList.remove('inactive');
            nextBtn.disabled = false;
        } else {
            nextBtn.classList.add('inactive');
            nextBtn.disabled = true;
        }
    }
}

// 페이지 로드 시 예약 정보 초기화
async function initializeBookingInfo() {
    const urlParams = new URLSearchParams(window.location.search);

    // URL 파라미터에서 undefined 문자열 처리
    const getValidParam = (param) => {
        const value = urlParams.get(param);
        return (value && value !== 'undefined' && value !== 'null') ? value : null;
    };

    // SMT 수정 시작 - PHP에서 전달받은 데이터 또는 booking_id로 기존 예약 정보 불러오기
    const phpData = window.phpBookingData || {};
    const existingBookingId = getValidParam('booking_id') || phpData.bookingId;
    const hasPhpData = phpData.dbLoadSuccess || (phpData.packageId && phpData.departureDate);

    if (hasPhpData) {
        bookingInfo.bookingId = phpData.bookingId || null;
        bookingInfo.packageId = phpData.packageId;
        bookingInfo.departureDate = phpData.departureDate;
        bookingInfo.departureTime = phpData.departureTime || '';
        bookingInfo.packageName = phpData.packageName || '';
    }

    // booking_id가 있고 PHP 데이터가 없으면 API로 조회 (수정/복귀 흐름)
    if (existingBookingId && !hasPhpData) {
        try {
            const response = await fetch('../backend/api/save-temp-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'get_by_id', bookingId: existingBookingId })
            });
            const result = await response.json();
            if (result.success && result.data) {
                const booking = result.data;
                bookingInfo.bookingId = booking.bookingId;
                bookingInfo.packageId = booking.packageId || '1';
                bookingInfo.departureDate = booking.departureDate;
                bookingInfo.departureTime = booking.departureTime || '';
                bookingInfo.packageName = booking.packageName || '';
                bookingInfo.totalAmount = parseFloat(booking.totalAmount) || 0;

                // selectedOptions(JSON)에 저장된 guestOptions 복원
                try {
                    const soRaw = booking.selectedOptions;
                    if (soRaw && typeof soRaw === 'string') {
                        const so = JSON.parse(soRaw);
                        if (so && Array.isArray(so.guestOptions)) {
                            bookingInfo.guestOptions = normalizeSavedGuestOptions(so.guestOptions);
                        }
                    }
                } catch (_) { }
            }
        } catch (error) {
            // 오류 시 기본값 사용
        }
    }
    // SMT 수정 완료

    // 위에서 로드하지 못한 경우 URL 파라미터 사용
    if (!bookingInfo.packageId) {
        bookingInfo.packageId = getValidParam('package_id') || '1';
    }
    if (!bookingInfo.departureDate) {
        bookingInfo.departureDate = getValidParam('departure_date');
    }
    if (!bookingInfo.departureTime) {
        bookingInfo.departureTime = getValidParam('departure_time') || '';
    }
    bookingInfo.flightId = getValidParam('flight_id') || bookingInfo.flightId;
    if (!bookingInfo.packageName) {
        bookingInfo.packageName = getValidParam('package_name') || '';
    }

    if (!bookingInfo.packageId || !bookingInfo.departureDate) {
        alert(getI18nText('invalidAccess'));
        history.back();
        return false;
    }

    // B2B/B2C 가격 티어 설정
    bookingInfo.priceTier = isB2BUser() ? 'B2B' : 'B2C';

    // 데이터베이스에서 패키지 정보 가져오기
    try {
        // 로딩 상태 표시
        showLoadingState();
        
        const response = await fetch(`../backend/api/packages.php?id=${encodeURIComponent(bookingInfo.packageId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        
        if (result.success && result.data) {
            const baseGuestOptions = normalizeGuestOptionsFromPackageData(result.data);
            // meeting time(항공편 미포함 상품에 사용) - 컬럼/버전별 호환
            const mtRaw = result.data.meeting_time || result.data.meetingTime || result.data.meeting_datetime || '';
            const mt = normalizeHHMM(mtRaw);
            // DB 옵션명/가격 리스트를 그대로 사용 (고정 adults/children/infants 사용 안함)
            bookingInfo.guestOptions = mergeGuestOptionsByName(baseGuestOptions, bookingInfo.guestOptions);
            bookingInfo.packageName = result.data.packageName || bookingInfo.packageName;
            if (mt) bookingInfo.meetingTime = mt;
        } else {
            console.error('패키지 정보 로드 실패:', result.message);
            bookingInfo.guestOptions = [];
        }
    } catch (error) {
        console.error('패키지 정보 로드 오류:', error);
        bookingInfo.guestOptions = [];
    } finally {
        // 로딩 상태 해제
        hideLoadingState();
    }

    // 선택한 출발일 기준(일자별 판매조정/항공요금) 반영
    try {
        const dep = String(bookingInfo.departureDate || '').trim();
        if (dep && /^\d{4}-\d{2}-\d{2}$/.test(dep)) {
            const year = dep.slice(0, 4);
            const month = String(parseInt(dep.slice(5, 7), 10));
            const avRes = await fetch(`../backend/api/product_availability.php?id=${encodeURIComponent(bookingInfo.packageId)}&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`, { credentials: 'same-origin' });
            const avJson = await avRes.json().catch(() => ({}));
            const items = avJson?.data?.availability || [];
            const hit = Array.isArray(items) ? items.find(x => String(x.availableDate || '') === dep) : null;
            if (hit) {
                // flightId는 이후 단계(API/정산)에서 필요할 수 있어 보관
                if (hit.flightId) bookingInfo.flightId = hit.flightId;

                // B2B 사용자면 B2B 가격 사용 (날짜별 가격 적용)
                const useB2B = isB2BUser();

                // 날짜별 성인/아동/유아 가격 (B2B 또는 B2C)
                const dateAdultPrice = useB2B && hit.b2bPrice != null ? Number(hit.b2bPrice) : Number(hit.price ?? 0);
                const dateChildPrice = useB2B && hit.b2bChildPrice != null ? Number(hit.b2bChildPrice) : (hit.childPrice != null ? Number(hit.childPrice) : null);
                const dateInfantPrice = useB2B && hit.b2bInfantPrice != null ? Number(hit.b2bInfantPrice) : (hit.infantPrice != null ? Number(hit.infantPrice) : null);

                // 항공편이 있는 상품: 인원 옵션(land) + 해당 일자 항공요금(flightPrice)을 각 옵션 단가에 가산
                const flightFare = Number(hit.flightPrice ?? 0);
                const hasFlight = !!hit.flightId || flightFare > 0;
                if (hasFlight) {
                    // 항공편 포함 상품: 출국일 항공편 출발 시각을 사용(가능한 경우)
                    const dt = normalizeHHMM(hit.departureTime || '');
                    if (dt) bookingInfo.departureTime = dt;
                    // 모든 인원 옵션 단가에 항공요금 가산
                    if (flightFare > 0 && Array.isArray(bookingInfo.guestOptions)) {
                        bookingInfo.guestOptions = bookingInfo.guestOptions.map((o) => ({
                            ...o,
                            unitPrice: Number(o.unitPrice || 0) + flightFare,
                        }));
                    }
                } else {
                    // 항공편 미포함 상품: 미팅 시각을 출발 시각으로 사용
                    const mt = normalizeHHMM(bookingInfo.meetingTime || '');
                    if (mt) bookingInfo.departureTime = mt;
                }

                // 날짜별 가격이 있으면 guestOptions 단가를 덮어쓰기
                if (dateAdultPrice > 0 && Array.isArray(bookingInfo.guestOptions)) {
                    bookingInfo.guestOptions = bookingInfo.guestOptions.map((o) => {
                        const nameLower = String(o.name || '').toLowerCase();
                        // 이름에 따라 적절한 가격 적용
                        if (nameLower.includes('adult') || nameLower.includes('matanda') || nameLower === getI18nText('adult').toLowerCase()) {
                            return { ...o, unitPrice: dateAdultPrice };
                        } else if ((nameLower.includes('child') || nameLower.includes('bata') || nameLower === getI18nText('child').toLowerCase()) && dateChildPrice != null) {
                            return { ...o, unitPrice: dateChildPrice };
                        } else if ((nameLower.includes('infant') || nameLower.includes('sanggol') || nameLower === getI18nText('infant').toLowerCase()) && dateInfantPrice != null) {
                            return { ...o, unitPrice: dateInfantPrice };
                        }
                        return o;
                    });
                }
            }
        }
    } catch (e) {
        // ignore - fallback은 packages.php 가격 사용
    }
    
    // 기존 pending 예약 자동 로드는 요구사항 위반 가능:
    // - 신규 진입 시 "기본값: 모든 인원 0" 이어야 함
    // 따라서 booking_id로 들어온 "수정/복귀" 흐름에서만 pending 복원
    const isEditMode = !!(bookingInfo.bookingId || existingBookingId || phpData.dbLoadSuccess);
    if (isEditMode) {
        await loadExistingPendingBooking();
    } else {
        // 신규 진입은 항상 0부터 시작
        if (Array.isArray(bookingInfo.guestOptions)) {
            bookingInfo.guestOptions = bookingInfo.guestOptions.map((o) => ({ ...o, qty: 0 }));
        }
    }
    
    // 페이지 정보 업데이트
    updatePageInfo();
    updatePricing();
    
    return true;
}

// 기존 pending 예약 정보 로드
async function loadExistingPendingBooking() {
    try {
        const userId = localStorage.getItem('userId');
        if (!userId || userId === '0') {
            console.log('로그인하지 않아서 기존 예약 조회 건너뜀');
            return; // 로그인하지 않은 경우는 건너뜀
        }
        
        console.log('기존 예약 조회 시작:', { userId, packageId: bookingInfo.packageId, departureDate: bookingInfo.departureDate });
        
        const response = await fetch('../backend/api/save-temp-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_pending',
                userId: userId,
                packageId: bookingInfo.packageId,
                departureDate: bookingInfo.departureDate
            })
        });
        
        console.log('기존 예약 조회 응답 상태:', response.status);
        
        if (response.ok) {
            const result = await response.json();
            console.log('기존 예약 조회 결과:', result);
            
            if (result.success && result.booking) {
                // 기존 예약의 guestOptions(selectedOptions JSON) 복원
                bookingInfo.bookingId = result.booking.bookingId;
                bookingInfo.totalAmount = parseFloat(result.booking.totalAmount) || 0;
                try {
                    const soRaw = result.booking.selectedOptions;
                    if (soRaw && typeof soRaw === 'string') {
                        const so = JSON.parse(soRaw);
                        if (so && Array.isArray(so.guestOptions)) {
                            bookingInfo.guestOptions = mergeGuestOptionsByName(
                                bookingInfo.guestOptions,
                                so.guestOptions
                            );
                        }
                    }
                } catch (_) { }

                console.log('✅ 기존 예약 정보 로드 완료:', {
                    bookingId: bookingInfo.bookingId,
                    guestOptionsCount: Array.isArray(bookingInfo.guestOptions) ? bookingInfo.guestOptions.length : 0
                });
            } else {
                console.log('기존 예약 없음');
            }
        } else {
            const errorText = await response.text();
            console.error('기존 예약 조회 실패:', response.status, errorText);
        }
    } catch (error) {
        console.error('기존 예약 정보 로드 오류:', error);
        // 에러가 발생해도 계속 진행 (기본값 사용)
    }
}

// 페이지 정보 업데이트
function updatePageInfo() {
    // 출발 정보는 PHP에서 이미 포맷되어 표시되므로 업데이트하지 않음
    // (다국어 지원을 위해 PHP의 formatDate 함수 사용)
    
    // 패키지명 표시 (필요시)
    const packageTitle = document.querySelector('.package-title');
    if (packageTitle) {
        packageTitle.textContent = bookingInfo.packageName;
    }
}

// 가격/요약 영역 업데이트 (DB pricingOptions 기반: optionName/price/qty만 사용)
function updatePricing() {
    renderGuestOptions();
    updateGuestSummaryAndTotal();
}

// legacy 이름 유지 (기존 호출부 호환)
function calculateTotalAmount() {
    updateGuestSummaryAndTotal();
}

// legacy 이름 유지 (기존 호출부 호환)
// 실제 카운터 바인딩은 renderGuestOptions() 내부에서 처리
function setupCounters() {
    updatePricing();
}

// 기존 예약 정보를 카운터에 반영
function setCounterValues() {
    // 고정 카운터(adults/children/infants) 사용 안함: 동적 렌더링만 갱신
    updatePricing();
}

// 예약 인원 수 업데이트
function updateBookingCount() {
    // 고정 카운터(adults/children/infants) 사용 안함
    updateGuestSummaryAndTotal();
}

// 다음 단계로 진행
async function proceedToNextStep() {
    const totalGuests = (bookingInfo.guestOptions || []).reduce((acc, o) => acc + Number(o?.qty || 0), 0);
    if (totalGuests <= 0) {
        showToast(getI18nText('minGuestsRequired'), 'error');
        return;
    }
    
    // Login check: rely on server session only (prevents stale localStorage from bypassing auth)
    const isLoggedIn = await syncAuthFromSession();
    if (!isLoggedIn) {
        if (confirm(getI18nText('loginRequired'))) {
            // 현재 예약 정보를 임시 저장
            sessionStorage.setItem('tempBookingInfo', JSON.stringify(bookingInfo));
            // auth-guard.js와 동일 키로 복귀 URL 저장(로그인 후 예약 플로우로 복귀)
            sessionStorage.setItem('redirectAfterLogin', window.location.href);
            location.href = 'login.html';
        }
        return;
    }

    // 재고 확인: 선택 인원 합이 잔여 재고보다 크면 차단 + 팝업
    const okStock = await checkInventoryOrPopup();
    if (!okStock) return;
    
    // 총 금액 재계산
    calculateTotalAmount();
    
    // 임시 예약 저장 및 예약번호 생성
    const bookingId = await saveTempBooking();
    
    if (bookingId) {
        // SMT: allow the "Temporarily saved" toast to be visible before navigation (E2E & UX)
        await new Promise((r) => setTimeout(r, 800));
        // 예약번호가 있으면 booking_id만 URL에 포함
        const params = new URLSearchParams();
        params.set('booking_id', bookingId);
        location.href = `select-room.php?${params.toString()}`;
    } else {
        // SMT: allow toast visibility before navigation (even when falling back)
        await new Promise((r) => setTimeout(r, 800));
        // 저장 실패 시 최소한의 정보만 전달 (호환성 유지)
        const params = new URLSearchParams();
        params.set('package_id', bookingInfo.packageId);
        params.set('departure_date', bookingInfo.departureDate);
        if (bookingInfo.departureTime) {
            params.set('departure_time', bookingInfo.departureTime);
        }
        params.set('total_amount', bookingInfo.totalAmount.toString());
        // 저장 실패 시에도 다음 단계에서 복원할 수 있게 세션에 저장
        try { sessionStorage.setItem('tempGuestOptions', JSON.stringify(bookingInfo.guestOptions || [])); } catch (_) {}
        location.href = `select-room.php?${params.toString()}`;
    }
}

// 진행 단계 표시 업데이트
function updateProgressBar() {
    const progressItems = document.querySelectorAll('.progress-type1 i');
    if (progressItems.length > 0) {
        // 첫 번째 단계 활성화
        progressItems[0].classList.add('active');
        
        // 나머지는 비활성화
        for (let i = 1; i < progressItems.length; i++) {
            progressItems[i].classList.remove('active');
        }
    }
}

// 로딩 상태 표시
function showLoadingState() {
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay';
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="loading-spinner"></div>
        <div style="margin-top: 20px; text-align: center; color: #666;">${getI18nText('loadingPackage')}</div>
    `;
    loadingOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    document.body.appendChild(loadingOverlay);
}

// 로딩 상태 해제
function hideLoadingState() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

// 중앙 토스트 메시지 표시
function showCenterToast(message) {
    // 기존 토스트 제거
    const existingToast = document.querySelector('.toast-center');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = 'toast-center';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // 2초 후 자동 제거
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// 임시 저장 함수
    async function saveTempBooking() {
        try {
            const userId = localStorage.getItem('userId') || null;
            const totalGuests = (bookingInfo.guestOptions || []).reduce((acc, o) => acc + Number(o?.qty || 0), 0);
            const saveData = {
                userId: userId,
                bookingId: bookingInfo.bookingId || null,
                packageId: bookingInfo.packageId,
                departureDate: bookingInfo.departureDate,
                departureTime: bookingInfo.departureTime || '',
                packageName: bookingInfo.packageName,
                // legacy columns: keep total guests in adults (children/infants=0) for compatibility
                adults: totalGuests,
                children: 0,
                infants: 0,
                totalAmount: bookingInfo.totalAmount,
                // dynamic guest options (authoritative)
                guestOptions: bookingInfo.guestOptions || [],
                // B2B/B2C 가격 티어
                priceTier: bookingInfo.priceTier || 'B2C'
            };
        
        const response = await fetch('../backend/api/save-temp-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        // 응답 상태 확인
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server error (${response.status}): ${errorText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            // 반환된 bookingId를 bookingInfo에 저장
            if (result.bookingId) {
                bookingInfo.bookingId = result.bookingId;
                bookingInfo.tempId = result.bookingId;
                localStorage.setItem('currentBooking', JSON.stringify(bookingInfo));
            }
            
            // Language policy: en default, only en/tl
            const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
            const currentLang = (cur === 'tl') ? 'tl' : 'en';
            let message = (currentLang === 'tl') ? 'Naka-save na pansamantala' : 'Temporarily saved';
            showCenterToast(message);
            return result.bookingId || null;
        } else {
            return null;
        }
    } catch (error) {
        return null;
    }
}

// 임시 저장된 예약 정보 복원
function restoreTempBookingInfo() {
    const tempInfo = sessionStorage.getItem('tempBookingInfo');
    if (tempInfo) {
        try {
            const savedInfo = JSON.parse(tempInfo);
            bookingInfo = { ...bookingInfo, ...savedInfo };
            sessionStorage.removeItem('tempBookingInfo');
            
            // URL 파라미터도 업데이트
            const params = new URLSearchParams();
            Object.keys(bookingInfo).forEach(key => {
                if (bookingInfo[key] !== null && bookingInfo[key] !== '') {
                    params.set(key.replace(/([A-Z])/g, '_$1').toLowerCase(), bookingInfo[key]);
                }
            });
            
            history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
        } catch (error) {
            console.error('Failed to restore temp booking info:', error);
        }
    }
}

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', async function() {
    // 뒤로가기 버튼 처리 (select-reservation.php 전용)
    // product-detail.js uses location.replace() when navigating here,
    // so history.back() will go to the page before product-detail (e.g., home.html)
    const backButton = document.querySelector('.btn-mypage');
    if (backButton && window.location.pathname.includes('select-reservation.php')) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // button.js의 이벤트 전파 방지
            history.back();
        });
    }
    
    if (window.location.pathname.includes('select-reservation.php')) {
        // 임시 저장된 정보 복원
        restoreTempBookingInfo();
        
        // 예약 정보 초기화 (async 함수로 변경)
        if (await initializeBookingInfo()) {
            // loadExistingPendingBooking에서 기존 예약 정보를 불러왔으면 그 값을 사용하고,
            // 없으면 initializeBookingInfo에서 세팅된 값(기본 0)을 그대로 사용한다.
            // (요구사항: 예약 인원 기본값은 0/0/0)
            
            // setupCounters()는 bookingInfo 값을 사용하여 초기화
            // loadExistingPendingBooking()에서 이미 bookingInfo를 업데이트했으므로 그 값이 사용됨
            setupCounters();
            
            // DOM 렌더링 완료 후 카운터 값 확실하게 반영
            setTimeout(() => {
                console.log('setCounterValues 호출 전 bookingInfo:', bookingInfo);
                setCounterValues();
                updatePricing(); // 가격 정보도 업데이트
            }, 200); // DOM 렌더링 대기
            
            updateProgressBar();
            
            // 다음 단계 버튼 이벤트
            const nextBtn = document.querySelector('.btn.primary.lg, .next-btn');
            if (nextBtn) {
                nextBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    await proceedToNextStep();
                });
            }

            // 재고 팝업 닫기 바인딩
            const okBtn = document.getElementById('stockPopupOkBtn');
            const layer = document.getElementById('stockLayer');
            okBtn?.addEventListener('click', (e) => { e.preventDefault(); hideStockPopup(); });
            layer?.addEventListener('click', () => hideStockPopup());
        }
    }
});

// CSS 스타일 추가
(function addSelectReservationStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .counter {
            user-select: none;
        }
        
        .counter button {
            cursor: pointer;
            border: none;
            background: none;
            // padding: 8px;
        }
        
        .counter button:hover {
            opacity: 0.7;
        }
        
        .counter button:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .count-value {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }
        
        .progress-type1 i.active {
            background-color: #ED1B23 !important;
            background: var(--reded) !important;
        }
    `;
    document.head.appendChild(style);
})();