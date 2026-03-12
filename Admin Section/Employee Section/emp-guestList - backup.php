<?php

session_start();

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee - Transaction</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionGuestList.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">

</head>

<body>

  <?php include '../Employee Section/includes/emp-sidebar.php' ?>

  <!-- Main Container -->
  <div class="main-container">

    <div class="navbar">
			<div class="page-header-wrapper">

				<div class="page-header-top">
				<div class="back-btn-wrapper">
					<button class="back-btn" id="redirect-btn">
					<i class="fas fa-chevron-left"></i>
					</button>
				</div>
				</div>

				<div class="page-header-content">
				<div class="page-header-text">
					<h5 class="header-title">Guest List</h5>
				</div>
				</div>

			</div>
			</div>

			<script>
			document.getElementById('redirect-btn').addEventListener('click', function () {
				window.location.href = '../Employee Section/emp-dashboard.php'; // Replace with your actual URL
			});
			</script>

    <div class="main-content">
      <div class="table-container">
        <div class="table-header">

          <div class="search-wrapper">
            <div class="search-input-wrapper">
              <input type="text" id="search" placeholder="Search here..">
            </div>
          </div>

          <div class="second-header-wrapper">
            
            <div class="date-range-wrapper sorting-wrapper">
              <div class="select-wrapper">
                <select id="packages">
                  <option value="All" disabled selected>Select Branch</option>
                  <?php
                    // Execute the SQL query
                    $sql1 = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
                    $res1 = $conn->query($sql1);

                    // Check if there are results
                    if ($res1->num_rows > 0) 
                    {
                      // Loop through the results and generate options
                      while ($row = $res1->fetch_assoc()) 
                      {
                        echo "<option value='" . $row['branchName'] . "'>" . $row['branchName'] . "</option>";
                      }
                    } 
                    else 
                    {
                      echo "<option value=''>No companies available</option>";
                    }
                  ?>
                </select>
              </div>
            </div>

            <div class="date-range-wrapper flightbooking-wrapper">
              <div class="date-range-inputs-wrapper">
                <div class="input-with-icon">
                  <input type="text" class="datepicker" id="FlightStartDate" placeholder="Flight Date" readonly>
                  <i class="fas fa-calendar-alt calendar-icon"></i>
                </div>
              </div>
            </div>

            <div class="buttons-wrapper">
              <button id="clearSorting" class="btn btn-secondary">
                Clear Filters
              </button>
            </div>
          </div>

        </div>

        <div class="table-container">
          <table class="product-table" id="product-table" aria-describedby="product-table-caption">
            <thead>
              <tr>
                <th>TRANSACTION NO</th>
                <th>AGE</th>
                <th>GIVEN NAME</th>
                <th>SURNAME</th>
                <th>FULLNAME</th>
                <th>DOB</th>
                <th>NAT</th>
                <th>PASSPORT</th>
                <th>D of E</th>
                <th>SEX</th>
                <th>ROOMING</th>
                <th>DEPARTURE DATE</th>
              </tr>
            </thead>
            <tbody>
              <?php
                // Ensure valid database connection
                if (!isset($conn) || $conn->connect_error) 
                {
                  die("Database connection error: " . ($conn->connect_error ?? 'Unknown error.'));
                }

                // SQL query for guest details
                $sql = "SELECT g.guestId, g.transactNo, g.fName, g.mName, g.lName, g.suffix, g.birthdate, g.age, g.sex, g.nationality, 
                          g.passportNo, g.passportExp, f.flightDepartureDate, rl.roomType
                        FROM `guest` g
                        JOIN `booking` b ON g.transactNo = b.transactNo
                        JOIN `flight` f ON b.flightId = f.flightId
                        JOIN `roominglist` rl ON g.guestId = rl.guestId
                        WHERE b.status = 'Confirmed'
                        ORDER BY f.flightDepartureDate ASC";

                // Execute the query
                $result = $conn->query($sql);

                // Check if query execution was successful
                if (!$result) 
                {
                  die("Query error: " . $conn->error);
                }

                // Fetch results and display rows
                if ($result->num_rows > 0) 
                {
                  while ($row = $result->fetch_assoc()) 
                  {
                    if ($row['suffix'] === 'N/A')
                    {
                      $row['suffix'] = '';
                    }

                    // Sanitize and format guest name
                    $guestName = $row['fName'] . ' ' . $row['suffix'] . ' ' . $row['lName'];

                    // Format dates
                    $birthdate = !empty($row['birthdate']) ? date('Y M d', strtotime($row['birthdate'])) : 'N/A';
                    $departureDate = !empty($row['flightDepartureDate']) ? date('Y-m-d', strtotime($row['flightDepartureDate'])) : 'N/A';

                    echo "<tr>
                            <td>" . $row['transactNo'] . "</td>
                            <td>" . $row['age'] . "</td>
                            <td>" . ($row['fName'] ?? '') . ' ' . ($row['suffix'] ?? '') . "</td>
                            <td>" . $row['lName'] . "</td>
                            <td>" . $guestName . "</td>
                            <td>" . $birthdate . "</td>
                            <td>" . $row['nationality'] . "</td>
                            <td>" . $row['passportNo'] . "</td>
                            <td>" . $row['passportExp'] . "</td>
                            <td>" . $row['sex'] . "</td>
                            <td>" . $row['roomType'] . "</td>
                            <td>" . $departureDate . "</td>
                          </tr>";
                  }
                } 
                else 
                {
                  echo "<tr><td colspan='7'>No records found.</td></tr>";
                }
  
              ?>
            </tbody>
          </table>
        </div>

        <div class="table-footer">
          <div class="pagination-controls">
            <button id="prevPage" class="pagination-btn">Previous</button>
            <span id="pageInfo" class="page-info">Page 1 of 10</span>
            <button id="nextPage" class="pagination-btn">Next</button>
          </div>
        </div>

      </div>
    </div>
  </div>


  <?php include '../Employee Section/includes/emp-scripts.php' ?>
  <!-- Add in your <head> or before </body> -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>


  <!-- JQuery Datapicker -->
  <script>
    document.addEventListener("scroll", function() 
    {
      const searchBar = document.querySelector(".search-bar");
      const scrollPosition = window.scrollY;

      // Add or remove the upward adjustment class based on scroll position
      if (scrollPosition > 70) 
      { // Adjust the threshold as needed
        searchBar.classList.add("scrolled-upward");
      } 
      else 
      {
        searchBar.classList.remove("scrolled-upward");
      }
    });
  </script>

  <!-- DataTables #product-table -->
  <script>
    $(document).ready(function () 
    {
      const table = $('#product-table').DataTable({
        dom: 'rtip',
        language: {
          emptyTable: "No Transaction Records Available"
        },
        order: [[0, 'desc']],
        scrollX: false,
        scrollY: '69vh',
        paging: true,
        pageLength: 15,
        autoWidth: false,
        autoHeight: false,
        columnDefs: [{
          targets: [1, 2, 3, 5, 6],
          orderable: false
        }]
      });

      // Search Functionality
      $('#search').on('keyup', function () {
        table.search(this.value).draw();
      });

      // Custom pagination info and button behavior
      function updatePagination() {
        const info = table.page.info();
        const currentPage = info.page + 1;
        const totalPages = info.pages;

        $('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);
        $('#prevPage').prop('disabled', currentPage === 1);
        $('#nextPage').prop('disabled', currentPage === totalPages);
      }

      $('#prevPage').on('click', function () {
        table.page('previous').draw('page');
        updatePagination();
      });

      $('#nextPage').on('click', function () {
        table.page('next').draw('page');
        updatePagination();
      });

      updatePagination();

      // Package Filter (Branch)
      $('#packages').on('change', function () {
        const selectedPackage = $(this).val();
        table.column(2).search(selectedPackage || '').draw();
      });

      // Flight Date Filter
      $('#FlightStartDate').datepicker({
        dateFormat: "yy-mm-dd",
        showAnim: "fadeIn",
        changeMonth: true,
        changeYear: true,
        yearRange: "1900:2100",
        onSelect: function (dateText) {
          $(this).val(dateText);
          table.column(11).search(dateText || '').draw();
        }
      });

      $('#FlightStartDate').on('change', function () {
        const selectedFlightDate = $(this).val();
        table.column(11).search(selectedFlightDate || '').draw();
      });

      // Clear Filters
      $('#clearSorting').on('click', function () {
        $('#search').val('');
        $('#packages').val('All').change();
        $('#FlightStartDate').val('').trigger('change');
        table.search('').draw();
      });
    });
  </script>


  <!-- Clickable table rows script -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      document.querySelectorAll("tr[data-url]").forEach(function(row) {
        row.addEventListener("click", function() {
          window.location.href = row.getAttribute("data-url");
        });
      });
    });
    // Add event listener to each row for redirection
    const rows = document.querySelectorAll("tr[data-url]");

    rows.forEach(row => {
      row.addEventListener("click", function() {
        const url = row.getAttribute("data-url");
        window.location.href = url; // Redirect to the specified URL
      });
    });
  </script>



</body>

</html>