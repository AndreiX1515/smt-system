<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statement of Account</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-soa.css?v=<?php echo time(); ?>">
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
      <!-- Row for User Type and Filter Mode Radios -->
      <div class="row">
        <!-- User Type Radio Buttons -->
        <div class="col-md-6 mb-3">
          <label>User Type:</label><br>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="user-type" id="user-agent" value="agent" checked>
            <label class="form-check-label" for="user-agent">Agent</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="user-type" id="user-client" value="client">
            <label class="form-check-label" for="user-client">Client</label>
          </div>
        </div>

        <!-- Filter Mode Radio Buttons -->
        <div class="col-md-6 mb-3">
          <label>Filter Mode:</label><br>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filter-mode" id="mode-flight" value="flight" checked>
            <label class="form-check-label" for="mode-flight">By Flight</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="filter-mode" id="mode-month" value="month">
            <label class="form-check-label" for="mode-month">By Month</label>
          </div>
        </div>
      </div>

      <!-- Row for Select Dropdowns -->
      <div class="row">
        <!-- Left Column: Agent & Company -->
        <div class="col-md-6">
          <div class="row">
            <!-- Agent Select -->
            <div class="col-md-12 mb-3" id="agent-container">
              <label for="agent-filter">Agent Name:</label>
              <select id="agent-filter" name="agent-filter" class="form-control">
                <option disabled selected>Select Agent</option>
                <?php
                $agentQuery = "SELECT accountId, fName, mName, lName FROM agent WHERE agentCode = '$agentCode'";
                $agentResult = $conn->query($agentQuery);

                if ($agentResult->num_rows > 0) {
                  while ($row = $agentResult->fetch_assoc()) {
                    $fullName = $row['fName'] . ' ' . (!empty($row['mName']) ? substr($row['mName'], 0, 1) . '. ' : '') . $row['lName'];
                    echo "<option value=\"{$row['accountId']}\">$fullName</option>";
                  }
                } else {
                  echo "<option disabled>No Agent available</option>";
                }
                ?>
              </select>
            </div>

            <!-- Travel Agency Select -->
            <div class="col-md-12 mb-3" id="company-container" style="display:none;">
              <label for="company-filter">Travel Agency:</label>
              <select id="company-filter" name="company-filter" class="form-control">
                <option disabled selected>Select Travel Agency</option>
                <?php
                $companyQuery = "SELECT companyId, companyName FROM company WHERE branchId = $branchId";
                $companyResult = $conn->query($companyQuery);

                if ($companyResult->num_rows > 0) {
                  while ($row = $companyResult->fetch_assoc()) {
                    echo "<option value=\"{$row['companyId']}\">{$row['companyName']}</option>";
                  }
                } else {
                  echo "<option disabled>No Travel Agency available</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Right Column: Flight, Month, Year -->
        <div class="col-md-6">
          <div class="row">
            <!-- Flight Date Select -->
            <div class="col-md-12 mb-3 filter-flight" id="flight-container">
              <label for="flight-filter">Select Flight Date:</label>
              <select id="flight-filter" name="flight-filter" class="form-control">
                <option value="Select Flight Date" selected disabled>Select Flight Date</option>
                <?php
                $sql1 = "SELECT DISTINCT flightDepartureDate FROM flight ORDER BY flightDepartureDate ASC";
                $result = $conn->query($sql1);

                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $formattedFlightDate = date("F j, Y", strtotime($row['flightDepartureDate']));
                    echo "<option value='" . $row['flightDepartureDate'] . "'>" . $formattedFlightDate . "</option>";
                  }
                } else {
                  echo "<option value='' disabled>No flights available</option>";
                }
                ?>
              </select>
            </div>

            <!-- Month Select -->
            <div class="col-md-6 mb-3 filter-month" id="month-container" style="display:none;">
              <label for="month-filter">Month</label>
              <select id="month-filter" name="month-filter" class="form-control">
                <option value="Select month" selected disabled>Select month</option>
              </select>
            </div>

            <!-- Year Select -->
            <div class="col-md-6 mb-3 filter-month" id="year-container" style="display:none;">
              <label for="year-filter">Year</label>
              <select id="year-filter" name="year-filter" class="form-control">
                <option value="Select year" selected disabled>Select year</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Buttons -->
      <div class="row">
        <div class="col-12 d-flex justify-content-end">
          <div class="me-2">
            <button id="generate-soa-btn" class="btn btn-primary">Preview SOA</button>
          </div>
          <div>
            <button class="btn btn-primary" id="download-btn" disabled>Generate SoA</button>
          </div>
        </div>
      </div>

      <!-- Results -->
      <div id="result-container" class="mt-3">
        
      </div>

      

    </div>

  </div>


  <?php require "../Agent Section/includes/scripts.php"; ?>
  <!-- jQuery (if not already included) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- SheetJS XLSX library -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>


  <!-- JavaScript for Date Filters -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const currentDate = new Date();
      const currentMonthIndex = currentDate.getMonth(); // 0-based: Jan = 0
      const currentYear = currentDate.getFullYear();

      const monthSelect = document.getElementById('month-filter');
      const yearSelect = document.getElementById('year-filter');

      const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
      ];

      // Populate Month Options
      monthNames.forEach((month, index) => {
        const option = document.createElement("option");
        option.value = index + 1;
        option.textContent = month;
        monthSelect.appendChild(option);
      });

      // Populate Year Options (range: currentYear - 5 to currentYear + 5)
      for (let y = currentYear - 5; y <= currentYear + 5; y++) {
        const option = document.createElement("option");
        option.value = y;
        option.textContent = y;
        yearSelect.appendChild(option);
      }
    });
  </script>

  <!-- JS for user and mode filter -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const userTypeRadios = document.getElementsByName("user-type");
      const agentContainer = document.getElementById("agent-container");
      const companyContainer = document.getElementById("company-container");

      const flightFilters = document.querySelectorAll(".filter-flight");
      const monthFilters = document.querySelectorAll(".filter-month");

      const resultContainer = document.getElementById("result-container");

      // Get select elements
      const agentFilter = document.getElementById("agent-filter");
      const companyFilter = document.getElementById("company-filter");
      const flightFilter = document.getElementById("flight-filter");
      const monthFilter = document.getElementById("month-filter");
      const yearFilter = document.getElementById("year-filter");

      function toggleUserType() {
        const selectedType = document.querySelector('input[name="user-type"]:checked').value;

        // Reset dropdowns
        agentFilter.selectedIndex = 0;
        companyFilter.selectedIndex = 0;
        flightFilter.selectedIndex = 0;
        monthFilter.selectedIndex = 0;
        yearFilter.selectedIndex = 0;

        if (selectedType === "agent") {
          console.log(selectedType);
          agentContainer.style.display = "block";
          companyContainer.style.display = "none";
          resultContainer.style.display = "none";
        }
        else {
          console.log(selectedType);
          agentContainer.style.display = "none";
          companyContainer.style.display = "block";
          resultContainer.style.display = "none";
        }
      }

      function toggleFilterMode() {
        const selectedMode = document.querySelector('input[name="filter-mode"]:checked').value;

        // Reset dropdowns
        flightFilter.selectedIndex = 0;
        monthFilter.selectedIndex = 0;
        yearFilter.selectedIndex = 0;

        if (selectedMode === "flight") {
          console.log(selectedMode);
          flightFilters.forEach(el => el.style.display = "block");
          monthFilters.forEach(el => el.style.display = "none");
          resultContainer.style.display = "none";
        }
        else {
          console.log(selectedMode);
          flightFilters.forEach(el => el.style.display = "none");
          monthFilters.forEach(el => el.style.display = "block");
          resultContainer.style.display = "none";
        }
      }

      // Event bindings
      userTypeRadios.forEach(radio => radio.addEventListener('change', toggleUserType));
      document.getElementsByName("filter-mode").forEach(radio => radio.addEventListener('change', toggleFilterMode));

      // Initial state
      toggleUserType();
      toggleFilterMode();
    });

    document.getElementById('month-filter').addEventListener('change', function () {
      console.log('Selected month:', this.value);
    });

    // Listen for changes in the year dropdown
    document.getElementById('year-filter').addEventListener('change', function () {
      console.log('Selected year:', this.value);
    });

    document.getElementById('company-filter').addEventListener('change', function () {
      console.log('Company Id:', this.value);
    });

    document.getElementById('agent-filter').addEventListener('change', function () {
      console.log('Account Id:', this.value);
    });
  </script>

  <!-- Preview SoA -->
  <script>
    document.getElementById('generate-soa-btn').addEventListener('click', function () {
      const agentSelect = document.getElementById('agent-filter');
      const companySelect = document.getElementById('company-filter');
      const monthFilter = document.getElementById('month-filter');
      const yearFilter = document.getElementById('year-filter');
      const flightFilter = document.getElementById('flight-filter');
      const selectedText = flightFilter.options[flightFilter.selectedIndex].text;
      console.log(selectedText);

      const agentId = agentSelect && agentSelect.selectedIndex > 0 ? agentSelect.value : null;
      const companyId = companySelect && companySelect.selectedIndex > 0 ? companySelect.value : null;

      let url = '';
      let data = '';

      console.log(yearFilter.value);
      console.log(monthFilter.value);
      console.log(flightFilter.value);

      // console.log(agentSelect);
      // console.log(companySelect);

      if (agentId && !companyId) {
        data += `agentId=${agentId}`;
      } else if (companyId && !agentId) {
        data += `companyId=${companyId}`;
      } else {
        document.getElementById('result-container').innerHTML = '<p>Please select either an agent or a client, not both.</p>';
        return;
      }

      // Determine whether to use the date filter or the flight filter
      if (monthFilter.value !== "Select month" && yearFilter.value !== "Select year" && agentId !== null) {
        // Use Month & Year (Orig Preview SoA)
        url = '../Agent Section/functions/fetchSoaAgent.php';
        data += `&month=${monthFilter.value}&year=${yearFilter.value}`;
      }
      else if (flightFilter.value !== "Select Flight Date" && agentId !== null) {
        // Use Flight ID (Flight Date Preview SoA)
        url = '../Agent Section/functions/fetchSoAByFlightDateAgent.php';
        data += `&flightId=${flightFilter.value}`;
      }
      else if (monthFilter.value !== "Select month" && yearFilter.value !== "Select year" && companyId !== null) {
        // Use Flight ID (Flight Date Preview SoA)
        url = '../Agent Section/functions/fetchSoA.php';
        data += `&month=${monthFilter.value}&year=${yearFilter.value}`;
      }
      else if (flightFilter.value !== "Select Flight Date" && companyId !== null) {
        // Use Flight ID (Flight Date Preview SoA)
        url = '../Agent Section/functions/fetchSoAByFlightDate.php';
        data += `&flightId=${flightFilter.value}`;
      }
      else {
        // Handle case where no filter is selected
        document.getElementById('result-container').innerHTML = '<p>Please select valid filters.</p>';
        return;
      }

      // Disable the button while the request is in progress
      document.getElementById('generate-soa-btn').disabled = true;

      // Show a loading indicator
      const resultContainer = document.getElementById('result-container');
      resultContainer.innerHTML = '<p>Loading...</p>';

      // Send data to PHP using AJAX
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      xhr.onload = function () {
        // Re-enable the button after the request is complete
        document.getElementById('generate-soa-btn').disabled = false;

        if (xhr.status === 200) {
          // Parse the JSON response
          const response = JSON.parse(xhr.responseText);

          if (response.dataAvailable) {
            // Update the result container with the HTML from the response
            console.log(response);
            resultContainer.style.display = "block";
            resultContainer.innerHTML = response.htmlContent;
            // Enable the download button if data is available
            // Attach export listener AFTER the content is injected
            const downloadBtn = document.getElementById('download-btn');
            if (downloadBtn) {
              downloadBtn.disabled = false;
              downloadBtn.onclick = function () {
                exportSOAToExcel(response.soaNumber); // Assuming you have this ID or pass it here
              };
            }
          } else {
            // If no data available, update the result container and disable the button
            resultContainer.innerHTML = '<p>No data found for the selected filters.</p>';
            resultContainer.style.display = "block";
            document.getElementById('download-btn').disabled = true;
          }
        } else {
          // Handle errors in the request
          resultContainer.style.display = "block";
          resultContainer.innerHTML = '<p>Error loading data. Please try again later.</p>';
          document.getElementById('download-btn').disabled = true;
        }
      };

      xhr.onerror = function () {
        // Handle network errors
        resultContainer.innerHTML = '<p>Network error. Please check your connection and try again.</p>';
        document.getElementById('generate-soa-btn').disabled = false;
        document.getElementById('download-btn').disabled = true;
      };

      console.log(data);

      // Send the data to the server
      xhr.send(data);
    });
  </script>

  <!-- Generate SOA (excel)-->
  <script>
    document.getElementById('download-btn').addEventListener('click', function () {
      const agentId = document.getElementById('agent-filter').value;
      const companyId = document.getElementById('company-filter').value;
      const flightDate = document.getElementById('flight-filter').value;
      const month = document.getElementById('month-filter').value;
      const year = document.getElementById('year-filter').value;

      const isAgentSelected = agentId && agentId !== "Select Agent";
      const isCompanySelected = companyId && companyId !== "Select Travel Agency";
      const isFlightSelected = flightDate && flightDate !== "Select Flight Date";
      const isMonthYearSelected = month && month !== "Select month" && year && year !== "Select year";

      // Validate Agent/Company
      if ((isAgentSelected && isCompanySelected) || (!isAgentSelected && !isCompanySelected)) {
        alert("Please select either an Agent OR a Travel Agency.");
        return;
      }

      // Validate Flight OR Month+Year
      if ((isFlightSelected && isMonthYearSelected) || (!isFlightSelected && !isMonthYearSelected)) {
        alert("Please select either a Flight Date OR a Month and Year.");
        return;
      }

      const currentDate = new Date();
      const currentDateFormatted = `${currentDate.getFullYear()}-${(currentDate.getMonth() + 1).toString().padStart(2, '0')}-${currentDate.getDate().toString().padStart(2, '0')}`;

      let accountType = isAgentSelected ? "agent" : "company";
      let accountId = isAgentSelected ? agentId : companyId;

      // Step 1: Request SOA number
      const xhrAddSoA = new XMLHttpRequest();
      xhrAddSoA.open('POST', '../Agent Section/functions/agent-addSoA.php', true);
      xhrAddSoA.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhrAddSoA.responseType = 'json';

      xhrAddSoA.onload = function () {
        if (xhrAddSoA.status === 200 && xhrAddSoA.response && xhrAddSoA.response.soanum) {
          const soaNumber = xhrAddSoA.response.soanum;

          // Step 2: Generate Excel from your SOA table
          exportSOAToExcel(soaNumber);

        } else {
          alert("Failed to generate SOA number.");
        }
      };

      xhrAddSoA.onerror = function () {
        alert("An error occurred while inserting SOA data.");
      };

      xhrAddSoA.send(
        `accountType=${accountType}&accountId=${accountId}&flightDate=${flightDate}&month=${month}&year=${year}&currentDate=${currentDateFormatted}`
      );
    });

    function exportSOAToExcel(soaNumber) {
      const table = document.getElementById("soaTable");
      const tbodyRows = table?.querySelectorAll("tbody tr");

      if (!table || !tbodyRows || tbodyRows.length === 0) {
        alert("No SOA data available to export.");
        return;
      }

      let data = [];

      // Extract headers
      const thead = table.querySelector("thead");
      if (thead) {
        const headers = Array.from(thead.rows[0].cells).map(cell => cell.innerText.trim());
        data.push(headers);
      }

      // Extract rows
      tbodyRows.forEach(row => {
        const rowData = Array.from(row.cells).map(cell => {
          const selects = cell.querySelectorAll("select");
          if (selects.length > 0) {
            return Array.from(selects).map(s => s.options[s.selectedIndex]?.text || "").join(", ");
          } else {
            return cell.innerText.trim();
          }
        });
        data.push(rowData);
      });

      // Generate and download Excel
      const ws = XLSX.utils.aoa_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "SOA");
      XLSX.writeFile(wb, `SOA-${soaNumber}.xlsx`);
    }

  </script>



  <!-- Generate SOA (pdf) -->
  <!-- <script>
    document.getElementById('download-btn').addEventListener('click', function () {
      const companyId = document.getElementById('company-filter').value;
      const month = document.getElementById('month-filter').value;
      const year = document.getElementById('year-filter').value;

      const currentDate = new Date();
      const currentDateFormatted = `${currentDate.getFullYear()}-${(currentDate.getMonth() + 1)
        .toString()
        .padStart(2, '0')}-${currentDate.getDate().toString().padStart(2, '0')}`;

      // Step 1: Send the request to insert SOA data and get SOA number
      const xhrAddSoA = new XMLHttpRequest();
      xhrAddSoA.open('POST', '../Agent Section/functions/agent-addSoA.php', true);
      xhrAddSoA.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhrAddSoA.responseType = 'json';

      xhrAddSoA.onload = function () {
        if (xhrAddSoA.status === 200 && xhrAddSoA.response.soanum) {
          const soaNumber = xhrAddSoA.response.soanum;

          // Step 2: Fetch data for Excel
          const xhrExcel = new XMLHttpRequest();
          xhrExcel.open('POST', '../Agent Section/functions/generateSoA.php', true);
          xhrExcel.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhrExcel.responseType = 'json';

          xhrExcel.onload = function () {
            if (xhrExcel.status === 200 && Array.isArray(xhrExcel.response)) {
              const soaData = xhrExcel.response;

              // Step 3: Generate Excel
              const ws = XLSX.utils.json_to_sheet(soaData);
              const wb = XLSX.utils.book_new();
              XLSX.utils.book_append_sheet(wb, ws, "SOA");

              XLSX.writeFile(wb, `Statement_of_Account_${soaNumber}.xlsx`);
            } else {
              alert("Failed to fetch SOA data for Excel export.");
            }
          };

          xhrExcel.onerror = function () {
            alert("An error occurred while fetching SOA data for Excel.");
          };

          xhrExcel.send(
            `companyId=${companyId}&month=${month}&year=${year}&currentDate=${currentDateFormatted}&soaNumber=${soaNumber}`
          );
        } else {
          alert("Failed to generate SOA number.");
        }
      };

      xhrAddSoA.onerror = function () {
        alert("An error occurred while inserting SOA data.");
      };

      xhrAddSoA.send(
        `companyId=${companyId}&month=${month}&year=${year}&currentDate=${currentDateFormatted}`
      );
    });
  </script> -->

  <!-- <script>
  //   document.getElementById('download-btn').addEventListener('click', function() {
  //     const companyId = document.getElementById('company-filter').value;
  //     const month = document.getElementById('month-filter').value;
  //     const year = document.getElementById('year-filter').value;

  //     // Get current date in mm/dd/yyyy format
  //     const currentDate = new Date();
  //     const currentDateFormatted = (currentDate.getMonth() + 1).toString().padStart(2, '0') + '/' +
  //       currentDate.getDate().toString().padStart(2, '0') + '/' +
  //       currentDate.getFullYear();

  //     // First, send the request to agent-addSoA.php to insert SOA data
  //     const xhrAddSoA = new XMLHttpRequest();
  //     xhrAddSoA.open('POST', '../Agent Section/functions/agent-addSoA.php', true);
  //     xhrAddSoA.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  //     xhrAddSoA.responseType = 'json'; // Expect JSON response for the SOA number

  //           if (response.soanum) {
  //             const soaNumber = response.soanum; // Get the generated SOA number

  //             // Proceed to generate the SOA PDF
  //             const xhrPdf = new XMLHttpRequest();
  //             xhrPdf.open('POST', '../Agent Section/functions/generateSoA.php', true);
  //             xhrPdf.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  //             xhrPdf.responseType = 'blob';

  //             xhrPdf.onload = function () {
  //               if (xhrPdf.status === 200) {
  //                 // Create a link to download the PDF
  //                 const blob = new Blob([xhrPdf.response], {
  //                   type: 'application/pdf'
  //                 });
  //                 const link = document.createElement('a');
  //                 link.href = window.URL.createObjectURL(blob);
  //                 link.download = `Statement_of_Account_${soaNumber}.pdf`;
  //                 link.click();
  //               } else {
  //                 alert('Failed to generate the SOA PDF. Please try again.');
  //               }
  //             };

  //             xhrPdf.onerror = function () {
  //               alert('An error occurred while generating the SOA PDF.');
  //             };

  //             // Send the request to generate the SOA PDF with the SOA number
  //             xhrPdf.send(`companyId=${companyId}&month=${month}&year=${year}&currentDate=${currentDateFormatted}&soaNumber=${soaNumber}`);
  //           } else {
  //             alert('Failed to generate SOA Number. Please try again.');
  //           }
  //         } else {
  //           alert('Failed to insert SOA number. Server error: ' + xhrAddSoA.statusText);
  //         }
  //       };

  //       xhrAddSoA.onerror = function () {
  //         alert('An error occurred while processing the request to insert SOA data.');
  //       };

  //     // Send the request with the necessary values for SOA number
  //     xhrAddSoA.send(`companyId=${companyId}&month=${month}&year=${year}&currentDate=${currentDateFormatted}`);
  //   });
  // </script> -->

  <!-- Modal -->
  <!-- <script>
  function openModal(row) 
  {
    const transactNo = row.getAttribute('data-transact-no'); // Get the transact number
    const modalContent = document.getElementById('modal-content');
    modalContent.innerHTML = `<p>${transactNo}</p>`; // Update modal content

    // Use Bootstrap's modal methods to show the modal
    const modal = new bootstrap.Modal(document.getElementById('staticBackdrop-tablerows'));
    modal.show();
  }

  function closeModal() 
  {
    // Use Bootstrap's modal methods to hide the modal
    const modal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
    modal.hide();
  }
  </script> -->

  <!-- Row Select -->
  <!-- <script>
  document.addEventListener("DOMContentLoaded", function() 
  {
    document.querySelectorAll("tr[data-url]").forEach(function(row) 
    {
      row.addEventListener("click", function() 
      {
        const transactionNumber = row.getAttribute("data-url").split('=')[1]; // Extract transaction number from the URL

        console.log("Transaction Number: ", transactionNumber); // Debugging line

        // Use AJAX to send the transaction number to the server
        $.ajax(
        {
          url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file to handle the session setting
          type: 'POST',
          data: { transaction_number: transactionNumber },
          success: function(response) 
          {
            console.log("Response: ", response); // Debugging line

            // Redirect to the next page after successfully setting the session
            window.location.href = row.getAttribute("data-url"); // Use the original URL stored in data-url attribute
          },
          error: function(xhr, status, error) 
          {
            console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
          }
        });
      });
    });
  });
  </script> -->

  <!-- <script>
  const table = $('#product-table').DataTable({
    dom: 'rtip',
    columnDefs: [{
      width: '12%',
      targets: 0
    }, // Transact No.
    {
      width: '22%',
      targets: 1
    }, // To/From
    {
      width: '6%',
      targets: 2
    }, // Pax
    {
      width: '15%',
      targets: 3
    }, // Booking Type
    {
      width: '12%',
      targets: 4
    }, // Package Price
    {
      width: '12%',
      targets: 5
    }, // Amount to be Paid
    {
      width: '12%',
      targets: 6
    }, // Amount Paid
    {
      width: '9%',
      targets: 7
    } // Status


    ],
    language: {
      emptyTable: "No Transaction Records Available"
    },
    order: [
      [0, 'desc']
    ],
    scrollX: false,
    autoWidth: false,
    pageLength: 10, // Limit the number of rows per page to 8
  });
  </script> -->


</body>

</html>