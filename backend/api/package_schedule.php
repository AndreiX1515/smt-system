<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../conn.php';

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET   -   
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $packageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($packageId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid package ID'
        ]);
        exit();
    }

    try {
        // Get basic schedule info from product_schedules
        $scheduleQuery = "SELECT * FROM product_schedules WHERE productId = ? ORDER BY dayNumber";
        $stmt = $conn->prepare($scheduleQuery);
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $scheduleResult = $stmt->get_result();
        $schedules = [];

        while ($row = $scheduleResult->fetch_assoc()) {
            $schedules[] = $row;
        }

        // Get detailed itinerary from package_itinerary
        $itineraryQuery = "SELECT * FROM package_itinerary WHERE packageId = ? ORDER BY dayNumber";
        $stmt = $conn->prepare($itineraryQuery);
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $itineraryResult = $stmt->get_result();
        $itineraries = [];

        while ($row = $itineraryResult->fetch_assoc()) {
            // Parse activities into array
            if ($row['activities']) {
                $row['activitiesList'] = explode("\n", $row['activities']);
            }

            // Format meals
            if ($row['meals']) {
                $mealTypes = [];
                if ($row['meals'] == 'all_meals') {
                    $mealTypes = ['breakfast', 'lunch', 'dinner'];
                } else if ($row['meals'] != 'none') {
                    $mealTypes[] = $row['meals'];
                }
                $row['mealsList'] = $mealTypes;
            }

            $itineraries[] = $row;
        }

        // Combine schedule and itinerary data
        $combinedData = [];
        foreach ($schedules as $schedule) {
            $dayNumber = $schedule['dayNumber'];
            $dayData = $schedule;

            // Find matching itinerary
            foreach ($itineraries as $itinerary) {
                if ($itinerary['dayNumber'] == $dayNumber) {
                    // Merge itinerary data into schedule
                    $dayData = array_merge($dayData, $itinerary);
                    break;
                }
            }

            $combinedData[] = $dayData;
        }

        // If no schedule data exists, return itinerary data only
        if (empty($combinedData) && !empty($itineraries)) {
            $combinedData = $itineraries;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'packageId' => $packageId,
                'schedules' => $combinedData,
                'totalDays' => count($combinedData)
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching schedule data: ' . $e->getMessage()
        ]);
    }

    exit();
}

// POST   -    ()
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Admin authentication check would go here

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['packageId']) || !isset($data['dayNumber'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        exit();
    }

    try {
        // Insert into package_itinerary
        $query = "INSERT INTO package_itinerary (packageId, dayNumber, title, description, activities,
                  accommodation, meals, transportation, startTime, endTime, location, locationAddress, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iisssssssssss",
            $data['packageId'],
            $data['dayNumber'],
            $data['title'],
            $data['description'],
            $data['activities'],
            $data['accommodation'],
            $data['meals'],
            $data['transportation'],
            $data['startTime'],
            $data['endTime'],
            $data['location'],
            $data['locationAddress'],
            $data['notes']
        );

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule added successfully',
                'itineraryId' => $conn->insert_id
            ]);
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding schedule: ' . $e->getMessage()
        ]);
    }

    exit();
}

// PUT   -   ()
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Admin authentication check would go here

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['itineraryId'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing itinerary ID'
        ]);
        exit();
    }

    try {
        $updateFields = [];
        $updateValues = [];
        $types = "";

        // Build dynamic update query
        $allowedFields = ['title', 'description', 'activities', 'accommodation',
                         'meals', 'transportation', 'startTime', 'endTime',
                         'location', 'locationAddress', 'notes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $data[$field];
                $types .= "s";
            }
        }

        if (empty($updateFields)) {
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
            exit();
        }

        $updateValues[] = $data['itineraryId'];
        $types .= "i";

        $query = "UPDATE package_itinerary SET " . implode(', ', $updateFields) . " WHERE itineraryId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$updateValues);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully'
            ]);
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating schedule: ' . $e->getMessage()
        ]);
    }

    exit();
}

// DELETE   -   ()
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Admin authentication check would go here

    $itineraryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($itineraryId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid itinerary ID'
        ]);
        exit();
    }

    try {
        $query = "DELETE FROM package_itinerary WHERE itineraryId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $itineraryId);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting schedule: ' . $e->getMessage()
        ]);
    }

    exit();
}

// Method not allowed
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);
?>