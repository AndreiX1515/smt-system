/**
 * Agent Admin - Reservation Detail Page JavaScript
 */

let currentBookingId = null;
let isEditMode = false;
let currentBookingData = null; // 현재 예약 데이터 저장
let originalCustomerData = {}; // 수정 전 고객 정보 백업
let originalTravelerData = []; // 수정 전 여행자 정보 백업
let isEditAllowed = false; // Admin이 수정 허용 시 true

// URL 정규화 함수 (passport, visa 등 파일 URL 처리)
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

// DOM이 완전히 로드된 후 실행
function initializePage() {
    // URL에서 bookingId 가져오기
    const urlParams = new URLSearchParams(window.location.search);
    currentBookingId = urlParams.get('id') || urlParams.get('bookingId');
    
    if (currentBookingId) {
        // requestAnimationFrame을 사용하여 DOM이 완전히 준비된 후 데이터 로드
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                loadReservationDetail();
            });
        });
    } else {
        showError('Booking ID is missing.');
    }

    // 공통 페이지 링크에 bookingId를 붙여서(새 탭) 해당 예약 컨텍스트로 열리도록 함
    try {
        const meetingLink = document.getElementById('meetingLocationLink');
        if (meetingLink && currentBookingId) {
            meetingLink.href = `../common/reservation-location.html?id=${encodeURIComponent(currentBookingId)}`;
        }
        const noticeLink = document.getElementById('announcementsLink');
        if (noticeLink && currentBookingId) {
            noticeLink.href = `../common/reservation-notices.html?id=${encodeURIComponent(currentBookingId)}`;
        }
    } catch (_) {}
    
    // 에이전트는 예약 상태를 수정할 수 없음 (요구사항) → 저장 버튼 숨김
    const saveButton = document.getElementById('saveBtn') || document.querySelector('.page-toolbar-actions .jw-button.typeB');
    if (saveButton) {
        saveButton.style.display = 'none';
    }
    
    // 에이전트는 예약 취소(상태 변경)를 할 수 없음 → 버튼 숨김
    const cancelBtn = document.getElementById('cancelReservationBtn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
    
    // 에이전트는 상태 변경 불가 → select 비활성(표시만)
    const statusSelect = document.getElementById('reservationStatusSelect') || document.querySelector('.page-toolbar-actions select');
    if (statusSelect) {
        statusSelect.disabled = true;
        statusSelect.setAttribute('aria-disabled', 'true');
    }
    
    // 3단계 결제 기한 설정 버튼 이벤트
    const setDownPaymentDeadlineBtn = document.getElementById('setDownPaymentDeadlineBtn');
    if (setDownPaymentDeadlineBtn) {
        setDownPaymentDeadlineBtn.addEventListener('click', () => {
            openDeadlineModal('down');
        });
    }

    const setSecondPaymentDeadlineBtn = document.getElementById('setSecondPaymentDeadlineBtn');
    if (setSecondPaymentDeadlineBtn) {
        setSecondPaymentDeadlineBtn.addEventListener('click', () => {
            openDeadlineModal('second');
        });
    }

    const setBalanceDeadlineBtn = document.getElementById('setBalanceDeadlineBtn');
    if (setBalanceDeadlineBtn) {
        setBalanceDeadlineBtn.addEventListener('click', () => {
            openDeadlineModal('balance');
        });
    }
    
    // 3단계 결제 증빙 파일 업로드 버튼 이벤트
    // Down Payment
    const uploadDownFileBtn = document.getElementById('uploadDownFileBtn');
    const downFileInput = document.getElementById('down_file_input');
    if (uploadDownFileBtn && downFileInput) {
        uploadDownFileBtn.addEventListener('click', () => downFileInput.click());
        downFileInput.addEventListener('change', (e) => handlePaymentFileUpload(e, 'down'));
    }

    // Second Payment
    const uploadSecondFileBtn = document.getElementById('uploadSecondFileBtn');
    const secondFileInput = document.getElementById('second_file_input');
    if (uploadSecondFileBtn && secondFileInput) {
        uploadSecondFileBtn.addEventListener('click', () => secondFileInput.click());
        secondFileInput.addEventListener('change', (e) => handlePaymentFileUpload(e, 'second'));
    }

    // Balance
    const uploadBalanceFileBtn = document.getElementById('uploadBalanceFileBtn');
    const balanceFileInput = document.getElementById('balance_file_input');
    if (uploadBalanceFileBtn && balanceFileInput) {
        uploadBalanceFileBtn.addEventListener('click', () => balanceFileInput.click());
        balanceFileInput.addEventListener('change', (e) => handlePaymentFileUpload(e, 'balance'));
    }

    // 3단계 결제 증빙 파일 다운로드/삭제 버튼 이벤트
    // Down Payment
    const downloadDownFileBtn = document.getElementById('downloadDownFileBtn');
    const deleteDownFileBtn = document.getElementById('deleteDownFileBtn');
    if (downloadDownFileBtn) {
        downloadDownFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            downloadPaymentFile('down');
        });
    }
    if (deleteDownFileBtn) {
        deleteDownFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            deletePaymentFile('down');
        });
    }

    // Second Payment
    const downloadSecondFileBtn = document.getElementById('downloadSecondFileBtn');
    const deleteSecondFileBtn = document.getElementById('deleteSecondFileBtn');
    if (downloadSecondFileBtn) {
        downloadSecondFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            downloadPaymentFile('second');
        });
    }
    if (deleteSecondFileBtn) {
        deleteSecondFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            deletePaymentFile('second');
        });
    }

    // Balance
    const downloadBalanceFileBtn = document.getElementById('downloadBalanceFileBtn');
    const deleteBalanceFileBtn = document.getElementById('deleteBalanceFileBtn');
    if (downloadBalanceFileBtn) {
        downloadBalanceFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            downloadPaymentFile('balance');
        });
    }
    if (deleteBalanceFileBtn) {
        deleteBalanceFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            deletePaymentFile('balance');
        });
    }

    // 3단계 결제 증빙 파일 View 버튼 이벤트
    // Down Payment View
    const viewDownFileBtn = document.getElementById('viewDownFileBtn');
    if (viewDownFileBtn) {
        viewDownFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewPaymentFile('down');
        });
    }

    // Second Payment View
    const viewSecondFileBtn = document.getElementById('viewSecondFileBtn');
    if (viewSecondFileBtn) {
        viewSecondFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewPaymentFile('second');
        });
    }

    // Balance View
    const viewBalanceFileBtn = document.getElementById('viewBalanceFileBtn');
    if (viewBalanceFileBtn) {
        viewBalanceFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewPaymentFile('balance');
        });
    }

    // Full Payment View
    const viewFullFileBtn = document.getElementById('viewFullFileBtn');
    if (viewFullFileBtn) {
        viewFullFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewPaymentFile('full');
        });
    }

    // 파일 뷰어 모달 닫기 이벤트
    const fileViewerModal = document.getElementById('file-viewer-modal');
    const fileViewerClose = document.getElementById('file-viewer-close');
    if (fileViewerModal) {
        if (fileViewerClose) {
            fileViewerClose.addEventListener('click', closeFileViewer);
        }
        // 모달 배경 클릭 시 닫기
        fileViewerModal.addEventListener('click', (e) => {
            if (e.target === fileViewerModal) {
                closeFileViewer();
            }
        });
    }

    // 기한 설정 모달 확인 버튼
    const confirmDeadlineBtn = document.getElementById('confirmDeadlineBtn');
    if (confirmDeadlineBtn) {
        confirmDeadlineBtn.addEventListener('click', handleSetDeadline);
    }

    // 예약 취소 확인 버튼
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', handleCancelReservation);
    }

    // Product Information 수정 버튼 이벤트
    const editProductBtn = document.getElementById('editProductBtn');
    const saveProductBtn = document.getElementById('saveProductBtn');
    const cancelProductBtn = document.getElementById('cancelProductBtn');

    if (editProductBtn) {
        editProductBtn.addEventListener('click', () => {
            handleEditProductClick();
        });
    }
    if (saveProductBtn) {
        saveProductBtn.addEventListener('click', () => {
            saveProductInfo();
        });
    }
    if (cancelProductBtn) {
        cancelProductBtn.addEventListener('click', () => {
            cancelProductEdit();
        });
    }

    // Acknowledge Reject 버튼 이벤트 (check_reject 상태에서 원래 상태로 복원)
    const acknowledgeRejectBtn = document.getElementById('acknowledgeRejectBtn');
    if (acknowledgeRejectBtn) {
        acknowledgeRejectBtn.addEventListener('click', handleAcknowledgeReject);
    }

    // 하단 고정 바 버튼 이벤트
    const bottomSaveBtn = document.getElementById('bottomSaveBtn');
    const bottomAcknowledgeRejectBtn = document.getElementById('bottomAcknowledgeRejectBtn');
    if (bottomSaveBtn) {
        bottomSaveBtn.addEventListener('click', handleSave);
    }
    if (bottomAcknowledgeRejectBtn) {
        bottomAcknowledgeRejectBtn.addEventListener('click', handleAcknowledgeReject);
    }
}

// check_reject 상태에서 확인 후 원래 상태로 복원
async function handleAcknowledgeReject() {
    if (!currentBookingId) {
        alert('Booking ID is missing.');
        return;
    }

    if (!confirm('Confirm rejection and restore to previous status?')) return;

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'acknowledgeRejection',
                bookingId: currentBookingId
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Status restored successfully.');
            window.location.reload();
        } else {
            alert('Failed to restore status: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Acknowledge reject error:', error);
        alert('An error occurred while restoring status.');
    }
}

// Product Edit 계속하기 (Edit in Progress 상태에서)
function continueProductEdit(bookingId) {
    window.location.href = `create-reservation.html?mode=edit&bookingId=${bookingId}`;
}

// Product Edit 취소하기 (Edit in Progress 상태에서)
async function cancelPendingProductEdit(bookingId) {
    if (!confirm('Cancel the edit and restore to previous status?')) {
        return;
    }

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'cancelProductEdit',
                bookingId: bookingId
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Edit cancelled. Booking restored to original status.');
            window.location.reload();
        } else {
            alert('Failed to cancel edit: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Cancel product edit error:', error);
        alert('An error occurred while cancelling the edit.');
    }
}

// 세션 확인 후 페이지 초기화
async function checkSessionAndInitialize() {
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated || sessionData.userType !== 'agent') {
            window.location.href = '../index.html';
            return;
        }
        
        // 세션 확인 후 페이지 초기화
        if (document.readyState === 'complete') {
            initializePage();
        } else {
            window.addEventListener('load', initializePage);
        }
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
}

// window.onload를 사용하여 모든 리소스가 로드된 후 실행
checkSessionAndInitialize();

async function loadReservationDetail() {
    try {
        showLoading();
        
        const response = await fetch(`../backend/api/agent-api.php?action=getReservationDetail&bookingId=${encodeURIComponent(currentBookingId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        // 디버깅: API 응답 확인
        console.log('API Response:', result);
        if (result.success && result.data && result.data.travelers) {
            console.log('Travelers from API:', result.data.travelers);
            result.data.travelers.forEach((traveler, index) => {
                console.log(`Traveler ${index}:`, {
                    travelerType: traveler.travelerType,
                    title: traveler.title,
                    gender: traveler.gender,
                    visaRequired: traveler.visaRequired,
                    visaStatus: traveler.visaStatus,
                    passportIssueDate: traveler.passportIssueDate,
                    firstName: traveler.firstName,
                    lastName: traveler.lastName
                });
            });
        }
        
        if (result.success) {
            renderReservationDetail(result.data);
        } else {
            showError('Failed to load reservation: ' + result.message);
        }
    } catch (error) {
        console.error('Error loading reservation detail:', error);
        showError('An error occurred while loading reservation.');
    } finally {
        hideLoading();
    }
}

function renderReservationDetail(data) {
    const booking = data.booking;
    const selectedOptions = data.selectedOptions || {};
    const travelers = data.travelers || [];
    const pricingLabels = data.pricingLabels || {};

    // 예약 데이터 저장
    currentBookingData = data;

    // Traveler 정보 저장 (Room Option 계산용)
    window.currentTravelers = travelers;

    // Edit Allowed 상태 확인 (Admin이 허용했을 때만 수정 가능)
    isEditAllowed = parseInt(booking.edit_allowed || 0) === 1;
    updateEditButtonsState();

    // 예약 거절 사유 표시 (bookingStatus가 'rejected'인 경우)
    const rejectionAlert = document.getElementById('bookingRejectionAlert');
    const rejectionReasonEl = document.getElementById('bookingRejectionReason');
    if (rejectionAlert && rejectionReasonEl) {
        if (booking.bookingStatus === 'rejected' && booking.remarks) {
            // remarks에서 [Rejected] 부분 추출
            const remarksText = booking.remarks || '';
            const rejectedMatch = remarksText.match(/\[Rejected\]\s*(.+?)(?:\n|$)/);
            const rejectionReason = rejectedMatch ? rejectedMatch[1].trim() : remarksText.trim();

            if (rejectionReason) {
                rejectionReasonEl.textContent = rejectionReason;
                rejectionAlert.style.display = 'block';
            } else {
                rejectionAlert.style.display = 'none';
            }
        } else {
            rejectionAlert.style.display = 'none';
        }
    }

    // pending_update, check_reject 상태 배너 및 버튼 표시
    const pendingUpdateBanner = document.getElementById('pendingUpdateBanner');
    const editInProgressBanner = document.getElementById('editInProgressBanner');
    const checkRejectBanner = document.getElementById('checkRejectBanner');
    const saveBtn = document.getElementById('saveBtn');
    const acknowledgeRejectBtn = document.getElementById('acknowledgeRejectBtn');
    const bookingStatus = (booking.bookingStatus || '').toLowerCase();

    // pending_update 상태 + product_edit changeType + newData가 null인 경우: Edit in Progress
    const pendingChangeRequest = data.pendingChangeRequest || null;
    const isProductEditInProgress = bookingStatus === 'pending_update' &&
        pendingChangeRequest &&
        pendingChangeRequest.changeType === 'product_edit' &&
        !pendingChangeRequest.newData;

    // Edit in Progress 배너 (product_edit + newData=null)
    if (editInProgressBanner) {
        if (isProductEditInProgress) {
            editInProgressBanner.style.display = 'block';
            editInProgressBanner.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <span style="font-size: 24px;">🔄</span>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: #1e40af; font-size: 16px; margin-bottom: 4px;">Edit in Progress</div>
                        <div style="color: #1e3a8a; font-size: 14px;">You have an unsaved product edit. Please complete or cancel the edit.</div>
                        <div style="margin-top: 12px; display: flex; gap: 8px;">
                            <button type="button" class="jw-button typeB" onclick="continueProductEdit('${currentBookingId}')" style="background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px;">
                                Continue Edit
                            </button>
                            <button type="button" class="jw-button typeC" onclick="cancelPendingProductEdit('${currentBookingId}')" style="background: #dc2626; color: white; padding: 8px 16px; border-radius: 6px;">
                                Cancel Edit
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            editInProgressBanner.style.display = 'none';
        }
    }

    // 일반 pending_update 배너 (product_edit가 아니거나 newData가 있는 경우)
    if (pendingUpdateBanner) {
        if (bookingStatus === 'pending_update' && !isProductEditInProgress) {
            pendingUpdateBanner.style.display = 'block';
            // product_edit이고 newData가 있는 경우 메시지 커스터마이즈
            if (pendingChangeRequest && pendingChangeRequest.changeType === 'product_edit' && pendingChangeRequest.newData) {
                const bannerText = document.getElementById('pendingUpdateBannerText');
                if (bannerText) {
                    bannerText.textContent = 'Product/date change request is awaiting admin approval.';
                }
            }
        } else {
            pendingUpdateBanner.style.display = 'none';
        }
    }
    if (checkRejectBanner) {
        if (bookingStatus === 'check_reject' && data.rejectedRequest) {
            const reason = data.rejectedRequest.rejectReason || 'No reason provided';
            const processedBy = data.rejectedRequest.processedBy || 'Admin';
            const processedAt = data.rejectedRequest.processedAt ? new Date(data.rejectedRequest.processedAt).toLocaleString() : '';
            const rejectText = document.getElementById('agentRejectReasonText');
            const rejectMeta = document.getElementById('agentRejectReasonMeta');
            if (rejectText) rejectText.textContent = reason;
            if (rejectMeta) rejectMeta.textContent = `Rejected by ${processedBy}${processedAt ? ' on ' + processedAt : ''}`;
            checkRejectBanner.style.display = 'block';
        } else {
            checkRejectBanner.style.display = 'none';
        }
    }
    // 버튼 표시 로직 (상단 toolbar)
    if (saveBtn && acknowledgeRejectBtn) {
        if (bookingStatus === 'check_reject') {
            saveBtn.style.display = 'none';
            acknowledgeRejectBtn.style.display = 'none'; // 상단에서는 숨김
        } else if (bookingStatus === 'pending_update') {
            saveBtn.style.display = 'none';
            acknowledgeRejectBtn.style.display = 'none';
        } else {
            saveBtn.style.display = '';
            acknowledgeRejectBtn.style.display = 'none';
        }
    }

    // 하단 고정 바 표시 로직
    const fixedBottomBar = document.getElementById('fixedBottomBar');
    const bottomSaveBtn = document.getElementById('bottomSaveBtn');
    const bottomAcknowledgeRejectBtn = document.getElementById('bottomAcknowledgeRejectBtn');
    if (fixedBottomBar && bottomSaveBtn && bottomAcknowledgeRejectBtn) {
        if (bookingStatus === 'check_reject') {
            fixedBottomBar.style.display = 'flex';
            bottomSaveBtn.style.display = 'none';
            bottomAcknowledgeRejectBtn.style.display = '';
        } else if (bookingStatus === 'pending_update') {
            fixedBottomBar.style.display = 'none';
        } else {
            fixedBottomBar.style.display = 'flex';
            bottomSaveBtn.style.display = '';
            bottomAcknowledgeRejectBtn.style.display = 'none';
        }
    }

    // 디버깅: 여행자 데이터 확인
    console.log('Travelers data:', travelers);
    if (travelers.length > 0) {
        console.log('First traveler:', travelers[0]);
    }

    // 상품 정보
    // (HTML 태그 수정) 디코딩 적용
    if (booking.packageName) {
        const productNameInput = document.getElementById('product_name');
        if (productNameInput) productNameInput.value = decodeHtmlEntities(booking.packageName) || '';
    }
    
    if (booking.departureDate) {
        const tripRangeInput = document.getElementById('trip_range');
        if (tripRangeInput) {
            const returnDate = booking.returnDate || calculateReturnDate(booking.departureDate, booking.duration_days || booking.durationDays || 5);
            tripRangeInput.value = `${booking.departureDate} - ${returnDate}`;
        }
    }
    
    // 미팅 시간
    if (booking.meetingTime || booking.meetTime) {
        const meetTimeInput = document.getElementById('meet_time');
        if (meetTimeInput) {
            const meetTime = booking.meetingTime || booking.meetTime;
            meetTimeInput.value = meetTime.includes(' ') ? meetTime : `${booking.departureDate} ${meetTime}`;
        }
    }
    
    // 미팅 장소
    if (booking.meetingLocation || booking.meetingPlace || booking.meetPlace) {
        const meetPlaceInput = document.getElementById('meet_place');
        if (meetPlaceInput) {
            meetPlaceInput.value = booking.meetingLocation || booking.meetingPlace || booking.meetPlace || '';
        }
    }
    
    // 예약 정보
    if (booking.bookingId) {
        const resNoInput = document.getElementById('res_no');
        if (resNoInput) resNoInput.value = booking.bookingId;
    }

    // Reservation Summary 채우기
    {
        const summaryBookingId = document.getElementById('summary_booking_id');
        const summaryPackageName = document.getElementById('summary_package_name');
        const summaryTripRange = document.getElementById('summary_trip_range');
        const summaryTravelers = document.getElementById('summary_travelers');
        const summaryTotalAmount = document.getElementById('summary_total_amount');

        if (summaryBookingId) {
            summaryBookingId.textContent = booking.bookingId || '-';
        }
        if (summaryPackageName) {
            summaryPackageName.textContent = decodeHtmlEntities(booking.packageName) || '-';
        }
        if (summaryTripRange && booking.departureDate) {
            const returnDate = booking.returnDate || calculateReturnDate(booking.departureDate, booking.duration_days || booking.durationDays || 5);
            summaryTripRange.textContent = `${booking.departureDate} ~ ${returnDate}`;
        }
        if (summaryTravelers) {
            const parts = [];
            try {
                const so = (selectedOptions && typeof selectedOptions === 'object') ? selectedOptions : {};
                const root = (so.selectedOptions && typeof so.selectedOptions === 'object') ? so.selectedOptions : so;
                const guestOptions = Array.isArray(root.guestOptions) ? root.guestOptions : (Array.isArray(so.guestOptions) ? so.guestOptions : []);
                for (const opt of guestOptions) {
                    if (!opt || typeof opt !== 'object') continue;
                    const name = String(opt.name ?? opt.optionName ?? opt.label ?? opt.type ?? '').trim();
                    const qty = Number(opt.qty ?? opt.quantity ?? opt.count ?? 0);
                    if (!name || !Number.isFinite(qty) || qty <= 0) continue;
                    parts.push(`${name} x${qty}`);
                }
            } catch (_) {}
            if (!parts.length && (booking.adults !== undefined || booking.children !== undefined || booking.infants !== undefined)) {
                const a = Number(booking.adults || 0);
                const c = Number(booking.children || 0);
                const i = Number(booking.infants || 0);
                if (a > 0) parts.push(`Adult x${a}`);
                if (c > 0) parts.push(`Child x${c}`);
                if (i > 0) parts.push(`Infant x${i}`);
            }
            summaryTravelers.textContent = parts.join(', ') || '-';
        }
        if (summaryTotalAmount) {
            const amount = Number(booking.totalAmount || booking.total_amount || 0);

            // pending_update + travelers 변경일 때 변경 후 예상 금액 표시
            const isPendingTravelerChange = pendingChangeRequest &&
                pendingChangeRequest.changeType === 'travelers' &&
                bookingStatus === 'pending_update';

            if (isPendingTravelerChange) {
                const pendingTravelers = pendingChangeRequest.newData?.pendingTravelers || [];
                if (pendingTravelers.length > 0) {
                    const pendingTotal = calculateTotalFromTravelersAgent(booking, pendingTravelers);
                    const diff = pendingTotal - amount;
                    const diffColor = diff > 0 ? '#dc2626' : diff < 0 ? '#059669' : '#6B7280';
                    const diffSign = diff > 0 ? '+' : '';
                    summaryTotalAmount.innerHTML = `
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="text-decoration: line-through; color: #9CA3AF;">₱${amount.toLocaleString()}</span>
                                <span style="font-size: 16px;">→</span>
                                <span style="color: #0050C8; font-weight: 700;">₱${pendingTotal.toLocaleString()}</span>
                            </div>
                            <div style="font-size: 12px; color: ${diffColor};">
                                (${diffSign}₱${diff.toLocaleString()})
                            </div>
                        </div>
                    `;
                } else {
                    summaryTotalAmount.textContent = amount > 0 ? `₱${amount.toLocaleString()}` : '-';
                }
            } else {
                summaryTotalAmount.textContent = amount > 0 ? `₱${amount.toLocaleString()}` : '-';
            }
        }

        // Amount Breakdown 채우기 (b2b-booking-detail과 동일한 상세 표시)
        const breakdownSection = document.getElementById('amount-breakdown-section');
        const breakdownList = document.getElementById('amount-breakdown-list');
        if (breakdownSection && breakdownList) {
            const items = [];

            // Sale 할인 정보
            const saleDiscountAmount = parseFloat(booking.saleDiscountAmount || 0);
            const saleName = booking.saleName || '';

            // DB에 저장된 단가 사용 (이미 할인 적용된 가격)
            const adultPrice = parseFloat(booking.adultPrice || booking.packagePrice || 0);
            const childPrice = parseFloat(booking.childPrice || 0) || (adultPrice * 0.8);
            const infantPrice = parseFloat(booking.infantPrice || 0) || 10000;

            // 할인 전 원래 가격 계산 (adultPrice에 할인액 더하기)
            const originalAdultPrice = saleDiscountAmount > 0 ? (adultPrice + saleDiscountAmount) : adultPrice;

            // 인원 수 계산 (travelers 배열에서)
            let adults = 0;
            let childrenWithRoom = 0;
            let childrenNoRoom = 0;
            let infants = 0;

            if (Array.isArray(travelers)) {
                travelers.forEach(t => {
                    const type = (t.type || t.travelerType || '').toLowerCase();
                    if (type === 'adult') {
                        adults++;
                    } else if (type === 'child') {
                        const hasRoom = parseInt(t.childRoom || 0) === 1;
                        if (hasRoom) {
                            childrenWithRoom++;
                        } else {
                            childrenNoRoom++;
                        }
                    } else if (type === 'infant') {
                        infants++;
                    }
                });
            }

            // 할인 적용 대상 인원 (Adult + Child with Room)
            const discountableCount = adults + childrenWithRoom;

            // Package Price 표시 (할인 전 가격 기준)
            if (adults > 0 && originalAdultPrice > 0) {
                items.push({ label: 'Adult x ' + adults, amount: adults * originalAdultPrice });
            }
            if (childrenWithRoom > 0 && originalAdultPrice > 0) {
                items.push({ label: 'Child (Room) x ' + childrenWithRoom, amount: childrenWithRoom * originalAdultPrice });
            }
            if (childrenNoRoom > 0 && childPrice > 0) {
                items.push({ label: 'Child x ' + childrenNoRoom, amount: childrenNoRoom * childPrice });
            }
            if (infants > 0) {
                items.push({ label: 'Infant x ' + infants, amount: infants * infantPrice });
            }

            // 룸 옵션 금액
            if (selectedOptions) {
                const opts = selectedOptions.selectedOptions || selectedOptions;
                if (opts.roomOptions && Array.isArray(opts.roomOptions)) {
                    opts.roomOptions.forEach(room => {
                        const roomPrice = parseFloat(room.price || 0);
                        const roomQty = parseInt(room.quantity || room.qty || 1);
                        if (roomPrice > 0) {
                            items.push({ label: (room.roomName || room.name || 'Room') + ' x ' + roomQty, amount: roomPrice * roomQty });
                        }
                    });
                }
            }

            // Visa Fee
            const visaFee = parseFloat(booking.visaFee || 0);
            if (visaFee > 0) {
                items.push({ label: 'Visa Fee', amount: visaFee });
            }

            // Flight Option Fee
            const flightOptionsTotal = parseFloat(booking.flightOptionFee || 0);
            if (flightOptionsTotal > 0) {
                items.push({ label: 'Flight Options', amount: flightOptionsTotal });
            }

            // 소계 (할인 전)
            const subtotal = items.reduce((sum, item) => sum + item.amount, 0);

            // Sale 할인 금액 계산 (인원당 할인액 × 할인 대상 인원수)
            const totalDiscount = saleDiscountAmount * discountableCount;

            // 총액 계산 (할인 차감)
            const calculatedTotal = subtotal - totalDiscount;

            if (items.length === 0) {
                breakdownSection.style.display = 'none';
            } else {
                breakdownSection.style.display = 'block';

                // HTML 생성
                let html = items.map(item =>
                    '<div style="display: flex; justify-content: space-between; font-size: 14px;">' +
                        '<span style="color: #4B5563;">' + item.label + '</span>' +
                        '<span style="color: #111827; font-weight: 500;">₱' + item.amount.toLocaleString() + '</span>' +
                    '</div>'
                ).join('');

                // 할인 정보 표시
                if (totalDiscount > 0 && saleName) {
                    html += '<div style="display: flex; justify-content: space-between; font-size: 14px; margin-top: 8px; padding: 8px 12px; background: #FEF2F2; border-radius: 6px; border: 1px solid #FECACA;">' +
                        '<span style="color: #DC2626; font-weight: 600;">' +
                            '<span style="background: #DC2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 8px;">SALE</span>' +
                            saleName +
                        '</span>' +
                        '<span style="color: #DC2626; font-weight: 600;">-₱' + totalDiscount.toLocaleString() + '</span>' +
                    '</div>';
                }

                // 최종 총액
                html += '<div style="display: flex; justify-content: space-between; font-size: 14px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #E5E7EB;">' +
                    '<span style="color: #0050C8; font-weight: 700;">Total</span>' +
                    '<span style="color: #0050C8; font-weight: 700;">₱' + calculatedTotal.toLocaleString() + '</span>' +
                '</div>';

                breakdownList.innerHTML = html;
            }
        }

        // Price Adjustment 정보 표시
        const adjustmentSection = document.getElementById('price-adjustment-section');
        const adjustmentInfo = document.getElementById('price-adjustment-info');
        if (adjustmentSection && adjustmentInfo) {
            const priceAdjustment = parseFloat(booking.priceAdjustment || 0);
            if (priceAdjustment !== 0) {
                adjustmentSection.style.display = 'block';
                const isIncrease = priceAdjustment > 0;
                const adjustmentDate = booking.lastAdjustmentDate
                    ? new Date(booking.lastAdjustmentDate).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : '-';
                const reason = booking.adjustmentReason || 'N/A';
                adjustmentInfo.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Adjustment Amount</span>
                        <span style="font-weight: 700; font-size: 16px; color: ${isIncrease ? '#dc2626' : '#059669'};">
                            ${isIncrease ? '+' : ''}₱${Math.abs(priceAdjustment).toLocaleString()}
                        </span>
                    </div>
                    <div style="font-size: 13px; color: #6B7280;">
                        <div><strong>Reason:</strong> ${reason}</div>
                        <div><strong>Date:</strong> ${adjustmentDate}</div>
                    </div>
                `;
            } else {
                adjustmentSection.style.display = 'none';
            }
        }
    }

    if (booking.createdAt) {
        const resDatetimeInput = document.getElementById('res_datetime');
        if (resDatetimeInput) resDatetimeInput.value = formatDateTime(booking.createdAt);
    }

    // 수정일시 (Reservation Information에 표시)
    {
        const resModifiedDateInput = document.getElementById('res_modified_date');
        if (resModifiedDateInput) {
            if (booking.updatedAt) {
                resModifiedDateInput.value = formatDateTime(booking.updatedAt);
            } else {
                resModifiedDateInput.value = '-';
            }
        }
    }

    // 예약 인원(옵션명+수량) 표시: 고정 Adult/Child/Infant가 아니라 DB 옵션명(guestOptions)을 우선 사용
    {
        const resPeopleInput = document.getElementById('res_people');
        if (resPeopleInput) {
            const parts = [];
            try {
                const so = (selectedOptions && typeof selectedOptions === 'object') ? selectedOptions : {};
                const root = (so.selectedOptions && typeof so.selectedOptions === 'object') ? so.selectedOptions : so;
                const guestOptions = Array.isArray(root.guestOptions) ? root.guestOptions : (Array.isArray(so.guestOptions) ? so.guestOptions : []);
                for (const opt of guestOptions) {
                    if (!opt || typeof opt !== 'object') continue;
                    const name = String(opt.name ?? opt.optionName ?? opt.label ?? opt.type ?? '').trim();
                    const qty = Number(opt.qty ?? opt.quantity ?? opt.count ?? 0);
                    if (!name || !Number.isFinite(qty) || qty <= 0) continue;
                    parts.push(`${name} x${qty}`);
                }
            } catch (_) {}

            // fallback: legacy adults/children/infants (고정 라벨 없이 총 인원만 표시)
            if (!parts.length && (booking.adults !== undefined || booking.children !== undefined || booking.infants !== undefined)) {
                const a = Number(booking.adults || 0);
                const c = Number(booking.children || 0);
                const i = Number(booking.infants || 0);
                const total = (Number.isFinite(a) ? a : 0) + (Number.isFinite(c) ? c : 0) + (Number.isFinite(i) ? i : 0);
                if (total > 0) parts.push(String(total));
            }

            resPeopleInput.value = parts.join(', ') || '-';
        }
    }
    
    // 룸 옵션
    const roomOptText = document.getElementById('room_opt_text');
    if (roomOptText) {
        const roomParts = [];
        if (selectedOptions.selectedRooms) {
            // 배열인 경우
            if (Array.isArray(selectedOptions.selectedRooms)) {
                selectedOptions.selectedRooms.forEach(room => {
                    if (room && (room.count > 0 || room.quantity > 0)) {
                        const count = room.count || room.quantity || 1;
                        const name = room.roomType || room.name || room.roomName || '룸';
                        roomParts.push(`${name}x${count}`);
                    }
                });
            } else {
                // 객체인 경우
                Object.values(selectedOptions.selectedRooms).forEach(room => {
                    if (room && (room.count > 0 || room.quantity > 0)) {
                        const count = room.count || room.quantity || 1;
                        const name = room.roomType || room.name || room.roomName || '룸';
                        roomParts.push(`${name}x${count}`);
                    }
                });
            }
        }
        roomOptText.value = roomParts.length > 0 ? roomParts.join(', ') : '-';

        // 현재 선택된 룸 옵션 저장 (수정용)
        window.currentSelectedRooms = selectedOptions.selectedRooms || [];
    }
    
    // 추가 옵션: createReservation()이 selectedOptions.selectedOptions에 배열/객체 둘 다 저장할 수 있으므로 폭넓게 지원
    const options = (selectedOptions && typeof selectedOptions === 'object')
        ? (selectedOptions.selectedOptions || selectedOptions.options || selectedOptions)
        : {};

    const normalizeAppliedValue = (v) => {
        const s = String(v ?? '').trim().toLowerCase();
        if (s === '') return '';
        if (v === true || s === 'true' || s === '1') return 'applied';
        if (v === false || s === 'false' || s === '0') return 'not_applied';
        if (s.includes('apply') || s.includes('applied') || s.includes('신청')) return 'applied';
        if (s.includes('not') || s.includes('미신청')) return 'not_applied';
        return String(v);
    };
    
    // 기내 수화물
    const baggageSelect = document.getElementById('opt_baggage');
    if (baggageSelect) {
        const baggageValue =
            options.baggage || options.carryOnBaggage || options.additionalBaggage || options.baggageOption ||
            selectedOptions.baggage || selectedOptions.carryOnBaggage;
        if (baggageValue) {
            // 값이 숫자인 경우 (예: "15", "20", "30")
            if (typeof baggageValue === 'string' && baggageValue.match(/^\d+$/)) {
                baggageSelect.value = baggageValue;
            } else if (typeof baggageValue === 'number') {
                baggageSelect.value = baggageValue.toString();
            } else {
                // "Add 20kg", "20kg 추가" 등에서 숫자 추출
                const m = String(baggageValue).match(/(\d{2})\s*kg/i) || String(baggageValue).match(/(\d{2})/);
                if (m && m[1]) baggageSelect.value = String(m[1]);
                else baggageSelect.value = String(baggageValue);
            }
        }
    }
    
    // 조식 신청
    const breakfastSelect = document.getElementById('opt_breakfast');
    if (breakfastSelect) {
        const breakfastValue = options.breakfast || options.breakfastRequest || options.breakfastApplied || selectedOptions.breakfast;
        if (breakfastValue) {
            const x = normalizeAppliedValue(breakfastValue);
            if (x === 'applied') {
                breakfastSelect.value = 'Applied';
            } else if (x === 'not_applied') {
                breakfastSelect.value = 'Not Applied';
            } else {
                breakfastSelect.value = String(breakfastValue);
            }
        }
    }
    
    // 와이파이 대여
    const wifiSelect = document.getElementById('opt_wifi');
    if (wifiSelect) {
        const wifiValue = options.wifi || options.wifiRental || options.wifiApplied || selectedOptions.wifi;
        if (wifiValue) {
            const x = normalizeAppliedValue(wifiValue);
            if (x === 'applied') {
                wifiSelect.value = 'Applied';
            } else if (x === 'not_applied') {
                wifiSelect.value = 'Not Applied';
            } else {
                wifiSelect.value = String(wifiValue);
            }
        }
    }
    
    // 요청사항
    if (selectedOptions.seatRequest) {
        const seatReqTextarea = document.querySelector('#seat_req, textarea[name="seatRequest"]');
        if (seatReqTextarea) seatReqTextarea.value = selectedOptions.seatRequest;
    }
    
    if (selectedOptions.otherRequest) {
        const etcReqTextarea = document.querySelector('#etc_req, textarea[name="otherRequest"]');
        if (etcReqTextarea) etcReqTextarea.value = selectedOptions.otherRequest;
    }
    
    // 에이전트 메모
    if (booking.agentMemo || booking.memo) {
        const agentMemoTextarea = document.getElementById('agent_memo');
        if (agentMemoTextarea) agentMemoTextarea.value = booking.agentMemo || booking.memo || '';
    }
    
    // 예약 고객 정보
    renderCustomerInfo(booking, selectedOptions);
    
    // 항공편 정보
    renderFlightInfo(booking, selectedOptions);
    hideFlightSectionIfEmpty(booking, selectedOptions);
    
    // 결제 정보 렌더링
    renderPaymentInfo(booking, data.pricingOptions || []);
    
    // 예약 이력 렌더링
    renderReservationHistory(data.history || []);
    
    // 상태별 UI 업데이트
    updateUIByStatus(booking);
    
    // 여행자 정보 렌더링 (다른 렌더링이 완료된 후 실행)
    // requestAnimationFrame을 사용하여 DOM이 완전히 준비된 후 실행
    requestAnimationFrame(() => {
        requestAnimationFrame(async () => {
            initTravelerPagination(travelers);
            // Flight Options 로드
            await loadFlightOptionCategories(booking);
            // 출발 한달 전 수정 가능 여부 체크 및 버튼 초기화
            initEditButtonsIfWithin24Hours(booking);
        });
    });
}

// 예약 고객 정보 렌더링
function renderCustomerInfo(booking, selectedOptions = {}) {
    // 고객 이름
    const custNameInput = document.getElementById('cust_name');
    if (custNameInput) {
        const ci = (selectedOptions && typeof selectedOptions === 'object') ? (selectedOptions.customerInfo || {}) : {};
        const firstName = booking.customerFirstName || booking.customerFName || booking.fName || ci.firstName || ci.fName || '';
        const lastName = booking.customerLastName || booking.customerLName || booking.lName || ci.lastName || ci.lName || '';
        custNameInput.value = `${firstName} ${lastName}`.trim() || booking.customerName || ci.name || '';
    }
    
    // 고객 이메일
    const custEmailInput = document.getElementById('cust_email');
    if (custEmailInput) {
        const ci = (selectedOptions && typeof selectedOptions === 'object') ? (selectedOptions.customerInfo || {}) : {};
        custEmailInput.value = booking.contactEmail || booking.customerEmail || booking.accountEmail || ci.email || ci.emailAddress || '';
    }
    
    // 고객 연락처
    const custPhoneInput = document.getElementById('cust_phone');
    if (custPhoneInput) {
        const ci = (selectedOptions && typeof selectedOptions === 'object') ? (selectedOptions.customerInfo || {}) : {};
        const phone = booking.contactPhone || booking.customerPhone || booking.contactNo || ci.phone || ci.contactNo || '';
        const countryCode = booking.countryCode || ci.countryCode || '';
        custPhoneInput.value = countryCode ? `${countryCode} ${phone}` : phone;
    }
}

function hideFlightSectionIfEmpty(booking, selectedOptions = {}) {
    const hasOutbound = !!(booking && booking.outboundFlight && booking.outboundFlight.flightNumber);
    const hasInbound = !!(booking && booking.inboundFlight && booking.inboundFlight.flightNumber);
    const hasAlt = !!(selectedOptions && selectedOptions.flightInfo);
    const shouldHide = !(hasOutbound || hasInbound || hasAlt);
    if (!shouldHide) return;

    const h2 = document.querySelector('h2.section-title[data-lan-eng="Flight Information"]');
    if (!h2) return;
    // 현재 마크업 구조: h2 다음에 card-panel(출국), card-panel(귀국) 2개가 연속으로 존재
    const p1 = h2.nextElementSibling;
    const p2 = p1 ? p1.nextElementSibling : null;
    h2.style.display = 'none';
    if (p1 && p1.classList && p1.classList.contains('card-panel')) p1.style.display = 'none';
    if (p2 && p2.classList && p2.classList.contains('card-panel')) p2.style.display = 'none';
}

// ===== Traveler pagination (10 per page) =====
let __travelerPage = 1;
let __allTravelers = [];
let __currentTravelerIndex = -1;

// 여행자 리스트 초기화 (카드 형식)
function initTravelerPagination(travelers) {
    // Deep copy로 원본 배열과 분리 (window.currentTravelers와 독립적으로 관리)
    __allTravelers = Array.isArray(travelers) ? travelers.map(t => ({...t})) : [];
    renderTravelerSummaryList(__allTravelers);
}

// 여행자 요약 리스트 렌더링 (카드 형식)
function renderTravelerSummaryList(travelers) {
    const container = document.getElementById('traveler-summary-list');
    const countEl = document.getElementById('traveler-summary-count');

    if (!container) return;

    // 여행자 수 업데이트
    if (countEl) {
        countEl.textContent = `${travelers.length} Traveler${travelers.length !== 1 ? 's' : ''}`;
    }

    if (travelers.length === 0) {
        container.innerHTML = '<p class="no-traveler-message">No travelers information.</p>';
        return;
    }

    container.innerHTML = travelers.map((traveler, index) => {
        const isPrimary = traveler.isMainTraveler == 1;
        const firstName = traveler.firstName || traveler.fName || '';
        const lastName = traveler.lastName || traveler.lName || '';
        const fullName = `${firstName} ${lastName}`.trim() || 'Unknown';
        const travelerType = (traveler.travelerType || 'adult').charAt(0).toUpperCase() + (traveler.travelerType || 'adult').slice(1);
        const gender = traveler.gender === 'male' ? 'Male' : traveler.gender === 'female' ? 'Female' : '-';
        const nationality = traveler.nationality || '-';
        const passportNo = traveler.passportNumber || traveler.passportNo || '-';

        return `
            <div class="traveler-summary-item ${isPrimary ? 'is-primary' : ''}" onclick="openTravelerDetail(${index})">
                <div class="traveler-summary-info">
                    ${isPrimary ? '<span class="badge-primary">Primary</span>' : ''}
                    <span class="badge-type">${escapeHtml(travelerType)}</span>
                    <span class="traveler-summary-name">${escapeHtml(fullName)}</span>
                </div>
                <div class="traveler-summary-details">
                    <span>${escapeHtml(gender)}</span>
                    <span>${escapeHtml(nationality)}</span>
                    <span>Passport: ${escapeHtml(passportNo)}</span>
                </div>
            </div>
        `;
    }).join('');
}

// 여행자 상세 모달 열기 (View Only) - create-reservation 스타일
function openTravelerDetail(index) {
    __currentTravelerIndex = index || 0;

    const typeMap = { adult: 'Adult', child: 'Child', infant: 'Infant' };
    const genderMap = { male: 'Male', female: 'Female' };

    const cardsContainer = document.getElementById('traveler-view-cards-container');
    const sidebarNav = document.getElementById('traveler-view-sidebar-nav');
    const countEl = document.getElementById('traveler-view-count');

    if (!cardsContainer || !sidebarNav) return;

    // 여행자 수 업데이트
    if (countEl) {
        countEl.textContent = `${__allTravelers.length} Traveler${__allTravelers.length !== 1 ? 's' : ''}`;
    }

    // 카드 생성
    cardsContainer.innerHTML = __allTravelers.map((traveler, idx) => {
        const isPrimary = traveler.isMainTraveler == 1;
        const firstName = traveler.firstName || traveler.fName || '';
        const lastName = traveler.lastName || traveler.lName || '';
        const fullName = `${firstName} ${lastName}`.trim() || 'Unknown';
        const travelerType = typeMap[traveler.travelerType] || 'Adult';
        const gender = genderMap[traveler.gender] || '-';
        const title = traveler.title || '-';
        const nationality = traveler.nationality || '-';
        const passportNo = traveler.passportNumber || traveler.passportNo || '-';

        const birthDate = traveler.dateOfBirth || traveler.birthDate || traveler.birthdate || '';
        const formattedBirthDate = birthDate ? formatDateForInput(birthDate) : '-';

        const passportExpiry = traveler.passportExpiryDate || traveler.passportExpiry || traveler.passportExp || '';
        const formattedPassportExpiry = passportExpiry ? formatDateForInput(passportExpiry) : '-';

        // 여권 이미지
        const passportImage = traveler.passportImage || '';
        let passportImageHtml = '<span style="color: #9CA3AF;">No passport image</span>';
        if (passportImage && passportImage.trim() !== '') {
            const imageUrl = passportImage.startsWith('http') || passportImage.startsWith('data:')
                ? passportImage
                : (passportImage.startsWith('/') ? window.location.origin + passportImage : window.location.origin + '/' + passportImage);
            passportImageHtml = `<a href="${escapeHtml(imageUrl)}" target="_blank" style="display: inline-block;">
                <img src="${escapeHtml(imageUrl)}" alt="Passport" style="max-width: 120px; max-height: 80px; border-radius: 6px; border: 1px solid #E5E7EB;">
            </a>`;
        }

        return `
            <div class="traveler-card-view ${isPrimary ? 'is-primary' : ''}" id="traveler-view-card-${idx}">
                <div class="traveler-card-header">
                    <div class="traveler-card-title">
                        <span>Traveler ${idx + 1}</span>
                        ${isPrimary ? '<span class="badge-primary">Primary</span>' : ''}
                    </div>
                </div>
                <div class="traveler-card-body">
                    <div class="form-group">
                        <label>Traveler Type</label>
                        <div class="form-value">${escapeHtml(travelerType)}</div>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <div class="form-value">${escapeHtml(title)}</div>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <div class="form-value">${escapeHtml(gender)}</div>
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <div class="form-value">${escapeHtml(nationality)}</div>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <div class="form-value">${escapeHtml(firstName || '-')}</div>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <div class="form-value">${escapeHtml(lastName || '-')}</div>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <div class="form-value">${escapeHtml(formattedBirthDate)}</div>
                    </div>
                    <div class="form-group">
                        <label>Passport Number</label>
                        <div class="form-value">${escapeHtml(passportNo)}</div>
                    </div>
                    <div class="form-group">
                        <label>Passport Expiry</label>
                        <div class="form-value">${escapeHtml(formattedPassportExpiry)}</div>
                    </div>
                    <div class="form-group">
                        <label>Passport Image</label>
                        <div class="form-value" style="min-height: auto; padding: 4px;">${passportImageHtml}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // 사이드바 네비게이션 생성
    sidebarNav.innerHTML = __allTravelers.map((traveler, idx) => {
        const isPrimary = traveler.isMainTraveler == 1;
        const firstName = traveler.firstName || traveler.fName || '';
        const lastName = traveler.lastName || traveler.lName || '';
        const fullName = `${firstName} ${lastName}`.trim() || 'Unknown';
        const travelerType = typeMap[traveler.travelerType] || 'Adult';
        const isActive = idx === __currentTravelerIndex;

        return `
            <div class="sidebar-nav-item ${isPrimary ? 'is-primary' : ''} ${isActive ? 'active' : ''}" onclick="scrollToTravelerCard(${idx})">
                <div class="nav-item-number">${idx + 1}</div>
                <div class="nav-item-info">
                    <div class="nav-item-name">${escapeHtml(fullName)}</div>
                    <div class="nav-item-type">${escapeHtml(travelerType)}</div>
                </div>
                ${isPrimary ? '<span class="nav-item-badge">Primary</span>' : ''}
            </div>
        `;
    }).join('');

    // 모달 표시
    document.getElementById('travelerDetailModal').style.display = 'flex';

    // 선택된 카드로 스크롤
    setTimeout(() => {
        scrollToTravelerCard(__currentTravelerIndex);
    }, 100);
}

// 여행자 카드로 스크롤 (View Modal)
function scrollToTravelerCard(index) {
    const card = document.getElementById(`traveler-view-card-${index}`);
    const container = document.querySelector('.traveler-view-modal-main');

    if (card && container) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // 사이드바 active 상태 업데이트
    const navItems = document.querySelectorAll('#traveler-view-sidebar-nav .sidebar-nav-item');
    navItems.forEach((item, idx) => {
        if (idx === index) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    __currentTravelerIndex = index;
}

// 여행자 카드로 스크롤 (Edit Modal)
function scrollToTravelerEditCard(index) {
    const card = document.getElementById(`traveler-edit-card-${index}`);
    const container = document.querySelector('#travelerEditAllModal .traveler-modal-main');

    if (card && container) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // 사이드바 active 상태 업데이트
    const navItems = document.querySelectorAll('#traveler-edit-sidebar-nav .sidebar-nav-item');
    navItems.forEach((item, idx) => {
        if (idx === index) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// ===== 여행자 수정 모달 (전체) =====

// 임시 저장용 여권 이미지 (base64)
let __tempPassportImages = {};

// Flight Options 전역 변수
window.__flightOptionCategories = [];
window.__currentAirlineName = '';

// Flight number에서 항공사명 추출
function getAirlineNameFromFlightNumber(flightNumber) {
    if (!flightNumber) return '';
    const airlineCodeMap = {
        '5J': 'Cebu Pacific',
        'PR': 'Philippine Airlines',
        'Z2': 'AirAsia Philippines',
        'DG': 'AirAsia Philippines',
        'TW': 'Tway Air',
        '7C': 'Jeju Air',
        'LJ': 'Jin Air',
        'KE': 'Korean Air',
        'OZ': 'Asiana Airlines',
        'SQ': 'Singapore Airlines',
        'CX': 'Cathay Pacific',
        'JL': 'Japan Airlines',
        'NH': 'All Nippon Airways'
    };
    const flightStr = String(flightNumber).toUpperCase().trim();
    const airlineCode = flightStr.substring(0, 2);
    return airlineCodeMap[airlineCode] || '';
}

// 옵션 카테고리 로드 (상품에 설정된 옵션 카테고리 사용)
async function loadFlightOptionCategories(booking) {
    window.__flightOptionCategories = [];
    window.__currentAirlineName = '';

    try {
        // 상품에 설정된 옵션 카테고리명 사용
        const optionCategoryName = booking.optionCategoryName || '';

        if (!optionCategoryName) {
            console.log('No option category found for this product, skipping options load');
            return;
        }

        console.log('Loading options for category:', optionCategoryName);

        const response = await fetch('../backend/api/agent-api.php?action=getAirlineOptionsByName&airlineName=' + encodeURIComponent(optionCategoryName), {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success) {
            window.__currentAirlineName = optionCategoryName;
            window.__flightOptionCategories = (result.data && result.data.categories) ? result.data.categories : [];
            console.log('Options loaded:', window.__flightOptionCategories);
        }
    } catch (error) {
        console.error('Error loading options:', error);
    }
}

// Flight Option 업데이트
function updateTravelerFlightOptionInEdit(travelerIndex, optionId, price, isChecked) {
    if (!__allTravelers[travelerIndex]) return;

    const numOptionId = Number(optionId);
    const numPrice = Number(price) || 0;

    if (!__allTravelers[travelerIndex].flightOptions) {
        __allTravelers[travelerIndex].flightOptions = [];
    }
    if (!__allTravelers[travelerIndex].flightOptionPrices) {
        __allTravelers[travelerIndex].flightOptionPrices = {};
    }

    const options = __allTravelers[travelerIndex].flightOptions;
    const prices = __allTravelers[travelerIndex].flightOptionPrices;

    if (isChecked) {
        if (options.indexOf(numOptionId) === -1) {
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
}

// Flight Options 렌더링
function renderFlightOptionsInEdit(travelerIndex, selectedOptions) {
    if (!window.__flightOptionCategories || window.__flightOptionCategories.length === 0) {
        return '';
    }

    const selectedIds = (selectedOptions || []).map(id => Number(id));
    const airlineName = window.__currentAirlineName || '';
    const airlineHtml = airlineName ? `<span class="flight-options-airline">${escapeHtml(airlineName)}</span>` : '';

    let html = `<div class="flight-options-section" data-traveler-index="${travelerIndex}">`;
    html += '<div class="flight-options-header">';
    html += '<span class="flight-options-title">Flight Options</span>';
    html += airlineHtml;
    html += '</div><div class="flight-options-body">';

    window.__flightOptionCategories.forEach(cat => {
        html += '<div class="flight-option-category">';
        html += `<span class="category-label">${escapeHtml(cat.category_name_en || cat.category_name || cat.categoryName || '')}</span>`;
        html += '<div class="category-options">';

        (cat.options || []).forEach(opt => {
            const optId = Number(opt.option_id || opt.optionId);
            const isChecked = selectedIds.indexOf(optId) !== -1;
            const price = Number(opt.price || 0);
            const priceText = price > 0 ? '+PHP ' + price.toLocaleString() : 'Free';

            html += '<label class="option-checkbox">';
            html += `<input type="checkbox" data-option-id="${optId}" data-option-price="${price}"`;
            if (isChecked) html += ' checked';
            html += ` onchange="updateTravelerFlightOptionInEdit(${travelerIndex}, ${optId}, ${price}, this.checked)">`;
            html += `<span class="option-name">${escapeHtml(opt.option_name_en || opt.option_name || opt.optionName || '')}</span>`;
            html += `<span class="option-price">${priceText}</span>`;
            html += '</label>';
        });

        html += '</div></div>';
    });

    html += '</div></div>';
    return html;
}

// 여행자 수정 모달 열기
function openTravelerEditModal() {
    // 출발 한달 전이거나 Edit Allowed인 경우에만 수정 가능
    const canEdit = __canEditBeforeDeparture || isEditAllowed;
    if (!canEdit) {
        alert('Edit is only allowed until one month before departure.');
        return;
    }

    __tempPassportImages = {};
    renderTravelerEditCards();
    document.getElementById('travelerEditAllModal').style.display = 'flex';
}

// 여행자 수정 모달 닫기
function closeTravelerEditModal() {
    __tempPassportImages = {};
    document.getElementById('travelerEditAllModal').style.display = 'none';
}

// 현재 폼 입력값을 __allTravelers 배열에 동기화
function syncTravelerFormData() {
    for (let i = 0; i < __allTravelers.length; i++) {
        const traveler = __allTravelers[i];

        // Title
        const titleEl = document.getElementById(`edit_title_${i}`);
        if (titleEl) traveler.title = titleEl.value;

        // Gender
        const genderEl = document.getElementById(`edit_gender_${i}`);
        if (genderEl) traveler.gender = genderEl.value;

        // First Name
        const firstNameEl = document.getElementById(`edit_firstname_${i}`);
        if (firstNameEl) traveler.firstName = firstNameEl.value;

        // Last Name
        const lastNameEl = document.getElementById(`edit_lastname_${i}`);
        if (lastNameEl) traveler.lastName = lastNameEl.value;

        // Date of Birth + Traveler Type 업데이트
        const birthDateEl = document.getElementById(`edit_birthdate_${i}`);
        if (birthDateEl && birthDateEl.value) {
            traveler.birthDate = birthDateEl.value;
            // 나이 계산 후 travelerType 업데이트
            const age = calculateAge(birthDateEl.value);
            if (age !== null) {
                if (age < 2) traveler.travelerType = 'infant';
                else if (age < 12) traveler.travelerType = 'child';
                else traveler.travelerType = 'adult';
            }
        }

        // Nationality
        const nationalityEl = document.getElementById(`edit_nationality_${i}`);
        if (nationalityEl) traveler.nationality = nationalityEl.value;

        // Passport Number
        const passportEl = document.getElementById(`edit_passport_${i}`);
        if (passportEl) traveler.passportNumber = passportEl.value;

        // Passport Issue Date
        const passportIssueEl = document.getElementById(`edit_passport_issue_${i}`);
        if (passportIssueEl) traveler.passportIssueDate = passportIssueEl.value;

        // Passport Expiry Date
        const passportExpiryEl = document.getElementById(`edit_passport_expiry_${i}`);
        if (passportExpiryEl) traveler.passportExpiry = passportExpiryEl.value;

        // Visa Type
        const visaEl = document.getElementById(`edit_visa_${i}`);
        if (visaEl) traveler.visaType = visaEl.value;

        // Child Room
        const childRoomEl = document.getElementById(`edit_childroom_${i}`);
        if (childRoomEl) traveler.childRoom = (childRoomEl.value === 'yes');

        // Profile/Source
        const profileSourceEl = document.getElementById(`edit_profile_source_${i}`);
        if (profileSourceEl) traveler.profile_source = profileSourceEl.value;
    }
}

// 여행자 카드 추가 (모달 내)
function addTravelerCardInEdit() {
    // 먼저 현재 입력된 값들을 __allTravelers에 저장
    syncTravelerFormData();

    const newTraveler = {
        travelerType: 'adult',
        title: 'MR',
        gender: 'male',
        firstName: '',
        lastName: '',
        birthDate: '',
        nationality: '',
        passportNumber: '',
        passportExpiry: '',
        passportImage: '',
        isMainTraveler: __allTravelers.length === 0 ? 1 : 0
    };
    __allTravelers.push(newTraveler);
    renderTravelerEditCards();
}

// 여행자 카드 삭제 (모달 내)
function deleteTravelerCardInEdit(index) {
    if (__allTravelers.length <= 1) {
        alert('At least one traveler is required.');
        return;
    }

    // 먼저 현재 입력된 값들을 __allTravelers에 저장
    syncTravelerFormData();

    const wasPrimary = __allTravelers[index].isMainTraveler == 1;
    __allTravelers.splice(index, 1);

    // 삭제된 카드가 primary였으면 첫 번째를 primary로 설정
    if (wasPrimary && __allTravelers.length > 0) {
        __allTravelers[0].isMainTraveler = 1;
    }

    renderTravelerEditCards();
}

// 대표 여행자 설정 (모달 내)
function setPrimaryTravelerInEdit(index) {
    // 먼저 현재 입력된 값들을 __allTravelers에 저장
    syncTravelerFormData();

    __allTravelers.forEach((t, i) => {
        t.isMainTraveler = (i === index) ? 1 : 0;
    });
    renderTravelerEditCards();
}

// 여행자 수정 카드 렌더링 - create-reservation 스타일
function renderTravelerEditCards() {
    const container = document.getElementById('traveler-edit-cards-container');
    const countEl = document.getElementById('traveler-edit-count');
    const sidebarNav = document.getElementById('traveler-edit-sidebar-nav');

    if (!container) return;

    const typeMap = { adult: 'Adult', child: 'Child', infant: 'Infant' };

    if (__allTravelers.length === 0) {
        container.innerHTML = '<div class="no-travelers-message">No travelers added. Click "+ Add Traveler" to add.</div>';
        if (countEl) countEl.textContent = '0 Travelers';
        if (sidebarNav) sidebarNav.innerHTML = '<div style="padding: 16px; color: #9CA3AF; text-align: center; font-size: 13px;">No travelers</div>';
        return;
    }

    if (countEl) countEl.textContent = `${__allTravelers.length} Traveler${__allTravelers.length > 1 ? 's' : ''}`;

    // 사이드바 네비게이션 렌더링
    if (sidebarNav) {
        sidebarNav.innerHTML = __allTravelers.map((traveler, idx) => {
            const isPrimary = traveler.isMainTraveler == 1;
            const firstName = traveler.firstName || traveler.fName || '';
            const lastName = traveler.lastName || traveler.lName || '';
            const fullName = `${firstName} ${lastName}`.trim() || 'New Traveler';
            const travelerType = typeMap[traveler.travelerType] || 'Adult';

            return `
                <div class="sidebar-nav-item ${isPrimary ? 'is-primary' : ''}" onclick="scrollToTravelerEditCard(${idx})">
                    <div class="nav-item-number">${idx + 1}</div>
                    <div class="nav-item-info">
                        <div class="nav-item-name">${escapeHtml(fullName)}</div>
                        <div class="nav-item-type">${escapeHtml(travelerType)}</div>
                    </div>
                    ${isPrimary ? '<span class="nav-item-badge">Primary</span>' : ''}
                </div>
            `;
        }).join('');
    }

    container.innerHTML = __allTravelers.map((traveler, index) => {
        const isPrimary = traveler.isMainTraveler == 1;
        const firstName = traveler.firstName || traveler.fName || '';
        const lastName = traveler.lastName || traveler.lName || '';
        const birthDate = traveler.dateOfBirth || traveler.birthDate || traveler.birthdate || '';
        const passportExpiry = traveler.passportExpiryDate || traveler.passportExpiry || traveler.passportExp || '';
        const passportIssueDate = traveler.passportIssueDate || '';
        const passportImage = traveler.passportImage || '';
        const travelerType = traveler.travelerType || 'adult';
        const age = traveler.age || calculateAge(birthDate);
        const typeLabel = travelerType === 'child' ? 'Child' : travelerType === 'infant' ? 'Infant' : 'Adult';

        const hasPassportPhoto = passportImage && passportImage.trim() !== '';
        const photoFileName = hasPassportPhoto ? passportImage.split('/').pop() : '';
        const profileSource = traveler.profile_source || traveler.profileSource || '';

        // Passport date warning
        const showDateWarning = passportIssueDate && passportExpiry &&
            new Date(passportIssueDate) >= new Date(passportExpiry);

        return `
            <div class="traveler-card ${isPrimary ? 'is-primary' : ''}" data-index="${index}" id="traveler-edit-card-${index}">
                <div class="traveler-card-header">
                    <div class="traveler-card-title">
                        <span>Traveler ${index + 1}</span>
                        ${isPrimary ? '<span class="badge-primary">Primary</span>' : ''}
                    </div>
                    <div class="traveler-card-actions">
                        ${!isPrimary ? `<button type="button" class="btn-set-primary" onclick="setPrimaryTravelerInEdit(${index})">Set as Primary</button>` : ''}
                        <button type="button" class="btn-delete-traveler" onclick="deleteTravelerCardInEdit(${index})">Delete</button>
                    </div>
                </div>
                <div class="traveler-card-body">
                    <!-- Row 1: Title, Gender, First Name, Last Name -->
                    <div class="form-group">
                        <label>Title</label>
                        <select id="edit_title_${index}">
                            <option value="MR" ${traveler.title === 'MR' ? 'selected' : ''}>Mr</option>
                            <option value="MS" ${traveler.title === 'MS' ? 'selected' : ''}>Ms</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select id="edit_gender_${index}">
                            <option value="male" ${traveler.gender === 'male' ? 'selected' : ''}>Male</option>
                            <option value="female" ${traveler.gender === 'female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" id="edit_firstname_${index}" value="${escapeHtml(firstName)}" placeholder="First Name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" id="edit_lastname_${index}" value="${escapeHtml(lastName)}" placeholder="Last Name">
                    </div>

                    <!-- Row 2: Type, Age, Date of Birth, Nationality -->
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" id="edit_type_${index}" value="${typeLabel}" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="text" id="edit_age_${index}" value="${age != null ? age : '-'}" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" id="edit_birthdate_${index}" value="${formatDateForInput(birthDate)}" min="1900-01-01" max="2099-12-31" onchange="updateTravelerAgeInEdit(${index}, this.value)">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" id="edit_nationality_${index}" value="${escapeHtml(traveler.nationality || '')}" placeholder="Nationality">
                    </div>

                    <!-- Row 3: Passport No., Issue Date, Expiration Date, Visa Application -->
                    <div class="form-group">
                        <label>Passport No.</label>
                        <input type="text" id="edit_passport_${index}" value="${escapeHtml(traveler.passportNumber || traveler.passportNo || '')}" placeholder="Passport Number">
                    </div>
                    <div class="form-group">
                        <label>Passport Issue Date</label>
                        <input type="date" id="edit_passport_issue_${index}" value="${formatDateForInput(passportIssueDate)}" min="1900-01-01" max="2099-12-31" onchange="checkPassportDatesInEdit(${index})">
                    </div>
                    <div class="form-group">
                        <label>Passport Expiration Date</label>
                        <input type="date" id="edit_passport_expiry_${index}" value="${formatDateForInput(passportExpiry)}" min="1900-01-01" max="2099-12-31" onchange="checkPassportDatesInEdit(${index})">
                    </div>
                    <div class="form-group">
                        <label>Visa Application</label>
                        <select id="edit_visa_${index}" onchange="handleVisaTypeChangeInEdit(${index}, this.value)">
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

                    <!-- Row 4: Passport Photo + Visa Document + Child Room -->
                    <div class="form-group">
                        <label>Passport Photo</label>
                        <div class="passport-photo-upload">
                            <input type="file" id="passport_file_${index}" accept="image/*" style="display:none;" onchange="handlePassportUpload(${index}, this)">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('passport_file_${index}').click()">
                                <img src="../image/upload.svg" alt="" onerror="this.style.display='none'"> Upload Photo
                            </button>
                            <div class="passport-photo-info ${hasPassportPhoto ? '' : 'hidden'}" id="passport-photo-info-${index}">
                                <span class="photo-filename">${escapeHtml(photoFileName)}</span>
                                <div class="file-action-buttons">
                                    <button type="button" class="btn-file-action btn-preview" onclick="previewPassportPhotoInEdit(${index})" title="Preview">
                                        <img src="../image/search2.svg" alt="Preview">
                                    </button>
                                    <button type="button" class="btn-file-action btn-download" onclick="downloadPassportPhotoInEdit(${index})" title="Download">
                                        <img src="../image/buttun-download.svg" alt="Download">
                                    </button>
                                    <button type="button" class="btn-file-action btn-delete" onclick="removePassportImage(${index})" title="Delete">
                                        <img src="../image/button-close2.svg" alt="Delete">
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group visa-upload-container" id="visa-upload-container-${index}" style="display: ${traveler.visaType === 'with_visa' || traveler.visaType === 'foreign' ? 'block' : 'none'};">
                        <label>Visa Document</label>
                        <div class="visa-document-upload">
                            <input type="file" id="visa_file_${index}" accept="image/*,.pdf" style="display:none;" onchange="handleVisaUploadInEdit(${index}, this)">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('visa_file_${index}').click()">
                                <img src="../image/upload.svg" alt="" onerror="this.style.display='none'"> Upload Visa
                            </button>
                            <div class="visa-document-info ${traveler.visaDocument ? '' : 'hidden'}" id="visa-document-info-${index}">
                                <span class="visa-filename">${escapeHtml(traveler.visaDocument ? traveler.visaDocument.split('/').pop() : '')}</span>
                                <div class="file-action-buttons">
                                    <button type="button" class="btn-file-action btn-preview" onclick="previewVisaDocumentInEdit(${index})" title="Preview">
                                        <img src="../image/search2.svg" alt="Preview">
                                    </button>
                                    <button type="button" class="btn-file-action btn-download" onclick="downloadVisaDocumentInEdit(${index})" title="Download">
                                        <img src="../image/buttun-download.svg" alt="Download">
                                    </button>
                                    <button type="button" class="btn-file-action btn-delete" onclick="removeVisaDocumentInEdit(${index})" title="Delete">
                                        <img src="../image/button-close2.svg" alt="Delete">
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="child-room-container-${index}" style="display: ${travelerType === 'child' ? 'block' : 'none'};">
                        <label>Child Room</label>
                        <select id="edit_childroom_${index}">
                            <option value="no" ${!traveler.childRoom ? 'selected' : ''}>No</option>
                            <option value="yes" ${traveler.childRoom ? 'selected' : ''}>Yes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Profile/Source</label>
                        <input type="text" id="edit_profile_source_${index}" value="${escapeHtml(profileSource)}" placeholder="Profile/Source">
                    </div>
                </div>
                ${renderFlightOptionsInEdit(index, traveler.flightOptions || [])}
            </div>
        `;
    }).join('');
}

// 나이 계산 헬퍼
function calculateAge(birthDate) {
    if (!birthDate) return null;
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age >= 0 ? age : null;
}

// 생년월일 변경 시 나이와 Type 자동 업데이트
function updateTravelerAgeInEdit(index, birthDate) {
    const age = calculateAge(birthDate);
    const ageEl = document.getElementById(`edit_age_${index}`);
    const typeEl = document.getElementById(`edit_type_${index}`);

    if (ageEl) ageEl.value = age != null ? age : '-';

    // Type 자동 결정 (0-1: infant, 2-11: child, 12+: adult)
    let type = 'Adult';
    if (age !== null) {
        if (age < 2) type = 'Infant';
        else if (age < 12) type = 'Child';
    }
    if (typeEl) typeEl.value = type;

    // __allTravelers 배열도 업데이트
    if (__allTravelers[index]) {
        __allTravelers[index].birthDate = birthDate;
        __allTravelers[index].age = age;
        __allTravelers[index].travelerType = type.toLowerCase();
    }

    // Child Room 컨테이너 표시/숨김
    const childRoomContainer = document.getElementById(`child-room-container-${index}`);
    if (childRoomContainer) {
        childRoomContainer.style.display = type.toLowerCase() === 'child' ? 'block' : 'none';
    }
}

// 여권 이미지 업로드 처리
function handlePassportUpload(index, input) {
    const file = input.files[0];
    if (!file) return;

    // 파일 크기 체크 (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        __tempPassportImages[index] = base64;

        // 파일 정보 표시 업데이트
        const infoEl = document.getElementById(`passport-photo-info-${index}`);
        if (infoEl) {
            infoEl.classList.remove('hidden');
            const filenameEl = infoEl.querySelector('.photo-filename');
            if (filenameEl) {
                filenameEl.textContent = file.name;
            }
        }
    };
    reader.readAsDataURL(file);
}

// 여권 이미지 삭제
function removePassportImage(index) {
    __tempPassportImages[index] = null; // null은 삭제 표시

    // 파일 정보 숨기기
    const infoEl = document.getElementById(`passport-photo-info-${index}`);
    if (infoEl) {
        infoEl.classList.add('hidden');
        const filenameEl = infoEl.querySelector('.photo-filename');
        if (filenameEl) {
            filenameEl.textContent = '';
        }
    }

    // 파일 input 초기화
    const fileInput = document.getElementById(`passport_file_${index}`);
    if (fileInput) {
        fileInput.value = '';
    }
}

// 여권 사진 미리보기
function previewPassportPhotoInEdit(index) {
    const traveler = __allTravelers[index];
    if (!traveler) return;

    let imageUrl = null;
    let fileName = 'Passport Photo';

    // 새로 업로드한 파일인 경우 (base64)
    if (__tempPassportImages[index]) {
        imageUrl = __tempPassportImages[index];
        fileName = 'Uploaded Photo';
    }
    // 기존 서버에 저장된 이미지인 경우
    else if (traveler.passportImage) {
        imageUrl = normalizePassportImageUrl(traveler.passportImage);
        fileName = traveler.passportImage.split('/').pop() || 'Passport Photo';
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
}

// 여권 사진 다운로드
function downloadPassportPhotoInEdit(index) {
    const traveler = __allTravelers[index];
    if (!traveler) return;

    let downloadUrl = null;
    let fileName = 'passport_photo';

    // 새로 업로드한 파일인 경우 (base64)
    if (__tempPassportImages[index]) {
        downloadUrl = __tempPassportImages[index];
        fileName = 'passport_photo.jpg';
    }
    // 기존 서버에 저장된 이미지인 경우
    else if (traveler.passportImage) {
        downloadUrl = normalizePassportImageUrl(traveler.passportImage);
        fileName = traveler.passportImage.split('/').pop() || 'passport_photo';
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
}

// Visa 타입 변경 핸들러
let __tempVisaDocuments = {};

function handleVisaTypeChangeInEdit(index, value) {
    const container = document.getElementById(`visa-upload-container-${index}`);
    if (container) {
        container.style.display = (value === 'with_visa' || value === 'foreign') ? 'block' : 'none';
    }

    // with_visa나 foreign이 아닌 경우 visa document 초기화
    if (value !== 'with_visa' && value !== 'foreign') {
        removeVisaDocumentInEdit(index);
    }
}

// Passport 날짜 검증
function checkPassportDatesInEdit(index) {
    const issueEl = document.getElementById(`edit_passport_issue_${index}`);
    const expiryEl = document.getElementById(`edit_passport_expiry_${index}`);
    const warningEl = document.getElementById(`passport-date-warning-${index}`);

    if (!issueEl || !expiryEl || !warningEl) return;

    const issueDate = issueEl.value;
    const expiryDate = expiryEl.value;

    if (issueDate && expiryDate && new Date(issueDate) >= new Date(expiryDate)) {
        warningEl.style.display = 'block';
    } else {
        warningEl.style.display = 'none';
    }
}

// Visa Document 업로드 핸들러
function handleVisaUploadInEdit(index, input) {
    const file = input.files[0];
    if (!file) return;

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

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        __tempVisaDocuments[index] = base64;

        // UI 업데이트
        const infoEl = document.getElementById(`visa-document-info-${index}`);
        if (infoEl) {
            infoEl.classList.remove('hidden');
            const filenameEl = infoEl.querySelector('.visa-filename');
            if (filenameEl) filenameEl.textContent = file.name;
        }
    };
    reader.readAsDataURL(file);
}

// Visa Document 삭제
function removeVisaDocumentInEdit(index) {
    __tempVisaDocuments[index] = null; // null은 삭제 표시

    const infoEl = document.getElementById(`visa-document-info-${index}`);
    if (infoEl) infoEl.classList.add('hidden');

    const fileInput = document.getElementById(`visa_file_${index}`);
    if (fileInput) fileInput.value = '';
}

// Visa Document 미리보기
function previewVisaDocumentInEdit(index) {
    const traveler = __allTravelers[index];
    if (!traveler) return;

    let fileUrl = null;
    let fileName = 'Visa Document';
    let fileType = '';

    // 새로 업로드한 파일인 경우 (base64)
    if (__tempVisaDocuments[index]) {
        fileUrl = __tempVisaDocuments[index];
        fileName = 'Uploaded Visa';
        // base64에서 파일 타입 추출
        if (fileUrl.startsWith('data:application/pdf')) {
            fileType = 'application/pdf';
        } else {
            fileType = 'image';
        }
    }
    // 기존 서버에 저장된 파일인 경우
    else if (traveler.visaDocument) {
        fileUrl = normalizePassportImageUrl(traveler.visaDocument);
        fileName = traveler.visaDocument.split('/').pop() || 'Visa Document';
        // 확장자로 파일 타입 추정
        const ext = fileName.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            fileType = 'application/pdf';
        } else {
            fileType = 'image';
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
}

// Visa Document 다운로드
function downloadVisaDocumentInEdit(index) {
    const traveler = __allTravelers[index];
    if (!traveler) return;

    let downloadUrl = null;
    let fileName = 'visa_document';

    // 새로 업로드한 파일인 경우 (base64)
    if (__tempVisaDocuments[index]) {
        downloadUrl = __tempVisaDocuments[index];
        // base64에서 확장자 추정
        if (downloadUrl.startsWith('data:application/pdf')) {
            fileName = 'visa_document.pdf';
        } else {
            fileName = 'visa_document.jpg';
        }
    }
    // 기존 서버에 저장된 파일인 경우
    else if (traveler.visaDocument) {
        downloadUrl = normalizePassportImageUrl(traveler.visaDocument);
        fileName = traveler.visaDocument.split('/').pop() || 'visa_document';
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
}

// 임시 저장 변수 (단계별 진행용)
let __pendingTravelers = null;

// 전체 여행자 저장 (Step 1: 임시 저장 후 Room Option Modal로 이동)
async function saveAllTravelers() {
    const travelers = [];

    for (let i = 0; i < __allTravelers.length; i++) {
        const original = __allTravelers[i];

        // 여권 이미지 처리
        let passportImage = original.passportImage || '';
        if (__tempPassportImages.hasOwnProperty(i)) {
            passportImage = __tempPassportImages[i]; // null이면 삭제, base64면 새 이미지
        }

        // Visa Application 값 확인 (with_visa면 false, group/individual이면 true)
        const visaType = document.getElementById(`edit_visa_${i}`)?.value || 'with_visa';
        const visaRequired = (visaType !== 'with_visa');

        // Visa Document 처리
        let visaDocument = original.visaDocument || '';
        if (__tempVisaDocuments.hasOwnProperty(i)) {
            visaDocument = __tempVisaDocuments[i]; // null이면 삭제, base64면 새 문서
        }

        // Child Room 값
        const childRoomEl = document.getElementById(`edit_childroom_${i}`);
        const childRoom = childRoomEl ? (childRoomEl.value === 'yes') : (original.childRoom || false);

        // Flight Options 값 (original에서 가져옴)
        const flightOptions = original.flightOptions || [];
        const flightOptionPrices = original.flightOptionPrices || {};

        // birthDate 기반으로 travelerType 계산
        const birthDateValue = document.getElementById(`edit_birthdate_${i}`)?.value || '';
        let travelerType = original.travelerType || 'adult';
        if (birthDateValue) {
            const age = calculateAge(birthDateValue);
            if (age !== null) {
                if (age < 2) travelerType = 'infant';
                else if (age < 12) travelerType = 'child';
                else travelerType = 'adult';
            }
        }

        travelers.push({
            travelerType: travelerType,
            title: document.getElementById(`edit_title_${i}`)?.value || 'MR',
            firstName: document.getElementById(`edit_firstname_${i}`)?.value || '',
            lastName: document.getElementById(`edit_lastname_${i}`)?.value || '',
            gender: document.getElementById(`edit_gender_${i}`)?.value || 'male',
            birthDate: birthDateValue,
            nationality: document.getElementById(`edit_nationality_${i}`)?.value || '',
            passportNumber: document.getElementById(`edit_passport_${i}`)?.value || '',
            passportIssueDate: document.getElementById(`edit_passport_issue_${i}`)?.value || '',
            passportExpiryDate: document.getElementById(`edit_passport_expiry_${i}`)?.value || '',
            passportImage: passportImage,
            visaRequired: visaRequired,
            visaType: visaType,
            visaDocument: visaDocument,
            childRoom: childRoom,
            flightOptions: flightOptions,
            flightOptionPrices: flightOptionPrices,
            isPrimary: original.isMainTraveler == 1,
            isMainTraveler: original.isMainTraveler == 1 ? 1 : 0
        });
    }

    // 임시 저장 (서버 저장은 Room Option 선택 후)
    __pendingTravelers = travelers;

    // 디버깅 로그
    console.log('=== saveAllTravelers Debug ===');
    console.log('__pendingTravelers set:', __pendingTravelers);
    __pendingTravelers.forEach((t, i) => {
        console.log(`Saved Traveler ${i}:`, { travelerType: t.travelerType, visaType: t.visaType, birthDate: t.birthDate, childRoom: t.childRoom });
    });

    // 모달 닫기
    closeTravelerEditModal();

    // Room Option Modal 열기 (단계 2로 이동)
    setTimeout(() => {
        openRoomOptionModalEdit();
    }, 200);
}

// 날짜를 input[type="date"]용 형식으로 변환
function formatDateForInput(dateStr) {
    if (!dateStr) return '';
    // YYYYMMDD 형식
    if (/^\d{8}$/.test(dateStr)) {
        return `${dateStr.substring(0, 4)}-${dateStr.substring(4, 6)}-${dateStr.substring(6, 8)}`;
    }
    // YYYY-MM-DD 형식 또는 다른 형식
    try {
        const date = new Date(dateStr);
        if (!isNaN(date.getTime())) {
            return date.toISOString().split('T')[0];
        }
    } catch (e) {}
    return dateStr;
}

// 여행자 상세 모달 닫기
function closeTravelerModal() {
    document.getElementById('travelerDetailModal').style.display = 'none';
    __currentTravelerIndex = -1;
}

// 항공편 정보 렌더링
function renderFlightInfo(booking, selectedOptions = {}) {
    // 출국편 정보
    if (booking.outboundFlight) {
        const outFlight = booking.outboundFlight;
        const outFlightNoInput = document.getElementById('out_flight_no');
        if (outFlightNoInput && outFlight.flightNumber) {
            outFlightNoInput.value = outFlight.flightNumber;
        }
        
        const outDepartDtInput = document.getElementById('out_depart_dt');
        if (outDepartDtInput && outFlight.departureDateTime) {
            outDepartDtInput.value = formatDateTime(outFlight.departureDateTime);
        }
        
        const outArriveDtInput = document.getElementById('out_arrive_dt');
        if (outArriveDtInput && outFlight.arrivalDateTime) {
            outArriveDtInput.value = formatDateTime(outFlight.arrivalDateTime);
        }
        
        const outDepartAirportInput = document.getElementById('out_depart_airport');
        if (outDepartAirportInput && outFlight.departureAirport) {
            outDepartAirportInput.value = outFlight.departureAirport;
        }
        
        const outArriveAirportInput = document.getElementById('out_arrive_airport');
        if (outArriveAirportInput && outFlight.arrivalAirport) {
            outArriveAirportInput.value = outFlight.arrivalAirport;
        }
    }
    
    // 귀국편 정보
    if (booking.inboundFlight) {
        const inFlight = booking.inboundFlight;
        const inFlightNoInput = document.getElementById('in_flight_no');
        if (inFlightNoInput && inFlight.flightNumber) {
            inFlightNoInput.value = inFlight.flightNumber;
        }
        
        const inDepartDtInput = document.getElementById('in_depart_dt');
        if (inDepartDtInput && inFlight.departureDateTime) {
            inDepartDtInput.value = formatDateTime(inFlight.departureDateTime);
        }
        
        const inArriveDtInput = document.getElementById('in_arrive_dt');
        if (inArriveDtInput && inFlight.arrivalDateTime) {
            inArriveDtInput.value = formatDateTime(inFlight.arrivalDateTime);
        }
        
        const inDepartAirportInput = document.getElementById('in_depart_airport');
        if (inDepartAirportInput && inFlight.departureAirport) {
            inDepartAirportInput.value = inFlight.departureAirport;
        }
        
        const inArriveAirportInput = document.getElementById('in_arrive_airport');
        if (inArriveAirportInput && inFlight.arrivalAirport) {
            inArriveAirportInput.value = inFlight.arrivalAirport;
        }
    }
    
    // selectedOptions에서 항공편 정보 확인 (다른 형식일 수 있음)
    if (selectedOptions && selectedOptions.flightInfo) {
        const flightInfo = selectedOptions.flightInfo;
        
        // 출국편
        if (flightInfo.outbound) {
            const outFlightNoInput = document.getElementById('out_flight_no');
            if (outFlightNoInput && !outFlightNoInput.value && flightInfo.outbound.flightNo) {
                outFlightNoInput.value = flightInfo.outbound.flightNo;
            }
            
            const outDepartDtInput = document.getElementById('out_depart_dt');
            if (outDepartDtInput && !outDepartDtInput.value && flightInfo.outbound.departureDate) {
                outDepartDtInput.value = flightInfo.outbound.departureDate;
            }
            
            const outArriveDtInput = document.getElementById('out_arrive_dt');
            if (outArriveDtInput && !outArriveDtInput.value && flightInfo.outbound.arrivalDate) {
                outArriveDtInput.value = flightInfo.outbound.arrivalDate;
            }
        }
        
        // 귀국편
        if (flightInfo.inbound) {
            const inFlightNoInput = document.getElementById('in_flight_no');
            if (inFlightNoInput && !inFlightNoInput.value && flightInfo.inbound.flightNo) {
                inFlightNoInput.value = flightInfo.inbound.flightNo;
            }
            
            const inDepartDtInput = document.getElementById('in_depart_dt');
            if (inDepartDtInput && !inDepartDtInput.value && flightInfo.inbound.departureDate) {
                inDepartDtInput.value = flightInfo.inbound.departureDate;
            }
            
            const inArriveDtInput = document.getElementById('in_arrive_dt');
            if (inArriveDtInput && !inArriveDtInput.value && flightInfo.inbound.arrivalDate) {
                inArriveDtInput.value = flightInfo.inbound.arrivalDate;
            }
        }
    }
}

// 기존 테이블 기반 렌더링 (사용 안함 - 카드 형식으로 변경됨)
function renderTravelers(travelers, retryCount = 0) {
    // 카드 형식으로 변경되어 더 이상 사용하지 않음
    // initTravelerPagination이 renderTravelerSummaryList를 호출함
}

// 예약금 비율 상수
const DEPOSIT_RATE = 0.1; // 10% (현재 회원 기능이 없기 때문에 고정값)

// 선금 자동 계산 함수 (공통 로직)
function calculateDepositAmount(booking, orderAmount) {
    // DB에 저장된 선금 확인 (0이거나 null/undefined인 경우도 포함)
    const savedDepositAmount = parseFloat(booking.depositAmount || booking.deposit || 0);
    
    // 자동 계산: Advance payment = Order Amount × (1 - 예약금 비율)
    const calculatedDepositAmount = orderAmount * (1 - DEPOSIT_RATE);
    
    // DB에 저장된 선금이 0보다 크면 저장된 값 사용, 아니면(0이거나 null/undefined) 자동 계산값 사용
    // 즉, DB에 0이 저장되어 있어도 자동 계산값을 사용
    return (savedDepositAmount > 0) ? savedDepositAmount : calculatedDepositAmount;
}

// 잔금 계산 함수 (공통 로직)
// Balance = Order Amount - Advance payment
function calculateBalanceAmount(orderAmount, advancePayment) {
    const balance = orderAmount - advancePayment;
    return Math.max(0, balance); // 음수 방지
}

// Advance payment 변경 시 Balance 자동 재계산 함수
function updateBalanceOnDepositChange() {
    const orderAmountInput = document.getElementById('order_amount') || document.getElementById('total_amount');
    const depositAmountInput = document.getElementById('deposit_amount');
    const depositConfirmedAmountInput = document.getElementById('deposit_confirmed_amount');
    const balanceAmountInput = document.getElementById('balance_amount');
    
    if (!orderAmountInput || !balanceAmountInput) return;
    
    // Order Amount 가져오기
    const orderAmount = parseFloat(orderAmountInput.value.replace(/[^\d.]/g, '')) || 0;
    if (orderAmount <= 0) return;
    
    // 현재 Advance payment 값 가져오기 (확인된 선금 입력 필드 우선, 없으면 선금 입력 필드)
    let currentDepositAmount = 0;
    if (depositConfirmedAmountInput && !depositConfirmedAmountInput.disabled) {
        currentDepositAmount = parseFloat(depositConfirmedAmountInput.value.replace(/[^\d.]/g, '')) || 0;
    } else if (depositAmountInput && !depositAmountInput.disabled) {
        currentDepositAmount = parseFloat(depositAmountInput.value.replace(/[^\d.]/g, '')) || 0;
    } else if (depositAmountInput) {
        // disabled인 경우에도 값 가져오기
        currentDepositAmount = parseFloat(depositAmountInput.value.replace(/[^\d.]/g, '')) || 0;
    }
    
    // Balance 재계산
    const balance = calculateBalanceAmount(orderAmount, currentDepositAmount);
    balanceAmountInput.value = formatPriceNumber(balance);
}

// deposit_amount 필드 변경 이벤트 핸들러
function handleDepositAmountChange() {
    updateBalanceOnDepositChange();
}

function renderPaymentInfo(booking, pricingOptions = []) {
    // 총 결제 금액
    const totalAmountInput = document.getElementById('order_amount') || document.getElementById('total_amount');
    const orderAmount = parseFloat(booking.orderAmount || booking.totalAmount || booking.total_amount || 0);
    if (totalAmountInput && orderAmount > 0) {
        totalAmountInput.value = formatPriceNumber(orderAmount);
    }

    // Payment Type 확인 및 표시
    const paymentType = booking.paymentType || 'staged';
    const paymentTypeDisplay = document.getElementById('paymentTypeDisplay');
    const stagedPaymentSections = document.getElementById('stagedPaymentSections');
    const fullPaymentSection = document.getElementById('fullPaymentSection');

    if (paymentTypeDisplay) {
        paymentTypeDisplay.textContent = paymentType === 'full' ? 'Full Payment' : 'Staged Payment (3-Step)';
        paymentTypeDisplay.style.color = paymentType === 'full' ? '#2E7D32' : '#1565C0';
    }

    // paymentType에 따라 섹션 표시/숨김
    if (paymentType === 'full') {
        if (stagedPaymentSections) stagedPaymentSections.style.display = 'none';
        if (fullPaymentSection) fullPaymentSection.style.display = 'block';

        // Full Payment 정보 렌더링
        renderFullPaymentInfo(booking, orderAmount);
        return; // Full Payment인 경우 여기서 종료
    } else {
        if (stagedPaymentSections) stagedPaymentSections.style.display = 'block';
        if (fullPaymentSection) fullPaymentSection.style.display = 'none';
    }

    // 출발일 기준으로 deadline 자동 계산 (DB에 값이 없을 경우)
    const departureDate = booking.departureDate ? new Date(booking.departureDate) : null;
    // 예약 생성일 (데드라인 계산에 사용)
    const reservationDate = booking.createdAt ? new Date(booking.createdAt) : new Date();
    reservationDate.setHours(0, 0, 0, 0);

    // 인원수 가져오기 (3단계 결제 금액 자동 계산용)
    const adultCount = Number(booking.adults || 0);
    const childCount = Number(booking.children || 0);
    const infantCount = Number(booking.infants || 0);
    const adultChildCount = adultCount + childCount; // Down/Second Payment 대상 인원

    // Infant 가격 가져오기: pricingOptions에서 찾거나 booking에서 가져옴
    let infantPrice = parseFloat(booking.infantPrice || 0);
    if (infantPrice <= 0 && pricingOptions.length > 0) {
        for (const opt of pricingOptions) {
            const name = (opt.optionName || opt.option_name || '').toLowerCase();
            if (name.includes('infant') || name.includes('유아') || name.includes('baby')) {
                infantPrice = parseFloat(opt.price || 0);
                break;
            }
        }
    }

    // 3단계 결제 정보 렌더링
    // Down Payment = 5000 × (Adult + Child 인원수) - 항상 인원수 기반 계산
    const downPaymentAmountInput = document.getElementById('downPaymentAmount');
    let downPaymentAmount = 0;
    if (downPaymentAmountInput) {
        // 항상 인원수 기반으로 계산: 5000 × (Adult + Child)
        if (adultChildCount > 0) {
            downPaymentAmount = 5000 * adultChildCount;
        }
        downPaymentAmountInput.value = downPaymentAmount > 0 ? formatPriceNumber(downPaymentAmount) : '-';
    }
    // 데드라인 자동 계산 (DB에 값이 없을 경우)
    const calculatedDeadlines = calculatePaymentDeadlinesFromBooking(reservationDate, departureDate);

    const downPaymentDeadlineInput = document.getElementById('downPaymentDeadline');
    if (downPaymentDeadlineInput) {
        let downDue = booking.downPaymentDueDate;
        if (!downDue && departureDate) {
            downDue = calculatedDeadlines.down;
        }
        downPaymentDeadlineInput.value = downDue || '-';
    }

    // Second Payment = 10000 × (Adult + Child 인원수) - 항상 인원수 기반 계산
    const secondPaymentAmountInput = document.getElementById('secondPaymentAmount');
    let secondPaymentAmount = 0;
    if (secondPaymentAmountInput) {
        // 항상 인원수 기반으로 계산: 10000 × (Adult + Child)
        if (adultChildCount > 0) {
            secondPaymentAmount = 10000 * adultChildCount;
        }
        secondPaymentAmountInput.value = secondPaymentAmount > 0 ? formatPriceNumber(secondPaymentAmount) : '-';
    }
    const secondPaymentDeadlineInput = document.getElementById('secondPaymentDeadline');
    if (secondPaymentDeadlineInput) {
        let secondDue = booking.advancePaymentDueDate;
        if (!secondDue && departureDate) {
            secondDue = calculatedDeadlines.second;
        }
        secondPaymentDeadlineInput.value = secondDue || '-';
    }

    // Balance = 나머지 금액 + (Infant 금액 × Infant 인원수)
    // 나머지 금액 = Order Amount - Down Payment - Second Payment - 항상 계산
    const balanceAmountInput = document.getElementById('balanceAmount');
    if (balanceAmountInput) {
        let amount = 0;
        // 항상 계산: Order Amount - Down Payment - Second Payment + Infant Total
        if (orderAmount > 0) {
            const infantTotal = infantPrice * infantCount;
            amount = orderAmount - downPaymentAmount - secondPaymentAmount + infantTotal;
        }
        balanceAmountInput.value = amount > 0 ? formatPriceNumber(amount) : '-';
    }
    const balanceDeadlineInput = document.getElementById('balanceDeadline');
    if (balanceDeadlineInput) {
        let balDue = booking.balanceDueDate;
        if (!balDue && departureDate) {
            balDue = calculatedDeadlines.balance;
        }
        balanceDeadlineInput.value = balDue || '-';
    }

    // 3단계 결제 섹션 UI 상태 업데이트
    updatePaymentSections(booking);
}

// === Payment Deadline 자동 계산 함수들 ===
// 규칙:
// - 출발 34일 이내: Full Payment만, deadline = 예약일 + 1일 (24시간)
// - 출발 35~44일: Full Payment만, deadline = 예약일 + 3일
// - 출발 44일 초과: Staged Payment
//   - Down Payment: 예약일 + 3일
//   - Second Payment: Down Payment + 30일
//   - Balance: 출발일 - 30일

function calculatePaymentDeadlinesFromBooking(reservationDate, departureDate) {
    if (!reservationDate || !departureDate) {
        return { down: null, second: null, balance: null };
    }

    const resDate = new Date(reservationDate);
    resDate.setHours(0, 0, 0, 0);
    const departure = new Date(departureDate);
    departure.setHours(0, 0, 0, 0);

    const daysUntilDeparture = Math.ceil((departure - resDate) / (1000 * 60 * 60 * 24));

    // 44일 이내: Full Payment 강제 (Staged Payment 없음)
    if (daysUntilDeparture <= 44) {
        return {
            down: null,
            second: null,
            balance: null
        };
    }

    // 44일 초과: Staged Payment
    // Down Payment: 예약일 + 3일
    const downDeadline = new Date(resDate);
    downDeadline.setDate(downDeadline.getDate() + 3);

    // Second Payment: Down Payment + 30일
    const secondDeadline = new Date(downDeadline);
    secondDeadline.setDate(secondDeadline.getDate() + 30);

    // Balance: 출발일 - 30일
    let balanceDeadline = new Date(departure);
    balanceDeadline.setDate(balanceDeadline.getDate() - 30);

    // Balance가 Second보다 짧으면 Second와 동일하게
    if (balanceDeadline < secondDeadline) {
        balanceDeadline = new Date(secondDeadline);
    }

    return {
        down: formatDateYMD(downDeadline),
        second: formatDateYMD(secondDeadline),
        balance: formatDateYMD(balanceDeadline)
    };
}

// 개별 함수들 (하위 호환성 유지)
function calculateDownPaymentDeadline(reservationDate, departureDate) {
    const deadlines = calculatePaymentDeadlinesFromBooking(reservationDate, departureDate);
    return deadlines.down;
}

function calculateDownPaymentDeadlineDateObj(reservationDate, departureDate) {
    const deadline = calculateDownPaymentDeadline(reservationDate, departureDate);
    return deadline ? new Date(deadline) : null;
}

function calculateSecondPaymentDeadline(reservationDate, departureDate) {
    const deadlines = calculatePaymentDeadlinesFromBooking(reservationDate, departureDate);
    return deadlines.second;
}

function calculateSecondPaymentDeadlineDateObj(reservationDate, departureDate) {
    const deadline = calculateSecondPaymentDeadline(reservationDate, departureDate);
    return deadline ? new Date(deadline) : null;
}

function calculateBalanceDeadline(reservationDate, departureDate) {
    const deadlines = calculatePaymentDeadlinesFromBooking(reservationDate, departureDate);
    return deadlines.balance;
}

// 날짜를 YYYY-MM-DD 형식으로 변환
function formatDateYMD(date) {
    if (!date) return null;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 3단계 결제 섹션 UI 상태 관리
function updatePaymentSections(booking) {
    // Down Payment 확인 여부
    const downPaymentConfirmed = !!(booking.downPaymentConfirmedAt);
    // Second Payment 확인 여부
    const secondPaymentConfirmed = !!(booking.advancePaymentConfirmedAt);
    // Balance 확인 여부
    const balanceConfirmed = !!(booking.balanceConfirmedAt);

    // Down Payment 파일 정보
    const downFile = booking.downPaymentFile || '';
    const downFileName = booking.downPaymentFileName || extractFileName(downFile);

    // Second Payment 파일 정보
    const secondFile = booking.advancePaymentFile || '';
    const secondFileName = booking.advancePaymentFileName || extractFileName(secondFile);

    // Balance 파일 정보
    const balFile = booking.balanceFile || '';
    const balFileName = booking.balanceFileName || extractFileName(balFile);

    // === Down Payment 섹션 ===
    const downFileDisplay = document.getElementById('down_file_display');
    const downFileUpload = document.getElementById('down_file_upload');
    const downFileNameEl = document.getElementById('down_file_name');
    const downloadDownBtn = document.getElementById('downloadDownFileBtn');
    const viewDownBtn = document.getElementById('viewDownFileBtn');
    const deleteDownBtn = document.getElementById('deleteDownFileBtn');
    const downPaymentStatus = document.getElementById('downPaymentStatus');

    if (downPaymentConfirmed) {
        // 확인됨 상태
        if (downPaymentStatus) {
            downPaymentStatus.innerHTML = '<span style="background:#4CAF50;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Confirmed</span>';
        }
        if (downFileDisplay) downFileDisplay.style.display = downFile ? 'flex' : 'none';
        if (downFileUpload) downFileUpload.style.display = 'none';
        if (downFileNameEl) downFileNameEl.textContent = downFileName || 'File';
        if (downloadDownBtn) downloadDownBtn.disabled = !downFile;
        if (viewDownBtn) viewDownBtn.disabled = !downFile;
        if (deleteDownBtn) deleteDownBtn.style.display = 'none'; // 확인 후 삭제 불가
    } else if (downFile) {
        // 파일 업로드됨, 확인 대기중
        if (downPaymentStatus) {
            downPaymentStatus.innerHTML = '<span style="background:#FF9800;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Pending Confirmation</span>';
        }
        if (downFileDisplay) downFileDisplay.style.display = 'flex';
        if (downFileUpload) downFileUpload.style.display = 'none';
        if (downFileNameEl) downFileNameEl.textContent = downFileName || 'File';
        if (downloadDownBtn) downloadDownBtn.disabled = false;
        if (viewDownBtn) viewDownBtn.disabled = false;
        if (deleteDownBtn) {
            deleteDownBtn.style.display = '';
            deleteDownBtn.disabled = false;
        }
    } else {
        // 파일 미업로드
        if (downPaymentStatus) {
            downPaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
        }
        if (downFileDisplay) downFileDisplay.style.display = 'none';
        if (downFileUpload) downFileUpload.style.display = 'block';
    }

    // === Second Payment 섹션 ===
    const secondDisabledNotice = document.getElementById('secondPaymentDisabledNotice');
    const secondPaymentFields = document.getElementById('secondPaymentFields');
    const secondFileDisplay = document.getElementById('second_file_display');
    const secondFileUpload = document.getElementById('second_file_upload');
    const secondFileNameEl = document.getElementById('second_file_name');
    const downloadSecondBtn = document.getElementById('downloadSecondFileBtn');
    const viewSecondBtn = document.getElementById('viewSecondFileBtn');
    const deleteSecondBtn = document.getElementById('deleteSecondFileBtn');
    const secondPaymentStatus = document.getElementById('secondPaymentStatus');

    // 관리자가 수동으로 second payment 상태로 변경한 경우 체크
    const bookingStatus = (booking.bookingStatus || '').toLowerCase();
    const isSecondPaymentStage = ['waiting_second_payment', 'checking_second_payment'].includes(bookingStatus);

    if (!downPaymentConfirmed && !isSecondPaymentStage) {
        // Down Payment 미확인 & 관리자가 second payment 단계로 변경하지 않음 → Second Payment 비활성화
        if (secondDisabledNotice) secondDisabledNotice.style.display = 'block';
        if (secondPaymentFields) secondPaymentFields.style.display = 'none';
        if (secondPaymentStatus) secondPaymentStatus.innerHTML = '';
    } else if (secondPaymentConfirmed) {
        // Second Payment 확인됨
        if (secondDisabledNotice) secondDisabledNotice.style.display = 'none';
        if (secondPaymentFields) secondPaymentFields.style.display = 'grid';
        if (secondPaymentStatus) {
            secondPaymentStatus.innerHTML = '<span style="background:#4CAF50;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Confirmed</span>';
        }
        if (secondFileDisplay) secondFileDisplay.style.display = secondFile ? 'flex' : 'none';
        if (secondFileUpload) secondFileUpload.style.display = 'none';
        if (secondFileNameEl) secondFileNameEl.textContent = secondFileName || 'File';
        if (downloadSecondBtn) downloadSecondBtn.disabled = !secondFile;
        if (viewSecondBtn) viewSecondBtn.disabled = !secondFile;
        if (deleteSecondBtn) deleteSecondBtn.style.display = 'none';
    } else if (secondFile) {
        // 파일 업로드됨, 확인 대기중
        if (secondDisabledNotice) secondDisabledNotice.style.display = 'none';
        if (secondPaymentFields) secondPaymentFields.style.display = 'grid';
        if (secondPaymentStatus) {
            secondPaymentStatus.innerHTML = '<span style="background:#FF9800;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Pending Confirmation</span>';
        }
        if (secondFileDisplay) secondFileDisplay.style.display = 'flex';
        if (secondFileUpload) secondFileUpload.style.display = 'none';
        if (secondFileNameEl) secondFileNameEl.textContent = secondFileName || 'File';
        if (downloadSecondBtn) downloadSecondBtn.disabled = false;
        if (viewSecondBtn) viewSecondBtn.disabled = false;
        if (deleteSecondBtn) {
            deleteSecondBtn.style.display = '';
            deleteSecondBtn.disabled = false;
        }
    } else {
        // Down Payment 확인됨, Second Payment 미업로드
        if (secondDisabledNotice) secondDisabledNotice.style.display = 'none';
        if (secondPaymentFields) secondPaymentFields.style.display = 'grid';
        if (secondPaymentStatus) {
            secondPaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
        }
        if (secondFileDisplay) secondFileDisplay.style.display = 'none';
        if (secondFileUpload) secondFileUpload.style.display = 'block';
    }

    // === Balance 섹션 ===
    const balanceDisabledNotice = document.getElementById('balanceDisabledNotice');
    const balancePaymentFields = document.getElementById('balancePaymentFields');
    const balFileDisplay = document.getElementById('balance_file_display');
    const balFileUpload = document.getElementById('balance_file_upload');
    const balFileNameEl = document.getElementById('balance_file_name');
    const downloadBalBtn = document.getElementById('downloadBalanceFileBtn');
    const viewBalBtn = document.getElementById('viewBalanceFileBtn');
    const deleteBalBtn = document.getElementById('deleteBalanceFileBtn');
    const balancePaymentStatus = document.getElementById('balancePaymentStatus');

    // 관리자가 수동으로 balance 상태로 변경한 경우 체크
    const isBalanceStage = ['waiting_balance', 'checking_balance'].includes(bookingStatus);

    if (!secondPaymentConfirmed && !isBalanceStage) {
        // Second Payment 미확인 & 관리자가 balance 단계로 변경하지 않음 → Balance 비활성화
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'block';
        if (balancePaymentFields) balancePaymentFields.style.display = 'none';
        if (balancePaymentStatus) balancePaymentStatus.innerHTML = '';
    } else if (balanceConfirmed) {
        // Balance 확인됨
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'none';
        if (balancePaymentFields) balancePaymentFields.style.display = 'grid';
        if (balancePaymentStatus) {
            balancePaymentStatus.innerHTML = '<span style="background:#4CAF50;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Confirmed</span>';
        }
        if (balFileDisplay) balFileDisplay.style.display = balFile ? 'flex' : 'none';
        if (balFileUpload) balFileUpload.style.display = 'none';
        if (balFileNameEl) balFileNameEl.textContent = balFileName || 'File';
        if (downloadBalBtn) downloadBalBtn.disabled = !balFile;
        if (viewBalBtn) viewBalBtn.disabled = !balFile;
        if (deleteBalBtn) deleteBalBtn.style.display = 'none';
    } else if (balFile) {
        // 파일 업로드됨, 확인 대기중
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'none';
        if (balancePaymentFields) balancePaymentFields.style.display = 'grid';
        if (balancePaymentStatus) {
            balancePaymentStatus.innerHTML = '<span style="background:#FF9800;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Pending Confirmation</span>';
        }
        if (balFileDisplay) balFileDisplay.style.display = 'flex';
        if (balFileUpload) balFileUpload.style.display = 'none';
        if (balFileNameEl) balFileNameEl.textContent = balFileName || 'File';
        if (downloadBalBtn) downloadBalBtn.disabled = false;
        if (viewBalBtn) viewBalBtn.disabled = false;
        if (deleteBalBtn) {
            deleteBalBtn.style.display = '';
            deleteBalBtn.disabled = false;
        }
    } else {
        // Second Payment 확인됨, Balance 미업로드
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'none';
        if (balancePaymentFields) balancePaymentFields.style.display = 'grid';
        if (balancePaymentStatus) {
            balancePaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
        }
        if (balFileDisplay) balFileDisplay.style.display = 'none';
        if (balFileUpload) balFileUpload.style.display = 'block';
    }

    // === Rejection Reason 표시 ===
    displayRejectionAlert('down', booking.downPaymentRejectionReason, booking.downPaymentRejectedAt);
    displayRejectionAlert('second', booking.advancePaymentRejectionReason, booking.advancePaymentRejectedAt);
    displayRejectionAlert('balance', booking.balanceRejectionReason, booking.balanceRejectedAt);
}

// Rejection Alert 표시 함수
function displayRejectionAlert(type, reason, rejectedAt) {
    const alertId = type === 'down' ? 'downPaymentRejectionAlert'
                  : type === 'second' ? 'secondPaymentRejectionAlert'
                  : 'balanceRejectionAlert';
    const reasonId = type === 'down' ? 'downPaymentRejectionReason'
                   : type === 'second' ? 'secondPaymentRejectionReason'
                   : 'balanceRejectionReason';
    const dateId = type === 'down' ? 'downPaymentRejectedAt'
                 : type === 'second' ? 'secondPaymentRejectedAt'
                 : 'balanceRejectedAt';

    const alertEl = document.getElementById(alertId);
    const reasonEl = document.getElementById(reasonId);
    const dateEl = document.getElementById(dateId);

    if (!alertEl) return;

    if (reason && reason.trim()) {
        alertEl.style.display = 'block';
        if (reasonEl) reasonEl.textContent = reason;
        if (dateEl && rejectedAt) {
            const date = new Date(rejectedAt);
            dateEl.textContent = 'Rejected on: ' + date.toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } else if (dateEl) {
            dateEl.textContent = '';
        }
    } else {
        alertEl.style.display = 'none';
    }
}

function updatePaymentUI(booking) {
    // 결제 상태 확인
    const depositConfirmed = booking.depositConfirmed || booking.depositStatus === 'confirmed' || booking.depositStatus === 'paid';
    const balanceConfirmed = booking.balanceConfirmed || booking.balanceStatus === 'confirmed' || booking.balanceStatus === 'paid';
    
    // Order Amount 가져오기
    const orderAmount = parseFloat(booking.totalAmount) || 0;
    
    // 선금 계산 (DB에 0이 저장되어 있어도 자동 계산)
    // 확인된 선금이 있으면 우선 사용, 없으면 자동 계산
    const depositConfirmedAmount = parseFloat(booking.depositConfirmedAmount || 0);
    const depositAmount = (depositConfirmedAmount > 0) 
        ? depositConfirmedAmount 
        : calculateDepositAmount(booking, orderAmount);
    
    // 잔금 계산: Balance = Order Amount - Advance payment
    const balanceConfirmedAmount = parseFloat(booking.balanceConfirmedAmount || 0);
    const balanceAmount = (balanceConfirmedAmount > 0)
        ? balanceConfirmedAmount
        : calculateBalanceAmount(orderAmount, depositAmount);
    
    // 선금 관련 요소
    const depositDueInput = document.getElementById('deposit_due');
    const depositConfirmContainer = getOrCreatePaymentConfirmContainer('deposit');
    
    // 잔금 관련 요소
    const balanceDueInput = document.getElementById('balance_due');
    const balanceConfirmContainer = getOrCreatePaymentConfirmContainer('balance');
    
    // Case 1: 선금 확인 전
    if (!depositConfirmed && !balanceConfirmed) {
        // 선금 확인 버튼 표시
        setupDepositConfirm(depositConfirmContainer, depositAmount, depositDueInput, true);
        // 잔금 확인 버튼 표시
        setupBalanceConfirm(balanceConfirmContainer, balanceAmount, balanceDueInput, true);
    }
    // Case 2: 잔금 확인 전 (선금 확인 완료, 잔금 미확인)
    else if (depositConfirmed && !balanceConfirmed) {
        // 선금 확인 완료 표시
        setupDepositConfirmed(depositConfirmContainer, depositAmount, depositDueInput, false);
        // 잔금 확인 버튼 표시
        setupBalanceConfirm(balanceConfirmContainer, balanceAmount, balanceDueInput, true);
    }
    // Case 3: 그외 상태 (둘 다 확인 완료)
    else {
        // 선금 확인 완료 표시
        setupDepositConfirmed(depositConfirmContainer, depositAmount, depositDueInput, false);
        // 잔금 확인 완료 표시
        setupBalanceConfirmed(balanceConfirmContainer, balanceAmount, balanceDueInput, false);
    }
}

function renderDepositProofFile(booking) {
    const container = document.getElementById('deposit_proof_container');
    const nameEl = document.getElementById('deposit_proof_name');
    const downloadBtn = document.getElementById('deposit_proof_download');
    const removeBtn = document.getElementById('deposit_proof_remove');
    
    if (!container || !nameEl || !downloadBtn) return;
    
    const rawPath = booking.downPaymentFile || booking.depositProofFile || booking.depositProof || '';
    if (!rawPath) {
        nameEl.textContent = 'No file uploaded.';
        nameEl.dataset.lanEng = 'No file uploaded';
        downloadBtn.disabled = true;
        downloadBtn.onclick = null;
        if (removeBtn) {
            removeBtn.disabled = true;
            removeBtn.onclick = null;
        }
        return;
    }
    
    const fileName = extractFileName(rawPath);
    const label = formatDepositProofLabel(fileName);
    nameEl.textContent = label;
    delete nameEl.dataset.lanEng;
    
    const fileUrl = buildFileUrl(rawPath);
    if (fileUrl) {
        // 다운로드 버튼 활성화
        downloadBtn.disabled = false;
        downloadBtn.onclick = () => window.open(fileUrl, '_blank', 'noopener');
        
        // 삭제 버튼 활성화 및 이벤트 리스너 추가
        if (removeBtn) {
            removeBtn.disabled = false;
            // 기존 이벤트 리스너 제거 후 새로 추가 (중복 방지)
            const newRemoveBtn = removeBtn.cloneNode(true);
            removeBtn.parentNode.replaceChild(newRemoveBtn, removeBtn);
            newRemoveBtn.addEventListener('click', () => handleRemoveDepositProofFile(booking.bookingId));
        }
    } else {
        downloadBtn.disabled = true;
        downloadBtn.onclick = null;
        if (removeBtn) {
            removeBtn.disabled = true;
            removeBtn.onclick = null;
        }
    }
}

// 선금 증빙 파일 삭제 처리
async function handleRemoveDepositProofFile(bookingId) {
    if (!bookingId) {
        alert('Booking ID is missing.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete the deposit proof file?')) {
        return;
    }
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'removeDepositProofFile',
                bookingId: bookingId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Deposit proof file has been deleted.');
            loadReservationDetail(); // 페이지 재로드
        } else {
            alert('Failed to delete file: ' + result.message);
        }
    } catch (error) {
        console.error('Error removing deposit proof file:', error);
        alert('An error occurred while deleting the file.');
    }
}

function extractFileName(path) {
    if (!path) return '';
    const normalized = path.replace(/\\/g, '/');
    const segments = normalized.split('/');
    return segments.pop() || '';
}

function formatDepositProofLabel(fileName) {
    if (!fileName) return '첨부된 파일';
    const parts = fileName.split('.');
    if (parts.length > 1) {
        const ext = parts.pop().toUpperCase();
        return `${parts.join('.')} [${ext}]`;
    }
    return fileName;
}

function buildFileUrl(path) {
    if (!path) return '';
    let cleaned = path.replace(/\\/g, '/');
    cleaned = cleaned.replace(/smart-travel2\//gi, '');
    cleaned = cleaned.replace(/\/uploads\/uploads\//gi, '/uploads/');
    cleaned = cleaned.replace(/\/{2,}/g, '/');
    if (cleaned.startsWith('http://') || cleaned.startsWith('https://')) {
        return cleaned;
    }
    if (!cleaned.startsWith('/')) {
        cleaned = '/' + cleaned.replace(/^\/+/, '');
    }
    return window.location.origin + cleaned;
}

function getOrCreatePaymentConfirmContainer(type) {
    // 선금/잔금 확인 컨테이너 찾기 또는 생성
    const labelName = type === 'deposit' ? '선금 확인' : '잔금 확인';
    const labelElement = Array.from(document.querySelectorAll('.label-name')).find(el => 
        el.textContent.includes(labelName)
    );
    
    if (labelElement) {
        const gridItem = labelElement.closest('.grid-item');
        if (gridItem) {
            let container = gridItem.querySelector('.payment-confirm-group');
            if (!container) {
                container = document.createElement('div');
                container.className = 'payment-confirm-group';
                gridItem.appendChild(container);
            }
            return container;
        }
    }
    
    // 기존 요소를 찾지 못한 경우, deposit_due 또는 balance_due 다음에 추가
    const dueInputId = type === 'deposit' ? 'deposit_due' : 'balance_due';
    const dueInput = document.getElementById(dueInputId);
    if (dueInput) {
        const gridItem = dueInput.closest('.grid-item');
        if (gridItem) {
            const nextGridItem = gridItem.nextElementSibling;
            if (nextGridItem && nextGridItem.classList.contains('grid-item')) {
                let container = nextGridItem.querySelector('.payment-confirm-group');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'payment-confirm-group';
                    
                    // 라벨 추가
                    const label = document.createElement('label');
                    label.className = 'label-name';
                    label.textContent = labelName;
                    nextGridItem.insertBefore(label, nextGridItem.firstChild);
                    nextGridItem.appendChild(container);
                }
                return container;
            }
        }
    }
    
    return null;
}

function setupDepositConfirm(container, defaultAmount, dueInput, editable) {
    if (!container) return;
    
    container.innerHTML = '';
    
    // 선금 확인 버튼
    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.id = 'deposit-confirm-btn';
    confirmBtn.className = 'jw-button typeB';
    confirmBtn.textContent = 'Confirm Deposit';
    confirmBtn.style.display = 'block';
    confirmBtn.addEventListener('click', () => handleDepositConfirm());
    
    // 금액 입력 필드
    const amountInputWrapper = document.createElement('div');
    amountInputWrapper.className = 'payment-amount-input';
    amountInputWrapper.style.display = editable ? 'flex' : 'none';
    amountInputWrapper.style.alignItems = 'center';
    amountInputWrapper.style.gap = '8px';
    
    const amountInput = document.createElement('input');
    amountInput.type = 'text';
    amountInput.id = 'deposit_confirmed_amount';
    amountInput.className = 'form-control';
    amountInput.placeholder = 'Enter amount';
    amountInput.value = formatPriceNumber(defaultAmount);
    if (!editable) amountInput.disabled = true;
    
    // Advance payment 수정 시 Balance 자동 재계산
    if (editable) {
        amountInput.addEventListener('input', function() {
            updateBalanceOnDepositChange();
        });
    }
    
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn-close';
    clearBtn.id = 'deposit-amount-clear';
    clearBtn.textContent = '×';
    clearBtn.addEventListener('click', () => {
        amountInput.value = '';
        updateBalanceOnDepositChange();
    });
    
    amountInputWrapper.appendChild(amountInput);
    amountInputWrapper.appendChild(clearBtn);
    
    container.appendChild(confirmBtn);
    container.appendChild(amountInputWrapper);
    
    // 입금 기한 입력 필드 처리 (항상 비활성화)
    if (dueInput) {
        dueInput.disabled = true; // 항상 비활성화
    }
}

function setupDepositConfirmed(container, amount, dueInput, editable) {
    if (!container) return;
    
    container.innerHTML = '';
    
    // 선금 확인 완료 버튼
    const confirmedBtn = document.createElement('button');
    confirmedBtn.type = 'button';
    confirmedBtn.id = 'deposit-confirmed-btn';
    confirmedBtn.className = 'jw-button typeB';
    confirmedBtn.style.backgroundColor = '#4caf50';
    confirmedBtn.style.display = 'block';
    confirmedBtn.disabled = true;
    confirmedBtn.textContent = 'Deposit Confirmed';
    
    // 금액 입력 필드 (read-only)
    const amountInputWrapper = document.createElement('div');
    amountInputWrapper.className = 'payment-amount-input';
    amountInputWrapper.style.display = 'flex';
    amountInputWrapper.style.alignItems = 'center';
    amountInputWrapper.style.gap = '8px';
    
    const amountInput = document.createElement('input');
    amountInput.type = 'text';
    amountInput.id = 'deposit_confirmed_amount';
    amountInput.className = 'form-control';
    amountInput.value = formatPriceNumber(amount);
    amountInput.disabled = true;
    
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn-close';
    clearBtn.id = 'deposit-amount-clear';
    clearBtn.textContent = '×';
    clearBtn.style.display = 'none'; // 완료 상태에서는 숨김
    
    amountInputWrapper.appendChild(amountInput);
    amountInputWrapper.appendChild(clearBtn);
    
    container.appendChild(confirmedBtn);
    container.appendChild(amountInputWrapper);
    
    // 입금 기한 입력 필드 처리
    if (dueInput) {
        dueInput.disabled = true;
    }
}

function setupBalanceConfirm(container, defaultAmount, dueInput, editable) {
    if (!container) return;
    
    container.innerHTML = '';
    
    // 잔금 확인 버튼
    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.id = 'balance-confirm-btn';
    confirmBtn.className = 'jw-button typeB';
    confirmBtn.textContent = 'Confirm Balance';
    confirmBtn.style.display = 'block';
    confirmBtn.addEventListener('click', () => handleBalanceConfirm());
    
    // 금액 입력 필드
    const amountInputWrapper = document.createElement('div');
    amountInputWrapper.className = 'payment-amount-input';
    amountInputWrapper.style.display = editable ? 'flex' : 'none';
    amountInputWrapper.style.alignItems = 'center';
    amountInputWrapper.style.gap = '8px';
    
    const amountInput = document.createElement('input');
    amountInput.type = 'text';
    amountInput.id = 'balance_confirmed_amount';
    amountInput.className = 'form-control';
    amountInput.placeholder = 'Enter amount';
    amountInput.value = formatPriceNumber(defaultAmount);
    if (!editable) amountInput.disabled = true;
    
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn-close';
    clearBtn.id = 'balance-amount-clear';
    clearBtn.textContent = '×';
    clearBtn.addEventListener('click', () => {
        amountInput.value = '';
    });
    
    amountInputWrapper.appendChild(amountInput);
    amountInputWrapper.appendChild(clearBtn);
    
    container.appendChild(confirmBtn);
    container.appendChild(amountInputWrapper);
    
    // 입금 기한 입력 필드 처리
    if (dueInput) {
        dueInput.disabled = !editable;
        if (editable) {
            dueInput.type = 'date';
        }
    }
}

function setupBalanceConfirmed(container, amount, dueInput, editable) {
    if (!container) return;
    
    container.innerHTML = '';
    
    // 잔금 확인 완료 버튼
    const confirmedBtn = document.createElement('button');
    confirmedBtn.type = 'button';
    confirmedBtn.id = 'balance-confirmed-btn';
    confirmedBtn.className = 'jw-button typeB';
    confirmedBtn.style.backgroundColor = '#4caf50';
    confirmedBtn.style.display = 'block';
    confirmedBtn.disabled = true;
    confirmedBtn.textContent = 'Balance Confirmed';
    
    // 금액 입력 필드 (read-only)
    const amountInputWrapper = document.createElement('div');
    amountInputWrapper.className = 'payment-amount-input';
    amountInputWrapper.style.display = 'flex';
    amountInputWrapper.style.alignItems = 'center';
    amountInputWrapper.style.gap = '8px';
    
    const amountInput = document.createElement('input');
    amountInput.type = 'text';
    amountInput.id = 'balance_confirmed_amount';
    amountInput.className = 'form-control';
    amountInput.value = formatPriceNumber(amount);
    amountInput.disabled = true;
    
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn-close';
    clearBtn.id = 'balance-amount-clear';
    clearBtn.textContent = '×';
    clearBtn.style.display = 'none'; // 완료 상태에서는 숨김
    
    amountInputWrapper.appendChild(amountInput);
    amountInputWrapper.appendChild(clearBtn);
    
    container.appendChild(confirmedBtn);
    container.appendChild(amountInputWrapper);
    
    // 입금 기한 입력 필드 처리
    if (dueInput) {
        dueInput.disabled = true;
    }
}

async function handleDepositConfirm() {
    const amountInput = document.getElementById('deposit_confirmed_amount');
    const amount = amountInput ? parseFloat(amountInput.value.replace(/,/g, '')) : 0;
    const dueInput = document.getElementById('deposit_due');
    const dueDate = dueInput ? dueInput.value : null;
    
    if (!amount || amount <= 0) {
        alert('Please enter the amount.');
        return;
    }
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'confirmDeposit',
                bookingId: currentBookingId,
                amount: amount,
                dueDate: dueDate
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Deposit has been confirmed.');
            loadReservationDetail(); // 재로드
        } else {
            alert('Failed to confirm deposit: ' + result.message);
        }
    } catch (error) {
        console.error('Error confirming deposit:', error);
        alert('An error occurred while confirming deposit.');
    }
}

async function handleBalanceConfirm() {
    const amountInput = document.getElementById('balance_confirmed_amount');
    const amount = amountInput ? parseFloat(amountInput.value.replace(/,/g, '')) : 0;
    const dueInput = document.getElementById('balance_due');
    const dueDate = dueInput ? dueInput.value : null;
    
    if (!amount || amount <= 0) {
        alert('Please enter the amount.');
        return;
    }
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'confirmBalance',
                bookingId: currentBookingId,
                amount: amount,
                dueDate: dueDate
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Balance has been confirmed.');
            loadReservationDetail(); // 재로드
        } else {
            alert('Failed to confirm balance: ' + result.message);
        }
    } catch (error) {
        console.error('Error confirming balance:', error);
        alert('An error occurred while confirming balance.');
    }
}

function formatPriceNumber(price) {
    if (!price) return '0';
    return parseInt(price).toLocaleString();
}

async function handleSave() {
    if (!isEditMode) {
        alert('No changes detected.');
        return;
    }
    
    try {
        const statusSelect = document.querySelector('.page-toolbar-actions select');
        const status = statusSelect ? statusSelect.value : null;
        
        const updateData = {
            bookingId: currentBookingId
        };
        
        if (status) {
            updateData.status = status;
        }
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: status ? 'updateReservationStatus' : 'updateReservation',
                ...updateData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Saved successfully.');
            isEditMode = false;
            loadReservationDetail(); // 재로드
        } else {
            alert('Failed to save: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert('An error occurred while saving.');
    }
}

function calculateReturnDate(departureDate, durationDays) {
    const date = new Date(departureDate);
    date.setDate(date.getDate() + durationDays - 1);
    return date.toISOString().split('T')[0];
}

function formatDateTime(datetime) {
    if (!datetime) return '';
    // 서버가 UTC로 저장하므로, UTC로 파싱하기 위해 'Z' 추가
    let dateStr = datetime;
    if (!dateStr.endsWith('Z') && !dateStr.includes('+')) {
        dateStr = dateStr.replace(' ', 'T') + 'Z';
    }
    const date = new Date(dateStr);
    // 필리핀 시간대 (Asia/Manila, UTC+8)로 변환
    const options = {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    };
    const formatter = new Intl.DateTimeFormat('en-CA', options);
    const parts = formatter.formatToParts(date);
    const get = (type) => parts.find(p => p.type === type)?.value || '';
    return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}`;
}

function formatPrice(price) {
    if (!price) return '₱0';
    return '₱' + parseInt(price).toLocaleString();
}

function showLoading() {
    // 로딩 상태 표시
    const tbody = document.querySelector('.booking-detail tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="9" class="is-center">Loading...</td></tr>';
    }
}

function hideLoading() {
    // 로딩은 renderTravelers에서 처리됨
}

function showError(message) {
    alert(message);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    if (!amount && amount !== 0) return '0';
    return new Intl.NumberFormat('ko-KR', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount).replace('PHP', '₱');
}

// 상태별 UI 업데이트 (에이전트 권한 기준)
function computeUiStatusKey(booking) {
    const status = normalizeBookingStatus(booking?.bookingStatus);
    const payment = normalizePaymentStatus(booking?.paymentStatus);
    const hasDepositProof = !!((booking?.downPaymentFile || booking?.depositProofFile) && String(booking?.downPaymentFile || booking?.depositProofFile).trim());

    // 취소/환불/완료는 무조건 잠금
    if (['cancelled', 'canceled', 'completed', 'refunded'].includes(status)) return (status === 'canceled' ? 'cancelled' : status);

    // SMT 수정: 관리자가 bookingStatus 자체를 "Waiting for Deposit/Waiting for Balance Payment" 성격으로 저장한 환경 지원
    if (status === 'pending_deposit' || status === 'waiting_for_deposit' || status === 'waiting_down_payment' || status === 'checking_down_payment') return 'pending_deposit';
    if (status === 'pending_balance' || status === 'waiting_for_balance' || status === 'waiting_for_balance_payment' || status === 'waiting_balance' || status === 'checking_balance') return 'pending_balance';

    // pending/confirmed 상태일 때:
    // - payment.php 정책 변경으로 무통장(입금대기)은 bookingStatus='pending', paymentStatus='pending'로 생성됨
    // - 따라서 에이전트 화면은 pending 상태도 "선금 확인 전/잔금 확인 전" 단계로 분기해야 한다.
    // - 선금 확인 전: downPaymentFile 미업로드
    // - 잔금 확인 전: downPaymentFile 업로드됨 (잔금 증빙 업로드 전/후 모두)
    // (환경 호환: paymentStatus partial도 잔금 대기로 간주)
    if ((status === 'pending' || status === 'confirmed') && (payment === 'pending' || payment === 'partial' || payment === '')) {
        if (!hasDepositProof) return 'pending_deposit';
        return 'pending_balance';
    }
    // status='pending'인데 payment가 다른 값이면 그대로 pending_deposit로 간주(에이전트 UX 일관)
    if (status === 'pending') {
        return hasDepositProof ? 'pending_balance' : 'pending_deposit';
    }
    return status || 'confirmed';
}

function extractFileName(path) {
    if (!path) return '';
    const normalized = String(path).replace(/\\/g, '/');
    const seg = normalized.split('/');
    return seg.pop() || '';
}

function renderProofSection(type, booking, uiKey) {
    // 3단계 결제(staged)는 updatePaymentSections에서 처리하므로 스킵
    const paymentType = booking?.paymentType || 'staged';
    if (paymentType === 'staged') {
        return; // 3단계 결제는 별도 함수에서 처리
    }

    const isDeposit = type === 'deposit';
    const filePathRaw = isDeposit ? (booking.downPaymentFile || booking.depositProofFile || '') : (booking.balanceFile || booking.balanceProofFile || '');
    const filePath = String(filePathRaw || '').trim();
    const hasFile = !!filePath;

    const displayWrap = document.getElementById(isDeposit ? 'deposit_file_display' : 'balance_file_display');
    const uploadWrap = document.getElementById(isDeposit ? 'deposit_file_upload' : 'balance_file_upload');
    const nameEl = document.getElementById(isDeposit ? 'deposit_file_name' : 'balance_file_name');
    const dlBtn = document.getElementById(isDeposit ? 'downloadDepositFileBtn' : 'downloadBalanceFileBtn');
    const delBtn = document.getElementById(isDeposit ? 'deleteDepositFileBtn' : 'deleteBalanceFileBtn');

    if (displayWrap) displayWrap.style.display = 'none';
    if (uploadWrap) uploadWrap.style.display = 'none';
    if (dlBtn) dlBtn.disabled = true;
    if (delBtn) delBtn.disabled = true;

    const allowUpload =
        (uiKey === 'pending_deposit' && isDeposit) ||
        (uiKey === 'pending_balance' && !isDeposit);

    // SMT 수정(검수 요구사항): 잔금 증빙 업로드는 "최고관리자가 잔금 입금 기한 설정" 후에만 가능
    const hasBalanceDue = !!(booking?.balanceDueDate && String(booking.balanceDueDate).trim());
    const canUploadByDeadline = isDeposit ? true : hasBalanceDue;
    // 요구사항(검수): 업로드 후에는 다운로드 + 삭제(또는 다운로드) 가능해야 함.
    // 운영상 교체가 필요하므로 "대기 상태(pending_*)"에서는 파일이 있으면 삭제도 허용한다.
    const allowDelete = (uiKey === 'pending_deposit' || uiKey === 'pending_balance');

    if (!hasFile) {
        if (allowUpload && canUploadByDeadline && uploadWrap) uploadWrap.style.display = 'block';
        return;
    }

    if (displayWrap) displayWrap.style.display = 'flex';
    if (nameEl) nameEl.textContent = extractFileName(filePath);
    if (dlBtn) dlBtn.disabled = false;
    if (delBtn) delBtn.disabled = !allowDelete;
    // 파일명/영역 클릭으로도 다운로드 가능(모바일에서 아이콘이 작거나 가려지는 케이스 대응)
    // 단, 버튼(다운로드/삭제) 클릭은 이 핸들러가 실행되지 않도록 제외한다.
    if (displayWrap) {
        // 중복 바인딩 방지
        if (!displayWrap.dataset.boundRowDownload) {
            displayWrap.addEventListener('click', (e) => {
                const target = e.target;
                if (target && target.closest && target.closest('button')) return;
                downloadProofFile(type);
            });
            displayWrap.dataset.boundRowDownload = '1';
        }
    }
}

function updateUIByStatus(booking) {
    const uiKey = computeUiStatusKey(booking);

    // 에이전트는 상태 변경/저장/취소 불가 → 항상 숨김/비활성
    const statusSelect = document.getElementById('reservationStatusSelect');
    if (statusSelect) {
        statusSelect.value = uiKey;
        statusSelect.disabled = true;
        statusSelect.setAttribute('aria-disabled', 'true');

        // 커스텀 select UI 업데이트 (jw-select)
        if (typeof window.refreshAllJwSelect === 'function') {
            window.refreshAllJwSelect();
        }
    }
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancelReservationBtn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const setDepositDeadlineBtn = document.getElementById('setDepositDeadlineBtn');
    const setBalanceDeadlineBtn = document.getElementById('setBalanceDeadlineBtn');
    if (setDepositDeadlineBtn) setDepositDeadlineBtn.style.display = 'none';
    if (setBalanceDeadlineBtn) setBalanceDeadlineBtn.style.display = 'none';

    // proof UI
    renderProofSection('deposit', booking, uiKey);
    renderProofSection('balance', booking, uiKey);
}

function normalizeBookingStatus(status) {
    if (!status) return '';
    // 새로운 11단계 상태값 지원
    const validStatuses = [
        'waiting_down_payment', 'checking_down_payment',
        'waiting_second_payment', 'checking_second_payment',
        'waiting_balance', 'checking_balance',
        'rejected', 'confirmed', 'completed', 'cancelled', 'refunded'
    ];
    const lower = String(status).trim().toLowerCase();
    if (validStatuses.includes(lower)) return lower;

    // 하위 호환성 매핑
    const statusMap = {
        'pending': 'waiting_down_payment',
        'partial': 'waiting_second_payment',
        'pending_deposit': 'waiting_down_payment',
        'pending_balance': 'waiting_balance',
        '예약 확정': 'confirmed',
        '여행 완료': 'completed',
        '예약 취소': 'cancelled',
        '환불 완료': 'refunded',
        '선금 확인 전': 'waiting_down_payment',
        '잔금 확인 전': 'waiting_balance',
        'waiting for deposit': 'waiting_down_payment',
        'waiting for balance payment': 'waiting_balance',
        'before advance payment confirmation': 'checking_down_payment',
        'before balance payment confirmation': 'checking_balance',
        'reservation confirmed': 'confirmed',
        'trip completed': 'completed',
        'reservation canceled': 'cancelled',
        'reservation cancelled': 'cancelled',
        'refund completed': 'refunded',
        'refund_completed': 'refunded'
    };
    return statusMap[lower] || statusMap[String(status).trim()] || lower;
}

function normalizePaymentStatus(status) {
    if (!status) return '';
    const statusMap = {
        '미결제': 'pending',
        '부분 결제': 'partial',
        '전액 결제': 'paid',
        '선금 확인 전': 'pending',
        '잔금 확인 전': 'partial',
        '선금 확인': 'partial',
        '전액 확인': 'paid'
    };
    return statusMap[status] || status.toLowerCase();
}

// 예약 이력 렌더링
function renderReservationHistory(history) {
    const historyList = document.getElementById('historyList');
    if (!historyList) return;
    
    if (!history || history.length === 0) {
        historyList.innerHTML = '<div class="history-item">No booking history.</div>';
        return;
    }
    
    historyList.innerHTML = history.map(item => `
        <div class="history-item">
            <div class="history-time">${formatDateTime(item.createdAt || item.timestamp)}</div>
            <div class="history-description">${escapeHtml(item.description || item.action || '')}</div>
        </div>
    `).join('');
}

// 기한 설정 모달 열기
let currentDeadlineType = null; // 'down', 'second', or 'balance'

function openDeadlineModal(type) {
    currentDeadlineType = type;
    const modal = document.getElementById('deadlineModal');
    const title = document.getElementById('deadlineModalTitle');
    if (title) {
        const titles = {
            'down': 'Down Payment Deadline',
            'second': 'Second Payment Deadline',
            'balance': 'Balance Deadline',
            'deposit': 'Down Payment Deadline' // legacy support
        };
        title.textContent = titles[type] || 'Payment Deadline';
    }
    if (modal) {
        modal.style.display = 'block';
        // 현재 기한이 있으면 표시
        const inputIds = {
            'down': 'downPaymentDeadline',
            'second': 'secondPaymentDeadline',
            'balance': 'balanceDeadline',
            'deposit': 'downPaymentDeadline' // legacy support
        };
        const currentInput = document.getElementById(inputIds[type]);
        const deadlineDateEl = document.getElementById('deadlineDate');
        const deadlineTimeEl = document.getElementById('deadlineTime');
        // 초기화
        if (deadlineDateEl) deadlineDateEl.value = '';
        if (deadlineTimeEl) deadlineTimeEl.value = '';

        if (currentInput && currentInput.value && currentInput.value !== '-') {
            const dateTime = currentInput.value.split(' ');
            if (dateTime.length >= 1 && deadlineDateEl) {
                deadlineDateEl.value = dateTime[0];
            }
            if (dateTime.length >= 2 && deadlineTimeEl) {
                deadlineTimeEl.value = dateTime[1];
            }
        }
    }
}

// 기한 설정 확인
async function handleSetDeadline() {
    if (!currentDeadlineType) return;
    
    const date = document.getElementById('deadlineDate').value;
    const time = document.getElementById('deadlineTime').value;
    
    if (!date) {
        alert('Please select a date.');
        return;
    }
    
    const deadline = time ? `${date} ${time}` : date;
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'setPaymentDeadline',
                bookingId: currentBookingId,
                type: currentDeadlineType,
                deadline: deadline
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Payment deadline has been set.');
            closeModal('deadlineModal');
            loadReservationDetail();
        } else {
            alert('Failed to set payment deadline: ' + result.message);
        }
    } catch (error) {
        console.error('Error setting deadline:', error);
        alert('An error occurred while setting payment deadline.');
    }
}

// 증빙 파일 업로드 (레거시 - deposit/balance)
async function handleFileUpload(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    // input 초기화(같은 파일 재업로드 가능하게)
    try { event.target.value = ''; } catch (_) {}

    const formData = new FormData();
    formData.append('action', 'uploadProofFile');
    formData.append('bookingId', currentBookingId);
    formData.append('type', type);
    formData.append('file', file);

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('File has been uploaded.');
            loadReservationDetail();
        } else {
            alert('Failed to upload file: ' + result.message);
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('An error occurred while uploading file.');
    }
}

// 3단계 결제 증빙 파일 업로드 (down/second/balance)
async function handlePaymentFileUpload(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    try { event.target.value = ''; } catch (_) {}

    const formData = new FormData();
    formData.append('action', 'uploadPaymentProofFile');
    formData.append('bookingId', currentBookingId);
    formData.append('paymentType', type); // 'down', 'second', 'balance'
    formData.append('file', file);

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('File uploaded successfully.');
            loadReservationDetail();
        } else {
            alert('Failed to upload file: ' + result.message);
        }
    } catch (error) {
        console.error('Error uploading payment file:', error);
        alert('An error occurred while uploading the file.');
    }
}

// 3단계 결제 증빙 파일 다운로드
function downloadPaymentFile(type) {
    const url = `../backend/api/agent-api.php?action=downloadPaymentProofFile&bookingId=${encodeURIComponent(currentBookingId || '')}&paymentType=${encodeURIComponent(type || '')}`;
    window.open(url, '_blank', 'noopener');
}

// 3단계 결제 증빙 파일 삭제
async function deletePaymentFile(type) {
    if (!confirm('Are you sure you want to delete this file?')) return;

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'deletePaymentProofFile',
                bookingId: currentBookingId,
                paymentType: type
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('File deleted successfully.');
            loadReservationDetail();
        } else {
            alert('Failed to delete file: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting payment file:', error);
        alert('An error occurred while deleting the file.');
    }
}

// 증빙 파일 다운로드
function downloadProofFile(type) {
    const url = `../backend/api/agent-api.php?action=downloadProofFile&bookingId=${encodeURIComponent(currentBookingId || '')}&type=${encodeURIComponent(type || '')}`;
    // 다운로드는 새 탭(또는 다운로드 핸들러)로 열어 화면 이탈/히스토리 꼬임을 방지
    window.open(url, '_blank', 'noopener');
}

// 증빙 파일 삭제
async function deleteProofFile(type) {
    if (!confirm('Are you sure you want to delete the proof file?')) return;
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'removeProofFile',
                bookingId: currentBookingId,
                type: type
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('File has been deleted.');
            loadReservationDetail();
        } else {
            alert('Failed to delete file: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting file:', error);
        alert('An error occurred while deleting the file.');
    }
}

// 예약 취소
async function handleCancelReservation() {
    if (!confirm('Are you sure you want to cancel this reservation?')) return;
    
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'cancelReservation',
                bookingId: currentBookingId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Reservation has been cancelled.');
            closeModal('cancelReservationModal');
            loadReservationDetail();
        } else {
            alert('Failed to cancel reservation: ' + result.message);
        }
    } catch (error) {
        console.error('Error cancelling reservation:', error);
        alert('An error occurred while cancelling reservation.');
    }
}

// 모달 열기/닫기
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeModal(modalId, skipReset = false) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }

    // Room Option Modal이 닫힐 때 pendingTravelers 초기화 (취소 시에만)
    if (modalId === 'room-option-modal' && !skipReset) {
        __pendingTravelers = null;
    }
}

// ===== 출발 한달 전까지 수정 기능 =====

// 출발일 한달 전까지인지 확인
function isBeforeOneMonthOfDeparture(departureDate) {
    if (!departureDate) return false;
    const departure = new Date(departureDate);
    const now = new Date();
    // 출발일 한달 전 날짜 계산
    const oneMonthBefore = new Date(departure);
    oneMonthBefore.setMonth(oneMonthBefore.getMonth() - 1);
    const daysUntilDeadline = Math.ceil((oneMonthBefore - now) / (1000 * 60 * 60 * 24));
    console.log(`[Edit Check] Departure: ${departureDate}, One month before: ${oneMonthBefore.toISOString().split('T')[0]}, Days until deadline: ${daysUntilDeadline}`);
    // 현재 날짜가 출발일 한달 전보다 이전이면 수정 가능
    return now < oneMonthBefore;
}

// 수정 가능 여부 저장
let __canEditBeforeDeparture = false;

// 출발 한달 전까지 수정 버튼 표시
function initEditButtonsIfWithin24Hours(booking) {
    const bookingStatus = (booking.bookingStatus || '').toLowerCase();

    // pending_update 상태에서는 수정 불가
    if (bookingStatus === 'pending_update') {
        __canEditBeforeDeparture = false;
        console.log(`[Edit Check] Editing disabled: booking is in pending_update status`);

        // 모든 수정 버튼 숨기기
        const customerEditBtns = document.getElementById('customerEditBtns');
        if (customerEditBtns) customerEditBtns.style.display = 'none';

        const editTravelerBtn = document.getElementById('editTravelerBtn');
        if (editTravelerBtn) editTravelerBtn.style.display = 'none';

        const editProductBtn = document.getElementById('editProductBtn');
        if (editProductBtn) editProductBtn.style.display = 'none';

        const roomOptionBtn = document.getElementById('room_option_btn');
        if (roomOptionBtn) roomOptionBtn.style.display = 'none';

        return;
    }

    const canEdit = isBeforeOneMonthOfDeparture(booking.departureDate);
    __canEditBeforeDeparture = canEdit;
    console.log(`[Edit Check] Can edit (before 1 month of departure): ${canEdit}`);

    // Customer Info 수정 버튼
    const customerEditBtns = document.getElementById('customerEditBtns');
    if (customerEditBtns) {
        customerEditBtns.style.display = canEdit ? 'flex' : 'none';
    }

    // Traveler Info Edit 버튼 (카드 형식 헤더에 표시)
    const editTravelerBtn = document.getElementById('editTravelerBtn');
    if (editTravelerBtn) {
        editTravelerBtn.style.display = canEdit ? 'inline-flex' : 'none';
        editTravelerBtn.onclick = openTravelerEditModal;
    }

    // 이벤트 리스너 등록
    if (canEdit) {
        // Customer Edit 버튼
        const editCustomerBtn = document.getElementById('editCustomerBtn');
        const saveCustomerBtn = document.getElementById('saveCustomerBtn');
        const cancelCustomerBtn = document.getElementById('cancelCustomerBtn');

        if (editCustomerBtn) editCustomerBtn.onclick = startEditCustomer;
        if (saveCustomerBtn) saveCustomerBtn.onclick = saveCustomerInfo;
        if (cancelCustomerBtn) cancelCustomerBtn.onclick = cancelEditCustomer;
    }

    // Edit 버튼 상태 업데이트 (출발 한달 전 체크 적용)
    updateEditButtonsState();
}

// ===== Customer Info 수정 =====

function startEditCustomer() {
    // 현재 값 백업
    originalCustomerData = {
        name: document.getElementById('cust_name')?.value || '',
        email: document.getElementById('cust_email')?.value || '',
        phone: document.getElementById('cust_phone')?.value || ''
    };

    // 입력 필드 활성화
    const custName = document.getElementById('cust_name');
    const custEmail = document.getElementById('cust_email');
    const custPhone = document.getElementById('cust_phone');

    if (custName) custName.disabled = false;
    if (custEmail) custEmail.disabled = false;
    if (custPhone) custPhone.disabled = false;

    // 버튼 상태 변경
    document.getElementById('editCustomerBtn').style.display = 'none';
    document.getElementById('saveCustomerBtn').style.display = 'inline-flex';
    document.getElementById('cancelCustomerBtn').style.display = 'inline-flex';
}

async function saveCustomerInfo() {
    const custName = document.getElementById('cust_name')?.value || '';
    const custEmail = document.getElementById('cust_email')?.value || '';
    const custPhone = document.getElementById('cust_phone')?.value || '';

    // 이름 분리 (First Last 형태로 가정)
    const nameParts = custName.trim().split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'updateCustomerInfo',
                bookingId: currentBookingId,
                firstName: firstName,
                lastName: lastName,
                email: custEmail,
                phone: custPhone
            })
        });

        const result = await response.json();
        if (result.success) {
            alert('Customer information updated successfully.');
            endEditCustomer();
        } else {
            alert('Failed to update: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving customer info:', error);
        alert('Failed to save customer information.');
    }
}

function cancelEditCustomer() {
    // 원래 값 복원
    const custName = document.getElementById('cust_name');
    const custEmail = document.getElementById('cust_email');
    const custPhone = document.getElementById('cust_phone');

    if (custName) custName.value = originalCustomerData.name || '';
    if (custEmail) custEmail.value = originalCustomerData.email || '';
    if (custPhone) custPhone.value = originalCustomerData.phone || '';

    endEditCustomer();
}

function endEditCustomer() {
    // 입력 필드 비활성화
    const custName = document.getElementById('cust_name');
    const custEmail = document.getElementById('cust_email');
    const custPhone = document.getElementById('cust_phone');

    if (custName) custName.disabled = true;
    if (custEmail) custEmail.disabled = true;
    if (custPhone) custPhone.disabled = true;

    // 버튼 상태 복원
    document.getElementById('editCustomerBtn').style.display = 'inline-flex';
    document.getElementById('saveCustomerBtn').style.display = 'none';
    document.getElementById('cancelCustomerBtn').style.display = 'none';
}

// ===== Traveler Info 수정 =====
// (카드 형식으로 변경됨 - 모달 기반 수정은 openTravelerDetail 및 saveTravelerFromModal 함수 사용)

// ===== Product Information 수정 =====
let originalProductData = {};
let selectedProductForEdit = null;
let tripRangePicker = null;

/**
 * Edit Allowed 상태에 따라 Edit 버튼들의 표시 여부를 제어
 * 출발 한달 전까지는 edit_allowed와 상관없이 수정 가능
 */
function updateEditButtonsState() {
    const editProductBtn = document.getElementById('editProductBtn');
    const editTravelerBtn = document.getElementById('editTravelerBtn');

    // 출발 한달 전이거나 Admin이 수정 허용한 경우 - 버튼 활성화
    const canEdit = __canEditBeforeDeparture || isEditAllowed;

    if (canEdit) {
        if (editProductBtn) {
            editProductBtn.style.display = 'inline-flex';
            editProductBtn.disabled = false;
            editProductBtn.style.opacity = '';
            editProductBtn.style.cursor = '';
            editProductBtn.title = '';
        }
        if (editTravelerBtn) {
            editTravelerBtn.style.display = 'inline-flex';
            editTravelerBtn.disabled = false;
            editTravelerBtn.style.opacity = '';
            editTravelerBtn.style.cursor = '';
            editTravelerBtn.title = '';
        }
    } else {
        // 출발 한달 이내이고 Admin 허용도 없는 경우 - 버튼 비활성화
        if (editProductBtn) {
            editProductBtn.disabled = true;
            editProductBtn.style.opacity = '0.5';
            editProductBtn.style.cursor = 'not-allowed';
            editProductBtn.title = 'Edit is only allowed until one month before departure.';
        }
        if (editTravelerBtn) {
            editTravelerBtn.disabled = true;
            editTravelerBtn.style.opacity = '0.5';
            editTravelerBtn.style.cursor = 'not-allowed';
            editTravelerBtn.title = 'Edit is only allowed until one month before departure.';
        }
    }
}

/**
 * Product Edit 버튼 클릭 핸들러
 * 관리자 승인 필요 플로우: 예약을 pending_update로 변경하고 신규 예약 정보 입력 페이지로 이동
 */
async function handleEditProductClick() {
    // 출발 한달 전이거나 Edit Allowed인 경우에만 수정 가능
    const canEdit = __canEditBeforeDeparture || isEditAllowed;
    if (!canEdit) {
        alert('Edit is only allowed until one month before departure.');
        return;
    }

    // 이미 pending_update 상태인지 확인
    if (currentBookingData && currentBookingData.booking && currentBookingData.booking.bookingStatus === 'pending_update') {
        alert('This booking already has a pending change request.');
        return;
    }

    // 확인 다이얼로그 표시 (승인 필요 안내)
    const confirmMessage = 'A change request requires admin approval.\n\nIf approved:\n- The current reservation will be cancelled\n- A new reservation will be created with the updated information\n\nDo you want to continue?';

    if (!confirm(confirmMessage)) {
        return;
    }

    try {
        // requestProductEdit API 호출 (예약을 pending_update로 변경 + change_request 생성)
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'requestProductEdit',
                bookingId: currentBookingId
            })
        });

        const result = await response.json();

        if (result.success) {
            // 신규 예약 페이지로 이동 (edit 모드, bookingId 전달)
            window.location.href = `create-reservation.html?mode=edit&bookingId=${currentBookingId}`;
        } else {
            alert('Failed to start product edit: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error during edit reservation flow:', error);
        alert('An error occurred. Please try again.');
    }
}

function enterProductEditMode() {
    // 출발 한달 전이거나 Edit Allowed인 경우에만 수정 가능
    const canEdit = __canEditBeforeDeparture || isEditAllowed;
    if (!canEdit) {
        alert('Edit is only allowed until one month before departure.');
        return;
    }

    // 현재 값 백업
    originalProductData = {
        packageId: document.getElementById('package_id')?.value || '',
        productName: document.getElementById('product_name')?.value || '',
        tripRange: document.getElementById('trip_range')?.value || '',
        meetTime: document.getElementById('meet_time')?.value || '',
        meetPlace: document.getElementById('meet_place')?.value || ''
    };
    selectedProductForEdit = null;

    // 미팅 시간/장소 필드 활성화
    const meetTime = document.getElementById('meet_time');
    const meetPlace = document.getElementById('meet_place');
    if (meetTime) meetTime.disabled = false;
    if (meetPlace) meetPlace.disabled = false;

    // 상품 검색 버튼 표시
    const productSearchBtn = document.getElementById('product_search_btn');
    if (productSearchBtn) productSearchBtn.style.display = 'inline-flex';

    // 여행 기간 달력 버튼 표시 및 daterangepicker 초기화
    const tripRangeBtn = document.getElementById('trip_range_btn');
    const tripRangeInput = document.getElementById('trip_range');
    if (tripRangeBtn) tripRangeBtn.style.display = 'inline-flex';

    // daterangepicker 초기화
    if (tripRangeInput && typeof $ !== 'undefined') {
        // 기존 값에서 날짜 파싱
        let startDate = moment();
        let endDate = moment().add(4, 'days');
        const currentValue = tripRangeInput.value;
        if (currentValue) {
            const dateMatch = currentValue.match(/(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/);
            if (dateMatch) {
                startDate = moment(dateMatch[1]);
                endDate = moment(dateMatch[2]);
            }
        }

        $(tripRangeInput).daterangepicker({
            startDate: startDate,
            endDate: endDate,
            locale: {
                format: 'YYYY-MM-DD',
                separator: ' - ',
                applyLabel: 'Apply',
                cancelLabel: 'Cancel',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
            },
            opens: 'left'
        });

        tripRangePicker = $(tripRangeInput).data('daterangepicker');
    }

    // 버튼 상태 변경
    document.getElementById('editProductBtn').style.display = 'none';
    document.getElementById('saveProductBtn').style.display = 'inline-flex';
    document.getElementById('cancelProductBtn').style.display = 'inline-flex';

    // 상품 검색 버튼 이벤트
    if (productSearchBtn) {
        productSearchBtn.onclick = openProductSearchModal;
    }
    if (tripRangeBtn) {
        tripRangeBtn.onclick = () => {
            if (tripRangePicker) tripRangePicker.show();
        };
    }
}

function cancelProductEdit() {
    // 원래 값으로 복원
    const packageId = document.getElementById('package_id');
    const productName = document.getElementById('product_name');
    const tripRange = document.getElementById('trip_range');
    const meetTime = document.getElementById('meet_time');
    const meetPlace = document.getElementById('meet_place');

    if (packageId) packageId.value = originalProductData.packageId || '';
    if (productName) productName.value = originalProductData.productName || '';
    if (tripRange) tripRange.value = originalProductData.tripRange || '';
    if (meetTime) meetTime.value = originalProductData.meetTime || '';
    if (meetPlace) meetPlace.value = originalProductData.meetPlace || '';

    selectedProductForEdit = null;
    endProductEditMode();
}

function endProductEditMode() {
    // 입력 필드 비활성화
    const meetTime = document.getElementById('meet_time');
    const meetPlace = document.getElementById('meet_place');

    if (meetTime) meetTime.disabled = true;
    if (meetPlace) meetPlace.disabled = true;

    // 상품 검색 버튼 숨김
    const productSearchBtn = document.getElementById('product_search_btn');
    if (productSearchBtn) productSearchBtn.style.display = 'none';

    // 여행 기간 달력 버튼 숨김
    const tripRangeBtn = document.getElementById('trip_range_btn');
    if (tripRangeBtn) tripRangeBtn.style.display = 'none';

    // daterangepicker 제거
    const tripRangeInput = document.getElementById('trip_range');
    if (tripRangeInput && typeof $ !== 'undefined') {
        try {
            $(tripRangeInput).data('daterangepicker')?.remove();
        } catch (e) {}
    }
    tripRangePicker = null;

    // 버튼 상태 복원
    document.getElementById('editProductBtn').style.display = 'inline-flex';
    document.getElementById('saveProductBtn').style.display = 'none';
    document.getElementById('cancelProductBtn').style.display = 'none';
}

// 상품 검색 모달
function openProductSearchModal() {
    const modal = document.getElementById('product-search-modal');
    if (modal) {
        modal.style.display = 'flex';
        // 검색 입력 초기화
        const searchInput = document.getElementById('product-search-input');
        if (searchInput) searchInput.value = '';
        const results = document.getElementById('product-search-results');
        if (results) results.innerHTML = '<p style="text-align:center; color:#6B7280; padding:20px;">상품명을 입력하고 검색하세요</p>';
    }

    // 검색 버튼 이벤트
    const searchBtn = document.getElementById('product-search-submit');
    if (searchBtn) {
        searchBtn.onclick = searchProducts;
    }

    // 선택 버튼 이벤트
    const confirmBtn = document.getElementById('product-search-confirm');
    if (confirmBtn) {
        confirmBtn.onclick = confirmProductSelection;
    }

    // 엔터키 검색
    const searchInput = document.getElementById('product-search-input');
    if (searchInput) {
        searchInput.onkeyup = (e) => {
            if (e.key === 'Enter') searchProducts();
        };
    }
}

async function searchProducts() {
    const searchInput = document.getElementById('product-search-input');
    const results = document.getElementById('product-search-results');
    const keyword = searchInput?.value?.trim() || '';

    if (!keyword) {
        results.innerHTML = '<p style="text-align:center; color:#6B7280; padding:20px;">검색어를 입력하세요</p>';
        return;
    }

    results.innerHTML = '<p style="text-align:center; color:#6B7280; padding:20px;">검색 중...</p>';

    try {
        const response = await fetch(`../backend/api/agent-api.php?action=searchPackages&keyword=${encodeURIComponent(keyword)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            // (HTML 태그 수정) 디코딩 적용
            results.innerHTML = result.data.map(pkg => `
                <div class="product-item" data-package-id="${pkg.packageId}" onclick="selectProductItem(this, ${pkg.packageId}, '${escapeHtml(decodeHtmlEntities(pkg.packageName) || '')}', ${pkg.duration_days || 5})">
                    <div class="product-item-content">
                        <div class="product-info">
                            <div class="product-name">${escapeHtml(decodeHtmlEntities(pkg.packageName) || '')}</div>
                            <div class="product-days">${pkg.duration_days || 5} Days</div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            results.innerHTML = '<p style="text-align:center; color:#6B7280; padding:20px;">검색 결과가 없습니다</p>';
        }
    } catch (error) {
        console.error('Search error:', error);
        results.innerHTML = '<p style="text-align:center; color:#DC2626; padding:20px;">검색 중 오류가 발생했습니다</p>';
    }
}

function selectProductItem(element, packageId, packageName, durationDays) {
    // 이전 선택 해제
    document.querySelectorAll('#product-search-results .product-item').forEach(el => {
        el.classList.remove('selected');
    });
    // 현재 선택
    element.classList.add('selected');
    selectedProductForEdit = { packageId, packageName, durationDays };
}

function confirmProductSelection() {
    if (!selectedProductForEdit) {
        alert('Please select a product.');
        return;
    }

    // 선택한 상품으로 필드 업데이트
    const productName = document.getElementById('product_name');
    const packageId = document.getElementById('package_id');
    if (productName) productName.value = selectedProductForEdit.packageName;
    if (packageId) packageId.value = selectedProductForEdit.packageId;

    // 모달 닫기
    closeModal('product-search-modal');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// (HTML 태그 수정) HTML 엔티티 디코딩 함수
function decodeHtmlEntities(str) {
    if (!str) return str;
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}

async function saveProductInfo() {
    try {
        const packageId = document.getElementById('package_id')?.value || '';
        const productName = document.getElementById('product_name')?.value || '';
        const tripRange = document.getElementById('trip_range')?.value || '';
        const meetTime = document.getElementById('meet_time')?.value || '';
        const meetPlace = document.getElementById('meet_place')?.value || '';

        // 여행 기간 파싱 (YYYY-MM-DD - YYYY-MM-DD 형식)
        let departureDate = '';
        let returnDate = '';
        if (tripRange) {
            const dateMatch = tripRange.match(/(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/);
            if (dateMatch) {
                departureDate = dateMatch[1];
                returnDate = dateMatch[2];
            }
        }

        const formData = new FormData();
        formData.append('action', 'updateProductInfo');
        formData.append('bookingId', currentBookingId);
        if (packageId) formData.append('packageId', packageId);
        formData.append('packageName', productName);
        formData.append('departureDate', departureDate);
        formData.append('returnDate', returnDate);
        formData.append('meetingTime', meetTime);
        formData.append('meetingPlace', meetPlace);

        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();

        if (result.success) {
            // pending_update 응답 처리
            if (result.data?.status === 'pending_update') {
                alert('Change request submitted. Waiting for admin approval.');
                endProductEditMode();
                await loadReservationDetail();
                return;
            }

            alert('Product information saved.');
            endProductEditMode();
            // 데이터 새로고침
            await loadReservationDetail();
        } else {
            alert('Failed to save: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Save product info error:', error);
        alert('An error occurred while saving.');
    }
}

// ============================================
// Full Payment 렌더링 함수
// ============================================

/**
 * Full Payment 정보 렌더링
 * @param {Object} booking - 예약 정보
 * @param {number} orderAmount - 총 주문 금액
 */
function renderFullPaymentInfo(booking, orderAmount) {
    // Full Payment Amount
    const fullPaymentAmountInput = document.getElementById('fullPaymentAmount');
    const fullPaymentAmount = parseFloat(booking.fullPaymentAmount) || orderAmount || 0;
    if (fullPaymentAmountInput) {
        fullPaymentAmountInput.value = fullPaymentAmount > 0 ? formatPriceNumber(fullPaymentAmount) : '-';
    }

    // Full Payment Deadline
    const fullPaymentDeadlineInput = document.getElementById('fullPaymentDeadline');
    if (fullPaymentDeadlineInput) {
        fullPaymentDeadlineInput.value = booking.fullPaymentDueDate || '-';
    }

    // Full Payment Status
    const fullPaymentStatus = document.getElementById('fullPaymentStatus');
    const fullFileDisplay = document.getElementById('full_file_display');
    const fullFileUpload = document.getElementById('full_file_upload');
    const fullFileName = document.getElementById('full_file_name');
    const fullPaymentRejectionAlert = document.getElementById('fullPaymentRejectionAlert');
    const viewFullBtn = document.getElementById('viewFullFileBtn');
    const downloadFullBtn = document.getElementById('downloadFullFileBtn');

    // 파일 표시
    if (booking.fullPaymentFile) {
        if (fullFileDisplay) fullFileDisplay.style.display = 'flex';
        if (fullFileUpload) fullFileUpload.style.display = 'none';
        if (fullFileName) {
            const fileName = booking.fullPaymentFileName || booking.fullPaymentFile.split('/').pop();
            fullFileName.textContent = fileName;
        }
        if (viewFullBtn) viewFullBtn.disabled = false;
        if (downloadFullBtn) downloadFullBtn.disabled = false;
    } else {
        if (fullFileDisplay) fullFileDisplay.style.display = 'none';
        if (fullFileUpload) fullFileUpload.style.display = 'block';
        if (viewFullBtn) viewFullBtn.disabled = true;
        if (downloadFullBtn) downloadFullBtn.disabled = true;
    }

    // 상태 배지 표시
    if (fullPaymentStatus) {
        if (booking.fullPaymentConfirmedAt) {
            fullPaymentStatus.innerHTML = '<span class="badge badge-success">Confirmed</span>';
            fullPaymentStatus.className = 'payment-status confirmed';
        } else if (booking.fullPaymentFile) {
            fullPaymentStatus.innerHTML = '<span class="badge badge-warning">Pending Confirmation</span>';
            fullPaymentStatus.className = 'payment-status pending';
        } else {
            fullPaymentStatus.innerHTML = '<span class="badge badge-secondary">Not Uploaded</span>';
            fullPaymentStatus.className = 'payment-status not-uploaded';
        }
    }

    // Rejection Alert 표시
    if (booking.fullPaymentRejectionReason && booking.fullPaymentRejectedAt) {
        if (fullPaymentRejectionAlert) {
            fullPaymentRejectionAlert.style.display = 'block';
            const reasonEl = document.getElementById('fullPaymentRejectionReason');
            const dateEl = document.getElementById('fullPaymentRejectedAt');
            if (reasonEl) reasonEl.textContent = booking.fullPaymentRejectionReason;
            if (dateEl) dateEl.textContent = 'Rejected at: ' + formatDateTime(booking.fullPaymentRejectedAt);
        }
    } else {
        if (fullPaymentRejectionAlert) fullPaymentRejectionAlert.style.display = 'none';
    }

    // 파일 업로드/다운로드/삭제 버튼 이벤트 초기화
    initializeFullPaymentFileHandlers(booking.bookingId);
}

/**
 * Full Payment 파일 핸들러 초기화
 * @param {string} bookingId - 예약 ID
 */
function initializeFullPaymentFileHandlers(bookingId) {
    const uploadBtn = document.getElementById('uploadFullFileBtn');
    const fileInput = document.getElementById('full_file_input');
    const downloadBtn = document.getElementById('downloadFullFileBtn');
    const deleteBtn = document.getElementById('deleteFullFileBtn');

    // Upload 버튼 클릭
    if (uploadBtn && fileInput) {
        uploadBtn.onclick = () => fileInput.click();
        fileInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'uploadPaymentProofFile');
            formData.append('bookingId', bookingId);
            formData.append('paymentType', 'full');
            formData.append('file', file);

            try {
                const response = await fetch('../backend/api/agent-api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (result.success) {
                    alert('File uploaded successfully');
                    location.reload();
                } else {
                    alert('Upload failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload error occurred');
            }
        };
    }

    // Download 버튼 클릭
    if (downloadBtn) {
        downloadBtn.onclick = () => {
            window.open(`../backend/api/agent-api.php?action=downloadPaymentProofFile&bookingId=${bookingId}&paymentType=full`, '_blank');
        };
    }

    // Delete 버튼 클릭
    if (deleteBtn) {
        deleteBtn.onclick = async () => {
            if (!confirm('Are you sure you want to delete this file?')) return;

            try {
                const response = await fetch('../backend/api/agent-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'deletePaymentProofFile',
                        bookingId: bookingId,
                        paymentType: 'full'
                    }),
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (result.success) {
                    alert('File deleted successfully');
                    location.reload();
                } else {
                    alert('Delete failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Delete error occurred');
            }
        };
    }
}

// ============================================
// Room Option 선택 기능
// ============================================

// 기본 룸 옵션 데이터
const defaultRoomOptions = [
    { roomId: 'standard', roomType: 'Standard room', capacity: 2, roomPrice: 0 },
    { roomId: 'double', roomType: 'Double room', capacity: 2, roomPrice: 0 },
    { roomId: 'triple', roomType: 'Triple room', capacity: 3, roomPrice: 0 },
    { roomId: 'family', roomType: 'Family room', capacity: 4, roomPrice: 0 },
    { roomId: 'single', roomType: 'Single Supplement Surcharge', capacity: 1, roomPrice: 10000 }
];

let currentRoomOptions = [...defaultRoomOptions];
let selectedRoomsInModal = [];
let roomOptionEditMode = false;

// Room Option 변경 버튼 표시
function showRoomOptionEditButton() {
    const btn = document.getElementById('room_option_btn');
    if (btn) btn.style.display = 'inline-flex';
    roomOptionEditMode = true;
}

// Room Option 변경 버튼 숨기기
function hideRoomOptionEditButton() {
    const btn = document.getElementById('room_option_btn');
    if (btn) btn.style.display = 'none';
    roomOptionEditMode = false;
}

// Room Option 모달 열기
function openRoomOptionModalEdit() {
    // pendingTravelers가 있으면 새로 선택, 없으면 기존 선택 유지
    if (__pendingTravelers && __pendingTravelers.length > 0) {
        // 새 여행자 정보로 시작 - 룸 옵션 초기화
        selectedRoomsInModal = [];
    } else {
        // 현재 선택된 룸 옵션 복사
        selectedRoomsInModal = [];
        if (window.currentSelectedRooms) {
            if (Array.isArray(window.currentSelectedRooms)) {
                selectedRoomsInModal = window.currentSelectedRooms.map(r => ({...r}));
            } else {
                selectedRoomsInModal = Object.values(window.currentSelectedRooms).map(r => ({...r}));
            }
        }
    }

    // display: flex로 설정 (중앙 정렬)
    const modal = document.getElementById('room-option-modal');
    if (modal) modal.style.display = 'flex';

    loadRoomOptionsEdit();
    updateRoomCombinationBanner();
    updateOrderSummary();
}

// 룸 옵션 목록 로드
async function loadRoomOptionsEdit() {
    const listEl = document.getElementById('room-option-list');
    if (!listEl) return;

    // 패키지 ID가 있으면 API에서 룸 옵션 가져오기
    const packageId = document.getElementById('package_id')?.value;
    if (packageId) {
        try {
            const response = await fetch(`../backend/api/agent-api.php?action=getPackageRoomOptions&packageId=${packageId}`);
            const result = await response.json();
            if (result.success && result.data && result.data.length > 0) {
                currentRoomOptions = result.data;
            } else {
                currentRoomOptions = [...defaultRoomOptions];
            }
        } catch (e) {
            currentRoomOptions = [...defaultRoomOptions];
        }
    } else {
        currentRoomOptions = [...defaultRoomOptions];
    }

    renderRoomOptionsEdit();
}

// 룸 옵션 렌더링
function renderRoomOptionsEdit() {
    const listEl = document.getElementById('room-option-list');
    if (!listEl) return;

    let html = '';
    currentRoomOptions.forEach(room => {
        const existingRoom = selectedRoomsInModal.find(r => r.roomId === room.roomId || r.roomType === room.roomType);
        const count = existingRoom ? (existingRoom.count || existingRoom.quantity || 0) : 0;
        const displayPrice = room.roomPrice > 0 ? `+₱${Number(room.roomPrice).toLocaleString()}` : 'Included';
        const isSelected = count > 0;

        html += `
            <div class="room-option-card ${isSelected ? 'selected' : ''}" data-room-id="${room.roomId}">
                <div class="room-option-card-row">
                    <span class="room-option-card-name">${room.roomType}</span>
                    <span class="room-option-card-capacity">${room.capacity} guests</span>
                    <span class="room-option-card-price ${room.roomPrice > 0 ? 'has-price' : ''}">${displayPrice}</span>
                    <div class="room-option-card-controls">
                        <button type="button" class="room-qty-btn" onclick="changeRoomQtyEdit('${room.roomId}', -1)" ${count === 0 ? 'disabled' : ''}>-</button>
                        <span class="room-qty-value ${count > 0 ? 'has-value' : ''}" id="room-qty-${room.roomId}">${count}</span>
                        <button type="button" class="room-qty-btn" onclick="changeRoomQtyEdit('${room.roomId}', 1)">+</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

// 룸 수량 변경
function changeRoomQtyEdit(roomId, delta) {
    const room = currentRoomOptions.find(r => r.roomId === roomId);
    if (!room) return;

    const existingIndex = selectedRoomsInModal.findIndex(r => r.roomId === roomId);
    const currentCount = existingIndex >= 0 ? selectedRoomsInModal[existingIndex].count : 0;
    const newCount = Math.max(0, currentCount + delta);

    if (newCount === 0) {
        selectedRoomsInModal = selectedRoomsInModal.filter(r => r.roomId !== roomId);
    } else {
        if (existingIndex >= 0) {
            selectedRoomsInModal[existingIndex].count = newCount;
        } else {
            selectedRoomsInModal.push({
                roomId: room.roomId,
                roomType: room.roomType,
                capacity: room.capacity,
                roomPrice: room.roomPrice,
                count: newCount
            });
        }
    }

    // UI 업데이트
    const qtyEl = document.getElementById(`room-qty-${roomId}`);
    if (qtyEl) {
        qtyEl.textContent = newCount;
        if (newCount > 0) {
            qtyEl.classList.add('has-value');
        } else {
            qtyEl.classList.remove('has-value');
        }
    }

    // 카드 selected 클래스 업데이트
    const card = document.querySelector(`.room-option-card[data-room-id="${roomId}"]`);
    if (card) {
        if (newCount > 0) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }

    // - 버튼 disabled 상태 업데이트
    const minusBtn = card?.querySelector('.room-qty-btn');
    if (minusBtn) {
        minusBtn.disabled = newCount === 0;
    }

    updateRoomCombinationBanner();
    updateOrderSummary();
}

// 총 예약 인원 계산 (Traveler 정보 기반)
function getTotalBookingGuests() {
    // pendingTravelers가 있으면 그것을 사용 (단계별 진행 중)
    const travelers = (__pendingTravelers && __pendingTravelers.length > 0)
        ? __pendingTravelers
        : (window.currentTravelers || []);

    // traveler 정보에서 Adult + Child(childRoom=Yes) 합계
    let total = 0;
    if (Array.isArray(travelers)) {
        travelers.forEach(t => {
            const type = (t.travelerType || '').toLowerCase();
            if (type === 'adult') {
                total++;
            } else if (type === 'child') {
                const childRoom = t.childRoom || t.child_room || '';
                if (childRoom === 'Yes' || childRoom === 'yes' || childRoom === true) {
                    total++;
                }
            }
        });
    }
    return total || 1;
}

// Room Combination 배너 업데이트
function updateRoomCombinationBanner() {
    const countEl = document.getElementById('room-combination-count');
    if (!countEl) return;

    const totalGuests = getTotalBookingGuests();
    let totalCapacity = 0;
    selectedRoomsInModal.forEach(room => {
        totalCapacity += (room.capacity || 0) * (room.count || 0);
    });

    countEl.textContent = `(${totalCapacity}/${totalGuests} pax)`;

    // 버튼 활성화/비활성화 (create-reservation과 동일: 정확히 일치해야만 활성화)
    const confirmBtn = document.getElementById('confirm-room-selection-btn');
    if (confirmBtn) {
        // 객실 미선택 또는 인원 불일치 시 비활성화
        if (totalCapacity === 0 || totalGuests === 0) {
            confirmBtn.disabled = true;
        } else if (totalCapacity === totalGuests) {
            // 정확히 일치할 때만 활성화
            confirmBtn.disabled = false;
        } else {
            // 부족하거나 초과하면 비활성화
            confirmBtn.disabled = true;
        }
    }
}

// 주문 요약 업데이트
function updateOrderSummary() {
    const summaryEl = document.getElementById('order-summary-list');
    const amountEl = document.getElementById('order-amount-value');
    if (!summaryEl) return;

    let totalAmount = 0;
    let summaryHtml = '';
    const booking = currentBookingData?.booking || {};

    // pendingTravelers가 있으면 사용, 아니면 currentTravelers 사용
    const travelers = (__pendingTravelers && __pendingTravelers.length > 0)
        ? __pendingTravelers
        : (window.currentTravelers || []);

    // 1. 여행자별 가격 계산
    const adultPrice = parseFloat(booking.adultPrice || booking.packagePrice || 0);
    const childWithRoomPrice = adultPrice; // 성인가격 100%
    const childNoRoomPrice = parseFloat(booking.childPrice || 0) || (adultPrice * 0.8); // 80%
    const infantPrice = parseFloat(booking.infantPrice || 0) || 10000;

    let adults = 0, childrenWithRoom = 0, childrenNoRoom = 0, infants = 0;
    travelers.forEach(t => {
        const type = (t.travelerType || t.type || '').toLowerCase();
        if (type === 'adult') adults++;
        else if (type === 'child') {
            const hasRoom = t.childRoom === true || t.childRoom === 'yes' || t.childRoom === 'Yes' || parseInt(t.childRoom || 0) === 1;
            if (hasRoom) childrenWithRoom++;
            else childrenNoRoom++;
        }
        else if (type === 'infant') infants++;
    });

    // 여행자 가격 섹션
    summaryHtml += '<div class="order-summary-section-title">Travelers</div>';
    if (adults > 0) {
        const adultTotal = adults * adultPrice;
        totalAmount += adultTotal;
        summaryHtml += `<div class="order-summary-item"><span>Adult x${adults}</span><span>₱${adultTotal.toLocaleString()}</span></div>`;
    }
    if (childrenWithRoom > 0) {
        const childRoomTotal = childrenWithRoom * childWithRoomPrice;
        totalAmount += childRoomTotal;
        summaryHtml += `<div class="order-summary-item"><span>Child (Room) x${childrenWithRoom}</span><span>₱${childRoomTotal.toLocaleString()}</span></div>`;
    }
    if (childrenNoRoom > 0) {
        const childNoRoomTotal = childrenNoRoom * childNoRoomPrice;
        totalAmount += childNoRoomTotal;
        summaryHtml += `<div class="order-summary-item"><span>Child x${childrenNoRoom}</span><span>₱${Math.round(childNoRoomTotal).toLocaleString()}</span></div>`;
    }
    if (infants > 0) {
        const infantTotal = infants * infantPrice;
        totalAmount += infantTotal;
        summaryHtml += `<div class="order-summary-item"><span>Infant x${infants}</span><span>₱${infantTotal.toLocaleString()}</span></div>`;
    }

    // 2. 룸 옵션 가격 섹션
    const roomItems = selectedRoomsInModal.filter(r => r.count > 0);
    if (roomItems.length > 0) {
        summaryHtml += '<div class="order-summary-section-title" style="margin-top:12px;">Room Options</div>';
        roomItems.forEach(room => {
            const roomTotal = (room.roomPrice || 0) * room.count;
            totalAmount += roomTotal;
            summaryHtml += `<div class="order-summary-item"><span>${room.roomType} x${room.count}</span><span>${roomTotal > 0 ? '₱' + roomTotal.toLocaleString() : 'Free'}</span></div>`;
        });
    }

    // 3. Visa Fee 계산
    let groupVisaCount = 0, individualVisaCount = 0;
    travelers.forEach(t => {
        const visaType = String(t.visaType || '').toLowerCase();
        if (visaType === 'group') groupVisaCount++;
        else if (visaType === 'individual') individualVisaCount++;
    });
    if (groupVisaCount > 0 || individualVisaCount > 0) {
        summaryHtml += '<div class="order-summary-section-title" style="margin-top:12px;">Visa Fee</div>';
        if (groupVisaCount > 0) {
            const groupTotal = groupVisaCount * 1500;
            totalAmount += groupTotal;
            summaryHtml += `<div class="order-summary-item"><span>Group Visa x${groupVisaCount}</span><span>₱${groupTotal.toLocaleString()}</span></div>`;
        }
        if (individualVisaCount > 0) {
            const indTotal = individualVisaCount * 1900;
            totalAmount += indTotal;
            summaryHtml += `<div class="order-summary-item"><span>Individual Visa x${individualVisaCount}</span><span>₱${indTotal.toLocaleString()}</span></div>`;
        }
    }

    // 4. Flight Options 계산
    let flightOptionsTotal = 0;
    travelers.forEach(t => {
        if (t.flightOptionPrices && typeof t.flightOptionPrices === 'object') {
            Object.values(t.flightOptionPrices).forEach(price => {
                flightOptionsTotal += Number(price) || 0;
            });
        }
    });
    if (flightOptionsTotal > 0) {
        summaryHtml += '<div class="order-summary-section-title" style="margin-top:12px;">Flight Options</div>';
        summaryHtml += `<div class="order-summary-item"><span>Flight Options</span><span>₱${flightOptionsTotal.toLocaleString()}</span></div>`;
        totalAmount += flightOptionsTotal;
    }

    if (summaryEl) {
        summaryEl.innerHTML = summaryHtml || '<div style="color:#999; text-align:center;">No items</div>';
    }
    if (amountEl) {
        amountEl.textContent = `₱${totalAmount.toLocaleString()}`;
    }
}

// 룸 옵션 선택 확인 → Summary Modal 표시
async function confirmRoomSelectionEdit() {
    // pendingTravelers가 있으면 그 인원 기준, 없으면 기존 인원 기준
    // Adult + Child(childRoom=Yes)만 카운트 (create-reservation과 동일)
    let totalGuests = getTotalBookingGuests();

    let totalCapacity = 0;
    selectedRoomsInModal.forEach(room => {
        totalCapacity += (room.capacity || 0) * (room.count || 0);
    });

    if (totalCapacity === 0) {
        alert('Please select rooms.');
        return;
    }

    if (totalCapacity < totalGuests) {
        alert(`Room capacity (${totalCapacity}) is less than total guests (${totalGuests}).`);
        return;
    }

    // 수용 인원이 예약 인원보다 많은 경우 (create-reservation과 동일)
    if (totalCapacity > totalGuests) {
        alert(`The number of people does not match the room capacity. Selected capacity: ${totalCapacity}, Required: ${totalGuests}.`);
        return;
    }

    // Room Option Modal 닫고 Summary Modal 열기 (skipReset=true로 pendingTravelers 유지)
    closeModal('room-option-modal', true);
    showChangeSummaryModal();
}

// Change Summary Modal 표시
function showChangeSummaryModal() {
    const modal = document.getElementById('change-summary-modal');
    if (!modal) return;

    const booking = currentBookingData?.booking || {};
    const beforeTravelers = window.currentTravelers || [];
    const afterTravelers = (__pendingTravelers && __pendingTravelers.length > 0) ? __pendingTravelers : beforeTravelers;
    const beforeRooms = window.currentSelectedRooms || [];
    const afterRooms = selectedRoomsInModal.filter(r => r.count > 0);

    // 디버깅 로그
    console.log('=== showChangeSummaryModal Debug ===');
    console.log('__pendingTravelers:', __pendingTravelers);
    console.log('afterTravelers:', afterTravelers);
    afterTravelers.forEach((t, i) => {
        console.log(`Traveler ${i}:`, { travelerType: t.travelerType, visaType: t.visaType, childRoom: t.childRoom, flightOptions: t.flightOptions, flightOptionPrices: t.flightOptionPrices });
    });

    // Before Travelers
    document.getElementById('summary-before-travelers').textContent = `${beforeTravelers.length} person(s)`;
    let beforeTravelerHtml = '';
    beforeTravelers.forEach(t => {
        const name = `${t.firstName || ''} ${t.lastName || ''}`.trim() || 'Unknown';
        const type = (t.travelerType || 'adult').charAt(0).toUpperCase() + (t.travelerType || 'adult').slice(1);
        beforeTravelerHtml += `<div style="padding: 6px 10px; background: #F9FAFB; border-radius: 4px; margin-bottom: 4px;">${name} (${type})</div>`;
    });
    document.getElementById('summary-before-traveler-list').innerHTML = beforeTravelerHtml || '<div style="color:#999;">-</div>';

    // After Travelers
    document.getElementById('summary-after-travelers').textContent = `${afterTravelers.length} person(s)`;
    let afterTravelerHtml = '';
    afterTravelers.forEach(t => {
        const name = `${t.firstName || ''} ${t.lastName || ''}`.trim() || 'Unknown';
        const type = (t.travelerType || t.type || 'adult').charAt(0).toUpperCase() + (t.travelerType || t.type || 'adult').slice(1);
        afterTravelerHtml += `<div style="padding: 6px 10px; background: #ECFDF5; border-radius: 4px; margin-bottom: 4px;">${name} (${type})</div>`;
    });
    document.getElementById('summary-after-traveler-list').innerHTML = afterTravelerHtml || '<div style="color:#999;">-</div>';

    // Before Rooms
    const beforeRoomCount = beforeRooms.reduce((sum, r) => sum + (r.count || 0), 0);
    document.getElementById('summary-before-rooms').textContent = `${beforeRoomCount} room(s)`;
    let beforeRoomHtml = '';
    beforeRooms.forEach(r => {
        if (r.count > 0) {
            beforeRoomHtml += `<div style="padding: 6px 10px; background: #F9FAFB; border-radius: 4px; margin-bottom: 4px;">${r.roomType} x${r.count}</div>`;
        }
    });
    document.getElementById('summary-before-room-list').innerHTML = beforeRoomHtml || '<div style="color:#999;">-</div>';

    // After Rooms
    const afterRoomCount = afterRooms.reduce((sum, r) => sum + (r.count || 0), 0);
    document.getElementById('summary-after-rooms').textContent = `${afterRoomCount} room(s)`;
    let afterRoomHtml = '';
    afterRooms.forEach(r => {
        if (r.count > 0) {
            afterRoomHtml += `<div style="padding: 6px 10px; background: #ECFDF5; border-radius: 4px; margin-bottom: 4px;">${r.roomType} x${r.count}</div>`;
        }
    });
    document.getElementById('summary-after-room-list').innerHTML = afterRoomHtml || '<div style="color:#999;">-</div>';

    // Amount Breakdown
    // Sale 할인 정보 (예약에 저장된 할인 정보)
    const saleDiscountAmount = parseFloat(booking.saleDiscountAmount || 0);
    const saleName = booking.saleName || '';

    // 가격 정보 (이미 할인 적용된 가격)
    const adultPrice = parseFloat(booking.adultPrice || booking.packagePrice || 0);
    const childWithRoomPrice = adultPrice;
    const childNoRoomPrice = parseFloat(booking.childPrice || 0) || (adultPrice * 0.8);
    const infantPrice = parseFloat(booking.infantPrice || 0) || 10000;

    // 할인 전 원래 가격 계산
    const originalAdultPrice = saleDiscountAmount > 0 ? (adultPrice + saleDiscountAmount) : adultPrice;
    const originalChildWithRoomPrice = originalAdultPrice;

    let adults = 0, childrenWithRoom = 0, childrenNoRoom = 0, infants = 0;
    afterTravelers.forEach(t => {
        const type = (t.travelerType || t.type || '').toLowerCase();
        console.log('Processing traveler type:', type, 'visaType:', t.visaType);
        if (type === 'adult') adults++;
        else if (type === 'child') {
            const hasRoom = t.childRoom === true || t.childRoom === 'yes' || t.childRoom === 'Yes' || parseInt(t.childRoom || 0) === 1;
            if (hasRoom) childrenWithRoom++;
            else childrenNoRoom++;
        }
        else if (type === 'infant') infants++;
    });

    // 할인 적용 대상 인원 (Adult + Child with Room)
    const discountableCount = adults + childrenWithRoom;

    console.log('Breakdown counts:', { adults, childrenWithRoom, childrenNoRoom, infants, discountableCount });

    let breakdownHtml = '';
    let subtotal = 0;

    // Package Price 표시 (할인 전 가격 기준)
    if (adults > 0) {
        const amt = adults * originalAdultPrice;
        subtotal += amt;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Adult x${adults}</span><span>₱${amt.toLocaleString()}</span></div>`;
    }
    if (childrenWithRoom > 0) {
        const amt = childrenWithRoom * originalChildWithRoomPrice;
        subtotal += amt;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Child (with room) x${childrenWithRoom}</span><span>₱${amt.toLocaleString()}</span></div>`;
    }
    if (childrenNoRoom > 0) {
        const amt = childrenNoRoom * childNoRoomPrice;
        subtotal += amt;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Child (no room) x${childrenNoRoom}</span><span>₱${amt.toLocaleString()}</span></div>`;
    }
    if (infants > 0) {
        const amt = infants * infantPrice;
        subtotal += amt;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Infant x${infants}</span><span>₱${amt.toLocaleString()}</span></div>`;
    }

    // Room prices
    afterRooms.forEach(r => {
        if (r.count > 0 && r.roomPrice > 0) {
            const amt = r.count * r.roomPrice;
            subtotal += amt;
            breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>${r.roomType} x${r.count}</span><span>₱${amt.toLocaleString()}</span></div>`;
        }
    });

    // Visa Fee 계산
    let groupVisaCount = 0, individualVisaCount = 0;
    afterTravelers.forEach(t => {
        const visaType = String(t.visaType || '').toLowerCase();
        console.log('Visa check - visaType:', visaType);
        if (visaType === 'group') groupVisaCount++;
        else if (visaType === 'individual') individualVisaCount++;
    });
    console.log('Visa counts:', { groupVisaCount, individualVisaCount });
    if (groupVisaCount > 0) {
        const groupTotal = groupVisaCount * 1500;
        subtotal += groupTotal;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Group Visa x${groupVisaCount}</span><span>₱${groupTotal.toLocaleString()}</span></div>`;
    }
    if (individualVisaCount > 0) {
        const indTotal = individualVisaCount * 1900;
        subtotal += indTotal;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Individual Visa x${individualVisaCount}</span><span>₱${indTotal.toLocaleString()}</span></div>`;
    }

    // Flight Options 계산
    let flightOptionsTotal = 0;
    afterTravelers.forEach(t => {
        console.log('Flight options check:', t.flightOptionPrices);
        if (t.flightOptionPrices && typeof t.flightOptionPrices === 'object') {
            Object.values(t.flightOptionPrices).forEach(price => {
                flightOptionsTotal += Number(price) || 0;
            });
        }
    });
    console.log('Flight options total:', flightOptionsTotal);
    if (flightOptionsTotal > 0) {
        subtotal += flightOptionsTotal;
        breakdownHtml += `<div style="display:flex; justify-content:space-between; padding: 6px 0;"><span>Flight Options</span><span>₱${flightOptionsTotal.toLocaleString()}</span></div>`;
    }

    // Sale 할인 금액 계산 (인원당 할인액 × 할인 대상 인원수)
    const totalDiscount = saleDiscountAmount * discountableCount;

    // 할인 정보 표시
    if (totalDiscount > 0 && saleName) {
        breakdownHtml += `
            <div style="display:flex; justify-content:space-between; padding: 8px 12px; margin-top: 8px; background: #FEF2F2; border-radius: 6px; border: 1px solid #FECACA;">
                <span style="color: #DC2626; font-weight: 600;">
                    <span style="background: #DC2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 8px;">SALE</span>
                    ${saleName}
                </span>
                <span style="color: #DC2626; font-weight: 600;">-₱${totalDiscount.toLocaleString()}</span>
            </div>
        `;
    }

    // 최종 총액 계산 (할인 차감)
    const totalAmount = subtotal - totalDiscount;

    document.getElementById('summary-breakdown-list').innerHTML = breakdownHtml || '<div style="color:#999;">-</div>';
    document.getElementById('summary-total-amount').textContent = `₱${totalAmount.toLocaleString()}`;

    modal.style.display = 'flex';
}

// Summary Modal에서 Back 버튼 클릭 → Room Option Modal로 돌아가기
function backToRoomOption() {
    closeModal('change-summary-modal');
    const modal = document.getElementById('room-option-modal');
    if (modal) modal.style.display = 'flex';
}

// Summary Modal 닫기
function closeChangeSummaryModal() {
    closeModal('change-summary-modal');
    __pendingTravelers = null;
}

// 최종 변경 사항 제출
async function submitChanges() {
    const submitBtn = document.getElementById('submit-changes-btn');
    if (submitBtn) submitBtn.disabled = true;

    try {
        // Step 1: Travelers 저장 (pendingTravelers가 있는 경우)
        if (__pendingTravelers && __pendingTravelers.length > 0) {
            const travelerResponse = await fetch('../backend/api/agent-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'updateTravelerInfo',
                    bookingId: currentBookingId,
                    travelers: __pendingTravelers
                })
            });

            const travelerResult = await travelerResponse.json();
            if (!travelerResult.success) {
                // pending_update 응답 처리
                if (travelerResult.data?.status === 'pending_update') {
                    alert('Change request submitted. Waiting for admin approval.');
                    __pendingTravelers = null;
                    closeModal('change-summary-modal');
                    // 리스트 페이지로 리다이렉션
                    window.location.href = 'reservation-list.html';
                    return;
                }
                alert('Failed to update travelers: ' + (travelerResult.message || 'Unknown error'));
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            // pending 초기화
            __pendingTravelers = null;
        }

        // Step 2: Room Options 저장
        const formData = new FormData();
        formData.append('action', 'updateRoomOptions');
        formData.append('bookingId', currentBookingId);
        formData.append('selectedRooms', JSON.stringify(selectedRoomsInModal.filter(r => r.count > 0)));

        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success) {
            alert('Changes saved successfully.');
            closeModal('change-summary-modal');
            // 리스트 페이지로 리다이렉션
            window.location.href = 'reservation-list.html';
        } else {
            alert('Failed to update: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating:', error);
        alert('Error saving changes.');
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}

// 전역 함수로 노출
window.showChangeSummaryModal = showChangeSummaryModal;
window.backToRoomOption = backToRoomOption;
window.closeChangeSummaryModal = closeChangeSummaryModal;
window.submitChanges = submitChanges;

// Room Option 버튼 이벤트 등록
document.addEventListener('DOMContentLoaded', function() {
    const roomOptionBtn = document.getElementById('room_option_btn');
    if (roomOptionBtn) {
        roomOptionBtn.addEventListener('click', openRoomOptionModalEdit);
    }

    // Room Option 모달 확인 버튼
    const confirmBtn = document.getElementById('confirm-room-selection-btn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmRoomSelectionEdit);
    }

    // Room Option 모달 바깥 클릭 시 닫기
    document.getElementById('room-option-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal('room-option-modal');
        }
    });
});

// 전역 함수로 노출
window.openRoomOptionModalEdit = openRoomOptionModalEdit;
window.changeRoomQtyEdit = changeRoomQtyEdit;
window.confirmRoomSelectionEdit = confirmRoomSelectionEdit;
window.showRoomOptionEditButton = showRoomOptionEditButton;
window.hideRoomOptionEditButton = hideRoomOptionEditButton;

// pendingTravelers 기준으로 총 금액 계산 (pending_update 시 예상 금액 표시용)
function calculateTotalFromTravelersAgent(booking, travelers) {
    if (!Array.isArray(travelers) || travelers.length === 0) return 0;

    // Sale 할인 정보
    const saleDiscountAmount = parseFloat(booking.saleDiscountAmount || 0);

    // DB에 저장된 단가 사용
    const adultPrice = parseFloat(booking.adultPrice || booking.packagePrice || 0);
    const childPrice = parseFloat(booking.childPrice || 0) || (adultPrice * 0.8);
    const infantPrice = parseFloat(booking.infantPrice || 0) || 10000;

    // 할인 전 원래 가격
    const originalAdultPrice = saleDiscountAmount > 0 ? (adultPrice + saleDiscountAmount) : adultPrice;

    // 인원 수 계산
    let adults = 0, childrenWithRoom = 0, childrenNoRoom = 0, infants = 0;
    let visaFeeTotal = 0;
    let flightOptionsTotal = 0;

    travelers.forEach(t => {
        const type = (t.type || t.travelerType || '').toLowerCase();
        if (type === 'adult') {
            adults++;
        } else if (type === 'child') {
            const hasRoom = parseInt(t.childRoom || 0) === 1;
            if (hasRoom) childrenWithRoom++;
            else childrenNoRoom++;
        } else if (type === 'infant') {
            infants++;
        }

        // Visa fee 계산 (group visa만)
        const visaType = (t.visaType || '').toLowerCase();
        const visaRequired = t.visaRequired === true || t.visaRequired === 'true' || t.visaRequired === 1;
        if (visaRequired && visaType === 'group') {
            visaFeeTotal += 1500; // Group visa fee per person
        }

        // Flight options 계산
        if (t.flightOptionPrices && typeof t.flightOptionPrices === 'object') {
            Object.values(t.flightOptionPrices).forEach(price => {
                flightOptionsTotal += Number(price) || 0;
            });
        }
    });

    // Package price 계산
    let packageTotal = 0;
    packageTotal += adults * originalAdultPrice;
    packageTotal += childrenWithRoom * originalAdultPrice;
    packageTotal += childrenNoRoom * childPrice;
    packageTotal += infants * infantPrice;

    // 할인 대상 인원
    const discountableCount = adults + childrenWithRoom;
    const totalDiscount = saleDiscountAmount * discountableCount;

    // 총액 계산
    const total = packageTotal - totalDiscount + visaFeeTotal + flightOptionsTotal;
    return total;
}

// =============================================
// 파일 뷰어 기능
// =============================================

// 파일이 이미지인지 확인
function isImageFile(filePath) {
    if (!filePath) return false;
    const ext = filePath.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
}

// 파일이 PDF인지 확인
function isPdfFile(filePath) {
    if (!filePath) return false;
    const ext = filePath.split('.').pop().toLowerCase();
    return ext === 'pdf';
}

// 파일 URL 정규화
function getFileUrl(filePath) {
    if (!filePath) return '';
    // 이미 절대 경로면 그대로 반환
    if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
        return filePath;
    }
    // /uploads로 시작하면 앞에 경로 추가
    if (filePath.startsWith('/uploads/')) {
        return filePath;
    }
    // uploads/로 시작하면 /를 추가
    if (filePath.startsWith('uploads/')) {
        return '/' + filePath;
    }
    // 그 외의 경우 /uploads/ 추가
    return '/uploads/' + filePath;
}

// 결제 증빙 파일 뷰어 열기
function viewPaymentFile(type) {
    const booking = currentBookingData?.booking;
    if (!booking) {
        alert('Booking data not loaded');
        return;
    }

    let filePath = '';
    let fileName = '';
    let title = '';

    switch (type) {
        case 'down':
            filePath = booking.downPaymentFile || '';
            fileName = booking.downPaymentFileName || extractFileName(filePath);
            title = 'Down Payment Proof';
            break;
        case 'second':
            filePath = booking.advancePaymentFile || '';
            fileName = booking.advancePaymentFileName || extractFileName(filePath);
            title = 'Second Payment Proof';
            break;
        case 'balance':
            filePath = booking.balanceFile || '';
            fileName = booking.balanceFileName || extractFileName(filePath);
            title = 'Balance Payment Proof';
            break;
        case 'full':
            filePath = booking.fullPaymentFile || '';
            fileName = booking.fullPaymentFileName || extractFileName(filePath);
            title = 'Full Payment Proof';
            break;
        default:
            alert('Unknown payment type');
            return;
    }

    if (!filePath) {
        alert('No file uploaded for this payment');
        return;
    }

    openFileViewer(filePath, `${title} - ${fileName}`);
}

// 파일 뷰어 모달 열기
function openFileViewer(filePath, title) {
    const modal = document.getElementById('file-viewer-modal');
    const titleEl = document.getElementById('file-viewer-title');
    const content = document.getElementById('file-viewer-content');

    if (!modal || !content) {
        alert('File viewer not available');
        return;
    }

    const fileUrl = getFileUrl(filePath);

    if (titleEl) {
        titleEl.textContent = title || 'File Viewer';
    }

    // 파일 타입에 따라 다르게 표시
    if (isImageFile(filePath)) {
        content.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <img src="${fileUrl}" alt="Payment Proof"
                     style="max-width: 100%; max-height: 70vh; object-fit: contain; border-radius: 8px;"
                     onerror="this.parentElement.innerHTML='<div style=\\'color:#f44336;padding:40px;\\'>Failed to load image.<br><br>File may have been moved or deleted.<br>Path: ${fileUrl}</div>'"
                />
            </div>
        `;
    } else if (isPdfFile(filePath)) {
        content.innerHTML = `
            <div style="width: 100%; height: 70vh;">
                <iframe src="${fileUrl}"
                        style="width: 100%; height: 100%; border: none; border-radius: 8px;"
                        title="PDF Viewer"
                ></iframe>
            </div>
        `;
    } else {
        // 기타 파일은 다운로드 링크 표시
        const fileName = filePath.split('/').pop() || 'file';
        content.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <p style="color: #666; margin-bottom: 20px;">This file type cannot be previewed.</p>
                <a href="${fileUrl}" download="${fileName}"
                   style="display: inline-block; padding: 12px 24px; background: #1976d2; color: white; text-decoration: none; border-radius: 8px;">
                    Download File
                </a>
            </div>
        `;
    }

    modal.style.display = 'flex';

    // ESC 키로 모달 닫기
    document.addEventListener('keydown', handleFileViewerEscape);
}

// 파일 뷰어 모달 닫기
function closeFileViewer() {
    const modal = document.getElementById('file-viewer-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    document.removeEventListener('keydown', handleFileViewerEscape);
}

// ESC 키 핸들러
function handleFileViewerEscape(e) {
    if (e.key === 'Escape') {
        closeFileViewer();
    }
}
