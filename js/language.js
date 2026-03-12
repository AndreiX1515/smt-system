document.addEventListener("DOMContentLoaded", function () {
    const languageButtons = document.querySelectorAll(".btn-language");
    const continueBtn = document.getElementById("continueBtn");
    let selectedLanguage = "en"; //   

    // NO Korean policy: only en/tl are supported. Default is en.
    try {
        const cur = localStorage.getItem('selectedLanguage');
        if (cur !== 'en' && cur !== 'tl') localStorage.setItem('selectedLanguage', 'en');
    } catch (_) {}

    //  (  +  )  ,
    //    /       .
    try {
        const languageOnboarded = localStorage.getItem('languageOnboarded') === '1';
        const permitConfirmed = localStorage.getItem('permitConfirmed') === '1';
        // SMT :
        // - "     " :
        //   permitConfirmed=1(   )      .
        // - selectedLanguage    redirect  ('en') .
        const savedLanguageForRedirect = localStorage.getItem("selectedLanguage");
        const redirectLang = savedLanguageForRedirect || "en";
        const autoLogin = localStorage.getItem('autoLogin') === 'true';
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

        //           .
        const shouldSkipLanguageOnboarding =
            languageOnboarded ||
            permitConfirmed ||
            ((autoLogin || isLoggedIn) && !!savedLanguageForRedirect);

        if (shouldSkipLanguageOnboarding) {
            if (permitConfirmed) {
                location.replace(`home.html?lang=${encodeURIComponent(redirectLang)}`);
                return;
            }
            location.replace(`user/permit.html?lang=${encodeURIComponent(redirectLang)}`);
            return;
        }
    } catch (_) {
        // ignore
    }

    //    
    languageButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            e.preventDefault();
            
            //   active  
            languageButtons.forEach(btn => btn.classList.remove("active"));
            
            //   active  
            this.classList.add("active");
            
            //   
            selectedLanguage = this.getAttribute("data-lang");
            if (selectedLanguage !== 'en' && selectedLanguage !== 'tl') selectedLanguage = 'en';

            //     
            localStorage.setItem("selectedLanguage", selectedLanguage);

            //      
            updateLanguageTexts(selectedLanguage);

            console.log("Selected language:", selectedLanguage);
        });
    });

    //       
    const savedLanguage = localStorage.getItem("selectedLanguage");
    if (savedLanguage) {
        selectedLanguage = (savedLanguage === 'en' || savedLanguage === 'tl') ? savedLanguage : 'en';
        try { localStorage.setItem("selectedLanguage", selectedLanguage); } catch (_) {}

        //    active 
        languageButtons.forEach(btn => {
            btn.classList.remove("active");
            if (btn.getAttribute("data-lang") === savedLanguage) {
                btn.classList.add("active");
            }
        });
    } else {
        //    (en) UI  (   localStorage   )
        selectedLanguage = "en";
        
        // English  active 
        languageButtons.forEach(btn => {
            btn.classList.remove("active");
            if (btn.getAttribute("data-lang") === "en") {
                btn.classList.add("active");
            }
        });
    }

    //       
    updateLanguageTexts(selectedLanguage);

    //          
    if (continueBtn) {
        continueBtn.addEventListener("click", function() {
            //   
            if (!selectedLanguage) {
                const texts = languageTexts[getCurrentLanguage()] || languageTexts.en;
                alert(texts.selectLanguage);
                return;
            }

            //     
            localStorage.setItem("selectedLanguage", selectedLanguage);
            //     
            localStorage.setItem("languageOnboarded", "1");

            const permitConfirmed = localStorage.getItem('permitConfirmed') === '1';
            if (permitConfirmed) {
                console.log("Moving to home with language:", selectedLanguage);
                location.href = `home.html?lang=${encodeURIComponent(selectedLanguage)}`;
                return;
            }

            console.log("Moving to permit page with language:", selectedLanguage);
            location.href = `user/permit.html?lang=${encodeURIComponent(selectedLanguage)}`;
        });
    }
});

//     
function getSelectedLanguage() {
    const lang = localStorage.getItem("selectedLanguage");
    return (lang === 'en' || lang === 'tl') ? lang : "en";
}

//   
const languageTexts = {
    en: {
        selectLanguage: "Please select your language.",
        continue: "Continue",
        english: "English",
        tagalog: "Tagalog",
        //  UI 
        home: "Home",
        mypage: "My Page",
        reservation: "Reservation",
        schedule: "Schedule",
        profile: "Profile",
        settings: "Settings",
        logout: "Logout"
    },
    tl: {
        selectLanguage: "Piliin ang inyong wika.",
        continue: "Magpatuloy",
        english: "English",
        tagalog: "Tagalog",
        //  UI 
        home: "Tahanan",
        mypage: "Aking Pahina",
        reservation: "Reserbasyon",
        schedule: "Iskedyul",
        profile: "Profile",
        settings: "Mga Setting",
        logout: "Mag-logout"
    }
};

//     
function updateLanguageTexts(lang) {
    if (lang !== 'en' && lang !== 'tl') lang = 'en';
    const texts = languageTexts[lang] || languageTexts.en;

    //   
    const selectLanguageText = document.querySelector(".text.fz20");
    if (selectLanguageText) {
        selectLanguageText.textContent = texts.selectLanguage;
    }

    const continueButton = document.getElementById("continueBtn");
    if (continueButton) {
        continueButton.textContent = texts.continue;
    }
}

//     (  )
function changeLanguage(lang) {
    if (lang !== 'en' && lang !== 'tl') lang = 'en';
    localStorage.setItem("selectedLanguage", lang);
    if (typeof updatePageLanguage === 'function') {
        updatePageLanguage(lang);
    }
    location.reload(); //     
}

//   
function getCurrentLanguage() {
    const lang = localStorage.getItem("selectedLanguage");
    return (lang === 'en' || lang === 'tl') ? lang : "en";
}