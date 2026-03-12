<?php
session_start();
require "../conn.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title></title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

 
    <?php include "../Agent Section/includes/sidebar.php"; ?>

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
              <h5 class="header-title">Client Payments</h5>
            </div>
          </div>

        </div>
      </div>

      <script>
        document.getElementById('redirect-btn').addEventListener('click', function () {
          window.location.href = '../Agent Section/agent-dashboard.php'; // Replace with your actual URL
        });
      </script>

      <?php
        $statusTab = isset($_GET['status']) ? $_GET['status'] : '';
      ?>

      <div class="main-content">
        <div class="table-wrapper">

          <!-- Filter Inputs -->
          <div class="table-header">
            <div class="search-wrapper">
              <div class="search-input-wrapper">
                <input type="text" id="search" placeholder="Search here..">
              </div>
            </div>

            <div class="second-header-wrapper">
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
                  Clear
                </button>
              </div>
            </div>
          </div>

          <!-- Table  -->
          <div class="table-container">
            <table id="product-table" class="table product-table">
              <thead>
                <tr>
                  <th>TRANSACTION NO</th>
                  <th>AMOUNT</th>
                  <th>PROOF OF PAYMENT</th>
                  <th>PAYMENT DATE</th>
                  <th>STATUS</th>
                  <th>REMARKS</th>
                  <th style='display:none;'>RAW PAYMENT DATE</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $sql1 = "SELECT b.transactNo, p.paymentId, p.amount, p.filePath, p.paymentDate, p.paymentStatus, p.paymentRemarks
                            FROM `booking` b
                            JOIN `paymentc` p ON b.transactNo = p.transactNo
                            WHERE b.agentCode = '$agentCode' AND p.paymentStatus= 'Submitted'
                            ORDER BY p.paymentId ASC";

                  // Execute the query
                  $result1 = $conn->query($sql1);

                  // Check if query execution was successful
                  if (!$result1) 
                  {
                    die("Query error: " . $conn->error);
                  }

                  // Fetch results and display rows
                  if ($result1->num_rows > 0) 
                  {
                    while ($row = $result1->fetch_assoc()) 
                    {
                      $amount = number_format($row['amount'], 2);
                      $date = date("F d, Y", strtotime($row['paymentDate']));
                      $remarks = !empty($row['paymentRemarks']) ? $row['paymentRemarks'] : 'N/A';

                      $status = isset($row['paymentStatus']) ? $row['paymentStatus'] : 'Unknown';
                      $statusClass = '';

                      switch ($status) 
                      {
                        case 'Approved':
                          $statusClass = 'bg-success text-white'; // Green background, white text
                          break;
                        case 'Rejected':
                          $statusClass = 'bg-danger text-white'; // Red background, white text
                          break;
                        case 'Submitted':
                          $statusClass = 'bg-warning text-dark';
                          break;
                        default:
                          $statusClass = 'bg-secondary text-white';
                      }

                      echo "<tr class='transaction-row' data-paymentId='{$row['paymentId']}'>
                              <td>" . $row['transactNo'] . "</td>
                              <td>₱ " . $amount . "</td>
                              <td>
                                <a href='functions/view-file.php?file=" . urlencode($row['filePath']) . "' target='_blank'>View File</a> 
                                <a href='functions/download.php?file=" . urlencode($row['filePath']) . "' target='_blank'>Download File</a> 
                              </td>
                              <td>" . $date . "</td>
                              <td>
                                <span class='badge p-2 rounded-pill {$statusClass}'>
                                  {$status}
                                </span>
                              </td>
                              <td>" . $remarks . "</td>
                              <td style='display:none;'>" . $row['paymentDate'] . "</td>
                            </tr>";
                    }
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
            <input type="hidden" id="accId" name="accId" value="<?php echo $_SESSION['agent_accountId']; ?>">

            <div class="mb-4">
              <label for="paymentStatus" class="form-label fw-bold">Request Status:</label>
              <select id="paymentStatus" name="paymentStatus" class="form-select">
                <option disabled selected>Select Option</option>
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

  <?php require "../Agent Section/includes/scripts.php"; ?>

  <!-- JQuery Datapicker -->
  <script>
    document.addEventListener("scroll", function () 
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
    $(document).ready(function () {
      const table = $('#product-table').DataTable({
        columnDefs: [
          { targets: 6, visible: false }
        ],
        order: [[6, 'desc']],
        responsive: true,
        pageLength: 10,
        searching: false, 
        language: {
          emptyTable: "NO PAYMENT RECORDS FOUND",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ payments",
          paginate: {
            first: "First",
            last: "Last",
            next: "→",
            previous: "←"
          }
        }
      });


      // ✅ 2. Link your custom search input
      $('#search').on('keyup', function () 
      {
        table.search(this.value).draw();
      });

      // Optional: Clear sorting button
      $('#clearSorting').on('click', function () 
      {
        table.order([[6, 'desc']]).search('').draw();
        $('#search').val('');
        $('#FlightStartDate').val('');
      });
    });
  </script>

  <!-- Clear Filter script -->
  <script>
    function toggleClearButton(input) 
    {
      const clearButton = input.nextElementSibling; // Get the button next to the input
      clearButton.style.display = input.value ? "block" : "none";
    }

    // Clear the input field
    function clearInput(button) 
    {
      const input = button.previousElementSibling; // Get the input field before the button
      input.value = "";
      button.style.display = "none"; // Hide the clear button
      input.focus(); // Refocus on the input
    }
  </script>

  <!-- Payment Approval -->
  <script>
    $(document).ready(function() 
    {
      $("#paymentForm").submit(function(event) {
        event.preventDefault(); // Prevent default form submission

        $.ajax({
          url: "../Agent Section/functions/agent-clientPaymentHistory-code.php",
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
            window.location.href = "../Agent Section/agent-clientPaymentHistory.php";
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

  <!-- Clickable Rows Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const rows = document.querySelectorAll('.transaction-row');

      rows.forEach(row => {
        row.addEventListener('click', function () {
          const paymentId = row.getAttribute('data-paymentId');
          const transactNo = row.getAttribute('data-transactno');
          const status = row.getAttribute('data-status');
          const remarks = row.getAttribute('data-remarks');

          // Populate modal fields
          document.getElementById('paymentIdInput').value = paymentId;
          document.getElementById('transactionModalLabel').textContent = transactNo;
          document.getElementById('paymentStatus').value = status;
          document.getElementById('paymentRemarks').value = remarks;

          // Show the modal
          const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
          modal.show();
        });
      });
    });
  </script>

</body>

</html>