<?php
session_start();
require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!-- Session Variables -->


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction Information</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-showguest.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

    <?php include "../Agent Section/includes/sidebar.php"; ?>

    <?php
    $sql1 = "Select * from branch where branchId= '$branchId'";
    $result1 = $conn->query($sql1);

    // Check if a result is returned
    if ($result1->num_rows > 0) {
      // Fetch the branchName
      $row = $result1->fetch_assoc();
      $branchName = $row['branchName'];
    } else {
      $branchName = "No Branch";
    }

    // Format the full name
    $fullName = htmlspecialchars($lName . ', ' . $fName . ($mName ? ' ' . substr($mName, 0, 1) . '.' : ''));

    // Optional: hide password by default
    $maskedPassword = '••••••••••';
    ?>

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
              <h5 class="header-title">Transaction</h5>
            </div>
          </div>

        </div>
      </div>

      <script>
        document.getElementById("redirect-btn").addEventListener("click", function () {
          window.location.href = "../Agent Section/agent-transactions.php";
        });
      </script>

      <!-- Current Date Variable -->
      <?php
        date_default_timezone_set('Asia/Taipei');
        $current_date = date('D, F d, Y');
      ?>

      <!-- Transact Number Session Variable -->
      <?php
        if (isset($_SESSION['transaction_number'])) {
          $transactionNumber = $_SESSION['transaction_number'];
        }

        if (isset($_GET['id'])) {
          $transactionNumber = htmlspecialchars($_GET['id']);
        }
      ?>

      <?php
        $query1 = "SELECT booking.*, package.packageName, flight.flightDepartureDate 
                    FROM booking 
                    JOIN package ON booking.packageId = package.packageId
                    LEFT JOIN flight ON booking.flightId = flight.flightId
                    WHERE transactNo = '$transactionNumber'";

        $result1 = $conn->query($query1);

        if ($result1->num_rows > 0) 
        {
          // Output data of each row
          while ($row1 = $result1->fetch_assoc()) 
          {
            $transactNum = $row1['transactNo'];
            $fName = $row1['fName'];
            $mName = $row1['mName'];
            $lName = $row1['lName'];
            $suffix = $row1['suffix'];
            $countryCode = $row1['countryCode'];
            $contact = $row1['contactNo'];
            $email = $row1['email'];
            $packageName = $row1['packageName'];
            $flightDate = $row1['flightDepartureDate'];
            $pax = $row1['pax'];
            $status = $row1['status'];
            $price = $row1['totalPrice'];
            $flightId = $row1['flightId']; // Fetch flightId


            $fullName = $lName . ", " . $fName . " " .
              ($suffix !== 'N/A' ? $suffix . " " : "") .  
              ($mName !== 'N/A' ? substr($mName, 0, 1) . ". " : ""); 

            $contactNo = $countryCode . $contact;


            if (is_null($flightId)) 
            {
              $flightDate = "Land Package Only";
            }

            $status = isset($row1['status']) ? $row1['status'] : 'Unknown';

            // Initialize an empty class string
            $statusClass = '';

            // Assign classes based on the status value using switch
            switch ($status) 
            {
              case 'Confirmed':
                $statusClass = 'bg-success text-white'; // Green background, white text
                break;
              case 'Cancelled':
                $statusClass = 'bg-danger text-white'; // Red background, white text
                break;
              case 'Pending':
                $statusClass = 'bg-warning text-dark'; // Yellow background, dark text
                break;
              default:
                $statusClass = 'bg-secondary text-white'; // Gray background, white text
                break;
            }
          }
        } 
        else 
        {
          echo "0 results";
        }
      ?>

      <div class="main-content">
        <div class="show-guest-wrapper">
          
          <div class="header">

            <div class="transaction-info">
              <div class="transaction-header">
                <h5 class="">Transaction Information: </h5>
              </div>

              <div class="transaction-info-body">
                <div class="row">
                  <div class="col-md-5 columns">
                    <div class="info-item">
                      <p><strong>Transaction No:</strong> <?php echo htmlspecialchars($transactNum); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Total Pax:</strong> <?php echo htmlspecialchars($pax); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Package:</strong> <?php echo htmlspecialchars($packageName); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Flight Date:</strong> <?php echo htmlspecialchars($flightDate); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Status:</strong> <span class="badge rounded-pill <?php echo $statusClass; ?>">
                          <?php echo htmlspecialchars($status); ?> </span> </p>
                    </div>
                  </div>

                  <div class="col-md-7 columns">
                    <div class="info-item">
                      <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($fullName); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Contact No:</strong> <?php echo htmlspecialchars($contactNo); ?></p>
                    </div>

                    <div class="info-item-email">
                      <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    </div>

                    <div class="info-item">
                      <p><strong>Price: ₱ <?php echo number_format((float)$price, 2); ?></strong></p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="transaction-info-footer">
                <!-- <button class="cancel-btn" data-bs-toggle="modal" data-bs-target="#cancelTransactionModal">
                  Cancel Transaction
                </button> -->

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?= $transactionNumber ?>"
                data-transact-no="<?= $transactionNumber ?>" data-account-id="<?= $accountId ?>">Add Payment</button>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal" 
                  data-transaction-id="<?= $transactionNumber ?>">Add Request</button>

                  <?php
                    // Run the query to get guest count and pax
                    $query2 = "SELECT COALESCE(COUNT(g.transactNo), 0) AS guest_count, 
                                      b.pax AS pax 
                                    FROM booking b
                                    LEFT JOIN guest g ON g.transactNo = b.transactNo 
                                    WHERE b.transactNo = '$transactionNumber'";

                    $query3 = "SELECT COALESCE(COUNT(v.transactNo), 0) AS visa_count, 
                                      b.pax AS pax 
                                    FROM booking b
                                    LEFT JOIN visarequirements v ON v.transactNo = b.transactNo 
                                    WHERE b.transactNo = '$transactionNumber'";

                    $result2 = $conn->query($query2);
                    $result3 = $conn->query($query3);

                    // Check if the query returned results
                    if ($result2 && $result2->num_rows > 0) 
                    {
                      // Fetch the result
                      $row2 = $result2->fetch_assoc();
                      $guest_count = $row2['guest_count'];
                      $pax2 = $row2['pax'];
                    }

                    if ($result3 && $result3->num_rows > 0) 
                    {
                      // Fetch the result
                      $row3 = $result3->fetch_assoc();
                      $visa_count = $row3['visa_count'];
                      $pax3 = $row3['pax'];
                    }

                    // Determine whether to disable the button
                    $disable_button = ($guest_count >= $pax2) ? 'disabled' : ''; // Disable if guest_count >= pax
                    $disable_button2 = ($visa_count >= $pax3) ? 'disabled' : ''; // Disable if guest_count >= pax
                  ?>

                  <!-- Add Guest Button -->
                  <button type="button" class="btn btn-primary" <?php echo $disable_button; ?>
                    onclick="if (!this.disabled) { window.location.href = 'agent-addGuest.php'; }">
                    Add Guest Information
                  </button>

                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visaModal">
                    Attach Visa Requirements
                  </button>
              </div>
            </div>

            <div class="table-wrapper">
              <div class="transaction-header">
                <h5 class="">Guest Information: </h5>
              </div>

              <div class="transaction-info-body">

              </div>

              <div class="transaction-info-footer">

              </div>
            </div>
            
          </div>

          
          <div class="pills-tab-container">
            <ul class="nav nav-pills" id="pills-tab" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#pills-home" type="button" role="tab" aria-controls="pills-home" aria-selected="true">Guest Information</button>
              </li>

              <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-visa-tab" data-bs-toggle="pill" data-bs-target="#pills-visa" type="button" role="tab" aria-controls="pills-visa" aria-selected="false">Visa Requirements</button>
              </li>

              <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-profile" type="button" role="tab" aria-controls="pills-profile" aria-selected="false">Request History</button>
              </li>

              <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-contact-tab" data-bs-toggle="pill" data-bs-target="#pills-contact" type="button" role="tab" aria-controls="pills-contact" aria-selected="false">Payment History</button>
              </li>
            </ul>
          </div>

          <div class="transaction-body">
            <div class="body-tab-container">
              <div class="tab-content" id="pills-tabContent">
                <!-- Guest Table -->
                <?php include 'agent-guestTable.php'; ?>
                <?php include 'agent-showVisa.php'; ?>
                <?php include 'agent-requestTable.php'; ?>
                <?php include 'agent-paymentTable.php'; ?>
              </div>
            </div>
          </div>

        </div>
      </div>
      
  </div>


<!-- Modal Structure -->
<div class="modal fade" id="cancelTransactionModal" tabindex="-1" aria-labelledby="cancelTransactionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cancelTransactionModalLabel">Confirm Cancellation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="../Agent Section/functions/agent-cancelTransact-code.php" method="POST">
        <div class="modal-body">
          <p class="mb-3">
            Are you sure you want to cancel this transaction? This action cannot be undone.
          </p>

          <!-- Hidden Input for Transaction Number -->
          <input type="hidden" name="updateTransactNo" value="<?php echo htmlspecialchars($transactNum); ?>">

          <!-- Reason for Cancellation -->
          <div class="mb-3">
            <label for="cancellationReason" class="form-label">
              Reason for Cancellation <span class="text-danger fw-bold">*</span>
            </label>
            <input id="cancellationReason" name="reason" class="form-control" placeholder="Enter the reason for cancellation" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="cancelTransact" class="btn btn-danger">Confirm Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

  


  <?php require "../Agent Section/includes/scripts.php"; ?>

  <script>
    function toggleSubMenu(submenuId) {
      const submenu = document.getElementById(submenuId);
      const sectionTitle = submenu.previousElementSibling;
      const chevron = sectionTitle.querySelector('.chevron-icon');

      // Check if the submenu is already open
      const isOpen = submenu.classList.contains('open');

      // If it's open, we need to close it, and reset the chevron
      if (isOpen) {
        submenu.classList.remove('open');
        chevron.style.transform = 'rotate(0deg)';
      } else {
        // First, close all open submenus and reset all chevrons
        const allSubmenus = document.querySelectorAll('.submenu');
        const allChevrons = document.querySelectorAll('.chevron-icon');

        allSubmenus.forEach(sub => {
          sub.classList.remove('open');
        });

        allChevrons.forEach(chev => {
          chev.style.transform = 'rotate(0deg)';
        });

        // Now, open the current submenu and rotate its chevron
        submenu.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
      }
    }
  </script>

</body>

</html>