<!-- inc/nav_super.php
     Accordion uses inline style="display:grid;grid-template-rows:0fr"
     on .nav-sub-wrap so legacy CSS files cannot override the grid trick.
     JS toggles is-open on .nav-item — sidebar.css then overrides the
     inline grid-template-rows via !important on the open state.
-->

<ul class="nav-list">

  <!-- Dashboard -->
  <li class="nav-item" data-menu="dashboard">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.46289 12.5107C7.51338 12.6174 8.33284 13.5044 8.33301 14.583V16.667C8.33283 17.8174 7.40048 18.75 6.25 18.75H2.08301C1.00435 18.7498 0.117255 17.9295 0.0107422 16.8789L0 16.667V14.583C0.000175588 13.4327 0.932673 12.5002 2.08301 12.5H6.25L6.46289 12.5107ZM16.8789 8.34473C17.8597 8.44416 18.6398 9.22332 18.7393 10.2041L18.75 10.417V16.667C18.7498 17.7456 17.9295 18.6327 16.8789 18.7393L16.667 18.75H12.5C11.3495 18.75 10.4172 17.8174 10.417 16.667V10.417L10.4277 10.2041C10.5272 9.22342 11.3065 8.44429 12.2871 8.34473L12.5 8.33301H16.667L16.8789 8.34473ZM2.08301 14.0625C1.79562 14.0627 1.56268 14.2956 1.5625 14.583V16.667C1.56268 16.9544 1.79562 17.1873 2.08301 17.1875H6.25C6.53754 17.1875 6.77033 16.9545 6.77051 16.667V14.583C6.77033 14.2955 6.53754 14.0625 6.25 14.0625H2.08301ZM12.5 9.89551C12.2124 9.89551 11.9795 10.1293 11.9795 10.417V16.667C11.9797 16.9545 12.2125 17.1875 12.5 17.1875H16.667C16.9544 17.1873 17.1873 16.9544 17.1875 16.667V10.417C17.1875 10.1295 16.9545 9.89568 16.667 9.89551H12.5ZM6.46289 0.0107422C7.51338 0.117364 8.33284 1.00443 8.33301 2.08301V8.33301C8.33301 9.4836 7.40059 10.417 6.25 10.417H2.08301C1.00435 10.4168 0.117255 9.5965 0.0107422 8.5459L0 8.33301V2.08301C0.000175979 0.932673 0.932673 0.000175845 2.08301 0H6.25L6.46289 0.0107422ZM2.08301 1.5625C1.79562 1.56268 1.56268 1.79562 1.5625 2.08301V8.33301C1.5625 8.62055 1.79551 8.85432 2.08301 8.85449H6.25C6.53765 8.85449 6.77051 8.62066 6.77051 8.33301V2.08301C6.77033 1.79551 6.53754 1.5625 6.25 1.5625H2.08301ZM16.667 0L16.8789 0.0107422C17.8597 0.110177 18.6398 0.890311 18.7393 1.87109L18.75 2.08301V4.16699C18.7498 5.24565 17.9295 6.13275 16.8789 6.23926L16.667 6.25H12.5C11.3495 6.25 10.4172 5.31744 10.417 4.16699V2.08301L10.4277 1.87109C10.5272 0.890389 11.3064 0.110278 12.2871 0.0107422L12.5 0H16.667ZM12.5 1.5625C12.2125 1.5625 11.9797 1.79551 11.9795 2.08301V4.16699C11.9797 4.45449 12.2125 4.6875 12.5 4.6875H16.667C16.9544 4.68732 17.1873 4.45438 17.1875 4.16699V2.08301C17.1873 1.79562 16.9544 1.56268 16.667 1.5625H12.5Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Dashboard">Dashboard</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="overview" href="../super/overview.php" data-lan-eng="Operating Status">Operating Status</a>
        <a class="side-link" data-page="reservation-status" href="../super/reservation-status.php" data-lan-eng="Reservation Status">Reservation Status</a>
        <a class="side-link" data-page="email-notification-logs" href="../super/email-notification-logs.php" data-lan-eng="Email Logs">Email Logs</a>
      </div></div>
    </div>
  </li>

  <!-- Member Management -->
  <li class="nav-item" data-menu="member-management">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.417 12.5C12.718 12.5002 14.583 14.3659 14.583 16.667V18.75H13.0205V16.667C13.0205 15.2289 11.8551 14.0627 10.417 14.0625H4.16699C2.72875 14.0625 1.5625 15.2288 1.5625 16.667V18.75H0V16.667C0 14.3658 1.86581 12.5 4.16699 12.5H10.417ZM7.29199 0C10.1682 0.00017606 12.4998 2.33178 12.5 5.20801C12.5 8.08438 10.1683 10.4168 7.29199 10.417C4.41551 10.417 2.08301 8.08449 2.08301 5.20801C2.08318 2.33167 4.41562 0 7.29199 0ZM7.29199 1.5625C5.27856 1.5625 3.64568 3.19462 3.64551 5.20801C3.64551 7.22155 5.27845 8.85449 7.29199 8.85449C9.30538 8.85432 10.9375 7.22144 10.9375 5.20801C10.9373 3.19473 9.30527 1.56268 7.29199 1.5625Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Member Management">Member Management</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="member-list" href="../super/member-list.php" data-lan-eng="Member List">Member List</a>
        <a class="side-link" data-page="b2b-customer-list,b2b-customer-detail" href="../super/b2b-customer-list.php" data-lan-eng="B2B Customer List">B2B Customer List</a>
        <a class="side-link" data-page="agent-list,agent-detail,agent-registration" href="../super/agent-list.php" data-lan-eng="Agent List">Agent List</a>
        <a class="side-link" data-page="guide-list,guide-detail,guide-registration" href="../super/guide-list.php" data-lan-eng="Guide List">Guide List</a>
      </div></div>
    </div>
  </li>

  <!-- Reservation Management -->
  <li class="nav-item" data-menu="reservation-management">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="19" height="21" viewBox="0 0 19 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 0C13.0753 2.57623e-07 13.542 0.466696 13.542 1.04199V2.08398H16.667C17.8174 2.08416 18.75 3.01651 18.75 4.16699V18.75C18.75 19.8288 17.9296 20.7157 16.8789 20.8223L16.667 20.834H2.08301L1.87109 20.8223C0.89031 20.7228 0.110177 19.9437 0.0107422 18.9629L0 18.75V4.16699C0 3.01651 0.932564 2.08416 2.08301 2.08398H5.20801V1.04199C5.20801 0.466696 5.6747 0 6.25 0C6.8253 0 7.29199 0.466696 7.29199 1.04199V2.08398H11.458V1.04199C11.458 0.466696 11.9247 0 12.5 0ZM1.5625 9.375V18.75C1.5625 19.0375 1.79551 19.2713 2.08301 19.2715H16.667C16.9545 19.2713 17.1875 19.0375 17.1875 18.75V9.375H1.5625ZM2.08301 3.64648C1.79551 3.64666 1.5625 3.87945 1.5625 4.16699V7.8125H17.1875V4.16699C17.1875 3.87945 16.9545 3.64666 16.667 3.64648H13.542V4.16699C13.542 4.74229 13.0753 5.20898 12.5 5.20898C11.9247 5.20898 11.458 4.74229 11.458 4.16699V3.64648H7.29199V4.16699C7.29199 4.74229 6.8253 5.20898 6.25 5.20898C5.6747 5.20898 5.20801 4.74229 5.20801 4.16699V3.64648H2.08301Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Reservation Management">Reservation Management</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="b2b-pending-list" href="../super/b2b-pending-list.php" data-lan-eng="B2B Pending List">B2B Pending List</a>
        <a class="side-link" data-page="b2b-booking-list,b2b-booking-detail" href="../super/b2b-booking-list.php" data-lan-eng="B2B Reservation List">B2B Reservation List</a>
        <a class="side-link" data-page="rooming-list" href="../super/rooming-list.php" data-lan-eng="Rooming List">Rooming List</a>
        <a class="side-link" data-page="travel-document-list,travel-document-detail" href="../super/travel-document-list.php" data-lan-eng="Travel Document">Travel Document</a>
      </div></div>
    </div>
  </li>

  <!-- Sales Management -->
  <li class="nav-item" data-menu="sales-management">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="20" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19.0098 0C19.251 0.0380859 19.4365 0.126953 19.7197 0.458984C19.791 0.78125V5.98926C19.791 6.42073 19.4412 6.7705 19.0098 6.77051C18.5783 6.77051 18.2285 6.42073 18.2285 5.98926V2.84668L11.7832 10.1514C11.5289 10.4396 11.1027 10.5002 10.7783 10.2939L5.6582 7.03613L1.40527 12.708C1.14633 13.0529 0.656593 13.1231 0.311523 12.8643C-0.0333031 12.6053 -0.102407 12.1156 0.15625 11.7705L4.84375 5.52051L5.8877 5.33008L11.0527 8.61719L17.2773 1.5625H13.8018C13.3704 1.5625 13.0205 0.349778 13.8018 0H19.0098Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Sales Management">Sales Management</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="sales-date" href="../super/sales-date.php" data-lan-eng="Sales Dashboard">Sales Dashboard</a>
        <a class="side-link" data-page="sales-product" href="../super/sales-product.php" data-lan-eng="Sales by product">Sales by product</a>
        <a class="side-link" data-page="monthly-invoice" href="../super/monthly-invoice.php" data-lan-eng="Monthly Invoice">Monthly Invoice</a>
      </div></div>
    </div>
  </li>

  <!-- Product Management -->
  <li class="nav-item" data-menu="product-management">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.71501 7.86219L12.0483 4.13638C12.333 3.99176 12.6696 3.99176 12.9543 4.13638L20.2876 7.86219C20.6232 8.03271 20.8346 8.37726 20.8346 8.75372V17.2418C20.8346 17.5982 20.645 17.9277 20.3368 18.1066L13.0034 22.3647C12.693 22.545 12.3097 22.545 11.9992 22.3647L4.66583 18.1066C4.35764 17.9277 4.16797 17.5982 4.16797 17.2418V8.75372C4.16797 8.37726 4.37939 8.03271 4.71501 7.86219Z" class="stroke" fill="none" stroke-width="1.5" stroke-linecap="round"/><path d="M4.16797 8.07422L12.5013 12.2409" class="stroke" fill="none" stroke-width="1.5" stroke-linecap="round"/><path d="M19.7917 8.07422L12.5 12.2409" class="stroke" fill="none" stroke-width="1.5" stroke-linecap="round"/><path d="M12.5 21.6133V12.2383" class="stroke" fill="none" stroke-width="1.5" stroke-linecap="round"/><path d="M15.8091 10.5654C16.1796 10.7506 16.6301 10.6004 16.8154 10.2299C17.0006 9.85946 16.8504 9.40895 16.4799 9.22371L16.1445 9.89453L15.8091 10.5654ZM8.33203 5.98828L7.99662 6.6591L15.8091 10.5654L16.1445 9.89453L16.4799 9.22371L8.66744 5.31746L8.33203 5.98828Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Product Management">Product Management</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="product-list,product-update,product-detail" href="../super/product-list.php" data-lan-eng="Product List">Product List</a>
        <a class="side-link" data-page="product-registration" href="../super/product-registration.php" data-lan-eng="Product Registration">Product Registration</a>
        <a class="side-link" data-page="sight-list,sight-registration" href="../super/sight-list.php" data-lan-eng="Sight Management">Sight Management</a>
        <a class="side-link" data-page="inventory-management" href="../super/inventory-management.php" data-lan-eng="Inventory Management">Inventory Management</a>
        <a class="side-link" data-page="template-list,template-detail,template-registration" href="../super/template-list.php" data-lan-eng="Template List">Template List</a>
        <a class="side-link" data-page="category-management" href="../super/category-management.php" data-lan-eng="Category Management">Category Management</a>
        <a class="side-link" data-page="option-management" href="../super/option-management.php" data-lan-eng="Option Management">Option Management</a>
        <a class="side-link" data-page="sale-management" href="../super/sale-management.php" data-lan-eng="Sale Management">Sale Management</a>
      </div></div>
    </div>
  </li>

  <div class="nav-divider"></div>

  <!-- Inquiries — direct link, no sub-menu -->
  <li class="nav-item" data-menu="message-management">
    <a class="nav-btn" href="../super/agent-message-list.php" data-page="agent-message-list">
      <i class="nav-icon" aria-hidden="true"><svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.583 0C15.7335 0 16.6658 0.932606 16.666 2.08301V10.8799C16.6659 12.0303 15.7335 12.9629 14.583 12.9629H4.82129L4.72949 12.9668C4.51884 12.9854 4.31789 13.0678 4.1543 13.2041L1.70898 15.2422L1.57715 15.3369C0.949886 15.7191 0.1199 15.33 0.0117188 14.6035L0 14.4434V2.08301C0.000225362 0.932606 0.932554 0 2.08301 0H14.583ZM2.08301 1.5625C1.7955 1.5625 1.56273 1.79555 1.5625 2.08301V13.3301L3.1543 12.0039L3.33496 11.8662C3.76969 11.5641 4.28829 11.4004 4.82129 11.4004H14.583C14.8706 11.4004 15.1034 11.1674 15.1035 10.8799V2.08301C15.1033 1.79555 14.8705 1.5625 14.583 1.5625H2.08301Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Inquiries">Inquiries</span>
      <span class="nav-unread-badge" id="inquiryUnreadBadge" style="display:none;">0</span>
    </a>
  </li>

  <!-- Announcements -->
  <li class="nav-item" data-menu="announcements">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.5 0C17.3284 0 18 0.671573 18 1.5V12C18 12.8284 17.3284 13.5 16.5 13.5H13.5L9 18V13.5H1.5C0.671573 13.5 0 12.8284 0 12V1.5C0 0.671573 0.671573 0 1.5 0H16.5ZM16.5 1.5H1.5V12H10.5V14.379L12.879 12H16.5V1.5ZM13.5 6.75V8.25H4.5V6.75H13.5ZM13.5 3.75V5.25H4.5V3.75H13.5Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Announcements">Announcements</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="announcements-list" href="../super/announcements-list.php" data-lan-eng="Announcements List">Announcements List</a>
      </div></div>
    </div>
  </li>

  <!-- Shopping Management -->
  <li class="nav-item" data-menu="shopping-management">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.33301 17.4167C6.97734 17.4167 7.49967 16.8943 7.49967 16.25C7.49967 15.6057 6.97734 15.0833 6.33301 15.0833C5.68868 15.0833 5.16634 15.6057 5.16634 16.25C5.16634 16.8943 5.68868 17.4167 6.33301 17.4167Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.833 17.4167C15.4773 17.4167 15.9997 16.8943 15.9997 16.25C15.9997 15.6057 15.4773 15.0833 14.833 15.0833C14.1887 15.0833 13.6663 15.6057 13.6663 16.25C13.6663 16.8943 14.1887 17.4167 14.833 17.4167Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M0.5 0.916672H3.66667L5.89 11.2617C5.96572 11.6282 6.16727 11.9568 6.46009 12.1908C6.75291 12.4247 7.11856 12.5492 7.49333 12.5417H14.3667C14.7414 12.5492 15.1071 12.4247 15.3999 12.1908C15.6927 11.9568 15.8943 11.6282 15.97 11.2617L17.1667 4.83334H4.83333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Shopping Management">Shopping Management</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="shop-category-list" href="../shop/category-list.php" data-lan-eng="Category List">Category List</a>
        <a class="side-link" data-page="shop-product-list,shop-product-form" href="../shop/product-list.php" data-lan-eng="Product List">Product List</a>
        <a class="side-link" data-page="shop-order-list,shop-order-detail" href="../shop/order-list.php" data-lan-eng="Order List">Order List</a>
      </div></div>
    </div>
  </li>

  <!-- Site Settings -->
  <li class="nav-item" data-menu="site-settings">
    <button class="nav-btn" type="button">
      <i class="nav-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.3049 8.88326C15.1689 8.73121 15.0939 8.53573 15.0939 8.33333C15.0939 8.13094 15.1689 7.93546 15.3049 7.78341L16.3906 6.58357C16.5103 6.45248 16.5846 6.28753 16.6029 6.11239C16.6212 5.93725 16.5825 5.76092 16.4924 5.6087L14.796 2.72576C14.7068 2.57372 14.5711 2.4532 14.4081 2.38138C14.2452 2.30957 14.0633 2.29012 13.8884 2.32582L12.2937 2.64244C12.0908 2.68363 11.8796 2.65043 11.6999 2.54912C11.5202 2.44781 11.3844 2.28539 11.3183 2.09252L10.8008 0.567723C10.7439 0.402228 10.6355 0.258486 10.4909 0.156814C10.3463 0.0551416 10.1729 0.000682 9.99503 0.00113H6.60212C6.41715 -0.00835 6.23411 0.0419 6.08094 0.144209C5.92778 0.246518 5.81292 0.395262 5.7539 0.567723L5.27889 2.09252C5.21273 2.28539 5.07699 2.44781 4.89728 2.54912C4.71758 2.65043 4.50634 2.68363 4.30343 2.64244L2.66636 2.32582C2.33156 2.3285 2.18062 2.39967 1.81813 2.72576L0.121679 5.6087C0.029358 5.75922 -0.012153 5.93456 0.003082 6.10966C0.018317 6.28475 0.089516 6.45062 0.206501 6.58357L1.28375 7.78341C1.41973 7.93546 1.49473 8.13094 1.49473 8.33333C1.49473 8.53573 1.41973 8.73121 1.28375 8.88326L0.206501 10.0831L1.81813 13.9409L3.98111 12.3828C4.57505 12.2601 5.21055 12.3621 5.75 12.6692C6.28945 12.9764 6.71227 13.4675 6.90748 14.0492L7.21284 14.9991H9.3843L9.70663 14.0659C9.90184 13.4842 10.3077 12.9931 10.8471 12.6859C11.3866 12.3787 12.0221 12.2768 12.633 12.3994L13.6339 12.5994L14.7196 10.7497L14.0411 9.99977C13.6285 9.54254 13.4006 8.95268 13.4006 8.34167C13.4006 7.73065 13.6285 7.1408 14.0411 6.68356L14.7196 5.93366L13.6339 4.08391L12.633 4.28388C12.0221 4.40655 11.3866 4.30462 10.8471 3.99742C10.3077 3.69023 9.90184 3.19916 9.70663 2.61744L9.3843 1.66757H7.21284L6.89052 2.60911C6.6953 3.19083 6.28945 3.6819 5.75 3.98909C5.21055 4.29628 4.57505 4.39822 3.96414 4.27555L2.96323 4.07558L1.87751 5.90866L2.55609 6.65856C2.97338 7.11685 3.20407 7.71014 3.20407 8.325C3.20407 8.93986 2.97338 9.53315 2.55609 9.99144L1.87751 10.7413L2.9802 12.5828Z"/></svg></i>
      <span class="nav-btn-label" data-lan-eng="Site Settings">Site Settings</span>
      <i class="nav-chevron" aria-hidden="true"></i>
    </button>
    <div class="nav-sub-wrap" style="display:grid;grid-template-rows:0fr;transition:grid-template-rows 230ms cubic-bezier(0.22,1,0.36,1);">
      <div class="nav-sub" style="overflow:hidden;"><div class="nav-sub-inner">
        <a class="side-link" data-page="popup-management" href="../super/popup-management.php" data-lan-eng="Popup Management">Popup Management</a>
        <a class="side-link" data-page="banner-management" href="../super/banner-management.php" data-lan-eng="Banner Management">Banner Management</a>
        <a class="side-link" data-page="notice" href="../super/notice.php" data-lan-eng="Announcements">Announcements</a>
        <a class="side-link" data-page="terms" href="../super/terms.php" data-lan-eng="Terms of Use">Terms of Use</a>
        <a class="side-link" data-page="company-information" href="../super/company-information.php" data-lan-eng="Company Information">Company Information</a>
        <a class="side-link" data-page="partner-management" href="../super/partner-management.php" data-lan-eng="Partner Management">Partner Management</a>
        <a class="side-link" data-page="login-history" href="../super/login-history.php" data-lan-eng="Login History">Login History</a>
        <a class="side-link" data-page="status-history" href="../super/status-history.php" data-lan-eng="Status Change History">Status Change History</a>
        <a class="side-link" data-page="menu-permission" href="../super/menu-permission.php" data-lan-eng="Menu Permission">Menu Permission</a>
      </div></div>
    </div>
  </li>

</ul>