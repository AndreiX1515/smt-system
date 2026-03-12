<div class="tab-pane fade" id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab" tabindex="0">

    <!-- <div class="card-header px-3 py-1">
        <h6>Request History</h6>
    </div> -->

    <div class="request-table-wrapper">
        <table class="request-table">
            <?php
            $sql1 = "SELECT request.requestId, concern.concernTitle, concerndetails.details, request.customRequest, 
                DATE_FORMAT(request.requestDate, '%m-%d-%Y') AS formattedRequestDate, 
                request.requestStatus
            FROM request
            LEFT JOIN concern ON request.concernId = concern.concernId
            LEFT JOIN concerndetails ON request.concernDetailsId = concerndetails.concernDetailsId
            WHERE request.transactNo = '$transactNum'";

            $res1 = $conn->query($sql1);

            if ($res1->num_rows > 0) {
                // Only display <thead> if there are rows
                echo "
        <thead>
            <tr>
                <th>REQUEST ID</th>
                <th>REQUEST TITLE</th>
                <th>REQUEST DETAILS</th>
                <th>REQUEST DATE</th>
                <th>STATUS</th>
            </tr>
        </thead>
        <tbody>";
                while ($row = $res1->fetch_assoc()) {
                    // Fetch and process row data
                    $status = $row['requestStatus'];
                    $badgeClass = '';
                    switch ($status) {
                        case 'Confirmed':
                            $badgeClass = 'text-bg-success'; // Green for Confirmed
                            break;
                        case 'Submitted':
                            $badgeClass = 'text-bg-secondary'; // Gray for Submitted
                            break;
                        case 'Rejected':
                            $badgeClass = 'text-bg-danger'; // Red for Rejected
                            break;
                        default:
                            $badgeClass = 'text-bg-info'; // Blue for other statuses
                            break;
                    }
                    $title = $row['concernTitle'] ?? 'Custom Request';
                    $details = $row['details'] ?? $row['customRequest'];

                    echo "
            <tr>
                <td>{$row['requestId']}</td>
                <td>{$title}</td>
                <td>{$details}</td>
                <td>{$row['formattedRequestDate']}</td>
                <td>
                    <span class='badge rounded-pill {$badgeClass} p-2'>{$status}</span>
                </td>
            </tr>";
                }
                echo "</tbody>";
            } else {
                // Hide <thead> and display no requests message
                echo "
        <thead style='display: none;'></thead>
        <tbody>
            <tr style='display: none;'></tr> <!-- Ensures no empty table rows -->
        </tbody>
        <div class='no-requests-container'>
            <span>No Requests Found</span>
        </div>";
            }
            ?>
        </table>

    </div>

</div>