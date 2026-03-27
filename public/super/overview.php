<?php
/* ── Page Config ───────────────────────── */
$pageTitle   = 'Operating Status';
$bodyClass   = 'page-overview'; // optional: for <body class="">
$headerPath  = __DIR__ . '/../../public/templates/header.php';
$navPath     = __DIR__ . '/../../public/templates/nav_super.php';

/* Page-specific CSS/JS (merged with layout defaults) */
$pageCSS     = ['../../public/css/pages/overview.css'];
$pageJS      = ['../../public/assets/js/super.js'];

/* Optional modals for the page */
$pageModals  = <<<HTML
<div id="my-modal" class="overlay modal overlay-open:opacity-100 hidden overlay-open:duration-300" role="dialog" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Modal Title</h3>
        <button type="button" class="btn btn-text btn-circle btn-sm absolute end-3 top-3" aria-label="Close" data-overlay="#my-modal">
          <span class="icon-[tabler--x] size-4"></span>
        </button>
      </div>
      <div class="modal-body">
        <p>This is the modal body content.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft btn-secondary" data-overlay="#my-modal">Close</button>
        <button type="button" class="btn btn-primary">Confirm</button>
      </div>
    </div>
  </div>
</div>
HTML;

/* ── Page Content ───────────────────────── */
$content = <<<HTML

<div class="page-header">
  <div class="page-header-left">
    <time class="page-date" id="currentDate" datetime=""></time>
    <h1 class="page-title" data-lan-eng="{$pageTitle}">{$pageTitle}</h1>
  </div>
  <div class="page-header-right">
    <!-- actions / buttons here -->
  </div>
</div>

<div class="page-content">
  <div class="overview-card-grid jw-mgt32">

    <!-- Reservation Status Card -->
    <article class="card">
      <div>
        <h2 class="card-title" data-lan-eng="Reservation Status">Reservation Status</h2>
        <a href="b2b-booking-list.php" class="card-link" data-lan-eng="See All">See All →</a>
      </div>
      <ul class="status-list">
        <li class="status-item"><i class="dot" style="background:#8b5cf6;"></i><span class="label">Pending</span><strong id="pendingCount">0</strong></li>
        <li class="status-item"><i class="dot dot-blue"></i><span class="label">Waiting for Down Payment</span><strong id="waitingDownCount">0</strong></li>
        <li class="status-item"><i class="dot dot-orange"></i><span class="label">Waiting for Second Payment</span><strong id="waitingSecondCount">0</strong></li>
        <li class="status-item"><i class="dot dot-purple"></i><span class="label">Waiting for Balance</span><strong id="waitingBalanceCount">0</strong></li>
        <li class="status-item"><i class="dot dot-red"></i><span class="label">Payment Rejected</span><strong id="rejectedCount">0</strong></li>
      </ul>
    </article>

    <!-- Inquiry Status Card -->
    <article class="card">
      <div>
        <h2 class="card-title" data-lan-eng="Inquiry Status">Inquiry Status</h2>
        <a href="user-inquiry-list.php" class="card-link" data-lan-eng="See All">See All →</a>
      </div>
      <ul class="status-list">
        <li class="status-item"><i class="dot dot-blue"></i><span class="label">Unanswered</span><strong id="unansweredCount"><span>0</span></strong></li>
        <li class="status-item"><i class="dot dot-green"></i><span class="label">Processing</span><strong id="processingCount"><span>0</span></strong></li>
      </ul>
    </article>

  </div>

  <!-- Keep your other cards (Today Travel Itinerary, Sales Statistics, Product Sales) here verbatim -->

</div>
HTML;

/* ── Optional: inline page JS if needed ───────────────── */
$inlineJS = <<<JS
window.__pageInit = async function initOverview() {
  // Example: Set current date
  const dateEl = document.getElementById('currentDate');
  if (dateEl) {
    const today = new Date();
    dateEl.textContent = today.toLocaleDateString('en-US', {
      year: 'numeric', month: 'long', day: 'numeric'
    });
    dateEl.setAttribute('datetime', today.toISOString().split('T')[0]);
  }

  // Load overview data, sales data, etc. here
};
JS;

// Inject inline JS as a base64 data URI into the page JS list
$pageJS[] = "data:text/javascript;base64," . base64_encode($inlineJS);

/* ── Include layout.php ───────────────── */
require '../../public/templates/layout.php';