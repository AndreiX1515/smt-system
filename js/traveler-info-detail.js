/**
 *     JavaScript
 *     
 */

let currentTravelerInfo = null;
let currentBooking = null;

const i18nTexts = {
    ko: {
        travelerInformation: '  ',
        loadingTravelerInfo: '   ...',
        loading: ' ...',
        selectTraveler: ' ',
        travelerTypeAdult: '',
        travelerTypeTeen: '',
        travelerTypeChild: '',
        title: '',
        firstName: '',
        lastName: '',
        gender: '',
        age: '',
        birthDate: '',
        nationality: '',
        passportNumber: '',
        passportIssueDate: ' ',
        passportExpiryDate: ' ',
        visaStatus: '  ',
        passportPhoto: ' ',
        apply: '',
        notApply: '',
        male: '',
        female: '',
        unknown: '-',
        pageLoadError: '    .',
        notSupportedTravelerId: 'travelerId      . bookingId .',
        travelerNotFound: '    .',
        loadFailed: '    .',
        renderFailed: '     .'
    },
    en: {
        travelerInformation: 'Traveler Information',
        loadingTravelerInfo: 'Loading traveler information...',
        loading: 'Loading...',
        selectTraveler: 'Select traveler',
        travelerTypeAdult: 'Adult',
        travelerTypeTeen: 'Teen',
        travelerTypeChild: 'Child',
        title: 'Title',
        firstName: 'First Name',
        lastName: 'Last Name',
        gender: 'Gender',
        age: 'Age',
        birthDate: 'Date of birth',
        nationality: 'Nationality',
        passportNumber: 'Passport Number',
        passportIssueDate: 'Passport Issue Date',
        passportExpiryDate: 'Passport Expiry Date',
        visaStatus: 'Visa application status',
        passportPhoto: 'Passport Photo',
        apply: 'Apply',
        notApply: 'Not apply',
        male: 'Male',
        female: 'Female',
        unknown: '-',
        pageLoadError: 'An error occurred while loading the page.',
        notSupportedTravelerId: 'Direct lookup by travelerId is not supported yet. Please use bookingId.',
        travelerNotFound: 'Traveler information not found.',
        loadFailed: 'Unable to load traveler information.',
        renderFailed: 'An error occurred while rendering traveler information.'
    },
    tl: {
        travelerInformation: 'Impormasyon ng Traveler',
        loadingTravelerInfo: 'Naglo-load ng impormasyon ng traveler...',
        loading: 'Naglo-load...',
        selectTraveler: 'Pumili ng traveler',
        // NOTE: Tagalog     en fallback .
        travelerTypeAdult: 'Matanda',
        travelerTypeChild: 'Bata',
        title: 'Titulo',
        firstName: 'Unang Pangalan',
        lastName: 'Apelyido',
        gender: 'Kasarian',
        age: 'Edad',
        birthDate: 'Petsa ng Kapanganakan',
        nationality: 'Nasyonalidad',
        passportNumber: 'Numero ng Pasaporte',
        passportIssueDate: 'Petsa ng Paglabas ng Pasaporte',
        passportExpiryDate: 'Petsa ng Pag-expire ng Pasaporte',
        visaStatus: 'Aplikasyon ng Visa',
        passportPhoto: 'Larawan ng Pasaporte',
        apply: 'Mag-apply',
        notApply: 'Hindi Mag-apply',
        male: 'Lalaki',
        female: 'Babae',
        unknown: '-',
        pageLoadError: 'May error sa pag-load ng page.',
        notSupportedTravelerId: 'Hindi pa supported ang direct lookup by travelerId. Gamitin ang bookingId.',
        travelerNotFound: 'Hindi nahanap ang impormasyon ng traveler.',
        loadFailed: 'Hindi ma-load ang impormasyon ng traveler.',
        renderFailed: 'May error sa pagpakita ng impormasyon ng traveler.'
    }
};

function getLang() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = (urlParams.get('lang') || '').toLowerCase();
    // NOTE: ko   /  (  )
    if (urlLang === 'en' || urlLang === 'tl') {
        try { localStorage.setItem('selectedLanguage', urlLang); } catch (_) {}
        return urlLang;
    }
    let stored = 'en';
    try { stored = (localStorage.getItem('selectedLanguage') || '').toLowerCase(); } catch (_) {}
    if (stored === 'en' || stored === 'tl') return stored;
    const docLang = String(document.documentElement.lang || '').toLowerCase();
    if (docLang === 'en' || docLang === 'tl') return docLang;
    return 'en';
}

function t(key) {
    const lang = getLang();
    return (i18nTexts[lang] && i18nTexts[lang][key]) || (i18nTexts.en[key]) || key;
}

//    
document.addEventListener('DOMContentLoaded', function() {
    setupTravelerInfoDetailBackLink();
    initializeTravelerInfoDetailPage();
});

function setupTravelerInfoDetailBackLink() {
    try {
        const back = document.querySelector('a.btn-mypage');
        if (!back) return;
        const qp = new URLSearchParams(window.location.search);
        const bookingId = qp.get('bookingId') || qp.get('booking_id') || '';
        const lang = getLang(); // en/tl only, default en

        // Requirement (#121): Back should go to reservation detail (not list).
        if (bookingId) {
            back.setAttribute('href', `reservation-detail.php?id=${encodeURIComponent(bookingId)}&lang=${encodeURIComponent(lang)}`);
        } else {
            // Fallback: reservation history
            back.setAttribute('href', `reservation-history.php?lang=${encodeURIComponent(lang)}`);
        }
    } catch (_) { /* ignore */ }
}

//     
async function initializeTravelerInfoDetailPage() {
    try {
        //  /  (i18n)
        const headerTitle = document.querySelector('.header-type2 .title');
        if (headerTitle) headerTitle.textContent = t('travelerInformation');
        const h = document.querySelector('.text.fz16.fw600.lh24.black12');
        if (h) h.textContent = t('loadingTravelerInfo');

        // URL   ID   ID 
        const urlParams = new URLSearchParams(window.location.search);
        const travelerId = urlParams.get('travelerId') || urlParams.get('traveler_id');
        const bookingId = urlParams.get('bookingId') || urlParams.get('booking_id');
        const type = urlParams.get('type'); // adult, child, infant
        const name = urlParams.get('name'); //  
        const index = urlParams.get('index'); //  

        if (!travelerId && !bookingId) {
            // ID     (HTML   )
            return;
        }

        //   
        if (travelerId) {
            await loadTravelerInfo(travelerId);
        } else if (bookingId) {
            await loadTravelerInfoByBooking(bookingId, type, name, index);
        }

    } catch (error) {
        console.error('Traveler info detail init error:', error);
        showErrorMessage(t('pageLoadError'));
    }
}

//   
async function loadTravelerInfo(travelerId) {
    try {
        showLoadingState();

        // travelers.php API    
        //  bookingId        ,
        //  travelerId   API 
        // bookingId     
        showErrorMessage(t('notSupportedTravelerId'));
        hideLoadingState();

    } catch (error) {
        console.error('Load traveler info error:', error);
        console.error('Load traveler by booking error:', error);
        showErrorMessage(t('loadFailed'));
    } finally {
        hideLoadingState();
    }
}

//  ID   
async function loadTravelerInfoByBooking(bookingId, type, name, index) {
    try {
        showLoadingState();

        // travelers.php API    
        const response = await fetch('../backend/api/travelers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getByBooking',
                bookingId: bookingId
            })
        });

        if (!response.ok) {
            throw new Error('API  : ' + response.status);
        }

        const result = await response.json();

        if (result.success && result.travelers) {
            let targetTraveler = null;

            // type name  index   
            if (type && (name || index)) {
                // type name 
                if (name) {
                    const decodedName = decodeURIComponent(name);
                    targetTraveler = result.travelers.find(t => {
                        const travelerType = (t.type || '').toLowerCase();
                        const travelerName = (t.firstName || '').trim();
                        return travelerType === type.toLowerCase() && 
                               travelerName.includes(decodedName.trim());
                    });
                }
                
                // name   index  type index 
                if (!targetTraveler && index) {
                    const typeTravelers = result.travelers.filter(t => 
                        (t.type || '').toLowerCase() === type.toLowerCase()
                    );
                    const travelerIndex = parseInt(index) - 1;
                    if (typeTravelers[travelerIndex]) {
                        targetTraveler = typeTravelers[travelerIndex];
                    }
                }
            }

            //        
            if (!targetTraveler && result.travelers.length > 0) {
                targetTraveler = result.travelers[0];
            }

            if (targetTraveler) {
                //    (travelers.php   )
                const travelerInfo = {
                    id: targetTraveler.id,
                    travelerType: targetTraveler.type,
                    title: targetTraveler.title,
                    first_name: targetTraveler.firstName,
                    last_name: targetTraveler.lastName,
                    name: (targetTraveler.firstName || '') + ' ' + (targetTraveler.lastName || ''),
                    birth_date: targetTraveler.birthDate,
                    gender: targetTraveler.gender,
                    nationality: targetTraveler.nationality,
                    passport_number: targetTraveler.passportNumber,
                    passport_issue_date: targetTraveler.passportIssueDate,
                    passport_expiry_date: targetTraveler.passportExpiry,
                    passport_photo: targetTraveler.passportImage,
                    visa_required: targetTraveler.visaStatus,
                    isMainTraveler: targetTraveler.isMainTraveler
                };

                currentTravelerInfo = travelerInfo;
                renderTravelerInfo(travelerInfo);

                //      UI 
                if (result.travelers.length > 1) {
                    renderTravelerSelector(result.travelers);
                }
            } else {
                showErrorMessage(t('travelerNotFound'));
            }
        } else {
            showErrorMessage(result.message || t('loadFailed'));
        }

    } catch (error) {
        console.error('   :', error);
        showErrorMessage(t('loadFailed') + ' ' + error.message);
    } finally {
        hideLoadingState();
    }
}

//   
function renderTravelerInfo(travelerInfo) {
    try {
        //   
        updateTravelerTitle(travelerInfo);

        //    
        updateTravelerDetails(travelerInfo);

        //   
        updatePassportPhoto(travelerInfo.passport_photo || travelerInfo.photo);

    } catch (error) {
        console.error('Render traveler info error:', error);
        showErrorMessage(t('renderFailed'));
    }
}

//   
function updateTravelerTitle(travelerInfo) {
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        const travelerType = getTravelerType(travelerInfo.age || travelerInfo.birth_date);
        const travelerNumber = travelerInfo.traveler_number || '1';
        titleElement.textContent = `${travelerType}${travelerNumber}`;
    }
}

//    
function updateTravelerDetails(travelerInfo) {
    const detailsList = document.querySelector('ul.mt16');
    if (!detailsList) return;

    //   
    detailsList.innerHTML = '';

    //   
    const infoItems = [
        { label: t('title'), value: getTitleText(travelerInfo.title) },
        { label: t('firstName'), value: travelerInfo.first_name || travelerInfo.firstName || travelerInfo.name || '' },
        { label: t('lastName'), value: travelerInfo.last_name || travelerInfo.lastName || travelerInfo.surname || '' },
        { label: t('gender'), value: getGenderText(travelerInfo.gender) },
        { label: t('age'), value: calculateAge(travelerInfo.birth_date || travelerInfo.birthDate || travelerInfo.age) },
        { label: t('birthDate'), value: formatDate(travelerInfo.birth_date || travelerInfo.birthDate) },
        { label: t('nationality'), value: travelerInfo.nationality || travelerInfo.country || '' },
        { label: t('passportNumber'), value: travelerInfo.passport_number || travelerInfo.passportNumber || '' },
        { label: t('passportIssueDate'), value: formatDate(travelerInfo.passport_issue_date || travelerInfo.passportIssueDate) },
        { label: t('passportExpiryDate'), value: formatDate(travelerInfo.passport_expiry_date || travelerInfo.passport_expiry_date || travelerInfo.passportExpiryDate) },
        { label: t('visaStatus'), value: getVisaStatus(travelerInfo.visa_required || travelerInfo.visa_status) }
    ];

    //   HTML 
    infoItems.forEach((item, index) => {
        if (item.value) {  //    
            const listItem = document.createElement('li');
            listItem.className = index === 0 ? 'align both vm' : 'align both vm mt12';
            listItem.innerHTML = `
                <div class="text fz14 fw400 lh22 black12">${item.label}</div>
                <div class="text fz14 fw400 lh22 black12">${item.value}</div>
            `;
            detailsList.appendChild(listItem);
        }
    });

    //    
    const passportPhotoItem = document.createElement('li');
    passportPhotoItem.className = 'align both mt12';
    passportPhotoItem.innerHTML = `
        <div class="text fz14 fw400 lh22 black12">${t('passportPhoto')}</div>
        <div class="passport-view">
            <img src="${travelerInfo.passport_photo || ''}" alt="${t('passportPhoto')}" onerror="this.style.display='none'">
        </div>
    `;
    detailsList.appendChild(passportPhotoItem);
}

//    UI 
function renderTravelerSelector(travelers) {
    const container = document.querySelector('.px20.pb20.mt20');
    if (!container) return;

    const selectorHTML = `
        <div class="traveler-selector mb20">
            <div class="text fz14 fw600 lh22 black12 mb12">${t('selectTraveler')}</div>
            <div class="traveler-tabs">
                ${travelers.map((traveler, index) => `
                    <button class="traveler-tab ${index === 0 ? 'active' : ''}"
                            data-traveler-index="${index}" type="button">
                        ${getTravelerType(traveler.age || traveler.birth_date)}${traveler.traveler_number || index + 1}
                    </button>
                `).join('')}
            </div>
        </div>
        <style>
        .traveler-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .traveler-tab {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }
        .traveler-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        </style>
    `;

    container.insertAdjacentHTML('afterbegin', selectorHTML);

    //     
    setupTravelerTabs(travelers);
}

function getTitleText(title) {
    const raw = String(title || '').trim().toUpperCase();
    if (!raw) return t('unknown');
    if (raw === 'MR' || raw === 'MS') return raw;
    return raw;
}

function getGenderText(gender) {
    const g = String(gender || '').toLowerCase();
    if (g === 'male' || g === 'm' || g === '') return t('male');
    if (g === 'female' || g === 'f' || g === '') return t('female');
    return t('unknown');
}

function getVisaStatus(v) {
    const val = String(v ?? '').toLowerCase();
    //   1/0  applied/not applied, /   
    const truthy = (val === '1' || val === 'true' || val.includes('apply') || val.includes(''));
    const falsy = (val === '0' || val === 'false' || val.includes('not') || val.includes(''));
    if (truthy && !falsy) return t('apply');
    if (falsy) return t('notApply');
    return t('unknown');
}

//    
function setupTravelerTabs(travelers) {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('traveler-tab')) {
            const index = parseInt(e.target.dataset.travelerIndex);
            if (travelers[index]) {
                //    
                document.querySelectorAll('.traveler-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                e.target.classList.add('active');

                //    
                currentTravelerInfo = travelers[index];
                renderTravelerInfo(travelers[index]);
            }
        }
    });
}

//  
function getTravelerType(age) {
    if (typeof age === 'string') {
        //      
        age = calculateAge(age);
    }

    const numAge = parseInt(age);
    if (!Number.isFinite(numAge)) return t('travelerTypeAdult');
    if (numAge < 12) return t('travelerTypeChild');
    if (numAge < 18) return t('travelerTypeTeen');
    return t('travelerTypeAdult');
}

function calculateAge(birthDate) {
    if (!birthDate) return '';

    const birth = new Date(birthDate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }

    return age.toString();
}

function formatDate(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

//   
function updatePassportPhoto(photoUrl) {
    const passportImg = document.querySelector('.passport-view img');
    if (passportImg && photoUrl) {
        passportImg.src = photoUrl;
        passportImg.style.display = 'block';
    }
}

//   
function showLoadingState() {
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        titleElement.style.opacity = '0.6';
    }
}

//   
function hideLoadingState() {
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        titleElement.style.opacity = '1';
    }
}

//   
function showErrorMessage(message) {
    alert(message);
}

//     
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadTravelerInfo,
        loadTravelerInfoByBooking,
        renderTravelerInfo
    };
}