<?php
// conn.php   (conn.php   )
require __DIR__ . '/../../../backend/conn.php';

//  conn.php    
//     

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Helper function to check if userType is admin (admin_ph or admin_kr)
function isAdminType($type) {
    return in_array($type, ['admin_ph', 'admin_kr', 'admin'], true);
}

//   (admin, agent, guide, cs )
// - ()  (accountId )      
function buildDisplayInfo($conn, $userType, $accountId) {
    $userType = (string)$userType;
    $accountId = (int)$accountId;

    $displayName = '';
    $roleLabel = '';

    //
    if ($userType === 'agent') $roleLabel = 'Agent';
    else if ($userType === 'guide') $roleLabel = 'Guide';
    else if (isAdminType($userType)) $roleLabel = 'Administrator';
    else $roleLabel = 'Employee'; // cs  Employee ()

    //
    if (isAdminType($userType)) {
        $displayName = ($userType === 'admin_kr') ? 'ADMIN (KR)' : 'ADMIN (PH)';
        return ['displayName' => $displayName, 'roleLabel' => $roleLabel];
    }
    if ($userType === 'cs') {
        $displayName = 'CS';
        return ['displayName' => $displayName, 'roleLabel' => $roleLabel];
    }

    // agent: accounts.username
    if ($userType === 'agent') {
        $agentTable = $conn->query("SHOW TABLES LIKE 'agent'");
        if ($agentTable && $agentTable->num_rows > 0) {
            $sql = "SELECT
                        COALESCE(NULLIF(a.username,''), '') AS displayName
                    FROM agent ag
                    LEFT JOIN accounts a ON ag.accountId = a.accountId
                    WHERE ag.accountId = ?
                    ORDER BY ag.id ASC
                    LIMIT 1";
            $st = $conn->prepare($sql);
            if ($st) {
                $st->bind_param('i', $accountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $displayName = (string)($row['displayName'] ?? '');
            }
        }
    }

    // guide: guides.guideName -> accounts.username
    if ($userType === 'guide') {
        $guidesTable = $conn->query("SHOW TABLES LIKE 'guides'");
        if ($guidesTable && $guidesTable->num_rows > 0) {
            $st = $conn->prepare("SELECT COALESCE(NULLIF(g.guideName,''), NULLIF(a.username,''), '') AS displayName
                                  FROM guides g
                                  LEFT JOIN accounts a ON g.accountId = a.accountId
                                  WHERE g.accountId = ?
                                  LIMIT 1");
            if ($st) {
                $st->bind_param('i', $accountId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $displayName = (string)($row['displayName'] ?? '');
            }
        }
    }

    if ($displayName === '') {
        // fallback: accounts.username
        $st = $conn->prepare("SELECT COALESCE(NULLIF(username,''), NULLIF(emailAddress,''), '') AS displayName FROM accounts WHERE accountId = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $accountId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $displayName = (string)($row['displayName'] ?? '');
        }
    }

    return ['displayName' => $displayName, 'roleLabel' => $roleLabel];
}

if (isset($_SESSION['admin_accountId'])) {
    $uid = (int)($_SESSION['admin_accountId'] ?? 0);
    $ut = $_SESSION['admin_userType'] ?? 'admin';
    $info = buildDisplayInfo($conn, $ut, $uid);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'userType' => $ut,
        'accountId' => $uid,
        'emailAddress' => $_SESSION['admin_emailAddress'] ?? '',
        'displayName' => $info['displayName'],
        'roleLabel' => $info['roleLabel']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_SESSION['agent_accountId'])) {
    $uid = (int)($_SESSION['agent_accountId'] ?? 0);
    $ut = $_SESSION['agent_userType'] ?? 'agent';
    $info = buildDisplayInfo($conn, $ut, $uid);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'userType' => $ut,
        'accountId' => $uid,
        'emailAddress' => $_SESSION['agent_emailAddress'] ?? '',
        'displayName' => $info['displayName'],
        'roleLabel' => $info['roleLabel']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_SESSION['guide_accountId'])) {
    $uid = (int)($_SESSION['guide_accountId'] ?? 0);
    $ut = $_SESSION['guide_userType'] ?? 'guide';
    $info = buildDisplayInfo($conn, $ut, $uid);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'userType' => $ut,
        'accountId' => $uid,
        'emailAddress' => $_SESSION['guide_emailAddress'] ?? '',
        'displayName' => $info['displayName'],
        'roleLabel' => $info['roleLabel']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_SESSION['cs_accountId'])) {
    $uid = (int)($_SESSION['cs_accountId'] ?? 0);
    $ut = $_SESSION['cs_userType'] ?? 'cs';
    $info = buildDisplayInfo($conn, $ut, $uid);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'userType' => $ut,
        'accountId' => $uid,
        'emailAddress' => $_SESSION['cs_emailAddress'] ?? '',
        'displayName' => $info['displayName'],
        'roleLabel' => $info['roleLabel']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'authenticated' => false
], JSON_UNESCAPED_UNICODE);
exit;
?>
