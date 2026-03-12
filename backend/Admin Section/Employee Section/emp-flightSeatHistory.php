<div class="table-header">
	<div class="search-wrapper">
		<div class="search-input-wrapper">
			<input type="text" id="search" name="search" placeholder="Search here..">
		</div>
	</div>



	<div class="second-header-wrapper">
		<div class="buttons-wrapper">
			<button id="clearSorting" class="btn btn-secondary">Clear Filters</button>
		</div>
	</div>
</div>

<div class="table-wrapper">
	<table class="product-table" id="product-table">
		<thead>
			<tr>
				<th>Team OP</th>
				<th>Package</th>
				<th>Flight Date</th>
				<th>Wholesale Price</th>
				<th>Retail Price</th>
				<th>Land Price</th>
				<th>Available Seats</th>
			</tr>
		</thead>
		<tbody>
			<?php
				$sql1 = "SELECT f.* , e.fName as fName, e.mName as mName, e.lName as lName, p.packageName as packageName, f.landPrice as landPrice
									FROM flight f
									LEFT JOIN employee e ON f.employeeId = e.employeeId
									JOIN package p ON f.packageId = p.packageId";
				$res1 = $conn->query($sql1);

				if ($res1->num_rows > 0) 
				{
					while ($row = $res1->fetch_assoc()) 
					{
						$departureDate = date("Y.m.d", strtotime($row['flightDepartureDate']));
						$returnDate = date("Y.m.d", strtotime($row['returnArrivalDate']));
						$fName = $row['fName'] ?? null;
						$mName = $row['mName'] ?? null;
						$lName = $row['lName'] ?? null;
						$wholesaleFormatted = number_format($row['wholesalePrice'], 2);
						$landPriceFormatted = number_format($row['landPrice'], 2);
						$flightFormatted = number_format($row['flightPrice'], 2);
						if (empty($fName) && empty($lName)) 
						{
							$fullName = "No Team OP";
						} else {
							$middleInitial = $mName ? strtoupper(substr($mName, 0, 1)) . '.' : '';
							$fullName = $lName . ", " . $fName . " " . $middleInitial;
						}
						echo "<tr>
										<td>" . $fullName . "</td>
										<td>" . $row['packageName'] . "</td>
										<td>" . $departureDate ." - ". $returnDate. "</td>
										<td>₱ " . $wholesaleFormatted . "</td>
										<td>₱ " . $flightFormatted . "</td>
										<td>₱ " . $landPriceFormatted . "</td>
										<td>" . $row['availSeats'] . "</td>
									</tr>";
					}
				} else {
					echo "<tr><td colspan='6' style='text-align: center;'>No Flights Found</td></tr>";
				}
			?>
		</tbody>
	</table>
</div>

<div class="table-footer">
	<div class="pagination-controls">
		<button id="prevPage" class="pagination-btn">Previous</button>
		<span id="pageInfo" class="page-info">Page 1 of 1</span>
		<button id="nextPage" class="pagination-btn">Next</button>
	</div>
</div>