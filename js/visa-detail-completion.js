/**
 * 비자 발급 완료 페이지 JavaScript
 * 비자 발급 완료 정보 표시 및 다운로드 기능
 */

let completedVisaApplication = null;

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    // 다국어 텍스트 로드
    if (typeof loadServerTexts === 'function') {
        loadServerTexts().then(() => {
            initializeVisaCompletionPage();
        });
    } else {
        initializeVisaCompletionPage();
    }
});

// 다국어 텍스트 가져오기
function getI18nText(key, fallback = '') {
    let currentLang = 'en';
    if (typeof getCurrentLanguage === 'function') {
        currentLang = getCurrentLanguage();
    } else {
        currentLang = localStorage.getItem('selectedLanguage') || 'en';
    }
    const texts = globalLanguageTexts && globalLanguageTexts[currentLang] ? globalLanguageTexts[currentLang] : {};
    return texts[key] || fallback;
}

// 비자 완료 페이지 초기화
async function initializeVisaCompletionPage() {
    try {
        // 로그인 확인
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userId = localStorage.getItem('userId');

        if (!isLoggedIn || !userId) {
            const loginRequired = getI18nText('loginRequired', 'Login is required.');
            alert(loginRequired);
            window.location.href = 'login.html';
            return;
        }

        // URL 파라미터에서 비자 신청 ID 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const applicationId = urlParams.get('applicationId') || urlParams.get('application_id') || urlParams.get('id');

        if (!applicationId) {
            const visaNotFound = getI18nText('visaApplicationNotFound', 'Visa application information not found.');
            alert(visaNotFound);
            history.back();
            return;
        }

        // 비자 신청 정보 로드
        await loadVisaApplicationDetails(applicationId, userId);

        // 이벤트 리스너 설정
        setupEventListeners();

    } catch (error) {
        console.error('Visa completion page init error:', error);
        const errorMsg = getI18nText('errorInitializingVisaPage', 'An error occurred while loading the page.');
        showErrorMessage(errorMsg);
    }
}

// 비자 신청 정보 로드
async function loadVisaApplicationDetails(applicationId, userId) {
    try {
        showLoadingState();

        // API 호출
        let result;
        if (window.api && window.api.getVisaApplication) {
            result = await window.api.getVisaApplication(applicationId, userId);
        } else {
            const response = await fetch('../backend/api/visa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_application',
                    applicationId: applicationId,
                    accountId: userId
                })
            });
            result = await response.json();
        }

        if (result.success && result.data) {
            completedVisaApplication = result.data;

            // 비자 상태가 승인된 경우에만 표시
            if (result.data.status !== 'approved') {
                const notYetIssued = getI18nText('visaNotYetIssued', 'Visa issuance has not been completed yet.');
                alert(notYetIssued);
                history.back();
                return;
            }

            renderVisaCompletionInfo(result.data);
        } else {
            const failedToLoad = getI18nText('failedToLoadVisaApplication', 'Failed to load visa application information.');
            showErrorMessage(result.message || failedToLoad);
        }

    } catch (error) {
        console.error('Visa application load error:', error);
        const errorMsg = getI18nText('errorLoadingVisaApplication', 'An error occurred while loading visa application information.');
        showErrorMessage(errorMsg);
    } finally {
        hideLoadingState();
    }
}

// 비자 완료 정보 렌더링
function renderVisaCompletionInfo(visaApplication) {
    try {
        // 완료 메시지 업데이트
        updateCompletionMessage(visaApplication);

        // 비자 정보 표시 (필요한 경우)
        displayVisaDetails(visaApplication);

        // 다운로드 버튼 활성화
        enableDownloadButton(visaApplication);

    } catch (error) {
        console.error('Visa completion render error:', error);
        const errorMsg = getI18nText('errorDisplayingVisaInfo', 'An error occurred while displaying visa information.');
        showErrorMessage(errorMsg);
    }
}

// 완료 메시지 업데이트
function updateCompletionMessage(visaApplication) {
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement && visaApplication.destination) {
        const completed = getI18nText('visaIssuanceCompleted', 'Visa issuance completed.');
        const pleaseCheck = getI18nText('pleaseCheckIssuedVisa', 'Please check your issued visa.');
        messageElement.innerHTML = `${visaApplication.destination} ${completed}<br>${pleaseCheck}`;
    }

    // 페이지 제목도 업데이트 (선택사항)
    const titleElement = document.querySelector('.title');
    if (titleElement && visaApplication.destination) {
        const issued = getI18nText('visaIssuanceCompleted', 'Visa issuance completed.');
        titleElement.textContent = `${visaApplication.destination} ${issued}`;
    }
}

// 비자 상세 정보 표시 (필요한 경우 추가 정보 표시)
function displayVisaDetails(visaApplication) {
    // 비자 유형, 유효기간 등의 정보를 표시할 영역이 있다면 여기서 처리
    // 현재 HTML에는 해당 영역이 없으므로 필요시 동적으로 추가

    if (visaApplication.visaType || visaApplication.validFrom || visaApplication.validUntil) {
        const detailsContainer = createVisaDetailsContainer(visaApplication);
        const messageContainer = document.querySelector('.px20.pb20.mt24');
        if (messageContainer && detailsContainer) {
            messageContainer.appendChild(detailsContainer);
        }
    }
}

// 비자 상세 정보 컨테이너 생성
function createVisaDetailsContainer(visaApplication) {
    const container = document.createElement('div');
    container.className = 'mt32 p20 bg-light-gray border-radius-8';

    const visaInfo = getI18nText('visaInformation', 'Visa Information');
    let detailsHTML = `<div class="text fz16 fw600 lh24 black12 mb16">${visaInfo}</div>`;

    if (visaApplication.visaType) {
        const visaTypeLabel = getI18nText('visaType', 'Visa Type');
        detailsHTML += `
            <div class="align both vm mb12">
                <span class="text fz14 fw500 lh22 gray96">${visaTypeLabel}</span>
                <span class="text fz14 fw400 lh22 black12">${visaApplication.visaType}</span>
            </div>
        `;
    }

    if (visaApplication.validFrom && visaApplication.validUntil) {
        const lang = (typeof getCurrentLanguage === 'function' ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en'));
        const locale = (lang === 'tl') ? 'en-US' : 'en-US';
        const validFrom = new Date(visaApplication.validFrom).toLocaleDateString(locale);
        const validUntil = new Date(visaApplication.validUntil).toLocaleDateString(locale);
        const validPeriod = getI18nText('validPeriod', 'Valid Period');

        detailsHTML += `
            <div class="align both vm mb12">
                <span class="text fz14 fw500 lh22 gray96">${validPeriod}</span>
                <span class="text fz14 fw400 lh22 black12">${validFrom} ~ ${validUntil}</span>
            </div>
        `;
    }

    if (visaApplication.applicationId) {
        const appNumber = getI18nText('applicationNumber', 'Application Number');
        detailsHTML += `
            <div class="align both vm">
                <span class="text fz14 fw500 lh22 gray96">${appNumber}</span>
                <span class="text fz14 fw400 lh22 black12">${visaApplication.applicationId}</span>
            </div>
        `;
    }

    container.innerHTML = detailsHTML;
    return container;
}

// 다운로드 버튼 활성화
function enableDownloadButton(visaApplication) {
    const downloadButton = document.querySelector('.btn.primary.lg.ico1');
    if (downloadButton) {
        downloadButton.disabled = false;
        downloadButton.style.opacity = '1';
        downloadButton.addEventListener('click', function() {
            handleVisaDownload(visaApplication);
        });
    }
}

// 이벤트 리스너 설정
function setupEventListeners() {
    // 다운로드 버튼 (이미 enableDownloadButton에서 설정됨)

    // 뒤로가기 버튼 (이미 HTML에서 설정됨)

    // 추가적인 이벤트 리스너가 필요한 경우 여기에 추가
}

// 비자 파일 다운로드 처리
async function handleVisaDownload(visaApplication) {
    try {
        showLoadingState();

        // 앱(WebView)에서 fetch+blob 다운로드가 막히는 케이스가 있어,
        // 예약상세의 "가이드 다운로드"처럼 "직접 다운로드 URL로 이동" 방식으로 통일
        let fileUrl = String(visaApplication?.visaFile || '').trim();
        // notes JSON 등에서 "uploads/..." 형태로 내려오는 케이스도 수용
        if (fileUrl && fileUrl.startsWith('uploads/')) fileUrl = '/' + fileUrl;
        // full URL(https://.../uploads/...)로 내려오는 케이스도 수용
        try {
            if (fileUrl && /^https?:\/\//i.test(fileUrl)) {
                const u = new URL(fileUrl);
                if (u.pathname && u.pathname.startsWith('/uploads/')) {
                    fileUrl = u.pathname;
                }
            }
        } catch (_) {}
        if (fileUrl && fileUrl.startsWith('/uploads/')) {
            // 보안 다운로드 헬퍼 사용(uploads 하위만 허용 + 세션 필요)
            const dl = `../backend/api/download.php?file=${encodeURIComponent(fileUrl)}`;
            // "가이드 다운로드" UX와 동일하게 anchor click 방식으로 트리거
            downloadFromUrl(dl, '');
            return;
        }

        // fallback: visa.php에서 notes(visaFile) 기반으로 다운로드 처리 (GET 지원 추가됨)
        const appId = visaApplication?.applicationId || visaApplication?.visaApplicationId || '';
        if (!appId) {
            const fileNotFound = getI18nText('visaFileNotFound', 'Visa file not found.');
            throw new Error(fileNotFound);
        }
        window.location.href = `../backend/api/visa.php?action=download_visa&applicationId=${encodeURIComponent(String(appId))}`;
        return;

    } catch (error) {
        console.error('Visa download error:', error);
        const errorMsg = getI18nText('errorDownloadingVisa', 'An error occurred while downloading the visa. Please try again later.');
        alert(errorMsg);
    } finally {
        hideLoadingState();
    }
}

// 파일 다운로드 (Blob 방식)
function downloadFile(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

// URL을 통한 파일 다운로드
function downloadFromUrl(fileUrl, filename) {
    const link = document.createElement('a');
    link.href = fileUrl;
    // filename이 비어있으면 서버 Content-Disposition을 우선 사용
    if (filename) link.download = filename;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 페이지 애니메이션 추가
function addPageAnimations() {
    // 완료 메시지에 애니메이션 효과
    const messageElement = document.querySelector('.text.fz20.fw600.lh28.black12');
    if (messageElement) {
        messageElement.style.opacity = '0';
        messageElement.style.transform = 'translateY(20px)';
        messageElement.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';

        setTimeout(() => {
            messageElement.style.opacity = '1';
            messageElement.style.transform = 'translateY(0)';
        }, 200);
    }

    // 다운로드 버튼에 애니메이션 효과
    const downloadButton = document.querySelector('.btn.primary.lg.ico1');
    if (downloadButton) {
        downloadButton.style.opacity = '0';
        downloadButton.style.transform = 'scale(0.9)';
        downloadButton.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';

        setTimeout(() => {
            downloadButton.style.opacity = '1';
            downloadButton.style.transform = 'scale(1)';
        }, 400);
    }
}

// 로딩 상태 표시
function showLoadingState() {
    const downloadButton = document.querySelector('.btn.primary.lg.ico1');
    if (downloadButton) {
        downloadButton.disabled = true;
        const processing = getI18nText('processing', 'Processing...');
        downloadButton.textContent = processing;
    }
}

// 로딩 상태 해제
function hideLoadingState() {
    const downloadButton = document.querySelector('.btn.primary.lg.ico1');
    if (downloadButton) {
        downloadButton.disabled = false;
        const downloadText = getI18nText('visaFileDownload', 'Visa file download');
        downloadButton.textContent = downloadText;
    }
}

// 오류 메시지 표시
function showErrorMessage(message) {
    alert(message);
}

// 페이지 로드 후 애니메이션 실행
setTimeout(addPageAnimations, 100);

// 외부 사용을 위한 함수 내보내기
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadVisaApplicationDetails,
        handleVisaDownload,
        renderVisaCompletionInfo
    };
}