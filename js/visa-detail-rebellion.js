/**
 * 비자 반려 페이지 JavaScript
 * 반려된 비자 재신청을 위한 서류 업로드 기능
 */

let rejectedVisaApplication = null;
let resubmissionFiles = {};
let rejectionReasons = [];
let requiredResubmitKeys = [];

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeVisaRejectionPage();
});

// 비자 반려 페이지 초기화
async function initializeVisaRejectionPage() {
    try {
        // 로그인 확인
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        // URL 파라미터에서 비자 신청 ID 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const applicationId = urlParams.get('applicationId') || urlParams.get('application_id') || urlParams.get('id');

        if (!applicationId) {
            alert('Unable to find visa application information.');
            history.back();
            return;
        }

        // 비자 신청 정보 로드
        await loadRejectedVisaApplication(applicationId, userId);

        // 재신청 폼 설정
        setupResubmissionForms();

        // 이벤트 리스너 설정
        setupEventListeners();

    } catch (error) {
        console.error('Visa rejection page init error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

// 반려된 비자 신청 정보 로드
async function loadRejectedVisaApplication(applicationId, userId) {
    try {
        showLoadingState();

        // API 호출
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
            rejectedVisaApplication = result.data;

            // 반려 상태인지 확인
            if (result.data.status !== 'rejected') {
                alert('This visa application is not in a returned state.');
                history.back();
                return;
            }

            renderRejectionInfo(result.data);
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

// 반려 정보 렌더링
function renderRejectionInfo(visaApplication) {
    try {
        // 반려 메시지 업데이트
        updateRejectionMessage(visaApplication);

        // 재신청 서류 목록 표시
        displayResubmissionDocuments(visaApplication.requiredDocuments || []);

    } catch (error) {
        console.error('Rejection info render error:', error);
        showErrorMessage('An error occurred while displaying information.');
    }
}

// 반료 메시지 업데이트
function updateRejectionMessage(visaApplication) {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        // Figma 디자인에 맞춰 간단하게 표시
        messageElement.innerHTML = `Your visa application has been rejected.<br>Please resubmit the documents below.`;
    }

    // 페이지 제목 업데이트
    const titleElement = document.querySelector('.title');
    if (titleElement) {
        titleElement.textContent = 'Visa Application';
    }
}

// 반료 사유 표시
function displayRejectionReasons(reasons) {
    const container = document.querySelector('.px20.pb20.mt24');
    if (!container) return;

    // 반료 사유가 있으면 표시
    if (reasons.length > 0) {
        const rejectionReasonsHTML = `
            <div class="rejection-reasons mt20 p16 bg-red-light border-radius-8">
                <div class="text fz14 fw600 lh22 red-dark mb12">Reason for return</div>
                <ul class="rejection-list">
                    ${reasons.map(reason => `
                        <li class="text fz12 fw400 lh18 gray66 mb4">• ${reason}</li>
                    `).join('')}
                </ul>
            </div>
        `;

        const messageDiv = container.querySelector('.text.fz20.fw600.lh28.black12');
        if (messageDiv && !container.querySelector('.rejection-reasons')) {
            messageDiv.insertAdjacentHTML('afterend', rejectionReasonsHTML);
        }
    }

    // 재신청 안내 메시지
    const resubmissionInfoHTML = `
        <div class="resubmission-info mt16 p16 bg-blue-light border-radius-8">
            <div class="text fz14 fw600 lh22 blue-dark mb8">Resubmission guide</div>
            <div class="text fz12 fw400 lh18 gray66">
                • Check the reason above and re-submit after addressing it.<br>
                • Upload the required documents again.<br>
                • File formats: JPG, PNG, PDF (max 10MB per file)
            </div>
        </div>
    `;

    if (!container.querySelector('.resubmission-info')) {
        const existingInfo = container.querySelector('.rejection-reasons') ||
                           container.querySelector('.text.fz20.fw600.lh28.black12');
        if (existingInfo) {
            existingInfo.insertAdjacentHTML('afterend', resubmissionInfoHTML);
        }
    }
}

// 재신청 서류 목록 표시
function displayResubmissionDocuments(requiredDocuments = []) {
    // 기본 필수 서류 목록 (HTML에 표시된 서류들)
    const defaultDocuments = [
        { key: 'photo', title: 'ID Photo (Within the last 6 months)', required: true },
        { key: 'passport', title: 'Passport copy', required: true }
    ];

    // 요구되는 서류가 있으면 사용하고, 없으면 기본 서류 사용
    const documentsToShow = requiredDocuments.length > 0 ? requiredDocuments : defaultDocuments;
    requiredResubmitKeys = documentsToShow.map(d => d.key).filter(Boolean);

    // 업로드 섹션 업데이트
    updateResubmissionUploadSections(documentsToShow);
}

// 재신청 업로드 섹션 업데이트
function updateResubmissionUploadSections(documents) {
    const uploadContainer = document.querySelector('.mt32');
    if (!uploadContainer) return;

    // 기존 내용 지우기
    uploadContainer.innerHTML = '';

    documents.forEach((docInfo, index) => {
        const sectionHTML = `
            <div class="document-section" data-document="${docInfo.key}" style="${index > 0 ? 'margin-top: 16px;' : ''}">
                <div class="text fz14 fw500 lh22" style="color: #6b6b6b;">
                    ${docInfo.title}
                </div>
                <div class="upload-wrapper" style="margin-top: 10px;">
                    <label for="file-resubmit-${docInfo.key}" class="upload-btn" data-document="${docInfo.key}" style="background: white; border: 1px solid #b0b0b0; display: inline-flex; gap: 4px; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                        <span class="upload-text text fz14 fw400 lh22" style="color: #4e4e4e;">Upload</span>
                        <img src="../images/ico_upload_black.svg" alt="" style="width: 16px; height: 16px;">
                    </label>
                    <input id="file-resubmit-${docInfo.key}" type="file" accept="image/*,.pdf" data-document="${docInfo.key}" style="display: none;">
                </div>
                <div class="uploaded-file-info" style="display: none;">
                    <div class="file-preview mt8 p12 bg-light-gray border-radius-4">
                        <span class="file-name"></span>
                        <button class="btn-remove-file" type="button" data-document="${docInfo.key}">×</button>
                    </div>
                </div>
                ${docInfo.note ? `<div class="text fz11 fw400 lh16 gray99 mt4">${docInfo.note}</div>` : ''}
            </div>
        `;

        uploadContainer.insertAdjacentHTML('beforeend', sectionHTML);
    });

    // 요구사항: 반려건 재제출 화면에서 "추가 서류" 입력란은 노출하지 않음
}

// 재신청 폼 설정
function setupResubmissionForms() {
    // 동적으로 생성되는 폼이므로 별도 설정 불필요
}

// 이벤트 리스너 설정
function setupEventListeners() {
    // 뒤로가기 버튼
    const backButton = document.querySelector('.btn-mypage');
    if (backButton) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            try {
                if (window.history && window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = 'visa-history.html';
                }
            } catch (_) {
                window.location.href = 'visa-history.html';
            }
        });
    }

    // 파일 업로드 이벤트 (동적으로 생성된 요소에 대해 이벤트 위임 사용)
    document.addEventListener('change', function(e) {
        if (e.target.type === 'file' && e.target.dataset.document) {
            handleResubmissionFileUpload(e);
        }
    });

    // 파일 제거 버튼
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-file')) {
            const documentKey = e.target.dataset.document;
            removeResubmissionFile(documentKey);
        }
    });

    // 저장 버튼 (재신청 제출)
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.textContent = 'Save';
        saveButton.addEventListener('click', handleResubmissionSubmit);
    }

    // 실시간 폼 유효성 검사
    setInterval(validateResubmissionForm, 500);
}

// 재신청 파일 업로드 처리
function handleResubmissionFileUpload(event) {
    const file = event.target.files[0];
    const documentKey = event.target.dataset.document;

    if (!file || !documentKey) return;

    // 파일 유효성 검사
    if (!validateFile(file)) {
        event.target.value = '';
        return;
    }

    // 업로드된 파일 정보 저장
    resubmissionFiles[documentKey] = file;

    // UI 업데이트
    updateResubmissionFileDisplay(documentKey, file);

    // 폼 유효성 검사
    validateResubmissionForm();
}

// 추가 서류 업로드: 요구사항에 따라 미지원 (UI/로직 모두 비활성화)
function handleAdditionalFilesUpload(_event) {
    return;
}

// 파일 유효성 검사
function validateFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];

    if (file.size > maxSize) {
        alert('File size must be 10MB or less.');
        return false;
    }

    if (!allowedTypes.includes(file.type)) {
        alert('Only JPG, PNG, GIF, and PDF files can be uploaded.');
        return false;
    }

    return true;
}

// 재신청 파일 표시 업데이트
function updateResubmissionFileDisplay(documentKey, file) {
    const section = document.querySelector(`[data-document="${documentKey}"]`);
    if (!section) return;

    const uploadBtn = section.querySelector('.upload-btn .upload-text');
    const fileInfo = section.querySelector('.uploaded-file-info');
    const fileName = section.querySelector('.file-name');

    if (uploadBtn && fileInfo && fileName) {
        uploadBtn.textContent = 'Change';
        fileInfo.style.display = 'block';
        fileName.textContent = `${file.name} (${formatFileSize(file.size)})`;
    }
}

// 재신청 파일 제거
function removeResubmissionFile(documentKey) {
    if (resubmissionFiles[documentKey]) {
        delete resubmissionFiles[documentKey];

        const section = document.querySelector(`[data-document="${documentKey}"]`);
        if (section) {
            const uploadBtn = section.querySelector('.upload-btn .upload-text');
            const fileInfo = section.querySelector('.uploaded-file-info');
            const fileInput = section.querySelector(`#file-resubmit-${documentKey}`);

            if (uploadBtn && fileInfo && fileInput) {
                uploadBtn.textContent = 'Upload';
                fileInfo.style.display = 'none';
                fileInput.value = '';
            }
        }

        validateResubmissionForm();
    }
}

// 재신청 제출 처리
async function handleResubmissionSubmit() {
    if (!validateResubmissionForm()) {
        alert('Please upload all required documents.');
        return;
    }

    if (!rejectedVisaApplication) {
        alert('Unable to find visa application information.');
        return;
    }

    // 요구사항: 반려건 재제출 화면에서 제출 클릭 시 "추가 요금" 등 불필요한 팝업이 뜨지 않아야 함
    // - confirm 팝업 제거하고 바로 제출 진행

    try {
        showSubmittingState();

        // 파일 업로드 처리
        const uploadResults = await uploadResubmissionFiles();

        // 새로운 비자 신청 생성
        const resubmissionResult = await createVisaResubmission(uploadResults);

        if (resubmissionResult.success) {
            alert('Your resubmission has been submitted successfully.');
            // 신규/레거시 응답 모두 대응
            const nextId =
                resubmissionResult.applicationId ||
                resubmissionResult.visaApplicationId ||
                resubmissionResult.data?.applicationId ||
                resubmissionResult.data?.visaApplicationId ||
                resubmissionResult.data?.resubmissionId ||
                resubmissionResult.data?.visaApplicationId ||
                rejectedVisaApplication?.applicationId ||
                rejectedVisaApplication?.visaApplicationId;
            window.location.href = `visa-detail-examination.html?applicationId=${encodeURIComponent(String(nextId || ''))}`;
        } else {
            alert(resubmissionResult.message || 'Failed to submit resubmission.');
        }

    } catch (error) {
        console.error('Resubmission submit error:', error);
        alert('An error occurred while submitting your resubmission.');
    } finally {
        hideSubmittingState();
    }
}

// 재신청 파일 업로드
async function uploadResubmissionFiles() {
    const uploadPromises = [];

    // 일반 서류 업로드
    for (const [documentKey, file] of Object.entries(resubmissionFiles)) {
        if (documentKey !== 'additional' && file) {
            const uploadPromise = uploadSingleFile(file, documentKey);
            uploadPromises.push(uploadPromise);
        }
    }

    const results = await Promise.all(uploadPromises);
    return results.filter(result => result !== null);
}

// 단일 파일 업로드
async function uploadSingleFile(file, documentKey) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'visa');
    formData.append('related_id', String(rejectedVisaApplication.applicationId || ''));
    formData.append('document_type', documentKey);
    formData.append('application_id', rejectedVisaApplication.applicationId);
    formData.append('resubmission', 'true');

    try {
        const response = await fetch('../backend/api/upload.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        if (response.ok) {
            const result = await response.json();
            return result.success ? {
                document_type: documentKey,
                file_url: (result?.data?.filePath || result?.data?.file_path || null),
                file_name: file.name
            } : null;
        }
    } catch (error) {
        console.error(`파일 업로드 실패: ${file.name}`, error);
    }

    // 시뮬레이션용 반환값
    return {
        document_type: documentKey,
        file_url: `uploads/visa/resubmission/${rejectedVisaApplication.applicationId}/${documentKey}_${file.name}`,
        file_name: file.name
    };
}

// 비자 재신청 생성
async function createVisaResubmission(uploadResults) {
    const resubmissionData = {
        original_application_id: rejectedVisaApplication.applicationId,
        destination: rejectedVisaApplication.destination,
        visa_type: rejectedVisaApplication.visaType,
        documents: uploadResults,
        resubmission: true
    };

    let result;
    if (window.api && window.api.createVisaResubmission) {
        result = await window.api.createVisaResubmission(resubmissionData);
    } else {
        const response = await fetch('../backend/api/visa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_resubmission',
                accountId: localStorage.getItem('userId'),
                ...resubmissionData
            })
        });
        result = await response.json();
    }

    return result;
}

// 재신청 폼 유효성 검사
function validateResubmissionForm() {
    // 반려된 항목(누락된 서류)만 재업로드하면 제출 가능
    const requiredDocs = Array.isArray(requiredResubmitKeys) && requiredResubmitKeys.length
        ? requiredResubmitKeys
        : ['photo', 'passport'];
    const hasAllRequired = requiredDocs.every(doc => resubmissionFiles[doc]);

    // 저장 버튼 상태 업데이트
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = !hasAllRequired;
        saveButton.style.opacity = hasAllRequired ? '1' : '0.5';
    }

    return hasAllRequired;
}

// 진행 상황 표시기 추가
function addRejectionProgressIndicator() {
    const container = document.querySelector('.px20.pb20.mt24');
    if (!container || container.querySelector('.progress-container')) return;

    const progressHTML = `
        <div class="progress-container mt32">
            <div class="progress-step-info">
                <div class="text fz14 fw600 lh22 black12 mb12">🔄 재신청 단계</div>
                <div class="progress-steps">
                    <div class="step completed">최초 신청</div>
                    <div class="step completed">심사</div>
                    <div class="step completed">반료</div>
                    <div class="step active">재신청</div>
                    <div class="step">재심사</div>
                    <div class="step">완료</div>
                </div>
            </div>
        </div>
        <style>
        .progress-steps {
            display: flex;
            gap: 6px;
            margin-top: 12px;
        }
        .step {
            padding: 4px 6px;
            border-radius: 12px;
            font-size: 10px;
            background: #f0f0f0;
            color: #999;
        }
        .step.completed {
            background: #4CAF50;
            color: white;
        }
        .step.active {
            background: #FF5722;
            color: white;
        }
        </style>
    `;

    container.insertAdjacentHTML('beforeend', progressHTML);
}

// 유틸리티 함수들
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showLoadingState() {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        messageElement.style.opacity = '0.6';
    }
}

function hideLoadingState() {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        messageElement.style.opacity = '1';
    }
}

function showSubmittingState() {
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Submitting...';
    }
}

function hideSubmittingState() {
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = false;
        saveButton.textContent = 'Save';
    }
}

function showErrorMessage(message) {
    alert(message);
}

// 외부 사용을 위한 함수 내보내기
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadRejectedVisaApplication,
        handleResubmissionFileUpload,
        handleResubmissionSubmit
    };
}