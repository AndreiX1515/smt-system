/* ============================================================
   default.js — Admin shared utilities & layout bootstrap
   UI Library: PrelineUI (HSStaticMethods.autoInit)
   ============================================================ */

/* ──────────────────────────────────────────────────────────
   § 0. DEBUG LOGGER
   Centralised logger. Set LOG_LEVEL to control verbosity.
   Levels: 0 = silent, 1 = errors only, 2 = warn+error, 3 = all
   ────────────────────────────────────────────────────────── */

const LOG_LEVEL = 3; // ← Set to 0 in production

const _log = {
  _ts:    () => new Date().toISOString().slice(11, 23), // HH:MM:SS.mmm
  info:   (...a) => { if (LOG_LEVEL >= 3) console.log  (`[${_log._ts()}] [INFO]`, ...a); },
  warn:   (...a) => { if (LOG_LEVEL >= 2) console.warn (`[${_log._ts()}] [WARN]`, ...a); },
  error:  (...a) => { if (LOG_LEVEL >= 1) console.error(`[${_log._ts()}] [ERR ]`, ...a); },
  group:  (label) => { if (LOG_LEVEL >= 3) console.group(`[${_log._ts()}] ▶ ${label}`); },
  groupEnd: ()   => { if (LOG_LEVEL >= 3) console.groupEnd(); },
  table:  (data) => { if (LOG_LEVEL >= 3) console.table(data); },
};

_log.info("default.js — parsing started");


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

function getLangText(el, lang) {
  lang = String(lang || "").toLowerCase();
  if (lang === "tl") return el.getAttribute("data-lan-tl") || el.getAttribute("data-lan-eng");
  return el.getAttribute("data-lan-eng") || el.getAttribute("data-lan-tl");
}

function __admin_force_lang_cookie() {
  try {
    const cur  = String(getCookie("lang") || "").toLowerCase();
    const lang = cur === "tl" ? "tl" : "eng";
    setCookie("lang", lang, 365);
    _log.info(`i18n — forced lang cookie → "${lang}"`);
    return lang;
  } catch (e) {
    _log.error("i18n — __admin_force_lang_cookie failed:", e);
    try { setCookie("lang", "eng", 365); } catch (_) {}
    return "eng";
  }
}

function language_apply(lang) {
  lang = String(lang || "").toLowerCase();
  if (!["eng", "tl", "kor", "ko"].includes(lang)) lang = "eng";
  if (lang === "ko") lang = "kor";
  _log.info(`i18n — applying lang="${lang}"`);

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

  document.querySelectorAll("[data-lan-eng-placeholder]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-placeholder");
    const tl  = el.getAttribute("data-lan-tl-placeholder");
    const kor = el.getAttribute("data-lan-kor-placeholder") || el.getAttribute("data-lan-ko-placeholder");
    if (lang === "eng" && eng)                          el.setAttribute("placeholder", eng);
    else if (lang === "tl")                             el.setAttribute("placeholder", tl || eng || el.getAttribute("placeholder") || "");
    else if ((lang === "kor" || lang === "ko") && kor)  el.setAttribute("placeholder", kor);
  });

  document.querySelectorAll("[data-lan-eng-alt]").forEach((el) => {
    const eng = el.getAttribute("data-lan-eng-alt");
    const tl  = el.getAttribute("data-lan-tl-alt");
    const kor = el.getAttribute("data-lan-kor-alt") || el.getAttribute("data-lan-ko-alt");
    if (lang === "eng" && eng)                          el.setAttribute("alt", eng);
    else if (lang === "tl")                             el.setAttribute("alt", tl || eng || el.getAttribute("alt") || "");
    else if ((lang === "kor" || lang === "ko") && kor)  el.setAttribute("alt", kor);
  });

  document.querySelectorAll("select option[data-lan-eng]").forEach((option) => {
    const eng = option.getAttribute("data-lan-eng");
    const tl  = option.getAttribute("data-lan-tl");
    const kor = option.getAttribute("data-lan-kor") || option.getAttribute("data-lan-ko");
    if (lang === "eng" && eng)                          option.textContent = eng;
    else if (lang === "tl")                             option.textContent = tl || eng || option.textContent;
    else if ((lang === "kor" || lang === "ko") && kor)  option.textContent = kor;
    else                                                option.textContent = kor || eng || option.textContent;
  });

  try { if (typeof window.refreshAllJwSelect === "function") window.refreshAllJwSelect(); } catch (_) {}

  document.querySelectorAll(".lang-text").forEach((node) => {
    if (lang === "eng")      node.textContent = "한국어";
    else if (lang === "kor") node.textContent = "Tagalog";
    else                     node.textContent = "English";
  });

  const htmlLang = document.getElementById("html-lang");
  if (htmlLang) {
    if (lang === "tl")       htmlLang.setAttribute("lang", "tl");
    else if (lang === "kor") htmlLang.setAttribute("lang", "ko");
    else                     htmlLang.setAttribute("lang", "en");
  }

  window.dispatchEvent(new CustomEvent("languageChanged", { detail: { lang } }));
  _log.info(`i18n — lang="${lang}" applied ✔`);
}

function language_set() {
  const cur  = getCookie("lang") || "eng";
  const next = cur === "eng" ? "kor" : cur === "kor" ? "tl" : "eng";
  _log.info(`i18n — language_set: "${cur}" → "${next}"`);
  setCookie("lang", next, 365);
  try { language_apply(next); } catch (e) { _log.error("i18n — language_apply failed:", e); }
  try { setTimeout(() => { try { language_apply(next); } catch (_) {} }, 0); } catch (_) {}
}


/* ──────────────────────────────────────────────────────────
   § 3. HANGUL SCRUBBER
   ────────────────────────────────────────────────────────── */

function __admin_scrub_hangul(root = document.body) {
  try {
    if (!root) return;
    const hasHangul   = (s) => /[가-힣]/.test(String(s || ""));
    const stripHangul = (s) => String(s || "").replace(/[가-힣]+/g, "").replace(/\s{2,}/g, " ").trim();
    const ensureEnglish = (orig, cleaned) => (!hasHangul(orig) ? cleaned : (cleaned?.length ? cleaned : orig));

    const isI18nEl = (el) => {
      try {
        return !!el?.closest?.("[data-lan-eng],[data-lan-tl],[data-lan-ko],[data-lan-kor],[data-i18n],[data-i18n-placeholder],[data-i18n-title],[data-i18n-alt]");
      } catch (_) { return false; }
    };

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

    try {
      root.querySelectorAll("*").forEach((el) => {
        if (!el?.getAttribute) return;
        try { if (el.closest?.('[data-skip-hangul-scrub="1"]')) return; } catch (_) {}
        if (!isI18nEl(el)) return;
        for (const attr of ["placeholder", "title", "aria-label", "alt"]) {
          const v = el.getAttribute(attr);
          if (!v || !hasHangul(v)) continue;
          if (attr === "alt")              el.setAttribute(attr, "Image");
          else if (attr === "placeholder") el.setAttribute(attr, "Please enter a value.");
          else                             el.setAttribute(attr, ensureEnglish(v, stripHangul(v)));
        }
      });
    } catch (_) {}

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
      [/삭제하시겠습니까\??/,    "Are you sure you want to delete?"],
      [/저장하시겠습니까\??/,    "Do you want to save?"],
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
   ────────────────────────────────────────────────────────── */

function jw_select() {
  const el = (tag, cls) => { const n = document.createElement(tag); if (cls) n.className = cls; return n; };

  const offWidth = (s) => {
    const t = el("div");
    t.style.cssText = "position:absolute;visibility:hidden;white-space:nowrap;display:inline-block;";
    t.textContent   = s;
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
      if (o.selected)  li.setAttribute("aria-selected", "true");
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

window.refreshAllJwSelect = function () {
  try {
    document.querySelectorAll(".jw-select").forEach((wrap) => {
      const nativeSel = wrap.querySelector("select");
      const box       = wrap.querySelector(".jw-selected");
      const list      = wrap.querySelector(".jw-select-list");
      if (!nativeSel || !box || !list) return;

      if (nativeSel.disabled) { wrap.classList.add("is-disabled"); box.setAttribute("aria-disabled", "true"); box.tabIndex = -1; }
      else                    { wrap.classList.remove("is-disabled"); box.removeAttribute("aria-disabled"); box.tabIndex = 0; }

      const opt  = nativeSel.selectedOptions?.[0] || nativeSel.options?.[0] || null;
      const icon = opt?.dataset?.icon || null;
      box.innerHTML = icon ? `<i class="${icon}"></i> ${opt.textContent || ""}` : (opt?.textContent || "");

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
  } catch (e) { _log.error("refreshAllJwSelect failed:", e); }
};


/* ──────────────────────────────────────────────────────────
   § 6. MODAL
   ────────────────────────────────────────────────────────── */

class Modal {
  constructor(action, width, sq) {
    this.action = action;
    this.width  = width;
    this.sq     = sq;
    this.el     = null;
    this._abort = null;
  }

  static _toQuery(data) {
    if (!data) return "";
    if (typeof data === "string")  return data.replace(/^\?/, "");
    if (data instanceof FormData)  return new URLSearchParams([...data.entries()]).toString();
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
        // FIX: was replaceWith(s, old) — use replaceChild instead
        old.parentNode?.replaceChild(s, old);
      });
    } catch (e) { _log.error("[Modal] script exec failed:", e); }
  }

  async _loadHTML(url, data) {
    const u = new URL(url, location.href);
    u.searchParams.set("_", Date.now());
    this._abort?.abort();
    this._abort = new AbortController();
    const timer = setTimeout(() => this._abort?.abort(), 15000);
    _log.info(`[Modal] fetching "${u.toString()}" method=${data ? "POST" : "GET"}`);
    try {
      const opts = data
        ? { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" }, body: Modal._toQuery(data), signal: this._abort.signal }
        : { signal: this._abort.signal, credentials: "same-origin" };
      const res = await fetch(u.toString(), opts);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const html = await res.text();
      _log.info(`[Modal] fetched OK — ${html.length} chars`);
      return html;
    } catch (e) {
      _log.error(`[Modal] fetch failed for "${url}":`, e);
      throw e;
    } finally {
      clearTimeout(timer);
      this._abort = null;
    }
  }

  async open() {
    _log.info(`[Modal] opening — action="${this.action}" width="${this.width}"`);
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

        const lang = getCookie("lang") || "eng";
        setCookie("lang", lang, 365);
        await Promise.resolve(language_apply(lang));
        if (typeof jw_select === "function") await Promise.resolve(jw_select());

        d.querySelector("#closeDialog")?.addEventListener("click", () => this.close(), { once: true });
        document.dispatchEvent(new CustomEvent("modal:loaded", { detail: { dialog: d, action: this.action, data: this.sq } }));
        _log.info(`[Modal] ready ✔ — action="${this.action}"`);
      } catch (err) {
        d.innerHTML = `<div class="dialog-error">Failed to load content. Please try again.</div>`;
        _log.error("[Modal] load error:", err);
      }
    }
    return this;
  }

  async update(action, sq) {
    if (!this.el) return;
    const url  = action || this.action;
    const data = sq !== undefined ? sq : this.sq;
    if (!url) return;
    _log.info(`[Modal] update — url="${url}"`);
    try {
      const html = await this._loadHTML(url, data);
      this.el.innerHTML = html;
      Modal._execScripts(this.el);
      this.el.querySelector("#closeDialog")?.addEventListener("click", () => this.close(), { once: true });
      document.dispatchEvent(new CustomEvent("modal:loaded", { detail: { dialog: this.el, action: url, data } }));
    } catch (err) {
      this.el.innerHTML = `<div class="dialog-error">Failed to load content.</div>`;
      _log.error("[Modal] update error:", err);
    }
  }

  close() {
    if (!this.el) return;
    _log.info(`[Modal] closing — action="${this.action}"`);
    this._abort?.abort();
    this.el.classList.add("dialog--closing");
    this.el.addEventListener("animationend", () => { this.el?.close?.(); this.el?.remove(); this.el = null; }, { once: true });
  }
}

function modal(page, w, sq) {
  _log.info(`modal() — page="${page}" w="${w}"`);
  const m = new Modal(page, w, sq);
  m.open();
  return m;
}

function modal_close() {
  const dialogs = document.querySelectorAll("dialog.jw-dialog");
  if (!dialogs.length) { _log.warn("modal_close() — no open dialogs found"); return; }
  const last = dialogs[dialogs.length - 1];
  last.classList.add("dialog--closing");
  last.addEventListener("animationend", () => { last.close?.(); last.remove(); }, { once: true });
  _log.info("modal_close() — closed topmost dialog");
}


/* ──────────────────────────────────────────────────────────
   § 7. AUTH — SESSION, LOGOUT, CHANGE PASSWORD
   ────────────────────────────────────────────────────────── */

function initLogoutButton(container) {
  const logoutBtn = container?.querySelector("#logoutBtn") ?? document.getElementById("logoutBtn");
  if (logoutBtn && !logoutBtn.hasAttribute("data-logout-initialized")) {
    logoutBtn.setAttribute("data-logout-initialized", "true");
    logoutBtn.addEventListener("click", handleLogout);
    _log.info("initLogoutButton — logout button wired ✔");
  }

  const changePwBtn = container?.querySelector("#changePasswordBtn") ?? document.getElementById("changePasswordBtn");
  if (changePwBtn && !changePwBtn.hasAttribute("data-change-password-initialized")) {
    changePwBtn.setAttribute("data-change-password-initialized", "true");
    changePwBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      modal("/admin/member/change-password.html", "580px", "520px");
    });
    _log.info("initLogoutButton — change-password button wired ✔");
  }
}

(function bindChangePasswordDelegated() {
  if (window.__ST_ADMIN_CHANGE_PW_DELEGATED__) return;
  window.__ST_ADMIN_CHANGE_PW_DELEGATED__ = true;
  document.addEventListener("click", (e) => {
    const t = e.target?.closest?.("#changePasswordBtn");
    if (!t) return;
    e.preventDefault();
    e.stopPropagation();
    try { modal("/admin/member/change-password.html", "580px", "520px"); }
    catch (err) { _log.error("Failed to open change-password modal:", err); alert("Failed to open Change Password."); }
  }, true);
  _log.info("bindChangePasswordDelegated — delegated listener attached ✔");
})();

async function hydrateAdminIdentityUI(root) {
  _log.info("hydrateAdminIdentityUI — fetching session...");
  try {
    const res  = await fetch("../backend/api/check-session.php", { credentials: "same-origin", cache: "no-store" });
    if (!res.ok) { _log.warn(`hydrateAdminIdentityUI — session fetch returned HTTP ${res.status}`); return; }
    const data = await res.json().catch(() => ({}));
    if (!data?.authenticated) { _log.warn("hydrateAdminIdentityUI — not authenticated:", data); return; }

    const headerName = document.querySelector(".layout-header .user-name");
    if (headerName) {
      const labels = { admin: "Admin", cs: "CS" };
      headerName.textContent = labels[data.userType] ?? data.displayName ?? "Admin";
    }

    const scope  = root || document;
    const nameEl = scope.querySelector(".header_memberinfo .name");
    const roleEl = scope.querySelector(".header_memberinfo .role");
    if (nameEl) nameEl.textContent = data.displayName || "ADMIN";
    if (roleEl) roleEl.textContent = data.roleLabel   || "Employee";
    _log.info("hydrateAdminIdentityUI — identity hydrated ✔", { userType: data.userType, displayName: data.displayName });
  } catch (e) { _log.error("hydrateAdminIdentityUI — error:", e); }
}

let isLoggingOut = false;

async function handleLogout(e) {
  e?.preventDefault();
  e?.stopPropagation();
  if (isLoggingOut) { _log.warn("handleLogout — already in progress, ignoring"); return; }
  if (!confirm("Do you want to log out?")) return;
  isLoggingOut = true;
  _log.info("handleLogout — posting to logout endpoint...");
  try {
    const res  = await fetch("../../public/backend/api/logout.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" } });
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (data.success) {
      _log.info("handleLogout — success, redirecting...");
      try { localStorage.removeItem("accountType"); } catch (_) {}
      window.location.href = "../index.php";
    } else {
      _log.error("handleLogout — server returned failure:", data);
      isLoggingOut = false;
      alert(data.message || "Logout failed.");
    }
  } catch (err) {
    _log.error("handleLogout — fetch error:", err);
    isLoggingOut = false;
    try { localStorage.removeItem("accountType"); } catch (_) {}
    window.location.href = "../index.php";
  }
}


/* ──────────────────────────────────────────────────────────
   § 8. MEMBER INFO DROPDOWN
   ────────────────────────────────────────────────────────── */

function member_info(btn, page) {
  const root = btn.closest(".membermenu") || btn;
  const box  = root.querySelector(".position");
  if (!box) { _log.warn("member_info — .position element not found"); return; }

  let wrap = box.querySelector(".wrap");
  if (!wrap) { wrap = document.createElement("div"); wrap.className = "wrap"; box.appendChild(wrap); }
  if (box.classList.contains("on")) return closeBox();

  if (!wrap.innerHTML.trim()) {
    _log.info(`member_info — loading fragment: "${page}"`);
    wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#6b7280;">Loading...</div>';
    fetch(page + "?" + Date.now(), { cache: "no-store" })
      .then((r) => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.text(); })
      .then((html) => {
        wrap.innerHTML = html;
        initLogoutButton(wrap);
        hydrateAdminIdentityUI(wrap);
        openBox();
        _log.info("member_info — fragment loaded ✔");
      })
      .catch((e) => {
        _log.error("member_info — fragment load failed:", e);
        wrap.innerHTML = '<div style="padding:10px;font-size:13px;color:#ef4444;">Failed to load.</div>';
        openBox();
      });
  } else { openBox(); }

  function openBox() {
    box.classList.add("on");
    const onDocDown     = (e) => { if (!root.contains(e.target)) closeBox(); };
    const onInsideClick = (e) => { if (e.target.closest(".closeMember_info")) { e.preventDefault(); closeBox(); } };
    removeListeners();
    document.addEventListener("pointerdown", onDocDown, true);
    box.addEventListener("click", onInsideClick);
    box._off = () => { document.removeEventListener("pointerdown", onDocDown, true); box.removeEventListener("click", onInsideClick); };
  }
  function closeBox()        { box.classList.remove("on"); removeListeners(); }
  function removeListeners() { box._off?.(); box._off = null; }
}


/* ──────────────────────────────────────────────────────────
   § 9. GENERAL UI HELPERS
   ────────────────────────────────────────────────────────── */

function setFabMenuState(menu, open) {
  if (!menu) return;
  menu.classList.toggle("on", open);
  menu.setAttribute("aria-hidden", String(!open));
  if ("inert" in menu) menu.inert = !open;
}

function layerToggle(btn) {
  const menu     = btn.closest(".layerToggleWrap")?.querySelector(":scope > .fab-menu");
  if (!menu) return;
  const willOpen = !menu.classList.contains("on");
  setFabMenuState(menu, willOpen);
  if (willOpen) { const f = menu.querySelector('button, [tabindex]:not([tabindex="-1"])'); f?.focus(); }
  else          { btn.focus(); }
}

function helpTextChange(target, v) { const el = document.querySelector(target); if (!el) return; el.textContent = v || ""; }

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

function scrollToSection(sectionId) {
  const target = document.getElementById(sectionId)
    || document.querySelector(`[data-section-name="${sectionId}"]`)
    || document.querySelector(`h2.section-title[data-lan-eng="${sectionId}"]`);
  if (!target) { _log.warn(`scrollToSection — section "${sectionId}" not found`); return; }
  if (target.classList.contains("hidden")) {
    const btn = document.querySelector(`button.aside-add-section[data-target-section="${sectionId}"]`);
    if (btn?.classList.contains("on")) togglePlusMinus(btn);
  }
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState(sectionId);
}

function updateAsideActiveState(sectionId) {
  document.querySelectorAll(".aside-row").forEach((row) => {
    row.classList.remove("is-active");
    const t = row.querySelector(".aside-row__title, .aside-row__subtitle");
    if (t?.getAttribute("data-scroll-target") === sectionId) row.classList.add("is-active");
  });
}

function scrollToDayPanel(dayNum) {
  const target = (dayNum === 1 ? document.getElementById("nday") : null)
    || document.querySelector(`.nday-panel[data-day="${dayNum}"]`);
  if (!target) { _log.warn(`scrollToDayPanel — panel for day ${dayNum} not found`); return; }
  window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 120, behavior: "smooth" });
  updateAsideActiveState("day-" + dayNum);
}

function trash_typeA(it) {
  const tbody = it.closest("tbody");
  if (!tbody) return;
  if (tbody.querySelectorAll("tr").length === 1) { modal("/admin/super/template-detail-modal2.html", "580px", "252px"); return; }
  it.closest("tr")?.remove();
  [...tbody.querySelectorAll("tr")].forEach((row, idx) => {
    const first = row.querySelector("td");
    if (first && Number.isFinite(parseInt(first.textContent, 10))) first.textContent = String(idx + 1);
  });
}

function temp_link(v) { location.href = v; }


/* ──────────────────────────────────────────────────────────
   § 10. SIDEBAR
   ────────────────────────────────────────────────────────── */

function toggleLayoutNav() {
  const nav = document.getElementById("layoutNav");
  const btn = document.querySelector(".nav-toggle-btn");
  if (!nav) { _log.warn("toggleLayoutNav — #layoutNav not found"); return; }
  if (window.innerWidth <= 768) {
    const isOpen = nav.classList.toggle("is-open");
    btn?.setAttribute("aria-expanded", String(isOpen));
    _log.info(`toggleLayoutNav — mobile sidebar ${isOpen ? "opened" : "closed"}`);
  } else {
    const isCollapsed = nav.classList.toggle("is-collapsed");
    try { localStorage.setItem("navCollapsed", isCollapsed ? "1" : "0"); } catch (_) {}
    _log.info(`toggleLayoutNav — sidebar ${isCollapsed ? "collapsed" : "expanded"}`);
  }
}


/* ──────────────────────────────────────────────────────────
   § 11. NAV — ACCORDION + ACTIVE STATE
   ────────────────────────────────────────────────────────── */

const _nav = {
  init() {
    _log.info("_nav.init — wiring accordion buttons...");
    document.querySelectorAll("#layoutNav .nav-btn").forEach((btn) => {
      if (btn.tagName === "BUTTON") btn.addEventListener("click", () => _nav.toggle(btn));
    });
    const slug = _nav.currentSlug();
    _log.info(`_nav.init — current slug: "${slug}"`);
    _nav.setActive(slug);
  },

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
    _log.info(`_nav.toggle — item ${!isOpen ? "opened" : "closed"}`);
  },

  currentSlug() {
    const content = document.getElementById("layoutContent");
    if (content?.dataset.pageSlug) return content.dataset.pageSlug;
    return location.pathname.split("/").pop().replace(/\.(php|html)$/, "");
  },

  setActive(slug) {
    if (!slug) { _log.warn("_nav.setActive — no slug provided"); return; }
    document.querySelectorAll("#layoutNav .side-link").forEach((a) => a.classList.remove("is-active"));
    document.querySelectorAll("#layoutNav .nav-item").forEach((li) => li.classList.remove("is-open", "has-active"));

    let matched = null;
    document.querySelectorAll("#layoutNav .side-link, #layoutNav a.nav-btn").forEach((a) => {
      const pages = (a.dataset.page || "").split(",").map((s) => s.trim());
      if (pages.includes(slug)) { a.classList.add("is-active"); matched = a; }
    });

    if (!matched) { _log.warn(`_nav.setActive — no nav link matched slug "${slug}"`); return; }

    const parentItem = matched.closest(".nav-item");
    if (parentItem) {
      parentItem.classList.add("is-open", "has-active");
      parentItem.querySelector("button.nav-btn")?.setAttribute("aria-expanded", "true");
      const wrap = parentItem.querySelector(":scope > .nav-sub-wrap");
      if (wrap) wrap.style.gridTemplateRows = "1fr";
    }
    _log.info(`_nav.setActive — slug "${slug}" matched ✔`);
  },
};


/* ──────────────────────────────────────────────────────────
   § 12. FRAGMENT LOADER & SCRIPT EXECUTOR
   ────────────────────────────────────────────────────────── */

async function _loadFragment(url, selector) {
  if (!url) { _log.warn(`_loadFragment — no URL provided for selector "${selector}"`); return; }
  _log.info(`_loadFragment — fetching "${url}" → "${selector}"`);
  try {
    const res  = await fetch(url, { credentials: "same-origin" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const html = await res.text();
    const el   = document.querySelector(selector);
    if (!el) { _log.warn(`_loadFragment — selector "${selector}" not found in DOM`); return; }
    el.innerHTML = html;
    _execScripts(el);
    _log.info(`_loadFragment — "${url}" injected into "${selector}" ✔ (${html.length} chars)`);
  } catch (err) { _log.error(`_loadFragment — failed for "${url}":`, err); }
}

/**
 * FIX: was using replaceWith(s, old) which is incorrect — it replaces the
 * *parent* with both nodes. replaceChild(s, old) is the correct API.
 */
function _execScripts(container) {
  if (!container) return;
  let count = 0;
  Array.from(container.querySelectorAll("script")).forEach((old) => {
    const s = document.createElement("script");
    Array.from(old.attributes).forEach((a) => s.setAttribute(a.name, a.value));
    s.text = old.src ? "" : old.textContent || "";
    if (old.src) { s.src = old.src; _log.info(`_execScripts — re-executing external: "${old.src}"`); }
    // FIX: was old.parentNode?.replaceWith(s, old) — wrong API, leaves old node in DOM
    old.parentNode?.replaceChild(s, old);
    count++;
  });
  if (count) _log.info(`_execScripts — re-executed ${count} script(s) in`, container);
}


/* ──────────────────────────────────────────────────────────
   § 13. SPA ROUTER
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
    _log.info("_router.start — SPA router listening ✔");
  },

  _isInternal(a) { try { return new URL(a.href, location.href).origin === location.origin; } catch { return false; } },

  async navigate(href, { pushState = true } = {}) {
    if (_router._busy) { _log.warn(`_router.navigate — busy, ignoring "${href}"`); return; }
    _router._busy = true;
    _log.info(`_router.navigate — navigating to "${href}"`);
    const content = document.getElementById("layoutContent");
    content?.classList.add("is-loading");
    try {
      const html = await _router._fetch(href);
      if (!html) return;
      const doSwap = () => _router._swap(html, href, pushState);
      if (document.startViewTransition) {
        await document.startViewTransition(doSwap).finished;
      } else {
        content.style.transition = "opacity 130ms ease";
        content.style.opacity    = "0";
        await doSwap();
        requestAnimationFrame(() => { content.style.opacity = "1"; });
      }
      _log.info(`_router.navigate — swap complete ✔`);
    } finally {
      content?.classList.remove("is-loading");
      _router._busy = false;
    }
  },

  async _fetch(href) {
    try {
      const res = await fetch(href, { credentials: "same-origin" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.text();
    } catch (err) {
      _log.error(`_router._fetch — failed for "${href}":`, err);
      location.href = href;
      return null;
    }
  },

  async _swap(html, href, pushState) {
    const doc     = new DOMParser().parseFromString(html, "text/html");
    const newSect = doc.getElementById("layoutContent");
    if (!newSect) { _log.error("_router._swap — #layoutContent not found in fetched HTML"); return; }

    const content = document.getElementById("layoutContent");
    content.innerHTML = newSect.innerHTML;
    if (newSect.dataset.pageSlug) content.dataset.pageSlug = newSect.dataset.pageSlug;

    const newTitle = doc.querySelector("title");
    if (newTitle) document.title = newTitle.textContent;
    if (pushState) history.pushState({}, "", href);

    _execScripts(content);
    if (typeof window.__pageInit === "function") await Promise.resolve(window.__pageInit());

    const lang = getCookie("lang") || "eng";
    try { language_apply(lang); } catch (e) { _log.error("_router._swap — language_apply failed:", e); }
    try { jw_select?.(); }        catch (e) { _log.error("_router._swap — jw_select failed:", e); }

    // Re-init Preline after content swap
    _initPreline("_router._swap");

    _nav.setActive(_nav.currentSlug());
    window.scrollTo({ top: 0, behavior: "instant" });
    _log.info(`_router._swap — page swapped ✔ slug="${_nav.currentSlug()}"`);
  },
};


/* ──────────────────────────────────────────────────────────
   § 14. i18n BOOTSTRAP
   ────────────────────────────────────────────────────────── */

(function __admin_i18n_bootstrap() {
  _log.group("i18n bootstrap");
  try {
    const root = document.documentElement;
    root.setAttribute("data-admin-i18n-ready", "0");

    const lang = __admin_force_lang_cookie();
    _log.info(`i18n bootstrap — lang="${lang}"`);
    try { root.setAttribute("data-lang", lang === "tl" ? "tl" : "en"); root.lang = lang === "tl" ? "tl" : "en"; } catch (_) {}
    try { language_apply(lang); }                catch (e) { _log.error("i18n bootstrap — language_apply failed:", e); }
    try { __admin_scrub_hangul(document.body); } catch (e) { _log.error("i18n bootstrap — hangul scrub failed:", e); }
  } catch (e) {
    _log.error("i18n bootstrap — critical failure:", e);
    try { document.documentElement.setAttribute("data-admin-i18n-ready", "1"); } catch (_) {}
  }
  _log.groupEnd();
})();


/* ──────────────────────────────────────────────────────────
   § 15. PRELINE HELPER
   FIX: Centralised Preline init. Only uses HSStaticMethods
   (window.Preline does not exist in this build). Calling
   this from multiple points ensures both pre-fragment and
   post-fragment DOM elements are picked up.
   ────────────────────────────────────────────────────────── */

function _initPreline(callerLabel) {
  const label = callerLabel || "unknown";
  _log.group(`Preline init [${label}]`);
  _log.info("Checking Preline globals...", {
    "window.HSStaticMethods":        typeof window.HSStaticMethods,
    "window.HSStaticMethods.autoInit": typeof window.HSStaticMethods?.autoInit,
    "window.Preline":                typeof window.Preline,
  });

  try {
    if (typeof window.HSStaticMethods?.autoInit === "function") {
      window.HSStaticMethods.autoInit();
      _log.info(`✔ Preline initialized via HSStaticMethods.autoInit [${label}]`);
    } else if (typeof window.Preline?.init === "function") {
      // Fallback: should never hit this with current preline.js build, but kept as safety net
      window.Preline.init();
      _log.info(`✔ Preline initialized via Preline.init [${label}] (unexpected fallback)`);
    } else {
      _log.error(
        `✖ Preline not found [${label}]. Neither HSStaticMethods.autoInit nor Preline.init exist.`,
        "\nLoaded preline script tag:", document.querySelector('script[src*="preline"]')?.src ?? "NOT FOUND"
      );
    }
  } catch (e) {
    _log.error(`✖ Preline init threw [${label}]:`, e);
  }

  _log.groupEnd();
}


/* ──────────────────────────────────────────────────────────
   § 16. INIT
   FIX: Preline now runs TWICE:
     • Before fragments load  → picks up main content components
     • After fragments load   → picks up header/nav components
   The layout.php inline Preline script should be REMOVED.
   ────────────────────────────────────────────────────────── */

function waitForHeaderUserNameAndHydrate(timeoutMs = 3000) {
  _log.info("waitForHeaderUserNameAndHydrate — polling for .user-name...");
  const start = Date.now();
  (function loop() {
    if (document.querySelector(".layout-header .user-name")) {
      _log.info("waitForHeaderUserNameAndHydrate — .user-name found, hydrating...");
      return hydrateAdminIdentityUI(document);
    }
    if (Date.now() - start > timeoutMs) { _log.warn("waitForHeaderUserNameAndHydrate — timed out, .user-name never appeared"); return; }
    requestAnimationFrame(loop);
  })();
}

async function init({ headerUrl, navUrl }) {
  _log.group("INIT");
  _log.info("Start →", { headerUrl, navUrl, href: location.href });

  // Diagnostic: log what Preline globals are available right now
  _log.table({
    "HSStaticMethods":         { type: typeof window.HSStaticMethods },
    "HSStaticMethods.autoInit":{ type: typeof window.HSStaticMethods?.autoInit },
    "Preline":                 { type: typeof window.Preline },
  });

  // ── Step 1: Pre-fragment Preline init (main page content) ──────────
  // Run BEFORE awaiting fragments so existing DOM components are caught
  _initPreline("pre-fragment");

  // ── Step 2: Load header + nav fragments ───────────────────────────
  _log.info("Loading fragments...");
  try {
    await Promise.all([
      _loadFragment(headerUrl, "#layoutHeader"),
      _loadFragment(navUrl,    "#layoutNav"),
    ]);
    _log.info("✔ Fragments loaded");
  } catch (e) { _log.error("✖ Fragment load failed:", e); }

  // ── Step 3: Post-fragment Preline init (header/nav components) ─────
  // Run AFTER fragments are injected so newly added components are caught
  _initPreline("post-fragment");

  // ── Step 4: Restore sidebar collapsed state ────────────────────────
  try {
    if (localStorage.getItem("navCollapsed") === "1") {
      document.getElementById("layoutNav")?.classList.add("is-collapsed");
    }
    _log.info("✔ Nav state restored");
  } catch (e) { _log.error("✖ Nav state error:", e); }

  // ── Step 5: Reveal body (anti-FOUC gate) ──────────────────────────
  try {
    document.documentElement.setAttribute("data-admin-i18n-ready", "1");
    _log.info("✔ UI revealed (data-admin-i18n-ready=1)");
  } catch (_) {}

  // ── Step 6: Nav accordion + SPA router ────────────────────────────
  try {
    _nav.init();
    if (!window.__ROUTER_STARTED__) {
      _router.start();
      window.__ROUTER_STARTED__ = true;
      _log.info("✔ Router started");
    } else {
      _log.info("ℹ Router already started — skipping");
    }
    waitForHeaderUserNameAndHydrate();
    _log.info("✔ Nav initialized");
  } catch (e) { _log.error("✖ Nav/Router error:", e); }

  // ── Step 7: Page-specific hook ────────────────────────────────────
  try {
    if (typeof window.__pageInit === "function") {
      _log.info("Running __pageInit...");
      await Promise.resolve(window.__pageInit());
      _log.info("✔ __pageInit executed");
    } else {
      _log.warn("⚠ No __pageInit defined on this page");
    }
  } catch (e) { _log.error("✖ __pageInit error:", e); }

  _log.info("INIT DONE ✔");
  _log.groupEnd();
}

_log.info("default.js — parsed & ready:", new Date().toISOString());