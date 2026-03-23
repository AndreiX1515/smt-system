<div class="header_memberinfo">
  <div class="member-identity">
    <div class="avatar">
      <svg viewBox="0 0 33 33" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="16.472" cy="10.98" r="6.113" stroke="#0065F8" stroke-width="1.5"/>
        <path d="M7.55078 28.8244V25.2754C7.55078 23.0662 9.34164 21.2754 11.5508 21.2754H21.3939C23.6031 21.2754 25.3939 23.0663 25.3939 25.2754V28.8244"
          stroke="#0065F8" stroke-width="1.5" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="member-text">
      <h3 class="name">ADMIN</h3>
      <p class="role">Employee</p>
    </div>
  </div>

  <div class="divider"></div>

  <div class="theme-row">
    <span class="theme-label">Theme: <span class="theme-val" id="themeVal">Light</span></span>
    <button class="toggle-track" id="themeToggleBtn" type="button" aria-label="Toggle theme">
      <span class="toggle-thumb"></span>
    </button>
  </div>

  <div class="divider"></div>

  <div class="actions">
    <button type="button" class="jw-button typeA" id="changePasswordBtn"
      onclick="event.preventDefault(); event.stopPropagation(); modal('/admin/member/change-password.html', '580px', '520px');">
      Change Password
    </button>
    <button type="button" class="jw-button typeA" id="logoutBtn">Logout</button>
  </div>
</div>