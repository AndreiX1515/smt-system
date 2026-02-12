/**
 * Super Admin Extra Options Management
 */
(function () {
    'use strict';

    let allBookings = [];
    let filteredBookings = [];
    let currentTab = 'all';
    const PAGE_SIZE = 20;
    let currentPage = 1;
    let rejectTargetBookingId = null;

    window.addEventListener('DOMContentLoaded', loadExtraOptions);

    async function loadExtraOptions() {
        try {
            const res = await fetch('../backend/api/super-api.php?action=getExtraOptionsListAll');
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            allBookings = json.data.bookings || [];
            updateCounts();
            applyFilters();
        } catch (e) {
            console.error('Failed to load extra options:', e);
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="8" class="is-center">Failed to load data</td></tr>';
        }
    }

    function updateCounts() {
        const counts = { all: 0, pending_payment: 0, checking: 0, confirmed: 0, rejected: 0 };
        allBookings.forEach(b => {
            const s = b.optionPaymentStatus || 'not_set';
            counts.all++;
            if (counts[s] !== undefined) counts[s]++;
        });
        document.getElementById('allCount').textContent = counts.all;
        document.getElementById('pendingPaymentCount').textContent = counts.pending_payment;
        document.getElementById('checkingCount').textContent = counts.checking;
        document.getElementById('confirmedCount').textContent = counts.confirmed;
        document.getElementById('rejectedCount').textContent = counts.rejected;
    }

    window.switchTab = function (tab) {
        currentTab = tab;
        currentPage = 1;
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        applyFilters();
    };

    window.applyFilters = function () {
        const searchType = document.getElementById('searchType').value;
        const searchInput = (document.getElementById('searchInput').value || '').trim().toLowerCase();

        filteredBookings = allBookings.filter(b => {
            if (currentTab !== 'all') {
                const s = b.optionPaymentStatus || 'not_set';
                if (s !== currentTab) return false;
            }
            if (searchInput) {
                if (searchType === 'bookingId') {
                    return (b.bookingId || '').toLowerCase().includes(searchInput);
                } else if (searchType === 'product') {
                    return (b.packageName || '').toLowerCase().includes(searchInput);
                } else if (searchType === 'agent') {
                    return (b.agentName || '').toLowerCase().includes(searchInput);
                } else {
                    return (b.bookingId || '').toLowerCase().includes(searchInput) ||
                           (b.packageName || '').toLowerCase().includes(searchInput) ||
                           (b.agentName || '').toLowerCase().includes(searchInput);
                }
            }
            return true;
        });

        document.getElementById('totalCount').textContent = filteredBookings.length;
        renderTable();
        renderPagination();
    };

    function renderTable() {
        const tbody = document.getElementById('tableBody');
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageData = filteredBookings.slice(start, start + PAGE_SIZE);

        if (pageData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="is-center">No data found</td></tr>';
            return;
        }

        tbody.innerHTML = pageData.map((b, i) => {
            const no = filteredBookings.length - start - i;
            const status = b.optionPaymentStatus || 'not_set';
            const statusLabel = getStatusLabel(status);
            const fee = b.optionPaymentAmount || b.flightOptionFee || 0;
            const feeDisplay = fee > 0 ? '₱' + Number(fee).toLocaleString('en-US', { minimumFractionDigits: 0 }) : '-';
            const travelDate = b.departureDate ? b.departureDate.substring(0, 10) : '-';

            let actionsHtml = '';
            if (status === 'checking') {
                actionsHtml = `
                    <button class="btn-sm btn-view" onclick="event.stopPropagation(); viewProof('${escapeAttr(b.bookingId)}', '${escapeAttr(b.optionPaymentFile || '')}')">View</button>
                    <button class="btn-sm btn-accept" onclick="event.stopPropagation(); confirmPayment('${escapeAttr(b.bookingId)}')">Accept</button>
                    <button class="btn-sm btn-reject" onclick="event.stopPropagation(); openRejectModal('${escapeAttr(b.bookingId)}')">Reject</button>`;
            } else if (b.optionPaymentFile) {
                actionsHtml = `<button class="btn-sm btn-view" onclick="event.stopPropagation(); viewProof('${escapeAttr(b.bookingId)}', '${escapeAttr(b.optionPaymentFile || '')}')">View</button>`;
            } else {
                actionsHtml = '<span style="color:#9ca3af;font-size:12px;">-</span>';
            }

            return `<tr>
                <td class="is-center">${no}</td>
                <td class="is-center">${escapeHtml(b.bookingId)}</td>
                <td class="is-center">${escapeHtml(b.agentName || '-')}</td>
                <td>${escapeHtml(b.packageName || '-')}</td>
                <td class="is-center">${travelDate}</td>
                <td class="is-center">${feeDisplay}</td>
                <td class="is-center"><span class="status-badge ${status}">${statusLabel}</span></td>
                <td class="is-center"><div class="action-btns">${actionsHtml}</div></td>
            </tr>`;
        }).join('');
    }

    function getStatusLabel(status) {
        const map = {
            'not_set': 'Not Set',
            'pending_payment': 'Pending Payment',
            'checking': 'Checking',
            'confirmed': 'Confirmed',
            'rejected': 'Rejected'
        };
        return map[status] || status;
    }

    window.viewProof = function (bookingId, filePath) {
        if (filePath) {
            window.open('../../../' + filePath, '_blank');
        } else {
            alert('No proof file available');
        }
    };

    window.confirmPayment = async function (bookingId) {
        if (!confirm(`Confirm option payment for booking ${bookingId}?`)) return;

        try {
            const res = await fetch('../backend/api/super-api.php?action=confirmOptionPayment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'confirmOptionPayment', bookingId: bookingId })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            alert('Payment confirmed successfully');
            loadExtraOptions();
        } catch (e) {
            alert('Failed to confirm: ' + e.message);
        }
    };

    window.openRejectModal = function (bookingId) {
        rejectTargetBookingId = bookingId;
        document.getElementById('rejectModalBookingId').textContent = bookingId;
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').classList.add('show');
    };

    window.closeRejectModal = function () {
        rejectTargetBookingId = null;
        document.getElementById('rejectModal').classList.remove('show');
    };

    window.submitReject = async function () {
        if (!rejectTargetBookingId) return;
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            alert('Please enter a rejection reason');
            return;
        }

        try {
            const res = await fetch('../backend/api/super-api.php?action=rejectOptionPayment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'rejectOptionPayment',
                    bookingId: rejectTargetBookingId,
                    reason: reason
                })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            closeRejectModal();
            alert('Payment rejected successfully');
            loadExtraOptions();
        } catch (e) {
            alert('Failed to reject: ' + e.message);
        }
    };

    function renderPagination() {
        const container = document.getElementById('pagination');
        const totalPages = Math.ceil(filteredBookings.length / PAGE_SIZE);
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        let html = '';
        html += `<button class="page-btn" onclick="goPage(1)" ${currentPage === 1 ? 'disabled' : ''}>&laquo;</button>`;
        html += `<button class="page-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&lsaquo;</button>`;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        for (let p = startPage; p <= endPage; p++) {
            html += `<button class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
        }

        html += `<button class="page-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>&rsaquo;</button>`;
        html += `<button class="page-btn" onclick="goPage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>&raquo;</button>`;
        container.innerHTML = html;
    }

    window.goPage = function (page) {
        const totalPages = Math.ceil(filteredBookings.length / PAGE_SIZE);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        renderTable();
        renderPagination();
    };

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
})();
