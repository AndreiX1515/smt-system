//   JavaScript

let userProfile = null;

//    
document.addEventListener("DOMContentLoaded", function() {
    initializeSettingPage();
});

//   
async function initializeSettingPage() {
    //   
    await loadServerTexts();
    
    //   
    updateLanguageDisplay();
    
    //   
    setupEventListeners();
    
    //    (  )
    await loadUserProfile();
}

//   
async function loadUserProfile() {
    try {
        const userId = localStorage.getItem('userId');
        if (!userId) return;
        
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
        if (result.success) {
            userProfile = result.profile;
        }
    } catch (error) {
        console.error('Failed to load user profile:', error);
    }
}

//   
function updateLanguageDisplay() {
    let selectedLanguage = getCurrentLanguage() || "en";
    if (selectedLanguage !== 'en' && selectedLanguage !== 'tl') selectedLanguage = 'en';
    const langTxt = document.querySelector(".lang-txt");
    const langButtons = document.querySelectorAll(".btn-language");
    
    if (langTxt) {
        switch(selectedLanguage) {
            case "en":
                langTxt.textContent = "English";
                break;
            case "tl":
                langTxt.textContent = "Tagalog";
                break;
            default:
                langTxt.textContent = "English";
        }
    }
    
    //     
    langButtons.forEach(button => {
        const lang = button.getAttribute('data-lang');
        if (lang === selectedLanguage) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
}

//   
function setupEventListeners() {
    //   
    const btnMore = document.querySelector(".btn-more");
    const closeLanguageModal = document.getElementById("closeLanguageModal");
    const languageLayer = document.getElementById("languageLayer");
    const languageModal = document.getElementById("languageModal");
    
    if (btnMore) {
        btnMore.addEventListener("click", function(e) {
            e.preventDefault();
            languageLayer.classList.add("active");
            languageModal.classList.add("active");
        });
    }
    
    if (closeLanguageModal) {
        closeLanguageModal.addEventListener("click", function() {
            languageLayer.classList.remove("active");
            languageModal.classList.remove("active");
        });
    }
    
    //   
    const langButtons = document.querySelectorAll(".btn-language");
    langButtons.forEach(button => {
        button.addEventListener("click", function() {
            let selectedLang = this.getAttribute('data-lang') || 'en';
            if (selectedLang !== 'en' && selectedLang !== 'tl') selectedLang = 'en';
            
            //   active  
            langButtons.forEach(btn => btn.classList.remove("active"));
            
            //   active  
            this.classList.add("active");
            
            //     
            changeLanguageWithServer(selectedLang);
            
            //   
            updateLanguageDisplay();
            
            //  
            languageLayer.classList.remove("active");
            languageModal.classList.remove("active");
        });
    });
    
    //
    const deleteCancelBtn = document.getElementById("deleteCancelBtn");
    const deleteConfirmBtn = document.getElementById("deleteConfirmBtn");
    
    if (deleteCancelBtn) {
        deleteCancelBtn.addEventListener("click", hideDeleteConfirmPopup);
    }
    
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener("click", confirmDeleteAccount);
    }
    
    //   
    const deleteRestrictOkBtn = document.getElementById("deleteRestrictOkBtn");
    if (deleteRestrictOkBtn) {
        deleteRestrictOkBtn.addEventListener("click", hideDeleteRestrictPopup);
    }
    
    //   
    const deleteCompleteOkBtn = document.getElementById("deleteCompleteOkBtn");
    if (deleteCompleteOkBtn) {
        deleteCompleteOkBtn.addEventListener("click", handleDeleteComplete);
    }
}

//
function showDeleteConfirmPopup() {
    const deleteConfirmLayer = document.getElementById("deleteConfirmLayer");
    const deleteConfirmPopup = document.getElementById("deleteConfirmPopup");
    if (deleteConfirmLayer && deleteConfirmPopup) {
        deleteConfirmLayer.style.display = 'block';
        deleteConfirmPopup.style.display = 'flex';
    }
}

//    
function hideDeleteConfirmPopup() {
    const deleteConfirmLayer = document.getElementById("deleteConfirmLayer");
    const deleteConfirmPopup = document.getElementById("deleteConfirmPopup");
    if (deleteConfirmLayer && deleteConfirmPopup) {
        deleteConfirmLayer.style.display = 'none';
        deleteConfirmPopup.style.display = 'none';
    }
}

//    
function showDeleteRestrictPopup() {
    hideDeleteConfirmPopup();
    const deleteRestrictLayer = document.getElementById("deleteRestrictLayer");
    const deleteRestrictPopup = document.getElementById("deleteRestrictPopup");
    if (deleteRestrictLayer && deleteRestrictPopup) {
        deleteRestrictLayer.style.display = 'block';
        deleteRestrictPopup.style.display = 'flex';
    }
}

//    
function hideDeleteRestrictPopup() {
    const deleteRestrictLayer = document.getElementById("deleteRestrictLayer");
    const deleteRestrictPopup = document.getElementById("deleteRestrictPopup");
    if (deleteRestrictLayer && deleteRestrictPopup) {
        deleteRestrictLayer.style.display = 'none';
        deleteRestrictPopup.style.display = 'none';
    }
}

//    
function showDeleteCompletePopup() {
    const deleteCompleteLayer = document.getElementById("deleteCompleteLayer");
    const deleteCompletePopup = document.getElementById("deleteCompletePopup");
    if (deleteCompleteLayer && deleteCompletePopup) {
        deleteCompleteLayer.style.display = 'block';
        deleteCompletePopup.style.display = 'flex';
    }
}

//   
function handleDeleteComplete() {
    hideDeleteCompletePopup();
    const currentLang = getCurrentLanguage();
    const safeLang = (currentLang === 'tl') ? 'tl' : 'en';
    const homeUrl = `../home.html?lang=${safeLang}`;
    window.location.href = homeUrl;
}

//   
function handleDeleteAccount() {
    showDeleteConfirmPopup();
}

//  
async function confirmDeleteAccount() {
    try {
        const userId = localStorage.getItem('userId');
        if (!userId) {
            alert(getI18nText('loginRequired') || ' .');
            return;
        }
        
        //   API  (   )
        const response = await fetch('../backend/api/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_account',
                user_id: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            hideDeleteConfirmPopup();
            
            //   
            localStorage.clear();
            
            //    
            showDeleteCompletePopup();
        } else if (result.hasActiveBookings) {
            //    
            hideDeleteConfirmPopup();
            showDeleteRestrictPopup();
        } else {
            alert(result.message || getI18nText('deleteAccountError') || '    .');
        }
    } catch (error) {
        console.error('Delete account error:', error);
        alert(getI18nText('deleteAccountError') || '    .');
    }
}

//    
function hideDeleteCompletePopup() {
    const deleteCompleteLayer = document.getElementById("deleteCompleteLayer");
    const deleteCompletePopup = document.getElementById("deleteCompletePopup");
    if (deleteCompleteLayer && deleteCompletePopup) {
        deleteCompleteLayer.style.display = 'none';
        deleteCompletePopup.style.display = 'none';
    }
}

//   
function getI18nText(key) {
    const currentLang = getCurrentLanguage();
    const texts = globalLanguageTexts[currentLang] || globalLanguageTexts.en;
    return texts[key] || key;
}

//   
window.handleDeleteAccount = handleDeleteAccount;
