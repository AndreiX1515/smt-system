

<?php
  date_default_timezone_set('Asia/Taipei');
  $current_date = date('D, F d, Y'); 
?>

<div class="sidebar" id="sidebar">
  <div class="main-sidebar">

    <div class="logo">
      <img src="../Assets/Logos/logo.png" alt="Smart Travel Logo">
    </div>

    <!-- <div class="dashboard-title">Menu</div> -->
    
    <!-- <a href="#" class="page-button add-booking mb-1 my-0" data-page-name="Add Booking - Packages"> 
      <i class="fa-solid fa-user-plus"></i> <span> Add Booking </span>
    </a> -->

    <a href="#" class="page-button home my-0 mb-1 " data-page-name="Dashboard"> 
      <i class="fas fa-home"></i> <span> Home </span> 
    </a>

    <!-- <a href="../Agent Section/agent-FIT.php" class="page-button add-FIT mb-1 my-0" data-page-name="Add Booking - F.I.T">
      <i class="fa-solid fa-user-plus"></i> <span> Add F.I.T </span>
    </a> -->

    <div class="section-title manage-accounts" onclick="toggleSubMenu('transactiontable-submenu')">
      Manage Tickets <span class="chevron-icon fas fa-chevron-down"></span>
    </div>

      <div class="submenu open" id="transactiontable-submenu">
        <a href="../Admin Section/admin-manageTickets.php" class="page-button" data-page-name="Manage Tickets">
          <i class="fas fa-user"></i> Tickets
        </a>

        <!-- <a href="../Agent Section/agent-FIT-table.php" class="page-button my-0" data-page-name="F.I.T - Transactions Table" style="font-size: 14px;">
          <i class="fas fa-file-invoice"></i> F.I.T 
        </a>  -->
    </div>
  
    <div class="section-title manage-accounts" onclick="toggleSubMenu('transactiontable-submenu')">
      Manage Accounts <span class="chevron-icon fas fa-chevron-down"></span>
    </div>

      <div class="submenu open" id="transactiontable-submenu">
        <a href="../Admin Section/admin-manageAgent.php" class="page-button" data-page-name="Packages - Transactions table">
          <i class="fas fa-user"></i> Agent
        </a>

        <!-- <a href="../Admin Section/admin-manageClient.php" class="page-button" data-page-name="Packages - Transactions table">
          <i class="fas fa-user"></i> Client
        </a> -->

        <a href="../Admin Section/admin-manageEmployee.php" class="page-button" data-page-name="Packages - Transactions table">
          <i class="fas fa-user"></i> Employee
        </a>

        

        <!-- <a href="../Agent Section/agent-FIT-table.php" class="page-button my-0" data-page-name="F.I.T - Transactions Table" style="font-size: 14px;">
          <i class="fas fa-file-invoice"></i> F.I.T 
        </a>  -->
      </div>

    <!-- <div class="section-title" onclick="toggleSubMenu('operational-submenu')">
      Reports <span class="chevron-icon fas fa-chevron-down"></span>
    </div>

    <div class="submenu open" id="operational-submenu">
      <a href="../Agent Section/agent-itenerary.php" class="page-button" data-page-name="Itinerary">
        <i class="fas fa-map"></i> Itinerary
      </a>

      <a href="../Agent Section/agent-soa2.php" class="page-button" data-page-name="Statement of Accounts (SOA) - Packages">
        <i class="fas fa-file-invoice-dollar"></i> SOA - Packages
      </a>

      <a href="../Agent Section/agent-fitSOA - rename.php" class="page-button" data-page-name="Statement of Accounts (SOA) - F.I.T">
        <i class="fas fa-file-invoice-dollar"></i> SOA - F.I.T
      </a>

      <a href="../Agent Section/agent-ticket.php" class="page-button" data-page-name="Ticket">
        <i class="fas fa-ticket"></i> Ticket
      </a>

      <a href="../Agent Section/agent-transactions.php" class="page-button" data-page-name="Voucher">
        <i class="fas fa-gift"></i> Voucher
      </a>
    </div> -->

  </div>

  <?php 
  // echo $fullName; 
  ?>
  <?php 
  // echo $branchName; 
  ?>
  
  <div class="profile-wrapper">

    <div class="profile-section">
      <div class="profile-icon">
        <i class="fas fa-user-circle"></i>
      </div>
      <div class="profile-details">
        <h6 class="profile-name"></h>
        <p class="profile-role mt-1"> <span> </span></p>
      </div>
    </div>


    <div class="logout-wrapper">
      <a href="#" class="page-button logout" data-page-name="" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
      </a>
    </div>

  </div>
</div>

<?php 
// include '../Agent Section/includes/logoutViewPassModal.php'; 
?>

<script>
function toggleSubMenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const sectionTitle = submenu.previousElementSibling;
    const chevron = sectionTitle.querySelector('.chevron-icon'); 

    // Check if the submenu is already open
    const isOpen = submenu.classList.contains('open');

    // Toggle the submenu: If it's open, close it; If it's closed, open it
    if (isOpen) {
        submenu.classList.remove('open');
        chevron.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
    }
}

// Optionally: Automatically open the submenu when the page loads (Transaction submenu is open by default in this case)
document.addEventListener('DOMContentLoaded', function () {
    const transactionSubmenu = document.getElementById('transactiontable-submenu');
    const transactionChevron = document.querySelector('#transactiontable-submenu').previousElementSibling.querySelector('.chevron-icon');

    // Set the default opened submenu (Transaction)
    transactionSubmenu.classList.add('open');
    transactionChevron.style.transform = 'rotate(180deg)';
});
</script>
