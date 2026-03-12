/**
 * Reservation Detail Page JavaScript
 * Handles dynamic loading and display of booking details
 */

let currentBooking = null;

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // 뒤로가기 버튼 처리 (reservation-detail.php 전용)
    // button.js보다 먼저 실행되도록 즉시 실행
    if (window.location.pathname.includes('reservation-detail.php')) {
        const backButton = document.querySelector('.btn-mypage');
        if (backButton) {
            // 기존 이벤트 리스너 제거 (중복 방지)
            const newBackButton = backButton.cloneNode(true);
            backButton.parentNode.replaceChild(newBackButton, backButton);
            
            // 새 이벤트 리스너 추가
            newBackButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // button.js의 이벤트 전파 방지
                
                console.log('reservation-detail.php 뒤로가기 버튼 클릭됨');
                history.back();
            }, true); // capture phase에서 실행하여 다른 리스너보다 먼저 실행
        }
    }
    
    initializeReservationDetailPage();
});

// Initialize reservation detail page
async function initializeReservationDetailPage() {
    try {
        // Load server texts for i18n
        if (typeof loadServerTexts === 'function') {
            await loadServerTexts();
        }
        
        // Check authentication (더 관대하게 처리)
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        // 로그인 체크를 더 관대하게 처리 (PHP에서 이미 데이터를 로드했으므로)
        if (!isLoggedIn || !userId) {
            console.warn('로그인 정보가 없지만 PHP에서 로드된 데이터를 사용합니다.');
            // alert('로그인이 필요합니다.');
            // window.location.href = 'login.html';
            // return;
        }

        // Get booking ID from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('id') || urlParams.get('bookingId') || urlParams.get('booking_id');

        console.log('Booking ID from URL:', bookingId);
        console.log('Window bookingInfo:', window.bookingInfo);

        if (!bookingId) {
            console.warn('URL에서 예약 ID를 찾을 수 없습니다. PHP에서 로드된 데이터를 확인합니다.');
            // PHP에서 로드된 데이터가 있으면 사용
            if (window.bookingInfo && window.bookingInfo.bookingId) {
                console.log('PHP에서 로드된 예약 정보를 사용합니다.');
                currentBooking = window.bookingInfo;
                renderBookingDetails(window.bookingInfo);
                return;
            }
            alert('예약 정보를 찾을 수 없습니다.');
            history.back();
            return;
        }

        // reservation-detail.php 는 PHP가 이미 화면을 렌더링합니다.
        // 여기서는 화면을 덮어쓰지 않고, 버튼 링크/동작만 최소 보정합니다.
        if (window.location.pathname.includes('reservation-detail.php')) {
            if (window.bookingInfo && window.bookingInfo.bookingId) {
                currentBooking = window.bookingInfo;
                hydrateDetailActions(window.bookingInfo);
                return;
            }
            // PHP가 bookingInfo를 못 실어준 경우에만 API 렌더링
            await loadBookingDetails(bookingId, userId);
            if (currentBooking) hydrateDetailActions(currentBooking);
            return;
        }

        // 그 외(HTML mock 등)만 기존 렌더링 로직 사용
        if (window.bookingInfo && window.bookingInfo.bookingId === bookingId) {
            console.log('Using PHP-loaded booking info');
            currentBooking = window.bookingInfo;
            renderBookingDetails(window.bookingInfo);
        } else {
            console.log('Loading booking details from API');
            await loadBookingDetails(bookingId, userId);
        }

    } catch (error) {
        console.error('Reservation detail initialization error:', error);
        showErrorMessage('페이지 로드 중 오류가 발생했습니다.');
    }
}

// PHP 렌더링을 유지하면서, 링크/버튼 동작만 보정
function hydrateDetailActions(booking) {
    try {
        updateLinks(booking);
    } catch (e) {
        console.warn('hydrateDetailActions failed:', e);
    }

    // payment datetime placeholder만 최소 치환(있을 때만)
    try {
        const paymentDateTimeElement = document.getElementById('paymentDateTime');
        if (paymentDateTimeElement) {
            const cur = (paymentDateTimeElement.textContent || '').trim().toLowerCase();
            if (cur.includes('loading') || cur.includes('불러')) {
                const paymentDate = booking.createdAt ? new Date(booking.createdAt) : null;
                if (paymentDate && !Number.isNaN(paymentDate.getTime())) {
                    paymentDateTimeElement.textContent = paymentDate.toLocaleString('ko-KR');
                }
            }
        }
    } catch (_) {}
}
// Load booking details from API
async function loadBookingDetails(bookingId, userId) {
    try {
        showLoadingState();

        // Get booking details from user_bookings API
        const response = await fetch(`../backend/api/user_bookings.php?accountId=${userId}&bookingId=${bookingId}`);
        const result = await response.json();

        if (result.success && result.data.bookings && result.data.bookings.length > 0) {
            currentBooking = result.data.bookings[0];
            renderBookingDetails(result.data.bookings[0]);
        } else {
            console.warn('API에서 예약 정보를 찾을 수 없습니다. PHP에서 로드된 데이터를 확인합니다.');
            // API에서 데이터를 찾을 수 없으면 PHP에서 로드된 데이터 사용
            if (window.bookingInfo && window.bookingInfo.bookingId === bookingId) {
                console.log('PHP에서 로드된 예약 정보를 사용합니다.');
                currentBooking = window.bookingInfo;
                renderBookingDetails(window.bookingInfo);
            } else {
                showErrorMessage('예약 정보를 찾을 수 없습니다.');
            }
        }

    } catch (error) {
        console.error('Load booking details error:', error);
        console.warn('API 호출 실패. PHP에서 로드된 데이터를 확인합니다.');
        // API 호출이 실패해도 PHP에서 로드된 데이터가 있으면 사용
        if (window.bookingInfo && window.bookingInfo.bookingId === bookingId) {
            console.log('PHP에서 로드된 예약 정보를 사용합니다.');
            currentBooking = window.bookingInfo;
            renderBookingDetails(window.bookingInfo);
        } else {
            showErrorMessage('예약 정보 로드 중 오류가 발생했습니다.');
        }
    } finally {
        hideLoadingState();
    }
}

// Render booking details on the page
function renderBookingDetails(booking) {
    try {
        console.log('Rendering booking details:', booking);
        
        // Update reservation status
        try {
            // SMT 수정: bookingStatus만으로는 B2B/B2C 상태 문구 요구사항을 만족할 수 없어 booking 전체로 판단
            updateReservationStatus(booking);
        } catch (error) {
            console.error('Error updating reservation status:', error);
        }

        // Update product information
        try {
            updateProductInfo(booking);
        } catch (error) {
            console.error('Error updating product info:', error);
        }

        // Update reservation information
        try {
            updateReservationInfo(booking);
        } catch (error) {
            console.error('Error updating reservation info:', error);
        }

        // Update booker information
        try {
            updateBookerInfo(booking);
        } catch (error) {
            console.error('Error updating booker info:', error);
        }

        // Update travelers information
        try {
            updateTravelersInfo(booking);
        } catch (error) {
            console.error('Error updating travelers info:', error);
        }

        // Update payment information
        try {
            updatePaymentInfo(booking);
        } catch (error) {
            console.error('Error updating payment info:', error);
        }

        // Update guide information
        try {
            updateGuideInfo(booking);
        } catch (error) {
            console.error('Error updating guide info:', error);
        }

        // Update links
        try {
            updateLinks(booking);
        } catch (error) {
            console.error('Error updating links:', error);
        }

        console.log('Booking details rendering completed successfully');

    } catch (error) {
        console.error('Render booking details error:', error);
        // 에러가 발생해도 페이지는 계속 작동하도록 함
        console.warn('일부 정보 업데이트에 실패했지만 페이지는 계속 작동합니다.');
    }
}

// Update reservation status display
function updateReservationStatus(status) {
    const statusElement = document.getElementById('bookingStatus');
    if (statusElement) {
        statusElement.className = 'label';
        // SMT 수정: selectedLanguage 사용(기존 language 키 혼재 보정)
        const currentLang = window.currentLang || localStorage.getItem('selectedLanguage') || 'en';

        const booking = (status && typeof status === 'object') ? status : { bookingStatus: status };
        const bs = String(booking.bookingStatus || '').toLowerCase();
        const ps = String(booking.paymentStatus || '').toLowerCase();

        // B2B/B2C 판별: accountType 기반
        // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B
        // - accountType IN ('guest', 'guide', 'cs', '') → B2C
        const accountType = String(localStorage.getItem('accountType') || '').toLowerCase();
        const isB2B = accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr';

        // 상태키 결정(요구사항)
        let key = '';
        if (['temporary_save', 'temporary', 'draft', 'temp', 'saved', 'saved_draft'].includes(bs)) {
            key = 'temporary_save';
        } else if (bs === 'refunded' || bs === 'refund_completed' || ps === 'refunded') {
            key = 'refund_completed';
        } else if (bs === 'completed') {
            key = 'travel_completed';
        } else if (bs === 'cancelled' || bs === 'canceled' || ps === 'failed') {
            key = 'canceled';
        } else if (bs === 'pending') {
            key = isB2B ? 'pending' : 'payment_suspended';
        } else if (bs === 'confirmed') {
            // B2C 요구사항: 예약 직후(입금 전)는 Payment Suspended로 분류
            if (ps === 'paid') key = isB2B ? 'confirmed' : 'completed';
            else if (ps === 'pending' || ps === 'partial' || ps === 'unpaid' || ps === '' || ps === 'null') key = isB2B ? 'confirmed' : 'payment_suspended';
            else key = isB2B ? 'confirmed' : 'payment_suspended';
        } else if (ps === 'paid') {
            key = isB2B ? 'confirmed' : 'completed';
        } else if (ps === 'pending' || ps === 'partial') {
            key = isB2B ? 'pending' : 'payment_suspended';
        } else {
            key = bs || ps || '';
        }

        // 뱃지 스타일 + 문구
        const labelMapB2B = {
            pending: 'Reservation pending',
            confirmed: 'Reservation confirmed',
            canceled: 'Reservation canceled',
            refund_completed: 'Refund completed',
            travel_completed: 'Travel completed',
            temporary_save: 'Temporary save'
        };
        const labelMapB2C = {
            payment_suspended: 'Payment Suspended',
            completed: 'Payment Completed',
            canceled: 'Payment Canceled',
            refund_completed: 'Refund Completed',
            travel_completed: 'Trip Completed',
            temporary_save: 'Temporary save'
        };
        const text = isB2B ? (labelMapB2B[key] || 'Reservation confirmed') : (labelMapB2C[key] || 'Payment Completed');

        // color class
        if (key === 'payment_suspended' || key === 'pending') statusElement.classList.add('secondary');
        else if (key === 'completed' || key === 'confirmed') statusElement.classList.add('primary');
        else if (key === 'canceled') statusElement.classList.add('danger');
        else statusElement.classList.add('secondary');

        statusElement.textContent = text;
    }
}

// Get i18n text (uses globalLanguageTexts if available)
function getI18nText(key, lang) {
    if (window.globalLanguageTexts && window.globalLanguageTexts[lang] && window.globalLanguageTexts[lang][key]) {
        return window.globalLanguageTexts[lang][key];
    }
    // Fallback to Korean
    if (window.globalLanguageTexts && window.globalLanguageTexts['ko'] && window.globalLanguageTexts['ko'][key]) {
        return window.globalLanguageTexts['ko'][key];
    }
    // Final fallback
    return key;
}

// Update product information
function updateProductInfo(booking) {
    try {
        console.log('Updating product info:', booking);
        
        // Update product name
        const productNameElement = document.getElementById('productName');
        if (productNameElement) {
            const productName = booking.productName || booking.productNameEn || booking.packageName || '패키지명 없음';
            productNameElement.textContent = productName;
            console.log('Product name updated:', productName);
        }

        // Update product price
        const productPriceElement = document.getElementById('productPrice');
        if (productPriceElement) {
            try {
                const price = formatPrice(booking.totalAmount || 0);
                productPriceElement.textContent = price;
                console.log('Product price updated:', price);
            } catch (error) {
                console.error('Error formatting price:', error);
                productPriceElement.textContent = '₱0';
            }
        }

        // Update product image (product_images JSON / path normalize)
        const productImageElement = document.getElementById('productImage');
        if (productImageElement) {
            const raw = booking.thumbnail || booking.imageUrl || booking.packageImage || '';
            let src = '';
            try {
                if (typeof raw === 'string' && raw.trim().startsWith('[')) {
                    const arr = JSON.parse(raw);
                    if (Array.isArray(arr) && arr[0]) src = String(arr[0] || '');
                }
            } catch (_) {}
            if (!src) src = String(raw || '').trim();
            if (src && !(src.startsWith('http://') || src.startsWith('https://') || src.startsWith('/'))) {
                src = '../uploads/products/' + src;
            } else if (src.startsWith('/uploads/')) {
                src = '..' + src;
            }
            if (src) {
                productImageElement.src = src;
            }
            productImageElement.alt = booking.productName || booking.packageName || '';
            console.log('Product image updated');
        }

        // Update product link
        const productLinkElement = document.getElementById('productLink');
        if (productLinkElement && booking.packageId) {
            productLinkElement.href = `product-detail.php?id=${booking.packageId}`;
            console.log('Product link updated');
        }

        // Update trip dates
        const tripDatesElement = document.getElementById('tripDates');
        if (tripDatesElement && booking.departureDate) {
            try {
                const departureDate = new Date(booking.departureDate);
                const duration = 5; // 기본값 사용
                const endDate = new Date(departureDate.getTime() + (duration - 1) * 24 * 60 * 60 * 1000);
                const formatDate = (date) => date.toISOString().split('T')[0];
                tripDatesElement.textContent = `${formatDate(departureDate)} - ${formatDate(endDate)} (${duration - 1}N${duration}D)`;
                console.log('Trip dates updated');
            } catch (error) {
                console.error('Error updating trip dates:', error);
                tripDatesElement.textContent = '여행 일정을 불러오는 중...';
            }
        }
        
        console.log('Product info update completed');
    } catch (error) {
        console.error('Error in updateProductInfo:', error);
        throw error;
    }
}

// Update reservation information
function updateReservationInfo(booking) {
    try {
        console.log('Updating reservation info:', booking);
        
        // Update reservation number
        const reservationNoElement = document.getElementById('reservationNo');
        if (reservationNoElement) {
            reservationNoElement.textContent = booking.bookingId || '예약번호 없음';
        }

        // Update reservation status
        const reservationStatusElement = document.getElementById('reservationStatus');
        if (reservationStatusElement) {
            try {
                reservationStatusElement.textContent = getStatusText(booking);
            } catch (error) {
                console.error('Error getting status text:', error);
                reservationStatusElement.textContent = booking.bookingStatus || '상태 없음';
            }
        }

        // Update guests information
        try {
            updateGuestsInfo(booking);
        } catch (error) {
            console.error('Error updating guests info:', error);
        }

        // Update room information
        try {
            updateRoomInfo(booking);
        } catch (error) {
            console.error('Error updating room info:', error);
        }

        // Update additional options
        try {
            updateAdditionalOptions(booking);
        } catch (error) {
            console.error('Error updating additional options:', error);
        }

        // Update total price
        const totalPriceElement = document.getElementById('totalPrice');
        if (totalPriceElement) {
            try {
                totalPriceElement.textContent = formatPrice(booking.totalAmount || 0);
            } catch (error) {
                console.error('Error formatting total price:', error);
                totalPriceElement.textContent = '₱0';
            }
        }
        
        console.log('Reservation info update completed');
    } catch (error) {
        console.error('Error in updateReservationInfo:', error);
        throw error;
    }
}

// Update guests information
function updateGuestsInfo(booking) {
    const guestsInfoElement = document.getElementById('guestsInfo');
    if (!guestsInfoElement) return;

    const adults = booking.adults || 0;
    const children = booking.children || 0;
    const infants = booking.infants || 0;
    const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';

    let guestsHtml = '';

    if (adults > 0) {
        guestsHtml += `
            <div class="align both vm mt4">
                <p class="text fz14 fw400 lh22 black12">${getI18nText('adult', currentLang)}x${adults}</p>
                <span class="text fz14 fw400 lh22 black12">₱${(booking.totalAmount * 0.7).toLocaleString()}</span>
            </div>
        `;
    }

    if (children > 0) {
        guestsHtml += `
            <div class="align both vm mt8">
                <p class="text fz14 fw400 lh22 black12">${getI18nText('child', currentLang)}x${children}</p>
                <span class="text fz14 fw400 lh22 black12">₱${(booking.totalAmount * 0.2).toLocaleString()}</span>
            </div>
        `;
    }

    if (infants > 0) {
        guestsHtml += `
            <div class="align both vm mt8">
                <p class="text fz14 fw400 lh22 black12">${getI18nText('infant', currentLang)}x${infants}</p>
                <span class="text fz14 fw400 lh22 black12">₱0</span>
            </div>
        `;
    }

    guestsInfoElement.innerHTML = guestsHtml;
}

// Update room information
function updateRoomInfo(booking) {
    const roomInfoElement = document.getElementById('roomInfo');
    if (!roomInfoElement) return;

    const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';
    
    // 기본 룸 옵션 표시 (실제 데이터가 있다면 수정)
    roomInfoElement.innerHTML = `
        <div class="align both vm mt4">
            <p class="text fz14 fw400 lh22 black12">${getI18nText('standard_room', currentLang)}x1</p>
            <span class="text fz14 fw400 lh22 black12">₱0</span>
        </div>
    `;
}

// Update additional options
function updateAdditionalOptions(booking) {
    try {
        console.log('Updating additional options:', booking);
        
        // Parse selectedOptions if it's a string
        let additionalOptions = {};
        if (booking.selectedOptions) {
            try {
                additionalOptions = typeof booking.selectedOptions === 'string' 
                    ? JSON.parse(booking.selectedOptions) 
                    : booking.selectedOptions;
            } catch (error) {
                console.error('Error parsing selectedOptions:', error);
                additionalOptions = {};
            }
        }

        // Update extra baggage
        updateSingleOption('Extra Baggage', additionalOptions, 'baggage');

        // Update breakfast request
        updateSingleOption('Breakfast Request', additionalOptions, 'breakfast');

        // Update Wi-Fi rental
        updateSingleOption('Wi-Fi Rental', additionalOptions, 'wifi');

        // Update seat preference (from selectedOptions)
        updateTextOption('Seat Preference', additionalOptions, 'seatPreference');

        // Update other requests (from booking.specialRequests primarily)
        const otherRequestsElement = document.getElementById('otherRequests');
        if (otherRequestsElement) {
            const v =
                (booking && typeof booking.specialRequests === 'string' && booking.specialRequests.trim()) ||
                (additionalOptions && additionalOptions.otherRequests && (additionalOptions.otherRequests.value || additionalOptions.otherRequests)) ||
                (additionalOptions && additionalOptions.specialRequests && (additionalOptions.specialRequests.value || additionalOptions.specialRequests)) ||
                '';
            if (v) otherRequestsElement.textContent = String(v);
        }
        
        console.log('Additional options update completed');
    } catch (error) {
        console.error('Error in updateAdditionalOptions:', error);
        throw error;
    }
}

// Update guest information
function updateGuestInfo(booking) {
    const guestContainer = document.querySelector('.align.both.vm.mt4');
    if (!guestContainer) return;

    // Clear existing guest info
    let nextElement = guestContainer;
    while (nextElement && nextElement.classList.contains('align') && nextElement.classList.contains('both')) {
        const toRemove = nextElement;
        nextElement = nextElement.nextElementSibling;
        if (toRemove.querySelector('.text.fz14.fw400.lh22.black12') &&
            (toRemove.textContent.includes('성인') || toRemove.textContent.includes('아동'))) {
            toRemove.remove();
        }
    }

    // Add guest information
    const adults = booking.adults || 0;
    const children = booking.children || 0;
    const packagePrice = booking.packagePrice || 0;

    if (adults > 0) {
        const adultElement = createGuestElement(`성인x${adults}`, formatPrice(packagePrice * adults));
        guestContainer.parentNode.insertBefore(adultElement, guestContainer.nextSibling);
    }

    if (children > 0) {
        const childElement = createGuestElement(`아동x${children}`, formatPrice(Math.max(packagePrice - 5000, 0) * children));
        const lastGuestElement = document.querySelector('.align.both.vm.mt8') || guestContainer;
        lastGuestElement.parentNode.insertBefore(childElement, lastGuestElement.nextSibling);
    }
}

// Create guest element
function createGuestElement(guestText, priceText) {
    const element = document.createElement('div');
    element.className = 'align both vm mt8';
    element.innerHTML = `
        <p class="text fz14 fw400 lh22 black12">${guestText}</p>
        <span class="text fz14 fw400 lh22 black12">${priceText}</span>
    `;
    return element;
}

// Update room options
function updateRoomOptions(roomOptions) {
    const roomSection = document.querySelector('.text.fz14.fw600.lh22.gray96.mt20');
    if (!roomSection || !roomSection.textContent.includes('Room Option')) return;

    // Clear existing room options
    let nextElement = roomSection.nextElementSibling;
    while (nextElement && nextElement.classList.contains('align') && nextElement.classList.contains('both')) {
        const toRemove = nextElement;
        nextElement = nextElement.nextElementSibling;
        toRemove.remove();
    }

    // Add room options
    roomOptions.forEach(room => {
        const roomElement = createOptionElement(
            `${room.name}x${room.quantity}`,
            formatPrice(room.price * room.quantity)
        );
        roomSection.parentNode.insertBefore(roomElement, roomSection.nextSibling);
    });
}

// Update single option
function updateSingleOption(labelText, options, optionKey) {
    try {
        const labelElement = Array.from(document.querySelectorAll('.text.fz14.fw600.lh22.gray96.mt20'))
            .find(el => el.textContent.includes(labelText));

        if (labelElement) {
            const valueElement = labelElement.nextElementSibling;
            if (valueElement) {
                // Language policy: English default, only en/tl supported
                let currentLang = String(window.currentLang || localStorage.getItem('selectedLanguage') || 'en').toLowerCase();
                if (currentLang !== 'en' && currentLang !== 'tl') currentLang = 'en';
                // options가 객체인 경우 처리
                let optionValue = getI18nText('not_selected', currentLang);
                
                if (Array.isArray(options)) {
                    // 배열인 경우
                    const option = options.find(opt => opt.type === optionKey);
                    optionValue = option ? (option.selected ? getI18nText('apply', currentLang) : getI18nText('not_selected', currentLang)) : getI18nText('not_selected', currentLang);
                } else if (options && typeof options === 'object') {
                    // 객체인 경우
                    if (options[optionKey]) {
                        optionValue = options[optionKey].selected ? getI18nText('apply', currentLang) : getI18nText('not_selected', currentLang);
                    }
                }
                
                valueElement.textContent = optionValue;
                console.log(`Updated ${labelText}: ${optionValue}`);
            }
        }
    } catch (error) {
        console.error(`Error updating single option ${labelText}:`, error);
    }
}

// Update text option
function updateTextOption(labelText, options, optionKey) {
    try {
        const labelElement = Array.from(document.querySelectorAll('.text.fz14.fw600.lh22.gray96.mt20'))
            .find(el => el.textContent.includes(labelText));

        if (labelElement) {
            const valueElement = labelElement.nextElementSibling;
            if (valueElement) {
                // options가 객체인 경우 처리
                // Language policy: English default, only en/tl supported
                let currentLang = String(window.currentLang || localStorage.getItem('selectedLanguage') || 'en').toLowerCase();
                if (currentLang !== 'en' && currentLang !== 'tl') currentLang = 'en';
                let optionValue = getI18nText('no_requests', currentLang) || 'No requests';
                
                if (Array.isArray(options)) {
                    // 배열인 경우
                    const option = options.find(opt => opt.type === optionKey);
                    optionValue = option ? (option.value || optionValue) : optionValue;
                } else if (options && typeof options === 'object') {
                    // 객체인 경우
                    if (options[optionKey]) {
                        optionValue = options[optionKey].value || options[optionKey] || optionValue;
                    }
                }
                
                valueElement.textContent = optionValue;
                console.log(`Updated text option ${labelText}: ${optionValue}`);
            }
        }
    } catch (error) {
        console.error(`Error updating text option ${labelText}:`, error);
    }
}

// Create option element
function createOptionElement(optionText, priceText) {
    const element = document.createElement('div');
    element.className = 'align both vm mt4';
    element.innerHTML = `
        <p class="text fz14 fw400 lh22 black12">${optionText}</p>
        <span class="text fz14 fw400 lh22 black12">${priceText}</span>
    `;
    return element;
}

// Update total price
function updateTotalPrice(totalAmount) {
    const totalElements = Array.from(document.querySelectorAll('.text.fz14.fw400.lh22.black12.mt4'))
        .filter(el => el.textContent.includes('₱'));

    const totalElement = totalElements[totalElements.length - 1];
    if (totalElement && totalAmount) {
        totalElement.textContent = formatPrice(totalAmount);
    }
}

// Update booker information
function updateBookerInfo(booking) {
    const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';
    
    const bookerNameElement = document.getElementById('bookerName');
    if (bookerNameElement) {
        const fullName = `${booking.fName || ''} ${booking.lName || ''}`.trim();
        bookerNameElement.textContent = fullName || getI18nText('no_booker_name', currentLang);
    }

    const bookerEmailElement = document.getElementById('bookerEmail');
    if (bookerEmailElement) {
        bookerEmailElement.textContent = booking.emailAddress || getI18nText('no_email', currentLang);
    }

    const bookerPhoneElement = document.getElementById('bookerPhone');
    if (bookerPhoneElement) {
        bookerPhoneElement.textContent = booking.contactNo || getI18nText('no_contact', currentLang);
    }
}

// Update travelers information
function updateTravelersInfo(booking) {
    const travelersInfoElement = document.getElementById('travelersInfo');
    if (!travelersInfoElement) return;

    const adults = booking.adults || 0;
    const children = booking.children || 0;
    const infants = booking.infants || 0;
    const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';

    let travelersHtml = '';

    // 성인 여행자
    for (let i = 1; i <= adults; i++) {
        travelersHtml += `
            <a href="traveler-info-detail.html?bookingId=${booking.bookingId}&type=adult&index=${i}" class="align both vm py12">
                <div class="align vm">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('adult', currentLang)} ${i}</div>
                    ${i === 1 ? `<span class="label green ml12">${getI18nText('main_traveler', currentLang)}</span>` : ''}
                </div>
                <img src="../images/ico_arrow_right_black.svg" alt="">
            </a>
        `;
    }

    // 아동 여행자
    for (let i = 1; i <= children; i++) {
        travelersHtml += `
            <a href="traveler-info-detail.html?bookingId=${booking.bookingId}&type=child&index=${i}" class="align both vm py12">
                <div class="align vm">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('child', currentLang)} ${i}</div>
                </div>
                <img src="../images/ico_arrow_right_black.svg" alt="">
            </a>
        `;
    }

    // 유아 여행자
    for (let i = 1; i <= infants; i++) {
        travelersHtml += `
            <a href="traveler-info-detail.html?bookingId=${booking.bookingId}&type=infant&index=${i}" class="align both vm py12">
                <div class="align vm">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('infant', currentLang)} ${i}</div>
                </div>
                <img src="../images/ico_arrow_right_black.svg" alt="">
            </a>
        `;
    }

    travelersInfoElement.innerHTML = travelersHtml;
}

// Update payment information
function updatePaymentInfo(booking) {
    const paymentMethodElement = document.getElementById('paymentMethod');
    if (paymentMethodElement) {
        const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';
        paymentMethodElement.textContent = booking.paymentMethod || getI18nText('credit_card', currentLang);
    }

    const paymentDateTimeElement = document.getElementById('paymentDateTime');
    if (paymentDateTimeElement) {
        const paymentDate = booking.createdAt ? new Date(booking.createdAt) : new Date();
        paymentDateTimeElement.textContent = paymentDate.toLocaleString('ko-KR');
    }

    const orderAmountElement = document.getElementById('orderAmount');
    if (orderAmountElement) {
        orderAmountElement.textContent = formatPrice(booking.totalAmount || 0);
    }

    const totalAmountPaidElement = document.getElementById('totalAmountPaid');
    if (totalAmountPaidElement) {
        totalAmountPaidElement.textContent = formatPrice(booking.totalAmount || 0);
    }
}

// Update guide information
function updateGuideInfo(booking) {
    const currentLang = window.currentLang || localStorage.getItem('language') || 'ko';
    
    const guideNameElement = document.getElementById('guideName');
    if (guideNameElement) {
        guideNameElement.textContent = booking.guideName || getI18nText('guide_info_unavailable', currentLang);
    }
    
    const guideContactElement = document.getElementById('guideContact');
    if (guideContactElement) {
        guideContactElement.textContent = booking.guidePhone || getI18nText('no_contact', currentLang);
    }

    const guidePhoneElement = document.getElementById('guidePhone');
    if (guidePhoneElement) {
        guidePhoneElement.textContent = booking.guidePhone || getI18nText('no_contact', currentLang);
    }

    const guideAboutElement = document.getElementById('guideAbout');
    if (guideAboutElement) {
        guideAboutElement.textContent = booking.guideAbout || getI18nText('guide_intro_unavailable', currentLang);
    }

    const guideImageElement = document.getElementById('guideImage');
    if (guideImageElement && booking.guideImage) {
        guideImageElement.src = booking.guideImage;
    }
}

// Update links
function updateLinks(booking) {
    const scheduleLinkElement = document.getElementById('scheduleLink');
    if (scheduleLinkElement) {
        // 요구사항: 여행 일정 섹션의 "일정 보러가기" → 상품 상세 페이지의 일정표 탭
        if (booking.packageId) {
            scheduleLinkElement.href = `product-detail.php?id=${encodeURIComponent(booking.packageId)}&tab=schedule`;
        }
    }

    const guideLocationLinkElement = document.getElementById('guideLocationLink');
    if (guideLocationLinkElement) {
        // 요구사항: 해당 예약 건의 "해당 날짜" 가이드 위치 페이지
        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        const date = `${y}-${m}-${d}`;
        guideLocationLinkElement.href = `guide-location.php?booking_id=${encodeURIComponent(booking.bookingId)}&date=${encodeURIComponent(date)}`;
    }

    const cancellationLinkElement = document.getElementById('cancellationLink');
    if (cancellationLinkElement) {
        // PHP 템플릿에 이미 링크가 있으므로, 여기서는 덮어쓰지 않습니다.
    }
}

// Format price for display
function formatPrice(price) {
    if (typeof price === 'string') {
        price = parseFloat(price.replace(/[^\d.-]/g, ''));
    }

    if (isNaN(price)) return '₱0';

    return `₱${price.toLocaleString()}`;
}

// Get status text
function getStatusText(bookingOrStatus) {
    // SMT 수정: 예약상세의 상태 텍스트도 예약내역과 동일한 규칙을 사용 (DOM을 건드리지 않고 계산)
    const booking = (bookingOrStatus && typeof bookingOrStatus === 'object')
        ? bookingOrStatus
        : { bookingStatus: bookingOrStatus };

    const bs = String(booking.bookingStatus || '').toLowerCase();
    const ps = String(booking.paymentStatus || '').toLowerCase();

    // B2B = 에이전트 계정 자체, B2C = 모든 일반 고객 (에이전트 소속 여부 무관)
    const accountType = (localStorage.getItem('accountType') || '').toString().toLowerCase();
    const isB2B = accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr';

    let key = '';
    if (['temporary_save', 'temporary', 'draft', 'temp', 'saved', 'saved_draft'].includes(bs)) key = 'temporary_save';
    else if (bs === 'refunded' || bs === 'refund_completed' || ps === 'refunded') key = 'refund_completed';
    else if (bs === 'completed') key = 'travel_completed';
    else if (bs === 'cancelled' || bs === 'canceled' || ps === 'failed') key = 'canceled';
    else if (bs === 'pending') key = isB2B ? 'pending' : 'payment_suspended';
    else if (bs === 'confirmed') {
        if (ps === 'pending' || ps === 'partial') key = isB2B ? 'pending' : 'payment_suspended';
        else if (ps === 'paid') key = isB2B ? 'confirmed' : 'completed';
        else key = isB2B ? 'confirmed' : 'completed';
    } else if (ps === 'paid') key = isB2B ? 'confirmed' : 'completed';
    else if (ps === 'pending' || ps === 'partial') key = isB2B ? 'pending' : 'payment_suspended';
    else key = bs || ps || '';

    const labelMapB2B = {
        pending: 'Reservation pending',
        confirmed: 'Reservation confirmed',
        canceled: 'Reservation canceled',
        refund_completed: 'Refund completed',
        travel_completed: 'Travel completed',
        temporary_save: 'Temporary save'
    };
    const labelMapB2C = {
        payment_suspended: 'Payment Suspended',
        completed: 'Payment Completed',
        canceled: 'Payment Canceled',
        refund_completed: 'Refund Completed',
        travel_completed: 'Trip Completed',
        temporary_save: 'Temporary save'
    };
    return isB2B ? (labelMapB2B[key] || 'Reservation confirmed') : (labelMapB2C[key] || 'Payment Completed');
}

// Show loading state
function showLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '0.6';
        mainContent.style.pointerEvents = 'none';
    }
}

// Hide loading state
function hideLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '1';
        mainContent.style.pointerEvents = 'auto';
    }
}

// Show error message
function showErrorMessage(message) {
    alert(message);
}

// Export functions for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadBookingDetails,
        renderBookingDetails,
        formatPrice
    };
}