<?php 
session_start(); 
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>
<body>

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

<div class="body-container">
  <?php include "../Agent Section/includes/sidebar.php"; ?>

  <div class="main-content-container">
    <div class="navbar">
      <h5 class="title-page">Transactions</h5>
    </div>

    <div class="main-content">
    <?php 
      if(isset($_SESSION['status'])):
    ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>Hey!</strong> <?= $_SESSION['status']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>

    <?php 
      unset($_SESSION['status']);
      endif;
    ?>

      <div class="header d-flex flex-row justify-content-between">
        <h6> <span class="fw-bold text-dark">Transaction No: </span>  <?php echo $transactionNumber ?></h6>
          <!-- Trigger Button -->
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?= $transactionNumber ?>"
        data-transact-no="<?= $transactionNumber ?>" data-account-id="<?= $accountId ?>">Add Payment</button>
      </div>

     <div class="table-container p-3">
      <table class="product-table">
        <thead>
          <tr>
            <th>Payment Id</th>
            <th>Payment Title</th>
            <th>Payment Type</th>
            <th>Amount</th>
            <th>Proof of Payment</th>
            <th>Payment Date</th>
            <th>Payment Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>

        <?php
           $sql1 = "SELECT *, FORMAT(amount, 2) AS amount, DATE_FORMAT(paymentDate, '%M %d, %Y %h:%i %p') AS paymentDate 
                    FROM payment 
                    WHERE transactNo = '$transactionNumber'";

           $res1 = $conn->query($sql1);

           if ($res1->num_rows > 0) {
               while ($row = $res1->fetch_assoc()) {
                   echo "<tr>
                           <td>{$row['paymentId']}</td>
                           <td>{$row['paymentTitle']}</td>
                           <td>{$row['paymentType']}</td>
                           <td>₱ {$row['amount']}</td>
                           <td>
                               <a href='functions/view-file.php?file=" . urlencode($row['filePath']) . "' target='_blank'>View File</a> 
                               <a href='functions/download.php?file=" . urlencode($row['filePath']) . "' target='_blank'>Download File</a> 
                           </td>
                           <td>{$row['paymentDate']}</td>
                           <td>{$row['paymentStatus']}</td>
                         </tr>";
               }
           } else {
               echo "<tr><td colspan='7'>No Payment Found</td></tr>";
           }
           ?>

        </tbody>
      </table>
     </div>


    </div>
  </div>
</div>

<?php require "../Agent Section/includes/scripts.php"; ?>

<!-- Modal -->
<div class="modal fade" id="paymentModal<?= $transactionNumber ?>" tabindex="-1" aria-labelledby="paymentModalLabel<?= $transactionNumber ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModalLabel<?= $transactionNumber ?>">Payment for Transaction #<?= $transactionNumber ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="../Agent Section/functions/agent-transactionPayment-code.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="transactionNumber" value="<?= $transactionNumber ?>">
          <input type="hidden" name="accountId" value="<?= $accountId ?>">

          <div class="mb-3">
            <label class="form-label">Payment for:</label>
            <select class="form-select" name="paymentTitle" required>
              <option selected disabled>Select Payment Title</option>
              <option value="Package Payment">Package Payment</option>
              <option value="Request Payment">Request Payment</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Type</label>
            <select class="form-select" name="paymentType" required>
              <option selected disabled>Select Payment Type</option>
              <option value="Downpayment">Downpayment</option>
              <option value="Partial Payment">Partial Payment</option>
              <option value="Full Payment">Full Payment</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Amount</label>
            <input type="number" class="form-control" name="amount" placeholder="Enter payment Amount" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Proof of Payment</label>
            <div class="mb-3">
              <input type="file" class="form-control" name="proofs[]" accept="image/*,application/pdf" multiple>
            </div>
            <!-- List of file names -->
            <ul id="fileList<?= $transactionNumber ?>" class="list-unstyled mt-2"></ul>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="payment" class="btn btn-primary">Submit payment</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function () 
  {
    // Target all buttons that trigger a modal
    const paymentModals = document.querySelectorAll('[data-bs-toggle="modal"]');
    
    paymentModals.forEach(button => 
    {
      button.addEventListener('click', function () 
      {
        const transactionNumber = button.getAttribute('data-transact-no');
        const accountId = button.getAttribute('data-account-id');

        // Target the modal associated with this transaction
        const modal = document.getElementById(`paymentModal${transactionNumber}`);

        // Set the hidden input fields with the correct transaction data
        modal.querySelector('[name="transactionNumber"]').value = transactionNumber;
        modal.querySelector('[name="accountId"]').value = accountId;

        // Show the modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
      });
    });
  });
</script>

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


</script>

  </body>
</html>