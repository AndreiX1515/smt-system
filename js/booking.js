//   API

// B2B 사용자 여부 확인 (agent/admin_ph/admin_kr = B2B, 나머지 = B2C)
function isB2BUser() {
    const accountType = String(localStorage.getItem('accountType') || '').toLowerCase();
    const clientType = String(localStorage.getItem('clientType') || '').toLowerCase();
    // agent, admin_ph, admin_kr 또는 wholeseller 타입은 B2B
    return accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr' || clientType === 'wholeseller';
}

//
async function createBooking(bookingData) {
    try {
        const response = await fetch('../backend/api/booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_booking',
                ...bookingData
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Booking creation error:', error);
        return { success: false, message: '  .' };
    }
}

//    
async function getUserBookings(userId) {
    try {
        const response = await fetch('../backend/api/booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_user_bookings',
                user_id: userId
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Get user bookings error:', error);
        return { success: false, message: '  .' };
    }
}

//    
async function getBookingDetails(bookingId, userId) {
    try {
        const response = await fetch('../backend/api/booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_booking_details',
                booking_id: bookingId,
                user_id: userId
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Get booking details error:', error);
        return { success: false, message: '  .' };
    }
}

//  
async function cancelBooking(bookingId, userId) {
    try {
        const response = await fetch('../backend/api/booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'cancel_booking',
                booking_id: bookingId,
                user_id: userId
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Cancel booking error:', error);
        return { success: false, message: '  .' };
    }
}

//
function collectBookingData() {
    const urlParams = new URLSearchParams(window.location.search);
    const packageId = urlParams.get('package_id');
    const departureDate = urlParams.get('departure_date');

    //
    const userId = localStorage.getItem('userId');
    if (!userId) {
        alert(' .');
        location.href = 'login.html';
        return null;
    }

    //    ( )
    const adults = parseInt(document.querySelector('.counter:nth-child(1) .count-value')?.textContent || '1');
    const children = parseInt(document.querySelector('.counter:nth-child(2) .count-value')?.textContent || '0');
    const infants = parseInt(document.querySelector('.counter:nth-child(3) .count-value')?.textContent || '0');

    //   (   )
    const roomOption = document.querySelector('input[name="room"]:checked')?.value || 'standard';

    //    (  )
    const basePrice = 34000;
    const totalAmount = (adults * basePrice) + (children * basePrice * 0.8) + (infants * basePrice * 0.1);

    // B2B/B2C 가격 티어 설정
    const priceTier = isB2BUser() ? 'B2B' : 'B2C';

    return {
        user_id: userId,
        package_id: packageId,
        departure_date: departureDate,
        adults: adults,
        children: children,
        infants: infants,
        room_option: roomOption,
        total_amount: Math.round(totalAmount),
        price_tier: priceTier
    };
}

//   
async function handleBooking() {
    const bookingData = collectBookingData();
    if (!bookingData) return;
    
    //  
    const bookingBtn = document.getElementById('bookingBtn');
    if (bookingBtn) {
        bookingBtn.disabled = true;
        bookingBtn.textContent = ' ...';
    }
    
    try {
        const result = await createBooking(bookingData);
        
        if (result.success) {
            alert(' !');
            //    
            location.href = `reservation-completed.php?booking_id=${result.booking.booking_id}`;
        } else {
            alert(result.message || ' .');
        }
        
    } catch (error) {
        console.error('Booking error:', error);
        alert('    .');
    } finally {
        //  
        if (bookingBtn) {
            bookingBtn.disabled = false;
            bookingBtn.textContent = '';
        }
    }
}

//   
async function renderUserBookings() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        location.href = 'login.html';
        return;
    }
    
    const container = document.querySelector('.booking-list');
    if (!container) return;
    
    try {
        //  
        container.innerHTML = '<div class="text-center">   ...</div>';
        
        const result = await getUserBookings(userId);
        
        if (result.success && result.data.length > 0) {
            const bookingsHtml = result.data.map(booking => createBookingCard(booking)).join('');
            container.innerHTML = bookingsHtml;
        } else {
            container.innerHTML = '<div class="text-center">  .</div>';
        }
        
    } catch (error) {
        console.error('Render bookings error:', error);
        container.innerHTML = '<div class="text-center">   .</div>';
    }
}

//   HTML 
function createBookingCard(booking) {
    const statusText = getBookingStatusText(booking.bookingStatus);
    const statusClass = getBookingStatusClass(booking.bookingStatus);
    const price = new Intl.NumberFormat('ko-KR').format(booking.totalAmount);
    
    return `
        <li onclick="location.href='reservation-detail.php?id=${booking.bookingId}'">
            <div class="card-type2">
                <div class="info">
                    <div class="text fz14 fw500 lh22">${booking.packageName}</div>
                    <div class="text fz12 fw400 lh18 gray8 mt4">${booking.departureDate}</div>
                    <div class="price mt8">₱ ${price}</div>
                </div>
                <div class="status">
                    <span class="label ${statusClass}">${statusText}</span>
                </div>
            </div>
        </li>
    `;
}

//    
function getBookingStatusText(status) {
    const statusMap = {
        'pending': ' ',
        'confirmed': ' ',
        'cancelled': ' ',
        'completed': ' '
    };
    return statusMap[status] || status;
}

//   CSS  
function getBookingStatusClass(status) {
    const classMap = {
        'pending': 'warning',
        'confirmed': 'success',
        'cancelled': 'danger',
        'completed': 'secondary'
    };
    return classMap[status] || 'secondary';
}

//    
document.addEventListener('DOMContentLoaded', function() {
    //      
    if (window.location.pathname.includes('reservation-history.php')) {
        renderUserBookings();
    }
    
    //      
    if (window.location.pathname.includes('reservation-detail.php')) {
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('id');
        if (bookingId) {
            loadBookingDetail(bookingId);
        }
    }
});

//    
async function loadBookingDetail(bookingId) {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        location.href = 'login.html';
        return;
    }
    
    try {
        const result = await getBookingDetails(bookingId, userId);
        
        if (result.success) {
            renderBookingDetail(result.data);
        } else {
            alert('    .');
            history.back();
        }
        
    } catch (error) {
        console.error('Load booking detail error:', error);
        alert('     .');
    }
}

//    
function renderBookingDetail(booking) {
    // 
    const packageNameEl = document.querySelector('.package-name');
    if (packageNameEl) packageNameEl.textContent = booking.packageName;
    
    // 
    const departureDateEl = document.querySelector('.departure-date');
    if (departureDateEl) departureDateEl.textContent = booking.departureDate;
    
    // 
    const guestCountEl = document.querySelector('.guest-count');
    if (guestCountEl) guestCountEl.textContent = ` ${booking.adults},  ${booking.children},  ${booking.infants}`;
    
    //  
    const totalAmountEl = document.querySelector('.total-amount');
    if (totalAmountEl) totalAmountEl.textContent = '₱ ' + new Intl.NumberFormat('ko-KR').format(booking.totalAmount);
    
    //  
    const statusEl = document.querySelector('.booking-status');
    if (statusEl) {
        statusEl.textContent = getBookingStatusText(booking.bookingStatus);
        statusEl.className = `label ${getBookingStatusClass(booking.bookingStatus)}`;
    }
}