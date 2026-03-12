<?php
require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$accountId = $_SESSION['agent_accountId'] ?? '';
$agentId = $_SESSION['agentId'] ?? '';
$agentCode = $_SESSION['agentCode'] ?? '';
$agentRole = $_SESSION['agentRole'] ?? '';
$agentType = $_SESSION['agentType'] ?? '';
$fName = $_SESSION['agent_fName'] ?? '';
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

  <ul class="nav flex-column nav-logo-wrapper nav-logo-header">
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

  <div class="nav flex-column">
    <li class="nav-item">
      <a class="nav-link page-button" href="../Agent Section/agent-dashboard.php" data-page-name="Dashboard">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-house"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label">Dashboard</span>
        </div>
      </a>
    </li>

    <!-- Packages -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-transactions.php" data-page-name="Packages">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-box"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Packages</span>
        </div>
      </a>
    </li>

    <!-- Guest List -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-guestInformationList.php"
        data-page-name="Guest List">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-users"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Guest List</span>
        </div>
      </a>
    </li>

    <!-- Request -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-requestHistory.php" data-page-name="Request">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-envelope-open-text"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Request</span>
        </div>
      </a>
    </li>

    <!-- Payment -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-paymentHistory.php" data-page-name="Payment">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Payment</span>
        </div>
      </a>
    </li>

    <!-- Rooming List -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-roomingList.php" data-page-name="Rooming List">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-bed"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Rooming List</span>
        </div>
      </a>
    </li>

    <!-- Client Payment -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-clientPaymentHistory.php" data-page-name="Rooming List">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-bed"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Client Payments List</span>
        </div>
      </a>
    </li>

    <!-- SOA -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-soa.php" data-page-name="SOA">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">SOA</span>
        </div>
      </a>
    </li>

    <!-- Reports -->
    <li class="nav-item transaction">
      <a class="nav-link page-button" href="../Agent Section/agent-reports.php" data-page-name="Reports">
        <div class="icon-wrapper">
          <div class="icon"><i class="fa-solid fa-chart-line"></i></div>
        </div>
        <div class="label-wrapper">
          <span class="label" style="font-size: 14px;">Reports</span>
        </div>
      </a>
    </li>


  </div>

  <div class="logout">
    <!-- <div class="separator"></div> -->
    <div class="profile-section">
      <div class="profile-left" id="profileLeft">
        <div class="name" style="font-size: <?php echo (strlen($fullName) >= 13) ? '14px' : '17px'; ?>;">
          <?php echo $fullName; ?>
        </div>
        <div class="empid fw-bold text-light" style="font-size: 14px;">
          Branch: <span class="fw-normal text-light"><?php echo $branchName; ?></span>
        </div>
      </div>
      <!-- <div class="profile-icon profile-icon-visible">
        <i class="fa-solid fa-user-circle"></i>
      </div> -->
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





<!-- Raise Ticket Modal -->
<div class="modal fade" id="raiseTicketModal" tabindex="-1" aria-labelledby="raiseTicketModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="raiseTicketModalLabel">Raise a Ticket</h5>
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
            <p class="mb-1"><strong>- Full Name <span style="font-weight: 400;">(First Name, Last Name, Middle Name,
                  Suffix)</span>:</strong> </p>
            <p class="mb-1"><strong>- Company Name:</strong></p>
            <p class="mb-1"><strong>- Contact Number:</strong></p>
            <p class="mb-3"><strong>- Email:</strong></p>
            <p class="mb-0"><strong>Note:</strong> A default password will be assigned initially.</p>
          </div>



          <!-- JS for Number of Users -->
          <script>
            document.getElementById("concernType").addEventListener("change", function () {
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

<script>
  document.getElementById('raiseTicket').addEventListener('click', function (e) {
    e.preventDefault();
    const raiseModal = new bootstrap.Modal(document.getElementById('raiseTicketModal'));
    raiseModal.show();
  });
</script>

<!-- Ticket Submission Script -->
<script>
  $(document).ready(function () {
    $("#ticketForm").submit(function (event) {
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
        beforeSend: function () {
          console.log("AJAX request is about to be sent..."); // Debugging
        },
        success: function (response) {
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
        error: function (xhr, status, error) {
          alert("An error occurred while submitting the ticket.");
          console.error("AJAX error:", status, error); // Debugging
          console.log("Response Text:", xhr.responseText); // Debugging
        }
      });
    });

    // Show/Hide Fields Based on Concern Selection
    $("#concernType").change(function () {
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



<!-- Change Password -->

<!-- Change Password and OTP Verification Modals -->

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel"
  aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title-wrapper">
          <h5 class="modal-title" id="changePasswordLabel">Change Password</h5>
          <small class="modal-subtext">Ensure your new password is secure and different from previous
            ones.</small>
        </div>
        <div class="modal-close-wrapper">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <form id="changePasswordForm">
        <div class="modal-body">
          <div class="mb-3">
            <label for="currentPassword" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="currentPassword" name="currentPassword"
              placeholder="Enter current password">
            <small id="currentPasswordError" class="error-label text-danger"></small>
          </div>

          <div class="mb-3">
            <label for="newPassword" class="form-label">New Password</label>
            <input type="password" class="form-control" id="newPassword" name="newPassword"
              placeholder="Enter new password" required>
            <small id="newPasswordError" class="error-label text-danger"></small>
          </div>

          <div class="mb-3">
            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirmNewPassword" name="confirmNewPassword"
              placeholder="Re-enter new password" required>
            <small id="confirmPasswordError" class="error-label text-danger"></small>
          </div>

          <div id="messageAlert"> </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- OTP Verification Modal -->
<div class="modal fade" id="otpVerificationModal" tabindex="-1" aria-labelledby="otpVerificationModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered ">
    <div class="modal-content otp-modal-content">


      <div class="modal-body">
        <div class="header-body-wrapper">
          <div class="otp-header">
            <div class="otp-icon-wrapper">
              <div class="otp-icon">
                <i class="fa-solid fa-lock"></i>
              </div>
            </div>

            <div class="header-body-content">
              <h5 class="modal-title">Verify OTP</h5>
              <p class="otp-subtext">To proceed with resetting your password, we’ve sent a verification
                code to your email address. <br> <span class="otp-email-mask">is****a8@gmail.com</span>
              </p>
            </div>
          </div>
        </div>

        <form id="otpVerificationForm">
          <div id="otpFieldContainer" class="otp-field-container">
            <div class="otp-modal-container">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp1">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp2">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp3">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp4">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp5">
              <input type="text" class="otp-modal-input" maxlength="1" id="otp6">
            </div>

            <small id="otpError" class="error-label text-danger"></small>
          </div>

          <div class="verify-button-wrapper">
            <button type="submit" class="btn btn-primary otp-verify-btn">Verify</button>
          </div>

          <div class="otpResent-button-wrapper">
            <p class="otp-resend-text">Didn’t receive code? </p> <a href="#" id="sendOtpBtn"
              class="otp-resend-link">Resend</a>
          </div>

          <div id="otpAlert"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Change Password Modal Open Script -->
<script>
  document.getElementById('changePasswordLink').addEventListener('click', function (e) {
    e.preventDefault(); // Prevent default link behavior
    var myModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    myModal.show();
  });
</script>

<!-- OTP Input Focus Script -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const otpInputs = document.querySelectorAll("#otpVerificationModal .otp-modal-input");

    otpInputs.forEach((input, index) => {
      input.addEventListener("input", (e) => {
        if (e.target.value && index < otpInputs.length - 1) {
          otpInputs[index + 1].focus();
        }
      });

      input.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && index > 0 && !e.target.value) {
          otpInputs[index - 1].focus();
        }
      });
    });
  });
</script>

<!-- jQuery Script for Change Password Modal -->
<script>
  $(document).ready(function () {

    let currentPassword, newPassword, confirmNewPassword;

    // Function for OTP Modal Alert
    function showOtpAlert(message, status) {

      // Set the color and background based on the status
      let backgroundColor, textColor, borderColor;

      // Determine the color scheme based on the provided status
      if (status === 'success') {
        backgroundColor = '#d4edda'; // Green background
        textColor = '#155724'; // Dark green text
        borderColor = '#c3e6cb'; // Green border
      } else if (status === 'error') {
        backgroundColor = '#f8d7da'; // Red background
        textColor = '#721c24'; // Dark red text
        borderColor = '#f5c6cb'; // Red border
      } else {
        backgroundColor = '#fff3cd'; // Yellow background (default for warnings)
        textColor = '#856404'; // Dark yellow text
        borderColor = '#ffeeba'; // Yellow border
      }

      // Apply the styles and show the alert
      $('#otpAlert').text(message).css({
        'background-color': backgroundColor,
        'color': textColor,
        'border': `1px solid ${borderColor}`
      }).show();

      // Hide the alert after 3.5 seconds (3500 milliseconds)
      setTimeout(function () {
        $('#otpAlert').fadeOut();
      }, 3500);
    }

    // Function for CP Alert
    function showCPAlert(message, status) {

      // Set the color and background based on the status
      let backgroundColor, textColor, borderColor;

      // Determine the color scheme based on the provided status
      if (status === 'success') {
        backgroundColor = '#d4edda'; // Green background
        textColor = '#155724'; // Dark green text
        borderColor = '#c3e6cb'; // Green border
      } else if (status === 'error') {
        backgroundColor = '#f8d7da'; // Red background
        textColor = '#721c24'; // Dark red text
        borderColor = '#f5c6cb'; // Red border
      } else {
        backgroundColor = '#fff3cd'; // Yellow background (default for warnings)
        textColor = '#856404'; // Dark yellow text
        borderColor = '#ffeeba'; // Yellow border
      }

      // Apply the styles and show the alert
      $('#messageAlert').text(message).css({
        'background-color': backgroundColor,
        'color': textColor,
        'border': `1px solid ${borderColor}`
      }).show();

      // Hide the alert after 3.5 seconds (3500 milliseconds)
      setTimeout(function () {
        $('#otpAlert').fadeOut();
      }, 3500);
    }

    // Function to send OTP
    function sendOtp(currentPassword, emailAddress) {

      if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
        showOtpAlert('Email address is missing. Please update your profile to receive OTP.', 'error');
        console.warn('Attempted to send OTP without a valid email address.');
        return;
      }

      sessionStorage.setItem('emailAddress', emailAddress);

      $.ajax({
        url: '../Employee Section/functions/General/emp-sendOtpCPassword.php',
        type: 'POST',
        data: {
          currentPassword: currentPassword,
          emailAddress: emailAddress
        },

        dataType: 'json',
        success: function (response) {
          if (response.status === 'success') {
            console.log('OTP Sent:', response.otp);

            // Mask the email for display
            function maskEmail(email) {
              const parts = email.split('@');
              const username = parts[0];
              const domain = parts[1];
              const maskedUsername = username.charAt(0) + '******' + username.charAt(username.length - 1);
              return maskedUsername + '@' + domain;
            }

            showOtpAlert('OTP has been sent to your email address.', 'success');

            setTimeout(function () {
              $('#changePasswordModal').modal('hide');
              $('#otpVerificationModal').modal('show');

              // Append accountId to form if provided
              if (response.accountId) {
                $('#otpVerificationForm').append('<input type="hidden" name="accountId" value="' + response.accountId + '">');
              }

              // Mask and display the email address
              const maskedEmail = maskEmail(emailAddress);
              $('#otpVerificationModal .otp-email-mask').text(maskedEmail);

              console.log('Masked Email:', maskedEmail);
            }, 500);

          } else {
            // Handle specific error message from backend
            console.log('Error Sending OTP:', response.message);
            showOtpAlert(response.message || 'Failed to send OTP. Please try again.', 'error');
          }
        },

        error: function (xhr, status, error) {
          console.log('AJAX Error:', error);
          showOtpAlert('An error occurred while sending OTP. Please try again later.', 'error');
        }
      });
    }

    // Handle form submission for change password
    $('#changePasswordForm').on('submit', function (e) {
      e.preventDefault(); // Prevent default form submission

      // Clear previous error messages and hide error labels
      document.getElementById('currentPasswordError').textContent = '';
      document.getElementById('newPasswordError').textContent = '';
      document.getElementById('confirmPasswordError').textContent = '';
      document.getElementById('messageAlert').style.display = 'none';

      currentPassword = document.getElementById('currentPassword').value;
      newPassword = document.getElementById('newPassword').value;
      confirmNewPassword = document.getElementById('confirmNewPassword').value;

      // You can now use these variables elsewhere in your code
      console.log(currentPassword, newPassword, confirmNewPassword);

      // Validate New Password
      if (newPassword.length < 8) {
        document.getElementById('newPasswordError').textContent = 'Password must be at least 8 characters long.';
        document.getElementById('newPasswordError').style.display = 'block';
        return;
      }

      // Validate New Password and Confirm Password
      if (newPassword !== confirmNewPassword) {
        document.getElementById('confirmPasswordError').textContent = 'Passwords do not match.';
        document.getElementById('confirmPasswordError').style.display = 'block';
        return;
      }

      $.ajax({
        url: '../Employee Section/functions/General/emp-changePassword.php',
        type: 'POST',
        data: {
          currentPassword: currentPassword,
          newPassword: newPassword
        },

        success: function (response) {
          response = JSON.parse(response);

          if (response.status === 'error') {
            document.getElementById('currentPasswordError').textContent = response.message;
            document.getElementById('currentPasswordError').style.display = 'block';
          }

          else if (response.status === 'success') {
            const accountId = response.accountId;
            const emailAddress = response.emailAddress;

            // Store in sessionStorage
            sessionStorage.setItem('emailAddress', emailAddress);


            console.log('Email Address:', emailAddress); // Debugging log
            showCPAlert(response.message, 'success');

            if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
              showCPAlert('Unable to send OTP. Email address is missing.', 'error');
              return;
            }

            // Proceed to send OTP
            setTimeout(function () {
              sendOtp(currentPassword, emailAddress); // Reusable OTP function
            }, 500);
          }

        },

        error: function (xhr, status, error) {
          console.log('AJAX Error:', error);
          showCPAlert('An error occurred while validating the password.', 'error');
        }
      });
    });

    // OTP verification form submission
    $('#otpVerificationForm').on('submit', function (e) {
      e.preventDefault(); // Prevent normal form submission

      let otp = '';
      let newPassword = document.getElementById('newPassword').value;
      let accountIdVerify = <?= isset($accountId) ? json_encode($accountId) : 'null'; ?>;
      console.log("Account ID:", accountIdVerify);


      $('.otp-modal-input').each(function () {
        otp += $(this).val();
      });

      console.log('OTP entered:', otp); // Debugging log

      if (otp.length !== 6) {
        $('#otpError').text('Please enter all 6 digits of the OTP.');
        console.warn('Invalid OTP length. OTP must be 6 digits.');
        return;
      }

      console.log('Sending OTP verification request...'); // Debugging log

      $.ajax({
        url: '../Employee Section/functions/General/emp-newPasswordChange.php',
        type: 'POST',
        data: {
          otp: otp,
          newPassword: newPassword,
          accountId: accountIdVerify
        },
        dataType: 'json',
        success: function (response) {
          console.log('OTP verification response:', response); // Debugging log
          if (response.status === 'success') {
            console.log('OTP Verified Successfully'); // Debugging log

            let newPassword = $('#newPassword').val();
            let accountId = <?= isset($accountId) ? json_encode($accountId) : 'null'; ?>;

            // Optional: Validate or use the ID
            if (accountId !== null) {
              console.log("Account ID:", accountId);
            } else {
              console.warn("Account ID is not set.");
            }

            if (!newPassword || newPassword.trim() === '') {
              console.warn('New password is empty or invalid.');
              showOtpAlert(response.message, 'error');
              return;
            }

            // Password Change AJAX Request
            console.log('Sending password change request...'); // Debugging log
            $.ajax({
              url: '../Employee Section/functions/General/emp-newPasswordChange.php',
              method: 'POST',
              data: {
                newPassword: newPassword,
                accountId: accountId
              },
              dataType: 'json',
              success: function (res) {
                console.log('Password change response:', res); // Debugging log
                if (res.status === 'success') {
                  console.log('Password changed successfully'); // Debugging log

                  showOtpAlert(response.message, response.status);

                  setTimeout(function () {
                    console.log('Reloading the page...');
                    location.reload(); // Reload the page after 3 seconds
                  }, 3000);

                } else {
                  console.warn('Password change failed:', res.message);
                  $('#messageAlert').show().text(res.message).css({
                    'background-color': '#f8d7da',
                    'color': '#721c24',
                    'border': '1px solid #f5c6cb'
                  });
                }
              },
              error: function (xhr, status, error) {
                console.error("AJAX Error (Password Change):", error);
                console.log("Response Text (Password Change):", xhr.responseText);

                $('#messageAlert').show().text('An error occurred while updating the password. Please try again.').css({
                  'background-color': '#f8d7da',
                  'color': '#721c24',
                  'border': '1px solid #f5c6cb'
                });
              }
            });

          } else if (response.status === 'error') {
            console.error('OTP Verification Failed:', response.message); // Error logging
            showOtpAlert(response.message, response.status);
          } else {
            console.warn('Unexpected response status:', response.status); // Warn for unexpected status
            showOtpAlert(response.message, response.status);
          }

        },
        error: function (xhr, status, error) {
          console.error("AJAX Error (OTP Verification):", error);
          console.log("Response Text (OTP Verification):", xhr.responseText);
          $('#otpError').text('An error occurred during OTP verification. Please try again.');
        }
      });
    });


    // Resend OTP functionality
    $('#sendOtpBtn').click(function (e) {
      e.preventDefault();

      const currentPassword = document.getElementById('currentPassword').value;

      // Safe email assignment from PHP
      let emailAddress = <?= isset($emailAddress) ? json_encode($emailAddress) : 'null'; ?>;


      // Early layer: if PHP email is already empty/null
      if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
        console.warn('Email from PHP session is missing. Checking sessionStorage as fallback.');
        emailAddress = sessionStorage.getItem('emailAddress');

        // If still not found, alert the user
        if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
          console.warn('Email address not found in PHP session or sessionStorage.');
          showOtpAlert('Unable to send OTP. No email address found. Please update your profile.', 'error');
          return;
        }
      }

      // Check if current password is empty
      if (!currentPassword || currentPassword.trim() === '') {
        showOtpAlert('Please enter your current password to resend OTP.', 'error');
        return;
      }

      // Final fallback: double-check before sending
      if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
        showOtpAlert('Email address is still invalid. Cannot send OTP.', 'error');
        return;
      }

      // Proceed to send OTP
      sendOtp(currentPassword, emailAddress); // Reuse existing OTP sending function
      showOtpAlert('Resending OTP. Please wait...', 'success');
    });

  });
</script>



<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="logoutModalLabel">Log Out</h5>
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

<script>
  $(document).ready(function () {
    $('#logoutButton').click(function () {
      $.ajax({
        url: '../Agent Section/functions/agent-logout.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            window.location.href = '../Agent Section/agentLogin.php';
          } else {
            alert(response.message);
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          console.error('AJAX Error:', textStatus, errorThrown);
          alert('An unexpected error occurred. Please try again.');
        }
      });
    });
  });
</script>


