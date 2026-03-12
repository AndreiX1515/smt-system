document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId = urlParams.get('booking_id') || urlParams.get('bookingId');
    let scheduleData = null;
    let guideData = null;
    let currentDate = new Date();
    // Kakao map only
    let kakaoMap = null;
    let kakaoMarkers = [];
    let kakaoOverlays = [];
    let kakaoGeocoder = null;
    let kakaoPlaces = null;
    const __geoCache = new Map(); // key: query -> {lat,lng,address}

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function stripHtml(html) {
        const s = String(html || '').trim();
        if (!s) return '';
        const txt = s.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        return txt;
    }

    function t(key, fallback) {
        try {
            if (typeof window.getI18nText === 'function') return String(window.getI18nText(key) || fallback || key);
        } catch (_) {}
        return String(fallback || key);
    }

    function normalizeAssetUrl(path) {
        const s = String(path || '').trim();
        if (!s) return '';
        if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('data:')) return s;
        if (s.startsWith('//')) return window.location.protocol + s;
        if (s.startsWith('/')) return window.location.origin + s;
        if (s.startsWith('uploads/')) return window.location.origin + '/' + s;
        // If DB stored only filename (e.g., tpl_xxx.jpeg), default to uploads/products/
        if (!s.includes('/') && /\.(png|jpe?g|gif|webp|svg)$/i.test(s)) return window.location.origin + '/uploads/products/' + s;
        return s; // relative
    }

    function openProfileModal() {
        const layer = document.querySelector('.layer');
        const modal = document.querySelector('.profile-modal');
        if (!layer || !modal) return;

        // 이미지/소개는 JS에서 최신 guideData로 보강 (이름/전화는 서버 렌더링이지만 같이 갱신)
        const img = modal.querySelector('img.img-profile');
        const aboutP = modal.querySelector('.form-wrap p');
        const texts = Array.from(modal.querySelectorAll('.form-wrap .input-wrap .text'));

        if (img) {
            const imgSrc = normalizeAssetUrl(guideData?.profileImage) || '../images/@img_profile.png';
            img.src = imgSrc;
            img.alt = guideData?.guideName || 'Guide';
        }
        if (texts && texts.length) {
            if (texts[0]) texts[0].textContent = guideData?.guideName || texts[0].textContent || '';
            if (texts[1]) texts[1].textContent = guideData?.phone || texts[1].textContent || '';
        }
        if (aboutP) {
            aboutP.textContent = (guideData?.about || '').trim() || aboutP.textContent || '';
        }

        layer.classList.add('active');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeProfileModal() {
        const layer = document.querySelector('.layer');
        const modal = document.querySelector('.profile-modal');
        if (!layer || !modal) return;
        layer.classList.remove('active');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function setupProfileModal() {
        const layer = document.querySelector('.layer');
        const modal = document.querySelector('.profile-modal');
        if (!layer || !modal) return;

        const closeBtn = document.querySelector('.btn-close-modal');
        closeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeProfileModal();
        });

        layer.addEventListener('click', () => closeProfileModal());
        modal.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeProfileModal();
        });

        // 초기 서버 렌더 상태의 버튼에도 바인딩
        const btn = document.querySelector('#profile .btn-profile-view');
        btn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openProfileModal();
        });
    }

    function toMinutes(timeStr) {
        const s = String(timeStr || '').trim();
        if (!s) return null;
        const m = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (!m) return null;
        const hh = parseInt(m[1], 10);
        const mm = parseInt(m[2], 10);
        if (!Number.isFinite(hh) || !Number.isFinite(mm)) return null;
        return hh * 60 + mm;
    }

    function formatTimeHHMM(timeStr) {
        const s = String(timeStr || '').trim();
        if (!s) return '';
        const m = s.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return '';
        return `${String(m[1]).padStart(2, '0')}:${m[2]}`;
    }

    function formatDurationFromTimes(startStr, endStr) {
        const a = toMinutes(startStr);
        const b = toMinutes(endStr);
        if (a == null || b == null) return '';
        const diff = Math.max(0, b - a);
        const h = Math.floor(diff / 60);
        const m = diff % 60;
        if (h <= 0) return `${m}m`;
        if (m === 0) return `${h}h`;
        return `${h}h ${m}m`;
    }

    function getSortedAttractions(daySchedule) {
        const atts = Array.isArray(daySchedule?.attractions) ? daySchedule.attractions : [];
        const withIdx = atts.map((a, i) => ({ ...(a || {}), __idx: i }));
        withIdx.sort((a, b) => {
            const ta = toMinutes(a.start_time);
            const tb = toMinutes(b.start_time);
            if (ta != null && tb != null && ta !== tb) return ta - tb;
            if (ta != null && tb == null) return -1;
            if (ta == null && tb != null) return 1;
            const oa = Number(a.visit_order || 0);
            const ob = Number(b.visit_order || 0);
            if (oa !== ob) return oa - ob;
            return (a.__idx || 0) - (b.__idx || 0);
        });
        return withIdx;
    }

    // NOTE:
    // 과거에 "해외 좌표로 bounds 이동 시 하얗게 보인다" 이슈가 있어 한국 좌표만 남기던 로직이 있었는데,
    // 현재 요구사항은 "해당 일자의 모든 관광지"가 지도에 포함되어야 하므로 좌표 국가 제한을 두지 않습니다.

    function ensureMap(lat, lng, addressText) {
        const mapEl = document.getElementById('map');
        if (!mapEl) return;

        const clat = Number(lat);
        const clng = Number(lng);
        if (!Number.isFinite(clat) || !Number.isFinite(clng)) return;

        // Kakao SDK 준비 전이면 재시도
        if (!(typeof kakao !== 'undefined' && kakao.maps)) {
            if (ensureMap.__retry == null) ensureMap.__retry = 0;
            if (ensureMap.__retry < 30) {
                ensureMap.__retry++;
                setTimeout(() => ensureMap(lat, lng, addressText), 200);
            }
            return;
        }
        ensureMap.__retry = 0;

        const center = new kakao.maps.LatLng(clat, clng);
        if (!kakaoMap) {
            kakaoMap = new kakao.maps.Map(mapEl, { center, level: 4 });
            kakaoGeocoder = kakao.maps.services ? new kakao.maps.services.Geocoder() : null;
        } else {
            kakaoMap.setCenter(center);
        }

        kakaoMarkers.forEach(m => { try { m.setMap(null); } catch (_) {} });
        kakaoOverlays.forEach(o => { try { o.setMap(null); } catch (_) {} });
        kakaoMarkers = [];
        kakaoOverlays = [];

        const marker = new kakao.maps.Marker({ position: center });
        marker.setMap(kakaoMap);
        kakaoMarkers.push(marker);
    }

    async function geocodeAddress(q) {
        const key = String(q || '').trim();
        if (!key) return null;
        if (__geoCache.has(key)) return __geoCache.get(key);
        // Kakao geocoder only (외부 지오코딩 API 금지)
        try {
            if (!(typeof kakao !== 'undefined' && kakao.maps && kakao.maps.services)) return null;
            if (!kakaoGeocoder) kakaoGeocoder = new kakao.maps.services.Geocoder();
            if (!kakaoPlaces && kakao.maps.services.Places) kakaoPlaces = new kakao.maps.services.Places();
            const geo = await new Promise((resolve) => {
                kakaoGeocoder.addressSearch(key, (result, status) => {
                    if (status !== kakao.maps.services.Status.OK || !result || !result[0]) return resolve(null);
                    const r0 = result[0];
                    const lat = Number(r0.y);
                    const lng = Number(r0.x);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return resolve(null);
                    resolve({ lat, lng, address: r0.address_name || key });
                });
            });
            if (geo) {
                __geoCache.set(key, geo);
                return geo;
            }

            // addressSearch가 실패하면 POI/키워드 검색 fallback (예: "인천공항", "동탄역" 등)
            if (kakaoPlaces && typeof kakaoPlaces.keywordSearch === 'function') {
                const kw = await new Promise((resolve) => {
                    kakaoPlaces.keywordSearch(key, (result, status) => {
                        if (status !== kakao.maps.services.Status.OK || !result || !result[0]) return resolve(null);
                        const r0 = result[0];
                        const lat = Number(r0.y);
                        const lng = Number(r0.x);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return resolve(null);
                        resolve({ lat, lng, address: r0.address_name || r0.road_address_name || r0.place_name || key });
                    });
                });
                if (kw) {
                    __geoCache.set(key, kw);
                    return kw;
                }
            }

            return null;
        } catch (_) {
            return null;
        }
    }

    async function ensureItineraryMap(daySchedule) {
        const mapEl = document.getElementById('map');
        if (!mapEl) return;

        // Kakao SDK 준비 전이면 재시도 (다른 지도 fallback 금지)
        if (!(typeof kakao !== 'undefined' && kakao.maps)) {
            if (ensureItineraryMap.__retry == null) ensureItineraryMap.__retry = 0;
            if (ensureItineraryMap.__retry < 30) {
                ensureItineraryMap.__retry++;
                setTimeout(() => ensureItineraryMap(daySchedule), 200);
            }
            return;
        }
        ensureItineraryMap.__retry = 0;

        const attractions = getSortedAttractions(daySchedule);

        // init map if needed
        if (!kakaoMap) {
            const lat = Number(guideData?.currentLatitude) || 37.5665;
            const lng = Number(guideData?.currentLongitude) || 126.9780;
            kakaoMap = new kakao.maps.Map(mapEl, { center: new kakao.maps.LatLng(lat, lng), level: 5 });
            kakaoGeocoder = kakao.maps.services ? new kakao.maps.services.Geocoder() : null;
        }

        kakaoMarkers.forEach(m => { try { m.setMap(null); } catch (_) {} });
        kakaoOverlays.forEach(o => { try { o.setMap(null); } catch (_) {} });
        kakaoMarkers = [];
        kakaoOverlays = [];

        // attractions geocode
        const points = [];
        for (let i = 0; i < attractions.length; i++) {
            const a = attractions[i] || {};
            // 주소 우선 → 실패 시 명칭으로 fallback
            const addrQ = (a.address || '').trim();
            const nameQ = (a.name || '').trim();
            const geo = (await geocodeAddress(addrQ)) || (await geocodeAddress(nameQ));
            if (!geo) continue;
            points.push({ idx: i + 1, name: a.name || addrQ || nameQ, address: geo.address, lat: geo.lat, lng: geo.lng });
        }

        // 마커가 없으면 가이드 위치로 fallback
        if (!points.length) {
            ensureMap(guideData?.currentLatitude, guideData?.currentLongitude, guideData?.location || '');
            return;
        }

        // numbered markers
        const bounds = new kakao.maps.LatLngBounds();
        points.forEach((p) => {
            const pos = new kakao.maps.LatLng(p.lat, p.lng);
            bounds.extend(pos);

            const marker = new kakao.maps.Marker({ position: pos });
            marker.setMap(kakaoMap);
            kakaoMarkers.push(marker);

            const content = `<div style="position:relative;transform:translate(-50%,-100%);">
                <div style="width:28px;height:28px;border-radius:14px;background:#e53935;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">${p.idx}</div>
            </div>`;
            const overlay = new kakao.maps.CustomOverlay({ position: pos, content, yAnchor: 1 });
            overlay.setMap(kakaoMap);
            kakaoOverlays.push(overlay);
        });
        // IMPORTANT: setBounds가 호출돼도 "한 지점에 줌인"처럼 보이는 경우가 있어
        // relayout 이후 한 tick 지연해서 bounds를 적용합니다(모바일/리사이즈 타이밍 이슈 대응).
        try { kakaoMap.relayout(); } catch (_) {}
        setTimeout(() => {
            // padding을 넉넉히 주어 마커가 가장자리에 딱 붙지 않도록(=조금 더 줌아웃 효과)
            try { kakaoMap.setBounds(bounds, 80, 80, 80, 80); } catch (_) {
                try { kakaoMap.setBounds(bounds); } catch (_) {}
            }

            // setBounds 결과가 타이트하면 레벨을 한 단계 더 줌아웃(요구: "지도 확대를 더 줄여")
            setTimeout(() => {
                try {
                    const lv = kakaoMap.getLevel();
                    // Kakao: level 값이 클수록 더 줌아웃
                    const next = Math.min((Number(lv) || 0) + 1, 14);
                    if (next > 0) kakaoMap.setLevel(next);
                } catch (_) {}
            }, 0);
        }, 0);
    }

    // 전역 변수가 로드될 때까지 기다리는 함수
    function waitForGlobalData() {
        return new Promise((resolve) => {
            const checkData = () => {
                if (window.scheduleData && window.guideData) {
                    console.log('Global data found:', window.scheduleData, window.guideData);
                    resolve(true);
                } else {
                    console.log('Waiting for global data...');
                    setTimeout(checkData, 100);
                }
            };
            checkData();
        });
    }

    async function loadScheduleData() {
        console.log('loadScheduleData called');
        console.log('window.scheduleData:', window.scheduleData);
        console.log('window.guideData:', window.guideData);
        console.log('bookingId from URL:', bookingId);
        
        // 전역 변수가 로드될 때까지 기다림
        await waitForGlobalData();
        
        // PHP에서 로드된 데이터가 있으면 사용
        if (window.scheduleData && window.guideData) {
            console.log('Using PHP-loaded schedule data');
            scheduleData = window.scheduleData;
            guideData = window.guideData;
            updateGuideInfo();
            // 지도는 선택된 날짜 기준으로 itinerary marker를 표시(없으면 guide 위치)
            updateCalendar();
            updateNotificationBadge();
            return;
        }

        // PHP에서 데이터가 없으면 API 호출
        if (!bookingId) {
            console.warn('No booking ID found. Using fallback data.');
            scheduleData = getFallbackScheduleData();
            guideData = getDefaultGuideData();
            updateGuideInfo();
            updateCalendar();
            return;
        }

        try {
            // 새로운 API 사용
            const result = await api.getTravelScheduleDetail(bookingId);
            
            if (result.success) {
                scheduleData = result.data;
                guideData = result.data.guide || getDefaultGuideData();
                updateGuideInfo();
                updateCalendar();
                updateNotificationBadge();
            } else {
                console.error('Failed to load schedule:', result.message);
                // Fallback 데이터 사용
                scheduleData = getFallbackScheduleData();
                guideData = getDefaultGuideData();
                updateGuideInfo();
                updateCalendar();
            }
        } catch (error) {
            console.error('Error loading schedule:', error);
            // Fallback 데이터 사용
            scheduleData = getFallbackScheduleData();
            guideData = getDefaultGuideData();
            updateGuideInfo();
            updateCalendar();
        }
    }

    function updateGuideInfo() {
        if (!guideData) return;

        const profileSection = document.getElementById('profile');
        if (profileSection) {
            profileSection.innerHTML = `
                <div>
                    <div class="align gap12">
                        <img class="profile" src="${guideData.profileImage || '../images/@img_profile.svg'}" alt="">
                        <div>
                            <div class="text fz12 fw500 lh16 grayb0">Guide</div>
                            <div class="text fz14 fw600 lh22 black12">${guideData.guideName}</div>
                            <div class="text fz13 fw400 lh16 black12">${guideData.phone}</div>
                        </div>
                    </div>
                </div>
                <button class="btn-profile-view" type="button" aria-label="View guide profile">
                    <img src="../images/ico_go_round.svg" alt="">
                </button>
            `;
            const btn = profileSection.querySelector('.btn-profile-view');
            btn?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openProfileModal();
            });
        }
    }

    function updateCalendar() {
        if (!scheduleData || !Array.isArray(scheduleData)) return;

        const calendarContainer = document.getElementById('calendarContainer');
        if (!calendarContainer) return;
        
        let calendarHtml = '';
        let defaultSelectedDate = null;
        
        // 오늘 날짜와 비교하여 가장 가까운 미래 날짜 찾기
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let closestFutureDate = null;
        let minDaysDiff = Infinity;
        
        scheduleData.forEach((schedule, index) => {
            const date = new Date(schedule.date);
            date.setHours(0, 0, 0, 0);
            const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24));
            
            // 오늘 날짜이거나 미래 날짜 중에서 가장 가까운 날짜 찾기
            if (daysDiff >= 0 && daysDiff < minDaysDiff) {
                minDaysDiff = daysDiff;
                closestFutureDate = schedule.date;
            }
        });
        
        // 가장 가까운 미래 날짜를 기본 선택 날짜로 설정
        defaultSelectedDate = closestFutureDate || scheduleData[0]?.date;
        
        scheduleData.forEach((schedule, index) => {
            const date = new Date(schedule.date);
            const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'short' });
            const dayOfMonth = date.getDate();
            const isSelected = schedule.date === defaultSelectedDate;
            const activeClass = isSelected ? 'active' : '';
            
            calendarHtml += `
                <li class="${activeClass}" data-date="${schedule.date}">
                    <div class="day">${dayOfWeek}</div>
                    <div class="date">${dayOfMonth}</div>
                </li>
            `;
        });

        calendarContainer.innerHTML = calendarHtml;
        
        // 기본 선택된 날짜의 일정 표시
        if (defaultSelectedDate) {
            displayScheduleForDate(defaultSelectedDate);
        }
        
        calendarContainer.addEventListener('click', function(e) {
            const li = e.target.closest('li');
            if (li) {
                document.querySelectorAll('#calendarContainer li').forEach(item => {
                    item.classList.remove('active');
                });
                li.classList.add('active');
                
                const selectedDate = li.dataset.date;
                displayScheduleForDate(selectedDate);
            }
        });
    }

    function displayScheduleForDate(dateStr) {
        if (!scheduleData || !Array.isArray(scheduleData)) return;

        const daySchedule = scheduleData.find(day => day.date === dateStr);
        const scheduleContainer = document.getElementById('scheduleList');
        
        if (!scheduleContainer) {
            console.error('Schedule container not found');
            return;
        }

        const hasAttractions = Array.isArray(daySchedule?.attractions) && daySchedule.attractions.length > 0;
        const hasActivities = Array.isArray(daySchedule?.activities) && daySchedule.activities.length > 0;
        if (!daySchedule || (!hasAttractions && !hasActivities)) {
            scheduleContainer.innerHTML = `
                <div class="align center mt40">
                    <p class="text fz14 fw400 lh22 gray96">${escapeHtml(t('no_schedule_today', 'No itinerary scheduled for this date.'))}</p>
                </div>
            `;
            return;
        }

        let cardsHtml = '';
        if (hasAttractions) {
            const atts = getSortedAttractions(daySchedule);
            cardsHtml = atts.map((a, index) => {
                const startTime = a.start_time || daySchedule.start_time || '';
                const endTime = a.end_time || daySchedule.end_time || '';
                const dur = formatDurationFromTimes(startTime, endTime);
                const img = String(a.image || '').trim()
                    ? escapeHtml(normalizeAssetUrl(a.image))
                    : '../images/@img_card1.jpg';
                const desc = stripHtml(a.description || '') ||
                    stripHtml(daySchedule.airport_description || '') ||
                    stripHtml(daySchedule.description || '') ||
                    '';
                return `
                    <li class="card-type5">
                        <div class="img-wrap">
                            <img src="${img}" alt="">
                            <i class="num">${index + 1}</i>
                        </div>
                        <div class="align both vm mt8">
                            <div>${escapeHtml(formatTimeHHMM(startTime))}${startTime && endTime ? ' - ' : ''}${escapeHtml(formatTimeHHMM(endTime))}</div>
                            <div class="label disabled-secondary ico-time">${escapeHtml(dur || '')}</div>
                        </div>
                        <div class="text fz14 fw600 lh22 black12 mt4">${escapeHtml(a.name || '')}</div>
                        <p class="text fz13 fw400 lh19 gray96 mt4">
                            ${escapeHtml(desc)}
                        </p>
                    </li>
                `;
            }).join('');
        } else {
            // 레거시 activities 기반
            cardsHtml = daySchedule.activities.map((activity, index) => {
                const startTime = daySchedule.start_time || '09:00:00';
                const endTime = daySchedule.end_time || '11:00:00';
                const dur = formatDurationFromTimes(startTime, endTime);
                const desc = stripHtml(daySchedule.airport_description || '') || stripHtml(daySchedule.description || '') || '';
                return `
                    <li class="card-type5">
                        <div class="img-wrap">
                            <img src="../images/@img_card1.jpg" alt="">
                            <i class="num">${index + 1}</i>
                        </div>
                        <div class="align both vm mt8">
                            <div>${escapeHtml(formatTimeHHMM(startTime))} - ${escapeHtml(formatTimeHHMM(endTime))}</div>
                            <div class="label disabled-secondary ico-time">${escapeHtml(dur || '')}</div>
                        </div>
                        <div class="text fz14 fw600 lh22 black12 mt4">${escapeHtml(activity)}</div>
                        <p class="text fz13 fw400 lh19 gray96 mt4">
                            ${escapeHtml(desc)}
                        </p>
                    </li>
                `;
            }).join('');
        }

        scheduleContainer.innerHTML = cardsHtml;
        
        // Timeline도 업데이트
        updateTimelineForDate(daySchedule);
        // 지도 마커 업데이트(관광지 → 없으면 가이드 위치)
        ensureItineraryMap(daySchedule);
    }
    
    function updateTimelineForDate(daySchedule) {
        const timelineContainer = document.getElementById('timelineList');
        if (!timelineContainer) return;
        
        if (!daySchedule) {
            timelineContainer.innerHTML = `
                <div class="align center mt40">
                    <p class="text fz14 fw400 lh22 gray96">${escapeHtml(t('no_timeline_today', 'No timeline for this date.'))}</p>
                </div>
            `;
            return;
        }
        
        // 주요 위치 정보가 있으면 타임라인 생성
        const timelineItems = [];

        // 1) 관광지(attractions)가 있으면 타임라인의 주 데이터로 사용
        const hasAtts = Array.isArray(daySchedule?.attractions) && daySchedule.attractions.length > 0;
        if (hasAtts) {
            const atts = getSortedAttractions(daySchedule);
            atts.forEach((a, idx) => {
                const time = formatTimeHHMM(a.start_time || '');
                const addr = String(a.address || '').trim();
                const desc = stripHtml(a.description || '') || stripHtml(daySchedule.description || '') || '';
                timelineItems.push(`
                    <li>
                        <div class="align gap14">
                            <i class="num">${idx + 1}</i>
                            <div>
                                <div class="text fz18 fw500 lh26 reded">${escapeHtml(time || '')}</div>
                                <div class="text fz14 fw600 lh22 black12 mt6">${escapeHtml(a.name || '')}</div>
                                <div class="text fz13 fw400 lh19 grayb0 mt6">${escapeHtml(addr)}</div>
                                <div class="text fz13 fw400 lh19 black12">${escapeHtml(desc)}</div>
                            </div>
                        </div>
                    </li>
                `);

                // 구간 소요(시간차 기반): 다음 관광지 시작까지
                if (idx < atts.length - 1) {
                    const curEnd = a.end_time || '';
                    const nextStart = atts[idx + 1]?.start_time || '';
                    const seg = formatDurationFromTimes(curEnd, nextStart);
                    if (seg) {
                        timelineItems.push(`
                            <div class="text fz14 fw600 lh22 green1b align gap14 py12 mt16">
                                <i class="time"></i>
                                About ${escapeHtml(seg)}
                            </div>
                        `);
                    }
                }
            });

            // SMT 수정(요구사항):
            // - Timeline View에서는 관광지 정보만 노출(숙소/공항 등 제외)
            // - 관광지가 있는 경우 공항/숙소 블록을 추가로 붙이지 않아 중복/개수 불일치를 방지
            if (timelineItems.length > 0) {
                timelineContainer.innerHTML = timelineItems.join('');
            } else {
                timelineContainer.innerHTML = `
                    <div class="align center mt40">
                        <p class="text fz14 fw400 lh22 gray96">${escapeHtml(t('no_timeline_today', 'No timeline for this date.'))}</p>
                    </div>
                `;
            }
            return;
        }

        // 2) 레거시 activities 기반: itinerary_details와 동일한 "관광지/활동" 개수로 타임라인을 생성
        const hasActivities = Array.isArray(daySchedule?.activities) && daySchedule.activities.length > 0;
        if (hasActivities) {
            const startTime = formatTimeHHMM(daySchedule.start_time || '');
            const endTime = formatTimeHHMM(daySchedule.end_time || '');
            const dur = formatDurationFromTimes(daySchedule.start_time || '', daySchedule.end_time || '');
            const addr = String(daySchedule.airport_address || daySchedule.airport_location || '').trim();
            const desc = stripHtml(daySchedule.airport_description || '') || stripHtml(daySchedule.description || '') || '';

            const items = daySchedule.activities.map((activity, idx) => {
                return `
                    <li>
                        <div class="align gap14">
                            <i class="num">${idx + 1}</i>
                            <div>
                                <div class="text fz18 fw500 lh26 reded">${escapeHtml(startTime || '')}${(startTime && endTime) ? ' - ' + escapeHtml(endTime) : ''}</div>
                                <div class="text fz14 fw600 lh22 black12 mt6">${escapeHtml(String(activity || '').trim())}</div>
                                <div class="text fz13 fw400 lh19 grayb0 mt6">${escapeHtml(addr)}</div>
                                <div class="text fz13 fw400 lh19 black12">${escapeHtml(desc)}</div>
                            </div>
                        </div>
                        ${dur ? `
                        <div class="text fz14 fw600 lh22 green1b align gap14 py12 mt16">
                            <i class="time"></i>
                            About ${escapeHtml(dur)}
                        </div>` : ``}
                    </li>
                `;
            }).join('');

            timelineContainer.innerHTML = items || `
                <div class="align center mt40">
                    <p class="text fz14 fw400 lh22 gray96">${escapeHtml(t('no_timeline_today', 'No timeline for this date.'))}</p>
                </div>
            `;
            return;
        }
        
        if (daySchedule.airport_location) {
            const startTime = new Date(`2000-01-01T${daySchedule.start_time || '14:00:00'}`);
            const endTime = new Date(`2000-01-01T${daySchedule.end_time || '15:40:00'}`);
            const duration = Math.round((endTime - startTime) / (1000 * 60 * 60));
            const minutes = Math.round(((endTime - startTime) % (1000 * 60 * 60)) / (1000 * 60));
            
            timelineItems.push(`
                <li>
                    <div class="align gap14">
                        <i class="num">1</i>
                        <div>
                            <div class="text fz18 fw500 lh26 reded">
                                ${startTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })}
                            </div>
                            <div class="text fz14 fw600 lh22 black12 mt6">${daySchedule.airport_location}</div>
                            <div class="text fz13 fw400 lh19 grayb0 mt6">${daySchedule.airport_address || ''}</div>
                            <div class="text fz13 fw400 lh19 black12">${daySchedule.airport_description || daySchedule.description || ''}</div>
                            <p class="text fz12 fw400 lh16 grayb0 mt6">Registered (12:59:27)</p>
                        </div>
                    </div>
                    <div class="text fz14 fw600 lh22 green1b align gap14 py12 mt16">
                        <i class="time"></i>
                        About ${duration}h ${minutes}m
                    </div>
                </li>
            `);
        }
        // SMT 수정(요구사항): Timeline View에서 숙소 정보는 노출하지 않음
        
        if (timelineItems.length > 0) {
            timelineContainer.innerHTML = timelineItems.join('');
        } else {
            timelineContainer.innerHTML = `
                <div class="align center mt40">
                    <p class="text fz14 fw400 lh22 gray96">${escapeHtml(t('no_timeline_today', 'No timeline for this date.'))}</p>
                </div>
            `;
        }
    }

    function createScheduleContainer() {
        const mapContainer = document.querySelector('.map-type1');
        if (mapContainer) {
            const scheduleContainer = document.createElement('div');
            scheduleContainer.className = 'schedule-items mt24';
            mapContainer.parentNode.insertBefore(scheduleContainer, mapContainer.nextSibling);
        }
    }

    function formatTime(timeStr) {
        const time = new Date(`2000-01-01T${timeStr}`);
        return time.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    }

    async function updateNotificationBadge() {
        try {
            const userId = localStorage.getItem('userId');
            if (!userId) return;

            const response = await fetch('../backend/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'get_unread_count',
                    userId: userId,
                    // 일정상세 헤더 벨은 "가이드 공지" 기준
                    category: 'guide_notice'
                })
            });

            if (!response.ok) return;
            const data = await response.json();

            if (data.success) {
                const badge = document.querySelector('.btn-bell .num');
                if (badge) {
                    const c = Number(data?.data?.unreadCount ?? data?.unreadCount ?? data?.count ?? 0);
                    if (c > 0) {
                        badge.textContent = c > 9 ? '9+' : String(c);
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Error updating notification badge:', error);
        }
    }

    function setupFoldingButton() {
        const foldButton = document.querySelector('.btn-folding');
        const profile = document.getElementById('profile');

        if (foldButton && profile) {
            foldButton.addEventListener('click', function() {
                if (profile.style.display === 'none') {
                    profile.style.display = 'block';
                    this.classList.remove('folded');
                } else {
                    profile.style.display = 'none';
                    this.classList.add('folded');
                }
            });
        }
    }

    // Fallback 데이터 함수들
    function getDefaultGuideData() {
        // PHP에서 로드된 guideData가 있으면 사용, 없으면 빈 데이터 반환
        if (window.guideData) {
            return window.guideData;
        }
        
        // fallback: 가이드 정보 없음 표시
        return {
            guideId: null,
            guideName: '',
            phone: '',
            profileImage: '../images/@img_profile.svg',
            about: ''
        };
    }

    function getFallbackScheduleData() {
        const today = new Date();
        const schedules = [];
        
        for (let i = 0; i < 7; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() + i);
            
            schedules.push({
                date: date.toISOString().split('T')[0],
                activities: [
                    'Activity 1',
                    'Activity 2',
                    'Activity 3'
                ],
                start_time: '09:00:00',
                end_time: '11:00:00',
                description: `Day ${i + 1} itinerary.`,
                airport_location: '',
                airport_address: '',
                airport_description: '',
                accommodation_name: '',
                accommodation_address: '',
                accommodation_description: ''
            });
        }
        
        // schedule.js는 "배열"을 기대합니다.
        return schedules;
    }

    const boot = () => {
        loadScheduleData();
        setupFoldingButton();
        setupProfileModal();
    };

    if (typeof kakao !== 'undefined' && kakao.maps && typeof kakao.maps.load === 'function') {
        kakao.maps.load(boot);
    } else {
        boot();
    }
});