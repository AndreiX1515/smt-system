<?php

/**
 * super/overview.php
 *
 * Only three things live here:
 *  1. Page variables
 *  2. HTML content (captured with ob_start)
 *  3. The page init script wrapped in window.__pageInit
 *
 * Nothing else. No <html>, no <head>, no <body>.
 */

$pageTitle   = 'Operating Status';
$pageSlug    = 'overview';            /* must match data-page in nav */
$navUrl      = '../inc/nav_super.php';
$pageScripts = ['../js/super.js?v=20260311'];
$pageStyles  = ['../../public/css/pages/overview.css'];






/* ── 1. Capture page HTML ─────────────────────────── */
ob_start();
?>

<time class="page-date" id="currentDate" datetime=""></time>
<h1 class="page-title" data-lan-eng="Operating Status">Operating Status</h1>

<div class="overview-card-grid jw-mgt32">

  <article class="card">
    <div>
      <h2 class="card-title" data-lan-eng="Reservation Status">Reservation Status</h2>
      <a href="b2b-booking-list.php" class="card-link" data-lan-eng="See All">See All →</a>
    </div>
    <ul class="status-list">
      <li class="status-item">
        <i class="dot" style="background:#8b5cf6;" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Pending">Pending</span>
        <strong class="count" id="pendingCount">0</strong>
      </li>
      <li class="status-item">
        <i class="dot dot-blue" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Waiting for Down Payment">Waiting for Down Payment</span>
        <strong class="count" id="waitingDownCount">0</strong>
      </li>
      <li class="status-item">
        <i class="dot dot-orange" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Waiting for Second Payment">Waiting for Second Payment</span>
        <strong class="count" id="waitingSecondCount">0</strong>
      </li>
      <li class="status-item">
        <i class="dot dot-purple" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Waiting for Balance">Waiting for Balance</span>
        <strong class="count" id="waitingBalanceCount">0</strong>
      </li>
      <li class="status-item">
        <i class="dot dot-red" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Payment Rejected">Payment Rejected</span>
        <strong class="count" id="rejectedCount">0</strong>
      </li>
    </ul>
  </article>

  <article class="card">
    <div>
      <h2 class="card-title" data-lan-eng="Inquiry Status">Inquiry Status</h2>
      <a href="user-inquiry-list.php" class="card-link" data-lan-eng="See All">See All →</a>
    </div>
    <ul class="status-list">
      <li class="status-item">
        <i class="dot dot-blue" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Unanswered">Unanswered</span>
        <strong class="count" id="unansweredCount"><span>0</span></strong>
      </li>
      <li class="status-item">
        <i class="dot dot-green" aria-hidden="true"></i>
        <span class="label" data-lan-eng="Processing">Processing</span>
        <strong class="count" id="processingCount"><span>0</span></strong>
      </li>
    </ul>
  </article>

</div>

<!-- Today Travel Itinerary -->
<div class="card-panel jw-mgt32">
  <h2 class="card-title" data-lan-eng="Today's Travel Itinerary">Today's Travel Itinerary</h2>
  <p class="card-subtitle"><strong><span id="todayBookingsCount">0</span></strong></p>

  <div class="tableA-scroll">
    <div class="jw-tableA typeB">
      <table>
        <colgroup>
          <col style="width:60px;"><!-- No -->
          <col><!--  -->
          <col style="width:220px;"><!--   -->
          <col style="width:120px;"><!--   -->
          <col style="width:100px;"><!--   -->
          <col style="width:140px;"><!--   -->
        </colgroup>
        <thead>
          <tr>
            <th>No</th>
            <th data-lan-eng="Product Name">Product Name</th>
            <th data-lan-eng="Travel period">Travel period</th>
            <th data-lan-eng="Customer Type">Customer Type</th>
            <th data-lan-eng="Number of people">Number of people</th>
            <th data-lan-eng="Assignment Guide">Assignment Guide</th>
          </tr>
        </thead>
        <tbody id="todayBookingsTableBody">
          <tr>
            <td colspan="6" class="is-center" style="padding: 40px;"> ...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Sales Statistics -->
<div class="card-panel jw-mgt32 sales-range">

  <div class="card-panel-header">
    <h2 class="card-title" data-lan-eng="Sales Statistics">Sales Statistics</h2>
  </div>

  <div class="card-panel-body">

    <div class="card-panel-stats">
      <div class="info-text" data-lan-eng="Total sales amount (₱)">Total sales amount (₱)</div>
      <div class="info-text2" id="totalSalesAmount">0</div>
    </div>

    <div class="card-panel-filters">
      <h3 class="card-subtitle2" data-lan-eng="Select period">Select period</h3>
      <div class="jw-cols jw-gap10 jw-mgt16">
        <label class="jw-radio typeA">
          <input type="radio" name="salesPeriod" value="daily" checked>
          <p class="text" data-lan-eng="Daily">Daily</p>
        </label>
        <label class="jw-radio typeA">
          <input type="radio" name="salesPeriod" value="weekly">
          <p class="text" data-lan-eng="Weekly">Weekly</p>
        </label>
        <label class="jw-radio typeA">
          <input type="radio" name="salesPeriod" value="monthly">
          <p class="text" data-lan-eng="Monthly">Monthly</p>
        </label>
        <label class="jw-radio typeA">
          <input type="radio" name="salesPeriod" value="yearly">
          <p class="text" data-lan-eng="Yearly">Yearly</p>
        </label>
      </div>
      <div class="input-box jw-mgt16">
        <input id="salesDateRange" name="salesDateRange" readonly>
      </div>
    </div>

  </div>

  <div class="card-panel-chart jw-mgt30">
    <div id="myChart" class="chart"></div>
  </div>

</div>

<!-- Sales Status by Product -->
<div class="card-panel jw-mgt32 product-sales-range">
  <h2 class="card-title" data-lan-eng="Sales Status by Product">Sales Status by Product</h2>
  <h3 class="card-subtitle2 jw-mgt44" data-lan-eng="Select period">Select period</h3>

  <div class="jw-cols jw-gap10 jw-mgt16">
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="daily" checked>
      <p class="text" data-lan-eng="Daily">Daily</p>
    </label>
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="weekly">
      <p class="text" data-lan-eng="Weekly">Weekly</p>
    </label>
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="monthly">
      <p class="text" data-lan-eng="Monthly">Monthly</p>
    </label>
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="yearly">
      <p class="text" data-lan-eng="Yearly">Yearly</p>
    </label>
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="all">
      <p class="text" data-lan-eng="All">All</p>
    </label>
    <label class="jw-radio typeA">
      <input type="radio" name="productSalesPeriod" value="custom" id="productSalesPeriodCustom">
      <p class="text" data-lan-eng="Select Period">Select Period</p>
    </label>
  </div>
  <div class="input-box jw-mgt16 jw-w400" id="productSalesDateRangeWrap">
    <input id="productSalesDateRange" name="productSalesDateRange" readonly>
  </div>
  <div class="jw-cols jw-gap10 jw-mgt16" id="productSalesCustomDateWrap" style="display: none;">
    <div class="input-box jw-w200">
      <input type="date" id="productSalesStartDate" name="productSalesStartDate" value="">
    </div>
    <span class="jw-mgt8">~</span>
    <div class="input-box jw-w200">
      <input type="date" id="productSalesEndDate" name="productSalesEndDate" value="">
    </div>
    <button type="button" class="jw-button typeB" id="productSalesApplyBtn" data-lan-eng="Apply">Apply</button>
  </div>
  <div class="info-text jw-mgt44" data-lan-eng="Total number of sales">Total number of sales</div>
  <div class="info-text2" id="totalProductSalesCount">0</div>


  <div class="sales-info jw-mgt32">
    <div class="sales-chart">
      <div style="padding: 40px; text-align: center; color: #999;"> ...</div>
    </div>
    <div class="sales-product">
      <table class="jw-tableA typeB">
        <colgroup>
          <col style="width:60px;"> <!-- No -->
          <col> <!--  -->
          <col style="width:120px;"> <!--  -->
          <col style="width:120px;"> <!--  -->
          <col style="width:120px;"> <!--  -->
          <col style="width:160px;"> <!--  -->
        </colgroup>
        <thead>
          <tr>
            <th>No</th>
            <th data-lan-eng="Product Name">Product Name</th>
            <th data-lan-eng="Views">Views</th>
            <th data-lan-eng="Number of reservations">Number of reservations</th>
            <th data-lan-eng="Reservation rate">Reservation rate</th>
            <th data-lan-eng="Sales amount">Sales amount</th>
          </tr>
        </thead>
        <tbody id="productSalesTableBody">
          <tr>
            <td colspan="6" class="is-center" style="padding: 40px;"> ...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>





<!-- Today's itinerary, sales charts etc. — keep your existing markup here -->

<?php
$pageContent = ob_get_clean();


/* ── 2. Inline page script ────────────────────────────
   RULE: all logic lives inside window.__pageInit.
   The router calls this after every content swap.
   The auto-run block at the bottom handles the first
   direct page load.
   ─────────────────────────────────────────────────── */
ob_start();
?>

<script>
  window.__pageInit = async function initOverview() {

    /* ── Date ─────────────────────────────────────────── */
    const dateEl = document.getElementById('currentDate');
    if (dateEl) {
      const today = new Date();
      dateEl.textContent = today.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
      dateEl.setAttribute('datetime', today.toISOString().split('T')[0]);
    }

    /* ── Session check ────────────────────────────────── */
    try {
      const res = await fetch('../backend/api/check-session.php', {
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.authenticated) {
        window.location.href = '../index.html';
        return;
      }
    } catch {
      window.location.href = '../index.html';
      return;
    }

    /* ── Load data ────────────────────────────────────── */
    await loadOverviewData();

    /* ── Sales period radios ──────────────────────────── */
    const salesDateInput = document.getElementById('salesDateRange');
    document.querySelectorAll('input[name="salesPeriod"]').forEach(radio => {
      radio.addEventListener('change', () => {
        if (salesDateInput) salesDateInput.value = calculateDateRange(radio.value);
        loadSalesData(radio.value);
      });
    });

    /* ── Product period radios ────────────────────────── */
    const productDateInput = document.getElementById('productSalesDateRange');
    const productDateWrap = document.getElementById('productSalesDateRangeWrap');
    const productCustomWrap = document.getElementById('productSalesCustomDateWrap');

    document.querySelectorAll('input[name="productSalesPeriod"]').forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'custom') {
          productCustomWrap && (productCustomWrap.style.display = 'flex');
          productDateWrap && (productDateWrap.style.display = 'none');
        } else {
          productCustomWrap && (productCustomWrap.style.display = 'none');
          productDateWrap && (productDateWrap.style.display = 'block');
          if (productDateInput) productDateInput.value = calculateDateRange(radio.value);
          loadProductSalesData(radio.value);
        }
      });
    });

    document.getElementById('productSalesApplyBtn')
      ?.addEventListener('click', () => {
        const start = document.getElementById('productSalesStartDate')?.value;
        const end = document.getElementById('productSalesEndDate')?.value;
        if (start && end) loadProductSalesData('custom', start, end);
      });

    /* ── Helpers ──────────────────────────────────────── */
    function escapeHtml(str) {
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }

    function decodeHtmlEntities(str) {
      if (!str) return str;
      const t = document.createElement('textarea');
      t.innerHTML = str;
      return t.value;
    }

    function calculateDateRange(period) {
      const today = new Date();
      const y = today.getFullYear(),
        m = today.getMonth(),
        d = today.getDate();
      let s, e;
      switch (period) {
        case 'daily':
          s = new Date(y, m, d);
          e = new Date(y, m, d);
          break;
        case 'weekly': {
          const diff = (today.getDay() + 6) % 7;
          s = new Date(y, m, d - diff);
          e = new Date(y, m, d - diff + 6);
        }
        break;
        case 'monthly':
          s = new Date(y, m, 1);
          e = new Date(y, m + 1, 0);
          break;
        case 'yearly':
          s = new Date(y, 0, 1);
          e = new Date(y, 11, 31);
          break;
        default:
          return ' ';
      }
      const fmt = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      return `${fmt(s)} ~ ${fmt(e)}`;
    }

    async function loadOverviewData() {
      try {
        const res = await fetch('../backend/api/overview.php', {
          credentials: 'same-origin'
        });
        const result = await res.json();
        if (result.success === false) return;
        const data = result.data || result;

        if (data.bookingStatus) {
          const set = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.textContent = v ?? 0;
          };
          set('pendingCount', data.bookingStatus.pending);
          set('waitingDownCount', data.bookingStatus.waitingDown);
          set('waitingSecondCount', data.bookingStatus.waitingSecond);
          set('waitingBalanceCount', data.bookingStatus.waitingBalance);
          set('rejectedCount', data.bookingStatus.rejected);
        }

        if (data.inquiryStatus) {
          const u = document.querySelector('#unansweredCount span');
          const p = document.querySelector('#processingCount span');
          if (u) u.textContent = data.inquiryStatus.unanswered ?? 0;
          if (p) p.textContent = data.inquiryStatus.processing ?? 0;
        }

        /* today bookings, product sales — keep your existing render logic here */

      } catch (err) {
        console.error('[overview] loadOverviewData:', err);
      }
    }

    async function loadSalesData(period, startDate = null, endDate = null) {
      /* keep your existing implementation — move it here verbatim */
    }

    async function loadProductSalesData(period, startDate = null, endDate = null) {
      /* keep your existing implementation — move it here verbatim */
    }
  };

  /* ── Auto-run on direct page load ────────────────────
     On a swap the router calls window.__pageInit() itself.
     On a direct URL load DOMContentLoaded hasn't fired yet
     (or may have already fired if the script is deferred).
     ─────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.__pageInit);
  } else {
    window.__pageInit();
  }
</script>
<?php
$pageContent .= ob_get_clean();

include '../inc/layout.php';
