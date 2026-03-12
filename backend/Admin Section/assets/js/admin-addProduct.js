//   JavaScript

let productData = {};

// DOM    
document.addEventListener('DOMContentLoaded', function() {
    initializeAddProduct();
    setupEventListeners();
});

// 
function initializeAddProduct() {
    console.log('   ');

    //   case  
    showCase('case1');
}

//   
function setupEventListeners() {
    // Case  
    document.querySelectorAll('.case-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const caseId = this.getAttribute('data-case');
            showCase(caseId);
        });
    });
}

// Case 
function showCase(caseId) {
    //    
    document.querySelectorAll('.case-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.case-content').forEach(content => {
        content.classList.remove('active');
    });

    //    
    const selectedTab = document.querySelector(`[data-case="${caseId}"]`);
    const selectedContent = document.getElementById(caseId);

    if (selectedTab) selectedTab.classList.add('active');
    if (selectedContent) selectedContent.classList.add('active');
}

// Step 
function goToStep1() {
    document.getElementById('step1').style.display = 'block';
    document.getElementById('step2').style.display = 'none';

    document.querySelector('[data-step="1"]').classList.add('active');
    document.querySelector('[data-step="2"]').classList.remove('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep2() {
    //   
    const form = document.getElementById('productForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    collectFormData();
    displayPreview();

    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';

    document.querySelector('[data-step="1"]').classList.remove('active');
    document.querySelector('[data-step="2"]').classList.add('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

//   
function collectFormData() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);

    productData = {};

    //   
    for (let [key, value] of formData.entries()) {
        productData[key] = value;
    }

    //    
    productData.includes = collectDynamicItems('includesContainer');
    productData.excludes = collectDynamicItems('excludesContainer');
    productData.highlights = collectDynamicItems('highlightsContainer');
    productData.images = collectDynamicItems('imagesContainer');

    console.log(' :', productData);
}

//   
function collectDynamicItems(containerId) {
    const container = document.getElementById(containerId);
    const items = [];

    container.querySelectorAll('.dynamic-item input').forEach(input => {
        if (input.value.trim()) {
            items.push(input.value.trim());
        }
    });

    return items;
}

//  
function displayPreview() {
    //  
    document.getElementById('preview-packageName').textContent = productData.packageName || '-';
    document.getElementById('preview-packageCategory').textContent = getCategoryLabel(productData.packageCategory);
    document.getElementById('preview-packageType').textContent = getTypeLabel(productData.packageType);
    document.getElementById('preview-packagePrice').textContent = productData.packagePrice ? `₱${parseFloat(productData.packagePrice).toLocaleString()}` : '-';
    document.getElementById('preview-duration').textContent = productData.duration || '-';
    document.getElementById('preview-difficulty').textContent = getDifficultyLabel(productData.difficulty);
    document.getElementById('preview-packageDescription').textContent = productData.packageDescription || '-';

    //  
    document.getElementById('preview-meeting_location').textContent = productData.meeting_location || '-';
    document.getElementById('preview-meeting_time').textContent = productData.meeting_time || '-';
    document.getElementById('preview-minParticipants').textContent = productData.minParticipants || '-';
    document.getElementById('preview-maxParticipants').textContent = productData.maxParticipants || '-';

    //  
    displayPreviewList('preview-includes', productData.includes);
    displayPreviewList('preview-excludes', productData.excludes);
    displayPreviewList('preview-highlights', productData.highlights);

    // 
    displayPreviewImages('preview-images', productData.images);
}

//  
function getCategoryLabel(value) {
    const labels = {
        'season': '',
        'region': '',
        'theme': ''
    };
    return labels[value] || value || '-';
}

//  
function getTypeLabel(value) {
    const labels = {
        'standard': '',
        'premium': '',
        'luxury': ''
    };
    return labels[value] || value || '-';
}

//  
function getDifficultyLabel(value) {
    const labels = {
        'easy': '',
        'moderate': '',
        'challenging': ''
    };
    return labels[value] || value || '-';
}

//   
function displayPreviewList(elementId, items) {
    const container = document.getElementById(elementId);
    container.innerHTML = '';

    if (!items || items.length === 0) {
        container.innerHTML = '<li style="color: #6C757D;"> </li>';
        return;
    }

    items.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        container.appendChild(li);
    });
}

//   
function displayPreviewImages(elementId, images) {
    const container = document.getElementById(elementId);
    container.innerHTML = '';

    if (!images || images.length === 0) {
        container.innerHTML = '<p style="color: #6C757D;">  .</p>';
        return;
    }

    images.forEach(imageUrl => {
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'Product Image';
        img.onerror = function() {
            this.src = '../Assets/placeholder.png';
        };
        container.appendChild(img);
    });
}

//   
function addIncludeItem() {
    addDynamicItem('includesContainer', '  ');
}

function addExcludeItem() {
    addDynamicItem('excludesContainer', '  ');
}

function addHighlightItem() {
    addDynamicItem('highlightsContainer', ' ');
}

function addImageItem() {
    addDynamicItem('imagesContainer', ' URL ');
}

function addDynamicItem(containerId, placeholder) {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'dynamic-item';
    div.innerHTML = `
        <input type="text" class="form-input" placeholder="${placeholder}">
        <button type="button" class="btn-remove" onclick="removeItem(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

//  
function removeItem(button) {
    const item = button.closest('.dynamic-item');
    const container = item.parentElement;

    //  1 
    if (container.querySelectorAll('.dynamic-item').length > 1) {
        item.remove();
    } else {
        //    
        item.querySelector('input').value = '';
    }
}

//   
function submitProduct() {
    if (!confirm(' ?')) {
        return;
    }

    // API   
    const apiData = {
        packageName: productData.packageName,
        packagePrice: parseFloat(productData.packagePrice),
        packageCategory: productData.packageCategory,
        packageDescription: productData.packageDescription || '',
        duration: productData.duration,
        duration_days: parseInt(productData.duration_days) || 3,
        meeting_location: productData.meeting_location || '',
        meeting_time: productData.meeting_time || '09:00:00',
        packageType: productData.packageType || 'standard',
        minParticipants: parseInt(productData.minParticipants) || 1,
        maxParticipants: parseInt(productData.maxParticipants) || 50,
        difficulty: productData.difficulty || 'easy',
        includes: productData.includes || [],
        excludes: productData.excludes || [],
        highlights: productData.highlights || [],
        images: productData.images || []
    };

    console.log('API  :', apiData);

    // API 
    fetch('../../backend/api/packages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(apiData)
    })
    .then(response => response.json())
    .then(result => {
        console.log('API :', result);
        if (result.success) {
            alert('  !');
            window.location.href = 'admin-manageProducts.php';
        } else {
            alert(': ' + (result.message || '  .'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('    .');
    });
}
