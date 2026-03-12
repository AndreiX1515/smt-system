/**
 * Reservation Completed Page JavaScript
 * Handles display of completed booking confirmation
 */

let completedBooking = null;

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    //    (reservation-completed.php )
    const backButton = document.querySelector('.btn-mypage');
    if (backButton && window.location.pathname.includes('reservation-completed.php')) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // button.js   
            
            //    
            window.location.href = 'reservation-history.php';
        });
    }
    
    initializeCompletedPage();
});

// Check server session validity
async function checkServerSession() {
    try {
        const response = await fetch('../backend/api/check-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include' // Include cookies for session
        });
        
        const result = await response.json();
        console.log('Session check result:', result);
        
        if (result.success && result.isLoggedIn) {
            //   localStorage 
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('userId', result.user.id);
            return true;
        } else {
            //    localStorage 
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userId');
            localStorage.removeItem('userInfo');
            return false;
        }
    } catch (error) {
        console.error('Session check error:', error);
        return false;
    }
}

//     -   API 

// Initialize completed booking page
async function initializeCompletedPage() {
    try {
        // Check authentication
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            alert('Login required.');
            window.location.href = 'login.html';
            return;
        }

        //    (     )
        console.log('Skipping session check for reservation completed page');

        // Try to get booking details from multiple sources
        let bookingData = null;

        // 1. Check URL parameters for booking ID
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('bookingId') || urlParams.get('booking_id');
        
        console.log('URL parameters:', urlParams.toString());
        console.log('Booking ID from URL:', bookingId);

        if (bookingId && bookingId !== 'undefined') {
            // Load booking details from API
            bookingData = await loadBookingFromAPI(bookingId, userId);
            
            if (!bookingData) {
                console.error('Failed to load booking data for:', bookingId);
                showErrorMessage('Booking information not found.');
                return;
            }
        } else {
            console.log('No valid booking ID found in URL parameters');
            // 2. Check localStorage for recently created booking
            const recentBooking = localStorage.getItem('recentBooking');
            if (recentBooking) {
                try {
                    bookingData = JSON.parse(recentBooking);
                    // Clear the recent booking from storage
                    localStorage.removeItem('recentBooking');
                } catch (e) {
                    console.error('Error parsing recent booking:', e);
                }
            }
        }

        if (bookingData) {
            completedBooking = bookingData;
            renderCompletedBooking(bookingData);
        } else {
            showNoBookingMessage();
        }

    } catch (error) {
        console.error('Completed page initialization error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

// Load booking details from API
async function loadBookingFromAPI(bookingId, userId) {
    try {
        // Use API client if available, otherwise direct fetch
        let result;
        if (window.api && window.api.getBooking) {
            result = await window.api.getBooking(bookingId, userId);
        } else {
            const response = await fetch(`../backend/api/booking.php?bookingId=${encodeURIComponent(bookingId)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            return result.data;
        } else {
            console.error('Failed to load booking:', result.message);
            return null;
        }

    } catch (error) {
        console.error('Load booking from API error:', error);
        return null;
    }
}

// Render completed booking details
function renderCompletedBooking(booking) {
    try {
        // Update product information
        updateProductInfo(booking);

        // Update reservation number
        updateReservationNumber(booking.bookingId);

        //    PHP    JavaScript  
        // updateTripDates(booking);  //  - PHP 
        // updateGuestInfo(booking);  //  - PHP 

        // Update navigation links
        updateNavigationLinks(booking.bookingId);

    } catch (error) {
        console.error('Render completed booking error:', error);
        showErrorMessage('     .');
    }
}

// Update product information
function updateProductInfo(booking) {
    // Update product title
    const titleElement = document.querySelector('.ellipsis1');
    if (titleElement && booking.packageName) {
        titleElement.textContent = booking.packageName;
    }

    // Update product price
    const priceElement = titleElement?.parentNode.querySelector('.text.fz14.fw600.lh22.black12');
    if (priceElement && booking.totalAmount) {
        priceElement.textContent = formatPrice(booking.totalAmount);
    }

    // Update product image
    const imageElement = document.querySelector('img[src*="@img_thumbnail.png"]');
    if (imageElement && booking.packageImage) {
        imageElement.src = booking.packageImage;
        imageElement.alt = booking.packageName || '';
    }
}

// Update reservation number
function updateReservationNumber(bookingId) {
    const reservationElement = document.querySelector('.text.fz14.lh22.black12.mt4');
    if (reservationElement && bookingId) {
        reservationElement.textContent = bookingId;
    }
}

// Update trip dates -  PHP    JavaScript  
function updateTripDates(booking) {
    // PHP  formatDate()      
    //     
}

// Update guest information - PHP    JavaScript  
function updateGuestInfo(booking) {
    // PHP        
    //     ( )
    const guestElement = document.getElementById('guests-info');
    if (guestElement && booking) {
        // i18n.js      
        //    i18n.js 
    }
}

// Update navigation links
function updateNavigationLinks(bookingId) {
    // Update reservation history link to include the new booking ID
    const historyLink = document.querySelector('a[href="reservation-history.php"]');
    if (historyLink && bookingId) {
        historyLink.href = `reservation-history.php?highlight=${bookingId}`;
    }

    // Ensure home link works correctly
    const homeLink = document.querySelector('a[href="../home.html"]');
    if (homeLink) {
        homeLink.addEventListener('click', function(e) {
            // Clear any booking-related temporary data
            localStorage.removeItem('bookingInProgress');
            localStorage.removeItem('selectedPackage');
        });
    }
}

// Show message when no booking data is available
function showNoBookingMessage() {
    const titleElement = document.querySelector('.text.fz20.fw600.lh28.black12.txt-center');
    if (titleElement) {
        titleElement.textContent = 'Booking information not found.';
    }

    // Hide booking details
    const bookingDetails = document.querySelector('.px20.mt22');
    if (bookingDetails) {
        bookingDetails.style.display = 'none';
    }

    // Update buttons
    const buttons = document.querySelector('.align.both.vm.gap12.mt64');
    if (buttons) {
        buttons.innerHTML = `
            <a class="btn primary lg" href="../home.html">Home</a>
        `;
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

// Show error message
function showErrorMessage(message) {
    alert(message);
}

// Store booking data for completed page (to be called from booking creation process)
function storeCompletedBooking(bookingData) {
    try {
        localStorage.setItem('recentBooking', JSON.stringify(bookingData));
    } catch (error) {
        console.error('Error storing completed booking:', error);
    }
}

// Add animation and user feedback
function addPageAnimations() {
    // Add subtle animation to the check icon
    const checkIcon = document.querySelector('.img-check');
    if (checkIcon) {
        checkIcon.style.transform = 'scale(0)';
        checkIcon.style.transition = 'transform 0.5s ease-out';

        setTimeout(() => {
            checkIcon.style.transform = 'scale(1)';
        }, 100);
    }

    // Add bounce animation to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach((button, index) => {
        button.style.opacity = '0';
        button.style.transform = 'translateY(20px)';
        button.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';

        setTimeout(() => {
            button.style.opacity = '1';
            button.style.transform = 'translateY(0)';
        }, 300 + index * 100);
    });
}

// Initialize animations after page load
setTimeout(addPageAnimations, 100);

// Export functions for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        renderCompletedBooking,
        storeCompletedBooking,
        formatPrice
    };
}

// Make storeCompletedBooking available globally for other booking pages
window.storeCompletedBooking = storeCompletedBooking;