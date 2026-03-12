// 문의사항 페이지 기능

let inquiries = [];
let currentStatus = 'all';
let currentPage = 1;
let inquiriesTotalCount = 0;
const PER_PAGE = 10;

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', function() {
    // inquiry.php(운영) / inquiry.html(정적) 둘 다 지원
    if (window.location.pathname.includes('inquiry.php') || window.location.pathname.includes('inquiry.html')) {
        initializeInquiryPage();
    }
});

function setupInquiryBackNavigation() {
    const backBtn = document.querySelector('a.btn-mypage, a.btn-back');
    if (!backBtn) return;

    const url = new URL(window.location.href);
    const params = url.searchParams;
    const lang = params.get('lang') || localStorage.getItem('selectedLanguage') || 'en';

    const rawReturn = params.get('returnUrl') || params.get('return_url') || '';
    let targetUrl = '';

    if (rawReturn) {
        try {
            // returnUrl은 같은 도메인의 path만 허용 (오픈리다이렉트 방지)
            const decoded = decodeURIComponent(rawReturn);
            if (decoded.startsWith('/')) {
                targetUrl = decoded;
            } else {
                const abs = new URL(decoded, window.location.origin);
                if (abs.origin === window.location.origin) {
                    targetUrl = abs.pathname + abs.search + abs.hash;
                }
            }
        } catch (_) {}
    }

    if (!targetUrl) {
        // referrer가 같은 도메인이면 우선 사용
        const ref = document.referrer || '';
        try {
            if (ref) {
                const r = new URL(ref);
                if (r.origin === window.location.origin && !r.pathname.includes('inquiry.html')) {
                    targetUrl = r.pathname + r.search + r.hash;
                }
            }
        } catch (_) {}
    }

    // SMT 수정 시작 - fallback을 mypage.html로 변경
    if (!targetUrl) {
        // 최후 fallback: 마이페이지로
        targetUrl = `mypage.html?lang=${encodeURIComponent(lang)}`;
    }
    // SMT 수정 완료

    backBtn.setAttribute('href', targetUrl);

    let navigating = false;
    backBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (navigating) return;
        navigating = true;

        window.location.href = targetUrl;
    }, { capture: true });
}

// 문의사항 페이지 초기화
async function initializeInquiryPage() {
    // 뒤로가기: returnUrl 우선 적용(상품상세에서 넘어온 경우 원래 페이지로 복귀)
    setupInquiryBackNavigation();

    // 다국어 텍스트 로드
    await loadServerTexts();
    
    // Call Support 클릭 시 전화번호 노출 + 전화 연결
    setupCallSupport();

    // URL 파라미터에서 상태 확인
    const urlParams = new URLSearchParams(window.location.search);
    currentStatus = urlParams.get('status') || 'all';
    const pageParam = parseInt(urlParams.get('page') || '1', 10);
    currentPage = Number.isFinite(pageParam) && pageParam > 0 ? pageParam : 1;
    
    // 문의사항 데이터 로드
    await loadInquiries();
    
    // 상태 필터 설정
    setupStatusFilter();
    
    // 문의사항 목록 렌더링
    renderInquiryList();
}

// 문의사항 데이터 로드
async function loadInquiries() {
    try {
        showLoadingState();
        
        // 새로운 API 사용
        const userId = localStorage.getItem('userId') || '';
        const result = await api.getInquiries(userId, currentStatus, '', PER_PAGE, (currentPage - 1) * PER_PAGE);
        
        if (result.success) {
            inquiries = result.data.inquiries;
            inquiriesTotalCount = Number.isFinite(result?.data?.totalCount) ? result.data.totalCount : inquiries.length;
            updateStatusCounts(result.data.statusCounts);
        } else {
            showEmptyState('Failed to load inquiries.');
        }
        
    } catch (error) {
        console.error('Failed to load inquiries:', error);
        showEmptyState('Failed to load inquiries.');
    } finally {
        hideLoadingState();
    }
}

function setupCallSupport() {
    const link = document.getElementById('callSupportLink');
    if (!link) return;

    link.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        const { domestic, international } = await fetchCompanyPhones();
        showCallSupportPopup({ domestic, international });
    });

    const layer = document.getElementById('callSupportLayer');
    const closeBtn = document.getElementById('callSupportCloseBtn');
    layer?.addEventListener('click', hideCallSupportPopup);
    closeBtn?.addEventListener('click', hideCallSupportPopup);
}

async function fetchCompanyPhones() {
    // 회사정보(footer)에서 phoneLocal / phoneInternational 사용
    const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');

    try {
        const res = await fetch(`../backend/api/company-info.php?type=footer&lang=${encodeURIComponent(lang || 'en')}`, {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const json = await res.json().catch(() => ({}));
        const info = json?.data?.companyInfo || {};

        const domestic = String(info.phoneLocal || '').trim();
        const international = String(info.phoneInternational || '').trim();
        return { domestic, international };
    } catch (_) {
        return { domestic: '', international: '' };
    }
}

function normalizeTel(phone) {
    const raw = String(phone || '').trim();
    if (!raw) return '';
    // keep only digits and leading '+'
    let cleaned = raw.replace(/[^0-9+]/g, '');
    // ensure '+' only at start
    cleaned = cleaned.replace(/\+(?=.)/g, '+'); // keep plus chars
    if (cleaned.includes('+')) {
        const firstPlus = cleaned.indexOf('+');
        cleaned = '+' + cleaned.replace(/\+/g, '').slice(firstPlus);
    }
    // if multiple plus were present, the above may be weird; simplest:
    if (raw.startsWith('+')) {
        cleaned = '+' + cleaned.replace(/\+/g, '');
    } else {
        cleaned = cleaned.replace(/\+/g, '');
    }
    return cleaned;
}

function showCallSupportPopup({ domestic, international }) {
    const layer = document.getElementById('callSupportLayer');
    const popup = document.getElementById('callSupportPopup');
    const desc = document.getElementById('callSupportDesc');
    const domesticBtn = document.getElementById('callSupportDomesticBtn');
    const intlBtn = document.getElementById('callSupportInternationalBtn');

    if (!layer || !popup || !domesticBtn || !intlBtn) return;

    const dTel = normalizeTel(domestic);
    const iTel = normalizeTel(international);

    if (desc) {
        const dTxt = dTel ? domestic : 'Not available';
        const iTxt = iTel ? international : 'Not available';
        desc.textContent = `Domestic: ${dTxt}\nInternational: ${iTxt}`;
        desc.style.whiteSpace = 'pre-line';
    }

    domesticBtn.disabled = !dTel;
    intlBtn.disabled = !iTel;
    domesticBtn.style.opacity = dTel ? '1' : '0.45';
    intlBtn.style.opacity = iTel ? '1' : '0.45';

    // 기존 리스너 중복 방지: onclick 사용
    domesticBtn.onclick = dTel ? () => { window.location.href = `tel:${dTel}`; } : null;
    intlBtn.onclick = iTel ? () => { window.location.href = `tel:${iTel}`; } : null;

    layer.style.display = 'block';
    popup.style.display = 'flex';
}

function hideCallSupportPopup() {
    const layer = document.getElementById('callSupportLayer');
    const popup = document.getElementById('callSupportPopup');
    if (layer) layer.style.display = 'none';
    if (popup) popup.style.display = 'none';
}

// 상태 필터 설정
function setupStatusFilter() {
    const statusButtons = document.querySelectorAll('.status-filter button');
    
    statusButtons.forEach(button => {
        button.addEventListener('click', () => {
            // 모든 버튼 비활성화
            statusButtons.forEach(btn => btn.classList.remove('active'));
            
            // 클릭된 버튼 활성화
            button.classList.add('active');
            
            // 상태 변경
            currentStatus = button.dataset.status || 'all';
            currentPage = 1;
            
            // URL 업데이트
            const url = new URL(window.location);
            if (currentStatus === 'all') {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', currentStatus);
            }
            url.searchParams.set('page', '1');
            window.history.pushState({}, '', url);
            
            // 데이터 다시 로드
            loadInquiries().then(() => {
                renderInquiryList();
            });
        });
    });
}

// 문의사항 목록 렌더링
function renderInquiryList() {
    const container = document.getElementById('inquiryListContainer');
    
    if (!container) {
        console.error('Inquiry list container not found');
        return;
    }
    
    // 총 문의 수 업데이트
    updateTotalCount();
    
    if (inquiries.length === 0) {
        // 0건일 때는 리스트 영역(<ul>) 안에 빈 상태를 렌더링해야 "오류처럼 보이는" 빈 화면이 되지 않음
        showEmptyState(getEmptyMessage(), container);
        // 페이지네이션도 숨김
        const pager = document.getElementById('inquiryPagination');
        if (pager) pager.innerHTML = '';
        return;
    }
    
    // 컨테이너는 <ul id="inquiryListContainer"> 이므로 li만 렌더링
    container.innerHTML = inquiries.map(inquiry => createInquiryItem(inquiry)).join('');

    renderPagination();
}

function renderPagination() {
    const pager = document.getElementById('inquiryPagination');
    if (!pager) return;

    const total = Number.isFinite(inquiriesTotalCount) ? inquiriesTotalCount : inquiries.length;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (totalPages <= 1) {
        pager.innerHTML = '';
        return;
    }

    // 5개 단위 그룹
    const groupSize = 5;
    const currentGroup = Math.floor((currentPage - 1) / groupSize);
    const start = currentGroup * groupSize + 1;
    const end = Math.min(totalPages, start + groupSize - 1);

    const btn = (label, page, disabled = false, active = false) => {
        const cls = [
            'btn',
            'line',
            'sm',
            disabled ? 'inactive' : '',
            active ? 'primary' : ''
        ].filter(Boolean).join(' ');
        return `<button type="button" class="${cls}" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>`;
    };

    const prevGroupPage = start - 1;
    const nextGroupPage = end + 1;

    const prevLabel = 'Previous';
    const nextLabel = 'Next';

    let html = `<div class="align vm" style="gap:8px; flex-wrap:wrap;">`;
    html += btn(prevLabel, prevGroupPage, prevGroupPage < 1);
    for (let p = start; p <= end; p++) {
        html += btn(String(p), p, false, p === currentPage);
    }
    html += btn(nextLabel, nextGroupPage, nextGroupPage > totalPages);
    html += `</div>`;

    pager.innerHTML = html;

    pager.querySelectorAll('button[data-page]').forEach(b => {
        b.addEventListener('click', () => {
            const p = parseInt(b.getAttribute('data-page'), 10);
            if (!Number.isFinite(p) || p < 1 || p > totalPages) return;
            if (p === currentPage) return;
            currentPage = p;

            const url = new URL(window.location);
            url.searchParams.set('page', String(currentPage));
            window.history.pushState({}, '', url);

            loadInquiries().then(() => renderInquiryList());
        });
    });
}

// 문의사항 아이템 HTML 생성
function createInquiryItem(inquiry) {
    const createdDate = formatDateYYYYMMDD(inquiry.createdAt);
    const statusClass = getStatusClass(inquiry.status);
    const statusText = getStatusText(inquiry.status);
    const typeText = getTypeText(inquiry.category || inquiry.inquiryType);
    const inquiryId = inquiry.inquiryId || inquiry.inquiry_id || '';
    const title = inquiry.subject || inquiry.title || 'Inquiry Title';
    const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
    
    return `
        <li class="border-bottomf3 py20 px20" style="cursor:pointer;" onclick="window.location.href='inquiry-detail.html?inquiryId=${encodeURIComponent(inquiryId)}&lang=${encodeURIComponent(lang || 'en')}'">
            <div class="align both vm">
                <p class="text fz13 fw400 lh19 gray96">${typeText}</p>
                <div class="label ${statusClass === 'status-pending' ? 'secondary' : 'primary'}">${statusText}</div>
            </div>
            <div class="text fz16 fw500 lh26 black12">${escapeHtml(title)}</div>
            <p class="text fz13 fw400 lh19 gray96 mt22">${createdDate}</p>
        </li>
    `;
}

// 총 문의 수 업데이트
function updateTotalCount() {
    const totalCountElement = document.getElementById('totalInquiriesCount');
    if (totalCountElement) {
        const totalCount = Number.isFinite(inquiriesTotalCount) ? inquiriesTotalCount : inquiries.length;
        totalCountElement.textContent = `Total ${totalCount} items`;
    }
}

// 문의 유형 텍스트 반환
function getTypeText(type) {
    // 문의 내역 요구사항 라벨(5종)로 표시
    const t = String(type || '').toLowerCase();
    // legacy/raw UI values도 함께 수용 (product/reservation/cancellation/other)
    if (t === 'booking' || t === 'reservation') return 'Reservation Inquiry';
    if (t === 'payment') return 'Payment Inquiry';
    if (t === 'complaint' || t === 'cancellation' || t === 'cancel') return 'Cancellation Inquiry';
    if (t === 'suggestion' || t === 'other') return 'Other';
    // general/visa/technical 등은 Product Inquiry로 표시(요구사항 우선)
    return 'Product Inquiry';
}

// 상태 텍스트 반환
function getStatusText(status) {
    const s = String(status || '').toLowerCase();
    // 요구사항(2가지 상태만 노출)
    if (s === 'pending' || s === 'open' || s === 'in_progress') return 'Waiting for answer';
    return 'Answer completed'; // replied/closed/resolved 등
}

// 상태 CSS 클래스 반환
function getStatusClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'pending' || s === 'open' || s === 'in_progress') return 'status-pending';
    return 'status-resolved';
}

// 우선순위 CSS 클래스 반환
function getPriorityClass(priority) {
    const classMap = {
        'high': 'priority-high',
        'medium': 'priority-medium',
        'low': 'priority-low'
    };
    return classMap[priority] || 'priority-medium';
}

// 상태별 카운트 업데이트
function updateStatusCounts(statusCounts) {
    const statusButtons = document.querySelectorAll('.status-filter button');
    
    statusButtons.forEach(button => {
        const status = button.dataset.status || 'all';
        const count = status === 'all' ? 
            Object.values(statusCounts).reduce((sum, count) => sum + count, 0) : 
            statusCounts[status] || 0;
        
        const countSpan = button.querySelector('.count');
        if (countSpan) {
            countSpan.textContent = `(${count})`;
        }
    });
}

// 빈 상태 표시
// - inquiry.php는 목록 컨테이너가 <ul id="inquiryListContainer"> 이므로, 기본은 UL 안에 <li> 형태로 렌더링해야 함
function showEmptyState(message = null, listContainer = null) {
    // 메시지가 없으면 현재 상태에 맞는 메시지 사용
    if (!message) message = getEmptyMessage();

    const ul = listContainer || document.getElementById('inquiryListContainer');
    if (!ul) return;

    // 기존 empty/loading 잔여물 정리
    try {
        ul.querySelectorAll('.empty-state, .loading-state').forEach(n => n.remove());
    } catch (_) { }

    // 리스트 영역을 명확히 채워서 "오류처럼 보이는" 빈 공백을 방지
    ul.innerHTML = `
        <li class="empty-state" style="text-align:center; padding: 40px 20px;">
            <img src="../images/ico_empty.svg" alt="" style="opacity:0.3; margin-bottom:16px;">
            <div class="text fz16 fw500 lh24 gray8">${message}</div>
        </li>
    `;
}

// 빈 상태 메시지 반환
function getEmptyMessage() {
    const messages = {
        'all': 'No inquiries.',
        'pending': 'No inquiries waiting for an answer.',
        'replied': 'No answered inquiries.',
        'closed': 'No answered inquiries.'
    };
    
    return messages[currentStatus] || 'No inquiries.';
}

// 문의사항 컨테이너 생성
function createInquiryContainer() {
    const container = document.querySelector('.px20') || document.querySelector('.main');
    if (!container) return null;
    
    const inquiryContainer = document.createElement('div');
    inquiryContainer.className = 'inquiry-list-container mt24';
    
    container.appendChild(inquiryContainer);
    return inquiryContainer;
}

// 로딩 상태 표시
function showLoadingState() {
    const container = document.getElementById('inquiryDetails') ||
                     document.querySelector('.px20') ||
                     document.querySelector('.main');
    
    if (!container) return;

    // Prevent duplicated messages when user switches tabs quickly / multiple loads overlap
    try {
        container.querySelectorAll('.loading-state').forEach(n => n.remove());
    } catch (_) { }
    
    const loadingHtml = `
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">Loading inquiries...</div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', loadingHtml);
}

// 로딩 상태 해제
function hideLoadingState() {
    try {
        document.querySelectorAll('.loading-state').forEach(n => n.remove());
    } catch (_) { }
}

function formatDateYYYYMMDD(value) {
    const t = Date.parse(String(value || ''));
    if (Number.isNaN(t)) return '';
    const d = new Date(t);
    const yyyy = String(d.getFullYear());
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}