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

  <link rel="stylesheet" href="../../Agent Section/assets/testing/agent-dashboard.css.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../../Agent Section/assets/testing/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>













  <div class="body-container">
    <?php include "../../Agent Section/includes/sidebar copy.php"; ?>

    <div class="main-content-container">

      <div class="navbar">
        <div class="page-header-wrapper">

          <div class="page-header-content">
            <div class="page-header-text">
              <h5 class="header-title">Dashboard</h5>
            </div>
          </div>

        </div>
      </div>

      <div class="main-content">

      </div>



    </div>

  </div>


  <?php require "../../Agent Section/includes/scripts.php"; ?>

  </body>
</html>