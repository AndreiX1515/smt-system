<!-- inc/header.php
     Header fragment — loaded async by init() into #layoutHeader.
     Has access to all global JS functions from default.js.
-->

<div class="header-left">
  <button
    type="button"
    class="nav-toggle-btn"
    onclick="toggleLayoutNav()"
    title="Toggle sidebar"
    aria-label="Toggle sidebar"
    aria-expanded="false"
    aria-controls="layoutNav"
  >
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <rect y="3"  width="20" height="2" rx="1" fill="currentColor"/>
      <rect y="9"  width="20" height="2" rx="1" fill="currentColor"/>
      <rect y="15" width="20" height="2" rx="1" fill="currentColor"/>
    </svg>
  </button>

  <div class="brand">
    <a href="/admin" data-no-router>
      <img src="../image/logo.png" alt="JW Admin">
    </a>
  </div>
</div>

<div class="header-right">
  
  <!-- Language toggle -->
  <button class="jw-button typeA" type="button" onclick="language_set()">
    <span class="lang-icon">
      <img src="../image/Union.svg" alt="" aria-hidden="true">
    </span>
    <span class="lang-text">English</span>
  </button>

  <!-- Member dropdown -->
  <div class="membermenu">
    <button
      class="jw-button memberbtn"
      type="button"
      onclick="member_info(this, '../inc/header_memberinfo.html')"
    >
      <span class="user-icon">
        <img src="../image/person.svg" alt="" aria-hidden="true">
      </span>
      <span class="user-name">-</span>
      <span class="user-arrow">
        <img src="../image/arrowVector.svg" alt="" aria-hidden="true">
      </span>
    </button>

    <div class="position">
      <div class="wrap"></div>
    </div>
	
  </div>


</div>