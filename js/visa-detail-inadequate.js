/**
 * 비자 서류 미비 페이지 JavaScript
 * 미비한 서류 업로드 및 재제출 기능
 */

let currentVisaApplication = null;
let uploadedFiles = {};
let requiredDocuments = [];
let requiredDocKeysToUpload = [];

// 비자 타입별 서류 설정 (fallback)
const DOCUMENT_CONFIG = {
    group: [
        { key: 'passport', title: 'Passport bio page', required: true, multiple: false },
        { key: 'visaApplicationForm', title: 'Visa Application Form', required: true, multiple: false, download: true, downloadPath: '/uploads/visa/doc/Korea-visa-application.pdf' },
        { key: 'bankCertificate', title: 'Bank Certificate', required: true, multiple: false },
        { key: 'bankStatement', title: 'Bank Statement', required: true, multiple: false },
        { key: 'additionalDocuments', title: 'Additional requirements', required: false, multiple: true }
    ],
    individual: [
        { key: 'visaApplicationForm', title: 'Visa Application Form', required: false, multiple: false, download: true, downloadPath: '/uploads/visa/doc/Korea-visa-application.pdf' },
        { key: 'dataPrivacyConsent', title: 'Signed Data Privacy Consent Form', required: false, multiple: false, download: true, downloadPath: '/uploads/visa/doc/Data-Privacy-Consent-Form_KVAC.pdf' }
    ]
};

// visaSend 상태 관리
let visaSendValue = null;

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    setupVisaInadequateBackLink();
    initializeVisaInadequatePage();
});

function setupVisaInadequateBackLink() {
    try {
        const a = document.querySelector('a.btn-mypage');
        if (!a) return;
        const qp = new URLSearchParams(window.location.search);
        const lang = (qp.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : '') || 'en').toLowerCase();
        a.setAttribute('href', `visa-history.html?lang=${encodeURIComponent(lang)}`);
    } catch (_) { /* ignore */ }
}

// 비자 서류 미비 페이지 초기화
async function initializeVisaInadequatePage() {
    try {
        // PHP 세션 확인 (localStorage가 아닌 실제 서버 세션 확인)
        let userId = null;
        try {
            const sessionRes = await fetch('../backend/api/check-session.php', { credentials: 'same-origin' });
            const sessionData = await sessionRes.json();
            if (!sessionData || !sessionData.success || !sessionData.isLoggedIn) {
                alert('Login is required.');
                window.location.href = 'login.html';
                return;
            }
            userId = sessionData.user?.id || localStorage.getItem('userId');
        } catch (e) {
            console.error('Session check failed:', e);
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        if (!userId) {
            alert('Login is required.');
            window.location.href = 'login.html';
            return;
        }

        // URL 파라미터에서 비자 신청 ID 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const applicationId = urlParams.get('applicationId') || urlParams.get('application_id') || urlParams.get('id');

        if (!applicationId) {
            alert('Unable to find visa application information.');
            // Avoid history.back() no-op; go back to list deterministically
            try {
                const qp = new URLSearchParams(window.location.search);
                const lang = (qp.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : '') || 'en').toLowerCase();
                window.location.replace(`visa-history.html?lang=${encodeURIComponent(lang)}`);
            } catch (_) {
                window.location.replace('visa-history.html');
            }
            return;
        }

        // 비자 신청 정보 로드
        await loadVisaApplicationDetails(applicationId, userId);

        // 업로드 폼 설정
        setupUploadForms();

        // 이벤트 리스너 설정
        setupEventListeners();

    } catch (error) {
        console.error('Visa inadequate page init error:', error);
        showErrorMessage('An error occurred while loading the page.');
    }
}

// 비자 신청 정보 로드
async function loadVisaApplicationDetails(applicationId, userId) {
    try {
        showLoadingState();

        // API 호출
        // api.js는 visa.php에 { action:'get_visa_application', visaApplicationId }로 호출하므로
        // URL 파라미터가 id=9(applicationId)인 경우에도 통과하도록 그대로 visaApplicationId로 전달한다.
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

            // 비자 타입 기반 서류 설정
            const visaType = (result.data.visaType || 'individual').toLowerCase();
            if (result.data.documentRequirements && result.data.documentRequirements.length > 0) {
                requiredDocuments = result.data.documentRequirements;
            } else {
                requiredDocuments = DOCUMENT_CONFIG[visaType] || DOCUMENT_CONFIG.individual;
            }
            requiredDocKeysToUpload = requiredDocuments
                .filter(doc => doc.required)
                .map(doc => doc.key);

            // 상태에 따라 올바른 상세 페이지로 라우팅
            // - 운영에서 오래된 링크/앱 deep-link가 visa-detail-inadequate.html?id=... 로 고정되어 들어오는 케이스가 있어,
            //   현재 상태에 맞는 페이지로 자동 전환한다.
            const st = String(result.data.status || '').toLowerCase();
            try {
                const lang = (typeof getCurrentLanguage === 'function')
                    ? getCurrentLanguage()
                    : (localStorage.getItem('selectedLanguage') || 'en');
                const qs = `id=${encodeURIComponent(String(applicationId))}&lang=${encodeURIComponent(lang || 'en')}`;
                if (st === 'approved' || st === 'completed') {
                    window.location.replace(`visa-detail-completion.php?${qs}`);
                    return;
                }
                if (st === 'rejected') {
                    window.location.replace(`visa-detail-rebellion.html?${qs}`);
                    return;
                }
                if (st === 'under_review' || st === 'processing') {
                    window.location.replace(`visa-detail-examination.html?${qs}`);
                    return;
                }
            } catch (_) { }

            // 서류 미비 상태인지 확인
            if (st !== 'pending' && st !== 'inadequate' && st !== 'document_required') {
                alert('This visa application is not in an incomplete-documents state.');
                try {
                    const qp = new URLSearchParams(window.location.search);
                    const lang = (qp.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : '') || 'en').toLowerCase();
                    window.location.replace(`visa-history.html?lang=${encodeURIComponent(lang)}`);
                } catch (_) {
                    window.location.replace('visa-history.html');
                }
                return;
            }

            renderInadequateInfo(result.data);
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

// 서류 미비 정보 렌더링
function renderInadequateInfo(visaApplication) {
    try {
        // 메시지 업데이트
        updateInadequateMessage(visaApplication);

        // 서류 안내문 표시
        renderDocumentGuide(visaApplication);

        // 미비한 서류 목록 표시
        displayRequiredDocuments(visaApplication.inadequateDocuments || []);

    } catch (error) {
        console.error('Inadequate info render error:', error);
        showErrorMessage('An error occurred while displaying information.');
    }
}

// 서류 안내문 렌더링
function renderDocumentGuide(visaApplication) {
    const guideContainer = document.getElementById('documentGuide');
    const visaTypeLabel = document.getElementById('visaTypeLabel');
    const documentList = document.getElementById('documentList');

    if (!guideContainer || !documentList) return;

    const visaType = (visaApplication.visaType || 'individual').toLowerCase();
    const visaTypeDisplay = visaType === 'group' ? 'Group Visa' : 'Individual Visa';

    if (visaTypeLabel) {
        visaTypeLabel.textContent = visaTypeDisplay;
    }

    let listHtml = '';

    if (visaType === 'individual') {
        // Individual Visa 상세 안내문
        listHtml = `
            <div style="background: #fff3cd; padding: 10px 12px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; color: #856404;">
                <strong>※ All documents must be sent to office via MAIL or FAX</strong>
            </div>

            <div style="margin-bottom: 16px;">
                <div class="text fz13 fw600 lh20 black12" style="margin-bottom: 8px;">GENERAL REQUIREMENTS:</div>
                <ol style="padding-left: 20px; margin: 0;">
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Passport bio page <span style="color: #6c757d;">(Make sure it is signed & still valid. Broken passport is not accepted.)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Copy of valid visa/s and arrival stamps to OECD countries for the past 5 years. <span style="color: #6c757d;">(if applicable)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Application form <span style="color: #6c757d;">(computerized/handwritten, print in A4, original/e-signature)</span> with attach passport size photo <span style="color: #6c757d;">(formal, NO HEAD ACCESSORIES, no teeth shown, photo taken within 6 months or better studio taken)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Bank Certificate with Average Daily Balance for 6 months, date opened, account type & balance <span style="color: #6c757d;">(should be addressed to Korean Embassy)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Bank Statement – 3 months
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Signed Data Privacy Consent Form
                    </li>
                </ol>
            </div>

            <div style="margin-bottom: 16px;">
                <div class="text fz13 fw600 lh20 black12" style="margin-bottom: 8px; color: #dc3545;">PLEASE NOTE ADDITIONAL REQUIREMENTS BELOW:</div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR BUSINESS OWNER</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ BUSINESS DOCUMENTS<br>
                        &nbsp;&nbsp;- SEC or DTI (whichever is applicable)<br>
                        &nbsp;&nbsp;- Business Permit<br>
                        &nbsp;&nbsp;- BIR Certificate of Registration<br>
                        &nbsp;&nbsp;- BIR Income Tax Return
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR EMPLOYED</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Certificate of employment with compensation, date hired & position, also include that the applicant is applying for Korean Visa<br>
                        ○ Company ID (PRC ID IF ANY)<br>
                        ○ Approval of Leave<br>
                        ○ Income Tax Return (2316)
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR HOUSEWIFE</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ HUSBAND'S/SPONSOR'S DOCUMENTS (BUSINESSMAN OR EMPLOYED)<br>
                        ○ PSA MARRIAGE CERTIFICATE<br>
                        ○ Notarized Affidavit of Support. If not married Notarized Affidavit of common law partner.<br>
                        <span style="color: #6c757d;">** If the housewife has bank documents, it's better to provide it.</span>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR SENIORS</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Sponsor's Documents<br>
                        ○ Senior Citizen ID<br>
                        ○ PSA Birth Certificate<br>
                        ○ Retirement certificate if applicable<br>
                        ○ Proof of pension if applicable<br>
                        ○ Notarized Affidavit of Support<br>
                        <span style="color: #6c757d;">** If the senior has bank documents, it's better to provide it.</span>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR STUDENTS</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ PARENT'S/SPONSOR'S DOCUMENTS (BUSINESSMAN OR EMPLOYED)<br>
                        ○ Notarized Affidavit of Support<br>
                        ○ School ID<br>
                        ○ PSA Birth Certificate
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">IF APPLICANT IS SUPPORTED BY AN OVERSEAS FILIPINO WORKERS/SEAFARER</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Clear scan copy of contract or certificate of employment abroad<br>
                        ○ Copy of OWWA Verified Contract<br>
                        ○ Copy of POEA Contract<br>
                        ○ Working Visa<br>
                        ○ Copy of ID if applicable / Marina ID & Seamans Book – for seafarer
                    </div>
                </div>
            </div>
        `;
    } else {
        // Group Visa 상세 안내문
        listHtml = `
            <div style="margin-bottom: 16px;">
                <div class="text fz13 fw600 lh20 black12" style="margin-bottom: 8px;">GENERAL REQUIREMENTS:</div>
                <ol style="padding-left: 20px; margin: 0;">
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Passport bio page <span style="color: #6c757d;">(Make sure it is signed & still valid.)</span> <span style="color: #6c757d;">(Kindly submit old passport for checking of previous Korean Visa application)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Application form <span style="color: #6c757d;">(computerized/handwritten, print in A4, original/e-signature)</span> with attach passport size photo <span style="color: #6c757d;">(formal, NO HEAD ACCESSORIES, no teeth shown, or better studio taken)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Bank Certificate with Average Daily Balance for 6 months, date opened, account type & balance <span style="color: #6c757d;">(should be addressed to Korean Embassy)</span>
                    </li>
                    <li style="margin-bottom: 6px; font-size: 13px; line-height: 1.5;">
                        Bank Statement – 3 months
                    </li>
                </ol>
            </div>

            <div style="margin-bottom: 16px;">
                <div class="text fz13 fw600 lh20 black12" style="margin-bottom: 8px; color: #dc3545;">PLEASE NOTE ADDITIONAL REQUIREMENTS BELOW:</div>
                <div style="background: #e7f3ff; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 12px; color: #0056b3;">
                    <strong>NOTE:</strong> If applying as family, PSA documents only, no need for Affidavit of Support for student child and spouse.
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR BUSINESS OWNER</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ BUSINESS DOCUMENTS<br>
                        &nbsp;&nbsp;- SEC or DTI (whichever is applicable)<br>
                        &nbsp;&nbsp;- Business Permit<br>
                        &nbsp;&nbsp;- BIR Certificate of Registration<br>
                        &nbsp;&nbsp;- BIR Income Tax Return
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR EMPLOYED</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Certificate of employment with compensation, date hired & position, also include that the applicant is applying for Korean Visa<br>
                        ○ Company ID (PRC ID IF ANY)<br>
                        ○ Approval of Leave<br>
                        ○ Income Tax Return (2316)
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR HOUSEWIFE</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ HUSBAND'S/SPONSOR'S DOCUMENTS (BUSINESSMAN OR EMPLOYED)<br>
                        ○ PSA MARRIAGE CERTIFICATE<br>
                        ○ Affidavit of Support (Notarized)
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR SENIORS</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Senior Citizen ID<br>
                        ○ Retirement certificate if any
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">FOR STUDENTS</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ PARENT'S/SPONSOR'S DOCUMENTS (BUSINESSMAN OR EMPLOYED)<br>
                        ○ Affidavit of Support (Notarized)<br>
                        ○ School ID<br>
                        ○ PSA Birth Certificate
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <div class="text fz13 fw600 lh20 black4e" style="margin-bottom: 4px;">IF APPLICANT IS SUPPORTED BY AN OVERSEAS FILIPINO WORKERS/SEAFARER</div>
                    <div style="padding-left: 12px; font-size: 12px; color: #495057; line-height: 1.6;">
                        ○ Clear scan copy of contract or certificate of employment abroad<br>
                        ○ Copy of OWWA Verified Contract<br>
                        ○ Copy of POEA Contract<br>
                        ○ Working Visa<br>
                        ○ Copy of ID if applicable / Marina ID & Seamans Book – for seafarer
                    </div>
                </div>
            </div>
        `;
    }

    documentList.innerHTML = listHtml;
    guideContainer.style.display = 'block';

    // 토글 기능 설정
    setupDocumentGuideToggle();
}

// 안내문 접기/펼치기 토글 설정
function setupDocumentGuideToggle() {
    const header = document.getElementById('documentGuideHeader');
    const content = document.getElementById('documentGuideContent');
    const arrow = document.getElementById('documentGuideArrow');

    if (!header || !content || !arrow) return;

    // 기존 이벤트 리스너 제거 (중복 방지)
    header.replaceWith(header.cloneNode(true));
    const newHeader = document.getElementById('documentGuideHeader');

    newHeader.addEventListener('click', function() {
        const isExpanded = content.style.display !== 'none';

        if (isExpanded) {
            // 접기
            content.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        } else {
            // 펼치기
            content.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        }
    });
}

// 서류 미비 메시지 업데이트
function updateInadequateMessage(visaApplication) {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement && visaApplication.destination) {
        messageElement.innerHTML = `To apply for a ${visaApplication.destination} visa,<br>please check the documents below.`;
    }

    // 페이지 제목 업데이트
    const titleElement = document.querySelector('.title');
    // 요구사항: "상품명 - Submit Documents" 형태가 아니라 항상 "Visa Application"만 표시
    if (titleElement) {
        titleElement.textContent = 'Visa Application';
    }
    // (선택) 브라우저 탭 타이틀도 동일하게 정리
    try { document.title = 'Smart Travel | Visa Application'; } catch (_) {}
}

// 필수 서류 목록 표시
function displayRequiredDocuments(inadequateDocuments) {
    // 미비한 서류가 명시되어 있다면 해당 서류만, 없다면 모든 서류 표시
    let documentsToShow = inadequateDocuments.length > 0 ?
                           inadequateDocuments :
                           requiredDocuments.map(doc => doc.key);

    // Group visa: additionalDocuments는 항상 표시 (optional 항목이므로 inadequate 리스트에 없어도 표시)
    const visaType = (currentVisaApplication?.visaType || 'individual').toLowerCase();
    if (visaType === 'group') {
        // additionalDocuments가 없으면 추가
        if (!documentsToShow.includes('additionalDocuments')) {
            documentsToShow = [...documentsToShow, 'additionalDocuments'];
        }
    }

    requiredDocKeysToUpload = documentsToShow;

    // 업로드 섹션 업데이트
    updateUploadSections(documentsToShow);
}

// 업로드 섹션 업데이트
function updateUploadSections(documentsToShow) {
    const uploadContainer = document.querySelector('.mt32');
    if (!uploadContainer) return;

    // 기존 내용 지우기
    uploadContainer.innerHTML = '';

    // 현재 비자 타입 확인
    const visaType = (currentVisaApplication?.visaType || 'individual').toLowerCase();

    documentsToShow.forEach((docKey, index) => {
        const docInfo = requiredDocuments.find(doc => doc.key === docKey) ||
                       { key: docKey, title: docKey, required: true, multiple: false };

        const isDownload = docInfo.download === true;
        const isMultiple = docInfo.multiple === true;
        const multipleAttr = isMultiple ? 'multiple' : '';
        const acceptAttr = 'image/*,.pdf';

        let sectionHTML = '';

        // 다운로드만 있는 경우 (Individual 비자)
        const isDownloadOnly = isDownload && !docInfo.required;
        // 다운로드 + 업로드 둘 다 (Group 비자의 visaApplicationForm)
        const isDownloadAndUpload = isDownload && docInfo.required;

        if (isDownloadOnly) {
            // 다운로드 버튼만 렌더링 (Individual)
            sectionHTML = `
                <div class="document-section" data-document="${docInfo.key}" data-download="true">
                    <div class="text fz14 fw500 lh22 black4e ${index > 0 ? 'mt16' : ''}">
                        ${docInfo.title}
                    </div>
                    <div class="mt10">
                        <a href="${docInfo.downloadPath}" download style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                            padding: 12px 24px;
                            background: linear-gradient(135deg, #4a90d9 0%, #357abd 100%);
                            color: #fff;
                            font-size: 14px;
                            font-weight: 500;
                            text-decoration: none;
                            border-radius: 24px;
                            box-shadow: 0 2px 8px rgba(74, 144, 217, 0.3);
                            transition: all 0.2s ease;
                        " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(74, 144, 217, 0.4)';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(74, 144, 217, 0.3)';">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            <span>Download</span>
                        </a>
                    </div>
                </div>
            `;
        } else if (isDownloadAndUpload) {
            // 다운로드 + 업로드 버튼 둘 다 렌더링 (Group visaApplicationForm)
            sectionHTML = `
                <div class="document-section" data-document="${docInfo.key}" data-multiple="${isMultiple}">
                    <div class="text fz14 fw500 lh22 black4e ${index > 0 ? 'mt16' : ''}">
                        ${docInfo.title}
                        ${docInfo.required ? '<span class="text fz12 fw400 lh18 reded ml4">*Required</span>' : '<span class="text fz12 fw400 lh18 gray96 ml4">(Optional)</span>'}
                    </div>
                    <div class="mt10" style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <a href="${docInfo.downloadPath}" download class="upload-btn" style="text-decoration: none;">
                            <span>Download</span>
                            <i><img src="../images/ico_download_gray.svg" alt="" onerror="this.style.display='none'"></i>
                        </a>
                        <div class="upload-wrapper">
                            <label for="file-upload-${docInfo.key}" class="upload-btn" data-document="${docInfo.key}">
                                <span class="upload-text">Upload</span>
                                <i><img src="../images/ico_upload_gray.svg" alt=""></i>
                            </label>
                            <input id="file-upload-${docInfo.key}" type="file" accept="${acceptAttr}" data-document="${docInfo.key}" ${multipleAttr}>
                        </div>
                    </div>
                    <div class="uploaded-files-container" id="files-${docInfo.key}"></div>
                    <div class="uploaded-file-info delete-wrapper" style="display: none;">
                        <div class="btn-delete file-preview mt8 p12 bg-light-gray border-radius-4">
                            <img src="../images/ico_document.svg" alt="">
                            <span class="file-name" style="font-size: 12px; border: none; height: auto;"></span>
                            <button class="btn-remove-file" style="padding: 8px;" type="button" data-document="${docInfo.key}">×</button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // 업로드 버튼 렌더링 (기존 로직)
            sectionHTML = `
                <div class="document-section" data-document="${docInfo.key}" data-multiple="${isMultiple}">
                    <div class="text fz14 fw500 lh22 black4e ${index > 0 ? 'mt16' : ''}">
                        ${docInfo.title}
                        ${docInfo.required ? '<span class="text fz12 fw400 lh18 reded ml4">*Required</span>' : '<span class="text fz12 fw400 lh18 gray96 ml4">(Optional)</span>'}
                        ${isMultiple ? '<span class="text fz12 fw400 lh18 gray96 ml4">(Multiple files allowed)</span>' : ''}
                    </div>
                    <div class="upload-wrapper mt10">
                        <label for="file-upload-${docInfo.key}" class="upload-btn" data-document="${docInfo.key}">
                            <span class="upload-text">Upload</span>
                            <i><img src="../images/ico_upload_gray.svg" alt=""></i>
                        </label>
                        <input id="file-upload-${docInfo.key}" type="file" accept="${acceptAttr}" data-document="${docInfo.key}" ${multipleAttr}>
                    </div>
                    <div class="uploaded-files-container" id="files-${docInfo.key}"></div>
                    <div class="uploaded-file-info delete-wrapper" style="display: none;">
                        <div class="btn-delete file-preview mt8 p12 bg-light-gray border-radius-4">
                            <img src="../images/ico_document.svg" alt="">
                            <span class="file-name" style="font-size: 12px; border: none; height: auto;"></span>
                            <button class="btn-remove-file" style="padding: 8px;" type="button" data-document="${docInfo.key}">×</button>
                        </div>
                    </div>
                </div>
            `;
        }

        uploadContainer.insertAdjacentHTML('beforeend', sectionHTML);
    });

    // Individual의 경우 Documents Send? 섹션 추가
    if (visaType === 'individual') {
        const savedVisaSend = currentVisaApplication?.visaSend;
        const yesChecked = savedVisaSend === 1 ? 'checked' : '';
        const noChecked = savedVisaSend === 0 ? 'checked' : '';

        const documentsSendHTML = `
            <div class="documents-send-section mt24">
                <div class="text fz14 fw500 lh22 black4e">Documents Send?</div>
                <div class="mt10" style="display: flex; gap: 24px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="visaSend" value="1" ${yesChecked} style="width: 18px; height: 18px;">
                        <span class="text fz14 fw400 lh22 black4e">Yes</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="visaSend" value="0" ${noChecked} style="width: 18px; height: 18px;">
                        <span class="text fz14 fw400 lh22 black4e">No</span>
                    </label>
                </div>
            </div>
        `;
        uploadContainer.insertAdjacentHTML('beforeend', documentsSendHTML);

        // visaSend 라디오 버튼 이벤트 리스너
        const radios = uploadContainer.querySelectorAll('input[name="visaSend"]');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                visaSendValue = parseInt(this.value, 10);
            });
        });

        // 초기값 설정
        if (savedVisaSend !== null && savedVisaSend !== undefined) {
            visaSendValue = savedVisaSend;
        }
    }
}

// 업로드 폼 설정
function setupUploadForms() {
    // 각 업로드 input에 고유한 ID 부여 및 이벤트 리스너 설정은
    // updateUploadSections에서 처리됨
}

// 이벤트 리스너 설정
function setupEventListeners() {
    // 뒤로가기 버튼
    const backButton = document.querySelector('.btn-mypage');
    if (backButton) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            // Always go to list deterministically (history.back can be a no-op)
            try {
                const qp = new URLSearchParams(window.location.search);
                const lang = (qp.get('lang') || (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : '') || 'en').toLowerCase();
                window.location.href = `visa-history.html?lang=${encodeURIComponent(lang)}`;
            } catch (_) {
                window.location.href = 'visa-history.html';
            }
        });
    }

    // 파일 업로드 이벤트 (동적으로 생성된 요소에 대해 이벤트 위임 사용)
    document.addEventListener('change', function(e) {
        if (e.target.type === 'file' && e.target.dataset.document) {
            handleFileUpload(e);
        }
    });

    // 파일 제거 버튼
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-file')) {
            const documentKey = e.target.dataset.document;
            removeUploadedFile(documentKey);
        }
        // 다중 파일 제거 버튼
        if (e.target.classList.contains('btn-remove-multi-file')) {
            const documentKey = e.target.dataset.document;
            const index = parseInt(e.target.dataset.index, 10);
            removeMultipleUploadedFile(documentKey, index);
        }
    });

    // 저장 버튼
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.addEventListener('click', handleSaveDocuments);
    }

    // 실시간 폼 유효성 검사
    setInterval(validateForm, 500);
}

// 파일 업로드 처리
function handleFileUpload(event) {
    const files = event.target.files;
    const documentKey = event.target.dataset.document;

    console.log('=== handleFileUpload ===');
    console.log('documentKey:', documentKey);
    console.log('files:', files);

    if (!files || files.length === 0 || !documentKey) return;

    const section = document.querySelector(`[data-document="${documentKey}"]`);
    const isMultiple = section?.dataset?.multiple === 'true';

    if (isMultiple) {
        // 다중 파일 업로드
        if (!uploadedFiles[documentKey]) {
            uploadedFiles[documentKey] = [];
        }

        for (const file of files) {
            if (!validateFile(file)) continue;
            uploadedFiles[documentKey].push(file);
        }

        updateMultipleFileDisplay(documentKey);
    } else {
        // 단일 파일 업로드
        const file = files[0];
        if (!validateFile(file)) {
            event.target.value = '';
            return;
        }

        uploadedFiles[documentKey] = file;
        updateFileDisplay(documentKey, file);
    }

    // 폼 유효성 검사
    validateForm();
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

// 파일 표시 업데이트
function updateFileDisplay(documentKey, file) {
    const section = document.querySelector(`[data-document="${documentKey}"]`);
    if (!section) return;

    const uploadBtn = section.querySelector('.upload-btn .upload-text');
    const fileInfo = section.querySelector('.uploaded-file-info');
    const fileName = section.querySelector('.file-name');

    // if (uploadBtn && fileInfo && fileName) {
    //     uploadBtn.textContent = 'Change';
    //     fileInfo.style.display = 'block';
    //     fileName.textContent = `${file.name} (${formatFileSize(file.size)})`;
    // }

    // 파일명 / 확장자 분리
    const name = file.name || '';
    const dotIndex = name.lastIndexOf('.');
    const baseName = dotIndex > -1 ? name.slice(0, dotIndex) : name;
    const ext = dotIndex > -1 ? name.slice(dotIndex + 1).toLowerCase() : '';

    // MIME 타입 보조 (pdf 등)
    const type = ext || (file.type ? file.type.split('/')[1] : 'jpg');

    if (uploadBtn && fileInfo && fileName) {
        uploadBtn.textContent = 'Change';
        fileInfo.style.display = 'block';
        fileName.innerHTML = `
            ${baseName}
            <br>
            [${type}, ${formatFileSize(file.size)}]
        `;
    }
}

// 다중 파일 표시 업데이트
function updateMultipleFileDisplay(documentKey) {
    const container = document.getElementById(`files-${documentKey}`);
    if (!container) return;

    container.innerHTML = '';
    const files = uploadedFiles[documentKey] || [];

    files.forEach((file, index) => {
        const name = file.name || '';
        const dotIndex = name.lastIndexOf('.');
        const baseName = dotIndex > -1 ? name.slice(0, dotIndex) : name;
        const ext = dotIndex > -1 ? name.slice(dotIndex + 1).toLowerCase() : '';

        const fileHtml = `
            <div class="btn-delete file-preview mt8 p12 bg-light-gray border-radius-4" data-file-index="${index}" style="background: #f5f5f5; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                <img src="../images/ico_document.svg" alt="">
                <span class="file-name" style="font-size: 12px; flex: 1;">
                    ${baseName}<br>[${ext}, ${formatFileSize(file.size)}]
                </span>
                <button class="btn-remove-multi-file" style="padding: 8px; background: none; border: none; cursor: pointer; font-size: 16px;" type="button"
                        data-document="${documentKey}" data-index="${index}">×</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', fileHtml);
    });

    // Upload 버튼 텍스트 업데이트
    const section = document.querySelector(`[data-document="${documentKey}"]`);
    const uploadBtn = section?.querySelector('.upload-btn .upload-text');
    if (uploadBtn) {
        uploadBtn.textContent = files.length > 0 ? 'Add More' : 'Upload';
    }
}

// 다중 파일 제거
function removeMultipleUploadedFile(documentKey, index) {
    if (uploadedFiles[documentKey] && Array.isArray(uploadedFiles[documentKey])) {
        uploadedFiles[documentKey].splice(index, 1);
        updateMultipleFileDisplay(documentKey);
        validateForm();
    }
}

// 업로드된 파일 제거
function removeUploadedFile(documentKey) {
    if (uploadedFiles[documentKey]) {
        delete uploadedFiles[documentKey];

        const section = document.querySelector(`[data-document="${documentKey}"]`);
        if (section) {
            const uploadBtn = section.querySelector('.upload-btn .upload-text');
            const fileInfo = section.querySelector('.uploaded-file-info');
            const fileInput = section.querySelector(`#file-upload-${documentKey}`);

            if (uploadBtn && fileInfo && fileInput) {
                uploadBtn.textContent = 'Upload';
                fileInfo.style.display = 'none';
                fileInput.value = '';
            }
        }

        validateForm();
    }
}

// 서류 저장 처리
async function handleSaveDocuments() {
    console.log('=== handleSaveDocuments START ===');
    console.log('[BEFORE UPLOAD] uploadedFiles:', JSON.stringify(uploadedFiles, (k, v) => v instanceof File ? `File(${v.name})` : v));
    console.log('[BEFORE UPLOAD] uploadedFiles keys:', Object.keys(uploadedFiles));
    console.log('[BEFORE UPLOAD] requiredDocuments:', requiredDocuments);
    console.log('[BEFORE UPLOAD] visaType:', currentVisaApplication?.visaType);

    if (!validateForm()) {
        alert('Please upload all required documents.');
        return;
    }

    if (!currentVisaApplication) {
        alert('Unable to find visa application information.');
        return;
    }

    // 현재 비자 타입 확인
    const visaType = (currentVisaApplication?.visaType || 'individual').toLowerCase();
    console.log('[DETECTED] visaType:', visaType);

    try {
        showSavingState();

        let uploadResults = {};

        // Individual의 경우 파일 업로드 없음
        if (visaType !== 'individual') {
            // 파일 업로드 처리
            console.log('[UPLOADING] Starting file upload...');
            uploadResults = await uploadFiles();
            console.log('[AFTER UPLOAD] uploadResults:', uploadResults);

            // 업로드 실패(파일 경로 없음) 방지: 가짜 경로를 저장하지 않도록 차단
            // 필수 서류만 확인 (additionalDocuments는 선택사항이므로 빈 배열 허용)
            const requiredDocs = requiredDocuments.filter(doc => doc.required && doc.key !== 'additionalDocuments');
            const failed = requiredDocs.filter(doc => {
                const v = uploadResults[doc.key];
                if (Array.isArray(v)) {
                    return v.length === 0;
                }
                return !v || String(v).trim() === '';
            });
            if (failed.length > 0) {
                const keys = failed.map(doc => doc.key).join(', ');
                alert(`File upload failed. Please try again. (missing: ${keys})`);
                return;
            }
        }

        // 비자 신청 업데이트
        const updateResult = await updateVisaApplication(uploadResults);

        if (updateResult.success) {
            // Visa application completed 모달 표시
            showVisaCompletedModal();
        } else {
            alert(updateResult.message || 'Failed to submit documents.');
        }

    } catch (error) {
        console.error('Document submit error:', error);
        alert('An error occurred while submitting documents.');
    } finally {
        hideSavingState();
    }
}

// 파일 업로드
async function uploadFiles() {
    console.log('[uploadFiles] uploadedFiles:', uploadedFiles);
    console.log('[uploadFiles] Object.entries:', Object.entries(uploadedFiles || {}));
    const results = {};

    for (const [documentKey, fileData] of Object.entries(uploadedFiles || {})) {
        console.log(`[uploadFiles] Processing: ${documentKey} =>`, fileData);
        if (Array.isArray(fileData)) {
            // 다중 파일
            const uploadedPaths = [];
            for (const file of fileData) {
                const path = await uploadSingleFile(file, documentKey);
                if (path) uploadedPaths.push(path);
            }
            results[documentKey] = uploadedPaths;
        } else {
            // 단일 파일
            results[documentKey] = await uploadSingleFile(fileData, documentKey);
        }
    }

    return results;
}

// 단일 파일 업로드
async function uploadSingleFile(file, documentKey) {
    console.log(`[uploadSingleFile] key=${documentKey}, file=`, file);
    console.log(`[uploadSingleFile] file instanceof File: ${file instanceof File}`);

    if (!file || !(file instanceof File)) {
        console.error(`[uploadSingleFile] Invalid file for ${documentKey}`);
        return null;
    }

    // 실제 구현에서는 FormData를 사용하여 파일 업로드
    const formData = new FormData();
    formData.append('file', file);
    // backend/api/upload.php가 기대하는 필드
    formData.append('type', 'visa');
    formData.append('related_id', String(currentVisaApplication.applicationId || ''));
    formData.append('document_type', documentKey);
    formData.append('application_id', String(currentVisaApplication.applicationId || ''));

    try {
        const response = await fetch('../backend/api/upload.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const text = await response.text();
        let result = null;
        try { result = JSON.parse(text); } catch (_) {}

        if (!response.ok) {
            const msg = (result && result.message) ? result.message : `HTTP ${response.status}`;
            throw new Error(msg);
        }

        const filePath = result?.data?.filePath || result?.data?.file_path || null;
        if (!result?.success || !filePath) {
            const msg = (result && result.message) ? result.message : 'Upload failed (missing filePath).';
            throw new Error(msg);
        }

        return filePath;
    } catch (error) {
        console.error(`파일 업로드 실패: ${file?.name || ''}`, error);
        // 업로드 실패 시 가짜 경로를 만들지 않는다.
        return null;
    }
}

// 비자 신청 업데이트
async function updateVisaApplication(uploadResults) {
    const visaType = (currentVisaApplication?.visaType || 'individual').toLowerCase();

    const updateData = {
        documents: uploadResults,
        status: 'under_review' // 서류 보완 후 다시 심사 중으로 변경
    };

    // Individual의 경우 visaSend 값 추가
    if (visaType === 'individual' && visaSendValue !== null) {
        updateData.visaSend = visaSendValue;
    }

    let result;
    if (window.api && window.api.updateVisaApplication) {
        result = await window.api.updateVisaApplication(currentVisaApplication.applicationId, updateData);
    } else {
        const response = await fetch('../backend/api/visa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_application',
                applicationId: currentVisaApplication.applicationId,
                updateData: updateData
            })
        });
        result = await response.json();
    }

    return result;
}

// 폼 유효성 검사
function validateForm() {
    // 현재 비자 타입 확인
    const visaType = (currentVisaApplication?.visaType || 'individual').toLowerCase();

    // Individual의 경우 업로드할 게 없으므로 항상 유효
    if (visaType === 'individual') {
        const saveButton = document.querySelector('.btn.primary.lg');
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.style.opacity = '1';
        }
        return true;
    }

    // Group의 경우 필수 서류 확인 (additionalDocuments는 선택사항)
    const requiredDocs = requiredDocuments.filter(doc => doc.required && doc.key !== 'additionalDocuments');
    const requiredKeys = requiredDocs.map(doc => doc.key);

    const allRequiredUploaded = requiredKeys.every(key => {
        const uploaded = uploadedFiles[key];
        if (Array.isArray(uploaded)) {
            return uploaded.length > 0;
        }
        return !!uploaded;
    });

    // 저장 버튼 상태 업데이트
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = !allRequiredUploaded;
        saveButton.style.opacity = allRequiredUploaded ? '1' : '0.5';
    }

    return allRequiredUploaded;
}

// 진행 상황 표시기 추가
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

function showSavingState() {
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }
}

function hideSavingState() {
    const saveButton = document.querySelector('.btn.primary.lg');
    if (saveButton) {
        saveButton.disabled = false;
        saveButton.textContent = 'Save';
    }
}

function showErrorMessage(message) {
    alert(message);
}

// Visa application completed 모달 표시
function showVisaCompletedModal() {
    // 모달 HTML 생성
    const modalHTML = `
        <div class="layer active" id="visaCompletedLayer" style="opacity: 1; visibility: visible; z-index: 1000;"></div>
        <div class="alert-modal" id="visaCompletedModal" style="display: flex; z-index: 1001;">
            <div class="guide">Visa application completed</div>
            <div class="guide-sub">We will notify you of the review results through a notification.</div>
            <div style="display: flex; justify-content: center; width: 100%;">
                <button class="btn line md" type="button" onclick="closeVisaCompletedModal()" style="width: 120px; padding: 12px; border: 1px solid #b0b0b0; border-radius: 4px; color: #4e4e4e; font-size: 14px; font-weight: 500; line-height: 22px; background: #fff;">Confirm</button>
            </div>
        </div>
    `;

    // body에 모달 추가
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// 모달 닫기 및 페이지 이동
function closeVisaCompletedModal() {
    const modal = document.getElementById('visaCompletedModal');
    const layer = document.getElementById('visaCompletedLayer');

    if (modal) modal.remove();
    if (layer) layer.remove();

    // 비자 심사 페이지로 이동
    if (currentVisaApplication) {
        window.location.href = `visa-detail-examination.html?applicationId=${currentVisaApplication.applicationId}`;
    }
}

// 외부 사용을 위한 함수 내보내기
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadVisaApplicationDetails,
        handleFileUpload,
        handleSaveDocuments
    };
}