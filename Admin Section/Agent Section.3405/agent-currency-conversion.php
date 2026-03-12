<?php 
session_start(); 

ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-currency-conversion.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar copy.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="body-container">
  <?php include "../Agent Section/includes/sidebar copy.php"; ?>

  <div class="main-content-container">
    <div class="navbar">
      <h5 class="title-page">Currency Conversion History</h5>
    </div>

    <div class="main-content">
      <div class="content-body">

        <div class="table-actions">
          <div class="row">
            <div class="columns col-md-3">
                <div class="table-filters-container">
                  <label for="month-filter">Month</label>
                  <select id="month-filter" name="month-filter" class="form-control">
                    <option selected disabled>Select month</option>
                    <option value="January">January</option>
                    <option value="February">February</option>
                    <option value="March">March</option>
                    <option value="April">April</option>
                    <option value="May">May</option>
                    <option value="June">June</option>
                    <option value="July">July</option>
                    <option value="August">August</option>
                    <option value="September">September</option>
                    <option value="October">October</option>
                    <option value="November">November</option>
                    <option value="December">December</option>
                  </select>
                </div>
            </div>

            <!-- Month Select - Script -->
            <script>
              // Get the current month as a number (0 = January, 1 = February, ..., 11 = December)
              const currentMonth = new Date().getMonth();
              
              // Get the select element for the month filter
              const selectElement = document.getElementById('month-filter');
              
              // Set the selected index to the current month (currentMonth is 0-based)
              selectElement.selectedIndex = currentMonth + 1; // Add 1 to account for the "Select month" option
            </script>

            <div class="columns col-md-3">
              <div class="table-filters-container">
                <label for="year-filter">Year</label>
                <select id="year-filter" name="year-filter" class="form-control">
                  <!-- Year options will be populated dynamically -->
                </select>
              </div>
            </div>

            <script>
              // Get the current month (1 = January, 2 = February, ..., 12 = December)
              const currentMonthIndex = new Date().getMonth() + 1; // Add 1 to make it 1-based
              const currentYear = new Date().getFullYear();

              // Get the year select element
              const yearSelect = document.getElementById('year-filter');
              
              // Dynamically populate the years
              for (let i = currentYear - 5; i <= currentYear + 5; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                yearSelect.appendChild(option);
              }

              // Optionally set the current year as selected
              yearSelect.value = currentYear;
              
              // Get the month select element
              const monthSelect = document.getElementById('month-filter');

              // Set the current month as selected
              monthSelect.value = currentMonthIndex; // Use 1-based month index
            </script>

            <div class="columns col-md-3">
              <div class="table-filters-container">
                <label for="year-filter">Show:</label>
                <select id="cur-filter" name="cur-filter" class="form-control">
                    <option value="all" selected>All</option>
                    <option value="PHP">PHP</option>
                    <option value="KWN">KWN</option>
                </select>
              </div>
            </div>

          </div>

          <div class="btn-container">
            <button id="convert-btn" class="btn btn-primary">
              Reload
            </button>
          </div>
        </div>

        <div class="content-body">

        </div>

        <!-- <div class="content-footer" id="content-footer">
            <button class="btn btn-primary">Insert Currency Conversion</button>
        </div> -->

      </div>

    </div>
  </div>

</div>


<?php require "../Agent Section/includes/scripts.php"; ?>

<script>
document.getElementById('convert-btn').addEventListener('click', function() {
    // Disable the button to prevent multiple clicks
    this.disabled = true;
    
    // AJAX request to trigger the PHP script
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "../Agent Section/functions/currency-conversion-script.php", true); // Path to the PHP script

    // Set the response type to JSON
    xhr.responseType = 'json';

    xhr.onload = function() {
        // Re-enable the button after the request is complete
        document.getElementById('convert-btn').disabled = false;

        if (xhr.status == 200) {
            var response = xhr.response;

            // Log the full response object for debugging purposes
            console.log('Full Response:', response);

            if (response && response.status) {
                console.log('Response Status:', response.status); // Log the response status
                console.log('Response Message:', response.message); // Log the response message

                if (response.status == "success") {
                    alert(response.message); // Display success message
                } else {
                    alert(response.message); // Display error message
                }
            } else {
                console.error("Invalid response structure:", response);
                alert("Received an invalid response from the server.");
            }
        } else {
            alert("Error with the request: " + xhr.status);
        }
    };

    xhr.onerror = function() {
        // Handle network errors
        alert("Network error occurred while making the request.");
        document.getElementById('convert-btn').disabled = false;
    };

    xhr.send();
});
</script>




<script>
function toggleSubMenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const sectionTitle = submenu.previousElementSibling;
    const chevron = sectionTitle.querySelector('.chevron-icon'); 

    // Check if the submenu is already open
    const isOpen = submenu.classList.contains('open');

    // If it's open, we need to close it, and reset the chevron
    if (isOpen) {
        submenu.classList.remove('open');
        chevron.style.transform = 'rotate(0deg)';
    } else {
        // First, close all open submenus and reset all chevrons
        const allSubmenus = document.querySelectorAll('.submenu');
        const allChevrons = document.querySelectorAll('.chevron-icon');
        
        allSubmenus.forEach(sub => {
            sub.classList.remove('open');
        });

        allChevrons.forEach(chev => {
            chev.style.transform = 'rotate(0deg)';
        });

        // Now, open the current submenu and rotate its chevron
        submenu.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
    }
}


</script>

  </body>
</html>