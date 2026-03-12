<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <?php include "../../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../../Agent Section/assets/testing/agent-dashboard.css?v=<?php echo time(); ?>">

</head>

<body>

  <div class="parent">

    <div class="sidebar">
      <button id="closeSidebarBtn" class="hamburger-btn" aria-label="Close Sidebar">
        <i class="fas fa-chevron-left"></i>
      </button>

      <div class="sidebar-header">
        
        <div class="sidebar-header-logo">
          <div class="sidebar-header-logo">
            <div class="logo-wrapper">
              <img src="../../Assets/Logos/logo-tab.png" alt="" class="logo">
            </div>

            <div class="logo-text">
              <h5>SMART TRAVEL</h5>
            </div>
          </div>
        </div>

      </div>


    </div>

    <div class="main-content-container">

      <div class="navbar">

        <div class="navbar-button-wrapper">
          <button id="toggleSidebarBtn" class="hamburger-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
          </button>
        </div>

        <div class="navbar-title-wrapper">
          <div class="navbar-title">
            <h5>Dashboard</h5> <!-- not h6 -->
          </div>
        </div>

      </div>

      <div class="main-content">
        <div class="content-wrapper">

          <div class="header-counts">

            <div class="card">
              <div class="card-header">
                <div class="primary-pill">
                  <h6 class="white-pill">Monthly Transaction</h6>
                </div>

                <div class="accent-pill">
                  <h6 class="accent-pill"></h6>
                </div>
              </div>

              <div class="card-content">

                <div class="row">

                  <div class="columns col-md-6">

                    <div class="card-icon icon-blue">
                      <i class="fas fa-calendar-alt"></i>
                    </div>

                    <div class="side-content">
                      <div class="side-content-count">
                        <h5 class="count-number">10</h5>
                      </div>

                      <div class="side-content-label">
                        <p class="count-label">TOTAL</p>
                      </div>
                    </div>


                  </div>

                  <div class="columns col-md-6">

                    <div class="card-icon icon-blue">
                      <i class="fas fa-calendar-alt"></i>
                    </div>

                    <div class="side-content">
                      <div class="side-content-count">
                        <h5 class="count-number">10</h5>
                      </div>

                      <div class="side-content-label">
                        <p class="count-label">TOTAL</p>
                      </div>
                    </div>
                  </div>

                </div>

                <div class="row">
                  <div class="columns col-md-6">

                    <div class="card-icon icon-blue">
                      <i class="fas fa-calendar-alt"></i>
                    </div>

                    <div class="side-content">
                      <div class="side-content-count">
                        <h5 class="count-number">10</h5>
                      </div>

                      <div class="side-content-label">
                        <p class="count-label">TOTAL</p>
                      </div>
                    </div>

                  </div>

                  <div class="columns col-md-6">

                    <div class="card-icon icon-blue">
                      <i class="fas fa-calendar-alt"></i>
                    </div>

                    <div class="side-content">
                      <div class="side-content-count">
                        <h5 class="count-number">10</h5>
                      </div>

                      <div class="side-content-label">
                        <p class="count-label">TOTAL</p>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <div class="card">


            </div>

            <div class="card">


            </div>

            <div class="card">


            </div>

          </div>


          

          <div class="body-wrapper">




          </div>



        </div>
      </div>

    </div>
  </div>

  <script>
    // Open the sidebar when the "Open Sidebar" button is clicked
    document.getElementById('toggleSidebarBtn').addEventListener('click', function () {
      var sidebar = document.querySelector('.sidebar');
      var closeButton = document.getElementById('closeSidebarBtn');

      // Show the sidebar and the close button
      sidebar.classList.add('open');
      closeButton.style.display = 'block'; // Show the close button
    });

    // Close the sidebar when the "Close Sidebar" button inside the sidebar is clicked
    document.getElementById('closeSidebarBtn').addEventListener('click', function () {
      var sidebar = document.querySelector('.sidebar');
      var closeButton = document.getElementById('closeSidebarBtn');

      // Hide the sidebar and the close button
      sidebar.classList.remove('open');
      closeButton.style.display = 'none'; // Hide the close button
    });
  </script>

  <?php require "../../Agent Section/includes/scripts.php"; ?>

</body>

</html>