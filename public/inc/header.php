<header class="layout-header">

  <!-- Left: sidebar toggle + logo -->
  <div class="header-left">
    <!-- <button
      type="button"
      class="nav-toggle-btn"
      onclick="toggleLayoutNav()"
      title="Toggle sidebar"
      aria-label="Toggle sidebar"
      aria-expanded="false"
      aria-controls="layoutNav">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
        <rect y="3" width="20" height="2" rx="1" fill="currentColor" />
        <rect y="9" width="20" height="2" rx="1" fill="currentColor" />
        <rect y="15" width="20" height="2" rx="1" fill="currentColor" />
      </svg>
    </button> -->

    <div class="brand">
      <a href="/admin" data-no-router>
        <img src="../image/logo.png" alt="JW Admin">
        <span class="brand-name">SMT-ESCAPE</span>
      </a>
    </div>


  </div>


  <!-- Right: language + member -->
  <div class="header-right">

    <!-- Language button -->
    <button class="jw-button typeA" type="button" onclick="language_set()">
      <span class="lang-icon">
        <i class="fas fa-globe"></i>
      </span>
      <span class="lang-text">English</span>
    </button>

    <!-- Theme Toggle button -->
    <button class="theme-btn" id="themeBtn" type="button" aria-label="Toggle theme">
      <span class="theme-icon" id="themeIcon"></span>
    </button>

    <!-- Member dropdown -->
    <div class="dropdown relative" data-dropdown="dropdown">

      <button type="button"
        class="jw-button memberbtn dropdown-toggle"
        aria-haspopup="menu"
        aria-expanded="false"
        data-dropdown-toggle>
        <span class="user-icon">
          <img src="../image/person.svg" alt="" aria-hidden="true">
        </span>
        <span class="user-name">ADMIN</span>
        <span class="user-arrow">
          <img src="../image/arrowVector.svg" alt="" aria-hidden="true">
        </span>
      </button>

      <div class="dropdown-menu dropdown-open:opacity-100 hidden w-64" role="menu">
        <div class="header_memberinfo p-4">

          <!-- Member identity -->
          <div class="member-identity">
            <div class="avatar">
              <svg viewBox="0 0 33 33" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="16.472" cy="10.98" r="6.113" stroke="#0065F8" stroke-width="1.5" />
                <path d="M7.55078 28.8244V25.2754C7.55078 23.0662 9.34164 21.2754 11.5508 21.2754H21.3939C23.6031 21.2754 25.3939 23.0663 25.3939 25.2754V28.8244"
                  stroke="#0065F8" stroke-width="1.5" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="member-text">
              <h3 class="name">ADMIN</h3>
              <p class="role">Employee</p>
            </div>
          </div>


          <div class="divider my-3"></div>

          <!-- Actions -->
          <div class="actions flex flex-col gap-2">
            <button type="button" class="jw-button typeA"
              onclick="event.preventDefault(); event.stopPropagation(); modal('/admin/member/change-password.html', '580px', '520px');">
              Change Password
            </button>
            <button type="button" class="jw-button typeA" id="logoutBtn">Logout</button>
          </div>

        </div>
      </div>

    </div>

  </div> <!-- .header-right -->

</header>



<!-- ==================== HEADER + MEMBER JS ==================== -->
<script>
  const themeBtn = document.getElementById('themeBtn');
  const themeIcon = document.getElementById('themeIcon');


  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    updateIcon(theme);
  }


  function updateIcon(theme) {
    if (theme === 'dark') {
      // Moon icon
      themeIcon.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 12.79A9 9 0 1111.21 3A7 7 0 0021 12.79z"/>
        </svg>
      `;
    } else {
      // Sun icon
      themeIcon.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="4"/>
          <line x1="12" y1="2" x2="12" y2="4"/>
          <line x1="12" y1="20" x2="12" y2="22"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="2" y1="12" x2="4" y2="12"/>
          <line x1="20" y1="12" x2="22" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      `;
    }
  }


  function initTheme() {
    const saved = localStorage.getItem('theme') || 'light';
    setTheme(saved);
  }


  themeBtn.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
  });


  initTheme();
</script>