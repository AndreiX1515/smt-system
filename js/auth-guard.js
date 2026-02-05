//   

//   
const PROTECTED_PAGES = [
    'edit-profile.html',
    'change-password.html',
    'reservation-history.php',
    'reservation-detail.php',
    'reservation-completed.php',
    'reservation-cancellation.html',
    'visa-history.html',
    'visa-detail-completion.php',
    'visa-detail-examination.html',
    'visa-detail-inadequate.html',
    'visa-detail-rebellion.html',
    'inquiry.html',
    'inquiry.php',
    'inquiry-detail.html',
    'inquiry-edit.html',
    'account-setting.html',
    'traveler-info-detail.html',
    'contact-agent.php'
];

//      
const GUEST_ONLY_PAGES = [
    'login.html',
    'join.html',
    'join.php',
    'find-id.html',
    'find-id.php',
    'find-id-result.html',
    'change-password1.html',
    'change-password2.html',
    'change-password2.php',
    'new-password.html'
];

//     
function isProtectedPage() {
    const currentPage = window.location.pathname.split('/').pop();
    return PROTECTED_PAGES.includes(currentPage);
}

//      
function isGuestOnlyPage() {
    const currentPage = window.location.pathname.split('/').pop();
    return GUEST_ONLY_PAGES.includes(currentPage);
}

//   
function checkAuthStatus() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userId = localStorage.getItem('userId');
    const userEmail = localStorage.getItem('userEmail');
    
    return {
        isLoggedIn: isLoggedIn && userId && userEmail,
        userId: userId,
        userEmail: userEmail,
        username: localStorage.getItem('username'),
        accountType: localStorage.getItem('accountType')
    };
}

//   
function executeAuthGuard() {
    const authStatus = checkAuthStatus();
    const currentPage = window.location.pathname.split('/').pop();
    
    //     
    if (isProtectedPage() && !authStatus.isLoggedIn) {
        handleUnauthorizedAccess();
        return false;
    }
    
    //      
    if (isGuestOnlyPage() && authStatus.isLoggedIn) {
        handleLoggedInUserAccess();
        return false;
    }
    
    //    (  )
    if (authStatus.isLoggedIn && isProtectedPage()) {
        validateSession();
    }
    
    return true;
}

//    
function handleUnauthorizedAccess() {
    //    (  )
    const currentUrl = window.location.href;
    sessionStorage.setItem('redirectAfterLogin', currentUrl);
    
    //  
    showAuthAlert(' .', () => {
        window.location.href = 'login.html';
    });
}

//      
function handleLoggedInUserAccess() {
    //   
    window.location.href = '../home.html';
}

//   
async function validateSession() {
    const userId = localStorage.getItem('userId');
    if (!userId) {
        handleSessionExpired();
        return;
    }
    
    //    ( )
    try {
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
        
        const result = await response.json();
        
        console.log('Session validation result:', result);
        
        if (!result.success) {
            console.log('Session validation failed, but continuing with localStorage check');
            //    localStorage     
            const localLogin = localStorage.getItem('isLoggedIn') === 'true';
            if (!localLogin) {
                handleSessionExpired();
            }
        }
        
    } catch (error) {
        console.error('Session validation error:', error);
        //      
    }
}

//   
function handleSessionExpired() {
    //   
    clearAuthData();
    
    //   
    const currentUrl = window.location.href;
    sessionStorage.setItem('redirectAfterLogin', currentUrl);
    
    //  
    showAuthAlert(' .  .', () => {
        window.location.href = 'login.html';
    });
}

//   
function clearAuthData() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('username');
    localStorage.removeItem('accountType');
    localStorage.removeItem('autoLogin');
}

//   
function showAuthAlert(message, callback) {
    //    ,  alert 
    if (typeof showModal === 'function') {
        showModal({
            title: '',
            message: message,
            confirmText: '',
            onConfirm: callback
        });
    } else {
        alert(message);
        if (callback) callback();
    }
}

//    
function handlePostLoginRedirect() {
    const redirectUrl = sessionStorage.getItem('redirectAfterLogin');
    if (redirectUrl) {
        sessionStorage.removeItem('redirectAfterLogin');
        window.location.href = redirectUrl;
        return true;
    }
    return false;
}

//   
function handleAutoLogin() {
    const autoLogin = localStorage.getItem('autoLogin');
    const userEmail = localStorage.getItem('userEmail');
    
    if (autoLogin === 'true' && userEmail) {
        //    
        localStorage.setItem('isLoggedIn', 'true');
    }
}

//    
function checkPageAccessLevel() {
    const authStatus = checkAuthStatus();
    const currentPage = window.location.pathname.split('/').pop();
    
    //    ( )
    const adminPages = [];
    
    //    ( )
    const agentPages = [];
    
    if (adminPages.includes(currentPage) && !['admin_ph', 'admin_kr'].includes(authStatus.accountType)) {
        showAuthAlert('  .', () => {
            history.back();
        });
        return false;
    }

    if (agentPages.includes(currentPage) && !['agent', 'admin_ph', 'admin_kr'].includes(authStatus.accountType)) {
        showAuthAlert('  .', () => {
            history.back();
        });
        return false;
    }
    
    return true;
}

//  
function initializeAuthGuard() {
    //   
    handleAutoLogin();
    
    //   
    if (!executeAuthGuard()) {
        return;
    }
    
    //    
    if (!checkPageAccessLevel()) {
        return;
    }
    
    //    
    if (window.location.pathname.includes('login.html')) {
        //     login.js 
    }
}

// DOM      
document.addEventListener('DOMContentLoaded', function() {
    initializeAuthGuard();
});

//      
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && isProtectedPage()) {
        const authStatus = checkAuthStatus();
        if (!authStatus.isLoggedIn) {
            handleUnauthorizedAccess();
        }
    }
});

//    (   )
window.addEventListener('storage', function(e) {
    if (e.key === 'isLoggedIn' && e.newValue === null) {
        if (isProtectedPage()) {
            handleSessionExpired();
        }
    }
});

//   
window.authGuard = {
    checkAuthStatus,
    executeAuthGuard,
    clearAuthData,
    handlePostLoginRedirect,
    isProtectedPage,
    isGuestOnlyPage
};