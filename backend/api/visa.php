<?php
require "../conn.php";

//    /  updatedAt        .
// (   :  /  updatedAt  )
try {
    $t = $conn->query("SHOW TABLES LIKE 'visa_applications'");
    if ($t && $t->num_rows > 0) {
        $c = $conn->query("SHOW COLUMNS FROM visa_applications LIKE 'updatedAt'");
        if ($c && $c->num_rows === 0) {
            $conn->query("ALTER TABLE visa_applications ADD COLUMN updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }
} catch (Throwable $e) {
    // ignore
}

// GET/POST   
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    //    GET ( WebView blob    )
    $action = $_GET['action'] ?? '';
    if ($action === 'download_visa') {
        handleDownloadVisa($_GET);
    } else {
        handleGetVisaApplications();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_visa_application':
            handleCreateVisaApplication($input);
            break;
        case 'get_visa_application':
        case 'get_application': // JavaScript   
            handleGetVisaApplication($input);
            break;
        case 'update_visa_application':
        case 'update_application': // JavaScript   
            handleUpdateVisaApplication($input);
            break;
        case 'cancel_visa_application':
            handleCancelVisaApplication($input);
            break;
        case 'upload_document':
            handleUploadDocument($input);
            break;
        case 'get_visa_status':
            handleGetVisaStatus($input);
            break;
        case 'create_resubmission':
            handleCreateResubmission($input);
            break;
        case 'download_document':
            handleDownloadDocument($input);
            break;
        case 'download_visa': // JavaScript  (  )
            handleDownloadVisa($input);
            break;
        default:
            send_json_response(['success' => false, 'message' => ' .'], 400);
    }
} else {
    send_json_response(['success' => false, 'message' => 'GET  POST  .'], 405);
}

function visa_table_has_column(mysqli $conn, string $col): bool {
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM visa_applications LIKE '$c'");
    return $r && $r->num_rows > 0;
}

function visa_status_to_ui(string $dbStatus): string {
    $s = strtolower(trim($dbStatus));
    if ($s === 'document_required') return 'inadequate';
    if ($s === 'under_review') return 'processing';
    if ($s === 'approved' || $s === 'completed') return 'approved';
    if ($s === 'rejected') return 'rejected';
    return 'pending';
}

function visa_status_from_ui(string $uiStatus): string {
    $s = strtolower(trim($uiStatus));
    // (JS)   DB enum 
    if ($s === 'processing' || $s === 'under_review') return 'under_review';
    if ($s === 'approved') return 'approved';
    if ($s === 'rejected') return 'rejected';
    if ($s === 'inadequate' || $s === 'pending' || $s === 'document_required') return 'document_required';
    return 'pending';
}

function visa_parse_notes_documents($notes) {
    if ($notes === null) return null;
    $txt = trim((string)$notes);
    if ($txt === '') return null;
    $j = json_decode($txt, true);
    if (is_array($j) && isset($j['documents'])) return $j['documents'];
    return null;
}

function visa_merge_notes_with_documents($existingNotes, $documents) {
    $base = [];
    $txt = trim((string)($existingNotes ?? ''));
    if ($txt !== '') {
        $j = json_decode($txt, true);
        if (is_array($j)) $base = $j;
        else $base = ['notesText' => $txt];
    }
    $base['documents'] = $documents;
    return json_encode($base, JSON_UNESCAPED_UNICODE);
}

function visa_required_document_keys(): array {
    return ['photo', 'passport', 'bankCertificate', 'bankStatement', 'itinerary'];
}

function visa_document_titles(): array {
    return [
        'photo' => 'ID Photo (Within the last 6 months)',
        'passport' => 'Passport copy',
        'bankCertificate' => 'Bank Account Certificate',
        'bankStatement' => 'Bank transaction history for the last 3 months',
        'itinerary' => 'Travel Itinerary Including Flight Information'
    ];
}

// 비자 타입별 서류 설정
function visa_document_config_by_type(string $visaType): array {
    if (strtolower($visaType) === 'individual') {
        // Individual: 다운로드만 (업로드 없음)
        return [
            ['key' => 'visaApplicationForm', 'title' => 'Visa Application Form', 'required' => false, 'multiple' => false, 'download' => true, 'downloadPath' => '/uploads/visa/doc/Korea-visa-application.pdf'],
            ['key' => 'dataPrivacyConsent', 'title' => 'Signed Data Privacy Consent Form', 'required' => false, 'multiple' => false, 'download' => true, 'downloadPath' => '/uploads/visa/doc/Data-Privacy-Consent-Form_KVAC.pdf']
        ];
    }

    // Group: 업로드 필요
    return [
        ['key' => 'passport', 'title' => 'Passport bio page', 'required' => true, 'multiple' => false],
        ['key' => 'visaApplicationForm', 'title' => 'Visa Application Form', 'required' => true, 'multiple' => false, 'download' => true, 'downloadPath' => '/uploads/visa/doc/Korea-visa-application.pdf'],
        ['key' => 'bankCertificate', 'title' => 'Bank Certificate', 'required' => true, 'multiple' => false],
        ['key' => 'bankStatement', 'title' => 'Bank Statement', 'required' => true, 'multiple' => false],
        ['key' => 'additionalDocuments', 'title' => 'Additional requirements', 'required' => false, 'multiple' => true],
    ];
}

// 비자 타입별 필수 서류 키 목록
function visa_required_document_keys_by_type(string $visaType): array {
    $config = visa_document_config_by_type($visaType);
    $required = [];
    foreach ($config as $doc) {
        if ($doc['required'] === true) {
            $required[] = $doc['key'];
        }
    }
    return $required;
}

// 비자 타입별 미제출 서류 확인
function visa_documents_missing_keys_by_type($docs, string $visaType): array {
    $docs = is_array($docs) ? $docs : [];
    $missing = [];
    $requiredKeys = visa_required_document_keys_by_type($visaType);

    foreach ($requiredKeys as $k) {
        // additionalDocuments는 선택사항
        if ($k === 'additionalDocuments') continue;

        $v = isset($docs[$k]) ? $docs[$k] : '';
        // 배열인 경우 (다중 업로드)
        if (is_array($v)) {
            if (count($v) === 0) $missing[] = $k;
        } else {
            if (trim((string)$v) === '') $missing[] = $k;
        }
    }
    return $missing;
}

function visa_normalize_path($p): string {
    $p = trim((string)$p);
    if ($p === '') return '';
    $p = str_replace('\\', '/', $p);
    // DB "uploads/.."  "/uploads/.."    
    if (str_starts_with($p, 'uploads/')) return '/' . $p;
    return $p;
}

function visa_extract_visa_file_from_notes($notes): string {
    $j = visa_parse_notes($notes);
    if (!is_array($j)) return '';
    $vf = trim((string)($j['visaFile'] ?? ($j['visa_file'] ?? ($j['visaUrl'] ?? ($j['visaDocument'] ?? '')))));
    return visa_normalize_path($vf);
}

function visa_documents_merge_keep_existing($existingNotes, array $newDocs): string {
    $base = visa_parse_notes($existingNotes);
    if (!is_array($base)) $base = [];
    $cur = [];
    if (isset($base['documents']) && is_array($base['documents'])) $cur = $base['documents'];
    foreach ($newDocs as $k => $v) {
        $kk = (string)$k;
        if ($kk === '') continue;

        // 배열인 경우 (다중 파일 업로드)
        if (is_array($v)) {
            $normalized = [];
            foreach ($v as $path) {
                $normalizedPath = visa_normalize_path($path);
                if ($normalizedPath !== '') {
                    $normalized[] = $normalizedPath;
                }
            }
            if (!empty($normalized)) {
                // 기존 배열에 추가
                $existing = isset($cur[$kk]) && is_array($cur[$kk]) ? $cur[$kk] : [];
                $cur[$kk] = array_merge($existing, $normalized);
            }
        } else {
            // 단일 파일
            $vv = visa_normalize_path($v);
            if ($vv === '') continue;
            $cur[$kk] = $vv;
        }
    }
    $base['documents'] = $cur;
    return json_encode($base, JSON_UNESCAPED_UNICODE);
}

function visa_documents_missing_keys($docs): array {
    $docs = is_array($docs) ? $docs : [];
    $missing = [];
    foreach (visa_required_document_keys() as $k) {
        $v = isset($docs[$k]) ? trim((string)$docs[$k]) : '';
        if ($v === '') $missing[] = $k;
    }
    return $missing;
}

function visa_is_all_required_documents_present($docs): bool {
    return count(visa_documents_missing_keys($docs)) === 0;
}

function visa_parse_notes($notes) {
    if ($notes === null) return null;
    $txt = trim((string)$notes);
    if ($txt === '') return null;
    $j = json_decode($txt, true);
    return is_array($j) ? $j : null;
}

function visa_merge_notes_set_key($existingNotes, string $key, $value): string {
    $base = [];
    $txt = trim((string)($existingNotes ?? ''));
    if ($txt !== '') {
        $j = json_decode($txt, true);
        if (is_array($j)) $base = $j;
        else $base = ['notesText' => $txt];
    }
    $base[$key] = $value;
    return json_encode($base, JSON_UNESCAPED_UNICODE);
}

function visa_notification_title_from_db_status(string $dbStatus): string {
    $s = strtolower(trim($dbStatus));
    if ($s === 'document_required' || $s === 'pending') return 'Incomplete documents';
    if ($s === 'under_review') return 'Under review';
    if ($s === 'approved' || $s === 'completed') return 'Issuance completed';
    if ($s === 'rejected') return 'Rejected';
    return 'Under review';
}

function visa_notify_status_change_safe(int $accountId, $applicationId, string $dbStatus): void {
    global $conn;
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$tableCheck || $tableCheck->num_rows === 0) return;

        $title = visa_notification_title_from_db_status($dbStatus);
        $message = 'Your visa application status has been changed.';
        $appIdStr = (string)$applicationId;
        $actionUrl = 'visa-detail-completion.php?id=' . rawurlencode($appIdStr);

        //        actionUrl  ( UI  )
        $s = strtolower(trim($dbStatus));
        if ($s === 'document_required' || $s === 'pending') $actionUrl = 'visa-detail-inadequate.html?id=' . rawurlencode($appIdStr);
        else if ($s === 'under_review') $actionUrl = 'visa-detail-examination.html?id=' . rawurlencode($appIdStr);
        else if ($s === 'rejected') $actionUrl = 'visa-detail-rebellion.html?id=' . rawurlencode($appIdStr);
        else if ($s === 'approved' || $s === 'completed') $actionUrl = 'visa-detail-completion.php?id=' . rawurlencode($appIdStr);

        // notifications  : notificationType enum(booking/payment/visa/general/promotional)
        $stmt = $conn->prepare("
            INSERT INTO notifications (accountId, notificationType, title, message, isRead, priority, actionUrl, createdAt)
            VALUES (?, 'visa', ?, ?, 0, 'high', ?, NOW())
        ");
        if (!$stmt) return;
        $stmt->bind_param('isss', $accountId, $title, $message, $actionUrl);
        @$stmt->execute();
        @$stmt->close();
    } catch (Throwable $e) {
        // ignore
    }
}

//    
function handleGetVisaApplications() {
    global $conn;
    
    $accountId = $_GET['accountId'] ?? '';
    $status = $_GET['status'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    if (empty($accountId)) {
        send_json_response(['success' => false, 'message' => ' ID .'], 400);
    }
    
    try {
        // visa_applications   
        $tableCheck = $conn->query("SHOW TABLES LIKE 'visa_applications'");
        
        if ($tableCheck->num_rows === 0) {
            //      
            sendDefaultVisaApplications($status);
            return;
        }
        
        //   (applicationId/applicationNo/destinationCountry ) 
        if (visa_table_has_column($conn, 'applicationId') && visa_table_has_column($conn, 'applicationNo')) {
            $query = "
                SELECT 
                    va.applicationId,
                    va.applicationNo,
                    va.applicantName,
                    va.visaType,
                    va.destinationCountry,
                    va.status,
                    va.applicationDate,
                    va.departureDate,
                    va.returnDate,
                    va.notes,
                    va.submittedAt,
                    va.processedAt,
                    va.completedAt
                FROM visa_applications va
                WHERE va.accountId = ?
            ";
        } else {
            // legacy schema fallback
            $query = "
                SELECT 
                    va.visaApplicationId,
                    va.bookingId,
                    va.applicantName,
                    va.passportNumber,
                    va.nationality,
                    va.visaType,
                    va.status,
                    va.applicationDate,
                    va.expectedProcessingDate,
                    va.actualProcessingDate,
                    va.notes,
                    va.documents,
                    b.departureDate,
                    p.packageName,
                    p.packageDestination
                FROM visa_applications va
                LEFT JOIN bookings b ON va.bookingId = b.bookingId
                LEFT JOIN packages p ON b.packageId = p.packageId
                WHERE va.accountId = ?
            ";
        }
        
        $params = [$accountId];
        $types = 'i';
        
        if (!empty($status)) {
            $st = strtolower(trim((string)$status));
            if (visa_table_has_column($conn, 'applicationId')) {
                // UI(status)  DB status  
                if ($st === 'pending') {
                    $query .= " AND va.status IN ('pending','document_required')";
                } elseif ($st === 'inadequate') {
                    $query .= " AND va.status = 'document_required'";
                } elseif ($st === 'processing' || $st === 'under_review') {
                    $query .= " AND va.status = 'under_review'";
                } elseif ($st === 'approved') {
                    $query .= " AND va.status IN ('approved','completed')";
                } elseif ($st === 'rejected') {
                    $query .= " AND va.status = 'rejected'";
                }
            } else {
                $query .= " AND va.status = ?";
                $params[] = $status;
                $types .= 's';
            }
        }
        
        $query .= " ORDER BY va.applicationDate DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            // new schema
            if (isset($row['applicationId'])) {
                $applications[] = [
                    'applicationId' => intval($row['applicationId']),
                    'applicationNo' => $row['applicationNo'] ?? '',
                    'applicantName' => $row['applicantName'] ?? '',
                    'visaType' => $row['visaType'] ?? 'tourist',
                    'destination' => $row['destinationCountry'] ?? '',
                    'status' => visa_status_to_ui($row['status'] ?? 'pending'),
                    'rawStatus' => $row['status'] ?? 'pending',
                    'applicationDate' => $row['applicationDate'] ?? '',
                    'departureDate' => $row['departureDate'] ?? '',
                    'returnDate' => $row['returnDate'] ?? '',
                    'expectedDate' => $row['processedAt'] ?? null,
                    'notes' => $row['notes'] ?? '',
                    'documents' => []
                ];
            } else {
                // legacy schema
                $row['documents'] = json_decode($row['documents'], true) ?: [];
                $applications[] = $row;
            }
        }
        
        //   
        $statusCounts = getVisaStatusCounts($accountId);
        
        log_activity($accountId, "visa_applications_retrieved", "Visa applications retrieved for user: {$accountId}");
        
        send_json_response([
            'success' => true,
            'data' => [
                'applications' => $applications,
                'statusCounts' => $statusCounts
            ]
        ]);
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "visa_applications_error", "Get visa applications error: " . $e->getMessage());
        sendDefaultVisaApplications($status);
    }
}

//   
function handleCreateVisaApplication($input) {
    global $conn;
    
    $accountId = $input['accountId'] ?? '';
    $bookingId = $input['bookingId'] ?? '';
    $applicantName = $input['applicantName'] ?? '';
    $passportNumber = $input['passportNumber'] ?? '';
    $nationality = $input['nationality'] ?? '';
    $visaType = $input['visaType'] ?? '';
    $documents = $input['documents'] ?? [];
    
    //   
    if (empty($accountId) || empty($bookingId) || empty($applicantName) || empty($passportNumber)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    try {
        $conn->begin_transaction();
        
        //   ID 
        $visaApplicationId = generateVisaApplicationId();
        
        //    
        $stmt = $conn->prepare("
            INSERT INTO visa_applications (
                visaApplicationId, accountId, bookingId, applicantName, 
                passportNumber, nationality, visaType, status, 
                applicationDate, documents
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
        ");
        
        $documentsJson = json_encode($documents);
        $stmt->bind_param("ssisssss", $visaApplicationId, $accountId, $bookingId, 
                          $applicantName, $passportNumber, $nationality, $visaType, $documentsJson);
        
        if (!$stmt->execute()) {
            throw new Exception("   : " . $stmt->error);
        }
        
        $conn->commit();
        
        log_activity($accountId, "visa_application_created", "Visa application created: {$visaApplicationId} for user: {$accountId}");
        
        send_json_response([
            'success' => true,
            'message' => '  .',
            'data' => [
                'visaApplicationId' => $visaApplicationId
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        log_activity($accountId ?? 0, "visa_application_create_error", "Create visa application error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '     .'], 500);
    }
}

//   
function handleGetVisaApplication($input) {
    global $conn;
    
    $visaApplicationId = $input['visaApplicationId'] ?? ($input['applicationId'] ?? ($input['application_id'] ?? ''));
    $accountId = $input['accountId'] ?? '';
    
    if (empty($visaApplicationId)) {
        send_json_response(['success' => false, 'message' => '  ID .'], 400);
    }
    
    try {
        // new schema
        if (visa_table_has_column($conn, 'applicationId')) {
            if (!is_numeric($visaApplicationId)) {
                send_json_response(['success' => false, 'message' => '    .'], 404);
            }
            $appId = intval($visaApplicationId);
            $query = "SELECT * FROM visa_applications WHERE applicationId = ?";
            $params = [$appId];
            $types = 'i';
            if (!empty($accountId)) {
                $query .= " AND accountId = ?";
                $params[] = intval($accountId);
                $types .= 'i';
            }
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                send_json_response(['success' => false, 'message' => '    .'], 404);
            }
            $r = $result->fetch_assoc();
            $stmt->close();

            $uiStatus = visa_status_to_ui($r['status'] ?? 'pending');
            $docsFromNotes = visa_parse_notes_documents($r['notes'] ?? null);
            $docsFromNotes = is_array($docsFromNotes) ? $docsFromNotes : [];
            //  normalize ("/uploads/.."  )
            foreach ($docsFromNotes as $k => $v) {
                // 배열인 경우 (다중 파일)
                if (is_array($v)) {
                    $docsFromNotes[$k] = array_map('visa_normalize_path', $v);
                } else {
                    $docsFromNotes[$k] = visa_normalize_path($v);
                }
            }
            $visaFile = visa_extract_visa_file_from_notes($r['notes'] ?? null);

            // 비자 타입 기반 서류 요구사항
            $visaType = $r['visaType'] ?? 'individual';
            $documentConfig = visa_document_config_by_type($visaType);
            $missing = visa_documents_missing_keys_by_type($docsFromNotes, $visaType);

            $requiredDocsForResubmit = [];
            foreach ($documentConfig as $doc) {
                if (in_array($doc['key'], $missing)) {
                    $requiredDocsForResubmit[] = [
                        'key' => $doc['key'],
                        'title' => $doc['title'],
                        'required' => $doc['required'],
                        'multiple' => $doc['multiple'] ?? false
                    ];
                }
            }
            $data = [
                'applicationId' => intval($r['applicationId']),
                'applicationNo' => $r['applicationNo'] ?? '',
                'accountId' => intval($r['accountId']),
                'applicantName' => $r['applicantName'] ?? '',
                'visaType' => $visaType,
                'destination' => $r['destinationCountry'] ?? '',
                'status' => $uiStatus,
                'rawStatus' => $r['status'] ?? 'pending',
                'applicationDate' => $r['applicationDate'] ?? '',
                'departureDate' => $r['departureDate'] ?? '',
                'returnDate' => $r['returnDate'] ?? '',
                'expectedDate' => $r['processedAt'] ?? null,
                // documents: {photo:"/uploads/..", ...}
                'documents' => $docsFromNotes,
                //  /  "  "  ( )
                'inadequateDocuments' => $missing,
                //  () - notes plain text
                'rejectionReasons' => (($r['status'] ?? '') === 'rejected' && !empty($r['notes']) && !is_array(visa_parse_notes($r['notes'] ?? null))) ? [trim((string)$r['notes'])] : [],
                //      (:  )
                'requiredDocuments' => $requiredDocsForResubmit,
                //    (    )
                'visaFile' => $visaFile,
                // 비자 타입별 전체 서류 요구사항
                'documentRequirements' => $documentConfig,
                // visaSend (Individual 전용)
                'visaSend' => isset($r['visaSend']) ? (int)$r['visaSend'] : null
            ];
            send_json_response(['success' => true, 'data' => $data]);
        }

        $query = "
            SELECT 
                va.*,
                b.departureDate,
                b.adults,
                b.children,
                b.infants,
                p.packageName,
                p.packageDestination,
                COALESCE((SELECT MAX(day_number) FROM package_schedules WHERE package_id = p.packageId), p.duration_days, 1) as duration_days
            FROM visa_applications va
            LEFT JOIN bookings b ON va.bookingId = b.bookingId
            LEFT JOIN packages p ON b.packageId = p.packageId
            WHERE va.visaApplicationId = ?
        ";
        
        $params = [$visaApplicationId];
        $types = 's';
        
        if (!empty($accountId)) {
            $query .= " AND va.accountId = ?";
            $params[] = $accountId;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '    .'], 404);
        }
        
        $application = $result->fetch_assoc();
        $application['documents'] = json_decode($application['documents'], true) ?: [];
        
        send_json_response([
            'success' => true,
            'data' => $application
        ]);
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "visa_application_get_error", "Get visa application error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   (        )
function handleDownloadVisa($input) {
    global $conn;

    $applicationId = $input['applicationId'] ?? $input['application_id'] ?? $input['visaApplicationId'] ?? ($input['id'] ?? '');
    if (empty($applicationId) || !is_numeric($applicationId)) {
        send_json_response(['success' => false, 'message' => '  ID .'], 400);
    }
    $appId = intval($applicationId);

    //     ()
    $sid = $_SESSION['accountId'] ?? null;

    $stmt = $conn->prepare("SELECT accountId, notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
    if (!$stmt) {
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        send_json_response(['success' => false, 'message' => '    .'], 404);
    }

    if ($sid !== null && (int)$sid > 0 && (int)$sid !== (int)($row['accountId'] ?? 0)) {
        send_json_response(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $notes = $row['notes'] ?? null;
    $j = visa_parse_notes($notes);
    $visaFile = '';
    if (is_array($j)) {
        $visaFile = trim((string)($j['visaFile'] ?? $j['visa_file'] ?? $j['visaUrl'] ?? $j['visaDocument'] ?? ''));
    }

    if ($visaFile === '') {
        send_json_response(['success' => false, 'message' => '  .'], 404);
    }

    // download.php   : uploads  
    $visaFile = str_replace('\\', '/', $visaFile);
    if (str_starts_with($visaFile, 'uploads/')) $rel = '/' . $visaFile;
    else $rel = $visaFile;
    if (!str_starts_with($rel, '/uploads/') || str_contains($rel, '..')) {
        send_json_response(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $abs = realpath(__DIR__ . '/../../' . ltrim($rel, '/'));
    $uploadsRoot = realpath(__DIR__ . '/../../uploads');
    if ($abs === false || $uploadsRoot === false) {
        send_json_response(['success' => false, 'message' => '   .'], 404);
    }
    if (!str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($abs) || !is_readable($abs)) {
        send_json_response(['success' => false, 'message' => '   .'], 404);
    }

    $name = basename($abs);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    if ($ext === 'pdf') $mime = 'application/pdf';
    else if (in_array($ext, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
    else if ($ext === 'png') $mime = 'image/png';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    header('Content-Length: ' . (string)filesize($abs));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($abs);
    exit;
}

//   
function handleUpdateVisaApplication($input) {
    global $conn;
    
    $visaApplicationId = $input['visaApplicationId'] ?? '';
    $updateData = $input['updateData'] ?? [];
    
    if (empty($visaApplicationId) || empty($updateData)) {
        send_json_response(['success' => false, 'message' => '  ID   .'], 400);
    }
    
    try {
        //  (applicationId/applicationNo...) 
        if (visa_table_has_column($conn, 'applicationId')) {
            if (!is_numeric($visaApplicationId)) {
                send_json_response(['success' => false, 'message' => '    .'], 404);
            }

            $appId = intval($visaApplicationId);

            // notes documents(JSON)  (  documents  )
            $documents = $updateData['documents'] ?? null;
            $notes = $updateData['notes'] ?? null;
            $status = isset($updateData['status']) ? visa_status_from_ui((string)$updateData['status']) : null;
            $visaFile = $updateData['visaFile'] ?? ($updateData['visa_file'] ?? ($updateData['visaUrl'] ?? null));
            $visaSend = isset($updateData['visaSend']) ? (int)$updateData['visaSend'] : null;

            //  notes (merge)
            $existingNotes = null;
            $existingStatus = null;
            $existingAccountId = null;
            $st0 = $conn->prepare("SELECT notes FROM visa_applications WHERE applicationId = ? LIMIT 1");
            if ($st0) {
                $st0->bind_param('i', $appId);
                $st0->execute();
                $existingNotes = ($st0->get_result()->fetch_assoc()['notes'] ?? null);
                $st0->close();
            }
            //  status/accountId  ( )
            $st1 = $conn->prepare("SELECT status, accountId FROM visa_applications WHERE applicationId = ? LIMIT 1");
            if ($st1) {
                $st1->bind_param('i', $appId);
                $st1->execute();
                $row1 = $st1->get_result()->fetch_assoc();
                $st1->close();
                $existingStatus = $row1['status'] ?? null;
                $existingAccountId = isset($row1['accountId']) ? (int)$row1['accountId'] : null;
            }

            $set = [];
            $params = [];
            $types = '';

            if ($status !== null) {
                $set[] = "status = ?";
                $params[] = $status;
                $types .= 's';
            }

            if ($visaSend !== null) {
                $set[] = "visaSend = ?";
                $params[] = $visaSend;
                $types .= 'i';
            }

            // documents/notes 
            $finalNotes = $existingNotes;
            if ($documents !== null) {
                // documents partial    (    )
                $docMap = [];
                if (is_array($documents)) {
                    $isList = array_keys($documents) === range(0, count($documents) - 1);
                    if ($isList) {
                        foreach ($documents as $d) {
                            if (!is_array($d)) continue;
                            $k = (string)($d['document_type'] ?? $d['type'] ?? '');
                            $u = (string)($d['file_url'] ?? $d['url'] ?? '');
                            if ($k !== '' && $u !== '') $docMap[$k] = $u;
                        }
                    } else {
                        $docMap = $documents;
                    }
                }
                $finalNotes = visa_documents_merge_keep_existing($existingNotes, $docMap);
            } elseif ($notes !== null) {
                $finalNotes = (string)$notes;
            }

            //    ( JSON visaFile)
            if ($visaFile !== null) {
                $vf = trim((string)$visaFile);
                if ($vf !== '') {
                    $finalNotes = visa_merge_notes_set_key($finalNotes, 'visaFile', $vf);
                }
            }

            if ($finalNotes !== null) {
                $set[] = "notes = ?";
                $params[] = $finalNotes;
                $types .= 's';
            }

            if (empty($set)) {
                send_json_response(['success' => false, 'message' => '  .'], 400);
            }

            $params[] = $appId;
            $types .= 'i';

            $query = "UPDATE visa_applications SET " . implode(', ', $set) . " WHERE applicationId = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                send_json_response(['success' => false, 'message' => '  .'], 500);
            }
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                //     ( )
                try {
                    if ($status !== null && $existingAccountId !== null) {
                        $prev = strtolower(trim((string)($existingStatus ?? '')));
                        $next = strtolower(trim((string)$status));
                        if ($prev !== $next) {
                            visa_notify_status_change_safe((int)$existingAccountId, (int)$appId, (string)$status);
                        }
                    }
                } catch (Throwable $e) {
                    // ignore
                }
                send_json_response(['success' => true, 'message' => '  .']);
            }
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }

        $allowedFields = ['applicantName', 'passportNumber', 'nationality', 'visaType', 'status', 'notes', 'documents'];
        $updateFields = [];
        $params = [];
        $types = '';

        //  (visaApplicationId)       status/accountId 
        $prevStatus = null;
        $prevAccountId = null;
        $stPrev = $conn->prepare("SELECT status, accountId FROM visa_applications WHERE visaApplicationId = ? LIMIT 1");
        if ($stPrev) {
            $stPrev->bind_param("s", $visaApplicationId);
            $stPrev->execute();
            $rowPrev = $stPrev->get_result()->fetch_assoc();
            $stPrev->close();
            $prevStatus = $rowPrev['status'] ?? null;
            $prevAccountId = isset($rowPrev['accountId']) ? (int)$rowPrev['accountId'] : null;
        }
        $nextStatus = null;
        
        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'documents') {
                    $value = json_encode($value);
                }
                if ($field === 'status') {
                    //  UI (processing/inadequate/approved/rejected )    DB enum 
                    $value = visa_status_from_ui((string)$value);
                    $nextStatus = (string)$value;
                }
                $updateFields[] = "{$field} = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
        
        if (empty($updateFields)) {
            send_json_response(['success' => false, 'message' => '  .'], 400);
        }
        
        $params[] = $visaApplicationId;
        $types .= 's';
        
        $query = "UPDATE visa_applications SET " . implode(', ', $updateFields) . " WHERE visaApplicationId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            //      ( )
            try {
                if ($nextStatus !== null && $prevAccountId !== null) {
                    $prev = strtolower(trim((string)($prevStatus ?? '')));
                    $next = strtolower(trim((string)$nextStatus));
                    if ($prev !== $next) {
                        visa_notify_status_change_safe((int)$prevAccountId, (string)$visaApplicationId, (string)$nextStatus);
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
            log_activity($input['accountId'] ?? 0, "visa_application_updated", "Visa application updated: {$visaApplicationId}");
            send_json_response([
                'success' => true,
                'message' => '  .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "visa_application_update_error", "Update visa application error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   :  applicationId documents  + status under_review 
// (:   )
function handleCreateResubmission($input) {
    global $conn;

    //  (applicationId ) " =   " 
    if (visa_table_has_column($conn, 'applicationId')) {
        $originalId = $input['original_application_id'] ?? ($input['applicationId'] ?? ($input['application_id'] ?? ($input['visaApplicationId'] ?? '')));
        if (empty($originalId) || !is_numeric($originalId)) {
            send_json_response(['success' => false, 'message' => '  ID .'], 400);
        }
        $appId = intval($originalId);
        $documents = $input['documents'] ?? [];
        $reason = (string)($input['reason'] ?? ($input['resubmissionReason'] ?? ''));

        try {
            // 기존 notes와 visaType 조회
            $existingNotes = null;
            $visaType = 'individual';
            $st0 = $conn->prepare("SELECT notes, visaType FROM visa_applications WHERE applicationId = ? LIMIT 1");
            if ($st0) {
                $st0->bind_param('i', $appId);
                $st0->execute();
                $row = $st0->get_result()->fetch_assoc();
                $existingNotes = ($row['notes'] ?? null);
                $visaType = $row['visaType'] ?? 'individual';
                $st0->close();
            }

            $finalNotes = $existingNotes;
            if (!empty($documents)) {
                //  :      " documents +  " .
                $docMap = [];
                if (is_array($documents)) {
                    $isList = array_keys($documents) === range(0, count($documents) - 1);
                    if ($isList) {
                        foreach ($documents as $d) {
                            if (!is_array($d)) continue;
                            $k = (string)($d['document_type'] ?? $d['type'] ?? '');
                            $u = (string)($d['file_url'] ?? $d['url'] ?? '');
                            if ($k !== '' && $u !== '') $docMap[$k] = $u;
                        }
                    } else {
                        $docMap = $documents;
                    }
                }
                $finalNotes = visa_documents_merge_keep_existing($existingNotes, $docMap);
            }

            if ($reason !== '') {
                $finalNotes = visa_merge_notes_set_key($finalNotes, 'resubmissionReason', $reason);
            }

            // 검증: 비자 타입에 맞는 필수 서류가 모두 있는지 확인
            $mergedDocs = visa_parse_notes_documents($finalNotes);
            $mergedDocs = is_array($mergedDocs) ? $mergedDocs : [];
            $missingKeys = visa_documents_missing_keys_by_type($mergedDocs, $visaType);
            if (count($missingKeys) > 0) {
                send_json_response(['success' => false, 'message' => 'Please upload all required documents. Missing: ' . implode(', ', $missingKeys)], 400);
            }

            $stmt = $conn->prepare("UPDATE visa_applications SET status = 'under_review', notes = ? WHERE applicationId = ?");
            if (!$stmt) {
                send_json_response(['success' => false, 'message' => '  .'], 500);
            }
            $stmt->bind_param('si', $finalNotes, $appId);
            $stmt->execute();
            $stmt->close();

            send_json_response([
                'success' => true,
                'message' => ' .',
                'applicationId' => $appId,
                'data' => ['applicationId' => $appId]
            ]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => '    .'], 500);
        }
    }

    // =====  :    =====
    global $conn;

    $visaApplicationId = $input['visaApplicationId'] ?? '';
    $accountId = $input['accountId'] ?? '';
    $reason = $input['reason'] ?? '';
    $documents = $input['documents'] ?? [];

    if (empty($visaApplicationId) || empty($accountId)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }

    try {
        $conn->begin_transaction();

        //     
        $stmt = $conn->prepare("
            SELECT applicantName, passportNumber, nationality, visaType, bookingId
            FROM visa_applications
            WHERE visaApplicationId = ? AND accountId = ?
        ");
        $stmt->bind_param("si", $visaApplicationId, $accountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("    .");
        }

        $originalApplication = $result->fetch_assoc();

        //   ID 
        $resubmissionId = generateVisaApplicationId();

        //   
        $stmt = $conn->prepare("
            INSERT INTO visa_applications (
                visaApplicationId, accountId, bookingId, applicantName,
                passportNumber, nationality, visaType, status,
                applicationDate, documents, parentApplicationId,
                resubmissionReason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?)
        ");

        $documentsJson = json_encode($documents);
        // 10 params →  10
        $stmt->bind_param("siisssssss",
                          $resubmissionId, $accountId, $originalApplication['bookingId'],
                          $originalApplication['applicantName'], $originalApplication['passportNumber'],
                          $originalApplication['nationality'], $originalApplication['visaType'],
                          $documentsJson, $visaApplicationId, $reason);

        if (!$stmt->execute()) {
            throw new Exception("  : " . $stmt->error);
        }

        //    'resubmitted' 
        $stmt = $conn->prepare("
            UPDATE visa_applications
            SET status = 'resubmitted', resubmittedAt = NOW()
            WHERE visaApplicationId = ?
        ");
        $stmt->bind_param("s", $visaApplicationId);

        if (!$stmt->execute()) {
            throw new Exception("    ");
        }

        $conn->commit();

        log_activity($accountId, "visa_resubmission_created", "Visa resubmission created: {$resubmissionId} for original: {$visaApplicationId}");

        send_json_response([
            'success' => true,
            'message' => ' .',
            'data' => [
                'resubmissionId' => $resubmissionId,
                'originalApplicationId' => $visaApplicationId,
                'applicationId' => $resubmissionId
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        log_activity($accountId ?? 0, "visa_resubmission_error", "Create resubmission error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '    .'], 500);
    }
}

//   
function handleCancelVisaApplication($input) {
    global $conn;
    
    $visaApplicationId = $input['visaApplicationId'] ?? '';
    $accountId = $input['accountId'] ?? '';
    $reason = $input['reason'] ?? '';
    
    if (empty($visaApplicationId)) {
        send_json_response(['success' => false, 'message' => '  ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE visa_applications 
            SET status = 'cancelled', 
                cancelledAt = NOW(),
                cancellationReason = ?
            WHERE visaApplicationId = ?
        ");
        
        $stmt->bind_param("ss", $reason, $visaApplicationId);
        
        if ($stmt->execute()) {
            log_activity($accountId, "visa_application_cancelled", "Visa application cancelled: {$visaApplicationId} by user: {$accountId}");
            send_json_response([
                'success' => true,
                'message' => '  .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '   .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($accountId ?? 0, "visa_application_cancel_error", "Cancel visa application error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//  
function handleUploadDocument($input) {
    global $conn;
    
    $visaApplicationId = $input['visaApplicationId'] ?? '';
    $documentType = $input['documentType'] ?? '';
    $documentUrl = $input['documentUrl'] ?? '';
    $documentName = $input['documentName'] ?? '';
    
    if (empty($visaApplicationId) || empty($documentType) || empty($documentUrl)) {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }
    
    try {
        //    
        $stmt = $conn->prepare("SELECT documents FROM visa_applications WHERE visaApplicationId = ?");
        $stmt->bind_param("s", $visaApplicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '    .'], 404);
        }
        
        $row = $result->fetch_assoc();
        $documents = json_decode($row['documents'], true) ?: [];
        
        //   
        $documents[] = [
            'type' => $documentType,
            'name' => $documentName,
            'url' => $documentUrl,
            'uploadedAt' => date('Y-m-d H:i:s')
        ];
        
        //   
        $documentsJson = json_encode($documents);
        $stmt = $conn->prepare("UPDATE visa_applications SET documents = ? WHERE visaApplicationId = ?");
        $stmt->bind_param("ss", $documentsJson, $visaApplicationId);
        
        if ($stmt->execute()) {
            log_activity($input['accountId'] ?? 0, "visa_document_uploaded", "Document uploaded for visa application: {$visaApplicationId}");
            send_json_response([
                'success' => true,
                'message' => ' .'
            ]);
        } else {
            send_json_response(['success' => false, 'message' => '  .'], 500);
        }
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "visa_document_upload_error", "Upload document error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function handleGetVisaStatus($input) {
    global $conn;
    
    $visaApplicationId = $input['visaApplicationId'] ?? '';
    
    if (empty($visaApplicationId)) {
        send_json_response(['success' => false, 'message' => '  ID .'], 400);
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                status,
                applicationDate,
                expectedProcessingDate,
                actualProcessingDate,
                notes
            FROM visa_applications 
            WHERE visaApplicationId = ?
        ");
        
        $stmt->bind_param("s", $visaApplicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '    .'], 404);
        }
        
        $status = $result->fetch_assoc();
        
        send_json_response([
            'success' => true,
            'data' => $status
        ]);
        
    } catch (Exception $e) {
        log_activity($input['accountId'] ?? 0, "visa_status_error", "Get visa status error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   
function getVisaStatusCounts($accountId) {
    global $conn;
    
    try {
        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
        $counts = [];
        
        foreach ($statuses as $status) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM visa_applications 
                WHERE accountId = ? AND status = ?
            ");
            $stmt->bind_param("is", $accountId, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $counts[$status] = $result->fetch_assoc()['count'];
        }
        
        return $counts;
    } catch (Exception $e) {
        return [];
    }
}

//    
function sendDefaultVisaApplications($status = '') {
    $applications = [
        [
            'visaApplicationId' => 'VA20250120001',
            'bookingId' => 'BK20250124001',
            'applicantName' => 'Jose Ramirez',
            'passportNumber' => 'P123456789',
            'nationality' => 'Philippines',
            'visaType' => 'Tourist',
            'status' => 'pending',
            'applicationDate' => '2025-01-20',
            'expectedProcessingDate' => '2025-02-15',
            'actualProcessingDate' => null,
            'notes' => '  ',
            'documents' => [],
            'departureDate' => '2025-04-19',
            'packageName' => '     5 6',
            'packageDestination' => ', '
        ],
        [
            'visaApplicationId' => 'VA20250120002',
            'bookingId' => 'BK20250124002',
            'applicantName' => 'Maria Santos',
            'passportNumber' => 'P987654321',
            'nationality' => 'Philippines',
            'visaType' => 'Tourist',
            'status' => 'under_review',
            'applicationDate' => '2025-01-18',
            'expectedProcessingDate' => '2025-02-10',
            'actualProcessingDate' => null,
            'notes' => ' ',
            'documents' => [],
            'departureDate' => '2025-03-15',
            'packageName' => '   3 4',
            'packageDestination' => ', '
        ]
    ];
    
    //  
    if (!empty($status)) {
        $applications = array_filter($applications, function($app) use ($status) {
            return $app['status'] === $status;
        });
    }
    
    //  
    $statusCounts = [
        'pending' => 1,
        'under_review' => 1,
        'approved' => 0,
        'rejected' => 0,
        'cancelled' => 0
    ];
    
    send_json_response([
        'success' => true,
        'data' => [
            'applications' => array_values($applications),
            'statusCounts' => $statusCounts
        ]
    ]);
}

//  
function handleDownloadDocument($input) {
    global $conn;

    $visaApplicationId = $input['visaApplicationId'] ?? '';
    $documentIndex = $input['documentIndex'] ?? '';
    $accountId = $input['accountId'] ?? '';

    if (empty($visaApplicationId) || $documentIndex === '') {
        send_json_response(['success' => false, 'message' => '  .'], 400);
    }

    try {
        //     
        $query = "
            SELECT documents, applicantName
            FROM visa_applications
            WHERE visaApplicationId = ?
        ";

        $params = [$visaApplicationId];
        $types = 's';

        if (!empty($accountId)) {
            $query .= " AND accountId = ?";
            $params[] = $accountId;
            $types .= 'i';
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_json_response(['success' => false, 'message' => '    .'], 404);
        }

        $application = $result->fetch_assoc();
        $documents = json_decode($application['documents'], true) ?: [];

        if (!isset($documents[$documentIndex])) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }

        $document = $documents[$documentIndex];

        //     (    )
        $filePath = $document['url'];
        $fileName = $document['name'] ?? 'document_' . ($documentIndex + 1);

        //    
        if (!file_exists($filePath)) {
            send_json_response(['success' => false, 'message' => '   .'], 404);
        }

        log_activity($accountId ?? 0, "visa_document_downloaded", "Document downloaded: {$visaApplicationId} - document {$documentIndex}");

        //     
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        //   
        readfile($filePath);
        exit;

    } catch (Exception $e) {
        log_activity($accountId ?? 0, "visa_document_download_error", "Download document error: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '  .'], 500);
    }
}

//   ID 
function generateVisaApplicationId() {
    $prefix = 'VA';
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}
?>
