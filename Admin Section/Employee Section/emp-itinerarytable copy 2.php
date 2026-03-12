<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Itinerary Table</title>

  <?php include '../Employee Section/includes/emp-head.php' ?>

  <link rel="stylesheet" href="../Employee Section/assets/css/emp-itineraryTable.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">

</head>

<body>

  <?php include '../Employee Section/includes/emp-sidebar.php' ?>

  <!-- Main Container -->
  <div class="main-container">

    <div class="navbar">
      <div class="page-header-wrapper">

        <!-- <div class="page-header-top">
          <div class="back-btn-wrapper">
            <button class="back-btn" id="redirect-btn">
              <i class="fas fa-chevron-left"></i>
            </button>
          </div>
        </div> -->

        <div class="page-header-content">
          <div class="page-header-text">
            <h5 class="header-title">Itinerary</h5>
          </div>
        </div>

      </div>
    </div>

    <?php
    $statusTab = isset($_GET['status']) ? $_GET['status'] : '';
    ?>

    <div class="main-content">

      <div class="table-container">

        <div class="table-header">
          <!-- <div class="search-wrapper">
            <div class="search-input-wrapper">
              <input type="text" id="search" placeholder="Search here..">
            </div>
          </div> -->

          <div class="second-header-wrapper">
            <!-- <div class="date-range-wrapper flightbooking-wrapper">
              <div class="date-range-inputs-wrapper">
                <div class="input-with-icon">
                  <input type="text" class="datepicker" id="FlightStartDate" placeholder="Flight Date" readonly>
                  <i class="fas fa-calendar-alt calendar-icon"></i>
                </div>
              </div>
            </div>

            <div class="date-range-wrapper sorting-wrapper">
              <div class="select-wrapper">
                <select id="packages">
                  <option value="" disabled selected>Select Branch</option>
                  <?php
                  // Execute the SQL query
                  $sql1 = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
                  $res1 = $conn->query($sql1);

                  // Check if there are results
                  if ($res1->num_rows > 0) {
                    // Loop through the results and generate options
                    while ($row = $res1->fetch_assoc()) {
                      echo "<option value='" . $row['branchName'] . "'>" . $row['branchName'] . "</option>";
                    }
                  } else {
                    echo "<option value=''>No companies available</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="buttons-wrapper">
              <button id="clearSorting" class="btn btn-secondary">
                Clear Filters
              </button>
            </div> -->
          </div>
        </div>


        <div class="navpills-container">
          <div class="filter-tabs" id="booking-filter-tabs">
            <button class="filter-btn active" data-filter="">
              Created itinerary
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM itineraries;";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

            <!-- <button class="filter-btn active" data-filter="">
              Available Itinerary
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM itineraries;";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button> -->

            
          </div>

          <div class="create-itinerary-wrapper">
            <div class="buttons-wrapper">
              <button id="createItinerary" class="btn btn-primary">
                Create Itinerary
              </button>
            </div>

            <script>
              document.getElementById("createItinerary").addEventListener("click", function() {
                window.location.href = "../Employee Section/emp-generateItinerary.php"; // Change to your target page
              });
            </script>

          </div>
        </div>


        <div class="itinerary-grid">
            <?php
            if (!isset($conn)) {
                die("Database connection error.");
            }

            $sql = "SELECT * FROM itineraries ORDER BY createdAt DESC;";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $itineraryId = htmlspecialchars($row['itineraryId'] ?? '');
                    $packageName = htmlspecialchars($row['itineraryName'] ?? 'Untitled');
                    $createdAt = $row['createdAt'] ? (new DateTime($row['createdAt']))->format('F j, Y g:i A') : 'N/A';

                    // Determine an icon letter (e.g., "IT" for itinerary)
                    $iconLetter = strtoupper(substr($packageName, 0, 1));
            ?>
                    <div class="itinerary-card" data-id="<?php echo $itineraryId; ?>">
                      <div class="card-content-wrap">
                          <!-- Header Section -->
                          <div class="it-card-header">
                              <div class="itinerary-info">
                                  <span class="file-type">IT</span>
                                  <div class="itinerary-name">
                                      <h6><?php echo $packageName; ?></h6>
                                  </div>
                              </div>

                              <!-- Dropdown Options -->
                              <div class="options dropdown">
                                  <button class="btn dropdown-toggle p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                      <i class="fas fa-ellipsis-v"></i>
                                  </button>
                                  <ul class="dropdown-menu dropdown-menu-end">
                                      <li><a class="dropdown-item" href="#">View Details</a></li>
                                      <li><a class="dropdown-item" href="#">Edit</a></li>
                                      <li><a class="dropdown-item text-danger" href="#">Delete</a></li>
                                  </ul>
                              </div>
                          </div>

                          <!-- Body Section -->
                          <div class="it-card-body">
                              <div class="itinerary-icon"><?php echo $iconLetter; ?></div>
                          </div>

                          <!-- Footer Section (Placeholder for future content) -->
                          <div class="it-card-footer"></div>
                      </div>
                  </div>




            <?php

                }

            } else {
                echo "<p class='no-records'>No itineraries found.</p>";
            }

            ?>
        </div>

      
        <!-- <div class="table-footer">
          <div class="pagination-controls">
            <button id="prevPage" class="pagination-btn">Previous</button>
            <span id="pageInfo" class="page-info">Page 1 of 10</span>
            <button id="nextPage" class="pagination-btn">Next</button>
          </div>
        </div> -->

      </div>

    </div>
  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>


  <!-- Itinerary Card Clickable Script -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.addEventListener("click", function (event) {
        let cardBody = event.target.closest(".it-card-body");
        if (cardBody) {
          let itineraryCard = cardBody.closest(".itinerary-card");
          let itineraryId = itineraryCard ? itineraryCard.getAttribute("data-id") : null;
          if (itineraryId) {
            window.location.href = `emp-itineraryDetails.php?id=${itineraryId}`;
          }
        }
      });
    });
  </script>

  <!-- For Button Tabs Status Sorting -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Get the status from the URL
      let statusTab = "<?php echo isset($_GET['status']) ? $_GET['status'] : ''; ?>";
      console.log("Status from URL:", statusTab); // Debugging

      // Find all filter buttons
      let buttons = document.querySelectorAll("#booking-filter-tabs .filter-btn");

      // Remove 'active' class from all buttons
      buttons.forEach(btn => btn.classList.remove("active"));

      // Find the button that matches the status
      let matchedButton = [...buttons].find(btn => btn.getAttribute("data-filter") === statusTab);

      if (matchedButton) {
        matchedButton.classList.add("active"); // Highlight the correct button
        console.log("Activating button:", matchedButton.innerText);

        setTimeout(() => {
          matchedButton.click();
        }, 3);

      } else {
        // Default to "All" if no match found
        let defaultButton = document.querySelector("#booking-filter-tabs .filter-btn[data-filter='']");
        if (defaultButton) {
          defaultButton.classList.add("active");
          console.log("Activating default button: All");

          setTimeout(() => {
            defaultButton.click();
          }, 100);
        }
      }

      // Add click event listener to each button
      buttons.forEach(button => {
        button.addEventListener("click", function() {
          // Remove active class from all buttons
          buttons.forEach(btn => btn.classList.remove("active"));

          // Add active class to the clicked button
          this.classList.add("active");

          let filterValue = this.getAttribute("data-filter");

          // Apply DataTables filtering
          if ($.fn.DataTable.isDataTable("#product-table")) {
            $('#product-table').DataTable().column(8).search(filterValue || '', true, false).draw();
          }
        });
      });
    });
  </script>

  <!-- Row Click Selection-->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      document.querySelectorAll("tr[data-url]").forEach(function(row) {
        row.addEventListener("click", function() {
          const transactionNumber = row.getAttribute("data-url").split('=')[1]; // Extract transaction number from the URL

          console.log("Transaction Number: ", transactionNumber); // Debugging line

          // Use AJAX to send the transaction number to the server
          $.ajax({
            url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file to handle the session setting
            type: 'POST',
            data: {
              transaction_number: transactionNumber
            },
            success: function(response) {
              console.log("Response: ", response); // Debugging line

              // Redirect to the next page after successfully setting the session
              window.location.href = row.getAttribute("data-url"); // Use the original URL stored in data-url attribute
            },
            error: function(xhr, status, error) {
              console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
            }
          });
        });
      });
    });
  </script>

  <!-- DataTables #product-table
  <script>
    $(document).ready(function() {
      const table = $('#product-table').DataTable({
        dom: 'rtip', // Use only the relevant table elements
        language: {
          emptyTable: "No Transaction Records Available"
        },
        order: [
          [0, 'desc']
        ], // Default sorting by Transaction ID (descending)
        scrollX: false,
        scrollY: '65.5vh', // Set a fixed height for the table (adjust as necessary)
        paging: true, // Enable pagination
        pageLength: 14, // Set the number of rows per page
        autoWidth: false,
        autoHeight: false, // Prevent automatic height adjustment

        // Disable sorting for specific columns
        columnDefs: [{
          targets: [1, 2, 3, 4, 5, 6, 7, 8], // Disable sorting for 2nd and 4th columns
          orderable: false
        }]
      });

      // Search Functionality
      $('#search').on('keyup', function() {
        table.search(this.value).draw();
      });

      // Update the custom pagination buttons and page info
      function updatePagination() {
        const info = table.page.info();
        const currentPage = info.page + 1; // Get current page number (1-indexed)
        const totalPages = info.pages; // Get total pages

        // Update page info text
        $('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);

        // Enable/Disable prev and next buttons based on current page
        $('#prevPage').prop('disabled', currentPage === 1);
        $('#nextPage').prop('disabled', currentPage === totalPages);
      }

      // Custom pagination button click events
      $('#prevPage').on('click', function() {
        table.page('previous').draw('page');
        updatePagination();
      });

      $('#nextPage').on('click', function() {
        table.page('next').draw('page');
        updatePagination();
      });

      // Initialize pagination on first load
      updatePagination();

      // Package Filter
      $('#packages').on('change', function() {
        const selectedPackage = $(this).val();
        table.column(1).search(selectedPackage || '').draw();
      });

      // Booking Date Filter with value change
      $('#BookingStartDate').on('change', function() {
        const selectedBookingDate = $(this).val(); // Get the selected value directly from the input field
        console.log("Booking Date Filter:", selectedBookingDate); // Log the selected booking date
        table.column(3).search(selectedBookingDate || '').draw(); // Column 4 (index starts at 0)
      });

      // Flight Date Filter with value change
      $('#FlightStartDate').on('change', function() {
        const selectedFlightDate = $(this).val(); // Get the selected value directly from the input field
        console.log("Flight Date Filter:", selectedFlightDate); // Log the selected flight date
        table.column(3).search(selectedFlightDate || '').draw(); // Column 5 (index starts at 0)
      });

      // Apply datepicker and input validation for FlightStartDate
      $("#FlightStartDate").datepicker({
        dateFormat: "yy-mm-dd", // Set the format to MM-DD-YYYY
        showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
        changeMonth: true, // Allow the month to be changed from the dropdown
        changeYear: true, // Allow the year to be changed from the dropdown
        yearRange: "1900:2100", // Set a range of years (optional)
        onSelect: function(dateText) {
          // When a date is selected, update the input field with the date
          $(this).val(dateText);
          flightStartDate = dateText; // Store the selected date
          console.log("FlightStartDate Selected Date (onSelect): " + dateText);
          table.column(3).search(flightStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });


      // Apply datepicker and input validation for BookingStartDate
      $("#BookingStartDate").datepicker({
        dateFormat: "mm-dd-yy", // Set the format to MM-DD-YYYY
        showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
        changeMonth: true, // Allow the month to be changed from the dropdown
        changeYear: true, // Allow the year to be changed from the dropdown
        yearRange: "1900:2100", // Set a range of years (optional)
        onSelect: function(dateText) {
          // When a date is selected, update the input field with the date
          $(this).val(dateText);
          bookingStartDate = dateText; // Store the selected date
          console.log("FlightStartDate Selected Date (onSelect): " + dateText);
          table.column(4).search(bookingStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });

      // BookingStartDate Input Validation and Formatting
      $("#BookingStartDate").on("input", function() {
        var value = $(this).val();

        // Remove non-numeric and non-dash characters
        value = value.replace(/[^\d-]/g, '');

        // Automatically add dashes in the correct places if necessary
        if (value.length > 2 && value.charAt(2) !== '-') {
          value = value.substring(0, 2) + '-' + value.substring(2);
        }
        if (value.length > 5 && value.charAt(5) !== '-') {
          value = value.substring(0, 5) + '-' + value.substring(5);
        }

        // Limit the total input length to 10 characters (MM-DD-YYYY)
        if (value.length > 10) {
          value = value.substring(0, 10);
        }

        // Update the input field value
        $(this).val(value);

        // Reset or update the bookingStartDate variable
        if (value === "") {
          bookingStartDate = ""; // Reset the variable if the input is cleared
        } else {
          bookingStartDate = value; // Update the variable with the formatted value
        }

        // Update the table column search
        table.column(5).search(bookingStartDate || '').draw(); // Column 5 (index starts at 0)

        console.log("BookingStartDate Input Value (on input): " + value);
      });


      // Clear All Filters
      $('#clearSorting').on('click', function() {
        // Clear search field
        $('#search').val('');
        table.search('').draw();

        // Reset status dropdown to "Select Status"
        $('#status').val('').trigger('change');

        // Reset branch dropdown to "Select Branch"
        $('#packages').val('').trigger('change');

        // Explicitly reset date filter variables
        flightStartDate = '';
        bookingStartDate = '';

        // Clear date fields
        $('#BookingStartDate').val('').trigger('change');
        $('#FlightStartDate').val('').trigger('change');

        // Reset DataTable filters & sorting
        table.order([
            [0, 'desc']
          ]) // Default sort by first column (Transaction ID)
          .search('') // Clear any search input
          .columns().search('') // Reset all column filters
          .draw(); // Redraw table to default state
      });

    });
  </script> -->

</body>

</html>