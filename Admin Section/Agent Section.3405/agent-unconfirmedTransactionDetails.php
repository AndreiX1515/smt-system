
<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction <?php echo $_SESSION['T.N']; ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">

</head>

<body>
  <?php include '../Agent Section/includes/sidebar.php'; ?> 

  <div class="main-content" id="mainContent">
    <?php include '../Agent Section/includes/navbar.php'; ?>

    <div class="content-wrapper d-flex flex-column">
      <table class="product-table">
        <thead>
          <tr>
            <th>T.N</th>
            <th>PACKAGE</th>
            <th>FLIGHT DATE</th>
            <th>PAX.</th>
            <th>CONTACT NAME</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $transactNo = $_SESSION['T.N'];
            $sql1 = "SELECT
                        b.transactNo AS `T.N`,
                        p.packageName AS `PACKAGE`,
                        CASE 
                            WHEN b.flightId IS NULL THEN 'Land Only'
                            ELSE DATE_FORMAT(f.flightDepartureDate, '%M %d, %Y')
                        END AS `FLIGHT DATE`,
                        b.pax AS `TOTAL PAX`,
                        CONCAT(
                            b.lName, ', ', b.fName, ' ', 
                            CASE WHEN b.mName = 'N/A' THEN '' ELSE CONCAT(SUBSTRING(b.mName, 1, 1), '.') END, ' ',
                            CASE WHEN b.suffix = 'N/A' THEN '' ELSE b.suffix END
                        ) AS `CONTACT NAME`,
                        b.status AS `STATUS`
                    FROM 
                        booking b
                    LEFT JOIN 
                        flight f ON b.flightId = f.flightId
                    LEFT JOIN 
                        package p ON b.packageId = p.packageId
                    LEFT JOIN
                        agent a ON b.agentId = a.agentId
                    WHERE 
                        b.transactNo = '$transactNo'";

            // Run the query and check for results
            $res1 = $conn->query($sql1);
              
            // Check if there are any results
            if ($res1->num_rows > 0) {
                // Output data for each row
                while ($row = $res1->fetch_assoc()) {
                    echo "
                        <tr>
                            <td>" . htmlspecialchars($row['T.N']) . "</td>
                            <td>" . htmlspecialchars($row['PACKAGE']) . "</td>
                            <td>" . htmlspecialchars($row['FLIGHT DATE']) . "</td>
                            <td>" . htmlspecialchars($row['TOTAL PAX']) . "</td>
                            <td>" . htmlspecialchars($row['CONTACT NAME']) . "</td>
                            <td>" . htmlspecialchars($row['STATUS']) . "</td>
                        </tr>";
                }
            } else {
                // If no records found
                echo "<tr><td colspan='6' style='text-align: center;'>No bookings found</td></tr>";
            }
          ?>
        </tbody>
      </table>
    </div>

  </div>

  <?php require "../Agent Section/includes/scripts.php"; ?>

</body>
</html>