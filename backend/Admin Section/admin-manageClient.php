<?php
session_start();
require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Accounts - Agent & Client</title>

  <?php include "../Admin Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Admin Section/assets/css/admin-transaction.css?v=<?php echo time(); ?>">

  <link rel="stylesheet" href="../Admin Section/assets/css/admin-addAccountAgent.css?v=<?php echo time(); ?>">

  <link rel="stylesheet" href="../Admin Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>
  <div class="body-container">
    <?php include "../Admin Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <div class="navbar">
        <h5 class="title-page">Manage Account - Agent & Client</h5>
      </div>

      <div class="main-content">
        <div class="table-wrapper">
          <div class="table-header">

            <div class="search-wrapper">
              <div class="search-input-wrapper">
                <input type="text" id="search" placeholder="Search here..">
              </div>
            </div>

            <!-- <div class="filter-field">
                <!-- <label for="status">Status:</label> 
                <div class="select-wrapper">
                  <select id="status">
                    <option value="All" disabled selected>Select Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Cancelled">Cancelled</option>
                  </select>
                </div>
              </div> -->

            <div class="second-header-wrapper">
              <div class="date-range-wrapper sorting-wrapper">
                <div class="select-wrapper">
                  <select id="packages">
                      <option value="All" disabled selected>Select Packages</option>
                      <option value="Autumn Tour Package">Autumn Tour</option>
                      <option value="Summer Tour Package">Summer Tour</option>
                      <option value="Spring Tour Package">Spring Tour</option>
                      <option value="Winter Tour Package">Winter Tour</option>
                      <option value="Regular Tour Package">Regular Tour</option>
                      <option value="Busan Tour Package">Busan Tour</option>
                  </select>
                </div> 
              </div>

              <div class="date-range-wrapper sorting-wrapper">
                <div class="select-wrapper">
                  <select id="packages">
                      <option value="All" disabled selected>Select Status</option>
                      <option value="Autumn Tour Package">Autumn Tour</option>
                      <option value="Summer Tour Package">Summer Tour</option>
                      <option value="Spring Tour Package">Spring Tour</option>
                      <option value="Winter Tour Package">Winter Tour</option>
                      <option value="Regular Tour Package">Regular Tour</option>
                      <option value="Busan Tour Package">Busan Tour</option>
                  </select>
                </div>
              </div>

              <div class="vertical-separator"></div>

              <!-- <div class="date-range-wrapper flightbooking-wrapper">
                <div class="date-range-inputs-wrapper">
                  <div class="input-with-icon">
                    <input type="text" class="datepicker" id="BookingStartDate" placeholder="Booking Date">
                    <i class="fas fa-calendar-alt calendar-icon"></i>
                  </div>
                </div>
              </div> -->

              <!-- <div class="date-range-wrapper flightbooking-wrapper">
                <div class="date-range-inputs-wrapper">
                  <div class="input-with-icon">
                    <input type="text" class="datepicker" id="FlightStartDate" placeholder="Flight Date">
                    <i class="fas fa-calendar-alt calendar-icon"></i>
                  </div>
                </div>
              </div> -->

              <div class="buttons-wrapper">
                <button id="clearSorting" class="btn btn-secondary">
                    Clear Filters
                </button>
              </div>

              <div class="buttons-wrapper">
                <button id="AddAccountBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#AddAccountModal">
                  <i class="fas fa-user-plus"></i> Add Account
                </button>
              </div>

            </div>

          </div>



          <div class="table-container">
                <table id="product-table" class="product-table">
                  <thead>
                    <tr>
                      <th>Account ID</th>
                      <th>Agent Code</th>
                      <th>Agent ID</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Password</th>
                      <th>Contact No.</th>
                      <th>Agent Type</th>
                      <th>Agent Role</th>
                      <th>STATUS</th>
                      <th></th>
                    </tr>
                  </thead>
                  <?php
                 $sql = "SELECT 
                 a.accountId AS `Account ID`,
                 c.clientCode AS `Client Code`,
                 c.clientId AS `Client ID`,
                 CONCAT(c.lName, ', ', c.fName, ' ', 
                     CASE WHEN c.mName = 'N/A' OR c.mName IS NULL 
                          THEN '' ELSE CONCAT(SUBSTRING(c.mName, 1, 1), '.') END) AS `Name`,
                 a.email AS `Email`,
                 a.password AS `Password`,
                 CONCAT(c.countryCode, ' ', c.contactNo) AS `Contact No.`,
                 c.clientType AS `Client Type`,
                 c.clientRole AS `Client Role`,
                 a.accountStatus AS `Status`
             FROM accounts a
             LEFT JOIN client c ON a.accountId = c.accountId
             WHERE a.accountType = 'aclient' 
             ORDER BY c.clientCode ASC, a.accountId ASC";  
     

                  $result = $conn->query($sql);


                  ?>
                  <tbody>
                    <?php

                    // Check if there are records
                    if ($result->num_rows > 0) {

                      while ($row = $result->fetch_assoc()) {
                        $accountId = htmlspecialchars($row['Account ID']);

                        echo "<tr>
                            <td>{$row['Account ID']}</td>
                            <td>{$row['Client Code']}</td>
                            <td>{$row['Client ID']}</td>
                            <td>{$row['Name']}</td>
                            <td>{$row['Email']}</td>
                            <td>***********</td>
                            <td>{$row['Contact No.']}</td>
                            <td>{$row['Client Type']}</td>
                            <td class='agentRole'>{$row['Client Role']}</td>
                            <td>{$row['Status']}</td>
                            <td>
                                <div class='dropdown-center' style='text-align: center; position: relative;'>
                                    <button class='btn' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                                        <i class='fas fa-ellipsis-v'></i>
                                    </button>
                                    <ul class='dropdown-menu' style='position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);'>
                                        <li>
                                            <a class='dropdown-item edit' href='#' data-id='<?php $accountId; ?>' data-bs-toggle='modal' data-bs-target='#editModal'>
                                                <i class='fas fa-edit'></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a class='dropdown-item delete text-danger' href='#' data-id='<?php echo $accountId; ?>' data-bs-toggle='modal' data-bs-target='#deleteModal'>
                                                <i class='fas fa-trash-alt'></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>


                          </tr>";
                      }
                    } else {
                      echo "<tr><td colspan='11' style='text-align: center;'>No agent records found</td></tr>";
                    }

                    
                    ?>
                  </tbody>

                </table>
          </div>

          <!-- Custom Pagination Container -->
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

  <?php require "../Admin Section/includes/scripts.php"; ?>

  <!-- Edit Modal -->
  <div class="modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editForm">
            <input type="hidden" id="editAccountId">
            <!-- Add form fields for account details here -->
            <div class="mb-3">
              <label for="editAccountName" class="form-label">Account Name</label>
              <input type="text" class="form-control" id="editAccountName" placeholder="Enter account name">
            </div>
            <!-- Add more fields as necessary -->
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="saveChanges">Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div class="modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">Delete Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this account?</p>
          <form id="deleteForm">
            <input type="hidden" id="deleteAccountId">
            <!-- You can add additional information or details if necessary -->
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
        </div>
      </div>
    </div>
  </div>


  <!-- Add Account Modal -->
  <div class="modal" id="AddAccountModal" tabindex="-1" aria-labelledby="AddAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Add Account - Agent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <form id="addAccountForm">
            <div class="content-section">
              <div class="content-header">
                Account Type
              </div>

              <div class="content-body">
                <div class="row">
                  <!-- Account Type Selection -->
                  <div class="columns col-md-4">
                      <label for="accountType" class="form-label">Account Type</label>
                      <select class="form-control" id="accountType" name="accountType">
                          <option value="" disabled selected>Select Account Type</option>
                          <option value="agent">Agent</option>
                          <option value="guest">Client</option> <!-- Match this value in the script -->
                      </select>
                  </div>
                </div>

              </div>

              <style>
                /* Make readonly fields greyed out */
                .readonly-grey {
                    background-color: #e9ecef !important; /* Grey background */
                    pointer-events: none; /* Prevent interaction */
                }
              </style>

              <script>
                document.addEventListener("DOMContentLoaded", function() {
                    document.getElementById("accountType").addEventListener("change", function() {
                        let agentRoleField = document.getElementById("agentRole");
                        let hiddenAgentRole = document.getElementById("hiddenAgentRole");

                        if (this.value === "guest") { // Match the lowercase value from your select options
                            agentRoleField.value = "Sub Agent";
                            hiddenAgentRole.value = "Sub Agent"; // Ensure value is posted
                            agentRoleField.setAttribute("disabled", "disabled"); // Disable selection
                            agentRoleField.classList.add("readonly-grey"); // Apply greyed-out style
                        } else {
                            agentRoleField.value = "";
                            hiddenAgentRole.value = ""; // Ensure it resets
                            agentRoleField.removeAttribute("disabled"); // Enable selection
                            agentRoleField.classList.remove("readonly-grey"); // Remove greyed-out style
                        }
                    });
                });
              </script>

            
              <div class="content-header">
                Personal Information
              </div>

              <div class="content-body">
                <div class="row">
                  <div class="columns col-md-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                  </div>

                  <div class="columns col-md-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                  </div>

                  <div class="columns col-md-3">
                    <label for="middleName" class="form-label">Middle Name</label>
                    <input type="text" class="form-control" id="middleName" name="middleName">
                  </div>

                  <div class="columns col-md-3">
                    <label for="Suffix" class="form-label">Suffix</label>
                    <select class="form-control" id="Suffix" name="Suffix">
                        <option value="None">Select Suffix</option>
                        <option value="Jr">Jr.</option>
                        <option value="Sr">Sr.</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                        <!-- Add more suffix options as needed -->
                    </select>
                  </div>

                </div>

                <div class="row">
                  <div class="columns col-md-7">
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

                        <input type="text" id="contactNo" name="contactNo" class="form-control" placeholder="Enter phone number" required maxlength="11">

                        <script>
                        document.getElementById("contactNo").addEventListener("input", function() {
                            // Remove non-numeric characters
                            this.value = this.value.replace(/\D/g, '');
                            
                            // Ensure max length of 11 characters
                            if (this.value.length > 11) {
                                this.value = this.value.slice(0, 11);
                            }
                        });
                        </script>



                        <span id="contactNoError" class="text-danger"></span>
                        <!-- Error message for Contact No -->
                      </div>

                    </div>
                  </div>
                </div>

                <!-- <div class="row">
                  <div class="columns col-md-5">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                  </div>
                </div> -->

                <div class="row password">
                  <div class="columns col-md-5">
                      <label for="password" class="form-label">Password</label>
                      <input type="password" class="form-control" id="password" name="password" required>
                  </div>

                  <div class="columns col-md-5">
                      <label for="cpassword" class="form-label">Confirm Password</label>
                      <input type="password" class="form-control" id="cpassword" name="cpassword" required>
                      <small id="passwordError" class="text-danger" style="display: none;">Passwords do not match!</small>
                  </div>
              </div>

              </div>
            </div>

            
              <div class="content-header">
                Account Information
              </div>

              <div class="content-body">
                <div class="row">
                  <div class="columns col-md-4">
                      <label for="accountStatus" class="form-label">Branch</label>
                      <select class="form-select" id="branchId" name="branchId" required>
                          <option value="All" disabled selected>Select Branch</option>
                          <?php
                          // Execute the SQL query to fetch branch details
                          $sql1 = "SELECT branchId, branchName FROM branch ORDER BY branchName ASC";
                          $res1 = $conn->query($sql1);    

                          // Check if there are results
                          if ($res1->num_rows > 0) {
                              // Loop through the results and generate options
                              while ($row = $res1->fetch_assoc()) {
                                  // Use branchId as the value for each option
                                  echo "<option value='" . $row['branchId'] . "'>" . $row['branchName'] . "</option>";
                              }
                          } else {
                              echo "<option value=''>No branches available</option>";
                          }
                          ?>
                      </select>
                  </div>

                  <div class="columns col-md-4">
                    <label for="accountStatus" class="form-label">Agent Type</label>
                    <select class="form-select" id="agentType" name="agentType" required>
                      <option value="" selected disabled>Select Agent Type</option>
                      <option value="Wholeseller">Wholeseller</option>
                      <option value="Retailer">Retailer</option>
                    </select>
                  </div>

                  <!-- Agent Role Selection -->
                  <div class="columns col-md-4">
                      <label for="agentRole" class="form-label">Agent Role</label>
                      <select class="form-control" id="agentRole" >
                          <option value="">Select Role</option>
                          <option value="Head Agent">Head Agent</option>
                          <option value="Sub Agent">Sub Agent</option>
                          <option value="Other Role">Other Role</option>
                      </select>
                  </div>

                </div>
              </div>

              <!-- Hidden input to store the selected agentRole -->
              <input type="text" id="hiddenAgentRole" name="agentRole">
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <div class="btn-wrapper">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="closeModalBtn">Close</button>
            <button type="submit" class="btn btn-primary">Save changes</button>
          </div>
        </div>

      </form>
    </div>
  </div>
  </div>

<!-- ContactNo and Country Code Script -->
<!-- <script>
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
  </script> -->

  <script>

    $(document).ready(function() {

      const password = document.getElementById("password");
      const confirmPassword = document.getElementById("cpassword");
      const passwordError = document.getElementById("passwordError");

      function validatePassword() {
          if (password.value !== confirmPassword.value) {
              passwordError.style.display = "block";
              confirmPassword.setCustomValidity("Passwords do not match!");
          } else {
              passwordError.style.display = "none";
              confirmPassword.setCustomValidity("");
          }
      }

      password.addEventListener("input", validatePassword);
      confirmPassword.addEventListener("input", validatePassword);



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
              location.reload();
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
    document.addEventListener("DOMContentLoaded", function () {
      let addAccountModal = document.getElementById("AddAccountModal");

      addAccountModal.addEventListener("hidden.bs.modal", function () {
          document.querySelectorAll(".modal-backdrop").forEach((el) => el.remove());
          document.body.classList.remove("modal-open"); // Prevent scrolling lock
      });
  });
  </script>
                             
  <script>
    document.getElementById("AddAccountBtn").addEventListener("click", function() {
      var addAccountModal = new bootstrap.Modal(document.getElementById("AddAccountModal"));
      addAccountModal.show();
    });
  </script>

  <script>
    // Listen for the 'hidden.bs.modal' event, which is triggered when the modal is fully hidden
    var addAccountModal = document.getElementById("AddAccountModal");
    addAccountModal.addEventListener('hidden.bs.modal', function () {
      // Reset the form fields when the modal is closed
      document.getElementById("addAccountForm").reset();
    });

    document.getElementById("AddAccountBtn").addEventListener("click", function() {
      var modal = new bootstrap.Modal(document.getElementById("AddAccountModal"));
      modal.show();
    });
  </script>



  <!-- DataTables #product-table -->
  <script>
    $(document).ready(function() {
      const table = $('#product-table').DataTable({
    dom: 'rtip',
    language: {
        emptyTable: "No Transaction Records Available",
        zeroRecords: "No matching records found" // This prevents errors on empty searches
    },
    order: [[0, 'desc']],
    scrollX: false,
    scrollY: '72vh',
    paging: true,
    pageLength: 14,
    autoWidth: false,
    autoHeight: false,
    columnDefs: [
        {
            targets: [1, 2, 3, 5, 6],
            orderable: false
        }
    ]
});



      // Search Functionality
      $('#search').on('keyup', function() {
        table.search(this.value).draw();
      });

      // Update the custom pagination buttons and page info
      function updatePagination() {
        const info = table.page.info();
        const currentPage = info.page + 1; // Get current page number (1-indexed)
        const totalPages = info.pages; // Get total pages

        // Update page info text
        $('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);

        // Enable/Disable prev and next buttons based on current page
        $('#prevPage').prop('disabled', currentPage === 1);
        $('#nextPage').prop('disabled', currentPage === totalPages);
      }

      // Custom pagination button click events
      $('#prevPage').on('click', function() {
        table.page('previous').draw('page');
        updatePagination();
      });

      $('#nextPage').on('click', function() {
        table.page('next').draw('page');
        updatePagination();
      });

      // Initialize pagination on first load
      updatePagination();

      // Status Filter
      $('#status').on('change', function() {
        const selectedStatus = $(this).val();
        table.column(8).search(selectedStatus || '').draw();
      });

      // Package Filter
      $('#packages').on('change', function() {
        const selectedPackage = $(this).val();
        table.column(3).search(selectedPackage || '').draw();
      });

      // Booking Date Filter with value change
      $('#BookingStartDate').on('change', function() {
        const selectedBookingDate = $(this).val(); // Get the selected value directly from the input field
        console.log("Booking Date Filter:", selectedBookingDate); // Log the selected booking date
        table.column(4).search(selectedBookingDate || '').draw(); // Column 4 (index starts at 0)
      });

      // Flight Date Filter with value change
      $('#FlightStartDate').on('change', function() {
        const selectedFlightDate = $(this).val(); // Get the selected value directly from the input field
        console.log("Flight Date Filter:", selectedFlightDate); // Log the selected flight date
        table.column(5).search(selectedFlightDate || '').draw(); // Column 5 (index starts at 0)
      });

      // Apply datepicker and input validation for FlightStartDate
      $("#FlightStartDate").datepicker({
        dateFormat: "mm-dd-yy", // Set the format to MM-DD-YYYY
        showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
        changeMonth: true, // Allow the month to be changed from the dropdown
        changeYear: true, // Allow the year to be changed from the dropdown
        yearRange: "1900:2100", // Set a range of years (optional)
        onSelect: function(dateText) {
          // When a date is selected, update the input field with the date
          $(this).val(dateText);
          flightStartDate = dateText; // Store the selected date
          console.log("FlightStartDate Selected Date (onSelect): " + dateText);
          table.column(5).search(flightStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });

      $("#FlightStartDate").datepicker({
        dateFormat: "mm-dd-yy", // Set the format to MM-DD-YYYY
        showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
        changeMonth: true, // Allow the month to be changed from the dropdown
        changeYear: true, // Allow the year to be changed from the dropdown
        yearRange: "1900:2100", // Set a range of years (optional)
        onSelect: function(dateText) {
          // When a date is selected, update the input field with the date
          if (dateText === "") {
            flightStartDate = ""; // Reset the variable if the field is cleared
          } else {
            flightStartDate = dateText; // Store the selected date
          }
          console.log("FlightStartDate Selected Date (onSelect): " + flightStartDate);
          table.column(5).search(flightStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });

      // Apply datepicker and input validation for BookingStartDate
      $("#BookingStartDate").datepicker({
        dateFormat: "mm-dd-yy", // Set the format to MM-DD-YYYY
        showAnim: "fadeIn", // Optional: Adds a fade-in effect when the date picker is opened
        changeMonth: true, // Allow the month to be changed from the dropdown
        changeYear: true, // Allow the year to be changed from the dropdown
        yearRange: "1900:2100", // Set a range of years (optional)
        onSelect: function(dateText) {
          // When a date is selected, update the input field with the date
          $(this).val(dateText);
          bookingStartDate = dateText; // Store the selected date
          console.log("FlightStartDate Selected Date (onSelect): " + dateText);
          table.column(4).search(bookingStartDate || '').draw(); // Column 5 (index starts at 0)
        }
      });

      // BookingStartDate Input Validation and Formatting
      $("#BookingStartDate").on("input", function() {
        var value = $(this).val();

        // Remove non-numeric and non-dash characters
        value = value.replace(/[^\d-]/g, '');

        // Automatically add dashes in the correct places if necessary
        if (value.length > 2 && value.charAt(2) !== '-') {
          value = value.substring(0, 2) + '-' + value.substring(2);
        }
        if (value.length > 5 && value.charAt(5) !== '-') {
          value = value.substring(0, 5) + '-' + value.substring(5);
        }

        // Limit the total input length to 10 characters (MM-DD-YYYY)
        if (value.length > 10) {
          value = value.substring(0, 10);
        }

        // Update the input field value
        $(this).val(value);

        // Reset or update the bookingStartDate variable
        if (value === "") {
          bookingStartDate = ""; // Reset the variable if the input is cleared
        } else {
          bookingStartDate = value; // Update the variable with the formatted value
        }

        // Update the table column search
        table.column(5).search(bookingStartDate || '').draw(); // Column 5 (index starts at 0)

        console.log("BookingStartDate Input Value (on input): " + value);
      });

      // Clear All Filters
      $('#clearSorting').on('click', function() {
        // Clear search field
        $('#search').val('');
        table.search('').draw();

        // Clear status dropdown
        $('#status').val('All').change();

        // Clear packages dropdown
        $('#packages').val('All').change();

        // Explicitly reset the variables
        flightStartDate = '';
        bookingStartDate = '';

        // Clear date fields
        $('#BookingStartDate').val('').trigger('change'); // Reset and trigger input for BookingStartDate
        $('#FlightStartDate').val('').trigger('change'); // Reset and trigger input for FlightStartDate



        // Redraw the table
        table.draw();
      });
    });
  </script>


</body>

</html>