<?php
/**
 * DB       
 */

require_once __DIR__ . '/../../../backend/conn.php';

try {
    // bookingStatus  →  
    $statusMap = [
        ' ' => 'confirmed',
        ' ' => 'completed',
        ' ' => 'cancelled',
        ' ' => 'refunded',
        '  ' => 'pending_deposit',
        '  ' => 'pending_balance'
    ];
    
    foreach ($statusMap as $korean => $english) {
        $sql = "UPDATE bookings SET bookingStatus = ? WHERE bookingStatus = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $english, $korean);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        if ($affected > 0) {
            echo "Updated $affected rows: '$korean' → '$english'\n";
        }
    }
    
    // paymentStatus  →  
    $paymentStatusMap = [
        '  ' => 'pending',
        '  ' => 'partial',
        ' ' => 'partial',
        ' ' => 'paid',
        ' ' => 'paid'
    ];
    
    foreach ($paymentStatusMap as $korean => $english) {
        $sql = "UPDATE bookings SET paymentStatus = ? WHERE paymentStatus = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $english, $korean);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        if ($affected > 0) {
            echo "Updated $affected rows: paymentStatus '$korean' → '$english'\n";
        }
    }
    
    echo "Status conversion completed.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

