/**
 * Reservation Notices Page JavaScript
 */

let currentBookingId = null;
let currentNoticeId = null;
let currentPage = 1;
let totalPages = 1;
let currentUserType = null;

document.addEventListener('DOMContentLoaded', function() {
    // Session check (admin/agent/guide)
    (async () => {
        try {
            const sessionRes = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionData = await sessionRes.json();
            if (!sessionData.authenticated || !['admin', 'agent', 'guide'].includes(sessionData.userType)) {
                window.location.href = '../index.html';
                return;
            }
            currentUserType = sessionData.userType;
            setupEditorUiByRole();
        } catch (e) {
            window.location.href = '../index.html';
            return;
        }
    })();

    // Read bookingId / noticeId from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentBookingId = urlParams.get('bookingId') || urlParams.get('id');
    currentNoticeId = urlParams.get('noticeId') || urlParams.get('notice_id');
    
    // Load notice list
    loadNoticeList();
    
    // If a specific notice is selected, load it; otherwise load latest for booking
    if (currentNoticeId) {
        loadNoticeDetail(currentNoticeId);
    } else if (currentBookingId) {
        // If bookingId exists, load latest notice for booking
        loadLatestNoticeForBooking();
        // guide는 기본을 "새 공지 작성" 모드로 둔다
        if (currentUserType === 'guide') {
            enterCreateMode();
        }
    }
});

function setupEditorUiByRole() {
    const actionBox = document.getElementById('notice-editor-actions');
    if (!actionBox) return;

    if (currentUserType === 'guide') {
        actionBox.style.display = 'flex';
        const newBtn = document.getElementById('newNoticeBtn');
        const regBtn = document.getElementById('registerNoticeBtn');
        newBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            enterCreateMode();
        });
        regBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            createNotice().catch((err) => {
                console.error('Failed to create notice:', err);
                alert('Failed to create notice.');
            });
        });
    } else {
        actionBox.style.display = 'none';
    }
}

function setRegisterButtonEnabled(enabled) {
    const btn = document.getElementById('registerNoticeBtn');
    if (!btn) return;
    btn.disabled = !enabled;
    btn.setAttribute('aria-disabled', String(!enabled));
}

function enterCreateMode() {
    currentNoticeId = null;
    const titleInput = document.getElementById('notice_title');
    const contentTextarea = document.getElementById('notice_content');
    const registrationInput = document.getElementById('notice_registration_datetime');

    if (registrationInput) registrationInput.value = '';
    if (titleInput) {
        titleInput.disabled = false;
        titleInput.value = '';
    }
    if (contentTextarea) {
        contentTextarea.disabled = false;
        contentTextarea.value = '';
    }
    setRegisterButtonEnabled(false);

    const onChange = () => {
        const t = (titleInput?.value || '').trim();
        const c = (contentTextarea?.value || '').trim();
        setRegisterButtonEnabled(t.length > 0 && c.length > 0);
    };
    titleInput?.addEventListener('input', onChange);
    contentTextarea?.addEventListener('input', onChange);

    // URL에서 noticeId 제거
    try {
        const url = new URL(window.location);
        url.searchParams.delete('noticeId');
        url.searchParams.delete('notice_id');
        window.history.replaceState({}, '', url);
    } catch (_) {}
}

// Load notice list
async function loadNoticeList(page = 1) {
    try {
        currentPage = page;
        
        let url = `../backend/api/agent-api.php?action=getNotices&page=${page}&limit=10`;
        if (currentBookingId) {
            url += `&bookingId=${encodeURIComponent(currentBookingId)}`;
        }
        
        const response = await fetch(url, { credentials: 'same-origin' });
        const result = await response.json();
        
        const tbody = document.getElementById('notice-list-tbody');
        if (!tbody) return;
        
        if (result.success && result.data && result.data.notices) {
            const notices = result.data.notices;
            totalPages = result.data.totalPages || 1;
            
            if (notices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="is-center" data-lan-eng="No notices">No notices.</td></tr>';
                renderPagination();
                return;
            }
            
            tbody.innerHTML = notices.map((notice, index) => {
                const rowNum = (page - 1) * 10 + index + 1;
                // 요구사항: 공통 페이지에서는 삭제건을 Status = "Delete" 로 표시하고 클릭 시 상세도 확인 가능해야 함
                const status = (notice.status === 'active' || notice.status === 'register') ? 'Registered' : 'Delete';
                
                return `
                    <tr onclick="selectNotice(${notice.noticeId || notice.announcementId || notice.id})" style="cursor:pointer;">
                        <td class="no is-center">${rowNum}</td>
                        <td class="is-center">${escapeHtml(notice.title || notice.noticeTitle || '')}</td>
                        <td class="is-center">${formatDateTime(notice.createdAt || notice.registrationDate || notice.createdDate)}</td>
                        <td class="is-center">${status}</td>
                    </tr>
                `;
            }).join('');
            
            // Render pagination
            renderPagination();
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="is-center" data-lan-eng="No notices">No notices.</td></tr>';
            renderPagination();
        }
    } catch (error) {
        console.error('Error loading notice list:', error);
        const tbody = document.getElementById('notice-list-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="4" class="is-center" data-lan-eng="Error loading notices">Failed to load notices.</td></tr>';
        }
    }
}

// Load notice detail
async function loadNoticeDetail(noticeId) {
    try {
        const response = await fetch(`../backend/api/agent-api.php?action=getNoticeDetail&noticeId=${encodeURIComponent(noticeId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const notice = result.data.notice || result.data;
            // 상세 조회 모드: 입력 잠금(가이드라도 기존 공지를 수정하게 하지 않음)
            const titleInput = document.getElementById('notice_title');
            const contentTextarea = document.getElementById('notice_content');
            if (titleInput) titleInput.disabled = true;
            if (contentTextarea) contentTextarea.disabled = true;
            setRegisterButtonEnabled(false);
            
            // Registration date/time
            const registrationInput = document.getElementById('notice_registration_datetime');
            if (registrationInput && notice.createdAt) {
                registrationInput.value = formatDateTime(notice.createdAt);
            }
            
            // Title
            if (titleInput) {
                titleInput.value = notice.title || notice.noticeTitle || '';
            }
            
            // Content
            if (contentTextarea) {
                contentTextarea.value = notice.content || notice.noticeContent || notice.description || '';
            }
        }
    } catch (error) {
        console.error('Error loading notice detail:', error);
        // Fallback
        const titleInput = document.getElementById('notice_title');
        const contentTextarea = document.getElementById('notice_content');
        if (titleInput) titleInput.value = '';
        if (contentTextarea) contentTextarea.value = '';
    }
}

// Load latest notice for booking
async function loadLatestNoticeForBooking() {
    try {
        if (!currentBookingId) return;

        const response = await fetch(`../backend/api/agent-api.php?action=getLatestNotice&bookingId=${encodeURIComponent(currentBookingId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success && result.data && result.data.notice) {
            const notice = result.data.notice;
            const registrationInput = document.getElementById('notice_registration_datetime');
            if (registrationInput) registrationInput.value = formatDateTime(notice.createdAt || '');
            const titleInput = document.getElementById('notice_title');
            if (titleInput) titleInput.value = notice.title || '';
            const contentTextarea = document.getElementById('notice_content');
            if (contentTextarea) contentTextarea.value = notice.content || '';
        }
    } catch (error) {
        console.error('Error loading latest notice for booking:', error);
    }
}

// Select notice
function selectNotice(noticeId) {
    currentNoticeId = noticeId;
    loadNoticeDetail(noticeId);
    
    // Update URL (pushState)
    const url = new URL(window.location);
    url.searchParams.set('noticeId', noticeId);
    window.history.pushState({ noticeId }, '', url);
}

async function createNotice() {
    if (!currentBookingId) {
        alert('Missing bookingId.');
        return;
    }
    const titleInput = document.getElementById('notice_title');
    const contentTextarea = document.getElementById('notice_content');
    const title = (titleInput?.value || '').trim();
    const content = (contentTextarea?.value || '').trim();
    if (!title || !content) {
        setRegisterButtonEnabled(false);
        alert('Please enter title and content.');
        return;
    }

    const form = new FormData();
    form.append('action', 'createNotice');
    form.append('bookingId', currentBookingId);
    form.append('title', title);
    form.append('content', content);

    const res = await fetch('../backend/api/agent-api.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: form,
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const result = await res.json();
    if (!result.success) throw new Error(result.message || 'Failed to create notice');

    const newId = result.data?.noticeId || result.noticeId;
    await loadNoticeList(1);
    if (newId) {
        selectNotice(newId);
    } else {
        await loadLatestNoticeForBooking();
    }
    alert('Registered.');
}

// Render pagination
function renderPagination() {
    const paginationContainer = document.getElementById('notice-pagination');
    if (!paginationContainer || totalPages <= 1) {
        if (paginationContainer) {
            paginationContainer.innerHTML = '';
        }
        return;
    }
    
    let html = '<div class="contents">';
    
    // First page button
    html += `<button type="button" class="first" aria-label="First page" ${currentPage === 1 ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadNoticeList(1)">
        <img src="../image/first.svg" alt="">
    </button>`;
    
    // Previous page button
    html += `<button type="button" class="prev" aria-label="Previous page" ${currentPage === 1 ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadNoticeList(${currentPage - 1})">
        <img src="../image/prev.svg" alt="">
    </button>`;
    
    // Page numbers
    html += '<div class="page" role="list">';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button type="button" class="p ${i === currentPage ? 'show' : ''}" role="listitem" ${i === currentPage ? 'aria-current="page"' : ''} onclick="loadNoticeList(${i})">${i}</button>`;
    }
    html += '</div>';
    
    // Next page button
    html += `<button type="button" class="next" aria-label="Next page" ${currentPage === totalPages ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadNoticeList(${currentPage + 1})">
        <img src="../image/next.svg" alt="">
    </button>`;
    
    // Last page button
    html += `<button type="button" class="last" aria-label="Last page" ${currentPage === totalPages ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadNoticeList(${totalPages})">
        <img src="../image/last.svg" alt="">
    </button>`;
    
    html += '</div>';
    paginationContainer.innerHTML = html;
}

// Utilities
function formatDateTime(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    if (isNaN(date.getTime())) return datetime;
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getCurrentLang() {
    const htmlLang = document.documentElement.getAttribute('lang');
    return htmlLang === 'en' ? 'eng' : 'ko';
}

