document.addEventListener('DOMContentLoaded', function() {
    //   
    const i18nTexts = {
        'ko': {
            bookingNotFound: '    .',
            departure: '',
            loadingDeparture: '   ...',
            adult: '',
            child: '',
            infant: '',
            childAge: '( 3~7)',
            infantAge: '( 2 )',
            roomNotSelected: ' ',
            name: '',
            email: '',
            phone: '',
            notEntered: '',
            title: '',
            lastName: '',
            gender: '',
            age: '',
            birthDate: '',
            nationality: '',
            passportNumber: '',
            passportIssueDate: ' ',
            passportExpiryDate: ' ',
            passportPhoto: ' ',
            visaApplication: '  ',
            apply: '',
            notApply: '',
            male: '',
            female: '',
            notSelected: '',
            noRequest: ' ',
            productAmount: ' ',
            roomFee: ' ',
            additionalOptions: ' ',
            paymentFee: ' ',
            vat: ' (VAT)',
            processingPayment: ' ...',
            paymentCompleted: ' !',
            paymentError: '    .',
            agreeRequired: '   .',
            pay: ''
        },
        'en': {
            bookingNotFound: 'Booking information not found.',
            departure: 'Departure',
            loadingDeparture: 'Loading departure information...',
            adult: 'Adult',
            child: 'Child',
            infant: 'Infant',
            childAge: 'Child (3-7 years)',
            infantAge: 'Infant (under 2 years)',
            roomNotSelected: 'Room not selected',
            name: 'Name',
            email: 'Email',
            phone: 'Phone',
            notEntered: 'Not entered',
            title: 'Title',
            lastName: 'Last Name',
            gender: 'Gender',
            age: 'Age',
            birthDate: 'Date of birth',
            nationality: 'Country of origin',
            passportNumber: 'Passport Number',
            passportIssueDate: 'Passport Issue Date',
            passportExpiryDate: 'Passport expiration date',
            passportPhoto: 'Passport Photo',
            visaApplication: 'Visa application status',
            apply: 'Apply',
            notApply: 'Not Apply',
            male: 'Male',
            female: 'Female',
            notSelected: 'Not Selected',
            noRequest: 'No requests',
            productAmount: 'Product Amount',
            roomFee: 'Room Fee',
            additionalOptions: 'Additional Options',
            paymentFee: 'Payment Fee',
            vat: 'VAT',
            processingPayment: 'Processing payment...',
            paymentCompleted: 'Payment completed!',
            paymentSuspended: 'Payment Suspended',
            paymentError: 'An error occurred during payment processing.',
            agreeRequired: 'Please agree to all required terms.',
            pay: 'Pay',
            representativeTraveler: 'Main Traveler'
        },
        'tl': {
            bookingNotFound: 'Hindi nahanap ang impormasyon ng booking.',
            departure: 'Alis',
            loadingDeparture: 'Naglo-load ng impormasyon ng pag-alis...',
            adult: 'Matanda',
            child: 'Bata',
            infant: 'Sanggol',
            childAge: 'Bata (3-7 taon)',
            infantAge: 'Sanggol (wala pang 2 taon)',
            roomNotSelected: 'Hindi napili ang kwarto',
            name: 'Pangalan',
            email: 'Email',
            phone: 'Telepono',
            notEntered: 'Hindi naipasok',
            title: 'Titulo',
            lastName: 'Apelyido',
            gender: 'Kasarian',
            age: 'Edad',
            birthDate: 'Petsa ng Kapanganakan',
            nationality: 'Nasyonalidad',
            passportNumber: 'Numero ng Pasaporte',
            passportIssueDate: 'Petsa ng Paglabas ng Pasaporte',
            passportExpiryDate: 'Petsa ng Pag-expire ng Pasaporte',
            passportPhoto: 'Larawan ng Pasaporte',
            visaApplication: 'Aplikasyon ng Visa',
            apply: 'Mag-apply',
            notApply: 'Hindi Mag-apply',
            male: 'Lalaki',
            female: 'Babae',
            notSelected: 'Hindi Napili',
            noRequest: 'Walang kahilingan',
            productAmount: 'Halaga ng Produkto',
            roomFee: 'Bayad sa Kwarto',
            additionalOptions: 'Karagdagang Opsyon',
            paymentFee: 'Bayad sa Pagbabayad',
            vat: 'VAT',
            processingPayment: 'Pinoproseso ang pagbabayad...',
            paymentCompleted: 'Natapos na ang pagbabayad!',
            paymentSuspended: 'Payment Suspended',
            paymentError: 'May error na naganap sa pagproseso ng pagbabayad.',
            agreeRequired: 'Pakipagkasundo sa lahat ng kinakailangang tuntunin.',
            pay: 'Magbayad',
            representativeTraveler: 'Representatibong Manlalakbay'
        }
    };

    //     
    function getI18nText(key) {
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        if (urlLang) {
            localStorage.setItem('selectedLanguage', urlLang);
        }
        // Language policy: English default, only en/tl supported
        let currentLang = String(urlLang || localStorage.getItem('selectedLanguage') || 'en').toLowerCase();
        if (currentLang !== 'en' && currentLang !== 'tl') currentLang = 'en';
        const texts = i18nTexts[currentLang] || i18nTexts['en'];
        return texts[key] || key;
    }

    let currentBooking = null;
    let travelers = [];
    let selectedPaymentMethod = getI18nText('bank_transfer');
    
    // URL  booking_id 
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId = urlParams.get('booking_id');
    
    console.log('URL  booking_id:', bookingId);
    
    // booking_id DB   
    async function loadBookingFromDB() {
        if (!bookingId) {
            alert(getI18nText('bookingNotFound'));
            window.location.href = '../home.html';
            return;
        }
        
        try {
            console.log('DB    , bookingId:', bookingId);
            
            const response = await fetch('../backend/api/booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_booking',
                    bookingId: bookingId
                })
            });
            
            if (!response.ok) {
                throw new Error(`API  : ${response.status}`);
            }
            
            const result = await response.json();
            console.log('booking.php API :', result);
            
            if (result.success && result.booking) {
                const booking = result.booking;
                
                // selectedOptions 
                let selectedOptionsRaw = {};
                console.log('=== booking.selectedOptions   ===');
                console.log('booking.selectedOptions :', booking.selectedOptions);
                console.log('booking.selectedOptions :', typeof booking.selectedOptions);
                console.log('booking.selectedOptions :', booking.selectedOptions ? booking.selectedOptions.length : 0);
                
                if (booking.selectedOptions) {
                    try {
                        if (typeof booking.selectedOptions === 'string') {
                            //   
                            if (booking.selectedOptions.trim() === '' || booking.selectedOptions === 'null') {
                                console.warn('⚠️ booking.selectedOptions    "null"');
                                selectedOptionsRaw = {};
                            } else {
                                selectedOptionsRaw = JSON.parse(booking.selectedOptions);
                                console.log('✅ JSON  ');
                            }
                        } else if (typeof booking.selectedOptions === 'object') {
                            selectedOptionsRaw = booking.selectedOptions;
                            console.log('✅   ');
                        } else {
                            console.warn('⚠️    :', typeof booking.selectedOptions);
                            selectedOptionsRaw = {};
                        }
                        
                        console.log(' selectedOptionsRaw:', selectedOptionsRaw);
                        console.log('selectedOptionsRaw :', typeof selectedOptionsRaw);
                        console.log('selectedOptionsRaw keys:', Object.keys(selectedOptionsRaw || {}));
                    } catch (e) {
                        console.error('❌ selectedOptions  :', e);
                        console.error('❌   :', booking.selectedOptions);
                        selectedOptionsRaw = {};
                    }
                } else {
                    console.warn('⚠️ booking.selectedOptions  (null  undefined)');
                }
                
                // selectedRooms 
                let selectedRooms = {};
                if (booking.selectedRooms) {
                    try {
                        selectedRooms = typeof booking.selectedRooms === 'string'
                            ? JSON.parse(booking.selectedRooms)
                            : booking.selectedRooms;
                        // selectedRooms "[]"  ()    
                        // UI      .
                        if (Array.isArray(selectedRooms)) selectedRooms = {};
                    } catch (e) {
                        console.error('selectedRooms  :', e);
                    }
                }
                
                // customerInfo  (selectedOptionsRaw)
                let customerInfo = {};
                if (selectedOptionsRaw.customerInfo) {
                    customerInfo = selectedOptionsRaw.customerInfo;
                }
                
                // guestOptions  (selectedOptionsRaw)
                let guestOptions = [];
                if (selectedOptionsRaw.guestOptions && Array.isArray(selectedOptionsRaw.guestOptions)) {
                    guestOptions = selectedOptionsRaw.guestOptions;
                    console.log('✅ guestOptions :', guestOptions);
                } else {
                    console.warn('⚠️ guestOptions   ');
                }
                
                //     (selectedOptions.selectedOptions   selectedOptions)
                let pureSelectedOptions = {};
                
                console.log('=== selectedOptionsRaw   ===');
                console.log('selectedOptionsRaw:', selectedOptionsRaw);
                console.log('selectedOptionsRaw :', typeof selectedOptionsRaw);
                console.log('selectedOptionsRaw keys:', Object.keys(selectedOptionsRaw || {}));
                console.log('selectedOptionsRaw.selectedOptions:', selectedOptionsRaw.selectedOptions);
                console.log('selectedOptionsRaw.baggage:', selectedOptionsRaw.baggage);
                console.log('selectedOptionsRaw.breakfast:', selectedOptionsRaw.breakfast);
                console.log('selectedOptionsRaw.wifi:', selectedOptionsRaw.wifi);
                
                // 1: selectedOptions.selectedOptions  ( )
                if (selectedOptionsRaw.selectedOptions && typeof selectedOptionsRaw.selectedOptions === 'object') {
                    pureSelectedOptions = selectedOptionsRaw.selectedOptions;
                    console.log('✅ 1:    pureSelectedOptions:', pureSelectedOptions);
                } 
                // 2: selectedOptionsRaw  baggage, breakfast, wifi  
                else if (selectedOptionsRaw.baggage || selectedOptionsRaw.breakfast || selectedOptionsRaw.wifi) {
                    // customerInfo, seatRequest, otherRequest    
                    pureSelectedOptions = {};
                    Object.keys(selectedOptionsRaw).forEach(key => {
                        if (key !== 'customerInfo' && key !== 'seatRequest' && key !== 'otherRequest' && key !== 'selectedOptions') {
                            pureSelectedOptions[key] = selectedOptionsRaw[key];
                        }
                    });
                    console.log('✅ 2:    pureSelectedOptions:', pureSelectedOptions);
                }
                // 3: selectedOptionsRaw   null 
                else {
                    pureSelectedOptions = {};
                    console.warn('⚠️ 3: selectedOptionsRaw     ');
                    console.warn('⚠️ selectedOptionsRaw :', JSON.stringify(selectedOptionsRaw, null, 2));
                }
                
                // 4: pureSelectedOptions  
                if (!pureSelectedOptions || typeof pureSelectedOptions !== 'object' || Array.isArray(pureSelectedOptions)) {
                    pureSelectedOptions = {};
                    console.warn('⚠️ 4: pureSelectedOptions     ');
                }
                
                // :   
                console.log('===     ===');
                console.log('selectedOptionsRaw:', JSON.stringify(selectedOptionsRaw, null, 2));
                console.log('pureSelectedOptions:', JSON.stringify(pureSelectedOptions, null, 2));
                console.log('baggage:', pureSelectedOptions.baggage);
                console.log('breakfast:', pureSelectedOptions.breakfast);
                console.log('wifi:', pureSelectedOptions.wifi);
                console.log('pureSelectedOptions keys:', Object.keys(pureSelectedOptions));
                
                currentBooking = {
                    bookingId: booking.bookingId,
                    packageId: booking.packageId,
                    packageName: booking.packageName || booking.package_name || '',
                    packagePrice: parseFloat(booking.packagePrice || booking.package_price || 0),
                    childPrice: booking.childPrice !== null && booking.childPrice !== undefined ? parseFloat(booking.childPrice) : null,
                    infantPrice: booking.infantPrice !== null && booking.infantPrice !== undefined ? parseFloat(booking.infantPrice) : null,
                    departureDate: booking.departureDate || booking.departure_date || '',
                    departureTime: booking.departureTime || booking.departure_time || '',
                    adults: parseInt(booking.adults || 0),
                    children: parseInt(booking.children || 0),
                    infants: parseInt(booking.infants || 0),
                    totalAmount: parseFloat(booking.totalAmount || booking.total_amount || 0),
                    selectedRooms: selectedRooms,
                    selectedOptions: pureSelectedOptions,
                    guestOptions: guestOptions, // 동적 옵션 리스트 추가
                    seatRequest: booking.seatRequest || selectedOptionsRaw.seatRequest || '',
                    otherRequest: booking.otherRequest || selectedOptionsRaw.otherRequest || '',
                    customerInfo: customerInfo,
                    finalAmount: parseFloat(booking.totalAmount || booking.total_amount || 0)
                };

                //   :
                // -   :    (product_availability.departureTime) 
                // -   : (packages meeting_time/meetingTime) 
                const normalizeHHMM = (v) => {
                    const s = String(v || '').trim();
                    const m = s.match(/(\d{1,2}):(\d{2})/);
                    if (!m) return '';
                    return `${String(m[1]).padStart(2, '0')}:${m[2]}`;
                };
                try {
                    const dep = String(currentBooking.departureDate || '').trim();
                    const pid = String(currentBooking.packageId || '').trim();
                    if (pid && dep && /^\d{4}-\d{2}-\d{2}$/.test(dep)) {
                        const year = dep.slice(0, 4);
                        const month = String(parseInt(dep.slice(5, 7), 10));
                        const avRes = await fetch(`../backend/api/product_availability.php?id=${encodeURIComponent(pid)}&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`);
                        const avJson = await avRes.json().catch(() => ({}));
                        const items = avJson?.data?.availability || [];
                        const hit = Array.isArray(items) ? items.find(x => String(x.availableDate || '') === dep) : null;
                        const dt = normalizeHHMM(hit?.departureTime || '');
                        if (dt) {
                            currentBooking.departureTime = dt;
                        } else {
                            const pkgRes = await fetch(`../backend/api/packages.php?id=${encodeURIComponent(pid)}`, { credentials: 'same-origin' });
                            const pkgJson = await pkgRes.json().catch(() => ({}));
                            const pkg = pkgJson?.data || null;
                            const mt = normalizeHHMM(pkg?.meeting_time || pkg?.meetingTime || '');
                            if (mt) currentBooking.departureTime = mt;
                        }
                    }
                } catch (e) {
                    // ignore
                }
                
                console.log('✅  currentBooking.selectedOptions:', currentBooking.selectedOptions);
                
                console.log('✅ DB   :', currentBooking);
                
                //   
                await loadTravelersFromDB();
                
                //   
                updateDepartureInfo();
                displayBookingGuests();
                displaySelectedRooms();
                displayCustomerInfo();
                displayTravelerInfo();
                displayAdditionalOptions();
                calculateAndDisplayPayment();
            } else {
                throw new Error(result.message || '    .');
            }
        } catch (error) {
            console.error('   :', error);
            alert(getI18nText('bookingNotFound') + ': ' + error.message);
            window.location.href = '../home.html';
        }
    }
    
    //   DB 
    async function loadTravelersFromDB() {
        if (!bookingId) return;
        
        try {
            console.log('   , bookingId:', bookingId);
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
            
            console.log('travelers.php API  :', response.status, response.ok);
            
            if (response.ok) {
                const result = await response.json();
                console.log('travelers.php API :', result);
                if (result.success && result.travelers) {
                    travelers = result.travelers;
                    console.log('✅ DB   :', travelers);
                } else {
                    console.warn('⚠️   :', result.message || '');
                    travelers = [];
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                console.error('❌    :', response.status, errorData);
                travelers = [];
            }
        } catch (error) {
            console.error('❌    :', error);
            travelers = [];
        }
    }

    //   
    function updateDepartureInfo() {
        const departureInfo = document.querySelector('.departure-info');
        if (currentBooking && currentBooking.departureDate && departureInfo) {
            const date = new Date(currentBooking.departureDate);
            const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
            const locale = currentLang === 'ko' ? 'ko-KR' : currentLang === 'en' ? 'en-US' : 'tl-PH';
            const formattedDate = date.toLocaleDateString(locale, { 
                month: 'numeric', 
                day: 'numeric', 
                weekday: 'short' 
            });
            const time = (currentBooking.departureTime || '').trim();
            const timePart = time ? ` ${time}` : '';
            departureInfo.innerHTML = `${formattedDate}${timePart} <span class="text fw 700">${getI18nText('departure')}</span>`;
            console.log('  :', `${formattedDate}${timePart} ${getI18nText('departure')}`);
        } else if (departureInfo) {
            departureInfo.innerHTML = getI18nText('loadingDeparture');
        }
    }

    //    
    function displayBookingGuests() {
        if (!currentBooking) return;
        
        const guestsList = document.querySelector('.booking-guests');
        if (!guestsList) return;

        let html = '';
        
        // guestOptions가 있으면 동적 옵션 사용, 없으면 기존 방식 사용
        if (currentBooking.guestOptions && Array.isArray(currentBooking.guestOptions) && currentBooking.guestOptions.length > 0) {
            // 동적 옵션 표시 (1단계에서 선택한 옵션명, 수량, 가격)
            currentBooking.guestOptions.forEach((option, index) => {
                const qty = Number(option.qty || 0);
                const unitPrice = Number(option.unitPrice || option.price || 0);
                const optionName = String(option.name || '');
                
                if (qty > 0 && optionName) {
                    const totalPrice = unitPrice * qty;
                    html += `
                        <li class="align both vm ${index > 0 ? 'mt8' : ''}">
                            <div class="text fz14 fw400 lh22 black12">${optionName}x${qty}</div>
                            <p class="text fz14 fw400 lh22 black12">₱${totalPrice.toLocaleString()}</p>
                        </li>
                    `;
                }
            });
        } else {
            // 기존 방식 (adults/children/infants)
            if (currentBooking.adults > 0) {
                const adultPrice = currentBooking.packagePrice || 0;
                const adultTotal = adultPrice * currentBooking.adults;
                html += `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('adult')}x${currentBooking.adults}</div>
                        <p class="text fz14 fw400 lh22 black12">₱${adultTotal.toLocaleString()}</p>
                    </li>
                `;
            }
            
            if (currentBooking.children > 0) {
                // childPrice DB    ( packagePrice * 0.8)
                const childPrice = currentBooking.childPrice || Math.max(currentBooking.packagePrice - 5000, 0);
                const childTotal = childPrice * currentBooking.children;
                html += `
                    <li class="align both vm mt8">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('child')}x${currentBooking.children}</div>
                        <p class="text fz14 fw400 lh22 black12">₱${childTotal.toLocaleString()}</p>
                    </li>
                `;
            }
            
            if (currentBooking.infants > 0) {
                // infantPrice DB    ( packagePrice * 0.1)
                const infantPrice = currentBooking.infantPrice || 9000;
                const infantTotal = infantPrice * currentBooking.infants;
                html += `
                    <li class="align both vm mt8">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('infant')}x${currentBooking.infants}</div>
                        <p class="text fz14 fw400 lh22 black12">₱${infantTotal.toLocaleString()}</p>
                    </li>
                `;
            }
        }

        guestsList.innerHTML = html;
        console.log('    ');
    }

    //    
    function displaySelectedRooms() {
        if (!currentBooking) return;
        
        const roomsList = document.querySelector('.selected-rooms');
        if (!roomsList) return;

        const bookingGuests = (currentBooking?.adults || 0) + (currentBooking?.children || 0);
        const isSingleRoom = (room) => {
            const rid = String(room?.id || room?.roomType || '').toLowerCase();
            const rnm = String(room?.name || '').toLowerCase();
            return room?.isSingleRoom === true || room?.isSingleRoom === 1 || rid === 'single' || rid.includes('single') || rnm.includes('single') || rnm.includes('');
        };
        const getRoomTotalPrice = (room) => {
            if (!room) return 0;
            if (isSingleRoom(room) && bookingGuests <= 1) return 0;
            const tp = Number(room.totalPrice);
            if (Number.isFinite(tp)) return tp;
            const unit = Number(room.price || 0);
            const count = Number(room.count || 0);
            return unit * count;
        };

        let html = '';
        
        if (currentBooking.selectedRooms && Object.keys(currentBooking.selectedRooms).length > 0) {
        Object.values(currentBooking.selectedRooms).forEach(room => {
                const roomTotal = getRoomTotalPrice(room);
                html += `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${room.name}x${room.count}</div>
                        <p class="text fz14 fw400 lh22 black12">₱${roomTotal.toLocaleString()}</p>
                    </li>
                `;
            });
        } else {
            html = `
                <li class="align both vm">
                    <div class="text fz14 fw400 lh22 black12">${getI18nText('roomNotSelected')}</div>
                    <p class="text fz14 fw400 lh22 black12">₱0</p>
                </li>
            `;
        }

        roomsList.innerHTML = html;
        console.log('    ');
    }

    //   
    function displayCustomerInfo() {
        if (!currentBooking) return;
        
        const customerList = document.querySelector('.customer-info');
        if (!customerList) return;

        let html = '';
        
        if (currentBooking.customerInfo) {
            html += `
                <li class="align both vm">
                    <div class="text fz14 fw400 lh22 black12">${getI18nText('name')}</div>
                    <p class="text fz14 fw400 lh22 black12">${currentBooking.customerInfo.name || getI18nText('notEntered')}</p>
                </li>
                <li class="align both vm mt8">
                    <div class="text fz14 fw400 lh22 black12">${getI18nText('email')}</div>
                    <p class="text fz14 fw400 lh22 black12">${currentBooking.customerInfo.email || getI18nText('notEntered')}</p>
                </li>
                <li class="align both vm mt8">
                    <div class="text fz14 fw400 lh22 black12">${getI18nText('phone')}</div>
                    <p class="text fz14 fw400 lh22 black12">${currentBooking.customerInfo.phone || getI18nText('notEntered')}</p>
                </li>
            `;
        } else {
            html = `
                <li class="align both vm">
                    <div class="text fz14 fw400 lh22 black12">${getI18nText('booker_info')}</div>
                    <p class="text fz14 fw400 lh22 black12">${getI18nText('notEntered')}</p>
                </li>
            `;
        }

        customerList.innerHTML = html;
        console.log('   ');
    }

    //    (YYYYMMDD -> YYYY-MM-DD)
    function formatBirthDate(dateStr) {
        if (!dateStr) return '';
        //  YYYY-MM-DD   
        if (dateStr.includes('-')) return dateStr;
        // YYYYMMDD  
        if (dateStr.length === 8) {
            return `${dateStr.substring(0, 4)}-${dateStr.substring(4, 6)}-${dateStr.substring(6, 8)}`;
        }
        return dateStr;
    }
    
    //   
    function calculateAge(birthDate) {
        if (!birthDate) return null;
        const date = formatBirthDate(birthDate);
        if (!date) return null;
        const birth = new Date(date);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }
    
    //    
    function getGenderText(gender) {
        if (!gender) return null;
        const genderUpper = String(gender).toUpperCase().trim();
        
        // M, MALE, ,     
        if (genderUpper === 'M' || genderUpper === 'MALE' || genderUpper === '' || genderUpper === '') {
            return getI18nText('male');
        }
        // F, FEMALE, ,     
        if (genderUpper === 'F' || genderUpper === 'FEMALE' || genderUpper === '' || genderUpper === '') {
            return getI18nText('female');
        }
        
        return null;
    }

    //   
    function displayTravelerInfo() {
        if (!currentBooking) return;
        
        const travelerContainer = document.querySelector('.traveler-info');
        if (!travelerContainer) return;
        
        console.log('displayTravelerInfo , travelers:', travelers);

        let html = '';
        
        if (travelers && travelers.length > 0) {
            const normalizePassportImgSrc = (src) => {
                if (!src) return '';
                const s = String(src).trim();
                if (!s) return '';
                if (s.startsWith('data:')) return s;
                if (s.startsWith('http://') || s.startsWith('https://')) return s;
                // /uploads/...  uploads/...  
                if (s.startsWith('/uploads/')) return s;
                if (s.startsWith('uploads/')) return '/' + s;
                // raw base64 (JPEG/PNG) : DB prefix    
                if (s.startsWith('/9j/')) return 'data:image/jpeg;base64,' + s;
                if (s.startsWith('iVBOR')) return 'data:image/png;base64,' + s;
                //   ( )
                return s;
            };
            // guestOptions에서 옵션명 매핑 생성 (type과 sequence 기반)
            const optionNameMap = {};
            if (currentBooking.guestOptions && Array.isArray(currentBooking.guestOptions)) {
                let adultIdx = 0, childIdx = 0, infantIdx = 0;
                currentBooking.guestOptions.forEach((option, idx) => {
                    const qty = Number(option.qty || 0);
                    const optionName = String(option.name || '');
                    
                    // 각 옵션의 수량만큼 매핑 생성
                    for (let i = 0; i < qty; i++) {
                        if (idx === 0) {
                            // 첫 번째 옵션은 adult로 매핑
                            optionNameMap[`adult_${adultIdx + 1}`] = optionName;
                            adultIdx++;
                        } else if (idx === 1) {
                            // 두 번째 옵션은 child로 매핑
                            optionNameMap[`child_${childIdx + 1}`] = optionName;
                            childIdx++;
                        } else if (idx === 2) {
                            // 세 번째 옵션은 infant로 매핑
                            optionNameMap[`infant_${infantIdx + 1}`] = optionName;
                            infantIdx++;
                        } else {
                            // 4번째 이상 옵션은 순서대로 adult로 매핑 (또는 필요에 따라 조정)
                            optionNameMap[`adult_${adultIdx + 1}`] = optionName;
                            adultIdx++;
                        }
                    }
                });
            }
            
            travelers.forEach((traveler, index) => {
                // 옵션명 매핑에서 찾기
                const optionKey = `${traveler.type}_${traveler.sequence}`;
                const optionName = optionNameMap[optionKey] || null;
                
                // 옵션명이 있으면 옵션명 사용, 없으면 기존 방식 사용
                const typeText = optionName || (traveler.type === 'adult' ? getI18nText('adult') : 
                               traveler.type === 'child' ? getI18nText('childAge') : getI18nText('infantAge'));
                
                //    (isMainTraveler     )
                const isMainTraveler = traveler.isMainTraveler === true || 
                                      traveler.isMainTraveler === 1 || 
                                      (index === 0 && !travelers.some(t => t.isMainTraveler === true || t.isMainTraveler === 1));
                
                html += `
                    <a class="align both vm mt8 btn-folding" href="#none">
                        <div class="align both vm" style="gap: 8px;">
                            <div class="text fz14 fw600 black12 lh22">${typeText}${traveler.sequence}</div>
                            ${isMainTraveler ? `<span class="badge-main-traveler" style="background: #4CAF50; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">${getI18nText('representativeTraveler')}</span>` : ''}
                        </div>
                        <img src="../images/ico_up_black.svg" alt="">
                    </a>
                    <div class="card-type8 pink mt8">
                        <ul>
                            <li class="align both vm">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('title')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.title || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('name')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.firstName || traveler.first_name || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('lastName')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.lastName || traveler.last_name || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('gender')}</div>
                                <p class="text fz14 fw400 lh22 black12">${getGenderText(traveler.gender) || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('age')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.age || calculateAge(traveler.birthDate || traveler.birth_date) || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('birthDate')}</div>
                                <p class="text fz14 fw400 lh22 black12">${formatBirthDate(traveler.birthDate || traveler.birth_date) || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('nationality')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.nationality || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('passportNumber')}</div>
                                <p class="text fz14 fw400 lh22 black12">${traveler.passportNumber || traveler.passport_number || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('passportIssueDate')}</div>
                                <p class="text fz14 fw400 lh22 black12">${formatBirthDate(traveler.passportIssueDate || traveler.passport_issue_date) || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('passportExpiryDate')}</div>
                                <p class="text fz14 fw400 lh22 black12">${formatBirthDate(traveler.passportExpiry || traveler.passport_expiry_date) || getI18nText('notEntered')}</p>
                            </li>
                            <li class="align both mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('passportPhoto')}</div>
                                <div class="" style="width: 94px; height: 94px; background: #EAEAEA;">
                                    ${(() => {
                                        const raw = (traveler.passportImage || traveler.passport_image || '').trim();
                                        const src = normalizePassportImgSrc(raw);
                                        return src ? '<img src="' + src + '" style="width: 100%; height: 100%; object-fit: cover;">' : '';
                                    })()}
                                </div>
                            </li>
                            <li class="align both vm mt8">
                                <div class="text fz14 fw400 lh22 black12">${getI18nText('visaApplication')}</div>
                                <p class="text fz14 fw400 lh22 black12">${(traveler.visaStatus || traveler.visa_required) ? getI18nText('apply') : getI18nText('notApply')}</p>
                            </li>
                        </ul>
                    </div>
                `;
            });
        } else {
            html = `
                <div class="card-type8 pink mt8">
                    <ul>
                        <li class="align both vm">
                            <div class="text fz14 fw400 lh22 black12">${getI18nText('traveler_info')}</div>
                            <p class="text fz14 fw400 lh22 black12">${getI18nText('notEntered')}</p>
                        </li>
                    </ul>
                </div>
            `;
        }
        
        travelerContainer.innerHTML = html;
        
        //        
        setupTravelerAccordion();
        console.log('   ');
    }

    //    
    function displayAdditionalOptions() {
        if (!currentBooking) {
            console.warn('⚠️ displayAdditionalOptions: currentBooking ');
            return;
        }
        
        console.log('=== displayAdditionalOptions  ===');
        console.log('currentBooking:', currentBooking);
        console.log('currentBooking.selectedOptions:', currentBooking.selectedOptions);
        console.log('selectedOptions :', typeof currentBooking.selectedOptions);
        console.log('selectedOptions keys:', Object.keys(currentBooking.selectedOptions || {}));
        console.log('selectedOptions :', JSON.stringify(currentBooking.selectedOptions, null, 2));
        
        //  
        const baggageList = document.querySelector('.baggage-option');
        if (baggageList) {
            if (currentBooking.selectedOptions && currentBooking.selectedOptions.baggage) {
                const baggage = currentBooking.selectedOptions.baggage;
                console.log('✅   :', baggage);
                const baggageName = (typeof baggage === 'object' && baggage.name) ? baggage.name : (typeof baggage === 'string' ? baggage : 'Extra Luggage');
                baggageList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${baggageName}</div>
                    </li>
                `;
                console.log('✅   :', baggageName);
            } else {
                baggageList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('notSelected')}</div>
                    </li>
                `;
                console.log('⚠️    - selectedOptions:', currentBooking.selectedOptions);
                console.log('⚠️    - baggage:', currentBooking.selectedOptions?.baggage);
            }
        } else {
            console.error('❌ baggage-option    ');
        }

        //  
        const breakfastList = document.querySelector('.breakfast-option');
        if (breakfastList) {
            if (currentBooking.selectedOptions && currentBooking.selectedOptions.breakfast) {
                console.log('✅   :', currentBooking.selectedOptions.breakfast);
                breakfastList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('apply')}</div>
                    </li>
                `;
                console.log('✅   : ');
            } else {
                breakfastList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('notApply')}</div>
                    </li>
                `;
                console.log('⚠️   :  - breakfast:', currentBooking.selectedOptions?.breakfast);
            }
        } else {
            console.error('❌ breakfast-option    ');
        }

        //  
        const wifiList = document.querySelector('.wifi-option');
        if (wifiList) {
            if (currentBooking.selectedOptions && currentBooking.selectedOptions.wifi) {
                console.log('✅   :', currentBooking.selectedOptions.wifi);
                wifiList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('apply')}</div>
                    </li>
                `;
                console.log('✅   : ');
            } else {
                wifiList.innerHTML = `
                    <li class="align both vm">
                        <div class="text fz14 fw400 lh22 black12">${getI18nText('notApply')}</div>
                    </li>
                `;
                console.log('⚠️   :  - wifi:', currentBooking.selectedOptions?.wifi);
            }
        } else {
            console.error('❌ wifi-option    ');
        }

        //   
        const seatRequestList = document.querySelector('.seat-request');
        if (seatRequestList) {
            seatRequestList.innerHTML = `
                <li class="align both vm">
                    <div class="text fz14 fw400 lh22 black12">${currentBooking.seatRequest || getI18nText('noRequest')}</div>
                </li>
            `;
            console.log('✅    :', currentBooking.seatRequest || '()');
        } else {
            console.error('❌ seat-request    ');
        }

        //  
        const otherRequestList = document.querySelector('.other-request');
        if (otherRequestList) {
            otherRequestList.innerHTML = `
                <li class="align both vm">
                    <div class="text fz14 fw400 lh22 black12">${currentBooking.otherRequest || getI18nText('noRequest')}</div>
                </li>
            `;
            console.log('✅   :', currentBooking.otherRequest || '()');
        } else {
            console.error('❌ other-request    ');
        }

        console.log('===      ===');
    }

    //     
    function calculateAndDisplayPayment() {
        if (!currentBooking) return;
        
        const paymentDetails = document.querySelector('.payment-details');
        const totalAmountElement = document.querySelector('.total-amount');
        const payButton = document.querySelector('.pay-button');
        
        if (!paymentDetails || !totalAmountElement || !payButton) return;

        //     (DB  ,  )
        let baseAmount = 0;
        if (currentBooking.adults > 0) {
            baseAmount += currentBooking.packagePrice * currentBooking.adults;
        }
        if (currentBooking.children > 0) {
            const childPrice = currentBooking.childPrice || Math.max(currentBooking.packagePrice - 5000, 0);
            baseAmount += childPrice * currentBooking.children;
        }
        if (currentBooking.infants > 0) {
            const infantPrice = currentBooking.infantPrice || 9000;
            baseAmount += infantPrice * currentBooking.infants;
        }

        //   
        let roomAmount = 0;
        if (currentBooking.selectedRooms) {
            Object.values(currentBooking.selectedRooms).forEach(room => {
                // 1  " " (+)  
                const bookingGuests = (currentBooking?.adults || 0) + (currentBooking?.children || 0);
                const rid = String(room?.id || room?.roomType || '').toLowerCase();
                const rnm = String(room?.name || '').toLowerCase();
                const isSingle = room?.isSingleRoom === true || room?.isSingleRoom === 1 || rid === 'single' || rid.includes('single') || rnm.includes('single') || rnm.includes('');
                if (isSingle && bookingGuests <= 1) return; // 1 ( ):   
                const tp = Number(room.totalPrice);
                if (Number.isFinite(tp)) {
                    roomAmount += tp;
                } else {
                    roomAmount += (Number(room.price || 0) * Number(room.count || 0));
                }
            });
        }

        //    
        let optionsAmount = 0;
        if (currentBooking.selectedOptions) {
            Object.values(currentBooking.selectedOptions).forEach(option => {
                optionsAmount += option.price || 0;
            });
        }

        //   (  1%)
        const paymentFee = Math.round(baseAmount * 0.01);
        
        //  (  1%)
        const vat = Math.round(baseAmount * 0.01);
        
        //  
        const totalAmount = baseAmount + roomAmount + optionsAmount + paymentFee + vat;
        
        //   (baseAmount + roomAmount + optionsAmount)
        const productAmount = baseAmount + roomAmount + optionsAmount;

        //   
        let html = `
            <li class="align both vm">
                <div class="text fz14 fw400 lh22 black12">${getI18nText('productAmount')}</div>
                <p class="text fz14 fw400 lh22 black12">₱${productAmount.toLocaleString()}</p>
            </li>
            <li class="align both vm mt8">
                <div class="text fz14 fw400 lh22 black12">${getI18nText('paymentFee')}</div>
                <p class="text fz14 fw400 lh22 black12">₱${paymentFee.toLocaleString()}</p>
            </li>
            <li class="align both vm mt8">
                <div class="text fz14 fw400 lh22 black12">${getI18nText('vat')}</div>
                <p class="text fz14 fw400 lh22 black12">₱${vat.toLocaleString()}</p>
            </li>
        `;

        paymentDetails.innerHTML = html;
        totalAmountElement.textContent = `₱${totalAmount.toLocaleString()}`;
        payButton.textContent = `${getI18nText('pay')} ₱${totalAmount.toLocaleString()}`;

        //     
        currentBooking.finalAmount = totalAmount;

        console.log('   :', {
            baseAmount,
            roomAmount,
            optionsAmount,
            paymentFee,
            vat,
            totalAmount
        });
    }

    //   
    function setupPaymentMethods() {
        const paymentRadio = document.querySelector('input[name="payment"]');
        
        if (paymentRadio) {
            paymentRadio.addEventListener('change', function() {
                selectedPaymentMethod = this.value === 'bank' ? getI18nText('bank_transfer') : getI18nText('credit_card');
                console.log('  :', selectedPaymentMethod);
            });
            
            //  
            selectedPaymentMethod = getI18nText('bank_transfer');
        }
    }

    //   
    function setupAgreementCheckboxes() {
        const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
        
        allCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                validatePaymentForm();
            });
        });

        //    
        const agreeCheck = document.getElementById('agreeCheck');
        if (agreeCheck) {
            agreeCheck.addEventListener('change', function() {
                const isChecked = this.checked;
                allCheckboxes.forEach(cb => {
                    if (cb !== agreeCheck) {
                        cb.checked = isChecked;
                    }
                });
                validatePaymentForm();
            });
        }
        
        //   
        validatePaymentForm();
    }

    //    
    function validatePaymentForm() {
        const payButton = document.querySelector('.pay-button');
        const requiredCheckboxes = document.querySelectorAll('#chk1, #chk2, #agreeCheck2');
        const allRequiredChecked = Array.from(requiredCheckboxes).every(cb => cb.checked);
        
        if (payButton) {
            if (allRequiredChecked) {
                payButton.classList.remove('inactive');
            } else {
                payButton.classList.add('inactive');
            }
        }
    }

    //  
    function setupPaymentButton() {
        const payButton = document.querySelector('.pay-button');
        
        if (payButton) {
            payButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (this.classList.contains('inactive')) {
                    alert(getI18nText('agreeRequired'));
            return;
        }

                console.log('  ');
                processPayment();
            });
        }
    }

    //    
    async function processPayment() {
        const payButton = document.querySelector('.pay-button');
        
        try {
            //   
            payButton.textContent = getI18nText('processingPayment');
            payButton.disabled = true;
            
            console.log('  :', currentBooking);
            
            // SMT   -  bookingId     
            //   
            const paymentData = {
                action: 'process',
                bookingData: {
                    bookingId: currentBooking.bookingId, //  bookingId 
                    packageId: currentBooking.packageId,
                    departureDate: currentBooking.departureDate,
                    // SMT  
                    departureTime: currentBooking.departureTime,
                    adults: currentBooking.adults,
                    children: currentBooking.children,
                    infants: currentBooking.infants,
                    totalAmount: currentBooking.finalAmount,
                    selectedRooms: currentBooking.selectedRooms,
                    // Ensure guestOptions is persisted in DB selectedOptions during payment processing.
                    // (payment.php previously could overwrite selectedOptions; now it preserves when null,
                    // but we also send guestOptions explicitly to be safe.)
                    selectedOptions: {
                        ...(currentBooking.selectedOptions || {}),
                        guestOptions: currentBooking.guestOptions || []
                    },
                    seatRequest: currentBooking.seatRequest,
                    otherRequest: currentBooking.otherRequest,
                    customerInfo: currentBooking.customerInfo,
                    travelers: travelers,
                    accountId: getUserId(),
                    finalPricing: {
                        baseAmount: calculateBaseAmount(),
                        roomAmount: calculateRoomAmount(),
                        optionsAmount: calculateOptionsAmount(),
                        paymentFee: calculatePaymentFee(),
                        vat: calculateVAT(),
                        total_price: currentBooking.finalAmount
                    }
                },
                paymentMethod: selectedPaymentMethod,
                userId: getUserId()
            };
            
            console.log('  :', paymentData);
            
            //  API 
            const response = await fetch('../backend/api/payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(paymentData)
            });
            
            console.log('API  :', response.status);
            
            const responseText = await response.text();
            console.log('API  :', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON  :', e);
                throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
            }
            
            if (result.success) {
                const statusKey = String(result?.data?.statusKey || '').toLowerCase();
                const paymentStatus = String(result?.data?.paymentStatus || '').toLowerCase();
                const isSuspended = statusKey === 'payment_suspended' || paymentStatus === 'pending';
                alert(isSuspended ? getI18nText('paymentSuspended') : getI18nText('paymentCompleted'));
                
                //     ( ID )
                const bookingId = result.data ? result.data.bookingId : result.bookingId;
                console.log('    bookingId:', bookingId);
                
                if (!bookingId) {
                    console.error('bookingId :', result);
                    alert('Booking ID is missing. Please try again.');
                    return;
                }
                
                window.location.href = `reservation-completed.php?bookingId=${bookingId}`;
            } else {
                throw new Error(result.message || getI18nText('paymentError'));
            }
            
        } catch (error) {
            console.error('  :', error);
            alert(getI18nText('paymentError') + ': ' + error.message);
            
            //   
            payButton.textContent = `${getI18nText('pay')} ₱${currentBooking.finalAmount.toLocaleString()}`;
            payButton.disabled = false;
        }
    }

    //   
    function calculateBaseAmount() {
        if (!currentBooking) return 0;
        let baseAmount = 0;
        if (currentBooking.adults > 0) {
            baseAmount += currentBooking.packagePrice * currentBooking.adults;
        }
        if (currentBooking.children > 0) {
            const childPrice = currentBooking.childPrice || Math.max(currentBooking.packagePrice - 5000, 0);
            baseAmount += childPrice * currentBooking.children;
        }
        if (currentBooking.infants > 0) {
            const infantPrice = currentBooking.infantPrice || 9000;
            baseAmount += infantPrice * currentBooking.infants;
        }
        return baseAmount;
    }

    function calculateRoomAmount() {
        if (!currentBooking) return 0;
        let roomAmount = 0;
        if (currentBooking.selectedRooms) {
            Object.values(currentBooking.selectedRooms).forEach(room => {
                if (!room) return;
                // 1  " " (+)
                const bookingGuests = (currentBooking?.adults || 0) + (currentBooking?.children || 0);
                const rid = String(room?.id || room?.roomType || '').toLowerCase();
                const rnm = String(room?.name || '').toLowerCase();
                const isSingle = room?.isSingleRoom === true || room?.isSingleRoom === 1 || rid === 'single' || rid.includes('single') || rnm.includes('single') || rnm.includes('');
                if (isSingle && bookingGuests <= 1) return; // 1 ( ):   

                const tp = Number(room.totalPrice);
                if (Number.isFinite(tp)) roomAmount += tp;
                else roomAmount += (Number(room.price || 0) * Number(room.count || 0));
            });
        }
        return roomAmount;
    }

    function calculateOptionsAmount() {
        if (!currentBooking) return 0;
        let optionsAmount = 0;
        if (currentBooking.selectedOptions) {
            Object.values(currentBooking.selectedOptions).forEach(option => {
                optionsAmount += option.price || 0;
            });
        }
        return optionsAmount;
    }

    function calculatePaymentFee() {
        return Math.round(calculateBaseAmount() * 0.01);
    }

    function calculateVAT() {
        return Math.round(calculateBaseAmount() * 0.01);
    }

    //  ID 
    function getUserId() {
        // localStorage   ID 
        const userId = localStorage.getItem('userId');
        if (!userId) {
            console.error(' ID .  .');
            alert(' .');
            window.location.href = 'login.html';
            return null;
        }
        console.log(' ID:', userId);
        return parseInt(userId);
    }

    // : DB  
    loadBookingFromDB().then(() => {
        setupPaymentMethods();
        setupAgreementCheckboxes();
        setupPaymentButton();
        console.log('reservation-sum-pay.js  ');
    }).catch(error => {
        console.error(' :', error);
    });
});

//    
function setupTravelerAccordion() {
    const travelerContainer = document.querySelector('.traveler-info');
    if (!travelerContainer) return;
    
    //    .btn-folding    
    travelerContainer.querySelectorAll('.btn-folding').forEach(function(btn) {
        //     ( )
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        //    
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            //    .card-type8 
            let cardElement = this.nextElementSibling;
            while (cardElement && !cardElement.classList.contains('card-type8')) {
                cardElement = cardElement.nextElementSibling;
            }
            
            if (cardElement) {
                //   
                const isHidden = cardElement.style.display === 'none' || 
                                 window.getComputedStyle(cardElement).display === 'none';
                
                if (isHidden) {
                    cardElement.style.display = 'block';
                } else {
                    cardElement.style.display = 'none';
                }
                
                //  /
                const img = this.querySelector('img');
                if (img) {
                    img.classList.toggle('active');
                    // ico_up_black.svg ico_down_black.svg  
                    if (cardElement.style.display === 'none') {
                        img.src = '../images/ico_arrow_down_black.svg';
                    } else {
                        img.src = '../images/ico_up_black.svg';
                    }
                }
            }
        });
    });
}
