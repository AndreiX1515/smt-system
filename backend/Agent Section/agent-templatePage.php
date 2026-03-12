<?php 
session_start(); 
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>
<body>

  <?php include "../Agent Section/includes/sidebar.php"; ?>

  <div class="main-container">

    <div class="navbar">

			<div class="page-header-wrapper">

        <div class="first-half">
          <div class="page-header-top">
            <div class="back-btn-wrapper">
              <button class="back-btn" id="redirect-btn">
                <i class="fas fa-chevron-left"></i>
              </button>
            </div>
          </div>

          <div class="page-header-content">
            <div class="page-header-text">
              <h5 class="header-title">Add Guest</h5>
            </div>
          </div>
        </div>
				
        <div class="second-half">
          
        </div>

			</div>
		</div>

		<script>
			document.getElementById('redirect-btn').addEventListener('click', function () {
				window.location.href = '../Agent Section/agent-showGuest.php'; // Replace with your actual URL
			});
		</script>

    <div class="main-content">
      

    </div>


  </div>




<?php require "../Agent Section/includes/scripts.php"; ?>

  </body>
</html>