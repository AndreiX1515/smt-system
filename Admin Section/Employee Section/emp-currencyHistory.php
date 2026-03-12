<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Currency History</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-currencyHistory.css?v=<?php echo time(); ?>">
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
            <h5 class="header-title">Currency History</h5>
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

      <div class="currency-history-wrapper">

        <div class="currency-history-header">

        </div>

        <div class="currency-history-body">

          <div class="currency-history-header-body">

          </div>

          <div class="currency-history-table-wrapper">

            <div class="currency-history-table-body">
              <div class="table-wrapper">
                <table id="currency-table" class="currency-table">
                  <thead>
                    <tr>
                      <th>Currency</th>
                      <th>Rate</th>
                      <!-- <th>Percentage Difference</th> -->
                      <th>Date and Time Recorded</th>
                    </tr>
                  </thead>
                  <tbody id="currency-body">
                    <tr>
                      <td colspan="4">Loading...</td>
                    </tr>
                  </tbody>
                </table>

              </div>

            </div>

            <div class="currency-history-table-footer">

              <div class="last-updated-wrapper">
                <h6>Last Updated: <span class="fw-light" id="lastUpdated"></span></h6>
              </div>

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
  </div>


  <?php include '../Employee Section/includes/emp-scripts.php' ?>

  <script>
    let currencyData = [];
    let currentPage = 1;
    const rowsPerPage = 13;

    function formatDateTime(datetimeString) {
      const options = {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: 'numeric', minute: '2-digit',
        hour12: true
      };
      const date = new Date(datetimeString);
      return date.toLocaleString('en-US', options);
    }

    function renderTablePage(page) {
      const tbody = document.getElementById('currency-body');
      tbody.innerHTML = '';

      const start = (page - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      const paginatedItems = currencyData.slice(start, end);

      if (paginatedItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3">No currency data found.</td></tr>';
        return;
      }

      paginatedItems.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>${row.currencyLabel}</td>
        <td>${row.currentRate}</td>
        <td>${row.dateTime}</td>
      `;
        tbody.appendChild(tr);
      });

      const totalPages = Math.ceil(currencyData.length / rowsPerPage);
      document.getElementById('pageInfo').textContent =
        `Page ${currentPage} of ${totalPages}`;

      document.getElementById('prevPage').disabled = currentPage === 1;
      document.getElementById('nextPage').disabled = currentPage === totalPages;
    }

    function fetchCurrencyRates() {
      fetch('../Agent Section/functions/currencyRateHistory/fetchCurrency.php')
        .then(response => response.json())
        .then(response => {
          currencyData = response.data || [];
          const latestTime = response.latestTimeRecorded;

          // Format and display time
          const formattedTime = latestTime ? formatDateTime(latestTime) : 'N/A';
          document.getElementById('lastUpdated').textContent = formattedTime;

          currentPage = 1;
          renderTablePage(currentPage);
        })
        .catch(err => {
          console.error('Failed to fetch currency rates:', err);
          document.getElementById('currency-body').innerHTML =
            '<tr><td colspan="3">Error loading data.</td></tr>';
        });
    }

    document.getElementById('prevPage').addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        renderTablePage(currentPage);
      }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
      if (currentPage < Math.ceil(currencyData.length / rowsPerPage)) {
        currentPage++;
        renderTablePage(currentPage);
      }
    });

    fetchCurrencyRates();
    // setInterval(fetchCurrencyRates, 5000);
  </script>




</body>

</html>