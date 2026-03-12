//    

//    
document.addEventListener('DOMContentLoaded', function() {
    const path = window.location.pathname;
    
    if (path.includes('terms.html')) {
        loadTerms();
    } else if (path.includes('company-intro.html')) {
        loadCompanyIntro();
    } else if (path.includes('partnership.html')) {
        loadPartnershipInfo();
    } else if (path.includes('privacy.html')) {
        loadPrivacyPolicy();
    } else if (path.includes('contact.html')) {
        loadContactInfo();
    }
});

//  
async function loadTerms() {
    try {
        showLoadingState();
        
        const result = await api.getTerms();
        
        if (result.success) {
            renderContent(result.data.content, result.data.updatedAt);
        } else {
            showError('  .');
        }
        
    } catch (error) {
        console.error('Load terms error:', error);
        showError('  .');
    } finally {
        hideLoadingState();
    }
}

//  
async function loadPrivacyPolicy() {
    try {
        showLoadingState();
        
        const result = await api.getPrivacyPolicy();
        
        if (result.success) {
            renderContent(result.data.content, result.data.updatedAt);
        } else {
            showError('  .');
        }
        
    } catch (error) {
        console.error('Load privacy policy error:', error);
        showError('  .');
    } finally {
        hideLoadingState();
    }
}

//   
async function loadCompanyIntro() {
    try {
        showLoadingState();
        
        const result = await api.getCompanyIntro();
        
        if (result.success) {
            renderContent(result.data.content, result.data.updatedAt);
        } else {
            showError('   .');
        }
        
    } catch (error) {
        console.error('Load company intro error:', error);
        showError('   .');
    } finally {
        hideLoadingState();
    }
}

//   
async function loadPartnershipInfo() {
    try {
        showLoadingState();
        
        const result = await api.getPartnershipInfo();
        
        if (result.success) {
            renderContent(result.data.content, result.data.updatedAt);
        } else {
            showError('   .');
        }
        
    } catch (error) {
        console.error('Load partnership info error:', error);
        showError('   .');
    } finally {
        hideLoadingState();
    }
}

//   
async function loadContactInfo() {
    try {
        showLoadingState();
        
        const result = await api.getContactInfo();
        
        if (result.success) {
            renderContent(result.data.content, result.data.updatedAt);
        } else {
            showError('   .');
        }
        
    } catch (error) {
        console.error('Load contact info error:', error);
        showError('   .');
    } finally {
        hideLoadingState();
    }
}

//  
function renderContent(content, updatedAt) {
    const container = document.querySelector('.px20.pb20.mt20') || 
                     document.querySelector('.px20.py20.mb85') ||
                     document.querySelector('.px20.pb20');
    
    if (!container) {
        console.error('Content container not found');
        return;
    }
    
    //   
    const updateInfo = document.createElement('div');
    updateInfo.className = 'text fz12 fw400 lh16 gray96 mb16';
    updateInfo.textContent = ` : ${new Date(updatedAt).toLocaleDateString('ko-KR')}`;
    
    //  
    const contentContainer = document.createElement('div');
    contentContainer.className = 'company-info-content';
    contentContainer.innerHTML = formatContent(content);
    
    //   
    const existingContent = container.querySelector('.company-info-content');
    if (existingContent) {
        existingContent.remove();
    }
    
    const existingUpdateInfo = container.querySelector('.update-info');
    if (existingUpdateInfo) {
        existingUpdateInfo.remove();
    }
    
    //   
    container.appendChild(updateInfo);
    container.appendChild(contentContainer);
}

//  
function formatContent(content) {
    // HTML   
    let formattedContent = content
        .replace(/<h2>/g, '<h2 class="text fz18 fw600 lh26 black12 mt24 mb12">')
        .replace(/<h3>/g, '<h3 class="text fz16 fw600 lh24 black12 mt20 mb8">')
        .replace(/<p>/g, '<p class="text fz14 fw400 lh22 black12 mb12">')
        .replace(/<ul>/g, '<ul class="text fz14 fw400 lh22 black12 mb12">')
        .replace(/<ol>/g, '<ol class="text fz14 fw400 lh22 black12 mb12">')
        .replace(/<li>/g, '<li class="mb4">')
        .replace(/<strong>/g, '<strong class="fw600">')
        .replace(/<em>/g, '<em class="fw500">');
    
    return formattedContent;
}

//   
function showLoadingState() {
    const container = document.querySelector('.px20.pb20.mt20') || 
                     document.querySelector('.px20.py20.mb85') ||
                     document.querySelector('.px20.pb20');
    
    if (!container) return;
    
    const loadingHtml = `
        <div class="loading-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 gray8">  ...</div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', loadingHtml);
}

//   
function hideLoadingState() {
    const loadingState = document.querySelector('.loading-state');
    if (loadingState) {
        loadingState.remove();
    }
}

//  
function showError(message) {
    const container = document.querySelector('.px20.pb20.mt20') || 
                     document.querySelector('.px20.py20.mb85') ||
                     document.querySelector('.px20.pb20');
    
    if (!container) return;
    
    const errorHtml = `
        <div class="error-state" style="text-align: center; padding: 40px 20px;">
            <div class="text fz16 fw500 lh24 reded">${message}</div>
            <button class="btn primary md mt16" onclick="location.reload()"> </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', errorHtml);
}

// CSS  
(function addCompanyInfoStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .company-info-content h2 {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }
        
        .company-info-content h3 {
            color: #333;
        }
        
        .company-info-content ul,
        .company-info-content ol {
            padding-left: 20px;
        }
        
        .company-info-content li {
            margin-bottom: 8px;
        }
        
        .company-info-content p {
            line-height: 1.6;
        }
        
        .loading-state,
        .error-state {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
    `;
    document.head.appendChild(style);
})();
