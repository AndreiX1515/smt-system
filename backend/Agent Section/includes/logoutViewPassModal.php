<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">

        <div class="modal-title-wrapper">
          <h5 class="modal-title" id="changePasswordLabel">Change Password</h5>
          <small class="modal-subtext">Ensure your new password is secure and different from previous ones.</small>
        </div>

        <div class="modal-close-wrapper">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

      </div>

      <form id="changePasswordForm">
        <div class="modal-body">
          <div class="mb-3">
            <label for="currentPassword" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="currentPassword" name="currentPassword" placeholder="Enter current password">
            <small id="currentPasswordError" class="error-label text-danger"></small>
          </div>

          <div class="mb-3">
            <label for="newPassword" class="form-label">New Password</label>
            <input type="password" class="form-control" id="newPassword" name="newPassword" placeholder="Enter new password" required>
            <small id="newPasswordError" class="error-label text-danger"></small>
          </div>

          <div class="mb-3">
            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirmNewPassword" name="confirmNewPassword" placeholder="Re-enter new password" required>
            <small id="confirmPasswordError" class="error-label text-danger"></small>
          </div>

          <!-- <div id="otpFieldContainer">
              <label for="otp" class="form-label">OTP</label>
              <div class="d-flex align-items-center otp-container">
                  <input type="text" class="form-control otp-input" id="otp" name="otp" max="6" placeholder="Enter OTP">
                  <button type="button" class="btn btn-outline-primary resend-btn" id="sendOtpBtn">Send OTP</button>
              </div>
              <small id="confirmPasswordError" class="error-label text-danger"></small>
          </div> -->

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
<div class="modal fade" id="otpVerificationModal" tabindex="-1" aria-labelledby="otpVerificationModalLabel" aria-hidden="true">
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
              <p class="otp-subtext">To proceed with resetting your password, we’ve sent a verification code to your email address. <br> <span class="otp-email-mask">is****a8@gmail.com</span></p>
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
            <p class="otp-resend-text">Didn’t receive code? </p> <a href="#" id="sendOtpBtn" class="otp-resend-link">Resend</a>
          </div>

          <div id="otpAlert"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- OTP Input Focus Script -->
<script>
  document.addEventListener("DOMContentLoaded", function() {
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
  $(document).ready(function() {

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
        setTimeout(function() {
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
      setTimeout(function() {
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
        url: '../Agent Section/functions/agent-sendOtpCPassword.php',
        type: 'POST',
        data: {
          currentPassword: currentPassword,
          emailAddress: emailAddress
        },
        dataType: 'json',
        success: function(response) {
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

            setTimeout(function() {
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

        error: function(xhr, status, error) {
          console.log('AJAX Error:', error);
          showOtpAlert('An error occurred while sending OTP. Please try again later.', 'error');
        }
      });
    }

    // Handle form submission for change password
    $('#changePasswordForm').on('submit', function(e) {
      e.preventDefault(); // Prevent default form submission

      // Clear previous error messages and hide error labels
      document.getElementById('currentPasswordError').textContent = '';
      document.getElementById('newPasswordError').textContent = '';
      document.getElementById('confirmPasswordError').textContent = '';
      document.getElementById('messageAlert').style.display = 'none';

      // Get form data
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmNewPassword = document.getElementById('confirmNewPassword').value;
      const emailAddress = <?= json_encode($_SESSION['agent_emailAddress'] ?? null); ?>;
      console.log("Email from session:", emailAddress);

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
        url: '../Agent Section/functions/General/agent-changePassword.php',
        type: 'POST',
        data: {
          currentPassword: currentPassword,
          newPassword: newPassword,
          emailAddress: emailAddress
        },

        success: function(response) {
          response = JSON.parse(response);

          if (response.status === 'error') {
            document.getElementById('currentPasswordError').textContent = response.message;
            document.getElementById('currentPasswordError').style.display = 'block';

          } else if (response.status === 'success') {
            const accountId = response.accountId;
            const emailAddress = response.emailAddress;

            // Store in sessionStorage
            sessionStorage.setItem('emailAddress', emailAddress);

            console.log('Email Address:', emailAddress); // Debugging log

            // ✅ Use showCPAlert for success message
            showCPAlert(response.message, 'success');

            // ✅ Check if email is missing
            if (!emailAddress || emailAddress === 'null' || emailAddress.trim() === '') {
              showCPAlert('Unable to send OTP. Email address is missing.', 'error');
              return;
            }

            // Proceed to send OTP
            setTimeout(function() {
              sendOtp(currentPassword, emailAddress); // Reusable OTP function
            }, 500);
          }
        },

        error: function(xhr, status, error) {
          console.log('AJAX Error:', error);
          showCPAlert('An error occurred while validating the password.', 'error');
        }
      });
    });

    // OTP verification form submission
    $('#otpVerificationForm').on('submit', function(e) {
      e.preventDefault(); // Prevent normal form submission

      let otp = '';
      $('.otp-modal-input').each(function() {
        otp += $(this).val();
      });

      if (otp.length !== 6) {
        $('#otpError').text('Please enter all 6 digits of the OTP.');
        return;
      }

      $.ajax({
        url: '../Agent Section/functions/General/agent-verifyOTP.php',
        type: 'POST',
        data: {
          otp: otp
        },

        dataType: 'json',
        success: function(response) {
          if (response.status === 'success') {
            console.log('OTP Verified Successfully'); // Debugging log

            let newPassword = $('#newPassword').val();
            let accountId = <?= $accountId; ?>;

            if (!newPassword || newPassword.trim() === '') {

              showOtpAlert(response.message, 'error'); 
              return;
            }

            // Password Change AJAX Request
            $.ajax({
              url: '../Agent Section/functions/General/agent-newPasswordChange.php',
              method: 'POST',
              data: {
                newPassword: newPassword,
                accountId: accountId
              },
              dataType: 'json',
              success: function(res) {
                if (res.status === 'success') {

                  showOtpAlert(response.message, response.status); 

                  setTimeout(function() {
                    location.reload(); // Reload the page after 2 seconds
                  }, 3000);

                } else {
                  $('#messageAlert').show().text(res.message).css({
                    'background-color': '#f8d7da',
                    'color': '#721c24',
                    'border': '1px solid #f5c6cb'
                  });
                }


              },
              error: function(xhr, status, error) {
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
            console.log('OTP Verification Failed:', response.message); 
            showOtpAlert(response.message, response.status); 
          }   
          
          else {
            showOtpAlert(response.message, response.status); 
          }

        },
        error: function(xhr, status, error) {
          console.error("AJAX Error (OTP Verification):", error);
          console.log("Response Text (OTP Verification):", xhr.responseText);
          $('#otpError').text('An error occurred during OTP verification. Please try again.');
        }
      });
    });

    // Resend OTP functionality
    $('#sendOtpBtn').click(function(e) {
      e.preventDefault();

        const currentPassword = document.getElementById('currentPassword').value;

        let emailAddress = <?= json_encode($_SESSION['agent_emailAddress'] ?? null); ?>;
        console.log("Email from session:", emailAddress);
      

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

<!-- Logout Script -->
<script>
  $(document).ready(function() {
    $('#logoutButton').click(function() {
      $.ajax({
        url: '../Agent Section/functions/agent-logout.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            window.location.href = '../Agent Section/agentLogin.php';
          } else {
            window.location.href = '../Agent Section/agentLogin.php';
          }
        },
        error: function(jqXHR, textStatus, errorThrown) {
          console.error('AJAX Error:', textStatus, errorThrown);
          alert("An error occurred during logout. Please try again.");
          window.location.href = '../Agent Section/agentLogin.php';
        }
      });
    });
  });
</script>

<!-- <script>
  $(document).ready(function() {
    // Handle OTP Send Button
    $('#sendOtpBtn').click(function() {
      // Get the values for the new password and confirmed password
      var newPassword = $('#newPassword').val();
      var confirmPassword = $('#confirmNewPassword').val();

      console.log(newPassword, confirmPassword)

      // Check if both new password and confirmed password have values
      if (!newPassword || !confirmPassword) {
        // Display error message if either password is empty
        $('#messageAlert').text('Please fill in both the new password and confirm password fields.')
          .css('border', '1px solid red')
          .css('background-color', '#f8d7da')
          .css('color', 'red')
          .show();
        return; // Stop the function from continuing
      }

      // Check if the new password and confirmed password match
      if (newPassword !== confirmPassword) {
        // Display error message if passwords don't match
        $('#messageAlert').text('The new password and confirm password do not match.')
          .css('border', '1px solid red')
          .css('background-color', '#f8d7da')
          .css('color', 'red')
          .show();
        return; // Stop the function from continuing
      }

      // Clear the error message if the passwords are valid
      $('#messageAlert').hide();

      var email = "<?php echo htmlspecialchars($email); ?>"; // PHP to JS variable

      // Show a loading spinner or disable the button to prevent multiple requests
      $('#sendOtpBtn')
        .prop('disabled', true)
        .text('Sending OTP...')
        .css('font-size', '12px'); // This sets the font size to 12px (adjust as needed)

      $.ajax({
        url: '../Agent Section/functions/agent-sendOtpCPassword.php',
        type: 'POST',
        data: {
          email: email // Send the user's email to the server
        },
        success: function(response) {
          // Parse the JSON response from the server
          var jsonResponse = JSON.parse(response);

          if (jsonResponse.success) {
            // Inform the user that OTP was sent successfully
            $('#messageAlert').text('OTP has been sent to your registered email address.')
              .css('border', '1px solid green')
              .css('background-color', '#d4edda')
              .css('color', 'green')
              .show();
          } else {
            // Handle the error (invalid email, failed to send OTP, etc.)
            $('#messageAlert').text('Failed to send OTP: ' + jsonResponse.message)
              .css('border', '1px solid red')
              .css('background-color', '#f8d7da')
              .css('color', 'red')
              .show();
          }

          // Re-enable the button and reset its text
          $('#sendOtpBtn').prop('disabled', false).text('Send OTP');
        },
        error: function() {
          // Handle any error that occurred during the AJAX request
          $('#messageAlert').text('An error occurred while sending the OTP.')
            .css('border', '1px solid red')
            .css('background-color', '#f8d7da')
            .css('color', 'red')
            .show();
          $('#sendOtpBtn').prop('disabled', false).text('Send OTP');
        }
      });
    });
  });

  $(document).ready(function() {
    // Handle Change Password Form submission
    $('#changePasswordForm').submit(function(e) {
      e.preventDefault(); // Prevent the default form submission

      // Get the entered OTP and new password details
      var enteredOtp = $('#otp').val(); // OTP input field ID 'otp'
      var newPassword = $('#newPassword').val();
      var confirmNewPassword = $('#confirmNewPassword').val();
      var account_id = "<?php echo htmlspecialchars($accountId); ?>"; // PHP to JS variable


      // Check if the new password and confirmation match
      if (newPassword !== confirmNewPassword) {
        $('#messageAlert').text('Passwords do not match.')
          .css('border', '1px solid red')
          .css('background-color', '#f8d7da')
          .css('color', 'red')
          .show();
        return; // Stop further execution if passwords do not match
      }

      // Perform the AJAX request to verify OTP
      $.ajax({
        url: '../Agent Section/functions/agent-verify-otp.php', // Path to OTP verification script
        type: 'POST',
        data: {
          'changepass-OTP': enteredOtp,
          'accountid': account_id,
          'newPassword': newPassword // No underscore here
        },
        success: function(response) {
          var jsonResponse = JSON.parse(response); // Assuming the server returns JSON

          // If OTP is valid, proceed with password change
          if (jsonResponse.success) {
            // Proceed with password change if OTP is verified
            $.ajax({
              url: '../Agent Section/functions/agent-passwordChangeFunction.php', // Path to password change script
              type: 'POST',
              data: {
                'changepass-OTP': enteredOtp,
                'accountid': account_id,
                'newPassword': newPassword // No underscore here
              },
              success: function(changePasswordResponse) {
                var changeResponse = JSON.parse(changePasswordResponse);
                if (changeResponse.status === 'success') {
                  $('#messageAlert').text('Password changed successfully.')
                    .css('border', '1px solid green')
                    .css('background-color', '#d4edda')
                    .css('color', 'green')
                    .show();
                  location.reload();
                } else {
                  $('#messageAlert').text(changeResponse.message)
                    .css('border', '1px solid red')
                    .css('background-color', '#f8d7da')
                    .css('color', 'red')
                    .show();
                }
              },
              error: function() {
                $('#messageAlert').text('An error occurred while changing the password.')
                  .css('border', '1px solid red')
                  .css('background-color', '#f8d7da')
                  .css('color', 'red')
                  .show();
              }
            });
          } else {
            $('#messageAlert').text('Invalid OTP entered.')
              .css('border', '1px solid red')
              .css('background-color', '#f8d7da')
              .css('color', 'red')
              .show();
          }
        },
        error: function() {
          $('#messageAlert').text('An error occurred while verifying the OTP.')
            .css('border', '1px solid red')
            .css('background-color', '#f8d7da')
            .css('color', 'red')
            .show();
        }
      });
    });
  });
</script> -->

<!-- <script>
  document.addEventListener('DOMContentLoaded', () => 
  {
    // Check if there's a saved title in local storage
    const savedTitle = localStorage.getItem('pageTitle');
    if (savedTitle) 
    {
      document.getElementById('page-title').textContent = savedTitle;
    }

    const buttons = document.querySelectorAll('.page-button');

    buttons.forEach(button => 
    {
      button.addEventListener('click', (event) => 
      {
        event.preventDefault();
        const newPageName = button.getAttribute('data-page-name');
        document.getElementById('page-title').textContent = newPageName;

        localStorage.setItem('pageTitle', newPageName);

        const newUrl = button.getAttribute('href');
        setTimeout(() => 
        {
          window.location.href = newUrl;
        }, 25);
      });
    });
  });
</script> -->