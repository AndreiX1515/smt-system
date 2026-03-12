// 예약 내역 페이지 기능

let userBookings = [];
let filteredBookings = [];
let currentFilter = 'scheduled'; // scheduled | past | canceled

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('reservation-history.php')) {
        initializeReservationHistory();
    }
});

// 예약 내역 초기화
async function initializeReservationHistory() {
    // 다국어 텍스트 로드
    await loadServerTexts();
    
    // 로그인 확인
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (!isLoggedIn) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        alert(texts.loginRequired || '로그인이 필요합니다.');
        location.href = 'login.html';
        return;
    }
    
    // 예약 데이터 로드 (먼저 실행)
    await loadBookingHistory();
    
    // 초기 상태: 모든 컨테이너 숨기기
    const containers = ['intended', 'past', 'canceled'];
    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'none';
        }
    });
    
    // 탭 이벤트 설정
    setupTabs();
    
    // 초기 필터 설정 (Scheduled 탭이 기본 활성화)
    const initialContainer = document.getElementById('intended');
    if (initialContainer) {
        initialContainer.style.display = 'block';
    }
    filterBookings('scheduled');
}

// 예약 내역 로드
async function loadBookingHistory() {
    const userId = localStorage.getItem('userId');
    
    if (!userId) {
        console.error('❌ userId가 없습니다. 로그인이 필요합니다.');
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        alert(texts.loginRequired || '로그인이 필요합니다.');
        location.href = 'login.html';
        return;
    }
    
    try {
        console.log('예약 내역 로드 시작, userId:', userId);
        showLoadingState();
        
        // API 엔드포인트
        const apiUrl = `../backend/api/user_bookings.php?accountId=${userId}`;
        console.log('API 호출:', apiUrl);
        
        const response = await fetch(apiUrl);
        
        console.log('API 응답 상태:', response.status, response.ok);
        
        // 응답 상태 확인
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // 응답 텍스트 먼저 확인
        const responseText = await response.text();
        console.log('Raw response (처음 500자):', responseText.substring(0, 500));
        
        if (!responseText.trim()) {
            throw new Error('Empty response from server');
        }
        
        const result = JSON.parse(responseText);
        console.log('파싱된 API 응답:', result);
        
        if (result.success && result.data) {
            userBookings = result.data.bookings || [];
            filteredBookings = [...userBookings];
            
            console.log(`✅ Loaded ${userBookings.length} bookings for user ${userId}`);
            // 탭 카운트는 요구사항(상태 기준 Scheduled/Past/Canceled)으로 클라이언트에서 계산
            updateTabCountsFromBookings(userBookings);
            renderBookingList();
        } else {
            console.error('❌ API 응답 실패:', result.message || 'Unknown error');
            const currentLang = getCurrentLanguage();
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
            showEmptyState(texts.loadBookingHistoryFailed || '예약 내역을 불러오는데 실패했습니다.');
        }
        
    } catch (error) {
        console.error('❌ Failed to load booking history:', error);
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        showEmptyState(texts.loadBookingHistoryFailed || '예약 내역을 불러오는데 실패했습니다.');
    } finally {
        hideLoadingState();
    }
}

// 탭 설정
function setupTabs() {
    const tabs = document.querySelectorAll('.btn-tab2');
    
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            // 모든 탭 비활성화
            tabs.forEach(t => t.classList.remove('active'));
            
            // 클릭된 탭 활성화
            tab.classList.add('active');
            
            // 모든 컨테이너 숨기기
            const containers = ['intended', 'past', 'canceled'];
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.style.display = 'none';
                }
            });
            
            // 필터 적용 (원본 퍼블리싱 3탭: Scheduled / Past / Canceled)
            let filter = 'scheduled';
            if (index === 1) filter = 'past';
            if (index === 2) filter = 'canceled';
            
            // 해당 탭의 컨테이너 표시
            const activeContainerId = getContainerIdForFilter(filter);
            const activeContainer = document.getElementById(activeContainerId);
            if (activeContainer) {
                activeContainer.style.display = 'block';
            }
            
            // 필터 적용
            filterBookings(filter);
        });
    });
}

// 필터에 맞는 컨테이너 ID 반환
function getContainerIdForFilter(filter) {
    switch (filter) {
        case 'scheduled':
            return 'intended';
        case 'past':
            return 'past';
        case 'canceled':
            return 'canceled';
        default:
            return 'intended';
    }
}

// 예약 필터링
function filterBookings(filter) {
    currentFilter = filter;
    
    filteredBookings = userBookings.filter(booking => getTabForBooking(booking) === filter);
    
    renderBookingList();
}

// 예약 목록 렌더링
function renderBookingList() {
    // 현재 필터에 따라 올바른 컨테이너 선택
    const containerId = getContainerIdForFilter(currentFilter);
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.error('❌ Container not found for filter:', currentFilter, 'containerId:', containerId);
        return;
    }
    
    // 컨테이너 표시
    container.style.display = 'block';
    
    console.log(`렌더링 시작 - 필터: ${currentFilter}, 컨테이너: ${containerId}, 예약 수: ${filteredBookings.length}`);
    
    if (filteredBookings.length === 0) {
        showEmptyState(getEmptyMessage(), container);
        return;
    }
    
    // 날짜별로 그룹화
    const groupedBookings = groupBookingsByDate(filteredBookings);
    
    const currentLang = getCurrentLanguage();
    const localeMap = {
        'ko': 'ko-KR',
        'en': 'en-US',
        'tl': 'en-US' // Tagalog uses English date format
    };
    const locale = localeMap[currentLang] || 'ko-KR';
    
    const bookingListHtml = Object.keys(groupedBookings)
        .sort((a, b) => new Date(b) - new Date(a)) // 최신 날짜부터
        .map(date => {
            const bookings = groupedBookings[date];
            const dateStr = new Date(date).toLocaleDateString(locale, {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            return `
                <div class="text fz14 fw500 black12">${dateStr}</div>
                ${bookings.map(booking => createBookingItem(booking)).join('')}
            `;
        }).join('');
    
    container.innerHTML = bookingListHtml;
}

// B2B/B2C 판별: accountType 기반
function isB2BUser() {
    // - accountType IN ('agent', 'admin') → B2B
    // - accountType IN ('guest', 'guide', 'cs', '') → B2C
    const at = String(localStorage.getItem('accountType') || '').toLowerCase();
    return at === 'agent' || at === 'admin';
}

// highlight 쿼리 파라미터 (예약번호 강조)
function getHighlightBookingId() {
    try {
        const u = new URL(window.location.href);
        return (u.searchParams.get('highlight') || '').trim();
    } catch (_) {
        return '';
    }
}

// bookingStatus/paymentStatus → 표준 상태키
function normalizeStatusKeyFromBooking(booking) {
    const bs = String(booking?.bookingStatus || '').toLowerCase();
    const ps = String(booking?.paymentStatus || '').toLowerCase();
    const b2b = isB2BUser();

    // Temporary save heuristic for user flow (dev_tasks #114):
    // bookingStatus='pending' is used while the user is still completing steps.
    // If payment isn't completed yet, treat it as temporary save so user can resume editing.
    if (bs === 'pending' && (ps === 'pending' || ps === '' || ps === 'null')) return 'temporary_save';

    // 임시저장: 'temporary'/'draft' 계열만 임시저장으로 간주 (pending은 입금대기/결제대기임)
    if (['temporary_save', 'temporary', 'draft', 'temp', 'saved', 'saved_draft'].includes(bs)) return 'temporary_save';

    // 환불 완료
    if (bs === 'refunded' || bs === 'refund_completed' || ps === 'refunded') return 'refund_completed';

    // 여행 완료
    if (bs === 'completed') return 'travel_completed';

    // 취소
    if (bs === 'cancelled' || bs === 'canceled' || ps === 'failed') {
        return b2b ? 'canceled' : 'canceled';
    }

    // 결제 대기/입금 대기(예약 직후 포함)
    if (bs === 'pending') return b2b ? 'pending' : 'payment_suspended';

    // confirmed 계열: 결제 진행중/완료
    if (bs === 'confirmed') {
        // B2C 요구사항: 예약 직후(입금 전)에는 "Payment Suspended"로 분류되어야 함.
        // 따라서 paymentStatus가 paid가 아니면 기본적으로 suspended로 본다.
        if (ps === 'paid') return b2b ? 'confirmed' : 'completed';
        if (ps === 'pending' || ps === 'partial' || ps === 'unpaid' || ps === '' || ps === 'null') {
            return b2b ? 'confirmed' : 'payment_suspended';
        }
        // 알 수 없는 결제상태도 B2C는 보수적으로 suspended 처리
        return b2b ? 'confirmed' : 'payment_suspended';
    }

    // paymentStatus만 보고 추정
    if (ps === 'paid') return b2b ? 'confirmed' : 'completed';
    if (ps === 'pending' || ps === 'partial') return b2b ? 'pending' : 'payment_suspended';
    if (ps === 'refunded') return 'refund_completed';
    if (ps === 'failed') return 'canceled';

    return (bs || ps || '');
}

// 표준 상태키 → 화면 표시 타이틀(요구사항 문구)
function getBookingStatusTitle(booking) {
    const b2b = isB2BUser();
    const k = normalizeStatusKeyFromBooking(booking);

    if (b2b) {
        const map = {
            pending: 'Reservation pending',
            confirmed: 'Reservation confirmed',
            canceled: 'Reservation canceled',
            cancelled: 'Reservation canceled',
            refund_completed: 'Refund completed',
            travel_completed: 'Travel completed',
            temporary_save: 'Temporary save'
        };
        return map[k] || 'Reservation confirmed';
    }

    const map = {
        payment_suspended: 'Payment Suspended',
        suspended: 'Payment Suspended',
        pending: 'Payment Suspended',
        completed: 'Payment Completed',
        confirmed: 'Payment Completed',
        paid: 'Payment Completed',
        canceled: 'Payment Canceled',
        cancelled: 'Payment Canceled',
        refund_completed: 'Refund Completed',
        travel_completed: 'Trip Completed',
        temporary_save: 'Temporary save'
    };
    return map[k] || 'Payment Completed';
}

// 상태키/타이틀 → 탭 분류(Scheduled/Past/Canceled)
function getTabForBooking(booking) {
    const b2b = isB2BUser();
    const k = normalizeStatusKeyFromBooking(booking);

    if (b2b) {
        // Scheduled : Reservation pending/Reservation confirmed/Temporary save
        if (k === 'pending' || k === 'confirmed' || k === 'temporary_save') return 'scheduled';
        // Past : Travel completed
        if (k === 'travel_completed') return 'past';
        // Canceled : Reservation canceled/Refund completed
        if (k === 'canceled' || k === 'cancelled' || k === 'refund_completed') return 'canceled';
        return 'scheduled';
    }

    // Scheduled : Payment Suspended/Payment Completed/Temporary save
    if (k === 'payment_suspended' || k === 'completed' || k === 'confirmed' || k === 'paid' || k === 'temporary_save') return 'scheduled';
    // Past : Trip Completed
    if (k === 'travel_completed') return 'past';
    // Canceled : Payment Canceled/Refund Completed
    if (k === 'canceled' || k === 'cancelled' || k === 'refund_completed') return 'canceled';
    return 'scheduled';
}

// 예약 아이템 HTML 생성
function createBookingItem(booking) {
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const statusText = getBookingStatusTitle(booking);
    
    const localeMap = {
        'ko': 'ko-KR',
        'en': 'en-US',
        'tl': 'en-US'
    };
    const locale = localeMap[currentLang] || 'ko-KR';
    
    // 출발일 포맷팅
    let departureDateStr = '';
    if (booking.departureDate) {
        const depDate = new Date(booking.departureDate);
        if (currentLang === 'ko') {
            departureDateStr = depDate.toLocaleDateString('ko-KR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } else if (currentLang === 'en') {
            departureDateStr = depDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } else {
            departureDateStr = depDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    }
    
    // 썸네일 이미지 경로 처리
    let thumbnailSrc = '../images/@img_thumbnail.png';
    if (booking.thumbnail) {
        if (booking.thumbnail.startsWith('http://') || booking.thumbnail.startsWith('https://')) {
            thumbnailSrc = booking.thumbnail;
        } else if (booking.thumbnail.startsWith('../')) {
            thumbnailSrc = booking.thumbnail;
        } else if (booking.thumbnail.startsWith('/')) {
            thumbnailSrc = '..' + booking.thumbnail;
        } else {
            // product_images 배열 처리 또는 단일 이미지
            try {
                const images = JSON.parse(booking.thumbnail);
                if (Array.isArray(images) && images.length > 0) {
                    thumbnailSrc = '../uploads/products/' + images[0];
                } else {
                    thumbnailSrc = '../uploads/products/' + booking.thumbnail;
                }
            } catch (e) {
                thumbnailSrc = '../uploads/products/' + booking.thumbnail;
            }
        }
    }
    
    // guestOptions만 사용 (동적 옵션명 표시) - API가 booking.guestOptions를 내려주는 것을 신뢰
    const guestOptions = Array.isArray(booking.guestOptions) ? booking.guestOptions : [];
    const guestParts = guestOptions
        .map(o => ({
            name: String(o?.name ?? o?.optionName ?? o?.title ?? '').trim(),
            qty: Number(o?.qty ?? o?.quantity ?? 0) || 0
        }))
        .filter(x => x.name && x.qty > 0)
        .map(x => `${x.name}x${x.qty}`);

    const guestText = guestParts.join(', ') || '-';

    const highlightId = getHighlightBookingId();
    const isHighlighted = !!(highlightId && String(booking.bookingId || '').toLowerCase() === String(highlightId).toLowerCase());
    const highlightClass = isHighlighted ? ' is-highlight' : '';
    
    const statusKey = normalizeStatusKeyFromBooking(booking);
    const isTemporarySave = statusKey === 'temporary_save' || 
        statusKey === 'draft' || 
        statusText.toLowerCase() === 'temporary save' ||
        String(booking.bookingStatus || '').toLowerCase() === 'draft';
    const buttonClass = isTemporarySave ? 'btn line primary lg' : 'btn primary lg';
    const lang = String(currentLang || 'en');
    const detailHref = isTemporarySave
        ? `select-reservation.php?booking_id=${encodeURIComponent(booking.bookingId)}&lang=${encodeURIComponent(lang)}`
        : `reservation-detail.php?id=${encodeURIComponent(booking.bookingId)}`;
    const btnText = isTemporarySave
        ? (texts.continue_booking || 'Continue Booking')
        : (texts.reservation_detail_history || 'Reservation Details');
    
    return `
        <div class="card-type11 mt12${highlightClass}">
            <div class="align both vm">
                <div class="text fz16 fw600 black12">${statusText}</div>
                ${!isTemporarySave ? `
                    <p class="text fz12 fw400 gray6b">${texts.reservation_number_label || '예약 번호'} ${booking.bookingId}</p>
                ` : ''}
            </div>
            <div class="align vm gap8 mt16">
                <img src="${thumbnailSrc}" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;" onerror="this.src='../images/@img_thumbnail.png'">
                <div class="mxw100 hidden">
                    <div class="text fz14 fw500 lh22 black12 ellipsis1">${booking.productName || booking.productNameEn || (texts.package_name_none || '패키지명 없음')}</div>
                    ${departureDateStr ? `<p class="text fz14 fw400 lh22 black12 mt4">${departureDateStr}</p>` : ''}
                </div>
            </div>
            ${!isTemporarySave ? `
            <div class="card-type8 pink mt16">
                <div class="text fz14 fw600 lh22 black12">${texts.number_of_guests || 'Number of Guests'}</div>
                <p class="text fz12 fw400 lh16 black12">${guestText}</p>
            </div>
            ` : ''}
            <div class="mt16">
                <a class="${buttonClass}" href="${detailHref}">${btnText}</a>
            </div>
        </div>
    `;
}

// 날짜별로 예약 그룹화
function groupBookingsByDate(bookings) {
    return bookings.reduce((groups, booking) => {
        const raw = booking.departureDate || booking.createdAt || '';
        const safe = String(raw || '');
        const date = safe.includes('T') ? safe.split('T')[0] : safe.substring(0, 10); // YYYY-MM-DD
        const key = date || 'Unknown';
        if (!groups[key]) {
            groups[key] = [];
        }
        groups[key].push(booking);
        return groups;
    }, {});
}

// 예약 상세 페이지로 이동
function goToBookingDetail(bookingId) {
    location.href = `reservation-detail.php?id=${bookingId}`;
}

// 빈 상태 표시
function showEmptyState(message = null, container = null) {
    if (!container) {
        container = document.getElementById(getContainerIdForFilter(currentFilter));
    }
    
    if (!container) return;
    
    // 메시지가 없으면 현재 언어에 맞는 메시지 사용
    if (!message) {
        message = getEmptyMessage();
    }
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const emptyId = (currentFilter === 'scheduled') ? ' id="none"' : '';
    const emptyStateHtml = `
        <div class="mt50"${emptyId}>
            <img class="block mx-auto" src="../images/ico_travel.svg" alt="">
            <div class="text fz16 fw600 lh24 black12 mt35 txt-center">${message}</div>
            <p class="text fz14 fw500 lh21 black12 mt8 txt-center">${texts.planNewTrip || '새로운 여행을 계획해보세요'}</p>
            <div class="align center vm mt36">
                <a class="btn line active sm mxw120 ico6" href="../home.html">${texts.browseProducts || '상품 둘러보기'}</a>
            </div>
        </div>
    `;
    
    container.innerHTML = emptyStateHtml;
}

// 빈 상태 메시지 반환
function getEmptyMessage() {
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const messages = {
        'scheduled': texts.noUpcomingTrips || "You don't have any scheduled trips yet",
        'past': texts.noCompletedTrips || 'No completed trips',
        'canceled': texts.noCancelledBookings || 'No cancelled bookings'
    };
    
    return messages[currentFilter] || texts.noReservationHistory || '예약 내역이 없습니다.';
}

// 로딩 상태 표시
function showLoadingState() {
    const container = document.getElementById('intended');
    if (!container) return;
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const loadingHtml = `
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">${texts.loadingReservationHistory || '예약 내역을 불러오는 중...'}</div>
        </div>
    `;
    
    container.innerHTML = loadingHtml;
}

// 로딩 상태 해제
function hideLoadingState() {
    const loadingState = document.querySelector('.loading-state');
    if (loadingState) {
        loadingState.remove();
    }
}

// 탭 카운트 업데이트 (요구사항 분류 기준)
function updateTabCountsFromBookings(bookings) {
    const counts = { scheduled: 0, past: 0, canceled: 0 };
    (bookings || []).forEach(b => {
        const t = getTabForBooking(b);
        if (t && counts[t] != null) counts[t] += 1;
    });
    
    const tabs = document.querySelectorAll('.btn-tab2');
    if (tabs.length >= 3) {
        const set = (idx, val) => {
            const span = tabs[idx]?.querySelector('.tab-count');
            if (span) span.textContent = String(val);
        };
        set(0, counts.scheduled);
        set(1, counts.past);
        set(2, counts.canceled);
    }
}