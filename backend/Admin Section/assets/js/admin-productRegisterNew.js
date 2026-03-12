// Product Registration - Figma Design Implementation

//     
const uploadedFiles = {
    thumbnailImage: null,
    detailImage: null,
    productImages: [null, null, null, null, null],
    airportImage: null,
    accommodationImage: null
};

document.addEventListener('DOMContentLoaded', function() {
    initCategoryDependency();
    initRichEditor();
    initImageUploads();
    initDateValidation();
    initParticipantValidation();
    initDayToggle();
    initAdditionalImageUploads();
    initFileUpload();
    initTravelCostCalculation();
    //      
    const firstDaySection = document.querySelector('.day-schedule-section[data-day-number="1"]');
    if (firstDaySection) {
        initImageUploadForDay(firstDaySection, 1);
    }
});

// Category Dependency
function initCategoryDependency() {
    const mainCategory = document.getElementById('mainCategory');
    const subCategory = document.getElementById('subCategory');

    const subCategories = {
        'season': [
            { value: 'spring', text: '' },
            { value: 'summer', text: '' },
            { value: 'autumn', text: '' },
            { value: 'winter', text: '' }
        ],
        'region': [
            { value: 'seoul', text: '' },
            { value: 'busan', text: '' },
            { value: 'jeju', text: '' },
            { value: 'gangwon', text: '' },
            { value: 'gyeonggi', text: '' }
        ],
        'theme': [
            { value: 'adventure', text: '' },
            { value: 'cultural', text: '' },
            { value: 'healing', text: '' },
            { value: 'food', text: '' },
            { value: 'shopping', text: '' }
        ]
    };

    mainCategory.addEventListener('change', function() {
        const selectedCategory = this.value;

        // Reset sub category
        subCategory.innerHTML = '<option value=""> </option>';

        if (selectedCategory && subCategories[selectedCategory]) {
            subCategories[selectedCategory].forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.value;
                option.textContent = sub.text;
                subCategory.appendChild(option);
            });
            subCategory.disabled = false;
        } else {
            subCategory.innerHTML = '<option value="">  </option>';
            subCategory.disabled = true;
        }
    });
}

// Rich Text Editor
function initRichEditor() {
    const editorContent = document.querySelector('.editor-content');
    const toolbarBtns = document.querySelectorAll('.toolbar-btn');

    // Toolbar button actions
    toolbarBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cmd = this.dataset.cmd;

            if (cmd) {
                executeCommand(cmd, this);
            }
        });
    });

    // Execute formatting command
    function executeCommand(cmd, btn) {
        editorContent.focus();

        switch(cmd) {
            case 'bold':
            case 'italic':
            case 'underline':
                document.execCommand(cmd, false, null);
                btn.classList.toggle('active');
                break;

            case 'foreColor':
                const color = prompt('   (: #ff0000)', '#000000');
                if (color) {
                    document.execCommand(cmd, false, color);
                }
                break;

            case 'justifyLeft':
            case 'justifyCenter':
            case 'justifyRight':
                document.execCommand(cmd, false, null);
                // Remove active from other alignment buttons
                document.querySelectorAll('[data-cmd^="justify"]').forEach(b => {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                break;

            case 'insertUnorderedList':
                document.execCommand(cmd, false, null);
                btn.classList.toggle('active');
                break;

            case 'insertLink':
                const url = prompt(' URL :', 'https://');
                if (url && url !== 'https://') {
                    document.execCommand('createLink', false, url);
                }
                break;

            default:
                document.execCommand(cmd, false, null);
        }
    }

    // Prevent default behavior on editor
    editorContent.addEventListener('keydown', function(e) {
        // Allow tab for indentation
        if (e.key === 'Tab') {
            e.preventDefault();
            document.execCommand('insertHTML', false, '&nbsp;&nbsp;&nbsp;&nbsp;');
        }
    });
}

// Image Uploads
function initImageUploads() {
    // Thumbnail upload
    const thumbnailUpload = document.getElementById('thumbnailUpload');
    const thumbnailInput = document.getElementById('thumbnailInput');
    const thumbnailPreview = document.getElementById('thumbnailPreview');

    thumbnailUpload.querySelector('.upload-trigger-btn').addEventListener('click', function() {
        thumbnailInput.click();
    });

    thumbnailInput.addEventListener('change', function(e) {
        handleImageUpload(e.target.files[0], thumbnailPreview, thumbnailUpload, 'thumbnailImage');
    });

    // Detail image upload
    const detailImageUpload = document.getElementById('detailImageUpload');
    const detailImageInput = document.getElementById('detailImageInput');
    const detailImagePreview = document.getElementById('detailImagePreview');

    detailImageUpload.querySelector('.upload-trigger-btn').addEventListener('click', function() {
        detailImageInput.click();
    });

    detailImageInput.addEventListener('change', function(e) {
        handleImageUpload(e.target.files[0], detailImagePreview, detailImageUpload, 'detailImage');
    });

    // Product images upload (grid)
    const productImagesInput = document.getElementById('productImagesInput');
    const uploadBtns = document.querySelectorAll('[data-upload-index]');

    uploadBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const index = this.dataset.uploadIndex;
            productImagesInput.dataset.currentIndex = index;
            productImagesInput.click();
        });
    });

    productImagesInput.addEventListener('change', function(e) {
        const index = this.dataset.currentIndex;
        const file = e.target.files[0];

        if (file && index !== undefined) {
            const gridItem = document.querySelectorAll('.image-upload-grid .grid-item')[index];
            const preview = gridItem.querySelector('.image-preview');
            handleImageUpload(file, preview, gridItem, 'productImage_' + index);
        }

        // Reset
        this.value = '';
        delete this.dataset.currentIndex;
    });
}

// Handle single image upload
function handleImageUpload(file, previewElement, containerElement, fileKey = null) {
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('   .');
        return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        alert(`    (${fileSizeMB}MB).\n  5MB  .\n   .`);
        return;
    }

    //  
    if (fileKey) {
        if (fileKey.startsWith('productImage_')) {
            const index = parseInt(fileKey.split('_')[1]);
            uploadedFiles.productImages[index] = file;
        } else {
            uploadedFiles[fileKey] = file;
        }
    }

    // Read file
    const reader = new FileReader();

    reader.onload = function(e) {
        previewElement.style.backgroundImage = `url(${e.target.result})`;
        previewElement.style.display = 'block';
        previewElement.classList.add('has-image');

        // Hide upload button
        const uploadBtn = containerElement.querySelector('.upload-trigger-btn');
        if (uploadBtn) {
            uploadBtn.style.display = 'none';
        }

        // Add remove functionality
        previewElement.addEventListener('click', function(event) {
            // Check if clicked on the X button area
            const rect = this.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            // X button is in top-right corner (8px from edges, 28px size)
            if (x > rect.width - 36 && x < rect.width - 8 && y > 8 && y < 36) {
                removeImage(previewElement, containerElement, fileKey);
            }
        });
    };

    reader.onerror = function() {
        alert('    .');
    };

    reader.readAsDataURL(file);
}

// Remove uploaded image
function removeImage(previewElement, containerElement, fileKey = null) {
    previewElement.style.backgroundImage = '';
    previewElement.style.display = 'none';
    previewElement.classList.remove('has-image');

    //  
    if (fileKey) {
        if (fileKey.startsWith('productImage_')) {
            const index = parseInt(fileKey.split('_')[1]);
            uploadedFiles.productImages[index] = null;
        } else {
            uploadedFiles[fileKey] = null;
        }
    }

    // Show upload button again
    const uploadBtn = containerElement.querySelector('.upload-trigger-btn');
    if (uploadBtn) {
        uploadBtn.style.display = 'flex';
    }
}

// Form validation
function validateForm() {
    const form = document.getElementById('productRegistrationForm');
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ff4444';

            if (!firstInvalidField) {
                firstInvalidField = field;
            }

            // Reset border on input
            field.addEventListener('input', function() {
                this.style.borderColor = '#d9d9d9';
            }, { once: true });
        }
    });

    // Check rich editor
    const editorContent = document.querySelector('.editor-content');
    if (editorContent && !editorContent.textContent.trim()) {
        isValid = false;
        editorContent.style.borderColor = '#ff4444';

        if (!firstInvalidField) {
            firstInvalidField = editorContent;
        }
    }

    if (!isValid) {
        alert('   .');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    return isValid;
}

// Date Validation (Max 100 days)
function initDateValidation() {
    const salesPeriodInput = document.querySelector('input[name="salesPeriod"]');

    if (salesPeriodInput) {
        // Set min date to today
        const today = new Date().toISOString().split('T')[0];
        salesPeriodInput.setAttribute('min', today);

        // Set max date to 100 days from today
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 100);
        salesPeriodInput.setAttribute('max', maxDate.toISOString().split('T')[0]);

        salesPeriodInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const todayDate = new Date(today);
            const diffTime = Math.abs(selectedDate - todayDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 100) {
                alert('   100   .');
                this.value = '';
            }
        });
    }
}

// Participant Validation
function initParticipantValidation() {
    const minParticipants = document.querySelector('input[name="minParticipants"]');
    const maxParticipants = document.querySelector('input[name="maxParticipants"]');

    if (minParticipants && maxParticipants) {
        function validateParticipants() {
            const minVal = parseInt(minParticipants.value) || 0;
            const maxVal = parseInt(maxParticipants.value) || 0;

            if (minVal > 0 && maxVal > 0 && minVal > maxVal) {
                alert('        .');
                minParticipants.value = '';
                return false;
            }
            return true;
        }

        minParticipants.addEventListener('change', validateParticipants);
        maxParticipants.addEventListener('change', validateParticipants);
    }
}

// Day Toggle
function initDayToggle() {
    //      
    document.addEventListener('click', function(e) {
        if (e.target.closest('.toggle-day-btn')) {
            const header = e.target.closest('.day-header');
            if (header) {
                const section = header.parentElement;
                section.classList.toggle('collapsed');
            }
        }
    });
}

//   
function addDaySchedule() {
    const container = document.getElementById('daySchedulesContainer');
    if (!container) return;
    
    //    
    const existingDays = container.querySelectorAll('.day-schedule-section');
    const nextDayNumber = existingDays.length + 1;
    
    //   HTML 
    const newDayHTML = createDayScheduleHTML(nextDayNumber);
    
    //  
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = newDayHTML;
    const newDaySection = tempDiv.firstElementChild;
    container.appendChild(newDaySection);
    
    //      
    initRichEditorForDay(newDaySection);
    
    //      
    initImageUploadForDay(newDaySection, nextDayNumber);
    
    //     
    newDaySection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

//   
function deleteDaySchedule(button) {
    const section = button.closest('.day-schedule-section');
    if (!section) return;
    
    const container = document.getElementById('daySchedulesContainer');
    const allDays = container.querySelectorAll('.day-schedule-section');
    
    //  1 
    if (allDays.length <= 1) {
        alert(' 1   .');
        return;
    }
    
    if (confirm('  ?')) {
        section.remove();
        //   
        renumberDaySchedules();
    }
}

//   
function renumberDaySchedules() {
    const container = document.getElementById('daySchedulesContainer');
    const allDays = container.querySelectorAll('.day-schedule-section');
    
    allDays.forEach((daySection, index) => {
        const dayNumber = index + 1;
        daySection.setAttribute('data-day-number', dayNumber);
        
        //  
        const dayTitle = daySection.querySelector('.day-title');
        if (dayTitle) {
            dayTitle.textContent = `${dayNumber}Day`;
        }
        
        //  input/select name  
        const inputs = daySection.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name) {
                // day1_xxx  name    
                input.name = input.name.replace(/day\d+_/, `day${dayNumber}_`);
            }
        });
        
        //   ID 
        const imageUpload = daySection.querySelector('.day-airport-image-upload');
        if (imageUpload) {
            imageUpload.id = `day${dayNumber}_airportImageUpload`;
        }
        const imageInput = daySection.querySelector('.day-airport-image-input');
        if (imageInput) {
            imageInput.id = `day${dayNumber}_airportImageInput`;
        }
        const imagePreview = daySection.querySelector('.day-airport-image-preview');
        if (imagePreview) {
            imagePreview.id = `day${dayNumber}_airportImagePreview`;
        }
    });
}

//  HTML  
function createDayScheduleHTML(dayNumber) {
    //    
    let hourOptions = '<option value="">00</option>';
    for (let i = 0; i < 24; i++) {
        const hour = String(i).padStart(2, '0');
        hourOptions += `<option value="${hour}">${hour}</option>`;
    }
    
    let minuteOptions = '<option value="">00</option>';
    for (let i = 0; i < 60; i += 5) {
        const minute = String(i).padStart(2, '0');
        minuteOptions += `<option value="${minute}">${minute}</option>`;
    }
    
    return `
        <div class="day-schedule-section" data-day-number="${dayNumber}">
            <div class="day-header">
                <span class="day-title">${dayNumber}Day</span>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="toggle-day-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                    <button type="button" class="delete-day-btn" onclick="deleteDaySchedule(this)" style="background: none; border: none; cursor: pointer; padding: 4px; color: #ff4444;" title=" ">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="day-content">
                <!--   -->
                <div class="form-field-group">
                    <label class="field-label"> </label>
                    <input type="text" name="day${dayNumber}_description" class="text-input" placeholder=" ">
                </div>

                <!--   -->
                <div class="form-field-group">
                    <label class="field-label"> </label>
                    <button type="button" class="add-attraction-btn">+  </button>
                </div>

                <!--    /   -->
                <div class="form-row-split">
                    <div class="form-field-group">
                        <label class="field-label required">  </label>
                        <div class="time-picker-group">
                            <select name="day${dayNumber}_startHour" class="time-select" required>
                                ${hourOptions}
                            </select>
                            <span class="time-separator">:</span>
                            <select name="day${dayNumber}_startMinute" class="time-select" required>
                                ${minuteOptions}
                            </select>
                        </div>
                    </div>
                    <div class="form-field-group">
                        <label class="field-label required">  </label>
                        <div class="time-picker-group">
                            <select name="day${dayNumber}_endHour" class="time-select" required>
                                ${hourOptions}
                            </select>
                            <span class="time-separator">:</span>
                            <select name="day${dayNumber}_endMinute" class="time-select" required>
                                ${minuteOptions}
                            </select>
                        </div>
                    </div>
                </div>

                <!--  -->
                <div class="form-field-group">
                    <label class="field-label required"></label>
                    <input type="text" name="day${dayNumber}_airport" class="text-input" placeholder=" " required>
                </div>

                <!--   -->
                <div class="form-field-group">
                    <label class="field-label required"> </label>
                    <input type="text" name="day${dayNumber}_airportAddress" class="text-input" placeholder="  " required>
                </div>

                <!--   -->
                <div class="form-field-group">
                    <label class="field-label required"> </label>
                    <div class="rich-editor">
                        <div class="editor-toolbar">
                            <select class="toolbar-select">
                                <option></option>
                            </select>
                            <select class="toolbar-select">
                                <option>Arial</option>
                            </select>
                            <select class="toolbar-select">
                                <option>15</option>
                            </select>
                            <div class="toolbar-divider"></div>
                            <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                            <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                            <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                            <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                            <div class="toolbar-divider"></div>
                            <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                            <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                            <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                            <div class="toolbar-divider"></div>
                            <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                            <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                        </div>
                        <div class="editor-content" contenteditable="true" data-placeholder="  "></div>
                    </div>
                </div>

                <!--   -->
                <div class="form-field-group">
                    <label class="field-label required"> </label>
                    <div class="image-upload-box single day-airport-image-upload" id="day${dayNumber}_airportImageUpload">
                        <div class="upload-btn-wrapper">
                            <button type="button" class="upload-trigger-btn">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                 
                            </button>
                        </div>
                        <input type="file" class="day-airport-image-input" id="day${dayNumber}_airportImageInput" accept="image/*" hidden>
                        <div class="image-preview day-airport-image-preview" id="day${dayNumber}_airportImagePreview" style="display:none;"></div>
                    </div>
                    <button type="button" class="outline-btn mt-2"> </button>
                </div>
            </div>
        </div>
    `;
}

//    
function initRichEditorForDay(daySection) {
    const editorContent = daySection.querySelector('.editor-content');
    const toolbarBtns = daySection.querySelectorAll('.toolbar-btn');
    
    if (!editorContent || !toolbarBtns.length) return;
    
    toolbarBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cmd = this.dataset.cmd;
            if (cmd) {
                editorContent.focus();
                executeEditorCommand(cmd, this, editorContent);
            }
        });
    });
}

//   
function executeEditorCommand(cmd, btn, editorContent) {
    editorContent.focus();
    
    switch(cmd) {
        case 'bold':
        case 'italic':
        case 'underline':
            document.execCommand(cmd, false, null);
            btn.classList.toggle('active');
            break;
        case 'foreColor':
            const color = prompt('   (: #ff0000)', '#000000');
            if (color) {
                document.execCommand(cmd, false, color);
            }
            break;
        case 'justifyLeft':
        case 'justifyCenter':
        case 'justifyRight':
            document.execCommand(cmd, false, null);
            editorContent.parentElement.querySelectorAll('[data-cmd^="justify"]').forEach(b => {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            break;
        case 'insertUnorderedList':
            document.execCommand(cmd, false, null);
            btn.classList.toggle('active');
            break;
        case 'insertLink':
            const url = prompt(' URL :', 'https://');
            if (url && url !== 'https://') {
                document.execCommand('createLink', false, url);
            }
            break;
    }
}

//    
function initImageUploadForDay(daySection, dayNumber) {
    const imageUpload = daySection.querySelector(`#day${dayNumber}_airportImageUpload`);
    const imageInput = daySection.querySelector(`#day${dayNumber}_airportImageInput`);
    const imagePreview = daySection.querySelector(`#day${dayNumber}_airportImagePreview`);
    
    if (!imageUpload || !imageInput || !imagePreview) return;
    
    const uploadBtn = imageUpload.querySelector('.upload-trigger-btn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            imageInput.click();
        });
    }
    
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            handleImageUpload(file, imagePreview, imageUpload, `day${dayNumber}_airportImage`);
        }
    });
}

// Additional Image Uploads
function initAdditionalImageUploads() {
    // Airport Image (   -   )
    //    initImageUploadForDay 

    // Accommodation Image
    const accommodationImageUpload = document.getElementById('accommodationImageUpload');
    const accommodationImageInput = document.getElementById('accommodationImageInput');
    const accommodationImagePreview = document.getElementById('accommodationImagePreview');

    if (accommodationImageUpload && accommodationImageInput) {
        accommodationImageUpload.querySelector('.upload-trigger-btn').addEventListener('click', function() {
            accommodationImageInput.click();
        });

        accommodationImageInput.addEventListener('change', function(e) {
            handleImageUpload(e.target.files[0], accommodationImagePreview, accommodationImageUpload, 'accommodationImage');
        });
    }
}

// Address Search function removed - users can now enter addresses manually

//     
function getRandomItem(array) {
    return array[Math.floor(Math.random() * array.length)];
}

function getRandomNumber(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function getRandomTime() {
    return String(Math.floor(Math.random() * 24)).padStart(2, '0');
}

function getRandomMinute() {
    const minutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
    return String(getRandomItem(minutes)).padStart(2, '0');
}

// Fill Test Data
function fillTestData() {
    // 
    const productNames = [
        '   ',
        '  ',
        '  ',
        '  ',
        '   ',
        '  ',
        '  ',
        '  ',
        '  ',
        '   ',
        '   ',
        '  & ',
        '  ',
        '   ',
        '   '
    ];
    
    const productNameInput = document.querySelector('input[name="productName"]');
    if (productNameInput) {
        const uniqueSuffix = new Date().toISOString().replace(/[-T:.Z]/g, '').slice(-10);
        productNameInput.value = `${getRandomItem(productNames)} #${uniqueSuffix}`;
    }
    //   ()
    const salesTargets = ['b2b', 'b2c'];
    const salesTargetSelect = document.querySelector('select[name="salesTarget"]');
    if (salesTargetSelect) {
        salesTargetSelect.value = getRandomItem(salesTargets);
    }

    //  ()
    const mainCategories = ['season', 'region', 'theme'];
    const mainCategorySelect = document.querySelector('select[name="mainCategory"]');
    if (mainCategorySelect) {
        const selectedCategory = getRandomItem(mainCategories);
        mainCategorySelect.value = selectedCategory;
        
        //    
        const mainCategoryEvent = new Event('change');
        mainCategorySelect.dispatchEvent(mainCategoryEvent);
    }

    //      
    setTimeout(() => {
        const subCategorySelect = document.querySelector('select[name="subCategory"]');
        if (subCategorySelect && subCategorySelect.options.length > 1) {
            const randomIndex = getRandomNumber(1, subCategorySelect.options.length - 1);
            subCategorySelect.selectedIndex = randomIndex;
        }
    }, 100);

    //   ()
    const productDescriptions = [
        '<p><strong>     !</strong></p><ul><li>  </li><li>  </li><li> </li></ul><p>      .</p>',
        '<p><strong>   </strong></p><ul><li> </li><li> </li><li> </li></ul><p>    .</p>',
        '<p><strong>   </strong></p><ul><li>  </li><li> </li><li> </li></ul><p>    .</p>',
        '<p><strong>    </strong></p><ul><li> </li><li> </li><li> </li></ul><p>   .</p>',
        '<p><strong>   </strong></p><ul><li> </li><li> </li><li> </li></ul><p>    .</p>'
    ];
    const productDescEditor = document.querySelectorAll('.editor-content')[0];
    if (productDescEditor) {
        productDescEditor.innerHTML = getRandomItem(productDescriptions);
    }

    //   ()
    const flightNumbers = ['KE631', 'KE633', 'KE635', 'OZ701', 'OZ703', 'PR501', 'PR503', '5J501', '5J503'];
    const airports = ['', '', ''];
    const destinations = [' ', '   ', ' ', ' '];
    
    const departureFlightNumber = getRandomItem(flightNumbers);
    const returnFlightNumber = getRandomItem(flightNumbers);
    const departurePoint = getRandomItem(airports);
    const destination = getRandomItem(destinations);
    
    document.querySelector('input[name="departureFlightNumber"]').value = departureFlightNumber;
    document.querySelector('select[name="departureFlightDepartureHour"]').value = getRandomTime();
    document.querySelector('select[name="departureFlightDepartureMinute"]').value = getRandomMinute();
    const arrivalHour = String((parseInt(getRandomTime()) + getRandomNumber(3, 6)) % 24).padStart(2, '0');
    document.querySelector('select[name="departureFlightArrivalHour"]').value = arrivalHour;
    document.querySelector('select[name="departureFlightArrivalMinute"]').value = getRandomMinute();
    document.querySelector('input[name="departureFlightDeparturePoint"]').value = departurePoint;
    document.querySelector('input[name="departureFlightDestination"]').value = destination;

    document.querySelector('input[name="returnFlightNumber"]').value = returnFlightNumber;
    const returnDepartureHour = String((parseInt(arrivalHour) + getRandomNumber(5, 10)) % 24).padStart(2, '0');
    document.querySelector('select[name="returnFlightDepartureHour"]').value = returnDepartureHour;
    document.querySelector('select[name="returnFlightDepartureMinute"]').value = getRandomMinute();
    const returnArrivalHour = String((parseInt(returnDepartureHour) + getRandomNumber(3, 6)) % 24).padStart(2, '0');
    document.querySelector('select[name="returnFlightArrivalHour"]').value = returnArrivalHour;
    document.querySelector('select[name="returnFlightArrivalMinute"]').value = getRandomMinute();
    document.querySelector('input[name="returnFlightDeparturePoint"]').value = destination;
    document.querySelector('input[name="returnFlightDestination"]').value = departurePoint;

    //   ()
    const today = new Date();
    const salesDate = new Date(today.getTime() + (getRandomNumber(7, 30) * 24 * 60 * 60 * 1000));
    document.querySelector('input[name="salesPeriod"]').value = salesDate.toISOString().split('T')[0];
    document.querySelector('input[name="minParticipants"]').value = getRandomNumber(2, 5);
    document.querySelector('input[name="maxParticipants"]').value = getRandomNumber(15, 30);
    document.querySelector('input[name="basePrice"]').value = getRandomNumber(10000, 50000);

    //   ()
    document.querySelector('input[name="airfareCost"]').value = getRandomNumber(5000, 15000);
    document.querySelector('input[name="accommodationCost"]').value = getRandomNumber(2000, 8000);
    document.querySelector('input[name="mealCost"]').value = getRandomNumber(1000, 3000);
    document.querySelector('input[name="guideCost"]').value = getRandomNumber(500, 2000);
    document.querySelector('input[name="vehicleCost"]').value = getRandomNumber(800, 2500);
    document.querySelector('input[name="entranceCost"]').value = getRandomNumber(300, 1500);
    document.querySelector('input[name="otherCost"]').value = getRandomNumber(100, 1000);

    //     (  )
    document.querySelector('input[name="airfareCost"]').dispatchEvent(new Event('input'));

    //   ()
    const meetingLocations = [
        ' 3 ',
        ' 2 ',
        ' 1 ',
        ' ',
        '  '
    ];
    const meetingAddresses = [
        '   272',
        '   112',
        '   108',
        '   110',
        '   123'
    ];
    
    document.querySelector('select[name="meetingHour"]').value = getRandomTime();
    document.querySelector('select[name="meetingMinute"]').value = getRandomMinute();
    document.querySelector('input[name="meetingLocation"]').value = getRandomItem(meetingLocations);
    document.querySelector('input[name="meetingAddress"]').value = getRandomItem(meetingAddresses);

    //   ( 3)
    const container = document.getElementById('daySchedulesContainer');
    const existingDays = container.querySelectorAll('.day-schedule-section');
    const currentDayCount = existingDays.length;
    const targetDayCount = Math.max(3, currentDayCount);
    
    //   
    for (let i = currentDayCount; i < targetDayCount; i++) {
        addDaySchedule();
    }
    
    //     
    setTimeout(() => {
        const allDays = container.querySelectorAll('.day-schedule-section');
        allDays.forEach((daySection, index) => {
            const dayNumber = index + 1;
            fillDayScheduleData(daySection, dayNumber);
        });
    }, 200);

    //   ()
    const accommodationNames = [
        'Crimson Resort & Spa Mactan',
        'Shangri-La Mactan Resort & Spa',
        'Marco Polo Plaza Cebu',
        'Waterfront Cebu City Hotel & Casino',
        'Radisson Blu Cebu',
        'Quest Hotel & Conference Center',
        'Bayfront Hotel Cebu',
        'Solea Mactan Cebu Resort'
    ];
    const accommodationAddresses = [
        'Seascapes Resort Town, Mactan Island, Cebu',
        'Punta Engaño Road, Mactan Island, Cebu',
        'Nivel Hills, Lahug, Cebu City',
        'Salinas Drive, Lahug, Cebu City',
        'Serging Osmeña Blvd, Cebu City',
        'Archbishop Reyes Ave, Cebu City',
        'Cardinal Rosales Ave, Cebu City',
        'M.L. Quezon National Highway, Mactan Island, Cebu'
    ];
    
    document.querySelector('input[name="accommodationName"]').value = getRandomItem(accommodationNames);
    document.querySelector('input[name="accommodationAddress"]').value = getRandomItem(accommodationAddresses);

    //   ()
    const accommodationDescriptions = [
        '<p><strong>5  </strong></p><ul><li> </li><li> </li><li> &  </li><li> 3</li></ul>',
        '<p><strong>   </strong></p><ul><li> </li><li> </li><li> </li><li> Wi-Fi</li></ul>',
        '<p><strong> </strong></p><ul><li> </li><li> </li><li> </li><li> </li></ul>',
        '<p><strong>  </strong></p><ul><li> </li><li> </li><li> & </li><li></li></ul>'
    ];
    const accommodationDescEditor = document.querySelectorAll('.editor-content')[2];
    if (accommodationDescEditor) {
        accommodationDescEditor.innerHTML = getRandomItem(accommodationDescriptions);
    }

    //   ()
    const transportationDescriptions = [
        '<p>       .</p>',
        '<p>     .</p>',
        '<p>    .</p>',
        '<p>      .</p>'
    ];
    const transportationDescEditor = document.querySelectorAll('.editor-content')[3];
    if (transportationDescEditor) {
        transportationDescEditor.innerHTML = getRandomItem(transportationDescriptions);
    }

    //   ()
    const breakfastOptions = ['  ', ' ', ' ', ' '];
    const lunchOptions = ['  ( )', ' ', '', ' '];
    const dinnerOptions = [' BBQ', ' ', ' ', '  '];
    
    document.querySelector('input[name="breakfast"]').value = getRandomItem(breakfastOptions);
    document.querySelector('input[name="lunch"]').value = getRandomItem(lunchOptions);
    document.querySelector('input[name="dinner"]').value = getRandomItem(dinnerOptions);

    //   ()
    const optionNames = [' ( 12 )', ' ( 2-11)', ' ( 2 )', ' ( 65 )'];
    const optionNameInputs = document.querySelectorAll('input[name="optionName[]"]');
    const optionPriceInputs = document.querySelectorAll('input[name="optionPrice[]"]');
    if (optionNameInputs.length > 0) {
        optionNameInputs[0].value = getRandomItem(optionNames);
        optionPriceInputs[0].value = getRandomNumber(10000, 30000);
    }

    //    ()
    const pricingTypes = ['per_person', 'per_group', 'per_day'];
    document.querySelector('select[name="productPricingType"]').value = getRandomItem(pricingTypes);
    document.querySelector('input[name="productPricing"]').value = getRandomNumber(12000, 35000);

    //   ()
    const includedItems = [
        '<ul><li> </li><li>3  (5 )</li><li>   (, , )</li><li>   </li><li>  </li><li> </li><li>  </li></ul>',
        '<ul><li> </li><li>2 </li><li> </li><li> </li><li> </li><li></li></ul>',
        '<ul><li></li><li></li><li> (, )</li><li></li><li></li><li></li></ul>'
    ];
    const includedItemsEditor = document.querySelectorAll('.editor-content')[4];
    if (includedItemsEditor) {
        includedItemsEditor.innerHTML = getRandomItem(includedItems);
    }

    //   ()
    const excludedItems = [
        '<ul><li> </li><li>  ( ,   )</li><li>   </li><li> (,  )</li></ul>',
        '<ul><li> </li><li> </li><li> </li><li></li></ul>',
        '<ul><li> </li><li> </li><li> </li></ul>'
    ];
    const excludedItemsEditor = document.querySelectorAll('.editor-content')[5];
    if (excludedItemsEditor) {
        excludedItemsEditor.innerHTML = getRandomItem(excludedItems);
    }

    //  ()
    const usageGuides = [
        '<p><strong> </strong></p><ul><li>  7   </li><li>  6  </li><li>  2 1 </li></ul>',
        '<p><strong></strong></p><ul><li>  3   </li><li>   </li><li>    </li></ul>',
        '<p><strong> </strong></p><ul><li>    </li><li>   </li><li>   </li></ul>'
    ];
    const usageGuideEditor = document.querySelectorAll('.editor-content')[6];
    if (usageGuideEditor) {
        usageGuideEditor.innerHTML = getRandomItem(usageGuides);
    }

    // /  ()
    const cancellationPolicies = [
        '<ul><li> 14 :  </li><li> 7-13 : 50% </li><li> 6 :  </li></ul>',
        '<ul><li> 21 :  </li><li> 14-20 : 70% </li><li> 7-13 : 50% </li><li> 6 :  </li></ul>',
        '<ul><li> 30 :  </li><li> 15-29 : 80% </li><li> 8-14 : 50% </li><li> 7 :  </li></ul>'
    ];
    const cancellationEditor = document.querySelectorAll('.editor-content')[7];
    if (cancellationEditor) {
        cancellationEditor.innerHTML = getRandomItem(cancellationPolicies);
    }

    //    ()
    const visaGuides = [
        '<p>   30   .</p><p>  6   .</p>',
        '<p>   (30 )</p><p>   </p><p>   </p>',
        '<p>  (30  )</p><p>  6 </p><p>   </p>'
    ];
    const visaGuideEditor = document.querySelectorAll('.editor-content')[8];
    if (visaGuideEditor) {
        visaGuideEditor.innerHTML = getRandomItem(visaGuides);
    }

    alert('   !\n 3  .\n  .');
}

//     
function fillDayScheduleData(daySection, dayNumber) {
    const dayDescriptions = [
        '   , ',
        '  ',
        '   ',
        ' & ',
        '    ',
        ' ',
        '  ',
        ' ',
        ' & ',
        ' '
    ];
    
    const attractions = [
        '  ',
        ' ',
        ' ',
        '  ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' '
    ];
    
    const attractionAddresses = [
        'Lapu-Lapu City, Cebu, Philippines',
        'Beverly Hills, Cebu City, Philippines',
        'Magallanes Street, Cebu City, Philippines',
        'A. Pigafetta Street, Cebu City, Philippines',
        'Carmen, Bohol, Philippines',
        'Corella, Bohol, Philippines',
        'Tagbilaran City, Bohol, Philippines',
        'Malay, Aklan, Philippines',
        'Puerto Princesa, Palawan, Philippines',
        'El Nido, Palawan, Philippines',
        'Coron, Palawan, Philippines',
        'Boracay Island, Aklan, Philippines'
    ];
    
    const attractionDescriptions = [
        '<p>      .</p><p>    .</p>',
        '<p>    .</p><p>    .</p>',
        '<p>    .</p><p>     .</p>',
        '<p>        .</p><p>    .</p>',
        '<p>     .</p><p>   .</p>'
    ];
    
    //  
    const descInput = daySection.querySelector(`input[name="day${dayNumber}_description"]`);
    if (descInput) {
        descInput.value = getRandomItem(dayDescriptions);
    }
    
    //  / 
    const startHour = getRandomTime();
    const startMinute = getRandomMinute();
    const endHour = String((parseInt(startHour) + getRandomNumber(4, 8)) % 24).padStart(2, '0');
    const endMinute = getRandomMinute();
    
    const startHourSelect = daySection.querySelector(`select[name="day${dayNumber}_startHour"]`);
    const startMinuteSelect = daySection.querySelector(`select[name="day${dayNumber}_startMinute"]`);
    const endHourSelect = daySection.querySelector(`select[name="day${dayNumber}_endHour"]`);
    const endMinuteSelect = daySection.querySelector(`select[name="day${dayNumber}_endMinute"]`);
    
    if (startHourSelect) startHourSelect.value = startHour;
    if (startMinuteSelect) startMinuteSelect.value = startMinute;
    if (endHourSelect) endHourSelect.value = endHour;
    if (endMinuteSelect) endMinuteSelect.value = endMinute;
    
    // 
    const airportInput = daySection.querySelector(`input[name="day${dayNumber}_airport"]`);
    if (airportInput) {
        airportInput.value = getRandomItem(attractions);
    }
    
    //  
    const airportAddressInput = daySection.querySelector(`input[name="day${dayNumber}_airportAddress"]`);
    if (airportAddressInput) {
        airportAddressInput.value = getRandomItem(attractionAddresses);
    }
    
    //  
    const editorContent = daySection.querySelector('.editor-content');
    if (editorContent) {
        editorContent.innerHTML = getRandomItem(attractionDescriptions);
    }
}

// Travel Cost Calculation
function initTravelCostCalculation() {
    const costFields = [
        'airfareCost',
        'accommodationCost',
        'mealCost',
        'guideCost',
        'vehicleCost',
        'entranceCost',
        'otherCost'
    ];

    const totalCostField = document.querySelector('input[name="totalCost"]');

    function calculateTotalCost() {
        let total = 0;

        costFields.forEach(fieldName => {
            const field = document.querySelector(`input[name="${fieldName}"]`);
            if (field) {
                const value = parseFloat(field.value) || 0;
                total += value;
            }
        });

        if (totalCostField) {
            totalCostField.value = total.toFixed(2);
        }
    }

    // Add event listeners to all cost fields
    costFields.forEach(fieldName => {
        const field = document.querySelector(`input[name="${fieldName}"]`);
        if (field) {
            field.addEventListener('input', calculateTotalCost);
            field.addEventListener('change', calculateTotalCost);
        }
    });
}

// Dynamic Pricing Table
function addPricingRow() {
    const tableBody = document.getElementById('pricingTableBody');
    const rowCount = tableBody.querySelectorAll('tr').length;
    const newRowNumber = rowCount + 1;

    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td class="row-number">${newRowNumber}</td>
        <td>
            <input type="text" name="optionName[]" class="table-input" placeholder="">
        </td>
        <td>
            <input type="number" name="optionPrice[]" class="table-input" placeholder=" " min="0" step="0.01">
        </td>
        <td class="text-center">
            <button type="button" class="delete-row-btn" onclick="deleteRow(this)">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M6 6L14 14M14 6L6 14" stroke="#666" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </td>
    `;

    tableBody.appendChild(newRow);
}

function deleteRow(button) {
    const row = button.closest('tr');
    const tableBody = document.getElementById('pricingTableBody');

    // Don't allow deleting if only one row remains
    if (tableBody.querySelectorAll('tr').length <= 1) {
        alert(' 1   .');
        return;
    }

    row.remove();

    // Renumber all rows
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        const numberCell = row.querySelector('.row-number');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
    });
}

// File Upload Handler
function initFileUpload() {
    const fileUploadCheckbox = document.querySelector('.file-upload-checkbox');
    const guideFileInput = document.getElementById('guideFileInput');

    if (fileUploadCheckbox && guideFileInput) {
        fileUploadCheckbox.addEventListener('change', function() {
            if (this.checked) {
                guideFileInput.click();
            }
        });

        guideFileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const fileName = e.target.files[0].name;
                console.log('File selected:', fileName);
                // You could add visual feedback here showing the file name
            } else {
                // If no file selected, uncheck the checkbox
                fileUploadCheckbox.checked = false;
            }
        });
    }
}

// Save Draft
function saveDraft() {
    const form = document.getElementById('productRegistrationForm');
    const formData = new FormData(form);

    // Add editor content
    const editors = document.querySelectorAll('.editor-content');
    editors.forEach((editor, index) => {
        formData.append(`editorContent_${index}`, editor.innerHTML);
    });

    // Save to localStorage as draft
    const draftData = {};
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }

    localStorage.setItem('productDraft', JSON.stringify(draftData));
    alert('.');
}

// Form submission
function submitForm() {
    if (!validateForm()) {
        return;
    }

    const form = document.getElementById('productRegistrationForm');
    const formData = new FormData(form);
    
    //   
    let totalSize = 0;
    for (let key in uploadedFiles) {
        if (key === 'productImages') {
            uploadedFiles[key].forEach(file => {
                if (file) totalSize += file.size;
            });
        } else if (uploadedFiles[key]) {
            totalSize += uploadedFiles[key].size;
        }
    }
    
    // 150MB  (  100MB )
    const maxSizeMB = 100;
    const totalSizeMB = totalSize / (1024 * 1024);
    if (totalSizeMB > maxSizeMB) {
        alert(`     (${totalSizeMB.toFixed(2)}MB).\n    .\n  : ${maxSizeMB}MB`);
        return;
    }

    // Add all editor content
    // 1.   (   -   )
    const allEditors = document.querySelectorAll('.editor-content');
    let editorIndex = 0;
    
    if (allEditors[editorIndex]) {
        formData.append('productDescription', allEditors[editorIndex].innerHTML);
        editorIndex++;
    }
    
    // 2.    
    const daySections = document.querySelectorAll('.day-schedule-section');
    daySections.forEach((daySection) => {
        const dayNumber = daySection.getAttribute('data-day-number') || 
                         parseInt(daySection.querySelector('.day-title').textContent.replace('Day', '').trim());
        const dayEditor = daySection.querySelector('.editor-content');
        if (dayEditor) {
            formData.append(`day${dayNumber}_airport_description`, dayEditor.innerHTML);
        }
    });
    
    // 3.    (   )
    //        
    const nonDayEditors = Array.from(allEditors).filter(editor => {
        return !editor.closest('.day-schedule-section');
    });
    
    //     
    const remainingEditors = nonDayEditors.slice(1); //     
    
    remainingEditors.forEach((editor, index) => {
        //      
        const parentSection = editor.closest('.form-field-group');
        if (parentSection) {
            const label = parentSection.querySelector('label');
            if (label) {
                const labelText = label.textContent.trim();
                if (labelText.includes(' ')) {
                    formData.append('accommodation_description', editor.innerHTML);
                } else if (labelText.includes('') || labelText.includes('')) {
                    const sectionTitle = parentSection.closest('.section-title, .subsection-title');
                    if (sectionTitle && sectionTitle.textContent.includes('')) {
                        formData.append('transportation_description', editor.innerHTML);
                    }
                } else if (labelText.includes(' ')) {
                    formData.append('included_items', editor.innerHTML);
                } else if (labelText.includes(' ')) {
                    formData.append('excluded_items', editor.innerHTML);
                } else if (labelText.includes('') || (labelText.includes('') && index === remainingEditors.length - 3)) {
                    formData.append('usage_guide', editor.innerHTML);
                } else if (labelText.includes('') || (labelText.includes('') && index === remainingEditors.length - 2)) {
                    formData.append('cancellation_policy', editor.innerHTML);
                } else if (labelText.includes('') || (labelText.includes('') && index === remainingEditors.length - 1)) {
                    formData.append('visa_guide', editor.innerHTML);
                }
            }
        }
    });

    // Add uploaded images
    if (uploadedFiles.thumbnailImage) {
        formData.append('thumbnailImage', uploadedFiles.thumbnailImage);
    }
    if (uploadedFiles.detailImage) {
        formData.append('detailImage', uploadedFiles.detailImage);
    }
    if (uploadedFiles.airportImage) {
        formData.append('airportImage', uploadedFiles.airportImage);
    }
    if (uploadedFiles.accommodationImage) {
        formData.append('accommodationImage', uploadedFiles.accommodationImage);
    }

    // Add product images
    uploadedFiles.productImages.forEach((file, index) => {
        if (file) {
            formData.append('productImage_' + index, file);
        }
    });

    // Show loading
    const submitButton = document.querySelector('.btn-primary');
    const originalText = submitButton.textContent;
    submitButton.textContent = ' ...';
    submitButton.disabled = true;

    // Submit via AJAX
    fetch('../../backend/api/product-register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        //   
        if (!response.ok) {
            // HTTP   
            if (response.status === 413) {
                throw new Error('    .   .');
            } else if (response.status === 500) {
                throw new Error('  .    .');
            } else {
                throw new Error(`  (${response.status})`);
            }
        }
        
        // Content-Type 
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('   .');
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(' .');
            // Clear draft after successful submission
            localStorage.removeItem('productDraft');
            window.location.href = 'admin-manageProducts.php';
        } else {
            throw new Error(data.message || '   .');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || '   .');
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}
