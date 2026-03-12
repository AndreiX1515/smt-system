<?php
session_start();
require "../conn.php"; // Move up to the parent directory

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <title>Employee - Dashboard</title>
  <?php include '../Employee Section/includes/emp-head.php' ?>
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-dashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">
</head>

<body>

  <?php include '../Employee Section/includes/emp-sidebar.php' ?>

  <!-- Main Container -->
  <div class="main-container">

    <div class="navbar">

      <div class="page-header-wrapper">

        <div class="page-header-content">
          <div class="page-header-text">
            <h5 class="header-title">Dashboard</h5>
          </div>
        </div>

      </div>
    </div>

    <?php include '../Agent Section/functions/exchange-rate.php' ?>

    <div class="main-content">

      <!-- Cards Count 1st Row -->
      <div class="header-counts">

        <!-- Card 1 -->
        <div class="card">
          <div class="counts-header">
            <div class="title-wrapper">
              <h6 class="">Active Transaction</h6>
            </div>
          </div>

          <div class="card-content card-content-body">

            <!-- Total and Confirmed Transaction Count -->
            <div class="row">

              <div class="col-md-5 clickable-card" onclick="redirectToTransactionStatus('current')">

                <div class="card-icon icon-orange">
                  <i class="fas fa-exchange-alt"></i>
                </div>

                <div class="side-content">
                  <?php
                  $totalTransactionsQuery = "SELECT COUNT(*) AS total FROM booking b 
                                              JOIN flight f ON b.flightId = f.flightId
                                              WHERE f.flightDepartureDate >= CURDATE()";
                  $result = mysqli_query($conn, $totalTransactionsQuery);

                  if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $totalTransactions = $row['total'];
                  } else {
                    $totalTransactions = 0;
                  }
                  ?>
                  <h5><?php echo $totalTransactions; ?></h5>
                  <p class="total-text">TOTAL TRANSACTIONS</p>
                </div>
              </div>

              <!-- Confirmed Transactions -->
              <div class="col-md-5 clickable-card" onclick="redirectToTransactionStatus('Confirmed')">
                <div class="card-icon icon-green">
                  <i class="fas fa-check-circle"></i>
                </div>

                <div class="side-content d-flex flex-column">
                  <?php
                  $confirmedTransactionsQuery = "SELECT COUNT(*) AS total FROM booking b
                                                  JOIN flight f ON b.flightId = f.flightId
                                                  WHERE f.flightDepartureDate >= CURDATE() AND b.status = 'Confirmed'";
                  $result = mysqli_query($conn, $confirmedTransactionsQuery);

                  if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $confirmedTransactions = $row['total'];
                  } else {
                    $confirmedTransactions = 0;
                  }
                  ?>
                  <h5><?php echo $confirmedTransactions; ?></h5>
                  <p>CONFIRMED</p>
                </div>
              </div>

            </div>

            <!-- Pending, Reserved, and Cancelled Transaction Count -->
            <div class="row">

              <!-- Pending Transaction Count -->
              <div class="col-md-4 clickable-card" onclick="redirectToTransactionStatus('Pending')">

                <div class="card-icon icon-yellow">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>

                <div class="side-content d-flex flex-column">
                  <?php
                  $pendingTransactionsQuery = "SELECT COUNT(*) AS total FROM booking b
                                                JOIN flight f ON b.flightId = f.flightId
                                                WHERE f.flightDepartureDate >= CURDATE() AND b.status = 'Pending'";
                  $result = mysqli_query($conn, $pendingTransactionsQuery);

                  if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $pendingTransactions = $row['total'];
                  } else {
                    $pendingTransactions = 0;
                  }
                  ?>
                  <h5><?php echo $pendingTransactions; ?></h5>
                  <p>PENDING</p>
                </div>

              </div>

              <!-- Reserved Transaction Count -->
              <div class="col-md-4 clickable-card" onclick="redirectToTransactionStatus('Reserved')">
                <div class="card-icon bg-secondary">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="side-content d-flex flex-column">
                  <?php
                  $pendingTransactionsQuery = "SELECT COUNT(*) AS total FROM booking b
                                                JOIN flight f ON b.flightId = f.flightId
                                                WHERE f.flightDepartureDate >= CURDATE() AND b.status = 'Reserved'";
                  $result = mysqli_query($conn, $pendingTransactionsQuery);

                  if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $pendingTransactions = $row['total'];
                  } else {
                    $pendingTransactions = 0;
                  }
                  ?>
                  <h5><?php echo $pendingTransactions; ?></h5>
                  <p>RESERVED</p>
                </div>
              </div>

              <!-- Cancelled Transaction Count -->
              <div class="col-md-4 clickable-card card-cancelled" onclick="redirectToTransactionStatus('Cancelled')">
                <div class="card-icon icon-red">
                  <i class="fas fa-times-circle"></i>
                </div>
                <div class="side-content d-flex flex-column">
                  <?php
                  $cancelledTransactionsQuery = "SELECT COUNT(*) AS total FROM booking b
                                                  JOIN flight f ON b.flightId = f.flightId
                                                  WHERE f.flightDepartureDate >= CURDATE() AND b.status = 'Cancelled'";
                  $result = mysqli_query($conn, $cancelledTransactionsQuery);

                  if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $cancelledTransactions = $row['total'];
                  } else {
                    $cancelledTransactions = 0;
                  }
                  ?>
                  <h5><?php echo $cancelledTransactions; ?></h5>
                  <p>CANCELLED</p>
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- Card 2 -->
        <div class="card card-top">
          <div class="counts-header">
            <div class="title-wrapper">
              <h6 class="">On Due</h6>
            </div>

          </div>

          <div class="card-content card-content-body">
            <!-- 5 Days and 15 Days Due Count -->
            <div class="row">
              <!-- 5 Days Due Count -->
              <div class="col-md-5 clickable-card" onclick="redirectToTransactionOnDue('5days')">
                <div class="card-icon icon-red">
                  <i class="fas fa-calendar-check"></i>
                </div>

                <div class="side-content d-flex flex-column">
                  <?php
                    // Assuming $conn is your database connection
                    $days5Query = "SELECT COUNT(*) AS `bookingsDueIn5Days` FROM booking b 
                                    JOIN flight f ON b.flightId = f.flightId
                                    LEFT JOIN (SELECT transactNo, SUM(CASE WHEN paymentStatus = 'Approved' THEN amount ELSE 0 END) 
                                      AS totalPaid FROM payment GROUP BY transactNo) p ON b.transactNo = p.transactNo
                                    WHERE DATEDIFF(f.flightDepartureDate, CURDATE()) <= 5 AND DATEDIFF(f.flightDepartureDate, CURDATE()) >= 0
                                      AND (b.totalPrice > IFNULL(p.totalPaid, 0)) AND (b.status='Confirmed' OR b.status='Reserved')";

                    $result = $conn->query($days5Query);

                    // Check if the query returned a result
                    if ($result->num_rows > 0) {
                      $row = $result->fetch_assoc();
                      $bookingsDueIn5Days = $row['bookingsDueIn5Days'];
                    } else {
                      $bookingsDueIn5Days = 0;  // Default to 0 if no records found
                    }
                  ?>
                  <h5><?php echo $bookingsDueIn5Days; ?></h5>
                  <p>5 DAYS BEFORE FLIGHT</p>
                </div>
              </div>

              <!-- 15 Days Due Count -->
              <div class="col-md-5 clickable-card" onclick="redirectToTransactionOnDue('10days')">

                <div class="card-icon icon-red">
                  <i class="fas fa-calendar-check"></i>
                </div>

                <div class="side-content d-flex flex-column">
                  <?php
                    // Assuming $conn is your database connection
                    $days15Query = "SELECT COUNT(*) AS bookingsDueIn15Days FROM booking b
                                      JOIN flight f ON b.flightId = f.flightId
                                      LEFT JOIN (SELECT transactNo, SUM(CASE WHEN paymentStatus = 'Approved' THEN amount ELSE 0 END) 
                                      AS totalPaid FROM payment GROUP BY transactNo) p ON b.transactNo = p.transactNo
                                      WHERE DATEDIFF(f.flightDepartureDate, CURDATE()) BETWEEN 6 AND 15
                                        AND (b.totalPrice > IFNULL(p.totalPaid, 0)) AND (b.status='Confirmed' OR b.status='Reserved')";

                    $result = $conn->query($days15Query);

                    // Check if the query returned a result
                    if ($result->num_rows > 0) {
                      $row = $result->fetch_assoc();
                      $bookingsDueIn15Days = $row['bookingsDueIn15Days'];
                    } else {
                      $bookingsDueIn15Days = 0;  // Default to 0 if no records found
                    }
                  ?>
                  <h5><?php echo $bookingsDueIn15Days; ?></h5>
                  <p>15 DAYS BEFORE FLIGHT</p>
                </div>
              </div>
            </div>

            <!-- 30 Days and more than 30 Days Due Count -->
            <div class="row">

              <!-- 30 Days Due Count -->
              <div class="col-md-5 clickable-card" onclick="redirectToTransactionOnDue('20days')">

                <div class="card-icon icon-red">
                  <i class="fas fa-calendar-check"></i>
                </div>

                <div class="side-content d-flex flex-column">
                  <?php
                    // Assuming $conn is your database connection
                    $days30Query = "SELECT COUNT(*) AS `bookingsDueIn30Days` FROM booking b 
                                      JOIN flight f ON b.flightId = f.flightId
                                      LEFT JOIN (SELECT transactNo, SUM(CASE WHEN paymentStatus = 'Approved' THEN amount ELSE 0 END) 
                                        AS totalPaid FROM payment GROUP BY transactNo) p 
                                      ON b.transactNo = p.transactNo
                                      WHERE DATEDIFF(f.flightDepartureDate, CURDATE()) BETWEEN 15 AND 30
                                        AND (b.totalPrice > IFNULL(p.totalPaid, 0)) AND (b.status='Confirmed' OR b.status='Reserved')";

                    $result = $conn->query($days30Query);

                    // Check if the query returned a result
                    if ($result->num_rows > 0) {
                      $row = $result->fetch_assoc();
                      $bookingsDueIn30Days = $row['bookingsDueIn30Days'];
                    } else {
                      $bookingsDueIn30Days = 0;  // Default to 0 if no records found
                    }
                  ?>
                  <h5><?php echo $bookingsDueIn30Days; ?></h5>
                  <p>30 DAYS BEFORE FLIGHT</p>
                </div>

              </div>

              <!-- more than 30 Days Due Count -->
              <div class="col-md-5 clickable-card" onclick="redirectToTransactionOnDue('30daysplus')">

                <div class="card-icon icon-red">
                  <i class="fas fa-calendar-check"></i>
                </div>


                <div class="side-content d-flex flex-column">
                  <?php
                    // Assuming $conn is your database connection
                    $daysMoreThan30Query = "SELECT COUNT(*) AS `bookingsOver30DaysAfterFlight` FROM booking b 
                                      JOIN flight f ON b.flightId = f.flightId
                                      LEFT JOIN (SELECT transactNo, SUM(CASE WHEN paymentStatus = 'Approved' THEN amount ELSE 0 END) 
                                      AS totalPaid FROM payment GROUP BY transactNo) p ON b.transactNo = p.transactNo
                                      WHERE DATEDIFF(CURDATE(), f.flightDepartureDate) > 30
                                        AND (b.totalPrice > IFNULL(p.totalPaid, 0)) AND (b.status='Confirmed' OR b.status='Reserved')";

                    $result = $conn->query($daysMoreThan30Query);

                    // Check if the query returned a result
                    if ($result->num_rows > 0) {
                      $row = $result->fetch_assoc();
                      $bookingsDueInMoreThan30Days = $row['bookingsOver30DaysAfterFlight'];
                    } else {
                      $bookingsDueInMoreThan30Days = 0;  // Default to 0 if no records found
                    }
                  ?>
                  <h5><?php echo $bookingsDueInMoreThan30Days; ?></h5>
                  <p>MORE THAN A MONTH</p>
                </div>

              </div>

            </div>

          </div>
        </div>

        <!-- Card 3 -->
        <div class="card card-top">
          <div class="counts-header">
            <div class="title-wrapper">
              <h6 class="">Total Sales</h6>
            </div>

          </div>

          <div class="card-content card-content-body">
            <div class="row">
              <div class="col-md-5 d-flex flex-row total-sales">
                <div class="card-icon icon-blue">
                  <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="side-content d-flex flex-column">
                  <?php
                  $currentMonthQuery = "SELECT SUM(b.totalPrice + IFNULL(r.requestCost, 0)) AS totalSales
                                            FROM booking b
                                            LEFT JOIN request r 
                                              ON r.transactNo = b.transactNo 
                                              AND r.requestStatus = 'Confirmed'
                                              AND MONTH(r.requestDate) = MONTH(CURRENT_DATE)
                                              AND YEAR(r.requestDate) = YEAR(CURRENT_DATE)
                                            WHERE 
                                              b.status = 'Confirmed'
                                              AND MONTH(b.bookingDate) = MONTH(CURRENT_DATE)
                                              AND YEAR(b.bookingDate) = YEAR(CURRENT_DATE)";

                  // Execute the query
                  $currentMonthResult = $conn->query($currentMonthQuery);

                  // Fetch result and handle nulls
                  $currentMonthTotal = '0.00'; // Default value
                  if ($currentMonthResult && $currentMonthResult->num_rows > 0) {
                    $currentMonthRow = $currentMonthResult->fetch_assoc();
                    $currentMonthTotal = isset($currentMonthRow['totalSales'])
                      ? number_format((float) $currentMonthRow['totalSales'], 2)
                      : '0.00';
                  }
                  ?>
                  <h5 class="month-sales">₱ <?php echo $currentMonthTotal; ?></h5>
                  <p>CURRENT MONTH</p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5 d-flex flex-row total-sales">
                <div class="card-icon icon-red">
                  <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="side-content d-flex flex-column">
                  <?php
                  $pastMonthQuery = "SELECT SUM(b.totalPrice + IFNULL(r.requestCost, 0)) AS totalSales
                                        FROM booking b
                                        LEFT JOIN request r ON r.transactNo = b.transactNo 
                                          AND r.requestStatus = 'Confirmed'
                                          AND MONTH(r.requestDate) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
                                          AND YEAR(r.requestDate) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
                                        WHERE 
                                          b.status = 'Confirmed'
                                          AND MONTH(b.bookingDate) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
                                          AND YEAR(b.bookingDate) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)";

                  // Execute the query
                  $pastMonthResult = $conn->query($pastMonthQuery);

                  // Fetch result and handle nulls
                  $pastMonthTotal = '0.00'; // Default value
                  if ($pastMonthResult && $pastMonthResult->num_rows > 0) {
                    $pastMonthRow = $pastMonthResult->fetch_assoc();
                    $pastMonthTotal = isset($pastMonthRow['totalSales'])
                      ? number_format((float) $pastMonthRow['totalSales'], 2)
                      : '0.00';
                  }
                  ?>
                  <h5 class="month-sales">₱ <?php echo $pastMonthTotal; ?></h5>
                  <p>PAST MONTH</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 4 -->
        <div class="card card-4">

          <div class="counts-header">
            <div class="title-wrapper">
              <h6 class="">Currency History</h6>
            </div>

            <div class="button-wrapper">
              <button class="btn btn-primary view-currency-btn" id="addCurrencyBtn"
                onclick="window.location.href='../Employee Section/emp-currencyHistory.php';">
                View History
              </button>
            </div>
          </div>

          <div class="card-content card-content-body">

            <div class="row currency-row">

              <!-- USD Section -->
              <div class="currency-card usd-card-wrapper">
                <div class="flag-icon-wrapper">
                  <img src="../Assets/Flags/english-flag.png" alt="US Flag">
                  <div class="currency-text-wrapper">
                    <h5 class="currency-value">$ 1</h5>
                    <p class="currency-label">US DOLLAR</p>
                  </div>
                </div>
              </div>

              <!-- Exchange Icon -->
              <div class="icon-container">
                <div class="icon-wrapper-currency">
                  <i class="fas fa-exchange-alt"></i>
                </div>
              </div>

              <!-- PHP-KR Section -->
              <div class="php-kr-card-wrapper">

                <div class="card-php-kr">
                  <div class="card-icon kr-icon-wrapper">
                    <div class="flag-icon-wrapper">
                      <img width="30px" height="30px" src="../Assets/Flags/korean-flag.png" alt="">
                    </div>
                  </div>

                  <div class="side-content d-flex flex-column">
                    <h5 class="currency-text">₩ <?php echo number_format($usd_to_krw, 0); ?> </h5>
                    <p>KOREAN WON</p>
                  </div>
                </div>

                <div class="card-php-kr">
                  <div class="card-icon">
                    <div class="flag-icon-wrapper">
                      <img width="30px" height="30px" src="../Assets/Flags/philippines (2).png" alt="">
                    </div>
                  </div>

                  <div class="side-content d-flex flex-column">
                    <h5 class="currency-text">₱ <?php echo number_format($usd_to_php, 2); ?></h5>
                    <p>PHILIPPINE PESO</p>
                  </div>

                </div>
              </div>

            </div>

          </div>

        </div>

      </div>

      <div class="second-div">

        <div class="navTabs-wrapper">
          <ul class="nav nav-pills" id="pills-tab" role="tablist">

            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-profile-tab" data-bs-toggle="pill"
                data-bs-target="#pills-profile" type="button" role="tab" aria-controls="pills-profile"
                aria-selected="false">Flight Seat
                Tracker</button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#pills-home"
                type="button" role="tab" aria-controls="pills-home" aria-selected="true">Booking and Requests</button>
            </li>
          </ul>
        </div>

        <div class="content-heading">
          <button class="btn btn-primary saveBtn" id="saveChanges">Save</button>
        </div>

      </div>

      <!-- Flight Seat Tracker Tab -->
      <div class="tab-content" id="pills-tabContent">

        <!-- Flight Seat Tracker Tab -->
        <div class="tab-pane fade show active" id="pills-profile" role="tabpanel" aria-labelledby="pills-profile-tab"
          tabindex="0">

          <div class="info-table-wrapper">
            <!-- Flight Seat Tracker Table -->
            <div class="table-wrapper info-table-container">

              <table class="table info-table" id="info-table">
                <thead>
                  <tr class="first-half">
                    <th rowspan="2" class="red-white"></th>
                    <th rowspan="2" class="red-white">TEAM OP</th>
                    <th rowspan="2" class="red-white">ORIGIN</th>
                    <th colspan="2" class="red-white">FLIGHT DATE</th>
                    <th rowspan="2" class="red-white" style="font-size: 10px;">AVAILABLE SEATS</th>
                    <th rowspan="2" class="red-white" style="font-size: 10px;">ADDITIONAL SEATS</th>
                    <th rowspan="2" class="red-white">AIR + LAND</th>
                    <th rowspan="2" class="red-white">LAND ONLY</th>
                    <th rowspan="2" class="red-white">WHOLESALE PRICE</th>
                    <th rowspan="2" class="red-white">RETAIL PRICE</th>
                    <th rowspan="2" class="red-white">LAND PRICE</th>

                    <!-- Dynamic headers for agent columns -->
                    <?php
                    $sql = "SELECT branchName FROM branch WHERE branchAgentCode IS NOT NULL AND branchAgentCode != ''";
                    $result = $conn->query($sql);

                    while ($row = $result->fetch_assoc()) {

                    // Output each agent column header with colspan=2 for "START" and "END"
                    echo '<th colspan="2" 
                          data-bs-toggle="tooltip" 
                          title="' . htmlspecialchars($row['branchName']) . '" 
                          style="background-color: #dc3545; color: #ffffff; font-weight: 500; font-size: 12px;">' . htmlspecialchars($row['branchName']) . '</th>';
                    }
                    ?>
                  </tr>

                  <tr class="second-half">
                    <!-- Sub-headers for FLIGHT DATE -->
                    <th class="red-white" style="font-size: 10px;">START</th>
                    <th class="red-white" style="font-size: 10px;">END</th>

                    <!-- Dynamic sub-headers for agent columns -->
                    <?php
                    $sql = "SELECT branchName FROM branch WHERE branchAgentCode IS NOT NULL AND branchAgentCode != ''";
                    $result = $conn->query($sql);

                    while ($row = $result->fetch_assoc()) {
                      // Output sub-headers for each dynamic agent column
                      echo '<th style="background-color: #dc3545; color: #ffffff; font-weight: 500; font-size: 12px;">A.L</th>';
                      echo '<th style="background-color: #dc3545; color: #ffffff; font-weight: 500; font-size: 12px;">L.O</th>';
                    }
                    ?>
                  </tr>

                </thead>

                <tbody id="info-table">
                  <?php
                  $sql = "SELECT branchName, branchAgentCode 
                          FROM branch WHERE branchAgentCode IS NOT NULL AND branchAgentCode != ''";
                  $result = $conn->query($sql);

                  $agentColumns = '';

                  while ($row = $result->fetch_assoc()) {

                    $agentCode = $row['branchAgentCode'];
                    $agentColumns .= "IFNULL(SUM(CASE WHEN b.bookingType = 'Package' 
                                          AND (b.status = 'Confirmed' OR b.status = 'Reserved')
                                          AND (a.agentCode = '$agentCode' OR c.clientCode = '$agentCode') 
                                          AND (a.agentType = 'Retailer' OR c.clientType = 'Retailer')
                                          THEN b.pax ELSE 0 END), 0) AS `{$agentCode}_AL`,
                    
                                        IFNULL(SUM(CASE WHEN b.bookingType = 'Package' 
                                          AND (b.status = 'Confirmed' OR b.status = 'Reserved')
                                          AND (a.agentCode = '$agentCode' OR c.clientCode = '$agentCode')
                                          AND (a.agentType = 'Wholeseller' OR c.clientType = 'Wholeseller')
                                          THEN b.pax ELSE 0 END), 0) AS `{$agentCode}_LO`, ";
                  }

                  // Trim the trailing comma from the dynamically generated columns
                  $agentColumns = rtrim($agentColumns, ', ');

                  // Main query
                  $sql = "SELECT f.flightId, f.is_active, f.origin, f.flightDepartureDate AS Start, f.returnDepartureDate AS End,
                              CONCAT(
                              IF(e.lName IS NOT NULL AND e.lName != '', CONCAT(e.lName, ', '), ''),
                              e.fName,
                              IF(e.mName IS NOT NULL AND e.mName != '' AND e.lName IS NOT NULL AND e.lName != '', CONCAT(' ', LEFT(e.mName, 1)), '')
                              ) AS TeamOP,
                              e.colorCode, 
                              f.availSeats AS FlightSeat, 
                              
                              GREATEST(f.availSeats - IFNULL(SUM(CASE 
                                WHEN (b.status = 'Confirmed' OR b.status = 'Reserved') 
                                AND b.bookingType = 'Package' THEN b.pax ELSE 0 END), 0), 0) AS AvailSeats, 
                              IF((f.availSeats - IFNULL(SUM(CASE WHEN (b.status = 'Confirmed' OR b.status = 'Reserved') 
                                AND b.bookingType = 'Package' THEN b.pax ELSE 0 END), 0)) < 0, 
                                ABS(f.availSeats - IFNULL(SUM(CASE WHEN (b.status = 'Confirmed' OR b.status = 'Reserved') 
                                  AND b.bookingType = 'Package' THEN b.pax ELSE 0 END), 0)), 0) AS AdditionalSeats,
                              SUM(CASE WHEN (b.status = 'Confirmed' OR b.status = 'Reserved')  AND b.bookingType = 'Package' 
                                AND (a.agentType = 'Retailer' OR c.clientType = 'Retailer') THEN b.pax 
                                ELSE 0 END) AS `Air+Land`,
                              SUM(CASE WHEN (b.status = 'Confirmed' OR b.status = 'Reserved') AND b.bookingType = 'Package' 
                                AND (a.agentType = 'Wholeseller' OR c.clientType = 'Wholeseller') THEN b.pax 
                                ELSE 0 END) AS `LandOnly`,
                              f.wholesalePrice AS WholesalePrice, f.flightPrice AS RetailPrice, p.packagePrice AS LandArrangement,
                              f.landPrice AS landPrice, 
                              $agentColumns
                            FROM employee e
                            RIGHT JOIN flight f ON f.employeeId = e.employeeId
                            LEFT JOIN booking b ON b.flightId = f.flightId
                            LEFT JOIN package p ON f.packageId = p.packageId
                            LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                            LEFT JOIN client c ON b.accountType = 'Client' AND b.accountId = c.accountId
                            WHERE f.flightDepartureDate >= CURDATE()
                            GROUP BY f.flightId, f.is_active, f.origin, f.flightDepartureDate, f.returnDepartureDate, f.availSeats, 
                              f.wholesalePrice, f.flightPrice, p.packagePrice, f.landPrice, e.colorCode
                            ORDER BY f.flightDepartureDate";

                  // Step 3: Execute the query
                  $result = $conn->query($sql);

                  // Step 4: Display the results in HTML table
                  
                  // class="form-check-input"
                  if ($result->num_rows > 0) {
                    // Fetch employee data and map Names to Employee IDs
                    $employeeQuery = "SELECT employeeId, CONCAT(lName, ', ', fName) AS fullName FROM employee";
                    $employeeResult = $conn->query($employeeQuery);

                    $employeeMapping = []; // Array to store FullName => Employee ID mapping
                  
                    if ($employeeResult->num_rows > 0) {
                      while ($empRow = $employeeResult->fetch_assoc()) {
                        $employeeMapping[$empRow['fullName']] = $empRow['employeeId'];
                      }
                    }

                    while ($row = $result->fetch_assoc()) {
                      $flight_id = $row['flightId'];
                      $chkStatus = $row['is_active'];

                      $formattedStartDate = date('Y.m.d', strtotime($row['Start']));
                      $formattedEndDate = date('Y.m.d', strtotime($row['End']));

                      // Get Employee Name from TeamOP
                      $employeeName = isset($row['TeamOP']) ? trim($row['TeamOP']) : "";

                      // Use the colorCode directly from the database, defaulting to white if not found
                      $rowColor = !empty($row['colorCode']) ? $row['colorCode'] : "#FFFFFF";

                      echo '<tr>';
                      echo '<td class="fw-bold" style="font-size: 12px; background-color: ' . $rowColor . ';">
                              <input type="checkbox" class="status-checkbox row-checkbox" data-id="' . $flight_id . '" 
                                    data-status="' . $chkStatus . '" ' . ($chkStatus == 1 ? 'checked' : '') . '>
                              </td>';

                      echo '<td class="" style="font-size: 12px; white-space: nowrap; background-color: ' . htmlspecialchars($rowColor) . '; font-weight: bold;">' . htmlspecialchars($employeeName) . '</td>';

                      echo '<td>' . htmlspecialchars($row['origin']) . '</td>';
                      echo '<td>' . htmlspecialchars($formattedStartDate) . '</td>';
                      echo '<td>' . htmlspecialchars($formattedEndDate) . '</td>';
                      echo '<td>' . htmlspecialchars($row['AvailSeats']) . '</td>';
                      echo '<td>' . htmlspecialchars($row['AdditionalSeats']) . '</td>';
                      echo '<td>' . htmlspecialchars($row['Air+Land']) . '</td>';
                      echo '<td>' . htmlspecialchars($row['LandOnly']) . '</td>';
                      echo '<td>₱ ' . number_format($row['WholesalePrice'], 2) . '</td>';
                      echo '<td>₱ ' . number_format($row['RetailPrice'], 2) . '</td>';
                      echo '<td>₱ ' . number_format($row['landPrice'], 2) . '</td>';

                      foreach ($row as $key => $value) {
                        if (strpos($key, '_AL') !== false || strpos($key, '_LO') !== false) {
                          $style = ($value > 0) ? 'style="font-weight: bold;"' : 'style="font-weight: 400;"';
                          echo '<td ' . $style . '>' . htmlspecialchars($value) . '</td>';
                        }
                      }

                      echo '</tr>';
                    }
                  }
                  ?>
                </tbody>


              </table>
            </div>

            <!-- <div class="info-footer">
              <div class="item-number-select">
                <label for="rowsPerPage">Rows per page:</label>
                <div class="select-container">
                  <select id="rowsPerPage" class="select-box">
                    <option value="16">16</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                  </select>
                  <span class="arrow-down"></span>
                </div>

                <button id="clear-btn" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                  Reset
                </button>

              </div>

              <div class="pagination-controls">
                <button id="prevPage" class="pagination-btn">Previous</button>
                <span id="pageInfo" class="page-info"></span>
                <button id="nextPage" class="pagination-btn">Next</button>
              </div>
            </div> -->

          </div>
        </div>

        <!-- Payment and Requests Table -->
        <div class="tab-pane fade" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" tabindex="0">

          <div class="tab-content ">

            <div class="header-wrapper">

              <!-- Request Table -->
              <div class="request-wrapper">

                <div class="table-header">
                  <div class="title-wrapper">
                    <h6 class="">Request</h6>
                  </div>
                </div>

                <div class="table-wrapper request-table-container">
                  <table class="request-table table">
                    <thead>
                      <tr>
                        <th>TRANSACTION NO</th>
                        <th>FLIGHT DATE</th>
                        <th>REQUESTED BY: </th>
                        <th>REQUEST</th>
                        <th>PAX</th>
                        <th>AMOUNT</th>
                        <th>STATUS</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $sql1 = "SELECT r.transactNo AS `T.N`, c.concernTitle AS `Request`, 
                                  DATE_FORMAT(r.requestDate, '%m.%d.%Y') AS `Date`,
                                  r.requestStatus, b.agentCode, CONCAT(a.lName, ', ', a.fName, 
                                  IF(a.mName IS NOT NULL AND a.mName != '', CONCAT(' ', LEFT(a.mName, 1)), '')) AS agentName,
                                  DATE_FORMAT(f.flightDepartureDate, '%Y.%m.%d') AS `flightDepartureDate`, br.branchName as branchName,
                                  CASE 
                                    WHEN a.accountId IS NOT NULL 
                                      THEN CASE WHEN a.companyId IS NOT NULL THEN co.companyName ELSE br.branchName END
                                    WHEN cl.accountId IS NOT NULL 
                                      THEN CASE WHEN cl.companyId IS NOT NULL THEN cc.companyName ELSE br.branchName END
                                    ELSE 'Unknown'END AS `ACCOUNT NAME`, r.pax as pax, r.requestCost as requestCost
                                FROM request r
                                JOIN booking b ON r.transactNo = b.transactNo
                                JOIN concern c ON r.concernId = c.concernId
                                LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                                LEFT JOIN company co ON a.companyId = co.companyId
                                LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                                LEFT JOIN company cc ON cl.companyId = cc.companyId
                                JOIN flight f ON b.flightId = f.flightId
                                JOIN branch br ON b.agentCode = br.branchAgentCode
                                WHERE 
                                  r.requestStatus = 'Submitted' AND f.flightDepartureDate >= CURDATE()
                                ORDER BY 
                                  r.requestDate DESC";  // Order by request date
                        
                        $res1 = $conn->query($sql1);

                        if ($res1->num_rows > 0) {
                          while ($row = $res1->fetch_assoc()) {
                            $statusClass = '';
                            switch ($row['requestStatus']) {
                              case 'Confirmed':
                                $statusClass = 'badge bg-success'; // Green pill for "Approved"
                                break;
                              case 'Pending':
                                $statusClass = 'badge bg-primary'; // Yellow pill for "Pending"
                                break;
                              case 'Rejected':
                                $statusClass = 'badge bg-danger'; // Red pill for "Rejected"
                                break;
                              case 'Submitted':
                                $statusClass = 'badge bg-warning text-dark'; // Red pill for "Rejected"
                                break;
                              default:
                                $statusClass = 'badge bg-secondary'; // Grey pill for unknown statuses
                                break;
                            }
                            $requestCost = $row['requestCost'] ?? '0.00'; // Default to '0.00' if requestCost is null
                            $formattedRequestCost = number_format((float) $requestCost, 2);

                            // Echo table row with dynamically styled pills
                            echo "<tr>
                                    <td>{$row['T.N']}</td>
                                    <td>{$row['flightDepartureDate']}</td>
                                    <td>{$row['branchName']}</td>
                                    <td>{$row['Request']}</td>
                                    <td>{$row['pax']}</td>
                                    <td>₱ $formattedRequestCost</td>
                                    <td><span class='{$statusClass} p-2'>{$row['requestStatus']}</span></td>
                                  </tr>";
                          }
                        } else {
                          echo "<tr><td colspan='7' style='text-align: center; font-size: 10px;'>NO CURRENT REQUEST AS OF THE MOMENT</td></tr>";
                        }
                      ?>
                    </tbody>
                  </table>
                </div>

              </div>

              <!-- Payment Table -->
              <div class="payment-wrapper">

                <div class="table-header">
                  <div class="title-wrapper">
                    <h6 class="">Booking</h6>
                  </div>
                </div>

                <div class="table-wrapper payment-table-container">
                  <table class="payment-table table">
                    <thead>
                      <tr>
                        <th>TRANSACTION NO</th>
                        <th>FLIGHT DATE</th>
                        <th>PAID BY</th>
                        <th>PAYMENT TYPE</th>
                        <th>PAYMENT AMOUNT</th>
                        <!-- <th>DATE</th>  -->
                        <th>STATUS</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $sql2 = "SELECT p.transactNo AS `Transaction No`, p.paymentTitle AS `Payment Title`,
                                  CONCAT(FORMAT(p.amount, 2)) AS `Amount`, DATE_FORMAT(p.paymentDate, '%m.%d.%Y') AS `Date`, 
                                  p.paymentType AS `Payment Type`, p.paymentStatus, b.agentCode, CONCAT(a.lName, ', ', a.fName, 
                                  IF(a.mName IS NOT NULL AND a.mName != '', CONCAT(' ', LEFT(a.mName, 1)), '')) AS agentName,
                                  DATE_FORMAT(f.flightDepartureDate, '%Y.%m.%d') AS `flightDepartureDate`, br.branchName as branchName,
                                  CASE 
                                    WHEN a.accountId IS NOT NULL 
                                      THEN CASE WHEN a.companyId IS NOT NULL THEN c.companyName ELSE br.branchName END
                                    WHEN cl.accountId IS NOT NULL 
                                      THEN CASE WHEN cl.companyId IS NOT NULL THEN cc.companyName ELSE br.branchName END
                                    ELSE 'Unknown'END AS `ACCOUNT NAME`
                                FROM payment p
                                JOIN booking b ON p.transactNo = b.transactNo
                                LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                                LEFT JOIN company c ON a.companyId = c.companyId
                                LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                                LEFT JOIN company cc ON cl.companyId = cc.companyId
                                JOIN flight f ON b.flightId = f.flightId
                                JOIN branch br ON b.agentCode = br.branchAgentCode
                                WHERE p.paymentStatus = 'Submitted'
                                ORDER BY p.paymentDate DESC";  // Order by payment date
                      
                      $res2 = $conn->query($sql2);

                      if ($res2->num_rows > 0) {
                        while ($row = $res2->fetch_assoc()) {
                          // Map paymentStatus to Bootstrap pill classes
                          $statusClass = '';
                          switch ($row['paymentStatus']) {
                            case 'Approved':
                              $statusClass = 'badge bg-success text-light'; // Green pill for "Paid"
                              break;
                            case 'Pending':
                              $statusClass = 'badge bg-warning text-dark'; // Yellow pill for "Pending"
                              break;
                            case 'Submitted':
                              $statusClass = 'badge bg-warning text-dark'; // Red pill for "Failed"
                              break;
                            default:
                              $statusClass = 'badge bg-secondary'; // Grey pill for unknown statuses
                              break;
                          }

                          // Echo table row with dynamically styled pills
                          echo "<tr>
                                    <td>{$row['Transaction No']}</td>
                                    <td>{$row['flightDepartureDate']}</td>
                                    <td>{$row['branchName']}</td>
                                    <td>{$row['Payment Type']}</td>
                                    <td>₱ {$row['Amount']}</td>
                                    <td><span class='{$statusClass} p-2'>{$row['paymentStatus']}</span></td>
                                  </tr>";
                        }
                      } else {
                        echo "<tr><td colspan='12' style='text-align: center; font-size: 10px;'>NO CURRENT PAYMENTS AS OF THE MOMENT</td></tr>";
                      }
                      ?>
                    </tbody>
                  </table>
                </div>

              </div>

            </div>

            <!-- Confirm Transaction Table -->
            <?php
              // Function to render the confirmed transactions table
              function renderConfirmedTransactionsTable($conn) {
                // Query to get confirmed transactions
                $query1 = "SELECT b.*, f.flightDepartureDate AS Start, p.packageName, b.totalPrice AS PackagePrice, 
                  f.returnDepartureDate AS End, CONCAT(a.lName, ', ', a.fName, 
                  IF(a.mName IS NOT NULL AND a.mName != '', CONCAT(' ', LEFT(a.mName, 1)), '')) AS agentName,
                  br.branchName as branchName, SUM(pa.amount) AS TotalAmountPaid, 
                  COALESCE(SUM(r.requestCost), 0) AS TotalRequestAmount,
                  CASE 
                    WHEN a.accountId IS NOT NULL 
                      THEN CASE 
                        WHEN a.companyId IS NOT NULL THEN co.companyName 
                        ELSE br.branchName END
                    WHEN cl.accountId IS NOT NULL 
                      THEN CASE 
                        WHEN cl.companyId IS NOT NULL THEN cc.companyName 
                        ELSE br.branchName END
                  ELSE 'Unknown' END AS `Account Name`
                FROM booking b 
                LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                LEFT JOIN company co ON a.companyId = co.companyId
                LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                LEFT JOIN company cc ON cl.companyId = cc.companyId
                JOIN branch br ON b.agentCode = br.branchAgentCode
                JOIN flight f ON b.flightId = f.flightId
                JOIN package p ON b.packageId = p.packageId
                LEFT JOIN payment pa ON pa.transactNo = b.transactNo AND pa.paymentStatus = 'Approved'
                LEFT JOIN request r ON r.transactNo = b.transactNo AND r.requestStatus = 'Confirmed'
                WHERE status = 'Confirmed' AND f.flightDepartureDate >= CURDATE() 
                GROUP BY b.transactNo";

                $result = $conn->query($query1);

                // Start the table HTML
                echo '<div class="confirm-container">
                <div class="table-header">
                  <div class="title-wrapper">
                    <h6 class="">Confirmed Transactions</h6>
                  </div>
                </div>

                <div class="table-wrapper confirm-table-container">
                  <table class="table confirm-table" id="confirm-table">
                    <thead>
                      <tr>
                        <th>TRANSACTION NO.</th>
                        <th>AGENT NAME</th>
                        <th>FLIGHT DATE</th>
                        <th>TOTAL PAX.</th>
                        <th>BOOKING TYPE</th>
                        <th>PACKAGE PRICE</th>
                        <th>AMOUNT INFO</th>
                        <th>STATUS</th>
                        <th>COMMENT</th>
                      </tr>
                    </thead>
                    <tbody>';

                if ($result && $result->num_rows > 0) {
                  // Loop through each row and render the table rows
                  while ($row = $result->fetch_assoc()) {
                    $packagePrice = $row['PackagePrice'] ?? 0;
                    $requestTotal = $row['TotalRequestAmount'] ?? 0;
                    $amountPaid = $row['TotalAmountPaid'] ?? 0;
                    $balance = ($packagePrice + $requestTotal) - $amountPaid;
                    $status = $row['status'];
                    $formattedPP = '₱ ' . number_format($packagePrice, 2);
                    $formattedAP = '₱' . number_format($amountPaid, 2);
                    $formattedBal = '₱' . number_format($balance, 2);
                    $formattedFlightDate = date('Y.m.d', strtotime($row['Start']));

                    // Define the pill status class based on the status value
                    switch ($status) {
                      case 'Confirmed':
                        $pillClass = 'bg-success';
                        break;
                      case 'Cancelled':
                        $pillClass = 'bg-danger';
                        break;
                      case 'Pending':
                        $pillClass = 'bg-warning';
                        break;
                      case 'Rejected':
                        $pillClass = 'bg-info';
                        break;
                      default:
                        $pillClass = 'bg-secondary';
                        break;
                    }

                    echo "<tr data-id='{$row['transactNo']}'>
                            <td>{$row['transactNo']}</td>
                            <td>{$row['branchName']}</td>
                            <td>{$formattedFlightDate}</td>
                            <td>{$row['pax']}</td>
                            <td>{$row['bookingType']}</td>
                            <td>{$formattedPP}</td>
                            <td>
                              <div class='payment-info'>
                                <div class='payment-row'><span class='label'>Amount Paid:</span><span class='value'>{$formattedAP}</span></div>
                                <div class='payment-row'><span class='label'>Balance:</span><span class='value'>{$formattedBal}</span></div>
                              </div>
                            </td>
                            <td><span class='badge $pillClass p-2'>{$status}</span></td>";

                    // Fetch comment
                    $transactNo = $row['transactNo'];
                    $stmt = $conn->prepare('SELECT * FROM bookingcomments WHERE transactNo = ?');
                    $stmt->bind_param('s', $transactNo);
                    $stmt->execute();
                    $resultComment = $stmt->get_result();
                    $comment = $resultComment->fetch_assoc();
                    $stmt->close();

                    echo "<td>
                            <div class='comment-container' id='commentContainer{$transactNo}'>";
                                if ($comment && !empty($comment['comment'])) {
                                  echo "<div class='comment-exists'>
                              <div class='comment-input'><input type='text' class='form-control' id='commentInput{$transactNo}' value='" . htmlspecialchars($comment['comment']) . "' disabled></div>
                              <div class='edit-button'><button type='button' class='btn btn-warning editComment' data-id='{$transactNo}'>Edit</button></div>
                            </div>";
                                } else {
                                  echo "<div class='no-comment'>
                              <div class='comment-input'><input type='text' class='form-control' id='commentInput{$transactNo}' disabled></div>
                              <div class='add-button'><button type='button' class='btn btn-success addComment' data-id='{$transactNo}'>Add</button></div>
                            </div>";
                                }

                                echo "<div class='button-container'>
                            <input type='text' class='recordId' value='{$transactNo}' hidden>
                            <button type='button' class='btn btn-primary submitAddComment' data-id='{$transactNo}' style='display:none;'>Submit</button>
                            <button type='button' class='btn btn-primary submitEditComment' data-id='{$transactNo}' style='display:none;'>Update</button>
                            <button type='button' class='btn btn-danger deleteComment' data-id='{$transactNo}' style='display:none;'>Remove</button>
                            <button type='button' class='btn btn-danger cancelEditComment' data-id='{$transactNo}' style='display:none;'>Cancel Edit</button>
                            <button type='button' class='btn btn-danger cancelAddComment' data-id='{$transactNo}' style='display:none;'>Cancel Add</button>
                          </div>
                        </div>
                      </td>
                    </tr>";
                    
                  }

                } else {
                  // Output the empty row message if no data
                  echo '<tr><td colspan="9" class="text-center">NO CONFIRMED BOOKING AS OF THE MOMENT</td></tr>';
                }

                echo '</tbody></table></div></div>'; // Close table and containers
              
                if ($result)
                  $result->free();
              }

              // Call the function to render the table
              renderConfirmedTransactionsTable($conn);
            ?>

          </div>

        </div>

      </div>

    </div>
  </div>

  <!-- Comment Delete Modal -->
  <div class="modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this comment? This action cannot be undone.
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../Employee Section/includes/emp-scripts.php' ?>


  <!-- for Card Counts Clickable -->
  <!-- <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Get URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      const selectedStatus = urlParams.get("status");

      if (selectedStatus) {
        document.getElementById("status").value = selectedStatus;
        $('#status').trigger('change'); // Trigger DataTable update if needed
      }
    });
  </script> -->

  <!-- For Clickable Cards -->
  <script>
    function redirectToTransactionStatus(status) {
      window.location.href = `../Employee Section/emp-transaction.php?tab=status&status=${status}`;
    }

    function redirectToTransactionOnDue(onDue) {
      window.location.href = `../Employee Section/emp-transaction.php?tab=onDue&onDue=${onDue}`;
    }

    // function redirectToTransactionRemainingBalance(Remaining) {
    //   window.location.href = `../Employee Section/emp-transaction.php?tab=remainBal&remainBal=${Remaining}`;
    // }
  </script>

  <!-- Clear RowColNum -->
  <script>
    function clearSelection() {
      const selectBox = document.getElementById("rowsPerPage");
      selectBox.value = "16"; // Reset to default value
    }
  </script>

  <!-- JS for Checkbox -->
  <script>
    $(document).ready(function () {
      let changes = {}; // Store changed checkbox values

      // Function to check if there are changes and toggle the Save button
      function toggleSaveButton() {
        if (Object.keys(changes).length > 0) {
          $('#saveChanges').css('display', 'block'); // Show Save button
        } else {
          $('#saveChanges').css('display', 'none'); // Hide Save button
        }
      }

      // Function to get all checked flight IDs and log them
      function logCheckedFlightIds() {
        let checkedIds = [];
        $('.status-checkbox:checked').each(function () {
          checkedIds.push($(this).data('id'));
        });
        console.log("Checked Flight IDs:", checkedIds); // Log the checked flight IDs
      }


      // When a checkbox is toggled
      $('.status-checkbox').on('change', function () {
        let flightId = $(this).data('id'); // Get flight ID
        let isChecked = $(this).is(':checked') ? 1 : 0; // Convert to 1 (checked) or 0 (unchecked)

        // Log the flight ID of the toggled checkbox
        console.log("Toggled Flight ID:", flightId);

        // If checkbox state differs from original, store it, otherwise remove it
        if ($(this).data('original') !== isChecked) {
          changes[flightId] = isChecked; // Add to changes object
        } else {
          delete changes[flightId]; // Remove from changes object
        }

        logCheckedFlightIds(); // Log checked flight IDs to console
        toggleSaveButton(); // Show or hide the Save button
      });

      // Save Button Click Event
      $('#saveChanges').on('click', function () {
        if (Object.keys(changes).length === 0) return; // No changes to save

        $.ajax({
          url: '../Agent Section/functions/agent-updateCheckStatus.php',
          type: 'POST',
          data: {
            updates: changes // Send updates as the payload
          },
          success: function (response) {
            alert('Status updated successfully!');
            changes = {}; // Clear changes after saving

            // Update original values for the checkboxes
            $('.status-checkbox').each(function () {
              $(this).data('original', $(this).is(':checked') ? 1 : 0);
            });

            toggleSaveButton(); // Hide the save button after saving

            // Destroy the DataTable instance before reinitializing
            var table = $('#example').DataTable();
            table.destroy();

            // Reinitialize the DataTable by calling the function
            initializeDataTable(); // This will reinitialize with the current settings

            // Reload the page after saving (optional, if you want to reload instead of just refreshing the table)
            // location.reload(); 
          },
          error: function () {
            alert('Error updating status.');
          }
        });
      });

      // Initialize original checkbox states
      $('.status-checkbox').each(function () {
        $(this).data('original', $(this).is(':checked') ? 1 : 0);
      });

      toggleSaveButton(); // Ensure the button is hidden initially
    });

    $('#pills-home-tab').on('click', function () {
      $('#saveChanges').css('display', 'none'); // Hide Save button
      changes = {}; // Flush the changes array
      // console.log("Changes array flushed:", changes); // Log the flushed array

      // Reset all checkboxes to their original state (untrigger non-changed checkboxes)
      $('.status-checkbox').each(function () {
        let originalState = $(this).data('original') === 1; // Get the original state (true or false)
        $(this).prop('checked', originalState); // Set checkbox to its original state
      });
    });
  </script>

  <!-- JS for Comment -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      function toggleCommentForm(transactNo, action) {
        const commentInput = document.getElementById('commentInput' + transactNo);
        const submitAddButton = document.querySelector('.submitAddComment[data-id="' + transactNo + '"]');
        const submitEditButton = document.querySelector('.submitEditComment[data-id="' + transactNo + '"]');
        const cancelEditButton = document.querySelector('.cancelEditComment[data-id="' + transactNo + '"]');
        const cancelAddButton = document.querySelector('.cancelAddComment[data-id="' + transactNo + '"]');
        const editButton = document.querySelector('.editComment[data-id="' + transactNo + '"]');
        const addButton = document.querySelector('.addComment[data-id="' + transactNo + '"]');
        const deleteButton = document.querySelector('.deleteComment[data-id="' + transactNo + '"]');

        commentInput.disabled = false;
        commentInput.focus();
        const originalComment = commentInput.value;
        commentInput.setAttribute('data-original-comment', originalComment);

        if (action === 'edit') {
          submitEditButton.style.display = 'inline-block';
          cancelEditButton.style.display = 'inline-block';
          submitAddButton.style.display = 'none';
          cancelAddButton.style.display = 'none';
          deleteButton.style.display = 'inline-block'; // Show delete button on edit
          editButton.style.display = 'none';
        } else if (action === 'add') {
          submitAddButton.style.display = 'inline-block';
          cancelAddButton.style.display = 'inline-block';
          submitEditButton.style.display = 'none';
          cancelEditButton.style.display = 'none';
          // deleteButton.style.display = 'none'; // Hide delete button on add
          addButton.style.display = 'none';
        }

        editButton.style.display = 'none';
        addButton.style.display = 'none';
      }

      document.querySelectorAll('.editComment').forEach(button => {
        button.addEventListener('click', function () {
          const transactNo = this.getAttribute('data-id');
          toggleCommentForm(transactNo, 'edit');
        });
      });

      document.querySelectorAll('.addComment').forEach(button => {
        button.addEventListener('click', function () {
          const transactNo = this.getAttribute('data-id');
          toggleCommentForm(transactNo, 'add');
        });
      });
    });

    // Handle click on delete button
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.deleteComment').forEach(button => {
        button.addEventListener('click', function () {
          const transactNo = this.getAttribute('data-id'); // Get the transactNo from the data-id attribute
          const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal')); // Use existing modal with id 'deleteModal'

          // Show the modal
          deleteModal.show();

          // When the "Delete" button in the modal is clicked, send the delete request
          document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const formData = new FormData();
            formData.append('transactNo', transactNo); // Send the transactNo

            // Send the delete request via fetch
            fetch('../Employee Section/functions/emp-commentDelete.php', {
              method: 'POST',
              body: formData
            })
              .then(response => response.json())
              .then(data => {
                if (data.status === 'success') {
                  alert(data.message);
                  location.reload(); // Reload the page after successful deletion
                } else {
                  alert(data.message || 'Error deleting comment.');
                }
              })
              .catch(error => {
                console.error('Error:', error);
                alert('Error occurred while deleting the comment.');
              });

            // Close the modal after deletion attempt
            deleteModal.hide();
          });
        });
      });
    });

    document.querySelectorAll('.submitAddComment').forEach(button => {
      button.addEventListener('click', function () {
        const transactNo = this.getAttribute('data-id');
        const comment = document.getElementById('commentInput' + transactNo).value;

        if (comment) {
          console.log('Transaction Number:', transactNo);
          console.log('Comment:', comment);

          $.ajax({
            url: '../Employee Section/functions/emp-commentSubmit.php',
            type: 'POST',
            data: {
              transactNo,
              comment
            },
            success: function (response) {
              const jsonResponse = JSON.parse(response); // Parse the JSON response
              if (jsonResponse.status === 'success') {
                alert('Comment added successfully!');

                // Log the returned variables (comment and transactNo) from the response
                console.log('Comment:', jsonResponse.comment);
                console.log('Transaction Number:', jsonResponse.transactNo);

                // Optionally, you can reload the table or perform any other update
                location.reload(); // Uncomment if you want to reload the page
              } else {
                alert(jsonResponse.message || 'Error adding comment.');
              }
            },
            error: function (xhr, status, error) {
              alert('Error adding comment.');
            }
          });
        } else {
          alert('Please enter a comment.');
        }
      });
    });


    document.querySelectorAll('.submitEditComment').forEach(button => {
      button.addEventListener('click', function () {
        const transactNo = this.getAttribute('data-id');
        const comment = document.getElementById('commentInput' + transactNo).value;

        if (comment) {
          $.ajax({
            url: '../Employee Section/functions/emp-commentUpdate.php',
            type: 'POST',
            data: {
              transactNo,
              comment
            },

            success: function (response) {
              alert('Comment updated successfully!');
              location.reload();
            },
            error: function (xhr, status, error) {
              alert('Error updating comment.');
            }
          });
        } else {
          alert('Please enter a comment.');
        }
      });
    });

    document.querySelectorAll('.cancelEditComment').forEach(button => {
      button.addEventListener('click', function () {
        const transactNo = this.getAttribute('data-id');
        const commentInput = document.getElementById('commentInput' + transactNo);
        const editButton = document.querySelector('.editComment[data-id="' + transactNo + '"]');
        const addButton = document.querySelector('.addComment[data-id="' + transactNo + '"]');
        const submitEditButton = document.querySelector('.submitEditComment[data-id="' + transactNo + '"]');
        const cancelEditButton = document.querySelector('.cancelEditComment[data-id="' + transactNo + '"]');
        const deleteButton = document.querySelector('.deleteComment[data-id="' + transactNo + '"]');

        commentInput.value = commentInput.getAttribute('data-original-comment');
        commentInput.disabled = true;

        // Restore default button visibility
        submitEditButton.style.display = 'none';
        cancelEditButton.style.display = 'none';
        deleteButton.style.display = 'none';
        editButton.style.display = 'inline-block';
      });
    });

    document.querySelectorAll('.cancelAddComment').forEach(button => {
      button.addEventListener('click', function () {
        const transactNo = this.getAttribute('data-id');
        const commentInput = document.getElementById('commentInput' + transactNo);
        const editButton = document.querySelector('.editComment[data-id="' + transactNo + '"]');
        const addButton = document.querySelector('.addComment[data-id="' + transactNo + '"]');
        const submitAddButton = document.querySelector('.submitAddComment[data-id="' + transactNo + '"]');
        const cancelAddButton = document.querySelector('.cancelAddComment[data-id="' + transactNo + '"]');

        commentInput.value = ''; // Reset comment input
        commentInput.disabled = true; // Disable the input field

        // Restore default button visibility
        submitAddButton.style.display = 'none';
        cancelAddButton.style.display = 'none';
        addButton.style.display = 'inline-block'; // Ensure 'addButton' is visible
      });
    });
  </script>

  <!-- <script>
    // Function to initialize or reinitialize the DataTable
    function initializeDataTable() {
      // Check if the table is already initialized
      if (!$.fn.DataTable.isDataTable('.info-table')) {
        var table = $('.info-table').DataTable({
          "scrollCollapse": true,
          "deferRender": true,
          responsive: true,
          autoWidth: false, // Prevent automatic width calculation
          scrollX: true, // Enable horizontal scrolling
          scrollY: "565px", // Set vertical scroll height
          paging: true, // Enable pagination
          searching: false, // Disable search
          info: false, // Disable info text
          pageLength: 16, // Set number of rows per page
          dom: 'rt<"bottom"flp>',
          ordering: false, // Disable sorting on columns

          columnDefs: [{
              targets: 0,
              width: '1%'
            },
            {
              targets: 1,
              width: '4%'
            },
            {
              targets: 2,
              width: '4%'
            },
            {
              targets: 3,
              width: '5.5%'
            },
            {
              targets: 4,
              width: '5.5%'
            },
            {
              targets: 5,
              width: '3%'
            },
            {
              targets: 6,
              width: '3%'
            },
            {
              targets: 7,
              width: '4%'
            },
            {
              targets: 8,
              width: '4%'
            },
            {
              targets: 9,
              width: '4%'
            },
            {
              targets: 10,
              width: '6%'
            },
            {
              targets: 11,
              width: '6%'
            },

            {
              targets: '_all',
              width: '3%',
              height: '30px',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap'
            }
          ]

        });

        // Event listener for row clicks in .info-table
        $('.info-table').on('click', 'tbody tr', function(e) {
          if ($(e.target).is('input[type="checkbox"]')) return; // Ignore checkboxes
          const index = $(this).index();
          selectRowInBothTables(index); // If you have this function
        });

        // For RowColNum 
        // Update page length based on user selection
        $('#rowsPerPage').on('change', function () {
            var pageLength = $(this).val();
            table.page.len(pageLength).draw(); // Set the page length and redraw the table
            toggleClearButton(); // Show or hide the clear button
        });

        // Function to reset the selection and DataTable page length
        window.clearSelection = function () {
            $('#rowsPerPage').val('16').trigger('change'); // Reset dropdown & trigger change event
        };

        // Show/hide the clear button dynamically
        function toggleClearButton() {
            if ($('#rowsPerPage').val() !== '16') {
                $('#clear-btn').show(); // Use ID selector for better accuracy
            } else {
                $('#clear-btn').hide();
            }
        }

        // Initialize: Hide clear button if default value is selected
        $(document).ready(function () {
            toggleClearButton(); // Ensure correct initial visibility
        });



        $(document).ready(function () {
            // Set default page info on load
            var info = table.page.info(); 
            $('#pageInfo').text('Page ' + (info.page + 1) + ' of ' + info.pages);

            // Handle previous/next buttons
            $('#prevPage').on('click', function() {
                table.page('previous').draw('page');
            });

            $('#nextPage').on('click', function() {
                table.page('next').draw('page');
            });

            // Update page info on page change
            table.on('draw', function() {
                var info = table.page.info();
                $('#pageInfo').text('Page ' + (info.page + 1) + ' of ' + info.pages);
            });
        });
        
      }
    }

    $(document).ready(function() {
      // Call the function to initialize the DataTable when the document is ready
      initializeDataTable();
    });
  </script> -->
</body>

</html>