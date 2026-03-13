/**
 * API   JavaScript 
 *   API  
 */

class SmartTravelAPI {
    constructor() {
        //      origin   API
        // (php -S //  )
        const origin = (typeof window !== 'undefined' && window.location && window.location.origin)
            ? window.location.origin
            : '';

        // Environment detection: localhost vs production
        const isLocalhost = (typeof window !== 'undefined' && window.location)
            && (window.location.hostname === 'localhost'
                || window.location.hostname === '127.0.0.1'
                || window.location.hostname.startsWith('192.168.')
                || window.location.hostname.startsWith('10.'));

        // override  window.__API_BASE_URL__
        this.baseURL = (typeof window !== 'undefined' && window.__API_BASE_URL__)
            ? String(window.__API_BASE_URL__).replace(/\/+$/, '')
            : isLocalhost
                ? `${origin}/smt-system/backend/api`  // Local: XAMPP with /smt-system/ subdirectory
                : `${origin}/backend/api`;             // Production: root directory
        this.endpoints = {
            auth: `${this.baseURL}/login.php`,
            //    packages.php(/// )  
            packages: `${this.baseURL}/packages.php`,
            bookings: `${this.baseURL}/user_bookings.php`,
            notifications: `${this.baseURL}/notifications.php`,
            inquiries: `${this.baseURL}/inquiries.php`,
            payments: `${this.baseURL}/payment.php`,
            visa: `${this.baseURL}/visa.php`,
            profile: `${this.baseURL}/profile.php`,
            // App(WebView) push token + Expo push sender
            pushToken: `${this.baseURL}/push-token.php`,
            expoPush: `${this.baseURL}/expo-push.php`
        };
    }

    /**
     * HTTP
     */
    async request(url, options = {}) {
        try {
            // AbortSignal.timeout은 일부 브라우저에서 지원되지 않으므로 AbortController 사용
            const abortController = new AbortController();
            const timeoutId = setTimeout(() => abortController.abort(), 10000); // 10초 타임아웃

            const defaultOptions = {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: abortController.signal
            };

            const finalOptions = { ...defaultOptions, ...options };

            //
            const token = localStorage.getItem('authToken');
            if (token) {
                finalOptions.headers.Authorization = `Bearer ${token}`;
            }

            const response = await fetch(url, finalOptions);

            // 타임아웃 정리
            clearTimeout(timeoutId);

            console.log('API Response status:', response.status);
            console.log('API Response headers:', response.headers);

            //
            const responseText = await response.text();

            // JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                // JSON
                console.error('API Error response (non-JSON):', responseText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // 404  HTTP   JSON   ( success )
            if (!response.ok) {
                console.error('API Error response:', responseText);
                // JSON
                return data;
            }

            console.log('API Response data:', data);
            return data;
        } catch (error) {
            console.error('API Request Error:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            console.error('Error stack:', error.stack);

            // 네트워크 에러 또는 타임아웃 에러 처리
            if (error.name === 'AbortError' || error.message.includes('timeout')) {
                throw new Error('Request timeout. Please try again.');
            } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                throw new Error('Network error. Please check your connection and try again.');
            }

            throw error;
        }
    }

    // ==========   API ==========

    /**
     *   
     */
    async getPackages(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${this.endpoints.packages}?${queryString}` : this.endpoints.packages;
        const res = await this.request(url);
        //  :
        // - packages.php: { success, data: [ ... ], pagination: {...} }
        // - home_packages.php(): { success, data: { packages:[...] } }
        if (res && res.success) {
            if (Array.isArray(res.data)) {
                return {
                    success: true,
                    message: res.message || '',
                    data: {
                        packages: res.data,
                        pagination: res.pagination || null
                    }
                };
            }
        }
        return res;
    }

    async getCategories() {
        return await this.request(`${this.baseURL}/categories.php`);
    }

    /**
     *    
     */
    async getPackageDetail(packageId) {
        // NOTE: home_packages.php   API.  packages.php?id=  .
        return await this.request(`${this.baseURL}/packages.php?id=${encodeURIComponent(packageId)}`);
    }

    // ==========   API ==========
    
    async login(email, password, rememberMe = false) {
        console.log('Login API call:', {
            url: this.endpoints.auth,
            email: email,
            password: password ? '***' : 'empty',
            rememberMe: rememberMe
        });
        
        return await this.request(this.endpoints.auth, {
            method: 'POST',
            body: JSON.stringify({ email, password, rememberMe })
        });
    }

    async logout() {
        //    
        try {
            await this.request(`${this.baseURL}/logout.php`, {
                method: 'POST',
                body: JSON.stringify({})
            });
        } catch (_) {
            // ignore
        }

        //   
        localStorage.removeItem('authToken');
        localStorage.removeItem('userInfo');
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userEmail');
        localStorage.removeItem('userId');
        localStorage.removeItem('username');
        localStorage.removeItem('accountType');
        localStorage.removeItem('autoLogin');
        
        return { success: true, message: 'Logged out.' };
    }

    async checkSession() {
        return await this.request(`${this.baseURL}/check-session.php`);
    }

    async register(name, email, phone, password) {
        const data = { name, email, password };
        if (phone != null && String(phone).trim() !== '') {
            data.phone = String(phone).trim();
        }
        return await this.request(`${this.baseURL}/register.php`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async checkEmailAvailability(email, excludeAccountId = null) {
        const payload = { email };
        if (excludeAccountId !== null && excludeAccountId !== undefined && String(excludeAccountId).trim() !== '') {
            payload.accountId = excludeAccountId;
        }
        return await this.request(`${this.baseURL}/check-email.php`, {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    }
    
    async findId(name, phone) {
        return await this.request(`${this.baseURL}/find-id.php`, {
            method: 'POST',
            body: JSON.stringify({ name, phone })
        });
    }
    
    async findPassword(action, email, verificationCode = '', newPassword = '') {
        const data = { action, email };
        
        if (verificationCode) {
            data.verificationCode = verificationCode;
        }
        
        if (newPassword) {
            data.newPassword = newPassword;
        }
        
        return await this.request(`${this.baseURL}/find-password.php`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async getUserPermissions(userId) {
        return await this.request(`${this.baseURL}/user-permissions.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_permissions', userId })
        });
    }
    
    async updateUserPermissions(userId, permissions) {
        return await this.request(`${this.baseURL}/user-permissions.php`, {
            method: 'POST',
            body: JSON.stringify({ 
                action: 'update_permissions', 
                userId, 
                ...permissions 
            })
        });
    }
    
    async getTravelSchedule(userId = null, bookingId = null) {
        const params = new URLSearchParams();
        if (userId) params.append('userId', userId);
        if (bookingId) params.append('bookingId', bookingId);
        
        return await this.request(`${this.baseURL}/travel-schedule.php?${params.toString()}`);
    }
    
    async getTravelScheduleDetail(bookingId) {
        return await this.request(`${this.baseURL}/travel-schedule.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_schedule_detail', bookingId })
        });
    }
    
    async getGuide(guideId = null, bookingId = null) {
        const params = new URLSearchParams();
        if (guideId) params.append('guideId', guideId);
        if (bookingId) params.append('bookingId', bookingId);
        
        return await this.request(`${this.baseURL}/guide.php?${params.toString()}`);
    }
    
    async getGuideLocation(guideId = null, bookingId = null) {
        return await this.request(`${this.baseURL}/guide.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_guide_location', guideId, bookingId })
        });
    }
    
    async getGuideProfile(guideId) {
        return await this.request(`${this.baseURL}/guide.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_guide_profile', guideId })
        });
    }
    
    async getGuideNotices(guideId = null, bookingId = null, limit = 10, offset = 0) {
        return await this.request(`${this.baseURL}/guide.php`, {
            method: 'POST',
            body: JSON.stringify({ 
                action: 'get_guide_notices', 
                guideId, 
                bookingId, 
                limit, 
                offset 
            })
        });
    }
    
    async getNotifications(userId, category = '', limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append('userId', userId);
        if (category) params.append('category', category);
        params.append('limit', limit);
        params.append('offset', offset);
        
        //   
        const currentLang = localStorage.getItem('selectedLanguage') || 'ko';
        params.append('lang', currentLang);
        
        return await this.request(`${this.baseURL}/notifications.php?${params.toString()}`);
    }
    
    async markNotificationAsRead(userId, notificationId) {
        return await this.request(`${this.baseURL}/notifications.php`, {
            method: 'POST',
            body: JSON.stringify({ 
                action: 'mark_as_read', 
                userId, 
                notificationId 
            })
        });
    }
    
    async markAllNotificationsAsRead(userId, category = '') {
        return await this.request(`${this.baseURL}/notifications.php`, {
            method: 'POST',
            body: JSON.stringify({ 
                action: 'mark_all_as_read', 
                userId, 
                category 
            })
        });
    }
    
    async getUnreadNotificationCount(userId, category = '') {
        return await this.request(`${this.baseURL}/notifications.php`, {
            method: 'POST',
            body: JSON.stringify({ 
                action: 'get_unread_count', 
                userId, 
                category 
            })
        });
    }
    
    async getPackages(params = {}) {
        const queryParams = new URLSearchParams();
        Object.keys(params).forEach(key => {
            if (params[key] !== undefined && params[key] !== '') {
                queryParams.append(key, params[key]);
            }
        });

        // IMPORTANT:
        //  home_packages.php sales_target(B2B/B2C)       .
        //    packages.php (   B2B/B2C  ).
        const res = await this.request(`${this.baseURL}/packages.php?${queryParams.toString()}`);
        // packages.php  : { success, data:[...] } -> { success, data:{packages:[...], pagination} }
        if (res && res.success && Array.isArray(res.data)) {
            return {
                success: true,
                message: res.message || '',
                data: {
                    packages: res.data,
                    pagination: res.pagination || null
                }
            };
        }
        return res;
    }
    
    async getPackageDetail(packageId) {
        return await this.request(`${this.baseURL}/packages.php?id=${packageId}`);
    }
    
    async searchPackages(query, category = '', limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append('search', query);
        if (category) params.append('category', category);
        params.append('limit', limit);
        params.append('offset', offset);
        
        return await this.request(`${this.baseURL}/packages.php?${params.toString()}`);
    }
    
    async createBooking(bookingData) {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'create_booking', ...bookingData })
        });
    }
    
    async getBooking(bookingId, accountId = null) {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_booking', bookingId, accountId })
        });
    }
    
    async updateBooking(bookingId, updateData) {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'update_booking', bookingId, updateData })
        });
    }
    
    async cancelBooking(bookingId, accountId = null, reason = '') {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'cancel_booking', bookingId, accountId, reason })
        });
    }
    
    async getBookingSummary(bookingId) {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_booking_summary', bookingId })
        });
    }
    
    async validateBooking(bookingData) {
        return await this.request(`${this.baseURL}/booking.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'validate_booking', ...bookingData })
        });
    }
    
    async getUserBookings(accountId, status = 'all') {
        const params = new URLSearchParams();
        params.append('accountId', accountId);
        if (status !== 'all') params.append('status', status);
        
        return await this.request(`${this.baseURL}/user_bookings.php?${params.toString()}`);
    }
    
    async getUserProfile(accountId) {
        return await this.request(`${this.baseURL}/user_profile.php`, {
            method: 'POST',
            body: JSON.stringify({ accountId })
        });
    }
    
    async updateUserProfile(accountId, profileData) {
        return await this.request(`${this.baseURL}/user_profile.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'update_profile', accountId, ...profileData })
        });
    }
    
    async getUserTripStatus(accountId) {
        return await this.request(`${this.baseURL}/user_trip_status.php?accountId=${accountId}`);
    }
    
    async getVisaApplications(accountId, status = '', limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append('accountId', accountId);
        if (status) params.append('status', status);
        params.append('limit', limit);
        params.append('offset', offset);
        
        return await this.request(`${this.baseURL}/visa.php?${params.toString()}`);
    }
    
    async createVisaApplication(visaData) {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'create_visa_application', ...visaData })
        });
    }
    
    async getVisaApplication(visaApplicationId, accountId = null) {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_visa_application', visaApplicationId, accountId })
        });
    }
    
    async updateVisaApplication(visaApplicationId, updateData) {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'update_visa_application', visaApplicationId, updateData })
        });
    }
    
    async cancelVisaApplication(visaApplicationId, accountId = null, reason = '') {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'cancel_visa_application', visaApplicationId, accountId, reason })
        });
    }
    
    async uploadVisaDocument(visaApplicationId, documentData) {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'upload_document', visaApplicationId, ...documentData })
        });
    }
    
    async getVisaStatus(visaApplicationId) {
        return await this.request(`${this.baseURL}/visa.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_visa_status', visaApplicationId })
        });
    }
    
    async getInquiries(accountId, status = '', category = '', limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append('accountId', accountId);
        if (status) params.append('status', status);
        if (category) params.append('category', category);
        params.append('limit', limit);
        params.append('offset', offset);
        
        return await this.request(`${this.baseURL}/inquiry.php?${params.toString()}`);
    }
    
    async createInquiry(inquiryData) {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'create_inquiry', ...inquiryData })
        });
    }
    
    async getInquiry(inquiryId, accountId = null) {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_inquiry', inquiryId, accountId })
        });
    }
    
    async updateInquiry(inquiryId, updateData) {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'update_inquiry', inquiryId, updateData })
        });
    }
    
    async deleteInquiry(inquiryId, accountId) {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_inquiry', inquiryId, accountId })
        });
    }
    
    async replyInquiry(inquiryId, replyContent, repliedBy = 'admin') {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'reply_inquiry', inquiryId, replyContent, repliedBy })
        });
    }
    
    async getInquiryReplies(inquiryId) {
        return await this.request(`${this.baseURL}/inquiry.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_inquiry_replies', inquiryId })
        });
    }
    
    async getNotices(category = '', limit = 20, offset = 0, search = '') {
        const params = new URLSearchParams();
        if (category) params.append('category', category);
        params.append('limit', limit);
        params.append('offset', offset);
        if (search) params.append('search', search);
        
        return await this.request(`${this.baseURL}/notice.php?${params.toString()}`);
    }
    
    async getNotice(noticeId) {
        return await this.request(`${this.baseURL}/notice.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'get_notice', noticeId })
        });
    }
    
    async createNotice(noticeData) {
        return await this.request(`${this.baseURL}/notice.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'create_notice', ...noticeData })
        });
    }
    
    async updateNotice(noticeId, updateData) {
        return await this.request(`${this.baseURL}/notice.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'update_notice', noticeId, updateData })
        });
    }
    
    async deleteNotice(noticeId, authorId) {
        return await this.request(`${this.baseURL}/notice.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_notice', noticeId, authorId })
        });
    }
    
    async incrementNoticeViewCount(noticeId) {
        return await this.request(`${this.baseURL}/notice.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'increment_view_count', noticeId })
        });
    }
    
    async getCompanyInfo(type) {
        return await this.request(`${this.baseURL}/company-info.php?type=${type}`);
    }
    
    async getTerms() {
        return await this.request(`${this.baseURL}/company-info.php?type=terms`);
    }
    
    async getPrivacyPolicy() {
        return await this.request(`${this.baseURL}/company-info.php?type=privacy`);
    }
    
    async getCompanyIntro() {
        return await this.request(`${this.baseURL}/company-info.php?type=company`);
    }
    
    async getPartnershipInfo() {
        return await this.request(`${this.baseURL}/company-info.php?type=partnership`);
    }
    
    async getContactInfo() {
        return await this.request(`${this.baseURL}/company-info.php?type=contact`);
    }
}

//  API
window.api = new SmartTravelAPI();
//   (  `api`   )
const api = window.api;

// =========================
// ReactNativeWebView Push Token Bridge
// =========================
// - 로그인 성공 후 앱에 토큰 요청(REQUEST_PUSH_TOKEN)
// - 앱이 보내준 토큰(PUSH_TOKEN_RECEIVED) 수신
// - 서버(DB accounts)에 저장(backend/api/push-token.php)
(function initPushTokenBridge() {
    if (typeof window === 'undefined') return;
    if (window.__pushTokenBridgeInitialized) return;
    window.__pushTokenBridgeInitialized = true;

    function isAppWebView() {
        return !!window.ReactNativeWebView;
    }

    function normalizeExpoToken(t) {
        const s = String(t || '').trim();
        if (!s) return '';
        if (/^ExponentPushToken\[[^\]]+\]$/.test(s)) return s;
        const m1 = s.match(/^Expo(nent)?PushToken\[([^\]]+)\]$/);
        if (m1) return `ExponentPushToken[${m1[2]}]`;
        const m2 = s.match(/\[([^\]]+)\]/);
        if (m2) return `ExponentPushToken[${m2[1]}]`;
        return `ExponentPushToken[${s}]`;
    }

    async function saveExpoPushTokenToServer(token) {
        try {
            const expoPushToken = normalizeExpoToken(token);
            if (!expoPushToken) return false;

            const res = await fetch(api.endpoints.pushToken, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ token: expoPushToken })
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json && json.success) {
                try { localStorage.setItem('expoPushToken', expoPushToken); } catch (_) {}
                try { localStorage.setItem('expoPushTokenSavedAt', String(Date.now())); } catch (_) {}
                return true;
            }
            return false;
        } catch (_) {
            return false;
        }
    }

    function requestPushToken() {
        if (!isAppWebView()) return false;
        try {
            window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REQUEST_PUSH_TOKEN' }));
            return true;
        } catch (_) {
            return false;
        }
    }

    // expose for login.js (and any other flows)
    window.requestPushToken = requestPushToken;
    window.__saveExpoPushToken = saveExpoPushTokenToServer;

    function handleTokenMessage(data) {
        try {
            const msg = (typeof data === 'string') ? JSON.parse(data) : data;
            if (!msg || msg.type !== 'PUSH_TOKEN_RECEIVED' || !msg.token) return;
            const expoPushToken = normalizeExpoToken(msg.token);
            if (!expoPushToken) return;

            // Always store locally
            try { localStorage.setItem('expoPushToken', expoPushToken); } catch (_) {}

            // Save to server only when logged in (server will validate session)
            let isLoggedIn = false;
            try { isLoggedIn = localStorage.getItem('isLoggedIn') === 'true'; } catch (_) { isLoggedIn = false; }
            if (isLoggedIn) {
                saveExpoPushTokenToServer(expoPushToken);
            }
        } catch (_) {
            // ignore
        }
    }

    // Android/iOS WebView 호환성: window + document 둘 다 바인딩
    window.addEventListener('message', (event) => handleTokenMessage(event.data));
    try {
        document.addEventListener('message', (event) => handleTokenMessage(event.data));
    } catch (_) {}

    // 로그인 직후 페이지 전환으로 토큰 응답이 늦을 수 있어, 플래그가 있으면 재요청
    try {
        const pending = sessionStorage.getItem('pendingPushTokenRequest') === '1';
        if (pending) {
            setTimeout(() => requestPushToken(), 100);
            setTimeout(() => { try { sessionStorage.removeItem('pendingPushTokenRequest'); } catch (_) {} }, 4000);
        }
    } catch (_) {}
})();

//    API

//    () -   
// opts:
// - purchasableOnly:     
async function fetchPackages(category = '', limit = null, opts = {}) {
    try {
        const params = {};
        if (category) params.category = category;
        if (limit) params.limit = limit;
        if (opts && opts.purchasableOnly) params.purchasableOnly = 1;

        // B2B/B2C salesTarget 전달: accountType 기반
        // - accountType IN ('agent', 'admin') → B2B
        // - 그 외 → B2C (또는 미지정)
        try {
            const at = String(localStorage.getItem('accountType') || '').toLowerCase();
            if (at === 'agent' || at === 'admin') {
                params.salesTarget = 'B2B';
            }
        } catch (_) {}

        const result = await api.getPackages(params);
        
        if (result.success) {
            return result.data.packages || [];
        } else {
            console.error('  :', result.message);
            return [];
        }
    } catch (error) {
        console.error(' API :', error);
        return [];
    }
}

//   HTML 
function createPackageCard(package) {
    const price = new Intl.NumberFormat('ko-KR').format(package.packagePrice);
    // image  packageImage  
    const imageSrc = package.image || package.packageImage || '@img_card1.jpg';
    
    //    (       )
    let imageUrl;
    const origin = (typeof window !== 'undefined' && window.location && window.location.origin)
        ? window.location.origin
        : '';
    if (imageSrc.startsWith('http')) {
        //   URL   
        imageUrl = imageSrc;
    } else if (imageSrc.startsWith('../')) {
        //    ../images/ -> {origin}/images/  
        imageUrl = imageSrc.replace('../images/', `${origin}/images/`);
    } else if (imageSrc.startsWith('/')) {
        //     origin 
        imageUrl = `${origin}${imageSrc}`;
    } else {
        //   
        imageUrl = `${origin}/images/${imageSrc}`;
    }
    
    const rawId = package.packageId || package.productId || package.id;
    const pid = Number(rawId);
    const lang = (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'ko'));
    const onClick = (Number.isFinite(pid) && pid > 0)
        ? `location.href='user/product-detail.php?id=${pid}&lang=${encodeURIComponent(lang)}'`
        : `alert('   .');`;

    return `
        <li onclick="${onClick}">
            <div class="card-type1">
                <img src="${imageUrl}" alt="${package.packageName}">
                <div>
                    <div class="info mt4">${package.packageName}</div>
                    <p class="price mt6">₱ ${price}~</p>
                </div>
            </div>
        </li>
    `;
}

//    ()
async function renderPackages(containerSelector, category = '', limit = null) {
    const container = document.querySelector(containerSelector);
    if (!container) {
        console.error('   :', containerSelector);
        return;
    }
    
    try {
        //  
        container.innerHTML = '<div class="text-center">  ...</div>';
        
        const packages = await fetchPackages(category, limit);
        
        if (packages.length === 0) {
            //    :    
            container.innerHTML = '<div class="text-center"> .</div>';
            return;
        }
        
        //  HTML 
        const cardsHtml = packages.map(createPackageCard).join('');
        
        //  
        container.innerHTML = cardsHtml;
        
        console.log(`${category}   ${packages.length}  `);
        
    } catch (error) {
        console.error('  :', error);
        //    
        container.innerHTML = '<div class="text-center">  .</div>';
    }
}

//    
async function getUserBookings(userId) {
    try {
        const response = await fetch('./backend/api/bookings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_user_bookings',
                user_id: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            return { success: true, data: result.data };
        } else {
            console.error('  :', result.message);
            return { success: false, data: [] };
        }
    } catch (error) {
        console.error(' API :', error);
        return { success: false, data: [] };
    }
}

//    
function checkLoginStatus() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userEmail = localStorage.getItem('userEmail');
    const username = localStorage.getItem('username');
    
    return {
        isLoggedIn,
        userEmail,
        username
    };
}

//  
function handleLogout() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('userId');
    localStorage.removeItem('username');
    localStorage.removeItem('accountType');
    localStorage.removeItem('autoLogin');
    
    alert('.');
    location.href = 'user/login.html';
}

//    
document.addEventListener('DOMContentLoaded', function() {
    //    UI 
    const loginStatus = checkLoginStatus();
    
    //   
    const mypageLink = document.querySelector('.btn-mypage');
    if (mypageLink) {
        //  href    URL  
        const currentHref = mypageLink.getAttribute('href');
        // SMT   - visa-detail      
        const currentPath = window.location.pathname;
        const skipMypageLinkPages = [
            'visa-detail-inadequate.html',
            'visa-detail-examination.html',
            'visa-detail-completion.php',
            'visa-detail-rebellion.html'
        ];
        const shouldSkip = skipMypageLinkPages.some(page => currentPath.includes(page));
        // SMT  
        if (!shouldSkip && (!currentHref || currentHref === '#none' || currentHref.startsWith('#'))) {
            //      
            if (currentPath.includes('/user/')) {
                // user     
                if (loginStatus.isLoggedIn) {
                    mypageLink.href = 'mypage.html';
                } else {
                    mypageLink.href = 'login.html';
                }
            } else {
                //  user/  
                if (loginStatus.isLoggedIn) {
                    mypageLink.href = 'user/mypage.html';
                } else {
                    mypageLink.href = 'user/login.html';
                }
            }
        }
    }
    
    //    (  )
    // schedule.php guide-notice.html      
    const bellLink = document.querySelector('.btn-bell');
    if (bellLink) {
        const currentHref = bellLink.getAttribute('href');
        //  guide-notice     
        if (!currentHref || (!currentHref.includes('guide-notice') && currentHref === '#none')) {
            //      
            const currentPath = window.location.pathname;
            if (currentPath.includes('/user/')) {
                // user     
                if (loginStatus.isLoggedIn) {
                    bellLink.href = 'alarm.html';
                } else {
                    bellLink.href = 'login.html';
                }
            } else {
                //  user/  
                if (loginStatus.isLoggedIn) {
                    bellLink.href = 'user/alarm.html';
                } else {
                    bellLink.href = 'user/login.html';
                }
            }
        }
    }
    
});
