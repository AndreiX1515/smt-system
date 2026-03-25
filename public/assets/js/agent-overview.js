/**
 * Agent Admin - Overview Page JavaScript
 */

//   
const overviewTexts = {
    eng: {
        noItineraries: 'No travel itineraries for today.',
        case: ''
    },
    kor: {
        noItineraries: '   .',
        case: ''
    }
};

//   
function getCurrentLang() {
    const lang = getCookie('lang') || 'eng';
    // Admin : eng/tl  (tl    eng fallback)
    if (lang === 'eng' || lang === 'tl') return 'eng';
    return 'kor';
}

//   
function getText(key) {
    const lang = getCurrentLang();
    return overviewTexts[lang]?.[key] || overviewTexts['eng'][key] || key;
}

document.addEventListener('DOMContentLoaded', function() {
    updateCurrentDate();
    //   overview.html     
    // loadOverviewData();
    // loadTodayItineraries();
});

function updateCurrentDate() {
    const dateElement = document.getElementById('current-date');
    if (dateElement) {
        const now = new Date();
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
        const dateString = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        dateElement.textContent = dateString;
        dateElement.setAttribute('datetime', now.toISOString().split('T')[0]);
    }
}

async function loadOverviewData() {
    try {
        const response = await fetch('../backend/api/agent-api.php?action=getOverview');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            //   
            updateBookingStatus(data.bookingStatus);
            
            //   
            updateInquiryStatus(data.inquiryStatus);
        } else {
            console.error('Failed to load overview:', result.message);
        }
    } catch (error) {
        console.error('Error loading overview:', error);
    }
}

async function loadTodayItineraries() {
    try {
        const response = await fetch('../backend/api/agent-api.php?action=getTodayItineraries');
        const result = await response.json();
        
        if (result.success) {
            renderTodayItineraries(result.data);
        } else {
            console.error('Failed to load today itineraries:', result.message);
            const tbody = document.querySelector('.jw-tableA.typeB tbody');
            if (tbody) {
                const noItinerariesText = getText('noItineraries');
                tbody.innerHTML = `<tr><td colspan="6" class="is-center">${escapeHtml(noItinerariesText)}</td></tr>`;
            }
        }
    } catch (error) {
        console.error('Error loading today itineraries:', error);
        const tbody = document.querySelector('.jw-tableA.typeB tbody');
        if (tbody) {
            const noItinerariesText = getText('noItineraries');
            tbody.innerHTML = `<tr><td colspan="6" class="is-center">${escapeHtml(noItinerariesText)}</td></tr>`;
        }
    }
}

function updateBookingStatus(status) {
    const pendingDepositElement = document.querySelector('.status-item:has(.dot-blue) + .status-item:has(.dot-blue)');
    const pendingBalanceElement = document.querySelector('.status-item:has(.dot-green)');
    
    //   
    const pendingDepositCount = document.querySelector('.overview-card-grid .card:first-child .status-item:first-child .count');
    if (pendingDepositCount) {
        const caseText = getText('case');
        //     
        const lang = getCurrentLang();
        if (lang === 'eng') {
            pendingDepositCount.textContent = status.pendingDeposit;
        } else {
            pendingDepositCount.innerHTML = status.pendingDeposit + (caseText ? '<span>' + caseText + '</span>' : '');
        }
    }
    
    //   
    const pendingBalanceCount = document.querySelector('.overview-card-grid .card:first-child .status-item:last-child .count');
    if (pendingBalanceCount) {
        const caseText = getText('case');
        const lang = getCurrentLang();
        if (lang === 'eng') {
            pendingBalanceCount.textContent = status.pendingBalance;
        } else {
            pendingBalanceCount.innerHTML = status.pendingBalance + (caseText ? '<span>' + caseText + '</span>' : '');
        }
    }
}

function updateInquiryStatus(status) {
    // 
    const unansweredCount = document.querySelector('.overview-card-grid .card:last-child .status-item:first-child .count');
    if (unansweredCount) {
        const caseText = getText('case');
        const lang = getCurrentLang();
        if (lang === 'eng') {
            unansweredCount.textContent = status.unanswered;
        } else {
            unansweredCount.innerHTML = status.unanswered + (caseText ? '<span>' + caseText + '</span>' : '');
        }
    }
    
    // 
    const processingCount = document.querySelector('.overview-card-grid .card:last-child .status-item:last-child .count');
    if (processingCount) {
        const caseText = getText('case');
        const lang = getCurrentLang();
        if (lang === 'eng') {
            processingCount.textContent = status.processing;
        } else {
            processingCount.innerHTML = status.processing + (caseText ? '<span>' + caseText + '</span>' : '');
        }
    }
}

function renderTodayItineraries(itineraries) {
    const tbody = document.querySelector('.jw-tableA.typeB tbody');
    if (!tbody) return;
    
    const countElement = document.querySelector('.card-subtitle strong');
    if (countElement) {
        const caseText = getText('case');
        const lang = getCurrentLang();
        if (lang === 'eng') {
            countElement.textContent = itineraries.length;
        } else {
            countElement.innerHTML = itineraries.length + (caseText ? '<span>' + caseText + '</span>' : '');
        }
    }
    
    if (itineraries.length === 0) {
        const noItinerariesText = getText('noItineraries');
        tbody.innerHTML = `<tr><td colspan="6" class="is-center">${escapeHtml(noItinerariesText)}</td></tr>`;
        return;
    }
    
    // (HTML 태그 수정) 디코딩 적용
    tbody.innerHTML = itineraries.map((item, index) => `
        <tr onclick="goToReservationDetail('${escapeHtml(item.bookingId)}')" style="cursor: pointer;">
            <td class="is-center">${index + 1}</td>
            <td>${escapeHtml(decodeHtmlEntities(item.packageName) || '')}</td>
            <td class="is-center">${item.travelPeriod}</td>
            <td class="is-center">${item.customerType}</td>
            <td class="is-center">${item.numPeople}</td>
            <td class="is-center">${escapeHtml(item.guideName)}</td>
        </tr>
    `).join('');
}

function goToReservationDetail(bookingId) {
    window.location.href = `reservation-detail.html?id=${bookingId}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// (HTML 태그 수정) HTML 엔티티 디코딩 함수
function decodeHtmlEntities(str) {
    if (!str) return str;
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}
