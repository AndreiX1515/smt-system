/**
 * Reservation Location Page JavaScript
 */

let currentBookingId = null;
let currentPage = 1;
let totalPages = 1;
const __addrCache = new Map(); // key: "lat,lng" -> address

document.addEventListener('DOMContentLoaded', function() {
    // Session check (admin/agent/guide)
    (async () => {
        try {
            const sessionRes = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionData = await sessionRes.json();
            if (!sessionData.authenticated || !['admin', 'agent', 'guide'].includes(sessionData.userType)) {
                window.location.href = '../index.html';
                return;
            }
        } catch (e) {
            window.location.href = '../index.html';
            return;
        }
    })();

    // Read bookingId from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentBookingId = urlParams.get('bookingId') || urlParams.get('id');
    
    if (currentBookingId) {
        loadLocationDetail();
        loadLocationHistory();
    } else {
        // No bookingId -> nothing to load
        console.warn('Booking ID not found in URL');
    }
});

function isLikelyCoordString(s) {
    const x = String(s || '').trim();
    if (!x) return false;
    // "lat, lng" 형태 또는 "lat lng" 형태
    return /^-?\d{1,3}\.\d+\s*[,\s]\s*-?\d{1,3}\.\d+$/.test(x);
}

async function reverseGeocodeIfNeeded(location, addressInput) {
    try {
        if (!location || !addressInput) return;
        const lat = Number(location.latitude ?? location.lat);
        const lng = Number(location.longitude ?? location.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const rawAddr = String(location.address || '').trim();
        if (rawAddr && !isLikelyCoordString(rawAddr)) return; // 이미 정상 주소

        const key = `${lat.toFixed(6)},${lng.toFixed(6)}`;
        if (__addrCache.has(key)) {
            addressInput.value = __addrCache.get(key);
            return;
        }

        // Naver Maps 역지오코딩
        if (typeof naver === 'undefined' || !naver.maps || !naver.maps.Service) return;

        naver.maps.Service.reverseGeocode({
            coords: new naver.maps.LatLng(lat, lng),
            orders: [naver.maps.Service.OrderType.ROAD_ADDR, naver.maps.Service.OrderType.ADDR].join(',')
        }, function(status, response) {
            try {
                if (status !== naver.maps.Service.Status.OK || !response.v2 || !response.v2.address) return;
                const addrObj = response.v2.address;
                const addr = addrObj.roadAddress || addrObj.jibunAddress || '';
                if (!addr) return;
                __addrCache.set(key, addr);
                addressInput.value = addr;
            } catch (_) {}
        });
    } catch (_) {}
}

// Load latest meeting location
async function loadLocationDetail() {
    try {
        if (!currentBookingId) return;

        const response = await fetch(`../backend/api/agent-api.php?action=getLatestMeetingLocation&bookingId=${encodeURIComponent(currentBookingId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success && result.data && result.data.location) {
            applyLocationDetail(result.data.location);
        } else {
            // Clear fields
            applyLocationDetail(null);
        }
    } catch (error) {
        console.error('Error loading location detail:', error);
    }
}

// Load meeting location history
async function loadLocationHistory(page = 1) {
    try {
        currentPage = page;
        
        const response = await fetch(`../backend/api/agent-api.php?action=getLocationHistory&bookingId=${encodeURIComponent(currentBookingId)}&page=${page}&limit=10`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        const tbody = document.getElementById('location-history-tbody');
        if (!tbody) return;
        
        if (result.success && result.data && result.data.locations) {
            const locations = result.data.locations;
            totalPages = result.data.totalPages || 1;
            
            if (locations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="is-center" data-lan-eng="No location history">No meeting location history.</td></tr>';
                return;
            }
            
            tbody.innerHTML = locations.map((location, index) => {
                const rowNum = (page - 1) * 10 + index + 1;
                // 요구사항: 공통 페이지에서는 삭제건을 Status = "Delete" 로 표시하고 클릭 시 상세도 확인 가능해야 함
                const status = (location.status === 'active' || location.status === 'register') ? 'Registered' : 'Delete';
                
                return `
                    <tr onclick="selectLocation(${location.locationId || location.id})" style="cursor:pointer;">
                        <td class="no is-center">${rowNum}</td>
                        <td class="is-center">${escapeHtml(location.placeName || location.name || '')}</td>
                        <td class="is-center">${formatDateTime(location.createdAt || location.registrationDate)}</td>
                        <td class="is-center">${escapeHtml(location.address || '')}</td>
                        <td class="is-center">${status}</td>
                    </tr>
                `;
            }).join('');
            
            // Render pagination
            renderPagination();
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="is-center" data-lan-eng="No location history">No meeting location history.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading location history:', error);
        const tbody = document.getElementById('location-history-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="is-center" data-lan-eng="Error loading location history">Failed to load meeting location history.</td></tr>';
        }
    }
}

// Select a location
function selectLocation(locationId) {
    if (!locationId) return;
    loadMeetingLocationDetail(locationId);
}

async function loadMeetingLocationDetail(locationId) {
    try {
        const res = await fetch(`../backend/api/agent-api.php?action=getMeetingLocationDetail&locationId=${encodeURIComponent(locationId)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success && json.data && json.data.location) {
            applyLocationDetail(json.data.location);
        }
    } catch (e) {
        console.error('Error loading meeting location detail:', e);
    }
}

function applyLocationDetail(location) {
    const registrationInput = document.getElementById('registration_datetime');
    const meetingTimeInput = document.getElementById('meeting_time');
    const placeNameInput = document.getElementById('place_name');
    const addressInput = document.getElementById('address');
    const contentTextarea = document.getElementById('location_content');

    if (!location) {
        if (registrationInput) registrationInput.value = '';
        if (meetingTimeInput) meetingTimeInput.value = '';
        if (placeNameInput) placeNameInput.value = '';
        if (addressInput) addressInput.value = '';
        if (contentTextarea) contentTextarea.value = '';
        return;
    }

    if (registrationInput) registrationInput.value = formatDateTime(location.createdAt || location.registrationDateTime || '');
    if (meetingTimeInput) meetingTimeInput.value = (location.meetingTime || '').toString().substring(0, 5);
    if (placeNameInput) placeNameInput.value = location.locationName || location.placeName || location.name || '';
    if (addressInput) addressInput.value = location.address || '';
    if (contentTextarea) contentTextarea.value = location.content || '';

    // 주소가 좌표로만 저장된 케이스 보강: lat/lng로 역지오코딩(Naver)
    if (addressInput) {
        reverseGeocodeIfNeeded(location, addressInput);
    }
}

// Render pagination
function renderPagination() {
    const paginationContainer = document.getElementById('location-pagination');
    if (!paginationContainer || totalPages <= 1) {
        if (paginationContainer) {
            paginationContainer.innerHTML = '';
        }
        return;
    }
    
    let html = '<div class="contents">';
    
    // First page button
    html += `<button type="button" class="first" aria-label="First page" ${currentPage === 1 ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadLocationHistory(1)">
        <img src="../image/first.svg" alt="">
    </button>`;
    
    // Previous page button
    html += `<button type="button" class="prev" aria-label="Previous page" ${currentPage === 1 ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadLocationHistory(${currentPage - 1})">
        <img src="../image/prev.svg" alt="">
    </button>`;
    
    // Page numbers
    html += '<div class="page" role="list">';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button type="button" class="p ${i === currentPage ? 'show' : ''}" role="listitem" ${i === currentPage ? 'aria-current="page"' : ''} onclick="loadLocationHistory(${i})">${i}</button>`;
    }
    html += '</div>';
    
    // Next page button
    html += `<button type="button" class="next" aria-label="Next page" ${currentPage === totalPages ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadLocationHistory(${currentPage + 1})">
        <img src="../image/next.svg" alt="">
    </button>`;
    
    // Last page button
    html += `<button type="button" class="last" aria-label="Last page" ${currentPage === totalPages ? 'aria-disabled="true" disabled' : 'aria-disabled="false"'} onclick="loadLocationHistory(${totalPages})">
        <img src="../image/last.svg" alt="">
    </button>`;
    
    html += '</div>';
    paginationContainer.innerHTML = html;
}

// Utilities
function formatDateTime(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    if (isNaN(date.getTime())) return datetime;
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getCurrentLang() {
    const htmlLang = document.documentElement.getAttribute('lang');
    return htmlLang === 'en' ? 'eng' : 'ko';
}

