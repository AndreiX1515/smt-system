<?php
  // Check if 'id' is passed in the URL
  if (isset($_GET['id'])) 
  {
    $transactionNumber = htmlspecialchars($_GET['id']);
  } 
?>

<!-- Payment History Table -->
<div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" tabindex="0">
  <div class="tab-wrapper">
    <div div class="d-flex justify-content-end align-items-center p-3">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?= $transactionNumber ?>"
      data-transact-no="<?= $transactionNumber ?>" data-account-id="<?= $accountId ?>">Add Payment</button>
    </div>

    <div class="table-container">
      <table class="product-table">
        <thead>
          <tr>
            <th>PAYMENT ID</th>
            <th>PAYMENT TYPE</th>
            <th>AMOUNT</th>
            <th>PROOF OF PAYMENT</th>
            <th>PAYMENT DATE</th>
            <th>PAYMENT STATUS</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $sql1 = "SELECT *, FORMAT(amount, 2) AS amount, DATE_FORMAT(paymentDate, '%m-%d-%Y') AS paymentDate 
                      FROM fitpayment 
                      WHERE transactNo = '$transactionNumber'";

            $res1 = $conn->query($sql1);

            if ($res1->num_rows > 0) 
            {
              while ($row = $res1->fetch_assoc())
              {
                // Fetch the payment status from the database
                $status = $row['paymentStatus'];
                
                // Assign a corresponding Bootstrap badge class based on the status
                $badgeClass = '';

                switch($status) 
                {
                  case 'Submitted':
                    $badgeClass = 'bg-primary'; // Blue for Submitted
                    break;
                  case 'Approved':
                    $badgeClass = 'bg-success'; // Green for Approved
                    break;
                  default:
                    $badgeClass = 'bg-secondary'; // Gray for unknown statuses
                    break;
                }

                echo "<tr>
                        <td>{$row['paymentId']}</td>
                        <td>{$row['paymentType']}</td>
                        <td>₱ {$row['amount']}</td>
                        <td>
                          <a href='functions/view-file.php?file=" . urlencode($row['filePath']) . "' target='_blank'>View File</a> 
                          <a href='functions/download.php?file=" . urlencode($row['filePath']) . "' target='_blank'>Download File</a> 
                        </td>
                        <td>{$row['paymentDate']}</td>
                        <td>
                          <span class='badge rounded-pill {$badgeClass} py-2'> {$status} </span>
                        </td>
                      </tr>";
              }
            } 
            else 
            {
             echo "<tr><td colspan='7' style='text-align: center;'>No Payment Found</td></tr>";
            }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal" id="paymentModal<?= $transactionNumber ?>" tabindex="-1" aria-labelledby="paymentModalLabel<?= $transactionNumber ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModalLabel<?= $transactionNumber ?>">Payment for Transaction #<?= $transactionNumber ?></h5>
        <button type="button" class="btn-close custom-close" aria-label="Close" data-modal-id="paymentModal<?= $transactionNumber ?>"></button>
      </div>
      <form action="../Agent Section/functions/agent-transactionFITPayment-code.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <!-- Hidden Inputs -->
          <input type="hidden" name="transactionNumber" value="<?= $transactionNumber ?>" placeholder="TransactNo">
          <input type="hidden" name="accountId" value="<?= $accountId ?>" placeholder="accId">

          <div class="mb-3">
            <label class="form-label">Payment Type</label>
            <select class="form-select" name="paymentType" required>
              <option selected disabled>Select Payment Type</option>
              <option value="Downpayment">Downpayment</option>
              <option value="Partial Payment">Partial Payment</option>
              <option value="Full Payment">Full Payment</option>
            </select>
          </div>

          <?php
            $sql1 = "SELECT f.transactionNo, f.phpPrice AS phpPrice, SUM(fp.amount) AS paidAmount, 
                        (f.phpPrice - SUM(fp.amount)) AS balanceRemaining 
                      FROM fit f
                      JOIN fitpayment fp ON f.transactionNo = fp.transactNo
                      WHERE fp.paymentStatus = 'Approved'
                      GROUP BY f.transactionNo, f.phpPrice";

            $result1 = $conn->query($sql1);

            if ($result1->num_rows > 0) 
            {
              $row = $result1->fetch_assoc(); // Assuming you are fetching data for one specific transaction
              $transactionNo = $row['transactionNo'];
              $phpPrice = $row['phpPrice'];
              $paidAmount = $row['paidAmount'];
              $balanceRemaining = $row['balanceRemaining'];
            } 
            else 
            {
              $phpPrice = 0;
              $paidAmount = 0;
              $balanceRemaining = 0;
            }
          ?>

          <div id="amountDisplay">
            Balance: ₱ <span id="amountValue"><?php echo number_format((float)$balanceRemaining, 2); ?></span>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Amount</label>
            <input type="number" step="0.01" class="form-control" id="paymentAmount" name="amount" 
              placeholder="Enter payment amount" min="1" step="0.01"
              max="<?php echo htmlspecialchars($balanceRemaining); ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Proof of Payment</label>
            <div class="mb-3">
              <input type="file" class="form-control" name="proofs[]" accept="image/*,application/pdf" multiple>
            </div>
            <!-- List of file names -->
            <ul id="fileList<?= $transactionNumber ?>" class="list-unstyled mt-2"></ul>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary custom-close" data-modal-id="paymentModal<?= $transactionNumber ?>">Close</button>
          <button type="submit" name="payment" class="btn btn-primary">Submit payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Function -->
<script>
  // Custom JavaScript for handling modal close and removing backdrop
  $(document).ready(function () 
  {
    // Handle close button click with custom behavior
    $(".custom-close").on("click", function () 
    {
      const modalId = $(this).data("modal-id");
      $(`#${modalId}`).modal("hide");
    });

    // Ensure backdrops are removed when the modal is hidden
    $("#paymentModal<?= $transactionNumber ?>").on("hidden.bs.modal", function () 
    {
      $(".modal-backdrop").remove();
    });
  });
</script>

<!-- Modal Close Function -->
<script>
  // Custom JavaScript to handle modal close functionality
  document.addEventListener("DOMContentLoaded", () => 
  {
    const closeButtons = document.querySelectorAll(".custom-close");

    closeButtons.forEach(button => 
    {
      button.addEventListener("click", function () 
      {
        const modalId = this.getAttribute("data-modal-id");
        const modalElement = document.getElementById(modalId);

        if (modalElement) 
        {
          // Use Bootstrap's modal('hide') to close the modal properly
          $(`#${modalId}`).modal('hide');
        }
      });
    });
  });
</script>

<!-- Max Value input -->
<script>
  // Get the payment amount input field
  const paymentAmountInput = document.getElementById("paymentAmount");

  // Add an event listener to handle changes in the input
  paymentAmountInput.addEventListener("input", () => 
  {
    // Get the max value from the input attribute
    const max = parseFloat(paymentAmountInput.getAttribute("max"));
    
    // Get the current input value
    const value = parseFloat(paymentAmountInput.value);

    // If the value exceeds the max, set it to the max
    if (value > max) 
    {
      paymentAmountInput.value = max;
    }
  });
</script>

<!-- Upload Script -->
<script>
  const maxFiles = 5;
  const maxFileSize = 4 * 1024 * 1024; // 4MB
  let selectedFiles = {};

  document.querySelectorAll('.drop-zone').forEach(dropZone => 
  {
    dropZone.addEventListener("click", function() 
    {
      const transactNo = this.id.replace('dropZone', ''); // Extract transactNo
      document.getElementById('fileInput' + transactNo).click();
    });
  });

  function handleDrop(event, transactNo) 
  {
    event.preventDefault();
    handleFiles(event.dataTransfer.files, transactNo);
  }

  function handleFiles(files, transactNo) 
  {
    const fileList = document.getElementById("fileList" + transactNo);
    selectedFiles[transactNo] = selectedFiles[transactNo] || [];

    if (selectedFiles[transactNo].length + files.length > maxFiles) 
    {
      alert(`You can upload a maximum of ${maxFiles} files.`);
      return;
    }

    Array.from(files).forEach(file => 
    {
      if (file.size > maxFileSize) 
      {
        alert(`File ${file.name} exceeds the 4MB limit and won't be added.`);
      } 
      else 
      {
        selectedFiles[transactNo].push(file);

        // Debugging: Log the file and the selectedFiles array
        console.log(`File added: ${file.name}, Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`);
        console.log(selectedFiles[transactNo]);

        // Create a list item for the file
        const listItem = document.createElement("li");
        listItem.classList.add("file-item");
        listItem.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;

        // Add remove button
        const removeButton = document.createElement("button");
        removeButton.textContent = "Remove";
        removeButton.classList.add("btn", "btn-danger", "btn-sm", "ml-2");
        removeButton.onclick = () => removeFile(file, transactNo);

        listItem.appendChild(removeButton);
        fileList.appendChild(listItem);
      }
    });

    updateFileInput(transactNo);
  }

  function removeFile(file, transactNo) 
  {
    const index = selectedFiles[transactNo].indexOf(file);
    if (index > -1) 
    {
      selectedFiles[transactNo].splice(index, 1); // Remove file from selectedFiles
    }

    // Remove the list item from the DOM
    const fileList = document.getElementById("fileList" + transactNo);
    const listItem = fileList.querySelector(`li:contains('${file.name}')`);
    if (listItem) 
    {
      fileList.removeChild(listItem);
    }

    updateFileInput(transactNo);
  }

  function updateFileInput(transactNo) 
  {
    const dataTransfer = new DataTransfer();
    selectedFiles[transactNo].forEach(file => dataTransfer.items.add(file));

    const fileInput = document.getElementById('fileInput' + transactNo);
    fileInput.files = dataTransfer.files;

    // Debugging: Log updated file input
    console.log(fileInput.files);
  }
</script>



