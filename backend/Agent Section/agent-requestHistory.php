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

          <!-- <div class="page-header-top">
            <div class="back-btn-wrapper">
              <button class="back-btn" id="redirect-btn">
                <i class="fas fa-chevron-left"></i>
              </button>
            </div>
          </div> -->

          <div class="page-header-content">
            <div class="page-header-text">
              <h5 class="header-title">Requests</h5>
            </div>
          </div>

        </div>
      </div>

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
                  <th>TRANSACT NO</th>
                  <th>FLIGHT DATE</th>
                  <th>REQUEST DETAILS</th>
                  <th>SPECIFIC DETAILS</th>
                  <th>TOTAL PAX</th>
                  <th>TOTAL AMOUNT</th>
                  <th>REQUEST DATE</th>
                  <th>STATUS</th>
                  <th>REQUEST REMARKS</th>
                  <th style='display:none;'>RAW REQUEST DATE</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  if ($agentRole != 'Head Agent')
                  {
                    $sql1 = "SELECT b.transactNo, r.requestId, r.pax, r.requestCost, r.requestDate, r.requestStatus, r.requestRemarks,
                              cd.details, cd.price, r.details as requestDetails, f.flightDepartureDate
                            FROM `booking` b
                            JOIN flight f ON b.flightId = f.flightId
                            JOIN `request` r ON b.transactNo = r.transactNo
                            JOIN `concerndetails` cd ON r.concerndetailsId = cd.concerndetailsId
                            WHERE b.accountId = $accountId
                            ORDER BY r.requestId ASC";

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
                        $amount = number_format($row['requestCost'], 2);
                        $date = date("F d, Y", strtotime($row['requestDate']));
                        $remarks = !empty($row['requestRemarks']) ? $row['requestRemarks'] : 'N/A';

                        $formattedFlightDate = date("Y.m.d", strtotime($row['flightDepartureDate']));

                        $status = isset($row['requestStatus']) ? $row['requestStatus'] : 'Unknown';
                        $statusClass = '';

                        switch ($status) 
                        {
                          case 'Confirmed':
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

                        echo "<tr>
                                <td>" . $row['transactNo'] . "</td>
                                <td>" . $formattedFlightDate . "</td>
                                <td>" . $row['details'] . "</td>
                                <td>" . $row['requestDetails'] . "</td>
                                <td>" . $row['pax'] . "</td>
                                
                                <td>₱ " . $amount . "</td>
                                <td>" . $date . "</td>
                                <td>
                                  <span class='badge p-2 rounded-pill {$statusClass}'>
                                    {$status}
                                  </span>
                                </td>
                                <td>" . $remarks . "</td>
                                <td style='display:none;'>{$row['requestDate']}</td> <!-- hidden raw date -->
                              </tr>";
                      }
                    }
                  }
                  else
                  {
                    $sql1 = "SELECT b.transactNo, r.requestId, r.pax, r.requestCost, r.requestDate, r.requestStatus, r.requestRemarks,
                              cd.details, cd.price, r.details as requestDetails, f.flightDepartureDate
                            FROM `booking` b
                            JOIN flight f ON b.flightId = f.flightId
                            JOIN `request` r ON b.transactNo = r.transactNo
                            JOIN `concerndetails` cd ON r.concerndetailsId = cd.concerndetailsId
                            WHERE b.agentCode = '$agentCode'
                            ORDER BY r.requestId ASC";

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
                        $amount = number_format($row['requestCost'], 2);
                        $date = date("F d, Y", strtotime($row['requestDate']));
                        $remarks = !empty($row['requestRemarks']) ? $row['requestRemarks'] : 'N/A';

                        $formattedFlightDate = date("Y.m.d", strtotime($row['flightDepartureDate']));

                        $status = isset($row['requestStatus']) ? $row['requestStatus'] : 'Unknown';
                        $statusClass = '';

                        switch ($status) 
                        {
                          case 'Confirmed':
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

                        echo "<tr>
                                <td>" . $row['transactNo'] . "</td>
                                <td>" . $formattedFlightDate . "</td>
                                <td>" . $row['details'] . "</td>
                                <td>" . $row['requestDetails'] . "</td>
                                <td>" . $row['pax'] . "</td>
                                
                                <td>₱ " . $amount . "</td>
                                <td>" . $date . "</td>
                                <td>
                                  <span class='badge p-2 rounded-pill {$statusClass}'>
                                    {$status}
                                  </span>
                                </td>
                                <td>" . $remarks . "</td>
                                <td style='display:none;'>{$row['requestDate']}</td> <!-- hidden raw date -->
                              </tr>";
                      }
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
	$(document).ready(function () 
	{
		const table = $('#product-table').DataTable(
		{
			dom: 'rtip',  // Use only the relevant table elements
			language: 
			{
				emptyTable: "No Transaction Records Available"
			},
			order: [[5, 'asc']],  // Default sorting by Transaction ID (descending)
			scrollX: false,
			scrollY: '69vh',  // Set a fixed height for the table (adjust as necessary)
			paging: true,  // Enable pagination
			pageLength: 15,  // Set the number of rows per page
			autoWidth: false,
			autoHeight: false,  // Prevent automatic height adjustment

			// Disable sorting for specific columns
			columnDefs: 
			[
				{
					targets: [1, 2, 3,  5, 6,], // Disable sorting for 2nd and 4th columns
					orderable: false
				}
			]
		});

		// Search Functionality
		$('#search').on('keyup', function () 
		{
			table.search(this.value).draw();
		});

		// Update the custom pagination buttons and page info
		function updatePagination() 
		{
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
		$('#prevPage').on('click', function() 
		{
			table.page('previous').draw('page');
			updatePagination();
		});

		$('#nextPage').on('click', function() 
		{
			table.page('next').draw('page');
			updatePagination();
		});

		// Initialize pagination on first load
		updatePagination();

		// Status Filter
		$('#status').on('change', function () 
		{
			const selectedStatus = $(this).val();
			table.column(8).search(selectedStatus || '').draw();
		});

		// Package Filter
		$('#packages').on('change', function () 
		{
			const selectedPackage = $(this).val();
			table.column(2).search(selectedPackage || '').draw();
		});

		// Booking Date Filter with value change
		$('#BookingStartDate').on('change', function () 
		{
			const selectedBookingDate = $(this).val();  // Get the selected value directly from the input field
			console.log("Booking Date Filter:", selectedBookingDate);  // Log the selected booking date
			table.column(3).search(selectedBookingDate || '').draw();  // Column 4 (index starts at 0)
		});

		// Flight Date Filter with value change
		$('#FlightStartDate').on('change', function () 
		{
			const selectedFlightDate = $(this).val();  // Get the selected value directly from the input field
			console.log("Flight Date Filter:", selectedFlightDate);  // Log the selected flight date
			table.column(8).search(selectedFlightDate || '').draw();  // Column 5 (index starts at 0)
		});

		// Apply datepicker and input validation for FlightStartDate
		$("#FlightStartDate").datepicker(
		{
			dateFormat: "yy-mm-dd", // Set the format to MM-DD-YYYY
			showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
			changeMonth: true, // Allow the month to be changed from the dropdown
			changeYear: true,  // Allow the year to be changed from the dropdown
			yearRange: "1900:2100", // Set a range of years (optional)
			onSelect: function(dateText) 
			{
				// When a date is selected, update the input field with the date
				$(this).val(dateText);
				flightStartDate = dateText; // Store the selected date
				console.log("FlightStartDate Selected Date (onSelect): " + dateText);
				table.column(8).search(flightStartDate || '').draw();  // Column 5 (index starts at 0)
			}
		});

		// Apply datepicker and input validation for BookingStartDate
		$("#BookingStartDate").datepicker(
		{
			dateFormat: "mm-dd-yy", // Set the format to MM-DD-YYYY
			showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
			changeMonth: true, // Allow the month to be changed from the dropdown
			changeYear: true,  // Allow the year to be changed from the dropdown
			yearRange: "1900:2100", // Set a range of years (optional)
			onSelect: function(dateText) 
			{
				// When a date is selected, update the input field with the date
				$(this).val(dateText);
				bookingStartDate = dateText; // Store the selected date
				console.log("FlightStartDate Selected Date (onSelect): " + dateText);
				table.column(4).search(bookingStartDate || '').draw();  // Column 5 (index starts at 0)
			}
		});

		// BookingStartDate Input Validation and Formatting
		$("#BookingStartDate").on("input", function () 
		{
			var value = $(this).val();

			// Remove non-numeric and non-dash characters
			value = value.replace(/[^\d-]/g, '');

			// Automatically add dashes in the correct places if necessary
			if (value.length > 2 && value.charAt(2) !== '-') 
			{
				value = value.substring(0, 2) + '-' + value.substring(2);
			}
			if (value.length > 5 && value.charAt(5) !== '-') 
			{
				value = value.substring(0, 5) + '-' + value.substring(5);
			}

			// Limit the total input length to 10 characters (MM-DD-YYYY)
			if (value.length > 10) 
			{
				value = value.substring(0, 10);
			}

			// Update the input field value
			$(this).val(value);

			// Reset or update the bookingStartDate variable
			if (value === "") 
			{
				bookingStartDate = ""; // Reset the variable if the input is cleared
			} 
			else 
			{
				bookingStartDate = value; // Update the variable with the formatted value
			}

			// Update the table column search
			table.column(5).search(bookingStartDate || '').draw(); // Column 5 (index starts at 0)

			console.log("BookingStartDate Input Value (on input): " + value);
		});

		// Clear All Filters
		$('#clearSorting').on('click', function () 
		{
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
			$('#FlightStartDate').val('').trigger('change');  // Reset and trigger input for FlightStartDate

			// Redraw the table
			table.draw();
		});
	});
</script>

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

</body>

</html>