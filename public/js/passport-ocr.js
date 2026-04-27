/**
 * Passport OCR Module
 * 여권 사진 업로드 시 AWS Textract를 이용한 자동 정보 추출
 *
 * 사용법:
 *   1. 페이지에서 onOcrConfirm 콜백 등록:
 *      window.__ocrOnConfirm = function(index, data, file) { ... };
 *   2. 업로드 핸들러에서 handlePassportUploadWithOcr(index, input) 호출
 */

// OCR 모달 상태
let __ocrCurrentIndex = null;
let __ocrCurrentFile = null;
let __ocrCurrentBase64 = null;

/**
 * 여권 업로드 시 OCR 처리
 */
function handlePassportUploadWithOcr(index, input) {
    const file = input.files[0];
    if (!file) return;

    // 파일 크기 체크 (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        input.value = '';
        return;
    }

    // 이미지 파일만 허용
    if (!file.type.startsWith('image/')) {
        alert('Only image files are supported');
        input.value = '';
        return;
    }

    // base64 변환 후 OCR 모달 오픈
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        openPassportOcrModal(index, base64, file);
    };
    reader.readAsDataURL(file);
}

/**
 * OCR 모달 열기 + OCR 요청 시작
 */
function openPassportOcrModal(index, base64, file) {
    __ocrCurrentIndex = index;
    __ocrCurrentFile = file;
    __ocrCurrentBase64 = base64;

    const modal = document.getElementById('passportOcrModal');
    if (!modal) {
        console.error('Passport OCR modal not found');
        // 모달 없으면 기존 방식으로 폴백
        _triggerOcrCallback(index, null, file, base64);
        return;
    }

    // 상태 초기화
    document.getElementById('passportOcrLoading').style.display = 'flex';
    document.getElementById('passportOcrError').style.display = 'none';
    document.getElementById('passportOcrResult').style.display = 'none';

    // 미리보기 이미지 설정 + 확대 초기화
    document.getElementById('passportOcrPreviewImg').src = base64;
    const imgPanel = document.getElementById('passportOcrImagePanel');
    if (imgPanel) imgPanel.classList.remove('zoomed');

    // 모달 표시
    modal.style.display = 'flex';

    // OCR 요청
    performOcrRequest(file);
}

/**
 * OCR API 요청
 */
async function performOcrRequest(file) {
    try {
        const formData = new FormData();
        formData.append('passport_image', file);

        const response = await fetch('/backend/api/passport-ocr.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success && result.data) {
            displayOcrResults(result.data, result.confidences || {});
        } else {
            showOcrError(result.message || 'OCR processing failed');
        }
    } catch (err) {
        console.error('OCR request failed:', err);
        showOcrError('Network error. Please check your connection.');
    }
}

/**
 * OCR 결과를 모달 필드에 표시
 */
function displayOcrResults(data, confidences) {
    document.getElementById('passportOcrLoading').style.display = 'none';
    document.getElementById('passportOcrError').style.display = 'none';
    document.getElementById('passportOcrResult').style.display = 'block';

    const fields = [
        'firstName', 'lastName', 'middleName', 'gender', 'dateOfBirth',
        'passportNumber', 'passportIssueDate', 'passportExpiryDate', 'nationality'
    ];

    fields.forEach(field => {
        const input = document.getElementById('ocr_' + field);
        const badge = document.getElementById('conf_' + field);

        if (input) {
            input.value = data[field] || '';
        }

        if (badge) {
            const conf = confidences[field];
            if (conf !== undefined && conf !== null) {
                badge.textContent = Math.round(conf) + '%';
                badge.style.display = 'inline-block';
                if (conf >= 90) {
                    badge.className = 'ocr-confidence-badge confidence-high';
                } else if (conf >= 70) {
                    badge.className = 'ocr-confidence-badge confidence-medium';
                } else {
                    badge.className = 'ocr-confidence-badge confidence-low';
                }
            } else {
                badge.style.display = 'none';
            }
        }
    });
}

/**
 * 이미지 뷰어 사이드 패널 열기 (OCR 모달 내 왼쪽에 표시)
 */
let __viewerScale = 1;
let __viewerPanX = 0;
let __viewerPanY = 0;
let __viewerDragging = false;
let __viewerDragStartX = 0;
let __viewerDragStartY = 0;
let __viewerPanStartX = 0;
let __viewerPanStartY = 0;

function _updateViewerTransform() {
    const img = document.getElementById('passportViewerImg');
    if (img) img.style.transform = `translate(${__viewerPanX}px, ${__viewerPanY}px) scale(${__viewerScale})`;
}

function openPassportImageViewer() {
    const src = document.getElementById('passportOcrPreviewImg')?.src;
    if (!src) return;

    const panel = document.getElementById('passportViewerSide');
    const img = document.getElementById('passportViewerImg');
    const modalContent = document.querySelector('.passport-ocr-modal-content');
    if (!panel || !img) return;

    img.src = src;
    __viewerScale = 1;
    __viewerPanX = 0;
    __viewerPanY = 0;
    _updateViewerTransform();
    document.getElementById('passportViewerZoomLevel').textContent = '100%';

    // 사이드 패널 표시 + 모달 확장
    panel.classList.add('active');
    if (modalContent) modalContent.classList.add('viewer-open');

    // 스크롤 확대/축소 이벤트
    const body = document.getElementById('passportViewerBody');
    body.onwheel = function(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        passportViewerZoom(delta);
    };

    // 드래그 이벤트
    img.onmousedown = function(e) {
        e.preventDefault();
        __viewerDragging = true;
        __viewerDragStartX = e.clientX;
        __viewerDragStartY = e.clientY;
        __viewerPanStartX = __viewerPanX;
        __viewerPanStartY = __viewerPanY;
        img.style.cursor = 'grabbing';
    };
    img.style.cursor = 'grab';
    document.addEventListener('mousemove', _onViewerMouseMove);
    document.addEventListener('mouseup', _onViewerMouseUp);
}

function _onViewerMouseMove(e) {
    if (!__viewerDragging) return;
    __viewerPanX = __viewerPanStartX + (e.clientX - __viewerDragStartX);
    __viewerPanY = __viewerPanStartY + (e.clientY - __viewerDragStartY);
    _updateViewerTransform();
}

function _onViewerMouseUp() {
    if (!__viewerDragging) return;
    __viewerDragging = false;
    const img = document.getElementById('passportViewerImg');
    if (img) img.style.cursor = 'grab';
}

function closePassportImageViewer() {
    const panel = document.getElementById('passportViewerSide');
    const modalContent = document.querySelector('.passport-ocr-modal-content');
    if (panel) panel.classList.remove('active');
    if (modalContent) modalContent.classList.remove('viewer-open');
    const body = document.getElementById('passportViewerBody');
    if (body) body.onwheel = null;
    document.removeEventListener('mousemove', _onViewerMouseMove);
    document.removeEventListener('mouseup', _onViewerMouseUp);
}

function passportViewerZoom(delta) {
    __viewerScale = Math.max(0.3, Math.min(5, __viewerScale + delta));
    _updateViewerTransform();
    const label = document.getElementById('passportViewerZoomLevel');
    if (label) label.textContent = Math.round(__viewerScale * 100) + '%';
}

function passportViewerReset() {
    __viewerScale = 1;
    __viewerPanX = 0;
    __viewerPanY = 0;
    _updateViewerTransform();
    document.getElementById('passportViewerZoomLevel').textContent = '100%';
}

/**
 * OCR 에러 표시
 */
function showOcrError(message) {
    document.getElementById('passportOcrLoading').style.display = 'none';
    document.getElementById('passportOcrError').style.display = 'flex';
    document.getElementById('passportOcrResult').style.display = 'none';
    document.getElementById('passportOcrErrorMsg').textContent = message;
}

/**
 * OCR 재시도
 */
function retryPassportOcr() {
    if (!__ocrCurrentFile) return;

    document.getElementById('passportOcrLoading').style.display = 'flex';
    document.getElementById('passportOcrError').style.display = 'none';
    document.getElementById('passportOcrResult').style.display = 'none';

    performOcrRequest(__ocrCurrentFile);
}

/**
 * OCR 확인 — 모달에서 값을 읽어 콜백으로 전달
 */
function confirmPassportOcr() {
    const index = __ocrCurrentIndex;
    if (index === null) return;

    // OCR 필드에서 값 읽기
    const ocrData = {
        firstName: document.getElementById('ocr_firstName')?.value?.trim() || '',
        lastName: document.getElementById('ocr_lastName')?.value?.trim() || '',
        middleName: document.getElementById('ocr_middleName')?.value?.trim() || '',
        gender: document.getElementById('ocr_gender')?.value || '',
        dateOfBirth: document.getElementById('ocr_dateOfBirth')?.value || '',
        passportNumber: document.getElementById('ocr_passportNumber')?.value?.trim() || '',
        passportIssueDate: document.getElementById('ocr_passportIssueDate')?.value || '',
        passportExpiryDate: document.getElementById('ocr_passportExpiryDate')?.value || '',
        nationality: document.getElementById('ocr_nationality')?.value?.trim() || '',
    };

    // 콜백으로 전달
    _triggerOcrCallback(index, ocrData, __ocrCurrentFile, __ocrCurrentBase64);

    // 모달 닫기
    closePassportOcrModal();
}

/**
 * OCR 실패 시 이미지만 저장하고 모달 닫기 (수동 입력)
 */
function skipOcrAndKeepImage() {
    const index = __ocrCurrentIndex;
    if (index === null) return;

    // ocrData=null → 이미지만 저장
    _triggerOcrCallback(index, null, __ocrCurrentFile, __ocrCurrentBase64);

    // 모달 닫기
    closePassportOcrModal();
}

/**
 * OCR 모달 닫기
 */
function closePassportOcrModal() {
    // 사이드 뷰어도 함께 닫기
    closePassportImageViewer();

    const modal = document.getElementById('passportOcrModal');
    if (modal) modal.style.display = 'none';

    __ocrCurrentIndex = null;
    __ocrCurrentFile = null;
    __ocrCurrentBase64 = null;
}

/**
 * 콜백 실행 — 각 페이지에서 window.__ocrOnConfirm을 등록하여 사용
 * @param {number} index - traveler 인덱스
 * @param {object|null} ocrData - OCR 추출 데이터 (null이면 이미지만)
 * @param {File} file - 업로드된 파일
 * @param {string} base64 - base64 이미지 데이터
 */
function _triggerOcrCallback(index, ocrData, file, base64) {
    if (typeof window.__ocrOnConfirm === 'function') {
        window.__ocrOnConfirm(index, ocrData, file, base64);
    } else {
        console.warn('OCR callback not registered (window.__ocrOnConfirm)');
    }
}
