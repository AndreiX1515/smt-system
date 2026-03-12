<?php
require_once __DIR__ . '/../conn.php';

// CLI   QUERY_STRING 
if (php_sapi_name() === 'cli') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $accountId = $_GET['accountId'] ?? 1;
    // legacy param: visaId -> applicationId
    $visaId = $_GET['visaId'] ?? null;
    $status = $_GET['status'] ?? 'all';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    // status  (DB -> UI)
    $mapStatus = function($dbStatus) {
        $dbStatus = strtolower((string)$dbStatus);
        if ($dbStatus === 'document_required') return 'pending';      //  
        if ($dbStatus === 'under_review') return 'under_review';     // 
        if ($dbStatus === 'approved' || $dbStatus === 'completed') return 'approved'; //  
        if ($dbStatus === 'rejected') return 'rejected';             // 
        return 'pending';
    };

    if ($visaId) {
        //    
        $stmt = $conn->prepare("
            SELECT 
                v.applicationId,
                v.applicationNo,
                v.applicantName,
                v.visaType,
                v.applicationDate,
                v.processingFee,
                v.departureDate,
                v.returnDate,
                v.destinationCountry,
                v.status,
                v.notes,
                v.submittedAt,
                v.processedAt,
                v.completedAt
            FROM visa_applications v
            WHERE v.applicationId = ? AND v.accountId = ?
        ");
        
        $stmt->bind_param("ii", $visaId, $accountId);
        $stmt->execute();
        $visa = $stmt->get_result()->fetch_assoc();
        
        if (!$visa) {
            send_json_response([
                'success' => false,
                'message' => '    .'
            ], 404);
        }
        
        //    
        $visaOut = [
            'visaId' => intval($visa['applicationId']),
            'applicationNo' => $visa['applicationNo'] ?? '',
            'applicantName' => $visa['applicantName'] ?? '',
            'passportNumber' => '',
            'nationality' => '',
            'visaType' => $visa['visaType'] ?? 'tourist',
            'applicationStatus' => $mapStatus($visa['status'] ?? ''),
            'applicationDate' => $visa['applicationDate'] ?? '',
            'departureDate' => $visa['departureDate'] ?? '',
            'returnDate' => $visa['returnDate'] ?? '',
            'packageName' => $visa['destinationCountry'] ?? '',
            'processingFee' => $visa['processingFee'] ?? 0,
            'submittedDocuments' => [],
            'rejectionReason' => ($visa['status'] ?? '') === 'rejected' ? ($visa['notes'] ?? '') : '',
            'createdAt' => $visa['submittedAt'] ?? null,
            'updatedAt' => $visa['processedAt'] ?? null
        ];
        
        send_json_response([
            'success' => true,
            'data' => $visaOut
        ]);
        
    } else {
        //    
        $whereClause = "WHERE v.accountId = ?";
        $params = [$accountId];
        $paramTypes = "i";
        
        if ($status !== 'all') {
            $st = strtolower((string)$status);
            // UI  DB status 
            if ($st === 'pending') {
                $whereClause .= " AND v.status IN ('pending','document_required')";
            } elseif ($st === 'under_review') {
                $whereClause .= " AND v.status = 'under_review'";
            } elseif ($st === 'approved') {
                $whereClause .= " AND v.status IN ('approved','completed')";
            } elseif ($st === 'rejected') {
                $whereClause .= " AND v.status = 'rejected'";
            }
        }
        
        $stmt = $conn->prepare("
            SELECT
                v.applicationId,
                v.applicationNo,
                v.applicantName,
                v.visaType,
                v.applicationDate,
                v.processingFee,
                v.departureDate,
                v.destinationCountry,
                v.status,
                v.submittedAt,
                COALESCE(b.packageName, v.destinationCountry) as packageName
            FROM visa_applications v
            LEFT JOIN bookings b ON v.transactNo = b.bookingId
            $whereClause
            ORDER BY v.submittedAt DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= "ii";
        
        // PHP 8+ bind_param            
        $bind = [];
        $bind[] = $paramTypes;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $visas = [];
        foreach ($rows as $r) {
            $visas[] = [
                'visaId' => intval($r['applicationId']),
                'applicationNo' => $r['applicationNo'] ?? '',
                'applicantName' => $r['applicantName'] ?? '',
                'passportNumber' => '',
                'nationality' => '',
                'visaType' => $r['visaType'] ?? 'tourist',
                'applicationStatus' => $mapStatus($r['status'] ?? ''),
                'applicationDate' => $r['applicationDate'] ?? '',
                'departureDate' => $r['departureDate'] ?? '',
                'packageName' => $r['packageName'] ?? $r['destinationCountry'] ?? '',
                'processingFee' => $r['processingFee'] ?? 0,
                'createdAt' => $r['submittedAt'] ?? null,
                'updatedAt' => null
            ];
        }
        
        //  
        $statusCounts = [];
        $statuses = ['pending', 'under_review', 'approved', 'rejected'];
        
        foreach ($statuses as $statusType) {
            if ($statusType === 'pending') {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE accountId = ? AND status IN ('pending','document_required')");
                $stmt2->bind_param("i", $accountId);
            } elseif ($statusType === 'under_review') {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE accountId = ? AND status = 'under_review'");
                $stmt2->bind_param("i", $accountId);
            } elseif ($statusType === 'approved') {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE accountId = ? AND status IN ('approved','completed')");
                $stmt2->bind_param("i", $accountId);
            } else { // rejected
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE accountId = ? AND status = 'rejected'");
                $stmt2->bind_param("i", $accountId);
            }
            $stmt2->execute();
            $statusCounts[$statusType] = intval($stmt2->get_result()->fetch_assoc()['count'] ?? 0);
        }
        
        send_json_response([
            'success' => true,
            'data' => [
                'visas' => $visas,
                'statusCounts' => $statusCounts
            ]
        ]);
    }
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => '  : ' . $e->getMessage()
    ], 500);
}
?>



