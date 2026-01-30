/**
 * Agent Admin - Reservation List Page JavaScript (Tab-based UI)
 */

let currentFilters = {
    search: '',
    travelStartDate: '',
    searchType: '',
    travelStartDateSort: '',
    status: ''
};

// 섹션별 상태 그룹 정의
const sectionGroups = {
    pending: ['pending', 'pending_update'],
    waiting: ['waiting_down_payment', 'waiting_second_payment', 'waiting_balance', 'waiting_full_payment'],
    checking: ['checking_down_payment', 'checking_second_payment', 'checking_balance', 'checking_full_payment'],
    rejected: ['rejected', 'check_reject'],
    confirmed: ['confirmed', 'completed'],
    cancelled: ['cancelled', 'refunded']
};

// 현재 활성 탭
let currentTab = 'waiting';

// 페이지네이션 설정
const PAGE_SIZE = 10;
const sectionData = {
    pending: [],
    waiting: [],
    checking: [],
    rejected: [],
    confirmed: [],
    cancelled: []
};
const sectionPage = {
    pending: 1,
    waiting: 1,
    checking: 1,
    rejected: 1,
    confirmed: 1,
    cancelled: 1
};

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

        // daterangepicker 이벤트 연결
        try {
            if (window.jQuery) {
                jQuery('#travelStartDate')
                    .on('apply.daterangepicker', function () { applyFilters(); })
                    .on('cancel.daterangepicker', function () { applyFilters(); });
            }
        } catch (_) { }

        await loadAllReservations();
        setupDownload();
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
});

function switchTab(tab) {
    currentTab = tab;
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    // 모든 탭 컨텐츠 숨기기
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    // 선택된 탭 활성화
    const activeBtn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
    const activeContent = document.getElementById('tab-' + tab);
    if (activeBtn) activeBtn.classList.add('active');
    if (activeContent) activeContent.classList.add('active');
}

function applyFilters() {
    currentFilters.search = document.getElementById('searchInput').value.trim();
    // 날짜 필터: "~" 구분자를 ","로 변환하여 백엔드에 전달 (범위 검색 지원)
    const dateValue = document.getElementById('travelStartDate').value.trim();
    if (dateValue.includes(' ~ ')) {
        currentFilters.travelStartDate = dateValue.replace(' ~ ', ',');
    } else {
        currentFilters.travelStartDate = dateValue;
    }
    currentFilters.searchType = document.getElementById('searchType')?.value || '';
    currentFilters.status = document.getElementById('statusFilter')?.value || '';

    // status 필터가 선택되면 해당 탭으로 자동 이동
    if (currentFilters.status) {
        const targetSection = getSectionForStatus(currentFilters.status);
        if (targetSection && targetSection !== currentTab) {
            switchTab(targetSection);
        }
    }

    loadAllReservations();
}

function getSectionForStatus(statusKey) {
    const key = String(statusKey || '').toLowerCase().trim();
    for (const [section, statuses] of Object.entries(sectionGroups)) {
        if (statuses.includes(key)) return section;
    }
    return 'pending'; // 기본값
}

async function loadAllReservations() {
    // 모든 섹션 로딩 상태로 변경
    ['pending', 'waiting', 'checking', 'rejected', 'confirmed', 'cancelled'].forEach(section => {
        const tbody = document.getElementById('tbody-' + section);
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="is-center">Loading...</td></tr>';
    });

    try {
        // 전체 데이터를 가져오기 위해 limit을 크게 설정
        const params = new URLSearchParams({
            action: 'getReservations',
            page: 1,
            limit: 1000
        });

        if (currentFilters.search) params.append('search', currentFilters.search);
        if (currentFilters.searchType) params.append('searchType', currentFilters.searchType);
        if (currentFilters.travelStartDate) params.append('travelStartDate', currentFilters.travelStartDate);
        if (currentFilters.travelStartDateSort) params.append('travelStartDateSort', currentFilters.travelStartDateSort);

        const response = await fetch(`../backend/api/agent-api.php?${params.toString()}`, {
            credentials: 'same-origin'
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);

        const result = await response.json();

        if (result.success && result.data) {
            const { reservations } = result.data;

            // 섹션별로 그룹화
            const grouped = {
                pending: [],
                waiting: [],
                checking: [],
                rejected: [],
                confirmed: [],
                cancelled: []
            };

            // status 필터 적용
            const statusFilter = currentFilters.status;

            reservations.forEach(reservation => {
                const statusKey = String(reservation.bookingStatus || reservation.statusKey || '').toLowerCase().trim();

                // status 필터가 설정되어 있으면 해당 상태만 포함
                if (statusFilter && statusKey !== statusFilter) {
                    return;
                }

                const section = getSectionForStatus(statusKey);
                grouped[section].push(reservation);
            });

            // 섹션별 데이터 저장 및 페이지 초기화
            let totalCount = 0;
            for (const [section, list] of Object.entries(grouped)) {
                sectionData[section] = list;
                sectionPage[section] = 1;
                totalCount += list.length;
                renderSection(section);
            }

            // 전체 카운트 표시
            const totalCountEl = document.getElementById('totalCount');
            if (totalCountEl) totalCountEl.textContent = totalCount;

            // 다국어 적용
            try {
                const lang = (typeof getCookie === 'function' ? (getCookie('lang') || 'eng') : 'eng');
                if (typeof language_apply === 'function') language_apply(lang);
            } catch (_) { }
        } else {
            ['pending', 'waiting', 'checking', 'rejected', 'confirmed', 'cancelled'].forEach(section => {
                const tbody = document.getElementById('tbody-' + section);
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="is-center">Failed to load data.</td></tr>';
            });
        }
    } catch (error) {
        console.error('Error loading reservations:', error);
        ['pending', 'waiting', 'checking', 'rejected', 'confirmed', 'cancelled'].forEach(section => {
            const tbody = document.getElementById('tbody-' + section);
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="is-center">Error loading data.</td></tr>';
        });
    }
}

function renderSection(section) {
    const tbody = document.getElementById('tbody-' + section);
    const countEl = document.getElementById(section + 'Count');
    const paginationEl = document.getElementById('pagination-' + section);
    const reservations = sectionData[section] || [];
    const currentPage = sectionPage[section] || 1;
    const totalPages = Math.ceil(reservations.length / PAGE_SIZE);

    if (countEl) countEl.textContent = reservations.length;

    if (!tbody) return;

    if (reservations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="is-center" data-lan-eng="No data">No data</td></tr>';
        if (paginationEl) paginationEl.innerHTML = '';
        return;
    }

    // 현재 페이지 데이터만 추출
    const startIdx = (currentPage - 1) * PAGE_SIZE;
    const endIdx = startIdx + PAGE_SIZE;
    const pageReservations = reservations.slice(startIdx, endIdx);

    tbody.innerHTML = pageReservations.map((item, index) => {
        const st = getStatusUi(item);
        const globalIndex = startIdx + index;
        const numPeople = (typeof item.numPeople === 'number') ? String(item.numPeople) : (item.numPeople || '-');
        const customerName = item.customerName || item.reserverName || item.bookingName || item.travelerName || 'N/A';

        return `
            <tr onclick="window.location.href='reservation-detail.html?id=${escapeHtml(item.bookingId || '')}'" style="cursor: pointer;">
                <td class="no is-center">${globalIndex + 1}</td>
                <td class="td-ellipsis"><span>${escapeHtml(decodeHtmlEntities(item.packageName) || '')}</span></td>
                <td class="is-center">${escapeHtml(item.departureDate || item.travelStartDate || '')}</td>
                <td class="is-center">${escapeHtml(customerName)}</td>
                <td class="is-center">${escapeHtml(numPeople)}</td>
                <td class="is-center"><span class="badge ${escapeHtml(st.className)}" data-lan-eng="${escapeHtml(st.dataLanEng)}">${escapeHtml(st.label)}</span></td>
            </tr>
        `;
    }).join('');

    // 페이지네이션 렌더링
    renderPagination(section, currentPage, totalPages);
}

function renderPagination(section, currentPage, totalPages) {
    const paginationEl = document.getElementById('pagination-' + section);
    if (!paginationEl) return;

    if (totalPages <= 1) {
        paginationEl.innerHTML = '';
        return;
    }

    let html = '';

    // 이전 버튼
    html += `<button class="page-btn" onclick="goToPage('${section}', ${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&lt;</button>`;

    // 페이지 번호들
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    if (startPage > 1) {
        html += `<button class="page-btn" onclick="goToPage('${section}', 1)">1</button>`;
        if (startPage > 2) {
            html += `<span class="page-info">...</span>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage('${section}', ${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="page-info">...</span>`;
        }
        html += `<button class="page-btn" onclick="goToPage('${section}', ${totalPages})">${totalPages}</button>`;
    }

    // 다음 버튼
    html += `<button class="page-btn" onclick="goToPage('${section}', ${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>&gt;</button>`;

    paginationEl.innerHTML = html;
}

function goToPage(section, page) {
    const reservations = sectionData[section] || [];
    const totalPages = Math.ceil(reservations.length / PAGE_SIZE);

    if (page < 1 || page > totalPages) return;

    sectionPage[section] = page;
    renderSection(section);

    // 다국어 재적용
    try {
        const lang = (typeof getCookie === 'function' ? (getCookie('lang') || 'eng') : 'eng');
        if (typeof language_apply === 'function') language_apply(lang);
    } catch (_) { }
}

function getStatusUi(item) {
    const statusKey = String(item.bookingStatus || item.statusKey || '').toLowerCase().trim();

    const statusMap = {
        'pending': { label: '대기중', className: 'badge-pending', dataLanEng: 'Pending' },
        'pending_update': { label: '수정 대기', className: 'badge-pending-update', dataLanEng: 'Pending Update' },
        'waiting_down_payment': { label: 'Down Payment 대기', className: 'badge-wait-down', dataLanEng: 'Waiting for Down Payment' },
        'checking_down_payment': { label: 'Down Payment 확인중', className: 'badge-check-down', dataLanEng: 'Checking Down Payment' },
        'waiting_second_payment': { label: 'Second Payment 대기', className: 'badge-wait-second', dataLanEng: 'Waiting for Second Payment' },
        'checking_second_payment': { label: 'Second Payment 확인중', className: 'badge-check-second', dataLanEng: 'Checking Second Payment' },
        'waiting_balance': { label: 'Balance 대기', className: 'badge-wait-balance', dataLanEng: 'Waiting for Balance' },
        'checking_balance': { label: 'Balance 확인중', className: 'badge-check-balance', dataLanEng: 'Checking Balance' },
        'waiting_full_payment': { label: 'Full Payment 대기', className: 'badge-wait-full', dataLanEng: 'Waiting for Full Payment' },
        'checking_full_payment': { label: 'Full Payment 확인중', className: 'badge-check-full', dataLanEng: 'Checking Full Payment' },
        'rejected': { label: '증빙 거절', className: 'badge-rejected', dataLanEng: 'Payment Rejected' },
        'check_reject': { label: '거절 확인 필요', className: 'badge-check-reject', dataLanEng: 'Check Reject' },
        'confirmed': { label: '예약 확정', className: 'badge-confirmed', dataLanEng: 'Reservation Confirmed' },
        'completed': { label: '여행 완료', className: 'badge-completed', dataLanEng: 'Trip Completed' },
        'cancelled': { label: '예약 취소', className: 'badge-cancelled', dataLanEng: 'Reservation Cancelled' },
        'refunded': { label: '환불 완료', className: 'badge-refunded', dataLanEng: 'Refund Completed' }
    };

    if (statusMap[statusKey]) {
        return statusMap[statusKey];
    }

    if (statusKey === 'partial') return statusMap['waiting_second_payment'];

    return { label: statusKey || '대기', className: 'badge-pending', dataLanEng: 'Pending' };
}

function setupDownload() {
    const btn = document.getElementById('downloadCsvBtn');
    if (!btn || btn._bound) return;
    btn.addEventListener('click', () => {
        const params = new URLSearchParams({ action: 'downloadReservations' });
        if (currentFilters.search) params.append('search', currentFilters.search);
        if (currentFilters.searchType) params.append('searchType', currentFilters.searchType);
        if (currentFilters.travelStartDate) params.append('travelStartDate', currentFilters.travelStartDate);
        if (currentFilters.status) params.append('status', currentFilters.status);
        window.location.href = `../backend/api/agent-api.php?${params.toString()}`;
    });
    btn._bound = true;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function decodeHtmlEntities(str) {
    if (!str) return str;
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}

// Travel start date 정렬 토글 기능
function toggleTravelDateSort() {
    // 정렬 상태 토글: '' -> 'asc' -> 'desc' -> ''
    if (currentFilters.travelStartDateSort === '') {
        currentFilters.travelStartDateSort = 'asc';
    } else if (currentFilters.travelStartDateSort === 'asc') {
        currentFilters.travelStartDateSort = 'desc';
    } else {
        currentFilters.travelStartDateSort = '';
    }

    // UI 업데이트
    updateSortIcon();

    // 데이터 다시 로드
    loadAllReservations();
}

// 정렬 아이콘 UI 업데이트
function updateSortIcon() {
    // 모든 sortable 헤더에서 정렬 클래스 제거 후 현재 상태 적용
    document.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (currentFilters.travelStartDateSort === 'asc') {
            th.classList.add('sort-asc');
        } else if (currentFilters.travelStartDateSort === 'desc') {
            th.classList.add('sort-desc');
        }
    });
}
