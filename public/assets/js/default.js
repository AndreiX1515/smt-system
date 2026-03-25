/* ============================================================
   default.js — Admin shared utilities + layout init
   FIX: Removed Flowbite. Added HSStaticMethods.autoInit().
        Body stays hidden until FlyonUI + fragments are ready.
   ============================================================ */

/* ── Custom select ──────────────────────────────────────── */
function jw_select() {
  const el = (t, cls) => {
    const n = document.createElement(t);
    if (cls) n.className = cls;
    return n;
  };
  const offWidth = (s) => {
    const t = el("div");
    t.style.cssText = "position:absolute;visibility:hidden;white-space:nowrap;display:inline-block;";
    t.textContent = s;
    document.body.appendChild(t);
    const w = t.offsetWidth;
    t.remove();
    return w;
  };

  if (!window.__jwselect_docbound) {
    document.addEventListener("click", () => {
      document.querySelectorAll(".jw-select.open").forEach((wrap) => {
        wrap.classList.remove("open");
        const box = wrap.querySelector(".jw-selected");
        if (box) (box.classList.remove("active"), box.setAttribute("aria-expanded", "false"));
      });
    });
    window.__jwselect_docbound = true;
  }

  document.querySelectorAll(".select").forEach((nativeSel) => {
    nativeSel.classList.remove("select");
    nativeSel.classList.add("jw-sr-only");

    const wrap = el("div", "jw-select");
    nativeSel.parentNode.insertBefore(wrap, nativeSel);
    wrap.appendChild(nativeSel);

    const box = el("div", "jw-selected");
    box.setAttribute("role", "button");
    box.setAttribute("aria-haspopup", "listbox");
    box.setAttribute("aria-expanded", "false");
    wrap.appendChild(box);

    const setBox = () => {
      const opt = nativeSel.selectedOptions[0] || nativeSel.options[0];
      const label = opt ? opt.textContent : "";
      const icon = opt?.dataset?.icon;
      box.innerHTML = icon ? `<i class="${icon}"></i> ${label}` : label;
    };
    setBox();

    const list = el("ul", "jw-select-list");
    list.setAttribute("role", "listbox");
    wrap.appendChild(list);

    let maxWidth = 0;
    Array.from(nativeSel.options).forEach((o) => {
      const li = el("li", "jw-select-item");
      li.setAttribute("role", "option");
      li.dataset.value = o.value;
      li.innerHTML = o.dataset?.icon ? `<i class="${o.dataset.icon}"></i> ${o.textContent}` : o.textContent;
      if (o.disabled) { li.setAttribute("aria-disabled", "true"); li.classList.add("is-disabled"); }
      if (o.selected) li.setAttribute("aria-selected", "true");
      list.appendChild(li);
      maxWidth = Math.max(maxWidth, offWidth(o.textContent));
    });

    if (maxWidth) wrap.style.minWidth = maxWidth + 40 + "px";
    if (nativeSel.dataset.direction === "up") wrap.classList.add("up");
    if (nativeSel.disabled) { wrap.classList.add("is-disabled"); box.setAttribute("aria-disabled", "true"); box.tabIndex = -1; }

    const openList = () => {
      if (nativeSel.disabled) return;
      wrap.classList.add("open"); box.classList.add("active"); box.setAttribute("aria-expanded", "true");
    };
    const closeList = () => {
      wrap.classList.remove("open"); box.classList.remove("active"); box.setAttribute("aria-expanded", "false");
    };
    const choose = (itemEl) => {
      if (itemEl.getAttribute("aria-disabled") === "true") return;
      list.querySelectorAll('.jw-select-item[aria-selected="true"]').forEach((li) => li.removeAttribute("aria-selected"));
      itemEl.setAttribute("aria-selected", "true");
      nativeSel.value = itemEl.dataset.value;
      nativeSel.dispatchEvent(new Event("change", { bubbles: true }));
      setBox(); closeList();
    };

    box.addEventListener("click", (e) => { e.stopPropagation(); wrap.classList.contains("open") ? closeList() : openList(); });
    list.addEventListener("click", (e) => { const item = e.target.closest(".jw-select-item"); if (!item) return; e.stopPropagation(); choose(item); });
  });
}


/* ── Cookie helpers ─────────────────────────────────────── */
function setCookie(name, value, days) {
  const d = new Date();
  d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`;
}


function getCookie(name) {
  const seg = `; ${document.cookie}`.split(`; ${name}=`);
  return seg.length === 2 ? decodeURIComponent(seg.pop().split(";").shift()) : null;
}


/* ── Korean → English popup patch ──────────────────────── */
(function patchPopupToEnglish() {
  if (window.__jwPopupEnglishPatched) return;
  window.__jwPopupEnglishPatched = true;
  const hasKorean = (s) => /[가-힣]/.test(String(s || ""));
  const translateAlert = (msg) => {
    let s = String(msg ?? "");
    if (!hasKorean(s)) return s;
    s = s.replace(/\s+/g, " ").trim();
    const rules = [
      [/^관리자 로그인이 필요합니다\.?$/g, "Admin login required."],
      [/^로그인이 필요합니다\.?$/g, "Login required."],
      [/^저장되었습니다\.?$/g, "Saved."],
      [/^임시저장되었습니다\.?$/g, "Temporarily saved."],
      [/^삭제되었습니다\.?$/g, "Deleted."],
      [/^삭제할 .* 없습니다\.?$/g, "Nothing to delete."],
      [/^상품명이 필요합니다\.?$/g, "Product name is required."],
      [/^카테고리를 선택해주세요\.?$/g, "Please select a category."],
      [/^필수 필드가 누락되었습니다.*$/g, "Required fields are missing."],
      [/^필수 항목을 모두 입력해주세요.*$/g, "Please fill in all required fields."],
      [/^시작일과 종료일을 모두 선택해주세요\.?$/g, "Please select both start and end dates."],
      [/^다운로드 중 오류가 발생했습니다\.?$/g, "An error occurred while downloading."],
      [/불러오는 중 오류가 발생했습니다\.?$/g, "An error occurred while loading."],
      [/저장 중 오류가 발생했습니다\.?$/g, "An error occurred while saving."],
      [/삭제 중 오류가 발생했습니다\.?$/g, "An error occurred while deleting."],
      [/업로드 중 오류가 발생했습니다\.?$/g, "An error occurred while uploading."],
      [/등록 중 오류가 발생했습니다\.?$/g, "An error occurred while creating."],
      [/수정 중 오류가 발생했습니다.*$/g, "An error occurred while updating."],
      [/^올바른 이메일 형식을 입력해주세요\.?$/g, "Please enter a valid email address."],
      [/^아이디를 입력해주세요\.?$/g, "Please enter your ID."],
      [/^비밀번호를 입력해주세요.*$/g, "Please enter a password."],
      [/^계정 정보를 찾을 수 없습니다\.?$/g, "Account information not found."],
      [/^객실을 선택해주세요\.?$/g, "Please select rooms."],
      [/^객실 수용 인원이 부족합니다\.?$/g, "Insufficient room capacity."],
      [/^비밀번호가 변경되었습니다\.?$/g, "Password has been changed."],
    ];
    for (const [re, out] of rules) { if (re.test(s)) return out; }
    return "Please check the information.";
  };
  const translateConfirm = (msg) => {
    let s = String(msg ?? "");
    if (!hasKorean(s)) return s;
    s = s.replace(/\s+/g, " ").trim();
    const rules = [
      [/삭제하시겠습니까\??/g, "Are you sure you want to delete?"],
      [/저장하시겠습니까\??/g, "Do you want to save?"],
      [/로그아웃하시겠습니까\??/g, "Do you want to log out?"],
    ];
    for (const [re, out] of rules) { if (re.test(s)) return out; }
    return "Are you sure?";
  };
  const __alert = window.alert ? window.alert.bind(window) : (m) => void m;
  const __confirm = window.confirm ? window.confirm.bind(window) : () => true;
  window.alert = (message) => __alert(translateAlert(message));
  window.confirm = (message) => __confirm(translateConfirm(message));
})();


/* ── i18n helpers ───────────────────────────────────────── */
function getLangText(el, lang) {
  lang = String(lang || "").toLowerCase();
  if (lang === "tl") return el.getAttribute("data-lan-tl") || el.getAttribute("data-lan-eng");
  return el.getAttribute("data-lan-eng") || el.getAttribute("data-lan-tl");
}


function __admin_force_lang_cookie() {
  try {
    const cur = String(getCookie("lang") || "").toLowerCase();
    const lang = cur === "tl" ? "tl" : "eng";
    setCookie("lang", lang, 365);
    return lang;
  } catch (_) {
    try { setCookie("lang", "eng", 365); } catch (_) {}
    return "eng";
  }
}


function __admin_scrub_hangul(root = document.body) {
  try {
    if (!root) return;
    const hasHangul = (s) => /[가-힣]/.test(String(s || ""));
    const stripHangul = (s) => String(s || "").replace(/[가-힣]+/g, "").replace(/\s{2,}/g, " ").trim();
    const ensureEnglish = (orig, cleaned) => {
      if (!hasHangul(orig)) return cleaned;
      return cleaned && cleaned.length ? cleaned : orig;
    };
    const isI18nUiEl = (el) => {
      try {
        if (!el || !el.closest) return false;
        return !!el.closest("[data-lan-eng],[data-lan-tl],[data-lan-ko],[data-lan-kor],[data-i18n],[data-i18n-placeholder],[data-i18n-title],[data-i18n-alt]");
      } catch (_) { return false; }
    };
    try {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      for (const n of nodes) {
        if (!n || !n.nodeValue) continue;
        try { const p = n.parentElement; if (p && (p.tagName === "SCRIPT" || p.tagName === "STYLE" || p.tagName === "NOSCRIPT")) continue; } catch (_) {}
        try { const p = n.parentElement || n.parentNode; if (p && p.closest && p.closest('[data-skip-hangul-scrub="1"]')) continue; } catch (_) {}
        try { const p = n.parentElement; if (!isI18nUiEl(p)) continue; } catch (_) { continue; }
        if (!hasHangul(n.nodeValue)) continue;
        n.nodeValue = ensureEnglish(n.nodeValue, stripHangul(n.nodeValue));
      }
    } catch (_) {}
    try {
      root.querySelectorAll("*").forEach((el) => {
        if (!el || !el.getAttribute) return;
        try { if (el.closest && el.closest('[data-skip-hangul-scrub="1"]')) return; } catch (_) {}
        if (!isI18nUiEl(el)) return;
        for (const a of ["placeholder", "title", "aria-label", "alt"]) {
          const v = el.getAttribute(a);
          if (!v || !hasHangul(v)) continue;
          if (a === "alt") el.setAttribute(a, "Image");
          else if (a === "placeholder") el.setAttribute(a, "Please enter a value.");
          else el.setAttribute(a, ensureEnglish(v, stripHangul(v)));
        }
      });
    } catch (_) {}
    try {
      if (!window.__admin_hangul_observer && root && root.ownerDocument) {
        let busy = false;
        const obs = new MutationObserver((mutations) => {
          if (busy) return;
          busy = true;
          try {
            for (const m of mutations) {
              if (m.type === "childList" && m.addedNodes && m.addedNodes.length) {
                m.addedNodes.forEach((node) => {
                  try {
                    if (node && node.nodeType === Node.ELEMENT_NODE) {
                      const tag = node.tagName;
                      if (tag === "SCRIPT" || tag === "STYLE" || tag === "NOSCRIPT") return;
                      __admin_scrub_hangul(node);
                    } else if (node && node.nodeType === Node.TEXT_NODE && node.nodeValue && hasHangul(node.nodeValue)) {
                      try { const p = node.parentElement; if (p && (p.tagName === "SCRIPT" || p.tagName === "STYLE" || p.tagName === "NOSCRIPT")) return; } catch (_) {}
                      node.nodeValue = ensureEnglish(node.nodeValue, stripHangul(node.nodeValue));
                    }
                  } catch (_) {}
                });
              } else if (m.type === "attributes" && m.target && m.target.nodeType === Node.ELEMENT_NODE) {
                try { __admin_scrub_hangul(m.target); } catch (_) {}
              }
            }
          } finally { busy = false; }
        });
        obs.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ["placeholder", "title", "aria-label", "alt"] });
        window.__admin_hangul_observer = obs;
      }
    } catch (_) {}
  } catch (_) {}
}


function language_apply(lang) {
  lang = String(lang || "").toLowerCase();
  if (lang !== "eng" && lang !== "tl" && lang !== "kor" && lang !== "ko") lang = "eng";
  if (lang === "ko") lang = "kor";
  document.querySelectorAll("[data-lan-eng],[data-lan-tl],[data-lan-kor],[data-lan-ko]").forEach((el) => {
    const txt = getLangText(el, lang);
    if (!txt) return;
    const tag = el.tagName;
    if (tag === "INPUT" || tag === "TEXTAREA") {
      if (el.hasAttribute("placeholder")) el.setAttribute("placeholder", txt);
      else el.value = txt;
    } else if (tag === "IMG") {
      el.setAttribute("alt", txt);
    } else {
      el.textContent = txt;
    }
  });
  document.querySelectorAll("[data-lan-eng-placeholder]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-placeholder");
    const tl  = el.getAttribute("data-lan-tl-placeholder");
    const kor = el.getAttribute("data-lan-kor-placeholder") || el.getAttribute("data-lan-ko-placeholder");
    if (lang === "eng" && eng) el.setAttribute("placeholder", eng);
    else if (lang === "tl") el.setAttribute("placeholder", tl || eng || el.getAttribute("placeholder") || "");
    else if ((lang === "kor" || lang === "ko") && kor) el.setAttribute("placeholder", kor);
  });
  document.querySelectorAll("[data-lan-eng-alt]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-alt");
    const tl  = el.getAttribute("data-lan-tl-alt");
    const kor = el.getAttribute("data-lan-kor-alt") || el.getAttribute("data-lan-ko-alt");
    if (lang === "eng" && eng) el.setAttribute("alt", eng);
    else if (lang === "tl") el.setAttribute("alt", tl || eng || el.getAttribute("alt") || "");
    else if ((lang === "kor" || lang === "ko") && kor) el.setAttribute("alt", kor);
  });
  document.querySelectorAll("select option[data-lan-eng]").forEach((option) => {
    const eng = option.getAttribute("data-lan-eng");
    const tl  = option.getAttribute("data-lan-tl");
    const kor = option.getAttribute("data-lan-kor") || option.getAttribute("data-lan-ko");
    if (lang === "eng" && eng) option.textContent = eng;
    else if (lang === "tl") option.textContent = tl || eng || option.textContent;
    else if ((lang === "kor" || lang === "ko") && kor) option.textContent = kor;
    else option.textContent = kor || option.getAttribute("data-lan-eng") || option.textContent;
  });
  try { if (typeof window.refreshAllJwSelect === "function") window.refreshAllJwSelect(); } catch (_) {}
  document.querySelectorAll(".lang-text").forEach((node) => {
    if (lang === "eng") node.textContent = "한국어";
    else if (lang === "kor") node.textContent = "Tagalog";
    else node.textContent = "English";
  });
  const htmlLang = document.getElementById("html-lang");
  if (htmlLang) {
    if (lang === "tl") htmlLang.setAttribute("lang", "tl");
    else if (lang === "kor") htmlLang.setAttribute("lang", "ko");
    else htmlLang.setAttribute("lang", "en");
  }
  window.dispatchEvent(new CustomEvent("languageChanged", { detail: { lang } }));
}


window.refreshAllJwSelect = function () {
  try {
    document.querySelectorAll(".jw-select").forEach((wrap) => {
      const nativeSel = wrap.querySelector("select");
      const box = wrap.querySelector(".jw-selected");
      const list = wrap.querySelector(".jw-select-list");
      if (!nativeSel || !box || !list) return;
      if (nativeSel.disabled) { wrap.classList.add("is-disabled"); box.setAttribute("aria-disabled", "true"); box.tabIndex = -1; }
      else { wrap.classList.remove("is-disabled"); box.removeAttribute("aria-disabled"); box.tabIndex = 0; }
      const opt = nativeSel.selectedOptions?.[0] || nativeSel.options?.[0] || null;
      const icon = opt?.dataset?.icon || null;
      box.innerHTML = icon ? `<i class="${icon}"></i> ${opt.textContent || ""}` : (opt ? opt.textContent || "" : "");
      list.innerHTML = "";
      Array.from(nativeSel.options || []).forEach((o) => {
        const li = document.createElement("li");
        li.className = "jw-select-item";
        li.setAttribute("role", "option");
        li.dataset.value = o.value;
        li.innerHTML = o.dataset?.icon ? `<i class="${o.dataset.icon}"></i> ${o.textContent}` : o.textContent || "";
        if (o.disabled) { li.setAttribute("aria-disabled", "true"); li.classList.add("is-disabled"); }
        if (o.selected) li.setAttribute("aria-selected", "true");
        list.appendChild(li);
      });
    });
  } catch (_) {}
};


function language_set() {
  const cur = getCookie("lang") || "eng";
  let next = "eng";
  if (cur === "eng") next = "kor";
  else if (cur === "kor" || cur === "ko") next = "tl";
  else next = "eng";
  setCookie("lang", next, 365);
  try { language_apply(next); } catch (_) {}
  try { setTimeout(() => { try { language_apply(next); } catch (_) {} }, 0); } catch (_) {}
}


try { console.log("[ADMIN] default.js loaded:", new Date().toISOString()); } catch (_) {}




/* ── FIX 1: i18n bootstrap ──────────────────────────────────
   Hides body immediately, applies language + scrubs Korean.
   Does NOT reveal body here — init() does that after FlyonUI
   is ready. This prevents any unstyled/Korean flash on load.
   ──────────────────────────────────────────────────────── */
(function __admin_i18n_bootstrap() {
  try {
    const root = document.documentElement;

    // Hide body so nothing unstyled or Korean flashes
    root.setAttribute("data-admin-i18n-ready", "0");

    // Enforce eng/tl only — no Korean cookie
    const lang = __admin_force_lang_cookie();
    try { root.setAttribute("data-lang", lang === "tl" ? "tl" : "en"); root.lang = lang === "tl" ? "tl" : "en"; } catch (_) {}

    // Apply language and scrub any remaining Korean
    try { language_apply(lang); } catch (_) {}
    try { __admin_scrub_hangul(document.body); } catch (_) {}

    // NOTE: body reveal is intentionally deferred to init()
    // so FlyonUI is initialized before the user sees anything

  } catch (_) {
    // Hard fallback — never leave a permanently blank page
    try { document.documentElement.setAttribute("data-admin-i18n-ready", "1"); } catch (_) {}
  }
})();




/* ── Modal ──────────────────────────────────────────────── */
class Modal {
  constructor(action, width, sq) {
    this.action = action;
    this.width = width;
    this.sq = sq;
    this.el = null;
    this._abort = null;
  }
  static _toQuery(data) {
    if (!data) return "";
    if (typeof data === "string") return data.replace(/^\?/, "");
    if (data instanceof FormData) return new URLSearchParams([...data.entries()]).toString();
    return new URLSearchParams(Object.entries(data)).toString();
  }
  static _execScripts(container) {
    try {
      if (!container) return;
      Array.from(container.querySelectorAll("script")).forEach((old) => {
        const s = document.createElement("script");
        Array.from(old.attributes || []).forEach((a) => s.setAttribute(a.name, a.value));
        s.text = old.src ? "" : old.textContent || "";
        if (old.src) s.src = old.src;
        old.parentNode?.replaceChild(s, old);
      });
    } catch (e) { console.error("[Modal] script exec failed:", e); }
  }
  async _loadHTML(url, data) {
    const u = new URL(url, location.href);
    u.searchParams.set("_", Date.now());
    if (this._abort) this._abort.abort();
    this._abort = new AbortController();
    const timer = setTimeout(() => this._abort?.abort(), 15000);
    try {
      const opts = data
        ? { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" }, body: Modal._toQuery(data), signal: this._abort.signal }
        : { signal: this._abort.signal, credentials: "same-origin" };
      const res = await fetch(u.toString(), opts);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.text();
    } finally { clearTimeout(timer); this._abort = null; }
  }
  async open() {
    const d = document.createElement("dialog");
    if (this.width) { const w = typeof this.width === "number" ? `${this.width}px` : this.width; d.style.setProperty("--modal-width", w); }
    d.classList.add("jw-dialog");
    d.innerHTML = `<div class="dialog-loading"><span class="dialog-loading-dot"></span><span class="dialog-loading-dot"></span><span class="dialog-loading-dot"></span></div>`;
    document.body.appendChild(d);
    this.el = d;
    typeof d.showModal === "function" ? d.showModal() : d.setAttribute("open", "");
    d.addEventListener("click", (e) => { if (e.target === d) this.close(); });
    d.addEventListener("cancel", (e) => { e.preventDefault(); this.close(); });
    if (this.action) {
      try {
        const html = await this._loadHTML(this.action, this.sq);
        d.innerHTML = html;
        d.classList.add("dialog--ready");
        Modal._execScripts(d);
        const lang = getCookie("lang") || "eng";
        setCookie("lang", lang, 365);
        await Promise.resolve(language_apply(lang));
        if (typeof jw_select === "function") await Promise.resolve(jw_select());
        d.querySelector("#closeDialog")?.addEventListener("click", () => this.close(), { once: true });
        document.dispatchEvent(new CustomEvent("modal:loaded", { detail: { dialog: d, action: this.action, data: this.sq } }));
      } catch (err) {
        d.innerHTML = `<div class="dialog-error">Failed to load content. Please try again.</div>`;
        console.error("[Modal] load error:", err);
      }
    }
    return this;
  }
  async update(action, sq) {
    if (!this.el) return;
    const url = action || this.action;
    const data = typeof sq !== "undefined" ? sq : this.sq;
    if (!url) return;
    try {
      const html = await this._loadHTML(url, data);
      this.el.innerHTML = html;
      Modal._execScripts(this.el);
      this.el.querySelector("#closeDialog")?.addEventListener("click", () => this.close(), { once: true });
      document.dispatchEvent(new CustomEvent("modal:loaded", { detail: { dialog: this.el, action: url, data } }));
    } catch (err) { this.el.innerHTML = `<div class="dialog-error">Failed to load content.</div>`; }
  }
  close() {
    if (!this.el) return;
    this._abort?.abort();
    this.el.classList.add("dialog--closing");
    this.el.addEventListener("animationend", () => { if (typeof this.el?.close === "function") this.el.close(); this.el?.remove(); this.el = null; }, { once: true });
  }
}


function modal(page, w, sq) { const m = new Modal(page, w, sq); m.open(); return m; }

function modal_close() {
  const dialogs = document.querySelectorAll("dialog.jw-dialog");
  if (!dialogs.length) return;
  const last = dialogs[dialogs.length - 1];
  last.classList.add("dialog--closing");
  last.addEventListener("animationend", () => { typeof last.close === "function" && last.close(); last.remove(); }, { once: true });
}


/* ── Member info dropdown ───────────────────────────────── */
function member_info(btn, page) {
  const root = btn.closest(".membermenu") || btn;
  const box = root.querySelector(".position");
  if (!box) return;
  let wrap = box.querySelector(".wrap");
  if (!wrap) { wrap = document.createElement("div"); wrap.className = "wrap"; box.appendChild(wrap); }
  if (box.classList.contains("on")) return closeBox();
  if (!wrap.innerHTML.trim()) {
    wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#6b7280;">Loading...</div>';
    fetch(page + "?" + Date.now(), { cache: "no-store" })
      .then((r) => { if (!r.ok) throw 0; return r.text(); })
      .then((html) => { wrap.innerHTML = html; initLogoutButton(wrap); hydrateAdminIdentityUI(wrap); openBox(); })
      .catch(() => { wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#ef4444;">Failed to load.</div>'; openBox(); });
  } else { openBox(); }
  function openBox() {
    box.classList.add("on");
    const onDocDown = (e) => { if (!root.contains(e.target)) closeBox(); };
    const onInsideClick = (e) => { const t = e.target.closest(".closeMember_info"); if (t) { e.preventDefault(); closeBox(); } };
    removeListeners();
    document.addEventListener("pointerdown", onDocDown, true);
    box.addEventListener("click", onInsideClick);
    box._off = () => { document.removeEventListener("pointerdown", onDocDown, true); box.removeEventListener("click", onInsideClick); };
  }
  function closeBox() { box.classList.remove("on"); removeListeners(); }
  function removeListeners() { if (box._off) { box._off(); box._off = null; } }
}


/* ── Auth helpers ───────────────────────────────────────── */
function initLogoutButton(container) {
  const logoutBtn = container ? container.querySelector("#logoutBtn") : document.getElementById("logoutBtn");
  if (logoutBtn && !logoutBtn.hasAttribute("data-logout-initialized")) {
    logoutBtn.setAttribute("data-logout-initialized", "true");
    logoutBtn.addEventListener("click", handleLogout);
  }
  const changePasswordBtn = container ? container.querySelector("#changePasswordBtn") : document.getElementById("changePasswordBtn");
  if (changePasswordBtn && !changePasswordBtn.hasAttribute("data-change-password-initialized")) {
    changePasswordBtn.setAttribute("data-change-password-initialized", "true");
    changePasswordBtn.addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); modal("/admin/member/change-password.html", "580px", "520px"); });
  }
}


// Delegated click for change password — handles dynamic header load timing
(function bindChangePasswordDelegatedOnce() {
  try {
    if (window.__ST_ADMIN_CHANGE_PW_DELEGATED__) return;
    window.__ST_ADMIN_CHANGE_PW_DELEGATED__ = true;
    document.addEventListener("click", function (e) {
      const t = e.target && e.target.closest ? e.target.closest("#changePasswordBtn") : null;
      if (!t) return;
      e.preventDefault(); e.stopPropagation();
      try { modal("/admin/member/change-password.html", "580px", "520px"); }
      catch (err) { console.error("Failed to open change-password modal:", err); alert("Failed to open Change Password."); }
    }, true);
  } catch (_) {}
})();

async function hydrateAdminIdentityUI(root) {
  try {
    const res = await fetch("../backend/api/check-session.php", { credentials: "same-origin", cache: "no-store" });
    const data = await res.json().catch(() => ({}));
    if (!data || !data.authenticated) return;
    const headerName = document.querySelector(".layout-header .user-name");
    if (headerName) {
      const ut = String(data.userType || "");
      if (ut === "admin") headerName.textContent = "Admin";
      else if (ut === "agent") headerName.textContent = data.displayName || "Agent";
      else if (ut === "guide") headerName.textContent = data.displayName || "Guide";
      else if (ut === "cs") headerName.textContent = "CS";
      else headerName.textContent = data.displayName || "Admin";
    }
    const scope = root || document;
    const nameEl = scope.querySelector(".header_memberinfo .name");
    const roleEl = scope.querySelector(".header_memberinfo .role");
    if (nameEl) nameEl.textContent = data.displayName || "ADMIN";
    if (roleEl) roleEl.textContent = data.roleLabel || "Employee";
  } catch (_) {}
}

let isLoggingOut = false;

async function handleLogout(e) {
  if (e) { e.preventDefault(); e.stopPropagation(); }
  if (isLoggingOut) return;
  if (!confirm("Do you want to log out?")) return;
  isLoggingOut = true;
  try {
    const response = await fetch("../../public/backend/api/logout.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" } });
    if (!response.ok) throw new Error("HTTP " + response.status);
    const data = await response.json();
    if (data.success) { try { localStorage.removeItem("accountType"); } catch (_) {} window.location.href = "../index.php"; }
    else { isLoggingOut = false; alert(data.message || "Logout failed."); }
  } catch (error) {
    console.error("Logout error:", error);
    isLoggingOut = false;
    try { localStorage.removeItem("accountType"); } catch (_) {}
    window.location.href = "../index.php";
  }
}



/* ── Misc UI helpers ────────────────────────────────────── */
function setFabMenuState(menu, open) {
  if (!menu) return;
  if (open) { menu.classList.add("on"); menu.setAttribute("aria-hidden", "false"); if ("inert" in menu) menu.inert = false; }
  else { menu.classList.remove("on"); menu.setAttribute("aria-hidden", "true"); if ("inert" in menu) menu.inert = true; }
}


function layerToggle(btn) {
  const menu = btn.closest(".layerToggleWrap")?.querySelector(":scope > .fab-menu");
  if (!menu) return;
  const willOpen = !menu.classList.contains("on");
  setFabMenuState(menu, willOpen);
  if (willOpen) { const f = menu.querySelector('button, [tabindex]:not([tabindex="-1"])'); if (f) f.focus(); } else btn.focus();
}

function helpTextChange(target, v) { const el = document.querySelector(target); if (!el) return; el.textContent = v || ""; }


function filePreview(el) {
  const f = el.files && el.files[0];
  if (!f) return;
  const box = el.closest(".image-preview") || el.closest(".banner-item")?.querySelector(".image-preview") || el.closest(".banner-image-wrap") || el.closest(".upload-item") || null;
  if (!box) return;
  const url = URL.createObjectURL(f);
  if (box.classList && box.classList.contains("upload-item")) {
    box.classList.add("is-filled", "has-image");
    try { box.style.backgroundImage = ""; } catch (_) {}
    try { const lab = box.querySelector("label.inputFile"); if (lab) lab.style.display = "none"; } catch (_) {}
    let img = box.querySelector("img.preview");
    if (!img) { img = document.createElement("img"); img.className = "preview"; img.alt = ""; box.insertAdjacentElement("afterbegin", img); }
    try { img.onload = () => { try { URL.revokeObjectURL(url); } catch (_) {} img.onload = null; }; } catch (_) {}
    img.src = url;
    try {
      if (!box.querySelector(".btn-close")) {
        const closeBtn = document.createElement("button");
        closeBtn.type = "button"; closeBtn.className = "jw-button btn-close"; closeBtn.setAttribute("aria-label", "remove");
        closeBtn.innerHTML = '<div class="ic-close" style="width:15px;height:15px;--line:1px;--color:#969696"></div>';
        box.appendChild(closeBtn);
      }
      const b = box.querySelector(".btn-close"); if (b) b.style.zIndex = "5";
    } catch (_) {}
    setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 60000);
    return;
  }
  box.style.backgroundImage = `url("${url}")`; box.style.backgroundSize = "cover"; box.style.backgroundPosition = "center"; box.classList.add("has-image");
  setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 60000);
}


function essentialCheck(it) {
  const wrap = it.closest('[data-essentialWrap="y"]');
  if (!wrap) return false;
  const essentials = wrap.querySelectorAll('[data-essential="y"]');
  if (!essentials.length) return false;
  const allOK = Array.prototype.every.call(essentials, (el) => {
    const tag = el.tagName; const type = (el.type || "").toLowerCase();
    if (type === "checkbox" || type === "radio") return el.checked;
    if (tag === "SELECT") return (el.value ?? "") !== "";
    if (type === "file") return el.files && el.files.length > 0;
    return (el.value || "").trim().length > 0;
  });
  wrap.querySelectorAll('[data-essentialTarget="y"]').forEach((tg) => { tg.disabled = !allOK; tg.classList.toggle("active", allOK); tg.classList.toggle("inactive", !allOK); });
  return allOK;
}

function togglePlusMinus(btn) {
  var hasOn = btn.classList.contains("on"); var img = btn.querySelector("img");
  var target = btn.getAttribute("data-target-section");
  var targets = target ? document.querySelectorAll('[data-section-name="' + target + '"]') : [];
  if (hasOn) { btn.classList.remove("on"); targets.forEach(function(el) { if (el !== btn) el.classList.remove("hidden"); }); img.src = "../image/minus.svg"; img.alt = "minus"; }
  else { btn.classList.add("on"); targets.forEach(function(el) { if (el !== btn) el.classList.add("hidden"); }); img.src = "../image/plus.svg"; img.alt = "plus"; }
}

function scrollToSection(sectionId) {
  var target = document.getElementById(sectionId) || document.querySelector('[data-section-name="' + sectionId + '"]') || document.querySelector('h2.section-title[data-lan-eng="' + sectionId + '"]');
  if (!target) return;
  if (target.classList.contains("hidden")) { var btn = document.querySelector('button.aside-add-section[data-target-section="' + sectionId + '"]'); if (btn && btn.classList.contains("on")) togglePlusMinus(btn); }
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState(sectionId);
}


function updateAsideActiveState(sectionId) {
  document.querySelectorAll(".aside-row").forEach(function(row) {
    row.classList.remove("is-active");
    var t = row.querySelector(".aside-row__title, .aside-row__subtitle");
    if (t && t.getAttribute("data-scroll-target") === sectionId) row.classList.add("is-active");
  });
}


function scrollToDayPanel(dayNum) {
  var target = (dayNum === 1 ? document.getElementById("nday") : null) || document.querySelector('.nday-panel[data-day="' + dayNum + '"]');
  if (!target) return;
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState("day-" + dayNum);
}


function trash_typeA(it) {
  var tbody = it.closest("tbody"); if (!tbody) return;
  if (tbody.querySelectorAll("tr").length === 1) { modal("/admin/super/template-detail-modal2.html", "580px", "252px"); return; }
  var tr = it.closest("tr"); if (tr) tr.remove();
  try {
    Array.prototype.forEach.call(tbody.querySelectorAll("tr"), function(row, idx) {
      var first = row.querySelector("td"); if (!first) return;
      var n = parseInt((first.textContent || "").trim(), 10);
      if (!Number.isFinite(n)) return;
      first.textContent = String(idx + 1);
    });
  } catch (_) {}
}


function temp_link(v) { location.href = v; }




/* ── Sidebar toggle ─────────────────────────────────────── */
function toggleLayoutNav() {
  const nav = document.getElementById("layoutNav");
  const btn = document.querySelector(".nav-toggle-btn");
  if (!nav) return;
  if (window.innerWidth <= 768) { const isOpen = nav.classList.toggle("is-open"); btn?.setAttribute("aria-expanded", String(isOpen)); }
  else { const isCollapsed = nav.classList.toggle("is-collapsed"); try { localStorage.setItem("navCollapsed", isCollapsed ? "1" : "0"); } catch (e) {} }
}


function waitForHeaderUserNameAndHydrate(timeoutMs = 3000) {
  const start = Date.now();
  (function loop() {
    if (document.querySelector(".layout-header .user-name")) return hydrateAdminIdentityUI(document);
    if (Date.now() - start > timeoutMs) return;
    requestAnimationFrame(loop);
  })();
}


/* ── Nav accordion + active state ──────────────────────── */
const _nav = {
  init() {
    document.querySelectorAll("#layoutNav .nav-btn").forEach((btn) => {
      if (btn.tagName === "BUTTON") btn.addEventListener("click", () => _nav.toggle(btn));
    });
    _nav.setActive(_nav.currentSlug());
  },
  toggle(btn) {
    const item = btn.closest(".nav-item"); if (!item) return;
    const isOpen = item.classList.contains("is-open");
    document.querySelectorAll("#layoutNav .nav-item.is-open").forEach((el) => {
      if (el === item) return;
      el.classList.remove("is-open");
      const wrap = el.querySelector(":scope > .nav-sub-wrap"); if (wrap) wrap.style.gridTemplateRows = "0fr";
    });
    item.classList.toggle("is-open", !isOpen);
    btn.setAttribute("aria-expanded", String(!isOpen));
    const wrap = item.querySelector(":scope > .nav-sub-wrap"); if (wrap) wrap.style.gridTemplateRows = !isOpen ? "1fr" : "0fr";
  },
  currentSlug() {
    const content = document.getElementById("layoutContent");
    if (content?.dataset.pageSlug) return content.dataset.pageSlug;
    return location.pathname.split("/").pop().replace(/\.(php|html)$/, "");
  },
  setActive(slug) {
    if (!slug) return;
    document.querySelectorAll("#layoutNav .side-link").forEach((a) => a.classList.remove("is-active"));
    document.querySelectorAll("#layoutNav .nav-item").forEach((li) => li.classList.remove("is-open", "has-active"));
    let matched = null;
    document.querySelectorAll("#layoutNav .side-link, #layoutNav a.nav-btn").forEach((a) => {
      const pages = (a.dataset.page || "").split(",").map((s) => s.trim());
      if (pages.includes(slug)) { a.classList.add("is-active"); matched = a; }
    });
    if (!matched) return;
    const parentItem = matched.closest(".nav-item");
    if (parentItem) {
      parentItem.classList.add("is-open", "has-active");
      parentItem.querySelector("button.nav-btn")?.setAttribute("aria-expanded", "true");
      const wrap = parentItem.querySelector(":scope > .nav-sub-wrap"); if (wrap) wrap.style.gridTemplateRows = "1fr";
    }
  },
};





/* ── Fragment loader ────────────────────────────────────── */
async function _loadFragment(url, selector) {
  if (!url) return;
  try {
    const res = await fetch(url, { credentials: "same-origin" });
    const html = await res.text();
    const el = document.querySelector(selector);
    if (el) { el.innerHTML = html; _execScripts(el); }
  } catch (err) { console.error(`[init] fragment load failed: ${url}`, err); }
}


/* ── Script executor — re-runs <script> tags after innerHTML swap */
function _execScripts(container) {
  if (!container) return;
  Array.from(container.querySelectorAll("script")).forEach((old) => {
    const s = document.createElement("script");
    Array.from(old.attributes).forEach((a) => s.setAttribute(a.name, a.value));
    s.text = old.src ? "" : old.textContent || "";
    if (old.src) s.src = old.src;
    old.parentNode?.replaceWith(s, old);
  });
}




/* ── SPA router ─────────────────────────────────────────── */
const _router = {
  _busy: false,
  start() {
    document.addEventListener("click", (e) => {
      const a = e.target.closest("a[href]");
      if (!a || !_router._isInternal(a) || a.target === "_blank" || a.hasAttribute("data-no-router")) return;
      e.preventDefault(); _router.navigate(a.href);
    });
    window.addEventListener("popstate", () => { _router.navigate(location.href, { pushState: false }); });
  },
  _isInternal(a) { try { return new URL(a.href, location.href).origin === location.origin; } catch { return false; } },
  async navigate(href, { pushState = true } = {}) {
    if (_router._busy) return;
    _router._busy = true;
    const content = document.getElementById("layoutContent");
    content?.classList.add("is-loading");
    try {
      const html = await _router._fetch(href); if (!html) return;
      const doSwap = () => _router._swap(html, href, pushState);
      if (document.startViewTransition) { await document.startViewTransition(doSwap).finished; }
      else { content.style.transition = "opacity 130ms ease"; content.style.opacity = "0"; await doSwap(); requestAnimationFrame(() => { content.style.opacity = "1"; }); }
    } finally { content?.classList.remove("is-loading"); _router._busy = false; }
  },
  async _fetch(href) {
    try { const res = await fetch(href, { credentials: "same-origin" }); if (!res.ok) throw new Error(`HTTP ${res.status}`); return await res.text(); }
    catch (err) { console.error("[router] fetch failed:", err); location.href = href; return null; }
  },
  async _swap(html, href, pushState) {
    const doc = new DOMParser().parseFromString(html, "text/html");
    const newSect = doc.getElementById("layoutContent"); if (!newSect) return;
    const content = document.getElementById("layoutContent");
    content.innerHTML = newSect.innerHTML;
    if (newSect.dataset.pageSlug) content.dataset.pageSlug = newSect.dataset.pageSlug;
    const newTitle = doc.querySelector("title"); if (newTitle) document.title = newTitle.textContent;
    if (pushState) history.pushState({}, "", href);
    _execScripts(content);
    if (typeof window.__pageInit === "function") await Promise.resolve(window.__pageInit());

    // Re-apply i18n + custom selects on every SPA swap
    const lang = getCookie("lang") || "eng";
    try { language_apply(lang); } catch (_) {}
    try { if (typeof jw_select === "function") jw_select(); } catch (_) {}

    // FIX 2: Re-init FlyonUI after every SPA content swap
    // Components in the new page content are not wired until this runs
    try { window.HSStaticMethods?.autoInit?.(); } catch (_) {}

    _nav.setActive(_nav.currentSlug());
    window.scrollTo({ top: 0, behavior: "instant" });
  },
};


/* ── init() — called once from layout.php on every page load
   FIX 3: Body is revealed here — AFTER fragments are loaded
   and FlyonUI is initialized. This is the key fix for FOUC.
   Sequence: hide (bootstrap) → load fragments → init FlyonUI
             → reveal body. User never sees unstyled content.
   ──────────────────────────────────────────────────────── */
async function init({ headerUrl, navUrl }) {
  console.group('[INIT]');
  console.log('Start →', { headerUrl, navUrl });

  /* ── 1. Load layout fragments ───────────────────── */
  try {
    await Promise.all([
      _loadFragment(headerUrl, "#layoutHeader"),
      _loadFragment(navUrl, "#layoutNav"),
    ]);
    console.log('✔ Fragments loaded');
  } catch (e) {
    console.error('✖ Fragment load failed:', e);
  }

  /* ── 2. Initialize FlyonUI ───────────────────────── */
  try {
    window.HSStaticMethods?.autoInit?.();
    console.log('✔ FlyonUI initialized');
  } catch (e) {
    console.error('✖ FlyonUI init error:', e);
  }

  /* ── 3. Restore sidebar state ───────────────────── */
  try {
    if (localStorage.getItem("navCollapsed") === "1") {
      document.getElementById("layoutNav")?.classList.add("is-collapsed");
    }
    console.log('✔ Nav state restored');
  } catch (e) {
    console.error('✖ Nav state error:', e);
  }

  /* ── 4. Reveal UI (anti-FOUC) ───────────────────── */
  try {
    document.documentElement.setAttribute("data-admin-i18n-ready", "1");
    console.log('✔ UI revealed');
  } catch (_) {}

  /* ── 5. Init navigation + router ────────────────── */
  try {
    _nav.init();

    // Prevent double router binding
    if (!window.__ROUTER_STARTED__) {
      _router.start();
      window.__ROUTER_STARTED__ = true;
      console.log('✔ Router started');
    } else {
      console.log('ℹ Router already started');
    }

    waitForHeaderUserNameAndHydrate();
    console.log('✔ Nav initialized');
  } catch (e) {
    console.error('✖ Nav/Router error:', e);
  }

  /* ── 6. Run page-specific init (CRITICAL) ───────── */
  try {
    if (typeof window.__pageInit === 'function') {
      await Promise.resolve(window.__pageInit());
      console.log('✔ Page init executed');
    } else {
      console.warn('⚠ No __pageInit found');
    }
  } catch (e) {
    console.error('✖ Page init error:', e);
  }

  console.log('DONE');
  console.groupEnd();
}