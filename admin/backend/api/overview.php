<?php
//   
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require __DIR__ . '/../../../backend/conn.php';

// Email notification service (for auto-cancellation notifications)
$email_service_file = __DIR__ . '/../../../backend/services/email_notification_service.php';
if (file_exists($email_service_file)) {
    require_once $email_service_file;
}

// Booking payments helper library
require_once __DIR__ . '/../../../backend/lib/booking_payments.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// PHP 8+ bind_param    
if (!function_exists('mysqli_bind_params_by_ref')) {
    function mysqli_bind_params_by_ref(mysqli_stmt $stmt, string $types, array &$params): void {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $i => $_) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

//  
if (!isset($_SESSION['admin_accountId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => ' .'], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET  
$period = $_GET['period'] ?? 'daily'; // daily, weekly, monthly, yearly
$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$productPeriod = $_GET['productPeriod'] ?? 'daily';
$productStartDate = $_GET['productStartDate'] ?? null;
$productEndDate = $_GET['productEndDate'] ?? null;

/**
 * packages     
 * package_views( )   / .
 */
function ensurePackageViewsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS package_views (
        packageId INT NOT NULL,
        viewDate DATE NOT NULL,
        viewCount INT NOT NULL DEFAULT 0,
        updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (packageId, viewDate),
        INDEX idx_viewDate (viewDate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

function normalizeDate($s) {
    if (!$s) return null;
    $t = strtotime($s);
    if ($t === false) return null;
    return date('Y-m-d', $t);
}

function getPeriodRange($period) {
    $today = new DateTime('today');
    $start = null;
    $end = null;

    switch ($period) {
        case 'daily':
            $start = clone $today;
            $end = clone $today;
            break;
        case 'weekly':
            // Monday-Sunday
            $weekday = (int)$today->format('N'); // 1(Mon)~7(Sun)
            $start = (clone $today)->modify('-' . ($weekday - 1) . ' days');
            $end = (clone $start)->modify('+6 days');
            break;
        case 'monthly':
            $start = new DateTime(date('Y-m-01'));
            $end = new DateTime(date('Y-m-t'));
            break;
        case 'yearly':
            $start = new DateTime(date('Y-01-01'));
            $end = new DateTime(date('Y-12-31'));
            break;
        default:
            $start = clone $today;
            $end = clone $today;
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function buildSalesBuckets($conn, $period, $startDate, $endDate) {
    $labels = [];
    $values = [];

    if ($period === 'daily') {
        // 00:00-23:00
        $labels = array_map(fn($h) => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00', range(0, 23));
        $values = array_fill(0, 24, 0.0);

        $sql = "SELECT HOUR(createdAt) AS h, SUM(COALESCE(totalAmount,0)) AS amt
                FROM bookings
                WHERE DATE(createdAt) = ?
                GROUP BY HOUR(createdAt)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $startDate);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $h = (int)$row['h'];
            if ($h >= 0 && $h <= 23) $values[$h] = (float)$row['amt'];
        }
        $stmt->close();
        return [$labels, $values];
    }

    if ($period === 'weekly') {
        // Monday-Sunday
        $labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $values = array_fill(0, 7, 0.0);

        $sql = "SELECT WEEKDAY(createdAt) AS wd, SUM(COALESCE(totalAmount,0)) AS amt
                FROM bookings
                WHERE DATE(createdAt) BETWEEN ? AND ?
                GROUP BY WEEKDAY(createdAt)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $wd = (int)$row['wd']; // 0(Mon)~6(Sun)
            if ($wd >= 0 && $wd <= 6) $values[$wd] = (float)$row['amt'];
        }
        $stmt->close();
        return [$labels, $values];
    }

    if ($period === 'monthly') {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $daysInMonth = (int)$end->format('j'); // last day number
        $labels = array_map(fn($d) => (string)$d, range(1, $daysInMonth));
        $values = array_fill(0, $daysInMonth, 0.0);

        $sql = "SELECT DAY(createdAt) AS d, SUM(COALESCE(totalAmount,0)) AS amt
                FROM bookings
                WHERE DATE(createdAt) BETWEEN ? AND ?
                GROUP BY DAY(createdAt)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $d = (int)$row['d']; // 1..31
            if ($d >= 1 && $d <= $daysInMonth) $values[$d - 1] = (float)$row['amt'];
        }
        $stmt->close();
        return [$labels, $values];
    }

    // yearly
    $labels = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $values = array_fill(0, 12, 0.0);

    $sql = "SELECT MONTH(createdAt) AS m, SUM(COALESCE(totalAmount,0)) AS amt
            FROM bookings
            WHERE DATE(createdAt) BETWEEN ? AND ?
            GROUP BY MONTH(createdAt)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $m = (int)$row['m']; // 1..12
        if ($m >= 1 && $m <= 12) $values[$m - 1] = (float)$row['amt'];
    }
    $stmt->close();
    return [$labels, $values];
}

// NOTE: overview.php   (super-api.php getUserInquiries)  ""  
// inquiry_replies  .
function ensureInquiryRepliesTable($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'inquiry_replies'");
    if ($check && $check->num_rows > 0) return;
    $sql = "CREATE TABLE IF NOT EXISTS inquiry_replies (
        replyId INT AUTO_INCREMENT PRIMARY KEY,
        inquiryId INT NOT NULL,
        authorId INT NOT NULL,
        content LONGTEXT NOT NULL,
        isInternal TINYINT(1) NOT NULL DEFAULT 0,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_inquiryId (inquiryId),
        KEY idx_createdAt (createdAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

/**
 * booking_status_history 테이블 존재 확인 및 생성
 */
function ensureBookingStatusHistoryTable($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS booking_status_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bookingId VARCHAR(50) NOT NULL,
                previousStatus VARCHAR(50),
                newStatus VARCHAR(50) NOT NULL,
                changedBy VARCHAR(100),
                changedByType VARCHAR(20),
                changedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bookingId (bookingId),
                INDEX idx_changedAt (changedAt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) { }
}

/**
 * 자동취소 히스토리 기록
 */
function logAutoCancellation($conn, $bookingId, $previousStatus) {
    ensureBookingStatusHistoryTable($conn);
    try {
        $phpTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $changedAt = $phpTime->format('Y-m-d H:i:s');
        $changedBy = 'System (Auto-Cancel)';
        $changedByType = 'system';
        $newStatus = 'cancelled';

        $stmt = $conn->prepare("
            INSERT INTO booking_status_history (bookingId, previousStatus, newStatus, changedBy, changedByType, changedAt)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param('ssssss', $bookingId, $previousStatus, $newStatus, $changedBy, $changedByType, $changedAt);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) { }
}

/**
 * B2B 자동취소:
 * - 선금/잔금 기한 경과 + 증빙 미업로드 -> cancelled
 *   overview 페이지 로드 시 일괄 적용.
 */
function applyB2BAutoCancellation($conn) {
    try {
        // Check for overdue payments from new booking_payments table
        $overduePayments = getOverduePayments($conn, null, 500);

        $hasPackages = false;
        $hasSalesTarget = false;
        $pt = $conn->query("SHOW TABLES LIKE 'packages'");
        $hasPackages = ($pt && $pt->num_rows > 0);
        if ($hasPackages) {
            $st = $conn->query("SHOW COLUMNS FROM packages LIKE 'sales_target'");
            if ($st && $st->num_rows > 0) $hasSalesTarget = true;
        }

        // Process overdue payments from new table
        $processedBookingIds = [];
        foreach ($overduePayments as $overduePayment) {
            $bookingId = $overduePayment['bookingId'];

            // Skip if already processed
            if (in_array($bookingId, $processedBookingIds)) {
                continue;
            }

            // Check B2B condition if applicable
            if ($hasSalesTarget) {
                $pkgStmt = $conn->prepare("SELECT p.sales_target FROM bookings b LEFT JOIN packages p ON b.packageId = p.packageId WHERE b.bookingId = ?");
                $pkgStmt->bind_param('s', $bookingId);
                $pkgStmt->execute();
                $pkgResult = $pkgStmt->get_result()->fetch_assoc();
                $pkgStmt->close();

                if ($pkgResult && $pkgResult['sales_target'] !== 'B2B') {
                    continue; // Skip non-B2B bookings
                }
            }

            // Log and send notification
            logAutoCancellation($conn, $bookingId, $overduePayment['bookingStatus'] ?? '');

            if (function_exists('send_rejection_notification_email')) {
                $reason = 'Payment deadline has passed without payment proof submission.';
                send_rejection_notification_email($conn, $bookingId, 'auto_cancellation', $reason);
            }

            // Cancel the booking
            $cancelStmt = $conn->prepare("UPDATE bookings SET bookingStatus = 'cancelled', paymentStatus = 'failed' WHERE bookingId = ?");
            $cancelStmt->bind_param('s', $bookingId);
            $cancelStmt->execute();
            $cancelStmt->close();

            $processedBookingIds[] = $bookingId;
        }

        // Also check legacy columns for bookings not yet in new table
        $bookingsColumnsCheck = $conn->query("SHOW COLUMNS FROM bookings");
        $cols = [];
        if ($bookingsColumnsCheck) {
            while ($c = $bookingsColumnsCheck->fetch_assoc()) $cols[] = strtolower($c['Field']);
        }

        $hasDownPaymentDueDate = in_array('downpaymentduedate', $cols, true);
        $hasDownPaymentFile = in_array('downpaymentfile', $cols, true);
        $hasAdvancePaymentDueDate = in_array('advancepaymentduedate', $cols, true);
        $hasAdvancePaymentFile = in_array('advancepaymentfile', $cols, true);
        $hasBalanceDueDate = in_array('balanceduedate', $cols, true);
        $hasBalanceFile = in_array('balancefile', $cols, true);
        $hasFullPaymentDueDate = in_array('fullpaymentduedate', $cols, true);
        $hasFullPaymentFile = in_array('fullpaymentfile', $cols, true);

        $hasAnyCancellationCondition = ($hasDownPaymentDueDate && $hasDownPaymentFile)
            || ($hasAdvancePaymentDueDate && $hasAdvancePaymentFile)
            || ($hasBalanceDueDate && $hasBalanceFile)
            || ($hasFullPaymentDueDate && $hasFullPaymentFile);
        if (!$hasAnyCancellationCondition) return;

        $dateExpr = function ($col) {
            return "(CASE
                WHEN CAST($col AS CHAR) REGEXP '^[0-9]{8}$' THEN STR_TO_DATE(CAST($col AS CHAR), '%Y%m%d')
                ELSE DATE($col)
            END)";
        };

        $join = ($hasPackages && $hasSalesTarget) ? "LEFT JOIN packages p ON b.packageId = p.packageId" : "";
        $b2bCond = ($hasPackages && $hasSalesTarget) ? "AND COALESCE(p.sales_target,'') = 'B2B'" : "";

        $conds = [];
        if ($hasDownPaymentDueDate && $hasDownPaymentFile) {
            $conds[] = "(" . $dateExpr('b.downPaymentDueDate') . " IS NOT NULL AND " . $dateExpr('b.downPaymentDueDate') . " < CURDATE() AND COALESCE(b.downPaymentFile,'') = '')";
        }
        if ($hasAdvancePaymentDueDate && $hasAdvancePaymentFile) {
            $conds[] = "(" . $dateExpr('b.advancePaymentDueDate') . " IS NOT NULL AND " . $dateExpr('b.advancePaymentDueDate') . " < CURDATE() AND COALESCE(b.advancePaymentFile,'') = '')";
        }
        if ($hasBalanceDueDate && $hasBalanceFile) {
            $conds[] = "(" . $dateExpr('b.balanceDueDate') . " IS NOT NULL AND " . $dateExpr('b.balanceDueDate') . " < CURDATE() AND COALESCE(b.balanceFile,'') = '')";
        }
        if ($hasFullPaymentDueDate && $hasFullPaymentFile) {
            $conds[] = "(" . $dateExpr('b.fullPaymentDueDate') . " IS NOT NULL AND " . $dateExpr('b.fullPaymentDueDate') . " < CURDATE() AND COALESCE(b.fullPaymentFile,'') = '')";
        }
        if (empty($conds)) return;

        // Exclude already processed bookings
        $excludeCond = "";
        if (!empty($processedBookingIds)) {
            $placeholders = implode(',', array_fill(0, count($processedBookingIds), '?'));
            $excludeCond = "AND b.bookingId NOT IN ($placeholders)";
        }

        $selectSql = "SELECT b.bookingId, b.bookingStatus
                FROM bookings b
                $join
                WHERE COALESCE(b.paymentStatus,'') = 'pending'
                  AND COALESCE(b.bookingStatus,'') NOT IN ('cancelled','confirmed','completed')
                  $b2bCond
                  $excludeCond
                  AND (" . implode(' OR ', $conds) . ")";

        $stmt = $conn->prepare($selectSql);
        if (!empty($processedBookingIds)) {
            $types = str_repeat('s', count($processedBookingIds));
            $stmt->bind_param($types, ...$processedBookingIds);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                logAutoCancellation($conn, $row['bookingId'], $row['bookingStatus'] ?? '');

                if (function_exists('send_rejection_notification_email')) {
                    $reason = 'Payment deadline has passed without payment proof submission.';
                    send_rejection_notification_email($conn, $row['bookingId'], 'auto_cancellation', $reason);
                }
            }
        }
        $stmt->close();

        // UPDATE legacy bookings
        $updateSql = "UPDATE bookings b
                $join
                SET b.bookingStatus='cancelled', b.paymentStatus='failed'
                WHERE COALESCE(b.paymentStatus,'') = 'pending'
                  AND COALESCE(b.bookingStatus,'') NOT IN ('cancelled','confirmed','completed')
                  $b2bCond
                  $excludeCond
                  AND (" . implode(' OR ', $conds) . ")";

        $updateStmt = $conn->prepare($updateSql);
        if (!empty($processedBookingIds)) {
            $types = str_repeat('s', count($processedBookingIds));
            $updateStmt->bind_param($types, ...$processedBookingIds);
        }
        $updateStmt->execute();
        $updateStmt->close();
    } catch (Throwable $e) {
        // ignore
    }
}

try {
    $data = [];
    ensurePackageViewsTable($conn);
    applyB2BAutoCancellation($conn); // 자동취소 활성화

    // 1. 예약 현황: bookingStatus 컬럼 값 기준 카운트 (단순화)
    // - 빈 bookingStatus는 제외
    $bookingStatusSql = "
        SELECT
            SUM(CASE WHEN b.bookingStatus = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN b.bookingStatus IN ('waiting_down_payment', 'checking_down_payment') THEN 1 ELSE 0 END) as waiting_down,
            SUM(CASE WHEN b.bookingStatus IN ('waiting_second_payment', 'checking_second_payment') THEN 1 ELSE 0 END) as waiting_second,
            SUM(CASE WHEN b.bookingStatus IN ('waiting_balance', 'checking_balance') THEN 1 ELSE 0 END) as waiting_balance,
            SUM(CASE WHEN b.bookingStatus = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM bookings b
        WHERE COALESCE(b.bookingStatus, '') != ''
    ";
    $bookingStatusResult = $conn->query($bookingStatusSql);
    $bookingStatus = $bookingStatusResult ? $bookingStatusResult->fetch_assoc() : null;

    $data['bookingStatus'] = [
        'pending' => (int)($bookingStatus['pending'] ?? 0),
        'waitingDown' => (int)($bookingStatus['waiting_down'] ?? 0),
        'waitingSecond' => (int)($bookingStatus['waiting_second'] ?? 0),
        'waitingBalance' => (int)($bookingStatus['waiting_balance'] ?? 0),
        'rejected' => (int)($bookingStatus['rejected'] ?? 0)
    ];

    // bookings 테이블 컬럼 확인 (후속 쿼리에서 사용)
    $bookingsColumnsCheck = $conn->query("SHOW COLUMNS FROM bookings");
    $bookingsColumnsList = [];
    if ($bookingsColumnsCheck) {
        while ($col = $bookingsColumnsCheck->fetch_assoc()) {
            $bookingsColumnsList[] = strtolower($col['Field']);
        }
    }

    // 2.  
    // inquiries   
    $inquiryCheck = $conn->query("SHOW TABLES LIKE 'inquiries'");
    if ($inquiryCheck && $inquiryCheck->num_rows > 0) {
        //   (super-api.php getUserInquiries)   :
        // -  : client   
        // - : inquiry_replies  
        // - : status = 'in_progress'
        ensureInquiryRepliesTable($conn);

        $data['inquiryStatus'] = [
            'unanswered' => 0,
            'processing' => 0
        ];

        $unansweredSql = "SELECT COUNT(*) as count
            FROM inquiries i
            WHERE EXISTS (SELECT 1 FROM client c WHERE c.accountId = i.accountId)
              AND NOT EXISTS (SELECT 1 FROM inquiry_replies ir WHERE ir.inquiryId = i.inquiryId)";
        $result = $conn->query($unansweredSql);
        if ($result) $data['inquiryStatus']['unanswered'] = (int)($result->fetch_assoc()['count'] ?? 0);

        $processingSql = "SELECT COUNT(*) as count
            FROM inquiries i
            WHERE EXISTS (SELECT 1 FROM client c WHERE c.accountId = i.accountId)
              AND i.status = 'in_progress'";
        $result = $conn->query($processingSql);
        if ($result) $data['inquiryStatus']['processing'] = (int)($result->fetch_assoc()['count'] ?? 0);
    } else {
        // inquiries   
        $data['inquiryStatus'] = [
            'unanswered' => 0,
            'processing' => 0
        ];
    }

    // 3.   
    $today = date('Y-m-d');
    
    // bookings   
    $bookingsColumns = [];
    $bookingColumnResult = $conn->query("SHOW COLUMNS FROM bookings");
    if ($bookingColumnResult) {
        while ($col = $bookingColumnResult->fetch_assoc()) {
            $bookingsColumns[] = strtolower($col['Field']);
        }
    }
    
    // packages   
    $packagesTableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
    $hasPackagesTable = ($packagesTableCheck && $packagesTableCheck->num_rows > 0);
    
    // guides   
    $guidesTableCheck = $conn->query("SHOW TABLES LIKE 'guides'");
    $hasGuidesTable = ($guidesTableCheck && $guidesTableCheck->num_rows > 0);
    
    // client   
    $clientTableCheck = $conn->query("SHOW TABLES LIKE 'client'");
    $hasClientTable = ($clientTableCheck && $clientTableCheck->num_rows > 0);

    // accounts   (  B2B/B2C )
    $accountsTableCheck = $conn->query("SHOW TABLES LIKE 'accounts'");
    $hasAccountsTable = ($accountsTableCheck && $accountsTableCheck->num_rows > 0);
    
    // packages   
    $packagesColumns = [];
    if ($hasPackagesTable) {
        $packageColumnResult = $conn->query("SHOW COLUMNS FROM packages");
        if ($packageColumnResult) {
            while ($col = $packageColumnResult->fetch_assoc()) {
                $packagesColumns[] = strtolower($col['Field']);
            }
        }
    }
    
    //  
    $dateColumn = in_array('departuredate', $bookingsColumns) ? 'departureDate' : 
                  (in_array('startdate', $bookingsColumns) ? 'startDate' : 'createdAt');
    //   ( packages )  
    $packageNameColumn = $hasPackagesTable
        ? "COALESCE(p.packageName, b.packageName)"
        : "b.packageName";
    //  (B2B/B2C)
    // - bookings.customerType     
    // -  accounts.accountType/affiliateCode B2B/B2C (guest + affiliateCode  = B2C)
    // -   clientType   B2B fallback
    if (in_array('customertype', $bookingsColumns)) {
        $customerTypeColumn = "CASE WHEN UPPER(COALESCE(b.customerType,'')) = 'B2C' THEN 'B2C' ELSE 'B2B' END";
    } elseif ($hasAccountsTable) {
        $customerTypeColumn = "CASE
            WHEN a.accountType IN ('agent','employee','admin_ph','admin_kr') THEN 'B2B'
            WHEN COALESCE(a.affiliateCode, '') <> '' THEN 'B2B'
            ELSE 'B2C'
        END";
    } else {
        // clientType  overview  B2B/B2C  
        $customerTypeColumn = "'B2B'";
    }
    
    //   
    $travelersSelect = '0';
    if (in_array('adults', $bookingsColumns) || in_array('numberoftravelers', $bookingsColumns)) {
        if (in_array('numberoftravelers', $bookingsColumns)) {
            $travelersSelect = 'b.numberOfTravelers';
        } else {
            $adults = in_array('adults', $bookingsColumns) ? 'b.adults' : '0';
            $children = in_array('children', $bookingsColumns) ? 'b.children' : '0';
            $infants = in_array('infants', $bookingsColumns) ? 'b.infants' : '0';
            $travelersSelect = "COALESCE($adults, 0) + COALESCE($children, 0) + COALESCE($infants, 0)";
        }
    }
    
    //  
    $guideNameSelect = "NULL as guideName";
    if ($hasGuidesTable && in_array('guideid', $bookingsColumns)) {
        $guideNameSelect = "COALESCE(g.guideName, '') as guideName";
    } elseif (in_array('guidename', $bookingsColumns)) {
        $guideNameSelect = 'b.guideName';
    }
    
    // endDate( ) 
    // - bookings returnDate/endDate   
    // -  packages.duration  
    $endDateColumnExpr = '';
    if (in_array('returndate', $bookingsColumns)) {
        $endDateColumnExpr = "DATE(b.returnDate)";
    } elseif (in_array('enddate', $bookingsColumns)) {
        $endDateColumnExpr = "DATE(b.endDate)";
    }

    // returnDate  (fallback:   )
    $hasDurationDays = in_array('duration_days', $packagesColumns) || in_array('durationdays', $packagesColumns);
    $hasDuration = in_array('duration', $packagesColumns);
    $returnDateExpression = '';
    if ($hasDurationDays) {
        $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL (COALESCE(p.duration_days, p.durationDays, 0) - 1) DAY)";
    } elseif ($hasDuration) {
        $returnDateExpression = "DATE_ADD(DATE($dateColumn), INTERVAL (COALESCE(p.duration, 0) - 1) DAY)";
    } else {
        $returnDateExpression = "DATE($dateColumn)";
    }

    $effectiveEndDateExpr = $endDateColumnExpr ? $endDateColumnExpr : $returnDateExpression;
    
    // SQL 
    $selectFields = [
        'b.bookingId',
        $packageNameColumn . ' as packageName',
        "DATE($dateColumn) as startDate",
        $effectiveEndDateExpr . ' as endDate',
        $customerTypeColumn . ' as customerType',
        "$travelersSelect as numberOfTravelers",
        $guideNameSelect
    ];
    
    $fromClause = "FROM bookings b";
    $joinClause = "";

    if ($hasAccountsTable && !in_array('customertype', $bookingsColumns)) {
        $joinClause .= " LEFT JOIN accounts a ON b.accountId = a.accountId";
    }
    
    if ($hasPackagesTable) {
        $joinClause .= " LEFT JOIN packages p ON b.packageId = p.packageId";
    }
    
    if ($hasClientTable && !in_array('customertype', $bookingsColumns)) {
        $joinClause .= " LEFT JOIN client c ON b.accountId = c.accountId";
    }
    
    if ($hasGuidesTable && in_array('guideid', $bookingsColumns)) {
        $joinClause .= " LEFT JOIN guides g ON b.guideId = g.guideId";
    }
    
    //       (startDate <=  <= endDate)
    $todayBookingsSql = "SELECT " . implode(', ', $selectFields) . " 
        $fromClause $joinClause
        WHERE DATE($dateColumn) <= ?
        AND $effectiveEndDateExpr >= ?
        AND b.bookingStatus = 'confirmed'
        ORDER BY $dateColumn DESC, COALESCE(p.packageName, b.packageName) ASC
        LIMIT 20";
    
    $stmt = $conn->prepare($todayBookingsSql);
    if ($stmt) {
        $stmt->bind_param('ss', $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $todayBookings = [];
        while ($row = $result->fetch_assoc()) {
            $todayBookings[] = $row;
        }
        $stmt->close();
        $data['todayBookings'] = $todayBookings;
        $data['todayBookingsCount'] = count($todayBookings);
    } else {
        $data['todayBookings'] = [];
        $data['todayBookingsCount'] = 0;
    }

    // 4.   () +  /
    $startDate = normalizeDate($startDate);
    $endDate = normalizeDate($endDate);
    if (!$startDate || !$endDate) {
        [$startDate, $endDate] = getPeriodRange($period);
    }
    
    $salesSql = "SELECT SUM(COALESCE(totalAmount, 0)) as totalSales, COUNT(*) as totalBookings
                 FROM bookings
                 WHERE DATE(createdAt) BETWEEN ? AND ?";
    $stmt = $conn->prepare($salesSql);
    $sales = ['totalSales' => 0, 'totalBookings' => 0];
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $sales = $stmt->get_result()->fetch_assoc() ?: $sales;
        $stmt->close();
    }
    $data['sales'] = [
        'amount' => (float)($sales['totalSales'] ?? 0),
        'bookings' => (int)($sales['totalBookings'] ?? 0),
        'range' => ['startDate' => $startDate, 'endDate' => $endDate]
    ];

    [$chartLabels, $chartValues] = buildSalesBuckets($conn, $period, $startDate, $endDate);
    $data['chart'] = [
        'period' => $period,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'labels' => $chartLabels,
        'values' => $chartValues
    ];

    // 5.    () + (views) +    viewCount 
    $productSales = [];
    if ($hasPackagesTable) {
        // packages   
        $packagesColumnsCheck = $conn->query("SHOW COLUMNS FROM packages");
        $packagesColumnsList = [];
        if ($packagesColumnsCheck) {
            while ($col = $packagesColumnsCheck->fetch_assoc()) {
                $packagesColumnsList[] = strtolower($col['Field']);
            }
        }
        
        // packages    
        $packageNameCol = in_array('packagename', $bookingsColumns) ? 'b.packageName' : 'p.packageName';
        $totalAmountCol = in_array('totalamount', $bookingsColumns) ? 'b.totalAmount' : 
                         (in_array('packageprice', $bookingsColumns) ? 'b.packagePrice' : '0');
        
        // packages    package_views      
        $viewCountCol = "COALESCE(v.totalViews, 0) as viewCount";
        
        $productSalesWhere = [];
        $productSalesParams = [];
        $productSalesTypes = '';
        
        $productStartDate = normalizeDate($productStartDate);
        $productEndDate = normalizeDate($productEndDate);

        if ($productStartDate && $productEndDate) {
            $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
            $productSalesParams[] = $productStartDate;
            $productSalesParams[] = $productEndDate;
            $productSalesTypes = 'ss';
        } elseif ($productPeriod !== 'all') {
            //   
            switch($productPeriod) {
                case 'daily':
                    [$ps, $pe] = getPeriodRange('daily');
                    $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
                    $productSalesParams[] = $ps;
                    $productSalesParams[] = $pe;
                    $productSalesTypes = 'ss';
                    break;
                case 'weekly':
                    [$ps, $pe] = getPeriodRange('weekly');
                    $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
                    $productSalesParams[] = $ps;
                    $productSalesParams[] = $pe;
                    $productSalesTypes = 'ss';
                    break;
                case 'monthly':
                    [$ps, $pe] = getPeriodRange('monthly');
                    $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
                    $productSalesParams[] = $ps;
                    $productSalesParams[] = $pe;
                    $productSalesTypes = 'ss';
                    break;
                case 'yearly':
                    [$ps, $pe] = getPeriodRange('yearly');
                    $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
                    $productSalesParams[] = $ps;
                    $productSalesParams[] = $pe;
                    $productSalesTypes = 'ss';
                    break;
                default:
                    [$ps, $pe] = getPeriodRange('daily');
                    $productSalesWhere[] = "DATE(b.createdAt) BETWEEN ? AND ?";
                    $productSalesParams[] = $ps;
                    $productSalesParams[] = $pe;
                    $productSalesTypes = 'ss';
            }
        }
        // 'all'  WHERE  
        // , UI    bookings  min/max  
        $productRange = ['startDate' => null, 'endDate' => null];
        // productSalesWhere DATE(b.createdAt) alias(b)  rangeSql  alias  .
        $rangeSql = "SELECT MIN(DATE(b.createdAt)) as startDate, MAX(DATE(b.createdAt)) as endDate FROM bookings b";
        if (!empty($productSalesWhere)) {
            $rangeSql .= " WHERE " . implode(' AND ', $productSalesWhere);
        }
        $rangeStmt = $conn->prepare($rangeSql);
        if ($rangeStmt) {
            if (!empty($productSalesParams)) {
                mysqli_bind_params_by_ref($rangeStmt, $productSalesTypes, $productSalesParams);
            }
            $rangeStmt->execute();
            $productRange = $rangeStmt->get_result()->fetch_assoc() ?: $productRange;
            $rangeStmt->close();
        }
        
        $productSalesSql = "SELECT 
            p.packageId as packageId,
            $packageNameCol as packageName,
            COUNT(*) as bookingCount,
            SUM(COALESCE($totalAmountCol, 0)) as totalAmount,
            $viewCountCol
            FROM bookings b
            LEFT JOIN packages p ON b.packageId = p.packageId
            LEFT JOIN (
                SELECT packageId, SUM(viewCount) as totalViews
                FROM package_views
                " . (!empty($productSalesWhere) ? "WHERE viewDate BETWEEN ? AND ?" : "") . "
                GROUP BY packageId
            ) v ON v.packageId = p.packageId";
        
        if (!empty($productSalesWhere)) {
            $productSalesSql .= " WHERE " . implode(' AND ', $productSalesWhere);
        }
        
        $productSalesSql .= " GROUP BY p.packageId, $packageNameCol";
        
        $productSalesSql .= " ORDER BY bookingCount DESC
            LIMIT 10";
        
        $productSalesStmt = $conn->prepare($productSalesSql);
        if ($productSalesStmt) {
            // viewDate   (package_views ) start/end 2  
            $bindTypes = '';
            $bindParams = [];
            if (!empty($productSalesWhere)) {
                $bindTypes .= 'ss';
                $bindParams[] = ($productSalesParams[0] ?? $productRange['startDate'] ?? date('Y-m-d'));
                $bindParams[] = ($productSalesParams[1] ?? $productRange['endDate'] ?? date('Y-m-d'));
            }
            if (!empty($productSalesParams)) {
                $bindTypes .= $productSalesTypes;
                $bindParams = array_merge($bindParams, $productSalesParams);
            }
            if ($bindTypes !== '') {
                mysqli_bind_params_by_ref($productSalesStmt, $bindTypes, $bindParams);
            }
            $productSalesStmt->execute();
            $result = $productSalesStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $productSales[] = $row;
            }
            $productSalesStmt->close();
        }
    }
    $data['productSales'] = $productSales;
    $data['productSalesRange'] = [
        'startDate' => $productRange['startDate'] ?? null,
        'endDate' => $productRange['endDate'] ?? null
    ];

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $errorTrace = $e->getTraceAsString();
    
    error_log("Overview API error: $errorMsg in $errorFile:$errorLine");
    error_log("Trace: $errorTrace");
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '    .',
        'error' => $errorMsg,
        'file' => basename($errorFile),
        'line' => $errorLine
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    $errorMsg = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    
    error_log("Overview API fatal error: $errorMsg in $errorFile:$errorLine");
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '    .',
        'error' => $errorMsg,
        'file' => basename($errorFile),
        'line' => $errorLine
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

