// 마이페이지 동적 기능

let userProfile = null;
let recentBooking = null;

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('mypage.html')) {
        initializeMyPage();
        loadAndRenderMypageCategories();
    }
});

// 마이페이지 초기화
async function initializeMyPage() {
    // Back button: absolute path + preserve lang (dev_tasks #167)
    try {
        const url = new URL(window.location.href);
        const lang = url.searchParams.get('lang') || localStorage.getItem('selectedLanguage') || 'en';
        const back = document.querySelector('a.btn-mypage[href]');
        if (back) {
            const href = back.getAttribute('href') || '';
            // mypage.html header back should always go to home with lang
            if (href.includes('home.html')) {
                const homeUrl = new URL(href, window.location.href);
                if (!homeUrl.searchParams.get('lang')) homeUrl.searchParams.set('lang', lang);
                back.setAttribute('href', homeUrl.pathname + '?' + homeUrl.searchParams.toString());
            }
        }
    } catch (_) {}

    console.log('마이페이지 초기화 시작');
    
    // 다국어 텍스트 로드
    await loadServerTexts();
    
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    console.log('로그인 상태:', isLoggedIn);
    
    if (isLoggedIn) {
        // 사용자 데이터 로드
        await loadUserData();
        showLoggedInView();
    } else {
        showLoggedOutView();
    }
}

// 사용자 데이터 로드
async function loadUserData() {
    try {
        const userId = localStorage.getItem('userId');
        if (!userId) {
            console.log('사용자 ID가 없습니다.');
            return;
        }

        // 사용자 프로필 로드
        const profileResponse = await fetch('../backend/api/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_profile',
                accountId: userId
            })
        });
        
        const profileResult = await profileResponse.json();
        
        if (profileResult.success) {
            userProfile = profileResult.profile;
            console.log('사용자 프로필 로드 완료:', userProfile);
        } else {
            console.log('프로필 로드 실패:', profileResult.message);
        }

        // 사용자 예약 내역 로드
        const response = await fetch(`../backend/api/user_bookings.php?accountId=${encodeURIComponent(userId)}`);
        const result = await response.json();
        
        if (result.success && result.data.bookings && result.data.bookings.length > 0) {
            // "최근 예약"은 생성일(createdAt) 기준.
            // 단, 임시저장(bookingStatus=pending)은 "최근 이용내역"에서 우선 제외(요구사항: 완료건이 있는데 임시저장건이 먼저 보이는 문제)
            const bookings = Array.isArray(result.data.bookings) ? result.data.bookings : [];
            const toTime = (b) => {
                // NOTE: MySQL DATETIME("YYYY-MM-DD HH:MM:SS")는 브라우저(Date.parse)에서 NaN이 날 수 있어 보정한다.
                const pick = (obj, keys) => {
                    for (const k of keys) {
                        const v = obj?.[k];
                        if (v !== undefined && v !== null && String(v).trim() !== '') return String(v).trim();
                    }
                    return '';
                };
                const parseAny = (s) => {
                    if (!s) return 0;
                    let t = Date.parse(s);
                    if (!Number.isNaN(t)) return t;
                    // MySQL DATETIME: 2025-12-27 07:10:10 -> 2025-12-27T07:10:10
                    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/.test(s)) {
                        t = Date.parse(s.replace(' ', 'T'));
                        if (!Number.isNaN(t)) return t;
                    }
                    // Date only: 2025-12-27
                    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
                        t = Date.parse(s + 'T00:00:00');
                        if (!Number.isNaN(t)) return t;
                    }
                    return 0;
                };

                // 최근성은 updatedAt > createdAt 우선 (임시저장/확정 모두 일관)
                const ts = pick(b, ['updatedAt', 'updated_at', 'createdAt', 'created_at']);
                const tt = parseAny(ts);
                if (tt) return tt;

                // 최후의 fallback: bookingId에 날짜가 포함된 경우가 있어(BKYYYYMMDD...) 파싱 시도
                const bid = String(b?.bookingId || '').trim();
                const m = bid.match(/BK(\d{4})(\d{2})(\d{2})/i);
                if (m) {
                    const ds = `${m[1]}-${m[2]}-${m[3]}T00:00:00`;
                    const tb = Date.parse(ds);
                    if (!Number.isNaN(tb)) return tb;
                }

                return 0;
            };
            const nonTemp = bookings.filter(b => String(b?.bookingStatus || '').toLowerCase() !== 'pending');
            const pool = nonTemp.length > 0 ? nonTemp : bookings;
            recentBooking = pool.slice().sort((a, b) => toTime(b) - toTime(a))[0] || null;
            console.log('최근 예약 데이터(임시저장 제외 우선):', recentBooking);
        } else {
            console.log('예약 데이터가 없습니다.');
            recentBooking = null;
        }
        
    } catch (error) {
        console.error('Failed to load user data:', error);
        recentBooking = null;
    }
}

// 로그인 상태 뷰 표시
function showLoggedInView() {
    // 로그인 전 요소들 숨기기
    hideLoggedOutElements();
    
    // 사용자 환영 메시지
    updateWelcomeMessage();
    
    // 계정 설정 링크 표시
    showAccountSettingsLink();
    
    // 최근 활동 표시
    showRecentActivity();
    
    // 여행 관리 섹션 표시
    showTripManagementSection();
    
    // Settings & Support 섹션 표시
    showSettingsSupportSection();
}

// 로그아웃 상태 뷰 표시
function showLoggedOutView() {
    // 로그인 후 요소들 숨기기
    hideLoggedInElements();
    
    // 로그인 유도 메시지 표시
    showLoginPrompt();

    // 요구사항: 비로그인 마이페이지에서는 "Manage My Trips" 섹션 제거
    hideTripManagementSection();
}

function hideTripManagementSection() {
    try {
        const title = Array.from(document.querySelectorAll('.text.fz16.fw600.lh24.black12'))
            .find(el => (el.textContent || '').trim().toLowerCase().includes('manage my trips'));
        if (!title) return;
        title.style.display = 'none';
        const next = title.nextElementSibling;
        if (next && next.classList.contains('card-type9')) {
            next.style.display = 'none';
        }
    } catch (_) {}
}

// 로그아웃 상태 요소들 숨기기
function hideLoggedOutElements() {
    // ID로 직접 접근
    const loginPrompt = document.getElementById('loginPrompt');
    const loginLink = document.getElementById('loginLink');
    const loginLinkWrapper = document.getElementById('loginLinkWrapper');
    
    if (loginPrompt) {
        loginPrompt.style.display = 'none';
    }
    
    if (loginLink) {
        loginLink.style.display = 'block';
    }
    
    if (loginLinkWrapper) {
        loginLinkWrapper.style.display = 'none';
    }
}

// 로그인 상태 요소들 숨기기
function hideLoggedInElements() {
    // ID로 직접 접근
    const welcomeMessage = document.getElementById('welcomeMessage');
    const accountLink = document.getElementById('accountLink');
    const accountLinkWrapper = document.getElementById('accountLinkWrapper');
    
    if (welcomeMessage) {
        welcomeMessage.style.display = 'none';
    }
    
    if (accountLink) {
        accountLink.style.display = 'block';
    }
    
    if (accountLinkWrapper) {
        accountLinkWrapper.style.display = 'none';
    }
}

// 환영 메시지 업데이트 (다국어 지원)
function updateWelcomeMessage() {
    const welcomeMessage = document.getElementById('welcomeMessage');
    const userNameSpan = document.getElementById('userName');
    
    if (welcomeMessage) {
        const userName = userProfile ? 
                        `${userProfile.firstName || ''} ${userProfile.lastName || ''}`.trim() || 
                        userProfile.username || 
                        localStorage.getItem('username') : 
                        localStorage.getItem('username') || 'User';
        
        // 다국어 텍스트 가져오기
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        const welcomeText = texts.welcomeMessage || 'Welcome, {userName}!';
        
        // 사용자 이름을 텍스트에 삽입
        const updatedText = welcomeText.replace('{userName}', userName);
        
        if (userNameSpan) {
            userNameSpan.textContent = userName;
            // 전체 메시지 업데이트
            welcomeMessage.innerHTML = welcomeText.replace('{userName}', `<span id="userName">${userName}</span>`);
        } else {
            welcomeMessage.innerHTML = updatedText;
        }
        welcomeMessage.style.display = 'block';
    }
}

// 계정 설정 링크 표시
function showAccountSettingsLink() {
    const settingsLink = document.getElementById('accountLink');
    const wrapper = document.getElementById('accountLinkWrapper');
    
    if (settingsLink) {
        settingsLink.style.display = 'block';
    }
    
    if (wrapper) {
        wrapper.style.display = 'flex';
    }
}

// 최근 활동 표시
function showRecentActivity() {
    const recentActivityCard = document.getElementById('recentActivityCard');
    
    if (!recentActivityCard) {
        console.log('Recent Activity 카드 요소를 찾을 수 없습니다.');
        return;
    }
    
    if (!recentBooking) {
        console.log('최근 예약이 없어서 Recent Activity 섹션을 숨깁니다.');
        // 다국어 텍스트 가져오기
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        recentActivityCard.innerHTML = `<div class="text fz14 fw400 lh22 gray6b text-center py20">${texts.noRecentActivity || '최근 활동이 없습니다.'}</div>`;
        return;
    }
    
    console.log('최근 예약 표시:', recentBooking);
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const statusText = getBookingStatusText(recentBooking.bookingStatus, recentBooking.paymentStatus);
    const statusColor = getBookingStatusColor(recentBooking.bookingStatus, recentBooking.paymentStatus);
    
    // 출발일 포맷팅
    let departureDateStr = '';
    if (recentBooking.departureDate) {
        const depDate = new Date(recentBooking.departureDate);
        if (currentLang === 'ko') {
            // 한국어: "2025. 4. 19. (토)" 형식
            const year = depDate.getFullYear();
            const month = depDate.getMonth() + 1;
            const day = depDate.getDate();
            const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
            const weekday = weekdays[depDate.getDay()];
            departureDateStr = `${year}. ${month}. ${day}. (${weekday})`;
        } else if (currentLang === 'en') {
            // 영어: "April 19, 2025 (Sat)" 형식
            departureDateStr = depDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'short'
            });
        } else {
            // Tagalog: 영어 형식 사용
            departureDateStr = depDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'short'
            });
        }
    }
    
    // 썸네일 이미지 경로 처리
    let thumbnailSrc = '../images/@img_thumbnail.png';
    if (recentBooking.thumbnail) {
        if (recentBooking.thumbnail.startsWith('http://') || recentBooking.thumbnail.startsWith('https://')) {
            thumbnailSrc = recentBooking.thumbnail;
        } else if (recentBooking.thumbnail.startsWith('../')) {
            thumbnailSrc = recentBooking.thumbnail;
        } else if (recentBooking.thumbnail.startsWith('/')) {
            thumbnailSrc = '..' + recentBooking.thumbnail;
        } else {
            // product_images 배열 처리 또는 단일 이미지
            try {
                const images = JSON.parse(recentBooking.thumbnail);
                if (Array.isArray(images) && images.length > 0) {
                    thumbnailSrc = '../uploads/products/' + images[0];
                } else {
                    thumbnailSrc = '../uploads/products/' + recentBooking.thumbnail;
                }
            } catch (e) {
                thumbnailSrc = '../uploads/products/' + recentBooking.thumbnail;
            }
        }
    }
    
    // 게스트 정보 다국어 처리
    const fallbackAdult = (currentLang === 'en' || currentLang === 'tl') ? 'Adult' : '성인';
    const fallbackChild = (currentLang === 'en' || currentLang === 'tl') ? 'Child' : '아동';
    const fallbackInfant = (currentLang === 'en' || currentLang === 'tl') ? 'Infant' : '유아';
    const guestParts = [];
    if (recentBooking.adults > 0) {
        guestParts.push(`${texts.adult || fallbackAdult} x${recentBooking.adults}`);
    }
    if (recentBooking.children > 0) {
        guestParts.push(`${texts.child || fallbackChild} x${recentBooking.children}`);
    }
    if (recentBooking.infants > 0) {
        guestParts.push(`${texts.infant || fallbackInfant} x${recentBooking.infants}`);
    }
    const guestText = guestParts.join(', ') || `${texts.adult || fallbackAdult} x0`;
    
    recentActivityCard.innerHTML = `
        <div>
            <div class="align both vm">
                <div class="text fz16 fw600 lh24 ${statusColor}">${statusText}</div>
                <p class="text fz12 fw400 lh16 gray6b">${texts.reservation_number_label || ((currentLang === 'en' || currentLang === 'tl') ? 'Reservation No.' : '예약 번호')} ${recentBooking.bookingId}</p>
            </div>
            <div class="align vm gap8 mt16">
                <img src="${thumbnailSrc}" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;" onerror="this.src='../images/@img_thumbnail.png'">
                <div class="mxw100 hidden">
                    <div class="text fz14 fw500 lh22 black12 ellipsis1">${recentBooking.productName || recentBooking.productNameEn || (texts.package_name_none || ((currentLang === 'en' || currentLang === 'tl') ? 'Package name not available' : '패키지명 없음'))}</div>
                    ${departureDateStr ? `<p class="text fz14 fw400 lh22 black12 mt4">${departureDateStr}</p>` : ''}
                </div>
            </div>
            <div class="card-type8 pink mt16">
                <div class="text fz14 fw600 lh22 black12">${texts.number_of_guests || ((currentLang === 'en' || currentLang === 'tl') ? 'Guests' : '인원')}</div>
                <p class="text fz12 fw400 lh16 black12">${guestText}</p>
            </div>
            <div class="mt16">
                <a class="btn primary lg" href="reservation-detail.php?id=${encodeURIComponent(recentBooking.bookingId)}&from=mypage&lang=${encodeURIComponent(currentLang || 'en')}">${texts.reservation_detail_history || ((currentLang === 'en' || currentLang === 'tl') ? 'Booking Details' : '예약 상세 내역')}</a>
            </div>
        </div>
    `;
}

// 여행 관리 섹션 표시
function showTripManagementSection() {
    // HTML에 이미 있는 Manage My Trips 섹션을 표시
    const tripManagementSection = document.querySelector('.text.fz16.fw600.lh24.black12.mt32');
    if (tripManagementSection && tripManagementSection.textContent.includes('Manage My Trips')) {
        tripManagementSection.style.display = 'block';
        const nextCard = tripManagementSection.nextElementSibling;
        if (nextCard && nextCard.classList.contains('card-type9')) {
            nextCard.style.display = 'block';
        }
    }
}

// Settings & Support 섹션 표시
function showSettingsSupportSection() {
    console.log('Settings & Support 섹션 표시 시작');
    // ID로 직접 접근
    const settingsTitle = document.getElementById('settingsSupportTitle');
    const settingsContent = document.getElementById('settingsSupportContent');
    
    console.log('Settings Title 요소:', settingsTitle);
    console.log('Settings Content 요소:', settingsContent);
    
    if (settingsTitle) {
        settingsTitle.style.display = 'block';
        console.log('Settings Title 표시됨');
    } else {
        console.log('Settings Title 요소를 찾을 수 없음');
    }
    
    if (settingsContent) {
        settingsContent.style.display = 'block';
        console.log('Settings Content 표시됨');
    } else {
        console.log('Settings Content 요소를 찾을 수 없음');
    }
}

// 로그인 유도 메시지 표시
function showLoginPrompt() {
    let loginPromptMessage = document.querySelector('.login-prompt-message');
    
    if (!loginPromptMessage) {
        // 기존 로그인 전 메시지 찾기
        loginPromptMessage = document.querySelector('.text.fz20.fw600.black12.mb16');
    }
    
    if (loginPromptMessage) {
        loginPromptMessage.innerHTML = `
            Log in and<br>
            plan your next trip
        `;
        loginPromptMessage.style.display = 'block';
    }
    
    // 로그인 링크 표시 (+ 언어 파라미터 유지)
    const loginLink = document.getElementById('loginLink');
    const loginLinkWrapper = document.getElementById('loginLinkWrapper');
    
    if (loginLink) {
        loginLink.style.display = 'block';
        try {
            const currentLang = (typeof getCurrentLanguage === 'function')
                ? getCurrentLanguage()
                : (localStorage.getItem('selectedLanguage') || 'en');
            // 로그인 화면은 /user/login.html?lang=en|tl 지원
            if (currentLang && currentLang !== 'ko') {
                loginLink.setAttribute('href', `login.html?lang=${encodeURIComponent(currentLang)}`);
            }
        } catch (_) {}
    }
    
    if (loginLinkWrapper) {
        loginLinkWrapper.style.display = 'flex';
    }
}

// 예약 상태 텍스트 반환
function getBookingStatusText(bookingStatus, paymentStatus) {
    // 요구사항(상태명 통일):
    // Payment Suspended / Payment Completed / Trip Completed / Payment Canceled / Refund Completed / Temporary save
    // (B2B는 기존 Reservation* 용어 유지)
    const bs = String(bookingStatus || '').toLowerCase();
    const ps = String(paymentStatus || '').toLowerCase();

    // B2B/B2C 판별: accountType 기반
    // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B
    // - accountType IN ('guest', 'guide', 'cs', '') → B2C
    const accountType = String(localStorage.getItem('accountType') || '').toLowerCase();
    const isB2B = accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr';

    // 공통 상태키 계산(예약내역/상세와 동일 규칙)
    let key = '';
    if (['temporary_save', 'temporary', 'draft', 'temp', 'saved', 'saved_draft'].includes(bs)) key = 'temporary_save';
    else if (bs === 'refunded' || bs === 'refund_completed' || ps === 'refunded') key = 'refund_completed';
    else if (bs === 'completed') key = 'travel_completed';
    else if (bs === 'cancelled' || bs === 'canceled' || ps === 'failed') key = 'canceled';
    else if (bs === 'pending') key = isB2B ? 'pending' : 'payment_suspended';
    else if (bs === 'confirmed') {
        if (ps === 'paid') key = isB2B ? 'confirmed' : 'completed';
        else if (ps === 'pending' || ps === 'partial' || ps === 'unpaid' || ps === '' || ps === 'null') key = isB2B ? 'confirmed' : 'payment_suspended';
        else key = isB2B ? 'confirmed' : 'payment_suspended';
    } else if (ps === 'paid') key = isB2B ? 'confirmed' : 'completed';
    else if (ps === 'pending' || ps === 'partial') key = isB2B ? 'pending' : 'payment_suspended';
    else key = bs || ps || '';

    if (isB2B) {
        const map = {
            pending: 'Reservation pending',
            confirmed: 'Reservation confirmed',
            canceled: 'Reservation canceled',
            refund_completed: 'Refund completed',
            travel_completed: 'Travel completed',
            temporary_save: 'Temporary save'
        };
        return map[key] || 'Reservation confirmed';
    }

    const map = {
        payment_suspended: 'Payment Suspended',
        completed: 'Payment Completed',
        canceled: 'Payment Canceled',
        refund_completed: 'Refund Completed',
        travel_completed: 'Trip Completed',
        temporary_save: 'Temporary save'
    };
    return map[key] || 'Payment Suspended';
}

// 예약 상태 색상 클래스 반환
function getBookingStatusColor(bookingStatus, paymentStatus) {
    const bs = String(bookingStatus || '').toLowerCase();
    const ps = String(paymentStatus || '').toLowerCase();

    // 색상 정책: 완료(초록) / 취소(회색) / 대기(주황) / 확정/완료(빨강)
    if (bs === 'refunded' || bs === 'refund_completed' || ps === 'refunded') return 'green';
    if (bs === 'completed') return 'green';
    if (bs === 'cancelled' || bs === 'canceled' || ps === 'failed') return 'gray6b';
    if (bs === 'pending') return 'orange';
    if (bs === 'confirmed' && (ps === 'pending' || ps === 'partial' || ps === 'unpaid' || ps === '' || ps === 'null')) return 'orange';
    if (ps === 'paid' || bs === 'confirmed') return 'reded';
    return 'black12';
}

// 로그아웃 처리
function handleLogout() {
    if (confirm('로그아웃 하시겠습니까?')) {
        // 서버 세션도 종료 (실패해도 로컬 정리는 수행)
        try {
            fetch('../backend/api/logout.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            }).catch(() => {});
        } catch (_) {}

        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userEmail');
        localStorage.removeItem('userId');
        localStorage.removeItem('username');
        localStorage.removeItem('accountType');
        localStorage.removeItem('autoLogin');
        
        alert('로그아웃되었습니다.');
        // 홈으로 이동 (로그인 상태 UI 리셋)
        const lang = (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en'));
        location.href = `../home.html?lang=${encodeURIComponent(lang || 'en')}`;
    }
}

// 로그아웃 버튼이 있다면 이벤트 연결
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.querySelector('.logout-btn, [data-action="logout"]');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
});

// 카테고리 링크 설정 (현재 언어 파라미터 포함)
function setupCategoryLinks() {
    const categoryLinks = document.querySelectorAll('.category-link');
    const currentLang = getCurrentLanguage();
    
    categoryLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href) {
            // 상대 경로 유지하면서 쿼리 파라미터 추가
            let urlString = href;
            const separator = urlString.includes('?') ? '&' : '?';
            
            if (currentLang && currentLang !== 'ko') {
                urlString += separator + 'lang=' + currentLang;
            }
            
            link.setAttribute('href', urlString);
        }
    });
}

async function loadAndRenderMypageCategories() {
    const list = document.getElementById('mypageCategoryList');
    if (!list) return;

    const currentLang = getCurrentLanguage();
    const langParam = (currentLang && currentLang !== 'ko') ? `&lang=${encodeURIComponent(currentLang)}` : '';

    try {
        const res = await fetch('../backend/api/categories.php');
        const json = await res.json();
        if (!json?.success || !Array.isArray(json?.data?.mainCategories)) {
            throw new Error('invalid categories response');
        }

        const mains = json.data.mainCategories.filter(m => m && m.code);
        // 2개씩 배치
        const rows = [];
        for (let i = 0; i < mains.length; i += 2) {
            rows.push(mains.slice(i, i + 2));
        }

        list.innerHTML = rows.map((pair, idx) => {
            const cls = idx === 0 ? 'align vm' : 'align vm mt22';
            const links = pair.map(m => {
                const href = `product-info.html?category=${encodeURIComponent(m.code)}${langParam}`;
                const label = (m.name || m.code);
                return `<a class="text fz14 fw500 lh22 black12 w100 category-link" href="${href}" data-category="${escapeHtmlAttr(m.code)}">${escapeHtml(label)}</a>`;
            }).join('');
            return `<li class="${cls}">${links}</li>`;
        }).join('');
    } catch (e) {
        // 실패 시 최소 fallback
        list.innerHTML = `
            <li class="align vm">
                <a class="text fz14 fw500 lh22 black12 w100 category-link" href="product-info.html?category=season${langParam}" data-category="season">계절별</a>
                <a class="text fz14 fw500 lh22 black12 w100 category-link" href="product-info.html?category=region${langParam}" data-category="region">지역별</a>
            </li>
            <li class="align vm mt22">
                <a class="text fz14 fw500 lh22 black12 w100 category-link" href="product-info.html?category=theme${langParam}" data-category="theme">테마별</a>
                <a class="text fz14 fw500 lh22 black12 w100 category-link" href="product-info.html?category=private${langParam}" data-category="private">프라이빗</a>
            </li>
            <li class="align vm mt22">
                <a class="text fz14 fw500 lh22 black12 w100 category-link" href="product-info.html?category=daytrip${langParam}" data-category="daytrip">당일치기</a>
            </li>
        `;
    }
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
}

function escapeHtmlAttr(s) {
    return String(s == null ? '' : s).replace(/"/g, '&quot;');
}