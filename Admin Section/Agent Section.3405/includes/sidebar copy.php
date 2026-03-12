<?php include '../../Agent Section/testing/sidebar-scripts.php' ?>



<div class="sidebar">
	<ul class="nav flex-column nav-logo-wrapper">
		<li class="nav-item nav-logo-item-wrapper">
			<a class="nav-link logo-link" href="#">
				<div class="logo-content">
					<div class="logo-backdrop">
						<img src="../Assets/Logos/logo-tab.png" alt="Logo" class="sidebar-logo">
					</div>
					<span class="fw-bold">SMART TRAVEL</span>
				</div>
			</a>
		</li>
	</ul>

	<ul class="nav flex-column">
		<li class="nav-item">
			<a class="nav-link page-button" href="../Employee Section/emp-dashboard.php" data-page-name="Dashboard">
				<div class="icon-wrapper">
					<div class="icon"><i class="fa-solid fa-house"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label">Dashboard</span>
				</div>
			</a>
		</li>

		<li class="nav-item transaction">
			<a class="nav-link page-button" href="../Employee Section/emp-transaction.php" data-page-name="Transactions">
				<div class="icon-wrapper">
					<div class="icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label">Transactions</span>
				</div>
			</a>
		</li>

		<li class="nav-item transaction">
			<a class="nav-link page-button" href="../Employee Section/emp-guestList.php" data-page-name="Guest List">
				<div class="icon-wrapper">
					<div class="icon"><i class="fas fa-user"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label">Guest List</span>
				</div>
			</a>
		</li>

		<li class="nav-item transaction">
			<a class="nav-link page-button" href="../Employee Section/emp-visaRequirementsTable.php" data-page-name="Visa Requirements">
				<div class="icon-wrapper">
					<div class="icon"><i class="fas fa-file-lines"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label" style="font-size: 14px;">Visa Requirements</span>
				</div>
			</a>
		</li>

		<li class="nav-item dropdown">
			<a class="nav-link page-button" href="#" data-bs-toggle="collapse" data-bs-target="#manageBookingMenu" aria-expanded="false" aria-controls="manageBookingMenu" data-page-name="Operationals">
				<div class="icon-wrapper">
					<div class="icon"><i class="fa-solid fa-thumbs-up"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label">For Approvals</span>
				</div>
			</a>
			<div class="collapse" id="manageBookingMenu">
				<ul class="nav flex-column managebooking-menu-wrapper">
					<li class="nav-item transaction">
						<a class="nav-link page-button" href="../Employee Section/emp-tablePending.php" data-page-name="For Approvals - Booking">Booking</a>
					</li>
					<li class="nav-item">
						<a class="nav-link page-button" href="../Employee Section/emp-tableRequest.php" data-page-name="For Approvals - Request">Request</a>
					</li>
					<li class="nav-item">
						<a class="nav-link page-button" href="../Employee Section/emp-tablePayment.php" data-page-name="For Approvals - Payment">Payment</a>
					</li>
				</ul>
			</div>
		</li>

		<li class="nav-item dropdown">
			<a class="nav-link page-button" href="#" data-bs-toggle="collapse" data-bs-target="#reportMenu" aria-expanded="false" aria-controls="reportMenu" data-page-name="Reports">
				<div class="icon-wrapper">
					<div class="icon"><i class="fa-regular fa-file"></i></div>
				</div>
				<div class="label-wrapper">
					<span class="label">Reports</span>
				</div>
			</a>

			<div class="collapse" id="reportMenu">
				<ul class="nav flex-column report-menu-wrapper">
					<li class="nav-item">
						<a class="nav-link page-button open-new-tab" href="../Employee Section/emp-itinerarytable.php" data-page-name="Itinerary" data-url="">Itinerary</a>
					</li>
					<li class="nav-item">
						<a class="nav-link page-button open-new-tab" href="../Employee Section/emp-voucherTable.php" data-page-name="Voucher" data-url="">Voucher</a>
					</li>
					<li class="nav-item">
						<a class="nav-link page-button" href="#" data-page-name="Ticket">Ticket</a>
					</li>
					<li class="nav-item">
						<a class="nav-link page-button" href="../Employee Section/emp-soa.php" data-page-name="SOA">SOA</a>
					</li>
				</ul>
			</div>
		</li>

	</ul>

	<div class="logout">
		<div class="profile-section">
			<div class="profile-left">
				<div class="name" style="font-size: <?php echo (strlen($fullName) >= 13) ? '15px' : '17px'; ?>;">
					<?php echo $fullName; ?>
				</div>
				<div class="empid fw-bold text-light" style="font-size: 14px;">EMP ID: <span class="fw-normal text-light"><?php echo $empId ?? ''; ?></span></div>
			</div>
			<div class="profile-icon profile-icon-visible">
				<i class="fa-solid fa-user-circle"></i>
			</div>
		</div>

		<div class="nav-item" id="raiseTicketWrapper">
			<a class="nav-link" id="raiseTicket" href="#">
				<div class="icon-wrapper">
					<div class="icon" id="raiseTicketIcon">
						<i class="fas fa-ticket-alt"></i>
					</div>
				</div>
				<div class="label-wrapper">
					<span class="label">Raise Ticket</span>
				</div>
			</a>
		</div>

		<div class="nav-item">
			<a class="nav-link" id="changePasswordLink" href="#">
				<div class="icon-wrapper">
					<div class="icon" id="changePasswordIcon">
						<i class="fas fa-key"></i>
					</div>
				</div>
				<div class="label-wrapper">
					<span class="label">Change Password</span>
				</div>
			</a>
		</div>

		<div class="nav-item" id="logoutWrapper">
			<a class="nav-link" id="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">
				<div class="icon-wrapper">
					<div class="icon" id="logoutIcon">
						<i class="fa-solid fa-right-from-bracket"></i>
					</div>
				</div>
				<div class="label-wrapper">
					<span class="label">Logout</span>
				</div>
			</a>
		</div>
	</div>
	
</div>





























<!-- Modals -->
<!-- Raise Ticket Modal -->
<div class="modal fade" id="raiseTicketModal" tabindex="-1" aria-labelledby="raiseTicketModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="raiseTicketModalLabel"><i class="fas fa-ticket-alt"></i> Raise a Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="ticketForm">
          <div class="mb-3">
            <label for="concernType" class="form-label">Concern</label>
            <select class="form-select" id="concernType" required>
              <option value="" selected disabled>Select Concern</option>
              <option value="Request for Additional User">Request for Additional User</option>
            </select>

            <!-- Hidden input field for Number of Users -->
            <div id="userCountContainer" style="display: none; margin-top: 10px;">
              <label for="numUsers" class="form-label">Number of Users</label>
              <input type="number" class="form-control" id="numUsers" min="1" placeholder="Enter number of users">

            </div>
          </div>

          <div class="alert alert-info mt-3" id="userCountContainer-note" style="display: none; font-size: 14px;">
            <p class="mb-1"><strong>Please provide user credentials using the template below:</strong></p>
            <p class="mb-1"><strong>- Full Name <span style="font-weight: 400;">(First Name, Last Name, Middle Name, Suffix)</span>:</strong> </p>
            <p class="mb-1"><strong>- Company Name:</strong></p>
            <p class="mb-1"><strong>- Contact Number:</strong></p>
            <p class="mb-3"><strong>- Email:</strong></p>
            <p class="mb-0"><strong>Note:</strong> A default password will be assigned initially.</p>
          </div>



          <!-- JS for Number of Users -->
          <script>
            document.getElementById("concernType").addEventListener("change", function() {
              var userCountContainer = document.getElementById("userCountContainer");
              var userCountContainerNote = document.getElementById("userCountContainer-note");
              var ticketPriority = document.getElementById("ticketPriority");
              if (this.value === "Request for Additional User") {
                userCountContainer.style.display = "block";
                userCountContainerNote.style.display = "block";
                ticketPriority.style.display = "hidden";
              } else {
                userCountContainer.style.display = "none";
                userCountContainerNote.style.display = "none";
                ticketPriority.style.display = "block";
              }
            });
          </script>


          <div class="mb-3">
            <label for="ticketDescription" class="form-label">Description</label>
            <textarea class="form-control" id="ticketDescription" rows="4" required></textarea>
          </div>
          <div class="mb-3" id="ticketPriority" style="display: hidden;">
            <label for="ticketPriority" class="form-label">Priority</label>
            <select class="form-select" id="ticketPriority">
              <option value="" disabled selected>Select Severity</option>
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <!-- <div class="mb-3">
                            <label for="ticketAttachment" class="form-label">Attachment (Optional)</label>
                            <input type="file" class="form-control" id="ticketAttachment">
                        </div> -->
          <button type="submit" class="btn btn-success w-100"> Submit Ticket</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to logout?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" class="btn btn-danger" id="logoutButton">Logout</a>

      </div>
    </div>
  </div>
</div>






<!-- Ticket Submission Script -->
<script>
  $(document).ready(function() {
    $("#ticketForm").submit(function(event) {
      event.preventDefault(); // Prevent default form submission

      console.log("Form submission triggered."); // Debugging

      // Collect form data
      var concernType = $("#concernType").val();
      var numUsers = $("#numUsers").val() || ""; // Get only if field is visible
      var ticketDescription = $("#ticketDescription").val();
      var ticketPriority = $("#ticketPriority").val() || "medium"; // Default to "medium" if empty


      console.log("Collected form data:", {
        concernType: concernType,
        numUsers: numUsers,
        ticketDescription: ticketDescription,
        ticketPriority: ticketPriority
      }); // Debugging

      // Create data object
      var formData = {
        concernType: concernType,
        numUsers: concernType === "Request for Additional User" ? numUsers : "", // Send only if applicable
        ticketDescription: ticketDescription,
        ticketPriority: ticketPriority
      };

      console.log("Final form data before AJAX request:", formData); // Debugging

      // AJAX Request
      $.ajax({
        type: "POST",
        url: "../Agent Section/functions/agent-processTicket.php", // Change to your server-side script
        data: formData,
        dataType: "json",
        beforeSend: function() {
          console.log("AJAX request is about to be sent..."); // Debugging
        },
        success: function(response) {
          console.log("AJAX success response:", response); // Debugging

          if (response.status === "success") {
            alert("Ticket submitted successfully! Ticket ID: " + response.ticketId);
            console.log("Ticket successfully created with ID:", response.ticketId); // Debugging

            // Close the modal
            let modal = document.getElementById("raiseTicketModal"); // Replace with your modal's actual ID
            let modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
              modalInstance.hide();
            }

            // Reset the form
            document.getElementById("ticketForm").reset(); // Replace with your form's actual ID


          } else {
            alert("Error: " + response.message);
            console.error("Server returned an error:", response.message); // Debugging
          }
        },
        error: function(xhr, status, error) {
          alert("An error occurred while submitting the ticket.");
          console.error("AJAX error:", status, error); // Debugging
          console.log("Response Text:", xhr.responseText); // Debugging
        }
      });
    });

    // Show/Hide Fields Based on Concern Selection
    $("#concernType").change(function() {
      console.log("Concern type changed to:", $(this).val()); // Debugging

      if ($(this).val() === "Request for Additional User") {
        $("#userCountContainer").show();
        $("#userCountContainer-note").show();
        $("#ticketPriority").hide();
        console.log("Showing additional user input fields."); // Debugging
      } else {
        $("#userCountContainer").hide();
        $("#userCountContainer-note").hide();
        $("#ticketPriority").show();
        console.log("Hiding additional user input fields."); // Debugging
      }
    });
  });
</script>

<!-- <script>
  function toggleSubMenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const sectionTitle = submenu.previousElementSibling;
    const chevron = sectionTitle.querySelector('.chevron-icon');

    // Check if the submenu is already open
    const isOpen = submenu.classList.contains('open');

    // Toggle the submenu: If it's open, close it; If it's closed, open it
    if (isOpen) {
      submenu.classList.remove('open');
      chevron.style.transform = 'rotate(0deg)';
    } else {
      submenu.classList.add('open');
      chevron.style.transform = 'rotate(180deg)';
    }
  }

  // Optionally: Automatically open the submenu when the page loads (Transaction submenu is open by default in this case)
  document.addEventListener('DOMContentLoaded', function() {
    const transactionSubmenu = document.getElementById('transactiontable-submenu');
    const transactionChevron = document.querySelector('#transactiontable-submenu').previousElementSibling.querySelector('.chevron-icon');

    // Set the default opened submenu (Transaction)
    transactionSubmenu.classList.add('open');
    transactionChevron.style.transform = 'rotate(180deg)';
  });
</script> -->

