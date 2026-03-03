/**
 * Reservation Cancellation Page JavaScript
 * Handles display of cancelled booking details and refund information
 */

let currentCancelledBooking = null;

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCancellationPage();
});

// Initialize cancellation page
async function initializeCancellationPage() {
    try {
        // Check authentication
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            alert(' .');
            window.location.href = 'login.html';
            return;
        }

        // Get booking ID from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('bookingId') || urlParams.get('booking_id');

        if (!bookingId) {
            alert('    .');
            history.back();
            return;
        }

        // Load cancelled booking details
        await loadCancelledBookingDetails(bookingId, userId);

    } catch (error) {
        console.error('Cancellation page initialization error:', error);
        showErrorMessage('    .');
    }
}

// Load cancelled booking details
async function loadCancelledBookingDetails(bookingId, userId) {
    try {
        showLoadingState();

        // Use API client if available, otherwise direct fetch
        let result;
        if (window.api && window.api.getBooking) {
            result = await window.api.getBooking(bookingId, userId);
        } else {
            const response = await fetch('../backend/api/booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_booking',
                    bookingId: bookingId,
                    accountId: userId
                })
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            currentCancelledBooking = result.data;

            // Verify booking is actually cancelled
            if (result.data.bookingStatus !== 'cancelled') {
                showErrorMessage('   .');
                history.back();
                return;
            }

            renderCancelledBookingDetails(result.data);
        } else {
            showErrorMessage(result.message || '     .');
        }

    } catch (error) {
        console.error('Load cancelled booking details error:', error);
        showErrorMessage('     .');
    } finally {
        hideLoadingState();
    }
}

// Render cancelled booking details
function renderCancelledBookingDetails(booking) {
    try {
        // Update product information
        updateProductInfo(booking);

        // Update reservation information
        updateReservationInfo(booking);

        // Update booker information
        updateBookerInfo(booking);

        // Update travelers information
        updateTravelersInfo(booking.travelers || []);

        // Calculate and display refund information
        calculateAndDisplayRefund(booking);

    } catch (error) {
        console.error('Render cancelled booking details error:', error);
        showErrorMessage('     .');
    }
}

// Update product information (reuse from reservation-detail.js)
function updateProductInfo(booking) {
    // Update product title
    const titleElement = document.querySelector('.ellipsis1');
    if (titleElement && booking.packageName) {
        titleElement.textContent = booking.packageName;
    }

    // Update product price
    const priceElement = titleElement?.parentNode.querySelector('.text.fz14.fw600.lh22.black12');
    if (priceElement && booking.packagePrice) {
        priceElement.textContent = formatPrice(booking.packagePrice);
    }

    // Update product image
    const imageElement = document.querySelector('img[src*="@img_thumbnail.png"]');
    if (imageElement && booking.packageImage) {
        imageElement.src = booking.packageImage;
        imageElement.alt = booking.packageName || '';
    }

    // Update trip dates
    const tripDatesElement = document.querySelector('.text.fz14.fw400.lh22.black12.mt4');
    if (tripDatesElement && booking.departureDate) {
        const departureDate = new Date(booking.departureDate);
        const duration = booking.duration_days || 5;
        const endDate = new Date(departureDate.getTime() + (duration - 1) * 24 * 60 * 60 * 1000);

        const formatDate = (date) => date.toISOString().split('T')[0];
        tripDatesElement.textContent = `${formatDate(departureDate)} - ${formatDate(endDate)} (${duration - 1}N${duration}D)`;
    }
}

// Update reservation information
function updateReservationInfo(booking) {
    const infoElements = document.querySelectorAll('.text.fz14.fw400.lh22.black12.mt4');

    // Reservation number
    if (infoElements[1] && booking.bookingId) {
        infoElements[1].textContent = booking.bookingId;
    }

    // Reservation status - should show  
    if (infoElements[2]) {
        infoElements[2].textContent = ' ';
    }

    // Update guest information
    updateGuestInfo(booking);

    // Update room options
    updateRoomOptions(booking.roomOptions || []);

    // Update additional options
    updateAdditionalOptions(booking.additionalOptions || []);

    // Update total price
    updateTotalPrice(booking.totalAmount);
}

// Update guest information
function updateGuestInfo(booking) {
    const guestElements = document.querySelectorAll('.align.both.vm.mt4, .align.both.vm.mt8');

    const adults = booking.adults || 0;
    const children = booking.children || 0;
    const packagePrice = booking.packagePrice || 0;

    // Find guest elements and update them
    let guestIndex = 0;
    guestElements.forEach((element, index) => {
        const textElement = element.querySelector('.text.fz14.fw400.lh22.black12');
        const priceElement = element.querySelector('span.text.fz14.fw400.lh22.black12');

        if (textElement && priceElement) {
            if (guestIndex === 0 && adults > 0) {
                textElement.textContent = `x${adults}`;
                priceElement.textContent = formatPrice(packagePrice * adults);
                guestIndex++;
            } else if (guestIndex === 1 && children > 0) {
                textElement.textContent = `x${children}`;
                priceElement.textContent = formatPrice(Math.max(packagePrice - 5000, 0) * children);
                guestIndex++;
            }
        }
    });
}

// Update room options
function updateRoomOptions(roomOptions) {
    // Find room option elements
    const roomElements = document.querySelectorAll('.align.both.vm.mt4, .align.both.vm.mt8');

    roomElements.forEach((element, index) => {
        const textElement = element.querySelector('.text.fz14.fw400.lh22.black12');
        const priceElement = element.querySelector('span.text.fz14.fw400.lh22.black12');

        if (textElement && priceElement && roomOptions[index]) {
            const room = roomOptions[index];
            textElement.textContent = `${room.name}x${room.quantity}`;
            priceElement.textContent = formatPrice(room.price * room.quantity);
        }
    });
}

// Update additional options
function updateAdditionalOptions(additionalOptions) {
    // Update extra baggage, breakfast, wifi etc. based on options
    const optionElements = document.querySelectorAll('.text.fz14.fw400.lh22.black12.mt4');

    optionElements.forEach(element => {
        const parentSection = element.previousElementSibling;
        if (parentSection && parentSection.classList.contains('gray96')) {
            const sectionText = parentSection.textContent;

            if (sectionText.includes('Extra Baggage')) {
                const baggageOption = additionalOptions.find(opt => opt.type === 'baggage');
                element.textContent = baggageOption ? (baggageOption.selected ? '' : '') : '';
            } else if (sectionText.includes('Breakfast')) {
                const breakfastOption = additionalOptions.find(opt => opt.type === 'breakfast');
                element.textContent = breakfastOption ? (breakfastOption.selected ? '' : '') : '';
            } else if (sectionText.includes('Wi-Fi')) {
                const wifiOption = additionalOptions.find(opt => opt.type === 'wifi');
                element.textContent = wifiOption ? (wifiOption.selected ? '' : '') : '';
            } else if (sectionText.includes('Seat Preference')) {
                const seatOption = additionalOptions.find(opt => opt.type === 'seatPreference');
                element.textContent = seatOption ? seatOption.value : '';
            } else if (sectionText.includes('Other Requests')) {
                const requestOption = additionalOptions.find(opt => opt.type === 'otherRequests');
                element.textContent = requestOption ? requestOption.value : '';
            }
        }
    });
}

// Update total price
function updateTotalPrice(totalAmount) {
    const totalElements = Array.from(document.querySelectorAll('.text.fz14.fw400.lh22.black12.mt4'))
        .filter(el => el.textContent.includes('₱'));

    if (totalElements.length > 0) {
        const totalElement = totalElements[totalElements.length - 1];
        if (totalElement && totalAmount) {
            totalElement.textContent = formatPrice(totalAmount);
        }
    }
}

// Update booker information
function updateBookerInfo(booking) {
    const bookerInfo = document.querySelectorAll('.card-type8.pink.mt8 li p.text.fz14.fw400.lh22.black12');

    if (bookerInfo.length >= 3) {
        // Name
        if (booking.fName || booking.lName) {
            bookerInfo[0].textContent = `${booking.fName || ''} ${booking.lName || ''}`.trim();
        }

        // Email
        if (booking.emailAddress) {
            bookerInfo[1].textContent = booking.emailAddress;
        }

        // Phone
        if (booking.contactNo) {
            bookerInfo[2].textContent = booking.contactNo;
        }
    }
}

// Update travelers information
function updateTravelersInfo(travelers) {
    const travelerLinks = document.querySelectorAll('a[href="#none"].align.both.vm.py12');

    travelers.forEach((traveler, index) => {
        if (travelerLinks[index]) {
            const travelerText = travelerLinks[index].querySelector('.text.fz14.fw500.lh22.black12');
            if (travelerText) {
                // Determine traveler type based on age or type
                let travelerType = '';
                if (traveler.age && traveler.age < 12) {
                    travelerType = '( 3~7)';
                } else if (traveler.type && traveler.type.includes('child')) {
                    travelerType = '( 3~7)';
                }

                travelerText.textContent = `${travelerType}${index + 1}`;
            }
        }
    });
}

// Calculate and display refund information
function calculateAndDisplayRefund(booking) {
    try {
        const totalAmount = parseFloat(booking.totalAmount) || 0;
        const departureDate = new Date(booking.departureDate);
        const cancelledDate = booking.cancelledAt ? new Date(booking.cancelledAt) : new Date();

        // Calculate days between cancellation and departure
        const daysUntilDeparture = Math.ceil((departureDate - cancelledDate) / (1000 * 60 * 60 * 24));

        // Calculate cancellation fee based on policy
        let cancellationFeeRate = 0;
        if (daysUntilDeparture >= 15) {
            cancellationFeeRate = 0; // No charge
        } else if (daysUntilDeparture >= 8) {
            cancellationFeeRate = 0.5; // 50% charge
        } else if (daysUntilDeparture >= 4) {
            cancellationFeeRate = 0.7; // 70% charge
        } else {
            cancellationFeeRate = 1.0; // 100% charge
        }

        const cancellationFee = totalAmount * cancellationFeeRate;
        const refundAmount = totalAmount - cancellationFee;

        // Update refund information in the DOM
        updateRefundInfo(totalAmount, cancellationFee, refundAmount, cancelledDate);

    } catch (error) {
        console.error('Calculate refund error:', error);
        // Show default values
        updateRefundInfo(0, 0, 0, new Date());
    }
}

// Update refund information in DOM
function updateRefundInfo(totalAmount, cancellationFee, refundAmount, refundDate) {
    const refundElements = document.querySelectorAll('.px20.pb20 .text.fz14.fw400.lh22.black12.mt4');

    if (refundElements.length >= 4) {
        // Total amount
        refundElements[0].textContent = formatPrice(totalAmount);

        // Cancellation fee
        refundElements[1].textContent = formatPrice(cancellationFee);

        // Total refund amount
        refundElements[2].textContent = formatPrice(refundAmount);

        // Refund date
        const formattedDate = refundDate.toLocaleDateString('ko-KR') + ' ' +
                             refundDate.toLocaleTimeString('ko-KR', {
                                 hour: '2-digit',
                                 minute: '2-digit'
                             });
        refundElements[3].textContent = formattedDate;
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
        loadCancelledBookingDetails,
        calculateAndDisplayRefund,
        formatPrice
    };
}