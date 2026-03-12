// 아이디 찾기 기능

document.addEventListener("DOMContentLoaded", async function () {
    // 다국어 텍스트 로드
    await loadServerTexts();

    // 국가 코드 로드(옵션 4개만 보이는 문제 수정)
    await initCountryCodes();
    
    const nameInput = document.getElementById("name");
    const phoneInput = document.getElementById("phone");
    const findIdBtn = document.getElementById("findIdBtn");
    
    // 입력 필드 변경 시 버튼 활성화 확인
    [nameInput, phoneInput].forEach(input => {
        if (input) {
            input.addEventListener("input", checkFormValidity);
        }
    });
    
    // 버튼 클릭 이벤트
    if (findIdBtn) {
        findIdBtn.addEventListener("click", handleFindId);
    }
    
    // 팝업 확인 버튼
    const noMemberOkBtn = document.getElementById("noMemberOkBtn");
    if (noMemberOkBtn) {
        noMemberOkBtn.addEventListener("click", hideNoMemberPopup);
    }
    
    // 초기 폼 상태 확인
    checkFormValidity();
});

async function initCountryCodes() {
    const sel = document.getElementById('countryCodeSelect') || document.querySelector('select.select-type1');
    if (!sel) return;
    try {
        const res = await fetch('../backend/api/countries.php', { credentials: 'same-origin' });
        const json = await res.json();
        let list = (json?.success && Array.isArray(json.countries)) ? json.countries : [];

        // 운영 환경에서 countries API가 제한적으로 내려오거나(예: 4개),
        // 캐시/프록시 문제로 리스트가 축소되는 케이스에 대비해 로컬 fallback을 병합한다.
        const fallback = [
            { code: '+63', name: 'Philippines' },
            { code: '+82', name: 'South Korea' },
            { code: '+1', name: 'United States' },
            { code: '+86', name: 'China' },
            { code: '+81', name: 'Japan' },
            { code: '+44', name: 'United Kingdom' },
            { code: '+49', name: 'Germany' },
            { code: '+33', name: 'France' },
            { code: '+39', name: 'Italy' },
            { code: '+34', name: 'Spain' },
            { code: '+61', name: 'Australia' },
            { code: '+64', name: 'New Zealand' },
            { code: '+65', name: 'Singapore' },
            { code: '+60', name: 'Malaysia' },
            { code: '+66', name: 'Thailand' },
            { code: '+84', name: 'Vietnam' },
            { code: '+62', name: 'Indonesia' },
            { code: '+91', name: 'India' },
            { code: '+7', name: 'Russia' },
            { code: '+55', name: 'Brazil' },
            { code: '+52', name: 'Mexico' },
            { code: '+54', name: 'Argentina' },
            { code: '+56', name: 'Chile' },
            { code: '+57', name: 'Colombia' },
            { code: '+51', name: 'Peru' },
            { code: '+27', name: 'South Africa' },
            { code: '+20', name: 'Egypt' },
            { code: '+971', name: 'UAE' },
            { code: '+966', name: 'Saudi Arabia' },
            { code: '+90', name: 'Turkey' },
            { code: '+98', name: 'Iran' },
            { code: '+92', name: 'Pakistan' },
            { code: '+880', name: 'Bangladesh' },
            { code: '+94', name: 'Sri Lanka' },
            { code: '+977', name: 'Nepal' },
            { code: '+975', name: 'Bhutan' },
            { code: '+93', name: 'Afghanistan' },
            { code: '+998', name: 'Uzbekistan' },
        ];

        const byCode = new Map();
        // 서버 리스트 우선
        (Array.isArray(list) ? list : []).forEach((c) => {
            const code = String(c?.code || '').trim();
            if (!code) return;
            byCode.set(code, { code, name: String(c?.name || '').trim() });
        });
        // 10개 미만이면 fallback 병합
        if (byCode.size < 10) {
            fallback.forEach((c) => {
                const code = String(c?.code || '').trim();
                if (!code) return;
                if (!byCode.has(code)) byCode.set(code, { code, name: String(c?.name || '').trim() });
            });
        }
        list = Array.from(byCode.values()).sort((a, b) => String(a.code).localeCompare(String(b.code)));

        const cur = sel.value || '+63';
        sel.innerHTML = '';
        list.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.code;
            // 드롭다운에는 국가명도 함께 노출 (요구사항: 국가별 국가번호 옵션)
            opt.textContent = (c.name ? `${c.code} (${c.name})` : c.code);
            sel.appendChild(opt);
        });
        sel.value = cur;
    } catch (e) {
        // ignore (keep default)
    }
}

// 폼 유효성 검사
function checkFormValidity() {
    const nameInput = document.getElementById("name");
    const phoneInput = document.getElementById("phone");
    const findIdBtn = document.getElementById("findIdBtn");
    
    if (!nameInput || !phoneInput || !findIdBtn) return;
    
    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    
    // 이름과 연락처가 모두 입력되어야 활성화
    const isValid = name.length > 0 && phone.length >= 8;
    
    if (isValid) {
        findIdBtn.classList.remove("inactive");
        findIdBtn.disabled = false;
    } else {
        findIdBtn.classList.add("inactive");
        findIdBtn.disabled = true;
    }
}

// 존재하지 않는 회원정보 팝업 표시
function showNoMemberPopup() {
    const noMemberLayer = document.getElementById("noMemberLayer");
    const noMemberPopup = document.getElementById("noMemberPopup");
    if (noMemberLayer && noMemberPopup) {
        noMemberLayer.style.display = 'block';
        noMemberPopup.style.display = 'flex';
    }
}

// 존재하지 않는 회원정보 팝업 숨기기
function hideNoMemberPopup() {
    const noMemberLayer = document.getElementById("noMemberLayer");
    const noMemberPopup = document.getElementById("noMemberPopup");
    if (noMemberLayer && noMemberPopup) {
        noMemberLayer.style.display = 'none';
        noMemberPopup.style.display = 'none';
    }
}

// 아이디 찾기 처리 함수
async function handleFindId() {
    const nameInput = document.getElementById("name");
    const phoneInput = document.getElementById("phone");
    const findIdBtn = document.getElementById("findIdBtn");
    
    if (!nameInput || !phoneInput || !findIdBtn) return;
    
    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    
    // 필수 필드 확인
    if (!name || !phone) {
        alert("Please enter your name and phone number.");
        return;
    }
    
    // 전화번호 형식 확인 (숫자만 허용)
    const phoneRegex = /^[0-9]+$/;
    if (!phoneRegex.test(phone.replace(/\s+/g, ''))) {
        alert("Please enter a valid phone number.");
        return;
    }
    
    try {
        // 로딩 표시
        findIdBtn.disabled = true;
        const currentLang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : (localStorage.getItem('selectedLanguage') || 'en');
        const originalText = findIdBtn.textContent;
        findIdBtn.textContent = "Searching...";
        
        // API 호출
        const countryCode = document.getElementById('countryCodeSelect')?.value
            || document.querySelector('select.select-type1')?.value
            || '+63';
        const normalizedPhone = String(phone).replace(/\s+/g, '');
        // 서버는 숫자만 남겨 비교하므로 +63 포함/미포함 둘 다 허용되지만, 일관성 위해 포함해서 전송
        const fullPhone = `${countryCode} ${normalizedPhone}`.trim();
        const result = await api.findId(name, fullPhone);
        
        if (result.success) {
            // 결과 페이지로 이동하면서 데이터 전달
            const params = new URLSearchParams({
                email: result.data.email,
                maskedEmail: result.data.maskedEmail,
                username: result.data.username
            });
            
            // 현재 언어 파라미터 추가
            if (currentLang && currentLang !== 'ko') {
                params.set('lang', currentLang);
            }
            
            location.href = `find-id-result.html?${params.toString()}`;
        } else {
            // 존재하지 않는 회원정보 팝업 표시
            showNoMemberPopup();
        }
        
    } catch (error) {
        console.error('Find ID error:', error);
        
        // 네트워크 오류인 경우
        alert("A network error occurred. Please try again.");
    } finally {
        // 로딩 해제
        const findIdBtn = document.getElementById("findIdBtn");
        if (findIdBtn) {
            findIdBtn.disabled = false;
            findIdBtn.textContent = "Find ID";
        }
    }
}
