/**
 *    JavaScript
 *    ,  ,  
 */

let currentInquiry = null;
let attachedFiles = [];
let existingAttachments = [];
let isAnswerCompleted = false;

//    
document.addEventListener('DOMContentLoaded', function() {
    initializeInquiryEditPage();
});

//    
async function initializeInquiryEditPage() {
    try {
        //   (localStorage +  )
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        let userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            try {
                const sessionRes = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
                const sessionJson = await sessionRes.json().catch(() => ({}));
                const sid = sessionJson?.user?.id || sessionJson?.user?.accountId || sessionJson?.userId;
                if (sid) {
                    userId = String(sid);
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userId', userId);
                }
            } catch (_) {}
        }

        if (!userId) {
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        // URL   ID 
        const urlParams = new URLSearchParams(window.location.search);
        const inquiryId = urlParams.get('inquiryId') || urlParams.get('inquiry_id');

        if (!inquiryId) {
            alert('Inquiry not found.');
            history.back();
            return;
        }

        //    
        await loadExistingInquiry(inquiryId, userId);

        //   
        setupEventListeners();

        //    
        initializeFormValidation();

    } catch (error) {
        console.error('Inquiry edit init error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

//    
async function loadExistingInquiry(inquiryId, userId) {
    try {
        showLoadingState();

        // API 
        let result;
        if (window.api && window.api.getInquiry) {
            result = await window.api.getInquiry(inquiryId, userId);
        } else {
            const response = await fetch('../backend/api/inquiry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_inquiry',
                    inquiryId: inquiryId,
                    accountId: userId
                }),
                credentials: 'same-origin'
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            currentInquiry = result.data;
            const st = String(result.data?.status || result.data?.dbStatus || '').toLowerCase();
            const replies = Array.isArray(result.data?.replies) ? result.data.replies : [];
            isAnswerCompleted = (st !== 'pending' && st !== 'open' && st !== 'in_progress') || replies.length > 0;
            if (isAnswerCompleted) {
                alert('You cannot edit an inquiry after it has been answered.');
                const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
                window.location.href = `inquiry-detail.html?inquiryId=${encodeURIComponent(inquiryId)}&lang=${encodeURIComponent(lang || 'en')}`;
                return;
            }
            populateForm(result.data);
        } else {
            showErrorMessage(result.message || 'Unable to load inquiry.');
            history.back();
        }

    } catch (error) {
        console.error('Load inquiry error:', error);
        showErrorMessage('An error occurred while loading inquiry.');
    } finally {
        hideLoadingState();
    }
}

//    
function populateForm(inquiry) {
    try {
        //  
        const emailInput = document.getElementById('txt1');
        if (emailInput) {
            const email = inquiry.emailAddress || inquiry.authorEmail || inquiry.email || '';
            if (email) emailInput.value = email;
        }

        //  (   )
        const phoneInput = document.getElementById('txt2');
        if (phoneInput && inquiry.contactNo) {
            // +63     
            const phoneNumber = inquiry.contactNo.replace(/^\+63\s*/, '');
            phoneInput.value = phoneNumber;
        }

        //  
        const categorySelect = document.querySelector('.custom-select .select-trigger .placeholder');
        const categoryValue = String(inquiry.category || inquiry.inquiry_type || inquiry.inquiryType || '').toLowerCase();
        if (categorySelect) {
            //   enum('general','booking','visa','payment','technical','complaint','suggestion') 
            // UI(5 )   .
            const mappedUiValue = mapDbCategoryToUiValue(categoryValue);
            categorySelect.textContent = getInquiryTypeLabel(mappedUiValue);
            //  enum    uiValue -> dbValue  dataset.value 
            categorySelect.dataset.value = mapUiValueToDbCategory(mappedUiValue, categoryValue);
        }

        //  (subject)
        const titleInput = document.getElementById('txt3');
        const subject = inquiry.title || inquiry.subject || '';
        if (titleInput && subject) {
            titleInput.value = subject;
        }

        //  (content)
        const contentTextarea = document.querySelector('.textarea-type1');
        const content = inquiry.message || inquiry.content || '';
        if (contentTextarea && content) {
            contentTextarea.value = content;
        }

        //   
        existingAttachments = Array.isArray(inquiry.attachments) ? inquiry.attachments : [];
        renderExistingAttachments(existingAttachments);

    } catch (error) {
        console.error('   :', error);
        showErrorMessage('An error occurred while loading form data.');
    }
}

//
function renderExistingAttachments(attachments) {
    const container = document.getElementById('existingAttachmentsContainer');
    if (!container) return;
    container.innerHTML = '';

    if (!attachments || attachments.length === 0) {
        return;
    }

    // Separate images and documents
    const images = [];
    const docs = [];
    attachments.forEach((att, idx) => {
        const fileName = att.name || att.fileName || 'attachment';
        const mime = att.mimeType || att.fileType || att.type || '';
        const isImage = mime.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(fileName);
        if (isImage) {
            images.push({ att, idx });
        } else {
            docs.push({ att, idx });
        }
    });

    // Render document files first (PDF etc.)
    docs.forEach(({ att, idx }) => {
        const fileName = att.name || att.fileName || 'attachment';
        const mime = att.mimeType || att.fileType || att.type || '';
        const size = att.size || att.fileSize || 0;
        const ext = fileName.split('.').pop() || mime.split('/').pop() || 'file';

        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex; align-items:center; gap:4px; padding:8px 12px; border:1px solid #D4D4D4; border-radius:4px; background:#fff; width:240px; margin-bottom:12px;';

        wrapper.innerHTML = '<div style="display:flex; align-items:center; gap:6px; flex:1; min-width:0;"><img src="../images/ico_document.svg" alt="" style="width:20px; height:20px; flex-shrink:0;"><div style="flex:1; min-width:0;"><div style="font-size:12px; font-weight:500; line-height:16px; color:#121212; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(fileName.replace(/\.[^.]+$/, '')) + '</div><div style="font-size:12px; font-weight:400; line-height:16px; color:#121212;">' + escapeHtml(ext) + ', ' + escapeHtml(formatFileSize(Number(size) || 0)) + '</div></div></div><div style="width:1px; height:12px; background:#D4D4D4;"></div><button type="button" style="padding:4px; border:0; background:transparent; cursor:pointer; flex-shrink:0;" onclick="removeExistingAttachment(' + idx + ', this.closest(\'div\'))" title="Delete"><img src="../images/ico_close_gray.svg" alt="" style="width:16px; height:16px;"></button>';

        container.appendChild(wrapper);
    });

    // Render image files
    if (images.length > 0) {
        const imageRow = document.createElement('div');
        imageRow.style.cssText = 'display:flex; gap:12px; flex-wrap:wrap;';

        images.forEach(({ att, idx }) => {
            const fileName = att.name || att.fileName || 'image';
            const size = att.size || att.fileSize || 0;
            const ext = fileName.split('.').pop() || 'jpg';
            const url = att.url || att.filePath || '';

            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'display:flex; flex-direction:column; gap:6px; width:110px;';

            wrapper.innerHTML = '<div style="position:relative; width:110px; height:110px;"><div style="width:110px; height:110px; background:#F3F3F3; border-radius:4px; overflow:hidden;"><img src="' + escapeHtml(url) + '" alt="" style="width:100%; height:100%; object-fit:cover;"></div><button type="button" style="position:absolute; top:2px; right:2px; padding:4px; border:0; background:transparent; cursor:pointer;" onclick="removeExistingAttachment(' + idx + ', this.closest(\'div\').parentElement)" title="Delete"><img src="../images/ico_close_black.svg" alt="" style="width:16px; height:16px;"></button></div><div style="padding:0 2px;"><div style="font-size:14px; font-weight:500; line-height:22px; color:#121212; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(fileName.replace(/\.[^.]+$/, '')) + '</div><div style="font-size:14px; font-weight:500; line-height:22px; color:#121212;">' + escapeHtml(ext) + ', ' + escapeHtml(formatFileSize(Number(size) || 0)) + '</div></div>';

            imageRow.appendChild(wrapper);
        });

        container.appendChild(imageRow);
    }
}


//
function setupEventListeners() {
    //  
    const uploadInput = document.getElementById('upload');
    if (uploadInput) {
        uploadInput.addEventListener('change', handleFileUpload);
    }

    //   
    setupCustomSelect();

    //    ( ) - addFileToDisplay 

    //   
    const submitButton = document.querySelector('.btn.primary.lg');
    if (submitButton) {
        submitButton.addEventListener('click', handleFormSubmit);
    }

    //   
    setupRealTimeValidation();
}

//   
function setupCustomSelect() {
    const selectTrigger = document.querySelector('.select-trigger');
    const selectOptions = document.querySelector('.select-options');

    if (selectTrigger && selectOptions) {
        // (5  )
        // DB enum('general','booking','visa','payment','technical','complaint','suggestion')  value DB enum .
        const categories = [
            { ui: 'product', label: 'Product Inquiry', db: 'general' },
            { ui: 'reservation', label: 'Reservation Inquiry', db: 'booking' },
            { ui: 'payment', label: 'Payment Inquiry', db: 'payment' },
            { ui: 'cancellation', label: 'Cancellation Inquiry', db: 'complaint' },
            { ui: 'other', label: 'Other', db: 'suggestion' }
        ];

        selectOptions.innerHTML = '';
        categories.forEach(category => {
            const li = document.createElement('li');
            li.textContent = category.label;
            li.dataset.ui = category.ui;
            li.dataset.value = category.db;
            li.addEventListener('click', function() {
                const placeholder = selectTrigger.querySelector('.placeholder');
                placeholder.textContent = category.label;
                placeholder.dataset.value = category.db;
                selectOptions.style.display = 'none';
                validateForm();
            });
            selectOptions.appendChild(li);
        });

        //  
        selectTrigger.addEventListener('click', function() {
            const isVisible = selectOptions.style.display === 'block';
            selectOptions.style.display = isVisible ? 'none' : 'block';
        });

        //    
        document.addEventListener('click', function(e) {
            if (!selectTrigger.contains(e.target) && !selectOptions.contains(e.target)) {
                selectOptions.style.display = 'none';
            }
        });
    }
}

//   
function handleFileUpload(event) {
    const files = Array.from(event.target.files);
    const maxFiles = 5;
    const maxFileSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];

    //    
    const currentFileCount = attachedFiles.length + existingAttachments.length;

    for (const file of files) {
        if (currentFileCount + attachedFiles.length >= maxFiles) {
            alert(`You can upload up to ${maxFiles} files.`);
            break;
        }

        //   
        if (file.size > maxFileSize) {
            alert(`${file.name}: file size exceeds 10MB.`);
            continue;
        }

        //   
        if (!allowedTypes.includes(file.type)) {
            alert(`${file.name}: unsupported file type.`);
            continue;
        }

        attachedFiles.push(file);
        addFileToDisplay(file);
    }

    //   
    event.target.value = '';
    updateFileCount();
}

// New file display (matching Figma design)
function addFileToDisplay(file) {
    const container = document.getElementById('newAttachmentsList') || document.querySelector('.img-upload');
    if (!container) return;

    const fileIndex = attachedFiles.indexOf(file);
    const fileName = file.name;
    const ext = fileName.split('.').pop() || 'file';
    const isImage = file.type.startsWith('image/');

    if (isImage) {
        // Image file: 110x110 thumbnail with delete icon
        const reader = new FileReader();
        reader.onload = function(e) {
            const wrapper = document.createElement('div');
            wrapper.className = 'new-file-item';
            wrapper.style.cssText = 'display:flex; flex-direction:column; gap:6px; width:110px;';
            wrapper.dataset.fileIndex = fileIndex;

            wrapper.innerHTML = `
                <div style="position:relative; width:110px; height:110px;">
                    <div style="width:110px; height:110px; background:#F3F3F3; border-radius:4px; overflow:hidden;">
                        <img src="${e.target.result}" alt="" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <button type="button" style="position:absolute; top:2px; right:2px; padding:4px; border:0; background:transparent; cursor:pointer;" onclick="removeNewAttachment(${fileIndex}, this.closest('.new-file-item'))" title="Delete">
                        <img src="../images/ico_close_black.svg" alt="" style="width:16px; height:16px;">
                    </button>
                </div>
                <div style="padding:0 2px;">
                    <div style="font-size:14px; font-weight:500; line-height:22px; color:#121212; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(fileName.replace(/\.[^.]+$/, ''))}</div>
                    <div style="font-size:14px; font-weight:500; line-height:22px; color:#121212;">${escapeHtml(ext)}, ${escapeHtml(formatFileSize(file.size))}</div>
                </div>
            `;

            container.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    } else {
        // Document file (PDF etc.): 240px box with file icon and delete icon
        const wrapper = document.createElement('div');
        wrapper.className = 'new-file-item';
        wrapper.style.cssText = 'display:flex; align-items:center; gap:4px; padding:8px 12px; border:1px solid #D4D4D4; border-radius:4px; background:#fff; width:240px; margin-bottom:12px;';
        wrapper.dataset.fileIndex = fileIndex;

        wrapper.innerHTML = `
            <div style="display:flex; align-items:center; gap:6px; flex:1; min-width:0;">
                <img src="../images/ico_document.svg" alt="" style="width:20px; height:20px; flex-shrink:0;">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:12px; font-weight:500; line-height:16px; color:#121212; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(fileName.replace(/\.[^.]+$/, ''))}</div>
                    <div style="font-size:12px; font-weight:400; line-height:16px; color:#121212;">${escapeHtml(ext)}, ${escapeHtml(formatFileSize(file.size))}</div>
                </div>
            </div>
            <div style="width:1px; height:12px; background:#D4D4D4;"></div>
            <button type="button" style="padding:4px; border:0; background:transparent; cursor:pointer; flex-shrink:0;" onclick="removeNewAttachment(${fileIndex}, this.closest('.new-file-item'))" title="Delete">
                <img src="../images/ico_close_gray.svg" alt="" style="width:16px; height:16px;">
            </button>
        `;

        container.appendChild(wrapper);
    }
}

// Remove new (not yet uploaded) attachment
function removeNewAttachment(index, element) {
    if (!confirm('Remove this attachment?')) return;
    attachedFiles.splice(index, 1);
    if (element) element.remove();
    // Re-render all new attachments to fix indices
    const container = document.getElementById('newAttachmentsList') || document.querySelector('.img-upload');
    if (container) {
        const items = container.querySelectorAll('.new-file-item');
        items.forEach(item => item.remove());
        attachedFiles.forEach(file => addFileToDisplay(file));
    }
    updateFileCount();
}

//   
function removeExistingAttachment(index, element) {
    if (!confirm('Remove this attachment?')) return;
    existingAttachments.splice(index, 1);
    renderExistingAttachments(existingAttachments);
    updateFileCount();
}

//   
function updateFileCount() {
    const totalFiles = attachedFiles.length + existingAttachments.length;
    const uploadBox = document.querySelector('.upload-box');

    if (uploadBox) {
        if (totalFiles >= 5) {
            uploadBox.style.display = 'none';
        } else {
            uploadBox.style.display = 'block';
        }
    }
}

//    
function setupRealTimeValidation() {
    const inputs = document.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', validateForm);
        input.addEventListener('blur', validateForm);
    });
}

//    
function initializeFormValidation() {
    validateForm();
}

//   
function validateForm() {
    const email = document.getElementById('txt1').value.trim();
    const phone = document.getElementById('txt2').value.trim();
    const category = document.querySelector('.select-trigger .placeholder').dataset.value;
    const title = document.getElementById('txt3').value.trim();
    const content = document.querySelector('.textarea-type1').value.trim();

    const isEmailValid = validateEmail(email);
    const isPhoneValid = validatePhone(phone);
    const isCategoryValid = !!category;
    const isTitleValid = title.length > 0;
    const isContentValid = content.length > 0;

    const isFormValid = isEmailValid && isPhoneValid && isCategoryValid && isTitleValid && isContentValid;

    //    
    const submitButton = document.querySelector('.btn.primary.lg');
    if (submitButton) {
        submitButton.disabled = !isFormValid;
        submitButton.style.opacity = isFormValid ? '1' : '0.5';
    }

    return isFormValid;
}

//   
function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return emailRegex.test(email);
}

//   
function validatePhone(phone) {
    const phoneRegex = /^\d{9,11}$/;
    return phoneRegex.test(phone);
}

//   
async function handleFormSubmit() {
    if (!validateForm()) {
        alert('Please fill in all required fields correctly.');
        return;
    }

    if (!currentInquiry) {
        alert('Inquiry not found.');
        return;
    }

    try {
        showLoadingState();

        //  update_inquiry updateData + files[]     
        const updateData = collectFormData();
        const keepIds = existingAttachments
            .map(a => a.attachmentId)
            .filter(v => v !== undefined && v !== null);

        const fd = new FormData();
        fd.append('action', 'update_inquiry');
        fd.append('inquiryId', String(currentInquiry.inquiryId));
        fd.append('updateData', JSON.stringify(updateData));
        fd.append('keepAttachmentIds', JSON.stringify(keepIds));
        attachedFiles.forEach(f => fd.append('files[]', f));

        const response = await fetch('../backend/api/inquiry.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const result = await response.json().catch(() => ({}));

        if (result.success) {
            alert('Inquiry updated.');
            const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
            window.location.href = `inquiry-detail.html?inquiryId=${encodeURIComponent(currentInquiry.inquiryId)}&lang=${encodeURIComponent(lang || 'en')}`;
        } else {
            alert(result.message || 'Failed to update inquiry.');
        }

    } catch (error) {
        console.error('Update inquiry error:', error);
        alert('An error occurred while updating inquiry.');
    } finally {
        hideLoadingState();
    }
}

//   
function collectFormData() {
    const email = document.getElementById('txt1').value.trim();
    const phone = document.getElementById('txt2').value.trim();
    const category = document.querySelector('.select-trigger .placeholder').dataset.value;
    const title = document.getElementById('txt3').value.trim();
    const content = document.querySelector('.textarea-type1').value.trim();

    return {
        title: title,
        message: content, // backend title/message (=subject/content )
        inquiry_type: category
    };
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

//  
function getInquiryTypeLabel(uiValue) {
    const map = {
        product: 'Product Inquiry',
        reservation: 'Reservation Inquiry',
        payment: 'Payment Inquiry',
        cancellation: 'Cancellation Inquiry',
        other: 'Other'
    };
    return map[uiValue] || 'Other';
}

function mapDbCategoryToUiValue(dbValue) {
    const v = String(dbValue || '').toLowerCase();
    if (v === 'booking') return 'reservation';
    if (v === 'payment') return 'payment';
    if (v === 'complaint') return 'cancellation';
    if (v === 'suggestion') return 'other';
    // visa/technical/general/unknown -> Product/Other ( Product)
    if (v === 'visa' || v === 'technical') return 'other';
    return 'product';
}

function mapUiValueToDbCategory(uiValue, fallbackDbValue = '') {
    // fallbackDbValue enum    (:  visa/technical)
    const fb = String(fallbackDbValue || '').toLowerCase();
    if (fb === 'visa' || fb === 'technical') {
        //   UI     ()
        // populateForm  uiValue other  ,
        // dataset.value     fb .
        if (uiValue === 'other') return fb;
    }

    const map = {
        product: 'general',
        reservation: 'booking',
        payment: 'payment',
        cancellation: 'complaint',
        other: 'suggestion'
    };
    return map[uiValue] || 'general';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '0.6';
        mainContent.style.pointerEvents = 'none';
    }
}

function hideLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '1';
        mainContent.style.pointerEvents = 'auto';
    }
}

function showErrorMessage(message) {
    alert(message);
}

//     
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadExistingInquiry,
        handleFormSubmit,
        validateForm
    };
}