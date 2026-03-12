<div class="card">
  <div class="card-body">
    <ul class="nav nav-tabs" id="entryTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">Manual Entry</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv" type="button" role="tab">CSV/Excel Upload</button>
      </li>
    </ul>
  </div>

  <div class="tab-content pt-3">
    <!-- Manual Flight Entry -->
    <div class="tab-pane fade show active" id="manual" role="tabpanel">
      <form id="manualFlightForm" method="POST" action="../Employee Section/functions/emp-addFlightDate.php">
        <div class="table-responsive">
          <table class="table table-bordered table-centered mb-0" id="manualEntryTable">
            <thead>
              <tr>
                <th style="width: 13%;">Team OP</th>
                <th style="width: 10%;">Package</th>
                <th style="width: 10%;">Origin</th>
                <th style="width: 10%;">Flight Code</th>
                <th style="width: 10%;">Departure Date</th>
                <th style="width: 10%;">Flight Code</th>
                <th style="width: 10%;">Return Date</th>
                <th style="width: 8%;">Wholesale Price</th>
                <th style="width: 8%;">Flight Price</th>
                <th style="width: 8%;">Land Price</th>
                <th style="width: 8%;">Available Seats</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="manualFlightBody">
              <tr>
                <!-- Team OP -->
                <td>
                  <select name="employeeId[]" class="form-control">
                    <option selected disabled>Select Team OP</option>
                    <?php
                      $sql1 = "SELECT * FROM employee";
                      $res1 = $conn->query($sql1);
                      
                      if ($res1 -> num_rows > 0)
                      {
                        while($row = $res1->fetch_assoc())
                        {
                          $fName = $row['fName'] ?? null;
                          $mName = $row['mName'] ?? null;
                          $lName = $row['lName'] ?? null;
                          if (empty($fName) && empty($lName)) 
                          {
                            $fullName = "No Team OP";
                          } else {
                            $middleInitial = $mName ? strtoupper(substr($mName, 0, 1)) . '.' : '';
                            $fullName = $lName . ", " . $fName . " " . $middleInitial;
                          }
                          echo "<option value='".$row['employeeId']."'>".$fullName."</option>";
                        }
                      }
                      else
                      {
                        echo "No Package Found";
                      }
                    ?>
                  </select>
                </td>
                <!-- Package -->
                <td>
                  <select name="packageId[]" class="form-control" required>
                    <option selected disabled>Select Package</option>
                    <?php
                      $sql1 = "SELECT * FROM package ORDER BY packageName";
                      $res1 = $conn->query($sql1);
                      
                      if ($res1 -> num_rows > 0)
                      {
                        while($row = $res1->fetch_assoc())
                        {
                          echo "<option value='".$row['packageId']."'>".$row['packageName']."</option>";
                        }
                      }
                      else
                      {
                        echo "No Package Found";
                      }
                    ?>
                  </select>
                </td>
                <!-- Origin -->
                <td>
                  <select name="origin[]" class="form-control" required>
                    <option selected disabled>Select Origin</option>
                    <?php
                      $sql1 = "SELECT DISTINCT origin FROM flight ORDER BY origin";
                      $res1 = $conn->query($sql1);
                      
                      if ($res1 -> num_rows > 0)
                      {
                        while($row = $res1->fetch_assoc())
                        {
                          echo "<option value='".$row['origin']."'>".$row['origin']."</option>";
                        }
                      }
                      else
                      {
                        echo "No Origin Found";
                      }
                    ?>
                  </select>
                </td>
                <!-- Departure Flight Code -->
                <td>
                  <select name="departureFlightCode[]" class="form-control" required>
                    <option selected disabled>Select Flight Code</option>
                    <?php
                      $sql1 = "SELECT DISTINCT flightCode FROM flight ORDER BY flightCode";
                      $res1 = $conn->query($sql1);
                      
                      if ($res1 -> num_rows > 0)
                      {
                        while($row = $res1->fetch_assoc())
                        {
                          echo "<option value='".$row['flightCode']."'>".$row['flightCode']."</option>";
                        }
                      }
                      else
                      {
                        echo "No Flight Code Found";
                      }
                    ?>
                  </select>
                </td>
                <td><input type="date" name="departureDate[]" class="form-control departure-date" required></td>
                <!-- Return Flight Code -->
                <td>
                  <select name="returnFlightCode[]" class="form-control" required>
                    <option selected disabled>Select Flight Code</option>
                    <?php
                      $sql1 = "SELECT DISTINCT returnFlightCode FROM flight ORDER BY returnFlightCode";
                      $res1 = $conn->query($sql1);
                      
                      if ($res1 -> num_rows > 0)
                      {
                        while($row = $res1->fetch_assoc())
                        {
                          echo "<option value='".$row['returnFlightCode']."'>".$row['returnFlightCode']."</option>";
                        }
                      }
                      else
                      {
                        echo "No Flight Code Found";
                      }
                    ?>
                  </select>
                </td>
                <td><input type="date" name="returnDate[]" class="form-control return-date" required readonly></td>
                <td><input type="number" name="wholesalePrice[]" step="0.01" class="form-control" min="1" required></td>
                <td><input type="number" name="flightPrice[]" step="0.01" class="form-control" min="1" required></td>
                <td><input type="number" name="landPrice[]" step="0.01" class="form-control" min="1" required></td>
                <td><input type="number" name="availSeats[]" class="form-control" min="1" required></td>
                <td>
                  <button type="button" class="btn btn-success btn-sm addRow">+</button>
                  <button type="button" class="btn btn-danger btn-sm removeRow">-</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-3 text-end">
          <button type="submit" class="btn btn-primary">Save Flights</button>
        </div>
      </form>
    </div>

    <!-- CSV/Excel Upload -->
    <div class="tab-pane fade" id="csv" role="tabpanel">
      <form method="POST" id="flightUploadForm" action="../Employee Section/functions/emp-importFlightDate.php" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="csvFile" class="form-label">Upload CSV or Excel File</label>
          <input type="file" name="flightFile" class="form-control" id="csvFile" accept=".csv, .xlsx, .xls" required>
        </div>
        <div class="text-end">
          <button type="submit" class="btn btn-primary">Upload</button>
          <!-- <a href="transactions.php" class="btn btn-secondary">Back to List</a> -->
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Dynamic Row for Data Entry -->
<script>
  document.addEventListener('DOMContentLoaded', function () 
  {
    const tableBody = document.querySelector('#manualFlightBody');

    // Handle Add Row
    tableBody.addEventListener('click', function (e) 
    {
      if (e.target.classList.contains('addRow')) 
      {
        const currentRow = e.target.closest('tr');
        const newRow = currentRow.cloneNode(true);

        // Clear all input and select values in the cloned row
        newRow.querySelectorAll('input, select').forEach(el => 
        {
          if (el.tagName === 'SELECT') 
          {
            el.selectedIndex = 0;
          } 
          else if (el.type === 'date' || el.type === 'number' || el.type === 'text') 
          {
            el.value = '';
          }
        });

        tableBody.appendChild(newRow);
      }

      // Handle Remove Row
      if (e.target.classList.contains('removeRow')) 
      {
        const rows = tableBody.querySelectorAll('tr');
        if (rows.length > 1) 
        {
          e.target.closest('tr').remove();
        }
      }
    });

    // Optional: Auto-set return date to 5 days after departure date
    tableBody.addEventListener('change', function (e) 
    {
      if (e.target.classList.contains('departure-date')) 
      {
        const depDateInput = e.target;
        const retDateInput = depDateInput.closest('tr').querySelector('.return-date');

        if (depDateInput.value) 
        {
          const depDate = new Date(depDateInput.value);
          depDate.setDate(depDate.getDate() + 5);
          retDateInput.valueAsDate = depDate;
        }
      }
    });
  });
</script>

<!-- Auto compute the return date based on departure date -->
<script>
  $(document).on('change', '.departure-date', function () {
    const departureInput = $(this);
    const departureDate = new Date(departureInput.val());

    if (!isNaN(departureDate)) {
      const returnDate = new Date(departureDate);
      returnDate.setDate(returnDate.getDate() + 6);

      const yyyy = returnDate.getFullYear();
      const mm = String(returnDate.getMonth() + 1).padStart(2, '0');
      const dd = String(returnDate.getDate()).padStart(2, '0');
      const formattedReturnDate = `${yyyy}-${mm}-${dd}`;

      // Update the return date in the same row
      departureInput.closest('tr').find('.return-date').val(formattedReturnDate);
    }
  });
</script>

<!-- AJAX for flight Submition manual -->
<script>
  $(document).ready(function () {
    $('#manualFlightForm').on('submit', function (e) {
      e.preventDefault(); // Prevent default form submission

      const form = $(this);
      const serializedData = form.serialize();

      // 🔍 Show serialized data
      console.log('📦 Serialized Data (query string):', serializedData);

      // 🔍 Convert to key-value pairs for easier reading
      const formDataObj = {};
      form.serializeArray().forEach(function (item) {
        if (!formDataObj[item.name]) {
          formDataObj[item.name] = item.value;
        } else {
          // If it's already an array, push
          if (!Array.isArray(formDataObj[item.name])) {
            formDataObj[item.name] = [formDataObj[item.name]];
          }
          formDataObj[item.name].push(item.value);
        }
      });

      // 📝 Log expanded key-value data
      console.log('📤 Data to be sent (expanded):', formDataObj);

      $.ajax({
        url: form.attr('action'),
        type: form.attr('method'),
        data: serializedData,
        beforeSend: function () {
          console.log('🚀 Sending AJAX request to:', form.attr('action'));
        },
        success: function (response) {
          console.log('✅ Server responded with:', response);

          let json;
          try {
            json = typeof response === 'string' ? JSON.parse(response) : response;
          } catch (e) {
            console.warn('⚠️ Could not parse JSON response:', response);
            alert('Unexpected server response.');
            return;
          }

          if (json.status === 'success') {
            alert(json.message);
            console.log('🟢 Success:', json.message);
          } else {
            alert('⚠️ Server error: ' + json.message);
            console.warn('🔍 Details:', json.details);
          }
        },
        error: function (xhr, status, error) {
          console.error('❌ AJAX request failed.');
          console.log('Status:', status);
          console.log('Error:', error);
          console.log('Response:', xhr.responseText);
          alert('An AJAX error occurred: ' + error);
        }
      });
    });
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('flightUploadForm');

    form.addEventListener('submit', function (e) {
      e.preventDefault(); // prevent normal form submission

      const formData = new FormData(form);

      fetch(form.action, {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          // Optionally, reload page or redirect after successful upload:
          window.location.href = '../Employee Section/emp-flightList.php';
        })
        .catch(() => {
          alert('❌ Something went wrong during upload.');
        });
    });
  });
</script>
