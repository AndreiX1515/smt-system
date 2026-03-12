<?php
  require "../../conn.php"; // Include your DB connection file

  // Check if packageId and origin are set
  if (isset($_POST['packageId']) && isset($_POST['origin'])) 
  {
    $packageId = $_POST['packageId'];
    $origin = $_POST['origin'];

    // SQL query to fetch distinct years for the selected origin and packageId
    $sql = "SELECT DISTINCT YEAR(flightDepartureDate) AS year_only
            FROM flight
            WHERE origin = ? AND packageId = ?
            ORDER BY year_only";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) 
    {
      // Bind the parameters
      $stmt->bind_param("si", $origin, $packageId);  // "si" for string (origin) and integer (packageId)

      // Execute the statement
      if ($stmt->execute()) 
      {
        // Get the result
        $result = $stmt->get_result();

        // Check if any years were found
        if ($result->num_rows > 0) 
        {
          // Start creating the dropdown options
          $options = '<option selected disabled>Select Year</option>';
          while ($row = $result->fetch_assoc()) 
          {
            $year = $row['year_only'];
            $options .= "<option value=\"$year\">$year</option>";
          }
          echo $options;
        } 
        else 
        {
          // No results found
          echo '<option selected disabled>No years available</option>';
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
