/**
 *     JavaScript
 *         
 */

let currentVisaApplication = null;
let __normalizedDocumentsForDownload = [];

//    
document.addEventListener('DOMContentLoaded', function() {
    initializeVisaExaminationPage();
});

//    
async function initializeVisaExaminationPage() {
    try {
        //  
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        // URL    ID 
        const urlParams = new URLSearchParams(window.location.search);
        const applicationId = urlParams.get('applicationId') || urlParams.get('application_id') || urlParams.get('id');

        if (!applicationId) {
            alert('Unable to find visa application information.');
            history.back();
            return;
        }

        //    
        await loadVisaApplicationDetails(applicationId, userId);

        //   
        setupEventListeners();

    } catch (error) {
        console.error('Visa examination page init error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

//    
async function loadVisaApplicationDetails(applicationId, userId) {
    try {
        showLoadingState();

        // API 
        const result = (window.api && window.api.getVisaApplication)
            ? await window.api.getVisaApplication(applicationId, userId)
            : await (async () => {
                const response = await fetch('../backend/api/visa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_visa_application',
                        visaApplicationId: applicationId,
                        accountId: userId
                    })
                });
                return await response.json();
            })();

        if (result.success && result.data) {
            currentVisaApplication = result.data;

            //      
            if (result.data.status !== 'processing' && result.data.status !== 'under_review') {
                alert('This visa application is not currently under review.');
                history.back();
                return;
            }

            renderVisaExaminationInfo(result.data);
        } else {
            showErrorMessage(result.message || 'Failed to load visa application information.');
        }

    } catch (error) {
        console.error('Visa application load error:', error);
        showErrorMessage('An error occurred while loading visa application information.');
    } finally {
        hideLoadingState();
    }
}

//    
function renderVisaExaminationInfo(visaApplication) {
    try {
        //   
        updateExaminationMessage(visaApplication);

        //   
        displaySubmittedDocuments(visaApplication.documents || []);

        //     ( )
        displayExpectedDate(visaApplication.expectedDate);

        //    
        updateProgressIndicator();

    } catch (error) {
        console.error('Visa examination render error:', error);
        showErrorMessage('An error occurred while displaying visa information.');
    }
}

//   
function updateExaminationMessage(visaApplication) {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement && visaApplication.destination) {
        messageElement.innerHTML = `Your ${visaApplication.destination} visa application is under review<br>based on the documents you submitted.`;
    }

    //   
    const titleElement = document.querySelector('.title');
    if (titleElement && visaApplication.destination) {
        titleElement.textContent = `${visaApplication.destination} - Under Review`;
    }
}

//
function displaySubmittedDocuments(documents) {
    //
    const documentContainer = document.querySelector('.mt32');
    if (!documentContainer) return;

    //
    documentContainer.innerHTML = '';

    // visaType 확인 (individual이면 문서 없이 Documents Send 상태만 표시)
    const visaType = (currentVisaApplication?.visaType || 'group').toLowerCase();

    if (visaType === 'individual') {
        // Individual visa: Documents Send 상태만 표시
        // visaSend can be integer (1/0) or string ('yes'/'no')
        const visaSendRaw = currentVisaApplication?.visaSend;
        const visaSendLabel = (visaSendRaw === 1 || visaSendRaw === '1' || String(visaSendRaw).toLowerCase() === 'yes') ? 'Yes' : 'No';

        const individualHTML = `
            <div class="text fz14 fw500 lh22 black4e">Documents Send</div>
            <div class="mt10 p16" style="background: #f8f9fa; border-radius: 8px;">
                <span class="text fz16 fw600">${visaSendLabel}</span>
            </div>
        `;
        documentContainer.insertAdjacentHTML('beforeend', individualHTML);
        return;
    }

    // Group visa: 업로드된 문서만 표시
    // documents를 배열로 정규화
    const normalizedDocuments = (() => {
        if (Array.isArray(documents)) return documents;
        if (documents && typeof documents === 'object') {
            return Object.entries(documents)
                .filter(([key, val]) => val && String(val).trim() !== '')
                .map(([key, val]) => {
                    const url = String(val);
                    const name = url.split('/').pop() || key;
                    const ext = name.includes('.') ? name.split('.').pop() : '';
                    return {
                        type: key,
                        name: name,
                        url: url,
                        file_url: url,
                        size: 0,
                        ext: ext
                    };
                });
        }
        return [];
    })();
    __normalizedDocumentsForDownload = normalizedDocuments;

    // Group visa 문서 설정
    const documentSections = [
        { key: 'passport', title: 'Passport bio page', defaultName: 'Passport bio page' },
        { key: 'visaApplicationForm', title: 'Visa Application Form', defaultName: 'Visa Application Form' },
        { key: 'bankCertificate', title: 'Bank Certificate', defaultName: 'Bank Certificate' },
        { key: 'bankStatement', title: 'Bank Statement', defaultName: 'Bank Statement' },
        { key: 'additionalDocuments', title: 'Additional requirements', defaultName: 'Additional requirements' }
    ];

    let hasDocuments = false;

    documentSections.forEach((section, index) => {
        // 해당 키에 맞는 문서 찾기 (업로드된 것만)
        const doc = normalizedDocuments.find(d => d.type === section.key);
        if (!doc || !doc.url) return; // 업로드된 문서가 없으면 스킵

        hasDocuments = true;
        const url = doc.url || doc.file_url || '';

        const sectionHTML = `
            <div class="text fz14 fw500 lh22 black4e ${hasDocuments ? 'mt16' : ''}">${section.title}</div>
            <div class="download-wrapper mt10">
                <button class="btn-download" type="button" data-document-id="${doc.id || index}" data-document-url="${url ? String(url).replace(/"/g, '&quot;') : ''}">
                    <img src="../images/ico_document.svg" alt="">
                    <span style="word-break: break-all; overflow-wrap: anywhere; padding-right: 40px;">${doc.name}<br> [${doc.ext || 'file'}, ${formatFileSize(doc.size || 0)}]</span>
                </button>
            </div>
        `;

        documentContainer.insertAdjacentHTML('beforeend', sectionHTML);
    });

    if (!hasDocuments) {
        documentContainer.innerHTML = '<div class="text fz14 fw400 lh22 black4e">No documents uploaded.</div>';
    }
}

//    
function displayExpectedDate(expectedDate) {
    if (!expectedDate) return;

    const container = document.querySelector('.mt32');
    if (!container) return;

    const expectedDateElement = container.querySelector('.bg-light-blue');
    if (!expectedDateElement) {
        //  displaySubmittedDocuments 
        return;
    }
}

//    
function updateProgressIndicator() {
    //      
    const container = document.querySelector('.px20.pb20.mt24');
    if (!container) return;

    const progressHTML = `
        <div class="progress-container mt32">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <div class="step-circle"></div>
                    <div class="step-label"> </div>
                </div>
                <div class="progress-step active">
                    <div class="step-circle"></div>
                    <div class="step-label"> </div>
                </div>
                <div class="progress-step">
                    <div class="step-circle"></div>
                    <div class="step-label"> </div>
                </div>
            </div>
        </div>
        <style>
        .progress-container {
            margin: 32px 0;
        }
        .progress-bar {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            border: 3px solid #e0e0e0;
            margin-bottom: 8px;
        }
        .progress-step.completed .step-circle {
            background: #4CAF50;
            border-color: #4CAF50;
        }
        .progress-step.active .step-circle {
            background: #2196F3;
            border-color: #2196F3;
            animation: pulse 1.5s infinite;
        }
        .step-label {
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        </style>
    `;

    container.insertAdjacentHTML('beforeend', progressHTML);
}

//   
function setupEventListeners() {
    //   
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-download')) {
            const button = e.target.closest('.btn-download');
            const documentId = button.dataset.documentId;
            handleDocumentDownload(documentId);
        }
    });

    //   ()
    addRefreshButton();
}

//   
function addRefreshButton() {
    const container = document.querySelector('.px20.pb20.mt24');
    if (!container) return;

    const refreshButtonHTML = `
        <div class="text-center mt40">
            <button class="btn line md refresh-button" type="button">
                 
            </button>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', refreshButtonHTML);

    const refreshButton = container.querySelector('.refresh-button');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            location.reload();
        });
    }
}

//   
async function handleDocumentDownload(documentId) {
    if (!currentVisaApplication) return;

    try {
        //  data-document-url   
        const btn = document.querySelector(`.btn-download[data-document-id="${String(documentId)}"]`);
        const url = btn ? (btn.dataset.documentUrl || '') : '';
        const fileUrl = url || '';

        if (!fileUrl) {
            alert('No file information available for download.');
            return;
        }

        //   /uploads/...     (/ )
        if (fileUrl.startsWith('/uploads/') || fileUrl.startsWith('uploads/')) {
            window.open(`../backend/api/download.php?file=${encodeURIComponent(fileUrl)}`, '_blank');
            return;
        }

        //  URL     
        window.open(fileUrl, '_blank');

    } catch (error) {
        console.error('Document download error:', error);
        alert('An error occurred while downloading the document.');
    }
}

//   
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

//   
function showLoadingState() {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        messageElement.style.opacity = '0.6';
    }
}

//   
function hideLoadingState() {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        messageElement.style.opacity = '1';
    }
}

//   
function showErrorMessage(message) {
    alert(message);
}

//     
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadVisaApplicationDetails,
        renderVisaExaminationInfo,
        handleDocumentDownload
    };
}