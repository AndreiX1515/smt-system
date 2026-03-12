<?php
require "../../conn.php"; // DB connection
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
  $reportType = $_POST['reportType'] ?? '';
  $reportFor = $_POST['reportFor'] ?? '';
  $month = $_POST['month'] ?? '';
  $week = $_POST['week'] ?? '';
  $flightDate = $_POST['flightDate'] ?? '';
  $agentCode = $_POST['agentCode'] ?? '';
  $agentIdSelect = $_POST['selectedAgent'] ?? '';
  $clientIdSelect = $_POST['selectedClient'] ?? '';
  $accountId = $_POST['accountId'] ?? '';

  // Monthly report
  if (($reportType === 'monthly' && $month) && $reportFor === 'all') 
  {
    // Convert month name to numeric format
    $monthNum = date('m', strtotime($month));
    $year = date('Y'); // Or get from POST if dynamic

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              c.fName as cfName, c.lName as clName, c.mName as cmName, a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            LEFT JOIN agent a ON a.accountId = b.accountId AND b.accountType = 'Agent'
            LEFT JOIN client c ON c.accountId = b.accountId AND b.accountType = 'Client'
            WHERE MONTH(b.bookingDate) = ? AND YEAR(b.bookingDate) = ? AND (a.agentCode = ? OR c.clientCode = ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $monthNum, $year, $agentCode, $agentCode);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      // Build full names
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");
      $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");

      // Decide whose name to show — agent if exists, else client
      $name = $agentFullName ?: $clientFullName;

      // Combine flight dates into one string
      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $name,                                  // AGENT NAME
        'flightDate' => $flightDate,                      // FLIGHT DATE
        'pax' => $row['pax'],                             // PAX
        'amount' => number_format($row['totalPrice'], 2), // AMOUNT
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for $month.";
    } 
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'monthly' && $month) && $reportFor === 'self') 
  {
    // Convert month name to numeric format
    $monthNum = date('m', strtotime($month));
    $year = date('Y'); // Or get from POST if dynamic

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN agent a ON a.accountId = b.accountId
            WHERE MONTH(b.bookingDate) = ? AND YEAR(b.bookingDate) = ? AND b.accountId = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $monthNum, $year, $accountId);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      // Build full names
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

      // Combine flight dates into one string
      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $agentFullName,                                  // AGENT NAME
        'flightDate' => $flightDate,                      // FLIGHT DATE
        'pax' => $row['pax'],                             // PAX
        'amount' => number_format($row['totalPrice'], 2), // AMOUNT
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for $month.";
    } 
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'monthly' && $month) && $reportFor === 'agent') 
  {
    // Convert month name to numeric format
    $monthNum = date('m', strtotime($month));
    $year = date('Y'); // Or get from POST if dynamic

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN agent a ON a.accountId = b.accountId
            WHERE MONTH(b.bookingDate) = ? AND YEAR(b.bookingDate) = ? AND a.agentId = ? AND b.accountType = 'Agent'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $monthNum, $year, $agentIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      // Build full names
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

      // Combine flight dates into one string
      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $agentFullName,                                  // AGENT NAME
        'flightDate' => $flightDate,                      // FLIGHT DATE
        'pax' => $row['pax'],                             // PAX
        'amount' => number_format($row['totalPrice'], 2), // AMOUNT
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for $month.";
    } 
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'monthly' && $month) && $reportFor === 'client')
  {
    // Convert month name to numeric format
    $monthNum = date('m', strtotime($month));
    $year = date('Y'); // Or get from POST if dynamic

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              c.fName as cfName, c.lName as clName, c.mName as cmName
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN client c ON c.accountId = b.accountId
            WHERE MONTH(b.bookingDate) = ? AND YEAR(b.bookingDate) = ? AND c.clientId = ?  AND b.accountType = 'Client'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $monthNum, $year, $clientIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      // Build full names
      $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");

      // Combine flight dates into one string
      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $clientFullName,                                  // AGENT NAME
        'flightDate' => $flightDate,                      // FLIGHT DATE
        'pax' => $row['pax'],                             // PAX
        'amount' => number_format($row['totalPrice'], 2), // AMOUNT
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for $month.";
    } 
    else 
    {
      $response['data'] = $reportData;
    }
  }
  // Weekly report
  elseif (($reportType === 'weekly' && $week) && $reportFor === 'all') 
  {
    // Assume week format is YYYY-W## (e.g., 2025-W15)
    $weekParts = explode('-W', $week);
    $year = $weekParts[0];
    $weekNum = $weekParts[1];

    // Get the Monday of the selected week
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$weekNum);
    $startDate = $dto->format('Y-m-d');

    // Get the Sunday of the selected week
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              c.fName as cfName, c.lName as clName, c.mName as cmName, a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            LEFT JOIN agent a ON a.accountId = b.accountId AND b.accountType = 'Agent'
            LEFT JOIN client c ON c.accountId = b.accountId AND b.accountType = 'Client'
            WHERE b.bookingDate BETWEEN ? AND ? AND (a.agentCode = ? OR c.clientCode = ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $startDate, $endDate, $agentCode, $agentCode);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");
      $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");
      $name = $agentFullName ?: $clientFullName;

      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $name,
        'flightDate' => $flightDate,
        'pax' => $row['pax'],
        'amount' => number_format($row['totalPrice'], 2),
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for the week of $startDate to $endDate.";
    }
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'weekly' && $week) && $reportFor === 'self') 
  {
    // Assume week format is YYYY-W## (e.g., 2025-W15)
    $weekParts = explode('-W', $week);
    $year = $weekParts[0];
    $weekNum = $weekParts[1];

    // Get the Monday of the selected week
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$weekNum);
    $startDate = $dto->format('Y-m-d');

    // Get the Sunday of the selected week
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN agent a ON a.accountId = b.accountId
            WHERE (b.bookingDate BETWEEN ? AND ?) AND b.accountId = ? AND b.accountType = 'Agent'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $startDate, $endDate, $accountId);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $agentFullName,
        'flightDate' => $flightDate,
        'pax' => $row['pax'],
        'amount' => number_format($row['totalPrice'], 2),
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for the week of $startDate to $endDate.";
    }
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'weekly' && $week) && $reportFor === 'agent')
  {
    // Assume week format is YYYY-W## (e.g., 2025-W15)
    $weekParts = explode('-W', $week);
    $year = $weekParts[0];
    $weekNum = $weekParts[1];

    // Get the Monday of the selected week
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$weekNum);
    $startDate = $dto->format('Y-m-d');

    // Get the Sunday of the selected week
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              a.fName as afName, a.lName as alName, a.mName as amName 
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN agent a ON a.accountId = b.accountId
            WHERE (b.bookingDate BETWEEN ? AND ?) AND a.agentId = ? AND b.accountType = 'Agent'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $startDate, $endDate, $agentIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $agentFullName,
        'flightDate' => $flightDate,
        'pax' => $row['pax'],
        'amount' => number_format($row['totalPrice'], 2),
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for the week of $startDate to $endDate.";
    }
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'weekly' && $week) && $reportFor === 'client')
  {
    // Assume week format is YYYY-W## (e.g., 2025-W15)
    $weekParts = explode('-W', $week);
    $year = $weekParts[0];
    $weekNum = $weekParts[1];

    // Get the Monday of the selected week
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$weekNum);
    $startDate = $dto->format('Y-m-d');

    // Get the Sunday of the selected week
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');

    $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
              c.fName as cfName, c.lName as clName, c.mName as cmName
            FROM booking b
            JOIN flight f ON f.flightId = b.flightId
            JOIN client c ON c.accountId = b.accountId
            WHERE (b.bookingDate BETWEEN ? AND ?) AND c.clientId = ? AND b.accountType = 'Client'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $startDate, $endDate, $clientIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    $reportData = [];
    while ($row = $result->fetch_assoc()) 
    {
      $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");

      $flightDate = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

      $reportData[] = 
      [
        'name' => $clientFullName,
        'flightDate' => $flightDate,
        'pax' => $row['pax'],
        'amount' => number_format($row['totalPrice'], 2),
      ];
    }

    if (empty($reportData)) 
    {
      $response['error'] = "No records found for the week of $startDate to $endDate.";
    }
    else 
    {
      $response['data'] = $reportData;
    }
  }
  elseif (($reportType === 'flight' && !empty($flightDate)) && $reportFor === 'all') 
  {
    // Step 1: Retrieve flightId(s) for the given flightDepartureDate
    $stmt = $conn->prepare("SELECT flightId FROM flight WHERE flightDepartureDate = ?");
    $stmt->bind_param("s", $flightDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $flightIds = [];
    while ($row = $result->fetch_assoc()) 
    {
      $flightIds[] = $row['flightId'];
    }
    $stmt->close();

    if (empty($flightIds)) 
    {
      $response['error'] = "No flights found for the selected date.";
    } 
    else 
    {
      // Step 2: Fetch bookings associated with the retrieved flightId(s)
      // Create placeholders for the IN clause
      $placeholders = implode(',', array_fill(0, count($flightIds), '?'));
      $types = str_repeat('i', count($flightIds)) . 'ss'; // 'i' for each flightId, 's' for agentCode and clientCode

      $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
                c.fName as cfName, c.lName as clName, c.mName as cmName, a.fName as afName, a.lName as alName, a.mName as amName 
              FROM booking b
              JOIN flight f ON f.flightId = b.flightId
              LEFT JOIN agent a ON a.accountId = b.accountId AND b.accountType = 'Agent'
              LEFT JOIN client c ON c.accountId = b.accountId AND b.accountType = 'Client'
              WHERE b.flightId IN ($placeholders) AND (a.agentCode = ? OR c.clientCode = ?)";

        $stmt = $conn->prepare($sql);
        $params = array_merge($flightIds, [$agentCode, $agentCode]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $reportData = [];
        while ($row = $result->fetch_assoc()) 
        {
          // Build full names
          $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");
          $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");

          // Decide whose name to show — agent if exists, else client
          $name = $agentFullName ?: $clientFullName;

          // Combine flight dates into one string
          $flightDateFormatted = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

          $reportData[] = 
          [
            'name' => $name,
            'flightDate' => $flightDateFormatted,
            'pax' => $row['pax'],
            'amount' => number_format($row['totalPrice'], 2),
          ];
        }

        if (empty($reportData)) 
        {
          $response['error'] = "No bookings found for the selected flight date.";
        } 
        else 
        {
          $response['data'] = $reportData;
        }
    }
  }
  elseif (($reportType === 'flight' && !empty($flightDate)) && $reportFor === 'self') 
  {
    // Step 1: Retrieve flightId(s) for the given flightDepartureDate
    $stmt = $conn->prepare("SELECT flightId FROM flight WHERE flightDepartureDate = ?");
    $stmt->bind_param("s", $flightDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $flightIds = [];
    while ($row = $result->fetch_assoc()) 
    {
      $flightIds[] = $row['flightId'];
    }
    $stmt->close();

    if (empty($flightIds)) 
    {
      $response['error'] = "No flights found for the selected date.";
    }
    else
    {
      // Step 2: Fetch bookings associated with the retrieved flightId(s)
      // Create placeholders for the IN clause
      $placeholders = implode(',', array_fill(0, count($flightIds), '?'));
      $types = str_repeat('i', count($flightIds)) . 'i'; // 'i' for each flightId, 's' for agentCode and clientCode

      $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
                a.fName as afName, a.lName as alName, a.mName as amName
              FROM booking b
              JOIN flight f ON f.flightId = b.flightId
              JOIN agent a ON a.accountId = b.accountId
              WHERE b.flightId IN ($placeholders) AND a.accountId = ? AND b.accountType = 'Agent'";

        $stmt = $conn->prepare($sql);
        $params = array_merge($flightIds, [$accountId]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $reportData = [];
        while ($row = $result->fetch_assoc()) 
        {
          // Build full names
          $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

          // Combine flight dates into one string
          $flightDateFormatted = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

          $reportData[] = 
          [
            'name' => $agentFullName,
            'flightDate' => $flightDateFormatted,
            'pax' => $row['pax'],
            'amount' => number_format($row['totalPrice'], 2),
          ];
        }

        if (empty($reportData)) 
        {
          $response['error'] = "No bookings found for the selected flight date.";
        } 
        else 
        {
          $response['data'] = $reportData;
        }
    }
  }
  elseif (($reportType === 'flight' && !empty($flightDate)) && $reportFor === 'agent')
  {
    // Step 1: Retrieve flightId(s) for the given flightDepartureDate
    $stmt = $conn->prepare("SELECT flightId FROM flight WHERE flightDepartureDate = ?");
    $stmt->bind_param("s", $flightDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $flightIds = [];
    while ($row = $result->fetch_assoc()) 
    {
      $flightIds[] = $row['flightId'];
    }
    $stmt->close();

    if (empty($flightIds)) 
    {
      $response['error'] = "No flights found for the selected date.";
    }
    else
    {
      // Step 2: Fetch bookings associated with the retrieved flightId(s)
      // Create placeholders for the IN clause
      $placeholders = implode(',', array_fill(0, count($flightIds), '?'));
      $types = str_repeat('i', count($flightIds)) . 's'; // 'i' for each flightId, 's' for agentCode and clientCode

      $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
                a.fName as afName, a.lName as alName, a.mName as amName
              FROM booking b
              JOIN flight f ON f.flightId = b.flightId
              JOIN agent a ON a.accountId = b.accountId
              WHERE b.flightId IN ($placeholders) AND a.agentId = ? AND b.accountType = 'Agent'";

        $stmt = $conn->prepare($sql);
        $params = array_merge($flightIds, [$agentIdSelect]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $reportData = [];
        while ($row = $result->fetch_assoc()) 
        {
          // Build full names
          $agentFullName = trim("{$row['afName']} {$row['amName']} {$row['alName']}");

          // Combine flight dates into one string
          $flightDateFormatted = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

          $reportData[] = 
          [
            'name' => $agentFullName,
            'flightDate' => $flightDateFormatted,
            'pax' => $row['pax'],
            'amount' => number_format($row['totalPrice'], 2),
          ];
        }

        if (empty($reportData)) 
        {
          $response['error'] = "No bookings found for the selected flight date.";
        } 
        else 
        {
          $response['data'] = $reportData;
        }
    }
  }
  elseif (($reportType === 'flight' && !empty($flightDate)) && $reportFor === 'client')
  {
    // Step 1: Retrieve flightId(s) for the given flightDepartureDate
    $stmt = $conn->prepare("SELECT flightId FROM flight WHERE flightDepartureDate = ?");
    $stmt->bind_param("s", $flightDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $flightIds = [];
    while ($row = $result->fetch_assoc()) 
    {
      $flightIds[] = $row['flightId'];
    }
    $stmt->close();

    if (empty($flightIds)) 
    {
      $response['error'] = "No flights found for the selected date.";
    } 
    else 
    {
      // Step 2: Fetch bookings associated with the retrieved flightId(s)
      // Create placeholders for the IN clause
      $placeholders = implode(',', array_fill(0, count($flightIds), '?'));
      $types = str_repeat('i', count($flightIds)) . 's'; // 'i' for each flightId, 's' for agentCode and clientCode

      $sql = "SELECT b.pax, b.totalPrice, f.flightDepartureDate AS departureDate, f.returnArrivalDate AS arrivalDate, 
                c.fName as cfName, c.lName as clName, c.mName as cmName
              FROM booking b
              JOIN flight f ON f.flightId = b.flightId
              JOIN client c ON c.accountId = b.accountId
              WHERE b.flightId IN ($placeholders) AND c.clientId = ? AND b.accountType = 'Client'";

        $stmt = $conn->prepare($sql);
        $params = array_merge($flightIds, [$clientIdSelect]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $reportData = [];
        while ($row = $result->fetch_assoc()) 
        {
          // Build full names
          $clientFullName = trim("{$row['cfName']} {$row['cmName']} {$row['clName']}");

          // Combine flight dates into one string
          $flightDateFormatted = date("F j, Y", strtotime($row['departureDate'])) . " - " . date("F j, Y", strtotime($row['arrivalDate']));

          $reportData[] = 
          [
            'name' => $clientFullName,
            'flightDate' => $flightDateFormatted,
            'pax' => $row['pax'],
            'amount' => number_format($row['totalPrice'], 2),
          ];
        }

        if (empty($reportData)) 
        {
          $response['error'] = "No bookings found for the selected flight date.";
        } 
        else 
        {
          $response['data'] = $reportData;
        }
    }
  }
  else 
  {
    $response['error'] = 'Missing or invalid report type/selection';
  }
}
else 
{
  $response['error'] = 'Invalid request method';
}

echo json_encode($response);
?>
