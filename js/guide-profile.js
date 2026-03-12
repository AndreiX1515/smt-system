//    

let guideData = null;

//    
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('guide-profile.html')) {
        initializeGuideProfilePage();
    }
});

//    
async function initializeGuideProfilePage() {
    // URL   ID 
    const urlParams = new URLSearchParams(window.location.search);
    const guideId = urlParams.get('guideId');
    
    if (!guideId) {
        alert('    .');
        window.location.href = '../home.html';
        return;
    }
    
    //   
    await loadGuideProfile(guideId);
    
    //   
    renderGuideProfile();
    
    //   
    setupModalEvents();
}

//    
async function loadGuideProfile(guideId) {
    try {
        showLoadingState();
        
        const response = await fetch(`../backend/api/guide_profile.php?guideId=${encodeURIComponent(guideId)}`);
        const result = await response.json();
        
        if (result.success) {
            guideData = result.data;
        } else {
            showErrorState('   .');
        }
        
    } catch (error) {
        console.error('Failed to load guide profile:', error);
        showErrorState('   .');
    } finally {
        hideLoadingState();
    }
}

//   
function renderGuideProfile() {
    if (!guideData) return;
    
    //   
    updateProfileCard();
    
    //   
    updateProfileDetails();
    
    //  
    updateReviews();
}

//   
function updateProfileCard() {
    const profileImage = document.querySelector('.profile-image img');
    const nameElement = document.querySelector('.profile-info .name');
    const titleElement = document.querySelector('.profile-info .title');
    const ratingElement = document.querySelector('.rating-score');
    const starsElement = document.querySelector('.stars');
    
    if (profileImage) {
        profileImage.src = guideData.profileImage || '../images/@img_profile_guide.jpg';
        profileImage.alt = guideData.guideName;
    }
    
    if (nameElement) {
        nameElement.textContent = guideData.guideName;
    }
    
    if (titleElement) {
        titleElement.textContent = '  ';
    }
    
    if (ratingElement) {
        ratingElement.textContent = `${guideData.rating} (${guideData.total_reviews} )`;
    }
    
    if (starsElement) {
        const rating = Math.round(guideData.rating);
        starsElement.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.className = `star ${i <= rating ? 'active' : ''}`;
            star.textContent = '★';
            starsElement.appendChild(star);
        }
    }
}

//   
function updateProfileDetails() {
    // 
    const bioElement = document.querySelector('.detail-section p');
    if (bioElement) {
        bioElement.textContent = guideData.bio || '  .';
    }
    
    //  
    updateSpecialties();
    
    // 
    updateLanguages();
    
    // 
    updateExperience();
    
    // 
    updateCertifications();
}

//   
function updateSpecialties() {
    const specialtiesContainer = document.querySelector('.specialty-tags');
    if (specialtiesContainer && guideData.specialties) {
        specialtiesContainer.innerHTML = '';
        guideData.specialties.forEach(specialty => {
            const tag = document.createElement('span');
            tag.className = 'tag';
            tag.textContent = specialty;
            specialtiesContainer.appendChild(tag);
        });
    }
}

//  
function updateLanguages() {
    const languagesContainer = document.querySelector('.language-list');
    if (languagesContainer && guideData.languages) {
        languagesContainer.innerHTML = '';
        guideData.languages.forEach(language => {
            const languageItem = document.createElement('div');
            languageItem.className = 'language-item';
            
            const level = getLanguageLevel(language);
            languageItem.innerHTML = `
                <span class="language">${language}</span>
                <span class="level ${level.class}">${level.text}</span>
            `;
            
            languagesContainer.appendChild(languageItem);
        });
    }
}

//  
function updateExperience() {
    const experienceContainer = document.querySelector('.experience-list');
    if (experienceContainer) {
        experienceContainer.innerHTML = `
            <div class="experience-item">
                <div class="period">${new Date().getFullYear() - guideData.experience_years} - </div>
                <div class="company">Smart Travel  </div>
            </div>
            <div class="experience-item">
                <div class="period">${new Date().getFullYear() - guideData.experience_years - 2} - ${new Date().getFullYear() - guideData.experience_years}</div>
                <div class="company">${guideData.location}  </div>
            </div>
        `;
    }
}

//  
function updateCertifications() {
    const certificationsContainer = document.querySelector('.certification-list');
    if (certificationsContainer && guideData.certifications) {
        certificationsContainer.innerHTML = '';
        guideData.certifications.forEach(cert => {
            const li = document.createElement('li');
            li.textContent = cert;
            certificationsContainer.appendChild(li);
        });
    }
}

//  
function updateReviews() {
    const reviewsContainer = document.querySelector('.recent-reviews');
    if (reviewsContainer && guideData.recentReviews) {
        reviewsContainer.innerHTML = '';
        guideData.recentReviews.forEach(review => {
            const reviewItem = document.createElement('div');
            reviewItem.className = 'review-item';
            reviewItem.innerHTML = `
                <div class="review-header">
                    <div class="reviewer-name">${review.reviewerName}</div>
                    <div class="review-rating">
                        ${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}
                    </div>
                </div>
                <div class="review-content">
                    <h4>${review.title}</h4>
                    <p>${review.comment}</p>
                </div>
                <div class="review-date">${new Date(review.createdAt).toLocaleDateString('ko-KR')}</div>
            `;
            reviewsContainer.appendChild(reviewItem);
        });
    }
}

//   
function getLanguageLevel(language) {
    if (language === '') {
        return { class: 'native', text: 'Native' };
    } else if (language === 'English') {
        return { class: 'fluent', text: 'Fluent' };
    } else {
        return { class: 'intermediate', text: 'Intermediate' };
    }
}

//   
function setupModalEvents() {
    const closeButton = document.querySelector('.btn-close-modal');
    const layer = document.querySelector('.layer');
    const modal = document.querySelector('.profile-modal');
    
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
    
    if (layer) {
        layer.addEventListener('click', closeModal);
    }
    
    // ESC   
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

//  
function closeModal() {
    const layer = document.querySelector('.layer');
    const modal = document.querySelector('.profile-modal');
    
    if (layer) layer.classList.remove('active');
    if (modal) modal.classList.remove('active');
    
    // URL  ID 
    const url = new URL(window.location);
    url.searchParams.delete('guideId');
    window.history.replaceState({}, '', url);
}

//   
function showLoadingState() {
    const modal = document.querySelector('.profile-modal');
    if (modal) {
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-body" style="text-align: center; padding: 40px;">
                    <div class="text fz16 fw500 lh24 gray8">   ...</div>
                </div>
            </div>
        `;
    }
}

//   
function hideLoadingState() {
    //    
    if (guideData) {
        renderGuideProfile();
    }
}

//   
function showErrorState(message) {
    const modal = document.querySelector('.profile-modal');
    if (modal) {
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2></h2>
                    <button class="btn-close-modal" type="button">×</button>
                </div>
                <div class="modal-body" style="text-align: center; padding: 40px;">
                    <div class="text fz16 fw500 lh24 gray8">${message}</div>
                    <div class="mt16">
                        <button class="btn primary md" onclick="closeModal()"></button>
                    </div>
                </div>
            </div>
        `;
    }
}



