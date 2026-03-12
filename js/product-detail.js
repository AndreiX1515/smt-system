//    

// SMT  
//   
const i18nTexts = {
    'ko': {
        selectDate: ' ',
        perPerson: '1 ',
        bookNow: '',
        loginRequired: ' .   ?',
        selectDateFirst: '  .',
        loading: '   ...',
        departureConfirmed: ' ',
        soldOut: ''
    },
    'en': {
        selectDate: 'Please select dates',
        perPerson: 'Per person',
        bookNow: 'Book Now',
        loginRequired: 'Login is required. Would you like to go to the login page?',
        selectDateFirst: 'Please select a date first.',
        loading: 'Loading package information...',
        departureConfirmed: 'Departure Confirmed',
        soldOut: 'Sold Out'
    },
    'tl': {
        selectDate: 'Piliin ang mga petsa',
        perPerson: 'Bawat tao',
        bookNow: 'Mag-book',
        loginRequired: 'Kailangan ng login. Gusto mo bang pumunta sa login page?',
        selectDateFirst: 'Pumili muna ng petsa.',
        loading: 'Naglo-load ng impormasyon ng package...',
        departureConfirmed: 'Kumpirmado ang Alis',
        soldOut: 'Sold Out'
    }
};
// SMT  

//     
function getI18nText(key) {
    const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
    const texts = i18nTexts[currentLang] || i18nTexts['ko'];
    return texts[key] || key;
}

function isB2BUser() {
    // B2B/B2C 판별: accountType 기반
    // - accountType IN ('agent', 'admin') → B2B
    // - accountType IN ('guest', 'guide', 'cs', '') → B2C
    const at = String(localStorage.getItem('accountType') || '').toLowerCase();
    return at === 'agent' || at === 'admin';
}

// 사용자 타입에 맞는 가격 반환 (이중 가격 시스템)
function getDisplayPrice(packageInfo) {
    const isB2B = isB2BUser();
    if (isB2B) {
        // B2B 가격 우선, 없으면 B2C 가격 사용
        return {
            adult: packageInfo.b2bPrice ?? packageInfo.packagePrice ?? 0,
            child: packageInfo.b2bChildPrice ?? packageInfo.childPrice ?? null,
            infant: packageInfo.b2bInfantPrice ?? packageInfo.infantPrice ?? null,
            priceTier: 'B2B'
        };
    } else {
        // B2C 가격 사용
        return {
            adult: packageInfo.packagePrice ?? 0,
            child: packageInfo.childPrice ?? null,
            infant: packageInfo.infantPrice ?? null,
            priceTier: 'B2C'
        };
    }
}

// 날짜별 가격에서 사용자 타입에 맞는 가격 반환
function getDisplayPriceForDate(availabilityItem) {
    const isB2B = isB2BUser();
    if (isB2B) {
        return {
            price: availabilityItem.b2bPrice ?? availabilityItem.price ?? 0,
            childPrice: availabilityItem.b2bChildPrice ?? availabilityItem.childPrice ?? null,
            infantPrice: availabilityItem.b2bInfantPrice ?? availabilityItem.infantPrice ?? null,
            priceTier: 'B2B'
        };
    } else {
        return {
            price: availabilityItem.price ?? 0,
            childPrice: availabilityItem.childPrice ?? null,
            infantPrice: availabilityItem.infantPrice ?? null,
            priceTier: 'B2C'
        };
    }
}

let currentPackage = null;
let selectedDate = null;
let currentAvailabilityData = null; //  availability  
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth() + 1;
// SMT  
let isInitialCalendarLoad = true;

function isAvailabilityBookable(item) {
    if (!item) return false;
    const status = String(item.status || '').toLowerCase();
    const maxSeats = Number(item.maxSeats ?? item.maxParticipants ?? item.availableSeats ?? 0);
    const remain = Number(item.remainingSeats ?? item.remaining_seats ?? 0);
    return status === 'available' && maxSeats > 0 && remain > 0;
}

//     (AJAX)
async function updateBookingStatusForDate(dateStr) {
    // URL packageId 

    console.log('[updateBookingStatusForDate] called with', dateStr);
    const urlParams = new URLSearchParams(window.location.search);
    const packageId = urlParams.get('id') || '1';

    const apiUrl = '../backend/api/booking_status.php';
    const url = `${apiUrl}?packageId=${encodeURIComponent(packageId)}&departureDate=${encodeURIComponent(dateStr)}`;

    console.log('booking status API URL =', url, 'dateStr =', dateStr);

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            console.error('Failed to fetch booking status:', response.status);
            return;
        }

        const data = await response.json();
        console.log('booking status response =', data);

        if (!data.success) {
            console.warn('API error:', data.message);
            return;
        }

        const bookedEl = document.getElementById('current-booked-seats');
        const maxEl    = document.getElementById('current-max-participants');
        const minEl    = document.getElementById('current-min-participants');
        const remainEl = document.getElementById('current-remaining-seats');

        if (bookedEl) bookedEl.textContent = data.bookedSeats;
        if (maxEl)    maxEl.textContent    = data.maxParticipants;
        if (minEl)    minEl.textContent    = data.minParticipants;
        if (remainEl) remainEl.textContent = data.remainingSeats;

        // Guaranteed Departure :    
        try {
            const confirmLabel = document.querySelector('.label.secondary');
            const isGuaranteed = !!data.isGuaranteedDeparture || (Number(data.minParticipants || 0) > 0 && Number(data.bookedSeats || 0) >= Number(data.minParticipants || 0));
            if (confirmLabel) {
                if (isGuaranteed) {
                    confirmLabel.textContent = getI18nText('departureConfirmed');
                    confirmLabel.style.display = 'inline-block';
                } else {
                    confirmLabel.style.display = 'none';
                }
            }
        } catch (e) {
            // ignore
        }

    } catch (err) {
        console.error('Error updating booking status:', err);
    }
}

// SMT  

//    
async function loadPackageDetail() {
    const urlParams = new URLSearchParams(window.location.search);
    const packageId = urlParams.get('id') || '1';
    
    try {
        //  
        showLoadingState();
        
        // API    
        console.log(' ID DB  :', packageId);
        const response = await fetch(`../backend/api/packages.php?id=${encodeURIComponent(packageId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        
        if (result.success) {
            currentPackage = result.data;
            console.log('  :', currentPackage);
            console.log('currentPackage is now set:', window.currentPackage === currentPackage);
            renderPackageDetail(result.data);

            //    
            // -          (    "  "   )
            try {
                const pkg = currentPackage?.product ? currentPackage.product : currentPackage;
                const start = pkg?.sales_start_date || pkg?.salesStartDate || pkg?.sales_period || pkg?.salesPeriod || '';
                if (start && typeof start === 'string') {
                    const m = start.match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
                    if (m) {
                        currentYear = parseInt(m[1], 10);
                        currentMonth = parseInt(m[2], 10);
                    }
                }
            } catch (_) {}
            await loadAvailability(packageId, currentYear, currentMonth);
        } else {
            console.error('  :', result.message);
            showNotification('    .', 'error');
            
            // Fallback   
            currentPackage = getSamplePackageDetail(packageId);
            renderPackageDetail(currentPackage);

            //       
            try {
                await loadAvailability(packageId);
            } catch (e) {
                console.log('    ,   ');
            }
        }
        
    } catch (error) {
        console.error('Load package detail error:', error);
        
        //    fallback  
        showNotification('   .   .', 'warning');

        currentPackage = getSamplePackageDetail(packageId);
        renderPackageDetail(currentPackage);

        // fallback     
        try {
            await loadAvailability(packageId);
        } catch (e) {
            console.log('    ,   ');
        }
    }
}

//    
async function loadAvailability(packageId, year = null, month = null) {
    try {
        const yearParam = year || currentYear;
        const monthParam = month || currentMonth;
        console.log(`Loading availability for package ${packageId}, year: ${yearParam}, month: ${monthParam}`);

        const response = await fetch(`../backend/api/product_availability.php?id=${encodeURIComponent(packageId)}&year=${encodeURIComponent(yearParam)}&month=${encodeURIComponent(monthParam)}`);
        
        const result = await response.json();
        
        console.log('Availability API response:', result);
        
        if (result.success && result.data) {
            renderAvailableDates(result.data);

        // SMT  
            if (isInitialCalendarLoad) {
                isInitialCalendarLoad = false;
                autoSelectInitialDate(result.data);
            }
        // SMT  

        } else {
            console.error('    :', result.message || 'Unknown error');
            //     
            renderAvailableDates({
                year: yearParam,
                month: monthParam,
                availability: [],
                product: {}
            });
        }
    } catch (error) {
        console.error('Load availability error:', error);
        //      
        renderAvailableDates({
            year: year || currentYear,
            month: month || currentMonth,
            availability: [],
            product: {}
        });
    }
}

//    
function renderPackageDetail(data) {
    console.log('  :', data);

    //     
    // 1) product_detail.php : {product: {...}, images: [...]}
    // 2) packages.php : {packageId, packageName, ...}
    const packageInfo = data.product ? data.product : data;

    //   
    console.log('Package info for title:', packageInfo);
    console.log('Package name for title:', packageInfo.packageName);
    //       
    const fallbackTitle = packageInfo.packageName || 'Product Detail';
    document.title = `Smart Travel | ${fallbackTitle}`;

    //    - images  packageImageUrl 
    const images = packageInfo.images || [packageInfo.packageImageUrl || 'default-korea.jpg'];
    updateProductImages(images);

    //  PHP     
    // updateBreadcrumbs(packageInfo);

    //    
    updateBasicInfo(packageInfo);

    //   
    renderTabContent(packageInfo);

    // PHP     JavaScript  
    console.log('PHP   , JavaScript  ');

    //
    updateFixedBottomBar(packageInfo);

    // 로딩 해제
    hideLoadingState();
}

//    - PHP      
function updateProductImages(images) {
    const sliderContainer = document.querySelector('.slider');
    if (!sliderContainer) return;

    //    :  (1/N) 
    const bindImageCounter = () => {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.slick) return;
        const $slider = $(sliderContainer);
        $slider.off('.productDetailCounter');
        $slider.on('init.productDetailCounter reInit.productDetailCounter afterChange.productDetailCounter', function (event, slick, currentSlide) {
            const i = (currentSlide ? currentSlide : 0) + 1;
            const curEl = document.getElementById('imageCounterCurrent');
            const totEl = document.getElementById('imageCounterTotal');
            if (curEl) curEl.textContent = String(i);
            if (totEl && slick && typeof slick.slideCount !== 'undefined') totEl.textContent = String(slick.slideCount);
        });
    };
    bindImageCounter();

    // PHP      
    const existingImages = sliderContainer.querySelectorAll('img');
    if (existingImages.length > 0) {
        // PHP      JavaScript  
        console.log('PHP    , JavaScript  ');
        
        //   (  )
        if (typeof $ !== 'undefined' && $.fn.slick) {
            if ($(sliderContainer).hasClass('slick-initialized')) {
                $(sliderContainer).slick('destroy');
            }
            
            setTimeout(() => {
                try {
                    $(sliderContainer).slick({
                        dots: true,
                        arrows: false,
                        infinite: true,
                        speed: 500,
                        slidesToShow: 1,
                        slidesToScroll: 1,
                        autoplay: true,
                        autoplaySpeed: 3000,
                        adaptiveHeight: true,
                        appendDots: $('.slick-counter')
                    });
                    // init  slick   
                } catch (error) {
                    console.error('Slick   :', error);
                }
            }, 100);
        }
        return;
    }

    // PHP    JavaScript 
    const defaultImages = [
        '../images/@img_banner_product.jpg',
        '../images/@img_card1.jpg',
        '../images/@img_card2.jpg',
        '../images/@img_travel.jpg'
    ];

    let processedImages = [];

    if (!images || images.length === 0) {
        processedImages = defaultImages.map((url, index) => ({
            imageUrl: url,
            imageAlt: '  ' + (index + 1)
        }));
    } else {
        processedImages = images.map((image, index) => {
            let imageUrl;
            let imageAlt = '  ' + (index + 1);

            if (typeof image === 'string') {
                // uploads/products   
                if (image.includes('Product image') || image.includes('uploads/products')) {
                    imageUrl = '../uploads/products/' + image;
                } else {
                    imageUrl = image.startsWith('../images/') ? image : '../images/' + image;
                }
            } else if (image && image.imageUrl) {
                imageUrl = image.imageUrl.startsWith('../images/') ? image.imageUrl : '../images/' + image.imageUrl;
                imageAlt = image.imageAlt || imageAlt;
            } else {
                imageUrl = defaultImages[index % defaultImages.length];
            }

            return {
                imageUrl: imageUrl,
                imageAlt: imageAlt
            };
        });

        while (processedImages.length < 4) {
            const index = processedImages.length;
            processedImages.push({
                imageUrl: defaultImages[index % defaultImages.length],
                imageAlt: '  ' + (index + 1)
            });
        }
    }
    
    const sliderHtml = processedImages.map((img, index) =>
        `<div><img src="${img.imageUrl}" alt="${img.imageAlt || `  ${index + 1}`}" loading="lazy" onerror="this.src='../images/@img_banner_product.jpg'"></div>`
    ).join('');
    
    sliderContainer.innerHTML = sliderHtml;
    
    // jQuery  
    if (typeof $ !== 'undefined' && $.fn.slick) {
        if ($(sliderContainer).hasClass('slick-initialized')) {
            $(sliderContainer).slick('destroy');
        }
        
        setTimeout(() => {
            try {
                $(sliderContainer).slick({
                    dots: true,
                    arrows: false,
                    infinite: true,
                    speed: 500,
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    autoplay: true,
                    autoplaySpeed: 3000,
                    adaptiveHeight: true,
                    appendDots: $('.slick-counter')
                });
                // init  slick   
            } catch (error) {
                console.error('Slick   :', error);
            }
        }, 100);
    }
}

//  
function updateBreadcrumbs(packageInfo) {
    const breadcrumbs = document.querySelector('.breadcrumbs-type1');
    if (!breadcrumbs) return;

    const categoryMap = {
        'season': '',
        'region': '',
        'theme': '',
        'private': ''
    };

    const categoryName = categoryMap[packageInfo.packageCategory] || '';
    const subCategory = getSubCategory(packageInfo);

    breadcrumbs.innerHTML = `
        <li><a href="product-info.html?category=${packageInfo.packageCategory}">${categoryName}</a></li>
        <li aria-current="page">${subCategory}</li>
    `;
}

//   
function getSubCategory(product) {
    if (product.category === 'seasonal') {
        if (product.packageName.includes('') || product.packageName.includes('Cherry')) return '';
        if (product.packageName.includes('') || product.packageName.includes('Summer')) return '';
        if (product.packageName.includes('') || product.packageName.includes('Autumn')) return '';
        if (product.packageName.includes('') || product.packageName.includes('Winter')) return '';
        return product.subCategory || '';
    } else if (product.category === 'region') {
        if (product.packageName.includes('') || product.packageName.includes('Seoul')) return '';
        if (product.packageName.includes('') || product.packageName.includes('Busan')) return '';
        if (product.packageName.includes('') || product.packageName.includes('Jeju')) return '';
        return product.subCategory || '';
    } else if (product.category === 'theme') {
        if (product.packageName.includes('K-Culture') || product.packageName.includes('K-POP')) return 'K-Pop';
        if (product.packageName.includes('Temple') || product.packageName.includes('')) return 'Culture';
        if (product.packageName.includes('Culinary') || product.packageName.includes('')) return 'Food';
        return product.subCategory || 'Culture';
    } else if (product.category === 'private') {
        if (product.packageName.includes('VIP') || product.packageName.includes('Luxury')) return 'VIP';
        if (product.packageName.includes('Custom')) return 'Custom';
        return product.subCategory || 'Premium';
    }
    return product.subCategory || '';
}

//   
function updateBasicInfo(packageInfo) {
    //  
    const titleElement = document.querySelector('h1.text.fz20.fw600.lh28.black12');
    if (titleElement) {
        titleElement.textContent = packageInfo.packageName;
    }

    //    (   )
    const confirmLabel = document.querySelector('.label.secondary');
    if (confirmLabel) {
        if (packageInfo.isConfirmed) {
            confirmLabel.textContent = getI18nText('departureConfirmed');
            confirmLabel.style.display = 'inline-block';
        } else {
            confirmLabel.style.display = 'none';
        }
    }
}

//
function updateFixedBottomBar(packageInfo) {
    console.log('=== updateFixedBottomBar CALLED ===');

    // B2B 사용자만 하단 바 표시 (B2C는 에이전트를 통해 예약)
    const b2bUser = isB2BUser();
    console.log('[updateFixedBottomBar] isB2BUser:', b2bUser, 'accountType:', localStorage.getItem('accountType'));

    // B2B용 하단 바 (Book Now 버튼) - b2c-contact-bar는 제외
    let fixedBar = document.querySelector('.fixed-bottom-bar.b2b-booking-bar');
    // B2C용 Contact Agent 바 - ID로 찾기
    let b2cContactBar = document.getElementById('b2cContactAgentBar');
    console.log('[updateFixedBottomBar] fixedBar:', fixedBar, 'b2cContactBar:', b2cContactBar);

    if (!b2bUser) {
        // B2C 사용자: B2B용 하단 바 숨김, Contact Agent 바 표시
        console.log('[updateFixedBottomBar] B2C user - showing Contact Agent bar');
        if (fixedBar) {
            fixedBar.style.display = 'none';
        }
        if (b2cContactBar) {
            b2cContactBar.style.display = 'block';
            console.log('[updateFixedBottomBar] b2cContactBar display set to block');
        } else {
            console.log('[updateFixedBottomBar] WARNING: b2cContactBar not found!');
        }
        return;
    } else {
        // B2B 사용자: Contact Agent 바 숨김
        console.log('[updateFixedBottomBar] B2B user - hiding Contact Agent bar');
        if (b2cContactBar) {
            b2cContactBar.style.display = 'none';
        }
    }

    if (!fixedBar) {
        fixedBar = document.createElement('div');
        fixedBar.className = 'fixed-bottom-bar b2b-booking-bar';
        fixedBar.style.cssText = `
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        `;
        document.body.appendChild(fixedBar);
    } else {
        fixedBar.style.display = 'flex';
    }

    // Handle undefined packagePrice to prevent NaN -  
    console.log('DEBUG: updateFixedBottomBar called with:', packageInfo);
    console.log('DEBUG: packageInfo.packagePrice:', packageInfo.packagePrice, typeof packageInfo.packagePrice);
    console.log('DEBUG: packageInfo.formattedPrice:', packageInfo.formattedPrice);

    let formattedPrice;
    // 1.      
    if (packageInfo.formattedPrice && !packageInfo.formattedPrice.includes('NaN')) {
        formattedPrice = packageInfo.formattedPrice;
        console.log('DEBUG: Using formattedPrice:', formattedPrice);
    }
    // 2. packagePrice   
    else if (packageInfo.packagePrice && !isNaN(packageInfo.packagePrice) && packageInfo.packagePrice > 0) {
        formattedPrice = `₱${new Intl.NumberFormat('ko-KR').format(packageInfo.packagePrice)}`;
        console.log('DEBUG: Formatted packagePrice:', formattedPrice);
    }
    // 3.   fallback -   
    else {
        formattedPrice = '₱340,000'; //   fallback  
        console.log('DEBUG: Using fallback price (no valid price found):', formattedPrice);
    }
    
    
    fixedBar.innerHTML = `
        <button class="btn primary lg booking-btn w100" onclick="proceedToBookingFromBar()" disabled>
            ${getI18nText('selectDate')}
        </button>
    `;
}

//     (DB )
function renderAvailableDates(data) {
    console.log('renderAvailableDates called with data:', data);
    const calendarBody = document.querySelector('.calendar tbody') || document.querySelector('#calendar-body');
    if (!calendarBody) {
        console.error('Calendar body not found');
        return;
    }

    const availability = data.availability || [];
    const product = data.product || {};

    console.log('Availability array:', availability, 'Length:', availability.length);

    //   availability  
    currentAvailabilityData = data;

    //    Book Now
    // B2B 사용자만 버튼 표시 (B2C는 에이전트를 통해 예약)
    try {
        const bookingBtn = document.querySelector('.booking-btn');
        const b2bUser = isB2BUser();
        if (bookingBtn) {
            if (!b2bUser) {
                // B2C 사용자: 버튼 숨김
                bookingBtn.style.display = 'none';
            } else {
                bookingBtn.style.display = '';
                bookingBtn.disabled = true;
                bookingBtn.classList.add('inactive');
                bookingBtn.removeAttribute('data-selected-date');
                bookingBtn.removeAttribute('data-availability-id');
                bookingBtn.textContent = getI18nText('selectDate');
            }
        }
        selectedDate = null;
    } catch (_) {}
    
    //   
    const monthDisplay = document.getElementById('calendar-month');
    if (monthDisplay) {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        monthDisplay.textContent = `${monthNames[data.month - 1]} ${data.year}`;
    }
    
    //  
    const firstDay = new Date(data.year, data.month - 1, 1).getDay();
    const daysInMonth = new Date(data.year, data.month, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    //     
    const availabilityMap = {};
    if (Array.isArray(availability)) {
        availability.forEach(item => {
            if (item && item.availableDate) {
                const date = new Date(item.availableDate);
                const day = date.getDate();
                availabilityMap[day] = item;
            }
        });
    }
    
    console.log('Availability map:', availabilityMap);
    
    let calendarHtml = '';
    let date = 1;
    
    for (let week = 0; week < 6; week++) {
        calendarHtml += '<tr>';
        
        for (let day = 0; day < 7; day++) {
            if (week === 0 && day < firstDay) {
                calendarHtml += '<td></td>';
            } else if (date > daysInMonth) {
                calendarHtml += '<td></td>';
            } else {
                const currentDate = `${data.year}-${String(data.month).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                const availabilityInfo = availabilityMap[date];
                const isToday = date === today.getDate() && (data.month - 1) === today.getMonth() && data.year === today.getFullYear();
                const isSelected = selectedDate === currentDate;
                
                let cellClass = '';
                let cellContent = '';
                let clickEvent = '';
                let ariaLabel = '';
                
                if (isToday && !isSelected) {
                    cellClass += 'today ';
                }
                
                if (isSelected) {
                    cellClass += 'selected ';
                }
                
                // SMT   - maxParticipants=0      
                if (availabilityInfo) {
                    if (availabilityInfo.status === 'available') {
                        // maxSeats 0 closed  ( )
                        if (availabilityInfo.maxSeats <= 0 || availabilityInfo.remainingSeats <= 0) {
                            cellClass += 'close';
                            cellContent = `
                                ${date}
                                <p class="text fz10 fw400 lh14" style="color: #999;">${getI18nText('soldOut')}</p>
                            `;
                            ariaLabel = `${data.month} ${date}, ${getI18nText('soldOut')}`;
                        } else {
                            cellClass += 'available';
                            // 사용자 타입에 맞는 가격 표시 (이중 가격 시스템)
                            const displayPrice = getDisplayPriceForDate(availabilityInfo);
                            const price = Math.floor(displayPrice.price / 1000);
                            cellContent = `
                                ${date}
                                <p class="text fz12 fw400 lh16">₱${price}K</p>
                            `;
                            clickEvent = `onclick="selectDate('${currentDate}', ${availabilityInfo.availabilityId})"`;
                            ariaLabel = `${data.month} ${date},  ${price}`;
                        }
                    } else if (availabilityInfo.status === 'reserved') {
                        cellClass += 'reservation';
                        cellContent = `
                            ${date}
                            <p class="text fz10 fw400 lh14" style="color: #999;">${getI18nText('soldOut')}</p>
                        `;
                        ariaLabel = `${data.month} ${date}, ${getI18nText('soldOut')}`;
                    } else if (availabilityInfo.status === 'closed') {
                        cellClass += 'close';
                        cellContent = `
                            ${date}
                            <p class="text fz10 fw400 lh14" style="color: #999;">${getI18nText('soldOut')}</p>
                        `;
                        ariaLabel = `${data.month} ${date}, ${getI18nText('soldOut')}`;
                    }
                // SMT  
                } else {
                    //   
                    const checkDate = new Date(data.year, data.month - 1, date);
                    checkDate.setHours(0, 0, 0, 0);
                    if (checkDate < today) {
                        cellClass += 'inactive';
                    } else {
                        //    
                        cellClass += 'inactive';
                    }
                    cellContent = `${date}`;
                }
                
                // const trimmedClass = cellClass.trim();
                // const hasSpecialStatus = trimmedClass.includes('available') || 
                //     trimmedClass.includes('selected') || 
                //     trimmedClass.includes('close') || 
                //     trimmedClass.includes('reservation');
                // const isDefaultCell = !hasSpecialStatus;
                
                // const defaultStyle = isDefaultCell ? 'style="background-color: white;"' : '';
                
                // calendarHtml += `<td class="${trimmedClass}" ${clickEvent} role="gridcell" tabindex="0" aria-label="${ariaLabel || `${data.month}/${date}`}" ${defaultStyle}>${cellContent}</td>`;
                calendarHtml += `<td class="${cellClass.trim()}" ${clickEvent} role="gridcell" tabindex="0" aria-label="${ariaLabel || `${data.month}/${date}`}">${cellContent}</td>`;
                date++;
            }
        }
        
        calendarHtml += '</tr>';
        
        if (date > daysInMonth) break;
    }
    
    console.log('Rendered calendar HTML length:', calendarHtml.length);
    calendarBody.innerHTML = calendarHtml;
    
    if (calendarHtml.length === 0) {
        console.error('Calendar HTML is empty!');
    }
    
    //       
    if (selectedDate) {
        const todayCells = document.querySelectorAll('.calendar td.today:not(.selected)');
        todayCells.forEach(td => {
            td.style.border = 'none';
        });
    }
}

// SMT  
function autoSelectInitialDate(data) {
    if (!data || !Array.isArray(data.availability) || data.availability.length === 0) {
        console.log('[autoSelectInitialDate] no availability data');
        return;
    }

    const today = new Date();
    const todayYear = today.getFullYear();
    const todayMonth = today.getMonth() + 1;
    const todayDay = today.getDate();

    //    ·  (      )
    if (data.year !== todayYear || data.month !== todayMonth) {
        console.log('[autoSelectInitialDate] calendar month is not today month, skip');
        return;
    }

    const todayStr = `${todayYear}-${String(todayMonth).padStart(2, '0')}-${String(todayDay).padStart(2, '0')}`;

    const isBookable = (item) => {
        if (!item) return false;
        const status = String(item.status || '').toLowerCase();
        const remain = Number(item.remainingSeats ?? item.remaining_seats ?? 0);
        return status === 'available' && remain > 0;
    };

    // 1)  "  "  
    let target = data.availability.find(item => item.availableDate === todayStr && isBookable(item));

    // 2)  "  "     
    if (!target) {
        target = data.availability.find(item => isBookable(item));
    }

    if (target) {
        console.log('[autoSelectInitialDate] selecting', target.availableDate, 'ID:', target.availabilityId);
        selectDate(target.availableDate, target.availabilityId);
    } else {
        console.log('[autoSelectInitialDate] no bookable date, skip auto select');
    }
}
// SMT  

//     ( DB  )
function generateAvailableDates(year, month, package) {
    const availableDates = [];
    const today = new Date();
    const currentDate = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    //          
    for (let day = 1; day <= daysInMonth; day++) {
        const checkDate = new Date(year, month, day);
        
        //   
        if (checkDate < today) continue;
        
        //      
        const dayOfWeek = checkDate.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6 || // 
            day % 5 === 0 || // 5  
            [15, 20, 25].includes(day)) { //  
            
            const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            availableDates.push(dateString);
        }
    }
    
    return availableDates;
}

//   selectDate   ()

// SMT   -   + localStorage  
//  
async function isLoggedInBySession() {
    try {
        const r = await fetch('../backend/api/check-session.php', { credentials: 'include' });
        const j = await r.json();
        if (j && j.success && j.isLoggedIn) {
            return true;
        }
        //    localStorage      
        const localLogin = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');
        if (localLogin && userId) {
            console.log('Server session not found, but localStorage has login info');
            return true;
        }
        return false;
    } catch (e) {
        console.warn('check-session failed, fallback to localStorage:', e);
        return (localStorage.getItem('isLoggedIn') === 'true');
    }
}
// SMT  

async function hydrateB2BFromSession() {
    try {
        const r = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
        const j = await r.json();

        const isB2B = !!(j && j.success && j.isLoggedIn && j.user && j.user.isB2B === true);
        if (typeof window !== 'undefined') {
            window.__smartTravelIsB2B = isB2B;
            window.smartTravelUser = window.smartTravelUser || {};
            window.smartTravelUser.isB2B = isB2B;
        }

        // B2B 예약 제한 해제 - UI 숨기지 않음
        // B2B 사용자도 예약 가능하며, 가격만 다르게 표시됨
    } catch (e) {
        //     PHP  ( B2C )
        console.warn('hydrateB2BFromSession failed:', e);
    }
}

async function requireLoginOrRedirect() {
    const ok = await isLoggedInBySession();
    if (ok) return true;

    if (confirm(getI18nText('loginRequired'))) {
        // dev_tasks #116: 로그인 후 예약 페이지로 확실히 복귀하도록 sessionStorage 키를 통일
        try { sessionStorage.setItem('redirectAfterLogin', window.location.href); } catch (_) {}
        localStorage.setItem('returnUrl', window.location.href);
        location.href = 'login.html';
    }
    return false;
}

// ()    proceedToBooking           .

//    (DB  )
function renderTabContent(data) {
    console.log('  :', data);

    //    (  - schedules  )
    // renderItineraryTab(data.schedules);

    // /   
    renderIncludesTab({
        includes: data.includes || [],
        excludes: data.excludes || []
    });

    //  , /,     (  )
    // renderGuideTab(data.guides);
    // renderCancellationTab(data.cancellations);
    // renderVisaTab(data.visas);
}

//    (PHP   )
async function loadScheduleData(packageId) {
    console.log('PHP     JavaScript  ');
    return;
}

//   
function renderItineraryTab(schedules) {
    //   HTML  
    const scheduleContainer = document.querySelector('#schedule');
    if (!scheduleContainer) {
        console.log('Schedule container not found, looking for day list');
        //    
        const dayList = document.querySelector('.list-type6');
        if (dayList && schedules && schedules.length > 0) {
            renderScheduleList(dayList, schedules);
        }
        return;
    }
    
    //   
    const meetingCard = scheduleContainer.querySelector('.card-type6');
    if (meetingCard && schedules.length > 0) {
        const firstSchedule = schedules[0];
        if (firstSchedule.meetingTime && firstSchedule.meetingPlace) {
            const timeElement = meetingCard.querySelector('li:first-child span');
            const placeElement = meetingCard.querySelector('li:nth-child(2) span');
            const addressElement = meetingCard.querySelector('li:nth-child(3) span');
            
            if (timeElement) timeElement.textContent = firstSchedule.meetingTime;
            if (placeElement) placeElement.textContent = firstSchedule.meetingPlace;
            if (addressElement) addressElement.textContent = firstSchedule.meetingAddress || '';
        }
    }
    
    //   
    const scheduleList = scheduleContainer.querySelector('.list-type6');
    if (scheduleList) {
        renderScheduleList(scheduleList, schedules);
    }
}

//    
function renderScheduleList(listElement, schedules) {
    if (!listElement || !schedules) return;

    const scheduleHtml = schedules.map(schedule => {
        //   
        const activitiesList = schedule.activitiesList ||
                              (schedule.activities ? schedule.activities.split('\n') : []);

        //   
        const mealText = getMealText(schedule.meals);

        return `
            <li>
                <a href="#none" class="align both vm btn-folding">
                    <span class="text fz14 fw600 lh22 reded">${schedule.dayNumber}</span>
                    <div class="text fz14 fw600 lh22 black12">${schedule.dayTitle || schedule.title || ''}</div>
                    <img src="../images/ico_arrow_down_black.svg" alt="">
                </a>
                <div class="card-wrap mt16" style="display: none;">
                    ${schedule.description ? `
                        <div class="px12 pb10">
                            <p class="text fz14 fw400 lh22 black12">${schedule.description}</p>
                        </div>
                    ` : ''}
                    ${activitiesList.length > 0 ? `
                        <ul class="list-type7">
                            ${activitiesList.map((activity, index) => `
                                <li>
                                    <div class="pl12">
                                        <div class="text fz14 fw600 lh22 black12">${activity}</div>
                                    </div>
                                </li>
                            `).join('')}
                        </ul>
                    ` : ''}

                    <div class="px12">
                        ${schedule.accommodation ? `
                            <div class="card-type7 mt8">
                                <div class="title ico1"></div>
                                <div class="pt10">
                                    <div class="text fz14 fw600 lh22 black12">${schedule.accommodation}</div>
                                </div>
                            </div>
                        ` : ''}

                        ${schedule.transportation ? `
                            <div class="card-type7 mt10">
                                <div class="title ico2"></div>
                                <div class="pt10">
                                    <div class="text fz14 fw500 lh22 black12">${schedule.transportation}</div>
                                </div>
                            </div>
                        ` : ''}

                        ${mealText ? `
                            <div class="card-type7 mt10">
                                <div class="title ico3"></div>
                                <div class="pt10">
                                    <div class="text fz14 fw500 lh22 black12">${mealText}</div>
                                </div>
                            </div>
                        ` : ''}

                        ${schedule.notes ? `
                            <div class="card-type7 mt10">
                                <div class="title"></div>
                                <div class="pt10">
                                    <div class="text fz12 fw400 lh18 gray6b">${schedule.notes}</div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </li>
        `;
    }).join('');

    listElement.innerHTML = scheduleHtml;

    //   
    setupScheduleFolding();
}

//    
function getMealText(meals) {
    if (!meals) return '';

    const mealMap = {
        'breakfast': '',
        'lunch': '',
        'dinner': '',
        'all_meals': ', , ',
        'none': ''
    };

    if (typeof meals === 'string') {
        return mealMap[meals] || meals;
    }

    if (Array.isArray(meals)) {
        return meals.map(meal => mealMap[meal] || meal).filter(m => m).join(', ');
    }

    return '';
}

//    
function setupScheduleFolding() {
    const foldingBtns = document.querySelectorAll('.btn-folding');
    foldingBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cardWrap = this.nextElementSibling;
            if (cardWrap) {
                if (cardWrap.style.display === 'none') {
                    cardWrap.style.display = 'block';
                    this.classList.add('active');
                } else {
                    cardWrap.style.display = 'none';
                    this.classList.remove('active');
                }
            }
        });
    });
}

//   
function getMealTypeName(mealType) {
    const mealTypeMap = {
        'breakfast': '',
        'lunch': '',
        'dinner': ''
    };
    return mealTypeMap[mealType] || mealType;
}

//   
function renderItineraryInContainer(container, package) {
    //  DB     ,   
    const itinerary = package.itinerary || generateSampleItinerary(package);
    
    const itineraryHtml = `
        <div class="px20 pt24">
            <div class="text fz16 fw600 lh24 black12 mb16"> </div>
            ${itinerary.map(day => `
                <div class="itinerary-day mb24">
                    <div class="day-header">
                        <span class="day-number">${day.day}</span>
                        <span class="day-title">${day.title}</span>
                    </div>
                    <div class="day-content">
                        <p class="description">${day.description}</p>
                        ${day.meals ? `<div class="meals-info"><strong> :</strong> ${day.meals}</div>` : ''}
                        ${day.accommodations ? `<div class="hotel-info"><strong>:</strong> ${day.accommodations}</div>` : ''}
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    container.innerHTML = itineraryHtml;
}

// /   
function renderIncludesTab(inclusions) {
    const includesContainer = document.querySelector('#inclusive');
    if (!includesContainer) return;

    //   : { includes: [], excludes: [] }
    const includedItems = inclusions.includes || [];
    const excludedItems = inclusions.excludes || [];

    // /     
    //     ,   
    // ( HTML  /   )

    console.log('Includes :', includedItems);
    console.log('Excludes :', excludedItems);
}

//   
function renderIncludesInContainer(container, package) {
    const includes = package.includes || [
        '   ',
        '  (: 2 1)',
        '  ',
        ' ',
        '  ',
        ' '
    ];
    
    const excludes = package.excludes || [
        ' ',
        ' ',
        '    ',
        ' (, )',
        '   ()'
    ];
    
    const includesHtml = `
        <div class="px20 pt24">
            <div class="includes-section mb32">
                <div class="text fz16 fw600 lh24 black12 mb16"> </div>
                <ul class="includes-list">
                    ${includes.map(item => `<li class="include-item">✓ ${item}</li>`).join('')}
                </ul>
            </div>
            
            <div class="excludes-section">
                <div class="text fz16 fw600 lh24 black12 mb16"> </div>
                <ul class="excludes-list">
                    ${excludes.map(item => `<li class="exclude-item">✗ ${item}</li>`).join('')}
                </ul>
            </div>
        </div>
    `;
    
    container.innerHTML = includesHtml;
}

//    
function renderGuideTab(guides) {
    const guideContainer = document.querySelector('#use_guide');
    if (!guideContainer) return;
    
    //   
    const existingContent = guideContainer.querySelector('.text.fz14.fw400.lh22.black12.mt20');
    if (existingContent) {
        existingContent.remove();
    }
    
    //   
    if (guides && guides.length > 0) {
        guides.forEach(guide => {
            const guideDiv = document.createElement('div');
            guideDiv.className = 'text fz14 fw400 lh22 black12 mt20';
            guideDiv.innerHTML = `
                <div class="text fz14 fw600 lh22 black12 mb8">${guide.title}</div>
                <div class="text fz14 fw400 lh22 black12">${guide.content}</div>
                ${guide.fileUrl ? `
                    <button class="btn line lg active ico2 mt16" type="button" onclick="window.open('${guide.fileUrl}', '_blank')">
                        ${guide.fileName || ' '}
                    </button>
                ` : ''}
            `;
            guideContainer.appendChild(guideDiv);
        });
    } else {
        const defaultDiv = document.createElement('div');
        defaultDiv.className = 'text fz14 fw400 lh22 black12 mt20';
        defaultDiv.textContent = '   .';
        guideContainer.appendChild(defaultDiv);
    }
}

// /   
function renderCancellationTab(cancellations) {
    const cancellationContainer = document.querySelector('#cancellation_refund');
    if (!cancellationContainer) return;
    
    //    
    const existingList = cancellationContainer.querySelector('ul');
    if (existingList) {
        existingList.remove();
    }
    
    //    
    if (cancellations && cancellations.length > 0) {
        const ul = document.createElement('ul');
        cancellations.forEach(cancellation => {
            const li = document.createElement('li');
            li.className = 'text fz14 fw400 lh22 black12';
            li.textContent = `•  ${cancellation.daysBefore} : ${cancellation.refundRate}% ${cancellation.description ? ` (${cancellation.description})` : ''}`;
            ul.appendChild(li);
        });
        cancellationContainer.appendChild(ul);
    } else {
        //  
        const ul = document.createElement('ul');
        const defaultRules = [
            '•  15 : 100%  (  )',
            '•  8-14 : 50%  (50%  )',
            '•  4-7 : 30%  (70%  )',
            '•  1-3 :   (100%  )'
        ];
        
        defaultRules.forEach(rule => {
            const li = document.createElement('li');
            li.className = 'text fz14 fw400 lh22 black12';
            li.textContent = rule;
            ul.appendChild(li);
        });
        cancellationContainer.appendChild(ul);
    }
}

//     
function renderVisaTab(visas) {
    const visaContainer = document.querySelector('#visa_application');
    if (!visaContainer) return;
    
    //   
    const existingContent = visaContainer.querySelector('.text.fz14.fw400.lh22.black12.mt20');
    if (existingContent) {
        existingContent.remove();
    }
    
    //   
    if (visas && visas.length > 0) {
        visas.forEach(visa => {
            const visaDiv = document.createElement('div');
            visaDiv.className = 'text fz14 fw400 lh22 black12 mt20';
            visaDiv.innerHTML = `
                <div class="text fz14 fw600 lh22 black12 mb8">${visa.title}</div>
                <div class="text fz14 fw400 lh22 black12 mb12">${visa.content}</div>
                ${visa.requirements ? `
                    <div class="text fz14 fw600 lh22 black12 mb8"> </div>
                    <div class="text fz14 fw400 lh22 black12">${visa.requirements}</div>
                ` : ''}
            `;
            visaContainer.appendChild(visaDiv);
        });
    } else {
        const defaultDiv = document.createElement('div');
        defaultDiv.className = 'text fz14 fw400 lh22 black12 mt20';
        defaultDiv.textContent = '     .';
        visaContainer.appendChild(defaultDiv);
    }
}

//   
function generateSampleItinerary(package) {
    const days = package.duration_days || 3;
    const itinerary = [];
    
    for (let i = 1; i <= days; i++) {
        let dayInfo = {
            day: i,
            title: `Day ${i}`,
            description: `${i} .`,
            meals: ', ',
            accommodations: i === days ? null : ' '
        };
        
        if (package.packageName.includes('Seoul') || package.packageName.includes('')) {
            if (i === 1) {
                dayInfo = {
                    day: i,
                    title: '    ',
                    description: '  →   →  →  ',
                    meals: ', ',
                    accommodations: '  '
                };
            }
        } else if (package.packageName.includes('Cherry') || package.packageName.includes('')) {
            if (i === 1) {
                dayInfo = {
                    day: i,
                    title: '   ',
                    description: '  →  →  →  ',
                    meals: ', ',
                    accommodations: '  '
                };
            }
        }
        
        itinerary.push(dayInfo);
    }
    
    return itinerary;
}

//   
function generateSampleGuideInfo(package) {
    return {
        preparation: [
            ' ( 6 )',
            ' ',
            ' ',
            '  ',
            '   '
        ],
        notices: [
            '   ',
            '     ',
            '     ',
            '   ',
            '     '
        ]
    };
}

//   
function updatePriceDisplay(price) {
    const priceElements = document.querySelectorAll('.price-display, .package-price');
    const formattedPrice = new Intl.NumberFormat('ko-KR').format(price);
    
    priceElements.forEach(element => {
        element.textContent = `₱ ${formattedPrice}`;
    });
}

//   
function showLoadingState() {
    //    
    const existingLoading = document.getElementById('loading-overlay');
    if (existingLoading) {
        existingLoading.remove();
    }
    
    //   
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay';
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="loading-spinner"></div>
        <div style="margin-top: 20px; text-align: center; color: #666;">${getI18nText('loading')}</div>
    `;
    loadingOverlay.setAttribute('aria-label', 'Loading');
    loadingOverlay.setAttribute('role', 'status');
    
    document.body.appendChild(loadingOverlay);
}

//   
function hideLoadingState() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

//  
function navigateMonth(direction) {
    currentMonth += direction;
    
    //  
    if (currentMonth > 12) {
        currentMonth = 1;
        currentYear++;
    } else if (currentMonth < 1) {
        currentMonth = 12;
        currentYear--;
    }
    
    //      
    const urlParams = new URLSearchParams(window.location.search);
    const packageId = urlParams.get('id') || '1';
    loadAvailability(packageId);
}

//     
function setupCalendarNavigation() {
    const prevBtn = document.querySelector('.btn-prev-month');
    const nextBtn = document.querySelector('.btn-next-month');
    
    if (prevBtn) {
        prevBtn.onclick = () => navigateMonth(-1);
        prevBtn.onkeydown = (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                navigateMonth(-1);
            }
        };
    }
    
    if (nextBtn) {
        nextBtn.onclick = () => navigateMonth(1);
        nextBtn.onkeydown = (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                navigateMonth(1);
            }
        };
    }
}

//
// Setup back button with duplicate click prevention
function setupBackButton() {
    const backBtn = document.getElementById('productBackButton');
    if (!backBtn) return;

    let navigating = false;
    backBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (navigating) return;
        navigating = true;

        // Simply go back - no loop possible since we use location.replace() when going to select-reservation
        history.back();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('product-detail.php')) {
        console.log('Product detail page loaded');
        console.log('Current URL:', window.location.href);
        console.log('URL params:', new URLSearchParams(window.location.search));
        //    B2B  1 (  )
        hydrateB2BFromSession();
        loadPackageDetail();
        setupCalendarNavigation();
        setupProductDescriptionToggle(); //
        setupBackButton(); // Setup back button handler

        // (Upcoming Trip) "Full Travel Itinerary"
        try {
            const params = new URLSearchParams(window.location.search);
            const tab = (params.get('tab') || '').toLowerCase();
            if (tab === 'itinerary' || tab === 'schedule') {
                setTimeout(() => {
                    const scheduleEl = document.getElementById('schedule');
                    if (scheduleEl) {
                        scheduleEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        window.location.hash = '#schedule';
                    }
                }, 250);
            }
        } catch (_) {}
    }
});

//   
function showNotification(message, type = 'info') {
    //   
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        max-width: 300px;
    `;
    
    //   
    const colors = {
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#FF9800',
        error: '#F44336'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // 3   
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// CSS  
(function addProductDetailStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .calendar td.available {
            cursor: pointer;
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .calendar td.available:hover {
            background-color: #e9ecef;
            transform: scale(1.05);
        }
        
        .calendar td.available:focus {
            outline: 2px solid #FF6B6B;
            outline-offset: -2px;
        }
        
        .calendar td.selected {
            background-color: #FF6B6B;
            color: white;
        }
        
        .calendar td.inactive {
            color: #6c757d;
            background-color: white;
            opacity: 0.5;
        }

        /* SMT   -    */
        .calendar td.close {
            color: #999;
            background-color: #f0f0f0;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .calendar td.reservation {
            color: #999;
            background-color: #f0f0f0;
            cursor: not-allowed;
            opacity: 0.6;
        }
        /* SMT   */

        .calendar td.today {
            border-radius: 4px;
        }
        
        .itinerary-day {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        
        .itinerary-day h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .includes-section ul {
            list-style: none;
            padding: 0;
        }
        
        .includes-section ul li {
            padding: 5px 0;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .fixed-bottom-bar .booking-btn.disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .includes-list, .excludes-list, .preparation-list, .notice-list, .docs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .includes-list li, .excludes-list li, .preparation-list li, .notice-list li, .docs-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .include-item {
            color: #4CAF50;
        }
        
        .exclude-item {
            color: #f44336;
        }
    `;
    document.head.appendChild(style);
})();

// Fallback    -  API  ID 
function getSamplePackageDetail(packageId) {
    const samplePackages = {
        '1': {
            packageId: 1,
            packageName: '     5 6',
            packagePrice: 340000,
            packageCategory: 'season',
            packageDescription: '    , ·· ',
            duration_days: 3,
            meeting_location: ' 2',
            meeting_time: '09:00',
            packageType: 'standard',
            formattedPrice: '₱340,000',
            images: [
                '../images/@img_card1.jpg',
                '../images/@img_banner_product.jpg',
                '../images/@img_travel.jpg'
            ],
            includes: [
                ' ',
                '  (2)',
                ' ',
                '',
                ''
            ],
            excludes: [
                '',
                '',
                '',
                ''
            ],
            highlights: [
                ' ',
                ' ',
                ' ',
                ' ',
                ' '
            ]
        },
        '3': {
            packageId: 3,
            packageName: '   4 5',
            packagePrice: 450000,
            packageCategory: 'season',
            packageDescription: '   ',
            duration_days: 3,
            meeting_location: '',
            meeting_time: '09:00',
            packageType: 'standard',
            formattedPrice: '₱450,000',
            images: [
                '../images/@img_travel.jpg',
                '../images/@img_banner_product.jpg'
            ],
            includes: [' ', ' ', '', ''],
            excludes: ['', ''],
            highlights: ['', '', ' ']
        },
        '4': {
            packageId: 4,
            packageName: '    2 3',
            packagePrice: 180000,
            packageCategory: 'season',
            packageDescription: '   ',
            duration_days: 3,
            meeting_location: '',
            meeting_time: '09:00',
            packageType: 'standard',
            formattedPrice: '₱180,000',
            images: [' '],
            includes: ['', '', ''],
            excludes: [''],
            highlights: [' ', ' ']
        },
        //   ...
        'default': {
            packageId: packageId,
            packageName: 'Smart Travel ',
            packagePrice: 340000,
            packageCategory: 'season',
            packageDescription: '   .',
            duration_days: 3,
            meeting_location: '',
            meeting_time: '09:00',
            packageType: 'standard',
            formattedPrice: '₱340,000',
            images: [
                '../images/@img_banner_product.jpg',
                '../images/@img_travel.jpg'
            ],
            includes: [
                ' ',
                ' ',
                '',
                ''
            ],
            excludes: [
                '',
                ''
            ],
            highlights: [
                '  ',
                ' ',
                ' '
            ]
        }
    };

    return samplePackages[packageId] || samplePackages['default'];
}

//    
function selectDate(date, availabilityId) {
    console.log(' :', date, 'ID:', availabilityId);

    //   
    selectedDate = date;

    //     -  selected  
    document.querySelectorAll('.calendar td.selected').forEach(td => {
        td.classList.remove('selected');
    });

    //     
    const dateNumber = parseInt(date.split('-')[2]); // YYYY-MM-DD DD 
    const dateParts = date.split('-');
    const year = parseInt(dateParts[0]);
    const month = parseInt(dateParts[1]);
    const day = parseInt(dateParts[2]);
    
    //       
    let targetCell = null;
    const allCells = document.querySelectorAll('.calendar tbody td');
    
    allCells.forEach(cell => {
        const cellText = cell.textContent.trim();
        const cellNumber = parseInt(cellText.split('\n')[0] || cellText);
        //      
        const monthDisplay = document.getElementById('calendar-month');
        if (monthDisplay) {
            const displayText = monthDisplay.textContent;
            //  :    available  
            if (cellNumber === day && cell.classList.contains('available')) {
                // onclick   
                const onclickAttr = cell.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes(date)) {
                    targetCell = cell;
                } else if (!onclickAttr) {
                    // onclick   
                    targetCell = cell;
                }
            }
        }
    });

    if (targetCell) {
        targetCell.classList.add('selected');
        
        //      today  
        const todayCells = document.querySelectorAll('.calendar td.today:not(.selected)');
        todayCells.forEach(td => {
            td.style.border = 'none';
        });
    } else {
        console.warn('Target cell not found for date:', date);
    }

    //
    const bookingBtn = document.querySelector('.booking-btn');
    // 선택된 날짜가 실제로 예약 가능한지(좌석>0 / maxSeats>0 / status=available) 최종 검증 (dev_tasks #115)
    const selectedAvailability = currentAvailabilityData?.availability?.find?.(item => item && item.availableDate === date) || null;
    const bookable = isAvailabilityBookable(selectedAvailability);

    // B2B 사용자만 Book Now 버튼 활성화 (B2C는 에이전트를 통해 예약)
    const b2bUser = isB2BUser();

    if (bookingBtn) {
        if (!b2bUser) {
            // B2C 사용자: 버튼 숨김
            bookingBtn.style.display = 'none';
        } else if (!bookable) {
            bookingBtn.style.display = '';
            bookingBtn.textContent = getI18nText('soldOut');
            bookingBtn.disabled = true;
            bookingBtn.classList.add('inactive');
            bookingBtn.removeAttribute('data-selected-date');
            bookingBtn.removeAttribute('data-availability-id');
        } else {
            bookingBtn.style.display = '';
            bookingBtn.textContent = getI18nText('bookNow');
            bookingBtn.disabled = false;
            bookingBtn.classList.remove('inactive');
            //   availability ID
            bookingBtn.setAttribute('data-selected-date', date);
            bookingBtn.setAttribute('data-availability-id', availabilityId);
        }
    }

    //      
    updateBottomBarPrice(date);
    // SMT  
    updateBookingStatusForDate(date);
    // SMT  
}

//      (이중 가격 시스템 적용)
function updateBottomBarPrice(selectedDate) {
    if (!currentAvailabilityData || !currentAvailabilityData.availability) {
        console.log('Availability data not found');
        return;
    }

    //   availability
    const selectedAvailability = currentAvailabilityData.availability.find(item =>
        item.availableDate === selectedDate
    );

    if (!selectedAvailability) {
        console.log('Selected date availability not found:', selectedDate);
        return;
    }

    //사용자 타입에 맞는 가격 표시
    const displayPrice = getDisplayPriceForDate(selectedAvailability);
    const priceElement = document.querySelector('.fixed-bottom-bar .price-info .text.fz20.fw600.lh28.black12');
    if (priceElement) {
        const formattedPrice = `₱${new Intl.NumberFormat('ko-KR').format(displayPrice.price)}`;
        priceElement.textContent = formattedPrice;
        console.log(`가격 업데이트: ${selectedDate} → ${formattedPrice} (${displayPrice.priceTier})`);
    }
}

//
async function proceedToBookingFromBar() {
    const bookingBtn = document.querySelector('.booking-btn');
    const selectedDate = bookingBtn?.getAttribute('data-selected-date');
    const availabilityId = bookingBtn?.getAttribute('data-availability-id');
    
    if (!selectedDate) {
        showNotification(getI18nText('selectDateFirst'), 'warning');
        return;
    }

    // 예약 가능 여부 재검증 (dev_tasks #115)
    const selectedAvailability = currentAvailabilityData?.availability?.find?.(item => item && item.availableDate === selectedDate) || null;
    if (!isAvailabilityBookable(selectedAvailability)) {
        showNotification(getI18nText('soldOut'), 'warning');
        try {
            bookingBtn.disabled = true;
            bookingBtn.classList.add('inactive');
            bookingBtn.textContent = getI18nText('soldOut');
            bookingBtn.removeAttribute('data-selected-date');
            bookingBtn.removeAttribute('data-availability-id');
        } catch (_) {}
        return;
    }

    if (!(await requireLoginOrRedirect())) return;
    await proceedToBooking(selectedDate, availabilityId);
}

//
async function proceedToBooking(date, dateId) {
    if (!currentPackage) {
        showNotification(getI18nText('loading'), 'warning');
        return;
    }

    if (!(await requireLoginOrRedirect())) return;

    //    (  )
    const params = new URLSearchParams({
        package_id: currentPackage.packageId,
        departure_date: date
    });

    // Navigate to select-reservation (preserve history so back button returns here)
    location.href = `select-reservation.php?${params.toString()}`;
}

//    
function requestVisaSupport() {
    //  
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (!isLoggedIn) {
        if (confirm('  .   ?')) {
            localStorage.setItem('returnUrl', window.location.href);
            location.href = 'login.html';
        }
        return;
    }
    
    //     (  )
    if (confirm('   ?   .')) {
        location.href = 'inquiry.html?type=visa&package_id=' + (currentPackage?.packageId || '');
    }
}

//
function setupProductDescriptionToggle() {
    const toggleButton = document.querySelector('.btn-product');
    const descriptionArea = document.getElementById('product-description');

    if (toggleButton && descriptionArea) {
        //
        const getText = (key) => {
            const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
            const texts = globalLanguageTexts[currentLang] || globalLanguageTexts['ko'];
            return texts[key] || key;
        };

        toggleButton.addEventListener('click', function() {
            const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                //
                descriptionArea.style.display = 'none';
                toggleButton.setAttribute('aria-expanded', 'false');
                toggleButton.innerHTML = getText('expand_intro') + '<img src="../images/ico_arrow_down_black.svg" alt="" style="transform: rotate(0deg);">';
            } else {
                //
                descriptionArea.style.display = 'block';
                toggleButton.setAttribute('aria-expanded', 'true');
                toggleButton.innerHTML = getText('collapse_intro') + '<img src="../images/ico_arrow_down_black.svg" alt="" style="transform: rotate(180deg);">';
            }
        });
    }
}

// B2C 사용자용 Contact Agent 페이지로 이동
function goToContactAgent() {
    const urlParams = new URLSearchParams(window.location.search);
    const packageId = urlParams.get('id') || '';
    const selectedDateStr = selectedDate || '';
    const packageName = currentPackage?.packageName ||
                       document.querySelector('h3.text.fz20.fw600')?.textContent ||
                       document.querySelector('h1.text.fz20.fw600.lh28.black12')?.textContent || '';

    const params = new URLSearchParams({
        product_id: packageId,
        product_name: packageName,
        departure_date: selectedDateStr
    });

    window.location.href = `/user/contact-agent.php?${params.toString()}`;
}
window.goToContactAgent = goToContactAgent;