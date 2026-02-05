<?php
/**
 * Public Agents API
 * Returns agent locations for the Contact Agent page
 * Only returns agents with location information (latitude/longitude)
 */

require_once __DIR__ . '/../conn.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Query agents with location information
    $sql = "SELECT
                a.id,
                a.agencyName,
                a.storeName,
                a.storeAddress,
                a.latitude,
                a.longitude,
                a.contactNo,
                a.personInChargeEmail
            FROM agent a
            WHERE a.latitude IS NOT NULL
              AND a.longitude IS NOT NULL
            ORDER BY a.storeName ASC, a.agencyName ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $agents = [];
    while ($row = $result->fetch_assoc()) {
        $agents[] = [
            'id' => (int)$row['id'],
            'companyName' => $row['agencyName'] ?? '',
            'storeName' => $row['storeName'] ?? $row['agencyName'] ?? '',
            'storeAddress' => $row['storeAddress'] ?? '',
            'latitude' => $row['latitude'] ? floatval($row['latitude']) : null,
            'longitude' => $row['longitude'] ? floatval($row['longitude']) : null,
            'phone' => $row['contactNo'] ?? '',
            'email' => $row['personInChargeEmail'] ?? ''
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $agents,
        'count' => count($agents)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}

$conn->close();
