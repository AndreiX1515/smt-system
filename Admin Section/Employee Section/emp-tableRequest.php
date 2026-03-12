<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee - Transactions</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionTableRequest.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">

<body>

  <?php include '../Employee Section/includes/emp-sidebar.php' ?>

  <!-- Main Container -->
  <div class="main-container">
    <?php include '../Employee Section/includes/emp-navbar.php' ?>

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

            <div class="date-range-wrapper flightbooking-wrapper">
              <div class="date-range-inputs-wrapper">
                <div class="input-with-icon">
                  <input type="text" class="datepicker" id="FlightStartDate" placeholder="Flight Date">
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
          <table class="product-table" id="product-table">
            <thead>
              <tr>
                <th>TRANSACT NO.</th>
                <th>BRANCH</th>
                <th>FLIGHT DATE</th>
                <th>REQUEST TITLE</th>
                <th>REQUEST DETAILS</th>
                <th>SPECIFIC DETAILS</th>
                <th>TOTAL PAX</th>
                <th>TOTAL AMOUNT</th>
                <th>REQUEST DATE</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql1 = "SELECT r.requestId, r.transactNo AS `TransactNo`,
                        CONCAT(a.lName, ', ', a.fName, 
                            IF(a.mName IS NOT NULL AND a.mName != '', CONCAT(' ', LEFT(a.mName, 1), '.'), '')) AS AgentName,
                        c.concernTitle AS `RequestTitle`, cd.details AS `RequestDetails`, b.pax AS `TotalPax`,
                        r.requestCost as requestCost,
                        r.customRequest as customRequest, r.details as details, DATE_FORMAT(r.requestDate, '%m-%d-%Y') AS `RequestDate`, 
                        r.requestStatus AS `Status`, br.branchName as branchName, f.flightDepartureDate AS `FlightDate`,
                        CASE 
                          WHEN a.accountId IS NOT NULL 
                            THEN CASE WHEN a.companyId IS NOT NULL THEN co.companyName ELSE br.branchName END
                          WHEN cl.accountId IS NOT NULL 
                            THEN CASE WHEN cl.companyId IS NOT NULL THEN cc.companyName ELSE br.branchName END
                          ELSE 'Unknown'END AS `ACCOUNT NAME`
                      FROM request r
                      LEFT JOIN concern c ON r.concernId = c.concernId
                      LEFT JOIN concerndetails cd ON r.concernDetailsId = cd.concernDetailsId
                      LEFT JOIN booking b ON r.transactNo = b.transactNo
                      JOIN flight f ON b.flightId = f.flightId
                      JOIN branch br ON b.agentCode = br.branchAgentCode
                      LEFT JOIN payment p ON b.transactNo = p.transactNo
                      LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                      LEFT JOIN company co ON a.companyId = co.companyId
                      LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                      LEFT JOIN company cc ON cl.companyId = cc.companyId
                      WHERE r.requestStatus = 'Submitted'
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
                  $flightDate = $row['FlightDate'] ?? 'N/A';
                  $formattedFlightDate = date('Y.m.d', strtotime($flightDate));

                  // Output table row with data-transactno attribute
                  echo "<tr class='request-row' data-requestId='{$row['requestId']}'>
                          <td>{$row['TransactNo']}</td>
                          <td>{$row['branchName']}</td>
                          <td>{$formattedFlightDate}</td>
                          <td>{$title}</td>
                          <td>{$details}</td>
                          <td>{$row['details']}</td>
                          <td>{$row['TotalPax']}</td>
                          <td>₱ {$row['requestCost']}</td>
                          <td>{$row['RequestDate']}</td>
                        </tr>";
                }
              } else {
                echo "<tr><td colspan='9' style='text-align: center;'>No Requests Found</td></tr>";
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

  <!-- Request Status Modal -->
  <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transaction Details - ID: <span id="transactionModalLabel"> </span> </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="requestStatusForm">
            <div class="modal-body">
                <input type="hidden" id="requestIdInput" name="requestId">

                <!-- Request Status Section -->
                <div class="mb-3">
                    <label for="requestStatus" class="form-label"><strong>Request Status:</strong></label>
                    <select id="requestStatus" name="requestStatus" class="form-select" required>
                        <option selected disabled>Select Option</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <!-- Handling Fee -->
                <div class="mb-3">
                    <label for="requestHandlingFee" class="form-label"><strong>Handling Fee:</strong></label>
                    <select id="requestHandlingFee" name="requestHandlingFee" class="form-select">
                        <option selected value="0">No Handling Fee</option>
                        <option value="100">₱ 100</option>
                        <option value="200">₱ 200</option>
                        <option value="300">₱ 300</option>
                        <option value="400">₱ 400</option>
                        <option value="500">₱ 500</option>
                    </select>
                </div>

                <div class="mb-4">
                    <!-- Remarks Input -->
                    <label for="requestRemarks" class="form-label fw-bold">Remarks:</label>
                    <input type="text" id="requestRemarks" name="requestRemarks" class="form-control"
                        placeholder="Enter remarks or additional comments here">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>

      </div>
    </div>
  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>

  <!-- <?php include '../Global Assets/toast/script/toast.php' ?> -->


  <script>
    $(document).ready(function() {
        $("#requestStatusForm").submit(function(event) {
            event.preventDefault();

            let submitButton = $("button[type='submit']");
            submitButton.prop("disabled", true).text("Updating...");

            $.ajax({
                url: "../Employee Section/functions/emp-tableRequest-code.php",
                type: "POST",
                data: $(this).serialize(),
                dataType: "json",
                success: function(response) {
                    let messageType = response.status === "success" ? "success" : "error";
                    localStorage.setItem("flashMessage", response.statusLabel);
                    localStorage.setItem("flashType", messageType);

                    // Redirect after update
                    window.location.href = "../Employee Section/emp-tableRequest.php";
                },
                error: function() {
                    localStorage.setItem("flashMessage", "An error occurred. Please try again.");
                    localStorage.setItem("flashType", "error");
                    window.location.href = "../Employee Section/emp-tableRequest.php";
                },
                complete: function() {
                    submitButton.prop("disabled", false).text("Update Status");
                    $("#requestStatusForm")[0].reset(); // Clear form after submission
                }
            });
        });
    });

  </script>

  <!-- DataTables #product-table -->
  <script>
    $(document).ready(function() {
      const table = $('#product-table').DataTable({
  dom: 'rtip',
  language: {
    emptyTable: "No Transaction Records Available"
  },
  order: [[8, 'desc']], // Sort by Request Date
  scrollX: false,
  scrollY: '76.5vh',
  paging: true,
  pageLength: 15,
  autoWidth: false,
  autoHeight: false,
  columnDefs: [
    // Set fixed widths for each column
    { targets: 0, width: '130px' }, // TRANSACT NO.
    { targets: 1, width: '120px' }, // BRANCH
    { targets: 2, width: '110px' }, // FLIGHT DATE
    { targets: 3, width: '160px' }, // REQUEST TITLE
    { targets: 4, width: '200px' }, // REQUEST DETAILS
    { targets: 5, width: '200px' }, // SPECIFIC DETAILS
    { targets: 6, width: '90px' },  // TOTAL PAX
    { targets: 7, width: '120px' }, // TOTAL AMOUNT
    { targets: 8, width: '110px' }, // REQUEST DATE

    // Disable sorting on some columns if needed
    { targets: [0, 1, 3, 4, 5], orderable: false }
  ]
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
      $('#packages').on('change', function () {
        const branch = this.value === 'All' ? '' : this.value;
        table.column(1).search(branch).draw(); // index 3 = Branch column
      });

      // Flight Date Filter with value change
      $('#FlightStartDate').on('change', function() {
        const selectedFlightDate = $(this).val(); // Get the selected value directly from the input field
        console.log("Flight Date Filter:", selectedFlightDate); // Log the selected flight date
        table.column(2).search(selectedFlightDate || '').draw(); // Column 5 (index starts at 0)
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
          table.column(2).search(flightStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });

      // Clear All Filters
      $('#clearSorting').on('click', function() {
        // Clear search field
        $('#search').val('');
        table.search('').draw();

        // Clear status dropdown
        $('#status').val('All').change();

        // Clear packages dropdown
        $('#packages').val('All').change();

        // Explicitly reset the variables
        flightStartDate = '';

        $('#FlightStartDate').val('').trigger('change'); // Reset and trigger input for FlightStartDate

        // Redraw the table
        table.draw();
      });

    });
  </script>

  <?php
    // Fetch the status from the session
    $statusMessage = isset($_SESSION['status']) ? $_SESSION['status'] : '';

    // Set default toast color, and check if status is "Submitted", "Confirmed", or "Rejected"
    $toastColor = 'text-bg-primary'; // Default color

    // Check for specific status messages and set the appropriate toast color
    if (isset($_SESSION['status'])) {
      if (strpos($_SESSION['status'], 'Cancelled') !== false) {
        // Change to red for "Cancelled" status
        $toastColor = 'text-bg-danger';
      } elseif (strpos($_SESSION['status'], 'Submitted') !== false) {
        // Blue color for "Submitted" status
        $toastColor = 'text-bg-secondary';
      } elseif (strpos($_SESSION['status'], 'Confirmed') !== false) {
        // Green color for "Confirmed" status
        $toastColor = 'text-bg-success';
      } elseif (strpos($_SESSION['status'], 'Rejected') !== false) {
        // Red color for "Rejected" status
        $toastColor = 'text-bg-danger';
      }
    } elseif (isset($_SESSION['toastColor'])) {
      // Use session-defined toast color if available
      $toastColor = $_SESSION['toastColor'];
    }


    if (!empty($statusMessage)) {
      // You can use this status message in a toast or somewhere else
      echo '<div class="toast-container position-fixed top-0 end-0 p-3">
              <div id="statusToast" class="toast align-items-center ' . $toastColor . ' border-0" role="alert" aria-live="assertive" aria-atomic="true">
                  <div class="d-flex">
                      <div class="toast-body">
                          ' . htmlspecialchars($statusMessage) . '
                      </div>
                      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                  </div>
              </div>
            </div>';

      // After displaying the status message, unset session variables
      unset($_SESSION['status']);
      unset($_SESSION['toastColor']);
    }
  ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Automatically display the toast if it exists
      const toastElement = document.getElementById('statusToast');
      if (toastElement) {
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
      }
    });
  </script>


  <script>
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
      // Get all the rows with the class 'request-row'
      const rows = document.querySelectorAll('.request-row');

      rows.forEach(row => {
        // Add click event listener to each row
        row.addEventListener('click', function() {
          // Get the requestId (data attribute)
          const requestId = row.getAttribute('data-requestId');

          // Set the requestId in both the <span> and <input> fields
          document.getElementById('requestIdInput').value = requestId;
          document.getElementById('transactionModalLabel').textContent = requestId;

          // Show the modal (using Bootstrap modal)
          const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
          modal.show();
        });
      });
    });
  </script>

</body>

</html>