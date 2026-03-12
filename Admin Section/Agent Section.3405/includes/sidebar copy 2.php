<?php
require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$accountId = $_SESSION['agent_accountId'];
$agentId = $_SESSION['agentId'];
$agentCode = $_SESSION['agentCode'];
$agentRole = $_SESSION['agentRole'];
$agentType = $_SESSION['agentType'];
$fName =  $_SESSION['agent_fName'] ?? '';
$lName = $_SESSION['agent_lName'] ?? '';
$mName = $_SESSION['agent_mName'] ?? '';
$branchId = $_SESSION['agent_branchId'] ?? '';
$email = $_SESSION['agent_email'] ?? '';
$password = $_SESSION['agent_password'] ?? '';
$emailAdress = $_SESSION['agent_emailAddress'] ?? '';

// Fetch Branch Name
$sql1 = "SELECT branchName FROM branch WHERE branchId = ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("i", $branchId);
$stmt1->execute();
$result1 = $stmt1->get_result();

if ($result1->num_rows > 0) {
  $row = $result1->fetch_assoc();
  $branchName = $row['branchName'];
} else {
  $branchName = "No Branch";
}

$stmt1->close();

// Fetch Agent Info (to get companyId)
$sql2 = "SELECT companyId FROM agent WHERE accountId = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $accountId);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows > 0) {
  $row2 = $result2->fetch_assoc();
  $companyId = $row2['companyId'];

  // Fetch Company Name if companyId is NOT NULL
  if (!is_null($companyId)) {
    $sql3 = "SELECT companyName FROM company WHERE companyId = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $companyId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();

    if ($result3->num_rows > 0) {
      $row3 = $result3->fetch_assoc();
      $companyName = $row3['companyName'];
    } else {
      $companyName = "Unknown Company"; // Fallback if no company record found
    }
    $stmt3->close();
  } else {
    $companyName = null; // No company assigned
  } 
} else {
  // Only set "No Branch" if branchName is still empty
  if (empty($branchName)) {
    $branchName = "No Branch";
  }
}
$stmt2->close();

// Format the full name in Last Name, First Name, Middle Name format
$fullName = htmlspecialchars(trim(
  $lName .                         // Always include last name
    ($fName ? ', ' . $fName : '') .  // Add first name with a comma if it's not empty
    ($mName ? ' ' . substr($mName, 0, 1) . '.' : '') // Add middle name initial if it's not empty
));

// Remove any trailing commas or extra spaces
$fullName = rtrim($fullName, ', '); // Clean up if only the last name is present
?>

<?php
date_default_timezone_set('Asia/Taipei');
$current_date = date('D, F d, Y');
?>

<div class="sidebar" id="sidebar">
  <div class="main-sidebar">
    <div class="logo">
      <img src="../Assets/Logos/logo.png" alt="Smart Travel Logo">
    </div>

    <div class="dashboard-title">Menu</div>

    <a href="../Agent Section/agent-dashboard.php" class="page-button home my-0 mb-1 " data-page-name="Dashboard">
      <i class="fas fa-home"></i> <span> Home </span>
    </a>

    <!-- <a href="../Agent Section/agent-revisedAddbooking.php" class="page-button add-booking mb-1 my-0" data-page-name="Add Booking - Packages"> 
      <i class="fa-solid fa-user-plus"></i> <span> Add Booking </span>
    </a> -->

    <!-- <a href="../Agent Section/agent-FIT.php" class="page-button add-FIT mb-1 my-0" data-page-name="Add Booking - F.I.T">
      <i class="fa-solid fa-user-plus"></i> <span> Add F.I.T </span>
    </a> -->

    <div class="section-title" onclick="toggleSubMenu('transactiontable-submenu')">
      Transactions <span class="chevron-icon fas fa-chevron-down"></span>
    </div>

    <div class="submenu open" id="transactiontable-submenu">
      <a href="../Agent Section/agent-transactions.php" class="page-button my-0" data-page-name="Packages - Transactions table">
        <i class="fas fa-file-invoice"></i> Packages
      </a>

      <a href="../Agent Section/agent-guestInformationList.php" class="page-button my-0" data-page-name="Guest Information List">
        <i class="fa-solid fa-table-list"></i> Guest List
      </a>

      <a href="../Agent Section/agent-requestHistory.php" class="page-button my-0" data-page-name="Request History">
        <i class="fa-solid fa-cart-plus"></i> Request
      </a>

      <a href="../Agent Section/agent-paymentHistory.php" class="page-button my-0" data-page-name="Payment History">
        <i class="fa-solid fa-money-check-dollar"></i> Payment
      </a>

      <a href="../Agent Section/agent-roomingList.php" class="page-button my-0" data-page-name="Rooming Assignment">
        <i class="fa-solid fa-newspaper"></i> Rooming List
      </a>

      <a href="../Agent Section/agent-soa.php" class="page-button my-0" data-page-name="Rooming Assignment">
        <i class="fas fa-file-invoice"></i> SOA
      </a>

      <a href="../Agent Section/agent-reports.php" class="page-button my-0" data-page-name="Rooming Assignment">
        <i class="fas fa-file-invoice"></i> Reports
      </a>

      <!-- <a href="../Agent Section/agent-FIT-table.php" class="page-button my-0" data-page-name="F.I.T - Transactions Table" style="font-size: 14px;">
        <i class="fas fa-file-invoice"></i> F.I.T 
      </a>  -->
    </div>

    <!-- <div class="section-title" onclick="toggleSubMenu('operational-submenu')">
      Reports <span class="chevron-icon fas fa-chevron-down"></span>
    </div>

    <div class="submenu open" id="operational-submenu">
      <a href="../Agent Section/agent-itenerary.php" class="page-button" data-page-name="Itinerary">
        <i class="fas fa-map"></i> Itinerary
      </a>

      <a href="../Agent Section/agent-soa2.php" class="page-button" data-page-name="Statement of Accounts (SOA) - Packages">
        <i class="fas fa-file-invoice-dollar"></i> SOA - Packages
      </a>

      <a href="../Agent Section/agent-fitSOA - rename.php" class="page-button" data-page-name="Statement of Accounts (SOA) - F.I.T">
        <i class="fas fa-file-invoice-dollar"></i> SOA - F.I.T
      </a>

      <a href="../Agent Section/agent-ticket.php" class="page-button" data-page-name="Ticket">
        <i class="fas fa-ticket"></i> Ticket
      </a>

      <a href="../Agent Section/agent-transactions.php" class="page-button" data-page-name="Voucher">
        <i class="fas fa-gift"></i> Voucher
      </a>
    </div> -->

    <!-- <a href="../Agent Section/agent-FIT-table.php" class="page-button my-0" data-page-name="F.I.T - Transactions Table" style="font-size: 14px;">
          <i class="fas fa-file-invoice"></i> F.I.T 
        </a> -->

  </div>

  <div class="profile-wrapper">
    <div class="concern-section mb-4">

      <div class="section-title" onclick="toggleSubMenu('concerntable-submenu')">
        Concerns <span class="chevron-icon fas fa-chevron-down"></span>
      </div>

      <div class="submenu open" id="concerntable-submenu">
        <a href="#" class="page-button my-0" data-bs-toggle="modal" data-bs-target="#raiseTicketModal">
          <i class="fas fa-ticket-alt"></i> Raise a Ticket
        </a>

        <a href="#" class="changePassword page-button my-0" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
          <i class="fas fa-lock"></i> Change Password
        </a>
      </div>

      <?php include '../Agent Section/includes/logoutViewPassModal.php'; ?>

    </div>

    <!-- Profile Section -->
    <div class="profile-section">
      <!-- <div class="profile-icon">
        <i class="fas fa-user-circle"></i>
      </div> -->
      <div class="profile-details">
        <h6 class="profile-name"><?php echo $fullName; ?></h>
          <p class="profile-role mt-1">
            <span><?php echo htmlspecialchars(!empty($companyName) ? $companyName : $branchName);  ?> </span>
            $branchName
          </p>
      </div>
    </div>


    <div class="logout-wrapper">
      <a href="#" class="page-button logout" data-page-name="" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
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

<!-- Change Password Modal -->
<!-- <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header 
      <div class="modal-header">
        <div class="modal-title-wrapper">
          <h5 class="modal-title" id="changePasswordLabel">Change Password</h5>
          <small class="modal-subtext">Ensure your new password is secure and different from previous ones.</small>
        </div>
        <div class="modal-close-wrapper">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <!-- Modal Body 
      <div class="modal-body">
        <form id="changePasswordForm">
          
          <!-- Current Password Field 
          <div class="mb-3">
            <label for="currentPassword" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password" required>
            <small id="currentPasswordError" class="error-label text-danger"></small>
          </div>

          <!-- New Password Field 
          <div class="mb-3">
            <label for="newPassword" class="form-label">New Password</label>
            <input type="password" class="form-control" id="newPassword" placeholder="Enter new password" required>
            <small id="newPasswordError" class="error-label text-danger"></small>
          </div>

          <!-- Confirm New Password Field 
          <div class="mb-3">
            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirmNewPassword" placeholder="Re-enter new password" required>
            <small id="confirmPasswordError" class="error-label text-danger"></small>
          </div>

          <!-- Success Message 
          <small id="updateMessage" class="text-success"></small>

          <!-- Modal Footer 
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Update Password</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div> -->

<!-- <script>
$(document).ready(function () {
    let accountId = 
    <?php 
    // echo $accountId; 
    ?>;

    // Real-time password length check
    $("#newPassword").on("input", function () {
        let newPassword = $(this).val().trim();
        console.log("New Password Length (input):", newPassword.length); // Debugging

        if (newPassword.length < 6) {
            $("#newPasswordError").text("Password must be at least 6 characters.").fadeIn();
        } else {
            $("#newPasswordError").fadeOut();
        }
    });

    // Real-time confirmation password match check
    $("#confirmNewPassword").on("input", function () {
        let newPassword = $("#newPassword").val().trim();
        let confirmNewPassword = $(this).val().trim();
        console.log("Confirm Password Match (input):", newPassword === confirmNewPassword);

        if (newPassword !== confirmNewPassword) {
            $("#confirmPasswordError").text("Passwords do not match.").fadeIn();
        } else {
            $("#confirmPasswordError").fadeOut();
        }
    });

  
    $(document).on("submit", "#changePasswordForm", function (e) {
        e.preventDefault(); 

      
        $(".error-label").text("").hide();
        $("#updateMessage").text("").hide();

        let currentPassword = $("#currentPassword").val().trim();
        let newPassword = $("#newPassword").val().trim();
        let confirmNewPassword = $("#confirmNewPassword").val().trim();
        let errorCount = 0;

       
        console.log("Form Submitted - New Password Length:", newPassword.length);

        
        if (newPassword.length < 6) {
            $("#newPasswordError").text("Password must be at least 6 characters.").fadeIn();
            errorCount++;
        }

      
        if (newPassword !== confirmNewPassword) {
            $("#confirmPasswordError").text("Passwords do not match.").fadeIn();
            errorCount++;
        }

     
        if (errorCount > 0) return;

        
        console.log("Submitting AJAX request...");
        console.log("Account ID:", accountId);
        console.log("Current Password:", currentPassword);
        console.log("New Password:", newPassword);

        $.ajax({
            url: "../Agent Section/functions/General/agent-changePassword.php", 
            type: "POST",
            data: {
                accountId: accountId,
                currentPassword: currentPassword,
                newPassword: newPassword
            },
            dataType: "json",
            success: function (response) {
                console.log("Server Response:", response); 

                if (response.status === "error") {
                    console.log("Error:", response.message);
                    $("#currentPasswordError").text(response.message).fadeIn();
                } else if (response.status === "success") {
                    console.log("Success: Password updated successfully!");
                    $("#updateMessage").text("Password updated successfully!").fadeIn();
                    $("#changePasswordForm")[0].reset(); // Reset the form
                    setTimeout(() => {
                        $("#changePasswordModal").modal("hide"); // Close modal after success
                    }, 1500);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log("AJAX Error:", textStatus, errorThrown);
                alert("Something went wrong. Please try again.");
            }
        });
    });

});
</script> -->

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

<script>
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
</script>

