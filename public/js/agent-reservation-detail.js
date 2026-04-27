/**
 * Agent Admin - Reservation Detail Page JavaScript
 */

let currentBookingId = null;
let isEditMode = false;
let currentBookingData = null; // 현재 예약 데이터 저장
let originalCustomerData = {}; // 수정 전 고객 정보 백업
let originalTravelerData = []; // 수정 전 여행자 정보 백업
let isEditAllowed = false; // Admin이 수정 허용 시 true

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
        showError('예약 번호가 없습니다.');
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
            enterProductEditMode();
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
            showError('예약 정보를 불러오는데 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error loading reservation detail:', error);
        showError('예약 정보를 불러오는 중 오류가 발생했습니다.');
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

    // 디버깅: 여행자 데이터 확인
    console.log('Travelers data:', travelers);
    if (travelers.length > 0) {
        console.log('First traveler:', travelers[0]);
    }

    // 상품 정보
    if (booking.packageName) {
        const productNameInput = document.getElementById('product_name');
        if (productNameInput) productNameInput.value = booking.packageName;
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
                breakfastSelect.value = '신청';
            } else if (x === 'not_applied') {
                breakfastSelect.value = '미신청';
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
                wifiSelect.value = '신청';
            } else if (x === 'not_applied') {
                wifiSelect.value = '미신청';
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
        requestAnimationFrame(() => {
            initTravelerPagination(travelers);
            // 24시간 이내 수정 가능 여부 체크 및 버튼 초기화
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
        container.innerHTML = '<p class="no-traveler-message" data-lan-eng="No travelers information.">여행자 정보가 없습니다.</p>';
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

// 여행자 카드로 스크롤
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

// ===== 여행자 수정 모달 (전체) =====

// 임시 저장용 여권 이미지 (base64)
let __tempPassportImages = {};

// 여행자 수정 모달 열기
function openTravelerEditModal() {
    // Edit Allowed 체크
    if (!isEditAllowed) {
        alert('Edit permission required. Please contact admin to request edit access.');
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

// 여행자 카드 추가 (모달 내)
function addTravelerCardInEdit() {
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
    __allTravelers.forEach((t, i) => {
        t.isMainTraveler = (i === index) ? 1 : 0;
    });
    renderTravelerEditCards();
}

// 여행자 수정 카드 렌더링 - create-reservation 스타일
function renderTravelerEditCards() {
    const container = document.getElementById('traveler-cards-container');
    const countEl = document.getElementById('traveler-edit-count');

    if (!container) return;

    if (__allTravelers.length === 0) {
        container.innerHTML = '<div class="no-travelers-message">No travelers added. Click "+ Add Traveler" to add.</div>';
        if (countEl) countEl.textContent = '0 Travelers';
        return;
    }

    if (countEl) countEl.textContent = `${__allTravelers.length} Traveler${__allTravelers.length > 1 ? 's' : ''}`;

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

        return `
            <div class="traveler-card ${isPrimary ? 'is-primary' : ''}" data-index="${index}">
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
                        <input type="date" id="edit_birthdate_${index}" value="${formatDateForInput(birthDate)}" onchange="updateTravelerAgeInEdit(${index}, this.value)">
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
                        <input type="date" id="edit_passport_issue_${index}" value="${formatDateForInput(passportIssueDate)}">
                    </div>
                    <div class="form-group">
                        <label>Passport Expiration Date</label>
                        <input type="date" id="edit_passport_expiry_${index}" value="${formatDateForInput(passportExpiry)}">
                    </div>
                    <div class="form-group">
                        <label>Visa Application</label>
                        <select id="edit_visa_${index}" onchange="handleVisaTypeChangeInEdit(${index}, this.value)">
                            <option value="" ${!traveler.visaType ? 'selected' : ''} disabled>Select option</option>
                            <option value="with_visa" ${traveler.visaType === 'with_visa' ? 'selected' : ''}>With Visa</option>
                            <option value="group" ${traveler.visaType === 'group' ? 'selected' : ''}>Group Visa +₱1500</option>
                            <option value="individual" ${traveler.visaType === 'individual' ? 'selected' : ''}>Individual Visa +₱1900</option>
                        </select>
                    </div>

                    <!-- Row 4: Passport Photo + Visa Document -->
                    <div class="form-group col-span-2">
                        <label>Passport Photo</label>
                        <div class="passport-photo-upload">
                            <input type="file" id="passport_file_${index}" accept="image/*" style="display:none;" onchange="handlePassportUpload(${index}, this)">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('passport_file_${index}').click()">
                                <img src="../image/upload.svg" alt="" onerror="this.style.display='none'"> Upload Photo
                            </button>
                            <div class="passport-photo-info ${hasPassportPhoto ? '' : 'hidden'}" id="passport-photo-info-${index}">
                                <span class="photo-filename">${escapeHtml(photoFileName)}</span>
                                <button type="button" class="btn-remove-photo" onclick="removePassportImage(${index})">
                                    <img src="../image/button-close2.svg" alt="">
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-span-2 visa-upload-container" id="visa-upload-container-${index}" style="display: ${traveler.visaType === 'with_visa' ? 'block' : 'none'};">
                        <label>Visa Document</label>
                        <div class="visa-document-upload">
                            <input type="file" id="visa_file_${index}" accept="image/*,.pdf" style="display:none;" onchange="handleVisaUploadInEdit(${index}, this)">
                            <button type="button" class="btn-upload-photo" onclick="document.getElementById('visa_file_${index}').click()">
                                <img src="../image/upload.svg" alt="" onerror="this.style.display='none'"> Upload Visa
                            </button>
                            <div class="visa-document-info ${traveler.visaDocument ? '' : 'hidden'}" id="visa-document-info-${index}">
                                <span class="visa-filename">${escapeHtml(traveler.visaDocument ? traveler.visaDocument.split('/').pop() : '')}</span>
                                <button type="button" class="btn-remove-photo" onclick="removeVisaDocumentInEdit(${index})">
                                    <img src="../image/button-close2.svg" alt="">
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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

        // 미리보기 업데이트
        const previewEl = document.getElementById(`passport_preview_${index}`);
        if (previewEl) {
            if (previewEl.tagName === 'IMG') {
                previewEl.src = base64;
            } else {
                previewEl.outerHTML = `<img src="${base64}" alt="Passport" class="passport-preview" id="passport_preview_${index}">`;
            }
        }
    };
    reader.readAsDataURL(file);
}

// 여권 이미지 삭제
function removePassportImage(index) {
    __tempPassportImages[index] = null; // null은 삭제 표시

    const previewEl = document.getElementById(`passport_preview_${index}`);
    if (previewEl) {
        previewEl.outerHTML = `<div id="passport_preview_${index}" style="width: 120px; height: 80px; background: #F3F4F6; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9CA3AF; font-size: 12px;">No Image</div>`;
    }
}

// Visa 타입 변경 핸들러
let __tempVisaDocuments = {};

function handleVisaTypeChangeInEdit(index, value) {
    const container = document.getElementById(`visa-upload-container-${index}`);
    if (container) {
        container.style.display = value === 'with_visa' ? 'block' : 'none';
    }

    // with_visa가 아닌 경우 visa document 초기화
    if (value !== 'with_visa') {
        removeVisaDocumentInEdit(index);
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

// 전체 여행자 저장
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

        travelers.push({
            travelerType: document.getElementById(`edit_type_${i}`)?.value || 'adult',
            title: document.getElementById(`edit_title_${i}`)?.value || 'MR',
            firstName: document.getElementById(`edit_firstname_${i}`)?.value || '',
            lastName: document.getElementById(`edit_lastname_${i}`)?.value || '',
            gender: document.getElementById(`edit_gender_${i}`)?.value || 'male',
            birthDate: document.getElementById(`edit_birthdate_${i}`)?.value || '',
            nationality: document.getElementById(`edit_nationality_${i}`)?.value || '',
            passportNumber: document.getElementById(`edit_passport_${i}`)?.value || '',
            passportIssueDate: document.getElementById(`edit_passport_issue_${i}`)?.value || '',
            passportExpiryDate: document.getElementById(`edit_passport_expiry_${i}`)?.value || '',
            passportImage: passportImage,
            visaRequired: visaRequired,
            visaType: visaType,
            visaDocument: visaDocument,
            isPrimary: original.isMainTraveler == 1
        });
    }

    // 저장 전 인원수 (Adult + Child만 계산, Infant 제외 - Room Option 기준)
    const prevCount = (window.currentTravelers || []).filter(t => {
        const type = (t.travelerType || t.type || '').toLowerCase();
        return type === 'adult' || type === 'child';
    }).length;
    const newCount = travelers.filter(t => {
        const type = (t.travelerType || t.type || '').toLowerCase();
        return type === 'adult' || type === 'child';
    }).length;

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'updateTravelerInfo',
                bookingId: currentBookingId,
                travelers: travelers
            })
        });

        const result = await response.json();
        if (result.success) {
            closeTravelerEditModal();
            await loadReservationDetail();

            // 인원수가 변경되었을 때만 Room Option 모달 열기
            if (prevCount !== newCount) {
                alert('Travelers saved. Please select room options for the updated number of guests.\n여행자 정보가 저장되었습니다. 변경된 인원에 맞게 객실 옵션을 선택해주세요.');
                setTimeout(() => {
                    openRoomOptionModalEdit();
                }, 300);
            }
        } else {
            alert('Failed to update: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving traveler info:', error);
        alert('Failed to save traveler information.');
    }
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
// - 출발 3일 이내 예약: 모두 당일
// - Down Payment: 예약일 + 3일
// - Second Payment: 출발 30일 이내면 예약일+3일, 아니면 예약일+30일
// - Balance: 출발 30일 이내면 예약일+3일, 아니면 출발-30일 (Second보다 짧으면 Second와 동일)

function calculatePaymentDeadlinesFromBooking(reservationDate, departureDate) {
    if (!reservationDate || !departureDate) {
        return { down: null, second: null, balance: null };
    }

    const resDate = new Date(reservationDate);
    resDate.setHours(0, 0, 0, 0);
    const departure = new Date(departureDate);
    departure.setHours(0, 0, 0, 0);

    const daysUntilDeparture = Math.ceil((departure - resDate) / (1000 * 60 * 60 * 24));

    // 특수 케이스: 출발 3일 이내 예약 → 모두 당일
    if (daysUntilDeparture <= 3) {
        return {
            down: formatDateYMD(resDate),
            second: formatDateYMD(resDate),
            balance: formatDateYMD(resDate)
        };
    }

    // Down Payment: 예약일 + 3일
    const downDeadline = new Date(resDate);
    downDeadline.setDate(downDeadline.getDate() + 3);

    // Second Payment: 출발 30일 이내면 예약일+3일, 아니면 예약일+30일
    const secondDeadline = new Date(resDate);
    if (daysUntilDeparture <= 30) {
        secondDeadline.setDate(secondDeadline.getDate() + 3);
    } else {
        secondDeadline.setDate(secondDeadline.getDate() + 30);
    }

    // Balance: 출발 30일 이내면 예약일+3일, 아니면 출발-30일
    let balanceDeadline;
    if (daysUntilDeparture <= 30) {
        balanceDeadline = new Date(resDate);
        balanceDeadline.setDate(balanceDeadline.getDate() + 3);
    } else {
        balanceDeadline = new Date(departure);
        balanceDeadline.setDate(balanceDeadline.getDate() - 30);
    }

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
    const bookingStatus = (booking.bookingStatus || '').toLowerCase();

    // DEBUG: 상태 로깅
    console.log('[updatePaymentSections] bookingStatus:', bookingStatus);
    console.log('[updatePaymentSections] balanceConfirmedAt:', booking.balanceConfirmedAt);
    console.log('[updatePaymentSections] balanceRejectedAt:', booking.balanceRejectedAt);
    console.log('[updatePaymentSections] balanceFile:', booking.balanceFile);

    // Down Payment 확인 여부 - 거절된 경우 또는 상태가 waiting_down_payment면 미확인으로 처리
    const downPaymentRejectedAfterConfirm = booking.downPaymentRejectedAt && booking.downPaymentConfirmedAt &&
        new Date(booking.downPaymentRejectedAt) > new Date(booking.downPaymentConfirmedAt);
    const isWaitingDownPayment = bookingStatus === 'waiting_down_payment';
    const downPaymentConfirmed = !!(booking.downPaymentConfirmedAt) && !downPaymentRejectedAfterConfirm && !isWaitingDownPayment;

    // Second Payment 확인 여부 - 거절된 경우 또는 상태가 waiting_second_payment면 미확인으로 처리
    const secondPaymentRejectedAfterConfirm = booking.advancePaymentRejectedAt && booking.advancePaymentConfirmedAt &&
        new Date(booking.advancePaymentRejectedAt) > new Date(booking.advancePaymentConfirmedAt);
    const isWaitingSecondPayment = bookingStatus === 'waiting_second_payment';
    const secondPaymentConfirmed = !!(booking.advancePaymentConfirmedAt) && !secondPaymentRejectedAfterConfirm && !isWaitingSecondPayment;

    // Balance 확인 여부 - 거절된 경우 또는 상태가 waiting_balance면 미확인으로 처리
    const balanceRejectedAfterConfirm = booking.balanceRejectedAt && booking.balanceConfirmedAt &&
        new Date(booking.balanceRejectedAt) > new Date(booking.balanceConfirmedAt);
    const isWaitingBalance = bookingStatus === 'waiting_balance';
    const balanceConfirmed = !!(booking.balanceConfirmedAt) && !balanceRejectedAfterConfirm && !isWaitingBalance;

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
    const deleteDownBtn = document.getElementById('deleteDownFileBtn');
    const downPaymentStatus = document.getElementById('downPaymentStatus');

    // 거절 상태 또는 waiting_down_payment 상태 체크 - 재업로드 허용 여부
    const allowDownPaymentReupload = downPaymentRejectedAfterConfirm || isWaitingDownPayment;

    if (downPaymentConfirmed) {
        // 확인됨 상태
        if (downPaymentStatus) {
            downPaymentStatus.innerHTML = '<span style="background:#4CAF50;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Confirmed</span>';
        }
        if (downFileDisplay) downFileDisplay.style.display = downFile ? 'flex' : 'none';
        if (downFileUpload) downFileUpload.style.display = 'none';
        if (downFileNameEl) downFileNameEl.textContent = downFileName || 'File';
        if (downloadDownBtn) downloadDownBtn.disabled = !downFile;
        if (deleteDownBtn) deleteDownBtn.style.display = 'none'; // 확인 후 삭제 불가
    } else if (allowDownPaymentReupload) {
        // 거절됨 또는 상태가 다시 waiting_down_payment로 변경됨 - 재업로드 허용
        if (downPaymentStatus) {
            if (downPaymentRejectedAfterConfirm) {
                downPaymentStatus.innerHTML = '<span style="background:#DC2626;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Rejected - Please Re-upload</span>';
            } else {
                downPaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
            }
        }
        if (downFileDisplay) downFileDisplay.style.display = downFile ? 'flex' : 'none';
        if (downFileUpload) downFileUpload.style.display = 'block'; // 재업로드 허용
        if (downFileNameEl && downFile) downFileNameEl.textContent = downFileName || 'File';
        if (downloadDownBtn) downloadDownBtn.disabled = !downFile;
        if (deleteDownBtn) {
            deleteDownBtn.style.display = downFile ? '' : 'none';
            deleteDownBtn.disabled = false;
        }
    } else if (downFile) {
        // 파일 업로드됨, 확인 대기중
        if (downPaymentStatus) {
            downPaymentStatus.innerHTML = '<span style="background:#FF9800;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Pending Confirmation</span>';
        }
        if (downFileDisplay) downFileDisplay.style.display = 'flex';
        if (downFileUpload) downFileUpload.style.display = 'none';
        if (downFileNameEl) downFileNameEl.textContent = downFileName || 'File';
        if (downloadDownBtn) downloadDownBtn.disabled = false;
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
    const deleteSecondBtn = document.getElementById('deleteSecondFileBtn');
    const secondPaymentStatus = document.getElementById('secondPaymentStatus');

    // 관리자가 수동으로 second payment 상태로 변경한 경우 체크
    const isSecondPaymentStage = ['waiting_second_payment', 'checking_second_payment'].includes(bookingStatus);

    // 거절 상태 또는 waiting_second_payment 상태 체크 - 재업로드 허용 여부
    const allowSecondPaymentReupload = secondPaymentRejectedAfterConfirm || isWaitingSecondPayment;

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
        if (deleteSecondBtn) deleteSecondBtn.style.display = 'none';
    } else if (allowSecondPaymentReupload) {
        // 거절됨 또는 상태가 다시 waiting_second_payment로 변경됨 - 재업로드 허용
        if (secondDisabledNotice) secondDisabledNotice.style.display = 'none';
        if (secondPaymentFields) secondPaymentFields.style.display = 'grid';
        if (secondPaymentStatus) {
            if (secondPaymentRejectedAfterConfirm) {
                secondPaymentStatus.innerHTML = '<span style="background:#DC2626;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Rejected - Please Re-upload</span>';
            } else {
                secondPaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
            }
        }
        if (secondFileDisplay) secondFileDisplay.style.display = secondFile ? 'flex' : 'none';
        if (secondFileUpload) secondFileUpload.style.display = 'block'; // 재업로드 허용
        if (secondFileNameEl && secondFile) secondFileNameEl.textContent = secondFileName || 'File';
        if (downloadSecondBtn) downloadSecondBtn.disabled = !secondFile;
        if (deleteSecondBtn) {
            deleteSecondBtn.style.display = secondFile ? '' : 'none';
            deleteSecondBtn.disabled = false;
        }
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
    const deleteBalBtn = document.getElementById('deleteBalanceFileBtn');
    const balancePaymentStatus = document.getElementById('balancePaymentStatus');

    // 관리자가 수동으로 balance 상태로 변경한 경우 체크
    const isBalanceStage = ['waiting_balance', 'checking_balance'].includes(bookingStatus);
    // 거절 상태 또는 waiting_balance 상태 체크 - 재업로드 허용 여부
    const allowBalanceReupload = balanceRejectedAfterConfirm || isWaitingBalance;

    // DEBUG: Balance 조건 로깅
    console.log('[Balance] secondPaymentConfirmed:', secondPaymentConfirmed);
    console.log('[Balance] isBalanceStage:', isBalanceStage);
    console.log('[Balance] balanceConfirmed:', balanceConfirmed);
    console.log('[Balance] allowBalanceReupload:', allowBalanceReupload);
    console.log('[Balance] balFile:', balFile);
    console.log('[Balance] balFileUpload element:', balFileUpload);

    if (!secondPaymentConfirmed && !isBalanceStage) {
        console.log('[Balance] Branch: DISABLED');
        // Second Payment 미확인 & 관리자가 balance 단계로 변경하지 않음 → Balance 비활성화
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'block';
        if (balancePaymentFields) balancePaymentFields.style.display = 'none';
        if (balancePaymentStatus) balancePaymentStatus.innerHTML = '';
    } else if (balanceConfirmed) {
        console.log('[Balance] Branch: CONFIRMED');
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
        if (deleteBalBtn) deleteBalBtn.style.display = 'none';
    } else if (allowBalanceReupload) {
        console.log('[Balance] Branch: ALLOW_REUPLOAD - setting display to block');
        // 거절됨 또는 상태가 다시 waiting_balance로 변경됨 - 재업로드 허용
        if (balanceDisabledNotice) balanceDisabledNotice.style.display = 'none';
        if (balancePaymentFields) balancePaymentFields.style.display = 'grid';
        if (balancePaymentStatus) {
            if (balanceRejectedAfterConfirm) {
                balancePaymentStatus.innerHTML = '<span style="background:#DC2626;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Rejected - Please Re-upload</span>';
            } else {
                balancePaymentStatus.innerHTML = '<span style="background:#9E9E9E;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Not Uploaded</span>';
            }
        }
        if (balFileDisplay) balFileDisplay.style.display = balFile ? 'flex' : 'none';
        if (balFileUpload) {
            balFileUpload.style.display = 'block';
            console.log('[Balance] After set: display =', balFileUpload.style.display);
            console.log('[Balance] Parent balancePaymentFields display =', balancePaymentFields?.style.display);
            console.log('[Balance] balanceSection display =', document.getElementById('balanceSection')?.style.display);
        } else {
            console.log('[Balance] ERROR: balFileUpload is null!');
        }
        if (balFileNameEl && balFile) balFileNameEl.textContent = balFileName || 'File';
        if (downloadBalBtn) downloadBalBtn.disabled = !balFile;
        if (deleteBalBtn) {
            deleteBalBtn.style.display = balFile ? '' : 'none';
            deleteBalBtn.disabled = false;
        }
    } else if (balFile) {
        console.log('[Balance] Branch: FILE_EXISTS');
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
        if (deleteBalBtn) {
            deleteBalBtn.style.display = '';
            deleteBalBtn.disabled = false;
        }
    } else {
        console.log('[Balance] Branch: NO_FILE - should show upload');
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
        nameEl.textContent = '첨부된 파일이 없습니다.';
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
        alert('예약 번호가 없습니다.');
        return;
    }
    
    if (!confirm('선금 증빙 파일을 삭제하시겠습니까?')) {
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
            alert('선금 증빙 파일이 삭제되었습니다.');
            loadReservationDetail(); // 페이지 재로드
        } else {
            alert('파일 삭제에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error removing deposit proof file:', error);
        alert('파일 삭제 중 오류가 발생했습니다.');
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
    confirmBtn.textContent = '선금 확인';
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
    amountInput.placeholder = '금액 입력';
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
    confirmedBtn.textContent = '선금 확인 완료';
    
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
    confirmBtn.textContent = '잔금 확인';
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
    amountInput.placeholder = '금액 입력';
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
    confirmedBtn.textContent = '잔금 확인 완료';
    
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
        alert('금액을 입력해주세요.');
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
            alert('선금이 확인되었습니다.');
            loadReservationDetail(); // 재로드
        } else {
            alert('선금 확인에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error confirming deposit:', error);
        alert('선금 확인 중 오류가 발생했습니다.');
    }
}

async function handleBalanceConfirm() {
    const amountInput = document.getElementById('balance_confirmed_amount');
    const amount = amountInput ? parseFloat(amountInput.value.replace(/,/g, '')) : 0;
    const dueInput = document.getElementById('balance_due');
    const dueDate = dueInput ? dueInput.value : null;
    
    if (!amount || amount <= 0) {
        alert('금액을 입력해주세요.');
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
            alert('잔금이 확인되었습니다.');
            loadReservationDetail(); // 재로드
        } else {
            alert('잔금 확인에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error confirming balance:', error);
        alert('잔금 확인 중 오류가 발생했습니다.');
    }
}

function formatPriceNumber(price) {
    if (!price) return '0';
    return parseInt(price).toLocaleString();
}

async function handleSave() {
    if (!isEditMode) {
        alert('변경된 내용이 없습니다.');
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
            alert('저장되었습니다.');
            isEditMode = false;
            loadReservationDetail(); // 재로드
        } else {
            alert('저장에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert('저장 중 오류가 발생했습니다.');
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
        tbody.innerHTML = '<tr><td colspan="9" class="is-center">로딩 중...</td></tr>';
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
    if (status === 'pending_deposit' || status === 'waiting_for_deposit') return 'pending_deposit';
    if (status === 'pending_balance' || status === 'waiting_for_balance' || status === 'waiting_for_balance_payment') return 'pending_balance';

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
        historyList.innerHTML = '<div class="history-item">예약 이력이 없습니다.</div>';
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
        alert('날짜를 선택해주세요.');
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
            alert('입금 기한이 설정되었습니다.');
            closeModal('deadlineModal');
            loadReservationDetail();
        } else {
            alert('입금 기한 설정에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error setting deadline:', error);
        alert('입금 기한 설정 중 오류가 발생했습니다.');
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
            alert('파일이 업로드되었습니다.');
            loadReservationDetail();
        } else {
            alert('파일 업로드에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('파일 업로드 중 오류가 발생했습니다.');
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
    if (!confirm('증빙 파일을 삭제하시겠습니까?')) return;
    
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
            alert('파일이 삭제되었습니다.');
            loadReservationDetail();
        } else {
            alert('파일 삭제에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting file:', error);
        alert('파일 삭제 중 오류가 발생했습니다.');
    }
}

// 예약 취소
async function handleCancelReservation() {
    if (!confirm('정말로 예약을 취소하시겠습니까?')) return;
    
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
            alert('예약이 취소되었습니다.');
            closeModal('cancelReservationModal');
            loadReservationDetail();
        } else {
            alert('예약 취소에 실패했습니다: ' + result.message);
        }
    } catch (error) {
        console.error('Error cancelling reservation:', error);
        alert('예약 취소 중 오류가 발생했습니다.');
    }
}

// 모달 열기/닫기
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// ===== 24시간 이내 수정 기능 =====

// 예약일로부터 24시간 이내인지 확인
function isWithin24Hours(createdAt) {
    if (!createdAt) return false;
    const reservationDate = new Date(createdAt);
    const now = new Date();
    const diffMs = now - reservationDate;
    const diffHours = diffMs / (1000 * 60 * 60);
    console.log(`[24h Check] Reservation: ${createdAt}, Diff hours: ${diffHours.toFixed(2)}`);
    return diffHours <= 24;
}

// 24시간 이내인지 저장
let __isWithin24Hours = false;

// 24시간 이내면 수정 버튼 표시
function initEditButtonsIfWithin24Hours(booking) {
    const within24h = isWithin24Hours(booking.createdAt);
    __isWithin24Hours = within24h;
    console.log(`[24h Check] Within 24 hours: ${within24h}`);

    // Customer Info 수정 버튼
    const customerEditBtns = document.getElementById('customerEditBtns');
    if (customerEditBtns) {
        customerEditBtns.style.display = within24h ? 'flex' : 'none';
    }

    // Traveler Info Edit 버튼 (카드 형식 헤더에 표시)
    const editTravelerBtn = document.getElementById('editTravelerBtn');
    if (editTravelerBtn) {
        editTravelerBtn.style.display = within24h ? 'inline-flex' : 'none';
        editTravelerBtn.onclick = openTravelerEditModal;
    }

    // 이벤트 리스너 등록
    if (within24h) {
        // Customer Edit 버튼
        const editCustomerBtn = document.getElementById('editCustomerBtn');
        const saveCustomerBtn = document.getElementById('saveCustomerBtn');
        const cancelCustomerBtn = document.getElementById('cancelCustomerBtn');

        if (editCustomerBtn) editCustomerBtn.onclick = startEditCustomer;
        if (saveCustomerBtn) saveCustomerBtn.onclick = saveCustomerInfo;
        if (cancelCustomerBtn) cancelCustomerBtn.onclick = cancelEditCustomer;
    }
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
 */
function updateEditButtonsState() {
    const editProductBtn = document.getElementById('editProductBtn');
    const editTravelerBtn = document.getElementById('editTravelerBtn');

    if (isEditAllowed) {
        // Admin이 수정 허용한 경우 - 버튼 표시 (기존 로직 유지)
        if (editProductBtn) {
            editProductBtn.style.display = 'inline-flex';
            editProductBtn.disabled = false;
            editProductBtn.style.opacity = '';
            editProductBtn.title = '';
        }
        // editTravelerBtn은 기존 within24h 로직에 의해 제어되므로 여기서는 disabled만 해제
        if (editTravelerBtn) {
            editTravelerBtn.disabled = false;
            editTravelerBtn.style.opacity = '';
            editTravelerBtn.title = '';
        }
    } else {
        // Admin이 수정 허용하지 않은 경우 - 버튼 비활성화
        if (editProductBtn) {
            editProductBtn.disabled = true;
            editProductBtn.style.opacity = '0.5';
            editProductBtn.style.cursor = 'not-allowed';
            editProductBtn.title = 'Edit permission required. Please contact admin.';
        }
        if (editTravelerBtn) {
            editTravelerBtn.disabled = true;
            editTravelerBtn.style.opacity = '0.5';
            editTravelerBtn.style.cursor = 'not-allowed';
            editTravelerBtn.title = 'Edit permission required. Please contact admin.';
        }
    }
}

function enterProductEditMode() {
    // Edit Allowed 체크
    if (!isEditAllowed) {
        alert('Edit permission required. Please contact admin to request edit access.');
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
            results.innerHTML = result.data.map(pkg => `
                <div class="product-item" data-package-id="${pkg.packageId}" onclick="selectProductItem(this, ${pkg.packageId}, '${escapeHtml(pkg.packageName)}', ${pkg.duration_days || 5})">
                    <div class="product-item-content">
                        <div class="product-info">
                            <div class="product-name">${escapeHtml(pkg.packageName)}</div>
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
        alert('상품을 선택하세요');
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
            alert('상품 정보가 저장되었습니다.\nProduct information saved.');
            endProductEditMode();
            // 데이터 새로고침
            await loadReservationDetail();
        } else {
            alert('저장 실패: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Save product info error:', error);
        alert('저장 중 오류가 발생했습니다.');
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

    // 거절됨 또는 waiting_full_payment 상태 체크 - 재업로드 허용 여부
    const bookingStatus = (booking.bookingStatus || '').toLowerCase();
    const fullPaymentRejectedAfterConfirm = booking.fullPaymentRejectedAt && booking.fullPaymentConfirmedAt &&
        new Date(booking.fullPaymentRejectedAt) > new Date(booking.fullPaymentConfirmedAt);
    const isWaitingFullPayment = bookingStatus === 'waiting_full_payment';
    const fullPaymentConfirmed = !!(booking.fullPaymentConfirmedAt) && !fullPaymentRejectedAfterConfirm && !isWaitingFullPayment;
    const allowFullPaymentReupload = fullPaymentRejectedAfterConfirm || isWaitingFullPayment;

    // 파일 표시
    if (fullPaymentConfirmed) {
        // 확인됨 상태
        if (fullFileDisplay) fullFileDisplay.style.display = booking.fullPaymentFile ? 'flex' : 'none';
        if (fullFileUpload) fullFileUpload.style.display = 'none';
        if (fullFileName && booking.fullPaymentFile) {
            const fileNameText = booking.fullPaymentFileName || booking.fullPaymentFile.split('/').pop();
            fullFileName.textContent = fileNameText;
        }
    } else if (allowFullPaymentReupload) {
        // 거절됨 또는 상태가 다시 waiting_full_payment로 변경됨 - 재업로드 허용
        if (fullFileDisplay) fullFileDisplay.style.display = booking.fullPaymentFile ? 'flex' : 'none';
        if (fullFileUpload) fullFileUpload.style.display = 'block'; // 재업로드 허용
        if (fullFileName && booking.fullPaymentFile) {
            const fileNameText = booking.fullPaymentFileName || booking.fullPaymentFile.split('/').pop();
            fullFileName.textContent = fileNameText;
        }
    } else if (booking.fullPaymentFile) {
        if (fullFileDisplay) fullFileDisplay.style.display = 'flex';
        if (fullFileUpload) fullFileUpload.style.display = 'none';
        if (fullFileName) {
            const fileNameText = booking.fullPaymentFileName || booking.fullPaymentFile.split('/').pop();
            fullFileName.textContent = fileNameText;
        }
    } else {
        if (fullFileDisplay) fullFileDisplay.style.display = 'none';
        if (fullFileUpload) fullFileUpload.style.display = 'block';
    }

    // 상태 배지 표시
    if (fullPaymentStatus) {
        if (fullPaymentConfirmed) {
            fullPaymentStatus.innerHTML = '<span class="badge badge-success">Confirmed</span>';
            fullPaymentStatus.className = 'payment-status confirmed';
        } else if (fullPaymentRejectedAfterConfirm) {
            fullPaymentStatus.innerHTML = '<span style="background:#DC2626;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">Rejected - Please Re-upload</span>';
            fullPaymentStatus.className = 'payment-status rejected';
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
            formData.append('paymentStep', 'full');
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
            window.open(`../backend/api/agent-api.php?action=downloadPaymentProofFile&bookingId=${bookingId}&paymentStep=full`, '_blank');
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
                        paymentStep: 'full'
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
    // 현재 선택된 룸 옵션 복사
    selectedRoomsInModal = [];
    if (window.currentSelectedRooms) {
        if (Array.isArray(window.currentSelectedRooms)) {
            selectedRoomsInModal = window.currentSelectedRooms.map(r => ({...r}));
        } else {
            selectedRoomsInModal = Object.values(window.currentSelectedRooms).map(r => ({...r}));
        }
    }

    openModal('room-option-modal');
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

    listEl.innerHTML = currentRoomOptions.map(room => {
        const existingRoom = selectedRoomsInModal.find(r => r.roomId === room.roomId || r.roomType === room.roomType);
        const count = existingRoom ? (existingRoom.count || existingRoom.quantity || 0) : 0;
        const hasValue = count > 0 ? ' has-value' : '';

        return `
            <div class="room-option-item" data-room-id="${room.roomId}">
                <div class="room-option-info">
                    <div class="room-option-name">${room.roomType}</div>
                    <div class="room-option-capacity">${room.capacity} persons</div>
                    ${room.roomPrice > 0 ? `<div class="room-option-price">+₱${parseInt(room.roomPrice).toLocaleString()}</div>` : ''}
                </div>
                <div class="quantity-selector">
                    <button type="button" class="quantity-btn" onclick="changeRoomQtyEdit('${room.roomId}', -1)">
                        <img src="../image/ico_minus.svg" alt="-">
                    </button>
                    <span class="quantity-value${hasValue}" id="qty-${room.roomId}">${count}</span>
                    <button type="button" class="quantity-btn" onclick="changeRoomQtyEdit('${room.roomId}', 1)">
                        <img src="../image/ico_plus.svg" alt="+">
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// 룸 수량 변경
function changeRoomQtyEdit(roomId, delta) {
    const room = currentRoomOptions.find(r => r.roomId === roomId);
    if (!room) return;

    let existingIdx = selectedRoomsInModal.findIndex(r => r.roomId === roomId || r.roomType === room.roomType);
    if (existingIdx === -1) {
        selectedRoomsInModal.push({
            roomId: room.roomId,
            roomType: room.roomType,
            capacity: room.capacity,
            roomPrice: room.roomPrice,
            count: 0
        });
        existingIdx = selectedRoomsInModal.length - 1;
    }

    const newCount = Math.max(0, (selectedRoomsInModal[existingIdx].count || 0) + delta);
    selectedRoomsInModal[existingIdx].count = newCount;

    // UI 업데이트
    const qtyEl = document.getElementById(`qty-${roomId}`);
    if (qtyEl) {
        qtyEl.textContent = newCount;
        if (newCount > 0) {
            qtyEl.classList.add('has-value');
        } else {
            qtyEl.classList.remove('has-value');
        }
    }

    updateRoomCombinationBanner();
    updateOrderSummary();
}

// 총 예약 인원 계산 (Traveler 정보 기반)
function getTotalBookingGuests() {
    // 현재 traveler 정보에서 Adult + Child(childRoom=Yes) 합계
    let total = 0;
    if (window.currentTravelers && Array.isArray(window.currentTravelers)) {
        window.currentTravelers.forEach(t => {
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

    countEl.textContent = `(${totalCapacity}/${totalGuests}명)`;

    // 버튼 활성화/비활성화
    const confirmBtn = document.getElementById('confirm-room-selection-btn');
    if (confirmBtn) {
        confirmBtn.disabled = totalCapacity < totalGuests;
    }
}

// 주문 요약 업데이트
function updateOrderSummary() {
    const summaryEl = document.getElementById('order-summary-list');
    const amountEl = document.getElementById('order-amount-value');
    if (!summaryEl) return;

    const selectedWithCount = selectedRoomsInModal.filter(r => r.count > 0);
    if (selectedWithCount.length === 0) {
        summaryEl.innerHTML = '<div style="color:#9CA3AF; text-align:center; padding:20px;">No rooms selected</div>';
        if (amountEl) amountEl.textContent = '0(₱)';
        return;
    }

    let totalAmount = 0;
    summaryEl.innerHTML = selectedWithCount.map(room => {
        const price = (room.roomPrice || 0) * room.count;
        totalAmount += price;
        return `
            <div class="order-summary-item">
                <span>${room.roomType} x${room.count}</span>
                <span>₱${price.toLocaleString()}</span>
            </div>
        `;
    }).join('');

    if (amountEl) amountEl.textContent = `${totalAmount.toLocaleString()}(₱)`;
}

// 룸 옵션 선택 확인
async function confirmRoomSelectionEdit() {
    const totalGuests = getTotalBookingGuests();
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

    // 서버에 저장
    try {
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
            alert('Room options updated successfully.');
            closeModal('room-option-modal');
            window.currentSelectedRooms = selectedRoomsInModal.filter(r => r.count > 0);

            // 화면 업데이트
            const roomOptText = document.getElementById('room_opt_text');
            if (roomOptText) {
                const parts = selectedRoomsInModal
                    .filter(r => r.count > 0)
                    .map(r => `${r.roomType}x${r.count}`);
                roomOptText.value = parts.join(', ') || '-';
            }

            // 전체 새로고침
            await loadReservationDetail();
        } else {
            alert('Failed to update: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating room options:', error);
        alert('Error updating room options.');
    }
}

// Room Option 버튼 이벤트 등록
document.addEventListener('DOMContentLoaded', function() {
    const roomOptionBtn = document.getElementById('room_option_btn');
    if (roomOptionBtn) {
        roomOptionBtn.addEventListener('click', openRoomOptionModalEdit);
    }
});

// 전역 함수로 노출
window.openRoomOptionModalEdit = openRoomOptionModalEdit;
window.changeRoomQtyEdit = changeRoomQtyEdit;
window.confirmRoomSelectionEdit = confirmRoomSelectionEdit;
window.showRoomOptionEditButton = showRoomOptionEditButton;
window.hideRoomOptionEditButton = hideRoomOptionEditButton;
