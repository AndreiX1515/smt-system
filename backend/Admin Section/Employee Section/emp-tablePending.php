<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee - Transactions</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-tableRequestPayment.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">

<body>

  <?php include '../Employee Section/includes/emp-sidebar.php' ?>

  <!-- Main Container -->
  <div class="main-container">

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
      <div class="table-container">

        <!-- <div class="table-subheader">
          <div class="search-wrapper position-relative">
            <input type="text" placeholder="Search..." class="form-control search-input"
              oninput="toggleClearButton(this)" />
            <button type="button" class="clear-button" onclick="clearInput(this)" style="display: none;">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="dropdowns d-flex align-items-center gap-3">
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="itemsPerPageDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                Items per Page
              </button>
              <ul class="dropdown-menu" aria-labelledby="itemsPerPageDropdown">
                <li><a class="dropdown-item" href="#">5</a></li>
                <li><a class="dropdown-item" href="#">10</a></li>
                <li><a class="dropdown-item" href="#">50</a></li>
                <li><a class="dropdown-item" href="#">100</a></li>
              </ul>
            </div>

           
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dateRangeDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                Date Range
              </button>
              <ul class="dropdown-menu" aria-labelledby="dateRangeDropdown">
                <li><a class="dropdown-item" href="#">Today</a></li>
                <li><a class="dropdown-item" href="#">This Week</a></li>
                <li><a class="dropdown-item" href="#">This Month</a></li>
                <li><a class="dropdown-item" href="#">Custom Range</a></li>
              </ul>
            </div>

            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                Filter Options
              </button>
              <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                <li><a class="dropdown-item" href="#">Status</a></li>
                <li><a class="dropdown-item" href="#">Category</a></li>
                <li><a class="dropdown-item" href="#">Priority</a></li>
                <li><a class="dropdown-item" href="#">Custom Filter</a></li>
              </ul>
            </div>

            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="exportDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                Export
              </button>
              <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                <li><a class="dropdown-item" href="#">Export as CSV</a></li>
                <li><a class="dropdown-item" href="#">Export as Excel</a></li>
                <li><a class="dropdown-item" href="#">Export as PDF</a></li>
              </ul>
            </div>

            <div class="clear-button-wrapper">
              <button class="btn btn-danger">
                <i class="fa-solid fa-circle-xmark"></i>
              </button>
            </div>
          </div>
        </div> -->

        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Transact No</th>
                <th>Branch</th>
                <th>Package Name</th>
                <th>Booking Date</th>
                <th>Flight Date</th>
                <th>Total Pax</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql1 = "SELECT b.transactNo AS `T.N`, br.branchName as branchName,
                            p.packageName AS `PACKAGE`, DATE_FORMAT(b.bookingDate, '%m-%d-%Y') AS `BOOKING DATE`,
                            DATE_FORMAT(f.flightDepartureDate, '%m-%d-%Y') AS `FLIGHT DATE`,
                            b.pax AS `TOTAL PAX`, b.status AS `STATUS`
                        FROM booking b
                        JOIN branch br ON b.agentCode = br.branchAgentCode
                        LEFT JOIN flight f ON b.flightId = f.flightId
                        LEFT JOIN package p ON b.packageId = p.packageId
                        WHERE b.status='Reserved' 
                          AND (br.branchAgentCode = 'BU4' OR br.branchAgentCode = 'BU6')
                        ORDER BY b.transactNo DESC";

              $res1 = $conn->query($sql1);

              if ($res1->num_rows > 0) {
                while ($row = $res1->fetch_assoc()) {
                  $transactNo = $row['T.N'];
                  $package = $row['PACKAGE'];
                  $branchName = $row['branchName'];
                  $bookingDate = $row['BOOKING DATE'];
                  $flightDate = $row['FLIGHT DATE'];
                  $totalPax = $row['TOTAL PAX'];
                  $status = $row['STATUS'];

                  // Determine status class
                  $statusClass = '';
                  switch ($status) {
                    case 'Confirmed':
                      $statusClass = 'bg-success text-white'; // Green
                      break;
                    case 'Reject':
                      $statusClass = 'bg-danger text-white'; // Red
                      break;
                    case 'Pending':
                      $statusClass = 'bg-warning text-dark'; // Yellow
                      break;
                    default:
                      $statusClass = 'bg-secondary text-white'; // Gray
                  }

                  echo "<tr class='transaction-row' data-transactNo='{$transactNo}'>
                          <td>{$transactNo}</td>
                          <td>{$branchName}</td>
                          <td>{$package}</td>
                          <td>{$bookingDate}</td>
                          <td>{$flightDate}</td>
                          <td>{$totalPax}</td>
                          <td>
                            <span class='badge p-2 rounded-pill {$statusClass}'>{$status}</span>
                          </td>
                        </tr>";
                }
              } else {
                echo "<tr><td colspan='10'>No bookings found</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>            

  <!-- Booking Status Modal-->
  <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header d-flex flex-row gap-1">
          <h5 class="modal-title" id="transactionModalTitle">Transaction Details - <span
              id="transactionModalLabel"></span> </h5>

          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="../Employee Section/functions/emp-tablePending-code.php" method="POST">
          <div class="modal-body">
            <input type="hidden" id="transactNoInput" name="transactNo">

            <!-- Request Status Section -->
            <div class="mb-3">
              <label for="bookingStatus" class="form-label"><strong>Booking Status:</strong></label>
              <select id="bookingStatus" name="bookingStatus" class="form-select">
                <option selected disabled>Select Option</option>
                <option value="Confirmed">Approved</option>
                <option value="Reject">Reject</option>
              </select>
            </div>

            <div class="mb-4">
              <!-- Remarks Input -->
              <label for="bookingRemarks" class="form-label fw-bold">Remarks:</label>
              <input type="text" id="bookingRemarks" name="bookingRemarks" class="form-control"
                placeholder="Enter remarks or additional comments here">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="updateBookingStatus" class="btn btn-primary">Update Status</button>
          </div>
        </form>
      </div>
    </div>
  </div>


  <?php
  // Fetch the status from the session
  $statusMessage = isset($_SESSION['status']) ? $_SESSION['status'] : '';

  // Set default toast color, and check if status is "Cancelled"
  $toastColor = 'text-bg-primary'; // Default color
  if (isset($_SESSION['status']) && strpos($_SESSION['status'], 'Cancelled') !== false) {
    $toastColor = 'text-bg-danger'; // Change to red for "Cancelled" status
  } elseif (isset($_SESSION['toastColor'])) {
    $toastColor = $_SESSION['toastColor']; // Use session-defined toast color
  }

  // Debugging output (you can remove these in production)
  echo 'Session ID: ' . session_id();  // Check if session ID is being generated
  echo 'Session Status: ' . $_SESSION['status']; // Show session status for debugging
  
  // Display the session status message if available
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

  <script>
    document.addEventListener('DOMContentLoaded', function () {
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
    document.addEventListener('DOMContentLoaded', function () {
      // Get all the rows with the class 'transaction-row'
      const rows = document.querySelectorAll('.transaction-row');

      rows.forEach(row => {
        // Add click event listener to each row
        row.addEventListener('click', function () {
          // Get the transaction number (data attribute)
          const transactNo = row.getAttribute('data-transactNo');

          // Set the transaction number in the modal
          document.getElementById('transactNoInput').value = transactNo;

          // Update the span content for the transaction number in the modal title
          document.getElementById('transactionModalLabel').textContent = transactNo;

          // Show the modal (using Bootstrap modal)
          const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
          modal.show();
        });
      });
    });
  </script>

</body>

</html>