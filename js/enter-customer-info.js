document.addEventListener('DOMContentLoaded', function() {
    // 뒤로가기 버튼 처리 (enter-customer-info.php 전용)
    const backButton = document.querySelector('.btn-mypage');
    if (backButton && window.location.pathname.includes('enter-customer-info.php')) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // button.js의 이벤트 전파 방지
            
            // URL 파라미터에서 booking_id 가져오기
            const urlParams = new URLSearchParams(window.location.search);
            const bookingId = urlParams.get('booking_id');
            
            if (bookingId) {
                // booking_id가 있으면 select-room.php로 이동
                window.location.href = `select-room.php?booking_id=${bookingId}`;
            } else {
                // booking_id가 없으면 history.back()
                history.back();
            }
        });
    }
    
    // 다국어 텍스트 정의
    const i18nTexts = {
        'ko': {
            bookingNotFound: '예약 정보를 찾을 수 없습니다.',
            departure: '출발',
            adult: '성인',
            child: '아동',
            infant: '유아',
            traveler: '여행자',
            name: '이름',
            email: '이메일',
            phone: '연락처',
            nameRequired: '이름을 입력해주세요.',
            emailInvalid: '이메일 형식이 올바르지 않습니다.',
            phoneInvalid: '연락처 형식이 올바르지 않습니다.',
            next: '다음',
            completed: '입력 완료',
            notCompleted: '입력 전',
            temporarilySaved: '임시저장 되었습니다',
            doubleCheckMessage: '입력한 정보를 다시 확인해주세요.',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress'
        },
        'en': {
            bookingNotFound: 'Booking information not found.',
            departure: 'Departure',
            adult: 'Adult',
            child: 'Child',
            infant: 'Infant',
            traveler: 'Traveler',
            name: 'Name',
            email: 'Email',
            phone: 'Phone',
            nameRequired: 'Please enter your name.',
            emailInvalid: 'Email format is incorrect.',
            phoneInvalid: 'Phone format is incorrect.',
            next: 'Next',
            completed: 'Completed',
            notCompleted: 'Not completed',
            temporarilySaved: 'Temporarily saved',
            doubleCheckMessage: 'Please double-check your information.',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress'
        },
        'tl': {
            bookingNotFound: 'Hindi mahanap ang impormasyon ng booking.',
            departure: 'Alis',
            adult: 'Matanda',
            child: 'Bata',
            infant: 'Sanggol',
            traveler: 'Traveler',
            name: 'Pangalan',
            email: 'Email',
            phone: 'Telepono',
            nameRequired: 'Pakilagay ang inyong pangalan.',
            emailInvalid: 'Mali ang format ng email.',
            phoneInvalid: 'Mali ang format ng telepono.',
            next: 'Susunod',
            completed: 'Tapos na',
            notCompleted: 'Hindi pa tapos',
            temporarilySaved: 'Naka-save na pansamantala',
            doubleCheckMessage: 'Paki-double check ang iyong impormasyon.',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress'
        }
    };

    // 현재 언어에 따른 텍스트 가져오기
    function getI18nText(key) {
        // URL에서 언어 파라미터 먼저 확인
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        
        // URL에 언어가 있으면 localStorage에 저장
        if (urlLang) {
            localStorage.setItem('selectedLanguage', urlLang);
        }

        // Language policy: English default, only en/tl supported
        let currentLang = String(urlLang || localStorage.getItem('selectedLanguage') || 'en').toLowerCase();
        if (currentLang !== 'en' && currentLang !== 'tl') currentLang = 'en';
        const texts = i18nTexts[currentLang] || i18nTexts['en'];
        return texts[key] || key;
    }

    let currentBooking = JSON.parse(localStorage.getItem('currentBooking'));
    let userProfile = JSON.parse(localStorage.getItem('userProfile'));

    // XSS-safe text escape for dynamic labels
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    // 중앙 토스트(간단) - 예약 단계 임시저장 안내용
    function showCenterToast(message) {
        const existingToast = document.querySelector('.toast-center');
        if (existingToast) existingToast.remove();
        const toast = document.createElement('div');
        toast.className = 'toast-center';
        toast.textContent = String(message || '');
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 1500);
    }

    // URL 파라미터에서 예약 정보 읽기 (localStorage가 없을 경우 대비)
    const urlParams = new URLSearchParams(window.location.search);
    console.log('URL 파라미터:', urlParams.toString());
    console.log('URL 파라미터 개수:', urlParams.size);
    
    if (!currentBooking && urlParams.size > 0) {
        currentBooking = {
            packageId: urlParams.get('package_id'),
            departureDate: urlParams.get('departure_date'),
            departureTime: urlParams.get('departure_time') || '12:20',
            packageName: urlParams.get('package_name'),
            packagePrice: parseFloat(urlParams.get('package_price')) || 0,
            adults: Number.isFinite(parseInt(urlParams.get('adults'))) ? parseInt(urlParams.get('adults')) : 0,
            children: parseInt(urlParams.get('children')) || 0,
            infants: parseInt(urlParams.get('infants')) || 0,
            totalAmount: parseFloat(urlParams.get('total_amount')) || 0,
            selectedRooms: urlParams.get('selected_rooms') ? JSON.parse(urlParams.get('selected_rooms')) : {}
        };
        
        // localStorage에 저장
        localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
        console.log('예약 정보를 URL에서 읽어와서 localStorage에 저장:', currentBooking);
    }
    
    // 디버깅: localStorage 데이터 확인
    console.log('Current booking from localStorage:', currentBooking);
    console.log('User profile from localStorage:', userProfile);
    
    if (!currentBooking) {
        alert(getI18nText('bookingNotFound'));
        window.location.href = '../home.html';
        return;
    }

    const checkbox = document.getElementById('chk1');
    const nameInput = document.getElementById('txt1');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const countryCodeSelect = document.querySelector('.select-type1');

    // Persist customer info between steps (요구사항 id 46/38):
    // If user goes to traveler info and comes back, previously typed booker info must remain.
    function persistCustomerInfoToLocal() {
        try {
            if (!currentBooking) return;
            currentBooking.customerInfo = {
                name: (nameInput?.value || '').trim(),
                email: (emailInput?.value || '').trim(),
                phone: (phoneInput?.value || '').trim(),
                country_code: countryCodeSelect?.value || '+63',
                load_from_account: !!(checkbox && checkbox.checked)
            };
            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
        } catch (_) {}
    }

    // keep in sync as user types
    try {
        nameInput?.addEventListener('input', persistCustomerInfoToLocal);
        emailInput?.addEventListener('input', persistCustomerInfoToLocal);
        phoneInput?.addEventListener('input', persistCustomerInfoToLocal);
        countryCodeSelect?.addEventListener('change', persistCustomerInfoToLocal);
        checkbox?.addEventListener('change', persistCustomerInfoToLocal);
    } catch (_) {}

    // 재확인 팝업(요구사항): Double-check / Reservation in progress
    function ensureDoubleCheckPopup() {
        let layer = document.getElementById('doubleCheckLayer');
        let popup = document.getElementById('doubleCheckPopup');
        if (layer && popup) return { layer, popup };

        layer = document.createElement('div');
        layer.id = 'doubleCheckLayer';
        layer.className = 'layer';

        popup = document.createElement('div');
        popup.id = 'doubleCheckPopup';
        popup.className = 'alert-modal';
        popup.style.display = 'none';

        popup.innerHTML = `
            <div class="guide" id="doubleCheckPopupMsg"></div>
            <div class="align gap12">
                <button class="btn line lg" type="button" id="doubleCheckCancelBtn" style="width: 100%;"></button>
                <button class="btn primary lg" type="button" id="doubleCheckProceedBtn" style="width: 100%;"></button>
            </div>
        `;

        document.body.appendChild(layer);
        document.body.appendChild(popup);
        return { layer, popup };
    }

    function showDoubleCheckPopup(onProceed) {
        const { layer, popup } = ensureDoubleCheckPopup();
        const msgEl = document.getElementById('doubleCheckPopupMsg');
        const cancelBtn = document.getElementById('doubleCheckCancelBtn');
        const proceedBtn = document.getElementById('doubleCheckProceedBtn');

        if (msgEl) msgEl.textContent = getI18nText('doubleCheckMessage');
        if (cancelBtn) cancelBtn.textContent = getI18nText('doubleCheck');
        if (proceedBtn) proceedBtn.textContent = getI18nText('reservationInProgress');

        layer.classList.add('active');
        popup.style.display = 'flex';

        const hide = () => {
            try { layer.classList.remove('active'); } catch (_) {}
            try { popup.style.display = 'none'; } catch (_) {}
        };

        if (cancelBtn) cancelBtn.onclick = () => hide();
        if (proceedBtn) {
            proceedBtn.onclick = async () => {
                hide();
                try { await (onProceed && onProceed()); } catch (e) { console.error(e); }
            };
        }
        layer.onclick = () => hide();
    }

    function updateDepartureInfo() {
        const departureInfo = document.querySelector('.departure-info');
        console.log('updateDepartureInfo 호출됨');
        
        // currentBooking에서 출발 정보 읽기 (우선순위)
        let departureDate = currentBooking?.departureDate;
        let departureTime = currentBooking?.departureTime || urlParams.get('departure_time') || '12:20';
        
        // currentBooking에 없으면 URL 파라미터에서 읽기
        if (!departureDate) {
            departureDate = urlParams.get('departure_date');
        }
        
        console.log('출발 날짜 (currentBooking 우선):', departureDate);
        console.log('출발 시간:', departureTime);
        
        if (departureDate && departureInfo) {
            console.log('출발 날짜 읽기 성공:', departureDate);
            const date = new Date(departureDate);
            console.log('parsed date:', date);
            
            // Language policy: English default
            const formattedDate = date.toLocaleDateString('en-US', { 
                month: 'numeric', 
                day: 'numeric', 
                weekday: 'short' 
            });
            console.log('formattedDate:', formattedDate, 'time:', departureTime);
            
            departureInfo.innerHTML = `${formattedDate} ${departureTime} <span class="text fw 700">${getI18nText('departure')}</span>`;
            console.log('출발 정보 업데이트 완료');
        } else if (departureInfo) {
            console.log('출발 정보가 없어서 기본 메시지 표시');
            departureInfo.innerHTML = 'Loading departure information...';
        } else {
            console.log('departureInfo element를 찾을 수 없음');
        }
    }

    async function loadUserProfile(preserveExistingValues = false) {
        try {
            // 저장된 customerInfo 값 확인 (preserveExistingValues가 true일 때)
            let savedName = '';
            let savedEmail = '';
            let savedPhone = '';
            let savedCountryCode = '';
            
            if (preserveExistingValues) {
                try {
                    const saved = JSON.parse(localStorage.getItem('currentBooking') || 'null');
                    if (saved && saved.customerInfo) {
                        savedName = String(saved.customerInfo.name || '').trim();
                        savedEmail = String(saved.customerInfo.email || '').trim();
                        savedPhone = String(saved.customerInfo.phone || '').trim();
                        savedCountryCode = String(saved.customerInfo.country_code || '').trim();
                    }
                } catch (_) {}
            }
            
            // 로그인한 사용자 정보 가져오기
            const userInfo = JSON.parse(localStorage.getItem('userInfo') || '{}');
            const userId = localStorage.getItem('userId');
            
            if (userId && userInfo.accountId) {
                // API에서 최신 사용자 정보 가져오기
                const response = await fetch('../backend/api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_profile',
                        accountId: userId
                    })
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.profile) {
                        const profile = data.profile;
                        // 저장된 값이 있으면 저장된 값 우선 사용, 없으면 프로필 값 사용
                        nameInput.value = savedName || `${profile.firstName || ''} ${profile.lastName || ''}`.trim();
                        emailInput.value = savedEmail || profile.email || userInfo.email || '';
                        phoneInput.value = savedPhone || profile.phoneNumber || '';
                        
                        if (profile.countryCode) {
                            countryCodeSelect.value = savedCountryCode || profile.countryCode;
                        }
                        
                        console.log('사용자 프로필 로드 완료:', profile);
                        return;
                    }
                }
            }
            
            // API 실패 시 localStorage의 기본 정보 사용
            if (userInfo.accountId) {
                nameInput.value = savedName || `${userInfo.firstName || ''} ${userInfo.lastName || ''}`.trim();
                emailInput.value = savedEmail || userInfo.email || '';
                phoneInput.value = savedPhone || userInfo.phoneNumber || '';
                
                console.log('localStorage 사용자 정보 로드 완료:', userInfo);
            }
            
        } catch (error) {
            console.error('사용자 프로필 로드 오류:', error);
            
            // 오류 시 localStorage의 기본 정보 사용
            const userInfo = JSON.parse(localStorage.getItem('userInfo') || '{}');
            if (userInfo.accountId) {
                // 저장된 값이 있으면 저장된 값 우선 사용
                let savedName = '';
                let savedEmail = '';
                let savedPhone = '';
                if (preserveExistingValues) {
                    try {
                        const saved = JSON.parse(localStorage.getItem('currentBooking') || 'null');
                        if (saved && saved.customerInfo) {
                            savedName = String(saved.customerInfo.name || '').trim();
                            savedEmail = String(saved.customerInfo.email || '').trim();
                            savedPhone = String(saved.customerInfo.phone || '').trim();
                        }
                    } catch (_) {}
                }
                
                nameInput.value = savedName || `${userInfo.firstName || ''} ${userInfo.lastName || ''}`.trim();
                emailInput.value = savedEmail || userInfo.email || '';
                phoneInput.value = savedPhone || userInfo.phoneNumber || '';
            }
        }
    }

    function setupAccountInfoToggle() {
        // 페이지 로드 시 체크박스가 이미 체크되어 있으면 프로필 로드 (저장된 값 유지)
        if (checkbox && checkbox.checked) {
            loadUserProfile(true).then(() => {
                // 저장된 customerInfo 값이 있으면 저장된 값 우선 사용
                try {
                    const saved = JSON.parse(localStorage.getItem('currentBooking') || 'null');
                    if (saved && saved.customerInfo) {
                        const ci = saved.customerInfo;
                        if (nameInput && ci.name) {
                            nameInput.value = ci.name;
                            nameInput.dispatchEvent(new Event('input'));
                        }
                        if (emailInput && ci.email) {
                            emailInput.value = ci.email;
                            emailInput.dispatchEvent(new Event('input'));
                        }
                        if (phoneInput && ci.phone) {
                            phoneInput.value = ci.phone;
                            phoneInput.dispatchEvent(new Event('input'));
                        }
                        if (countryCodeSelect && ci.country_code) {
                            countryCodeSelect.value = ci.country_code;
                        }
                    }
                } catch (_) {}
                setTimeout(() => {
                    updateNextButtonState().catch(console.error);
                }, 100);
            });
        }
        
        checkbox.addEventListener('change', async function() {
            if (this.checked) {
                // 사용자가 체크박스를 체크한 경우에는 저장된 값 유지하지 않고 프로필 로드
                await loadUserProfile(false);
                // 프로필 로드 후 버튼 상태 업데이트
                setTimeout(() => {
                    updateNextButtonState().catch(console.error);
                }, 100);
            } else {
                clearForm();
                // 필드 값 지운 후 버튼 상태 업데이트
                setTimeout(() => {
                    updateNextButtonState().catch(console.error);
                }, 100);
            }
        });
    }

    function clearForm() {
        nameInput.value = '';
        emailInput.value = '';
        phoneInput.value = '';
        countryCodeSelect.value = '+63';
        
        // input 이벤트 발생시켜 버튼 상태 업데이트 트리거
        nameInput.dispatchEvent(new Event('input'));
        emailInput.dispatchEvent(new Event('input'));
        phoneInput.dispatchEvent(new Event('input'));
    }

    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function validatePhone(phone) {
        // 하이픈, 공백, 괄호를 제거하고 숫자만 추출
        const cleanPhone = phone.replace(/[-\s()]/g, '');

        // general phone pattern (10-15 digits)
        const generalPhoneRegex = /^\d{10,15}$/;
        return generalPhoneRegex.test(cleanPhone);
    }

    function showValidationError(input, message) {
        const errorDiv = input.parentNode.querySelector('.reded') || input.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('reded')) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }
    }

    function hideValidationError(input) {
        const errorDiv = input.parentNode.querySelector('.reded') || input.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('reded')) {
            errorDiv.style.display = 'none';
        }
    }

    function setupValidation() {
        emailInput.addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                showValidationError(this, getI18nText('emailInvalid'));
            } else {
                hideValidationError(this);
            }
        });

        phoneInput.addEventListener('blur', function() {
            if (this.value && !validatePhone(this.value)) {
                showValidationError(this, getI18nText('phoneInvalid'));
            } else {
                hideValidationError(this);
            }
        });

        emailInput.addEventListener('input', function() {
            if (validateEmail(this.value)) {
                hideValidationError(this);
            }
        });

        phoneInput.addEventListener('input', function() {
            if (validatePhone(this.value)) {
                hideValidationError(this);
            }
        });
    }

    async function loadTravelerInfo() {
        try {
            // booking_id를 URL 파라미터에서 먼저 확인
            const urlBookingId = urlParams.get('booking_id');
            const finalBookingId = urlBookingId ||
                                 currentBooking?.bookingId ||
                                 currentBooking?.tempId ||
                                 (currentBooking?.bookingId && currentBooking.bookingId.startsWith('TEMP') ? currentBooking.bookingId : null) ||
                                 'temp';
            
            console.log('여행자 정보 API 로드 시작:', {
                urlBookingId,
                currentBookingId: currentBooking?.bookingId,
                tempId: currentBooking?.tempId,
                finalBookingId: finalBookingId
            });
            
            const response = await fetch('../backend/api/travelers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'getByBooking',
                    bookingId: finalBookingId
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('API에서 받은 여행자 정보:', data);
                if (data.success && data.travelers && Array.isArray(data.travelers) && data.travelers.length > 0) {
                    // 타입별로 순서를 매기기
                    const adults = Number.isFinite(parseInt(urlParams.get('adults'))) ? parseInt(urlParams.get('adults')) : (Number.isFinite(parseInt(currentBooking?.adults)) ? parseInt(currentBooking?.adults) : 0);
                    const children = parseInt(urlParams.get('children')) || currentBooking?.children || 0;
                    let adultSeq = 1, childSeq = 1, infantSeq = 1;
                    
                    const convertedTravelers = data.travelers.map(t => {
                        const type = (t.type === 'Adult' || t.type === 'adult') ? 'adult' : 
                                     ((t.type === 'Child' || t.type === 'child') ? 'child' : 'infant');
                        let sequence = 1;
                        
                        if (type === 'adult') {
                            sequence = adultSeq++;
                        } else if (type === 'child') {
                            sequence = childSeq++;
                        } else {
                            sequence = infantSeq++;
                        }
                        
                        return {
                            type: type,
                            sequence: sequence,
                            first_name: t.firstName || t.first_name || '',
                            last_name: t.lastName || t.last_name || '',
                            birth_date: t.birthDate || t.birth_date || '',
                            nationality: t.nationality || '',
                            passport_number: t.passportNumber || t.passport_number || '',
                            title: t.title || '',
                            gender: t.gender || '',
                            passport_issue_date: t.passportIssueDate || t.passport_issue_date || '',
                            passport_expiry: t.passportExpiry || t.passport_expiry || '',
                            visa_status: t.visaStatus || t.visa_status || '',
                            special_requests: t.specialRequests || t.special_requests || '',
                            is_main_traveler: t.isMainTraveler || t.is_main_traveler || false
                        };
                    });
                    
                    console.log('변환된 여행자 정보 (DB에서):', convertedTravelers);
                    updateTravelerList(convertedTravelers);
                } else {
                    // API에서 여행자 정보가 없으면 (새로운 예약이므로) 빈 배열로 표시
                    console.log('API에서 여행자 정보 없음 (새로운 예약)');
                    updateTravelerList([]);
                }
            } else {
                // API 호출 실패 시에도 빈 배열 사용 (새로운 예약일 가능성)
                console.log('API 호출 실패, 새로운 예약으로 간주하고 빈 배열 사용');
                updateTravelerList([]);
            }
        } catch (error) {
            console.error('Error loading traveler info:', error);
            // 네트워크 오류 시에도 빈 배열 사용 (새로운 예약일 가능성)
            console.log('에러 발생, 새로운 예약으로 간주하고 빈 배열 사용');
            updateTravelerList([]);
        }
    }

    function updateTravelerList(travelers = []) {
        const travelerList = document.querySelector('.traveler-list');
        if (!travelerList) return;

        // Prefer dynamic guestOptions (DB selectedOptions.guestOptions) if available.
        const guestOptions = Array.isArray(currentBooking?.guestOptions) ? currentBooking.guestOptions : [];
        const totalGuestsFromOptions = guestOptions.reduce((sum, o) => sum + (Number(o?.qty ?? o?.quantity ?? 0) || 0), 0);

        // Fallback to legacy counts
        const adults = (totalGuestsFromOptions > 0)
            ? totalGuestsFromOptions
            : (Number.isFinite(parseInt(urlParams.get('adults')))
                ? parseInt(urlParams.get('adults'))
                : (Number.isFinite(parseInt(currentBooking?.adults)) ? parseInt(currentBooking?.adults) : 0));
        const children = (totalGuestsFromOptions > 0) ? 0 : (parseInt(urlParams.get('children')) || parseInt(currentBooking?.children) || 0);
        const infants = (totalGuestsFromOptions > 0) ? 0 : (parseInt(urlParams.get('infants')) || parseInt(currentBooking?.infants) || 0);
        
        console.log('인원 수:', { adults, children, infants });
        console.log('사용할 여행자 정보 (DB에서):', travelers);

        // booking_id가 있으면 booking_id, type, index만 포함, 없으면 기존 방식 사용
        const urlBookingId = urlParams.get('booking_id');
        const finalBookingId = urlBookingId || currentBooking?.bookingId || currentBooking?.tempId;
        
        let baseParams;
        if (finalBookingId) {
            // booking_id가 있으면 필요한 파라미터만 포함
            baseParams = new URLSearchParams();
            baseParams.set('booking_id', finalBookingId);
            if (urlParams.get('lang')) baseParams.set('lang', urlParams.get('lang'));
        } else {
            // booking_id가 없으면 기존 방식 (모든 파라미터 포함)
            baseParams = new URLSearchParams();
            if (urlParams.get('package_id')) baseParams.set('package_id', urlParams.get('package_id'));
            if (urlParams.get('departure_date')) baseParams.set('departure_date', urlParams.get('departure_date'));
            if (urlParams.get('departure_time')) baseParams.set('departure_time', urlParams.get('departure_time'));
            if (urlParams.get('package_name')) baseParams.set('package_name', urlParams.get('package_name'));
            if (urlParams.get('package_price')) baseParams.set('package_price', urlParams.get('package_price'));
            if (urlParams.get('adults')) baseParams.set('adults', urlParams.get('adults'));
            if (urlParams.get('children')) baseParams.set('children', urlParams.get('children'));
            if (urlParams.get('infants')) baseParams.set('infants', urlParams.get('infants'));
            if (urlParams.get('total_amount')) baseParams.set('total_amount', urlParams.get('total_amount'));
            if (urlParams.get('selected_rooms')) baseParams.set('selected_rooms', urlParams.get('selected_rooms'));
            if (urlParams.get('lang')) baseParams.set('lang', urlParams.get('lang'));
        }

        // SMT 수정 시작 - 여행자 정보가 완료되었는지 확인하는 함수 (camelCase/snake_case 모두 지원)
        function isTravelerComplete(traveler) {
            if (!traveler) return false;
            const firstName = traveler.first_name || traveler.firstName || '';
            const lastName = traveler.last_name || traveler.lastName || '';
            const birthDate = traveler.birth_date || traveler.birthDate || '';
            const nationality = traveler.nationality || '';
            const passportNumber = traveler.passport_number || traveler.passportNumber || '';

            return firstName && lastName && birthDate && nationality && passportNumber;
        }
        // SMT 수정 완료

        let html = '';

        // New: If guestOptions exist, render traveler rows by option names (label),
        // while keeping type=adult and index=global sequence for backend compatibility.
        if (guestOptions.length > 0 && totalGuestsFromOptions > 0) {
            let globalIndex = 1;
            for (let oi = 0; oi < guestOptions.length; oi++) {
                const opt = guestOptions[oi] || {};
                const optName = String(opt.name || opt.optionName || opt.title || '').trim() || 'Traveler';
                const qty = Number(opt.qty ?? opt.quantity ?? 0) || 0;
                for (let j = 0; j < qty; j++) {
                    const traveler = travelers.find(t => t.type === 'adult' && t.sequence === globalIndex);
                    const isComplete = traveler && isTravelerComplete(traveler);
                    const status = isComplete ? getI18nText('completed') : getI18nText('notCompleted');
                    const statusClass = isComplete ? 'completed' : '';

                    const travelerParams = new URLSearchParams(baseParams.toString());
                    travelerParams.set('type', 'adult');
                    travelerParams.set('index', String(globalIndex));
                    travelerParams.set('label', optName);

                    html += `
                        <li class="align both vm py12">
                            <div class="text fz14 fw500 lh22 black12">${escapeHtml(optName)}x${j + 1}</div>
                            <a class="text fz12 fw400 lh16 gray96 btn-input ${statusClass}" href="enter-traveler-info.php?${travelerParams.toString()}">${status}</a>
                        </li>
                    `;
                    globalIndex++;
                }
            }

            travelerList.innerHTML = html;
            console.log('여행자 정보 업데이트 완료(guestOptions):', { totalGuestsFromOptions, guestOptions });
            updateNextButtonState().catch(console.error);
            return;
        }

        // 성인 여행자 생성
        for (let i = 0; i < adults; i++) {
            const traveler = travelers.find(t => t.type === 'adult' && t.sequence === i + 1);
            const isComplete = traveler && isTravelerComplete(traveler);
            const status = isComplete ? getI18nText('completed') : getI18nText('notCompleted');
            const statusClass = isComplete ? 'completed' : '';
            
            // 여행자 정보 링크에 예약 정보 파라미터 포함
            const travelerParams = new URLSearchParams(baseParams.toString());
            travelerParams.set('type', 'adult');
            travelerParams.set('index', (i + 1).toString());
            
            html += `
                <li class="align both vm py12">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('adult')}${i + 1}</div>
                    <a class="text fz12 fw400 lh16 gray96 btn-input ${statusClass}" href="enter-traveler-info.php?${travelerParams.toString()}">${status}</a>
                </li>
            `;
        }

        // 아동 여행자 생성
        for (let i = 0; i < children; i++) {
            const traveler = travelers.find(t => t.type === 'child' && t.sequence === i + 1);
            const isComplete = traveler && isTravelerComplete(traveler);
            const status = isComplete ? getI18nText('completed') : getI18nText('notCompleted');
            const statusClass = isComplete ? 'completed' : '';
            
            // 여행자 정보 링크에 예약 정보 파라미터 포함
            const travelerParams = new URLSearchParams(baseParams.toString());
            travelerParams.set('type', 'child');
            travelerParams.set('index', (i + 1).toString());
            
            html += `
                <li class="align both vm py12">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('child')}${i + 1}</div>
                    <a class="text fz12 fw400 lh16 gray96 btn-input ${statusClass}" href="enter-traveler-info.php?${travelerParams.toString()}">${status}</a>
                </li>
            `;
        }

        // 유아 여행자 생성
        for (let i = 0; i < infants; i++) {
            const traveler = travelers.find(t => t.type === 'infant' && t.sequence === i + 1);
            const isComplete = traveler && isTravelerComplete(traveler);
            const status = isComplete ? getI18nText('completed') : getI18nText('notCompleted');
            const statusClass = isComplete ? 'completed' : '';
            
            // 여행자 정보 링크에 예약 정보 파라미터 포함
            const travelerParams = new URLSearchParams(baseParams.toString());
            travelerParams.set('type', 'infant');
            travelerParams.set('index', (i + 1).toString());
            
            html += `
                <li class="align both vm py12">
                    <div class="text fz14 fw500 lh22 black12">${getI18nText('infant')}${i + 1}</div>
                    <a class="text fz12 fw400 lh16 gray96 btn-input ${statusClass}" href="enter-traveler-info.php?${travelerParams.toString()}">${status}</a>
                </li>
            `;
        }

        travelerList.innerHTML = html;
        console.log('여행자 정보 업데이트 완료:', { adults, children, infants });
        
        // 여행자 정보 업데이트 후 버튼 상태 확인
        updateNextButtonState().catch(console.error);
    }
    
    // 모든 여행자 정보가 완료되었는지 확인하는 함수 (DB에서 확인)
    async function areAllTravelersComplete() {
        const guestOptions = Array.isArray(currentBooking?.guestOptions) ? currentBooking.guestOptions : [];
        const totalGuestsFromOptions = guestOptions.reduce((sum, o) => sum + (Number(o?.qty ?? o?.quantity ?? 0) || 0), 0);

        const adults = (totalGuestsFromOptions > 0)
            ? totalGuestsFromOptions
            : (Number.isFinite(parseInt(urlParams.get('adults')))
                ? parseInt(urlParams.get('adults'))
                : (Number.isFinite(parseInt(currentBooking?.adults)) ? parseInt(currentBooking?.adults) : 0));
        const children = (totalGuestsFromOptions > 0) ? 0 : (parseInt(urlParams.get('children')) || parseInt(currentBooking?.children) || 0);
        const infants = (totalGuestsFromOptions > 0) ? 0 : (parseInt(urlParams.get('infants')) || parseInt(currentBooking?.infants) || 0);
        const totalTravelers = adults + children + infants;
        
        // DB에서 여행자 정보 확인
        const urlBookingId = urlParams.get('booking_id');
        const bookingId = urlBookingId || currentBooking?.bookingId || currentBooking?.tempId || null;
        
        if (!bookingId || bookingId === 'temp') {
            return false; // bookingId가 없으면 미완료로 간주
        }
        
        try {
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
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.travelers && Array.isArray(data.travelers)) {
                    // 타입별로 순서 매기기
                    let adultSeq = 1, childSeq = 1, infantSeq = 1;
                    const travelerList = data.travelers.map(t => {
                        const type = (t.type === 'Adult' || t.type === 'adult') ? 'adult' : 
                                     ((t.type === 'Child' || t.type === 'child') ? 'child' : 'infant');
                        let sequence = 1;
                        
                        if (type === 'adult') {
                            sequence = adultSeq++;
                        } else if (type === 'child') {
                            sequence = childSeq++;
                        } else {
                            sequence = infantSeq++;
                        }
                        
                        return {
                            type: type,
                            sequence: sequence,
                            first_name: t.firstName || t.first_name || ''
                        };
                    });
                    
                    // 모든 여행자 정보가 입력되었는지 확인
                    let completedCount = 0;
                    // guestOptions 기반일 때는 모두 adult 타입으로 저장/조회(호환성 유지)
                    if (totalGuestsFromOptions > 0) {
                        for (let i = 0; i < totalGuestsFromOptions; i++) {
                            const traveler = travelerList.find(t => t.type === 'adult' && t.sequence === i + 1);
                            if (traveler && traveler.first_name) completedCount++;
                        }
                    } else {
                        for (let i = 0; i < adults; i++) {
                            const traveler = travelerList.find(t => t.type === 'adult' && t.sequence === i + 1);
                            if (traveler && traveler.first_name) {
                                completedCount++;
                            }
                        }
                        for (let i = 0; i < children; i++) {
                            const traveler = travelerList.find(t => t.type === 'child' && t.sequence === i + 1);
                            if (traveler && traveler.first_name) {
                                completedCount++;
                            }
                        }
                        for (let i = 0; i < infants; i++) {
                            const traveler = travelerList.find(t => t.type === 'infant' && t.sequence === i + 1);
                            if (traveler && traveler.first_name) {
                                completedCount++;
                            }
                        }
                    }
                    
                    console.log('여행자 완료 상태 확인:', {
                        completedCount,
                        totalTravelers,
                        adults,
                        children,
                        infants,
                        travelerList: travelerList.length
                    });
                    
                    const allComplete = completedCount === totalTravelers;
                    console.log('모든 여행자 완료 여부:', allComplete);
                    
                    return allComplete;
                }
            }
        } catch (error) {
            console.error('Error checking travelers:', error);
        }
        
        return false;
    }
    
    async function updateNextButtonState() {
        const nextBtn = document.querySelector('.btn.primary.lg');
        if (!nextBtn) {
            console.warn('Next 버튼을 찾을 수 없습니다');
            return;
        }
        
        // 고객 정보 확인
        const name = nameInput?.value.trim() || '';
        const email = emailInput?.value.trim() || '';
        const phone = phoneInput?.value.trim() || '';
        const isCustomerInfoComplete = name && email && phone;
        
        console.log('버튼 상태 확인 - 고객 정보:', {
            name: !!name,
            email: !!email,
            phone: !!phone,
            isCustomerInfoComplete
        });
        
        // 여행자 정보 확인 (DB에서)
        const isTravelersComplete = await areAllTravelersComplete();
        
        console.log('버튼 상태 확인 - 여행자 정보:', {
            isTravelersComplete
        });
        
        const isEnabled = isCustomerInfoComplete && isTravelersComplete;
        
        console.log('버튼 활성화 상태:', {
            isCustomerInfoComplete,
            isTravelersComplete,
            isEnabled,
            name,
            email,
            phone
        });
        
        if (isEnabled) {
            nextBtn.classList.remove('inactive');
            nextBtn.style.pointerEvents = 'auto';
            nextBtn.style.opacity = '1';
        } else {
            nextBtn.classList.add('inactive');
            nextBtn.style.pointerEvents = 'none';
            nextBtn.style.opacity = '0.5';
        }
    }

    function setupNextButton() {
        const nextBtn = document.querySelector('.btn.primary.lg');
        console.log('다음 버튼 찾기:', nextBtn);
        
        if (nextBtn) {
            // 입력 필드 변경 시마다 버튼 상태 업데이트
            if (nameInput) nameInput.addEventListener('input', updateNextButtonState);
            if (nameInput) nameInput.addEventListener('blur', updateNextButtonState);
            if (emailInput) emailInput.addEventListener('input', updateNextButtonState);
            if (emailInput) emailInput.addEventListener('blur', updateNextButtonState);
            if (phoneInput) phoneInput.addEventListener('input', updateNextButtonState);
            if (phoneInput) phoneInput.addEventListener('blur', updateNextButtonState);
            
            nextBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                console.log('다음 버튼 클릭됨');
                
                if (!validateForm()) {
                    console.log('폼 검증 실패');
                    return;
                }
                
                // DB에서 여행자 정보 확인
                const urlBookingId = urlParams.get('booking_id');
                const bookingId = urlBookingId || currentBooking?.bookingId || currentBooking?.tempId || null;
                
                if (!bookingId || bookingId === 'temp') {
                    alert('모든 여행자 정보를 입력해주세요.');
                    return;
                }
                
                const isTravelersComplete = await areAllTravelersComplete();
                if (!isTravelersComplete) {
                    alert('모든 여행자 정보를 입력해주세요.');
                    return;
                }
                
                console.log('폼 검증 성공, 다음 단계로 이동');

                const customerInfo = {
                    name: nameInput.value.trim(),
                    email: emailInput.value.trim(),
                    phone: phoneInput.value.trim(),
                    country_code: countryCodeSelect.value,
                    load_from_account: checkbox.checked
                };

                // 요구사항: 다음 버튼 클릭 시 재확인 팝업 → Double-check(닫기) / Reservation in progress(진행)
                showDoubleCheckPopup(async () => {
                    // DB에 저장 (임시 저장)
                    try {
                        const response = await fetch('../backend/api/save-temp-booking.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'save',
                                bookingId: bookingId,
                                customerInfo: customerInfo
                            })
                        });
                        
                        const result = await response.json();
                        console.log('저장 API 응답:', result);
                        
                        if (response.ok && result.success) {
                            console.log('고객 정보 저장 완료:', customerInfo);
                            showCenterToast(getI18nText('temporarilySaved'));
                            // booking_id로만 이동
                            window.location.href = `add-option.php?booking_id=${bookingId}`;
                        } else {
                            const errorMessage = result.message || result.error || '저장 중 오류가 발생했습니다.';
                            console.error('저장 실패:', errorMessage);
                            alert(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error saving customer info:', error);
                        alert('저장 중 오류가 발생했습니다: ' + error.message);
                    }
                });
            });
            
            // 초기 버튼 상태 설정
            updateNextButtonState().catch(console.error);
        } else {
            console.error('다음 버튼을 찾을 수 없습니다');
        }
    }

    function validateForm() {
        let isValid = true;
        
        console.log('폼 검증 시작:', {
            name: nameInput.value,
            email: emailInput.value,
            phone: phoneInput.value
        });
        
        if (!nameInput.value.trim()) {
            console.log('이름 검증 실패:', nameInput.value);
            showValidationError(nameInput, getI18nText('nameRequired'));
            alert(getI18nText('nameRequired'));
            isValid = false;
        }

        if (!emailInput.value.trim()) {
            console.log('이메일 검증 실패:', emailInput.value);
            showValidationError(emailInput, getI18nText('email'));
            alert('Please enter your email.');
            isValid = false;
        } else if (!validateEmail(emailInput.value)) {
            console.log('이메일 형식 검증 실패:', emailInput.value);
            showValidationError(emailInput, getI18nText('emailInvalid'));
            alert('Email format is incorrect.\nExample: example@email.com');
            isValid = false;
        }

        if (!phoneInput.value.trim()) {
            console.log('연락처 검증 실패:', phoneInput.value);
            showValidationError(phoneInput, 'Please enter your phone number.');
            alert('Please enter your phone number.');
            isValid = false;
        } else if (!validatePhone(phoneInput.value)) {
            console.log('연락처 형식 검증 실패:', phoneInput.value);
            showValidationError(phoneInput, getI18nText('phoneInvalid'));
            alert('Phone format is incorrect.');
            isValid = false;
        }

        console.log('폼 검증 결과:', isValid);
        return isValid;
    }

    async function loadCountryCodes() {
        try {
            const response = await fetch('../backend/api/countries.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    updateCountryCodeSelect(data.countries);
                } else {
                    // API 실패 시 기본 국가 코드 설정
                    setDefaultCountryCodes();
                }
            } else {
                // API 호출 실패 시 기본 국가 코드 설정
                setDefaultCountryCodes();
            }
        } catch (error) {
            console.error('Error loading country codes:', error);
            // 네트워크 오류 시에도 기본 국가 코드 설정
            setDefaultCountryCodes();
        }
    }

    function setDefaultCountryCodes() {
        const defaultCountries = [
            { code: '+63', name: 'Philippines' },
            { code: '+82', name: 'South Korea' },
            { code: '+1', name: 'United States' },
            { code: '+86', name: 'China' },
            { code: '+81', name: 'Japan' }
        ];
        updateCountryCodeSelect(defaultCountries);
    }

    function updateCountryCodeSelect(countries) {
        countryCodeSelect.innerHTML = countries.map(country => 
            `<option value="${country.code}">${country.code} ${country.name}</option>`
        ).join('');
        
        countryCodeSelect.value = '+63';
    }

    updateDepartureInfo();
    
    // DB에서 예약 정보와 고객 정보 로드
    (async () => {
        const urlBookingId = urlParams.get('booking_id');
        if (urlBookingId) {
            try {
                // Keep any locally typed customerInfo (not yet saved to DB) across reload/back.
                let localCustomerInfo = null;
                try {
                    const localSaved = JSON.parse(localStorage.getItem('currentBooking') || 'null');
                    if (localSaved && localSaved.customerInfo) localCustomerInfo = localSaved.customerInfo;
                } catch (_) {}

                const response = await fetch('../backend/api/booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_booking',
                        bookingId: urlBookingId
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('booking.php API 응답:', result);
                    
                    // API 응답 형식 확인 (result.data 또는 result.booking)
                    const booking = result.data || result.booking;
                    if (result.success && booking) {
                        currentBooking = booking;
                        console.log('DB에서 로드한 예약 정보:', currentBooking);

                        // Parse selectedOptions (JSON) once and expose guestOptions for traveler list rendering
                        try {
                            let so = null;
                            if (booking.selectedOptions && typeof booking.selectedOptions === 'string') {
                                so = JSON.parse(booking.selectedOptions);
                            } else if (booking.selectedOptions && typeof booking.selectedOptions === 'object') {
                                so = booking.selectedOptions;
                            }
                            if (so && typeof so === 'object') {
                                if (Array.isArray(so.guestOptions)) {
                                    currentBooking.guestOptions = so.guestOptions.map((o) => ({
                                        name: o?.name ?? o?.optionName ?? o?.title ?? '',
                                        unitPrice: o?.unitPrice ?? o?.price ?? o?.optionPrice ?? null,
                                        qty: Number(o?.qty ?? o?.quantity ?? 0) || 0,
                                    }));
                                }
                                currentBooking.__selectedOptionsObj = so;
                            }
                        } catch (e) {
                            console.warn('selectedOptions JSON parse failed (guestOptions):', e);
                        }
                        
                        // customerInfo 복원
                        if (booking.selectedOptions) {
                            try {
                                const selectedOptions = JSON.parse(booking.selectedOptions);
                                if (selectedOptions.customerInfo) {
                                    currentBooking.customerInfo = selectedOptions.customerInfo;
                                    // Prefer locally typed values over DB-selectedOptions (요구사항 id 46)
                                    const customerInfo = { ...(selectedOptions.customerInfo || {}) };
                                    try {
                                        if (localCustomerInfo && typeof localCustomerInfo === 'object') {
                                            if (String(localCustomerInfo.name || '').trim()) customerInfo.name = String(localCustomerInfo.name || '').trim();
                                            if (String(localCustomerInfo.email || '').trim()) customerInfo.email = String(localCustomerInfo.email || '').trim();
                                            if (String(localCustomerInfo.phone || '').trim()) customerInfo.phone = String(localCustomerInfo.phone || '').trim();
                                            if (String(localCustomerInfo.country_code || '').trim()) customerInfo.country_code = String(localCustomerInfo.country_code || '').trim();
                                            if (typeof localCustomerInfo.load_from_account === 'boolean') customerInfo.load_from_account = localCustomerInfo.load_from_account;
                                            currentBooking.customerInfo = customerInfo;
                                        }
                                    } catch (_) {}
                                    
                                    // 고객 정보를 폼에 표시
                                    setTimeout(() => {
                                        if (nameInput && customerInfo.name) {
                                            nameInput.value = customerInfo.name;
                                            // input 이벤트 발생시켜 버튼 상태 업데이트
                                            nameInput.dispatchEvent(new Event('input'));
                                        }
                                        if (emailInput && customerInfo.email) {
                                            emailInput.value = customerInfo.email;
                                            emailInput.dispatchEvent(new Event('input'));
                                        }
                                        if (phoneInput && customerInfo.phone) {
                                            phoneInput.value = customerInfo.phone;
                                            phoneInput.dispatchEvent(new Event('input'));
                                        }
                                        if (countryCodeSelect && customerInfo.country_code) {
                                            countryCodeSelect.value = customerInfo.country_code;
                                        }
                                        if (checkbox !== null && customerInfo.load_from_account !== undefined) {
                                            checkbox.checked = customerInfo.load_from_account;
                                            // 체크박스가 체크되어 있으면 프로필 로드 (저장된 값 유지)
                                            if (checkbox.checked) {
                                                loadUserProfile(true).then(() => {
                                                    // 프로필 로드 후에도 저장된 값이 있으면 저장된 값 우선 사용
                                                    if (nameInput && customerInfo.name) {
                                                        nameInput.value = customerInfo.name;
                                                        nameInput.dispatchEvent(new Event('input'));
                                                    }
                                                    if (emailInput && customerInfo.email) {
                                                        emailInput.value = customerInfo.email;
                                                        emailInput.dispatchEvent(new Event('input'));
                                                    }
                                                    if (phoneInput && customerInfo.phone) {
                                                        phoneInput.value = customerInfo.phone;
                                                        phoneInput.dispatchEvent(new Event('input'));
                                                    }
                                                    if (countryCodeSelect && customerInfo.country_code) {
                                                        countryCodeSelect.value = customerInfo.country_code;
                                                    }
                                                    // 버튼 상태 업데이트
                                                    setTimeout(() => {
                                                        updateNextButtonState().catch(console.error);
                                                    }, 100);
                                                });
                                            }
                                        }
                                        
                                        console.log('고객 정보 복원 완료:', customerInfo);

                                        // 로컬에도 동기화(뒤로가기/새로고침 대비)
                                        try {
                                            currentBooking.customerInfo = {
                                                name: nameInput?.value?.trim() || customerInfo.name || '',
                                                email: emailInput?.value?.trim() || customerInfo.email || '',
                                                phone: phoneInput?.value?.trim() || customerInfo.phone || '',
                                                country_code: countryCodeSelect?.value || customerInfo.country_code || '+63',
                                                load_from_account: checkbox?.checked || false
                                            };
                                            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
                                        } catch (_) {}
                                        
                                        // 버튼 상태 업데이트
                                        setTimeout(() => {
                                            updateNextButtonState().catch(console.error);
                                        }, 200);
                                    }, 100);
                                }
                            } catch (e) {
                                console.error('selectedOptions 파싱 오류:', e);
                            }
                        } else {
                            // 요구사항(id 46): 뒤로 왔을 때 입력값이 계정정보로 덮어써지지 않도록,
                            // 1) bookings.contactEmail/contactPhone 이 있으면 그 값을 우선 복원
                            // 2) 둘 다 없을 때만 계정 프로필을 로드
                            console.log('selectedOptions가 없음. bookings 필드 기반 복원 시도');
                            try {
                                // If user typed locally, prefer it first.
                                if (localCustomerInfo && typeof localCustomerInfo === 'object') {
                                    const ln = String(localCustomerInfo.name || '').trim();
                                    const le = String(localCustomerInfo.email || '').trim();
                                    const lp = String(localCustomerInfo.phone || '').trim();
                                    const lcc = String(localCustomerInfo.country_code || '').trim() || '+63';
                                    const hasAny = !!(ln || le || lp);
                                    if (hasAny) {
                                        if (nameInput && ln) nameInput.value = ln;
                                        if (emailInput && le) { emailInput.value = le; emailInput.dispatchEvent(new Event('input')); }
                                        if (phoneInput && lp) { phoneInput.value = lp; phoneInput.dispatchEvent(new Event('input')); }
                                        if (countryCodeSelect && lcc) countryCodeSelect.value = lcc;
                                        if (checkbox && typeof localCustomerInfo.load_from_account === 'boolean') {
                                            checkbox.checked = localCustomerInfo.load_from_account;
                                            // 체크박스가 체크되어 있으면 프로필 로드 (저장된 값 유지)
                                            if (checkbox.checked) {
                                                loadUserProfile(true).then(() => {
                                                    // 프로필 로드 후에도 저장된 값 우선 사용 (체크박스 상태 유지)
                                                    if (nameInput && ln) nameInput.value = ln;
                                                    if (emailInput && le) { emailInput.value = le; emailInput.dispatchEvent(new Event('input')); }
                                                    if (phoneInput && lp) { phoneInput.value = lp; phoneInput.dispatchEvent(new Event('input')); }
                                                    if (countryCodeSelect && lcc) countryCodeSelect.value = lcc;
                                                    // 버튼 상태 업데이트
                                                    setTimeout(() => {
                                                        updateNextButtonState().catch(console.error);
                                                    }, 100);
                                                });
                                            }
                                        }
                                        try {
                                            currentBooking.customerInfo = {
                                                name: (nameInput?.value || '').trim(),
                                                email: (emailInput?.value || '').trim(),
                                                phone: (phoneInput?.value || '').trim(),
                                                country_code: countryCodeSelect?.value || '+63',
                                                load_from_account: !!checkbox?.checked
                                            };
                                            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
                                        } catch (_) {}
                                        setTimeout(() => updateNextButtonState().catch(console.error), 200);
                                        return;
                                    }
                                }

                                const ce = (booking.contactEmail || booking.accountEmail || '').toString().trim();
                                const cp = (booking.contactPhone || '').toString().trim();

                                let cc = '+63';
                                let pn = '';
                                if (cp) {
                                    // 형태: "+63 9123456789" / "+639123456789" / "639123456789"
                                    const m = cp.match(/^(\+\d{1,4})\s*(.*)$/);
                                    if (m) {
                                        cc = m[1];
                                        pn = (m[2] || '').replace(/[^\d]/g, '');
                                    } else {
                                        pn = cp.replace(/[^\d]/g, '');
                                    }
                                }

                                if (ce || pn) {
                                    if (emailInput && ce) { emailInput.value = ce; emailInput.dispatchEvent(new Event('input')); }
                                    if (countryCodeSelect && cc) { countryCodeSelect.value = cc; }
                                    if (phoneInput && pn) { phoneInput.value = pn; phoneInput.dispatchEvent(new Event('input')); }
                                    if (checkbox) checkbox.checked = false; // 계정정보 자동 로드로 덮어쓰기 방지

                                    try {
                                        currentBooking.customerInfo = {
                                            name: nameInput?.value?.trim() || '',
                                            email: emailInput?.value?.trim() || '',
                                            phone: phoneInput?.value?.trim() || '',
                                            country_code: countryCodeSelect?.value || '+63',
                                            load_from_account: false
                                        };
                                        localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
                                    } catch (_) {}

                                    setTimeout(() => updateNextButtonState().catch(console.error), 200);
                                } else {
                                    console.log('bookings 필드에도 값이 없어 사용자 프로필에서 로드 시도');
                                    loadUserProfile().then(() => {
                                        setTimeout(() => {
                                            updateNextButtonState().catch(console.error);
                                        }, 200);
                                    });
                                }
                            } catch (e) {
                                console.warn('bookings 필드 복원 실패, 사용자 프로필로 fallback:', e);
                                loadUserProfile().then(() => {
                                    setTimeout(() => {
                                        updateNextButtonState().catch(console.error);
                                    }, 200);
                                });
                            }
                        }
                        
                        // 출발 정보 업데이트 (departureDate가 있으면)
                        if (booking.departureDate) {
                            updateDepartureInfo();
                        }
                    }
                } else {
                    console.error('예약 정보 로드 실패:', response.status);
                }
            } catch (error) {
                console.error('Error loading booking:', error);
            }
        }
        
        setupAccountInfoToggle();
        setupValidation();
        setupNextButton();
        loadTravelerInfo();
        loadCountryCodes();
    })();
});