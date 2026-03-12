document.addEventListener('DOMContentLoaded', async function() {
    // 뒤로가기 버튼 처리 (select-room.php 전용)
    // Use history.back() to preserve proper navigation history
    const backButton = document.querySelector('.btn-mypage');
    if (backButton && window.location.pathname.includes('select-room.php')) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // button.js의 이벤트 전파 방지
            history.back();
        });
    }
    
    // 다국어 텍스트 정의
    const i18nTexts = {
        'ko': {
            noRoomsAvailable: '사용 가능한 객실이 없습니다.',
            people: '인',
            selectRoom: '객실을 선택해주세요',
            departure: '출발',
            adult: '성인',
            child: '아동',
            infant: '유아',
            roomFee: '객실 요금',
            otherFees: '기타 요금',
            totalAmount: '총 상품 금액',
            feesIncluded: '결제수수료 및 부가세 포함',
            next: '다음',
            selectRoomMessage: '객실을 선택해주세요.',
            insufficientCapacity: '객실 수용 인원이 부족합니다.',
            roomCombinationComplete: '객실 조합이 완료되었습니다.',
            standard_room: '스탠다드룸',
            double_room: '더블룸',
            triple_room: '트리플룸',
            family_room: '패밀리룸',
            current_room_combination: '현재 객실 조합',
            excessCapacity: '선택한 객실의 수용 인원이 예약 인원과 일치하지 않습니다.'
        },
        'en': {
            noRoomsAvailable: 'No rooms available.',
            people: ' people',
            selectRoom: 'Please select rooms',
            departure: 'Departure',
            adult: 'Adult',
            child: 'Child',
            infant: 'Infant',
            roomFee: 'Room Fee',
            otherFees: 'Other Fees',
            totalAmount: 'Total Amount',
            feesIncluded: 'Payment fees and taxes included',
            next: 'Next',
            selectRoomMessage: 'Please select rooms.',
            insufficientCapacity: 'Insufficient room capacity.',
            roomCombinationComplete: 'Room combination completed.',
            standard_room: 'Standard Room',
            double_room: 'Double Room',
            triple_room: 'Triple Room',
            family_room: 'Family Room',
            single_room: 'Single Room',
            current_room_combination: 'Current Room Combination',
            excessCapacity: 'The number of people does not match the room capacity.'
        },
        'tl': {
            noRoomsAvailable: 'Walang available na kwarto.',
            people: ' tao',
            selectRoom: 'Piliin ang mga kwarto',
            departure: 'Alis',
            adult: 'Matanda',
            child: 'Bata',
            infant: 'Sanggol',
            roomFee: 'Bayad sa Kwarto',
            otherFees: 'Ibang Bayad',
            totalAmount: 'Kabuuang Halaga',
            feesIncluded: 'Kasama ang bayad sa pagbabayad at buwis',
            next: 'Susunod',
            selectRoomMessage: 'Piliin ang mga kwarto.',
            insufficientCapacity: 'Kulang ang capacity ng kwarto.',
            roomCombinationComplete: 'Tapos na ang kombinasyon ng kwarto.',
            standard_room: 'Standard Room',
            double_room: 'Double Room',
            triple_room: 'Triple Room',
            family_room: 'Family Room',
            single_room: 'Single Room',
            current_room_combination: 'Kasalukuyang Kombinasyon ng Kwarto',
            excessCapacity: 'Ang mga napiling kwarto ay may mas maraming kapasidad kaysa sa bilang ng mga bisita.'
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
        
        const currentLang = urlLang || localStorage.getItem('selectedLanguage') || 'ko';
        const texts = i18nTexts[currentLang] || i18nTexts['ko'];
        return texts[key] || key;
    }

    // guestOptions (DB pricingOptions 기반) 유틸
    function normalizeSavedGuestOptions(value) {
        const arr = Array.isArray(value) ? value : [];
        return arr
            .map((x) => ({
                name: String(x?.name || x?.optionName || '').trim(),
                unitPrice: Number(x?.unitPrice ?? x?.price ?? NaN),
                qty: Math.max(0, parseInt(x?.qty ?? x?.quantity ?? 0, 10) || 0),
            }))
            .filter((x) => x.name && Number.isFinite(x.unitPrice));
    }

    function totalGuestsFromOptions(opts) {
        return (Array.isArray(opts) ? opts : []).reduce((acc, o) => acc + Number(o?.qty || 0), 0);
    }

    // URL 파라미터에서 예약 정보 가져오기
    const urlParams = new URLSearchParams(window.location.search);
    console.log('URL 파라미터:', urlParams.toString());
    
    const bookingId = urlParams.get('booking_id');
    let currentBooking = null;
    let selectedRooms = {}; // 초기 선언
    
    // booking_id로 DB에서 예약 정보 로드
    async function loadBookingFromAPI(bookingId) {
        try {
            console.log('예약 정보 API 호출:', bookingId);
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
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            console.log('예약 정보 API 응답:', result);
            
            // API 응답 구조: result.success && result.data 또는 result.success && result.booking
            const booking = result.data || result.booking;
            if (result.success && booking) {
                // guestOptions는 selectedOptions(JSON)에서 복원
                let guestOptions = [];
                try {
                    const soRaw = booking.selectedOptions;
                    if (soRaw && typeof soRaw === 'string') {
                        const so = JSON.parse(soRaw);
                        if (so && Array.isArray(so.guestOptions)) {
                            guestOptions = normalizeSavedGuestOptions(so.guestOptions);
                        }
                    }
                } catch (_) { }

                // selectedRooms를 별도 컬럼에서 직접 읽기
                let parsedSelectedRooms = {};
                if (booking.selectedRooms) {
                    try {
                        parsedSelectedRooms = typeof booking.selectedRooms === 'string' 
                            ? JSON.parse(booking.selectedRooms) 
                            : booking.selectedRooms;
                        
                        // booking.selectedRooms가 "[]"로 저장된 경우(JSON 배열)에는
                        // 배열에 string key를 추가해도 JSON.stringify 시 []로만 직렬화되어 저장이 깨짐.
                        // 따라서 배열은 항상 객체로 정규화한다.
                        if (Array.isArray(parsedSelectedRooms)) {
                            parsedSelectedRooms = {};
                        }
                        if (!parsedSelectedRooms || typeof parsedSelectedRooms !== 'object') {
                            parsedSelectedRooms = {};
                        }
                    } catch (e) {
                        console.error('selectedRooms 파싱 오류:', e);
                        parsedSelectedRooms = {};
                    }
                }
                
                currentBooking = {
                    bookingId: booking.bookingId,
                    packageId: booking.packageId,
                    flightId: booking.flightId || null,
                    departureDate: booking.departureDate,
                    departureTime: booking.departureTime || '12:20',
                    packageName: booking.packageName || '',
                    // 고정 adults/children/infants는 사용하지 않고, guestOptions만 사용
                    guestOptions: guestOptions,
                    totalAmount: parseFloat(booking.totalAmount) || 0,
                    selectedRooms: parsedSelectedRooms,
                    // legacy fields (do not use)
                    adults: Number.isFinite(parseInt(booking.adults)) ? parseInt(booking.adults) : 0,
                    children: Number.isFinite(parseInt(booking.children)) ? parseInt(booking.children) : 0,
                    infants: Number.isFinite(parseInt(booking.infants)) ? parseInt(booking.infants) : 0
                };
                
                // localStorage에도 저장
                localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
                
                console.log('=== DB에서 로드한 예약 정보 ===');
                console.log('currentBooking:', currentBooking);
                console.log('guestOptions:', currentBooking.guestOptions);
                console.log('departureDate:', currentBooking.departureDate, 'departureTime:', currentBooking.departureTime);
                console.log('DB에서 로드한 selectedRooms:', currentBooking.selectedRooms);
                console.log('selectedRooms 타입:', typeof currentBooking.selectedRooms);
                console.log('selectedRooms 상세:', JSON.stringify(currentBooking.selectedRooms, null, 2));
                console.log('selectedRooms keys:', Object.keys(currentBooking.selectedRooms || {}));
                
                // 전역 변수에 즉시 반영
                selectedRooms = currentBooking.selectedRooms || {};
                console.log('✅ 전역 selectedRooms 업데이트 완료:', selectedRooms);
                console.log('전역 selectedRooms keys:', Object.keys(selectedRooms));
                
                return true;
            } else {
                console.error('예약 정보 로드 실패:', result.message || 'Unknown error');
                return false;
            }
        } catch (error) {
            console.error('예약 정보 로드 오류:', error);
            return false;
        }
    }
    
    // booking_id가 있으면 DB에서 로드, 없으면 기존 방식(URL 파라미터) 사용
    if (bookingId) {
        const loaded = await loadBookingFromAPI(bookingId);
        if (!loaded) {
            console.warn('DB에서 로드 실패, URL 파라미터 사용');
            // 폴백: URL 파라미터에서 읽기
            currentBooking = {
                bookingId: bookingId,
                packageId: urlParams.get('package_id'),
                flightId: urlParams.get('flight_id'),
                departureDate: urlParams.get('departure_date'),
                departureTime: urlParams.get('departure_time') || '12:20',
                packageName: urlParams.get('package_name'),
                packagePrice: parseFloat(urlParams.get('package_price')) || 0,
                childPrice: null,
                infantPrice: null,
                // URL 파라미터도 0 허용 (성인 1 강제 금지)
                adults: Number.isFinite(parseInt(urlParams.get('adults'))) ? parseInt(urlParams.get('adults')) : 0,
                children: parseInt(urlParams.get('children')) || 0,
                infants: parseInt(urlParams.get('infants')) || 0,
                totalAmount: parseFloat(urlParams.get('total_amount')) || 0,
                selectedRooms: {},
                adultName: null,
                childName: null,
                infantName: null
            };
        }
        // DB에서 로드한 selectedRooms를 전역 변수에 반영 (항상 업데이트)
        if (currentBooking) {
            selectedRooms = currentBooking.selectedRooms || {};
            console.log('DB 로드 후 전역 selectedRooms 업데이트:', selectedRooms);
            console.log('전역 selectedRooms 타입:', typeof selectedRooms, 'keys:', Object.keys(selectedRooms || {}));
        }
    } else {
        // 폴백: URL 파라미터에서 읽기 (기존 호환성 유지)
        currentBooking = {
            packageId: urlParams.get('package_id'),
            flightId: urlParams.get('flight_id'),
            departureDate: urlParams.get('departure_date'),
            departureTime: urlParams.get('departure_time') || '12:20',
            packageName: urlParams.get('package_name'),
            packagePrice: parseFloat(urlParams.get('package_price')) || 0,
            childPrice: null,
            infantPrice: null,
            adults: Number.isFinite(parseInt(urlParams.get('adults'))) ? parseInt(urlParams.get('adults')) : 0,
            children: parseInt(urlParams.get('children')) || 0,
            infants: parseInt(urlParams.get('infants')) || 0,
            totalAmount: parseFloat(urlParams.get('total_amount')) || 0,
            selectedRooms: {},
            adultName: null,
            childName: null,
            infantName: null
        };
        console.log('URL 파라미터에서 읽은 예약 정보:', currentBooking);
    }
    
    if (!currentBooking) {
        console.error('예약 정보를 가져올 수 없습니다.');
        alert('예약 정보를 찾을 수 없습니다.');
        window.location.href = '../home.html';
        return;
    }
    
    console.log('현재 예약 정보:', currentBooking);
    console.log('실제 인원수:', { adults: currentBooking.adults, children: currentBooking.children, infants: currentBooking.infants });
    
    // booking_id를 currentBooking에 저장 (다음 단계에서 사용)
    if (bookingId) {
        currentBooking.bookingId = bookingId;
    }
    
    // 총 인원 수 계산 (guestOptions 기준 - 어떤 옵션도 자동 제외하지 않음)
    let totalGuests = totalGuestsFromOptions(currentBooking.guestOptions);
    console.log('총 인원 수 (guestOptions 기준):', totalGuests);
    
    let roomData = [];
    let packageMeta = { pricingOptions: null, singleRoomFee: null };
    // selectedRooms는 이미 위에서 업데이트되었음
    console.log('초기 selectedRooms (최종):', selectedRooms);
    console.log('currentBooking.selectedRooms:', currentBooking.selectedRooms);
    
    // 초기화 순서 조정
    updateDepartureInfo();
    await loadRoomOptions();
    // loadRoomOptions 내부에서 updateCurrentRoomCombination 호출됨

    // 옵션명 정규화 함수 (select-reservation.js와 동일)
    function normalizePricingOptions(packageData) {
        const opts = Array.isArray(packageData?.pricingOptions) ? packageData.pricingOptions : [];
        if (!opts.length) {
            console.log('normalizePricingOptions: 옵션이 없음');
            return null;
        }

        console.log('normalizePricingOptions: 옵션 개수:', opts.length);
        
        // DB의 옵션명을 그대로 사용 (adult/child/infant 분류 없이 순서대로만 사용)
        // 첫 번째 옵션을 첫 번째 행에, 두 번째 옵션을 두 번째 행에, 세 번째 옵션을 세 번째 행에 표시
        let adult = null, child = null, infant = null;
        let adultName = null, childName = null, infantName = null;
        
        // 첫 번째 옵션 (첫 번째 행에 표시)
        if (opts[0]) {
            const n0 = String(opts[0]?.optionName || '').trim();
            const p0 = Number(opts[0]?.price ?? opts[0]?.optionPrice ?? NaN);
            if (n0 && Number.isFinite(p0)) { 
                adult = p0; 
                adultName = n0; // DB 옵션명 그대로 사용
                console.log('normalizePricingOptions: 첫 번째 옵션 할당:', { adultName, price: p0, index: 0 });
            }
        }
        
        // 두 번째 옵션 (두 번째 행에 표시)
        if (opts[1]) {
            const n1 = String(opts[1]?.optionName || '').trim();
            const p1 = Number(opts[1]?.price ?? opts[1]?.optionPrice ?? NaN);
            if (n1 && Number.isFinite(p1)) { 
                child = p1; 
                childName = n1; // DB 옵션명 그대로 사용
                console.log('normalizePricingOptions: 두 번째 옵션 할당:', { childName, price: p1, index: 1 });
            }
        }
        
        // 세 번째 옵션 (세 번째 행에 표시)
        if (opts[2]) {
            const n2 = String(opts[2]?.optionName || '').trim();
            const p2 = Number(opts[2]?.price ?? opts[2]?.optionPrice ?? NaN);
            if (n2 && Number.isFinite(p2)) { 
                infant = p2; 
                infantName = n2; // DB 옵션명 그대로 사용
                console.log('normalizePricingOptions: 세 번째 옵션 할당:', { infantName, price: p2, index: 2 });
            }
        }
        
        const result = { adult, child, infant, adultName, childName, infantName };
        console.log('normalizePricingOptions: 최종 결과:', JSON.stringify(result, null, 2));
        return result;
    }

    async function loadRoomOptions() {
        try {
            // DB에서 객실 정보 가져오기
            const response = await fetch(`../backend/api/packages.php?id=${encodeURIComponent(currentBooking.packageId)}`, { credentials: 'same-origin' });
            const result = await response.json();
            
            console.log('=== 객실 옵션 API 응답 ===');
            console.log('result:', result);
            console.log('result.data:', result.data);
            console.log('result.data.roomOptions:', result.data?.roomOptions);
            
            // 패키지 가격/옵션(인원별 요금, 싱글룸 요금)도 여기서 같이 갱신 (관리자 설정과 일치)
            if (result?.success && result?.data) {
                const d = result.data;
                packageMeta.singleRoomFee = (d.singleRoomFee === null || d.singleRoomFee === undefined) ? null : parseFloat(d.singleRoomFee);
                packageMeta.pricingOptions = Array.isArray(d.pricingOptions) ? d.pricingOptions : null;
                
                // 인원 옵션(guestOptions)은 DB pricingOptions(optionName/price)만 사용
                const pricingOptions = Array.isArray(d.pricingOptions) ? d.pricingOptions : [];
                const baseGuestOptions = pricingOptions
                    .map((o) => ({
                        name: String(o?.optionName || '').trim(),
                        unitPrice: Number(o?.price ?? o?.optionPrice ?? NaN),
                        qty: 0,
                    }))
                    .filter((x) => x.name && Number.isFinite(x.unitPrice));

                // 1) booking.selectedOptions.guestOptions가 있으면 qty를 유지하면서 unitPrice를 DB값으로 동기화
                // 2) 없으면 sessionStorage 임시값(tempGuestOptions) 사용
                // 3) 그것도 없으면 0으로 초기화
                let existing = Array.isArray(currentBooking.guestOptions) ? currentBooking.guestOptions : [];
                if (!existing.length) {
                    try {
                        const tmp = sessionStorage.getItem('tempGuestOptions');
                        if (tmp) existing = normalizeSavedGuestOptions(JSON.parse(tmp));
                    } catch (_) {}
                }

                if (baseGuestOptions.length) {
                    const byName = new Map(baseGuestOptions.map((x) => [x.name, x]));
                    currentBooking.guestOptions = baseGuestOptions.map((b) => {
                        const hit = existing.find((x) => x.name === b.name);
                        return hit ? { ...b, qty: hit.qty } : b;
                    });
                } else {
                    currentBooking.guestOptions = existing;
                }

                // localStorage에도 저장
                localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
            }

            if (result.success && result.data && result.data.roomOptions && result.data.roomOptions.length > 0) {
                // DB에서 가져온 룸 옵션 사용
                roomData = result.data.roomOptions.map(room => {
                    let roomPrice = parseFloat(room.roomPrice) || 0;
                    const roomId = String(room.roomId || room.roomType?.toLowerCase() || 'standard');
                    const roomTypeLower = (room.roomType || '').toLowerCase();
                    const isSingleRoom = roomTypeLower.includes('single') || roomTypeLower.includes('싱글');

                    // 싱글룸 추가요금은 packages.single_room_fee를 우선 사용 (관리자 등록값)
                    if (isSingleRoom && Number.isFinite(packageMeta.singleRoomFee) && packageMeta.singleRoomFee > 0) {
                        roomPrice = packageMeta.singleRoomFee;
                    }
                    
                    console.log(`객실 ${room.roomType} (${roomId}): 가격 = ${roomPrice}, 싱글룸 여부 = ${isSingleRoom}`);
                    
                    return {
                        id: roomId,
                        name: room.roomType || getI18nText('standard_room'),
                        capacity: room.maxOccupancy || 2,
                        price: roomPrice
                    };
                });
                
                // 싱글룸을 마지막으로 정렬 (패밀리룸 밑으로)
                roomData.sort((a, b) => {
                    const isSingleA = a.id.toLowerCase().includes('single') || a.name.toLowerCase().includes('싱글');
                    const isSingleB = b.id.toLowerCase().includes('single') || b.name.toLowerCase().includes('싱글');
                    if (isSingleA && !isSingleB) return 1; // 싱글룸은 뒤로
                    if (!isSingleA && isSingleB) return -1; // 싱글룸은 뒤로
                    return 0; // 나머지는 원래 순서 유지
                });
                
                console.log('DB에서 가져온 객실 정보:', roomData);
                console.log('객실별 가격 확인:', roomData.map(r => ({ name: r.name, price: r.price, id: r.id })));
            } else {
                console.warn('DB에서 객실 정보를 가져오지 못함, fallback 사용');
                // fallback: 기본 객실 정보 (싱글룸 포함)
                // 가격은 "임의값"을 쓰지 않고, single_room_fee(관리자 등록값)만 사용
                const roomTypeMap = {
                    'single': getI18nText('single_room'),
                    'standard': getI18nText('standard_room'),
                    'double': getI18nText('double_room'),
                    'triple': getI18nText('triple_room'),
                    'family': getI18nText('family_room')
                };

                const singleFee = Number.isFinite(packageMeta.singleRoomFee) ? packageMeta.singleRoomFee : 0;
                
                roomData = [
                    { id: 'standard', name: roomTypeMap['standard'] || '스탠다드룸', capacity: 2, price: 0 },
                    { id: 'double', name: roomTypeMap['double'] || '더블룸', capacity: 2, price: 0 },
                    { id: 'triple', name: roomTypeMap['triple'] || '트리플룸', capacity: 4, price: 0 },
                    { id: 'family', name: roomTypeMap['family'] || '패밀리룸', capacity: 4, price: 0 },
                    { id: 'single', name: roomTypeMap['single'] || '싱글룸', capacity: 1, price: singleFee }
                ];
                console.log('기본 객실 정보 사용 (fallback) - single_room_fee 기반');
            }
            
            displayRoomOptions();
            
            // 초기 가격 정보 표시 (객실이 선택되지 않아도 표시)
            calculatePricing();
            updateCurrentRoomCombination();
            
            // DB에서 불러온 selectedRooms가 있으면 카운터에 반영 (DOM이 렌더링된 후)
            setTimeout(() => {
                // 전역 selectedRooms 변수 확인 (가장 최신 값 사용)
                const roomsToApply = selectedRooms;
                
                console.log('=== DB에서 불러온 selectedRooms 반영 시작 ===');
                console.log('전역 selectedRooms:', selectedRooms);
                console.log('currentBooking.selectedRooms:', currentBooking.selectedRooms);
                console.log('roomsToApply:', roomsToApply);
                console.log('roomsToApply 타입:', typeof roomsToApply);
                console.log('roomsToApply keys:', Object.keys(roomsToApply || {}));
                console.log('roomsToApply JSON:', JSON.stringify(roomsToApply, null, 2));
                
                if (roomsToApply && typeof roomsToApply === 'object' && Object.keys(roomsToApply).length > 0) {
                    console.log('✅ 객실 정보가 있어서 카운터에 반영합니다');
                    Object.keys(roomsToApply).forEach(roomId => {
                        const room = roomsToApply[roomId];
                        console.log(`객실 ${roomId} 정보:`, room);
                        if (room && room.count && room.count > 0) {
                            const counter = document.querySelector(`.counter[data-room-id="${roomId}"]`);
                            console.log(`객실 ${roomId} 카운터 찾기:`, !!counter);
                            if (counter) {
                                const countValue = counter.querySelector('.count-value');
                                if (countValue) {
                                    console.log(`✅ 객실 ${roomId} 카운터 값 설정: ${room.count}`);
                                    countValue.textContent = room.count;
                                    // updateSelectedRooms를 호출하여 상태 업데이트
                                    updateSelectedRooms(roomId, room.count);
                                } else {
                                    console.warn(`객실 ${roomId} 카운터 값 요소를 찾을 수 없음`);
                                }
                            } else {
                                console.warn(`객실 ${roomId} 카운터 요소를 찾을 수 없음 (room-id: ${roomId})`);
                                // 모든 카운터 요소 확인
                                const allCounters = document.querySelectorAll('.counter');
                                console.log('현재 페이지의 모든 카운터:', Array.from(allCounters).map(c => c.dataset.roomId));
                            }
                        }
                    });
                    // 가격 재계산
                    calculatePricing();
                    console.log('✅ selectedRooms 반영 완료, 가격 재계산 완료');
                } else {
                    console.log('⚠️ 불러올 selectedRooms가 없거나 비어있음 (초기 상태 또는 저장되지 않음)');
                    console.log('roomsToApply 상세:', JSON.stringify(roomsToApply, null, 2));
                }
                updateCurrentRoomCombination();
                validateRoomSelection();
            }, 500); // DOM 렌더링 대기 시간 증가 (더 충분한 시간 제공)
        } catch (error) {
            console.error('Error loading room options:', error);
            // 에러 발생 시 기본 객실 정보 사용
            roomData = [
                { id: 'standard', name: '스탠다드룸', capacity: 2, price: 0 },
                { id: 'double', name: '더블룸', capacity: 2, price: 0 },
                { id: 'triple', name: '트리플룸', capacity: 4, price: 0 },
                { id: 'family', name: '패밀리룸', capacity: 4, price: 0 },
                { id: 'single', name: '싱글룸', capacity: 1, price: 0 }
            ];
            displayRoomOptions();
            // 에러 발생 시에도 가격 정보 표시
            calculatePricing();
            updateCurrentRoomCombination();
            validateRoomSelection();
        }
    }

    function displayRoomOptions() {
        console.log('displayRoomOptions 호출됨, roomData:', roomData);
        const roomList = document.querySelector('#room-list') || document.querySelector('ul.mt32');
        
        if (roomData.length === 0) {
            roomList.innerHTML = '<li>' + getI18nText('noRoomsAvailable') + '</li>';
            return;
        }

        // 싱글룸 추가요금 정책: 1인 예약(유아 제외)일 때는 청구하지 않음
        const bookingGuests = totalGuestsFromOptions(currentBooking?.guestOptions);
        const isSingleRoomId = (id, name) => {
            const rid = String(id || '').toLowerCase();
            const rnm = String(name || '').toLowerCase();
            return rid === 'single' || rid.includes('single') || rnm.includes('single') || rnm.includes('싱글');
        };

        const newHTML = roomData.map((room, index) => {
            const single = isSingleRoomId(room.id, room.name);
            const displayPrice = (single && bookingGuests <= 1) ? 0 : (room.price || 0);
            return `
            <li class="align vm both ${index > 0 ? 'mt20' : ''}">
                <div>
                    <p class="text fz14 fw500 lh22 black12">${room.name}</p>
                    <p class="text fz14 fw500 lh22 black12">${room.capacity}${getI18nText('people')}</p>
                    ${(displayPrice > 0 || single) ? `<p class="text fz12 fw400 lh16 grayb0 mt4">₱${displayPrice.toLocaleString()}</p>` : ''}
                </div>
                <div class="counter" data-room-id="${room.id}">
                    <button class="btn-minus" type="button"><img src="../images/ico_minus.svg" alt=""></button>
                    <p class="count-value">0</p>
                    <button class="btn-plus" type="button"><img src="../images/ico_plus.svg" alt=""></button>
                </div>
            </li>
        `;
        }).join('');

        console.log('새로운 HTML 생성:', newHTML);
        roomList.innerHTML = newHTML;

        setupCounters();
    }

    function setupCounters() {
        document.querySelectorAll('.counter').forEach(counter => {
            const roomId = counter.dataset.roomId;
            const minusBtn = counter.querySelector('.btn-minus');
            const plusBtn = counter.querySelector('.btn-plus');
            const countValue = counter.querySelector('.count-value');

            minusBtn.addEventListener('click', () => {
                let currentCount = parseInt(countValue.textContent);
                if (currentCount > 0) {
                    currentCount--;
                    countValue.textContent = currentCount;
                    updateSelectedRooms(roomId, currentCount);
                }
            });

            plusBtn.addEventListener('click', () => {
                let currentCount = parseInt(countValue.textContent);
                const newCount = currentCount + 1;
                
                // 증가 시킬 객실 정보 찾기
                const room = roomData.find(r => String(r.id) === String(roomId));
                if (!room) {
                    console.warn(`객실 정보를 찾을 수 없음: ${roomId}`);
                    return;
                }
                
                // 스텝퍼는 항상 동작 (인원 제한 체크 제거)
                // 인원 불일치는 validateRoomSelection()에서 검증하여 메시지 표시 및 버튼 비활성화
                countValue.textContent = newCount;
                updateSelectedRooms(roomId, newCount);
            });
        });
    }

    function updateSelectedRooms(roomId, count) {
        // roomId를 문자열로 통일 (타입 불일치 방지)
        const normalizedRoomId = String(roomId);
        
        if (count === 0) {
            delete selectedRooms[normalizedRoomId];
            console.log(`객실 ${normalizedRoomId} 제거됨`);
        } else {
            // roomData에서 찾을 때도 문자열로 비교
            const room = roomData.find(r => String(r.id) === normalizedRoomId);
            if (room) {
                let roomPrice = room.price || 0;
                let totalPrice = 0;
                
                // 싱글룸 가격 정책: 2인 이상 예약 시에만 싱글룸 추가 요금 부과
                // 1인 예약 시 싱글룸 사용은 무료
                const isSingleRoom = normalizedRoomId.toLowerCase().includes('single') || 
                                     normalizedRoomId.toLowerCase() === 'single' ||
                                     room.name.toLowerCase().includes('싱글') ||
                                     room.name.toLowerCase().includes('single');
                
                if (isSingleRoom) {
                    // 총 예약 인원 수 계산 (소아 제외)
                    const totalBookingGuests = totalGuestsFromOptions(currentBooking.guestOptions);
                    
                    if (totalBookingGuests >= 2) {
                        // 2인 이상 예약 시 싱글룸 추가 요금 발생
                        totalPrice = roomPrice * count;
                        console.log(`싱글룸 추가 요금 적용: ${totalBookingGuests}명 예약, ${count}개 × ${roomPrice}₱ = ${totalPrice}₱`);
                    } else {
                        // 1인 예약 시 싱글룸 사용은 무료
                        totalPrice = 0;
                        console.log(`1인 예약이므로 싱글룸 추가 요금 없음: ${totalBookingGuests}명 예약`);
                    }
                } else {
                    // 싱글룸이 아닌 경우: room_options에 가격이 있으면 그대로 적용
                    totalPrice = roomPrice * count;
                }
                
                console.log(`객실 ${normalizedRoomId} 업데이트:`, {
                    name: room.name,
                    price: roomPrice,
                    count: count,
                    totalPrice: totalPrice,
                    isSingleRoom: isSingleRoom
                });
                
                selectedRooms[normalizedRoomId] = {
                    ...room,
                    id: normalizedRoomId, // ID도 문자열로 통일
                    count: count,
                    totalCapacity: room.capacity * count,
                    totalPrice: totalPrice,
                    isSingleRoom: isSingleRoom
                };
                console.log(`객실 ${normalizedRoomId} 최종 업데이트:`, selectedRooms[normalizedRoomId]);
            } else {
                console.warn(`객실 데이터를 찾을 수 없음: ${normalizedRoomId}`);
                console.warn('현재 roomData:', roomData);
                console.warn('찾으려는 roomId:', normalizedRoomId, '타입:', typeof normalizedRoomId);
            }
        }
        
        // currentBooking에 동기화하고 localStorage에 저장
        if (!currentBooking) {
            currentBooking = {};
        }
        currentBooking.selectedRooms = selectedRooms;
        localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
        console.log('selectedRooms 업데이트 및 저장 완료:', selectedRooms);
        console.log('selectedRooms 가격 요약:', Object.values(selectedRooms).map(r => ({
            name: r.name,
            count: r.count,
            price: r.price,
            totalPrice: r.totalPrice,
            isSingleRoom: r.isSingleRoom
        })));
        
        // 자동 저장 (debounce 적용)
        clearTimeout(window.autoSaveTimeout);
        window.autoSaveTimeout = setTimeout(() => {
            if (currentBooking.bookingId) {
                console.log('자동 저장 시작...');
                saveTempBooking().then(bookingId => {
                    if (bookingId) {
                        console.log('자동 저장 성공:', bookingId);
                    }
                }).catch(err => {
                    console.error('자동 저장 실패:', err);
                });
            } else {
                console.warn('bookingId가 없어서 자동 저장할 수 없음');
            }
        }, 1000); // 1초 후 자동 저장

        updateCurrentRoomCombination();
        calculatePricing();
        validateRoomSelection();
    }

    function updateCurrentRoomCombination() {
        const combinationDiv = document.getElementById('room-combination');
        let totalRooms = 0;
        let totalCapacity = 0;
        let roomSummary = [];

        Object.values(selectedRooms).forEach(room => {
            totalRooms += room.count;
            totalCapacity += room.totalCapacity;
            roomSummary.push(`${room.name} x${room.count}`);
        });

        // 초기 상태일 때 기본값 설정
        if (totalRooms === 0) {
            totalCapacity = 0; // 아직 객실을 선택하지 않음
            console.log('초기 상태 - 객실 미선택');
        }

        // 총 예약 인원 수 계산 (소아 제외)
        const totalBookingGuests = totalGuestsFromOptions(currentBooking.guestOptions);
        const summaryText = roomSummary.length > 0 ? roomSummary.join(', ') : getI18nText('selectRoom');
        combinationDiv.innerHTML = `${getI18nText('current_room_combination')} (${totalCapacity}/${totalBookingGuests}${getI18nText('people')})<br><small>${summaryText}</small>`;
    }

    async function calculatePricing() {
        try {
            // 인원 옵션(guestOptions) 기준으로만 가격 계산 (adults/children/infants 사용 안함)
            const guestTotal = (currentBooking.guestOptions || []).reduce(
                (acc, o) => acc + (Number(o?.unitPrice || 0) * Number(o?.qty || 0)),
                0
            );
            
            // 룸 가격 계산 (updateSelectedRooms에서 계산된 totalPrice를 합산)
            let roomPrice = 0;
            Object.values(selectedRooms).forEach(room => {
                // updateSelectedRooms에서 이미 계산된 totalPrice 사용
                const roomTotal = room.totalPrice || 0;
                console.log(`객실 ${room.name || room.id} 가격 계산:`, {
                    price: room.price,
                    count: room.count,
                    totalPrice: room.totalPrice,
                    isSingleRoom: room.isSingleRoom,
                    계산된_totalPrice: roomTotal
                });
                roomPrice += roomTotal;
            });

            const totalPrice = guestTotal + roomPrice;
            const pricing = {
                guest_price: guestTotal,
                room_price: roomPrice,
                additional_fees: 0,
                total_price: totalPrice
            };

            displayPricing(pricing);
        } catch (error) {
            console.error('Error calculating pricing:', error);
        }
    }

    function displayPricing(pricing) {
        const pricingSection = document.getElementById('pricing-section');

        let itemsHtml = '';
        // 1단계에서 저장한 guestOptions(옵션명/가격/수량) 그대로 표시
        const selectedGuests = (currentBooking.guestOptions || []).filter(o => Number(o?.qty || 0) > 0);
        selectedGuests.forEach((o, idx) => {
            const rowTotal = Number(o?.unitPrice || 0) * Number(o?.qty || 0);
            itemsHtml += `
                <div class="align both vm ${idx > 0 ? 'mt8' : ''}">
                    <p class="text fz14 fw400 lh22 black12">${String(o.name || '')}x${Number(o.qty || 0)}</p>
                    <span class="text fz14 fw400 lh22 black12">₱${rowTotal.toLocaleString()}</span>
                </div>
            `;
        });
        
        // 각 객실 타입별로 개별 가격 표시 (가격이 있으면 표시)
        Object.values(selectedRooms).forEach(room => {
            if (room.count > 0) {
                const roomTotalPrice = room.totalPrice || 0;
                itemsHtml += `
                    <div class="align both vm mt8">
                        <p class="text fz14 fw400 lh22 black12">${room.name}x${room.count}</p>
                        <span class="text fz14 fw400 lh22 black12">₱${roomTotalPrice.toLocaleString()}</span>
                    </div>
                `;
            }
        });

        pricingSection.innerHTML = `
            ${itemsHtml}
            <div class="mt24 align both vm">
                <div class="text fz16 fw600 lh24 black12">${getI18nText('totalAmount')}</div>
                <strong class="text fz20 fw600 lh28 black12">₱${pricing.total_price.toLocaleString()}</strong>
            </div>
            <p class="align right mt4 text fz14 fw400 grayb0 lh140">${getI18nText('feesIncluded')}</p>
            <div class="mt88 mb12">
                <div class="validation-message"></div>
                 <a class="btn primary lg next-btn" href="javascript:void(0);">${getI18nText('next')}</a>
            </div>
        `;

        currentBooking.pricing = pricing;
        localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
    }

    function validateRoomSelection() {
        const validationMessage = document.querySelector('.validation-message');
        const nextBtn = document.querySelector('.next-btn');
        
        if (!validationMessage || !nextBtn) return;

        // 총 예약 인원 수 계산 (소아 제외)
        const totalBookingGuests = totalGuestsFromOptions(currentBooking.guestOptions);
        
        // 각 룸타입 수량 × 수용 인원 합 계산
        let totalCapacity = 0;
        Object.values(selectedRooms).forEach(room => {
            totalCapacity += room.totalCapacity;
        });

        // 초기 상태 (객실 미선택)
        if (totalCapacity === 0) {
            validationMessage.innerHTML = '<div class="text fz12 fw400 lh16 reded mt4 mb12 align center">' + getI18nText('selectRoomMessage') + '</div>';
            nextBtn.classList.add('inactive');
            nextBtn.style.pointerEvents = 'none';
            return;
        }

        // 총 예약 인원 수 = 각 룸타입 수량 × 수용 인원 합 검증
        // 수용 인원이 부족한 경우
        if (totalCapacity < totalBookingGuests) {
            validationMessage.innerHTML = '<div class="text fz12 fw400 lh16 reded mt4 mb12 align center">' + getI18nText('insufficientCapacity') + '</div>';
            nextBtn.classList.add('inactive');
            nextBtn.style.pointerEvents = 'none';
            
            currentBooking.selectedRooms = selectedRooms;
            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
            return;
        }

        // 수용 인원이 예약 인원보다 많은 경우 - 버튼 비활성화
        if (totalCapacity > totalBookingGuests) {
            const excessCapacity = totalCapacity - totalBookingGuests;
            const warningText = getI18nText('excessCapacity') || `선택한 객실의 수용 인원이 예약 인원보다 ${excessCapacity}명 많습니다.`;
            validationMessage.innerHTML = '<div class="text fz12 fw400 lh16 reded mt4 mb12 align center">' + warningText + '</div>';
            nextBtn.classList.add('inactive');
            nextBtn.style.pointerEvents = 'none';
            
            currentBooking.selectedRooms = selectedRooms;
            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
            return;
        }

        // 수용 인원이 예약 인원과 정확히 일치하는 경우만 성공 메시지 및 버튼 활성화
        if (totalCapacity === totalBookingGuests) {
            validationMessage.innerHTML = '<div class="text fz12 fw400 lh16 green1b mt4 mb12 align center">' + getI18nText('roomCombinationComplete') + '</div>';
            nextBtn.classList.remove('inactive');
            nextBtn.style.pointerEvents = 'auto';
            
            currentBooking.selectedRooms = selectedRooms;
            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
            return;
        }
    }

    function updateDepartureInfo() {
        // 출발 정보는 PHP에서 이미 포맷되어 표시되므로 업데이트하지 않음
        // (다국어 지원을 위해 PHP의 formatDate 함수 사용)
        // 하지만 DB에서 불러온 정보가 있으면 업데이트
        if (currentBooking && currentBooking.departureDate) {
            const departureInfo = document.querySelector('.departure-info');
            if (departureInfo && currentBooking.departureTime) {
                // 날짜와 시간을 포맷팅하여 표시
                const departureDateTime = `${currentBooking.departureDate} ${currentBooking.departureTime}`;
                // 다국어 지원을 위해 formatDate 사용 (PHP에서 이미 처리됨)
                console.log('출발 정보:', departureDateTime);
            }
        }
    }

    // URL에서 가져온 가격 정보로 화면 업데이트
    function updatePricingFromURL() {
        const basePrice = currentBooking.packagePrice;
        const childPrice = Math.max(basePrice - 5000, 0);
        const infantPrice = 9000;
        
        console.log('가격 계산:', { basePrice, childPrice, infantPrice });
        console.log('인원 수:', { adults: currentBooking.adults, children: currentBooking.children, infants: currentBooking.infants });
        
        const adultTotal = currentBooking.adults * basePrice;
        const childTotal = currentBooking.children * childPrice;
        const infantTotal = currentBooking.infants * infantPrice;
        const totalPrice = adultTotal + childTotal + infantTotal;
        
        console.log('총 가격 계산:', { adultTotal, childTotal, infantTotal, totalPrice });
        
        // 가격 정보 업데이트
        const pricingSection = document.getElementById('pricing-section');
        if (pricingSection) {
            let itemsHtml = '';
            
            if (currentBooking.adults > 0) {
                itemsHtml += `
                    <div class="align both vm">
                        <p class="text fz14 fw400 lh22 black12">${getI18nText('adult')}x${currentBooking.adults}</p>
                        <span class="text fz14 fw400 lh22 black12">₱${adultTotal.toLocaleString()}</span>
                    </div>
                `;
            }
            
            if (currentBooking.children > 0) {
                itemsHtml += `
                    <div class="align both vm mt8">
                        <p class="text fz14 fw400 lh22 black12">${getI18nText('child')}x${currentBooking.children}</p>
                        <span class="text fz14 fw400 lh22 black12">₱${childTotal.toLocaleString()}</span>
                    </div>
                `;
            }
            
            if (currentBooking.infants > 0) {
                itemsHtml += `
                    <div class="align both vm mt8">
                        <p class="text fz14 fw400 lh22 black12">${getI18nText('infant')}x${currentBooking.infants}</p>
                        <span class="text fz14 fw400 lh22 black12">₱${infantTotal.toLocaleString()}</span>
                    </div>
                `;
            }
            
            pricingSection.innerHTML = `
                ${itemsHtml}
                <div class="mt24 align both vm">
                    <div class="text fz16 fw600 lh24 black12">${getI18nText('totalAmount')}</div>
                    <strong class="text fz20 fw600 lh28 black12">₱${totalPrice.toLocaleString()}</strong>
                </div>
                <p class="align right mt4 text fz14 fw400 grayb0 lh140">${getI18nText('feesIncluded')}</p>
                <div class="mt88 mb12">
                    <div class="validation-message"></div>
                    <a class="btn primary lg next-btn" href="javascript:void(0);">${getI18nText('next')}</a>
                </div>
            `;
        }
        
        // 객실 검증 실행
        validateRoomSelection();
    }

    // 초기화 완료
    
    // 중앙 토스트 메시지 표시
    function showCenterToast(message) {
        const existingToast = document.querySelector('.toast-center');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = 'toast-center';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }

    // 임시 저장 함수
    async function saveTempBooking() {
        try {
            // selectedRooms를 currentBooking에 동기화
            currentBooking.selectedRooms = selectedRooms;
            localStorage.setItem('currentBooking', JSON.stringify(currentBooking));
            
            const userId = localStorage.getItem('userId') || null;
            const totalGuests = totalGuestsFromOptions(currentBooking.guestOptions);
            const totalAmount = Number(currentBooking?.pricing?.total_price ?? currentBooking.totalAmount ?? 0);
            const saveData = {
                userId: userId,
                bookingId: currentBooking.bookingId || null, // 기존 예약이 있으면 포함하여 업데이트
                packageId: currentBooking.packageId,
                departureDate: currentBooking.departureDate,
                departureTime: currentBooking.departureTime || '12:20',
                packageName: currentBooking.packageName,
                // legacy columns: keep total guests in adults (children/infants=0) for compatibility
                adults: totalGuests,
                children: 0,
                infants: 0,
                totalAmount: totalAmount,
                // dynamic guest options (authoritative)
                guestOptions: currentBooking.guestOptions || [],
                selectedRooms: selectedRooms // 전역 selectedRooms 변수 사용 (빈 객체도 포함)
            };
            
            console.log('저장할 데이터:', saveData);
            console.log('selectedRooms 타입:', typeof selectedRooms, '값:', selectedRooms);
            
            const response = await fetch('../backend/api/save-temp-booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saveData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 반환된 bookingId를 currentBooking에 저장
                if (result.bookingId) {
                    currentBooking.bookingId = result.bookingId;
                    currentBooking.tempId = result.bookingId;
                    console.log('임시 저장된 예약번호:', result.bookingId);
                }
                
                // Language policy: en default, only en/tl
                const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
                const currentLang = (cur === 'tl') ? 'tl' : 'en';
                let message = (currentLang === 'tl') ? 'Naka-save na pansamantala' : 'Temporarily saved';
                showCenterToast(message);
                return result.bookingId || null; // bookingId 반환
            } else {
                console.error('임시 저장 실패:', result.message);
                // 요구사항(id 47): 단계 이동 시 임시저장 토스트는 항상 노출
                try {
                    const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
                    const currentLang = (cur === 'tl') ? 'tl' : 'en';
                    const message = (currentLang === 'tl') ? 'Naka-save na pansamantala' : 'Temporarily saved';
                    showCenterToast(message);
                } catch (_) {}
                return null;
            }
        } catch (error) {
            console.error('임시 저장 오류:', error);
            // 요구사항(id 47): 저장 실패여도 사용자 흐름은 유지 + 토스트 노출
            try {
                const cur = String(localStorage.getItem('selectedLanguage') || '').toLowerCase();
                const currentLang = (cur === 'tl') ? 'tl' : 'en';
                const message = (currentLang === 'tl') ? 'Naka-save na pansamantala' : 'Temporarily saved';
                showCenterToast(message);
            } catch (_) {}
            return null;
        }
    }

    // 다음 버튼 클릭 이벤트 추가
    document.addEventListener('click', async function(e) {
        if (e.target.classList.contains('next-btn') || e.target.closest('.next-btn')) {
            e.preventDefault();
            
            // 요구사항(id 47): booking_id가 이미 있어도 다음 단계로 넘어가기 전에 "항상" 임시저장(업데이트)한다.
            const savedId = await saveTempBooking();
            console.log('saveTempBooking() 결과:', savedId);

            // UX/요구사항: 사용자가 임시저장 토스트를 "실제로 볼 수 있도록" 최소 지연
            try { await new Promise(r => setTimeout(r, 800)); } catch (_) {}

            // bookingId 우선순위: saveTempBooking() > URL 파라미터 > currentBooking.bookingId
            let finalBookingId = savedId || bookingId || currentBooking?.bookingId || currentBooking?.tempId;
            
            if (finalBookingId) {
                // 예약번호가 있으면 booking_id만 URL에 포함
                const params = new URLSearchParams();
                params.set('booking_id', finalBookingId);
                console.log('예약번호만 URL에 포함:', finalBookingId);
                window.location.href = `enter-customer-info.php?${params.toString()}`;
            } else {
                // 저장 실패 시 폴백 (기존 호환성 유지)
                console.warn('임시 저장 실패, 기존 방식으로 진행');
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
                window.location.href = `enter-customer-info.php?${params.toString()}`;
            }
        }
    });
});