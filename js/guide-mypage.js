document.addEventListener('DOMContentLoaded', async () => {
    // user/guide-mypage.html 
    if (!window.location.pathname.includes('guide-mypage.html')) return;

    const welcomeEl = document.getElementById('guideWelcome');
    const meetingLink = document.getElementById('guideMeetingLink');
    const noticeLink = document.getElementById('guideNoticeLink');
    const logoutBtn = document.getElementById('guideLogoutButton');

    const lang =
        (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : null) ||
        localStorage.getItem('selectedLanguage') ||
        'ko';

    //  lang 
    const langParam = `?lang=${encodeURIComponent(lang)}`;
    if (meetingLink) meetingLink.href = `meeting-location.html${langParam}`;
    if (noticeLink) noticeLink.href = `notice-info.html${langParam}`;

    //  
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            try {
                logoutBtn.disabled = true;

                // backend/api/logout.php  user/admin   
                const res = await fetch('../backend/api/logout.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json().catch(() => ({}));

                //   ( )
                try { localStorage.removeItem('isLoggedIn'); } catch (_) {}
                try { localStorage.removeItem('returnUrl'); } catch (_) {}

                //      (    )
                const msg = (json && json.message) ? String(json.message) : '';
                if (msg) alert(msg);
            } catch (e) {
                console.error('Guide logout failed:', e);
            } finally {
                //     
                window.location.href = `login.html${langParam}`;
            }
        });
    }

    try {
        const res = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        if (!json?.success || !json?.isLoggedIn || !json?.user) {
            //    localStorage    
            // login.html "  "     .
            try { localStorage.removeItem('isLoggedIn'); } catch (_) {}
            try { localStorage.removeItem('userId'); } catch (_) {}
            try { localStorage.removeItem('userEmail'); } catch (_) {}
            try { localStorage.removeItem('username'); } catch (_) {}
            try { localStorage.removeItem('accountType'); } catch (_) {}
            window.location.href = `login.html${langParam}`;
            return;
        }

        const u = json.user || {};
        const name =
            String(u.displayName || '').trim() ||
            String(`${u.firstName || ''} ${u.lastName || ''}`).trim() ||
            String(u.username || '').trim() ||
            'User';

        // i18n    (welcomeMessage: "Welcome, {userName}!" )
        let tpl = null;
        try {
            const texts = (window.globalLanguageTexts && (globalLanguageTexts[lang] || globalLanguageTexts.ko)) || null;
            tpl = texts?.welcomeMessage || null;
        } catch (e) {}

        const msg = (tpl ? String(tpl) : (lang === 'en' ? 'Welcome, {userName}!' : ', {userName}!'))
            .replace('{userName}', name);

        if (welcomeEl) welcomeEl.textContent = msg;
    } catch (e) {
        //   UI    
        if (welcomeEl) welcomeEl.textContent = (lang === 'en') ? 'Welcome!' : '!';
    }
});


