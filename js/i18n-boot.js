// i18n anti-flash bootstrap
// - Runs early (non-defer) to pick language & mark page as pending i18n
// - Works with /css/i18n-boot.css and js/i18n.js (which sets data-i18n-ready)
(function () {
  try {
    // Inline anti-flicker style:
    // - Ensures body stays hidden immediately, even if /css/i18n-boot.css hasn't loaded yet.
    try {
      if (!document.getElementById('i18n-boot-inline-style')) {
        var st = document.createElement('style');
        st.id = 'i18n-boot-inline-style';
        st.textContent = 'html[data-i18n-pending="1"]:not([data-i18n-ready="1"]) body{visibility:hidden;}';
        (document.head || document.documentElement).appendChild(st);
      }
    } catch (_) {}

    var params = new URLSearchParams(window.location.search || "");
    var urlLang = params.get("lang");
    var storedLang = null;
    try {
      storedLang = window.localStorage ? localStorage.getItem("selectedLanguage") : null;
    } catch (_) {}

    // Source of truth:
    // - If user already chose a language (localStorage), it MUST win.
    // - URL param is treated as a hint/bootstrap only when storage is missing/invalid.
    var hasStored = (storedLang === "en" || storedLang === "tl");
    var hasUrl = (urlLang === "en" || urlLang === "tl");
    var lang = hasStored ? storedLang : (hasUrl ? urlLang : "en");

    // Only bootstrap storage from URL when storage isn't already set.
    if (!hasStored && hasUrl) {
      try { localStorage.setItem("selectedLanguage", urlLang); } catch (_) {}
    }

    var root = document.documentElement;
    root.setAttribute("data-i18n-pending", "1");
    root.setAttribute("data-lang", lang);
    root.lang = lang;
  } catch (_) {
    var rootFallback = document.documentElement;
    rootFallback.setAttribute("data-i18n-pending", "1");
    rootFallback.setAttribute("data-lang", "en");
    rootFallback.lang = "en";
  }
})();


