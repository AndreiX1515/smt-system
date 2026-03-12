<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Payment</title>

  <?php include "../Agent Section/includes/head.php"; ?>


  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/agent-payment.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

  <?php include "../Agent Section/includes/sidebar.php"; ?>

  <div class="main-container">  

    <div class="navbar">
      <div class="page-header-wrapper">

        <!-- <div class="page-header-top">
          <div class="back-btn-wrapper">
            <button class="back-btn" id="logout-btn">
              <i class="fas fa-chevron-left"></i>
            </button>
          </div>
        </div> -->

        <div class="page-header-content">
          <div class="page-header-text">
            <h5 class="header-title">Payment Details</h5>
          </div>
        </div>

      </div>
    </div>

    <?php
    // Check if 'id' is passed in the URL
    if (isset($_GET['id'])) {
      $transactionNumber = htmlspecialchars($_GET['id']);
    }
    ?>


    <div class="main-content">

      <div class="container-body">

        <div class="info-wrapper">
          <div class="payment-method">

            <div class="section section-1">
              <div class="header-container">
                <h4>Payment Method</h4>
                <p>Please select your preferred payment method to complete the booking process.</p>
              </div>


              <div class="section-body">

                <div class="section-content">
                  <div class="row-content">

                    <!-- Bank Transfer -->
                    <div class="card-wrapper">
                      <div class="billing-card">
                        <div class="payment-content">
                          <div class="payment-logo">
                            <i class="fas fa-university fa-2x"></i>
                          </div>
                          <div class="payment-name">
                            <span>Bank Transfer</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="card-wrapper">
                      <div class="billing-card">
                        <div class="payment-content">
                          <div class="payment-logo">
                            <!-- <i class="fas fa-mobile-alt fa-2x"></i>  -->
                          </div>
                          <div class="payment-name">
                            <span></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="section-content">
                  <div class="row-content">
                    <!-- Bank Transfer -->
                    <div class="card-wrapper">
                      <div class="billing-card disabled">
                        <div class="payment-content">
                          <div class="payment-logo">
                            <i class="fas fa-mobile-alt fa-2x"></i>
                          </div>
                          <div class="payment-name">
                            <span>GCash</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Smart/Sun -->
                    <div class="card-wrapper">
                      <div class="billing-card disabled">
                        <div class="payment-content">
                          <div class="payment-logo">
                            <i class="fas fa-mobile-alt fa-2x"></i>
                          </div>
                          <div class="payment-name">
                            <span>Paymaya</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div class="subscription">

            <script>
              document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll('.billing-card').forEach(card => {
                  card.addEventListener('click', function() {
                    // Remove active state from all cards
                    document.querySelectorAll('.billing-card').forEach(c => {
                      c.classList.remove('active');
                      c.querySelector('.hidden-radio').checked = false;
                    });

                    // Add active state to the clicked card
                    this.classList.add('active');
                    this.querySelector('.hidden-radio').checked = true;
                  });
                });
              });
            </script>

            <div class="section section-1">
              <div class="header-container">
                <h4>Bank Details</h4>
                <p>Please ensure that the payment details are correct before proceeding with the transaction.</p>
              </div>

              <div class="bank-detail-row">
                <div class="bank-detail-col">
                  <label for="bank-name">Bank Name:</label>
                  <p id="bank-name">Banco De Oro (BDO)</p>
                </div>
                <div class="bank-detail-col">
                  <label for="account-name">Account Name:</label>
                  <p id="account-name">Hyung Sub Kim (Nickname: Jed Kim)</p>
                </div>
              </div>

              <div class="bank-detail-row">
                <div class="bank-detail-col">
                  <label for="account-number">Account Number (PH - Peso):</label>
                  <p id="account-number">00780020352</p>
                </div>
              </div>

              <div class="bank-detail-row">
                <div class="bank-detail-col">
                  <label for="account-number">Account Number (US - Dollar):</label>
                  <p id="account-number">10780018789</p>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="order-summary">
          <?php
          $packageName = "N/A";
          $pax = 0;
          $flightDate = "N/A";
          $formattedDP = "0.00";
          $formattedPrice = "0.00";

          $sql1 = mysqli_query($conn, "SELECT b.pax, b.totalPrice,
                  IF(f.flightId != 0, DATE_FORMAT(f.flightDepartureDate, '%M %d, %Y'), 'Custom Scheduled Flight') 
                  AS onboardFlightSched, p.packageName 
              FROM booking b 
              JOIN flight f ON b.flightId = f.flightId 
              JOIN package p ON b.packageId = p.packageId 
              WHERE b.transactNo = '$transactionNumber'");

          if ($sql1 && mysqli_num_rows($sql1) > 0) {
            while ($res1 = mysqli_fetch_array($sql1)) {
              $totalPrice = $res1['totalPrice'];
              $formattedPrice = number_format($totalPrice, 2); // Format to 2 decimal places
              $downpayment = $res1['pax'] * 3000;
              $formattedDP = number_format($downpayment, 2); // Format to 2 decimal places
          
              // Get additional fields
              $flightDate = $res1['onboardFlightSched'];
              $packageName = $res1['packageName'];
              $pax = $res1['pax'];
            }

          } else {
            echo "<p class='text-danger'>No booking details found for TransactNo: $transactionNumber.</p>";
          }
          ?>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0"><strong>Package Name:</strong></p>
                <p class="mb-0"><?php echo $packageName; ?></p> <!-- Added commas for better readability -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0"><strong>Total Number of Guest:</strong></p>
                <p class="mb-0"><?php echo $pax; ?></p> <!-- Added commas for better readability -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0"><strong>Flight Date:</strong></p>
                <p class="mb-0"><?php echo $flightDate; ?></p> <!-- Added commas for better readability -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0"><strong>Downpayment:</strong></p>
                <p class="mb-0">Minimum ₱ <?php echo $formattedDP; ?></p> <!-- Added commas for better readability -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0">₱ 3,000 per Guest.</p> <!-- Added space for better readability -->
              </div>
            </div>
          </div>

          <hr>

          <div class="row">
            <div class="col-sm">
              <div class="d-flex justify-content-between mb-1">
                <p class="mb-0"><strong>Total:</strong></p>
                <p class="mb-0">₱ <?php echo $formattedPrice; ?></p> <!-- Added commas for better readability -->
              </div>
            </div>
          </div>

          <!--  -->

          <form id="paymentForm" enctype="multipart/form-data">
            <hr>
            <input type="hidden" value="<?php echo $_SESSION['agent_accountId']; ?>" name="agentAccountId">
            <input type="hidden" value="<?php echo $transactionNumber; ?>" name="transactNo">
            <input type="number" class="form-control" name="downpayment" step="0.01" min="<?php echo $downpayment; ?>"
              max="<?php echo $totalPrice; ?>" placeholder="Enter Downpayment Amount" required>

            <h6 class="mt-4">Attach Proof/Screenshot of transaction:</h6>
            <input type="file" id="attachment" class="attachment" name="proofs[]" accept="image/*" required>
            <hr>

            <div class="row mt-4">
              <div class="col-sm">
                <div class="d-flex align-items-left mb-3">
                  <input type="checkbox" id="termsCheckbox" class="ms-1 me-3" required>
                  <div class="checkbox-text">
                    <span>
                      By clicking this, I agree to Smart Travel <a href="#" class="terms-link">Terms & Conditions</a>
                      and
                      <a href="#" class="privacy-link">Privacy Policy</a>
                    </span>
                  </div>
                </div>

                <button type="submit" class="pay-button btn btn-success">
                  Pay Now
                </button>

          </form>

          <button type="button" class="reserve-button btn btn-secondary" data-bs-toggle="modal"
            data-bs-target="#payLaterModal">
            Reserve Booking
          </button>

        </div>

      </div>

    </div>

  </div>

  <!-- Pay Now Modal (Centered) -->
  <div class="modal fade" id="payNowModal" tabindex="-1" aria-labelledby="payNowLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">

      <!-- Mobile-friendly & Centered -->
      <div class="modal-content pay-now-modal">
        <!-- Success Icon -->
        <div class="modal-body text-center">
          <div class="pay-now-success-icon">
            <div class="circle"></div>
            <div class="checkmark"></div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="modal-body pay-now-body">
          <p>Booking Confirmation</p>
          <p class="pay-now-secondary">Ensure sufficient balance or a valid payment method before proceeding.</p>
        </div>

        <!-- Footer -->
        <div class="modal-footer pay-now-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="confirmPayment">Confirm</button>
        </div>
      </div>

    </div>
  </div>

  <!-- Pay Later Modal (Centered) -->
  <div class="modal fade" id="payLaterModal" tabindex="-1" aria-labelledby="payLaterLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">

      <!-- Mobile-friendly & Centered -->
      <div class="modal-content pay-later-modal">
        <!-- Warning Icon -->
        <div class="modal-body text-center">
          <div class="pay-later-warning-icon">
            <div class="circle"></div>
            <div class="exclamation"></div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="modal-body pay-later-body">
          <p>Your booking will be placed under <strong>"Reserved"</strong> status.</p>
          <p class="pay-later-secondary">Failure to complete the payment within the given timeframe may result in
            cancellation.</p>
        </div>

        <!-- Footer -->
        <div class="modal-footer pay-later-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="confirmLogout" data-bs-toggle="modal"
            data-bs-target="#successModal">Confirm</button>
        </div>
      </div>

    </div>
  </div>

  <!-- Booking Accept Modal - Reserved -->
  <div class="modal fade" id="successModalLater" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
        </div>
        <div class="modal-body text-center">

          <div class="success-icon">
            <div class="circle"></div>
            <div class="checkmark"></div>
          </div>

          <div class="text-content">
            <h4>Successfully Booked!</h4>
            <p>Your booking transaction <strong><?php echo $transactionNumber; ?></strong> has been successfully
              <strong>Booked!</strong> Please wait for a confirmation email, which will be sent to you shortly.
            </p>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success w-100" id="okButtonLater">Got it</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Booking Accept Modal - Pending -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
        </div>
        <div class="modal-body text-center">

          <div class="success-icon">
            <div class="circle"></div>
            <div class="checkmark"></div>
          </div>

          <div class="text-content">
            <h4>Successfully Booked!</h4>
            <p>Your booking transaction <strong><?php echo $transactionNumber; ?></strong> has been successfully
              <strong>Booked!</strong> Please wait for a confirmation email, which will be sent to you shortly.
            </p>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-success w-100" id="okButton">Got it</button>
        </div>
      </div>
    </div>
  </div>


  <?php require "../Agent Section/includes/scripts.php"; ?>

  <!-- Script for Pay Later Modal -->
  <script>
    $(document).ready(function () {
      $("#confirmLogout").click(function (event) {
        event.preventDefault(); // Prevent default modal opening behavior

        let hasErrors = false;
        let requiredField = $("#requiredField").val().trim(); // Replace with actual input field ID

        // Clear previous errors
        $(".error-text").text("");

        // Validate Required Field
        if (requiredField === "") {
          $("#requiredFieldError").text("This field is required.");
          hasErrors = true;
        }

        if (!hasErrors) {
          // No errors, proceed with showing the modal
          $("#payLaterModal").modal("hide");
          $("#successModal").modal("show");
        }
      });

      // ✅ Redirect on "Got it" Click with 1-second delay
      $("#okButton").click(function () {
        setTimeout(function () {
          window.location.href = "../Agent Section/agent-showGuest.php?id=<?= $transactionNumber ?>";
        }, 1000); // 1 second = 1000ms
      });

      // ✅ Optional: Redirect if modal is closed manually with 1-second delay
      $("#successModal").on("hidden.bs.modal", function () {
        setTimeout(function () {
          window.location.href = "../Agent Section/agent-showGuest.php?id=<?= $transactionNumber ?>";
        }, 1000);
      });
    });
  </script>


  <!-- Script for Pay Now -->
  <script>
    $(document).ready(function () {
      $("#paymentForm").on("submit", function (event) {
        event.preventDefault(); // Prevent default form submission

        $('#message-payment').html(''); // Clear previous messages
        $(".error-text").remove(); // Remove previous error messages

        let hasErrors = false;

        // Get input values
        let downpayment = $("input[name='downpayment']").val().trim();
        let minDownpayment = parseFloat($("input[name='downpayment']").attr("min"));
        let attachment = $("#attachment").val();
        let termsChecked = $("#termsCheckbox").is(":checked");

        // Downpayment Validation
        if (downpayment === "" || isNaN(downpayment)) {
          $("input[name='downpayment']").after('<small class="error-text text-danger">Please enter a valid amount.</small>');
          hasErrors = true;
        } else if (parseFloat(downpayment) < minDownpayment) {
          $("input[name='downpayment']").after(`<small class="error-text text-danger">Minimum downpayment is ${minDownpayment}.</small>`);
          hasErrors = true;
        }

        // Attachment Validation
        if (attachment === "") {
          $("#attachment").after('<small class="error-text text-danger">Proof of transaction is required.</small>');
          hasErrors = true;
        }

        // Terms Checkbox Validation
        if (!termsChecked) {
          $('#message-payment').html('<div class="alert alert-danger">You must agree to the Terms & Conditions and Privacy Policy.</div>');
          hasErrors = true;
        }

        // Prevent AJAX submission & modal opening if errors exist
        if (hasErrors) {
          return;
        }

        let formData = new FormData(this);
        formData.append('pay', '1'); // Add identifier for processing

        $.ajax({
          // 
          url: "../Agent Section/functions/agent-addBookingPayment-code.php",
          type: "POST",
          data: formData,
          contentType: false,
          processData: false,
          beforeSend: function () {
            $('#message-payment').html('<div class="alert alert-info">Processing payment...</div>');
          },
          success: function (response) {
            console.log("Server Response:", response);

            let res;

            try {
              res = typeof response === "string" ? JSON.parse(response) : response;

              if (res.status === "success") {
                let bookingStatus = res.bookingStatus; // Get bookingStatus from response
                let transactionNumber = res.transactionNumber; // Get transactionNumber

                // ✅ If "Pay Later", show Reserved modal
                if (bookingStatus === "Pay Later") {
                  $("#successModalLater").modal("show");

                  // ✅ Otherwise, show Booked modal
                } else {
                  $("#successModal").modal("show");
                }

              } else {
                $('#message-payment').html('<div class="alert alert-danger">' + res.message + '</div>');
                console.error("Payment Error:", res.message);
              }
            } catch (error) {
              $('#message-payment').html('<div class="alert alert-danger">Unexpected error. Please try again.</div>');
              console.error("JSON Parse Error:", error);
            }
          },
          error: function (xhr, status, error) {
            $('#message-payment').html('<div class="alert alert-danger">Error processing payment. Please try again.</div>');
            console.error("AJAX Error:", status, error);
          }
        });
      });

      // ✅ Ensure modal allows closing by clicking outside or pressing ESC
      $("#successModal").modal({
        backdrop: true,  // Allow closing by clicking outside
        keyboard: true   // Allow closing with ESC key
      });

      // ✅ Redirect when "Got it" is clicked
      $("#okButton").on("click", function () {
        $("#successModal").modal("hide"); // Ensure modal hides first
        setTimeout(function () {
          window.location.href = "../Agent Section/agent-showGuest.php?id=<?= $transactionNumber ?>";
        }, 500); // Small delay for a smooth transition
      });

      // ✅ Redirect when modal is closed (by clicking outside or pressing ESC)
      $("#successModal").on("hidden.bs.modal", function () {
        window.location.href = "../Agent Section/agent-showGuest.php?id=<?= $transactionNumber ?>";
      });
    });



  </script>

</body>

</html>