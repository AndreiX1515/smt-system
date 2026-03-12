<?php
session_start();
require "../conn.php";

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking</title>

  <?php include "../Agent Section/includes/head.php"; ?>


  <link rel="stylesheet" href="../Agent Section/assets/css/agent-addBooking.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

  <div class="body-container">
    <?php include "../Agent Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <div class="navbar">
        <h5 class="title-page" id="page-title">Booking</h5>
      </div>


      <div class="main-content">
        <?php
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
        ?>

        <?php
        if (isset($_SESSION['status'])):
        ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong></strong> <?= $_SESSION['status']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php
          unset($_SESSION['status']);
        endif;
        ?>

        <?php
        // Set flightId to session value by default, if available
        $flightId = $_SESSION['agent_flightId'] ?? null;

        // If GET is set, override flightId and update session
        if (isset($_GET['flightid'])) {
            $flightId = $_GET['flightid'];
            $_SESSION['agent_flightId'] = $flightId;
        }

        // Proceed only if flightId is available
        if ($flightId) {
            // SQL query to join flight and package tables
            $sql1 = "SELECT flight.*, package.packageName, package.packagePrice
                    FROM flight
                    JOIN package ON flight.packageId = package.packageId
                    WHERE flight.flightId = ?";

            // Prepare the statement
            if ($stmt = $conn->prepare($sql1)) {
                // Bind the flightId as an integer parameter
                $stmt->bind_param("i", $flightId);

                // Execute the statement
                if ($stmt->execute()) {
                    $result = $stmt->get_result();

                    // Check if a row is returned
                    if ($result->num_rows > 0) {
                        // Fetch the data
                        while ($row = $result->fetch_assoc()) {
                            $packageId = $row['packageId'];
                            $packageName = $row['packageName'];
                            $packagePrice = $row['packagePrice'];
                            $origin = $row['origin'];
                            $year = date('Y', strtotime($row['flightDepartureDate']));
                            $month = date('F', strtotime($row['flightDepartureDate']));
                            $flightDepartureDate = $row['flightDepartureDate'];
                            $flightPrice = $row['flightPrice'];
                            $wholesalePrice = $row['wholesalePrice'];
                        }
                    } else {
                        echo "No flight found with that ID.";
                    }
                } else {
                    echo "Error executing query: " . $stmt->error;
                }
                // Close the statement
                $stmt->close();
            } else {
                echo "Error preparing statement: " . $conn->error;
            }
        }
        ?>


        <form action="../Agent Section/functions/agent-addBooking-code.php" method="POST">
          <div class="booking-wrapper">
            <div class="card">
              <div class="card-header">
                <h4 class="">Details</h4>
              </div>

              <div class="card-body">
                <div class="row">
                  <!-- Flight Date Dropdown -->
                  <div class="columns col-md-6">
                    <div class="form-group">
                      <label for="flightDate">Flight Date <span class="text-danger"> *</span></label>

                      <select class="form-select" id="flightDate" name="flightDate" required>
                        <option selected disabled>Select Flight Date</option>
                        <?php
                        // Query to fetch packageId and packageName
                        $sql1 = mysqli_query($conn, "SELECT flightId, flightDepartureDate, flightPrice, wholesalePrice 
                                          FROM flight WHERE packageId = $packageId AND 
                                          MONTHNAME(flightDepartureDate) = '$month' ORDER BY flightDepartureDate ASC");

                        // Loop through the result to create options
                        while ($res1 = mysqli_fetch_array($sql1)) {
                          // Check if this packageId is equal to the selected packageId (to mark it as selected)
                          $formattedRetailPrice = number_format($res1['flightPrice'], 2);
                          $formattedWholesalePrice = number_format($res1['wholesalePrice'], 2);

                          if ($agentType === 'Retailer') {
                            $selected = ($res1['flightDepartureDate'] == $flightDepartureDate) ? 'selected' : '';
                            echo "<option value='{$res1['flightId']}' {$selected}>
                                      " . date('M j, Y', strtotime($res1['flightDepartureDate'])) . " || Price: ₱ {$formattedRetailPrice}
                                    </option>";
                          } else if ($agentType === 'Wholeseller') {
                            $selected = ($res1['flightDepartureDate'] == $flightDepartureDate) ? 'selected' : '';
                            echo "<option value='{$res1['flightId']}' {$selected}>
                                      " . date('M j, Y', strtotime($res1['flightDepartureDate'])) . " || Price: ₱ {$formattedWholesalePrice}
                                    </option>";
                          }
                        }
                        ?>
                      </select>

                      <span id="flightDateError" class="text-danger"></span>
                      <!-- Error message for outbound flight -->
                    </div>
                  </div>

                  <!-- Total Pax Input -->
                  <div class="columns col-md-6">
                    <div class="form-group">
                      <div class="col-header">
                        <div>
                          <label for="totalPax">Total Pax <span class="text-danger"> *</span></label>
                        </div>
                      </div>

                      <input type="number" class="form-control" id="totalPax" name="totalPax" min="1" placeholder="Enter Total Pax" required>

                      <span id="totalPaxError" class="text-danger"></span>
                      <!-- Error message for Total Pax -->
                    </div>

                    <div class="pax-seats">
                      <div class="maxAvail">
                        <label id="maxSeats"></label>
                        <div class="separator"></div>
                        <label id="availSeats"></label>
                      </div>
                    </div>


                  </div>
                </div>

                <div class="row">
                  <div class="columns col-md-12 land-only">
                    <input type="checkbox" id="land" name="land" value="Land Only">
                    <label for="land"> Land Only</label><br>
                  </div>
                </div>

                <div class="row ">
                  <!-- Flight Details Input -->
                  <div class="columns col-md-12 flight-details-wrapper" id="flightDetailsContainer" style="display: none;">
                    <div class="form-group">
                      <label for="flightDetails">Flight Details for Package Only</label>

                      <textarea class="form-control" id="flightDetails" name="flightDetails" placeholder="Input Flight Details Here"></textarea>
                    </div>
                  </div>
                </div>

                <input type="text" id="agentCode" name="agentCode" value="<?php echo $_SESSION['agentCode']; ?>" placeholder="Agent Code Input">

                <input type="text" id="flightId" name="flightId" value="<?php echo $flightId; ?>" placeholder="Flight Id Input">

                <!-- Adjusted Fields -->
                <input type="text" id="packagePrice" name="packagePrice" value="<?php echo isset($packagePrice) ? $packagePrice : ''; ?>" placeholder="Package Price">

                <input type="text" name="flightPrice" id="flightPricee" placeholder="Flight Price"
                  value="<?php echo isset($agentType) ? ($agentType === 'Retailer' ? htmlspecialchars($flightPrice) : htmlspecialchars($wholesalePrice)) : ''; ?>">

                <input type="text" name="agentId" id="agentId" value="<?php echo $_SESSION['agentId']; ?>" placeholder="Agent Id">

                <input type="text" name="agentType" placeholder="Agent Type Input" value="<?php echo $_SESSION['agentType']; ?>">
                
                <input type="text" name="accId" id="accId" placeholder="Account Id Input" value="<?php echo $_SESSION['agent_accountId']; ?>">

                <!-- Adjusted Package Fields -->
                <input type="text" name="packageId" id="packageId" value="<?php echo isset($packageId) ? $packageId : ''; ?>" placeholder="Package Id Input">

                <input type="text" name="packageName" id="packageName" value="<?php echo isset($packageName) ? $packageName : ''; ?>" placeholder="Package Name Input">

                <input type="text" name="origin" id="origin" value="<?php echo isset($origin) ? $origin : ''; ?>" placeholder="Origin Input">

              </div>

              <div class="card-footer">
                <h5 style="display: none;"> Price: ₱ <span id="flightPrice">0.00</span> </h5>
              </div>
            </div>

            <div class="card contact-person-details">
              <div class="card-header">
                <h4 class="">Contact Person Details</h4>
              </div>

              <div class="card-body">
                <div class="row">
                  <!-- First Name Input -->
                  <div class="columns col-md-3">
                    <div class="form-group">
                      <label for="fName">First Name <span class="text-danger"> *</span></label>
                      <input type="text" name="fName" id="fName" class="form-control" placeholder="Enter First Name" required>
                      <span id="fNameError" class="text-danger"></span>
                      <!-- Error message for First Name -->
                    </div>
                  </div>

                  <!-- Last Name Input -->
                  <div class="columns col-md-3">
                    <div class="form-group">
                      <label for="lName">Last Name <span class="text-danger"> *</span> </label>
                      <input type="text" name="lName" id="lName" class="form-control" placeholder="Enter Last Name" required>
                      <span id="lNameError" class="text-danger"></span>
                      <!-- Error message for Last Name -->
                    </div>
                  </div>

                  <!-- Middle Name Input -->
                  <div class="columns col-md-3">
                    <div class="form-group">
                      <label for="mName">Middle Name <span class="text-danger mText">Type N/A if none</span></label>

                      <input type="text" name="mName" id="mName" class="form-control" placeholder="Enter Middle Name" required>

                      <span id="mNameError" class="text-danger"></span>
                      <!-- Error message for Middle Name -->
                    </div>
                  </div>

                  <!-- Suffix Dropdown -->
                  <div class="columns col-md-3">
                    <div class="form-group">
                      <label for="suffix">Suffix <span class="text-danger"> *</span></label>
                      <select class="form-select" name="suffix" id="suffix" required>
                        <option selected disabled>Select Suffix</option>
                        <option value="N/A">None</option>
                        <option value="Jr.">Jr.</option>
                        <option value="Sr.">Sr.</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                        <option value="V">V</option>
                      </select>
                      <span id="suffixError" class="text-danger"></span>
                      <!-- Error message for Suffix -->
                    </div>
                  </div>

                </div>

                <div class="row">
                  <!-- Contact No Input-->
                  <div class="columns col-md-4">
                    <div class="form-group">

                      <label for="contactNo" class="contactNo">Contact No. <span class="text-danger">*</span></label>

                      <div class="input-group">
                        <select name="countryCode" id="countryCode" class="form-select" required>
                          <option disabled>Country Code</option>
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
                          <option value="+63" selected>Philippines (+63)</option>
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

                        <input type="tel" class="form-control mt-2" id="contactNo" name="contactNo" placeholder="Contact Number" required>
                      </div>

                      <span id="contactNoError" class="text-danger"></span>
                      <!-- Error message for Contact No -->
                    </div>
                  </div>

                  <!-- Email Input -->
                  <div class="columns col-md-4 email-fields">
                    <div class="form-group">
                      <label for="email">Email <span class="text-danger">*</span></label>
                      <input type="email" name="email" id="email" class="form-control" placeholder="Enter Email Address" required>
                      <span id="emailError" class="text-danger"></span> <!-- Error message for Email -->
                    </div>
                  </div>

                </div>

              </div>
            </div>

            <!-- <strong id="errorMessage" class="text-danger"></strong> -->

            <div class="card price-wrapper">
              <div class="card-body">
                <h5 class="">Total Price: ₱ <span id="displayTotalPrice">0</span></h5>
                <button type="button" class="btn btn-primary" id="bookNowButton">Book Now</button>
              </div>

              <input type="hidden" id="totalPrice" name="totalPrice" placeholder="Total Price">
            </div>

            <!-- Booking Summary Modal -->
            <div class="modal fade" id="BookingSummaryModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered"> <!-- Added modal-lg for a wider modal -->
                <div class="modal-content position-relative">

                  <button type="button" class="btn-close close-outside p-4" data-bs-dismiss="modal" aria-label="Close"></button>

                  <div class="modal-body">
                    <div class="confirmation-container container">
                      <!-- Logo Section -->
                      <div class="row d-flex justify-content-center align-items-center text-center mb-3 mt-2">
                        <div class="col">
                          <img src="../Assets/Logos/SMART LOGO 2 (2).png" alt="Trip Image" class="img-fluid" style="max-width: 250px; max-height: 80px;">
                        </div>
                      </div>

                      <h5 class="text-left mb-4">BOOKING SUMMARY</h5>
                      <!-- Transaction and Contact Info -->
                      <div class="transaction-info row mb-3">
                        <div class="col-12">

                          <div class="d-flex justify-content-between mb-1">
                            <p class="mb-0"><strong>Contact Guest Name:</strong></p>
                            <p class="mb-0" id="contactPersonName">Sample Name</p>
                          </div>

                          <div class="d-flex justify-content-between mb-1">
                            <p class="mb-0"><strong>Contact Email:</strong></p>
                            <p class="mb-0" id="contactPersonEmail">Sample Email</p>
                          </div>
                        </div>
                      </div>
                      <hr>

                      <!-- Package Details -->
                      <div class="row hotel-details mb-3">
                        <div class="col-12">
                          <div class="d-flex justify-content-between mb-1">
                            <p class="mb-0"><strong>Package Name:</strong></p>
                            <p class="mb-0" id="selectedPackage">No Package Selected</p>
                          </div>

                          <div class="d-flex justify-content-between">
                            <p class="mb-0"><strong>No. of Guests:</strong></p>
                            <p class="mb-0" id="guestCount">1</p>
                          </div>
                        </div>
                      </div>
                      <hr>

                      <!-- Flight/Origin Details -->
                      <div class="row mb-3">
                        <div class="col-12">
                          <div class="d-flex justify-content-between mb-1">
                            <p class="mb-0"><strong>Origin:</strong></p>
                            <p class="mb-0" id="selectedOrigin">No Origin Selected</p>
                          </div>

                          <div class="d-flex justify-content-between">
                            <p class="mb-0"><strong>Flight Date:</strong></p>
                            <p class="mb-0" id="selectedDate">No Flight Date Selected</p>
                          </div>
                        </div>
                      </div>
                      <hr>

                      <!-- Proceed to Payment -->
                      <div class="row mt-4">
                        <div class="col d-flex justify-content-between">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary" name="bookNow">Proceed to Payment</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

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

  <script>
    $(document).ready(function() {
      // Fetch flight Related Details once changed
      $('#flightDate').on('change', function() {
        var flightId = $(this).val();
        var agentType = $(this).val();
        
        $('#flightId').val(flightId); // Set the value of the input field
        var selectedFlight = $("#flightDate option:selected").text();
        var selectedDate = selectedFlight.split(' || ')[0].trim();
        $('#selectedDate').text(selectedDate);
        console.log("Selected Flight ID:", flightId); // Debugging output

        $.ajax({
          url: '../Agent Section/functions/fetchFlightDetails.php', // Separate PHP file for return flight
          type: 'POST',
          data: {
            flightId: flightId,
            agentType: agentType
          },
          success: function(response) {
            var data = JSON.parse(response); // Parse the JSON response
            console.log(data);

            $('#packagePrice').val(data.packagePrice); // Set the value of the input field
            $('#packageName').val(data.packageName); // Set the value of the input field
            $('#flightPricee').val(data.flightPrice); // Set the value of the input field
            $('#packageId').val(data.packageId); // Set the value of the input field
            $('#origin').val(data.origin); // Set the value of the input field

            updateTotalPaxMax();

          },
          error: function(xhr, status, error) {
            console.error('Error fetching return flight:', error); // Log the error to console
          }
        });
      });

      // Event listeners
      $('#flightId').on('change', updateTotalPaxMax); // Trigger on flight change
      $('#land').on('change', updateTotalPaxMax); // Trigger on "Land Only" checkbox toggle

      // Ensure that if the user manually enters a number greater than the max, it's automatically corrected
      $('#totalPax').on('input', function() {
        var maxSeats = parseInt($(this).attr('max'));
        var currentPax = parseInt($(this).val());

        // If currentPax is greater than maxSeats or less than 1, adjust the value
        if (currentPax > maxSeats) {
          $(this).val(maxSeats); // Reset to the max value
        } else if (currentPax < 1 || isNaN(currentPax)) {
          $(this).val(1); // Reset to 1 if the value is less than 1 or not a number
        }
      });

      // New Book Now Button Click Event
      $('#bookNowButton').click(function(event) {
        $('#selectedPackage').text($('#packageName').val());
        $('#selectedOrigin').text($('#origin').val());
        var selectedFlight = $("#flightDate option:selected").text();
        var selectedDate = selectedFlight.split(' || ')[0].trim();
        $('#selectedDate').text(selectedDate);
        event.preventDefault(); // Prevent default form submission

        const errors = {
          totalPax: 'Please Enter Total Pax.',
          flightDate: 'Please Select Flight Date.',
          fName: 'Please Enter First Name',
          lName: 'Please Enter Last Name',
          mName: 'Please Enter Middle Name',
          suffix: 'Please Select Suffix',
          countryCode: 'Please Select Country Code',
          contactNo: 'Please Enter Contact No',
          email: 'Please Enter Email'
        };

        // Reset error messages and remove invalid class
        $('span[id$="Error"]').text('');
        $('select, input').removeClass('is-invalid');

        let isValid = true; // Initialize isValid flag
        // Extract the numeric value from the label's text
        let totalSeatsText = $('#availSeats').text();
        let totalSeats = parseInt(totalSeatsText.replace(/\D/g, '')) || 0; // Replace all non-digit characters and parse the number
        let landOnly = $('#land').prop('checked');

        console.log(totalSeats);

        // Validation function
        const validateField = (selector, errorMsgKey) => {
          const fieldValue = $(selector).val();
          if (!fieldValue) {
            $(`${selector}Error`).text(errors[errorMsgKey]); // Update error message
            $(selector).addClass('is-invalid'); // Add invalid class
            isValid = false; // Set valid flag to false
          }
        };

        // Validate all fields
        validateField('#totalPax', 'totalPax');
        validateField('#flightDate', 'flightDate');
        validateField('#fName', 'fName');
        validateField('#lName', 'lName');
        validateField('#mName', 'mName');
        validateField('#suffix', 'suffix');
        validateField('#countryCode', 'countryCode');
        validateField('#contactNo', 'contactNo');
        validateField('#email', 'email');

        // Additional check for totalPax to ensure it is not 0
        const totalPax = parseInt($('#totalPax').val());
        if (totalPax === 0 || isNaN(totalPax)) {
          $('#totalPaxError').text('Total Pax cannot be 0. Please enter a valid number.');
          $('#totalPax').addClass('is-invalid');
          isValid = false;
        }

        // Clear error messages when inputs are focused or changed
        $('select, input').on('focus change', function() {
          const errorSpanId = `#${$(this).attr('id')}Error`;
          $(this).removeClass('is-invalid'); // Remove invalid class
          $(errorSpanId).text(''); // Clear error message
          $('#errorMessage').text(''); // Show error message in the UI
        });

        // Combined validation for Land Only or Seat availability
        if (isValid) {
          const firstName = $('#fName').val().trim();
          const lastName = $('#lName').val().trim();
          let middleName = $('#mName').val().trim() || '';
          let suffix = $('#suffix').val().trim() || '';
          let email = $('#email').val().trim();

          // Set suffix and middle name to an empty string if they are "N/A"
          suffix = suffix === 'N/A' ? '' : suffix;
          middleName = middleName === 'N/A' ? '' : middleName;

          // Format middle name to the first letter followed by a dot, if not empty
          middleName = middleName ? middleName.charAt(0) + '.' : '';

          // Concatenate to full name in the desired format
          const fullName = `${lastName}, ${firstName} ${suffix} ${middleName}`;

          // Check if "Land Only" is selected
          if ($('#land').prop('checked')) {
            // Set the full name and email, and trigger modal
            $('#contactPersonName').text(fullName);
            $('#contactPersonEmail').text(email);
            $('#guestCount').text(totalPax);
            $('#BookingSummaryModal').modal('show'); // Trigger modal display
          } else if (totalPax > totalSeats) {
            // If land only is not selected, check for seat availability
            $('#errorMessage').text('The Available Seats are not enough.'); // Show error message in the UI
            alert('The Available Seats are not enough.'); // Show error message as an alert
          } else {
            // Set the full name in the contactPersonName paragraph
            $('#contactPersonName').text(fullName);
            // Set the email in the email paragraph
            $('#contactPersonEmail').text(email);
            // Set the total number of guests in the guestCount paragraph
            $('#guestCount').text(totalPax);

            $('#BookingSummaryModal').modal('show'); // Trigger modal display
          }
        } else {
          $('#errorMessage').text('Validation failed or no seats available.'); // Show error message in the UI
          console.error('Validation failed or no seats available.');
        }
      });

      // Automatically recalculate total price when flightDate or totalPax changes
      $('#flightDate, #totalPax').on('input change', function() {
        updateTotalPrice(); // Recalculate total price
      });

      // Recalculate total price when "land" checkbox is toggled
      document.getElementById('land').addEventListener('change', function() {
        updateTotalPrice(); // Recalculate total price when land is checked/unchecked
      });

      // Function to update total price calculation
      function updateTotalPrice() {
        let totalPrice = 0;
        const isLandChecked = document.getElementById('land').checked; // Check if "land" checkbox is checked
        const totalPax = parseInt($('#totalPax').val()) || 0; // Get total passengers, default to 0 if invalid

        if (isLandChecked) {
          // If the "land" checkbox is checked, use the package price
          flightDetailsContainer.style.display = 'block'; // Show when checked
          const packagePriceField = document.getElementById('packagePrice');
          if (packagePriceField) {
            const packagePrice = parseFloat(packagePriceField.value) || 0; // Use package price, default to 0 if invalid
            totalPrice = packagePrice * totalPax; // Multiply by total passengers

            // Update the display to show the package price per pax
            const flightPriceSpan = document.getElementById('flightPrice');
            if (flightPriceSpan) {
              const formattedPackagePrice = `${formatNumberWithCommas(packagePrice.toFixed(2))}`;
              flightPriceSpan.innerText = formattedPackagePrice; // Show package price per pax
            }
          }
        } else {
          // If "land" is unchecked, use the original flight price from the hidden input
          const flightPriceSpan = document.getElementById('flightPrice');
          const flightPriceField = $('#flightPricee'); // Hidden input field using jQuery
          flightDetailsContainer.style.display = 'none'; // Show when checked
          if (flightPriceSpan && flightPriceField.length) {
            const originalFlightPrice = parseFloat(
              flightPriceField.val().replace(/,/g, '').replace('₱', '').trim()
            ) || 0; // Retrieve and parse the original flight price
            totalPrice = originalFlightPrice * totalPax; // Calculate total price using original flight price

            const formattedOriginalPrice = `${formatNumberWithCommas(originalFlightPrice.toFixed(2))}`;
            flightPriceSpan.innerText = formattedOriginalPrice; // Show original flight price
          }
        }

        // Format and update the total price display
        const displayTotalPriceElement = document.getElementById('displayTotalPrice');
        if (displayTotalPriceElement) {
          displayTotalPriceElement.innerText = `${formatNumberWithCommas(totalPrice.toFixed(2))}`; // Format with commas
        }

        // Update the totalPrice hidden input field
        const totalPriceField = document.getElementById('totalPrice');
        if (totalPriceField) {
          totalPriceField.value = totalPrice.toFixed(2); // Set value with 2 decimal places
        }
      }

      // Optional: Listen for changes in pax fields
      document.querySelectorAll('.pax').forEach((element) => {
        element.addEventListener('input', function() {
          updateTotalPrice(); // Recalculate when pax value changes
        });
      });

      // Helper function to format numbers with commas
      function formatNumberWithCommas(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      }

      function updateTotalPaxMax() {
        // Get the input values
        var flightId = $('#flightId').val();
        var agentId = $('#agentId').val();
        var accId = $('#accId').val();
        var isLandOnlyChecked = $('#land').is(':checked');

        if (flightId !== '') {
          // Perform an AJAX request to fetch seat information
          $.ajax({
            url: '../Agent Section/functions/fetchMaxSeatsPerAgent.php', // Replace with your server-side script URL
            method: 'POST',
            data: {
              flightId: flightId,
              accId: accId
            }, // Send the flightId to the server
            dataType: 'json', // Specify that we're expecting JSON response
            success: function(response) {
              if (response.flightId !== null) {
                // Extract the maxSeats from the response
                var maxSeats = response.maxSeats;
                var totalSeats = response.totalSeatsLeft;

                if (!isLandOnlyChecked) {
                  // If "Land Only" is not checked, dynamically update the max attribute
                  $('#totalPax').attr('max', maxSeats);

                  // Check if the current value of totalPax exceeds maxSeats, reset to maxSeats if needed
                  var currentPax = $('#totalPax').val();
                  if (currentPax > maxSeats) {
                    $('#totalPax').val(maxSeats); // Adjust the value
                    console.log('Pax left: ' + maxSeats);
                    console.log('Seats left: ' + totalSeats);
                  }

                  // Display the available seats
                  $('#maxSeats').text('Agent-Specific Available Seats for this Flight: ' + maxSeats);
                  $('#availSeats').text('Total Remaining Seats for this Flight: ' + totalSeats);
                } else {
                  // If "Land Only" is checked, set a default max value and clear the display
                  $('#totalPax').attr('max', 999); // Example max value, adjust as needed
                  $('#maxSeats').text(' ');
                  $('#availSeats').text(' ');
                }
              } else {
                // Handle the case where no flight information is found
                $('#maxSeats').text('Available Seats for this Flight: N/A');
              }
            },
            error: function(xhr, status, error) {
              // Log any errors
              console.error('AJAX Error:', error);
            }
          });
        } else {
          // Reset if no flight ID is selected
          $('#totalPax').removeAttr('max');
          $('#maxSeats').text('Available Seats for this Flight: N/A');
        }
      }

      // Initial call to set total price on page load
      updateTotalPrice();
      updateTotalPaxMax();
    });
  </script>

</body>

</html>