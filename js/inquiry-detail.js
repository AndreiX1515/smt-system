/**
 * 문의 상세 페이지 JavaScript
 * 문의 상세 정보 조회, 답변 표시, 수정/삭제 기능 처리
 */

let currentInquiry = null;
let isAnswerCompleted = false;

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeInquiryDetailPage();
});

// 문의 상세 페이지 초기화
async function initializeInquiryDetailPage() {
    try {
        // 다국어 텍스트 로드(가능한 경우)
        if (typeof loadServerTexts === 'function') {
            await loadServerTexts();
        }

        // 로그인 확인 (localStorage가 비어있어도 서버 세션으로 보완)
        let isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        let userId = localStorage.getItem('userId');
        if (!isLoggedIn || !userId) {
            try {
                const res = await fetch('../backend/api/check-session.php', { credentials: 'include' });
                const json = await res.json().catch(() => ({}));
                const sid = json?.user?.id || json?.user?.accountId || json?.userId;
                if (json?.isLoggedIn && sid) {
                    isLoggedIn = true;
                    userId = String(sid);
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userId', userId);
                    if (json?.user?.email) localStorage.setItem('userEmail', json.user.email);
                    if (json?.user?.accountType) localStorage.setItem('accountType', json.user.accountType);
                }
            } catch (_) {}
        }

        if (!isLoggedIn || !userId) {
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        // URL 파라미터에서 문의 ID 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const inquiryId = urlParams.get('inquiryId') || urlParams.get('inquiry_id');

        if (!inquiryId) {
            alert('Inquiry not found.');
            history.back();
            return;
        }

        // 문의 상세 정보 로드
        await loadInquiryDetails(inquiryId, userId);

        // 이벤트 리스너 설정
        setupEventListeners();

    } catch (error) {
        console.error('Inquiry detail init error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

// 문의 상세 정보 로드
async function loadInquiryDetails(inquiryId, userId) {
    try {
        showLoadingState();

        // API 호출
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
                })
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            currentInquiry = result.data;
            renderInquiryDetails(result.data);
        } else {
            showErrorMessage(result.message || 'Unable to load inquiry.');
        }

    } catch (error) {
        console.error('Load inquiry detail error:', error);
        showErrorMessage('An error occurred while loading inquiry.');
    } finally {
        hideLoadingState();
    }
}

// 문의 상세 정보 렌더링
function renderInquiryDetails(inquiry) {
    try {
        // 답변 완료 여부(상태 + replies) 판단
        const st = String(inquiry?.status || inquiry?.dbStatus || '').toLowerCase();
        isAnswerCompleted = (st !== 'pending' && st !== 'open' && st !== 'in_progress') || ((inquiry?.replies || []).length > 0);

        // 답변 상태 업데이트
        updateInquiryStatus(inquiry.status);

        // 문의 제목 및 기본 정보 업데이트
        updateInquiryBasicInfo(inquiry);

        // 문의 내용 업데이트
        updateInquiryContent(inquiry);

        // 첨부파일 업데이트
        updateAttachments(inquiry.attachments || []);

        // 답변 정보 업데이트
        updateReplies(inquiry.replies || []);

        // 답변 완료 시 Edit 비활성화
        updateEditAvailability(!isAnswerCompleted);

    } catch (error) {
        console.error('Render inquiry detail error:', error);
        showErrorMessage('An error occurred while displaying inquiry.');
    }
}

function updateEditAvailability(canEdit) {
    const editButton = document.querySelector('.btn-edit');
    if (!editButton) return;

    if (canEdit) {
        editButton.disabled = false;
        editButton.removeAttribute('aria-disabled');
        editButton.classList.remove('inactive');
        editButton.style.pointerEvents = '';
        editButton.style.opacity = '';
        return;
    }

    editButton.disabled = true;
    editButton.setAttribute('aria-disabled', 'true');
    // 기존 스타일 시스템에 inactive가 있으면 사용
    editButton.classList.add('inactive');
    // 버튼 컴포넌트가 disabled 스타일을 안 먹는 경우 대비
    editButton.style.pointerEvents = 'none';
    editButton.style.opacity = '0.45';
}

// 문의 상태 업데이트
function updateInquiryStatus(status) {
    const statusElements = document.querySelectorAll('.label.secondary, .label.primary');

    statusElements.forEach(element => {
        element.style.display = 'none';
    });

    // If replies exist, treat as answered even if legacy status wasn't updated.
    // This prevents: list = \"Answer completed\" but detail = \"Waiting for answer\" (#153)
    const effectiveAnswered = (typeof isAnswerCompleted === 'boolean' && isAnswerCompleted === true);

    if (!effectiveAnswered && (status === 'pending' || status === 'open' || status === 'in_progress')) {
        const pendingElement = document.querySelector('.label.secondary');
        if (pendingElement) {
            pendingElement.style.display = 'inline-flex';
            pendingElement.textContent = 'Waiting for answer';
        }
    } else {
        const repliedElement = document.querySelector('.label.primary');
        if (repliedElement) {
            repliedElement.style.display = 'inline-flex';
            repliedElement.textContent = 'Answer completed';
        }
    }
}

// 문의 기본 정보 업데이트
function updateInquiryBasicInfo(inquiry) {
    // 문의 제목
    const titleElement = document.getElementById('inquiryTitle') || document.querySelector('.text.fz16.fw500.lh26.black12');
    const title = inquiry.title || inquiry.subject || 'Inquiry Title';
    if (titleElement) {
        titleElement.textContent = title;
    }

    // 문의 카테고리 및 날짜
    const categoryElement = document.getElementById('inquiryCategory') || document.querySelector('.text.fz13.fw400.lh19.gray96');
    if (categoryElement) {
        const categoryText = getCategoryText(inquiry.category || inquiry.inquiryType);
        categoryElement.textContent = categoryText;
    }

    const dateElement = document.getElementById('inquiryDate') || document.querySelector('.text.fz13.fw400.lh19.gray96.ico-bar');
    if (dateElement) {
        dateElement.textContent = formatDate(inquiry.createdAt);
    }

    // 이메일/번호/문의번호
    const emailEl = document.getElementById('inquiryEmail');
    if (emailEl) {
        const email = inquiry.emailAddress || inquiry.authorEmail || inquiry.email || localStorage.getItem('userEmail') || '';
        emailEl.textContent = email ? `Email ${email}` : 'Email -';
    }
    const phoneEl = document.getElementById('inquiryPhone');
    if (phoneEl) {
        const phone = inquiry.contactNo || inquiry.contactNumber || inquiry.phoneNumber || '';
        phoneEl.textContent = phone ? `Phone ${phone}` : 'Phone -';
    }
    const noEl = document.getElementById('inquiryNo');
    if (noEl) {
        const no = inquiry.inquiryNo || inquiry.inquiry_no || inquiry.inquiryId || '';
        noEl.textContent = no ? `Inquiry No. ${no}` : 'Inquiry No. -';
    }
}

// 문의 내용 업데이트
function updateInquiryContent(inquiry) {
    const contentElement = document.getElementById('inquiryContent') || document.querySelector('.text.fz14.fw500.lh22.black12');
    const content = inquiry.content || inquiry.message || '';
    if (contentElement) {
        // Quill/HTML로 저장된 답변/내용에서 <p> 같은 태그 문자열이 그대로 노출되는 문제 방지
        contentElement.textContent = htmlToPlainText(content || '');
    }
}

// 첨부파일 업데이트
function updateAttachments(attachments) {
    const container = document.getElementById('attachmentsContainer');
    if (!container) return;
    container.innerHTML = '';

    if (!attachments || attachments.length === 0) return;
    attachments.forEach((attachment, idx) => {
        container.appendChild(createAttachmentElement(attachment, idx));
    });
}

// 첨부파일 요소 생성
function createAttachmentElement(attachment, index = 0) {
    const wrapper = document.createElement('div');
    wrapper.className = `download-wrapper ${index === 0 ? 'mt16' : 'mt12'}`;

    const mime = attachment.mimeType || attachment.fileType || attachment.type || '';
    const size = typeof attachment.size === 'number' ? attachment.size : parseInt(attachment.size || '0', 10);
    const isImage = mime.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(attachment.name || '');

    if (isImage && attachment.url) {
        // 이미지 파일: 미리보기 + 다운로드 아이콘 표시
        const imagePreview = document.createElement('div');
        imagePreview.className = 'image-preview';
        imagePreview.style.cssText = 'position: relative;';

        const img = document.createElement('img');
        img.src = attachment.url;
        img.alt = attachment.name || 'Attachment';
        img.style.cssText = 'width: 100%; max-width: 200px; height: auto; border-radius: 8px; object-fit: cover;';

        // 다운로드 아이콘 버튼
        const downloadBtn = document.createElement('button');
        downloadBtn.type = 'button';
        downloadBtn.className = 'btn-image-download';
        downloadBtn.style.cssText = 'position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.9); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';

        const downloadIcon = document.createElement('img');
        downloadIcon.src = '../images/ico_download_gray.svg';
        downloadIcon.alt = 'Download';
        downloadIcon.style.cssText = 'width: 20px; height: 20px;';
        downloadBtn.appendChild(downloadIcon);

        // 캡션 (파일명, 크기)
        const caption = document.createElement('div');
        caption.className = 'text fz12 fw400 lh18 gray96 mt4';
        caption.textContent = `${attachment.name || 'Image'}, ${formatFileSize(Number.isFinite(size) ? size : 0)}`;

        imagePreview.appendChild(img);
        imagePreview.appendChild(downloadBtn);
        wrapper.appendChild(imagePreview);
        wrapper.appendChild(caption);

        // 다운로드 버튼 클릭 시 다운로드
        downloadBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            downloadAttachment(attachment);
        });
    } else {
        // 문서 파일: 아이콘 UI
        const button = document.createElement('button');
        button.className = 'btn-download';
        button.type = 'button';

        const icon = document.createElement('img');
        icon.src = '../images/ico_document.svg';
        icon.alt = '';

        const span = document.createElement('span');
        span.style.cssText = 'word-break: break-all; overflow-wrap: anywhere; padding-right: 40px;';
        span.innerHTML = `${attachment.name}<br> ${mime || 'file'}, ${formatFileSize(Number.isFinite(size) ? size : 0)}`;

        button.appendChild(icon);
        button.appendChild(span);
        wrapper.appendChild(button);

        // 다운로드 이벤트 리스너
        button.addEventListener('click', function() {
            downloadAttachment(attachment);
        });
    }

    return wrapper;
}

// 답변 정보 업데이트
function updateReplies(replies) {
    const answerElement = document.getElementById('answerBox') || document.querySelector('.answer');
    if (!answerElement) return;

    if (replies.length > 0) {
        // 가장 최근 답변 표시
        const latestReply = replies[replies.length - 1];

        const contentElement = document.getElementById('answerContent') || answerElement.querySelector('p');
        const dateElement = document.getElementById('answerDate') || answerElement.querySelector('span');

        if (contentElement) {
            // Quill/HTML로 저장된 답변에서 <p> 태그가 문자열로 노출되는 문제 방지
            const raw = latestReply.replyContent || latestReply.replyMessage || '';
            contentElement.textContent = htmlToPlainText(raw);
        }

        if (dateElement) {
            dateElement.textContent = formatDate(latestReply.createdAt);
        }

        answerElement.style.display = 'block';
    } else {
        answerElement.style.display = 'none';
    }
}

// Quill/HTML 콘텐츠를 화면 표시용 "텍스트"로 정리
function htmlToPlainText(value) {
    let s = String(value ?? '');
    if (!s) return '';

    // 답변/내용이 HTML 엔티티로 저장된 경우(&lt;p&gt;...) 먼저 디코딩
    // - textContent로 넣을 때 "<p>"가 그대로 보이는 케이스 대응
    if (s.includes('&lt;') || s.includes('&gt;') || s.includes('&#60;') || s.includes('&#62;')) {
        try {
            const ta = document.createElement('textarea');
            ta.innerHTML = s;
            s = ta.value;
        } catch (_) { }
    }

    // 태그가 없으면 그대로 반환
    if (!/[<>]/.test(s)) return s;

    // 줄바꿈 유지를 위해 주요 태그를 \n 으로 치환
    let x = s
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p\s*>/gi, '\n')
        .replace(/<\/div\s*>/gi, '\n')
        .replace(/<\/li\s*>/gi, '\n')
        .replace(/<li[^>]*>/gi, '- ');

    // DOMParser가 있으면 가장 안전하게 텍스트 추출
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(x, 'text/html');
        const txt = (doc?.body?.textContent ?? '').replace(/\r\n?/g, '\n');
        return txt.replace(/\n{3,}/g, '\n\n').trim();
    } catch (_) {
        // fallback: 정규식 기반 태그 제거
        x = x.replace(/<[^>]+>/g, '');
        try { x = x.replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>'); } catch (_) {}
        x = x.replace(/\r\n?/g, '\n');
        return x.replace(/\n{3,}/g, '\n\n').trim();
    }
}

// 이벤트 리스너 설정
function setupEventListeners() {
    // 더보기 버튼 (메뉴 표시)
    const moreButton = document.querySelector('.btn-more');
    if (moreButton) {
        moreButton.addEventListener('click', toggleModal);
    }

    // 모달 배경 클릭 시 닫기
    const layer = document.querySelector('.layer');
    if (layer) {
        layer.addEventListener('click', hideModal);
    }

    // 모달 닫기 버튼
    const closeButton = document.querySelector('.btn-close');
    if (closeButton) {
        closeButton.addEventListener('click', hideModal);
    }

    // 수정 버튼
    const editButton = document.querySelector('.btn-edit');
    if (editButton) {
        editButton.addEventListener('click', editInquiry);
    }

    // 삭제 버튼
    const deleteButton = document.querySelector('.btn-delete');
    if (deleteButton) {
        deleteButton.addEventListener('click', deleteInquiry);
    }
}

// 모달 토글
function toggleModal() {
    const modal = document.querySelector('.modal-bottom');
    const layer = document.querySelector('.layer');

    if (modal && layer) {
        const isVisible = modal.style.display === 'block';
        if (isVisible) {
            hideModal();
        } else {
            showModal();
        }
    }
}

// 모달 표시
function showModal() {
    const modal = document.querySelector('.modal-bottom');
    const layer = document.querySelector('.layer');

    if (modal && layer) {
        modal.style.display = 'block';
        layer.style.display = 'block';
    }
}

// 모달 숨기기
function hideModal() {
    const modal = document.querySelector('.modal-bottom');
    const layer = document.querySelector('.layer');

    if (modal && layer) {
        modal.style.display = 'none';
        layer.style.display = 'none';
    }
}

// 문의 수정
function editInquiry() {
    if (isAnswerCompleted) {
        // UI는 비활성화지만, 혹시라도 호출되면 방어
        alert('You cannot edit an inquiry after it has been answered.');
        hideModal();
        return;
    }
    if (currentInquiry) {
        const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
        window.location.href = `inquiry-edit.html?inquiryId=${encodeURIComponent(currentInquiry.inquiryId)}&lang=${encodeURIComponent(lang || 'en')}`;
    }
}

// 문의 삭제
async function deleteInquiry() {
    if (!currentInquiry) return;

    const confirmDelete = confirm('Do you want to delete this inquiry?');
    if (!confirmDelete) return;

    try {
        const userId = localStorage.getItem('userId');

        let result;
        if (window.api && window.api.deleteInquiry) {
            result = await window.api.deleteInquiry(currentInquiry.inquiryId, userId);
        } else {
            const response = await fetch('../backend/api/inquiry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_inquiry',
                    inquiryId: currentInquiry.inquiryId,
                    accountId: userId
                })
            });
            result = await response.json();
        }

        if (result.success) {
            alert('Inquiry deleted.');
            // 목록은 운영 페이지(inquiry.php)로
            const lang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
            window.location.href = `inquiry.php?lang=${encodeURIComponent(lang || 'en')}`;
        } else {
            alert(result.message || 'Failed to delete inquiry.');
        }

    } catch (error) {
        console.error('Delete inquiry error:', error);
        alert('An error occurred while deleting inquiry.');
    }
}

// 첨부파일 다운로드
function downloadAttachment(attachment) {
    const url = attachment.url || attachment.downloadUrl || attachment.filePath;
    if (!url) {
        alert('File URL not found.');
        return;
    }

    // 같은 origin(/uploads/...)이면 바로 다운로드 트리거
    const a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';
    a.download = attachment.name || 'attachment';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

// 카테고리 텍스트 변환
function getCategoryText(category) {
    const c = String(category || '').toLowerCase();
    const categoryMap = {
        // 문의 수정/목록 요구사항(5종)과 동일하게 표시
        // legacy/raw UI values도 함께 수용 (reservation/product/cancellation/other)
        'booking': 'Reservation Inquiry',
        'reservation': 'Reservation Inquiry',
        'payment': 'Payment Inquiry',
        'complaint': 'Cancellation Inquiry',
        'cancellation': 'Cancellation Inquiry',
        'cancel': 'Cancellation Inquiry',
        'suggestion': 'Other',
        // general/visa/technical 등은 Product Inquiry로 표시
        'product': 'Product Inquiry',
        'general': 'Product Inquiry',
        'visa': 'Product Inquiry',
        'technical': 'Product Inquiry',
        'other': 'Other'
    };
    return categoryMap[c] || 'Product Inquiry';
}

// 파일 크기 포맷
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// 날짜 포맷
function formatDate(value) {
    const t = Date.parse(String(value || ''));
    if (Number.isNaN(t)) return '';
    const d = new Date(t);
    const yyyy = String(d.getFullYear());
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

// 로딩 상태 표시
function showLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '0.6';
        mainContent.style.pointerEvents = 'none';
    }
}

// 로딩 상태 해제
function hideLoadingState() {
    const mainContent = document.querySelector('.main');
    if (mainContent) {
        mainContent.style.opacity = '1';
        mainContent.style.pointerEvents = 'auto';
    }
}

// 오류 메시지 표시
function showErrorMessage(message) {
    alert(message);
}

// 외부 사용을 위한 함수 내보내기
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadInquiryDetails,
        renderInquiryDetails,
        formatDate
    };
}