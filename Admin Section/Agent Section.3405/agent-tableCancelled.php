<div class="table-container">
  <table id="product-table" class="product-table">
    <thead>
      <tr>
        <th>Transaction ID</th>
        <th>Contact Person Info</th>
        <th>Contact Details</th>
        <th>Branch Name</th>
        <!-- <th>Booking Date</th> -->
        <th>Flight Date</th>
        <th>Total Pax</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php
        $agentRole = $_SESSION['agentRole'];
        $agentCode = $_SESSION['agentCode'];
        $accountId = $_SESSION['accountId'];
        if ($agentRole != 'Head Agent')
        {
          $sql1 = "SELECT b.transactNo AS `T.N`, p.packageName AS `PACKAGE`,
                    DATE_FORMAT(b.bookingDate, '%m-%d-%Y') AS `TRANSACTION DATE`, b.bookingType as bookingType,
                    DATE_FORMAT(f.flightDepartureDate, '%m-%d-%Y') AS `FLIGHT DATE`, b.pax AS `TOTAL PAX`,
                    CONCAT(b.lName, ', ', b.fName, ' ', CASE WHEN b.mName = 'N/A' THEN '' 
                      ELSE CONCAT(SUBSTRING(b.mName, 1, 1), '.') END, ' ', CASE WHEN b.suffix = 'N/A' THEN '' 
                      ELSE b.suffix END) AS `CONTACT NAME`, br.branchName as branchName,
                    b.email AS `CONTACT EMAIL`, CONCAT(b.countryCode, ' ', b.contactNo) AS `CONTACT PHONE`, b.status AS `STATUS`
                FROM booking b
                LEFT JOIN flight f ON b.flightId = f.flightId
                LEFT JOIN package p ON b.packageId = p.packageId
                LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                JOIN branch br ON b.agentCode = br.branchAgentCode
                WHERE b.accountId = $accountId AND b.status = 'Cancelled'
                ORDER BY b.transactNo DESC";

          $res1 = $conn->query($sql1);

          if ($res1->num_rows > 0) 
          {
            while ($row = $res1->fetch_assoc()) 
            {
              $transactNo = $row['T.N'];
              $pax = $row['TOTAL PAX'];

              $status = isset($row['STATUS']) ? $row['STATUS'] : 'Unknown';
              $statusClass = '';

              switch ($status) {
                  case 'Confirmed':
                      $statusClass = 'bg-success text-white'; // Green background, white text
                      break;
                  case 'Cancelled':
                      $statusClass = 'bg-danger text-white'; // Red background, white text
                      break;
                  case 'Pending':
                      $statusClass = 'bg-warning text-dark'; 
                      break;
                  default:
                      $statusClass = 'bg-secondary text-white'; 
              }

              // Booking Date
              // <td>{$row['TRANSACTION DATE']}</td>

              echo "<tr data-url='agent-showGuest.php?id=" . htmlspecialchars($transactNo) . "'>
                      <td>{$transactNo}</td>
                      <td>{$row['CONTACT NAME']}</td>
                      <td> 
                        <div class='d-flex flex-column'>
                          <span><strong>Email: </strong>" . $row['CONTACT EMAIL'] ." </span>
                          <span><strong>Contact Number: </strong> " . $row['CONTACT PHONE'] ."</span>
                        </div>
                      </td>

                      <td>{$row['branchName']}</td>
                      
                      <td>{$row['FLIGHT DATE']}</td>
                      <td style='text-align: center; font-weight: bold;'>
                          {$row['TOTAL PAX']}
                      </td>
                      <td>
                        <span class='badge p-2 rounded-pill {$statusClass} '>
                            {$status}
                        </span>
                    </td>
              </tr>";
            }
          }
          else
          {
            echo "<tr>
                    <td colspan='7' class='text-center text-danger'>
                        No Cancelled bookings found.
                    </td>
                  </tr>";
          }
        
        }
        else
        {
          $sql1 = "SELECT b.transactNo AS `T.N`, p.packageName AS `PACKAGE`, br.branchName as branchName,
                      DATE_FORMAT(b.bookingDate, '%m-%d-%Y') AS `TRANSACTION DATE`, b.bookingType as bookingType,
                      DATE_FORMAT(f.flightDepartureDate, '%m-%d-%Y') AS `FLIGHT DATE`,
                      b.pax AS `TOTAL PAX`, CONCAT(b.lName, ', ', b.fName, ' ', CASE WHEN b.mName = 'N/A' 
                      THEN '' ELSE CONCAT(SUBSTRING(b.mName, 1, 1), '.') END, ' ', CASE WHEN b.suffix = 'N/A' THEN '' 
                      ELSE b.suffix END) AS `CONTACT NAME`, b.email AS `CONTACT EMAIL`,
                      CONCAT(b.countryCode, ' ', b.contactNo) AS `CONTACT PHONE`, b.status AS `STATUS`
                    FROM booking b
                    LEFT JOIN flight f ON b.flightId = f.flightId
                    LEFT JOIN package p ON b.packageId = p.packageId
                    LEFT JOIN agent a ON b.accountType = 'Agent' AND b.accountId = a.accountId
                    LEFT JOIN company c ON a.companyId = c.companyId
                    LEFT JOIN client cl ON b.accountType = 'Client' AND b.accountId = cl.accountId
                    LEFT JOIN company cc ON cl.companyId = cc.companyId
                    JOIN branch br ON b.agentCode = br.branchAgentCode
                    WHERE b.agentCode = '$agentCode' AND b.status = 'Cancelled'
                    AND (COALESCE(c.companyId, '') = COALESCE('$companyId', '') 
                    OR COALESCE(cc.companyId, '') = COALESCE('$companyId', '')) 
                    ORDER BY b.transactNo DESC";

          $res1 = $conn->query($sql1);

          if ($res1->num_rows > 0) 
          {
            while ($row = $res1->fetch_assoc()) 
            {
              $transactNo = $row['T.N'];
              $pax = $row['TOTAL PAX'];

              $status = isset($row['STATUS']) ? $row['STATUS'] : 'Unknown';
              $statusClass = '';

              switch ($status) 
              {
                case 'Confirmed':
                    $statusClass = 'bg-success text-white'; // Green background, white text
                    break;
                case 'Cancelled':
                    $statusClass = 'bg-danger text-white'; // Red background, white text
                    break;
                case 'Pending':
                    $statusClass = 'bg-warning text-dark'; 
                    break;
                default:
                    $statusClass = 'bg-secondary text-white'; 
              }

              // <td>{$row['TRANSACTION DATE']}</td>

              echo "<tr data-url='agent-showGuest.php?id=" . htmlspecialchars($transactNo) . "'>
                  <td>{$transactNo}</td>
                  <td>{$row['CONTACT NAME']}</td>
                  <td>
                    <div class='d-flex flex-column'>
                      <span><strong>Email: </strong>" . $row['CONTACT EMAIL'] ."</span>
                      <span><strong>Contact Number: </strong>" . $row['CONTACT PHONE'] ."</span>
                    </div>
                  </td>
                  <td>{$row['branchName']}</td>
                  
                  <td>{$row['FLIGHT DATE']}</td>
                  <td style='text-align: center; font-weight: bold;'>{$row['TOTAL PAX']}</td>
                  <td>
                    <span class='badge p-2 rounded-pill {$statusClass}'>
                      {$status}
                    </span>
                  </td>
                </tr>";
            }
          } 
          else
          {
            echo "<tr>
                    <td colspan='7' class='text-center text-danger'>
                        No Cancelled bookings found.
                    </td>
                  </tr>";
          }
        }

        if ($res1) {
          $res1->free();
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