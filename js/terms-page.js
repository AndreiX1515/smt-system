// user/terms.html: 관리자에서 저장한 약관(terms 테이블) 내용을 표시

function getQueryParam(name) {
  try {
    return new URLSearchParams(window.location.search).get(name);
  } catch {
    return null;
  }
}

function escapeHtml(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : String(s);
  return div.innerHTML;
}

function categoryLabel(category, lang) {
  // UI labels must come from i18n (English/Tagalog only). DB content is displayed as-is.
  const map = {
    terms: { key: 'termsOfUse', fallback: 'Terms of Use' },
    privacy_collection: { key: 'privacyCollection', fallback: 'Personal Information Collection and Use' },
    privacy_sharing: { key: 'privacyThirdParty', fallback: 'Personal Information Third Party Provision' },
    marketing_consent: { key: 'marketingConsent', fallback: 'Marketing Use Consent' },
  };
  const it = map[category] || null;
  const key = it?.key;
  const fallback = it?.fallback || category;
  try {
    if (key && typeof window.getI18nText === 'function') {
      const v = String(window.getI18nText(key, lang) || '').trim();
      if (v) return v;
    }
  } catch (_) {}
  return fallback;
}

document.addEventListener('DOMContentLoaded', async () => {
  const lang = (localStorage.getItem('selectedLanguage') || document.documentElement.lang || 'en').toLowerCase();
  let category = (getQueryParam('category') || 'terms').toLowerCase();
  const from = (getQueryParam('from') || '').toLowerCase();
  // 과거 링크 호환: marketing -> marketing_consent
  if (category === 'marketing') category = 'marketing_consent';

  const titleEl = document.getElementById('termsTitle');
  const contentEl = document.getElementById('termsContent');
  const titleText = categoryLabel(category, lang);

  // Always show correct title per category (requested).
  const headerTitleEl = document.querySelector('.header-type2 .title');
  if (titleEl) {
    titleEl.style.display = '';
    titleEl.textContent = titleText;
  }
  if (headerTitleEl) headerTitleEl.textContent = titleText;
  try { document.title = `Smart Travel | ${titleText}`; } catch (_) {}

  // 요구사항(id 52): 회원가입 약관 상세에서 뒤로가기 클릭 시 회원가입 페이지로 복귀
  try {
    if (from === 'join') {
      const backBtn = document.querySelector('.header-type2 .btn-mypage');
      if (backBtn) {
        backBtn.addEventListener('click', (e) => {
          e.preventDefault();
          // history가 있으면 우선 사용(입력값 복원 로직이 있어 UX가 더 좋음)
          if (window.history && window.history.length > 1) {
            window.history.back();
            return;
          }
          window.location.href = `join.php?lang=${encodeURIComponent(lang === 'tl' ? 'tl' : 'en')}`;
        });
      }
    }
  } catch (_) {}

  try {
    // backend/api/terms.php는 lang 파라미터명을 `lang`으로 받습니다.
    const res = await fetch(`../backend/api/terms.php?category=${encodeURIComponent(category)}&lang=${encodeURIComponent(lang)}`, {
      credentials: 'same-origin'
    });
    const json = await res.json().catch(() => ({}));
    const content = json?.data?.content ?? '';

    // textarea로 저장된 plain text를 안전하게 표시 (줄바꿈 유지)
    const safe = escapeHtml(content).replace(/\n/g, '<br>');
    if (contentEl) contentEl.innerHTML = safe || '<span style="color:#6b7280;">(No content)</span>';
  } catch (e) {
    console.error(e);
    if (contentEl) contentEl.textContent = 'Failed to load terms.';
  }
});


