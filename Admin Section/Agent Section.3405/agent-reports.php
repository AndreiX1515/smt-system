<?php
session_start();

require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-addGuest.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>


  <?php include "../Agent Section/includes/sidebar.php"; ?>

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
            <h5 class="header-title">Transaction</h5>
          </div>
        </div>

      </div>
    </div>

    <div class="main-content">
      <div class="content-wrapper">
        <div class="content-body">
          <form method="POST" id="reportForm">
            <!-- Report Type Radio Button -->
            <div class="mb-4">
              <label class="form-label">Report Type:</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportType" id="flightReport" value="flight" checked>
                <label class="form-check-label" for="flightReport">Flight</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportType" id="monthlyReport" value="monthly">
                <label class="form-check-label" for="monthlyReport">Monthly</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportType" id="weeklyReport" value="weekly">
                <label class="form-check-label" for="weeklyReport">Weekly</label>
              </div>
            </div>

            <!-- Report For Type Radio Button -->
            <div class="mb-4">
              <label class="form-label">For:</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportFor" id="selfReport" value="self" checked>
                <label class="form-check-label" for="selfReport">Self</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportFor" id="agentReport" value="agent">
                <label class="form-check-label" for="agentReport">Agent</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportFor" id="clientReport" value="client">
                <label class="form-check-label" for="clientReport">Client</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reportFor" id="allReport" value="all">
                <label class="form-check-label" for="allReport">All</label>
              </div>
            </div>

            <!-- Flight Date Selector -->
            <div id="flightSelector" class="mb-3" style="display: none;">
              <label for="flightDate" class="form-label">Select Flight Date:</label>
              <select class="form-select" name="flightDate" id="flightDate">
                <option selected disabled>Select a flight date</option>
                <?php
                $query = "SELECT DISTINCT flightDepartureDate FROM flight ORDER BY flightDepartureDate ASC";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $date = $row['flightDepartureDate'];
                    $formattedDate = date("M d, Y", strtotime($date));
                    echo "<option value=\"$date\">$formattedDate</option>";
                  }
                } else {
                  echo "<option disabled>No flight dates available</option>";
                }
                ?>
              </select>
            </div>

            <!-- Monthly Selector -->
            <div id="monthlySelector" class="mb-3" style="display: none;">
              <label for="month" class="form-label">Select Month:</label>
              <select class="form-select" name="month" id="month">
                <option selected disabled>Select Month</option>
                <option>January</option>
                <option>February</option>
                <option>March</option>
                <option>April</option>
                <option>May</option>
                <option>June</option>
                <option>July</option>
                <option>August</option>
                <option>September</option>
                <option>October</option>
                <option>November</option>
                <option>December</option>
              </select>
            </div>

            <!-- Weekly Selector -->
            <div id="weeklySelector" class="mb-3" style="display: none;">
              <label for="week" class="form-label">Select Week:</label>
              <select class="form-select" id="week" name="week">
                <option selected disabled>Select a week</option>
              </select>
            </div>

            <!-- Agent Selector -->
            <div id="agentSelector" class="mb-3" style="display: none;">
              <label for="agentSelect" class="form-label">Select Agent:</label>
              <select class="form-select" name="selectedAgent" id="agentSelect">
                <option value="all">All Agents</option>
                <?php
                $agentQuery = "SELECT agentId, fName, mName, lName FROM agent WHERE agentCode = '$agentCode'";
                $agentResult = $conn->query($agentQuery);

                if ($result->num_rows > 0) {
                  while ($row = $agentResult->fetch_assoc()) {
                    $fullName = $row['fName'] . ' ' . (!empty($row['mName']) ? substr($row['mName'], 0, 1) . '. ' : '') . $row['lName'];
                    echo "<option value=\"{$row['agentId']}\">$fullName</option>";
                  }
                } else {
                  echo "<option disabled>No flight dates available</option>";
                }

                ?>
              </select>
            </div>

            <!-- Client Selector -->
            <div id="clientSelector" class="mb-3" style="display: none;">
              <label for="clientSelect" class="form-label">Select Client:</label>
              <select class="form-select" name="selectedClient" id="clientSelect">
                <option value="all">All Clients</option>
                <?php
                $clientQuery = "SELECT clientId, fName, mName, lName FROM client WHERE clientCode = '$agentCode'";
                $clientResult = $conn->query($clientQuery);

                while ($row = $clientResult->fetch_assoc()) {
                  $fullName = $row['fName'] . ' ' . (!empty($row['mName']) ? substr($row['mName'], 0, 1) . '. ' : '') . $row['lName'];
                  echo "<option value=\"{$row['clientId']}\">$fullName</option>";
                }
                ?>
              </select>
            </div>

            <input name="agentCode" value="<?php echo $agentCode; ?>" hidden>
            <input name="accountId" value="<?php echo $accountId; ?>" hidden>

            <!-- Submit Button -->
            <div class="content-footer">
              <button type="submit" class="btn btn-primary">Generate Report</button>
            </div>
          </form>

          <!-- Table for Displaying Data -->
          <table class="table" id="dataTable" style="display:none;">
            <thead>
              <tr>
                <th>AGENT NAME</th>
                <th>FLIGHT DATE</th>
                <th>PAX</th>
                <th>AMOUNT</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>

          <!-- Button to Generate the Report -->
          <button id="downloadReport" class="btn btn-success" style="display: none;">Download Report</button>
        </div>
      </div>
    </div>
  </div>


  <!-- Script to Toggle Selectors -->
  <script>
    // Report Type Radio Buttons
    const flightRadio = document.getElementById('flightReport');
    const monthlyRadio = document.getElementById('monthlyReport');
    const weeklyRadio = document.getElementById('weeklyReport');

    // Report For Radio Buttons
    const selfReportRadio = document.getElementById('selfReport');
    const agentReportRadio = document.getElementById('agentReport');
    const clientReportRadio = document.getElementById('clientReport');
    const allReportRadio = document.getElementById('allReport');

    // Selectors
    const flightSelector = document.getElementById('flightSelector');
    const monthlySelector = document.getElementById('monthlySelector');
    const weeklySelector = document.getElementById('weeklySelector');
    const agentSelector = document.getElementById('agentSelector');
    const clientSelector = document.getElementById('clientSelector');

    // Data Table and Download Report
    const dataTable = document.getElementById('dataTable');
    const downloadReport = document.getElementById('downloadReport');

    // Handle Report Type Change
    flightRadio.addEventListener('change', () => {
      if (flightRadio.checked) {
        flightSelector.style.display = 'block';
        monthlySelector.style.display = 'none';
        weeklySelector.style.display = 'none';
        dataTable.style.display = 'none';
        downloadReport.style.display = 'none';
      }
    });

    monthlyRadio.addEventListener('change', () => {
      if (monthlyRadio.checked) {
        monthlySelector.style.display = 'block';
        flightSelector.style.display = 'none';
        weeklySelector.style.display = 'none';
        dataTable.style.display = 'none';
        downloadReport.style.display = 'none';
      }
    });

    weeklyRadio.addEventListener('change', () => {
      if (weeklyRadio.checked) {
        weeklySelector.style.display = 'block';
        flightSelector.style.display = 'none';
        monthlySelector.style.display = 'none';
        dataTable.style.display = 'none';
        downloadReport.style.display = 'none';
      }
    });

    // Handle Report For Change
    selfReportRadio.addEventListener('change', () => {
      if (selfReportRadio.checked) {
        agentSelector.style.display = 'none';
        clientSelector.style.display = 'none';
      }
    });

    agentReportRadio.addEventListener('change', () => {
      if (agentReportRadio.checked) {
        agentSelector.style.display = 'block';
        clientSelector.style.display = 'none';
      }
    });

    clientReportRadio.addEventListener('change', () => {
      if (clientReportRadio.checked) {
        clientSelector.style.display = 'block';
        agentSelector.style.display = 'none';
      }
    });

    // Initial Setup - Trigger change events on page load to set the initial state
    (function initialSetup() {
      if (flightRadio.checked) {
        flightSelector.style.display = 'block';
      }
      else if (monthlyRadio.checked) {
        monthlySelector.style.display = 'block';
      }
      else if (weeklyRadio.checked) {
        weeklySelector.style.display = 'block';
      }

      if (selfReportRadio.checked) {
        agentSelector.style.display = 'none';
        clientSelector.style.display = 'none';
      }
      else if (agentReportRadio.checked) {
        agentSelector.style.display = 'block';
        clientSelector.style.display = 'none';
      }
      else if (clientReportRadio.checked) {
        clientSelector.style.display = 'block';
        agentSelector.style.display = 'none';
      }
      else if (allReportRadio.checked) {
        agentSelector.style.display = 'none';
        clientSelector.style.display = 'none';
      }
    })();
  </script>

  <!-- Script for Populating Weekly -->
  <script>
    function generateWeeks(year) {
      const select = document.getElementById('week');
      select.innerHTML = '<option disabled>Select a week</option>'; // Reset

      const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
      ];

      const today = new Date();

      const format = (d) =>
        `${monthNames[d.getMonth()]} ${d.getDate().toString().padStart(2, '0')}, ${d.getFullYear()}`;

      // Start from the first Monday of the year
      let start = new Date(year, 0, 1);
      while (start.getDay() !== 1) {
        start.setDate(start.getDate() + 1);
      }

      const end = new Date(year, 11, 31);

      while (start <= end) {
        const weekStart = new Date(start);
        const weekEnd = new Date(start);
        weekEnd.setDate(weekStart.getDate() + 6);

        // Determine ISO week number
        const isoWeekNumber = getISOWeekNumber(weekStart);

        const label = `${format(weekStart)} to ${format(weekEnd)}`;
        const value = `${year}-W${isoWeekNumber.toString().padStart(2, '0')}`;

        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;

        // Auto-select if today is in this range
        if (today >= weekStart && today <= weekEnd) {
          option.selected = true;
        }

        select.appendChild(option);

        // Move to next week
        start.setDate(start.getDate() + 7);
      }
    }

    // Function to get ISO week number
    function getISOWeekNumber(date) {
      const tempDate = new Date(date.getTime());
      tempDate.setHours(0, 0, 0, 0);
      // Thursday in current week decides the year
      tempDate.setDate(tempDate.getDate() + 3 - ((tempDate.getDay() + 6) % 7));
      // January 4 is always in week 1
      const week1 = new Date(tempDate.getFullYear(), 0, 4);
      // Adjust to Thursday in week 1 and count number of weeks from date to week1
      return 1 + Math.round(((tempDate.getTime() - week1.getTime()) / 86400000
        - 3 + ((week1.getDay() + 6) % 7)) / 7);
    }

    generateWeeks(new Date().getFullYear());
  </script>

  <!-- Script for Generating Report -->
  <script>
    document.getElementById("reportForm").addEventListener("submit", function (event) {
      event.preventDefault();  // Prevent default form submission
      console.log('Form submitted');

      const formData = new FormData(this);
      console.log('Form data:', formData);

      fetch('../Agent Section/functions/agent-generateReports.php',
        {
          method: 'POST',
          body: formData
        })
        .then(response => {
          console.log('Response received:', response);
          return response.json();
        })
        .then(data => {
          console.log('Response data:', data);

          if (data.error) {
            alert(data.error);  // Show error message if no data
            console.log('Error in data:', data.error);
          }
          else {
            // Show table and fill it with data
            const tableBody = document.querySelector('#dataTable tbody');
            tableBody.innerHTML = '';  // Clear existing table data
            console.log('Filling table with data');
            data.data.forEach(row => {
              console.log('Row data:', row); // Debug individual row data
              const tr = document.createElement('tr');
              tr.innerHTML = `
              <td>${row.name}</td>
              <td>${row.flightDate}</td>
              <td>${row.pax}</td>
              <td>₱ ${row.amount}</td>`;
              tableBody.appendChild(tr);
            });

            document.getElementById('dataTable').style.display = 'table';  // Show the table
            document.getElementById('downloadReport').style.display = 'inline-block';  // Show download button
          }
        })
        .catch(error => {
          console.error('Error during fetch:', error);
        });
    });

    // Download report (this could be CSV or Excel)
    document.getElementById('downloadReport').addEventListener('click', function () {
      console.log('Download button clicked');

      const table = document.getElementById('dataTable');
      if (!table || table.style.display === 'none') {
        alert('No data to export.');
        return;
      }

      let tableHTML = table.outerHTML.replace(/ /g, '%20');

      const filename = 'flight-report.xls';
      const dataType = 'application/vnd.ms-excel';

      const downloadLink = document.createElement("a");
      document.body.appendChild(downloadLink);

      if (navigator.msSaveOrOpenBlob) {
        // For IE
        const blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob(blob, filename);
      }
      else {
        // For other browsers
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
      }

      document.body.removeChild(downloadLink);
    });
  </script>

</body>

</html>