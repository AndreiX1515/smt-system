/**
 * Agent Extra Options Detail
 */
(function () {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const bookingId = params.get('id');

    let bookingData = null;
    let travelersData = [];
    let travelerOptionsData = {};
    let categoriesData = [];
    let paymentInfoData = null;

    if (!bookingId) {
        alert('Booking ID is required');
        location.href = 'extra-options.html';
        return;
    }

    window.addEventListener('DOMContentLoaded', loadDetail);

    async function loadDetail() {
        try {
            const res = await fetch(`../backend/api/agent-api.php?action=getExtraOptionsDetail&bookingId=${encodeURIComponent(bookingId)}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            bookingData = json.data.booking;
            travelersData = json.data.travelers || [];
            travelerOptionsData = json.data.travelerOptions || {};
            categoriesData = json.data.categories || [];
            paymentInfoData = json.data.paymentInfo;

            renderSummary();
            renderTravelerOptions();
            updateTotalFee();
            renderPaymentProof();
            checkDeadline();
        } catch (e) {
            console.error('Failed to load detail:', e);
            alert('Failed to load data: ' + e.message);
        }
    }

    function renderSummary() {
        document.getElementById('summaryBookingId').textContent = bookingData.bookingId || '-';
        document.getElementById('summaryProductName').textContent = bookingData.packageName || '-';

        const dep = bookingData.departureDate ? bookingData.departureDate.substring(0, 10) : '';
        const ret = bookingData.returnDate ? bookingData.returnDate.substring(0, 10) : '';
        document.getElementById('summaryTravelPeriod').textContent = dep && ret ? `${dep} ~ ${ret}` : dep || '-';

        document.getElementById('summaryPax').textContent = `${bookingData.pax || 0} (Adult: ${bookingData.adults || 0}, Child: ${bookingData.children || 0}, Infant: ${bookingData.infants || 0})`;
    }

    function renderTravelerOptions() {
        const container = document.getElementById('travelerOptionsContainer');

        if (travelersData.length === 0) {
            container.innerHTML = '<p class="is-center" style="color:#6b7280;">No travelers found for this booking</p>';
            return;
        }

        if (categoriesData.length === 0) {
            container.innerHTML = '<p class="is-center" style="color:#6b7280;">No flight options available for this package\'s airline</p>';
            return;
        }

        container.innerHTML = travelersData.map((traveler, idx) => {
            const name = `${traveler.firstName || ''} ${traveler.lastName || ''}`.trim() || `Traveler ${idx + 1}`;
            const type = traveler.travelerType || 'adult';
            const selectedOptions = travelerOptionsData[idx] || [];
            const selectedIds = selectedOptions.map(o => o.optionId);

            let categoriesHtml = categoriesData.map(cat => {
                const optionsHtml = cat.options.map(opt => {
                    const checked = selectedIds.includes(opt.option_id) ? 'checked' : '';
                    return `<div class="option-item">
                        <label>
                            <input type="checkbox" name="opt_${idx}_${cat.category_id}" value="${opt.option_id}"
                                   data-traveler="${idx}" data-option="${opt.option_id}" data-price="${opt.price}"
                                   data-category="${cat.category_id}"
                                   ${checked} onchange="onOptionChange(this)">
                            ${escapeHtml(opt.option_name_en || opt.option_name)}
                        </label>
                        <span class="option-price">₱${Number(opt.price).toLocaleString('en-US')}</span>
                    </div>`;
                }).join('');

                return `<div class="option-category">
                    <div class="option-category-title">${escapeHtml(cat.category_name_en || cat.category_name)}</div>
                    ${optionsHtml}
                </div>`;
            }).join('');

            return `<div class="traveler-card" id="travelerCard_${idx}">
                <div class="traveler-card-header">
                    <h4>${escapeHtml(name)}</h4>
                    <span class="traveler-type-badge ${type}">${type}</span>
                </div>
                ${categoriesHtml}
                <div class="traveler-subtotal" id="subtotal_${idx}">Subtotal: ₱0</div>
            </div>`;
        }).join('');

        // 초기 서브토탈 계산
        travelersData.forEach((_, idx) => updateSubtotal(idx));
    }

    window.onOptionChange = function (checkbox) {
        const travelerIdx = parseInt(checkbox.dataset.traveler);
        const categoryId = checkbox.dataset.category;

        // 같은 카테고리 내에서는 하나만 선택 가능 (라디오 동작)
        if (checkbox.checked) {
            const sameCat = document.querySelectorAll(`input[data-traveler="${travelerIdx}"][data-category="${categoryId}"]`);
            sameCat.forEach(cb => {
                if (cb !== checkbox) cb.checked = false;
            });
        }

        updateSubtotal(travelerIdx);
        updateTotalFee();
    };

    function updateSubtotal(travelerIdx) {
        const checkboxes = document.querySelectorAll(`input[data-traveler="${travelerIdx}"]:checked`);
        let subtotal = 0;
        checkboxes.forEach(cb => subtotal += parseFloat(cb.dataset.price) || 0);
        const el = document.getElementById(`subtotal_${travelerIdx}`);
        if (el) el.textContent = `Subtotal: ₱${Number(subtotal).toLocaleString('en-US')}`;
    }

    function updateTotalFee() {
        let total = 0;
        document.querySelectorAll('input[data-traveler]:checked').forEach(cb => {
            total += parseFloat(cb.dataset.price) || 0;
        });
        document.getElementById('totalOptionFee').textContent = `₱${Number(total).toLocaleString('en-US')}`;
    }

    function getSelectedOptions() {
        const options = [];
        document.querySelectorAll('input[data-traveler]:checked').forEach(cb => {
            options.push({
                travelerIndex: parseInt(cb.dataset.traveler),
                optionId: parseInt(cb.dataset.option),
                price: parseFloat(cb.dataset.price) || 0
            });
        });
        return options;
    }

    function getTotalFee() {
        let total = 0;
        document.querySelectorAll('input[data-traveler]:checked').forEach(cb => {
            total += parseFloat(cb.dataset.price) || 0;
        });
        return total;
    }

    window.saveOptions = async function () {
        const travelerOptions = getSelectedOptions();
        const totalOptionFee = getTotalFee();

        try {
            const res = await fetch('../backend/api/agent-api.php?action=saveExtraOptions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'saveExtraOptions',
                    bookingId: bookingId,
                    travelerOptions: travelerOptions,
                    totalOptionFee: totalOptionFee
                })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            alert('Options saved successfully!');
            // 결제 증빙 섹션 표시 업데이트
            loadDetail();
        } catch (e) {
            console.error('Failed to save options:', e);
            alert('Failed to save: ' + e.message);
        }
    };

    function renderPaymentProof() {
        const section = document.getElementById('paymentProofSection');
        const statusBadge = document.getElementById('paymentStatusBadge');
        const rejectionAlert = document.getElementById('rejectionAlert');
        const fileDisplay = document.getElementById('fileDisplay');
        const fileUpload = document.getElementById('fileUpload');
        const deleteBtn = document.getElementById('deleteFileBtn');

        const status = paymentInfoData?.status || 'not_set';

        // pending_payment 이상이면 결제 증빙 섹션 표시
        if (status === 'not_set') {
            // 옵션이 있는데 아직 저장 안 된 경우 - 옵션 저장 후 표시됨
            // 이미 저장했으면 pending_payment이 됨
            section.style.display = 'none';
            return;
        }
        section.style.display = '';

        // 상태 뱃지
        const statusLabels = {
            'not_set': 'Not Set',
            'pending_payment': 'Pending Payment',
            'checking': 'Checking',
            'confirmed': 'Confirmed',
            'rejected': 'Rejected'
        };
        statusBadge.textContent = statusLabels[status] || status;
        statusBadge.className = 'status-badge ' + status;

        // 반려 알림
        if (status === 'rejected' && paymentInfoData.rejectionReason) {
            rejectionAlert.style.display = '';
            document.getElementById('rejectionReason').textContent = paymentInfoData.rejectionReason || 'No reason provided';
            document.getElementById('rejectionDate').textContent = paymentInfoData.rejectedAt ? `Rejected at: ${paymentInfoData.rejectedAt}` : '';
        } else {
            rejectionAlert.style.display = 'none';
        }

        // 파일 표시
        if (paymentInfoData.file) {
            fileDisplay.style.display = 'flex';
            document.getElementById('fileName').textContent = paymentInfoData.fileName || 'Payment proof';
            // confirmed면 삭제 버튼 숨김
            deleteBtn.style.display = (status === 'confirmed') ? 'none' : '';
            fileUpload.style.display = 'none';
        } else {
            fileDisplay.style.display = 'none';
            // pending_payment 또는 rejected 상태면 업로드 가능
            fileUpload.style.display = (status === 'pending_payment' || status === 'rejected') ? '' : 'none';
        }
    }

    window.uploadFile = async function () {
        const fileInput = document.getElementById('fileInput');
        if (!fileInput.files.length) return;

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('action', 'uploadOptionPaymentProof');
        formData.append('bookingId', bookingId);

        try {
            const res = await fetch('../backend/api/agent-api.php?action=uploadOptionPaymentProof', {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            alert('Payment proof uploaded successfully!');
            fileInput.value = '';
            loadDetail();
        } catch (e) {
            console.error('Failed to upload:', e);
            alert('Failed to upload: ' + e.message);
        }
    };

    window.viewFile = function () {
        if (paymentInfoData?.file) {
            window.open('../../../' + paymentInfoData.file, '_blank');
        }
    };

    window.downloadFile = function () {
        if (paymentInfoData?.file) {
            const a = document.createElement('a');
            a.href = '../../../' + paymentInfoData.file;
            a.download = paymentInfoData.fileName || 'payment_proof';
            a.click();
        }
    };

    window.deleteFile = async function () {
        if (!confirm('Are you sure you want to delete this payment proof?')) return;

        try {
            const res = await fetch('../backend/api/agent-api.php?action=deleteOptionPaymentProof', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'deleteOptionPaymentProof',
                    bookingId: bookingId
                })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            alert('Payment proof deleted successfully');
            loadDetail();
        } catch (e) {
            console.error('Failed to delete:', e);
            alert('Failed to delete: ' + e.message);
        }
    };

    function checkDeadline() {
        if (!bookingData || !bookingData.departureDate) return;
        const dep = new Date(bookingData.departureDate.substring(0, 10) + 'T00:00:00');
        const now = new Date();
        const diffDays = Math.ceil((dep - now) / (1000 * 60 * 60 * 24));

        if (diffDays < 7) {
            // 체크박스 모두 비활성화
            document.querySelectorAll('input[data-traveler]').forEach(cb => cb.disabled = true);

            // Save 버튼 비활성화
            const saveBtn = document.getElementById('saveOptionsBtn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.5';
                saveBtn.style.cursor = 'not-allowed';
            }

            // 안내 메시지 삽입
            const section = document.getElementById('flightOptionsSection');
            if (section && !document.getElementById('deadlineNotice')) {
                const notice = document.createElement('div');
                notice.id = 'deadlineNotice';
                notice.style.cssText = 'margin: 12px 0 0; padding: 10px 14px; background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 6px; color: #92400E; font-size: 13px; font-weight: 500;';
                notice.textContent = 'Options can only be changed up to 7 days before departure.';
                section.querySelector('.card-panel-divider').after(notice);
            }
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
