
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
            <th>Transaction No.</th>
            <th>Request</th>
            <th>Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $transactNo = $_SESSION['transactNo'];
            $sql1 = "SELECT 
                        r.transactNo AS `T.N`,
                        c.concernTitle AS `Request`,
                        DATE_FORMAT(r.requestDate, '%M-%d-%Y %h:%i:%s %p') AS `Date`,
                        r.requestStatus as status
                    FROM 
                        request r
                    JOIN 
                        booking b ON r.transactNo = b.transactNo
                    JOIN 
                        concern c ON r.concernId = c.concernId
                    WHERE 
                        b.transactNo = '$transactNo'";

            // Run the query and check for results
            $res1 = $conn->query($sql1);
              
            // Check if there are any results
            if ($res1->num_rows > 0) {
                // Output data for each row
                while ($row = $res1->fetch_assoc()) {
                    echo "<tr>
                              <td>{$row['T.N']}</td>
                              <td>{$row['Request']}</td>
                              <td>" . date('F d, Y', strtotime($row['Date'])) . "</td>
                              <td>{$row['status']}</td>
                            </tr>";
                }
            } else {
                // If no records found
                echo "<tr><td colspan='6' style='text-align: center;'>No Request found</td></tr>";
            }
          ?>
        </tbody>
      </table>
    </div>

  </div>

  <?php require "../Agent Section/includes/scripts.php"; ?>

</body>
</html>