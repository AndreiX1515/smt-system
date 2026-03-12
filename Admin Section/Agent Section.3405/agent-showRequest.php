<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>
  <?php include '../Agent Section/includes/sidebar.php'; ?> 

  <div class="main-content" id="mainContent">
    <?php 
      include '../Agent Section/includes/navbar.php'; 
      
      // Check if the transaction number is set in the session
      if (isset($_SESSION['transaction_number'])) 
      {
        $transactionNumber = $_SESSION['transaction_number'];
      } 
      else 
      {
        echo "No transaction number found.";
      }
    ?>

    <?php 
      if(isset($_SESSION['status'])):
    ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>Hey!</strong> <?= $_SESSION['status']; ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal" 
        data-transaction-id="{$transactionNumber}">Add Request</button>

      </div>
    <?php 
      unset($_SESSION['status']);
      endif;
    ?>
    <div class="content-wrapper">
     <div class="header d-flex flex-row justify-content-between">
      <h6> <span class="fw-bold text-dark">Transaction No: </span>  <?php echo $transactionNumber ?></h6>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal" 
        data-transaction-id="<?= $transactionNumber ?>">Add Request</button>

      </div>
      <div class="table-container p-3">
       <table class="product-table">
         <thead>
          <tr>
            <th>Request Id</th>
            <th>Request Title</th>
            <th>Request Details</th>
            <th>Request Date</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
            $sql1 = "SELECT request.requestId, concern.concernTitle, concerndetails.details, 
            DATE_FORMAT(request.requestDate, '%M %d, %Y %h:%i %p') AS formattedRequestDate, 
            request.requestStatus
           FROM request
           JOIN concern ON request.concernId = concern.concernId
           JOIN concerndetails ON request.concernDetailsId = concerndetails.concernDetailsId
           WHERE request.transactNo = '$transactionNumber'";

  $res1 = $conn->query($sql1);

  if ($res1->num_rows > 0) {
    while ($row = $res1->fetch_assoc()) {
      echo "<tr>
              <td>{$row['requestId']}</td>
              <td>{$row['concernTitle']}</td>
              <td>{$row['details']}</td>
              <td>{$row['formattedRequestDate']}</td>
              <td>{$row['requestStatus']}</td>
            </tr>";
    }
  } else {
    echo "<tr><td colspan='6'>No Payment Found</td></tr>";
  }
          ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>


  
  <!-- Modal for Request -->
  <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="requestModalLabel">Request for Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="../Agent Section/functions/agent-transactionRequest-code.php" method="POST" id="requestForm">
          <div class="modal-body">
            <!-- Transaction Number Display -->
            <p><strong>Transaction No:</strong> <span id="requestTransactionId"></span></p>

            <!-- Hidden Input Fields -->
            <input type="hidden" name="transaction_number" id="transactionNumberInput">
            <input type="hidden" name="agentId" value="<?php echo $agentId; ?>">
            <input type="hidden" name="accountId" value="<?php echo $accountId; ?>">

            <!-- Request Type Selection -->
            <div class="mb-3">
              <select class="form-select mt-2" name="concern" id="concern" required>
                <option selected disabled>Select Request</option>
                <?php
                  $sql1 = mysqli_query($conn, "SELECT DISTINCT concernId, concernTitle FROM concern ORDER BY concernTitle ASC");
                  while($res1 = mysqli_fetch_array($sql1)) 
                  {
                    echo "<option value='{$res1['concernId']}'>{$res1['concernTitle']}</option>";
                  }
                ?>
              </select>
            </div>

            <!-- Request Details Selection -->
            <div class="mb-3" id="additionalSelectContainer" style="display: none;">
              <select class="form-select mt-2" name="requestDetails" id="requestDetails" required>
                <option selected disabled>Select Specific Detail</option>
              </select>
              <label value="0.00">₱ <input type="text" id="price" name="price" value="0.00" style="border: none; background: transparent; padding: 5px 10px; font-size: 14px; display: inline-block; width: auto;" readonly></label>
            </div>

            <!-- Pax Input -->
            <div class="mb-3">
              <label class="form-label">Pax</label>
              <input type="number" class="form-control" id="paxRequest" name="pax" placeholder="Enter pax" min="1" required>
            </div>

            <!-- Details Input -->
            <div class="mb-3">
              <label class="form-label">Details</label>
              <textarea class="form-control" name="details" placeholder="Enter Specific Message" rows="4"></textarea>
            </div>

            <label>₱ <span id="displayTotalPrice">0.00</span></label>
            <input type="hidden" name="totalPrice" id="TotalPrice" value="0.00" style="border: none; background: transparent; padding: 5px 10px; font-size: 14px; display: inline-block; width: auto;" readonly>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="request" class="btn btn-primary">Send Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
document.addEventListener('DOMContentLoaded', function () {
  // Get the modal element
  const requestModal = document.getElementById('requestModal');

  if (requestModal) {
    // Add event listener for when the modal is shown
    requestModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget; // Button that triggered the modal

      if (button) {
        const transactionId = button.getAttribute('data-transaction-id'); // Fetch transaction ID

        // Reset the form to clear any previous data
        const form = document.getElementById('requestForm');
        if (form) form.reset();

        // Hide the 'additionalSelectContainer' if it exists
        const additionalSelectContainer = document.getElementById('additionalSelectContainer');
        if (additionalSelectContainer) additionalSelectContainer.style.display = 'none';

        // Populate the hidden input field specific to the request form
        const transactionInput = document.querySelector('#requestForm input[name="transaction_number"]');
        if (transactionInput) transactionInput.value = transactionId;

        // Display the transaction ID in the modal
        const transactionIdDisplay = document.getElementById('requestTransactionId');
        if (transactionIdDisplay) {
            transactionIdDisplay.textContent = transactionId;
        } else {
            console.warn('Element with ID "requestTransactionId" not found.');
        }


        // Call the function to fetch Pax for the transaction ID
        if (typeof fetchPaxForRequestModal === 'function') {
          fetchPaxForRequestModal(transactionId);
        }
      }
    });
  }
});

// Function to fetch pax for the request modal
function fetchPaxForRequestModal(transactionId) 
    {
      fetch('../Agent Section/functions/getBookingDetails.php', 
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ transaction_id: transactionId }),
      })
      .then(response => response.json())
      .then(data => 
      {
        if (data.success) 
        {
          // Populate only the pax field with the fetched data
          const paxInput = document.querySelector('input[name="pax"]');
          paxInput.setAttribute('max', data.booking.pax);
          
          // Ensure the current value is valid in case it exceeds the max
          validateMaxValue(paxInput);
        } 
        else 
        {
          console.error('Error fetching booking details:', data.message);
        }
      })
      .catch(error => 
      {
        console.error('Fetch error:', error);
      });
    }

    // Function to validate the max value of the input
    function validateMaxValue(input) 
    {
      const max = parseInt(input.getAttribute("max"));
      const currentValue = parseInt(input.value);
      
      if (currentValue > max) 
      {
        input.value = max; // Set the value to the max if it exceeds
      }
    }


  </script>

<?php require "../Agent Section/includes/scripts.php"; ?>

</body>
</html>
