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
                      ag.agentCode AS `Agent Code`,
                      ag.agentId AS `Agent ID`,
                      CONCAT(ag.lName, ', ', ag.fName, ' ', 
                            CASE WHEN ag.mName = 'N/A' OR ag.mName IS NULL 
                            THEN '' ELSE CONCAT(SUBSTRING(ag.mName, 1, 1), '.') END) AS `Name`,
                      a.email AS `Email`,
                      a.password AS `Password`,
                      CONCAT(ag.countryCode, ' ', ag.contactNo) AS `Contact No.`,
                      ag.agentType AS `Agent Type`,
                      ag.agentRole AS `Agent Role`,
                      a.accountStatus AS `Status`
                      FROM accounts a
                      LEFT JOIN agent ag ON a.accountId = ag.accountId
                      WHERE a.accountType = 'guest'
                      ORDER BY ag.agentCode ASC, a.accountId ASC";  // Prioritizing Agent Code (A001, A002)

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
                            <td>{$row['Agent Code']}</td>
                            <td>{$row['Agent ID']}</td>
                            <td>{$row['Name']}</td>
                            <td>{$row['Email']}</td>
                            <td>***********</td>
                            <td>{$row['Contact No.']}</td>
                            <td>{$row['Agent Type']}</td>
                            <td class='agentRole'>{$row['Agent Role']}</td>
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

<!-- DataTables #product-table -->
<script>
    $(document).ready(function() {
      const table = $('#product-table').DataTable({
        dom: 'rtip', // Use only the relevant table elements
        language: {
          emptyTable: "No Transaction Records Available"
        },
        order: [
          [0, 'desc']
        ], // Default sorting by Transaction ID (descending)
        scrollX: false,
        scrollY: '68.7vh', // Set a fixed height for the table (adjust as necessary)
        paging: true, // Enable pagination
        pageLength: 13, // Set the number of rows per page
        autoWidth: false,
        autoHeight: false, // Prevent automatic height adjustment

        // Disable sorting for specific columns
        columnDefs: [{
          targets: [1, 2, 3, 5, 6, ], // Disable sorting for 2nd and 4th columns
          orderable: false
        }]
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