document.addEventListener('DOMContentLoaded', function() {
    //   
    const i18nTexts = {
        'ko': {
            notSelected: '',
            bookingNotFound: '    .',
            temporarilySaved: ' '
        },
        'en': {
            notSelected: 'Not Selected',
            bookingNotFound: 'Booking information not found.',
            temporarilySaved: 'Temporarily saved'
        },
        'tl': {
            notSelected: 'Hindi Pili',
            bookingNotFound: 'Hindi mahanap ang impormasyon ng booking.',
            temporarilySaved: 'Naka-save na pansamantala'
        }
    };
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


    //     
    function getI18nText(key) {
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        if (urlLang) localStorage.setItem('selectedLanguage', urlLang);
        const storedLang = localStorage.getItem('selectedLanguage');
        // Language policy: English default, only en/tl supported
        let currentLang = String(urlLang || storedLang || 'en').toLowerCase();
        if (currentLang !== 'en' && currentLang !== 'tl') currentLang = 'en';
        const texts = i18nTexts[currentLang] || i18nTexts['en'];
        return texts[key] || key;
    }

    let currentBooking = JSON.parse(localStorage.getItem('currentBooking')) || {};
    let packageOptions = [];
    let selectedOptions = {};
    
    // bookingId : URL  > currentBooking.bookingId > currentBooking.tempId
    const urlParams = new URLSearchParams(window.location.search);
    const urlBookingId = urlParams.get('booking_id');
    const bookingId = urlBookingId || 
                     currentBooking?.bookingId || 
                     currentBooking?.tempId || 
                     null;
    
    console.log('add-option - bookingId:', bookingId);
    
    if (!bookingId && !currentBooking.packageId) {
        alert(getI18nText('bookingNotFound'));
        window.location.href = '../home.html';
        return;
    }

    const baggageSelect = document.querySelector('.custom-select');
    const breakfastButtons = document.querySelectorAll('.btn-apply');
    const wifiButtons = document.querySelectorAll('.btn-wifi');
    // textarea ID   ( )
    const seatRequestTextarea = document.getElementById('seatRequest') || document.querySelectorAll('textarea.textarea-type2')[0];
    const otherRequestTextarea = document.getElementById('otherRequest') || document.querySelectorAll('textarea.textarea-type2')[1];
    const nextButton = document.getElementById('next-btn') || document.querySelector('.btn.primary.lg');
    
    console.log('Textarea  :', {
        seatRequestTextarea: !!seatRequestTextarea,
        otherRequestTextarea: !!otherRequestTextarea,
        seatRequestValue: seatRequestTextarea?.value || '()',
        otherRequestValue: otherRequestTextarea?.value || '()'
    });

    async function loadPackageOptions() {
        try {
            // packageId : bookingId  DB,  currentBooking
            let packageId = currentBooking?.packageId;
            
            if (bookingId && !packageId) {
                // bookingId DB packageId 
                try {
                    // booking.php POST  action=get_booking  (Invalid action )
                    const bookingResponse = await fetch('../backend/api/booking.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'get_booking',
                            bookingId: bookingId
                        })
                    });
                    
                    if (bookingResponse.ok) {
                        const bookingData = await bookingResponse.json();
                        const bookingObj = bookingData.booking || bookingData.data || null;
                        if (bookingData.success && bookingObj) {
                            packageId = bookingObj.packageId;
                            // currentBooking 
                            currentBooking = { ...currentBooking, ...bookingObj };
                        }
                    }
                } catch (e) {
                    console.error('   :', e);
                }
            }
            
            if (!packageId) {
                throw new Error('Package ID   .');
            }
            
            const response = await fetch('../backend/api/package-options.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'getByPackage',
                    packageId: packageId,
                    bookingId: bookingId, // bookingId 
                    lang: localStorage.getItem('selectedLanguage') || 'ko' //   
                })
            });

            console.log('package-options.php API  :', response.status, response.ok);
            
            if (response.ok) {
                const data = await response.json();
                console.log('package-options.php API  :', data);
                console.log('data.success:', data.success);
                console.log('data.options:', data.options);
                console.log('data.options :', typeof data.options);
                console.log('data.options :', data.options?.length);
                
                if (data.success) {
                    packageOptions = data.options || [];
                    console.log('✅ API  :', packageOptions);
                    console.log('✅  :', packageOptions.length);
                    
                    //     
                    if (packageOptions.length === 0) {
                        console.warn('⚠️ API  ,   ');
                        packageOptions = getDefaultOptions();
                    }
                    
                    if (data.warning) {
                        console.warn('API :', data.warning);
                    }
                    
                    // setupBaggageOptions   packageOptions 
                    console.log('setupBaggageOptions   packageOptions:', packageOptions);
                    setupBaggageOptions();
                } else {
                    const errorMsg = data.message || data.error || 'API  ';
                    console.error('❌ API  :', errorMsg);
                    throw new Error(errorMsg);
                }
            } else {
                //   JSON    
                let errorMsg = 'Network response was not ok';
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.message || errorData.error || errorMsg;
                    console.error('API  :', errorData);
                } catch (e) {
                    console.error('  :', response.status, response.statusText);
                }
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error('API  ,   :', error);
            // API      
            packageOptions = getDefaultOptions();
        }
    }

    //    
    function getDefaultOptions() {
        const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
        const texts = {
            'ko': {
                baggage1: '  5kg ',
                baggage2: '  10kg ',
                baggage3: '  15kg ',
                breakfast: ' ',
                wifi: ' '
            },
            'en': {
                baggage1: 'Extra Cabin Baggage 5kg',
                baggage2: 'Extra Cabin Baggage 10kg',
                baggage3: 'Extra Cabin Baggage 15kg',
                breakfast: 'Breakfast Request',
                wifi: 'Wi-Fi Rental'
            },
            'tl': {
                baggage1: 'Dagdag na Cabin Baggage 5kg',
                baggage2: 'Dagdag na Cabin Baggage 10kg',
                baggage3: 'Dagdag na Cabin Baggage 15kg',
                breakfast: 'Kahilingan sa Almusal',
                wifi: 'Paupahang Wi-Fi'
            }
        };
        
        const langTexts = texts[currentLang] || texts['ko'];
        
        return [
            { id: 'baggage1', category: 'baggage', name: langTexts.baggage1, price: 5000 },
            { id: 'baggage2', category: 'baggage', name: langTexts.baggage2, price: 8000 },
            { id: 'baggage3', category: 'baggage', name: langTexts.baggage3, price: 12000 },
            { id: 'breakfast', category: 'meal', name: langTexts.breakfast, price: 3000 },
            { id: 'wifi', category: 'wifi', name: langTexts.wifi, price: 2000 }
        ];
    }

    function setupBaggageOptions() {
        console.log('setupBaggageOptions , packageOptions:', packageOptions);
        
        // packageOptions    
        if (packageOptions.length === 0) {
            packageOptions = getDefaultOptions();
            console.log('setupBaggageOptions   :', packageOptions);
        }
        
        const baggageOptions = packageOptions.filter(opt => opt.category === 'baggage');
        console.log('baggageOptions:', baggageOptions);
        
        const selectTriggerDiv = baggageSelect?.querySelector('.select-trigger');
        const selectTriggerSpan = baggageSelect?.querySelector('.select-trigger span');
        const optionsList = baggageSelect?.querySelector('.select-options');

        if (!selectTriggerDiv || !selectTriggerSpan || !optionsList) {
            console.error('baggageSelect    ', {
                selectTriggerDiv: !!selectTriggerDiv,
                selectTriggerSpan: !!selectTriggerSpan,
                optionsList: !!optionsList
            });
            return;
        }

        if (baggageOptions.length > 0) {
            let optionsHtml = `<li data-value="">${getI18nText('notSelected')}</li>`;
            optionsHtml += baggageOptions.map(option => 
                `<li data-value="${option.id}" data-price="${option.price}">${option.name}</li>`
            ).join('');
            
            console.log('   HTML:', optionsHtml);
            console.log(' :', baggageOptions.length);
            
            // HTML  (   )
            optionsList.innerHTML = optionsHtml;
            console.log('  HTML ');
            console.log('  optionsList.children.length:', optionsList.children.length);
            
            //       
            const oldOptionsList = optionsList;
            const newOptionsList = oldOptionsList.cloneNode(true);
            oldOptionsList.parentNode.replaceChild(newOptionsList, oldOptionsList);
            
            console.log('    newOptionsList.children.length:', newOptionsList.children.length);
            
            //  li      ( )
            const liElements = newOptionsList.querySelectorAll('li');
            console.log('✅ li  :', liElements.length);
            
            liElements.forEach((li, index) => {
                li.style.cursor = 'pointer';
                li.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const selectedText = this.textContent.trim();
                    console.log('✅ li  - index:', index, 'text:', selectedText);
                    
                    // selectTriggerSpan  (  - DOM   )
                    const currentTriggerSpan = baggageSelect.querySelector('.select-trigger span');
                    if (currentTriggerSpan) {
                        currentTriggerSpan.textContent = selectedText;
                        console.log('✅ triggerSpan :', selectedText);
                    }
                    
                    // optionsList 
                    const currentOptionsList = baggageSelect.querySelector('.select-options');
                    if (currentOptionsList) {
                        currentOptionsList.style.display = 'none';
                        console.log('✅   ');
                    }
                    
                    const optionId = this.dataset.value;
                    console.log('✅   :', optionId, selectedText);
                    
                    if (optionId) {
                        //   
                        const selectedOption = baggageOptions.find(opt => String(opt.id) === String(optionId));
                        if (selectedOption) {
                            selectedOptions.baggage = {
                                id: selectedOption.id,
                                name: selectedOption.name
                                // price 필드 제거: 수하물 옵션은 요금 추가 없음
                            };
                            console.log('✅ selectedOptions.baggage :', selectedOptions.baggage);
                        } else {
                            console.warn('⚠️     :', optionId);
                        }
                    } else {
                        delete selectedOptions.baggage;
                        console.log('✅ selectedOptions.baggage  (Not Selected)');
                    }
                    
                    updateBookingOptions();
                });
            });
            
            //      (fallback)
            newOptionsList.addEventListener('click', function(e) {
                console.log('✅ optionsList   :', e.target.tagName, e.target);
                e.stopPropagation();
            });
        } else {
            console.warn('  ');
        }

        // selectTrigger   (  /)
        //        
        const newSelectTriggerDiv = selectTriggerDiv.cloneNode(true);
        selectTriggerDiv.parentNode.replaceChild(newSelectTriggerDiv, selectTriggerDiv);
        
        //   select-trigger div  (span   div)
        newSelectTriggerDiv.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const currentOptionsList = baggageSelect.querySelector('.select-options');
            if (currentOptionsList) {
                const isVisible = currentOptionsList.style.display === 'block';
                currentOptionsList.style.display = isVisible ? 'none' : 'block';
                console.log('✅    :', isVisible ? '' : '');
                console.log('✅    :', !isVisible ? '' : '');
                console.log('✅   children:', currentOptionsList.children.length);
                if (currentOptionsList.children.length > 0) {
                    console.log('✅   :', currentOptionsList.children[0].textContent);
                    console.log('✅  :', Array.from(currentOptionsList.children).map(li => li.textContent));
                }
            }
        });
        
        //     
        document.addEventListener('click', function(e) {
            if (baggageSelect && !baggageSelect.contains(e.target)) {
                const currentOptionsList = baggageSelect.querySelector('.select-options');
                if (currentOptionsList && currentOptionsList.style.display === 'block') {
                    currentOptionsList.style.display = 'none';
                    console.log('✅     ');
                }
            }
        });
    }

    function setupBreakfastButtons() {
        console.log('setupBreakfastButtons , packageOptions:', packageOptions);
        
        // packageOptions    
        if (packageOptions.length === 0) {
            packageOptions = getDefaultOptions();
            console.log('packageOptions    :', packageOptions);
        }
        
        // meal   breakfast   
        const breakfastOption = packageOptions.find(opt => 
            opt.category === 'meal' || 
            (opt.name && (opt.name.toLowerCase().includes('breakfast') || opt.name.toLowerCase().includes('')))
        );
        console.log('breakfastOption:', breakfastOption);
        console.log(' packageOptions:', packageOptions.map(opt => ({ id: opt.id, category: opt.category, name: opt.name })));
        
        breakfastButtons.forEach(button => {
            button.addEventListener('click', function() {
                breakfastButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const btnText = this.textContent.trim();
                console.log('  :', btnText, 'breakfastOption :', !!breakfastOption);
                console.log(' active:', this.classList.contains('active'));
                
                // breakfastOption   
                const defaultBreakfastOption = breakfastOption || packageOptions.find(opt => opt.id === 'breakfast') || { id: 'breakfast', category: 'meal', name: ' ', price: 3000 };
                
                // active  , "", "Apply", "Yes"     
                const isApplyButton = this.classList.contains('active') && (
                    btnText === '' || 
                    btnText.toLowerCase() === 'apply' || 
                    btnText.toLowerCase() === 'yes' ||
                    btnText.toLowerCase().includes('apply') ||
                    btnText.toLowerCase().includes('yes') ||
                    btnText.toLowerCase().includes('')
                );
                
                console.log('isApplyButton:', isApplyButton, 'btnText:', btnText);
                
                //   () active ,   () active 
                const buttonIndex = Array.from(breakfastButtons).indexOf(this);
                const isFirstButton = buttonIndex === 0;
                
                if (isFirstButton && this.classList.contains('active')) {
                    //     active  
                    selectedOptions.breakfast = {
                        id: defaultBreakfastOption.id,
                        name: defaultBreakfastOption.name,
                        price: defaultBreakfastOption.price
                    };
                    console.log('selectedOptions.breakfast :', selectedOptions.breakfast);
                } else if (!isFirstButton && this.classList.contains('active')) {
                    //     active   ()
                    delete selectedOptions.breakfast;
                    console.log('selectedOptions.breakfast  ()');
                } else {
                    //  active  
                    delete selectedOptions.breakfast;
                    console.log('selectedOptions.breakfast  ()');
                }
                
                updateBookingOptions();
            });
        });
    }

    function setupWifiButtons() {
        console.log('setupWifiButtons , packageOptions:', packageOptions);
        
        // packageOptions    
        if (packageOptions.length === 0) {
            packageOptions = getDefaultOptions();
            console.log('packageOptions    :', packageOptions);
        }
        
        const wifiOption = packageOptions.find(opt => opt.category === 'wifi' || (opt.category === 'service' && (opt.name.includes('') || opt.name.toLowerCase().includes('wifi'))));
        console.log('wifiOption:', wifiOption);
        
        wifiButtons.forEach(button => {
            button.addEventListener('click', function() {
                wifiButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const btnText = this.textContent.trim();
                console.log('  :', btnText, 'wifiOption :', !!wifiOption);
                console.log(' active:', this.classList.contains('active'));
                
                // wifiOption   
                const defaultWifiOption = wifiOption || packageOptions.find(opt => opt.id === 'wifi') || { id: 'wifi', category: 'wifi', name: ' ', price: 2000 };
                
                //   () active ,   () active 
                const buttonIndex = Array.from(wifiButtons).indexOf(this);
                const isFirstButton = buttonIndex === 0;
                
                if (isFirstButton && this.classList.contains('active')) {
                    //     active  
                    selectedOptions.wifi = {
                        id: defaultWifiOption.id,
                        name: defaultWifiOption.name,
                        price: defaultWifiOption.price
                    };
                    console.log('selectedOptions.wifi :', selectedOptions.wifi);
                } else if (!isFirstButton && this.classList.contains('active')) {
                    //     active   ()
                    delete selectedOptions.wifi;
                    console.log('selectedOptions.wifi  ()');
                } else {
                    //  active  
                    delete selectedOptions.wifi;
                    console.log('selectedOptions.wifi  ()');
                }
                
                updateBookingOptions();
            });
        });
    }

    function updateBookingOptions() {
        console.log('updateBookingOptions , selectedOptions:', selectedOptions);
        currentBooking.selectedOptions = selectedOptions;
        currentBooking.seatRequest = seatRequestTextarea ? seatRequestTextarea.value.trim() : '';
        currentBooking.otherRequest = otherRequestTextarea ? otherRequestTextarea.value.trim() : '';
        
        localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
        console.log('localStorage :', currentBooking.selectedOptions);
    }
    
    function setupNextButton() {
        if (nextButton) {
            nextButton.addEventListener('click', async function(e) {
                e.preventDefault();
                console.log('  ');
                
                updateBookingOptions();
                
                if (seatRequestTextarea && seatRequestTextarea.value.length > 500) {
                    alert('   500  .');
                    return;
                }
                
                if (otherRequestTextarea && otherRequestTextarea.value.length > 500) {
                    alert('  500  .');
                    return;
                }
                
                // bookingId  DB 
                if (bookingId && bookingId !== 'temp') {
                    try {
                        //   
                        console.log(' selectedOptions:', selectedOptions);
                        console.log('selectedOptions keys:', Object.keys(selectedOptions));
                        
                        // textarea    (Next   )
                        const seatRequestTextareaCheck = document.getElementById('seatRequest') || document.querySelectorAll('textarea.textarea-type2')[0];
                        const otherRequestTextareaCheck = document.getElementById('otherRequest') || document.querySelectorAll('textarea.textarea-type2')[1];
                        
                        const seatRequestValue = seatRequestTextareaCheck ? seatRequestTextareaCheck.value.trim() : '';
                        const otherRequestValue = otherRequestTextareaCheck ? otherRequestTextareaCheck.value.trim() : '';
                        
                        console.log('=== Next    textarea  ===');
                        console.log('seatRequestTextareaCheck:', !!seatRequestTextareaCheck);
                        console.log('otherRequestTextareaCheck:', !!otherRequestTextareaCheck);
                        console.log('seatRequestValue :', seatRequestTextareaCheck?.value);
                        console.log('seatRequestValue (trim):', seatRequestValue, '(:', seatRequestValue.length, ')');
                        console.log('otherRequestValue :', otherRequestTextareaCheck?.value);
                        console.log('otherRequestValue (trim):', otherRequestValue, '(:', otherRequestValue.length, ')');
                        
                        const optionsData = {
                            selectedOptions: selectedOptions,
                            seatRequest: seatRequestValue,
                            otherRequest: otherRequestValue
                        };
                        
                        console.log(' optionsData:', optionsData);
                        console.log(' JSON:', JSON.stringify({
                            action: 'save',
                            bookingId: bookingId,
                            selectedOptions: optionsData
                        }));
                        
                        const response = await fetch('../backend/api/save-temp-booking.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'save',
                                bookingId: bookingId,
                                selectedOptions: optionsData
                            })
                        });
                        
                        const result = await response.json();
                        console.log('  API :', result);
                        console.log(' bookingId:', result.bookingId);
                        
                        if (response.ok && result.success) {
                            console.log('✅   ');
                            console.log(' :', {
                                selectedOptions: selectedOptions,
                                seatRequest: seatRequestValue,
                                otherRequest: otherRequestValue
                            });
                            
                            //  ()
                            showCenterToast(getI18nText('temporarilySaved'));
                            // UX/요구사항: 토스트가 실제로 보이도록 최소 지연 후 이동
                            setTimeout(() => {
                                navigateToNextStep();
                            }, 800);
                        } else {
                            const errorMessage = result.message || result.error || '   .';
                            console.error('❌  :', errorMessage);
                            console.error(' :', response.status);
                            // -336:   /    
                            console.warn('    .');
                            showCenterToast(getI18nText('temporarilySaved'));
                            setTimeout(() => {
                                navigateToNextStep();
                            }, 800);
                        }
                    } catch (error) {
                        console.error('Error saving options:', error);
                        // -336:   /    
                        console.warn('     .');
                        showCenterToast(getI18nText('temporarilySaved'));
                        setTimeout(() => {
                            navigateToNextStep();
                        }, 800);
                    }
                } else {
                    // bookingId     
                    navigateToNextStep();
                }
            });
        }
    }

    function navigateToNextStep() {
        const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
        const currentLang = (cur === 'tl') ? 'tl' : 'en';
        // bookingId  bookingId ,    
        if (bookingId) {
            const params = new URLSearchParams();
            params.set('booking_id', bookingId);
            params.set('lang', currentLang);
            window.location.href = `reservation-sum-pay.php?${params.toString()}`;
        } else {
            // URL   reservation-sum-pay.html 
            const currentUrl = new URL(window.location.href);
            const baseUrl = currentUrl.origin + currentUrl.pathname.replace('add-option.php', 'reservation-sum-pay.php');
            
            //    URL  
            const params = new URLSearchParams();
            params.set('package_id', currentBooking.packageId || '');
            params.set('departure_date', currentBooking.departureDate || '');
            params.set('departure_time', currentBooking.departureTime || '12:20');
            params.set('package_name', currentBooking.packageName || '');
            params.set('package_price', currentBooking.packagePrice || '0');
            params.set('adults', currentBooking.adults || '1');
            params.set('children', currentBooking.children || '0');
            params.set('infants', currentBooking.infants || '0');
            params.set('total_amount', currentBooking.totalAmount || '0');
            params.set('selected_rooms', JSON.stringify(currentBooking.selectedRooms || {}));
            params.set('selected_options', JSON.stringify(currentBooking.selectedOptions || {}));
            params.set('seat_request', currentBooking.seatRequest || '');
            params.set('other_request', currentBooking.otherRequest || '');
            params.set('lang', currentLang);
            
            window.location.href = `${baseUrl}?${params.toString()}`;
        }
    }

    function setupTextareaCounters() {
        [seatRequestTextarea, otherRequestTextarea].forEach(textarea => {
            if (textarea) {
                textarea.addEventListener('input', function() {
                    const maxLength = 500;
                    if (this.value.length > maxLength) {
                        this.value = this.value.substring(0, maxLength);
                    }
                    updateBookingOptions();
                });
            }
        });
    }

    async function loadExistingOptions() {
        console.log('loadExistingOptions ');
        
        // bookingId  DB 
        if (bookingId && bookingId !== 'temp') {
            try {
                const response = await fetch('../backend/api/booking.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_booking',
                        bookingId: bookingId
                    })
                });
                
                console.log('booking.php API  :', response.status, response.ok);
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('booking.php API :', result);
                    
                    // API   : result.booking  result.data
                    const booking = result.booking || result.data || null;
                    
                    if (result.success && booking) {
                        console.log('DB   :', booking);
                        console.log('booking.selectedOptions:', booking.selectedOptions);
                        console.log('booking.seatRequest:', booking.seatRequest);
                        console.log('booking.otherRequest:', booking.otherRequest);
                        
                        // selectedOptions 
                        if (booking.selectedOptions) {
                            let savedOptions = {};
                            try {
                                if (typeof booking.selectedOptions === 'string') {
                                    savedOptions = JSON.parse(booking.selectedOptions);
                                } else if (typeof booking.selectedOptions === 'object') {
                                    savedOptions = booking.selectedOptions;
                                }
                                
                                console.log(' savedOptions:', savedOptions);
                                
                                if (savedOptions.selectedOptions && typeof savedOptions.selectedOptions === 'object') {
                                    selectedOptions = savedOptions.selectedOptions;
                                    currentBooking.selectedOptions = selectedOptions;
                                    console.log('selectedOptions  (DB):', selectedOptions);
                                }
                                
                                // seatRequest otherRequest JSON    
                                const seatRequestValue = booking.seatRequest || savedOptions.seatRequest || '';
                                const otherRequestValue = booking.otherRequest || savedOptions.otherRequest || '';
                                
                                if (seatRequestValue && seatRequestTextarea) {
                                    seatRequestTextarea.value = seatRequestValue;
                                    currentBooking.seatRequest = seatRequestValue;
                                    console.log('seatRequest :', seatRequestValue);
                                }
                                
                                if (otherRequestValue && otherRequestTextarea) {
                                    otherRequestTextarea.value = otherRequestValue;
                                    currentBooking.otherRequest = otherRequestValue;
                                    console.log('otherRequest :', otherRequestValue);
                                }
                            } catch (parseError) {
                                console.error('selectedOptions  :', parseError);
                            }
                        } else {
                            console.warn('booking.selectedOptions ');
                        }
                    } else {
                        console.warn('API  booking   :', result);
                    }
                } else {
                    console.error('booking.php API  :', response.status, response.statusText);
                    try {
                        const errorData = await response.json();
                        console.error(' :', errorData);
                    } catch (e) {
                        console.error('  ');
                    }
                }
            } catch (error) {
                console.error('DB   :', error);
            }
        }
        
        // localStorage  (fallback)
        if (currentBooking.selectedOptions && Object.keys(currentBooking.selectedOptions).length > 0) {
            if (!selectedOptions || Object.keys(selectedOptions).length === 0) {
                selectedOptions = currentBooking.selectedOptions;
                console.log('localStorage selectedOptions :', selectedOptions);
            }
        }
        
        console.log(' selectedOptions:', selectedOptions);
        
        // UI  (   DOM   )
        setTimeout(() => {
            //   
            if (selectedOptions.baggage) {
                const selectTrigger = baggageSelect?.querySelector('.select-trigger span');
                if (selectTrigger) {
                    selectTrigger.textContent = selectedOptions.baggage.name;
                    console.log('  :', selectedOptions.baggage.name);
                }
            }
            
            //    
            if (selectedOptions.breakfast) {
                //   () active 
                if (breakfastButtons.length > 0) {
                    breakfastButtons.forEach((btn, index) => {
                        if (index === 0) {
                            btn.classList.add('active');
                            console.log('   active ');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }
            } else {
                //   () active 
                if (breakfastButtons.length > 1) {
                    breakfastButtons.forEach((btn, index) => {
                        if (index === 1) {
                            btn.classList.add('active');
                            console.log('   active ');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }
            }
            
            //    
            if (selectedOptions.wifi) {
                //   () active 
                if (wifiButtons.length > 0) {
                    wifiButtons.forEach((btn, index) => {
                        if (index === 0) {
                            btn.classList.add('active');
                            console.log('   active ');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }
            } else {
                //   () active 
                if (wifiButtons.length > 1) {
                    wifiButtons.forEach((btn, index) => {
                        if (index === 1) {
                            btn.classList.add('active');
                            console.log('   active ');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }
            }
            
            if (currentBooking.seatRequest && seatRequestTextarea) {
                seatRequestTextarea.value = currentBooking.seatRequest;
            }
            
            if (currentBooking.otherRequest && otherRequestTextarea) {
                otherRequestTextarea.value = currentBooking.otherRequest;
            }
        }, 300); // DOM      
    }

    //      setupBaggageOptions 

    // PHP     updateDepartureInfo 
    loadPackageOptions().then(() => {
        setupBreakfastButtons();
        setupWifiButtons();
        loadExistingOptions();
    });
    setupNextButton();
    setupTextareaCounters();
});