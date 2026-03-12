<?php
  require "../../conn.php"; // Include your DB connection file

  // Check if year, packageId, and origin are set
  if (isset($_POST['year']) && isset($_POST['packageId']) && isset($_POST['origin'])) 
  {
    $year = $_POST['year'];
    $packageId = $_POST['packageId'];
    $origin = $_POST['origin'];

    // SQL query to fetch distinct months for the selected year, origin, and packageId
    $sql = "SELECT DISTINCT MONTH(flightDepartureDate) AS month
            FROM flight
            WHERE YEAR(flightDepartureDate) = ? AND origin = ? AND packageId = ?
            ORDER BY month";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) 
    {
      // Bind the parameters
      $stmt->bind_param("isi", $year, $origin, $packageId);  // "isi" for integer (year), string (origin), and integer (packageId)

      // Execute the statement
      if ($stmt->execute()) 
      {
        // Get the result
        $result = $stmt->get_result();

        // Check if any months were found
        if ($result->num_rows > 0) 
        {
          // Start creating the dropdown options
          $options = '<option selected disabled>Select Month</option>';
          while ($row = $result->fetch_assoc()) 
          {
            $month = $row['month'];
            // Convert month number to month name
            $monthName = DateTime::createFromFormat('!m', $month)->format('F');
            $options .= "<option value=\"$month\">$monthName</option>";
          }
          echo $options;
        } 
        else 
        {
          // No months available
          echo '<option selected disabled>No months available</option>';
        }
      } 
      else 
      {
        echo "Error executing query: " . $stmt->error;
      }

      $stmt->close();
    } 
    else 
    {
      echo "Error preparing statement: " . $conn->error;
    }

    $conn->close();
  } 
  else 
  {
    echo "Invalid input data.";
  }
?>
