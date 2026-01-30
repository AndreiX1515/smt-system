// 홈페이지 동적 기능

let userBookings = [];
let currentTrip = null;
let upcomingTrip = null;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function getHomeSalesTarget() {
    // B2B/B2C 판별: accountType 기반
    // - accountType IN ('agent', 'admin_ph', 'admin_kr') → B2B
    // - accountType IN ('guest', 'guide', 'cs', '') → B2C
    try {
        const at = String(localStorage.getItem('accountType') || '').toLowerCase();
        if (at === 'agent' || at === 'admin_ph' || at === 'admin_kr') return 'B2B';
    } catch (_) {}
    return 'B2C';
}

function filterPackagesForHome(packages) {
    // 이중 가격 시스템: sales_target 필터링 제거
    // 모든 상품이 모든 사용자에게 노출되고, 가격만 다르게 표시됨
    return Array.isArray(packages) ? packages : [];
}

function __isPlaceholderImage(src) {
    const s = String(src || '').trim();
    if (!s) return false;
    // 기본 리소스(@img_*)는 "등록된 썸네일"이 없는 경우의 fallback이므로,
    // 요구사항(id 49): 실제 등록 이미지가 있으면 그것을 우선하고, placeholder는 최후순위로 밀어낸다.
    if (s.startsWith('@img_')) return true;
    if (s.includes('/images/@img_')) return true;
    if (s.includes('images/@img_')) return true;
    return false;
}

function __firstImageFromProductImagesField(v) {
    // product_images / productImages 가 배열(JSON) 형태로 내려오는 케이스 대응
    try {
        if (Array.isArray(v)) return v[0] || '';
        const raw = String(v || '').trim();
        if (!raw) return '';
        if (raw.startsWith('[') || raw.startsWith('{')) {
            const decoded = JSON.parse(raw);
            if (Array.isArray(decoded)) return decoded[0] || '';
            // {en:[], tl:[]} 또는 {en:'', tl:''} 등 다양한 포맷
            if (decoded && typeof decoded === 'object') {
                const lang = (typeof getCurrentLanguage === 'function')
                    ? getCurrentLanguage()
                    : (localStorage.getItem('selectedLanguage') || 'en');
                const pick = decoded[lang] ?? decoded.en ?? decoded.ko ?? null;
                if (Array.isArray(pick)) return pick[0] || '';
                if (typeof pick === 'string') return pick;
            }
        }
        return raw;
    } catch (_) {
        return '';
    }
}

function __pickPackageThumbnail(pkg) {
    // 우선순위:
    // 1) 서버 정규화(imageUrl/images)
    // 2) 스키마 편차(thumbnail_image/product_images 등)
    // 3) 레거시(packageImageUrl/packageImage)
    const candidates = [
        pkg?.imageUrl,
        (Array.isArray(pkg?.images) ? pkg.images[0] : null),
        pkg?.thumbnail_image,
        pkg?.thumbnailImage,
        pkg?.thumbnail,
        __firstImageFromProductImagesField(pkg?.product_images),
        __firstImageFromProductImagesField(pkg?.productImages),
        pkg?.mainImage,
        pkg?.packageImageUrl,
        pkg?.packageImage,
    ].map(v => (v == null ? '' : String(v).trim())).filter(Boolean);

    // placeholder는 마지막으로 밀어냄
    const nonPlaceholders = candidates.filter(c => !__isPlaceholderImage(c));
    return (nonPlaceholders[0] || candidates[0] || '');
}

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', function() {
    console.log('Current pathname:', window.location.pathname);
    
    // URL에서 언어 파라미터 확인 및 localStorage에 저장
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');
    // NOTE: ko는 미지원. en/tl만 허용.
    if (urlLang === 'en' || urlLang === 'tl') {
        console.log('Language from URL:', urlLang);
        localStorage.setItem('selectedLanguage', urlLang);
        // URL에서 언어 파라미터 제거 (깔끔한 URL 유지)
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('lang');
        window.history.replaceState({}, '', newUrl.toString());
    }

    // 홈 기본 언어는 영어(요구사항). 값이 없을 때만 세팅.
    if (!localStorage.getItem('selectedLanguage')) {
        localStorage.setItem('selectedLanguage', 'en');
    }

    // product-detail에서 잘못된 접근(잘못된 id / 권한 불일치)로 홈으로 리다이렉트되면
    // 사용자는 "상품 클릭했는데 새로고침 됨"처럼 느낀다. 원인을 메시지로 안내한다.
    const reason = urlParams.get('reason');
    if (reason) {
        const lang = localStorage.getItem('selectedLanguage') || 'en';
        const msgMap = {
            invalid_product: {
                en: 'This product is unavailable.',
                tl: 'Hindi available ang produktong ito.'
            },
            not_allowed: {
                en: 'This product is not available for your account type.',
                tl: 'Hindi available ang produktong ito para sa uri ng iyong account.'
            }
        };
        const msg = (msgMap[reason] && msgMap[reason][lang]) ? msgMap[reason][lang] : (msgMap[reason]?.en || 'This product is unavailable.');
        try { alert(msg); } catch (_) {}
        // URL 정리
        try {
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('reason');
            window.history.replaceState({}, '', newUrl.toString());
        } catch (_) {}
    }
    
    // More flexible path detection
    const isHomePage = window.location.pathname.includes('home.html') || 
                      window.location.pathname.endsWith('/') || 
                      window.location.pathname === '' ||
                      window.location.href.includes('home.html') ||
                      window.location.href.endsWith('www/');
    
    console.log('Is home page?', isHomePage);
    
    if (isHomePage) {
        console.log('Initializing home page...');
        initializeHomePage();
    }
});

// 홈페이지 초기화
async function initializeHomePage() {
    console.log('Starting home page initialization...');
    
    // 현재 언어 설정 확인 + 진입 시 즉시 번역 적용
    const currentLang = (typeof getCurrentLanguage === 'function')
        ? getCurrentLanguage()
        : (localStorage.getItem('selectedLanguage') || 'en');
    console.log('Home page initialization - Current language from localStorage:', currentLang);
    try { document.documentElement.lang = currentLang; } catch (_) {}
    try {
        if (typeof loadServerTexts === 'function') {
            await loadServerTexts(currentLang);
        }
        if (typeof updatePageLanguage === 'function') {
            updatePageLanguage(currentLang);
        }
    } catch (e) {
        console.warn('i18n init failed:', e);
    }
    
    // 로그인 상태 확인 및 UI 업데이트
    updateHeaderForLoginStatus();
    
    // 사용자 여행 상태 로드
    await loadUserTripStatus();
    
    // 패키지 섹션들 로드
    console.log('Loading package sections...');
    await loadPackageSections();

    // 홈 배너 로드(관리자 배너 연동)
    await updateBannerSlider();

    // 홈 팝업 로드(최고관리자 팝업 연동)
    await loadHomePopups();

    // 푸터 회사 정보 로드(관리자 회사정보 연동)
    await updateFooterCompanyInfo();
    
    // 알림 개수 업데이트
    updateNotificationCount();
    
    // 최종 설정
    finalizeHomePage();
}

// ===== 홈 팝업(관리자 등록 팝업) =====
async function loadHomePopups() {
    try {
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        };

        const currentLang = (typeof getCurrentLanguage === 'function')
            ? getCurrentLanguage()
            : (localStorage.getItem('selectedLanguage') || 'en');
        const res = await fetch(`backend/api/public-popups.php?lang=${encodeURIComponent(currentLang)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json().catch(() => ({}));
        const popups = (json && json.success && json.data && Array.isArray(json.data.popups)) ? json.data.popups : [];
        if (!popups.length) return;

        // 오늘 하루 보지 않기: localStorage key (popupId + date)
        const todayKey = new Date().toISOString().slice(0, 10);
        const visible = popups.filter(p => {
            const id = p.popupId ?? '';
            const k = `hidePopup:${id}:${todayKey}`;
            return localStorage.getItem(k) !== '1';
        });
        if (!visible.length) return;

        // 1개씩 순차 노출
        let idx = 0;
        const showOne = () => {
            const p = visible[idx];
            if (!p) return;

            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;';

            const card = document.createElement('div');
            card.className = 'popup-card';

            const imgWrap = document.createElement('div');
            imgWrap.className = 'popup-image';

            if (p.imageUrl) {
                const img = document.createElement('img');
                img.src = p.imageUrl;
                img.alt = String(p.title || '');
                img.loading = 'lazy';
                imgWrap.appendChild(img);
            } else {
                imgWrap.textContent = (p.title || 'Popup');
                imgWrap.style.padding = '28px 14px';
            }

            if (p.link) {
                imgWrap.style.cursor = 'pointer';
                imgWrap.addEventListener('click', () => {
                    const url = String(p.link || '').trim();
                    if (!url) return;
                    const target = (String(p.target || '').toLowerCase() === 'blank') ? '_blank' : '_self';
                    window.open(url, target);
                });
            }

            const footer = document.createElement('div');
            footer.className = 'popup-footer';

            const left = document.createElement('div');
            left.className = 'left';

            const iconWrap = document.createElement('div');
            iconWrap.className = 'popup-icon';
            const small = document.createElement('div');
            small.className = 'small';
            
            const circle = document.createElement('div');
            circle.className = 'circle';
            circle.innerHTML = `
                <svg width="12" height="10" viewBox="0 0 12 10" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block;">
                    <path d="M1 5L4.5 8.5L11 1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
            circle.style.display = 'flex';
            circle.style.alignItems = 'center';
            circle.style.justifyContent = 'center';

            iconWrap.appendChild(small);
            iconWrap.appendChild(circle);

            left.appendChild(iconWrap);

            const hideToday = document.createElement('button');
            hideToday.type = 'button';
            hideToday.className = 'popup-hide-btn';
            hideToday.textContent = 'Close for Today';
            left.appendChild(hideToday);

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'popup-close-btn';
            closeBtn.textContent = (idx < visible.length - 1) ? 'Close' : 'Close';

            footer.appendChild(left);
            footer.appendChild(closeBtn);

            card.appendChild(imgWrap);
            card.appendChild(footer);
            overlay.appendChild(card);
            document.body.appendChild(overlay);

            const close = () => {
                overlay.remove();
            };
            const closeAndNext = () => {
                close();
                idx++;
                if (idx < visible.length) showOne();
            };

            closeBtn.addEventListener('click', closeAndNext);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeAndNext();
            });
            hideToday.addEventListener('click', () => {
                const id = p.popupId ?? '';
                localStorage.setItem(`hidePopup:${id}:${todayKey}`, '1');
                closeAndNext();
            });
            card.tabIndex = -1;
        };

        showOne();
    } catch (e) {
        console.error('Failed to load home popups:', e);
    }
}

async function updateFooterCompanyInfo() {
    try {
        const companyNameBtn = document.getElementById('footerCompanyNameBtn');
        const ceoEl = document.getElementById('footerCeo');
        const addrEl = document.getElementById('footerAddress');
        const brnEl = document.getElementById('footerBusinessReg');
        const telecomEl = document.getElementById('footerTelecomReg');
        const csDomEl = document.getElementById('footerCsDomestic');
        const csIntlEl = document.getElementById('footerCsInternational');
        const emailDomEl = document.getElementById('footerEmailDomestic');
        const emailIntlEl = document.getElementById('footerEmailInternational');
        const faxEl = document.getElementById('footerFax');

        if (!companyNameBtn) return;

        const currentLang = localStorage.getItem('selectedLanguage') || 'en';
        const res = await fetch(`backend/api/company-info.php?type=footer&lang=${encodeURIComponent(currentLang)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json().catch(() => ({}));
        const info = json?.data?.companyInfo;
        if (!json?.success || !info) return;

        const companyName = (info.companyName || 'SMART TRAVEL').trim();
        companyNameBtn.textContent = companyName ? companyName : 'SMART TRAVEL';

        if (ceoEl) {
            const rep = (info.representative || '').trim();
            ceoEl.textContent = rep ? `CEO ${rep}` : 'CEO';
        }
        if (addrEl) {
            const addr = (info.address || '').trim();
            addrEl.textContent = addr ? `Address ${addr}` : 'Address';
        }
        if (brnEl) {
            const brn = (info.businessRegistration || '').trim();
            brnEl.textContent = brn ? `Business Registration Number ${brn}` : 'Business Registration Number';
        }
        if (telecomEl) {
            const trn = (info.telecomRegistration || '').trim();
            telecomEl.textContent = trn ? `Mail-Order Business Registration Number ${trn}` : 'Mail-Order Business Registration Number';
        }

        const hours = (info.operatingHours || '').trim();
        if (csDomEl) {
            const p = (info.phoneLocal || '').trim();
            csDomEl.textContent = `CS Domestic: ${p}${hours ? ` (${hours})` : ''}`;
        }
        if (csIntlEl) {
            const p = (info.phoneInternational || '').trim();
            csIntlEl.textContent = `    International: ${p}${hours ? ` (${hours})` : ''}`;
        }
        if (emailDomEl) {
            const e = (info.email || '').trim();
            emailDomEl.textContent = `E-mail: ${e}`;
        }
        if (emailIntlEl) {
            // 별도 international 이메일 컬럼이 없어서 동일 email을 표기하거나 빈칸 처리
            const e = (info.email || '').trim();
            emailIntlEl.textContent = `    International: ${e}`;
        }
        if (faxEl) {
            const f = (info.fax || '').trim();
            faxEl.textContent = f ? `FAX ${f}` : 'FAX';
        }
    } catch (e) {
        console.error('Failed to load footer company info:', e);
    }
}

// 헤더 로그인 상태 업데이트
function updateHeaderForLoginStatus() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const accountType = (localStorage.getItem('accountType') || '').toLowerCase();
    const currentLang = localStorage.getItem('selectedLanguage') || 'en';
    const mypageLink = document.getElementById('mypageLink');
    const bellLink = document.getElementById('bellLink');
    
    if (mypageLink) {
        if (isLoggedIn) {
            // 가이드 계정은 전용 마이페이지로 이동
            if (accountType === 'guide') {
                mypageLink.href = `user/guide-mypage.html?lang=${currentLang}`;
            } else {
                mypageLink.href = `user/mypage.html?lang=${currentLang}`;
            }
        } else {
            mypageLink.href = `user/login.html?lang=${currentLang}`;
        }
    }
    
    if (bellLink) {
        if (isLoggedIn) {
            bellLink.href = `user/alarm.html?lang=${currentLang}`;
        } else {
            bellLink.href = `user/login.html?lang=${currentLang}`;
        }
    }
}


// 사용자 여행 상태 로드
async function loadUserTripStatus() {
    // agent/admin 계정은 예약 여행정보 섹션을 표시하지 않음
    const accountType = (localStorage.getItem('accountType') || '').toLowerCase();
    if (accountType === 'agent' || accountType === 'admin_ph' || accountType === 'admin_kr') {
        hideAllTripSections();
        return;
    }

    // localStorage 기반 로그인 플래그가 비어있어도(세션 로그인만 존재) 홈 카드가 안 뜨는 문제가 있어
    // 우선 세션으로 로그인 여부를 보완한다.
    let isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    let userId = localStorage.getItem('userId');

    if (!isLoggedIn || !userId) {
        try {
            const sessionRes = await fetch('backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionJson = await sessionRes.json().catch(() => ({}));
            const sessionLoggedIn = !!(sessionJson && sessionJson.isLoggedIn);
            const sid = sessionJson?.user?.id || sessionJson?.user?.accountId || sessionJson?.userId;

            if (sessionLoggedIn) {
                isLoggedIn = true;
                localStorage.setItem('isLoggedIn', 'true');
                if (sid) {
                    userId = String(sid);
                    localStorage.setItem('userId', userId);
                }
            }
        } catch (_) {
            // ignore
        }
    }

    if (!isLoggedIn) {
        hideAllTripSections();
        return;
    }

    // userId가 localStorage에 없을 수 있어 세션으로 보완
    if (!userId) {
        try {
            const sessionRes = await fetch('backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionJson = await sessionRes.json().catch(() => ({}));
            const sid = sessionJson?.user?.id || sessionJson?.user?.accountId || sessionJson?.userId;
            if (sid) {
                userId = String(sid);
                localStorage.setItem('userId', userId);
            }
        } catch (_) {}
    }

    if (!userId) {
        hideAllTripSections();
        return;
    }
    
    try {
        // API에서 사용자 예약 정보 가져오기
        const response = await fetch(`backend/api/user_bookings.php?accountId=${encodeURIComponent(userId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success && result.data.bookings && result.data.bookings.length > 0) {
            userBookings = result.data.bookings.filter(booking => 
                booking.bookingStatus === 'confirmed' || booking.bookingStatus === 'completed'
            );
            
            analyzeTripStatus();
            updateTripSections();
        } else {
            hideAllTripSections();
        }
        
    } catch (error) {
        console.error('Failed to load trip status:', error);
        hideAllTripSections();
    }
}

// 여행 상태 분석
function analyzeTripStatus() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    currentTrip = null;
    upcomingTrip = null;
    
    const pickSooner = (a, b) => {
        if (!a) return b;
        if (!b) return a;
        return (a.daysUntil ?? 999) <= (b.daysUntil ?? 999) ? a : b;
    };

    for (const booking of userBookings) {
        if (!booking || !booking.departureDate) continue;
        const departureDate = new Date(booking.departureDate);
        if (isNaN(departureDate.getTime())) continue;
        departureDate.setHours(0, 0, 0, 0);

        const daysDiff = Math.floor((departureDate - today) / (1000 * 60 * 60 * 24));

        // 1) 당일 여행 예정(출발일=오늘)
        if (daysDiff === 0) {
            currentTrip = { ...booking, tripDay: 1 };
            continue;
        }

        // 2) 여행 중(오늘이 여행 기간 내) - duration_days 기반(없으면 6일 가정)
        const dur = Number(booking.duration || booking.durationDays || booking.duration_days || 6);
        if (daysDiff < 0 && Math.abs(daysDiff) < Math.max(1, dur)) {
            const tripDay = Math.abs(daysDiff) + 1;
            // 출발일=오늘이 없을 때만 여행중 카드로 사용
            if (!currentTrip) currentTrip = { ...booking, tripDay };
            continue;
        }

        // 3) 3일 이내 출발 예정
        if (daysDiff > 0 && daysDiff <= 3) {
            upcomingTrip = pickSooner(upcomingTrip, { ...booking, daysUntil: daysDiff });
        }
    }
}

// 여행 섹션 업데이트
function updateTripSections() {
    // Today's Trip 업데이트
    if (currentTrip) {
        showTodaysTrip(currentTrip);
    } else {
        hideTodaysTrip();
    }
    
    // Upcoming Trip 업데이트
    if (upcomingTrip) {
        showUpcomingTrip(upcomingTrip);
    } else {
        hideUpcomingTrip();
    }
}

// Today's Trip 표시
function showTodaysTrip(trip) {
    const todaysSection = document.getElementById('todaysTripSection');
    if (!todaysSection) return;
    
    todaysSection.style.display = 'block';
    
    const cardContainer = todaysSection.querySelector('.card-type2');
    if (cardContainer) {
        // 현재 언어 가져오기
        const currentLang = localStorage.getItem('selectedLanguage') || 'en';
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        
        // travelDay 텍스트 처리 (예: "여행 1일차" -> "여행 N일차")
        let travelDayText = '';
        if (currentLang === 'en') {
            travelDayText = `Day ${trip.tripDay}`;
        } else if (texts.travelDay) {
            travelDayText = texts.travelDay.replace('1', trip.tripDay);
        } else {
            travelDayText = `여행 ${trip.tripDay}일차`;
        }
        
        const langParam = `&lang=${encodeURIComponent(currentLang)}`;
        cardContainer.innerHTML = `
            <div class="label secondary">${travelDayText}</div>
            <div class="text fz16 fw500 lh26 black12 mt8 ellipsis2">${trip.productName || trip.productNameEn || trip.packageName || (texts.packageNameNotFound || '패키지명 없음')}</div>
            <div class="text fz14 fw500 lh22 black12 ico-time mt16">--:-- – --:--</div>
            <ul class="list-type1 mt12">
                <li><i class="num">1</i> ${(currentLang === 'en') ? 'Loading itinerary...' : (texts.schedule_loading || '일정 정보를 불러오는 중...')}</li>
            </ul>
            <div class="align gap8 mt16">
                <button class="btn line lg active" type="button" onclick="location.href='user/schedule.php?booking_id=${encodeURIComponent(trip.bookingId)}${langParam}'">${texts.detailedItinerary || texts.scheduleDetail || 'Detailed Itinerary'}</button>
                <button class="btn primary lg ico-location" type="button" onclick="location.href='user/guide-location.php?booking_id=${encodeURIComponent(trip.bookingId)}${langParam}'">${texts.guideLocation || 'Guide Location'}</button>
            </div>
        `;

        // 일정 미리보기(최대 4개) 채우기:
        // 1) 신규 상품등록 구조(package_schedules + package_attractions) 기반으로 "오늘 일정"을 시간순 정렬하여 노출
        // 2) 데이터가 없으면 package_itinerary 기반(레거시)으로 fallback
        fillTodaysTripItineraryPreview(cardContainer, trip.packageId, trip.tripDay);
    }
}

async function fillTodaysTripItineraryPreview(cardContainer, packageId, dayNumber) {
    try {
        if (!cardContainer || !packageId) return;
        const ul = cardContainer.querySelector('ul.list-type1');
        if (!ul) return;

        const currentLang = localStorage.getItem('selectedLanguage') || 'en';
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;

        const fmtHHMM = (t) => {
            const s = String(t || '').trim();
            if (!s) return '';
            // HH:MM or HH:MM:SS
            const m = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
            if (!m) return '';
            const hh = String(m[1]).padStart(2, '0');
            const mm = String(m[2]).padStart(2, '0');
            return `${hh}:${mm}`;
        };
        const timeToMin = (t) => {
            const s = String(t || '').trim();
            const m = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
            if (!m) return null;
            const hh = Number(m[1]);
            const mm = Number(m[2]);
            if (!Number.isFinite(hh) || !Number.isFinite(mm)) return null;
            return hh * 60 + mm;
        };
        const escape = (v) => escapeHtml(String(v ?? ''));

        // 1) package-detail.php (신규 구조: package_schedules + package_attractions)
        let attractions = [];
        let scheduleStart = '';
        let scheduleEnd = '';
        try {
            const res = await fetch(`backend/api/package-detail.php?id=${encodeURIComponent(packageId)}`, { credentials: 'same-origin' });
            const json = await res.json().catch(() => ({}));
            const schedules = Array.isArray(json?.data?.schedules) ? json.data.schedules : [];
            const dayKey = String(dayNumber || 1);
            const daySchedule = schedules.find(s => String(s?.day_number ?? s?.dayNumber ?? '') === dayKey)
                || schedules.find(s => String(s?.day_number ?? s?.dayNumber ?? '') === '1')
                || null;

            if (daySchedule) {
                scheduleStart = fmtHHMM(daySchedule.start_time || daySchedule.startTime || '');
                scheduleEnd = fmtHHMM(daySchedule.end_time || daySchedule.endTime || '');
                const raw = Array.isArray(daySchedule.attractions) ? daySchedule.attractions : [];
                attractions = raw
                    .map(a => ({
                        name: (a?.attraction_name || a?.name || '').trim(),
                        start: fmtHHMM(a?.start_time || a?.startTime || ''),
                        end: fmtHHMM(a?.end_time || a?.endTime || ''),
                        visitOrder: Number(a?.visit_order ?? a?.visitOrder ?? 0),
                        id: Number(a?.attraction_id ?? a?.id ?? 0)
                    }))
                    .filter(a => !!a.name);
            }
        } catch (_) {
            // ignore
        }

        // attractions 시간순 정렬 + 1~n
        if (attractions.length) {
            attractions.sort((a, b) => {
                const am = timeToMin(a.start);
                const bm = timeToMin(b.start);
                if (am !== null && bm !== null && am !== bm) return am - bm;
                if (am !== null && bm === null) return -1;
                if (am === null && bm !== null) return 1;
                if ((a.visitOrder || 0) !== (b.visitOrder || 0)) return (a.visitOrder || 0) - (b.visitOrder || 0);
                return (a.id || 0) - (b.id || 0);
            });

            // 오늘 일정 시작/종료시간: 첫 관광지 시작, 마지막 관광지 종료 (없으면 schedule start/end fallback)
            const first = attractions[0];
            const last = attractions[attractions.length - 1];
            const start = first?.start || scheduleStart || '--:--';
            const end = last?.end || scheduleEnd || '--:--';

            const timeEl = cardContainer.querySelector('.ico-time');
            if (timeEl) timeEl.textContent = `${start} – ${end}`;

            const finalItems = attractions.slice(0, 4).map(a => a.name);
            ul.innerHTML = finalItems.map((txt, idx) => `<li><i class="num">${idx + 1}</i> ${escape(txt)}</li>`).join('');
            return;
        }

        const items = [];
        // 2) fallback: package_itinerary 기반 (레거시)
        try {
            const res = await fetch(`backend/api/packages-simple.php?id=${encodeURIComponent(packageId)}`, { credentials: 'same-origin' });
            const data = await res.json().catch(() => null);
            const itinerary = Array.isArray(data?.itinerary) ? data.itinerary : null;
            if (Array.isArray(itinerary)) {
                const day = itinerary.find(d => String(d.dayNumber) === String(dayNumber)) || itinerary.find(d => String(d.dayNumber) === '1') || null;
                if (day) {
                    const act = (day.activities || '').trim();
                    if (act) {
                        act.split('\n').map(s => s.trim()).filter(Boolean).forEach(s => items.push(s));
                    }
                    if (!items.length && day.startTime && day.endTime) {
                        const t1 = fmtHHMM(day.startTime);
                        const t2 = fmtHHMM(day.endTime);
                        const timeEl = cardContainer.querySelector('.ico-time');
                        if (timeEl && (t1 || t2)) timeEl.textContent = `${t1 || '--:--'} – ${t2 || '--:--'}`;
                    }
                    if (!items.length && day.title) items.push(String(day.title));
                    if (!items.length && day.description) items.push(String(day.description));
                }
            }
        } catch (_) {}

        const fallback = currentLang === 'en'
            ? 'See details in Detailed Itinerary'
            : (texts.scheduleDetailFallback || '일정 상세에서 확인해주세요.');
        const finalItems = (items.length ? items : [fallback]).slice(0, 4);

        ul.innerHTML = finalItems.map((txt, idx) => `<li><i class="num">${idx + 1}</i> ${escape(String(txt))}</li>`).join('');
    } catch (e) {
        // ignore
    }
}

// Today's Trip 숨기기
function hideTodaysTrip() {
    const todaysSection = document.getElementById('todaysTripSection');
    if (todaysSection) {
        todaysSection.style.display = 'none';
    }
}

// Upcoming Trip 표시
function showUpcomingTrip(trip) {
    const upcomingSection = document.getElementById('upcomingTripSection');
    if (!upcomingSection) return;
    
    upcomingSection.style.display = 'block';
    
    const cardContainer = upcomingSection.querySelector('.card-type2');
    if (cardContainer) {
        const currentLang = localStorage.getItem('selectedLanguage') || 'en';
        const langParam = `&lang=${encodeURIComponent(currentLang)}`;
        cardContainer.innerHTML = `
            <div class="label secondary">D-${trip.daysUntil}</div>
            <div class="text fz16 fw500 lh26 black12 mt8 ellipsis2">${trip.productName || trip.productNameEn || trip.packageName || '패키지명 없음'}</div>
            <div class="text fz14 fw500 lh22 reded ico-location mt16">Meeting Location</div>
            <div class="text fz14 fw500 black12 lh22 ml24">
                ${trip.meetingLocation || '미정'}
            </div>
            <div class="text fz14 fw500 lh22 reded ico-time mt16">Meeting Time</div>
            <div class="text fz14 fw500 black12 lh22 ml24">
                ${trip.meetingTime || '미정'}
            </div>
            <div class="mt16">
                <button class="btn primary lg" type="button" onclick="location.href='user/reservation-detail.php?id=${encodeURIComponent(trip.bookingId)}${langParam}'">Booking Details</button>
                <button class="btn line lg active mt8" type="button" onclick="location.href='user/product-detail.php?id=${encodeURIComponent(trip.packageId || '')}&tab=itinerary&bookingId=${encodeURIComponent(trip.bookingId)}${langParam}'">Full Travel Itinerary</button>
            </div>
        `;
    }
}

// Upcoming Trip 숨기기
function hideUpcomingTrip() {
    const upcomingSection = document.getElementById('upcomingTripSection');
    if (upcomingSection) {
        upcomingSection.style.display = 'none';
    }
}

// 모든 여행 섹션 숨기기
function hideAllTripSections() {
    hideTodaysTrip();
    hideUpcomingTrip();
}

// 패키지 섹션들 로드
async function loadPackageSections() {
    console.log('Loading package sections (dynamic main categories)...');

    const host = document.getElementById('homeCategorySections');
    if (!host) {
        console.warn('homeCategorySections container not found; skipping category sections.');
        return;
    }

    host.innerHTML = '';

    // 관리자 카테고리(product_main_categories) 기준으로 홈 섹션 렌더
    let mainCategories = [];
    try {
        const res = await fetch('backend/api/categories.php', { credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        mainCategories = Array.isArray(json?.data?.mainCategories) ? json.data.mainCategories : [];
    } catch (e) {
        mainCategories = [];
    }

    // fallback: 최소 3개라도 유지
    if (!mainCategories.length) {
        mainCategories = [
            { code: 'season', name: 'By Season' },
            { code: 'region', name: 'By Region' },
            { code: 'theme', name: 'By Theme' }
        ];
    }

    const currentLang = localStorage.getItem('selectedLanguage') || 'en';

    // 섹션 생성
    const sectionEls = [];
    mainCategories.forEach((cat, idx) => {
        const code = String(cat?.code || '').trim();
        if (!code) return;

        const title = String(cat?.name || code);
        const mt = idx === 0 ? 'mt32' : 'mt20';

        const wrap = document.createElement('div');
        wrap.className = `px20 ${mt}`;
        wrap.innerHTML = `
            <div class="align both vm py16">
                <div class="text fz18 fw500 lh26 black12">${escapeHtml(title)}</div>
                <a class="btn-moreview" data-category="${escapeHtml(code)}" href="user/product-info.html?category=${encodeURIComponent(code)}&lang=${encodeURIComponent(currentLang)}">See all</a>
            </div>
            <ul class="list-type2" id="catPackages-${escapeHtml(code)}"></ul>
        `;
        host.appendChild(wrap);
        const ul = wrap.querySelector('ul.list-type2');
        if (ul) sectionEls.push({ code, ul });
    });

    // 섹션별 상품 로드(병렬)
    await Promise.all(sectionEls.map(({ code, ul }) => loadPackagesByCategory(code, ul)));
}

// 카테고리별 패키지 로드
async function loadPackagesByCategory(category, container) {
    console.log(`Starting to load ${category} packages for container:`, container ? container.id : 'null');
    
    if (!container) {
        console.error(`Container is null for category: ${category}`);
        return;
    }
    
    try {
        // api.js의 fetchPackages 함수 사용
        const packagesRaw = await fetchPackages(category, 4, { purchasableOnly: true });
        const packages = filterPackagesForHome(packagesRaw);

        if (packages && packages.length > 0) {
            console.log(`Loaded ${packages.length} packages for ${category}`);
            renderPackageSection(container, packages, category);
        } else {
            console.log(`No packages found for ${category}.`);
            // 더미 데이터 노출 금지: 실제 카테고리/상품과 불일치하므로 빈 상태로 처리
            container.innerHTML = '';
        }

    } catch (error) {
        console.error(`Error loading ${category} packages:`, error);
        // 더미 데이터 노출 금지
        container.innerHTML = '';
    }
}

// 패키지 섹션 렌더링
function renderPackageSection(container, packages, category) {
    if (!container) {
        console.error('Container is null for renderPackageSection');
        return;
    }
    
    console.log(`Rendering ${packages.length} packages for category ${category} in container:`, container.id);
    
    // B2B/B2C 사용자 구분하여 가격 표시
    const isB2BUser = getHomeSalesTarget() === 'B2B';

    const packagesHtml = packages.map((pkg, index) => {
        // API 응답과 fallback 데이터 구조 모두 지원
        const rawId = pkg.packageId || pkg.productId || pkg.id;
        const packageId = Number(rawId);
        const packageName = pkg.packageName || pkg.productName || pkg.name;

        // 이중 가격 시스템: B2B 사용자는 b2bPrice, B2C 사용자는 packagePrice 표시
        // 텍스트 가격이 있으면 텍스트 우선 사용
        let priceFormatted = '';

        if (isB2BUser) {
            // B2B 가격 (에이전트)
            if (pkg.b2bPriceDisplayText) {
                // 텍스트 가격이 있으면 그대로 사용
                priceFormatted = pkg.b2bPriceDisplayText;
            } else {
                const displayPrice = Number(pkg.b2bPrice || pkg.b2b_price || pkg.packagePrice || 0);
                priceFormatted = `₱${new Intl.NumberFormat('en-US').format(displayPrice)}~`;
            }
        } else {
            // B2C 가격 (일반 사용자)
            if (pkg.priceDisplayText) {
                // 텍스트 가격이 있으면 그대로 사용
                priceFormatted = pkg.priceDisplayText;
            } else {
                const displayPrice = Number(pkg.packagePrice || pkg.price || 0);
                priceFormatted = `₱${new Intl.NumberFormat('en-US').format(displayPrice)}~`;
            }
        }

        const packagePrice = priceFormatted;
        
        // "+" 기호가 포함된 경우 두 가격을 합산 (항공권 포함 상품)
        if (priceFormatted.includes('+')) {
            try {
                // "₱5,000 + ₱5,000" 형식에서 숫자 추출
                const priceParts = priceFormatted.split('+');
                let totalPrice = 0;
                
                priceParts.forEach(part => {
                    // 각 부분에서 숫자만 추출 (콤마 제거)
                    const numbers = part.replace(/[^\d.]/g, '');
                    if (numbers) {
                        totalPrice += parseFloat(numbers.replace(/,/g, ''));
                    }
                });
                
                // 합산된 가격을 포맷팅
                if (totalPrice > 0) {
                    priceFormatted = `₱${new Intl.NumberFormat('en-US').format(totalPrice)}~`;
                }
            } catch (error) {
                console.error('Error calculating total price:', error);
                // 오류 발생 시 원본 가격 사용
            }
        }
        
        const hasAvailableSeats = pkg.hasAvailableSeats || pkg.availableCount > 0 || pkg.isConfirmed;

        // 이미지 경로 처리 (요구사항 id 49: 등록된 썸네일 우선)
        let imageSrc = __pickPackageThumbnail(pkg);
        if (imageSrc && imageSrc.startsWith('http')) {
            // 이미 절대 URL인 경우 그대로 사용
            imageSrc = imageSrc;
        } else if (imageSrc && imageSrc.startsWith('/')) {
            // 절대 경로
            imageSrc = `${window.location.origin}${imageSrc}`;
        } else if (imageSrc && typeof imageSrc === 'string' && imageSrc.trim() !== '' && !imageSrc.includes('/') && !imageSrc.startsWith('@')) {
            // 파일명만 있는 경우(대부분 업로드 이미지)
            imageSrc = `${window.location.origin}/uploads/products/${imageSrc}`;
        } else if (imageSrc && !imageSrc.startsWith('http') && !imageSrc.startsWith('../')) {
            // @img_*.jpg 같은 기본 리소스는 /images/ 아래에 있음
            imageSrc = `${window.location.origin}/images/${imageSrc}`;
        } else if (imageSrc && imageSrc.startsWith('../')) {
            imageSrc = imageSrc.replace('../images/', `${window.location.origin}/images/`);
        }

        console.log(`Rendering package ${index + 1}: ${packageName} with image ${imageSrc}`);

        // 현재 선택된 언어 가져오기 (ko 미지원)
        const currentLang = (typeof getCurrentLanguage === 'function')
            ? getCurrentLanguage()
            : (localStorage.getItem('selectedLanguage') || 'en');
        console.log(`Package ${index + 1} - Current language from localStorage:`, currentLang);
        
        const onClick = (Number.isFinite(packageId) && packageId > 0)
            ? `location.href='user/product-detail.php?id=${packageId}&lang=${encodeURIComponent(currentLang)}'`
            : `alert('상품 정보를 준비 중입니다.');`;

        const imgHtml = imageSrc
            ? `<img src="${imageSrc}" alt="${pkg.imageAlt || packageName}" onerror="this.style.display='none'">`
            : `<div class="no-image" style="width:100%;aspect-ratio: 16/9;background:#f2f2f2;"></div>`;

        return `
            <li onclick="${onClick}" style="cursor: pointer;">
                <div class="card-type1">
                    ${imgHtml}
                    <div class="card-content">
                        <div class="info">${packageName}</div>
                        <p class="price">${priceFormatted}</p>
                        ${hasAvailableSeats ? `<div class="label secondary">${(typeof getText === 'function' ? getText('guaranteedDeparture', currentLang) : 'Guaranteed Departure')}</div>` : ''}
                    </div>
                </div>
            </li>
        `;
    }).join('');
    
    console.log(`Generated HTML length: ${packagesHtml.length} characters`);
    container.innerHTML = packagesHtml;
    console.log(`Container innerHTML updated for ${category}`);
}

// 알림 개수 업데이트
async function updateNotificationCount() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const notificationBadge = document.querySelector('.btn-bell .num');
    
    if (!isLoggedIn || !notificationBadge) {
        if (notificationBadge) {
            notificationBadge.style.display = 'none';
        }
        return;
    }
    
    try {
        // 실제로는 알림 API를 호출해야 하지만, 임시로 처리
        const unreadCount = await getUnreadNotificationCount();
        
        if (unreadCount > 0) {
            notificationBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            notificationBadge.style.display = 'block';
        } else {
            notificationBadge.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Failed to update notification count:', error);
        notificationBadge.style.display = 'none';
    }
}

// 미읽음 알림 개수 조회
async function getUnreadNotificationCount() {
    let userId = localStorage.getItem('userId');
    if (!userId) {
        // 세션 로그인만 있는 경우 userId 보완
        try {
            const sessionRes = await fetch('backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionJson = await sessionRes.json().catch(() => ({}));
            const sid = sessionJson?.user?.id || sessionJson?.user?.accountId || sessionJson?.userId;
            if (sid) {
                userId = String(sid);
                localStorage.setItem('userId', userId);
                localStorage.setItem('isLoggedIn', 'true');
            }
        } catch (_) {}
    }
    if (!userId) return 0;
    
    try {
        const response = await fetch('backend/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_unread_count',
                // backend/api/notifications.php는 userId 키를 사용
                userId: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            return result?.data?.unreadCount ?? result?.unreadCount ?? 0;
        } else {
            console.error('Failed to get notification count:', result.message);
            return 0;
        }
        
    } catch (error) {
        console.error('Get notification count error:', error);
        return 0;
    }
}

// 배너 슬라이더 동적 업데이트 (관리자 배너 연동)
async function updateBannerSlider() {
    const bannerSlider = document.getElementById('homeBannerSlider') || document.querySelector('.slider');
    if (!bannerSlider) return;

    try {
        const res = await fetch('backend/api/banners.php', { credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        const banners = json?.data?.banners || [];

        // imageUrl 있는 것만 사용
        const usable = banners.filter(b => b && b.imageUrl);
        if (!usable.length) {
            // fallback: 기존 이미지가 없을 때 기본 배너 1개라도 넣기
            bannerSlider.innerHTML = `<div><img src="images/@img_banner1.jpg" alt="banner"></div>`;
            window.dispatchEvent(new CustomEvent('home:banners:ready'));
            return;
        }

        const makeAbs = (u) => {
            if (!u) return '';
            if (u.startsWith('http://') || u.startsWith('https://')) return u;
            if (u.startsWith('/')) return `${location.origin}${u}`;
            return u;
        };

        bannerSlider.innerHTML = usable.map((b) => {
            const img = makeAbs(String(b.imageUrl || ''));
            const link = String(b.url || '').trim();
            if (link) {
                return `<div><a href="${link}" target="_blank" rel="noopener"><img src="${img}" alt="banner"></a></div>`;
            }
            return `<div><img src="${img}" alt="banner"></div>`;
        }).join('');

        // slider.js가 이벤트를 받아 slick 초기화하도록 함
        window.dispatchEvent(new CustomEvent('home:banners:ready'));
    } catch (e) {
        console.error('Failed to load banners:', e);
        bannerSlider.innerHTML = `<div><img src="images/@img_banner1.jpg" alt="banner"></div>`;
        window.dispatchEvent(new CustomEvent('home:banners:ready'));
    }
}

// See all 링크는 이미 HTML에서 직접 설정됨

// 검색 기능 (향후 확장용)
function setupSearch() {
    const searchInput = document.querySelector('.search-input');
    const searchBtn = document.querySelector('.search-btn');
    
    if (searchInput && searchBtn) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            if (query) {
                location.href = `user/product-info.html?search=${encodeURIComponent(query)}`;
            }
        });
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });
    }
}

// 초기화 완료 후 추가 설정
function finalizeHomePage() {
    setupSearch();
    updateSeeAllLinks();
}

// See all 링크에 언어 파라미터 추가
function updateSeeAllLinks() {
    const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
    console.log('updateSeeAllLinks - Current language from localStorage:', currentLang);
    
    // 동적 카테고리 "See all" 링크 업데이트
    document.querySelectorAll('a.btn-moreview[data-category]').forEach((a) => {
        const cat = a.getAttribute('data-category') || '';
        if (!cat) return;
        a.href = `user/product-info.html?category=${encodeURIComponent(cat)}&lang=${encodeURIComponent(currentLang)}`;
    });
    
    // About us 섹션 링크들 업데이트
    const companyIntroLink = document.getElementById('companyIntroLink');
    const partnershipLink = document.getElementById('partnershipLink');
    
    if (companyIntroLink) {
        companyIntroLink.href = `user/company-intro.html?lang=${currentLang}`;
    }
    
    if (partnershipLink) {
        partnershipLink.href = `user/partnership-information.php?lang=${currentLang}`;
    }
    
    // 푸터 링크들 업데이트
    const privacyPolicyLink = document.getElementById('privacyPolicyLink');
    const termsOfUseLink = document.getElementById('termsOfUseLink');
    const footerPartnershipLink = document.getElementById('footerPartnershipLink');
    
    if (privacyPolicyLink) {
        privacyPolicyLink.href = `user/terms.php?category=privacy_collection&lang=${currentLang}`;
    }
    
    if (termsOfUseLink) {
        termsOfUseLink.href = `user/terms.php?category=terms&lang=${currentLang}`;
    }
    
    if (footerPartnershipLink) {
        footerPartnershipLink.href = `user/partnership-information.php?lang=${currentLang}`;
    }
}

// 페이지 가시성 변경 시 데이터 새로고침
document.addEventListener('visibilitychange', function () {
    const isHomePage = window.location.pathname.includes('home.html') ||
        window.location.pathname.endsWith('/') ||
        window.location.pathname === '' ||
        window.location.href.includes('home.html') ||
        window.location.href.endsWith('www/');

    if (!document.hidden && isHomePage) {
        // 페이지가 다시 보일 때 여행 상태와 알림 업데이트
        setTimeout(() => {
            loadUserTripStatus();
            updateNotificationCount();
        }, 500);
    }
});