/* ============================================================
   default.js — Admin shared utilities & layout bootstrap
   UI Library: PrelineUI (HSStaticMethods.autoInit)
   ============================================================ */


/* ──────────────────────────────────────────────────────────
   § 1. COOKIE HELPERS
   Simple get/set wrappers for persistent state.
   ────────────────────────────────────────────────────────── */

function setCookie(name, value, days) {
  const d = new Date();
  d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`;
}

function getCookie(name) {
  const seg = `; ${document.cookie}`.split(`; ${name}=`);
  return seg.length === 2 ? decodeURIComponent(seg.pop().split(";").shift()) : null;
}


/* ──────────────────────────────────────────────────────────
   § 2. INTERNATIONALISATION (i18n)
   Supports: eng | tl | kor
   Applies translated text/placeholder/alt attributes to
   all [data-lan-*] elements, then re-syncs custom selects.
   ────────────────────────────────────────────────────────── */

/** Return the best text for `el` given the active language. */
function getLangText(el, lang) {
  lang = String(lang || "").toLowerCase();
  if (lang === "tl") return el.getAttribute("data-lan-tl") || el.getAttribute("data-lan-eng");
  return el.getAttribute("data-lan-eng") || el.getAttribute("data-lan-tl");
}

/**
 * Ensure only "eng" or "tl" is stored in the lang cookie.
 * Korean is an authoring language, never shown to end-users.
 */
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

/** Apply translated content to every [data-lan-*] element in the DOM. */
function language_apply(lang) {
  lang = String(lang || "").toLowerCase();
  if (!["eng", "tl", "kor", "ko"].includes(lang)) lang = "eng";
  if (lang === "ko") lang = "kor";

  // Text content / value / alt
  document.querySelectorAll("[data-lan-eng],[data-lan-tl],[data-lan-kor],[data-lan-ko]").forEach((el) => {
    const txt = getLangText(el, lang);
    if (!txt) return;
    const tag = el.tagName;
    if (tag === "INPUT" || tag === "TEXTAREA") {
      el.hasAttribute("placeholder") ? el.setAttribute("placeholder", txt) : (el.value = txt);
    } else if (tag === "IMG") {
      el.setAttribute("alt", txt);
    } else {
      el.textContent = txt;
    }
  });

  // Placeholder overrides
  document.querySelectorAll("[data-lan-eng-placeholder]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-placeholder");
    const tl  = el.getAttribute("data-lan-tl-placeholder");
    const kor = el.getAttribute("data-lan-kor-placeholder") || el.getAttribute("data-lan-ko-placeholder");
    if (lang === "eng" && eng) el.setAttribute("placeholder", eng);
    else if (lang === "tl")   el.setAttribute("placeholder", tl || eng || el.getAttribute("placeholder") || "");
    else if ((lang === "kor" || lang === "ko") && kor) el.setAttribute("placeholder", kor);
  });

  // Alt overrides
  document.querySelectorAll("[data-lan-eng-alt]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-alt");
    const tl  = el.getAttribute("data-lan-tl-alt");
    const kor = el.getAttribute("data-lan-kor-alt") || el.getAttribute("data-lan-ko-alt");
    if (lang === "eng" && eng) el.setAttribute("alt", eng);
    else if (lang === "tl")   el.setAttribute("alt", tl || eng || el.getAttribute("alt") || "");
    else if ((lang === "kor" || lang === "ko") && kor) el.setAttribute("alt", kor);
  });

  // <select> <option> text
  document.querySelectorAll("select option[data-lan-eng]").forEach((option) => {
    const eng = option.getAttribute("data-lan-eng");
    const tl  = option.getAttribute("data-lan-tl");
    const kor = option.getAttribute("data-lan-kor") || option.getAttribute("data-lan-ko");
    if (lang === "eng" && eng)                        option.textContent = eng;
    else if (lang === "tl")                           option.textContent = tl || eng || option.textContent;
    else if ((lang === "kor" || lang === "ko") && kor) option.textContent = kor;
    else                                              option.textContent = kor || eng || option.textContent;
  });

  // Sync custom selects after option text changes
  try { if (typeof window.refreshAllJwSelect === "function") window.refreshAllJwSelect(); } catch (_) {}

  // Language-toggle button label (cycles eng → kor → tl)
  document.querySelectorAll(".lang-text").forEach((node) => {
    if (lang === "eng")      node.textContent = "한국어";
    else if (lang === "kor") node.textContent = "Tagalog";
    else                     node.textContent = "English";
  });

  // <html lang> attribute
  const htmlLang = document.getElementById("html-lang");
  if (htmlLang) {
    if (lang === "tl")      htmlLang.setAttribute("lang", "tl");
    else if (lang === "kor") htmlLang.setAttribute("lang", "ko");
    else                    htmlLang.setAttribute("lang", "en");
  }

  window.dispatchEvent(new CustomEvent("languageChanged", { detail: { lang } }));
}

/**
 * Cycle active language: eng → kor → tl → eng.
 * Persists to cookie and applies immediately.
 */
function language_set() {
  const cur  = getCookie("lang") || "eng";
  const next = cur === "eng" ? "kor" : cur === "kor" ? "tl" : "eng";
  setCookie("lang", next, 365);
  try { language_apply(next); } catch (_) {}
  try { setTimeout(() => { try { language_apply(next); } catch (_) {} }, 0); } catch (_) {}
}


/* ──────────────────────────────────────────────────────────
   § 3. HANGUL SCRUBBER
   Strips any residual Korean characters from the rendered UI.
   Runs once on boot and watches for future DOM mutations.
   Only touches elements inside [data-lan-*] / [data-i18n-*]
   to avoid false positives in content areas.
   ────────────────────────────────────────────────────────── */

function __admin_scrub_hangul(root = document.body) {
  try {
    if (!root) return;
    const hasHangul  = (s) => /[가-힣]/.test(String(s || ""));
    const stripHangul = (s) => String(s || "").replace(/[가-힣]+/g, "").replace(/\s{2,}/g, " ").trim();
    const ensureEnglish = (orig, cleaned) => (!hasHangul(orig) ? cleaned : (cleaned?.length ? cleaned : orig));

    const isI18nEl = (el) => {
      try {
        return !!el?.closest?.("[data-lan-eng],[data-lan-tl],[data-lan-ko],[data-lan-kor],[data-i18n],[data-i18n-placeholder],[data-i18n-title],[data-i18n-alt]");
      } catch (_) { return false; }
    };

    // Text nodes
    try {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const nodes  = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      for (const n of nodes) {
        if (!n?.nodeValue) continue;
        try { const p = n.parentElement; if (p && ["SCRIPT","STYLE","NOSCRIPT"].includes(p.tagName)) continue; } catch (_) {}
        try { if (n.parentElement?.closest?.('[data-skip-hangul-scrub="1"]')) continue; } catch (_) {}
        try { if (!isI18nEl(n.parentElement)) continue; } catch (_) { continue; }
        if (!hasHangul(n.nodeValue)) continue;
        n.nodeValue = ensureEnglish(n.nodeValue, stripHangul(n.nodeValue));
      }
    } catch (_) {}

    // Attribute values (placeholder, title, aria-label, alt)
    try {
      root.querySelectorAll("*").forEach((el) => {
        if (!el?.getAttribute) return;
        try { if (el.closest?.('[data-skip-hangul-scrub="1"]')) return; } catch (_) {}
        if (!isI18nEl(el)) return;
        for (const attr of ["placeholder", "title", "aria-label", "alt"]) {
          const v = el.getAttribute(attr);
          if (!v || !hasHangul(v)) continue;
          if (attr === "alt")         el.setAttribute(attr, "Image");
          else if (attr === "placeholder") el.setAttribute(attr, "Please enter a value.");
          else                        el.setAttribute(attr, ensureEnglish(v, stripHangul(v)));
        }
      });
    } catch (_) {}

    // MutationObserver — watch for newly injected Korean
    try {
      if (!window.__admin_hangul_observer && root?.ownerDocument) {
        let busy = false;
        const obs = new MutationObserver((mutations) => {
          if (busy) return;
          busy = true;
          try {
            for (const m of mutations) {
              if (m.type === "childList") {
                m.addedNodes.forEach((node) => {
                  try {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                      if (["SCRIPT","STYLE","NOSCRIPT"].includes(node.tagName)) return;
                      __admin_scrub_hangul(node);
                    } else if (node.nodeType === Node.TEXT_NODE && hasHangul(node.nodeValue)) {
                      try { const p = node.parentElement; if (p && ["SCRIPT","STYLE","NOSCRIPT"].includes(p.tagName)) return; } catch (_) {}
                      node.nodeValue = ensureEnglish(node.nodeValue, stripHangul(node.nodeValue));
                    }
                  } catch (_) {}
                });
              } else if (m.type === "attributes" && m.target?.nodeType === Node.ELEMENT_NODE) {
                try { __admin_scrub_hangul(m.target); } catch (_) {}
              }
            }
          } finally { busy = false; }
        });
        obs.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ["placeholder","title","aria-label","alt"] });
        window.__admin_hangul_observer = obs;
      }
    } catch (_) {}
  } catch (_) {}
}


/* ──────────────────────────────────────────────────────────
   § 4. POPUP / ALERT TRANSLATION PATCH
   Intercepts window.alert / window.confirm and replaces any
   Korean strings with English equivalents before display.
   ────────────────────────────────────────────────────────── */

(function patchPopupToEnglish() {
  if (window.__jwPopupEnglishPatched) return;
  window.__jwPopupEnglishPatched = true;

  const hasKorean = (s) => /[가-힣]/.test(String(s || ""));

  const translateAlert = (msg) => {
    let s = String(msg ?? "").replace(/\s+/g, " ").trim();
    if (!hasKorean(s)) return s;
    const rules = [
      [/^관리자 로그인이 필요합니다\.?$/,         "Admin login required."],
      [/^로그인이 필요합니다\.?$/,                "Login required."],
      [/^저장되었습니다\.?$/,                    "Saved."],
      [/^임시저장되었습니다\.?$/,                "Temporarily saved."],
      [/^삭제되었습니다\.?$/,                    "Deleted."],
      [/^삭제할 .* 없습니다\.?$/,               "Nothing to delete."],
      [/^상품명이 필요합니다\.?$/,               "Product name is required."],
      [/^카테고리를 선택해주세요\.?$/,            "Please select a category."],
      [/^필수 필드가 누락되었습니다/,             "Required fields are missing."],
      [/^필수 항목을 모두 입력해주세요/,           "Please fill in all required fields."],
      [/^시작일과 종료일을 모두 선택해주세요\.?$/, "Please select both start and end dates."],
      [/다운로드 중 오류가 발생했습니다\.?/,      "An error occurred while downloading."],
      [/불러오는 중 오류가 발생했습니다\.?/,      "An error occurred while loading."],
      [/저장 중 오류가 발생했습니다\.?/,         "An error occurred while saving."],
      [/삭제 중 오류가 발생했습니다\.?/,         "An error occurred while deleting."],
      [/업로드 중 오류가 발생했습니다\.?/,        "An error occurred while uploading."],
      [/등록 중 오류가 발생했습니다\.?/,         "An error occurred while creating."],
      [/수정 중 오류가 발생했습니다/,             "An error occurred while updating."],
      [/^올바른 이메일 형식을 입력해주세요\.?$/,  "Please enter a valid email address."],
      [/^아이디를 입력해주세요\.?$/,             "Please enter your ID."],
      [/^비밀번호를 입력해주세요/,               "Please enter a password."],
      [/^계정 정보를 찾을 수 없습니다\.?$/,      "Account information not found."],
      [/^객실을 선택해주세요\.?$/,               "Please select rooms."],
      [/^객실 수용 인원이 부족합니다\.?$/,        "Insufficient room capacity."],
      [/^비밀번호가 변경되었습니다\.?$/,          "Password has been changed."],
    ];
    for (const [re, out] of rules) if (re.test(s)) return out;
    return "Please check the information.";
  };

  const translateConfirm = (msg) => {
    let s = String(msg ?? "").replace(/\s+/g, " ").trim();
    if (!hasKorean(s)) return s;
    const rules = [
      [/삭제하시겠습니까\??/,   "Are you sure you want to delete?"],
      [/저장하시겠습니까\??/,   "Do you want to save?"],
      [/로그아웃하시겠습니까\??/, "Do you want to log out?"],
    ];
    for (const [re, out] of rules) if (re.test(s)) return out;
    return "Are you sure?";
  };

  const __alert   = window.alert?.bind(window)   ?? ((m) => void m);
  const __confirm = window.confirm?.bind(window) ?? (() => true);
  window.alert   = (message) => __alert(translateAlert(message));
  window.confirm = (message) => __confirm(translateConfirm(message));
})();


/* ──────────────────────────────────────────────────────────
   § 5. CUSTOM SELECT  (jw-select)
   Replaces native <select class="select"> elements with an
   accessible custom dropdown. Supports icons, disabled state,
   and up-direction via data-direction="up".
   ────────────────────────────────────────────────────────── */

function jw_select() {
  /** Create a DOM element with an optional class. */
  const el = (tag, cls) => { const n = document.createElement(tag); if (cls) n.className = cls; return n; };

  /** Measure text width off-screen to auto-size the dropdown. */
  const offWidth = (s) => {
    const t = el("div");
    t.style.cssText = "position:absolute;visibility:hidden;white-space:nowrap;display:inline-block;";
    t.textContent   = s;
    document.body.appendChild(t);
    const w = t.offsetWidth;
    t.remove();
    return w;
  };

  // Single document-level click listener closes all open dropdowns
  if (!window.__jwselect_docbound) {
    document.addEventListener("click", () => {
      document.querySelectorAll(".jw-select.open").forEach((wrap) => {
        wrap.classList.remove("open");
        const box = wrap.querySelector(".jw-selected");
        if (box) { box.classList.remove("active"); box.setAttribute("aria-expanded", "false"); }
      });
    });
    window.__jwselect_docbound = true;
  }

  document.querySelectorAll(".select").forEach((nativeSel) => {
    nativeSel.classList.replace("select", "jw-sr-only");

    const wrap = el("div", "jw-select");
    nativeSel.parentNode.insertBefore(wrap, nativeSel);
    wrap.appendChild(nativeSel);

    // Visible button showing the selected option
    const box = el("div", "jw-selected");
    box.setAttribute("role", "button");
    box.setAttribute("aria-haspopup", "listbox");
    box.setAttribute("aria-expanded", "false");
    wrap.appendChild(box);

    const setBox = () => {
      const opt  = nativeSel.selectedOptions[0] || nativeSel.options[0];
      const icon = opt?.dataset?.icon;
      box.innerHTML = icon ? `<i class="${icon}"></i> ${opt.textContent}` : (opt?.textContent ?? "");
    };
    setBox();

    // Dropdown list
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

    const openList  = () => { if (nativeSel.disabled) return; wrap.classList.add("open"); box.classList.add("active"); box.setAttribute("aria-expanded", "true"); };
    const closeList = () => { wrap.classList.remove("open"); box.classList.remove("active"); box.setAttribute("aria-expanded", "false"); };
    const choose    = (itemEl) => {
      if (itemEl.getAttribute("aria-disabled") === "true") return;
      list.querySelectorAll('.jw-select-item[aria-selected="true"]').forEach((li) => li.removeAttribute("aria-selected"));
      itemEl.setAttribute("aria-selected", "true");
      nativeSel.value = itemEl.dataset.value;
      nativeSel.dispatchEvent(new Event("change", { bubbles: true }));
      setBox();
      closeList();
    };

    box.addEventListener("click",  (e) => { e.stopPropagation(); wrap.classList.contains("open") ? closeList() : openList(); });
    list.addEventListener("click", (e) => { const item = e.target.closest(".jw-select-item"); if (!item) return; e.stopPropagation(); choose(item); });
  });
}

/** Re-render all jw-select dropdowns in place (called after language switch). */
window.refreshAllJwSelect = function () {
  try {
    document.querySelectorAll(".jw-select").forEach((wrap) => {
      const nativeSel = wrap.querySelector("select");
      const box       = wrap.querySelector(".jw-selected");
      const list      = wrap.querySelector(".jw-select-list");
      if (!nativeSel || !box || !list) return;

      // Sync disabled state
      if (nativeSel.disabled) { wrap.classList.add("is-disabled"); box.setAttribute("aria-disabled", "true"); box.tabIndex = -1; }
      else                    { wrap.classList.remove("is-disabled"); box.removeAttribute("aria-disabled"); box.tabIndex = 0; }

      // Sync selected label
      const opt  = nativeSel.selectedOptions?.[0] || nativeSel.options?.[0] || null;
      const icon = opt?.dataset?.icon || null;
      box.innerHTML = icon ? `<i class="${icon}"></i> ${opt.textContent || ""}` : (opt?.textContent || "");

      // Re-render option list
      list.innerHTML = "";
      Array.from(nativeSel.options || []).forEach((o) => {
        const li = document.createElement("li");
        li.className = "jw-select-item";
        li.setAttribute("role", "option");
        li.dataset.value = o.value;
        li.innerHTML = o.dataset?.icon ? `<i class="${o.dataset.icon}"></i> ${o.textContent}` : (o.textContent || "");
        if (o.disabled) { li.setAttribute("aria-disabled", "true"); li.classList.add("is-disabled"); }
        if (o.selected)  li.setAttribute("aria-selected", "true");
        list.appendChild(li);
      });
    });
  } catch (_) {}
};


/* ──────────────────────────────────────────────────────────
   § 6. MODAL
   Fetches remote HTML into a <dialog> element.
   Supports dynamic width, POST data, script re-execution,
   and close-on-backdrop-click / ESC behaviour.
   ────────────────────────────────────────────────────────── */

class Modal {
  constructor(action, width, sq) {
    this.action = action;
    this.width  = width;
    this.sq     = sq;
    this.el     = null;
    this._abort = null;
  }

  /** Convert various data formats to a URL-encoded query string. */
  static _toQuery(data) {
    if (!data) return "";
    if (typeof data === "string")  return data.replace(/^\?/, "");
    if (data instanceof FormData)  return new URLSearchParams([...data.entries()]).toString();
    return new URLSearchParams(Object.entries(data)).toString();
  }

  /** Re-execute <script> tags inside dynamically injected HTML. */
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

  /** Fetch remote HTML via GET or POST (15 s timeout). */
  async _loadHTML(url, data) {
    const u = new URL(url, location.href);
    u.searchParams.set("_", Date.now());
    this._abort?.abort();
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

  /** Open the dialog, show a loading indicator, then inject the fetched content. */
  async open() {
    const d = document.createElement("dialog");
    if (this.width) {
      const w = typeof this.width === "number" ? `${this.width}px` : this.width;
      d.style.setProperty("--modal-width", w);
    }
    d.classList.add("jw-dialog");
    d.innerHTML = `<div class="dialog-loading"><span class="dialog-loading-dot"></span><span class="dialog-loading-dot"></span><span class="dialog-loading-dot"></span></div>`;
    document.body.appendChild(d);
    this.el = d;

    typeof d.showModal === "function" ? d.showModal() : d.setAttribute("open", "");
    d.addEventListener("click",  (e) => { if (e.target === d) this.close(); });
    d.addEventListener("cancel", (e) => { e.preventDefault(); this.close(); });

    if (this.action) {
      try {
        const html = await this._loadHTML(this.action, this.sq);
        d.innerHTML = html;
        d.classList.add("dialog--ready");
        Modal._execScripts(d);

        // Re-apply i18n and custom selects inside the modal
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

  /** Reload modal content from a (optionally different) URL. */
  async update(action, sq) {
    if (!this.el) return;
    const url  = action || this.action;
    const data = sq !== undefined ? sq : this.sq;
    if (!url) return;
    try {
      const html = await this._loadHTML(url, data);
      this.el.innerHTML = html;
      Modal._execScripts(this.el);
      this.el.querySelector("#closeDialog")?.addEventListener("click", () => this.close(), { once: true });
      document.dispatchEvent(new CustomEvent("modal:loaded", { detail: { dialog: this.el, action: url, data } }));
    } catch (err) { this.el.innerHTML = `<div class="dialog-error">Failed to load content.</div>`; }
  }

  /** Animate out and remove the dialog from the DOM. */
  close() {
    if (!this.el) return;
    this._abort?.abort();
    this.el.classList.add("dialog--closing");
    this.el.addEventListener("animationend", () => { this.el?.close?.(); this.el?.remove(); this.el = null; }, { once: true });
  }
}

/** Shorthand: open a modal and return its instance. */
function modal(page, w, sq) { const m = new Modal(page, w, sq); m.open(); return m; }

/** Close the topmost open jw-dialog. */
function modal_close() {
  const dialogs = document.querySelectorAll("dialog.jw-dialog");
  if (!dialogs.length) return;
  const last = dialogs[dialogs.length - 1];
  last.classList.add("dialog--closing");
  last.addEventListener("animationend", () => { last.close?.(); last.remove(); }, { once: true });
}


/* ──────────────────────────────────────────────────────────
   § 7. AUTH — SESSION, LOGOUT, CHANGE PASSWORD
   ────────────────────────────────────────────────────────── */

/** Wire up logout and change-password buttons inside `container`. */
function initLogoutButton(container) {
  const logoutBtn = container?.querySelector("#logoutBtn") ?? document.getElementById("logoutBtn");
  if (logoutBtn && !logoutBtn.hasAttribute("data-logout-initialized")) {
    logoutBtn.setAttribute("data-logout-initialized", "true");
    logoutBtn.addEventListener("click", handleLogout);
  }

  const changePwBtn = container?.querySelector("#changePasswordBtn") ?? document.getElementById("changePasswordBtn");
  if (changePwBtn && !changePwBtn.hasAttribute("data-change-password-initialized")) {
    changePwBtn.setAttribute("data-change-password-initialized", "true");
    changePwBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      modal("/admin/member/change-password.html", "580px", "520px");
    });
  }
}

// Delegated click for change-password — handles dynamic header timing
(function bindChangePasswordDelegated() {
  if (window.__ST_ADMIN_CHANGE_PW_DELEGATED__) return;
  window.__ST_ADMIN_CHANGE_PW_DELEGATED__ = true;
  document.addEventListener("click", (e) => {
    const t = e.target?.closest?.("#changePasswordBtn");
    if (!t) return;
    e.preventDefault();
    e.stopPropagation();
    try { modal("/admin/member/change-password.html", "580px", "520px"); }
    catch (err) { console.error("Failed to open change-password modal:", err); alert("Failed to open Change Password."); }
  }, true);
})();

/**
 * Fetch session info and update the header user name / role label.
 * Safe to call multiple times; silently ignores errors.
 */
async function hydrateAdminIdentityUI(root) {
  try {
    const res  = await fetch("../backend/api/check-session.php", { credentials: "same-origin", cache: "no-store" });
    const data = await res.json().catch(() => ({}));
    if (!data?.authenticated) return;

    // Header name
    const headerName = document.querySelector(".layout-header .user-name");
    if (headerName) {
      const labels = { admin: "Admin", cs: "CS" };
      headerName.textContent = labels[data.userType] ?? data.displayName ?? "Admin";
    }

    // Dropdown name / role
    const scope  = root || document;
    const nameEl = scope.querySelector(".header_memberinfo .name");
    const roleEl = scope.querySelector(".header_memberinfo .role");
    if (nameEl) nameEl.textContent = data.displayName || "ADMIN";
    if (roleEl) roleEl.textContent = data.roleLabel   || "Employee";
  } catch (_) {}
}

let isLoggingOut = false;

/** Confirm, POST to logout endpoint, then redirect to login page. */
async function handleLogout(e) {
  e?.preventDefault();
  e?.stopPropagation();
  if (isLoggingOut) return;
  if (!confirm("Do you want to log out?")) return;
  isLoggingOut = true;
  try {
    const res  = await fetch("../../public/backend/api/logout.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" } });
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (data.success) { try { localStorage.removeItem("accountType"); } catch (_) {} window.location.href = "../index.php"; }
    else              { isLoggingOut = false; alert(data.message || "Logout failed."); }
  } catch (err) {
    console.error("Logout error:", err);
    isLoggingOut = false;
    try { localStorage.removeItem("accountType"); } catch (_) {}
    window.location.href = "../index.php";
  }
}


/* ──────────────────────────────────────────────────────────
   § 8. MEMBER INFO DROPDOWN
   Lazy-loads a remote fragment into the .position element of
   the closest .membermenu ancestor, with outside-click close.
   ────────────────────────────────────────────────────────── */

function member_info(btn, page) {
  const root = btn.closest(".membermenu") || btn;
  const box  = root.querySelector(".position");
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
    const onDocDown    = (e) => { if (!root.contains(e.target)) closeBox(); };
    const onInsideClick = (e) => { if (e.target.closest(".closeMember_info")) { e.preventDefault(); closeBox(); } };
    removeListeners();
    document.addEventListener("pointerdown", onDocDown, true);
    box.addEventListener("click", onInsideClick);
    box._off = () => { document.removeEventListener("pointerdown", onDocDown, true); box.removeEventListener("click", onInsideClick); };
  }
  function closeBox()       { box.classList.remove("on"); removeListeners(); }
  function removeListeners() { box._off?.(); box._off = null; }
}


/* ──────────────────────────────────────────────────────────
   § 9. GENERAL UI HELPERS
   Small utility functions used across various admin pages.
   ────────────────────────────────────────────────────────── */

/** Open/close a FAB sub-menu, managing aria-hidden and inert. */
function setFabMenuState(menu, open) {
  if (!menu) return;
  menu.classList.toggle("on", open);
  menu.setAttribute("aria-hidden", String(!open));
  if ("inert" in menu) menu.inert = !open;
}

/** Toggle a .fab-menu inside the closest .layerToggleWrap. */
function layerToggle(btn) {
  const menu     = btn.closest(".layerToggleWrap")?.querySelector(":scope > .fab-menu");
  if (!menu) return;
  const willOpen = !menu.classList.contains("on");
  setFabMenuState(menu, willOpen);
  if (willOpen) { const f = menu.querySelector('button, [tabindex]:not([tabindex="-1"])'); f?.focus(); }
  else          { btn.focus(); }
}

/** Update help text content by selector. */
function helpTextChange(target, v) { const el = document.querySelector(target); if (!el) return; el.textContent = v || ""; }

/** Preview a selected file as a background image or <img> inside the closest upload wrapper. */
function filePreview(el) {
  const f = el.files?.[0];
  if (!f) return;
  const box = el.closest(".image-preview")
    || el.closest(".banner-item")?.querySelector(".image-preview")
    || el.closest(".banner-image-wrap")
    || el.closest(".upload-item")
    || null;
  if (!box) return;
  const url = URL.createObjectURL(f);

  if (box.classList.contains("upload-item")) {
    box.classList.add("is-filled", "has-image");
    box.querySelector("label.inputFile")?.style.setProperty("display", "none");
    let img = box.querySelector("img.preview");
    if (!img) { img = document.createElement("img"); img.className = "preview"; img.alt = ""; box.insertAdjacentElement("afterbegin", img); }
    img.onload = () => { URL.revokeObjectURL(url); img.onload = null; };
    img.src = url;
    if (!box.querySelector(".btn-close")) {
      const btn = document.createElement("button");
      btn.type = "button"; btn.className = "jw-button btn-close"; btn.setAttribute("aria-label", "remove");
      btn.innerHTML = '<div class="ic-close" style="width:15px;height:15px;--line:1px;--color:#969696"></div>';
      box.appendChild(btn);
    }
    box.querySelector(".btn-close")?.style.setProperty("z-index", "5");
    setTimeout(() => URL.revokeObjectURL(url), 60000);
    return;
  }

  box.style.backgroundImage    = `url("${url}")`;
  box.style.backgroundSize     = "cover";
  box.style.backgroundPosition = "center";
  box.classList.add("has-image");
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

/**
 * Check whether all [data-essential="y"] fields inside the
 * nearest [data-essentialWrap="y"] are filled in, then toggle
 * [data-essentialTarget="y"] buttons accordingly.
 */
function essentialCheck(it) {
  const wrap = it.closest('[data-essentialWrap="y"]');
  if (!wrap) return false;
  const essentials = wrap.querySelectorAll('[data-essential="y"]');
  if (!essentials.length) return false;
  const allOK = [...essentials].every((el) => {
    const type = (el.type || "").toLowerCase();
    if (type === "checkbox" || type === "radio") return el.checked;
    if (el.tagName === "SELECT") return el.value !== "";
    if (type === "file") return el.files?.length > 0;
    return el.value.trim().length > 0;
  });
  wrap.querySelectorAll('[data-essentialTarget="y"]').forEach((tg) => {
    tg.disabled = !allOK;
    tg.classList.toggle("active",   allOK);
    tg.classList.toggle("inactive", !allOK);
  });
  return allOK;
}

/** Toggle a section between collapsed (+) and expanded (−). */
function togglePlusMinus(btn) {
  const hasOn  = btn.classList.contains("on");
  const img    = btn.querySelector("img");
  const target = btn.getAttribute("data-target-section");
  const targets = target ? document.querySelectorAll(`[data-section-name="${target}"]`) : [];
  if (hasOn) {
    btn.classList.remove("on");
    targets.forEach((el) => { if (el !== btn) el.classList.remove("hidden"); });
    img.src = "../image/minus.svg"; img.alt = "minus";
  } else {
    btn.classList.add("on");
    targets.forEach((el) => { if (el !== btn) el.classList.add("hidden"); });
    img.src = "../image/plus.svg"; img.alt = "plus";
  }
}

/** Smooth-scroll to a named section and mark it active in the aside. */
function scrollToSection(sectionId) {
  const target = document.getElementById(sectionId)
    || document.querySelector(`[data-section-name="${sectionId}"]`)
    || document.querySelector(`h2.section-title[data-lan-eng="${sectionId}"]`);
  if (!target) return;
  if (target.classList.contains("hidden")) {
    const btn = document.querySelector(`button.aside-add-section[data-target-section="${sectionId}"]`);
    if (btn?.classList.contains("on")) togglePlusMinus(btn);
  }
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState(sectionId);
}

/** Mark the matching aside row as active. */
function updateAsideActiveState(sectionId) {
  document.querySelectorAll(".aside-row").forEach((row) => {
    row.classList.remove("is-active");
    const t = row.querySelector(".aside-row__title, .aside-row__subtitle");
    if (t?.getAttribute("data-scroll-target") === sectionId) row.classList.add("is-active");
  });
}

/** Smooth-scroll to a day panel in multi-day itinerary views. */
function scrollToDayPanel(dayNum) {
  const target = (dayNum === 1 ? document.getElementById("nday") : null)
    || document.querySelector(`.nday-panel[data-day="${dayNum}"]`);
  if (!target) return;
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState("day-" + dayNum);
}

/**
 * Remove the clicked row from a table body.
 * Shows a modal warning if it is the last remaining row.
 */
function trash_typeA(it) {
  const tbody = it.closest("tbody");
  if (!tbody) return;
  if (tbody.querySelectorAll("tr").length === 1) { modal("/admin/super/template-detail-modal2.html", "580px", "252px"); return; }
  it.closest("tr")?.remove();
  // Re-number first-column index cells
  [...tbody.querySelectorAll("tr")].forEach((row, idx) => {
    const first = row.querySelector("td");
    if (first && Number.isFinite(parseInt(first.textContent, 10))) first.textContent = String(idx + 1);
  });
}

/** Simple redirect helper. */
function temp_link(v) { location.href = v; }


/* ──────────────────────────────────────────────────────────
   § 10. SIDEBAR
   Collapse / expand or slide in / out on mobile.
   State is persisted to localStorage.
   ────────────────────────────────────────────────────────── */

function toggleLayoutNav() {
  const nav = document.getElementById("layoutNav");
  const btn = document.querySelector(".nav-toggle-btn");
  if (!nav) return;
  if (window.innerWidth <= 768) {
    const isOpen = nav.classList.toggle("is-open");
    btn?.setAttribute("aria-expanded", String(isOpen));
  } else {
    const isCollapsed = nav.classList.toggle("is-collapsed");
    try { localStorage.setItem("navCollapsed", isCollapsed ? "1" : "0"); } catch (_) {}
  }
}


/* ──────────────────────────────────────────────────────────
   § 11. NAV — ACCORDION + ACTIVE STATE
   ────────────────────────────────────────────────────────── */

const _nav = {
  init() {
    document.querySelectorAll("#layoutNav .nav-btn").forEach((btn) => {
      if (btn.tagName === "BUTTON") btn.addEventListener("click", () => _nav.toggle(btn));
    });
    _nav.setActive(_nav.currentSlug());
  },

  /** Toggle a nav accordion item open/closed, closing siblings. */
  toggle(btn) {
    const item   = btn.closest(".nav-item");
    if (!item) return;
    const isOpen = item.classList.contains("is-open");
    document.querySelectorAll("#layoutNav .nav-item.is-open").forEach((el) => {
      if (el === item) return;
      el.classList.remove("is-open");
      const wrap = el.querySelector(":scope > .nav-sub-wrap");
      if (wrap) wrap.style.gridTemplateRows = "0fr";
    });
    item.classList.toggle("is-open", !isOpen);
    btn.setAttribute("aria-expanded", String(!isOpen));
    const wrap = item.querySelector(":scope > .nav-sub-wrap");
    if (wrap) wrap.style.gridTemplateRows = !isOpen ? "1fr" : "0fr";
  },

  /** Derive the current page slug from data-page-slug or the URL. */
  currentSlug() {
    const content = document.getElementById("layoutContent");
    if (content?.dataset.pageSlug) return content.dataset.pageSlug;
    return location.pathname.split("/").pop().replace(/\.(php|html)$/, "");
  },

  /** Highlight the nav link(s) matching `slug` and expand its parent. */
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
      const wrap = parentItem.querySelector(":scope > .nav-sub-wrap");
      if (wrap) wrap.style.gridTemplateRows = "1fr";
    }
  },
};


/* ──────────────────────────────────────────────────────────
   § 12. FRAGMENT LOADER & SCRIPT EXECUTOR
   Used by init() to inject header/nav HTML without a full
   page reload, and re-execute any <script> tags inside.
   ────────────────────────────────────────────────────────── */

async function _loadFragment(url, selector) {
  if (!url) return;
  try {
    const res  = await fetch(url, { credentials: "same-origin" });
    const html = await res.text();
    const el   = document.querySelector(selector);
    if (el) { el.innerHTML = html; _execScripts(el); }
  } catch (err) { console.error(`[init] fragment load failed: ${url}`, err); }
}

/** Replace <script> tags with fresh clones so the browser re-executes them. */
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


/* ──────────────────────────────────────────────────────────
   § 13. SPA ROUTER
   Intercepts same-origin link clicks, fetches the target
   page, swaps #layoutContent, and updates the browser
   history — without a full page reload.
   ────────────────────────────────────────────────────────── */

const _router = {
  _busy: false,

  start() {
    document.addEventListener("click", (e) => {
      const a = e.target.closest("a[href]");
      if (!a || !_router._isInternal(a) || a.target === "_blank" || a.hasAttribute("data-no-router")) return;
      e.preventDefault();
      _router.navigate(a.href);
    });
    window.addEventListener("popstate", () => _router.navigate(location.href, { pushState: false }));
  },

  _isInternal(a) { try { return new URL(a.href, location.href).origin === location.origin; } catch { return false; } },

  async navigate(href, { pushState = true } = {}) {
    if (_router._busy) return;
    _router._busy = true;
    const content = document.getElementById("layoutContent");
    content?.classList.add("is-loading");
    try {
      const html = await _router._fetch(href);
      if (!html) return;
      const doSwap = () => _router._swap(html, href, pushState);
      if (document.startViewTransition) { await document.startViewTransition(doSwap).finished; }
      else {
        content.style.transition = "opacity 130ms ease";
        content.style.opacity    = "0";
        await doSwap();
        requestAnimationFrame(() => { content.style.opacity = "1"; });
      }
    } finally { content?.classList.remove("is-loading"); _router._busy = false; }
  },

  async _fetch(href) {
    try {
      const res = await fetch(href, { credentials: "same-origin" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.text();
    } catch (err) { console.error("[router] fetch failed:", err); location.href = href; return null; }
  },

  async _swap(html, href, pushState) {
    const doc     = new DOMParser().parseFromString(html, "text/html");
    const newSect = doc.getElementById("layoutContent");
    if (!newSect) return;

    const content = document.getElementById("layoutContent");
    content.innerHTML = newSect.innerHTML;
    if (newSect.dataset.pageSlug) content.dataset.pageSlug = newSect.dataset.pageSlug;

    const newTitle = doc.querySelector("title");
    if (newTitle) document.title = newTitle.textContent;
    if (pushState) history.pushState({}, "", href);

    _execScripts(content);
    if (typeof window.__pageInit === "function") await Promise.resolve(window.__pageInit());

    // Re-apply i18n and custom selects after swap
    const lang = getCookie("lang") || "eng";
    try { language_apply(lang); }  catch (_) {}
    try { jw_select?.(); }         catch (_) {}

    // Re-init PrelineUI components in the new content
    try { window.HSStaticMethods?.autoInit?.(); } catch (_) {}

    _nav.setActive(_nav.currentSlug());
    window.scrollTo({ top: 0, behavior: "instant" });
  },
};


/* ──────────────────────────────────────────────────────────
   § 14. i18n BOOTSTRAP  (runs immediately, before DOMContentLoaded)
   Hides the page body to prevent unstyled / Korean flash,
   then applies the correct language. The body is only
   revealed inside init() once all fragments are ready.
   ────────────────────────────────────────────────────────── */

(function __admin_i18n_bootstrap() {
  try {
    const root = document.documentElement;
    root.setAttribute("data-admin-i18n-ready", "0");  // body hidden via CSS until "1"

    const lang = __admin_force_lang_cookie();
    try { root.setAttribute("data-lang", lang === "tl" ? "tl" : "en"); root.lang = lang === "tl" ? "tl" : "en"; } catch (_) {}
    try { language_apply(lang); }           catch (_) {}
    try { __admin_scrub_hangul(document.body); } catch (_) {}
  } catch (_) {
    // Hard fallback — never leave a permanently blank page
    try { document.documentElement.setAttribute("data-admin-i18n-ready", "1"); } catch (_) {}
  }
})();


/* ──────────────────────────────────────────────────────────
   § 15. INIT  (called once from layout.php on every page load)
   Sequence:
     1. Load header + nav fragments
     2. Init PrelineUI components
     3. Restore sidebar collapsed state
     4. Reveal body (anti-FOUC)
     5. Start nav accordion + SPA router
     6. Run page-specific __pageInit hook
   ────────────────────────────────────────────────────────── */

/** Poll until .layout-header .user-name exists, then hydrate it. */
function waitForHeaderUserNameAndHydrate(timeoutMs = 3000) {
  const start = Date.now();
  (function loop() {
    if (document.querySelector(".layout-header .user-name")) return hydrateAdminIdentityUI(document);
    if (Date.now() - start > timeoutMs) return;
    requestAnimationFrame(loop);
  })();
}

async function init({ headerUrl, navUrl }) {
  console.group("[INIT]");
  console.log("Start →", { headerUrl, navUrl });

  // 1. Load layout fragments
  try {
    await Promise.all([
      _loadFragment(headerUrl, "#layoutHeader"),
      _loadFragment(navUrl,    "#layoutNav"),
    ]);
    console.log("✔ Fragments loaded");
  } catch (e) { console.error("✖ Fragment load failed:", e); }

  // 2. Init PrelineUI
  try {
    window.HSStaticMethods?.autoInit?.();
    console.log("✔ PrelineUI initialized");
  } catch (e) { console.error("✖ PrelineUI init error:", e); }

  // 3. Restore sidebar state
  try {
    if (localStorage.getItem("navCollapsed") === "1") {
      document.getElementById("layoutNav")?.classList.add("is-collapsed");
    }
    console.log("✔ Nav state restored");
  } catch (e) { console.error("✖ Nav state error:", e); }

  // 4. Reveal body (anti-FOUC gate opened here)
  try {
    document.documentElement.setAttribute("data-admin-i18n-ready", "1");
    console.log("✔ UI revealed");
  } catch (_) {}

  // 5. Nav accordion + SPA router (guard against double-bind)
  try {
    _nav.init();
    if (!window.__ROUTER_STARTED__) {
      _router.start();
      window.__ROUTER_STARTED__ = true;
      console.log("✔ Router started");
    } else {
      console.log("ℹ Router already started");
    }
    waitForHeaderUserNameAndHydrate();
    console.log("✔ Nav initialized");
  } catch (e) { console.error("✖ Nav/Router error:", e); }

  // 6. Page-specific hook
  try {
    if (typeof window.__pageInit === "function") {
      await Promise.resolve(window.__pageInit());
      console.log("✔ Page init executed");
    } else {
      console.warn("⚠ No __pageInit found");
    }
  } catch (e) { console.error("✖ Page init error:", e); }

  console.log("DONE");
  console.groupEnd();
}

try { console.log("[ADMIN] default.js loaded:", new Date().toISOString()); } catch (_) {}