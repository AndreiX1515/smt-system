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
  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction .css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">

  <!-- Include Flatpickr -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</head>

<body>

  <?php include "../Agent Section/includes/sidebar.php"; ?>

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
            <h5 class="header-title">Request</h5>
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
                    <input type="text" id="FlightStartDate" class="filter-input" placeholder="Flight Date" readonly>

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

        <div class="navpills-container">

          <div class="filter-tabs" id="booking-filter-tabs">

            <!-- All Button -->
            <button class="filter-btn active" data-filter="">
              All
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM booking;";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

            <!-- Pending Button -->
            <button class="filter-btn" data-filter="Pending">Pending
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM booking 
                      WHERE status = 'Pending'";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

            <!-- Reserved Button -->
            <button class="filter-btn" data-filter="Reserved">Reserved
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM booking 
                      WHERE status = 'Reserved'";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

            <!-- Confirmed Button -->
            <button class="filter-btn" data-filter="Confirmed">Confirmed
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM booking 
                      WHERE status = 'Confirmed'";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

            <!-- Cancelled Button -->
            <button class="filter-btn" data-filter="Cancelled">Cancelled
              <span class="badge-status-tab">
                <h6>
                  <?php
                  $sql = "SELECT COUNT(*) AS totalBookings FROM booking 
                      WHERE status = 'Cancelled'";
                  $result = mysqli_query($conn, $sql);
                  echo ($result) ? mysqli_fetch_assoc($result)['totalBookings'] : 0;
                  ?>
                </h6>
              </span>
            </button>

          </div>

        </div>

        <div class="table-content-body">

          <div class="table-container">
            <table id="product-table" class="table product-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Contact Person Info</th>
                  <th>Contact Details</th>
                  <th>Branch Name</th>
                  <th>Flight Date</th>
                  <th>Total Pax</th>
                  <th>Package Price</th>
                  <th>Total Req. Cost</th>
                  <th>Amt. Paid Balance</th>
                  <th>Booking Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if ($agentRole != 'Head Agent') {
                  $sql1 = "SELECT b.transactNo AS `T.N`, p.packageName AS `PACKAGE`, b.bookingDate, 
                              b.bookingType as bookingType, DATE_FORMAT(f.flightDepartureDate, '%m-%d-%Y') AS `FLIGHT DATE`, b.pax AS `TOTAL PAX`, 
                              CONCAT(b.lName, ', ', b.fName, ' ', CASE WHEN b.mName = 'N/A' THEN '' 
                              ELSE CONCAT(SUBSTRING(b.mName, 1, 1), '.') END, ' ', CASE WHEN b.suffix = 'N/A' THEN '' 
                              ELSE b.suffix END) AS `CONTACT NAME`, br.branchName as branchName,
                              b.email AS `CONTACT EMAIL`, CONCAT(b.countryCode, ' ', b.contactNo) AS `CONTACT PHONE`, b.status AS `STATUS`, 
                              COALESCE(SUM(r.requestCost), 0) AS TotalRequestAmount, b.totalPrice AS PackagePrice, 
                              COALESCE(SUM(pa.amount), 0) AS TotalAmountPaid
                            FROM booking b
                            LEFT JOIN flight f ON b.flightId = f.flightId
                            LEFT JOIN package p ON b.packageId = p.packageId
                            LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                            JOIN branch br ON b.agentCode = br.branchAgentCode
                            LEFT JOIN payment pa ON pa.transactNo = b.transactNo AND pa.paymentStatus = 'Approved'
                            LEFT JOIN request r ON r.transactNo = b.transactNo AND r.requestStatus = 'Confirmed'
                            WHERE b.accountId = $accountId
                            GROUP BY b.transactNo
                            ORDER BY `FLIGHT DATE`";

                  $res1 = $conn->query($sql1);

                  if ($res1->num_rows > 0) {
                    while ($row = $res1->fetch_assoc()) {
                      $transactNo = $row['T.N'];
                      $pax = $row['TOTAL PAX'];

                      $status = isset($row['STATUS']) ? $row['STATUS'] : 'Unknown';
                      $statusClass = '';

                      switch ($status) {
                        case 'Confirmed':
                          $statusClass = 'bg-success text-white'; // Green background, white text
                          break;
                        case 'Cancelled':
                          $statusClass = 'bg-danger text-white'; // Red background, white text
                          break;
                        case 'Pending':
                          $statusClass = 'bg-warning text-dark';
                          break;
                        default:
                          $statusClass = 'bg-secondary text-white';
                      }

                      $packagePrice = $row['PackagePrice'] ?? 0;
                      $formattedPackagePrice = number_format($packagePrice, 2);
                      $requestTotal = $row['TotalRequestAmount'] ?? 0;
                      $formattedRequestTotal = number_format($requestTotal, 2);
                      $amountPaid = $row['TotalAmountPaid'] ?? 0;
                      $formattedAmountPaid = number_format($amountPaid, 2);
                      $balance = max(($packagePrice + $requestTotal) - $amountPaid, 0);
                      $formattedBalance = number_format($balance, 2);

                      $formattedBookingDate = date('Y.m.d', strtotime($row['bookingDate']));

                      // Prevent negative balances
                      // Booking Date
                      // <td>{$row['TRANSACTION DATE']}</td>
                
                      echo "<tr data-url='agent-showGuest.php?id=" . htmlspecialchars($transactNo) . "'>
                                <td>{$transactNo}</td>
                                <td>{$row['CONTACT NAME']}</td>
                                <td> 
                                  <div class='d-flex flex-column'>
                                    <span><strong>Email: </strong>" . $row['CONTACT EMAIL'] . " </span>
                                    <span><strong>Contact Number: </strong> " . $row['CONTACT PHONE'] . "</span>
                                  </div>
                                </td>
                                <td>{$row['branchName']}</td>
                                <td>{$row['FLIGHT DATE']}</td>
                                <td style='text-align: center; font-weight: bold;'>
                                  {$row['TOTAL PAX']}
                                </td>
                                <td>₱ {$formattedPackagePrice}</td>
                                <td>₱ {$formattedRequestTotal}</td>
                                <td>
                                  <div class='d-flex flex-column'>
                                    <span><strong>Amount Paid: </strong> ₱ " . $formattedAmountPaid . " </span>
                                    <span><strong>Balance: </strong> ₱ " . $formattedBalance . "</span>
                                  </div>
                                </td>
                                <td>{$formattedBookingDate}</td>
                                <td>
                                  <span class='badge p-2 rounded-pill {$statusClass}'>
                                    {$status}
                                  </span>
                                </td>
                            </tr>";
                    }
                  }
                } else {
                  $sql1 = "SELECT b.transactNo AS `T.N`, p.packageName AS `PACKAGE`, b.bookingDate, 
                              b.bookingType as bookingType, DATE_FORMAT(f.flightDepartureDate, '%m-%d-%Y') AS `FLIGHT DATE`, b.pax AS `TOTAL PAX`, 
                              CONCAT(b.lName, ', ', b.fName, ' ', CASE WHEN b.mName = 'N/A' THEN '' 
                              ELSE CONCAT(SUBSTRING(b.mName, 1, 1), '.') END, ' ', CASE WHEN b.suffix = 'N/A' THEN '' 
                              ELSE b.suffix END) AS `CONTACT NAME`, br.branchName as branchName,
                              b.email AS `CONTACT EMAIL`, CONCAT(b.countryCode, ' ', b.contactNo) AS `CONTACT PHONE`, b.status AS `STATUS`, 
                              COALESCE(SUM(r.requestCost), 0) AS TotalRequestAmount, b.totalPrice AS PackagePrice, 
                              COALESCE(SUM(pa.amount), 0) AS TotalAmountPaid
                            FROM booking b
                            LEFT JOIN flight f ON b.flightId = f.flightId
                            LEFT JOIN package p ON b.packageId = p.packageId
                            LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                            JOIN branch br ON b.agentCode = br.branchAgentCode
                            LEFT JOIN payment pa ON pa.transactNo = b.transactNo AND pa.paymentStatus = 'Approved'
                            LEFT JOIN request r ON r.transactNo = b.transactNo AND r.requestStatus = 'Confirmed'
                            WHERE b.agentCode = '$agentCode'
                            GROUP BY b.transactNo
                            ORDER BY `FLIGHT DATE`";

                  $res1 = $conn->query($sql1);

                  if ($res1->num_rows > 0) {
                    while ($row = $res1->fetch_assoc()) {
                      $transactNo = $row['T.N'];
                      $pax = $row['TOTAL PAX'];

                      $status = isset($row['STATUS']) ? $row['STATUS'] : 'Unknown';
                      $statusClass = '';

                      switch ($status) {
                        case 'Confirmed':
                          $statusClass = 'bg-success text-white'; // Green background, white text
                          break;
                        case 'Cancelled':
                          $statusClass = 'bg-danger text-white'; // Red background, white text
                          break;
                        case 'Pending':
                          $statusClass = 'bg-warning text-dark';
                          break;
                        default:
                          $statusClass = 'bg-secondary text-white';
                      }

                      $packagePrice = $row['PackagePrice'] ?? 0;
                      $formattedPackagePrice = number_format($packagePrice, 2);
                      $requestTotal = $row['TotalRequestAmount'] ?? 0;
                      $formattedRequestTotal = number_format($requestTotal, 2);
                      $amountPaid = $row['TotalAmountPaid'] ?? 0;
                      $formattedAmountPaid = number_format($amountPaid, 2);
                      $balance = max(($packagePrice + $requestTotal) - $amountPaid, 0);
                      $formattedBalance = number_format($balance, 2);

                      $formattedBookingDate = date('Y.m.d', strtotime($row['bookingDate']));

                      // Prevent negative balances
                      // Booking Date
                      // <td>{$row['TRANSACTION DATE']}</td>
                
                      echo "<tr data-url='agent-showGuest.php?id=" . htmlspecialchars($transactNo) . "'>
                                <td>{$transactNo}</td>
                                <td>{$row['CONTACT NAME']}</td>
                                <td> 
                                  <div class='d-flex flex-column'>
                                    <span><strong>Email: </strong>" . $row['CONTACT EMAIL'] . " </span>
                                    <span><strong>Contact Number: </strong> " . $row['CONTACT PHONE'] . "</span>
                                  </div>
                                </td>
                                <td>{$row['branchName']}</td>
                                <td>{$row['FLIGHT DATE']}</td>
                                <td style='text-align: center; font-weight: bold;'>
                                  {$row['TOTAL PAX']}
                                </td>
                                <td>₱ {$formattedPackagePrice}</td>
                                <td>₱ {$formattedRequestTotal}</td>
                                <td>
                                  <div class='d-flex flex-column'>
                                    <span><strong>Amount Paid: </strong> ₱ " . $formattedAmountPaid . " </span>
                                    <span><strong>Balance: </strong> ₱ " . $formattedBalance . "</span>
                                  </div>
                                </td>
                                <td>{$formattedBookingDate}</td>
                                <td>
                                  <span class='badge p-2 rounded-pill {$statusClass}'>
                                    {$status}
                                  </span>
                                </td>
                            </tr>";
                    }
                  }
                }

                if ($res1) {
                  $res1->free();
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


  <!-- Modal for Update Booking -->
  <div class="modal fade" id="updateBookingModal" tabindex="-1" aria-labelledby="updateBookingModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="updateBookingModalLabel">Update Booking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Form for updating booking details -->
        <form action="../Agent Section/functions/agent-transactionUpdateBooking-code.php" id="updateBookingForm"
          method="POST">
          <div class="modal-body">
            <div class="mb-4 d-flex align-items-center w-100">
              <h6 class="mb-0">Transaction ID:</h6>
              <span id="transactionId" class="ms-2"></span>
            </div>

            <input type="hidden" name="transaction_number" value="">

            <h6 class="fw-bold">Personal Information:</h6>

            <div class="row mt-2">
              <div class="col-md-3 mb-3">
                <label for="contactName" class="form-label">First Name</label>
                <input type="text" class="form-control" id="fName" name="fName" required>
              </div>
              <div class="col-md-3 mb-3">
                <label for="contactLName" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="lName" name="lName">
              </div>
              <div class="col-md-3 mb-3">
                <label for="contactMName" class="form-label">Middle Name</label>
                <input type="text" class="form-control" id="mName" name="mName">
              </div>
              <div class="col-md-3 mb-3">
                <label for="contactSuffix" class="form-label">Suffix</label>
                <select class="form-control" id="suffix" name="suffix">
                  <option value="Jr.">Jr.</option>
                  <option value="Sr.">Sr.</option>
                  <option value="III">III</option>
                  <option value="IV">IV</option>
                  <option value="V">V</option>
                  <option value="N/A">N/A</option>
                  <!-- Add more options as needed -->
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-2 mb-3">
                <label for="countryCode" class="form-label">Country Code</label>
                <!-- <input type="text" class="form-control" id="countryCode" name="countryCode"> -->
                <select name="countryCode" id="countryCode" class="form-select" required>
                  <option disabled selected>Country Code</option>
                  <option value="+93">Afghanistan (+93)</option>
                  <option value="+355">Albania (+355)</option>
                  <option value="+213">Algeria (+213)</option>
                  <option value="+376">Andorra (+376)</option>
                  <option value="+244">Angola (+244)</option>
                  <option value="+1-268">Antigua and Barbuda (+1-268)</option>
                  <option value="+54">Argentina (+54)</option>
                  <option value="+374">Armenia (+374)</option>
                  <option value="+61">Australia (+61)</option>
                  <option value="+43">Austria (+43)</option>
                  <option value="+994">Azerbaijan (+994)</option>
                  <option value="+1-242">Bahamas (+1-242)</option>
                  <option value="+973">Bahrain (+973)</option>
                  <option value="+880">Bangladesh (+880)</option>
                  <option value="+1-246">Barbados (+1-246)</option>
                  <option value="+375">Belarus (+375)</option>
                  <option value="+32">Belgium (+32)</option>
                  <option value="+501">Belize (+501)</option>
                  <option value="+229">Benin (+229)</option>
                  <option value="+975">Bhutan (+975)</option>
                  <option value="+591">Bolivia (+591)</option>
                  <option value="+387">Bosnia and Herzegovina (+387)</option>
                  <option value="+267">Botswana (+267)</option>
                  <option value="+55">Brazil (+55)</option>
                  <option value="+673">Brunei (+673)</option>
                  <option value="+359">Bulgaria (+359)</option>
                  <option value="+226">Burkina Faso (+226)</option>
                  <option value="+257">Burundi (+257)</option>
                  <option value="+238">Cabo Verde (+238)</option>
                  <option value="+855">Cambodia (+855)</option>
                  <option value="+237">Cameroon (+237)</option>
                  <option value="+1">Canada (+1)</option>
                  <option value="+236">Central African Republic (+236)</option>
                  <option value="+235">Chad (+235)</option>
                  <option value="+56">Chile (+56)</option>
                  <option value="+86">China (+86)</option>
                  <option value="+57">Colombia (+57)</option>
                  <option value="+269">Comoros (+269)</option>
                  <option value="+243">Congo, Democratic Republic of the (+243)</option>
                  <option value="+242">Congo, Republic of the (+242)</option>
                  <option value="+506">Costa Rica (+506)</option>
                  <option value="+385">Croatia (+385)</option>
                  <option value="+53">Cuba (+53)</option>
                  <option value="+357">Cyprus (+357)</option>
                  <option value="+420">Czech Republic (+420)</option>
                  <option value="+45">🇩🇰 Denmark (+45)</option>
                  <option value="+253">🇩🇯 Djibouti (+253)</option>
                  <option value="+1-767">🇩🇲 Dominica (+1-767)</option>
                  <option value="+1-809">🇩🇴 Dominican Republic (+1-809)</option>
                  <option value="+593">Ecuador (+593)</option>
                  <option value="+20">Egypt (+20)</option>
                  <option value="+503">El Salvador (+503)</option>
                  <option value="+240">Equatorial Guinea (+240)</option>
                  <option value="+291">Eritrea (+291)</option>
                  <option value="+372">Estonia (+372)</option>
                  <option value="+268">Eswatini (+268)</option>
                  <option value="+251">Ethiopia (+251)</option>
                  <option value="+679">Fiji (+679)</option>
                  <option value="+358">Finland (+358)</option>
                  <option value="+33">France (+33)</option>
                  <option value="+241">Gabon (+241)</option>
                  <option value="+220">Gambia (+220)</option>
                  <option value="+995">Georgia (+995)</option>
                  <option value="+49">Germany (+49)</option>
                  <option value="+233">Ghana (+233)</option>
                  <option value="+30">Greece (+30)</option>
                  <option value="+1-473">Grenada (+1-473)</option>
                  <option value="+502">Guatemala (+502)</option>
                  <option value="+224">Guinea (+224)</option>
                  <option value="+245">Guinea-Bissau (+245)</option>
                  <option value="+592">Guyana (+592)</option>
                  <option value="+509">Haiti (+509)</option>
                  <option value="+504">Honduras (+504)</option>
                  <option value="+36">Hungary (+36)</option>
                  <option value="+354">Iceland (+354)</option>
                  <option value="+91">India (+91)</option>
                  <option value="+62">Indonesia (+62)</option>
                  <option value="+98">Iran (+98)</option>
                  <option value="+964">Iraq (+964)</option>
                  <option value="+353">Ireland (+353)</option>
                  <option value="+972">Israel (+972)</option>
                  <option value="+39">Italy (+39)</option>
                  <option value="+225">Ivory Coast (+225)</option>
                  <option value="+81">Japan (+81)</option>
                  <option value="+962">Jordan (+962)</option>
                  <option value="+7">Kazakhstan (+7)</option>
                  <option value="+254">Kenya (+254)</option>
                  <option value="+686">Kiribati (+686)</option>
                  <option value="+965">Kuwait (+965)</option>
                  <option value="+996">Kyrgyzstan (+996)</option>
                  <option value="+856">Laos (+856)</option>
                  <option value="+371">Latvia (+371)</option>
                  <option value="+961">Lebanon (+961)</option>
                  <option value="+266">Lesotho (+266)</option>
                  <option value="+231">Liberia (+231)</option>
                  <option value="+218">Libya (+218)</option>
                  <option value="+423">Liechtenstein (+423)</option>
                  <option value="+370">Lithuania (+370)</option>
                  <option value="+352">Luxembourg (+352)</option>
                  <option value="+261">Madagascar (+261)</option>
                  <option value="+265">Malawi (+265)</option>
                  <option value="+60">Malaysia (+60)</option>
                  <option value="+960">Maldives (+960)</option>
                  <option value="+223">Mali (+223)</option>
                  <option value="+356">Malta (+356)</option>
                  <option value="+692">Marshall Islands (+692)</option>
                  <option value="+596">Martinique (+596)</option>
                  <option value="+222">Morocco (+222)</option>
                  <option value="+258">Mozambique (+258)</option>
                  <option value="+95">Myanmar (+95)</option>
                  <option value="+264">Namibia (+264)</option>
                  <option value="+674">Nauru (+674)</option>
                  <option value="+977">Nepal (+977)</option>
                  <option value="+31">Netherlands (+31)</option>
                  <option value="+599">Netherlands Antilles (+599)</option>
                  <option value="+64">New Zealand (+64)</option>
                  <option value="+505">Nicaragua (+505)</option>
                  <option value="+227">Niger (+227)</option>
                  <option value="+234">Nigeria (+234)</option>
                  <option value="+683">Niue (+683)</option>
                  <option value="+672">Norfolk Island (+672)</option>
                  <option value="+850">North Korea (+850)</option>
                  <option value="+1-670">Northern Mariana Islands (+1-670)</option>
                  <option value="+47">Norway (+47)</option>
                  <option value="+968">Oman (+968)</option>
                  <option value="+92">Pakistan (+92)</option>
                  <option value="+680">Palau (+680)</option>
                  <option value="+507">Panama (+507)</option>
                  <option value="+675">Papua New Guinea (+675)</option>
                  <option value="+595">Paraguay (+595)</option>
                  <option value="+51">Peru (+51)</option>
                  <option value="+63">Philippines (+63)</option>
                  <option value="+48">Poland (+48)</option>
                  <option value="+351">Portugal (+351)</option>
                  <option value="+974">Qatar (+974)</option>
                  <option value="+40">Romania (+40)</option>
                  <option value="+7">Russia (+7)</option>
                  <option value="+250">Rwanda (+250)</option>
                  <option value="+508">Saint Barthélemy (+508)</option>
                  <option value="+1-869">Saint Kitts and Nevis (+1-869)</option>
                  <option value="+1-758">Saint Lucia (+1-758)</option>
                  <option value="+590">Saint Martin (+590)</option>
                  <option value="+1-345">Cayman Islands (+1-345)</option>
                  <option value="+239">São Tomé and Príncipe (+239)</option>
                  <option value="+966">Saudi Arabia (+966)</option>
                  <option value="+221">Senegal (+221)</option>
                  <option value="+381">Serbia (+381)</option>
                  <option value="+248">Seychelles (+248)</option>
                  <option value="+232">Sierra Leone (+232)</option>
                  <option value="+65">Singapore (+65)</option>
                  <option value="+421">Slovakia (+421)</option>
                  <option value="+386">Slovenia (+386)</option>
                  <option value="+677">Solomon Islands (+677)</option>
                  <option value="+252">Somalia (+252)</option>
                  <option value="+27">South Africa (+27)</option>
                  <option value="+82">South Korea (+82)</option>
                  <option value="+211">South Sudan (+211)</option>
                  <option value="+34">Spain (+34)</option>
                  <option value="+94">Sri Lanka (+94)</option>
                  <option value="+249">Sudan (+249)</option>
                  <option value="+597">Suriname (+597)</option>
                  <option value="+268">Swaziland (+268)</option>
                  <option value="+46">Sweden (+46)</option>
                  <option value="+41">Switzerland (+41)</option>
                  <option value="+963">Syria (+963)</option>
                  <option value="+886">Taiwan (+886)</option>
                  <option value="+992">Tajikistan (+992)</option>
                  <option value="+255">Tanzania (+255)</option>
                  <option value="+66">Thailand (+66)</option>
                  <option value="+670">Timor-Leste (+670)</option>
                  <option value="+228">Togo (+228)</option>
                  <option value="+676">Tonga (+676)</option>
                  <option value="+1-868">Trinidad and Tobago (+1-868)</option>
                  <option value="+216">Tunisia (+216)</option>
                  <option value="+90">Turkey (+90)</option>
                  <option value="+993">Turkmenistan (+993)</option>
                  <option value="+1-649">Turks and Caicos Islands (+1-649)</option>
                  <option value="+688">Vanuatu (+688)</option>
                  <option value="+39">Vatican City (+39)</option>
                  <option value="+58">Venezuela (+58)</option>
                  <option value="+84">Vietnam (+84)</option>
                  <option value="+681">Wallis and Futuna (+681)</option>
                  <option value="+967">Yemen (+967)</option>
                  <option value="+260">Zambia (+260)</option>
                  <option value="+263">Zimbabwe (+263)</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="contactPhone" class="form-label">Contact Phone</label>
                <input type="tel" class="form-control" id="contactNo" name="contactNo" placeholder="Contact Number"
                  required>
                <!-- <input type="text" class="form-control" id="contactPhone" name="contactNo" required> -->
              </div>
              <div class="col-md-6 mb-3">
                <label for="contactEmail" class="form-label">Contact Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
              </div>
            </div>

          </div>

          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" name="updateBooking">Update</button>
          </div>

        </form>
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

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      // Get the status from the URL
      let statusTab = "<?php echo isset($_GET['status']) ? $_GET['status'] : ''; ?>";
      console.log("Status from URL:", statusTab); // Debugging

      // Find all filter tabs
      let tabs = document.querySelectorAll("#booking-filter-tabs li");

      // Remove 'active' class from all tabs
      tabs.forEach(tab => tab.classList.remove("active"));

      // Find the tab that matches the status
      let matchedTab = [...tabs].find(tab => tab.getAttribute("data-filter") === statusTab);

      if (matchedTab) {
        matchedTab.classList.add("active"); // Highlight the correct tab
        console.log("Activating tab:", matchedTab.innerText);

        setTimeout(() => {
          matchedTab.dispatchEvent(new Event("click", {
            bubbles: true
          }));
        }, 3);

      } else {
        // Default to "All" if no match found
        let defaultTab = document.querySelector("#booking-filter-tabs li[data-filter='']");
        if (defaultTab) {
          defaultTab.classList.add("active");
          console.log("Activating default tab: All");

          setTimeout(() => {
            defaultTab.dispatchEvent(new Event("click", {
              bubbles: true
            }));
          }, 100);
        }
      }
    });
  </script>

  <!-- Status Sorting tabs -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const tabs = document.querySelectorAll("#booking-filter-tabs li");

      tabs.forEach(tab => {
        tab.addEventListener("click", function () {
          // Remove active class from all tabs
          tabs.forEach(t => t.classList.remove("active"));
          // Add active class to the clicked tab
          this.classList.add("active");

          let filterValue = this.getAttribute("data-filter");

          // Apply DataTables filtering (assuming your table uses DataTables)
          if ($.fn.DataTable.isDataTable("#product-table")) {
            $('#product-table').DataTable().column(9).search(filterValue || '', true, false).draw();
          }
        });
      });
    });
  </script>

  <!-- DataTables #product-table -->
  <script>
    $(document).ready(function () {
      $('#product-table').DataTable({
        dom: 'rtip',
        language: {
          emptyTable: "No Transaction Records Available"
        },
        order: [[4, 'asc']], // Sorting by flight date
        scrollX: true, // Enable horizontal scroll if needed
        scrollY: '66.1vh',
        paging: true,
        pageLength: 11,
        autoWidth: false,
        columnDefs: [
          {
            targets: [1, 2, 3, 5, 6],
            orderable: false
          }
        ]
      });

      // Search Functionality
      $('#search').on('keyup', function () {
        table.search(this.value).draw();
      });

      // Update Pagination
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

      updatePagination(); // Initialize pagination

      // Package Filter
      $('#packages').on('change', function () {
        const selectedPackage = $(this).val();
        table.column(3).search(selectedPackage || '').draw();
      });

      $("#FlightStartDate").datepicker({
        dateFormat: "mm-dd-yy",
        showAnim: "fadeIn",
        changeMonth: true,
        changeYear: true,
        yearRange: "1900:2100",
        onSelect: function (dateText) {
          console.log("FlightStartDate Selected:", dateText);
          table.column(4).search(dateText || '').draw();
        }
      });

      // Flight Date Filter
      $('#FlightStartDate').on('change', function () {
        const selectedFlightDate = $(this).val();
        console.log("Flight Date Filter:", selectedFlightDate);
        table.column(4).search(selectedFlightDate || '').draw();
      });

      // Clear All Filters
      $('#clearSorting').on('click', function () {
        $('#search').val('');
        table.search('').draw();

        $('#packages').val('All').change();

        $('#FlightStartDate').datepicker("setDate", null); // Properly clear date
        table.column(4).search('').draw(); // Explicitly reset column filter

        updatePagination(); // Ensure pagination updates after clearing filters
      });
    });
  </script>

  <!-- Row Click Selection JS -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll("tr[data-url]").forEach(function (row) {
        row.addEventListener("click", function () {
          const transactionNumber = row.getAttribute("data-url").split('=')[1]; // Extract transaction number from the URL

          console.log("Transaction Number: ", transactionNumber); // Debugging line

          // Use AJAX to send the transaction number to the server
          $.ajax({
            url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file to handle the session setting
            type: 'POST',
            data: {
              transaction_number: transactionNumber
            },
            success: function (response) {
              console.log("Response: ", response); // Debugging line

              // Redirect to the next page after successfully setting the session
              window.location.href = row.getAttribute("data-url"); // Use the original URL stored in data-url attribute
            },
            error: function (xhr, status, error) {
              console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
            }
          });
        });
      });
    });
  </script>

  <script>
    function addGuestInfo(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line (To Remove in Prod)
          window.location.href = '../Agent Section/agent-addGuest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showGuestInfo(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line
      // Use AJAX to send the transaction number to the server
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showGuest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showRequestHistory(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)
      // Use AJAX to send the transaction number to the server
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showRequest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showPaymentHistory(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)

      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showPayment.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }
  </script>

  <script>
    function addGuestInfo(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line (To Remove in Prod)
          window.location.href = '../Agent Section/agent-addGuest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showGuestInfo(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line
      // Use AJAX to send the transaction number to the server
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showGuest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showRequestHistory(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)
      // Use AJAX to send the transaction number to the server
      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showRequest.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }

    function showPaymentHistory(transactionNumber) {
      console.log("Transaction Number: ", transactionNumber); // Debug line (To Remove in Prod)

      $.ajax({
        url: '../Agent Section/functions/fetchTransactNo.php', // The PHP file that will handle the session setting
        type: 'POST',
        data: {
          transaction_number: transactionNumber
        },
        success: function (response) {
          console.log("Response: ", response); // Debug line
          // Redirect to the next page after setting the session
          window.location.href = '../Agent Section/agent-showPayment.php'; // Redirect to your next page
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error: " + status + " " + error); // Enhanced error logging
        }
      });
    }
  </script>

  <?php require "../Agent Section/includes/scripts.php"; ?>







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