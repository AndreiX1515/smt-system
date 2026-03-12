document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId = urlParams.get('booking_id') || urlParams.get('bookingId');
    let guideData = null;
    let locationUpdateInterval = null;

    function getLang() {
        return (window.currentLang || document.documentElement.getAttribute('lang') || 'en').toLowerCase();
    }

    function t(en, ko) {
        return getLang().startsWith('en') ? en : ko;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeAssetUrl(path) {
        const s = String(path || '').trim();
        if (!s) return '';
        if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('data:')) return s;
        if (s.startsWith('//')) return window.location.protocol + s;
        if (s.startsWith('/')) return window.location.origin + s;
        if (s.startsWith('uploads/')) return window.location.origin + '/' + s;
        return s; // relative
    }

    function getGuidePhone() {
        return guideData?.phone || guideData?.phoneNumber || '';
    }

    function getGuideAbout() {
        return guideData?.about || guideData?.introduction || '';
    }

    function openProfileModal() {
        const layer = document.querySelector('.layer');
        const modal = document.querySelector('.profile-modal');
        if (!layer || !modal) return;

        // bind content
        const img = modal.querySelector('img.img-profile');
        const nameInput = modal.querySelector('.form-wrap .input-wrap input[type="text"]');
        const phoneInput = modal.querySelector('.form-wrap .input-wrap input[type="tel"]');
        const aboutP = modal.querySelector('.form-wrap p');

        const imgSrc = normalizeAssetUrl(guideData?.profileImage) || '../images/@img_profile.png';
        if (img) {
            img.src = imgSrc;
            img.alt = (guideData?.guideName || 'Guide');
        }
        if (nameInput) {
            nameInput.value = guideData?.guideName || '';
            nameInput.readOnly = true;
        }
        if (phoneInput) {
            phoneInput.value = getGuidePhone();
            phoneInput.readOnly = true;
        }
        if (aboutP) {
            aboutP.textContent = getGuideAbout() || t(
                'A guide with years of experience and expertise, providing tours tailored to each traveler’s style.',
                '경험과 전문성을 바탕으로 여행자에게 맞춘 투어를 제공합니다.'
            );
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
    }

    // 전역 변수가 로드될 때까지 기다리는 함수
    function waitForGlobalData() {
        return new Promise((resolve) => {
            const checkData = () => {
                if (window.guideData && window.bookingInfo) {
                    console.log('Global data found:', window.guideData, window.bookingInfo);
                    resolve(true);
                } else {
                    console.log('Waiting for global data...');
                    setTimeout(checkData, 100);
                }
            };
            checkData();
        });
    }

    async function loadGuideLocation() {
        console.log('loadGuideLocation called');
        console.log('window.guideData:', window.guideData);
        console.log('window.bookingInfo:', window.bookingInfo);
        console.log('bookingId from URL:', bookingId);
        
        // 전역 변수가 로드될 때까지 기다림
        await waitForGlobalData();
        
        // PHP에서 로드된 데이터가 있으면 사용
        if (window.guideData && window.bookingInfo) {
            console.log('Using PHP-loaded guide data');
            guideData = window.guideData;
            updateGuideInfo();
            updateGuideLocation();
            updateNotificationBadge();
            startLocationUpdates();
            return;
        }

        if (!bookingId) {
            alert(t('Booking information was not found.', '예약 정보를 찾을 수 없습니다.'));
            window.location.href = '../home.html';
            return;
        }

        try {
            // 새로운 API 사용
            const result = await api.getGuideLocation(null, bookingId);
            
            if (result.success) {
                guideData = result.data;
                updateGuideInfo();
                updateGuideLocation();
                updateNotificationBadge();
                startLocationUpdates();
            } else {
                console.error('Failed to load guide location:', result.message);
                // Fallback 데이터 사용
                guideData = getDefaultGuideData();
                updateGuideInfo();
                updateGuideLocation();
            }
        } catch (error) {
            console.error('Error loading guide location:', error);
            // Fallback 데이터 사용
            guideData = getDefaultGuideData();
            updateGuideInfo();
            updateGuideLocation();
        }
    }

    function updateGuideInfo() {
        if (!guideData) return;

        const profileSection = document.getElementById('profile');
        if (profileSection) {
            profileSection.innerHTML = `
                <div>
                    <div class="align gap12">
                        <img class="profile" src="${escapeHtml(normalizeAssetUrl(guideData.profileImage) || '../images/@img_profile.svg')}" alt="">
                        <div>
                            <div class="text fz12 fw500 lh16 grayb0">Guide</div>
                            <div class="text fz14 fw600 lh22 black12">${guideData.guideName}</div>
                            <div class="text fz13 fw400 lh16 black12">${escapeHtml(getGuidePhone())}</div>
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

    // Kakao map only
    let kakaoMap = null;
    let kakaoMarker = null;

    function ensureMap(lat, lng, addressText) {
        const mapEl = document.getElementById('map');
        if (!mapEl) return;

        const clat = Number(lat);
        const clng = Number(lng);
        if (!Number.isFinite(clat) || !Number.isFinite(clng)) return;

        // Kakao SDK 준비 전이면 재시도 (다른 지도 fallback 금지)
        if (!(typeof kakao !== 'undefined' && kakao.maps)) {
            if (ensureMap.__retry == null) ensureMap.__retry = 0;
            if (ensureMap.__retry < 30) {
                ensureMap.__retry++;
                setTimeout(() => ensureMap(lat, lng, addressText), 200);
            }
            return;
        }
        ensureMap.__retry = 0;

        const pos = new kakao.maps.LatLng(clat, clng);
        if (!kakaoMap) {
            kakaoMap = new kakao.maps.Map(mapEl, { center: pos, level: 4 });
            kakaoMarker = new kakao.maps.Marker({ position: pos });
            kakaoMarker.setMap(kakaoMap);
        } else {
            kakaoMap.setCenter(pos);
            if (kakaoMarker) kakaoMarker.setPosition(pos);
        }
    }

    function updateGuideLocation() {
        if (!guideData) return;

        const mapContainer = document.querySelector('.map-type1');
        if (mapContainer) {
            // 요구사항: 마지막 순번(가장 늦은 집합 시각)의 마커가 표시된 지도를 확인(중앙 고정)
            // meetingLocations가 있으면 그 중 첫 번째(순번 내림차순이므로)를 우선 표시
            try {
                const list = window.meetingLocations || window.locationHistory || [];
                const latest = Array.isArray(list) && list.length ? list[0] : null;
                const lat = latest?.latitude ?? latest?.lat ?? null;
                const lng = latest?.longitude ?? latest?.lng ?? null;
                const addr = latest?.address ?? latest?.location ?? '';
                if (lat && lng) {
                    ensureMap(lat, lng, addr || '');
                } else if (guideData.currentLatitude && guideData.currentLongitude) {
                    ensureMap(guideData.currentLatitude, guideData.currentLongitude, guideData.location || '');
                }
            } catch (_) {
                if (guideData.currentLatitude && guideData.currentLongitude) {
                    ensureMap(guideData.currentLatitude, guideData.currentLongitude, guideData.location || '');
                }
            }
        }
    }

    function updateLocationInfo() {
        const locationInfoContainer = document.querySelector('.location-info');
        if (!locationInfoContainer) {
            createLocationInfoContainer();
        }

        const container = document.querySelector('.location-info');
        if (container && guideData) {
            const lastUpdate = new Date(guideData.lastLocationUpdate);
            const timeAgo = getTimeAgo(lastUpdate);

            container.innerHTML = `
                <div class="card-type6 mt16">
                    <div class="align vm both">
                        <div class="location-details">
                            <div class="text fz16 fw600 lh24 black12">${escapeHtml(t('Current location', '현재 위치'))}</div>
                            <p class="text fz14 fw400 lh22 gray6b mt4">${escapeHtml(guideData.location || t('No location information', '위치 정보 없음'))}</p>
                            <div class="text fz12 fw400 lh16 gray96 mt8">
                                <img src="../images/ico_time.svg" class="mr4">
                                ${escapeHtml(t(`${timeAgo} updated`, `${timeAgo} 업데이트`))}
                            </div>
                        </div>
                        <div class="location-actions">
                            <button class="btn line sm" onclick="openNavigation()">${escapeHtml(t('Get directions', '길찾기'))}</button>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    function createLocationInfoContainer() {
        const mapContainer = document.querySelector('.map-type1');
        if (mapContainer) {
            const locationContainer = document.createElement('div');
            locationContainer.className = 'location-info';
            mapContainer.parentNode.insertBefore(locationContainer, mapContainer.nextSibling);
        }
    }

    // 기존 generateMapImage는 사용하지 않음(구글 지도 사용)

    function getTimeAgo(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return t('Just now', '방금 전');
        if (diffMins < 60) return t(`${diffMins} min ago`, `${diffMins}분 전`);
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return t(`${diffHours} hours ago`, `${diffHours}시간 전`);
        
        const diffDays = Math.floor(diffHours / 24);
        return t(`${diffDays} days ago`, `${diffDays}일 전`);
    }

    function startLocationUpdates() {
        locationUpdateInterval = setInterval(async () => {
            try {
                const response = await fetch('../backend/api/guide.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_guide_location',
                        bookingId: bookingId
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data) {
                        const oldLat = guideData?.currentLatitude;
                        const oldLng = guideData?.currentLongitude;
                        guideData = { ...(guideData || {}), ...data.data };

                        if (oldLat !== guideData.currentLatitude || oldLng !== guideData.currentLongitude) {
                            updateGuideLocation();
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating guide location:', error);
            }
        }, 30000);
    }

    async function updateNotificationBadge() {
        try {
            const userId = localStorage.getItem('userId');
            if (!userId) return;

            const response = await fetch('../backend/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_unread_count',
                    userId: userId,
                    // 가이드 공지 벨 배지 기준
                    category: 'guide_notice'
                })
            });

            if (response.ok) {
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
                const icon = foldButton.querySelector('img');
                if (profile.style.display === 'none') {
                    profile.style.display = 'block';
                    icon?.classList.add('active');
                    foldButton.setAttribute('aria-expanded', 'true');
                } else {
                    profile.style.display = 'none';
                    icon?.classList.remove('active');
                    foldButton.setAttribute('aria-expanded', 'false');
                }
            });

            // 초기 상태: 펼침(기획 Case 1 Default)
            const icon = foldButton.querySelector('img');
            icon?.classList.add('active');
            foldButton.setAttribute('aria-expanded', 'true');
        }
    }

    // 기본 가이드 데이터
    function getDefaultGuideData() {
        return {
            guideId: 'guide001',
            guideName: '',
            profileImage: '../images/@img_profile.svg',
            phone: '',
            location: t('Seoul, Korea', '서울, 한국'),
            currentLatitude: 37.5665,
            currentLongitude: 126.9780,
            lastLocationUpdate: new Date().toISOString()
        };
    }

    window.openNavigation = function() {
        if (guideData && guideData.currentLatitude && guideData.currentLongitude) {
            const lat = guideData.currentLatitude;
            const lng = guideData.currentLongitude;
            const name = encodeURIComponent(guideData.location || 'Destination');
            // Kakao Maps 길찾기 URL
            window.open(`https://map.kakao.com/link/to/${name},${lat},${lng}`);
        }
    };

    window.addEventListener('beforeunload', function() {
        if (locationUpdateInterval) {
            clearInterval(locationUpdateInterval);
        }
    });

    const boot = () => {
        setupProfileModal();
        loadGuideLocation();
        setupFoldingButton();
    };
    if (typeof kakao !== 'undefined' && kakao.maps && typeof kakao.maps.load === 'function') kakao.maps.load(boot);
    else boot();
});