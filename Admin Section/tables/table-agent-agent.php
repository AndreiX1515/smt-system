<div class="table-container">
    <table id="product-table-2" class="product-table">
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
                      WHERE a.accountType = 'agent'
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
        const table2 = $('#produc-table-2').DataTable({
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
                targets: [1, 2, 3, 5, 6], // Disable sorting for specified columns
                orderable: false
            }]
        });

        // Search Functionality
        $('#search').on('keyup', function() {
            table2.search(this.value).draw();
        });

        // Update the custom pagination buttons and page info
        function updatePagination() {
            const info = table2.page.info();
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
            table2.page('previous').draw('page');
            updatePagination();
        });

        $('#nextPage').on('click', function() {
            table2.page('next').draw('page');
            updatePagination();
        });

        // Initialize pagination on first load
        updatePagination();

        // Status Filter
        $('#status').on('change', function() {
            const selectedStatus = $(this).val();
            table2.column(8).search(selectedStatus || '').draw();
        });

        // Package Filter
        $('#packages').on('change', function() {
            const selectedPackage = $(this).val();
            table2.column(3).search(selectedPackage || '').draw();
        });

        // Booking Date Filter with value change
        $('#BookingStartDate').on('change', function() {
            const selectedBookingDate = $(this).val();
            table2.column(4).search(selectedBookingDate || '').draw();
        });

        // Flight Date Filter with value change
        $('#FlightStartDate').on('change', function() {
            const selectedFlightDate = $(this).val();
            table2.column(5).search(selectedFlightDate || '').draw();
        });

        // Apply datepicker for FlightStartDate
        $("#FlightStartDate").datepicker({
            dateFormat: "mm-dd-yy",
            showAnim: "fadeIn",
            changeMonth: true,
            changeYear: true,
            yearRange: "1900:2100",
            onSelect: function(dateText) {
                table2.column(5).search(dateText || '').draw();
            }
        });

        // Apply datepicker for BookingStartDate
        $("#BookingStartDate").datepicker({
            dateFormat: "mm-dd-yy",
            showAnim: "fadeIn",
            changeMonth: true,
            changeYear: true,
            yearRange: "1900:2100",
            onSelect: function(dateText) {
                table2.column(4).search(dateText || '').draw();
            }
        });

        // Clear All Filters
        $('#clearSorting').on('click', function() {
            $('#search').val('');
            table2.search('').draw();

            $('#status').val('All').change();
            $('#packages').val('All').change();

            $('#BookingStartDate').val('').trigger('change');
            $('#FlightStartDate').val('').trigger('change');

            table2.draw();
        });
    });
</script>
