
<?php
  require "../conn.php"; // DB connection
	session_start(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Employee - Transactions</title>
	<?php include '../Employee Section/includes/emp-head.php' ?>
	<link rel="stylesheet" href="../Employee Section/assets/css/emp-transactionRequestPayment.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../Employee Section/includes/emp-sidebar.php' ?>

<!-- Main Container -->
<div class="main-container">
  <?php include '../Employee Section/includes/emp-navbar.php' ?>

  <div class="main-content">
    <div class="table-container">

    <!-- Nav pills -->
<ul class="nav nav-pills mb-3" id="flightTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="list-tab" data-bs-toggle="pill" data-bs-target="#list" type="button" role="tab">Flight List</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="entry-tab" data-bs-toggle="pill" data-bs-target="#entry" type="button" role="tab">Add Flight Date</button>
  </li>
</ul>

<!-- Tab content -->
<div class="tab-content" id="flightTabContent">
  <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
    <?php include 'emp-flightSeatHistory.php'; ?>
  </div>
  <div class="tab-pane fade" id="entry" role="tabpanel" aria-labelledby="entry-tab">
    <?php include 'emp-flightDataEntry.php'; ?>
  </div>
</div>

      

    </div>
  </div>
</div>


<?php include '../Employee Section/includes/emp-scripts.php' ?>

<!-- Data table Script -->
<script>
  $(document).ready(function () {
    const table = $('#product-table').DataTable({
      dom: 'rtip', // You can change this to 'Bfrtip' if you add buttons (like export)
      scrollX: true,
      paging: true,
      pageLength: 18,
      autoWidth: false,
      order: [[2, 'asc']],
      language: {
        emptyTable: "No Flights Found"
      }
    });

    // Live search
    $('#search').on('keyup', function () {
      table.search(this.value).draw();
    });

    // Clear search button
    $('#clearSorting').on('click', function () {
      $('#search').val('');
      table.search('').draw();
    });

    // Pagination logic
    function updatePagination() {
      const info = table.page.info();
      $('#pageInfo').text(`Page ${info.page + 1} of ${info.pages}`);
      $('#prevPage').prop('disabled', info.page === 0);
      $('#nextPage').prop('disabled', info.page === info.pages - 1);
    }

    $('#prevPage').on('click', function () {
      table.page('previous').draw('page');
      updatePagination();
    });

    $('#nextPage').on('click', function () {
      table.page('next').draw('page');
      updatePagination();
    });

    // Update pagination after every draw
    table.on('draw', updatePagination);
    updatePagination();
  });
</script>

</body>
</html>
