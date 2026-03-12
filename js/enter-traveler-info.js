document.addEventListener('DOMContentLoaded', function() {
    //   
    const i18nTexts = {
        'ko': {
            bookingNotFound: '    .',
            adult: '',
            child: '',
            infant: '',
            travelerInfoInput: '  ',
            next: '',
            save: '',
            invalidDateFormat: '   .',
            requiredField: '  .',
            passportUploaded: ' .',
            passportUploadError: '    .',
            titleRequired: ' .',
            genderRequired: ' .',
            visaRequired: '   .',
            nameRequired: ' .',
            surnameRequired: ' .',
            ageRequired: ' .',
            birthDateRequired: ' .',
            nationalityRequired: ' .',
            passportNumberRequired: ' .',
            passportIssueDateRequired: '  .',
            passportExpiryDateRequired: '  .',
            saved: '.',
            doubleCheckMessage: '   .',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress',
            temporarilySaved: ' '
        },
        'en': {
            bookingNotFound: 'Booking information not found.',
            adult: 'Adult',
            child: 'Child',
            infant: 'Infant',
            travelerInfoInput: 'Enter Traveler Info',
            next: 'Next',
            save: 'Save',
            invalidDateFormat: 'Input format is incorrect.',
            requiredField: 'This field is required.',
            passportUploaded: 'Passport uploaded successfully.',
            passportUploadError: 'Error uploading passport.',
            titleRequired: 'Please select a title.',
            genderRequired: 'Please select gender.',
            visaRequired: 'Please select visa application status.',
            nameRequired: 'Please enter your first name.',
            surnameRequired: 'Please enter your last name.',
            ageRequired: 'Please enter your age.',
            birthDateRequired: 'Please enter your birth date.',
            nationalityRequired: 'Please enter your nationality.',
            passportNumberRequired: 'Please enter your passport number.',
            passportIssueDateRequired: 'Please enter passport issue date.',
            passportExpiryDateRequired: 'Please enter passport expiry date.',
            saved: 'Saved.',
            doubleCheckMessage: 'Please double-check your information.',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress',
            temporarilySaved: 'Temporarily saved'
        },
        'tl': {
            bookingNotFound: 'Hindi mahanap ang impormasyon ng booking.',
            adult: 'Matanda',
            child: 'Bata',
            infant: 'Sanggol',
            travelerInfoInput: 'Ilagay ang Impormasyon ng Traveler',
            next: 'Susunod',
            save: 'I-save',
            invalidDateFormat: 'Mali ang format ng input.',
            requiredField: 'Kailangan ang field na ito.',
            passportUploaded: 'Matagumpay na na-upload ang passport.',
            passportUploadError: 'May error sa pag-upload ng passport.',
            titleRequired: 'Pakipili ang title.',
            genderRequired: 'Pakipili ang kasarian.',
            visaRequired: 'Pakipili ang visa application status.',
            nameRequired: 'Pakilagay ang inyong first name.',
            surnameRequired: 'Pakilagay ang inyong last name.',
            ageRequired: 'Pakilagay ang inyong edad.',
            birthDateRequired: 'Pakilagay ang inyong birth date.',
            nationalityRequired: 'Pakilagay ang inyong nasyonalidad.',
            passportNumberRequired: 'Pakilagay ang inyong passport number.',
            passportIssueDateRequired: 'Pakilagay ang passport issue date.',
            passportExpiryDateRequired: 'Pakilagay ang passport expiry date.',
            saved: 'Nai-save.',
            doubleCheckMessage: 'Paki-double check ang iyong impormasyon.',
            doubleCheck: 'Double-check',
            reservationInProgress: 'Reservation in progress',
            temporarilySaved: 'Naka-save na pansamantala'
        }
    };

    //     
    function getI18nText(key) {
        // URL    
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        
        // URL   localStorage 
        if (urlLang) {
            localStorage.setItem('selectedLanguage', urlLang);
        }
        
        const currentLang = urlLang || localStorage.getItem('selectedLanguage') || 'ko';
        const texts = i18nTexts[currentLang] || i18nTexts['ko'];
        return texts[key] || key;
    }

    let currentBooking = JSON.parse(localStorage.getItem('currentBooking'));
    const urlParams = new URLSearchParams(window.location.search);
    const travelerType = urlParams.get('type') || 'adult';
    const travelerIndex = parseInt(urlParams.get('index')) || 1;
    let isEditing = false;
    let currentTravelerId = null;

    if (!currentBooking) {
        alert(getI18nText('bookingNotFound'));
        window.location.href = '../home.html';
        return;
    }

    const titleButtons = document.querySelectorAll('.btn-title');
    const genderButtons = document.querySelectorAll('.btn-gender');
    const visaButtons = document.querySelectorAll('.btn-visa');
    const nextButton = document.getElementById('next-btn') || document.querySelector('.btn.primary.lg');
    const toastContainer = document.getElementById('toast-container');

    const inputs = {
        name: document.getElementById('txt1'),
        surname: document.getElementById('txt2'),
        age: document.getElementById('txt3'),
        birthDate: document.getElementById('txt4'),
        nationality: document.getElementById('txt5'),
        passportNumber: document.getElementById('txt6'),
        passportIssueDate: document.getElementById('txt7'),
        passportExpiryDate: document.getElementById('txt8'),
        passportUpload: document.getElementById('passportUpload')
    };

    function updatePageTitle() {
        const titleElement = document.querySelector('.title');
        if (titleElement) {
            const typeText = travelerType === 'adult' ? getI18nText('adult') : 
                           travelerType === 'child' ? getI18nText('child') : 
                           getI18nText('infant');
            titleElement.textContent = `${getI18nText('travelerInfoInput')} (${typeText} ${travelerIndex})`;
        }
    }

    function validateDateFormat(dateString, options = {}) {
        const dateRegex = /^(\d{4})(\d{2})(\d{2})$/;
        const match = String(dateString || '').match(dateRegex);

        if (!match) return false;

        const year = parseInt(match[1], 10);
        const month = parseInt(match[2], 10);
        const day = parseInt(match[3], 10);

        if (month < 1 || month > 12) return false;
        if (day < 1 || day > 31) return false;

        const currentYear = new Date().getFullYear();
        const minYear = Number.isFinite(options.minYear) ? options.minYear : 1900;
        const maxYear = Number.isFinite(options.maxYear) ? options.maxYear : 2100;
        if (year < minYear || year > maxYear) return false;

        //   (/ ) 
        const date = new Date(year, month - 1, day);
        return date.getFullYear() === year &&
               date.getMonth() === month - 1 &&
               date.getDate() === day;
    }

    function showValidationError(input, message) {
        let errorDiv = input.parentNode.querySelector('.reded');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'text fz12 fw400 lh16 reded mt4';
            input.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }

    function hideValidationError(input) {
        const errorDiv = input.parentNode.querySelector('.reded');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }
    
    function showToast(message, type = 'info') {
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        toast.style.display = 'block';
        toast.style.marginBottom = '10px';
        
        //    
        if (type === 'error') {
            toast.style.background = 'rgba(237, 27, 35, 0.9)';
        } else if (type === 'success') {
            toast.style.background = 'rgba(0, 128, 0, 0.9)';
        } else {
            toast.style.background = 'rgba(0, 0, 0, 0.7)';
        }
        
        toastContainer.appendChild(toast);
        toastContainer.style.display = 'block';
        
        // 3   
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                toast.remove();
                if (toastContainer.children.length === 0) {
                    toastContainer.style.display = 'none';
                }
            }, 300);
        }, 3000);
    }

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

    function showDoubleCheckPopup() {
        const { layer, popup } = ensureDoubleCheckPopup();
        const msgEl = document.getElementById('doubleCheckPopupMsg');
        const cancelBtn = document.getElementById('doubleCheckCancelBtn');
        const proceedBtn = document.getElementById('doubleCheckProceedBtn');

        if (msgEl) msgEl.textContent = getI18nText('doubleCheckMessage');
        if (cancelBtn) cancelBtn.textContent = getI18nText('doubleCheck');
        if (proceedBtn) proceedBtn.textContent = getI18nText('reservationInProgress');

        layer.classList.add('active');
        popup.style.display = 'flex';

        if (cancelBtn) {
            cancelBtn.onclick = () => hideDoubleCheckPopup();
        }
        if (proceedBtn) {
            proceedBtn.onclick = () => {
                hideDoubleCheckPopup();
                saveTravelerInfo();
            };
        }
        layer.onclick = () => hideDoubleCheckPopup();
    }

    function hideDoubleCheckPopup() {
        const layer = document.getElementById('doubleCheckLayer');
        const popup = document.getElementById('doubleCheckPopup');
        if (layer) layer.classList.remove('active');
        if (popup) popup.style.display = 'none';
    }
    
    function checkAllFieldsFilled() {
        //    
        const checks = {
            name: inputs.name && inputs.name.value && !!inputs.name.value.trim(),
            surname: inputs.surname && inputs.surname.value && !!inputs.surname.value.trim(),
            age: inputs.age && inputs.age.value && !!inputs.age.value.trim(),
            birthDate: inputs.birthDate && inputs.birthDate.value && validateDateFormat(inputs.birthDate.value, { maxYear: new Date().getFullYear() }),
            nationality: inputs.nationality && inputs.nationality.value && !!inputs.nationality.value.trim(),
            passportNumber: inputs.passportNumber && inputs.passportNumber.value && !!inputs.passportNumber.value.trim(),
            passportIssueDate: inputs.passportIssueDate && inputs.passportIssueDate.value && validateDateFormat(inputs.passportIssueDate.value, { maxYear: new Date().getFullYear() }),
            //  2030  (10  ) → 2100 
            passportExpiryDate: inputs.passportExpiryDate && inputs.passportExpiryDate.value && validateDateFormat(inputs.passportExpiryDate.value, { maxYear: 2100 }),
            title: !!document.querySelector('.btn-title.active'),
            gender: !!document.querySelector('.btn-gender.active'),
            visa: !!document.querySelector('.btn-visa.active')
        };
        
        // :     (  )
        const missingFields = Object.keys(checks).filter(key => !checks[key]);
        if (missingFields.length > 0 && missingFields.length < 11) {
            console.log(' :', missingFields);
            console.log('  :', {
                name: inputs.name?.value,
                surname: inputs.surname?.value,
                age: inputs.age?.value,
                birthDate: inputs.birthDate?.value,
                nationality: inputs.nationality?.value,
                passportNumber: inputs.passportNumber?.value,
                passportIssueDate: inputs.passportIssueDate?.value,
                passportExpiryDate: inputs.passportExpiryDate?.value
            });
        }
        
        return Object.values(checks).every(v => v === true);
    }
    
    function updateNextButtonState() {
        if (!nextButton) return;
        
        if (checkAllFieldsFilled()) {
            nextButton.classList.remove('inactive');
            nextButton.style.pointerEvents = 'auto';
        } else {
            nextButton.classList.add('inactive');
            nextButton.style.pointerEvents = 'none';
        }
    }

    function getGenderValue() {
        const activeBtn = document.querySelector('.btn-gender.active');
        if (!activeBtn) return 'male';
        const text = activeBtn.textContent.trim();
        //  
        if (text === '' || text === 'Male' || text === 'Lalaki') return 'male';
        if (text === '' || text === 'Female' || text === 'Babae') return 'female';
        return 'male';
    }
    
    function getVisaValue() {
        const activeBtn = document.querySelector('.btn-visa.active');
        if (!activeBtn) return 0;
        // Prefer stable positional logic (buttons are rendered as [Apply, Not apply])
        try {
            const btns = Array.from(document.querySelectorAll('.btn-visa'));
            const idx = btns.indexOf(activeBtn);
            if (idx === 0) return 1;
            if (idx === 1) return 0;
        } catch (_) {}

        // Fallback to text matching (en/tl only)
        const text = (activeBtn.textContent || '').trim().toLowerCase();
        if (text.includes('not')) return 0;
        if (text.includes('apply')) return 1;
        return 0;
    }
    
    function setupDateValidation() {
        [inputs.birthDate, inputs.passportIssueDate, inputs.passportExpiryDate].forEach(input => {
            if (input) {
                input.addEventListener('blur', function() {
                    const maxYear = (this === inputs.birthDate || this === inputs.passportIssueDate)
                        ? new Date().getFullYear()
                        : 2100;
                    if (this.value && !validateDateFormat(this.value, { maxYear })) {
                        showValidationError(this, '   .');
                    } else {
                        hideValidationError(this);
                    }
                });

                input.addEventListener('input', function() {
                    const maxYear = (this === inputs.birthDate || this === inputs.passportIssueDate)
                        ? new Date().getFullYear()
                        : 2100;
                    if (validateDateFormat(this.value, { maxYear })) {
                        hideValidationError(this);
                    }
                });
            }
        });
    }

    function setupButtonGroups() {
        [titleButtons, genderButtons, visaButtons].forEach(buttonGroup => {
            buttonGroup.forEach(button => {
                button.addEventListener('click', function() {
                    buttonGroup.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    //        
                    setTimeout(updateNextButtonState, 100);
                });
            });
        });
    }

    function setupPassportUpload() {
        const uploadInput = inputs.passportUpload;
        const previewImage = document.getElementById('passportPreview');

        if (uploadInput && previewImage) {
            uploadInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        showToast('File size must be 5MB or less.', 'error');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }

    async function loadExistingTravelerData() {
        try {
            // bookingId : URL  > currentBooking.bookingId > currentBooking.tempId
            const urlParams2 = new URLSearchParams(window.location.search);
            const urlBookingId = urlParams2.get('booking_id');
            const bookingId = urlBookingId || 
                             currentBooking?.bookingId || 
                             currentBooking?.tempId || 
                             'temp';
            
            const response = await fetch('../backend/api/travelers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'getByTypeAndIndex',
                    bookingId: bookingId,
                    type: travelerType,
                    index: travelerIndex
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('  API :', data);
                if (data.success && data.traveler) {
                    console.log('   :', data.traveler);
                    populateForm(data.traveler);
                    isEditing = true;
                    currentTravelerId = data.traveler.id;
                    // populateForm     (DOM   )
                    setTimeout(() => {
                        updateNextButtonState();
                        console.log('      ');
                    }, 300);
                } else if (data.success && !data.traveler) {
                    //     (  )
                    console.log('   ( )');
                    //   
                    setTimeout(updateNextButtonState, 100);
                }
            } else {
                // API     
                let errorText = '';
                try {
                    errorText = await response.text();
                    console.error('API   -  :', errorText);
                    const errorData = JSON.parse(errorText);
                    console.error('API  :', errorData.message || response.statusText);
                } catch (e) {
                    console.error('API   -  :', response.status, ':', errorText || ' ');
                }
                // API   localStorage  
                loadFromLocalStorage();
            }
        } catch (error) {
            console.error('API  , localStorage  :', error);
            //    localStorage  
            loadFromLocalStorage();
        }
    }

    function loadFromLocalStorage() {
        const travelers = JSON.parse(localStorage.getItem('travelers') || '[]');
        const traveler = travelers.find(t => 
            t.type === travelerType && t.sequence === travelerIndex
        );
        
        if (traveler) {
            populateForm(traveler);
            isEditing = true;
            currentTravelerId = traveler.id;
            console.log('localStorage   :', traveler);
        }
    }

    function populateForm(traveler) {
        // API  camelCase, localStorage snake_case
        const firstName = traveler.firstName || traveler.first_name || '';
        const lastName = traveler.lastName || traveler.last_name || '';
        const birthDate = traveler.birthDate || traveler.birth_date || '';
        const passportIssueDate = traveler.passportIssueDate || traveler.passport_issue_date || '';
        const passportExpiryDate = traveler.passportExpiry || traveler.passport_expiry_date || '';
        
        inputs.name.value = firstName;
        inputs.surname.value = lastName;
        
        // age  : DB age  birthDate 
        let age = traveler.age || '';
        if (!age && birthDate) {
            // birthDate   (YYYY-MM-DD  YYYYMMDD )
            const birthDateStr = birthDate.replace(/-/g, '');
            if (birthDateStr.length === 8) {
                const birthYear = parseInt(birthDateStr.substring(0, 4));
                const birthMonth = parseInt(birthDateStr.substring(4, 6));
                const birthDay = parseInt(birthDateStr.substring(6, 8));
                const today = new Date();
                let calculatedAge = today.getFullYear() - birthYear;
                const monthDiff = today.getMonth() + 1 - birthMonth;
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDay)) {
                    calculatedAge--;
                }
                age = calculatedAge.toString();
            }
        }
        inputs.age.value = age || '';
        //  YYYYMMDD   (YYYY-MM-DD -> YYYYMMDD)
        if (birthDate) {
            inputs.birthDate.value = birthDate.replace(/-/g, '');
        }
        inputs.nationality.value = traveler.nationality || '';
        inputs.passportNumber.value = traveler.passportNumber || traveler.passport_number || '';
        //   YYYYMMDD  
        if (passportIssueDate) {
            inputs.passportIssueDate.value = passportIssueDate.replace(/-/g, '');
        }
        //   YYYYMMDD  
        if (passportExpiryDate) {
            inputs.passportExpiryDate.value = passportExpiryDate.replace(/-/g, '');
        }

        //  
        if (traveler.title) {
            titleButtons.forEach(btn => {
                btn.classList.remove('active');
                const btnText = btn.textContent.trim();
                if (btnText === traveler.title || 
                    (traveler.title === 'MR' && btnText === 'MR') ||
                    (traveler.title === 'MS' && btnText === 'MS') ||
                    (traveler.title === 'MRS' && btnText === 'MRS')) {
                    btn.classList.add('active');
                }
            });
        }

        //  
        if (traveler.gender) {
            const gender = traveler.gender.toLowerCase();
            genderButtons.forEach(btn => {
                btn.classList.remove('active');
                const btnText = btn.textContent.trim();
                if ((gender === 'male' || gender === 'm') && (btnText === '' || btnText === 'Male' || btnText === 'Lalaki')) {
                    btn.classList.add('active');
                } else if ((gender === 'female' || gender === 'f') && (btnText === '' || btnText === 'Female' || btnText === 'Babae')) {
                    btn.classList.add('active');
                }
            });
        }

        // Visa required (normalize; en/tl only)
        const visaStatus = traveler.visaStatus || traveler.visa_required;
        if (visaStatus !== null && visaStatus !== undefined) {
            const visaRequired =
                visaStatus === 'applied' ||
                visaStatus === 1 ||
                visaStatus === '1' ||
                visaStatus === true ||
                visaStatus === 'true';
            visaButtons.forEach(btn => btn.classList.remove('active'));
            if (visaButtons && visaButtons.length >= 2) {
                // UI order: [Apply, Not apply]
                (visaRequired ? visaButtons[0] : visaButtons[1]).classList.add('active');
            } else {
                // fallback
                visaButtons.forEach(btn => {
                    const lower = String(btn.textContent || '').toLowerCase();
                    if (visaRequired && lower.includes('apply') && !lower.includes('not')) btn.classList.add('active');
                    if (!visaRequired && lower.includes('not')) btn.classList.add('active');
                });
            }
        }

        // Passport image preview (supports dataURL / raw base64 / upload path)
        if (traveler.passportImage || traveler.passport_image) {
            const previewImage = document.getElementById('passportPreview');
            if (previewImage) {
                const raw = String(traveler.passportImage || traveler.passport_image || '').trim();
                let src = raw;
                if (src.startsWith('data:')) {
                    // ok
                } else if (src.startsWith('http://') || src.startsWith('https://')) {
                    // ok
                } else {
                    const normalized = src.replace(/\\/g, '/').replace(/^\/+/, '');
                    if (normalized.startsWith('uploads/')) {
                        src = window.location.origin + '/' + normalized;
                    } else {
                        // assume raw base64 payload
                        src = 'data:image/jpeg;base64,' + normalized;
                    }
                }
                previewImage.src = src;
                previewImage.style.display = 'block';
            }
        }
        
        //     
        //    input     
        setTimeout(() => {
            //   input  
            Object.values(inputs).forEach(input => {
                if (input && input.value) {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            
            //    
            const titleBtn = document.querySelector('.btn-title.active');
            const genderBtn = document.querySelector('.btn-gender.active');
            const visaBtn = document.querySelector('.btn-visa.active');
            
            console.log('     :', {
                name: inputs.name?.value,
                surname: inputs.surname?.value,
                age: inputs.age?.value,
                birthDate: inputs.birthDate?.value,
                nationality: inputs.nationality?.value,
                passportNumber: inputs.passportNumber?.value,
                passportIssueDate: inputs.passportIssueDate?.value,
                passportExpiryDate: inputs.passportExpiryDate?.value,
                title: !!titleBtn,
                gender: !!genderBtn,
                visa: !!visaBtn
            });
            
            updateNextButtonState();
            
            //     (DOM  )
            setTimeout(() => {
                const allFieldsFilled = checkAllFieldsFilled();
                console.log('  :', allFieldsFilled);
                if (allFieldsFilled) {
                    nextButton.classList.remove('inactive');
                    nextButton.style.pointerEvents = 'auto';
                } else {
                    nextButton.classList.add('inactive');
                    nextButton.style.pointerEvents = 'none';
                }
            }, 200);
        }, 100);
    }

    function validateForm() {
        let isValid = true;
        
        if (!inputs.name.value.trim()) {
            showValidationError(inputs.name, ' .');
            isValid = false;
        }

        if (!inputs.surname.value.trim()) {
            showValidationError(inputs.surname, ' .');
            isValid = false;
        }

        if (!inputs.age.value) {
            showValidationError(inputs.age, ' .');
            isValid = false;
        }

        if (!inputs.birthDate.value) {
            showValidationError(inputs.birthDate, ' .');
            isValid = false;
        } else if (!validateDateFormat(inputs.birthDate.value, { maxYear: new Date().getFullYear() })) {
            showValidationError(inputs.birthDate, '   .');
            isValid = false;
        }

        if (!inputs.nationality.value.trim()) {
            showValidationError(inputs.nationality, ' .');
            isValid = false;
        }

        if (!inputs.passportNumber.value.trim()) {
            showValidationError(inputs.passportNumber, ' .');
            isValid = false;
        }

        if (!inputs.passportIssueDate.value) {
            showValidationError(inputs.passportIssueDate, '  .');
            isValid = false;
        } else if (!validateDateFormat(inputs.passportIssueDate.value, { maxYear: new Date().getFullYear() })) {
            showValidationError(inputs.passportIssueDate, '   .');
            isValid = false;
        }

        if (!inputs.passportExpiryDate.value) {
            showValidationError(inputs.passportExpiryDate, '  .');
            isValid = false;
        } else if (!validateDateFormat(inputs.passportExpiryDate.value, { maxYear: 2100 })) {
            showValidationError(inputs.passportExpiryDate, '   .');
            isValid = false;
        }

        const selectedTitle = document.querySelector('.btn-title.active');
        if (!selectedTitle) {
            showToast(getI18nText('titleRequired'), 'error');
            isValid = false;
        }

        const selectedGender = document.querySelector('.btn-gender.active');
        if (!selectedGender) {
            showToast(getI18nText('genderRequired'), 'error');
            isValid = false;
        }

        const selectedVisa = document.querySelector('.btn-visa.active');
        if (!selectedVisa) {
            showToast(getI18nText('visaRequired'), 'error');
            isValid = false;
        }

        return isValid;
    }

    async function saveTravelerInfo() {
        console.log('saveTravelerInfo ');
        
        if (!validateForm()) {
            console.log('  ');
            return false;
        }
        
        console.log('  ,   ');

        // bookingId : URL  > currentBooking.bookingId > currentBooking.tempId
        const urlParams = new URLSearchParams(window.location.search);
        const urlBookingId = urlParams.get('booking_id');
        const bookingId = urlBookingId || 
                         currentBooking?.bookingId || 
                         currentBooking?.tempId || 
                         'temp';
        
        console.log('   - bookingId:', bookingId);

        // dev_tasks #117: bookingId가 없으면(=temp) DB 저장이 불가능하다.
        // 기존 로직은 temp인 경우에도 성공 처리(localStorage)로 넘어가 실제 저장이 안 된 채 다음 단계로 진행될 수 있음.
        // 예약 흐름(로그인 사용자)에서는 반드시 booking_id를 유지해야 하므로 여기서 명확히 차단한다.
        if (!bookingId || bookingId === 'temp') {
            showToast('Booking ID is missing. Please restart the booking process.', 'error');
            return false;
        }
        
        const travelerData = {
            id: currentTravelerId || `traveler_${travelerType}_${travelerIndex}_${Date.now()}`,
            bookingId: bookingId,
            type: travelerType === 'adult' ? 'Adult' : (travelerType === 'child' ? 'Child' : 'Infant'),
            sequence: travelerIndex,
            title: (document.querySelector('.btn-title.active')?.textContent || 'MR').trim(),
            first_name: inputs.name.value.trim(),
            last_name: inputs.surname.value.trim(),
            age: inputs.age.value,
            birth_date: inputs.birthDate.value,
            gender: getGenderValue(),
            nationality: inputs.nationality.value.trim(),
            passport_number: inputs.passportNumber.value.trim(),
            passport_issue_date: inputs.passportIssueDate.value,
            passport_expiry_date: inputs.passportExpiryDate.value,
            visa_required: getVisaValue(),
            created_at: new Date().toISOString()
        };

        try {
            //  API 
            const formData = new FormData();
            formData.append('action', isEditing ? 'update' : 'create');
            
            if (isEditing && currentTravelerId) {
                formData.append('travelerId', currentTravelerId);
            }

            Object.keys(travelerData).forEach(key => {
                if (key !== 'passport_image' && travelerData[key] !== null) {
                    formData.append(key, travelerData[key]);
                }
            });

            if (inputs.passportUpload.files[0]) {
                formData.append('passport_image', inputs.passportUpload.files[0]);
            }

            const response = await fetch('../backend/api/travelers.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log('API   ');
                    saveToLocalStorage(travelerData);
                    showToast(getI18nText('temporarilySaved'), 'success');
                    setTimeout(() => {
                        navigateToNextStep();
                    }, 1000);
                    return true;
                } else {
                    throw new Error(data.message || 'API  ');
                }
            } else {
                throw new Error('Network response was not ok');
            }
        // SMT   - API    
        } catch (error) {
            console.error('API  :', error);

            // bookingId  API    
            const errorMessage = error.message || 'API  ';
            showToast(errorMessage, 'error');
            return false;
        }
        // SMT  
    }

    function saveToLocalStorage(travelerData) {
        let travelers = JSON.parse(localStorage.getItem('travelers') || '[]');
        
        //    ,  
        const existingIndex = travelers.findIndex(t => 
            t.type === travelerData.type && t.sequence === travelerData.sequence
        );
        
        if (existingIndex >= 0) {
            travelers[existingIndex] = travelerData;
        } else {
            travelers.push(travelerData);
        }
        
        localStorage.setItem('travelers', JSON.stringify(travelers));
        console.log('localStorage   :', travelerData);
    }

    function convertFileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    function navigateToNextStep() {
        // booking_id URL  enter-customer-info.php 
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('booking_id') || currentBooking?.bookingId || currentBooking?.tempId;
        
        if (bookingId && bookingId !== 'temp') {
            // booking_id  booking_id 
            const currentUrl = new URL(window.location.href);
            const baseUrl = currentUrl.origin + currentUrl.pathname.replace('enter-traveler-info.php', 'enter-customer-info.php');
            
            const params = new URLSearchParams();
            params.set('booking_id', bookingId);
            //   
            if (urlParams.get('lang')) {
                params.set('lang', urlParams.get('lang'));
            }
            
            console.log('   ,   :', bookingId);
            window.location.href = `${baseUrl}?${params.toString()}`;
        } else {
            // booking_id   (  )
            console.warn('booking_id ,   ');
            const currentUrl = new URL(window.location.href);
            const baseUrl = currentUrl.origin + currentUrl.pathname.replace('enter-traveler-info.php', 'enter-customer-info.php');
            
            const params = new URLSearchParams();
            params.set('package_id', currentBooking?.packageId || '');
            params.set('departure_date', currentBooking?.departureDate || '');
            params.set('departure_time', currentBooking?.departureTime || '12:20');
            params.set('package_name', currentBooking?.packageName || '');
            params.set('package_price', currentBooking?.packagePrice || '0');
            params.set('adults', currentBooking?.adults || '1');
            params.set('children', currentBooking?.children || '0');
            params.set('infants', currentBooking?.infants || '0');
            params.set('total_amount', currentBooking?.totalAmount || '0');
            params.set('selected_rooms', JSON.stringify(currentBooking?.selectedRooms || {}));
            
            window.location.href = `${baseUrl}?${params.toString()}`;
        }
    }

    function setupNextButton() {
        if (nextButton) {
            //        
            Object.values(inputs).forEach(input => {
                if (input) {
                    input.addEventListener('input', updateNextButtonState);
                    input.addEventListener('blur', updateNextButtonState);
                }
            });
            
            //    
            nextButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (checkAllFieldsFilled()) {
                    // 3-1(여행자 정보 입력)에서는 재확인 팝업을 띄우지 않고 바로 저장/다음으로 진행
                    // 재확인 팝업은 3단계(enter-customer-info) "Next" 클릭 시에만 노출
                    saveTravelerInfo();
                } else {
                    //      
                    showToast(getI18nText('requiredField'), 'error');
                }
            });
            
            //    
            updateNextButtonState();
        }
    }

    //    
    function generateTestData() {
        //   
        const firstNames = ['', '', '', '', '', '', '', ''];
        const lastNames = ['', '', '', '', '', '', '', ''];
        const englishFirstNames = ['John', 'Sarah', 'Michael', 'Emily', 'David', 'Lisa', 'James', 'Anna'];
        const englishLastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        
        //   
        const emailDomains = ['gmail.com', 'naver.com', 'yahoo.com', 'hotmail.com'];
        
        //  
        const countries = ['', '', '', '', '', '', '', ''];
        
        //     
        const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
        const firstName = currentLang === 'ko' ? 
            firstNames[Math.floor(Math.random() * firstNames.length)] :
            englishFirstNames[Math.floor(Math.random() * englishFirstNames.length)];
        const lastName = currentLang === 'ko' ? 
            lastNames[Math.floor(Math.random() * lastNames.length)] :
            englishLastNames[Math.floor(Math.random() * englishLastNames.length)];
        
        //   
        const testData = {
            firstName: firstName,
            lastName: lastName,
            age: Math.floor(Math.random() * 50) + 18, // 18-67
            birthDate: generateRandomBirthDate(),
            nationality: countries[Math.floor(Math.random() * countries.length)],
            passportNumber: 'P' + Math.floor(Math.random() * 9000000) + 1000000, // P1000000-P9999999
            passportIssueDate: generateRandomPassportDate(),
            passportExpiryDate: generateRandomExpiryDate(),
            email: firstName.toLowerCase() + '.' + lastName.toLowerCase() + '@' + emailDomains[Math.floor(Math.random() * emailDomains.length)]
        };
        
        //   
        fillFormWithTestData(testData);
        
        console.log('   :', testData);
    }
    
    //    (YYYYMMDD)
    function generateRandomBirthDate() {
        const year = Math.floor(Math.random() * 50) + 1955; // 1955-2004
        const month = Math.floor(Math.random() * 12) + 1;
        
        //     
        const daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        const isLeapYear = (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
        if (isLeapYear && month === 2) {
            daysInMonth[1] = 29; //  2
        }
        
        const maxDay = daysInMonth[month - 1];
        const day = Math.floor(Math.random() * maxDay) + 1;
        
        return year.toString() + month.toString().padStart(2, '0') + day.toString().padStart(2, '0');
    }
    
    //     ( 10)
    function generateRandomPassportDate() {
        const currentYear = new Date().getFullYear();
        const year = Math.floor(Math.random() * 10) + (currentYear - 10);
        const month = Math.floor(Math.random() * 12) + 1;
        
        //     
        const daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        const isLeapYear = (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
        if (isLeapYear && month === 2) {
            daysInMonth[1] = 29; //  2
        }
        
        const maxDay = daysInMonth[month - 1];
        const day = Math.floor(Math.random() * maxDay) + 1;
        
        return year.toString() + month.toString().padStart(2, '0') + day.toString().padStart(2, '0');
    }
    
    //     ( 5-10 )
    function generateRandomExpiryDate() {
        const issueYear = Math.floor(Math.random() * 5) + 5; // 5-9 
        const currentYear = new Date().getFullYear();
        const year = currentYear + issueYear;
        
        // 2030   
        const maxYear = Math.min(year, 2030);
        const month = Math.floor(Math.random() * 12) + 1;
        
        //     
        const daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        const isLeapYear = (maxYear % 4 === 0 && maxYear % 100 !== 0) || (maxYear % 400 === 0);
        if (isLeapYear && month === 2) {
            daysInMonth[1] = 29; //  2
        }
        
        const maxDay = daysInMonth[month - 1];
        const day = Math.floor(Math.random() * maxDay) + 1;
        
        return maxYear.toString() + month.toString().padStart(2, '0') + day.toString().padStart(2, '0');
    }
    
    //    
    function fillFormWithTestData(data) {
        //  
        if (inputs.name) inputs.name.value = data.firstName;
        if (inputs.surname) inputs.surname.value = data.lastName;
        
        //  
        if (inputs.age) inputs.age.value = data.age;
        
        //  
        if (inputs.birthDate) inputs.birthDate.value = data.birthDate;
        
        //  
        if (inputs.nationality) inputs.nationality.value = data.nationality;
        
        //  
        if (inputs.passportNumber) inputs.passportNumber.value = data.passportNumber;
        
        //   
        if (inputs.passportIssueDate) inputs.passportIssueDate.value = data.passportIssueDate;
        
        //   
        if (inputs.passportExpiryDate) inputs.passportExpiryDate.value = data.passportExpiryDate;
        
        //   
        const genderButtons = document.querySelectorAll('.btn-gender');
        const randomGender = Math.random() < 0.5 ? 0 : 1;
        genderButtons.forEach((btn, index) => {
            btn.classList.toggle('active', index === randomGender);
        });
        
        //   
        const titleButtons = document.querySelectorAll('.btn-title');
        const randomTitle = Math.random() < 0.5 ? 0 : 1;
        titleButtons.forEach((btn, index) => {
            btn.classList.toggle('active', index === randomTitle);
        });
        
        //     
        const visaButtons = document.querySelectorAll('.btn-visa');
        const randomVisa = Math.random() < 0.7 ? 0 : 1; // 70%  
        visaButtons.forEach((btn, index) => {
            btn.classList.toggle('active', index === randomVisa);
        });
        
        //   input     
        Object.values(inputs).forEach(input => {
            if (input && input.value) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        
        //     
        const titleBtn = document.querySelector('.btn-title.active');
        const genderBtn = document.querySelector('.btn-gender.active');
        const visaBtn = document.querySelector('.btn-visa.active');
        if (titleBtn) titleBtn.click();
        if (genderBtn) genderBtn.click();
        if (visaBtn) visaBtn.click();
        
        //   
        setTimeout(() => {
            updateNextButtonState();
            console.log('    ,   ');
        }, 100);
    }
    

    updatePageTitle();
    setupDateValidation();
    setupButtonGroups();
    setupPassportUpload();
    setupNextButton();
    loadExistingTravelerData();
});