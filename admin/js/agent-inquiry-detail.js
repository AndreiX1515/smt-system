/**
 * Agent Admin - Inquiry Detail Page JavaScript
 */

let currentInquiryId = null;
let isAnswered = false;
let currentAttachments = [];
let selectedFiles = []; //    
let replyAttachments = []; //  
let replySelectedFiles = []; //     
let replyContentEditor = null; //   
let inquiryContentEditor = null; //    

function triggerDownload(url, filename = '') {
    const a = document.createElement('a');
    a.href = url;
    if (filename) a.download = filename;
    else a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function downloadAttachmentForAgent(inquiryId, attachment) {
    const fileName = attachment?.fileName || attachment?.name || 'attachment';
    const filePath = attachment?.filePath || attachment?.path || '';
    if (!filePath) return;

    //   (data URL)   
    if (typeof filePath === 'string' && filePath.startsWith('data:')) {
        triggerDownload(filePath, fileName);
        return;
    }

    const url = `../backend/api/agent-api.php?action=downloadInquiryAttachment&inquiryId=${encodeURIComponent(inquiryId)}&filePath=${encodeURIComponent(filePath)}`;
    triggerDownload(url, fileName);
}

document.addEventListener('DOMContentLoaded', async function() {
    try {
        //  
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated) {
            window.location.href = '../index.html';
            return;
        }
        
        // URL inquiryId 
        const urlParams = new URLSearchParams(window.location.search);
        currentInquiryId = urlParams.get('id') || urlParams.get('inquiryId');

        //   
        const saveButton = document.getElementById('save-btn');
        if (saveButton) {
            saveButton.addEventListener('click', handleSave);
        }

        const fileUploadBtn = document.getElementById('file-upload-btn');
        const fileUploadInput = document.getElementById('file-upload');
        if (fileUploadBtn && fileUploadInput) {
            fileUploadBtn.addEventListener('click', () => fileUploadInput.click());
            fileUploadInput.addEventListener('change', handleFileUpload);
        }

        //   (multi-editor.js initQuillEditor  )
        initEditors();

        if (!currentInquiryId) {
            showError(' ID .');
            return;
        }

        await loadInquiryDetail();
    } catch (error) {
        console.error('Inquiry detail init error:', error);
        showError('    .');
        window.location.href = '../index.html';
        return;
    }
});

function initEditors() {
    //   
    try {
        if (typeof initQuillEditor === 'function') {
            inquiryContentEditor = initQuillEditor('content', 'content-toolbar');
        }
    } catch (e) {
        inquiryContentEditor = null;
    }

    //    ( )
    try {
        if (typeof initQuillEditor === 'function') {
            replyContentEditor = initQuillEditor('reply_content_input', 'reply-content-toolbar');
        }
    } catch (e) {
        replyContentEditor = null;
    }
}

function looksLikeQuillDeltaJson(str) {
    if (!str || typeof str !== 'string') return false;
    const t = str.trim();
    if (!t.startsWith('{') && !t.startsWith('[')) return false;
    try {
        const obj = JSON.parse(t);
        return !!(obj && typeof obj === 'object' && Array.isArray(obj.ops));
    } catch (e) {
        return false;
    }
}

function setEditorContent(editorInstance, elementId, value) {
    const raw = (value ?? '').toString();
    if (!raw) return;

    if (editorInstance && typeof editorInstance.setContents === 'function') {
        // Quill delta JSON 
        if (looksLikeQuillDeltaJson(raw)) {
            try {
                editorInstance.setContents(JSON.parse(raw));
                return;
            } catch (e) {}
        }
        // HTML 
        if (editorInstance.clipboard && typeof editorInstance.clipboard.dangerouslyPasteHTML === 'function') {
            editorInstance.clipboard.dangerouslyPasteHTML(raw);
            return;
        }
    }

    // fallback
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = raw;
}

async function loadInquiryDetail() {
    try {
        showLoading();
        
        const response = await fetch(`../backend/api/agent-api.php?action=getInquiryDetail&inquiryId=${encodeURIComponent(currentInquiryId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        
        if (result.success) {
            renderInquiryDetail(result.data);
        } else {
            showError('   : ' + result.message);
        }
    } catch (error) {
        console.error('Error loading inquiry detail:', error);
        showError('     .');
    } finally {
        hideLoading();
    }
}

function renderInquiryDetail(data) {
    const inquiry = data.inquiry;
    
    // :  
    console.log('Inquiry data:', inquiry);
    
    //   
    const replyContentRaw = (inquiry.replyContent ?? '').toString();
    const repliedAtRaw = inquiry.repliedAt || inquiry.replied_at || null;
    const statusRaw = (inquiry.status ?? inquiry.dbStatus ?? '').toString().toLowerCase().trim();
    isAnswered = !!(
        replyContentRaw.trim() ||
        repliedAtRaw ||
        ['completed', 'answered', 'resolved', 'closed'].includes(statusRaw)
    );
    
    //   UI 
    setupUIForStatus(isAnswered);
    
    // 
    const createdAtInput = document.getElementById('created_at');
    const createdAt =
        inquiry.createdAt ||
        inquiry.created_at ||
        inquiry.registrationDate ||
        inquiry.regDate ||
        inquiry.created ||
        '';
    if (createdAtInput && createdAt) {
        createdAtInput.value = formatDateTime(createdAt);
    }
    
    //  
    const titleInput = document.getElementById('title');
    const title = inquiry.inquiryTitle || inquiry.subject || inquiry.title || '';
    if (titleInput && title) {
        titleInput.value = title;
        titleInput.readOnly = isAnswered;
    }
    
    //   ( )
    const content = inquiry.inquiryContent || inquiry.content || '';
    setEditorContent(inquiryContentEditor, 'content', content);
    
    //  
    currentAttachments = inquiry.attachments || [];
    if (currentAttachments.length > 0) {
        renderAttachments(currentAttachments, !isAnswered);
    }
    
    //   ( )
    if (isAnswered) {
        // HTML      reply_content_display 
        const replyDisplay = document.getElementById('reply_content_display');
        if (replyDisplay) {
            replyDisplay.innerHTML = replyContentRaw || '';
        }
        
        const repliedAtBox = document.getElementById('replied_at');
        if (repliedAtBox && repliedAtRaw) {
            repliedAtBox.textContent = formatDateTime(repliedAtRaw);
        }
    } else {
        // :   ""  / 
        const replyDisplay = document.getElementById('reply_content_display');
        if (replyDisplay) {
            replyDisplay.innerHTML = '<div style="color:#666;">Not Responded</div>';
        }
    }
}

function setupUIForStatus(answered) {
    // Save 버튼은 항상 표시 (에이전트가 문의 내용 수정 가능)
    const saveBtn = document.getElementById('save-btn');
    if (saveBtn) {
        saveBtn.style.display = 'inline-block';
    }
    
    //    
    const titleInput = document.getElementById('title');
    if (titleInput) {
        titleInput.readOnly = answered;
    }
    
    //   / ( : content-toolbar)
    const editorToolbar = document.getElementById('content-toolbar');
    const contentEl = document.getElementById('content');
    if (editorToolbar) editorToolbar.style.display = answered ? 'none' : 'flex';
    if (contentEl) contentEl.style.background = answered ? '#F3F3F3' : '#fff';
    if (contentEl) contentEl.contentEditable = answered ? 'false' : 'true';
    // Quill  enable/disable 
    if (inquiryContentEditor && typeof inquiryContentEditor.enable === 'function') {
        inquiryContentEditor.enable(!answered);
    }
    
    //    /
    const uploadBtnWrap = document.getElementById('upload-btn-wrap');
    if (uploadBtnWrap) {
        uploadBtnWrap.style.display = answered ? 'none' : 'block';
    }
    
    //   /
    // Agent   /   (  )
    const repliedSectionWrap = document.getElementById('replied-section-wrap');
    const replyAttachmentDisplaySection = document.getElementById('reply-attachment-display-section');
    const replyInputSection = document.getElementById('reply-input-section');
    const replyAttachmentSection = document.getElementById('reply-attachment-section');
    const submitSection = document.getElementById('submit-section');

    if (repliedSectionWrap) repliedSectionWrap.style.display = answered ? 'block' : 'none';
    if (replyAttachmentDisplaySection) replyAttachmentDisplaySection.style.display = answered ? 'block' : 'none';

    if (replyInputSection) replyInputSection.style.display = 'none';
    if (replyAttachmentSection) replyAttachmentSection.style.display = 'none';
    if (submitSection) submitSection.style.display = 'none';
}

function initRichTextEditor() {
    const editorContent = document.getElementById('content');
    const toolbarBtns = document.querySelectorAll('.toolbar-btn');
    const styleSelect = document.getElementById('editor-style');
    const fontSelect = document.getElementById('editor-font');
    const sizeSelect = document.getElementById('editor-size');
    
    if (!editorContent) return;
    
    //   
    toolbarBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cmd = this.dataset.cmd;
            if (cmd) {
                executeCommand(cmd, this);
            }
        });
    });
    
    //  
    if (styleSelect) {
        styleSelect.addEventListener('change', function() {
            const value = this.value;
            editorContent.focus();
            document.execCommand('formatBlock', false, value);
        });
    }
    
    //  
    if (fontSelect) {
        fontSelect.addEventListener('change', function() {
            const value = this.value;
            editorContent.focus();
            document.execCommand('fontName', false, value);
        });
    }
    
    //   
    if (sizeSelect) {
        sizeSelect.addEventListener('change', function() {
            const value = this.value;
            editorContent.focus();
            document.execCommand('fontSize', false, '3');
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                const span = document.createElement('span');
                span.style.fontSize = value + 'px';
                try {
                    range.surroundContents(span);
                } catch (e) {
                    //    
                }
            }
        });
    }
    
    //  
    function executeCommand(cmd, btn) {
        editorContent.focus();
        
        switch(cmd) {
            case 'bold':
            case 'italic':
            case 'underline':
            case 'strikeThrough':
                document.execCommand(cmd, false, null);
                btn.classList.toggle('active');
                break;
                
            case 'foreColor':
                const color = prompt('   (: #ff0000)', '#000000');
                if (color) {
                    document.execCommand(cmd, false, color);
                }
                break;
                
            case 'backColor':
                const bgColor = prompt('   (: #ffff00)', '#ffffff');
                if (bgColor) {
                    document.execCommand(cmd, false, bgColor);
                }
                break;
                
            case 'justifyLeft':
            case 'justifyCenter':
            case 'justifyRight':
            case 'justifyFull':
                document.execCommand(cmd, false, null);
                document.querySelectorAll('[data-cmd^="justify"]').forEach(b => {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                break;
                
            case 'insertOrderedList':
            case 'insertUnorderedList':
                document.execCommand(cmd, false, null);
                btn.classList.toggle('active');
                break;
                
            case 'createLink':
                const url = prompt(' URL :', 'https://');
                if (url && url !== 'https://') {
                    document.execCommand('createLink', false, url);
                }
                break;
                
            case 'insertImage':
                const imgUrl = prompt(' URL :', 'https://');
                if (imgUrl && imgUrl !== 'https://') {
                    document.execCommand('insertImage', false, imgUrl);
                }
                break;
        }
    }
}

function handleFileUpload(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    //    selectedFiles  
    Array.from(files).forEach(file => {
        //  
        if (!selectedFiles.find(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) {
            selectedFiles.push(file);
            
            //    attachment  
            const reader = new FileReader();
            reader.onload = function(e) {
                const attachment = {
                    fileName: file.name,
                    fileSize: file.size,
                    fileType: file.type,
                    filePath: e.target.result, //  data URL 
                    isNew: true //    
                };
                currentAttachments.push(attachment);
                renderAttachments(currentAttachments, true);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // input 
    event.target.value = '';
}

function renderAttachments(attachments, allowRemove = true) {
    const attachList = document.getElementById('attach-list');
    if (!attachList) return;
    
    attachList.innerHTML = attachments.map((attachment, index) => {
        const fileName = attachment.fileName || attachment.name || '';
        let filePath = attachment.filePath || attachment.path || '';
        const fileSize = attachment.fileSize || 0;
        const fileType = attachment.fileType || '';
        
        //    (uploads/inquiries/ -> www/uploads/inquiries/)
        if (filePath && !filePath.startsWith('http://') && !filePath.startsWith('https://') && !filePath.startsWith('data:')) {
            if (filePath.startsWith('/')) {
                //   
                filePath = window.location.origin + filePath;
            } else if (filePath.startsWith('../')) {
                // ../www/uploads/inquiries/...  
                filePath = window.location.origin + '/' + filePath.replace('../www/', '');
            } else if (filePath.startsWith('uploads/')) {
                //  uploads/      
                filePath = window.location.origin + '/' + filePath;
            } else {
                //    uploads/  
                const normalizedPath = filePath.startsWith('uploads/') ? filePath : `uploads/${filePath}`;
                filePath = window.location.origin + '/' + normalizedPath;
            }
        }
        
        //   
        const formatFileSize = (bytes) => {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + 'B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + 'KB';
            return (bytes / (1024 * 1024)).toFixed(0) + 'MB';
        };
        
        //     
        const isImage = fileType && fileType.startsWith('image/');
        
        if (isImage) {
            return `
                <div class="grid-item">
                    <div class="upload-box">
                        <img src="${escapeHtml(filePath)}" alt="${escapeHtml(fileName)}" class="preview" style="width: 110px; height: 110px; object-fit: cover; border-radius: 10px;">
                        <div class="upload-meta">
                            <div class="file-title">${escapeHtml(fileName)}</div>
                            <div class="file-info">${formatFileSize(fileSize)}</div>
                            <div class="file-controller">
                                <button type="button" class="btn-icon" aria-label="" onclick="downloadAttachmentForAgent('${escapeHtml(currentInquiryId)}', currentAttachments[${index}])"><img src="../image/button-download.svg" alt=""></button>
                                ${allowRemove ? `<button type="button" class="btn-icon" aria-label="" onclick="removeAttachment(${index})"><img src="../image/button-close2.svg" alt=""></button>` : ''}
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
                            <button type="button" class="jw-button typeF" aria-label="" onclick="downloadAttachmentForAgent('${escapeHtml(currentInquiryId)}', currentAttachments[${index}])"><img src="../image/buttun-download.svg" alt=""></button>
                            ${allowRemove ? `<button type="button" class="jw-button typeF" aria-label="" onclick="removeAttachment(${index})"><img src="../image/button-close2.svg" alt=""></button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
    }).join('');
}

function removeAttachment(index) {
    const attachment = currentAttachments[index];
    
    //     selectedFiles 
    if (attachment && attachment.isNew) {
        const fileName = attachment.fileName;
        selectedFiles = selectedFiles.filter(f => f.name !== fileName);
    }
    
    currentAttachments.splice(index, 1);
    renderAttachments(currentAttachments, !isAnswered);
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    } catch (e) {
        return dateString;
    }
}

async function handleSave() {
    try {
        const titleInput = document.getElementById('title');
        const contentEditor = document.getElementById('content');
        
        if (!titleInput || !contentEditor) {
            alert('    .');
            return;
        }
        
        // FormData  (  )
        const formData = new FormData();
        formData.append('action', 'updateInquiry');
        formData.append('inquiryId', currentInquiryId);
        formData.append('inquiryTitle', titleInput.value);
        // Quill delta JSON (  ),  HTML
        if (inquiryContentEditor && typeof inquiryContentEditor.getContents === 'function') {
            formData.append('inquiryContent', JSON.stringify(inquiryContentEditor.getContents()));
        } else {
            formData.append('inquiryContent', contentEditor.innerHTML);
        }
        
        //    FormData 
        if (selectedFiles && selectedFiles.length > 0) {
            selectedFiles.forEach((file, index) => {
                formData.append(`file_${index}`, file);
            });
        }
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData // FormData   Content-Type   
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('.');
            //     
            selectedFiles = [];
            loadInquiryDetail(); // 
        } else {
            alert(' : ' + result.message);
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert('   .');
    }
}

function showLoading() {
    //   
}

function hideLoading() {
    //  
}

function showError(message) {
    alert(message);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
