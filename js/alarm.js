//   

let notifications = [];
let filteredNotifications = [];
let currentPage = 0;
let currentCategory = 'entire'; // 'entire', 'reservation_schedule', 'visa'
const NOTIFICATIONS_PER_PAGE = 20;

//    
document.addEventListener('DOMContentLoaded', function() {
    // alarm.html alarm.php    
    if (window.location.pathname.includes('alarm.html') || window.location.pathname.includes('alarm.php')) {
        initializeAlarmPage();
    }
});

//   
async function initializeAlarmPage() {
    //   
    await loadServerTexts();
    
    //  
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (!isLoggedIn) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        alert(texts.loginRequired || ' .');
        location.href = 'login.html';
        return;
    }
    
    //   
    setupTabs();
    
    //   
    await loadNotifications();
    
    //     
    setupMarkAllAsReadButton();
}

//   
async function loadNotifications(page = 0, category = '') {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        console.error('User ID not found in localStorage');
        showEmptyState(' .');
        return;
    }
    
    try {
        showLoadingState();
        
        console.log('Loading notifications for userId:', userId, 'category:', category, 'page:', page);
        
        // API  -  
        const result = await api.getNotifications(userId, category, NOTIFICATIONS_PER_PAGE, page * NOTIFICATIONS_PER_PAGE);
        
        console.log('Notifications API response:', result);
        console.log('Result structure:', {
            success: result?.success,
            hasData: !!result?.data,
            hasNotifications: !!result?.data?.notifications,
            notificationsLength: result?.data?.notifications?.length,
            notificationsType: Array.isArray(result?.data?.notifications) ? 'array' : typeof result?.data?.notifications
        });
        
        if (result && result.success) {
            const notificationList = result.data && result.data.notifications ? result.data.notifications : [];
            
            console.log('Notification list received:', notificationList);
            console.log('Notification list length:', notificationList.length);
            
            if (page === 0) {
                notifications = notificationList;
            } else {
                notifications = [...notifications, ...notificationList];
            }
            
            console.log('Total notifications loaded:', notifications.length);
            
            //     
            if (notifications.length === 0) {
                const currentLang = getCurrentLanguage();
                const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
                showEmptyState(texts.noNotifications || ' .');
                return;
            }
            
            //    
            notifications.sort((a, b) => {
                const dateA = new Date(a.createdAt || a.created_at || 0);
                const dateB = new Date(b.createdAt || b.created_at || 0);
                return dateB - dateA; // 
            });
            
            filterAndRenderNotifications();
            
            //   /
            toggleLoadMoreButton(notificationList.length >= NOTIFICATIONS_PER_PAGE);
        } else {
            console.error('Failed to load notifications:', result);
            const errorMessage = result && result.message ? result.message : '  .';
            showEmptyState(errorMessage);
        }
        
    } catch (error) {
        console.error('Load notifications error:', error);
        showEmptyState('  : ' + error.message);
    } finally {
        hideLoadingState();
    }
}

//  
function setupTabs() {
    const tabs = document.querySelectorAll('.btn-alarmtab');
    
    tabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();

            //
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            //
            const href = String(tab.getAttribute('href') || '').trim();
            // NOTE: 이전 로직에 tab.textContent.includes('') (빈 문자열) 조건이 있어 항상 true가 되어
            // 어떤 탭을 눌러도 entire로만 처리되는 버그가 있었음. href(#id) 기준으로만 판별한다.
            let category = '';
            let containerId = 'entire';
            if (href === '#reservation_schedule') {
                category = 'reservation_schedule';
                containerId = 'reservation_schedule';
            } else if (href === '#visa') {
                category = 'visa';
                containerId = 'visa';
            } else {
                category = '';
                containerId = 'entire';
            }
            currentCategory = containerId;

            // NOTE: 탭 UI 표시(show/hide)는 js/tab.js가 이미 처리하고 있으므로 여기서는 건드리지 않는다.
            // 선택 탭은 서버 카테고리로 다시 로드(탭별 분리 보장 + legacy category 누락 row도 서버에서 보정)
            currentPage = 0;
            notifications = [];
            filteredNotifications = [];
            loadNotifications(0, category);
        });
    });
}

//    
function filterAndRenderNotifications() {
    if (currentCategory === 'entire') {
        filteredNotifications = [...notifications];
    } else {
        filteredNotifications = notifications.filter(n => {
            return n.category === currentCategory || 
                   (currentCategory === 'reservation_schedule' && (n.category === 'reservation_schedule' || n.type === 'booking' || n.type === 'payment')) ||
                   (currentCategory === 'visa' && (n.category === 'visa' || n.type === 'visa'));
        });
    }
    
    renderNotifications();
}

//   
function renderNotifications() {
    //     
    let container;
    switch(currentCategory) {
        case 'reservation_schedule':
            container = document.getElementById('reservation_schedule');
            break;
        case 'visa':
            container = document.getElementById('visa');
            break;
        default:
            container = document.getElementById('entire');
    }
    
    if (!container) {
        container = document.getElementById('entire');
    }
    
    if (!container) {
        console.error('Notification container not found. currentCategory:', currentCategory);
        return;
    }
    
    console.log('Rendering to container:', container.id, 'Display:', container.style.display);
    
    if (filteredNotifications.length === 0) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        showEmptyState(texts.noNotifications || ' .', container);
        return;
    }
    
    console.log('Rendering notifications:', filteredNotifications.length);
    const notificationsHtml = filteredNotifications.map((notification, index) => {
        try {
            const html = createNotificationItem(notification);
            if (!html) {
                console.warn(`Notification ${index} returned empty HTML:`, notification);
            }
            return html;
        } catch (error) {
            console.error('Error creating notification item:', error, notification);
            return '';
        }
    }).filter(html => html !== '').join('');
    
    console.log('Generated HTML length:', notificationsHtml.length);
    
    if (!notificationsHtml) {
        const currentLang = getCurrentLanguage();
        const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
        showEmptyState(texts.noNotifications || ' .', container);
        return;
    }
    
    container.innerHTML = notificationsHtml;
}

// 이미지 경로 변환 함수 (전역)
function getImagePath(path) {
    if (!path) return '';
    // 상대 경로를 절대 경로로 변환
    if (path.startsWith('../')) {
        // /user/alarm.php인 경우 ../images/는 /images/가 됨
        return path.replace('../images/', '/images/');
    }
    // 이미 절대 경로인 경우
    if (path.startsWith('/')) {
        return path;
    }
    return path;
}

//   HTML 
function createNotificationItem(notification) {
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    //   (YYYY-MM-DD)
    const sentDate = new Date(notification.createdAt);
    const formattedDate = sentDate.toLocaleDateString('en-CA'); // YYYY-MM-DD 
    
    // data ( object    )
    const notificationData = notification.data ? 
        (typeof notification.data === 'string' ? safeJsonParse(notification.data) : notification.data) : {};

    // /  //  ( )
    const normalized = buildAlarmPresentation(notification, notificationData);
    if (!normalized) {
        console.warn('buildAlarmPresentation returned null for notification:', {
            notificationId: notification.notificationId,
            type: notification.type,
            category: notification.category,
            title: notification.title,
            message: notification.message
        });
        // buildAlarmPresentation이 null을 반환해도 기본 정보로 표시
        return createDefaultNotificationItem(notification);
    }

    //   /
    // alarm.php는 /user/ 디렉토리에 있으므로 ../images/는 /images/를 가리킴
    // 예약/스케줄: 캘린더 아이콘, 비자: 문서 아이콘
    const iconSrc = (normalized.category === 'visa' || normalized.type === 'visa')
        ? '../images/ico_document_2.svg'
        : '../images/ico_calendar.svg';

    const packageId = notificationData.packageId || notificationData.productId || '';
    const productId = notificationData.productId || packageId || '';
    
    //  buildAlarmPresentation   
    let detailUrl = normalized.detailUrl || 'javascript:void(0)';
    if ((!detailUrl || detailUrl === 'javascript:void(0)') && notification.actionUrl) {
        detailUrl = notification.actionUrl;
    }
    
    //     ( ID,  ID, actionUrl   )
    const isClickable = (notification.relatedId || notification.actionUrl || normalized.detailUrl) && detailUrl !== 'javascript:void(0)';
    const clickHandler = isClickable ? `onclick="handleNotificationClick('${notification.notificationId || ''}', '${normalized.type || ''}', '${notification.relatedId || ''}', '${packageId || productId || ''}', '${detailUrl}', '${normalized.statusKey || ''}'); return false;"` : '';
    const cursorStyle = isClickable ? 'cursor: pointer;' : '';
    
    return `
        <li class="border-bottomf2 px20 py20" ${clickHandler} style="${cursorStyle}">
            <div class="align vm gap12">
                <img src="${getImagePath(iconSrc)}" alt="" class="img-alarm" onerror="this.onerror=null; this.src='${getImagePath('../images/ico_bell.svg')}';">
                <div class="flex1">
                    <div class="text fz16 fw600 lh24 black12 ">${escapeHtml(normalized.title || notification.title || '')}</div>
                    <p class="text fz14 fw400 lh22 gray4e mt4">${escapeHtml(normalized.message || notification.message || '')}</p>
                    <div class="align both vm">
                        <div class="text fz12 fw500 lh16 grayb0">${formattedDate}</div>
                        <a class="text fz12 fw500 lh16 reded" href="${detailUrl}" onclick="event.stopPropagation(); handleNotificationClick('${notification.notificationId || ''}', '${normalized.type || ''}', '${notification.relatedId || ''}', '${packageId || productId || ''}', '${detailUrl}', '${normalized.statusKey || ''}'); return false;">See more</a>
                    </div>
                </div>
            </div>
        </li>
    `;
}

//   
async function handleNotificationClick(notificationId, type, relatedId, productId = '', detailUrl = '', statusKey = '') {
    if (notificationId && notificationId !== '') {
        //   (   )
        markNotificationAsRead(notificationId).catch(err => console.error('Mark as read error:', err));
    }
    
    // detailUrl   ,  redirectToRelatedPage 
    if (detailUrl && detailUrl !== '' && detailUrl !== 'javascript:void(0)') {
        //      ()
        if (detailUrl.startsWith('product-detail.php') || detailUrl.startsWith('reservation-detail.php') || detailUrl.startsWith('visa-detail-completion.php') || detailUrl.startsWith('inquiry-detail.html')) {
            location.href = detailUrl;
        } else if (detailUrl.startsWith('http://') || detailUrl.startsWith('https://')) {
            location.href = detailUrl;
        } else {
            location.href = detailUrl;
        }
    } else {
        //   
        redirectToRelatedPage(type, relatedId, productId, statusKey);
    }
}

//   
async function markNotificationAsRead(notificationId) {
    const userId = localStorage.getItem('userId');
    if (!userId) return;
    
    try {
        const result = await api.markNotificationAsRead(userId, notificationId);
        
        if (result.success) {
            // UI   
            const notification = notifications.find(n => n.notificationId == notificationId);
            if (notification) {
                notification.isRead = true;
            }
            
            //      
            const notificationElement = document.querySelector(`li[onclick*="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread');
                notificationElement.classList.add('read');
                notificationElement.querySelector('.notification-unread')?.classList.remove('notification-unread');
                notificationElement.querySelector('.unread-dot')?.remove();
            }
        }
        
    } catch (error) {
        console.error('Mark as read error:', error);
    }
}

//   
function redirectToRelatedPage(type, relatedId, productId = '', statusKey = '') {
    //  ID  product-detail.php 
    if (productId && productId !== '') {
        location.href = `product-detail.php?id=${productId}`;
        return;
    }
    
    // relatedId   
    if (!relatedId || relatedId === '') {
        return;
    }
    
    switch (type) {
        case 'booking':
        case 'payment':
        case 'reservation':
        case 'reservation_schedule':
            //     
            location.href = `reservation-detail.php?id=${relatedId}`;
            break;
        case 'visa':
            //     
            location.href = `${visaDetailPathByStatus(statusKey)}?id=${relatedId}`;
            break;
        case 'inquiry':
            // inquiry-detail.js inquiryId  inquiry_id .
            location.href = `inquiry-detail.html?inquiryId=${encodeURIComponent(relatedId)}`;
            break;
        case 'product':
        case 'package':
        case 'promotional':
            //      (productId )
            if (productId) {
                location.href = `product-detail.php?id=${productId}`;
            } else if (relatedId) {
                // relatedId packageId  
                location.href = `product-detail.php?id=${relatedId}`;
            }
            break;
        default:
            //      
            if (relatedId) {
                location.href = `reservation-detail.php?id=${relatedId}`;
            }
            break;
    }
}

function safeJsonParse(str) {
    try { return JSON.parse(str); } catch { return {}; }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

// buildAlarmPresentation이 null을 반환할 때 사용하는 기본 알림 아이템 생성
function createDefaultNotificationItem(notification) {
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const sentDate = new Date(notification.createdAt || notification.created_at || Date.now());
    const formattedDate = sentDate.toLocaleDateString('en-CA');
    
    const title = notification.title || texts.notification || 'Notification';
    const message = notification.message || '';
    const detailUrl = notification.actionUrl || 'javascript:void(0)';
    const isClickable = !!notification.actionUrl && notification.actionUrl !== 'javascript:void(0)';
    const clickHandler = isClickable ? `onclick="handleNotificationClick('${notification.notificationId || ''}', '${notification.type || ''}', '${notification.relatedId || ''}', '', '${detailUrl}', ''); return false;"` : '';
    const cursorStyle = isClickable ? 'cursor: pointer;' : '';
    
    return `
        <li class="border-bottomf2 px20 py20" ${clickHandler} style="${cursorStyle}">
            <div class="align vm gap12">
                <img src="${getImagePath('../images/ico_bell.svg')}" alt="" class="img-alarm" onerror="this.onerror=null; this.src='${getImagePath('../images/ico_bell.svg')}';">
                <div class="flex1">
                    <div class="text fz16 fw600 lh24 black12 ">${escapeHtml(title)}</div>
                    <p class="text fz14 fw400 lh22 gray4e mt4">${escapeHtml(message)}</p>
                    <div class="align both vm">
                        <div class="text fz12 fw500 lh16 grayb0">${formattedDate}</div>
                        ${isClickable ? `<a class="text fz12 fw500 lh16 reded" href="${detailUrl}" onclick="event.stopPropagation(); handleNotificationClick('${notification.notificationId || ''}', '${notification.type || ''}', '${notification.relatedId || ''}', '', '${detailUrl}', ''); return false;">See more</a>` : ''}
                    </div>
                </div>
            </div>
        </li>
    `;
}

function visaDetailPathByStatus(statusKey) {
    //    
    const k = String(statusKey || '').toLowerCase();
    if (k === 'incomplete' || k === 'incomplete_documents' || k === 'document_required') return 'visa-detail-inadequate.html';
    if (k === 'under_review' || k === 'reviewing') return 'visa-detail-examination.html';
    if (k === 'issuance_completed' || k === 'approved' || k === 'completed') return 'visa-detail-completion.php';
    if (k === 'rejected' || k === 'returned') return 'visa-detail-rebellion.html';
    // 
    return 'visa-detail-completion.php';
}

function buildAlarmPresentation(notification, data) {
    const type = (notification.type || notification.notificationType || '').toLowerCase();
    const category = (notification.category || '').toLowerCase();
    const actionUrl = (notification.actionUrl || '').trim();

    // statusKey 
    let statusKey = (data.statusKey || data.status || data.bookingStatus || data.paymentStatus || data.visaStatus || '').toString();

    //   (B2B/B2C)
    const bookingId = data.bookingId || data.booking_id || notification.relatedId || '';
    const isBooking = category === 'reservation_schedule' || ['booking','payment','reservation','reservation_schedule'].includes(type);
    const isVisa = category === 'visa' || type === 'visa';

    //  data/statusKey       
    if (!statusKey) {
        const blob = `${notification.title || ''} ${notification.message || ''}`.toLowerCase();
        if (isVisa) {
            // Incomplete documents (ko/en  )
            if ((blob.includes('') && blob.includes('')) || blob.includes('incomplete') || blob.includes('document required') || blob.includes('documents')) {
                statusKey = 'document_required';
            }
            else if (blob.includes('') || blob.includes('review')) statusKey = 'under_review';
            else if (blob.includes('') || blob.includes('') || blob.includes('issued') || blob.includes('approved')) statusKey = 'approved';
            else if (blob.includes('') || blob.includes('rejected') || blob.includes('returned')) statusKey = 'rejected';
        } else if (isBooking) {
            if (blob.includes('')) statusKey = 'temporary_save';
            else if (blob.includes('') || blob.includes('pending')) statusKey = 'pending';
            else if (blob.includes('') || blob.includes('confirmed')) statusKey = 'confirmed';
            else if (blob.includes('') || blob.includes('canceled') || blob.includes('cancelled')) statusKey = 'canceled';
            else if (blob.includes('') || blob.includes('refund')) statusKey = 'refund_completed';
            else if (blob.includes('') && blob.includes('')) statusKey = 'travel_completed';
            else if (blob.includes('') && blob.includes('')) statusKey = 'completed';
        }
    }

    //  
    const isDepartureReminder = (type === 'reminder') || /departure/i.test(notification.title || '') || /departure/i.test(notification.message || '');
    if (isDepartureReminder) {
        const dep = data.departureDate ? new Date(data.departureDate) : null;
        let days = data.daysRemaining;
        if (typeof days !== 'number' && dep instanceof Date && !isNaN(dep)) {
            const today = new Date();
            today.setHours(0,0,0,0);
            dep.setHours(0,0,0,0);
            days = Math.max(0, Math.round((dep - today) / (1000*60*60*24)));
        }

        // :   7  
        const windowDays = (typeof data.reminderWindowDays === 'number' && isFinite(data.reminderWindowDays))
            ? data.reminderWindowDays
            : 7;
        const computedDays = (typeof days === 'number' && isFinite(days)) ? days : 1;

        const statusBlob = String(
            data.bookingStatus ||
            data.reservationStatus ||
            data.paymentStatus ||
            data.statusKey ||
            data.status ||
            ''
        ).toLowerCase();
        const isConfirmed =
            statusBlob.includes('confirmed') ||
            statusBlob.includes('reservation_confirmed') ||
            statusBlob.includes('payment_completed') ||
            statusBlob === 'paid' ||
            statusBlob === 'completed';

        //    ,       
        if (!isConfirmed) return null;
        if (computedDays < 0) return null;
        if (computedDays > windowDays) return null;

        const msgDays = computedDays;
        return {
            type: 'reservation_schedule',
            category: 'reservation_schedule',
            title: 'Departure Reminder',
            message: `The product's departure date is ${msgDays} day away.`,
            detailUrl: bookingId ? `reservation-detail.php?id=${bookingId}` : (actionUrl || 'javascript:void(0)'),
            statusKey: 'departure_reminder'
        };
    }

    if (isVisa) {
        //    
        const k = statusKey.toLowerCase();
        let title = 'Under review';
        if (['incomplete','incomplete_documents','document_required'].includes(k)) title = 'Incomplete documents';
        else if (['under_review','reviewing'].includes(k)) title = 'Under review';
        else if (['issuance_completed','approved','completed'].includes(k)) title = 'Issuance completed';
        else if (['rejected','returned'].includes(k)) title = 'Rejected';

        const visaId = data.visaApplicationId || data.applicationId || notification.relatedId || '';
        return {
            type: 'visa',
            category: 'visa',
            title,
            message: 'Your visa application status has been changed.',
            detailUrl: visaId ? `${visaDetailPathByStatus(k)}?id=${visaId}` : (actionUrl || 'javascript:void(0)'),
            statusKey: k
        };
    }

    if (isBooking) {
        const rawKey = String(statusKey || '').toLowerCase();

        // B2B/B2C (    : clientType/accountType/bookingType)
        const hint = (data.clientType || data.bookingType || data.accountType || data.userType || '').toString().toLowerCase();
        const isB2B = !!(data.isB2B) ||
            hint.includes('b2b') ||
            hint.includes('wholeseller') || hint.includes('wholesaler') ||
            hint.includes('agent');

        // DB/      
        const normalizeBookingKey = (k) => {
            const key = String(k || '').toLowerCase();
            if (!key) return '';

            // paymentStatus 
            if (['paid', 'payment_completed', 'paymentconfirmed', 'payment_confirmed'].includes(key)) {
                return isB2B ? 'confirmed' : 'completed';
            }
            if (['cancelled', 'canceled', 'payment_canceled', 'payment_cancelled'].includes(key)) {
                return 'canceled';
            }
            if (['refunded', 'refund_completed', 'refund', 'refund_complete'].includes(key)) {
                return 'refund_completed';
            }

            // bookingStatus 
            if (['pending', 'reservation_pending'].includes(key)) return 'pending';
            if (['confirmed', 'reservation_confirmed'].includes(key)) return 'confirmed';
            if (['completed', 'travel_completed', 'trip_completed'].includes(key)) return 'travel_completed';
            if (['temporary_save', 'temporary', 'draft', 'temp_save', 'saved_draft'].includes(key)) return 'temporary_save';

            //    
            if (['canceled','refund_completed','travel_completed','temporary_save'].includes(key)) return key;

            return key;
        };

        const k = normalizeBookingKey(rawKey);

        //  /  " "       
        if (k === 'guide_notice') {
            return {
                type: 'reservation_schedule',
                category: 'guide_notice',
                title: 'Guide Notice',
                message: 'A guide notice has been posted.',
                detailUrl: bookingId ? `guide-notice.html?booking_id=${bookingId}` : (actionUrl || 'javascript:void(0)'),
                statusKey: k
            };
        }
        if (k === 'meeting_location' || k === 'guide_location') {
            return {
                type: 'reservation_schedule',
                category: 'guide_notice',
                title: 'Guide Location',
                message: 'A new meeting location has been posted.',
                detailUrl: bookingId ? `guide-location.php?booking_id=${bookingId}` : (actionUrl || 'javascript:void(0)'),
                statusKey: k
            };
        }

        // :  () 
        // - B2B: Reservation pending/confirmed/canceled/Refund completed/Travel completed/Temporary save
        // - B2C: Payment Suspended/Payment Completed/Payment Canceled/Refund Completed/Trip Completed/Temporary save
        let title = isB2B ? 'Reservation confirmed' : 'Payment Completed';
        if (isB2B) {
            const map = {
                pending: 'Reservation pending',
                confirmed: 'Reservation confirmed',
                canceled: 'Reservation canceled',
                cancelled: 'Reservation canceled',
                refund_completed: 'Refund completed',
                travel_completed: 'Travel completed',
                temporary_save: 'Temporary save'
            };
            title = map[k] || title;
        } else {
            const map = {
                payment_suspended: 'Payment Suspended',
                suspended: 'Payment Suspended',
                pending: 'Payment Suspended',
                completed: 'Payment Completed',
                confirmed: 'Payment Completed',
                paid: 'Payment Completed',
                canceled: 'Payment Canceled',
                cancelled: 'Payment Canceled',
                refund_completed: 'Refund Completed',
                travel_completed: 'Trip Completed',
                temporary_save: 'Temporary save'
            };
            title = map[k] || title;
        }

        return {
            type: 'reservation_schedule',
            category: 'reservation_schedule',
            title,
            message: "The product's reservation status has been changed.",
            detailUrl: bookingId ? `reservation-detail.php?id=${bookingId}` : (actionUrl || 'javascript:void(0)'),
            statusKey: k
        };
    }

    // :    + actionUrl 
    return {
        type: type || 'general',
        category: category || 'general',
        title: notification.title || '',
        message: notification.message || '',
        detailUrl: actionUrl || 'javascript:void(0)',
        statusKey: statusKey || ''
    };
}

//   
async function markAllAsRead() {
    const userId = localStorage.getItem('userId');
    if (!userId) return;
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const confirmMessage = texts.markAllAsReadConfirm || '   ?';
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        const result = await api.markAllNotificationsAsRead(userId);
        
        if (result.success) {
            //     
            notifications.forEach(notification => {
                notification.isRead = true;
            });
            
            // UI 
            renderNotifications();
            
            const successMessage = texts.allNotificationsMarkedRead || '   .';
            alert(successMessage);
        } else {
            const errorMessage = texts.markAsReadFailed || '  .';
            alert(errorMessage);
        }
        
    } catch (error) {
        console.error('Mark all as read error:', error);
        const errorMessage = texts.markAsReadError || '    .';
        alert(errorMessage);
    }
}

//     
function setupMarkAllAsReadButton() {
    //       
    const container = document.querySelector('.px20') || document.querySelector('.main');
    if (!container) return;
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    const buttonText = texts.markAllAsRead || ' ';
    
    const markAllButton = document.createElement('div');
    markAllButton.className = 'mark-all-container';
    markAllButton.innerHTML = `
        <button class="btn line md mark-all-btn" onclick="markAllAsRead()" data-i18n="markAllAsRead">
            ${buttonText}
        </button>
    `;
    
    //   
    const header = container.querySelector('header') || container.firstElementChild;
    if (header) {
        header.parentNode.insertBefore(markAllButton, header.nextSibling);
    }
}

//  
async function loadMoreNotifications() {
    currentPage++;
    const category = currentCategory === 'entire' ? '' : currentCategory;
    await loadNotifications(currentPage, category);
}

//   /
function toggleLoadMoreButton(hasMore) {
    //     
    const containerId = currentCategory === 'entire' ? 'entire' : 
                        currentCategory === 'reservation_schedule' ? 'reservation_schedule' : 'visa';
    const container = document.getElementById(containerId);
    
    if (!container) return;
    
    let loadMoreBtn = container.querySelector('.load-more-btn');
    
    if (hasMore) {
        if (!loadMoreBtn) {
            const loadMoreContainer = document.createElement('div');
            loadMoreContainer.className = 'load-more-container';
            loadMoreContainer.style.cssText = 'text-align: center; padding: 20px 0;';
            loadMoreContainer.innerHTML = `
                <button class="btn line lg load-more-btn" onclick="loadMoreNotifications()">
                    
                </button>
            `;
            container.appendChild(loadMoreContainer);
            loadMoreBtn = container.querySelector('.load-more-btn');
        }
        loadMoreBtn.closest('.load-more-container').style.display = 'block';
    } else {
        const loadMoreContainer = container.querySelector('.load-more-container');
        if (loadMoreContainer) {
            loadMoreContainer.style.display = 'none';
        }
    }
}

//   
function getNotificationTypeIcon(type) {
    const iconMap = {
        'booking': 'ico_calendar.svg',
        'payment': 'ico_payment.svg',
        'inquiry': 'ico_inquiry.svg',
        'system': 'ico_system.svg',
        'general': 'ico_bell.svg'
    };
    return iconMap[type] || 'ico_bell.svg';
}

//   CSS 
function getNotificationTypeClass(type) {
    const classMap = {
        'booking': 'notification-booking',
        'payment': 'notification-payment',
        'inquiry': 'notification-inquiry',
        'system': 'notification-system',
        'general': 'notification-general'
    };
    return classMap[type] || 'notification-general';
}

//   
function showLoadingState() {
    const container = document.getElementById(currentCategory === 'entire' ? 'entire' : 
                                               currentCategory === 'reservation_schedule' ? 'reservation_schedule' : 'visa');
    if (!container) return;
    
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.ko;
    
    const loadingHtml = `
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">${texts.loading || '  ...'}</div>
        </div>
    `;
    
    container.innerHTML = loadingHtml;
}

//   
function hideLoadingState() {
    const loadingState = document.querySelector('.loading-state');
    if (loadingState) {
        loadingState.remove();
    }
}

//   
function showEmptyState(message = ' .', container = null) {
    if (!container) {
        container = document.getElementById(currentCategory === 'entire' ? 'entire' : 
                                           currentCategory === 'reservation_schedule' ? 'reservation_schedule' : 'visa');
    }
    
    if (!container) return;
    
    const emptyStateHtml = `
        <div class="empty-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">${message}</div>
        </div>
    `;
    
    container.innerHTML = emptyStateHtml;
}

// CSS  
(function addAlarmStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .notification-unread {
            background-color: #f8f9ff;
            border-left: 3px solid #007bff;
        }
        
        .unread-dot {
            width: 8px;
            height: 8px;
            background-color: #ff4757;
            border-radius: 50%;
            margin-left: auto;
        }
        
        .notification-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-booking {
            background-color: #e3f2fd;
        }
        
        .notification-payment {
            background-color: #e8f5e8;
        }
        
        .notification-inquiry {
            background-color: #fff3e0;
        }
        
        .notification-system {
            background-color: #f3e5f5;
        }
        
        .notification-general {
            background-color: #f5f5f5;
        }
        
        .mark-all-container {
            text-align: right;
            padding: 16px 0;
        }
        
        .load-more-container {
            text-align: center;
            padding: 20px 0;
        }
    `;
    document.head.appendChild(style);
})();