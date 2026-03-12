<!-- Guest Table -->
<div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" tabindex="0">

  <div class="tabs-wrapper">

    <div class="table-header">
      <?php
      // Check if 'id' is passed in the URL
      if (isset($_GET['id'])) {
        $transactionNumber = htmlspecialchars($_GET['id']);
        $_SESSION['transaction_number'] = $transactionNumber;
      }

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
      if ($result2 && $result2->num_rows > 0) {
        // Fetch the result
        $row2 = $result2->fetch_assoc();
        $guest_count = $row2['guest_count'];
        $pax2 = $row2['pax'];
      }

      if ($result3 && $result3->num_rows > 0) {
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

      <!-- <button type="button" class="btn btn-primary">
          View Guest Files
        </button> -->
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visaModal">
        Attach Visa Requirements
      </button>

    </div>

    <!-- <p>Pax: <?php echo $pax2; ?></p>
      <p>Guest Count: <?php echo $guest_count; ?></p>
      <p>Visa Count: <?php echo $visa_count; ?></p> -->


    <div class="table-container">
      <div class="table-wrapper-container">
        <table class="product-table">
          <thead>
            <tr>
              <th>GUEST ID</th>
              <th>NAME</th>
              <th>BIRTHDATE</th>
              <th>AGE</th>
              <th>SEX</th>
              <th>NATIONALITY</th>
              <th>CONTACT NO.</th>
              <th>OTHER CONTACT NO.</th>
              <th>EMAIL</th>
              <th>ADDRESS</th>
              <th>PASSPORT NO.</th>
              <th>PASSPORT ISSUED DATE</th>
              <th>PASSPORT EXP.</th>
              <th>VISA STATUS</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql1 = "SELECT *, DATE_FORMAT(birthdate, '%M %d, %Y') AS birthdate, CONCAT(countryCode, ' ', contactNo) AS contactNo,
                    CASE 
                      WHEN countryCode2 IS NULL OR contactNo2 IS NULL THEN 'N/A'
                      ELSE CONCAT(countryCode2, ' ', contactNo2)
                    END AS contactNo2, CONCAT(addressLine1, ', ', 
                    CASE 
                      WHEN addressLine2 IS NOT NULL AND addressLine2 != '' THEN CONCAT(addressLine2, ', ') 
                      ELSE '' 
                    END, city, ', ', state, ', ', zipcode, ', ', country) AS address
                    FROM guest 
                    WHERE transactNo = '$transactionNumber'";

            $res1 = $conn->query($sql1);

            if ($res1->num_rows > 0) {
              while ($row = $res1->fetch_assoc()) {
                // Define the full name variable with suffix
                // Define the full name without suffix first
                $fullName = $row['fName'] . ' ' . $row['mName'] . ' ' . $row['lName'];

                // Append suffix only if it is not "N/A"
                if (!empty($row['suffix']) && $row['suffix'] !== 'N/A') {
                  $fullName .= ' ' . $row['suffix']; // Append suffix if it exists and is not "N/A"
                }

                // Escape values for safety
                $guestId = htmlspecialchars($row['guestId']);
                $birthdate = htmlspecialchars($row['birthdate']);
                $age = htmlspecialchars($row['age']);
                $sex = htmlspecialchars($row['sex']);
                $nationality = htmlspecialchars($row['nationality']);
                $contactNo = htmlspecialchars($row['contactNo']);
                $contactNo2 = htmlspecialchars($row['contactNo2']);
                $emailAdd = htmlspecialchars($row['emailAdd']);
                $address = htmlspecialchars($row['address']);
                $passportNo = htmlspecialchars($row['passportNo']);
                $passportIssuedDate = $row['passportIssuedDate'] ?? '';
                $passportExp = htmlspecialchars($row['passportExp']);

                echo "<tr data-url='agent-updateGuestInfo.php?id={$guestId}'>
                          <td>{$guestId}</td>
                          <td>{$fullName}</td>
                          <td>{$birthdate}</td>
                          <td>{$age}</td>
                          <td>{$sex}</td>
                          <td>{$nationality}</td>
                          <td>{$contactNo}</td>
                          <td>{$contactNo2}</td>
                          <td>{$emailAdd}</td>
                          <td>{$address}</td>
                          <td>{$passportNo}</td>
                          <td>{$passportIssuedDate}</td>
                          <td>{$passportExp}</td>
                          <td>{$row['visaStatus']}</td>
                        </tr>";
              }
            } else {
              echo "<tr><td colspan='100' style='text-align: center;'>No Guest found</td></tr>";
            }
            ?>
          </tbody>
        </table>

      </div>
    </div>

  </div>

  
</div>

<!-- Attach Visa Requirements Modal -->
<div class="modal fade" id="visaModal" tabindex="-1" aria-labelledby="visaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="visaModalLabel">
          Visa Requirements for Transaction No: <?php echo htmlspecialchars($_SESSION['transaction_number'] ?? ''); ?>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="../Agent Section/functions/agent-addVisaRequirements-code.php" method="POST"
        enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="transaction_number"
            value="<?php echo htmlspecialchars($_SESSION['transaction_number'] ?? ''); ?>">
          <input type="hidden" name="accId" value="<?php echo $accountId; ?>">

          <!-- Select Guest -->
          <div class="mb-4">
            <label for="guestSelect" class="form-label">Select Guest:</label>
            <select class="form-select" id="guestSelect" onchange="addGuestFields(this)">
              <option selected disabled>-- Select Guest --</option>
              <?php
              if ($res1) {
                $query1 = "SELECT g.guestId, CONCAT(g.lName, ', ', g.fName, ' ', 
                              CASE WHEN g.suffix = 'N/A' THEN '' ELSE g.suffix END, ' ',
                              CASE WHEN g.mName = 'N/A' THEN '' ELSE CONCAT(SUBSTRING(g.mName, 1, 1), '.') END) AS FULLNAME 
                            FROM guest g
                            WHERE g.transactNo = '$transactionNumber'";
                $res1 = mysqli_query($conn, $query1);
                while ($row = mysqli_fetch_assoc($res1)) {
                  $guestId = $row['guestId'];
                  $fullName = htmlspecialchars($row['FULLNAME']);
                  echo "<option value='$guestId'>$fullName</option>";
                }
              } else {
                echo "<option value=''>No guests available</option>";
              }
              ?>
            </select>
          </div>

          <!-- Container for all guests' visa requirements -->
          <div id="allGuestFields"></div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="attachVisaRequirements" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- New Visa Requirements Guest Script -->
<script>
  let addedGuests = new Set();

  function addGuestFields(selectElement) {
    const guestId = selectElement.value;
    const guestName = selectElement.options[selectElement.selectedIndex].text;

    if (!guestId || addedGuests.has(guestId)) {
      alert("Guest already added or invalid selection.");
      return;
    }

    addedGuests.add(guestId);

    const allGuestFieldsContainer = document.getElementById("allGuestFields");
    const guestFieldsHTML = `
      <div id="guestFields-${guestId}" class="guest-fields border rounded p-3 mt-3">
        <h6>Visa Requirements for ${guestName}</h6>
        <input type="hidden" name="guestIds[]" value="${guestId}">

        <div class="mb-3">
          <label class="form-label">Select Document to Upload:</label>
          <select class="form-select" onchange="showFileInput(this, ${guestId})">
            <option selected disabled>-- Select Document --</option>
            <option value="passport">Passport</option>
            <option value="permit">Permit</option>
            <option value="validId">Valid ID</option>
            <option value="certificate">Certificate</option>
            <option value="guaranteedLetter">Guaranteed Letter</option>
          </select>
        </div>

        <div id="fileInputs-${guestId}"></div>

        <button type="button" class="btn btn-danger btn-sm mt-2" onclick="removeGuestFields('${guestId}')">
          Remove ${guestName}
        </button>
      </div>`;

    allGuestFieldsContainer.insertAdjacentHTML("beforeend", guestFieldsHTML);
  }

  function showFileInput(selectElement, guestId) {
    const fileInputsContainer = document.getElementById(`fileInputs-${guestId}`);

    if (!fileInputsContainer) {
      console.error(`Error: File input container not found for guestId: ${guestId}`);
      return;
    }

    const selectedValue = selectElement.value;

    if (!selectedValue) {
      alert("Please select a document type.");
      return;
    }

    // Allow multiple file inputs for each document type
    const fileInputHTML = `
      <div class="mb-3 d-flex align-items-center">
        <label class="form-label me-2">${selectElement.options[selectElement.selectedIndex].text}:</label>
        <input type="file" class="form-control me-2" name="${selectedValue}[${guestId}][]" style="width:70%" multiple>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">X</button>
      </div>`;

    fileInputsContainer.insertAdjacentHTML("beforeend", fileInputHTML);
  }

  function removeGuestFields(guestId) {
    document.getElementById(`guestFields-${guestId}`).remove();
    addedGuests.delete(guestId);
  }
</script>

<!-- Working Visa Requirements Guest Script -->
<!-- <script>
  let guestCounter = 0;

  function addGuestFields(guestId = "") 
  {
    const allGuestFieldsContainer = document.getElementById("allGuestFields");
    const guestName = getGuestNameById(guestId);

    guestCounter++;

    const guestFieldsHTML = `
      <div id="guestFields-${guestCounter}" class="guest-fields">
        <h5 class="form-label mt-4">Visa Requirements for Guest: ${guestName}</h5>

        <div class="mb-3">
          <label class="form-label">Guest Name:</label>
          <input type="text" class="form-control" name="guestNames[]" value="${guestName}" readonly>
        </div>

        <input type="hidden" name="guestIds[]" value="${guestId}">

        <div class="mb-3">
          <label class="form-label">Select Document to Upload:</label>
          <select class="form-select" onchange="showFileInput(this, ${guestCounter})">
            <option selected disabled>-- Select Document --</option>
            <option value="passport">Passport</option>
            <option value="permit">Permit</option>
            <option value="validId">Valid ID</option>
            <option value="certificate">Certificate</option>
            <option value="guaranteedLetter">Guaranteed Letter</option>
          </select>
        </div>

        <div id="fileInputs-${guestCounter}"></div>

        <button type="button" class="btn btn-danger mt-2" onclick="removeGuestFields(${guestCounter}, '${guestId}')">Remove Guest</button>
        <hr>
      </div>`;

    allGuestFieldsContainer.insertAdjacentHTML("beforeend", guestFieldsHTML);
    disableSelectedGuest(guestId);
  }

  // Fixed function with remove button for file inputs
  function showFileInput(selectElement, counter) 
  {
    const fileInputsContainer = document.getElementById(`fileInputs-${counter}`);
    const selectedValue = selectElement.value;

    // Check if the document type is already added
    if (document.getElementById(`${selectedValue}-${counter}`)) 
    {
      alert("You have already added this document.");
      return;
    }

    // Generate file input with remove button
    const fileInputHTML = `
      <div id="${selectedValue}-${counter}" class="mb-3 d-flex align-items-center">
        <label class="form-label me-2">${selectElement.options[selectElement.selectedIndex].text}:</label>
        <input type="file" class="form-control me-2" name="${selectedValue}s[]" style="width:70%">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeFileInput('${selectedValue}-${counter}')">X</button>
      </div>`;

    fileInputsContainer.insertAdjacentHTML("beforeend", fileInputHTML);
  }

  // Function to remove specific file input
  function removeFileInput(inputId) 
  {
    const fileInput = document.getElementById(inputId);
    if (fileInput) 
    {
      fileInput.remove();
    }
  }

  // Function to get guest name by ID
  function getGuestNameById(guestId) 
  {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;

    for (let i = 0; i < options.length; i++) 
    {
      if (options[i].value == guestId) 
      {
        return options[i].text;
      }
    }

    return ''; // Return empty if no match is found
  }

  // Function to remove guest fields
  function removeGuestFields(counter, guestId) 
  {
    const guestFields = document.getElementById(`guestFields-${counter}`);
    if (guestFields) 
    {
      guestFields.remove();
    }

    // Re-enable the removed guest in the select dropdown
    enableGuestInSelect(guestId);
  }

  // Function to disable selected guest in dropdown
  function disableSelectedGuest(guestId) 
  {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;
    for (let i = 0; i < options.length; i++) 
    {
      if (options[i].value == guestId) 
      {
        options[i].disabled = true;
        break;
      }
    }
  }

  // Function to re-enable guest in dropdown
  function enableGuestInSelect(guestId) 
  {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;
    for (let i = 0; i < options.length; i++) 
    {
      if (options[i].value == guestId) 
      {
        options[i].disabled = false;
        break;
      }
    }
  }
</script> -->

<!-- Working Properly Reset Modal When Closed -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const visaModal = document.getElementById("visaModal");

    visaModal.addEventListener("hidden.bs.modal", function () {
      // Reset the form
      document.querySelector("#visaModal form").reset();

      // Only remove guest fields, but keep the "Select Guest" dropdown
      const allGuestFieldsContainer = document.getElementById("allGuestFields");
      const guestSelectWrapper = document.querySelector("#allGuestFields .mb-4"); // Keeps the select field
      allGuestFieldsContainer.innerHTML = ""; // Clear everything first
      if (guestSelectWrapper) {
        allGuestFieldsContainer.appendChild(guestSelectWrapper); // Restore select field
      }

      // Re-enable all previously disabled dropdown options
      const guestSelect = document.getElementById("guestSelect");
      for (let i = 0; i < guestSelect.options.length; i++) {
        guestSelect.options[i].disabled = false;
      }

      // Reset the guest dropdown selection
      guestSelect.selectedIndex = 0;
    });
  });
</script>

<!-- Orig Visa Requirements Guest Script -->
<!-- <script>
  let guestCounter = 0;

  function addGuestFields(guestId = "") {
    const allGuestFieldsContainer = document.getElementById("allGuestFields");
    const guestSelect = document.getElementById("guestSelect");

    // Increment counter for unique IDs
    guestCounter++;

    // Fetch the guest name from the selected ID
    const guestName = getGuestNameById(guestId);

    // Generate a new guest field block
    const guestFieldsHTML = `
      <div id="guestFields-${guestCounter}" class="guest-fields">
        <h5 class="form-label mt-4">
          Visa Requirements for Guest: ${guestName}
        </h5>
        
        Guest Name Display (Read-Only)
        <div class="mb-3">
          <label for="guestName-${guestCounter}" class="form-label">Guest Name:</label>
          <input type="text" class="form-control" id="guestName-${guestCounter}" name="guestNames[]" 
            value="${guestName}" readonly>
        </div>
        
        <input type="hidden" name="guestIds[]" value="${guestId}">
        
        Visa Fields
        <div class="mb-3">
          <label for="passport-${guestCounter}" class="form-label">Passport:</label>
          <input type="file" class="form-control" id="passport-${guestCounter}" name="passports[]">
        </div>
        <div class="mb-3">
          <label for="permit-${guestCounter}" class="form-label">Permit:</label>
          <input type="file" class="form-control" id="permit-${guestCounter}" name="permits[]">
        </div>
        <div class="mb-3">
          <label for="validId-${guestCounter}" class="form-label">Valid ID:</label>
          <input type="file" class="form-control" id="validId-${guestCounter}" name="validIds[]">
        </div>
        <div class="mb-3">
          <label for="certificate-${guestCounter}" class="form-label">Certificate:</label>
          <input type="file" class="form-control" id="certificate-${guestCounter}" name="certificates[]">
        </div>
        <div class="mb-3">
          <label for="guaranteedLetter-${guestCounter}" class="form-label">Guaranteed Letter:</label>
          <input type="file" class="form-control" id="guaranteedLetter-${guestCounter}" name="guaranteedLetters[]">
        </div>
        
        Remove Button
        <button type="button" class="btn btn-danger" onclick="removeGuestFields(${guestCounter}, '${guestId}')">Remove</button>
        <hr>
      </div>`;

    // Append the new guest fields to the container
    allGuestFieldsContainer.insertAdjacentHTML("beforeend", guestFieldsHTML);

    // Disable the selected guest in the dropdown
    disableSelectedGuest(guestId);
  }

  function getGuestNameById(guestId) {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;

    // Loop through the options to find the guest name based on guestId
    for (let i = 0; i < options.length; i++) {
      if (options[i].value == guestId) {
        return options[i].text;
      }
    }

    return ''; // Return empty if no match is found
  }

  function removeGuestFields(counter, guestId) {
    const guestFields = document.getElementById(`guestFields-${counter}`);
    if (guestFields) {
      guestFields.remove();
    }

    // Re-enable the removed guest in the select dropdown
    enableGuestInSelect(guestId);
  }

  function disableSelectedGuest(guestId) {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;
    for (let i = 0; i < options.length; i++) {
      if (options[i].value == guestId) {
        options[i].disabled = true;
        break;
      }
    }
  }

  function enableGuestInSelect(guestId) {
    const guestSelect = document.getElementById("guestSelect");
    const options = guestSelect.options;
    for (let i = 0; i < options.length; i++) {
      if (options[i].value == guestId) {
        options[i].disabled = false;
        break;
      }
    }
  }
</script> -->

<!-- Specific Row Clickable Script -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("tr[data-url]").forEach(function (row) {
      row.addEventListener("click", function () {
        window.location.href = row.getAttribute("data-url");
      });
    });
  });
  // Add event listener to each row for redirection
  const rows = document.querySelectorAll("tr[data-url]");

  rows.forEach(row => {
    row.addEventListener("click", function () {
      const url = row.getAttribute("data-url");
      window.location.href = url; // Redirect to the specified URL
    });
  });
</script>