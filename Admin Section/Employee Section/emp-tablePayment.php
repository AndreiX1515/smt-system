<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee - Transactions</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionTablePayment.css?v=<?php echo time(); ?>">
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
              <!-- <span class="icon">🔍</span> -->
            </div>
          </div>

          <!-- <div class="filter-field">
          <!-- <label for="status">Status:</label> 
          <div class="select-wrapper">
            <select id="status">
              <option value="All" disabled selected>Select Status</option>
              <option value="Pending">Pending</option>
              <option value="Confirmed">Confirmed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
        </div> -->

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

            <!-- <div class="date-range-wrapper flightbooking-wrapper">
      <div class="date-range-inputs-wrapper">
        <div class="input-with-icon">
          <input type="text" class="datepicker" id="BookingStartDate" placeholder="Booking Date">
          <i class="fas fa-calendar-alt calendar-icon"></i>
        </div>
      </div>
    </div> -->

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

        <div class="table-wrapper">
          <table class="product-table" id="product-table">
            <thead>
              <tr>
                <th>TRANSACT NO.</th>
                <th>BRANCH</th>
                <th>FLIGHT DATE</th>
                <th>PAYMENT TITLE</th>
                <th>PAYMENT TYPE</th>
                <th>AMOUNT</th>
                <th>PROOF OF PAYMENT</th>
                <th>PAYMENT DATE</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql1 = "SELECT p.paymentId, p.transactNo, 
                        CONCAT(a.lName, ', ', a.fName, 
                            IF(a.mName IS NOT NULL AND a.mName != '', CONCAT(' ', LEFT(a.mName, 1), '.'), '')) AS agentName, 
                        p.paymentTitle, p.paymentType, FORMAT(p.amount, 2) AS amount, 
                        p.filePath, DATE_FORMAT(p.paymentDate, '%M %d, %Y') AS paymentDate, p.paymentStatus, 
                        br.branchName as branchName, f.flightDepartureDate AS flightDate,
                        CASE 
                          WHEN a.accountId IS NOT NULL 
                            THEN CASE WHEN a.companyId IS NOT NULL THEN c.companyName ELSE br.branchName END
                          WHEN cl.accountId IS NOT NULL 
                            THEN CASE WHEN cl.companyId IS NOT NULL THEN cc.companyName ELSE br.branchName END
                          ELSE 'Unknown'END AS `ACCOUNT NAME`
                      FROM payment p
                      LEFT JOIN booking b ON p.transactNo = b.transactNo
                      JOIN flight f ON b.flightId = f.flightId
                      JOIN branch br ON b.agentCode = br.branchAgentCode
                      LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                      LEFT JOIN company c ON a.companyId = c.companyId
                      LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                      LEFT JOIN company cc ON cl.companyId = cc.companyId
                      WHERE p.paymentStatus = 'Submitted'";

              $res1 = $conn->query($sql1);

              if ($res1->num_rows > 0) {
                while ($row = $res1->fetch_assoc()) {
                  $paymentTypeClass = '';
                  $paymentTypeValue = $row['paymentType'];

                  // Assign classes based on the payment type
                  if ($paymentTypeValue === 'Partial Payment') {
                    $paymentTypeClass = 'badge bg-warning text-dark';
                  } elseif ($paymentTypeValue === 'Full Payment') {
                    $paymentTypeClass = 'badge bg-success';
                  } else {
                    $paymentTypeClass = 'badge bg-secondary';
                  }

                  $flightDate = $row['flightDate'] ?? 'N/A'; // Handle null flight date
                  $formattedFlightDate = date('Y.m.d', strtotime($flightDate));;

                  // Output table row with data-transactno attribute
                  echo "<tr class='transaction-row' data-paymentId='{$row['paymentId']}'>
                          <td>{$row['transactNo']}</td>
                          <td>{$row['branchName']}</td>
                          <td>{$formattedFlightDate}</td>
                          <td>{$row['paymentTitle']}</td>
                          <td><span class='$paymentTypeClass p-2'>$paymentTypeValue</span></td>
                          <td>₱ {$row['amount']}</td>

                          
                          <td class='viewdownloadfile-wrapper'>
                              <a class='btn-view' href='../Agent Section/functions/view-file.php?file=" .  urlencode($row['filePath']) . "' target='_blank'>
                                  <i class='fas fa-eye'></i>
                              </a>
                              <a class='btn-download' href='../Agent Section/functions/download.php?file=" .  urlencode($row['filePath']) . "' target='_blank'>
                                  <i class='fas fa-download'></i>
                              </a>
                          </td>


                          <td> {$row['paymentDate']} </td>
                      </tr>";
                }
              } else {
                echo "<tr><td colspan='8' style='text-align: center;'>No Payments Found</td></tr>";
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


  <!-- Payment Status Modal-->
  <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transaction Details - ID: <span id="transactionModalLabel"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="paymentForm">
          <div class="modal-body">
              <input type="hidden" id="paymentIdInput" name="paymentId">
              <input type="hidden" id="accId" name="accId" value="<?php echo $_SESSION['employee_accountId'] ?>">

              <!-- Request Status Section -->
              <div class="mb-4">
                  <label for="paymentStatus" class="form-label fw-bold">Request Status:</label>
                  <select id="paymentStatus" name="paymentStatus" class="form-select" required>
                      <option selected disabled value="">Select Option</option>
                      <option value="Approved">Approved</option>
                      <option value="Rejected">Rejected</option>
                  </select>
              </div>

              <div class="mb-4">
                  <label for="paymentRemarks" class="form-label fw-bold">Remarks:</label>
                  <input type="text" id="paymentRemarks" name="paymentRemarks" class="form-control" placeholder="Enter remarks or additional comments here">
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

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      // Select all view and download links inside .viewdownloadfile-wrapper
      document.querySelectorAll(".viewdownloadfile-wrapper a").forEach(link => {
          link.addEventListener("click", function () {
              // Close the modal
              let transactionModal = document.getElementById("transactionModal");
              let bootstrapModal = bootstrap.Modal.getInstance(transactionModal);
              if (bootstrapModal) {
                  bootstrapModal.hide();
              }
          });
      });
  });

  </script>

  <script>
      $(document).ready(function() {
          $("#paymentForm").submit(function(event) {
              event.preventDefault(); // Prevent default form submission

              $.ajax({
                  url: "../Employee Section/functions/emp-tablePayment-code.php",
                  type: "POST",
                  data: $(this).serialize(), // Serialize form data
                  dataType: "json",
                  success: function(response) {
                      if (response.status === "success") {
                          localStorage.setItem("flashMessage", response.statusLabel); // Show friendly label
                          localStorage.setItem("flashType", (response.paymentStatus === "Approved") ? "success" : "error"); 
                      } else {
                          localStorage.setItem("flashMessage", response.message);
                          localStorage.setItem("flashType", "error");
                      }

                      // Redirect after setting the message
                      window.location.href = "../Employee Section/emp-tablePayment.php";
                  },

                  error: function() {
                      localStorage.setItem("flashMessage", "An error occurred. Please try again.");
                      localStorage.setItem("flashType", "error");
                      window.location.href = "nextpage.php"; 
                  }
              });
          });
      });
  </script>


  <!-- DataTables #product-table -->
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
        scrollY: '75.5vh', // Set a fixed height for the table (adjust as necessary)
        paging: true, // Enable pagination
        pageLength: 15, // Set the number of rows per page
        autoWidth: false,
        autoHeight: false, // Prevent automatic height adjustment

        // Disable sorting for specific columns
        columnDefs: [{
          targets: [1, 2, 3, 5, 6, ], // Disable sorting for 2nd and 4th columns
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

      // Status Filter
      $('#status').on('change', function() {
        const selectedStatus = $(this).val();
        table.column(8).search(selectedStatus || '').draw();
      });

      // Package Filter
      $('#packages').on('change', function() {
        const selectedPackage = $(this).val();
        table.column(2).search(selectedPackage || '').draw();
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

        // Clear status dropdown
        $('#status').val('All').change();

        // Clear packages dropdown
        $('#packages').val('All').change();

        // Explicitly reset the variables
        flightStartDate = '';
        bookingStartDate = '';

        // Clear date fields
        $('#BookingStartDate').val('').trigger('change'); // Reset and trigger input for BookingStartDate
        $('#FlightStartDate').val('').trigger('change'); // Reset and trigger input for FlightStartDate



        // Redraw the table
        table.draw();
      });


    });
  </script>

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
    document.querySelectorAll('.viewdownloadfile-wrapper a').forEach((link) => {
      link.addEventListener('click', (event) => {
        if (link.textContent.trim() === 'View File') {
          // Close the modal
          const modal = document.getElementById('transactionModal');
          const bootstrapModal = bootstrap.Modal.getInstance(modal); // Get the active modal instance
          if (bootstrapModal) {
            bootstrapModal.hide(); // Close the modal
          }
        }
      });
    });
  </script>

  <!-- Row Select JS -->
  <script>
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
      // Get all the rows with the class 'transaction-row'
      const rows = document.querySelectorAll('.transaction-row');

      rows.forEach(row => {
        // Add click event listener to each row
        row.addEventListener('click', function() {
          // Get the transaction number (data attribute)
          const paymentId = row.getAttribute('data-paymentId');

          // Set the transaction number in the modal
          document.getElementById('paymentIdInput').value = paymentId;
          document.getElementById('transactionModalLabel').textContent = paymentId
          // Show the modal (using Bootstrap modal)
          const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
          modal.show();
        });
      });
    });
  </script>

  </body>
</html>