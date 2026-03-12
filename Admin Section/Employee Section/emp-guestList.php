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
						<h5 class="header-title">Guest List</h5>
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

									<!-- For date input -->
									<div class="filter-input-with-icon--input">
										<input type="text" placeholder="Select Flight Date">
										<i class="fas fa-calendar filter-calendar-icon"></i>
									</div>

									<!-- For select dropdown -->
									<div class="filter-input-with-icon--select">
										<select id="packages" class="filter-select">
											<option value="All" disabled selected>Select Branch</option>
											<?php
											$sql1 = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
											$res1 = $conn->query($sql1);
											if ($res1->num_rows > 0) {
												while ($row = $res1->fetch_assoc()) {
													echo "<option value='" . $row['branchName'] . "'>" . $row['branchName'] . "</option>";
												}
											} else {
												echo "<option value=''>No branches available</option>";
											}
											?>
										</select>

										<i class="fas fa-chevron-down filter-calendar-icon"></i>
									</div>

								</div>

							</div>

							<!-- Clear Button -->
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
						<table class="table guestList-table" id="guestList-table"
							aria-describedby="product-table-caption">
							<thead>
								<tr>
									<th>TRANSACTION NO</th>
									<th>FULLNAME</th>
									<th>AGE</th>
									<th>D.O.B</th>
									<th>NATIONALITY</th>
									<th>PASSPORT ID</th>
									<th>DATE OF EXP.</th>
									<th>SEX</th>
									<th>ROOMING</th>
									<th>DEPARTURE DATE</th>
								</tr>
							</thead>
							<tbody>
								<?php
								// Ensure valid database connection
								if (!isset($conn) || $conn->connect_error) {
									die("Database connection error: " . ($conn->connect_error ?? 'Unknown error.'));
								}

								// SQL query for guest details
								$sql = "SELECT g.guestId, g.transactNo, g.fName, g.mName, g.lName, g.suffix, g.birthdate, g.age, g.sex, g.nationality, 
								g.passportNo, g.passportExp, f.flightDepartureDate, rl.roomType
								FROM `guest` g
								JOIN `booking` b ON g.transactNo = b.transactNo
								JOIN `flight` f ON b.flightId = f.flightId
								JOIN `roominglist` rl ON g.guestId = rl.guestId
								WHERE b.status = 'Confirmed'
								ORDER BY f.flightDepartureDate ASC";

								// Execute the query
								$result = $conn->query($sql);

								// Check if query execution was successful
								if (!$result) {
									die("Query error: " . $conn->error);
								}

								// Fetch results and display rows
								if ($result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
										if ($row['suffix'] === 'N/A') {
											$row['suffix'] = '';
										}

										// Sanitize and format guest name
										$guestName = $row['fName'] . ' ' . $row['suffix'] . ' ' . $row['lName'];


										// Format dates
										$birthdate = !empty($row['birthdate']) ? date('Y M d', strtotime($row['birthdate'])) : 'N/A';


										$departureDate = !empty($row['flightDepartureDate']) ? date('Y-m-d', strtotime($row['flightDepartureDate'])) : 'N/A';

										echo "<tr>
												<td>" . $row['transactNo'] . "</td>
												<td>" . $guestName . "</td>
												<td>" . $row['age'] . "</td>

												<td>" . $birthdate . "</td>
											
												<td>" . $row['nationality'] . "</td>
												<td>" . $row['passportNo'] . "</td>
												<td>" . $row['passportExp'] . "</td>
												<td>" . $row['sex'] . "</td>
												<td>" . $row['roomType'] . "</td>
												<td>" . $departureDate . "</td>
											</tr>";
									}
								} else {
									echo "<tr>
										<td colspan='12'>No records found.</td>
									</tr>";
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
			const table = $('#guestList-table').DataTable({
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
				dateFormat: "yy-mm-dd",
				showAnim: "fadeIn",
				changeMonth: true,
				changeYear: true,
				yearRange: "1900:2100",
				onSelect: function (dateText) {
					$(this).val(dateText);
					table.column(8).search(dateText || '').draw();
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