/**
 * Agent Admin - Submit Inquiry Page JavaScript
 */

let selectedFiles = [];
let contentEditor = null;

document.addEventListener('DOMContentLoaded', async function() {
    //  
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated || sessionData.userType !== 'agent') {
            window.location.href = '../index.html';
            return;
        }
        
        initializeSubmitInquiry();
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
});

function initializeSubmitInquiry() {
    //    
    const fileUploadBtn = document.getElementById('file-upload-btn');
    const fileUploadInput = document.getElementById('file-upload');
    if (fileUploadBtn && fileUploadInput) {
        fileUploadBtn.addEventListener('click', () => {
            fileUploadInput.click();
        });
        fileUploadInput.addEventListener('change', handleFileUpload);
    }
    
    //   
    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', handleSubmit);
    }
    
    //  
    initContentEditor();
}

function initContentEditor() {
    const editorElement = document.getElementById('content');
    if (!editorElement) return;
    
    // Quill   (multi-editor.js  )
    if (typeof initQuillEditor === 'function') {
        contentEditor = initQuillEditor('content', 'content-toolbar');
    } else {
        //  contentEditable 
        editorElement.contentEditable = 'true';
    }
}

function handleFileUpload(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    Array.from(files).forEach(file => {
        if (!selectedFiles.find(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) {
            selectedFiles.push(file);
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const attachment = {
                    fileName: file.name,
                    fileSize: file.size,
                    fileType: file.type,
                    filePath: e.target.result,
                    isNew: true
                };
                renderAttachments([attachment], true);
            };
            reader.readAsDataURL(file);
        }
    });
    
    event.target.value = '';
}

function renderAttachments(attachments, allowRemove = true) {
    const attachList = document.getElementById('attach-list');
    if (!attachList) return;
    
    //     
    const existingHTML = attachList.innerHTML;
    const newHTML = attachments.map((attachment, index) => {
        const fileName = attachment.fileName || attachment.name || '';
        let filePath = attachment.filePath || attachment.path || '';
        const fileSize = attachment.fileSize || 0;
        const fileType = attachment.fileType || '';
        
        if (filePath && !filePath.startsWith('http://') && !filePath.startsWith('https://') && !filePath.startsWith('data:')) {
            if (filePath.startsWith('/')) {
                filePath = window.location.origin + filePath;
            } else if (filePath.startsWith('../')) {
                filePath = window.location.origin + '/' + filePath.replace('../www/', '');
            } else if (filePath.startsWith('uploads/')) {
                filePath = window.location.origin + '/' + filePath;
            } else {
                const normalizedPath = filePath.startsWith('uploads/') ? filePath : `uploads/${filePath}`;
                filePath = window.location.origin + '/' + normalizedPath;
            }
        }
        
        const formatFileSize = (bytes) => {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + 'B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + 'KB';
            return (bytes / (1024 * 1024)).toFixed(0) + 'MB';
        };
        
        const isImage = fileType && fileType.startsWith('image/');
        const attachmentIndex = selectedFiles.length - attachments.length + index;
        
        if (isImage) {
            return `
                <div class="grid-item">
                    <div class="upload-box">
                        <img src="${escapeHtml(filePath)}" alt="${escapeHtml(fileName)}" class="preview" style="width: 110px; height: 110px; object-fit: cover; border-radius: 10px;">
                        <div class="upload-meta">
                            <div class="file-title">${escapeHtml(fileName)}</div>
                            <div class="file-info">${formatFileSize(fileSize)}</div>
                            <div class="file-controller">
                                <button type="button" class="btn-icon" aria-label="" onclick="window.open('${escapeHtml(filePath)}', '_blank')"><img src="../image/button-download.svg" alt=""></button>
                                ${allowRemove ? `<button type="button" class="btn-icon" aria-label="" onclick="removeAttachment(${attachmentIndex})"><img src="../image/button-close2.svg" alt=""></button>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            return `
                <div class="cell">
                    <div class="field-row jw-center">
                        <div class="jw-center jw-gap10"><img src="../image/file.svg" alt=""> ${escapeHtml(fileName)} [${formatFileSize(fileSize)}]</div>
                        <div class="jw-center jw-gap10">
                            <i></i>
                            <button type="button" class="jw-button typeF" aria-label="" onclick="window.open('${escapeHtml(filePath)}', '_blank')"><img src="../image/buttun-download.svg" alt=""></button>
                            ${allowRemove ? `<button type="button" class="jw-button typeF" aria-label="" onclick="removeAttachment(${attachmentIndex})"><img src="../image/button-close2.svg" alt=""></button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
    }).join('');
    
    attachList.innerHTML = existingHTML + newHTML;
}

function removeAttachment(index) {
    if (index >= 0 && index < selectedFiles.length) {
        selectedFiles.splice(index, 1);
        updateAttachmentsDisplay();
    }
}

function updateAttachmentsDisplay() {
    const attachList = document.getElementById('attach-list');
    if (!attachList) return;
    
    attachList.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const attachment = {
                fileName: file.name,
                fileSize: file.size,
                fileType: file.type,
                filePath: e.target.result,
                isNew: true
            };
            renderAttachments([attachment], true);
        };
        reader.readAsDataURL(file);
    });
}

async function handleSubmit() {
    try {
        const titleInput = document.getElementById('title');
        const contentElement = document.getElementById('content');
        
        if (!titleInput || !contentElement) {
            alert('    .');
            return;
        }
        
        const title = titleInput.value.trim();
        if (!title) {
            alert(' .');
            titleInput.focus();
            return;
        }
        
        let content = '';
        if (contentEditor && typeof contentEditor.getContents === 'function') {
            // Quill  
            const delta = contentEditor.getContents();
            content = JSON.stringify(delta);
        } else {
            //  contentEditable 
            content = contentElement.innerHTML;
        }
        
        if (!content || content.trim() === '' || content === '<p><br></p>') {
            alert(' .');
            contentElement.focus();
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'createInquiry');
        formData.append('inquiryTitle', title);
        formData.append('inquiryContent', content);
        
        //  
        if (selectedFiles && selectedFiles.length > 0) {
            selectedFiles.forEach((file, index) => {
                formData.append(`file_${index}`, file);
            });
        }
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(' .');
            window.location.href = 'inquiry-list.html';
        } else {
            alert('  : ' + result.message);
        }
    } catch (error) {
        console.error('Error submitting inquiry:', error);
        alert('    .');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

