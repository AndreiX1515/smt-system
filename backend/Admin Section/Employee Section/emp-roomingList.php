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
	<link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionRoomingList 2.css?v=<?php echo time(); ?>">
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
						<h5 class="header-title">Rooming List</h5>
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
					<div class="content-rows">

						<!-- Select Branch -->
						<div class="columns">
							<!-- Header Div for Label -->
							<div class="header-wrapper">
								<label for="branch">Branch:</label>
							</div>

							<!-- For select dropdown -->
							<div class="filter-input-with-icon--select">
								<select id="branch" class="filter-select">
									<option disabled selected>Select Branch</option>
									<?php
									$sql1 = "SELECT branchId, branchAgentCode, branchName FROM branch ORDER BY branchName ASC";
									$result = $conn->query($sql1);

									if ($result->num_rows > 0) {
										while ($row = $result->fetch_assoc()) {
											echo "<option value='" . $row['branchAgentCode'] . "'>" . $row['branchName'] . "</option>";
										}
									} else {
										echo "<option value='' disabled>No flights available</option>";
									}
									?>
								</select>

								<i class="fas fa-chevron-down filter-calendar-icon"></i>
							</div>

						</div>

						<!-- Flight Date Select -->
						<div class="columns">
							<!-- Header Div for Label -->
							<div class="header-wrapper">
								<label for="flightDate">Flight Date:</label>
							</div>

							<!-- <div class="select-wrapper">
								<select id="flightDate" class="form-control" required>
									<option disabled selected>Select a Flight Date</option>
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
							</div> -->

							<div class="filter-input-with-icon--select">
								<select id="flightDate" class="filter-select" required>
									<option disabled selected>Select a Flight Date</option>
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

								<i class="fas fa-chevron-down filter-calendar-icon"></i>
							</div>

						</div>

						<!-- button for getting guests -->
						<div class="btnGetGuests">
							<button type="button" id="btnGetGuests" class="btn btn-primary btn-sm w-100">
								Select
							</button>
						</div>

					</div>

					<!-- Select Guest Filter -->
					<div class="content-rows">

						<div class="columns">
							<div class="header-wrapper">
								<label for="guestName">Guests:</label>
							</div>

							<div class="select-wrapper">
								<select id="guestName" class="form-control guestListSelect" multiple></select>

								<?php
								$luggageOptions = [];
								$guestLuggage = []; // Stores guestId → selected luggage mapping
								
								// Fetch luggage options
								$query = "SELECT concernDetailsId, details FROM concerndetails";
								$result = $conn->query($query);
								if ($result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
										$luggageOptions[] = $row;
									}
								}

								// Fetch stored luggage selections for each guest
								$query = "SELECT g.guestId, cd.concernDetailsId 
									FROM guestluggage g
									JOIN concerndetails cd ON g.luggageType = cd.concernDetailsId"; // Adjust your table name
								$result = $conn->query($query);

								if ($result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
										$guestLuggage[$row['guestId']] = $row['concernDetailsId'];
									}
								}

								// Pass data to JavaScript
								echo "<script>
									let luggageOptions = " . json_encode($luggageOptions) . ";
									let guestLuggage = " . json_encode($guestLuggage) . ";
									</script>";
								?>
							</div>
							
						</div>

						<div class="columns room-type-wrapper">

							<div class="room-type-select">

								<div class="header-wrapper">
									<label for="roomType">Room Type:</label>
								</div>

								<div class="room-type-content">
									<!-- <div class="select-wrapper">
										<select id="roomType" class="form-control">
											<option value="Single">Single Supplement (Max 1)</option>
											<option value="Twin">Twin Room (Max 2)</option>
											<option value="Double">Double Room (Max 2)</option>
											<option value="Triple">Triple Room (Min 2 - Max 3)</option>
										</select>
									</div> -->

									<div class="filter-input-with-icon--select">
										<select id="roomType" class="filter-select">
											<option value="Single">Single Supplement (Max 1)</option>
											<option value="Twin">Twin Room (Max 2)</option>
											<option value="Double">Double Room (Max 2)</option>
											<option value="Triple">Triple Room (Min 2 - Max 3)</option>
										</select>

										<i class="fas fa-chevron-down filter-calendar-icon"></i>
									</div>

									
								</div>

							</div>

							<div class="assign-btn-wrapper">
								<button class="btn btn-primary btn-sm" onclick="assignRoom()">Assign</button>
							</div>



						</div>
					</div>

				</div>

				<div class="table-content-body">



					<div class="table-container">
						<table class="table assigned-rooms-table">
							<thead class="thead-dark">
								<tr>
									<th>#</th>
									<th>AGE</th>
									<th>GIVEN NAME</th>
									<th>SURNAME</th>
									<th>FULLNAME</th>
									<th>DOB</th>
									<th>NATIONALITY</th>
									<th>PASSPORT</th>
									<th>EXPIRATION DATE</th>
									<th>SEX</th>
									<th>ROOMING</th>
									<th>LUGGAGE (AIR TICKET)</th>
									<th></th>
								</tr>
							</thead>

							<tbody id="assignedRoomsTable"></tbody>
						</table>
					</div>

					<div class="table-footer">
						<button class="btn btn-primary btn-sm" onclick="generateExcel()">Download</button>
						<button class="btn btn-success btn-sm" id="saveAssignments">Save</button>
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

	<!-- Modal -->
	<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel"
		aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form action="../Employee Section/functions/emp-requestUpdateAmount-code.php" method="POST">
					<div class="modal-body">
						<div class="mb-3">
							<label for="transactNo" class="form-label fw-bold">Transaction Number:</label>
							<span id="transactNo" class="text-primary"></span>
						</div>

						<input type="hidden" id="modalRequestId" name="requestId">

						<div class="mb-3">
							<label for="requestAmount" class="form-label">Enter Total Amount:</label>
							<input type="number" id="requestAmount" name="requestAmount" class="form-control"
								placeholder="Enter amount in PHP" step="0.01" min="0" required>
						</div>
					</div>

					<div class="modal-footer">
						<button type="submit" name="updatePrice" class="btn btn-primary">Update Price</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php include '../Employee Section/includes/emp-scripts.php' ?>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

	<!-- Combined script working -->
	<script>
		let guests = [];  // Stores all guests fetched from PHP
		let availableGuests = [];  // Stores guests available for assignment
		let rooms = [];
		let removedLuggage = [];

		document.getElementById('saveAssignments').addEventListener('click', function () {
			let roomAssignments = [];
			let luggageAssignments = [];

			$.ajax(
				{
					url: '../Agent Section/functions/fetchMaxRoomNumber.php', // Create this PHP file
					type: 'GET',
					dataType: 'json',
					success: function (response) {
						let maxRoomNumber = response.maxRoomNumber || 0; // Start from 0 if no existing data

						let roomAssignments = [];
						let luggageAssignments = [];

						// Assign room numbers dynamically
						rooms.forEach((room) => {
							maxRoomNumber++; // Increment for the next room

							room.guests.forEach(guest => {
								roomAssignments.push(
									{
										transactNo: guest.transactNo,
										guestId: guest.id,
										roomType: room.type,
										roomNumber: maxRoomNumber // Assign based on fetched maxRoomNumber
									});

								// Collect luggage data for each guest
								let luggageItems = Array.from(document.querySelectorAll(`#luggageContainer-${guest.id} select`)).map(select => select.value);
								luggageItems.forEach(luggage => {
									luggageAssignments.push(
										{
											transactNo: guest.transactNo,
											guestId: guest.id,
											luggageId: luggage
										});
								});
							});
						});

						// Ensure there's data to send
						if (roomAssignments.length === 0) {
							alert("No room assignments to save.");
							return;
						}

						// Send AJAX request to insert room assignments
						$.ajax(
							{
								url: '../Agent Section/functions/agent-addRoomingList.php',
								type: 'POST',
								data: {
									roomAssignments: JSON.stringify(roomAssignments),
									luggageAssignments: JSON.stringify(luggageAssignments) // Include luggage data in the same request
								},
								success: function (response) {
									console.log(response);
									alert("Room assignments and luggage details saved successfully!");
								},
								error: function (xhr, status, error) {
									console.error('Error saving data:', error);
									alert("Failed to save room assignments and luggage details.");
								}
							});
					},
					error: function (xhr, status, error) {
						console.error('Error fetching max room number:', error);
						alert("Failed to fetch max room number.");
					}
				});
		});

		// Fetch guests when flight date changes
		$('#btnGetGuests').on('click', function () {
			let flightDate = $('#flightDate').val();
			let agentCode = $('#branch').val(); // corrected

			console.log(agentCode);

			console.log("Luggage Options:", luggageOptions);
			$('#guestName').html('<option selected disabled>Loading guests...</option>');

			if (flightDate && agentCode) {
				$.ajax({
					url: '../Agent Section/functions/fetchGuest.php',
					type: 'POST',
					data: { flightDate: flightDate, agentCode: agentCode },
					success: function (response) {
						console.log(response);
						let guestSelect = $('#guestName');
						guestSelect.empty();

						let data = JSON.parse(response);

						// Populate assigned guests
						if (data.assignedGuests.length > 0) {
							rooms = [];
							data.assignedGuests.forEach(guest => {
								let room = {
									type: guest.roomType,
									guests: [{ ...guest }],
									roomNumber: guest.roomNumber
								};
								rooms.push(room);
							});
							updateRoomList();
						}

						// Populate unassigned guests
						if (data.unassignedGuests.length > 0) {
							data.unassignedGuests.forEach(guest => {
								guestSelect.append(new Option(guest.name, guest.id));
							});
							guests = data.unassignedGuests;
						} else {
							$('#guestName').html('<option selected disabled>No guests found</option>');
						}
					},
					error: function (xhr, status, error) {
						console.error('Error fetching guests:', error);
						$('#guestName').html('<option selected disabled>No guests found</option>');
					}
				});
			} else {
				$('#guestName').html('<option selected disabled>Please select branch and flight date</option>');
			}
		});

		function assignRoom() {
			let guestSelect = document.getElementById('guestName');
			let selectedGuests = Array.from(guestSelect.selectedOptions).map(opt => {
				let guestData = guests.find(g => g.id == opt.value);
				return { ...guestData };
			});

			let roomType = document.getElementById('roomType').value;
			let minCapacity = getMinCapacity(roomType);
			let maxCapacity = getMaxCapacity(roomType);

			if (selectedGuests.length < minCapacity || selectedGuests.length > maxCapacity) {
				alert(`A ${roomType} room must have between ${minCapacity} and ${maxCapacity} guests.`);
				return;
			}

			// Fetch latest room number before assigning
			let transactNo = selectedGuests[0]?.transactNo; // Assuming all guests share the same transactNo
			if (!transactNo) {
				alert("Error: Missing transaction number.");
				return;
			}

			// Prevent duplicate assignments
			let alreadyAssigned = selectedGuests.some(guest =>
				rooms.some(room => room.guests.some(g => g.id == guest.id))
			);
			if (alreadyAssigned) {
				alert("One or more guests are already assigned to a room.");
				return;
			}

			$.ajax(
				{
					url: '../Agent Section/functions/fetchMaxRoomNumber.php',
					type: 'POST',
					data: { transactNo: transactNo },
					success: function (response) {
						let lastRoomNumber = !isNaN(parseInt(response)) ? parseInt(response) : 0; // Default to 0 if no rooms exist
						let nextRoomNumber = lastRoomNumber + 1; // Increment for new room assignment

						// Update available guests list after assignment
						guests = guests.filter(g => !selectedGuests.some(sg => sg.id == g.id));

						// Add luggage types to selected guests before assigning to room
						selectedGuests.forEach(guest => {
							// Assuming guestLuggage is a global object holding luggage types by guestId
							guest.luggageTypes = guestLuggage[guest.id] || [];
						});

						let room = { type: roomType, guests: selectedGuests, roomNumber: nextRoomNumber };
						rooms.push(room);

						updateRoomList();
						updateGuestDropdown(); // Refresh guest dropdown after assignment
					},
					error: function (xhr, status, error) {
						console.error("Error fetching last room number:", error);
						alert("Failed to fetch room number.");
					}
				});
		}

		// Function to update the room list
		function updateRoomList() {
			let assignedRoomsTable = document.getElementById('assignedRoomsTable');
			assignedRoomsTable.innerHTML = ''; // Clear the existing table

			let rowNumber = 1;

			rooms.forEach((room, roomIndex) => {
				let firstGuest = true;

				room.guests.forEach((guest) => {
					let row = assignedRoomsTable.insertRow();
					row.setAttribute("data-guest-id", guest.id);
					row.setAttribute("data-transact-no", guest.transactNo);

					// Get the count of luggage for the guest
					let luggageCount = (guest.luggageType || []).length;
					console.log(`Guest ID: ${guest.id}, Number of Luggage: ${luggageCount}`);

					// Generate container with multiple luggage selects for each guest
					let luggageSelectGroup = `
			<div id="luggageContainer-${guest.id}" class="luggage-group" data-guest-id="${guest.id}">
				<div id="luggageSelects-${guest.id}">`;

					if (luggageCount > 0) {
						for (let i = 0; i < luggageCount; i++) {
							luggageSelectGroup += `
				<select class="form-control" name="luggageSelect-${guest.id}[]" 
					style="width: 100%; display: block; margin-bottom: 5px;" 
					id="luggageSelect-${guest.id}-${i}">
					<option value="">Select Luggage</option>`;

							luggageOptions.forEach(option => {
								const selected = option.concernDetailsId == guest.luggageType[i] ? 'selected' : '';
								luggageSelectGroup += `<option value="${option.concernDetailsId}" ${selected}>${option.details}</option>`;
							});

							luggageSelectGroup += `</select>`;
						}
					}

					luggageSelectGroup += `
			</div> <!-- end of luggageSelects container -->
				<button type="button" class="btn btn-sm btn-primary mt-1" style="margin-top: 5px;" onclick="addLuggageSelect(${guest.id})">Add</button>
				<button type="button" class="btn btn-sm btn-danger mt-1" style="margin-top: 5px; margin-left: 5px;" onclick="removeLuggageSelect(${guest.id})">Remove</button>
			</div>`;

					row.innerHTML = `
			<td style="text-align: center; vertical-align: middle;">${rowNumber++}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.age || "N/A"}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.name.split(" ")[0]}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.name.split(" ").slice(-1).join(" ")}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.name}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.dob || "N/A"}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.nationality || "N/A"}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.passport || "N/A"}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.passportExp || "N/A"}</td>
			<td style="text-align: center; vertical-align: middle;">${guest.sex || "N/A"}</td>
			${firstGuest ? `<td style="text-align: center;" class="room-type" rowspan="${room.guests.length}">${room.type.toUpperCase()}</td>` : ''} 
			<td style="text-align: center; vertical-align: middle;">
				${luggageSelectGroup}
			</td>
			${firstGuest ? `<td style="vertical-align: middle;" rowspan="${room.guests.length}"> 
				<button style="display: block; margin: auto;" class="btn btn-danger btn-sm" onclick="removeRoom(${roomIndex})">
				Remove
				</button>
			</td>` : ''}`;

					firstGuest = false;
				});
			});
		}

		// Add a luggage select dropdown dynamically
		function addLuggageSelect(guestId) {
			const container = document.getElementById(`luggageSelects-${guestId}`);
			const selectCount = container.querySelectorAll('select').length;

			let select = document.createElement('select');
			select.className = 'form-control';
			select.name = `luggageSelect-${guestId}[]`;
			select.id = `luggageSelect-${guestId}-${selectCount}`;
			select.style.cssText = 'width: 100%; display: block; margin-bottom: 5px;';

			let defaultOption = document.createElement('option');
			defaultOption.value = '';
			defaultOption.textContent = 'Select Luggage';
			select.appendChild(defaultOption);

			luggageOptions.forEach(option => {
				let opt = document.createElement('option');
				opt.value = option.concernDetailsId;
				opt.textContent = option.details;
				select.appendChild(opt);
			});

			container.appendChild(select); // Append to the luggageSelects container
		}

		function generateLuggageSelects(guestId) {
			let html = "";

			if (guestLuggage[guestId]) {
				// Ensure luggageIds are always an array
				let luggageIds = Array.isArray(guestLuggage[guestId]) ? guestLuggage[guestId] : [guestLuggage[guestId]];

				// Iterate through all luggage types assigned to the guest
				luggageIds.forEach((luggageId, index) => {
					let select = `<select class="form-control" name="luggageSelect-${guestId}[]" style="width: 100%; display: block;" id="luggageSelect-${guestId}-${index}">
							<option value="">Select Luggage</option>`;

					// Populate luggage options dynamically
					luggageOptions.forEach(option => {
						select += `<option value="${option.concernDetailsId}" ${option.concernDetailsId == luggageId ? 'selected' : ''}>${option.details}</option>`;
					});

					select += `</select>`;
					html += select;
				});
			}

			return html;
		}

		// Remove the last luggage select dropdown for a guest
		function removeLuggageSelect(guestId) {
			const container = document.getElementById(`luggageSelects-${guestId}`);
			const selects = container.querySelectorAll('select');

			if (selects.length > 0) {
				container.removeChild(selects[selects.length - 1]);
			}
		}

		// Remove a room and reassign the guests to the unassigned list
		function removeRoom(index) {
			let removedGuests = rooms[index].guests;
			guests = [...guests, ...removedGuests];
			updateGuestDropdown();
			rooms.splice(index, 1);
			updateRoomList();
		}

		// Update the guest dropdown
		function updateGuestDropdown() {
			let guestSelect = document.getElementById('guestName');
			guestSelect.innerHTML = '';
			if (guests.length > 0) {
				guests.forEach(guest => {
					let option = document.createElement('option');
					option.value = guest.id;
					option.textContent = guest.name;
					guestSelect.appendChild(option);
				});
			}
			else {
				guestSelect.innerHTML = '<option selected disabled>No guests available</option>';
			}
			sortGuestDropdown();
		}

		// Sort the guest dropdown alphabetically
		function sortGuestDropdown() {
			let guestSelect = document.getElementById('guestName');
			let options = Array.from(guestSelect.options);
			options.sort((a, b) => a.textContent.localeCompare(b.textContent));
			guestSelect.innerHTML = '';
			options.forEach(option => guestSelect.appendChild(option));
		}

		// Get minimum capacity based on room type
		function getMinCapacity(roomType) {
			switch (roomType) {
				case 'Single':
					return 1;
				case 'Twin':
				case 'Double':
					return 2;
				case 'Triple':
					return 3;
				default:
					return 1;
			}
		}

		// Get maximum capacity based on room type
		function getMaxCapacity(roomType) {
			switch (roomType) {
				case 'Single':
					return 1;
				case 'Twin':
				case 'Double':
					return 2;
				case 'Triple':
					return 3;
				default:
					return 1;
			}
		}
	</script>




</body>

</html>