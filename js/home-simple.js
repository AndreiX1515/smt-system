// Simple and reliable home page script that works with or without database
console.log('Loading home-simple.js...');

// Configuration
const API_CONFIG = {
    baseURL: '/backend/api/',
    timeout: 10000,
    retryAttempts: 3,
    retryDelay: 1000
};

// Initialize home page
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking if this is home page...');
    
    // Check if we're on the home page
    const isHomePage = window.location.href.includes('home.html') || 
                      window.location.pathname.endsWith('/') ||
                      window.location.pathname === '';
    
    if (isHomePage) {
        console.log('This is home page, initializing...');
        initializeHomePage();
    }
});

// Main initialization function
async function initializeHomePage() {
    try {
        // Check user login status and load user trip data
        await checkAndLoadUserTrips();
        
        // Load all package sections
        await loadAllPackageSections();
        console.log('Home page initialization complete');
    } catch (error) {
        console.error('Home page initialization failed:', error);
        showFallbackContent();
    }
}

// Load all package sections
async function loadAllPackageSections() {
    console.log('Loading package sections...');
    
    const sections = [
        { category: 'season', containerId: 'seasonPackages' },
        { category: 'region', containerId: 'regionPackages' }, 
        { category: 'theme', containerId: 'themePackages' },
        { category: 'oneday', containerId: 'onedayPackages' }
    ];
    
    // Load sections in parallel
    const promises = sections.map(section => loadPackageSection(section));
    await Promise.all(promises);
}

// Load individual package section
async function loadPackageSection({ category, containerId }) {
    console.log(`Loading ${category} packages for container ${containerId}...`);
    
    const container = document.getElementById(containerId);
    if (!container) {
        console.warn(`Container ${containerId} not found`);
        return;
    }
    
    // Show loading state
    container.innerHTML = '<div style="text-align: center; padding: 20px;">  ...</div>';
    
    try {
        // Try API first
        const packages = await fetchPackages(category, 6); // Limit to 6 packages
        
        if (packages && packages.length > 0) {
            renderPackages(container, packages);
        } else {
            // Fallback to sample data
            const fallbackPackages = getFallbackPackages(category);
            renderPackages(container, fallbackPackages);
        }
        
    } catch (error) {
        console.error(`Failed to load ${category} packages:`, error);
        
        // Show fallback data
        const fallbackPackages = getFallbackPackages(category);
        renderPackages(container, fallbackPackages);
    }
}

// Fetch packages from API
async function fetchPackages(category, limit = 20) {
    const url = `${API_CONFIG.baseURL}packages-simple.php?category=${category}&limit=${limit}`;
    console.log(`Fetching packages from: ${url}`);
    
    try {
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log(`Received data for ${category}:`, data);
        
        if (data.packages) {
            return data.packages;
        } else if (Array.isArray(data)) {
            return data;
        } else {
            console.warn('Unexpected API response format:', data);
            return [];
        }
        
    } catch (error) {
        console.error(`API fetch failed for ${category}:`, error);
        throw error;
    }
}

// Render packages to container
function renderPackages(container, packages) {
    if (!packages || packages.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"> .</div>';
        return;
    }
    
    const packageHTML = packages.map(pkg => createPackageCard(pkg)).join('');
    container.innerHTML = packageHTML;
    
    console.log(`Rendered ${packages.length} packages`);
}

// Create individual package card HTML - matching home.html structure
function createPackageCard(pkg) {
    const imageUrl = pkg.primaryImage || pkg.packageImageUrl || 'images/@img_card1.jpg';
    const price = formatPrice(pkg.packagePrice);
    const rating = pkg.rating ? `${pkg.rating}` : '4.5';
    const reviewCount = pkg.reviewCount ? `(${pkg.reviewCount})` : '(0)';
    const duration = pkg.durationDays > 0 ? `${pkg.durationDays} ${pkg.durationDays + 1}` : '';
    
    return `
        <li>
            <a class="card-type1" href="user/product-detail.php?id=${pkg.packageId}">
                <img src="${imageUrl}" alt="${pkg.packageName}" onerror="this.src='images/@img_card1.jpg'">
                <div class="pt16">
                    <div class="text fz14 fw500 lh22 black12 ellipsis2">${pkg.packageName}</div>
                    <div class="align vm mt8">
                        <div class="text fz12 fw600 lh18 gray6b mr4">${pkg.destination || ''}</div>
                        <div class="text fz12 fw600 lh18 gray6b">${duration}</div>
                    </div>
                    <div class="align vm mt8">
                        <div class="text fz12 fw600 lh18 reded mr4">⭐ ${rating}</div>
                        <div class="text fz12 fw600 lh18 gray6b">${reviewCount}</div>
                    </div>
                    <div class="text fz14 fw600 lh22 black12 mt8">${price}</div>
                </div>
            </a>
        </li>
    `;
}

// Navigate to package detail page
function goToPackageDetail(packageId) {
    window.location.href = `user/product-detail.php?id=${packageId}`;
}

// Format price
function formatPrice(price) {
    if (!price) return ' ';
    
    const numPrice = parseFloat(price);
    if (isNaN(numPrice)) return ' ';
    
    return '₱' + numPrice.toLocaleString();
}

// Fallback packages when API fails
function getFallbackPackages(category) {
    const allPackages = {
        season: [
            {
                packageId: 1,
                packageName: '     5 6',
                destination: '',
                packagePrice: 450000,
                rating: 4.8,
                reviewCount: 127,
                durationDays: 5,
                packageImageUrl: 'images/packages/seoul_cherry.jpg'
            },
            {
                packageId: 2,
                packageName: '   3 4',
                destination: '',
                packagePrice: 280000,
                rating: 4.6,
                reviewCount: 89,
                durationDays: 3,
                packageImageUrl: 'images/packages/jeju_canola.jpg'
            },
            {
                packageId: 3,
                packageName: '   2 3',
                destination: '',
                packagePrice: 180000,
                rating: 4.9,
                reviewCount: 156,
                durationDays: 2,
                packageImageUrl: 'images/packages/seorak_autumn.jpg'
            }
        ],
        region: [
            {
                packageId: 4,
                packageName: '   3 4',
                destination: '',
                packagePrice: 220000,
                rating: 4.7,
                reviewCount: 203,
                durationDays: 3,
                packageImageUrl: 'images/packages/busan_beach.jpg'
            },
            {
                packageId: 5,
                packageName: '   2 3',
                destination: '',
                packagePrice: 165000,
                rating: 4.5,
                reviewCount: 98,
                durationDays: 2,
                packageImageUrl: 'images/packages/jeonju_hanok.jpg'
            },
            {
                packageId: 6,
                packageName: '   3 4',
                destination: '',
                packagePrice: 195000,
                rating: 4.8,
                reviewCount: 145,
                durationDays: 3,
                packageImageUrl: 'images/packages/gyeongju_history.jpg'
            }
        ],
        theme: [
            {
                packageId: 7,
                packageName: '   2 3',
                destination: '',
                packagePrice: 240000,
                rating: 4.4,
                reviewCount: 67,
                durationDays: 2,
                packageImageUrl: 'images/packages/jeju_cafe.jpg'
            },
            {
                packageId: 8,
                packageName: '   4 5',
                destination: '',
                packagePrice: 320000,
                rating: 4.7,
                reviewCount: 112,
                durationDays: 4,
                packageImageUrl: 'images/packages/gangwon_trek.jpg'
            },
            {
                packageId: 9,
                packageName: '  3 4',
                destination: '',
                packagePrice: 285000,
                rating: 4.6,
                reviewCount: 134,
                durationDays: 3,
                packageImageUrl: 'images/packages/chungcheong_spa.jpg'
            }
        ],
        oneday: [
            {
                packageId: 12,
                packageName: '  ',
                destination: '',
                packagePrice: 65000,
                rating: 4.3,
                reviewCount: 89,
                durationDays: 0,
                packageImageUrl: 'images/packages/nami_island.jpg'
            },
            {
                packageId: 13,
                packageName: '  ',
                destination: '',
                packagePrice: 55000,
                rating: 4.1,
                reviewCount: 67,
                durationDays: 0,
                packageImageUrl: 'images/packages/incheon_china.jpg'
            },
            {
                packageId: 14,
                packageName: '  ',
                destination: '',
                packagePrice: 48000,
                rating: 4.4,
                reviewCount: 92,
                durationDays: 0,
                packageImageUrl: 'images/packages/suwon_palace.jpg'
            }
        ]
    };
    
    return allPackages[category] || [];
}

// Show fallback content when everything fails
function showFallbackContent() {
    const containers = ['seasonPackages', 'regionPackages', 'themePackages', 'onedayPackages'];
    
    containers.forEach((containerId, index) => {
        const container = document.getElementById(containerId);
        if (container) {
            const categories = ['season', 'region', 'theme', 'oneday'];
            const packages = getFallbackPackages(categories[index]);
            renderPackages(container, packages);
        }
    });
}

// Check user login status and load trip data
async function checkAndLoadUserTrips() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userId = localStorage.getItem('userId');
    
    if (!isLoggedIn || !userId) {
        // Hide trip sections for non-logged in users
        hideTripSections();
        return;
    }
    
    try {
        // Try to load user bookings to check for active/upcoming trips
        const response = await fetch(`${API_CONFIG.baseURL}bookings.php?userId=${userId}&status=active`);
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                const activeBooking = data.data[0];
                showUserTripStatus(activeBooking);
            } else {
                hideTripSections();
            }
        } else {
            hideTripSections();
        }
    } catch (error) {
        console.log('Failed to load user trip data, hiding trip sections:', error);
        hideTripSections();
    }
}

// Hide trip sections
function hideTripSections() {
    const todaysTrip = document.getElementById('todaysTripSection');
    const upcomingTrip = document.getElementById('upcomingTripSection');
    
    if (todaysTrip) todaysTrip.style.display = 'none';
    if (upcomingTrip) upcomingTrip.style.display = 'none';
}

// Show user trip status based on booking data
function showUserTripStatus(booking) {
    const today = new Date();
    const tripStart = new Date(booking.tripStartDate || booking.startDate);
    const tripEnd = new Date(booking.tripEndDate || booking.endDate);
    
    // Check if trip is currently happening
    if (today >= tripStart && today <= tripEnd) {
        showTodaysTrip(booking);
    }
    // Check if trip is upcoming (within 7 days)
    else if (tripStart > today && (tripStart - today) / (1000 * 60 * 60 * 24) <= 7) {
        showUpcomingTrip(booking);
    }
    // Otherwise hide trip sections
    else {
        hideTripSections();
    }
}

// Show today's trip section with real data
function showTodaysTrip(booking) {
    const section = document.getElementById('todaysTripSection');
    if (!section) return;
    
    const tripDay = calculateTripDay(booking);
    
    // Update content with real booking data
    section.querySelector('.label').textContent = ` ${tripDay}`;
    section.querySelector('.text.fz16').textContent = booking.packageName || ' ';
    
    section.style.display = 'block';
    hideSectionById('upcomingTripSection');
}

// Show upcoming trip section with real data
function showUpcomingTrip(booking) {
    const section = document.getElementById('upcomingTripSection');
    if (!section) return;
    
    const daysUntilTrip = Math.ceil((new Date(booking.tripStartDate || booking.startDate) - new Date()) / (1000 * 60 * 60 * 24));
    
    // Update content with real booking data
    section.querySelector('.label').textContent = `D-${daysUntilTrip}`;
    section.querySelector('.text.fz16').textContent = booking.packageName || ' ';
    
    // Update meeting location and time if available
    if (booking.meetingLocation) {
        section.querySelector('.ml24').textContent = booking.meetingLocation;
    }
    if (booking.meetingTime) {
        const timeElement = section.querySelectorAll('.ml24')[1];
        if (timeElement) timeElement.textContent = booking.meetingTime;
    }
    
    section.style.display = 'block';
    hideSectionById('todaysTripSection');
}

// Calculate which day of trip it is
function calculateTripDay(booking) {
    const today = new Date();
    const tripStart = new Date(booking.tripStartDate || booking.startDate);
    return Math.floor((today - tripStart) / (1000 * 60 * 60 * 24)) + 1;
}

// Hide section by ID
function hideSectionById(id) {
    const section = document.getElementById(id);
    if (section) section.style.display = 'none';
}

console.log('home-simple.js loaded successfully');