<?php
// Check if 'id' is passed in the URL
if (isset($_GET['id'])) {
  $transactionNumber = htmlspecialchars($_GET['id']);
}
?>

<!-- Visa Requirements Table -->
<div class="tab-pane fade" id="pills-visa" role="tabpanel" aria-labelledby="pills-visa-tab" tabindex="0">

  <div class="tabs-wrapper">

    <div class="table-header">
       <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal" data-transaction-id="<?= $transactionNumber ?>">Add Request</button> -->
    </div>

    <div class="table-container">
      <div class="table-wrapper-container">

        <table class="product-table">
          <thead>
            <tr>
              <th>GUEST ID</th>
              <th>GUEST NAME</th>
              <th>PASSPORT</th>
              <th>PERMIT</th>
              <th>VALID ID</th>
              <th>CERTIFICATE</th>
              <th>GUARANTEED LETTER</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sql1 = "SELECT v.requirementId, v.guestId, v.fileType, v.filePath,
                    CONCAT(g.fName, ' ', IF(g.mName = 'N/A' OR g.mName IS NULL, '', CONCAT(SUBSTRING(g.mName, 1, 1), '. ')), 
                    g.lName, IF(g.suffix = 'N/A' OR g.suffix IS NULL, '', CONCAT(' ', g.suffix))) AS guestName
                  FROM visarequirements v
                  INNER JOIN guest g ON v.guestId = g.guestId
                  WHERE v.transactNo = '$transactionNumber'";

            $res1 = $conn->query($sql1);

            $filesByGuest = [];
            if ($res1->num_rows > 0) {
              while ($row = $res1->fetch_assoc()) {
                $guestId = $row['guestId'];
                $fileType = $row['fileType'];
                $filePath = $row['filePath'] ?? ''; // Ensure it's not NULL
                $requirementId = $row['requirementId'];

                // Initialize guest data if not set
                if (!isset($filesByGuest[$guestId])) {
                  $filesByGuest[$guestId]['guestName'] = $row['guestName'];
                  $filesByGuest[$guestId]['files'] = [];
                }

                // Store both requirementId and filePath together
                $filesByGuest[$guestId]['files'][$fileType][] = [
                  'filePath' => $filePath,
                  'requirementId' => $requirementId
                ];
              }
            }

            if (!empty($filesByGuest)) {
              foreach ($filesByGuest as $guestId => $guestData) {
                echo "<tr>
                      <td>{$guestId}</td>
                      <td>{$guestData['guestName']}</td>";

                // Define the expected file types
                $fileTypes = ['passport', 'permit', 'validId', 'certificate', 'guaranteedLetter'];

                // Generate table columns dynamically based on available/missing files
                foreach ($fileTypes as $fileType) {
                  echo "<td>";

                  if (!empty($guestData['files'][$fileType])) {
                    foreach ($guestData['files'][$fileType] as $file)  // ✅ Now correctly accessing both
                    {
                      $filePath = !empty($file['filePath']) ? $file['filePath'] : ''; // Ensure no NULL values
            
                      echo "<div >
                            <a class='btn btn-info btn-sm' 
                              href='../Agent Section/functions/view-file.php?file=" . urlencode($filePath) . "' target='_blank'>View File</a> 

                            <a class='btn btn-success btn-sm' 
                              href='../Agent Section/functions/download.php?file=" . urlencode($filePath) . "' target='_blank'>Download File</a>

                            <button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' 
                              data-bs-target='#confirmDeleteModal' data-fileid='" . $file['requirementId'] . "'>
                                Remove
                            </button>
                          </div>";
                    }
                  } else {
                    echo "<button class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#uploadModal' 
                          data-guestid='{$guestId}' data-filetype='$fileType'>Upload " . ucfirst($fileType) . "</button>";
                  }

                  echo "</td>";
                }

                echo "</tr>";
              }
            } else {
              echo "<tr><td colspan='7' style='text-align: center;'>No Visa Requirements</td></tr>";
            }
            ?>
          </tbody>
        </table>

      </div>
    </div>

  </div>
</div>

<!-- Upload Modal For Existing -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadModalLabel">Upload File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="uploadForm" action="../Agent Section/functions/agent-visaRequirementsUpdate-code.php" method="POST"
        enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="guestId" id="modalGuestId">
          <input type="hidden" name="fileType" id="modalFileType">
          <input type="hidden" name="transactNo" value="<?php echo $transactionNumber; ?>">
          <input type="hidden" name="accountId" value="<?php echo $accountId; ?>">

          <div class="mb-3">
            <label for="fileInput" class="form-label">Select File</label>
            <input type="file" class="form-control" name="file" id="fileInput" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="upload" class="btn btn-success">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
  aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action='../Agent Section/functions/agent-removeVisaRequirements-code.php' method='POST'>
        <div class="modal-body">
          Are you sure you want to remove this file?
          <input type="hidden" name="fileId" id="fileIdToDelete">
          <input type="hidden" name="transactNo" value="<?php echo $transactionNumber; ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" name="remove">Yes, Remove</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Remove Modal Fetch Data  -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    var confirmDeleteModal = document.getElementById('confirmDeleteModal');
    confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget; // Button that triggered the modal
      var fileId = button.getAttribute('data-fileid'); // Get file ID
      document.getElementById('fileIdToDelete').value = fileId; // Set hidden input value
    });
  });
</script>

<!-- Clickable Row -->
<script>
  var uploadModal = document.getElementById('uploadModal');
  uploadModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget; // Button that triggered the modal
    var guestId = button.getAttribute('data-guestid');
    var fileType = button.getAttribute('data-filetype');

    document.getElementById('modalGuestId').value = guestId;
    document.getElementById('modalFileType').value = fileType;

    // Change modal title dynamically
    document.getElementById('uploadModalLabel').innerText = "Upload " + fileType.charAt(0).toUpperCase() + fileType.slice(1);
  });
</script>