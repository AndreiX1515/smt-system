/**
 * Agent Admin - Register Inquiry Page JavaScript
 */

document.addEventListener('DOMContentLoaded', async function() {
    //  
    try {
        const sessionResponse = await fetch('../backend/api/check-session.php', {
            credentials: 'same-origin'
        });
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.authenticated) {
            window.location.href = '../index.html';
            return;
        }
    } catch (error) {
        console.error('Session check error:', error);
        window.location.href = '../index.html';
        return;
    }
    
    //   
    initFileUpload();
    
    //   
    const saveButton = document.getElementById('saveInquiryBtn');
    if (saveButton) {
        saveButton.addEventListener('click', handleSave);
    }
});

//    
let selectedFiles = [];

//   
function initFileUpload() {
    const fileUploadBtn = document.getElementById('file-upload-btn');
    const fileInput = document.getElementById('file-upload');
    const attachList = document.getElementById('attach-list');
    
    if (!fileUploadBtn || !fileInput || !attachList) return;
    
    //    
    fileUploadBtn.addEventListener('click', function() {
        fileInput.click();
    });
    
    //  
    fileInput.addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            //  
            if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
                addFileToList(file, selectedFiles.length - 1);
            }
        });
        //       
        e.target.value = '';
    });
    
    //   
    function addFileToList(file, index) {
        const fileItem = document.createElement('div');
        fileItem.className = 'attach-item';
        fileItem.dataset.fileIndex = index;
        fileItem.dataset.fileName = file.name;
        
        //   
        const formatFileSize = (bytes) => {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + 'B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + 'KB';
            return (bytes / (1024 * 1024)).toFixed(0) + 'MB';
        };
        
        fileItem.innerHTML = `
            <span class="attach-item-name">${escapeHtml(file.name)} (${formatFileSize(file.size)})</span>
            <button type="button" class="attach-item-remove" aria-label="">×</button>
        `;
        
        //   
        const removeBtn = fileItem.querySelector('.attach-item-remove');
        removeBtn.addEventListener('click', function() {
            const fileIndex = parseInt(fileItem.dataset.fileIndex);
            selectedFiles.splice(fileIndex, 1);
            fileItem.remove();
            //  
            updateFileIndices();
        });
        
        attachList.appendChild(fileItem);
    }
    
    //   
    function updateFileIndices() {
        const fileItems = attachList.querySelectorAll('.attach-item');
        fileItems.forEach((item, index) => {
            item.dataset.fileIndex = index;
        });
    }
}

//  
async function handleSave() {
    try {
        const titleInput = document.getElementById('q-title');
        const editorArea = document.querySelector('.jw-editor .jweditor');
        
        if (!titleInput?.value.trim()) {
            alert(' .');
            titleInput.focus();
            return;
        }
        
        const content = editorArea?.innerHTML || '';
        if (!content.trim()) {
            alert(' .');
            editorArea?.focus();
            return;
        }
        
        // FormData  (  )
        const formData = new FormData();
        formData.append('action', 'registerInquiry');
        formData.append('inquiryTitle', titleInput.value.trim());
        formData.append('inquiryContent', content);
        
        //   FormData 
        if (selectedFiles && selectedFiles.length > 0) {
            selectedFiles.forEach((file, index) => {
                formData.append(`file_${index}`, file);
            });
        }
        
        const response = await fetch('../backend/api/agent-api.php', {
            method: 'POST',
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
        console.error('Error saving:', error);
        alert('    .');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
