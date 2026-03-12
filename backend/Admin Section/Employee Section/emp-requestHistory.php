<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee - Transactions</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionRequestHistory.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">

  <!-- Include Flatpickr -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


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
            <h5 class="header-title">Request</h5>
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

      <div class="page-content">

        <div class="table-content-header">

           <div class="search-wrapper">
            <div class="search-input-wrapper">
              <i class="fas fa-search icon"></i>
              <input type="text" id="search" placeholder="Search...">
            </div>
          </div>

          <div class="second-header-wrapper">

            <div class="filter-container">

              <div class="filter-date-wrapper">

                <div class="filter-date-inputs">

                  <div class="filter-input-with-icon--input">
                   <input type="text" id="FlightStartDate" class="filter-input" placeholder="Flight Date" readonly>

                    <i class="fas fa-calendar-alt filter-calendar-icon"></i>
                  </div>

                </div>

              </div>

              <div class="filter-buttons">
                <button id="clearSorting" class="btn-material">
                  <svg class="reset-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/>
                  </svg>
                </button>
              </div>

            </div>


          </div>

        </div>

        <div class="table-content-body">

          <div class="table-container">
            <table class="table product-table" id="product-table">
              <thead>
                <tr>
                  <th>TRANSACT NO</th>
                  <th>BRANCH</th>
                  <th>FLIGHT DATE</th>
                  <th>REQUEST TITLE</th>
                  <th>REQUEST DETAILS</th>
                  <th>SPECIFIC DETAILS</th>
                  <th>TOTAL PAX</th>
                  <th>TOTAL AMOUNT</th>
                  <th>REQUEST DATE</th>
                  <th>STATUS</th>
                  <th>REQUEST REMARKS</th>
                  <th>RAW REQUEST DATE</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sql1 = "SELECT r.requestId, r.transactNo AS `TransactNo`, br.branchName,
                            c.concernTitle AS `RequestTitle`, cd.details AS `RequestDetails`, b.pax AS `TotalPax`,
                            r.requestCost as requestCost, r.requestRemarks,
                            r.customRequest as customRequest, r.details as details, r.requestDate, 
                            r.requestStatus AS `Status`, f.flightDepartureDate AS `FlightDate`
                          FROM request r
                          LEFT JOIN concern c ON r.concernId = c.concernId
                          LEFT JOIN concerndetails cd ON r.concernDetailsId = cd.concernDetailsId
                          LEFT JOIN booking b ON r.transactNo = b.transactNo
                          JOIN flight f ON b.flightId = f.flightId
                          LEFT JOIN branch br ON br.branchAgentCode = b.agentCode
                          WHERE r.requestStatus = 'Confirmed'
                          GROUP BY r.requestId";

                $res1 = $conn->query($sql1);

                if ($res1->num_rows > 0) {
                  while ($row = $res1->fetch_assoc()) {
                    // Determine the badge class based on the status
                    $status = $row['Status'];
                    $badgeClass = '';
                    switch ($status) {
                      case 'Confirmed':
                        $badgeClass = 'text-bg-success'; // Green for Confirmed
                        break;
                      case 'Submitted':
                        $badgeClass = 'text-bg-secondary'; // Gray for Submitted
                        break;
                      case 'Rejected':
                        $badgeClass = 'text-bg-danger'; // Red for Rejected
                        break;
                      default:
                        $badgeClass = 'text-bg-info'; // Blue for other statuses
                        break;
                    }

                    // Ensure that title and details are displayed properly
                    $title = $row['RequestTitle'] ?? 'Custom Request';
                    $details = $row['RequestDetails'] ?? $row['customRequest'];
                    $requestId = $row['requestId'];
                    $formattedRequestCost = number_format($row['requestCost'], 2);
                    $formattedRequestDate = date("m.d.Y", strtotime($row['requestDate']));
                    $formattedRequestDateFilter = date('Y-m-d', strtotime($row['requestDate']));
                    $formattedFlightDate = date('Y.m.d', strtotime($row['FlightDate']));

                    // Output table row with data-transactno attribute
                    echo "<tr data-transactno='{$row['TransactNo']}' data-requestid='{$requestId}' class='transaction-row'>
                            <td>{$row['TransactNo']}</td>
                            <td>{$row['branchName']}</td>
                            <td>{$formattedFlightDate}</td>
                            <td>{$title}</td>
                            <td>{$details}</td>
                            <td>{$row['details']}</td>
                            <td>{$row['TotalPax']}</td>
                            <td>₱ {$formattedRequestCost}</td>
                            <td>{$formattedRequestDate}</td>
                            <td>{$row['Status']}</td>
                            <td>{$row['requestRemarks']}</td>
                            <td style='display:none;'>{$formattedRequestDateFilter}</td> 
                            <!-- hidden raw date -->
                          </tr>";
                  }
                } else {
                  echo "<tr><td colspan='9' style='text-align: center;'>NO REQUESTS AS OF THE MOMENT</td></tr>";
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

  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>


  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>


  <!-- JQuery Datapicker -->
  <script>
    document.addEventListener("scroll", function () {
      const searchBar = document.querySelector(".search-bar");
      const scrollPosition = window.scrollY;

      // Add or remove the upward adjustment class based on scroll position
      if (scrollPosition > 70) { // Adjust the threshold as needed
        searchBar.classList.add("scrolled-upward");
      }
      else {
        searchBar.classList.remove("scrolled-upward");
      }
    });
  </script>

  <!-- DataTables #product-table -->
  <script>
    $(document).ready(function () {
      const table = $('#product-table').DataTable({
        dom: 'rtip',
        language: { emptyTable: "No Transaction Records Available" },
        order: [[0, 'asc']], // Sort by Transaction ID
        scrollX: false,
        paging: true,
        pageLength: 20,
        autoWidth: false,
        columnDefs: [
          {
            targets: [1, 2, 3, 5, 6],
            orderable: false
          },
          { targets: [1, 2, 3, 5, 6, 9, 10], orderable: false }
        ]
      });

      // Global search
      $('#search').on('keyup', function () {
        table.search(this.value).draw();
      });

      // Custom Pagination Info
      function updatePagination() {
        const info = table.page.info();
        $('#pageInfo').text(`Page ${info.page + 1} of ${info.pages}`);
        $('#prevPage').prop('disabled', info.page === 0);
        $('#nextPage').prop('disabled', info.page + 1 === info.pages);
      }

      $('#prevPage').on('click', function () {
        table.page('previous').draw('page');
        updatePagination();
      });

      $('#nextPage').on('click', function () {
        table.page('next').draw('page');
        updatePagination();
      });

      updatePagination(); // On load



      // Flight Date Filter
      $('#FlightStartDate').on('change', function () {
        table.column(11).search($(this).val() || '').draw();
      });

      // Datepickers
      flatpickr("#FlightStartDate", {
        dateFormat: "Y-m-d", // Same as "yy-mm-dd"
        allowInput: true,
        defaultDate: null,
        onChange: function (selectedDates, dateStr) {
          // Trigger DataTables filter on column 11
          $('#FlightStartDate').val(dateStr);
          table.column(11).search(dateStr || '').draw();
        }
      });






      // Clear all filters
      $('#clearSorting').on('click', function () {
        $('#search').val('');
        table.search('').draw();

        $('#status, #packages').val('All').change();

        $('#BookingStartDate, #FlightStartDate').val('').trigger('change');

        table.draw();
      });
    });
  </script>

  <script>
    function toggleClearButton(input) {
      const clearButton = input.nextElementSibling; // Get the button next to the input
      clearButton.style.display = input.value ? "block" : "none";
    }

    // Clear the input field
    function clearInput(button) {
      const input = button.previousElementSibling; // Get the input field before the button
      input.value = "";
      button.style.display = "none"; // Hide the clear button
      input.focus(); // Refocus on the input
    }
  </script>


  </body>
</html>