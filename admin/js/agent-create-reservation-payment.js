/**
 * Agent Create Reservation - Step 2: Payment
 * 예약 생성 2단계 - 결제 정보 입력
 */

// 전역 변수
let currentBookingId = null;
let currentBookingData = null;
let selectedPaymentType = 'staged';
let downPaymentFile = null;
let fullPaymentFile = null;
let isReservationCompleted = false; // 예약 완료 여부 플래그

// 페이지 초기화
document.addEventListener('DOMContentLoaded', async function() {
    // URL에서 bookingId 파싱
    const urlParams = new URLSearchParams(window.location.search);
    currentBookingId = urlParams.get('bookingId');

    if (!currentBookingId) {
        alert('Booking ID is missing. Redirecting to reservation creation page.');
        window.location.href = 'create-reservation.html';
        return;
    }

    // 예약 정보 로드
    await loadReservationDetail(currentBookingId);

    // UI 초기화
    initializePaymentUI();
    initializeFileUpload();

    // 이벤트 핸들러 등록
    document.getElementById('saveBtn').addEventListener('click', handleSave);
    document.getElementById('backBtn').addEventListener('click', handleBack);

    // 페이지 이탈 시 예약 삭제
    window.addEventListener('beforeunload', handlePageUnload);
    window.addEventListener('pagehide', handlePageUnload);
});

/**
 * 예약 상세 정보 로드
 */
async function loadReservationDetail(bookingId) {
    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'getReservationDetail',
                bookingId: bookingId
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load reservation');
        }

        // API 응답: result.data.booking에 예약 정보가 있음
        currentBookingData = result.data.booking || result.data;

        // travelers와 selectedOptions도 currentBookingData에 병합
        if (result.data.travelers) {
            currentBookingData.travelers = result.data.travelers;
        }
        if (result.data.selectedOptions) {
            currentBookingData.selectedOptions = result.data.selectedOptions;
        }

        // 할인 정보 조회 (해당 날짜에 세일이 적용되어 있는지 확인)
        await loadSaleInfo(currentBookingData);

        displayReservationSummary(currentBookingData);
        displayPaymentInfo(currentBookingData);

    } catch (error) {
        console.error('Error loading reservation:', error);
        alert('Failed to load reservation: ' + error.message);
        window.location.href = 'reservation-list.html';
    }
}

/**
 * 할인 정보 조회 (해당 날짜에 세일이 적용되어 있는지 확인)
 */
async function loadSaleInfo(data) {
    try {
        const packageId = data.packageId;
        const departureDate = data.departureDate;

        if (!packageId || !departureDate) return;

        const dateObj = new Date(departureDate);
        const year = dateObj.getFullYear();
        const month = dateObj.getMonth() + 1;

        const response = await fetch(`${window.location.origin}/backend/api/product_availability.php?id=${packageId}&year=${year}&month=${month}`, {
            credentials: 'same-origin'
        });

        const result = await response.json();

        if (result.success && result.data && result.data.availability) {
            const dateInfo = result.data.availability.find(d => d.availableDate === departureDate);
            if (dateInfo && dateInfo.isOnSale) {
                data.saleInfo = {
                    isOnSale: true,
                    discountAmount: dateInfo.discountAmount || 0,
                    saleName: dateInfo.saleName || '',
                    originalPrice: dateInfo.originalPrice || 0,
                    originalB2bPrice: dateInfo.originalB2bPrice || 0
                };
                console.log('[Payment] Sale info loaded:', data.saleInfo);
            }
        }
    } catch (error) {
        console.error('Error loading sale info:', error);
    }
}

/**
 * 예약 요약 정보 표시
 */
function displayReservationSummary(data) {
    // 예약번호
    const bookingIdEl = document.getElementById('summary_booking_id');
    if (bookingIdEl) bookingIdEl.textContent = data.bookingId || '-';

    // 상품명
    const packageNameEl = document.getElementById('summary_package_name');
    if (packageNameEl) packageNameEl.textContent = data.packageName || '-';

    // 여행기간
    const tripRangeEl = document.getElementById('summary_trip_range');
    if (tripRangeEl) {
        const startDate = data.departureDate || '';
        const endDate = data.returnDate || '';
        if (startDate && endDate) {
            tripRangeEl.textContent = `${formatDisplayDate(startDate)} ~ ${formatDisplayDate(endDate)}`;
        } else if (startDate) {
            tripRangeEl.textContent = formatDisplayDate(startDate);
        } else {
            tripRangeEl.textContent = '-';
        }
    }

    // 인원
    const travelersEl = document.getElementById('summary_travelers');
    if (travelersEl) {
        const adults = parseInt(data.adults) || 0;
        const children = parseInt(data.children) || 0;
        const infants = parseInt(data.infants) || 0;
        const parts = [];
        if (adults > 0) parts.push(`Adult ${adults}`);
        if (children > 0) parts.push(`Child ${children}`);
        if (infants > 0) parts.push(`Infant ${infants}`);
        travelersEl.textContent = parts.length > 0 ? parts.join(', ') : '-';
    }

    // 금액 상세 breakdown 표시 (먼저 계산)
    const breakdown = displayAmountBreakdown(data);

    // 총 금액 (Visa Fee, Flight Options 포함된 계산 총액 사용)
    const totalAmountEl = document.getElementById('summary_total_amount');
    if (totalAmountEl) {
        const displayTotal = breakdown.total || parseFloat(data.totalAmount) || 0;
        totalAmountEl.textContent = `PHP ${formatCurrency(displayTotal)}`;
    }

    // 계산된 총액을 data에 저장 (Payment 계산에서 사용)
    data._calculatedTotal = breakdown.total || 0;
    data._visaFee = breakdown.visaFee || 0;
    data._flightOptions = breakdown.flightOptions || 0;
}

/**
 * 금액 상세 breakdown 표시
 * - Package Price: 여행자별 가격 합계 (adults × packagePrice, child/infant는 별도 계산)
 * - Room Options: 선택된 룸 옵션 합계
 * - Visa Fee: 여행자별 비자 요금 합계
 * - Flight Options: 여행자별 항공 옵션 합계
 */
function displayAmountBreakdown(data) {
    const breakdownSection = document.getElementById('amount-breakdown-section');
    const breakdownList = document.getElementById('amount-breakdown-list');

    if (!breakdownSection || !breakdownList) return { total: 0, visaFee: 0, flightOptions: 0 };

    // selectedOptions 파싱
    let selectedOptions = {};
    if (data.selectedOptions) {
        try {
            selectedOptions = typeof data.selectedOptions === 'string'
                ? JSON.parse(data.selectedOptions)
                : data.selectedOptions;
        } catch (e) {
            console.error('Failed to parse selectedOptions:', e);
        }
    }

    const breakdownItems = [];
    let calculatedTotal = 0;

    // travelers 파싱 (공통으로 사용)
    let travelersArr = [];
    if (data.travelers) {
        try {
            travelersArr = typeof data.travelers === 'string'
                ? JSON.parse(data.travelers)
                : (Array.isArray(data.travelers) ? data.travelers : []);
        } catch (e) {
            travelersArr = [];
        }
    }

    // 1. Package Price 계산 (여행자별 가격 합계)
    // - Adult: packagePrice
    // - Child (Room Yes): adult price
    // - Child (Room No): childPrice || adult × 70%
    // - Infant: infantPrice || 10,000

    // 할인 정보 확인 - 할인 전 가격으로 Package Price 표시
    const saleInfo = data.saleInfo || null;
    const discountPerAdult = (saleInfo && saleInfo.isOnSale) ? (saleInfo.discountAmount || 0) : 0;

    // 할인된 가격 (저장된 값)
    const packagePrice = parseFloat(data.packagePrice) || 0;
    // 할인 전 가격 계산 (할인이 있으면 원래 가격 사용)
    const originalPackagePrice = discountPerAdult > 0 ? (packagePrice + discountPerAdult) : packagePrice;

    const childPrice = parseFloat(data.childPrice) || 0;
    const infantPrice = parseFloat(data.infantPrice) || 0;

    let packageTotal = 0;  // 할인 전 가격 기준
    let adultCount = 0;    // 할인 적용 대상 인원 (성인)

    if (travelersArr.length > 0) {
        travelersArr.forEach(t => {
            const type = (t.travelerType || t.type || 'adult').toLowerCase();
            if (type.includes('infant') || type.includes('baby')) {
                // Infant: DB price or 10,000
                packageTotal += (infantPrice > 0) ? infantPrice : 10000;
            } else if (type.includes('child') || type.includes('kid')) {
                // Child: Room Yes → adult price, Room No → childPrice or adult×80%
                const childRoom = t.childRoom === true || t.childRoom === 1 || t.childRoom === '1';
                if (childRoom) {
                    packageTotal += originalPackagePrice;
                    adultCount++; // Child with room도 할인 적용
                } else {
                    packageTotal += (childPrice > 0) ? childPrice : Math.round(originalPackagePrice * 0.8);
                }
            } else {
                // Adult - 할인 전 가격 사용
                packageTotal += originalPackagePrice;
                adultCount++;
            }
        });
    } else {
        // fallback: travelers 정보 없으면 인원수 기반 계산
        const adults = parseInt(data.adults) || 0;
        const children = parseInt(data.children) || 0;
        const infants = parseInt(data.infants) || 0;
        adultCount = adults;
        packageTotal = (adults * originalPackagePrice) +
                       (children * ((childPrice > 0) ? childPrice : Math.round(originalPackagePrice * 0.8))) +
                       (infants * ((infantPrice > 0) ? infantPrice : 10000));
    }

    // 총 할인 금액 계산
    const totalDiscount = discountPerAdult * adultCount;

    // 2. Room Options 계산 (1인 예약이어도 싱글룸 추가요금 부과)
    const selectedRooms = selectedOptions.selectedRooms || [];
    let roomTotal = 0;
    selectedRooms.forEach(room => {
        const price = parseFloat(room.roomPrice || room.price || 0);
        const count = parseInt(room.count || 1);
        if (count <= 0) return;
        roomTotal += price * count;
    });

    // 3. Visa Amount 계산
    let visaTotal = 0;
    travelersArr.forEach(t => {
        const visaType = (t.visaType || t.visa_type || 'with_visa').toLowerCase();
        if (visaType === 'group') {
            visaTotal += 1500;
        } else if (visaType === 'individual') {
            visaTotal += 1900;
        }
    });

    // 4. Flight Options 계산
    let flightOptionsTotal = 0;
    travelersArr.forEach(t => {
        if (t.flightOptionPrices && typeof t.flightOptionPrices === 'object') {
            Object.values(t.flightOptionPrices).forEach(price => {
                flightOptionsTotal += parseFloat(price) || 0;
            });
        }
    });

    // Package Price 추가 (항상 표시)
    if (packageTotal > 0) {
        breakdownItems.push({ label: 'Package Price', value: packageTotal });
        calculatedTotal += packageTotal;
    }

    // Room Options 추가
    if (roomTotal > 0) {
        breakdownItems.push({ label: 'Room Options', value: roomTotal });
        calculatedTotal += roomTotal;
    }

    // Visa Fee 추가
    if (visaTotal > 0) {
        breakdownItems.push({ label: 'Visa Fee', value: visaTotal });
        calculatedTotal += visaTotal;
    }

    // Flight Options 추가
    if (flightOptionsTotal > 0) {
        breakdownItems.push({ label: 'Flight Options', value: flightOptionsTotal });
        calculatedTotal += flightOptionsTotal;
    }

    // Breakdown이 있으면 표시
    if (breakdownItems.length > 0) {
        let html = '';

        // 일반 항목들 (Package Price, Room Options, Visa Fee, Flight Options)
        breakdownItems.forEach(item => {
            html += `
                <div class="breakdown-item">
                    <span class="breakdown-item-label">${item.label}</span>
                    <span class="breakdown-item-value">₱${formatCurrency(item.value)}</span>
                </div>
            `;
        });

        // 할인 항목 표시 (할인이 있는 경우만)
        if (totalDiscount > 0 && saleInfo && saleInfo.saleName) {
            html += `
                <div class="breakdown-item" style="background: #FEF2F2; border: 1px solid #FECACA;">
                    <span class="breakdown-item-label" style="color: #DC2626; font-weight: 600;">
                        <span style="background: #DC2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 8px;">SALE</span>
                        ${saleInfo.saleName}
                    </span>
                    <span class="breakdown-item-value" style="color: #DC2626; font-weight: 600;">-₱${formatCurrency(totalDiscount)}</span>
                </div>
            `;
            // 할인 차감
            calculatedTotal -= totalDiscount;
        }

        // Total: 계산된 총액 (할인 차감 후)
        html += `
            <div class="breakdown-item total">
                <span class="breakdown-item-label">Total Amount</span>
                <span class="breakdown-item-value">₱${formatCurrency(calculatedTotal)}</span>
            </div>
        `;

        breakdownList.innerHTML = html;
        breakdownSection.style.display = 'block';
    } else {
        breakdownSection.style.display = 'none';
    }

    // 계산된 총액, Visa Fee, Flight Options 반환
    return { total: calculatedTotal, visaFee: visaTotal, flightOptions: flightOptionsTotal };
}

/**
 * 결제 정보 표시
 */
function displayPaymentInfo(data) {
    // 계산된 총액 사용 (Visa Fee, Flight Options 포함)
    const totalAmount = parseFloat(data._calculatedTotal || data.totalAmount) || 0;

    // 인원수 계산 (adults + children, infants 제외)
    const adults = parseInt(data.adults) || 0;
    const children = parseInt(data.children) || 0;
    const travelerCount = adults + children;

    // Visa Fee: 이미 계산된 값 사용 또는 재계산
    let visaFee = parseFloat(data._visaFee) || 0;
    if (visaFee === 0 && Array.isArray(data.travelers)) {
        data.travelers.forEach(t => {
            const visaType = String(t.visaType || '').toLowerCase();
            if (visaType === 'group') visaFee += 1500;
            else if (visaType === 'individual') visaFee += 1900;
        });
    }

    // Order Amount 표시 (계산된 총액)
    const payTotalEl = document.getElementById('pay_total');
    const fullPayTotalEl = document.getElementById('full_pay_total');
    if (payTotalEl) payTotalEl.value = formatCurrency(totalAmount);
    if (fullPayTotalEl) fullPayTotalEl.value = formatCurrency(totalAmount);

    // 출발일까지 남은 일수 계산
    const daysUntilDeparture = getDaysUntilDeparture(data.departureDate);

    // 결제 타입 제어 (출발일 기준)
    applyPaymentTypeRestrictions(daysUntilDeparture, data.paymentType);

    // Staged Payment 금액 계산 (인원수, Visa Fee 전달)
    calculatePaymentAmounts(totalAmount, travelerCount, visaFee);

    // Full Payment 금액
    const fullPaymentAmountEl = document.getElementById('full_payment_amount');
    if (fullPaymentAmountEl) fullPaymentAmountEl.value = formatCurrency(totalAmount);

    // 데드라인 계산
    calculatePaymentDeadlines(data.departureDate);
}

/**
 * 출발일까지 남은 일수 계산
 */
function getDaysUntilDeparture(departureDate) {
    if (!departureDate) return null;
    const departure = new Date(departureDate);
    departure.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.ceil((departure - today) / (1000 * 60 * 60 * 24));
}

/**
 * 결제 타입 제한 적용
 * - 34일 이내: Full Payment Only (24시간 내)
 * - 35~44일: Full Payment Only (3일 내)
 * - 44일 초과: Staged/Full 선택 가능
 */
function applyPaymentTypeRestrictions(daysUntilDeparture, existingPaymentType) {
    const stagedTabBtn = document.querySelector('[data-payment-type="staged"]');
    const fullTabBtn = document.querySelector('[data-payment-type="full"]');
    const warningBox = document.getElementById('payment-warning-box');

    // Warning 메시지 업데이트
    if (warningBox) {
        if (daysUntilDeparture !== null && daysUntilDeparture <= 34) {
            warningBox.innerHTML = `<strong>Warning:</strong> For products with less than 34 days until departure, all payments must be completed within <strong>24 hours</strong>. (Full Payment only)`;
        } else if (daysUntilDeparture !== null && daysUntilDeparture <= 44) {
            warningBox.innerHTML = `<strong>Warning:</strong> For products with 35-44 days until departure, all payments must be completed within <strong>3 days</strong>. (Full Payment only)`;
        } else {
            warningBox.style.background = '#F0F7FF';
            warningBox.style.borderColor = '#0050C8';
            warningBox.style.color = '#0050C8';
            warningBox.innerHTML = `<strong>Info:</strong> For products with more than 44 days until departure, you can choose between <strong>Staged Payment (3-Step)</strong> or <strong>Full Payment</strong>.`;
        }
    }

    // 44일 이내: Full Payment만 가능
    if (daysUntilDeparture !== null && daysUntilDeparture <= 44) {
        // Staged 탭 비활성화
        if (stagedTabBtn) {
            stagedTabBtn.disabled = true;
            stagedTabBtn.style.opacity = '0.5';
            stagedTabBtn.style.cursor = 'not-allowed';
            stagedTabBtn.title = daysUntilDeparture <= 34
                ? 'Only Full Payment available (departure within 34 days)'
                : 'Only Full Payment available (departure within 44 days)';
        }
        // Full Payment 강제 선택
        switchPaymentType('full');
    } else {
        // 44일 초과: 선택 가능
        if (stagedTabBtn) {
            stagedTabBtn.disabled = false;
            stagedTabBtn.style.opacity = '1';
            stagedTabBtn.style.cursor = 'pointer';
            stagedTabBtn.title = '';
        }
        // 기존 결제 타입 유지 또는 기본값
        const paymentType = existingPaymentType || 'staged';
        switchPaymentType(paymentType);
    }
}

/**
 * Payment 금액 계산
 */
function calculatePaymentAmounts(totalAmount, travelerCount, visaFee = 0) {
    // Down Payment: 5,000 PHP × 인원수
    const downPayment = 5000 * travelerCount;
    const downPaymentEl = document.getElementById('down_payment_amount');
    if (downPaymentEl) downPaymentEl.value = formatCurrency(downPayment);

    // Second Payment: 10,000 PHP × 인원수 + Visa Fee
    const secondPayment = 10000 * travelerCount + visaFee;
    const secondPaymentEl = document.getElementById('second_payment_amount');
    if (secondPaymentEl) secondPaymentEl.value = formatCurrency(secondPayment);

    // Balance: 나머지 금액
    const balance = totalAmount - downPayment - secondPayment;
    const balanceEl = document.getElementById('balance_amount');
    if (balanceEl) balanceEl.value = formatCurrency(balance);
}

/**
 * 결제 데드라인 계산
 * 규칙:
 * - 출발 34일 이내: Full Payment만, deadline = +1일 (24시간)
 * - 출발 35~44일: Full Payment만, deadline = +3일
 * - 출발 44일 초과: Staged Payment
 */
function calculatePaymentDeadlines(departureDate) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // 출발일까지 남은 일수 계산
    let daysUntilDeparture = null;
    if (departureDate) {
        const departure = new Date(departureDate);
        departure.setHours(0, 0, 0, 0);
        daysUntilDeparture = Math.ceil((departure - today) / (1000 * 60 * 60 * 24));
    }

    // Full Payment Deadline 계산
    let fullPaymentDeadline;
    if (daysUntilDeparture !== null && daysUntilDeparture <= 34) {
        // 34일 이내: +1일 (24시간)
        fullPaymentDeadline = new Date(today);
        fullPaymentDeadline.setDate(fullPaymentDeadline.getDate() + 1);
    } else {
        // 35일 이상: +3일
        fullPaymentDeadline = new Date(today);
        fullPaymentDeadline.setDate(fullPaymentDeadline.getDate() + 3);
    }

    // Down Payment Deadline (Staged인 경우만 사용): +3일
    const downPaymentDeadline = new Date(today);
    downPaymentDeadline.setDate(downPaymentDeadline.getDate() + 3);
    const downPaymentDeadlineEl = document.getElementById('down_payment_deadline_display');
    if (downPaymentDeadlineEl) {
        downPaymentDeadlineEl.textContent = `By ${formatDisplayDate(downPaymentDeadline.toISOString().split('T')[0])}`;
    }

    // Full Payment Deadline 표시
    const fullPaymentDeadlineEl = document.getElementById('full_payment_deadline_display');
    if (fullPaymentDeadlineEl) {
        fullPaymentDeadlineEl.textContent = `By ${formatDisplayDate(fullPaymentDeadline.toISOString().split('T')[0])}`;
    }
}

/**
 * 결제 UI 초기화
 */
function initializePaymentUI() {
    // 탭 버튼에 이벤트 연결
    document.querySelectorAll('.payment-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.paymentType;
            if (type) switchPaymentType(type);
        });
    });
}

/**
 * 파일 업로드 초기화
 */
function initializeFileUpload() {
    // Down Payment 파일 업로드
    const downPaymentFileInput = document.getElementById('down_payment_file_input');
    const downPaymentFileBtn = document.getElementById('down_payment_file_btn');
    const downPaymentFileInfo = document.getElementById('down_payment_file_info');
    const downPaymentFileName = document.getElementById('down_payment_file_name');
    const downPaymentFileRemove = document.getElementById('down_payment_file_remove');

    if (downPaymentFileBtn && downPaymentFileInput) {
        downPaymentFileBtn.addEventListener('click', () => downPaymentFileInput.click());
        downPaymentFileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                downPaymentFile = e.target.files[0];
                if (downPaymentFileName) downPaymentFileName.textContent = downPaymentFile.name;
                if (downPaymentFileInfo) downPaymentFileInfo.style.display = 'block';
            }
        });
    }
    if (downPaymentFileRemove) {
        downPaymentFileRemove.addEventListener('click', () => {
            downPaymentFile = null;
            if (downPaymentFileInput) downPaymentFileInput.value = '';
            if (downPaymentFileInfo) downPaymentFileInfo.style.display = 'none';
        });
    }

    // Full Payment 파일 업로드
    const fullPaymentFileInput = document.getElementById('full_payment_file_input');
    const fullPaymentFileBtn = document.getElementById('full_payment_file_btn');
    const fullPaymentFileInfo = document.getElementById('full_payment_file_info');
    const fullPaymentFileName = document.getElementById('full_payment_file_name');
    const fullPaymentFileRemove = document.getElementById('full_payment_file_remove');

    if (fullPaymentFileBtn && fullPaymentFileInput) {
        fullPaymentFileBtn.addEventListener('click', () => fullPaymentFileInput.click());
        fullPaymentFileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                fullPaymentFile = e.target.files[0];
                if (fullPaymentFileName) fullPaymentFileName.textContent = fullPaymentFile.name;
                if (fullPaymentFileInfo) fullPaymentFileInfo.style.display = 'block';
            }
        });
    }
    if (fullPaymentFileRemove) {
        fullPaymentFileRemove.addEventListener('click', () => {
            fullPaymentFile = null;
            if (fullPaymentFileInput) fullPaymentFileInput.value = '';
            if (fullPaymentFileInfo) fullPaymentFileInfo.style.display = 'none';
        });
    }
}

/**
 * 결제 유형 탭 전환
 */
function switchPaymentType(paymentType) {
    // 비활성화된 탭 클릭 방지
    const targetBtn = document.querySelector(`[data-payment-type="${paymentType}"]`);
    if (targetBtn && targetBtn.disabled) {
        return; // 비활성화된 탭은 전환하지 않음
    }

    selectedPaymentType = paymentType;

    // Update hidden field
    const paymentTypeInput = document.getElementById('payment_type');
    if (paymentTypeInput) paymentTypeInput.value = paymentType;

    // Update tab button states
    document.querySelectorAll('.payment-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`[data-payment-type="${paymentType}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    // Show/hide content sections
    const stagedContent = document.getElementById('staged_payment_content');
    const fullContent = document.getElementById('full_payment_content');

    if (stagedContent) stagedContent.style.display = paymentType === 'staged' ? 'block' : 'none';
    if (fullContent) fullContent.style.display = paymentType === 'full' ? 'block' : 'none';
}

/**
 * 저장 핸들러
 */
async function handleSave() {
    try {
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        // 결제 데이터 수집
        const today = new Date();
        const downPaymentDueDate = new Date(today);
        downPaymentDueDate.setDate(downPaymentDueDate.getDate() + 3);
        const downPaymentDueDateStr = downPaymentDueDate.toISOString().split('T')[0];

        // 계산된 총액 사용 (Visa Fee, Flight Options 포함)
        const totalAmount = parseFloat(currentBookingData?._calculatedTotal || currentBookingData?.totalAmount) || 0;
        const adults = parseInt(currentBookingData?.adults) || 0;
        const children = parseInt(currentBookingData?.children) || 0;
        const travelerCount = adults + children;

        // Visa Fee: 이미 계산된 값 사용 또는 재계산
        let visaFee = parseFloat(currentBookingData?._visaFee) || 0;
        if (visaFee === 0 && Array.isArray(currentBookingData?.travelers)) {
            currentBookingData.travelers.forEach(t => {
                const visaType = String(t.visaType || '').toLowerCase();
                if (visaType === 'group') visaFee += 1500;
                else if (visaType === 'individual') visaFee += 1900;
            });
        }

        const downPaymentAmount = 5000 * travelerCount;
        const secondPaymentAmount = 10000 * travelerCount + visaFee;
        const balanceAmount = totalAmount - downPaymentAmount - secondPaymentAmount;

        const paymentData = {
            action: 'updatePaymentInfo',
            bookingId: currentBookingId,
            paymentType: selectedPaymentType,
            downPaymentAmount: downPaymentAmount,
            downPaymentDueDate: downPaymentDueDateStr,
            advancePaymentAmount: secondPaymentAmount,
            advancePaymentDueDate: null,
            balanceAmount: balanceAmount,
            balanceDueDate: null,
            fullPaymentAmount: totalAmount,
            fullPaymentDueDate: downPaymentDueDateStr
        };

        // FormData 구성 (파일 포함)
        const formData = new FormData();
        formData.append('action', 'updatePaymentInfo');
        formData.append('data', JSON.stringify(paymentData));

        // 파일 첨부
        if (selectedPaymentType === 'staged' && downPaymentFile) {
            formData.append('downPaymentFile', downPaymentFile);
        }
        if (selectedPaymentType === 'full' && fullPaymentFile) {
            formData.append('fullPaymentFile', fullPaymentFile);
        }

        // API 호출
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to update payment info');
        }

        // 예약 완료 플래그 설정 (페이지 이탈 시 삭제 방지)
        isReservationCompleted = true;

        alert('Reservation completed successfully!');
        window.location.href = `reservation-detail.html?id=${currentBookingId}`;

    } catch (error) {
        console.error('Error saving payment info:', error);
        alert('Error: ' + error.message);
    } finally {
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Complete Reservation';
    }
}

/**
 * 뒤로가기 핸들러
 */
function handleBack() {
    if (confirm('Are you sure you want to go back? The reservation will be cancelled.')) {
        // 예약 삭제 후 예약 생성 페이지로 이동
        deleteIncompleteReservation().then(() => {
            window.location.href = 'create-reservation.html';
        }).catch(() => {
            window.location.href = 'create-reservation.html';
        });
    }
}

/**
 * 페이지 이탈 시 미완료 예약 삭제
 */
function handlePageUnload(event) {
    // 예약이 완료된 경우 삭제하지 않음
    if (isReservationCompleted || !currentBookingId) {
        return;
    }

    // navigator.sendBeacon을 사용하여 페이지 이탈 시에도 API 호출 보장
    const data = JSON.stringify({
        action: 'deleteIncompleteReservation',
        bookingId: currentBookingId
    });

    const blob = new Blob([data], { type: 'application/json' });
    navigator.sendBeacon('../backend/api/agent-api.php', blob);
}

/**
 * 미완료 예약 삭제 API 호출
 */
async function deleteIncompleteReservation() {
    if (!currentBookingId) return;

    try {
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'deleteIncompleteReservation',
                bookingId: currentBookingId
            })
        });
        return await response.json();
    } catch (error) {
        console.error('Error deleting incomplete reservation:', error);
    }
}

// ============ 유틸리티 함수 ============

/**
 * 통화 포맷
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US').format(Math.round(amount || 0));
}

/**
 * 날짜 표시 포맷
 */
function formatDisplayDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// 전역에서 접근 가능하도록 노출
window.switchPaymentType = switchPaymentType;
