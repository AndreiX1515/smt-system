<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>SMT-Escape | Login</title>

  <link rel="shortcut icon" href="../admin/image/logo.png">


  <!-- Default CSS -->
  <link rel="stylesheet" href="../public/css/general/root.css">

  <!-- Components CSS -->
  <link rel="stylesheet" href="../public/css/components/modal.css">

  <!-- Login page styles -->
  <link rel="stylesheet" href="../public/css/pages/login.css">
</head>

<body>

  <div class="login-split">

    <!-- ══════════════════════════════════════
         LEFT — Image / Brand Panel
         Hidden on tablet + mobile via CSS
         ══════════════════════════════════════ -->
    <aside class="login-image-panel" aria-hidden="true">
      <div class="login-image-panel__bg"></div>

      <div class="login-image-panel__content">

        <!-- Brand -->
        <div class="login-brand">
          <div class="login-brand__logo">
            <img src="../admin/image/logo.png" alt="SMT Escape logo">
          </div>
          <span class="login-brand__name">SMT Escape</span>
        </div>

        <!-- Hero copy -->
        <div class="login-image-panel__tagline">
          <h1>Where every trip<br>becomes a <span>smart escape.</span></h1>
          <p>Your gateway to seamless travel planning and unforgettable destinations.</p>
        </div>

        <!-- Stats -->
        <div class="login-stat-strip">
          <div class="login-stat">
            <span class="login-stat__num">240+</span>
            <span class="login-stat__label">Destinations</span>
          </div>
          <div class="login-stat">
            <span class="login-stat__num">18k</span>
            <span class="login-stat__label">Bookings/mo</span>
          </div>
          <div class="login-stat">
            <span class="login-stat__num">99.9%</span>
            <span class="login-stat__label">Uptime</span>
          </div>
        </div>

      </div>
    </aside>

    <!-- ══════════════════════════════════════
         RIGHT — Form Panel
         ══════════════════════════════════════ -->
    <main class="login-form-panel">

      <!-- Theme toggle — top-right of form panel -->
      <header class="theme-header">

        <span class="theme-label" id="themeLabel">Light</span>

        <label class="pill-toggle" aria-label="Toggle dark mode">
          <input type="checkbox" id="themeToggle">

          <!-- Moon icon -->
          <!-- <span class="pill-toggle__moon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
          </span> -->

          <!-- Toggle thumb -->
          <span class="pill-toggle__thumb"></span>

          <!-- Sun icon -->
          <!-- <span class="pill-toggle__sun">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4" />
              <line x1="12" y1="2" x2="12" y2="4" />
              <line x1="12" y1="20" x2="12" y2="22" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="2" y1="12" x2="4" y2="12" />
              <line x1="20" y1="12" x2="22" y2="12" />
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
            </svg>
          </span> -->

        </label>
      </header>

      <div class="login-form-inner">

        <header class="login-form-header">
          <h2>Welcome back</h2>
          <p>Sign in to access your dashboard</p>
        </header>

        <form id="loginForm" autocomplete="off" novalidate>

          <!-- ID -->
          <div class="lf-field">
            <label class="lf-label" for="username" data-lan-eng="ID">ID</label>
            <div class="lf-input-wrap">
              <input
                class="lf-input"
                type="text"
                id="username"
                name="username"
                placeholder="Enter your ID"
                data-lan-eng="ID"
                autocomplete="username">
            </div>
            <p id="usernameError" class="lf-error" role="alert"></p>
          </div>

          <!-- Password -->
          <div class="lf-field">
            <label class="lf-label" for="password" data-lan-eng="Password">Password</label>
            <div class="lf-input-wrap">
              <input
                class="lf-input"
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                data-lan-eng="Password"
                autocomplete="current-password"
                style="padding-right:46px;">
              <button type="button" class="lf-eye-btn" id="eyeBtn" aria-label="Toggle password visibility">
                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <svg id="eyeOffIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                  <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94" />
                  <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19" />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
              </button>
            </div>
            <p id="passwordError" class="lf-error" role="alert"></p>
          </div>

          <!-- Remember + Links -->
          <div class="lf-row">
            <label class="lf-checkbox">
              <input type="checkbox" id="rememberId" name="rememberId" checked>
              <span class="lf-checkbox__text" data-lan-eng="Remember ID">Remember ID</span>
            </label>
            <div class="lf-links">
              <button type="button" class="lf-link-btn" data-lan-eng="Find ID" onclick="openIdFind()">Find ID</button>
              <button type="button" class="lf-link-btn" data-lan-eng="Reset Password" onclick="openPasswordReset()">Reset Password</button>
            </div>
          </div>

          <!-- Global error -->
          <p id="loginError" class="lf-login-error" role="alert"></p>

          <!-- Submit -->
          <button type="submit" class="lf-submit-btn" data-lan-eng="Login">Sign In</button>

        </form>

        <p class="lf-footer">
          Authorized personnel only.<br>All access is monitored and logged.
        </p>

      </div>
    </main>



    
  </div><!-- /.login-split -->

  <!-- Dependencies -->
  <script src="./js/default.js"></script>
  <script src="./js/super.js?v=20251226_adminloginfix1"></script>

  <script>
    /* ── Theme toggle ─────────────────────────────────────────────────────
       Runs immediately so the correct theme is applied before first paint,
       preventing a flash of the wrong theme on load.
    ─────────────────────────────────────────────────────────────────────── */

    /* ── Theme toggle (Improved + DB Sync) ──────────────────────────────── */
    (function() {

      // ── Local storage helpers ──────────────────────────────
      function getStorageTheme() {
        try {
          return localStorage.getItem('st-theme');
        } catch (_) {
          return null;
        }
      }

      function setStorageTheme(theme) {
        try {
          localStorage.setItem('st-theme', theme);
        } catch (_) {}
      }

      // ── Apply theme immediately to prevent flash ─────────────
      function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        const label = document.getElementById('themeLabel');
        if (label) label.textContent = theme === 'dark' ? 'Dark' : 'Light';
      }

      // ── Step 1: Apply theme ASAP (first paint) ─────────────
      let theme = getStorageTheme() || 'light';
      applyTheme(theme);

      // ── Step 2: Wait for DOM before accessing toggle ────────
      document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        // 3. Sync toggle UI with current theme
        toggle.checked = theme === 'dark';

        // 4. Toggle handler
        toggle.addEventListener('change', function() {
          const newTheme = toggle.checked ? 'dark' : 'light';
          applyTheme(newTheme);
          setStorageTheme(newTheme);

          // 5. Sync change to backend (if user logged in)
          if (window.accountId) { // Make sure accountId is set in JS
            fetch('./api/save-user-setting.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              credentials: 'include',
              body: JSON.stringify({
                accountId: window.accountId,
                settingKey: 'theme',
                settingValue: newTheme
              })
            });
          }
        });

        // 6. Optional: Initial sync to backend after login
        if (window.accountId && theme) {
          fetch('./api/save-user-setting.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
              accountId: window.accountId,
              settingKey: 'theme',
              settingValue: theme
            })
          });
        }
      });

    })();





    /* ── Site init (header compatibility) ────────────────────────────────
    Pass headerUrl: null so default.js does not try to load a header
    include on the login page.
    ─────────────────────────────────────────────────────────────────────── */
    if (typeof init === 'function') init({
      headerUrl: null
    });



    /* ══════════════════════════════════════════════════════════════════════
    PAGE BOOTSTRAP (runs after DOM is ready)

    Step 1 — Session check:
    If the user already has a valid session, skip the login form and
    redirect straight to their dashboard.

    Redirect map:
    agent → ./public/agent/overview.php
    guide → ./public/guide/full-list.php
    cs → ./public/cs/inquiry-list.php
    * → ./public/super/overview.php (admin default)

    Step 2 — Restore saved username:
    Pre-fill the ID field if "Remember ID" was checked before.

    Step 3 — Attach form handler:
    Wire the submit listener once the DOM is confirmed ready.
    ══════════════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', async function() {

      /* Step 1 — Session check */
      try {
        const res = await fetch('./backend/api/check-session.php', {
          credentials: 'same-origin',
        });

        if (res.ok) {
          const data = await res.json();
          if (data.authenticated) {
            const redirectMap = {
              agent: './public/agent/overview.php',
              guide: './public/guide/full-list.php',
              cs: './public/cs/inquiry-list.php',
            };
            const userType = data.userType || 'admin_ph';
            const destination = redirectMap[userType] ?? './super/overview.php';
            window.location.href = destination;
            return; // Stop — page is navigating away
          }
        }
      } catch (_) {
        /* Network or parse failure — fall through and show the login form.
        Never block the user from logging in due to a check-session error. */
      }


      /* Step 2 — Restore saved username */
      try {
        if (typeof getCookie === 'function') {
          const savedUsername = getCookie('saved_username');
          if (savedUsername) {
            const usernameInput = document.getElementById('username');
            const rememberCheckbox = document.getElementById('rememberId');
            if (usernameInput) usernameInput.value = savedUsername;
            if (rememberCheckbox) rememberCheckbox.checked = true;
          }
        }
      } catch (_) {}

      /* Step 3 — Attach form handler */
      const loginForm = document.getElementById('loginForm');
      if (loginForm) loginForm.addEventListener('submit', handleLogin);

    });


    /* ── Password visibility toggle ───────────────────────────────────────
    Swaps between password and text input type, updates the eye icon.
    ─────────────────────────────────────────────────────────────────────── */
    document.getElementById('eyeBtn').addEventListener('click', function() {
      const inp = document.getElementById('password');
      const on = document.getElementById('eyeIcon');
      const off = document.getElementById('eyeOffIcon');
      if (inp.type === 'password') {
        inp.type = 'text';
        on.style.display = 'none';
        off.style.display = '';
      } else {
        inp.type = 'password';
        on.style.display = '';
        off.style.display = 'none';
      }
    });



    /* ══════════════════════════════════════════════════════════════════════
    LOGIN HANDLER

    Flow:
    1. Client-side presence validation.
    2. POST to login.php.
    3. Guard against non-JSON responses (PHP fatal, gateway errors).
    4. On success — save cookie, store accountType, redirect.
    5. On failure — route message to the specific field ('field' key)
    or the global error banner.
    6. On network — show generic connection error, no console output.
    ══════════════════════════════════════════════════════════════════════ */
    async function handleLogin(e) {
      e.preventDefault();

      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const remember = document.getElementById('rememberId').checked;
      const btn = document.querySelector('.lf-submit-btn');

      clearErrors();

      /* Client-side presence checks */
      if (!username) {
        showFieldError('username', 'Please enter your ID.');
        return;
      }
      if (!password) {
        showFieldError('password', 'Please enter your password.');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Signing in…';

      try {
        const body = new FormData();
        body.append('username', username);
        body.append('password', password);

        const res = await fetch('./backend/api/login.php', {
          method: 'POST',
          body,
          credentials: 'same-origin',
        });

        /* Guard: a PHP fatal or gateway error returns HTML, not JSON */
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
          showLoginError('Server error. Please try again later.');
          btn.disabled = false;
          btn.textContent = 'Sign In';
          return;
        }

        const data = await res.json();

        if (data.success) {
          if (typeof setCookie === 'function') {
            remember
              ?
              setCookie('saved_username', username, 365) :
              setCookie('saved_username', '', -1);
          }
          try {
            localStorage.setItem('accountType', data.userType || '');
          } catch (_) {}

          /* Button stays disabled intentionally during navigation */
          window.location.href = data.redirectUrl || './public/super/overview.php';
          return;
        }

        /* Failure — route to field error or global banner based on server hint */
        const msg = data.message || 'Login failed. Please try again.';
        const field = data.field || null;

        if (field === 'username' || field === 'password') {
          showFieldError(field, msg);
        } else {
          showLoginError(msg);
        }

      } catch (_) {
        /* Network failure — user-facing message only, no console output */
        showLoginError('Unable to connect. Please check your connection and try again.');
      }

      btn.disabled = false;
      btn.textContent = 'Sign In';
    }



    /* ══════════════════════════════════════════════════════════════════════
    ERROR HELPERS
    ══════════════════════════════════════════════════════════════════════ */
    /**
     * Clears all visible error states — field highlights and error messages.
     * Called at the start of every validation attempt and form submission.
     */
    function clearErrors() {
      ['usernameError', 'passwordError', 'loginError'].forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = '';
        el.classList.remove('visible');
      });
      document.querySelectorAll('.lf-input').forEach(function(i) {
        i.classList.remove('has-error');
      });
    }


    /**
     * Highlights a specific field and shows its error message below it.
     *
     * The error element ID is derived as fieldName + 'Error' so any field
     * with a matching <p id="fieldNameError"> works without updating this
     * function — e.g. 'username' → #usernameError, 'password' → #passwordError.
     *
     * @param {string} field - The input element's id.
     * @param {string} msg - Error message to display.
     */
    function showFieldError(field, msg) {
      clearErrors();
      const input = document.getElementById(field);
      const errEl = document.getElementById(field + 'Error');
      if (input) {
        input.classList.add('has-error');
        input.focus();
      }
      if (errEl) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
      }
    }



    /**
     * Shows a global error banner not tied to any specific field.
     * Used for account-level rejections (inactive, suspended, banned),
     * server errors, and network failures.
     *
     * @param {string} msg - Error message to display.
     */

    function showLoginError(msg) {
      clearErrors();
      const el = document.getElementById('loginError');
      if (el) {
        el.textContent = msg;
        el.classList.add('visible');
      }
    }



    /* ── Modals ───────────────────────────────────────────────────────────
    Loaded via the Modal class in default.js.
    ─────────────────────────────────────────────────────────────────────── */
    function openIdFind() {
      if (typeof modal === 'function') modal('../public/member/agent-id-find.html', '480px');
    }

    function openPasswordReset() {
      if (typeof modal === 'function') modal('../public/member/agent-password-reset.html', '480px');
    }
  </script>

</body>

</html>