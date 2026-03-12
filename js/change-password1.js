// user/change-password1.html: +      +  

document.addEventListener('DOMContentLoaded', () => {
  const nameInput = document.getElementById('name');
  const emailInput = document.getElementById('email');
  const sendBtn = document.getElementById('sendResetLinkBtn');

  const layer = document.getElementById('resetLinkLayer');
  const popup = document.getElementById('resetLinkPopup');
  const popupTitle = document.getElementById('resetLinkPopupTitle');
  const popupMsg = document.getElementById('resetLinkPopupMsg');
  const okBtn = document.getElementById('resetLinkOkBtn');

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
  }

  function setBtnEnabled(enabled) {
    if (!sendBtn) return;
    sendBtn.disabled = !enabled;
    if (enabled) sendBtn.classList.remove('inactive');
    else sendBtn.classList.add('inactive');
  }

  function showPopup(title, msg, onClose) {
    if (popupTitle) popupTitle.textContent = title;
    if (popupMsg) popupMsg.textContent = msg;
    if (layer) layer.style.display = 'block';
    if (popup) popup.style.display = 'block';

    const close = () => {
      if (layer) layer.style.display = 'none';
      if (popup) popup.style.display = 'none';
      if (onClose) onClose();
    };

    if (layer) layer.onclick = close;
    if (okBtn) okBtn.onclick = close;
  }

  function checkForm() {
    const name = (nameInput?.value || '').trim();
    const email = (emailInput?.value || '').trim();
    setBtnEnabled(!!name && isValidEmail(email));
  }

  nameInput?.addEventListener('input', checkForm);
  emailInput?.addEventListener('input', checkForm);
  checkForm();

  sendBtn?.addEventListener('click', async (e) => {
    e.preventDefault();

    const name = (nameInput?.value || '').trim();
    const email = (emailInput?.value || '').trim();

    if (!name || !email) {
      showPopup('Notice', 'Please enter your name and email.');
      return;
    }
    if (!isValidEmail(email)) {
      showPopup('Notice', 'Please enter a valid email address.');
      return;
    }

    setBtnEnabled(false);
    const prevText = sendBtn.textContent;
    sendBtn.textContent = 'Sending...';

    try {
      const res = await fetch('../backend/api/password-reset-link.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_link', name, email })
      });

      // PHP fatal  JSON    
      const text = await res.text();
      let data = null;
      try { data = JSON.parse(text); } catch (_) {}

      const currentLang = (typeof getCurrentLanguage === 'function') ? getCurrentLanguage() : 'en';
      const texts = (typeof globalLanguageTexts !== 'undefined' && globalLanguageTexts[currentLang])
        ? globalLanguageTexts[currentLang]
        : (typeof globalLanguageTexts !== 'undefined' ? globalLanguageTexts.en : {});

      if (res.ok && data && data.success && (data.data?.mailSent !== false)) {
        showPopup(texts.confirm || 'Confirm', (texts.passwordResetLinkSent || 'Password reset link has been sent to your email.'), () => {
          // stays on page
        });
      } else {
        const msg = (data && data.message)
          ? data.message
          : (texts.noMemberInfo || 'There is no member information that matches the information you entered.');
        showPopup('Notice', msg);
      }
    } catch (err) {
      console.error('send reset link error', err);
      showPopup('Error', 'An error occurred while sending the reset link. Please try again.');
    } finally {
      sendBtn.textContent = prevText;
      checkForm();
    }
  });
});


