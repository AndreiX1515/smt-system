<?php
  // Check if 'id' is passed in the URL
  if (isset($_GET['id'])) 
  {
    $transactionNumber = htmlspecialchars($_GET['id']);
  } 
?>

<div class="tab-pane fade" id="flight-tab-pane" role="tabpanel" aria-labelledby="flight-tab" tabindex="0">
  <div class="tab-wrapper">
    <div div class="d-flex justify-content-end align-items-center p-3 mt-2">
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#flightModal">
          Add Flight Details
        </button>
      </div>
    </div>

    <div class="table-container p-3">
      <table class="product-table">
        <thead>
          <tr>
            <th>Flight Name</th>
            <th>Departure Date</th>
            <th>Return Flight Name</th>
            <th>Return Departure Date</th>
          </tr>
        </thead>
        <tbody>
          <?php
            // Corrected SQL query
            $sql1 = "SELECT flightName, flightCode, returnFlightName, 
                            DATE_FORMAT(flightDepartureDate, '%M-%d-%Y') AS formattedFlightDepartureDate,
                            DATE_FORMAT(flightDepartureTime, '%h:%i %p') AS formattedFlightDepartureTime,
                            DATE_FORMAT(returnDepartureDate, '%M-%d-%Y') AS formattedReturnDepartureDate,
                            DATE_FORMAT(returnDepartureTime, '%h:%i %p') AS formattedReturnDepartureTime
                    FROM clientflight 
                    WHERE transactNo = '$transactionNumber'";

            $res1 = $conn->query($sql1);

            if ($res1->num_rows > 0) 
            {
              // Loop through the rows and populate the table
              while ($row = $res1->fetch_assoc()) 
              {
                $departureDate = $row['formattedFlightDepartureDate'] . ' ' . $row['formattedFlightDepartureTime'];
                $returnDate = $row['formattedReturnDepartureDate'] . ' ' . $row['formattedReturnDepartureTime'];

                echo "<tr>
                        <td>" . htmlspecialchars($row['flightName']) . "</td>
                        <td>" . htmlspecialchars($departureDate) . "</td>
                        <td>" . htmlspecialchars($row['returnFlightName']) . "</td>
                        <td>" . htmlspecialchars($returnDate) . "</td>
                      </tr>";
              }
            } 
            else 
            {
              echo "<tr><td colspan='5' style='text-align: center;'>No Flight details found</td></tr>";
            }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Flight Details Modal -->
<div class="modal fade" id="flightModal" tabindex="-1" aria-labelledby="flightModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="flightModalLabel">
          Flight Details
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="../Agent Section/functions/agent-addFlightDetails-code.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <!-- Hidden input for transaction number -->
          <input type="hidden" name="transaction_number" value="<?php echo $transactionNumber; ?>">

          <div class="mb-3">
            <label for="flightName" class="form-label">Flight Name</label>
            <input type="text" class="form-control" id="flightName" name="flightName" required>
          </div>

          <div class="mb-3">
            <label for="flightCode" class="form-label">Flight Code</label>
            <input type="text" class="form-control" id="flightCode" name="flightCode" required>
          </div>

          <div class="mb-3">
            <label for="flightDepartureDate" class="form-label">Flight Departure Date</label>
            <input type="date" class="form-control" id="flightDepartureDate" name="flightDepartureDate" required>
          </div>

          <div class="mb-3">
            <label for="flightDepartureTime" class="form-label">Flight Departure Time</label>
            <input type="time" class="form-control" id="flightDepartureTime" name="flightDepartureTime" required>
          </div>

          <h5 class="mt-4">Return Flight Details</h5>

          <div class="mb-3">
            <label for="returnFlightName" class="form-label">Return Flight Name</label>
            <input type="text" class="form-control" id="returnFlightName" name="returnFlightName" required>
          </div>

          <div class="mb-3">
            <label for="returnFlightCode" class="form-label">Return Flight Code</label>
            <input type="text" class="form-control" id="returnFlightCode" name="returnFlightCode" required>
          </div>

          <div class="mb-3">
            <label for="returnDepartureDate" class="form-label">Return Departure Date</label>
            <input type="date" class="form-control" id="returnDepartureDate" name="returnDepartureDate" required>
          </div>

          <div class="mb-3">
            <label for="returnDepartureTime" class="form-label">Return Departure Time</label>
            <input type="time" class="form-control" id="returnDepartureTime" name="returnDepartureTime" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="addFlightDetails" class="btn btn-primary">Submit</button>
        </div>
      </form>

    </div>
  </div>
</div>