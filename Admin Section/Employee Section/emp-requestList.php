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
            <h5 class="header-title">Set Up - Request</h5>
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
              <input type="text" id="search" name="search" placeholder="Search here..">
            </div>
          </div>

          <button id="openModalBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequestModal">
            Add New Request
          </button>

          <div class="second-header-wrapper">
            <!-- <div class="date-range-wrapper sorting-wrapper">
            <div class="select-wrapper">
              <input type="text" id="search" placeholder="Search Requests...">
            </div>
          </div> -->
            <div class="buttons-wrapper">
              <button id="clearSorting" class="btn btn-secondary">Clear Filters</button>
            </div>
          </div>

        </div>

        <div class="table-content-body">

          <div class="table-container">
            <table class="table product-table" id="product-table">
            <thead>
              <tr>
                <th>Request Title</th>
                <th>Request Details</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql1 = "SELECT c.concernId, c.concernTitle, cd.details, cd.price 
                       FROM concern c
                       JOIN concerndetails cd ON c.concernId = cd.concernId";
              $res1 = $conn->query($sql1);

              if ($res1->num_rows > 0) {
                while ($row = $res1->fetch_assoc()) {
                  $formattedPrice = number_format($row['price'], 2);
                  echo "<tr>
                          <td>" . htmlspecialchars($row['concernTitle']) . "</td>
                          <td>" . htmlspecialchars($row['details']) . "</td>
                          <td>₱ " . htmlspecialchars($formattedPrice) . "</td>
                        </tr>";
                }
              } else {
                echo "<tr><td colspan='3' style='text-align: center;'>No Requests Found</td></tr>";
              }
              ?>
            </tbody>
            </table>
          </div>

          <div class="table-footer">
            <div class="pagination-controls">
              <button id="prevPage" class="pagination-btn">Previous</button>
              <span id="pageInfo" class="page-info">Page 1 of 1</span>
              <button id="nextPage" class="pagination-btn">Next</button>
            </div>
          </div>

        </div>

      </div>

    </div>









    
  </div>

  <!-- Add New Request Item Modal -->
  <div class="modal" id="addRequestModal" tabindex="-1" aria-labelledby="modalLabel">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="../Employee Section/functions/emp-requestList-code.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">New Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- CSRF Token (if used in backend session) -->
            <?php if (isset($_SESSION['csrf_token'])): ?>
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <?php endif; ?>

            <div class="mb-3">
              <label for="requestTitle" class="form-label">Request Title</label>
              <select class="form-control" name="requestTitle" id="requestTitle" required>
                <option selected disabled>Select Request Title</option>
                <?php
                $sql1 = "SELECT * FROM concern";
                $result = $conn->query($sql1);
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($row['concernId']) . "'>" . htmlspecialchars($row['concernTitle']) . "</option>";
                  }
                } else {
                  echo "<option disabled>No concerns available</option>";
                }
                ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="requestDetails" class="form-label">Details</label>
              <textarea class="form-control" id="requestDetails" name="requestDetails" rows="3"
                placeholder="Enter details"></textarea>
            </div>

            <div class="mb-3">
              <label for="requestAmount" class="form-label">Request Cost</label>
              <input type="number" step="0.01" class="form-control" id="requestAmount" name="requestAmount"
                placeholder="Enter Cost" min="1" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="submit" class="btn btn-success">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>

  <script>
    $(document).ready(function () {
      const table = $('#product-table').DataTable({
        dom: 'rtip',
        scrollX: false,
        scrollY: false,
        paging: true,
        pageLength: 20,
        autoWidth: false,
        language: {
          emptyTable: "No Requests Found"
        }
      });

      $('#search').on('keyup', function () {
        table.search(this.value).draw();
      });

      $('#clearSorting').on('click', function () {
        // Clear the search input
        $('#search').val('');

        // Clear the DataTable global search
        table.search('').draw();
      });

      function updatePagination() {
        const info = table.page.info();
        $('#pageInfo').text(`Page ${info.page + 1} of ${info.pages}`);
        $('#prevPage').prop('disabled', info.page === 0);
        $('#nextPage').prop('disabled', info.page === info.pages - 1);
      }

      $('#prevPage').on('click', function () {
        table.page('previous').draw('page');
        updatePagination();
      });

      $('#nextPage').on('click', function () {
        table.page('next').draw('page');
        updatePagination();
      });

      table.on('draw', updatePagination);
      updatePagination();
    });
  </script>

</body>

</html>