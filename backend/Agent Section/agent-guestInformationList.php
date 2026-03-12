<?php
session_start();
require "../conn.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title></title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

    <?php include "../Agent Section/includes/sidebar.php"; ?>

    <div class="main-container">

      <div class="navbar">
        <div class="page-header-wrapper">

          <!-- <div class="page-header-top">
            <div class="back-btn-wrapper">
              <button class="back-btn" id="redirect-btn">
                <i class="fas fa-chevron-left"></i>
              </button> 
            </div>
          </div> -->

          <div class="page-header-content">
            <div class="page-header-text">
              <h5 class="header-title">Guest List</h5>
            </div>
          </div>

        </div>
      </div>

      <?php
        $statusTab = isset($_GET['status']) ? $_GET['status'] : '';
      ?>

      <div class="main-content">
        <div class="table-wrapper">

          <!-- Filter Inputs -->
          <div class="table-header">
            <div class="search-wrapper">
              <div class="search-input-wrapper">
                <input type="text" id="search" placeholder="Search here..">
              </div>
            </div>

            <div class="second-header-wrapper">
              <div class="date-range-wrapper flightbooking-wrapper">
                <div class="date-range-inputs-wrapper">
                  <div class="input-with-icon">
                    <input type="text" class="datepicker" id="FlightStartDate" placeholder="Flight Date" readonly>
                    <i class="fas fa-calendar-alt calendar-icon"></i>
                  </div>
                </div>
              </div>

              <div class="buttons-wrapper">
                <button id="clearSorting" class="btn btn-secondary">
                  Clear
                </button>
              </div>
            </div>
          </div>

          <!-- Table  -->
          <div class="table-container">
            <table id="product-table" class="table product-table">
              <thead>
                <tr>
                  <th>TRANSACTION NO</th>
                  <th>AGE</th>
                  <th>GIVEN NAME</th>
                  <th>SURNAME</th>
                  <th>FULLNAME</th>
                  <th>DOB</th>
                  <th>NAT</th>
                  <th>PASSPORT</th>
                  <th>D of E</th>
                  <th>SEX</th>
                  <th>DEPARTURE DATE</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  if ($agentRole != 'Head Agent')
                  {
                    $sql1 = "SELECT g.guestId, g.transactNo, g.fName, g.mName, g.lName, g.suffix, g.birthdate, g.age, g.sex, g.nationality, 
                              g.passportNo, g.passportExp, f.flightDepartureDate
                            FROM `guest` g
                            JOIN `booking` b ON g.transactNo = b.transactNo
                            JOIN `flight` f ON b.flightId = f.flightId
                            WHERE b.accountId = $accountId
                            ORDER BY f.flightDepartureDate ASC";

                    // Execute the query
                    $result1 = $conn->query($sql1);

                    // Check if query execution was successful
                    if (!$result1) 
                    {
                      die("Query error: " . $conn->error);
                    }

                    // Fetch results and display rows
                    if ($result1->num_rows > 0) 
                    {
                      while ($row = $result1->fetch_assoc()) 
                      {
                        if ($row['suffix'] === 'N/A')
                        {
                          $row['suffix'] = '';
                        }

                        // Sanitize and format guest name
                        $guestName = $row['fName'] . ' ' . $row['suffix'] . ' ' . $row['lName'];

                        // Format dates
                        $birthdate = !empty($row['birthdate']) ? date('Y M d', strtotime($row['birthdate'])) : 'N/A';
                        $departureDate = !empty($row['flightDepartureDate']) ? date('Y-m-d', strtotime($row['flightDepartureDate'])) : 'N/A';

                        echo "<tr>
                                <td>" . $row['transactNo'] . "</td>
                                <td>" . $row['age'] . "</td>
                                <td>" . ($row['fName'] ?? '') . ' ' . ($row['suffix'] ?? '') . "</td>
                                <td>" . $row['lName'] . "</td>
                                <td>" . $guestName . "</td>
                                <td>" . $birthdate . "</td>
                                <td>" . $row['nationality'] . "</td>
                                <td>" . $row['passportNo'] . "</td>
                                <td>" . $row['passportExp'] . "</td>
                                <td>" . $row['sex'] . "</td>
                                <td>" . $departureDate . "</td>
                              </tr>";
                      }
                    }
                  }
                  else
                  {
                    $sql1 = "SELECT g.guestId, g.transactNo, g.fName, g.mName, g.lName, g.suffix, g.birthdate, g.age, g.sex, g.nationality, 
                              g.passportNo, g.passportExp, f.flightDepartureDate
                            FROM `guest` g
                            JOIN `booking` b ON g.transactNo = b.transactNo
                            JOIN `flight` f ON b.flightId = f.flightId
                            WHERE b.agentCode = '$agentCode'
                            ORDER BY f.flightDepartureDate ASC";

                    // Execute the query
                    $result1 = $conn->query($sql1);

                    // Check if query execution was successful
                    if (!$result1) 
                    {
                      die("Query error: " . $conn->error);
                    }

                    // Fetch results and display rows
                    if ($result1->num_rows > 0) 
                    {
                      while ($row = $result1->fetch_assoc()) 
                      {
                        if ($row['suffix'] === 'N/A')
                        {
                          $row['suffix'] = '';
                        }

                        // Sanitize and format guest name
                        $guestName = $row['fName'] . ' ' . $row['suffix'] . ' ' . $row['lName'];

                        // Format dates
                        $birthdate = !empty($row['birthdate']) ? date('Y M d', strtotime($row['birthdate'])) : 'N/A';
                        $departureDate = !empty($row['flightDepartureDate']) ? date('Y-m-d', strtotime($row['flightDepartureDate'])) : 'N/A';

                        echo "<tr>
                                <td>" . $row['transactNo'] . "</td>
                                <td>" . $row['age'] . "</td>
                                <td>" . ($row['fName'] ?? '') . ' ' . ($row['suffix'] ?? '') . "</td>
                                <td>" . $row['lName'] . "</td>
                                <td>" . $guestName . "</td>
                                <td>" . $birthdate . "</td>
                                <td>" . $row['nationality'] . "</td>
                                <td>" . $row['passportNo'] . "</td>
                                <td>" . $row['passportExp'] . "</td>
                                <td>" . $row['sex'] . "</td>
                                <td>" . $departureDate . "</td>
                              </tr>";
                      }
                    }
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



  <?php require "../Agent Section/includes/scripts.php"; ?>

  

  <!-- Data tables script -->
  <script>
    $(document).ready(function () {
      $('#product-table').DataTable({
        "pageLength": 10,
        "lengthChange": true,
        "searching": false,
        "ordering": true,
        "order": [[10, 'asc']],  // Sort by "Departure Date"
        "columnDefs": [
          { "orderable": false, "targets": [2, 4] }, // Disable sort for Given Name & Fullname
          { "width": "120px", "targets": 0 }, // Transaction No
          { "width": "50px", "targets": 1 },  // Age
          { "width": "120px", "targets": 2 }, // Given Name
          { "width": "120px", "targets": 3 }, // Surname
          { "width": "180px", "targets": 4 }, // Fullname
          { "width": "100px", "targets": 5 }, // DOB
          { "width": "80px", "targets": 6 },  // Nationality
          { "width": "130px", "targets": 7 }, // Passport
          { "width": "100px", "targets": 8 }, // D of E
          { "width": "50px", "targets": 9 },  // Sex
          { "width": "130px", "targets": 10 } // Departure Date
        ],
        "autoWidth": false // Important to prevent DataTables from overriding widths
      });
    });
  </script>

</body>

</html>