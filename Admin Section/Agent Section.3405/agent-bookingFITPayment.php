
<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Payment for FIT - Agent</title>

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Agent Section/assets/css/agent-payment.css?v=<?php echo time(); ?>">
 </head>
 
  <body>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php include '../Agent Section/includes/sidebar.php' ?>

    <div class="main-content" id="mainContent">
      <?php include '../Agent Section/includes/navbar.php' ?>

      <?php
        // Check if 'id' is passed in the URL
        if (isset($_GET['id'])) 
        {
          $transactionNumber = htmlspecialchars($_GET['id']);
        }
      ?>

      <div class="container">
       
        <a href="agent-addbooking.php" class="back-button">
          <i class="fas fa-arrow-left"></i>
        </a>

        <div class="subscription">
          <h3 class="ms-3">Payment Details</h3>

          <div class="section section-1 px-3">
            <div class="header-container d-flex flex-row justify-content-between mb-2">
              <h4>Choose Payment Method</h4>
            </div>

            <div class="billing-options mt-4" >
              <!-- Bank Transfer Payment Option -->
              <div class="billing-card" data-value="bank-transfer">
                <div class="radiobutton-container">
                  <input type="radio" name="billing">
                </div>
                <div class="payment-logo" style="margin-top: 10px;">
                  <i class="fas fa-money-bill-transfer" style="font-size: 52px;"></i>
                  <span>Bank Transfer</span>
                </div>
              </div>
            </div>
          </div>

          <div class="section section-1 px-3">
            <h3>Bank Details</h3>
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
         
        <div class="order-summary">
          <?php
            $sql1 = mysqli_query($conn, "SELECT h.HotelName as hotelName, r.rooms as roomName, f.nights as nights, 
                                          CONCAT(DATE_FORMAT(f.startDate, '%Y-%m-%d'), ' to ', DATE_FORMAT(f.returnDate, '%Y-%m-%d')) 
                                          AS tripDuration, f.pax as pax, f.rooms as noOfRooms, f.phpPrice as price
                                        FROM fit f
                                        JOIN fithotel h ON h.hotelId = f.hotelId
                                        JOIN fitrooms r ON r.roomId = f.roomId
                                        WHERE transactionNo = '$transactionNumber'");

            if ($sql1 && mysqli_num_rows($sql1) > 0) 
            {
              $res1 = mysqli_fetch_assoc($sql1); // Fetch a single row since transactNo is unique
              $downpayment = $res1['pax'] * 1000;
              $formattedPrice = number_format($res1['price'], 2); // Format to 2 decimal places
            } 
            else 
            {
              echo "<p class='text-danger'>No booking details found for TransactNo: $transactionNumber.</p>";
            }
          ?>

          <?php if (!empty($res1)) 
          { ?>
            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Hotel:</strong></p>
                  <p class="mb-0"><?php echo $res1['hotelName']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Room Type:</strong></p>
                  <p class="mb-0"><?php echo $res1['roomName']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Total Nights:</strong></p>
                  <p class="mb-0"><?php echo $res1['nights']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Number of Rooms:</strong></p>
                  <p class="mb-0"><?php echo $res1['noOfRooms']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Trip Duration:</strong></p>
                  <p class="mb-0"><?php echo $res1['tripDuration']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Total Guests:</strong></p>
                  <p class="mb-0"><?php echo $res1['pax']; ?></p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm">
                <div class="d-flex justify-content-between mb-1">
                  <p class="mb-0"><strong>Total Price:</strong></p>
                  <p class="mb-0">₱ <?php echo number_format($res1['price'], 2); ?></p>
                </div>
              </div>
            </div>
          <?php 
          } 
          ?>

          <form action="../Agent Section/functions/agent-addFITBookingPayment.php" method="POST" enctype="multipart/form-data">
            <hr>
            <input type="hidden" value="<?php echo $_SESSION['agent_accountId']; ?>" name="agentAccountId">
            <input type="hidden" value="<?php echo $transactionNumber; ?>" name="transactNo">
            <input type="number" class="form-control" name="downpayment" step="0.01" 
                  min="<?php echo $downpayment; ?>" 
                  max="<?php echo $res1['price']; ?>" 
                  placeholder="Enter Downpayment Amount" required>
            <h6 class="mt-4">Attach Proof/Screenshot of transaction:</h6>
            <input type="file" id="attachment" class="attachment" name="proofs[]" accept="image/*" required>
            <hr>

            <div class="row mt-4">
              <div class="col-sm">
                <div class="d-flex align-items-left mb-3">
                  <input type="checkbox" class="ms-1 me-3" required>
                  <div class="checkbox-text">
                    <span>
                      By clicking this, I agree to Smart Travel <a href="#" class="terms-link">Terms & Conditions</a> and 
                      <a href="#" class="privacy-link">Privacy Policy</a>
                    </span>
                  </div>
                </div>
                <button type="submit" class="pay-button" name="pay">Pay Now</button>
              </div>
            </div>
          </form>
        </div>

      </div> 
    </div>

    <?php require "../Agent Section/includes/scripts.php"; ?>

  </body>

</html>