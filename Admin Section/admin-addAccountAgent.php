<?php
session_start();
require "../conn.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Account - Agent</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Admin Section/assets/css/admin-addAccountAgent.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Admin Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

  <div class="body-container">
    <?php include "../Admin Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <div class="navbar">
        <div class="backbutton-wrapper">
          <div class="back-button-wrapper">
            <a href="../Admin Section/admin-manageAgent.php" class="back-button-link">
              <i class="fa-solid fa-arrow-left"></i>
            </a>
          </div>

          <div class="page-name-wrapper">
            <h5>Add Account - Agent</h5>
          </div>

        </div>
      </div>

      <div class="main-content">
        <form id="addAccountForm">

        <div class="content-container">

            <div class="content-section">
              <div class="content-header">
                Personal Information
              </div>

              <div class="content-body">

                <div class="row">
                  <div class="col-md-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                  </div>

                  <div class="col-md-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                  </div>

                  <div class="col-md-3">
                    <label for="middleName" class="form-label">Middle Name</label>
                    <input type="text" class="form-control" id="middleName" name="middleName">
                  </div>

                  <div class="col-md-3">
                    <label for="middleName" class="form-label">Suffix</label>
                    <input type="text" class="form-control" id="middleName" name="middleName">
                  </div>
                </div>

                <div class="row mt-3">

                  <div class="col-md-4">
                    <div class="form-group">

                      <label for="contactNo" class="contactNo">Contact No. <span class="text-danger">*</span></label>

                      <div class="input-group contactNoWrapper">
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

                        <input type="text" id="contactNo" name="contactNo" class="form-control" placeholder="Enter phone number" required>


                        <span id="contactNoError" class="text-danger"></span>
                        <!-- Error message for Contact No -->
                      </div>

                    </div>
                  </div>

                  <div class="col-md-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                  </div>

                </div>

                <div class="row mt-3">

                  <div class="col-md-3">
                    <div class="form-group">
                      <label for="email" class="form-label">Email</label>
                      <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                  </div>

                </div>

                <div class="row password">

                <div class="col-md-3">
                    <label for="email" class="form-label">Password</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                  </div>

                  <div class="col-md-3">
                    <label for="email" class="form-label">Confirm Password</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                  </div>

                </div>

              </div>


            </div>

            <div class="content-section">
              <div class="content-header">
                Account Information
              </div>

              <div class="content-body">

                <div class="row">
                  <div class="col-md-3">
                    <label for="accountStatus" class="form-label">Branch</label>
                    <select class="form-select" id="accountStatus" name="accountStatus" required>
                      <option value="All" disabled selected>Select Branch</option>
                      <?php
                      // Execute the SQL query
                      $sql1 = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
                      $res1 = $conn->query($sql1);

                      // Check if there are results
                      if ($res1->num_rows > 0) {
                        // Loop through the results and generate options
                        while ($row = $res1->fetch_assoc()) {
                          echo "<option value='" . $row['branchName'] . "'>" . $row['branchName'] . "</option>";
                        }
                      } else {
                        echo "<option value=''>No companies available</option>";
                      }
                      ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label for="accountStatus" class="form-label">Agent Type</label>
                    <select class="form-select" id="accountStatus" name="accountStatus" required>
                        <option value="" selected disabled>Select Agent Type</option>
                        <option value="Wholesaler">Wholesaler</option>
                        <option value="Retailer">Retailer</option>
                    </select>
                  </div>

                </div>

              </div>


            </div>

            <div class="submit-button-wrapper">
              <button type="submit" class="btn btn-primary">Add Agent</button>
            </div>

        </div>
        </form>

      </div>
    </div>

  </div>


  <?php require "../Agent Section/includes/scripts.php"; ?>






  <!-- ContactNo and Country Code Script -->
  <script>
    let contactNo = document.getElementById("contactNo");
    let countryCode = document.getElementById("countryCode");

    // **Set default PH format on page load**
    contactNo.placeholder = "9-XXXXXXXXX"; // PH format
    contactNo.value = "9-"; // Reset to enforce format
    contactNo.setAttribute("maxlength", "11"); // Limit length

    // Listen for country code changes
    countryCode.addEventListener("change", function() {
      if (this.value === "+63") {
        contactNo.placeholder = "9-XXXXXXXXX"; // PH format
        contactNo.value = "9-"; // Reset to enforce format
        contactNo.setAttribute("maxlength", "11"); // Limit length
      } else {
        contactNo.placeholder = "Enter phone number"; // Default format
        contactNo.value = ""; // Clear input
        contactNo.removeAttribute("maxlength"); // Remove length restriction
      }
    });

    // Enforce PH number format while typing
    contactNo.addEventListener("input", function() {
      if (countryCode.value === "+63") {
        this.value = this.value.replace(/\D/g, ""); // Remove non-numeric characters

        // Ensure '9-' is always at the start
        if (!this.value.startsWith("9-")) {
          this.value = "9-" + this.value.replace(/^9-?/, "").slice(0, 9);
        }
      }
    });

    // Prevent users from editing the '9-'
    contactNo.addEventListener("keydown", function(event) {
      if (countryCode.value === "+63") {
        if (this.selectionStart < 2) {
          event.preventDefault(); // Block edits before '9-'
        }
      }
    });
  </script>


  <script>
    $(document).ready(function() {
      $('#addAccountForm').submit(function(event) {
        event.preventDefault(); // Prevent default form submission

        // Log form data to console
        let formData = new FormData(this);
        formData.forEach(function(value, key) {
          console.log(key + ": " + value); // Log each form field and its value
        });

        $.ajax({
          url: '../Admin Section/functions/admin-addAccountAgent - code.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
            let jsonResponse = JSON.parse(response);

            // Display the response message in the DOM
            if (jsonResponse.status === "success") {
              $('#responseMessage').html('<span style="color:green;">' + jsonResponse.message + '</span>');
              // Display an alert for success
              alert("Success: " + jsonResponse.message);
            } else {
              $('#responseMessage').html('<span style="color:red;">' + jsonResponse.message + '</span>');
              // Display an alert for error
              alert("Error: " + jsonResponse.message);
            }
          },

          error: function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX error: " + textStatus + ': ' + errorThrown);
            alert("Error: Something went wrong during the request.");
          }
        });
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