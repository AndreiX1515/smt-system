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
	<link rel="stylesheet"
		href="../Employee Section/assets/css/emp-transactionRequestHistory.css?v=<?php echo time(); ?>">
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
						<h5 class="header-title">Payment</h5>
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
										<input type="text" id="FlightStartDate" class="filter-input"
											placeholder="Flight Date">

										<i class="fas fa-calendar-alt filter-calendar-icon"></i>
									</div>

								</div>

							</div>

							<div class="filter-buttons">
								<button id="clearSorting" class="btn-material">
									<svg class="reset-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
										<path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z" />
									</svg>
								</button>
							</div>

						</div>
					</div>

				</div>

				<div class="table-content-body">
					<div class="table-container">
						<table id="payment-table" class="table payment-table">
							<thead>
								<tr>
									<th>TRANSACTION NO</th>
									<th>BRANCH</th>
									<th>FLIGHT DATE</th>
									<th>AMOUNT</th>
									<th>PROOF OF PAYMENT</th>
									<th>PAYMENT DATE</th>
									<th>STATUS</th>
									<th>REMARKS</th>
									<th style="display: none;">RAW PAYMENT DATE</th>
								</tr>
							</thead>
							<tbody>
								<?php
									$sql1 = "SELECT b.transactNo, p.paymentId, p.amount, p.filePath, p.paymentDate, p.paymentStatus, p.paymentRemarks, 
											br.branchName, f.flightDepartureDate AS flightDate
											FROM `booking` b
											JOIN flight f ON b.flightId = f.flightId
											JOIN `payment` p ON b.transactNo = p.transactNo
											JOIN `branch` br ON br.branchAgentCode = b.agentCode
											ORDER BY p.paymentId ASC";

									// Execute the query
									$result1 = $conn->query($sql1);

									// Check if query execution was successful
									if (!$result1) {
										die("Query error: " . $conn->error);
									}

									// Fetch results and display rows
									if ($result1->num_rows > 0) {
										while ($row = $result1->fetch_assoc()) {
											$amount = number_format($row['amount'], 2);
											$date = date("m.d.Y", strtotime($row['paymentDate']));
											
											$status = isset($row['paymentStatus']) ? $row['paymentStatus'] : 'Unknown';
											$statusClass = '';

											switch ($status) {
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

											$remarks = !empty($row['paymentRemarks']) ? $row['paymentRemarks'] : 'N/A';
											$remarksClass = '';

											// Format remarks - uppercase first character
											$remarksFormatted = ucfirst(strtolower($remarks));

											switch ($remarksFormatted) {
												case 'Good':
													$remarksClass = 'bg-success text-white'; // Green background, white text
													break;
												case 'Rejected':
													$remarksClass = 'bg-danger text-white'; // Red background, white text
													break;
												case 'Submitted':
													$remarksClass = 'bg-warning text-dark';
													break;
												default:
													$remarksClass = 'bg-secondary text-white';
											}

											

											$flightDate = $row['flightDate'];
											$formattedFlightDate = date('Y.m.d', strtotime($flightDate));
											$fomattedPaymentDate = date('Y-m-d', strtotime($row['paymentDate']));

											echo "<tr>
													<td>" . $row['transactNo'] . "</td>
													<td>" . $row['branchName'] . "</td>
													<td>" . $formattedFlightDate . "</td>
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
													</td>";

											if ($remarks !== 'N/A') {
												echo "<td>
														$remarks
													  </td>";
											} else {
												echo "<td></td>";
											}

											echo "  <td style='display: none;'>" . $fomattedPaymentDate . "</td>
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
			const table = $('#payment-table').DataTable({
				dom: 'rtip',
				language: {
					emptyTable: "No Transaction Records Available"
				},
				order: [[4, 'asc']], // Sort by payment date
				scrollX: false,
				paging: true,
				pageLength: 17,
				autoWidth: false,
				autoHeight: false,
				columnDefs: [
					{
						targets: [1, 3, 6], // Disable sorting for selected columns
						orderable: false
					}
				]
			});

			// General Search
			$('#search').on('keyup', function () {
				table.search(this.value).draw();
			});

			// Flight Date Filter (RAW value from hidden column)
			$('#FlightStartDate').datepicker({
				dateFormat: "yy.mm.dd",
				showAnim: "fadeIn",
				changeMonth: true,
				changeYear: true,
				yearRange: "1900:2100",
				onSelect: function (dateText) {
					$(this).val(dateText);
					table.column(2).search(dateText || '').draw(); 
				}
			});

			// Clear Filters
			$('#clearSorting').on('click', function () {
				$('#search').val('');
				$('#FlightStartDate').val('').trigger('change');
				table.search('').columns().search('').draw();
			});

			// Pagination Controls
			function updatePagination() {
				const info = table.page.info();
				const currentPage = info.page + 1;
				const totalPages = info.pages;
				$('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);
				$('#prevPage').prop('disabled', currentPage === 1);
				$('#nextPage').prop('disabled', currentPage === totalPages);
			}

			$('#prevPage').on('click', function () {
				table.page('previous').draw('page');
				updatePagination();
			});

			$('#nextPage').on('click', function () {
				table.page('next').draw('page');
				updatePagination();
			});

			updatePagination(); // Initialize on load
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