/**
 * Super Admin - Travel Document List
 */

const PAGE_SIZE = 10;
let allBookings = [];
let currentPage = 1;
let currentFilters = {
    search: '',
    agentName: '',
    travelStartDate: ''
};

document.addEventListener('DOMContentLoaded', async () => {
    // Session check
    try {
        const res = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
        const session = await res.json();
        if (!session.authenticated) {
            window.location.href = '../index.html';
            return;
        }
    } catch (e) {
        window.location.href = '../index.html';
        return;
    }

    initDatepicker();
    restoreFilters();
    await loadBookings();

    // Enter key on search
    document.getElementById('searchInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') applyFilters();
    });
    document.getElementById('agentSearch').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') applyFilters();
    });
});

function initDatepicker() {
    $('#travelStartDate').daterangepicker({
        autoUpdateInput: false,
        locale: { cancelLabel: 'Clear', format: 'YYYY-MM-DD' }
    });
    $('#travelStartDate').on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
        applyFilters();
    });
    $('#travelStartDate').on('cancel.daterangepicker', function () {
        $(this).val('');
        applyFilters();
    });
}

function applyFilters() {
    currentFilters.search = document.getElementById('searchInput').value.trim();
    currentFilters.agentName = document.getElementById('agentSearch').value.trim();
    const dateVal = document.getElementById('travelStartDate').value.trim();
    currentFilters.travelStartDate = dateVal;
    currentPage = 1;
    saveFilters();
    loadBookings();
}

function saveFilters() {
    sessionStorage.setItem('travelDocSuperFilters', JSON.stringify({
        ...currentFilters,
        travelStartDateDisplay: document.getElementById('travelStartDate').value
    }));
}

function restoreFilters() {
    const saved = sessionStorage.getItem('travelDocSuperFilters');
    if (saved) {
        try {
            const f = JSON.parse(saved);
            currentFilters = { search: f.search || '', agentName: f.agentName || '', travelStartDate: f.travelStartDate || '' };
            document.getElementById('searchInput').value = f.search || '';
            document.getElementById('agentSearch').value = f.agentName || '';
            if (f.travelStartDateDisplay) document.getElementById('travelStartDate').value = f.travelStartDateDisplay;
        } catch (e) { }
    }
}

async function loadBookings() {
    const params = new URLSearchParams({ action: 'getTravelDocumentBookings' });
    if (currentFilters.search) params.set('search', currentFilters.search);
    if (currentFilters.agentName) params.set('agentName', currentFilters.agentName);
    if (currentFilters.travelStartDate) params.set('travelStartDate', currentFilters.travelStartDate);

    try {
        const res = await fetch(`../backend/api/super-api.php?${params}`, { credentials: 'same-origin' });
        const result = await res.json();
        if (result.success) {
            allBookings = result.data.bookings || [];
        } else {
            allBookings = [];
        }
    } catch (e) {
        console.error('Failed to load bookings:', e);
        allBookings = [];
    }

    document.getElementById('totalCount').textContent = allBookings.length;
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('bookingTableBody');
    const startIdx = (currentPage - 1) * PAGE_SIZE;
    const pageItems = allBookings.slice(startIdx, startIdx + PAGE_SIZE);

    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="is-center" style="padding:40px;color:#999;">No bookings found</td></tr>';
        document.getElementById('paginationWrap').innerHTML = '';
        return;
    }

    tbody.innerHTML = pageItems.map((b, i) => {
        const isConfirmed = b.bookingStatus === 'confirmed';
        const statusClass = isConfirmed ? 'badge-confirmed' : 'badge-completed';
        const statusLabel = isConfirmed ? 'Confirmed' : 'Completed';
        return `<tr style="cursor:pointer;" onclick="window.location.href='travel-document-detail.html?bookingId=${escapeHtml(b.bookingId)}'">
            <td class="no is-center">${startIdx + i + 1}</td>
            <td class="is-center" style="white-space:nowrap;">${escapeHtml(b.bookingId)}</td>
            <td>${escapeHtml(b.packageName || '-')}</td>
            <td class="is-center" style="white-space:nowrap;">${escapeHtml(b.departureDate || '-')}</td>
            <td class="is-center">${b.numberOfPeople || '-'}</td>
            <td class="is-center">${escapeHtml(b.agentName || '-')}</td>
            <td class="is-center show"><span class="badge ${statusClass}" style="white-space:nowrap;">${statusLabel}</span></td>
        </tr>`;
    }).join('');

    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(allBookings.length / PAGE_SIZE);
    if (totalPages <= 1) {
        document.getElementById('paginationWrap').innerHTML = '';
        return;
    }

    let html = '';
    html += `<button class="page-btn" ${currentPage <= 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})">&lt;</button>`;

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, startPage + 4);

    for (let p = startPage; p <= endPage; p++) {
        html += `<button class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goToPage(${p})">${p}</button>`;
    }

    html += `<button class="page-btn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})">&gt;</button>`;
    document.getElementById('paginationWrap').innerHTML = html;
}

function goToPage(page) {
    const totalPages = Math.ceil(allBookings.length / PAGE_SIZE);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
