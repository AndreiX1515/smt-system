// Admin Product Registration - Figma Design Implementation

document.addEventListener('DOMContentLoaded', function() {
    initImageUploads();
    initDynamicInputs();
    initSummaryUpdates();
    initFormSubmit();
    initCategoryDependency();
});

// Image Upload Handlers
function initImageUploads() {
    // Thumbnail Upload
    const thumbnailArea = document.getElementById('thumbnailArea');
    const thumbnailInput = document.getElementById('thumbnailInput');
    const thumbnailPreview = document.getElementById('thumbnailPreview');

    thumbnailArea.addEventListener('click', () => thumbnailInput.click());

    thumbnailInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                thumbnailPreview.src = event.target.result;
                thumbnailPreview.style.display = 'block';
                updateSummary();
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // Product Images Upload
    const addImageBtn = document.getElementById('addImageBtn');
    const productImagesInput = document.getElementById('productImagesInput');
    const productImagesGrid = document.getElementById('productImagesGrid');

    addImageBtn.addEventListener('click', () => productImagesInput.click());

    productImagesInput.addEventListener('change', function(e) {
        if (e.target.files) {
            Array.from(e.target.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const item = createUploadedImageItem(event.target.result);
                    productImagesGrid.insertBefore(item, addImageBtn);
                };
                reader.readAsDataURL(file);
            });
            productImagesInput.value = '';
        }
    });

    // Detail Image Upload
    const detailImageArea = document.getElementById('detailImageArea');
    const detailImageInput = document.getElementById('detailImageInput');

    detailImageArea.addEventListener('click', () => detailImageInput.click());

    detailImageInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = document.createElement('img');
                img.src = event.target.result;
                img.className = 'preview-img';
                detailImageArea.querySelector('.upload-content').style.display = 'none';
                detailImageArea.appendChild(img);
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
}

// Create Uploaded Image Item
function createUploadedImageItem(src) {
    const div = document.createElement('div');
    div.className = 'uploaded-image-item';
    div.innerHTML = `
        <img src="${src}" alt="Product Image">
        <button type="button" class="btn-remove-uploaded" onclick="this.parentElement.remove()">×</button>
    `;
    return div;
}

// Dynamic Input Lists
function initDynamicInputs() {
    // Add Item Buttons
    document.querySelectorAll('.btn-add-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const container = document.getElementById(targetId);
            const newItem = createDynamicInputItem(targetId);
            container.appendChild(newItem);
        });
    });

    // Remove Item Buttons (Event Delegation)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-item')) {
            const container = e.target.closest('.dynamic-input-list');
            if (container.children.length > 1) {
                e.target.parentElement.remove();
            } else {
                alert(' 1  .');
            }
        }
    });
}

// Create Dynamic Input Item
function createDynamicInputItem(containerId) {
    const div = document.createElement('div');
    div.className = 'dynamic-input-item';

    const placeholder = {
        'includesContainer': ' ',
        'excludesContainer': ' '
    }[containerId] || '';

    const name = containerId.replace('Container', '[]');

    div.innerHTML = `
        <input type="text" name="${name}" class="field-input" placeholder="${placeholder}">
        <button type="button" class="btn-remove-item">-</button>
    `;

    return div;
}

// Summary Updates
function initSummaryUpdates() {
    const form = document.getElementById('productForm');

    // Update on input
    form.addEventListener('input', debounce(updateSummary, 300));
    form.addEventListener('change', updateSummary);
}

// Update Summary Panel
function updateSummary() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);

    // Product Name
    const productName = formData.get('packageName');
    document.getElementById('summaryProductName').textContent = productName || '-';

    // Category
    const category = formData.get('packageCategory');
    const categoryText = {
        'season': '',
        'region': '',
        'theme': ''
    }[category] || '-';
    document.getElementById('summaryCategory').textContent = categoryText;

    // Product Type
    const type = formData.get('packageType');
    const typeText = type ? type.charAt(0).toUpperCase() + type.slice(1) : '-';
    document.getElementById('summaryType').textContent = typeText;

    // Price
    const adultPrice = formData.get('adultPrice');
    if (adultPrice) {
        document.getElementById('summaryPrice').textContent = parseInt(adultPrice).toLocaleString() + '';
    } else {
        document.getElementById('summaryPrice').textContent = '-';
    }

    // Schedule
    const schedule = formData.get('schedule_day_1');
    document.getElementById('summarySchedule').textContent = schedule ? schedule.substring(0, 50) + '...' : '-';

    // Participants
    const minPart = formData.get('minParticipants') || '1';
    const maxPart = formData.get('maxParticipants') || '50';
    document.getElementById('summaryParticipants').textContent = `${minPart} ~ ${maxPart}`;

    // Status
    document.getElementById('summaryStatus').textContent = ' ';
}

// Category Dependency
function initCategoryDependency() {
    const categorySelect = document.querySelector('select[name="packageCategory"]');
    const subCategorySelect = document.querySelector('select[name="subCategory"]');

    const subCategories = {
        'season': [
            { value: 'spring', text: '' },
            { value: 'summer', text: '' },
            { value: 'fall', text: '' },
            { value: 'winter', text: '' }
        ],
        'region': [
            { value: 'seoul', text: '' },
            { value: 'busan', text: '' },
            { value: 'jeju', text: '' },
            { value: 'gangwon', text: '' }
        ],
        'theme': [
            { value: 'adventure', text: '' },
            { value: 'cultural', text: '' },
            { value: 'food', text: '' },
            { value: 'shopping', text: '' }
        ]
    };

    categorySelect.addEventListener('change', function() {
        const category = this.value;
        subCategorySelect.innerHTML = '<option value=""></option>';

        if (category && subCategories[category]) {
            subCategories[category].forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.value;
                option.textContent = sub.text;
                subCategorySelect.appendChild(option);
            });
        }

        updateSummary();
    });
}

// Form Submit
function initFormSubmit() {
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    const saveProductBtn = document.getElementById('saveProductBtn');

    saveDraftBtn.addEventListener('click', () => saveProduct(true));
    saveProductBtn.addEventListener('click', () => {
        if (validateForm()) {
            saveProduct(false);
        }
    });
}

// Validate Form
function validateForm() {
    const requiredFields = document.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ff4444';

            if (!firstInvalidField) {
                firstInvalidField = field;
            }

            field.addEventListener('input', function() {
                this.style.borderColor = '#d4d4d4';
            }, { once: true });
        }
    });

    if (!isValid) {
        alert('   .');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    return isValid;
}

// Save Product
function saveProduct(isDraft) {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);

    // Add draft status
    formData.append('isDraft', isDraft ? '1' : '0');

    // Collect includes
    const includes = [];
    document.querySelectorAll('input[name="includes[]"]').forEach(input => {
        if (input.value.trim()) {
            includes.push(input.value.trim());
        }
    });
    formData.append('includesJson', JSON.stringify(includes));

    // Collect excludes
    const excludes = [];
    document.querySelectorAll('input[name="excludes[]"]').forEach(input => {
        if (input.value.trim()) {
            excludes.push(input.value.trim());
        }
    });
    formData.append('excludesJson', JSON.stringify(excludes));

    // Collect operation days
    const operationDays = [];
    document.querySelectorAll('input[name="operationDays[]"]:checked').forEach(input => {
        operationDays.push(input.value);
    });
    formData.append('operationDaysJson', JSON.stringify(operationDays));

    // Get editor content
    document.querySelectorAll('.editor-area').forEach((editor, index) => {
        const content = editor.innerHTML;
        formData.append(`editor_${index}`, content);
    });

    // Show loading
    const saveBtn = isDraft ? document.getElementById('saveDraftBtn') : document.getElementById('saveProductBtn');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = ' ...';
    saveBtn.disabled = true;

    // Send to API
    fetch('../../backend/api/packages.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(isDraft ? ' .' : ' .');

            if (!isDraft) {
                window.location.href = 'admin-manageProducts.php';
            }
        } else {
            throw new Error(data.message || '   .');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || '   .');
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

// Debounce Helper
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Text Formatting Buttons
document.querySelectorAll('.toolbar-mini-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const command = this.textContent;
        const commands = {
            'B': 'bold',
            'I': 'italic',
            'U': 'underline'
        };

        if (commands[command]) {
            document.execCommand(commands[command], false, null);
        }
    });
});
