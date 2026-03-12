/**
 *    JavaScript
 *     
 */

let currentNotice = null;

function getActiveLanguage() {
    try {
        if (typeof getCurrentLanguage === 'function') return getCurrentLanguage();
    } catch (e) {}
    return localStorage.getItem('selectedLanguage') || 'ko';
}

function pickLocalizedText(value) {
    if (value == null) return '';
    if (typeof value !== 'string') return String(value);
    const s = value.trim();
    if (!s) return '';
    //   {"ko":"...","en":"...","tl":"..."}    
    if (s.startsWith('{') && s.endsWith('}')) {
        try {
            const obj = JSON.parse(s);
            const lang = getActiveLanguage();
            if (obj && typeof obj === 'object') {
                return String(obj[lang] ?? obj.ko ?? obj.en ?? obj.tl ?? value);
            }
        } catch (e) {
            // ignore
        }
    }
    return value;
}

function pickLocalizedAsset(value) {
    return pickLocalizedText(value);
}

//    
document.addEventListener('DOMContentLoaded', function() {
    initializeNoticeDetailPage();
});

//    
async function initializeNoticeDetailPage() {
    try {
        // URL   ID 
        const urlParams = new URLSearchParams(window.location.search);
        const noticeId = urlParams.get('noticeId') || urlParams.get('notice_id') || urlParams.get('id');

        if (!noticeId) {
            // ID     (HTML   )
            return;
        }

        //    
        await loadNoticeDetail(noticeId);
        //  ( )
        incrementViewCount(noticeId);

    } catch (error) {
        console.error('    :', error);
        showErrorMessage('    .');
    }
}

//    
async function loadNoticeDetail(noticeId) {
    try {
        showLoadingState();

        // API 
        let result;
        if (window.api && window.api.getNotice) {
            result = await window.api.getNotice(noticeId);
        } else {
            const response = await fetch('../backend/api/notice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_notice',
                    noticeId: noticeId
                })
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            currentNotice = result.data;
            renderNoticeDetail(result.data);
        } else {
            showErrorMessage(result.message || '   .');
        }

    } catch (error) {
        console.error('  :', error);
        showErrorMessage('    .');
    } finally {
        hideLoadingState();
    }
}

//    
function renderNoticeDetail(notice) {
    try {
        //   
        updatePageTitle(notice.title);

        //   
        updateNoticeContent(notice);

        //    ( )
        if (notice.attachments && notice.attachments.length > 0) {
            displayAttachments(notice.attachments);
        }

    } catch (error) {
        console.error('  :', error);
        showErrorMessage('    .');
    }
}

//   
function updatePageTitle(title) {
    const titleElement = document.querySelector('.title');
    if (titleElement) {
        titleElement.textContent = '';
    }
}

//   
function updateNoticeContent(notice) {
    //  
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        titleElement.textContent = pickLocalizedText(notice.title || notice.subject);
    }

    //  
    const dateElement = document.querySelector('.text.fz13.fw400.lh19.gray96');
    if (dateElement) {
        // API (createdAt/publishedAt/created_at)
        dateElement.textContent = formatDate(
            notice.publishedAt || notice.createdAt || notice.created_at || notice.date || notice.updatedAt || notice.updated_at
        );
    }

    //  
    const contentElement = document.querySelector('.text.fz14.fw500.lh22.black12.mt14');
    if (contentElement) {
        const imgUrlRaw = pickLocalizedAsset(notice.imageUrl || '');
        const imgUrl = imgUrlRaw ? (String(imgUrlRaw).startsWith('/') ? String(imgUrlRaw) : `/${String(imgUrlRaw)}`) : '';
        const imgHtml = imgUrl ? `<div class="mt14"><img src="${escapeHtml(imgUrl)}" alt="" style="width:100%; height:auto; border-radius: 12px;"></div>` : '';
        const contentHtml = formatNoticeContent(pickLocalizedText(notice.content || notice.description));
        contentElement.innerHTML = imgHtml + contentHtml;
    }

    //    ( )
    updateViewCount(notice.view_count);
}

//   
function displayAttachments(attachments) {
    const contentContainer = document.querySelector('.px20.pb20.mt24');
    if (!contentContainer) return;

    const attachmentsHTML = `
        <div class="attachments-section mt32 pt20 border-top">
            <div class="text fz14 fw600 lh22 black12 mb12">📎 </div>
            <div class="attachments-list">
                ${attachments.map(attachment => `
                    <div class="attachment-item p12 mb8 bg-light-gray border-radius-8">
                        <div class="align both vm">
                            <div class="text fz14 fw400 lh22 black12">${attachment.name}</div>
                            <a href="${attachment.url}" class="btn line xs" download="${attachment.name}">
                                
                            </a>
                        </div>
                        <div class="text fz12 fw400 lh18 gray99 mt4">
                            ${formatFileSize(attachment.size)} • ${formatDate(attachment.created_at)}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    contentContainer.insertAdjacentHTML('beforeend', attachmentsHTML);
}

//  
function updateViewCount(viewCount) {
    if (!viewCount) return;

    const dateElement = document.querySelector('.text.fz13.fw400.lh19.gray96');
    if (dateElement) {
        const currentText = dateElement.textContent;
        dateElement.textContent = `${currentText} •  ${viewCount}`;
    }
}

//   
function formatNoticeContent(content) {
    if (!content) return '';

    const s = String(content);
    // Quill/  HTML    
    if (/[<][a-z!\/]/i.test(s)) {
        return s;
    }
    //    <br> 
    return s.replace(/\n/g, '<br>').replace(/\r/g, '').trim();
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

//  
function formatDate(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

//   
function formatFileSize(bytes) {
    if (!bytes) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

//   
function showLoadingState() {
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        titleElement.style.opacity = '0.6';
    }
}

//   
function hideLoadingState() {
    const titleElement = document.querySelector('.text.fz16.fw600.lh24.black12');
    if (titleElement) {
        titleElement.style.opacity = '1';
    }
}

//   
function showErrorMessage(message) {
    alert(message);
}

//   
async function incrementViewCount(noticeId) {
    try {
        if (window.api && window.api.incrementNoticeViewCount) {
            await window.api.incrementNoticeViewCount(noticeId);
        } else {
            await fetch('../backend/api/notice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'increment_view_count',
                    noticeId: noticeId
                })
            });
        }
    } catch (error) {
        console.error('  :', error);
        //      
    }
}

//     
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadNoticeDetail,
        renderNoticeDetail,
        incrementViewCount
    };
}